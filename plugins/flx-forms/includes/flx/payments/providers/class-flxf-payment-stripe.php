<?php
/**
 * Flux Forms: Stripe as a payment provider.
 *
 * A thin adapter around the existing Stripe add-on (vendor/stripe). The working
 * Stripe logic is not duplicated: starting a payment is delegated to the legacy
 * card/add flow (\Emsfb\StripeHandlerEfb::card_add_stripe -> core
 * pay_stripe_sub_Emsfb_api), verification to StripeHandlerEfb::verify_payment_row()
 * and webhooks to StripeHandlerEfb::webhook(). Keys stay where they always were
 * (stripePKey / stripeSKey in the plugin settings).
 *
 * Part of Flux Forms (GPL v2 or later), a fork of Easy Form Builder 4.2.0 by WhiteStudio.
 *
 * @package FLX_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLXF_Payment_Stripe extends FLXF_Payment_Provider_Base implements FLXF_Payment_Provider_Custom_Start {

	public function id() {
		return 'stripe';
	}

	public function label() {
		return 'Stripe';
	}

	private static function handler() {
		if ( ! class_exists( '\Emsfb\StripeHandlerEfb' ) ) {
			$f = EMSFB_PLUGIN_DIRECTORY . 'vendor/stripe/class-Emsfb-stripe-handler.php';
			if ( is_readable( $f ) ) {
				require_once $f;
			}
		}
		return class_exists( '\Emsfb\StripeHandlerEfb' );
	}

	public function supports( $currency, $method ) {
		return self::handler()
			&& in_array( $method, \Emsfb\StripeHandlerEfb::PAYMENT_METHODS, true )
			&& (bool) preg_match( '/^[A-Z]{3}$/', strtoupper( (string) $currency ) );
	}

	/**
	 * The existing Settings › Payments markup already has inputs for these keys
	 * (stripePKey_emsFormBuilder / stripeSKey_emsFormBuilder), so the generic
	 * renderer leaves them alone and the saved keys keep working.
	 */
	public function settings_fields() {
		return array(
			array( 'key' => 'stripePKey', 'label' => __( 'Publishable key', 'flx-forms' ), 'type' => 'text', 'required' => true ),
			array( 'key' => 'stripeSKey', 'label' => __( 'Secret key', 'flx-forms' ), 'type' => 'password', 'required' => true ),
		);
	}

	public function is_configured() {
		return self::handler() && '' !== \Emsfb\StripeHandlerEfb::secret_key() && strlen( $this->setting( 'stripePKey' ) ) > 5;
	}

	/** Stripe.js and stripe_pay-efb.js are enqueued by the core with the payment box. */
	public function front_assets() {
		return array();
	}

	public function start_request( $request, $public ) {
		if ( ! self::handler() ) {
			return new WP_REST_Response( array( 'success' => true, 'data' => array( 'success' => false, 'm' => __( 'Stripe is not configured on this site. Please contact the site administrator.', 'flx-forms' ) ) ), 200 );
		}
		return \Emsfb\StripeHandlerEfb::card_add_stripe( $request, $public );
	}

	/**
	 * Contract implementation for one-time charges (the live form uses the legacy
	 * card/add flow above, which also covers subscriptions): creates a
	 * PaymentIntent for the server amount; the browser confirms it with Stripe.js.
	 */
	public function create_payment( $amount, $currency, array $meta ) {
		if ( ! self::handler() ) {
			return FLXF_Payment_Result::failed( __( 'Stripe is not configured on this site. Please contact the site administrator.', 'flx-forms' ) );
		}
		if ( 'charge' !== ( $meta['method'] ?? 'charge' ) ) {
			return FLXF_Payment_Result::failed( __( 'The payment method of this form is not supported.', 'flx-forms' ) );
		}
		$api = \Emsfb\StripeHandlerEfb::api();
		if ( ! $api ) {
			return FLXF_Payment_Result::failed( __( 'Stripe is not configured on this site. Please contact the site administrator.', 'flx-forms' ) );
		}
		$params = array(
			'amount'                 => \Emsfb\StripeApiEfb::to_minor( $amount, $currency ),
			'currency'               => strtolower( $currency ),
			'payment_method_types'   => array( 'card' ),
			'description'            => (string) ( $meta['description'] ?? '' ),
		);
		if ( ! empty( $meta['email'] ) && is_email( $meta['email'] ) ) {
			$params['receipt_email'] = $meta['email'];
		}
		try {
			$pi = $api->request( 'POST', '/v1/payment_intents', $params );
		} catch ( \Throwable $e ) {
			return FLXF_Payment_Result::failed( 'Stripe: ' . $e->getMessage() );
		}
		if ( empty( $pi['id'] ) || empty( $pi['client_secret'] ) ) {
			return FLXF_Payment_Result::failed( __( 'The payment could not be started. Please try again.', 'flx-forms' ) );
		}
		return FLXF_Payment_Result::requires_action( $pi['id'], array( 'client_secret' => $pi['client_secret'] ), array( 'record' => array( 'payment_type' => 'one-time' ) ) );
	}

	public function verify( $record, array $params = array() ) {
		if ( ! self::handler() ) {
			return FLXF_Payment_Result::failed( __( 'The payment could not be verified yet.', 'flx-forms' ) );
		}
		$reason = '';
		if ( \Emsfb\StripeHandlerEfb::verify_payment_row( $record, $reason ) ) {
			return FLXF_Payment_Result::completed( (string) $record->transaction_id );
		}
		return FLXF_Payment_Result::failed( __( 'The payment could not be verified yet.', 'flx-forms' ), array( 'record' => array( 'reason' => $reason ) ) );
	}

	/** Webhook card on the Payments admin page (legacy URL + signing secret form of the add-on). */
	public function admin_webhook_card() {
		$has = self::handler() && class_exists( '\Emsfb\StripePayment' ) ? '' !== \Emsfb\StripePayment::webhook_secret() : false;
		echo '<p class="flxfp-muted">' . esc_html__( 'Optional but recommended: in the Stripe Dashboard add a webhook endpoint with this URL and the events payment_intent.succeeded, payment_intent.payment_failed, payment_intent.canceled, charge.refunded, customer.subscription.updated and customer.subscription.deleted, then paste its signing secret below.', 'flx-forms' ) . '</p>';
		echo '<p><code class="flxfp-code">' . esc_html( rest_url( 'Emsfb/v1/forms/payment/stripe/webhook' ) ) . '</code></p>';
		if ( ! has_action( 'admin_post_emsfb_stripe_save' ) ) {
			return;
		}
		echo '<form class="flxfp-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="emsfb_stripe_save">';
		wp_nonce_field( 'emsfb_stripe_save' );
		echo '<label class="flxfp-label" for="emsfb_stripe_webhook_secret">' . esc_html__( 'Webhook signing secret', 'flx-forms' ) . '</label>';
		echo '<div class="flxfp-row"><input type="password" class="flxfp-input" autocomplete="off" id="emsfb_stripe_webhook_secret" name="emsfb_stripe_webhook_secret" value="" placeholder="' . esc_attr( $has ? __( 'Saved - leave empty to keep it', 'flx-forms' ) : 'whsec_...' ) . '">';
		echo '<button type="submit" class="flxfp-btn flxfp-btn--primary">' . esc_html__( 'Save', 'flx-forms' ) . '</button></div>';
		if ( $has ) {
			echo '<label class="flxfp-check"><input type="checkbox" name="emsfb_stripe_webhook_clear" value="1"> ' . esc_html__( 'Remove the saved secret', 'flx-forms' ) . '</label>';
		}
		echo '</form>';
	}

	public function handle_webhook( $request ) {
		if ( ! self::handler() ) {
			return new WP_REST_Response( array( 'error' => 'webhook_not_configured' ), 503 );
		}
		return \Emsfb\StripeHandlerEfb::webhook( $request );
	}
}
