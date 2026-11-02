<?php
/**
 * Kaldırma: motorun kendi kayıtları silinir. Ön ayarların yazdığı ayarlar, sayfalar, medya ve kurum profili
 * (flx_profile) sitede kalır; geri almak için kaldırmadan önce Görünüm › Ön ayarlar › Ön ayarı kaldır.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
foreach ( array( 'flxk_state', 'flxk_history', 'flxk_patches', 'flxk_settings', 'flxk_install_queue' ) as $flxk_opt ) {
	delete_option( $flxk_opt );
}
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'flxk\\_run\\_%'" ); // phpcs:ignore WordPress.DB
