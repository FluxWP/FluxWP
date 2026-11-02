<?php
/**
 * OAuth istemcileri. Yerleşik: Flux Masaüstü (genel istemci, PKCE).
 * Diğerleri `flxapp_clients` seçeneğinde: yönetici ekler ya da MCP istemcileri dinamik kaydolur (RFC 7591).
 */

defined( 'ABSPATH' ) || exit;

const FLXAPP_DESKTOP_CLIENT = 'flx-masaustu';

function flxapp_builtin_clients() {
	return (array) apply_filters(
		'flxapp_builtin_clients',
		array(
			FLXAPP_DESKTOP_CLIENT => array(
				'id'            => FLXAPP_DESKTOP_CLIENT,
				'name'          => __( 'Flux Masaüstü', 'flx-app' ),
				'redirect_uris' => array( 'flxmasaustu://oauth/callback', 'http://127.0.0.1/callback', 'http://localhost/callback' ),
				'secret_hash'   => '',
				'builtin'       => true,
				'dynamic'       => false,
				'created'       => 0,
			),
		)
	);
}

function flxapp_stored_clients() {
	return (array) get_option( 'flxapp_clients', array() );
}

function flxapp_clients() {
	return array_merge( flxapp_stored_clients(), flxapp_builtin_clients() );
}

function flxapp_client( $id ) {
	$all = flxapp_clients();
	return is_string( $id ) && isset( $all[ $id ] ) ? $all[ $id ] : null;
}

function flxapp_is_loopback( $host ) {
	return in_array( strtolower( trim( (string) $host, '[]' ) ), array( '127.0.0.1', 'localhost', '::1' ), true );
}

/**
 * Yönlendirme adresi geçerli mi? Tam eşleşme; loopback adreslerde port serbest (RFC 8252 §7.3).
 */
function flxapp_redirect_ok( array $client, $uri ) {
	if ( ! is_string( $uri ) || '' === $uri || strlen( $uri ) > 500 ) {
		return false;
	}
	$p = wp_parse_url( $uri );
	if ( ! $p || empty( $p['scheme'] ) || ! empty( $p['fragment'] ) ) {
		return false;
	}
	foreach ( (array) $client['redirect_uris'] as $reg ) {
		if ( $reg === $uri ) {
			return true;
		}
		$r = wp_parse_url( $reg );
		if ( $r && 'http' === ( $r['scheme'] ?? '' ) && 'http' === $p['scheme'] && flxapp_is_loopback( $r['host'] ?? '' ) && flxapp_is_loopback( $p['host'] ?? '' )
			&& ( $r['path'] ?? '/' ) === ( $p['path'] ?? '/' ) && ( $r['query'] ?? '' ) === ( $p['query'] ?? '' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Kayıt için yönlendirme adresi biçimi uygun mu? https, loopback http ya da uygulamaya özel şema.
 */
function flxapp_redirect_allowed_format( $uri ) {
	$p = wp_parse_url( (string) $uri );
	if ( ! $p || empty( $p['scheme'] ) || ! empty( $p['fragment'] ) || strlen( (string) $uri ) > 500 ) {
		return false;
	}
	$scheme = strtolower( $p['scheme'] );
	if ( 'https' === $scheme ) {
		return ! empty( $p['host'] );
	}
	if ( 'http' === $scheme ) {
		return flxapp_is_loopback( $p['host'] ?? '' );
	}
	if ( in_array( $scheme, array( 'javascript', 'data', 'file', 'vbscript', 'blob', 'about' ), true ) ) {
		return false;
	}
	return (bool) preg_match( '/^[a-z][a-z0-9+.\-]{2,}$/', $scheme );
}

/**
 * İstemci ekler. Gizli anahtar istenirse düz metni bir kez döner.
 *
 * @return array|WP_Error [ client, secret ]
 */
function flxapp_add_client( $name, array $uris, $confidential = false, $dynamic = false ) {
	$name = sanitize_text_field( $name );
	$uris = array_values( array_unique( array_filter( array_map( 'trim', $uris ) ) ) );
	if ( '' === $name || ! $uris || count( $uris ) > 10 ) {
		return new WP_Error( 'invalid_client_metadata', __( 'Ad ve en az bir yönlendirme adresi gerekli (en çok 10).', 'flx-app' ) );
	}
	foreach ( $uris as $u ) {
		if ( ! flxapp_redirect_allowed_format( $u ) ) {
			return new WP_Error(
				'invalid_redirect_uri',
				/* translators: %s: redirect URI */
				sprintf( __( 'Yönlendirme adresi kabul edilmedi: %s (https, 127.0.0.1 ya da uygulama şeması olmalı).', 'flx-app' ), $u )
			);
		}
	}
	$all = flxapp_stored_clients();
	if ( $dynamic && count( array_filter( $all, function ( $c ) { return ! empty( $c['dynamic'] ); } ) ) >= 100 ) {
		return new WP_Error( 'invalid_client_metadata', __( 'Dinamik istemci sınırı doldu; eskileri silin.', 'flx-app' ) );
	}
	$id     = 'flxc_' . strtolower( wp_generate_password( 16, false ) );
	$secret = $confidential ? flxapp_random( 'flxcs_' ) : '';
	$client = array(
		'id'            => $id,
		'name'          => mb_substr( $name, 0, 80 ),
		'redirect_uris' => $uris,
		'secret_hash'   => $secret ? flxapp_hash( $secret ) : '',
		'builtin'       => false,
		'dynamic'       => (bool) $dynamic,
		'created'       => time(),
		'user'          => get_current_user_id(),
	);
	$all[ $id ] = $client;
	update_option( 'flxapp_clients', $all, false );
	return array( $client, $secret );
}

function flxapp_delete_client( $id ) {
	global $wpdb;
	$all = flxapp_stored_clients();
	if ( ! isset( $all[ $id ] ) ) {
		return false;
	}
	unset( $all[ $id ] );
	update_option( 'flxapp_clients', $all, false );
	$wpdb->update( flxapp_table(), array( 'revoked' => 1 ), array( 'client_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	return true;
}

/** Gizli anahtarlı istemcide doğrulama; genel istemcide true. */
function flxapp_client_auth_ok( array $client, $secret ) {
	if ( empty( $client['secret_hash'] ) ) {
		return true;
	}
	return is_string( $secret ) && '' !== $secret && hash_equals( $client['secret_hash'], flxapp_hash( $secret ) );
}
