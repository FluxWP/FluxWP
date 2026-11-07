<?php
/**
 * 2.17.0: "I will cover the transaction fee".
 *
 *  - Settings › Appearance › Transaction fee (flxb_look: fee_on, fee_checked, fee_rates per gateway: % + fixed).
 *  - The donation form shows one box near the end with the highest possible fee ("up to …").
 *  - The choice is kept in the WooCommerce session; the checkout adds a fee line for the payment method
 *    the donor picks (block checkout: the picked method is sent with extensionCartUpdate, namespace flxb-fee).
 *  - The order keeps _flxb_fee_cover = 1. The fee is not part of the donation amount (fee line, not an item).
 *
 * @package FluxDonate
 */

defined( 'ABSPATH' ) || exit;

class FLXB_WC_Fee {

	const NS      = 'flxb-fee';
	const SESSION = 'flxb_fee_cover';

	public static function init() {
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'add_fee' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'checkout_script' ), 25 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'order_meta' ) );
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			self::register_update();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'register_update' ) );
		}
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'store_api_order' ), 20 );
	}

	public static function on() {
		return (bool) FLXB_WC_Look::get( 'fee_on' ) && (bool) self::rates();
	}

	/** [ gateway id => [ pct, fixed ] ] for enabled gateways only. */
	public static function rates() {
		$all = (array) FLXB_WC_Look::get( 'fee_rates' );
		$out = array();
		foreach ( self::gateways() as $id => $title ) {
			if ( isset( $all[ $id ] ) ) {
				$out[ $id ] = array( 'pct' => (float) $all[ $id ]['pct'], 'fixed' => (float) $all[ $id ]['fixed'] );
			}
		}
		return $out;
	}

	/** Enabled payment methods: [ id => title ]. */
	public static function gateways() {
		$out = array();
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return $out;
		}
		foreach ( WC()->payment_gateways()->payment_gateways() as $id => $g ) {
			if ( 'yes' === $g->enabled ) {
				$out[ (string) $id ] = wp_strip_all_tags( (string) $g->get_title() );
			}
		}
		return $out;
	}

	public static function amount( $base, $gateway ) {
		$r = self::rates()[ (string) $gateway ] ?? null;
		if ( ! $r || $base <= 0 ) {
			return 0.0;
		}
		return round( $base * $r['pct'] / 100 + $r['fixed'], wc_get_price_decimals() );
	}

	/** For the form script: all rates (the form shows the highest). */
	public static function rates_for_form() {
		return self::on() ? array_values( self::rates() ) : array();
	}

	public static function form_box( array $old ) {
		if ( ! self::on() ) {
			return '';
		}
		$on = isset( $old['flxb_action'] ) ? ! empty( $old['fee_cover'] ) : ( function_exists( 'WC' ) && WC()->session && null !== WC()->session->get( self::SESSION ) ? (bool) WC()->session->get( self::SESSION ) : (bool) FLXB_WC_Look::get( 'fee_checked' ) );
		return '<label class="flxwb-check flxwb-fee" data-flxwb-fee><input type="checkbox" name="fee_cover" value="1"' . checked( $on, true, false ) . '> <span><strong>' . esc_html__( 'I will also cover the transaction fee', 'flx-donate' ) . '</strong> <small data-flxwb-fee-amount></small><small class="flxwb-fee__help">' . esc_html__( 'So your whole donation reaches its purpose. The exact fee depends on the payment method and is shown at the checkout.', 'flx-donate' ) . '</small></span></label>';
	}

	/** Keep the form's choice for the checkout. */
	public static function remember( array $in ) {
		if ( self::on() && function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::SESSION, ! empty( $in['fee_cover'] ) ? 1 : 0 );
		}
	}

	public static function covered() {
		return self::on() && function_exists( 'WC' ) && WC()->session && (int) WC()->session->get( self::SESSION );
	}

	private static function chosen() {
		$gw = WC()->session ? (string) WC()->session->get( 'chosen_payment_method' ) : '';
		if ( '' === $gw || ! isset( self::gateways()[ $gw ] ) ) {
			$avail = WC()->payment_gateways() ? WC()->payment_gateways()->get_available_payment_gateways() : array();
			$gw    = $avail ? (string) array_key_first( $avail ) : '';
		}
		return $gw;
	}

	public static function add_fee( $cart ) {
		if ( ! self::covered() || ! $cart instanceof WC_Cart ) {
			return;
		}
		$base = (float) $cart->get_cart_contents_total();
		$fee  = self::amount( $base, self::chosen() );
		if ( $fee > 0 ) {
			$cart->add_fee( __( 'Transaction fee (covered by you)', 'flx-donate' ), $fee, false );
		}
	}

	public static function register_update() {
		if ( ! function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			return;
		}
		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => self::NS,
				'callback'  => static function ( $data ) {
					if ( ! WC()->session ) {
						return;
					}
					if ( isset( $data['method'] ) ) {
						WC()->session->set( 'chosen_payment_method', sanitize_key( (string) $data['method'] ) );
					}
					if ( isset( $data['cover'] ) ) {
						WC()->session->set( self::SESSION, $data['cover'] ? 1 : 0 );
					}
				},
			)
		);
	}

	/** The checkout re-calculates the fee when the donor picks another payment method. */
	public static function checkout_script() {
		if ( ! self::on() || ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url() ) {
			return;
		}
		wp_enqueue_script( 'flxb-fee', FLXB_URL . 'assets/wc/fee.js', array( 'wp-data' ), FLXB_VERSION, true );
		wp_add_inline_script( 'flxb-fee', 'window.FLXB_FEE=' . wp_json_encode( array( 'ns' => self::NS, 'on' => self::covered() ) ) . ';', 'before' );
	}

	public static function order_meta( $order ) {
		if ( self::covered() ) {
			$order->update_meta_data( '_flxb_fee_cover', 1 );
		}
	}

	public static function store_api_order( $order ) {
		self::order_meta( $order );
	}
}
