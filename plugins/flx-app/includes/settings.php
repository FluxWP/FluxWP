<?php
/** Ayarlar (tek seçenek `flxapp_settings`) ve küçük yardımcılar. */

defined( 'ABSPATH' ) || exit;

function flxapp_defaults() {
	return array(
		'access_minutes'  => 60,
		'refresh_days'    => 60,
		'dynamic_clients' => 1,
		'web_session'     => 1,
		'keys_cap'        => 'read',
		'otp_enabled'     => 1,
		'otp_create'      => 1,
		'otp_staff'       => 0,
		'modules_off'     => array(),
	);
}

function flxapp_settings() {
	return wp_parse_args( (array) get_option( 'flxapp_settings', array() ), flxapp_defaults() );
}

function flxapp_opt( $key ) {
	$s = flxapp_settings();
	return $s[ $key ] ?? null;
}

/** Rastgele, URL'de güvenli belirteç: önek + 43 karakter. */
function flxapp_random( $prefix = '' ) {
	return $prefix . rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

function flxapp_hash( $token ) {
	return hash( 'sha256', (string) $token );
}

function flxapp_ip() {
	$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}

/** Sitenin kimliği (OAuth "issuer"): sonda eğik çizgi olmadan ana adres. */
function flxapp_issuer() {
	return untrailingslashit( home_url() );
}

/**
 * Basit oran sınırı: anahtar başına pencerede en çok $max istek. Aşılırsa false.
 */
function flxapp_rate( $key, $max = 30, $window = 60 ) {
	$k = 'flxapp_rl_' . md5( $key . '|' . flxapp_ip() );
	$n = (int) get_transient( $k );
	if ( $n >= $max ) {
		return false;
	}
	set_transient( $k, $n + 1, $window );
	return true;
}

/** Değişken veri dönen yanıtlar önbelleğe girmesin (LiteSpeed/CDN). */
function flxapp_nocache_headers() {
	if ( headers_sent() ) {
		return;
	}
	header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
	header( 'Pragma: no-cache' );
	header( 'X-LiteSpeed-Cache-Control: no-cache' );
	header( 'CDN-Cache-Control: no-store' );
	do_action( 'litespeed_control_set_nocache', 'flx-app' );
}
