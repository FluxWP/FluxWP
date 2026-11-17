<?php
/**
 * Flux App: Flux Ads is a native screen (the same React screen as wp-admin, @fluxwp/modules `flxads-ads`,
 * data over GraphQL). App versions without the screen open the admin page instead.
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flxapp_modules',
	static function ( $m ) {
		$m               = is_array( $m ) ? $m : array();
		$m['flxads-ads'] = array(
			'title'   => __( 'Ad conversions', 'flx-ads' ),
			'cap'     => 'flxads_view',
			'menu'    => 'flx-ads',
			'url'     => admin_url( 'admin.php?page=flx-ads' ),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'chart',
			'version' => FLXADS_VERSION,
			'order'   => 60,
		);
		return $m;
	}
);

/*
 * Shared app manifest (app/manifest.json): the mobile app draws these screens natively over GraphQL.
 * The desktop panel keeps its React screen, so the manifest is for mobile only.
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
				$m['ads'] = flxapp_manifest_file(
					FLXADS_DIR . 'app/manifest.json',
					'flx-ads',
					array(
						'version' => FLXADS_VERSION,
						'caps'    => array( 'view' => 'flxads_view' ),
					)
				);
				return $m;
			}
		);
	},
	20
);
