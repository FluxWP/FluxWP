<?php
/**
 * Yardımcılar: ayarlar, yönetici anahtarının şifreli saklanması, biçimleme.
 *
 * @package FLX_Updater_Manager
 */

defined( 'ABSPATH' ) || exit;

function flxum_admin_url( $tab = '', $args = array() ) {
	$q = array( 'page' => 'flx-updater-manager' );
	if ( $tab ) {
		$q['tab'] = $tab;
	}
	return add_query_arg( $q + $args, admin_url( 'admin.php' ) );
}

/** Ayarlar: server, key (şifreli). */
function flxum_settings() {
	$s = get_option( 'flxum_settings', array() );
	return wp_parse_args( is_array( $s ) ? $s : array(), array( 'server' => '', 'key' => '' ) );
}

/* ------------------------------------------------------------------ */
/* Şifreli saklama (anahtar düz metin olarak veritabanına yazılmaz)     */
/* ------------------------------------------------------------------ */

function flxum_crypto_key() {
	return sodium_crypto_generichash( 'flx-updater-manager|' . wp_salt( 'secure_auth' ), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
}

function flxum_seal( $plain ) {
	$plain = (string) $plain;
	if ( '' === $plain ) {
		return '';
	}
	if ( function_exists( 'sodium_crypto_secretbox' ) ) {
		try {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			return 's1:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, flxum_crypto_key() ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		} catch ( Exception $e ) {
			unset( $e );
		}
	}
	return 'p1:' . base64_encode( $plain ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
}

function flxum_unseal( $stored ) {
	$stored = (string) $stored;
	if ( 0 === strpos( $stored, 'p1:' ) ) {
		return (string) base64_decode( substr( $stored, 3 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}
	if ( 0 === strpos( $stored, 's1:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
		$raw = base64_decode( substr( $stored, 3 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		try {
			$out = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), flxum_crypto_key() );
			return false === $out ? '' : $out;
		} catch ( Exception $e ) {
			return '';
		}
	}
	return '';
}

/* ------------------------------------------------------------------ */
/* Biçimleme                                                           */
/* ------------------------------------------------------------------ */

function flxum_num( $n ) {
	return number_format_i18n( (float) $n );
}

function flxum_size( $bytes ) {
	$bytes = (int) $bytes;
	return $bytes > 0 ? size_format( $bytes, $bytes >= 1048576 ? 1 : 0 ) : '0 B';
}

/** "3 saat önce": FluxWP ortak tarih yardımcısı (flx-brand). */
function flxum_ago( $ts ) {
	$ts = (int) $ts;
	if ( ! $ts ) {
		return '—';
	}
	return function_exists( 'flx_time_ago' ) ? flx_time_ago( $ts ) : gmdate( 'Y-m-d H:i', $ts );
}

function flxum_date( $ts, $format = 'datetime' ) {
	$ts = (int) $ts;
	if ( ! $ts ) {
		return '—';
	}
	return function_exists( 'flx_date' ) ? flx_date( $ts, $format ) : gmdate( 'Y-m-d H:i', $ts );
}

/** Sunucudan gelen SVG'ler (grafik, harita) için izinli etiketler. */
function flxum_kses_svg( $svg ) {
	$common = array(
		'class'  => true,
		'data-t' => true,
	);
	return wp_kses(
		(string) $svg,
		array(
			'svg'    => array( 'viewbox' => true, 'role' => true, 'aria-label' => true, 'xmlns' => true ),
			'g'      => array( 'class' => true ),
			'line'   => array( 'x1' => true, 'x2' => true, 'y1' => true, 'y2' => true ),
			'text'   => array( 'x' => true, 'y' => true, 'text-anchor' => true ) + $common,
			'rect'   => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true ) + $common,
			'circle' => array( 'cx' => true, 'cy' => true, 'r' => true ) + $common,
			'path'   => array( 'd' => true ) + $common,
		)
	);
}
