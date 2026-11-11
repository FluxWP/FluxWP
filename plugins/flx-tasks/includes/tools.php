<?php
/**
 * Araç kaydı: Flux AI MCP sunucusu ve yapay zekâ sohbeti aynı araçları kullanır.
 * Araçlar her zaman oturumdaki kullanıcının liste yetkileriyle çalışır.
 */

defined( 'ABSPATH' ) || exit;

class FLXG_Tool_Error extends Exception {}

function flxg_tool_fail( $msg ) {
	throw new FLXG_Tool_Error( $msg );
}

/** Görevi yapay zekâ/MCP için okunur biçime çevirir. */
function flxg_tool_task( $t, $full = false ) {
	$list   = flxg_get_list( $t['list'] );
	$status = $list ? flxg_status_info( $list, $t['status'] ) : null;
	$users  = flxg_users();
	$tags   = array();
	foreach ( flxg_all_tags() as $tg ) {
		if ( in_array( $tg['id'], $t['tags'], true ) ) {
			$tags[] = $tg['name'];
		}
	}
	$pr  = flxg_priorities();
	$out = array(
		'id'        => $t['id'],
		'title'     => $t['title'],
		'list_id'   => $t['list'],
		'list'      => $list ? $list['name'] : '',
		'status'    => $t['status'],
		'status_label' => $status ? $status['label'] : $t['status'],
		'done'      => $status && 'done' === $status['type'],
		'priority'  => $t['priority'] ? $t['priority'] . ' (' . $pr[ $t['priority'] ]['label'] . ')' : null,
		'start'     => $t['start'] ? $t['start'] : null,
		'due'       => $t['due'] ? $t['due'] : null,
		'assignees' => array_values(
			array_map(
				function ( $u ) use ( $users ) {
					return array( 'id' => $u, 'name' => $users[ $u ]['name'] ?? flxg_user_name( $u ) );
				},
				$t['assignees']
			)
		),
		'tags'      => $tags,
		'comments'  => $t['comments'],
		'url'       => flxg_app_url( $t['id'] ),
	);
	if ( $t['parent'] ) {
		$out['parent_id'] = $t['parent'];
	}
	$fields = array();
	foreach ( flxg_get_fields( $t['list'] ) as $f ) {
		$v = $t['fields']->{$f['id']} ?? null;
		if ( null !== $v && '' !== $v && array() !== $v ) {
			if ( in_array( $f['type'], array( 'dropdown', 'labels' ), true ) ) {
				$map = wp_list_pluck( (array) ( $f['options']['choices'] ?? array() ), 'label', 'id' );
				$v   = is_array( $v ) ? array_map( function ( $x ) use ( $map ) {
					return $map[ $x ] ?? $x;
				}, $v ) : ( $map[ $v ] ?? $v );
			}
			$fields[ $f['name'] ] = $v;
		}
	}
	if ( $fields ) {
		$out['fields'] = $fields;
	}
	if ( $full ) {
		$out['description'] = trim( html_entity_decode( wp_strip_all_tags( str_replace( array( '</p>', '<br>', '<br/>', '</li>' ), "\n", (string) ( $t['description'] ?? '' ) ) ) ) );
		$out['created']     = $t['created'];
		$out['updated']     = $t['updated'];
	}
	return $out;
}

function flxg_tool_list_or_fail( $id, $need ) {
	$list = flxg_get_list( (int) $id );
	if ( ! $list || ! flxg_can_list( $list, 'view' ) ) {
		flxg_tool_fail( 'List not found or not accessible: ' . (int) $id );
	}
	if ( ! flxg_can_list( $list, $need ) ) {
		flxg_tool_fail( 'You do not have permission for this action on list ' . (int) $id );
	}
	return $list;
}

function flxg_tool_task_or_fail( $id, $need ) {
	$row = flxg_get_task_row( (int) $id );
	if ( ! $row || ! flxg_can_task( $row, 'view' ) ) {
		flxg_tool_fail( 'Task not found or not accessible: ' . (int) $id );
	}
	if ( ! flxg_can_task( $row, $need ) ) {
		flxg_tool_fail( 'You do not have permission for this action on task ' . (int) $id );
	}
	return $row;
}

