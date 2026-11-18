<?php
/**
 * SEO admin page: the same React screen as the FluxWP app (assets/flux/, tools/fluxapp/embed-sync.sh flx-seo),
 * opened through Flux GraphQL's screen shell (first queries preloaded, other Flux screens open from the menu
 * without reloading wp-admin). There is no classic page; when the screen cannot open the shell says so.
 *
 * @package flx-seo
 */

defined( 'ABSPATH' ) || exit;

/** Mount options of the React screen (Flux GraphQL screen shell adds rest, nonce, locale, site name, admin URL). */
function flxseo_flux_options() {
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	return array(
		'colors' => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
	);
}

add_action(
	'admin_menu',
	static function () {
		if ( ! function_exists( 'flx_admin_screen' ) ) {
			return;
		}
		flx_admin_screen(
			'flxseo',
			array(
				'module'      => 'flxseo-seo',
				'assets_dir'  => FLXSEO_DIR . 'assets/flux/',
				'assets_url'  => FLXSEO_URL . 'assets/flux/',
				'version'     => FLXSEO_VERSION,
				'options'     => 'flxseo_flux_options',
				'start'       => 'genel',
				'start_param' => 'tab',
				'preload'     => array( array( 'FlxSeoSettingsScreen' ) ),
			)
		);
	},
	20
);
