<?php
/**
 * Satış istatistikleri: tarih aralığında ciro, sipariş, ortalama sepet, ürün/kategori/ödeme yöntemi kırılımı
 * ve günlük seri. WooCommerce sipariş API'si (HPOS ve klasik tablo) ile okunur; sonuç 10 dk önbellekte
 * tutulur, sipariş durumu değişince önbellek sürümü artar.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Stats {

	const MAX_DAYS = 400;

	public static function init() {
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'bump' ) );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'bump' ) );
		add_action( 'woocommerce_new_order', array( __CLASS__, 'bump' ) );
		add_action( 'update_option_' . FLXCM_OPTION, array( __CLASS__, 'bump' ) );
	}

	public static function bump() {
		update_option( 'flxcm_stats_ver', (int) get_option( 'flxcm_stats_ver', 1 ) + 1, false );
	}

	/** Yerel tarih (Y-m-d) → UTC zaman damgası; $end true ise günün sonu. */
	public static function ts( $ymd, $end = false ) {
		$tz = wp_timezone();
		$d  = date_create_immutable( (string) $ymd . ( $end ? ' 23:59:59' : ' 00:00:00' ), $tz );
		return $d ? $d->getTimestamp() : time();
	}

	public static function today() {
		return wp_date( 'Y-m-d' );
	}

	/** Bugünden geriye N gün (bugün dahil). */
	public static function range_days( $days ) {
		$days = max( 1, (int) $days );
		$to   = self::today();
		$from = wp_date( 'Y-m-d', self::ts( $to ) - ( $days - 1 ) * DAY_IN_SECONDS + 2 * HOUR_IN_SECONDS );
		return array( $from, $to );
	}

	/** Geçerli bir aralık: [from, to] (Y-m-d), en fazla MAX_DAYS gün. */
	public static function clean_range( $from, $to ) {
		$re = '/^\d{4}-\d{2}-\d{2}$/';
		$to = preg_match( $re, (string) $to ) ? (string) $to : self::today();
		if ( ! preg_match( $re, (string) $from ) ) {
			list( $from ) = self::range_days( 30 );
		}
		if ( $from > $to ) {
			list( $from, $to ) = array( $to, $from );
		}
		if ( ( self::ts( $to ) - self::ts( $from ) ) / DAY_IN_SECONDS > self::MAX_DAYS ) {
			$from = wp_date( 'Y-m-d', self::ts( $to ) - self::MAX_DAYS * DAY_IN_SECONDS );
		}
		return array( $from, $to );
	}

	/** Aralıktaki sayılan sipariş kimlikleri. */
	public static function order_ids( $from, $to, array $statuses = array() ) {
		$statuses = $statuses ? $statuses : flxcm_paid_statuses();
		return flxcm_order_ids(
			array(
				'type'         => 'shop_order',
				'status'       => array_map( static function ( $s ) { return 'wc-' . $s; }, $statuses ),
				'date_created' => self::ts( $from ) . '...' . self::ts( $to, true ),
				'orderby'      => 'date',
				'order'        => 'ASC',
			)
		);
	}

	/**
	 * Aralığın özeti.
	 *
	 * @return array { from, to, revenue, gross, refunds, orders, items, aov, customers, new_customers,
	 *                 series: [ Y-m-d => [revenue, orders] ], products: [ [id, name, qty, total] ],
	 *                 categories: [ [name, qty, total] ], methods: [ [title, orders, total] ] }
	 */
	public static function summary( $from, $to ) {
		list( $from, $to ) = self::clean_range( $from, $to );
		$key  = 'flxcm_s_' . md5( $from . $to . (int) get_option( 'flxcm_stats_ver', 1 ) . get_locale() );
		$hit  = get_transient( $key );
		if ( is_array( $hit ) ) {
			return $hit;
		}
		$series = array();
		for ( $t = self::ts( $from ) + 2 * HOUR_IN_SECONDS; $t <= self::ts( $to, true ); $t += DAY_IN_SECONDS ) {
			$series[ wp_date( 'Y-m-d', $t ) ] = array( 0.0, 0 );
		}
		$out = array(
			'from'          => $from,
			'to'            => $to,
			'revenue'       => 0.0,
			'gross'         => 0.0,
			'refunds'       => 0.0,
			'shipping'      => 0.0,
			'tax'           => 0.0,
			'discount'      => 0.0,
			'orders'        => 0,
			'items'         => 0,
			'aov'           => 0.0,
			'customers'     => 0,
			'new_customers' => 0,
			'series'        => $series,
			'products'      => array(),
			'categories'    => array(),
			'methods'       => array(),
		);
		$emails   = array();
		$products = array();
		$cats     = array();
		$methods  = array();
		$cat_name = array();
		foreach ( array_chunk( self::order_ids( $from, $to ), 200 ) as $chunk ) {
			foreach ( $chunk as $oid ) {
				$order = wc_get_order( $oid );
				if ( ! $order instanceof WC_Order || ! flxcm_count_order( $order ) ) {
					continue;
				}
				$total    = (float) $order->get_total();
				$refunded = (float) $order->get_total_refunded();
				$net      = $total - $refunded;
				$day      = $order->get_date_created() ? wp_date( 'Y-m-d', $order->get_date_created()->getTimestamp() ) : $to;
				++$out['orders'];
				$out['gross']    += $total;
				$out['refunds']  += $refunded;
				$out['revenue']  += $net;
				$out['shipping'] += (float) $order->get_shipping_total();
				$out['tax']      += (float) $order->get_total_tax();
				$out['discount'] += (float) $order->get_discount_total();
				if ( isset( $out['series'][ $day ] ) ) {
					$out['series'][ $day ][0] += $net;
					++$out['series'][ $day ][1];
				}
				$email = strtolower( (string) $order->get_billing_email() );
				if ( '' !== $email ) {
					$emails[ $email ] = true;
				}
				$m = $order->get_payment_method();
				if ( ! isset( $methods[ $m ] ) ) {
					$methods[ $m ] = array( $order->get_payment_method_title() ? $order->get_payment_method_title() : ( $m ? $m : __( 'No payment', 'flx-commerce' ) ), 0, 0.0 );
				}
				++$methods[ $m ][1];
				$methods[ $m ][2] += $net;
				foreach ( $order->get_items( 'line_item' ) as $item ) {
					$pid  = (int) $item->get_product_id();
					$qty  = (int) $item->get_quantity() - abs( (int) $order->get_qty_refunded_for_item( $item->get_id() ) );
					$line = (float) $item->get_total() - abs( (float) $order->get_total_refunded_for_item( $item->get_id() ) );
					$out['items'] += $qty;
					if ( ! isset( $products[ $pid ] ) ) {
						$products[ $pid ] = array( $pid, $item->get_name(), 0, 0.0 );
					}
					$products[ $pid ][2] += $qty;
					$products[ $pid ][3] += $line;
					if ( $pid ) {
						if ( ! isset( $cat_name[ $pid ] ) ) {
							$terms            = get_the_terms( $pid, 'product_cat' );
							$cat_name[ $pid ] = $terms && ! is_wp_error( $terms ) ? $terms[0]->name : __( 'Uncategorized', 'flx-commerce' );
						}
						$c = $cat_name[ $pid ];
						if ( ! isset( $cats[ $c ] ) ) {
							$cats[ $c ] = array( $c, 0, 0.0 );
						}
						$cats[ $c ][1] += $qty;
						$cats[ $c ][2] += $line;
					}
				}
			}
		}
		$out['customers'] = count( $emails );
		$out['aov']       = $out['orders'] ? $out['revenue'] / $out['orders'] : 0.0;
		if ( $emails ) {
			global $wpdb;
			$t                    = FLXCM_Install::table();
			$out['new_customers'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE first_at >= %s AND first_at <= %s", gmdate( 'Y-m-d H:i:s', self::ts( $from ) ), gmdate( 'Y-m-d H:i:s', self::ts( $to, true ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$sort = static function ( $i ) {
			return static function ( $a, $b ) use ( $i ) {
				return $b[ $i ] <=> $a[ $i ];
			};
		};
		usort( $products, $sort( 3 ) );
		usort( $cats, $sort( 2 ) );
		uasort( $methods, $sort( 2 ) );
		$out['products']   = array_values( $products );
		$out['categories'] = array_values( $cats );
		$out['methods']    = array_values( $methods );
		set_transient( $key, $out, 10 * MINUTE_IN_SECONDS );
		return $out;
	}

	/** Önceki eşit uzunluktaki döneme göre değişim (%); önceki 0 ise null. */
	public static function delta( $now, $before ) {
		$before = (float) $before;
		if ( $before <= 0 ) {
			return null;
		}
		return ( (float) $now - $before ) / $before * 100;
	}

	public static function previous_range( $from, $to ) {
		$days = (int) round( ( self::ts( $to, true ) - self::ts( $from ) ) / DAY_IN_SECONDS );
		$pto  = wp_date( 'Y-m-d', self::ts( $from ) - 12 * HOUR_IN_SECONDS );
		$pfr  = wp_date( 'Y-m-d', self::ts( $pto ) - ( $days - 1 ) * DAY_IN_SECONDS + 2 * HOUR_IN_SECONDS );
		return array( $pfr, $pto );
	}

	/** Ödeme bekleyen siparişler (Beklemede / Ödeme bekleniyor), en yeni önce. */
	public static function awaiting( $limit = 8 ) {
		$orders = wc_get_orders(
			array(
				'type'    => 'shop_order',
				'status'  => array( 'wc-on-hold', 'wc-pending' ),
				'limit'   => max( 1, (int) $limit ) * 2,
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);
		$out = array();
		foreach ( (array) $orders as $o ) {
			if ( $o instanceof WC_Order && flxcm_count_order( $o ) ) {
				$out[] = $o;
			}
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	public static function awaiting_count() {
		$n = 0;
		foreach ( array( 'on-hold', 'pending' ) as $s ) {
			$n += (int) wc_orders_count( $s );
		}
		return $n;
	}

	/** Stoğu eşik veya altında olan ürünler (stok yönetimi açık). */
	public static function low_stock( $limit = 8 ) {
		$th = max( 0, (int) flxcm_setting( 'low_stock' ) );
		$q  = new WP_Query(
			array(
				'post_type'      => array( 'product', 'product_variation' ),
				'post_status'    => 'publish',
				'posts_per_page' => (int) $limit,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_stock', // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array( 'key' => '_manage_stock', 'value' => 'yes' ),
					array( 'key' => '_stock', 'value' => $th, 'compare' => '<=', 'type' => 'NUMERIC' ),
				),
			)
		);
		$out = array();
		foreach ( $q->posts as $id ) {
			$p = wc_get_product( $id );
			if ( $p && ! ( $p->get_parent_id() && 'publish' !== get_post_status( $p->get_parent_id() ) ) && ! ( class_exists( 'FLXB_WC_Products' ) && get_post_meta( $p->get_parent_id() ? $p->get_parent_id() : $id, '_flxb_type_id', true ) ) ) {
				$out[] = $p;
			}
		}
		return $out;
	}
}
FLXCM_Stats::init();
