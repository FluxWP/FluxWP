<?php
// Flux Backup: geri yükleme ekranı için birkaç demo yedek (yalnız yerel)
require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
wp_set_current_user( 1 );
if ( count( flxy_backup_list() ) >= 3 ) { echo "backups var\n"; return; }
$r = array(
	flxy_backup_create( array( 'type' => 'db', 'label' => 'Veritabanı', 'sync' => true ) ),
	flxy_backup_create( array( 'type' => 'pre_update', 'paths' => array( 'wp-content/plugins/flx-roles' ), 'context' => array( 'source' => 'flx-setup', 'packages' => array( array( 'type' => 'plugin', 'slug' => 'flx-roles', 'name' => 'Flux Roles', 'from_version' => '1.1.9', 'to_version' => '1.2.0' ) ) ), 'sync' => true ) ),
	flxy_backup_create( array( 'type' => 'manual', 'label' => 'Kampanya öncesi', 'paths' => array( 'wp-content/themes/fluxwp' ), 'sync' => true ) ),
);
foreach ( $r as $b ) { echo is_wp_error( $b ) ? $b->get_error_message() : ( $b['id'] ?? '?' ), "\n"; }
