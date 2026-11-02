<?php
/**
 * Flux Donate 2.0: admin parts of the WooCommerce layer.
 *
 *  - Donations › Settings › WooCommerce (certificates, printed certificate, bank transfer notice,
 *    receipts, cart, Flux Forms checkout, card method), status list, product XML address, "create the
 *    donation page".
 *  - Donation type editor: "Online donation page" card (certificate, template, preset amounts,
 *    "different number" option, page address, linked product).
 *  - Order screen: "Donation" box (transfer reference, receipts, Confirm payment, Make certificate),
 *    "Receipt" column in the orders list.
 *  - Donation detail: refunds of WooCommerce donations go through WooCommerce (wc_create_refund).
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

class FLXB_WC_Admin {

	public static function init() {
		add_filter( 'flxb_settings_tabs', array( __CLASS__, 'tab' ) );
		add_action( 'flxb_settings_tab_woocommerce', array( __CLASS__, 'settings' ) );
		add_action( 'admin_post_flxb_wc_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_flxb_wc_setup_page', array( __CLASS__, 'setup_page' ) );
		add_action( 'flxb_type_fields', array( __CLASS__, 'type_card' ), 20 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_flxb_approve', array( __CLASS__, 'approve' ) );
		add_action( 'admin_post_flxb_issue', array( __CLASS__, 'issue' ) );
		add_action( 'admin_post_flxb_wc_refund', array( __CLASS__, 'refund' ) );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'column' ) );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'column_value' ), 10, 2 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'column_value' ), 10, 2 );
	}

	public static function url( array $args = array() ) {
		return FLXB_Admin_Url::page( 'flxb-settings', array_merge( array( 'tab' => 'woocommerce' ), $args ) );
	}

	public static function tab( $tabs ) {
		$out = array();
		foreach ( $tabs as $k => $v ) {
			if ( 'import' === $k ) {
				$out['woocommerce'] = __( 'WooCommerce', 'flx-donate' );
			}
			$out[ $k ] = $v;
		}
		if ( ! isset( $out['woocommerce'] ) ) {
			$out['woocommerce'] = __( 'WooCommerce', 'flx-donate' );
		}
		return $out;
	}

	public static function assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
			wp_enqueue_style( 'flxwb-admin', FLXB_URL . 'assets/wc/admin.css', array(), FLXB_VERSION );
		}
	}

	/* ------------------------------------------------------------------
	 * Settings tab
	 * ------------------------------------------------------------------ */

	private static function check( $name, $on, $label, $value = '1', $hidden = true ) {
		return ( $hidden ? '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">' : '' ) . '<label class="flxb-switch"><input type="checkbox" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . checked( $on, true, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html( $label ) . '</label>';
	}

	private static function box( $name, $on, $label, $value ) {
		return '<label class="flxb-check"><input type="checkbox" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . checked( $on, true, false ) . '> ' . esc_html( $label ) . '</label>';
	}

	/** Status lines (also used by the system check). */
	public static function status() {
		$gws  = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		$on   = static function ( $id ) use ( $gws ) {
			return isset( $gws[ $id ] ) && 'yes' === $gws[ $id ]->enabled;
		};
		$page = (int) FLXB_WC::setting( 'page_id' );
		$tpls = FLXB_WC::certificates_available() && function_exists( 'flxs_list_templates' ) ? flxs_list_templates( true ) : array();
		$out  = array();
		$out[] = $on( FLXB_WC_Iyzico::OFFICIAL )
			? array( 'ok', __( 'Card payments: the official iyzico plugin is enabled.', 'flx-donate' ) )
			: ( FLXB_WC_Iyzico::usable() ? array( 'ok', __( 'Card payments: Flux Donate iyzico (the official iyzico plugin is not enabled).', 'flx-donate' ) ) : array( 'warn', __( 'No card method is enabled (WooCommerce › Settings › Payments).', 'flx-donate' ) ) );
		$out[] = FLXB_WC_Iyzico::usable() ? array( 'ok', __( 'Monthly donations: Flux Donate iyzico is ready.', 'flx-donate' ) ) : array( 'warn', __( 'Monthly donations are off: enable "Card (iyzico) - Flux Donate" and enter iyzico keys.', 'flx-donate' ) );
		$out[] = $on( FLXB_WC_Havale::ID ) ? array( 'ok', __( 'Bank transfer (donations) is enabled.', 'flx-donate' ) ) : array( 'warn', __( 'Bank transfer (donations) is disabled.', 'flx-donate' ) );
		$out[] = FLXB_WC::certificates_available() ? ( $tpls ? array( 'ok', __( 'Flux Certificates is connected.', 'flx-donate' ) ) : array( 'warn', __( 'Flux Certificates is active but has no active template.', 'flx-donate' ) ) ) : array( 'warn', __( 'Flux Certificates is not active: no certificate is made after payment.', 'flx-donate' ) );
		$out[] = $page && 'publish' === get_post_status( $page ) ? array( 'ok', __( 'Donation page:', 'flx-donate' ) . ' ' . get_the_title( $page ) ) : array( 'warn', __( 'The donation options are not on a page yet.', 'flx-donate' ) );
		return $out;
	}

	public static function settings() {
		if ( ! FLXB_WC::ready() ) {
			echo '<section class="flxb-card"><p>' . esc_html__( 'WooCommerce is not active. Donations are paid through WooCommerce from version 2.0: install and activate WooCommerce.', 'flx-donate' ) . '</p></section>';
			return;
		}
		$s      = FLXB_WC::settings();
		$flxs    = FLXB_WC::certificates_available();
		$tpls   = $flxs && function_exists( 'flxs_list_templates' ) ? flxs_list_templates( true ) : array();
		$events = $flxs && function_exists( 'flxs_get_events' ) ? flxs_get_events( 'open' ) : array();
		$chans  = array( 'whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => __( 'E-mail', 'flx-donate' ) );
		$page   = (int) $s['page_id'];

		echo '<div class="flxb-grid flxb-grid--2-1"><div class="flxb-stack">';
		echo '<form class="flxb-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxb_wc_save">';
		wp_nonce_field( 'flxb_wc_save' );

		echo '<section class="flxb-card"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Certificate', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'Sapling packages, prices and badges are edited on the donation type (Donations › Donation types). Which types give a certificate is set there too.', 'flx-donate' ) . '</p></div></div>';
		echo '<div class="flxb-field"><label for="flxbwc-tpl">' . esc_html__( 'Template', 'flx-donate' ) . '</label><select id="flxbwc-tpl" name="template_id"><option value="0">' . esc_html__( 'Automatic ("Fidan Bağış Sertifikası")', 'flx-donate' ) . '</option>';
		foreach ( $tpls as $t ) {
			echo '<option value="' . (int) $t['id'] . '"' . selected( (int) $s['template_id'], (int) $t['id'], false ) . '>' . esc_html( $t['name'] ) . '</option>';
		}
		echo '</select></div>';
		echo '<div class="flxb-field"><label for="flxbwc-ev">' . esc_html__( 'Event', 'flx-donate' ) . '</label><select id="flxbwc-ev" name="event_id"><option value="0">' . esc_html__( 'None', 'flx-donate' ) . '</option>';
		foreach ( $events as $e ) {
			echo '<option value="' . (int) $e['id'] . '"' . selected( (int) $s['event_id'], (int) $e['id'], false ) . '>' . esc_html( $e['name'] ?? ( $e['title'] ?? '#' . $e['id'] ) ) . '</option>';
		}
		echo '</select><p class="flxb-help">' . esc_html__( 'When chosen, online donation certificates count for this open event.', 'flx-donate' ) . '</p></div>';
		echo '<fieldset class="flxb-field"><legend class="flxb-label">' . esc_html__( 'Send by', 'flx-donate' ) . '</legend><div class="flxb-checks">';
		foreach ( $chans as $k => $l ) {
			echo self::box( 'cert_channels[]', in_array( $k, (array) $s['cert_channels'], true ), $l, $k ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div><p class="flxb-help">' . esc_html__( 'When the payment is confirmed the certificate link goes out on these channels. With a WhatsApp route no SMS is sent.', 'flx-donate' ) . '</p></fieldset>';
		echo '<div class="flxb-field"><label for="flxbwc-print">' . esc_html__( '"Online + printed certificate"', 'flx-donate' ) . '</label><select id="flxbwc-print" name="print_mode">';
		foreach ( array( 'soon' => __( 'Shown as "Coming soon" (cannot be chosen)', 'flx-donate' ), 'on' => __( 'Can be chosen', 'flx-donate' ), 'off' => __( 'Hidden', 'flx-donate' ) ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( (string) $s['print_mode'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></div>';
		echo '<div class="flxb-field"><label for="flxbwc-fee">' . esc_html__( 'Printed certificate fee (per line)', 'flx-donate' ) . '</label><input type="text" inputmode="decimal" id="flxbwc-fee" name="print_fee" value="' . esc_attr( wc_format_localized_price( $s['print_fee'] ) ) . '"><p class="flxb-help">' . esc_html__( '0 = free. Orders with a printed certificate stay "Processing"; set them to "Completed" when sent.', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-field">' . self::check( 'auto_complete', (bool) $s['auto_complete'], __( 'Complete paid orders with an online certificate automatically', 'flx-donate' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</section>';

		echo '<section class="flxb-card"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Bank transfer', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'IBAN, recipient and reference code: WooCommerce › Settings › Payments › Bank transfer (donations). The transfer details and the receipt link are added to WooCommerce\'s "on hold" e-mail.', 'flx-donate' ) . '</p></div></div>';
		echo '<fieldset class="flxb-field"><legend class="flxb-label">' . esc_html__( 'Short notification', 'flx-donate' ) . '</legend><div class="flxb-checks">' . self::box( 'notify_channels[]', in_array( 'whatsapp', (array) $s['notify_channels'], true ), 'WhatsApp', 'whatsapp' ) . self::box( 'notify_channels[]', in_array( 'sms', (array) $s['notify_channels'], true ), 'SMS', 'sms' ) . '</div><p class="flxb-help">' . esc_html__( 'A short message to the donor\'s phone when a transfer is registered (through Flux Mail, or Flux Certificates\'s providers). With both ticked, SMS only goes out when there is no WhatsApp route.', 'flx-donate' ) . '</p></fieldset>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="flxb-field"><label for="flxbwc-wa">' . esc_html__( 'WhatsApp text', 'flx-donate' ) . '</label><textarea id="flxbwc-wa" name="notify_wa" rows="9">' . esc_textarea( FLXB_WC::notify_text( 'whatsapp' ) ) . '</textarea><p class="flxb-help">' . esc_html__( 'Placeholders: {ad} {tutar} {siparis} {alici} {iban} {aciklama} {link} {site}', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-field"><label for="flxbwc-sms">' . esc_html__( 'SMS text', 'flx-donate' ) . '</label><textarea id="flxbwc-sms" name="notify_sms" rows="3">' . esc_textarea( FLXB_WC::notify_text( 'sms' ) ) . '</textarea></div>';
		echo '<div class="flxb-fields-2"><div class="flxb-field"><label for="flxbwc-mb">' . esc_html__( 'Largest receipt file (MB)', 'flx-donate' ) . '</label><input type="number" id="flxbwc-mb" name="receipt_max_mb" min="1" max="50" value="' . (int) $s['receipt_max_mb'] . '"></div>';
		echo '<div class="flxb-field"><label for="flxbwc-rn">' . esc_html__( 'Tell this address when a receipt is uploaded', 'flx-donate' ) . '</label><input type="email" id="flxbwc-rn" name="receipt_notify" value="' . esc_attr( $s['receipt_notify'] ) . '" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '"></div></div>';
		echo '</section>';

		echo '<section class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Cart and checkout', 'flx-donate' ) . '</h2></div>';
		echo '<div class="flxb-field"><label for="flxbwc-after">' . esc_html__( 'After "Donate"', 'flx-donate' ) . '</label><select id="flxbwc-after" name="after_add">';
		foreach ( array( 'checkout' => __( 'Go straight to the checkout', 'flx-donate' ), 'cart' => __( 'Go to the cart', 'flx-donate' ), 'stay' => __( 'Stay on the page', 'flx-donate' ) ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['after_add'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></div>';
		echo '<div class="flxb-field">' . self::check( 'simple_address', (bool) $s['simple_address'], __( 'Do not ask for an address at the checkout when the cart holds only donations', 'flx-donate' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="flxb-field"><label for="flxbwc-support">' . esc_html__( 'Support WhatsApp number', 'flx-donate' ) . '</label><input type="tel" id="flxbwc-support" name="support_wa" value="' . esc_attr( $s['support_wa'] ) . '" placeholder="+90 530 000 00 00"><p class="flxb-help">' . esc_html__( 'Shown at the checkout and with the bank transfer details as "Any trouble? Write to us on WhatsApp". Leave empty to hide.', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-field"><label for="page_id">' . esc_html__( 'Donation page', 'flx-donate' ) . '</label>';
		wp_dropdown_pages( array( 'name' => 'page_id', 'id' => 'page_id', 'selected' => $page, 'show_option_none' => __( '— Choose —', 'flx-donate' ), 'option_none_value' => 0 ) );
		echo '<p class="flxb-help">' . esc_html__( '"Donate" links and the hidden product pages go here.', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-field">' . self::check( 'forms_checkout', (bool) $s['forms_checkout'], __( 'Flux Forms payment forms continue on the WooCommerce checkout', 'flx-donate' ) ) . '<p class="flxb-help">' . esc_html__( 'The donor picks card or bank transfer there; the form entry, the certificate automation and the donation record are completed when the order is paid.', 'flx-donate' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="flxb-fields-2"><div class="flxb-field"><label for="flxbwc-card">' . esc_html__( 'Card payment screen', 'flx-donate' ) . '</label><select id="flxbwc-card" name="card_ui">';
		foreach ( array( 'fields' => __( 'Our own card fields on the checkout (3D Secure, installments, saved cards)', 'flx-donate' ), 'form' => __( 'iyzico payment page (as before)', 'flx-donate' ), 'official' => __( 'Official iyzico plugin for one-time donations', 'flx-donate' ) ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( FLXB_WC_Iyzico::ui(), $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select><p class="flxb-help">' . esc_html__( 'Card numbers go straight to iyzico and are never stored on this site. Monthly donations always use Flux Donate iyzico (it stores the card).', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-field"><label for="flxbwc-inst">' . esc_html__( 'Most installments', 'flx-donate' ) . '</label><select id="flxbwc-inst" name="card_installments">';
		foreach ( array( 1, 2, 3, 6, 9, 12 ) as $n ) {
			echo '<option value="' . (int) $n . '"' . selected( (int) $s['card_installments'], $n, false ) . '>' . esc_html( 1 === $n ? __( 'Single payment only', 'flx-donate' ) : number_format_i18n( $n ) ) . '</option>';
		}
		echo '</select><p class="flxb-help">' . esc_html__( 'With our own card fields. The options and any interest come from iyzico for each card.', 'flx-donate' ) . '</p></div></div>';
		echo '</section>';
		echo '<div class="flxb-formactions"><button type="submit" class="flxb-btn flxb-btn--primary">' . esc_html__( 'Save', 'flx-donate' ) . '</button></div></form></div>';

		/* Side */
		echo '<div class="flxb-stack"><section class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Status', 'flx-donate' ) . '</h2></div><ul class="flxb-list">';
		foreach ( self::status() as $r ) {
			echo '<li><span class="flxb-pill flxb-pill--' . ( 'ok' === $r[0] ? 'completed' : 'pending' ) . '">' . ( 'ok' === $r[0] ? '✓' : '!' ) . '</span> ' . esc_html( $r[1] ) . '</li>';
		}
		echo '</ul>';
		if ( ! $page || 'publish' !== get_post_status( $page ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxb_wc_setup_page">';
			wp_nonce_field( 'flxb_wc_setup_page' );
			echo '<p class="flxb-help">' . esc_html__( 'Add the "Donation options" block to any page, or create a ready page:', 'flx-donate' ) . '</p><button class="flxb-btn flxb-btn--sm">' . esc_html__( 'Create the online donation page', 'flx-donate' ) . '</button></form>';
		}
		echo '</section>';
		echo '<section class="flxb-card"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Product list (XML)', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'For iyzico "upload your products with XML" and Google Merchant Center. Every fixed-price option on sale is one product; free amounts are left out. Updating daily is enough.', 'flx-donate' ) . '</p></div></div>';
		echo FLXB_Admin::copy_field( 'flxbwc-feed', __( 'Address', 'flx-donate' ), FLXB_WC_Feed::url() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</section>';
		echo '<section class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Shortcode and block', 'flx-donate' ) . '</h2></div><p><code>[flx_bagis_secenekleri]</code></p><p class="flxb-help">' . esc_html__( 'Without attributes it shows the donation types marked "Show in the Donation options block". tur="slug1,slug2" shows only those types; sekme="slug" opens that type\'s tab first. In the editor: "Donation options" block.', 'flx-donate' ) . '</p></section>';
		echo '</div></div>';
	}

	public static function save() {
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_wc_save' );
		$in   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised below
		$chan = static function ( $key, array $allowed ) use ( $in ) {
			return array_values( array_intersect( array_map( 'sanitize_key', (array) ( $in[ $key ] ?? array() ) ), $allowed ) );
		};
		$s = array(
			'template_id'     => absint( $in['template_id'] ?? 0 ),
			'event_id'        => absint( $in['event_id'] ?? 0 ),
			'cert_channels'   => $chan( 'cert_channels', array( 'whatsapp', 'sms', 'email' ) ),
			'print_mode'      => in_array( (string) ( $in['print_mode'] ?? '' ), array( 'soon', 'on', 'off' ), true ) ? (string) $in['print_mode'] : 'soon',
			'print_fee'       => max( 0, round( FLXB_WC::parse_amount( $in['print_fee'] ?? 0 ), 2 ) ),
			'auto_complete'   => empty( $in['auto_complete'] ) ? 0 : 1,
			'simple_address'  => empty( $in['simple_address'] ) ? 0 : 1,
			'after_add'       => in_array( $in['after_add'] ?? '', array( 'cart', 'checkout', 'stay' ), true ) ? $in['after_add'] : 'checkout',
			'support_wa'      => trim( preg_replace( '/[^0-9+\s()-]/', '', (string) ( $in['support_wa'] ?? '' ) ) ),
			'page_id'         => absint( $in['page_id'] ?? 0 ),
			'notify_channels' => $chan( 'notify_channels', array( 'whatsapp', 'sms' ) ),
			'notify_wa'       => trim( sanitize_textarea_field( (string) ( $in['notify_wa'] ?? '' ) ) ),
			'notify_sms'      => trim( sanitize_textarea_field( (string) ( $in['notify_sms'] ?? '' ) ) ),
			'receipt_max_mb'  => min( 50, max( 1, absint( $in['receipt_max_mb'] ?? 8 ) ) ),
			'receipt_notify'  => sanitize_email( (string) ( $in['receipt_notify'] ?? '' ) ),
			'forms_checkout'  => empty( $in['forms_checkout'] ) ? 0 : 1,
			'card_ui'         => in_array( $in['card_ui'] ?? '', array( 'fields', 'form', 'official' ), true ) ? $in['card_ui'] : 'fields',
			'card_installments' => min( 12, max( 1, absint( $in['card_installments'] ?? 12 ) ) ),
		);
		/* Same as the default text: keep it empty so a language change shows the translation. */
		foreach ( array( 'notify_wa' => 'whatsapp', 'notify_sms' => 'sms' ) as $k => $ch ) {
			if ( str_replace( "\r", '', $s[ $k ] ) === str_replace( "\r", '', FLXB_WC::default_notify_text( $ch ) ) ) {
				$s[ $k ] = '';
			}
		}
		FLXB_WC::save_settings( $s );
		wp_safe_redirect( self::url( array( 'flxb_msg' => 'saved' ) ) );
		exit;
	}

	public static function setup_page() {
		if ( ! flxb_can_manage() || ! current_user_can( 'publish_pages' ) ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_wc_setup_page' );
		FLXB_WC_Products::sync_all();
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Online donation', 'flx-donate' ),
				'post_name'    => 'online-bagis',
				'post_content' => "<!-- wp:paragraph -->\n<p>" . esc_html__( 'Choose a sapling package or give any amount you like. You can pay by card or bank transfer; a certificate is made in your name.', 'flx-donate' ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:flx-wc-bagis/secenekler /-->",
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			FLXB_WC::save_settings( array( 'page_id' => (int) $id ) );
		}
		wp_safe_redirect( self::url( array( 'flxb_msg' => 'saved' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Donation type card
	 * ------------------------------------------------------------------ */

	public static function type_card( $t ) {
		if ( ! is_array( $t ) ) {
			return;
		}
		$m       = (array) ( $t['meta'] ?? array() );
		$tpls    = FLXB_WC::certificates_available() && function_exists( 'flxs_list_templates' ) ? flxs_list_templates( true ) : array();
		$pid     = (int) ( $m['wc_product_id'] ?? 0 );
		$presets = implode( ' ', array_map( static function ( $v ) {
			return wc_format_localized_price( $v );
		}, (array) ( $m['presets'] ?? array() ) ) );
		echo '<section class="flxb-card"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Online donation page', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'How this type appears in the "Donation options" block and the WooCommerce cart. Its hidden WooCommerce product is kept in step automatically.', 'flx-donate' ) . '</p></div></div><div class="flxb-form">';
		echo '<input type="hidden" name="meta[in_form]" value="0"><label class="flxb-switch"><input type="checkbox" name="meta[in_form]" value="1"' . checked( ! empty( $m['in_form'] ), true, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html__( 'Show in the "Donation options" block (donation page) and the product XML', 'flx-donate' ) . '</label>';
		echo '<input type="hidden" name="meta[certificate]" value="0"><label class="flxb-switch"><input type="checkbox" name="meta[certificate]" value="1"' . checked( ! empty( $m['certificate'] ), true, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html__( 'Give a certificate (Flux Certificates)', 'flx-donate' ) . '</label>';
		echo '<div class="flxb-field"><label for="flxb-ctpl">' . esc_html__( 'Certificate template', 'flx-donate' ) . '</label><select id="flxb-ctpl" name="meta[cert_template]"><option value="0">' . esc_html__( 'Same as the WooCommerce settings', 'flx-donate' ) . '</option>';
		foreach ( $tpls as $tp ) {
			echo '<option value="' . (int) $tp['id'] . '"' . selected( (int) ( $m['cert_template'] ?? 0 ), (int) $tp['id'], false ) . '>' . esc_html( $tp['name'] ) . '</option>';
		}
		echo '</select></div>';
		echo '<div class="flxb-field"><label for="flxb-custom">' . esc_html__( 'Option with a − / + quantity', 'flx-donate' ) . '</label><select id="flxb-custom" name="meta[custom_option]"><option value="">' . esc_html__( 'None', 'flx-donate' ) . '</option>';
		foreach ( (array) $t['options'] as $o ) {
			echo '<option value="' . esc_attr( $o['key'] ) . '"' . selected( (string) ( $m['custom_option'] ?? '' ), $o['key'], false ) . '>' . esc_html( $o['label'] ) . '</option>';
		}
		echo '</select><p class="flxb-help">' . esc_html__( 'This option\'s card gets − / + buttons (e.g. "1 sapling"); price = number × this option\'s price, checked again on the server. The other options stay fixed packages. Use "Maximum quantity" above for the limit.', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-field"><label for="flxb-presets">' . esc_html__( 'Preset amounts (free amount)', 'flx-donate' ) . '</label><input type="text" id="flxb-presets" name="meta[presets]" value="' . esc_attr( $presets ) . '"><p class="flxb-help">' . esc_html__( 'Separated by spaces, e.g. 250 500 1000.', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-field"><label for="flxb-purl">' . esc_html__( 'Page address', 'flx-donate' ) . '</label><input type="url" id="flxb-purl" name="meta[page_url]" value="' . esc_attr( FLXB_WC::type_page_url( $t ) ) . '" placeholder="' . esc_attr( FLXB_WC::page_url() ) . '"><p class="flxb-help">' . esc_html__( 'Optional: where its product page and the product XML link go. Empty = the donation page in the WooCommerce settings.', 'flx-donate' ) . '</p></div>';
		if ( $pid && get_post( $pid ) ) {
			/* translators: %d: product id */
			echo '<p class="flxb-help">' . esc_html( sprintf( __( 'WooCommerce product #%d (hidden from the shop).', 'flx-donate' ), $pid ) ) . ' <a href="' . esc_url( get_edit_post_link( $pid ) ) . '">' . esc_html__( 'Open', 'flx-donate' ) . '</a></p>';
		}
		echo '</div></section>';
	}

	/* ------------------------------------------------------------------
	 * Order screen
	 * ------------------------------------------------------------------ */

	public static function meta_boxes() {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		foreach ( array_unique( $screens ) as $sc ) {
			add_meta_box( 'flxwb-box', __( 'Donation', 'flx-donate' ), array( __CLASS__, 'order_box' ), $sc, 'side', 'high' );
		}
	}

	public static function order_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		$lines  = FLXB_WC_Cart::order_lines( $order );
		$havale = FLXB_WC_Havale::ID === $order->get_payment_method();
		if ( ! $lines && ! $havale ) {
			echo '<p>' . esc_html__( 'This order has no donation line.', 'flx-donate' ) . '</p>';
			return;
		}
		echo '<div class="flxwb-box">';
		$n = FLXB_WC_Cart::order_units( $order );
		if ( $n ) {
			echo '<p><strong>' . esc_html( FLXB_WC::units_text( $n, 'fidan' ) ) . '</strong></p>';
		}
		$ids = array_filter( array_map( 'intval', (array) $order->get_meta( '_flxb_donation_ids' ) ) );
		if ( $ids ) {
			echo '<p>' . esc_html__( 'Donation records:', 'flx-donate' ) . ' ';
			$links = array();
			foreach ( $ids as $did ) {
				$links[] = '<a href="' . esc_url( FLXB_Admin_Url::donation( $did ) ) . '">#' . (int) $did . '</a>';
			}
			echo implode( ', ', $links ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( $havale ) {
			echo '<p>' . esc_html__( 'Reference code:', 'flx-donate' ) . '<br><code>' . esc_html( FLXB_WC_Havale::reference( $order ) ) . '</code></p>';
			$rec = FLXB_WC_Receipts::of( $order );
			echo '<p><strong>' . esc_html__( 'Receipts', 'flx-donate' ) . '</strong></p>';
			if ( $rec ) {
				echo '<ul class="flxwb-box__files">';
				foreach ( $rec as $i => $r ) {
					printf(
						'<li><a href="%1$s" target="_blank" rel="noopener">%2$s</a><br><small>%3$s · %4$s</small>%5$s</li>',
						esc_url( FLXB_WC_Receipts::url( $order, $i, true ) ),
						esc_html( $r['name'] ),
						esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $r['at'] ) ),
						esc_html( size_format( (int) $r['size'] ) ),
						empty( $r['dup'] ) ? '' : '<br><strong style="color:#b32d2e">' . esc_html( FLXB_WC_Receipts::dup_text( $r ) ) . '</strong>'
					);
				}
				echo '</ul>';
			} else {
				echo '<p class="description">' . esc_html__( 'No receipt uploaded yet.', 'flx-donate' ) . '</p>';
			}
			if ( FLXB_WC_Havale::awaiting( $order ) ) {
				$url = wp_nonce_url( admin_url( 'admin-post.php?action=flxb_approve&order=' . $order->get_id() ), 'flxb_approve_' . $order->get_id() );
				echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '" onclick="return confirm(\'' . esc_js( __( 'Has the payment reached the bank? When you confirm, the certificate is made and sent to the donor.', 'flx-donate' ) ) . '\')">' . esc_html__( 'Confirm payment', 'flx-donate' ) . '</a></p>';
			}
		}
		$certs = FLXB_WC_Certificates::of( $order );
		if ( $certs ) {
			echo '<p><strong>' . esc_html__( 'Certificates', 'flx-donate' ) . '</strong></p><ul>';
			foreach ( $certs as $c ) {
				echo '<li><a href="' . esc_url( function_exists( 'flxs_certificate_url' ) ? flxs_certificate_url( $c ) : '#' ) . '" target="_blank">' . esc_html( (string) ( $c['number'] ?? '' ) ) . '</a> · ' . esc_html( function_exists( 'flxs_certificate_name' ) ? flxs_certificate_name( $c ) : '' ) . '</li>';
			}
			echo '</ul>';
		} elseif ( FLXB_WC_Certificates::order_paid( $order ) && FLXB_WC_Certificates::expects( $order ) ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=flxb_issue&order=' . $order->get_id() ), 'flxb_issue_' . $order->get_id() );
			echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Make the certificate', 'flx-donate' ) . '</a></p>';
		}
		echo '</div>';
	}

	private static function order_from_request( $action ) {
		$id = absint( $_GET['order'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked next
		check_admin_referer( $action . '_' . $id );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-donate' ) );
		}
		return wc_get_order( $id );
	}

	public static function approve() {
		$order = self::order_from_request( 'flxb_approve' );
		if ( $order && FLXB_WC_Havale::awaiting( $order ) ) {
			$order->update_meta_data( '_flxb_state', 'approved' );
			$order->save_meta_data();
			/* translators: %s: user */
			$order->add_order_note( sprintf( __( 'Bank transfer confirmed (%s).', 'flx-donate' ), wp_get_current_user()->display_name ) );
			$order->payment_complete();
		}
		$back = absint( $_GET['back'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked in order_from_request()
		if ( $back ) {
			wp_safe_redirect( FLXB_Admin_Url::donation( $back ) );
			exit;
		}
		wp_safe_redirect( $order ? $order->get_edit_order_url() : admin_url() );
		exit;
	}

	public static function issue() {
		$order = self::order_from_request( 'flxb_issue' );
		if ( $order ) {
			delete_transient( 'flxb_cert_lock_' . $order->get_id() );
			FLXB_WC_Certificates::issue( $order );
		}
		wp_safe_redirect( $order ? $order->get_edit_order_url() : admin_url() );
		exit;
	}

	public static function column( $cols ) {
		$out = array();
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'order_status' === $k ) {
				$out['flxb_receipt'] = __( 'Receipt', 'flx-donate' );
			}
		}
		return $out;
	}

	public static function column_value( $col, $order ) {
		if ( 'flxb_receipt' !== $col ) {
			return;
		}
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order || FLXB_WC_Havale::ID !== $order->get_payment_method() ) {
			echo '–';
			return;
		}
		$n = count( FLXB_WC_Receipts::of( $order ) );
		if ( $n && FLXB_WC_Havale::awaiting( $order ) ) {
			echo '<mark class="order-status status-processing"><span>' . esc_html__( 'Check', 'flx-donate' ) . '</span></mark>';
		} elseif ( $n ) {
			echo esc_html( number_format_i18n( $n ) );
		} else {
			echo FLXB_WC_Havale::awaiting( $order ) ? esc_html__( 'Waiting', 'flx-donate' ) : '–';
		}
	}

	/* ------------------------------------------------------------------
	 * Donation detail: refunds through WooCommerce
	 * ------------------------------------------------------------------ */

	/**
	 * Actions of a donation paid through WooCommerce (order link, refund form).
	 *
	 * @return bool True when the donation belongs to an order (the 1.x money form is not shown).
	 */
	public static function donation_actions( array $d ) {
		$oid   = FLXB_WC_Ledger::order_of( $d );
		$order = $oid ? wc_get_order( $oid ) : null;
		if ( ! $order ) {
			return false;
		}
		$item_id = FLXB_WC_Ledger::item_of( $d );
		$item    = $item_id ? $order->get_item( $item_id ) : null;
		echo '<div class="flxb-formactions">';
		/* translators: %s: order number */
		echo '<a class="flxb-btn flxb-btn--sm" href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html( sprintf( __( 'WooCommerce order #%s', 'flx-donate' ), $order->get_order_number() ) ) . '</a>';
		if ( $item && $order->is_paid() && current_user_can( 'edit_shop_orders' ) ) {
			$paid = FLXB_WC_Ledger::line_amount( $item );
			$left = round( $paid - abs( (float) $order->get_total_refunded_for_item( $item_id ) ) - abs( (float) $order->get_tax_refunded_for_item( $item_id, 0 ) ), 2 );
			if ( $left > 0 ) {
				$gw   = wc_get_payment_gateway_by_order( $order );
				$auto = $gw && $gw->supports( 'refunds' );
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxb-inline-form" onsubmit="return confirm(' . esc_attr( wp_json_encode( $auto ? __( 'Refund this amount to the donor\'s card?', 'flx-donate' ) : __( 'Record this refund? The money is not sent back automatically for this payment method.', 'flx-donate' ) ) ) . ')">';
				echo '<input type="hidden" name="action" value="flxb_wc_refund"><input type="hidden" name="donation" value="' . (int) $d['id'] . '">';
				wp_nonce_field( 'flxb_wc_refund_' . (int) $d['id'] );
				echo '<label class="screen-reader-text" for="flxb-wcr">' . esc_html__( 'Amount', 'flx-donate' ) . '</label><input id="flxb-wcr" type="text" inputmode="decimal" name="amount" size="8" value="' . esc_attr( wc_format_localized_price( $left ) ) . '"> ';
				echo '<button class="flxb-btn flxb-btn--sm flxb-btn--danger">' . esc_html( $auto ? __( 'Refund', 'flx-donate' ) : __( 'Record refund', 'flx-donate' ) ) . '</button>';
				echo '<p class="flxb-help">' . esc_html( $auto ? __( 'Refunded through WooCommerce and the payment method; the record follows.', 'flx-donate' ) : __( 'Recorded in WooCommerce only: send the money back yourself (e.g. by bank transfer).', 'flx-donate' ) ) . '</p></form>';
			}
		}
		echo '</div>';
		return true;
	}

	public static function refund() {
		$id = absint( $_POST['donation'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked next
		check_admin_referer( 'flxb_wc_refund_' . $id );
		if ( ! flxb_can_manage() || ! current_user_can( 'edit_shop_orders' ) ) {
			FLXB_Admin::deny();
		}
		$d       = FLXB_Donations::get( $id );
		$oid     = $d ? FLXB_WC_Ledger::order_of( $d ) : 0;
		$order   = $oid ? wc_get_order( $oid ) : null;
		$item_id = $d ? FLXB_WC_Ledger::item_of( $d ) : 0;
		$back    = FLXB_Admin_Url::donation( $id );
		$res     = $order && $item_id ? self::refund_line( $order, $item_id, FLXB_WC::parse_amount( wp_unslash( $_POST['amount'] ?? '' ) ) ) : new WP_Error( 'flxb_wc', __( 'Order not found.', 'flx-donate' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed as a number
		if ( is_wp_error( $res ) ) {
			wp_safe_redirect( add_query_arg( array( 'flxb_msg' => 'error', 'flxb_err' => rawurlencode( $res->get_error_message() ) ), $back ) );
		} else {
			wp_safe_redirect( add_query_arg( 'flxb_msg', 'refunded', $back ) );
		}
		exit;
	}

	/**
	 * Refund part or all of one donation line through WooCommerce.
	 *
	 * @return WC_Order_Refund|WP_Error
	 */
	public static function refund_line( WC_Order $order, $item_id, $amount ) {
		$item = $order->get_item( $item_id );
		if ( ! $item ) {
			return new WP_Error( 'flxb_wc', __( 'Order line not found.', 'flx-donate' ) );
		}
		$paid   = FLXB_WC_Ledger::line_amount( $item );
		$left   = round( $paid - abs( (float) $order->get_total_refunded_for_item( $item_id ) ) - abs( (float) $order->get_tax_refunded_for_item( $item_id, 0 ) ), 2 );
		$amount = round( (float) $amount, 2 );
		if ( $amount <= 0 || $amount > $left + 0.004 ) {
			return new WP_Error( 'flxb_wc', __( 'Enter an amount up to what is left to refund.', 'flx-donate' ) );
		}
		$gw   = wc_get_payment_gateway_by_order( $order );
		$auto = $gw && $gw->supports( 'refunds' );
		$tax  = (float) $item->get_total_tax();
		$r    = wc_create_refund(
			array(
				'order_id'       => $order->get_id(),
				'amount'         => $amount,
				/* translators: %s: user */
				'reason'         => sprintf( __( 'Refunded from Flux Donate by %s', 'flx-donate' ), wp_get_current_user()->display_name ),
				'refund_payment' => $auto,
				'restock_items'  => false,
				'line_items'     => array(
					$item_id => array(
						'qty'          => 0,
						'refund_total' => $tax > 0 ? round( $amount * ( (float) $item->get_total() / $paid ), 2 ) : $amount,
						'refund_tax'   => array(),
					),
				),
			)
		);
		if ( ! is_wp_error( $r ) ) {
			FLXB_WC_Ledger::sync( wc_get_order( $order->get_id() ) );
		}
		return $r;
	}
}
