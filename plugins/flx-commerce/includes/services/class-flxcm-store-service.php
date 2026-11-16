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
		$today  = FLXCM_Stats::today();
		if ( 'month' === $preset ) {
			return array( wp_date( 'Y-m-01' ), $today );
		}
		if ( 'last_month' === $preset ) {
			$first = wp_date( 'Y-m-01', FLXCM_Stats::ts( wp_date( 'Y-m-01' ) ) - 12 * HOUR_IN_SECONDS );
			$last  = wp_date( 'Y-m-d', FLXCM_Stats::ts( wp_date( 'Y-m-01' ) ) - 12 * HOUR_IN_SECONDS );
			return array( $first, $last );
		}
		if ( 'year' === $preset ) {
			return array( wp_date( 'Y-01-01' ), $today );
		}
		if ( in_array( $preset, array( '7', '30', '90' ), true ) ) {
			list( $f, $t ) = FLXCM_Stats::range_days( (int) $preset );
			return array( $f, $t );
		}
		if ( '' !== $from || '' !== $to ) {
			list( $f, $t ) = FLXCM_Stats::clean_range( $from, $to );
			return array( $f, $t );
		}
		list( $f, $t ) = FLXCM_Stats::range_days( 30 );
		return array( $f, $t );
	}

	/**
	 * @param array<string,mixed> $sum Stats summary.
	 * @return array<string,mixed>
	 */
	private static function pack( array $sum, ?array $prev ): array {
		$series = array();
		foreach ( (array) $sum['series'] as $day => $pair ) {
			$pair     = (array) $pair;
			$series[] = array(
				'date'    => (string) $day,
				'revenue' => flxcm_gql_money( $pair[0] ?? 0 ),
				'orders'  => (int) ( $pair[1] ?? 0 ),
			);
		}
		$products = array();
		foreach ( (array) $sum['products'] as $p ) {
			$p          = (array) $p;
			$products[] = array(
				'productId' => (int) ( $p[0] ?? 0 ),
				'name'      => (string) ( $p[1] ?? '' ),
				'quantity'  => (int) ( $p[2] ?? 0 ),
				'total'     => flxcm_gql_money( $p[3] ?? 0 ),
			);
		}
		$categories = array();
		foreach ( (array) $sum['categories'] as $c ) {
			$c            = (array) $c;
			$categories[] = array(
				'name'     => (string) ( $c[0] ?? '' ),
				'quantity' => (int) ( $c[1] ?? 0 ),
				'total'    => flxcm_gql_money( $c[2] ?? 0 ),
			);
		}
		$methods = array();
		foreach ( (array) $sum['methods'] as $m ) {
			$m         = (array) $m;
			$methods[] = array(
				'title'  => (string) ( $m[0] ?? '' ),
				'orders' => (int) ( $m[1] ?? 0 ),
				'total'  => flxcm_gql_money( $m[2] ?? 0 ),
			);
		}
		return array(
			'from'         => (string) $sum['from'],
			'to'           => (string) $sum['to'],
			'revenue'      => flxcm_gql_money( $sum['revenue'] ),
			'gross'        => flxcm_gql_money( $sum['gross'] ),
			'refunds'      => flxcm_gql_money( $sum['refunds'] ),
			'shipping'     => flxcm_gql_money( $sum['shipping'] ),
			'tax'          => flxcm_gql_money( $sum['tax'] ),
			'discount'     => flxcm_gql_money( $sum['discount'] ),
			'orders'       => (int) $sum['orders'],
			'items'        => (int) $sum['items'],
			'aov'          => flxcm_gql_money( $sum['aov'] ),
			'customers'    => (int) $sum['customers'],
			'newCustomers' => (int) $sum['new_customers'],
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
		for ( $t = FLXCM_Stats::ts( $from ) + 2 * HOUR_IN_SECONDS; $t <= FLXCM_Stats::ts( $to, true ); $t += DAY_IN_SECONDS ) {
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
		$sum  = FLXCM_Stats::summary( $from, $to );
		$prev = null;
		if ( $compare ) {
			list( $pf, $pt ) = FLXCM_Stats::previous_range( $sum['from'], $sum['to'] );
			$prev            = FLXCM_Stats::summary( $pf, $pt );
		}
		return self::pack( $sum, $prev );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function summary_days( int $days ): array {
		$days = min( 365, max( 1, $days ) );
		list( $from, $to ) = FLXCM_Stats::range_days( $days );
		$pack = self::summary_between( $from, $to, true );
		$pack['days'] = $days;
		$pack['awaiting'] = flxcm_wc_ready() && function_exists( 'wc_orders_count' ) ? (int) FLXCM_Stats::awaiting_count() : 0;
		$pack['lowStock'] = self::low_stock();
		return $pack;
	}

	/**
	 * @return list<array{id:int,name:string,stock:int,url:string}>
	 */
	public static function low_stock(): array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return array();
		}
		$out = array();
		foreach ( FLXCM_Stats::low_stock( 8 ) as $p ) {
			if ( ! $p instanceof WC_Product ) {
				continue;
			}
			$out[] = array(
				'id'    => $p->get_id(),
				'name'  => $p->get_name(),
				'stock' => (int) $p->get_stock_quantity(),
				'url'   => get_edit_post_link( $p->get_id(), 'raw' ) ? (string) get_edit_post_link( $p->get_id(), 'raw' ) : '',
			);
		}
		return $out;
	}

	/**
	 * @param list<array<string,mixed>> $rows Rows.
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public static function slice( array $rows, int $offset, int $limit ): array {
		return array(
			'items' => array_slice( $rows, $offset, $limit ),
			'total' => count( $rows ),
		);
	}
}
