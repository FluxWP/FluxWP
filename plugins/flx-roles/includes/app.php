<?php
/**
 * Flux App: Roles and access is a native screen (the same React screen as wp-admin, @fluxwp/modules
 * `flxr-roles`, data over GraphQL). App versions without the screen open the admin page instead.
 *
 * @package flx-roles
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flxapp_modules',
	static function ( $m ) {
		$m               = is_array( $m ) ? $m : array();
		$m['flxr-roles'] = array(
			'title'   => __( 'Roles and access', 'flx-roles' ),
			'cap'     => 'manage_options',
			'menu'    => 'flx-roles',
			'url'     => admin_url( 'users.php?page=flx-roles' ),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'users',
			'version' => FLXR_VERSION,
			'order'   => 80,
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
				$m['roles'] = flxapp_manifest_file(
					FLXR_DIR . 'app/manifest.json',
					'flx-roles',
					array(
						'version' => FLXR_VERSION,
						'caps'    => array( 'view' => 'manage_options' ),
					)
				);
				return $m;
			}
		);
	},
	20
);
