<?php
/**
 * Flux Donate: "Awaiting approval" screen of the Flux App mobile app (staff approve bank transfers
 * from the phone). Contract: docs/fluxapp/MODUL-API.md › Bağış onayı.
 *
 *   GET  /wp-json/flxb/v1/app/pending?page=&per_page=        gifts waiting for approval, oldest first
 *   POST /wp-json/flxb/v1/app/donations/<id>/approve          { ok, status_label, certificates }
 *   POST /wp-json/flxb/v1/app/donations/<id>/reject {reason, notify?}  { ok, status_label, sent }
 *
 * Same rules as the card on the donation detail (FLXB_Approval): what counts as pending, who may act
 * (edit_shop_orders for WooCommerce gifts, the manage capability otherwise) and what happens.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_App_Review {

	const NS  = 'flxb/v1';
	const MAX = 300;

	public static function init() {
		add_filter( 'flxapp_modules', array( __CLASS__, 'module' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function module( $m ) {
		$m['flxb-review'] = array(
			'title'   => __( 'Awaiting approval', 'flx-donate' ),
			'cap'     => (string) apply_filters( 'flxb_app_review_capability', 'edit_shop_orders' ),
			'menu'    => '',
			'api'     => rest_url( self::NS . '/app' ),
			'icon'    => 'heart',
			'version' => FLXB_VERSION,
			'order'   => 5,
		);
		return $m;
	}

	/** The approval rules live with the admin card; REST requests do not load the admin. */
	private static function load() {
		if ( ! class_exists( 'FLXB_Approval' ) ) {
			require_once FLXB_DIR . 'includes/admin/class-flxb-approval.php';
		}
	}

	public static function routes() {
		$auth = function () {
			if ( ! is_user_logged_in() ) {
				return new WP_Error( 'rest_not_logged_in', __( '\\nPlease sign in.\\n', 'flx-donate' ), array( 'status' => 401 ) );
			}
			return current_user_can( 'edit_shop_orders' ) || flxb_can_manage() ? true : new WP_Error( 'rest_forbidden', __( '\\nSorry, you are not allowed to access this page.\\n', 'flx-donate' ), array( 'status' => 403 ) );
		};
		register_rest_route(
			self::NS,
			'/app/pending',
			array(
				'methods'             => 'GET',
				'permission_callback' => $auth,
				'callback'            => array( __CLASS__, 'rest_pending' ),
				'args'                => array(
					'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
					'per_page' => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 50 ),
				),
			)
		);
		register_rest_route( self::NS, '/app/donations/(?P<id>\d+)/approve', array( 'methods' => 'POST', 'permission_callback' => $auth, 'callback' => array( __CLASS__, 'rest_approve' ) ) );
		register_rest_route(
			self::NS,
			'/app/donations/(?P<id>\d+)/reject',
			array(
				'methods'             => 'POST',
				'permission_callback' => $auth,
				'callback'            => array( __CLASS__, 'rest_reject' ),
				'args'                => array(
					'reason' => array( 'type' => 'string', 'required' => true ),
					'notify' => array( 'type' => 'boolean', 'default' => true ),
				),
			)
		);
	}

	/** Gifts waiting for approval that the current user may act on, oldest first (one order = one gift). */
	public static function pending_gifts() {
		global $wpdb;
		self::load();
		$t    = flxb_table( 'donations' );
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE status = 'pending' ORDER BY created_at ASC, id ASC LIMIT %d", self::MAX ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out  = array();
		foreach ( $rows as $r ) {
			$k = (int) $r['wc_order_id'] ? 'o' . (int) $r['wc_order_id'] : 'd' . (int) $r['id'];
			if ( isset( $out[ $k ] ) ) {
				$out[ $k ]['rows'][] = $r;
				continue;
			}
			if ( ! FLXB_Approval::can( $r ) ) {
				continue;
			}
			$p = FLXB_Approval::pending( $r );
			if ( $p ) {
				$out[ $k ] = array( 'rows' => array( $r ), 'order' => $p['order'], 'receipts' => $p['receipts'] );
			}
		}
		return array_values( $out );
	}

	public static function rest_pending( WP_REST_Request $r ) {
		$all   = self::pending_gifts();
		$per   = (int) $r['per_page'];
		$page  = (int) $r['page'];
		$items = array_map( array( __CLASS__, 'out' ), array_slice( $all, ( $page - 1 ) * $per, $per ) );
		return rest_ensure_response( array( 'items' => $items, 'total' => count( $all ), 'page' => $page, 'pages' => (int) ceil( count( $all ) / $per ) ) );
	}

	/** "received 2 days ago" since the gift came in. */
	private static function waiting( $utc ) {
		$ts = strtotime( $utc . ' UTC' );
		/* translators: %s: relative time, e.g. "2 days ago" */
		return $ts ? sprintf( __( '\\nreceived%s\\n', 'flx-donate' ), flx_time_ago( $ts ) ) : '';
	}

	/** One gift in the app's shape. */
	public static function out( array $g ) {
		$rows   = $g['rows'];
		$first  = $rows[0];
		$order  = $g['order'];
		$amount = $order ? (float) $order->get_total() : (float) array_sum( array_map( 'floatval', wp_list_pluck( $rows, 'amount' ) ) );
		$cur    = $order ? $order->get_currency() : (string) $first['currency'];
		$donor  = (int) $first['donor_id'] ? FLXB_Donors::get( (int) $first['donor_id'] ) : null;
		$ref    = (int) $first['ref_user_id'];
		$lines  = array();
		foreach ( $rows as $row ) {
			$t       = (int) $row['type_id'] ? FLXB_Types::get( (int) $row['type_id'] ) : null;
			$lines[] = array(
				'title'        => '' !== (string) $row['option_label'] ? (string) $row['option_label'] : ( $t ? (string) $t['title'] : __( 'Donation', 'flx-donate' ) ),
				'quantity'     => max( 1, (int) $row['quantity'] ),
				'amount_label' => flxb_money( $row['amount'], $row['currency'] ),
			);
		}
		$receipt = null;
		$rc      = $g['receipts'] ? $g['receipts'][ count( $g['receipts'] ) - 1 ] : null; // the newest one
		if ( $rc ) {
			$url = (string) $rc['url'];
			/* A receipt uploaded on the site: a signed address instead of the admin nonce (the app has no browser session). */
			if ( $order && class_exists( 'FLXB_WC_Receipts' ) && false !== strpos( $url, 'flxb_dekont=' ) ) {
				parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $q );
				$url = FLXB_WC_Receipts::signed_url( $order, (int) ( $q['i'] ?? 0 ) );
			}
			$receipt = array(
				'url'      => $url,
				'type'     => 'pdf' === $rc['kind'] ? 'pdf' : 'image',
				'name'     => (string) $rc['name'],
				'at_label' => (string) $rc['meta'],
				'warn'     => (string) $rc['warn'],
				'count'    => count( $g['receipts'] ),
			);
		}
		$out = array(
			'id'            => (int) $first['id'],
			'order_id'      => (int) $first['wc_order_id'],
			'title'         => implode( ', ', array_unique( wp_list_pluck( $lines, 'title' ) ) ),
			'amount'        => round( $amount, 2 ),
			'amount_label'  => flxb_money( $amount, $cur ),
			'at'            => gmdate( 'c', strtotime( $first['created_at'] . ' UTC' ) ),
			'date_label'    => flxb_date( $first['created_at'] ),
			'waiting_label' => self::waiting( $first['created_at'] ),
			'donor'         => array(
				'name'  => $donor ? (string) $donor['name'] : '',
				'phone' => $donor && '' !== (string) $donor['phone'] ? flxb_format_phone( $donor['phone'] ) : '',
				'email' => $donor ? (string) $donor['email'] : '',
			),
			'ref'           => $ref ? array( 'name' => FLXB_Refs::name( $ref ) ) : null,
			'lines'         => $lines,
			'receipt'       => $receipt,
			'note'          => $order ? (string) $order->get_customer_note() : '',
			'web_url'       => FLXB_Admin_Url::page( 'flxb-donations', array( 'donation' => (int) $first['id'] ) ),
		);
		return (array) apply_filters( 'flxb_app_review_item', $out, $g );
	}

	/** The gift behind an id, checked: exists, the user may act, still pending. */
	private static function gift( $id ) {
		self::load();
		$d = FLXB_Donations::get( (int) $id );
		if ( ! $d ) {
			return new WP_Error( 'flxb_not_found', __( 'Donation not found.', 'flx-donate' ), array( 'status' => 404 ) );
		}
		if ( ! FLXB_Approval::can( $d ) ) {
			return new WP_Error( 'rest_forbidden', __( '\\nSorry, you are not allowed to access this page.\\n', 'flx-donate' ), array( 'status' => 403 ) );
		}
		if ( ! FLXB_Approval::pending( $d ) ) {
			return new WP_Error( 'flxb_state', __( '\\nThis donation is no longer awaiting approval.\\n', 'flx-donate' ), array( 'status' => 409 ) );
		}
		return $d;
	}

	private static function status_label( $id ) {
		$d = FLXB_Donations::get( (int) $id );
		return $d ? flxb_status_label( $d['status'] )[1] : '';
	}

	public static function rest_approve( WP_REST_Request $r ) {
		$d = self::gift( $r['id'] );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		$order = FLXB_Approval::do_approve( $d );
		$certs = $order && class_exists( 'FLXB_WC_Certificates' ) ? count( FLXB_WC_Certificates::of( wc_get_order( $order->get_id() ) ) ) : 0;
		return rest_ensure_response( array( 'ok' => true, 'status_label' => self::status_label( $d['id'] ), 'certificates' => $certs ) );
	}

	public static function rest_reject( WP_REST_Request $r ) {
		$reason = trim( sanitize_textarea_field( (string) $r['reason'] ) );
		if ( '' === $reason ) {
			return new WP_Error( 'flxb_reason', __( '\\nPlease write a reason.\\n', 'flx-donate' ), array( 'status' => 400 ) );
		}
		$d = self::gift( $r['id'] );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		$sent = FLXB_Approval::do_reject( $d, $reason, (bool) $r['notify'] );
		return rest_ensure_response( array( 'ok' => true, 'status_label' => self::status_label( $d['id'] ), 'sent' => $sent ) );
	}
}
