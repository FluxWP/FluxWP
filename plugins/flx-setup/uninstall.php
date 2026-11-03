<?php
/**
 * Kaldırma: motorun ve güncelleyicinin kendi kayıtları silinir. Ön ayarların yazdığı ayarlar, sayfalar, medya ve
 * kurum profili (flx_profile) sitede kalır; geri almak için kaldırmadan önce Görünüm › Ön ayarlar › Ön ayarı kaldır.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
foreach ( array( 'flxk_state', 'flxk_history', 'flxk_patches', 'flxk_settings', 'flxk_install_queue', 'flxk_onboarding', 'flxk_activation_repaired', 'flxk_repair_version', 'flxk_telemetry', 'flxk_telemetry_at', 'flxk_telemetry_id', 'flxk_telemetry_queue', 'flxk_telemetry_last' ) as $flxk_opt ) {
	delete_option( $flxk_opt );
}
wp_clear_scheduled_hook( 'flxk_telemetry_send' );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'flxk\\_run\\_%'" ); // phpcs:ignore WordPress.DB

// Eski Flux Updater hâlâ kuruluysa onun ayarlarına dokunma.
if ( ! file_exists( WP_PLUGIN_DIR . '/flx-updater/flx-updater.php' ) ) {
	foreach ( array( 'flxu_settings', 'flxu_status', 'flxu_known_slugs', 'flxu_catalog_last', 'flxu_sources', 'flxu_source_status', 'flxu_source_catalogs', 'flxu_pins', 'flxu_install_ids' ) as $flxu_option ) {
		delete_site_option( $flxu_option );
	}
	delete_site_transient( 'flxu_catalog' );
}
