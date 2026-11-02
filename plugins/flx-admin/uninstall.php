<?php
/**
 * Flux Admin kaldırılınca ayarlarını, menü düzenlerini, kişisel tercihleri (tema, sabitlemeler, modül filtreleri) ve önbelleklerini siler.
 * Yetkiler sekmesindeki değişiklikler WordPress rollerinde kalır; yedeği (flxa_caps_backup) de durur ki gerekirse elle dönülebilsin.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'flxa_settings', 'flxa_menus', 'flxa_menu_catalog' ) as $flxa_opt ) {
	delete_option( $flxa_opt );
}
foreach ( array( 'flxa_mode', 'flxa_pins', 'flxa_away', 'flxa_sub_away', 'flxa_sub_keep', 'flxa_wprefs', 'flxa_task_list', 'flxa_list_pp' ) as $flxa_meta ) {
	delete_metadata( 'user', 0, $flxa_meta, '', true );
}

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_flxa\\_%' OR option_name LIKE '\\_transient\\_timeout\\_flxa\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
