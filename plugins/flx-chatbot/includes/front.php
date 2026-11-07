<?php
/**
 * Ön yüz: görünürlük, sohbet penceresi dosyaları ve ayarları, [flx_chatbot] kısa kodu.
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

/** Geçerli sayfanın türü (görünürlük ayarları için). */
function flxcb_current_page_type() {
	if ( function_exists( 'is_cart' ) && is_cart() ) {
		return 'cart';
	}
	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		return 'checkout';
	}
	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		return 'account';
	}
	if ( ( function_exists( 'is_shop' ) && is_shop() ) || ( function_exists( 'is_product' ) && is_product() ) || ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) ) {
		return 'shop';
	}
	if ( is_front_page() ) {
		return 'home';
	}
	if ( is_singular( 'post' ) ) {
		return 'posts';
	}
	if ( is_page() ) {
		return 'pages';
	}
	return 'other';
}

/** Adres hariç listesinde mi ("*" joker, satır başına bir yol ya da adres). */
function flxcb_url_excluded( $path ) {
	foreach ( flxcb_lines( 'exclude_urls' ) as $rule ) {
		$rule = preg_replace( '#^https?://[^/]+#i', '', $rule );
		if ( '' === $rule ) {
			continue;
		}
		$re = '#^' . str_replace( '\*', '.*', preg_quote( $rule, '#' ) ) . '$#i';
		if ( preg_match( $re, $path ) || preg_match( $re, untrailingslashit( $path ) ) || preg_match( $re, trailingslashit( $path ) ) ) {
			return true;
		}
	}
	return false;
}

/** Bu sayfada sohbet penceresi görünür mü. */
function flxcb_should_show() {
	if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return false;
	}
	$preview = isset( $_GET['flxcb_preview'] ) && current_user_can( 'manage_options' ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! flxcb_setting( 'enabled' ) && ! $preview ) {
		return false;
	}
	$aud = (string) flxcb_setting( 'audience' );
	if ( ( 'guests' === $aud && is_user_logged_in() ) || ( 'members' === $aud && ! is_user_logged_in() ) ) {
		return (bool) apply_filters( 'flxcb_show', $preview, 'audience' );
	}
	if ( ! $preview && ! flxcb_setting( 'show_on_' . flxcb_current_page_type() ) ) {
		return (bool) apply_filters( 'flxcb_show', false, 'page_type' );
	}
	$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/', PHP_URL_PATH );
	if ( ! $preview && flxcb_url_excluded( '' !== $path ? $path : '/' ) ) {
		return (bool) apply_filters( 'flxcb_show', false, 'url' );
	}
	return (bool) apply_filters( 'flxcb_show', true, '' );
}

