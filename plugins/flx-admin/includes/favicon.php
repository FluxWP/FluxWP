<?php
/**
 * Favicon: site simgesi (Görünüm › Özelleştir › Site kimliği) seçilmemişse yönetim panelinde ve giriş
 * ekranında FluxWP simgesi gösterilir. Site simgesi seçildiyse WordPress'inki kullanılır, Flux ezmez.
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

function flxa_favicon_head() {
	if ( has_site_icon() ) {
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
}
add_action( 'admin_head', 'flxa_favicon_head', 1 );
add_action( 'login_head', 'flxa_favicon_head', 1 );

/** Ön yüz: Flux SEO ya da temanın kurum simgesi favicon yazmıyorsa FluxWP simgesi. */
add_action(
	'wp_head',
	static function () {
		if ( defined( 'FLXSEO_VERSION' ) || ( function_exists( 'flx_logo_files' ) && '' !== (string) ( flx_logo_files()['icon'] ?? '' ) ) ) {
			return;
		}
		flxa_favicon_head();
	},
	99
);
