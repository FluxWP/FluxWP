<?php
/**
 * Eklenti silinirken: "verileri de sil" ayarı açıksa tablolar, ayarlar ve medya klasörü silinir.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$flxc_s = get_option( 'flxc_settings', array() );
if ( empty( $flxc_s['delete_data'] ) ) {
	return;
}
global $wpdb;
foreach ( array( 'chats', 'messages', 'replies', 'receipts', 'bench_runs', 'bench_results', 'memory', 'jobs', 'blocklist', 'reminders', 'editor_sessions', 'dekonts' ) as $flxc_t ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'flxc_' . $flxc_t ); // phpcs:ignore
}
foreach ( array( 'flxc_settings', 'flxc_db_version', 'flxc_replies_seeded', 'flxc_conn_state', 'flxc_conn_rev', 'flxc_bot_bench', 'flxc_bot_prompt', 'flxc_bot_docs', 'flxc_ai_usage', 'flxc_bot_stats', 'flxc_bot_donations', 'flxc_bench_key', 'flxc_notify_sent', 'flxc_jobs_lock', 'flxc_mirror_lock', 'flxc_mirror_last', 'flxc_mirror_error' ) as $flxc_o ) {
	delete_option( $flxc_o );
}
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'flxc\_last\_webhook\_%'" ); // phpcs:ignore
$flxc_up  = wp_upload_dir( null, false );
$flxc_dir = trailingslashit( $flxc_up['basedir'] ) . 'flx-chat';
if ( is_dir( $flxc_dir ) ) {
	$flxc_it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $flxc_dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $flxc_it as $flxc_f ) {
		$flxc_f->isDir() ? @rmdir( $flxc_f->getPathname() ) : @unlink( $flxc_f->getPathname() ); // phpcs:ignore
	}
	@rmdir( $flxc_dir ); // phpcs:ignore
}
delete_post_meta_by_key( '_flxc_bot_hide' );
remove_role( 'flxc_agent' );
