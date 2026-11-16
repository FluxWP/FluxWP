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
