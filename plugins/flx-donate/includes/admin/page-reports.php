<?php
/**
 * Flux Donate 2.11: Reports. Pick a report, choose a period, read the table, download CSV; managers edit any
 * report's JSON. 2.44.0: the pages are the React screen (packages/modules/src/donate/Reports.tsx,
 * FLXB_Admin_Reports_Service over GraphQL); this class keeps the CSV download.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Reports {

	const SLUG = 'flxb-reports';

	public static function init() {
		add_action( 'admin_post_flxb_report_csv', array( __CLASS__, 'csv' ) );
	}

	public static function enabled() {
		return (bool) apply_filters( 'flxb_reports_enabled', ! empty( flxb_setting( 'module_reports', 0 ) ) || flxb_track_enabled() );
	}

	public static function render() {
		if ( ! flxb_can_view() ) {
			FLXB_Admin::deny();
		}
		FLXB_Admin::screen();
	}

	public static function csv() {
		if ( ! flxb_can_view() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_report_csv' );
		$r = FLXB_Reports::get( sanitize_key( FLXB_Admin::q( 'r' ) ) );
		if ( ! $r ) {
			wp_die( esc_html__( 'Report not found.', 'flx-donate' ) );
		}
		$res = FLXB_Reports::run( $r, array( 'from' => FLXB_Admin::q( 'from' ), 'to' => FLXB_Admin::q( 'to' ) ) );
		flx_csv_stream(
			'report-' . $r['id'] . ( $res['from'] ? '-' . $res['from'] : '' ) . ( $res['to'] ? '_' . $res['to'] : '' ) . '.csv',
			wp_list_pluck( $res['cols'], 'label' ),
			( static function () use ( $res ) {
				foreach ( $res['rows'] as $row ) {
					$line = array();
					foreach ( $res['cols'] as $k => $c ) {
						$line[] = $c['dim'] ? FLXB_Reports::show( $c['kind'], $row[ $k ] ) : $row[ $k ];
					}
					yield $line;
				}
			} )()
		);
		exit;
	}
}
