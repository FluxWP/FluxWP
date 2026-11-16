<?php
/**
 * GraphQL schema of the cart bridge: flxCart + flxCart* mutations over the WooCommerce Store API.
 *
 * The types are registered on every site (also without WooCommerce) so the schema snapshot and the
 * generated client types do not depend on what a site has installed; without WooCommerce the fields
 * answer NOT_FOUND ("The shop is not available on this site.").
 *
 * cap 'public': the cart belongs to whoever holds the session (Cart-Token or WooCommerce session
 * cookie), signed in or not, exactly like the Store API. Nothing here reads another person's cart.
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the cart types, query and mutations.
 */
function flx_gql_register_cart_schema(): void {

	flx_gql_type(
		'FlxCartMeta',
		array(
			'description' => __( 'A name and value shown under a cart line (variation attribute or line detail).', 'flx-graphql' ),
			'fields'      => array(
				'name'  => array(
					'type'        => 'String!',
					'description' => __( 'Label.', 'flx-graphql' ),
				),
				'value' => array(
					'type'        => 'String!',
					'description' => __( 'Value as plain text.', 'flx-graphql' ),
				),
			),
		)
	);
	flx_gql_type(
		'FlxCartQuantityLimits',
		array(
			'description' => __( 'Quantities a cart line accepts.', 'flx-graphql' ),
			'fields'      => array(
				'minimum'    => array(
					'type'        => 'Int!',
					'description' => __( 'Lowest quantity.', 'flx-graphql' ),
				),
				'maximum'    => array(
					'type'        => 'Int!',
					'description' => __( 'Highest quantity.', 'flx-graphql' ),
				),
				'multipleOf' => array(
					'type'        => 'Int!',
					'description' => __( 'The quantity is a multiple of this.', 'flx-graphql' ),
				),
				'editable'   => array(
					'type'        => 'Boolean!',
					'description' => __( 'Whether the shopper may change the quantity (false for fixed lines such as a donation).', 'flx-graphql' ),
				),
			),
		)
	);
	flx_gql_type(
		'FlxCartItem',
		array(
			'description' => __( 'A line in the cart.', 'flx-graphql' ),
			'fields'      => array(
				'key'              => array(
					'type'        => 'String!',
					'description' => __( 'Cart item key; flxCartItemUpdate and flxCartItemRemove take it.', 'flx-graphql' ),
				),
				'kind'             => array(
					'type'        => 'String!',
					'description' => __( 'What the line is: "product", or a name a package gives its own lines (Flux Donate: "donation"). Filter flx_gql_cart_item_kind.', 'flx-graphql' ),
				),
				'productId'        => array(
					'type'        => 'Int!',
					'description' => __( 'WooCommerce product (or variation) database ID.', 'flx-graphql' ),
				),
				'type'             => array(
					'type'        => 'String!',
					'description' => __( 'WooCommerce product type (simple, variation …).', 'flx-graphql' ),
				),
				'name'             => array(
					'type'        => 'String!',
					'description' => __( 'Product name.', 'flx-graphql' ),
				),
				'sku'              => array(
					'type'        => 'String!',
					'description' => __( 'SKU; empty when the product has none.', 'flx-graphql' ),
				),
				'permalink'        => array(
					'type'        => 'String',
					'description' => __( 'Product page; null for products without a page.', 'flx-graphql' ),
				),
				'imageUrl'         => array(
					'type'        => 'String',
					'description' => __( 'First product image; null without an image.', 'flx-graphql' ),
				),
				'imageAlt'         => array(
					'type'        => 'String!',
					'description' => __( 'Alternative text of the image.', 'flx-graphql' ),
				),
				'quantity'         => array(
					'type'        => 'Int!',
					'description' => __( 'Quantity.', 'flx-graphql' ),
				),
				'quantityLimits'   => array(
					'type'        => 'FlxCartQuantityLimits!',
					'description' => __( 'Quantities this line accepts.', 'flx-graphql' ),
				),
				'soldIndividually' => array(
					'type'        => 'Boolean!',
					'description' => __( 'Only one of this product per order.', 'flx-graphql' ),
				),
				'unitPrice'        => array(
					'type'        => 'FlxMoney!',
					'description' => __( 'Price of one unit.', 'flx-graphql' ),
				),
				'regularPrice'     => array(
					'type'        => 'FlxMoney!',
					'description' => __( 'Regular price of one unit (before a sale).', 'flx-graphql' ),
				),
				'subtotal'         => array(
					'type'        => 'FlxMoney!',
					'description' => __( 'Line total before coupons.', 'flx-graphql' ),
				),
				'total'            => array(
					'type'        => 'FlxMoney!',
					'description' => __( 'Line total after coupons, without tax.', 'flx-graphql' ),
				),
				'tax'              => array(
					'type'        => 'FlxMoney!',
					'description' => __( 'Tax of the line.', 'flx-graphql' ),
				),
				'variation'        => array(
					'type'        => '[FlxCartMeta!]!',
					'description' => __( 'Chosen variation attributes.', 'flx-graphql' ),
				),
				'itemData'         => array(
					'type'        => '[FlxCartMeta!]!',
					'description' => __( 'Line details other plugins add (e.g. a donation\'s names and certificate).', 'flx-graphql' ),
				),
			),
		)
	);
	flx_gql_type(
		'FlxCartCoupon',
		array(
			'description' => __( 'A coupon applied to the cart.', 'flx-graphql' ),
			'fields'      => array(
				'code'         => array(
					'type'        => 'String!',
					'description' => __( 'Coupon code.', 'flx-graphql' ),
				),
				'discountType' => array(
					'type'        => 'String!',
					'description' => __( 'WooCommerce discount type (percent, fixed_cart …).', 'flx-graphql' ),
				),
				'discount'     => array(
					'type'        => 'FlxMoney!',
					'description' => __( 'Discount without tax.', 'flx-graphql' ),
				),
				'discountTax'  => array(
					'type'        => 'FlxMoney!',
					'description' => __( 'Tax part of the discount.', 'flx-graphql' ),
				),
			),
		)
	);
	flx_gql_type(
		'FlxCartFee',
		array(
			'description' => __( 'A fee added to the cart (e.g. a payment fee).', 'flx-graphql' ),
			'fields'      => array(
				'key'   => array(
					'type'        => 'String!',
					'description' => __( 'Fee key.', 'flx-graphql' ),
				),
				'name'  => array(
					'type'        => 'String!',
					'description' => __( 'Fee name.', 'flx-graphql' ),
				),
				'total' => array(
					'type'        => 'FlxMoney!',
					'description' => __( 'Fee without tax.', 'flx-graphql' ),
				),
				'tax'   => array(
					'type'        => 'FlxMoney!',
					'description' => __( 'Tax of the fee.', 'flx-graphql' ),
				),
			),
		)
	);
	flx_gql_type(
		'FlxCartTaxLine',
		array(
			'description' => __( 'One tax rate in the cart totals.', 'flx-graphql' ),
			'fields'      => array(
				'name'  => array(
					'type'        => 'String!',
					'description' => __( 'Tax name.', 'flx-graphql' ),
				),
				'rate'  => array(
					'type'        => 'String!',
					'description' => __( 'Rate as WooCommerce shows it.', 'flx-graphql' ),
				),
				'price' => array(
					'type'        => 'FlxMoney!',
					'description' => __( 'Amount.', 'flx-graphql' ),
				),
			),
		)
	);
	flx_gql_type(
		'FlxCartTotals',
		array(
			'description' => __( 'Cart totals, as WooCommerce calculated them.', 'flx-graphql' ),
			'fields'      => array(
				'currency'      => array(
					'type'        => 'String!',
					'description' => __( 'ISO 4217 currency of the cart.', 'flx-graphql' ),
				),
				'itemsSubtotal' => array(
					'type'        => 'FlxMoney!',
					'description' => __( 'Lines before coupons, without tax.', 'flx-graphql' ),
				),
				'itemsTax'      => array(
					'type'        => 'FlxMoney!',
					'description' => __( 'Tax of the lines.', 'flx-graphql' ),
				),
				'fees'          => array(
					'type'        => 'FlxMoney!',
					'description' => __( 'Fees without tax.', 'flx-graphql' ),
				),
				'discount'      => array(
					'type'        => 'FlxMoney!',
					'description' => __( 'Coupon discounts without tax.', 'flx-graphql' ),
				),
				'shipping'      => array(
					'type'        => 'FlxMoney',
					'description' => __( 'Shipping without tax; null until shipping is calculated.', 'flx-graphql' ),
				),
				'tax'           => array(
					'type'        => 'FlxMoney!',
					'description' => __( 'All taxes.', 'flx-graphql' ),
				),
				'total'         => array(
					'type'        => 'FlxMoney!',
					'description' => __( 'Amount to pay.', 'flx-graphql' ),
				),
				'taxLines'      => array(
					'type'        => '[FlxCartTaxLine!]!',
					'description' => __( 'Taxes by rate.', 'flx-graphql' ),
				),
			),
		)
	);
	flx_gql_type(
		'FlxCartShippingRate',
		array(
			'description' => __( 'A shipping option.', 'flx-graphql' ),
			'fields'      => array(
				'rateId'       => array(
					'type'        => 'String!',
					'description' => __( 'Rate ID for flxCartShippingRateSelect.', 'flx-graphql' ),
				),
				'methodId'     => array(
					'type'        => 'String!',
					'description' => __( 'Shipping method (flat_rate, free_shipping …).', 'flx-graphql' ),
				),
				'name'         => array(
					'type'        => 'String!',
					'description' => __( 'Name.', 'flx-graphql' ),
				),
				'description'  => array(
					'type'        => 'String!',
					'description' => __( 'Description; may be empty.', 'flx-graphql' ),
				),
				'deliveryTime' => array(
					'type'        => 'String!',
					'description' => __( 'Delivery time; may be empty.', 'flx-graphql' ),
				),
				'price'        => array(
					'type'        => 'FlxMoney!',
					'description' => __( 'Price without tax.', 'flx-graphql' ),
				),
				'selected'     => array(
					'type'        => 'Boolean!',
					'description' => __( 'Whether this option is chosen.', 'flx-graphql' ),
				),
			),
		)
	);
	flx_gql_type(
		'FlxCartShippingPackage',
		array(
			'description' => __( 'A group of lines shipped together and its shipping options.', 'flx-graphql' ),
			'fields'      => array(
				'packageId' => array(
					'type'        => 'Int!',
					'description' => __( 'Package ID.', 'flx-graphql' ),
				),
				'name'      => array(
					'type'        => 'String!',
					'description' => __( 'Package name.', 'flx-graphql' ),
				),
				'rates'     => array(
					'type'        => '[FlxCartShippingRate!]!',
					'description' => __( 'Shipping options.', 'flx-graphql' ),
				),
			),
		)
	);
	$address = array();
	foreach ( flx_gql_cart_address_fields() as $name => $label ) {
		$address[ $name ] = array(
			'type'        => 'email' === $name ? 'String' : 'String!',
			'description' => $label,
		);
	}
	$address['email']['description'] = __( 'E-mail; null on the shipping address.', 'flx-graphql' );
	flx_gql_type(
		'FlxCartAddress',
		array(
			'description' => __( 'A billing or shipping address of the cart.', 'flx-graphql' ),
			'fields'      => $address,
		)
	);
	flx_gql_type(
		'FlxCartNotice',
		array(
			'description' => __( 'A problem with the cart the shopper has to fix before paying (e.g. a product went out of stock).', 'flx-graphql' ),
			'fields'      => array(
				'code'    => array(
					'type'        => 'String!',
					'description' => __( 'WooCommerce error code.', 'flx-graphql' ),
				),
				'message' => array(
					'type'        => 'String!',
					'description' => __( 'Message in the shopper\'s language.', 'flx-graphql' ),
				),
			),
		)
	);
	flx_gql_type(
		'FlxCartCheckout',
		array(
			'description' => __( 'Where the payment step continues. Payment stays on the WooCommerce Store API checkout; card data never goes through GraphQL.', 'flx-graphql' ),
			'fields'      => array(
				'endpoint'    => array(
					'type'        => 'String!',
					'description' => __( 'Store API checkout URL (POST with the same Cart-Token, billing address and payment method).', 'flx-graphql' ),
				),
				'pageUrl'     => array(
					'type'        => 'String',
					'description' => __( 'The site\'s checkout page; null when WooCommerce is not loaded.', 'flx-graphql' ),
				),
				'cartPageUrl' => array(
					'type'        => 'String',
					'description' => __( 'The site\'s cart page; null when WooCommerce is not loaded.', 'flx-graphql' ),
				),
			),
		)
	);
	flx_gql_type(
		'FlxCart',
		array(
			'description' => __( 'The shopper\'s WooCommerce cart (Store API).', 'flx-graphql' ),
			'fields'      => array(
				'token'                 => array(
					'type'        => 'String',
					'description' => __( 'Cart token. Send it back as the Cart-Token header (headless front end, mobile); it is also in the Cart-Token response header. Null when WooCommerce gave none.', 'flx-graphql' ),
				),
				'nonce'                 => array(
					'type'        => 'String',
					'description' => __( 'Store API nonce for the same-site cookie session (Nonce header). Null when WooCommerce gave none.', 'flx-graphql' ),
				),
				'itemsCount'            => array(
					'type'        => 'Int!',
					'description' => __( 'Number of units in the cart (quantities added up).', 'flx-graphql' ),
				),
				'items'                 => array(
					'type'        => '[FlxCartItem!]!',
					'description' => __( 'Lines.', 'flx-graphql' ),
				),
				'coupons'               => array(
					'type'        => '[FlxCartCoupon!]!',
					'description' => __( 'Applied coupons.', 'flx-graphql' ),
				),
				'fees'                  => array(
					'type'        => '[FlxCartFee!]!',
					'description' => __( 'Fees.', 'flx-graphql' ),
				),
				'totals'                => array(
					'type'        => 'FlxCartTotals!',
					'description' => __( 'Totals.', 'flx-graphql' ),
				),
				'needsPayment'          => array(
					'type'        => 'Boolean!',
					'description' => __( 'Whether the order needs a payment (false for a free cart).', 'flx-graphql' ),
				),
				'needsShipping'         => array(
					'type'        => 'Boolean!',
					'description' => __( 'Whether the order is shipped (false for donations and virtual products).', 'flx-graphql' ),
				),
				'hasCalculatedShipping' => array(
					'type'        => 'Boolean!',
					'description' => __( 'Whether shipping was calculated for the current address.', 'flx-graphql' ),
				),
				'shippingPackages'      => array(
					'type'        => '[FlxCartShippingPackage!]!',
					'description' => __( 'Shipping packages and their options.', 'flx-graphql' ),
				),
				'paymentMethods'        => array(
					'type'        => '[String!]!',
					'description' => __( 'IDs of the payment gateways available for this cart (payment_method of the checkout).', 'flx-graphql' ),
				),
				'paymentRequirements'   => array(
					'type'        => '[String!]!',
					'description' => __( 'Features a gateway must support for this cart (e.g. products, subscriptions).', 'flx-graphql' ),
				),
				'notices'               => array(
					'type'        => '[FlxCartNotice!]!',
					'description' => __( 'Problems to fix before paying.', 'flx-graphql' ),
				),
				'billingAddress'        => array(
					'type'        => 'FlxCartAddress!',
					'description' => __( 'Billing address.', 'flx-graphql' ),
				),
				'shippingAddress'       => array(
					'type'        => 'FlxCartAddress!',
					'description' => __( 'Shipping address.', 'flx-graphql' ),
				),
				'checkout'              => array(
					'type'        => 'FlxCartCheckout!',
					'description' => __( 'Where the payment step continues.', 'flx-graphql' ),
				),
			),
		)
	);

	flx_gql_input(
		'FlxCartVariationInput',
		array(
			'description' => __( 'A variation attribute.', 'flx-graphql' ),
			'fields'      => array(
				'attribute' => array(
					'type'        => 'String!',
					'description' => __( 'Attribute name or taxonomy (e.g. pa_color).', 'flx-graphql' ),
				),
				'value'     => array(
					'type'        => 'String!',
					'description' => __( 'Chosen value.', 'flx-graphql' ),
				),
			),
		)
	);
	$address_in = array();
	foreach ( flx_gql_cart_address_fields() as $name => $label ) {
		$address_in[ $name ] = array(
			'type'        => 'String',
			'description' => $label,
		);
	}
	flx_gql_input(
		'FlxCartAddressInput',
		array(
			'description' => __( 'Address fields to change; fields left out keep their value.', 'flx-graphql' ),
			'fields'      => $address_in,
		)
	);

	flx_gql_query(
		'flxCart',
		array(
			'type'        => 'FlxCart!',
			'cap'         => 'public',
			'description' => __( 'The shopper\'s cart (Cart-Token session or the WooCommerce session cookie). Never cached.', 'flx-graphql' ),
			'resolve'     => static fn() => FLXGQL_Cart::get(),
		)
	);

	$cart_out = array(
		'cart' => array(
			'type'        => 'FlxCart',
			'description' => __( 'The cart after the change; null when the change failed.', 'flx-graphql' ),
		),
	);

	flx_gql_mutation(
		'flxCartItemAdd',
		array(
			'cap'         => 'public',
			'description' => __( 'Adds a product to the cart (Store API cart/add-item).', 'flx-graphql' ),
			'input'       => array(
				'productId' => array(
					'type'        => 'Int!',
					'description' => __( 'Product or variation database ID.', 'flx-graphql' ),
				),
				'quantity'  => array(
					'type'        => 'Int',
					'description' => __( 'Quantity; default 1.', 'flx-graphql' ),
				),
				'variation' => array(
					'type'        => '[FlxCartVariationInput!]',
					'description' => __( 'Variation attributes for a variable product.', 'flx-graphql' ),
				),
			),
			'output'      => $cart_out + array(
				'itemKey' => array(
					'type'        => 'String',
					'description' => __( 'Key of the line that was added or increased; null when the change failed.', 'flx-graphql' ),
				),
			),
			'resolve'     => static function ( array $input ) {
				$variation = array();
				foreach ( flx_gql_arr( $input['variation'] ?? array() ) as $v ) {
					$v           = flx_gql_arr( $v );
					$variation[] = array(
						'attribute' => sanitize_text_field( flx_gql_str( $v['attribute'] ?? '' ) ),
						'value'     => sanitize_text_field( flx_gql_str( $v['value'] ?? '' ) ),
					);
				}
				return FLXGQL_Cart::add_item( flx_gql_int( $input['productId'] ?? 0 ), flx_gql_int( $input['quantity'] ?? 1, 1 ), $variation );
			},
		)
	);
	flx_gql_mutation(
		'flxCartItemUpdate',
		array(
			'cap'         => 'public',
			'description' => __( 'Changes the quantity of a cart line; 0 removes it.', 'flx-graphql' ),
			'input'       => array(
				'key'      => array(
					'type'        => 'String!',
					'description' => __( 'Cart item key.', 'flx-graphql' ),
				),
				'quantity' => array(
					'type'        => 'Int!',
					'description' => __( 'New quantity.', 'flx-graphql' ),
				),
			),
			'output'      => $cart_out,
			'resolve'     => static fn( array $input ) => flx_gql_cart_payload( FLXGQL_Cart::update_item( flx_gql_str( $input['key'] ?? '' ), flx_gql_int( $input['quantity'] ?? 0 ) ) ),
		)
	);
	flx_gql_mutation(
		'flxCartItemRemove',
		array(
			'cap'         => 'public',
			'description' => __( 'Removes a cart line.', 'flx-graphql' ),
			'input'       => array(
				'key' => array(
					'type'        => 'String!',
					'description' => __( 'Cart item key.', 'flx-graphql' ),
				),
			),
			'output'      => $cart_out,
			'resolve'     => static fn( array $input ) => flx_gql_cart_payload( FLXGQL_Cart::remove_item( flx_gql_str( $input['key'] ?? '' ) ) ),
		)
	);
	flx_gql_mutation(
		'flxCartClear',
		array(
			'cap'         => 'public',
			'description' => __( 'Empties the cart.', 'flx-graphql' ),
			'input'       => array(),
			'output'      => $cart_out,
			'resolve'     => static fn() => flx_gql_cart_payload( FLXGQL_Cart::clear() ),
		)
	);
	flx_gql_mutation(
		'flxCartCouponApply',
		array(
			'cap'         => 'public',
			'description' => __( 'Applies a coupon.', 'flx-graphql' ),
			'input'       => array(
				'code' => array(
					'type'        => 'String!',
					'description' => __( 'Coupon code.', 'flx-graphql' ),
				),
			),
			'output'      => $cart_out,
			'resolve'     => static fn( array $input ) => flx_gql_cart_payload( FLXGQL_Cart::apply_coupon( flx_gql_str( $input['code'] ?? '' ) ) ),
		)
	);
	flx_gql_mutation(
		'flxCartCouponRemove',
		array(
			'cap'         => 'public',
			'description' => __( 'Removes a coupon.', 'flx-graphql' ),
			'input'       => array(
				'code' => array(
					'type'        => 'String!',
					'description' => __( 'Coupon code.', 'flx-graphql' ),
				),
			),
			'output'      => $cart_out,
			'resolve'     => static fn( array $input ) => flx_gql_cart_payload( FLXGQL_Cart::remove_coupon( flx_gql_str( $input['code'] ?? '' ) ) ),
		)
	);
	flx_gql_mutation(
		'flxCartShippingRateSelect',
		array(
			'cap'         => 'public',
			'description' => __( 'Chooses a shipping option.', 'flx-graphql' ),
			'input'       => array(
				'rateId'    => array(
					'type'        => 'String!',
					'description' => __( 'Rate ID (FlxCartShippingRate.rateId).', 'flx-graphql' ),
				),
				'packageId' => array(
					'type'        => 'Int',
					'description' => __( 'Shipping package; left out = every package.', 'flx-graphql' ),
				),
			),
			'output'      => $cart_out,
			'resolve'     => static fn( array $input ) => flx_gql_cart_payload( FLXGQL_Cart::select_shipping_rate( flx_gql_str( $input['rateId'] ?? '' ), isset( $input['packageId'] ) ? flx_gql_int( $input['packageId'] ) : null ) ),
		)
	);
	flx_gql_mutation(
		'flxCartCustomerUpdate',
		array(
			'cap'         => 'public',
			'description' => __( 'Changes the billing and/or shipping address; totals, taxes and shipping options follow.', 'flx-graphql' ),
			'input'       => array(
				'billingAddress'  => array(
					'type'        => 'FlxCartAddressInput',
					'description' => __( 'Billing address fields to change.', 'flx-graphql' ),
				),
				'shippingAddress' => array(
					'type'        => 'FlxCartAddressInput',
					'description' => __( 'Shipping address fields to change.', 'flx-graphql' ),
				),
			),
			'output'      => $cart_out,
			'resolve'     => static fn( array $input ) => flx_gql_cart_payload(
				FLXGQL_Cart::update_customer(
					isset( $input['billingAddress'] ) ? flx_gql_arr( $input['billingAddress'] ) : null,
					isset( $input['shippingAddress'] ) ? flx_gql_arr( $input['shippingAddress'] ) : null
				)
			),
		)
	);
}

