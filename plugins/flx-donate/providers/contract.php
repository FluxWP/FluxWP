<?php
/**
 * Flux Donate: payment provider contract (interface, result, base class).
 *
 * Flux Donate owns the payment provider layer when it is active next to Flux Forms. The
 * names Flux Forms used (FLXF_Payment_Provider, FLXF_Payment_Provider_Base, FLXF_Payment_Result,
 * FLXF_Payment_Provider_Custom_Start) are kept as aliases, so a provider written for Flux Forms
 * keeps working unchanged. Developer guide: docs/PAYMENT-PROVIDERS.md in Flux Forms.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface FLXB_Payment_Provider {

	/** Unique machine id: lowercase letters, digits, "_" or "-" (e.g. "stripe"). */
	public function id();

	/** Human readable name. */
	public function label();

	/**
	 * Can this provider take a payment in this currency and with this form payment method?
	 *
	 * @param string $currency ISO 4217 code, upper case.
	 * @param string $method   Form `paymentmethod`: charge | day | week | month | year.
	 */
	public function supports( $currency, $method );

	/**
	 * Settings fields shown on Donations › Payments. Each field:
	 * array( 'key' => 'iyzicoApiKey', 'label' => '', 'type' => 'text|password|select|checkbox',
	 *        'options' => array(), 'help' => '', 'required' => true ).
	 * Prefix keys with the provider id.
	 *
	 * @return array[]
	 */
	public function settings_fields();

	/** True when every setting the provider needs is present. */
	public function is_configured();

	/**
	 * Front-end assets loaded with the payment box when this provider is active:
	 * array( 'scripts' => array( handle => array( 'src', 'deps', 'ver', 'data' => array( jsObject => array() ) ) ),
	 *        'styles'  => array( handle => array( 'src', 'deps', 'ver' ) ) ).
	 * Scripts get "flxf-pay" (window.FLXFPay) as a dependency and call FLXFPay.register( id, { mount, pay } ).
	 */
	public function front_assets();

	/**
	 * Start a payment for the server-quoted amount (never an amount from the browser).
	 *
	 * @param float  $amount   Major units, computed on the server.
	 * @param string $currency ISO 4217, upper case.
	 * @param array  $meta     form_id, form_name, description, method, email, uid, ip, reference,
	 *                         page_url, params (extra browser data such as a card token), rows.
	 * @return FLXB_Payment_Result
	 */
	public function create_payment( $amount, $currency, array $meta );

	/**
	 * Is a stored payment settled?
	 *
	 * @param object $record Row of {prefix}emsfb_pay_.
	 * @param array  $params Extra browser data.
	 * @return FLXB_Payment_Result COMPLETED when verified for the stored amount.
	 */
	public function verify( $record, array $params = array() );

	/**
	 * POST Emsfb/v1/forms/payment/webhook/{id}. The provider authenticates the request itself.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handle_webhook( $request );

	/**
	 * Refund (optional).
	 *
	 * @param object     $record Row of emsfb_pay_.
	 * @param float|null $amount Major units, null = full refund.
	 * @return FLXB_Payment_Result
	 */
	public function refund( $record, $amount = null );
}

/** Optional: a provider that answers the whole start request itself. */
interface FLXB_Payment_Provider_Custom_Start {

	/**
	 * @param WP_REST_Request $request
	 * @param object          $public  Flux Forms's public controller.
	 */
	public function start_request( $request, $public );
}

/** Normalised result of a provider call. */
final class FLXB_Payment_Result {

	const COMPLETED       = 'completed';
	const PENDING         = 'pending';
	const REQUIRES_ACTION = 'requires_action';
	const REDIRECT        = 'redirect';
	const FAILED          = 'failed';

	/** @var string */
	public $status = self::FAILED;
	/** @var string Provider transaction id (stored as transaction_id). */
	public $reference = '';
	/** @var string Message for the visitor. */
	public $message = '';
	/** @var array Data for the provider's front script (client secret, redirect URL). Never secrets. */
	public $client = array();
	/** @var array Extra emsfb_pay_ columns: payment_type, status, capture_id, subscription_id, plan_id, product_id, interval_unit, meta. */
	public $record = array();

