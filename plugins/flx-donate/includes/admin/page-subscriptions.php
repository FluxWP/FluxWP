<?php
/**
 * Flux Donate: recurring donations.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Subscriptions {

	const PER_PAGE = 25;

	public static function render() {
		if ( ! flxb_can_view() ) {
			FLXB_Admin::deny();
		}
		$status = FLXB_Admin::q( 'status' );
		$search = FLXB_Admin::q( 's' );
		$page   = max( 1, absint( FLXB_Admin::q( 'paged', '1' ) ) );
		$per    = FLXB_Admin::per_page( 'flxb-subscriptions', self::PER_PAGE );
		$res    = FLXB_Subscriptions::query( array( 'status' => $status, 'search' => $search, 'per_page' => $per, 'page' => $page ) );
		$tot    = FLXB_Subscriptions::totals();
		$args   = array_filter( array( 'page' => 'flxb-subscriptions', 'status' => $status, 's' => $search ), 'strlen' );

		FLXB_Admin::header( __( 'Recurring donations', 'flx-donate' ), __( 'Monthly, weekly or yearly gifts charged automatically by the payment provider.', 'flx-donate' ) );
		echo '<div class="flxb-kpis">';
		echo '<div class="flxb-kpi"><div class="flxb-kpi__label">' . esc_html__( 'Recurring (monthly)', 'flx-donate' ) . '</div><div class="flxb-kpi__value">' . esc_html( flxb_money( $tot['mrr'] ) ) . '</div><div class="flxb-kpi__sub">' . esc_html__( 'monthly value of active ones', 'flx-donate' ) . '</div></div>';
		echo '<div class="flxb-kpi" style="--accent:#2F6FA3"><div class="flxb-kpi__label">' . esc_html__( 'Active', 'flx-donate' ) . '</div><div class="flxb-kpi__value">' . esc_html( number_format_i18n( $tot['active'] ) ) . '</div></div>';
		echo '<div class="flxb-kpi" style="--accent:#E0A526"><div class="flxb-kpi__label">' . esc_html__( 'Past due', 'flx-donate' ) . '</div><div class="flxb-kpi__value">' . esc_html( number_format_i18n( $tot['past_due'] ) ) . '</div></div>';
		echo '<div class="flxb-kpi" style="--accent:#7A8C96"><div class="flxb-kpi__label">' . esc_html__( 'Canceled', 'flx-donate' ) . '</div><div class="flxb-kpi__value">' . esc_html( number_format_i18n( $tot['canceled'] ) ) . '</div></div>';
		echo '</div>';

		$sts = array( '' => __( 'All', 'flx-donate' ), 'active' => __( 'Active', 'flx-donate' ), 'past_due' => __( 'Past due', 'flx-donate' ), 'canceled' => __( 'Canceled', 'flx-donate' ) );
		if ( FLXB_Admin::list_lib() ) {
			/* 2.6.0: shared Flux Admin list library. */
			echo '<form class="flxb-filters flxb-filters--lib" method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"' . FLXB_Admin::list_attrs( 'flxb-subscriptions' ) . '><input type="hidden" name="page" value="flxb-subscriptions">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Name, e-mail, phone, reference…', 'flx-donate' ) . '" aria-label="' . esc_attr__( 'Search', 'flx-donate' ) . '">';
			echo '<label><span>' . esc_html__( 'Status', 'flx-donate' ) . '</span><select name="status">';
			foreach ( $sts as $k => $label ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $status, $k, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></label><button class="flxb-btn flxb-btn--primary" type="submit">' . esc_html__( 'Search', 'flx-donate' ) . '</button></form>';
		} else {
			echo '<form class="flxb-filters" method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="flxb-subscriptions"><div class="flxb-seg">';
			foreach ( $sts as $k => $label ) {
				echo '<a class="' . ( $k === $status ? 'is-on' : '' ) . '" href="' . esc_url( FLXB_Admin_Url::page( 'flxb-subscriptions', array_filter( array( 'status' => $k ), 'strlen' ) ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</div>';
		if ( '' !== $status ) {
			echo '<input type="hidden" name="status" value="' . esc_attr( $status ) . '">';
		}
		echo '<label class="screen-reader-text" for="flxb-s">' . esc_html__( 'Search', 'flx-donate' ) . '</label><input type="search" id="flxb-s" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Name, e-mail, phone, reference…', 'flx-donate' ) . '">';
		echo '<div class="flxb-filters__end"><button class="flxb-btn flxb-btn--primary" type="submit">' . FLXB_Admin::icon( 'search' ) . esc_html__( 'Search', 'flx-donate' ) . '</button></div></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<div class="flxb-panel">';
		if ( ! $res['items'] ) {
			echo '<div class="flxb-emptystate">' . FLXB_Admin::icon( 'repeat', 32 ) . '<h2>' . esc_html__( 'No recurring donations yet', 'flx-donate' ) . '</h2><p>' . esc_html__( 'A recurring donation is created when a payment form is set to charge every day, week, month or year.', 'flx-donate' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			self::table( $res['items'], true );
			echo '<div class="flxb-tablefoot"><span>';
			/* translators: %s: number of recurring donations */
			echo esc_html( sprintf( _n( '%s recurring donation', '%s recurring donations', $res['total'], 'flx-donate' ), number_format_i18n( $res['total'] ) ) );
			echo '</span>' . FLXB_Admin::list_pagination( $res['total'], $per, $page, $args ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
		FLXB_Admin::footer();
	}

	/** Recurring gifts can be canceled from here when Flux Donate takes the payments. */
	private static function can_cancel() {
		return function_exists( 'flxb_owns_payments' ) && flxb_owns_payments() && flxb_can_manage();
	}

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
					echo '<form method="post" class="flxb-inline" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(this.dataset.confirm)" data-confirm="' . esc_attr__( 'Cancel this recurring donation at the payment provider? No further charges will be made.', 'flx-donate' ) . '"><input type="hidden" name="action" value="flxb_sub_cancel"><input type="hidden" name="id" value="' . (int) $s['id'] . '"><input type="hidden" name="back" value="' . esc_attr( remove_query_arg( array( 'flxb_msg', 'flxb_err' ), wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) ) . '">'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
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
