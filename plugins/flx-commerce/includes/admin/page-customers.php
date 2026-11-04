<?php
/**
 * Mağaza › Müşteriler.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Page_Customers {

	public static function render() {
		if ( ! current_user_can( FLXCM_Admin::CAP_VIEW ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-commerce' ), 403 );
		}
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! empty( $_GET['customer'] ) || ! empty( $_GET['email'] ) ) {
			FLXCM_Page_Customer::render( absint( $_GET['customer'] ?? 0 ), isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '' );
			return;
		}
		$args = array(
			's'        => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'segment'  => isset( $_GET['segment'] ) ? sanitize_key( $_GET['segment'] ) : 'all',
			'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'last_at',
			'order'    => isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'desc',
			'paged'    => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1,
			'per_page' => 25,
		);
		// phpcs:enable
		$c     = FLXCM_Customers::counts();
		$q     = FLXCM_Customers::query( $args );
		$built = (int) get_option( 'flxcm_customers_built' );
		$can   = current_user_can( FLXCM_Admin::CAP_MANAGE );

		$actions = '';
		if ( $can ) {
			$csv      = wp_nonce_url( admin_url( 'admin-post.php?action=flxcm_customers_csv&segment=' . rawurlencode( $args['segment'] ) . '&s=' . rawurlencode( $args['s'] ) ), 'flxcm_customers_csv' );
			$actions .= '<a class="button" href="' . esc_url( $csv ) . '">' . flxcm_icon( 'download', 15 ) . ' ' . esc_html__( 'CSV indir', 'flx-commerce' ) . '</a>';
			$actions .= '<button type="button" class="button" data-flxcm-rebuild>' . flxcm_icon( 'refresh', 15 ) . ' ' . esc_html__( 'Yeniden oluştur', 'flx-commerce' ) . '</button>';
		}
		FLXCM_Admin::open( __( 'Müşteriler', 'flx-commerce' ), __( 'Üye ve misafir müşteriler, e-posta başına; sipariş sayısı ve toplam harcamayla.', 'flx-commerce' ), $actions );

		echo '<p class="flxcm-progress" data-flxcm-progress hidden role="status"></p>';
		if ( ! $built && $can ) {
			echo '<div class="flxcm-callout">' . flxcm_icon( 'alert', 18 ) . '<div><strong>' . esc_html__( 'Eski siparişler henüz taranmadı.', 'flx-commerce' ) . '</strong> ' . esc_html__( 'Liste yeni siparişlerle kendiliğinden dolar. Önceki siparişleri de eklemek için "Yeniden oluştur"a basın; tarama 100\'lük parçalarla yapılır.', 'flx-commerce' ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}

		echo '<div class="flxcm-kpis">';
		FLXCM_Admin::kpi( 'users', __( 'Müşteri', 'flx-commerce' ), number_format_i18n( $c['all'] ) );
		FLXCM_Admin::kpi( 'refresh', __( 'Tekrar alan', 'flx-commerce' ), number_format_i18n( $c['repeat'] ), null, $c['all'] ? sprintf( /* translators: %s: percent */ __( '%%%s', 'flx-commerce' ), number_format_i18n( $c['repeat'] / $c['all'] * 100, 0 ) ) : '' );
		FLXCM_Admin::kpi( 'coins', __( 'Müşteri başı harcama', 'flx-commerce' ), flxcm_money_short( $c['ltv'] ) );
		FLXCM_Admin::kpi( 'clock', __( '90 gündür almayan', 'flx-commerce' ), number_format_i18n( $c['lapsed'] ) );
		echo '</div>';

		$segs = array(
			'all'    => array( __( 'Tümü', 'flx-commerce' ), $c['all'] ),
			'new'    => array( __( 'Yeni (30 gün)', 'flx-commerce' ), $c['new'] ),
			'repeat' => array( __( 'Tekrar alan', 'flx-commerce' ), $c['repeat'] ),
			'vip'    => array( __( 'En çok harcayan %10', 'flx-commerce' ), $c['vip'] ),
			'lapsed' => array( __( 'Uzaklaşan (90 gün)', 'flx-commerce' ), $c['lapsed'] ),
		);
		echo '<div class="flxcm-toolbar"><div class="flxcm-chips">';
		foreach ( $segs as $k => $sg ) {
			$on = $k === $args['segment'];
			echo '<a class="flxcm-chip' . ( $on ? ' is-on' : '' ) . '" href="' . esc_url( flxcm_admin_url( 'customers', array( 'segment' => $k, 's' => $args['s'] ) ) ) . '"' . ( $on ? ' aria-current="true"' : '' ) . '>' . esc_html( $sg[0] ) . ' <span>' . esc_html( number_format_i18n( $sg[1] ) ) . '</span></a>';
		}
		echo '</div><form class="flxcm-search" method="get"><input type="hidden" name="page" value="flx-commerce-customers"><input type="hidden" name="segment" value="' . esc_attr( $args['segment'] ) . '">';
		echo '<label class="screen-reader-text" for="flxcm-s">' . esc_html__( 'Müşteri ara', 'flx-commerce' ) . '</label>';
		echo '<span class="flxcm-search__box">' . flxcm_icon( 'search', 16 ) . '<input id="flxcm-s" type="search" name="s" value="' . esc_attr( $args['s'] ) . '" placeholder="' . esc_attr__( 'Ad, e-posta, telefon, şehir', 'flx-commerce' ) . '"></span></form></div>'; // phpcs:ignore WordPress.Security.EscapeOutput

		if ( ! $q['rows'] ) {
			echo '<div class="flxcm-card"><p class="flxcm-empty">' . esc_html( '' !== $args['s'] ? __( 'Aramaya uyan müşteri yok.', 'flx-commerce' ) : __( 'Henüz müşteri yok.', 'flx-commerce' ) ) . '</p></div>';
			FLXCM_Admin::close();
			return;
		}

		$sort = static function ( $col, $label ) use ( $args ) {
			$on    = $args['orderby'] === $col;
			$order = $on && 'desc' === $args['order'] ? 'asc' : 'desc';
			$url   = flxcm_admin_url( 'customers', array( 'segment' => $args['segment'], 's' => $args['s'], 'orderby' => $col, 'order' => $order ) );
			$arrow = $on ? ( 'desc' === $args['order'] ? ' ↓' : ' ↑' ) : '';
			return '<a href="' . esc_url( $url ) . '">' . esc_html( $label . $arrow ) . '</a>';
		};
		echo '<div class="flxcm-card flxcm-card--flush"><table class="flxcm-table flxcm-table--customers"><thead><tr>';
		echo '<th>' . $sort( 'name', __( 'Müşteri', 'flx-commerce' ) ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<th class="num">' . $sort( 'orders', __( 'Sipariş', 'flx-commerce' ) ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<th class="num">' . $sort( 'total', __( 'Toplam', 'flx-commerce' ) ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<th>' . $sort( 'last_at', __( 'Son sipariş', 'flx-commerce' ) ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<th>' . esc_html__( 'Not', 'flx-commerce' ) . '</th></tr></thead><tbody>';
		$orders_url = function_exists( 'wc_get_container' ) && class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? admin_url( 'admin.php?page=wc-orders' ) : admin_url( 'edit.php?post_type=shop_order' );
		foreach ( $q['rows'] as $r ) {
			$name = '' !== trim( (string) $r['name'] ) ? $r['name'] : $r['email'];
			$init = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( trim( (string) $name ), 0, 1 ) ) : strtoupper( substr( (string) $name, 0, 1 ) );
			echo '<tr><td data-label="' . esc_attr__( 'Müşteri', 'flx-commerce' ) . '"><div class="flxcm-who"><span class="flxcm-avatar" aria-hidden="true">' . esc_html( $init ) . '</span><span><a class="flxcm-who__name" href="' . esc_url( FLXCM_Page_Customer::url( $r['id'] ) ) . '"><strong>' . esc_html( $name ) . '</strong></a>';
			if ( (int) $r['user_id'] ) {
				echo ' <span class="flxcm-badge">' . esc_html__( 'Üye', 'flx-commerce' ) . '</span>';
			}
			if ( (int) $r['orders'] >= 2 ) {
				echo ' <span class="flxcm-badge is-ok">' . esc_html__( 'Tekrar', 'flx-commerce' ) . '</span>';
			}
			echo '<small><a href="mailto:' . esc_attr( $r['email'] ) . '">' . esc_html( $r['email'] ) . '</a>';
			if ( $r['phone'] ) {
				echo ' · <a href="https://wa.me/' . esc_attr( flxcm_phone( $r['phone'] ) ) . '" target="_blank" rel="noopener">' . esc_html( $r['phone'] ) . '</a>';
			}
			if ( $r['city'] ) {
				echo ' · ' . esc_html( $r['city'] );
			}
			echo '</small></span></div></td>';
			echo '<td class="num" data-label="' . esc_attr__( 'Sipariş', 'flx-commerce' ) . '"><a href="' . esc_url( add_query_arg( 's', rawurlencode( $r['email'] ), $orders_url ) ) . '">' . esc_html( number_format_i18n( (int) $r['orders'] ) ) . '</a></td>';
			echo '<td class="num" data-label="' . esc_attr__( 'Toplam', 'flx-commerce' ) . '"><strong>' . esc_html( flxcm_money( $r['total'] ) ) . '</strong></td>';
			echo '<td data-label="' . esc_attr__( 'Son sipariş', 'flx-commerce' ) . '">' . ( $r['last_at'] ? '<a href="' . esc_url( ( $o = wc_get_order( (int) $r['last_order'] ) ) ? $o->get_edit_order_url() : $orders_url ) . '">' . esc_html( get_date_from_gmt( $r['last_at'], 'd.m.Y' ) ) . '</a>' : '—' ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Not', 'flx-commerce' ) . '">';
			if ( $can ) {
				echo '<input type="text" class="flxcm-note-input" data-flxcm-note="' . (int) $r['id'] . '" value="' . esc_attr( (string) $r['note'] ) . '" placeholder="' . esc_attr__( 'Not ekle…', 'flx-commerce' ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: customer */ __( '%s için not', 'flx-commerce' ), $name ) ) . '">';
			} else {
				echo esc_html( (string) $r['note'] );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';

		if ( $q['pages'] > 1 ) {
			echo '<div class="flxcm-pager">';
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $q['paged'],
						'total'     => $q['pages'],
						'prev_text' => '‹',
						'next_text' => '›',
					)
				)
			);
			echo '</div>';
		}
		FLXCM_Admin::close();
	}
}
