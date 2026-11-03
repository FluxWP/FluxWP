<?php
/**
 * Kaldırma: tablolar, ayarlar, yetki, zamanlayıcı ve veri dizini silinir (istatistikler yalnız bu eklentiye ait).
 *
 * @package flx-analytics
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
foreach ( array( 'paths', 'refs', 'site', 'pages', 'referrers', 'dims', 'events', 'visits', 'fsteps', 'seen', 'recent', 'tm_installs', 'tm_errors', 'tm_esites', 'tm_days' ) as $flxan_t ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}flxan_{$flxan_t}" ); // phpcs:ignore
}
foreach ( array( 'flxan_settings', 'flxan_db_version', 'flxan_version', 'flxan_token', 'flxan_last_agg', 'flxan_last_rows', 'flxan_last_error', 'flxan_last_daily', 'flxan_agg_lock', 'flxan_fast_test', 'flxan_fast_test_time', 'flxan_first_day', 'flxan_koko_imported', 'flxan_funnels', 'flxan_ref_names', 'flxan_tm_db' ) as $flxan_o ) {
	delete_option( $flxan_o );
}
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%flxan\\_%' OR option_name LIKE '\\_transient\\_timeout\\_%flxan\\_%'" ); // phpcs:ignore
wp_clear_scheduled_hook( 'flxan_aggregate' );
wp_clear_scheduled_hook( 'flxan_daily' );
foreach ( wp_roles()->role_objects as $flxan_role ) {
	$flxan_role->remove_cap( 'flxan_view' );
}
$flxan_up  = wp_upload_dir( null, false );
$flxan_dir = rtrim( $flxan_up['basedir'], '/' ) . '/flx-analytics';
if ( is_dir( $flxan_dir ) ) {
	foreach ( (array) glob( $flxan_dir . '/{,.}*', GLOB_BRACE ) as $flxan_f ) {
		if ( is_file( $flxan_f ) ) {
			@unlink( $flxan_f ); // phpcs:ignore
		}
	}
	@rmdir( $flxan_dir ); // phpcs:ignore
}
