<?php
/**
 * Sunucu tarafı olaylar: ödenen bağış/sipariş (WooCommerce, her sipariş bir kez), sepete ekleme / çıkarma, kupon, sipariş oluşturma.
 * Kişisel bilgi yazılmaz; yalnız bağış türü (ilk kalemin adı) ve tutar.
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

function flxan_track_order( $order_id ) {
	if ( ! flxan_settings( 'ev_donation' ) || ! function_exists( 'wc_get_order' ) ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! $order || ! is_a( $order, 'WC_Order' ) || $order->get_meta( '_flxan_tracked' ) || apply_filters( 'flx_silent', false, $order ) ) { // 0.4.10: imported history
		return;
	}
	$name = '';
	foreach ( $order->get_items() as $item ) {
		$name = $item->get_name();
		break;
	}
	$name = (string) apply_filters( 'flxan_order_event_param', $name, $order );
	$ref  = (string) $order->get_meta( '_flxan_ref' );
	if ( flxan_track_event( 'donation', $name, (float) $order->get_total(), 'order-' . $order->get_id(), $ref ) ) {
		$order->update_meta_data( '_flxan_tracked', time() );
		$order->save();
	}
}
/** Sipariş oluşurken referans çerezi (?r= ile gelen) siparişe yazılır; bağış o koda sayılır. */
function flxan_order_ref( $order_id, $order = null ) {
	if ( empty( $_COOKIE['flxan_r'] ) || ! function_exists( 'wc_get_order' ) ) {
		return;
	}
	$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
	$rc    = flxan_clean_ref( sanitize_text_field( wp_unslash( $_COOKIE['flxan_r'] ) ) ); // phpcs:ignore
	if ( $order && '' !== $rc && ! $order->get_meta( '_flxan_ref' ) ) {
		$order->update_meta_data( '_flxan_ref', $rc );
		$order->save_meta_data();
	}
}
/**
 * Sunucuda olan ama ziyaretçinin yolculuğuna bağlanması gereken olaylar (sepete ekleme vb.):
 * kısa ömürlü "flxan_pe" çerezine yazılır, tracker.js bir sonraki sayfada (ya da AJAX sonrası hemen)
 * ziyaretçinin kendi olayı olarak gönderir ve çerezi siler. Böylece huni ve yolculukta görünür.
 */
function flxan_queue_event( $name, $param = '' ) {
	static $queue = null;
	if ( ! flxan_settings( 'on' ) || ! flxan_settings( 'ev_donation' ) || headers_sent() || ! preg_match( '/^[a-z0-9_-]{1,40}$/', (string) $name ) ) {
		return;
	}
	if ( null === $queue ) {
		$queue = array();
		if ( ! empty( $_COOKIE['flxan_pe'] ) ) {
			$old = json_decode( wp_unslash( (string) $_COOKIE['flxan_pe'] ), true ); // phpcs:ignore
			if ( is_array( $old ) ) {
				foreach ( $old as $e ) {
					if ( is_array( $e ) && isset( $e[0] ) && preg_match( '/^[a-z0-9_-]{1,40}$/', (string) $e[0] ) ) {
						$queue[] = array( (string) $e[0], flxan_c_text( (string) ( $e[1] ?? '' ), 80 ) );
					}
				}
			}
		}
	}
	$queue[] = array( $name, flxan_c_text( wp_strip_all_tags( html_entity_decode( (string) $param, ENT_QUOTES, 'UTF-8' ) ), 80 ) );
	$queue   = array_slice( $queue, -6 );
	$val     = wp_json_encode( $queue );
	$_COOKIE['flxan_pe'] = $val;
	setcookie(
		'flxan_pe',
		$val,
		array(
			'expires'  => time() + 1800,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'secure'   => is_ssl(),
			'httponly' => false,
			'samesite' => 'Lax',
		)
	);
}

/** Sepete eklenen ürünün (bağış türünün) adı. */
function flxan_cart_product_name( $product_id, $item = array() ) {
	$name = $product_id ? get_the_title( $product_id ) : '';
	return (string) apply_filters( 'flxan_cart_event_param', $name, $product_id, $item );
}

add_action(
	'woocommerce_add_to_cart',
	function ( $key, $product_id, $qty = 1, $variation_id = 0, $variation = array(), $data = array() ) {
		flxan_queue_event( 'add_to_cart', flxan_cart_product_name( $variation_id ? $variation_id : $product_id, $data ) );
	},
	20,
	6
);
add_action(
	'woocommerce_remove_cart_item',
	function ( $key, $cart ) {
		$item = is_object( $cart ) && method_exists( $cart, 'get_cart_item' ) ? $cart->get_cart_item( $key ) : array();
		flxan_queue_event( 'remove_from_cart', flxan_cart_product_name( (int) ( $item['product_id'] ?? 0 ), $item ) );
	},
	20,
	2
);
add_action(
	'woocommerce_applied_coupon',
	function ( $code ) {
		flxan_queue_event( 'coupon', strtolower( (string) $code ) );
	}
);
/** Sipariş oluştu (ödeme öncesi): ödeme yöntemiyle. Ödenince ayrıca "donation" sayılır. */
function flxan_queue_order_event( $order ) {
	$order = $order instanceof WC_Order ? $order : ( function_exists( 'wc_get_order' ) ? wc_get_order( $order ) : null );
	if ( $order && ! $order->get_meta( '_flxan_oq' ) ) {
		flxan_queue_event( 'order', (string) $order->get_payment_method() );
		$order->update_meta_data( '_flxan_oq', 1 );
		$order->save_meta_data();
	}
}
add_action( 'woocommerce_checkout_order_created', 'flxan_queue_order_event', 20 );
add_action( 'woocommerce_store_api_checkout_order_processed', 'flxan_queue_order_event', 20 );

add_action( 'woocommerce_new_order', 'flxan_order_ref', 10, 2 );
add_action( 'woocommerce_checkout_order_created', 'flxan_order_ref' );
add_action( 'woocommerce_store_api_checkout_order_processed', 'flxan_order_ref' );

add_action( 'woocommerce_payment_complete', 'flxan_track_order' );
add_action( 'woocommerce_order_status_processing', 'flxan_track_order' );
add_action( 'woocommerce_order_status_completed', 'flxan_track_order' );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FLXAN_FILE, true );
		}
	}
);
