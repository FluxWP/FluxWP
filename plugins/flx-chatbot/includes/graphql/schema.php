<?php
/**
 * Flux AI Chatbot GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a
 * GraphQL request. Resolvers call FLXCB_Chat_Service and FLXCB_Settings_Service; the Chatbot settings
 * and Site chats screens (wp-admin and FluxWP app) read and write only through these fields.
 *
 * The settings values themselves are the generated `flxSettings.chatbot` group (flx_settings_schema);
 * saving goes through flxChatbotSettingsSave so the same cleaning as the classic form runs.
 *
 * The visitor side of the chat (session, message stream, poll, handoff, contact, upload, file, rate,
 * close, typing) is the public protocol of the widget and stays on REST (docs/graphql/ISTISNALAR.md).
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Conversation that is also a GraphQL Node (global ID, node(id:) lookup).
 */
final class FLXCB_Conversation_Node extends FLXCB_Conversation implements FLXGQL_Node {

	public static function of( FLXCB_Conversation $c ): self {
		return new self(
			$c->id,
			$c->title,
			$c->name,
			$c->email,
			$c->phone,
			$c->status,
			$c->unread,
			$c->rating,
			$c->note,
			$c->lead,
			$c->order_id,
			$c->order_url,
			$c->page_url,
			$c->page_title,
			$c->user_id,
			$c->agent,
			$c->flux_chat_url,
			$c->summary,
			$c->last_ts,
			$c->online,
			$c->typing,
			$c->last
		);
	}

	#[\Override]
	public function gql_type(): string {
		return 'FlxChatbotConversation';
	}

	#[\Override]
	public function gql_id(): int {
		return $this->id;
	}
}

/**
 * Batches conversation lookups of one request; only agents see conversations.
 */
final class FLXCB_Conversation_Loader extends FLXGQL_Loader {

	/**
	 * @param array<int,int> $ids Ids.
	 * @return array<int,FLXCB_Conversation_Node>
	 */
	#[\Override]
	protected function fetch( array $ids ): array {
		$out = array();
		foreach ( $ids as $id ) {
			$c = FLXCB_Chat_Service::find( $id );
			if ( null !== $c ) {
				$out[ $id ] = FLXCB_Conversation_Node::of( $c );
			}
		}
		return $out;
	}

	/**
	 * @param mixed $record Record.
	 */
	#[\Override]
	protected function can_see( $record ): bool {
		return $record instanceof FLXCB_Conversation && flxcb_can_reply();
	}
}

/**
 * Service result → value, or the WP_Error thrown as a typed GraphQL error.
 *
 * @template T
 * @param T|WP_Error $r Result.
 * @return T
 * @throws FLXGQL_Error On WP_Error.
 */
function flxcb_gql_ok( $r ) {
	if ( $r instanceof WP_Error ) {
		throw flx_gql_from_wp_error( $r );
	}
	return $r;
}

/**
 * Typed parent value of a field.
 *
 * @template T of object
 * @param mixed           $source Parent.
 * @param class-string<T> $class  Expected class.
 * @return T
 * @throws FLXGQL_Error When the parent has another type.
 */
function flxcb_gql_src( $source, string $class ) {
	if ( ! $source instanceof $class ) {
		throw flx_gql_error( 'INTERNAL' );
	}
	return $source;
}

/** Who may answer chats (the same rule as the classic inbox). */
$flxcb_gql_reply = flx_gql_any_cap( 'manage_options', 'flxcb_reply', 'flxc_use' );

/* ------------------------------------------------------------------ Enums */

flx_gql_enum(
	'FlxChatbotConversationStatus',
	array(
		'description' => __( 'Where a chat is.', 'flx-chatbot' ),
		'values'      => array(
			'BOT'     => array( 'value' => 'bot', 'description' => __( 'The assistant is answering.', 'flx-chatbot' ) ),
			'QUEUED'  => array( 'value' => 'queued', 'description' => __( 'Waiting for an agent.', 'flx-chatbot' ) ),
			'AGENT'   => array( 'value' => 'agent', 'description' => __( 'An agent is talking with the visitor.', 'flx-chatbot' ) ),
			'OFFLINE' => array( 'value' => 'offline', 'description' => __( 'The visitor left a message outside working hours.', 'flx-chatbot' ) ),
			'CLOSED'  => array( 'value' => 'closed', 'description' => __( 'The chat ended.', 'flx-chatbot' ) ),
		),
	)
);

flx_gql_enum(
	'FlxChatbotConversationFilter',
	array(
		'description' => __( 'Which chats the list shows.', 'flx-chatbot' ),
		'values'      => array(
			'OPEN'    => array( 'value' => 'open', 'description' => __( 'Waiting for an agent, in conversation or left a message.', 'flx-chatbot' ) ),
			'BOT'     => array( 'value' => 'bot', 'description' => __( 'With the assistant.', 'flx-chatbot' ) ),
			'QUEUED'  => array( 'value' => 'queued', 'description' => __( 'Waiting for an agent.', 'flx-chatbot' ) ),
			'AGENT'   => array( 'value' => 'agent', 'description' => __( 'In conversation.', 'flx-chatbot' ) ),
			'OFFLINE' => array( 'value' => 'offline', 'description' => __( 'Left a message.', 'flx-chatbot' ) ),
			'CLOSED'  => array( 'value' => 'closed', 'description' => __( 'Closed.', 'flx-chatbot' ) ),
			'ALL'     => array( 'value' => '', 'description' => __( 'Every chat.', 'flx-chatbot' ) ),
		),
	)
);

