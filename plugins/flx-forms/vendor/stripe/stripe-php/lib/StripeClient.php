<?php
namespace Stripe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Allow the composer PSR-4 path (vendor/autoload.php) to work as well as init.php. */
if ( ! class_exists( '\Emsfb\StripeApiEfb', false ) ) {
	require_once dirname( __DIR__, 2 ) . '/class-Emsfb-stripe-api.php';
}
foreach ( array( 'StripeObject', 'Service/ResourceService', 'Exception/ApiErrorException', 'Exception/CardException', 'Exception/InvalidRequestException', 'Exception/AuthenticationException', 'Exception/PermissionException', 'Exception/RateLimitException', 'Exception/ApiConnectionException' ) as $efb_stripe_file ) {
	require_once __DIR__ . '/' . $efb_stripe_file . '.php';
}
unset( $efb_stripe_file );

/**
 * Minimal StripeClient compatible with the subset of stripe-php the plugin
 * core calls (see includes/class-Emsfb-public.php pay_stripe_sub_Emsfb_api).
 *
 * Amount convention: the core always sends and reads amounts as
 * "major amount x 100" regardless of currency. For two-decimal currencies
 * (USD, EUR, TRY, ...) that equals Stripe's minor unit and nothing changes.
 * For zero-decimal (JPY, KRW, ...) and three-decimal (KWD, BHD, ...)
 * currencies this client converts to/from Stripe's real minor units so the
 * customer is charged what the form shows.
 *
 * @property-read Service\ResourceService $paymentIntents
 * @property-read Service\ResourceService $products
 * @property-read Service\ResourceService $prices
 * @property-read Service\ResourceService $customers
 * @property-read Service\ResourceService $subscriptions
 */
class StripeClient {

	const SERVICES = array(
		'paymentIntents' => '/v1/payment_intents',
		'products'       => '/v1/products',
		'prices'         => '/v1/prices',
		'customers'      => '/v1/customers',
		'subscriptions'  => '/v1/subscriptions',
		'charges'        => '/v1/charges',
		'refunds'        => '/v1/refunds',
		'paymentMethods' => '/v1/payment_methods',
		'invoices'       => '/v1/invoices',
	);

	const AMOUNT_KEYS = array( 'amount', 'unit_amount', 'amount_received', 'amount_capturable', 'amount_refunded', 'amount_paid', 'amount_due', 'amount_remaining', 'subtotal', 'total' );

	/** @var string */
	private $apiKey;
	/** @var \Emsfb\StripeApiEfb */
	private $api;
	/** @var array */
	private $services = array();

	public function __construct( $config = array() ) {
		if ( is_string( $config ) ) {
			$config = array( 'api_key' => $config );
		}
		$config       = is_array( $config ) ? $config : array();
		$this->apiKey = isset( $config['api_key'] ) ? (string) $config['api_key'] : '';
		$version      = isset( $config['stripe_version'] ) ? (string) $config['stripe_version'] : null;
		$this->api    = new \Emsfb\StripeApiEfb( $this->apiKey, 30, $version );
	}

	public function getApiKey() {
		return $this->apiKey;
	}

	public function __get( $name ) {
		if ( ! isset( self::SERVICES[ $name ] ) ) {
			trigger_error( 'Undefined Stripe service: ' . esc_html( $name ), E_USER_NOTICE );
			return null;
		}
		if ( ! isset( $this->services[ $name ] ) ) {
			$this->services[ $name ] = new Service\ResourceService( $this, self::SERVICES[ $name ] );
		}
		return $this->services[ $name ];
	}

	/**
	 * @return StripeObject
	 * @throws Exception\ApiErrorException
	 */
	public function request( $method, $path, $params = null, $opts = null ) {
		$params = is_array( $params ) ? $params : array();
		$opts   = is_array( $opts ) ? $opts : array();

		if ( '/v1/subscriptions' === $path && 'post' === strtolower( $method ) && ! isset( $params['payment_behavior'] ) ) {
			/* Without this a declined / 3DS-required first invoice still
			 * returns a subscription ("incomplete") that the caller would
			 * report as paid. Fail loudly instead. */
			$params['payment_behavior'] = 'error_if_incomplete';
		}

		$params = self::paramsToStripeUnits( $params );

		try {
			$result = $this->api->request( $method, $path, $params, $opts );
		} catch ( \Emsfb\StripeApiErrorEfb $e ) {
			throw Exception\ApiErrorException::fromEfbError( $e );
		}
		return StripeObject::constructFrom( self::resultToCoreUnits( $result ) );
	}

	/** Core units (major x 100) -> Stripe minor units, for the top-level params. */
	public static function paramsToStripeUnits( array $params ) {
		if ( isset( $params['currency'] ) && is_string( $params['currency'] ) ) {
			$params['currency'] = strtolower( $params['currency'] );
			$cur                = $params['currency'];
			foreach ( array( 'amount', 'unit_amount' ) as $k ) {
				if ( isset( $params[ $k ] ) && is_numeric( $params[ $k ] ) ) {
					$params[ $k ] = self::coreToMinor( $params[ $k ], $cur );
				}
			}
		}
		return $params;
	}

	public static function coreToMinor( $value, $currency ) {
		$d = \Emsfb\StripeApiEfb::currency_decimals( $currency );
		if ( 0 === $d ) {
			return (int) round( (float) $value / 100 );
		}
		if ( 3 === $d ) {
			return (int) ( round( (float) $value ) * 10 );
		}
		return (int) round( (float) $value );
	}

	public static function minorToCore( $value, $currency ) {
		$d = \Emsfb\StripeApiEfb::currency_decimals( $currency );
		if ( 0 === $d ) {
			return (int) $value * 100;
		}
		if ( 3 === $d ) {
			return $value / 10;
		}
		return $value;
	}

	/** Stripe minor units -> core units, in every hash that names its currency. */
	public static function resultToCoreUnits( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		if ( isset( $data['currency'] ) && is_string( $data['currency'] ) && 2 !== \Emsfb\StripeApiEfb::currency_decimals( $data['currency'] ) ) {
			foreach ( self::AMOUNT_KEYS as $k ) {
				if ( isset( $data[ $k ] ) && is_numeric( $data[ $k ] ) ) {
					$data[ $k ] = self::minorToCore( $data[ $k ], $data['currency'] );
				}
			}
		}
		foreach ( $data as $k => $v ) {
			if ( is_array( $v ) ) {
				$data[ $k ] = self::resultToCoreUnits( $v );
			}
		}
		return $data;
	}
}
