<?php
/**
 * Flux Donate 2.21.0: EsnekPOS card fields on the checkout, 3D Secure in a modal, installments.
 *
 *  - Block checkout: the EsnekPOS method shows card fields (number, expiry, CVV, name on card), the phone
 *    when the order has none, and the installment options once the card's BIN is known.
 *  - Place order: the Store API order is paid with EsnekPOS's 3D payment (EYV3DPay). The card goes from the
 *    browser to this site over HTTPS and straight on to EsnekPOS; it is never saved, logged or put on the order.
 *  - 3D Secure: the bank page opens in a modal on the checkout (iframe). EsnekPOS posts the result back to our
 *    return URL inside the iframe; the order is settled with ProcessQuery as before (FLXB_WC_Esnekpos::settle)
 *    and a small page tells the checkout the result (postMessage). The donor never leaves the checkout.
 *    If the bank page cannot open in a frame, "Open in this window" falls back to the full-page flow.
 *  - Installments: EYVBinService (card family) + GetInstallments (rates). With "donor pays the installment
 *    difference", the difference to a single payment is added to the order as a fee line before the charge.
 *  - Without card fields (order-pay page, payment links, classic checkout) the old payment page flow stays.
 *
 * Settings: WooCommerce › Settings › Payments › EsnekPOS (Flux Donate section).
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

final class FLXB_WC_Esnekpos_Direct {

	const NS  = 'flxb/v1';
	const FEE = '_flxb_esn_inst_fee';

	public static function init() {
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( __CLASS__, 'blocks' ), 20 );
		add_action( 'woocommerce_rest_checkout_process_payment_with_context', array( __CLASS__, 'process' ), 5, 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'woocommerce_settings_api_form_fields_' . FLXB_WC_Esnekpos::ID, array( __CLASS__, 'form_fields' ), 20 );
	}

	/* ---------- Settings ---------- */

	public static function opt( $key ) {
		$s = FLXB_WC_Esnekpos::settings();
		$d = array(
			'flxb_inline'       => 'yes',
			'flxb_installments' => 'yes',
			'flxb_inst_max'     => '12',
			'flxb_inst_payer'   => 'donor',
			'flxb_public_token' => '',
		);
		return isset( $s[ $key ] ) && '' !== $s[ $key ] ? $s[ $key ] : ( $d[ $key ] ?? '' );
	}

	public static function inline() {
		return 'yes' === self::opt( 'flxb_inline' );
	}

	public static function form_fields( $fields ) {
		$add = array(
			'flxb_inline_title' => array(
				'title' => __( 'Card fields on the checkout', 'flx-donate' ),
				'type'  => 'title',
			),
			'flxb_inline'       => array(
				'title'       => __( 'Card fields', 'flx-donate' ),
				'type'        => 'checkbox',
				'label'       => __( 'Take the card on the checkout and open 3D Secure in a window on the same page', 'flx-donate' ),
				'default'     => 'yes',
				'description' => __( 'The card goes to EsnekPOS through this site over HTTPS; it is not saved. Off: donors go to EsnekPOS\'s payment page.', 'flx-donate' ),
			),
			'flxb_installments' => array(
				'title'   => __( 'Installments', 'flx-donate' ),
				'type'    => 'checkbox',
				'label'   => __( 'Offer installments once the card is known', 'flx-donate' ),
				'default' => 'yes',
			),
			'flxb_inst_max'     => array(
				'title'   => __( 'Most installments', 'flx-donate' ),
				'type'    => 'number',
				'default' => '12',
			),
			'flxb_inst_payer'   => array(
				'title'   => __( 'Installment difference', 'flx-donate' ),
				'type'    => 'select',
				'default' => 'donor',
				'options' => array(
					'donor'    => __( 'The donor pays it (added to the order as a separate line)', 'flx-donate' ),
					'merchant' => __( 'We pay it (the donor pays the same amount)', 'flx-donate' ),
				),
			),
			'flxb_public_token' => array(
				'title'       => __( 'Public token', 'flx-donate' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'MERCHANT_PUBLIC_TOKEN from the EsnekPOS panel, used for the installment rates.', 'flx-donate' ),
			),
		);
		return (array) $fields + $add;
	}

	/* ---------- Block checkout ---------- */

	public static function blocks( $registry ) {
		if ( ! self::inline() || ! FLXB_WC_Esnekpos::present() || ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) || $registry->is_registered( FLXB_WC_Esnekpos::ID ) ) {
			return;
		}
		require_once FLXB_DIR . 'includes/wc/gateways/class-flxb-wc-esnekpos-blocks.php';
		$registry->register( new FLXB_WC_Esnekpos_Blocks() );
	}

	/** Strings and settings for the card form (esnekpos-blocks.js). */
	public static function script_data() {
		$gw = FLXB_WC_Gateways::all()[ FLXB_WC_Esnekpos::ID ] ?? null;
		return array(
			'title'        => $gw ? wp_strip_all_tags( (string) $gw->get_title() ) : 'EsnekPOS',
			'installments' => 'yes' === self::opt( 'flxb_installments' ),
			'rest'         => esc_url_raw( rest_url( self::NS . '/esnekpos/' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'test'         => FLXB_WC_Esnekpos::credentials()['test'],
			'i18n'         => array(
				'card'         => __( 'Card number', 'flx-donate' ),
				'exp'          => __( 'Expiry (MM/YY)', 'flx-donate' ),
				'cvv'          => __( 'CVV', 'flx-donate' ),
				'name'         => __( 'Name on card', 'flx-donate' ),
				'phone'        => __( 'Mobile phone', 'flx-donate' ),
				'phoneHelp'    => __( 'Card payments need a phone number.', 'flx-donate' ),
				'inst'         => __( 'Installments', 'flx-donate' ),
				'single'       => __( 'Single payment', 'flx-donate' ),
				/* translators: %d: number of installments */
				'nInst'        => __( '%d installments', 'flx-donate' ),
				/* translators: 1: amount per installment, 2: total */
				'perInst'      => __( '%1$s × installment · total %2$s', 'flx-donate' ),
				'instWait'     => __( 'Installment options appear once the card number is entered.', 'flx-donate' ),
				'instNone'     => __( 'This card can be used for a single payment.', 'flx-donate' ),
				'sec'          => __( 'Protected by 3D Secure: your bank confirms the payment with a code sent to your phone.', 'flx-donate' ),
				'secShort'     => __( 'Your card details are sent encrypted and are not stored on this site.', 'flx-donate' ),
				'badCard'      => __( 'Please check the card number.', 'flx-donate' ),
				'badExp'       => __( 'Please check the expiry date (MM/YY).', 'flx-donate' ),
				'expired'      => __( 'This card has expired.', 'flx-donate' ),
				'badCvv'       => __( 'Please enter the 3 or 4 digit security code on the back of the card.', 'flx-donate' ),
				'badName'      => __( 'Please write the name on the card.', 'flx-donate' ),
				'badPhone'     => __( 'Please enter a valid mobile phone number, e.g. 0532 123 45 67.', 'flx-donate' ),
				'modalTitle'   => __( '3D Secure verification', 'flx-donate' ),
				'modalNote'    => __( 'This window is your bank\'s page. You do not need to leave this page.', 'flx-donate' ),
				'modalLoading' => __( 'Opening your bank\'s page…', 'flx-donate' ),
				'modalBlank'   => __( 'Page empty? Open it in this window', 'flx-donate' ),
				'close'        => __( 'Close', 'flx-donate' ),
				'closeAsk'     => __( 'Stop the verification? Your card will not be charged.', 'flx-donate' ),
				'checking'     => __( 'Checking the payment…', 'flx-donate' ),
				'cancelled'    => __( '3D Secure verification was not completed, so no payment was taken from your card. You can try again.', 'flx-donate' ),
				'failTitle'    => __( 'Payment not received', 'flx-donate' ),
			),
		);
	}

	/* ---------- REST: installments, status ---------- */

	public static function routes() {
		register_rest_route(
			self::NS,
			'/esnekpos/installments',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'rest_installments' ),
				'args'                => array(
					'bin'    => array( 'type' => 'string', 'required' => true ),
					'amount' => array( 'type' => 'number', 'required' => true ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/esnekpos/status',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'rest_status' ),
			)
		);
	}

	/** Installment options for a BIN and an amount (display only; the charge recomputes them). */
	public static function rest_installments( WP_REST_Request $r ) {
		$bin = substr( preg_replace( '/\D+/', '', (string) $r['bin'] ), 0, 8 );
		$amt = round( (float) $r['amount'], 2 );
		if ( strlen( $bin ) < 6 || $amt <= 0 || 'yes' !== self::opt( 'flxb_installments' ) ) {
			return rest_ensure_response( array( 'options' => array() ) );
		}
		$res = rest_ensure_response( self::options( $bin, $amt ) );
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	/** After the modal was closed: settle from ProcessQuery and say where to go. */
	public static function rest_status( WP_REST_Request $r ) {
		$order = wc_get_order( absint( $r['order'] ) );
		$key   = sanitize_text_field( (string) $r['key'] );
		if ( ! $order || '' === $key || ! hash_equals( $order->get_order_key(), $key ) || FLXB_WC_Esnekpos::ID !== $order->get_payment_method() ) {
			return new WP_Error( 'flxb_esn', __( 'Order not found.', 'flx-donate' ), array( 'status' => 404 ) );
		}
		$ref = (string) $order->get_meta( FLXB_WC_Esnekpos::REF );
		if ( ! $order->is_paid() && '' !== $ref ) {
			FLXB_WC_Esnekpos::settle( $order, $ref, false, '' );
			$order = wc_get_order( $order->get_id() );
		}
		return rest_ensure_response(
			array(
				'paid' => $order->is_paid(),
				'url'  => $order->is_paid() ? $order->get_checkout_order_received_url() : '',
			)
		);
	}

	/**
	 * Card family for a BIN (cached a day) and the installment rows for an amount.
	 *
	 * @return array{family:string,bank:string,brand:string,options:array}
	 */
	public static function options( $bin, $amount ) {
		$bin6 = substr( $bin, 0, 6 );
		$key  = 'flxb_esn_bin_' . $bin6;
		$info = get_transient( $key );
		if ( ! is_array( $info ) ) {
			$j    = FLXB_WC_Esnekpos::post( '/api/services/EYVBinService', array( 'CardNumber' => $bin ) );
			$info = is_array( $j ) ? array(
				'family' => strtolower( trim( (string) ( $j['Card_Family'] ?? '' ) ) ),
				'bank'   => sanitize_text_field( (string) ( $j['Bank_Name'] ?? '' ) ),
				'brand'  => strtolower( sanitize_text_field( (string) ( $j['Bank_Brand'] ?? '' ) ) ),
				'type'   => strtolower( sanitize_text_field( (string) ( $j['Card_Type'] ?? '' ) ) ),
			) : array( 'family' => '', 'bank' => '', 'brand' => '', 'type' => '' );
			set_transient( $key, $info, is_array( $j ) ? DAY_IN_SECONDS : 5 * MINUTE_IN_SECONDS );
		}
		$out = $info + array( 'options' => array() );
		if ( '' === $info['family'] || 'credit' !== ( $info['type'] ?: 'credit' ) ) {
			return $out; // debit / prepaid cards and unknown cards: single payment
		}
		$rates = self::rates( $amount );
		$max   = max( 1, min( 36, (int) self::opt( 'flxb_inst_max' ) ) );
		$base  = null;
		foreach ( $rates as $row ) {
			if ( $row['family'] === $info['family'] && 1 === $row['n'] ) {
				$base = $row['rate'];
			}
		}
		foreach ( $rates as $row ) {
			if ( $row['family'] !== $info['family'] || $row['n'] < 2 || $row['n'] > $max ) {
				continue;
			}
			$total = $amount + self::fee( $amount, $row['rate'], null === $base ? 0.0 : $base );
			$out['options'][ $row['n'] ] = array(
				'n'     => $row['n'],
				'total' => $total,
				'per'   => round( $total / $row['n'], 2 ),
			);
		}
		ksort( $out['options'] );
		$out['options'] = array_values( $out['options'] );
		return $out;
	}

	/** Rate rows from GetInstallments (cached an hour per amount). @return array<int,array{family:string,n:int,rate:float}> */
	public static function rates( $amount ) {
		$key  = 'flxb_esn_rates_' . md5( (string) $amount );
		$rows = get_transient( $key );
		if ( is_array( $rows ) ) {
			return $rows;
		}
		$c = FLXB_WC_Esnekpos::credentials();
		$j = FLXB_WC_Esnekpos::post(
			'/api/services/GetInstallments',
			array(
				'MERCHANT'                => $c['merchant'],
				'MERCHANT_KEY'            => $c['key'],
				'AMOUNT'                  => number_format( (float) $amount, 2, ',', '' ),
				'MERCHANT_PUBLIC_TOKEN'   => (string) self::opt( 'flxb_public_token' ),
				'COMMISSION_FOR_CUSTOMER' => 'donor' === self::opt( 'flxb_inst_payer' ) ? 1 : 0,
			)
		);
		$rows = array();
		if ( is_array( $j ) && FLXB_WC_Esnekpos::ok( $j ) && ! empty( $j['INSTALLMENTS'] ) && is_array( $j['INSTALLMENTS'] ) ) {
			foreach ( $j['INSTALLMENTS'] as $i ) {
				if ( is_array( $i ) && isset( $i['FAMILY'], $i['INSTALLMENT'] ) ) {
					$rows[] = array(
						'family' => strtolower( trim( (string) $i['FAMILY'] ) ),
						'n'      => (int) $i['INSTALLMENT'],
						'rate'   => (float) str_replace( ',', '.', (string) ( $i['RATE'] ?? 0 ) ),
					);
				}
			}
		}
		set_transient( $key, $rows, $rows ? HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS );
		return $rows;
	}

	/** Installment difference the donor pays: the extra commission over a single payment. */
	public static function fee( $amount, $rate, $base ) {
		if ( 'donor' !== self::opt( 'flxb_inst_payer' ) || $rate <= $base ) {
			return 0.0;
		}
		return (float) apply_filters( 'flxb_esnekpos_installment_fee', round( $amount * ( $rate - $base ), 2 ), $amount, $rate, $base );
	}

	/* ---------- Place order (Store API) ---------- */

	/**
	 * Pay a block checkout order with the card from the card fields.
	 *
	 * @param \Automattic\WooCommerce\StoreApi\Payments\PaymentContext $context
	 * @param \Automattic\WooCommerce\StoreApi\Payments\PaymentResult  $result
	 */
	public static function process( $context, &$result ) {
		if ( FLXB_WC_Esnekpos::ID !== $context->payment_method || ! self::inline() ) {
			return;
		}
		$d = (array) $context->payment_data;
		if ( empty( $d['flxb_cc'] ) ) {
			return; // no card fields (older script): WooCommerce runs the module, the payment page flow follows
		}
		$order = $context->order;
		$card  = array(
			'number' => preg_replace( '/\D+/', '', (string) ( $d['flxb_cc_number'] ?? '' ) ),
			'exp'    => preg_replace( '/\D+/', '', (string) ( $d['flxb_cc_exp'] ?? '' ) ),
			'cvv'    => preg_replace( '/\D+/', '', (string) ( $d['flxb_cc_cvv'] ?? '' ) ),
			'name'   => trim( sanitize_text_field( (string) ( $d['flxb_cc_name'] ?? '' ) ) ),
		);
		$inst  = max( 1, min( 36, absint( $d['flxb_inst'] ?? 1 ) ) );
		$phone = sanitize_text_field( (string) ( $d['flxb_phone'] ?? '' ) );
		unset( $d );
		if ( '' !== $phone && '' === trim( (string) $order->get_billing_phone() ) ) {
			$n = function_exists( 'flxb_normalize_phone' ) ? flxb_normalize_phone( $phone ) : '';
			if ( '' === $n ) {
				self::result( $result, $order, new WP_Error( 'flxb_esn_phone', __( 'Please enter a valid mobile phone number, e.g. 0532 123 45 67.', 'flx-donate' ) ) );
				return;
			}
			$order->set_billing_phone( $n );
		}
		$out = self::charge( $order, $card, $inst );
		unset( $card );
		self::result( $result, $order, $out );
	}

	/** @param \Automattic\WooCommerce\StoreApi\Payments\PaymentResult $result */
	private static function result( &$result, WC_Order $order, $out ) {
		if ( is_wp_error( $out ) ) {
			$result->set_status( 'failure' );
			$result->set_payment_details( array( 'flxb_error' => $out->get_error_message() ) );
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'flxb_esnekpos', esc_html( $out->get_error_message() ), 400 );
		}
		$result->set_status( 'success' );
		$result->set_redirect_url( $order->get_checkout_order_received_url() );
		if ( 'paid' !== $out ) {
			$result->set_payment_details(
				array(
					'flxb_3ds'   => $out,
					'flxb_order' => (string) $order->get_id(),
					'flxb_key'   => $order->get_order_key(),
				)
			);
		}
	}

	/**
	 * Start EsnekPOS's 3D payment for an order.
	 *
	 * @param array  $card  number, exp (MMYY or MMYYYY), cvv, name. Never stored.
	 * @return string|WP_Error 3D Secure page URL, 'paid' (no 3D step), or an error for the donor.
	 */
	public static function charge( WC_Order $order, array $card, $inst ) {
		$c = FLXB_WC_Esnekpos::credentials();
		if ( '' === $c['merchant'] || '' === $c['key'] ) {
			return new WP_Error( 'flxb_esn', __( 'Card payments are not configured on this site. Please choose another payment method.', 'flx-donate' ) );
		}
		$v = self::validate( $card );
		if ( is_wp_error( $v ) ) {
			return $v;
		}
		FLXB_WC_Cart::fill_address( $order );
		$phone = FLXB_WC_Esnekpos::phone( $order );
		if ( '' === $phone ) {
			return new WP_Error( 'flxb_esn_phone', __( 'Card payments need a phone number. Please write your phone number and try again.', 'flx-donate' ) );
		}
		/* Installments: the difference goes on the order before the charge, so the amounts match ProcessQuery. */
		self::clear_fee( $order );
		$base_total = round( (float) $order->get_total(), 2 );
		if ( $inst > 1 ) {
			$opts = self::options( $card['number'], $base_total );
			$pick = null;
			foreach ( $opts['options'] as $o ) {
				if ( (int) $o['n'] === (int) $inst ) {
					$pick = $o;
				}
			}
			if ( ! $pick ) {
				return new WP_Error( 'flxb_esn_inst', __( 'This card cannot be paid in the chosen installments. Please choose another option.', 'flx-donate' ) );
			}
			$extra = round( (float) $pick['total'] - $base_total, 2 );
			if ( $extra > 0 ) {
				$fee = new WC_Order_Item_Fee();
				/* translators: %d: number of installments */
				$fee->set_name( sprintf( __( 'Installment difference (%d installments)', 'flx-donate' ), $inst ) );
				$fee->set_amount( $extra );
				$fee->set_total( $extra );
				$fee->set_tax_status( 'none' );
				$fee->add_meta_data( self::FEE, 1, true );
				$order->add_item( $fee );
				$order->calculate_totals( false );
			}
		}
		$order->update_meta_data( '_flxb_esn_installments', $inst );
		$order->save();

		$exp_m = substr( $card['exp'], 0, 2 );
		$exp_y = substr( $card['exp'], 2 );
		$exp_y = 2 === strlen( $exp_y ) ? '20' . $exp_y : $exp_y;
		$ref   = substr( $order->get_id() . '-' . base_convert( (string) time(), 10, 36 ), 0, 24 );
		$first = trim( (string) $order->get_billing_first_name() );
		$last  = trim( (string) $order->get_billing_last_name() );
		$city  = trim( (string) $order->get_billing_city() );
		$body  = array(
			'Config'     => array(
				'MERCHANT'         => $c['merchant'],
				'MERCHANT_KEY'     => $c['key'],
				'BACK_URL'         => add_query_arg( 'm', '1', FLXB_WC_Esnekpos::return_url( $order ) ),
				'PRICES_CURRENCY'  => strtoupper( $order->get_currency() ),
				'ORDER_REF_NUMBER' => $ref,
				'ORDER_AMOUNT'     => FLXB_WC_Esnekpos::amount( $order->get_total() ),
			),
			'CreditCard' => array(
				'CC_NUMBER'          => $card['number'],
				'EXP_MONTH'          => $exp_m,
				'EXP_YEAR'           => $exp_y,
				'CC_CVV'             => $card['cvv'],
				'CC_OWNER'           => $card['name'],
				'INSTALLMENT_NUMBER' => (string) $inst,
			),
			'Customer'   => array(
				'FIRST_NAME' => '' !== $first ? $first : __( 'Donor', 'flx-donate' ),
				'LAST_NAME'  => '' !== $last ? $last : '-',
				'MAIL'       => (string) $order->get_billing_email(),
				'PHONE'      => $phone,
				'CITY'       => $city,
				'STATE'      => '' !== trim( (string) $order->get_billing_state() ) ? (string) $order->get_billing_state() : $city,
				'ADDRESS'    => trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() ),
				'CLIENT_IP'  => (string) WC_Geolocation::get_ip_address(),
			),
			'Product'    => FLXB_WC_Esnekpos::products( $order ),
		);
		unset( $card );
		$r = FLXB_WC_Esnekpos::post( '/api/pay/EYV3DPay', $body );
		unset( $body ); // the card is not kept anywhere
		if ( is_wp_error( $r ) ) {
			/* translators: %s: error */
			$order->add_order_note( sprintf( __( 'EsnekPOS payment could not start:%s', 'flx-donate' ), $r->get_error_message() ) );
			return new WP_Error( 'flxb_esn', __( 'We could not reach the payment service. No payment was taken. Please try again in a moment.', 'flx-donate' ) );
		}
		$msg = FLXB_WC_Esnekpos::message( $r );
		if ( ! FLXB_WC_Esnekpos::ok( $r ) ) {
			/* translators: 1: EsnekPOS message, 2: test or live */
			$order->add_order_note( sprintf( __( 'EsnekPOS payment could not start:%1$s(%2$s).', 'flx-donate' ), '' !== $msg ? $msg : '-', $c['test'] ? __( 'test mode', 'flx-donate' ) : __( 'live', 'flx-donate' ) ) );
			return new WP_Error( 'flxb_esn', self::explain( $msg ) );
		}
		$order->update_meta_data( FLXB_WC_Esnekpos::REF, $ref );
		$order->update_meta_data( '_flxb_esnek_test', $c['test'] ? 1 : 0 );
		$order->save();
		$url = (string) ( $r['URL_3DS'] ?? '' );
		$no3 = in_array( strtolower( (string) ( $r['IS_NOT_3D_PAYMENT'] ?? '' ) ), array( '1', 'true' ), true );
		if ( $no3 || '' === $url ) {
			$err = FLXB_WC_Esnekpos::settle( $order, $ref, true, '' );
			return wc_get_order( $order->get_id() )->is_paid() ? 'paid' : new WP_Error( 'flxb_esn', self::explain( '' !== $err ? $err : $msg ) );
		}
		if ( ! preg_match( '#^' . ( 0 === strpos( FLXB_WC_Esnekpos::base_url(), 'http://' ) ? 'https?' : 'https' ) . '://#', $url ) ) {
			return new WP_Error( 'flxb_esn', __( 'The payment could not be started. Please try again.', 'flx-donate' ) );
		}
		return $url;
	}

	/** Card checks before anything is sent. */
	public static function validate( array $card ) {
		$n = $card['number'];
		if ( strlen( $n ) < 13 || strlen( $n ) > 19 || ! self::luhn( $n ) ) {
			return new WP_Error( 'flxb_cc', __( 'Please check the card number.', 'flx-donate' ) );
		}
		if ( ! preg_match( '/^(0[1-9]|1[0-2])(\d{2}|\d{4})$/', $card['exp'], $m ) ) {
			return new WP_Error( 'flxb_cc', __( 'Please check the expiry date (MM/YY).', 'flx-donate' ) );
		}
		$y = (int) ( 2 === strlen( $m[2] ) ? '20' . $m[2] : $m[2] );
		if ( $y * 100 + (int) $m[1] < (int) gmdate( 'Y' ) * 100 + (int) gmdate( 'n' ) ) {
			return new WP_Error( 'flxb_cc', __( 'This card has expired.', 'flx-donate' ) );
		}
		if ( ! preg_match( '/^\d{3,4}$/', $card['cvv'] ) ) {
			return new WP_Error( 'flxb_cc', __( 'Please enter the 3 or 4 digit security code on the back of the card.', 'flx-donate' ) );
		}
		if ( mb_strlen( $card['name'] ) < 3 ) {
			return new WP_Error( 'flxb_cc', __( 'Please write the name on the card.', 'flx-donate' ) );
		}
		return true;
	}

	public static function luhn( $n ) {
		$sum = 0;
		$alt = false;
		for ( $i = strlen( $n ) - 1; $i >= 0; $i-- ) {
			$d = (int) $n[ $i ];
			if ( $alt ) {
				$d *= 2;
				if ( $d > 9 ) {
					$d -= 9;
				}
			}
			$sum += $d;
			$alt  = ! $alt;
		}
		return 0 === $sum % 10;
	}

	private static function clear_fee( WC_Order $order ) {
		$changed = false;
		foreach ( $order->get_fees() as $id => $fee ) {
			if ( $fee->get_meta( self::FEE ) ) {
				$order->remove_item( $id );
				$changed = true;
			}
		}
		if ( $changed ) {
			$order->calculate_totals( false );
		}
	}

	/** A bank / EsnekPOS message as a sentence the donor can act on. */
	public static function explain( $msg ) {
		$msg = trim( (string) $msg );
		if ( 0 === stripos( $msg, 'EsnekPOS: ' ) ) {
			$msg = trim( substr( $msg, 10 ) );
		}
		$tail = __( 'No payment was taken from your card. You can try another card or choose bank transfer.', 'flx-donate' );
		if ( '' === $msg || '-' === $msg ) {
			return __( 'Your bank did not approve the payment.', 'flx-donate' ) . ' ' . $tail;
		}
		/* translators: %s: reason from the bank or the payment service */
		return sprintf( __( 'Your bank did not approve the payment: %s.', 'flx-donate' ), rtrim( $msg, '.' ) ) . ' ' . $tail;
	}

	/**
	 * The return page inside the 3D Secure modal: tells the checkout the result and stops.
	 * Outside a frame (fallback flow) it goes on to the result page itself.
	 */
	public static function bridge( WC_Order $order, $err ) {
		$order = wc_get_order( $order->get_id() );
		$paid  = $order->is_paid();
		$msg   = $paid ? '' : self::explain( $err );
		$url   = $paid ? $order->get_checkout_order_received_url() : add_query_arg( FLXB_WC_Esnekpos::ERR, rawurlencode( $msg ), wc_get_checkout_url() );
		$data  = array(
			'flxb3ds' => 1,
			'paid'    => $paid,
			'url'     => $url,
			'message' => $msg,
		);
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		$origin = wp_parse_url( home_url(), PHP_URL_SCHEME ) . '://' . wp_parse_url( home_url(), PHP_URL_HOST ) . ( wp_parse_url( home_url(), PHP_URL_PORT ) ? ':' . wp_parse_url( home_url(), PHP_URL_PORT ) : '' );
		echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . esc_html( $paid ? __( 'Payment received', 'flx-donate' ) : __( 'Payment not received', 'flx-donate' ) ) . '</title></head>'
			. '<body style="font:15px system-ui,sans-serif;display:grid;place-items:center;min-height:90vh;color:#374151"><p>' . esc_html( __( 'Checking the payment…', 'flx-donate' ) ) . '</p>'
			. '<script>(function(d,o){try{if(window.parent&&window.parent!==window){window.parent.postMessage(d,o);return;}}catch(e){}location.replace(d.url);})(' . wp_json_encode( $data ) . ',' . wp_json_encode( $origin ) . ');</script></body></html>';
		exit;
	}
}
