<?php
/**
 * Flux Donate 2.0: Flux Forms payment forms pay through the WooCommerce checkout.
 *
 * When the setting "forms_checkout" is on (default) the active Flux Forms payment provider is
 * "woocommerce". Pay on a form → the server prices the rows as before (donation type prices) →
 * the priced lines are kept on the server under a one-time token → the browser goes to
 * /?flxb_wc_handoff=<token>, the lines are put in the cart and the donor continues on the checkout
 * (any gateway: official iyzico, bank transfer, Flux Donate iyzico for monthly). When the order is
 * paid, the form's entry is completed with the submitted fields, its payment record is marked
 * completed and Flux Forms runs its "payment completed" automations (action `flxf_external_payment`,
 * Flux Forms 2.1.0+). The ledger records the donation from the order (one row per line).
 *
 * Forms without payment are not touched. Without WooCommerce Flux Donate' own providers stay in use.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

class FLXB_WC_FLXForm {

	const PROVIDER = 'woocommerce';
	const TTL      = 2 * HOUR_IN_SECONDS;

	/** @var array|null Rows the browser sent to forms/payment/start in this request. */
	public static $raw_rows = null;

	public static function init() {
		add_filter( 'flxf_payment_providers', array( __CLASS__, 'provider' ), 20 );
		add_filter( 'flxf_payment_active_provider', array( __CLASS__, 'active' ), 20 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'capture_rows' ), 10, 3 );
		add_action( 'flxb_payment_started', array( __CLASS__, 'started' ), 10, 3 );
		add_action( 'wp_loaded', array( __CLASS__, 'handoff' ), 30 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_status' ), 15, 4 );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'complete' ), 15 );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'thankyou' ), 8 );
	}

	public static function enabled() {
		return FLXB_WC::ready() && (bool) FLXB_WC::setting( 'forms_checkout' ) && (bool) apply_filters( 'flxb_wc_forms_checkout', true );
	}

	public static function provider( $providers ) {
		if ( class_exists( 'FLXB_Payment_Provider_Base' ) && ! class_exists( 'FLXB_Payment_WooCommerce' ) ) {
			require_once FLXB_DIR . 'includes/wc/class-flxb-payment-woocommerce.php';
		}
		if ( class_exists( 'FLXB_Payment_WooCommerce' ) ) {
			$providers[ self::PROVIDER ] = 'FLXB_Payment_WooCommerce';
		}
		return $providers;
	}

	public static function active( $id ) {
		return self::enabled() ? self::PROVIDER : $id;
	}

	/** Keep what the donor typed (all fields, not only the priced ones) for the entry. */
	public static function capture_rows( $response, $handler, $request ) {
		if ( $request instanceof WP_REST_Request && 0 === strcasecmp( '/' . trim( (string) $request->get_route(), '/' ), '/Emsfb/v1/forms/payment/start' ) ) {
			$p    = (array) $request->get_json_params();
			$rows = isset( $p['value'] ) && is_string( $p['value'] ) ? json_decode( str_replace( '\\', '', sanitize_text_field( $p['value'] ) ), true ) : null;
			self::$raw_rows = is_array( $rows ) ? self::clean_rows( $rows ) : null;
		}
		return $response;
	}

	/** Scalar fields only, sanitized; payment rows dropped (the server writes those). */
	public static function clean_rows( array $rows ) {
		$out = array();
		foreach ( $rows as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$type = (string) ( $r['type'] ?? '' );
			if ( false !== strpos( $type, 'pay' ) || 'payment' === ( $r['id_'] ?? '' ) ) {
				continue;
			}
			$row = array();
			foreach ( $r as $k => $v ) {
				if ( is_scalar( $v ) || null === $v ) {
					$row[ sanitize_key( (string) $k ) ] = is_string( $v ) ? sanitize_text_field( $v ) : $v;
				} elseif ( is_array( $v ) ) {
					$row[ sanitize_key( (string) $k ) ] = array_map( 'sanitize_text_field', array_filter( $v, 'is_scalar' ) );
				}
			}
			$out[] = $row;
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Hand-off: form → cart → checkout
	 * ------------------------------------------------------------------ */

	public static function key( $token ) {
		return 'flxb_wc_ho_' . md5( (string) $token );
	}

	public static function handoff_url( $token ) {
		return add_query_arg( 'flxb_wc_handoff', rawurlencode( $token ), home_url( '/' ) );
	}

	/** forms/payment/start stored the entry: remember its tracking code with the hand-off. */
	public static function started( $track, $provider, $result ) {
		if ( self::PROVIDER !== $provider || ! $result instanceof FLXB_Payment_Result ) {
			return;
		}
		$data = get_transient( self::key( $result->reference ) );
		if ( ! is_array( $data ) ) {
			return;
		}
		$data['track'] = (string) $track;
		foreach ( $data['lines'] as &$l ) {
			$l['form']['track'] = (string) $track;
		}
		unset( $l );
		set_transient( self::key( $result->reference ), $data, self::TTL );
	}

	public static function handoff() {
		if ( empty( $_GET['flxb_wc_handoff'] ) || ! function_exists( 'WC' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- one-time server token
			return;
		}
		$token = sanitize_text_field( wp_unslash( $_GET['flxb_wc_handoff'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data  = get_transient( self::key( $token ) );
		if ( ! is_array( $data ) || empty( $data['lines'] ) ) {
			wc_add_notice( __( 'This payment link has expired. Please fill in the form again.', 'flx-donate' ), 'error' );
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}
		if ( ! WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		/* The same hand-off opened twice: do not add its lines again. */
		foreach ( WC()->cart->get_cart() as $k => $item ) {
			if ( isset( $item[ FLXB_WC_Cart::KEY ]['handoff'] ) && $item[ FLXB_WC_Cart::KEY ]['handoff'] === md5( $token ) ) {
				WC()->cart->remove_cart_item( $k );
			}
		}
		foreach ( $data['lines'] as $l ) {
			$l['handoff'] = md5( $token );
			$r            = FLXB_WC_Cart::add_line( $l );
			if ( is_wp_error( $r ) ) {
				wc_add_notice( $r->get_error_message(), 'error' );
				wp_safe_redirect( wc_get_cart_url() );
				exit;
			}
		}
		$c = (array) ( $data['contact'] ?? array() );
		if ( WC()->customer ) {
			$name  = trim( (string) ( $c['name'] ?? '' ) );
			$parts = '' !== $name ? preg_split( '/\s+/u', $name ) : array();
			$last  = count( $parts ) > 1 ? array_pop( $parts ) : '';
			if ( $parts && '' === WC()->customer->get_billing_first_name() ) {
				WC()->customer->set_billing_first_name( implode( ' ', $parts ) );
				WC()->customer->set_billing_last_name( $last );
			}
			if ( ! empty( $c['email'] ) && is_email( $c['email'] ) && '' === WC()->customer->get_billing_email() ) {
				WC()->customer->set_billing_email( (string) $c['email'] );
			}
			if ( ! empty( $c['phone'] ) && '' === WC()->customer->get_billing_phone() ) {
				WC()->customer->set_billing_phone( (string) $c['phone'] );
			}
			WC()->customer->save();
		}
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Paid order → form entry, payment record, automations
	 * ------------------------------------------------------------------ */

	public static function on_status( $order_id, $from, $to, $order = null ) {
		if ( in_array( $to, array( 'processing', 'completed' ), true ) ) {
			self::complete( $order instanceof WC_Order ? $order : $order_id );
		}
	}

	/** Tracking codes of the Flux Forms entries paid by an order. */
	public static function tracks( WC_Order $order ) {
		$out = array();
		foreach ( FLXB_WC_Cart::order_lines( $order ) as $item ) {
			$l = FLXB_WC_Cart::item_line( $item );
			if ( ! empty( $l['form']['track'] ) ) {
				$out[ (string) $l['form']['track'] ] = (int) ( $l['form']['id'] ?? 0 );
			}
		}
		return $out;
	}

	/** Paid amount of the order lines that belong to one form entry. */
	private static function track_total( WC_Order $order, $track ) {
		$sum = 0.0;
		foreach ( FLXB_WC_Cart::order_lines( $order ) as $item ) {
			$l = FLXB_WC_Cart::item_line( $item );
			if ( (string) ( $l['form']['track'] ?? '' ) === (string) $track ) {
				$sum += FLXB_WC_Ledger::line_amount( $item );
			}
		}
		return round( $sum, 2 );
	}

	public static function complete( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order || ! $order->is_paid() || ! class_exists( 'FLXB_Payment_Records' ) ) {
			return;
		}
		foreach ( self::tracks( $order ) as $track => $form_id ) {
			$row = FLXB_Payment_Records::get_by_track( $track, self::PROVIDER );
			if ( ! $row ) {
				continue;
			}
			if ( ! (int) $order->get_meta( '_flxb_pay_id' ) ) {
				$order->update_meta_data( '_flxb_pay_id', (int) $row->id );
				$order->save_meta_data();
			}
			if ( 'pending' !== $row->status ) {
				continue;
			}
			$m             = ! empty( $row->meta ) ? json_decode( (string) $row->meta, true ) : array();
			$m             = is_array( $m ) ? $m : array();
			$m['wc_order'] = (int) $order->get_id();
			FLXB_Payment_Records::update(
				$row->id,
				array(
					'status'     => 'completed',
					'capture_id' => (string) $order->get_id(),
					'meta'       => $m,
				)
			);
			$rows = self::finish_entry( $track, (array) ( $m['rows'] ?? array() ), $order );
			/* translators: %s: Flux Forms tracking code */
			$order->add_order_note( sprintf( __( 'Flux Forms entry %s completed by this payment.', 'flx-donate' ), $track ) );
			$ctx = array(
				'event_type'                => 'payment',
				'track_code'                => $track,
				'form_id'                   => (int) ( $form_id ? $form_id : $row->form_id ),
				'form_fields'               => class_exists( 'FLXB_FLXForm_Bridge' ) ? FLXB_FLXForm_Bridge::form_structure( (int) $row->form_id ) : array(),
				'submitted_values'          => (array) ( $m['rows'] ?? array() ),
				'enriched_submitted_values' => $rows,
				'page_url'                  => (string) ( $m['page_url'] ?? '' ),
				'wc_order_id'               => (int) $order->get_id(),
				'verified_payment'          => array(
					'amount'    => self::track_total( $order, $track ),
					'currency'  => $order->get_currency(),
					'reference' => (string) $order->get_order_number(),
					'source'    => 'woocommerce',
				),
			);
			/* Flux Forms 2.1.0+: "payment completed" automations (certificates, e-mails, webhooks). */
			do_action( 'flxf_external_payment', $ctx );
			do_action( 'flxb_wc_form_paid', $track, $order, $row );
		}
	}

	/**
	 * Write the submitted fields and the settled payment row into the form entry (read_ 2 → 0).
	 *
	 * @return array Entry rows.
	 */
	private static function finish_entry( $track, array $fields, WC_Order $order ) {
		global $wpdb;
		$t     = $wpdb->prefix . 'emsfb_msg_';
		$saved = $wpdb->get_var( $wpdb->prepare( "SELECT content FROM `{$t}` WHERE track = %s LIMIT 1", $track ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$saved = is_string( $saved ) ? json_decode( str_replace( '\\', '', $saved ), true ) : array();
		$saved = is_array( $saved ) ? $saved : array();
		foreach ( $saved as &$r ) {
			if ( is_array( $r ) && 'payment' === ( $r['type'] ?? '' ) ) {
				$r['status']         = 'completed';
				$r['paymentIntent']  = (string) $order->get_order_number();
				$r['paymentGateway'] = self::PROVIDER;
				$r['wcMethod']       = $order->get_payment_method_title();
			}
		}
		unset( $r );
		$rows = array_values( array_merge( $fields, $saved ) );
		$wpdb->update(
			$t,
			array(
				'content'   => sanitize_text_field( wp_json_encode( $rows, JSON_UNESCAPED_UNICODE ) ),
				'read_'     => 0,
				'read_date' => wp_date( 'Y-m-d H:i:s' ),
			),
			array( 'track' => $track )
		);
		return $rows;
	}

	/** Certificate cards made by the form's automation, on the order received page. */
	public static function thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		foreach ( array_keys( self::tracks( $order ) ) as $track ) {
			$c = get_transient( 'flxf_auto_ty_' . md5( $track ) );
			if ( is_array( $c ) && ! empty( $c['html'] ) ) {
				do_action( 'flxf_enqueue_certificate_card' ); // Flux Forms 2.1.0: card styles and preview script.
				echo '<div class="flxwb-form-cards">' . wp_kses( (string) $c['html'], self::card_tags() ) . '</div>';
			}
		}
	}

	/** Post HTML plus the canvas and icon SVG of Flux Forms's certificate card. */
	private static function card_tags() {
		$t      = wp_kses_allowed_html( 'post' );
		$common = array( 'class' => true, 'style' => true, 'aria-hidden' => true, 'role' => true );
		$svg    = array( 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true );
		$t['canvas']   = $common + array( 'width' => true, 'height' => true );
		$t['figure']   = ( isset( $t['figure'] ) ? $t['figure'] : array() ) + $common + array( 'aria-busy' => true, 'title' => true );
		$t['svg']      = $common + $svg + array( 'viewbox' => true, 'width' => true, 'height' => true, 'xmlns' => true, 'focusable' => true );
		$t['path']     = $svg + array( 'd' => true );
		$t['circle']   = $svg + array( 'cx' => true, 'cy' => true, 'r' => true );
		$t['rect']     = $svg + array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true );
		$t['polyline'] = $svg + array( 'points' => true );
		$t['g']        = $svg;
		return $t;
	}
}
