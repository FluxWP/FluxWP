<?php
/**
 * Subscription add-to-cart through the Flux cart bridge, and the free-shipping bar values.
 *
 * Public on purpose: the product page already lets a guest add the subscription. Checkout
 * stays on the Store API. Extra meta is not accepted from GraphQL (the classic helper still can).
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Cart_Service {

	public static function init(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		add_filter( 'flx_gql_cart_item_kind', array( __CLASS__, 'kind' ), 10, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_editable', array( __CLASS__, 'quantity_editable' ), 10, 3 );
	}

	/**
	 * A subscription line is quantity 1 (SEPET.md section 6).
	 *
	 * @param mixed $editable  Current flag.
	 * @param mixed $product   Product, when the store has one.
	 * @param mixed $cart_item Cart item.
	 */
	public static function quantity_editable( $editable, $product = null, $cart_item = null ) {
		if ( is_array( $cart_item ) && ! empty( $cart_item['flxcm_sub']['period'] ) ) {
			return false;
		}
		if ( $product instanceof WC_Product && class_exists( 'FLXCM_Sub_Product' ) && FLXCM_Sub_Product::is_sub( $product ) ) {
			return false;
		}
		return $editable;
	}

	/**
	 * @param mixed                $kind  Current kind.
	 * @param array<string,mixed>|null $wc WooCommerce cart item.
	 * @param mixed                $store Store API item (unused).
	 */
	public static function kind( $kind, $wc = null, $store = null ): string {
		unset( $store );
		if ( is_array( $wc ) && ! empty( $wc['flxcm_sub']['period'] ) ) {
			return 'subscription';
		}
		return is_string( $kind ) && '' !== $kind ? $kind : 'product';
	}

	/**
	 * Prices offered on a simple subscription product.
	 *
	 * @return array{productId:int,name:string,month:array{amount:float,currency:string,formatted:string}|null,year:array{amount:float,currency:string,formatted:string}|null}|WP_Error
	 */
	public static function offer( int $product_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return new WP_Error( 'flxcm_wc', __( 'WooCommerce is required.', 'flx-commerce' ), array( 'status' => 400 ) );
		}
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product || ! FLXCM_Sub_Product::is_sub( $product ) ) {
			return new WP_Error( 'flxcm_missing', __( 'This product is not a subscription.', 'flx-commerce' ), array( 'status' => 404 ) );
		}
		$prices = FLXCM_Sub_Product::prices_of( $product );
		$cur    = $product->get_currency();
		return array(
			'productId' => $product->get_id(),
			'name'      => $product->get_name(),
			'month'     => isset( $prices['month'] ) ? flxcm_gql_money( $prices['month'], $cur ) : null,
			'year'      => isset( $prices['year'] ) ? flxcm_gql_money( $prices['year'], $cur ) : null,
		);
	}

	/**
	 * Adds the subscription through FLXGQL_Cart::add_item().
	 *
	 * @return array{cart:array<string,mixed>|null,itemKey:string}|WP_Error
	 */
	public static function add( int $product_id, string $period ) {
		if ( ! class_exists( 'FLXGQL_Cart' ) ) {
			return new WP_Error( 'flxcm_cart', __( 'The cart is not available.', 'flx-commerce' ), array( 'status' => 400 ) );
		}
		$offer = self::offer( $product_id );
		if ( is_wp_error( $offer ) ) {
			return $offer;
		}
		$period = sanitize_key( $period );
		if ( '' === $period ) {
			$period = null !== $offer['month'] ? 'month' : 'year';
		}
		if ( ( 'month' === $period && null === $offer['month'] ) || ( 'year' === $period && null === $offer['year'] ) || ! in_array( $period, array( 'month', 'year' ), true ) ) {
			return new WP_Error( 'flxcm_period', __( 'That billing period is not offered.', 'flx-commerce' ), array( 'status' => 400 ) );
		}
		self::drop_existing( $product_id );
		$added = FLXGQL_Cart::add_item( $product_id, 1, array(), array( 'flxcm_sub' => array( 'period' => $period, 'meta' => array() ) ) );
		if ( is_wp_error( $added ) ) {
			return $added;
		}
		$cart = isset( $added['cart'] ) && is_array( $added['cart'] ) ? $added['cart'] : null;
		$key  = (string) ( $added['itemKey'] ?? '' );
		$cart = self::clamp_quantity( $cart, $key, $product_id );
		return array(
			'cart'    => $cart,
			'itemKey' => $key,
		);
	}

	/**
	 * One line per subscription product: the classic validate() removes the previous line.
	 * The Store API stand-in does not run that hook, so the GraphQL cart is scanned too.
	 */
	private static function drop_existing( int $product_id ): void {
		if ( class_exists( 'FLXCM_Sub_Product' ) && function_exists( 'WC' ) && WC()->cart instanceof WC_Cart ) {
			FLXCM_Sub_Product::validate( true, $product_id );
		}
		$cart = FLXGQL_Cart::get();
		if ( ! is_array( $cart ) || empty( $cart['items'] ) || ! is_array( $cart['items'] ) ) {
			return;
		}
		foreach ( $cart['items'] as $item ) {
			if ( ! is_array( $item ) || (int) ( $item['productId'] ?? 0 ) !== $product_id ) {
				continue;
			}
			FLXGQL_Cart::remove_item( (string) ( $item['key'] ?? '' ) );
		}
	}

	/**
	 * The stand-in increments quantity when the line key matches. Force 1.
	 *
	 * @param array<string,mixed>|null $cart Cart.
	 * @return array<string,mixed>|null
	 */
	private static function clamp_quantity( ?array $cart, string $key, int $product_id ): ?array {
		if ( ! is_array( $cart ) || empty( $cart['items'] ) || ! is_array( $cart['items'] ) ) {
			return $cart;
		}
		foreach ( $cart['items'] as $item ) {
			if ( ! is_array( $item ) || (int) ( $item['quantity'] ?? 0 ) <= 1 ) {
				continue;
			}
			$same = ( '' !== $key && (string) ( $item['key'] ?? '' ) === $key ) || (int) ( $item['productId'] ?? 0 ) === $product_id;
			if ( ! $same ) {
				continue;
			}
			$fixed = FLXGQL_Cart::update_item( (string) ( $item['key'] ?? '' ), 1 );
			if ( is_array( $fixed ) ) {
				$cart = $fixed;
			}
		}
		return $cart;
	}

	/**
	 * Free-shipping bar for the current cart.
	 *
	 * @return array{enabled:bool,threshold:array{amount:float,currency:string,formatted:string},remaining:array{amount:float,currency:string,formatted:string},reached:bool}
	 */
	public static function free_shipping(): array {
		$min = class_exists( 'FLXCM_Checkout' ) ? (float) FLXCM_Checkout::free_threshold() : 0.0;
		$sub = 0.0;
		if ( function_exists( 'WC' ) && WC()->cart ) {
			$sub = (float) WC()->cart->get_displayed_subtotal() - (float) WC()->cart->get_discount_total();
		}
		$left = max( 0, $min - $sub );
		return array(
			'enabled'   => (bool) flxcm_setting( 'free_ship_bar' ) && $min > 0,
			'threshold' => flxcm_gql_money( $min ),
			'remaining' => flxcm_gql_money( $left ),
			'reached'   => $min > 0 && $sub >= $min,
		);
	}

	/**
	 * Subscription stored on a cart line, or null. Meta is not returned.
	 *
	 * @param array<string,mixed> $source FlxCartItem.
	 * @return array{period:string,amount:array{amount:float,currency:string,formatted:string}|null}|null
	 */
	public static function line( array $source ): ?array {
		if ( ! class_exists( 'FLXGQL_Cart' ) ) {
			return null;
		}
		$wc = FLXGQL_Cart::wc_item( (string) ( $source['key'] ?? '' ) );
		if ( ! is_array( $wc ) || empty( $wc['flxcm_sub']['period'] ) ) {
			return null;
		}
		$period = sanitize_key( (string) $wc['flxcm_sub']['period'] );
		$amount = null;
		if ( function_exists( 'wc_get_product' ) && ! empty( $wc['product_id'] ) ) {
			$product = wc_get_product( (int) $wc['product_id'] );
			if ( $product instanceof WC_Product ) {
				$prices = FLXCM_Sub_Product::prices_of( $product );
				if ( isset( $prices[ $period ] ) ) {
					$amount = flxcm_gql_money( $prices[ $period ], $product->get_currency() );
				}
			}
		}
		return array(
			'period' => $period,
			'amount' => $amount,
		);
	}
}
