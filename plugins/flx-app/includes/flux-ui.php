<?php
/**
 * New view (0.6.0, GraphQL): "Application" (administrators) and "App access" (everyone) open the same React screens as
 * the FluxWP app (assets/flux/, tools/fluxapp/embed-sync.sh flx-app). It is the default and has every feature of the
 * classic pages; a user can switch back to the classic page from the screen header for now. Needs Flux GraphQL.
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

/** The React view is on for the current user. */
function flxapp_flux_ui() {
	return flxapp_flux_available() && 'classic' !== get_user_meta( get_current_user_id(), 'flxapp_ui', true );
}

/**
 * Link that switches the current user between the views.
 *
 * @param string $ui   flux | classic.
 * @param string $page flxapp-settings | flx-app.
 */
function flxapp_ui_switch_url( $ui, $page ) {
	$base = 'flxapp-settings' === $page ? admin_url( 'admin.php?page=flxapp-settings' ) : admin_url( current_user_can( 'list_users' ) ? 'users.php?page=flx-app' : 'profile.php?page=flx-app' );
	return add_query_arg(
		array(
			'flxapp_ui' => 'flux' === $ui ? 'flux' : 'classic',
			'_wpnonce'  => wp_create_nonce( 'flxapp_ui' ),
		),
		$base
	);
}

add_action(
	'admin_init',
	function () {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $_GET['flxapp_ui'] ) || '' === flxapp_flux_module( $page ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		check_admin_referer( 'flxapp_ui' );
		if ( 'classic' === $_GET['flxapp_ui'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			update_user_meta( get_current_user_id(), 'flxapp_ui', 'classic' );
		} else {
			delete_user_meta( get_current_user_id(), 'flxapp_ui' );
		}
		wp_safe_redirect( remove_query_arg( array( 'flxapp_ui', '_wpnonce' ) ) );
		exit;
	}
);

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

/** Styles and script of the shared bundle, mounted on #flxapp-app. */
function flxapp_flux_assets( $page ) {
	$ver    = FLXAPP_VERSION . '-' . (int) filemtime( FLXAPP_DIR . 'assets/flux/flux-modules.js' );
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	$opts   = array(
		'module'     => flxapp_flux_module( $page ),
		'rest'       => rest_url(),
		'nonce'      => wp_create_nonce( 'wp_rest' ),
		'locale'     => get_user_locale(),
		'siteName'   => get_bloginfo( 'name' ),
		'adminUrl'   => admin_url(),
		'theme'      => 'light',
		'startPage'  => isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
		'colors'     => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
		'classicUrl' => flxapp_ui_switch_url( 'classic', $page ),
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

/** The mount point (the React view's page body). */
function flxapp_flux_root() {
	echo '<style>#flxapp-app.flux-root{height:auto;background:transparent;font-family:inherit}</style>';
	echo '<div class="wrap flxapp-wrap"><div id="flxapp-app"><noscript>' . esc_html__( 'JavaScript is required for this screen.', 'flx-app' ) . '</noscript></div></div>';
}

/** "New view" link of a classic page, shown when the React view is installed but switched off for the user. */
function flxapp_flux_switch_link( $page ) {
	if ( ! flxapp_flux_available() ) {
		return;
	}
	echo '<p><a class="button" href="' . esc_url( flxapp_ui_switch_url( 'flux', $page ) ) . '">' . esc_html__( 'New view', 'flx-app' ) . '</a> <span class="flxapp-muted">' . esc_html__( 'Open the new view (same features, works in the FluxWP app too).', 'flx-app' ) . '</span></p>';
}
