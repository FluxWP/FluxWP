<?php
/**
 * Flux AI GraphQL: the MCP server page (switch, keys, activity) and the MCP connections (external servers
 * this site uses). Key secrets and OAuth tokens are write-only: a key is returned once, when it is
 * created, and a connection only says whether a secret is saved.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------ Server and keys */

flx_gql_type(
	'FlxAiMcpServer',
	array(
		'description' => __( 'The MCP server of this site.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Mcp_Server::class,
			array(
				'enabled'     => array( 'Boolean!', __( 'The MCP server is on.', 'flx-ai' ) ),
				'oauth'       => array( 'Boolean!', __( 'Sign-in with OAuth is allowed.', 'flx-ai' ) ),
				'oauthActive' => array( 'Boolean!', __( 'OAuth sign-in is in effect (server on and OAuth allowed).', 'flx-ai' ), 'oauth_active' ),
				'legacy'      => array( 'Boolean!', __( 'An old Flux SEO version runs its own server.', 'flx-ai' ) ),
				'url'         => array( 'String!', __( 'Server address for assistants.', 'flx-ai' ) ),
				'restUrl'     => array( 'String!', __( 'The same server at its REST address.', 'flx-ai' ), 'rest_url' ),
				'toolCount'   => array( 'Int!', __( 'Registered tools.', 'flx-ai' ), 'tool_count' ),
				'rolesUrl'    => array( 'String!', __( 'Roles and access page; empty when Flux Roles is not active.', 'flx-ai' ), 'roles_url' ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiMcpKey',
	array(
		'description' => __( 'An access key or an OAuth connection to the MCP server.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Mcp_Key::class,
			array(
				'id'        => array( 'String!', __( 'Key id.', 'flx-ai' ) ),
				'name'      => array( 'String!', __( 'Name.', 'flx-ai' ) ),
				'type'      => array( 'String!', __( 'key or oauth.', 'flx-ai' ) ),
				'hint'      => array( 'String!', __( 'Last four characters of a key; empty for OAuth.', 'flx-ai' ) ),
				'userId'    => array( 'Int!', __( 'User whose permissions the key uses.', 'flx-ai' ), 'user_id' ),
				'userLogin' => array( 'String!', __( 'Login of that user; empty when the user is gone.', 'flx-ai' ), 'user_login' ),
				'canUse'    => array( 'Boolean!', __( 'The user may use MCP.', 'flx-ai' ), 'can_use' ),
				'tools'     => array( '[String!]!', __( 'Tools the key is limited to; empty means all.', 'flx-ai' ) ),
				'created'   => array( 'FlxDateTime!', __( 'When it was created.', 'flx-ai' ), 'created', 'time' ),
				'lastUsed'  => array( 'FlxDateTime', __( 'Last use; null when never.', 'flx-ai' ), 'last_used', 'time' ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiMcpToolChoice',
	array(
		'description' => __( 'A tool a key can be limited to.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Mcp_Tool_Choice::class,
			array(
				'name'     => array( 'String!', __( 'Tool name.', 'flx-ai' ) ),
				'title'    => array( 'String!', __( 'Tool title.', 'flx-ai' ) ),
				'readonly' => array( 'Boolean!', __( 'The tool only reads.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiMcpLogEntry',
	array(
		'description' => __( 'One recent call over MCP.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Mcp_Log_Entry::class,
			array(
				'time'      => array( 'FlxDateTime!', __( 'When it happened.', 'flx-ai' ), 'ts', 'time' ),
				'userLogin' => array( 'String!', __( 'User; empty when the user is gone.', 'flx-ai' ), 'user_login' ),
				'tool'      => array( 'String!', __( 'Tool name.', 'flx-ai' ) ),
				'target'    => array( 'String!', __( 'What the call was about.', 'flx-ai' ) ),
				'ok'        => array( 'Boolean!', __( 'The call succeeded.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiMcpOverview',
	array(
		'description' => __( 'The MCP page: server, keys, tools a key can be limited to and recent activity.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Mcp_Overview::class,
			array(
				'server'  => array( 'FlxAiMcpServer!', __( 'The server.', 'flx-ai' ) ),
				'keys'    => array( '[FlxAiMcpKey!]!', __( 'Keys and OAuth connections.', 'flx-ai' ) ),
				'choices' => array( '[FlxAiMcpToolChoice!]!', __( 'Tools a key can be limited to.', 'flx-ai' ) ),
				'log'     => array( '[FlxAiMcpLogEntry!]!', __( 'The last 20 calls, newest first.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_query(
	'flxAiMcp',
	array(
		'description' => __( 'The MCP page: server state, keys and recent activity.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'type'        => 'FlxAiMcpOverview!',
		'resolve'     => static function (): FLXAI_Mcp_Overview {
			return FLXAI_Mcp_Service::overview();
		},
	)
);

$flxai_mcp_out = array(
	'overview' => array(
		'type'        => 'FlxAiMcpOverview',
		'description' => __( 'The MCP page after the change; empty when the input was refused.', 'flx-ai' ),
	),
);

flx_gql_mutation(
	'flxAiMcpServerUpdate',
	array(
		'description' => __( 'Switches the MCP server and OAuth sign-in on or off.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'enabled' => array( 'type' => 'Boolean!', 'description' => __( 'The server is on.', 'flx-ai' ) ),
			'oauth'   => array( 'type' => 'Boolean!', 'description' => __( 'Assistants may sign in with OAuth.', 'flx-ai' ) ),
		),
		'output'      => $flxai_mcp_out,
		'resolve'     => static function ( array $input ) {
			return array( 'overview' => FLXAI_Mcp_Service::set_server( ! empty( $input['enabled'] ), ! empty( $input['oauth'] ) ) );
		},
	)
);

flx_gql_mutation(
	'flxAiMcpKeyCreate',
	array(
		'description' => __( 'Creates an access key for the signed-in user. The key is in the result once and cannot be read again.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'name'  => array( 'type' => 'String', 'description' => __( 'Name, for example the assistant it is for.', 'flx-ai' ) ),
			'tools' => array( 'type' => '[String!]', 'description' => __( 'Limit the key to these tools; leave out for all.', 'flx-ai' ) ),
		),
		'output'      => $flxai_mcp_out + array(
			'key' => array(
				'type'        => 'String',
				'description' => __( 'The new key. Shown only in this result.', 'flx-ai' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Mcp_Service::create_key( flx_gql_str( $input['name'] ?? '' ), FLXAI_Cast::strings( $input['tools'] ?? array() ) );
			return array( 'overview' => $r->overview, 'key' => $r->key );
		},
	)
);

flx_gql_mutation(
	'flxAiMcpKeyRevoke',
	array(
		'description' => __( 'Revokes a key or an OAuth connection. Assistants using it stop working.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'id' => array( 'type' => 'String!', 'description' => __( 'Key id.', 'flx-ai' ) ),
		),
		'output'      => $flxai_mcp_out,
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Mcp_Service::revoke_key( flx_gql_str( $input['id'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'overview' => $r );
		},
	)
);

/* ------------------------------------------------------------------ Connections */

flx_gql_type(
	'FlxAiConnectionTool',
	array(
		'description' => __( 'A tool a connected MCP server offers.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Connection_Tool::class,
			array(
				'name'        => array( 'String!', __( 'Name on the server.', 'flx-ai' ) ),
				'toolName'    => array( 'String!', __( 'Name in chats, tasks and agents.', 'flx-ai' ), 'tool_name' ),
				'title'       => array( 'String!', __( 'Title.', 'flx-ai' ) ),
				'description' => array( 'String!', __( 'What the tool does.', 'flx-ai' ) ),
				'readonly'    => array( 'Boolean!', __( 'The server marks the tool read-only.', 'flx-ai' ) ),
				'destructive' => array( 'Boolean!', __( 'The server marks the tool destructive.', 'flx-ai' ) ),
				'permission'  => array( 'String!', __( 'allow, ask or off.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiConnection',
	array(
		'description' => __( 'A connected MCP server. Secrets are never returned.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Connection::class,
			array(
				'id'              => array( 'String!', __( 'Short id; also the tool name prefix.', 'flx-ai' ) ),
				'name'            => array( 'String!', __( 'Name.', 'flx-ai' ) ),
				'url'             => array( 'String!', __( 'Server address.', 'flx-ai' ) ),
				'auth'            => array( 'String!', __( 'none, bearer, header or oauth.', 'flx-ai' ) ),
				'header'          => array( 'String!', __( 'Header name of the custom header sign-in.', 'flx-ai' ) ),
				'scope'           => array( 'String!', __( 'personal or shared.', 'flx-ai' ) ),
				'ownerId'         => array( 'Int!', __( 'User who added it.', 'flx-ai' ), 'owner_id' ),
				'ownerLogin'      => array( 'String!', __( 'Login of that user.', 'flx-ai' ), 'owner_login' ),
				'roles'           => array( '[String!]!', __( 'Roles that may use a shared connection.', 'flx-ai' ) ),
				'enabled'         => array( 'Boolean!', __( 'Its tools are offered.', 'flx-ai' ) ),
				'editable'        => array( 'Boolean!', __( 'The signed-in user may change it (not another user\'s personal connection).', 'flx-ai' ) ),
				'hasSecret'       => array( 'Boolean!', __( 'A key or token is saved.', 'flx-ai' ), 'has_secret' ),
				'clientId'        => array( 'String!', __( 'OAuth client id entered by hand; empty when none or registered automatically.', 'flx-ai' ), 'client_id' ),
				'hasClientSecret' => array( 'Boolean!', __( 'An OAuth client secret is saved.', 'flx-ai' ), 'has_client_secret' ),
				'signedIn'        => array( 'Boolean!', __( 'OAuth sign-in is done.', 'flx-ai' ), 'signed_in' ),
				'serverName'      => array( 'String!', __( 'Name the server reports.', 'flx-ai' ), 'server_name' ),
				'serverVersion'   => array( 'String!', __( 'Version the server reports.', 'flx-ai' ), 'server_version' ),
				'serverProtocol'  => array( 'String!', __( 'MCP protocol version.', 'flx-ai' ), 'server_protocol' ),
				'checked'         => array( 'FlxDateTime', __( 'Last time the tool list was loaded; null when never.', 'flx-ai' ), 'checked', 'time' ),
				'error'           => array( 'String!', __( 'Last error; empty when fine.', 'flx-ai' ) ),
				'status'          => array( 'String!', __( 'off, error, signin or connected.', 'flx-ai' ) ),
				'prefix'          => array( 'String!', __( 'Tool name pattern.', 'flx-ai' ) ),
				'toolsOn'         => array( 'Int!', __( 'Tools that are not off.', 'flx-ai' ), 'tools_on' ),
				'tools'           => array( '[FlxAiConnectionTool!]!', __( 'Tools the server offers.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiConnectionsScreen',
	array(
		'description' => __( 'The connections page.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Connections_Screen::class,
			array(
				'connections' => array( '[FlxAiConnection!]!', __( 'Connections the user may see.', 'flx-ai' ) ),
				'roles'       => array( '[FlxAiOption!]!', __( 'Roles a shared connection can be opened to (administrators always may).', 'flx-ai' ) ),
				'callbackUrl' => array( 'String!', __( 'Redirect address to register at an OAuth provider.', 'flx-ai' ), 'callback_url' ),
			)
		),
	)
);

flx_gql_query(
	'flxAiConnections',
	array(
		'description' => __( 'The MCP connections and what their form needs.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'type'        => 'FlxAiConnectionsScreen!',
		'resolve'     => static function (): FLXAI_Connections_Screen {
			return FLXAI_Connections_Service::screen();
		},
	)
);

$flxai_conn_out = array(
	'connection' => array(
		'type'        => 'FlxAiConnection',
		'description' => __( 'The connection after the change; empty when the input was refused.', 'flx-ai' ),
	),
);
$flxai_conn_id  = array(
	'id' => array( 'type' => 'String!', 'description' => __( 'Connection id.', 'flx-ai' ) ),
);

flx_gql_mutation(
	'flxAiConnectionSave',
	array(
		'description' => __( 'Adds a connection, or changes one when `id` is given. Fields you leave out keep their value; a secret you leave out or send empty keeps the saved one. A connection that is on loads its tools when saved; the outcome is in `error`.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'id'           => array( 'type' => 'String', 'description' => __( 'Connection to change; leave out to add one.', 'flx-ai' ) ),
			'name'         => array( 'type' => 'String', 'description' => __( 'Name; empty takes the host name.', 'flx-ai' ) ),
			'url'          => array( 'type' => 'String', 'description' => __( 'Streamable HTTP address (https). Required when adding.', 'flx-ai' ) ),
			'auth'         => array( 'type' => 'String', 'description' => __( 'none, bearer, header or oauth.', 'flx-ai' ) ),
			'header'       => array( 'type' => 'String', 'description' => __( 'Header name for the custom header sign-in.', 'flx-ai' ) ),
			'secret'       => array( 'type' => 'String', 'description' => __( 'Key or token (write-only; stored encrypted).', 'flx-ai' ) ),
			'scope'        => array( 'type' => 'String', 'description' => __( 'personal or shared.', 'flx-ai' ) ),
			'roles'        => array( 'type' => '[String!]', 'description' => __( 'Roles that may use a shared connection.', 'flx-ai' ) ),
			'enabled'      => array( 'type' => 'Boolean', 'description' => __( 'Offer the server\'s tools.', 'flx-ai' ) ),
			'clientId'     => array( 'type' => 'String', 'description' => __( 'OAuth client id (optional; registered automatically when the server allows).', 'flx-ai' ) ),
			'clientSecret' => array( 'type' => 'String', 'description' => __( 'OAuth client secret (write-only; stored encrypted).', 'flx-ai' ) ),
		),
		'output'      => $flxai_conn_out,
		'resolve'     => static function ( array $input ) {
			$id = flx_gql_str( $input['id'] ?? '' );
			unset( $input['id'] );
			if ( array_key_exists( 'clientId', $input ) ) {
				$input['client_id'] = $input['clientId'];
			}
			if ( array_key_exists( 'clientSecret', $input ) ) {
				$input['client_secret'] = $input['clientSecret'];
			}
			unset( $input['clientId'], $input['clientSecret'] );
			$r = FLXAI_Connections_Service::save( $input, $id );
			return $r instanceof WP_Error ? $r : array( 'connection' => $r );
		},
	)
);

flx_gql_mutation(
	'flxAiConnectionDelete',
	array(
		'description' => __( 'Deletes a connection. Its tools disappear from chats, tasks and agents.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => $flxai_conn_id,
		'output'      => array( 'deleted' => array( 'type' => 'Boolean', 'description' => __( 'Done.', 'flx-ai' ) ) ),
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Connections_Service::delete( flx_gql_str( $input['id'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'deleted' => true );
		},
	)
);

flx_gql_mutation(
	'flxAiConnectionRefresh',
	array(
		'description' => __( 'Tests the connection and reloads its tool list. A failure is in the connection\'s `error`.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => $flxai_conn_id,
		'output'      => $flxai_conn_out,
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Connections_Service::refresh( flx_gql_str( $input['id'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'connection' => $r );
		},
	)
);

flx_gql_mutation(
	'flxAiConnectionSignIn',
	array(
		'description' => __( 'Starts the OAuth sign-in. Send the browser to the returned address; the provider sends it back to the connections page.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => $flxai_conn_id,
		'output'      => array( 'url' => array( 'type' => 'String', 'description' => __( 'The provider\'s sign-in page.', 'flx-ai' ) ) ),
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Connections_Service::sign_in_url( flx_gql_str( $input['id'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'url' => $r );
		},
	)
);

flx_gql_mutation(
	'flxAiConnectionSignOut',
	array(
		'description' => __( 'Forgets the OAuth sign-in of a connection.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => $flxai_conn_id,
		'output'      => $flxai_conn_out,
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Connections_Service::sign_out( flx_gql_str( $input['id'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'connection' => $r );
		},
	)
);

flx_gql_input(
	'FlxAiConnectionPermissionInput',
	array(
		'description' => __( 'The permission of one tool.', 'flx-ai' ),
		'fields'      => array(
			'tool'       => array( 'type' => 'String!', 'description' => __( 'Tool name on the server.', 'flx-ai' ) ),
			'permission' => array( 'type' => 'String!', 'description' => __( 'allow (read-only tools only), ask or off.', 'flx-ai' ) ),
		),
	)
);

flx_gql_mutation(
	'flxAiConnectionPermissions',
	array(
		'description' => __( 'Sets the permission of every tool of a connection. Tools you leave out go back to "ask".', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => $flxai_conn_id + array(
			'permissions' => array( 'type' => '[FlxAiConnectionPermissionInput!]!', 'description' => __( 'Permissions to set.', 'flx-ai' ) ),
		),
		'output'      => $flxai_conn_out,
		'resolve'     => static function ( array $input ) {
			$perms = array();
			foreach ( flx_gql_arr( $input['permissions'] ?? array() ) as $row ) {
				$row = flx_gql_arr( $row );
				$perms[ flx_gql_str( $row['tool'] ?? '' ) ] = flx_gql_str( $row['permission'] ?? '' );
			}
			$r = FLXAI_Connections_Service::set_permissions( flx_gql_str( $input['id'] ?? '' ), $perms );
			return $r instanceof WP_Error ? $r : array( 'connection' => $r );
		},
	)
);
