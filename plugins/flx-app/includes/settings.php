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
		'otp_create'      => 0,
		'otp_staff'       => 0,
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

function flxapp_secret_key() {
	return sodium_crypto_generichash( wp_salt( 'auth' ) . '|flxapp', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
}

function flxapp_encrypt( $plain ) {
	if ( '' === (string) $plain ) {
		return '';
	}
	if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
		return 'p:' . (string) $plain;
	}
	$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	return 's:' . base64_encode( $nonce . sodium_crypto_secretbox( (string) $plain, $nonce, flxapp_secret_key() ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

function flxapp_decrypt( $v ) {
	$v = (string) $v;
	if ( 0 === strpos( $v, 'p:' ) ) {
		return substr( $v, 2 );
	}
	if ( 0 !== strpos( $v, 's:' ) || ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
		return '';
	}
	$raw = (string) base64_decode( substr( $v, 2 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	if ( strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
		return '';
	}
	$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), flxapp_secret_key() );
	return false === $plain ? '' : $plain;
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
	$k  = 'flxapp_rl_' . md5( $id );
	$n  = (int) get_transient( $k );
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
