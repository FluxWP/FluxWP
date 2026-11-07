<?php
/**
 * Ayar köprüsü: GET/POST /flxapp/v1/settings/<option>. Yalnız kullanıcının gördüğü bir manifest `settings`
 * ekranında (`option`) bildirilen seçenek ve alanlar açılır; alan türleri `flx_settings_schema`'dan gelir.
 * Gizli alanlar (secret, password, token, key) asla dönmez; boş gönderilirse eski değer kalır.
 * Yazma yetkisi: ekranın `can` koşulu (manifest) + `manage_options` (ekran `write_cap` ile değiştirebilir).
 */

defined( 'ABSPATH' ) || exit;

/** Kullanıcının görebildiği settings ekranları: option => [ fields(adlar), write_cap ]. */
function flxapp_settings_screens( $user = null ) {
	$out = array();
	foreach ( flxapp_user_manifest( $user ) as $m ) {
		foreach ( (array) $m['screens'] as $s ) {
			if ( 'settings' !== ( $s['type'] ?? '' ) || empty( $s['option'] ) ) {
				continue;
			}
			$opt  = sanitize_key( (string) $s['option'] );
			$keys = array();
			foreach ( (array) ( $s['fields'] ?? array() ) as $f ) {
				if ( ! empty( $f['name'] ) ) {
					$keys[] = (string) $f['name'];
				}
			}
			$out[ $opt ] = array(
				'fields'    => array_merge( $out[ $opt ]['fields'] ?? array(), $keys ),
				'write_cap' => (string) ( $s['write_cap'] ?? 'manage_options' ),
				'secret'    => array_merge( $out[ $opt ]['secret'] ?? array(), array_values( array_filter( $keys, fn( $k ) => flxapp_settings_is_secret( $k, (array) $s['fields'] ) ) ) ),
			);
		}
	}
	return $out;
}

function flxapp_settings_is_secret( $key, array $fields = array() ) {
	foreach ( $fields as $f ) {
		if ( ( $f['name'] ?? '' ) === $key && in_array( $f['type'] ?? '', array( 'secret' ), true ) ) {
			return true;
		}
	}
	return (bool) preg_match( '/(secret|password|passwd|token|api_?key|private|_key$|^key$)/i', (string) $key );
}

function flxapp_settings_schema_fields( $opt ) {
	$s = (array) apply_filters( 'flx_settings_schema', array() );
	return (array) ( $s[ $opt ]['fields'] ?? array() );
}

/** Şemadaki türe göre değer temizliği. null = reddedildi. */
function flxapp_settings_clean_value( $value, array $def ) {
	switch ( (string) ( $def['type'] ?? 'string' ) ) {
		case 'bool':
			return rest_sanitize_boolean( $value ) ? 1 : 0;
		case 'int':
		case 'page':
			return is_numeric( $value ) ? (int) $value : null;
		case 'enum':
			return in_array( (string) $value, array_map( 'strval', (array) ( $def['enum'] ?? array() ) ), true ) ? (string) $value : null;
		case 'color':
			$c = sanitize_hex_color( (string) $value );
			return null === $c ? null : (string) $c;
		case 'url':
			return esc_url_raw( (string) $value );
		case 'text':
			return sanitize_textarea_field( (string) $value );
		case 'html':
			return current_user_can( 'unfiltered_html' ) ? (string) $value : wp_kses_post( (string) $value );
		case 'object':
		case 'list':
			return is_array( $value ) ? map_deep( $value, 'sanitize_text_field' ) : null;
		default:
			return sanitize_text_field( (string) $value );
	}
}

function flxapp_settings_get( $opt, array $screen ) {
	$cur    = get_option( $opt, array() );
	$cur    = is_array( $cur ) ? $cur : array();
	$schema = flxapp_settings_schema_fields( $opt );
	$values = array();
	$saved  = array();
	foreach ( array_unique( $screen['fields'] ) as $k ) {
		if ( in_array( $k, $screen['secret'], true ) ) {
			$saved[ $k ] = isset( $cur[ $k ] ) && '' !== (string) $cur[ $k ];
			continue;
		}
		$values[ $k ] = array_key_exists( $k, $cur ) ? $cur[ $k ] : ( $schema[ $k ]['default'] ?? null );
	}
	return array(
		'option' => $opt,
		'values' => (object) $values,
		'saved'  => (object) $saved,
		'schema' => (object) array_intersect_key( $schema, array_flip( $screen['fields'] ) ),
	);
}

function flxapp_rest_settings( WP_REST_Request $req ) {
	$opt     = sanitize_key( (string) $req['option'] );
	$screens = flxapp_settings_screens();
	if ( ! isset( $screens[ $opt ] ) ) {
		return new WP_Error( 'flxapp_not_found', __( 'Bu ayar uygulamadan açılamaz.', 'flx-app' ), array( 'status' => 404 ) );
	}
	$screen = $screens[ $opt ];
	if ( 'GET' === $req->get_method() ) {
		return flxapp_settings_get( $opt, $screen );
	}
	if ( ! current_user_can( $screen['write_cap'] ) ) {
		return new WP_Error( 'rest_forbidden', __( 'Bu işlem için yetkiniz yok.', 'flx-app' ), array( 'status' => 403 ) );
	}
	$in     = (array) $req->get_json_params() + (array) $req->get_body_params();
	$in     = isset( $in['values'] ) && is_array( $in['values'] ) ? $in['values'] : $in;
	$schema = flxapp_settings_schema_fields( $opt );
	$cur    = get_option( $opt, array() );
	$cur    = is_array( $cur ) ? $cur : array();
	$errors = array();
	foreach ( array_unique( $screen['fields'] ) as $k ) {
		if ( ! array_key_exists( $k, $in ) ) {
			continue;
		}
		if ( in_array( $k, $screen['secret'], true ) && '' === (string) $in[ $k ] ) {
			continue;
		}
		$v = flxapp_settings_clean_value( $in[ $k ], (array) ( $schema[ $k ] ?? array() ) );
		if ( null === $v ) {
			$errors[ $k ] = __( 'Geçersiz değer.', 'flx-app' );
			continue;
		}
		$cur[ $k ] = $v;
	}
	if ( $errors ) {
		return new WP_Error( 'rest_invalid_param', __( 'Bazı alanlar geçersiz.', 'flx-app' ), array( 'status' => 400, 'params' => $errors ) );
	}
	update_option( $opt, $cur );
	return flxapp_settings_get( $opt, $screen );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'flxapp/v1',
			'/settings/(?P<option>[a-z0-9_\-]+)',
			array(
				array( 'methods' => 'GET', 'callback' => 'flxapp_rest_settings', 'permission_callback' => 'flxapp_logged_in' ),
				array( 'methods' => 'POST', 'callback' => 'flxapp_rest_settings', 'permission_callback' => 'flxapp_logged_in' ),
			)
		);
	}
);
