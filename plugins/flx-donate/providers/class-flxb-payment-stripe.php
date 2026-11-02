<?php
/**
 * Flux Donate: Stripe provider.
 *
 * One-time gifts: a PaymentIntent for the server amount, confirmed in the browser with
 * Stripe.js (Flux Forms's stripe_pay-efb.js, unchanged). Recurring gifts (form payment method
 * day / week / month / year): product + recurring price + customer (card token) + subscription,
 * recorded in Flux Donate's recurring donations. Verification asks Stripe for the stored amount.
 *
 * Keys: Donations › Payments; when empty there, the keys saved in Flux Forms's settings are used,
 * so an existing site keeps working without entering them again. Webhook secret: Donations ›
 * Payments, else Flux Forms's saved Stripe webhook secret.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLXB_Payment_Stripe extends FLXB_Payment_Provider_Base {

	const METHODS    = array( 'charge', 'day', 'week', 'month', 'year' );
	const SUB_OK     = array( 'active', 'trialing' );

	public function id() {
		return 'stripe';
	}

	public function label() {
		return 'Stripe';
	}

	public function supports( $currency, $method ) {
		return in_array( $method, self::METHODS, true ) && (bool) preg_match( '/^[A-Z]{3}$/', strtoupper( (string) $currency ) );
	}

	public function settings_fields() {
		return array(
			array( 'key' => 'stripePKey', 'label' => __( 'Publishable key', 'flx-donate' ), 'type' => 'text', 'required' => true, 'help' => __( 'Starts with pk_live_ or pk_test_.', 'flx-donate' ) ),
			array( 'key' => 'stripeSKey', 'label' => __( 'Secret key', 'flx-donate' ), 'type' => 'password', 'required' => true, 'help' => __( 'Starts with sk_live_, sk_test_ or rk_ (restricted key).', 'flx-donate' ) ),
			array( 'key' => 'stripeWebhookSecret', 'label' => __( 'Webhook signing secret', 'flx-donate' ), 'type' => 'password', 'help' => __( 'Starts with whsec_. Needed for refunds and renewals of recurring gifts to show up here.', 'flx-donate' ) ),
		);
	}

	public function is_configured() {
		return strlen( $this->setting( 'stripeSKey' ) ) > 5 && strlen( $this->setting( 'stripePKey' ) ) > 5;
	}

	public function publishable_key() {
		$k = $this->setting( 'stripePKey' );
		return strlen( $k ) > 5 ? $k : '';
	}

	public function webhook_secret() {
		$s = $this->setting( 'stripeWebhookSecret' );
		if ( '' === $s && defined( 'EMSFB_STRIPE_WEBHOOK_SECRET' ) && EMSFB_STRIPE_WEBHOOK_SECRET ) {
			$s = (string) EMSFB_STRIPE_WEBHOOK_SECRET;
		}
		if ( '' === $s ) {
			$s = (string) get_option( 'emsfb_stripe_webhook_secret', '' );
		}
		return (string) apply_filters( 'flxb_stripe_webhook_secret', $s );
	}

	/** @return FLXB_Stripe_Api|null */
	public function api() {
		$sk = $this->setting( 'stripeSKey' );
		return strlen( $sk ) > 5 ? apply_filters( 'flxb_stripe_api_client', new FLXB_Stripe_Api( $sk ) ) : null;
	}

	/** Stripe.js and stripe_pay-efb.js come with Flux Forms's payment box. */
	public function front_assets() {
		return array();
	}

	public function create_payment( $amount, $currency, array $meta ) {
		$api = $this->api();
		if ( ! $api ) {
			return FLXB_Payment_Result::failed( __( 'Stripe is not configured on this site. Please contact the site administrator.', 'flx-donate' ) );
		}
		$method = (string) ( $meta['method'] ?? 'charge' );
		$minor  = FLXB_Stripe_Api::to_minor( $amount, $currency );
		$desc   = (string) ( $meta['description'] ?? '' );
		$email  = ! empty( $meta['email'] ) && is_email( $meta['email'] ) ? (string) $meta['email'] : '';
		$ref    = (string) ( $meta['reference'] ?? '' );
		try {
			if ( 'charge' === $method ) {
				$p = array(
					'amount'               => $minor,
					'currency'             => strtolower( $currency ),
					'payment_method_types' => array( 'card' ),
					'description'          => $desc,
					'metadata'             => array( 'flxb_reference' => $ref, 'form_id' => (string) (int) ( $meta['form_id'] ?? 0 ) ),
				);
				if ( '' !== $email ) {
					$p['receipt_email'] = $email;
				}
				$pi = $api->request( 'POST', '/v1/payment_intents', $p, $ref . '-pi' );
				if ( empty( $pi['id'] ) || empty( $pi['client_secret'] ) ) {
					return FLXB_Payment_Result::failed( __( 'The payment could not be started. Please try again.', 'flx-donate' ) );
				}
				return FLXB_Payment_Result::requires_action( (string) $pi['id'], array( 'client_secret' => (string) $pi['client_secret'] ), array( 'record' => array( 'payment_type' => 'one-time', 'status' => 'pending' ) ) );
			}

			/* Recurring gift. */
			$token = (string) ( $meta['params']['token'] ?? '' );
			if ( ! preg_match( '/^(tok|pm|src|card)_[A-Za-z0-9_]+$/', $token ) ) {
				return FLXB_Payment_Result::failed( __( 'Invalid payment data. Please refresh the page and try again.', 'flx-donate' ) );
			}
			$product  = $api->request( 'POST', '/v1/products', array( 'name' => '' !== $desc ? $desc : get_bloginfo( 'name' ) ), $ref . '-prod' );
			$price    = $api->request( 'POST', '/v1/prices', array( 'unit_amount' => $minor, 'currency' => strtolower( $currency ), 'recurring' => array( 'interval' => $method ), 'product' => (string) $product['id'] ), $ref . '-price' );
			$customer = array( 'description' => $desc );
			$customer[ 0 === strpos( $token, 'pm_' ) ? 'payment_method' : 'source' ] = $token;
			if ( '' !== $email ) {
				$customer['email'] = $email;
			}
			$cus = $api->request( 'POST', '/v1/customers', $customer, $ref . '-cus' );
			$sp  = array(
				'customer' => (string) $cus['id'],
				'items'    => array( array( 'price' => (string) $price['id'] ) ),
				'metadata' => array( 'flxb_reference' => $ref, 'form_id' => (string) (int) ( $meta['form_id'] ?? 0 ) ),
			);
			if ( 0 === strpos( $token, 'pm_' ) ) {
				$sp['default_payment_method'] = $token;
			}
			$sub = $api->request( 'POST', '/v1/subscriptions', $sp, $ref . '-sub' );
		} catch ( FLXB_Stripe_Error $e ) {
			return FLXB_Payment_Result::failed( 'Stripe: ' . $e->getMessage() );
		}
		$status = (string) ( $sub['status'] ?? '' );
		if ( empty( $sub['id'] ) || ! in_array( $status, self::SUB_OK, true ) ) {
			return FLXB_Payment_Result::failed( __( 'Your card could not be charged for the recurring donation. Please try another card.', 'flx-donate' ) );
		}
		$end = $sub['current_period_end'] ?? ( $sub['items']['data'][0]['current_period_end'] ?? null );
		return FLXB_Payment_Result::completed(
			(string) $sub['id'],
			array(
				'record' => array(
					'payment_type'    => 'subscription',
					'status'          => $status,
					'subscription_id' => (string) $sub['id'],
					'plan_id'         => (string) $price['id'],
					'product_id'      => (string) $product['id'],
					'interval_unit'   => strtoupper( $method ),
					'meta'            => array( 'next_charge_at' => $end ? gmdate( 'Y-m-d H:i:s', (int) $end ) : '', 'customer' => (string) $cus['id'] ),
				),
				'client' => array( 'interval' => $method, 'next' => $end ? (int) $end : 0 ),
			)
		);
	}

	public static function intent_matches( $pi, $row ) {
		if ( ! is_array( $pi ) || ! is_object( $row ) ) {
			return false;
		}
		$expected = FLXB_Stripe_Api::to_minor( $row->amount, $row->currency );
		return isset( $pi['id'], $pi['status'], $pi['amount'], $pi['currency'] )
			&& $pi['id'] === $row->transaction_id
			&& 'succeeded' === $pi['status']
			&& $expected > 0
			&& (int) $pi['amount'] === $expected
			&& (int) ( $pi['amount_received'] ?? $pi['amount'] ) >= $expected
			&& strtolower( (string) $pi['currency'] ) === strtolower( (string) $row->currency );
	}

	public static function subscription_matches( $sub, $row ) {
		if ( ! is_array( $sub ) || ! is_object( $row ) || ! isset( $sub['id'], $sub['status'] ) || $sub['id'] !== $row->subscription_id ) {
			return false;
		}
		if ( ! in_array( $sub['status'], self::SUB_OK, true ) ) {
			return false;
		}
		$item     = $sub['items']['data'][0] ?? null;
		$unit     = $item['price']['unit_amount'] ?? ( $sub['plan']['amount'] ?? null );
		$currency = $item['price']['currency'] ?? ( $sub['plan']['currency'] ?? ( $sub['currency'] ?? '' ) );
		$qty      = (int) ( $item['quantity'] ?? ( $sub['quantity'] ?? 1 ) );
		$expected = FLXB_Stripe_Api::to_minor( $row->amount, $row->currency );
		return null !== $unit && $expected > 0 && (int) $unit * max( 1, $qty ) === $expected && strtolower( (string) $currency ) === strtolower( (string) $row->currency );
	}

	public function verify( $record, array $params = array() ) {
		$no = FLXB_Payment_Result::failed( __( 'The payment could not be verified yet.', 'flx-donate' ) );
		if ( ! is_object( $record ) || 'stripe' !== $record->gateway ) {
			return $no;
		}
		$is_sub = 'subscription' === $record->payment_type;
		if ( ! $is_sub ) {
			if ( 'completed' === $record->status ) {
				return FLXB_Payment_Result::completed( (string) $record->transaction_id );
			}
			if ( ! in_array( $record->status, array( 'pending', 'processing' ), true ) ) {
				return $no;
			}
		}
		$api = $this->api();
		if ( ! $api ) {
			return $no;
		}
		try {
			if ( $is_sub ) {
				if ( '' === (string) $record->subscription_id ) {
					return $no;
				}
				$sub = $api->request( 'GET', '/v1/subscriptions/' . rawurlencode( $record->subscription_id ) );
				if ( isset( $sub['status'] ) && is_string( $sub['status'] ) && $sub['status'] !== $record->status ) {
					FLXB_Payment_Records::update( $record->id, array( 'status' => sanitize_key( $sub['status'] ) ) );
				}
				return self::subscription_matches( $sub, $record ) ? FLXB_Payment_Result::completed( (string) $record->subscription_id ) : $no;
			}
			$pi = $api->request( 'GET', '/v1/payment_intents/' . rawurlencode( $record->transaction_id ) );
			if ( ! self::intent_matches( $pi, $record ) ) {
				if ( isset( $pi['status'] ) && 'canceled' === $pi['status'] ) {
					FLXB_Payment_Records::update( $record->id, array( 'status' => 'canceled' ) );
				}
				return $no;
			}
			FLXB_Payment_Records::update( $record->id, array( 'status' => 'completed', 'capture_id' => ! empty( $pi['latest_charge'] ) && is_string( $pi['latest_charge'] ) ? $pi['latest_charge'] : $pi['id'] ) );
			return FLXB_Payment_Result::completed( (string) $record->transaction_id );
		} catch ( FLXB_Stripe_Error $e ) {
			return $no;
		}
	}

	public function refund( $record, $amount = null ) {
		$api = $this->api();
		if ( ! $api || ! is_object( $record ) || 'one-time' !== $record->payment_type || '' === (string) $record->transaction_id ) {
			return FLXB_Payment_Result::failed( __( 'This payment cannot be refunded from the site.', 'flx-donate' ) );
		}
		$p = array( 'payment_intent' => (string) $record->transaction_id );
		if ( null !== $amount && (float) $amount > 0 && (float) $amount < (float) $record->amount ) {
			$p['amount'] = FLXB_Stripe_Api::to_minor( $amount, $record->currency );
		}
		try {
			$r = $api->request( 'POST', '/v1/refunds', $p );
		} catch ( FLXB_Stripe_Error $e ) {
			return FLXB_Payment_Result::failed( 'Stripe: ' . $e->getMessage() );
		}
		if ( empty( $r['id'] ) || in_array( $r['status'] ?? '', array( 'failed', 'canceled' ), true ) ) {
			return FLXB_Payment_Result::failed( __( 'Stripe did not accept the refund.', 'flx-donate' ) );
		}
		FLXB_Payment_Records::update( $record->id, array( 'status' => isset( $p['amount'] ) ? 'partially_refunded' : 'refunded' ) );
		return FLXB_Payment_Result::completed( (string) $r['id'] );
	}

	/** Cancel a recurring gift at Stripe (immediately). */
	public function cancel_subscription( $subscription_id ) {
		$api = $this->api();
		if ( ! $api || '' === (string) $subscription_id ) {
			return FLXB_Payment_Result::failed( __( 'Stripe is not configured on this site.', 'flx-donate' ) );
		}
		try {
			$s = $api->request( 'DELETE', '/v1/subscriptions/' . rawurlencode( (string) $subscription_id ) );
		} catch ( FLXB_Stripe_Error $e ) {
			return FLXB_Payment_Result::failed( 'Stripe: ' . $e->getMessage() );
		}
		return 'canceled' === ( $s['status'] ?? '' ) ? FLXB_Payment_Result::completed( (string) $subscription_id ) : FLXB_Payment_Result::failed( __( 'Stripe did not cancel the subscription.', 'flx-donate' ) );
	}

	/* ------------------------------------------------------------------
	 * Webhook
	 * ------------------------------------------------------------------ */

	public function handle_webhook( $request ) {
		$secret = $this->webhook_secret();
		if ( '' === $secret ) {
			return new WP_REST_Response( array( 'error' => 'webhook_not_configured' ), 503 );
		}
		$payload = (string) $request->get_body();
		if ( ! FLXB_Stripe_Api::verify_signature( $payload, (string) $request->get_header( 'stripe_signature' ), $secret, (int) apply_filters( 'efb_stripe_webhook_tolerance', 300 ) ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid_signature' ), 400 );
		}
		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) || empty( $event['type'] ) || ! isset( $event['data']['object'] ) || ! is_array( $event['data']['object'] ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid_payload' ), 400 );
		}
		self::handle_event( $event );
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/** Keep emsfb_pay_ in step, then let listeners (donations, Flux Forms automations) react. */
	public static function handle_event( array $event ) {
		$o    = $event['data']['object'];
		$type = (string) $event['type'];
		switch ( $type ) {
			case 'payment_intent.succeeded':
				$row = isset( $o['id'] ) ? FLXB_Payment_Records::get_by_reference( 'stripe', (string) $o['id'] ) : null;
				if ( $row && 'subscription' !== $row->payment_type && in_array( $row->status, array( 'pending', 'processing' ), true ) ) {
					if ( self::intent_matches( $o, $row ) ) {
						FLXB_Payment_Records::update( $row->id, array( 'status' => 'completed', 'capture_id' => ! empty( $o['latest_charge'] ) && is_string( $o['latest_charge'] ) ? $o['latest_charge'] : $o['id'] ) );
					} else {
						FLXB_Payment_Records::update( $row->id, array( 'status' => 'mismatch' ) );
					}
				}
				break;
			case 'payment_intent.processing':
			case 'payment_intent.payment_failed':
			case 'payment_intent.canceled':
				$row = isset( $o['id'] ) ? FLXB_Payment_Records::get_by_reference( 'stripe', (string) $o['id'] ) : null;
				if ( $row && in_array( $row->status, array( 'pending', 'processing' ), true ) ) {
					$map = array( 'payment_intent.processing' => 'processing', 'payment_intent.payment_failed' => 'pending', 'payment_intent.canceled' => 'canceled' );
					FLXB_Payment_Records::update( $row->id, array( 'status' => $map[ $type ] ) );
				}
				break;
			case 'charge.refunded':
				$pi  = isset( $o['payment_intent'] ) && is_string( $o['payment_intent'] ) ? $o['payment_intent'] : '';
				$row = '' !== $pi ? FLXB_Payment_Records::get_by_reference( 'stripe', $pi ) : null;
				if ( $row ) {
					$full = ! empty( $o['refunded'] ) || ( isset( $o['amount_refunded'], $o['amount'] ) && (int) $o['amount_refunded'] >= (int) $o['amount'] );
					FLXB_Payment_Records::update( $row->id, array( 'status' => $full ? 'refunded' : 'partially_refunded' ) );
				}
				break;
			case 'customer.subscription.created':
			case 'customer.subscription.updated':
			case 'customer.subscription.deleted':
				$row = isset( $o['id'] ) ? FLXB_Payment_Records::get_by_reference( 'stripe', (string) $o['id'], 'subscription_id' ) : null;
				if ( $row && isset( $o['status'] ) && is_string( $o['status'] ) ) {
					FLXB_Payment_Records::update( $row->id, array( 'status' => sanitize_key( $o['status'] ) ) );
				}
				break;
		}
		/* Same action Flux Forms fires after its own webhook, so listeners do not care who received it. */
		do_action( 'efb_stripe_webhook_event', $event );
	}
}
