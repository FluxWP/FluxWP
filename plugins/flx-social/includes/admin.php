<?php
/** Yönetim: tam ekran Sosyal Medya uygulaması (wp-admin içinde kendi arayüzü). */

defined( 'ABSPATH' ) || exit;

require_once FLXSM_DIR . 'includes/js-strings.php';

add_action(
	'admin_menu',
	function () {
		$cap   = current_user_can( FLXSM_CAP_POST ) ? FLXSM_CAP_POST : FLXSM_CAP_MANAGE;
		$title = __( 'Social Media', 'flx-social' );
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

/*
 * New view (0.3.0, GraphQL): the screen opens the same React screen as the FluxWP app (assets/flux/,
 * tools/fluxapp/embed-sync.sh flx-social). It is the default and has every feature of the classic screen;
 * a user can switch back to the classic screen from the screen header for now. Needs Flux GraphQL.
 */
function flxsm_flux_available() {
	return file_exists( FLXSM_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
}

function flxsm_flux_ui() {
	return flxsm_flux_available() && 'classic' !== get_user_meta( get_current_user_id(), 'flxsm_ui', true );
}

/** Link that switches the current user between the views. */
function flxsm_ui_switch_url( $ui ) {
	return wp_nonce_url( admin_url( 'admin.php?page=flx-social&flxsm_ui=' . ( 'flux' === $ui ? 'flux' : 'classic' ) ), 'flxsm_ui' );
}

add_action(
	'admin_init',
	function () {
		if ( ! isset( $_GET['flxsm_ui'], $_GET['page'] ) || 'flx-social' !== $_GET['page'] || ! flxsm_can_post() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		check_admin_referer( 'flxsm_ui' );
		if ( 'classic' === $_GET['flxsm_ui'] ) {
			update_user_meta( get_current_user_id(), 'flxsm_ui', 'classic' );
		} else {
			delete_user_meta( get_current_user_id(), 'flxsm_ui' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=flx-social' ) );
		exit;
	}
);

/** The chunked bundle loads as an ES module. */
function flxsm_flux_module_tag( $tag, $handle ) {
	if ( 'flxsm-flux' !== $handle ) {
		return $tag;
	}
	return (string) preg_replace_callback(
		'/<script\b[^>]*\ssrc=[^>]*>/',
		static function ( $m ) {
			$open = (string) preg_replace( '/\stype=("|\')[^"\']*\1/', '', $m[0] );
			return (string) preg_replace( '/^<script\b/', '<script type="module"', $open );
		},
		$tag,
		1
	);
}

/** Mount options of the screen besides what the Flux GraphQL shell adds (rest, nonce, locale…). */
function flxsm_flux_options() {
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	return array(
		'appIcon'    => FLXSM_URL . 'assets/icon.svg',
		'colors'     => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
		'classicUrl' => flxsm_ui_switch_url( 'classic' ),
	);
}

// Flux GraphQL screen shell: first data printed by PHP, in-place navigation from the menu.
add_action(
	'admin_menu',
	function () {
		if ( ! function_exists( 'flx_admin_screen' ) || ! flxsm_flux_available() ) {
			return;
		}
		flx_admin_screen(
			'flx-social',
			array(
				'module'       => 'flxsm-social',
				'assets_dir'   => FLXSM_DIR . 'assets/flux/',
				'assets_url'   => FLXSM_URL . 'assets/flux/',
				'version'      => FLXSM_VERSION,
				'enabled'      => 'flxsm_flux_ui',
				'options'      => 'flxsm_flux_options',
				'container_id' => 'flxsm-app',
				'preload'      => array( array( 'FlxSocialBoot' ), array( 'FlxSocialCounts' ), array( 'FlxSocialStats', array( 'days' => 30 ) ) ),
				'layout'       => 'full',
			)
		);
	},
	20
);

function flxsm_flux_assets() {
	if ( function_exists( 'flx_admin_screen_current' ) && '' !== flx_admin_screen_current() ) {
		return; // The Flux GraphQL screen shell loads the bundle.
	}
	$ver    = FLXSM_VERSION . '-' . (int) filemtime( FLXSM_DIR . 'assets/flux/flux-modules.js' );
	$opts = array_merge(
		array(
			'module'     => 'flxsm-social',
			'rest'       => rest_url(),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'locale'     => get_user_locale(),
			'siteName'   => get_bloginfo( 'name' ),
			'adminUrl'   => admin_url(),
			'theme'      => 'light',
		),
		flxsm_flux_options()
	);
	wp_enqueue_style( 'flxsm-flux', FLXSM_URL . 'assets/flux/flux-modules.css', array(), $ver );
	wp_enqueue_script( 'flxsm-flux', FLXSM_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
	add_filter( 'script_loader_tag', 'flxsm_flux_module_tag', 10, 2 );
	// Light / dark follows Flux Admin (html.flxa-dark), also when the user switches it on the page.
	wp_add_inline_script(
		'flxsm-flux',
		'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxsm-app";slot.opts=o;function sync(){var el=document.getElementById("flxsm-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
		'before'
	);
}

add_action(
	'admin_enqueue_scripts',
	function () {
		if ( ! flxsm_is_app_screen() ) {
			return;
		}
		if ( flxsm_flux_ui() ) {
			flxsm_flux_assets();
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
	if ( flxsm_flux_ui() ) {
		echo '<script>document.documentElement.classList.add("flxsm-lock");</script>';
		echo '<style>html.flxsm-lock,html.flxsm-lock body{overflow:hidden;height:100%}html.flxsm-lock #wpadminbar,html.flxsm-lock #adminmenumain,html.flxsm-lock #wpfooter,html.flxsm-lock .notice,html.flxsm-lock .update-nag,html.flxsm-lock #screen-meta-links{display:none!important}html.flxsm-lock #wpcontent,html.flxsm-lock #wpbody-content{margin:0!important;padding:0!important}#flxsm-app.flux-root{position:fixed;inset:0;z-index:99990;height:auto}</style>';
		if ( function_exists( 'flx_admin_screen_render' ) && flx_admin_screen_render() ) {
			return;
		}
		echo '<div id="flxsm-app"><noscript>' . esc_html__( 'Social Media requires JavaScript.', 'flx-social' ) . '</noscript></div>';
		return;
	}
	if ( flxsm_flux_available() ) {
		echo '<a class="flxsm-switch" href="' . esc_url( flxsm_ui_switch_url( 'flux' ) ) . '" style="position:fixed;right:16px;bottom:16px;z-index:99999;background:#1f2937;color:#fff;padding:8px 14px;border-radius:999px;font:500 13px/1 system-ui;text-decoration:none;box-shadow:0 2px 8px rgba(0,0,0,.25)">' . esc_html__( 'New view', 'flx-social' ) . '</a>';
	}
	echo '<script>document.documentElement.classList.add("flxsm-lock");</script>';
	echo '<div id="flxsm-app" class="flxsm"><noscript>' . esc_html__( 'Social Media requires JavaScript.', 'flx-social' ) . '</noscript></div>';
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
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=flx-social' ) ) . '">' . esc_html__( 'Social Media', 'flx-social' ) . '</a>' );
		return $links;
	}
);
