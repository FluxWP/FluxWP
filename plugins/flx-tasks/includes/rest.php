<?php
/**
 * REST API: /wp-json/flx-tasks/v1/...
 * Arayüz güncellemeleri POST, silmeyi POST + X-HTTP-Method-Override: DELETE ile gönderir (bazı sunucu güvenlik duvarları PATCH/DELETE'i engelliyor).
 */

defined( 'ABSPATH' ) || exit;

const FLXG_NS = 'flx-tasks/v1';

function flxg_rest_can_use() {
	return is_user_logged_in() && current_user_can( 'flxg_use' );
}

function flxg_bump_structure() {
	update_option( 'flxg_struct_rev', (int) get_option( 'flxg_struct_rev', 0 ) + 1, true );
}

function flxg_route( $path, $methods, $cb, $perm = 'flxg_rest_can_use' ) {
	register_rest_route(
		FLXG_NS,
		$path,
		array(
			'methods'             => $methods,
			'callback'            => $cb,
			'permission_callback' => $perm,
		)
	);
}

add_action( 'rest_api_init', 'flxg_register_routes' );
add_filter( 'rest_post_dispatch', 'flxg_rest_nocache', 10, 3 );

/**
 * Görev API yanıtları hiçbir katmanda (LiteSpeed, CDN, tarayıcı) önbelleğe alınmaz.
 * Yoksa yeni eklenen yorum/alt görev bir süre görünmeyebilir.
 */
function flxg_rest_nocache( $res, $server, $req ) {
	if ( 0 !== strpos( (string) $req->get_route(), '/flx-tasks/v1' ) || ! $res instanceof WP_REST_Response ) {
		return $res;
	}
	if ( false !== strpos( (string) $req->get_route(), '/files/' ) && 'GET' === $req->get_method() ) {
		return $res;
	}
	$res->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private' );
	$res->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
	$res->header( 'CDN-Cache-Control', 'no-store' );
	$res->header( 'Pragma', 'no-cache' );
	do_action( 'litespeed_control_set_nocache', 'flx-tasks api' );
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	return $res;
}
function flxg_register_routes() {
	flxg_route( '/boot', 'GET', 'flxg_rest_boot' );
	flxg_route( '/me', 'POST', 'flxg_rest_me' );

	flxg_route( '/spaces', 'POST', 'flxg_rest_space_create' );
	flxg_route( '/spaces/(?P<id>\d+)', 'GET', 'flxg_rest_space_get' );
	flxg_route( '/spaces/(?P<id>\d+)', 'POST, PUT, PATCH', 'flxg_rest_space_update' );
	flxg_route( '/spaces/(?P<id>\d+)', 'DELETE', 'flxg_rest_space_delete' );
	flxg_route( '/spaces/(?P<id>\d+)/members', 'GET', 'flxg_rest_space_members' );
	flxg_route( '/spaces/(?P<id>\d+)/members', 'POST', 'flxg_rest_space_members_save' );

	flxg_route( '/folders', 'POST', 'flxg_rest_folder_create' );
	flxg_route( '/folders/(?P<id>\d+)', 'POST, PUT, PATCH', 'flxg_rest_folder_update' );
	flxg_route( '/folders/(?P<id>\d+)', 'DELETE', 'flxg_rest_folder_delete' );

	flxg_route( '/lists', 'POST', 'flxg_rest_list_create' );
	flxg_route( '/lists/(?P<id>\d+)', 'GET', 'flxg_rest_list_get' );
	flxg_route( '/lists/(?P<id>\d+)', 'POST, PUT, PATCH', 'flxg_rest_list_update' );
	flxg_route( '/lists/(?P<id>\d+)', 'DELETE', 'flxg_rest_list_delete' );
	flxg_route( '/lists/(?P<id>\d+)/move-check', 'GET', 'flxg_rest_list_move_check' );
	flxg_route( '/lists/(?P<id>\d+)/move', 'POST', 'flxg_rest_list_move' );
	flxg_route( '/lists/(?P<id>\d+)/members', 'GET', 'flxg_rest_list_members' );
	flxg_route( '/lists/(?P<id>\d+)/members', 'POST', 'flxg_rest_list_members_save' );
	flxg_route( '/lists/(?P<id>\d+)/fields', 'POST', 'flxg_rest_field_create' );
	flxg_route( '/fields/(?P<id>\d+)', 'POST, PUT, PATCH', 'flxg_rest_field_update' );
	flxg_route( '/fields/(?P<id>\d+)', 'DELETE', 'flxg_rest_field_delete' );

	flxg_route( '/home', 'GET', 'flxg_rest_home' );
	flxg_route( '/views', 'POST', 'flxg_rest_view_create' );
	flxg_route( '/views/(?P<id>\d+)', 'POST, PUT, PATCH', 'flxg_rest_view_update' );
	flxg_route( '/views/(?P<id>\d+)', 'DELETE', 'flxg_rest_view_delete' );

	flxg_route( '/tags', 'POST', 'flxg_rest_tag_create' );
	flxg_route( '/tags/(?P<id>\d+)', 'POST, PUT, PATCH', 'flxg_rest_tag_update' );
	flxg_route( '/tags/(?P<id>\d+)', 'DELETE', 'flxg_rest_tag_delete' );

	flxg_route( '/tasks', 'GET', 'flxg_rest_tasks' );
	flxg_route( '/tasks', 'POST', 'flxg_rest_task_create' );
	flxg_route( '/tasks/bulk', 'POST', 'flxg_rest_task_bulk' );
	flxg_route( '/tasks/(?P<id>\d+)', 'GET', 'flxg_rest_task_get' );
	flxg_route( '/tasks/(?P<id>\d+)', 'POST, PUT, PATCH', 'flxg_rest_task_update' );
	flxg_route( '/tasks/(?P<id>\d+)', 'DELETE', 'flxg_rest_task_delete' );
	flxg_route( '/tasks/(?P<id>\d+)/watch', 'POST', 'flxg_rest_task_watch' );
	flxg_route( '/tasks/(?P<id>\d+)/comments', 'POST', 'flxg_rest_comment_create' );
	flxg_route( '/comments/(?P<id>\d+)', 'POST, PUT, PATCH', 'flxg_rest_comment_update' );
	flxg_route( '/comments/(?P<id>\d+)', 'DELETE', 'flxg_rest_comment_delete' );

	flxg_route( '/files', 'POST', 'flxg_rest_file_upload' );
	flxg_route( '/files/(?P<id>\d+)', 'GET', 'flxg_rest_file_get' );
	flxg_route( '/files/(?P<id>\d+)', 'DELETE', 'flxg_rest_file_delete' );

	flxg_route( '/notifications', 'GET', 'flxg_rest_notifications' );
	flxg_route( '/notifications/read', 'POST', 'flxg_rest_notifications_read' );
	flxg_route( '/sync', 'GET', 'flxg_rest_sync' );

	$manage = function () {
		return current_user_can( 'flxg_manage' );
	};
	flxg_route( '/dashboard', 'GET', 'flxg_rest_dashboard', $manage );
	flxg_route( '/dashboard/scan', 'POST', 'flxg_rest_dashboard_scan', $manage );
	flxg_route( '/settings', 'GET', 'flxg_rest_settings_get', $manage );
	flxg_route( '/settings', 'POST', 'flxg_rest_settings_save', $manage );

	$ai = function () {
		return current_user_can( 'flxg_use' ) && current_user_can( 'flxg_ai' );
	};
	flxg_route( '/ai/chat', 'POST', 'flxg_rest_ai_chat', $ai );
	flxg_route( '/ai/task/(?P<id>\d+)', 'POST', 'flxg_rest_ai_task', $ai );
	flxg_route( '/doc-pages/(?P<id>\d+)/ai', 'POST', 'flxg_rest_doc_ai', $ai );

	flxg_route( '/mcp-keys', 'GET', 'flxg_rest_mcp_keys' );
	flxg_route( '/mcp-keys', 'POST', 'flxg_rest_mcp_key_create' );
	flxg_route( '/mcp-keys/(?P<id>[A-Za-z0-9]+)', 'DELETE', 'flxg_rest_mcp_key_delete' );
}

