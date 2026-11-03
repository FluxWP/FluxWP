<?php
/** Yönetim: tam ekran Stüdyo uygulaması (wp-admin içinde kendi arayüzü). */

defined( 'ABSPATH' ) || exit;

require_once FLXST_DIR . 'includes/js-strings.php';

add_action(
	'admin_menu',
	function () {
		$cap = current_user_can( FLXST_CAP_USE ) ? FLXST_CAP_USE : FLXST_CAP_MANAGE;
		add_menu_page( __( 'Stüdyo', 'flx-studio' ), __( 'Stüdyo', 'flx-studio' ), $cap, 'flx-studio', 'flxst_render_app', 'none', 11 );
	}
);

function flxst_is_app_screen() {
	return is_admin() && isset( $_GET['page'] ) && 'flx-studio' === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification
}

add_filter(
	'admin_body_class',
	function ( $c ) {
		return flxst_is_app_screen() ? $c . ' flxst-page' : $c;
	}
);

add_action(
	'admin_init',
	function () {
		if ( flxst_is_app_screen() ) {
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		}
	}
);

add_action(
	'admin_enqueue_scripts',
	function () {
		if ( ! flxst_is_app_screen() ) {
			return;
		}
		wp_enqueue_style( 'flxst-app', FLXST_URL . 'assets/css/app.css', array(), FLXST_VERSION );
		wp_enqueue_script( 'flxst-core', FLXST_URL . 'assets/js/core.js', array(), FLXST_VERSION, true );
		wp_enqueue_script( 'flxst-editor', FLXST_URL . 'assets/js/editor.js', array( 'flxst-core' ), FLXST_VERSION, true );
		wp_enqueue_script( 'flxst-dash', FLXST_URL . 'assets/js/dash.js', array( 'flxst-core' ), FLXST_VERSION, true );
		wp_enqueue_script( 'flxst-app', FLXST_URL . 'assets/js/app.js', array( 'flxst-editor', 'flxst-dash' ), FLXST_VERSION, true );
		wp_localize_script(
			'flxst-core',
			'FLXST_BOOT',
			array(
				'rest'     => trailingslashit( rest_url( FLXST_NS ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'locale'   => str_replace( '_', '-', get_user_locale() ),
				'adminUrl' => admin_url(),
				'mode'     => function_exists( 'flxa_user_mode' ) ? flxa_user_mode() : 'auto',
				'library'  => current_user_can( 'upload_files' ),
				'i18n'     => flxst_js_strings(),
			)
		);
	}
);

function flxst_render_app() {
	echo '<script>document.documentElement.classList.add("flxst-lock");</script>';
	echo '<div id="flxst-app" class="flxst"><noscript>' . esc_html__( 'Stüdyo için JavaScript gerekli.', 'flx-studio' ) . '</noscript></div>';
	// Tema div'in hemen ardından, ilk boyamadan önce (app.js yüklenene kadar açık tema görünmesin).
	printf( "<script>(function(d,w){var m=null;try{m=JSON.parse(localStorage.getItem('flxst.theme')||'null')}catch(e){}m=m||%s;d.getElementById('flxst-app').setAttribute('data-theme',m==='dark'||(m==='auto'&&!!w.matchMedia&&w.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light')})(document,window)</script>", wp_json_encode( function_exists( 'flxa_user_mode' ) ? flxa_user_mode() : 'auto' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
}

add_action(
	'admin_head',
	function () {
		echo '<style>#toplevel_page_flx-studio .wp-menu-image{background:url(' . esc_url( FLXST_URL . 'assets/icon.svg?v=' . FLXST_VERSION ) . ') center/20px 20px no-repeat}#toplevel_page_flx-studio .wp-menu-image::before{content:none}</style>' . "\n";
		if ( flxst_is_app_screen() ) {
			echo '<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">' . "\n";
			echo '<meta name="theme-color" content="#0f1115" media="(prefers-color-scheme: dark)">' . "\n";
			echo '<style>html.wp-toolbar{padding-top:0!important}</style>' . "\n";
		}
	}
);

add_filter(
	'admin_viewport_meta',
	function ( $v ) {
		return flxst_is_app_screen() ? 'width=device-width,initial-scale=1.0,viewport-fit=cover' : $v;
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( FLXST_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=flx-studio' ) ) . '">' . esc_html__( 'Stüdyo', 'flx-studio' ) . '</a>' );
		return $links;
	}
);
