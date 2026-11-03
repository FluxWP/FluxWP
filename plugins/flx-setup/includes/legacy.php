<?php
/**
 * Eski "Flux Updater" (flx-updater) eklentisinden geçiş.
 *
 * Güncelleyici artık FluxWP Panel'in parçası. Eski eklenti hâlâ etkinse aynı sınıflar iki kez
 * yüklenmesin diye bu istekte güncelleyici modülü atlanır, eski eklenti kapatılır; sonraki istekte
 * FluxWP Panel devralır. Ayarlar (flxu_*) aynı adla kaldığı için kaybolmaz.
 */

defined( 'ABSPATH' ) || exit;

const FLXK_LEGACY_UPDATER = 'flx-updater/flx-updater.php';

/** Yükleme anında: eski eklentinin sınıfları yüklendi ya da yüklenecek mi. FluxWP Panel kendi FLXU_* sabitlerini bundan sonra tanımlar. */
function flxk_legacy_updater_active() {
	if ( defined( 'FLXU_VERSION' ) || class_exists( 'FLXU_Updater', false ) ) {
		return true;
	}
	return flxk_legacy_updater_listed();
}

/** Eski eklenti etkin eklentiler listesinde mi. */
function flxk_legacy_updater_listed() {
	$active = (array) get_option( 'active_plugins', array() );
	if ( is_multisite() ) {
		$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	}
	return in_array( FLXK_LEGACY_UPDATER, $active, true );
}

/** Etkinleşince: eski güncelleyiciyi kapat, ilk kurulumsa sihirbazı aç. */
function flxk_on_activate( $network_wide = false ) {
	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( is_plugin_active( FLXK_LEGACY_UPDATER ) || is_plugin_active_for_network( FLXK_LEGACY_UPDATER ) ) {
		deactivate_plugins( FLXK_LEGACY_UPDATER, true, $network_wide );
	}
	if ( ! get_option( 'flxk_onboarding' ) ) {
		update_option( 'flxk_onboarding', 'pending', false );
		if ( ! $network_wide && ! isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			set_transient( 'flxk_onboarding_redirect', 1, 60 );
		}
	}
}

add_action(
	'admin_init',
	static function () {
		if ( ! flxk_legacy_updater_listed() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		deactivate_plugins( FLXK_LEGACY_UPDATER, true, is_plugin_active_for_network( FLXK_LEGACY_UPDATER ) );
		set_transient( 'flxk_notice', array( 'ok', __( 'Flux Updater artık FluxWP Panel\'in parçası; eski eklenti kapatıldı, ayarları korundu. Eklentiler ekranından silebilirsiniz.', 'flx-setup' ) ), 120 );
	},
	1
);
