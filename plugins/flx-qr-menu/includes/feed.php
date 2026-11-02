<?php
/**
 * Menü sitelerinin veri ucu: GET /wp-json/flx-qr-menu/v1/feed (sözleşme: API.md / SOZLESME.md).
 *
 * Kimlik: X-FLXQ-Key. Yanıtlar önbelleğe alınmaz; ETag "flxq-<menuId>-<version>" eşleşirse gövdesiz 304.
 * Yanlış anahtar denemeleri IP başına sayılır: 10 dakikada 30'u geçince 429.
 *
 * @package FLX_QR_Menu
 */

defined( 'ABSPATH' ) || exit;

const FLXQ_FAIL_LIMIT  = 30;
const FLXQ_FAIL_WINDOW = 600;

add_action( 'rest_api_init', 'flxq_register_feed_route' );
add_filter( 'rest_post_dispatch', 'flxq_feed_shape_response', 20, 3 );
add_filter( 'rest_pre_serve_request', 'flxq_feed_serve_304', 10, 4 );

function flxq_register_feed_route() {
	register_rest_route(
		FLXQ_NS,
		'/feed',
		array(
			'methods'             => 'GET',
			'callback'            => 'flxq_feed',
			'permission_callback' => 'flxq_feed_permission',
		)
	);
}

function flxq_client_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-fA-F:.]/', '', (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
}

function flxq_fail_key() {
	return 'flxq_fail_' . md5( flxq_client_ip() );
}

/** Anahtarı doğrular; menü kimliğini isteğe yazar. */
function flxq_feed_permission( WP_REST_Request $req ) {
	$fails = (int) get_transient( flxq_fail_key() );
	if ( $fails >= FLXQ_FAIL_LIMIT ) {
		return new WP_Error( 'too_many_attempts', __( 'Çok fazla hatalı anahtar denemesi. Biraz sonra tekrar deneyin.', 'flx-qr-menu' ), array( 'status' => 429 ) );
	}
	$id = flxq_find_menu_by_key( trim( (string) $req->get_header( 'x_flxq_key' ) ) );
	if ( ! $id ) {
		set_transient( flxq_fail_key(), $fails + 1, FLXQ_FAIL_WINDOW );
		return new WP_Error( 'invalid_key', __( 'Bağlantı anahtarı geçersiz.', 'flx-qr-menu' ), array( 'status' => 401 ) );
	}
	$req->set_param( '_flxq_menu', $id );
	return true;
}

function flxq_etag_matches( $header, $etag ) {
	if ( '' === (string) $header ) {
		return false;
	}
	foreach ( explode( ',', (string) $header ) as $t ) {
		$t = trim( $t );
		if ( '*' === $t || preg_replace( '#^W/#', '', $t ) === $etag ) {
			return true;
		}
	}
	return false;
}

function flxq_feed( WP_REST_Request $req ) {
	$id = (int) $req->get_param( '_flxq_menu' );
	if ( ! flxq_menu_enabled( $id ) ) {
		return new WP_Error( 'menu_disabled', __( 'Menü yayından kaldırılmış.', 'flx-qr-menu' ), array( 'status' => 423 ) );
	}
	flxq_register_site(
		$id,
		(string) $req->get_header( 'x_flxq_site' ),
		(string) $req->get_header( 'x_flxq_hook' ),
		(string) $req->get_header( 'x_flxq_client' ),
		flxq_client_ip()
	);
	$version = flxq_menu_version( $id );
	$etag    = '"flxq-' . $id . '-' . $version . '"';
	if ( flxq_etag_matches( $req->get_header( 'if_none_match' ), $etag ) ) {
		$res = new WP_REST_Response( null, 304 );
		$res->header( 'ETag', $etag );
		return $res;
	}
	$updated = (int) get_post_meta( $id, '_flxq_updated', true );
	$res     = new WP_REST_Response(
		array(
			'ok'   => true,
			'menu' => array(
				'id'      => $id,
				'name'    => get_the_title( $id ),
				'version' => $version,
				'updated' => $updated ? wp_date( 'c', $updated ) : '',
			),
			'data' => flxq_menu_data( $id ),
		),
		200
	);
	$res->header( 'ETag', $etag );
	return $res;
}

function flxq_is_feed_request( $req ) {
	return $req instanceof WP_REST_Request && '/' . FLXQ_NS . '/feed' === untrailingslashit( (string) $req->get_route() );
}

/** Hata gövdelerini sözleşme biçimine çevirir: {"ok":false,"code":"invalid_key"}. */
function flxq_feed_shape_response( $res, $server, $req ) {
	if ( ! flxq_is_feed_request( $req ) || ! $res instanceof WP_REST_Response ) {
		return $res;
	}
	$status = $res->get_status();
	if ( $status >= 400 ) {
		$d    = $res->get_data();
		$code = is_array( $d ) && isset( $d['code'] ) ? (string) $d['code'] : 'error';
		$res->set_data( array( 'ok' => false, 'code' => $code ) );
	}
	flxq_nocache_headers( $res, 'flx-qr-menu feed' );
	return $res;
}

/** Feed ve /rev 304'leri gövdesiz gönderilir (WordPress aksi halde "null" yazar). Başlıklar bu noktada gönderilmiştir. */
function flxq_feed_serve_304( $served, $res, $req, $server ) {
	if ( ! $served && ( flxq_is_feed_request( $req ) || flxq_is_rev_request( $req ) ) && $res instanceof WP_REST_Response && 304 === $res->get_status() ) {
		return true;
	}
	return $served;
}

/** LiteSpeed / CDN / tarayıcı önbelleğini kapatır. */
function flxq_nocache_headers( WP_REST_Response $res, $why ) {
	$res->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private' );
	$res->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
	$res->header( 'CDN-Cache-Control', 'no-store' );
	$res->header( 'Pragma', 'no-cache' );
	do_action( 'litespeed_control_set_nocache', $why );
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
}
