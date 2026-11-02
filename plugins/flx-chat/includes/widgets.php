<?php
/**
 * 1.24.0 (Ahmet): asistan cevabında görsel. Flux AI 0.2.0+ kuruluysa model ```widget bloğunda tek bir <svg>
 * yazabilir; WhatsApp'a PNG resim olarak gider, metin her zaman ayrıca gider. Çevirici yoksa (Hostinger'da
 * Imagick SVG ya da Gotenberg adresi gerekir) kural talimata eklenmez, blok gelirse atılır.
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

/** Görsel açık mı: ayar + Flux AI görsel API'si + sunucuda bir çevirici. */
function flxc_widgets_on() {
	if ( ! (int) flxc_setting( 'bot_widgets' ) || ! function_exists( 'flxai_render_widget' ) || ! function_exists( 'flxai_widget_rules' ) || ! function_exists( 'flxai_extract_widgets' ) ) {
		return false;
	}
	if ( get_transient( 'flxc_widgets_unavail' ) ) {
		return false;
	}
	// Filtreyle kendi çeviricisini bağlayan site (flxai_render_widget) renderer listesinde görünmez; ona güvenilir.
	return ! function_exists( 'flxai_widget_renderer' ) || '' !== flxai_widget_renderer() || has_filter( 'flxai_render_widget' );
}

/** Sistem talimatına eklenen bölüm. */
function flxc_widgets_prompt() {
	if ( ! flxc_widgets_on() ) {
		return '';
	}
	return flxai_widget_rules( 'svg' )
		. "\n- WhatsApp'ta görseli yalnız gerçekten işe yarıyorsa kullan (ör. personel raporu, karşılaştırma, adım adım süreç). Selam, kısa soru ve bağış akışında kullanma.\n"
		. '- Fiyat, IBAN, link ve kişisel bilgi görsele yazılmaz; bunları sistem ayrıca gönderir.';
}

/**
 * Cevaptan görselleri ayırır.
 *
 * @return array { text: blokları çıkarılmış metin, codes: [ svg kodu ] } (mermaid sunucuda çevrilmez, atılır)
 */
function flxc_widgets_split( $reply ) {
	$reply = (string) $reply;
	if ( false === strpos( $reply, '```' ) ) {
		return array( 'text' => $reply, 'codes' => array() );
	}
	if ( ! function_exists( 'flxai_extract_widgets' ) ) {
		return array( 'text' => flxc_widgets_strip( $reply ), 'codes' => array() );
	}
	$x     = flxai_extract_widgets( $reply );
	$codes = array();
	foreach ( (array) $x['widgets'] as $w ) {
		if ( 'widget' === ( $w['type'] ?? '' ) && '' !== trim( (string) $w['code'] ) ) {
			$codes[] = (string) $w['code'];
		}
	}
	return array( 'text' => (string) $x['text'], 'codes' => array_slice( $codes, 0, 3 ) );
}

/**
 * Bir görseli PNG'ye çevirir. Çevirici yoksa 1 saat görsel kuralı kapanır (model boşuna çizmesin).
 *
 * @return array|WP_Error { mime, data }
 */
function flxc_widget_png( $code ) {
	if ( ! function_exists( 'flxai_render_widget' ) ) {
		return new WP_Error( 'flxc_widget', __( 'Flux AI görsel desteği yok.', 'flx-chat' ) );
	}
	$r = flxai_render_widget( $code, array( 'source' => 'flx-chat' ) );
	// SVG bile çevrilemediyse sunucuda çevirici yok (HTML widget'ın çevrilememesi yalnız o görseli düşürür).
	if ( is_wp_error( $r ) && 'flxai_render_unavailable' === $r->get_error_code() && ( ! function_exists( 'flxai_widget_is_svg' ) || flxai_widget_is_svg( $code ) ) ) {
		set_transient( 'flxc_widgets_unavail', 1, HOUR_IN_SECONDS );
	}
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	if ( 'image/png' !== ( $r['mime'] ?? '' ) || '' === (string) ( $r['data'] ?? '' ) ) {
		return new WP_Error( 'flxc_widget', __( 'Görsel çevrilemedi.', 'flx-chat' ) );
	}
	return $r;
}

/** Görseli sohbete resim olarak gönderir; olmazsa sohbete sistem satırı düşer, metin zaten gitmiştir. */
function flxc_widget_send( $chat, $code, $delay = 0 ) {
	$png = flxc_widget_png( $code );
	if ( is_wp_error( $png ) ) {
		flxc_system_message( $chat, __( 'Asistanın görseli gönderilemedi: ', 'flx-chat' ) . $png->get_error_message() );
		return false;
	}
	$tmp = wp_tempnam( 'flxc-widget.png' );
	if ( ! $tmp || false === file_put_contents( $tmp, $png['data'] ) ) { // phpcs:ignore
		return false;
	}
	$r = flxc_send(
		$chat,
		array(
			'type'    => 'image',
			'body'    => '',
			'file'    => $tmp,
			'mime'    => 'image/png',
			'name'    => 'gorsel.png',
			'source'  => 'bot',
			'user_id' => 0,
			'delay'   => (int) $delay,
		)
	);
	wp_delete_file( $tmp );
	return ! is_wp_error( $r );
}

/**
 * 1.30.1: kişiye giden metinde ham görsel kodu (```widget, ```mermaid, ```svg/```html) hiç kalmaz. Hangi yoldan
 * gelirse gelsin (asistan, hatırlatıcı, rapor) flxc_send bunu son kez temizler.
 */
function flxc_widgets_strip( $text ) {
	$text = preg_replace( '/^```(?:widget|mermaid|svg|html)[^\n]*\n.*?(?:\n```[ \t]*$|\z)/msi', '', (string) $text );
	$text = preg_replace( '/<svg\b.*?(?:<\/svg>|\z)/si', '', $text );
	return trim( preg_replace( "/\n{3,}/", "\n\n", $text ) );
}