/** Durum adı veya anahtarından anahtar. */
function flxg_tool_status_key( $list, $s ) {
	if ( null === $s || '' === $s ) {
		return null;
	}
	foreach ( $list['statuses'] as $st ) {
		if ( $st['key'] === $s || mb_strtolower( $st['label'] ) === mb_strtolower( (string) $s ) ) {
			return $st['key'];
		}
	}
	if ( in_array( mb_strtolower( (string) $s ), array( 'done', 'complete', 'completed', 'closed' ), true ) ) {
		return flxg_first_status( $list, 'done' );
	}
	flxg_tool_fail( 'Unknown status "' . $s . '". Valid: ' . implode( ', ', wp_list_pluck( $list['statuses'], 'label' ) ) );
}

/** Kişi kimliği, adı ya da "me". */
function flxg_tool_user_ids( $in ) {
	$out   = array();
	$users = flxg_users();
	foreach ( (array) $in as $u ) {
		if ( 'me' === $u ) {
			$out[] = get_current_user_id();
			continue;
		}
		if ( is_numeric( $u ) && isset( $users[ (int) $u ] ) ) {
			$out[] = (int) $u;
			continue;
		}
		$found = false;
		foreach ( $users as $x ) {
			if ( mb_strtolower( $x['name'] ) === mb_strtolower( (string) $u ) ) {
				$out[] = $x['id'];
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			flxg_tool_fail( 'Unknown user "' . $u . '". Use list_users to see ids.' );
		}
	}
	return $out;
}

/** Alan adlarını/kimliklerini ve seçenek etiketlerini alan değerlerine çevirir. */
function flxg_tool_fields( $list_id, $in ) {
	$out    = array();
	$fields = flxg_get_fields( $list_id );
	foreach ( (array) $in as $k => $v ) {
		$match = null;
		foreach ( $fields as $f ) {
			if ( (string) $f['id'] === (string) $k || mb_strtolower( $f['name'] ) === mb_strtolower( (string) $k ) ) {
				$match = $f;
				break;
			}
		}
		if ( ! $match ) {
			flxg_tool_fail( 'Unknown field "' . $k . '". Use list_fields.' );
		}
		if ( in_array( $match['type'], array( 'dropdown', 'labels' ), true ) ) {
			$map = array();
			foreach ( (array) ( $match['options']['choices'] ?? array() ) as $c ) {
				$map[ mb_strtolower( $c['label'] ) ] = $c['id'];
				$map[ $c['id'] ]                     = $c['id'];
			}
			$v = is_array( $v ) ? array_map( function ( $x ) use ( $map ) {
				return $map[ mb_strtolower( (string) $x ) ] ?? $x;
			}, $v ) : ( $map[ mb_strtolower( (string) $v ) ] ?? $v );
		}
		if ( 'people' === $match['type'] ) {
			$v = flxg_tool_user_ids( (array) $v );
		}
		$out[ $match['id'] ] = $v;
	}
	return $out;
}

function flxg_tools() {
	$task_props = array(
		'title'       => array( 'type' => 'string' ),
		'description' => array( 'type' => 'string', 'description' => 'Plain text or simple HTML (p, ul, li, strong, a).' ),
		'status'      => array( 'type' => 'string', 'description' => 'Status key or label of the list.' ),
		'priority'    => array( 'type' => 'integer', 'description' => '0 none, 1 urgent, 2 high, 3 normal, 4 low' ),
		'start'       => array( 'type' => 'string', 'description' => 'YYYY-MM-DD or YYYY-MM-DDTHH:MM (site time). Empty string clears.' ),
		'due'         => array( 'type' => 'string', 'description' => 'YYYY-MM-DD or YYYY-MM-DDTHH:MM (site time). Empty string clears.' ),
		'assignees'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'User ids, names or "me". Replaces the assignees.' ),
		'tags'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Tag names (created if missing). Replaces the tags.' ),
		'fields'      => array( 'type' => 'object', 'description' => 'Custom field values keyed by field name or id.' ),
	);
	return array_merge( array(
		'list_projects'      => array(
			'title'       => 'List projects and lists',
			'description' => 'Projects (spaces), folders and lists the user can see, with list ids, statuses and the user role.',
			'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
			'readonly'    => true,
			'run'         => 'flxg_tool_list_projects',
		),
		'list_users'         => array(
			'title'       => 'List users',
			'description' => 'People who can be assigned to tasks.',
			'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
			'readonly'    => true,
			'run'         => function () {
				return array_values(
					array_map(
						function ( $u ) {
							return array( 'id' => $u['id'], 'name' => $u['name'] );
						},
						flxg_users()
					)
				);
			},
		),
		'search_tasks'       => array(
			'title'       => 'Search tasks',
			'description' => 'Find tasks. Without list_id/project_id searches every list the user can see. Done tasks are excluded unless include_done is true.',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'list_id'      => array( 'type' => 'integer' ),
					'project_id'   => array( 'type' => 'integer' ),
					'query'        => array( 'type' => 'string', 'description' => 'Text in title/description, or a task id like #12.' ),
					'status'       => array( 'type' => 'string' ),
					'assignee'     => array( 'type' => 'string', 'description' => 'User id, name or "me".' ),
					'due_before'   => array( 'type' => 'string', 'description' => 'YYYY-MM-DD' ),
					'due_after'    => array( 'type' => 'string', 'description' => 'YYYY-MM-DD' ),
					'include_done' => array( 'type' => 'boolean' ),
					'limit'        => array( 'type' => 'integer', 'description' => 'Default 50, max 200.' ),
				),
			),
			'readonly'    => true,
			'run'         => 'flxg_tool_search_tasks',
		),
		'get_task'           => array(
			'title'       => 'Get task',
			'description' => 'Full task with description, custom fields, comments and subtasks.',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'task_id' => array( 'type' => 'integer' ) ),
				'required'   => array( 'task_id' ),
			),
			'readonly'    => true,
			'run'         => 'flxg_tool_get_task',
		),
		'create_task'        => array(
			'title'       => 'Create task',
			'description' => 'Create a task in a list. Requires edit permission on the list.',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array_merge(
					array(
						'list_id'   => array( 'type' => 'integer' ),
						'parent_id' => array( 'type' => 'integer', 'description' => 'Make it a subtask of this task.' ),
					),
					$task_props
				),
				'required'   => array( 'list_id', 'title' ),
			),
			'run'         => 'flxg_tool_create_task',
		),
		'update_task'        => array(
			'title'       => 'Update task',
			'description' => 'Change task properties. Only given properties change. list_id moves the task to another list.',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array_merge(
					array(
						'task_id' => array( 'type' => 'integer' ),
						'list_id' => array( 'type' => 'integer' ),
					),
					$task_props
				),
				'required'   => array( 'task_id' ),
			),
			'run'         => 'flxg_tool_update_task',
		),
		'add_comment'        => array(
			'title'       => 'Add comment',
			'description' => 'Comment on a task. Mention a user with @{user_id}.',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'task_id' => array( 'type' => 'integer' ),
					'text'    => array( 'type' => 'string' ),
				),
				'required'   => array( 'task_id', 'text' ),
			),
			'run'         => 'flxg_tool_add_comment',
		),
		'list_fields'        => array(
			'title'       => 'List custom fields',
			'description' => 'Custom fields of a list with types and choices.',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'list_id' => array( 'type' => 'integer' ) ),
				'required'   => array( 'list_id' ),
			),
			'readonly'    => true,
			'run'         => function ( $a ) {
				$list = flxg_tool_list_or_fail( $a['list_id'] ?? 0, 'view' );
				$out  = array();
				foreach ( flxg_get_fields( $list['id'] ) as $f ) {
					$row = array( 'id' => $f['id'], 'name' => $f['name'], 'type' => $f['type'] );
					if ( ! empty( $f['options']['choices'] ) ) {
						$row['choices'] = wp_list_pluck( $f['options']['choices'], 'label' );
					}
					$out[] = $row;
				}
				return $out;
			},
		),
		'create_list'        => array(
			'title'       => 'Create list',
			'description' => 'Create a new list in a project (optionally inside a folder). Requires list creation permission.',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'project_id' => array( 'type' => 'integer' ),
					'folder_id'  => array( 'type' => 'integer' ),
					'name'       => array( 'type' => 'string' ),
				),
				'required'   => array( 'project_id', 'name' ),
			),
			'run'         => function ( $a ) {
				if ( ! current_user_can( 'flxg_create' ) || ! flxg_can_space( (int) ( $a['project_id'] ?? 0 ), 'edit' ) ) {
					flxg_tool_fail( 'You cannot create lists in this project.' );
				}
				$id = flxg_create_list_row(
					array(
						'space_id'  => (int) $a['project_id'],
						'folder_id' => (int) ( $a['folder_id'] ?? 0 ),
						'name'      => (string) $a['name'],
						'owner_id'  => get_current_user_id(),
					)
				);
				return array( 'list_id' => $id, 'name' => flxg_get_list( $id )['name'] );
			},
		),
		'delete_task'        => array(
			'title'       => 'Delete task',
			'description' => 'Permanently delete a task with its comments and files. Ask the user before using.',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'task_id' => array( 'type' => 'integer' ) ),
				'required'   => array( 'task_id' ),
			),
			'destructive' => true,
			'run'         => function ( $a ) {
				$row = flxg_tool_task_or_fail( $a['task_id'] ?? 0, 'edit' );
				flxg_delete_task( (int) $row['id'], get_current_user_id() );
				return array( 'deleted' => (int) $row['id'] );
			},
		),
		'list_notifications' => array(
			'title'       => 'List notifications',
			'description' => 'Recent notifications of the user.',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'unread_only' => array( 'type' => 'boolean' ) ),
			),
			'readonly'    => true,
			'run'         => function ( $a ) {
				$out = array();
				foreach ( flxg_get_notifications( get_current_user_id(), array( 'unread' => ! empty( $a['unread_only'] ), 'limit' => 30 ) ) as $n ) {
					$out[] = array(
						'task_id' => $n['task'],
						'task'    => $n['title'],
						'text'    => flxg_notification_text( $n['type'], $n['data'], $n['actor'] ? flxg_user_name( $n['actor'] ) : '' ),
						'excerpt' => $n['data']['excerpt'] ?? null,
						'read'    => $n['read'],
						'time'    => $n['created'],
					);
				}
				return $out;
			},
		),
	), flxg_doc_tools() );
}

