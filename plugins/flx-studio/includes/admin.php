<?php
/** Yönetim: tam ekran Stüdyo uygulaması (ortak React ekranı; klasik ekran yoktur). */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		$cap = current_user_can( FLXST_CAP_USE ) ? FLXST_CAP_USE : FLXST_CAP_MANAGE;
		add_menu_page( __( 'Studio', 'flx-studio' ), __( 'Studio', 'flx-studio' ), $cap, 'flx-studio', 'flxst_render_app', 'none', 11 );
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

/*
 * The page opens the same React screen as the FluxWP app (assets/flux/, tools/fluxapp/embed-sync.sh
 * flx-studio). Without Flux GraphQL or the bundle the page says it cannot open.
 */
function flxst_flux_available() {
	return file_exists( FLXST_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && flx_gql_ready();
}

/** The chunked bundle loads as an ES module. */
function flxst_flux_module_tag( $tag, $handle ) {
	if ( 'flxst-flux' !== $handle ) {
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

function flxst_flux_assets() {
	$ver    = FLXST_VERSION . '-' . (int) filemtime( FLXST_DIR . 'assets/flux/flux-modules.js' );
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	$opts   = array(
		'module'     => 'studio',
		'rest'       => rest_url(),
		'nonce'      => wp_create_nonce( 'wp_rest' ),
		'locale'     => get_user_locale(),
		'siteName'   => get_bloginfo( 'name' ),
		'adminUrl'   => admin_url(),
		'appIcon'    => FLXST_URL . 'assets/icon.svg',
		'theme'      => 'light',
		'colors'     => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
	);
	wp_enqueue_style( 'flxst-flux', FLXST_URL . 'assets/flux/flux-modules.css', array(), $ver );
	wp_enqueue_script( 'flxst-flux', FLXST_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
	add_filter( 'script_loader_tag', 'flxst_flux_module_tag', 10, 2 );
	// Light / dark follows Flux Admin (html.flxa-dark), also when the user switches it on the page.
	wp_add_inline_script(
		'flxst-flux',
		'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxst-app";slot.opts=o;function sync(){var el=document.getElementById("flxst-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
		'before'
	);
}

add_action(
	'admin_enqueue_scripts',
	function () {
		if ( flxst_is_app_screen() && flxst_flux_available() ) {
			flxst_flux_assets();
		}
	}
);

function flxst_render_app() {
	if ( ! flxst_flux_available() ) {
		if ( function_exists( 'flx_admin_screen_unavailable' ) ) {
			flx_admin_screen_unavailable();
		}
		return;
	}
	echo '<script>document.documentElement.classList.add("flxst-lock");</script>';
	// Page-level rules live here, not in the bundle: the bundle's CSS is scoped under .flux-root.
	echo '<style>html.flxst-lock,html.flxst-lock body{overflow:hidden;height:100%}html.flxst-lock #wpadminbar,html.flxst-lock #adminmenumain,html.flxst-lock #wpfooter,html.flxst-lock .notice,html.flxst-lock .update-nag,html.flxst-lock #screen-meta-links{display:none!important}html.flxst-lock #wpcontent,html.flxst-lock #wpbody-content{margin:0!important;padding:0!important}#flxst-app{position:fixed;inset:0;z-index:100000;min-height:0}@media screen and (min-width:783px){html body.flxa-appmode #flxst-app{top:var(--wp-admin--admin-bar--height,32px);left:var(--flxa-app-w,0px);transition:left .22s ease}}@media screen and (max-width:782px){html:not(.flxa-kb) body.flxa-appmode.flxa-has-bottom #flxst-app{bottom:calc(67px + env(safe-area-inset-bottom))}}</style>';
	echo '<div id="flxst-app"><noscript>' . esc_html__( 'Studio requires JavaScript.', 'flx-studio' ) . '</noscript></div>';
	if ( function_exists( 'flx_admin_screen_failsafe' ) ) {
		flx_admin_screen_failsafe( 'flxst-app' );
	}
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
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=flx-studio' ) ) . '">' . esc_html__( 'Studio', 'flx-studio' ) . '</a>' );
		return $links;
	}
);
