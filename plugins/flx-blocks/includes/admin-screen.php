<?php
/**
 * New Block Editor dashboard (0.3.0, GraphQL): the page opens the same React screen as the FluxWP app
 * (@fluxwp/modules `flxbk-dashboard`, assets/flux/, tools/fluxapp/embed-sync.sh flx-blocks). It is the
 * default and has every feature of the classic dashboard, plus the design library (linked libraries,
 * sharing, pattern previews). A user can switch back to the classic dashboard from the screen header
 * for now. Needs Flux GraphQL.
 *
 * @package flx-blocks
 */

defined( 'ABSPATH' ) || exit;

/** The new screen can run: bundle shipped and Flux GraphQL ready. */
function flxbk_flux_available(): bool {
	return file_exists( FLXBK_PATH . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
}

/** The current user sees the new screen. */
function flxbk_flux_ui(): bool {
	return flxbk_flux_available() && 'classic' !== get_user_meta( get_current_user_id(), 'flxbk_ui', true );
}

/**
 * Link that switches the current user between the views.
 *
 * @param string $ui flux | classic.
 */
function flxbk_ui_switch_url( string $ui ): string {
	return wp_nonce_url( add_query_arg( array( 'page' => 'flx-blocks', 'flxbk_ui' => 'flux' === $ui ? 'flux' : 'classic' ), admin_url( 'admin.php' ) ), 'flxbk_ui' );
}

add_action(
	'admin_init',
	static function () {
		if ( ! isset( $_GET['flxbk_ui'], $_GET['page'] ) || 'flx-blocks' !== $_GET['page'] || ! FLXBK_Dashboard_Service::can_manage() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		check_admin_referer( 'flxbk_ui' );
		if ( 'classic' === $_GET['flxbk_ui'] ) {
			update_user_meta( get_current_user_id(), 'flxbk_ui', 'classic' );
		} else {
			delete_user_meta( get_current_user_id(), 'flxbk_ui' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=flx-blocks' ) );
		exit;
	}
);

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
function flxbk_flux_assets(): void {
	$ver    = FLXBK_VERSION . '-' . (int) filemtime( FLXBK_PATH . 'assets/flux/flux-modules.js' );
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#4338CA';
	$opts   = array(
		'module'     => 'flxbk-dashboard',
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
		'classicUrl' => flxbk_ui_switch_url( 'classic' ),
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

/** Mount point of the React screen. */
function flxbk_flux_render(): void {
	echo '<style>#flxbk-app.flux-root{height:auto;background:transparent;font-family:inherit}</style>';
	echo '<div class="wrap flxbk-wrap"><div id="flxbk-app"><noscript>' . esc_html__( 'JavaScript is required for this screen.', 'flx-blocks' ) . '</noscript></div></div>';
}

/** Classic dashboard: button to the new view. */
function flxbk_flux_switch_notice(): void {
	if ( ! flxbk_flux_available() ) {
		return;
	}
	echo '<p style="margin:12px 0"><a class="button" href="' . esc_url( flxbk_ui_switch_url( 'flux' ) ) . '">' . esc_html__( 'New view', 'flx-blocks' ) . '</a> <span style="color:#646970">' . esc_html__( 'Open the new view (same features plus the design library, works in the FluxWP app too).', 'flx-blocks' ) . '</span></p>';
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
