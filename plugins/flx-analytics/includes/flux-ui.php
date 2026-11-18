<?php
/**
 * The Statistics, Journeys, Settings and Product telemetry pages open the same React screens as the FluxWP
 * app (assets/flux/, tools/fluxapp/embed-sync.sh flx-analytics) through Flux GraphQL's screen shell: first
 * queries preloaded into the page, the other Flux screens open from the menu without reloading wp-admin.
 * There are no classic pages; when a screen cannot open the shell says so.
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

/** Admin page slug => module of @fluxwp/modules. */
function flxan_flux_modules() {
	return array(
		'flx-analytics'           => 'flxan-stats',
		'flx-analytics-journeys'  => 'flxan-journeys',
		'flx-analytics-settings'  => 'flxan-settings',
		'flx-analytics-telemetry' => 'flxan-telemetry',
	);
}

/** Mount options of the React screens (Flux GraphQL's screen shell adds rest, nonce, locale, site name, admin URL). */
function flxan_flux_options() {
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#1F2937';
	return array(
		'colors' => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
	);
}

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
			if ( 'flxan-journeys' === $module ) {
				// Default view (last 30 days, 4 steps); another filter is fetched by the screen.
				$args['preload'][] = array(
					'FlxAnalyticsJourneys',
					array(
						'filter'  => array( 'range' => 'DAYS_30' ),
						'options' => array(
							'steps' => 4,
							'from'  => '',
							'to'    => '',
							'group' => false,
							'top'   => 100,
						),
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
