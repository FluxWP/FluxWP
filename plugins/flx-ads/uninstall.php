<?php
/**
 * Kaldırma: Flux Ads tabloları (gönderim kaydı, sipariş atfı, reklam harcaması, kitle üyeleri), ayarlar, yetkiler ve zamanlanmış iş silinir.
 * Siparişler ve bağışlar WooCommerce / Flux Donate'te kalır; siparişteki `_flxads` atıf bilgisi de kalır. Meta'daki kitleler silinmez (Reklam Yöneticisi'nden silinir).
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
foreach ( array( 'conversions', 'orders', 'spend', 'aud_members' ) as $flxads_t ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}flxads_{$flxads_t}" ); // phpcs:ignore WordPress.DB
}
foreach ( array( 'flxads_settings', 'flxads_db_version', 'flxads_heartbeat', 'flxads_tests', 'flxads_spend_sync', 'flxads_tool_log', 'flxads_audiences', 'flxads_mgr_log', 'flxads_mgr_keys', 'flxads_drafts', 'flxads_suggestions', 'flxads_opt_run' ) as $flxads_opt ) {
	delete_option( $flxads_opt );
}
foreach ( wp_roles()->role_objects as $flxads_role ) {
	$flxads_role->remove_cap( 'flxads_view' );
	$flxads_role->remove_cap( 'flxads_manage' );
}
wp_clear_scheduled_hook( 'flxads_retry' );
