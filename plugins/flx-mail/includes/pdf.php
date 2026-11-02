<?php
/**
 * E-posta önizlemesini PDF'e çevirme.
 *
 * Sunucuda tarayıcı yok (Hostinger paylaşımlı); çevirici olarak Flux AI › Ayarlar › Görseller'deki
 * Gotenberg kullanılır. Yoksa tarayıcının "PDF olarak kaydet" yazdırma penceresi açılır (admin.js).
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/** Sunucuda PDF çevirici var mı (filtre ya da Flux AI'daki Gotenberg adresi). */
function flxi_pdf_available() {
	if ( has_filter( 'flxi_render_pdf' ) ) {
		return true;
	}
	return function_exists( 'flxai_setting' ) && '' !== trim( (string) flxai_setting( 'render_url', '' ) );
}

/**
 * HTML e-postayı tek sayfalık PDF'e çevirir.
 *
 * @param string $html Tam e-posta HTML'i.
 * @return string|WP_Error PDF ikili verisi.
 */
function flxi_render_pdf( $html ) {
	$pre = apply_filters( 'flxi_render_pdf', null, $html );
	if ( null !== $pre ) {
		return $pre;
	}
	if ( ! flxi_pdf_available() ) {
		return new WP_Error( 'flxi_pdf_unavailable', __( 'Sunucuda PDF çevirici yok (Flux AI › Ayarlar › Görseller › Gotenberg).', 'flx-mail' ) );
	}
	$url      = untrailingslashit( trim( (string) flxai_setting( 'render_url', '' ) ) ) . '/forms/chromium/convert/html';
	$boundary = 'flxi' . wp_generate_password( 16, false );
	$fields   = array(
		'paperWidth'           => '8.27',
		'paperHeight'          => '11.7',
		'marginTop'            => '0',
		'marginBottom'         => '0',
		'marginLeft'           => '0',
		'marginRight'          => '0',
		'printBackground'      => 'true',
		'singlePage'           => 'true',
		'emulatedMediaType'    => 'screen',
		'skipNetworkIdleEvent' => 'false',
	);
	$body = '';
	foreach ( $fields as $k => $v ) {
		$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$k}\"\r\n\r\n{$v}\r\n";
	}
	$body   .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"files\"; filename=\"index.html\"\r\nContent-Type: text/html\r\n\r\n{$html}\r\n--{$boundary}--\r\n";
	$headers = array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary );
	$user    = (string) flxai_setting( 'render_user', '' );
	if ( '' !== $user ) {
		$headers['Authorization'] = 'Basic ' . base64_encode( $user . ':' . (string) flxai_setting( 'key_render', '' ) ); // phpcs:ignore
	}
	$r = wp_remote_post(
		$url,
		array(
			'timeout' => 40,
			'headers' => $headers,
			'body'    => $body,
		)
	);
	if ( is_wp_error( $r ) ) {
		/* translators: %s: hata */
		return new WP_Error( 'flxi_pdf_http', sprintf( __( 'PDF çeviriciye ulaşılamadı: %s', 'flx-mail' ), $r->get_error_message() ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	$data = (string) wp_remote_retrieve_body( $r );
	if ( 200 !== $code || '%PDF' !== substr( $data, 0, 4 ) ) {
		/* translators: %d: HTTP kodu */
		return new WP_Error( 'flxi_pdf_failed', sprintf( __( 'PDF oluşturulamadı (HTTP %d).', 'flx-mail' ), $code ) );
	}
	return $data;
}

/** PDF dosya adı: konudan. */
function flxi_pdf_filename( $subject ) {
	$name = sanitize_title( remove_accents( (string) $subject ) );
	return ( '' !== $name ? $name : 'e-posta' ) . '.pdf';
}