function flxg_tool_list_projects() {
	$out = array();
	foreach ( flxg_all_spaces() as $s ) {
		$lists = array();
		foreach ( flxg_all_lists() as $l ) {
			if ( (int) $l['space_id'] !== $s['id'] || ! flxg_can_list( $l ) ) {
				continue;
			}
			$folder  = $l['folder_id'] ? flxg_get_folder( $l['folder_id'] ) : null;
			$lists[] = array(
				'id'       => $l['id'],
				'name'     => $l['name'],
				'folder'   => $folder ? $folder['name'] : null,
				'role'     => flxg_list_role( $l ),
				'statuses' => array_map(
					function ( $st ) {
						return $st['label'] . ' [' . $st['key'] . ( 'done' === $st['type'] ? ', done' : '' ) . ']';
					},
					$l['statuses']
				),
			);
		}
		if ( ! $lists && ! flxg_can_space( $s ) ) {
			continue;
		}
		$out[] = array(
			'id'    => $s['id'],
			'name'  => $s['name'],
			'lists' => $lists,
		);
	}
	return array(
		'today'    => flxg_today(),
		'me'       => array( 'id' => get_current_user_id(), 'name' => flxg_user_name( get_current_user_id() ) ),
		'projects' => $out,
	);
}

function flxg_tool_search_tasks( $a ) {
	if ( ! empty( $a['list_id'] ) ) {
		$lists = array( (int) flxg_tool_list_or_fail( $a['list_id'], 'view' )['id'] );
	} else {
		$lists = flxg_visible_list_ids( (int) ( $a['project_id'] ?? 0 ) );
	}
	$q = array(
		'lists'      => $lists,
		'q'          => (string) ( $a['query'] ?? '' ),
		'due_before' => ! empty( $a['due_before'] ) ? flxg_clean_date( $a['due_before'] ) : '',
		'due_after'  => ! empty( $a['due_after'] ) ? flxg_clean_date( $a['due_after'] ) : '',
		'limit'      => max( 1, min( 200, (int) ( $a['limit'] ?? 50 ) ) ),
		'order'      => 'due',
	);
	if ( empty( $a['include_done'] ) ) {
		$q['status_type'] = 'open';
	}
	if ( ! empty( $a['assignee'] ) ) {
		$ids           = flxg_tool_user_ids( array( $a['assignee'] ) );
		$q['assignee'] = $ids[0];
	}
	if ( ! empty( $a['status'] ) && 1 === count( $lists ) ) {
		$q['status'] = array( flxg_tool_status_key( flxg_get_list( $lists[0] ), $a['status'] ) );
	}
	$tasks = flxg_query_tasks( $q );
	if ( ! empty( $a['status'] ) && count( $lists ) > 1 ) {
		$tasks = array_filter(
			$tasks,
			function ( $t ) use ( $a ) {
				$st = flxg_status_info( flxg_get_list( $t['list'] ), $t['status'] );
				return $st && ( $st['key'] === $a['status'] || mb_strtolower( $st['label'] ) === mb_strtolower( $a['status'] ) );
			}
		);
	}
	return array(
		'count' => count( $tasks ),
		'tasks' => array_values( array_map( 'flxg_tool_task', $tasks ) ),
	);
}

