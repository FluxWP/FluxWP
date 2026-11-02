<?php
/**
 * Flux Donate: donor screens of the Flux App mobile / desktop app ("My donations", "My certificates").
 *
 * The app logs donors in with a one-time code (Flux App, flxapp/v1/otp) and opens the modules
 * registered through `flxapp_modules` on its own screens. Data comes from these endpoints, always for
 * the signed-in user only:
 *
 *   GET /wp-json/flxb/v1/me                    summary (name, totals, counts)
 *   GET /wp-json/flxb/v1/me/donations          gifts, newest first (?page, ?per_page ≤ 50)
 *   GET /wp-json/flxb/v1/me/donations/<id>     one gift with its lines and certificates
 *   GET /wp-json/flxb/v1/me/certificates       certificates of the user's gifts
 *
 * Which gifts are the user's: the gifts of their own WooCommerce orders, gifts of donor cards linked
 * to the account (user meta flxb_donor_ids: written when Flux App opens an account for an order or
 * the user signs in with a code sent to their phone) and the donor card with the account's e-mail
 * address. A phone number typed into the billing details alone is not trusted. Filter: flxb_app_donor_ids.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_App {

	const NS  = 'flxb/v1';
	const MAX = 500;

	public static function init() {
		add_filter( 'flxapp_modules', array( __CLASS__, 'modules' ) );
		add_action( 'flxapp_otp_customer_created', array( __CLASS__, 'customer_created' ), 10, 2 );
		add_action( 'flxapp_otp_login', array( __CLASS__, 'otp_login' ), 10, 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/** Native app screens; staff keep using the donation pages of the admin menu. */
	public static function modules( $m ) {
		$m['flxb-donations']    = array(
			'title'   => __( 'My donations', 'flx-donate' ),
			'cap'     => 'read',
			'menu'    => '',
			'api'     => rest_url( self::NS . '/me/donations' ),
			'icon'    => 'heart',
			'version' => FLXB_VERSION,
			'order'   => 10,
		);
		$m['flxb-certificates'] = array(
			'title'   => __( 'My certificates', 'flx-donate' ),
			'cap'     => 'read',
			'menu'    => '',
			'api'     => rest_url( self::NS . '/me/certificates' ),
			'icon'    => 'award',
			'version' => FLXB_VERSION,
			'order'   => 20,
		);
		return $m;
	}

	/** Flux App opened an account for someone who had donated: link their donor card. */
	public static function customer_created( $user_id, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$ids = FLXB_Donations::ids_of_order( $order->get_id() );
		$dn  = array();
		foreach ( $ids as $id ) {
			$d = FLXB_Donations::get( $id );
			if ( $d && (int) $d['donor_id'] ) {
				$dn[] = (int) $d['donor_id'];
			}
		}
		if ( $dn ) {
			self::link( (int) $user_id, $dn );
		}
	}

	/**
	 * Signed in with a code sent to their phone: the phone is proven. When the account carries a single
	 * phone number, the donor card with that number is linked (with several we cannot tell which one got the code).
	 */
	public static function otp_login( $user_id, $type ) {
		if ( 'phone' !== $type ) {
			return;
		}
		$phones = array();
		foreach ( array( 'billing_phone', 'flxapp_phone', 'flxc_phone', 'flx_phone', 'phone' ) as $k ) {
			$p = flxb_normalize_phone( (string) get_user_meta( $user_id, $k, true ) );
			if ( '' !== $p ) {
				$phones[ $p ] = true;
			}
		}
		if ( 1 !== count( $phones ) ) {
			return;
		}
		$d = FLXB_Donors::find( (string) key( $phones ), '' );
		if ( $d ) {
			self::link( (int) $user_id, array( (int) $d['id'] ) );
		}
	}

	/** Add donor cards to an account. */
	public static function link( $user_id, array $donor_ids ) {
		$have = array_map( 'intval', (array) get_user_meta( $user_id, 'flxb_donor_ids', true ) );
		update_user_meta( $user_id, 'flxb_donor_ids', array_values( array_unique( array_filter( array_merge( $have, array_map( 'intval', $donor_ids ) ) ) ) ) );
	}

	/** Donor card ids of an account. */
	public static function donor_ids( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}
		$ids = array_map( 'intval', (array) get_user_meta( $user_id, 'flxb_donor_ids', true ) );
		if ( is_email( $user->user_email ) ) {
			$d = FLXB_Donors::find( '', $user->user_email );
			if ( $d ) {
				$ids[] = (int) $d['id'];
			}
		}
		return array_values( array_unique( array_filter( array_map( 'intval', (array) apply_filters( 'flxb_app_donor_ids', $ids, $user ) ) ) ) );
	}

	/** WooCommerce order ids of an account. */
	private static function order_ids( $user_id ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		return array_map( 'intval', (array) wc_get_orders( array( 'customer_id' => (int) $user_id, 'limit' => self::MAX, 'return' => 'ids', 'status' => array_keys( wc_get_order_statuses() ) ) ) );
	}

	/** Donation rows of an account, newest first (trash left out). */
	public static function rows( $user_id ) {
		global $wpdb;
		$donors = self::donor_ids( $user_id );
		$orders = self::order_ids( $user_id );
		if ( ! $donors && ! $orders ) {
			return array();
		}
		$t    = flxb_table( 'donations' );
		$or   = array();
		$args = array();
		if ( $donors ) {
			$or[] = 'donor_id IN (' . implode( ',', array_fill( 0, count( $donors ), '%d' ) ) . ')';
			$args = array_merge( $args, $donors );
		}
		if ( $orders ) {
			$or[] = 'wc_order_id IN (' . implode( ',', array_fill( 0, count( $orders ), '%d' ) ) . ')';
			$args = array_merge( $args, $orders );
		}
		$args[] = self::MAX;
		$sql    = "SELECT * FROM {$t} WHERE status <> 'trash' AND (" . implode( ' OR ', $or ) . ') ORDER BY created_at DESC, id DESC LIMIT %d';
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/** Rows grouped into gifts: the lines of one order are one gift. */
	public static function gifts( $user_id ) {
		$out = array();
		foreach ( self::rows( $user_id ) as $r ) {
			$k = (int) $r['wc_order_id'] ? 'o' . (int) $r['wc_order_id'] : 'd' . (int) $r['id'];
			if ( ! isset( $out[ $k ] ) ) {
				$out[ $k ] = array();
			}
			$out[ $k ][] = $r;
		}
		return array_values( $out );
	}

	public static function routes() {
		$auth = function () {
			return is_user_logged_in() ? true : new WP_Error( 'rest_not_logged_in', __( 'Please sign in.', 'flx-donate' ), array( 'status' => 401 ) );
		};
		register_rest_route( self::NS, '/me', array( 'methods' => 'GET', 'permission_callback' => $auth, 'callback' => array( __CLASS__, 'rest_me' ) ) );
		register_rest_route(
			self::NS,
			'/me/donations',
			array(
				'methods'             => 'GET',
				'permission_callback' => $auth,
				'callback'            => array( __CLASS__, 'rest_donations' ),
				'args'                => array(
					'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
					'per_page' => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 50 ),
				),
			)
		);
		register_rest_route( self::NS, '/me/donations/(?P<id>\d+)', array( 'methods' => 'GET', 'permission_callback' => $auth, 'callback' => array( __CLASS__, 'rest_donation' ) ) );
		register_rest_route( self::NS, '/me/certificates', array( 'methods' => 'GET', 'permission_callback' => $auth, 'callback' => array( __CLASS__, 'rest_certificates' ) ) );
	}

	public static function rest_me() {
		$uid   = get_current_user_id();
		$user  = wp_get_current_user();
		$gifts = self::gifts( $uid );
		$paid  = 0.0;
		$n     = 0;
		$wait  = 0;
		foreach ( $gifts as $g ) {
			$s = self::gift_status( $g );
			if ( in_array( $s, array( 'completed', 'partially_refunded' ), true ) ) {
				++$n;
				$paid += self::gift_amount( $g );
			} elseif ( 'pending' === $s ) {
				++$wait;
			}
		}
		return rest_ensure_response(
			array(
				'name'         => $user->display_name,
				'donations'    => count( $gifts ),
				'paid'         => $n,
				'pending'      => $wait,
				'total'        => round( $paid, 2 ),
				'total_label'  => flxb_money( $paid ),
				'certificates' => count( self::certificates_of( $gifts ) ),
				'donate_url'   => class_exists( 'FLXB_WC' ) && method_exists( 'FLXB_WC', 'page_url' ) ? FLXB_WC::page_url() : home_url( '/' ),
			)
		);
	}

	public static function rest_donations( WP_REST_Request $r ) {
		$gifts = self::gifts( get_current_user_id() );
		$per   = (int) $r['per_page'];
		$page  = (int) $r['page'];
		$items = array_map( array( __CLASS__, 'gift_out' ), array_slice( $gifts, ( $page - 1 ) * $per, $per ) );
		$res   = rest_ensure_response( array( 'items' => $items, 'total' => count( $gifts ), 'page' => $page, 'pages' => (int) ceil( count( $gifts ) / $per ) ) );
		$res->header( 'X-WP-Total', (string) count( $gifts ) );
		return $res;
	}

	public static function rest_donation( WP_REST_Request $r ) {
		$id = (int) $r['id'];
		foreach ( self::gifts( get_current_user_id() ) as $g ) {
			foreach ( $g as $row ) {
				if ( (int) $row['id'] === $id ) {
					$out                 = self::gift_out( $g );
					$out['certificates'] = array_map( array( __CLASS__, 'cert_out' ), self::certificates_of( array( $g ) ) );
					return rest_ensure_response( $out );
				}
			}
		}
		return new WP_Error( 'flxb_not_found', __( 'Donation not found.', 'flx-donate' ), array( 'status' => 404 ) );
	}

	public static function rest_certificates() {
		return rest_ensure_response( array( 'items' => array_map( array( __CLASS__, 'cert_out' ), self::certificates_of( self::gifts( get_current_user_id() ) ) ) ) );
	}

	/** Status of a gift: its lines share it; a mixed order shows the first "open" one. */
	private static function gift_status( array $g ) {
		$all = array_unique( wp_list_pluck( $g, 'status' ) );
		foreach ( array( 'pending', 'completed', 'partially_refunded', 'refunded', 'failed', 'canceled' ) as $s ) {
			if ( in_array( $s, $all, true ) ) {
				return $s;
			}
		}
		return (string) reset( $all );
	}

	private static function gift_amount( array $g ) {
		return (float) array_sum( array_map( 'floatval', wp_list_pluck( $g, 'amount' ) ) );
	}

	private static function line_title( array $r ) {
		if ( '' !== (string) $r['option_label'] ) {
			return (string) $r['option_label'];
		}
		$t = (int) $r['type_id'] ? FLXB_Types::get( (int) $r['type_id'] ) : null;
		return $t ? (string) $t['title'] : __( 'Donation', 'flx-donate' );
	}

	/** One gift in the app's shape. */
	public static function gift_out( array $g ) {
		$first  = $g[0];
		$status = self::gift_status( $g );
		$amount = self::gift_amount( $g );
		$oid    = (int) $first['wc_order_id'];
		$order  = $oid && function_exists( 'wc_get_order' ) ? wc_get_order( $oid ) : null;
		$lines  = array();
		foreach ( $g as $r ) {
			$lines[] = array(
				'id'           => (int) $r['id'],
				'title'        => self::line_title( $r ),
				'quantity'     => max( 1, (int) $r['quantity'] ),
				'amount'       => round( (float) $r['amount'], 2 ),
				'amount_label' => flxb_money( $r['amount'], $r['currency'] ),
			);
		}
		$out = array(
			'id'           => (int) $first['id'],
			'order_id'     => $oid,
			'date'         => gmdate( 'c', strtotime( $first['created_at'] . ' UTC' ) ),
			'date_label'   => flxb_date( $first['created_at'] ),
			'title'        => 1 === count( $lines ) ? $lines[0]['title'] : implode( ', ', array_unique( wp_list_pluck( $lines, 'title' ) ) ),
			'amount'       => round( $amount, 2 ),
			'currency'     => (string) $first['currency'],
			'amount_label' => flxb_money( $amount, $first['currency'] ),
			'type'         => (string) $first['type'],
			'type_label'   => flxb_type_label( $first['type'] ),
			'status'       => $status,
			'status_label' => flxb_status_label( $status )[1],
			'status_tone'  => flxb_status_label( $status )[0],
			'lines'        => $lines,
			'certificates' => count( self::codes_of( $g ) ),
			'web_url'      => $order ? $order->get_view_order_url() : '',
		);
		/* A bank transfer waiting for payment: the app opens the web page to upload the receipt. */
		if ( 'pending' === $status && $order && class_exists( 'FLXB_WC_Account' ) ) {
			$out['pay_url'] = wc_get_account_endpoint_url( FLXB_WC_Account::ENDPOINT );
		}
		return (array) apply_filters( 'flxb_app_gift', $out, $g );
	}

	/** Certificate codes on a gift's lines. */
	private static function codes_of( array $g ) {
		$codes = array();
		foreach ( $g as $r ) {
			if ( '' !== (string) $r['certificate_code'] ) {
				$codes[] = (string) $r['certificate_code'];
			}
			foreach ( (array) ( FLXB_Donations::meta( $r )['certificates'] ?? array() ) as $c ) {
				$codes[] = (string) $c;
			}
		}
		return array_values( array_unique( array_filter( $codes ) ) );
	}

	/** Certificates of these gifts from Flux Certificates (revoked ones left out). */
	private static function certificates_of( array $gifts ) {
		if ( ! function_exists( 'flxs_get_certificate_by_code' ) ) {
			return array();
		}
		$out = array();
		foreach ( $gifts as $g ) {
			foreach ( self::codes_of( $g ) as $code ) {
				$c = flxs_get_certificate_by_code( $code );
				if ( $c && 'revoked' !== (string) ( $c['status'] ?? '' ) ) {
					$c['_gift'] = (int) $g[0]['id'];
					$out[ (int) $c['id'] ] = $c;
				}
			}
		}
		krsort( $out );
		return array_values( $out );
	}

	/** One certificate in the app's shape. */
	public static function cert_out( array $c ) {
		$name = (string) ( $c['data']['values']['ad_soyad'] ?? $c['recipient_name'] ?? $c['name'] ?? '' );
		return array(
			'id'          => (int) $c['id'],
			'code'        => (string) $c['code'],
			'number'      => (string) ( $c['number'] ?? '' ),
			'name'        => $name,
			'title'       => (string) ( $c['title'] ?? '' ),
			'date'        => ! empty( $c['created_at'] ) ? gmdate( 'c', strtotime( $c['created_at'] . ' UTC' ) ) : '',
			'date_label'  => ! empty( $c['created_at'] ) ? flxb_date( $c['created_at'], 'd.m.Y' ) : '',
			'donation_id' => (int) ( $c['_gift'] ?? 0 ),
			'url'         => function_exists( 'flxs_certificate_url' ) ? flxs_certificate_url( $c ) : '',
			'pdf_url'     => function_exists( 'flxs_certificate_pdf_url' ) ? flxs_certificate_pdf_url( $c ) : '',
			'image_url'   => function_exists( 'flxs_certificate_file_url' ) ? flxs_certificate_file_url( $c, 'image' ) : '',
		);
	}
}
