<?php
/**
 * Flux Donate: minimal Stripe REST client (no SDK).
 *
 * Form-encoded requests to https://api.stripe.com through the WordPress HTTP API, with a
 * Bearer secret key, a pinned Stripe-Version and an idempotency key on every POST.
 * Amounts are Stripe minor units. Only called when a visitor pays or a webhook arrives.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLXB_Stripe_Error extends Exception {
	/** @var int */
	public $http_status = 0;
	/** @var string */
	public $error_type = '';
	/** @var string */
	public $stripe_code = '';
}

class FLXB_Stripe_Api {

	const API_BASE    = 'https://api.stripe.com';
	/* Pinned: subscription objects are read with plan / current_period_end. */
	const API_VERSION = '2024-06-20';

	const ZERO_DECIMAL  = array( 'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf' );
	const THREE_DECIMAL = array( 'bhd', 'jod', 'kwd', 'omr', 'tnd' );

	/** @var string */
	private $secret;

	public function __construct( $secret ) {
		$this->secret = trim( (string) $secret );
	}

	/**
	 * @param string $method GET|POST|DELETE.
	 * @param string $path   e.g. /v1/payment_intents.
	 * @param array  $params Parameters.
	 * @param string $idem   Idempotency key for POST (random when empty).
	 * @return array Decoded body.
	 * @throws FLXB_Stripe_Error On a transport or API error.
	 */
	public function request( $method, $path, array $params = array(), $idem = '' ) {
		if ( '' === $this->secret ) {
			throw new FLXB_Stripe_Error( __( '\\nThe Stripe secret key is not configured.\\n', 'flx-donate' ) );
		}
		$method  = strtoupper( (string) $method );
		$headers = array(
			'Authorization'  => 'Bearer ' . $this->secret,
			'Stripe-Version' => self::API_VERSION,
			'Accept'         => 'application/json',
		);
		$url  = self::API_BASE . $path;
		$args = array(
			'method'     => $method,
			'timeout'    => 30,
			'user-agent' => 'FluxWP-Bagis/' . FLXB_VERSION . ' (WordPress)',
			'sslverify'  => true,
		);
		if ( 'GET' === $method || 'DELETE' === $method ) {
			$q = self::encode( $params );
			if ( '' !== $q ) {
				$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $q;
			}
		} else {
			$headers['Content-Type']    = 'application/x-www-form-urlencoded';
			$headers['Idempotency-Key'] = '' !== $idem ? (string) $idem : wp_generate_uuid4();
			$args['body']               = self::encode( $params );
		}
		$args['headers'] = $headers;
		$res             = wp_remote_request( $url, $args );
		if ( is_wp_error( $res ) ) {
			/* translators: %s: transport error */
			throw new FLXB_Stripe_Error( sprintf( __( '\\nCould not connect to Stripe:%s\', \'en\']]', 'flx-donate' ), $res->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code >= 200 && $code < 300 && is_array( $body ) ) {
			return $body;
		}
		$err = is_array( $body ) && isset( $body['error'] ) && is_array( $body['error'] ) ? $body['error'] : array();
		/* translators: %d: HTTP status */
		$e              = new FLXB_Stripe_Error( ! empty( $err['message'] ) ? (string) $err['message'] : sprintf( __( '\\nStripe API error (HTTP%d).\', \'en\']]', 'flx-donate' ), $code ) );
		$e->http_status = $code;
		$e->error_type  = (string) ( $err['type'] ?? '' );
		$e->stripe_code = (string) ( $err['code'] ?? '' );
		throw $e;
	}

	/** Stripe's bracket encoding (a[b][0]=c, booleans as true/false). */
	public static function encode( $params, $prefix = null ) {
		$pairs = array();
		foreach ( (array) $params as $key => $value ) {
			$name = null === $prefix ? (string) $key : $prefix . '[' . $key . ']';
			if ( is_array( $value ) ) {
				if ( $value ) {
					$sub = self::encode( $value, $name );
					if ( '' !== $sub ) {
						$pairs[] = $sub;
					}
				}
				continue;
			}
			if ( is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			}
			$pairs[] = rawurlencode( $name ) . '=' . rawurlencode( (string) $value );
		}
		return implode( '&', $pairs );
	}

	public static function decimals( $currency ) {
		$c = strtolower( (string) $currency );
		if ( in_array( $c, self::ZERO_DECIMAL, true ) ) {
			return 0;
		}
		return in_array( $c, self::THREE_DECIMAL, true ) ? 3 : 2;
	}

	public static function to_minor( $major, $currency ) {
		$d     = self::decimals( $currency );
		$minor = (int) round( (float) $major * pow( 10, $d ) );
		return 3 === $d ? (int) ( round( $minor / 10 ) * 10 ) : $minor;
	}

	public static function to_major( $minor, $currency ) {
		return (float) $minor / pow( 10, self::decimals( $currency ) );
	}

	/** Stripe-Signature header check (HMAC-SHA256 over "t.payload"). */
	public static function verify_signature( $payload, $header, $secret, $tolerance = 300 ) {
		if ( '' === (string) $secret || '' === (string) $header ) {
			return false;
		}
		$t    = null;
		$sigs = array();
		foreach ( explode( ',', (string) $header ) as $part ) {
			$kv = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $kv ) ) {
				continue;
			}
			if ( 't' === $kv[0] && ctype_digit( $kv[1] ) ) {
				$t = (int) $kv[1];
			} elseif ( 'v1' === $kv[0] ) {
				$sigs[] = $kv[1];
			}
		}
		if ( null === $t || ! $sigs || ( $tolerance > 0 && abs( time() - $t ) > $tolerance ) ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $t . '.' . $payload, (string) $secret );
		foreach ( $sigs as $s ) {
			if ( hash_equals( $expected, $s ) ) {
				return true;
			}
		}
		return false;
	}
}