flx_gql_enum(
	'FlxChatbotMessageRole',
	array(
		'description' => __( 'Who wrote a message.', 'flx-chatbot' ),
		'values'      => array(
			'VISITOR' => array( 'value' => 'visitor' ),
			'BOT'     => array( 'value' => 'bot', 'description' => __( 'The assistant.', 'flx-chatbot' ) ),
			'AGENT'   => array( 'value' => 'agent' ),
			'SYSTEM'  => array( 'value' => 'system', 'description' => __( 'An event line (handed over, ended, …).', 'flx-chatbot' ) ),
		),
	)
);

flx_gql_enum(
	'FlxChatbotMessageType',
	array(
		'description' => __( 'What a message carries.', 'flx-chatbot' ),
		'values'      => array(
			'TEXT'  => array( 'value' => 'text' ),
			'IMAGE' => array( 'value' => 'image' ),
			'FILE'  => array( 'value' => 'file' ),
		),
	)
);

flx_gql_enum(
	'FlxChatbotStatusState',
	array(
		'description' => __( 'State of a connection line.', 'flx-chatbot' ),
		'values'      => array(
			'OK'   => array( 'value' => 'ok' ),
			'WARN' => array( 'value' => 'warn' ),
			'INFO' => array( 'value' => 'info' ),
		),
	)
);

/* ------------------------------------------------------------------ Chat types */

