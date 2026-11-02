<?php
/**
 * WordPress REST API: çevrilebilir türlere lang ve translations alanları, listelerde ?lang=en süzgeci.
 * Örnek: GET /wp-json/wp/v2/pages?lang=en · POST /wp-json/wp/v2/pages/12 {"lang":"en"}.
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	function () {
		foreach ( flxl_post_types() as $type ) {
			register_rest_field(
				$type,
				'lang',
				array(
					'get_callback'    => function ( $p ) {
						return flxl_post_language( $p['id'] );
					},
					'update_callback' => function ( $value, $post ) {
						if ( ! current_user_can( 'edit_post', $post->ID ) ) {
							return new WP_Error( 'flxl_cap', __( 'Bu işlem için yetkiniz yok.', 'flx-lang' ), array( 'status' => 403 ) );
						}
						if ( ! flxl_is_language( $value ) ) {
							return new WP_Error( 'flxl_lang', __( 'Dil etkin değil.', 'flx-lang' ), array( 'status' => 400 ) );
						}
						flxl_set_post_language( $post->ID, $value );
						return true;
					},
					'schema'          => array(
						'type'        => 'string',
						'enum'        => flxl_language_codes(),
						'description' => 'Language code of this content.',
						'context'     => array( 'view', 'edit' ),
					),
				)
			);
			register_rest_field(
				$type,
				'translations',
				array(
					'get_callback' => function ( $p ) {
						return (object) flxl_get_translations( $p['id'] );
					},
					'schema'       => array(
						'type'        => 'object',
						'description' => 'Language code => post ID of each translation (including itself).',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
				)
			);
			add_filter(
				'rest_' . $type . '_query',
				function ( $args, $req ) {
					$l = $req->get_param( 'lang' );
					if ( $l && ( 'all' === $l || flxl_is_language( $l ) ) ) {
						$args['flxl_lang'] = $l;
					}
					return $args;
				},
				10,
				2
			);
			add_filter(
				'rest_' . $type . '_collection_params',
				function ( $params ) {
					$params['lang'] = array(
						'description' => 'Limit to a language code, or "all".',
						'type'        => 'string',
					);
					return $params;
				}
			);
		}
	}
);
