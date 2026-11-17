<?php
/**
 * FluxWP app: Headless is a native screen (the same React screen as wp-admin, @fluxwp/modules
 * `flxhl-headless`, data over GraphQL).
 *
 * @package FLX_Headless
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flxapp_modules',
	static function ( $m ) {
		$m                   = is_array( $m ) ? $m : array();
		$m['flxhl-headless'] = array(
			'title'   => __( 'Headless', 'flx-headless' ),
			'cap'     => 'manage_options',
			'menu'    => 'flx-headless',
			'url'     => flxhl_admin_url(),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'globe',
			'version' => FLXHL_VERSION,
			'order'   => 92,
		);
		return $m;
	}
);

/*
 * Shared app manifest (app/manifest.json): the mobile app draws these screens natively over GraphQL.
 * The desktop panel keeps the React screen above, so the manifest is for mobile only.
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( ! function_exists( 'flxapp_manifest_file' ) ) {
			return;
		}
		add_filter(
			'flxapp_manifest',
			static function ( $m ) {
				$m        = is_array( $m ) ? $m : array();
				$m['headless'] = flxapp_manifest_file(
					FLXHL_DIR . 'app/manifest.json',
					'flx-headless',
					array(
						'version' => FLXHL_VERSION,
						'caps'    => array( 'view' => 'manage_options' ),
					)
				);
				return $m;
			}
		);
	},
	20
);
