<?php
/**
 * Kaldırma: ayarlar ve POS hesapları (şifreli anahtarlar) silinir. İşlem kayıtları (wp_flxpos_txn) muhasebe
 * için kalır; tabloyu da silmek için wp-config.php'ye define( 'FLXPOS_UNINSTALL_DROP', true );
 *
 * @package FLX_POS
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'flxpos_accounts', 'flxpos_settings', 'flxpos_db' ) as $flxpos_opt ) {
	delete_option( $flxpos_opt );
}
wp_clear_scheduled_hook( 'flxpos_daily' );
if ( defined( 'FLXPOS_UNINSTALL_DROP' ) && FLXPOS_UNINSTALL_DROP ) {
	global $wpdb;
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'flxpos_txn' ); // phpcs:ignore WordPress.DB
}
