<?php
/**
 * FluxWP Panel screens (Packages, Settings) as the shared React screens (assets/flux/, built with
 * tools/fluxapp/embed-sync.sh flx-setup). Whenever Flux GraphQL (0.2.0+) runs, only the React screen opens;
 * there is no classic view to switch to. Flux Setup is what installs Flux GraphQL, so without it the PHP
 * page stays as the recovery path: the notice with the install button (flxk_graphql_notice) and the
 * installer it opens.
 *
 * @package FLX_Setup
 */

defined( 'ABSPATH' ) || exit;

function flxk_flux_available(): bool {
	return file_exists( FLXK_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
}

/**
 * The chunked bundle loads as an ES module.
 *
 * @param string $tag    Script tag.
 * @param string $handle Handle.
 */
function flxk_flux_module_tag( $tag, $handle ) {
	if ( 'flxk-flux' !== $handle ) {
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

/**
 * Enqueues the bundle and mounts a module in #flxk-app.
 *
 * @param string $module Module id (flxk-packages|flxk-settings).
 * @param string $page   Admin page slug (unused, kept for callers).
 */
function flxk_flux_assets( string $module, string $page ): void {
	$ver    = FLXK_VERSION . '-' . (int) filemtime( FLXK_DIR . 'assets/flux/flux-modules.js' );
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	$opts   = array(
		'module'     => $module,
		'rest'       => rest_url(),
		'nonce'      => wp_create_nonce( 'wp_rest' ),
		'locale'     => get_user_locale(),
		'siteName'   => get_bloginfo( 'name' ),
		'adminUrl'   => admin_url(),
		'theme'      => 'light',
		'colors'     => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
	);
	wp_enqueue_style( 'flxk-flux', FLXK_URL . 'assets/flux/flux-modules.css', array(), $ver );
	wp_enqueue_script( 'flxk-flux', FLXK_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
	add_filter( 'script_loader_tag', 'flxk_flux_module_tag', 10, 2 );
	// Light / dark follows Flux Admin (html.flxa-dark), also when the user switches it on the page.
	wp_add_inline_script(
		'flxk-flux',
		'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxk-app";slot.opts=o;function sync(){var el=document.getElementById("flxk-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
		'before'
	);
}

/**
 * Prints the mount point and loads the bundle. True when the shared screen is shown.
 *
 * @param string $module Module id.
 * @param string $page   Admin page slug.
 */
function flxk_flux_render( string $module, string $page ): bool {
	if ( ! flxk_flux_available() ) {
		return false;
	}
	flxk_flux_assets( $module, $page );
	echo '<style>#flxk-app.flux-root{height:auto;background:transparent;font-family:inherit}</style>';
	echo '<div class="wrap flxk-wrap"><div id="flxk-app"><noscript>' . esc_html__( 'JavaScript is required for this screen.', 'flx-setup' ) . '</noscript></div>';
	if ( function_exists( 'flx_admin_screen_failsafe' ) ) {
		flx_admin_screen_failsafe( 'flxk-app' );
	}
	echo '</div>';
	return true;
}

/**
 * On the recovery page (no Flux GraphQL): the notice with the install button.
 *
 * @param string $page Admin page slug.
 */
function flxk_flux_classic_notice( string $page ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- kept for callers.
	if ( ! flxk_flux_available() && function_exists( 'flxk_graphql_notice' ) ) {
		flxk_graphql_notice( __( 'FluxWP Panel', 'flx-setup' ) );
	}
}

add_filter(
	'flxapp_modules',
	static function ( $m ) {
		$m                  = is_array( $m ) ? $m : array();
		$m['flxk-packages'] = array(
			'title'   => __( 'Packages', 'flx-setup' ),
			'cap'     => 'update_plugins',
			'menu'    => 'flx-setup',
			'url'     => admin_url( 'admin.php?page=flx-setup' ),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'package',
			'version' => FLXK_VERSION,
			'order'   => 20,
		);
		$m['flxk-settings'] = array(
			'title'   => __( 'Update settings', 'flx-setup' ),
			'cap'     => 'manage_options',
			'menu'    => 'flx-setup-settings',
			'url'     => admin_url( 'admin.php?page=flx-setup-settings' ),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'settings',
			'version' => FLXK_VERSION,
			'order'   => 21,
		);
		return $m;
	}
);
