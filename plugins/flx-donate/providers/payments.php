<?php
/**
 * Flux Donate: payment provider layer (registry, settings, REST, guards, front wiring).
 *
 * When Flux Donate is active next to Flux Forms, Flux Forms hands its payment layer over
 * (filter `flxf_payments_delegate`) and Flux Donate provides it under the same public names,
 * so forms, scripts and third-party providers keep working:
 *
 *  - functions flxf_payment_providers(), flxf_payment_provider(), flxf_payment_setting(),
 *    flxf_payment_active_provider_id(), flxf_payment_active_provider(), flxf_payment_track_gateway(),
 *    flxf_payment_rest_start(); filters `flxf_payment_providers`, `flxf_payment_active_provider`;
 *  - REST (Emsfb/v1): forms/payment/start, forms/payment/confirm, forms/payment/webhook/{id};
 *    Flux Forms's Stripe routes (stripe/card/add, stripe/confirm, stripe/pkey, stripe/webhook)
 *    are answered here too, with the same request and response shapes;
 *  - the final submit of a paid form is refused until its provider verified the payment;
 *  - front: window.FLXFPay (providers/assets/flxf-pay.js) and the active provider's scripts.
 *
 * Records stay in Flux Forms's {prefix}emsfb_pay_ table. When Flux Donate is deactivated, Flux Forms
 * uses its own built-in layer again (Stripe only), nothing to migrate.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/contract.php';
require_once __DIR__ . '/class-flxb-payment-records.php';
require_once __DIR__ . '/class-flxb-stripe-api.php';
require_once __DIR__ . '/class-flxb-payment-stripe.php';
require_once __DIR__ . '/class-flxb-payment-test.php';
require_once __DIR__ . '/class-flxb-iyzico-api.php';
require_once __DIR__ . '/class-flxb-iyzico-recurring.php';
require_once __DIR__ . '/class-flxb-payment-iyzico.php';

if ( ! defined( 'FLXF_PAY_PROVIDER_SETTING' ) ) {
	define( 'FLXF_PAY_PROVIDER_SETTING', 'flxfPayProvider' );
}

/** Option holding the active provider and every provider's settings. */
const FLXB_PAY_OPTION = 'flxb_payments';

/* ======================================================================
 * Settings and registry
 * ==================================================================== */

/** @return array{provider:string, fields:array} */
function flxb_payment_options() {
	$o = get_option( FLXB_PAY_OPTION, array() );
	$o = is_array( $o ) ? $o : array();
	return array(
		'provider' => isset( $o['provider'] ) ? sanitize_key( (string) $o['provider'] ) : '',
		'fields'   => isset( $o['fields'] ) && is_array( $o['fields'] ) ? $o['fields'] : array(),
	);
}

/** Flux Forms's own settings row (fallback for keys saved before Flux Donate took over). */
function flxb_flxform_settings() {
	static $rows = null;
	if ( null === $rows ) {
		$rows = array();
		if ( function_exists( 'get_setting_Emsfb' ) ) {
			$rows[] = get_setting_Emsfb( 'decoded' );
		}
		$opt = get_option( 'emsfb_settings', '' );
		if ( is_string( $opt ) && '' !== $opt ) {
			$d = json_decode( $opt );
			if ( ! is_object( $d ) ) {
				$d = json_decode( str_replace( '\\', '', $opt ) );
			}
			$rows[] = $d;
		}
	}
	return $rows;
}

/** A provider setting: Donations › Payments first, then Flux Forms's settings ('' when missing). */
function flxf_payment_setting( $key, $default = '' ) {
	$o = flxb_payment_options();
	if ( FLXF_PAY_PROVIDER_SETTING === $key && '' !== $o['provider'] ) {
		return $o['provider'];
	}
	if ( isset( $o['fields'][ $key ] ) && is_scalar( $o['fields'][ $key ] ) && '' !== trim( (string) $o['fields'][ $key ] ) ) {
		return trim( (string) $o['fields'][ $key ] );
	}
	foreach ( flxb_flxform_settings() as $s ) {
		if ( is_object( $s ) && isset( $s->$key ) && is_scalar( $s->$key ) ) {
			$v = trim( (string) $s->$key );
			if ( '' !== $v && 'null' !== $v ) {
				return $v;
			}
		}
	}
	return $default;
}

