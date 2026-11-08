<?php
/**
 * Stripe add-on (AdnSPF): REST endpoints, server-side payment verification,
 * final-submit guard and webhook.
 *
 * Flow (charge):
 *   1. POST Emsfb/v1/forms/payment/stripe/card/add   -> card_add(): validates the
 *      submitted payment rows, then delegates to core
 *      _Public::pay_stripe_sub_Emsfb_api(), which prices the order from the
 *      stored form definition, creates the PaymentIntent (through the
 *      stripe-php shim), stores the pending entry (read_=2) and calls
 *      StripePayment::insert_payment().
 *   2. Browser: stripe.confirmCardPayment(client_secret).
 *   3. POST .../stripe/confirm -> confirm(): retrieves the PaymentIntent from
 *      Stripe and marks the row completed only if status, amount and currency
 *      match what the server recorded.
 *   4. POST Emsfb/v1/forms/message/add (type=payment) -> guard_final_submit()
 *      refuses to finalise a Stripe form entry whose payment is not verified.
 *   Webhook (optional) keeps statuses in sync (refunds, failures, renewals).
 *
 * @package Easy_Form_Builder
 */

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-Emsfb-stripe-api.php';
require_once __DIR__ . '/class-Emsfb-stripe-payment.php';

if ( ! class_exists( '\Emsfb\StripeHandlerEfb' ) ) {

	class StripeHandlerEfb {

		const ROUTE_NS          = 'Emsfb/v1';
		const PAYMENT_METHODS   = array( 'charge', 'day', 'week', 'month', 'year' );
		const SUB_OK_STATUSES   = array( 'active', 'trialing' );

		/* ------------------------------------------------------------------
		 * Wiring
		 * ------------------------------------------------------------------ */

		public static function boot() {
			add_action( 'efb_register_payment_rest_routes', array( __CLASS__, 'register_routes' ), 10, 1 );
			add_filter( 'rest_dispatch_request', array( __CLASS__, 'guard_final_submit' ), 10, 4 );
		}

		/**
		 * @param object $public The core \Emsfb\_Public instance (passed by do_action).
		 */
		public static function register_routes( $public ) {
			if ( ! is_object( $public ) ) {
				return;
			}
			$nonce_cb = method_exists( $public, 'check_nonce_permission_efb' )
				? array( $public, 'check_nonce_permission_efb' )
				: array( __CLASS__, 'fallback_nonce_permission' );

			register_rest_route( self::ROUTE_NS, 'forms/payment/stripe/card/add', array(
				'methods'             => 'POST',
				'callback'            => function ( $request ) use ( $public ) {
					return StripeHandlerEfb::card_add( $request, $public );
				},
				'permission_callback' => $nonce_cb,
			) );
			/* Server-side price quote: the browser shows this amount, never its own sum. */
			register_rest_route( self::ROUTE_NS, 'forms/payment/quote', array(
				'methods'             => 'POST',
				'callback'            => function ( $request ) use ( $public ) {
					return StripeHandlerEfb::quote( $request, $public );
				},
				'permission_callback' => $nonce_cb,
			) );
			register_rest_route( self::ROUTE_NS, 'forms/payment/stripe/confirm', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'confirm' ),
				'permission_callback' => $nonce_cb,
			) );
			if ( method_exists( $public, 'get_stripe_public_key_Emsfb_api' ) ) {
				register_rest_route( self::ROUTE_NS, 'forms/payment/stripe/pkey', array(
					'methods'             => 'GET',
					'callback'            => array( $public, 'get_stripe_public_key_Emsfb_api' ),
					/* The publishable key is public by design. */
					'permission_callback' => '__return_true',
				) );
			}
			register_rest_route( self::ROUTE_NS, 'forms/payment/stripe/webhook', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'webhook' ),
				/* Authenticated by the Stripe-Signature HMAC inside the callback. */
				'permission_callback' => '__return_true',
			) );
		}

		public static function fallback_nonce_permission( $request ) {
			$nonce = $request->get_header( 'x_wp_nonce' );
			if ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return true;
			}
			return new \WP_Error( 'rest_forbidden', __( '\\nInvalid or expired nonce\\n', 'flx-forms' ), array( 'status' => 403 ) );
		}

		/* ------------------------------------------------------------------
		 * Helpers
		 * ------------------------------------------------------------------ */

		public static function secret_key() {
			$keys = array();
			if ( function_exists( 'get_setting_Emsfb' ) ) {
				$keys[] = get_setting_Emsfb( 'decoded' );
			}
			$opt = get_option( 'emsfb_settings', '' );
			if ( is_string( $opt ) && '' !== $opt ) {
				$d = json_decode( $opt );
				if ( ! is_object( $d ) ) {
					$d = json_decode( str_replace( '\\', '', $opt ) );
				}
				$keys[] = $d;
			}
			foreach ( $keys as $s ) {
				if ( is_object( $s ) && isset( $s->stripeSKey ) ) {
					$v = trim( (string) $s->stripeSKey );
					if ( strlen( $v ) > 5 && 'null' !== $v ) {
						return $v;
					}
				}
			}
			return '';
		}

		/** @return StripeApiEfb|null */
		public static function api() {
			$sk = self::secret_key();
			if ( '' === $sk ) {
				return null;
			}
			return apply_filters( 'efb_stripe_api_client', new StripeApiEfb( $sk ) );
		}

		/** Decode a stored form structure the same way the core does. */
		public static function load_form_structure( $form_id ) {
			global $wpdb;
			$form_id = (int) $form_id;
			if ( $form_id <= 0 ) {
				return null;
			}
			$t   = $wpdb->prefix . 'emsfb_form';
			$raw = $wpdb->get_var( $wpdb->prepare( "SELECT form_structer FROM `{$t}` WHERE form_id = %d", $form_id ) );
			if ( ! is_string( $raw ) || '' === $raw ) {
				return null;
			}
			$fs = json_decode( str_replace( '\\', '', $raw ), true );
			return ( is_array( $fs ) && isset( $fs[0] ) && is_array( $fs[0] ) ) ? $fs : null;
		}

		public static function is_stripe_form( $fs ) {
			if ( ! is_array( $fs ) || empty( $fs[0] ) ) {
				return false;
			}
			if ( isset( $fs[0]['getway'] ) && 'stripe' === $fs[0]['getway'] ) {
				return true;
			}
			foreach ( $fs as $el ) {
				if ( is_array( $el ) && isset( $el['type'] ) && 'stripe' === $el['type'] ) {
					return true;
				}
			}
			return false;
		}

		private static function client_ip() {
			return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		}

		private static function rate_limited( $bucket = 'card_add' ) {
			if ( 'quote' === $bucket ) {
				/* Quotes are cheap and fire on every price change (debounced), so allow more. */
				$limit = (int) apply_filters( 'flxf_payment_quote_rate_limit', 120 );
				$key   = 'flxf_quote_rl_' . md5( self::client_ip() );
			} else {
				$limit = (int) apply_filters( 'efb_stripe_card_add_rate_limit', 20 );
				$key   = 'efb_stripe_rl_' . md5( self::client_ip() );
			}
			if ( $limit <= 0 ) {
				return false;
			}
			$count = (int) get_transient( $key );
			if ( $count >= $limit ) {
				return true;
			}
			set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
			return false;
		}

		private static function fail( $message, $status = 200 ) {
			wp_send_json_success( array( 'success' => false, 'm' => $message ), $status );
			exit; // wp_send_json_* dies; this keeps unit harnesses honest.
		}

		private static function num_equal( $a, $b ) {
			return abs( (float) $a - (float) $b ) < 0.000001;
		}

		/* ------------------------------------------------------------------
		 * card/add
		 * ------------------------------------------------------------------ */

		/**
		 * Validate the browser's payment rows before the core prices the order.
		 *
		 * The core charges the total it computes from the stored form
		 * definition for every choice field. The one value it takes from the
		 * browser is the amount typed into a "Price" (prcfld) field, and it
		 * adds that amount without checking its sign or that the field exists,
		 * so a negative row would lower the charge. Reject that here.
		 *
		 * @param array  $params        Request JSON params.
		 * @param bool   $require_token False for price quotes (no card token yet).
		 * @param string $provider      Flux Forms payment provider id ('stripe' = the
		 *                              original Stripe checks; another id = that
		 *                              provider's supports() decides the method).
		 * @return true|string True or an error message.
		 */
		public static function validate_card_add( $params, $require_token = true, $provider = 'stripe' ) {
			$invalid = __( '\\nInvalid payment data. Please refresh the page and try again.\\n', 'flx-forms' );
			if ( ! is_array( $params ) ) {
				return $invalid;
			}
			$form_id = isset( $params['id'] ) && is_scalar( $params['id'] ) ? absint( $params['id'] ) : 0;
			$fs      = self::load_form_structure( $form_id );
			if ( null === $fs ) {
				return __( '\\nForm not found.\\n', 'flx-forms' );
			}
			/* is_stripe_form() = the form has EFB's card payment box, whichever
			 * Flux Forms provider takes the payment. */
			if ( ( $fs[0]['type'] ?? '' ) !== 'payment' || ! self::is_stripe_form( $fs ) ) {
				return 'stripe' === $provider
					? __( 'This form does not accept Stripe payments.', 'flx-forms' )
					: __( 'This form does not accept online payments.', 'flx-forms' );
			}
			$method = isset( $fs[0]['paymentmethod'] ) ? (string) $fs[0]['paymentmethod'] : 'charge';
			if ( isset( $fs[0]['currency'] ) && strlen( (string) $fs[0]['currency'] ) > 2 && ! preg_match( '/^[A-Za-z]{3}$/', (string) $fs[0]['currency'] ) ) {
				return __( '[[\'The currency of this form is not supported.\\n', 'flx-forms' );
			}
			if ( 'stripe' === $provider ) {
				if ( ! in_array( $method, self::PAYMENT_METHODS, true ) ) {
					return __( '\\nThe payment method of this form is not supported.\\n', 'flx-forms' );
				}
			} else {
				$p        = function_exists( 'flxf_payment_provider' ) ? flxf_payment_provider( $provider ) : null;
				$currency = isset( $fs[0]['currency'] ) && strlen( (string) $fs[0]['currency'] ) > 2 ? strtoupper( (string) $fs[0]['currency'] ) : 'USD';
				if ( ! $p ) {
					return __( '\\nThe payment method of this form is not supported.\\n', 'flx-forms' );
				}
				if ( ! $p->supports( $currency, $method ) ) {
					return in_array( $method, self::PAYMENT_METHODS, true ) && $p->supports( $currency, 'charge' )
						? __( '\\nThe payment method of this form is not supported.\\n', 'flx-forms' )
						: __( '[[\'The currency of this form is not supported.\\n', 'flx-forms' );
				}
			}
			if ( 'stripe' === $provider && 'charge' !== $method && $require_token ) {
				$token = isset( $params['token'] ) && is_string( $params['token'] ) ? $params['token'] : '';
				if ( ! preg_match( '/^(tok|pm|src|card)_[A-Za-z0-9_]+$/', $token ) ) {
					return $invalid;
				}
			}
			$raw = isset( $params['value'] ) && is_string( $params['value'] ) ? $params['value'] : '';
			/* Same decoding as the core so both see the same rows. */
			$rows = json_decode( str_replace( '\\', '', sanitize_text_field( $raw ) ), true );
			if ( ! is_array( $rows ) || empty( $rows ) || count( $rows ) > 500 ) {
				return $invalid;
			}

			$price_fields = array();
			foreach ( $fs as $el ) {
				if ( is_array( $el ) && isset( $el['type'], $el['id_'] ) && 'prcfld' === $el['type'] ) {
					$price_fields[ (string) $el['id_'] ] = $el;
				}
			}

			$seen_prcfld = array();
			$total       = 0.0;
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					return $invalid;
				}
				if ( ! array_key_exists( 'price', $row ) ) {
					continue;
				}
				$price = $row['price'];
				if ( ! is_numeric( $price ) || ! is_finite( (float) $price ) || (float) $price < 0 ) {
					return $invalid;
				}
				$type = isset( $row['type'] ) ? (string) $row['type'] : '';
				if ( 'prcfld' === $type ) {
					$id = isset( $row['id_'] ) ? (string) $row['id_'] : '';
					if ( '' === $id || ! isset( $price_fields[ $id ] ) || isset( $seen_prcfld[ $id ] ) ) {
						return $invalid;
					}
					$seen_prcfld[ $id ] = true;
					$value = $row['value'] ?? null;
					if ( ! is_numeric( $value ) || (float) $value <= 0 || ! self::num_equal( $value, $price ) ) {
						return $invalid;
					}
				}
				$total += (float) $price;
			}
			/* Stripe's hard ceiling is 99,999,999 minor units. */
			if ( $total > 999999.99 ) {
				return __( 'The payment amount is too large.', 'flx-forms' );
			}
			return true;
		}

		/**
		 * POST forms/payment/stripe/card/add. Kept for existing forms and the
		 * Stripe script; when Flux Forms's active payment provider is not Stripe the
		 * request goes to the generic start flow (includes/flx/payments).
		 */
		public static function card_add( $request, $public ) {
			if ( function_exists( 'flxf_payment_active_provider_id' ) && 'stripe' !== flxf_payment_active_provider_id() && function_exists( 'flxf_payment_rest_start' ) ) {
				return flxf_payment_rest_start( $request, $public );
			}
			return self::card_add_stripe( $request, $public );
		}

		/** The original Stripe card/add (validation, then the core creates the PaymentIntent). */
		public static function card_add_stripe( $request, $public ) {
			if ( self::rate_limited() ) {
				self::fail( __( '\\nToo many payment attempts. Please wait a few minutes and try again.\\n', 'flx-forms' ), 429 );
			}
			$check = self::validate_card_add( $request->get_json_params() );
			if ( true !== $check ) {
				self::fail( $check );
			}
			if ( '' === self::secret_key() ) {
				self::fail( __( '\\nStripe is not configured on this site. Please contact the site administrator.\\n', 'flx-forms' ) );
			}
			return $public->pay_stripe_sub_Emsfb_api( $request );
		}

		/* ------------------------------------------------------------------
		 * forms/payment/quote
		 * ------------------------------------------------------------------ */

		/**
		 * Price the submitted rows exactly the way card/add will, without
		 * charging anything. The browser displays this amount and only enables
		 * the Pay button once a quote succeeded; card/add still recomputes the
		 * amount itself and never trusts the client.
		 *
		 * Body: {id, value} (value = JSON of the visitor's rows).
		 * Returns: {success, amount, currency, formatted} or {success:false, m}.
		 */
		public static function quote( $request, $public ) {
			$no_cache = array( 'Cache-Control' => 'no-store' );
			if ( self::rate_limited( 'quote' ) ) {
				return new \WP_REST_Response( array( 'success' => false, 'm' => __( '\\nToo many requests. Please wait a moment and try again.\\n', 'flx-forms' ) ), 429, $no_cache );
			}
			$params   = $request->get_json_params();
			$provider = function_exists( 'flxf_payment_active_provider_id' ) ? flxf_payment_active_provider_id() : 'stripe';
			$check    = self::validate_card_add( $params, false, $provider );
			if ( true !== $check ) {
				return new \WP_REST_Response( array( 'success' => false, 'm' => $check ), 400, $no_cache );
			}
			$fs   = self::load_form_structure( absint( $params['id'] ) );
			$rows = json_decode( str_replace( '\\', '', sanitize_text_field( $params['value'] ) ), true );
			if ( ! is_object( $public ) || ! method_exists( $public, 'fun_validation_pay_elements_efb' ) || ! is_array( $rows ) || null === $fs ) {
				return new \WP_REST_Response( array( 'success' => false, 'm' => __( '\\nInvalid payment data. Please refresh the page and try again.\\n', 'flx-forms' ) ), 400, $no_cache );
			}
			$obj    = self::server_amount( $public, $rows, $fs, true, absint( $params['id'] ) );
			if ( is_array( $obj ) && ! empty( $obj['error'] ) && is_string( $obj['error'] ) ) {
				/* A flxf_payment_server_amount filter refused the selection (e.g. a required consent is missing). */
				return new \WP_REST_Response( array( 'success' => false, 'm' => $obj['error'] ), 200, $no_cache );
			}
			$amount   = round( (float) ( is_array( $obj ) && isset( $obj['price_total'] ) ? $obj['price_total'] : 0 ), 2 );
			$currency = isset( $fs[0]['currency'] ) && strlen( (string) $fs[0]['currency'] ) > 2 ? strtoupper( (string) $fs[0]['currency'] ) : 'USD';
			if ( ! is_finite( $amount ) || $amount < 0 ) {
				return new \WP_REST_Response( array( 'success' => false, 'm' => __( '\\nInvalid payment data. Please refresh the page and try again.\\n', 'flx-forms' ) ), 400, $no_cache );
			}
			if ( ! class_exists( '\Emsfb\Formbuilder' ) && defined( 'EMSFB_PLUGIN_DIRECTORY' ) && is_readable( EMSFB_PLUGIN_DIRECTORY . 'includes/class-Emsfb-formbuilder.php' ) ) {
				require_once EMSFB_PLUGIN_DIRECTORY . 'includes/class-Emsfb-formbuilder.php';
			}
			if ( class_exists( '\Emsfb\Formbuilder' ) && method_exists( '\Emsfb\Formbuilder', 'formatPrice_efb' ) ) {
				$formatted = Formbuilder::formatPrice_efb( $amount, $currency );
			} else {
				$formatted = number_format_i18n( $amount, 2 ) . ' ' . $currency;
			}
			return new \WP_REST_Response( array(
				'success'   => true,
				'amount'    => $amount,
				'currency'  => $currency,
				'formatted' => $formatted,
				'provider'  => $provider,
			), 200, $no_cache );
		}

		/**
		 * Price rows from the stored form definition (the core's
		 * fun_validation_pay_elements_efb). Used by the quote and by Flux Forms's
		 * generic payment start, so both charge exactly what was quoted.
		 *
		 * @param bool $quiet The core mails the admin a tamper warning when the
		 *                    browser's sum and the server's differ. A quote fires
		 *                    on every change, so it stays quiet; a real charge does not.
		 * @param int  $form_id Form id, passed to the flxf_payment_server_amount filter.
		 * @return array {price_total, email, valobj}
		 */
		public static function server_amount( $public, $rows, $fs, $quiet = false, $form_id = 0 ) {
			$mute = function () { return false; };
			if ( $quiet ) {
				add_filter( 'pre_wp_mail', $mute, PHP_INT_MAX );
			}
			try {
				$obj = $public->fun_validation_pay_elements_efb( $rows, $fs );
			} finally {
				if ( $quiet ) {
					remove_filter( 'pre_wp_mail', $mute, PHP_INT_MAX );
				}
			}
			$obj = is_array( $obj ) ? $obj : array();
			/* Lets a companion plugin (Flux Donate donation types) set the price from its own records.
			 * A filter that sets 'error' (string) stops the quote with that message. */
			$obj = apply_filters( 'flxf_payment_server_amount', $obj, $rows, $fs, (int) $form_id );
			return is_array( $obj ) ? $obj : array();
		}

		/* ------------------------------------------------------------------
		 * Verification
		 * ------------------------------------------------------------------ */

		/**
		 * Does a PaymentIntent (raw API array) settle this payment row?
		 */
		public static function payment_intent_matches( $pi, $row ) {
			if ( ! is_array( $pi ) || ! is_object( $row ) ) {
				return false;
			}
			$expected = StripeApiEfb::to_minor( $row->amount, $row->currency );
			return isset( $pi['id'], $pi['status'], $pi['amount'], $pi['currency'] )
				&& $pi['id'] === $row->transaction_id
				&& 'succeeded' === $pi['status']
				&& $expected > 0
				&& (int) $pi['amount'] === $expected
				&& (int) ( $pi['amount_received'] ?? $pi['amount'] ) >= $expected
				&& strtolower( (string) $pi['currency'] ) === strtolower( (string) $row->currency );
		}

		/**
		 * Does a Subscription (raw API array) settle this payment row?
		 */
		public static function subscription_matches( $sub, $row ) {
			if ( ! is_array( $sub ) || ! is_object( $row ) ) {
				return false;
			}
			if ( ! isset( $sub['id'], $sub['status'] ) || $sub['id'] !== $row->subscription_id ) {
				return false;
			}
			if ( ! in_array( $sub['status'], self::SUB_OK_STATUSES, true ) ) {
				return false;
			}
			$item     = $sub['items']['data'][0] ?? null;
			$unit     = $item['price']['unit_amount'] ?? ( $sub['plan']['amount'] ?? null );
			$currency = $item['price']['currency'] ?? ( $sub['plan']['currency'] ?? ( $sub['currency'] ?? '' ) );
			$qty      = (int) ( $item['quantity'] ?? ( $sub['quantity'] ?? 1 ) );
			$expected = StripeApiEfb::to_minor( $row->amount, $row->currency );
			return null !== $unit
				&& $expected > 0
				&& (int) $unit * max( 1, $qty ) === $expected
				&& strtolower( (string) $currency ) === strtolower( (string) $row->currency );
		}

		/**
		 * Verify a stored Stripe payment with the Stripe API and record the
		 * result. Fails closed: no key / API error / mismatch => false.
		 *
		 * @param object      $row    emsfb_pay_ row.
		 * @param string|null $reason Set to a short machine reason.
		 * @return bool
		 */
		public static function verify_payment_row( $row, &$reason = null ) {
			$reason = '';
			if ( ! is_object( $row ) || 'stripe' !== $row->gateway ) {
				$reason = 'no_row';
				return false;
			}
			$is_sub = 'subscription' === $row->payment_type;

			if ( ! $is_sub ) {
				if ( 'completed' === $row->status ) {
					return true;
				}
				if ( ! in_array( $row->status, array( 'pending', 'processing' ), true ) ) {
					$reason = 'status_' . $row->status;
					return false;
				}
			}

			$api = self::api();
			if ( ! $api ) {
				$reason = 'no_key';
				return false;
			}

			try {
				if ( $is_sub ) {
					if ( '' === (string) $row->subscription_id ) {
						$reason = 'no_subscription';
						return false;
					}
					$sub = $api->request( 'GET', '/v1/subscriptions/' . rawurlencode( $row->subscription_id ) );
					if ( ! self::subscription_matches( $sub, $row ) ) {
						$reason = 'subscription_mismatch';
						if ( isset( $sub['status'] ) && is_string( $sub['status'] ) && $sub['status'] !== $row->status ) {
							StripePayment::update_payment( $row->id, array( 'status' => $sub['status'] ) );
						}
						return false;
					}
					if ( $sub['status'] !== $row->status ) {
						StripePayment::update_payment( $row->id, array( 'status' => $sub['status'] ) );
					}
					return true;
				}

				$pi = $api->request( 'GET', '/v1/payment_intents/' . rawurlencode( $row->transaction_id ) );
				if ( ! self::payment_intent_matches( $pi, $row ) ) {
					$reason = 'intent_mismatch';
					if ( isset( $pi['status'] ) && in_array( $pi['status'], array( 'canceled' ), true ) ) {
						StripePayment::update_payment( $row->id, array( 'status' => 'canceled' ) );
					}
					return false;
				}
				StripePayment::update_payment( $row->id, array(
					'status'     => 'completed',
					'capture_id' => ! empty( $pi['latest_charge'] ) && is_string( $pi['latest_charge'] ) ? $pi['latest_charge'] : $pi['id'],
				) );
				return true;
			} catch ( StripeApiErrorEfb $e ) {
				$reason = 'api_error';
				return false;
			}
		}

		/* ------------------------------------------------------------------
		 * confirm
		 * ------------------------------------------------------------------ */

		public static function confirm( $request ) {
			$p     = $request->get_json_params();
			$p     = is_array( $p ) ? $p : array();
			$pi_id = isset( $p['paymentIntentId'] ) && is_string( $p['paymentIntentId'] ) ? sanitize_text_field( $p['paymentIntentId'] ) : '';
			$track = isset( $p['trackid'] ) && is_scalar( $p['trackid'] ) ? sanitize_text_field( (string) $p['trackid'] ) : '';

			if ( ! preg_match( '/^pi_[A-Za-z0-9]+$/', $pi_id ) ) {
				wp_send_json_success( array( 'success' => false, 'm' => __( '[[\'Payment Intent ID is missing\\n', 'flx-forms' ) ), 400 );
				return;
			}
			/* Only the PaymentIntent the server itself created for this entry
			 * counts - never "any succeeded intent" sent with a track code. */
			$row = StripePayment::get_by_transaction( $pi_id );
			if ( ! $row || ( '' !== $track && (string) $row->track !== $track ) ) {
				wp_send_json_success( array( 'success' => false, 'm' => __( '\\nPayment not found.\\n', 'flx-forms' ) ), 404 );
				return;
			}
			$ok = self::verify_payment_row( $row, $reason );
			wp_send_json_success( array(
				'success'         => $ok,
				'paymentIntentId' => $pi_id,
				'trackid'         => $row->track,
				'm'               => $ok ? '' : __( '\\nThe payment could not be verified yet.\\n', 'flx-forms' ),
			), 200 );
		}

		/* ------------------------------------------------------------------
		 * Final submit guard
		 * ------------------------------------------------------------------ */

		/**
		 * rest_dispatch_request filter: runs after the route's permission
		 * check and before the core callback for Emsfb/v1/forms/message/add.
		 */
		public static function guard_final_submit( $result, $request, $route, $handler ) {
			if ( null !== $result || ! is_string( $route ) || 0 !== strcasecmp( rtrim( $route, '/' ), '/Emsfb/v1/forms/message/add' ) ) {
				return $result;
			}
			$p = $request->get_json_params();
			if ( ! is_array( $p ) || ! isset( $p['type'] ) || 'payment' !== $p['type'] ) {
				return $result;
			}
			$track = isset( $p['payid'] ) && is_scalar( $p['payid'] ) ? sanitize_text_field( (string) $p['payid'] ) : '';

			/* Flux Forms: when Flux Donate owns the payment layer it guards this submit itself. */
			if ( apply_filters( 'flxf_payments_delegate', false ) ) {
				return $result;
			}

			/* Flux Forms: a payment taken by another provider is checked by that
			 * provider (flxf_payment_guard_final_submit), not here. */
			if ( function_exists( 'flxf_payment_track_gateway' ) ) {
				$gw = '' !== $track ? flxf_payment_track_gateway( $track ) : '';
				if ( ( '' !== $gw && 'stripe' !== $gw ) || ( '' === $gw && 'stripe' !== flxf_payment_active_provider_id() ) ) {
					return $result;
				}
			}

			global $wpdb;
			$msg_form_id = 0;
			if ( '' !== $track ) {
				$mt          = $wpdb->prefix . 'emsfb_msg_';
				$msg_form_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT form_id FROM `{$mt}` WHERE track = %s AND read_ = %d LIMIT 1", $track, 2 ) );
			}
			$req_form_id = isset( $p['id'] ) && is_scalar( $p['id'] ) ? absint( $p['id'] ) : 0;

			$stripe = false;
			foreach ( array_unique( array_filter( array( $msg_form_id, $req_form_id ) ) ) as $fid ) {
				if ( self::is_stripe_form( self::load_form_structure( $fid ) ) ) {
					$stripe = true;
					break;
				}
			}
			if ( ! $stripe ) {
				return $result;
			}
			if ( 0 === $msg_form_id ) {
				/* No pending entry: the core rejects this itself. */
				return $result;
			}

			$row = StripePayment::get_by_track( $track );
			$ok  = $row && (int) $row->form_id === $msg_form_id && self::verify_payment_row( $row, $reason );
			if ( $ok ) {
				return $result;
			}
			return new \WP_REST_Response( array(
				'success' => true,
				'data'    => array(
					'success' => false,
					'm'       => __( 'Your payment has not been confirmed by Stripe yet. Please wait a moment and try again. If the problem persists, contact the site administrator.', 'flx-forms' ),
				),
			), 200 );
		}

		/* ------------------------------------------------------------------
		 * Webhook
		 * ------------------------------------------------------------------ */

		public static function webhook( $request ) {
			$secret  = StripePayment::webhook_secret();
			$payload = (string) $request->get_body();
			$header  = (string) $request->get_header( 'stripe_signature' );

			if ( '' === $secret ) {
				return new \WP_REST_Response( array( 'error' => 'webhook_not_configured' ), 503 );
			}
			$tolerance = (int) apply_filters( 'efb_stripe_webhook_tolerance', 300 );
			if ( ! StripeApiEfb::verify_signature( $payload, $header, $secret, $tolerance ) ) {
				return new \WP_REST_Response( array( 'error' => 'invalid_signature' ), 400 );
			}
			$event = json_decode( $payload, true );
			if ( ! is_array( $event ) || empty( $event['type'] ) || ! isset( $event['data']['object'] ) || ! is_array( $event['data']['object'] ) ) {
				return new \WP_REST_Response( array( 'error' => 'invalid_payload' ), 400 );
			}
			self::handle_event( $event );
			return new \WP_REST_Response( array( 'received' => true ), 200 );
		}

		public static function handle_event( array $event ) {
			$obj  = $event['data']['object'];
			$type = (string) $event['type'];

			switch ( $type ) {
				case 'payment_intent.succeeded':
					$row = isset( $obj['id'] ) ? StripePayment::get_by_transaction( (string) $obj['id'] ) : null;
					if ( $row && 'subscription' !== $row->payment_type && in_array( $row->status, array( 'pending', 'processing' ), true ) ) {
						if ( self::payment_intent_matches( $obj, $row ) ) {
							StripePayment::update_payment( $row->id, array(
								'status'     => 'completed',
								'capture_id' => ! empty( $obj['latest_charge'] ) && is_string( $obj['latest_charge'] ) ? $obj['latest_charge'] : $obj['id'],
							) );
						} else {
							StripePayment::update_payment( $row->id, array( 'status' => 'mismatch' ) );
						}
					}
					break;

				case 'payment_intent.processing':
				case 'payment_intent.payment_failed':
				case 'payment_intent.canceled':
					$row = isset( $obj['id'] ) ? StripePayment::get_by_transaction( (string) $obj['id'] ) : null;
					if ( $row && in_array( $row->status, array( 'pending', 'processing' ), true ) ) {
						$map = array(
							'payment_intent.processing'     => 'processing',
							'payment_intent.payment_failed' => 'pending', // the visitor may retry the same intent
							'payment_intent.canceled'       => 'canceled',
						);
						StripePayment::update_payment( $row->id, array( 'status' => $map[ $type ] ) );
					}
					break;

				case 'charge.refunded':
					$pi  = isset( $obj['payment_intent'] ) && is_string( $obj['payment_intent'] ) ? $obj['payment_intent'] : '';
					$row = '' !== $pi ? StripePayment::get_by_transaction( $pi ) : null;
					if ( $row ) {
						$full = ! empty( $obj['refunded'] ) || ( isset( $obj['amount_refunded'], $obj['amount'] ) && (int) $obj['amount_refunded'] >= (int) $obj['amount'] );
						StripePayment::update_payment( $row->id, array( 'status' => $full ? 'refunded' : 'partially_refunded' ) );
					}
					break;

				case 'customer.subscription.created':
				case 'customer.subscription.updated':
				case 'customer.subscription.deleted':
					$row = isset( $obj['id'] ) ? StripePayment::get_by_subscription( (string) $obj['id'] ) : null;
					if ( $row && isset( $obj['status'] ) && is_string( $obj['status'] ) ) {
						StripePayment::update_payment( $row->id, array( 'status' => sanitize_key( $obj['status'] ) ) );
					}
					break;
			}
			do_action( 'efb_stripe_webhook_event', $event );
		}
	}
}
