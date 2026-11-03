<?php
/**
 * Plugin Name:       Flux Admin
 * Description:       Yönetim panelini baştan tasarlar: rol bazlı düzenlenebilir sol menü, mobil alt bar ve yan panel, WhatsApp / Görevler geçişi, düzenlenebilir Başlangıç modülleri, otomatik açık/koyu tema, komut paleti (Ctrl+K), canlı sayaçlar ve PWA (ana ekrana ekle).
 * Version:           1.6.13
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            FluxWP
 * Text Domain:       flx-admin
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

define( 'FLXA_VERSION', '1.6.13' );
define( 'FLXA_FILE', __FILE__ );
define( 'FLXA_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXA_URL', plugin_dir_url( __FILE__ ) );

require_once FLXA_DIR . 'includes/helpers.php';
require_once FLXA_DIR . 'includes/icons.php';
require_once FLXA_DIR . 'includes/menu.php';
require_once FLXA_DIR . 'includes/chrome.php';
require_once FLXA_DIR . 'includes/dashboard.php';
require_once FLXA_DIR . 'includes/widgets.php';
require_once FLXA_DIR . 'includes/theme.php';
require_once FLXA_DIR . 'includes/caps.php';
require_once FLXA_DIR . 'includes/live.php';
require_once FLXA_DIR . 'includes/pwa.php';
require_once FLXA_DIR . 'includes/favicon.php';
require_once FLXA_DIR . 'includes/login.php';
require_once FLXA_DIR . 'includes/system-checks.php';
require_once FLXA_DIR . 'includes/list.php';
require_once FLXA_DIR . 'includes/page-head.php';

if ( is_admin() ) {
	require_once FLXA_DIR . 'includes/settings-page.php';
}

/*
 * Çeviri: kaynak metinler Türkçe; site/kullanıcı dili başka olduğunda languages/ içindeki çeviri kullanılır.
 * en_GB, en_AU gibi diğer İngilizce yerel ayarları en_US çevirisine düşer.
 */
add_action( 'init', 'flxa_load_textdomain', 1 );
function flxa_load_textdomain() {
	load_plugin_textdomain( 'flx-admin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_filter(
	'load_textdomain_mofile',
	function ( $mofile, $domain ) {
		if ( 'flx-admin' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXA_DIR . 'languages/flx-admin-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

register_activation_hook( __FILE__, 'flxa_activate' );
function flxa_activate() {
	if ( false === get_option( 'flxa_settings' ) ) {
		add_option( 'flxa_settings', array(), '', true );
	}
	if ( false === get_option( 'flxa_menus' ) ) {
		add_option( 'flxa_menus', array(), '', true );
	}
}

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( flxa_settings_url() ) . '">' . esc_html__( 'Ayarlar', 'flx-admin' ) . '</a>' );
		return $links;
	}
);
