<?php
/**
 * Flux Donate 2.0: iyzico for WooCommerce orders.
 *
 *  - Gateway `flxb_iyzico` (classic + block checkout): iyzico Checkout Form for the order total,
 *    conversation/basket id "flxbwc-<order>", callback REST flxb/v1/wc/iyzico/callback.
 *  - The payment is counted only when iyzico says SUCCESS for the same basket, amount and currency
 *    (re-checked with iyzico by token; the callback and the merchant notification are idempotent).
 *  - Monthly lines: the card iyzico stores (cardUserKey + cardToken) is kept sealed on the order
 *    and handed to FLXB_WC_Recurring.
 *  - Which card method a cart sees: monthly lines → only `flxb_iyzico` (card storage); otherwise the
 *    official iyzico plugin (gateway `iyzico`) when it is enabled, else `flxb_iyzico`
 *    (setting card_gateway: auto | flxb).
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

class FLXB_WC_Iyzico {

	const ID       = 'flxb_iyzico';
	const OFFICIAL = 'iyzico';
	const ROUTE    = '/wc/iyzico/callback';

	public static function init() {
		add_action( 'woocommerce_init', array( __CLASS__, 'load_gateway' ) );
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'register' ) );
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( __CLASS__, 'blocks' ) );
		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'available' ), 50 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'template_redirect', array( __CLASS__, 'error_notice' ) );
	}

	/** After a failed iyzico page the donor lands on the order-pay page with the reason. */
	public static function error_notice() {
		if ( empty( $_GET['flxb_iyz_error'] ) || ! function_exists( 'wc_add_notice' ) || ! function_exists( 'is_checkout' ) || ! is_checkout() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		wc_add_notice( mb_substr( sanitize_text_field( wp_unslash( $_GET['flxb_iyz_error'] ) ), 0, 300 ), 'error' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	public static function load_gateway() {
		if ( class_exists( 'WC_Payment_Gateway' ) && ! class_exists( 'FLXB_WC_Gateway_Iyzico' ) ) {
			require_once FLXB_DIR . 'includes/wc/gateways/class-flxb-wc-gateway-iyzico.php';
		}
	}

	public static function register( $gateways ) {
		self::load_gateway();
		if ( class_exists( 'FLXB_WC_Gateway_Iyzico' ) ) {
			$gateways[] = 'FLXB_WC_Gateway_Iyzico';
		}
		return $gateways;
	}

	public static function blocks( $registry ) {
		if ( class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) && ! $registry->is_registered( self::ID ) ) {
			require_once FLXB_DIR . 'includes/wc/gateways/class-flxb-wc-iyzico-blocks.php';
			$registry->register( new FLXB_WC_Iyzico_Blocks() );
		}
	}

	/** iyzico keys present? */
	public static function configured() {
		return null !== FLXB_Iyzico_Api::make();
	}

	/** Gateway enabled in WooCommerce (default yes) and keys present: monthly donations possible. */
	public static function usable() {
		$s = get_option( 'woocommerce_' . self::ID . '_settings', array() );
		return 'no' !== ( is_array( $s ) ? ( $s['enabled'] ?? 'yes' ) : 'yes' ) && self::configured();
	}

	/** Is the official iyzico WooCommerce gateway registered and enabled? */
	public static function official_enabled() {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return false;
		}
		$g = WC()->payment_gateways()->payment_gateways();
		return isset( $g[ self::OFFICIAL ] ) && 'yes' === $g[ self::OFFICIAL ]->enabled;
	}

	public static function keys_text() {
		$c = FLXB_Iyzico_Api::credentials();
		switch ( $c['source'] ) {
			case 'woocommerce':
			case 'woocommerce-subscription':
				$t = __( 'Keys are read from the iyzico WooCommerce plugin settings.', 'flx-donate' );
				break;
			case 'own':
				$t = __( 'Keys are read from Donations › Settings › Payments › iyzico.', 'flx-donate' );
				break;
			default:
				return __( 'No iyzico keys found. Enter them in the iyzico WooCommerce plugin or in Donations › Settings › Payments › iyzico.', 'flx-donate' );
		}
		return $t . ' ' . ( $c['sandbox'] ? __( 'Environment: sandbox (test).', 'flx-donate' ) : __( 'Environment: live.', 'flx-donate' ) );
	}

	/**
	 * How the card is asked (2.1.0, setting card_ui): fields = our own card fields on the checkout
	 * (3D Secure, installments, saved cards), form = iyzico's payment page, official = the official
	 * iyzico plugin for one-time donations (monthly ones always use Flux Donate).
	 */
	public static function ui() {
		$u = (string) FLXB_WC::setting( 'card_ui' );
		return in_array( $u, array( 'fields', 'form', 'official' ), true ) ? $u : 'fields';
	}

	/** Which card method the current cart may use. */
	public static function available( $gateways ) {
		if ( ! is_array( $gateways ) || is_admin() && ! wp_doing_ajax() ) {
			return $gateways;
		}
		$monthly = self::context_monthly();
		if ( $monthly ) {
			/* Card storage is needed: only Flux Donate iyzico can charge the next months. */
			return isset( $gateways[ self::ID ] ) ? array( self::ID => $gateways[ self::ID ] ) : array();
		}
		if ( isset( $gateways[ self::ID ], $gateways[ self::OFFICIAL ] ) ) {
			/* One card method: the official plugin only when the setting says so (2.1.0). */
			if ( 'official' === self::ui() ) {
				unset( $gateways[ self::ID ] );
			} else {
				unset( $gateways[ self::OFFICIAL ] );
			}
		}
		return self::order_method_first( $gateways );
	}

	/** Order-pay page: the order's own method comes first, so WooCommerce selects it (2.1.0). */
	private static function order_method_first( array $gateways ) {
		global $wp;
		if ( empty( $wp->query_vars['order-pay'] ) || ! function_exists( 'is_checkout_pay_page' ) || ! is_checkout_pay_page() ) {
			return $gateways;
		}
		$o  = wc_get_order( absint( $wp->query_vars['order-pay'] ) );
		$id = $o ? $o->get_payment_method() : '';
		if ( 'iyzico' === $id && isset( $gateways[ self::ID ] ) ) {
			$id = self::ID;
		}
		if ( '' === $id || ! isset( $gateways[ $id ] ) ) {
			return $gateways;
		}
		return array( $id => $gateways[ $id ] ) + $gateways;
	}

	/** Monthly lines in the cart, or in the order being paid (order-pay page). */
	public static function context_monthly() {
		global $wp;
		if ( isset( $wp->query_vars['order-pay'] ) && function_exists( 'is_checkout_pay_page' ) && is_checkout_pay_page() ) {
			$o = wc_get_order( absint( $wp->query_vars['order-pay'] ) );
			return $o && FLXB_WC_Cart::order_has_monthly( $o );
		}
		return FLXB_WC_Cart::cart_has_monthly();
	}

	public static function callback_url() {
		return rest_url( 'flxb/v1' . self::ROUTE );
	}

	public static function routes() {
		register_rest_route(
			'flxb/v1',
			self::ROUTE,
			array(
				'methods'             => array( 'GET', 'POST' ),
				'permission_callback' => '__return_true', // iyzico posts the token here; the payment is re-checked with iyzico
				'callback'            => array( __CLASS__, 'callback' ),
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Payment
	 * ------------------------------------------------------------------ */

	/** Basket of an order: donation lines and fees (printed certificate). */
	public static function basket_lines( WC_Order $order ) {
		$lines = array();
		foreach ( $order->get_items( array( 'line_item', 'fee' ) ) as $item_id => $item ) {
			$amount = round( (float) $item->get_total() + (float) $item->get_total_tax(), 2 );
			if ( $amount <= 0 ) {
				continue;
			}
			$lines[] = array(
				'key'    => 'i' . $item_id,
				'label'  => $item->get_name(),
				'units'  => 1,
				'amount' => $amount,
			);
		}
		return $lines;
	}

	/**
	 * Start the iyzico checkout form for an order.
	 *
	 * @return string|WP_Error iyzico payment page URL.
	 */
	public static function start( WC_Order $order ) {
		$api = FLXB_Iyzico_Api::make();
		if ( ! $api ) {
			return new WP_Error( 'flxb_iyz', __( 'Card payments are not configured on this site. Please choose another payment method.', 'flx-donate' ) );
		}
		$amount = (float) $order->get_total();
		if ( $amount <= 0 ) {
			return new WP_Error( 'flxb_iyz', __( 'The amount must be greater than zero.', 'flx-donate' ) );
		}
		$conv    = 'flxbwc-' . $order->get_id();
		$contact = array(
			'name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'email' => $order->get_billing_email(),
			'phone' => $order->get_billing_phone(),
		);
		list( $buyer, $address ) = FLXB_Iyzico_Api::buyer( $contact, $order->get_billing_email(), (string) $order->get_customer_ip_address() );
		if ( '' !== $order->get_billing_city() ) {
			$address['city'] = $order->get_billing_city();
			$buyer['city']   = $order->get_billing_city();
		}
		$body = array(
			'locale'              => FLXB_Iyzico_Api::locale(),
			'conversationId'      => $conv,
			'price'               => FLXB_Iyzico_Api::price( $amount ),
			'paidPrice'           => FLXB_Iyzico_Api::price( $amount ),
			'basketId'            => $conv,
			'paymentGroup'        => FLXB_WC_Cart::order_has_monthly( $order ) ? 'SUBSCRIPTION' : 'PRODUCT',
			'buyer'               => $buyer,
			'billingAddress'      => $address,
			'basketItems'         => FLXB_Iyzico_Api::basket( $amount, self::basket_lines( $order ), __( 'Donation', 'flx-donate' ), 'flxbwc' ),
			'callbackUrl'         => self::callback_url(),
			'currency'            => strtoupper( $order->get_currency() ),
			'enabledInstallments' => array( 1 ),
		);
		$body = (array) apply_filters( 'flxb_wc_iyzico_checkout_request', $body, $order );
		try {
			$r = $api->checkout_initialize( $body );
		} catch ( FLXB_Iyzico_Error $e ) {
			return new WP_Error( 'flxb_iyz', $e->getMessage() );
		}
		if ( ! FLXB_Iyzico_Api::ok( $r ) || empty( $r['token'] ) || empty( $r['paymentPageUrl'] ) ) {
			$err = FLXB_Iyzico_Api::error_text( $r );
			return new WP_Error( 'flxb_iyz', '' !== $err ? 'iyzico: ' . $err : __( 'The payment could not be started. Please try again.', 'flx-donate' ) );
		}
		$order->update_meta_data( '_flxb_iyz_token', sanitize_text_field( (string) $r['token'] ) );
		$order->update_meta_data( '_flxb_iyz_sandbox', FLXB_Iyzico_Api::credentials()['sandbox'] ? 1 : 0 );
		$order->save();
		return (string) $r['paymentPageUrl'];
	}

	/** The order a checkout token belongs to. */
	public static function order_by_token( $token ) {
		if ( '' === (string) $token ) {
			return null;
		}
		$o = wc_get_orders(
			array(
				'limit'      => 1,
				'meta_query' => array( array( 'key' => '_flxb_iyz_token', 'value' => (string) $token ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		return $o ? $o[0] : null;
	}

	/**
	 * Ask iyzico about a checkout token and settle its order once. Safe to repeat.
	 *
	 * @return bool Paid.
	 */
	public static function settle_token( $token, $source = 'callback' ) {
		$order = self::order_by_token( $token );
		if ( ! $order ) {
			return false;
		}
		if ( $order->is_paid() || $order->has_status( array( 'refunded' ) ) ) {
			return true;
		}
		if ( ! $order->has_status( array( 'pending', 'failed', 'on-hold' ) ) ) {
			return false;
		}
		$lock = 'flxb_wc_iyz_lock_' . $order->get_id();
		if ( ! add_option( $lock, time(), '', false ) ) {
			if ( time() - (int) get_option( $lock ) < 60 ) {
				for ( $i = 0; $i < 8; $i++ ) {
					usleep( 500000 );
					$again = wc_get_order( $order->get_id() );
					if ( $again && $again->is_paid() ) {
						return true;
					}
				}
				return false;
			}
			update_option( $lock, time(), false );
		}
		try {
			return self::settle( wc_get_order( $order->get_id() ), (string) $token, $source );
		} finally {
			delete_option( $lock );
		}
	}

	private static function settle( WC_Order $order, $token, $source ) {
		if ( $order->is_paid() ) {
			return true;
		}
		$api = FLXB_Iyzico_Api::make();
		if ( ! $api ) {
			return false;
		}
		$conv = 'flxbwc-' . $order->get_id();
		try {
			$r = $api->checkout_retrieve( $token, $conv );
		} catch ( FLXB_Iyzico_Error $e ) {
			return false;
		}
		$status = (string) ( $r['paymentStatus'] ?? '' );
		if ( ! FLXB_Iyzico_Api::ok( $r ) || 'SUCCESS' !== $status ) {
			if ( 'FAILURE' === $status || ( FLXB_Iyzico_Api::ok( $r ) && '' !== $status ) ) {
				$err = FLXB_Iyzico_Api::error_text( $r );
				/* translators: %s: iyzico error */
				$order->update_status( 'failed', sprintf( __( 'iyzico: the payment was not completed (%s).', 'flx-donate' ), '' !== $err ? $err : $status ) );
				$order->update_meta_data( '_flxb_iyz_error', '' !== $err ? $err : __( 'The payment could not be completed. Please try again or use another card.', 'flx-donate' ) );
				$order->save_meta_data();
			}
			return false;
		}
		return self::finish( $order, $r, $source, $token );
	}

	/**
	 * A successful iyzico payment answer (checkout form detail or 3D Secure auth): check it against
	 * the order, keep the card reference for monthly lines (and for the donor's saved cards), pay.
	 */
	public static function finish( WC_Order $order, array $r, $source, $token = '' ) {
		$conv   = 'flxbwc-' . $order->get_id();
		$want   = FLXB_Iyzico_Api::minor( $order->get_total() );
		$basket = (string) ( $r['basketId'] ?? '' );
		$match  = $want > 0
			&& FLXB_Iyzico_Api::minor( $r['price'] ?? 0 ) === $want
			&& FLXB_Iyzico_Api::minor( $r['paidPrice'] ?? 0 ) >= $want
			&& strtoupper( (string) ( $r['currency'] ?? '' ) ) === strtoupper( $order->get_currency() )
			&& ( '' === $basket || $basket === $conv )
			&& ( '' === (string) $token || ! isset( $r['token'] ) || (string) $r['token'] === $token )
			&& -1 !== (int) ( $r['fraudStatus'] ?? 1 );
		$info = FLXB_Iyzico_Api::payment_info( $r );
		$order->update_meta_data( '_flxb_iyzico', $info );
		if ( ! $match ) {
			$order->save_meta_data();
			/* translators: %s: iyzico payment id */
			$order->update_status( 'on-hold', sprintf( __( 'iyzico reported a payment (%s) that does not match this order (amount, currency or basket). Check it in the iyzico panel before doing anything.', 'flx-donate' ), $info['paymentId'] ) );
			return false;
		}
		$k = (string) ( $r['cardUserKey'] ?? '' );
		$t = (string) ( $r['cardToken'] ?? '' );
		if ( '' !== $k && '' !== $t && FLXB_WC_Cart::order_has_monthly( $order ) ) {
			$order->update_meta_data( '_flxb_iyz_card', array( 'k' => FLXB_Iyzico_Api::seal( $k ), 't' => FLXB_Iyzico_Api::seal( $t ), 'label' => $info['card'] ) );
		}
		if ( '' !== $k && $order->get_customer_id() ) {
			FLXB_WC_Iyzico_Card::remember_user_key( $order->get_customer_id(), $k );
		}
		$order->delete_meta_data( '_flxb_iyz_3ds' );
		$order->delete_meta_data( '_flxb_iyz_error' );
		$order->save_meta_data();
		/* translators: 1: card, 2: iyzico payment id, 3: callback | webhook */
		$order->add_order_note( sprintf( __( 'iyzico payment received: %1$s, payment id %2$s (%3$s).', 'flx-donate' ), '' !== $info['card'] ? $info['card'] : '-', $info['paymentId'], $source ) );
		$order->payment_complete( $info['paymentId'] );
		do_action( 'flxb_wc_iyzico_paid', $order, $r );
		return true;
	}

	/** iyzico posts the token here after the payment page; the donor is sent on. */
	public static function callback( WP_REST_Request $r ) {
		$token = sanitize_text_field( (string) $r->get_param( 'token' ) );
		$order = self::order_by_token( $token );
		$paid  = $order ? self::settle_token( $token, 'callback' ) : false;
		$order = $order ? wc_get_order( $order->get_id() ) : null;
		if ( $paid && $order ) {
			$to = $order->get_checkout_order_received_url();
		} elseif ( $order ) {
			$err = (string) $order->get_meta( '_flxb_iyz_error' );
			$to  = add_query_arg( 'flxb_iyz_error', rawurlencode( '' !== $err ? $err : __( 'The payment could not be completed. Please try again or use another card.', 'flx-donate' ) ), $order->get_checkout_payment_url() );
		} else {
			$to = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/' );
		}
		$res = new WP_REST_Response( null, 302 );
		$res->header( 'Location', esc_url_raw( $to ) );
		return $res;
	}

	/* ------------------------------------------------------------------
	 * Refunds
	 * ------------------------------------------------------------------ */

	/**
	 * Refund an amount of an order paid with flxb_iyzico (spread over iyzico's item transactions).
	 *
	 * @return true|WP_Error
	 */
	public static function refund( WC_Order $order, $amount, $reason = '' ) {
		$amount = round( (float) $amount, 2 );
		$info   = $order->get_meta( '_flxb_iyzico' );
		if ( $amount <= 0 ) {
			return new WP_Error( 'flxb_iyz', __( 'Enter an amount to refund.', 'flx-donate' ) );
		}
		if ( ! is_array( $info ) || empty( $info['items'] ) ) {
			return new WP_Error( 'flxb_iyz', __( 'This order has no iyzico transaction to refund.', 'flx-donate' ) );
		}
		$api = FLXB_Iyzico_Api::make();
		if ( ! $api ) {
			return new WP_Error( 'flxb_iyz', __( 'iyzico is not configured on this site.', 'flx-donate' ) );
		}
		$left = FLXB_Iyzico_Api::minor( $amount );
		$room = 0;
		foreach ( $info['items'] as $it ) {
			$room += max( 0, FLXB_Iyzico_Api::minor( $it['paid'] ) - FLXB_Iyzico_Api::minor( $it['refunded'] ?? 0 ) );
		}
		if ( $left > $room ) {
			return new WP_Error( 'flxb_iyz', __( 'The amount is more than what is left to refund at iyzico.', 'flx-donate' ) );
		}
		$ip = (string) $order->get_customer_ip_address();
		$ip = '' !== $ip ? $ip : '127.0.0.1';
		foreach ( $info['items'] as $i => $it ) {
			if ( $left <= 0 ) {
				break;
			}
			$can = max( 0, FLXB_Iyzico_Api::minor( $it['paid'] ) - FLXB_Iyzico_Api::minor( $it['refunded'] ?? 0 ) );
			$now = min( $can, $left );
			if ( $now <= 0 ) {
				continue;
			}
			try {
				$r = $api->refund( $it['tx'], $now / 100, $order->get_currency(), $ip, 'flxbwc-' . $order->get_id() . '-r' . time() );
			} catch ( FLXB_Iyzico_Error $e ) {
				$r = null;
				$err = $e->getMessage();
			}
			if ( ! FLXB_Iyzico_Api::ok( $r ) ) {
				$order->update_meta_data( '_flxb_iyzico', $info );
				$order->save_meta_data();
				/* translators: %s: iyzico error */
				return new WP_Error( 'flxb_iyz', sprintf( __( 'iyzico refused the refund: %s', 'flx-donate' ), isset( $err ) ? $err : FLXB_Iyzico_Api::error_text( $r ) ) );
			}
			$info['items'][ $i ]['refunded'] = round( (float) ( $it['refunded'] ?? 0 ) + $now / 100, 2 );
			$left                           -= $now;
		}
		$order->update_meta_data( '_flxb_iyzico', $info );
		$order->save_meta_data();
		/* translators: 1: amount, 2: reason */
		$order->add_order_note( sprintf( __( 'Refunded at iyzico: %1$s. %2$s', 'flx-donate' ), FLXB_WC::money_text( $amount, $order->get_currency() ), (string) $reason ) );
		return true;
	}
}
