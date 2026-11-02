<?php
/**
 * Canlı sayaçlar WordPress Heartbeat ile gelir: yeni bir istek türü açılmaz, WordPress'in zaten
 * yaptığı periyodik isteğe eklenir (Hostinger hız sınırı için önemli). Sekme gizliyken Heartbeat yavaşlar.
 */
defined( 'ABSPATH' ) || exit;

add_filter( 'heartbeat_received', 'flxa_heartbeat', 10, 2 );
add_filter( 'heartbeat_settings', 'flxa_heartbeat_settings', 20 );

function flxa_heartbeat( $response, $data ) {
	if ( empty( $data['flxa'] ) || ! flxa_enabled() ) {
		return $response;
	}
	$response['flxa'] = array( 'counts' => flxa_counts() );
	return $response;
}

function flxa_heartbeat_settings( $settings ) {
	global $pagenow;
	if ( ! is_admin() || ! flxa_enabled() || in_array( $pagenow, array( 'post.php', 'post-new.php', 'site-editor.php' ), true ) ) {
		return $settings; // Düzenleme ekranlarında WordPress'in kilit / otomatik kayıt ritmi korunur.
	}
	$n = (int) flxa_setting( 'heartbeat' );
	if ( $n >= 15 && $n <= 120 ) {
		$settings['interval'] = $n;
	}
	return $settings;
}