/**
 * Registered providers, keyed by id. Add one with
 *     add_filter( 'flxf_payment_providers', fn( $p ) => $p + array( 'myprovider' => 'My_Provider' ) );
 *
 * @return FLXB_Payment_Provider[]
 */
function flxf_payment_providers() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$raw = apply_filters( 'flxf_payment_providers', array( 'stripe' => 'FLXB_Payment_Stripe', 'iyzico' => 'FLXB_Payment_Iyzico' ) );
	$out = array();
	foreach ( (array) $raw as $item ) {
		if ( is_string( $item ) && class_exists( $item ) ) {
			$item = new $item();
		}
		if ( ! $item instanceof FLXB_Payment_Provider ) {
			continue;
		}
		$id = sanitize_key( (string) $item->id() );
		if ( '' !== $id && ! isset( $out[ $id ] ) ) {
			$out[ $id ] = $item;
		}
	}
	if ( did_action( 'init' ) ) {
		$cache = $out;
	}
	return $out;
}

/** @return FLXB_Payment_Provider|null */
function flxf_payment_provider( $id ) {
	$all = flxf_payment_providers();
	$id  = sanitize_key( (string) $id );
	return $all[ $id ] ?? null;
}

function flxf_payment_active_provider_id() {
	$all = flxf_payment_providers();
	$id  = sanitize_key( flxf_payment_setting( FLXF_PAY_PROVIDER_SETTING, 'stripe' ) );
	if ( ! isset( $all[ $id ] ) ) {
		$id = isset( $all['stripe'] ) ? 'stripe' : (string) key( $all );
	}
	return (string) apply_filters( 'flxf_payment_active_provider', $id );
}

/** @return FLXB_Payment_Provider|null */
function flxf_payment_active_provider() {
	return flxf_payment_provider( flxf_payment_active_provider_id() );
}

function flxf_payment_track_gateway( $track ) {
	$row = FLXB_Payment_Records::get_by_track( (string) $track );
	return $row ? (string) $row->gateway : '';
}

/* Development-only test provider. */
add_filter(
	'flxf_payment_providers',
	function ( $providers ) {
		if ( ( defined( 'FLXF_PAYMENTS_TEST_PROVIDER' ) && FLXF_PAYMENTS_TEST_PROVIDER ) || apply_filters( 'flxf_payments_enable_test_provider', false ) ) {
			$providers['flxf_test'] = 'FLXB_Payment_Test';
		}
		return $providers;
	},
	100
);

/* ======================================================================
 * REST
 * ==================================================================== */

final class FLXB_Payments_Rest {

	/** @var object|null Flux Forms's public controller (insert_message_db, server pricing). */
	public static $public = null;

	const LEGACY = array(
		'/Emsfb/v1/forms/payment/stripe/card/add' => 'card_add',
		'/Emsfb/v1/forms/payment/stripe/confirm'  => 'stripe_confirm',
		'/Emsfb/v1/forms/payment/stripe/pkey'     => 'stripe_pkey',
		'/Emsfb/v1/forms/payment/stripe/webhook'  => 'stripe_webhook',
	);

	public static function init() {
		add_action( 'efb_register_payment_rest_routes', array( __CLASS__, 'routes' ), 1, 1 );
		add_filter( 'rest_dispatch_request', array( __CLASS__, 'legacy' ), 5, 4 );
		add_filter( 'rest_dispatch_request', array( __CLASS__, 'guard_final_submit' ), 8, 4 );
	}

