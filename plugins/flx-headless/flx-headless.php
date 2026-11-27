<?php
/**
 * Plugin Name:       Flux Headless
 * Description:       Serves one or more headless front ends (Next.js, Nuxt, Astro or any framework) from this WordPress over GraphQL: a page by its address with SEO head, languages and cache tags (flxRoute), the site's logo, menus, colours, languages and contact details (flxSite), and a signed revalidate webhook when content changes.
 * Version:           0.3.5
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  flx-graphql
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flx-headless
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-headless
 *
 * @package FLX_Headless
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.

if ( defined( 'FLXHL_VERSION' ) ) {
	return;
}

define( 'FLXHL_VERSION', '0.3.5' );
define( 'FLXHL_FILE', __FILE__ );
define( 'FLXHL_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXHL_URL', plugin_dir_url( __FILE__ ) );

require_once FLXHL_DIR . 'includes/helpers.php';
require_once FLXHL_DIR . 'includes/services/class-flxhl-dto.php';
require_once FLXHL_DIR . 'includes/services/class-flxhl-frontend-service.php';
require_once FLXHL_DIR . 'includes/services/class-flxhl-route-service.php';
require_once FLXHL_DIR . 'includes/services/class-flxhl-site-service.php';
require_once FLXHL_DIR . 'includes/services/class-flxhl-revalidate-service.php';
require_once FLXHL_DIR . 'includes/hooks.php';
require_once FLXHL_DIR . 'includes/page-mode.php';
require_once FLXHL_DIR . 'includes/system-checks.php';
require_once FLXHL_DIR . 'includes/app.php';
if ( is_admin() ) {
	require_once FLXHL_DIR . 'includes/admin.php';
}

/*
 * GraphQL (Flux GraphQL): the schema loads only inside a GraphQL request. The persisted operations are the
 * settings screen's and the public ones a front end sends (flxRoute, flxSite).
 */
add_action(
	'flx_gql_register',
	static function () {
		if ( ! function_exists( 'flx_gql_all_caps' ) ) {
			return;
		}
		require_once FLXHL_DIR . 'includes/graphql/schema.php';
	}
);
add_filter(
	'flx_gql_persisted_manifests',
	static function ( $m ) {
		$m                 = is_array( $m ) ? $m : array();
		$m['flx-headless'] = FLXHL_DIR . 'graphql/persisted-queries.json';
		return $m;
	}
);

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'flx-headless', false, dirname( plugin_basename( FLXHL_FILE ) ) . '/languages' );
	},
	1
);

add_filter(
	'plugin_action_links_' . plugin_basename( FLXHL_FILE ),
	static function ( $links ) {
		$links = is_array( $links ) ? $links : array();
		array_unshift( $links, '<a href="' . esc_url( flxhl_admin_url() ) . '">' . esc_html__( 'Settings', 'flx-headless' ) . '</a>' );
		return $links;
	}
);

/* Logo on the Updates screen. */
add_filter(
	'flxu_package_icons',
	static function ( $icons, $slug ) {
		if ( 'flx-headless' === $slug && ! $icons ) {
			$url   = FLXHL_URL . 'assets/icon.svg?ver=' . FLXHL_VERSION;
			$icons = array( 'svg' => $url, '1x' => $url, '2x' => $url );
		}
		return $icons;
	},
	10,
	2
);

/* Flux Admin: page head (logo + description) and a place in the "System" group. */
add_filter(
	'flxa_page_heads',
	static function ( $map ) {
		$map                 = is_array( $map ) ? $map : array();
		$map['flx-headless'] = 'flx-headless';
		return $map;
	}
);
add_filter(
	'flxa_package_heads',
	static function ( $heads ) {
		$heads                 = is_array( $heads ) ? $heads : array();
		$heads['flx-headless'] = array( 'Flux Headless', __( 'Serves headless front ends over GraphQL', 'flx-headless' ) );
		return $heads;
	}
);
add_filter(
	'flxa_default_groups',
	static function ( $groups ) {
		if ( is_array( $groups ) && isset( $groups['system'][1] ) && is_array( $groups['system'][1] ) && ! in_array( 'flx-headless', $groups['system'][1], true ) ) {
			$groups['system'][1][] = 'flx-headless';
		}
		return $groups;
	}
);
