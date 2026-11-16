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
	return file_exists( FLXAN_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && function_exists( 'flx_admin_screen' ) && flx_gql_ready();
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

/** The React screen (Flux GraphQL's screen shell prints it). */
function flxan_flux_mount() {
	flx_admin_screen_render();
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

/** Mount options of the React screens (Flux GraphQL's screen shell adds rest, nonce, locale, site name, admin URL). */
function flxan_flux_options() {
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#1F2937';
	return array(
		'colors'     => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
		'classicUrl' => flxan_ui_switch_url( 'classic' ),
	);
}

/*
 * The pages open through Flux GraphQL's screen shell (includes/screens.php there): first queries preloaded
 * into the page, and the other Flux screens open from the menu without reloading wp-admin.
 */
add_action(
	'admin_menu',
	static function () {
		if ( ! function_exists( 'flx_admin_screen' ) ) {
			return;
		}
		foreach ( flxan_flux_modules() as $page => $module ) {
			$args = array(
				'module'     => $module,
				'assets_dir' => FLXAN_DIR . 'assets/flux/',
				'assets_url' => FLXAN_URL . 'assets/flux/',
				'version'    => FLXAN_VERSION,
				'enabled'    => 'flxan_flux_ui',
				'options'    => 'flxan_flux_options',
				'preload'    => array( array( 'FlxAnalyticsConfig' ) ),
			);
			if ( 'flxan-stats' === $module && file_exists( FLXAN_DIR . 'assets/world.js' ) ) {
				// window.FLXAN_WORLD for the map.
				$args['scripts'] = array( add_query_arg( 'ver', FLXAN_VERSION, FLXAN_URL . 'assets/world.js' ) );
			}
			if ( 'flxan-stats' === $module ) {
				// Default view (last 30 days, no filter in the address); another filter is fetched by the screen.
				$args['preload'][] = array( 'FlxAnalyticsReport', array( 'filter' => array( 'range' => 'DAYS_30' ) ) );
				$args['preload'][] = array(
					'FlxAnalyticsRealtime',
					array(
						'filter'  => array( 'range' => 'DAYS_30' ),
						'minutes' => 30,
						'kpi'     => true,
					),
				);
			}
			if ( 'flxan-settings' === $module ) {
				$args['preload'][] = array( 'FlxAnalyticsSettings' );
			}
			flx_admin_screen( $page, $args );
		}
	},
	20
);
