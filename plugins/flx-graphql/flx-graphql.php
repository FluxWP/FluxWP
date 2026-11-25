<?php
/**
 * Plugin Name:       Flux GraphQL
 * Description:       Shared GraphQL API for FluxWP plugins: an embedded, pinned WPGraphQL, the Flux GraphQL kit (typed registration, permissions, errors, pagination, persisted queries, cache tags) and one upload endpoint for every package.
 * Version:           0.8.3
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            FluxWP
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       flx-graphql
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-graphql
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

if ( defined( 'FLXGQL_VERSION' ) ) {
	return;
}

define( 'FLXGQL_VERSION', '0.8.3' );
define( 'FLXGQL_FILE', __FILE__ );
define( 'FLXGQL_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXGQL_URL', plugin_dir_url( __FILE__ ) );
/** Lowest WPGraphQL the kit is written against; a separately installed copy below this is not used. */
define( 'FLXGQL_WPGQL_MIN', '2.0.0' );
/** First WPGraphQL major the kit has not been tested with. */
define( 'FLXGQL_WPGQL_MAX', '3.0.0' );

// PHP 8.2–8.5 functions for PHP 8.1 (each one guarded by function_exists).
require_once FLXGQL_DIR . 'includes/kit/polyfills.php';
require_once FLXGQL_DIR . 'includes/helpers.php';
require_once FLXGQL_DIR . 'includes/settings.php';
require_once FLXGQL_DIR . 'includes/loader.php';
require_once FLXGQL_DIR . 'includes/locale.php';

// The embedded WPGraphQL loads here, while plugins load (a separately activated WPGraphQL loads after us).
flx_gql_boot_wpgraphql();

require_once FLXGQL_DIR . 'includes/kit/errors.php';
require_once FLXGQL_DIR . 'includes/kit/owners.php';
require_once FLXGQL_DIR . 'includes/kit/registry.php';
require_once FLXGQL_DIR . 'includes/kit/connection.php';
require_once FLXGQL_DIR . 'includes/kit/dataloader.php';
require_once FLXGQL_DIR . 'includes/kit/types.php';
require_once FLXGQL_DIR . 'includes/kit/settings-types.php';
require_once FLXGQL_DIR . 'includes/kit/caps.php';
require_once FLXGQL_DIR . 'includes/kit/limits.php';
require_once FLXGQL_DIR . 'includes/kit/persisted.php';
require_once FLXGQL_DIR . 'includes/kit/cache.php';
require_once FLXGQL_DIR . 'includes/kit/cors.php';
require_once FLXGQL_DIR . 'includes/upload.php';
// WooCommerce cart bridge (flxCart*): Store API under GraphQL (docs/graphql/SEPET.md).
require_once FLXGQL_DIR . 'includes/woo/class-flxgql-store-api.php';
require_once FLXGQL_DIR . 'includes/woo/class-flxgql-cart.php';
require_once FLXGQL_DIR . 'includes/woo/cart-schema.php';
FLXGQL_Store_Api::init();
add_action( 'flx_gql_register_core', 'flx_gql_register_cart_schema', 40 );
require_once FLXGQL_DIR . 'includes/checks.php';
if ( is_admin() ) {
	require_once FLXGQL_DIR . 'includes/admin.php';
	// Shell of the Flux React screens in wp-admin and in-place navigation between them (docs/graphql/EKRAN-KABUGU.md).
	require_once FLXGQL_DIR . 'includes/screens.php';
}
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once FLXGQL_DIR . 'includes/cli.php';
}

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'flx-graphql', false, dirname( plugin_basename( FLXGQL_FILE ) ) . '/languages' );
	},
	1
);
// en_GB, en_AU … use the source strings; tr (any region) uses tr_TR.
add_filter(
	'load_textdomain_mofile',
	static function ( $mofile, $domain ) {
		if ( ! is_string( $mofile ) || 'flx-graphql' !== $domain || is_readable( $mofile ) || is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) ) {
			return $mofile;
		}
		if ( preg_match( '/-tr(_[A-Z]{2})?\.mo$/', $mofile ) ) {
			return FLXGQL_DIR . 'languages/flx-graphql-tr_TR.mo';
		}
		return $mofile;
	},
	10,
	2
);

// The kit's own persisted operations (flxViewer) for every client.
add_filter(
	'flx_gql_persisted_manifests',
	static function ( $m ) {
		$m                = is_array( $m ) ? $m : array();
		$m['flx-graphql'] = FLXGQL_DIR . 'graphql/persisted-queries.json';
		return $m;
	}
);

register_activation_hook( __FILE__, 'flx_gql_activate' );
register_deactivation_hook( __FILE__, 'flx_gql_deactivate' );
