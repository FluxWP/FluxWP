<?php
/**
 * Cart service of the GraphQL cart bridge: Store API cart routes in, typed FlxCart shapes out.
 *
 * GraphQL resolvers (cart-schema.php) and package mutations (Flux Donate, Flux Commerce) call this class;
 * nothing here talks to WC()->cart for writes, the Store API does (FLXGQL_Store_Api).
 *
 * Package extension point, for a package that adds its own kind of line (a donation):
 *
 *     $r = FLXGQL_Cart::add_item( $product_id, 1, array(), array( 'flxb_line' => $line ) );
 *     // $r: array{cart: FlxCart, itemKey: string}|WP_Error
 *
 * The cart item data is merged on WooCommerce's own `woocommerce_add_cart_item_data` filter (priority 5)
 * during that one Store API add-item call, so the line is stored exactly like a classic add_to_cart() with
 * cart item data. Lines show their kind with the `flx_gql_cart_item_kind` filter and extra typed fields
 * with flx_gql_field( 'FlxCartItem', … ) reading FLXGQL_Cart::wc_item( $item['key'] ).
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cart service.
 *
 * @phpstan-type FlxMoneyShape array{amount: float, currency: string, formatted: string}
 * @phpstan-type FlxCartMeta array{name: string, value: string}
 * @phpstan-type FlxCartItemShape array{key: string, kind: string, productId: int, type: string, name: string, sku: string, permalink: ?string, imageUrl: ?string, imageAlt: string, quantity: int, quantityLimits: array{minimum: int, maximum: int, multipleOf: int, editable: bool}, soldIndividually: bool, unitPrice: FlxMoneyShape, regularPrice: FlxMoneyShape, subtotal: FlxMoneyShape, total: FlxMoneyShape, tax: FlxMoneyShape, variation: list<FlxCartMeta>, itemData: list<FlxCartMeta>}
 * @phpstan-type FlxCartShape array{token: ?string, nonce: ?string, itemsCount: int, items: list<FlxCartItemShape>, coupons: list<array<string,mixed>>, fees: list<array<string,mixed>>, totals: array<string,mixed>, needsPayment: bool, needsShipping: bool, hasCalculatedShipping: bool, shippingPackages: list<array<string,mixed>>, paymentMethods: list<string>, paymentRequirements: list<string>, notices: list<array{code: string, message: string}>, billingAddress: array<string,?string>, shippingAddress: array<string,?string>, checkout: array{endpoint: string, pageUrl: ?string, cartPageUrl: ?string}}
 */
final class FLXGQL_Cart {

	/** GraphQL address field → Store API key. */
	private const ADDRESS = array(
		'firstName' => 'first_name',
		'lastName'  => 'last_name',
		'company'   => 'company',
		'address1'  => 'address_1',
		'address2'  => 'address_2',
		'city'      => 'city',
		'state'     => 'state',
		'postcode'  => 'postcode',
		'country'   => 'country',
		'email'     => 'email',
		'phone'     => 'phone',
	);

	/**
	 * Cart item data to merge into the next add-to-cart of this product (add_item()).
	 *
	 * @var array{product: int, data: array<string,mixed>}|null
	 */
	private static ?array $pending = null;

	/** Cart item key WooCommerce gave the last add-to-cart. */
	private static string $added_key = '';

	/**
	 * The visitor's cart.
	 *
	 * @return FlxCartShape|WP_Error
	 */
	public static function get() {
		return self::cart_of( FLXGQL_Store_Api::request( 'GET', '/cart' ) );
	}

