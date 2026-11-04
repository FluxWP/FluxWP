<?php
/**
 * Kaldırma: ayarlar ve önbellek kayıtları silinir. Wiki sayfaları (içerik) silinmez.
 *
 * @package FLX_Wiki
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'flxwk_settings', 'flxwk_seeded', 'flxwk_flush', 'flxwk_gen' ) as $flxwk_opt ) {
	delete_option( $flxwk_opt );
}
delete_transient( 'flxwk_index' );
delete_transient( 'flxwk_seed_stat' );
