<?php
/**
 * Flux App: the Flux AI screens as native modules (the same React screens as wp-admin, @fluxwp/modules
 * `flxai-admin`, data over GraphQL).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/* Flux App: the same screens as native modules (App versions without them open the admin page). */
add_filter(
	'flxapp_modules',
	static function ( $m ) {
		$m                = is_array( $m ) ? $m : array();
		$m['flxai-admin'] = array(
			'title'   => 'Flux AI & MCP',
			'cap'     => 'manage_options',
			'menu'    => 'flx-ai',
			'url'     => admin_url( 'admin.php?page=flx-ai' ),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'bolt',
			'version' => FLXAI_VERSION,
			'order'   => 70,
		);
		return $m;
	}
);
