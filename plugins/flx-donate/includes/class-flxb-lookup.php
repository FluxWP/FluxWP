<?php
/**
 * Flux Donate: "My donations" lookup page for visitors without an account.
 *
 * Shortcode [flx_bagislarim] (alias [flx_my_donations]). The visitor types an e-mail address or a phone
 * number, receives a 6-digit one-time code there (e-mail through Flux Mail / wp_mail, phone through
 * WhatsApp (Flux Chat or Flux Mail) or SMS (Flux Mail)), enters it and sees their donations and
 * certificates (view, download PDF). Signed-in visitors see their own gifts at once.
 *
 * Nothing is rendered for a person on the server: the page HTML is the same for everybody (safe for
 * LiteSpeed page cache); data comes from POST endpoints with no-store headers:
 *
 *   POST /wp-json/flxb/v1/lookup/start  { login }        → { ok, message, via }  (same answer whether or not a gift exists)
 *   POST /wp-json/flxb/v1/lookup/verify { login, code }  → { ok, data } and an HttpOnly session cookie (flxb_lk)
 *   POST /wp-json/flxb/v1/lookup/data                    → { ok, data } for the session (or the signed-in user)
 *   POST /wp-json/flxb/v1/lookup/logout
 *
 * Which gifts: donor cards with the proven e-mail / phone and WooCommerce orders with that billing
 * e-mail / phone (and orders of accounts with that e-mail). Settings: option `flxb_lookup`
 * (Donations › Settings › Donation lookup). Filters: `flxb_lookup_send` (own channel),
 * `flxb_lookup_match` (donor and order ids), `flxb_lookup_daily_cap`, `flxb_lookup_data`.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Lookup {

	const NS      = 'flxb/v1';
	const OPTION  = 'flxb_lookup';
	const COOKIE  = 'flxb_lk';
	const TTL     = 600;  // code: 10 minutes
	const TRIES   = 5;    // wrong codes before the code is dropped
	const SESSION = 1800; // signed-in lookup: 30 minutes

	public static function init() {
		add_shortcode( 'flx_bagislarim', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'flx_my_donations', array( __CLASS__, 'shortcode' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'flxb_email_types', array( __CLASS__, 'email_type' ) );
		add_filter( 'flxb_settings_tabs', array( __CLASS__, 'tab' ) );
		add_action( 'flxb_settings_tab_lookup', array( __CLASS__, 'render_tab' ) );
		add_action( 'admin_post_flxb_lookup_save', array( __CLASS__, 'save' ) );
		add_filter( 'flx_system_checks', array( __CLASS__, 'checks' ) );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'no_cache' ), 10, 3 );
	}

	/* ---------- Settings ---------- */

	public static function settings() {
		$s = get_option( self::OPTION, array() );
		$s = wp_parse_args(
			is_array( $s ) ? $s : array(),
			array(
				'on'       => 1,
				'page_id'  => 0,
				'channels' => array( 'email', 'whatsapp', 'sms' ),
			)
		);
		$s['channels'] = array_values( array_intersect( (array) $s['channels'], array( 'email', 'whatsapp', 'sms' ) ) );
		return $s;
	}

	/** Is this channel turned on and able to send? */
	public static function channel_ready( $ch ) {
		if ( ! in_array( $ch, self::settings()['channels'], true ) ) {
			return false;
		}
		if ( 'email' === $ch ) {
			return true;
		}
		if ( 'whatsapp' === $ch && function_exists( 'flxc_send_private_text' ) && function_exists( 'flxc_default_connection' ) && flxc_default_connection() ) {
			return true;
		}
		return function_exists( 'flxi_send_message' ) && function_exists( 'flxi_get_channel_connection' ) && (bool) flxi_get_channel_connection( $ch );
	}

	public static function phone_ready() {
		return self::channel_ready( 'whatsapp' ) || self::channel_ready( 'sms' );
	}

	/** URL of the lookup page ('' when there is none). */
	public static function page_url() {
		$id = (int) self::settings()['page_id'];
		if ( $id && 'publish' === get_post_status( $id ) ) {
			return (string) get_permalink( $id );
		}
		$found = self::find_page();
		return $found ? (string) get_permalink( $found ) : '';
	}

	/** A published page that holds the shortcode. */
	private static function find_page() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND ( post_content LIKE '%[flx\\_bagislarim%' OR post_content LIKE '%[flx\\_my\\_donations%' ) ORDER BY ID ASC LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/* ---------- E-mail template ---------- */

	public static function email_type( $types ) {
		$types['flx-donate/lookup-code'] = array(
			'label'       => __( 'Donation lookup code', 'flx-donate' ),
			'description' => __( 'One-time code a donor enters on the "My donations" page to see their donations and certificates.', 'flx-donate' ),
			'recipient'   => 'user',
			'vars'        => array(
				'kod'    => __( 'Code', 'flx-donate' ),
				'dakika' => __( 'Minutes the code is valid', 'flx-donate' ),
				'link'   => __( 'Lookup page', 'flx-donate' ),
			),
			'sample'      => array( 'kod' => '482913', 'dakika' => '10', 'link' => home_url( '/' ) ),
			'defaults'    => array(
				/* translators: {kod} and {kurum} stay as they are. */
				'subject'     => __( '{kod} is your code to view your donations', 'flx-donate' ),
				'heading'     => __( 'Your verification code', 'flx-donate' ),
				'body'        => '<p>' . __( 'Enter this code on the "My donations" page to see your donations and download your certificates:', 'flx-donate' ) . '</p><p style="font-size:30px;letter-spacing:8px;font-weight:700">{kod}</p><p>' . __( 'The code is valid for {dakika} minutes. If you did not ask for it, you can ignore this e-mail.', 'flx-donate' ) . '</p>',
				'button_text' => '',
				'button_url'  => '',
				'sender'      => 'noreply',
				'enabled'     => 1,
			),
		);
		return $types;
	}

	/* ---------- Matching ---------- */

	/** [ 'email'|'phone', normalized ] or [ '', '' ]. */
	public static function normalize( $login ) {
		$login = trim( (string) $login );
		if ( false !== strpos( $login, '@' ) ) {
			$e = flxb_normalize_email( $login );
			return '' !== $e ? array( 'email', $e ) : array( '', '' );
		}
		$p = flxb_normalize_phone( $login );
		if ( '' === $p && function_exists( 'flx_phone_normalize' ) ) {
			$p = flx_phone_normalize( $login );
		}
		return '' !== $p ? array( 'phone', $p ) : array( '', '' );
	}

	/** Donor card ids and order ids for a proven e-mail or phone. */
	public static function match( $type, $value ) {
		global $wpdb;
		$donors = array();
		$orders = array();
		$col    = 'email' === $type ? 'email' : 'phone';
		$donors = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxb_table( 'donors' ) . " WHERE {$col} = %s LIMIT 50", $value ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		if ( function_exists( 'wc_get_orders' ) ) {
			$st   = array_keys( wc_get_order_statuses() );
			$args = array( 'limit' => FLXB_App::MAX, 'return' => 'ids', 'status' => $st );
			if ( 'email' === $type ) {
				$orders = (array) wc_get_orders( $args + array( 'billing_email' => $value ) );
				$user   = get_user_by( 'email', $value );
				if ( $user ) {
					$orders = array_merge( $orders, (array) wc_get_orders( $args + array( 'customer_id' => (int) $user->ID ) ) );
				}
			} else {
				foreach ( array_unique( array( $value, '0' . substr( $value, 3 ), substr( $value, 3 ) ) ) as $p ) {
					if ( 0 === strpos( $value, '+90' ) || $p === $value ) {
						$orders = array_merge( $orders, (array) wc_get_orders( $args + array( 'billing_phone' => $p ) ) );
					}
				}
			}
		}
		$m = apply_filters(
			'flxb_lookup_match',
			array(
				'donors' => $donors,
				'orders' => array_map( 'intval', $orders ),
			),
			$type,
			$value
		);
		return array(
			'donors' => array_values( array_unique( array_map( 'intval', (array) ( $m['donors'] ?? array() ) ) ) ),
			'orders' => array_values( array_unique( array_map( 'intval', (array) ( $m['orders'] ?? array() ) ) ) ),
		);
	}

	/** Gifts for a proven login. */
	public static function gifts( $type, $value ) {
		$m = self::match( $type, $value );
		return FLXB_App::group( FLXB_App::rows_for( $m['donors'], $m['orders'] ) );
	}

	/* ---------- Rate limits ---------- */

	private static function ip() {
		$ip = flx_client_ip();
		return '' !== $ip ? $ip : '0';
	}

	private static function h( $s ) {
		return substr( hash_hmac( 'sha256', (string) $s, wp_salt( 'nonce' ) ), 0, 32 );
	}

	/** Counts a hit; false when the limit is reached. */
	private static function rate( $key, $max, $window ) {
		return flx_rate_limit( 'flxb_lk|' . $key, (int) $max, (int) $window );
	}

	/** Daily cap per channel (Hostinger: 100 e-mails a day for the whole site). */
	private static function daily_ok( $via ) {
		$cap = (int) apply_filters( 'flxb_lookup_daily_cap', 'email' === $via ? 40 : 200, $via );
		$k   = 'flxb_lk_day_' . $via . '_' . gmdate( 'Ymd' );
		$n   = (int) get_transient( $k );
		if ( $cap > 0 && $n >= $cap ) {
			return false;
		}
		set_transient( $k, $n + 1, DAY_IN_SECONDS );
		return true;
	}

	/* ---------- REST ---------- */

	public static function routes() {
		$open = static function () {
			return (bool) self::settings()['on'];
		};
		register_rest_route( self::NS, '/lookup/start', array( 'methods' => 'POST', 'permission_callback' => $open, 'callback' => array( __CLASS__, 'rest_start' ) ) );
		register_rest_route( self::NS, '/lookup/verify', array( 'methods' => 'POST', 'permission_callback' => $open, 'callback' => array( __CLASS__, 'rest_verify' ) ) );
		register_rest_route( self::NS, '/lookup/data', array( 'methods' => 'POST', 'permission_callback' => $open, 'callback' => array( __CLASS__, 'rest_data' ) ) );
		register_rest_route( self::NS, '/lookup/logout', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'rest_logout' ) ) );
	}

	/** Answers of the lookup endpoints are never cached (LiteSpeed, CDN, browser). */
	public static function no_cache( $res, $server, $req ) {
		if ( $req instanceof WP_REST_Request && 0 === strpos( (string) $req->get_route(), '/' . self::NS . '/lookup' ) && $res instanceof WP_REST_Response ) {
			flx_rest_nocache( $res );
			$res->header( 'CDN-Cache-Control', 'no-store' );
			do_action( 'litespeed_control_set_nocache', 'flx-donate lookup' );
		}
		return $res;
	}

	private static function err( $code, $msg, $status = 400 ) {
		return new WP_Error( $code, $msg, array( 'status' => $status ) );
	}

	public static function rest_start( WP_REST_Request $r ) {
		list( $type, $value ) = self::normalize( (string) $r->get_param( 'login' ) );
		if ( '' === $type ) {
			return self::err( 'flxb_lookup_login', __( 'Enter a valid e-mail address or phone number.', 'flx-donate' ) );
		}
		if ( 'phone' === $type && ! self::phone_ready() ) {
			return self::err( 'flxb_lookup_phone', __( 'Codes cannot be sent by phone at the moment. Please use your e-mail address.', 'flx-donate' ) );
		}
		if ( 'email' === $type && ! self::channel_ready( 'email' ) ) {
			return self::err( 'flxb_lookup_email', __( 'Codes cannot be sent by e-mail at the moment. Please use your phone number.', 'flx-donate' ) );
		}
		if ( ! self::rate( 'ip|' . self::ip(), 8, 10 * MINUTE_IN_SECONDS ) || ! self::rate( 'login|' . $value, 3, 10 * MINUTE_IN_SECONDS ) ) {
			return self::err( 'flxb_lookup_rate', __( 'Too many attempts. Please wait a few minutes and try again.', 'flx-donate' ), 429 );
		}
		$via = 'email' === $type ? 'email' : '';
		$m   = self::match( $type, $value );
		if ( $m['donors'] || $m['orders'] ) {
			$code = (string) wp_rand( 100000, 999999 );
			$sent = self::deliver( $type, $value, $code );
			if ( false === $sent ) {
				return self::err( 'flxb_lookup_send', __( 'The code could not be sent. Please try again later or contact us.', 'flx-donate' ), 503 );
			}
			$via = $sent;
			set_transient( 'flxb_lkc_' . self::h( $value ), array( 'h' => wp_hash_password( $code ), 'tries' => 0 ), self::TTL );
		} elseif ( 'phone' === $type ) {
			$via = self::channel_ready( 'whatsapp' ) ? 'whatsapp' : 'sms';
		}
		/* The same answer whether or not there is a donation: nobody learns who donated. */
		return rest_ensure_response(
			array(
				'ok'      => true,
				'via'     => $via,
				'ttl'     => self::TTL,
				'message' => self::sent_message( $via ),
			)
		);
	}

	private static function sent_message( $via ) {
		if ( 'whatsapp' === $via ) {
			return __( 'If there is a donation with this number, we have sent a 6-digit code to it on WhatsApp.', 'flx-donate' );
		}
		if ( 'sms' === $via ) {
			return __( 'If there is a donation with this number, we have sent a 6-digit code to it by SMS.', 'flx-donate' );
		}
		return __( 'If there is a donation with this address, we have sent a 6-digit code to it. Check your spam folder too.', 'flx-donate' );
	}

	/** Sends the code. @return string|false channel used */
	private static function deliver( $type, $value, $code ) {
		$site = wp_specialchars_decode( flxb_brand()['name'], ENT_QUOTES );
		/* translators: 1: code, 2: organisation name */
		$text = sprintf( __( '%1$s is your code to view your donations at %2$s. Valid for 10 minutes. Do not share it with anyone.', 'flx-donate' ), $code, $site );
		$via  = apply_filters( 'flxb_lookup_send', null, $type, $value, $code, $text );
		if ( $via ) {
			return (string) $via;
		}
		if ( 'email' === $type ) {
			if ( ! self::daily_ok( 'email' ) ) {
				return false;
			}
			$ok = FLXB_Mail::send(
				'flx-donate/lookup-code',
				$value,
				array(
					'kod'    => $code,
					'dakika' => (string) ( self::TTL / 60 ),
					'link'   => self::page_url(),
				)
			);
			return $ok ? 'email' : false;
		}
		foreach ( array( 'whatsapp', 'sms' ) as $ch ) {
			if ( ! self::channel_ready( $ch ) || ! self::daily_ok( $ch ) ) {
				continue;
			}
			if ( 'whatsapp' === $ch && function_exists( 'flxc_send_private_text' ) && function_exists( 'flxc_default_connection' ) && flxc_default_connection() ) {
				// Private send: the code is not stored in the chat panel.
				if ( true === flxc_send_private_text( $value, $text, array( 'source' => 'flx-donate-lookup' ) ) ) {
					return 'whatsapp';
				}
			}
			if ( function_exists( 'flxi_send_message' ) && function_exists( 'flxi_get_channel_connection' ) && flxi_get_channel_connection( $ch ) ) {
				$r = flxi_send_message( $ch, $value, $text, array( 'source' => 'flx-donate-lookup', 'log' => false ) );
				if ( is_array( $r ) && ! empty( $r['ok'] ) ) {
					return $ch;
				}
			}
		}
		return false;
	}

	public static function rest_verify( WP_REST_Request $r ) {
		list( $type, $value ) = self::normalize( (string) $r->get_param( 'login' ) );
		$code                 = preg_replace( '/\D+/', '', (string) $r->get_param( 'code' ) );
		$bad                  = self::err( 'flxb_lookup_code', __( 'The code is wrong or has expired.', 'flx-donate' ) );
		if ( '' === $type || 6 !== strlen( $code ) ) {
			return $bad;
		}
		if ( ! self::rate( 'verify|' . self::ip(), 20, 10 * MINUTE_IN_SECONDS ) ) {
			return self::err( 'flxb_lookup_rate', __( 'Too many attempts. Please wait a few minutes and try again.', 'flx-donate' ), 429 );
		}
		$key = 'flxb_lkc_' . self::h( $value );
		$d   = get_transient( $key );
		if ( ! is_array( $d ) || empty( $d['h'] ) ) {
			return $bad;
		}
		if ( ! wp_check_password( $code, $d['h'] ) ) {
			$d['tries'] = (int) $d['tries'] + 1;
			if ( $d['tries'] >= self::TRIES ) {
				delete_transient( $key );
			} else {
				set_transient( $key, $d, self::TTL );
			}
			return $bad;
		}
		delete_transient( $key );
		$token = wp_generate_password( 40, false, false );
		set_transient( 'flxb_lks_' . self::h( $token ), array( 'type' => $type, 'value' => $value ), self::SESSION );
		self::set_cookie( $token, time() + self::SESSION );
		return rest_ensure_response( array( 'ok' => true, 'data' => self::data_for( $type, $value ) ) );
	}

	private static function set_cookie( $value, $expire ) {
		if ( headers_sent() ) {
			return;
		}
		$path = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie( self::COOKIE, $value, array( 'expires' => $expire, 'path' => $path, 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		} else {
			setcookie( self::COOKIE, $value, $expire, $path . '; samesite=Lax', '', is_ssl(), true );
		}
	}

	/** Session of the lookup cookie: [ type, value ] or null. */
	private static function session() {
		$t = isset( $_COOKIE[ self::COOKIE ] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( 40 !== strlen( $t ) ) {
			return null;
		}
		$s = get_transient( 'flxb_lks_' . self::h( $t ) );
		return is_array( $s ) && ! empty( $s['type'] ) ? $s : null;
	}

	public static function rest_data() {
		if ( is_user_logged_in() ) {
			return rest_ensure_response( array( 'ok' => true, 'data' => self::data_user( get_current_user_id() ) ) );
		}
		$s = self::session();
		if ( ! $s ) {
			return rest_ensure_response( array( 'ok' => false ) );
		}
		return rest_ensure_response( array( 'ok' => true, 'data' => self::data_for( $s['type'], $s['value'] ) ) );
	}

	public static function rest_logout() {
		$t = isset( $_COOKIE[ self::COOKIE ] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( '' !== $t ) {
			delete_transient( 'flxb_lks_' . self::h( $t ) );
		}
		self::set_cookie( '', time() - HOUR_IN_SECONDS );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/* ---------- Output ---------- */

	/** a***@gmail.com, +90 532 *** ** 67 */
	public static function mask( $type, $value ) {
		if ( 'email' === $type ) {
			list( $u, $d ) = array_pad( explode( '@', $value, 2 ), 2, '' );
			return mb_substr( $u, 0, 1 ) . str_repeat( '*', max( 2, min( 6, mb_strlen( $u ) - 1 ) ) ) . '@' . $d;
		}
		$f = flxb_format_phone( $value );
		return substr( $f, 0, 7 ) . preg_replace( '/\d/', '*', substr( $f, 7, -2 ) ) . substr( $f, -2 );
	}

	private static function data_user( $user_id ) {
		$u = get_userdata( $user_id );
		return self::shape( FLXB_App::gifts( $user_id ), $u ? (string) $u->display_name : '', true );
	}

	private static function data_for( $type, $value ) {
		return self::shape( self::gifts( $type, $value ), self::mask( $type, $value ), false );
	}

	/** Gifts and certificates in the page's shape. */
	private static function shape( array $gifts, $who, $account ) {
		$items = array();
		$paid  = 0.0;
		$cur   = '';
		$mixed = false;
		foreach ( $gifts as $g ) {
			$o     = FLXB_App::gift_out( $g );
			$order = $o['order_id'] && function_exists( 'wc_get_order' ) ? wc_get_order( $o['order_id'] ) : null;
			/* Waiting bank transfer: the order-received page holds the IBAN and the receipt upload. */
			if ( 'pending' === $o['status'] && $order ) {
				$o['pay_url'] = $order->get_checkout_order_received_url();
			}
			unset( $o['web_url'] );
			if ( $account && $order ) {
				$o['web_url'] = $order->get_view_order_url();
			}
			$o['certificate_list'] = array_map( array( 'FLXB_App', 'cert_out' ), FLXB_App::certificates_of( array( $g ) ) );
			$items[]               = $o;
			if ( in_array( $o['status'], array( 'completed', 'partially_refunded' ), true ) ) {
				if ( '' === $cur ) {
					$cur = $o['currency'];
				} elseif ( $cur !== $o['currency'] ) {
					$mixed = true;
				}
				$paid += (float) $o['amount'];
			}
		}
		$certs = array();
		foreach ( $items as $o ) {
			foreach ( $o['certificate_list'] as $c ) {
				$certs[] = $c;
			}
		}
		$out = array(
			'who'          => $who,
			'account'      => (bool) $account,
			'count'        => count( $items ),
			'total_label'  => $mixed || '' === $cur ? '' : flxb_money( $paid, $cur ),
			'items'        => $items,
			'certificates' => $certs,
			'donate_url'   => class_exists( 'FLXB_WC' ) && method_exists( 'FLXB_WC', 'page_url' ) ? FLXB_WC::page_url() : home_url( '/' ),
		);
		return (array) apply_filters( 'flxb_lookup_data', $out, $gifts );
	}

	/* ---------- Shortcode ---------- */

	public static function strings() {
		return array(
			'email'      => __( 'E-mail', 'flx-donate' ),
			'phone'      => __( 'Phone', 'flx-donate' ),
			'send'       => __( 'Send code', 'flx-donate' ),
			'sending'    => __( 'Sending…', 'flx-donate' ),
			'verify'     => __( 'Show my donations', 'flx-donate' ),
			'checking'   => __( 'Checking…', 'flx-donate' ),
			'resend'     => __( 'Send again', 'flx-donate' ),
			/* translators: %s: seconds */
			'resend_in'  => __( 'Send again in %s s', 'flx-donate' ),
			'change'     => __( 'Change', 'flx-donate' ),
			'code'       => __( '6-digit code', 'flx-donate' ),
			'error'      => __( 'Something went wrong. Please try again.', 'flx-donate' ),
			'logout'     => __( 'Sign out', 'flx-donate' ),
			/* translators: %s: masked e-mail or phone */
			'verified'   => __( 'Verified: %s', 'flx-donate' ),
			'gifts'      => __( 'My donations', 'flx-donate' ),
			'certs'      => __( 'My certificates', 'flx-donate' ),
			'total'      => __( 'Total donated', 'flx-donate' ),
			'count'      => __( 'Donations', 'flx-donate' ),
			'ncerts'     => __( 'Certificates', 'flx-donate' ),
			'none'       => __( 'We could not find a donation with these details.', 'flx-donate' ),
			'none_hint'  => __( 'Try the other e-mail address or phone number you may have used when donating.', 'flx-donate' ),
			'no_certs'   => __( 'There is no certificate yet. Certificates are issued once the payment is confirmed.', 'flx-donate' ),
			'donate'     => __( 'Donate', 'flx-donate' ),
			'view'       => __( 'View', 'flx-donate' ),
			'pdf'        => __( 'Download PDF', 'flx-donate' ),
			'pay'        => __( 'Payment details', 'flx-donate' ),
			'order'      => __( 'Order details', 'flx-donate' ),
			/* translators: %s: donation number */
			'no'         => __( 'Donation #%s', 'flx-donate' ),
			'cert_for'   => __( 'Certificate', 'flx-donate' ),
			'need_email' => __( 'Enter your e-mail address.', 'flx-donate' ),
			'need_phone' => __( 'Enter your phone number.', 'flx-donate' ),
			'need_code'  => __( 'Enter the 6-digit code.', 'flx-donate' ),
		);
	}

	public static function shortcode( $atts ) {
		$s = self::settings();
		if ( empty( $s['on'] ) ) {
			return current_user_can( 'manage_options' ) ? '<p>' . esc_html__( 'Donation lookup is turned off (Donations › Settings › Donation lookup).', 'flx-donate' ) . '</p>' : '';
		}
		$a = shortcode_atts( array( 'baslik' => '' ), $atts, 'flx_bagislarim' );
		$v = FLXB_VERSION;
		wp_enqueue_style( 'flxb-lookup', FLXB_URL . 'assets/lookup.css', array(), $v );
		wp_add_inline_style( 'flxb-lookup', flxb_brand_css( '.flxb-lk' ) );
		wp_enqueue_script( 'flxb-lookup', FLXB_URL . 'assets/lookup.js', array(), $v, true );
		$email = self::channel_ready( 'email' );
		$phone = self::phone_ready();
		wp_localize_script(
			'flxb-lookup',
			'FLXB_LOOKUP',
			array(
				'api'    => esc_url_raw( rest_url( self::NS . '/lookup/' ) ),
				'nonce'  => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
				'user'   => is_user_logged_in(),
				'i18n'   => self::strings(),
				'resend' => 60,
			)
		);
		$phone_field = $phone && function_exists( 'flx_phone_field' ) ? flx_phone_field( array( 'name' => 'flxb_lk_phone', 'id' => 'flxb-lk-phone', 'required' => false ) ) : '<input type="tel" id="flxb-lk-phone" class="flxb-lk__input" name="flxb_lk_phone" inputmode="tel" autocomplete="tel">';
		$title       = '' !== $a['baslik'] ? (string) $a['baslik'] : __( 'My donations and certificates', 'flx-donate' );
		ob_start();
		?>
		<section class="flxb-lk" data-state="<?php echo is_user_logged_in() ? 'loading' : 'start'; ?>" aria-live="polite">
			<div class="flxb-lk__gate">
				<div class="flxb-lk__card">
					<span class="flxb-lk__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-4.4-9.3-9A5.3 5.3 0 0 1 12 6a5.3 5.3 0 0 1 9.3 6c-2.3 4.6-9.3 9-9.3 9z"/></svg></span>
					<h2 class="flxb-lk__title"><?php echo esc_html( $title ); ?></h2>
					<p class="flxb-lk__lead"><?php esc_html_e( 'Enter the e-mail address or phone number you used when donating. We will send you a one-time code; no account or password is needed.', 'flx-donate' ); ?></p>

					<form class="flxb-lk__step flxb-lk__step--start" novalidate>
						<?php if ( $email && $phone ) : ?>
							<div class="flxb-lk__tabs" role="tablist">
								<button type="button" role="tab" class="flxb-lk__tab is-on" data-mode="email" aria-selected="true"><?php esc_html_e( 'E-mail', 'flx-donate' ); ?></button>
								<button type="button" role="tab" class="flxb-lk__tab" data-mode="phone" aria-selected="false"><?php esc_html_e( 'Phone', 'flx-donate' ); ?></button>
							</div>
						<?php endif; ?>
						<?php if ( $email ) : ?>
							<label class="flxb-lk__field" data-for="email">
								<span class="flxb-lk__label"><?php esc_html_e( 'E-mail address', 'flx-donate' ); ?></span>
								<input type="email" class="flxb-lk__input" name="flxb_lk_email" autocomplete="email" inputmode="email" placeholder="<?php esc_attr_e( 'name@example.com', 'flx-donate' ); ?>">
							</label>
						<?php endif; ?>
						<?php if ( $phone ) : ?>
							<div class="flxb-lk__field" data-for="phone"<?php echo $email ? ' hidden' : ''; ?>>
								<label class="flxb-lk__label" for="flxb-lk-phone"><?php esc_html_e( 'Phone number', 'flx-donate' ); ?></label>
								<?php echo $phone_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped by flx_phone_field() ?>
							</div>
						<?php endif; ?>
						<p class="flxb-lk__msg" role="alert" hidden></p>
						<button type="submit" class="flxb-lk__btn"><?php esc_html_e( 'Send code', 'flx-donate' ); ?></button>
					</form>

					<form class="flxb-lk__step flxb-lk__step--code" novalidate hidden>
						<p class="flxb-lk__sent"></p>
						<label class="flxb-lk__field">
							<span class="flxb-lk__label"><?php esc_html_e( '6-digit code', 'flx-donate' ); ?></span>
							<input type="text" class="flxb-lk__input flxb-lk__code" name="flxb_lk_code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" placeholder="••••••">
						</label>
						<p class="flxb-lk__msg" role="alert" hidden></p>
						<button type="submit" class="flxb-lk__btn"><?php esc_html_e( 'Show my donations', 'flx-donate' ); ?></button>
						<div class="flxb-lk__row">
							<button type="button" class="flxb-lk__link" data-act="change"><?php esc_html_e( 'Change', 'flx-donate' ); ?></button>
							<button type="button" class="flxb-lk__link" data-act="resend" disabled><?php esc_html_e( 'Send again', 'flx-donate' ); ?></button>
						</div>
					</form>
					<p class="flxb-lk__note"><?php esc_html_e( 'Your details are only used to find your donations.', 'flx-donate' ); ?></p>
				</div>
			</div>
			<div class="flxb-lk__loading" aria-hidden="true"><span></span><span></span><span></span></div>
			<div class="flxb-lk__result" hidden></div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/* ---------- Settings tab ---------- */

	public static function tab( $tabs ) {
		$out = array();
		foreach ( $tabs as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'general' === $k ) {
				$out['lookup'] = __( 'Donation lookup', 'flx-donate' );
			}
		}
		if ( ! isset( $out['lookup'] ) ) {
			$out['lookup'] = __( 'Donation lookup', 'flx-donate' );
		}
		return $out;
	}

	public static function render_tab() {
		$s     = self::settings();
		$url   = self::page_url();
		$names = array(
			'email'    => __( 'E-mail', 'flx-donate' ),
			'whatsapp' => 'WhatsApp',
			'sms'      => 'SMS',
		);
		echo '<div class="flxb-grid flxb-grid--2-1"><section class="flxb-card"><form class="flxb-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxb_lookup_save">';
		wp_nonce_field( 'flxb_lookup_save' );
		echo '<div class="flxb-field"><input type="hidden" name="on" value="0"><label class="flxb-switch"><input type="checkbox" name="on" value="1"' . checked( ! empty( $s['on'] ), true, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html__( 'Donors can see their donations with a code', 'flx-donate' ) . '</label><p class="flxb-help">' . esc_html__( 'On the "My donations" page a donor enters their e-mail or phone, gets a one-time code and sees their donations and certificates without an account.', 'flx-donate' ) . '</p></div>';
		echo '<fieldset class="flxb-field"><legend class="flxb-label">' . esc_html__( 'Send the code by', 'flx-donate' ) . '</legend>';
		foreach ( $names as $k => $label ) {
			$ready = in_array( $k, $s['channels'], true ) ? self::channel_ready( $k ) : null;
			echo '<label class="flxb-check"><input type="checkbox" name="channels[]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, $s['channels'], true ), true, false ) . '> ' . esc_html( $label );
			if ( false === $ready ) {
				echo ' <span class="flxb-muted">' . esc_html__( '(no connection yet: set it up in Flux Mail or Flux Chat)', 'flx-donate' ) . '</span>';
			}
			echo '</label>';
		}
		echo '<p class="flxb-help">' . esc_html__( 'Phone numbers get the code on WhatsApp first, then by SMS. E-mail codes are limited to 40 a day to stay under the hosting mail limit.', 'flx-donate' ) . '</p></fieldset>';
		echo '<div class="flxb-field"><label for="flxb-lk-page">' . esc_html__( 'Page', 'flx-donate' ) . '</label>';
		wp_dropdown_pages(
			array(
				'name'              => 'page_id',
				'id'                => 'flxb-lk-page', // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment
				'selected'          => (int) $s['page_id'],
				'show_option_none'  => __( '— Find automatically —', 'flx-donate' ),
				'option_none_value' => '0',
			)
		);
		echo '<p class="flxb-help">' . sprintf(
			/* translators: %s: shortcode */
			esc_html__( 'The page shows the %s shortcode. Leave it on "Find automatically" or create the page with the button below.', 'flx-donate' ),
			'<code>[flx_bagislarim]</code>'
		) . '</p></div>';
		echo '<div class="flxb-formactions"><button type="submit" class="flxb-btn flxb-btn--primary">' . esc_html__( 'Save settings', 'flx-donate' ) . '</button>';
		if ( '' === $url ) {
			echo ' <button type="submit" name="create_page" value="1" class="flxb-btn">' . esc_html__( 'Create the "My donations" page', 'flx-donate' ) . '</button>';
		}
		echo '</div></form></section>';
		echo '<aside class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'The page', 'flx-donate' ) . '</h2></div>';
		if ( '' !== $url ) {
			echo '<p><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( wp_make_link_relative( $url ) ) . '</a></p>';
		} else {
			echo '<p class="flxb-muted">' . esc_html__( 'There is no page yet.', 'flx-donate' ) . '</p>';
		}
		echo '<p>' . esc_html__( 'The code is valid for 10 minutes; after 5 wrong codes a new one is needed. One address can ask for 3 codes in 10 minutes. A verified visitor stays signed in for 30 minutes on this device.', 'flx-donate' ) . '</p>';
		echo '<p>' . esc_html__( 'The answer is the same whether or not there is a donation, so the page does not reveal who has donated.', 'flx-donate' ) . '</p></aside></div>';
	}

	public static function save() {
		if ( ! flxb_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-donate' ), 403 );
		}
		check_admin_referer( 'flxb_lookup_save' );
		$s             = self::settings();
		$s['on']       = empty( $_POST['on'] ) ? 0 : 1;
		$s['channels'] = array_values( array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['channels'] ?? array() ) ), array( 'email', 'whatsapp', 'sms' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$s['page_id']  = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;
		if ( ! empty( $_POST['create_page'] ) && '' === self::page_url() ) {
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => __( 'My donations', 'flx-donate' ),
					'post_name'    => sanitize_title( remove_accents( __( 'My donations', 'flx-donate' ) ) ),
					'post_content' => "<!-- wp:shortcode -->\n[flx_bagislarim]\n<!-- /wp:shortcode -->",
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				$s['page_id'] = (int) $id;
			}
		}
		update_option( self::OPTION, $s, false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'flxb-settings', 'tab' => 'lookup', 'flxb_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ---------- System check ---------- */

	public static function checks( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		$s      = self::settings();
		if ( empty( $s['on'] ) ) {
			return $checks;
		}
		$url  = self::page_url();
		$link = admin_url( 'admin.php?page=flxb-settings&tab=lookup' );
		if ( '' === $url ) {
			$checks[] = array(
				'id'           => 'flxb.lookup_page',
				'source'       => 'Flux Donate',
				'group'        => 'Config',
				'label'        => __( 'Donation lookup page', 'flx-donate' ),
				'status'       => 'info',
				'message'      => __( 'Donors cannot look up their donations yet: no published page has the [flx_bagislarim] shortcode.', 'flx-donate' ),
				'action_url'   => $link,
				'action_label' => __( 'Create the page', 'flx-donate' ),
			);
			return $checks;
		}
		$phone    = self::phone_ready();
		$checks[] = array(
			'id'           => 'flxb.lookup_page',
			'source'       => 'Flux Donate',
			'group'        => 'Config',
			'label'        => __( 'Donation lookup page', 'flx-donate' ),
			'status'       => $phone || ! array_intersect( array( 'whatsapp', 'sms' ), $s['channels'] ) ? 'ok' : 'warning',
			'message'      => $phone
				/* translators: %s: page address */
				? sprintf( __( 'Donors can see their donations on %s with a code sent by e-mail or phone.', 'flx-donate' ), wp_make_link_relative( $url ) )
				/* translators: %s: page address */
				: sprintf( __( 'The page %s works with e-mail only: there is no WhatsApp or SMS connection for phone codes.', 'flx-donate' ), wp_make_link_relative( $url ) ),
			'action_url'   => $link,
			'action_label' => __( 'Settings', 'flx-donate' ),
		);
		return $checks;
	}
}
