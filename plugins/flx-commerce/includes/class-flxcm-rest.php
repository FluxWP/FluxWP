<?php
/**
 * Uygulama REST'i: satış özeti, müşteriler, dekont bekleyen havale siparişleri.
 *
 *   GET  /wp-json/flx-commerce/v1/summary
 *   GET  /wp-json/flx-commerce/v1/customers
 *   GET  /wp-json/flx-commerce/v1/customers/<id>
 *   GET  /wp-json/flx-commerce/v1/receipts
 *   POST /wp-json/flx-commerce/v1/receipts/<order_id>/approve
 *
 * Okuma `view_woocommerce_reports` (ya da `manage_woocommerce`). Onay `edit_shop_orders`.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_REST {

	const NS = 'flx-commerce/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'nocache' ), 10, 3 );
	}

	public static function can_view() {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'view_woocommerce_reports' );
	}

	public static function can_approve() {
		return current_user_can( 'edit_shop_orders' ) || current_user_can( 'manage_woocommerce' );
	}

	public static function routes() {
		$view = array( __CLASS__, 'can_view' );
		$args = array(
			'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
			'per_page' => array( 'type' => 'integer', 'default' => 25, 'minimum' => 1, 'maximum' => 100 ),
		);
		register_rest_route( self::NS, '/summary', array( 'methods' => 'GET', 'permission_callback' => $view, 'callback' => array( __CLASS__, 'summary' ) ) );
		register_rest_route( self::NS, '/customers', array( 'methods' => 'GET', 'permission_callback' => $view, 'callback' => array( __CLASS__, 'customers' ), 'args' => $args ) );
		register_rest_route( self::NS, '/customers/(?P<id>\d+)', array( 'methods' => 'GET', 'permission_callback' => $view, 'callback' => array( __CLASS__, 'customer' ) ) );
		register_rest_route( self::NS, '/receipts', array( 'methods' => 'GET', 'permission_callback' => array( __CLASS__, 'can_approve' ), 'callback' => array( __CLASS__, 'receipts' ), 'args' => $args ) );
		register_rest_route( self::NS, '/receipts/(?P<id>\d+)', array( 'methods' => 'GET', 'permission_callback' => array( __CLASS__, 'can_approve' ), 'callback' => array( __CLASS__, 'receipt' ) ) );
		register_rest_route( self::NS, '/receipts/(?P<id>\d+)/approve', array( 'methods' => 'POST', 'permission_callback' => array( __CLASS__, 'can_approve' ), 'callback' => array( __CLASS__, 'approve' ) ) );
	}

	public static function nocache( $result, $server, $request ) {
		unset( $server );
		if ( $result instanceof WP_REST_Response && 0 === strpos( (string) $request->get_route(), '/' . self::NS . '/' ) && '/flx-commerce/v1/receipt' !== (string) $request->get_route() ) {
			$result->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
			$result->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
			$result->header( 'CDN-Cache-Control', 'no-store' );
			do_action( 'litespeed_control_set_nocache', 'flxcm-app' );
		}
		return $result;
	}

	private static function when( $mysql ) {
		if ( ! $mysql ) {
			return '';
		}
		$ts = strtotime( $mysql . ' UTC' );
		return $ts ? flxcm_date( $ts, 'datetime' ) : '';
	}

	public static function customer_out( array $r ) {
		return array(
			'id'          => (int) $r['id'],
			'name'        => (string) $r['name'],
			'email'       => (string) $r['email'],
			'phone'       => (string) ( $r['phone'] ?? '' ),
			'city'        => (string) ( $r['city'] ?? '' ),
			'orders'      => (int) $r['orders'],
			'total'       => round( (float) $r['total'], 2 ),
			'total_label' => flxcm_money( $r['total'] ),
			'note'        => (string) ( $r['note'] ?? '' ),
			'first_label' => self::when( $r['first_at'] ?? '' ),
			'last_label'  => self::when( $r['last_at'] ?? '' ),
		);
	}

	public static function summary( WP_REST_Request $r ) {
		$days = min( 365, max( 1, absint( $r->get_param( 'days' ) ? $r->get_param( 'days' ) : 30 ) ) );
		list( $from, $to ) = FLXCM_Stats::range_days( $days );
		$sum = FLXCM_Stats::summary( $from, $to );
		$series = array();
		foreach ( (array) $sum['series'] as $day => $pair ) {
			$pair      = (array) $pair;
			$series[]  = array(
				'label'   => (string) $day,
				'revenue' => round( (float) ( $pair[0] ?? 0 ), 2 ),
				'orders'  => (int) ( $pair[1] ?? 0 ),
			);
		}
		$awaiting = self::receipt_query( 1, 1 );
		return array(
			'from'          => $sum['from'],
			'to'            => $sum['to'],
			'revenue'       => round( (float) $sum['revenue'], 2 ),
			'revenue_label' => flxcm_money( $sum['revenue'] ),
			'orders'        => (int) $sum['orders'],
			'aov_label'     => flxcm_money( $sum['aov'] ),
			'customers'     => (int) $sum['customers'],
			'new_customers' => (int) $sum['new_customers'],
			'awaiting'      => (int) $awaiting['total'],
			'series'        => $series,
		);
	}

	public static function customers( WP_REST_Request $r ) {
		$per = (int) $r['per_page'];
		$q   = FLXCM_Customers::query(
			array(
				's'        => sanitize_text_field( (string) $r->get_param( 's' ) ),
				'segment'  => sanitize_key( (string) $r->get_param( 'segment' ) ),
				'orderby'  => sanitize_key( (string) $r->get_param( 'orderby' ) ),
				'order'    => sanitize_key( (string) $r->get_param( 'order' ) ),
				'paged'    => (int) $r['page'],
				'per_page' => $per,
			)
		);
		return array(
			'items' => array_map( array( __CLASS__, 'customer_out' ), $q['rows'] ),
			'total' => (int) $q['total'],
			'pages' => (int) $q['pages'],
		);
	}

	public static function customer( WP_REST_Request $r ) {
		$row = FLXCM_Customers::get( (int) $r['id'] );
		if ( ! $row ) {
			return new WP_Error( 'flxcm_missing', __( 'Customer not found.', 'flx-commerce' ), array( 'status' => 404 ) );
		}
		return self::customer_out( $row );
	}

	private static function receipt_query( $page, $per ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array( 'orders' => array(), 'total' => 0, 'pages' => 1 );
		}
		$q = wc_get_orders(
			array(
				'type'           => 'shop_order',
				'status'         => array( 'on-hold', 'pending' ),
				'payment_method' => FLXCM_Bank::ID,
				'limit'          => $per,
				'paged'          => $page,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'paginate'       => true,
			)
		);
		$orders = is_object( $q ) ? (array) $q->orders : (array) $q;
		$total  = is_object( $q ) ? (int) $q->total : count( $orders );
		$pages  = is_object( $q ) ? (int) $q->max_num_pages : 1;
		return array( 'orders' => $orders, 'total' => $total, 'pages' => max( 1, $pages ) );
	}

	public static function receipt_out( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return null;
		}
		$files = FLXCM_Receipts::of( $order );
		$list  = array();
		foreach ( $files as $i => $file ) {
			$list[] = array(
				'name' => (string) ( $file['name'] ?? '' ),
				'url'  => FLXCM_Receipts::url( $order, $i, true ),
			);
		}
		return array(
			'id'           => $order->get_id(),
			'number'       => $order->get_order_number(),
			'name'         => trim( $order->get_formatted_billing_full_name() ),
			'email'        => (string) $order->get_billing_email(),
			'phone'        => (string) $order->get_billing_phone(),
			'total_label'  => flxcm_money( $order->get_total(), $order->get_currency() ),
			'reference'    => FLXCM_Bank::reference( $order ),
			'status'       => $order->get_status(),
			'status_label' => flxcm_status_label( $order->get_status() ),
			'receipts'     => count( $list ),
			'files'        => $list,
			'date_label'   => $order->get_date_created() ? flxcm_date( $order->get_date_created()->getTimestamp(), 'datetime' ) : '',
		);
	}

	public static function receipts( WP_REST_Request $r ) {
		$q     = self::receipt_query( (int) $r['page'], (int) $r['per_page'] );
		$items = array();
		foreach ( $q['orders'] as $order ) {
			$row = self::receipt_out( $order );
			if ( $row ) {
				$items[] = $row;
			}
		}
		return array( 'items' => $items, 'total' => $q['total'], 'pages' => $q['pages'] );
	}

	public static function receipt( WP_REST_Request $r ) {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $r['id'] ) : null;
		if ( ! $order || FLXCM_Bank::ID !== $order->get_payment_method() ) {
			return new WP_Error( 'flxcm_missing', __( 'Order not found.', 'flx-commerce' ), array( 'status' => 404 ) );
		}
		return self::receipt_out( $order );
	}

	public static function approve( WP_REST_Request $r ) {
		$order = wc_get_order( (int) $r['id'] );
		if ( ! $order ) {
			return new WP_Error( 'flxcm_missing', __( 'Order not found.', 'flx-commerce' ), array( 'status' => 404 ) );
		}
		if ( ! FLXCM_Bank::mark_paid( $order ) ) {
			return new WP_Error( 'flxcm_state', __( 'This order is not waiting for approval.', 'flx-commerce' ), array( 'status' => 409 ) );
		}
		$order = wc_get_order( $order->get_id() );
		return array( 'ok' => true, 'order' => self::receipt_out( $order ) );
	}
}
