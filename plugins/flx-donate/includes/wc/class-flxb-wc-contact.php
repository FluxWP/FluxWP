<?php
/**
 * One contact section on a donation-only checkout (2.0.4): e-mail OR phone, at least one, both allowed.
 * The "Billing address" heading, country and the separate phone field are hidden; first and last name stay.
 *
 * WooCommerce and iyzico need an e-mail on the order. When the donor gives only a phone, the order gets a
 * placeholder address on the site's own domain (2.0.6; WordPress never mails it); nothing is mailed to it and
 * Flux Donate records the donor by phone.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

class FLXB_WC_Contact {

	/** 2.0.4 placeholders used the reserved ".invalid" domain; iyzico rejects it as a bad e-mail format. */
	const OLD_DOMAIN = 'telefon.invalid';

	/** 2.0.6: placeholders live on the site's own domain (valid format for iyzico; WordPress never mails them). */
	public static function domain() {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$host = preg_replace( '/^www\./i', '', $host );
		return (string) apply_filters( 'flxb_wc_contact_domain', false !== strpos( $host, '.' ) ? $host : 'example.org' );
	}

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_filter( 'woocommerce_billing_fields', array( __CLASS__, 'classic_fields' ), 60 );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'classic_validate' ), 10, 2 );
		add_filter( 'woocommerce_checkout_posted_data', array( __CLASS__, 'classic_posted' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'store_api_check' ), 5, 2 );
		add_filter( 'pre_wp_mail', array( __CLASS__, 'skip_placeholder_mail' ), 5, 2 );
	}

	/** On by default; the "Do not ask for an address" setting and a donation-only cart switch it on. */
	public static function active() {
		return (bool) apply_filters( 'flxb_wc_contact_either', FLXB_WC_Cart::trim_fields() );
	}

	public static function is_placeholder( $email ) {
		$e = (string) $email;
		return (bool) preg_match( '/@' . preg_quote( self::OLD_DOMAIN, '/' ) . '$/i', $e )
			|| (bool) preg_match( '/^bagisci-[0-9a-z]+@' . preg_quote( self::domain(), '/' ) . '$/i', $e );
	}

	public static function placeholder( $phone ) {
		$digits = preg_replace( '/\D+/', '', (string) $phone );
		return 'bagisci-' . ( '' !== $digits ? $digits : wp_generate_password( 8, false, false ) ) . '@' . self::domain();
	}

	/** The donor's real e-mail on an order ('' when the donor gave only a phone). */
	public static function email( $order ) {
		$e = $order instanceof WC_Order ? (string) $order->get_billing_email() : (string) $order;
		return self::is_placeholder( $e ) ? '' : $e;
	}

	private static function phone_ok( $phone ) {
		return strlen( preg_replace( '/\D+/', '', (string) $phone ) ) >= 10;
	}

	public static function message() {
		return __( 'Please write your e-mail or phone number (at least one).', 'flx-donate' );
	}

	/* ---------- Block checkout ---------- */

	public static function enqueue() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url() || ! self::active() ) {
			return;
		}
		wp_enqueue_style( 'flxb-contact', FLXB_URL . 'assets/wc/contact.css', array(), FLXB_VERSION );
		$deps = array();
		if ( function_exists( 'flx_field_enqueue' ) ) {
			flx_field_enqueue(); // 2.22.0: shared FluxWP fields
			$deps[] = 'flx-field';
		}
		wp_enqueue_script( 'flxb-contact', FLXB_URL . 'assets/wc/contact.js', $deps, FLXB_VERSION, true );
		/* "Order summary" → "Donation summary": WooCommerce's block checkout translates in JS, so filter its gettext there, before it renders. */
		wp_add_inline_script( 'wp-hooks', 'window.wp&&wp.hooks&&wp.hooks.addFilter("i18n.gettext_woocommerce","flx-donate/summary",function(t,s){return "Order summary"===s?' . wp_json_encode( __( 'Donation summary', 'flx-donate' ) ) . ':t;});' );
		wp_localize_script(
			'flxb-contact',
			'FLXBContact',
			array(
				'domain'     => self::domain(),
				'email'      => __( 'E-mail', 'flx-donate' ),
				'phone'      => __( 'Phone', 'flx-donate' ),
				'help'       => __( 'Write at least one. We send your receipt and certificate there.', 'flx-donate' ),
				'need'       => self::message(),
				'badEmail'   => __( 'Please enter a valid e-mail address.', 'flx-donate' ),
				'badPhone'   => __( 'Please enter a valid phone number.', 'flx-donate' ),
				'phoneHolder' => '0532 123 45 67',
				'support'    => FLXB_WC::support_html(),
				'total'      => __( 'Total', 'flx-donate' ),
				'pay'        => __( 'Continue to payment', 'flx-donate' ),
				'user'       => self::signed_in(),
				'signupHint' => __( 'We e-mail you a link to set your password. Your donations and certificates are then under My account.', 'flx-donate' ),
			)
		);
	}

	/**
	 * 2.21.0: a signed-in donor's e-mail and phone are shown read-only (they are changed under My account).
	 * Empty ones stay editable.
	 */
	private static function signed_in() {
		if ( ! is_user_logged_in() ) {
			return null;
		}
		$u     = wp_get_current_user();
		$email = is_email( $u->user_email ) && ! self::is_placeholder( $u->user_email ) ? $u->user_email : '';
		$phone = (string) get_user_meta( $u->ID, 'billing_phone', true );
		$name  = trim( $u->first_name . ' ' . $u->last_name );
		$acct  = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'edit-account' ) : admin_url( 'profile.php' );
		return array(
			'email'  => $email,
			'phone'  => self::phone_ok( $phone ) ? $phone : '',
			'first'  => (string) $u->first_name,
			'last'   => (string) $u->last_name,
			/* translators: %s: the donor's name or e-mail */
			'who'    => sprintf( __( 'Signed in as %s.', 'flx-donate' ), '' !== $name ? $name : ( '' !== $email ? $email : $u->display_name ) ),
			'edit'   => __( 'Change in My account', 'flx-donate' ),
			'editUrl' => $acct,
			'out'    => __( 'Not you? Log out', 'flx-donate' ),
			'outUrl' => wp_logout_url( wc_get_checkout_url() ),
		);
	}

	/** Server side guard: a placeholder e-mail needs a phone. */
	public static function store_api_check( $order, $request ) {
		if ( ! $order instanceof WC_Order || ! self::is_placeholder( $order->get_billing_email() ) ) {
			return;
		}
		if ( ! self::phone_ok( $order->get_billing_phone() ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'flxb_contact_required', self::message(), 400 ); // phpcs:ignore
		}
		$order->update_meta_data( '_flxb_phone_only', 1 );
	}

	/* ---------- Classic checkout ---------- */

	public static function classic_fields( $fields ) {
		if ( ! self::active() ) {
			return $fields;
		}
		foreach ( array( 'billing_email', 'billing_phone' ) as $k ) {
			if ( isset( $fields[ $k ] ) ) {
				$fields[ $k ]['required'] = false;
			}
		}
		unset( $fields['billing_country'] );
		return $fields;
	}

	public static function classic_posted( $data ) {
		if ( self::active() && empty( $data['billing_email'] ) && self::phone_ok( $data['billing_phone'] ?? '' ) ) {
			$data['billing_email'] = self::placeholder( $data['billing_phone'] );
		}
		if ( self::active() && empty( $data['billing_country'] ) ) {
			$data['billing_country'] = WC()->countries ? WC()->countries->get_base_country() : 'TR';
		}
		return $data;
	}

	public static function classic_validate( $data, $errors ) {
		if ( ! self::active() ) {
			return;
		}
		$email = (string) ( $data['billing_email'] ?? '' );
		$phone = (string) ( $data['billing_phone'] ?? '' );
		if ( ( '' === $email || self::is_placeholder( $email ) ) && ! self::phone_ok( $phone ) ) {
			$errors->add( 'flxb_contact_required', self::message() );
		}
	}

	/* ---------- Mail ---------- */

	/** Never try to mail the placeholder address (WooCommerce, Flux Mail and others all go through wp_mail). */
	public static function skip_placeholder_mail( $return, $atts ) {
		if ( null !== $return ) {
			return $return;
		}
		$to = is_array( $atts['to'] ?? '' ) ? $atts['to'] : explode( ',', (string) ( $atts['to'] ?? '' ) );
		$to = array_filter( array_map( 'trim', $to ) );
		if ( $to && count( array_filter( $to, array( __CLASS__, 'is_placeholder' ) ) ) === count( $to ) ) {
			return true;
		}
		return $return;
	}
}
