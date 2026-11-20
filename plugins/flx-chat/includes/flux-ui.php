<?php
/**
 * Settings and Reports pages as React screens (assets/flux/, tools/fluxapp/embed-sync.sh flx-chat) opened
 * through the Flux GraphQL screen shell. React is the default; without the bundle or Flux GraphQL the
 * classic PHP pages render as before. The classic admin-post handlers stay (they share the services).
 *
 * @package FLX_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the React screens can open: the bundle is there and Flux GraphQL answers.
 */
function flxc_flux_screens_ready(): bool {
	return (bool) apply_filters(
		'flxc_flux_ui',
		file_exists( FLXC_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_admin_screen' ) && function_exists( 'flx_gql_ready' ) && flx_gql_ready()
	);
}

/**
 * Mount options of the screens (the shell adds GraphQL URL, nonce, locale and site name).
 *
 * @return array<string,mixed>
 */
function flxc_flux_screen_options(): array {
	$accent = FLXC_Val::str( flxc_setting( 'accent' ) );
	return array(
		'colors' => array(
			'primary'         => '' !== $accent ? $accent : '#00a884',
			'primaryContrast' => '#ffffff',
		),
	);
}

add_action(
	'admin_menu',
	static function () {
		if ( ! function_exists( 'flx_admin_screen' ) ) {
			return;
		}
		$base = array(
			'assets_dir' => FLXC_DIR . 'assets/flux/',
			'assets_url' => FLXC_URL . 'assets/flux/',
			'version'    => FLXC_VERSION,
			'enabled'    => 'flxc_flux_screens_ready',
			'options'    => 'flxc_flux_screen_options',
		);
		flx_admin_screen(
			'flx-chat-ayarlar',
			$base + array(
				'module'      => 'flxc-settings',
				'start_param' => 'tab',
				'preload'     => array( array( 'FlxChatSettings' ) ),
			)
		);
		flx_admin_screen(
			'flx-chat-raporlar',
			$base + array(
				'module'  => 'flxc-reports',
				'preload' => array( array( 'FlxChatReports' ) ),
			)
		);
	},
	20
);
