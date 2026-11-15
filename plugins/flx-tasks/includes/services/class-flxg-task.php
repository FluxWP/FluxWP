<?php
/**
 * Typed task and comment records (services layer). Built from the store's REST-shaped arrays,
 * so REST output stays byte-for-byte the same while GraphQL reads typed properties.
 *
 * @package FLX_Tasks
 */

defined( 'ABSPATH' ) || exit;

/**
 * Narrowing helpers for untyped store arrays.
 */
final class FLXG_Val {

	/**
	 * @param array<mixed> $a   Row.
	 * @param string       $key Key.
	 */
	public static function int( array $a, string $key ): int {
		$v = $a[ $key ] ?? 0;
		return is_numeric( $v ) ? (int) $v : 0;
	}

	/**
	 * @param array<mixed> $a   Row.
	 * @param string       $key Key.
	 */
	public static function str( array $a, string $key ): string {
		$v = $a[ $key ] ?? '';
		return is_scalar( $v ) ? (string) $v : '';
	}

	/**
	 * @param array<mixed> $a   Row.
	 * @param string       $key Key.
	 */
	public static function nstr( array $a, string $key ): ?string {
		$v = self::str( $a, $key );
		return '' === $v ? null : $v;
	}

	/**
	 * @param array<mixed> $a   Row.
	 * @param string       $key Key.
	 * @return list<int>
	 */
	public static function ints( array $a, string $key ): array {
		return self::int_list( $a[ $key ] ?? array() );
	}

	/**
	 * @param mixed $v List.
	 * @return list<int>
	 */
	public static function int_list( $v ): array {
		return is_array( $v ) ? array_values( array_map( static fn( $x ): int => is_numeric( $x ) ? (int) $x : 0, $v ) ) : array();
	}

	/**
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	public static function arr( $v ): array {
		if ( is_object( $v ) ) {
			$v = get_object_vars( $v );
		}
		return is_array( $v ) ? $v : array();
	}
}

/**
 * A task as Flux Tasks stores it.
 */
class FLXG_Task {

	/**
	 * @param list<int>           $assignee_ids Assigned user ids.
	 * @param list<int>           $tag_ids      Tag ids.
	 * @param array<mixed>        $field_values Custom field values keyed by field id.
	 * @param array<string,mixed> $raw          Store array (REST shape) it was built from.
	 */
	final public function __construct(
		public readonly int $id,
		public readonly int $list_id,
		public readonly int $parent_id,
		public readonly string $kind,
		public readonly string $title,
		public readonly string $status,
		public readonly int $priority,
		public readonly ?string $start_date,
		public readonly ?string $due_date,
		public readonly float $position,
		public readonly int $creator_id,
		public readonly ?string $created_at,
		public readonly ?string $updated_at,
		public readonly ?string $completed_at,
		public readonly array $assignee_ids,
		public readonly array $tag_ids,
		public readonly array $field_values,
		public readonly int $comment_count,
		public readonly int $file_count,
		public readonly int $subtask_count,
		public readonly bool $archived,
		public readonly bool $has_description,
		public readonly ?string $description,
		public readonly int $cover_id,
		public readonly int $agenda_count,
		public readonly int $decision_count,
		public readonly array $raw,
	) {}

	/**
	 * From the store array (flxg_format_tasks shape).
	 *
	 * @param mixed $t Task array.
	 * @return static|null
	 */
	public static function from_array( $t ): ?self {
		$t = FLXG_Val::arr( $t );
		$id = FLXG_Val::int( $t, 'id' );
		if ( $id <= 0 ) {
			return null;
		}
		/** @var array<string,mixed> $raw */
		$raw  = $t;
		$desc = array_key_exists( 'description', $t ) ? FLXG_Val::str( $t, 'description' ) : null;
		return new static(
			$id,
			FLXG_Val::int( $t, 'list' ),
			FLXG_Val::int( $t, 'parent' ),
			FLXG_Val::str( $t, 'kind' ) ?: 'task',
			FLXG_Val::str( $t, 'title' ),
			FLXG_Val::str( $t, 'status' ),
			FLXG_Val::int( $t, 'priority' ),
			FLXG_Val::nstr( $t, 'start' ),
			FLXG_Val::nstr( $t, 'due' ),
			is_numeric( $t['pos'] ?? null ) ? (float) $t['pos'] : 0.0,
			FLXG_Val::int( $t, 'creator' ),
			FLXG_Val::nstr( $t, 'created' ),
			FLXG_Val::nstr( $t, 'updated' ),
			FLXG_Val::nstr( $t, 'completed' ),
			FLXG_Val::ints( $t, 'assignees' ),
			FLXG_Val::ints( $t, 'tags' ),
			FLXG_Val::arr( $t['fields'] ?? array() ),
			FLXG_Val::int( $t, 'comments' ),
			FLXG_Val::int( $t, 'files' ),
			FLXG_Val::int( $t, 'subtasks' ),
			(bool) FLXG_Val::int( $t, 'archived' ),
			null !== $desc ? '' !== trim( wp_strip_all_tags( $desc ) ) : ! empty( $t['hasDesc'] ),
			$desc,
			FLXG_Val::int( $t, 'cover' ),
			FLXG_Val::int( $t, 'agenda' ),
			FLXG_Val::int( $t, 'decisions' ),
			$raw
		);
	}
}

/**
 * A task comment.
 */
final class FLXG_Task_Comment {

	/**
	 * @param array<string,mixed> $raw Store array (REST shape).
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $task_id,
		public readonly int $author_id,
		public readonly string $body,
		public readonly ?string $created_at,
		public readonly ?string $edited_at,
		public readonly array $raw,
	) {}

	/**
	 * From the store array (flxg_comment_out shape).
	 *
	 * @param mixed $c Comment array.
	 */
	public static function from_array( $c ): ?self {
		$c = FLXG_Val::arr( $c );
		if ( FLXG_Val::int( $c, 'id' ) <= 0 ) {
			return null;
		}
		/** @var array<string,mixed> $raw */
		$raw = $c;
		return new self(
			FLXG_Val::int( $c, 'id' ),
			FLXG_Val::int( $c, 'task' ),
			FLXG_Val::int( $c, 'user' ),
			FLXG_Val::str( $c, 'body' ),
			FLXG_Val::nstr( $c, 'created' ),
			FLXG_Val::nstr( $c, 'edited' ),
			$raw
		);
	}
}
