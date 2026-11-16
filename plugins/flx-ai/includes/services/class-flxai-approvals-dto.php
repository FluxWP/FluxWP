<?php
/**
 * Value objects of the approval queue.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** One action waiting for approval. */
final class FLXAI_Approval {

	public function __construct(
		public readonly int $chat_id,
		public readonly string $chat_title,
		public readonly string $call_id,
		public readonly string $tool,
		public readonly string $title,
		public readonly string $args,
		public readonly string $note,
		public readonly int $ts,
		public readonly int $task_id,
		public readonly string $task_title,
	) {}
}

/** One changed file of a change request. */
final class FLXAI_Change_File {

	public function __construct(
		public readonly string $path,
		public readonly string $op,
		public readonly int $add,
		public readonly int $del,
	) {}
}

/** An open workspace change request. */
final class FLXAI_Change {

	/** @param list<FLXAI_Change_File> $files Files. */
	public function __construct(
		public readonly int $id,
		public readonly string $title,
		public readonly int $chat_id,
		public readonly int $task_id,
		public readonly string $task_title,
		public readonly int $created,
		public readonly array $files,
	) {}
}

/** The queue. */
final class FLXAI_Approval_Queue {

	/**
	 * @param list<FLXAI_Approval> $items   Actions.
	 * @param list<FLXAI_Change>   $changes Change requests.
	 */
	public function __construct(
		public readonly array $items,
		public readonly array $changes,
		public readonly int $count,
	) {}
}

/** What a decision did. */
final class FLXAI_Approval_Result {

	public function __construct(
		public readonly string $state,
		public readonly FLXAI_Approval_Queue $queue,
	) {}
}