/**
 * Address fields: GraphQL name → description.
 *
 * @return array<string,string>
 */
function flx_gql_cart_address_fields(): array {
	return array(
		'firstName' => __( 'First name.', 'flx-graphql' ),
		'lastName'  => __( 'Last name.', 'flx-graphql' ),
		'company'   => __( 'Company.', 'flx-graphql' ),
		'address1'  => __( 'Address line 1.', 'flx-graphql' ),
		'address2'  => __( 'Address line 2.', 'flx-graphql' ),
		'city'      => __( 'City.', 'flx-graphql' ),
		'state'     => __( 'State or province code.', 'flx-graphql' ),
		'postcode'  => __( 'Postcode.', 'flx-graphql' ),
		'country'   => __( 'ISO 3166-1 alpha-2 country code.', 'flx-graphql' ),
		'email'     => __( 'E-mail.', 'flx-graphql' ),
		'phone'     => __( 'Phone.', 'flx-graphql' ),
	);
}

/**
 * Service result → `{ cart }` payload (WP_Error passes through to the kit).
 *
 * @param array<string,mixed>|WP_Error $cart Cart or error.
 * @return array<string,mixed>|WP_Error
 */
function flx_gql_cart_payload( $cart ) {
	return $cart instanceof WP_Error ? $cart : array( 'cart' => $cart );
}
