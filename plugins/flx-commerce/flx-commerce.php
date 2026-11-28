<?php
/**
 * Plugin Name:       Flux Commerce
 * Description:       WooCommerce mağazası için satış paneli, müşteriler, aylık/yıllık abonelikler, satış raporları, dekont yüklemeli Havale/EFT, sade ödeme sayfası, ücretsiz kargo çubuğu, Google Merchant ürün feed'i ve WhatsApp/e-posta bildirimleri.
 * Version:           0.8.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce, flx-graphql
 * WC requires at least: 8.0
 * WC tested up to:   11.2
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flx-commerce
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-commerce
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

if ( defined( 'FLXCM_VERSION' ) ) {
	return;
}

define( 'FLXCM_VERSION', '0.8.1' );
define( 'FLXCM_DB_VERSION', 2 );
define( 'FLXCM_FILE', __FILE__ );
define( 'FLXCM_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXCM_URL', plugin_dir_url( __FILE__ ) );

require_once FLXCM_DIR . 'includes/helpers.php';
require_once FLXCM_DIR . 'includes/class-flxcm-install.php';
require_once FLXCM_DIR . 'includes/class-flxcm-stats.php';
require_once FLXCM_DIR . 'includes/class-flxcm-customers.php';
require_once FLXCM_DIR . 'includes/class-flxcm-bank.php';
require_once FLXCM_DIR . 'includes/class-flxcm-receipts.php';
require_once FLXCM_DIR . 'includes/class-flxcm-rest.php';
require_once FLXCM_DIR . 'includes/app-manifest.php';
require_once FLXCM_DIR . 'includes/class-flxcm-checkout.php';
require_once FLXCM_DIR . 'includes/class-flxcm-feed.php';
require_once FLXCM_DIR . 'includes/class-flxcm-notify.php';
require_once FLXCM_DIR . 'includes/class-flxcm-subscriptions.php';
require_once FLXCM_DIR . 'includes/class-flxcm-sub-product.php';
require_once FLXCM_DIR . 'includes/class-flxcm-sub-account.php';
require_once FLXCM_DIR . 'includes/class-flxcm-hidden-product.php';
require_once FLXCM_DIR . 'includes/class-flxcm-sub-cards.php';
require_once FLXCM_DIR . 'includes/system-checks.php';
require_once FLXCM_DIR . 'includes/services/class-flxcm-customer-service.php';
require_once FLXCM_DIR . 'includes/services/class-flxcm-subscription-service.php';
require_once FLXCM_DIR . 'includes/services/class-flxcm-receipt-service.php';
require_once FLXCM_DIR . 'includes/services/class-flxcm-settings-service.php';
require_once FLXCM_DIR . 'includes/services/class-flxcm-store-service.php';
require_once FLXCM_DIR . 'includes/services/class-flxcm-cart-service.php';
// Customer profile data is shared with GraphQL, which is not an admin request.
require_once FLXCM_DIR . 'includes/admin/page-customer.php';
if ( is_admin() ) {
	require_once FLXCM_DIR . 'includes/admin/class-flxcm-admin.php';
}

/*
 * Çeviri: kaynak metinler Türkçe; site/kullanıcı dili İngilizce olduğunda languages/ içindeki çeviri kullanılır.
 * en_GB, en_AU gibi diğer İngilizce yerel ayarları en_US çevirisine düşer.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'flx-commerce', false, dirname( plugin_basename( FLXCM_FILE ) ) . '/languages' );
	},
	1
);
add_filter(
	'load_textdomain_mofile',
	function ( $mofile, $domain ) {
		if ( 'flx-commerce' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXCM_DIR . 'languages/flx-commerce-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

/* WooCommerce HPOS ve sepet/ödeme blokları uyumu. */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FLXCM_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', FLXCM_FILE, true );
		}
	}
);

register_activation_hook( FLXCM_FILE, array( 'FLXCM_Install', 'install' ) );
add_action( 'plugins_loaded', array( 'FLXCM_Install', 'maybe_upgrade' ), 5 );

