<?php
/**
 * Flux App: POS ekranı uygulamada da aynı React ekranı (@fluxwp/modules `flxpos-pos`, veri GraphQL).
 * Gizli anahtarlar yalnız yazılır, geri okunmaz.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flxapp_modules',
	static function ( $m ) {
		$m               = is_array( $m ) ? $m : array();
		$m['flxpos-pos'] = array(
			'title'   => __( 'Sanal POS', 'flx-pos' ),
			'cap'     => 'manage_woocommerce',
			'menu'    => 'flx-pos',
			'url'     => admin_url( 'admin.php?page=flx-pos' ),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'card',
			'version' => FLXPOS_VERSION,
			'order'   => 62,
		);
		return $m;
	}
);
