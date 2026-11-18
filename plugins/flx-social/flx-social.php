<?php
/**
 * Plugin Name:       Flux Social
 * Description:       Sosyal medya yönetimi sitenin içinde: hesap bağlama (anahtarlar panelden), hesap başına içerik, zamanlama, ağda düzenleme/silme, takvim, pano, Flux AI yazım yardımı ve MCP araçları. Facebook, Instagram, YouTube, X, LinkedIn, TikTok, Pinterest, Google İşletme, WhatsApp.
 * Version:           0.5.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  flx-graphql
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * Text Domain:       flx-social
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-social
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

if ( defined( 'FLXSM_VERSION' ) ) {
	return;
}

define( 'FLXSM_VERSION', '0.5.1' );
define( 'FLXSM_DB_VERSION', 1 );
define( 'FLXSM_FILE', __FILE__ );
define( 'FLXSM_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXSM_URL', plugin_dir_url( __FILE__ ) );
define( 'FLXSM_NS', 'flx-social/v1' );

require_once FLXSM_DIR . 'includes/install.php';
require_once FLXSM_DIR . 'includes/util.php';
require_once FLXSM_DIR . 'includes/store.php';
require_once FLXSM_DIR . 'includes/providers/class-provider.php';
require_once FLXSM_DIR . 'includes/providers/meta.php';
require_once FLXSM_DIR . 'includes/providers/google.php';
require_once FLXSM_DIR . 'includes/providers/x.php';
require_once FLXSM_DIR . 'includes/providers/linkedin.php';
require_once FLXSM_DIR . 'includes/providers/tiktok.php';
require_once FLXSM_DIR . 'includes/providers/pinterest.php';
require_once FLXSM_DIR . 'includes/providers/whatsapp.php';
require_once FLXSM_DIR . 'includes/providers.php';
require_once FLXSM_DIR . 'includes/oauth.php';
require_once FLXSM_DIR . 'includes/engine.php';
require_once FLXSM_DIR . 'includes/stats.php';
require_once FLXSM_DIR . 'includes/ai.php';
require_once FLXSM_DIR . 'includes/services/class-flxsm-dto.php';
require_once FLXSM_DIR . 'includes/services/class-flxsm-accounts-service.php';
require_once FLXSM_DIR . 'includes/services/class-flxsm-posts-service.php';
require_once FLXSM_DIR . 'includes/services/class-flxsm-admin-service.php';
require_once FLXSM_DIR . 'includes/rest.php';
require_once FLXSM_DIR . 'includes/mcp.php';
require_once FLXSM_DIR . 'includes/autopost.php';
require_once FLXSM_DIR . 'includes/checks.php';
require_once FLXSM_DIR . 'includes/integrations.php';
require_once FLXSM_DIR . 'includes/app.php';
if ( is_admin() ) {
	require_once FLXSM_DIR . 'includes/admin.php';
}

/*
 * GraphQL (Flux GraphQL): schema only inside a GraphQL request; the persisted operations the Social Media
 * screen sends, the upload context of its media picker and the permission catalog. Without Flux GraphQL
 * (0.2.0+, capability model) the schema is left out and the admin page says it cannot open.
 */
add_action(
	'flx_gql_register',
	static function () {
		if ( ! function_exists( 'flx_gql_all_caps' ) ) {
			return;
		}
		require_once FLXSM_DIR . 'includes/graphql/schema.php';
	}
);
add_filter(
	'flx_gql_persisted_manifests',
	static function ( $m ) {
		$m               = is_array( $m ) ? $m : array();
		$m['flx-social'] = FLXSM_DIR . 'graphql/persisted-queries.json';
		return $m;
	}
);
add_filter(
	'flx_gql_upload_contexts',
	static function ( $c ) {
		$c                         = is_array( $c ) ? $c : array();
		$c['flx-social/media']     = array(
			'cap'    => 'upload_files',
			'mimes'  => array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
				'gif'          => 'image/gif',
				'webp'         => 'image/webp',
				'mp4|m4v'      => 'video/mp4',
				'mov|qt'       => 'video/quicktime',
			),
			'max_mb' => 512,
		);
		return $c;
	}
);
add_filter(
	'flx_gql_capabilities',
	static function ( $caps ) {
		$caps = is_array( $caps ) ? $caps : array();
		$add  = array(
			FLXSM_CAP_POST    => __( 'Social media: prepare posts', 'flx-social' ),
			FLXSM_CAP_PUBLISH => __( 'Social media: publish and approve', 'flx-social' ),
			FLXSM_CAP_MANAGE  => __( 'Social media: accounts and settings', 'flx-social' ),
		);
		foreach ( $add as $cap => $label ) {
			if ( ! isset( $caps[ $cap ] ) ) {
				$caps[ $cap ] = array(
					'label'   => $label,
					'group'   => 'flx-social',
					'package' => 'flx-social',
				);
			}
		}
		return $caps;
	}
);

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'flx-social', false, dirname( plugin_basename( FLXSM_FILE ) ) . '/languages' );
	},
	1
);
add_filter(
	'load_textdomain_mofile',
	function ( $mofile, $domain ) {
		if ( 'flx-social' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXSM_DIR . 'languages/flx-social-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

register_activation_hook( FLXSM_FILE, 'flxsm_install' );
register_deactivation_hook( FLXSM_FILE, 'flxsm_deactivate' );
add_action( 'plugins_loaded', 'flxsm_maybe_upgrade', 5 );
