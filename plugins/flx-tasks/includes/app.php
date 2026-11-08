<?php
/**
 * Flux App yerel ekranı: `flxapp_modules` kaydı ve sade `flx-tasks/v1/app/*` uçları.
 * Sözleşme: docs/fluxapp/MODUL-API.md › Görevler. Yetki her uçta görev/liste düzeyinde denetlenir.
 *
 * @package flx-tasks
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flxapp_modules',
	function ( $m ) {
		$m              = is_array( $m ) ? $m : array();
		$m['flxg-tasks'] = array(
			'title'   => __( 'Tasks', 'flx-tasks' ),
			'cap'     => 'flxg_use',
			'menu'    => 'flx-tasks',
			'api'     => rest_url( FLXG_NS . '/app' ),
			'icon'    => 'task',
			'version' => FLXG_VERSION,
			'order'   => 30,
		);
		return $m;
	}
);

add_action(
	'rest_api_init',
	function () {
		flxg_route( '/app/projects', 'GET', 'flxg_app_projects' );
		flxg_route( '/app/tasks', 'GET', 'flxg_app_tasks' );
		flxg_route( '/app/tasks', 'POST', 'flxg_app_task_create' );
		flxg_route( '/app/tasks/(?P<id>\d+)', 'GET', 'flxg_app_task_get' );
		flxg_route( '/app/tasks/(?P<id>\d+)', 'POST', 'flxg_app_task_update' );
		flxg_route( '/app/tasks/(?P<id>\d+)/comments', 'POST', 'flxg_app_comment_add' );
		flxg_route( '/app/tasks/(?P<id>\d+)/checklist/(?P<item>\d+)', 'POST', 'flxg_app_checklist' );
	}
);

/* ------------------------------------------------------------------ Biçim */

/** Görev listesinde tamamlanma durumu (listenin durum türüne göre). */
function flxg_app_is_done( $list, $status ) {
	$info = $list ? flxg_status_info( $list, $status ) : null;
	return $info && 'done' === $info['type'];
}

function flxg_app_user( $uid ) {
	$u = get_userdata( (int) $uid );
	return array(
		'id'     => (int) $uid,
		'name'   => $u ? $u->display_name : __( 'Deleted user', 'flx-tasks' ),
		'avatar' => get_avatar_url( (int) $uid, array( 'size' => 96 ) ),
	);
}

/** "27 Eki" / "27 Eki 14:30"; yıl farklıysa yıl da yazılır. */
function flxg_app_due_label( $due ) {
	if ( '' === (string) $due ) {
		return '';
	}
	$tz   = wp_timezone();
	$has  = false !== strpos( $due, 'T' );
	$dt   = date_create_immutable( str_replace( 'T', ' ', $due ), $tz );
	if ( ! $dt ) {
		return $due;
	}
	$fmt = wp_date( 'Y' ) === $dt->format( 'Y' ) ? 'j M' : 'j M Y';
	return flx_date( $dt->getTimestamp(), $fmt . ( $has ? ' H:i' : '' ) );
}

/** Tarih/saat geçmiş mi (yalnız tarihse gün bitince gecikmiş sayılır). */
function flxg_app_overdue( $due ) {
	if ( '' === (string) $due ) {
		return false;
	}
	if ( false === strpos( $due, 'T' ) ) {
		return $due < wp_date( 'Y-m-d' );
	}
	return str_replace( 'T', ' ', $due ) < wp_date( 'Y-m-d H:i' );
}

/** Açıklamadaki işaretlenebilir liste maddeleri (ul[data-checklist] > li), sırayla. */
function flxg_app_checklist_items( $html ) {
	$out = array();
	if ( ! preg_match_all( '#<ul[^>]*data-checklist[^>]*>(.*?)</ul>#is', (string) $html, $uls ) ) {
		return $out;
	}
	foreach ( $uls[1] as $inner ) {
		preg_match_all( '#<li([^>]*)>(.*?)</li>#is', $inner, $lis, PREG_SET_ORDER );
		foreach ( $lis as $li ) {
			$out[] = array(
				'id'   => count( $out ) + 1,
				'text' => trim( html_entity_decode( wp_strip_all_tags( $li[2] ), ENT_QUOTES, 'UTF-8' ) ),
				'done' => (bool) preg_match( '/data-checked="1"/', $li[1] ),
			);
		}
	}
	return $out;
}

