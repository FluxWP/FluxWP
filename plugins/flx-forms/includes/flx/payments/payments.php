<?php
/**
 * Flux Forms: provider-agnostic payments.
 *
 *  - Contract: FLXF_Payment_Provider (interface-flxf-payment-provider.php), base class
 *    FLXF_Payment_Provider_Base, normalized FLXF_Payment_Result.
 *  - Registry: flxf_payment_providers() + filter `flxf_payment_providers`.
 *  - Active provider: Settings › Payments ("flxfPayProvider" in the plugin settings row), default Stripe.
 *  - REST (all under Emsfb/v1):
 *      POST forms/payment/quote              server-authoritative amount (vendor/stripe handler)
 *      POST forms/payment/start              start a payment with the active provider
 *      POST forms/payment/stripe/card/add    legacy Stripe start; dispatches to start for other providers
 *      POST forms/payment/confirm            verify a stored payment with its provider
 *      POST forms/payment/webhook/{provider} provider webhooks (provider authenticates)
 *  - Front: assets/flxf-pay.js (window.FLXFPay) holds the quote + Pay button gating;
 *    provider scripts register into it (Stripe: public/assets/js/stripe_pay-efb.js).
 *  - Records: {$wpdb->prefix}emsfb_pay_ (gateway = provider id), same as Stripe payments.
 *
 * Developer guide: docs/PAYMENT-PROVIDERS.md
 *
 * Part of Flux Forms (GPL v2 or later), a fork of Easy Form Builder 4.2.0 by WhiteStudio.
 *
 * @package FLX_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/interface-flxf-payment-provider.php';
require_once __DIR__ . '/class-flxf-payment-result.php';
require_once __DIR__ . '/class-flxf-payment-provider-base.php';
require_once __DIR__ . '/class-flxf-payment-records.php';
require_once __DIR__ . '/providers/class-flxf-payment-stripe.php';
require_once __DIR__ . '/providers/class-flxf-payment-test.php';
require_once __DIR__ . '/admin-page.php';

/** Settings key that stores the active provider id. */
const FLXF_PAY_PROVIDER_SETTING = 'flxfPayProvider';

/* ======================================================================
 * Registry
 * ==================================================================== */

/**
 * All registered providers, keyed by id.
 *
 * Register one with:
 *     add_filter( 'flxf_payment_providers', function ( $providers ) {
 *         $providers['iyzico'] = 'My_Iyzico_Provider'; // class name or instance
 *         return $providers;
 *     } );
 *
 * @return FLXF_Payment_Provider[]
 */
function flxf_payment_providers() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$raw = apply_filters( 'flxf_payment_providers', array( 'stripe' => 'FLXF_Payment_Stripe' ) );
	$out = array();
	foreach ( (array) $raw as $item ) {
		if ( is_string( $item ) && class_exists( $item ) ) {
			$item = new $item();
		}
		if ( ! $item instanceof FLXF_Payment_Provider ) {
			continue;
		}
		$id = sanitize_key( (string) $item->id() );
		if ( '' !== $id && ! isset( $out[ $id ] ) ) {
			$out[ $id ] = $item;
		}
	}
	/* Cache once plugins had their chance to hook the filter. */
	if ( did_action( 'init' ) ) {
		$cache = $out;
	}
	return $out;
}

/** @return FLXF_Payment_Provider|null */
function flxf_payment_provider( $id ) {
	$all = flxf_payment_providers();
	$id  = sanitize_key( (string) $id );
	return isset( $all[ $id ] ) ? $all[ $id ] : null;
}

/** Read a value from the plugin settings row ('' when missing). */
function flxf_payment_setting( $key, $default = '' ) {
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
	foreach ( $rows as $s ) {
		if ( is_object( $s ) && isset( $s->$key ) && is_scalar( $s->$key ) ) {
			$v = trim( (string) $s->$key );
			if ( '' !== $v && 'null' !== $v ) {
				return $v;
			}
		}
	}
	return $default;
}

