<?php
/**
 * Header/footer her sayfada aynı kalsın: tema güncellenince veya Header/Footer,
 * şablon ya da menü kaydedilince tüm sayfa önbelleğini temizler.
 * (Önbellek eklentileri normalde yalnızca düzenlenen sayfayı temizler; header ise her sayfada.)
 *
 * @package fluxwp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function flx_purge_page_cache() {
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
	do_action( 'litespeed_purge_all' );                     // LiteSpeed Cache.
	do_action( 'cloudflare_purge_everything' );             // Cloudflare eklentisi (destekleyen sürümler).
	if ( function_exists( 'rocket_clean_domain' ) ) {       // WP Rocket.
		rocket_clean_domain();
	}
	if ( function_exists( 'w3tc_flush_all' ) ) {            // W3 Total Cache.
		w3tc_flush_all();
	}
	if ( function_exists( 'wp_cache_clear_cache' ) ) {      // WP Super Cache.
		wp_cache_clear_cache();
	}
	if ( function_exists( 'sg_cachepress_purge_cache' ) ) { // SiteGround.
		sg_cachepress_purge_cache();
	}
	if ( class_exists( 'WpFastestCache' ) ) {               // WP Fastest Cache.
		do_action( 'wpfc_clear_all_cache' );
	}
	if ( class_exists( 'autoptimizeCache' ) ) {             // Autoptimize.
		autoptimizeCache::clearall();
	}
	do_action( 'breeze_clear_all_cache' );                  // Breeze (Cloudways).
}

/**
 * Şablon parçası, şablon, menü veya global stil kaydedilince.
 */
function flx_purge_on_layout_save( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( in_array( $post->post_type, array( 'wp_template_part', 'wp_template', 'wp_navigation', 'wp_global_styles' ), true ) ) {
		flx_purge_page_cache();
	}
}
add_action( 'save_post', 'flx_purge_on_layout_save', 20, 2 );
add_action( 'wp_update_nav_menu', 'flx_purge_page_cache' );
add_action( 'customize_save_after', 'flx_purge_page_cache' );
add_action( 'after_switch_theme', 'flx_purge_page_cache' );

/**
 * Tema dosyaları güncellenince (yeni zip yüklendiğinde) bir kez temizle.
 */
function flx_purge_on_theme_update() {
	if ( get_option( 'flx_cache_version' ) === FLX_VERSION ) {
		return;
	}
	update_option( 'flx_cache_version', FLX_VERSION );
	flx_purge_page_cache();
}
add_action( 'init', 'flx_purge_on_theme_update', 99 );
