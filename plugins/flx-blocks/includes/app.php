<?php
/**
 * FluxWP app: mobile screens from the shared manifest.
 *
 * @package flx-blocks
 */

defined( 'ABSPATH' ) || exit;

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
				$m           = is_array( $m ) ? $m : array();
				$m['blocks'] = flxapp_manifest_file(
					FLXBK_PATH . 'app/manifest.json',
					'flx-blocks',
					array(
						'version' => FLXBK_VERSION,
						'caps'    => array( 'view' => FLXBK_Dashboard_Service::capability() ),
					)
				);
				return $m;
			}
		);
	},
	20
);
