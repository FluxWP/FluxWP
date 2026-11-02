<?php
/**
 * Pending-transfer mini notification.
 *
 * When a bank transfer donation is registered (order on hold), the donor gets a short WhatsApp or
 * SMS message with the IBAN, the reference code and the receipt upload link. It is sent through
 * Flux Mail when that plugin is active, otherwise through Flux Certificates's queue (retries and log
 * there). The e-mail is WooCommerce's "on hold" e-mail with the transfer details added.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

class FLXB_WC_Notify {

	public static function init() {
		add_action( 'flxb_wc_preregistered', array( __CLASS__, 'send' ) );
	}

	/** Placeholders of the message for an order. */
	public static function vars( WC_Order $order ) {
		return array(
			'{ad}'       => $order->get_billing_first_name() ? $order->get_billing_first_name() : $order->get_formatted_billing_full_name(),
			'{tutar}'    => FLXB_WC::money_text( $order->get_total(), $order->get_currency() ),
			'{siparis}'  => $order->get_order_number(),
			'{alici}'    => FLXB_WC_Havale::option( 'holder' ),
			'{iban}'     => FLXB_WC_Havale::format_iban( FLXB_WC_Havale::option( 'iban' ) ),
			'{aciklama}' => FLXB_WC_Havale::reference( $order ),
			'{link}'     => $order->get_checkout_order_received_url(),
			'{site}'     => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		);
	}

	public static function send( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order || $order->get_meta( '_flxb_notified' ) ) {
			return false;
		}
		$channels = array_values( array_intersect( (array) FLXB_WC::setting( 'notify_channels' ), array( 'whatsapp', 'sms' ) ) );
		$phone    = function_exists( 'flxb_normalize_phone' ) ? flxb_normalize_phone( $order->get_billing_phone() ) : preg_replace( '/\D/', '', $order->get_billing_phone() );
		if ( ! $channels || ! $phone ) {
			return false;
		}
		/* Both chosen: SMS only when there is no WhatsApp route. */
		if ( count( $channels ) > 1 && FLXB_Mail::has_channel( 'whatsapp' ) ) {
			$channels = array( 'whatsapp' );
		}
		$vars = self::vars( $order );
		$done = array();
		foreach ( $channels as $ch ) {
			$r = FLXB_Mail::message( $ch, $phone, strtr( FLXB_WC::notify_text( $ch ), $vars ), $order->get_formatted_billing_full_name(), 'flxb_onkayit' );
			if ( true === $r ) {
				$done[] = 'sms' === $ch ? 'SMS' : 'WhatsApp';
			}
		}
		if ( ! $done ) {
			return false;
		}
		$order->update_meta_data( '_flxb_notified', time() );
		$order->save_meta_data();
		/* translators: %s: channel list */
		$order->add_order_note( sprintf( __( 'Pending transfer notification sent: %s.', 'flx-donate' ), implode( ', ', $done ) ) );
		return true;
	}
}
