<?php
/**
 * Flux Donate 2.13.0: EsnekPOS (Hoppa) official WooCommerce module, made safe on our checkout.
 *
 * The module (gateway `esnekpospos`, class WC_Gateway_Esnekpospos) keeps its settings screen and its
 * place in the gateway list. Flux Donate runs the payment itself:
 *
 *  - Start: the order-pay page of an EsnekPOS order sends the donor to EsnekPOS's common payment page
 *    (CommonPaymentDealer) with a fresh reference "<order>-<time>" and the order's own lines, so it
 *    works without a cart too (call-center payment links). The module needs a cart and fails without one.
 *  - Return: EsnekPOS posts the donor back to our wc-api URL (order id + order key). The payment counts
 *    only when the server-to-server ProcessQuery says SUCCESS / 0 for that reference and at least the
 *    order total (the posted fields are never trusted). The module's own return check sends an empty
 *    query and can be fooled by a forged POST; it is not used. Old return URLs are handled the same way.
 *  - Block checkout: the module has no blocks integration; FLXB_WC_Gateways adds it (redirect method).
 *  - It is the first adapter of FLXB_WC_Gateways (`flxb_gateway_adapters`): what it can do is listed on
 *    Donations › Settings › WooCommerce, its logo is offered for the footer payment logos.
 *  - The module's `init` hook (its return check on every request) is removed.
 *
 * Settings stay in WooCommerce › Settings › Payments › EsnekPOS (merchant, merchant key). Merchant
 * TEST1234 uses EsnekPOS's test service.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

class FLXB_WC_Esnekpos {

	const ID     = 'esnekpospos';
	const MODULE = 'WC_Gateway_Esnekpospos';
	const API    = 'flxb_esnekpos';
	const REF    = '_flxb_esnek_ref';
	const ERR    = 'flxb_esn_error';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'unhook_module' ), 9 );
		add_action( 'init', array( __CLASS__, 'unhook_module' ), 10 );
		add_action( 'woocommerce_api_' . strtolower( self::MODULE ), array( __CLASS__, 'callback' ), 1 );
		add_action( 'woocommerce_api_' . self::API, array( __CLASS__, 'callback' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_start' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'error_notice' ) );
		add_action( 'wc_payment_gateways_initialized', array( __CLASS__, 'unhook_module' ) );
		add_filter( 'woocommerce_settings_api_form_fields_' . self::ID, array( __CLASS__, 'form_fields' ) );
		add_filter( 'flxb_gateway_adapters', array( __CLASS__, 'adapter' ) );
	}

	/** Is the EsnekPOS module active? */
	public static function present() {
		return class_exists( self::MODULE );
	}

	public static function settings() {
		return (array) get_option( 'woocommerce_' . self::ID . '_settings', array() );
	}

	public static function enabled() {
		$s = self::settings();
		return self::present() && 'yes' === ( $s['enabled'] ?? 'no' );
	}

	/** @return array{merchant:string,key:string,test:bool} */
	public static function credentials() {
		$s        = self::settings();
		$merchant = trim( (string) ( $s['esnekpos_username'] ?? '' ) );
		return array(
			'merchant' => $merchant,
			'key'      => trim( (string) ( $s['esnekpos_password'] ?? '' ) ),
			'test'     => 'TEST1234' === $merchant,
		);
	}

	public static function base_url() {
		$c = self::credentials();
		return (string) apply_filters( 'flxb_esnekpos_base_url', $c['test'] ? 'https://posservicetest.esnekpos.com' : 'https://posservice.esnekpos.com', $c['test'] );
	}

	/** Remove the module's own handlers: return check on every `init`, cart-only receipt page. */
	public static function unhook_module() {
		foreach ( array( 'init', 'woocommerce_receipt_' . self::ID, 'woocommerce_api_' . strtolower( self::MODULE ) ) as $hook ) {
			global $wp_filter;
			if ( empty( $wp_filter[ $hook ] ) || ! is_object( $wp_filter[ $hook ] ) ) {
				continue;
			}
			foreach ( (array) $wp_filter[ $hook ]->callbacks as $prio => $cbs ) {
				foreach ( (array) $cbs as $cb ) {
					$f = $cb['function'] ?? null;
					if ( is_array( $f ) && isset( $f[0] ) && is_object( $f[0] ) && is_a( $f[0], self::MODULE ) ) {
						remove_action( $hook, $f, $prio );
					}
				}
			}
		}
	}

	/** Settings screen: the merchant key is a password field; a note on how the payment runs. */
	public static function form_fields( $fields ) {
		if ( isset( $fields['esnekpos_password'] ) ) {
			$fields['esnekpos_password']['type'] = 'password';
		}
		if ( isset( $fields['esnekpos_tdmode'] ) ) {
			$fields['esnekpos_tdmode']['description'] = __( 'Flux Donate always uses EsnekPOS\'s secure payment page; the bank asks for 3D Secure there when needed.', 'flx-donate' );
		}
		$note = array(
			'flxb_note' => array(
				'title'       => __( 'Flux Donate', 'flx-donate' ),
				'type'        => 'title',
				'description' => __( 'Flux Donate runs this payment method: classic and block checkout, payment links without a cart, and every payment is confirmed with EsnekPOS from the server before the order is marked paid. For a test, enter merchant TEST1234 and the test merchant key from EsnekPOS\'s developer page.', 'flx-donate' ),
			),
		);
		return $note + (array) $fields;
	}

	/* ---------- Payment ---------- */

	/** Order-pay page of an EsnekPOS order (after checkout): straight to EsnekPOS. */
	public static function maybe_start() {
		global $wp;
		self::unhook_module();
		if ( empty( $wp->query_vars['order-pay'] ) || isset( $_GET['pay_for_order'] ) || ! self::present() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$order = wc_get_order( absint( $wp->query_vars['order-pay'] ) );
		$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $order || self::ID !== $order->get_payment_method() || '' === $key || ! hash_equals( $order->get_order_key(), $key ) ) {
			return;
		}
		if ( ! $order->needs_payment() ) {
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}
		$url = self::start( $order );
		if ( is_wp_error( $url ) ) {
			wp_safe_redirect( add_query_arg( self::ERR, rawurlencode( $url->get_error_message() ), $order->get_checkout_payment_url() ) );
			exit;
		}
		nocache_headers();
		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- EsnekPOS payment page
		exit;
	}

	public static function error_notice() {
		if ( empty( $_GET[ self::ERR ] ) || ! function_exists( 'wc_add_notice' ) || ! function_exists( 'is_checkout' ) || ! is_checkout() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		wc_add_notice( mb_substr( sanitize_text_field( wp_unslash( $_GET[ self::ERR ] ) ), 0, 300 ), 'error' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/** Amount as EsnekPOS takes it: "150", "150.50". */
	public static function amount( $x ) {
		$x = round( (float) $x, 2 );
		$s = abs( $x - round( $x ) ) < 0.005 ? (string) (int) round( $x ) : number_format( $x, 2, '.', '' );
		return (string) apply_filters( 'flxb_esnekpos_amount', $s, $x );
	}

	/** Amount from an EsnekPOS answer: 150, "150", "150.50", "150,50", "1.250,50". */
	public static function parse_amount( $v ) {
		if ( is_int( $v ) || is_float( $v ) ) {
			return (float) $v;
		}
		$s = preg_replace( '/[^0-9.,]/', '', (string) $v );
		if ( false !== strpos( $s, ',' ) ) {
			$s = str_replace( array( '.', ',' ), array( '', '.' ), $s );
		}
		return (float) $s;
	}

	/** Product lines of the order; one line with the total when they do not add up. */
	public static function products( WC_Order $order ) {
		$total = round( (float) $order->get_total(), 2 );
		$out   = array();
		$sum   = 0.0;
		foreach ( FLXB_WC_Iyzico::basket_lines( $order ) as $l ) {
			$sum  += $l['amount'];
			$out[] = array(
				'PRODUCT_ID'          => (string) $l['key'],
				'PRODUCT_NAME'        => mb_substr( (string) $l['label'], 0, 100 ),
				'PRODUCT_CATEGORY'    => __( 'Donation', 'flx-donate' ),
				'PRODUCT_DESCRIPTION' => mb_substr( (string) $l['label'], 0, 100 ),
				'PRODUCT_AMOUNT'      => self::amount( $l['amount'] ),
			);
		}
		if ( ! $out || abs( $sum - $total ) > 0.009 ) {
			/* translators: %d: order number */
			$name = sprintf( __( 'Donation #%d', 'flx-donate' ), $order->get_id() );
			$out  = array(
				array(
					'PRODUCT_ID'          => 'o' . $order->get_id(),
					'PRODUCT_NAME'        => $name,
					'PRODUCT_CATEGORY'    => __( 'Donation', 'flx-donate' ),
					'PRODUCT_DESCRIPTION' => $name,
					'PRODUCT_AMOUNT'      => self::amount( $total ),
				),
			);
		}
		return $out;
	}

	public static function return_url( WC_Order $order ) {
		return add_query_arg(
			array(
				'order' => $order->get_id(),
				'key'   => $order->get_order_key(),
			),
			WC()->api_request_url( self::API )
		);
	}

	/** JSON POST to EsnekPOS. @return array|WP_Error */
	private static function post( $path, array $body ) {
		$r = wp_remote_post(
			self::base_url() . $path,
			array(
				'timeout' => 30,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$j = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		if ( ! is_array( $j ) ) {
			/* translators: %d: HTTP status */
			return new WP_Error( 'flxb_esn', sprintf( __( 'EsnekPOS did not answer (HTTP %d).', 'flx-donate' ), (int) wp_remote_retrieve_response_code( $r ) ) );
		}
		return $j;
	}

	private static function message( array $r ) {
		foreach ( array( 'tr' === self::locale() ? 'RETURN_MESSAGE_TR' : 'RETURN_MESSAGE', 'RETURN_MESSAGE', 'RETURN_MESSAGE_TR' ) as $k ) {
			if ( ! empty( $r[ $k ] ) && is_scalar( $r[ $k ] ) ) {
				return sanitize_text_field( (string) $r[ $k ] );
			}
		}
		return '';
	}

	private static function locale() {
		return 0 === strpos( (string) determine_locale(), 'tr' ) ? 'tr' : 'en';
	}

	private static function ok( array $r ) {
		return 'SUCCESS' === strtoupper( (string) ( $r['STATUS'] ?? '' ) ) && '0' === trim( (string) ( $r['RETURN_CODE'] ?? '' ) );
	}

	/**
	 * Open EsnekPOS's payment page for an order.
	 *
	 * @return string|WP_Error Payment page URL.
	 */
	public static function start( WC_Order $order ) {
		$c = self::credentials();
		if ( '' === $c['merchant'] || '' === $c['key'] ) {
			return new WP_Error( 'flxb_esn', __( 'Card payments are not configured on this site. Please choose another payment method.', 'flx-donate' ) );
		}
		$total = (float) $order->get_total();
		if ( $total <= 0 ) {
			return new WP_Error( 'flxb_esn', __( 'The amount must be greater than zero.', 'flx-donate' ) );
		}
		FLXB_WC_Cart::fill_address( $order ); // EsnekPOS wants a city and an address; our checkout asks none
		$ref   = substr( $order->get_id() . '-' . base_convert( (string) time(), 10, 36 ), 0, 24 );
		$first = trim( (string) $order->get_billing_first_name() );
		$last  = trim( (string) $order->get_billing_last_name() );
		if ( '' === $last && false !== strpos( $first, ' ' ) ) {
			$last  = trim( substr( $first, strrpos( $first, ' ' ) ) );
			$first = trim( substr( $first, 0, strrpos( $first, ' ' ) ) );
		}
		$city = trim( (string) $order->get_billing_city() );
		$body = array(
			'Config'   => array(
				'MERCHANT'         => $c['merchant'],
				'MERCHANT_KEY'     => $c['key'],
				'BACK_URL'         => self::return_url( $order ),
				'PRICES_CURRENCY'  => strtoupper( $order->get_currency() ),
				'ORDER_REF_NUMBER' => $ref,
				'ORDER_AMOUNT'     => self::amount( $total ),
				'LOCALE'           => self::locale(),
			),
			'Customer' => array(
				'FIRST_NAME' => '' !== $first ? $first : __( 'Donor', 'flx-donate' ),
				'LAST_NAME'  => '' !== $last ? $last : '-',
				'MAIL'       => (string) $order->get_billing_email(),
				'PHONE'      => (string) $order->get_billing_phone(),
				'CITY'       => $city,
				'STATE'      => '' !== trim( (string) $order->get_billing_state() ) ? (string) $order->get_billing_state() : $city,
				'ADDRESS'    => trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() ),
			),
			'Product'  => self::products( $order ),
		);
		$body = (array) apply_filters( 'flxb_esnekpos_request', $body, $order );
		$r    = self::post( '/api/pay/CommonPaymentDealer', $body );
		unset( $body );
		if ( is_wp_error( $r ) ) {
			/* translators: %s: error */
			$order->add_order_note( sprintf( __( 'EsnekPOS payment could not start: %s', 'flx-donate' ), $r->get_error_message() ) );
			return new WP_Error( 'flxb_esn', __( 'The payment could not be started. Please try again.', 'flx-donate' ) );
		}
		$url = (string) ( $r['URL_3DS'] ?? '' );
		if ( ! self::ok( $r ) || ! preg_match( '#^' . ( 0 === strpos( self::base_url(), 'http://' ) ? 'https?' : 'https' ) . '://#', $url ) ) { // http only against a local test service
			$err = self::message( $r );
			/* translators: 1: EsnekPOS message, 2: test or live */
			$order->add_order_note( sprintf( __( 'EsnekPOS payment could not start: %1$s (%2$s).', 'flx-donate' ), '' !== $err ? $err : '-', $c['test'] ? __( 'test mode', 'flx-donate' ) : __( 'live', 'flx-donate' ) ) );
			return new WP_Error( 'flxb_esn', '' !== $err ? 'EsnekPOS: ' . $err : __( 'The payment could not be started. Please try again.', 'flx-donate' ) );
		}
		$order->update_meta_data( self::REF, $ref );
		$order->update_meta_data( '_flxb_esnek_test', $c['test'] ? 1 : 0 );
		$order->save();
		return $url;
	}

	/** ProcessQuery for a reference. @return array|WP_Error */
	public static function query( $ref ) {
		$c = self::credentials();
		return self::post(
			'/api/services/ProcessQuery',
			array(
				'MERCHANT'         => $c['merchant'],
				'MERCHANT_KEY'     => $c['key'],
				'ORDER_REF_NUMBER' => (string) $ref,
			)
		);
	}

	/** Does a ProcessQuery answer show a successful payment of at least the order total for the reference? */
	public static function verified( array $q, $ref, WC_Order $order ) {
		if ( ! self::ok( $q ) ) {
			return false;
		}
		$qref = (string) ( $q['ORDER_REF_NUMBER'] ?? ( $q['ORDER_REF_NO'] ?? '' ) );
		if ( '' !== $qref && $qref !== (string) $ref ) {
			return false;
		}
		if ( self::parse_amount( $q['AMOUNT'] ?? 0 ) + 0.009 < round( (float) $order->get_total(), 2 ) ) {
			return false;
		}
		if ( ! empty( $q['TRANSACTIONS'] ) && is_array( $q['TRANSACTIONS'] ) ) {
			foreach ( $q['TRANSACTIONS'] as $t ) {
				if ( is_array( $t ) && 3 === (int) ( $t['STATUS_ID'] ?? 0 ) ) {
					return true;
				}
			}
			return false;
		}
		return true;
	}

	/** EsnekPOS posts the donor back here (also the module's old return URL). */
	public static function callback() {
		self::unhook_module();
		// phpcs:disable WordPress.Security.NonceVerification -- a bank return; checked against EsnekPOS below
		$posted_ref = isset( $_POST['ORDER_REF_NUMBER'] ) ? sanitize_text_field( wp_unslash( $_POST['ORDER_REF_NUMBER'] ) ) : '';
		$posted_ok  = isset( $_POST['STATUS'] ) && 'SUCCESS' === strtoupper( sanitize_text_field( wp_unslash( $_POST['STATUS'] ) ) );
		$posted_msg = '';
		foreach ( array( 'RETURN_MESSAGE_TR', 'RETURN_MESSAGE' ) as $k ) {
			if ( '' === $posted_msg && ! empty( $_POST[ $k ] ) ) {
				$posted_msg = mb_substr( sanitize_text_field( wp_unslash( $_POST[ $k ] ) ), 0, 200 );
			}
		}
		$order = null;
		$keyed = false;
		if ( isset( $_GET['order'], $_GET['key'] ) ) {
			$o = wc_get_order( absint( $_GET['order'] ) );
			if ( $o && hash_equals( $o->get_order_key(), sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) ) {
				$order = $o;
				$keyed = true;
			}
		} elseif ( preg_match( '/^(\d+)/', $posted_ref, $m ) ) {
			$order = wc_get_order( (int) $m[1] ); // module's old return URL: no key, the query below decides
		}
		// phpcs:enable
		if ( ! $order || self::ID !== $order->get_payment_method() ) {
			wp_safe_redirect( function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/' ) );
			exit;
		}
		$ref = (string) $order->get_meta( self::REF );
		if ( '' === $ref ) {
			$ref = $posted_ref; // order started by the module itself (reference = order id)
		}
		$err = '';
		if ( ! $order->is_paid() && '' !== $ref ) {
			$err = self::settle( $order, $ref, $posted_ok || ! $keyed, $posted_msg );
		}
		$order = wc_get_order( $order->get_id() );
		if ( ! $keyed ) {
			/* Old return URL: no order key in it, so none is handed out (the donor gets the e-mail). */
			wp_safe_redirect( function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/' ) );
		} elseif ( $order->is_paid() ) {
			wp_safe_redirect( $order->get_checkout_order_received_url() );
		} else {
			wp_safe_redirect( add_query_arg( self::ERR, rawurlencode( '' !== $err ? $err : __( 'The payment could not be completed. Please try again or use another card.', 'flx-donate' ) ), $order->get_checkout_payment_url() ) );
		}
		exit;
	}

	/**
	 * Check the payment with EsnekPOS and settle the order (once).
	 *
	 * @return string Error for the donor ('' when paid).
	 */
	private static function settle( WC_Order $order, $ref, $posted_ok, $posted_msg ) {
		$lock = 'flxb_wc_esn_lock_' . $order->get_id();
		if ( ! add_option( $lock, time(), '', false ) && time() - (int) get_option( $lock ) < 60 ) {
			return __( 'The payment is being checked. Please wait a moment and refresh the page.', 'flx-donate' );
		}
		update_option( $lock, time(), false );
		try {
			$order = wc_get_order( $order->get_id() );
			if ( $order->is_paid() ) {
				return '';
			}
			$q = self::query( $ref );
			if ( is_wp_error( $q ) ) {
				/* translators: %s: error */
				$order->add_order_note( sprintf( __( 'EsnekPOS payment could not be checked: %s', 'flx-donate' ), $q->get_error_message() ) );
				return __( 'The payment could not be verified yet. If money was taken from your card, please contact us before trying again.', 'flx-donate' );
			}
			if ( self::verified( $q, $ref, $order ) ) {
				$refno  = sanitize_text_field( (string) ( $q['REFNO'] ?? '' ) );
				$inst   = max( 1, (int) ( $q['INSTALLMENT'] ?? 1 ) );
				$amount = self::parse_amount( $q['AMOUNT'] ?? 0 );
				$order->update_meta_data(
					'_flxb_esnekpos',
					array(
						'ref'         => $ref,
						'refno'       => $refno,
						'installment' => $inst,
						'amount'      => $amount,
						'txn'         => sanitize_text_field( (string) ( $q['SUCCESS_TRANSACTION_ID'] ?? '' ) ),
					)
				);
				$order->save_meta_data();
				/* translators: 1: EsnekPOS reference, 2: installments, 3: amount charged */
				$order->add_order_note( sprintf( __( 'EsnekPOS payment received: reference %1$s, %2$s installment(s), %3$s charged.', 'flx-donate' ), '' !== $refno ? $refno : $ref, $inst, FLXB_WC::money_text( $amount, $order->get_currency() ) ) );
				$order->payment_complete( '' !== $refno ? $refno : $ref );
				do_action( 'flxb_wc_esnekpos_paid', $order, $q );
				return '';
			}
			$msg = self::message( $q );
			if ( self::ok( $q ) ) {
				/* translators: %s: EsnekPOS reference */
				$order->update_status( 'on-hold', sprintf( __( 'EsnekPOS reported a payment (%s) that does not match this order (amount or reference). Check it in the EsnekPOS panel before doing anything.', 'flx-donate' ), $ref ) );
				return __( 'The payment could not be verified yet. If money was taken from your card, please contact us before trying again.', 'flx-donate' );
			}
			$why = '' !== $posted_msg ? $posted_msg : ( '' !== $msg ? $msg : '-' );
			if ( $posted_ok ) { // also an old return URL without the order key: only a note, the status stays
				/* translators: %s: EsnekPOS message */
				$order->add_order_note( sprintf( __( 'EsnekPOS return said success but EsnekPOS did not confirm the payment: %s', 'flx-donate' ), '' !== $msg ? $msg : '-' ) );
			} else {
				/* translators: %s: EsnekPOS message */
				$order->update_status( 'failed', sprintf( __( 'EsnekPOS: the payment was not completed (%s).', 'flx-donate' ), $why ) );
			}
			return '-' !== $why ? 'EsnekPOS: ' . $why : '';
		} finally {
			delete_option( $lock );
		}
	}

	/* ---------- Adapter (FLXB_WC_Gateways) ---------- */

	/** The module's logo (img/logo.png next to its main file), or ''. */
	public static function logo_url() {
		if ( ! self::present() ) {
			return '';
		}
		$file = dirname( ( new ReflectionClass( self::MODULE ) )->getFileName() ) . '/img/logo.png';
		return file_exists( $file ) ? plugin_dir_url( $file ) . 'logo.png' : '';
	}

	public static function adapter( $a ) {
		if ( ! self::present() ) {
			return $a;
		}
		$c              = self::credentials();
		$a[ self::ID ] = array(
			'label' => 'EsnekPOS',
			'logo'  => self::logo_url(),
			'note'  => $c['test'] ? __( 'Test mode (merchant TEST1234).', 'flx-donate' ) : '',
			'caps'  => array(
				'no_cart'      => array( 'flux', __( 'Flux Donate sends the order lines, no cart needed.', 'flx-donate' ) ),
				'verified'     => array( 'flux', __( 'Flux Donate asks EsnekPOS (ProcessQuery) before the order is marked paid.', 'flx-donate' ) ),
				'installments' => array( 'page', __( 'On EsnekPOS\'s payment page, as set in the EsnekPOS panel.', 'flx-donate' ) ),
				'tokenization' => array( 'no', '' ),
				'refunds'      => array( 'no', __( 'Refund in the EsnekPOS panel, then mark the order refunded.', 'flx-donate' ) ),
			),
		);
		return $a;
	}
}
