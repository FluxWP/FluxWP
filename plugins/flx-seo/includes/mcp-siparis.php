<?php
/**
 * MCP: WooCommerce sipariş özeti (yalnız okuma).
 *
 * flxseo_order_summary: bir günün (varsayılan bugün, `date` ile geçmiş gün) ödenmiş sipariş sayısı, ciro (iadeler
 * düşülmüş), ortalama, onay/ödeme bekleyenler ve son siparişler. `compare` önceki günü ve farkı da döndürür. Kişisel veri en az: müşteri için yalnız ad + soyadın baş harfi; adres, e-posta, telefon yok.
 * TV sipariş paneli dakikada bir çağırır; bu yüzden başarılı çağrılar Son işlemler'e yazılmaz ('log' => false).
 * Böyle bir istemci için anahtarı yalnız bu araçla sınırlayın (Yapay zekâ › Araçları sınırla).
 *
 * @package FLX_SEO
 */

defined( 'ABSPATH' ) || exit;

/** Taslak (blok ödeme sayfası) dışındaki sipariş durumları, "wc-" öneksiz. */
function flxseo_order_statuses() {
	$out = array();
	foreach ( array_keys( wc_get_order_statuses() ) as $s ) {
		$s = 0 === strpos( $s, 'wc-' ) ? substr( $s, 3 ) : $s;
		if ( 'checkout-draft' !== $s ) {
			$out[] = $s;
		}
	}
	return $out;
}

/** Panelde gösterilecek kadar sipariş bilgisi. Tutar iadeler düşülmüş. */
function flxseo_order_row( WC_Order $o ) {
	$items = 0;
	foreach ( $o->get_items() as $item ) {
		$items += (int) $item->get_quantity();
	}
	$last    = trim( (string) $o->get_billing_last_name() );
	$created = $o->get_date_created();
	return array(
		'id'               => $o->get_id(),
		'number'           => (string) $o->get_order_number(),
		'status'           => $o->get_status(),
		'total'            => (float) wc_format_decimal( (float) $o->get_total() - (float) $o->get_total_refunded(), wc_get_price_decimals() ),
		'currency'         => $o->get_currency(),
		'date_created_gmt' => $created ? gmdate( 'Y-m-d\TH:i:s\Z', $created->getTimestamp() ) : '',
		'customer_name'    => trim( $o->get_billing_first_name() . ( '' !== $last ? ' ' . mb_substr( $last, 0, 1 ) . '.' : '' ) ),
		'item_count'       => $items,
	);
}

/**
 * Bir günün özeti: [$start, $end) aralığında oluşturulan siparişler.
 *
 * @return array{0: array, 1: WC_Order[]} Özet ve aralıktaki siparişler (yeniden eskiye, en çok $limit).
 */
function flxseo_order_day_summary( DateTimeImmutable $start, DateTimeImmutable $end, array $statuses, $limit = 500 ) {
	$orders        = wc_get_orders(
		array(
			'type'         => 'shop_order',
			'status'       => $statuses,
			'date_created' => $start->getTimestamp() . '...' . ( $end->getTimestamp() - 1 ),
			'limit'        => $limit + 1,
			'orderby'      => 'date',
			'order'        => 'DESC',
		)
	);
	$paid_statuses = wc_get_is_paid_statuses();
	$sum           = array( 'orders' => 0, 'revenue' => 0.0, 'waiting' => 0, 'waiting_total' => 0.0, 'cancelled' => 0 );
	$list          = array_slice( $orders, 0, $limit );
	foreach ( $list as $o ) {
		$net = (float) $o->get_total() - (float) $o->get_total_refunded();
		if ( $o->has_status( $paid_statuses ) ) {
			$sum['orders']++;
			$sum['revenue'] += $net;
		} elseif ( $o->has_status( array( 'pending', 'on-hold' ) ) ) {
			$sum['waiting']++;
			$sum['waiting_total'] += $net;
		} elseif ( $o->has_status( array( 'cancelled', 'failed' ) ) ) {
			$sum['cancelled']++;
		}
	}
	$dec                  = wc_get_price_decimals();
	$sum['average']       = $sum['orders'] ? (float) wc_format_decimal( $sum['revenue'] / $sum['orders'], $dec ) : 0.0;
	$sum['revenue']       = (float) wc_format_decimal( $sum['revenue'], $dec );
	$sum['waiting_total'] = (float) wc_format_decimal( $sum['waiting_total'], $dec );
	$sum['truncated']     = count( $orders ) > $limit;
	return array( $sum, $list );
}

/** İki gün özeti arasındaki fark (gün − önceki gün); yüzde, önceki değer 0 ise null. */
function flxseo_order_day_change( array $cur, array $prev ) {
	$dec = wc_get_price_decimals();
	$out = array();
	foreach ( array( 'orders', 'revenue', 'average', 'waiting', 'waiting_total', 'cancelled' ) as $k ) {
		$d         = (float) $cur[ $k ] - (float) $prev[ $k ];
		$out[ $k ] = array(
			'diff'    => in_array( $k, array( 'orders', 'waiting', 'cancelled' ), true ) ? (int) $d : (float) wc_format_decimal( $d, $dec ),
			'percent' => (float) $prev[ $k ] ? round( $d / (float) $prev[ $k ] * 100, 1 ) : null,
		);
	}
	return $out;
}

