<?php
/**
 * Flux Tasks GraphQL schema (Flux GraphQL kit). Loaded on the flx_gql_register action, i.e. only
 * inside a GraphQL request and only when Flux GraphQL is active. Resolvers call FLXG_Task_Service.
 *
 * Pilot scope: one task (screen data), task lists (cursor pages), create / update / delete,
 * watch, comment, notifications read. The board / calendar bulk read and sync stay on REST.
 *
 * @package FLX_Tasks
 */

defined( 'ABSPATH' ) || exit;

/**
 * Task record that is also a GraphQL Node (global ID, node(id:) lookup).
 */
final class FLXG_Task_Node extends FLXG_Task implements FLXGQL_Node {

	#[\Override]
	public function gql_type(): string {
		return 'FlxTask';
	}

	#[\Override]
	public function gql_id(): int {
		return $this->id;
	}
}

/**
 * Batches task lookups of one request; hides tasks the user may not see.
 */
final class FLXG_Task_Loader extends FLXGQL_Loader {

	/**
	 * @param array<int,int> $ids Ids.
	 * @return array<int,FLXG_Task_Node>
	 */
	#[\Override]
	protected function fetch( array $ids ): array {
		$out = array();
		foreach ( FLXG_Task_Service::by_ids( array_values( $ids ) ) as $id => $row ) {
			$node = FLXG_Task_Node::from_array( $row );
			if ( $node instanceof FLXG_Task_Node ) {
				$out[ $id ] = $node;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $record Record.
	 */
	#[\Override]
	protected function can_see( $record ): bool {
		return $record instanceof FLXG_Task && FLXG_Task_Service::can( $record->id, 'view', get_current_user_id() );
	}
}

/**
 * Deferred task lookup through the request's loader.
 *
 * @param \WPGraphQL\AppContext $context Context.
 * @param int                   $id      Task id.
 */
function flxg_gql_load( \WPGraphQL\AppContext $context, int $id ): \GraphQL\Deferred {
	return $context->get_loader( 'FlxTask' )->load_deferred( $id );
}

/**
 * Store row → Node (null when empty).
 *
 * @param mixed $row Store array.
 */
function flxg_gql_node( $row ): ?FLXG_Task_Node {
	$n = FLXG_Task_Node::from_array( $row );
	return $n instanceof FLXG_Task_Node ? $n : null;
}

/**
 * List of store rows → Nodes.
 *
 * @param array<mixed> $rows Rows.
 * @return list<FLXG_Task_Node>
 */
function flxg_gql_nodes( array $rows ): array {
	return array_values( array_filter( array_map( 'flxg_gql_node', $rows ) ) );
}

/**
 * Service result → value, or the WP_Error thrown as a typed GraphQL error.
 *
 * @template T
 * @param T|WP_Error $r Result.
 * @return T
 * @throws FLXGQL_Error On WP_Error.
 */
function flxg_gql_ok( $r ) {
	if ( $r instanceof WP_Error ) {
		throw flx_gql_from_wp_error( $r );
	}
	return $r;
}

/**
 * ISO time from the store → FlxDateTime.
 *
 * @param string|null $iso "2026-11-15T10:00:00Z".
 * @return array{iso:string,timestamp:int,label:string,relative:string}|null
 */
function flxg_gql_time( ?string $iso ): ?array {
	$ts = null !== $iso ? strtotime( $iso ) : false;
	return false === $ts ? null : flx_gql_datetime( $ts );
}

/**
 * Task screen data (comments, activity, files, subtasks), computed once per task and GraphQL request:
 * the same single call the REST task screen makes. Cleared when a request starts and after every write.
 *
 * @param int $id Task id (0 with $clear: only clear).
 * @return array<mixed>
 */
function flxg_gql_detail( int $id, bool $clear = false ): array {
	/** @var array<int,array<mixed>> $memo */
	static $memo = array();
	if ( $clear ) {
		$memo = array();
		return array();
	}
	if ( ! isset( $memo[ $id ] ) ) {
		$d           = FLXG_Task_Service::detail( $id, get_current_user_id() );
		$memo[ $id ] = $d instanceof WP_Error ? array() : $d;
	}
	return $memo[ $id ];
}

foreach ( array( 'init_graphql_request', 'graphql_before_execute' ) as $flxg_gql_hook ) {
	add_action(
		$flxg_gql_hook,
		static function (): void {
			flxg_gql_detail( 0, true );
		}
	);
}
unset( $flxg_gql_hook );

/**
 * A list inside the task screen data.
 *
 * @param int    $id  Task id.
 * @param string $key comments_list, activity, attachments, subtree, children.
 * @return list<array<mixed>>
 */
function flxg_gql_detail_list( int $id, string $key ): array {
	$v = flxg_gql_detail( $id )[ $key ] ?? array();
	return is_array( $v ) ? array_values( array_map( array( 'FLXG_Val', 'arr' ), $v ) ) : array();
}

/**
 * Input → store patch keys (REST names). Only keys present in the input are set.
 *
 * @param array<string,mixed> $in Mutation input.
 * @return array<string,mixed>
 */
function flxg_gql_patch( array $in ): array {
	$map = array(
		'title'       => 'title',
		'description' => 'description',
		'status'      => 'status',
		'priority'    => 'priority',
		'startDate'   => 'start',
		'dueDate'     => 'due',
		'assigneeIds' => 'assignees',
		'tagIds'      => 'tags',
		'listId'      => 'list',
		'parentId'    => 'parent',
		'archived'    => 'archived',
	);
	$out = array();
	foreach ( $map as $from => $to ) {
		if ( array_key_exists( $from, $in ) ) {
			$v          = $in[ $from ];
			$out[ $to ] = null === $v && in_array( $to, array( 'start', 'due' ), true ) ? '' : $v;
		}
	}
	if ( array_key_exists( 'kind', $in ) && is_string( $in['kind'] ) ) {
		$out['kind'] = $in['kind'];
	}
	return $out;
}

flx_gql_enum(
	'FlxTaskKind',
	array(
		'description' => __( 'What a task row is.', 'flx-tasks' ),
		'values'      => array(
			'TASK'     => array( 'value' => 'task', 'description' => __( 'A task.', 'flx-tasks' ) ),
			'AGENDA'   => array( 'value' => 'agenda', 'description' => __( 'An agenda item of a meeting.', 'flx-tasks' ) ),
			'DECISION' => array( 'value' => 'decision', 'description' => __( 'A decision taken in a meeting.', 'flx-tasks' ) ),
		),
	)
);

flx_gql_enum(
	'FlxTaskRole',
	array(
		'description' => __( 'What the current user may do on a list.', 'flx-tasks' ),
		'values'      => array(
			'VIEW'    => array( 'value' => 'view', 'description' => __( 'See tasks.', 'flx-tasks' ) ),
			'COMMENT' => array( 'value' => 'comment', 'description' => __( 'See and comment.', 'flx-tasks' ) ),
			'EDIT'    => array( 'value' => 'edit', 'description' => __( 'Create and edit tasks.', 'flx-tasks' ) ),
			'ADMIN'   => array( 'value' => 'admin', 'description' => __( 'Manage the list and its members.', 'flx-tasks' ) ),
		),
	)
);

flx_gql_type(
	'FlxTaskFile',
	array(
		'description' => __( 'A file attached to a task, a comment or a custom field.', 'flx-tasks' ),
		'fields'      => array(
			'databaseId' => array(
				'type'        => 'Int!',
				'description' => __( 'File ID.', 'flx-tasks' ),
				'resolve'     => static fn( array $f ): int => FLXG_Val::int( $f, 'id' ),
			),
			'taskId'     => array(
				'type'        => 'Int!',
				'description' => __( 'Task the file belongs to.', 'flx-tasks' ),
				'resolve'     => static fn( array $f ): int => FLXG_Val::int( $f, 'task' ),
			),
			'commentId'  => array(
				'type'        => 'Int',
				'description' => __( 'Comment the file was added with; empty for task attachments.', 'flx-tasks' ),
				'resolve'     => static fn( array $f ): ?int => FLXG_Val::int( $f, 'comment' ) ?: null,
			),
			'fieldId'    => array(
				'type'        => 'Int',
				'description' => __( 'Custom field the file belongs to; empty otherwise.', 'flx-tasks' ),
				'resolve'     => static fn( array $f ): ?int => FLXG_Val::int( $f, 'field' ) ?: null,
			),
			'context'    => array(
				'type'        => 'String!',
				'description' => __( 'Where the file is used: task, comment, field, description.', 'flx-tasks' ),
				'resolve'     => static fn( array $f ): string => FLXG_Val::str( $f, 'context' ),
			),
			'uploaderId' => array(
				'type'        => 'Int!',
				'description' => __( 'User who uploaded the file.', 'flx-tasks' ),
				'resolve'     => static fn( array $f ): int => FLXG_Val::int( $f, 'user' ),
			),
			'name'       => array(
				'type'        => 'String!',
				'description' => __( 'File name.', 'flx-tasks' ),
				'resolve'     => static fn( array $f ): string => FLXG_Val::str( $f, 'name' ),
			),
			'mime'       => array(
				'type'        => 'String!',
				'description' => __( 'MIME type.', 'flx-tasks' ),
				'resolve'     => static fn( array $f ): string => FLXG_Val::str( $f, 'mime' ),
			),
			'size'       => array(
				'type'        => 'Int!',
				'description' => __( 'Size in bytes.', 'flx-tasks' ),
				'resolve'     => static fn( array $f ): int => FLXG_Val::int( $f, 'size' ),
			),
			'image'      => array(
				'type'        => 'Boolean!',
				'description' => __( 'The file is an image.', 'flx-tasks' ),
				'resolve'     => static fn( array $f ): bool => ! empty( $f['image'] ),
			),
			'hasThumb'   => array(
				'type'        => 'Boolean!',
				'description' => __( 'A small preview exists.', 'flx-tasks' ),
				'resolve'     => static fn( array $f ): bool => ! empty( $f['thumb'] ),
			),
			'width'      => array(
				'type'        => 'Int!',
				'description' => __( 'Image width in pixels (0 for other files).', 'flx-tasks' ),
				'resolve'     => static fn( array $f ): int => FLXG_Val::int( $f, 'w' ),
			),
			'height'     => array(
				'type'        => 'Int!',
				'description' => __( 'Image height in pixels (0 for other files).', 'flx-tasks' ),
				'resolve'     => static fn( array $f ): int => FLXG_Val::int( $f, 'h' ),
			),
			'createdAt'  => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'Upload time.', 'flx-tasks' ),
				'resolve'     => static fn( array $f ): ?array => flxg_gql_time( FLXG_Val::nstr( $f, 'created' ) ),
			),
		),
	)
);

flx_gql_type(
	'FlxTaskActivity',
	array(
		'description' => __( 'One entry of a task history.', 'flx-tasks' ),
		'fields'      => array(
			'databaseId' => array(
				'type'        => 'Int!',
				'description' => __( 'Entry ID.', 'flx-tasks' ),
				'resolve'     => static fn( array $a ): int => FLXG_Val::int( $a, 'id' ),
			),
			'userId'     => array(
				'type'        => 'Int!',
				'description' => __( 'User who made the change.', 'flx-tasks' ),
				'resolve'     => static fn( array $a ): int => FLXG_Val::int( $a, 'user' ),
			),
			'action'     => array(
				'type'        => 'String!',
				'description' => __( 'What happened: created, status, assigned, due, moved …', 'flx-tasks' ),
				'resolve'     => static fn( array $a ): string => FLXG_Val::str( $a, 'action' ),
			),
			'data'       => array(
				'type'        => 'FlxJSON!',
				'description' => __( 'Details of the change; the keys depend on the action.', 'flx-tasks' ),
				'resolve'     => static fn( array $a ): object => (object) FLXG_Val::arr( $a['data'] ?? array() ),
			),
			'createdAt'  => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'When it happened.', 'flx-tasks' ),
				'resolve'     => static fn( array $a ): ?array => flxg_gql_time( FLXG_Val::nstr( $a, 'created' ) ),
			),
		),
	)
);