/** Id of the active provider (falls back to Stripe when the saved one is gone). */
function flxf_payment_active_provider_id() {
	$all = flxf_payment_providers();
	$id  = sanitize_key( flxf_payment_setting( FLXF_PAY_PROVIDER_SETTING, 'stripe' ) );
	if ( ! isset( $all[ $id ] ) ) {
		$id = isset( $all['stripe'] ) ? 'stripe' : (string) key( $all );
	}
	return (string) apply_filters( 'flxf_payment_active_provider', $id );
}

/** @return FLXF_Payment_Provider|null */
function flxf_payment_active_provider() {
	return flxf_payment_provider( flxf_payment_active_provider_id() );
}

/** Provider that took the payment behind a confirmation code ('' when none). */
function flxf_payment_track_gateway( $track ) {
	$row = FLXF_Payment_Records::get_by_track( (string) $track );
	return $row ? (string) $row->gateway : '';
}

/* ======================================================================
 * REST
 * ==================================================================== */

add_action( 'efb_register_payment_rest_routes', 'flxf_payments_register_routes', 20, 1 );

function flxf_payments_register_routes( $public ) {
	if ( ! is_object( $public ) ) {
		return;
	}
	$nonce_cb = method_exists( $public, 'check_nonce_permission_efb' ) ? array( $public, 'check_nonce_permission_efb' ) : 'flxf_payments_nonce_permission';
	register_rest_route( 'Emsfb/v1', 'forms/payment/start', array(
		'methods'             => 'POST',
		'callback'            => function ( $request ) use ( $public ) {
			return flxf_payment_rest_start( $request, $public );
		},
		'permission_callback' => $nonce_cb,
	) );
	register_rest_route( 'Emsfb/v1', 'forms/payment/confirm', array(
		'methods'             => 'POST',
		'callback'            => 'flxf_payment_rest_confirm',
		'permission_callback' => $nonce_cb,
	) );
	register_rest_route( 'Emsfb/v1', 'forms/payment/webhook/(?P<provider>[a-z0-9_-]+)', array(
		'methods'             => 'POST',
		'callback'            => 'flxf_payment_rest_webhook',
		/* Each provider authenticates its webhook (signature) itself. */
		'permission_callback' => '__return_true',
	) );
}

function flxf_payments_nonce_permission( $request ) {
	$nonce = $request->get_header( 'x_wp_nonce' );
	if ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return true;
	}
	return new WP_Error( 'rest_forbidden', __( 'Invalid or expired nonce', 'flx-forms' ), array( 'status' => 403 ) );
}

/** Same envelope the legacy Stripe endpoint answers with ({success:true, data:{...}}). */
function flxf_payment_reply( array $data, $status = 200 ) {
	return new WP_REST_Response( array( 'success' => true, 'data' => $data ), $status, array( 'Cache-Control' => 'no-store' ) );
}

function flxf_payment_rate_limited( $bucket, $default ) {
	$limit = (int) apply_filters( 'flxf_payment_' . $bucket . '_rate_limit', $default );
	if ( $limit <= 0 ) {
		return false;
	}
	$ip    = function_exists( 'flxf_client_ip' ) ? flxf_client_ip() : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
	$key   = 'flxf_pay_' . $bucket . '_' . md5( $ip );
	$count = (int) get_transient( $key );
	if ( $count >= $limit ) {
		return true;
	}
	set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
	return false;
}

/**
 * POST Emsfb/v1/forms/payment/start
 * Body: {id, value, name, url?, ...provider extras}. The amount is never read from the body.
 */