/**
 * Görevleri uygulama biçimine çevirir.
 *
 * @param array $tasks flxg_format_tasks çıktısı (description varsa kontrol listesi sayılır).
 */
function flxg_app_tasks_out( array $tasks ) {
	$pr  = flxg_priorities();
	$out = array();
	$usr = array();
	foreach ( $tasks as $t ) {
		$list  = flxg_get_list( (int) $t['list'] );
		$space = $list ? flxg_get_space( (int) $list['space_id'] ) : null;
		$info  = $list ? flxg_status_info( $list, $t['status'] ) : null;
		$done  = $info && 'done' === $info['type'];
		$as    = array();
		foreach ( (array) $t['assignees'] as $uid ) {
			if ( ! isset( $usr[ $uid ] ) ) {
				$usr[ $uid ] = flxg_app_user( $uid );
			}
			$as[] = $usr[ $uid ];
		}
		$cl    = isset( $t['description'] ) ? flxg_app_checklist_items( $t['description'] ) : array();
		$p     = (int) $t['priority'];
		$out[] = array(
			'id'             => (int) $t['id'],
			'title'          => $t['title'],
			'status'         => $done ? 'done' : 'open',
			'status_key'     => $t['status'],
			'status_label'   => $info ? $info['label'] : $t['status'],
			'status_color'   => $info ? $info['color'] : '#94a3b8',
			'project'        => $space ? array( 'id' => (int) $space['id'], 'title' => $space['name'], 'color' => $space['color'] ) : null,
			'list'           => $list ? array( 'id' => (int) $list['id'], 'title' => $list['name'] ) : null,
			'due'            => $t['due'],
			'due_label'      => flxg_app_due_label( $t['due'] ),
			'overdue'        => ! $done && flxg_app_overdue( $t['due'] ),
			// Sözleşme 0-3 (3 en yüksek): Acil 3, Yüksek 2, Normal 1, Düşük 1, yok 0. Eklentinin kendi değeri priority_key.
			'priority'       => array( 0 => 0, 1 => 3, 2 => 2, 3 => 1, 4 => 1 )[ $p ] ?? 0,
			'priority_key'   => $p,
			'priority_label' => $pr[ $p ]['label'] ?? '',
			'assignees'      => $as,
			'comments'       => (int) $t['comments'],
			'checklist'      => array(
				'done'  => count( array_filter( wp_list_pluck( $cl, 'done' ) ) ),
				'total' => count( $cl ),
			),
			'subtasks'       => (int) $t['subtasks'],
			'url'            => flxg_app_url( $t['id'] ),
		);
	}
	return $out;
}

/** Durum isteği: 'done' / 'open' listenin ilk uygun durumuna, başka değer listenin durum anahtarı olarak. */
function flxg_app_status_key( $list, $want ) {
	$want = (string) $want;
	if ( 'done' === $want || 'open' === $want ) {
		return flxg_first_status( $list, $want );
	}
	return flxg_status_info( $list, $want ) ? $want : null;
}

/* ------------------------------------------------------------------ Uçlar */

