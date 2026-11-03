<?php
/**
 * Bildirimler. WhatsApp mesajları Flux Mail'in bağlantısıyla (flxi_send_message) gider; Flux Mail yoksa ya
 * da WhatsApp bağlantısı yoksa sessizce atlanır. E-postalar wp_mail ile (Flux Mail kuruluysa onun
 * tasarımında) gider. WooCommerce'in kendi sipariş e-postaları olduğu gibi kalır.
 *
 *  - Havale siparişi verildi → müşteriye WhatsApp: IBAN, açıklama, dekont bağlantısı.
 *  - Dekont yüklendi → yöneticiye e-posta (+ telefon verildiyse WhatsApp).
 *  - Havale onaylandı → müşteriye WhatsApp.
 *  - Ödenmiş yeni sipariş → (açıksa) yöneticiye WhatsApp.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Notify {

	public static function init() {
		add_action( 'flxcm_bank_placed', array( __CLASS__, 'bank_placed' ) );
		add_action( 'flxcm_receipt_uploaded', array( __CLASS__, 'receipt_uploaded' ), 10, 2 );
		add_action( 'flxcm_bank_confirmed', array( __CLASS__, 'bank_confirmed' ) );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'paid' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'paid' ) );
	}

	/** WhatsApp gönderebilir mi? */
	public static function whatsapp_ready() {
		return function_exists( 'flxi_send_message' ) && ( ! function_exists( 'flxi_get_channel_connection' ) || flxi_get_channel_connection( 'whatsapp' ) );
	}

	public static function whatsapp( $phone, $text ) {
		$phone = flxcm_phone( $phone );
		if ( '' === $phone || ! self::whatsapp_ready() ) {
			return false;
		}
		$r = flxi_send_message( 'whatsapp', $phone, $text, array( 'source' => 'flx-commerce' ) );
		return true === $r || ( is_array( $r ) && ! empty( $r['ok'] ) );
	}

	private static function site() {
		return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}

	public static function bank_placed( $order ) {
		if ( ! $order instanceof WC_Order || ! flxcm_setting( 'notify_customer_wa' ) || $order->get_meta( '_flxcm_wa_placed' ) ) {
			return;
		}
		$text = sprintf(
			/* translators: 1: first name, 2: site, 3: order no, 4: amount, 5: holder, 6: IBAN, 7: reference, 8: link */
			__( 'Merhaba %1$s, %2$s siparişiniz (#%3$s) alındı.

Ödeme: %4$s
Alıcı: %5$s
IBAN: %6$s
Açıklama: %7$s

Ödemeden sonra dekontu buradan yükleyebilirsiniz: %8$s', 'flx-commerce' ),
			$order->get_billing_first_name(),
			self::site(),
			$order->get_order_number(),
			flxcm_money( $order->get_total(), $order->get_currency() ),
			FLXCM_Bank::option( 'holder' ),
			FLXCM_Bank::format_iban( FLXCM_Bank::option( 'iban' ) ),
			FLXCM_Bank::reference( $order ),
			$order->get_checkout_order_received_url()
		);
		if ( self::whatsapp( $order->get_billing_phone(), (string) apply_filters( 'flxcm_wa_bank_placed', $text, $order ) ) ) {
			$order->update_meta_data( '_flxcm_wa_placed', time() );
			$order->save_meta_data();
			$order->add_order_note( __( 'Havale bilgileri müşteriye WhatsApp ile gönderildi.', 'flx-commerce' ) );
		}
	}

	public static function receipt_uploaded( $order, $rec ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$to = trim( (string) flxcm_setting( 'receipt_notify' ) );
		$to = is_email( $to ) ? $to : get_option( 'admin_email' );
		/* translators: 1: order no, 2: name */
		$subject = sprintf( __( 'Dekont geldi: sipariş #%1$s · %2$s', 'flx-commerce' ), $order->get_order_number(), $order->get_formatted_billing_full_name() );
		$body    = sprintf(
			/* translators: 1: name, 2: order no, 3: amount, 4: reference, 5: file, 6: link */
			__( '%1$s, #%2$s siparişi için dekont yükledi.

Tutar: %3$s
Açıklama: %4$s
Dosya: %5$s

Kontrol edip onaylayın: %6$s', 'flx-commerce' ),
			$order->get_formatted_billing_full_name(),
			$order->get_order_number(),
			flxcm_money( $order->get_total(), $order->get_currency() ),
			FLXCM_Bank::reference( $order ),
			(string) ( $rec['name'] ?? '' ),
			$order->get_edit_order_url()
		);
		wp_mail( $to, $subject, $body );
		$admin = (string) flxcm_setting( 'notify_admin_phone' );
		if ( '' !== $admin ) {
			self::whatsapp( $admin, $subject . "\n" . $order->get_edit_order_url() );
		}
	}

	public static function bank_confirmed( $order ) {
		if ( ! $order instanceof WC_Order || ! flxcm_setting( 'notify_customer_wa' ) ) {
			return;
		}
		$text = sprintf(
			/* translators: 1: first name, 2: order no, 3: site */
			__( 'Merhaba %1$s, #%2$s siparişinizin ödemesi onaylandı. Siparişiniz hazırlanıyor. %3$s', 'flx-commerce' ),
			$order->get_billing_first_name(),
			$order->get_order_number(),
			self::site()
		);
		self::whatsapp( $order->get_billing_phone(), (string) apply_filters( 'flxcm_wa_bank_confirmed', $text, $order ) );
	}

	public static function paid( $order_id ) {
		$order = wc_get_order( $order_id );
		$phone = (string) flxcm_setting( 'notify_admin_phone' );
		if ( ! $order || ! flxcm_setting( 'notify_admin_order' ) || '' === $phone || $order->get_meta( '_flxcm_wa_admin' ) || ! flxcm_count_order( $order ) ) {
			return;
		}
		$lines = array();
		foreach ( $order->get_items() as $item ) {
			$lines[] = '• ' . $item->get_quantity() . ' × ' . $item->get_name();
		}
		$text = sprintf(
			/* translators: 1: order no, 2: amount, 3: name, 4: items, 5: link */
			__( 'Yeni sipariş #%1$s · %2$s
%3$s
%4$s
%5$s', 'flx-commerce' ),
			$order->get_order_number(),
			flxcm_money( $order->get_total(), $order->get_currency() ),
			$order->get_formatted_billing_full_name(),
			implode( "\n", array_slice( $lines, 0, 10 ) ),
			$order->get_edit_order_url()
		);
		if ( self::whatsapp( $phone, $text ) ) {
			$order->update_meta_data( '_flxcm_wa_admin', time() );
			$order->save_meta_data();
		}
	}
}
