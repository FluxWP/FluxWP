<?php
/**
 * New view (0.5.0, GraphQL): the Statistics, Journeys, Settings and Product telemetry pages open the same
 * React screens as the FluxWP app (assets/flux/, tools/fluxapp/embed-sync.sh flx-analytics). It is the
 * default and has every feature of the classic pages; a user can switch back to the classic pages from
 * the screen header for now. Needs Flux GraphQL.
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

/** Admin page slug => module of @fluxwp/modules. */
function flxan_flux_modules() {
	return array(
		'flx-analytics'            => 'flxan-stats',
		'flx-analytics-journeys'   => 'flxan-journeys',
		'flx-analytics-settings'   => 'flxan-settings',
		'flx-analytics-telemetry'  => 'flxan-telemetry',
	);
}

function flxan_flux_available() {
	return file_exists( FLXAN_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
}

/** Module of the admin page being shown ('' when it is not one of ours). */
function flxan_flux_page() {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$all  = flxan_flux_modules();
	return isset( $all[ $page ] ) ? $page : '';
}

function flxan_flux_ui() {
	return flxan_flux_available() && 'classic' !== get_user_meta( get_current_user_id(), 'flxan_ui', true );
}

/** The current page opens the React screen. */
function flxan_flux_ui_active() {
	return '' !== flxan_flux_page() && flxan_flux_ui();
}

/** Link that switches the current user between the views. */
function flxan_ui_switch_url( $ui ) {
	$page = flxan_flux_page();
	$page = '' === $page ? 'flx-analytics' : $page;
	return wp_nonce_url( add_query_arg( array( 'page' => $page, 'flxan_ui' => 'flux' === $ui ? 'flux' : 'classic' ), admin_url( 'admin.php' ) ), 'flxan_ui' );
}

/** "New view" bar shown on the classic pages. */
function flxan_flux_classic_notice() {
	if ( ! flxan_flux_available() ) {
		return;
	}
	echo '<p class="flxan-newview"><a class="button" href="' . esc_url( flxan_ui_switch_url( 'flux' ) ) . '">' . esc_html__( 'New view', 'flx-analytics' ) . '</a> <span class="description">' . esc_html__( 'Open the new view (same features, works in the FluxWP app too).', 'flx-analytics' ) . '</span></p>';
}

/** The React screen's mount point. */
function flxan_flux_mount() {
	echo '<style>#flxan-app.flux-root{height:auto;background:transparent;font-family:inherit}</style>';
	echo '<div class="wrap flxan-wrap"><div id="flxan-app"><noscript>' . esc_html__( 'This screen requires JavaScript.', 'flx-analytics' ) . '</noscript></div></div>';
}

add_action(
	'admin_init',
	function () {
		if ( ! isset( $_GET['flxan_ui'] ) || '' === flxan_flux_page() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'flxan_view' ) ) {
			return;
		}
		check_admin_referer( 'flxan_ui' );
		if ( 'classic' === $_GET['flxan_ui'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			update_user_meta( get_current_user_id(), 'flxan_ui', 'classic' );
		} else {
			delete_user_meta( get_current_user_id(), 'flxan_ui' );
		}
		wp_safe_redirect( add_query_arg( 'page', flxan_flux_page(), admin_url( 'admin.php' ) ) );
		exit;
	}
);

/** The chunked bundle loads as an ES module. */
function flxan_flux_module_tag( $tag, $handle ) {
	if ( 'flxan-flux' !== $handle ) {
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

add_action(
	'admin_enqueue_scripts',
	function () {
		if ( ! flxan_flux_ui_active() ) {
			return;
		}
		$all    = flxan_flux_modules();
		$module = $all[ flxan_flux_page() ];
		$ver    = FLXAN_VERSION . '-' . (int) filemtime( FLXAN_DIR . 'assets/flux/flux-modules.js' );
		$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#1F2937';
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
			'classicUrl' => flxan_ui_switch_url( 'classic' ),
		);
		if ( 'flxan-stats' === $module && file_exists( FLXAN_DIR . 'assets/world.js' ) ) {
			wp_enqueue_script( 'flxan-world', FLXAN_URL . 'assets/world.js', array(), FLXAN_VERSION, true ); // window.FLXAN_WORLD for the map
		}
		wp_enqueue_style( 'flxan-flux', FLXAN_URL . 'assets/flux/flux-modules.css', array(), $ver );
		wp_enqueue_script( 'flxan-flux', FLXAN_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
		add_filter( 'script_loader_tag', 'flxan_flux_module_tag', 10, 2 );
		// Light / dark follows Flux Admin (html.flxa-dark), also when the user switches it on the page.
		wp_add_inline_script(
			'flxan-flux',
			'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxan-app";slot.opts=o;function sync(){var el=document.getElementById("flxan-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
			'before'
		);
	},
	5
);
