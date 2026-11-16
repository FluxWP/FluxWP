<?php
/**
 * Value objects of the tool list.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

final class FLXAI_Tool {

	/**
	 * @param list<string> $aliases Old names.
	 * @param list<string> $rivals  Sources that offer the same name.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $title,
		public readonly string $description,
		public readonly string $source,
		public readonly string $label,
		public readonly bool $readonly,
		public readonly bool $approval,
		public readonly string $cap,
		public readonly bool $mcp,
		public readonly bool $chat,
		public readonly bool $off,
		public readonly array $aliases,
		public readonly bool $dupe,
		public readonly array $rivals,
		public readonly bool $prefixed,
		public readonly int $calls,
		public readonly int $errors,
		public readonly int $avg_ms,
	) {}
}
