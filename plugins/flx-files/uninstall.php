<?php
/**
 * Kaldırma: ayarlar, Etkinlik kaydı ve zamanlanmış iş silinir.
 * Çöp kutusu ve sürüm klasörü (wp-content/flx-files-*) kullanıcı verisi olduğu için yerinde bırakılır;
 * gerekmiyorsa FTP ile silinebilir.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'flxfm_settings', 'flxfm_log', 'flxfm_daily_at', 'flxfm_usage' ) as $flxfm_opt ) {
	delete_option( $flxfm_opt );
}
wp_clear_scheduled_hook( 'flxfm_daily' );
