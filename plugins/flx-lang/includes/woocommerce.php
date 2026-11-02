<?php
/**
 * WooCommerce: sepet, ödeme, hesabım her dilde aynı sayfa (arayüz istek dilinde). Siparişe ziyaretçinin dili yazılır
 * (_flxl_lang); blok ödeme sayfasının Store API isteği de /en/wp-json/ ile gelir. Flux Donate / Flux Mail e-postayı o dilde göndermek için flxl_order_language() kullanabilir.
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

function flxl_order_language( $order ) {
	$order = is_numeric( $order ) && function_exists( 'wc_get_order' ) ? wc_get_order( $order ) : $order;
	$l     = $order && is_callable( array( $order, 'get_meta' ) ) ? (string) $order->get_meta( '_flxl_lang' ) : '';
	return flxl_is_language( $l ) ? $l : flxl_default_language();
}

add_action(
	'woocommerce_checkout_create_order',
	function ( $order ) {
		$order->update_meta_data( '_flxl_lang', flxl_current_language() );
	}
);
add_action(
	'woocommerce_store_api_checkout_update_order_meta',
	function ( $order ) {
		$order->update_meta_data( '_flxl_lang', flxl_current_language() );
	}
);
