<?php
/**
 * Kullanıcılar › Uygulama erişimi (yönetici olmayanlarda Profil altında) ve sol menüde "Uygulama" (yöneticiler).
 * İki sayfa da ortak React ekranıdır (includes/flux-ui.php); klasik PHP sayfası yoktur. Ekranların okuduğu
 * kataloglar admin-catalogs.php'de.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		$pages = array(
			add_users_page( __( 'App access', 'flx-app' ), __( 'App access', 'flx-app' ), 'read', 'flx-app', 'flxapp_render_page' )                                                                                  => 'flx-app',
			add_menu_page( __( 'Application', 'flx-app' ), __( 'Application', 'flx-app' ), 'manage_options', 'flxapp-settings', 'flxapp_render_settings_page', 'dashicons-smartphone', 72 ) => 'flxapp-settings',
		);
		foreach ( $pages as $hook => $page ) {
			add_action(
				'admin_print_styles-' . $hook,
				function () use ( $page ) {
					if ( flxapp_flux_available() ) {
						flxapp_flux_assets( $page );
					}
				}
			);
		}
	}
);

/** App access (everyone). */
function flxapp_render_page() {
	flxapp_flux_root();
}

/** Application (administrators). */
function flxapp_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'flx-app' ), 403 );
	}
	flxapp_flux_root();
}
