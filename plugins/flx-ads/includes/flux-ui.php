<?php
/**
 * Flux Ads: the new view. The Ad conversions page opens the same React screen as the FluxWP app
 * (assets/flux/, tools/fluxapp/embed-sync.sh flx-ads), with its data over GraphQL. It is the default and
 * has every feature of the classic page; a user can switch back to the classic page from the screen header
 * for now. Needs Flux GraphQL.
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

/**
 * The bundle is built and Flux GraphQL (capability model) is ready.
 */
function flxads_flux_available(): bool {
	return file_exists( FLXADS_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
}

/**
 * The current user sees the new view.
 */
function flxads_flux_ui(): bool {
	return flxads_flux_available() && 'classic' !== get_user_meta( get_current_user_id(), 'flxads_ui', true );
}

/**
 * Link that switches the current user between the views.
 *
 * @param string $ui   flux | classic.
 * @param string $tab  Tab to land on.
 */
function flxads_ui_switch_url( string $ui, string $tab = 'report' ): string {
	return wp_nonce_url( flxads_admin_url( $tab, array( 'flxads_ui' => 'flux' === $ui ? 'flux' : 'classic' ) ), 'flxads_ui' );
}

add_action(
	'admin_init',
	static function (): void {
		if ( ! isset( $_GET['flxads_ui'], $_GET['page'] ) || ! flxads_can() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$page = sanitize_key( wp_unslash( (string) $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'flx-ads' !== $page && 0 !== strpos( $page, 'flx-ads-' ) ) {
			return;
		}
		check_admin_referer( 'flxads_ui' );
		if ( 'classic' === $_GET['flxads_ui'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			update_user_meta( get_current_user_id(), 'flxads_ui', 'classic' );
		} else {
			delete_user_meta( get_current_user_id(), 'flxads_ui' );
		}
		wp_safe_redirect( flxads_admin_url( flxads_current_tab() ) );
		exit;
	}
);

/**
 * The chunked bundle loads as an ES module.
 *
 * @param string $tag    Script tag.
 * @param string $handle Script handle.
 */
function flxads_flux_module_tag( $tag, $handle ) {
	if ( 'flxads-flux' !== $handle ) {
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
 * Enqueues the bundle and mounts it into #flxads-app.
 *
 * @param string $tab Tab to open first.
 */
/**
 * Mount options of the screen besides what the Flux GraphQL shell adds (rest, nonce, locale…).
 *
 * @param string $page Page slug (flx-ads, flx-ads-<tab>).
 */
function flxads_flux_options( string $page = '' ): array {
	$tab    = 0 === strpos( $page, 'flx-ads-' ) ? substr( $page, 8 ) : ( '' === $page ? flxads_current_tab() : 'report' );
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	return array(
		'colors'     => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
		'classicUrl' => flxads_ui_switch_url( 'classic', $tab ),
	);
}

// Flux GraphQL screen shell: every Ads page is one screen with its own start page; first data printed by
// PHP, in-place navigation between the pages from the menu.
add_action(
	'admin_menu',
	static function (): void {
		if ( ! function_exists( 'flx_admin_screen' ) || ! flxads_flux_available() ) {
			return;
		}
		foreach ( array( 'report', 'campaigns', 'suggestions', 'studio', 'planner', 'experiments', 'value', 'spend', 'audiences', 'log', 'links', 'settings' ) as $tab ) {
			$page = flxads_page_slug( $tab );
			flx_admin_screen(
				$page,
				array(
					'module'       => 'flxads-ads',
					'assets_dir'   => FLXADS_DIR . 'assets/flux/',
					'assets_url'   => FLXADS_URL . 'assets/flux/',
					'version'      => FLXADS_VERSION,
					'enabled'      => 'flxads_flux_ui',
					'options'      => 'flxads_flux_options',
					'start'        => $tab,
					'start_param'  => 'flx-ads' === $page ? 'tab' : '',
					'container_id' => 'flxads-app',
					'preload'      => 'report' === $tab ? array( array( 'FlxAdsContext' ), array( 'FlxAdsReport', array( 'filter' => array( 'by' => 'channel', 'kind' => 'all', 'range' => '30' ) ) ) ) : array( array( 'FlxAdsContext' ) ),
					'wrap_class'   => 'flxads-wrap',
				)
			);
		}
	},
	20
);

function flxads_flux_assets( string $tab ): void {
	if ( function_exists( 'flx_admin_screen_current' ) && '' !== flx_admin_screen_current() ) {
		return; // The Flux GraphQL screen shell loads the bundle.
	}
	$ver  = FLXADS_VERSION . '-' . (int) filemtime( FLXADS_DIR . 'assets/flux/flux-modules.js' );
	$opts = array_merge(
		array(
			'module'    => 'flxads-ads',
			'rest'      => rest_url(),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'locale'    => get_user_locale(),
			'siteName'  => get_bloginfo( 'name' ),
			'adminUrl'  => admin_url(),
			'theme'     => 'light',
			'startPage' => $tab,
		),
		flxads_flux_options( flxads_page_slug( $tab ) )
	);
	wp_enqueue_style( 'flxads-flux', FLXADS_URL . 'assets/flux/flux-modules.css', array(), $ver );
	wp_enqueue_script( 'flxads-flux', FLXADS_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
	add_filter( 'script_loader_tag', 'flxads_flux_module_tag', 10, 2 );
	// Light / dark follows Flux Admin (html.flxa-dark), also when the user switches it on the page.
	wp_add_inline_script(
		'flxads-flux',
		'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxads-app";slot.opts=o;function sync(){var el=document.getElementById("flxads-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
		'before'
	);
}

/**
 * The page body of the new view.
 */
function flxads_flux_render(): void {
	if ( function_exists( 'flx_admin_screen_render' ) && flx_admin_screen_render() ) {
		return;
	}
	echo '<style>#flxads-app.flux-root{height:auto;background:transparent;font-family:inherit}</style>';
	echo '<div class="wrap flxads-wrap"><div id="flxads-app"><noscript>' . esc_html__( 'JavaScript is required for this screen.', 'flx-ads' ) . '</noscript></div></div>';
}
