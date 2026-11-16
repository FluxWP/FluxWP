<?php
/**
 * Abonelik ürünü: basit WooCommerce ürününe aylık / yıllık fiyat, ürün sayfasında dönem seçimi, sepette dönem fiyatı.
 *
 * Ürün meta: _flxcm_sub (yes), _flxcm_sub_month, _flxcm_sub_year (boş = o dönem sunulmaz).
 * Sepet satırı: flxcm_sub = { period, meta }.  Sipariş satırı: _flxcm_period (+ görünen flxcm_period), _flxcm_sub_meta.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Sub_Product {

	public static function init() {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'price_html' ), 20, 2 );
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'chooser' ) );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'from_session' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'prices' ), 20 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_price', array( __CLASS__, 'cart_price' ), 10, 2 );
		add_filter( 'woocommerce_is_sold_individually', array( __CLASS__, 'individually' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'line_item' ), 10, 3 );
		add_filter( 'woocommerce_order_item_display_meta_key', array( __CLASS__, 'meta_key' ), 10, 2 );
		add_filter( 'woocommerce_order_item_display_meta_value', array( __CLASS__, 'meta_value' ), 10, 2 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( __CLASS__, 'hidden_meta' ) );
		add_filter( 'woocommerce_checkout_registration_required', array( __CLASS__, 'require_account' ) );
		add_filter( 'woocommerce_checkout_registration_enabled', array( __CLASS__, 'enable_account' ) );
		add_filter( 'woocommerce_product_add_to_cart_text', array( __CLASS__, 'button_text' ), 10, 2 );
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( __CLASS__, 'button_text' ), 10, 2 );
	}

	/** Ürün abonelik mi? */
	public static function is_sub( $product ) {
		$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
		return $product && 'yes' === $product->get_meta( '_flxcm_sub' ) && $product->is_type( 'simple' ) && self::prices_of( $product );
	}

	/**
	 * Sunulan dönemlerin fiyatları: [ 'month' => 599.0, 'year' => 5990.0 ].
	 *
	 * @return array<string,float>
	 */
	public static function prices_of( WC_Product $product ) {
		$out = array();
		foreach ( FLXCM_Subscriptions::PERIODS as $p ) {
			$v = $product->get_meta( '_flxcm_sub_' . $p );
			if ( '' !== (string) $v && (float) $v > 0 ) {
				$out[ $p ] = (float) $v;
			}
		}
		return (array) apply_filters( 'flxcm_subscription_prices', $out, $product );
	}

	public static function fields() {
		global $product_object;
		$p = $product_object instanceof WC_Product ? $product_object : null;
		echo '<div class="options_group show_if_simple flxcm-sub-fields">';
		woocommerce_wp_checkbox(
			array(
				'id'          => '_flxcm_sub',
				'label'       => __( 'Subscription', 'flx-commerce' ),
				'description' => __( 'Sold as a monthly or yearly subscription (Flux Commerce). Renewals arrive as orders.', 'flx-commerce' ),
				'value'       => $p ? $p->get_meta( '_flxcm_sub' ) : '',
			)
		);
		$cur = get_woocommerce_currency_symbol();
		woocommerce_wp_text_input(
			array(
				'id'          => '_flxcm_sub_month',
				/* translators: %s: currency symbol */
				'label'       => sprintf( __( 'Monthly price (%s)', 'flx-commerce' ), $cur ),
				'data_type'   => 'price',
				'desc_tip'    => true,
				'description' => __( 'Empty = no monthly option.', 'flx-commerce' ),
				'value'       => $p ? wc_format_localized_price( (string) $p->get_meta( '_flxcm_sub_month' ) ) : '',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_flxcm_sub_year',
				/* translators: %s: currency symbol */
				'label'       => sprintf( __( 'Yearly price (%s)', 'flx-commerce' ), $cur ),
				'data_type'   => 'price',
				'desc_tip'    => true,
				'description' => __( 'Empty = no yearly option.', 'flx-commerce' ),
				'value'       => $p ? wc_format_localized_price( (string) $p->get_meta( '_flxcm_sub_year' ) ) : '',
			)
		);
		echo '</div>';
		// Abonelik açıkken normal fiyat alanı kafa karıştırmasın.
		echo "<script>jQuery(function($){function t(){var on=$('#_flxcm_sub').is(':checked');$('.flxcm-sub-fields ._flxcm_sub_month_field,.flxcm-sub-fields ._flxcm_sub_year_field').toggle(on);}$('#_flxcm_sub').on('change',t);t();});</script>";
	}

	public static function save( WC_Product $product ) {
		// phpcs:disable WordPress.Security.NonceVerification -- WooCommerce ürün kaydı kendi nonce'unu doğrular.
		$on = ! empty( $_POST['_flxcm_sub'] ) ? 'yes' : 'no';
		$product->update_meta_data( '_flxcm_sub', $on );
		foreach ( FLXCM_Subscriptions::PERIODS as $p ) {
			$raw = isset( $_POST[ '_flxcm_sub_' . $p ] ) ? wc_format_decimal( wp_unslash( $_POST[ '_flxcm_sub_' . $p ] ) ) : '';
			$product->update_meta_data( '_flxcm_sub_' . $p, '' === $raw || (float) $raw <= 0 ? '' : $raw );
		}
		// phpcs:enable
		if ( 'yes' === $on && '' === (string) $product->get_regular_price() ) {
			$prices = self::prices_of( $product );
			if ( $prices ) {
				// WooCommerce fiyatsız ürünü satın alınamaz sayar; ilk dönem fiyatı ürün fiyatı olur.
				$product->set_regular_price( (string) reset( $prices ) );
			}
		}
	}

	public static function price_html( $html, $product ) {
		if ( ! self::is_sub( $product ) ) {
			return $html;
		}
		$parts = array();
		foreach ( self::prices_of( $product ) as $p => $v ) {
			$parts[] = wc_price( wc_get_price_to_display( $product, array( 'price' => $v ) ) ) . ' <span class="flxcm-sub-per">/ ' . esc_html( FLXCM_Subscriptions::period_label( $p, false ) ) . '</span>';
		}
		return implode( ' <span class="flxcm-sub-or">' . esc_html__( 'or', 'flx-commerce' ) . '</span> ', $parts );
	}

	public static function button_text( $text, $product ) {
		return self::is_sub( $product ) ? __( 'Subscribe', 'flx-commerce' ) : $text;
	}

	/** Ürün sayfasında Aylık / Yıllık seçimi. */
	public static function chooser() {
		global $product;
		if ( ! self::is_sub( $product ) ) {
			return;
		}
		$prices = self::prices_of( $product );
		// phpcs:ignore WordPress.Security.NonceVerification
		$sel = isset( $_GET['flxcm_period'] ) && isset( $prices[ sanitize_key( $_GET['flxcm_period'] ) ] ) ? sanitize_key( $_GET['flxcm_period'] ) : (string) key( $prices );
		wp_enqueue_style( 'flxcm-front', FLXCM_URL . 'assets/front.css', array(), FLXCM_VERSION );
		echo '<div class="flxcm-period-pick" role="radiogroup" aria-labelledby="flxcm-period-pick-l"><span class="flxcm-period-pick__legend" id="flxcm-period-pick-l">' . esc_html__( 'Billing period', 'flx-commerce' ) . '</span>';
		$month = $prices['month'] ?? 0;
		foreach ( $prices as $p => $v ) {
			$save = '';
			if ( 'year' === $p && $month > 0 && $v < $month * 12 ) {
				/* translators: %s: percent */
				$save = '<span class="flxcm-period-pick__save">' . esc_html( sprintf( __( 'Save %s%%', 'flx-commerce' ), number_format_i18n( ( 1 - $v / ( $month * 12 ) ) * 100, 0 ) ) ) . '</span>';
			}
			echo '<label class="flxcm-period-pick__opt"><input type="radio" name="flxcm_period" value="' . esc_attr( $p ) . '"' . checked( $sel, $p, false ) . '><span class="flxcm-period-pick__name">' . esc_html( FLXCM_Subscriptions::period_label( $p ) ) . $save . '</span><span class="flxcm-period-pick__price">' . wp_kses_post( wc_price( wc_get_price_to_display( $product, array( 'price' => $v ) ) ) ) . ' / ' . esc_html( FLXCM_Subscriptions::period_label( $p, false ) ) . '</span></label>';
		}
		echo '<p class="flxcm-period-pick__hint">' . esc_html__( 'Renews automatically; we send a reminder before every renewal. You can cancel any time from My account.', 'flx-commerce' ) . '</p></div>';
	}

	private static function requested_period( WC_Product $product ) {
		$prices = self::prices_of( $product );
		$req    = isset( $_REQUEST['flxcm_period'] ) ? sanitize_key( wp_unslash( $_REQUEST['flxcm_period'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return isset( $prices[ $req ] ) ? $req : (string) key( $prices );
	}

	public static function validate( $ok, $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $ok || ! self::is_sub( $product ) || ! WC()->cart ) {
			return $ok;
		}
		// Aynı ürün tek satır: dönem değişirse eskisi çıkar.
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( (int) $item['product_id'] === (int) $product_id && isset( $item['flxcm_sub'] ) ) {
				WC()->cart->remove_cart_item( $key );
			}
		}
		return $ok;
	}

	public static function cart_item_data( $data, $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! self::is_sub( $product ) ) {
			return $data;
		}
		if ( empty( $data['flxcm_sub']['period'] ) ) {
			$data['flxcm_sub'] = array(
				'period' => self::requested_period( $product ),
				'meta'   => isset( $data['flxcm_sub']['meta'] ) && is_array( $data['flxcm_sub']['meta'] ) ? $data['flxcm_sub']['meta'] : array(),
			);
		}
		return $data;
	}

	public static function from_session( $item, $values ) {
		if ( isset( $values['flxcm_sub'] ) ) {
			$item['flxcm_sub'] = $values['flxcm_sub'];
		}
		return $item;
	}

	/** Sepet fiyatı sunucuda, ürünün dönem fiyatından. */
	public static function prices( $cart ) {
		if ( ! $cart instanceof WC_Cart ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item['flxcm_sub']['period'] ) || empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
				continue;
			}
			$prices = self::prices_of( wc_get_product( $item['product_id'] ) );
			$p      = $item['flxcm_sub']['period'];
			if ( isset( $prices[ $p ] ) ) {
				$item['data']->set_price( (string) $prices[ $p ] );
			}
		}
	}

	public static function item_data( $data, $item ) {
		if ( ! empty( $item['flxcm_sub']['period'] ) ) {
			$data[] = array(
				'key'   => __( 'Billing period', 'flx-commerce' ),
				'value' => FLXCM_Subscriptions::period_label( $item['flxcm_sub']['period'] ),
			);
		}
		return $data;
	}

	public static function cart_price( $html, $item ) {
		if ( ! empty( $item['flxcm_sub']['period'] ) ) {
			$html .= ' <span class="flxcm-sub-per">/ ' . esc_html( FLXCM_Subscriptions::period_label( $item['flxcm_sub']['period'], false ) ) . '</span>';
		}
		return $html;
	}

	public static function individually( $yes, $product ) {
		return self::is_sub( $product ) ? true : $yes;
	}

	public static function line_item( $line, $key, $values ) {
		if ( empty( $values['flxcm_sub']['period'] ) ) {
			return;
		}
		$line->add_meta_data( '_flxcm_period', $values['flxcm_sub']['period'], true );
		$line->add_meta_data( 'flxcm_period', $values['flxcm_sub']['period'], true ); // Görünen satır (WooCommerce "_" ile başlayanı göstermez).
		if ( ! empty( $values['flxcm_sub']['meta'] ) ) {
			$line->add_meta_data( '_flxcm_sub_meta', (array) $values['flxcm_sub']['meta'], true );
		}
	}

	/** Sipariş satırında "Ödeme dönemi: Aylık" görünür; diğer iç meta gizli. */
	public static function meta_key( $key, $meta ) {
		return 'flxcm_period' === $meta->key ? __( 'Billing period', 'flx-commerce' ) : $key;
	}

	public static function meta_value( $value, $meta ) {
		return 'flxcm_period' === $meta->key ? FLXCM_Subscriptions::period_label( (string) $meta->value ) : $value;
	}

	public static function hidden_meta( $keys ) {
		$keys[] = '_flxcm_sub_id';
		$keys[] = '_flxcm_sub_meta';
		return $keys;
	}

	public static function cart_has_sub() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item['flxcm_sub']['period'] ) ) {
				return true;
			}
		}
		return false;
	}

	/** Abonelik Hesabım'dan yönetilir: sepette abonelik varsa hesap açmak zorunlu. */
	public static function require_account( $req ) {
		return self::cart_has_sub() ? true : $req;
	}

	public static function enable_account( $on ) {
		return self::cart_has_sub() ? true : $on;
	}

	/**
	 * Sepete abonelik ekler (başka eklentiler: Flux Cloud plan seçimi vb.). $meta abonelik kaydına taşınır.
	 *
	 * @return string|WP_Error Sepet satır anahtarı.
	 */
	public static function add_to_cart( $product_id, $period = '', array $meta = array() ) {
		$product = wc_get_product( $product_id );
		if ( ! self::is_sub( $product ) ) {
			return new WP_Error( 'flxcm_not_sub', __( 'This product is not a subscription.', 'flx-commerce' ) );
		}
		$prices = self::prices_of( $product );
		$period = isset( $prices[ $period ] ) ? $period : (string) key( $prices );
		if ( ! WC()->cart ) {
			wc_load_cart();
		}
		self::validate( true, $product_id );
		$key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), array( 'flxcm_sub' => array( 'period' => $period, 'meta' => $meta ) ) );
		return $key ? $key : new WP_Error( 'flxcm_cart', __( 'The product could not be added to the cart.', 'flx-commerce' ) );
	}
}

/**
 * Sepete abonelik ekler; ardından wc_get_checkout_url()'e yönlendirin.
 *
 * @param int    $product_id Abonelik ürünü.
 * @param string $period     month | year (boş = ilk sunulan).
 * @param array  $meta       Aboneliğe yazılacak ek bilgi (ör. array( 'flx_cloud_account' => 12 )).
 * @return string|WP_Error
 */
function flxcm_add_subscription_to_cart( $product_id, $period = '', array $meta = array() ) {
	return FLXCM_Sub_Product::add_to_cart( (int) $product_id, (string) $period, $meta );
}
