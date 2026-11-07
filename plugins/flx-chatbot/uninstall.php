<?php
/**
 * Eklenti silinince: tablolar, ayarlar ve yüklenen dosyalar kaldırılır.
 *
 * @package FLX_Chatbot
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
foreach ( array( 'flxcb_messages', 'flxcb_conversations' ) as $t ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . $t ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
}
foreach ( array( 'flxcb_settings', 'flxcb_db_version', 'flxcb_ai_day' ) as $o ) {
	delete_option( $o );
}
delete_transient( 'flxcb_team' );
delete_transient( 'flxcb_open_count' );
wp_clear_scheduled_hook( 'flxcb_cleanup' );

$up  = wp_upload_dir( null, false );
$dir = trailingslashit( $up['basedir'] ) . 'flx-chatbot';
if ( is_dir( $dir ) ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $f ) {
		$f->isDir() ? @rmdir( $f->getPathname() ) : @unlink( $f->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}
foreach ( wp_roles()->roles as $name => $r ) {
	$role = get_role( $name );
	$role && $role->remove_cap( 'flxcb_reply' );
}
