<?php
/**
 * Mağaza › Ayarlar.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Page_Settings {

	private static function toggle( $key, $label, $hint = '' ) {
		$on = (bool) flxcm_setting( $key );
		echo '<div class="flxcm-field flxcm-field--toggle"><label class="flxcm-switch"><input type="checkbox" name="' . esc_attr( $key ) . '" value="1"' . checked( $on, true, false ) . '><span aria-hidden="true"></span></label><div><span class="flxcm-field__label">' . esc_html( $label ) . '</span>';
		if ( '' !== $hint ) {
			echo '<span class="flxcm-field__hint">' . esc_html( $hint ) . '</span>';
		}
		echo '</div></div>';
	}

	private static function input( $key, $label, $type = 'text', $hint = '', $attrs = '' ) {
		$id = 'flxcm-' . $key;
		echo '<div class="flxcm-field"><label class="flxcm-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		echo '<input id="' . esc_attr( $id ) . '" type="' . esc_attr( $type ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) flxcm_setting( $key ) ) . '" ' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( '' !== $hint ) {
			echo '<span class="flxcm-field__hint">' . esc_html( $hint ) . '</span>';
		}
		echo '</div>';
	}

	public static function render() {
		if ( ! current_user_can( FLXCM_Admin::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-commerce' ), 403 );
		}
		FLXCM_Admin::open( __( 'Settings', 'flx-commerce' ), __( 'Dashboard, checkout, bank transfer, product feed and notifications.', 'flx-commerce' ) );
		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'flx-commerce' ) . '</p></div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxcm-settings">';
		wp_nonce_field( 'flxcm_save' );
		echo '<input type="hidden" name="action" value="flxcm_save">';

		// Panel ve raporlar.
		echo '<section class="flxcm-card"><h2>' . flxcm_icon( 'trend', 18 ) . ' ' . esc_html__( 'Dashboard and reports', 'flx-commerce' ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<fieldset class="flxcm-field"><legend class="flxcm-field__label">' . esc_html__( 'Order statuses counted as revenue', 'flx-commerce' ) . '</legend><div class="flxcm-checks">';
		$paid = flxcm_paid_statuses();
		foreach ( wc_get_order_statuses() as $k => $label ) {
			$k = substr( $k, 3 );
			if ( in_array( $k, array( 'pending', 'cancelled', 'failed', 'checkout-draft', 'refunded' ), true ) ) {
				continue;
			}
			echo '<label><input type="checkbox" name="paid_statuses[]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, $paid, true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</div><span class="flxcm-field__hint">' . esc_html__( 'On-hold (bank transfer) orders count once the payment is confirmed.', 'flx-commerce' ) . '</span></fieldset>';
		if ( class_exists( 'FLXB_WC_Cart' ) ) {
			self::toggle( 'exclude_donations', __( 'Leave donation orders out of the store statistics', 'flx-commerce' ), __( 'Flux Donate is installed: orders with only donations stay in the Donations dashboard.', 'flx-commerce' ) );
		}
		self::input( 'low_stock', __( 'Low stock threshold', 'flx-commerce' ), 'number', __( 'Products whose stock falls to this number or below are listed on the Overview.', 'flx-commerce' ), 'min="0" max="9999" class="small-text"' );
		echo '</section>';

		// Ödeme sayfası.
		echo '<section class="flxcm-card"><h2>' . flxcm_icon( 'cart', 18 ) . ' ' . esc_html__( 'Cart and checkout', 'flx-commerce' ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		self::toggle( 'hide_company', __( 'Hide the company name field', 'flx-commerce' ) );
		self::toggle( 'hide_address_2', __( 'Hide the second address line', 'flx-commerce' ) );
		self::toggle( 'virtual_no_address', __( 'Do not ask for an address when the cart only has virtual/downloadable products', 'flx-commerce' ), __( 'On the classic checkout. Country, name, e-mail and phone stay.', 'flx-commerce' ) );
		self::toggle( 'order_notes', __( 'Order notes field', 'flx-commerce' ) );
		self::toggle( 'sticky_pay', __( 'On mobile, keep the "Place order" button fixed at the bottom', 'flx-commerce' ) );
		self::toggle( 'free_ship_bar', __( 'Free shipping bar', 'flx-commerce' ), __( '"Add X more for free shipping" in the cart and at checkout.', 'flx-commerce' ) );
		$auto = FLXCM_Checkout::free_threshold();
		self::input(
			'free_ship_amount',
			__( 'Free shipping amount', 'flx-commerce' ),
			'number',
			(float) flxcm_setting( 'free_ship_amount' ) > 0 || $auto <= 0
				? __( 'Empty or 0 = the minimum amount of WooCommerce\'s "Free shipping" method.', 'flx-commerce' )
				/* translators: %s: amount */
				: sprintf( __( 'Empty = from WooCommerce\'s "Free shipping" method: %s', 'flx-commerce' ), flxcm_money( $auto ) ),
			'min="0" step="0.01" class="regular-text"'
		);
		echo '</section>';

		// Havale/EFT.
		$gw_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . FLXCM_Bank::ID );
		echo '<section class="flxcm-card"><h2>' . flxcm_icon( 'bank', 18 ) . ' ' . esc_html__( 'Bank transfer', 'flx-commerce' ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="flxcm-muted">' . esc_html__( 'Turning the method on, the IBAN and the reference format are in WooCommerce › Settings › Payments › Bank transfer.', 'flx-commerce' ) . ' <a href="' . esc_url( $gw_url ) . '">' . esc_html__( 'Open the payment method', 'flx-commerce' ) . '</a></p>';
		if ( class_exists( 'FLXB_WC_Havale' ) ) {
			self::toggle( 'defer_to_donate', __( 'Hide this method when Flux Donate\'s bank transfer is on', 'flx-commerce' ), __( 'So the checkout does not show two separate "Bank transfer" options.', 'flx-commerce' ) );
		}
		self::input( 'receipt_max_mb', __( 'Largest receipt (MB)', 'flx-commerce' ), 'number', '', 'min="1" max="20" class="small-text"' );
		self::input( 'receipt_notify', __( 'Receipt notification e-mail', 'flx-commerce' ), 'email', __( 'Empty = the site admin e-mail.', 'flx-commerce' ), 'class="regular-text"' );
		self::input( 'cancel_after_days', __( 'Cancel unpaid bank transfers after (days)', 'flx-commerce' ), 'number', __( '0 = never cancel. Orders with a receipt are not cancelled; stock is restored.', 'flx-commerce' ), 'min="0" max="60" class="small-text"' );
		echo '</section>';

		// Feed.
		echo '<section class="flxcm-card"><h2>' . flxcm_icon( 'rss', 18 ) . ' ' . esc_html__( 'Product feed (Google Merchant)', 'flx-commerce' ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		self::toggle( 'feed', __( 'Feed on', 'flx-commerce' ), __( 'Google Merchant Center, Meta catalog and similar services read this address.', 'flx-commerce' ) );
		if ( flxcm_setting( 'feed' ) ) {
			$url = FLXCM_Feed::url();
			echo '<div class="flxcm-field"><span class="flxcm-field__label">' . esc_html__( 'Feed address', 'flx-commerce' ) . '</span><span class="flxcm-copyline"><input type="text" readonly value="' . esc_attr( $url ) . '" class="large-text" onfocus="this.select()"><button type="button" class="button" data-flxcm-copy="' . esc_attr( $url ) . '">' . flxcm_icon( 'copy', 15 ) . ' ' . esc_html__( 'Copy', 'flx-commerce' ) . '</button></span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		self::input( 'feed_brand', __( 'Brand', 'flx-commerce' ), 'text', __( 'Empty = site name.', 'flx-commerce' ), 'class="regular-text"' );
		echo '</section>';

		// Bildirimler.
		$wa = FLXCM_Notify::whatsapp_ready();
		echo '<section class="flxcm-card"><h2>' . flxcm_icon( 'alert', 18 ) . ' ' . esc_html__( 'Notifications', 'flx-commerce' ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="flxcm-muted">' . ( $wa ? esc_html__( 'WhatsApp messages go through Flux Mail\'s WhatsApp connection.', 'flx-commerce' ) : esc_html__( 'WhatsApp needs Flux Mail and a WhatsApp connection; only e-mail is sent for now.', 'flx-commerce' ) ) . '</p>';
		self::toggle( 'notify_customer_wa', __( 'Send the bank details and the payment confirmation to the customer by WhatsApp', 'flx-commerce' ) );
		self::input( 'notify_admin_phone', __( 'Admin WhatsApp number', 'flx-commerce' ), 'tel', __( 'Gets a message when a receipt arrives (and, if on, for every new order).', 'flx-commerce' ), 'class="regular-text" placeholder="+90 5xx xxx xx xx"' );
		self::toggle( 'notify_admin_order', __( 'WhatsApp the admin for every new paid order', 'flx-commerce' ) );
		echo '</section>';

		echo '<p class="flxcm-save"><button type="submit" class="button button-primary button-large">' . esc_html__( 'Save', 'flx-commerce' ) . '</button></p></form>';
		FLXCM_Admin::close();
	}

	public static function save() {
		check_admin_referer( 'flxcm_save' );
		if ( ! current_user_can( FLXCM_Admin::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-commerce' ), 403 );
		}
		$in   = wp_unslash( $_POST );
		$bool = array( 'exclude_donations', 'hide_company', 'hide_address_2', 'virtual_no_address', 'order_notes', 'sticky_pay', 'free_ship_bar', 'defer_to_donate', 'feed', 'notify_customer_wa', 'notify_admin_order' );
		$out  = array();
		foreach ( $bool as $k ) {
			// Ekranda olmayan anahtar (ör. Flux Donate yokken) eski değerini korur.
			if ( 'exclude_donations' === $k && ! class_exists( 'FLXB_WC_Cart' ) || 'defer_to_donate' === $k && ! class_exists( 'FLXB_WC_Havale' ) ) {
				continue;
			}
			$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		$valid = array_map( static function ( $k ) { return substr( $k, 3 ); }, array_keys( wc_get_order_statuses() ) );
		$paid  = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $in['paid_statuses'] ?? array() ) ), $valid ) );
		$out['paid_statuses']      = $paid ? $paid : array( 'processing', 'completed' );
		$out['low_stock']          = min( 9999, absint( $in['low_stock'] ?? 3 ) );
		$out['free_ship_amount']   = max( 0, (float) wc_format_decimal( $in['free_ship_amount'] ?? 0 ) );
		$out['receipt_max_mb']     = max( 1, min( 20, absint( $in['receipt_max_mb'] ?? 8 ) ) );
		$out['receipt_notify']     = sanitize_email( (string) ( $in['receipt_notify'] ?? '' ) );
		$out['cancel_after_days']  = min( 60, absint( $in['cancel_after_days'] ?? 0 ) );
		$out['feed_brand']         = sanitize_text_field( (string) ( $in['feed_brand'] ?? '' ) );
		$out['notify_admin_phone'] = preg_replace( '/[^0-9+ ]/', '', (string) ( $in['notify_admin_phone'] ?? '' ) );
		flxcm_save_settings( $out );
		delete_transient( 'flxcm_free_min' );
		FLXCM_Feed::flush();
		wp_safe_redirect( flxcm_admin_url( 'settings', array( 'saved' => 1 ) ) );
		exit;
	}
}
