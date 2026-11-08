<?php
/**
 * Ortak Sistem Kontrolü satırları (flx_system_checks) ve düzeltme (flx_system_fixes). Dış HTTP yok.
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_system_checks',
	function ( $checks ) {
		$checks   = is_array( $checks ) ? $checks : array();
		$src      = __( 'Site Analytics', 'flx-analytics' );
		$settings = admin_url( 'admin.php?page=flx-analytics-settings' );
		$s        = flxan_settings();

		if ( (int) get_option( 'flxan_db_version', 0 ) < 1 ) {
			$checks[] = array(
				'id'      => 'flxan.tables',
				'source'  => $src,
				'group'   => 'Plugins',
				'label'   => __( 'Statistics tables', 'flx-analytics' ),
				'status'  => 'error',
				'message' => __( 'Tables are not installed. Deactivate and reactivate the plugin.', 'flx-analytics' ),
			);
			return $checks;
		}

		if ( ! flxan_schema_ok() ) {
			$checks[] = array(
				'id'      => 'flxan.schema',
				'source'  => $src,
				'group'   => 'Plugins',
				'label'   => __( 'Statistics tables', 'flx-analytics' ),
				'status'  => 'error',
				'message' => __( 'Tables are from an older version; visits cannot be saved.', 'flx-analytics' ),
			);
		}

		$checks[] = array(
			'id'           => 'flxan.on',
			'source'       => $src,
			'group'        => 'Plugins',
			'label'        => __( 'Visit counting', 'flx-analytics' ),
			'status'       => $s['on'] ? 'ok' : 'warning',
			'message'      => $s['on'] ? __( 'On. No cookies, no personal data stored.', 'flx-analytics' ) : __( 'Off: visits are not being counted.', 'flx-analytics' ),
			'action_url'   => $settings,
			'action_label' => __( 'Settings', 'flx-analytics' ),
		);

		$dir_ok   = is_dir( flxan_data_dir() ) && is_writable( flxan_data_dir() ) && is_file( flxan_data_dir() . '/config.php' );
		$checks[] = array(
			'id'      => 'flxan.dir',
			'source'  => $src,
			'group'   => 'Plugins',
			'label'   => __( 'Data directory', 'flx-analytics' ),
			'status'  => $dir_ok ? 'ok' : 'error',
			'message' => $dir_ok
				? __( 'wp-content/uploads/flx-analytics is writable and closed to web access.', 'flx-analytics' )
				: __( 'wp-content/uploads/flx-analytics could not be created or is not writable; visits cannot be recorded. Check the directory permissions (755).', 'flx-analytics' ),
		);

		$method   = flxan_method();
		$test     = get_option( 'flxan_fast_test', '' );
		$checks[] = array(
			'id'           => 'flxan.method',
			'source'       => $src,
			'group'        => 'Plugins',
			'label'        => __( 'Collection method', 'flx-analytics' ),
			'status'       => 'fast' === $method ? 'ok' : ( 'rest' === $s['method'] ? 'info' : 'warning' ),
			'message'      => 'fast' === $method
				? __( 'Fast endpoint: visits are recorded without loading WordPress.', 'flx-analytics' )
				: ( 'fail' === $test
					? __( 'The fast endpoint failed its test (a security plugin or the server may block PHP in the plugins folder); the REST endpoint is used. It works, but WordPress loads on every visit.', 'flx-analytics' )
					: __( 'The REST endpoint is used. It works, but WordPress loads on every visit.', 'flx-analytics' ) ),
			'action_url'   => $settings,
			'action_label' => __( 'Settings', 'flx-analytics' ),
		);

		$last     = (int) get_option( 'flxan_last_agg', 0 );
		$pending  = FLXAN_Aggregator::pending_bytes();
		$stale    = $pending > 0 && ( ! $last || time() - $last > HOUR_IN_SECONDS );
		$checks[] = array(
			'id'      => 'flxan.aggregate',
			'source'  => $src,
			'group'   => 'Cron',
			'label'   => __( 'Statistics processing', 'flx-analytics' ),
			'status'  => $stale ? 'warning' : ( $last ? 'ok' : 'info' ),
			'message' => $last
				/* translators: 1: geçen süre, 2: bekleyen veri boyutu */
				? sprintf( __( 'Last processed %1$s. Waiting: %2$s.', 'flx-analytics' ), flx_time_ago( $last ), size_format( $pending ) )
				: __( 'No visits processed yet.', 'flx-analytics' ),
		);

		$err = get_option( 'flxan_last_error' );
		if ( is_array( $err ) && ! empty( $err['time'] ) && time() - (int) $err['time'] < DAY_IN_SECONDS ) {
			$checks[] = array(
				'id'      => 'flxan.error',
				'source'  => $src,
				'group'   => 'Plugins',
				'label'   => __( 'Processing error', 'flx-analytics' ),
				'status'  => 'warning',
				'message' => (string) $err['msg'],
			);
		}

		$cached   = defined( 'LSCWP_V' ) || defined( 'WP_ROCKET_VERSION' ) || defined( 'W3TC' ) || function_exists( 'wp_cache_postload' );
		$checks[] = array(
			'id'      => 'flxan.cache',
			'source'  => $src,
			'group'   => 'Plugins',
			'label'   => __( 'Page cache compatibility', 'flx-analytics' ),
			'status'  => 'ok',
			'message' => $cached
				? __( 'A cache plugin is active; the counter runs in the browser, so cached pages are counted too. The counter script is excluded from combining/delaying.', 'flx-analytics' )
				: __( 'The counter runs in the browser; counting continues if a cache plugin is installed.', 'flx-analytics' ),
		);

		return $checks;
	}
);

add_filter(
	'flx_system_fixes',
	function ( $fixes ) {
		$fixes                       = is_array( $fixes ) ? $fixes : array();
		$fixes['flxan_aggregate_now'] = array(
			'label'       => __( 'Process statistics now', 'flx-analytics' ),
			'description' => __( 'Processes the queued visits into the tables right away.', 'flx-analytics' ),
			'source'      => __( 'Site Analytics', 'flx-analytics' ),
			'checks'      => array( 'flxan.aggregate' ),
			'value'       => null,
			'current'     => function () {
				return size_format( FLXAN_Aggregator::pending_bytes() );
			},
			'run'         => function () {
				$n = FLXAN_Aggregator::run( 25 );
				if ( $n < 0 ) {
					throw new Exception( __( 'Processing is already running; try again in a few seconds.', 'flx-analytics' ) );
				}
				/* translators: %s: satır sayısı */
				return sprintf( __( '%s rows processed.', 'flx-analytics' ), number_format_i18n( $n ) );
			},
		);
		$fixes['flxan_repair_tables'] = array(
			'label'       => __( 'Repair statistics tables', 'flx-analytics' ),
			'description' => __( 'Adds missing columns and tables and updates keys. No data is deleted.', 'flx-analytics' ),
			'source'      => __( 'Site Analytics', 'flx-analytics' ),
			'checks'      => array( 'flxan.schema' ),
			'value'       => null,
			'current'     => function () {
				return flxan_schema_ok() ? 'ok' : __( 'outdated', 'flx-analytics' );
			},
			'run'         => function () {
				flxan_install();
				if ( ! flxan_schema_ok() ) {
					throw new Exception( __( 'Tables could not be updated. The database user needs the ALTER privilege.', 'flx-analytics' ) );
				}
				return __( 'Tables updated.', 'flx-analytics' );
			},
		);
		return $fixes;
	}
);
