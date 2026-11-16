<?php
/**
 * Value objects of the MCP connections (external MCP servers this site uses). Secrets are never part of
 * them: a connection only says whether a secret is saved.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** A tool a connected server offers, with its permission. */
final class FLXAI_Connection_Tool {

	public function __construct(
		public readonly string $name,
		public readonly string $tool_name,
		public readonly string $title,
		public readonly string $description,
		public readonly bool $readonly,
		public readonly bool $destructive,
		public readonly string $permission,
	) {}
}

/** A connected MCP server. */
final class FLXAI_Connection {

	/**
	 * @param list<string>                $roles Roles that may use a shared connection.
	 * @param list<FLXAI_Connection_Tool> $tools Cached tool list.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $name,
		public readonly string $url,
		public readonly string $auth,
		public readonly string $header,
		public readonly string $scope,
		public readonly int $owner_id,
		public readonly string $owner_login,
		public readonly array $roles,
		public readonly bool $enabled,
		public readonly bool $editable,
		public readonly bool $has_secret,
		public readonly string $client_id,
		public readonly bool $has_client_secret,
		public readonly bool $signed_in,
		public readonly string $server_name,
		public readonly string $server_version,
		public readonly string $server_protocol,
		public readonly ?int $checked,
		public readonly string $error,
		public readonly string $status,
		public readonly string $prefix,
		public readonly int $tools_on,
		public readonly array $tools,
	) {}
}

/** The connections page: the list and what the form needs. */
final class FLXAI_Connections_Screen {

	/**
	 * @param list<FLXAI_Connection> $connections Connections the user may see.
	 * @param list<FLXAI_Option>     $roles       Roles a shared connection can be opened to.
	 */
	public function __construct(
		public readonly array $connections,
		public readonly array $roles,
		public readonly string $callback_url,
	) {}
}
