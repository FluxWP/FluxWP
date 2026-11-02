<?php
/**
 * Hesap bağlama: OAuth başlat → sağlayıcı → callback → (birden çok sayfa/kanal varsa) seçim.
 * Callback herkese açık uçtur; güven state (tek kullanımlık, 15 dk, kullanıcıya bağlı) ile sağlanır.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			FLXSM_NS,
			'/oauth/start',
			array(
				'methods'             => 'GET',
				'callback'            => 'flxsm_oauth_start',
				'permission_callback' => fn() => flxsm_can_manage(),
			)
		);
		register_rest_route(
			FLXSM_NS,
			'/oauth/callback/(?P<app>[a-z]+)',
			array(
				'methods'             => 'GET',
				'callback'            => 'flxsm_oauth_callback',
				'permission_callback' => '__return_true', // sağlayıcıdan dönüş; state ile doğrulanır
			)
		);
		register_rest_route(
			FLXSM_NS,
			'/oauth/pick/(?P<key>[a-zA-Z0-9]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => 'flxsm_oauth_pick_get',
					'permission_callback' => fn() => flxsm_can_manage(),
				),
				array(
					'methods'             => 'POST',
					'callback'            => 'flxsm_oauth_pick_save',
					'permission_callback' => fn() => flxsm_can_manage(),
				),
			)
		);
	}
);

function flxsm_app_url( $hash = '' ) {
	return admin_url( 'admin.php?page=flx-social' ) . ( $hash ? '#' . $hash : '' );
}

function flxsm_redirect_response( $url ) {
	$r = new WP_REST_Response( null, 302 );
	$r->header( 'Location', $url );
	$r->header( 'Cache-Control', 'no-store' );
	return $r;
}

function flxsm_oauth_start( WP_REST_Request $req ) {
	$for = sanitize_key( (string) $req->get_param( 'for' ) );
	$map = flxsm_connect_target( $for );
	if ( ! $map ) {
		return flxsm_redirect_response( flxsm_app_url( '/accounts?error=' . rawurlencode( __( 'Bilinmeyen ağ.', 'flx-social' ) ) ) );
	}
	list( $pid, $app ) = $map;
	$prov              = flxsm_provider( $pid );
	$state             = wp_generate_password( 32, false );
	$ctx               = array(
		'state'    => $state,
		'for'      => $for,
		'provider' => $pid,
		'app'      => $app,
		'user'     => get_current_user_id(),
		'verifier' => wp_generate_password( 64, false ),
	);
	$url               = $prov ? $prov->auth_url( $ctx ) : new WP_Error( 'flxsm_provider', 'provider' );
	if ( is_wp_error( $url ) ) {
		return flxsm_redirect_response( flxsm_app_url( '/accounts?error=' . rawurlencode( $url->get_error_message() ) ) );
	}
	set_transient( 'flxsm_oa_' . $state, $ctx, 15 * MINUTE_IN_SECONDS );
	return flxsm_redirect_response( $url );
}

function flxsm_oauth_callback( WP_REST_Request $req ) {
	$app    = sanitize_key( $req['app'] );
	$params = $req->get_query_params();
	$state  = (string) ( $params['state'] ?? '' );
	if ( '' === $state && 'x' === $app ) {
		$state = flxsm_provider( 'x' )->state_from_callback( $params );
	}
	$ctx = $state ? get_transient( 'flxsm_oa_' . $state ) : false;
	if ( ! is_array( $ctx ) || $ctx['app'] !== $app ) {
		return flxsm_redirect_response( flxsm_app_url( '/accounts?error=' . rawurlencode( __( 'Bağlantı oturumu bulunamadı ya da süresi doldu; yeniden deneyin.', 'flx-social' ) ) ) );
	}
	delete_transient( 'flxsm_oa_' . $state );
	if ( ! empty( $params['error'] ) && empty( $params['code'] ) ) {
		$msg = (string) ( $params['error_description'] ?? $params['error_message'] ?? $params['error'] );
		return flxsm_redirect_response( flxsm_app_url( '/accounts?error=' . rawurlencode( mb_substr( sanitize_text_field( $msg ), 0, 200 ) ) ) );
	}
	$prov = flxsm_provider( $ctx['provider'] );
	$list = $prov ? $prov->exchange( $params, $ctx ) : new WP_Error( 'flxsm_provider', 'provider' );
	if ( is_wp_error( $list ) ) {
		flxsm_log( $ctx['provider'] . ' connect: ' . $list->get_error_message(), 'error' );
		return flxsm_redirect_response( flxsm_app_url( '/accounts?error=' . rawurlencode( mb_substr( $list->get_error_message(), 0, 300 ) ) ) );
	}
	wp_set_current_user( (int) $ctx['user'] );
	/* Zaten bağlı hesapların belirteci kendiliğinden yenilenir; yeni olanlar seçime düşer. */
	$new     = array();
	$updated = 0;
	foreach ( $list as $c ) {
		if ( flxsm_account_exists( $c['provider'], $c['ext_id'] ) ) {
			flxsm_upsert_account( $c );
			++$updated;
		} else {
			$new[] = $c;
		}
	}
	if ( 1 === count( $list ) && $new ) {
		flxsm_upsert_account( $new[0] );
		return flxsm_redirect_response( flxsm_app_url( '/accounts?added=1' ) );
	}
	if ( ! $new ) {
		return flxsm_redirect_response( flxsm_app_url( '/accounts?updated=' . $updated ) );
	}
	$key = wp_generate_password( 24, false );
	set_transient(
		'flxsm_pick_' . $key,
		array(
			'user' => (int) $ctx['user'],
			'list' => $new,
		),
		20 * MINUTE_IN_SECONDS
	);
	return flxsm_redirect_response( flxsm_app_url( '/accounts?pick=' . $key . ( $updated ? '&updated=' . $updated : '' ) ) );
}

