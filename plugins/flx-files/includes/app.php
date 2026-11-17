<?php
/**
 * FluxWP app: mobile screens from the shared manifest.
 *
 * @package flx-files
 */

defined( 'ABSPATH' ) || exit;

/*
 * Shared app manifest (app/manifest.json): the mobile app draws these screens natively over GraphQL.
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
				$m          = is_array( $m ) ? $m : array();
				$m['files'] = flxapp_manifest_file(
					FLXFM_DIR . 'app/manifest.json',
					'flx-files',
					array(
						'version' => FLXFM_VERSION,
						'caps'    => array( 'view' => 'flxfm_manage' ),
					)
				);
				return $m;
			}
		);
	},
	20
);
