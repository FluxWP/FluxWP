<?php
/**
 * Mağaza › Satış raporu: tarih aralığı, özet, günlük grafik, ürün / kategori / ödeme yöntemi kırılımı, CSV.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Page_Reports {

	private static function range() {
		// phpcs:disable WordPress.Security.NonceVerification
		$preset = isset( $_GET['preset'] ) ? sanitize_key( $_GET['preset'] ) : '';
		$from   = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		$to     = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
		// phpcs:enable
		$today = FLXCM_Stats::today();
		switch ( $preset ) {
			case 'month':
				return array( wp_date( 'Y-m-01' ), $today, $preset );
			case 'last_month':
				$first = wp_date( 'Y-m-01', FLXCM_Stats::ts( wp_date( 'Y-m-01' ) ) - 12 * HOUR_IN_SECONDS );
				$last  = wp_date( 'Y-m-d', FLXCM_Stats::ts( wp_date( 'Y-m-01' ) ) - 12 * HOUR_IN_SECONDS );
				return array( $first, $last, $preset );
			case 'year':
				return array( wp_date( 'Y-01-01' ), $today, $preset );
			case '7':
			case '30':
			case '90':
				list( $f, $t ) = FLXCM_Stats::range_days( (int) $preset );
				return array( $f, $t, $preset );
		}
		list( $f, $t ) = FLXCM_Stats::clean_range( $from, $to );
		return array( $f, $t, '' !== $from ? 'custom' : '30' );
	}

	public static function render() {
		if ( ! current_user_can( FLXCM_Admin::CAP_VIEW ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-commerce' ), 403 );
		}
		list( $from, $to, $preset ) = self::range();
		$s                          = FLXCM_Stats::summary( $from, $to );
		list( $pfrom, $pto )        = FLXCM_Stats::previous_range( $s['from'], $s['to'] );
		$p                          = FLXCM_Stats::summary( $pfrom, $pto );
		$csv                        = wp_nonce_url( admin_url( 'admin-post.php?action=flxcm_report_csv&from=' . $s['from'] . '&to=' . $s['to'] ), 'flxcm_report_csv' );
		$actions                    = '<a class="button" href="' . esc_url( $csv ) . '">' . flxcm_icon( 'download', 15 ) . ' ' . esc_html__( 'Download CSV', 'flx-commerce' ) . '</a>';

		FLXCM_Admin::open( __( 'Sales report', 'flx-commerce' ), __( 'Paid orders in the period you pick; net amounts after refunds.', 'flx-commerce' ), $actions );

		$presets = array(
			'7'          => __( '7 days', 'flx-commerce' ),
			'30'         => __( '30 days', 'flx-commerce' ),
			'90'         => __( '90 days', 'flx-commerce' ),
			'month'      => __( 'This month', 'flx-commerce' ),
			'last_month' => __( 'Last month', 'flx-commerce' ),
			'year'       => __( 'This year', 'flx-commerce' ),
		);
		echo '<form class="flxcm-toolbar flxcm-range" method="get"><input type="hidden" name="page" value="flx-commerce-reports"><div class="flxcm-chips">';
		foreach ( $presets as $k => $label ) {
			$on = (string) $k === (string) $preset;
			echo '<a class="flxcm-chip' . ( $on ? ' is-on' : '' ) . '" href="' . esc_url( flxcm_admin_url( 'reports', array( 'preset' => $k ) ) ) . '"' . ( $on ? ' aria-current="true"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</div><div class="flxcm-range__dates"><label><span class="screen-reader-text">' . esc_html__( 'From', 'flx-commerce' ) . '</span><input type="date" name="from" value="' . esc_attr( $s['from'] ) . '" max="' . esc_attr( FLXCM_Stats::today() ) . '"></label><span aria-hidden="true">–</span>';
		echo '<label><span class="screen-reader-text">' . esc_html__( 'To', 'flx-commerce' ) . '</span><input type="date" name="to" value="' . esc_attr( $s['to'] ) . '" max="' . esc_attr( FLXCM_Stats::today() ) . '"></label>';
		echo '<button class="button button-primary" type="submit">' . esc_html__( 'Show', 'flx-commerce' ) . '</button></div></form>';

		echo '<div class="flxcm-kpis flxcm-kpis--6">';
		FLXCM_Admin::kpi( 'coins', __( 'Net revenue', 'flx-commerce' ), flxcm_money_short( $s['revenue'] ), FLXCM_Stats::delta( $s['revenue'], $p['revenue'] ) );
		FLXCM_Admin::kpi( 'receipt', __( 'Orders', 'flx-commerce' ), number_format_i18n( $s['orders'] ), FLXCM_Stats::delta( $s['orders'], $p['orders'] ) );
		FLXCM_Admin::kpi( 'box', __( 'Items sold', 'flx-commerce' ), number_format_i18n( $s['items'] ), FLXCM_Stats::delta( $s['items'], $p['items'] ) );
		FLXCM_Admin::kpi( 'cart', __( 'Average order', 'flx-commerce' ), flxcm_money_short( $s['aov'] ), FLXCM_Stats::delta( $s['aov'], $p['aov'] ) );
		FLXCM_Admin::kpi( 'refresh', __( 'Refunds', 'flx-commerce' ), flxcm_money_short( $s['refunds'] ) );
		FLXCM_Admin::kpi( 'truck', __( 'Shipping income', 'flx-commerce' ), flxcm_money_short( $s['shipping'] ) );
		echo '</div>';

		echo '<div class="flxcm-grid">';
		echo '<section class="flxcm-card flxcm-span-3"><div class="flxcm-card__head"><h2>' . esc_html__( 'Daily revenue', 'flx-commerce' ) . '</h2><span class="flxcm-muted">' . esc_html( flxcm_date( FLXCM_Stats::ts( $s['from'] ) + 2 * HOUR_IN_SECONDS ) . ' – ' . flxcm_date( FLXCM_Stats::ts( $s['to'] ) + 2 * HOUR_IN_SECONDS ) ) . '</span></div>';
		if ( $s['orders'] ) {
			FLXCM_Admin::chart( $s['series'] );
		} else {
			echo '<p class="flxcm-empty">' . esc_html__( 'No paid orders in this period.', 'flx-commerce' ) . '</p>';
		}
		echo '</section></div>';

		echo '<div class="flxcm-grid">';
		echo '<section class="flxcm-card flxcm-span-2 flxcm-card--flush"><div class="flxcm-card__head"><h2>' . esc_html__( 'Products', 'flx-commerce' ) . '</h2></div>';
		if ( $s['products'] ) {
			echo '<table class="flxcm-table"><thead><tr><th>' . esc_html__( 'Product', 'flx-commerce' ) . '</th><th class="num">' . esc_html__( 'Qty', 'flx-commerce' ) . '</th><th class="num">' . esc_html__( 'Net sales', 'flx-commerce' ) . '</th><th class="num">' . esc_html__( 'Share', 'flx-commerce' ) . '</th></tr></thead><tbody>';
			$sum = array_sum( array_column( $s['products'], 3 ) );
			foreach ( array_slice( $s['products'], 0, 50 ) as $r ) {
				$link = $r[0] ? get_edit_post_link( $r[0] ) : '';
				echo '<tr><td data-label="' . esc_attr__( 'Product', 'flx-commerce' ) . '">' . ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $r[1] ) . '</a>' : esc_html( $r[1] ) ) . '</td>';
				echo '<td class="num" data-label="' . esc_attr__( 'Qty', 'flx-commerce' ) . '">' . esc_html( number_format_i18n( $r[2] ) ) . '</td>';
				echo '<td class="num" data-label="' . esc_attr__( 'Net sales', 'flx-commerce' ) . '">' . esc_html( flxcm_money( $r[3] ) ) . '</td>';
				echo '<td class="num" data-label="' . esc_attr__( 'Share', 'flx-commerce' ) . '">' . esc_html( $sum > 0 ? number_format_i18n( $r[3] / $sum * 100, 1 ) . '%' : '—' ) . '</td></tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p class="flxcm-empty">' . esc_html__( 'No sales in this period.', 'flx-commerce' ) . '</p>';
		}
		echo '</section>';
		echo '<div class="flxcm-stack">';
		echo '<section class="flxcm-card"><div class="flxcm-card__head"><h2>' . esc_html__( 'Categories', 'flx-commerce' ) . '</h2></div>';
		FLXCM_Admin::bars_table( array_slice( $s['categories'], 0, 8 ), 0, 2, 1, __( 'pcs', 'flx-commerce' ), __( 'No sales in this period.', 'flx-commerce' ) );
		echo '</section>';
		echo '<section class="flxcm-card"><div class="flxcm-card__head"><h2>' . esc_html__( 'Payment methods', 'flx-commerce' ) . '</h2></div>';
		FLXCM_Admin::bars_table( $s['methods'], 0, 2, 1, __( 'orders', 'flx-commerce' ), __( 'No sales in this period.', 'flx-commerce' ) );
		echo '</section></div></div>';

		echo '<p class="flxcm-muted flxcm-foot">' . esc_html(
			sprintf(
				/* translators: %s: statuses */
				__( 'Counted statuses: %s. Change them in Settings.', 'flx-commerce' ),
				implode( ', ', array_map( 'flxcm_status_label', flxcm_paid_statuses() ) )
			)
		) . '</p>';
		FLXCM_Admin::close();
	}

	public static function csv() {
		check_admin_referer( 'flxcm_report_csv' );
		if ( ! current_user_can( FLXCM_Admin::CAP_VIEW ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-commerce' ), 403 );
		}
		$s = FLXCM_Stats::summary( sanitize_text_field( wp_unslash( $_GET['from'] ?? '' ) ), sanitize_text_field( wp_unslash( $_GET['to'] ?? '' ) ) );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="sales-' . $s['from'] . '_' . $s['to'] . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$put = static function ( array $row ) use ( $out ) {
			fputcsv( $out, array_map( 'flx_csv_cell', $row ) );
		};
		$put( array( __( 'Summary', 'flx-commerce' ), $s['from'] . ' – ' . $s['to'] ) );
		foreach (
			array(
				__( 'Gross revenue', 'flx-commerce' )      => wc_format_decimal( $s['gross'], 2 ),
				__( 'Refunds', 'flx-commerce' )           => wc_format_decimal( $s['refunds'], 2 ),
				__( 'Net revenue', 'flx-commerce' )       => wc_format_decimal( $s['revenue'], 2 ),
				__( 'Shipping income', 'flx-commerce' )   => wc_format_decimal( $s['shipping'], 2 ),
				__( 'Tax', 'flx-commerce' )          => wc_format_decimal( $s['tax'], 2 ),
				__( 'Discount', 'flx-commerce' )        => wc_format_decimal( $s['discount'], 2 ),
				__( 'Orders', 'flx-commerce' )        => $s['orders'],
				__( 'Items sold', 'flx-commerce' )   => $s['items'],
				__( 'Average order', 'flx-commerce' ) => wc_format_decimal( $s['aov'], 2 ),
			) as $k => $v
		) {
			$put( array( $k, $v ) );
		}
		fputcsv( $out, array() );
		$put( array( __( 'Day', 'flx-commerce' ), __( 'Net revenue', 'flx-commerce' ), __( 'Orders', 'flx-commerce' ) ) );
		foreach ( $s['series'] as $day => $v ) {
			$put( array( $day, wc_format_decimal( $v[0], 2 ), $v[1] ) );
		}
		fputcsv( $out, array() );
		$put( array( __( 'Product', 'flx-commerce' ), __( 'Qty', 'flx-commerce' ), __( 'Net sales', 'flx-commerce' ) ) );
		foreach ( $s['products'] as $r ) {
			$put( array( $r[1], $r[2], wc_format_decimal( $r[3], 2 ) ) );
		}
		fputcsv( $out, array() );
		$put( array( __( 'Category', 'flx-commerce' ), __( 'Qty', 'flx-commerce' ), __( 'Net sales', 'flx-commerce' ) ) );
		foreach ( $s['categories'] as $r ) {
			$put( array( $r[0], $r[1], wc_format_decimal( $r[2], 2 ) ) );
		}
		fputcsv( $out, array() );
		$put( array( __( 'Payment method', 'flx-commerce' ), __( 'Orders', 'flx-commerce' ), __( 'Net sales', 'flx-commerce' ) ) );
		foreach ( $s['methods'] as $r ) {
			$put( array( $r[0], $r[1], wc_format_decimal( $r[2], 2 ) ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
}