function flxg_tool_get_task( $a ) {
	$row = flxg_tool_task_or_fail( $a['task_id'] ?? 0, 'view' );
	$t   = flxg_get_task( (int) $row['id'], true );
	$out = flxg_tool_task( $t, true );
	$out['comments'] = array_map(
		function ( $c ) {
			return array(
				'id'     => $c['id'],
				'author' => flxg_user_name( $c['user'] ),
				'time'   => $c['created'],
				'text'   => flxg_comment_excerpt( $c['body'], 4000 ),
				'files'  => wp_list_pluck( $c['files'], 'name' ),
			);
		},
		flxg_get_comments( (int) $row['id'] )
	);
	$out['subtasks'] = array_map( 'flxg_tool_task', flxg_query_tasks( array( 'lists' => array( (int) $row['list_id'] ), 'parent' => (int) $row['id'] ) ) );
	return $out;
}

function flxg_tool_task_patch( $list, $a ) {
	$p = array();
	foreach ( array( 'title', 'description', 'priority', 'start', 'due' ) as $k ) {
		if ( array_key_exists( $k, $a ) ) {
			$p[ $k ] = $a[ $k ];
		}
	}
	if ( isset( $p['description'] ) && false === strpos( (string) $p['description'], '<' ) ) {
		$p['description'] = wpautop( esc_html( (string) $p['description'] ) );
	}
	if ( isset( $a['status'] ) ) {
		$p['status'] = flxg_tool_status_key( $list, $a['status'] );
	}
	if ( isset( $a['assignees'] ) ) {
		$p['assignees'] = flxg_tool_user_ids( (array) $a['assignees'] );
	}
	if ( isset( $a['tags'] ) ) {
		$p['tags']      = array();
		$p['tag_names'] = array_map( 'strval', (array) $a['tags'] );
	}
	if ( isset( $a['fields'] ) ) {
		$p['fields'] = flxg_tool_fields( $list['id'], (array) $a['fields'] );
	}
	return $p;
}

