<?php
/**
 * Flux Updater kaldırılınca ayarlarını siler.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'flxu_settings', 'flxu_status', 'flxu_known_slugs', 'flxu_catalog_last', 'flxu_sources', 'flxu_source_status', 'flxu_source_catalogs', 'flxu_pins' ) as $flxu_option ) {
	delete_site_option( $flxu_option );
}
delete_site_transient( 'flxu_catalog' );
