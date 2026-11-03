<?php
/**
 * Sepet ve ödeme sayfası iyileştirmeleri:
 *  - Sade alanlar: şirket ve adres 2. satır gizlenebilir; yalnız sanal/indirilebilir ürün varsa adres
 *    alanları kalkar (klasik ödeme). Sipariş notu açılıp kapanır.
 *  - Mobilde "Siparişi ver" düğmesi altta sabit (klasik ve blok ödeme).
 *  - Ücretsiz kargo çubuğu: sepette ve ödemede "X ₺ daha ekleyin, kargo bedava" + ilerleme çubuğu. Eşik
 *    ayardan ya da WooCommerce'in "Ücretsiz kargo" yönteminin en az tutarından gelir.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Checkout {

	public static function init() {
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'fields' ), 50 );
		add_filter( 'woocommerce_enable_order_notes_field', array( __CLASS__, 'notes' ), 50 );
		add_filter( 'pre_option_woocommerce_checkout_company_field', array( __CLASS__, 'block_company' ) );
		add_filter( 'pre_option_woocommerce_checkout_address_2_field', array( __CLASS__, 'block_address_2' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		add_action( 'woocommerce_before_cart_table', array( __CLASS__, 'bar_echo' ) );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'bar_echo' ), 5 );
		add_action( 'woocommerce_before_mini_cart_contents', array( __CLASS__, 'bar_echo' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'fragments' ) );
		add_filter( 'render_block_woocommerce/cart', array( __CLASS__, 'bar_block' ) );
		add_filter( 'render_block_woocommerce/checkout', array( __CLASS__, 'bar_block' ) );
		foreach ( array( 'woocommerce_shipping_zone_method_added', 'woocommerce_shipping_zone_method_deleted', 'woocommerce_shipping_zone_method_status_toggled', 'woocommerce_update_options_shipping', 'woocommerce_delete_shipping_zone' ) as $h ) {
			add_action( $h, array( __CLASS__, 'flush_threshold' ) );
		}
	}

	public static function flush_threshold() {
		delete_transient( 'flxcm_free_min' );
	}

	/** Sepette yalnız kargo gerektirmeyen (sanal) ürün mü var? */
	public static function virtual_only() {
		return function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() && ! WC()->cart->needs_shipping();
	}

	public static function fields( $fields ) {
		if ( flxcm_setting( 'hide_company' ) ) {
			unset( $fields['billing']['billing_company'], $fields['shipping']['shipping_company'] );
		}
		if ( flxcm_setting( 'hide_address_2' ) ) {
			unset( $fields['billing']['billing_address_2'], $fields['shipping']['shipping_address_2'] );
		}
		if ( flxcm_setting( 'virtual_no_address' ) && self::virtual_only() ) {
			foreach ( array( 'billing_company', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_postcode', 'billing_state' ) as $k ) {
				unset( $fields['billing'][ $k ] );
			}
		}
		return $fields;
	}

	public static function notes( $on ) {
		return flxcm_setting( 'order_notes' ) ? $on : false;
	}

	public static function block_company( $v ) {
		return flxcm_setting( 'hide_company' ) ? 'hidden' : $v;
	}

	public static function block_address_2( $v ) {
		return flxcm_setting( 'hide_address_2' ) ? 'hidden' : $v;
	}

	public static function assets() {
		$cart = function_exists( 'is_cart' ) && is_cart();
		$co   = function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page();
		if ( ! $cart && ! $co && ! ( function_exists( 'is_account_page' ) && is_account_page() ) ) {
			return;
		}
		wp_enqueue_style( 'flxcm-front', FLXCM_URL . 'assets/front.css', array(), FLXCM_VERSION );
		if ( $cart || $co ) {
			wp_enqueue_script( 'flxcm-ship', FLXCM_URL . 'assets/ship.js', array(), FLXCM_VERSION, true );
		}
		// FluxWP teması ödeme düğmesini zaten altta sabit tutar.
		if ( $co && flxcm_setting( 'sticky_pay' ) && ! current_theme_supports( 'flx-theme' ) ) {
			wp_add_inline_style(
				'flxcm-front',
				'@media (max-width:640px){'
				. '.woocommerce-checkout #payment .place-order{position:sticky;bottom:0;z-index:20;margin:0 -1em;padding:12px 1em calc(12px + env(safe-area-inset-bottom));background:var(--wp--preset--color--base,#fff);box-shadow:0 -6px 18px rgba(0,0,0,.08)}'
				. '.woocommerce-checkout #payment #place_order{width:100%;min-height:48px}'
				. '.wc-block-checkout__actions{position:sticky;bottom:0;z-index:20;padding:12px 0 calc(12px + env(safe-area-inset-bottom));background:var(--wp--preset--color--base,#fff);box-shadow:0 -6px 18px rgba(0,0,0,.08)}'
				. '.wc-block-checkout__actions .wc-block-components-checkout-place-order-button{width:100%;min-height:48px}'
				. '}'
			);
		}
	}

	/** Ücretsiz kargo eşiği: ayar, yoksa açık "Ücretsiz kargo" yöntemlerinin en düşük en az tutarı. */
	public static function free_threshold() {
		$v = (float) flxcm_setting( 'free_ship_amount' );
		if ( $v <= 0 && class_exists( 'WC_Shipping_Zones' ) ) {
			$cache = get_transient( 'flxcm_free_min' );
			if ( false === $cache ) {
				$cache = 0.0;
				$zones = WC_Shipping_Zones::get_zones();
				$zones[] = array( 'shipping_methods' => ( new WC_Shipping_Zone( 0 ) )->get_shipping_methods( true ) );
				foreach ( $zones as $z ) {
					foreach ( (array) ( $z['shipping_methods'] ?? array() ) as $m ) {
						if ( $m instanceof WC_Shipping_Free_Shipping && 'yes' === $m->enabled && in_array( $m->requires, array( 'min_amount', 'either' ), true ) && (float) $m->min_amount > 0 ) {
							$cache = $cache > 0 ? min( $cache, (float) $m->min_amount ) : (float) $m->min_amount;
						}
					}
				}
				set_transient( 'flxcm_free_min', $cache, 10 * MINUTE_IN_SECONDS );
			}
			$v = (float) $cache;
		}
		return (float) apply_filters( 'flxcm_free_shipping_threshold', $v );
	}

	/** Çubuk HTML'i (sepet boşsa, kargo gerekmiyorsa ya da eşik yoksa boş). */
	public static function bar() {
		if ( ! flxcm_setting( 'free_ship_bar' ) || ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() || ! WC()->cart->needs_shipping() ) {
			return '<div class="flxcm-ship" hidden></div>';
		}
		$min = self::free_threshold();
		if ( $min <= 0 ) {
			return '<div class="flxcm-ship" hidden></div>';
		}
		$sub  = (float) WC()->cart->get_displayed_subtotal() - (float) WC()->cart->get_discount_total();
		$left = max( 0, $min - $sub );
		$pct  = min( 100, max( 0, $sub / $min * 100 ) );
		$text = $left > 0
			/* translators: %s: amount */
			? sprintf( __( 'Ücretsiz kargo için %s daha ekleyin.', 'flx-commerce' ), '<strong>' . esc_html( flxcm_money( $left ) ) . '</strong>' )
			: '<strong>' . esc_html__( 'Kargo bedava!', 'flx-commerce' ) . '</strong>';
		$data = array(
			'min'  => $min,
			'incl' => WC()->cart->display_prices_including_tax() ? 1 : 0,
			/* translators: %s: amount */
			'left' => sprintf( __( 'Ücretsiz kargo için %s daha ekleyin.', 'flx-commerce' ), '<strong>%s</strong>' ),
			'done' => '<strong>' . esc_html__( 'Kargo bedava!', 'flx-commerce' ) . '</strong>',
		);
		return '<div class="flxcm-ship' . ( $left > 0 ? '' : ' is-done' ) . '" role="status" data-flxcm-ship="' . esc_attr( wp_json_encode( $data ) ) . '"><span class="flxcm-ship__icon">' . flxcm_icon( 'truck', 18 ) . '</span><div class="flxcm-ship__body"><p data-flxcm-ship-text>' . $text . '</p><span class="flxcm-ship__bar"><span style="width:' . esc_attr( round( $pct, 1 ) ) . '%"></span></span></div></div>';
	}

	public static function bar_echo() {
		echo self::bar(); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function fragments( $f ) {
		$f['div.flxcm-ship'] = self::bar();
		return $f;
	}

	public static function bar_block( $html ) {
		if ( false !== strpos( $html, 'flxcm-ship' ) || is_admin() || wp_doing_ajax() ) {
			return $html;
		}
		$bar = self::bar();
		return 0 === strpos( $bar, '<div class="flxcm-ship" hidden' ) ? $html : $bar . $html;
	}
}
