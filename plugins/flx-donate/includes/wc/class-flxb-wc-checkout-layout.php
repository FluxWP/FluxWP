<?php
/**
 * Flux Donate 2.21.0: compact two-column checkout (Donations › Settings › Appearance › Checkout page layout).
 *
 * Every checkout step is a card, contact and name are one card, the left column has no empty gaps. On a wide
 * screen the summary column gets the terms box and a "Pay {total}" button; both drive WooCommerce's own
 * checkbox and place order button, so validation, payment methods and 3D Secure work as before.
 * Also: guests can create an account at the checkout (WooCommerce's own option, password e-mailed) and a
 * signed-in donor's e-mail stays the account e-mail.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

final class FLXB_WC_Checkout_Layout {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 31 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'render_block_woocommerce/checkout-order-note-block', array( __CLASS__, 'notes' ) );
		add_filter( 'woocommerce_checkout_registration_enabled', array( __CLASS__, 'signup' ) );
		add_filter( 'option_woocommerce_registration_generate_password', array( __CLASS__, 'generate_password' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'account_email' ), 4, 2 );
	}

	private static function on() {
		return 'compact' === FLXB_WC_Look::get( 'checkout' ) && function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url();
	}

	public static function body_class( $c ) {
		if ( self::on() ) {
			$c[] = 'flxb-co';
		}
		return $c;
	}

	public static function assets() {
		if ( ! self::on() ) {
			return;
		}
		wp_enqueue_style( 'flxb-checkout', FLXB_URL . 'assets/wc/checkout.css', array(), FLXB_VERSION );
		wp_add_inline_style( 'flxb-checkout', 'body.flxb-co{' . FLXB_WC_Look::vars() . '}' );
		wp_enqueue_script( 'flxb-checkout', FLXB_URL . 'assets/wc/checkout.js', array(), FLXB_VERSION, true );
		wp_localize_script(
			'flxb-checkout',
			'FLXBCheckout',
			array(
				/* translators: %s: order total, e.g. "₺900,00" */
				'pay'      => __( 'Pay %s', 'flx-donate' ),
				'payPlain' => __( 'Pay', 'flx-donate' ),
				'terms'    => __( 'Please accept the terms to continue.', 'flx-donate' ),
				'secure'   => __( 'Secure, encrypted payment. Card details are not stored on this site.', 'flx-donate' ),
			)
		);
	}

	/** 2.21.0: the order note box can be turned off (Appearance › Checkout); the block is left out of the page. */
	public static function notes( $html ) {
		return FLXB_WC_Look::get( 'notes' ) ? $html : '';
	}

	/** "Create an account" box for guests (WooCommerce's own; it creates the account after the order). */
	public static function signup( $on ) {
		return FLXB_WC_Look::get( 'signup' ) ? true : $on;
	}

	/** No password field at the checkout: WooCommerce e-mails a link to set one. */
	public static function generate_password( $v ) {
		if ( ! FLXB_WC_Look::get( 'signup' ) ) {
			return $v;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$rest = false !== strpos( $uri, 'wc/store' ) || false !== strpos( $uri, 'rest_route=%2Fwc%2Fstore' ) || false !== strpos( $uri, 'rest_route=/wc/store' );
		return ( $rest || ( did_action( 'wp' ) && function_exists( 'is_checkout' ) && is_checkout() ) ) ? 'yes' : $v;
	}

	/** A signed-in donor gives with the account e-mail (the field is read-only on the page; this is the guard). */
	public static function account_email( $order, $request ) {
		if ( ! $order instanceof WC_Order || ! is_user_logged_in() ) {
			return;
		}
		$user = wp_get_current_user();
		if ( $user && is_email( $user->user_email ) && ! FLXB_WC_Contact::is_placeholder( $user->user_email ) && $order->get_billing_email() !== $user->user_email ) {
			$order->set_billing_email( $user->user_email );
		}
	}
}
