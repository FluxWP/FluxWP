<?php
/**
 * Task service: permission checks and task operations in one place. REST (rest.php) and GraphQL
 * (includes/graphql/) both call it, so the two APIs cannot drift apart. Results are the store's
 * REST-shaped arrays; GraphQL wraps them in FLXG_Task.
 *
 * @package FLX_Tasks
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type FlxgTaskFilter array{list?: int, space?: int, mine?: bool, q?: string, archived?: bool, parent?: int|null, ids?: list<int>}
 */
final class FLXG_Task_Service {

	/**
	 * Same as flxg_error(), typed.
	 *
	 * @param string $code    Error code.
	 * @param string $message Translated message.
	 * @param int    $status  HTTP status.
	 */
	private static function err( string $code, string $message, int $status = 400 ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * Lists in scope: one list, one project (space) or everything the user sees. Null: the list is not visible.
	 *
	 * @param int $list  List id (0: none).
	 * @param int $space Project id (0: none).
	 * @param int $uid   User.
	 * @return list<int>|null
	 */
	public static function scope_lists( int $list, int $space, int $uid ): ?array {
		if ( $list > 0 ) {
			$l = FLXG_Val::arr( flxg_get_list( $list ) );
			return $l && flxg_can_list( $l, 'view', $uid ) ? array( FLXG_Val::int( $l, 'id' ) ) : null;
		}
		$ids = $space > 0 ? flxg_visible_list_ids( $space, $uid ) : flxg_visible_list_ids( 0, $uid );
		return FLXG_Val::int_list( $ids );
	}

	/**
	 * One page of tasks.
	 *
	 * @param FlxgTaskFilter $f      Filter.
	 * @param int            $offset Offset.
	 * @param int            $limit  Page size (+1 to detect a next page).
	 * @param int            $uid    User.
	 * @return array{items: list<array<string,mixed>>, total: int}|WP_Error
	 */
	public static function page( array $f, int $offset, int $limit, int $uid ) {
		$lists = self::scope_lists( $f['list'] ?? 0, $f['space'] ?? 0, $uid );
		if ( null === $lists ) {
			return self::err( 'flxg_404', __( 'List not found or you don\'t have access.', 'flx-tasks' ), 404 );
		}
		$q = array(
			'lists'    => $lists,
			'archived' => ! empty( $f['archived'] ),
			'q'        => $f['q'] ?? '',
			'limit'    => $limit,
			'offset'   => $offset,
		);
		if ( ! empty( $f['mine'] ) ) {
			$q['assignee'] = $uid;
		}
		if ( array_key_exists( 'parent', $f ) ) {
			$q['parent'] = $f['parent'];
		}
		if ( ! empty( $f['ids'] ) ) {
			$q['ids'] = array_slice( $f['ids'], 0, 200 );
		}
		$items = array();
		foreach ( (array) flxg_query_tasks( $q ) as $t ) {
			/** @var array<string,mixed> $row */
			$row     = FLXG_Val::arr( $t );
			$items[] = $row;
		}
		$count = flxg_query_tasks( array( 'count' => true, 'limit' => 0, 'offset' => 0 ) + $q );
		return array(
			'items' => $items,
			'total' => is_numeric( $count ) ? (int) $count : count( $items ),
		);
	}

	/**
	 * Tasks by id, archived ones too (no permission check: the caller filters with can()).
	 *
	 * @param list<int> $ids       Ids.
	 * @param bool      $with_desc Include the description.
	 * @return array<int, array<string,mixed>> Keyed by id.
	 */
	public static function by_ids( array $ids, bool $with_desc = false ): array {
		if ( ! $ids ) {
			return array();
		}
		$lists = array();
		foreach ( (array) flxg_all_lists() as $l ) {
			$lists[] = FLXG_Val::int( FLXG_Val::arr( $l ), 'id' );
		}
		$out = array();
		foreach ( array( false, true ) as $archived ) {
			foreach ( (array) flxg_query_tasks(
				array(
					'lists'     => $lists,
					'ids'       => array_slice( $ids, 0, 500 ),
					'limit'     => 500,
					'archived'  => $archived,
					'with_desc' => $with_desc,
				)
			) as $t ) {
				/** @var array<string,mixed> $row */
				$row                                = FLXG_Val::arr( $t );
				$out[ FLXG_Val::int( $row, 'id' ) ] = $row;
			}
		}
		return $out;
	}

	/**
	 * Whether the user may see / act on the task.
	 *
	 * @param int    $id   Task id.
	 * @param string $need view|comment|edit|admin.
	 * @param int    $uid  User.
	 */
	public static function can( int $id, string $need, int $uid ): bool {
		return (bool) flxg_can_task( $id, $need, $uid );
	}

	/**
	 * Task with comments, activity, files, watchers and children (the task screen).
	 *
	 * @param int $id  Task id.
	 * @param int $uid User.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function detail( int $id, int $uid ) {
		if ( ! self::can( $id, 'view', $uid ) ) {
			return self::err( 'flxg_404', __( 'Task not found or you don\'t have access.', 'flx-tasks' ), 404 );
		}
		/** @var array<string,mixed> $t */
		$t = FLXG_Val::arr( flxg_task_detail( $id, $uid ) );
		return $t;
	}

