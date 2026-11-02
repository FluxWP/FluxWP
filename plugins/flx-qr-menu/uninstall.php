<?php
/**
 * Kaldırma: ayarlar, adres eşlemesi, yetkiler, Menü yöneticisi rolü ve geçici sayaçlar silinir.
 * Menüler ve geçmiş yalnız "Eklenti silinince menüleri ve geçmişi de sil" seçiliyse silinir.
 *
 * @package FLX_QR_Menu
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$flxq_settings = get_option( 'flxq_settings', array() );
$flxq_delete   = is_array( $flxq_settings ) && ! empty( $flxq_settings['delete_on_uninstall'] );

if ( $flxq_delete ) {
	$flxq_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'flxq_menu' ) );
	foreach ( $flxq_ids as $flxq_id ) {
		wp_delete_post( (int) $flxq_id, true );
	}
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}flxq_history" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
}

delete_option( 'flxq_settings' );
delete_option( 'flxq_db_version' );
delete_option( 'flxq_routes' );
if ( $flxq_delete ) {
	delete_option( 'flxq_keys' );
}

$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_flxq_fail_' ) . '%', $wpdb->esc_like( '_transient_timeout_flxq_fail_' ) . '%' ) );

$flxq_caps = array( 'flxq_manage_menus', 'flxq_edit_menus', 'flxq_create_menus', 'flxq_set_address' );
foreach ( wp_roles()->role_objects as $flxq_role ) {
	foreach ( $flxq_caps as $flxq_cap ) {
		if ( $flxq_role->has_cap( $flxq_cap ) ) {
			$flxq_role->remove_cap( $flxq_cap );
		}
	}
}
// Menü yöneticileri rolsüz kalmasın: abone yapılır.
foreach ( get_users( array( 'role' => 'flxq_menu_manager' ) ) as $flxq_user ) {
	$flxq_user->remove_role( 'flxq_menu_manager' );
	if ( ! $flxq_user->roles ) {
		$flxq_user->add_role( 'subscriber' );
	}
}
remove_role( 'flxq_menu_manager' );
// Kullanıcı başına verilen yetkiler.
foreach ( get_users( array( 'meta_key' => $wpdb->get_blog_prefix() . 'capabilities', 'meta_value' => 'flxq_', 'meta_compare' => 'LIKE' ) ) as $flxq_user ) { // phpcs:ignore WordPress.DB.SlowDBQuery
	foreach ( $flxq_caps as $flxq_cap ) {
		if ( isset( $flxq_user->caps[ $flxq_cap ] ) ) {
			$flxq_user->remove_cap( $flxq_cap );
		}
	}
}
