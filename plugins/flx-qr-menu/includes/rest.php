<?php
/**
 * Yönetim REST uçları (flxq_edit_menus yetkisi + wp_rest nonce; menü yöneticisi yalnız kendi menülerine, diğerleri 403).
 * Anahtar, bağlı siteler ve düzenleyenler yalnız tüm menüleri yönetene (flxq_manage_menus) açık.
 * Yazma POST ile yapılır (bazı sunucular PATCH/DELETE'i engelliyor).
 *
 * @package FLX_QR_Menu
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'flxq_register_admin_routes' );
add_filter( 'rest_post_dispatch', 'flxq_admin_nocache', 10, 3 );

function flxq_rest_can() {
	return current_user_can( FLXQ_EDIT_CAP );
}

function flxq_forbidden() {
	return new WP_Error( 'flxq_forbidden', __( 'Bu menüye erişim yetkiniz yok.', 'flx-qr-menu' ), array( 'status' => 403 ) );
}

function flxq_admin_nocache( $res, $server, $req ) {
	if ( $res instanceof WP_REST_Response && 0 === strpos( (string) $req->get_route(), '/' . FLXQ_NS . '/menus' ) ) {
		flxq_nocache_headers( $res, 'flx-qr-menu admin' );
	}
	return $res;
}

function flxq_register_admin_routes() {
	$routes = array(
		array( '/menus/(?P<id>\d+)', 'GET', 'flxq_rest_get' ),
		array( '/menus/(?P<id>\d+)', 'POST', 'flxq_rest_save' ),
		array( '/menus/(?P<id>\d+)/key', 'POST', 'flxq_rest_key' ),
		array( '/menus/(?P<id>\d+)/toggle', 'POST', 'flxq_rest_toggle' ),
		array( '/menus/(?P<id>\d+)/sites/remove', 'POST', 'flxq_rest_site_remove' ),
		array( '/menus/(?P<id>\d+)/history', 'GET', 'flxq_rest_history' ),
		array( '/menus/(?P<id>\d+)/history/(?P<hid>\d+)', 'GET', 'flxq_rest_history_get' ),
		array( '/menus/(?P<id>\d+)/history/(?P<hid>\d+)/restore', 'POST', 'flxq_rest_restore' ),
		array( '/menus/(?P<id>\d+)/address', 'POST', 'flxq_rest_address' ),
		array( '/menus/(?P<id>\d+)/editors', 'POST', 'flxq_rest_editors' ),
		array( '/menus/(?P<id>\d+)/import-images', 'POST', 'flxq_rest_import_images' ),
		array( '/menus/(?P<id>\d+)/preview', 'POST', 'flxq_rest_preview' ),
	);
	foreach ( $routes as $r ) {
		register_rest_route(
			FLXQ_NS,
			$r[0],
			array(
				'methods'             => $r[1],
				'callback'            => $r[2],
				'permission_callback' => 'flxq_rest_can',
			)
		);
	}
}

function flxq_rest_menu_or_404( WP_REST_Request $req ) {
	$post = flxq_get_post( (int) $req['id'] );
	if ( ! $post || 'trash' === $post->post_status ) {
		return new WP_Error( 'flxq_not_found', __( 'Menü bulunamadı.', 'flx-qr-menu' ), array( 'status' => 404 ) );
	}
	if ( ! flxq_can_edit_menu( $post->ID ) ) {
		return flxq_forbidden();
	}
	return $post;
}

/** Yalnız tüm menüleri yönetenin yapabileceği işlemler. */
function flxq_rest_admin_menu( WP_REST_Request $req ) {
	$post = flxq_rest_menu_or_404( $req );
	if ( ! is_wp_error( $post ) && ! current_user_can( FLXQ_CAP ) ) {
		return flxq_forbidden();
	}
	return $post;
}