	/**
	 * Marks the task's notifications read.
	 *
	 * @param int $id  Task id.
	 * @param int $uid User.
	 * @return int Unread notifications left.
	 */
	public static function mark_read( int $id, int $uid ): int {
		if ( self::can( $id, 'view', $uid ) ) {
			flxg_mark_read( $uid, null, $id );
		}
		$n = flxg_unread_count( $uid );
		return is_numeric( $n ) ? (int) $n : 0;
	}

	/**
	 * Creates a task.
	 *
	 * @param array<string,mixed> $b   list, title, parent, status, priority, start, due, assignees, tags, fields, description …
	 * @param int                 $uid User.
	 * @return array<string,mixed>|WP_Error The task (without description).
	 */
	public static function create( array $b, int $uid ) {
		$list = FLXG_Val::arr( flxg_get_list( FLXG_Val::int( $b, 'list' ) ) );
		if ( ! $list || ! flxg_can_list( $list, 'edit', $uid ) ) {
			return self::err( 'flxg_forbidden', __( 'You don\'t have permission to add tasks to this list.', 'flx-tasks' ), 403 );
		}
		$b['list_id'] = FLXG_Val::int( $list, 'id' );
		if ( ! empty( $b['parent'] ) ) {
			$b['parent_id'] = self::can( FLXG_Val::int( $b, 'parent' ), 'edit', $uid ) ? FLXG_Val::int( $b, 'parent' ) : 0;
		}
		$id = flxg_create_task( $b, $uid );
		if ( $id instanceof WP_Error ) {
			return $id;
		}
		/** @var array<string,mixed> $t */
		$t = FLXG_Val::arr( flxg_get_task( is_numeric( $id ) ? (int) $id : 0, false ) );
		return $t;
	}

