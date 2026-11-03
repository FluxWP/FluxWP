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
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-commerce' ), 403 );
		}
		FLXCM_Admin::open( __( 'Ayarlar', 'flx-commerce' ), __( 'Panel, ödeme sayfası, Havale/EFT, ürün feed\'i ve bildirimler.', 'flx-commerce' ) );
		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Ayarlar kaydedildi.', 'flx-commerce' ) . '</p></div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxcm-settings">';
		wp_nonce_field( 'flxcm_save' );
		echo '<input type="hidden" name="action" value="flxcm_save">';

		// Panel ve raporlar.
		echo '<section class="flxcm-card"><h2>' . flxcm_icon( 'trend', 18 ) . ' ' . esc_html__( 'Panel ve raporlar', 'flx-commerce' ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<fieldset class="flxcm-field"><legend class="flxcm-field__label">' . esc_html__( 'Ciroya sayılan sipariş durumları', 'flx-commerce' ) . '</legend><div class="flxcm-checks">';
		$paid = flxcm_paid_statuses();
		foreach ( wc_get_order_statuses() as $k => $label ) {
			$k = substr( $k, 3 );
			if ( in_array( $k, array( 'pending', 'cancelled', 'failed', 'checkout-draft', 'refunded' ), true ) ) {
				continue;
			}
			echo '<label><input type="checkbox" name="paid_statuses[]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, $paid, true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</div><span class="flxcm-field__hint">' . esc_html__( 'Beklemede (havale) siparişler ödeme onaylanınca sayılır.', 'flx-commerce' ) . '</span></fieldset>';
		if ( class_exists( 'FLXB_WC_Cart' ) ) {
			self::toggle( 'exclude_donations', __( 'Bağış siparişlerini mağaza istatistiklerine katma', 'flx-commerce' ), __( 'Flux Donate kurulu: yalnız bağış içeren siparişler Bağışlar panelinde kalır.', 'flx-commerce' ) );
		}
		self::input( 'low_stock', __( 'Düşük stok eşiği', 'flx-commerce' ), 'number', __( 'Stoğu bu sayı ya da altına inen ürünler Genel bakış\'ta listelenir.', 'flx-commerce' ), 'min="0" max="9999" class="small-text"' );
		echo '</section>';

		// Ödeme sayfası.
		echo '<section class="flxcm-card"><h2>' . flxcm_icon( 'cart', 18 ) . ' ' . esc_html__( 'Sepet ve ödeme sayfası', 'flx-commerce' ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		self::toggle( 'hide_company', __( 'Şirket adı alanını gizle', 'flx-commerce' ) );
		self::toggle( 'hide_address_2', __( 'Adres 2. satırını gizle', 'flx-commerce' ) );
		self::toggle( 'virtual_no_address', __( 'Yalnız sanal/indirilebilir ürün varsa adres sorma', 'flx-commerce' ), __( 'Klasik ödeme sayfasında. Ülke, ad, e-posta ve telefon kalır.', 'flx-commerce' ) );
		self::toggle( 'order_notes', __( 'Sipariş notu alanı', 'flx-commerce' ) );
		self::toggle( 'sticky_pay', __( 'Mobilde "Siparişi ver" düğmesi altta sabit', 'flx-commerce' ) );
		self::toggle( 'free_ship_bar', __( 'Ücretsiz kargo çubuğu', 'flx-commerce' ), __( 'Sepette ve ödemede "X ₺ daha ekleyin, kargo bedava".', 'flx-commerce' ) );
		$auto = FLXCM_Checkout::free_threshold();
		self::input(
			'free_ship_amount',
			__( 'Ücretsiz kargo tutarı', 'flx-commerce' ),
			'number',
			(float) flxcm_setting( 'free_ship_amount' ) > 0 || $auto <= 0
				? __( 'Boş ya da 0 = WooCommerce "Ücretsiz kargo" yönteminin en az tutarı.', 'flx-commerce' )
				/* translators: %s: amount */
				: sprintf( __( 'Boş = WooCommerce "Ücretsiz kargo" yönteminden: %s', 'flx-commerce' ), flxcm_money( $auto ) ),
			'min="0" step="0.01" class="regular-text"'
		);
		echo '</section>';

		// Havale/EFT.
		$gw_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . FLXCM_Bank::ID );
		echo '<section class="flxcm-card"><h2>' . flxcm_icon( 'bank', 18 ) . ' ' . esc_html__( 'Havale/EFT', 'flx-commerce' ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="flxcm-muted">' . esc_html__( 'Yöntemi açmak, IBAN ve açıklama biçimi WooCommerce › Ayarlar › Ödemeler › Havale/EFT\'de.', 'flx-commerce' ) . ' <a href="' . esc_url( $gw_url ) . '">' . esc_html__( 'Ödeme yöntemini aç', 'flx-commerce' ) . '</a></p>';
		if ( class_exists( 'FLXB_WC_Havale' ) ) {
			self::toggle( 'defer_to_donate', __( 'Flux Donate\'in havale yöntemi açıksa bunu gösterme', 'flx-commerce' ), __( 'Ödeme sayfasında iki ayrı "Havale" seçeneği görünmesin.', 'flx-commerce' ) );
		}
		self::input( 'receipt_max_mb', __( 'En büyük dekont (MB)', 'flx-commerce' ), 'number', '', 'min="1" max="20" class="small-text"' );
		self::input( 'receipt_notify', __( 'Dekont bildirimi e-postası', 'flx-commerce' ), 'email', __( 'Boş = site yöneticisinin e-postası.', 'flx-commerce' ), 'class="regular-text"' );
		self::input( 'cancel_after_days', __( 'Ödenmeyen havaleyi iptal et (gün)', 'flx-commerce' ), 'number', __( '0 = iptal etme. Dekont yüklenmiş siparişler iptal edilmez; stok geri döner.', 'flx-commerce' ), 'min="0" max="60" class="small-text"' );
		echo '</section>';

		// Feed.
		echo '<section class="flxcm-card"><h2>' . flxcm_icon( 'rss', 18 ) . ' ' . esc_html__( 'Ürün feed\'i (Google Merchant)', 'flx-commerce' ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		self::toggle( 'feed', __( 'Feed açık', 'flx-commerce' ), __( 'Google Merchant Center, Meta katalog ve benzerleri bu adresi okur.', 'flx-commerce' ) );
		if ( flxcm_setting( 'feed' ) ) {
			$url = FLXCM_Feed::url();
			echo '<div class="flxcm-field"><span class="flxcm-field__label">' . esc_html__( 'Feed adresi', 'flx-commerce' ) . '</span><span class="flxcm-copyline"><input type="text" readonly value="' . esc_attr( $url ) . '" class="large-text" onfocus="this.select()"><button type="button" class="button" data-flxcm-copy="' . esc_attr( $url ) . '">' . flxcm_icon( 'copy', 15 ) . ' ' . esc_html__( 'Kopyala', 'flx-commerce' ) . '</button></span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		self::input( 'feed_brand', __( 'Marka', 'flx-commerce' ), 'text', __( 'Boş = site adı.', 'flx-commerce' ), 'class="regular-text"' );
		echo '</section>';

		// Bildirimler.
		$wa = FLXCM_Notify::whatsapp_ready();
		echo '<section class="flxcm-card"><h2>' . flxcm_icon( 'alert', 18 ) . ' ' . esc_html__( 'Bildirimler', 'flx-commerce' ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="flxcm-muted">' . ( $wa ? esc_html__( 'WhatsApp mesajları Flux Mail\'in WhatsApp bağlantısıyla gider.', 'flx-commerce' ) : esc_html__( 'WhatsApp için Flux Mail ve bir WhatsApp bağlantısı gerekir; şu an yalnız e-posta gider.', 'flx-commerce' ) ) . '</p>';
		self::toggle( 'notify_customer_wa', __( 'Havale bilgilerini ve ödeme onayını müşteriye WhatsApp ile gönder', 'flx-commerce' ) );
		self::input( 'notify_admin_phone', __( 'Yönetici WhatsApp numarası', 'flx-commerce' ), 'tel', __( 'Dekont geldiğinde (ve açıksa yeni siparişte) bu numaraya yazılır.', 'flx-commerce' ), 'class="regular-text" placeholder="+90 5xx xxx xx xx"' );
		self::toggle( 'notify_admin_order', __( 'Ödenmiş her yeni siparişte yöneticiye WhatsApp', 'flx-commerce' ) );
		echo '</section>';

		echo '<p class="flxcm-save"><button type="submit" class="button button-primary button-large">' . esc_html__( 'Kaydet', 'flx-commerce' ) . '</button></p></form>';
		FLXCM_Admin::close();
	}

	public static function save() {
		check_admin_referer( 'flxcm_save' );
		if ( ! current_user_can( FLXCM_Admin::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-commerce' ), 403 );
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