	public static function routes( $public ) {
		if ( ! is_object( $public ) ) {
			return;
		}
		self::$public = $public;
		$nonce_cb     = method_exists( $public, 'check_nonce_permission_efb' ) ? array( $public, 'check_nonce_permission_efb' ) : array( __CLASS__, 'nonce_permission' );
		register_rest_route( 'Emsfb/v1', 'forms/payment/start', array(
			'methods'             => 'POST',
			'callback'            => function ( $request ) use ( $public ) {
				return flxf_payment_rest_start( $request, $public );
			},
			'permission_callback' => $nonce_cb,
		) );
		register_rest_route( 'Emsfb/v1', 'forms/payment/confirm', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'confirm' ),
			'permission_callback' => $nonce_cb,
		) );
		register_rest_route( 'Emsfb/v1', 'forms/payment/webhook/(?P<provider>[a-z0-9_-]+)', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'webhook' ),
			'permission_callback' => '__return_true', // each provider authenticates its webhook
		) );
	}

	public static function nonce_permission( $request ) {
		$nonce = $request->get_header( 'x_wp_nonce' );
		if ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return true;
		}
		return new WP_Error( 'rest_forbidden', __( 'Invalid or expired nonce', 'flx-donate' ), array( 'status' => 403 ) );
	}

	/** {success:true, data:{...}}: the envelope Flux Forms's payment scripts read. */
	public static function reply( array $data, $status = 200 ) {
		return new WP_REST_Response( array( 'success' => true, 'data' => $data ), $status, array( 'Cache-Control' => 'no-store' ) );
	}

	public static function rate_limited( $bucket, $default ) {
		$limit = (int) apply_filters( 'flxf_payment_' . $bucket . '_rate_limit', $default );
		if ( $limit <= 0 ) {
			return false;
		}
		$ip    = function_exists( 'flxf_client_ip' ) ? flxf_client_ip() : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		$key   = 'flxb_pay_' . $bucket . '_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return true;
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return false;
	}

	private static function handler() {
		if ( ! class_exists( '\Emsfb\StripeHandlerEfb' ) && defined( 'EMSFB_PLUGIN_DIRECTORY' ) && is_readable( EMSFB_PLUGIN_DIRECTORY . 'vendor/stripe/class-Emsfb-stripe-handler.php' ) ) {
			require_once EMSFB_PLUGIN_DIRECTORY . 'vendor/stripe/class-Emsfb-stripe-handler.php';
		}
		return class_exists( '\Emsfb\StripeHandlerEfb' );
	}

	/**
	 * Start a payment with a provider. The amount is Flux Forms's server price (with the
	 * donation type's prices when the form is linked to one), never a value from the browser.
	 */
	public static function start( $request, $public, $provider ) {
		if ( ! $provider ) {
			return self::reply( array( 'success' => false, 'm' => __( 'No payment provider is available on this site. Please contact the site administrator.', 'flx-donate' ) ) );
		}
		if ( $provider instanceof FLXB_Payment_Provider_Custom_Start ) {
			return $provider->start_request( $request, $public );
		}
		if ( self::rate_limited( 'start', 20 ) ) {
			return self::reply( array( 'success' => false, 'm' => __( 'Too many payment attempts. Please wait a few minutes and try again.', 'flx-donate' ) ), 429 );
		}
		if ( ! self::handler() || ! is_object( $public ) || ! method_exists( $public, 'insert_message_db' ) ) {
			return self::reply( array( 'success' => false, 'm' => __( 'Invalid payment data. Please refresh the page and try again.', 'flx-donate' ) ) );
		}
		$params = $request->get_json_params();
		$check  = \Emsfb\StripeHandlerEfb::validate_card_add( $params, 'stripe' === $provider->id(), $provider->id() );
		if ( true !== $check ) {
			return self::reply( array( 'success' => false, 'm' => $check ) );
		}
		if ( ! $provider->is_configured() ) {
			return self::reply( array( 'success' => false, 'm' => __( 'The payment provider is not configured on this site. Please contact the site administrator.', 'flx-donate' ) ) );
		}
		$form_id = absint( $params['id'] );
		$fs      = \Emsfb\StripeHandlerEfb::load_form_structure( $form_id );
		$rows    = json_decode( str_replace( '\\', '', sanitize_text_field( $params['value'] ) ), true );
		$obj     = \Emsfb\StripeHandlerEfb::server_amount( $public, is_array( $rows ) ? $rows : array(), $fs, false, $form_id );
		$amount  = round( (float) ( $obj['price_total'] ?? 0 ), 2 );
		if ( ! empty( $obj['flxb_error'] ) ) {
			return self::reply( array( 'success' => false, 'm' => (string) $obj['flxb_error'] ) );
		}
		if ( ! is_finite( $amount ) || $amount <= 0 ) {
			return self::reply( array( 'success' => false, 'm' => __( 'No payment amount detected. Please review your selected items and try again. If the problem persists, contact support.', 'flx-donate' ) ) );
		}
		$currency = isset( $fs[0]['currency'] ) && strlen( (string) $fs[0]['currency'] ) > 2 ? strtoupper( (string) $fs[0]['currency'] ) : 'USD';
		$method   = isset( $fs[0]['paymentmethod'] ) ? (string) $fs[0]['paymentmethod'] : 'charge';
		$user     = wp_get_current_user();
		$uid      = $user->exists() ? $user->user_nicename : esc_html__( 'Guest', 'flx-donate' );
		$ip       = function_exists( 'flxf_client_ip' ) ? flxf_client_ip() : $public->get_ip_address();
		$email    = isset( $obj['email'] ) ? sanitize_email( (string) $obj['email'] ) : '';
		$extra    = is_array( $params ) ? array_diff_key( $params, array_flip( array( 'value', 'nonce', 'action', 'amount', 'price', 'total' ) ) ) : array();
		/* Donor details (name, e-mail, phone, consents) for providers that need a buyer (iyzico). */
		$contact  = class_exists( 'FLXB_FLXForm_Bridge' ) && is_array( $rows ) ? FLXB_FLXForm_Bridge::contact_from_rows( $rows, is_array( $fs ) ? $fs : array() ) : array();
		if ( '' === (string) ( $contact['email'] ?? '' ) && '' !== $email ) {
			$contact['email'] = $email;
		}
		$brand    = function_exists( 'flxb_brand' ) ? flxb_brand()['name'] : get_bloginfo( 'name' );
		$meta     = array(
			'form_id'     => $form_id,
			'form_name'   => isset( $fs[0]['formName'] ) ? (string) $fs[0]['formName'] : '',
			'description' => $brand . ' >' . ( $fs[0]['formName'] ?? '' ),
			'method'      => $method,
			'email'       => $email,
			'uid'         => $uid,
			'ip'          => $ip,
			'reference'   => 'flxb_' . strtolower( wp_generate_password( 20, false, false ) ),
			'page_url'    => isset( $params['url'] ) && is_string( $params['url'] ) ? esc_url_raw( $params['url'] ) : '',
			'params'      => $extra,
			'rows'        => isset( $obj['valobj'] ) && is_array( $obj['valobj'] ) ? $obj['valobj'] : array(),
			'contact'     => $contact,
			'flxb'         => ! empty( $obj['flxb'] ) && is_array( $obj['flxb'] ) ? $obj['flxb'] : array(),
		);
		try {
			$result = $provider->create_payment( $amount, $currency, $meta );
		} catch ( \Throwable $e ) {
			$result = FLXB_Payment_Result::failed( $provider->label() . ': ' . $e->getMessage() );
		}
		if ( ! $result instanceof FLXB_Payment_Result ) {
			$result = FLXB_Payment_Result::failed( __( 'The payment could not be started. Please try again.', 'flx-donate' ) );
		}
		if ( ! $result->is_ok() ) {
			return self::reply( array( 'success' => false, 'm' => '' !== $result->message ? $result->message : __( 'The payment could not be started. Please try again.', 'flx-donate' ) ) );
		}

		$rec      = $result->record;
		$is_sub   = 'subscription' === ( $rec['payment_type'] ?? '' );
		$status   = isset( $rec['status'] ) ? sanitize_key( (string) $rec['status'] ) : ( $result->is_paid() ? 'completed' : 'pending' );
		$created  = wp_date( 'Y-m-d-h:i:s' );
		$pay_row  = array(
			'id_'             => 'payment',
			'amount'          => 0,
			'name'            => esc_html__( 'Payment', 'flx-donate' ),
			'type'            => 'payment',
			'value'           => $amount . ' ' . strtolower( $currency ),
			'paymentIntent'   => $result->reference,
			'paymentGateway'  => $provider->id(),
			'paymentmethod'   => $method,
			'paymentAmount'   => $amount,
			'paymentCreated'  => $created,
			'paymentcurrency' => strtolower( $currency ),
			'gateway'         => $provider->id(),
			'uid'             => $uid,
			'status'          => $is_sub ? 'active' : $status,
			'updatetime'      => $created,
			'description'     => $meta['description'],
			'total'           => $amount,
		);
		if ( $is_sub ) {
			$pay_row['interval'] = $method;
			if ( ! empty( $result->client['next'] ) ) {
				$pay_row['nextDate'] = gmdate( 'Y-m-d-h:i:s', (int) $result->client['next'] );
			}
		}
		$priced   = array_values( array_filter( $meta['rows'], function ( $r ) {
			return is_array( $r ) && isset( $r['price'] );
		} ) );
		$priced[] = $pay_row;

		$style = flxf_payment_setting( 'trackCodeStyle', 'date_en_mix' );
		$public->id    = $form_id;
		$public->ip    = $ip;
		$public->name  = isset( $params['name'] ) && is_string( $params['name'] ) ? sanitize_text_field( $params['name'] ) : $meta['form_name'];
		$public->value = str_replace( '"', '\\"', wp_json_encode( $priced, JSON_UNESCAPED_UNICODE ) );
		$track         = $public->insert_message_db( 2, false, $style );

		/* What was given (type, option, quantity, unit price) travels with the payment record. */
		$rmeta = isset( $rec['meta'] ) && is_array( $rec['meta'] ) ? $rec['meta'] : array();
		if ( ! empty( $obj['flxb'] ) && is_array( $obj['flxb'] ) ) {
			$rmeta['flxb'] = $obj['flxb'];
		}
		FLXB_Payment_Records::insert( array_merge(
			array( 'payment_type' => 'one-time' ),
			array_intersect_key( $rec, array_flip( array( 'payment_type', 'capture_id', 'subscription_id', 'plan_id', 'product_id', 'interval_unit' ) ) ),
			array(
				'form_id'        => $form_id,
				'track'          => $track,
				'gateway'        => $provider->id(),
				'transaction_id' => $result->reference,
				'amount'         => $amount,
				'currency'       => $currency,
				'status'         => $status,
				'payer_email'    => $email,
				'uid'            => $uid,
				'ip'             => $ip,
				'form_name'      => $meta['form_name'],
				'meta'           => $rmeta ? $rmeta : null,
			)
		) );
		do_action( 'flxb_payment_started', $track, $provider->id(), $result );

		$data = array(
			'success'   => true,
			'provider'  => $provider->id(),
			'status'    => $result->status,
			'client'    => $result->client,
			'id'        => $track,
			'transStat' => $pay_row,
			'uid'       => $uid,
		);
		if ( ! empty( $result->client['client_secret'] ) ) {
			$data['client_secret'] = (string) $result->client['client_secret']; // stripe_pay-efb.js reads it here
		}
		return self::reply( $data );
	}

	/** POST forms/payment/confirm {track, ...}. */
	public static function confirm( $request ) {
		$p     = $request->get_json_params();
		$p     = is_array( $p ) ? $p : array();
		$track = isset( $p['track'] ) && is_scalar( $p['track'] ) ? sanitize_text_field( (string) $p['track'] ) : '';
		if ( self::rate_limited( 'confirm', 60 ) ) {
			return self::reply( array( 'success' => false, 'm' => __( 'Too many requests. Please wait a moment and try again.', 'flx-donate' ) ), 429 );
		}
		$row      = '' !== $track ? FLXB_Payment_Records::get_by_track( $track ) : null;
		$provider = $row ? flxf_payment_provider( $row->gateway ) : null;
		if ( ! $row || ! $provider ) {
			return self::reply( array( 'success' => false, 'm' => __( 'Payment not found.', 'flx-donate' ) ), 404 );
		}
		$result = $provider->verify( $row, array_diff_key( $p, array( 'track' => 1 ) ) );
		if ( $result->is_paid() && 'completed' !== $row->status && 'subscription' !== $row->payment_type ) {
			FLXB_Payment_Records::update( $row->id, array( 'status' => 'completed' ) );
		}
		return self::reply( array(
			'success' => $result->is_paid(),
			'status'  => $result->status,
			'trackid' => $row->track,
			'm'       => $result->is_paid() ? '' : ( '' !== $result->message ? $result->message : __( 'The payment could not be verified yet.', 'flx-donate' ) ),
		) );
	}

	public static function webhook( $request ) {
		$provider = flxf_payment_provider( (string) $request->get_param( 'provider' ) );
		if ( ! $provider ) {
			return new WP_REST_Response( array( 'error' => 'unknown_provider' ), 404 );
		}
		$res = $provider->handle_webhook( $request );
		return $res instanceof WP_REST_Response ? $res : new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/** Flux Forms's Stripe routes, answered by Flux Donate (same shapes). Runs after the permission check. */
	public static function legacy( $result, $request, $route, $handler ) {
		if ( null !== $result || ! is_string( $route ) ) {
			return $result;
		}
		$key = '/' . trim( $route, '/' );
		$fn  = null;
		foreach ( self::LEGACY as $r => $m ) {
			if ( 0 === strcasecmp( $key, $r ) ) {
				$fn = $m;
			}
		}
		if ( null === $fn ) {
			return $result;
		}
		$stripe = flxf_payment_provider( 'stripe' );
		switch ( $fn ) {
			case 'card_add':
				if ( ! is_object( self::$public ) ) {
					return $result; // Flux Forms answers itself
				}
				return self::start( $request, self::$public, flxf_payment_active_provider() );
			case 'stripe_confirm':
				$p     = (array) $request->get_json_params();
				$pi    = isset( $p['paymentIntentId'] ) && is_string( $p['paymentIntentId'] ) ? sanitize_text_field( $p['paymentIntentId'] ) : '';
				$track = isset( $p['trackid'] ) && is_scalar( $p['trackid'] ) ? sanitize_text_field( (string) $p['trackid'] ) : '';
				if ( ! preg_match( '/^pi_[A-Za-z0-9_]+$/', $pi ) ) {
					return self::reply( array( 'success' => false, 'm' => __( 'Payment Intent ID is missing', 'flx-donate' ) ), 400 );
				}
				/* Only the PaymentIntent created for this entry counts. */
				$row = FLXB_Payment_Records::get_by_reference( 'stripe', $pi );
				if ( ! $row || ! $stripe || ( '' !== $track && (string) $row->track !== $track ) ) {
					return self::reply( array( 'success' => false, 'm' => __( 'Payment not found.', 'flx-donate' ) ), 404 );
				}
				$ok = $stripe->verify( $row )->is_paid();
				return self::reply( array( 'success' => $ok, 'paymentIntentId' => $pi, 'trackid' => $row->track, 'm' => $ok ? '' : __( 'The payment could not be verified yet.', 'flx-donate' ) ) );
			case 'stripe_pkey':
				return self::reply( array( 'key' => $stripe instanceof FLXB_Payment_Stripe ? $stripe->publishable_key() : '' ) );
			case 'stripe_webhook':
				return $stripe ? $stripe->handle_webhook( $request ) : new WP_REST_Response( array( 'error' => 'webhook_not_configured' ), 503 );
		}
		return $result;
	}

	public static function block_submit() {
		return self::reply( array(
			'success' => false,
			'm'       => __( 'Your payment has not been confirmed yet. Please wait a moment and try again. If the problem persists, contact the site administrator.', 'flx-donate' ),
		) );
	}

	/**
	 * Final submit of a paid form (forms/message/add, type=payment): only after the provider that
	 * took the payment verified it for this entry. Runs after the route's permission check.
	 */
	public static function guard_final_submit( $result, $request, $route, $handler ) {
		if ( null !== $result || ! is_string( $route ) || 0 !== strcasecmp( '/' . trim( $route, '/' ), '/Emsfb/v1/forms/message/add' ) ) {
			return $result;
		}
		$p = $request->get_json_params();
		if ( ! is_array( $p ) || ! isset( $p['type'] ) || 'payment' !== $p['type'] ) {
			return $result;
		}
		$track = isset( $p['payid'] ) && is_scalar( $p['payid'] ) ? sanitize_text_field( (string) $p['payid'] ) : '';
		if ( '' === $track || ! self::handler() ) {
			return $result; // the core rejects a payment submit without a pending entry
		}
		global $wpdb;
		$mt      = $wpdb->prefix . 'emsfb_msg_';
		$form_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT form_id FROM `{$mt}` WHERE track = %s AND read_ = %d LIMIT 1", $track, 2 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 0 === $form_id ) {
			return $result;
		}
		/* Only forms with the card payment box are ours (PayPal and others guard themselves). */
		if ( ! \Emsfb\StripeHandlerEfb::is_stripe_form( \Emsfb\StripeHandlerEfb::load_form_structure( $form_id ) ) ) {
			return $result;
		}
		$row      = FLXB_Payment_Records::get_by_track( $track );
		$provider = $row ? flxf_payment_provider( $row->gateway ) : null;
		if ( ! $row || ! $provider || (int) $row->form_id !== $form_id ) {
			return self::block_submit();
		}
		$verified = $provider->verify( $row, array() );
		if ( ! $verified->is_paid() ) {
			return self::block_submit();
		}
		if ( 'completed' !== $row->status && 'subscription' !== $row->payment_type ) {
			FLXB_Payment_Records::update( $row->id, array( 'status' => 'completed' ) );
		}
		return $result;
	}
}

