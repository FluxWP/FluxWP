<?php
/**
 * Overview and sales report. WooCommerce reads go through FLXCM_Stats; without it the
 * figures are zero so GraphQL still answers.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Store_Service {

	/**
	 * @return array{0:string,1:string}
	 */
	public static function range( string $preset, string $from, string $to ): array {
		$preset = sanitize_key( $preset );
		$today  = FLXCM_Val::str( FLXCM_Stats::today() );
		if ( 'month' === $preset ) {
			return array( FLXCM_Val::str( wp_date( 'Y-m-01' ) ), $today );
		}
		if ( 'last_month' === $preset ) {
			$stamp = FLXCM_Val::int( FLXCM_Stats::ts( FLXCM_Val::str( wp_date( 'Y-m-01' ) ) ) ) - 12 * HOUR_IN_SECONDS;
			return array( FLXCM_Val::str( wp_date( 'Y-m-01', $stamp ) ), FLXCM_Val::str( wp_date( 'Y-m-d', $stamp ) ) );
		}
		if ( 'year' === $preset ) {
			return array( FLXCM_Val::str( wp_date( 'Y-01-01' ) ), $today );
		}
		if ( in_array( $preset, array( '7', '30', '90' ), true ) ) {
			return self::pair( FLXCM_Stats::range_days( FLXCM_Val::int( $preset ) ) );
		}
		if ( '' !== $from || '' !== $to ) {
			return self::pair( FLXCM_Stats::clean_range( $from, $to ) );
		}
		return self::pair( FLXCM_Stats::range_days( 30 ) );
	}

	/**
	 * @param mixed $span Two dates.
	 * @return array{0:string,1:string}
	 */
	private static function pair( $span ): array {
		$span = FLXCM_Val::arr( $span );
		return array( FLXCM_Val::str( $span[0] ?? '' ), FLXCM_Val::str( $span[1] ?? '' ) );
	}

	/**
	 * @param array<string,mixed>      $sum  Stats summary.
	 * @param array<string,mixed>|null $prev Previous period.
	 * @return array<string,mixed>
	 */
	private static function pack( array $sum, ?array $prev ): array {
		$series = array();
		foreach ( (array) $sum['series'] as $day => $pair ) {
			$pair     = (array) $pair;
			$series[] = array(
				'date'    => FLXCM_Val::str($day),
				'revenue' => flxcm_gql_money( $pair[0] ?? 0 ),
				'orders'  => FLXCM_Val::int(( $pair[1] ?? 0 )),
			);
		}
		$products = array();
		foreach ( (array) $sum['products'] as $p ) {
			$p          = (array) $p;
			$products[] = array(
				'productId' => FLXCM_Val::int(( $p[0] ?? 0 )),
				'name'      => FLXCM_Val::str(( $p[1] ?? '' )),
				'quantity'  => FLXCM_Val::int(( $p[2] ?? 0 )),
				'total'     => flxcm_gql_money( $p[3] ?? 0 ),
			);
		}
		$categories = array();
		foreach ( (array) $sum['categories'] as $c ) {
			$c            = (array) $c;
			$categories[] = array(
				'name'     => FLXCM_Val::str(( $c[0] ?? '' )),
				'quantity' => FLXCM_Val::int(( $c[1] ?? 0 )),
				'total'    => flxcm_gql_money( $c[2] ?? 0 ),
			);
		}
		$methods = array();
		foreach ( (array) $sum['methods'] as $m ) {
			$m         = (array) $m;
			$methods[] = array(
				'title'  => FLXCM_Val::str(( $m[0] ?? '' )),
				'orders' => FLXCM_Val::int(( $m[1] ?? 0 )),
				'total'  => flxcm_gql_money( $m[2] ?? 0 ),
			);
		}
		return array(
			'from'         => FLXCM_Val::str($sum['from']),
			'to'           => FLXCM_Val::str($sum['to']),
			'revenue'      => flxcm_gql_money( $sum['revenue'] ),
			'gross'        => flxcm_gql_money( $sum['gross'] ),
			'refunds'      => flxcm_gql_money( $sum['refunds'] ),
			'shipping'     => flxcm_gql_money( $sum['shipping'] ),
			'tax'          => flxcm_gql_money( $sum['tax'] ),
			'discount'     => flxcm_gql_money( $sum['discount'] ),
			'orders'       => FLXCM_Val::int($sum['orders']),
			'items'        => FLXCM_Val::int($sum['items']),
			'aov'          => flxcm_gql_money( $sum['aov'] ),
			'customers'    => FLXCM_Val::int($sum['customers']),
			'newCustomers' => FLXCM_Val::int($sum['new_customers']),
			'revenueDelta' => $prev ? FLXCM_Stats::delta( $sum['revenue'], $prev['revenue'] ) : null,
			'ordersDelta'  => $prev ? FLXCM_Stats::delta( $sum['orders'], $prev['orders'] ) : null,
			'itemsDelta'   => $prev ? FLXCM_Stats::delta( $sum['items'], $prev['items'] ) : null,
			'aovDelta'     => $prev ? FLXCM_Stats::delta( $sum['aov'], $prev['aov'] ) : null,
			'series'       => $series,
			'products'     => $products,
			'categories'   => $categories,
			'methods'      => $methods,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function empty_summary( string $from, string $to ): array {
		$series = array();
		for ( $t = FLXCM_Val::int( FLXCM_Stats::ts( $from ) ) + 2 * HOUR_IN_SECONDS; $t <= FLXCM_Val::int( FLXCM_Stats::ts( $to, true ) ); $t += DAY_IN_SECONDS ) {
			$series[ wp_date( 'Y-m-d', $t ) ] = array( 0.0, 0 );
		}
		return self::pack(
			array(
				'from'          => $from,
				'to'            => $to,
				'revenue'       => 0,
				'gross'         => 0,
				'refunds'       => 0,
				'shipping'      => 0,
				'tax'           => 0,
				'discount'      => 0,
				'orders'        => 0,
				'items'         => 0,
				'aov'           => 0,
				'customers'     => 0,
				'new_customers' => 0,
				'series'        => $series,
				'products'      => array(),
				'categories'    => array(),
				'methods'       => array(),
			),
			null
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function summary_between( string $from, string $to, bool $compare ): array {
		if ( ! flxcm_wc_ready() ) {
			return self::empty_summary( $from, $to );
		}
		$sum  = FLXCM_Val::map( FLXCM_Stats::summary( $from, $to ) );
		$prev = null;
		if ( $compare ) {
			$span = self::pair( FLXCM_Stats::previous_range( FLXCM_Val::str( $sum['from'] ?? '' ), FLXCM_Val::str( $sum['to'] ?? '' ) ) );
			$prev = FLXCM_Val::map( FLXCM_Stats::summary( $span[0], $span[1] ) );
		}
		return self::pack( $sum, $prev );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function summary_days( int $days ): array {
		$days = min( 365, max( 1, $days ) );
		$span = self::pair( FLXCM_Stats::range_days( $days ) );
		$pack = self::summary_between( $span[0], $span[1], true );
		$pack['days'] = $days;
		$pack['awaiting'] = flxcm_wc_ready() && function_exists( 'wc_orders_count' ) ? FLXCM_Val::int(FLXCM_Stats::awaiting_count()) : 0;
		$pack['lowStock'] = self::low_stock();
		$pack['lowStockThreshold'] = max( 0, FLXCM_Val::int( flxcm_setting( 'low_stock' ) ) );
		$pack['awaitingOrders']    = self::awaiting_orders();
		$from                      = FLXCM_Val::str( $pack['from'] ?? '' );
		$to                        = FLXCM_Val::str( $pack['to'] ?? '' );
		$prev                      = self::pair( FLXCM_Stats::previous_range( $from, $to ) );
		$pack['period']            = sprintf(
			/* translators: 1: from, 2: to, 3: previous from, 4: previous to */
			__( '%1$s – %2$s · compared with %3$s – %4$s', 'flx-commerce' ),
			FLXCM_Val::str( flxcm_date( FLXCM_Val::int( FLXCM_Stats::ts( $from ) ) + 2 * HOUR_IN_SECONDS ) ),
			FLXCM_Val::str( flxcm_date( FLXCM_Val::int( FLXCM_Stats::ts( $to ) ) + 2 * HOUR_IN_SECONDS ) ),
			FLXCM_Val::str( flxcm_date( FLXCM_Val::int( FLXCM_Stats::ts( $prev[0] ) ) + 2 * HOUR_IN_SECONDS, 'short' ) ),
			FLXCM_Val::str( flxcm_date( FLXCM_Val::int( FLXCM_Stats::ts( $prev[1] ) ) + 2 * HOUR_IN_SECONDS, 'short' ) )
		);
		return $pack;
	}

	/**
	 * Up to six orders waiting for payment, as the classic Overview listed them.
	 *
	 * @return list<array{id:int,number:string,name:string,method:string,ago:string,total:string,receiptIn:bool,statusLabel:string,url:string}>
	 */
	public static function awaiting_orders(): array {
		if ( ! flxcm_wc_ready() || ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		$out = array();
		foreach ( FLXCM_Val::arr( FLXCM_Stats::awaiting( 6 ) ) as $o ) {
			if ( ! $o instanceof WC_Order ) {
				continue;
			}
			$rec     = FLXCM_Bank::ID === $o->get_payment_method() ? count( FLXCM_Val::arr( FLXCM_Receipts::of( $o ) ) ) : 0;
			$created = $o->get_date_created();
			$method  = $o->get_payment_method_title();
			$out[]   = array(
				'id'          => $o->get_id(),
				'number'      => FLXCM_Val::str( $o->get_order_number() ),
				'name'        => $o->get_formatted_billing_full_name(),
				'method'      => '' !== $method ? $method : '—',
				'ago'         => $created ? FLXCM_Val::str( flxcm_ago( $created->getTimestamp() ) ) : '',
				'total'       => FLXCM_Val::str( flxcm_money( $o->get_total(), $o->get_currency() ) ),
				'receiptIn'   => $rec > 0,
				'statusLabel' => FLXCM_Val::str( flxcm_status_label( $o->get_status() ) ),
				'url'         => $o->get_edit_order_url(),
			);
		}
		return $out;
	}

	/**
	 * How the store writes money, so a screen can shorten amounts the way the classic screens did.
	 *
	 * @return array{symbol:string,position:string,decimal:string,thousand:string}
	 */
	public static function money_format(): array {
		return array(
			'symbol'   => function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( FLXCM_Val::str( get_woocommerce_currency_symbol() ), ENT_QUOTES, 'UTF-8' ) : '',
			'position' => FLXCM_Val::str( get_option( 'woocommerce_currency_pos', 'left' ) ),
			'decimal'  => function_exists( 'wc_get_price_decimal_separator' ) ? FLXCM_Val::str( wc_get_price_decimal_separator() ) : '.',
			'thousand' => function_exists( 'wc_get_price_thousand_separator' ) ? FLXCM_Val::str( wc_get_price_thousand_separator() ) : ',',
		);
	}

	/**
	 * @return list<array{id:int,name:string,sku:string,stock:int,url:string}>
	 */
	public static function low_stock(): array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return array();
		}
		$out = array();
		foreach ( FLXCM_Val::arr( FLXCM_Stats::low_stock( 6 ) ) as $p ) {
			if ( ! $p instanceof WC_Product ) {
				continue;
			}
			$pid   = $p->get_parent_id() ? $p->get_parent_id() : $p->get_id();
			$sku   = $p->get_sku();
			$out[] = array(
				'id'    => $p->get_id(),
				'name'  => $p->get_name(),
				'sku'   => '' !== $sku ? $sku : '—',
				'stock' => FLXCM_Val::int( $p->get_stock_quantity() ),
				'url'   => FLXCM_Val::str( get_edit_post_link( $pid, 'raw' ) ),
			);
		}
		return $out;
	}

	/**
	 * @param array<mixed> $rows Rows.
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public static function slice( array $rows, int $offset, int $limit ): array {
		$items = array();
		foreach ( array_slice( array_values( $rows ), $offset, $limit ) as $row ) {
			if ( is_array( $row ) ) {
				$items[] = FLXCM_Val::map( $row );
			}
		}
		return array(
			'items' => $items,
			'total' => count( $rows ),
		);
	}
}