function flxf_payment_rest_start( $request, $public ) {
	$provider = flxf_payment_active_provider();
	if ( ! $provider ) {
		return flxf_payment_reply( array( 'success' => false, 'm' => __( 'No payment provider is available on this site. Please contact the site administrator.', 'flx-forms' ) ) );
	}
	if ( $provider instanceof FLXF_Payment_Provider_Custom_Start ) {
		return $provider->start_request( $request, $public );
	}
	if ( flxf_payment_rate_limited( 'start', 20 ) ) {
		return flxf_payment_reply( array( 'success' => false, 'm' => __( 'Too many payment attempts. Please wait a few minutes and try again.', 'flx-forms' ) ), 429 );
	}
	if ( ! class_exists( '\Emsfb\StripeHandlerEfb' ) && is_readable( EMSFB_PLUGIN_DIRECTORY . 'vendor/stripe/class-Emsfb-stripe-handler.php' ) ) {
		require_once EMSFB_PLUGIN_DIRECTORY . 'vendor/stripe/class-Emsfb-stripe-handler.php';
	}
	if ( ! class_exists( '\Emsfb\StripeHandlerEfb' ) || ! is_object( $public ) || ! method_exists( $public, 'insert_message_db' ) ) {
		return flxf_payment_reply( array( 'success' => false, 'm' => __( 'Invalid payment data. Please refresh the page and try again.', 'flx-forms' ) ) );
	}
	$params = $request->get_json_params();
	$check  = \Emsfb\StripeHandlerEfb::validate_card_add( $params, false, $provider->id() );
	if ( true !== $check ) {
		return flxf_payment_reply( array( 'success' => false, 'm' => $check ) );
	}
	if ( ! $provider->is_configured() ) {
		return flxf_payment_reply( array( 'success' => false, 'm' => __( 'The payment provider is not configured on this site. Please contact the site administrator.', 'flx-forms' ) ) );
	}

	$form_id = absint( $params['id'] );
	$fs      = \Emsfb\StripeHandlerEfb::load_form_structure( $form_id );
	$rows    = json_decode( str_replace( '\\', '', sanitize_text_field( $params['value'] ) ), true );
	$obj     = \Emsfb\StripeHandlerEfb::server_amount( $public, is_array( $rows ) ? $rows : array(), $fs, false, $form_id );
	$amount  = round( (float) ( $obj['price_total'] ?? 0 ), 2 );
	if ( ! is_finite( $amount ) || $amount <= 0 ) {
		return flxf_payment_reply( array( 'success' => false, 'm' => __( 'No payment amount detected. Please review your selected items and try again. If the problem persists, contact support.', 'flx-forms' ) ) );
	}
	$currency = isset( $fs[0]['currency'] ) && strlen( (string) $fs[0]['currency'] ) > 2 ? strtoupper( (string) $fs[0]['currency'] ) : 'USD';
	$method   = isset( $fs[0]['paymentmethod'] ) ? (string) $fs[0]['paymentmethod'] : 'charge';
	$user     = wp_get_current_user();
	$uid      = $user->exists() ? $user->user_nicename : esc_html__( 'Guest', 'flx-forms' );
	$ip       = function_exists( 'flxf_client_ip' ) ? flxf_client_ip() : $public->get_ip_address();
	$email    = isset( $obj['email'] ) ? sanitize_email( (string) $obj['email'] ) : '';
	$extra    = is_array( $params ) ? array_diff_key( $params, array_flip( array( 'value', 'nonce', 'action', 'amount', 'price', 'total' ) ) ) : array();

	$meta = array(
		'form_id'     => $form_id,
		'form_name'   => isset( $fs[0]['formName'] ) ? (string) $fs[0]['formName'] : '',
		'description' => get_bloginfo( 'name' ) . ' >' . ( $fs[0]['formName'] ?? '' ),
		'method'      => $method,
		'email'       => $email,
		'uid'         => $uid,
		'ip'          => $ip,
		'reference'   => 'flxf_' . strtolower( wp_generate_password( 20, false, false ) ),
		'page_url'    => isset( $params['url'] ) && is_string( $params['url'] ) ? esc_url_raw( $params['url'] ) : '',
		'params'      => $extra,
		'rows'        => isset( $obj['valobj'] ) && is_array( $obj['valobj'] ) ? $obj['valobj'] : array(),
	);

	try {
		$result = $provider->create_payment( $amount, $currency, $meta );
	} catch ( \Throwable $e ) {
		$result = FLXF_Payment_Result::failed( $provider->label() . ': ' . $e->getMessage() );
	}
	if ( ! $result instanceof FLXF_Payment_Result ) {
		$result = FLXF_Payment_Result::failed( __( 'The payment could not be started. Please try again.', 'flx-forms' ) );
	}
	if ( ! $result->is_ok() ) {
		return flxf_payment_reply( array( 'success' => false, 'm' => '' !== $result->message ? $result->message : __( 'The payment could not be started. Please try again.', 'flx-forms' ) ) );
	}

	/* Pending entry (read_=2) with the priced rows + one payment row, the shape the
	 * core's final submit (forms/message/add, type=payment) and the response viewer read. */
	$created = wp_date( 'Y-m-d-h:i:s' );
	$pay_row = array(
		'id_'             => 'payment',
		'amount'          => 0,
		'name'            => esc_html__( 'Payment', 'flx-forms' ),
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
		'status'          => $result->is_paid() ? 'completed' : 'pending',
		'updatetime'      => $created,
		'description'     => $meta['description'],
		'total'           => $amount,
	);
	$priced  = array_values( array_filter( $meta['rows'], function ( $r ) {
		return is_array( $r ) && isset( $r['price'] );
	} ) );
	$priced[] = $pay_row;

	$style = 'date_en_mix';
	$set   = flxf_payment_setting( 'trackCodeStyle' );
	if ( '' !== $set ) {
		$style = $set;
	}
	$public->id    = $form_id;
	$public->ip    = $ip;
	$public->name  = isset( $params['name'] ) && is_string( $params['name'] ) ? sanitize_text_field( $params['name'] ) : $meta['form_name'];
	$public->value = str_replace( '"', '\\"', wp_json_encode( $priced, JSON_UNESCAPED_UNICODE ) );
	$track         = $public->insert_message_db( 2, false, $style );

	FLXF_Payment_Records::insert( array_merge(
		array( 'payment_type' => 'one-time' ),
		array_intersect_key( $result->record, array_flip( array( 'payment_type', 'capture_id', 'subscription_id', 'plan_id', 'product_id', 'interval_unit', 'meta' ) ) ),
		array(
			'form_id'        => $form_id,
			'track'          => $track,
			'gateway'        => $provider->id(),
			'transaction_id' => $result->reference,
			'amount'         => $amount,
			'currency'       => $currency,
			'status'         => $result->is_paid() ? 'completed' : 'pending',
			'payer_email'    => $email,
			'uid'            => $uid,
			'ip'             => $ip,
			'form_name'      => $meta['form_name'],
		)
	) );

	return flxf_payment_reply( array(
		'success'   => true,
		'provider'  => $provider->id(),
		'status'    => $result->status,
		'client'    => $result->client,
		'id'        => $track,
		'transStat' => $pay_row,
	) );
}