function flxg_body( WP_REST_Request $r ) {
	$j = $r->get_json_params();
	return is_array( $j ) ? $j : $r->get_body_params();
}

/* =========================================================================
 * Açılış verisi
 * ====================================================================== */

function flxg_structure( $uid ) {
	$spaces  = array();
	$lists   = array();
	$folders = array();
	$vis_sp  = array();
	foreach ( flxg_all_lists() as $l ) {
		$role = flxg_list_role( $l, $uid );
		if ( ! $role ) {
			continue;
		}
		$l['role'] = $role;
		unset( $l['description'] );
		$lists[]                        = $l;
		$vis_sp[ (int) $l['space_id'] ] = true;
	}
	foreach ( flxg_all_spaces() as $s ) {
		$role = flxg_space_role( $s, $uid );
		if ( ! $role && empty( $vis_sp[ $s['id'] ] ) ) {
			continue;
		}
		$s['role'] = $role ? $role : 'view';
		$spaces[]  = $s;
	}
	$sp_ids = wp_list_pluck( $spaces, 'id' );
	foreach ( flxg_all_folders() as $f ) {
		if ( in_array( $f['space_id'], $sp_ids, true ) ) {
			$folders[] = $f;
		}
	}
	$tags = array_values(
		array_filter(
			flxg_all_tags(),
			function ( $t ) use ( $sp_ids ) {
				return in_array( $t['space_id'], $sp_ids, true );
			}
		)
	);
	$docs = array_values(
		array_filter(
			flxg_visible_docs( $uid ),
			function ( $d ) use ( $sp_ids ) {
				return in_array( $d['space_id'], $sp_ids, true );
			}
		)
	);
	return array(
		'spaces'  => $spaces,
		'folders' => $folders,
		'lists'   => $lists,
		'docs'    => $docs,
		'tags'    => $tags,
		'rev'     => (int) get_option( 'flxg_struct_rev', 0 ),
	);
}

function flxg_rest_boot() {
	$uid = get_current_user_id();
	$pr  = array();
	foreach ( flxg_priorities() as $k => $p ) {
		$pr[] = array( 'id' => $k ) + $p;
	}
	return array_merge(
		flxg_structure( $uid ),
		array(
			'me'       => array_merge(
				flxg_user_public( $uid ),
				array(
					'can'   => array(
						'create' => current_user_can( 'flxg_create' ),
						'manage' => current_user_can( 'flxg_manage' ),
						'ai'     => current_user_can( 'flxg_ai' ) && flxg_ai_ready(),
					),
					'email' => '0' !== (string) get_user_meta( $uid, 'flxg_email', true ),
					'prefs' => (object) flxg_user_prefs( $uid ),
				)
			),
			'users'    => array_values( flxg_users() ),
			'unread'   => flxg_unread_count( $uid ),
			'time'     => flxg_now(),
			'config'   => array(
				'poll'        => max( 15, (int) flxg_setting( 'poll_interval' ) ),
				'maxUpload'   => flxg_max_upload_bytes(),
				'emailOn'     => (bool) flxg_setting( 'email_notify' ),
				'mcp'         => (bool) flxg_setting( 'mcp_enabled' ),
				'mcpUrl'      => flxg_mcp_url(),
				'fieldTypes'  => flxg_field_types(),
				'priorities'  => $pr,
				'roles'       => flxg_roles(),
				'today'       => flxg_today(),
				'mailDraft'   => flxg_can_mail_draft(),
				'weekStart'   => (int) get_option( 'start_of_week', 1 ),
			),
		)
	);
}

/** Kullanıcının ekran tercihleri (sütunlar, genişlikler, katlanan bölümler): cihazlar arasında aynı kalır. */
function flxg_user_prefs( $uid ) {
	$v = json_decode( (string) get_user_meta( $uid, 'flxg_prefs', true ), true );
	return is_array( $v ) ? $v : array();
}

function flxg_rest_me( WP_REST_Request $r ) {
	$b = flxg_body( $r );
	if ( isset( $b['email'] ) ) {
		update_user_meta( get_current_user_id(), 'flxg_email', $b['email'] ? '1' : '0' );
	}
	if ( isset( $b['prefs'] ) && is_array( $b['prefs'] ) ) {
		$prefs = flxg_user_prefs( get_current_user_id() );
		foreach ( $b['prefs'] as $k => $v ) {
			if ( ! preg_match( '/^[a-z0-9-]{1,40}$/', (string) $k ) ) {
				continue;
			}
			if ( null === $v ) {
				unset( $prefs[ $k ] );
			} else {
				$prefs[ $k ] = $v;
			}
		}
		$json = wp_json_encode( $prefs );
		// Ekran tercihleri küçüktür; sınırı aşan yazma reddedilir.
		if ( strlen( (string) $json ) > 30000 ) {
			return flxg_error( 'flxg_prefs', __( 'Ekran tercihleri çok büyük.', 'flx-tasks' ) );
		}
		update_user_meta( get_current_user_id(), 'flxg_prefs', $json );
	}
	return array( 'ok' => true );
}

/* =========================================================================
 * Projeler
 * ====================================================================== */

