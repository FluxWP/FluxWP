<?php
/**
 * Flux Forms: payment provider contract.
 *
 * A provider is one class that implements this interface and is registered
 * through the `flxf_payment_providers` filter (see payments.php and
 * docs/PAYMENT-PROVIDERS.md). The core never trusts an amount from the
 * browser: create_payment() always receives the server quote.
 *
 * Part of Flux Forms (GPL v2 or later), a fork of Easy Form Builder 4.2.0 by WhiteStudio.
 *
 * @package FLX_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface FLXF_Payment_Provider {

	/** Unique machine id: lowercase letters, digits, "_" or "-" (e.g. "stripe"). */
	public function id();

	/** Human readable name shown in Settings › Payments. */
	public function label();

	/**
	 * Can this provider take a payment in this currency and with this EFB payment method?
	 *
	 * @param string $currency ISO 4217 code, upper case (e.g. "TRY").
	 * @param string $method   Form `paymentmethod`: charge | day | week | month | year.
	 * @return bool
	 */
	public function supports( $currency, $method );

	/**
	 * Settings fields rendered in Settings › Payments. Values are stored as flat keys
	 * in the plugin's setting row, so prefix keys with the provider id (e.g. "iyzicoApiKey").
	 *
	 * Each field: array(
	 *   'key'      => 'iyzicoApiKey',
	 *   'label'    => 'API key',
	 *   'type'     => 'text' | 'password' | 'select' | 'checkbox',
	 *   'options'  => array( 'value' => 'Label' ),   // select only
	 *   'help'     => 'Optional help text',
	 *   'required' => true,                          // is_configured() of the base class checks these
	 * )
	 *
	 * @return array[]
	 */
	public function settings_fields();

	/** True when every setting the provider needs to take a payment is present. */
	public function is_configured();

	/**
	 * Front-end assets loaded with the payment box when this provider is active.
	 *
	 * array(
	 *   'scripts' => array( 'handle' => array( 'src' => URL, 'deps' => array(), 'ver' => '1.0',
	 *                                          'data' => array( 'jsObjectName' => array( ...strings... ) ) ) ),
	 *   'styles'  => array( 'handle' => array( 'src' => URL, 'deps' => array(), 'ver' => '1.0' ) ),
	 * )
	 * Scripts get "flxf-pay" (window.FLXFPay) as a dependency automatically and should call
	 * FLXFPay.register( id, { mount, pay } ).
	 *
	 * @return array
	 */
	public function front_assets();

	/**
	 * Start a payment for the server-quoted amount.
	 *
	 * @param float  $amount   Major units (e.g. 150.00), computed on the server.
	 * @param string $currency ISO 4217, upper case.
	 * @param array  $meta     form_id, form_name, description, method, email, uid, ip,
	 *                         reference (unique order reference), page_url, params (extra
	 *                         browser data such as a card token; never an amount), rows.
	 * @return FLXF_Payment_Result
	 */
	public function create_payment( $amount, $currency, array $meta );

	/**
	 * Ask the provider whether a stored payment is settled.
	 *
	 * @param object $record Row of {$wpdb->prefix}emsfb_pay_ (gateway, transaction_id, amount, currency, status...).
	 * @param array  $params Extra data from the browser (e.g. a callback token).
	 * @return FLXF_Payment_Result COMPLETED when the payment is verified for the stored amount.
	 */
	public function verify( $record, array $params = array() );

	/**
	 * Handle POST Emsfb/v1/forms/payment/webhook/{id}. The provider must authenticate
	 * the request itself (signature / HMAC); the route is public.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handle_webhook( $request );

	/**
	 * Refund a payment (optional; the base class answers "not supported").
	 *
	 * @param object     $record Row of emsfb_pay_.
	 * @param float|null $amount Major units, null = full refund.
	 * @return FLXF_Payment_Result
	 */
	public function refund( $record, $amount = null );
}

/**
 * Optional: a provider that already has its own complete start endpoint (Stripe's
 * card/add) takes over the whole request instead of the generic flow.
 */
interface FLXF_Payment_Provider_Custom_Start {

	/**
	 * @param WP_REST_Request $request
	 * @param object          $public  The core \Emsfb\_Public instance.
	 * @return mixed REST response (or sends JSON itself).
	 */
	public function start_request( $request, $public );
}
