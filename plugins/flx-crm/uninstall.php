<?php
/**
 * Kaldırma: CRM tabloları (kişiler, olaylar, etiketler, segmentler), ayarlar, yetkiler ve günlük iş silinir.
 * Bağış, sipariş, form ve sohbet kayıtları kendi eklentilerinde kalır.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
foreach ( array( 'contacts', 'links', 'events', 'tags', 'contact_tags', 'segments' ) as $flxcrm_t ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}flxcrm_{$flxcrm_t}" ); // phpcs:ignore WordPress.DB
}
foreach ( array( 'flxcrm_settings', 'flxcrm_db_version', 'flxcrm_installed_at', 'flxcrm_scored_at', 'flxcrm_synced_at', 'flxcrm_segments_seeded' ) as $flxcrm_opt ) {
	delete_option( $flxcrm_opt );
}
delete_transient( 'flxcrm_stats' );
foreach ( wp_roles()->role_objects as $flxcrm_role ) {
	$flxcrm_role->remove_cap( 'flxcrm_view' );
	$flxcrm_role->remove_cap( 'flxcrm_manage' );
}
wp_clear_scheduled_hook( 'flxcrm_daily' );
