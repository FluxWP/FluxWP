<?php
/**
 * Plugin Name:       Flux Analytics
 * Description:       Çerezsiz, kişisel veri tutmayan site istatistikleri: ziyaretçi, görüntülenme, en çok okunan sayfalar, yönlendiren siteler, kanal, cihaz/tarayıcı, kampanya (UTM), tıklama olayları ve bağış dönüşümleri. Veriler sitede kalır; yapay zekâ Flux AI / Flux AI & MCP araçlarıyla okur.
 * Version:           0.5.3
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  flx-graphql
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * Text Domain:       flx-analytics
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-analytics
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

if ( defined( 'FLXAN_VERSION' ) ) {
	return;
}

define( 'FLXAN_VERSION', '0.5.3' );
define( 'FLXAN_DB_VERSION', 3 );
define( 'FLXAN_FILE', __FILE__ );
define( 'FLXAN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXAN_URL', plugin_dir_url( __FILE__ ) );

require_once FLXAN_DIR . 'includes/collect.php';
require_once FLXAN_DIR . 'includes/settings.php';
require_once FLXAN_DIR . 'includes/agent.php';
require_once FLXAN_DIR . 'includes/aggregator.php';
require_once FLXAN_DIR . 'includes/stats.php';
require_once FLXAN_DIR . 'includes/journeys.php';
require_once FLXAN_DIR . 'includes/tracker.php';
require_once FLXAN_DIR . 'includes/rest.php';
require_once FLXAN_DIR . 'includes/events.php';
require_once FLXAN_DIR . 'includes/public.php';
require_once FLXAN_DIR . 'includes/mcp.php';
require_once FLXAN_DIR . 'includes/system-checks.php';
require_once FLXAN_DIR . 'includes/js-strings.php';
require_once FLXAN_DIR . 'includes/telemetry-hub.php';
require_once FLXAN_DIR . 'includes/dashboard.php'; // widget'lar + REST /dashboard (REST isteği is_admin değildir)
require_once FLXAN_DIR . 'includes/app.php';
require_once FLXAN_DIR . 'includes/services/class-flxan-service.php';
require_once FLXAN_DIR . 'includes/services/class-flxan-telemetry-service.php';
require_once FLXAN_DIR . 'includes/flux-ui.php';
require_once FLXAN_DIR . 'includes/import.php'; // functions only; the service also runs inside a GraphQL request
if ( is_admin() ) {
	require_once FLXAN_DIR . 'includes/admin.php';
}

/*
 * GraphQL (Flux GraphQL): schema only inside a GraphQL request; persisted operations the screens send;
 * the "View statistics" capability joins the shared catalog. Without Flux GraphQL the schema is left out
 * and the classic pages keep working. Visit collection stays outside GraphQL (docs/graphql/ISTISNALAR.md).
 */
add_action(
	'flx_gql_register',
	static function () {
		if ( ! function_exists( 'flx_gql_all_caps' ) ) {
			return;
		}
		require_once FLXAN_DIR . 'includes/graphql/schema.php';
	}
);
add_filter(
	'flx_gql_persisted_manifests',
	static function ( $m ) {
		$m                   = is_array( $m ) ? $m : array();
		$m['flx-analytics']  = FLXAN_DIR . 'graphql/persisted-queries.json';
		return $m;
	}
);
add_filter(
	'flx_gql_capabilities',
	static function ( $caps ) {
		$caps = is_array( $caps ) ? $caps : array();
		if ( ! isset( $caps['flxan_view'] ) ) {
			$caps['flxan_view'] = array(
				'label'   => __( 'View statistics', 'flx-analytics' ),
				'package' => 'flx-analytics',
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
		load_plugin_textdomain( 'flx-analytics', false, dirname( plugin_basename( FLXAN_FILE ) ) . '/languages' );
	},
	1
);
add_filter(
	'load_textdomain_mofile',
	function ( $mofile, $domain ) {
		if ( 'flx-analytics' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXAN_DIR . 'languages/flx-analytics-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

register_activation_hook( FLXAN_FILE, 'flxan_install' );
register_deactivation_hook( FLXAN_FILE, 'flxan_deactivate' );
add_action( 'plugins_loaded', 'flxan_maybe_upgrade' );

add_filter(
	'plugin_action_links_' . plugin_basename( FLXAN_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=flx-analytics' ) ) . '">' . esc_html__( 'Statistics', 'flx-analytics' ) . '</a>' );
		return $links;
	}
);

/** Flux Roles: yetki listesine "İstatistikleri görüntüle". */
add_filter(
	'flx_role_caps_supported',
	function ( $caps ) {
		return array_merge( (array) $caps, array( 'flxan_view' ) );
	}
);

/** Güncellemeler ekranında paket logosu. */
add_filter(
	'flxu_package_icons',
	function ( $icons, $slug ) {
		if ( 'flx-analytics' === $slug && ! $icons ) {
			$url   = FLXAN_URL . 'assets/icon.svg?ver=' . FLXAN_VERSION;
			$icons = array( 'svg' => $url, '1x' => $url, '2x' => $url );
		}
		return $icons;
	},
	10,
	2
);
