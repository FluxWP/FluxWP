<?php
/**
 * Minimal Stripe REST client used by the bundled Stripe add-on (AdnSPF).
 *
 * Talks to https://api.stripe.com with form-encoded bodies, a Bearer secret
 * key and a pinned Stripe-Version header, through the WordPress HTTP API
 * (wp_remote_request), so no Stripe PHP SDK is required.
 *
 * Amounts handled by this class are always in Stripe's own minor units
 * (e.g. 1999 = 19.99 TRY, 1000 = 1000 JPY).
 *
 * @package Easy_Form_Builder
 */

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Emsfb\StripeApiErrorEfb' ) ) {
	/**
	 * Error raised for a non-2xx Stripe answer or a transport failure.
	 */
	class StripeApiErrorEfb extends \Exception {
		/** @var int HTTP status (0 for a connection failure). */
		public $http_status = 0;
		/** @var string Stripe error type (card_error, invalid_request_error, ...). */
		public $error_type = '';
		/** @var string Stripe error code. */
		public $stripe_code = '';
		/** @var string Card decline code. */
		public $decline_code = '';
		/** @var string Offending parameter. */
		public $param = '';
		/** @var array|null Decoded error body. */
		public $error_body = null;
	}
}

if ( ! class_exists( '\Emsfb\StripeApiEfb' ) ) {

	class StripeApiEfb {

		const API_BASE    = 'https://api.stripe.com';
		/* Pinned on purpose: the core code reads Subscription->plan and
		 * Subscription->current_period_end, which later API versions moved. */
		const API_VERSION = '2024-06-20';

		/** Stripe zero-decimal currencies. */
		const ZERO_DECIMAL = array( 'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf' );
		/** Stripe three-decimal currencies. */
		const THREE_DECIMAL = array( 'bhd', 'jod', 'kwd', 'omr', 'tnd' );

		/** @var string */
		private $secret;
		/** @var int */
		private $timeout;
		/** @var string */
		private $version;

		public function __construct( $secret, $timeout = 30, $version = null ) {
			$this->secret  = trim( (string) $secret );
			$this->timeout = max( 5, (int) $timeout );
			$this->version = $version ? (string) $version : self::API_VERSION;
		}

		/**
		 * Perform an API call.
		 *
		 * @param string $method GET|POST|DELETE.
		 * @param string $path   e.g. /v1/payment_intents.
		 * @param array  $params Request parameters.
		 * @param array  $opts   idempotency_key, stripe_version, stripe_account.
		 * @return array Decoded JSON body.
		 * @throws StripeApiErrorEfb On transport or API error.
		 */
		public function request( $method, $path, $params = array(), $opts = array() ) {
			if ( '' === $this->secret ) {
				$e = new StripeApiErrorEfb( __( 'The Stripe secret key is not configured.', 'flx-forms' ) );
				$e->error_type = 'authentication_error';
				throw $e;
			}
			$method = strtoupper( (string) $method );
			$params = is_array( $params ) ? $params : array();
			$opts   = is_array( $opts ) ? $opts : array();

			$headers = array(
				'Authorization'  => 'Bearer ' . $this->secret,
				'Stripe-Version' => ! empty( $opts['stripe_version'] ) ? (string) $opts['stripe_version'] : $this->version,
				'Accept'         => 'application/json',
			);
			if ( ! empty( $opts['stripe_account'] ) ) {
				$headers['Stripe-Account'] = (string) $opts['stripe_account'];
			}

			$url  = self::API_BASE . $path;
			$body = null;
			if ( 'GET' === $method || 'DELETE' === $method ) {
				$query = self::encode( $params );
				if ( '' !== $query ) {
					$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $query;
				}
			} else {
				$headers['Content-Type'] = 'application/x-www-form-urlencoded';
				$headers['Idempotency-Key'] = ! empty( $opts['idempotency_key'] )
					? (string) $opts['idempotency_key']
					: ( function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : bin2hex( random_bytes( 16 ) ) );
				$body = self::encode( $params );
			}

			$args = array(
				'method'     => $method,
				'headers'    => $headers,
				'timeout'    => $this->timeout,
				'user-agent' => 'EFB-Stripe/1.0 (WordPress)',
				'sslverify'  => true,
			);
			if ( null !== $body ) {
				$args['body'] = $body;
			}

			$response = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				$e = new StripeApiErrorEfb(
					/* translators: %s: transport error message */
					sprintf( __( 'Could not connect to Stripe: %s', 'flx-forms' ), $response->get_error_message() )
				);
				$e->error_type = 'api_connection_error';
				throw $e;
			}

			$code    = (int) wp_remote_retrieve_response_code( $response );
			$raw     = (string) wp_remote_retrieve_body( $response );
			$decoded = json_decode( $raw, true );

			if ( $code >= 200 && $code < 300 && is_array( $decoded ) ) {
				return $decoded;
			}

			$err     = ( is_array( $decoded ) && isset( $decoded['error'] ) && is_array( $decoded['error'] ) ) ? $decoded['error'] : array();
			$message = isset( $err['message'] ) && '' !== $err['message']
				? (string) $err['message']
				/* translators: %d: HTTP status code */
				: sprintf( __( 'Stripe API error (HTTP %d).', 'flx-forms' ), $code );
			$e               = new StripeApiErrorEfb( $message, $code );
			$e->http_status  = $code;
			$e->error_type   = isset( $err['type'] ) ? (string) $err['type'] : '';
			$e->stripe_code  = isset( $err['code'] ) ? (string) $err['code'] : '';
			$e->decline_code = isset( $err['decline_code'] ) ? (string) $err['decline_code'] : '';
			$e->param        = isset( $err['param'] ) ? (string) $err['param'] : '';
			$e->error_body   = is_array( $decoded ) ? $decoded : null;
			throw $e;
		}

		/**
		 * Encode parameters the way Stripe expects (a[b][0]=c, booleans as
		 * "true"/"false", objects with an id as that id).
		 */
		public static function encode( $params, $prefix = null ) {
			$pairs = array();
			foreach ( (array) $params as $key => $value ) {
				$name = null === $prefix ? (string) $key : $prefix . '[' . $key . ']';
				if ( is_object( $value ) ) {
					if ( isset( $value->id ) && is_scalar( $value->id ) ) {
						$value = (string) $value->id;
					} elseif ( method_exists( $value, 'toArray' ) ) {
						$value = $value->toArray();
					} else {
						$value = get_object_vars( $value );
					}
				}
				if ( is_array( $value ) ) {
					if ( empty( $value ) ) {
						continue;
					}
					$sub = self::encode( $value, $name );
					if ( '' !== $sub ) {
						$pairs[] = $sub;
					}
					continue;
				}
				if ( is_bool( $value ) ) {
					$value = $value ? 'true' : 'false';
				} elseif ( null === $value ) {
					$value = '';
				}
				$pairs[] = rawurlencode( $name ) . '=' . rawurlencode( (string) $value );
			}
			return implode( '&', $pairs );
		}

		/** Number of minor-unit decimals Stripe uses for a currency. */
		public static function currency_decimals( $currency ) {
			$c = strtolower( (string) $currency );
			if ( in_array( $c, self::ZERO_DECIMAL, true ) ) {
				return 0;
			}
			if ( in_array( $c, self::THREE_DECIMAL, true ) ) {
				return 3;
			}
			return 2;
		}

		/** Major amount (e.g. 19.99) to Stripe minor units (1999). */
		public static function to_minor( $major, $currency ) {
			$d     = self::currency_decimals( $currency );
			$minor = (int) round( (float) $major * pow( 10, $d ) );
			if ( 3 === $d ) {
				$minor = (int) ( round( $minor / 10 ) * 10 );
			}
			return $minor;
		}

		/**
		 * Verify a Stripe-Signature header (HMAC-SHA256 over "t.payload").
		 *
		 * @return bool
		 */
		public static function verify_signature( $payload, $header, $secret, $tolerance = 300, $now = null ) {
			$secret = (string) $secret;
			$header = (string) $header;
			if ( '' === $secret || '' === $header ) {
				return false;
			}
			$timestamp  = null;
			$signatures = array();
			foreach ( explode( ',', $header ) as $part ) {
				$kv = explode( '=', trim( $part ), 2 );
				if ( 2 !== count( $kv ) ) {
					continue;
				}
				if ( 't' === $kv[0] && ctype_digit( $kv[1] ) ) {
					$timestamp = (int) $kv[1];
				} elseif ( 'v1' === $kv[0] ) {
					$signatures[] = $kv[1];
				}
			}
			if ( null === $timestamp || empty( $signatures ) ) {
				return false;
			}
			$now = null === $now ? time() : (int) $now;
			if ( $tolerance > 0 && abs( $now - $timestamp ) > $tolerance ) {
				return false;
			}
			$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
			foreach ( $signatures as $sig ) {
				if ( hash_equals( $expected, $sig ) ) {
					return true;
				}
			}
			return false;
		}
	}
}
