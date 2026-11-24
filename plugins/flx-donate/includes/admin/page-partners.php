<?php
/**
 * Flux Donate: Partners (tracking module). The list, the settlement and the partner editor are the React
 * screen (packages/modules/src/donate/Partners.tsx, FLXB_Admin_Partners_Service); the settlement CSV stays
 * a download here.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Partners {

	const SLUG = 'flxb-partners';

	public static function init() {
		add_action( 'admin_post_flxb_partner_settle_csv', array( __CLASS__, 'settle_csv' ) );
	}

	public static function render() {
		if ( ! flxb_can_view() ) {
			FLXB_Admin::deny();
		}
		FLXB_Admin::screen(); // 2.43.0: the list, the settlement and the editor are the React screen.
	}

	public static function settle_csv() {
		if ( ! flxb_can_view() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_partner_settle_csv' );
		$kind   = 'source' === FLXB_Admin::q( 'kind' ) ? 'source' : 'service';
		$period = sanitize_text_field( FLXB_Admin::q( 'period' ) );
		$res = FLXB_Partners::settlement( $kind, $period, absint( FLXB_Admin::q( 'partner' ) ), true );
		flx_csv_stream(
			'settlement-' . $kind . '-' . str_replace( '..', '_', $period ) . '.csv',
			array( __( 'Partner', 'flx-donate' ), __( 'Date', 'flx-donate' ), __( 'Code', 'flx-donate' ), __( 'On behalf of', 'flx-donate' ), __( 'Option', 'flx-donate' ), __( 'Status', 'flx-donate' ), __( 'Price', 'flx-donate' ), __( 'Currency', 'flx-donate' ) ),
			( static function () use ( $res ) {
				foreach ( $res as $r ) {
					foreach ( $r['rows'] as $x ) {
						$sts = FLXB_Track::statuses( (int) $x['type_id'] );
						yield array( $r['name'], $x['day'], $x['code'], $x['beneficiary_name'], $x['option_label'], $sts[ $x['status'] ] ?? $x['status'], $x['price'], $x['cur'] );
					}
				}
			} )()
		);
		exit;
	}
}
