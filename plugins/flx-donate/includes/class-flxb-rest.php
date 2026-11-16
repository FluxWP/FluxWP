<?php
/**
 * Flux Donate: public read-only REST endpoints (same shapes as flxb_get_types() / flxb_get_type()).
 *
 *   GET /wp-json/flxb/v1/types?status=visible|open&category=kurban
 *   GET /wp-json/flxb/v1/types/<id or slug>
 *
 * Archived types and the form mapping are never returned.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Rest {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			'flxb/v1',
			'/types',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array(
					'status'   => array( 'type' => 'string', 'enum' => array( 'visible', 'open' ), 'default' => 'visible' ),
					'category' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_key' ),
				),
				'callback'            => function ( WP_REST_Request $r ) {
					return rest_ensure_response( FLXB_Type_Public_Service::list( (string) $r['status'], (string) $r['category'] ) );
				},
			)
		);
		register_rest_route(
			'flxb/v1',
			'/types/(?P<ref>[A-Za-z0-9_-]+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function ( WP_REST_Request $r ) {
					$t = FLXB_Type_Public_Service::one( (string) $r['ref'] );
					return $t instanceof WP_Error ? $t : rest_ensure_response( $t );
				},
			)
		);
	}
}