/**
 * POST Emsfb/v1/forms/payment/confirm
 * Body: {track, ...provider extras}. Verifies the stored payment with its own provider.
 */
function flxf_payment_rest_confirm( $request ) {
	$p     = $request->get_json_params();
	$p     = is_array( $p ) ? $p : array();
	$track = isset( $p['track'] ) && is_scalar( $p['track'] ) ? sanitize_text_field( (string) $p['track'] ) : '';
	if ( flxf_payment_rate_limited( 'confirm', 60 ) ) {
		return flxf_payment_reply( array( 'success' => false, 'm' => __( 'Too many requests. Please wait a moment and try again.', 'flx-forms' ) ), 429 );
	}
	$row      = '' !== $track ? FLXF_Payment_Records::get_by_track( $track ) : null;
	$provider = $row ? flxf_payment_provider( $row->gateway ) : null;
	if ( ! $row || ! $provider ) {
		return flxf_payment_reply( array( 'success' => false, 'm' => __( 'Payment not found.', 'flx-forms' ) ), 404 );
	}
	$result = $provider->verify( $row, array_diff_key( $p, array( 'track' => 1 ) ) );
	if ( $result->is_paid() && 'completed' !== $row->status ) {
		FLXF_Payment_Records::update( $row->id, array( 'status' => 'completed' ) );
	}
	return flxf_payment_reply( array(
		'success' => $result->is_paid(),
		'status'  => $result->status,
		'trackid' => $row->track,
		'm'       => $result->is_paid() ? '' : ( '' !== $result->message ? $result->message : __( 'The payment could not be verified yet.', 'flx-forms' ) ),
	) );
}

