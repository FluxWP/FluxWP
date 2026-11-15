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
