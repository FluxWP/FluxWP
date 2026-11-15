<?php
/**
 * Uninstall: settings, cached manifests and unclaimed uploads. Package data is not ours to delete.
 *
 * @package FLX_GraphQL
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'flxgql_settings' );
delete_option( 'flxgql_rewrite' );

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_flxgql\\_%' OR option_name LIKE '\\_transient\\_timeout\\_flxgql\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$up  = wp_upload_dir( null, false );
$tmp = trailingslashit( (string) $up['basedir'] ) . 'flx-uploads/tmp';
if ( is_dir( $tmp ) ) {
	foreach ( (array) scandir( $tmp ) as $name ) {
		if ( is_string( $name ) && '.' !== $name[0] && is_file( $tmp . '/' . $name ) ) {
			wp_delete_file( $tmp . '/' . $name );
		}
	}
}
flush_rewrite_rules( false );