/** Düzenleme ekranının ihtiyaç duyduğu her şey (anahtar ve sır hariç). */
function flxq_editor_payload( $id ) {
	$info = flxq_menu_info( $id );
	$out  = array();
	foreach ( $info['sites'] as $k => $s ) {
		$out[] = array(
			'id'     => $k,
			'url'    => $s['url'],
			'hook'   => '' !== (string) $s['hook'],
			'client' => $s['client'],
			'ip'     => $s['ip'],
			'first'  => (int) $s['first'],
			'last'   => (int) $s['last'],
			'ago'    => human_time_diff( (int) $s['last'] ),
		);
	}
	usort(
		$out,
		function ( $a, $b ) {
			return $b['last'] - $a['last'];
		}
	);
	$admin             = current_user_can( FLXQ_CAP );
	$info['sites']     = $admin ? $out : array();
	$info['data_json'] = flxq_encode( flxq_menu_data( $id ) );
	$info['base']      = flxq_preview_base( $id );
	$info['images']    = count( flxq_relative_images( flxq_menu_data( $id ) ) );
	$info['bundled']   = array( 'base' => FLXQ_URL . FLXQ_SAMPLE_IMG, 'files' => flxq_bundled_images() );
	$info['editors']   = array_map(
		function ( $uid ) {
			return array( 'id' => $uid, 'name' => flxq_user_name( $uid ) );
		},
		$info['editors']
	);
	$info['can']       = array(
		'admin'   => $admin,
		'address' => flxq_can_set_address( $id ),
		'trash'   => flxq_can_trash( $id ),
		'upload'  => current_user_can( 'upload_files' ),
	);
	if ( ! $admin ) {
		$info['key'] = array( 'has' => $info['key']['has'], 'last4' => '', 'created' => 0 );
	}
	return $info;
}

function flxq_rest_get( WP_REST_Request $req ) {
	$post = flxq_rest_menu_or_404( $req );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	return flxq_editor_payload( $post->ID );
}

