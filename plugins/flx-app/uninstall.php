<?php
/** Kaldırma: belirteç tablosu, istemciler, ayarlar ve menü kayıtları silinir (tüm uygulama oturumları kapanır). */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}flxapp_tokens" ); // phpcs:ignore WordPress.DB
foreach ( array( 'flxapp_settings', 'flxapp_clients', 'flxapp_db_version', 'flxapp_menu_catalog', 'flxapp_menu_catalog_hash' ) as $flxapp_opt ) {
	delete_option( $flxapp_opt );
}
delete_metadata( 'user', 0, 'flxapp_menu', '', true );
delete_metadata( 'user', 0, 'flxapp_menu_hash', '', true );
