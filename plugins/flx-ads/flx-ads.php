<?php
/**
 * Plugin Name:       Flux Ads
 * Description:       Reklam dönüşüm takibi: Meta Pixel + Conversions API, TikTok Pixel + Events API ve Google Analytics 4 (Measurement Protocol). Meta reklam harcamasını çeker, ROAS ve bağış başı maliyeti gösterir. Bağış ve siparişleri hangi reklamın, kampanyanın ve reklam metninin getirdiğini ölçer; Havale/EFT bağışı onaylanınca dönüşümü sunucudan gönderir. Çerez onayına uyar, kişisel veri yalnız izinle ve hash'li gider. Flux CRM kuruluysa kişi kartına reklam kaynağını yazar.
 * Version:           0.10.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce, flx-graphql
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flx-ads
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-ads
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

if ( defined( 'FLXADS_VERSION' ) ) {
	return;
}

define( 'FLXADS_VERSION', '0.10.1' );
define( 'FLXADS_DB_VERSION', 3 );
define( 'FLXADS_FILE', __FILE__ );
define( 'FLXADS_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXADS_URL', plugin_dir_url( __FILE__ ) );

require_once FLXADS_DIR . 'includes/helpers.php';
require_once FLXADS_DIR . 'includes/class-flxads-install.php';
require_once FLXADS_DIR . 'includes/class-flxads-front.php';
require_once FLXADS_DIR . 'includes/class-flxads-orders.php';
require_once FLXADS_DIR . 'includes/class-flxads-send.php';
require_once FLXADS_DIR . 'includes/class-flxads-report.php';
require_once FLXADS_DIR . 'includes/class-flxads-spend.php';
require_once FLXADS_DIR . 'includes/class-flxads-cohort.php';
require_once FLXADS_DIR . 'includes/class-flxads-audiences.php';
require_once FLXADS_DIR . 'includes/class-flxads-manager.php';
require_once FLXADS_DIR . 'includes/class-flxads-studio.php';
require_once FLXADS_DIR . 'includes/class-flxads-optimizer.php';
require_once FLXADS_DIR . 'includes/class-flxads-experiments.php';
require_once FLXADS_DIR . 'includes/class-flxads-planner.php';
require_once FLXADS_DIR . 'includes/services/class-flxads-view.php';
require_once FLXADS_DIR . 'includes/services/class-flxads-reports-service.php';
require_once FLXADS_DIR . 'includes/services/class-flxads-meta-service.php';
require_once FLXADS_DIR . 'includes/services/class-flxads-creative-service.php';
require_once FLXADS_DIR . 'includes/services/class-flxads-settings-service.php';
require_once FLXADS_DIR . 'includes/app.php';
require_once FLXADS_DIR . 'includes/crm.php';
require_once FLXADS_DIR . 'includes/mcp.php';
require_once FLXADS_DIR . 'includes/ai-write.php';
require_once FLXADS_DIR . 'includes/system-checks.php';
if ( is_admin() ) {
	require_once FLXADS_DIR . 'includes/admin/class-flxads-admin.php';
}

register_activation_hook( __FILE__, array( 'FLXADS_Install', 'install' ) );
register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'flxads_retry' );
	}
);

/*
 * GraphQL (Flux GraphQL): schema only inside a GraphQL request; the persisted operations the Ad conversions
 * screen sends. Without Flux GraphQL (0.2.0+, capability model) the schema is left out and the admin screen
 * says it cannot open.
 */
