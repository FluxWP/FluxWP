<?php
/**
 * Ortak yardımcılar: ayarlar, para biçimi, ikonlar, sayılan sipariş durumları.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

const FLXCM_OPTION = 'flxcm_settings';

function flxcm_wc_ready() {
	return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_orders' );
}

/** Varsayılan ayarlar. Filtre `flxcm_default_settings`. */
function flxcm_defaults() {
	return (array) apply_filters(
		'flxcm_default_settings',
		array(
			// Panel ve raporlar.
			'paid_statuses'      => array( 'processing', 'completed' ),
			'exclude_donations'  => 1,
			'low_stock'          => 3,
			// Ödeme sayfası.
			'hide_company'       => 0,
			'hide_address_2'     => 0,
			'virtual_no_address' => 1,
			'order_notes'        => 1,
			'sticky_pay'         => 1,
			// Ücretsiz kargo çubuğu.
			'free_ship_bar'      => 1,
			'free_ship_amount'   => 0,
			// Havale/EFT.
			'receipt_max_mb'     => 8,
			'receipt_notify'     => '',
			'defer_to_donate'    => 1,
			'cancel_after_days'  => 0,
			// Ürün feed'i.
			'feed'               => 0,
			'feed_brand'         => '',
			'feed_key'           => '',
			// Bildirimler.
			'notify_customer_wa' => 1,
			'notify_admin_phone' => '',
			'notify_admin_order' => 0,
		)
	);
}

function flxcm_settings() {
	return array_merge( flxcm_defaults(), (array) get_option( FLXCM_OPTION, array() ) );
}

function flxcm_setting( $key ) {
	$s = flxcm_settings();
	return $s[ $key ] ?? null;
}

function flxcm_save_settings( array $values ) {
	$all = array_merge( flxcm_settings(), $values );
	update_option( FLXCM_OPTION, array_intersect_key( $all, flxcm_defaults() ), false );
}

/** Ciroya sayılan durumlar (wc- öneki olmadan). */
function flxcm_paid_statuses() {
	$s = array_values( array_filter( array_map( 'sanitize_key', (array) flxcm_setting( 'paid_statuses' ) ) ) );
	return (array) apply_filters( 'flxcm_paid_statuses', $s ? $s : array( 'processing', 'completed' ) );
}

/**
 * Sipariş mağaza istatistiklerine sayılır mı? Flux Donate kuruluyken yalnız bağış satırı olan siparişler
 * (bağışlar kendi panelinde) varsayılan olarak dışarıda kalır. Filtre `flxcm_count_order`.
 */
function flxcm_count_order( $order ) {
	$count = true;
	if ( flxcm_setting( 'exclude_donations' ) && flxcm_is_donation_order( $order ) ) {
		$count = false;
	}
	return (bool) apply_filters( 'flxcm_count_order', $count, $order );
}

