<?php
/**
 * Ayarlar › Diller.
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_options_page( __( 'Languages', 'flx-lang' ), __( 'Languages', 'flx-lang' ), 'manage_options', 'flx-lang', 'flxl_settings_page' );
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( FLXL_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=flx-lang' ) ) . '">' . esc_html__( 'Settings', 'flx-lang' ) . '</a>' );
		return $links;
	}
);

/**
 * Settings page address.
 *
 * @param array<string,string> $args Extra query arguments.
 */
function flxl_admin_url( array $args = array() ) {
	return add_query_arg( $args, admin_url( 'options-general.php?page=flx-lang' ) );
}

/*
 * The page opens the same React screen as the FluxWP app (assets/flux/, tools/fluxapp/embed-sync.sh
 * flx-lang). There is no classic page: without Flux GraphQL or the bundle the page says it cannot open.
 */
function flxl_flux_available() {
	return file_exists( FLXL_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
}

/** The chunked bundle loads as an ES module. */
function flxl_flux_module_tag( $tag, $handle ) {
	if ( 'flxl-flux' !== $handle ) {
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
function flxl_flux_options() {
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	return array(
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
		if ( ! function_exists( 'flx_admin_screen' ) || ! flxl_flux_available() ) {
			return;
		}
		flx_admin_screen(
			'flx-lang',
			array(
				'module'       => 'flxl-lang',
				'assets_dir'   => FLXL_DIR . 'assets/flux/',
				'assets_url'   => FLXL_URL . 'assets/flux/',
				'version'      => FLXL_VERSION,
				'options'      => 'flxl_flux_options',
				'container_id' => 'flxl-app',
				'preload'      => array( array( 'FlxLanguageSettingsScreen' ) ),
				'wrap_class'   => 'flxl-settings',
			)
		);
	},
	20
);

function flxl_flux_assets() {
	if ( function_exists( 'flx_admin_screen_current' ) && '' !== flx_admin_screen_current() ) {
		return; // The Flux GraphQL screen shell loads the bundle.
	}
	$ver    = FLXL_VERSION . '-' . (int) filemtime( FLXL_DIR . 'assets/flux/flux-modules.js' );
	$opts = array_merge(
		array(
			'module'     => 'flxl-lang',
			'rest'       => rest_url(),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'locale'     => get_user_locale(),
			'siteName'   => get_bloginfo( 'name' ),
			'adminUrl'   => admin_url(),
			'theme'      => 'light',
		),
		flxl_flux_options()
	);
	wp_enqueue_style( 'flxl-flux', FLXL_URL . 'assets/flux/flux-modules.css', array(), $ver );
	wp_enqueue_script( 'flxl-flux', FLXL_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
	add_filter( 'script_loader_tag', 'flxl_flux_module_tag', 10, 2 );
	// Light / dark follows Flux Admin (html.flxa-dark), also when the user switches it on the page.
	wp_add_inline_script(
		'flxl-flux',
		'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxl-app";slot.opts=o;function sync(){var el=document.getElementById("flxl-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
		'before'
	);
}

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'settings_page_flx-lang' === $hook && flxl_flux_available() ) {
			flxl_flux_assets();
		}
	}
);

function flxl_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( function_exists( 'flx_admin_screen_render' ) && flx_admin_screen_render() ) {
		return;
	}
	if ( ! flxl_flux_available() ) {
		if ( function_exists( 'flx_admin_screen_unavailable' ) ) {
			flx_admin_screen_unavailable();
		}
		return;
	}
	echo '<style>#flxl-app.flux-root{height:auto;background:transparent;font-family:inherit}</style>';
	echo '<div class="wrap flxl-settings"><div id="flxl-app"><noscript>' . esc_html__( 'JavaScript is required for this screen.', 'flx-lang' ) . '</noscript></div>';
	if ( function_exists( 'flx_admin_screen_failsafe' ) ) {
		flx_admin_screen_failsafe( 'flxl-app' );
	}
	echo '</div>';
}
