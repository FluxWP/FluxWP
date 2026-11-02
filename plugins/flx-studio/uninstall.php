<?php
/** Kaldırma: tablolar, ayarlar, yetkiler ve uploads/flx-studio silinir (Medya kitaplığına eklenen kopyalar kalır). */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
foreach ( array( 'items', 'folders', 'people', 'refs', 'templates' ) as $flxst_t ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}flxst_{$flxst_t}" ); // phpcs:ignore WordPress.DB
}
delete_option( 'flxst_settings' );
delete_option( 'flxst_db_version' );
foreach ( wp_roles()->role_objects as $flxst_role ) {
	$flxst_role->remove_cap( 'flxst_use' );
	$flxst_role->remove_cap( 'flxst_manage' );
}
$flxst_up = wp_upload_dir( null, false );
$flxst_dir = trailingslashit( $flxst_up['basedir'] ) . 'flx-studio';
if ( is_dir( $flxst_dir ) ) {
	$flxst_it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $flxst_dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $flxst_it as $flxst_f ) {
		$flxst_f->isDir() ? @rmdir( $flxst_f->getPathname() ) : @unlink( $flxst_f->getPathname() ); // phpcs:ignore
	}
	@rmdir( $flxst_dir ); // phpcs:ignore
}
