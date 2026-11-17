<?php
/**
 * Doküman sayfası yorumları ve sayfa koruması (kilit, erişim) için REST uçları. İş FLXG_Doc_Service'te;
 * GraphQL aynı servisi çağırır (includes/graphql/schema.php).
 *
 *   GET    doc-pages/{id}/comments          yorumlar + sürüm
 *   POST   doc-pages/{id}/comments          { body, anchor } yeni konu, { body, parent } yanıt
 *   PATCH  doc-comments/{id}                { body } düzenle, { resolved } çöz / yeniden aç
 *   DELETE doc-comments/{id}
 *   POST   doc-pages/{id}/lock              { locked: bool }
 *   GET    doc-pages/{id}/access            erişim + üyeler
 *   POST   doc-pages/{id}/access            { access: 'doc'|'restricted', members: [ {user, role} ] }
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	static function () {
		flxg_route( '/doc-pages/(?P<id>\d+)/comments', 'GET', 'flxg_rest_doc_comments' );
		flxg_route( '/doc-pages/(?P<id>\d+)/comments', 'POST', 'flxg_rest_doc_comment_create' );
		flxg_route( '/doc-comments/(?P<id>\d+)', 'POST, PUT, PATCH', 'flxg_rest_doc_comment_update' );
		flxg_route( '/doc-comments/(?P<id>\d+)', 'DELETE', 'flxg_rest_doc_comment_delete' );
		flxg_route( '/doc-pages/(?P<id>\d+)/lock', 'POST', 'flxg_rest_doc_page_lock' );
		flxg_route( '/doc-pages/(?P<id>\d+)/access', 'GET', 'flxg_rest_doc_page_access' );
		flxg_route( '/doc-pages/(?P<id>\d+)/access', 'POST', 'flxg_rest_doc_page_access_save' );
	}
);

function flxg_rest_doc_comments( WP_REST_Request $r ) {
	return FLXG_Doc_Service::comments( (int) $r['id'], get_current_user_id() );
}

function flxg_rest_doc_comment_create( WP_REST_Request $r ) {
	$b = flxg_body( $r );
	$c = FLXG_Doc_Service::create( (int) $r['id'], (int) ( $b['parent'] ?? 0 ), $b['anchor'] ?? null, (string) ( $b['body'] ?? '' ), get_current_user_id() );
	return is_wp_error( $c ) ? $c : array( 'comment' => $c, 'rev' => FLXG_Doc_Service::rev( (int) $r['id'] ) );
}

function flxg_rest_doc_comment_update( WP_REST_Request $r ) {
	$b   = flxg_body( $r );
	$uid = get_current_user_id();
	$c   = null;
	if ( isset( $b['body'] ) ) {
		$c = FLXG_Doc_Service::update( (int) $r['id'], (string) $b['body'], $uid );
	}
	if ( ! is_wp_error( $c ) && isset( $b['resolved'] ) ) {
		$c = FLXG_Doc_Service::resolve( (int) $r['id'], ! empty( $b['resolved'] ), $uid );
	}
	if ( null === $c ) {
		return flxg_error( 'flxg_empty', __( 'Nothing to change.', 'flx-tasks' ) );
	}
	return is_wp_error( $c ) ? $c : array( 'comment' => $c, 'rev' => FLXG_Doc_Service::rev( (int) $c['page'] ) );
}

function flxg_rest_doc_comment_delete( WP_REST_Request $r ) {
	$d = FLXG_Doc_Service::delete( (int) $r['id'], get_current_user_id() );
	return is_wp_error( $d ) ? $d : $d + array( 'rev' => FLXG_Doc_Service::rev( $d['page'] ) );
}

function flxg_rest_doc_page_lock( WP_REST_Request $r ) {
	$b = flxg_body( $r );
	$l = FLXG_Doc_Service::lock( (int) $r['id'], ! empty( $b['locked'] ), get_current_user_id() );
	if ( is_wp_error( $l ) ) {
		return $l;
	}
	$p = flxg_get_page( (int) $r['id'] );
	return $l + array( 'pages' => $p ? flxg_doc_pages_for( (int) $p['doc_id'] ) : array() );
}

function flxg_rest_doc_page_access( WP_REST_Request $r ) {
	return FLXG_Doc_Service::access( (int) $r['id'], get_current_user_id() );
}

function flxg_rest_doc_page_access_save( WP_REST_Request $r ) {
	$b = flxg_body( $r );
	$a = FLXG_Doc_Service::set_access( (int) $r['id'], (string) ( $b['access'] ?? '' ), array_values( array_filter( (array) ( $b['members'] ?? array() ), 'is_array' ) ), get_current_user_id() );
	if ( is_wp_error( $a ) ) {
		return $a;
	}
	$p = flxg_get_page( (int) $r['id'] );
	return $a + array( 'pages' => $p ? flxg_doc_pages_for( (int) $p['doc_id'] ) : array() );
}
