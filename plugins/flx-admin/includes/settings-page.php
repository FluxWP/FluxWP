<?php
/** Ayarlar › Yönetim teması: menü kaydı ve React ekranı (Genel, Görünüm, Menü, Yetkiler, Başlangıç, PWA). */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'flxa_settings_menu' );
add_action( 'load-settings_page_flx-admin', 'flxa_system_tab_redirect' );

/** Flux Speed kuruluysa Sistem kontrolü orada (Performans › Sistem Kontrolü); buradaki sekme gizlenir, eski adres oraya gider. */
function flxa_system_moved() {
	return function_exists( 'flxp_system_checks_url' );
}

function flxa_system_tab_redirect() {
	if ( flxa_system_moved() && 'system' === sanitize_key( wp_unslash( $_GET['tab'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		wp_safe_redirect( flxp_system_checks_url() );
		exit;
	}
}

function flxa_settings_menu() {
	add_options_page( __( 'Admin theme', 'flx-admin' ), __( 'Admin theme', 'flx-admin' ), 'manage_options', 'flx-admin', 'flxa_settings_render' );
}

/*
 * The page opens the same React screen as the FluxWP app (assets/flux/, tools/fluxapp/embed-sync.sh flx-admin)
 * through Flux GraphQL's screen shell. There is no classic page; when the screen cannot open the shell says so.
 */
add_action(
	'admin_menu',
	static function () {
		if ( ! function_exists( 'flx_admin_screen' ) ) {
			return;
		}
		flx_admin_screen(
			'flx-admin',
			array(
				'module'     => 'flxa-admin',
				'assets_dir' => FLXA_DIR . 'assets/flux/',
				'assets_url' => FLXA_URL . 'assets/flux/',
				'version'    => FLXA_VERSION,
				'options'    => static function () {
					$accent = (string) flxa_accent();
					return array(
						'appIcon' => FLXA_URL . 'assets/icon.svg',
						'colors'  => array(
							'primary'         => $accent,
							'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
						),
					);
				},
				'start_param'  => 'tab',
				'container_id' => 'flxa-app',
				'preload'      => array( array( 'FlxAdminSettingsScreen' ) ),
				'wrap_class'   => 'flxa-settings-flux',
			)
		);
	},
	20
);

function flxa_settings_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	flx_admin_screen_page();
}
