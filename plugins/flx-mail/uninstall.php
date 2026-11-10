<?php
/**
 * Silinirken: tablolar ve ayarlar kaldırılır. Flux Certificates sağlayıcıları eski haline döner.
 *
 * @package flx-mail
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

foreach ( array( 'log', 'connections', 'campaigns', 'queue', 'mail', 'drafts' ) as $flxi_t ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'flxi_' . $flxi_t ); // phpcs:ignore
}
foreach ( array( 'flxi_settings', 'flxi_templates', 'flxi_discovered', 'flxi_db_version', 'flxi_conn_tests', 'flxi_dns_check', 'flxi_last_test', 'flxi_queue_lock', 'flxi_unsubscribed', 'flxi_wc_branded', 'flxi_flxs_migration', 'flxi_quota', 'flxi_quota_full_days', 'flxi_quota_paused', 'flxi_smtp_auth', 'flxi_flxs_restore', 'flxi_layouts', 'flxi_mailbox_state', 'flxi_mailbox_pending', 'flxi_art_160', 'flxi_wa_state' ) as $flxi_o ) {
	delete_option( $flxi_o );
}
$flxi_log = $wpdb->prefix . 'flx_background_log';
$flxi_log_found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $flxi_log ) ) );
if ( $flxi_log_found === $flxi_log ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$flxi_log} WHERE plugin = %s", 'flx-mail' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( null === $wpdb->get_var( "SELECT id FROM {$flxi_log} LIMIT 1" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$flxi_log}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		delete_option( 'flx_background_log_db' );
	}
}
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'flxi_social'" ); // phpcs:ignore
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'flxi\\_wac\\_%'" ); // phpcs:ignore
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%flxi\_wacm\_%'" ); // phpcs:ignore
wp_clear_scheduled_hook( 'flxi_daily' );
wp_clear_scheduled_hook( 'flxi_run_queue' );
wp_clear_scheduled_hook( 'flxi_netgsm_poll' );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'flxi_netgsm_' ) . '%' ) );
wp_clear_scheduled_hook( 'flxi_mailbox_sync' );
$flxi_role = get_role( 'administrator' );
if ( $flxi_role ) {
	$flxi_role->remove_cap( 'flxi_manage' );
}
