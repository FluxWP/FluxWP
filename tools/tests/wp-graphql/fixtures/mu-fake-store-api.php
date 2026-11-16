<?php
/**
 * Test fixture: a small stand-in for the WooCommerce Store API cart routes, used only when WooCommerce
 * is not active on the test site (the cloud containers cannot download it; CI installs the real one).
 *
 * It answers the same routes, response shape, Cart-Token / Nonce headers and errors the cart bridge
 * depends on, and fires the same WooCommerce hooks (woocommerce_add_cart_item_data,
 * woocommerce_add_to_cart, woocommerce_get_item_data). Products are `product` posts with a `_price` meta,
 * coupons `shop_coupon` posts with `coupon_amount` (fixed cart). Sessions live in options.
 * Never load this next to WooCommerce.
 */

defined( 'ABSPATH' ) || exit;

if ( ! in_array( 'woocommerce/woocommerce.php', (array) get_option( 'active_plugins', array() ), true ) ) {
	require __DIR__ . '/flxt-fake-store-api/impl.php';
}

