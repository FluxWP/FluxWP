<?php
/** Diğer FluxWP paketleriyle bağlar: Flux App (masaüstü/mobil), Flux Admin, Flux AI. */

defined( 'ABSPATH' ) || exit;

/* Flux App: Stüdyo uygulamada yerel ekranla açılır. */
add_filter(
	'flxapp_modules',
	function ( $m ) {
		$m['studio'] = array(
			'title'   => __( 'Studio', 'flx-studio' ),
			'cap'     => FLXST_CAP_USE,
			'menu'    => 'flx-studio',
			'api'     => rest_url( FLXST_NS ),
			'icon'    => 'image',
			'version' => FLXST_VERSION,
		);
		return $m;
	}
);

/* Flux Admin: tam ekran uygulama ve uygulama geçişi. */
add_filter(
	'flxa_app_screens',
	function ( $map ) {
		$map['flx-studio'] = 'studio';
		return $map;
	}
);
add_filter(
	'flxa_apps',
	function ( $apps ) {
		$apps['studio'] = array(
			'label' => __( 'Studio', 'flx-studio' ),
			'url'   => admin_url( 'admin.php?page=flx-studio' ),
			'cap'   => FLXST_CAP_USE,
			'icon'  => 'image',
		);
		return $apps;
	}
);

/* Flux AI & MCP › Bağlı sistemler. */
add_filter(
	'flxai_systems',
	function ( $s ) {
		$s['flx-studio'] = array(
			'label'  => 'Flux Studio',
			'plugin' => plugin_basename( FLXST_FILE ),
			'page'   => 'admin.php?page=flx-studio',
		);
		return $s;
	}
);
