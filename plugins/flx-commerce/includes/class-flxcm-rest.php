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
			flx_rest_nocache( $result );
			$result->header( 'CDN-Cache-Control', 'no-store' );
			do_action( 'litespeed_control_set_nocache', 'flxcm-app' );
		}
		return $result;
	}

	public static function customer_out( array $r ) {
		return FLXCM_Customer_Service::rest_row( $r );
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
		return FLXCM_Customer_Service::rest_list( $r );
	}

	public static function customer( WP_REST_Request $r ) {
		return FLXCM_Customer_Service::rest_one( (int) $r['id'] );
	}

	private static function receipt_query( $page, $per ) {
		return FLXCM_Receipt_Service::query( (int) $page, (int) $per );
	}

	public static function receipt_out( $order ) {
		return FLXCM_Receipt_Service::rest_row( $order );
	}

	public static function receipts( WP_REST_Request $r ) {
		return FLXCM_Receipt_Service::rest_list( (int) $r['page'], (int) $r['per_page'] );
	}

	public static function receipt( WP_REST_Request $r ) {
		return FLXCM_Receipt_Service::rest_one( (int) $r['id'] );
	}

	public static function approve( WP_REST_Request $r ) {
		return FLXCM_Receipt_Service::approve( (int) $r['id'] );
	}
}
