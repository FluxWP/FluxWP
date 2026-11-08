<?php
/**
 * WooCommerce: sohbetten sonra verilen siparişler o sohbete yazılır ("sohbetin getirdiği satış").
 * Tarayıcı, sohbet anahtarını flxcb_t çerezinde tutar; sipariş anında son 7 gün içinde konuşulmuş sohbet aranır.
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

function flxcb_attribute_order( $order ) {
	if ( ! $order || ! is_object( $order ) || empty( $_COOKIE['flxcb_t'] ) ) {
		return;
	}
	$conv = flxcb_conversation_by_token( sanitize_text_field( wp_unslash( $_COOKIE['flxcb_t'] ) ) );
	if ( ! $conv || $conv['order_id'] || flxcb_now_ms() - (int) $conv['last_ts'] > 7 * DAY_IN_SECONDS * 1000 ) {
		return;
	}
	if ( $order->get_meta( '_flxcb_conv' ) ) {
		return;
	}
	$order->update_meta_data( '_flxcb_conv', (int) $conv['id'] );
	$order->save_meta_data();
	flxcb_update_conversation( (int) $conv['id'], array( 'order_id' => (int) $order->get_id() ) );
	/* translators: 1: order number, 2: total */
	flxcb_event( (int) $conv['id'], 'order', sprintf( __( 'Order placed: #%1$s (%2$s)', 'flx-chatbot' ), $order->get_order_number(), flxcb_plain( $order->get_formatted_order_total() ) ) );
	do_action( 'flxcb_order_attributed', $conv, $order );
}

add_action(
	'woocommerce_checkout_order_created',
	'flxcb_attribute_order'
);
add_action(
	'woocommerce_store_api_checkout_order_processed',
	'flxcb_attribute_order'
);

/* Sipariş ekranında küçük not: hangi sohbetten geldi. */
add_action(
	'woocommerce_admin_order_data_after_order_details',
	function ( $order ) {
		$id = (int) $order->get_meta( '_flxcb_conv' );
		if ( $id && flxcb_can_reply() ) {
			echo '<p class="form-field form-field-wide"><a href="' . esc_url( flxcb_inbox_url( $id ) ) . '">' . esc_html(
				/* translators: %d: conversation number */
				sprintf( __( 'Placed after site chat #%d', 'flx-chatbot' ), $id )
			) . '</a></p>';
		}
	}
);
