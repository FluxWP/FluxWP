<?php
/**
 * Plugin Name:       Flux Speed
 * Description:       Site performansı tek yerde: istek başına sorgular, PHP hataları, dış HTTP istekleri (geliştirici paneli), yavaş/hatalı istek kayıtları, sunucu (CPU, RAM, disk, OPcache), veritabanı, autoload, cron ve Sistem Kontrolü. Yapay zekâ hepsini Flux AI & MCP / Flux AI araçlarıyla okur.
 * Version:           0.11.9
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Requires Plugins:  flx-graphql
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * Text Domain:       flx-speed
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-speed
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

if ( defined( 'FLXP_VERSION' ) ) {
	return;
}

define( 'FLXP_VERSION', '0.11.9' );
define( 'FLXP_DB_VERSION', 1 );
define( 'FLXP_FILE', __FILE__ );
define( 'FLXP_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXP_URL', plugin_dir_url( __FILE__ ) );

require_once FLXP_DIR . 'includes/settings.php';
require_once FLXP_DIR . 'includes/services/class-flxp-speed-service.php';
require_once FLXP_DIR . 'includes/optimize.php';
require_once FLXP_DIR . 'includes/util.php';
require_once FLXP_DIR . 'includes/login-url.php';
require_once FLXP_DIR . 'includes/collector.php';
require_once FLXP_DIR . 'includes/cron-timing.php';
require_once FLXP_DIR . 'includes/store.php';
require_once FLXP_DIR . 'includes/environment.php';
require_once FLXP_DIR . 'includes/server.php';
require_once FLXP_DIR . 'includes/cache.php';
require_once FLXP_DIR . 'includes/lean-rest.php';
require_once FLXP_DIR . 'includes/js-strings.php';
require_once FLXP_DIR . 'includes/panel.php';
require_once FLXP_DIR . 'includes/mcp.php';
require_once FLXP_DIR . 'includes/flx-system-checks.php';
require_once FLXP_DIR . 'includes/system-checks.php';
require_once FLXP_DIR . 'includes/dashboard.php';
require_once FLXP_DIR . 'includes/app.php';
if ( is_admin() ) {
	require_once FLXP_DIR . 'includes/admin.php';
}

// Toplama mümkün olduğunca erken başlar (yüklenen eklentilerin sorguları da görünsün).
FLXP_Collector::boot();
FLXP_Cron_Timing::boot();

/*
 * GraphQL (Flux GraphQL): schema only inside a GraphQL request; the persisted operations the Speed
 * screen sends. Without Flux GraphQL (0.2.0+, capability model) the schema is left out and the
 * admin screen says it cannot open.
 */
add_action(
	'flx_gql_register',
	static function () {
		if ( ! function_exists( 'flx_gql_all_caps' ) ) {
			return;
		}
		require_once FLXP_DIR . 'includes/graphql/schema.php';
	}
);
add_filter(
	'flx_gql_persisted_manifests',
	static function ( $m ) {
		$m              = is_array( $m ) ? $m : array();
		$m['flx-speed'] = FLXP_DIR . 'graphql/persisted-queries.json';
		return $m;
	}
);
add_filter(
	'flx_gql_capabilities',
	static function ( $caps ) {
		$caps = is_array( $caps ) ? $caps : array();
		if ( ! isset( $caps['flxp_view'] ) ) {
			$caps['flxp_view'] = array(
				'label'   => __( 'View Flux Speed', 'flx-speed' ),
				'package' => 'flx-speed',
			);
		}
		return $caps;
	}
);

/*
 * Çeviri: kaynak metinler Türkçe; kullanıcı dili İngilizce olduğunda languages/ içindeki çeviri kullanılır.
 * en_GB, en_AU gibi diğer İngilizce yerel ayarları en_US çevirisine düşer.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'flx-speed', false, dirname( plugin_basename( FLXP_FILE ) ) . '/languages' );
	},
	1
);
add_filter(
	'load_textdomain_mofile',
	function ( $mofile, $domain ) {
		if ( 'flx-speed' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXP_DIR . 'languages/flx-speed-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

register_activation_hook( FLXP_FILE, 'flxp_install' );
add_action( 'plugins_loaded', 'flxp_maybe_upgrade' );

add_filter(
	'plugin_action_links_' . plugin_basename( FLXP_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=flx-speed' ) ) . '">' . esc_html__( 'Performance', 'flx-speed' ) . '</a>' );
		return $links;
	}
);

/** Flux Roles: yetki listesine "Performansı görüntüle". */
add_filter(
	'flx_role_caps_supported',
	function ( $caps ) {
		return array_merge( (array) $caps, array( 'flxp_view' ) );
	}
);

/** Güncellemeler ekranında paket logosu. */
add_filter(
	'flxu_package_icons',
	function ( $icons, $slug ) {
		if ( 'flx-speed' === $slug && ! $icons ) {
			$url   = FLXP_URL . 'assets/icon.svg?ver=' . FLXP_VERSION;
			$icons = array( 'svg' => $url, '1x' => $url, '2x' => $url );
		}
		return $icons;
	},
	10,
	2
);
