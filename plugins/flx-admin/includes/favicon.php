<?php
/**
 * Favicon: site simgesi (Görünüm › Özelleştir › Site kimliği) seçilmemişse yönetim panelinde ve giriş
 * ekranında kurum profili simgesi, o da yoksa FluxWP simgesi gösterilir. Site simgesi seçildiyse WordPress'inki kullanılır, Flux ezmez.
 * Filtre `flxa_favicon` (dosya adresleri dizisi) ile değiştirilebilir; boş dizi döndürmek kapatır.
 */
defined( 'ABSPATH' ) || exit;

function flxa_favicon_files() {
	$v = '?v=' . FLXA_VERSION;
	return (array) apply_filters(
		'flxa_favicon',
		array(
			'svg'   => FLXA_URL . 'assets/icons/favicon.svg' . $v,
			'png'   => FLXA_URL . 'assets/icons/favicon-32.png' . $v,
			'apple' => FLXA_URL . 'assets/icons/apple-touch-icon.png' . $v,
		)
	);
}

/**
 * Sitenin kendi simgesi (site simgesi seçilmemişken): sol menü logosuyla aynı sıra, kurum profili simgesi
 * (tema ve ön ayar filtreleri flx_logo_files dahil). Filtre `flx_custom_favicon`. Boşsa FluxWP simgesi.
 */
function flxa_custom_favicon() {
	static $url = null;
	if ( null !== $url ) {
		return $url;
	}
	$url = '';
	if ( function_exists( 'flx_logo_files' ) ) {
		$url = (string) ( flx_logo_files()['icon'] ?? '' );
	}
	if ( '' === $url && function_exists( 'flx_profile' ) ) {
		$icon = flx_profile( 'icon', '' );
		if ( is_numeric( $icon ) && (int) $icon > 0 ) {
			$url = (string) wp_get_attachment_url( (int) $icon );
		} elseif ( is_string( $icon ) && preg_match( '#^(https?:)?//|^/#', $icon ) ) {
			$url = $icon;
		}
	}
	$url = (string) apply_filters( 'flx_custom_favicon', $url );
	return $url;
}

function flxa_favicon_head() {
	if ( has_site_icon() ) {
		return;
	}
	$custom = flxa_custom_favicon();
	if ( '' !== $custom ) {
		$svg = (bool) preg_match( '#\.svg($|\?)#i', $custom );
		echo '<link rel="icon"' . ( $svg ? ' type="image/svg+xml" sizes="any"' : '' ) . ' href="' . esc_url( $custom ) . '">' . "\n";
		do_action( 'flx_favicon_printed' );
		return;
	}
	$f = flxa_favicon_files();
	if ( ! empty( $f['svg'] ) ) {
		echo '<link rel="icon" type="image/svg+xml" href="' . esc_url( $f['svg'] ) . '">' . "\n";
	}
	if ( ! empty( $f['png'] ) ) {
		echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url( $f['png'] ) . '">' . "\n";
	}
	// PWA açıkken apple-touch-icon'u flxa_pwa_head yazar.
	if ( ! empty( $f['apple'] ) && ! ( function_exists( 'flxa_pwa_on' ) && flxa_pwa_on() ) ) {
		echo '<link rel="apple-touch-icon" href="' . esc_url( $f['apple'] ) . '">' . "\n";
	}
	do_action( 'flx_favicon_printed' );
}
add_action( 'admin_head', 'flxa_favicon_head', 1 );
add_action( 'login_head', 'flxa_favicon_head', 1 );

/**
 * Ön yüz: favicon'u Flux SEO yazar (sitenin simgesi varsa yalnız onu). Flux SEO yoksa sitenin simgesini
 * tema yazar; bu yalnız ikisi de yazmadıysa ve sitenin simgesi yoksa FluxWP simgesini ekler.
 */
add_action(
	'wp_head',
	static function () {
		if ( defined( 'FLXSEO_VERSION' ) || did_action( 'flx_favicon_printed' ) || '' !== flxa_custom_favicon() ) {
			return;
		}
		flxa_favicon_head();
	},
	99
);