function flxg_rest_space_create( WP_REST_Request $r ) {
	if ( ! current_user_can( 'flxg_create' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Proje oluşturma yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	$b = flxg_body( $r );
	if ( '' === trim( (string) ( $b['name'] ?? '' ) ) ) {
		return flxg_error( 'flxg_name', __( 'Ad boş olamaz.', 'flx-tasks' ) );
	}
	$id = flxg_create_space( $b, get_current_user_id() );
	if ( ! empty( $b['list'] ) ) {
		flxg_create_list_row( array( 'space_id' => $id, 'name' => (string) $b['list'], 'owner_id' => get_current_user_id() ) );
	}
	flxg_bump_structure();
	return array( 'id' => $id ) + flxg_structure( get_current_user_id() );
}

function flxg_rest_space_get( WP_REST_Request $r ) {
	$id    = (int) $r['id'];
	$space = flxg_get_space( $id );
	$lists = flxg_visible_list_ids( $id );
	if ( ! $space || ( ! flxg_can_space( $space ) && ! $lists ) ) {
		return flxg_error( 'flxg_404', __( 'Proje bulunamadı.', 'flx-tasks' ), 404 );
	}
	return array(
		'space'  => $space,
		'role'   => flxg_space_role( $space ),
		'fields' => flxg_get_fields( $lists ),
		'views'  => flxg_get_views( 'space', $id, get_current_user_id() ),
	);
}

function flxg_rest_space_update( WP_REST_Request $r ) {
	$id = (int) $r['id'];
	if ( ! flxg_can_space( $id, 'admin' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu projeyi yönetme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	flxg_update_space( $id, flxg_body( $r ) );
	flxg_bump_structure();
	return flxg_structure( get_current_user_id() );
}

function flxg_rest_space_delete( WP_REST_Request $r ) {
	$id = (int) $r['id'];
	if ( ! flxg_can_space( $id, 'admin' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu projeyi yönetme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	flxg_delete_space( $id );
	flxg_bump_structure();
	return flxg_structure( get_current_user_id() );
}

function flxg_rest_space_members( WP_REST_Request $r ) {
	$id = (int) $r['id'];
	if ( ! flxg_can_space( $id, 'admin' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu projeyi yönetme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	return array( 'members' => flxg_get_members( 'space', $id ), 'space' => flxg_get_space( $id ) );
}

function flxg_rest_space_members_save( WP_REST_Request $r ) {
	$id = (int) $r['id'];
	if ( ! flxg_can_space( $id, 'admin' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu projeyi yönetme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	$b = flxg_body( $r );
	flxg_set_members( 'space', $id, (array) ( $b['members'] ?? array() ) );
	flxg_update_space( $id, array_intersect_key( $b, array_flip( array( 'access', 'default_role' ) ) ) );
	flxg_bump_structure();
	return array( 'members' => flxg_get_members( 'space', $id ) ) + flxg_structure( get_current_user_id() );
}

/* =========================================================================
 * Klasörler
 * ====================================================================== */

function flxg_rest_folder_create( WP_REST_Request $r ) {
	$b = flxg_body( $r );
	if ( ! current_user_can( 'flxg_create' ) || ! flxg_can_space( (int) ( $b['space'] ?? 0 ), 'edit' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu projede klasör oluşturma yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	if ( '' === trim( (string) ( $b['name'] ?? '' ) ) ) {
		return flxg_error( 'flxg_name', __( 'Ad boş olamaz.', 'flx-tasks' ) );
	}
	$id = flxg_create_folder( array( 'space_id' => (int) $b['space'], 'name' => $b['name'] ) );
	flxg_bump_structure();
	return array( 'id' => $id ) + flxg_structure( get_current_user_id() );
}

function flxg_rest_folder_update( WP_REST_Request $r ) {
	$f = flxg_get_folder( (int) $r['id'] );
	if ( ! $f || ! flxg_can_space( $f['space_id'], 'edit' ) || ! current_user_can( 'flxg_create' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu klasörü düzenleme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	flxg_update_folder( $f['id'], flxg_body( $r ) );
	flxg_bump_structure();
	return flxg_structure( get_current_user_id() );
}

function flxg_rest_folder_delete( WP_REST_Request $r ) {
	$f = flxg_get_folder( (int) $r['id'] );
	if ( ! $f || ! flxg_can_space( $f['space_id'], 'admin' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu klasörü silme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	flxg_delete_folder( $f['id'] );
	flxg_bump_structure();
	return flxg_structure( get_current_user_id() );
}

/* =========================================================================
 * Listeler
 * ====================================================================== */

function flxg_rest_list_create( WP_REST_Request $r ) {
	$b     = flxg_body( $r );
	$space = (int) ( $b['space'] ?? 0 );
	if ( ! current_user_can( 'flxg_create' ) || ! flxg_can_space( $space, 'edit' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu projede liste oluşturma yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	if ( '' === trim( (string) ( $b['name'] ?? '' ) ) ) {
		return flxg_error( 'flxg_name', __( 'Ad boş olamaz.', 'flx-tasks' ) );
	}
	$folder = (int) ( $b['folder'] ?? 0 );
	$f      = $folder ? flxg_get_folder( $folder ) : null;
	$id     = flxg_create_list_row(
		array(
			'space_id'  => $space,
			'folder_id' => $f && (int) $f['space_id'] === $space ? $folder : 0,
			'name'      => $b['name'],
			'color'     => $b['color'] ?? '',
			'access'    => $b['access'] ?? 'inherit',
			'owner_id'  => get_current_user_id(),
			'kind'      => $b['kind'] ?? 'task',
		)
	);
	if ( 'members' === ( $b['access'] ?? '' ) ) {
		flxg_set_members( 'list', $id, array( array( 'user' => get_current_user_id(), 'role' => 'admin' ) ) );
	}
	flxg_bump_structure();
	return array( 'id' => $id ) + flxg_structure( get_current_user_id() );
}

function flxg_rest_list_get( WP_REST_Request $r ) {
	$list = flxg_get_list( (int) $r['id'] );
	if ( ! $list || ! flxg_can_list( $list ) ) {
		return flxg_error( 'flxg_404', __( 'Liste bulunamadı ya da erişiminiz yok.', 'flx-tasks' ), 404 );
	}
	return array(
		'list'   => $list,
		'role'   => flxg_list_role( $list ),
		'fields' => flxg_get_fields( $list['id'] ),
		'views'  => flxg_get_views( 'list', $list['id'], get_current_user_id() ),
	);
}

function flxg_rest_list_update( WP_REST_Request $r ) {
	$list = flxg_get_list( (int) $r['id'] );
	if ( ! $list || ! flxg_can_list( $list, 'admin' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu listeyi yönetme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	$b = flxg_body( $r );
	if ( isset( $b['space_id'] ) && (int) $b['space_id'] !== $list['space_id'] && ! flxg_can_space( (int) $b['space_id'], 'edit' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Hedef projeye erişiminiz yok.', 'flx-tasks' ), 403 );
	}
	$list = flxg_update_list( $list['id'], $b );
	flxg_bump_structure();
	return array( 'list' => $list ) + flxg_structure( get_current_user_id() );
}

function flxg_rest_list_delete( WP_REST_Request $r ) {
	$list = flxg_get_list( (int) $r['id'] );
	if ( ! $list || ! flxg_can_list( $list, 'admin' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu listeyi yönetme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	flxg_delete_list( $list['id'] );
	flxg_bump_structure();
	return flxg_structure( get_current_user_id() );
}

/** Listeyi başka projeye taşımadan önce: etiketler, erişimi kalkacak kişiler, hedefteki durumlar. */
function flxg_rest_list_move_check( WP_REST_Request $r ) {
	global $wpdb;
	$list  = flxg_get_list( (int) $r['id'] );
	$space = flxg_get_space( (int) $r['space'] );
	if ( ! $list || ! flxg_can_list( $list, 'admin' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu listeyi yönetme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	if ( ! $space || ! flxg_can_space( $space, 'edit' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Hedef projeye erişiminiz yok.', 'flx-tasks' ), 403 );
	}
	$tt   = flxg_table( 'tasks' );
	$tags = array();
	if ( (int) $space['id'] !== (int) $list['space_id'] ) {
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT g.id, g.name, g.color, COUNT(*) AS n FROM ' . flxg_table( 'task_tags' ) . ' x JOIN ' . flxg_table( 'tags' ) . " g ON g.id = x.tag_id JOIN $tt t ON t.id = x.task_id WHERE t.list_id = %d GROUP BY g.id, g.name, g.color ORDER BY g.name", (int) $list['id'] ), ARRAY_A ); // phpcs:ignore
		foreach ( (array) $rows as $g ) {
			$match  = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . flxg_table( 'tags' ) . ' WHERE space_id = %d AND name = %s', (int) $space['id'], $g['name'] ) ); // phpcs:ignore
			$tags[] = array( 'id' => (int) $g['id'], 'name' => $g['name'], 'color' => $g['color'], 'count' => (int) $g['n'], 'exists' => (bool) $match );
		}
	}
	// Görevlerde adı geçen ya da listeye üye olup taşınınca listeyi göremeyecek kişiler.
	$uids  = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT a.user_id FROM ' . flxg_table( 'assignees' ) . " a JOIN $tt t ON t.id = a.task_id WHERE t.list_id = %d", (int) $list['id'] ) ) ); // phpcs:ignore
	foreach ( flxg_get_members( 'list', $list['id'] ) as $m ) {
		$uids[] = (int) $m['user'];
	}
	$moved  = array_merge( $list, array( 'space_id' => (int) $space['id'] ) );
	$people = array();
	foreach ( array_unique( $uids ) as $uid ) {
		if ( $uid && flxg_list_role( $list, $uid ) && ! flxg_list_role( $moved, $uid ) ) {
			$u        = get_userdata( $uid );
			$people[] = array( 'id' => $uid, 'name' => $u ? $u->display_name : '#' . $uid );
		}
	}
	return array( 'tags' => $tags, 'people' => $people );
}

/**
 * Listeyi başka projeye/klasöre taşır. İsteğe bağlı: durumları hedefteki bir listenin durumlarıyla eşler (map: kaynak → hedef anahtar),
 * etiketleri hedef projede aynı adla bağlar (yoksa oluşturur), erişimi kalkacak kişileri hedef projeye üye ekler.
 */
function flxg_rest_list_move( WP_REST_Request $r ) {
	global $wpdb;
	$list  = flxg_get_list( (int) $r['id'] );
	$b     = flxg_body( $r );
	$space = flxg_get_space( (int) ( $b['space'] ?? 0 ) );
	if ( ! $list || ! flxg_can_list( $list, 'admin' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu listeyi yönetme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	if ( ! $space || ! flxg_can_space( $space, 'edit' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Hedef projeye erişiminiz yok.', 'flx-tasks' ), 403 );
	}
	$tt      = flxg_table( 'tasks' );
	$lid     = (int) $list['id'];
	$from    = (int) $list['space_id'];
	$to      = (int) $space['id'];
	$tmpl    = ! empty( $b['statuses_from'] ) ? flxg_get_list( (int) $b['statuses_from'] ) : null;
	$changed = 0;
	if ( $tmpl && (int) $tmpl['id'] !== $lid ) {
		$keys = wp_list_pluck( $tmpl['statuses'], 'key' );
		$map  = (array) ( $b['status_map'] ?? array() );
		foreach ( $list['statuses'] as $st ) {
			$dst = sanitize_key( $map[ $st['key'] ] ?? '' );
			if ( ! in_array( $dst, $keys, true ) ) {
				$dst = $keys[0];
			}
			if ( $dst !== $st['key'] ) {
				$changed += (int) $wpdb->query( $wpdb->prepare( "UPDATE $tt SET status = %s WHERE list_id = %d AND status = %s", '__mv_' . $dst, $lid, $st['key'] ) ); // phpcs:ignore
			}
		}
		// İki adımda: önce geçici önek, sonra gerçek anahtar (a→b, b→a gibi takaslar karışmasın).
		foreach ( $keys as $k ) {
			$wpdb->query( $wpdb->prepare( "UPDATE $tt SET status = %s WHERE list_id = %d AND status = %s", $k, $lid, '__mv_' . $k ) ); // phpcs:ignore
		}
		$wpdb->update( flxg_table( 'lists' ), array( 'statuses' => flxg_enc( $tmpl['statuses'] ) ), array( 'id' => $lid ) );
	}
	$tags = 0;
	if ( $from !== $to ) {
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT DISTINCT g.id, g.name, g.color FROM ' . flxg_table( 'task_tags' ) . ' x JOIN ' . flxg_table( 'tags' ) . " g ON g.id = x.tag_id JOIN $tt t ON t.id = x.task_id WHERE t.list_id = %d AND g.space_id <> %d", $lid, $to ), ARRAY_A ); // phpcs:ignore
		foreach ( (array) $rows as $g ) {
			$nid = flxg_create_tag( $to, $g['name'], $g['color'] );
			if ( $nid ) {
				$tags += (int) $wpdb->query( $wpdb->prepare( 'UPDATE ' . flxg_table( 'task_tags' ) . " SET tag_id = %d WHERE tag_id = %d AND task_id IN (SELECT id FROM $tt WHERE list_id = %d)", $nid, (int) $g['id'], $lid ) ); // phpcs:ignore
			}
		}
		if ( ! empty( $b['add_members'] ) ) {
			foreach ( (array) $b['add_members'] as $uid ) {
				$uid = (int) $uid;
				if ( $uid && get_userdata( $uid ) && ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . flxg_table( 'members' ) . " WHERE object_type = 'space' AND object_id = %d AND user_id = %d", $to, $uid ) ) ) { // phpcs:ignore
					$wpdb->insert( flxg_table( 'members' ), array( 'object_type' => 'space', 'object_id' => $to, 'user_id' => $uid, 'role' => 'edit' ) );
				}
			}
		}
	}
	$pos = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(position) FROM ' . flxg_table( 'lists' ) . ' WHERE space_id = %d', $to ) ); // phpcs:ignore
	$upd = array( 'space_id' => $to, 'folder_id' => (int) ( $b['folder'] ?? 0 ) );
	if ( $from !== $to || (int) $list['folder_id'] !== $upd['folder_id'] ) {
		$upd['position'] = $pos + 1024;
	}
	flxg_all_lists( true );
	$list = flxg_update_list( $lid, $upd );
	flxg_bump_structure();
	return array( 'list' => $list, 'moved' => array( 'statuses' => $changed, 'tags' => $tags ) ) + flxg_structure( get_current_user_id() );
}

function flxg_rest_list_members( WP_REST_Request $r ) {
	$list = flxg_get_list( (int) $r['id'] );
	if ( ! $list || ! flxg_can_list( $list, 'admin' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu listeyi yönetme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	$space = flxg_get_space( $list['space_id'] );
	return array(
		'members'      => flxg_get_members( 'list', $list['id'] ),
		'access'       => $list['access'],
		'spaceAccess'  => $space['access'] ?? 'everyone',
		'spaceDefault' => $space['default_role'] ?? 'edit',
		'spaceMembers' => flxg_get_members( 'space', $list['space_id'] ),
	);
}

function flxg_rest_list_members_save( WP_REST_Request $r ) {
	$list = flxg_get_list( (int) $r['id'] );
	if ( ! $list || ! flxg_can_list( $list, 'admin' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu listeyi yönetme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	$b = flxg_body( $r );
	flxg_set_members( 'list', $list['id'], (array) ( $b['members'] ?? array() ) );
	if ( isset( $b['access'] ) ) {
		flxg_update_list( $list['id'], array( 'access' => $b['access'] ) );
	}
	flxg_bump_structure();
	return array( 'members' => flxg_get_members( 'list', $list['id'] ) ) + flxg_structure( get_current_user_id() );
}

function flxg_rest_field_create( WP_REST_Request $r ) {
	$list = flxg_get_list( (int) $r['id'] );
	if ( ! $list || ! flxg_can_list( $list, 'admin' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Alan eklemek için liste yöneticisi olmalısınız.', 'flx-tasks' ), 403 );
	}
	$b = flxg_body( $r );
	if ( '' === trim( (string) ( $b['name'] ?? '' ) ) ) {
		return flxg_error( 'flxg_name', __( 'Ad boş olamaz.', 'flx-tasks' ) );
	}
	$id = flxg_create_field( $list['id'], $b );
	flxg_bump_structure();
	return array( 'field' => flxg_get_field( $id ), 'fields' => flxg_get_fields( $list['id'] ) );
}

function flxg_rest_field_update( WP_REST_Request $r ) {
	$f = flxg_get_field( (int) $r['id'] );
	if ( ! $f || ! flxg_can_list( $f['list_id'], 'admin' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Alanı düzenlemek için liste yöneticisi olmalısınız.', 'flx-tasks' ), 403 );
	}
	flxg_update_field( $f['id'], flxg_body( $r ) );
	flxg_bump_structure();
	return array( 'field' => flxg_get_field( $f['id'] ), 'fields' => flxg_get_fields( $f['list_id'] ) );
}

function flxg_rest_field_delete( WP_REST_Request $r ) {
	$f = flxg_get_field( (int) $r['id'] );
	if ( ! $f || ! flxg_can_list( $f['list_id'], 'admin' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Alanı silmek için liste yöneticisi olmalısınız.', 'flx-tasks' ), 403 );
	}
	flxg_delete_field( $f['id'] );
	flxg_bump_structure();
	return array( 'fields' => flxg_get_fields( $f['list_id'] ) );
}

/* =========================================================================
 * Görünümler
 * ====================================================================== */

function flxg_rest_home() {
	$uid = get_current_user_id();
	return array(
		'views'  => flxg_get_views( 'home', $uid, $uid ),
		'fields' => array(),
	);
}

function flxg_view_scope_role( $scope, $scope_id ) {
	if ( 'list' === $scope ) {
		return flxg_list_role( (int) $scope_id );
	}
	if ( 'space' === $scope ) {
		$r = flxg_space_role( (int) $scope_id );
		return $r ? $r : ( flxg_visible_list_ids( (int) $scope_id ) ? 'view' : '' );
	}
	if ( 'home' === $scope ) {
		return (int) $scope_id === get_current_user_id() ? 'admin' : '';
	}
	return '';
}

function flxg_rest_view_create( WP_REST_Request $r ) {
	$b     = flxg_body( $r );
	$scope = (string) ( $b['scope'] ?? 'list' );
	$sid   = (int) ( $b['scope_id'] ?? 0 );
	$role  = flxg_view_scope_role( $scope, $sid );
	if ( ! $role ) {
		return flxg_error( 'flxg_forbidden', __( 'Erişiminiz yok.', 'flx-tasks' ), 403 );
	}
	// Paylaşılan görünüm için düzenleme yetkisi gerekir; yoksa kişisel olur.
	$shared = ! empty( $b['shared'] ) && flxg_role_rank( $role ) >= flxg_role_rank( 'edit' ) && 'home' !== $scope;
	$id     = flxg_create_view(
		array(
			'scope'    => $scope,
			'scope_id' => $sid,
			'user_id'  => $shared ? 0 : get_current_user_id(),
			'name'     => '' !== trim( (string) ( $b['name'] ?? '' ) ) ? $b['name'] : __( 'Yeni görünüm', 'flx-tasks' ),
			'type'     => $b['type'] ?? 'list',
			'config'   => $b['config'] ?? array(),
		)
	);
	return array( 'view' => flxg_get_view( $id ), 'views' => flxg_get_views( $scope, $sid, get_current_user_id() ) );
}

function flxg_can_edit_view( $v ) {
	if ( ! $v ) {
		return false;
	}
	if ( (int) $v['user_id'] === get_current_user_id() ) {
		return true;
	}
	if ( 0 !== (int) $v['user_id'] ) {
		return false;
	}
	return flxg_role_rank( flxg_view_scope_role( $v['scope'], $v['scope_id'] ) ) >= flxg_role_rank( 'edit' );
}

function flxg_rest_view_update( WP_REST_Request $r ) {
	$v = flxg_get_view( (int) $r['id'] );
	if ( ! flxg_can_edit_view( $v ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu görünümü değiştirme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	$b = flxg_body( $r );
	if ( isset( $b['shared'] ) && $b['shared'] && flxg_role_rank( flxg_view_scope_role( $v['scope'], $v['scope_id'] ) ) < flxg_role_rank( 'edit' ) ) {
		unset( $b['shared'] );
	}
	return array( 'view' => flxg_update_view( $v['id'], $b ) );
}

function flxg_rest_view_delete( WP_REST_Request $r ) {
	$v = flxg_get_view( (int) $r['id'] );
	if ( ! flxg_can_edit_view( $v ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu görünümü silme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	flxg_delete_view( $v['id'] );
	return array( 'views' => flxg_get_views( $v['scope'], $v['scope_id'], get_current_user_id() ) );
}

/* =========================================================================
 * Etiketler
 * ====================================================================== */

function flxg_rest_tag_create( WP_REST_Request $r ) {
	$b = flxg_body( $r );
	if ( ! flxg_can_space( (int) ( $b['space'] ?? 0 ), 'edit' ) && ! flxg_visible_list_ids( (int) ( $b['space'] ?? 0 ), null, 'edit' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Erişiminiz yok.', 'flx-tasks' ), 403 );
	}
	$id = flxg_create_tag( (int) $b['space'], (string) ( $b['name'] ?? '' ), (string) ( $b['color'] ?? '' ) );
	if ( ! $id ) {
		return flxg_error( 'flxg_name', __( 'Ad boş olamaz.', 'flx-tasks' ) );
	}
	flxg_bump_structure();
	return array( 'tag' => flxg_get_tag( $id ), 'tags' => flxg_structure( get_current_user_id() )['tags'] );
}

function flxg_rest_tag_update( WP_REST_Request $r ) {
	$t = flxg_get_tag( (int) $r['id'] );
	if ( ! $t || ! flxg_can_space( $t['space_id'], 'admin' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Etiketleri proje yöneticileri düzenler.', 'flx-tasks' ), 403 );
	}
	flxg_update_tag( $t['id'], flxg_body( $r ) );
	flxg_bump_structure();
	return array( 'tags' => flxg_structure( get_current_user_id() )['tags'] );
}

function flxg_rest_tag_delete( WP_REST_Request $r ) {
	$t = flxg_get_tag( (int) $r['id'] );
	if ( ! $t || ! flxg_can_space( $t['space_id'], 'admin' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Etiketleri proje yöneticileri düzenler.', 'flx-tasks' ), 403 );
	}
	flxg_delete_tag( $t['id'] );
	flxg_bump_structure();
	return array( 'tags' => flxg_structure( get_current_user_id() )['tags'] );
}

/* =========================================================================
 * Görevler
 * ====================================================================== */

/** İstek kapsamındaki listeler: list=ID | space=ID | mine=1. */
function flxg_scope_lists( WP_REST_Request $r ) {
	if ( $r->get_param( 'list' ) ) {
		$l = flxg_get_list( (int) $r->get_param( 'list' ) );
		return $l && flxg_can_list( $l ) ? array( $l['id'] ) : null;
	}
	if ( $r->get_param( 'space' ) ) {
		return flxg_visible_list_ids( (int) $r->get_param( 'space' ) );
	}
	return flxg_visible_list_ids();
}

function flxg_rest_tasks( WP_REST_Request $r ) {
	$lists = flxg_scope_lists( $r );
	if ( null === $lists ) {
		return flxg_error( 'flxg_404', __( 'Liste bulunamadı ya da erişiminiz yok.', 'flx-tasks' ), 404 );
	}
	$q = array(
		'lists'    => $lists,
		'archived' => (bool) $r->get_param( 'archived' ),
		'q'        => (string) $r->get_param( 'q' ),
		'limit'    => (int) ( $r->get_param( 'limit' ) ?: 3000 ),
	);
	if ( $r->get_param( 'mine' ) ) {
		$q['assignee'] = get_current_user_id();
	}
	if ( $r->get_param( 'ids' ) ) {
		$q['ids']   = array_slice( array_filter( array_map( 'intval', explode( ',', (string) $r->get_param( 'ids' ) ) ) ), 0, 200 );
		$q['limit'] = 200;
	}
	$tasks = flxg_query_tasks( $q );
	// Ekranda açık tutulan görevler (ör. başkasına atanan) filtreye uymasa da gelir.
	$keep = array_slice( array_filter( array_map( 'intval', explode( ',', (string) $r->get_param( 'keep' ) ) ) ), 0, 100 );
	if ( $keep ) {
		$have = wp_list_pluck( $tasks, 'id' );
		$keep = array_diff( $keep, $have );
		if ( $keep ) {
			$tasks = array_merge( $tasks, flxg_query_tasks( array( 'lists' => flxg_visible_list_ids( 0 ), 'ids' => $keep, 'limit' => 100 ) ) );
		}
	}
	return array(
		'tasks'  => $tasks,
		'time'   => flxg_now(),
		'fields' => $r->get_param( 'list' ) ? array() : flxg_get_fields( $lists ),
	);
}

function flxg_rest_task_create( WP_REST_Request $r ) {
	$b    = flxg_body( $r );
	$list = flxg_get_list( (int) ( $b['list'] ?? 0 ) );
	if ( ! $list || ! flxg_can_list( $list, 'edit' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu listeye görev ekleme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	$b['list_id'] = $list['id'];
	if ( ! empty( $b['parent'] ) ) {
		$b['parent_id'] = flxg_can_task( (int) $b['parent'], 'edit' ) ? (int) $b['parent'] : 0;
	}
	$id = flxg_create_task( $b, get_current_user_id() );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	return array( 'task' => flxg_get_task( $id, false ) );
}

function flxg_rest_task_get( WP_REST_Request $r ) {
	$id = (int) $r['id'];
	if ( ! flxg_can_task( $id ) ) {
		return flxg_error( 'flxg_404', __( 'Görev bulunamadı ya da erişiminiz yok.', 'flx-tasks' ), 404 );
	}
	flxg_mark_read( get_current_user_id(), null, $id );
	return array(
		'task'   => flxg_task_detail( $id, get_current_user_id() ),
		'unread' => flxg_unread_count( get_current_user_id() ),
	);
}

function flxg_rest_task_update( WP_REST_Request $r ) {
	$id  = (int) $r['id'];
	$row = flxg_get_task_row( $id );
	if ( ! $row || ! flxg_can_task( $row, 'edit' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu görevi düzenleme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	$b = flxg_body( $r );
	if ( isset( $b['list'] ) && (int) $b['list'] !== (int) $row['list_id'] && ! flxg_can_list( (int) $b['list'], 'edit' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Hedef listeye görev ekleme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	$t = flxg_update_task( $id, $b, get_current_user_id() );
	if ( is_wp_error( $t ) ) {
		return $t;
	}
	if ( ! empty( $b['detail'] ) ) {
		return array( 'task' => flxg_task_detail( $id, get_current_user_id() ) );
	}
	unset( $t['description'] );
	return array( 'task' => $t );
}

function flxg_rest_task_delete( WP_REST_Request $r ) {
	$id = (int) $r['id'];
	if ( ! flxg_can_task( $id, 'edit' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu görevi silme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	flxg_delete_task( $id, get_current_user_id() );
	return array( 'deleted' => $id );
}

function flxg_rest_task_bulk( WP_REST_Request $r ) {
	$b     = flxg_body( $r );
	$ids   = array_map( 'intval', (array) ( $b['ids'] ?? array() ) );
	$out   = array();
	$del   = array();
	$patch = is_array( $b['patch'] ?? null ) ? $b['patch'] : array();
	foreach ( array_slice( $ids, 0, 500 ) as $id ) {
		if ( ! flxg_can_task( $id, 'edit' ) ) {
			continue;
		}
		if ( ! empty( $b['delete'] ) ) {
			flxg_delete_task( $id, get_current_user_id() );
			$del[] = $id;
			continue;
		}
		if ( isset( $patch['list'] ) && ! flxg_can_list( (int) $patch['list'], 'edit' ) ) {
			continue;
		}
		$t = flxg_update_task( $id, $patch, get_current_user_id() );
		if ( ! is_wp_error( $t ) ) {
			unset( $t['description'] );
			$out[] = $t;
		}
	}
	return array( 'tasks' => $out, 'deleted' => $del );
}

function flxg_rest_task_watch( WP_REST_Request $r ) {
	$id = (int) $r['id'];
	if ( ! flxg_can_task( $id ) ) {
		return flxg_error( 'flxg_404', __( 'Görev bulunamadı ya da erişiminiz yok.', 'flx-tasks' ), 404 );
	}
	$b = flxg_body( $r );
	if ( ! empty( $b['watch'] ) ) {
		flxg_add_watcher( $id, get_current_user_id() );
	} else {
		flxg_remove_watcher( $id, get_current_user_id() );
	}
	return array( 'watchers' => flxg_watchers( $id ) );
}

function flxg_rest_comment_create( WP_REST_Request $r ) {
	$id = (int) $r['id'];
	if ( ! flxg_can_task( $id, 'comment' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu göreve yorum yazma yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	$b = flxg_body( $r );
	return flxg_add_comment( $id, (string) ( $b['body'] ?? '' ), (array) ( $b['files'] ?? array() ), get_current_user_id() );
}

function flxg_rest_comment_update( WP_REST_Request $r ) {
	$c = flxg_get_comment( (int) $r['id'] );
	if ( ! $c || (int) $c['user_id'] !== get_current_user_id() ) {
		return flxg_error( 'flxg_forbidden', __( 'Yalnız kendi yorumunuzu düzenleyebilirsiniz.', 'flx-tasks' ), 403 );
	}
	$b = flxg_body( $r );
	if ( '' === flxg_clean_comment( $b['body'] ?? '' ) ) {
		return flxg_error( 'flxg_empty', __( 'Yorum boş olamaz.', 'flx-tasks' ) );
	}
	$c = flxg_update_comment( $c['id'], (string) $b['body'] );
	return flxg_comment_out( $c );
}

function flxg_rest_comment_delete( WP_REST_Request $r ) {
	$c = flxg_get_comment( (int) $r['id'] );
	if ( ! $c || ( (int) $c['user_id'] !== get_current_user_id() && ! flxg_can_task( (int) $c['task_id'], 'admin' ) ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu yorumu silme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	flxg_delete_comment( $c['id'] );
	return array( 'deleted' => (int) $c['id'] );
}

/* =========================================================================
 * Dosyalar
 * ====================================================================== */

function flxg_rest_file_upload( WP_REST_Request $r ) {
	$files = $r->get_file_params();
	if ( empty( $files['file'] ) ) {
		return flxg_error( 'flxg_upload', __( 'Dosya yüklenemedi.', 'flx-tasks' ) );
	}
	$task    = (int) $r->get_param( 'task' );
	$doc     = (int) $r->get_param( 'doc' );
	$context = (string) $r->get_param( 'context' );
	$context = in_array( $context, array( 'task', 'pending' ), true ) ? $context : 'pending';
	if ( $doc ) {
		if ( ! flxg_can_doc( $doc, 'edit' ) ) {
			return flxg_error( 'flxg_forbidden', __( 'Bu dokümana dosya ekleme yetkiniz yok.', 'flx-tasks' ), 403 );
		}
		$f = flxg_store_upload( $files['file'], 0, get_current_user_id(), 'doc' );
		if ( is_wp_error( $f ) ) {
			return $f;
		}
		global $wpdb;
		$wpdb->update( flxg_table( 'files' ), array( 'doc_id' => $doc ), array( 'id' => (int) $f['id'] ) );
		$f['doc'] = $doc;
		return array( 'file' => $f );
	}
	if ( $task ) {
		$need = 'task' === $context ? 'edit' : 'comment';
		if ( ! flxg_can_task( $task, $need ) ) {
			return flxg_error( 'flxg_forbidden', __( 'Bu göreve dosya ekleme yetkiniz yok.', 'flx-tasks' ), 403 );
		}
	} else {
		$context = 'pending';
	}
	$f = flxg_store_upload( $files['file'], $task, get_current_user_id(), $context );
	if ( is_wp_error( $f ) ) {
		return $f;
	}
	if ( $task && 'task' === $context && $r->get_param( 'cover' ) && $f['image'] ) {
		flxg_update_task( $task, array( 'cover' => $f['id'] ), get_current_user_id() );
	}
	return array( 'file' => $f );
}

function flxg_file_can_view( $f ) {
	if ( ! $f ) {
		return false;
	}
	if ( 'pending' === $f['context'] && (int) $f['user_id'] === get_current_user_id() ) {
		return true;
	}
	if ( ! empty( $f['doc_id'] ) ) {
		return flxg_can_doc( (int) $f['doc_id'] );
	}
	return $f['task_id'] && flxg_can_task( (int) $f['task_id'] );
}

function flxg_rest_file_get( WP_REST_Request $r ) {
	$f = flxg_get_file( (int) $r['id'] );
	if ( ! flxg_file_can_view( $f ) ) {
		return flxg_error( 'flxg_404', __( 'Dosya bulunamadı.', 'flx-tasks' ), 404 );
	}
	flxg_send_file( $f, (string) $r->get_param( 'size' ) );
}

function flxg_rest_file_delete( WP_REST_Request $r ) {
	$f = flxg_get_file( (int) $r['id'] );
	if ( ! $f ) {
		return flxg_error( 'flxg_404', __( 'Dosya bulunamadı.', 'flx-tasks' ), 404 );
	}
	$own = (int) $f['user_id'] === get_current_user_id();
	if ( ! ( $own && flxg_file_can_view( $f ) ) && ! ( $f['task_id'] && flxg_can_task( (int) $f['task_id'], 'edit' ) ) && ! ( ! empty( $f['doc_id'] ) && flxg_can_doc( (int) $f['doc_id'], 'edit' ) ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu dosyayı silme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	flxg_delete_file( $f['id'] );
	if ( $f['task_id'] && 'pending' !== $f['context'] ) {
		flxg_log( (int) $f['task_id'], 'detach', array( 'name' => $f['name'] ), get_current_user_id() );
		global $wpdb;
		$wpdb->update( flxg_table( 'tasks' ), array( 'updated_at' => flxg_now() ), array( 'id' => (int) $f['task_id'] ) );
	}
	return array( 'deleted' => (int) $f['id'] );
}

/* =========================================================================
 * Bildirimler ve canlı güncelleme
 * ====================================================================== */

function flxg_rest_notifications( WP_REST_Request $r ) {
	$uid = get_current_user_id();
	return array(
		'items'  => flxg_get_notifications(
			$uid,
			array(
				'before' => (int) $r->get_param( 'before' ),
				'unread' => (bool) $r->get_param( 'unread' ),
				'limit'  => 30,
			)
		),
		'unread' => flxg_unread_count( $uid ),
	);
}

function flxg_rest_notifications_read( WP_REST_Request $r ) {
	$b   = flxg_body( $r );
	$uid = get_current_user_id();
	if ( ! empty( $b['all'] ) ) {
		flxg_mark_read( $uid );
	} elseif ( ! empty( $b['task'] ) ) {
		flxg_mark_read( $uid, null, (int) $b['task'] );
	} else {
		flxg_mark_read( $uid, (array) ( $b['ids'] ?? array() ) );
	}
	return array( 'unread' => flxg_unread_count( $uid ) );
}

/**
 * Seyrek yoklama: okunmamış sayısı, yeni bildirimler, açık kapsamdaki değişen/silinen görevler ve yapı sürümü.
 * Tek sorgu turu; uzun bekletme yok (paylaşımlı sunucuda süreç sınırı).
 */
function flxg_rest_sync( WP_REST_Request $r ) {
	global $wpdb;
	$uid   = get_current_user_id();
	$now   = flxg_now();
	$since = (string) $r->get_param( 'since' );
	$out   = array(
		'time'   => $now,
		'unread' => flxg_unread_count( $uid ),
		'rev'    => (int) get_option( 'flxg_struct_rev', 0 ),
		'notifs' => array(),
		'tasks'  => array(),
		'gone'   => array(),
	);
	$after = (int) $r->get_param( 'after' );
	if ( $after ) {
		$out['notifs'] = flxg_get_notifications( $uid, array( 'after' => $after, 'limit' => 20 ) );
	}
	if ( $since && preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since ) && ( $r->get_param( 'list' ) || $r->get_param( 'space' ) || $r->get_param( 'mine' ) ) ) {
		$lists = flxg_scope_lists( $r );
		if ( $lists ) {
			$back = gmdate( 'Y-m-d H:i:s', strtotime( $since . ' UTC' ) - 2 );
			$q    = array( 'lists' => $lists, 'since' => $back, 'limit' => 500 );
			if ( $r->get_param( 'mine' ) ) {
				$q['assignee'] = $uid;
			}
			$out['tasks'] = flxg_query_tasks( $q );
			$gone         = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT task_id FROM ' . flxg_table( 'activity' ) . " WHERE action IN ('deleted','removed','archived') AND created_at >= %s AND list_id IN (" . flxg_ids_in( $lists ) . ')', $back ) ); // phpcs:ignore
			$out['gone']  = array_map( 'intval', $gone );
			$changed_task = (int) $r->get_param( 'task' );
			if ( $changed_task && flxg_can_task( $changed_task ) ) {
				$row = flxg_get_task_row( $changed_task );
				$out['taskChanged'] = $row && $row['updated_at'] > $back;
			}
		}
	}
	return $out;
}

/* =========================================================================
 * Yönetim
 * ====================================================================== */

function flxg_rest_dashboard() {
	return flxg_dashboard_data();
}

function flxg_rest_dashboard_scan() {
	return flxg_scan_uploads();
}

function flxg_public_settings() {
	$s = flxg_settings();
	unset( $s['ai_key'], $s['ai_model'] );
	// Model Flux AI'de saklanır: "Eklentiye özel model" boşsa Flux AI varsayılanı.
	$map                = function_exists( 'flxai_setting' ) ? (array) flxai_setting( 'source_models', array() ) : array();
	$s['ai_hub']        = flxg_ai_hub();
	$s['ai_ready']      = flxg_ai_ready();
	$s['ai_model']      = (string) ( $map['flx-tasks'] ?? '' );
	$s['ai_model_eff']  = flxg_ai_model();
	$s['ai_hub_url']    = admin_url( flxg_ai_hub() ? 'admin.php?page=flx-ai-settings' : 'plugins.php' );
	$roles              = array();
	foreach ( wp_roles()->roles as $key => $r ) {
		$roles[] = array( 'key' => $key, 'name' => translate_user_role( $r['name'] ) );
	}
	return array(
		'settings' => $s,
		'roles'    => $roles,
		'usage'    => get_option( 'flxg_ai_usage', null ),
		'mcpUrl'   => flxg_mcp_url(),
	);
}

function flxg_rest_settings_get() {
	$s = flxg_public_settings();
	// Ortak sistem kontrolü (flx_system_checks) varsa tüm FluxWP eklenti/tema kontrolleri; yoksa yalnız Flux Tasks'inkiler.
	$s['checks'] = function_exists( 'flx_system_checks_run' ) ? array_values( (array) flx_system_checks_run() ) : flxg_system_checks();
	return $s;
}

function flxg_rest_settings_save( WP_REST_Request $r ) {
	$b   = flxg_body( $r );
	$set = array();
	$all = array_keys( wp_roles()->roles );
	foreach ( array( 'roles_use', 'roles_create', 'ai_roles' ) as $k ) {
		if ( isset( $b[ $k ] ) ) {
			$set[ $k ] = array_values( array_intersect( array_map( 'strval', (array) $b[ $k ] ), $all ) );
		}
	}
	if ( isset( $b['poll_interval'] ) ) {
		$set['poll_interval'] = max( 15, min( 600, (int) $b['poll_interval'] ) );
	}
	if ( isset( $b['max_upload_mb'] ) ) {
		$set['max_upload_mb'] = max( 1, min( 512, (int) $b['max_upload_mb'] ) );
	}
	foreach ( array( 'email_notify', 'mcp_enabled', 'delete_data' ) as $k ) {
		if ( isset( $b[ $k ] ) ) {
			$set[ $k ] = $b[ $k ] ? 1 : 0;
		}
	}
	if ( isset( $b['ai_model'] ) && function_exists( 'flxai_set_source_model' ) ) {
		flxai_set_source_model( 'flx-tasks', sanitize_text_field( (string) $b['ai_model'] ) );
	}
	if ( isset( $b['ai_temperature'] ) ) {
		$set['ai_temperature'] = max( 0, min( 1.5, (float) $b['ai_temperature'] ) );
	}
	if ( isset( $b['ai_instructions'] ) ) {
		$set['ai_instructions'] = sanitize_textarea_field( (string) $b['ai_instructions'] );
	}
	if ( isset( $b['accent'] ) ) {
		$set['accent'] = flxg_color( $b['accent'], '#2e7d32' );
	}
	flxg_update_settings( $set );
	return flxg_public_settings();
}

function flxg_rest_ai_chat( WP_REST_Request $r ) {
	if ( ! flxg_ai_ready() ) {
		return flxg_error( 'flxg_ai_off', flxg_ai_off_message() );
	}
	$b = flxg_body( $r );
	return flxg_ai_chat( (array) ( $b['messages'] ?? array() ), (array) ( $b['context'] ?? array() ) );
}

function flxg_rest_ai_task( WP_REST_Request $r ) {
	$id = (int) $r['id'];
	if ( ! flxg_can_task( $id ) ) {
		return flxg_error( 'flxg_404', __( 'Görev bulunamadı ya da erişiminiz yok.', 'flx-tasks' ), 404 );
	}
	if ( ! flxg_ai_ready() ) {
		return flxg_error( 'flxg_ai_off', flxg_ai_off_message() );
	}
	$b = flxg_body( $r );
	return flxg_ai_task_action( $id, (string) ( $b['action'] ?? '' ), (string) ( $b['hint'] ?? '' ) );
}

/* =========================================================================
 * MCP anahtarları
 * ====================================================================== */

function flxg_rest_mcp_keys() {
	$uid = get_current_user_id();
	$all = current_user_can( 'flxg_manage' );
	$out = array();
	foreach ( flxg_mcp_keys() as $id => $k ) {
		if ( ! $all && (int) $k['user'] !== $uid ) {
			continue;
		}
		$out[] = array(
			'id'       => $id,
			'label'    => $k['label'],
			'hint'     => $k['hint'],
			'user'     => (int) $k['user'],
			'userName' => flxg_user_name( (int) $k['user'] ),
			'created'  => gmdate( 'c', (int) $k['created'] ),
			'lastUsed' => $k['last_used'] ? gmdate( 'c', (int) $k['last_used'] ) : null,
		);
	}
	return array( 'keys' => $out, 'url' => flxg_mcp_url(), 'enabled' => (bool) flxg_setting( 'mcp_enabled' ) );
}

function flxg_rest_mcp_key_create( WP_REST_Request $r ) {
	$b = flxg_body( $r );
	$k = flxg_mcp_create_key( get_current_user_id(), (string) ( $b['label'] ?? '' ) );
	return array( 'key' => $k['key'], 'id' => $k['id'] ) + flxg_rest_mcp_keys();
}

function flxg_rest_mcp_key_delete( WP_REST_Request $r ) {
	$keys = flxg_mcp_keys();
	$id   = (string) $r['id'];
	if ( ! isset( $keys[ $id ] ) || ( (int) $keys[ $id ]['user'] !== get_current_user_id() && ! current_user_can( 'flxg_manage' ) ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Bu anahtarı silme yetkiniz yok.', 'flx-tasks' ), 403 );
	}
	flxg_mcp_delete_key( $id );
	return flxg_rest_mcp_keys();
}