function flxg_app_projects() {
	global $wpdb;
	$uid   = get_current_user_id();
	$lists = array();
	foreach ( flxg_all_lists() as $l ) {
		if ( flxg_can_list( $l, 'view', $uid ) ) {
			$lists[ (int) $l['id'] ] = $l;
		}
	}
	// Açık görev sayısı: tek sorguda liste + durum bazında.
	$open = array();
	if ( $lists ) {
		$rows = $wpdb->get_results( 'SELECT list_id, status, COUNT(*) AS n FROM ' . flxg_table( 'tasks' ) . ' WHERE archived = 0 AND parent_id = 0 AND list_id IN (' . flxg_ids_in( array_keys( $lists ) ) . ') GROUP BY list_id, status' ); // phpcs:ignore
		foreach ( (array) $rows as $r ) {
			if ( ! flxg_app_is_done( $lists[ (int) $r->list_id ], $r->status ) ) {
				$open[ (int) $r->list_id ] = ( $open[ (int) $r->list_id ] ?? 0 ) + (int) $r->n;
			}
		}
	}
	$items = array();
	foreach ( flxg_all_spaces() as $sp ) {
		$ls = array();
		$n  = 0;
		foreach ( $lists as $l ) {
			if ( (int) $l['space_id'] !== (int) $sp['id'] || ! in_array( $l['kind'] ?? 'task', array( 'task', 'meeting' ), true ) ) {
				continue;
			}
			$ls[] = array(
				'id'     => (int) $l['id'],
				'title'  => $l['name'],
				'color'  => $l['color'],
				'status' => empty( $l['archived'] ) ? 'open' : 'done',
				'open'   => $open[ (int) $l['id'] ] ?? 0,
			);
			$n   += $open[ (int) $l['id'] ] ?? 0;
		}
		if ( ! $ls && ! flxg_can_space( $sp, 'view', $uid ) ) {
			continue;
		}
		$items[] = array(
			'id'    => (int) $sp['id'],
			'title' => $sp['name'],
			'color' => $sp['color'],
			'lists' => $ls,
			'open'  => $n,
		);
	}
	return array( 'items' => $items );
}

function flxg_app_tasks( WP_REST_Request $r ) {
	$uid  = get_current_user_id();
	$view = sanitize_key( (string) ( $r['view'] ?? 'mine' ) );
	$per  = max( 1, min( 50, (int) ( $r['per_page'] ?? 30 ) ) );
	$page = max( 1, (int) ( $r['page'] ?? 1 ) );
	$q    = array( 'exclude_kind' => array( 'agenda' ), 'q' => sanitize_text_field( (string) ( $r['q'] ?? '' ) ) );
	$list = (int) ( $r['list'] ?? 0 );
	$proj = (int) ( $r['project'] ?? 0 );
	if ( $list ) {
		if ( ! flxg_can_list( $list, 'view', $uid ) ) {
			return flxg_error( 'flxg_404', __( 'List not found or you don\'t have access.', 'flx-tasks' ), 404 );
		}
		$q['lists'] = array( $list );
	} else {
		$q['lists'] = flxg_visible_list_ids( $proj, $uid );
	}
	$today = wp_date( 'Y-m-d' );
	if ( 'project' === $view ) {
		$q['parent'] = 0;
		$q['order']  = 'position';
		if ( empty( $r['done'] ) ) {
			$q['status_type'] = 'open';
		}
	} else {
		$q['assignee']    = $uid;
		$q['status_type'] = 'open';
		$q['order']       = 'due';
		if ( 'today' === $view ) {
			$q['due_before'] = $today;
		} elseif ( 'overdue' === $view ) {
			$q['due_before'] = wp_date( 'Y-m-d', time() - DAY_IN_SECONDS );
		}
	}
	$total = (int) flxg_query_tasks( $q + array( 'count' => true ) );
	$rows  = flxg_query_tasks( $q + array( 'limit' => $per, 'offset' => ( $page - 1 ) * $per, 'with_desc' => true ) );
	return array(
		'items' => flxg_app_tasks_out( $rows ),
		'total' => $total,
		'page'  => $page,
		'pages' => max( 1, (int) ceil( $total / $per ) ),
	);
}