function flxg_tool_create_task( $a ) {
	$list = flxg_tool_list_or_fail( $a['list_id'] ?? 0, 'edit' );
	$p    = flxg_tool_task_patch( $list, $a );
	$p['list_id'] = $list['id'];
	if ( ! empty( $a['parent_id'] ) ) {
		$parent = flxg_tool_task_or_fail( $a['parent_id'], 'edit' );
		$p['parent_id'] = (int) $parent['id'];
	}
	$id = flxg_create_task( $p, get_current_user_id() );
	if ( is_wp_error( $id ) ) {
		flxg_tool_fail( $id->get_error_message() );
	}
	return array( 'created' => flxg_tool_task( flxg_get_task( $id ) ) );
}

function flxg_tool_update_task( $a ) {
	$row  = flxg_tool_task_or_fail( $a['task_id'] ?? 0, 'edit' );
	$list = flxg_get_list( (int) $row['list_id'] );
	if ( ! empty( $a['list_id'] ) && (int) $a['list_id'] !== (int) $row['list_id'] ) {
		$list = flxg_tool_list_or_fail( $a['list_id'], 'edit' );
	}
	$p = flxg_tool_task_patch( $list, $a );
	if ( (int) $list['id'] !== (int) $row['list_id'] ) {
		$p['list'] = $list['id'];
	}
	$t = flxg_update_task( (int) $row['id'], $p, get_current_user_id() );
	if ( is_wp_error( $t ) ) {
		flxg_tool_fail( $t->get_error_message() );
	}
	return array( 'updated' => flxg_tool_task( $t ) );
}

function flxg_tool_add_comment( $a ) {
	$row = flxg_tool_task_or_fail( $a['task_id'] ?? 0, 'comment' );
	$c   = flxg_add_comment( (int) $row['id'], (string) ( $a['text'] ?? '' ), array(), get_current_user_id() );
	if ( is_wp_error( $c ) ) {
		flxg_tool_fail( $c->get_error_message() );
	}
	return array( 'comment_id' => $c['id'], 'task_id' => (int) $row['id'] );
}

/** Aracı çalıştırır: [ok, data]. Flux AI yalnız oturumu açar; `flxg_use` ve liste kuralları burada denetlenir. */
function flxg_run_tool( $name, $args ) {
	if ( ! current_user_can( 'flxg_use' ) ) {
		return array( false, array( 'error' => 'This user is not allowed to use Flux Tasks.' ) );
	}
	$tools = flxg_tools();
	if ( ! isset( $tools[ $name ] ) ) {
		return array( false, array( 'error' => 'Unknown tool: ' . $name ) );
	}
	try {
		return array( true, call_user_func( $tools[ $name ]['run'], (array) $args ) );
	} catch ( Throwable $e ) {
		return array( false, array( 'error' => $e->getMessage() ) );
	}
}