/** Pencereye giden metinler. */
function flxcb_js_strings() {
	return array(
		'open'        => __( 'Sohbeti aç', 'flx-chatbot' ),
		'close'       => __( 'Kapat', 'flx-chatbot' ),
		'minimize'    => __( 'Küçült', 'flx-chatbot' ),
		'back'        => __( 'Geri', 'flx-chatbot' ),
		'home'        => __( 'Ana sayfa', 'flx-chatbot' ),
		'messages'    => __( 'Mesajlar', 'flx-chatbot' ),
		'send'        => __( 'Gönder', 'flx-chatbot' ),
		'attach'      => __( 'Dosya ekle', 'flx-chatbot' ),
		'dictate'     => __( 'Sesle yaz', 'flx-chatbot' ),
		'dictateStop' => __( 'Dinlemeyi durdur', 'flx-chatbot' ),
		'startChat'   => __( 'Bize yazın', 'flx-chatbot' ),
		'continueChat' => __( 'Sohbete devam edin', 'flx-chatbot' ),
		'replyTime'   => __( 'Genelde birkaç dakika içinde cevap veririz', 'flx-chatbot' ),
		'aiReply'     => __( 'Asistanımız hemen cevap verir', 'flx-chatbot' ),
		'online'      => __( 'Çevrim içi', 'flx-chatbot' ),
		'offline'     => __( 'Şu an çevrim dışı', 'flx-chatbot' ),
		'typing'      => __( 'yazıyor…', 'flx-chatbot' ),
		'thinking'    => __( 'Düşünüyor…', 'flx-chatbot' ),
		'searching'   => __( 'Sitede arıyor…', 'flx-chatbot' ),
		'products'    => __( 'Ürünlere bakıyor…', 'flx-chatbot' ),
		'orders'      => __( 'Siparişlerinize bakıyor…', 'flx-chatbot' ),
		'human'       => __( 'Temsilciyle görüş', 'flx-chatbot' ),
		'humanAsk'    => __( 'Bir temsilciyle görüşmek ister misiniz?', 'flx-chatbot' ),
		/* translators: %d: queue position */
		'queue'       => __( 'Sıradaki yeriniz: %d', 'flx-chatbot' ),
		'queued'      => __( 'Temsilci bekleniyor', 'flx-chatbot' ),
		'agentJoined' => __( 'Temsilci', 'flx-chatbot' ),
		'seen'        => __( 'Görüldü', 'flx-chatbot' ),
		'sent'        => __( 'Gönderildi', 'flx-chatbot' ),
		'you'         => __( 'Siz', 'flx-chatbot' ),
		'end'         => __( 'Sohbeti sonlandır', 'flx-chatbot' ),
		'endAsk'      => __( 'Sohbet sonlandırılsın mı?', 'flx-chatbot' ),
		'yes'         => __( 'Evet', 'flx-chatbot' ),
		'no'          => __( 'Hayır', 'flx-chatbot' ),
		'newChat'     => __( 'Yeni sohbet', 'flx-chatbot' ),
		'rateTitle'   => __( 'Sohbeti nasıl buldunuz?', 'flx-chatbot' ),
		'rateNote'    => __( 'Eklemek istediğiniz bir şey var mı? (isteğe bağlı)', 'flx-chatbot' ),
		'rateThanks'  => __( 'Teşekkürler, geri bildiriminiz bize ulaştı.', 'flx-chatbot' ),
		'rateSend'    => __( 'Gönder', 'flx-chatbot' ),
		'name'        => __( 'Adınız', 'flx-chatbot' ),
		'email'       => __( 'E-posta adresiniz', 'flx-chatbot' ),
		'phone'       => __( 'Telefon (isteğe bağlı)', 'flx-chatbot' ),
		'message'     => __( 'Mesajınız', 'flx-chatbot' ),
		'leaveMsg'    => __( 'Mesaj bırakın', 'flx-chatbot' ),
		'contactIntro' => __( 'Size dönebilmemiz için e-posta adresinizi bırakın.', 'flx-chatbot' ),
		'contactSend' => __( 'Gönder', 'flx-chatbot' ),
		'badEmail'    => __( 'Geçerli bir e-posta adresi yazın.', 'flx-chatbot' ),
		'error'       => __( 'Bağlantı sorunu. Tekrar deneyin.', 'flx-chatbot' ),
		'retry'       => __( 'Tekrar dene', 'flx-chatbot' ),
		'tooBig'      => __( 'Dosya çok büyük.', 'flx-chatbot' ),
		'addToCart'   => __( 'Sepete ekle', 'flx-chatbot' ),
		'view'        => __( 'İncele', 'flx-chatbot' ),
		'outOfStock'  => __( 'Stokta yok', 'flx-chatbot' ),
		'poweredBy'   => __( 'FluxWP ile çalışır', 'flx-chatbot' ),
		'today'       => __( 'Bugün', 'flx-chatbot' ),
		'unread'      => __( 'yeni mesaj', 'flx-chatbot' ),
		'askAnything' => __( 'Bir soru sorun', 'flx-chatbot' ),
		'contactUs'   => __( 'Bize ulaşın', 'flx-chatbot' ),
		'hours'       => __( 'Çalışma saatleri', 'flx-chatbot' ),
	);
}

