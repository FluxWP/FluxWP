<?php
/**
 * Kaldırma: ayarlar, müşteri özeti tablosu ve önbellekler silinir. Siparişlerdeki havale açıklaması ve
 * dekont kayıtları (_flxcm_*) sipariş geçmişi olduğu için kalır; dekont dosyaları uploads/flxcm-dekont/'ta.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}flxcm_customers" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( array( 'flxcm_settings', 'flxcm_db_version', 'flxcm_installed_at', 'flxcm_stats_ver', 'flxcm_customers_built' ) as $flxcm_opt ) {
	delete_option( $flxcm_opt );
}
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_flxcm\_%' OR option_name LIKE '\_transient\_timeout\_flxcm\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
wp_clear_scheduled_hook( 'flxcm_daily' );
