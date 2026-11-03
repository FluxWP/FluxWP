<?php
/**
 * Plugin Name:       Flux QR Menu
 * Description:       QR menüleri bu siteden yönetir ve yayınlar: menüler bu sitede kendi adresinde (/menu, menu1.alanadi) ya da ayrı alan adlarındaki menü sitelerinde açılır; menü yöneticisi rolü, QR kod, anlık güncelleme ve tüm değişikliklerin geçmişi.
 * Version:           1.3.3
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * Text Domain:       flx-qr-menu
 * Domain Path:       /languages
 * Update URI:        https://update.yesilturkiyeist.org/flx-qr-menu
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'FLXQ_VERSION' ) ) {
	return;
}

define( 'FLXQ_VERSION', '1.3.3' );
define( 'FLXQ_DB_VERSION', 2 );
define( 'FLXQ_FILE', __FILE__ );
define( 'FLXQ_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXQ_URL', plugin_dir_url( __FILE__ ) );
define( 'FLXQ_CAP', 'flxq_manage_menus' );
define( 'FLXQ_EDIT_CAP', 'flxq_edit_menus' );
define( 'FLXQ_ROLE', 'flxq_menu_manager' );
define( 'FLXQ_NS', 'flx-qr-menu/v1' );
define( 'FLXQ_MAX_SITES', 20 );
define( 'FLXQ_MAX_REVISIONS', 300 );

require_once FLXQ_DIR . 'includes/install.php';
require_once FLXQ_DIR . 'includes/store.php';
require_once FLXQ_DIR . 'includes/access.php';
require_once FLXQ_DIR . 'includes/address.php';
require_once FLXQ_DIR . 'includes/diff.php';
require_once FLXQ_DIR . 'includes/hooks.php';
require_once FLXQ_DIR . 'includes/feed.php';
require_once FLXQ_DIR . 'includes/rest.php';
require_once FLXQ_DIR . 'includes/media.php';
require_once FLXQ_DIR . 'includes/public.php';
require_once FLXQ_DIR . 'includes/checks.php';
require_once FLXQ_DIR . 'includes/mcp.php';

if ( is_admin() ) {
	require_once FLXQ_DIR . 'includes/js-strings.php';
	require_once FLXQ_DIR . 'includes/admin.php';
	require_once FLXQ_DIR . 'includes/settings.php';
}

/*
 * Çeviri: kaynak metinler Türkçe. en_GB, en_AU gibi İngilizce yerel ayarlar en_US çevirisine düşer.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'flx-qr-menu', false, dirname( plugin_basename( FLXQ_FILE ) ) . '/languages' );
	},
	1
);
add_filter(
	'load_textdomain_mofile',
	function ( $mofile, $domain ) {
		if ( 'flx-qr-menu' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) )
			&& preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXQ_DIR . 'languages/flx-qr-menu-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

register_activation_hook( __FILE__, 'flxq_install' );
add_action( 'plugins_loaded', 'flxq_maybe_upgrade', 5 );
add_action( 'init', 'flxq_register_post_type', 5 );

/** Güncellemeler ekranında paket logosu. */
add_filter(
	'flxu_package_icons',
	function ( $icons, $slug ) {
		if ( 'flx-qr-menu' === $slug && ! $icons ) {
			$url   = FLXQ_URL . 'assets/icon.svg?ver=' . FLXQ_VERSION;
			$icons = array( 'svg' => $url, '1x' => $url, '2x' => $url );
		}
		return $icons;
	},
	10,
	2
);

add_filter(
	'plugin_action_links_' . plugin_basename( FLXQ_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=flx-qr-menu' ) ) . '">' . esc_html__( 'QR Menüler', 'flx-qr-menu' ) . '</a>' );
		return $links;
	}
);