function flxsm_account_exists( $provider, $ext_id ) {
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . flxsm_table( 'accounts' ) . ' WHERE provider = %s AND ext_id = %s', $provider, $ext_id ) ); // phpcs:ignore WordPress.DB
}

function flxsm_pick_data( $key ) {
	$d = get_transient( 'flxsm_pick_' . $key );
	return is_array( $d ) && (int) $d['user'] === get_current_user_id() ? $d : null;
}

function flxsm_oauth_pick_get( WP_REST_Request $req ) {
	$d = flxsm_pick_data( $req['key'] );
	if ( ! $d ) {
		return new WP_Error( 'flxsm_pick', __( 'Seçim listesinin süresi doldu; yeniden bağlayın.', 'flx-social' ), array( 'status' => 404 ) );
	}
	$out = array();
	foreach ( $d['list'] as $c ) {
		$prov  = flxsm_provider( $c['provider'] );
		$out[] = array(
			'id'       => $c['provider'] . ':' . $c['ext_id'],
			'provider' => $c['provider'],
			'network'  => $prov ? $prov->label() : $c['provider'],
			'name'     => $c['name'],
			'username' => $c['username'],
			'avatar'   => $c['avatar'],
			'kind'     => (string) ( $c['meta']['kind'] ?? '' ),
		);
	}
	return rest_ensure_response( array( 'items' => $out ) );
}

function flxsm_oauth_pick_save( WP_REST_Request $req ) {
	$d = flxsm_pick_data( $req['key'] );
	if ( ! $d ) {
		return new WP_Error( 'flxsm_pick', __( 'Seçim listesinin süresi doldu; yeniden bağlayın.', 'flx-social' ), array( 'status' => 404 ) );
	}
	$want = array_map( 'strval', (array) $req->get_param( 'ids' ) );
	$n    = 0;
	foreach ( $d['list'] as $c ) {
		if ( in_array( $c['provider'] . ':' . $c['ext_id'], $want, true ) ) {
			flxsm_upsert_account( $c );
			++$n;
		}
	}
	delete_transient( 'flxsm_pick_' . $req['key'] );
	return rest_ensure_response(
		array(
			'added'    => $n,
			'accounts' => flxsm_accounts(),
		)
	);
}