flx_gql_type(
	'FlxChatbotProductCard',
	array(
		'description' => __( 'A product the assistant suggested.', 'flx-chatbot' ),
		'fields'      => array(
			'title'   => array(
				'type'        => 'String!',
				'description' => __( 'Product name.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): string => flxcb_gql_src( $c, FLXCB_Card::class )->title,
			),
			'url'     => array(
				'type'        => 'String!',
				'description' => __( 'Product page.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): string => flxcb_gql_src( $c, FLXCB_Card::class )->url,
			),
			'image'   => array(
				'type'        => 'String',
				'description' => __( 'Product image; empty when it has none.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): ?string => '' !== flxcb_gql_src( $c, FLXCB_Card::class )->image ? flxcb_gql_src( $c, FLXCB_Card::class )->image : null,
			),
			'price'   => array(
				'type'        => 'String!',
				'description' => __( 'Price as shown in the shop.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): string => flxcb_gql_src( $c, FLXCB_Card::class )->price,
			),
			'inStock' => array(
				'type'        => 'Boolean!',
				'description' => __( 'The product is in stock.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): bool => 'in' === flxcb_gql_src( $c, FLXCB_Card::class )->stock,
			),
			'cartUrl' => array(
				'type'        => 'String',
				'description' => __( 'Add-to-cart address; empty when the product cannot be added directly.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): ?string => '' !== flxcb_gql_src( $c, FLXCB_Card::class )->cart ? flxcb_gql_src( $c, FLXCB_Card::class )->cart : null,
			),
		),
	)
);

flx_gql_type(
	'FlxChatbotMessage',
	array(
		'description' => __( 'One message of a chat.', 'flx-chatbot' ),
		'fields'      => array(
			'databaseId' => array(
				'type'        => 'Int!',
				'description' => __( 'Message number; grows with time.', 'flx-chatbot' ),
				'resolve'     => static fn( $m ): int => flxcb_gql_src( $m, FLXCB_Message::class )->id,
			),
			'role'       => array(
				'type'        => 'FlxChatbotMessageRole!',
				'description' => __( 'Who wrote it.', 'flx-chatbot' ),
				'resolve'     => static fn( $m ): string => in_array( flxcb_gql_src( $m, FLXCB_Message::class )->role, array( 'visitor', 'bot', 'agent' ), true ) ? flxcb_gql_src( $m, FLXCB_Message::class )->role : 'system',
			),
			'type'       => array(
				'type'        => 'FlxChatbotMessageType!',
				'description' => __( 'What it carries.', 'flx-chatbot' ),
				'resolve'     => static fn( $m ): string => in_array( flxcb_gql_src( $m, FLXCB_Message::class )->type, array( 'image', 'file' ), true ) ? flxcb_gql_src( $m, FLXCB_Message::class )->type : 'text',
			),
			'body'       => array(
				'type'        => 'String!',
				'description' => __( 'Text (assistant replies use a small Markdown subset); a file caption for files.', 'flx-chatbot' ),
				'resolve'     => static fn( $m ): string => flxcb_gql_src( $m, FLXCB_Message::class )->body,
			),
			'sentAt'     => array(
				'type'        => 'FlxDateTime!',
				'description' => __( 'When it was sent.', 'flx-chatbot' ),
				'resolve'     => static fn( $m ): ?array => flx_gql_datetime( (int) floor( flxcb_gql_src( $m, FLXCB_Message::class )->ts / 1000 ) ),
			),
			'author'     => array(
				'type'        => 'String',
				'description' => __( 'Agent first name or the assistant\'s name; empty for the visitor and event lines.', 'flx-chatbot' ),
				'resolve'     => static fn( $m ): ?string => '' !== flxcb_gql_src( $m, FLXCB_Message::class )->author ? flxcb_gql_src( $m, FLXCB_Message::class )->author : null,
			),
			'file'       => array(
				'type'        => 'FlxFile',
				'description' => __( 'Attached file; the address works for signed-in agents only.', 'flx-chatbot' ),
				'resolve'     => static function ( $m ): ?array {
					$msg = flxcb_gql_src( $m, FLXCB_Message::class );
					if ( '' === $msg->file_url ) {
						return null;
					}
					return flx_gql_file(
						array(
							'id'   => (string) $msg->id,
							'name' => $msg->file_name,
							'mime' => $msg->file_mime,
							'size' => 0,
							'url'  => $msg->file_url,
						)
					);
				},
			),
			'cards'      => array(
				'type'        => '[FlxChatbotProductCard!]!',
				'description' => __( 'Products the assistant suggested in this message.', 'flx-chatbot' ),
				'resolve'     => static fn( $m ): array => flxcb_gql_src( $m, FLXCB_Message::class )->cards,
			),
		),
	)
);

flx_gql_type(
	'FlxChatbotMessagePage',
	array(
		'description' => __( 'Messages after a given message, oldest first (the chat screen asks again with the last number to get what is new).', 'flx-chatbot' ),
		'fields'      => array(
			'nodes'    => array(
				'type'        => '[FlxChatbotMessage!]!',
				'description' => __( 'The messages.', 'flx-chatbot' ),
				'resolve'     => static fn( $p ): array => flxcb_gql_src( $p, FLXCB_Message_Page::class )->nodes,
			),
			'lastId'   => array(
				'type'        => 'Int!',
				'description' => __( 'Number of the last returned message; pass it as afterId next time.', 'flx-chatbot' ),
				'resolve'     => static fn( $p ): int => flxcb_gql_src( $p, FLXCB_Message_Page::class )->last_id,
			),
			'hasMore'  => array(
				'type'        => 'Boolean!',
				'description' => __( 'More messages follow; ask again with lastId.', 'flx-chatbot' ),
				'resolve'     => static fn( $p ): bool => flxcb_gql_src( $p, FLXCB_Message_Page::class )->has_more,
			),
		),
	)
);

flx_gql_type(
	'FlxChatbotLastMessage',
	array(
		'description' => __( 'The last line of a chat, for the list.', 'flx-chatbot' ),
		'fields'      => array(
			'role' => array(
				'type'        => 'FlxChatbotMessageRole!',
				'description' => __( 'Who wrote it.', 'flx-chatbot' ),
				'resolve'     => static fn( $l ): string => in_array( flxcb_gql_src( $l, FLXCB_Last::class )->role, array( 'visitor', 'bot', 'agent' ), true ) ? flxcb_gql_src( $l, FLXCB_Last::class )->role : 'system',
			),
			'body' => array(
				'type'        => 'String!',
				'description' => __( 'First 120 characters (the file name for a file).', 'flx-chatbot' ),
				'resolve'     => static fn( $l ): string => flxcb_gql_src( $l, FLXCB_Last::class )->body,
			),
		),
	)
);

flx_gql_type(
	'FlxChatbotConversation',
	array(
		'description' => __( 'A chat between a site visitor and the assistant or an agent.', 'flx-chatbot' ),
		'node'        => FLXCB_Conversation_Loader::class,
		'fields'      => array(
			'title'        => array(
				'type'        => 'String!',
				'description' => __( 'Visitor name, else email, else "Visitor #n".', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): string => flxcb_gql_src( $c, FLXCB_Conversation::class )->title,
			),
			'name'         => array(
				'type'        => 'String',
				'description' => __( 'Name the visitor gave; empty when none.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): ?string => '' !== flxcb_gql_src( $c, FLXCB_Conversation::class )->name ? flxcb_gql_src( $c, FLXCB_Conversation::class )->name : null,
			),
			'email'        => array(
				'type'        => 'String',
				'description' => __( 'Visitor email; empty when not given.', 'flx-chatbot' ),
				'cap'         => $flxcb_gql_reply,
				'resolve'     => static fn( $c ): ?string => '' !== flxcb_gql_src( $c, FLXCB_Conversation::class )->email ? flxcb_gql_src( $c, FLXCB_Conversation::class )->email : null,
			),
			'phone'        => array(
				'type'        => 'String',
				'description' => __( 'Visitor phone; empty when not given.', 'flx-chatbot' ),
				'cap'         => $flxcb_gql_reply,
				'resolve'     => static fn( $c ): ?string => '' !== flxcb_gql_src( $c, FLXCB_Conversation::class )->phone ? flxcb_gql_src( $c, FLXCB_Conversation::class )->phone : null,
			),
			'status'       => array(
				'type'        => 'FlxChatbotConversationStatus!',
				'description' => __( 'Where the chat is.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): string => in_array( flxcb_gql_src( $c, FLXCB_Conversation::class )->status, FLXCB_Chat_Service::STATUSES, true ) ? flxcb_gql_src( $c, FLXCB_Conversation::class )->status : 'bot',
			),
			'unread'       => array(
				'type'        => 'Int!',
				'description' => __( 'Visitor messages no agent has read.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): int => flxcb_gql_src( $c, FLXCB_Conversation::class )->unread,
			),
			'rating'       => array(
				'type'        => 'Int',
				'description' => __( 'Stars (1-5) the visitor gave; empty when not rated.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): ?int => flxcb_gql_src( $c, FLXCB_Conversation::class )->rating > 0 ? flxcb_gql_src( $c, FLXCB_Conversation::class )->rating : null,
			),
			'ratingNote'   => array(
				'type'        => 'String',
				'description' => __( 'Note that came with the rating; empty when none.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): ?string => '' !== flxcb_gql_src( $c, FLXCB_Conversation::class )->note ? flxcb_gql_src( $c, FLXCB_Conversation::class )->note : null,
			),
			'isLead'       => array(
				'type'        => 'Boolean!',
				'description' => __( 'The visitor left contact details.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): bool => flxcb_gql_src( $c, FLXCB_Conversation::class )->lead,
			),
			'orderId'      => array(
				'type'        => 'Int',
				'description' => __( 'WooCommerce order placed after the chat; empty when none.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): ?int => flxcb_gql_src( $c, FLXCB_Conversation::class )->order_id > 0 ? flxcb_gql_src( $c, FLXCB_Conversation::class )->order_id : null,
			),
			'orderUrl'     => array(
				'type'        => 'String',
				'description' => __( 'Edit screen of that order; empty when none or WooCommerce is off.', 'flx-chatbot' ),
				'cap'         => $flxcb_gql_reply,
				'resolve'     => static fn( $c ): ?string => '' !== flxcb_gql_src( $c, FLXCB_Conversation::class )->order_url ? flxcb_gql_src( $c, FLXCB_Conversation::class )->order_url : null,
			),
			'pageUrl'      => array(
				'type'        => 'String',
				'description' => __( 'Page the visitor is on; empty when unknown.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): ?string => '' !== flxcb_gql_src( $c, FLXCB_Conversation::class )->page_url ? flxcb_gql_src( $c, FLXCB_Conversation::class )->page_url : null,
			),
			'pageTitle'    => array(
				'type'        => 'String',
				'description' => __( 'Title of that page; empty when unknown.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): ?string => '' !== flxcb_gql_src( $c, FLXCB_Conversation::class )->page_title ? flxcb_gql_src( $c, FLXCB_Conversation::class )->page_title : null,
			),
			'userId'       => array(
				'type'        => 'Int',
				'description' => __( 'Site member id when the visitor was signed in; empty for guests.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): ?int => flxcb_gql_src( $c, FLXCB_Conversation::class )->user_id > 0 ? flxcb_gql_src( $c, FLXCB_Conversation::class )->user_id : null,
			),
			'agentName'    => array(
				'type'        => 'String',
				'description' => __( 'First name of the agent the chat is assigned to; empty when none.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): ?string => '' !== flxcb_gql_src( $c, FLXCB_Conversation::class )->agent ? flxcb_gql_src( $c, FLXCB_Conversation::class )->agent : null,
			),
			'fluxChatUrl'  => array(
				'type'        => 'String',
				'description' => __( 'The same chat in the Flux Chat inbox; empty when it is not there.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): ?string => '' !== flxcb_gql_src( $c, FLXCB_Conversation::class )->flux_chat_url ? flxcb_gql_src( $c, FLXCB_Conversation::class )->flux_chat_url : null,
			),
			'summary'      => array(
				'type'        => 'String',
				'description' => __( 'Note the assistant wrote for the agent when it handed over; empty when none.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): ?string => '' !== flxcb_gql_src( $c, FLXCB_Conversation::class )->summary ? flxcb_gql_src( $c, FLXCB_Conversation::class )->summary : null,
			),
			'lastActivity' => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'Time of the last message; empty for a chat without messages.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): ?array => flxcb_gql_src( $c, FLXCB_Conversation::class )->last_ts > 0 ? flx_gql_datetime( (int) floor( flxcb_gql_src( $c, FLXCB_Conversation::class )->last_ts / 1000 ) ) : null,
			),
			'online'       => array(
				'type'        => 'Boolean!',
				'description' => __( 'The visitor is on the page now.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): bool => flxcb_gql_src( $c, FLXCB_Conversation::class )->online,
			),
			'typing'       => array(
				'type'        => 'Boolean!',
				'description' => __( 'The visitor is typing.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): bool => flxcb_gql_src( $c, FLXCB_Conversation::class )->typing,
			),
			'lastMessage'  => array(
				'type'        => 'FlxChatbotLastMessage',
				'description' => __( 'The last message; empty for a chat without messages.', 'flx-chatbot' ),
				'resolve'     => static fn( $c ): ?FLXCB_Last => flxcb_gql_src( $c, FLXCB_Conversation::class )->last,
			),
		),
	)
);

flx_gql_connection( 'FlxChatbotConversation', __( 'A page of chats.', 'flx-chatbot' ) );

flx_gql_query(
	'flxChatbotConversations',
	array(
		'type'        => 'FlxChatbotConversationConnection!',
		'description' => __( 'Site chats, newest activity first.', 'flx-chatbot' ),
		'cap'         => $flxcb_gql_reply,
		'args'        => flx_gql_connection_args(
			array(
				'filter' => array(
					'type'        => 'FlxChatbotConversationFilter',
					'description' => __( 'Which chats to list; open chats when left out.', 'flx-chatbot' ),
				),
				'search' => array(
					'type'        => 'String',
					'description' => __( 'Words in the name, email, phone or page title.', 'flx-chatbot' ),
				),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
			/** @var array<string,mixed> $args */
			$filter = array_key_exists( 'filter', $args ) ? flx_gql_str( $args['filter'] ) : 'open';
			$search = trim( flx_gql_str( $args['search'] ?? '' ) );
			flx_gql_cache_tag( FLXCB_Chat_Service::CACHE_TAG );
			return flx_gql_paginate(
				$args,
				static function ( int $offset, int $limit ) use ( $filter, $search ): array {
					$page = FLXCB_Chat_Service::page( $filter, $search, $offset, $limit );
					return array(
						'items' => array_map( array( FLXCB_Conversation_Node::class, 'of' ), $page['items'] ),
						'total' => $page['total'],
					);
				}
			);
		},
	)
);

flx_gql_query(
	'flxChatbotConversation',
	array(
		'type'        => 'FlxChatbotConversation',
		'description' => __( 'One chat; empty when it does not exist or the user is not an agent.', 'flx-chatbot' ),
		'cap'         => $flxcb_gql_reply,
		'args'        => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or chat number.', 'flx-chatbot' ),
			),
		),
		'resolve'     => static fn( $root, array $args, \WPGraphQL\AppContext $context ) => $context->get_loader( 'FlxChatbotConversation' )->load_deferred( flx_gql_decode_id( $args['id'] ?? '', 'FlxChatbotConversation' ) ),
	)
);

flx_gql_query(
	'flxChatbotMessages',
	array(
		'type'        => 'FlxChatbotMessagePage!',
		'description' => __( 'Messages of a chat after a message number, oldest first. Unless peek is on, opening the chat marks the visitor\'s messages as read.', 'flx-chatbot' ),
		'cap'         => $flxcb_gql_reply,
		'args'        => array(
			'conversationId' => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or chat number.', 'flx-chatbot' ),
			),
			'afterId'        => array(
				'type'        => 'Int',
				'description' => __( 'Only messages after this message number (0: from the start).', 'flx-chatbot' ),
			),
			'first'          => array(
				'type'        => 'Int',
				'description' => __( 'How many messages (at most 100).', 'flx-chatbot' ),
			),
			'peek'           => array(
				'type'        => 'Boolean',
				'description' => __( 'Read without marking the chat as read.', 'flx-chatbot' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): FLXCB_Message_Page {
			/** @var array<string,mixed> $args */
			$id = flx_gql_decode_id( $args['conversationId'] ?? '', 'FlxChatbotConversation' );
			return flxcb_gql_ok( FLXCB_Chat_Service::messages( $id, flx_gql_int( $args['afterId'] ?? 0 ), min( FLXCB_Chat_Service::MESSAGE_LIMIT, max( 1, flx_gql_int( $args['first'] ?? FLXCB_Chat_Service::MESSAGE_LIMIT ) ) ), ! empty( $args['peek'] ) ) );
		},
	)
);

flx_gql_type(
	'FlxChatbotInbox',
	array(
		'description' => __( 'What the Site chats screen needs besides the chats.', 'flx-chatbot' ),
		'fields'      => array(
			'fluxChatUrl'  => array(
				'type'        => 'String',
				'description' => __( 'Flux Chat inbox; empty when handed-over chats do not go there.', 'flx-chatbot' ),
				'resolve'     => static fn(): ?string => 'flx-chat' === flxcb_handoff_target() ? admin_url( 'admin.php?page=flx-chat' ) : null,
			),
			'settingsUrl'  => array(
				'type'        => 'String',
				'description' => __( 'Chatbot settings page; empty for agents who may not change settings.', 'flx-chatbot' ),
				'resolve'     => static fn(): ?string => current_user_can( 'manage_options' ) ? flxcb_admin_url() : null,
			),
			'canDelete'    => array(
				'type'        => 'Boolean!',
				'description' => __( 'The user may delete chats (administrators).', 'flx-chatbot' ),
				'resolve'     => static fn(): bool => current_user_can( 'manage_options' ),
			),
			'agentName'    => array(
				'type'        => 'String!',
				'description' => __( 'Name replies are signed with.', 'flx-chatbot' ),
				'resolve'     => static fn(): string => flxcb_user_name( get_current_user_id() ),
			),
			'maxUploadMb'  => array(
				'type'        => 'Int!',
				'description' => __( 'Largest file an agent can attach (MB).', 'flx-chatbot' ),
				'resolve'     => static fn(): int => max( 1, FLXCB_Val::int( flxcb_setting( 'upload_mb' ) ) ),
			),
		),
	)
);

flx_gql_query(
	'flxChatbotInbox',
	array(
		'type'        => 'FlxChatbotInbox!',
		'description' => __( 'Settings of the Site chats screen.', 'flx-chatbot' ),
		'cap'         => $flxcb_gql_reply,
		'resolve'     => static fn(): array => array(),
	)
);

/* ------------------------------------------------------------------ Chat mutations */

flx_gql_mutation(
	'flxChatbotConversationReply',
	array(
		'description' => __( 'Answers a chat as an agent. The visitor sees it right away.', 'flx-chatbot' ),
		'cap'         => $flxcb_gql_reply,
		'input'       => array(
			'id'       => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or chat number.', 'flx-chatbot' ),
			),
			'text'     => array(
				'type'        => 'String',
				'description' => __( 'Reply text.', 'flx-chatbot' ),
			),
			'uploadId' => array(
				'type'        => 'String',
				'description' => __( 'File sent first to /wp-json/flx/v1/upload (context flx-chatbot/reply).', 'flx-chatbot' ),
			),
		),
		'output'      => array(
			'conversation' => array(
				'type'        => 'FlxChatbotConversation',
				'description' => __( 'The chat after the reply.', 'flx-chatbot' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			$id   = flx_gql_decode_id( $input['id'] ?? '', 'FlxChatbotConversation' );
			$file = array();
			$up   = flx_gql_str( $input['uploadId'] ?? '' );
			if ( '' !== $up ) {
				$taken = flxcb_gql_ok( flx_gql_upload_take( $up, 'flx-chatbot/reply' ) );
				$path  = flx_gql_str( $taken['path'] ?? '' );
				/** @var array<string,mixed> $file */
				$file = flxcb_gql_ok( flxcb_store_local( $path, flx_gql_str( $taken['name'] ?? '' ), flx_gql_int( $taken['size'] ?? 0 ) ) );
				wp_delete_file( $path );
			}
			$c = flxcb_gql_ok( FLXCB_Chat_Service::reply( $id, get_current_user_id(), flx_gql_str( $input['text'] ?? '' ), $file ) );
			return array( 'conversation' => FLXCB_Conversation_Node::of( $c ) );
		},
	)
);

flx_gql_mutation(
	'flxChatbotConversationClose',
	array(
		'description' => __( 'Ends a chat. It reopens when the visitor writes again.', 'flx-chatbot' ),
		'cap'         => $flxcb_gql_reply,
		'input'       => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or chat number.', 'flx-chatbot' ),
			),
		),
		'output'      => array(
			'conversation' => array(
				'type'        => 'FlxChatbotConversation',
				'description' => __( 'The chat after it ended.', 'flx-chatbot' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			$c = flxcb_gql_ok( FLXCB_Chat_Service::close( flx_gql_decode_id( $input['id'] ?? '', 'FlxChatbotConversation' ) ) );
			return array( 'conversation' => FLXCB_Conversation_Node::of( $c ) );
		},
	)
);

flx_gql_mutation(
	'flxChatbotConversationDelete',
	array(
		'description' => __( 'Deletes a chat and its files for good.', 'flx-chatbot' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or chat number.', 'flx-chatbot' ),
			),
		),
		'output'      => array(
			'deletedId' => array(
				'type'        => 'Int',
				'description' => __( 'Number of the deleted chat.', 'flx-chatbot' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			$id = flx_gql_decode_id( $input['id'] ?? '', 'FlxChatbotConversation' );
			if ( ! FLXCB_Chat_Service::delete( $id ) ) {
				throw flx_gql_error( 'NOT_FOUND', __( 'Chat not found.', 'flx-chatbot' ) );
			}
			return array( 'deletedId' => $id );
		},
	)
);

flx_gql_mutation(
	'flxChatbotConversationTyping',
	array(
		'description' => __( 'Shows the visitor that an agent is typing (for 8 seconds).', 'flx-chatbot' ),
		'cap'         => $flxcb_gql_reply,
		'input'       => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or chat number.', 'flx-chatbot' ),
			),
		),
		'output'      => array(
			'ok' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Always true.', 'flx-chatbot' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			FLXCB_Chat_Service::typing( flx_gql_decode_id( $input['id'] ?? '', 'FlxChatbotConversation' ) );
			return array( 'ok' => true );
		},
	)
);

/* ------------------------------------------------------------------ Settings screen */

flx_gql_type(
	'FlxChatbotStats',
	array(
		'description' => __( 'Chat figures of the last days.', 'flx-chatbot' ),
		'fields'      => array(
			'total'    => array(
				'type'        => 'Int!',
				'description' => __( 'Chats started.', 'flx-chatbot' ),
				'resolve'     => static fn( $s ): int => flxcb_gql_src( $s, FLXCB_Stats::class )->total,
			),
			'human'    => array(
				'type'        => 'Int!',
				'description' => __( 'Chats an agent was needed for.', 'flx-chatbot' ),
				'resolve'     => static fn( $s ): int => flxcb_gql_src( $s, FLXCB_Stats::class )->human,
			),
			'resolved' => array(
				'type'        => 'Int!',
				'description' => __( 'Percent solved by the assistant alone.', 'flx-chatbot' ),
				'resolve'     => static fn( $s ): int => flxcb_gql_src( $s, FLXCB_Stats::class )->resolved,
			),
			'leads'    => array(
				'type'        => 'Int!',
				'description' => __( 'Chats where the visitor left contact details.', 'flx-chatbot' ),
				'resolve'     => static fn( $s ): int => flxcb_gql_src( $s, FLXCB_Stats::class )->leads,
			),
			'orders'   => array(
				'type'        => 'Int!',
				'description' => __( 'Orders placed within 7 days of a chat.', 'flx-chatbot' ),
				'resolve'     => static fn( $s ): int => flxcb_gql_src( $s, FLXCB_Stats::class )->orders,
			),
			'rating'   => array(
				'type'        => 'Float',
				'description' => __( 'Average stars; empty when nothing is rated.', 'flx-chatbot' ),
				'resolve'     => static fn( $s ): ?float => flxcb_gql_src( $s, FLXCB_Stats::class )->rated > 0 ? flxcb_gql_src( $s, FLXCB_Stats::class )->rating : null,
			),
			'rated'    => array(
				'type'        => 'Int!',
				'description' => __( 'Number of ratings.', 'flx-chatbot' ),
				'resolve'     => static fn( $s ): int => flxcb_gql_src( $s, FLXCB_Stats::class )->rated,
			),
			'open'     => array(
				'type'        => 'Int!',
				'description' => __( 'Chats waiting for an agent or in conversation now.', 'flx-chatbot' ),
				'resolve'     => static fn( $s ): int => flxcb_gql_src( $s, FLXCB_Stats::class )->open,
			),
		),
	)
);

flx_gql_type(
	'FlxChatbotStatusRow',
	array(
		'description' => __( 'One line of the Connections card.', 'flx-chatbot' ),
		'fields'      => array(
			'key'       => array(
				'type'        => 'String!',
				'description' => __( 'Line: ai, live or hours.', 'flx-chatbot' ),
				'resolve'     => static fn( $r ): string => flxcb_gql_src( $r, FLXCB_Status_Row::class )->key,
			),
			'state'     => array(
				'type'        => 'FlxChatbotStatusState!',
				'description' => __( 'Good, needs attention, or just information.', 'flx-chatbot' ),
				'resolve'     => static fn( $r ): string => flxcb_gql_src( $r, FLXCB_Status_Row::class )->state,
			),
			'title'     => array(
				'type'        => 'String!',
				'description' => __( 'Headline.', 'flx-chatbot' ),
				'resolve'     => static fn( $r ): string => flxcb_gql_src( $r, FLXCB_Status_Row::class )->title,
			),
			'text'      => array(
				'type'        => 'String!',
				'description' => __( 'Explanation.', 'flx-chatbot' ),
				'resolve'     => static fn( $r ): string => flxcb_gql_src( $r, FLXCB_Status_Row::class )->text,
			),
			'url'       => array(
				'type'        => 'String',
				'description' => __( 'Where to fix or change it; empty when there is nothing to open.', 'flx-chatbot' ),
				'resolve'     => static fn( $r ): ?string => '' !== flxcb_gql_src( $r, FLXCB_Status_Row::class )->url ? flxcb_gql_src( $r, FLXCB_Status_Row::class )->url : null,
			),
			'urlLabel'  => array(
				'type'        => 'String',
				'description' => __( 'Label of that link.', 'flx-chatbot' ),
				'resolve'     => static fn( $r ): ?string => '' !== flxcb_gql_src( $r, FLXCB_Status_Row::class )->url_label ? flxcb_gql_src( $r, FLXCB_Status_Row::class )->url_label : null,
			),
		),
	)
);

flx_gql_type(
	'FlxChatbotTextDefault',
	array(
		'description' => __( 'The text a field uses while it is left empty.', 'flx-chatbot' ),
		'fields'      => array(
			'key'  => array(
				'type'        => 'String!',
				'description' => __( 'Setting key, e.g. welcome.', 'flx-chatbot' ),
				'resolve'     => static fn( $d ): string => flx_gql_str( flx_gql_arr( $d )['key'] ?? '' ),
			),
			'text' => array(
				'type'        => 'String!',
				'description' => __( 'Default text in the admin\'s language.', 'flx-chatbot' ),
				'resolve'     => static fn( $d ): string => flx_gql_str( flx_gql_arr( $d )['text'] ?? '' ),
			),
		),
	)
);

flx_gql_type(
	'FlxChatbotChoice',
	array(
		'description' => __( 'A key with its label (page type, weekday).', 'flx-chatbot' ),
		'fields'      => array(
			'key'   => array(
				'type'        => 'String!',
				'description' => __( 'Key; weekdays are 1 (Monday) to 7 (Sunday).', 'flx-chatbot' ),
				'resolve'     => static fn( $d ): string => flx_gql_str( flx_gql_arr( $d )['key'] ?? '' ),
			),
			'label' => array(
				'type'        => 'String!',
				'description' => __( 'Name in the admin\'s language.', 'flx-chatbot' ),
				'resolve'     => static fn( $d ): string => flx_gql_str( flx_gql_arr( $d )['label'] ?? '' ),
			),
		),
	)
);

flx_gql_type(
	'FlxChatbotPreview',
	array(
		'description' => __( 'What the live preview needs to show the real chat window.', 'flx-chatbot' ),
		'fields'      => array(
			'config'    => array(
				'type'        => 'FlxJSON!',
				'description' => __( 'Window configuration the widget script reads (free-form: it is the widget\'s own contract, not an API).', 'flx-chatbot' ),
				'resolve'     => static fn(): array => FLXCB_Settings_Service::preview_config(),
			),
			'scriptUrl' => array(
				'type'        => 'String!',
				'description' => __( 'The widget script.', 'flx-chatbot' ),
				'resolve'     => static fn(): string => FLXCB_URL . 'assets/widget.js?ver=' . FLXCB_VERSION,
			),
		),
	)
);

flx_gql_type(
	'FlxChatbotAdmin',
	array(
		'description' => __( 'Everything the Chatbot settings screen shows besides the settings values.', 'flx-chatbot' ),
		'fields'      => array(
			'stats'              => array(
				'type'        => 'FlxChatbotStats!',
				'description' => __( 'Chat figures.', 'flx-chatbot' ),
				'args'        => array(
					'days' => array(
						'type'        => 'Int',
						'description' => __( 'How many days back (default 30).', 'flx-chatbot' ),
					),
				),
				'resolve'     => static fn( $root, array $args ): FLXCB_Stats => FLXCB_Chat_Service::stats( flx_gql_int( $args['days'] ?? 30, 30 ) ),
			),
			'status'             => array(
				'type'        => '[FlxChatbotStatusRow!]!',
				'description' => __( 'The Connections card.', 'flx-chatbot' ),
				'resolve'     => static fn(): array => FLXCB_Settings_Service::status_rows(),
			),
			'textDefaults'       => array(
				'type'        => '[FlxChatbotTextDefault!]!',
				'description' => __( 'Texts used while a text field is empty.', 'flx-chatbot' ),
				'resolve'     => static function (): array {
					$out = array();
					foreach ( FLXCB_Settings_Service::default_texts() as $k => $t ) {
						$out[] = array( 'key' => $k, 'text' => $t );
					}
					return $out;
				},
			),
			'pageTypes'          => array(
				'type'        => '[FlxChatbotChoice!]!',
				'description' => __( 'Page types of the Visibility tab (setting showOn<Type>).', 'flx-chatbot' ),
				'resolve'     => static function (): array {
					$out = array();
					foreach ( FLXCB_Settings_Service::page_types() as $k => $l ) {
						$out[] = array( 'key' => $k, 'label' => $l );
					}
					return $out;
				},
			),
			'weekdays'           => array(
				'type'        => '[FlxChatbotChoice!]!',
				'description' => __( 'Weekdays of the working hours table.', 'flx-chatbot' ),
				'resolve'     => static function (): array {
					$out = array();
					foreach ( flxcb_day_names() as $d => $l ) {
						$out[] = array( 'key' => (string) $d, 'label' => $l );
					}
					return $out;
				},
			),
			'avatarUrl'          => array(
				'type'        => 'String',
				'description' => __( 'Thumbnail of the chosen avatar image; empty when none.', 'flx-chatbot' ),
				'resolve'     => static function (): ?string {
					$u = FLXCB_Settings_Service::avatar_thumb();
					return '' !== $u ? $u : null;
				},
			),
			'notifyEmailDefault' => array(
				'type'        => 'String!',
				'description' => __( 'Address used for notifications while the field is empty (the site admin).', 'flx-chatbot' ),
				'resolve'     => static fn(): string => flx_gql_str( get_option( 'admin_email' ) ),
			),
			'aiReady'            => array(
				'type'        => 'Boolean!',
				'description' => __( 'Flux AI & MCP is connected for the assistant.', 'flx-chatbot' ),
				'resolve'     => static fn(): bool => flxcb_ai_ready(),
			),
			'aiSettingsUrl'      => array(
				'type'        => 'String',
				'description' => __( 'Where the model for this plugin is chosen (Flux AI); empty without Flux AI.', 'flx-chatbot' ),
				'resolve'     => static fn(): ?string => function_exists( 'flx_ai_settings_url' ) ? flx_gql_str( flx_ai_settings_url( 'flx-chatbot' ) ) : null,
			),
			'fluxChatReady'      => array(
				'type'        => 'Boolean!',
				'description' => __( 'Flux Chat can take handed-over chats.', 'flx-chatbot' ),
				'resolve'     => static fn(): bool => flxcb_fc_ready(),
			),
			'fluxChatActive'     => array(
				'type'        => 'Boolean!',
				'description' => __( 'Flux Chat is installed (its working hours can be used).', 'flx-chatbot' ),
				'resolve'     => static fn(): bool => flxcb_flux_chat_active(),
			),
			'shopActive'         => array(
				'type'        => 'Boolean!',
				'description' => __( 'WooCommerce is active (product cards, order status, orders after chat).', 'flx-chatbot' ),
				'resolve'     => static fn(): bool => function_exists( 'wc_get_product' ),
			),
			'ordersActive'       => array(
				'type'        => 'Boolean!',
				'description' => __( 'WooCommerce orders can be looked up.', 'flx-chatbot' ),
				'resolve'     => static fn(): bool => function_exists( 'wc_get_orders' ),
			),
			'wikiActive'         => array(
				'type'        => 'Boolean!',
				'description' => __( 'Flux Wiki is active (its pages can be a knowledge source).', 'flx-chatbot' ),
				'resolve'     => static fn(): bool => defined( 'FLXWK_PT' ),
			),
			'inboxUrl'           => array(
				'type'        => 'String!',
				'description' => __( 'Where the chats are answered.', 'flx-chatbot' ),
				'resolve'     => static fn(): string => flxcb_inbox_url(),
			),
			'siteUrl'            => array(
				'type'        => 'String!',
				'description' => __( 'Front page, to try the window on the site.', 'flx-chatbot' ),
				'resolve'     => static fn(): string => home_url( '/' ),
			),
			'preview'            => array(
				'type'        => 'FlxChatbotPreview!',
				'description' => __( 'Data of the live preview.', 'flx-chatbot' ),
				'resolve'     => static fn(): array => array(),
			),
		),
	)
);

flx_gql_query(
	'flxChatbotAdmin',
	array(
		'type'        => 'FlxChatbotAdmin!',
		'description' => __( 'Data of the Chatbot settings screen (the values are in flxSettings.chatbot).', 'flx-chatbot' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): array {
			flx_gql_cache_tag( array( 'flx-chatbot:settings', FLXCB_Chat_Service::CACHE_TAG ) );
			return array();
		},
	)
);

/** Generated settings input (flx_settings_schema): GraphQL field → setting key. */
/** @var array{type:string,fields:array<string,array{key:string}>}|null $flxcb_gql_group */
$flxcb_gql_group = flx_gql_settings_schema()['flxcb_settings'] ?? null;
if ( null !== $flxcb_gql_group ) {
	flx_gql_mutation(
		'flxChatbotSettingsSave',
		array(
			'description' => __( 'Saves chatbot settings the way the settings form does (values are cleaned and limited). Only the fields you send change.', 'flx-chatbot' ),
			'cap'         => 'manage_options',
			'input'       => array(
				'settings' => array(
					'type'        => $flxcb_gql_group['type'] . 'Input!',
					'description' => __( 'The settings to change.', 'flx-chatbot' ),
				),
			),
			'output'      => array(
				'settings' => array(
					'type'        => $flxcb_gql_group['type'],
					'description' => __( 'Settings after saving.', 'flx-chatbot' ),
					'resolve'     => static fn(): array => flxcb_settings( true ),
				),
			),
			'resolve'     => static function ( array $input ) use ( $flxcb_gql_group ): array {
				/** @var array<string,mixed> $input */
				$in = array();
				foreach ( flx_gql_arr( $input['settings'] ?? null ) as $gql => $value ) {
					$f = $flxcb_gql_group['fields'][ $gql ] ?? null;
					if ( null === $f || null === $value ) {
						continue;
					}
					$in[ $f['key'] ] = $value;
				}
				if ( isset( $in['notify_email'] ) && '' !== $in['notify_email'] && ! is_email( flx_gql_str( $in['notify_email'] ) ) ) {
					throw flx_gql_error( 'INVALID_INPUT', __( 'Enter a valid email address.', 'flx-chatbot' ), 'notifyEmail' );
				}
				FLXCB_Settings_Service::save( $in );
				return array();
			},
		)
	);
}
