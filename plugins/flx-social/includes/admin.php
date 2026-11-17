<?php
/** Yönetim: tam ekran Sosyal Medya uygulaması (ortak React ekranı; klasik ekran yoktur). */

defined( 'ABSPATH' ) || exit;

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
 * The screen is the same React screen as the FluxWP app (assets/flux/, tools/fluxapp/embed-sync.sh
 * flx-social). Without Flux GraphQL or the bundle the page says it cannot open.
 */
function flxsm_flux_available() {
	return file_exists( FLXSM_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
}

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
		if ( flxsm_is_app_screen() && flxsm_flux_available() ) {
			flxsm_flux_assets();
		}
	}
);

function flxsm_render_app() {
	if ( ! flxsm_flux_available() ) {
		if ( function_exists( 'flx_admin_screen_unavailable' ) ) {
			flx_admin_screen_unavailable();
		}
		return;
	}
	echo '<script>document.documentElement.classList.add("flxsm-lock");</script>';
	echo '<style>html.flxsm-lock,html.flxsm-lock body{overflow:hidden;height:100%}html.flxsm-lock #wpadminbar,html.flxsm-lock #adminmenumain,html.flxsm-lock #wpfooter,html.flxsm-lock .notice,html.flxsm-lock .update-nag,html.flxsm-lock #screen-meta-links{display:none!important}html.flxsm-lock #wpcontent,html.flxsm-lock #wpbody-content{margin:0!important;padding:0!important}#flxsm-app.flux-root{position:fixed;inset:0;z-index:99990;height:auto}</style>';
	if ( function_exists( 'flx_admin_screen_render' ) && flx_admin_screen_render() ) {
		return;
	}
	echo '<div id="flxsm-app"><noscript>' . esc_html__( 'Social Media requires JavaScript.', 'flx-social' ) . '</noscript></div>';
	if ( function_exists( 'flx_admin_screen_failsafe' ) ) {
		flx_admin_screen_failsafe( 'flxsm-app' );
	}
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
