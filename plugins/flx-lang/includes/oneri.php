<?php
/**
 * Ziyaretçiye dil önerisi. Varsayılan: Türkiye dışından gelen ilk girişte kendi diline (İngilizce) açılır.
 *
 * Sayfa herkese aynı gelir (önbellek güvenli); karar tarayıcıda verilir:
 *  1. flxl_pref çerezi varsa (ziyaretçi dil seçmiş) hiçbir şey yapılmaz.
 *  2. Arama motoru / önizleme botları hariç.
 *  3. Ülke: /wp-json/flx-lang/v1/geo (Cloudflare CF-IPCountry vb. başlıklar) → yoksa saat dilimi (Europe/Istanbul = TR).
 *  4. Ev ülkesi (TR, KKTC) → varsayılan dil; değilse tarayıcı dili etkin dillerdeyse o, yoksa ayardaki dil (en).
 *  5. Önerilen dil şu anki dilden farklıysa: "redirect" (varsayılan) sayfa çizilmeden yönlendirir ve tercihi çereze yazar;
 *     sonraki girişlerde de seçtiği dile açılır; dil seçiciden Türkçe seçerse Türkçe kalır. "banner" üstte öneri bandı gösterir.
 *  Betik <head> içinde, erteleme olmadan yüklenir (yönlendirmede Türkçe sayfa görünmesin).
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ülke kodu başlıkları (sırayla denenir). Hostinger CDN / Cloudflare / LiteSpeed GeoIP.
 */
function flxl_geo_headers() {
	return (array) apply_filters(
		'flxl_geo_headers',
		array( 'HTTP_CF_IPCOUNTRY', 'HTTP_X_COUNTRY_CODE', 'HTTP_X_GEO_COUNTRY', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'HTTP_X_VERCEL_IP_COUNTRY', 'HTTP_X_APPENGINE_COUNTRY', 'GEOIP_COUNTRY_CODE', 'HTTP_GEOIP_COUNTRY_CODE', 'MM_COUNTRY_CODE' )
	);
}

/**
 * Ziyaretçinin ülkesi (başlıktan) ve kaynağı.
 *
 * @return array{country: string, source: string}
 */
function flxl_geo_country() {
	foreach ( flxl_geo_headers() as $h ) {
		$v = isset( $_SERVER[ $h ] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER[ $h ] ) ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( preg_match( '/^[A-Z]{2}$/', $v ) && 'XX' !== $v && 'T1' !== $v ) {
			return array( 'country' => $v, 'source' => $h );
		}
	}
	return array( 'country' => '', 'source' => '' );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'flx-lang/v1',
			'/geo',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true', // Herkese açık; yalnız ziyaretçinin kendi ülke kodu döner, kayıt yok.
				'callback'            => function () {
					$g = flxl_geo_country();
					$last = get_option( 'flxl_geo_seen' );
					$now  = $g['source'] ? $g['source'] : 'none';
					if ( ! is_array( $last ) || ( $last['source'] ?? '' ) !== $now || ( time() - (int) ( $last['t'] ?? 0 ) ) > DAY_IN_SECONDS ) {
						update_option( 'flxl_geo_seen', array( 'source' => $now, 't' => time() ), false );
					}
					$res = new WP_REST_Response( array( 'country' => $g['country'] ) );
					$res->header( 'Cache-Control', 'no-store, private, max-age=0' );
					$res->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
					$res->header( 'CDN-Cache-Control', 'no-store' );
					$res->header( 'Vary', 'CF-IPCountry' );
					do_action( 'litespeed_control_set_nocache', 'flx-lang geo' );
					return $res;
				},
			)
		);
	}
);

/**
 * Öneri bandı metinleri, hedef dilde (ziyaretçinin dili; sayfanın dili değil).
 */
function flxl_suggest_texts() {
	$key  = 'flxl_suggest_texts_' . md5( FLXL_VERSION . wp_json_encode( flxl_languages() ) );
	$data = get_transient( $key );
	if ( is_array( $data ) ) {
		return $data;
	}
	$data = array();
	foreach ( flxl_languages() as $code => $l ) {
		$switched = switch_to_locale( flxl_language_locale( $code ) );
		$data[ $code ] = array(
			/* translators: %s: language name, e.g. English */
			'msg'  => sprintf( __( 'Bu sayfanın %s sürümü var.', 'flx-lang' ), $l['name'] ),
			/* translators: %s: language name */
			'go'   => sprintf( __( '%s sürümüne geç', 'flx-lang' ), $l['name'] ),
			'stay' => __( 'Bu sürümde kal', 'flx-lang' ),
			'close' => __( 'Kapat', 'flx-lang' ),
		);
		if ( $switched ) {
			restore_previous_locale();
		}
	}
	set_transient( $key, $data, WEEK_IN_SECONDS );
	return $data;
}

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( count( flxl_languages() ) < 2 ) {
			return;
		}
		$mode = flxl_settings()['suggest'];
		wp_register_script( 'flxl-oneri', FLXL_URL . 'assets/js/oneri.js', array(), FLXL_VERSION, array( 'in_footer' => false ) );
		$alts = array();
		foreach ( flxl_switcher_items() as $i ) {
			$alts[ $i['code'] ] = $i['url'];
		}
		$cfg = array(
			'mode'    => in_array( $mode, array( 'banner', 'redirect' ), true ) && ! is_user_logged_in() ? $mode : 'off',
			'cur'     => flxl_current_language(),
			'def'     => flxl_default_language(),
			'to'      => flxl_settings()['suggest_to'],
			'home'    => array_values( array_map( 'strtoupper', (array) flxl_settings()['home_countries'] ) ),
			'alts'    => $alts,
			'geo'     => rest_url( 'flx-lang/v1/geo' ),
			'texts'   => 'off' === $mode ? array() : flxl_suggest_texts(),
			'path'    => COOKIEPATH ? COOKIEPATH : '/',
		);
		wp_add_inline_script( 'flxl-oneri', 'window.flxlData=' . wp_json_encode( $cfg ) . ';', 'before' );
		wp_enqueue_script( 'flxl-oneri' );
		if ( 'off' !== $cfg['mode'] ) {
			wp_enqueue_style( 'flxl-oneri', FLXL_URL . 'assets/css/oneri.css', array(), FLXL_VERSION );
		}
	}
);
