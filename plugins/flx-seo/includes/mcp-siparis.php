<?php
/**
 * MCP: WooCommerce sipariş özeti (yalnız okuma).
 *
 * order_summary: bugünkü ödenmiş sipariş sayısı, ciro (iadeler düşülmüş), ortalama, onay/ödeme bekleyenler
 * ve son siparişler. Kişisel veri en az: müşteri için yalnız ad + soyadın baş harfi; adres, e-posta, telefon yok.
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

add_filter(
	'flx_mcp_tools',
	function ( $tools ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return $tools;
		}
		$tools['order_summary'] = array(
			'title'       => 'Sipariş özeti',
			'description' => 'WooCommerce: bugünkü ödenmiş sipariş sayısı, ciro (iadeler düşülmüş), ortalama sepet, ödeme/onay bekleyenler ve son siparişler (numara, durum, tutar, saat, ürün adedi, müşteri adı + soyadın baş harfi). Adres, e-posta, telefon dönmez. "Bugün" sitenin saat diliminde ya da verilen timezone\'da.',
			'readonly'    => true,
			'log'         => false,
			'cap'         => 'view_woocommerce_reports',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'recent'   => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 20, 'default' => 8, 'description' => 'Kaç son sipariş dönsün (0–20).' ),
					'timezone' => array( 'type' => 'string', 'description' => 'Günün başlangıcı için IANA saat dilimi, ör. Europe/Istanbul. Boşsa sitenin saat dilimi.' ),
				),
			),
			'run'         => function ( $a ) {
				$tz = wp_timezone();
				if ( ! empty( $a['timezone'] ) && in_array( (string) $a['timezone'], timezone_identifiers_list(), true ) ) {
					$tz = new DateTimeZone( (string) $a['timezone'] );
				}
				$start    = new DateTimeImmutable( 'today', $tz );
				$statuses = flxseo_order_statuses();
				$limit    = 500;
				$today    = wc_get_orders(
					array(
						'type'         => 'shop_order',
						'status'       => $statuses,
						'date_created' => '>=' . $start->getTimestamp(),
						'limit'        => $limit + 1,
						'orderby'      => 'date',
						'order'        => 'DESC',
					)
				);
				$paid_statuses = wc_get_is_paid_statuses();
				$sum           = array( 'orders' => 0, 'revenue' => 0.0, 'waiting' => 0, 'waiting_total' => 0.0, 'cancelled' => 0 );
				foreach ( array_slice( $today, 0, $limit ) as $o ) {
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
				$sum['truncated']     = count( $today ) > $limit;

				$n      = max( 0, min( 20, (int) ( $a['recent'] ?? 8 ) ) );
				$recent = $n ? wc_get_orders(
					array(
						'type'    => 'shop_order',
						'status'  => $statuses,
						'limit'   => $n,
						'orderby' => 'date',
						'order'   => 'DESC',
					)
				) : array();

				return array(
					'date'          => $start->format( 'Y-m-d' ),
					'timezone'      => $tz->getName(),
					'currency'      => get_woocommerce_currency(),
					'paid_statuses' => array_values( $paid_statuses ),
					'today'         => $sum,
					'recent'        => array_map( 'flxseo_order_row', $recent ),
				);
			},
		);
		return $tools;
	}
);
