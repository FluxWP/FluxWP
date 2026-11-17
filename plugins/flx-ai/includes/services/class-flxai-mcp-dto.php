<?php
/**
 * Value objects of the MCP server page: the server, its keys and the recent activity.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** The MCP server of this site. */
final class FLXAI_Mcp_Server {

	public function __construct(
		public readonly bool $enabled,
		public readonly bool $oauth,
		public readonly bool $oauth_active,
		public readonly bool $legacy,
		public readonly string $url,
		public readonly string $rest_url,
		public readonly int $tool_count,
		public readonly string $roles_url,
	) {}
}

/** An access key or an OAuth connection. */
final class FLXAI_Mcp_Key {

	/** @param list<string> $tools Tools the key is limited to; empty means all. */
	public function __construct(
		public readonly string $id,
		public readonly string $name,
		public readonly string $type,
		public readonly string $hint,
		public readonly int $user_id,
		public readonly string $user_login,
		public readonly bool $can_use,
		public readonly array $tools,
		public readonly int $created,
		public readonly ?int $last_used,
	) {}
}

/** A tool a key can be limited to. */
final class FLXAI_Mcp_Tool_Choice {

	public function __construct(
		public readonly string $name,
		public readonly string $title,
		public readonly bool $readonly,
	) {}
}

/** One entry of the activity log. */
final class FLXAI_Mcp_Log_Entry {

	public function __construct(
		public readonly int $ts,
		public readonly string $user_login,
		public readonly string $tool,
		public readonly string $target,
		public readonly bool $ok,
	) {}
}

/** Everything the MCP page shows. */
final class FLXAI_Mcp_Overview {

	/**
	 * @param list<FLXAI_Mcp_Key>         $keys    Keys.
	 * @param list<FLXAI_Mcp_Tool_Choice> $choices Tools a key can be limited to.
	 * @param list<FLXAI_Mcp_Log_Entry>   $log     Recent activity, newest first.
	 */
	public function __construct(
		public readonly FLXAI_Mcp_Server $server,
		public readonly array $keys,
		public readonly array $choices,
		public readonly array $log,
	) {}
}

/** A freshly created key: the secret is in the result once and is never stored. */
final class FLXAI_Mcp_New_Key {

	public function __construct(
		public readonly string $key,
		public readonly FLXAI_Mcp_Overview $overview,
	) {}
}
