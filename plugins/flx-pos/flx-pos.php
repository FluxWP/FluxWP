<?php
/**
 * Plugin Name:       Flux POS Entegrator
 * Description:       WooCommerce için ortak sanal POS: Türkiye'deki bankalar (Akbank, Garanti BBVA, İş Bankası, Yapı Kredi, Ziraat, Halkbank, Vakıfbank, QNB, Denizbank, TEB, Kuveyt Türk…) ve ödeme kuruluşları (iyzico, PayTR, Sipay, Param, Craftgate, Moka, Paratika, EsnekPOS…) tek eklentide. Kendi kart formu (klasik ve blok ödeme), 3D Secure, test modu, taksit, siteden iade, işlem kayıtları ve sitedeki tüm ödeme yöntemlerinin yetenek tablosu.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flx-pos
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-pos
 * WC requires at least: 8.0
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

if ( defined( 'FLXPOS_VERSION' ) ) {
	return;
}

define( 'FLXPOS_VERSION', '0.1.0' );
define( 'FLXPOS_DB', 1 );
define( 'FLXPOS_FILE', __FILE__ );
define( 'FLXPOS_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXPOS_URL', plugin_dir_url( __FILE__ ) );

require_once FLXPOS_DIR . 'includes/helpers.php';
require_once FLXPOS_DIR . 'includes/class-flxpos-vault.php';
require_once FLXPOS_DIR . 'includes/class-flxpos-txn.php';
require_once FLXPOS_DIR . 'includes/class-flxpos-providers.php';
require_once FLXPOS_DIR . 'includes/class-flxpos-accounts.php';
require_once FLXPOS_DIR . 'includes/class-flxpos-payment.php';
require_once FLXPOS_DIR . 'includes/drivers/class-flxpos-driver.php';
require_once FLXPOS_DIR . 'includes/class-flxpos-installments.php';
require_once FLXPOS_DIR . 'includes/class-flxpos-flow.php';
require_once FLXPOS_DIR . 'includes/class-flxpos-gateways.php';
require_once FLXPOS_DIR . 'includes/class-flxpos-methods.php';
require_once FLXPOS_DIR . 'includes/class-flxpos-esnekpos-module.php';
require_once FLXPOS_DIR . 'includes/rest.php';
require_once FLXPOS_DIR . 'includes/api.php';
require_once FLXPOS_DIR . 'includes/system-checks.php';
if ( is_admin() ) {
	require_once FLXPOS_DIR . 'includes/admin.php';
}

register_activation_hook( FLXPOS_FILE, array( 'FLXPOS_Txn', 'install' ) );

/*
 * Çeviri: kaynak metinler Türkçe; site/kullanıcı dili İngilizce olduğunda languages/ içindeki çeviri kullanılır.
 * en_GB, en_AU gibi diğer İngilizce yerel ayarları en_US çevirisine düşer.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'flx-pos', false, dirname( plugin_basename( FLXPOS_FILE ) ) . '/languages' );
	},
	1
);
add_filter(
	'load_textdomain_mofile',
	function ( $mofile, $domain ) {
		if ( 'flx-pos' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXPOS_DIR . 'languages/flx-pos-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

/* WooCommerce HPOS ve blok ödeme uyumu. */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FLXPOS_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', FLXPOS_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		FLXPOS_Txn::maybe_upgrade();
		FLXPOS_Rest::init();
		FLXPOS_Flow::init();
		if ( class_exists( 'WooCommerce' ) ) {
			FLXPOS_Gateways::init();
			FLXPOS_Methods::init();
			FLXPOS_Esnekpos_Module::init();
		}
		if ( is_admin() ) {
			FLXPOS_Admin::init();
		}
		do_action( 'flxpos_loaded' );
	},
	15
);

add_filter(
	'plugin_action_links_' . plugin_basename( FLXPOS_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( flxpos_admin_url() ) . '">' . esc_html__( 'POS hesapları', 'flx-pos' ) . '</a>' );
		return $links;
	}
);

/** Güncellemeler ekranında paket logosu. */
add_filter(
	'flxu_package_icons',
	function ( $icons, $slug ) {
		if ( 'flx-pos' === $slug && ! $icons ) {
			$url   = FLXPOS_URL . 'assets/icon.svg?ver=' . FLXPOS_VERSION;
			$icons = array( 'svg' => $url, '1x' => $url, '2x' => $url );
		}
		return $icons;
	},
	10,
	2
);

/* Flux Admin: sayfa başlığı (logo + açıklama) ve menü logosu. */
add_filter(
	'flxa_page_heads',
	function ( $map ) {
		$map['flx-pos'] = 'flx-pos';
		return $map;
	}
);
add_filter(
	'flxa_package_heads',
	function ( $heads ) {
		$heads['flx-pos'] = array( 'Flux POS Entegrator', __( 'Bankalar ve ödeme kuruluşlarıyla kartlı ödeme', 'flx-pos' ) );
		return $heads;
	}
);
add_filter(
	'flxa_menu_logos',
	function ( $m ) {
		$m['flx-pos'] = 'flx-pos.svg';
		return $m;
	}
);
