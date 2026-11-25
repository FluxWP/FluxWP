<?php
defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_flxs_person_save', 'flxs_handle_person_save' );
add_action( 'admin_post_flxs_person_delete', 'flxs_handle_person_delete' );
add_action( 'admin_post_flxs_people_bulk', 'flxs_handle_people_bulk' );

function flxs_handle_person_save() {
	check_admin_referer( 'flxs_person_save' );
	if ( ! flxs_can_use() ) {
		wp_die( 'Yetki yok' );
	}
	$id = (int) ( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( $id && ! flxs_can_see_person( $id ) ) {
		wp_die( 'Yetki yok' );
	}
	$go = FLXS_Person_Service::save_admin(
		$id,
		(string) wp_unslash( $_POST['name'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification
		(string) wp_unslash( $_POST['phone'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification
		(string) wp_unslash( $_POST['email'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification
		(string) wp_unslash( $_POST['note'] ?? '' ) // phpcs:ignore WordPress.Security.NonceVerification
	);
	flxs_redirect( $go['page'], $go['args'] );
}

function flxs_handle_person_delete() {
	check_admin_referer( 'flxs_person_delete' );
	if ( ! flxs_can_manage() ) {
		wp_die( 'Yetki yok' );
	}
	$go = FLXS_Person_Service::delete_admin( (int) ( $_POST['id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
	flxs_redirect( $go['page'], $go['args'] );
}

function flxs_handle_people_bulk() {
	check_admin_referer( 'flxs_people_bulk' );
	$ids = array_filter( array_map( 'intval', (array) ( $_POST['ids'] ?? array() ) ) );
	if ( ! $ids ) {
		flxs_redirect( 'flxs-kisiler', array( 'flxs_err' => 'Kişi seçmediniz.' ) );
	}
	flxs_redirect( 'flxs-toplu', array( 'people' => implode( ',', $ids ), 'channel' => sanitize_key( $_POST['channel'] ?? 'sms' ) ) );
}
