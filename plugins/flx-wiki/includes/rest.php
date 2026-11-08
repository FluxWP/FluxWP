<?php
/**
 * REST: /flx-wiki/v1/search (herkese açık), /ask (Wiki'ye sor, Flux AI), /preview (düzenleyici önizlemesi).
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'flx-wiki/v1',
			'/search',
			array(
				'methods'             => 'GET',
				'permission_callback' => 'flxwk_visible',
				'args'                => array(
					'q'       => array( 'type' => 'string', 'default' => '' ),
					'section' => array( 'type' => 'string' ),
					'limit'   => array( 'type' => 'integer', 'default' => 10 ),
				),
				'callback'            => function ( WP_REST_Request $r ) {
					$q = trim( (string) $r['q'] );
					if ( '' === $q ) {
						return rest_ensure_response( array( 'total' => 0, 'results' => array() ) );
					}
					$res = flxwk_search(
						mb_substr( $q, 0, 200 ),
						array(
							'limit'   => (int) $r['limit'],
							'section' => sanitize_title( (string) $r['section'] ),
						)
					);
					$out = new WP_REST_Response( $res );
					$out->header( 'Cache-Control', 'public, max-age=60' );
					return $out;
				},
			)
		);

		register_rest_route(
			'flx-wiki/v1',
			'/ask',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return flxwk_can_ask() ? true : new WP_Error( 'flxwk_ask_off', __( 'Soru sormak için giriş yapın.', 'flx-wiki' ), array( 'status' => 403 ) );
				},
				'args'                => array(
					'q'    => array( 'type' => 'string', 'required' => true, 'maxLength' => 1000 ),
					'page' => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0 ),
				),
				'callback'            => function ( WP_REST_Request $r ) {
					$q = trim( sanitize_textarea_field( (string) $r['q'] ) );
					if ( mb_strlen( $q ) < 3 ) {
						return new WP_Error( 'flxwk_short', __( 'Soru çok kısa.', 'flx-wiki' ), array( 'status' => 400 ) );
					}
					$lock = flxwk_ask_lock();
					if ( is_wp_error( $lock ) ) { return $lock; }
					try {
						$limit = flxwk_rate_hit();
						if ( is_wp_error( $limit ) ) { return $limit; }
						$res = flxwk_ask( $q, (int) $r['page'] );
						return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
					} finally {
						delete_option( $lock );
					}
				},
			)
		);

		register_rest_route(
			'flx-wiki/v1',
			'/preview',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array( 'md' => array( 'type' => 'string', 'required' => true ) ),
				'callback'            => function ( WP_REST_Request $r ) {
					$res = flxwk_render( (string) $r['md'] );
					return rest_ensure_response( array( 'html' => $res['html'], 'toc' => $res['toc'] ) );
				},
			)
		);
	}
);

/** Saatlik soru sınırı (kullanıcı ya da IP başına). */
function flxwk_rate_hit() {
	$max = max( 1, (int) flxwk_opt( 'ask_limit' ) );
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	$key = 'flxwk_rl_' . flxwk_ask_identity() . '_' . (int) floor( time() / HOUR_IN_SECONDS );
	$n   = (int) get_transient( $key );
	if ( $n >= $max ) {
		return new WP_Error( 'flxwk_rate', __( 'Bu saat için soru sınırına ulaştınız. Biraz sonra tekrar deneyin.', 'flx-wiki' ), array( 'status' => 429 ) );
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );
	return true;
}

/** Raw IPs are never persisted; ignore spoofable forwarded headers. */
function flxwk_ask_identity() {
	$who = get_current_user_id() ? 'u' . get_current_user_id() : 'ip' . (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	return substr( hash_hmac( 'sha256', $who, wp_salt() ), 0, 32 );
}

/** Atomic per-visitor in-flight gate also serializes quota updates. */
function flxwk_ask_lock() {
	global $wpdb;
	$key = 'flxwk_ask_lock_' . flxwk_ask_identity();
	$old = get_option( $key );
	if ( $old && (int) $old < time() ) {
		// Compare-and-delete prevents an expired reader removing a newly acquired lock.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, (string) $old ) );
		wp_cache_delete( $key, 'options' );
	}
	if ( ! add_option( $key, time() + 90, '', false ) ) {
		return new WP_Error( 'flxwk_busy', __( 'Önceki sorunuz yanıtlanıyor. Biraz sonra tekrar deneyin.', 'flx-wiki' ), array( 'status' => 429 ) );
	}
	return $key;
}