/** POST Emsfb/v1/forms/payment/webhook/{provider} */
function flxf_payment_rest_webhook( $request ) {
	$provider = flxf_payment_provider( (string) $request->get_param( 'provider' ) );
	if ( ! $provider ) {
		return new WP_REST_Response( array( 'error' => 'unknown_provider' ), 404 );
	}
	$res = $provider->handle_webhook( $request );
	return $res instanceof WP_REST_Response ? $res : new WP_REST_Response( array( 'received' => true ), 200 );
}

/**
 * Final submit guard for payments taken by a provider other than Stripe (Stripe
 * keeps its own guard in vendor/stripe). Runs after the route's permission check.
 */
function flxf_payment_guard_final_submit( $result, $request, $route, $handler ) {
	if ( null !== $result || ! is_string( $route ) || 0 !== strcasecmp( rtrim( $route, '/' ), '/Emsfb/v1/forms/message/add' ) ) {
		return $result;
	}
	$p = $request->get_json_params();
	if ( ! is_array( $p ) || ! isset( $p['type'] ) || 'payment' !== $p['type'] ) {
		return $result;
	}
	$track = isset( $p['payid'] ) && is_scalar( $p['payid'] ) ? sanitize_text_field( (string) $p['payid'] ) : '';
	if ( '' === $track ) {
		return $result;
	}
	$row = FLXF_Payment_Records::get_by_track( $track );
	if ( $row ) {
		$gateway = (string) $row->gateway;
	} else {
		/* No record: only our business when a non-Stripe provider is active. */
		$gateway = flxf_payment_active_provider_id();
	}
	if ( 'stripe' === $gateway || '' === $gateway ) {
		return $result;
	}
	$provider = flxf_payment_provider( $gateway );
	if ( ! $provider ) {
		/* A record from a provider that is no longer installed. */
		return flxf_payment_block_submit();
	}
	global $wpdb;
	$mt          = $wpdb->prefix . 'emsfb_msg_';
	$msg_form_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT form_id FROM `{$mt}` WHERE track = %s AND read_ = %d LIMIT 1", $track, 2 ) );
	if ( 0 === $msg_form_id ) {
		return $result; // the core rejects an unknown pending entry itself
	}
	if ( ! $row || (int) $row->form_id !== $msg_form_id ) {
		return flxf_payment_block_submit();
	}
	$verified = $provider->verify( $row, array() );
	if ( $verified->is_paid() ) {
		if ( 'completed' !== $row->status ) {
			FLXF_Payment_Records::update( $row->id, array( 'status' => 'completed' ) );
		}
		return $result;
	}
	return flxf_payment_block_submit();
}
add_filter( 'rest_dispatch_request', 'flxf_payment_guard_final_submit', 9, 4 );

function flxf_payment_block_submit() {
	return new WP_REST_Response( array(
		'success' => true,
		'data'    => array(
			'success' => false,
			'm'       => __( 'Your payment has not been confirmed yet. Please wait a moment and try again. If the problem persists, contact the site administrator.', 'flx-forms' ),
		),
	), 200 );
}

/* ======================================================================
 * Front-end and admin assets
 * ==================================================================== */

function flxf_payments_asset_ver( $file ) {
	$p = __DIR__ . '/assets/' . $file;
	return is_readable( $p ) ? (string) filemtime( $p ) : FLXF_FORK_VERSION;
}

/**
 * Wherever EFB loads the payment box script (stripe_js: public form, builder
 * preview, panel), load the neutral layer first and, on the public form, the
 * active provider's own assets.
 */
