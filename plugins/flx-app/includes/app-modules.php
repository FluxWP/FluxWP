<?php
/**
 * Flux App: "Application" settings and "App access" are native screens (the same React screens as wp-admin,
 * @fluxwp/modules `flxapp-settings` and `flxapp-access`, data over GraphQL). App versions without the screens open the
 * admin pages instead.
 *
 * @package flx-app
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flxapp_modules',
	static function ( $m ) {
		$m                   = is_array( $m ) ? $m : array();
		$m['flxapp-settings'] = array(
			'title'   => __( 'Application', 'flx-app' ),
			'cap'     => 'manage_options',
			'menu'    => 'flxapp-settings',
			'url'     => admin_url( 'admin.php?page=flxapp-settings' ),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'smartphone',
			'version' => FLXAPP_VERSION,
			'order'   => 90,
		);
		$m['flxapp-access']   = array(
			'title'   => __( 'App access', 'flx-app' ),
			'cap'     => 'read',
			'menu'    => 'flx-app',
			'url'     => admin_url( 'users.php?page=flx-app' ),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'key',
			'version' => FLXAPP_VERSION,
			'order'   => 91,
		);
		return $m;
	}
);