	/**
	 * Updates a task (see flxg_update_task for the keys).
	 *
	 * @param int                 $id  Task id.
	 * @param array<string,mixed> $b   Patch; "detail" returns the full task screen data.
	 * @param int                 $uid User.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function update( int $id, array $b, int $uid ) {
		$row = FLXG_Val::arr( flxg_get_task_row( $id ) );
		if ( ! $row || ! flxg_can_task( $row, 'edit', $uid ) ) {
			return self::err( 'flxg_forbidden', __( 'You don\'t have permission to edit this task.', 'flx-tasks' ), 403 );
		}
		if ( isset( $b['list'] ) && FLXG_Val::int( $b, 'list' ) !== FLXG_Val::int( $row, 'list_id' ) && ! flxg_can_list( FLXG_Val::int( $b, 'list' ), 'edit', $uid ) ) {
			return self::err( 'flxg_forbidden', __( 'You don\'t have permission to add tasks to the target list.', 'flx-tasks' ), 403 );
		}
		$t = flxg_update_task( $id, $b, $uid );
		if ( $t instanceof WP_Error ) {
			return $t;
		}
		if ( ! empty( $b['detail'] ) ) {
			return self::detail( $id, $uid );
		}
		/** @var array<string,mixed> $out */
		$out = FLXG_Val::arr( $t );
		unset( $out['description'] );
		return $out;
	}

	/**
	 * Deletes a task (and its subtasks).
	 *
	 * @param int $id  Task id.
	 * @param int $uid User.
	 * @return int|WP_Error Deleted id.
	 */
	public static function delete( int $id, int $uid ) {
		if ( ! self::can( $id, 'edit', $uid ) ) {
			return self::err( 'flxg_forbidden', __( 'You don\'t have permission to delete this task.', 'flx-tasks' ), 403 );
		}
		flxg_delete_task( $id, $uid );
		return $id;
	}

	/**
	 * Starts or stops watching a task.
	 *
	 * @param int  $id    Task id.
	 * @param bool $watch Watch.
	 * @param int  $uid   User.
	 * @return list<int>|WP_Error Watcher ids.
	 */
	public static function watch( int $id, bool $watch, int $uid ) {
		if ( ! self::can( $id, 'view', $uid ) ) {
			return self::err( 'flxg_404', __( 'Task not found or you don\'t have access.', 'flx-tasks' ), 404 );
		}
		if ( $watch ) {
			flxg_add_watcher( $id, $uid );
		} else {
			flxg_remove_watcher( $id, $uid );
		}
		return self::watchers( $id );
	}

	/**
	 * Watcher ids.
	 *
	 * @param int $id Task id.
	 * @return list<int>
	 */
	public static function watchers( int $id ): array {
		return FLXG_Val::int_list( flxg_watchers( $id ) );
	}

	/**
	 * Adds a comment.
	 *
	 * @param int       $id    Task id.
	 * @param string    $body  Comment (limited HTML, @mentions).
	 * @param list<int> $files Pending file ids uploaded by the user.
	 * @param int       $uid   User.
	 * @return array<string,mixed>|WP_Error The comment.
	 */
	public static function comment( int $id, string $body, array $files, int $uid ) {
		if ( ! self::can( $id, 'comment', $uid ) ) {
			return self::err( 'flxg_forbidden', __( 'You don\'t have permission to comment on this task.', 'flx-tasks' ), 403 );
		}
		$c = flxg_add_comment( $id, $body, $files, $uid );
		if ( $c instanceof WP_Error ) {
			return $c;
		}
		/** @var array<string,mixed> $out */
		$out = FLXG_Val::arr( $c );
		return $out;
	}

	/**
	 * Comments of a task, oldest first.
	 *
	 * @param int $id Task id.
	 * @return list<array<string,mixed>>
	 */
	public static function comments( int $id ): array {
		$out = array();
		foreach ( (array) flxg_get_comments( $id ) as $c ) {
			/** @var array<string,mixed> $row */
			$row   = FLXG_Val::arr( $c );
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * The user's role on the task's list ('' when none).
	 *
	 * @param int $list_id List id.
	 * @param int $uid     User.
	 */
	public static function role( int $list_id, int $uid ): string {
		$r = flxg_list_role( $list_id, $uid );
		return is_string( $r ) ? $r : '';
	}

	/**
	 * Direct children (subtasks, agenda items, decisions).
	 *
	 * @param int $id      Task id.
	 * @param int $list_id List id.
	 * @return list<array<string,mixed>>
	 */
	public static function children( int $id, int $list_id ): array {
		$out = array();
		foreach ( (array) flxg_query_tasks( array( 'lists' => array( $list_id ), 'parent' => $id ) ) as $t ) {
			/** @var array<string,mixed> $row */
			$row   = FLXG_Val::arr( $t );
			$out[] = $row;
		}
		return $out;
	}
}