/* WooCommerce yoksa yalnız uyarı; katmanlar plugins_loaded:15'te açılır. */
add_action(
	'plugins_loaded',
	function () {
		if ( ! flxcm_wc_ready() ) {
			add_action( 'admin_notices', 'flxcm_wc_missing_notice' );
			return;
		}
		FLXCM_Customers::init();
		FLXCM_REST::init();
		FLXCM_Bank::init();
		FLXCM_Receipts::init();
		FLXCM_Checkout::init();
		FLXCM_Feed::init();
		FLXCM_Notify::init();
		FLXCM_Subscriptions::init();
		FLXCM_Sub_Product::init();
		FLXCM_Sub_Account::init();
		FLXCM_Hidden_Product::init();
		FLXCM_Sub_Cards::init();
		FLXCM_Cart_Service::init();
		if ( is_admin() ) {
			FLXCM_Admin::init();
		}
		do_action( 'flxcm_loaded' );
	},
	15
);

function flxcm_wc_missing_notice() {
	if ( current_user_can( 'activate_plugins' ) ) {
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Flux Commerce needs WooCommerce. Install and activate WooCommerce.', 'flx-commerce' ) . '</p></div>';
	}
}

add_filter(
	'plugin_action_links_' . plugin_basename( FLXCM_FILE ),
	function ( $links ) {
		if ( flxcm_wc_ready() ) {
			array_unshift( $links, '<a href="' . esc_url( flxcm_admin_url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'flx-commerce' ) . '</a>' );
		}
		return $links;
	}
);

/** Güncellemeler ekranında paket logosu. */
add_filter(
	'flxu_package_icons',
	function ( $icons, $slug ) {
		if ( 'flx-commerce' === $slug && ! $icons ) {
			$url   = FLXCM_URL . 'assets/icon.svg?ver=' . FLXCM_VERSION;
			$icons = array( 'svg' => $url, '1x' => $url, '2x' => $url );
		}
		return $icons;
	},
	10,
	2
);

/* Flux Admin: sayfa başlığı (logo + açıklama), menü logosu ve "Bağış" grubunda yer. */
add_filter(
	'flxa_page_heads',
	function ( $map ) {
		$map['flx-commerce'] = 'flx-commerce';
		return $map;
	}
);
add_filter(
	'flxa_package_heads',
	function ( $heads ) {
		$heads['flx-commerce'] = array( 'Flux Commerce', __( 'Sales dashboard, customers, reports and checkout improvements', 'flx-commerce' ) );
		return $heads;
	}
);
add_filter(
	'flxa_menu_logos',
	function ( $m ) {
		$m['flx-commerce'] = 'flx-commerce.svg';
		return $m;
	}
);
/*
 * GraphQL is registered only when Flux GraphQL is present. REST and the classic screens
 * keep working without it. Priority 20 is after Flux CRM (10) so FlxContact exists when it is active.
 */
add_action(
	'flx_gql_register',
	static function () {
		if ( ! function_exists( 'flx_gql_any_cap' ) ) {
			return;
		}
		require_once FLXCM_DIR . 'includes/graphql/schema.php';
	},
	20
);
add_filter(
	'flx_gql_persisted_manifests',
	static function ( $manifests ) {
		$manifests                 = is_array( $manifests ) ? $manifests : array();
		$manifests['flx-commerce'] = FLXCM_DIR . 'graphql/persisted-queries.json';
		return $manifests;
	}
);
add_filter(
	'flx_gql_capabilities',
	static function ( $caps ) {
		$caps = is_array( $caps ) ? $caps : array();
		return $caps + array(
			'view_woocommerce_reports' => array(
				'label'   => __( 'Sees store reports', 'flx-commerce' ),
				'group'   => __( 'Store', 'flx-commerce' ),
				'package' => 'flx-commerce',
			),
			'manage_woocommerce'       => array(
				'label'   => __( 'Manages the store', 'flx-commerce' ),
				'group'   => __( 'Store', 'flx-commerce' ),
				'package' => 'flx-commerce',
			),
			'edit_shop_orders'         => array(
				'label'   => __( 'Edits shop orders', 'flx-commerce' ),
				'group'   => __( 'Store', 'flx-commerce' ),
				'package' => 'flx-commerce',
			),
		);
	}
);

add_filter(
	'flxa_default_groups',
	function ( $groups ) {
		if ( isset( $groups['donate'][1] ) && is_array( $groups['donate'][1] ) && ! in_array( 'flx-commerce', $groups['donate'][1], true ) ) {
			array_unshift( $groups['donate'][1], 'flx-commerce' );
		}
		return $groups;
	}
);
