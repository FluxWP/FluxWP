<?php
/**
 * "Application" (administrators) and "App access" (everyone) open the same React screens as the FluxWP app
 * (assets/flux/, tools/fluxapp/embed-sync.sh flx-app). There are no classic pages: without Flux GraphQL or the
 * bundle the page says it cannot open.
 *
 * @package flx-app
 */

defined( 'ABSPATH' ) || exit;

/** Module id of the screen a wp-admin page shows. */
function flxapp_flux_module( $page ) {
	return 'flxapp-settings' === $page ? 'flxapp-settings' : ( 'flx-app' === $page ? 'flxapp-access' : '' );
}

function flxapp_flux_available() {
	return file_exists( FLXAPP_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
}

/** The chunked bundle loads as an ES module. */
function flxapp_flux_module_tag( $tag, $handle ) {
	if ( 'flxapp-flux' !== $handle ) {
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

/**
 * Mount options of the screen besides what the Flux GraphQL shell adds (rest, nonce, locale…).
 *
 * @param string $page Page slug.
 */
function flxapp_flux_options( $page ) {
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	return array(
		'colors' => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
	);
}

// Flux GraphQL screen shell: first data printed by PHP, in-place navigation from the menu.
add_action(
	'admin_menu',
	function () {
		if ( ! function_exists( 'flx_admin_screen' ) || ! flxapp_flux_available() ) {
			return;
		}
		foreach ( array( 'flxapp-settings', 'flx-app' ) as $page ) {
			flx_admin_screen(
				$page,
				array(
					'module'       => flxapp_flux_module( $page ),
					'assets_dir'   => FLXAPP_DIR . 'assets/flux/',
					'assets_url'   => FLXAPP_URL . 'assets/flux/',
					'version'      => FLXAPP_VERSION,
					'options'      => 'flxapp_flux_options',
					'start_param'  => 'tab',
					'container_id' => 'flxapp-app',
					'preload'      => 'flx-app' === $page ? array( array( 'FlxAppAccess', array( 'all' => false ) ) ) : array( array( 'FlxAppAdminSettings' ) ),
					'wrap_class'   => 'flxapp-wrap',
				)
			);
		}
	},
	20
);

/** Styles and script of the shared bundle, mounted on #flxapp-app. */
function flxapp_flux_assets( $page ) {
	if ( function_exists( 'flx_admin_screen_current' ) && '' !== flx_admin_screen_current() ) {
		return; // The Flux GraphQL screen shell loads the bundle.
	}
	$ver  = FLXAPP_VERSION . '-' . (int) filemtime( FLXAPP_DIR . 'assets/flux/flux-modules.js' );
	$opts = array_merge(
		array(
			'module'    => flxapp_flux_module( $page ),
			'rest'      => rest_url(),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'locale'    => get_user_locale(),
			'siteName'  => get_bloginfo( 'name' ),
			'adminUrl'  => admin_url(),
			'theme'     => 'light',
			'startPage' => isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
		),
		flxapp_flux_options( $page )
	);
	wp_enqueue_style( 'flxapp-flux', FLXAPP_URL . 'assets/flux/flux-modules.css', array(), $ver );
	wp_enqueue_script( 'flxapp-flux', FLXAPP_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
	add_filter( 'script_loader_tag', 'flxapp_flux_module_tag', 10, 2 );
	// Light / dark follows Flux Admin (html.flxa-dark), also when the user switches it on the page.
	wp_add_inline_script(
		'flxapp-flux',
		'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxapp-app";slot.opts=o;function sync(){var el=document.getElementById("flxapp-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
		'before'
	);
}

/** The mount point (the screen's page body), or the "could not be loaded" box when the screen cannot open. */
function flxapp_flux_root() {
	if ( function_exists( 'flx_admin_screen_render' ) && flx_admin_screen_render() ) {
		return;
	}
	if ( ! flxapp_flux_available() ) {
		if ( function_exists( 'flx_admin_screen_unavailable' ) ) {
			flx_admin_screen_unavailable();
		}
		return;
	}
	echo '<style>#flxapp-app.flux-root{height:auto;background:transparent;font-family:inherit}</style>';
	echo '<div class="wrap flxapp-wrap"><div id="flxapp-app"><noscript>' . esc_html__( 'JavaScript is required for this screen.', 'flx-app' ) . '</noscript></div>';
	if ( function_exists( 'flx_admin_screen_failsafe' ) ) {
		flx_admin_screen_failsafe( 'flxapp-app' );
	}
	echo '</div>';
}
