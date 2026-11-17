<?php
/**
 * Value objects of the chat history.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** One chat in the history list. */
final class FLXAI_Chat_Summary {

	public function __construct(
		public readonly int $id,
		public readonly string $title,
		public readonly bool $archived,
		public readonly string $status,
		public readonly int $updated,
		public readonly string $snippet,
		public readonly string $task,
	) {}
}

/** The history list page. */
final class FLXAI_Chat_Page {

	/** @param list<FLXAI_Chat_Summary> $items Chats. */
	public function __construct(
		public readonly array $items,
		public readonly bool $has_more,
	) {}
}

/** One message of a transcript (read-only). */
final class FLXAI_Chat_Message {

	public function __construct(
		public readonly int $index,
		public readonly string $role,
		public readonly string $text,
		public readonly ?int $ts,
		public readonly string $tool,
		public readonly ?bool $ok,
		public readonly string $error,
		public readonly string $tools,
	) {}
}

/** A chat with its transcript. */
final class FLXAI_Chat_Detail {

	/** @param list<FLXAI_Chat_Message> $messages Transcript. */
	public function __construct(
		public readonly FLXAI_Chat_Summary $summary,
		public readonly string $model,
		public readonly int $total,
		public readonly array $messages,
	) {}
}
