<?php
/**
 * Ayarlar, gizli anahtar saklama, istatistik ve küçük yardımcılar.
 *
 * @package FLX_Recaptcha
 */

defined( 'ABSPATH' ) || exit;

/** Varsayılan ayarlar. `forms` bağlam anahtarı → 0/1; listede olmayan bağlam kendi varsayılanını alır. */
function flxrc_defaults() {
	return array(
		'version'        => 'v3',      // v3 | v2.
		'site_key'       => '',
		'secret'         => '',        // Şifreli (flxrc_secret()).
		'threshold'      => 0.5,       // v3 puanı bunun altındaysa bot sayılır.
		'domain'         => 'google',  // google | recaptcha (www.recaptcha.net).
		'v2_theme'       => 'auto',    // auto | light | dark.
		'v2_size'        => 'normal',  // normal | compact.
		'hide_badge'     => 0,         // v3 rozeti gizlensin mi (formun altına Google metni eklenir).
		'skip_logged_in' => 1,         // Giriş yapmış kullanıcıya sorma (yorum, ödeme, bağış, form).
		'fail_open'      => 1,         // Google'a ulaşılamazsa geçir.
		'allow_ips'      => '',        // Satır başına IP ya da CIDR; bunlara hiç sorulmaz.
		'forms'          => array(),
		'verified'       => '',        // Test geçen anahtarların özeti; anahtarlar değişince boşalır.
	);
}

/** Ayarlar (dizi) ya da tek alan. */
function flxrc_settings( $key = null ) {
	static $cache = null;
	if ( null === $cache || 'flush' === $key ) {
		$s     = get_option( 'flxrc_settings', array() );
		$cache = array_merge( flxrc_defaults(), is_array( $s ) ? $s : array() );
		if ( 'flush' === $key ) {
			return $cache;
		}
	}
	if ( null === $key ) {
		return $cache;
	}
	return array_key_exists( $key, $cache ) ? $cache[ $key ] : null;
}

function flxrc_update_settings( array $s ) {
	update_option( 'flxrc_settings', array_merge( flxrc_defaults(), $s ), true );
	flxrc_settings( 'flush' );
}

/** Kurtarma: wp-config.php'de define( 'FLXRC_DISABLE', true ); tüm koruma kapanır. */
function flxrc_disabled_by_constant() {
	return defined( 'FLXRC_DISABLE' ) && FLXRC_DISABLE;
}

/* ------------------------------------------------------------------ */
/* Gizli anahtar                                                       */
/* ------------------------------------------------------------------ */

/** Düz metni saklanacak biçime çevirir. */
function flxrc_seal( $plain ) {
	return flx_secret_seal( (string) $plain, 'flx-recaptcha' );
}

