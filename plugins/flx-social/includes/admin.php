<?php
/** Yönetim: tam ekran Sosyal Medya uygulaması (wp-admin içinde kendi arayüzü). */

defined( 'ABSPATH' ) || exit;

require_once FLXSM_DIR . 'includes/js-strings.php';

add_action(
	'admin_menu',
	function () {
		$cap   = current_user_can( FLXSM_CAP_POST ) ? FLXSM_CAP_POST : FLXSM_CAP_MANAGE;
		$title = __( 'Sosyal Medya', 'flx-social' );
		$n     = flxsm_can_publish() ? (int) ( flxsm_counts()['pending'] ?? 0 ) : 0;
		$menu  = $title . ( $n ? ' <span class="awaiting-mod count-' . $n . '"><span class="pending-count">' . number_format_i18n( $n ) . '</span></span>' : '' );
		add_menu_page( $title, $menu, $cap, 'flx-social', 'flxsm_render_app', 'none', 12 );
	}
);

function flxsm_is_app_screen() {
	return is_admin() && isset( $_GET['page'] ) && 'flx-social' === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification
}

add_filter(
	'admin_body_class',
	function ( $c ) {
		return flxsm_is_app_screen() ? $c . ' flxsm-page' : $c;
	}
);

add_action(
	'admin_init',
	function () {
		if ( flxsm_is_app_screen() ) {
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		}
	}
);

add_action(
	'admin_enqueue_scripts',
	function () {
		if ( ! flxsm_is_app_screen() ) {
			return;
		}
		wp_enqueue_style( 'flxsm-app', FLXSM_URL . 'assets/css/app.css', array(), FLXSM_VERSION );
		wp_enqueue_script( 'flxsm-core', FLXSM_URL . 'assets/js/core.js', array(), FLXSM_VERSION, true );
		wp_enqueue_script( 'flxsm-dash', FLXSM_URL . 'assets/js/dash.js', array( 'flxsm-core' ), FLXSM_VERSION, true );
		wp_enqueue_script( 'flxsm-composer', FLXSM_URL . 'assets/js/composer.js', array( 'flxsm-core' ), FLXSM_VERSION, true );
		wp_enqueue_script( 'flxsm-settings', FLXSM_URL . 'assets/js/settings.js', array( 'flxsm-core' ), FLXSM_VERSION, true );
		wp_enqueue_script( 'flxsm-app', FLXSM_URL . 'assets/js/app.js', array( 'flxsm-dash', 'flxsm-composer', 'flxsm-settings' ), FLXSM_VERSION, true );
		wp_localize_script(
			'flxsm-core',
			'FLXSM_BOOT',
			array(
				'rest'     => trailingslashit( rest_url( FLXSM_NS ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'locale'   => str_replace( '_', '-', get_user_locale() ),
				'adminUrl' => admin_url(),
				'mode'     => function_exists( 'flxa_user_mode' ) ? flxa_user_mode() : 'auto',
				'icons'    => FLXSM_URL . 'assets/net/',
				'logo'     => FLXSM_URL . 'assets/icon.svg',
				'i18n'     => flxsm_js_strings(),
			)
		);
	}
);

function flxsm_render_app() {
	echo '<script>document.documentElement.classList.add("flxsm-lock");</script>';
	echo '<div id="flxsm-app" class="flxsm"><noscript>' . esc_html__( 'Sosyal Medya için JavaScript gerekli.', 'flx-social' ) . '</noscript></div>';
	// Tema div'in hemen ardından, ilk boyamadan önce (app.js yüklenene kadar açık tema görünmesin).
	printf( "<script>(function(d,w){var m=null;try{m=JSON.parse(localStorage.getItem('flxsm.theme')||'null')}catch(e){}m=m||%s;d.getElementById('flxsm-app').setAttribute('data-theme',m==='dark'||(m==='auto'&&!!w.matchMedia&&w.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light')})(document,window)</script>", wp_json_encode( function_exists( 'flxa_user_mode' ) ? flxa_user_mode() : 'auto' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
}

add_action(
	'admin_head',
	function () {
		echo '<style>#toplevel_page_flx-social .wp-menu-image{background:url(' . esc_url( FLXSM_URL . 'assets/icon.svg?v=' . FLXSM_VERSION ) . ') center/20px 20px no-repeat}#toplevel_page_flx-social .wp-menu-image::before{content:none}</style>' . "\n";
		if ( flxsm_is_app_screen() ) {
			echo '<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">' . "\n";
			echo '<meta name="theme-color" content="#0f1115" media="(prefers-color-scheme: dark)">' . "\n";
			echo '<style>html.wp-toolbar{padding-top:0!important}</style>' . "\n";
		}
	}
);

add_filter(
	'admin_viewport_meta',
	function ( $v ) {
		return flxsm_is_app_screen() ? 'width=device-width,initial-scale=1.0,viewport-fit=cover' : $v;
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( FLXSM_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=flx-social' ) ) . '">' . esc_html__( 'Sosyal Medya', 'flx-social' ) . '</a>' );
		return $links;
	}
);
