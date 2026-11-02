<?php
/** Kaldırma: tablolar, ayarlar, şifreli anahtarlar, yetkiler ve uploads/flxsm silinir. Sosyal ağlardaki gönderilere dokunulmaz. */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
foreach ( array( 'accounts', 'posts', 'targets', 'metrics', 'log' ) as $flxsm_t ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}flxsm_{$flxsm_t}" ); // phpcs:ignore WordPress.DB
}
foreach ( array( 'flxsm_settings', 'flxsm_secrets', 'flxsm_db_version', 'flxsm_last_daily', 'flxsm_last_metrics', 'flxsm_last_tick', 'flxsm_loopback_err', 'flxsm_tick_key' ) as $flxsm_o ) {
	delete_option( $flxsm_o );
}
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%flxsm\_%' OR option_name LIKE '\_transient\_timeout\_%flxsm\_%'" ); // phpcs:ignore WordPress.DB
delete_post_meta_by_key( '_flxsm_auto' );
wp_clear_scheduled_hook( 'flxsm_tick' );
wp_clear_scheduled_hook( 'flxsm_daily' );
foreach ( wp_roles()->role_objects as $flxsm_role ) {
	foreach ( array( 'flxsm_post', 'flxsm_publish', 'flxsm_manage' ) as $flxsm_c ) {
		$flxsm_role->remove_cap( $flxsm_c );
	}
}
$flxsm_up  = wp_upload_dir( null, false );
$flxsm_dir = trailingslashit( $flxsm_up['basedir'] ) . 'flxsm';
if ( is_dir( $flxsm_dir ) ) {
	$flxsm_it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $flxsm_dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $flxsm_it as $flxsm_f ) {
		$flxsm_f->isDir() ? @rmdir( $flxsm_f->getPathname() ) : @unlink( $flxsm_f->getPathname() ); // phpcs:ignore
	}
	@rmdir( $flxsm_dir ); // phpcs:ignore
}
