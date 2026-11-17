<?php
/**
 * Block Editor dashboard: the page opens the same React screen as the FluxWP app (@fluxwp/modules
 * `flxbk-dashboard`, assets/flux/, tools/fluxapp/embed-sync.sh flx-blocks), with the block list, editor
 * widths and the design library. There is no classic dashboard: without Flux GraphQL or the bundle the
 * page says it cannot open.
 *
 * @package flx-blocks
 */

defined( 'ABSPATH' ) || exit;

/** The screen can run: bundle shipped and Flux GraphQL ready. */
function flxbk_flux_available(): bool {
	return file_exists( FLXBK_PATH . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
}

/**
 * The chunked bundle loads as an ES module.
 *
 * @param string $tag    Script tag.
 * @param string $handle Handle.
 */
function flxbk_flux_module_tag( $tag, $handle ) {
	if ( 'flxbk-flux' !== $handle ) {
		return $tag;
	}
	return (string) preg_replace_callback(
		'/<script\b[^>]*\ssrc=[^>]*>/',
		static function ( $m ) {
			$open = (string) preg_replace( '/\stype=("|\')[^"\']*\1/', '', $m[0] );
			return (string) preg_replace( '/^<script\b/', '<script type="module"', $open );
		},
		(string) $tag,
		1
	);
}

/** Loads the React screen. */
/** Mount options of the screen besides what the Flux GraphQL shell adds (rest, nonce, locale…). */
function flxbk_flux_options() {
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#4338CA';
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
		if ( ! function_exists( 'flx_admin_screen' ) || ! flxbk_flux_available() ) {
			return;
		}
		flx_admin_screen(
			'flx-blocks',
			array(
				'module'       => 'flxbk-dashboard',
				'assets_dir'   => FLXBK_PATH . 'assets/flux/',
				'assets_url'   => FLXBK_URL . 'assets/flux/',
				'version'      => FLXBK_VERSION,
				'options'      => 'flxbk_flux_options',
				'container_id' => 'flxbk-app',
				'preload'      => array( array( 'FlxBlocksDashboard' ) ),
				'wrap_class'   => 'flxbk-wrap',
			)
		);
	},
	20
);

function flxbk_flux_assets(): void {
	if ( function_exists( 'flx_admin_screen_current' ) && '' !== flx_admin_screen_current() ) {
		return; // The Flux GraphQL screen shell loads the bundle.
	}
	$ver    = FLXBK_VERSION . '-' . (int) filemtime( FLXBK_PATH . 'assets/flux/flux-modules.js' );
	$opts = array_merge(
		array(
			'module'     => 'flxbk-dashboard',
			'rest'       => rest_url(),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'locale'     => get_user_locale(),
			'siteName'   => get_bloginfo( 'name' ),
			'adminUrl'   => admin_url(),
			'theme'      => 'light',
		),
		flxbk_flux_options()
	);
	wp_enqueue_style( 'flxbk-flux', FLXBK_URL . 'assets/flux/flux-modules.css', array(), $ver );
	wp_enqueue_script( 'flxbk-flux', FLXBK_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
	add_filter( 'script_loader_tag', 'flxbk_flux_module_tag', 10, 2 );
	// Light / dark follows Flux Admin (html.flxa-dark), also when the user switches it on the page.
	wp_add_inline_script(
		'flxbk-flux',
		'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxbk-app";slot.opts=o;function sync(){var el=document.getElementById("flxbk-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
		'before'
	);
}

/** Mount point of the React screen, or the "could not be loaded" box when the screen cannot open. */
function flxbk_flux_render(): void {
	echo '<style>#flxbk-app.flux-root{height:auto;background:transparent;font-family:inherit}</style>';
	if ( function_exists( 'flx_admin_screen_render' ) && flx_admin_screen_render() ) {
		return;
	}
	if ( ! flxbk_flux_available() ) {
		if ( function_exists( 'flx_admin_screen_unavailable' ) ) {
			flx_admin_screen_unavailable();
		}
		return;
	}
	echo '<div class="wrap flxbk-wrap"><div id="flxbk-app"><noscript>' . esc_html__( 'JavaScript is required for this screen.', 'flx-blocks' ) . '</noscript></div>';
	if ( function_exists( 'flx_admin_screen_failsafe' ) ) {
		flx_admin_screen_failsafe( 'flxbk-app' );
	}
	echo '</div>';
}

/*
 * FluxWP app: the dashboard is a native screen (same React screen, data over GraphQL). App versions
 * without the screen open the admin page instead.
 */
add_filter(
	'flxapp_modules',
	static function ( $m ) {
		$m                    = is_array( $m ) ? $m : array();
		$m['flxbk-dashboard'] = array(
			'title'   => __( 'Block Editor', 'flx-blocks' ),
			'cap'     => FLXBK_Dashboard_Service::capability(),
			'menu'    => 'flx-blocks',
			'url'     => admin_url( 'admin.php?page=flx-blocks' ),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'layout',
			'version' => FLXBK_VERSION,
			'order'   => 70,
		);
		return $m;
	}
);
