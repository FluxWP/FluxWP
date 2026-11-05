<?php
/**
 * Kaldırma: ayarlar ve POS hesapları (şifreli anahtarlar) silinir. İşlem ve banka hareketi kayıtları (wp_flxpos_txn, wp_flxpos_bank) muhasebe
 * için kalır; tabloyu da silmek için wp-config.php'ye define( 'FLXPOS_UNINSTALL_DROP', true );
 *
 * @package FLX_POS
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'flxpos_accounts', 'flxpos_settings', 'flxpos_db', 'flxpos_bank_db', 'flxpos_bank_sources' ) as $flxpos_opt ) {
	delete_option( $flxpos_opt );
}
wp_clear_scheduled_hook( 'flxpos_daily' );
wp_clear_scheduled_hook( 'flxpos_bank_pull' );
wp_clear_scheduled_hook( 'flxpos_bank_match' );
if ( defined( 'FLXPOS_UNINSTALL_DROP' ) && FLXPOS_UNINSTALL_DROP ) {
	global $wpdb;
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'flxpos_txn' ); // phpcs:ignore WordPress.DB
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'flxpos_bank' ); // phpcs:ignore WordPress.DB
}
