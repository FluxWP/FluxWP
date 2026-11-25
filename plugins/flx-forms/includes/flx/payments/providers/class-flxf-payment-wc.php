<?php
/**
 * Flux Forms: payment forms pay on the WooCommerce checkout (provider "woocommerce"). Flux Forms takes no payment
 * itself; the order is paid with any WooCommerce gateway the site has (Flux POS card, bank transfer…).
 *
 * Flow: Pay → POST forms/payment/start (server price) → a pending WooCommerce order with one line for the amount
 * (meta _flxf_track / _flxf_form_id) → the browser opens the order's payment page in a window → after paying,
 * WooCommerce sends that window to ?flxf_pay_return=1 instead of its "order received" page → the page tells the form
 * → forms/payment/confirm checks the order (paid, same total and currency). Order status changes also mark the
 * record, so a payment counts even when the window is closed early.
 *
 * Part of Flux Forms (GPL v2 or later), a fork of Easy Form Builder 4.2.0 by WhiteStudio.
 *
 * @package FLX_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLXF_Payment_WC extends FLXF_Payment_Provider_Base {

	const ID = 'woocommerce';

	/** WooCommerce is active and can make orders. */
	public static function available() {
		return function_exists( 'wc_create_order' ) && function_exists( 'get_woocommerce_currency' ) && function_exists( 'wc_get_order' );
	}

	/** The store currency ('' without WooCommerce). */
	public static function currency() {
		return self::available() ? strtoupper( (string) get_woocommerce_currency() ) : '';
	}

	/**
	 * Titles of the enabled WooCommerce payment methods.
	 *
	 * @return list<string>
	 */
	public static function gateways() {
		if ( ! self::available() || ! function_exists( 'WC' ) ) {
			return array();
		}
		$out = array();
		foreach ( WC()->payment_gateways()->payment_gateways() as $g ) {
			if ( is_object( $g ) && isset( $g->enabled ) && 'yes' === $g->enabled ) {
				$out[] = wp_strip_all_tags( (string) ( method_exists( $g, 'get_title' ) ? $g->get_title() : ( $g->title ?? $g->id ) ) );
			}
		}
		return $out;
	}

	public function id() {
		return self::ID;
	}

	public function label() {
		return __( 'WooCommerce checkout', 'flx-forms' );
	}

	/** One-time payments in the store currency. */
	public function supports( $currency, $method ) {
		return 'charge' === $method && '' !== self::currency() && strtoupper( (string) $currency ) === self::currency();
	}

	public function is_configured() {
		return self::available() && array() !== self::gateways();
	}

	public function front_assets() {
		return array(
			'scripts' => array(
				'flxf-pay-wc' => array(
					'src'  => plugins_url( 'assets/flxf-pay-wc.js', dirname( __FILE__ ) ),
					'ver'  => flxf_payments_asset_ver( 'flxf-pay-wc.js' ),
					'data' => array(
						'flxfPayWc' => array(
							'i18n' => array(
								'note'    => __( 'You will choose how to pay (card, bank transfer…) on the secure checkout page.', 'flx-forms' ),
								'window'  => __( 'Complete the payment in the window that opened. This page continues when it is done.', 'flx-forms' ),
								'blocked' => __( 'The payment window was blocked. Allow pop-ups for this site and try again.', 'flx-forms' ),
								'failed'  => __( 'The payment was not completed. You can try again.', 'flx-forms' ),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * @param float  $amount   Server quote.
	 * @param string $currency ISO 4217.
	 * @param array  $meta     See FLXF_Payment_Provider::create_payment().
	 */
	public function create_payment( $amount, $currency, array $meta ) {
		if ( ! $this->supports( $currency, 'charge' ) ) {
			return FLXF_Payment_Result::failed( __( 'The currency of this form is not supported.', 'flx-forms' ) );
		}
		$order = wc_create_order(
			array(
				'status'      => 'pending',
				'created_via' => 'flx-forms',
				'customer_id' => get_current_user_id(),
			)
		);
		if ( is_wp_error( $order ) ) {
			return FLXF_Payment_Result::failed( __( 'The payment could not be started. Please try again.', 'flx-forms' ) );
		}
		$fee = new WC_Order_Item_Fee();
		$fee->set_name( '' !== (string) ( $meta['form_name'] ?? '' ) ? (string) $meta['form_name'] : __( 'Payment', 'flx-forms' ) );
		$fee->set_amount( (string) $amount );
		$fee->set_total( (string) $amount );
		$fee->set_tax_status( 'none' );
		$order->add_item( $fee );
		$order->set_currency( strtoupper( (string) $currency ) );
		$email = sanitize_email( (string) ( $meta['email'] ?? '' ) );
		if ( '' !== $email ) {
			$order->set_billing_email( $email );
		}
		$order->update_meta_data( '_flxf_track', (string) ( $meta['reference'] ?? '' ) );
		$order->update_meta_data( '_flxf_form_id', (string) (int) ( $meta['form_id'] ?? 0 ) );
		$order->calculate_totals( false );
		$order->save();
		if ( abs( (float) $order->get_total() - (float) $amount ) > 0.004 ) {
			$order->delete( true );
			return FLXF_Payment_Result::failed( __( 'The payment could not be started. Please try again.', 'flx-forms' ) );
		}
		return FLXF_Payment_Result::redirect( (string) $order->get_id(), (string) $order->get_checkout_payment_url() );
	}

	/** A record is paid when its order is paid for the stored amount and currency. */
	public function verify( $record, array $params = array() ) {
		$order = self::available() ? wc_get_order( (int) $record->transaction_id ) : false;
		if ( ! $order instanceof WC_Order ) {
			return FLXF_Payment_Result::failed( __( 'Payment not found.', 'flx-forms' ) );
		}
		$same = abs( (float) $order->get_total() - (float) $record->amount ) < 0.005
			&& strtoupper( (string) $order->get_currency() ) === strtoupper( (string) $record->currency );
		if ( $order->is_paid() && $same ) {
			return FLXF_Payment_Result::completed( (string) $order->get_id() );
		}
		if ( $order->has_status( array( 'pending', 'on-hold' ) ) ) {
			return FLXF_Payment_Result::pending(
				(string) $order->get_id(),
				array(
					'message' => $order->has_status( 'on-hold' )
						? __( 'The order is received; the payment is confirmed when it arrives.', 'flx-forms' )
						: __( 'The payment could not be verified yet.', 'flx-forms' ),
				)
			);
		}
		return FLXF_Payment_Result::failed( __( 'The payment was not completed. You can try again.', 'flx-forms' ) );
	}

	/** Our orders: the form's payment record (null for other orders). */
	private static function record( $order ) {
		if ( ! $order instanceof WC_Order || '' === (string) $order->get_meta( '_flxf_track' ) ) {
			return null;
		}
		$row = FLXF_Payment_Records::get_by_reference( self::ID, (string) $order->get_id() );
		return $row ? $row : null;
	}

	/** woocommerce_order_status_changed / woocommerce_payment_complete: mark the form's record. */
	public static function settled( $order_id ) {
		$order = self::available() ? wc_get_order( (int) $order_id ) : false;
		$row   = self::record( $order );
		if ( ! $row || 'completed' === $row->status ) {
			return;
		}
		$p = flxf_payment_provider( self::ID );
		if ( ! $p ) {
			return;
		}
		$v = $p->verify( $row );
		if ( $v->is_paid() ) {
			FLXF_Payment_Records::update( (int) $row->id, array( 'status' => 'completed' ) );
		} elseif ( 'pending' !== $v->status ) {
			FLXF_Payment_Records::update( (int) $row->id, array( 'status' => 'failed' ) );
		}
	}

	/** After paying, WooCommerce sends our orders' window to the return page instead of "order received". */
	public static function return_url( $url, $order ) {
		if ( ! $order instanceof WC_Order || '' === (string) $order->get_meta( '_flxf_track' ) ) {
			return $url;
		}
		return add_query_arg(
			array(
				'flxf_pay_return' => '1',
				'order'           => $order->get_id(),
				'key'             => $order->get_order_key(),
			),
			home_url( '/' )
		);
	}

	/**
	 * ?flxf_pay_return=1: the payment window lands here. It tells the form page and closes; opened without a form page
	 * it says what happened.
	 */
	public static function return_page() {
		if ( ! isset( $_GET['flxf_pay_return'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the order key is the proof; the form confirms on the server.
		$oid = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		// phpcs:enable
		$order = $oid && self::available() ? wc_get_order( $oid ) : false;
		$ok    = $order instanceof WC_Order && '' !== $key && hash_equals( (string) $order->get_order_key(), $key );
		$paid  = $ok && $order->is_paid();
		nocache_headers();
		if ( $paid ) {
			$msg = __( 'Payment completed. You can close this window and return to the form.', 'flx-forms' );
		} elseif ( $ok && $order->has_status( 'on-hold' ) ) {
			$msg = __( 'The order is received; the payment is confirmed when it arrives. You can close this window and return to the form.', 'flx-forms' );
		} else {
			$msg = __( 'The payment was not completed. You can close this window and try again on the form.', 'flx-forms' );
		}
		$data = array(
			'type'   => 'flxf-pay',
			'status' => $paid ? 'paid' : 'pending',
			'ref'    => $ok ? (string) $order->get_id() : '',
		);
		echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . esc_html( get_bloginfo( 'name' ) ) . '</title>';
		echo '<style>body{margin:0;font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;display:flex;min-height:100vh;align-items:center;justify-content:center;text-align:center;padding:24px;box-sizing:border-box}</style></head><body>';
		echo '<p>' . esc_html( $msg ) . '</p>';
		$u      = wp_parse_url( home_url() );
		$origin = ( $u['scheme'] ?? 'https' ) . '://' . ( $u['host'] ?? '' ) . ( isset( $u['port'] ) ? ':' . $u['port'] : '' );
		/* The opener can be lost on the way (a bank page with its own window policy), so the same-origin form page also
		 * hears it through BroadcastChannel and a storage event. */
		if ( $ok ) {
			echo '<script>(function(d,o){var s=false;try{if(window.opener&&!window.opener.closed){window.opener.postMessage(d,o);s=true;}}catch(e){}try{var c=new BroadcastChannel("flxf-pay");c.postMessage(d);c.close();s=true;}catch(e){}try{localStorage.setItem("flxf-pay",JSON.stringify(Object.assign({t:Date.now()},d)));s=true;}catch(e){}if(s){setTimeout(function(){window.close();},300);}})(' . wp_json_encode( $data ) . ',' . wp_json_encode( $origin ) . ');</script>';
		}
		echo '</body></html>';
		exit;
	}
}

add_filter(
	'flxf_payment_providers',
	static function ( $providers ) {
		if ( FLXF_Payment_WC::available() ) {
			$providers[ FLXF_Payment_WC::ID ] = 'FLXF_Payment_WC';
		}
		return $providers;
	}
);
add_action( 'woocommerce_payment_complete', array( 'FLXF_Payment_WC', 'settled' ), 15 );
add_action( 'woocommerce_order_status_changed', array( 'FLXF_Payment_WC', 'settled' ), 15 );
add_filter( 'woocommerce_get_checkout_order_received_url', array( 'FLXF_Payment_WC', 'return_url' ), 20, 2 );
add_action( 'template_redirect', array( 'FLXF_Payment_WC', 'return_page' ), 0 );