function flxg_app_task_detail( $id ) {
	$t = flxg_get_task( $id, true );
	if ( ! $t ) {
		return null;
	}
	$out                     = flxg_app_tasks_out( array( $t ) )[0];
	$out['description_html'] = (string) $t['description'];
	$out['checklist_items']  = flxg_app_checklist_items( $t['description'] );
	$out['comments_list']    = array();
	foreach ( flxg_get_comments( $id ) as $c ) {
		$out['comments_list'][] = flxg_app_comment_out( $c );
	}
	$list = flxg_get_list( (int) $t['list'] );
	$out['statuses'] = array();
	foreach ( (array) ( $list['statuses'] ?? array() ) as $s ) {
		$out['statuses'][] = array( 'key' => $s['key'], 'label' => $s['label'], 'color' => $s['color'], 'done' => 'done' === $s['type'] );
	}
	$out['can_edit'] = flxg_can_task( $id, 'edit' );
	return $out;
}

function flxg_app_comment_out( array $c ) {
	$u = flxg_app_user( $c['user'] );
	return array(
		'id'       => (int) $c['id'],
		'author'   => array( 'id' => $u['id'], 'name' => $u['name'], 'avatar' => $u['avatar'] ),
		'text'     => $c['body'],
		'at'       => $c['created'],
		'at_label' => $c['created'] ? flx_date( strtotime( $c['created'] ), 'j M H:i' ) : '',
	);
}

function flxg_app_task_get( WP_REST_Request $r ) {
	$id = (int) $r['id'];
	if ( ! flxg_can_task( $id ) ) {
		return flxg_error( 'flxg_404', __( 'Task not found or you don\'t have access.', 'flx-tasks' ), 404 );
	}
	flxg_mark_read( get_current_user_id(), null, $id );
	$t = flxg_app_task_detail( $id );
	// Ayrıntıda sözleşmedeki gibi checklist ve comments tam dizi; özet sayılar ayrı alanlarda.
	$t['checklist_summary'] = $t['checklist'];
	$t['checklist']         = $t['checklist_items'];
	$t['comments_count']    = $t['comments'];
	$t['comments']          = $t['comments_list'];
	unset( $t['checklist_items'], $t['comments_list'] );
	return $t;
}

function flxg_app_task_create( WP_REST_Request $r ) {
	$b    = flxg_body( $r );
	$uid  = get_current_user_id();
	$list = null;
	if ( ! empty( $b['list'] ) ) {
		$list = flxg_get_list( (int) $b['list'] );
	} elseif ( ! empty( $b['project'] ) ) {
		// Listesi verilmemişse projenin yazılabilir ilk görev listesi.
		foreach ( flxg_all_lists() as $l ) {
			if ( (int) $l['space_id'] === (int) $b['project'] && 'task' === ( $l['kind'] ?? 'task' ) && flxg_can_list( $l, 'edit', $uid ) ) {
				$list = $l;
				break;
			}
		}
	}
	if ( ! $list || ! flxg_can_list( $list, 'edit', $uid ) ) {
		return flxg_error( 'flxg_forbidden', __( 'You don\'t have permission to add tasks to this list.', 'flx-tasks' ), 403 );
	}
	$d = array(
		'list_id'   => (int) $list['id'],
		'title'     => (string) ( $b['title'] ?? '' ),
		'due'       => (string) ( $b['due'] ?? '' ),
		'assignees' => array_map( 'intval', (array) ( $b['assignees'] ?? array() ) ),
	);
	if ( isset( $b['priority'] ) ) {
		$d['priority'] = flxg_app_priority_in( $b['priority'] );
	}
	$id = flxg_create_task( $d, $uid );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	return flxg_app_tasks_out( array( flxg_get_task( $id, true ) ) )[0];
}

/** Sözleşme önceliği (0-3, 3 en yüksek) → eklenti (0 yok, 1 Acil … 4 Düşük). */
function flxg_app_priority_in( $p ) {
	return array( 0 => 0, 1 => 3, 2 => 2, 3 => 1 )[ max( 0, min( 3, (int) $p ) ) ];
}