function flxq_rest_save( WP_REST_Request $req ) {
	$post = flxq_rest_menu_or_404( $req );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	$json = (string) $req->get_param( 'data_json' );
	$data = json_decode( $json );
	if ( null === $data || JSON_ERROR_NONE !== json_last_error() ) {
		return new WP_Error( 'flxq_invalid', __( 'Menü verisi geçerli JSON değil.', 'flx-qr-menu' ), array( 'status' => 400 ) );
	}
	$base = $req->get_param( 'base_version' );
	$res  = flxq_save_menu(
		$post->ID,
		(string) $req->get_param( 'name' ),
		$data,
		array(
			'action'       => 'import' === $req->get_param( 'source' ) ? 'import' : 'save',
			'base_version' => null === $base ? null : (int) $base,
			'force'        => (bool) $req->get_param( 'force' ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return array_merge( $res, array( 'menu' => flxq_editor_payload( $post->ID ) ) );
}

function flxq_rest_key( WP_REST_Request $req ) {
	$post = flxq_rest_admin_menu( $req );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	$key = flxq_generate_key( $post->ID );
	return array(
		'key'  => $key,
		'menu' => flxq_editor_payload( $post->ID ),
	);
}

function flxq_rest_toggle( WP_REST_Request $req ) {
	$post = flxq_rest_menu_or_404( $req );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	flxq_set_enabled( $post->ID, (bool) $req->get_param( 'enabled' ) );
	return array( 'menu' => flxq_editor_payload( $post->ID ) );
}

function flxq_rest_site_remove( WP_REST_Request $req ) {
	$post = flxq_rest_admin_menu( $req );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	flxq_remove_site( $post->ID, (string) $req->get_param( 'site' ) );
	return array( 'menu' => flxq_editor_payload( $post->ID ) );
}

function flxq_rest_history( WP_REST_Request $req ) {
	$post = flxq_rest_menu_or_404( $req );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	$per  = 20;
	$page = max( 1, (int) $req->get_param( 'page' ) );
	$list = flxq_history_list( $post->ID, $page, $per );
	return array(
		'rows'  => $list['rows'],
		'total' => $list['total'],
		'page'  => $page,
		'pages' => max( 1, (int) ceil( $list['total'] / $per ) ),
	);
}

function flxq_rest_history_get( WP_REST_Request $req ) {
	$post = flxq_rest_menu_or_404( $req );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	$row = flxq_history_get( $post->ID, (int) $req['hid'] );
	if ( ! $row ) {
		return new WP_Error( 'flxq_not_found', __( 'Kayıt bulunamadı.', 'flx-qr-menu' ), array( 'status' => 404 ) );
	}
	$out              = flxq_history_row_out( $row + array( 'has_data' => null !== $row['data'] ) );
	$out['data_json'] = null === $row['data'] ? null : (string) $row['data'];
	return $out;
}

function flxq_rest_restore( WP_REST_Request $req ) {
	$post = flxq_rest_menu_or_404( $req );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	$base = $req->get_param( 'base_version' );
	$res  = flxq_restore( $post->ID, (int) $req['hid'], null === $base ? null : (int) $base );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return array_merge( $res, array( 'menu' => flxq_editor_payload( $post->ID ) ) );
}

function flxq_rest_address( WP_REST_Request $req ) {
	$post = flxq_rest_menu_or_404( $req );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	if ( ! flxq_can_set_address( $post->ID ) ) {
		return new WP_Error( 'flxq_forbidden', __( 'Adres değiştirme yetkiniz yok.', 'flx-qr-menu' ), array( 'status' => 403 ) );
	}
	$res = flxq_set_address( $post->ID, (string) $req->get_param( 'slug' ), (string) $req->get_param( 'host' ) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return array_merge( $res, array( 'menu' => flxq_editor_payload( $post->ID ) ) );
}

function flxq_rest_editors( WP_REST_Request $req ) {
	$post = flxq_rest_admin_menu( $req );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	$res = flxq_set_editors( $post->ID, array_map( 'intval', (array) $req->get_param( 'users' ) ) );
	return array_merge( $res, array( 'menu' => flxq_editor_payload( $post->ID ) ) );
}

function flxq_rest_import_images( WP_REST_Request $req ) {
	$post = flxq_rest_menu_or_404( $req );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	if ( ! current_user_can( 'upload_files' ) ) {
		return new WP_Error( 'flxq_forbidden', __( 'Dosya yükleme yetkiniz yok.', 'flx-qr-menu' ), array( 'status' => 403 ) );
	}
	$base = $req->get_param( 'base_version' );
	$skip = array_map( 'strval', (array) $req->get_param( 'skip' ) );
	$res  = flxq_import_images( $post->ID, null === $base ? null : (int) $base, $skip );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return array_merge( $res, array( 'menu' => flxq_editor_payload( $post->ID ) ) );
}

/**
 * Canlı önizleme: kaydedilmemiş menü verisinden (data_json) yayındaki sayfayla aynı şablonla HTML üretir.
 * Hiçbir şey kaydetmez; veri kayıttaki gibi doğrulanır (flxq_normalize).
 */
function flxq_rest_preview( WP_REST_Request $req ) {
	$post = flxq_rest_menu_or_404( $req );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	$json = $req->get_param( 'data_json' );
	$data = null === $json ? flxq_menu_data( $post->ID ) : json_decode( (string) $json );
	if ( null === $data || JSON_ERROR_NONE !== json_last_error() ) {
		return new WP_Error( 'flxq_invalid', __( 'Menü verisi geçerli JSON değil.', 'flx-qr-menu' ), array( 'status' => 400 ) );
	}
	$data = flxq_normalize( $data );
	if ( is_wp_error( $data ) ) {
		$data->add_data( array( 'status' => 400 ) );
		return $data;
	}
	$urls = flxq_menu_urls( $post->ID );
	$base = $urls ? $urls[0]['url'] : home_url( '/' );
	$html = flxq_render_menu_html( flxq_public_data_from( json_decode( flxq_encode( $data ), true ) ), $base, array( 'preview' => true ) );
	return array( 'html' => $html );
}
