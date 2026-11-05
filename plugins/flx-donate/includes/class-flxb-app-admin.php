<?php
/**
 * Flux Donate yönetim listeleri (uygulama): bağış, bağışçı ve abonelik.
 *
 *   GET /wp-json/flxb/v1/admin/summary
 *   GET /wp-json/flxb/v1/admin/donations
 *   GET /wp-json/flxb/v1/admin/donations/<id>
 *   GET /wp-json/flxb/v1/admin/donors
 *   GET /wp-json/flxb/v1/admin/donors/<id>
 *   GET /wp-json/flxb/v1/admin/subscriptions
 *
 * Okuma `flx_bagis_view` (ya da yönetici). Onay ekranı `flxb/v1/app/*` uçlarında kalır.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_App_Admin {

	const NS = 'flxb/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'nocache' ), 10, 3 );
	}

	public static function can_view() {
		return flxb_can_view();
	}

	public static function routes() {
		$view = array( __CLASS__, 'can_view' );
		$args = array(
			'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
			'per_page' => array( 'type' => 'integer', 'default' => 25, 'minimum' => 1, 'maximum' => 100 ),
		);
		register_rest_route( self::NS, '/admin/summary', array( 'methods' => 'GET', 'permission_callback' => $view, 'callback' => array( __CLASS__, 'summary' ) ) );
		register_rest_route( self::NS, '/admin/donations', array( 'methods' => 'GET', 'permission_callback' => $view, 'callback' => array( __CLASS__, 'donations' ), 'args' => $args ) );
		register_rest_route( self::NS, '/admin/donations/(?P<id>\d+)', array( 'methods' => 'GET', 'permission_callback' => $view, 'callback' => array( __CLASS__, 'donation' ) ) );
		register_rest_route( self::NS, '/admin/donors', array( 'methods' => 'GET', 'permission_callback' => $view, 'callback' => array( __CLASS__, 'donors' ), 'args' => $args ) );
		register_rest_route( self::NS, '/admin/donors/(?P<id>\d+)', array( 'methods' => 'GET', 'permission_callback' => $view, 'callback' => array( __CLASS__, 'donor' ) ) );
		register_rest_route( self::NS, '/admin/subscriptions', array( 'methods' => 'GET', 'permission_callback' => $view, 'callback' => array( __CLASS__, 'subscriptions' ), 'args' => $args ) );
	}

	public static function nocache( $result, $server, $request ) {
		unset( $server );
		if ( $result instanceof WP_REST_Response && 0 === strpos( (string) $request->get_route(), '/' . self::NS . '/admin/' ) ) {
			$result->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
			$result->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
			$result->header( 'CDN-Cache-Control', 'no-store' );
			do_action( 'litespeed_control_set_nocache', 'flxb-admin' );
		}
		return $result;
	}

	private static function page_of( $total, $per ) {
		$per = max( 1, (int) $per );
		return max( 1, (int) ceil( (int) $total / $per ) );
	}

	private static function tone( $status ) {
		$pair = flxb_status_label( $status );
		return array( $pair[0], $pair[1] );
	}

	/** Bir bağış satırı (gruplu listedeki baş satır). */
	public static function donation_out( array $r ) {
		$amount = isset( $r['group_amount'] ) ? (float) $r['group_amount'] : (float) $r['amount'];
		list( $tone, $label ) = self::tone( $r['status'] );
		return array(
			'id'           => (int) $r['id'],
			'donor_id'     => (int) $r['donor_id'],
			'donor_name'   => (string) ( $r['donor_name'] ?? '' ),
			'email'        => (string) ( $r['donor_email'] ?? '' ),
			'phone'        => (string) ( $r['donor_phone'] ?? '' ),
			'amount'       => round( $amount, 2 ),
			'currency'     => (string) $r['currency'],
			'amount_label' => flxb_money( $amount, $r['currency'] ),
			'type'         => (string) $r['type'],
			'type_label'   => flxb_type_label( $r['type'] ),
			'status'       => (string) $r['status'],
			'status_label' => $label,
			'status_tone'  => 'pending' === $tone ? 'pending' : $tone,
			'title'        => (string) ( $r['option_label'] ? $r['option_label'] : flxb_type_label( $r['type'] ) ),
			'lines'        => isset( $r['group_lines'] ) ? (int) $r['group_lines'] : 1,
			'date'         => mysql_to_rfc3339( $r['created_at'] ),
			'date_label'   => flxb_date( $r['created_at'] ),
		);
	}

	public static function donor_out( array $r ) {
		return array(
			'id'             => (int) $r['id'],
			'name'           => (string) $r['name'],
			'email'          => (string) $r['email'],
			'phone'          => (string) $r['phone'],
			'donation_count' => (int) $r['donation_count'],
			'total'          => round( (float) $r['total_amount'], 2 ),
			'total_label'    => flxb_money( $r['total_amount'] ),
			'active_subs'    => (int) ( $r['active_subs'] ?? 0 ),
			'city'           => (string) ( $r['city'] ?? '' ),
			'last_label'     => ! empty( $r['last_donation_at'] ) ? flxb_date( $r['last_donation_at'] ) : '',
			'first_label'    => ! empty( $r['first_donation_at'] ) ? flxb_date( $r['first_donation_at'] ) : '',
		);
	}

	public static function subscription_out( array $r ) {
		list( $tone, $label ) = self::tone( $r['status'] );
		return array(
			'id'           => (int) $r['id'],
			'donor_id'     => (int) $r['donor_id'],
			'donor_name'   => (string) ( $r['donor_name'] ?? '' ),
			'email'        => (string) ( $r['donor_email'] ?? '' ),
			'amount'       => round( (float) $r['amount'], 2 ),
			'currency'     => (string) $r['currency'],
			'amount_label' => flxb_money( $r['amount'], $r['currency'] ),
			'interval'     => (string) $r['interval_unit'],
			'interval_label' => flxb_interval_label( $r['interval_unit'] ),
			'status'       => (string) $r['status'],
			'status_label' => $label,
			'status_tone'  => 'pending' === $tone ? 'pending' : $tone,
			'next_label'   => ! empty( $r['next_charge_at'] ) ? flxb_date( $r['next_charge_at'] ) : '',
		);
	}

	public static function summary() {
		$subs = FLXB_Subscriptions::totals();
		$q    = FLXB_Donations::query( array( 'per_page' => 1, 'page' => 1, 'status' => 'pending', 'group' => 1 ) );
		return array(
			'pending'     => (int) $q['total'],
			'sum_label'   => flxb_money( $q['sum'] ),
			'mrr'         => $subs['mrr'],
			'mrr_label'   => flxb_money( $subs['mrr'] ),
			'active'      => (int) $subs['active'],
			'past_due'    => (int) $subs['past_due'],
			'canceled'    => (int) $subs['canceled'],
		);
	}

	public static function donations( WP_REST_Request $r ) {
		$per = (int) $r['per_page'];
		$q   = FLXB_Donations::query(
			array(
				'per_page' => $per,
				'page'     => (int) $r['page'],
				'search'   => sanitize_text_field( (string) $r->get_param( 's' ) ),
				'status'   => sanitize_key( (string) $r->get_param( 'status' ) ),
				'orderby'  => sanitize_key( (string) $r->get_param( 'orderby' ) ),
				'order'    => sanitize_key( (string) $r->get_param( 'order' ) ),
				'group'    => 1,
			)
		);
		return array(
			'items' => array_map( array( __CLASS__, 'donation_out' ), $q['items'] ),
			'total' => (int) $q['total'],
			'pages' => self::page_of( $q['total'], $per ),
			'sum'   => round( (float) $q['sum'], 2 ),
		);
	}

	public static function donation( WP_REST_Request $r ) {
		$row = FLXB_Donations::get( (int) $r['id'] );
		if ( ! $row ) {
			return new WP_Error( 'flxb_not_found', __( 'Bağış bulunamadı.', 'flx-donate' ), array( 'status' => 404 ) );
		}
		$donor = (int) $row['donor_id'] ? FLXB_Donors::get( (int) $row['donor_id'] ) : null;
		$row['donor_name']  = $donor ? (string) $donor['name'] : '';
		$row['donor_email'] = $donor ? (string) $donor['email'] : '';
		$row['donor_phone'] = $donor ? (string) $donor['phone'] : '';
		$out          = self::donation_out( $row );
		$out['lines'] = array();
		foreach ( FLXB_Donations::siblings( $row ) as $line ) {
			$out['lines'][] = array(
				'id'           => (int) $line['id'],
				'title'        => (string) ( $line['option_label'] ? $line['option_label'] : __( 'Bağış', 'flx-donate' ) ),
				'quantity'     => max( 1, (int) $line['quantity'] ),
				'amount_label' => flxb_money( $line['amount'], $line['currency'] ),
				'status_label' => flxb_status_label( $line['status'] )[1],
			);
		}
		$out['note'] = (string) ( $row['note'] ?? '' );
		return $out;
	}

	public static function donors( WP_REST_Request $r ) {
		$per = (int) $r['per_page'];
		$q   = FLXB_Donors::query(
			array(
				'per_page' => $per,
				'page'     => (int) $r['page'],
				'search'   => sanitize_text_field( (string) $r->get_param( 's' ) ),
				'orderby'  => sanitize_key( (string) $r->get_param( 'orderby' ) ),
				'order'    => sanitize_key( (string) $r->get_param( 'order' ) ),
				'kind'     => sanitize_key( (string) $r->get_param( 'kind' ) ),
			)
		);
		return array(
			'items' => array_map( array( __CLASS__, 'donor_out' ), $q['items'] ),
			'total' => (int) $q['total'],
			'pages' => self::page_of( $q['total'], $per ),
		);
	}

	public static function donor( WP_REST_Request $r ) {
		$row = FLXB_Donors::get( (int) $r['id'] );
		if ( ! $row ) {
			return new WP_Error( 'flxb_not_found', __( 'Bağışçı bulunamadı.', 'flx-donate' ), array( 'status' => 404 ) );
		}
		$row['active_subs'] = 0;
		$out                = self::donor_out( $row );
		$gifts              = FLXB_Donations::query( array( 'donor_id' => (int) $row['id'], 'per_page' => 20, 'page' => 1, 'group' => 1 ) );
		$out['donations']   = array_map( array( __CLASS__, 'donation_out' ), $gifts['items'] );
		return $out;
	}

	public static function subscriptions( WP_REST_Request $r ) {
		$per = (int) $r['per_page'];
		$q   = FLXB_Subscriptions::query(
			array(
				'per_page' => $per,
				'page'     => (int) $r['page'],
				'search'   => sanitize_text_field( (string) $r->get_param( 's' ) ),
				'status'   => sanitize_key( (string) $r->get_param( 'status' ) ),
				'donor_id' => absint( $r->get_param( 'donor_id' ) ),
			)
		);
		return array(
			'items' => array_map( array( __CLASS__, 'subscription_out' ), $q['items'] ),
			'total' => (int) $q['total'],
			'pages' => self::page_of( $q['total'], $per ),
		);
	}
}