/** POST Emsfb/v1/forms/payment/start (also called by Flux Forms's own Stripe handler). */
function flxf_payment_rest_start( $request, $public ) {
	return FLXB_Payments_Rest::start( $request, $public, flxf_payment_active_provider() );
}

function flxf_payment_reply( array $data, $status = 200 ) {
	return FLXB_Payments_Rest::reply( $data, $status );
}

FLXB_Payments_Rest::init();

/* ======================================================================
 * Front-end wiring
 * ==================================================================== */

function flxb_payments_asset_ver( $file ) {
	$p = __DIR__ . '/assets/' . $file;
	return is_readable( $p ) ? (string) filemtime( $p ) : FLXB_VERSION;
}

/**
 * Wherever Flux Forms prints its payment box script (stripe_js: public form, builder preview,
 * panel), load window.FLXFPay first and, on the public form, the active provider's assets.
 */
function flxb_payments_wire_scripts() {
	$scripts = wp_scripts();
	$box     = $scripts->query( 'stripe_js', 'registered' );
	if ( ! $box ) {
		return;
	}
	if ( ! wp_script_is( 'flxf-pay', 'registered' ) ) {
		wp_register_script( 'flxf-pay', FLXB_URL . 'providers/assets/flxf-pay.js', array( 'jquery' ), flxb_payments_asset_ver( 'flxf-pay.js' ), true );
	}
	if ( ! in_array( 'flxf-pay', $box->deps, true ) ) {
		$box->deps[] = 'flxf-pay';
	}
	static $done = false;
	if ( $done || ! wp_script_is( 'stripe_js', 'enqueued' ) ) {
		return;
	}
	$done     = true;
	$id       = flxf_payment_active_provider_id();
	$provider = flxf_payment_provider( $id );
	wp_add_inline_script(
		'flxf-pay',
		'window.flxfPayCfg = ' . wp_json_encode(
			array(
				'provider'    => $id,
				'label'       => $provider ? $provider->label() : '',
				'configured'  => $provider && $provider->is_configured(),
				'unavailable' => __( 'Online payment is not available at the moment. Please try again later or contact us.', 'flx-donate' ),
			)
		) . ';',
		'before'
	);
	if ( is_admin() || ! $provider ) {
		return;
	}
	if ( 'stripe' !== $id ) {
		wp_dequeue_script( 'stripe-js' ); // no request to js.stripe.com
	} elseif ( $provider instanceof FLXB_Payment_Stripe ) {
		/* A publishable key saved on Donations › Payments wins over the one in Flux Forms's settings. */
		$o = flxb_payment_options();
		if ( ! empty( $o['fields']['stripePKey'] ) ) {
			wp_add_inline_script( 'stripe_js', '(function(k){function s(){if(window.ajax_object_efm){window.ajax_object_efm.paymentKey=k;}}s();document.addEventListener("DOMContentLoaded",s);})(' . wp_json_encode( (string) $o['fields']['stripePKey'] ) . ');', 'before' );
		}
	}
	$assets = (array) $provider->front_assets();
	foreach ( (array) ( $assets['styles'] ?? array() ) as $handle => $spec ) {
		if ( ! empty( $spec['src'] ) ) {
			wp_enqueue_style( $handle, $spec['src'], (array) ( $spec['deps'] ?? array() ), $spec['ver'] ?? null );
		}
	}
	foreach ( (array) ( $assets['scripts'] ?? array() ) as $handle => $spec ) {
		if ( empty( $spec['src'] ) ) {
			continue;
		}
		wp_register_script( $handle, $spec['src'], array_merge( array( 'flxf-pay' ), (array) ( $spec['deps'] ?? array() ) ), $spec['ver'] ?? null, true );
		foreach ( (array) ( $spec['data'] ?? array() ) as $object => $value ) {
			wp_localize_script( $handle, (string) $object, (array) $value );
		}
		if ( ! in_array( $handle, $box->deps, true ) ) {
			$box->deps[] = $handle;
		}
	}
}
add_action( 'wp_print_footer_scripts', 'flxb_payments_wire_scripts', 1 );
add_action( 'admin_print_footer_scripts', 'flxb_payments_wire_scripts', 1 );
