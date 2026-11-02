<?php
/**
 * Kaldırma: veriler yalnız Ayarlar'da "Kaldırınca verileri sil" açıksa silinir.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$flxg_settings = get_option( 'flxg_settings', array() );
if ( empty( $flxg_settings['delete_data'] ) ) {
	return;
}
global $wpdb;
foreach ( array( 'spaces', 'folders', 'lists', 'members', 'tasks', 'assignees', 'watchers', 'tags', 'task_tags', 'fields', 'values', 'views', 'comments', 'files', 'activity', 'notifications', 'docs', 'doc_pages', 'doc_blocks', 'doc_revs', 'doc_presence' ) as $flxg_t ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'flxg_' . $flxg_t ); // phpcs:ignore
}
foreach ( array( 'flxg_settings', 'flxg_db_version', 'flxg_seeded', 'flxg_struct_rev', 'flxg_mcp_keys', 'flxg_ai_usage' ) as $flxg_o ) {
	delete_option( $flxg_o );
}
delete_transient( 'flxg_ai_models' ); // eski sürümlerden kalan önbellek
delete_transient( 'flxg_uploads_size' );
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'flxg_email'" ); // phpcs:ignore
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'flxg_prefs'" ); // phpcs:ignore
$flxg_up = wp_upload_dir( null, false );
$flxg_dir = trailingslashit( $flxg_up['basedir'] ) . 'flx-tasks';
if ( is_dir( $flxg_dir ) ) {
	$flxg_it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $flxg_dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $flxg_it as $flxg_f ) {
		$flxg_f->isDir() ? rmdir( $flxg_f->getPathname() ) : unlink( $flxg_f->getPathname() ); // phpcs:ignore
	}
	rmdir( $flxg_dir ); // phpcs:ignore
}
wp_clear_scheduled_hook( 'flxg_daily' );