function flxg_app_task_update( WP_REST_Request $r ) {
	$id  = (int) $r['id'];
	$uid = get_current_user_id();
	$row = flxg_get_task_row( $id );
	if ( ! $row || ! flxg_can_task( $row, 'edit', $uid ) ) {
		return flxg_error( 'flxg_forbidden', __( 'You don\'t have permission to edit this task.', 'flx-tasks' ), 403 );
	}
	$b = flxg_body( $r );
	$d = array();
	if ( isset( $b['title'] ) ) {
		$d['title'] = (string) $b['title'];
	}
	if ( array_key_exists( 'due', $b ) ) {
		$d['due'] = (string) $b['due'];
	}
	if ( isset( $b['assignees'] ) ) {
		$d['assignees'] = array_map( 'intval', (array) $b['assignees'] );
	}
	if ( isset( $b['priority_key'] ) ) {
		$d['priority'] = max( 0, min( 4, (int) $b['priority_key'] ) );
	} elseif ( isset( $b['priority'] ) ) {
		$d['priority'] = flxg_app_priority_in( $b['priority'] );
	}
	$target = flxg_get_list( (int) $row['list_id'] );
	if ( isset( $b['list'] ) && (int) $b['list'] !== (int) $row['list_id'] ) {
		$target = flxg_get_list( (int) $b['list'] );
		if ( ! $target || ! flxg_can_list( $target, 'edit', $uid ) ) {
			return flxg_error( 'flxg_forbidden', __( 'You don\'t have permission to add tasks to the target list.', 'flx-tasks' ), 403 );
		}
		$d['list'] = (int) $target['id'];
	}
	if ( isset( $b['status'] ) ) {
		$key = flxg_app_status_key( $target, $b['status'] );
		if ( null === $key ) {
			return flxg_error( 'flxg_status', __( 'This list has no such status.', 'flx-tasks' ), 400 );
		}
		$d['status'] = $key;
	}
	$t = flxg_update_task( $id, $d, $uid );
	if ( is_wp_error( $t ) ) {
		return $t;
	}
	return flxg_app_tasks_out( array( flxg_get_task( $id, true ) ) )[0];
}

function flxg_app_comment_add( WP_REST_Request $r ) {
	$id = (int) $r['id'];
	if ( ! flxg_can_task( $id, 'comment' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'You don\'t have permission to comment on this task.', 'flx-tasks' ), 403 );
	}
	$b = flxg_body( $r );
	$c = flxg_add_comment( $id, (string) ( $b['text'] ?? '' ), array(), get_current_user_id() );
	if ( is_wp_error( $c ) ) {
		return $c;
	}
	return flxg_app_comment_out( $c );
}

function flxg_app_checklist( WP_REST_Request $r ) {
	$id   = (int) $r['id'];
	$item = (int) $r['item'];
	$uid  = get_current_user_id();
	$row  = flxg_get_task_row( $id );
	if ( ! $row || ! flxg_can_task( $row, 'edit', $uid ) ) {
		return flxg_error( 'flxg_forbidden', __( 'You don\'t have permission to edit this task.', 'flx-tasks' ), 403 );
	}
	$done = ! empty( flxg_body( $r )['done'] );
	$n    = 0;
	$hit  = false;
	$html = preg_replace_callback(
		'#(<ul[^>]*data-checklist[^>]*>)(.*?)(</ul>)#is',
		function ( $ul ) use ( $item, $done, &$n, &$hit ) {
			$inner = preg_replace_callback(
				'#<li([^>]*)>#i',
				function ( $li ) use ( $item, $done, &$n, &$hit ) {
					if ( ++$n !== $item ) {
						return $li[0];
					}
					$hit   = true;
					$attrs = preg_replace( '/\s*data-checked="[^"]*"/', '', $li[1] );
					return '<li' . $attrs . ' data-checked="' . ( $done ? '1' : '0' ) . '">';
				},
				$ul[2]
			);
			return $ul[1] . $inner . $ul[3];
		},
		(string) $row['description']
	);
	if ( ! $hit ) {
		return flxg_error( 'flxg_404', __( 'Item not found.', 'flx-tasks' ), 404 );
	}
	$t = flxg_update_task( $id, array( 'description' => $html ), $uid );
	if ( is_wp_error( $t ) ) {
		return $t;
	}
	return array( 'ok' => true );
}
