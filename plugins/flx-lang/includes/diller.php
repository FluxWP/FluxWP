<?php
/**
 * Dil listesi ve ayarlar.
 *
 * Ayarlar tek seçenekte (flxl_settings). Varsayılan dil (tr) adreste önek almaz; diğer diller /<kod>/ ile başlar.
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

/**
 * Panelden eklenebilecek diller. Kod = adres öneki ve hreflang.
 */
function flxl_catalog() {
	$list = array(
		'tr' => array( 'name' => 'Türkçe', 'locale' => 'tr_TR', 'flag' => '🇹🇷' ),
		'en' => array( 'name' => 'English', 'locale' => 'en_US', 'flag' => '🇬🇧' ),
		'de' => array( 'name' => 'Deutsch', 'locale' => 'de_DE', 'flag' => '🇩🇪' ),
		'fr' => array( 'name' => 'Français', 'locale' => 'fr_FR', 'flag' => '🇫🇷' ),
		'nl' => array( 'name' => 'Nederlands', 'locale' => 'nl_NL', 'flag' => '🇳🇱' ),
		'es' => array( 'name' => 'Español', 'locale' => 'es_ES', 'flag' => '🇪🇸' ),
		'it' => array( 'name' => 'Italiano', 'locale' => 'it_IT', 'flag' => '🇮🇹' ),
		'ru' => array( 'name' => 'Русский', 'locale' => 'ru_RU', 'flag' => '🇷🇺' ),
		'ar' => array( 'name' => 'العربية', 'locale' => 'ar', 'flag' => '🇸🇦' ),
		'az' => array( 'name' => 'Azərbaycanca', 'locale' => 'az', 'flag' => '🇦🇿' ),
		'ka' => array( 'name' => 'ქართული', 'locale' => 'ka_GE', 'flag' => '🇬🇪' ),
		'fa' => array( 'name' => 'فارسی', 'locale' => 'fa_IR', 'flag' => '🇮🇷' ),
		'uk' => array( 'name' => 'Українська', 'locale' => 'uk', 'flag' => '🇺🇦' ),
		'pt-br' => array( 'name' => 'Português do Brasil', 'locale' => 'pt_BR', 'flag' => '🇧🇷' ),
		'ja' => array( 'name' => '日本語', 'locale' => 'ja', 'flag' => '🇯🇵' ),
		'zh' => array( 'name' => '中文', 'locale' => 'zh_CN', 'flag' => '🇨🇳' ),
	);
	return (array) apply_filters( 'flxl_catalog', $list );
}

function flxl_default_settings() {
	return array(
		'default'     => 'tr',
		'languages'   => array(
			'tr' => array( 'name' => 'Türkçe', 'locale' => 'tr_TR', 'flag' => '🇹🇷', 'active' => 1 ),
			'en' => array( 'name' => 'English', 'locale' => 'en_US', 'flag' => '🇬🇧', 'active' => 1 ),
		),
		'post_types'  => array( 'post', 'page', 'proje', 'etkinlik' ),
		'fallback'    => 1,          // Çevirisi olmayan içerik diğer dilde varsayılan dilde gösterilir.
		'suggest'     => 'redirect', // off | banner | redirect.
		'suggest_to'  => 'en',       // Yabancı ziyaretçiye önerilen dil (tarayıcı dili aktif değilse).
		'home_countries' => array( 'TR', 'CY' ),
		'switcher'    => 'code',     // code | name | flag.
		'switcher_place' => 'footer', // footer | header | both: temanın başlığı/alt bilgisi.
		'texts'       => array(),    // dil => [ anahtar => metin ] (site adı, slogan, Flux SEO ana sayfa başlığı…).
	);
}

function flxl_settings( $reset = false ) {
	static $s = null;
	if ( null === $s || $reset ) {
		$s = wp_parse_args( (array) get_option( 'flxl_settings', array() ), flxl_default_settings() );
		if ( empty( $s['languages'] ) || ! is_array( $s['languages'] ) ) {
			$s['languages'] = flxl_default_settings()['languages'];
		}
	}
	return $s;
}

foreach ( array( 'add_option_flxl_settings', 'update_option_flxl_settings', 'delete_option_flxl_settings' ) as $flxl_hook ) {
	add_action(
		$flxl_hook,
		function () {
			flxl_settings( true );
		},
		1
	);
}

/**
 * Varsayılan dil kodu (adreste önek yok).
 */
function flxl_default_language() {
	$d = flxl_settings()['default'];
	return isset( flxl_settings()['languages'][ $d ] ) ? $d : (string) key( flxl_settings()['languages'] );
}

/**
 * Etkin diller: kod => [name, locale, flag]. Varsayılan dil her zaman ilk ve etkin.
 *
 * @param bool $all Pasif diller de gelsin.
 */
function flxl_languages( $all = false ) {
	$out = array();
	$def = flxl_default_language();
	foreach ( flxl_settings()['languages'] as $code => $l ) {
		if ( ! $all && empty( $l['active'] ) && $code !== $def ) {
			continue;
		}
		$out[ $code ] = array(
			'code'   => $code,
			'name'   => (string) ( $l['name'] ?? $code ),
			'locale' => (string) ( $l['locale'] ?? $code ),
			'flag'   => (string) ( $l['flag'] ?? '' ),
			'active' => $code === $def || ! empty( $l['active'] ),
		);
	}
	if ( isset( $out[ $def ] ) ) {
		$out = array( $def => $out[ $def ] ) + $out;
	}
	return $out;
}

function flxl_language_codes() {
	return array_keys( flxl_languages() );
}

function flxl_is_language( $code ) {
	return is_string( $code ) && isset( flxl_languages()[ $code ] );
}

/**
 * The configured site-content locale, independent of the WordPress admin language.
 */
function flxl_language_locale( $code ) {
	return flxl_languages()[ $code ]['locale'] ?? 'en_US';
}

/**
 * hreflang değeri (tr, en, de…; bölgesel kod gerekirse flxl_hreflang filtresi).
 */
function flxl_hreflang( $code ) {
	return (string) apply_filters( 'flxl_hreflang', $code, flxl_language_locale( $code ) );
}

function flxl_language_name( $code ) {
	return flxl_languages( true )[ $code ]['name'] ?? strtoupper( (string) $code );
}

/**
 * Çevrilebilir içerik türleri (ayar + var olan türler).
 */
function flxl_post_types() {
	$types = array();
	foreach ( (array) flxl_settings()['post_types'] as $t ) {
		if ( post_type_exists( $t ) ) {
			$types[] = $t;
		}
	}
	return (array) apply_filters( 'flxl_post_types', $types );
}

function flxl_is_translatable_type( $type ) {
	return in_array( $type, flxl_post_types(), true );
}

function flxl_fallback() {
	return ! empty( flxl_settings()['fallback'] );
}
