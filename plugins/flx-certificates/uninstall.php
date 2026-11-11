<?php
/**
 * Eklenti silinirken çalışır. Veriler yalnızca Ayarlar > "Silerken verileri de sil" açıksa silinir.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
$flxs_log = $wpdb->prefix . 'flx_background_log';
$flxs_log_found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $flxs_log ) ) );
if ( $flxs_log_found === $flxs_log ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$flxs_log} WHERE plugin = %s", 'flx-certificates' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( null === $wpdb->get_var( "SELECT id FROM {$flxs_log} LIMIT 1" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$flxs_log}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		delete_option( 'flx_background_log_db' );
	}
}

$flxs_settings = get_option( 'flxs_settings', array() );
if ( empty( $flxs_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
foreach ( array( 'people', 'templates', 'certificates', 'batches', 'messages', 'providers', 'logs', 'events' ) as $flxs_t ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'flxs_' . $flxs_t ); // phpcs:ignore
}
// People live in the shared FluxWP table (2.0.0) and stay for the other packages; only the Certificates tie goes.
$flxs_links = $wpdb->prefix . 'flx_people_links';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $flxs_links ) ) ) === $flxs_links ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$flxs_links} WHERE source = %s", 'certificates' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
foreach ( array( 'flxs_settings', 'flxs_people_shared', 'flxs_events_shared', 'flxs_custom_fonts', 'flxs_db_version', 'flxs_defaults_installed', 'flxs_flush_rewrite', 'flxs_queue_lock', 'flxs_recipients', 'flxs_tpl_migration', 'flxs_site', 'flxs_caps_dashboard' ) as $flxs_o ) {
	delete_option( $flxs_o );
}
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'flxs\_slot\_%' OR option_name LIKE '\_transient\_flxs\_%' OR option_name LIKE '\_transient\_timeout\_flxs\_%'" ); // phpcs:ignore

wp_clear_scheduled_hook( 'flxs_queue_tick' );
wp_clear_scheduled_hook( 'flxs_daily' );
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'flxs_run_queue' );
}
remove_role( 'flxs_personel' );
foreach ( array( 'administrator' ) as $flxs_r ) {
	$flxs_role = get_role( $flxs_r );
	if ( $flxs_role ) {
		$flxs_role->remove_cap( 'flxs_use' );
		$flxs_role->remove_cap( 'flxs_manage' );
		$flxs_role->remove_cap( 'flxs_bulk' );
	}
}

$flxs_up = wp_upload_dir();
$flxs_dir = trailingslashit( $flxs_up['basedir'] ) . 'flx-certificates';
if ( is_dir( $flxs_dir ) ) {
	$flxs_it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $flxs_dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $flxs_it as $flxs_f ) {
		$flxs_f->isDir() ? rmdir( $flxs_f->getPathname() ) : unlink( $flxs_f->getPathname() ); // phpcs:ignore
	}
	rmdir( $flxs_dir ); // phpcs:ignore
}