add_filter(
	'flx_mcp_tools',
	function ( $tools ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return $tools;
		}
		$tools['flxseo_order_summary'] = array(
			'aliases'     => array( 'order_summary' ), // Flux SEO 1.5.x ve öncesindeki ad.
			'source'      => 'flx-seo',
			'title'       => 'Sipariş özeti',
			'description' => 'WooCommerce: bir günün ödenmiş sipariş sayısı, ciro (iadeler düşülmüş), ortalama sepet, ödeme/onay bekleyenler, iptal/başarısız ve son siparişler (numara, durum, tutar, saat, ürün adedi, müşteri adı + soyadın baş harfi). Gün varsayılan bugün; date (Y-m-d, ör. dün) ile geçmiş bir gün, compare=true ile önceki günün özeti (previous) ve fark (change: diff + percent) de döner. Günlük brifing için: dünü date ile al, compare=true ile önceki günle karşılaştır. Adres, e-posta, telefon dönmez. Gün sitenin saat diliminde ya da verilen timezone\'da.',
			'readonly'    => true,
			'log'         => false,
			'cap'         => 'view_woocommerce_reports',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'date'     => array( 'type' => 'string', 'pattern' => '^\\d{4}-\\d{2}-\\d{2}$', 'description' => 'Özeti alınacak gün (Y-m-d), ör. 2026-11-11. Boşsa bugün. Gelecek tarih olmaz.' ),
					'compare'  => array( 'type' => 'boolean', 'default' => false, 'description' => 'true ise bir önceki günün özeti (previous) ve fark (change) da döner.' ),
					'recent'   => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 20, 'default' => 8, 'description' => 'Kaç son sipariş dönsün (0–20). date verilirse o günün son siparişleri.' ),
					'timezone' => array( 'type' => 'string', 'description' => 'Günün başlangıcı için IANA saat dilimi, ör. Europe/Istanbul. Boşsa sitenin saat dilimi.' ),
				),
			),
			'run'         => function ( $a ) {
				$tz = wp_timezone();
				if ( ! empty( $a['timezone'] ) && in_array( (string) $a['timezone'], timezone_identifiers_list(), true ) ) {
					$tz = new DateTimeZone( (string) $a['timezone'] );
				}
				$today = new DateTimeImmutable( 'today', $tz );
				$start = $today;
				$date  = trim( (string) ( $a['date'] ?? '' ) );
				if ( '' !== $date ) {
					$d = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $tz );
					if ( ! $d || $d->format( 'Y-m-d' ) !== $date ) {
						throw new Exception( 'Geçersiz tarih: ' . $date . '. Biçim Y-m-d olmalı, ör. ' . $today->modify( '-1 day' )->format( 'Y-m-d' ) . '.' );
					}
					if ( $d > $today ) {
						throw new Exception( 'Gelecek tarih olmaz: ' . $date . '. Bugün ' . $today->format( 'Y-m-d' ) . '.' );
					}
					$start = $d;
				}
				$end      = $start->modify( '+1 day' );
				$is_today = $start == $today; // phpcs:ignore Universal.Operators.StrictComparisons -- DateTime değer karşılaştırması.
				$statuses = flxseo_order_statuses();
				list( $sum, $day_orders ) = flxseo_order_day_summary( $start, $end, $statuses );

				$n = max( 0, min( 20, (int) ( $a['recent'] ?? 8 ) ) );
				if ( ! $n ) {
					$recent = array();
				} elseif ( $is_today ) {
					// Bugün: eskisi gibi son siparişler (gün sınırı yok; TV paneli dün geceyi de görsün).
					$recent = wc_get_orders(
						array(
							'type'    => 'shop_order',
							'status'  => $statuses,
							'limit'   => $n,
							'orderby' => 'date',
							'order'   => 'DESC',
						)
					);
				} else {
					$recent = array_slice( $day_orders, 0, $n );
				}

				$out = array(
					'date'          => $start->format( 'Y-m-d' ),
					'is_today'      => $is_today,
					'timezone'      => $tz->getName(),
					'currency'      => get_woocommerce_currency(),
					'paid_statuses' => array_values( wc_get_is_paid_statuses() ),
					'today'         => $sum, // Ad geriye uyum için; date verilince o günün özeti.
				);
				if ( ! empty( $a['compare'] ) && false !== filter_var( $a['compare'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ) {
					$prev_start      = $start->modify( '-1 day' );
					list( $prev )    = flxseo_order_day_summary( $prev_start, $start, $statuses );
					$out['previous'] = array( 'date' => $prev_start->format( 'Y-m-d' ), 'summary' => $prev );
					$out['change']   = flxseo_order_day_change( $sum, $prev );
				}
				$out['recent'] = array_map( 'flxseo_order_row', $recent );
				return $out;
			},
		);
		return $tools;
	}
);
