<?php
/**
 * Kaldırma: ayarlar, istatistik ve durum kayıtları silinir.
 *
 * @package FLX_Recaptcha
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'flxrc_settings', 'flxrc_stats', 'flxrc_status' ) as $flxrc_opt ) {
	delete_option( $flxrc_opt );
}
