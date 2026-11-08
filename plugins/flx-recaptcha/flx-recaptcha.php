<?php
/**
 * Plugin Name:       Flux reCAPTCHA
 * Description:       Google reCAPTCHA (v3 görünmez ya da v2 kutucuk) ile giriş, kayıt, şifre sıfırlama, yorum, WooCommerce hesap ve ödeme, Flux Donate bağış formu ve Flux Forms gönderimlerini spam ve bot saldırılarına karşı korur. Hangi formun korunacağı panelden açılıp kapanır; Flux Speed özel giriş adresiyle uyumludur.
 * Version:           0.2.2
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flx-recaptcha
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-recaptcha
 *
 * @package FLX_Recaptcha
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

if ( defined( 'FLXRC_VERSION' ) ) {
	return;
}

define( 'FLXRC_VERSION', '0.2.2' );
define( 'FLXRC_FILE', __FILE__ );
define( 'FLXRC_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXRC_URL', plugin_dir_url( __FILE__ ) );

require_once FLXRC_DIR . 'includes/helpers.php';
require_once FLXRC_DIR . 'includes/contexts.php';
require_once FLXRC_DIR . 'includes/class-flxrc-verify.php';
require_once FLXRC_DIR . 'includes/class-flxrc-front.php';
require_once FLXRC_DIR . 'includes/integrations.php';
require_once FLXRC_DIR . 'includes/api.php';
require_once FLXRC_DIR . 'includes/system-checks.php';
require_once FLXRC_DIR . 'includes/app.php';
if ( is_admin() ) {
	require_once FLXRC_DIR . 'includes/admin.php';
}

/*
 * Çeviri: kaynak metinler Türkçe; site/kullanıcı dili İngilizce olduğunda languages/ içindeki çeviri kullanılır.
 * en_GB, en_AU gibi diğer İngilizce yerel ayarları en_US çevirisine düşer.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'flx-recaptcha', false, dirname( plugin_basename( FLXRC_FILE ) ) . '/languages' );
	},
	1
);
add_filter(
	'load_textdomain_mofile',
	function ( $mofile, $domain ) {
		if ( 'flx-recaptcha' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXRC_DIR . 'languages/flx-recaptcha-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

add_action(
	'plugins_loaded',
	function () {
		FLXRC_Integrations::init();
		FLXRC_Front::init();
		if ( is_admin() ) {
			FLXRC_Admin::init();
		}
		do_action( 'flxrc_loaded' );
	},
	15
);

add_filter(
	'plugin_action_links_' . plugin_basename( FLXRC_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( flxrc_admin_url() ) . '">' . esc_html__( 'Settings', 'flx-recaptcha' ) . '</a>' );
		return $links;
	}
);

/** Güncellemeler ekranında paket logosu. */
add_filter(
	'flxu_package_icons',
	function ( $icons, $slug ) {
		if ( 'flx-recaptcha' === $slug && ! $icons ) {
			$url   = FLXRC_URL . 'assets/icon.svg?ver=' . FLXRC_VERSION;
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
		$map['flx-recaptcha'] = 'flx-recaptcha';
		return $map;
	}
);
add_filter(
	'flxa_package_heads',
	function ( $heads ) {
		$heads['flx-recaptcha'] = array( 'Flux reCAPTCHA', __( 'Protects forms against spam and bot attacks', 'flx-recaptcha' ) );
		return $heads;
	}
);
add_filter(
	'flxa_menu_logos',
	function ( $m ) {
		$m['flx-recaptcha'] = 'flx-recaptcha.svg';
		return $m;
	}
);
add_filter(
	'flxa_default_groups',
	function ( $groups ) {
		if ( isset( $groups['system'][1] ) && is_array( $groups['system'][1] ) && ! in_array( 'flx-recaptcha', $groups['system'][1], true ) ) {
			$at = array_search( 'flx-speed', $groups['system'][1], true );
			array_splice( $groups['system'][1], false === $at ? 0 : $at + 1, 0, array( 'flx-recaptcha' ) );
		}
		return $groups;
	}
);
