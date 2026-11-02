<?php
/**
 * Flux Donate: small iyzico API client (no SDK, no WooCommerce needed).
 *
 * Signs each request with iyzico's IYZWSv2 scheme (HMAC-SHA256 over random key + URI path +
 * JSON body) and sends it with wp_remote_post(), so it works whether or not the iyzico
 * WooCommerce plugins (and their bundled SDK) are installed, and tests can answer it through
 * the `pre_http_request` filter. Only the endpoints Flux Donate needs:
 *
 *   checkout_initialize()  POST /payment/iyzipos/checkoutform/initialize/auth/ecom
 *   checkout_retrieve()    POST /payment/iyzipos/checkoutform/auth/ecom/detail
 *   payment_create()       POST /payment/auth            (stored card, renewals)
 *   refund()               POST /payment/refund          (per payment transaction)
 *   cancel()               POST /payment/cancel          (same-day void)
 *   bin_check()            POST /payment/bin/check       (system check: key test)
 *   threeds_initialize()   POST /payment/3dsecure/initialize (2.1.0: card fields on the checkout)
 *   threeds_auth()         POST /payment/3dsecure/auth       (after the bank's 3D Secure page)
 *   installments()         POST /payment/iyzipos/installment (installment prices for a card BIN)
 *   cards() / card_delete() POST|DELETE /cardstorage/cards, /cardstorage/card (cards iyzico stores)
 *
 * Credentials (FLXB_Iyzico_Api::credentials()): the iyzico WooCommerce plugin's settings
 * (option woocommerce_iyzico_settings: api_key, secret_key, api_type = base URL) when present,
 * else the iyzico Subscription plugin's (woocommerce_iyzico_subscription_settings: api_key,
 * secret_key, sandbox = yes/no), else Flux Donate's own fields (Donations › Payments › iyzico).
 * Keys and secrets are never logged or printed.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLXB_Iyzico_Error extends Exception {}

final class FLXB_Iyzico_Api {

	const LIVE    = 'https://api.iyzipay.com';
	const SANDBOX = 'https://sandbox-api.iyzipay.com';

	/** @var string */
	private $key;
	/** @var string */
	private $secret;
	/** @var string */
	private $base;

	public function __construct( $key, $secret, $base ) {
		$this->key    = (string) $key;
		$this->secret = (string) $secret;
		$this->base   = rtrim( (string) $base, '/' );
	}

	/* ------------------------------------------------------------------
	 * Credentials
	 * ------------------------------------------------------------------ */

	/**
	 * Where the keys come from and which environment they are for.
	 *
	 * @return array{api_key:string, secret_key:string, sandbox:bool, base:string, source:string}
	 *         source: 'woocommerce' | 'woocommerce-subscription' | 'own' | ''.
	 */
	public static function credentials() {
		$out = array( 'api_key' => '', 'secret_key' => '', 'sandbox' => true, 'base' => self::SANDBOX, 'source' => '' );

		$wc = get_option( 'woocommerce_iyzico_settings', array() );
		if ( is_array( $wc ) && self::usable( $wc['api_key'] ?? '' ) && self::usable( $wc['secret_key'] ?? '' ) ) {
			/* api_type holds the base URL (Live: https://api.iyzipay.com, Sandbox: https://sandbox-api.iyzipay.com).
			 * Anything else is treated as sandbox, so a half-saved setting never takes real money. */
			$live = rtrim( strtolower( trim( (string) ( $wc['api_type'] ?? '' ) ) ), '/' ) === self::LIVE;
			$out  = array( 'api_key' => trim( (string) $wc['api_key'] ), 'secret_key' => trim( (string) $wc['secret_key'] ), 'sandbox' => ! $live, 'base' => $live ? self::LIVE : self::SANDBOX, 'source' => 'woocommerce' );
		} else {
			$ws = get_option( 'woocommerce_iyzico_subscription_settings', array() );
			if ( is_array( $ws ) && self::usable( $ws['api_key'] ?? '' ) && self::usable( $ws['secret_key'] ?? '' ) ) {
				$sb  = 'no' !== strtolower( (string) ( $ws['sandbox'] ?? 'yes' ) );
				$out = array( 'api_key' => trim( (string) $ws['api_key'] ), 'secret_key' => trim( (string) $ws['secret_key'] ), 'sandbox' => $sb, 'base' => $sb ? self::SANDBOX : self::LIVE, 'source' => 'woocommerce-subscription' );
			} else {
				$k  = self::own( 'iyzicoApiKey' );
				$s  = self::own( 'iyzicoSecretKey' );
				$sb = '0' !== self::own( 'iyzicoSandbox', '1' );
				if ( self::usable( $k ) && self::usable( $s ) ) {
					$out = array( 'api_key' => $k, 'secret_key' => $s, 'sandbox' => $sb, 'base' => $sb ? self::SANDBOX : self::LIVE, 'source' => 'own' );
				}
			}
		}
		return (array) apply_filters( 'flxb_iyzico_credentials', $out );
	}

	private static function usable( $v ) {
		return is_string( $v ) && strlen( trim( $v ) ) > 5;
	}

	private static function own( $key, $default = '' ) {
		$o = get_option( 'flxb_payments', array() );
		$f = is_array( $o ) && isset( $o['fields'] ) && is_array( $o['fields'] ) ? $o['fields'] : array();
		return isset( $f[ $key ] ) && is_scalar( $f[ $key ] ) && '' !== trim( (string) $f[ $key ] ) ? trim( (string) $f[ $key ] ) : $default;
	}

	/** @return FLXB_Iyzico_Api|null */
	public static function make() {
		$c = self::credentials();
		if ( ! self::usable( $c['api_key'] ) || ! self::usable( $c['secret_key'] ) ) {
			return null;
		}
		return apply_filters( 'flxb_iyzico_api_client', new self( $c['api_key'], $c['secret_key'], $c['base'] ) );
	}

	/* ------------------------------------------------------------------
	 * Formatting
	 * ------------------------------------------------------------------ */

	/** iyzico price string: "150.0", "150.5", "149.99" (same as the SDK's RequestFormatter). */
	public static function price( $v ) {
		$s = number_format( round( (float) $v, 2 ), 2, '.', '' );
		$s = rtrim( $s, '0' );
		return '.' === substr( $s, -1 ) ? $s . '0' : $s;
	}

	/** Minor units (kuruş / cents) for exact sums. */
	public static function minor( $v ) {
		return (int) round( (float) $v * 100 );
	}

	public static function locale() {
		$l = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		return 0 === strpos( strtolower( (string) $l ), 'tr' ) ? 'tr' : 'en';
	}

	/* ------------------------------------------------------------------
	 * Request parts
	 * ------------------------------------------------------------------ */

	/** Basket items from the donation's lines; a remainder (unmapped priced rows) is its own item. */
	public static function basket( $amount, array $lines, $fallback_name, $prefix ) {
		$total = FLXB_Iyzico_Api::minor( $amount );
		$items = array();
		$sum   = 0;
		foreach ( $lines as $i => $l ) {
			$m = FLXB_Iyzico_Api::minor( $l['amount'] ?? 0 );
			if ( $m <= 0 || $sum + $m > $total ) {
				continue;
			}
			$sum    += $m;
			$label   = trim( (string) ( $l['label'] ?? '' ) );
			$units   = (int) ( $l['units'] ?? 1 );
			$items[] = array(
				'id'        => substr( $prefix . '-' . ( (string) ( $l['key'] ?? '' ) !== '' ? sanitize_key( (string) $l['key'] ) : 'l' . $i ), 0, 64 ),
				'name'      => mb_substr( ( '' !== $label ? $label : $fallback_name ) . ( $units > 1 ? ' × ' . $units : '' ), 0, 100 ),
				'category1' => __( 'Donation', 'flx-donate' ),
				'itemType'  => 'VIRTUAL',
				'price'     => FLXB_Iyzico_Api::price( $m / 100 ),
			);
		}
		if ( $sum < $total ) {
			$items[] = array(
				'id'        => substr( $prefix . '-' . ( $items ? 'other' : 'donation' ), 0, 64 ),
				'name'      => mb_substr( $items ? __( 'Other', 'flx-donate' ) : $fallback_name, 0, 100 ),
				'category1' => __( 'Donation', 'flx-donate' ),
				'itemType'  => 'VIRTUAL',
				'price'     => FLXB_Iyzico_Api::price( ( $total - $sum ) / 100 ),
			);
		}
		return $items;
	}

	/** iyzico buyer + billing address from the donor's details. */
	public static function buyer( array $contact, $email, $ip ) {
		$name  = trim( preg_replace( '/\s+/u', ' ', (string) ( $contact['name'] ?? '' ) ) );
		$parts = '' !== $name ? explode( ' ', $name ) : array();
		$last  = count( $parts ) > 1 ? array_pop( $parts ) : '';
		$first = $parts ? implode( ' ', $parts ) : '';
		$first = '' !== $first ? $first : __( 'Donor', 'flx-donate' );
		$last  = '' !== $last ? $last : $first;
		$email = is_email( $email ) ? $email : ( is_email( $contact['email'] ?? '' ) ? (string) $contact['email'] : '' );
		if ( '' === $email ) {
			$host  = wp_parse_url( home_url(), PHP_URL_HOST );
			$email = 'bagis@' . ( $host ? preg_replace( '/^www\./', '', $host ) : 'example.com' );
		}
		$phone = '';
		if ( ! empty( $contact['phone'] ) ) {
			$phone = function_exists( 'flxb_normalize_phone' ) ? (string) flxb_normalize_phone( (string) $contact['phone'] ) : preg_replace( '/[^0-9+]/', '', (string) $contact['phone'] );
		}
		$city    = __( 'Istanbul', 'flx-donate' );
		$country = __( 'Turkey', 'flx-donate' );
		$b       = array(
			'id'                  => 'flxb-' . substr( md5( strtolower( $email ) . '|' . $phone ), 0, 16 ),
			'name'                => mb_substr( $first, 0, 50 ),
			'surname'             => mb_substr( $last, 0, 50 ),
			'identityNumber'      => '11111111111', // TC kimlik no is not asked on donation forms; iyzico accepts this placeholder
			'email'               => $email,
			'gsmNumber'           => $phone,
			'registrationAddress' => '-',
			'city'                => $city,
			'country'             => $country,
			'ip'                  => (string) $ip,
		);
		$b       = (array) apply_filters( 'flxb_iyzico_buyer', $b, $contact );
		$address = array(
			'contactName' => trim( $b['name'] . ' ' . $b['surname'] ),
			'city'        => (string) $b['city'],
			'country'     => (string) $b['country'],
			'address'     => (string) $b['registrationAddress'],
		);
		return array( $b, $address );
	}

	/** What refunds and the admin need from a payment answer (no card data). */
	public static function payment_info( array $r ) {
		$items = array();
		foreach ( (array) ( $r['itemTransactions'] ?? array() ) as $it ) {
			if ( is_array( $it ) && ! empty( $it['paymentTransactionId'] ) ) {
				$items[] = array(
					'item'     => sanitize_text_field( (string) ( $it['itemId'] ?? '' ) ),
					'tx'       => sanitize_text_field( (string) $it['paymentTransactionId'] ),
					'paid'     => round( (float) ( $it['paidPrice'] ?? ( $it['price'] ?? 0 ) ), 2 ),
					'refunded' => 0,
				);
			}
		}
		$last4 = (string) ( $r['lastFourDigits'] ?? '' );
		return array(
			'paymentId' => sanitize_text_field( (string) ( $r['paymentId'] ?? '' ) ),
			'paidPrice' => round( (float) ( $r['paidPrice'] ?? 0 ), 2 ),
			'currency'  => strtoupper( sanitize_text_field( (string) ( $r['currency'] ?? '' ) ) ),
			'items'     => $items,
			'card'      => trim( sanitize_text_field( (string) ( $r['cardAssociation'] ?? '' ) ) . ( preg_match( '/^\d{4}$/', $last4 ) ? ' ···· ' . $last4 : '' ) ),
			'at'        => current_time( 'mysql', true ),
		);
	}

	/* ------------------------------------------------------------------
	 * Transport
	 * ------------------------------------------------------------------ */

	/** IYZWSv2 Authorization header for a URI path and the exact JSON body that is sent. */
	public static function authorization( $key, $secret, $path, $json, $rnd ) {
		$signature = bin2hex( hash_hmac( 'sha256', $rnd . $path . $json, $secret, true ) );
		return 'IYZWSv2 ' . base64_encode( 'apiKey:' . $key . '&randomKey:' . $rnd . '&signature:' . $signature );
	}

	/**
	 * POST a JSON body to an iyzico path.
	 *
	 * @return array Decoded answer (status 'success' | 'failure', errorCode, errorMessage, ...).
	 * @throws FLXB_Iyzico_Error On transport errors or an unreadable answer.
	 */
	public function post( $path, array $body, $method = 'POST' ) {
		/* Same encoding as the official SDK (plain json_encode); the exact string sent is signed. */
		$json = wp_json_encode( self::strip( $body ) );
		$rnd  = uniqid();
		$res  = wp_remote_request(
			$this->base . $path,
			array(
				'method'      => $method,
				'timeout'     => (int) apply_filters( 'flxb_iyzico_timeout', 30 ),
				'redirection' => 0,
				'headers'     => array(
					'Accept'                => 'application/json',
					'Content-Type'          => 'application/json',
					'Authorization'         => self::authorization( $this->key, $this->secret, $path, $json, $rnd ),
					'x-iyzi-client-version' => 'flx-donate-' . ( defined( 'FLXB_VERSION' ) ? FLXB_VERSION : '1' ),
				),
				'body'        => $json,
			)
		);
		if ( is_wp_error( $res ) ) {
			throw new FLXB_Iyzico_Error( __( 'iyzico could not be reached. Please try again.', 'flx-donate' ) );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) ) {
			throw new FLXB_Iyzico_Error( sprintf( /* translators: %d: HTTP status code */ __( 'iyzico gave an unexpected answer (HTTP %d).', 'flx-donate' ), (int) wp_remote_retrieve_response_code( $res ) ) );
		}
		return $data;
	}

	/** Leave out empty values, like the SDK's JsonBuilder. */
	private static function strip( array $a ) {
		$out = array();
		foreach ( $a as $k => $v ) {
			if ( null === $v || '' === $v || ( is_array( $v ) && ! $v ) ) {
				continue;
			}
			$out[ $k ] = is_array( $v ) ? self::strip( $v ) : $v;
		}
		return $out;
	}

	public static function ok( $r ) {
		return is_array( $r ) && 'success' === ( $r['status'] ?? '' );
	}

	/** Short, safe error text from an answer (code + message, nothing else). */
	public static function error_text( $r ) {
		if ( ! is_array( $r ) ) {
			return '';
		}
		$msg  = sanitize_text_field( (string) ( $r['errorMessage'] ?? '' ) );
		$code = sanitize_text_field( (string) ( $r['errorCode'] ?? '' ) );
		return trim( ( '' !== $code ? '[' . $code . '] ' : '' ) . $msg );
	}

	public function checkout_initialize( array $body ) {
		return $this->post( '/payment/iyzipos/checkoutform/initialize/auth/ecom', $body );
	}

	public function checkout_retrieve( $token, $conversation_id = '' ) {
		return $this->post( '/payment/iyzipos/checkoutform/auth/ecom/detail', array( 'locale' => self::locale(), 'conversationId' => (string) $conversation_id, 'token' => (string) $token ) );
	}

	/** Card BIN lookup: a signed, harmless request used by the system check's "Test connection". */
	public function bin_check( $bin ) {
		return $this->post( '/payment/bin/check', array( 'locale' => self::locale(), 'conversationId' => 'flxb-test-' . time(), 'binNumber' => substr( preg_replace( '/\D/', '', (string) $bin ), 0, 8 ) ) );
	}

	public function threeds_initialize( array $body ) {
		return $this->post( '/payment/3dsecure/initialize', $body );
	}

	public function threeds_auth( $payment_id, $conversation_data, $conversation_id = '' ) {
		return $this->post( '/payment/3dsecure/auth', array( 'locale' => self::locale(), 'conversationId' => (string) $conversation_id, 'paymentId' => (string) $payment_id, 'conversationData' => (string) $conversation_data ) );
	}

	/** Installment prices for a card BIN (first 6-8 digits) and an amount. */
	public function installments( $bin, $amount ) {
		return $this->post( '/payment/iyzipos/installment', array( 'locale' => self::locale(), 'conversationId' => 'flxb-inst-' . time(), 'binNumber' => substr( preg_replace( '/\D/', '', (string) $bin ), 0, 8 ), 'price' => self::price( $amount ) ) );
	}

	public function cards( $card_user_key ) {
		return $this->post( '/cardstorage/cards', array( 'locale' => self::locale(), 'conversationId' => 'flxb-cards-' . time(), 'cardUserKey' => (string) $card_user_key ) );
	}

	public function card_delete( $card_user_key, $card_token ) {
		return $this->post( '/cardstorage/card', array( 'locale' => self::locale(), 'conversationId' => 'flxb-cdel-' . time(), 'cardUserKey' => (string) $card_user_key, 'cardToken' => (string) $card_token ), 'DELETE' );
	}

	public function payment_create( array $body ) {
		return $this->post( '/payment/auth', $body );
	}

	public function refund( $transaction_id, $amount, $currency, $ip, $conversation_id = '' ) {
		return $this->post( '/payment/refund', array( 'locale' => self::locale(), 'conversationId' => (string) $conversation_id, 'paymentTransactionId' => (string) $transaction_id, 'price' => self::price( $amount ), 'currency' => strtoupper( (string) $currency ), 'ip' => (string) $ip ) );
	}

	public function cancel( $payment_id, $ip, $conversation_id = '', $reason = 'other', $description = '' ) {
		$b = array( 'locale' => self::locale(), 'conversationId' => (string) $conversation_id, 'paymentId' => (string) $payment_id, 'ip' => (string) $ip, 'reason' => (string) $reason );
		if ( '' !== $description ) {
			$b['description'] = mb_substr( (string) $description, 0, 250 );
		}
		return $this->post( '/payment/cancel', $b );
	}

	/* ------------------------------------------------------------------
	 * Card tokens at rest
	 * ------------------------------------------------------------------ */

	private static function crypt_key() {
		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' );
		return hash( 'sha256', 'flxb-iyzico-card|' . $salt, true );
	}

	/** Encrypt a card reference for the database (sodium, else OpenSSL AES-256-GCM). */
	public static function seal( $plain ) {
		$plain = (string) $plain;
		if ( '' === $plain ) {
			return '';
		}
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			return 's1:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, self::crypt_key() ) );
		}
		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv  = random_bytes( 12 );
			$tag = '';
			$c   = openssl_encrypt( $plain, 'aes-256-gcm', self::crypt_key(), OPENSSL_RAW_DATA, $iv, $tag );
			if ( false !== $c ) {
				return 'o1:' . base64_encode( $iv . $tag . $c );
			}
		}
		return '';
	}

	public static function open( $sealed ) {
		$sealed = (string) $sealed;
		$raw    = base64_decode( substr( $sealed, 3 ), true );
		if ( false === $raw ) {
			return '';
		}
		if ( 0 === strpos( $sealed, 's1:' ) && function_exists( 'sodium_crypto_secretbox_open' ) && strlen( $raw ) > SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			$p = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), self::crypt_key() );
			return false === $p ? '' : $p;
		}
		if ( 0 === strpos( $sealed, 'o1:' ) && function_exists( 'openssl_decrypt' ) && strlen( $raw ) > 28 ) {
			$p = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', self::crypt_key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
			return false === $p ? '' : $p;
		}
		return '';
	}
}