/** Siparişin tüm satırları Flux Donate bağışı mı? */
function flxcm_is_donation_order( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return false;
	}
	$items = $order->get_items( 'line_item' );
	if ( ! $items ) {
		return false;
	}
	if ( ! class_exists( 'FLXB_WC_Cart' ) || ! method_exists( 'FLXB_WC_Cart', 'item_line' ) ) {
		return false;
	}
	foreach ( $items as $item ) {
		if ( ! FLXB_WC_Cart::item_line( $item ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Sipariş kimlikleri, 500'lük sayfalarla (limit -1 bazı veritabanı katmanlarında, ör. SQLite, boş döner;
 * büyük mağazada da tek dev sorgu yerine parça parça okunur).
 */
function flxcm_order_ids( array $args ) {
	$args = array_merge( $args, array( 'limit' => 500, 'return' => 'ids' ) );
	$out  = array();
	for ( $page = 1; $page <= 400; $page++ ) {
		$args['paged'] = $page;
		$ids           = array_map( 'intval', (array) wc_get_orders( $args ) );
		$out           = array_merge( $out, $ids );
		if ( count( $ids ) < 500 ) {
			break;
		}
	}
	return $out;
}

function flxcm_money( $amount, $currency = '' ) {
	$amount = (float) $amount;
	if ( function_exists( 'wc_price' ) ) {
		return wp_strip_all_tags( html_entity_decode( wc_price( $amount, $currency ? array( 'currency' => $currency ) : array() ), ENT_QUOTES, 'UTF-8' ) );
	}
	return number_format_i18n( $amount, 2 );
}

/** Kısa para: 12,4 B ₺ gibi (kartlarda). Mağazanın ondalık/binlik ayıracını kullanır. */
function flxcm_money_short( $amount ) {
	$amount = (float) $amount;
	$sym    = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '';
	$dec    = function_exists( 'wc_get_price_decimal_separator' ) ? wc_get_price_decimal_separator() : '.';
	$tho    = function_exists( 'wc_get_price_thousand_separator' ) ? wc_get_price_thousand_separator() : ',';
	$num    = static function ( $n, $d ) use ( $dec, $tho ) {
		return number_format( $n, $d, $dec, $tho );
	};
	if ( abs( $amount ) >= 1000000 ) {
		/* translators: %s: amount in millions */
		$txt = sprintf( __( '%s Mn', 'flx-commerce' ), $num( $amount / 1000000, 1 ) );
	} elseif ( abs( $amount ) >= 10000 ) {
		/* translators: %s: amount in thousands */
		$txt = sprintf( __( '%s B', 'flx-commerce' ), $num( $amount / 1000, 1 ) );
	} else {
		$txt = $num( $amount, floor( $amount ) == $amount ? 0 : 2 ); // phpcs:ignore Universal.Operators.StrictComparisons
	}
	$pos = get_option( 'woocommerce_currency_pos', 'left' );
	return in_array( $pos, array( 'left', 'left_space' ), true ) ? trim( $sym . ( 'left_space' === $pos ? ' ' : '' ) . $txt ) : trim( $txt . ' ' . $sym );
}

function flxcm_admin_url( $page = 'dashboard', array $args = array() ) {
	$slug = 'dashboard' === $page ? 'flx-commerce' : 'flx-commerce-' . $page;
	return add_query_arg( array_merge( array( 'page' => $slug ), $args ), admin_url( 'admin.php' ) );
}

/** Telefonu yalnız rakamlara çevirir, Türkiye numaralarında 90 ekler. */
function flxcm_phone( $phone ) {
	$d = preg_replace( '/\D+/', '', (string) $phone );
	if ( '' === $d ) {
		return '';
	}
	if ( 0 === strpos( $d, '00' ) ) {
		$d = substr( $d, 2 );
	}
	if ( 10 === strlen( $d ) && '5' === $d[0] ) {
		$d = '90' . $d;
	} elseif ( 11 === strlen( $d ) && '0' === $d[0] ) {
		$d = '90' . substr( $d, 1 );
	}
	return (string) apply_filters( 'flxcm_phone', $d, $phone );
}

/** Lucide (ISC) çizgi ikonları, satır içi SVG. */
function flxcm_icon( $name, $size = 18 ) {
	static $p = array(
		'bag'      => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
		'cart'     => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
		'coins'    => '<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/>',
		'receipt'  => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 17.5v-11"/>',
		'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
		'box'      => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
		'alert'    => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
		'clock'    => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
		'bank'     => '<path d="M3 22h18"/><path d="M6 18v-7"/><path d="M10 18v-7"/><path d="M14 18v-7"/><path d="M18 18v-7"/><path d="m12 2 8 5H4Z"/>',
		'copy'     => '<rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
		'upload'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
		'file'     => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>',
		'check'    => '<path d="M20 6 9 17l-5-5"/>',
		'truck'    => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.62l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
		'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
		'trend'    => '<path d="m22 7-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/>',
		'arrow'    => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
		'refresh'  => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
		'rss'      => '<path d="M4 11a9 9 0 0 1 9 9"/><path d="M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/>',
		'back'     => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
		'mail'     => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
		'phone'    => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
		'pin'      => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
		'message'  => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
		'calendar' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
		'user'     => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
		'note'     => '<path d="M15.5 3H5a2 2 0 0 0-2 2v14c0 1.1.9 2 2 2h14a2 2 0 0 0 2-2V8.5L15.5 3Z"/><path d="M15 3v6h6"/>',
		'search'   => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
	);
	if ( ! isset( $p[ $name ] ) ) {
		return '';
	}
	return '<svg class="flxcm-i" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p[ $name ] . '</svg>';
}

/**
 * Tarih, kullanıcının dilinde (ortak flx_date: TR/EN ay adları çekirdek dil paketinden bağımsız).
 *
 * @param int|string $time   Unix zaman damgası ya da GMT "Y-m-d H:i:s".
 * @param string     $format date | short | datetime | time | PHP biçimi.
 */
function flxcm_date( $time, $format = 'date' ) {
	if ( function_exists( 'flx_date' ) ) {
		return flx_date( $time, $format );
	}
	$ts  = is_numeric( $time ) ? (int) $time : (int) strtotime( $time . ' UTC' );
	$map = array(
		'date'     => get_option( 'date_format' ),
		'short'    => 'j M',
		'datetime' => get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
		'time'     => get_option( 'time_format' ),
	);
	return $ts ? wp_date( $map[ $format ] ?? $format, $ts ) : '—';
}

/** "31 dakika önce" / "31 minutes ago" (ortak flx_time_ago). */
function flxcm_ago( $time ) {
	if ( function_exists( 'flx_time_ago' ) ) {
		return flx_time_ago( $time );
	}
	$ts = is_numeric( $time ) ? (int) $time : (int) strtotime( $time . ' UTC' );
	/* translators: %s: human time difference */
	return $ts ? sprintf( __( '%s önce', 'flx-commerce' ), human_time_diff( $ts ) ) : '—';
}

/** Sipariş durumu adı; WooCommerce'in temel durumları eklentinin kendi çevirisinden (WC dil paketi yoksa da doğru). */
function flxcm_status_label( $status ) {
	$status = 0 === strpos( (string) $status, 'wc-' ) ? substr( $status, 3 ) : (string) $status;
	$labels = array(
		'pending'    => __( 'Ödeme bekleniyor', 'flx-commerce' ),
		'processing' => __( 'Hazırlanıyor', 'flx-commerce' ),
		'on-hold'    => __( 'Beklemede', 'flx-commerce' ),
		'completed'  => __( 'Tamamlandı', 'flx-commerce' ),
		'cancelled'  => __( 'İptal edildi', 'flx-commerce' ),
		'refunded'   => __( 'İade edildi', 'flx-commerce' ),
		'failed'     => __( 'Başarısız', 'flx-commerce' ),
	);
	if ( isset( $labels[ $status ] ) ) {
		return $labels[ $status ];
	}
	return function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $status ) : $status;
}