	public function __construct( $status, $reference = '', array $args = array() ) {
		$this->status    = in_array( $status, array( self::COMPLETED, self::PENDING, self::REQUIRES_ACTION, self::REDIRECT, self::FAILED ), true ) ? $status : self::FAILED;
		$this->reference = (string) $reference;
		foreach ( array( 'message', 'client', 'record' ) as $k ) {
			if ( isset( $args[ $k ] ) ) {
				$this->$k = 'message' === $k ? (string) $args[ $k ] : (array) $args[ $k ];
			}
		}
	}

	public static function completed( $reference, array $args = array() ) {
		return new self( self::COMPLETED, $reference, $args );
	}

	public static function pending( $reference, array $args = array() ) {
		return new self( self::PENDING, $reference, $args );
	}

	public static function requires_action( $reference, array $client, array $args = array() ) {
		$args['client'] = $client;
		return new self( self::REQUIRES_ACTION, $reference, $args );
	}

	public static function redirect( $reference, $url, array $args = array() ) {
		$args['client'] = array_merge( isset( $args['client'] ) ? (array) $args['client'] : array(), array( 'redirect_url' => esc_url_raw( $url ) ) );
		return new self( self::REDIRECT, $reference, $args );
	}

	public static function failed( $message, array $args = array() ) {
		$args['message'] = (string) $message;
		return new self( self::FAILED, '', $args );
	}

	public function is_ok() {
		return self::FAILED !== $this->status;
	}

	public function is_paid() {
		return self::COMPLETED === $this->status;
	}

	public function to_array() {
		return array(
			'status'    => $this->status,
			'reference' => $this->reference,
			'message'   => $this->message,
			'client'    => $this->client,
		);
	}
}

/** Convenient base: implement id(), label() and create_payment(). */
abstract class FLXB_Payment_Provider_Base implements FLXB_Payment_Provider {

	public function supports( $currency, $method ) {
		return 'charge' === $method && (bool) preg_match( '/^[A-Z]{3}$/', strtoupper( (string) $currency ) );
	}

	public function settings_fields() {
		return array();
	}

	public function is_configured() {
		foreach ( $this->settings_fields() as $f ) {
			if ( ! empty( $f['required'] ) && '' === $this->setting( $f['key'] ) ) {
				return false;
			}
		}
		return true;
	}

	public function front_assets() {
		return array();
	}

	/** Default: trust only a record already marked completed (by create_payment() or a webhook). */
	public function verify( $record, array $params = array() ) {
		if ( is_object( $record ) && 'completed' === $record->status ) {
			return FLXB_Payment_Result::completed( (string) $record->transaction_id );
		}
		return FLXB_Payment_Result::failed( __( '\\nThe payment could not be verified yet.\\n', 'flx-donate' ) );
	}

	public function handle_webhook( $request ) {
		return new WP_REST_Response( array( 'error' => 'webhook_not_supported' ), 501 );
	}

	public function refund( $record, $amount = null ) {
		return FLXB_Payment_Result::failed( __( '\\nThis payment provider does not support refunds from the site.\\n', 'flx-donate' ) );
	}

	/** One of this provider's stored settings ('' when missing). */
	protected function setting( $key, $default = '' ) {
		return flxf_payment_setting( $key, $default );
	}
}

/* Names used by Flux Forms and by providers written for it. */
foreach ( array(
	'FLXF_Payment_Provider'              => 'FLXB_Payment_Provider',
	'FLXF_Payment_Provider_Custom_Start' => 'FLXB_Payment_Provider_Custom_Start',
	'FLXF_Payment_Result'                => 'FLXB_Payment_Result',
	'FLXF_Payment_Provider_Base'         => 'FLXB_Payment_Provider_Base',
) as $flxb_alias => $flxb_class ) {
	if ( ! class_exists( $flxb_alias, false ) && ! interface_exists( $flxb_alias, false ) ) {
		class_alias( $flxb_class, $flxb_alias );
	}
}
unset( $flxb_alias, $flxb_class );
