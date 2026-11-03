<?php
/**
 * Plugin Name: Flux SEO
 * Description: Meta başlık/açıklama, canonical, Open Graph, schema (Organization, WebSite, Breadcrumb, Article), sitemap ayarları, favicon ve SEO kontrolü. Ayrıca yapay zekâ asistanlarının (Claude vb.) içerik ve SEO'yu yönetebilmesi için MCP sunucusu.
 * Version: 1.2.7
 * Author: FluxWP
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: flx-seo
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

define( 'FLXSEO_VERSION', '1.2.7' );

if ( ! function_exists( 'array_is_list' ) ) {
	function array_is_list( array $a ) {
		return array() === $a || array_keys( $a ) === range( 0, count( $a ) - 1 );
	}
}
define( 'FLXSEO_FILE', __FILE__ );
define( 'FLXSEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXSEO_URL', plugin_dir_url( __FILE__ ) );

require FLXSEO_DIR . 'includes/ayarlar.php';
require FLXSEO_DIR . 'includes/meta.php';
require FLXSEO_DIR . 'includes/head.php';
require FLXSEO_DIR . 'includes/og-gorsel.php';
require FLXSEO_DIR . 'includes/schema.php';
require FLXSEO_DIR . 'includes/anahtar.php';
require FLXSEO_DIR . 'includes/sitemap.php';
require FLXSEO_DIR . 'includes/indexnow.php';
require FLXSEO_DIR . 'includes/favicon.php';
require FLXSEO_DIR . 'includes/onbellek.php';
require FLXSEO_DIR . 'includes/kontrol.php';
require FLXSEO_DIR . 'includes/mcp.php';
require FLXSEO_DIR . 'includes/sistem.php';
require FLXSEO_DIR . 'includes/mcp-sistem.php';
require FLXSEO_DIR . 'includes/mcp-siparis.php';

/**
 * Başka bir SEO eklentisi (Yoast, Rank Math, AIOSEO, SEOPress) etkinse meta/schema çıktısı kapanır;
 * favicon, SEO kontrolü ve MCP çalışmaya devam eder.
 */
function flxseo_other_seo_plugin() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_VERSION' );
}

function flxseo_output_enabled() {
	return ! flxseo_other_seo_plugin() && (bool) flxseo_opt( 'enabled' );
}

/**
 * SEO alanı olan içerik türleri.
 */
function flxseo_post_types() {
	$types = get_post_types( array( 'public' => true ), 'names' );
	unset( $types['attachment'] );
	return array_values( $types );
}

register_activation_hook(
	__FILE__,
	function () {
		if ( false === get_option( 'flxseo' ) ) {
			add_option( 'flxseo', flxseo_defaults() );
		}
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		flxseo_cache_htaccess( true );
		delete_option( 'flxseo_cache_rules' );
	}
);
