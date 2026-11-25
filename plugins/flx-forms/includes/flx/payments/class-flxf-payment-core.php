<?php
/**
 * Flux Forms: the form side of a payment. Reads the stored form, checks the visitor's rows and prices them on the
 * server (the same code prices the quote and the charge). The money itself is taken by Flux POS
 * (providers/class-flxf-payment-pos.php); Flux Forms has no payment gateway of its own.
 *
 * Part of Flux Forms (GPL v2 or later), a fork of Easy Form Builder 4.2.0 by WhiteStudio.
 *
 * @package FLX_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXF_Payment_Core {

	/** Payment methods of a form (`paymentmethod`): one charge or a recurring interval. */
	const METHODS = array( 'charge', 'day', 'week', 'month', 'year' );

	/** Decode a stored form structure the same way the core does. */
	public static function load_form_structure( $form_id ) {
		global $wpdb;
		$form_id = (int) $form_id;
		if ( $form_id <= 0 ) {
			return null;
		}
		$t   = $wpdb->prefix . 'emsfb_form';
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT form_structer FROM `{$t}` WHERE form_id = %d", $form_id ) ); // phpcs:ignore WordPress.DB
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$fs = json_decode( str_replace( '\\', '', $raw ), true );
		return ( is_array( $fs ) && isset( $fs[0] ) && is_array( $fs[0] ) ) ? $fs : null;
	}

	/** The form has the card payment box (element type "stripe" in stored forms: the box, whichever provider). */
	public static function has_pay_box( $fs ) {
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

	/** Currency of a form (ISO 4217, upper case). */
	public static function currency( $fs ) {
		return isset( $fs[0]['currency'] ) && strlen( (string) $fs[0]['currency'] ) > 2 ? strtoupper( (string) $fs[0]['currency'] ) : 'TRY';
	}

	private static function num_equal( $a, $b ) {
		return abs( (float) $a - (float) $b ) < 0.000001;
	}

	/**
	 * Check the browser's payment rows before the server prices them. The core charges the total it computes from the
	 * stored form for every choice field; the one value it takes from the browser is the amount typed into a "Price"
	 * (prcfld) field, so a negative or unknown row is refused here.
	 *
	 * @param mixed                      $params   Request JSON params ({id, value}).
	 * @param FLXF_Payment_Provider|null $provider The provider that would take the payment.
	 * @return true|string True or an error message.
	 */
	public static function validate( $params, $provider ) {
		$invalid = __( 'Invalid payment data. Please refresh the page and try again.', 'flx-forms' );
		if ( ! is_array( $params ) ) {
			return $invalid;
		}
		$form_id = isset( $params['id'] ) && is_scalar( $params['id'] ) ? absint( $params['id'] ) : 0;
		$fs      = self::load_form_structure( $form_id );
		if ( null === $fs ) {
			return __( 'Form not found.', 'flx-forms' );
		}
		if ( ( $fs[0]['type'] ?? '' ) !== 'payment' || ! self::has_pay_box( $fs ) ) {
			return __( 'This form does not accept online payments.', 'flx-forms' );
		}
		if ( isset( $fs[0]['currency'] ) && strlen( (string) $fs[0]['currency'] ) > 2 && ! preg_match( '/^[A-Za-z]{3}$/', (string) $fs[0]['currency'] ) ) {
			return __( 'The currency of this form is not supported.', 'flx-forms' );
		}
		$method   = isset( $fs[0]['paymentmethod'] ) ? (string) $fs[0]['paymentmethod'] : 'charge';
		$currency = self::currency( $fs );
		if ( ! $provider ) {
			return __( 'Online payment is not available at the moment. Please try again later or contact us.', 'flx-forms' );
		}
		if ( ! $provider->supports( $currency, $method ) ) {
			return in_array( $method, self::METHODS, true ) && $provider->supports( $currency, 'charge' )
				? __( 'The payment method of this form is not supported.', 'flx-forms' )
				: __( 'The currency of this form is not supported.', 'flx-forms' );
		}
		$raw  = isset( $params['value'] ) && is_string( $params['value'] ) ? $params['value'] : '';
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
		$seen  = array();
		$total = 0.0;
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
			if ( 'prcfld' === ( $row['type'] ?? '' ) ) {
				$id = isset( $row['id_'] ) ? (string) $row['id_'] : '';
				if ( '' === $id || ! isset( $price_fields[ $id ] ) || isset( $seen[ $id ] ) ) {
					return $invalid;
				}
				$seen[ $id ] = true;
				$value       = $row['value'] ?? null;
				if ( ! is_numeric( $value ) || (float) $value <= 0 || ! self::num_equal( $value, $price ) ) {
					return $invalid;
				}
			}
			$total += (float) $price;
		}
		if ( $total > 999999.99 ) {
			return __( 'The payment amount is too large.', 'flx-forms' );
		}
		return true;
	}

	/**
	 * Price rows from the stored form definition (the core's fun_validation_pay_elements_efb). Used by the quote and
	 * by the start, so both charge exactly what was quoted.
	 *
	 * @param bool $quiet The core mails the admin a tamper warning when the browser's sum and the server's differ. A
	 *                    quote fires on every change, so it stays quiet; a real charge does not.
	 * @return array {price_total, email, valobj}
	 */
	public static function server_amount( $public, $rows, $fs, $quiet = false, $form_id = 0 ) {
		$mute = function () {
			return false;
		};
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

	/** Price text in the form's currency. */
	public static function money( $amount, $currency ) {
		if ( ! class_exists( '\Emsfb\Formbuilder' ) && defined( 'EMSFB_PLUGIN_DIRECTORY' ) && is_readable( EMSFB_PLUGIN_DIRECTORY . 'includes/class-Emsfb-formbuilder.php' ) ) {
			require_once EMSFB_PLUGIN_DIRECTORY . 'includes/class-Emsfb-formbuilder.php';
		}
		if ( class_exists( '\Emsfb\Formbuilder' ) ) {
			return \Emsfb\Formbuilder::formatPrice_efb( $amount, $currency );
		}
		return number_format_i18n( $amount, 2 ) . ' ' . $currency;
	}

	/**
	 * POST Emsfb/v1/forms/payment/quote: price the submitted rows exactly the way the start will, without charging.
	 * Body: {id, value}. Returns {success, amount, currency, formatted} or {success:false, m}.
	 */
	public static function quote( $request, $public ) {
		$no_cache = array( 'Cache-Control' => 'no-store' );
		if ( flxf_payment_rate_limited( 'quote', 120 ) ) {
			return new WP_REST_Response( array( 'success' => false, 'm' => __( 'Too many requests. Please wait a moment and try again.', 'flx-forms' ) ), 429, $no_cache );
		}
		$params   = $request->get_json_params();
		$provider = flxf_payment_active_provider();
		$check    = self::validate( $params, $provider );
		if ( true !== $check ) {
			return new WP_REST_Response( array( 'success' => false, 'm' => $check ), 400, $no_cache );
		}
		$fs   = self::load_form_structure( absint( $params['id'] ) );
		$rows = json_decode( str_replace( '\\', '', sanitize_text_field( $params['value'] ) ), true );
		if ( ! is_object( $public ) || ! method_exists( $public, 'fun_validation_pay_elements_efb' ) || ! is_array( $rows ) || null === $fs ) {
			return new WP_REST_Response( array( 'success' => false, 'm' => __( 'Invalid payment data. Please refresh the page and try again.', 'flx-forms' ) ), 400, $no_cache );
		}
		$obj = self::server_amount( $public, $rows, $fs, true, absint( $params['id'] ) );
		if ( ! empty( $obj['error'] ) && is_string( $obj['error'] ) ) {
			return new WP_REST_Response( array( 'success' => false, 'm' => $obj['error'] ), 200, $no_cache );
		}
		$amount   = round( (float) ( $obj['price_total'] ?? 0 ), 2 );
		$currency = self::currency( $fs );
		if ( ! is_finite( $amount ) || $amount < 0 ) {
			return new WP_REST_Response( array( 'success' => false, 'm' => __( 'Invalid payment data. Please refresh the page and try again.', 'flx-forms' ) ), 400, $no_cache );
		}
		return new WP_REST_Response(
			array(
				'success'   => true,
				'amount'    => $amount,
				'currency'  => $currency,
				'formatted' => self::money( $amount, $currency ),
				'provider'  => $provider ? $provider->id() : '',
			),
			200,
			$no_cache
		);
	}
}
