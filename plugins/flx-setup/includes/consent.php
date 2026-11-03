<?php
/**
 * Yasal metinler ve kullanım verisi izni.
 *
 * - Şartlar onayı: kurulum sihirbazında Kullanım Şartları + Gizlilik Politikası kabulü ve KVKK Aydınlatma Metni
 *   bilgisi. Kayıt `flxk_terms` (metin sürümü, zaman, kullanıcı). Güncellemeleri engellemez.
 * - Kullanım verisi (açık rıza, varsayılan KAPALI): `flxk_telemetry` = '1' ise açık, `flxk_telemetry_at` izin
 *   ya da geri alma zamanı. Kapalıyken güncelleme denetimi (/v1/check) yalnız paketleri, FluxWP Panel sürümünü,
 *   kurulum kimliğini ve varsa site anahtarını gönderir; site adresi, WordPress/PHP sürümü, saat dilimi ve dil
 *   gitmez, sunucuya "usage": false bildirilir. Anonim telemetri de aynı seçeneği okur.
 *
 * @package flx-setup
 */

defined( 'ABSPATH' ) || exit;

/** Yasal metinlerin sürümü: metin değişince artırılır, sihirbaz onayı bu sürümle kaydedilir. */
const FLXK_LEGAL_VERSION = '2026-11-03';

/** Kullanım verisi göndermeye izin var mı? */
function flxk_telemetry_enabled(): bool {
	return '1' === (string) get_option( 'flxk_telemetry', '' );
}

/** Kullanım verisi iznini kaydeder (sihirbaz ve FluxWP Panel › Ayarlar). */
function flxk_set_telemetry( bool $on ): void {
	if ( flxk_telemetry_enabled() === $on && false !== get_option( 'flxk_telemetry_at', false ) ) {
		return;
	}
	if ( $on ) {
		update_option( 'flxk_telemetry', '1', false );
	} else {
		delete_option( 'flxk_telemetry' );
	}
	update_option( 'flxk_telemetry_at', time(), false );
	do_action( 'flxk_telemetry_changed', $on );
}

/** Şartlar onayını kaydeder. */
function flxk_accept_terms(): void {
	update_option(
		'flxk_terms',
		array(
			'version' => FLXK_LEGAL_VERSION,
			'at'      => time(),
			'user'    => get_current_user_id(),
		),
		false
	);
}

/** @return array{version:string,at:int,user:int}|array{} */
function flxk_terms_accepted(): array {
	$t = get_option( 'flxk_terms', array() );
	return is_array( $t ) && ! empty( $t['at'] ) ? $t : array();
}

/**
 * Yasal metin adresleri, panel diline göre (Türkçe panel → /tr/). Filtre: flxk_legal_urls.
 *
 * @return array{terms:string,privacy:string,kvkk:string}
 */
function flxk_legal_urls(): array {
	$base = 'https://fluxwp.com/' . ( 0 === strpos( determine_locale(), 'tr' ) ? 'tr/' : '' );
	return (array) apply_filters(
		'flxk_legal_urls',
		array(
			'terms'   => $base . 'terms/',
			'privacy' => $base . 'privacy/',
			'kvkk'    => $base . 'kvkk/',
		)
	);
}

/**
 * WordPress'in varsayılan User-Agent'ı site adresini taşır ("WordPress/6.8; https://ornek.com").
 * İzin yokken FluxWP güncelleme sunucularına giden isteklerde (denetim ve zip indirme) adres çıkarılır.
 */
add_filter(
	'http_request_args',
	static function ( $args, $url ) {
		if ( flxk_telemetry_enabled() || ! class_exists( 'FLXU_Sources' ) ) {
			return $args;
		}
		$host = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
		if ( '' === $host ) {
			return $args;
		}
		foreach ( FLXU_Sources::all() as $s ) {
			if ( is_array( $s ) && 'server' === ( $s['type'] ?? '' ) && FLXU_Sources::host( $s ) === $host ) {
				$args['user-agent'] = 'FluxWP-Panel/' . ( defined( 'FLXK_VERSION' ) ? FLXK_VERSION : '1' );
				break;
			}
		}
		return $args;
	},
	10,
	2
);