flx_gql_type(
	'FlxTaskFileGroup',
	array(
		'description' => __( 'Files of one child task (agenda item previews).', 'flx-tasks' ),
		'fields'      => array(
			'taskId' => array(
				'type'        => 'Int!',
				'description' => __( 'Child task.', 'flx-tasks' ),
				'resolve'     => static fn( array $g ): int => FLXG_Val::int( $g, 'task' ),
			),
			'files'  => array(
				'type'        => '[FlxTaskFile!]!',
				'description' => __( 'Its files.', 'flx-tasks' ),
				'resolve'     => static fn( array $g ): array => array_values( array_map( array( 'FLXG_Val', 'arr' ), FLXG_Val::arr( $g['files'] ?? array() ) ) ),
			),
		),
	)
);

flx_gql_type(
	'FlxTaskComment',
	array(
		'description' => __( 'A comment on a task.', 'flx-tasks' ),
		'fields'      => array(
			'databaseId' => array(
				'type'        => 'Int!',
				'description' => __( 'Comment ID.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task_Comment $c ): int => $c->id,
			),
			'taskId'     => array(
				'type'        => 'Int!',
				'description' => __( 'Task the comment belongs to.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task_Comment $c ): int => $c->task_id,
			),
			'authorId'   => array(
				'type'        => 'Int!',
				'description' => __( 'User who wrote the comment.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task_Comment $c ): int => $c->author_id,
			),
			'body'       => array(
				'type'        => 'String!',
				'description' => __( 'Comment text (limited HTML).', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task_Comment $c ): string => $c->body,
			),
			'createdAt'  => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'When the comment was written.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task_Comment $c ): ?array => flxg_gql_time( $c->created_at ),
			),
			'editedAt'   => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'Last edit; empty when never edited.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task_Comment $c ): ?array => $c->edited_at !== $c->created_at ? flxg_gql_time( $c->edited_at ) : null,
			),
			'files'      => array(
				'type'        => '[FlxTaskFile!]!',
				'description' => __( 'Files added with the comment.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task_Comment $c ): array => array_values( array_map( array( 'FLXG_Val', 'arr' ), FLXG_Val::arr( $c->raw['files'] ?? array() ) ) ),
			),
		),
	)
);

flx_gql_type(
	'FlxTask',
	array(
		'description' => __( 'A task, agenda item or decision.', 'flx-tasks' ),
		'node'        => FLXG_Task_Loader::class,
		'fields'      => array(
			'title'           => array(
				'type'        => 'String!',
				'description' => __( 'Task name.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): string => $t->title,
			),
			'kind'            => array(
				'type'        => 'FlxTaskKind!',
				'description' => __( 'Task, agenda item or decision.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): string => $t->kind,
			),
			'status'          => array(
				'type'        => 'String!',
				'description' => __( 'Status key; each list defines its own statuses.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): string => $t->status,
			),
			'priority'        => array(
				'type'        => 'Int!',
				'description' => __( 'Priority: 0 none, 1 urgent, 2 high, 3 normal, 4 low.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): int => $t->priority,
			),
			'listId'          => array(
				'type'        => 'Int!',
				'description' => __( 'List the task belongs to.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): int => $t->list_id,
			),
			'parentId'        => array(
				'type'        => 'Int',
				'description' => __( 'Parent task; empty for a top-level task.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): ?int => $t->parent_id > 0 ? $t->parent_id : null,
			),
			'startDate'       => array(
				'type'        => 'String',
				'description' => __( 'Start date (YYYY-MM-DD, optionally with THH:MM); empty when not set.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): ?string => $t->start_date,
			),
			'dueDate'         => array(
				'type'        => 'String',
				'description' => __( 'Due date (YYYY-MM-DD, optionally with THH:MM); empty when not set.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): ?string => $t->due_date,
			),
			'position'        => array(
				'type'        => 'Float!',
				'description' => __( 'Sort position inside the list.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): float => $t->position,
			),
			'creatorId'       => array(
				'type'        => 'Int!',
				'description' => __( 'User who created the task.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): int => $t->creator_id,
			),
			'createdAt'       => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'When the task was created.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): ?array => flxg_gql_time( $t->created_at ),
			),
			'updatedAt'       => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'Last change.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): ?array => flxg_gql_time( $t->updated_at ),
			),
			'completedAt'     => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'When the task was completed; empty while open.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): ?array => flxg_gql_time( $t->completed_at ),
			),
			'assigneeIds'     => array(
				'type'        => '[Int!]!',
				'description' => __( 'Assigned users.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): array => $t->assignee_ids,
			),
			'tagIds'          => array(
				'type'        => '[Int!]!',
				'description' => __( 'Tags.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): array => $t->tag_ids,
			),
			'fieldValues'     => array(
				'type'        => 'FlxJSON!',
				'description' => __( 'Custom field values keyed by field ID; the list defines the fields and their types.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): object => (object) $t->field_values,
			),
			'commentCount'    => array(
				'type'        => 'Int!',
				'description' => __( 'Number of comments.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): int => $t->comment_count,
			),
			'fileCount'       => array(
				'type'        => 'Int!',
				'description' => __( 'Number of attached files.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): int => $t->file_count,
			),
			'subtaskCount'    => array(
				'type'        => 'Int!',
				'description' => __( 'Number of open subtasks.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): int => $t->subtask_count,
			),
			'archived'        => array(
				'type'        => 'Boolean!',
				'description' => __( 'The task is archived.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): bool => $t->archived,
			),
			'hasDescription'  => array(
				'type'        => 'Boolean!',
				'description' => __( 'The task has a description.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): bool => $t->has_description,
			),
			'description'     => array(
				'type'        => 'String!',
				'description' => __( 'Description (limited HTML). Ask for it on one task, not on lists.', 'flx-tasks' ),
				'resolve'     => static function ( FLXG_Task $t ): string {
					if ( null !== $t->description ) {
						return $t->description;
					}
					$full = FLXG_Task_Service::by_ids( array( $t->id ), true );
					return FLXG_Val::str( $full[ $t->id ] ?? array(), 'description' );
				},
			),
			'coverFileId'     => array(
				'type'        => 'Int',
				'description' => __( 'Image file shown as the board card cover; empty when none.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): ?int => $t->cover_id > 0 ? $t->cover_id : null,
			),
			'agendaCount'     => array(
				'type'        => 'Int!',
				'description' => __( 'Number of agenda items (meetings).', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): int => $t->agenda_count,
			),
			'decisionCount'   => array(
				'type'        => 'Int!',
				'description' => __( 'Number of decisions (meetings).', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): int => $t->decision_count,
			),
			'attachments'     => array(
				'type'        => '[FlxTaskFile!]!',
				'description' => __( 'Files of the task and its comments. Ask for it on one task.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): array => flxg_gql_detail_list( $t->id, 'attachments' ),
			),
			'activity'        => array(
				'type'        => '[FlxTaskActivity!]!',
				'description' => __( 'History, oldest first. Ask for it on one task.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): array => flxg_gql_detail_list( $t->id, 'activity' ),
			),
			'subtree'         => array(
				'type'        => '[FlxTask!]!',
				'description' => __( 'Subtasks below the direct children (up to 10 levels, 300 tasks). Ask for it on one task.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): array => flxg_gql_nodes( flxg_gql_detail_list( $t->id, 'subtree' ) ),
			),
			'childFiles'      => array(
				'type'        => '[FlxTaskFileGroup!]!',
				'description' => __( 'Files of the agenda items (meeting previews). Ask for it on one task.', 'flx-tasks' ),
				'resolve'     => static function ( FLXG_Task $t ): array {
					$out = array();
					foreach ( FLXG_Val::arr( flxg_gql_detail( $t->id )['childFiles'] ?? array() ) as $task => $files ) {
						$out[] = array(
							'task'  => (int) $task,
							'files' => $files,
						);
					}
					return $out;
				},
			),
			'depth'           => array(
				'type'        => 'Int!',
				'description' => __( 'Nesting level (0: top-level task).', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): int => FLXG_Val::int( flxg_gql_detail( $t->id ), 'depth' ),
			),
			'depthLimit'      => array(
				'type'        => 'Int!',
				'description' => __( 'Deepest subtask level the list allows.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): int => FLXG_Val::int( flxg_gql_detail( $t->id ), 'depthLimit' ),
			),
			'role'            => array(
				'type'        => 'FlxTaskRole',
				'description' => __( 'What the current user may do on this task.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): ?string => FLXG_Task_Service::role( $t->list_id, get_current_user_id() ) ?: null,
			),
			'watching'        => array(
				'type'        => 'Boolean!',
				'description' => __( 'The current user follows the task.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): bool => in_array( get_current_user_id(), FLXG_Task_Service::watchers( $t->id ), true ),
			),
			'watcherIds'      => array(
				'type'        => '[Int!]!',
				'description' => __( 'Users who follow the task.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): array => FLXG_Val::int_list( flxg_gql_detail( $t->id )['watchers'] ?? FLXG_Task_Service::watchers( $t->id ) ),
			),
			'comments'        => array(
				'type'        => '[FlxTaskComment!]!',
				'description' => __( 'Comments, oldest first. Ask for it on one task.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): array => array_values( array_filter( array_map( array( FLXG_Task_Comment::class, 'from_array' ), flxg_gql_detail_list( $t->id, 'comments_list' ) ) ) ),
			),
			'children'        => array(
				'type'        => '[FlxTask!]!',
				'description' => __( 'Direct subtasks, agenda items and decisions. Ask for it on one task.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t ): array => flxg_gql_nodes( flxg_gql_detail_list( $t->id, 'children' ) ),
			),
			'parent'          => array(
				'type'        => 'FlxTask',
				'description' => __( 'Parent task; empty for a top-level task or when it is not visible.', 'flx-tasks' ),
				'resolve'     => static fn( FLXG_Task $t, $args, \WPGraphQL\AppContext $context ) => $t->parent_id > 0 ? flxg_gql_load( $context, $t->parent_id ) : null,
			),
		),
	)
);

flx_gql_connection( 'FlxTask', __( 'A page of tasks.', 'flx-tasks' ) );

flx_gql_query(
	'flxTask',
	array(
		'type'        => 'FlxTask',
		'description' => __( 'One task; empty when it does not exist or the user may not see it.', 'flx-tasks' ),
		'cap'         => 'flxg_use',
		'args'        => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or task number.', 'flx-tasks' ),
			),
		),
		'resolve'     => static fn( $root, array $args, \WPGraphQL\AppContext $context ) => flxg_gql_load( $context, flx_gql_decode_id( $args['id'] ?? '', 'FlxTask' ) ),
	)
);

flx_gql_query(
	'flxTasks',
	array(
		'type'        => 'FlxTaskConnection!',
		'description' => __( 'Tasks the user can see, in list order.', 'flx-tasks' ),
		'cap'         => 'flxg_use',
		'args'        => flx_gql_connection_args(
			array(
				'listId'   => array(
					'type'        => 'Int',
					'description' => __( 'Only this list.', 'flx-tasks' ),
				),
				'spaceId'  => array(
					'type'        => 'Int',
					'description' => __( 'Only lists of this project.', 'flx-tasks' ),
				),
				'parentId' => array(
					'type'        => 'Int',
					'description' => __( 'Only children of this task (0: top-level tasks).', 'flx-tasks' ),
				),
				'mine'     => array(
					'type'        => 'Boolean',
					'description' => __( 'Only tasks assigned to the current user.', 'flx-tasks' ),
				),
				'search'   => array(
					'type'        => 'String',
					'description' => __( 'Words in the title or description, or a task number.', 'flx-tasks' ),
				),
				'archived' => array(
					'type'        => 'Boolean',
					'description' => __( 'Archived tasks instead of active ones.', 'flx-tasks' ),
				),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
			/** @var array<string,mixed> $args */
			$f = array(
				'list'     => flx_gql_int( $args['listId'] ?? 0 ),
				'space'    => flx_gql_int( $args['spaceId'] ?? 0 ),
				'mine'     => ! empty( $args['mine'] ),
				'q'        => flx_gql_str( $args['search'] ?? '' ),
				'archived' => ! empty( $args['archived'] ),
			);
			if ( isset( $args['parentId'] ) ) {
				$f['parent'] = flx_gql_int( $args['parentId'] );
			}
			flx_gql_cache_tag( 'flx-tasks:tasks' );
			return flx_gql_paginate(
				$args,
				static function ( int $offset, int $limit ) use ( $f ): array {
					$page = flxg_gql_ok( FLXG_Task_Service::page( $f, $offset, $limit, get_current_user_id() ) );
					return array(
						'items' => flxg_gql_nodes( $page['items'] ),
						'total' => $page['total'],
					);
				}
			);
		},
	)
);

$flxg_gql_task_input = array(
	'title'       => array(
		'type'        => 'String',
		'description' => __( 'Task name.', 'flx-tasks' ),
	),
	'description' => array(
		'type'        => 'String',
		'description' => __( 'Description (limited HTML).', 'flx-tasks' ),
	),
	'status'      => array(
		'type'        => 'String',
		'description' => __( 'Status key of the list.', 'flx-tasks' ),
	),
	'priority'    => array(
		'type'        => 'Int',
		'description' => __( 'Priority: 0 none, 1 urgent, 2 high, 3 normal, 4 low.', 'flx-tasks' ),
	),
	'startDate'   => array(
		'type'        => 'String',
		'description' => __( 'Start date (YYYY-MM-DD); null clears it.', 'flx-tasks' ),
	),
	'dueDate'     => array(
		'type'        => 'String',
		'description' => __( 'Due date (YYYY-MM-DD); null clears it.', 'flx-tasks' ),
	),
	'assigneeIds' => array(
		'type'        => '[Int!]',
		'description' => __( 'Assigned users (replaces the current ones).', 'flx-tasks' ),
	),
	'tagIds'      => array(
		'type'        => '[Int!]',
		'description' => __( 'Tags (replaces the current ones).', 'flx-tasks' ),
	),
	'parentId'    => array(
		'type'        => 'Int',
		'description' => __( 'Parent task.', 'flx-tasks' ),
	),
	'kind'        => array(
		'type'        => 'FlxTaskKind',
		'description' => __( 'Task, agenda item or decision.', 'flx-tasks' ),
	),
);

flx_gql_mutation(
	'flxTaskCreate',
	array(
		'description' => __( 'Creates a task.', 'flx-tasks' ),
		'cap'         => 'flxg_use',
		'input'       => array(
			'listId' => array(
				'type'        => 'Int!',
				'description' => __( 'List to add the task to.', 'flx-tasks' ),
			),
		) + $flxg_gql_task_input,
		'output'      => array(
			'task' => array(
				'type'        => 'FlxTask',
				'description' => __( 'The new task.', 'flx-tasks' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			$t = flxg_gql_ok( FLXG_Task_Service::create( flxg_gql_patch( $input ), get_current_user_id() ) );
			flxg_gql_detail( 0, true );
			flx_gql_purge( array( 'flx-tasks:tasks' ) );
			return array( 'task' => flxg_gql_node( $t ) );
		},
	)
);

flx_gql_mutation(
	'flxTaskUpdate',
	array(
		'description' => __( 'Changes a task. Fields left out stay as they are.', 'flx-tasks' ),
		'cap'         => 'flxg_use',
		'input'       => array(
			'id'       => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or task number.', 'flx-tasks' ),
			),
			'listId'   => array(
				'type'        => 'Int',
				'description' => __( 'Move the task to this list.', 'flx-tasks' ),
			),
			'archived' => array(
				'type'        => 'Boolean',
				'description' => __( 'Archive or restore the task.', 'flx-tasks' ),
			),
		) + $flxg_gql_task_input,
		'output'      => array(
			'task' => array(
				'type'        => 'FlxTask',
				'description' => __( 'The task after the change.', 'flx-tasks' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			$id = flx_gql_decode_id( $input['id'] ?? '', 'FlxTask' );
			$t  = flxg_gql_ok( FLXG_Task_Service::update( $id, flxg_gql_patch( $input ), get_current_user_id() ) );
			flxg_gql_detail( 0, true );
			flx_gql_purge( array( 'flx-tasks:tasks' ) );
			return array( 'task' => flxg_gql_node( $t ) );
		},
	)
);

flx_gql_mutation(
	'flxTaskDelete',
	array(
		'description' => __( 'Deletes a task and its subtasks.', 'flx-tasks' ),
		'cap'         => 'flxg_use',
		'input'       => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or task number.', 'flx-tasks' ),
			),
		),
		'output'      => array(
			'deletedId' => array(
				'type'        => 'Int',
				'description' => __( 'Number of the deleted task.', 'flx-tasks' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			$id = flxg_gql_ok( FLXG_Task_Service::delete( flx_gql_decode_id( $input['id'] ?? '', 'FlxTask' ), get_current_user_id() ) );
			flxg_gql_detail( 0, true );
			flx_gql_purge( array( 'flx-tasks:tasks' ) );
			return array( 'deletedId' => $id );
		},
	)
);

flx_gql_mutation(
	'flxTaskWatch',
	array(
		'description' => __( 'Starts or stops following a task.', 'flx-tasks' ),
		'cap'         => 'flxg_use',
		'input'       => array(
			'id'    => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or task number.', 'flx-tasks' ),
			),
			'watch' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Follow (true) or stop following (false).', 'flx-tasks' ),
			),
		),
		'output'      => array(
			'watcherIds' => array(
				'type'        => '[Int!]!',
				'description' => __( 'Users who follow the task now.', 'flx-tasks' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			$w = flxg_gql_ok( FLXG_Task_Service::watch( flx_gql_decode_id( $input['id'] ?? '', 'FlxTask' ), ! empty( $input['watch'] ), get_current_user_id() ) );
			flxg_gql_detail( 0, true );
			return array( 'watcherIds' => $w );
		},
	)
);

flx_gql_mutation(
	'flxTaskCommentCreate',
	array(
		'description' => __( 'Adds a comment to a task.', 'flx-tasks' ),
		'cap'         => 'flxg_use',
		'input'       => array(
			'taskId'  => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or task number.', 'flx-tasks' ),
			),
			'body'    => array(
				'type'        => 'String!',
				'description' => __( 'Comment text (limited HTML, @mentions).', 'flx-tasks' ),
			),
			'fileIds' => array(
				'type'        => '[Int!]',
				'description' => __( 'Files uploaded for this comment.', 'flx-tasks' ),
			),
		),
		'output'      => array(
			'comment' => array(
				'type'        => 'FlxTaskComment',
				'description' => __( 'The new comment.', 'flx-tasks' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			$files = FLXG_Val::int_list( $input['fileIds'] ?? array() );
			$c     = flxg_gql_ok( FLXG_Task_Service::comment( flx_gql_decode_id( $input['taskId'] ?? '', 'FlxTask' ), flx_gql_str( $input['body'] ?? '' ), $files, get_current_user_id() ) );
			flxg_gql_detail( 0, true );
			return array( 'comment' => FLXG_Task_Comment::from_array( $c ) );
		},
	)
);

flx_gql_mutation(
	'flxTaskNotificationsRead',
	array(
		'description' => __( 'Marks the notifications of a task read (the task was opened).', 'flx-tasks' ),
		'cap'         => 'flxg_use',
		'input'       => array(
			'taskId' => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or task number.', 'flx-tasks' ),
			),
		),
		'output'      => array(
			'unread' => array(
				'type'        => 'Int!',
				'description' => __( 'Unread notifications left.', 'flx-tasks' ),
			),
		),
		'resolve'     => static fn( array $input ): array => array( 'unread' => FLXG_Task_Service::mark_read( flx_gql_decode_id( $input['taskId'] ?? '', 'FlxTask' ), get_current_user_id() ) ),
	)
);

unset( $flxg_gql_task_input );
