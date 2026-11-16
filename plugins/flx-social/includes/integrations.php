<?php
/** Diğer FluxWP paketleriyle bağlar: Flux App (masaüstü/mobil), Flux Admin, Flux AI. */

defined( 'ABSPATH' ) || exit;

/* Flux App: native screen (the same React screen as wp-admin, @fluxwp/modules `flxsm-social`, data over GraphQL). App versions without the screen open the admin page. */
add_filter(
	'flxapp_modules',
	function ( $m ) {
		$m                = is_array( $m ) ? $m : array();
		$m['flxsm-social'] = array(
			'title'   => __( 'Social Media', 'flx-social' ),
			'cap'     => FLXSM_CAP_POST,
			'menu'    => 'flx-social',
			'url'     => admin_url( 'admin.php?page=flx-social' ),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'share',
			'version' => FLXSM_VERSION,
		);
		return $m;
	}
);

/* Flux Admin: tam ekran uygulama ve uygulama geçişi. */
add_filter(
	'flxa_app_screens',
	function ( $map ) {
		$map['flx-social'] = 'social';
		return $map;
	}
);
add_filter(
	'flxa_apps',
	function ( $apps ) {
		$apps['social'] = array(
			'label' => __( 'Social Media', 'flx-social' ),
			'url'   => admin_url( 'admin.php?page=flx-social' ),
			'cap'   => FLXSM_CAP_POST,
			'icon'  => 'share',
		);
		return $apps;
	}
);

/* Flux AI & MCP › Bağlı sistemler. */
add_filter(
	'flxai_systems',
	function ( $s ) {
		$s['flx-social'] = array(
			'label'  => 'Flux Social',
			'plugin' => plugin_basename( FLXSM_FILE ),
			'page'   => 'admin.php?page=flx-social',
		);
		return $s;
	}
);
