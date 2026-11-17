<?php
/**
 * Plugin Name: Acme Hello (Flux plugin kit example)
 * Description: Smallest plugin with a wp-admin screen on the Flux runtime. Build: bun run --filter @fluxwp/plugin-kit example
 * Version: 0.1.0
 * Requires PHP: 8.1
 * Requires Plugins: flx-graphql
 * Text Domain: acme-hello
 *
 * @package Acme_Hello
 */

defined( 'ABSPATH' ) || exit;

// Operations this plugin's screens send (flx-kit codegen writes the manifest).
add_filter(
	'flx_gql_persisted_manifests',
	static function ( $m ) {
		$m               = is_array( $m ) ? $m : array();
		$m['acme-hello'] = __DIR__ . '/graphql/persisted-queries.json';
		return $m;
	}
);

add_action(
	'admin_menu',
	static function () {
		add_menu_page(
			__( 'Acme Hello', 'acme-hello' ),
			__( 'Acme Hello', 'acme-hello' ),
			'read',
			'acme-hello',
			static function () {
				if ( function_exists( 'flx_admin_screen_render' ) && flx_admin_screen_render() ) {
					return;
				}
				echo '<div class="wrap"><h1>' . esc_html__( 'Acme Hello', 'acme-hello' ) . '</h1><p>' . esc_html__( 'Flux GraphQL is needed for this screen.', 'acme-hello' ) . '</p></div>';
			},
			'dashicons-smiley'
		);
		if ( function_exists( 'flx_admin_screen' ) ) {
			flx_admin_screen(
				'acme-hello',
				array(
					'module'     => 'acme-hello',
					'assets_dir' => plugin_dir_path( __FILE__ ) . 'assets/flux/',
					'assets_url' => plugin_dir_url( __FILE__ ) . 'assets/flux/',
					'version'    => '0.1.0',
					// First queries come with the page (same variables as the screen's useFlxQuery).
					'preload'    => array( array( 'FlxAcmeHelloViewer' ) ),
				)
			);
		}
	},
	20
);
