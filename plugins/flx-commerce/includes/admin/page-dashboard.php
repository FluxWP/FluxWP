<?php
/**
 * Mağaza › Genel bakış.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Page_Dashboard {

	public static function render() {
		if ( ! current_user_can( FLXCM_Admin::CAP_VIEW ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-commerce' ), 403 );
		}
		$ranges = array(
			1  => __( 'Today', 'flx-commerce' ),
			7  => __( '7 days', 'flx-commerce' ),
			30 => __( '30 days', 'flx-commerce' ),
			90 => __( '90 days', 'flx-commerce' ),
		);
		$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30; // phpcs:ignore WordPress.Security.NonceVerification
		$days = isset( $ranges[ $days ] ) ? $days : 30;
		list( $from, $to )   = FLXCM_Stats::range_days( $days );
		list( $pfrom, $pto ) = FLXCM_Stats::previous_range( $from, $to );
		$s = FLXCM_Stats::summary( $from, $to );
		$p = FLXCM_Stats::summary( $pfrom, $pto );

		$seg = '<div class="flxcm-seg" role="group" aria-label="' . esc_attr__( 'Period', 'flx-commerce' ) . '">';
		foreach ( $ranges as $d => $label ) {
			$seg .= '<a class="flxcm-seg__btn' . ( $d === $days ? ' is-on' : '' ) . '" href="' . esc_url( flxcm_admin_url( 'dashboard', array( 'days' => $d ) ) ) . '"' . ( $d === $days ? ' aria-current="true"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		$seg .= '</div>';

		FLXCM_Admin::open( __( 'Store', 'flx-commerce' ), __( 'Sales, orders awaiting payment and stock at a glance.', 'flx-commerce' ), $seg );
		echo '<div class="flxcm-kpis">';
		FLXCM_Admin::kpi( 'coins', __( 'Net revenue', 'flx-commerce' ), flxcm_money_short( $s['revenue'] ), FLXCM_Stats::delta( $s['revenue'], $p['revenue'] ) );
		FLXCM_Admin::kpi( 'receipt', __( 'Orders', 'flx-commerce' ), number_format_i18n( $s['orders'] ), FLXCM_Stats::delta( $s['orders'], $p['orders'] ) );
		FLXCM_Admin::kpi( 'cart', __( 'Average order', 'flx-commerce' ), flxcm_money_short( $s['aov'] ), FLXCM_Stats::delta( $s['aov'], $p['aov'] ) );
		FLXCM_Admin::kpi( 'users', __( 'Customers', 'flx-commerce' ), number_format_i18n( $s['customers'] ), FLXCM_Stats::delta( $s['customers'], $p['customers'] ), $s['new_customers'] ? sprintf( /* translators: %s: count */ __( '%s new', 'flx-commerce' ), number_format_i18n( $s['new_customers'] ) ) : '' );
		echo '</div>';
		echo '<p class="flxcm-period">' . esc_html(
			sprintf(
				/* translators: 1: from, 2: to, 3: previous from, 4: previous to */
				__( '%1$s – %2$s · compared with %3$s – %4$s', 'flx-commerce' ),
				flxcm_date( FLXCM_Stats::ts( $from ) + 2 * HOUR_IN_SECONDS ),
				flxcm_date( FLXCM_Stats::ts( $to ) + 2 * HOUR_IN_SECONDS ),
				flxcm_date( FLXCM_Stats::ts( $pfrom ) + 2 * HOUR_IN_SECONDS, 'short' ),
				flxcm_date( FLXCM_Stats::ts( $pto ) + 2 * HOUR_IN_SECONDS, 'short' )
			)
		) . '</p>';

		echo '<div class="flxcm-grid">';
		echo '<section class="flxcm-card flxcm-span-2"><div class="flxcm-card__head"><h2>' . esc_html__( 'Daily revenue', 'flx-commerce' ) . '</h2><a href="' . esc_url( flxcm_admin_url( 'reports', array( 'from' => $from, 'to' => $to ) ) ) . '">' . esc_html__( 'Full report', 'flx-commerce' ) . ' ' . flxcm_icon( 'arrow', 14 ) . '</a></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( 1 === $days ) {
			echo '<p class="flxcm-empty">' . esc_html__( 'Pick 7 days or longer for the daily chart.', 'flx-commerce' ) . '</p>';
		} elseif ( ! $s['orders'] ) {
			echo '<p class="flxcm-empty">' . esc_html__( 'No paid orders in this period.', 'flx-commerce' ) . '</p>';
		} else {
			FLXCM_Admin::chart( $s['series'] );
		}
		echo '</section>';

		self::awaiting();

		echo '<section class="flxcm-card"><div class="flxcm-card__head"><h2>' . esc_html__( 'Best sellers', 'flx-commerce' ) . '</h2></div>';
		FLXCM_Admin::bars_table( array_slice( $s['products'], 0, 6 ), 1, 3, 2, __( 'pcs', 'flx-commerce' ), __( 'No sales in this period.', 'flx-commerce' ) );
		echo '</section>';

		self::low_stock();

		echo '<section class="flxcm-card"><div class="flxcm-card__head"><h2>' . esc_html__( 'Payment methods', 'flx-commerce' ) . '</h2></div>';
		FLXCM_Admin::bars_table( array_slice( $s['methods'], 0, 6 ), 0, 2, 1, __( 'orders', 'flx-commerce' ), __( 'No sales in this period.', 'flx-commerce' ) );
		echo '</section>';
		echo '</div>';

		echo '<nav class="flxcm-quick">';
		$links = array(
			array( 'receipt', __( 'Orders', 'flx-commerce' ), function_exists( 'wc_get_container' ) && class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? admin_url( 'admin.php?page=wc-orders' ) : admin_url( 'edit.php?post_type=shop_order' ) ),
			array( 'box', __( 'Products', 'flx-commerce' ), admin_url( 'edit.php?post_type=product' ) ),
			array( 'bag', __( 'New product', 'flx-commerce' ), admin_url( 'post-new.php?post_type=product' ) ),
			array( 'coins', __( 'Coupons', 'flx-commerce' ), admin_url( 'edit.php?post_type=shop_coupon' ) ),
			array( 'truck', __( 'Shipping', 'flx-commerce' ), admin_url( 'admin.php?page=wc-settings&tab=shipping' ) ),
		);
		foreach ( $links as $l ) {
			echo '<a class="flxcm-quick__a" href="' . esc_url( $l[2] ) . '">' . flxcm_icon( $l[0], 16 ) . '<span>' . esc_html( $l[1] ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</nav>';
		FLXCM_Admin::close();
	}

	private static function awaiting() {
		$list = FLXCM_Stats::awaiting( 6 );
		echo '<section class="flxcm-card"><div class="flxcm-card__head"><h2>' . esc_html__( 'Awaiting payment', 'flx-commerce' ) . '</h2>';
		if ( $list ) {
			echo '<span class="flxcm-badge is-wait">' . esc_html( number_format_i18n( FLXCM_Stats::awaiting_count() ) ) . '</span>';
		}
		echo '</div>';
		if ( ! $list ) {
			echo '<p class="flxcm-empty">' . flxcm_icon( 'check', 16 ) . ' ' . esc_html__( 'No orders awaiting payment.', 'flx-commerce' ) . '</p></section>'; // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}
		echo '<ul class="flxcm-list">';
		foreach ( $list as $o ) {
			$rec  = FLXCM_Bank::ID === $o->get_payment_method() ? count( FLXCM_Receipts::of( $o ) ) : 0;
			$tag  = $rec ? '<span class="flxcm-badge is-ok">' . esc_html__( 'Receipt in', 'flx-commerce' ) . '</span>' : '<span class="flxcm-badge is-wait">' . esc_html( flxcm_status_label( $o->get_status() ) ) . '</span>';
			$when = $o->get_date_created() ? flxcm_ago( $o->get_date_created()->getTimestamp() ) : '';
			echo '<li><a href="' . esc_url( $o->get_edit_order_url() ) . '"><span class="flxcm-list__main"><strong>#' . esc_html( $o->get_order_number() ) . ' · ' . esc_html( $o->get_formatted_billing_full_name() ) . '</strong>';
			echo '<small>' . esc_html( ( $o->get_payment_method_title() ? $o->get_payment_method_title() : '—' ) . ' · ' . $when ) . '</small></span>';
			echo '<span class="flxcm-list__side"><strong>' . esc_html( flxcm_money( $o->get_total(), $o->get_currency() ) ) . '</strong>' . $tag . '</span></a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</ul></section>';
	}

	private static function low_stock() {
		$list = FLXCM_Stats::low_stock( 6 );
		echo '<section class="flxcm-card"><div class="flxcm-card__head"><h2>' . esc_html__( 'Low stock', 'flx-commerce' ) . '</h2>';
		/* translators: %s: threshold */
		echo '<span class="flxcm-muted">' . esc_html( sprintf( __( 'threshold %s', 'flx-commerce' ), number_format_i18n( (int) flxcm_setting( 'low_stock' ) ) ) ) . '</span></div>';
		if ( ! $list ) {
			echo '<p class="flxcm-empty">' . flxcm_icon( 'check', 16 ) . ' ' . esc_html__( 'No products running low.', 'flx-commerce' ) . '</p></section>'; // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}
		echo '<ul class="flxcm-list">';
		foreach ( $list as $p ) {
			$q   = (int) $p->get_stock_quantity();
			$pid = $p->get_parent_id() ? $p->get_parent_id() : $p->get_id();
			echo '<li><a href="' . esc_url( get_edit_post_link( $pid, 'raw' ) ) . '"><span class="flxcm-list__main"><strong>' . esc_html( $p->get_name() ) . '</strong>';
			echo '<small>' . esc_html( $p->get_sku() ? $p->get_sku() : '—' ) . '</small></span>';
			echo '<span class="flxcm-list__side"><span class="flxcm-badge ' . ( $q <= 0 ? 'is-bad' : 'is-wait' ) . '">' . esc_html( $q <= 0 ? __( 'Out of stock', 'flx-commerce' ) : sprintf( /* translators: %s: quantity */ __( '%s left', 'flx-commerce' ), number_format_i18n( $q ) ) ) . '</span></span></a></li>';
		}
		echo '</ul></section>';
	}
}
