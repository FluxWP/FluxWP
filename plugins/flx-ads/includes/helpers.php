<?php
/**
 * Flux Ads: ayarlar, gizli değerler, platform tanıma, ortak yardımcılar.
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

/** Tablo adı: conversions (platformlara gönderim kaydı) | orders (siparişin reklam atfı). */
function flxads_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'flxads_' . $name;
}

function flxads_now() {
	return current_time( 'mysql', true );
}

/** Varsayılan ayarlar. Filtre `flxads_default_settings`. */
function flxads_defaults() {
	return (array) apply_filters(
		'flxads_default_settings',
		array(
			'meta_on'       => 0,
			'meta_pixel'    => '',
			'meta_token'    => '',      // gizli
			'meta_test'     => '',      // Events Manager › Test events kodu (ör. TEST12345)
			'tiktok_on'     => 0,
			'tiktok_pixel'  => '',
			'tiktok_token'  => '',      // gizli
			'tiktok_test'   => '',
			'ga4_on'        => 0,
			'ga4_id'        => '',      // boşsa tema ayarındaki GA4 kimliği
			'ga4_secret'    => '',      // gizli (Measurement Protocol API secret)
			'scope'         => 'donations', // donations | all (yalnız bağış siparişleri ya da tüm siparişler)
			'consent'       => 'auto',  // auto (tema çerez banner'ı açıksa onay beklenir) | always
			'pii'           => 'consent', // consent (yalnız reklam izni olan kişi) | never | always
			'browser'       => 1,       // tarayıcı pixel'leri de yüklensin (kapalıysa yalnız sunucu)
			'window_days'   => 90,      // atıf çerezinin ömrü
		)
	);
}

function flxads_settings() {
	$s = get_option( 'flxads_settings', array() );
	return array_merge( flxads_defaults(), is_array( $s ) ? $s : array() );
}

function flxads_setting( $key ) {
	$s = flxads_settings();
	return $s[ $key ] ?? null;
}

/** Gizli alanlar: panelde, logda, REST/MCP'de asla açık gösterilmez. */
function flxads_secret_keys() {
	return array( 'meta_token', 'tiktok_token', 'ga4_secret' );
}

function flxads_save_settings( array $values ) {
	$cur = flxads_settings();
	foreach ( $values as $k => $v ) {
		if ( ! array_key_exists( $k, $cur ) ) {
			continue;
		}
		if ( in_array( $k, flxads_secret_keys(), true ) ) {
			if ( '' === (string) $v ) {
				continue; // write-only: boş gelirse eskisi kalır
			}
			$v = '__clear__' === $v ? '' : flxads_encrypt( (string) $v );
		}
		$cur[ $k ] = $v;
	}
	update_option( 'flxads_settings', $cur, false );
	return $cur;
}

