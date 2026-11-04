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
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'       => array( 'type' => 'string', 'required' => true ),
					'section' => array( 'type' => 'string' ),
					'limit'   => array( 'type' => 'integer', 'default' => 10 ),
				),
				'callback'            => function ( WP_REST_Request $r ) {
					$res = flxwk_search(
						mb_substr( (string) $r['q'], 0, 200 ),
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
					'q'    => array( 'type' => 'string', 'required' => true ),
					'page' => array( 'type' => 'integer', 'default' => 0 ),
				),
				'callback'            => function ( WP_REST_Request $r ) {
					$q = trim( sanitize_textarea_field( (string) $r['q'] ) );
					if ( mb_strlen( $q ) < 3 ) {
						return new WP_Error( 'flxwk_short', __( 'Soru çok kısa.', 'flx-wiki' ), array( 'status' => 400 ) );
					}
					$limit = flxwk_rate_hit();
					if ( is_wp_error( $limit ) ) {
						return $limit;
					}
					$res = flxwk_ask( mb_substr( $q, 0, 1000 ), (int) $r['page'] );
					return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
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
	$who = get_current_user_id() ? 'u' . get_current_user_id() : 'ip' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) . wp_salt() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$key = 'flxwk_rl_' . substr( md5( $who ), 0, 20 );
	$n   = (int) get_transient( $key );
	if ( $n >= $max ) {
		return new WP_Error( 'flxwk_rate', __( 'Bu saat için soru sınırına ulaştınız. Biraz sonra tekrar deneyin.', 'flx-wiki' ), array( 'status' => 429 ) );
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );
	return true;
}
