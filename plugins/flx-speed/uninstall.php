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
delete_option( 'flxp_du' );
delete_option( 'flxp_ps_seen' );
delete_option( 'flxp_cache_test' );
delete_option( 'flxp_cache_pending' );
delete_transient( 'flxp_ps_anim' );
// PageSpeed önbelleği (uploads/flx-speed).
$flxp_up = wp_upload_dir( null, false );
$flxp_dir = trailingslashit( $flxp_up['basedir'] ) . 'flx-speed';
foreach ( array( 'css', 'js', 'meta' ) as $flxp_sub ) {
	foreach ( (array) glob( $flxp_dir . '/' . $flxp_sub . '/*' ) as $flxp_f ) {
		if ( $flxp_f && is_file( $flxp_f ) ) {
			@unlink( $flxp_f ); // phpcs:ignore
		}
	}
	@rmdir( $flxp_dir . '/' . $flxp_sub ); // phpcs:ignore
}
@unlink( $flxp_dir . '/index.php' ); // phpcs:ignore
@rmdir( $flxp_dir ); // phpcs:ignore
delete_transient( 'flxp_cpu_sample' );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%flxp\\_prof\\_%'" ); // phpcs:ignore
foreach ( wp_roles()->role_objects as $role ) {
	$role->remove_cap( 'flxp_view' );
}