	/**
	 * Adds a product (or variation) to the cart.
	 *
	 * @param int                                             $product_id     Product or variation ID.
	 * @param int                                             $quantity       Quantity (≥ 1).
	 * @param array<int,array{attribute:string,value:string}> $variation      Variation attributes.
	 * @param array<string,mixed>                             $cart_item_data Extra cart item data (packages; see the class note).
	 * @return array{cart: FlxCartShape, itemKey: string}|WP_Error
	 */
	public static function add_item( int $product_id, int $quantity = 1, array $variation = array(), array $cart_item_data = array() ) {
		if ( $product_id <= 0 ) {
			return self::invalid( __( 'Choose a product.', 'flx-graphql' ), 'productId' );
		}
		if ( $quantity < 1 ) {
			return self::invalid( __( 'The quantity must be at least 1.', 'flx-graphql' ), 'quantity' );
		}
		self::$added_key = '';
		self::$pending   = $cart_item_data ? array(
			'product' => $product_id,
			'data'    => $cart_item_data,
		) : null;
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'merge_item_data' ), 5, 3 );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'remember_key' ), 1 );
		$params = array(
			'id'       => $product_id,
			'quantity' => $quantity,
		);
		if ( $variation ) {
			$params['variation'] = array_values( $variation );
		}
		$res = FLXGQL_Store_Api::request( 'POST', '/cart/add-item', $params );
		remove_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'merge_item_data' ), 5 );
		remove_action( 'woocommerce_add_to_cart', array( __CLASS__, 'remember_key' ), 1 );
		self::$pending = null;
		$cart          = self::cart_of( $res );
		if ( $cart instanceof WP_Error ) {
			return $cart;
		}
		return array(
			'cart'    => $cart,
			'itemKey' => self::$added_key,
		);
	}

	/**
	 * Changes the quantity of a line.
	 *
	 * @param string $key      Cart item key.
	 * @param int    $quantity New quantity (0 removes the line).
	 * @return FlxCartShape|WP_Error
	 */
	public static function update_item( string $key, int $quantity ) {
		if ( $quantity < 0 ) {
			return self::invalid( __( 'The quantity cannot be negative.', 'flx-graphql' ), 'quantity' );
		}
		if ( 0 === $quantity ) {
			return self::remove_item( $key );
		}
		return self::cart_of(
			FLXGQL_Store_Api::request(
				'POST',
				'/cart/update-item',
				array(
					'key'      => self::key( $key ),
					'quantity' => $quantity,
				)
			)
		);
	}

	/**
	 * Removes a line.
	 *
	 * @param string $key Cart item key.
	 * @return FlxCartShape|WP_Error
	 */
	public static function remove_item( string $key ) {
		return self::cart_of( FLXGQL_Store_Api::request( 'POST', '/cart/remove-item', array( 'key' => self::key( $key ) ) ) );
	}

	/**
	 * Empties the cart.
	 *
	 * @return FlxCartShape|WP_Error
	 */
	public static function clear() {
		$res = FLXGQL_Store_Api::request( 'DELETE', '/cart/items' );
		return $res instanceof WP_Error ? $res : self::get();
	}

	/**
	 * Applies a coupon.
	 *
	 * @param string $code Coupon code.
	 * @return FlxCartShape|WP_Error
	 */
	public static function apply_coupon( string $code ) {
		$code = flx_gql_coupon_code( $code );
		if ( '' === $code ) {
			return self::invalid( __( 'Enter a coupon code.', 'flx-graphql' ), 'code' );
		}
		return self::cart_of( FLXGQL_Store_Api::request( 'POST', '/cart/apply-coupon', array( 'code' => $code ) ) );
	}

	/**
	 * Removes a coupon.
	 *
	 * @param string $code Coupon code.
	 * @return FlxCartShape|WP_Error
	 */
	public static function remove_coupon( string $code ) {
		return self::cart_of( FLXGQL_Store_Api::request( 'POST', '/cart/remove-coupon', array( 'code' => flx_gql_coupon_code( $code ) ) ) );
	}

	/**
	 * Selects a shipping rate.
	 *
	 * @param string   $rate_id    Rate ID (e.g. "flat_rate:1").
	 * @param int|null $package_id Shipping package; null = every package.
	 * @return FlxCartShape|WP_Error
	 */
	public static function select_shipping_rate( string $rate_id, ?int $package_id = null ) {
		$params = array( 'rate_id' => sanitize_text_field( $rate_id ) );
		if ( null !== $package_id ) {
			$params['package_id'] = $package_id;
		}
		return self::cart_of( FLXGQL_Store_Api::request( 'POST', '/cart/select-shipping-rate', $params ) );
	}

	/**
	 * Updates the billing and/or shipping address (totals, taxes and shipping rates follow).
	 *
	 * @param array<string,mixed>|null $billing  GraphQL address (camelCase keys); null = unchanged.
	 * @param array<string,mixed>|null $shipping GraphQL address; null = unchanged.
	 * @return FlxCartShape|WP_Error
	 */
	public static function update_customer( ?array $billing, ?array $shipping ) {
		if ( null === $billing && null === $shipping ) {
			return self::get();
		}
		// The Store API validates whole addresses: start from the current ones, change only the given fields.
		$now = FLXGQL_Store_Api::request( 'GET', '/cart' );
		if ( $now instanceof WP_Error ) {
			return $now;
		}
		$params = array();
		if ( null !== $billing ) {
			$params['billing_address'] = array_merge( self::address_raw( flx_gql_arr( $now['billing_address'] ?? array() ), true ), self::address_in( $billing ) );
		}
		if ( null !== $shipping ) {
			$params['shipping_address'] = array_merge( self::address_raw( flx_gql_arr( $now['shipping_address'] ?? array() ), false ), self::address_in( $shipping ) );
		}
		return self::cart_of( FLXGQL_Store_Api::request( 'POST', '/cart/update-customer', $params ) );
	}

	/**
	 * Raw WooCommerce cart item (after a cart route ran in this request), for package fields on FlxCartItem.
	 *
	 * @param string $key Cart item key.
	 * @return array<string,mixed>|null
	 */
	public static function wc_item( string $key ): ?array {
		if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof WC_Cart ) {
			return null;
		}
		$item = WC()->cart->get_cart_item( $key );
		return is_array( $item ) && $item ? flx_gql_arr( $item ) : null;
	}

	/**
	 * `woocommerce_add_cart_item_data` during add_item(): merges the package's cart item data.
	 *
	 * @param mixed $data       Cart item data.
	 * @param mixed $product_id Product ID.
	 * @param mixed $variation  Variation ID.
	 * @return mixed
	 */
	public static function merge_item_data( $data, $product_id = 0, $variation = 0 ) {
		$p = self::$pending;
		if ( null === $p ) {
			return $data;
		}
		$ids = array( flx_gql_int( $product_id ), flx_gql_int( $variation ) );
		if ( ! in_array( $p['product'], $ids, true ) ) {
			return $data;
		}
		return array_merge( flx_gql_arr( $data ), $p['data'] );
	}

	/**
	 * `woocommerce_add_to_cart`: remembers the new line's key.
	 *
	 * @param mixed $key Cart item key.
	 */
	public static function remember_key( $key ): void {
		self::$added_key = flx_gql_str( $key );
	}

	/**
	 * Store API cart answer → FlxCart.
	 *
	 * @param array<string,mixed>|WP_Error $c Store API cart.
	 * @return FlxCartShape|WP_Error
	 */
	public static function cart_of( $c ) {
		if ( $c instanceof WP_Error ) {
			return $c;
		}
		$cur   = self::currency( flx_gql_arr( $c['totals'] ?? array() ) );
		$items = array();
		foreach ( flx_gql_arr( $c['items'] ?? array() ) as $raw ) {
			$items[] = self::item( flx_gql_arr( $raw ) );
		}
		$coupons = array();
		foreach ( flx_gql_arr( $c['coupons'] ?? array() ) as $raw ) {
			$raw       = flx_gql_arr( $raw );
			$t         = flx_gql_arr( $raw['totals'] ?? array() );
			$tc        = self::currency( $t, $cur );
			$coupons[] = array(
				'code'         => flx_gql_str( $raw['code'] ?? '' ),
				'discountType' => flx_gql_str( $raw['discount_type'] ?? '' ),
				'discount'     => self::money( $t['total_discount'] ?? 0, $tc ),
				'discountTax'  => self::money( $t['total_discount_tax'] ?? 0, $tc ),
			);
		}
		$fees = array();
		foreach ( flx_gql_arr( $c['fees'] ?? array() ) as $raw ) {
			$raw    = flx_gql_arr( $raw );
			$t      = flx_gql_arr( $raw['totals'] ?? array() );
			$tc     = self::currency( $t, $cur );
			$fees[] = array(
				'key'   => flx_gql_str( $raw['key'] ?? '' ),
				'name'  => self::text( $raw['name'] ?? '' ),
				'total' => self::money( $t['total'] ?? 0, $tc ),
				'tax'   => self::money( $t['total_tax'] ?? 0, $tc ),
			);
		}
		$packages = array();
		foreach ( flx_gql_arr( $c['shipping_rates'] ?? array() ) as $raw ) {
			$raw   = flx_gql_arr( $raw );
			$rates = array();
			foreach ( flx_gql_arr( $raw['shipping_rates'] ?? array() ) as $r ) {
				$r       = flx_gql_arr( $r );
				$rates[] = array(
					'rateId'       => flx_gql_str( $r['rate_id'] ?? '' ),
					'methodId'     => flx_gql_str( $r['method_id'] ?? '' ),
					'name'         => self::text( $r['name'] ?? '' ),
					'description'  => self::text( $r['description'] ?? '' ),
					'deliveryTime' => self::text( $r['delivery_time'] ?? '' ),
					'price'        => self::money( $r['price'] ?? 0, self::currency( $r, $cur ) ),
					'selected'     => ! empty( $r['selected'] ),
				);
			}
			$packages[] = array(
				'packageId' => flx_gql_int( $raw['package_id'] ?? 0 ),
				'name'      => self::text( $raw['name'] ?? '' ),
				'rates'     => $rates,
			);
		}
		$t      = flx_gql_arr( $c['totals'] ?? array() );
		$lines  = array();
		foreach ( flx_gql_arr( $t['tax_lines'] ?? array() ) as $raw ) {
			$raw     = flx_gql_arr( $raw );
			$lines[] = array(
				'name'  => self::text( $raw['name'] ?? '' ),
				'rate'  => flx_gql_str( $raw['rate'] ?? '' ),
				'price' => self::money( $raw['price'] ?? 0, $cur ),
			);
		}
		$notices = array();
		foreach ( flx_gql_arr( $c['errors'] ?? array() ) as $raw ) {
			$raw       = flx_gql_arr( $raw );
			$notices[] = array(
				'code'    => flx_gql_str( $raw['code'] ?? '' ),
				'message' => self::text( $raw['message'] ?? '' ),
			);
		}
		$token = FLXGQL_Store_Api::token();
		$nonce = FLXGQL_Store_Api::nonce();
		return array(
			'token'                 => '' !== $token ? $token : null,
			'nonce'                 => '' !== $nonce ? $nonce : null,
			'itemsCount'            => flx_gql_int( $c['items_count'] ?? 0 ),
			'items'                 => $items,
			'coupons'               => $coupons,
			'fees'                  => $fees,
			'totals'                => array(
				'currency'      => $cur['code'],
				'itemsSubtotal' => self::money( $t['total_items'] ?? 0, $cur ),
				'itemsTax'      => self::money( $t['total_items_tax'] ?? 0, $cur ),
				'fees'          => self::money( $t['total_fees'] ?? 0, $cur ),
				'discount'      => self::money( $t['total_discount'] ?? 0, $cur ),
				'shipping'      => isset( $t['total_shipping'] ) ? self::money( $t['total_shipping'], $cur ) : null,
				'tax'           => self::money( $t['total_tax'] ?? 0, $cur ),
				'total'         => self::money( $t['total_price'] ?? 0, $cur ),
				'taxLines'      => $lines,
			),
			'needsPayment'          => ! empty( $c['needs_payment'] ),
			'needsShipping'         => ! empty( $c['needs_shipping'] ),
			'hasCalculatedShipping' => ! empty( $c['has_calculated_shipping'] ),
			'shippingPackages'      => $packages,
			'paymentMethods'        => self::strings( $c['payment_methods'] ?? array() ),
			'paymentRequirements'   => self::strings( $c['payment_requirements'] ?? array() ),
			'notices'               => $notices,
			'billingAddress'        => self::address_out( flx_gql_arr( $c['billing_address'] ?? array() ), true ),
			'shippingAddress'       => self::address_out( flx_gql_arr( $c['shipping_address'] ?? array() ), false ),
			'checkout'              => array(
				'endpoint'    => rest_url( ltrim( FLXGQL_Store_Api::NS, '/' ) . '/checkout' ),
				'pageUrl'     => function_exists( 'wc_get_checkout_url' ) ? flx_gql_str( wc_get_checkout_url() ) : null,
				'cartPageUrl' => function_exists( 'wc_get_cart_url' ) ? flx_gql_str( wc_get_cart_url() ) : null,
			),
		);
	}

	/**
	 * Store API cart item → FlxCartItem.
	 *
	 * @param array<string,mixed> $raw Store API item.
	 * @return FlxCartItemShape
	 */
	private static function item( array $raw ): array {
		$prices = flx_gql_arr( $raw['prices'] ?? array() );
		$totals = flx_gql_arr( $raw['totals'] ?? array() );
		$pc     = self::currency( $prices );
		$tc     = self::currency( $totals, $pc );
		$limits = flx_gql_arr( $raw['quantity_limits'] ?? array() );
		$image  = flx_gql_arr( flx_gql_arr( $raw['images'] ?? array() )[0] ?? array() );
		$var    = array();
		foreach ( flx_gql_arr( $raw['variation'] ?? array() ) as $v ) {
			$v     = flx_gql_arr( $v );
			$var[] = array(
				'name'  => self::text( $v['attribute'] ?? '' ),
				'value' => self::text( $v['value'] ?? '' ),
			);
		}
		$data = array();
		foreach ( flx_gql_arr( $raw['item_data'] ?? array() ) as $d ) {
			$d      = flx_gql_arr( $d );
			$data[] = array(
				'name'  => self::text( $d['name'] ?? ( $d['key'] ?? '' ) ),
				'value' => self::text( $d['display'] ?? ( $d['value'] ?? '' ) ),
			);
		}
		$key = flx_gql_str( $raw['key'] ?? '' );
		/**
		 * Kind of a cart line ("product" by default). A package that adds its own lines names them, e.g.
		 * Flux Donate returns "donation" for lines that carry its cart item data.
		 *
		 * @since 0.3.0
		 * @param string                   $kind  Kind.
		 * @param array<string,mixed>|null $wc    WooCommerce cart item (FLXGQL_Cart::wc_item()).
		 * @param array<string,mixed>      $store Store API cart item.
		 */
		$kind = sanitize_key( flx_gql_str( apply_filters( 'flx_gql_cart_item_kind', 'product', self::wc_item( $key ), $raw ) ) );
		$link = flx_gql_str( $raw['permalink'] ?? '' );
		$src  = flx_gql_str( $image['src'] ?? '' );
		return array(
			'key'              => $key,
			'kind'             => '' !== $kind ? $kind : 'product',
			'productId'        => flx_gql_int( $raw['id'] ?? 0 ),
			'type'             => flx_gql_str( $raw['type'] ?? 'simple' ),
			'name'             => self::text( $raw['name'] ?? '' ),
			'sku'              => flx_gql_str( $raw['sku'] ?? '' ),
			'permalink'        => '' !== $link ? $link : null,
			'imageUrl'         => '' !== $src ? $src : null,
			'imageAlt'         => self::text( $image['alt'] ?? '' ),
			'quantity'         => flx_gql_int( $raw['quantity'] ?? 0 ),
			'quantityLimits'   => array(
				'minimum'    => flx_gql_int( $limits['minimum'] ?? 1, 1 ),
				'maximum'    => flx_gql_int( $limits['maximum'] ?? 9999, 9999 ),
				'multipleOf' => max( 1, flx_gql_int( $limits['multiple_of'] ?? 1, 1 ) ),
				'editable'   => ! array_key_exists( 'editable', $limits ) || ! empty( $limits['editable'] ),
			),
			'soldIndividually' => ! empty( $raw['sold_individually'] ),
			'unitPrice'        => self::money( $prices['price'] ?? 0, $pc ),
			'regularPrice'     => self::money( $prices['regular_price'] ?? ( $prices['price'] ?? 0 ), $pc ),
			'subtotal'         => self::money( $totals['line_subtotal'] ?? 0, $tc ),
			'total'            => self::money( $totals['line_total'] ?? 0, $tc ),
			'tax'              => self::money( $totals['line_total_tax'] ?? 0, $tc ),
			'variation'        => $var,
			'itemData'         => $data,
		);
	}

	/**
	 * Currency of a Store API price block.
	 *
	 * @param array<string,mixed>                   $block    Block with currency_code / currency_minor_unit.
	 * @param array{code:string,minor:int}|null $fallback Parent currency.
	 * @return array{code:string,minor:int}
	 */
	private static function currency( array $block, ?array $fallback = null ): array {
		$code = strtoupper( flx_gql_str( $block['currency_code'] ?? '' ) );
		if ( '' === $code ) {
			return $fallback ?? array(
				'code'  => function_exists( 'get_woocommerce_currency' ) ? flx_gql_str( get_woocommerce_currency(), 'TRY' ) : 'TRY',
				'minor' => function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2,
			);
		}
		return array(
			'code'  => $code,
			'minor' => max( 0, min( 6, flx_gql_int( $block['currency_minor_unit'] ?? 2, 2 ) ) ),
		);
	}

	/**
	 * Store API amount (string in minor units) → FlxMoney.
	 *
	 * @param mixed                        $minor Amount in minor units.
	 * @param array{code:string,minor:int} $cur   Currency.
	 * @return FlxMoneyShape
	 */
	private static function money( $minor, array $cur ): array {
		$v = is_numeric( $minor ) ? (float) $minor : 0.0;
		return flx_gql_money( $v / ( 10 ** $cur['minor'] ), $cur['code'] );
	}

	/**
	 * Plain text from a Store API string (names may carry HTML entities or tags).
	 *
	 * @param mixed $v Value.
	 */
	private static function text( $v ): string {
		return trim( html_entity_decode( wp_strip_all_tags( flx_gql_str( $v ) ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * List of strings.
	 *
	 * @param mixed $v Value.
	 * @return list<string>
	 */
	private static function strings( $v ): array {
		return array_values( array_filter( array_map( 'flx_gql_str', flx_gql_arr( $v ) ), static fn( string $s ): bool => '' !== $s ) );
	}

	/**
	 * Store API address as the Store API accepts it back (every key, strings).
	 *
	 * @param array<string,mixed> $a       Store API address.
	 * @param bool                $billing Billing (has e-mail).
	 * @return array<string,string>
	 */
	private static function address_raw( array $a, bool $billing ): array {
		$out = array();
		foreach ( self::ADDRESS as $gql => $store ) {
			if ( 'email' === $gql && ! $billing ) {
				continue;
			}
			$out[ $store ] = flx_gql_str( $a[ $store ] ?? '' );
		}
		return $out;
	}

	/**
	 * GraphQL address → Store API address (only the keys given).
	 *
	 * @param array<string,mixed> $in Address.
	 * @return array<string,string>
	 */
	private static function address_in( array $in ): array {
		$out = array();
		foreach ( self::ADDRESS as $gql => $store ) {
			if ( isset( $in[ $gql ] ) ) {
				$out[ $store ] = sanitize_text_field( flx_gql_str( $in[ $gql ] ) );
			}
		}
		return $out;
	}

	/**
	 * Store API address → FlxCartAddress.
	 *
	 * @param array<string,mixed> $a       Address.
	 * @param bool                $billing Billing (has e-mail).
	 * @return array<string,?string>
	 */
	private static function address_out( array $a, bool $billing ): array {
		$out = array();
		foreach ( self::ADDRESS as $gql => $store ) {
			$out[ $gql ] = flx_gql_str( $a[ $store ] ?? '' );
		}
		if ( ! $billing ) {
			$out['email'] = null;
		}
		return $out;
	}

	/**
	 * Cart item key from input.
	 *
	 * @param string $key Key.
	 */
	private static function key( string $key ): string {
		return (string) preg_replace( '/[^a-f0-9]/', '', strtolower( $key ) );
	}

	/**
	 * INVALID_INPUT error.
	 *
	 * @param string $message Message.
	 * @param string $field   Input field.
	 */
	private static function invalid( string $message, string $field ): WP_Error {
		return new WP_Error(
			'flx_cart_input',
			$message,
			array(
				'graphql_code' => 'INVALID_INPUT',
				'field'        => $field,
			)
		);
	}
}

/**
 * Coupon code as WooCommerce stores it (wc_format_coupon_code when WooCommerce is loaded).
 *
 * @param string $code Code.
 */
function flx_gql_coupon_code( string $code ): string {
	return function_exists( 'wc_format_coupon_code' ) ? flx_gql_str( wc_format_coupon_code( $code ) ) : strtolower( trim( sanitize_text_field( $code ) ) );
}
