<?php
/**
 * Flux Donate: recurring donations (the screen, and the table of the Flux CRM person card).
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Subscriptions {

	/** 2.39.0: the list is the React screen (packages/modules/src/donate). */
	public static function render() {
		if ( ! flxb_can_view() ) {
			FLXB_Admin::deny();
		}
		FLXB_Admin::screen();
	}

	/** Recurring gifts can be canceled from here when Flux Donate takes the payments. */
	private static function can_cancel() {
		return function_exists( 'flxb_owns_payments' ) && flxb_owns_payments() && flxb_can_manage();
	}

	/** The donor's recurring donations on the Flux CRM person card (FLXB_Page_Donors::crm_main_before()). */
	public static function table( array $items, $show_donor ) {
		echo '<div class="flxb-tablewrap"><table class="flxb-table"><thead><tr>';
		if ( $show_donor ) {
			echo '<th scope="col">' . esc_html__( 'Donor', 'flx-donate' ) . '</th>';
		}
		echo '<th scope="col" class="num">' . esc_html__( 'Amount', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Interval', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Status', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Started', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Next charge', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Gifts', 'flx-donate' ) . '</th>' . ( self::can_cancel() ? '<th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'flx-donate' ) . '</span></th>' : '' ) . '</tr></thead><tbody>';
		global $wpdb;
		$dn = flxb_table( 'donations' );
		foreach ( $items as $s ) {
			$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$dn} WHERE subscription_id = %d AND status IN ('completed','partially_refunded')", (int) $s['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			echo '<tr>';
			if ( $show_donor ) {
				echo '<td class="flxb-col-main">' . ( (int) $s['donor_id'] ? '<a href="' . esc_url( FLXB_Admin_Url::donor( (int) $s['donor_id'] ) ) . '">' . esc_html( FLXB_Admin::donor_name( $s ) ) . '</a>' : '<span class="flxb-muted">' . esc_html__( 'Anonymous', 'flx-donate' ) . '</span>' );
				echo '<span class="flxb-sub2"><code>' . esc_html( $s['provider_ref'] ) . '</code></span></td>';
			}
			echo '<td class="num' . ( $show_donor ? '' : ' flxb-col-main' ) . '" data-label="' . esc_attr__( 'Amount', 'flx-donate' ) . '"><span>' . esc_html( flxb_money( $s['amount'], $s['currency'] ) ) . '</span></td>';
			echo '<td data-label="' . esc_attr__( 'Interval', 'flx-donate' ) . '"><span>' . esc_html( flxb_interval_label( $s['interval_unit'] ) ) . '</span></td>';
			$why = '';
			if ( 'iyzico' === $s['provider'] && 'canceled' !== $s['status'] ) {
				$sm = FLXB_Subscriptions::meta( $s );
				if ( ! empty( $sm['last_error'] ) && (int) ( $sm['retry_count'] ?? 0 ) > 0 ) {
					/* translators: 1: attempts made, 2: attempts allowed, 3: error from iyzico */
					$why = sprintf( __( 'Attempt %1$s of %2$s failed: %3$s', 'flx-donate' ), number_format_i18n( (int) $sm['retry_count'] ), number_format_i18n( FLXB_Iyzico_Recurring::max_attempts() ), (string) $sm['last_error'] );
				}
				if ( ! empty( $sm['card']['label'] ) ) {
					$why = trim( (string) $sm['card']['label'] . ( '' !== $why ? ' · ' . $why : '' ) );
				}
			}
			echo '<td data-label="' . esc_attr__( 'Status', 'flx-donate' ) . '"><span>' . FLXB_Admin::pill( $s['status'] ) . ( '' !== $why ? '<span class="flxb-sub2">' . esc_html( $why ) . '</span>' : '' ) . '</span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td class="flxb-col-date" data-label="' . esc_attr__( 'Started', 'flx-donate' ) . '"><span>' . esc_html( flxb_date( $s['started_at'], 'date' ) ) . '</span></td>';
			echo '<td class="flxb-col-date" data-label="' . esc_attr__( 'Next charge', 'flx-donate' ) . '"><span>' . esc_html( 'canceled' === $s['status'] ? ( $s['canceled_at'] ? sprintf( /* translators: %s: date */ __( 'canceled %s', 'flx-donate' ), flxb_date( $s['canceled_at'], 'date' ) ) : '—' ) : flxb_date( $s['next_charge_at'], 'date' ) ) . '</span></td>';
			echo '<td data-label="' . esc_attr__( 'Gifts', 'flx-donate' ) . '"><span><a href="' . esc_url( FLXB_Admin_Url::page( 'flxb-donations', array( 'donor_id' => (int) $s['donor_id'], 'type' => 'recurring' ) ) ) . '">' . esc_html( number_format_i18n( $n ) ) . '</a></span></td>';
			if ( self::can_cancel() ) {
				echo '<td class="flxb-col-act">';
				$p = flxf_payment_provider( $s['provider'] );
				if ( $p && method_exists( $p, 'cancel_subscription' ) && in_array( $s['status'], array( 'active', 'past_due' ), true ) ) {
					echo '<form method="post" class="flxb-inline" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-flx-confirm-danger data-flx-confirm="' . esc_attr__( 'Cancel this recurring donation at the payment provider? No further charges will be made.', 'flx-donate' ) . '"><input type="hidden" name="action" value="flxb_sub_cancel"><input type="hidden" name="id" value="' . (int) $s['id'] . '"><input type="hidden" name="back" value="' . esc_attr( remove_query_arg( array( 'flxb_msg', 'flxb_err' ), wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) ) . '">'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					wp_nonce_field( 'flxb_sub_cancel_' . (int) $s['id'] );
					echo '<button type="submit" class="flxb-linkbtn flxb-linkbtn--danger">' . esc_html__( 'Cancel', 'flx-donate' ) . '</button></form>';
				}
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}
}