/** Pencere ayarları (ön yüz ve yönetim önizlemesi aynı nesneyi kullanır). */
function flxcb_widget_config() {
	$s      = flxcb_settings();
	$color  = sanitize_hex_color( (string) $s['color'] );
	$tcolor = sanitize_hex_color( (string) $s['color_text'] );
	$user   = null;
	if ( is_user_logged_in() ) {
		$u    = wp_get_current_user();
		$user = array(
			'name'  => flxcb_user_name( $u->ID ),
			'email' => $u->user_email,
		);
	}
	$target = flxcb_handoff_target();
	$cfg    = array(
		'rest'       => esc_url_raw( rest_url( 'flx-chatbot/v1/' ) ),
		'css'        => FLXCB_URL . 'assets/widget.css?ver=' . FLXCB_VERSION,
		'flxTheme'   => 'fluxwp' === get_template(),
		'nonce'      => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
		'mode'       => (string) $s['mode'],
		'ai'         => 'live' !== $s['mode'] && flxcb_ai_ready(),
		'live'       => '' !== $target,
		'online'     => flxcb_is_online(),
		'hours'      => flxcb_hours_text(),
		'botName'    => flxcb_bot_name(),
		'siteName'   => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		'avatar'     => flxcb_avatar_url(),
		'avatarIcon' => (string) $s['avatar_icon'],
		'launcher'   => array(
			'icon'  => (string) $s['launcher_icon'],
			'label' => trim( (string) $s['launcher_label'] ),
			'size'  => max( 44, min( 80, (int) $s['launcher_size'] ) ),
		),
		'color'      => $color ? $color : '',
		'colorText'  => $tcolor ? $tcolor : ( $color ? flxcb_contrast( $color ) : '' ),
		'theme'      => (string) $s['theme'],
		'position'   => 'left' === $s['position'] ? 'left' : 'right',
		'offsetX'    => max( 0, min( 200, (int) $s['offset_x'] ) ),
		'offsetY'    => max( 0, min( 300, (int) $s['offset_y'] ) ),
		'radius'     => max( 0, min( 28, (int) $s['radius'] ) ),
		'width'      => max( 320, min( 480, (int) $s['width'] ) ),
		'header'     => 'solid' === $s['header_style'] ? 'solid' : 'gradient',
		'font'       => 'system' === $s['font'] ? 'system' : 'inherit',
		'mobileFull' => (bool) $s['mobile_full'],
		'branding'   => (bool) apply_filters( 'flxcb_show_branding', (bool) $s['show_branding'] ),
		'sound'      => (bool) $s['sound'],
		'devices'    => array(
			'desktop' => (bool) $s['device_desktop'],
			'mobile'  => (bool) $s['device_mobile'],
		),
		'home'       => array(
			'enabled' => (bool) $s['home_enabled'],
			'title'   => flxcb_text( 'home_title' ),
			'text'    => flxcb_text( 'home_text' ),
			'team'    => $s['show_team'] && '' !== $target ? flxcb_team() : array(),
			'links'   => flxcb_links(),
		),
		'quick'      => array_slice( flxcb_lines( 'quick_replies' ), 0, 6 ),
		'welcome'    => 'live' === $s['mode'] ? '' : flxcb_text( 'welcome' ),
		'placeholder' => flxcb_text( 'placeholder' ),
		'teaser'     => array(
			'enabled' => (bool) $s['teaser_enabled'],
			'text'    => flxcb_text( 'teaser_text' ),
			'delay'   => max( 0, min( 300, (int) $s['teaser_delay'] ) ),
		),
		'offlineText' => flxcb_text( 'offline_text' ),
		'askEmail'   => (string) $s['ask_email'],
		'rating'     => (bool) $s['rating'],
		'uploads'    => (bool) $s['uploads'],
		'uploadMb'   => max( 1, (int) $s['upload_mb'] ),
		'dictation'  => (bool) $s['dictation'],
		'privacy'    => wp_kses( flxcb_text( 'privacy_text' ), array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ),
		'user'       => $user,
		'locale'     => determine_locale(),
		'i18n'       => flxcb_js_strings(),
	);
	return (array) apply_filters( 'flxcb_widget_config', $cfg );
}

function flxcb_enqueue_widget( $preview = false ) {
	wp_enqueue_script( 'flxcb-widget', FLXCB_URL . 'assets/widget.js', array(), FLXCB_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	$cfg            = flxcb_widget_config();
	$cfg['preview'] = (bool) $preview;
	wp_add_inline_script( 'flxcb-widget', 'window.flxcbConfig=' . wp_json_encode( $cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . ';', 'before' );
}

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( flxcb_should_show() ) {
			flxcb_enqueue_widget();
		}
	}
);

/*
 * [flx_chatbot] kısa kodu: sayfa içinde "Bize yazın" düğmesi (pencereyi açar).
 * Herhangi bir bağlantıya data-flxcb-open ya da #flxcb-open adresi vermek de pencereyi açar.
 */
add_shortcode(
	'flx_chatbot',
	function ( $atts ) {
		$a = shortcode_atts(
			array(
				'label'   => __( 'Bize yazın', 'flx-chatbot' ),
				'message' => '',
			),
			$atts,
			'flx_chatbot'
		);
		if ( ! wp_script_is( 'flxcb-widget', 'enqueued' ) && flxcb_setting( 'enabled' ) ) {
			flxcb_enqueue_widget();
		}
		return '<button type="button" class="flxcb-open-btn wp-element-button" data-flxcb-open' . ( '' !== $a['message'] ? ' data-flxcb-message="' . esc_attr( $a['message'] ) . '"' : '' ) . '>' . esc_html( $a['label'] ) . '</button>';
	}
);