function flxf_payments_wire_scripts() {
	$scripts = wp_scripts();
	$box     = $scripts->query( 'stripe_js', 'registered' );
	if ( ! $box ) {
		return;
	}
	if ( ! wp_script_is( 'flxf-pay', 'registered' ) ) {
		wp_register_script( 'flxf-pay', plugins_url( 'assets/flxf-pay.js', __FILE__ ), array( 'jquery' ), flxf_payments_asset_ver( 'flxf-pay.js' ), true );
	}
	if ( ! in_array( 'flxf-pay', $box->deps, true ) ) {
		$box->deps[] = 'flxf-pay';
	}
	static $done = false;
	if ( $done || ! wp_script_is( 'stripe_js', 'enqueued' ) ) {
		return;
	}
	$done = true;

	$id       = flxf_payment_active_provider_id();
	$provider = flxf_payment_provider( $id );
	$cfg      = array(
		'provider'    => $id,
		'label'       => $provider ? $provider->label() : '',
		'configured'  => $provider && $provider->is_configured(),
		'unavailable' => __( 'Online payment is not available at the moment. Please try again later or contact us.', 'flx-forms' ),
	);
	wp_add_inline_script( 'flxf-pay', 'window.flxfPayCfg = ' . wp_json_encode( $cfg ) . ';', 'before' );

	if ( is_admin() || ! $provider ) {
		return;
	}
	if ( 'stripe' !== $id ) {
		/* Another provider is active: no request to js.stripe.com. */
		wp_dequeue_script( 'stripe-js' );
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
		if ( ! empty( $spec['data'] ) && is_array( $spec['data'] ) ) {
			foreach ( $spec['data'] as $object => $value ) {
				wp_localize_script( $handle, (string) $object, (array) $value );
			}
		}
		if ( ! in_array( $handle, $box->deps, true ) ) {
			$box->deps[] = $handle;
		}
	}
}
/* Footer scripts print at priority 10 of these hooks. */
add_action( 'wp_print_footer_scripts', 'flxf_payments_wire_scripts', 1 );
add_action( 'admin_print_footer_scripts', 'flxf_payments_wire_scripts', 1 );

/** Settings › Payments: provider choice + each provider's settings fields. */
function flxf_payments_admin_settings_assets() {
	if ( ! wp_script_is( 'Emsfb-list_form-efb-js', 'enqueued' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$providers = array();
	foreach ( flxf_payment_providers() as $id => $p ) {
		$fields = array();
		foreach ( (array) $p->settings_fields() as $f ) {
			if ( empty( $f['key'] ) || ! preg_match( '/^[A-Za-z][A-Za-z0-9_]*$/', (string) $f['key'] ) ) {
				continue;
			}
			$type     = in_array( $f['type'] ?? 'text', array( 'text', 'password', 'select', 'checkbox' ), true ) ? $f['type'] : 'text';
			$value    = flxf_payment_setting( $f['key'] );
			$fields[] = array(
				'key'      => $f['key'],
				'label'    => (string) ( $f['label'] ?? $f['key'] ),
				'type'     => $type,
				'options'  => 'select' === $type ? (array) ( $f['options'] ?? array() ) : array(),
				'help'     => (string) ( $f['help'] ?? '' ),
				'required' => ! empty( $f['required'] ),
				/* Secrets are never echoed back; an empty password input keeps the saved value. */
				'value'    => 'password' === $type ? '' : $value,
				'saved'    => '' !== $value,
			);
		}
		$providers[] = array(
			'id'         => $id,
			'label'      => $p->label(),
			'configured' => (bool) $p->is_configured(),
			'fields'     => $fields,
		);
	}
	wp_enqueue_script( 'flxf-pay-admin', plugins_url( 'assets/flxf-pay-admin.js', __FILE__ ), array( 'Emsfb-list_form-efb-js' ), flxf_payments_asset_ver( 'flxf-pay-admin.js' ), true );
	wp_localize_script( 'flxf-pay-admin', 'flxfPayAdmin', array(
		'settingKey' => FLXF_PAY_PROVIDER_SETTING,
		'active'     => flxf_payment_active_provider_id(),
		'providers'  => $providers,
		'i18n'       => array(
			'activeProvider' => __( 'Active payment provider', 'flx-forms' ),
			'activeHelp'     => __( 'Payment forms take card payments through this provider. Only installed providers are listed.', 'flx-forms' ),
			'configured'     => __( 'Configured', 'flx-forms' ),
			'notConfigured'  => __( 'Not configured', 'flx-forms' ),
			'active'         => __( 'Active', 'flx-forms' ),
			'savedSecret'    => __( 'Saved — leave empty to keep', 'flx-forms' ),
			'settingsOf'     => __( '%s settings', 'flx-forms' ),
		),
	) );
}
add_action( 'admin_print_footer_scripts', 'flxf_payments_admin_settings_assets', 1 );
