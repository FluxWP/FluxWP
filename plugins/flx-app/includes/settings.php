<?php
/** Ayarlar (tek seçenek `flxapp_settings`) ve küçük yardımcılar. */

defined( 'ABSPATH' ) || exit;

function flxapp_defaults() {
	return array(
		'access_minutes'  => 60,
		'refresh_days'    => 60,
		'dynamic_clients' => 0,
		'web_session'     => 1,
		'keys_cap'        => 'read',
		'otp_enabled'       => 1,
		'otp_create'        => 0,
		'otp_staff'         => 0,
		'phone_self_verify' => 1,
		'modules_off'     => array(),
		'screens_off'     => array(),
		'push_enabled'    => 1,
		'push_hide'       => 0,
		'generic_app'     => 1,
		'app_clients'     => array(),
		'dev_redirects'   => 0,
		'embed_front'     => 1,
		'store_ios'       => '',
		'store_android'   => '',
	);
}

/* Gizli değerler (Expo erişim belirteci, push belirteçleri) sodium ile şifreli. Panelde yalnız "kayıtlı" görünür. */

function flxapp_encrypt( $plain ) {
	return flx_secret_seal( (string) $plain, 'flx-app' );
}

function flxapp_decrypt( $v ) {
	if ( ! function_exists( 'flx_secret_open' ) && function_exists( 'flx_brand_boot' ) ) {
		flx_brand_boot(); // Read before after_setup_theme (e.g. mail sent while plugins load): load the shared core now.
	}
	if ( ! function_exists( 'flx_secret_open' ) ) {
		return '';
	}
	$v = (string) $v;
	if ( 0 === strpos( $v, 'p:' ) ) {
		return substr( $v, 2 );
	}
	if ( 0 !== strpos( $v, 'flx1:' ) && 0 !== strpos( $v, 's:' ) ) {
		return '';
	}
	return flx_secret_open( $v, 'flx-app', array( 's:' => wp_salt( 'auth' ) . '|flxapp' ) );
}

function flxapp_secret_put( $name, $value ) {
	$all = (array) get_option( 'flxapp_secrets', array() );
	if ( '' === (string) $value ) {
		unset( $all[ $name ] );
	} else {
		$all[ $name ] = flxapp_encrypt( $value );
	}
	update_option( 'flxapp_secrets', $all, false );
}

function flxapp_secret_get( $name ) {
	return flxapp_decrypt( ( (array) get_option( 'flxapp_secrets', array() ) )[ $name ] ?? '' );
}

function flxapp_secret_has( $name ) {
	return isset( ( (array) get_option( 'flxapp_secrets', array() ) )[ $name ] );
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
 * Basit oran sınırı: pencerede en çok $max istek. Aşılırsa false.
 * $bind_ip true ise kova çağıranın IP'sine de bağlanır; false ise yalnız hedefe (hesap, telefon, e-posta).
 */
function flxapp_rate( $key, $max = 30, $window = 60, $bind_ip = true ) {
	$id = $bind_ip ? $key . '|' . flxapp_ip() : 'id|' . $key;
	return flx_rate_limit( 'flxapp|' . $id, (int) $max, (int) $window );
}

/** Değişken veri dönen yanıtlar önbelleğe girmesin (LiteSpeed/CDN). */
function flxapp_nocache_headers() {
	if ( headers_sent() ) {
		return;
	}
	flx_rest_nocache();
	header( 'CDN-Cache-Control: no-store' );
	do_action( 'litespeed_control_set_nocache', 'flx-app' );
}
