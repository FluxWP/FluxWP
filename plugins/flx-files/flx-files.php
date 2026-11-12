<?php
/**
 * Plugin Name:       Flux Files
 * Description:       Modern dosya yöneticisi: site dosyalarına göz at, yükle (sürükle-bırak, büyük dosyalar parça parça), kod editöründe düzenle, ZIP indir/arşivle/aç, izinleri değiştir. Silinenler Çöp kutusu'nda bekler, her kayıtta önceki sürüm saklanır, tüm işlemler Etkinlik'te. Flux AI ve MCP ile dosya listele, oku, ara; yazma ve silme onayla. Uploads içindeki bir klasör [flx_files] kısa koduyla sitede belge kütüphanesi olur.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flx-files
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-files
 *
 * @package FLX_Files
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

if ( defined( 'FLXFM_VERSION' ) ) {
	return;
}

define( 'FLXFM_VERSION', '0.1.0' );
define( 'FLXFM_FILE', __FILE__ );
define( 'FLXFM_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXFM_URL', plugin_dir_url( __FILE__ ) );

require_once FLXFM_DIR . 'includes/helpers.php';
require_once FLXFM_DIR . 'includes/class-flxfm-fs.php';
require_once FLXFM_DIR . 'includes/store.php';
require_once FLXFM_DIR . 'includes/rest.php';
require_once FLXFM_DIR . 'includes/ai.php';
require_once FLXFM_DIR . 'includes/mcp.php';
require_once FLXFM_DIR . 'includes/library.php';
require_once FLXFM_DIR . 'includes/system-checks.php';
if ( is_admin() ) {
	require_once FLXFM_DIR . 'includes/admin.php';
}

/*
 * Çeviri: kaynak metinler İngilizce; Türkçe ve İngilizce paketler languages/ içinde.
 * en_GB, en_AU gibi diğer İngilizce yerel ayarları en_US çevirisine düşer.
 */
add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'flx-files', false, dirname( plugin_basename( FLXFM_FILE ) ) . '/languages' );
	},
	1
);

register_activation_hook(
	__FILE__,
	static function () {
		flxfm_data_dir();
		if ( ! wp_next_scheduled( 'flxfm_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'flxfm_daily' );
		}
	}
);
register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'flxfm_daily' );
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( FLXFM_FILE ),
	static function ( $links ) {
		if ( current_user_can( 'flxfm_manage' ) ) {
			array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=flx-files' ) ) . '">' . esc_html__( 'Open', 'flx-files' ) . '</a>' );
		}
		return $links;
	}
);

/** Güncellemeler ekranında paket logosu. */
add_filter(
	'flxu_package_icons',
	static function ( $icons, $slug ) {
		if ( 'flx-files' === $slug && ! $icons ) {
			$url   = FLXFM_URL . 'assets/icon.svg?ver=' . FLXFM_VERSION;
			$icons = array( 'svg' => $url, '1x' => $url, '2x' => $url );
		}
		return $icons;
	},
	10,
	2
);

/* Flux Admin: sayfa başlığı, menü logosu ve "Sistem" grubunda yer. */
add_filter(
	'flxa_page_heads',
	static function ( $map ) {
		$map['flx-files'] = 'flx-files';
		return $map;
	}
);
add_filter(
	'flxa_package_heads',
	static function ( $heads ) {
		$heads['flx-files'] = array( 'Flux Files', __( 'Browse, edit and share the site files', 'flx-files' ) );
		return $heads;
	}
);
add_filter(
	'flxa_page_head_skip',
	static function ( $skip ) {
		$skip   = is_array( $skip ) ? $skip : array();
		$skip[] = 'flx-files'; // Uygulamanın kendi üst çubuğu var.
		return $skip;
	}
);
add_filter(
	'flxa_menu_logos',
	static function ( $m ) {
		$m['flx-files'] = 'flx-files.svg';
		return $m;
	}
);
add_filter(
	'flxa_package_logo_url',
	static function ( $url, $file, $style = '' ) {
		if ( 'flx-files.svg' === $file ) {
			$url = FLXFM_URL . ( 'pastel' === $style ? 'assets/icon-pastel.svg' : 'assets/icon.svg' ) . '?ver=' . FLXFM_VERSION;
		}
		return $url;
	},
	10,
	3
);
add_filter(
	'flxa_default_groups',
	static function ( $groups ) {
		if ( isset( $groups['system'][1] ) && is_array( $groups['system'][1] ) && ! in_array( 'flx-files', $groups['system'][1], true ) ) {
			$at = array_search( 'flx-backup', $groups['system'][1], true );
			array_splice( $groups['system'][1], false === $at ? 1 : $at + 1, 0, array( 'flx-files' ) );
		}
		return $groups;
	}
);
