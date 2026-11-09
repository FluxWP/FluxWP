<?php
/**
 * Flux Donate 2.1.0: our own card fields for iyzico (setting card_ui = fields, the default).
 *
 *  - The donor types the card on the checkout (block and classic, also the order-pay page) or picks
 *    a card iyzico stores for them. The card goes from the checkout request straight to iyzico's
 *    3D Secure initialize call; it is never written to the database, the order or a log.
 *  - Installments: the BIN (first 8 digits) is sent to flxb/v1/wc/iyzico/installments, which asks
 *    iyzico for the prices; the server asks again when paying, so the browser cannot change a price.
 *  - 3D Secure: iyzico's bank page HTML is kept for a few minutes (one use) and shown on
 *    /?flxb_3ds=<key>; the bank posts back to flxb/v1/wc/iyzico/3ds, where the payment is completed
 *    with iyzico (3dsecure/auth) and checked like the payment page's (FLXB_WC_Iyzico::finish()).
 *  - Saved cards: "Save my card" (logged-in donors) and monthly donations ask iyzico to store the
 *    card; we keep only iyzico's cardUserKey, sealed, in user meta (_flxb_iyz_cuk).
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

class FLXB_WC_Iyzico_Card {

	/** 2.2.3: iyzico sandbox cards (docs.iyzico.com, Test cards); any other number fails there with [5177]. */
	const TEST_CARDS = array( '5890040000000016', '5526080000000006', '9792072000017956', '4766620000000001', '4603450000000000', '9792023757123604', '4987490000000002', '5311570000000005', '9792020000000001', '9792030000000000', '5170410000000004', '5400360000000003', '374427000000003', '4475050000000003', '5528790000000008', '4059030000000009', '5504720000000003', '5892830000000000', '4543590000000006', '4910050000000006', '4157920000000002', '6500528865390837', '6501700194147183', '5168880000000002', '5451030000000000', '5400010000000004', '4054180000000007', '5406670000000009', '4111111111111129', '4129111111111111', '4128111111111112', '4127111111111113', '4126111111111114', '4125111111111115', '4124111111111116', '4123111111111117', '4122111111111118', '4121111111111119', '4120111111111110', '4130111111111118', '4131111111111117', '4141111111111115', '4151111111111112' );

	private static function sandbox_on() {
		return (bool) FLXB_Iyzico_Api::credentials()['sandbox'];
	}

	const ROUTE_3DS  = '/wc/iyzico/3ds';
	const ROUTE_INST = '/wc/iyzico/installments';
	const USER_KEY   = '_flxb_iyz_cuk';
	const QUERY      = 'flxb_3ds';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'template_redirect', array( __CLASS__, 'bank_page' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
	}

	public static function on() {
		return 'fields' === FLXB_WC_Iyzico::ui();
	}

	/** Largest number of installments offered (setting card_installments, 1 = single payment only). */
	public static function max_installments() {
		return min( 12, max( 1, (int) FLXB_WC::setting( 'card_installments' ) ) );
	}

	public static function register_assets() {
		wp_register_style( 'flxb-card', FLXB_URL . 'assets/wc/card.css', array(), FLXB_VERSION );
		wp_register_script( 'flxb-card', FLXB_URL . 'assets/wc/card.js', array(), FLXB_VERSION, true );
		wp_localize_script( 'flxb-card', 'FLXBCard', self::js_data() );
	}

	/** What the card form needs (strings, endpoint, the donor's saved cards). */
	public static function js_data() {
		$monthly = function_exists( 'WC' ) && WC()->cart && FLXB_WC_Cart::cart_has_monthly();
		return array(
			'inst'      => rest_url( 'flxb/v1' . self::ROUTE_INST ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'maxInst'   => $monthly ? 1 : self::max_installments(),
			'monthly'   => $monthly,
			'canSave'   => is_user_logged_in(),
			'cards'     => self::saved_cards_public( get_current_user_id() ),
			'currency'  => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'TRY',
			'locale'    => str_replace( '_', '-', determine_locale() ),
			'sandbox'   => (bool) FLXB_Iyzico_Api::credentials()['sandbox'],
			'i18n'      => array(
				'number'     => __( 'Card number', 'flx-donate' ),
				'name'       => __( 'Name on the card', 'flx-donate' ),
				'exp'        => __( 'Expiry (MM/YY)', 'flx-donate' ),
				'expHolder'  => _x( 'MM/YY', 'context_def6e4575e04', 'flx-donate' ),
				'cvc'        => __( 'CVC', 'flx-donate' ),
				'save'       => __( 'Save my card for next time', 'flx-donate' ),
				'saveHelp'   => __( 'iyzico keeps the card; this site never stores card numbers.', 'flx-donate' ),
				'monthly'    => __( 'For the monthly donation iyzico keeps your card and charges it once a month. You can cancel at any time.', 'flx-donate' ),
				'newCard'    => __( 'Use another card', 'flx-donate' ),
				'savedCards' => __( 'Your saved cards', 'flx-donate' ),
				'inst'       => __( 'Installments', 'flx-donate' ),
				'single'     => __( 'Single payment', 'flx-donate' ),
				/* translators: 1: number of installments, 2: amount of one installment, 3: total */
				'instRow'    => __( '%1$sinstallments ×%2$s(total%3$s)', 'flx-donate' ),
				'instHint'   => __( 'Installment options appear after the card number.', 'flx-donate' ),
				'badNumber'  => __( 'Please check the card number.', 'flx-donate' ),
				'badName'    => __( 'Please write the name on the card.', 'flx-donate' ),
				'badExp'     => __( 'Please check the expiry date.', 'flx-donate' ),
				'badCvc'     => __( 'Please check the security code (CVC).', 'flx-donate' ),
				'secure'     => __( 'Paid with 3D Secure through iyzico. Your bank may send you a code.', 'flx-donate' ),
				'test'       => __( 'Test mode: use an iyzico test card, e.g. 5528 7900 0000 0008, 12/30, 123.', 'flx-donate' ),
				'testFill'   => __( 'Fill in the test card', 'flx-donate' ),
				'notTest'    => __( 'Test mode accepts only iyzico test cards. Use "Fill in the test card".', 'flx-donate' ),
			),
			'testCards' => self::sandbox_on() ? self::TEST_CARDS : array(),
		);
	}

	public static function routes() {
		register_rest_route(
			'flxb/v1',
			self::ROUTE_3DS,
			array(
				'methods'             => array( 'POST', 'GET' ),
				'permission_callback' => '__return_true', // the bank posts here; the payment is completed and checked with iyzico
				'callback'            => array( __CLASS__, 'callback' ),
			)
		);
		register_rest_route(
			'flxb/v1',
			self::ROUTE_INST,
			array(
				'methods'             => 'POST',
				'permission_callback' => static function ( WP_REST_Request $r ) {
					return (bool) wp_verify_nonce( (string) $r->get_header( 'x_wp_nonce' ), 'wp_rest' );
				},
				'callback'            => array( __CLASS__, 'rest_installments' ),
			)
		);
	}

	/* ---------- Installments ---------- */

	/**
	 * Installment options for a BIN and an amount: [ [ n, each, total ], ... ] plus the card's
	 * bank/family for the label. Cached briefly per BIN and amount.
	 */
	public static function installments( $bin, $amount ) {
		$bin    = substr( preg_replace( '/\D/', '', (string) $bin ), 0, 8 );
		$amount = round( (float) $amount, 2 );
		if ( strlen( $bin ) < 6 || $amount <= 0 ) {
			return new WP_Error( 'flxb_inst', 'bad request' );
		}
		$key = 'flxb_inst_' . md5( $bin . '|' . $amount . '|' . self::max_installments() );
		$hit = get_transient( $key );
		if ( is_array( $hit ) ) {
			return $hit;
		}
		$api = FLXB_Iyzico_Api::make();
		if ( ! $api ) {
			return new WP_Error( 'flxb_inst', 'not configured' );
		}
		try {
			$r = $api->installments( $bin, $amount );
		} catch ( FLXB_Iyzico_Error $e ) {
			return new WP_Error( 'flxb_inst', $e->getMessage() );
		}
		if ( ! FLXB_Iyzico_Api::ok( $r ) ) {
			return new WP_Error( 'flxb_inst', FLXB_Iyzico_Api::error_text( $r ) );
		}
		$d    = (array) ( $r['installmentDetails'][0] ?? array() );
		$rows = array();
		foreach ( (array) ( $d['installmentPrices'] ?? array() ) as $p ) {
			$n = (int) ( $p['installmentNumber'] ?? 0 );
			if ( $n >= 1 && $n <= self::max_installments() ) {
				$rows[] = array( $n, round( (float) ( $p['installmentPrice'] ?? 0 ), 2 ), round( (float) ( $p['totalPrice'] ?? 0 ), 2 ) );
			}
		}
		if ( ! $rows ) {
			$rows[] = array( 1, $amount, $amount );
		}
		$out = array(
			'rows'   => $rows,
			'bank'   => sanitize_text_field( (string) ( $d['bankName'] ?? '' ) ),
			'family' => sanitize_text_field( (string) ( $d['cardFamilyName'] ?? '' ) ),
			'assoc'  => sanitize_text_field( (string) ( $d['cardAssociation'] ?? '' ) ),
			'type'   => sanitize_text_field( (string) ( $d['cardType'] ?? '' ) ),
		);
		set_transient( $key, $out, 10 * MINUTE_IN_SECONDS );
		return $out;
	}

	public static function rest_installments( WP_REST_Request $r ) {
		$amount = (float) $r->get_param( 'amount' );
		if ( function_exists( 'WC' ) && ! WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		if ( $r->get_param( 'order' ) ) {
			$o = wc_get_order( absint( $r->get_param( 'order' ) ) );
			if ( $o && hash_equals( $o->get_order_key(), (string) $r->get_param( 'key' ) ) ) {
				$amount = (float) $o->get_total();
			}
		} elseif ( function_exists( 'WC' ) && WC()->cart ) {
			if ( ! (float) WC()->cart->get_total( 'edit' ) ) {
				WC()->cart->calculate_totals();
			}
			$amount = (float) WC()->cart->get_total( 'edit' );
		}
		$card = (string) $r->get_param( 'card' );
		$bin  = (string) $r->get_param( 'bin' );
		if ( '' !== $card ) {
			$c   = self::find_card( get_current_user_id(), $card );
			$bin = $c ? $c['bin'] : '';
		}
		$res = self::installments( $bin, $amount );
		if ( is_wp_error( $res ) ) {
			return new WP_REST_Response( array( 'rows' => array( array( 1, $amount, $amount ) ) ), 200 );
		}
		return new WP_REST_Response( $res, 200 );
	}

	/* ---------- Saved cards ---------- */

	public static function remember_user_key( $user_id, $card_user_key ) {
		if ( $user_id && '' !== (string) $card_user_key && self::user_key( $user_id ) !== (string) $card_user_key ) {
			update_user_meta( $user_id, self::USER_KEY, FLXB_Iyzico_Api::seal( (string) $card_user_key ) );
		}
		delete_transient( 'flxb_cards_' . (int) $user_id );
	}

	public static function user_key( $user_id ) {
		$s = $user_id ? (string) get_user_meta( $user_id, self::USER_KEY, true ) : '';
		return '' !== $s ? FLXB_Iyzico_Api::open( $s ) : '';
	}

	/** Cards iyzico stores for a user: id (a hash, never the token), label, bin; token kept server side. */
	public static function saved_cards( $user_id ) {
		$cuk = self::user_key( $user_id );
		if ( '' === $cuk ) {
			return array();
		}
		$hit = get_transient( 'flxb_cards_' . (int) $user_id );
		if ( is_array( $hit ) ) {
			return $hit;
		}
		$api = FLXB_Iyzico_Api::make();
		if ( ! $api ) {
			return array();
		}
		try {
			$r = $api->cards( $cuk );
		} catch ( FLXB_Iyzico_Error $e ) {
			return array();
		}
		$out = array();
		foreach ( (array) ( FLXB_Iyzico_Api::ok( $r ) ? ( $r['cardDetails'] ?? array() ) : array() ) as $c ) {
			if ( empty( $c['cardToken'] ) ) {
				continue;
			}
			$last4 = preg_match( '/^\d{4}$/', (string) ( $c['lastFourDigits'] ?? '' ) ) ? (string) $c['lastFourDigits'] : '';
			$name  = trim( implode( ' ', array_filter( array( (string) ( $c['cardFamily'] ?? '' ), (string) ( $c['cardBankName'] ?? '' ) ) ) ) );
			$out[] = array(
				'id'    => substr( hash_hmac( 'sha256', (string) $c['cardToken'], wp_salt( 'nonce' ) ), 0, 16 ),
				'label' => sanitize_text_field( ( '' !== $name ? $name : (string) ( $c['cardAssociation'] ?? '' ) ) . ( '' !== $last4 ? ' ···· ' . $last4 : '' ) ),
				'alias' => sanitize_text_field( (string) ( $c['cardAlias'] ?? '' ) ),
				'bin'   => preg_replace( '/\D/', '', (string) ( $c['binNumber'] ?? '' ) ),
				'token' => (string) $c['cardToken'],
			);
		}
		set_transient( 'flxb_cards_' . (int) $user_id, $out, 10 * MINUTE_IN_SECONDS );
		return $out;
	}

	public static function saved_cards_public( $user_id ) {
		return array_map(
			static function ( $c ) {
				return array( 'id' => $c['id'], 'label' => $c['label'] );
			},
			$user_id ? self::saved_cards( $user_id ) : array()
		);
	}

	public static function find_card( $user_id, $id ) {
		foreach ( $user_id ? self::saved_cards( $user_id ) : array() as $c ) {
			if ( hash_equals( $c['id'], (string) $id ) ) {
				return $c;
			}
		}
		return null;
	}

	/* ---------- Classic checkout / order-pay page ---------- */

	public static function fields_html() {
		wp_enqueue_style( 'flxb-card' );
		wp_enqueue_script( 'flxb-card' );
		global $wp;
		$order = isset( $wp->query_vars['order-pay'] ) ? absint( $wp->query_vars['order-pay'] ) : 0;
		$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="flxb-card" data-flxb-card data-order="' . esc_attr( $order ) . '" data-key="' . esc_attr( $key ) . '"></div>';
	}

	/* ---------- Payment ---------- */

	/** The card fields of the checkout request ($_POST, also filled by the block checkout). */
	private static function posted() {
		$g = static function ( $k ) {
			return isset( $_POST[ $k ] ) && is_scalar( $_POST[ $k ] ) ? trim( (string) wp_unslash( $_POST[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- WooCommerce checked the checkout nonce; card data is not sanitised as text
		};
		$exp = preg_replace( '/\D/', '', $g( 'flxb_card_exp' ) );
		return array(
			'number' => preg_replace( '/\D/', '', $g( 'flxb_card_number' ) ),
			'name'   => sanitize_text_field( $g( 'flxb_card_name' ) ),
			'month'  => substr( $exp, 0, 2 ),
			'year'   => strlen( $exp ) >= 6 ? substr( $exp, 2, 4 ) : '20' . substr( $exp, 2, 2 ),
			'cvc'    => preg_replace( '/\D/', '', $g( 'flxb_card_cvc' ) ),
			'inst'   => max( 1, (int) $g( 'flxb_card_inst' ) ),
			'save'   => '1' === $g( 'flxb_card_save' ),
			'saved'  => sanitize_key( $g( 'flxb_card_saved' ) ),
		);
	}

	private static function luhn( $n ) {
		$sum = 0;
		$alt = false;
		for ( $i = strlen( $n ) - 1; $i >= 0; $i-- ) {
			$d = (int) $n[ $i ];
			if ( $alt ) {
				$d *= 2;
				$d  = $d > 9 ? $d - 9 : $d;
			}
			$sum += $d;
			$alt  = ! $alt;
		}
		return 0 === $sum % 10;
	}

	/** @return true|WP_Error */
	public static function validate( array $c ) {
		if ( '' !== $c['saved'] ) {
			return true;
		}
		if ( strlen( $c['number'] ) < 12 || strlen( $c['number'] ) > 19 || ! self::luhn( $c['number'] ) ) {
			return new WP_Error( 'flxb_card', __( 'Please check the card number.', 'flx-donate' ) );
		}
		if ( mb_strlen( $c['name'] ) < 3 ) {
			return new WP_Error( 'flxb_card', __( 'Please write the name on the card.', 'flx-donate' ) );
		}
		$m = (int) $c['month'];
		$y = (int) $c['year'];
		if ( $m < 1 || $m > 12 || $y < (int) gmdate( 'Y' ) || ( $y === (int) gmdate( 'Y' ) && $m < (int) gmdate( 'n' ) ) || $y > (int) gmdate( 'Y' ) + 25 ) {
			return new WP_Error( 'flxb_card', __( 'Please check the expiry date.', 'flx-donate' ) );
		}
		if ( ! preg_match( '/^\d{3,4}$/', $c['cvc'] ) ) {
			return new WP_Error( 'flxb_card', __( 'Please check the security code (CVC).', 'flx-donate' ) );
		}
		if ( self::sandbox_on() && ! in_array( $c['number'], self::TEST_CARDS, true ) ) {
			return new WP_Error( 'flxb_card', __( 'Test mode accepts only iyzico test cards. Use "Fill in the test card".', 'flx-donate' ) );
		}
		return true;
	}

	/**
	 * Start 3D Secure for an order with the posted card.
	 *
	 * @return string|WP_Error URL of the bank page.
	 */
	public static function start( WC_Order $order ) {
		$c  = self::posted();
		$ok = self::validate( $c );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$api = FLXB_Iyzico_Api::make();
		if ( ! $api ) {
			return new WP_Error( 'flxb_iyz', __( 'Card payments are not configured on this site. Please choose another payment method.', 'flx-donate' ) );
		}
		$amount = (float) $order->get_total();
		if ( $amount <= 0 ) {
			return new WP_Error( 'flxb_iyz', __( 'The amount must be greater than zero.', 'flx-donate' ) );
		}
		$monthly = FLXB_WC_Cart::order_has_monthly( $order );
		$user    = (int) $order->get_customer_id();
		$card    = null;
		$saved   = null;
		if ( '' !== $c['saved'] ) {
			$saved = self::find_card( $user, $c['saved'] );
			if ( ! $saved ) {
				return new WP_Error( 'flxb_card', __( 'This saved card cannot be used any more. Please enter the card.', 'flx-donate' ) );
			}
			$card = array( 'cardUserKey' => self::user_key( $user ), 'cardToken' => $saved['token'] );
			$bin  = $saved['bin'];
		} else {
			$card = array(
				'cardHolderName' => $c['name'],
				'cardNumber'     => $c['number'],
				'expireMonth'    => $c['month'],
				'expireYear'     => $c['year'],
				'cvc'            => $c['cvc'],
			);
			if ( $monthly || ( $c['save'] && $user ) ) {
				$card['registerCard'] = 1;
				$cuk                  = self::user_key( $user );
				if ( '' !== $cuk ) {
					$card['cardUserKey'] = $cuk;
				}
			}
			$bin = substr( $c['number'], 0, 8 );
		}
		/* Installment price from iyzico, never from the browser. */
		$inst = $monthly ? 1 : min( $c['inst'], self::max_installments() );
		$paid = $amount;
		if ( $inst > 1 ) {
			$opts = self::installments( $bin, $amount );
			$row  = null;
			foreach ( is_wp_error( $opts ) ? array() : $opts['rows'] as $o ) {
				if ( (int) $o[0] === $inst ) {
					$row = $o;
				}
			}
			if ( ! $row ) {
				return new WP_Error( 'flxb_card', __( 'This installment option is not available for the card. Please choose another.', 'flx-donate' ) );
			}
			$paid = max( $amount, (float) $row[2] );
		}
		$conv    = 'flxbwc-' . $order->get_id();
		$contact = array(
			'name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'email' => $order->get_billing_email(),
			'phone' => $order->get_billing_phone(),
		);
		list( $buyer, $address ) = FLXB_Iyzico_Api::buyer( $contact, $order->get_billing_email(), (string) $order->get_customer_ip_address() );
		$body = array(
			'locale'          => FLXB_Iyzico_Api::locale(),
			'conversationId'  => $conv,
			'price'           => FLXB_Iyzico_Api::price( $amount ),
			'paidPrice'       => FLXB_Iyzico_Api::price( $paid ),
			'installment'     => $inst,
			'basketId'        => $conv,
			'paymentChannel'  => 'WEB',
			'paymentGroup'    => $monthly ? 'SUBSCRIPTION' : 'PRODUCT',
			'paymentCard'     => $card,
			'buyer'           => $buyer,
			'billingAddress'  => $address,
			'basketItems'     => FLXB_Iyzico_Api::basket( $amount, FLXB_WC_Iyzico::basket_lines( $order ), __( 'Donation', 'flx-donate' ), 'flxbwc' ),
			'callbackUrl'     => rest_url( 'flxb/v1' . self::ROUTE_3DS ),
			'currency'        => strtoupper( $order->get_currency() ),
		);
		$body = (array) apply_filters( 'flxb_wc_iyzico_3ds_request', $body, $order );
		try {
			$r = $api->threeds_initialize( $body );
		} catch ( FLXB_Iyzico_Error $e ) {
			return new WP_Error( 'flxb_iyz', $e->getMessage() );
		}
		$masked = '' !== $c['saved'] ? (string) $saved['label'] : substr( $c['number'], 0, 6 ) . '…' . substr( $c['number'], -4 ); // first 6 + last 4 only
		unset( $body, $card, $c ); // the card is not kept anywhere
		if ( ! FLXB_Iyzico_Api::ok( $r ) || empty( $r['threeDSHtmlContent'] ) ) {
			$err = FLXB_Iyzico_Api::error_text( $r );
			/* translators: 1: iyzico error, 2: card (first 6 and last 4 digits), 3: test or live */
			$order->add_order_note( sprintf( __( 'Card payment could not start:%1$s(card%2$s,%3$s).', 'flx-donate' ), '' !== $err ? $err : '-', $masked, FLXB_Iyzico_Api::credentials()['sandbox'] ? __( 'test mode', 'flx-donate' ) : __( 'live', 'flx-donate' ) ) );
			return new WP_Error( 'flxb_iyz', '' !== $err ? 'iyzico: ' . $err : __( 'The payment could not be started. Please try again.', 'flx-donate' ) );
		}
		$html = base64_decode( (string) $r['threeDSHtmlContent'], true );
		if ( false === $html || '' === trim( $html ) ) {
			return new WP_Error( 'flxb_iyz', __( 'The payment could not be started. Please try again.', 'flx-donate' ) );
		}
		$page = wp_generate_password( 32, false );
		set_transient( 'flxb_3ds_' . $page, array( 'o' => $order->get_id(), 'h' => $html ), 10 * MINUTE_IN_SECONDS );
		$order->update_meta_data(
			'_flxb_iyz_3ds',
			array(
				'at'    => time(),
				'pid'   => sanitize_text_field( (string) ( $r['paymentId'] ?? '' ) ),
				'inst'  => $inst,
				'saved' => $saved && $monthly ? array( 'k' => FLXB_Iyzico_Api::seal( self::user_key( $user ) ), 't' => FLXB_Iyzico_Api::seal( $saved['token'] ), 'label' => $saved['label'] ) : null,
			)
		);
		$order->update_meta_data( '_flxb_iyz_sandbox', FLXB_Iyzico_Api::credentials()['sandbox'] ? 1 : 0 );
		if ( $inst > 1 ) {
			/* translators: 1: installments, 2: amount charged */
			$order->add_order_note( sprintf( __( 'Card payment started:%1$sinstallments,%2$scharged by the bank.', 'flx-donate' ), $inst, FLXB_WC::money_text( $paid, $order->get_currency() ) ) );
		}
		$order->save();
		return add_query_arg( self::QUERY, $page, home_url( '/' ) );
	}

	/** The bank's 3D Secure page (one use). */
	public static function bank_page() {
		if ( empty( $_GET[ self::QUERY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$k = sanitize_key( wp_unslash( $_GET[ self::QUERY ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$t = get_transient( 'flxb_3ds_' . $k );
		delete_transient( 'flxb_3ds_' . $k );
		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, private' );
		header( 'X-Robots-Tag: noindex' );
		if ( ! is_array( $t ) || empty( $t['h'] ) ) {
			wp_safe_redirect( function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/' ) );
			exit;
		}
		echo $t['h']; // phpcs:ignore WordPress.Security.EscapeOutput -- iyzico's 3D Secure form, posts the donor to the bank
		exit;
	}

	/** The bank posts the 3D Secure result here. */
	public static function callback( WP_REST_Request $r ) {
		$conv  = sanitize_text_field( (string) $r->get_param( 'conversationId' ) );
		$order = preg_match( '/^flxbwc-(\d+)$/', $conv, $m ) ? wc_get_order( (int) $m[1] ) : null;
		$paid  = false;
		$err   = '';
		if ( $order && FLXB_WC_Iyzico::ID === $order->get_payment_method() && is_array( $order->get_meta( '_flxb_iyz_3ds' ) ) ) {
			if ( $order->is_paid() ) {
				$paid = true;
			} elseif ( 'success' === (string) $r->get_param( 'status' ) && '1' === (string) $r->get_param( 'mdStatus' ) ) {
				$paid = self::complete( $order, sanitize_text_field( (string) $r->get_param( 'paymentId' ) ), (string) $r->get_param( 'conversationData' ) );
				$err  = $paid ? '' : (string) wc_get_order( $order->get_id() )->get_meta( '_flxb_iyz_error' );
			} else {
				$err = self::md_text( (string) $r->get_param( 'mdStatus' ) );
				/* translators: %s: reason */
				$order->update_status( 'failed', sprintf( __( 'iyzico 3D Secure:%s', 'flx-donate' ), $err ) );
			}
		}
		$order = $order ? wc_get_order( $order->get_id() ) : null;
		if ( $paid && $order ) {
			$to = $order->get_checkout_order_received_url();
		} elseif ( $order ) {
			$to = add_query_arg( 'flxb_iyz_error', rawurlencode( '' !== $err ? $err : __( 'The payment could not be completed. Please try again or use another card.', 'flx-donate' ) ), $order->get_checkout_payment_url() );
		} else {
			$to = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/' );
		}
		$res = new WP_REST_Response( null, 302 );
		$res->header( 'Location', esc_url_raw( $to ) );
		return $res;
	}

	private static function md_text( $md ) {
		switch ( $md ) {
			case '0':
			case '-1':
				return __( 'The bank did not confirm the 3D Secure code. Please try again.', 'flx-donate' );
			case '2':
			case '3':
			case '4':
				return __( 'This card is not enrolled for 3D Secure. Please call your bank or use another card.', 'flx-donate' );
			default:
				return __( 'The bank could not check the card. Please try again or use another card.', 'flx-donate' );
		}
	}

	/** Complete the payment with iyzico after 3D Secure (once; repeated calls see the paid order). */
	private static function complete( WC_Order $order, $payment_id, $conversation_data ) {
		$lock = 'flxb_wc_iyz_lock_' . $order->get_id();
		if ( ! add_option( $lock, time(), '', false ) && time() - (int) get_option( $lock ) < 60 ) {
			return false;
		}
		update_option( $lock, time(), false );
		try {
			$order = wc_get_order( $order->get_id() );
			if ( $order->is_paid() ) {
				return true;
			}
			$api = FLXB_Iyzico_Api::make();
			if ( ! $api ) {
				return false;
			}
			try {
				$r = $api->threeds_auth( $payment_id, $conversation_data, 'flxbwc-' . $order->get_id() );
			} catch ( FLXB_Iyzico_Error $e ) {
				$order->update_meta_data( '_flxb_iyz_error', $e->getMessage() );
				$order->save_meta_data();
				return false;
			}
			$status = (string) ( $r['paymentStatus'] ?? 'SUCCESS' );
			if ( ! FLXB_Iyzico_Api::ok( $r ) || 'SUCCESS' !== $status ) {
				$e = FLXB_Iyzico_Api::error_text( $r );
				/* translators: %s: iyzico error */
				$order->update_status( 'failed', sprintf( __( 'iyzico: the payment was not completed (%s).', 'flx-donate' ), '' !== $e ? $e : $status ) );
				$order->update_meta_data( '_flxb_iyz_error', '' !== $e ? $e : __( 'The payment could not be completed. Please try again or use another card.', 'flx-donate' ) );
				$order->save_meta_data();
				return false;
			}
			$m = $order->get_meta( '_flxb_iyz_3ds' );
			if ( empty( $r['cardToken'] ) && is_array( $m ) && ! empty( $m['saved'] ) ) {
				/* Paid with a saved card for a monthly donation: keep that card for the next months. */
				$order->update_meta_data( '_flxb_iyz_card', $m['saved'] );
			}
			return FLXB_WC_Iyzico::finish( $order, $r, '3ds' );
		} finally {
			delete_option( $lock );
		}
	}
}
