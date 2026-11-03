<?php
/**
 * Plugin Name:       Flux Speed
 * Description:       Site performansı tek yerde: istek başına sorgular, PHP hataları, dış HTTP istekleri (geliştirici paneli), yavaş/hatalı istek kayıtları, sunucu (CPU, RAM, disk, OPcache), veritabanı, autoload, cron ve Sistem Kontrolü. Yapay zekâ hepsini Flux SEO & MCP / Flux AI araçlarıyla okur.
 * Version:           0.2.3
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * Text Domain:       flx-speed
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-speed
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

if ( defined( 'FLXP_VERSION' ) ) {
	return;
}

define( 'FLXP_VERSION', '0.2.3' );
define( 'FLXP_DB_VERSION', 1 );
define( 'FLXP_FILE', __FILE__ );
define( 'FLXP_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXP_URL', plugin_dir_url( __FILE__ ) );

require_once FLXP_DIR . 'includes/settings.php';
require_once FLXP_DIR . 'includes/util.php';
require_once FLXP_DIR . 'includes/collector.php';
require_once FLXP_DIR . 'includes/cron-timing.php';
require_once FLXP_DIR . 'includes/store.php';
require_once FLXP_DIR . 'includes/environment.php';
require_once FLXP_DIR . 'includes/server.php';
require_once FLXP_DIR . 'includes/js-strings.php';
require_once FLXP_DIR . 'includes/panel.php';
require_once FLXP_DIR . 'includes/mcp.php';
require_once FLXP_DIR . 'includes/flx-system-checks.php';
require_once FLXP_DIR . 'includes/system-checks.php';
require_once FLXP_DIR . 'includes/dashboard.php';
if ( is_admin() ) {
	require_once FLXP_DIR . 'includes/admin.php';
}

// Toplama mümkün olduğunca erken başlar (yüklenen eklentilerin sorguları da görünsün).
FLXP_Collector::boot();
FLXP_Cron_Timing::boot();

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
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=flx-speed' ) ) . '">' . esc_html__( 'Performans', 'flx-speed' ) . '</a>' );
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
