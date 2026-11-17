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