/** Saklanan değeri açar; açılamazsa (tuzlar değişti) ''. */
function flxrc_unseal( $stored ) {
	if ( ! function_exists( 'flx_secret_open' ) && function_exists( 'flx_brand_boot' ) ) {
		flx_brand_boot(); // Read before after_setup_theme (e.g. mail sent while plugins load): load the shared core now.
	}
	if ( ! function_exists( 'flx_secret_open' ) ) {
		return '';
	}
	$stored = (string) $stored;
	if ( 0 === strpos( $stored, 'p1:' ) ) {
		return (string) base64_decode( substr( $stored, 3 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}
	if ( 0 !== strpos( $stored, 'flx1:' ) && 0 !== strpos( $stored, 's1:' ) ) {
		return '';
	}
	return flx_secret_open( $stored, 'flx-recaptcha', array( 's1:' => 'flx-recaptcha|' . wp_salt( 'secure_auth' ) ) );
}

/** Gizli anahtar (düz). FLXRC_SECRET_KEY sabiti paneli ezer. */
function flxrc_secret() {
	if ( defined( 'FLXRC_SECRET_KEY' ) && is_string( FLXRC_SECRET_KEY ) && '' !== FLXRC_SECRET_KEY ) {
		return FLXRC_SECRET_KEY;
	}
	return flxrc_unseal( flxrc_settings( 'secret' ) );
}

/** Site anahtarı. FLXRC_SITE_KEY sabiti paneli ezer. */
function flxrc_site_key() {
	if ( defined( 'FLXRC_SITE_KEY' ) && is_string( FLXRC_SITE_KEY ) && '' !== FLXRC_SITE_KEY ) {
		return FLXRC_SITE_KEY;
	}
	return (string) flxrc_settings( 'site_key' );
}

function flxrc_version() {
	return 'v2' === flxrc_settings( 'version' ) ? 'v2' : 'v3';
}

/** Test geçen anahtar özetini üretir (sürüm + iki anahtar). */
function flxrc_key_fingerprint() {
	return substr( hash( 'sha256', flxrc_version() . '|' . flxrc_site_key() . '|' . flxrc_secret() ), 0, 24 );
}

function flxrc_has_keys() {
	return '' !== flxrc_site_key() && '' !== flxrc_secret();
}

/** Anahtarlar girildi ve panelde test edildi mi. Test edilmemiş anahtarla koruma çalışmaz (yöneticinin kilitlenmemesi için). */
function flxrc_keys_verified() {
	return flxrc_has_keys() && hash_equals( (string) flxrc_settings( 'verified' ), flxrc_key_fingerprint() );
}

/** Koruma çalışıyor mu (genel). */
function flxrc_active() {
	return ! flxrc_disabled_by_constant() && flxrc_keys_verified();
}

/** Gizli değeri ekranda göstermek için: son 4 hane. */
function flxrc_mask( $v ) {
	$v = (string) $v;
	return '' === $v ? '' : '••••' . substr( $v, -4 );
}

function flxrc_admin_url( $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=flx-recaptcha' ) );
}

/** API sunucusu (www.google.com ya da www.recaptcha.net: Google'ın erişilemediği ağlar için). */
function flxrc_api_host() {
	return 'recaptcha' === flxrc_settings( 'domain' ) ? 'https://www.recaptcha.net' : 'https://www.google.com';
}

/* ------------------------------------------------------------------ */
/* İstek bilgisi                                                       */
/* ------------------------------------------------------------------ */

/** Ziyaretçi IP'si (yalnız REMOTE_ADDR; vekil başlıkları sahte olabilir). Filtre: flxrc_client_ip. */
function flxrc_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return (string) apply_filters( 'flxrc_client_ip', $ip );
}

/** IP izin listesinde mi (tek IP ya da IPv4/IPv6 CIDR). */
function flxrc_ip_allowed( $ip = null ) {
	$ip   = null === $ip ? flxrc_client_ip() : (string) $ip;
	$list = preg_split( '/[\s,]+/', (string) flxrc_settings( 'allow_ips' ), -1, PREG_SPLIT_NO_EMPTY );
	if ( '' === $ip || ! $list ) {
		return false;
	}
	$bin = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	foreach ( $list as $rule ) {
		if ( $rule === $ip ) {
			return true;
		}
		if ( false === $bin || false === strpos( $rule, '/' ) ) {
			continue;
		}
		list( $net, $bits ) = explode( '/', $rule, 2 );
		$nb                 = @inet_pton( $net ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$bits               = (int) $bits;
		if ( false === $nb || strlen( $nb ) !== strlen( $bin ) || $bits < 0 || $bits > strlen( $bin ) * 8 ) {
			continue;
		}
		$bytes = intdiv( $bits, 8 );
		$rem   = $bits % 8;
		if ( substr( $nb, 0, $bytes ) !== substr( $bin, 0, $bytes ) ) {
			continue;
		}
		if ( 0 === $rem ) {
			return true;
		}
		$mask = ( 0xFF << ( 8 - $rem ) ) & 0xFF;
		if ( ( ord( $nb[ $bytes ] ) & $mask ) === ( ord( $bin[ $bytes ] ) & $mask ) ) {
			return true;
		}
	}
	return false;
}

/* ------------------------------------------------------------------ */
/* İstatistik: son 30 gün, gün → bağlam → [geçti, engellendi]           */
/* ------------------------------------------------------------------ */

function flxrc_stat( $ctx, $ok ) {
	$s   = get_option( 'flxrc_stats', array() );
	$s   = is_array( $s ) ? $s : array();
	$day = wp_date( 'Y-m-d' );
	if ( ! isset( $s[ $day ][ $ctx ] ) ) {
		$s[ $day ][ $ctx ] = array( 0, 0 );
	}
	++$s[ $day ][ $ctx ][ $ok ? 0 : 1 ];
	if ( count( $s ) > 31 ) {
		ksort( $s );
		$s = array_slice( $s, -31, null, true );
	}
	update_option( 'flxrc_stats', $s, false );
}

/** Toplamlar: [ bağlam => [geçti, engellendi] ] ve '_all'. */
function flxrc_stats_totals( $days = 30 ) {
	$s    = get_option( 'flxrc_stats', array() );
	$from = wp_date( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS );
	$out  = array( '_all' => array( 0, 0 ) );
	foreach ( is_array( $s ) ? $s : array() as $day => $rows ) {
		if ( $day < $from ) {
			continue;
		}
		foreach ( (array) $rows as $ctx => $r ) {
			if ( ! isset( $out[ $ctx ] ) ) {
				$out[ $ctx ] = array( 0, 0 );
			}
			$out[ $ctx ][0]  += (int) $r[0];
			$out[ $ctx ][1]  += (int) $r[1];
			$out['_all'][0] += (int) $r[0];
			$out['_all'][1] += (int) $r[1];
		}
	}
	return $out;
}

/** Günlük seri (grafik için): [ 'Y-m-d' => [geçti, engellendi] ]. */
function flxrc_stats_daily( $days = 14 ) {
	$s   = get_option( 'flxrc_stats', array() );
	$out = array();
	for ( $i = $days - 1; $i >= 0; $i-- ) {
		$d         = wp_date( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
		$out[ $d ] = array( 0, 0 );
		foreach ( (array) ( $s[ $d ] ?? array() ) as $r ) {
			$out[ $d ][0] += (int) $r[0];
			$out[ $d ][1] += (int) $r[1];
		}
	}
	return $out;
}

/** Son hata ve test sonucu (autoload kapalı). */
function flxrc_status( $key = null, $value = null ) {
	$st = get_option( 'flxrc_status', array() );
	$st = is_array( $st ) ? $st : array();
	if ( null !== $value ) {
		$st[ $key ] = $value;
		update_option( 'flxrc_status', $st, false );
		return $value;
	}
	return null === $key ? $st : ( $st[ $key ] ?? null );
}

function flxrc_status_clear( $key ) {
	$st = get_option( 'flxrc_status', array() );
	if ( is_array( $st ) && isset( $st[ $key ] ) ) {
		unset( $st[ $key ] );
		update_option( 'flxrc_status', $st, false );
	}
}

/** Lucide ikonları (ISC). */
function flxrc_icon( $name, $size = 16 ) {
	$p = array(
		'shield'  => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
		'key'     => '<path d="m15.5 7.5 2.3 2.3a1 1 0 0 0 1.4 0l2.1-2.1a1 1 0 0 0 0-1.4L19 4"/><path d="m21 2-9.6 9.6"/><circle cx="7.5" cy="15.5" r="5.5"/>',
		'list'    => '<path d="M13 5h8"/><path d="M13 12h8"/><path d="M13 19h8"/><path d="m3 17 2 2 4-4"/><path d="m3 7 2 2 4-4"/>',
		'sliders' => '<line x1="21" x2="14" y1="4" y2="4"/><line x1="10" x2="3" y1="4" y2="4"/><line x1="21" x2="12" y1="12" y2="12"/><line x1="8" x2="3" y1="12" y2="12"/><line x1="21" x2="16" y1="20" y2="20"/><line x1="12" x2="3" y1="20" y2="20"/><line x1="14" x2="14" y1="2" y2="6"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="16" x2="16" y1="18" y2="22"/>',
		'check'   => '<path d="M20 6 9 17l-5-5"/>',
		'alert'   => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
		'bot'     => '<path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/>',
		'user'    => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
		'chart'   => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
		'play'    => '<path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"/>',
		'info'    => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
		'ext'     => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
	);
	if ( ! isset( $p[ $name ] ) ) {
		return '';
	}
	return '<svg class="flxrc-i" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p[ $name ] . '</svg>';
}
