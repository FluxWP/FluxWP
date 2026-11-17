<?php
/**
 * Flux AI GraphQL: chat history of the signed-in user (list, search, transcript, rename, archive, delete).
 * Sending, streaming and approving inside a chat stay on the chat's own REST/SSE protocol.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

flx_gql_type(
	'FlxAiChatSummary',
	array(
		'description' => __( 'A chat in the history list.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Chat_Summary::class,
			array(
				'id'       => array( 'Int!', __( 'Chat id.', 'flx-ai' ) ),
				'title'    => array( 'String!', __( 'Title; empty until the assistant names it.', 'flx-ai' ) ),
				'archived' => array( 'Boolean!', __( 'Archived.', 'flx-ai' ) ),
				'status'   => array( 'String!', __( 'idle, running or approval.', 'flx-ai' ) ),
				'updated'  => array( 'FlxDateTime!', __( 'Last activity.', 'flx-ai' ), 'updated', 'time' ),
				'snippet'  => array( 'String!', __( 'Matching text when a search hit the content, otherwise empty.', 'flx-ai' ) ),
				'task'     => array( 'String!', __( 'Title of the scheduled task this chat belongs to; empty for a normal chat.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiChatPage',
	array(
		'description' => __( 'One page of the chat history.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Chat_Page::class,
			array(
				'items'   => array( '[FlxAiChatSummary!]!', __( 'Chats, most recent first.', 'flx-ai' ) ),
				'hasMore' => array( 'Boolean!', __( 'More chats follow.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiChatMessage',
	array(
		'description' => __( 'A message of a chat transcript.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Chat_Message::class,
			array(
				'index' => array( 'Int!', __( 'Position in the chat.', 'flx-ai' ) ),
				'role'  => array( 'String!', __( 'user, assistant or tool.', 'flx-ai' ) ),
				'text'  => array( 'String!', __( 'Message text; for a tool result the (shortened) output.', 'flx-ai' ) ),
				'time'  => array( 'FlxDateTime', __( 'When it was written, when known.', 'flx-ai' ), 'ts', 'time' ),
				'tool'  => array( 'String!', __( 'Tool name of a tool result; empty otherwise.', 'flx-ai' ) ),
				'ok'    => array( 'Boolean', __( 'Whether a tool result succeeded.', 'flx-ai' ) ),
				'error' => array( 'String!', __( 'Error of a failed tool call; empty otherwise.', 'flx-ai' ) ),
				'tools' => array( 'String!', __( 'Tools an assistant message called, comma separated.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiChat',
	array(
		'description' => __( 'A chat with its transcript.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Chat_Detail::class,
			array(
				'summary'  => array( 'FlxAiChatSummary!', __( 'Title, status and time.', 'flx-ai' ) ),
				'model'    => array( 'String!', __( 'Model chosen for this chat; empty for the default.', 'flx-ai' ) ),
				'total'    => array( 'Int!', __( 'Stored messages including internal ones.', 'flx-ai' ) ),
				'messages' => array( '[FlxAiChatMessage!]!', __( 'Transcript: user, assistant and tool messages.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_query(
	'flxAiChats',
	array(
		'description' => __( 'The signed-in user\'s chat history. Search looks in titles and message text.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'type'        => 'FlxAiChatPage!',
		'args'        => array(
			'q'        => array(
				'type'        => 'String',
				'description' => __( 'Search text.', 'flx-ai' ),
			),
			'archived' => array(
				'type'        => 'Int',
				'description' => __( '0 active chats (default), 1 archived, -1 all.', 'flx-ai' ),
			),
			'limit'    => array(
				'type'        => 'Int',
				'description' => __( 'Page size, 1 to 100 (default 30).', 'flx-ai' ),
			),
			'offset'   => array(
				'type'        => 'Int',
				'description' => __( 'Chats to skip.', 'flx-ai' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): FLXAI_Chat_Page {
			return FLXAI_Chats_Service::page(
				flx_gql_str( $args['q'] ?? '' ),
				flx_gql_int( $args['archived'] ?? 0 ),
				flx_gql_int( $args['limit'] ?? 30 ),
				flx_gql_int( $args['offset'] ?? 0 )
			);
		},
	)
);

flx_gql_query(
	'flxAiChat',
	array(
		'description' => __( 'One chat of the signed-in user with its transcript.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'type'        => 'FlxAiChat!',
		'args'        => array(
			'id' => array(
				'type'        => 'Int!',
				'description' => __( 'Chat id.', 'flx-ai' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): FLXAI_Chat_Detail {
			return flxai_gql_ok( FLXAI_Chats_Service::get( flx_gql_int( $args['id'] ?? 0 ) ) );
		},
	)
);

flx_gql_mutation(
	'flxAiChatUpdate',
	array(
		'description' => __( 'Renames and/or archives a chat; a field left out stays as it is.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'id'       => array(
				'type'        => 'Int!',
				'description' => __( 'Chat id.', 'flx-ai' ),
			),
			'title'    => array(
				'type'        => 'String',
				'description' => __( 'New title.', 'flx-ai' ),
			),
			'archived' => array(
				'type'        => 'Boolean',
				'description' => __( 'Archive (true) or restore (false).', 'flx-ai' ),
			),
		),
		'output'      => array(
			'chat' => array(
				'type'        => 'FlxAiChatSummary',
				'description' => __( 'The chat after the change.', 'flx-ai' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$title    = array_key_exists( 'title', $input ) && null !== $input['title'] ? flx_gql_str( $input['title'] ) : null;
			$archived = array_key_exists( 'archived', $input ) && null !== $input['archived'] ? (bool) $input['archived'] : null;
			$r        = FLXAI_Chats_Service::update( flx_gql_int( $input['id'] ?? 0 ), $title, $archived );
			return $r instanceof WP_Error ? $r : array( 'chat' => $r );
		},
	)
);

flx_gql_mutation(
	'flxAiChatDelete',
	array(
		'description' => __( 'Deletes a chat with its files. This cannot be undone.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'id' => array(
				'type'        => 'Int!',
				'description' => __( 'Chat id.', 'flx-ai' ),
			),
		),
		'output'      => array(
			'deleted' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Deleted.', 'flx-ai' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Chats_Service::delete( flx_gql_int( $input['id'] ?? 0 ) );
			return $r instanceof WP_Error ? $r : array( 'deleted' => $r );
		},
	)
);
