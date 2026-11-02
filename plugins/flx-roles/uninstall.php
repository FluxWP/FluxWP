<?php
/**
 * Kaldırma: roller silinir (kişiler varsayılan role geçer), ayarlar, referans kodları ve telefonlar silinir.
 * Siparişlerdeki _flx_ref_user kaydı bağış geçmişi olduğu için silinmez.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$flxr_default = (string) get_option( 'default_role', 'subscriber' );
foreach ( array_merge( array( 'flx_personel', 'flx_idareci', 'flx_yonetici' ), array_keys( (array) get_option( 'flxr_custom_roles', array() ) ) ) as $flxr_role ) {
	foreach ( get_users( array( 'role' => $flxr_role, 'fields' => 'all' ) ) as $flxr_user ) {
		$flxr_user->remove_role( $flxr_role );
		if ( ! $flxr_user->roles ) {
			$flxr_user->add_role( $flxr_default );
		}
	}
	remove_role( $flxr_role );
}
foreach ( wp_roles()->role_objects as $flxr_obj ) {
	$flxr_obj->remove_cap( 'flx_referans' );
}
delete_option( 'flxr_settings' );
delete_option( 'flxr_db_version' );
delete_option( 'flxr_gorev_synced' );
delete_option( 'flxr_custom_roles' );
delete_metadata( 'user', 0, 'flx_phone', '', true );
delete_metadata( 'user', 0, 'flx_phone_key', '', true );
delete_metadata( 'user', 0, 'flxr_ref_code', '', true );
