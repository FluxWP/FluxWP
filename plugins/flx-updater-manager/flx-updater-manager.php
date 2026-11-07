<?php
/**
 * Plugin Name:       Flux Updater Manager
 * Description:       FluxWP güncelleme sunucusunu (Flux Update Server) bu WordPress panelinden yönetir: genel bakış ve ülke istatistikleri, bağlanan siteler, site anahtarları, paketler, GitHub depoları, güvenlik anahtarları, ülke erişimi ve sunucu kaynakları. Sunucunun /admin panelinde üretilen yönetici anahtarıyla bağlanır.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flx-updater-manager
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-updater-manager
 *
 * @package FLX_Updater_Manager
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

if ( defined( 'FLXUM_VERSION' ) ) {
	return;
}

define( 'FLXUM_VERSION', '0.1.0' );
define( 'FLXUM_FILE', __FILE__ );
define( 'FLXUM_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXUM_URL', plugin_dir_url( __FILE__ ) );
define( 'FLXUM_DEFAULT_SERVER', 'https://updater.fluxwp.com' );

require_once FLXUM_DIR . 'includes/helpers.php';
require_once FLXUM_DIR . 'includes/class-flxum-client.php';
if ( is_admin() ) {
	require_once FLXUM_DIR . 'includes/class-flxum-admin.php';
	require_once FLXUM_DIR . 'includes/class-flxum-views.php';
}

/*
 * Çeviri: kaynak metinler Türkçe; site/kullanıcı dili İngilizce olduğunda languages/ içindeki çeviri kullanılır.
 * en_GB, en_AU gibi diğer İngilizce yerel ayarları en_US çevirisine düşer.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'flx-updater-manager', false, dirname( plugin_basename( FLXUM_FILE ) ) . '/languages' );
	},
	1
);
add_filter(
	'load_textdomain_mofile',
	function ( $mofile, $domain ) {
		if ( 'flx-updater-manager' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXUM_DIR . 'languages/flx-updater-manager-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

add_action(
	'plugins_loaded',
	function () {
		if ( is_admin() ) {
			FLXUM_Admin::init();
		}
	},
	15
);

add_filter(
	'plugin_action_links_' . plugin_basename( FLXUM_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( flxum_admin_url() ) . '">' . esc_html__( 'Yönet', 'flx-updater-manager' ) . '</a>' );
		return $links;
	}
);

/** Güncellemeler ekranında paket logosu. */
add_filter(
	'flxu_package_icons',
	function ( $icons, $slug ) {
		if ( 'flx-updater-manager' === $slug && ! $icons ) {
			$url   = FLXUM_URL . 'assets/icon.svg?ver=' . FLXUM_VERSION;
			$icons = array( 'svg' => $url, '1x' => $url, '2x' => $url );
		}
		return $icons;
	},
	10,
	2
);

/* Flux Admin: sayfa başlığı (logo + açıklama), menü logosu ve "Sistem" grubunda yer. */
add_filter(
	'flxa_page_heads',
	function ( $map ) {
		$map['flx-updater-manager'] = 'flx-updater-manager';
		return $map;
	}
);
add_filter(
	'flxa_package_heads',
	function ( $heads ) {
		$heads['flx-updater-manager'] = array( 'Flux Updater Manager', __( 'Güncelleme sunucusunu WordPress panelinden yönetir', 'flx-updater-manager' ) );
		return $heads;
	}
);
add_filter(
	'flxa_menu_logos',
	function ( $m ) {
		$m['flx-updater-manager'] = 'flx-updater-manager.svg';
		return $m;
	}
);
add_filter(
	'flxa_default_groups',
	function ( $groups ) {
		if ( isset( $groups['system'][1] ) && is_array( $groups['system'][1] ) && ! in_array( 'flx-updater-manager', $groups['system'][1], true ) ) {
			$groups['system'][1][] = 'flx-updater-manager';
		}
		return $groups;
	}
);
