<?php
/**
 * Flux Donate 2.0: donation lines in the WooCommerce cart and order.
 *
 * The choice lives in the cart item data (key `flxb`); the price is set on every calculation from
 * the donation type on the server (woocommerce_before_calculate_totals), never from the browser.
 * Order lines keep the choice in `_flxb` plus visible meta (flxb_fidan, flxb_units, flxb_cert,
 * flxb_cert_name, flxb_cert_address, flxb_cert_no, flxb_monthly, flxb_on_behalf, flxb_names, flxb_proxy).
 *
 * Line shape: type_id, option ('' = free amount), units, amount (free amount only), cert
 * (online|print|''), name (name on the certificate), address, for_other, ben_name, ben_phone,
 * ben_email, send_me, monthly, form {id, track}, beneficiaries (2.0.1: one name per share, proxy types),
 * proxy_consent { version, accepted_at, source }.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_WC_Cart {

	const KEY = 'flxb';

	public static function init() {
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'default_line' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'prices' ), 20 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'from_session' ), 10, 2 );
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'fees' ) );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_name', array( __CLASS__, 'cart_item_name' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_line' ), 10, 3 );
		add_filter( 'woocommerce_order_item_display_meta_key', array( __CLASS__, 'meta_key_label' ), 10, 2 );
		add_filter( 'woocommerce_order_item_display_meta_value', array( __CLASS__, 'meta_value_label' ), 10, 2 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( __CLASS__, 'hidden_meta' ) );
		add_filter( 'woocommerce_payment_complete_order_status', array( __CLASS__, 'complete_status' ), 10, 3 );
		add_filter( 'woocommerce_get_country_locale', array( __CLASS__, 'locale' ), 50 );
		add_filter( 'woocommerce_get_country_locale_default', array( __CLASS__, 'locale_default' ), 50 );
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'checkout_fields' ), 50 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'fill_address' ), 20 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'fill_address_store_api' ), 20 );
		add_filter( 'woocommerce_cart_item_quantity', array( __CLASS__, 'fixed_quantity' ), 10, 3 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'check_cart' ) );
	}

	/* ------------------------------------------------------------------
	 * Type helpers
	 * ------------------------------------------------------------------ */

	/** Is the type shown in the "Donation options" block by default (type meta in_form)? */
	public static function in_form( array $t ) {
		return ! empty( FLXB_Types::meta( $t, 'in_form', false ) );
	}

	/**
	 * Type for Flux Forms payment forms that are not linked to a type: filter
	 * `flxb_wc_form_fallback_type`, else the first open type shown on the donation page that takes a
	 * free amount, else the first open type that takes a free amount.
	 */
	public static function fallback_type() {
		$id = (int) apply_filters( 'flxb_wc_form_fallback_type', 0 );
		if ( $id && FLXB_Types::get( $id ) ) {
			return $id;
		}
		$any = 0;
		foreach ( FLXB_Types::all( 'active' ) as $t ) {
			if ( ! $t['allow_free'] || ! FLXB_Types::is_open( $t ) ) {
				continue;
			}
			if ( self::in_form( $t ) ) {
				return (int) $t['id'];
			}
			$any = $any ? $any : (int) $t['id'];
		}
		return $any;
	}

	/**
	 * 2.23.0: does the type take a hand-written amount? Its own "free amount" switch, or Appearance ›
	 * "Other amount" for the types shown on the form (not for proxy types: a share has a fixed price).
	 */
	public static function takes_free( array $t ) {
		if ( ! empty( $t['allow_free'] ) ) {
			return true;
		}
		$on = class_exists( 'FLXB_WC_Look' ) && FLXB_WC_Look::get( 'other_amount' ) && self::in_form( $t ) && ! FLXB_Types::requires_proxy( $t );
		return (bool) apply_filters( 'flxb_takes_free', $on, $t );
	}

	/** 2.23.0: lowest hand-written amount: the type's own, else the one of Settings › Appearance. */
	public static function min_free( array $t, $currency = '' ) {
		$cur = '' !== (string) $currency ? strtoupper( (string) $currency ) : self::currency();
		$min = FLXB_Types::min_amount_in( $t, $cur );
		if ( $min <= 0 && class_exists( 'FLXB_WC_Look' ) ) {
			$base = class_exists( 'FLXB_WC_Currency' ) ? FLXB_WC_Currency::base() : get_woocommerce_currency();
			$map  = (array) FLXB_WC_Look::get( 'min_amounts' );
			$min  = $cur === $base ? (float) FLXB_WC_Look::get( 'min_amount' ) : (float) ( $map[ $cur ] ?? 0 );
		}
		return (float) apply_filters( 'flxb_min_free', $min, $t, $cur );
	}

	/** 2.23.0: the first option with a count that is on sale in the currency (amount buttons, units of a free amount). */
	public static function unit_option( array $t, $currency = '' ) {
		$cur = '' !== (string) $currency ? strtoupper( (string) $currency ) : self::currency();
		foreach ( (array) $t['options'] as $o ) {
			if ( self::option_takes_units( $t, (string) $o['key'] ) && FLXB_Types::option_on_sale_in( $t, $o, $cur ) ) {
				return $o;
			}
		}
		return null;
	}

	/** The option that takes a free count ("different quantity"), or '' when quantity is per option. */
	public static function custom_option( array $t ) {
		return (string) FLXB_Types::meta( $t, 'custom_option', '' );
	}

	/** Does this option of the type take a count? */
	public static function option_takes_units( array $t, $key ) {
		$custom = self::custom_option( $t );
		if ( '' !== $custom ) {
			return $key === $custom;
		}
		return '' !== $key && ! empty( $t['allow_quantity'] );
	}

	public static function max_units( array $t ) {
		return (int) $t['max_quantity'] > 0 ? (int) $t['max_quantity'] : 1000;
	}

	public static function type_has_certificate( array $t ) {
		return ! empty( FLXB_Types::meta( $t, 'certificate', false ) );
	}

	/** Can a monthly gift be taken now (Flux Donate iyzico gateway ready)? */
	public static function monthly_available() {
		return class_exists( 'FLXB_WC_Iyzico' ) && FLXB_WC_Iyzico::usable();
	}

	/* ------------------------------------------------------------------
	 * Building a line
	 * ------------------------------------------------------------------ */

	/**
	 * Check a choice and turn it into cart item data.
	 *
	 * @param array $in flxb_type|type_id, option, units|qty, amount, cert, cert_name, address,
	 *                  for_other, ben_name, ben_phone, ben_email, send_me, monthly, form_id, track.
	 * @return array|WP_Error
	 */
	public static function build_line( array $in ) {
		$tid = absint( $in['flxb_type'] ?? ( $in['type_id'] ?? 0 ) );
		$t = $tid ? FLXB_Types::get( $tid ) : null;
		if ( ! $t ) {
			return new WP_Error( 'flxb_type', __( 'Please choose what you would like to donate for.', 'flx-donate' ) );
		}
		if ( ! FLXB_Types::is_open( $t ) ) {
			$msg = FLXB_Types::closed_message( $t );
			return new WP_Error( 'flxb_closed', '' !== $msg ? $msg : __( 'Donations for this cause are closed at the moment.', 'flx-donate' ) );
		}
		$cur  = self::currency();
		$line = array( 'type_id' => (int) $t['id'], 'option' => '', 'units' => 1, 'currency' => $cur );

		$key = sanitize_key( (string) ( $in['option'] ?? '' ) );
		$free = '' === $key;
		if ( '' !== $key ) {
			$opt = FLXB_Types::option( $t, $key );
			if ( $opt && FLXB_Types::option_on_sale( $opt ) && ! FLXB_Types::option_on_sale_in( $t, $opt, $cur ) ) {
				/* translators: %s: currency code */
				return new WP_Error( 'flxb_currency', sprintf( __( 'This option cannot be donated in %s. Please choose another currency.', 'flx-donate' ), $cur ) );
			}
			if ( ! $opt || ! FLXB_Types::option_on_sale_in( $t, $opt, $cur ) ) {
				return new WP_Error( 'flxb_option', __( 'This option cannot be donated online at the moment.', 'flx-donate' ) );
			}
			$line['option'] = $key;
			if ( self::option_takes_units( $t, $key ) ) {
				$n   = absint( $in['units'] ?? ( $in['qty'] ?? 1 ) );
				$max = self::max_units( $t );
				if ( $n < 1 || $n > $max ) {
					/* translators: %s: highest number */
					return new WP_Error( 'flxb_units', sprintf( __( 'The number must be between 1 and %s.', 'flx-donate' ), number_format_i18n( $max ) ) );
				}
				$line['units'] = $n;
			}
		} elseif ( $free && self::takes_free( $t ) ) {
			$amount = FLXB_WC::parse_amount( $in['amount'] ?? '' );
			$min    = self::min_free( $t, $cur );
			if ( $amount <= 0 || $amount < $min ) {
				/* translators: %s: lowest amount */
				return new WP_Error( 'flxb_amount', $min > 0 ? sprintf( __( 'The amount must be at least %s.', 'flx-donate' ), FLXB_WC::money_text( $min ) ) : __( 'Please enter an amount.', 'flx-donate' ) );
			}
			if ( $amount > 1000000 ) {
				return new WP_Error( 'flxb_amount', __( 'For this amount please get in touch with us.', 'flx-donate' ) );
			}
			$line['amount'] = round( $amount, 2 );
			/* 2.23.0: a free amount on a type sold by the piece counts as whole pieces (450 TL = 3 saplings at 150 TL), at least one. */
			$uo = self::unit_option( $t, $cur );
			$up = $uo ? (float) FLXB_Types::option_price( $t, $uo, $cur ) : 0;
			if ( $uo && $up > 0 && '' !== (string) $uo['unit'] ) {
				$line['free_units'] = max( 1, (int) floor( ( $amount + 0.001 ) / $up ) ) * max( 1, (int) $uo['units_per'] );
				$line['free_unit']  = (string) $uo['unit'];
			}
		} else {
			return new WP_Error( 'flxb_option', __( 'Please choose an option.', 'flx-donate' ) );
		}

		if ( self::type_has_certificate( $t ) ) {
			if ( 'print' === ( $in['cert'] ?? '' ) && ! self::print_here() ) {
				return new WP_Error( 'flxb_print', __( 'Printed certificates are not available yet. Please choose the online certificate.', 'flx-donate' ) );
			}
			$line['cert'] = 'print' === ( $in['cert'] ?? '' ) ? 'print' : 'online';
			if ( 'print' === $line['cert'] ) {
				$addr = trim( sanitize_textarea_field( (string) ( $in['address'] ?? '' ) ) );
				if ( strlen( $addr ) < 10 ) {
					return new WP_Error( 'flxb_address', __( 'Please write the postal address for the printed certificate.', 'flx-donate' ) );
				}
				$line['address'] = mb_substr( $addr, 0, 400 );
			}
		}

		/* On behalf of someone else (name on the certificate, where it is sent). */
		$line['for_other'] = ! empty( $in['for_other'] );
		$name              = trim( sanitize_text_field( (string) ( $in['ben_name'] ?? ( $in['cert_name'] ?? '' ) ) ) );
		$line['name']      = mb_substr( $name, 0, 80 );
		if ( $line['for_other'] && ! FLXB_Types::requires_proxy( $t ) ) {
			if ( '' === $line['name'] ) {
				return new WP_Error( 'flxb_ben', __( 'Please write the name of the person you are donating for.', 'flx-donate' ) );
			}
			$line['ben_name'] = $line['name'];
		}
		/* 2.0.1: proxy (vekâlet) types: one name per share and the consent to the current proxy text. */
		if ( FLXB_Types::requires_proxy( $t ) ) {
			$text = flxb_proxy_text();
			if ( ! $text ) {
				return new WP_Error( 'flxb_proxy', __( 'This donation needs a proxy (vekâlet) consent, but it is not set up yet. Please contact the site administrator.', 'flx-donate' ) );
			}
			$given = $in['proxy_consent'] ?? '';
			if ( empty( $given ) ) {
				return new WP_Error( 'flxb_proxy', __( 'Please accept the proxy (vekâlet) text to continue.', 'flx-donate' ) );
			}
			if ( is_numeric( $given ) && (int) $given > 1 && (int) $given !== (int) $text['version'] ) {
				/* The text changed after the page was opened: the donor must accept the current version. */
				return new WP_Error( 'flxb_proxy', __( 'The proxy (vekâlet) text has been updated. Please read it again and accept it.', 'flx-donate' ) );
			}
			$raw    = $in['beneficiaries'] ?? '';
			$names  = array_slice( flxb_split_names( is_array( $raw ) ? implode( "\n", array_filter( $raw, 'is_scalar' ) ) : (string) $raw ), 0, 1000 );
			$names  = array_map( static function ( $n ) { return mb_substr( $n, 0, 80 ); }, $names );
			$shares = '' === $line['option'] ? 1 : (int) $line['units'];
			if ( count( $names ) > $shares ) {
				/* translators: %s: number of shares */
				return new WP_Error( 'flxb_names', sprintf( _n( 'Please write at most %s name (one per share).', 'Please write at most %s names (one per share).', $shares, 'flx-donate' ), number_format_i18n( $shares ) ) );
			}
			$line['beneficiaries'] = $names;
			/* 2.16.0: a phone per share card, next to its name (empty = none). */
			$nums = isset( $in['beneficiary_phones'] ) && is_array( $in['beneficiary_phones'] ) ? array_slice( array_values( $in['beneficiary_phones'] ), 0, count( $names ) ) : array();
			$nums = array_map( static function ( $p ) { return mb_substr( preg_replace( '/[^0-9+]/', '', (string) $p ), 0, 32 ); }, $nums );
			if ( array_filter( $nums ) ) {
				$line['beneficiary_phones'] = array_pad( $nums, count( $names ), '' );
			} else {
				unset( $line['beneficiary_phones'] );
			}
			$line['proxy_consent'] = array( 'version' => (int) $text['version'], 'accepted_at' => flxb_now(), 'source' => 'cart' );
			$line['for_other']     = (bool) $names;
			$line['name']          = $names ? mb_substr( implode( ', ', $names ), 0, 80 ) : '';
			$line['send_me']       = false;
			if ( $names ) {
				$line['ben_name'] = $line['name'];
			} else {
				unset( $line['ben_name'] );
			}
		}
		if ( empty( $line['cert'] ) ) {
			/* No certificate for this type: nothing to send, so no delivery contacts. */
			$in['ben_phone'] = '';
			$in['ben_email'] = '';
		}
		$phone = trim( (string) ( $in['ben_phone'] ?? '' ) );
		if ( '' !== $phone ) {
			$norm = flxb_normalize_phone( $phone );
			if ( '' === $norm ) {
				return new WP_Error( 'flxb_phone', __( 'The phone number does not look right. Example: 0532 123 45 67', 'flx-donate' ) );
			}
			$line['ben_phone'] = $norm;
		}
		$email = trim( (string) ( $in['ben_email'] ?? '' ) );
		if ( '' !== $email ) {
			if ( ! is_email( $email ) ) {
				return new WP_Error( 'flxb_email', __( 'The e-mail address does not look right.', 'flx-donate' ) );
			}
			$line['ben_email'] = strtolower( sanitize_email( $email ) );
		}
		$line['send_me'] = $line['for_other'] && ! empty( $line['cert'] ) && ! empty( $in['send_me'] ) && empty( $line['proxy_consent'] );

		if ( ! empty( $in['monthly'] ) ) {
			if ( empty( $t['allow_recurring'] ) ) {
				return new WP_Error( 'flxb_monthly', __( 'This cause does not take monthly donations.', 'flx-donate' ) );
			}
			if ( ! self::monthly_available() ) {
				return new WP_Error( 'flxb_monthly', __( 'Monthly donations are not available at the moment.', 'flx-donate' ) );
			}
			$line['monthly'] = true;
		}
		if ( ! empty( $in['form_id'] ) ) {
			$line['form'] = array( 'id' => absint( $in['form_id'] ), 'track' => sanitize_text_field( (string) ( $in['track'] ?? '' ) ) );
		}
		return (array) apply_filters( 'flxb_wc_line', $line, $in, $t );
	}

	/** 2.15: the donor's currency now (store currency without the currency choice). */
	public static function currency() {
		return class_exists( 'FLXB_WC_Currency' ) ? FLXB_WC_Currency::current() : strtoupper( (string) get_woocommerce_currency() );
	}

	/**
	 * 2.15: the currency a line's amount was written in. Option lines are priced again in any currency;
	 * free and fixed amounts only count in their own currency (lines from before 2.15: the type's).
	 */
	public static function line_currency( array $line, $t = null ) {
		if ( ! empty( $line['currency'] ) ) {
			return strtoupper( (string) $line['currency'] );
		}
		$t = is_array( $t ) ? $t : FLXB_Types::get( (int) ( $line['type_id'] ?? 0 ) );
		return $t ? strtoupper( (string) $t['currency'] ) : self::currency();
	}

	/** 2.15: "Online + printed certificate" can be chosen (its fee is set in the store currency only). */
	public static function print_here() {
		if ( ! FLXB_WC::print_on() ) {
			return false;
		}
		return (float) FLXB_WC::setting( 'print_fee' ) <= 0 || ! class_exists( 'FLXB_WC_Currency' ) || FLXB_WC_Currency::current() === FLXB_WC_Currency::base();
	}

	/** Unit price of a line (server side) in the current currency; 0 = cannot be paid in it. */
	public static function line_price( array $line, $currency = '' ) {
		$t = FLXB_Types::get( (int) ( $line['type_id'] ?? 0 ) );
		if ( ! $t ) {
			return 0.0;
		}
		$cur   = '' !== (string) $currency ? strtoupper( (string) $currency ) : self::currency();
		$fixed = ! empty( $line['fixed'] ) && (float) ( $line['amount'] ?? 0 ) > 0;
		if ( $fixed || '' === (string) ( $line['option'] ?? '' ) ) {
			if ( self::line_currency( $line, $t ) !== $cur ) {
				return 0.0;
			}
			if ( $fixed ) {
				/* Priced on the server before it reached the cart (Flux Forms hand-off), never by the browser. */
				return round( (float) $line['amount'], 2 );
			}
			return self::takes_free( $t ) ? max( 0, (float) ( $line['amount'] ?? 0 ) ) : 0.0;
		}
		$o = FLXB_Types::option( $t, (string) $line['option'] );
		if ( ! $o || empty( $o['active'] ) ) {
			return 0.0;
		}
		return round( FLXB_Types::option_price( $t, $o, $cur ) * max( 1, (int) ( $line['units'] ?? 1 ) ), 2 );
	}

	/** Add a choice to the cart. @return string|WP_Error cart item key */
	public static function add( array $in ) {
		$line = self::build_line( $in );
		if ( is_wp_error( $line ) ) {
			return $line;
		}
		return self::add_line( $line );
	}

	public static function add_line( array $line ) {
		if ( ! WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		if ( ! empty( $line['fixed'] ) && class_exists( 'FLXB_WC_Currency' ) ) {
			/* 2.15: a form charges in its type's currency; the cart follows it when that currency is on offer. */
			$line['currency'] = self::line_currency( $line );
			if ( $line['currency'] !== FLXB_WC_Currency::current() && ! FLXB_WC_Currency::set( $line['currency'] ) ) {
				/* translators: %s: currency code */
				return new WP_Error( 'flxb_currency', sprintf( __( 'Donations in %s cannot be paid here at the moment.', 'flx-donate' ), $line['currency'] ) );
			}
		}
		$pid = FLXB_WC_Products::product_id( (int) $line['type_id'] );
		if ( ! $pid ) {
			return new WP_Error( 'flxb_product', __( 'The donation product could not be found.', 'flx-donate' ) );
		}
		$key = WC()->cart->add_to_cart( $pid, 1, 0, array(), array( self::KEY => $line ) );
		if ( ! $key ) {
			$err = wc_get_notices( 'error' );
			wc_clear_notices();
			$msg = $err ? wp_strip_all_tags( is_array( $err[0] ) ? $err[0]['notice'] : $err[0] ) : __( 'It could not be added to the cart.', 'flx-donate' );
			return new WP_Error( 'flxb_cart', $msg );
		}
		return $key;
	}

	/* ------------------------------------------------------------------
	 * Cart
	 * ------------------------------------------------------------------ */

	/** A donation product added with ?add-to-cart=ID gets a sensible default (first option / minimum). */
	public static function default_line( $data, $product_id ) {
		if ( isset( $data[ self::KEY ] ) ) {
			return $data;
		}
		$tid = FLXB_WC_Products::type_of_product( $product_id );
		$t   = $tid ? FLXB_Types::get( $tid ) : null;
		if ( ! $t ) {
			return $data;
		}
		$in = array( 'type_id' => $tid );
		foreach ( $t['options'] as $o ) {
			if ( FLXB_Types::option_on_sale_in( $t, $o, self::currency() ) ) {
				$in['option'] = $o['key'];
				break;
			}
		}
		if ( empty( $in['option'] ) ) {
			$in['amount'] = max( 1, self::min_free( $t, self::currency() ) );
		}
		$line = self::build_line( $in );
		if ( ! is_wp_error( $line ) ) {
			$data[ self::KEY ] = $line;
		}
		return $data;
	}

	public static function prices( $cart ) {
		foreach ( $cart->get_cart() as $item ) {
			if ( ! empty( $item[ self::KEY ] ) && isset( $item['data'] ) && $item['data'] instanceof WC_Product ) {
				$price = self::line_price( $item[ self::KEY ] );
				$item['data']->set_price( $price );
				/* 2.15: the product's list price is in the store currency; without this the block cart shows a "discount". */
				$item['data']->set_regular_price( $price );
				/* 2.15.1: the hidden product's name in the visitor's language (block cart, checkout, order line). */
				if ( '' !== FLXB_Types::lang() ) {
					$t = FLXB_Types::get( (int) ( $item[ self::KEY ]['type_id'] ?? 0 ) );
					if ( $t ) {
						$item['data']->set_name( FLXB_Types::localize( $t )['title'] );
					}
				}
			}
		}
	}

	public static function from_session( $item, $values ) {
		if ( isset( $values[ self::KEY ] ) ) {
			$item[ self::KEY ] = $values[ self::KEY ];
		}
		return $item;
	}

	/** Lines whose type closed or option went off sale since they were added are removed. */
	public static function check_cart() {
		if ( ! WC()->cart ) {
			return;
		}
		$cur   = self::currency();
		$other = false;
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( empty( $item[ self::KEY ] ) ) {
				$other = true;
				continue;
			}
			if ( self::line_price( $item[ self::KEY ] ) <= 0 ) {
				$here = self::line_price( $item[ self::KEY ], self::line_currency( $item[ self::KEY ] ) ) > 0;
				WC()->cart->remove_cart_item( $key );
				/* translators: %s: currency code */
				wc_add_notice( $here ? sprintf( __( 'A donation in your cart cannot be paid in %s and was removed.', 'flx-donate' ), $cur ) : __( 'A donation in your cart is no longer available and was removed.', 'flx-donate' ), 'error' );
				continue;
			}
			if ( 'print' === ( $item[ self::KEY ]['cert'] ?? '' ) && ! self::print_here() ) {
				/* 2.15: the printed certificate fee is in the store currency only. */
				WC()->cart->cart_contents[ $key ][ self::KEY ]['cert'] = 'online';
				WC()->cart->set_session();
				wc_add_notice( __( 'Printed certificates can only be paid in the store currency; your certificate will be sent online.', 'flx-donate' ), 'notice' );
			}
		}
		if ( $other && class_exists( 'FLXB_WC_Currency' ) && FLXB_WC_Currency::base() !== $cur ) {
			/* Shop products have one price, in the store currency. */
			/* translators: 1: store currency, 2: switch link */
			wc_add_notice( sprintf( __( 'Products in your cart can only be paid in %1$s. <a href="%2$s">Switch to %1$s</a>', 'flx-donate' ), esc_html( FLXB_WC_Currency::base() ), esc_url( FLXB_WC_Currency::url( FLXB_WC_Currency::base() ) ) ), 'error' );
		}
	}

	/** Printed certificate fee (per line; none when 0). */
	public static function fees( $cart ) {
		$fee = (float) FLXB_WC::setting( 'print_fee' );
		if ( $fee <= 0 || ! self::print_here() ) {
			return;
		}
		$n = 0;
		foreach ( $cart->get_cart() as $item ) {
			if ( 'print' === ( $item[ self::KEY ]['cert'] ?? '' ) ) {
				++$n;
			}
		}
		if ( $n ) {
			$cart->add_fee( __( 'Printed certificate delivery', 'flx-donate' ), $fee * $n, false );
		}
	}

	public static function item_data( $data, $item ) {
		if ( empty( $item[ self::KEY ] ) ) {
			return $data;
		}
		foreach ( self::summary( $item[ self::KEY ], (int) ( $item['quantity'] ?? 1 ) ) as $k => $v ) {
			$data[] = array( 'key' => $k, 'value' => $v, 'display' => esc_html( $v ) );
		}
		return $data;
	}

	/** Units a line stands for (option count × units per choice × cart quantity). */
	public static function units( array $line, $qty = 1 ) {
		$t = FLXB_Types::get( (int) ( $line['type_id'] ?? 0 ) );
		$o = $t && '' !== (string) ( $line['option'] ?? '' ) ? FLXB_Types::option( $t, (string) $line['option'] ) : null;
		if ( ! $o ) {
			/* 2.23.0: a free amount on a type sold by the piece. */
			$n = (int) ( $line['free_units'] ?? 0 );
			return $n > 0 && '' !== (string) ( $line['free_unit'] ?? '' ) ? array( $n * max( 1, (int) $qty ), (string) $line['free_unit'] ) : array( 0, '' );
		}
		return array( max( 1, (int) ( $line['units'] ?? 1 ) ) * max( 1, (int) $o['units_per'] ) * max( 1, (int) $qty ), (string) $o['unit'] );
	}

	/** Label => value lines shown under a donation in the cart and checkout. */
	public static function summary( array $line, $qty = 1 ) {
		$out = array();
		$t   = FLXB_Types::get( (int) ( $line['type_id'] ?? 0 ) );
		$t   = $t && ! is_admin() ? FLXB_Types::localize( $t ) : $t;
		list( $n, $unit ) = self::units( $line, $qty );
		if ( $n && '' !== $unit ) {
			$out[ __( 'Quantity', 'flx-donate' ) ] = FLXB_WC::units_text( $n, $unit );
		} elseif ( $t && '' !== (string) ( $line['option'] ?? '' ) && count( $t['options'] ) > 1 ) {
			$o = FLXB_Types::option( $t, (string) $line['option'] );
			if ( $o ) {
				$out[ __( 'Option', 'flx-donate' ) ] = $o['label'] . ( (int) ( $line['units'] ?? 1 ) > 1 ? ' × ' . number_format_i18n( (int) $line['units'] ) : '' );
			}
		}
		if ( ! empty( $line['monthly'] ) ) {
			$out[ __( 'Repeats', 'flx-donate' ) ] = __( 'Every month', 'flx-donate' );
		}
		if ( ! empty( $line['beneficiaries'] ) && is_array( $line['beneficiaries'] ) ) {
			$out[ __( 'Names (one per share)', 'flx-donate' ) ] = self::names_text( $line['beneficiaries'] );
		} elseif ( ! empty( $line['for_other'] ) && '' !== (string) ( $line['ben_name'] ?? '' ) ) {
			$out[ __( 'On behalf of', 'flx-donate' ) ] = $line['ben_name'];
		}
		if ( ! empty( $line['proxy_consent']['version'] ) ) {
			$out[ __( 'Proxy consent', 'flx-donate' ) ] = self::consent_text( $line['proxy_consent'] );
		}
		if ( ! empty( $line['cert'] ) ) {
			$out[ __( 'Certificate', 'flx-donate' ) ] = self::cert_label( $line['cert'] );
			if ( ! empty( $line['tpl_name'] ) ) {
				$out[ __( 'Design', 'flx-donate' ) ] = (string) $line['tpl_name'];
			}
			if ( '' !== (string) ( $line['name'] ?? '' ) && empty( $line['for_other'] ) ) {
				$out[ __( 'Name on the certificate', 'flx-donate' ) ] = $line['name'];
			}
			$to = array_filter( array( $line['ben_phone'] ?? '', $line['ben_email'] ?? '' ) );
			if ( $to ) {
				$out[ __( 'Sent to', 'flx-donate' ) ] = implode( ', ', $to ) . ( ! empty( $line['send_me'] ) ? ' · ' . __( 'copy to me', 'flx-donate' ) : '' );
			}
			if ( ! empty( $line['address'] ) ) {
				$out[ __( 'Postal address', 'flx-donate' ) ] = $line['address'];
			}
		}
		return $out;
	}

	/** "1. Ali Veli · 2. Ayşe Kaya" (share order). */
	public static function names_text( array $names ) {
		$out = array();
		foreach ( array_values( $names ) as $i => $n ) {
			$out[] = number_format_i18n( $i + 1 ) . '. ' . $n;
		}
		return implode( ' · ', $out );
	}

	public static function consent_text( array $c ) {
		/* translators: %s: proxy text version */
		return sprintf( __( 'accepted (text version %s)', 'flx-donate' ), number_format_i18n( (int) ( $c['version'] ?? 0 ) ) );
	}

	public static function cert_label( $cert ) {
		return 'print' === $cert ? __( 'Online + printed', 'flx-donate' ) : __( 'Online', 'flx-donate' );
	}

	/** Line title: "7 Sapling Donation" / type title (option). */
	public static function line_title( array $line, $qty = 1 ) {
		$t = FLXB_Types::get( (int) ( $line['type_id'] ?? 0 ) );
		if ( ! $t ) {
			return '';
		}
		$t = is_admin() ? $t : FLXB_Types::localize( $t ); // 2.15: shop pages in the visitor's language
		list( $n, $unit ) = self::units( $line, 1 );
		if ( 'fidan' === $unit && $n ) {
			/* translators: %s: number of saplings */
			return sprintf( __( '%s Sapling Donation', 'flx-donate' ), number_format_i18n( $n ) );
		}
		$title = (string) $t['title'];
		if ( '' !== (string) ( $line['option'] ?? '' ) && count( $t['options'] ) > 1 ) {
			$o = FLXB_Types::option( $t, (string) $line['option'] );
			if ( $o && false === mb_stripos( $title, $o['label'] ) ) {
				$title .= ' · ' . $o['label'];
			}
		}
		return $title;
	}

	public static function cart_item_name( $name, $item ) {
		if ( empty( $item[ self::KEY ] ) ) {
			return $name;
		}
		$title = self::line_title( $item[ self::KEY ] );
		return '' !== $title ? esc_html( $title ) : $name;
	}

	/** Custom count and free-amount lines keep quantity 1 in the cart (the count is in the line). */
	public static function fixed_quantity( $html, $key, $item ) {
		if ( ! empty( $item[ self::KEY ] ) && ( '' === (string) ( $item[ self::KEY ]['option'] ?? '' ) || (int) ( $item[ self::KEY ]['units'] ?? 1 ) > 1 || ! empty( $item[ self::KEY ]['monthly'] ) ) ) {
			return '1<input type="hidden" name="cart[' . esc_attr( $key ) . '][qty]" value="1">';
		}
		return $html;
	}

	/* ------------------------------------------------------------------
	 * Order lines
	 * ------------------------------------------------------------------ */

	public static function order_line( $item, $cart_key, $values ) {
		if ( empty( $values[ self::KEY ] ) ) {
			return;
		}
		self::write_line_meta( $item, $values[ self::KEY ], (int) $values['quantity'] );
	}

	/** Meta of a donation order line (checkout, renewals, Flux Chat orders). */
	public static function write_line_meta( WC_Order_Item_Product $item, array $l, $qty = 1 ) {
		$item->add_meta_data( '_flxb', $l, true );
		$item->add_meta_data( '_flxb_type_id', (int) $l['type_id'], true );
		$title = self::line_title( $l );
		if ( '' !== $title ) {
			$item->set_name( $title );
		}
		list( $n, $unit ) = self::units( $l, $qty );
		if ( $n && 'fidan' === $unit ) {
			$item->add_meta_data( 'flxb_fidan', $n, true );
		} elseif ( $n && '' !== $unit ) {
			$item->add_meta_data( 'flxb_units', $n . '|' . $unit, true );
		}
		if ( ! empty( $l['monthly'] ) ) {
			$item->add_meta_data( 'flxb_monthly', 1, true );
		}
		if ( ! empty( $l['beneficiaries'] ) && is_array( $l['beneficiaries'] ) ) {
			$item->add_meta_data( 'flxb_names', self::names_text( $l['beneficiaries'] ), true );
		} elseif ( ! empty( $l['for_other'] ) && '' !== (string) ( $l['ben_name'] ?? '' ) ) {
			$item->add_meta_data( 'flxb_on_behalf', $l['ben_name'], true );
		}
		if ( ! empty( $l['proxy_consent']['version'] ) ) {
			$item->add_meta_data( 'flxb_proxy', (int) $l['proxy_consent']['version'], true );
		}
		if ( ! empty( $l['cert'] ) ) {
			$item->add_meta_data( 'flxb_cert', $l['cert'], true );
			if ( ! empty( $l['tpl_name'] ) ) {
				$item->add_meta_data( 'flxb_cert_design', (string) $l['tpl_name'], true );
			}
			if ( '' !== (string) ( $l['name'] ?? '' ) ) {
				$item->add_meta_data( 'flxb_cert_name', $l['name'], true );
			}
			if ( ! empty( $l['address'] ) ) {
				$item->add_meta_data( 'flxb_cert_address', $l['address'], true );
			}
		}
	}

	public static function meta_key_label( $key, $meta ) {
		$map = array(
			'flxb_fidan'     => __( 'Saplings', 'flx-donate' ),
			'flxb_units'      => __( 'Quantity', 'flx-donate' ),
			'flxb_monthly'    => __( 'Repeats', 'flx-donate' ),
			'flxb_on_behalf'  => __( 'On behalf of', 'flx-donate' ),
			'flxb_names'      => __( 'Names (one per share)', 'flx-donate' ),
			'flxb_proxy'      => __( 'Proxy consent', 'flx-donate' ),
			'flxb_cert'      => __( 'Certificate', 'flx-donate' ),
			'flxb_cert_name' => __( 'Name on the certificate', 'flx-donate' ),
			'flxb_cert_design' => __( 'Design', 'flx-donate' ),
			'flxb_cert_address'   => __( 'Postal address', 'flx-donate' ),
			'flxb_cert_no'   => __( 'Certificate no', 'flx-donate' ),
		);
		return $map[ $meta->key ] ?? $key;
	}

	public static function meta_value_label( $value, $meta ) {
		switch ( $meta->key ) {
			case 'flxb_cert':
				return esc_html( self::cert_label( $meta->value ) );
			case 'flxb_fidan':
				return esc_html( FLXB_WC::units_text( (int) $meta->value, 'fidan' ) );
			case 'flxb_units':
				$p = explode( '|', (string) $meta->value );
				return esc_html( FLXB_WC::units_text( (int) $p[0], (string) ( $p[1] ?? '' ) ) );
			case 'flxb_monthly':
				return esc_html__( 'Every month', 'flx-donate' );
			case 'flxb_proxy':
				return esc_html( self::consent_text( array( 'version' => (int) $meta->value ) ) );
		}
		return $value;
	}

	public static function hidden_meta( $keys ) {
		return array_merge( (array) $keys, array( '_flxb', '_flxb_type_id', '_flxb_donation_id', '_flxb_cert_ids', '_flxb_cert_id' ) );
	}

	/** Donation lines of an order: item id => item. */
	public static function order_lines( WC_Order $order ) {
		$out = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( self::item_line( $item ) ) {
				$out[ $item_id ] = $item;
			}
		}
		return $out;
	}

	/** Normalised line of an order item (null when it is not a donation). */
	public static function item_line( $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return null;
		}
		$l = $item->get_meta( '_flxb' );
		if ( is_array( $l ) && ! empty( $l['type_id'] ) ) {
			return $l;
		}
		return null;
	}

	public static function order_is_donation_only( WC_Order $order ) {
		$items = $order->get_items();
		return $items && count( self::order_lines( $order ) ) === count( $items );
	}

	/** Total saplings (or units of the first unit kind) in an order. */
	public static function order_units( WC_Order $order, $unit = 'fidan' ) {
		$n = 0;
		foreach ( self::order_lines( $order ) as $item ) {
			if ( 'fidan' === $unit ) {
				$n += (int) $item->get_meta( 'flxb_fidan' );
			} else {
				$p = explode( '|', (string) $item->get_meta( 'flxb_units' ) );
				$n += ( $p[1] ?? '' ) === $unit ? (int) $p[0] : 0;
			}
		}
		return $n;
	}

	public static function order_has_monthly( WC_Order $order ) {
		foreach ( self::order_lines( $order ) as $item ) {
			if ( (int) $item->get_meta( 'flxb_monthly' ) ) {
				return true;
			}
		}
		return false;
	}

	/** Online-certificate donations go straight to Completed when paid; printed ones stay Processing. */
	public static function complete_status( $status, $order_id, $order = null ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order || ! FLXB_WC::setting( 'auto_complete' ) || ! self::order_is_donation_only( $order ) ) {
			return $status;
		}
		foreach ( self::order_lines( $order ) as $item ) {
			if ( 'print' === $item->get_meta( 'flxb_cert' ) ) {
				return $status;
			}
		}
		return 'completed';
	}

	/* ------------------------------------------------------------------
	 * Checkout fields
	 * ------------------------------------------------------------------ */

	public static function cart_donation_only() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( empty( $item[ self::KEY ] ) ) {
				return false;
			}
		}
		return true;
	}

	public static function cart_has_monthly() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item[ self::KEY ]['monthly'] ) ) {
				return true;
			}
		}
		return false;
	}

	public static function trim_fields() {
		return (bool) apply_filters( 'flxb_wc_simple_checkout', FLXB_WC::setting( 'simple_address' ) && self::cart_donation_only() );
	}

	/** Address fields a donation-only checkout does not ask for (2.0.2: the whole address, not only line 2 / postcode). */
	public static function hidden_fields() {
		return (array) apply_filters( 'flxb_wc_hidden_checkout_fields', array( 'company', 'address_1', 'address_2', 'city', 'state', 'postcode' ) );
	}

	/** Donation-only cart: no address on the checkout (classic and block checkout). */
	public static function locale( $locale ) {
		if ( ! self::trim_fields() ) {
			return $locale;
		}
		$hide = array_fill_keys( self::hidden_fields(), array( 'required' => false, 'hidden' => true ) );
		foreach ( $locale as $cc => $fields ) {
			$locale[ $cc ] = array_replace_recursive( (array) $fields, $hide );
		}
		return $locale;
	}

	public static function locale_default( $fields ) {
		if ( self::trim_fields() ) {
			foreach ( self::hidden_fields() as $k ) {
				$fields[ $k ] = array_merge( (array) ( $fields[ $k ] ?? array() ), array( 'required' => false, 'hidden' => true ) );
			}
		}
		return $fields;
	}

	/** Classic checkout: drop the hidden fields from the form altogether. */
	public static function checkout_fields( $fields ) {
		if ( self::trim_fields() && isset( $fields['billing'] ) ) {
			foreach ( self::hidden_fields() as $k ) {
				unset( $fields['billing'][ 'billing_' . $k ] );
			}
		}
		return $fields;
	}

	/**
	 * Payment providers (iyzico) need a buyer address and city. When the checkout did not ask for them,
	 * fill in the organisation's address (WooCommerce store address) so the payment is not refused.
	 */
	public static function fill_address( $order ) {
		if ( ! $order instanceof WC_Order || ! self::order_is_donation_only( $order ) ) {
			return;
		}
		$c = function_exists( 'WC' ) && WC()->countries ? WC()->countries : null;
		if ( '' === trim( (string) $order->get_billing_country() ) ) {
			$order->set_billing_country( $c ? $c->get_base_country() : 'TR' );
		}
		if ( '' === trim( (string) $order->get_billing_city() ) ) {
			$city = $c ? trim( (string) $c->get_base_city() ) : '';
			$order->set_billing_city( '' !== $city ? $city : (string) apply_filters( 'flxb_wc_default_city', 'İstanbul' ) );
		}
		if ( '' === trim( (string) $order->get_billing_address_1() ) ) {
			$addr = $c ? trim( (string) $c->get_base_address() ) : '';
			$order->set_billing_address_1( '' !== $addr ? $addr : __( 'Online donation', 'flx-donate' ) );
			$order->update_meta_data( '_flxb_address_filled', 1 ); // not the donor's address: kept out of the address book
		}
	}

	/** Store API (block checkout) hook: the order is filled from the request, then saved. */
	public static function fill_address_store_api( $order ) {
		self::fill_address( $order );
	}
}
