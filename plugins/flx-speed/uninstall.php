<?php
/**
 * Kaldırma: ayarlar, kayıt tablosu ve yetki silinir (kayıtlar yalnız teşhis verisi).
 *
 * @package flx-speed
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}flxp_requests" ); // phpcs:ignore
delete_option( 'flxp_settings' );
delete_option( 'flxp_db_version' );
delete_option( 'flxp_last_cleanup' );
delete_option( 'flxp_cron_seen' );
delete_option( 'flxp_cron_stats' );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%flxp\\_prof\\_%'" ); // phpcs:ignore
foreach ( wp_roles()->role_objects as $role ) {
	$role->remove_cap( 'flxp_view' );
}
