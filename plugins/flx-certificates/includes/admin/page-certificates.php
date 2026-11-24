<?php
defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_flxs_cert_bulk', 'flxs_handle_cert_bulk' );

function flxs_handle_cert_bulk() {
	check_admin_referer( 'flxs_cert_bulk' );
	if ( ! flxs_can_use() ) {
		wp_die( esc_html__( 'Not allowed', 'flx-certificates' ) );
	}
	$ids    = array_filter( array_map( 'intval', (array) ( $_POST['ids'] ?? array() ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$action = sanitize_key( $_POST['bulk'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$go     = FLXS_Certificate_Service::bulk_admin( $ids, $action, (int) ( $_POST['event'] ?? 0 ), (int) ( $_POST['ref_user'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
	flxs_redirect( $go['page'], $go['args'] );
}

function flxs_cert_message_summary( $ids ) {
	global $wpdb;
	if ( ! $ids ) {
		return array();
	}
	$rows = $wpdb->get_results( 'SELECT certificate_id, channel, status, COUNT(*) n FROM ' . flxs_table( 'messages' ) . ' WHERE certificate_id IN (' . implode( ',', array_map( 'intval', $ids ) ) . ') GROUP BY certificate_id, channel, status', ARRAY_A );
	$out  = array();
	foreach ( $rows as $r ) {
		$out[ $r['certificate_id'] ][ $r['channel'] ][ $r['status'] ] = (int) $r['n'];
	}
	return $out;
}

function flxs_ref_counts() {
	global $wpdb;
	$out = array();
	$where = flxs_scoped() ? ' WHERE ' . flxs_scope_sql( '' ) : '';
	foreach ( $wpdb->get_results( 'SELECT ref_user_id, COUNT(*) n FROM ' . flxs_table( 'certificates' ) . $where . ' GROUP BY ref_user_id', ARRAY_A ) as $r ) {
		$out[ (int) $r['ref_user_id'] ] = (int) $r['n'];
	}
	return $out;
}


/** Etkinlik başına sertifika sayısı (event_id => adet) */
function flxs_event_counts() {
	global $wpdb;
	$out   = array();
	$where = flxs_scoped() ? ' WHERE ' . flxs_scope_sql( '' ) : '';
	foreach ( $wpdb->get_results( 'SELECT event_id, COUNT(*) n FROM ' . flxs_table( 'certificates' ) . $where . ' GROUP BY event_id', ARRAY_A ) as $r ) { // phpcs:ignore
		$out[ (int) $r['event_id'] ] = (int) $r['n'];
	}
	return $out;
}
