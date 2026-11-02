<?php
/**
 * Flux Menu Theme: menu.example.org için en sade block theme. Ana sayfa boş ve beyaz; QR menüler Flux QR Menu eklentisinden gelir.
 *
 * @package flx-menu-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'YF_VERSION', '1.2.1' );

require_once __DIR__ . '/inc/ayarlar.php';
require_once __DIR__ . '/inc/kapat.php';
require_once __DIR__ . '/inc/gorunmez.php';
require_once __DIR__ . '/inc/kurulum.php';
require_once __DIR__ . '/inc/sistem.php';

/** Çeviri (TR kaynak, en_US çeviri; diğer İngilizce yerel ayarlar en_US'e düşer). */
function yf_setup() {
	load_theme_textdomain( 'flx-menu-theme', get_template_directory() . '/languages' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'responsive-embeds' );
}
add_action( 'after_setup_theme', 'yf_setup', 1 );

function yf_english_fallback( $mofile, $domain ) {
	if ( 'flx-menu-theme' === $domain && ! is_readable( $mofile ) && preg_match( '/en_[A-Z]{2}\.mo$/', $mofile ) ) {
		return get_template_directory() . '/languages/en_US.mo';
	}
	return $mofile;
}
add_filter( 'load_textdomain_mofile', 'yf_english_fallback', 10, 2 );

/** Sayfa her zaman açık renk: cihaz koyu temada olsa da beyaz kalır (Dark Reader vb. de koyulaştırmaz). */
function yf_head_meta() {
	echo '<meta name="color-scheme" content="only light">' . "\n";
	echo '<meta name="darkreader-lock">' . "\n";
}
add_action( 'wp_head', 'yf_head_meta', 1 );

/** Desen kategorisi. */
function yf_pattern_category() {
	register_block_pattern_category( 'flx-menu-theme', array( 'label' => __( 'Flux Menu Theme', 'flx-menu-theme' ) ) );
}
add_action( 'init', 'yf_pattern_category' );
