<?php
/**
 * Plugin Name:       Flux Studio
 * Description:       Yapay zekâ ile görsel ve video stüdyosu: üretim, referanslar ve kişiler, alan düzenleme, Geliştir ve Beyin fırtınası, klasörlü galeri. Üretim Flux AI üzerinden (fal.ai, EvoLink, OpenAI, Gemini, OpenRouter).
 * Version:           0.8.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  flx-graphql
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * Text Domain:       flx-studio
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-studio
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

if ( defined( 'FLXST_VERSION' ) ) {
	return;
}

define( 'FLXST_VERSION', '0.8.0' );
define( 'FLXST_DB_VERSION', 1 );
define( 'FLXST_FILE', __FILE__ );
define( 'FLXST_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXST_URL', plugin_dir_url( __FILE__ ) );
define( 'FLXST_NS', 'flx-studio/v1' );

require_once FLXST_DIR . 'includes/install.php';
require_once FLXST_DIR . 'includes/services/class-flxst-dto.php';
require_once FLXST_DIR . 'includes/services/class-flxst-service.php';
require_once FLXST_DIR . 'includes/store.php';
require_once FLXST_DIR . 'includes/media.php';
require_once FLXST_DIR . 'includes/ai.php';
require_once FLXST_DIR . 'includes/rest.php';
require_once FLXST_DIR . 'includes/stats.php';
require_once FLXST_DIR . 'includes/checks.php';
require_once FLXST_DIR . 'includes/integrations.php';
require_once FLXST_DIR . 'includes/app.php';
if ( is_admin() ) {
	require_once FLXST_DIR . 'includes/admin.php';
}

/*
 * GraphQL (Flux GraphQL 0.2.0+, capability model): the schema is registered only inside a GraphQL request;
 * the persisted operations the Studio screen sends. Without Flux GraphQL the schema is left out and the
 * admin page says it cannot open.
 */
add_action(
	'flx_gql_register',
	static function () {
		if ( ! function_exists( 'flx_gql_any_cap' ) ) {
			return;
		}
		require_once FLXST_DIR . 'includes/graphql/schema.php';
	}
);
add_action(
	'flx_gql_loaders',
	static function () {
		// Only the class name: the class is defined with the schema (includes/graphql/schema.php).
		if ( function_exists( 'flx_gql_node_loader' ) ) {
			flx_gql_node_loader( 'FlxStudioItem', 'FLXST_Item_Loader' );
		}
	}
);
add_filter(
	'flx_gql_persisted_manifests',
	static function ( $m ) {
		$m               = is_array( $m ) ? $m : array();
		$m['flx-studio'] = FLXST_DIR . 'graphql/persisted-queries.json';
		return $m;
	}
);
add_filter(
	'flx_gql_upload_contexts',
	static function ( $c ) {
		$c = is_array( $c ) ? $c : array();
		// Reference photos (and the mask of an area edit): the screen sends the file here first, then the mutation takes it over.
		$c['flx-studio/reference'] = array(
			'cap'    => static fn(): bool => flxst_can_use(),
			'mimes'  => array( 'png' => 'image/png', 'jpg|jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif' ),
			'max_mb' => 25,
		);
		$c['flx-studio/mask']      = array(
			'cap'    => static fn(): bool => flxst_can_use(),
			'mimes'  => array( 'png' => 'image/png' ),
			'max_mb' => 20,
		);
		return $c;
	}
);

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'flx-studio', false, dirname( plugin_basename( FLXST_FILE ) ) . '/languages' );
	},
	1
);
add_filter(
	'load_textdomain_mofile',
	function ( $mofile, $domain ) {
		if ( 'flx-studio' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXST_DIR . 'languages/flx-studio-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

register_activation_hook( FLXST_FILE, 'flxst_install' );
add_action( 'plugins_loaded', 'flxst_maybe_upgrade', 5 );