/** Gizli değeri sakla (sodium varsa şifreli). */
function flxads_encrypt( $plain ) {
	if ( '' === $plain ) {
		return '';
	}
	if ( function_exists( 'sodium_crypto_secretbox' ) ) {
		$key   = substr( hash( 'sha256', wp_salt( 'auth' ) . 'flxads', true ), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return 'enc:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $key ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}
	return 'b64:' . base64_encode( $plain ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
}

function flxads_decrypt( $stored ) {
	$stored = (string) $stored;
	if ( 0 === strpos( $stored, 'enc:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
		$raw = base64_decode( substr( $stored, 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$key   = substr( hash( 'sha256', wp_salt( 'auth' ) . 'flxads', true ), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $key );
		return false === $plain ? '' : $plain;
	}
	if ( 0 === strpos( $stored, 'b64:' ) ) {
		return (string) base64_decode( substr( $stored, 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}
	return $stored;
}

/** Gizli değer (wp-config sabiti isteğe bağlı override: FLXADS_META_TOKEN, FLXADS_TIKTOK_TOKEN, FLXADS_GA4_SECRET). */
function flxads_secret( $key ) {
	$const = 'FLXADS_' . strtoupper( (string) $key );
	if ( defined( $const ) && '' !== (string) constant( $const ) ) {
		return (string) constant( $const );
	}
	return flxads_decrypt( flxads_setting( $key ) );
}

/** "kayıtlı · …1234" gibi maske. */
function flxads_mask( $plain ) {
	$plain = (string) $plain;
	return '' === $plain ? '' : '…' . substr( $plain, -4 );
}

/** GA4 ölçüm kimliği: Flux Ads ayarı, yoksa FluxWP temasının Tema Ayarları'ndaki kimlik. */
function flxads_ga4_id() {
	$id = strtoupper( trim( (string) flxads_setting( 'ga4_id' ) ) );
	if ( '' === $id && function_exists( 'flx_ayar' ) ) {
		$id = strtoupper( trim( (string) flx_ayar( 'ga4_id' ) ) );
	}
	return preg_match( '/^G-[A-Z0-9]{4,}$/', $id ) ? $id : '';
}

/** GA4 etiketini tema zaten yüklüyor mu (çift yüklemeyi önlemek için)? */
function flxads_theme_loads_ga4() {
	return function_exists( 'flx_ayar' ) && '' !== (string) flx_ayar( 'ga4_id' ) && strtoupper( (string) flx_ayar( 'ga4_id' ) ) === flxads_ga4_id();
}

/** Platform açık ve gerekli kimlik girilmiş mi? $server = sunucu gönderimi için anahtar da gerekli. */
function flxads_platform_on( $p, $server = false ) {
	$s = flxads_settings();
	switch ( $p ) {
		case 'meta':
			return ! empty( $s['meta_on'] ) && preg_match( '/^\d{6,20}$/', (string) $s['meta_pixel'] ) && ( ! $server || '' !== flxads_secret( 'meta_token' ) );
		case 'tiktok':
			return ! empty( $s['tiktok_on'] ) && preg_match( '/^[A-Z0-9]{10,30}$/i', (string) $s['tiktok_pixel'] ) && ( ! $server || '' !== flxads_secret( 'tiktok_token' ) );
		case 'ga4':
			return ! empty( $s['ga4_on'] ) && '' !== flxads_ga4_id() && ( ! $server || '' !== flxads_secret( 'ga4_secret' ) );
	}
	return false;
}

/** Dönüşüm gönderilen platformlar. */
function flxads_platforms() {
	return array(
		'meta'   => 'Meta',
		'tiktok' => 'TikTok',
		'ga4'    => 'Google Analytics 4',
	);
}

/** Atıf parametreleri (URL'den okunur). */
function flxads_param_keys() {
	return array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_id', 'fbclid', 'gclid', 'gbraid', 'wbraid', 'ttclid', 'msclkid' );
}

/**
 * Bir dokunuşun (touch) reklam kanalı: meta | google | tiktok | microsoft | other | ''.
 * Tıklama kimliği UTM'den önce gelir. Filtre `flxads_channel`.
 */
function flxads_channel( array $t ) {
	$src = strtolower( (string) ( $t['utm_source'] ?? '' ) );
	$med = strtolower( (string) ( $t['utm_medium'] ?? '' ) );
	$ch  = '';
	if ( ! empty( $t['fbclid'] ) || preg_match( '/^(fb|facebook|ig|instagram|meta|an|messenger)(\.|$|_|-)/', $src ) ) {
		$ch = 'meta';
	} elseif ( ! empty( $t['ttclid'] ) || preg_match( '/^tiktok/', $src ) ) {
		$ch = 'tiktok';
	} elseif ( ! empty( $t['gclid'] ) || ! empty( $t['gbraid'] ) || ! empty( $t['wbraid'] ) || ( preg_match( '/^(google|youtube|adwords)/', $src ) && preg_match( '/(cpc|ppc|paid|ads|display|video|pmax)/', $med ) ) ) {
		$ch = 'google';
	} elseif ( ! empty( $t['msclkid'] ) || ( preg_match( '/^(bing|microsoft)/', $src ) && preg_match( '/(cpc|ppc|paid)/', $med ) ) ) {
		$ch = 'microsoft';
	} elseif ( '' !== $src ) {
		$ch = 'other';
	}
	return (string) apply_filters( 'flxads_channel', $ch, $t );
}

/** Kanal adları. */
function flxads_channel_labels() {
	return array(
		'meta'      => 'Meta (Facebook / Instagram)',
		'google'    => 'Google Ads',
		'tiktok'    => 'TikTok',
		'microsoft' => 'Microsoft Ads',
		'other'     => __( 'Other campaigns (UTM)', 'flx-ads' ),
		''          => __( 'No ad (direct, search, other sites)', 'flx-ads' ),
	);
}

function flxads_channel_label( $ch ) {
	$l = flxads_channel_labels();
	return $l[ (string) $ch ] ?? (string) $ch;
}

/** Yetki: görüntüle | yönet. Flux Roles bu yetkileri başka rollere verebilir. */
function flxads_can( $manage = false ) {
	return current_user_can( $manage ? 'flxads_manage' : 'flxads_view' ) || current_user_can( 'manage_options' );
}

function flxads_admin_url( $tab = 'report', array $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => 'flx-ads', 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
}

/** Para gösterimi. */
function flxads_money( $amount, $currency = '' ) {
	$currency = '' !== $currency ? strtoupper( $currency ) : ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'TRY' );
	$amount   = (float) $amount;
	if ( function_exists( 'wc_price' ) ) {
		return wp_strip_all_tags( html_entity_decode( wc_price( $amount, array( 'currency' => $currency ) ), ENT_QUOTES, 'UTF-8' ) );
	}
	$sym = array( 'TRY' => '₺', 'USD' => '$', 'EUR' => '€', 'GBP' => '£' );
	$n   = number_format_i18n( $amount, floor( $amount ) == $amount ? 0 : 2 ); // phpcs:ignore Universal.Operators.StrictComparisons
	return isset( $sym[ $currency ] ) ? ( 'TRY' === $currency ? $n . ' ' . $sym[ $currency ] : $sym[ $currency ] . $n ) : $n . ' ' . $currency;
}

/** Tarih (flx-brand yoksa WordPress). */
function flxads_date( $gmt, $format = 'datetime' ) {
	if ( '' === (string) $gmt || '0000-00-00 00:00:00' === (string) $gmt ) {
		return '';
	}
	return function_exists( 'flx_date' ) ? flx_date( $gmt, $format ) : get_date_from_gmt( $gmt, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
}

function flxads_ago( $gmt ) {
	if ( '' === (string) $gmt ) {
		return '';
	}
	return function_exists( 'flx_time_ago' ) ? flx_time_ago( $gmt ) : flxads_date( $gmt );
}

/** Meta/TikTok normalizasyonu: trim + küçük harf, SHA-256 hex. */
function flxads_hash( $v ) {
	$v = strtolower( trim( (string) $v ) );
	return '' === $v ? '' : hash( 'sha256', $v );
}

/** Telefon: yalnız rakamlar (ülke kodlu E.164, + olmadan), SHA-256. */
function flxads_hash_phone( $phone ) {
	$p = (string) $phone;
	if ( function_exists( 'flx_phone_normalize' ) ) {
		$n = flx_phone_normalize( $p, 'tr' );
		$p = '' !== $n ? $n : $p;
	}
	$d = preg_replace( '/\D+/', '', $p );
	if ( 10 === strlen( $d ) && '5' === $d[0] ) {
		$d = '90' . $d; // Türkiye cep, ülke kodsuz
	} elseif ( 11 === strlen( $d ) && '05' === substr( $d, 0, 2 ) ) {
		$d = '9' . $d;
	}
	return '' === $d ? '' : hash( 'sha256', $d );
}