add_action(
	'flx_gql_register',
	static function () {
		if ( ! function_exists( 'flx_gql_all_caps' ) ) {
			return;
		}
		require_once FLXADS_DIR . 'includes/graphql/schema.php';
	}
);
add_filter(
	'flx_gql_persisted_manifests',
	static function ( $m ) {
		$m            = is_array( $m ) ? $m : array();
		$m['flx-ads'] = FLXADS_DIR . 'graphql/persisted-queries.json';
		return $m;
	}
);
add_filter(
	'flx_gql_capabilities',
	static function ( $caps ) {
		$caps = is_array( $caps ) ? $caps : array();
		return $caps + array(
			'flxads_view'   => array(
				'label'   => __( 'Sees ad conversions', 'flx-ads' ),
				'group'   => __( 'Ads', 'flx-ads' ),
				'package' => 'flx-ads',
			),
			'flxads_manage' => array(
				'label'   => __( 'Manages ads and tracking settings', 'flx-ads' ),
				'group'   => __( 'Ads', 'flx-ads' ),
				'package' => 'flx-ads',
			),
		);
	}
);

/*
 * Çeviri: kaynak metinler İngilizce; Türkçe ve İngilizce paketler languages/ içinde.
 * en_GB, en_AU gibi diğer İngilizce yerel ayarları en_US çevirisine düşer.
 */
add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'flx-ads', false, dirname( plugin_basename( FLXADS_FILE ) ) . '/languages' );
	},
	1
);

add_action(
	'plugins_loaded',
	static function () {
		FLXADS_Install::maybe_upgrade();
		FLXADS_Front::init();
		FLXADS_Orders::init();
		FLXADS_Send::init();
		FLXADS_CRM::init();
		FLXADS_Spend::init();
		FLXADS_Audiences::init();
		FLXADS_Optimizer::init();
		FLXADS_Experiments::init();
		if ( is_admin() ) {
			FLXADS_Admin::init();
		}
		do_action( 'flxads_loaded' );
	},
	15
);

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FLXADS_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', FLXADS_FILE, true );
		}
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( FLXADS_FILE ),
	static function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( flxads_admin_url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'flx-ads' ) . '</a>' );
		return $links;
	}
);

/** Güncellemeler ekranında paket logosu. */
add_filter(
	'flxu_package_icons',
	static function ( $icons, $slug ) {
		if ( 'flx-ads' === $slug && ! $icons ) {
			$url   = FLXADS_URL . 'assets/icon.svg?ver=' . FLXADS_VERSION;
			$icons = array( 'svg' => $url, '1x' => $url, '2x' => $url );
		}
		return $icons;
	},
	10,
	2
);

/* Flux Admin: sayfa başlığı (logo + açıklama), menü logosu ve "Büyüme" grubunda CRM'in arkasında yer. */
add_filter(
	'flxa_page_heads',
	static function ( $map ) {
		$map['flx-ads'] = 'flx-ads';
		return $map;
	}
);
add_filter(
	'flxa_package_heads',
	static function ( $heads ) {
		$heads['flx-ads'] = array( 'Flux Ads', __( 'Which ad brought which donation', 'flx-ads' ) );
		return $heads;
	}
);
add_filter(
	'flxa_menu_logos',
	static function ( $m ) {
		$m['flx-ads'] = 'flx-ads.svg';
		return $m;
	}
);
/* Logo Flux Admin'in kendi klasöründe değilse paketin kendi dosyası verilir. */
add_filter(
	'flxa_package_logo_url',
	static function ( $url, $file, $style = '' ) {
		if ( 'flx-ads.svg' === $file ) {
			$url = FLXADS_URL . ( 'pastel' === $style ? 'assets/icon-pastel.svg' : 'assets/icon.svg' ) . '?ver=' . FLXADS_VERSION;
		}
		return $url;
	},
	10,
	3
);
add_filter(
	'flxa_default_groups',
	static function ( $groups ) {
		if ( isset( $groups['growth'][1] ) && is_array( $groups['growth'][1] ) && ! in_array( 'flx-ads', $groups['growth'][1], true ) ) {
			$at = array_search( 'flx-crm', $groups['growth'][1], true );
			array_splice( $groups['growth'][1], false === $at ? 0 : $at + 1, 0, array( 'flx-ads' ) );
		}
		return $groups;
	}
);
