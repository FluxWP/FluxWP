<?php
/**
 * Flux Chat GraphQL schema (part 1: conversations, messages, live updates and the chat page).
 * Loaded on flx_gql_register only. REST stays on the same services and is unchanged.
 *
 * Every field that takes or returns a chat checks flxc_user_can_see_chat. A hidden chat is
 * NOT_FOUND, and node(id:) / lists omit it. Media addresses are built for the current viewer
 * (they carry a per-user nonce) and are not given a shared cache tag.
 *
 * @package FLX_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Chat row as a Node.
 */
final class FLXC_Chat_Node implements FLXGQL_Node {

	/** @var array<string,mixed> */
	public array $row;

	public bool $panel;

	/** @var array<string,mixed>|null */
	private ?array $js = null;

	/**
	 * @param array<string,mixed> $row Chat row.
	 */
	public function __construct( array $row, bool $panel = false ) {
		$this->row   = $row;
		$this->panel = $panel;
	}

	public function gql_type(): string {
		return 'FlxChat';
	}

	public function gql_id(): int {
		return FLXC_Val::int($this->row['id']);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function js(): array {
		if ( null === $this->js ) {
			$this->js = flxc_chat_for_js( $this->row );
		}
		return $this->js;
	}
}

/**
 * One query for a page of chats. can_see drops a chat the agent must not open.
 */
final class FLXC_Chat_Loader extends FLXGQL_Loader {

	/**
	 * @param array<int,int> $ids Ids.
	 * @return array<int,FLXC_Chat_Node>
	 */
	protected function fetch( array $ids ): array {
		$out = array();
		foreach ( $ids as $id ) {
			$row = flxc_get_chat( FLXC_Val::int($id) );
			if ( is_array( $row ) ) {
				$out[ FLXC_Val::int($id) ] = new FLXC_Chat_Node( $row );
			}
		}
		return $out;
	}

	/**
	 * @param mixed $record Record.
	 */
	protected function can_see( $record ): bool {
		return $record instanceof FLXC_Chat_Node && flxc_user_can_see_chat( $record->row );
	}
}

/**
 * People by phone, one SQL batch. A person is returned only when the viewer can see a chat
 * with that phone. CRM-only fields keep their own cap.
 */
final class FLXC_Phone_Person_Loader extends \WPGraphQL\Data\Loader\AbstractDataLoader {

	/**
	 * @param array<int,mixed> $keys Phone keys.
	 * @return array<int|string,mixed>
	 */
	protected function loadKeys( array $keys ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$want = array();
		foreach ( $keys as $key ) {
			$digits = preg_replace( '/\D+/', '', FLXC_Val::str($key) );
			if ( is_string( $digits ) && '' !== $digits ) {
				$want[ $digits ] = $digits;
			}
		}
		$found = array();
		if ( $want && class_exists( 'FLXCRM_Contact' ) && class_exists( 'FLXCRM_Contact_Node' ) && function_exists( 'flx_people_table' ) && function_exists( 'flxcrm_display_name' ) ) {
			global $wpdb;
			/** @var wpdb $wpdb */
			$visible = array();
			$place   = implode( ',', array_fill( 0, count( $want ), '%s' ) );
			$chats   = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'chats' ) . " WHERE phone IN ($place)", array_values( $want ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare // @phpstan-ignore argument.type
			foreach ( (array) $chats as $chat ) {
				$chat = FLXC_Val::arr( $chat );
				if ( array() !== $chat && flxc_user_can_see_chat( $chat ) ) {
					$visible[ FLXC_Val::str($chat['phone']) ] = true;
				}
			}
			$phones = array_keys( array_intersect_key( $want, $visible ) );
			if ( $phones ) {
				$place = implode( ',', array_fill( 0, count( $phones ), '%s' ) );
				$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . FLXC_Val::str( flx_people_table() ) . " WHERE phone IN ($place)", $phones ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare // @phpstan-ignore argument.type
				foreach ( (array) $rows as $row ) {
					$row = FLXC_Val::arr( $row );
					if ( array() === $row ) {
						continue;
					}
					$phone = preg_replace( '/\D+/', '', FLXC_Val::str(( $row['phone'] ?? '' )) );
					if ( is_string( $phone ) && '' !== $phone && isset( $visible[ $phone ] ) ) {
						$found[ $phone ] = new FLXCRM_Contact_Node( FLXCRM_Contact::from_row( $row ) );
					}
				}
			}
		}
		$out = array();
		foreach ( $keys as $key ) {
			$digits = preg_replace( '/\D+/', '', FLXC_Val::str($key) );
			$idx    = is_int( $key ) || is_string( $key ) ? $key : FLXC_Val::str( $key );
			$out[ $idx ] = is_string( $digits ) ? ( $found[ $digits ] ?? null ) : null;
		}
		return $out;
	}
}

/**
 * @param mixed $source Parent.
 */
function flxc_gql_chat( $source ): FLXC_Chat_Node {
	if ( ! $source instanceof FLXC_Chat_Node ) {
		throw flx_gql_error( 'INTERNAL' );
	}
	return $source;
}

/**
 * @param array<string,mixed> $params Params.
 */
function flxc_gql_req( array $params ): WP_REST_Request {
	$req = new WP_REST_Request( 'POST' );
	foreach ( $params as $key => $value ) {
		$req->set_param( FLXC_Val::str($key), $value );
	}
	return $req;
}

/**
 * Visible chat or NOT_FOUND.
 *
 * @return array<string,mixed>
 */
function flxc_gql_visible( int $id ): array {
	$row = FLXC_Chat_Service::visible( $id );
	if ( null === $row ) {
		throw flx_gql_error( 'NOT_FOUND', __( 'Chat not found.', 'flx-chat' ) );
	}
	return $row;
}

/**
 * @param mixed $id Global id or number.
 */
function flxc_gql_chat_id( $id ): int {
	return flx_gql_decode_id( $id, 'FlxChat' );
}

/**
 * Milliseconds as a decimal string. GraphQL Int is 32-bit.
 *
 * @param mixed $ms Milliseconds.
 */
function flxc_gql_ms( $ms ): string {
	return FLXC_Val::str(FLXC_Val::int($ms));
}

/**
 * @param mixed $ms Milliseconds.
 * @return array{iso:string,timestamp:int,label:string,relative:string}|null
 */
function flxc_gql_ms_time( $ms ): ?array {
	$ms = FLXC_Val::int($ms);
	if ( $ms < 1 ) {
		return null;
	}
	return flx_gql_datetime( FLXC_Val::int(floor( $ms / 1000 )) );
}

/**
 * @param array<mixed,mixed> $js Message for the classic client.
 * @return array<string,mixed>
 */
function flxc_gql_message( array $js ): array {
	$status = FLXC_Val::str(( $js['status'] ?? '' ));
	$react  = array();
	$raw    = $js['react'] ?? array();
	if ( $raw instanceof stdClass ) {
		$raw = (array) $raw;
	}
	if ( is_array( $raw ) ) {
		foreach ( $raw as $side => $emoji ) {
			if ( is_string( $emoji ) && '' !== $emoji ) {
				$react[] = array(
					'side'  => FLXC_Val::str($side),
					'emoji' => $emoji,
				);
			}
		}
	}
	$media = null;
	if ( isset( $js['media'] ) && is_array( $js['media'] ) ) {
		$m     = $js['media'];
		$media = array(
			'file'  => flx_gql_file(
				array(
					'id'   => FLXC_Val::str(( $js['id'] ?? '' )),
					'name' => FLXC_Val::str(( $m['name'] ?? '' )),
					'mime' => FLXC_Val::str(( $m['mime'] ?? '' )),
					'size' => FLXC_Val::int(( $m['size'] ?? 0 )),
					'url'  => FLXC_Val::str(( $m['url'] ?? '' )),
				)
			),
			'ready' => ! empty( $m['ready'] ),
			'ptt'   => ! empty( $m['ptt'] ),
			'secs'  => FLXC_Val::int(( $m['secs'] ?? 0 )),
			'w'     => FLXC_Val::int(( $m['w'] ?? 0 )),
			'h'     => FLXC_Val::int(( $m['h'] ?? 0 )),
			'thumb' => FLXC_Val::str(( $m['thumb'] ?? '' )),
		);
	}
	$del = isset( $js['del'] ) && is_array( $js['del'] ) ? $js['del'] : null;
	return array(
		'databaseId'   => FLXC_Val::int(( $js['id'] ?? 0 )),
		'chatId'       => FLXC_Val::int(( $js['chat'] ?? 0 )),
		'wa'           => FLXC_Val::str(( $js['wa'] ?? '' )),
		'dir'          => FLXC_Val::str(( $js['dir'] ?? 'in' )),
		'type'         => FLXC_Val::str(( $js['type'] ?? 'text' )),
		'body'         => FLXC_Val::str(( $js['body'] ?? '' )),
		'status'       => '' !== $status ? $status : null,
		'error'        => FLXC_Val::str(( $js['error'] ?? '' )),
		'author'       => FLXC_Val::str(( $js['user'] ?? '' )),
		'authorId'     => FLXC_Val::int(( $js['userId'] ?? 0 )),
		'source'       => FLXC_Val::str(( $js['source'] ?? '' )),
		'sender'       => FLXC_Val::str(( $js['sender'] ?? '' )),
		'sentAt'       => flxc_gql_ms_time( $js['ts'] ?? 0 ),
		'ts'           => flxc_gql_ms( $js['ts'] ?? 0 ),
		'rev'          => flxc_gql_ms( $js['rev'] ?? 0 ),
		'edited'       => ! empty( $js['edited'] ),
		'editUntil'    => isset( $js['editUntil'] ) ? flxc_gql_ms_time( $js['editUntil'] ) : null,
		'revokeUntil'  => isset( $js['revokeUntil'] ) ? flxc_gql_ms_time( $js['revokeUntil'] ) : null,
		'deleted'      => ! empty( $js['deleted'] ),
		'deletion'     => $del ? array(
			'scope' => FLXC_Val::str(( $del['scope'] ?? '' )),
			'by'    => FLXC_Val::str(( $del['by'] ?? '' )),
			'at'    => flxc_gql_ms_time( $del['at'] ?? 0 ),
			'empty' => ! empty( $del['empty'] ),
		) : null,
		'reactions'    => $react,
		'linkPreview'  => isset( $js['lp'] ) && is_array( $js['lp'] ) ? $js['lp'] : null,
		'linkPreviewSkipped' => isset( $js['lp'] ) && false === $js['lp'],
		'receipts'     => isset( $js['receipts'] ) && is_array( $js['receipts'] ) ? array_values( $js['receipts'] ) : array(),
		'rcheck'       => isset( $js['rcheck'] ) && is_array( $js['rcheck'] ) ? array(
			'verdict' => FLXC_Val::str(( $js['rcheck']['v'] ?? '' )),
			'summary' => FLXC_Val::str(( $js['rcheck']['s'] ?? '' )),
		) : null,
		'origin'       => isset( $js['origin'] ) && is_array( $js['origin'] ) ? array(
			'label' => FLXC_Val::str(( $js['origin']['label'] ?? '' )),
			'url'   => FLXC_Val::str(( $js['origin']['url'] ?? '' )),
			'kind'  => FLXC_Val::str(( $js['origin']['kind'] ?? '' )),
		) : null,
		'media'        => $media,
		'quoted'       => isset( $js['quoted'] ) && is_array( $js['quoted'] ) ? array(
			'databaseId' => FLXC_Val::int(( $js['quoted']['id'] ?? 0 )),
			'dir'        => FLXC_Val::str(( $js['quoted']['dir'] ?? 'in' )),
			'type'       => FLXC_Val::str(( $js['quoted']['type'] ?? 'text' )),
			'body'       => FLXC_Val::str(( $js['quoted']['body'] ?? '' )),
			'author'     => FLXC_Val::str(( $js['quoted']['user'] ?? '' )),
		) : null,
	);
}

/**
 * @param array<string,mixed>|WP_Error $result Service result.
 * @return array<string,mixed>|WP_Error
 */
function flxc_gql_message_payload( $result ) {
	if ( $result instanceof WP_Error ) {
		return $result;
	}
	if ( ! isset( $result['message'] ) || ! is_array( $result['message'] ) ) {
		return array();
	}
	return array( 'message' => flxc_gql_message( $result['message'] ) );
}

$flxc_gql_use    = static fn(): bool => flxc_can_use();
$flxc_gql_manage = static fn(): bool => flxc_can_manage();

flx_gql_enum(
	'FlxChatStatus',
	array(
		'description' => __( 'Open or closed.', 'flx-chat' ),
		'values'      => array(
			'OPEN'   => array( 'value' => 'open', 'description' => __( 'Open.', 'flx-chat' ) ),
			'CLOSED' => array( 'value' => 'closed', 'description' => __( 'Closed.', 'flx-chat' ) ),
		),
	)
);
flx_gql_enum(
	'FlxChatFilter',
	array(
		'description' => __( 'Which chats the list shows. ALL is the open inbox and leaves closed chats out.', 'flx-chat' ),
		'values'      => array(
			'ALL'        => array( 'value' => 'all', 'description' => __( 'Open chats.', 'flx-chat' ) ),
			'UNREAD'     => array( 'value' => 'unread', 'description' => __( 'Unread open chats.', 'flx-chat' ) ),
			'MINE'       => array( 'value' => 'mine', 'description' => __( 'Assigned to the viewer.', 'flx-chat' ) ),
			'UNASSIGNED' => array( 'value' => 'unassigned', 'description' => __( 'Not assigned.', 'flx-chat' ) ),
			'AI'         => array( 'value' => 'ai', 'description' => __( 'The assistant is on.', 'flx-chat' ) ),
			'CLOSED'     => array( 'value' => 'closed', 'description' => __( 'Closed chats.', 'flx-chat' ) ),
			'ANY'        => array( 'value' => 'any', 'description' => __( 'Open and closed.', 'flx-chat' ) ),
		),
	)
);
flx_gql_enum(
	'FlxChatDir',
	array(
		'description' => __( 'Who the line belongs to.', 'flx-chat' ),
		'values'      => array(
			'IN'     => array( 'value' => 'in', 'description' => __( 'From the contact.', 'flx-chat' ) ),
			'OUT'    => array( 'value' => 'out', 'description' => __( 'From the team.', 'flx-chat' ) ),
			'NOTE'   => array( 'value' => 'note', 'description' => __( 'Internal note.', 'flx-chat' ) ),
			'SYSTEM' => array( 'value' => 'system', 'description' => __( 'System line.', 'flx-chat' ) ),
		),
	)
);
flx_gql_enum(
	'FlxChatMsgStatus',
	array(
		'description' => __( 'Delivery of an outgoing message. Empty on the wire is null.', 'flx-chat' ),
		'values'      => array(
			'PENDING'   => array( 'value' => 'pending' ),
			'SENT'      => array( 'value' => 'sent' ),
			'DELIVERED' => array( 'value' => 'delivered' ),
			'READ'      => array( 'value' => 'read' ),
			'PLAYED'    => array( 'value' => 'played' ),
			'FAILED'    => array( 'value' => 'failed' ),
		),
	)
);
flx_gql_enum(
	'FlxChatBot',
	array(
		'description' => __( 'Assistant on this chat.', 'flx-chat' ),
		'values'      => array(
			'ON'     => array( 'value' => 'on' ),
			'OFF'    => array( 'value' => 'off' ),
			'PAUSED' => array( 'value' => 'paused' ),
		),
	)
);

flx_gql_type(
	'FlxChatLastMessage',
	array(
		'description' => __( 'Preview of the latest line.', 'flx-chat' ),
		'fields'      => array(
			'dir'    => array( 'type' => 'FlxChatDir!', 'description' => __( 'Direction.', 'flx-chat' ) ),
			'type'   => array( 'type' => 'String!', 'description' => __( 'Message type.', 'flx-chat' ) ),
			'body'   => array( 'type' => 'String!', 'description' => __( 'Preview text.', 'flx-chat' ) ),
			'status' => array( 'type' => 'FlxChatMsgStatus', 'description' => __( 'Delivery. Null when the line has none.', 'flx-chat' ) ),
			'user'   => array( 'type' => 'String!', 'description' => __( 'Author name.', 'flx-chat' ) ),
		),
	)
);
flx_gql_type(
	'FlxChatReaction',
	array(
		'description' => __( 'One reaction.', 'flx-chat' ),
		'fields'      => array(
			'side'  => array( 'type' => 'String!', 'description' => __( 'in, out, or a group sender id.', 'flx-chat' ) ),
			'emoji' => array( 'type' => 'String!', 'description' => __( 'Emoji.', 'flx-chat' ) ),
		),
	)
);
flx_gql_type(
	'FlxChatDeletion',
	array(
		'description' => __( 'Who deleted a line and how.', 'flx-chat' ),
		'fields'      => array(
			'scope' => array( 'type' => 'String!', 'description' => __( 'contact, phone, everyone or panel.', 'flx-chat' ) ),
			'by'    => array( 'type' => 'String!', 'description' => __( 'Name. Empty when the contact deleted it.', 'flx-chat' ) ),
			'at'    => array( 'type' => 'FlxDateTime', 'description' => __( 'When. Null when unknown.', 'flx-chat' ) ),
			'empty' => array( 'type' => 'Boolean!', 'description' => __( 'The body was not kept.', 'flx-chat' ) ),
		),
	)
);
flx_gql_type(
	'FlxChatLinkPreview',
	array(
		'description' => __( 'Card for a link in a message.', 'flx-chat' ),
		'fields'      => array(
			'url'    => array( 'type' => 'String!', 'description' => __( 'URL.', 'flx-chat' ) ),
			'title'  => array( 'type' => 'String!', 'description' => __( 'Title.', 'flx-chat' ) ),
			'desc'   => array( 'type' => 'String!', 'description' => __( 'Description.', 'flx-chat' ) ),
			'image'  => array( 'type' => 'String!', 'description' => __( 'Image URL. Empty when none.', 'flx-chat' ) ),
			'site'   => array( 'type' => 'String!', 'description' => __( 'Site name.', 'flx-chat' ) ),
			'domain' => array( 'type' => 'String!', 'description' => __( 'Domain.', 'flx-chat' ) ),
		),
	)
);
flx_gql_type(
	'FlxChatOrigin',
	array(
		'description' => __( 'Campaign or record the outgoing line came from.', 'flx-chat' ),
		'fields'      => array(
			'label' => array( 'type' => 'String!', 'description' => __( 'Label.', 'flx-chat' ) ),
			'url'   => array( 'type' => 'String!', 'description' => __( 'Detail URL. Empty when none.', 'flx-chat' ) ),
			'kind'  => array( 'type' => 'String!', 'description' => __( 'Kind.', 'flx-chat' ) ),
		),
	)
);
flx_gql_type(
	'FlxChatRcheck',
	array(
		'description' => __( 'AI summary of a receipt check.', 'flx-chat' ),
		'fields'      => array(
			'verdict' => array( 'type' => 'String!', 'description' => __( 'Verdict.', 'flx-chat' ) ),
			'summary' => array( 'type' => 'String!', 'description' => __( 'Short text.', 'flx-chat' ) ),
		),
	)
);
flx_gql_type(
	'FlxChatReceiptRef',
	array(
		'description' => __( 'A donation or certificate matched to a message.', 'flx-chat' ),
		'fields'      => array(
			'k'  => array( 'type' => 'String!', 'description' => __( 'donation or certificate.', 'flx-chat' ), 'resolve' => static fn( $r ): string => FLXC_Val::str(( is_array( $r ) ? ( $r['k'] ?? '' ) : '' )) ),
			'id' => array( 'type' => 'Int!', 'description' => __( 'Record id.', 'flx-chat' ), 'resolve' => static fn( $r ): int => FLXC_Val::int(( is_array( $r ) ? ( $r['id'] ?? 0 ) : 0 )) ),
		),
	)
);
flx_gql_type(
	'FlxChatMedia',
	array(
		'description' => __( 'File on a message. The URL is for this viewer only.', 'flx-chat' ),
		'fields'      => array(
			'file'  => array( 'type' => 'FlxFile!', 'description' => __( 'File. The URL carries this viewer\'s nonce and is not shared across users.', 'flx-chat' ) ),
			'ready' => array( 'type' => 'Boolean!', 'description' => __( 'The bytes are on disk.', 'flx-chat' ) ),
			'ptt'   => array( 'type' => 'Boolean!', 'description' => __( 'Voice note. False for an audio file.', 'flx-chat' ) ),
			'secs'  => array( 'type' => 'Int!', 'description' => __( 'Length in seconds.', 'flx-chat' ) ),
			'w'     => array( 'type' => 'Int!', 'description' => __( 'Width.', 'flx-chat' ) ),
			'h'     => array( 'type' => 'Int!', 'description' => __( 'Height.', 'flx-chat' ) ),
			'thumb' => array( 'type' => 'String!', 'description' => __( 'Thumbnail. Empty when none.', 'flx-chat' ) ),
		),
	)
);
flx_gql_type(
	'FlxChatQuoted',
	array(
		'description' => __( 'The line a message quotes.', 'flx-chat' ),
		'fields'      => array(
			'databaseId' => array( 'type' => 'Int!', 'description' => __( 'Message id.', 'flx-chat' ) ),
			'dir'        => array( 'type' => 'FlxChatDir!', 'description' => __( 'Direction.', 'flx-chat' ) ),
			'type'       => array( 'type' => 'String!', 'description' => __( 'Type.', 'flx-chat' ) ),
			'body'       => array( 'type' => 'String!', 'description' => __( 'Text.', 'flx-chat' ) ),
			'author'     => array( 'type' => 'String!', 'description' => __( 'Author.', 'flx-chat' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatMessage',
	array(
		'description' => __( 'One line. The parent query already checked the chat.', 'flx-chat' ),
		'fields'      => array(
			'databaseId'          => array( 'type' => 'Int!', 'description' => __( 'Message id.', 'flx-chat' ) ),
			'chatId'              => array( 'type' => 'Int!', 'description' => __( 'Chat id.', 'flx-chat' ) ),
			'wa'                  => array( 'type' => 'String!', 'description' => __( 'Provider id. Empty when none.', 'flx-chat' ) ),
			'dir'                 => array( 'type' => 'FlxChatDir!', 'description' => __( 'Direction.', 'flx-chat' ) ),
			'type'                => array( 'type' => 'String!', 'description' => __( 'Provider type. A voice note is audio with media.ptt.', 'flx-chat' ) ),
			'body'                => array( 'type' => 'String!', 'description' => __( 'Text.', 'flx-chat' ) ),
			'status'              => array( 'type' => 'FlxChatMsgStatus', 'description' => __( 'Delivery. Null when the line has none.', 'flx-chat' ) ),
			'error'               => array( 'type' => 'String!', 'description' => __( 'Send error. Empty when none.', 'flx-chat' ) ),
			'author'              => array( 'type' => 'String!', 'description' => __( 'Author name.', 'flx-chat' ) ),
			'authorId'            => array( 'type' => 'Int!', 'description' => __( 'Author user id. 0 for the contact.', 'flx-chat' ) ),
			'source'              => array( 'type' => 'String!', 'description' => __( 'agent, ai, quick, system, record, …', 'flx-chat' ) ),
			'sender'              => array( 'type' => 'String!', 'description' => __( 'Group sender id. Empty in a direct chat.', 'flx-chat' ) ),
			'sentAt'              => array( 'type' => 'FlxDateTime', 'description' => __( 'When it was sent. Null when unknown.', 'flx-chat' ) ),
			'ts'                  => array( 'type' => 'String!', 'description' => __( 'Milliseconds as a decimal string.', 'flx-chat' ) ),
			'rev'                 => array( 'type' => 'String!', 'description' => __( 'Revision milliseconds as a decimal string.', 'flx-chat' ) ),
			'edited'              => array( 'type' => 'Boolean!', 'description' => __( 'The text was edited.', 'flx-chat' ) ),
			'editUntil'           => array( 'type' => 'FlxDateTime', 'description' => __( 'Edit deadline. Null when it cannot be edited.', 'flx-chat' ) ),
			'revokeUntil'         => array( 'type' => 'FlxDateTime', 'description' => __( 'Delete-for-everyone deadline. Null when only the panel can hide it.', 'flx-chat' ) ),
			'deleted'             => array( 'type' => 'Boolean!', 'description' => __( 'Deleted.', 'flx-chat' ) ),
			'deletion'            => array( 'type' => 'FlxChatDeletion', 'description' => __( 'Deletion audit. Null when the line is not deleted.', 'flx-chat' ) ),
			'reactions'           => array( 'type' => '[FlxChatReaction!]!', 'description' => __( 'Reactions.', 'flx-chat' ) ),
			'linkPreview'         => array( 'type' => 'FlxChatLinkPreview', 'description' => __( 'Link card. Null when there is none.', 'flx-chat' ) ),
			'linkPreviewSkipped'  => array( 'type' => 'Boolean!', 'description' => __( 'The sender turned the card off.', 'flx-chat' ) ),
			'receipts'            => array( 'type' => '[FlxChatReceiptRef!]!', 'description' => __( 'Matched records.', 'flx-chat' ) ),
			'rcheck'              => array( 'type' => 'FlxChatRcheck', 'description' => __( 'AI receipt check. Null when none.', 'flx-chat' ) ),
			'origin'              => array( 'type' => 'FlxChatOrigin', 'description' => __( 'Campaign origin. Null when the line was typed here.', 'flx-chat' ) ),
			'media'               => array( 'type' => 'FlxChatMedia', 'description' => __( 'File. Null for text. Resolved for this viewer.', 'flx-chat' ) ),
			'quoted'              => array( 'type' => 'FlxChatQuoted', 'description' => __( 'Quoted line. Null when none.', 'flx-chat' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatSectionItem',
	array(
		'description' => __( 'One row in the contact panel.', 'flx-chat' ),
		'fields'      => array(
			'label' => array( 'type' => 'String!', 'description' => __( 'Label.', 'flx-chat' ) ),
			'sub'   => array( 'type' => 'String!', 'description' => __( 'Second line.', 'flx-chat' ) ),
			'url'   => array( 'type' => 'String!', 'description' => __( 'Link. Empty when none.', 'flx-chat' ) ),
			'tone'  => array( 'type' => 'String!', 'description' => __( 'Tone.', 'flx-chat' ) ),
		),
	)
);
flx_gql_type(
	'FlxChatSection',
	array(
		'description' => __( 'A block in the contact panel of one chat.', 'flx-chat' ),
		'fields'      => array(
			'id'    => array( 'type' => 'String!', 'description' => __( 'Id.', 'flx-chat' ), 'resolve' => static fn( $s ): string => FLXC_Val::str(( is_array( $s ) ? ( $s['id'] ?? '' ) : '' )) ),
			'title' => array( 'type' => 'String!', 'description' => __( 'Title.', 'flx-chat' ), 'resolve' => static fn( $s ): string => FLXC_Val::str(( is_array( $s ) ? ( $s['title'] ?? '' ) : '' )) ),
			'items' => array(
				'type'        => '[FlxChatSectionItem!]!',
				'description' => __( 'Rows.', 'flx-chat' ),
				'resolve'     => static function ( $s ): array {
					$items = is_array( $s ) && isset( $s['items'] ) && is_array( $s['items'] ) ? $s['items'] : array();
					$out   = array();
					foreach ( $items as $item ) {
						if ( ! is_array( $item ) ) {
							continue;
						}
						$out[] = array(
							'label' => FLXC_Val::str(( $item['label'] ?? '' )),
							'sub'   => FLXC_Val::str(( $item['sub'] ?? '' )),
							'url'   => FLXC_Val::str(( $item['url'] ?? '' )),
							'tone'  => FLXC_Val::str(( $item['tone'] ?? '' )),
						);
					}
					return $out;
				},
			),
		),
	)
);
flx_gql_type(
	'FlxChatLogEntry',
	array(
		'description' => __( 'A change to the contact on this chat.', 'flx-chat' ),
		'fields'      => array(
			'text' => array( 'type' => 'String!', 'description' => __( 'What changed.', 'flx-chat' ), 'resolve' => static fn( $e ): string => FLXC_Val::str(( is_array( $e ) ? ( $e['text'] ?? $e['note'] ?? '' ) : '' )) ),
			'at'   => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'When. Null when unknown.', 'flx-chat' ),
				'resolve'     => static function ( $e ): ?array {
					if ( ! is_array( $e ) ) {
						return null;
					}
					$ts = FLXC_Val::int(( $e['ts'] ?? $e['at'] ?? 0 ));
					if ( $ts > 100000000000 ) {
						$ts = FLXC_Val::int(floor( $ts / 1000 ));
					}
					return $ts > 0 ? flx_gql_datetime( $ts ) : null;
				},
			),
		),
	)
);

flx_gql_type(
	'FlxChat',
	array(
		'description' => __( 'A WhatsApp or site chat the viewer is allowed to see.', 'flx-chat' ),
		'node'        => FLXC_Chat_Loader::class,
		'fields'      => array(
			'connectionId' => array( 'type' => 'String!', 'description' => __( 'Connection id.', 'flx-chat' ), 'resolve' => static fn( $c ): string => FLXC_Val::str(flxc_gql_chat( $c )->js()['conn']) ),
			'jid'          => array( 'type' => 'String!', 'description' => __( 'Provider id.', 'flx-chat' ), 'resolve' => static fn( $c ): string => FLXC_Val::str(flxc_gql_chat( $c )->js()['jid']) ),
			'phoneDigits'  => array( 'type' => 'String!', 'description' => __( 'Digits. Empty for a hidden or group number.', 'flx-chat' ), 'resolve' => static fn( $c ): string => FLXC_Val::str(flxc_gql_chat( $c )->js()['phone']) ),
			'phoneFmt'     => array( 'type' => 'String!', 'description' => __( 'Number as shown.', 'flx-chat' ), 'resolve' => static fn( $c ): string => FLXC_Val::str(flxc_gql_chat( $c )->js()['phoneFmt']) ),
			'phone'        => array(
				'type'        => 'FlxPhone',
				'description' => __( 'Number. Null when the chat has none.', 'flx-chat' ),
				'resolve'     => static function ( $c ): ?array {
					$digits = FLXC_Val::str(flxc_gql_chat( $c )->js()['phone']);
					return '' !== $digits ? flx_gql_phone( $digits ) : null;
				},
			),
			'channel'      => array( 'type' => 'String!', 'description' => __( 'Channel id.', 'flx-chat' ), 'resolve' => static fn( $c ): string => FLXC_Val::str(flxc_gql_chat( $c )->js()['channel']) ),
			'title'        => array( 'type' => 'String!', 'description' => __( 'Title.', 'flx-chat' ), 'resolve' => static fn( $c ): string => FLXC_Val::str(flxc_gql_chat( $c )->js()['title']) ),
			'name'         => array( 'type' => 'String!', 'description' => __( 'Saved name.', 'flx-chat' ), 'resolve' => static fn( $c ): string => FLXC_Val::str(flxc_gql_chat( $c )->js()['name']) ),
			'pushName'     => array( 'type' => 'String!', 'description' => __( 'Name from WhatsApp.', 'flx-chat' ), 'resolve' => static fn( $c ): string => FLXC_Val::str(flxc_gql_chat( $c )->js()['pushName']) ),
			'avatarUrl'    => array( 'type' => 'String!', 'description' => __( 'Photo for this viewer. Empty when none.', 'flx-chat' ), 'resolve' => static fn( $c ): string => FLXC_Val::str(flxc_gql_chat( $c )->js()['avatar']) ),
			'avatarAt'     => array( 'type' => 'FlxDateTime', 'description' => __( 'When the photo was fetched. Null when never.', 'flx-chat' ), 'resolve' => static fn( $c ): ?array => flxc_gql_ms_time( FLXC_Val::int(flxc_gql_chat( $c )->js()['avatarAt']) * 1000 ) ),
			'labels'       => array(
				'type'        => '[String!]!',
				'description' => __( 'Label ids.', 'flx-chat' ),
				'resolve'     => static function ( $c ): array {
					$labels = flxc_gql_chat( $c )->js()['labels'] ?? array();
					$labels = is_array( $labels ) ? $labels : array();
					return array_values( array_map( static fn( $v ): string => FLXC_Val::str( $v ), $labels ) );
				},
			),
			'notes'        => array( 'type' => 'String!', 'description' => __( 'Internal notes.', 'flx-chat' ), 'resolve' => static fn( $c ): string => FLXC_Val::str(flxc_gql_chat( $c )->js()['notes']) ),
			'status'       => array( 'type' => 'FlxChatStatus!', 'description' => __( 'Open or closed.', 'flx-chat' ), 'resolve' => static fn( $c ): string => FLXC_Val::str(flxc_gql_chat( $c )->js()['status']) ),
			'assignedTo'   => array( 'type' => 'Int!', 'description' => __( 'Agent user id. 0 when unassigned.', 'flx-chat' ), 'resolve' => static fn( $c ): int => FLXC_Val::int(flxc_gql_chat( $c )->js()['assigned']) ),
			'staffUser'    => array(
				'type'        => 'Int',
				'description' => __( 'Linked staff user. Null for an agent; only a manager sees it.', 'flx-chat' ),
				'resolve'     => static function ( $c ): ?int {
					if ( ! flxc_can_manage() ) {
						return null;
					}
					$js = flxc_gql_chat( $c )->js();
					return isset( $js['staffUser'] ) ? FLXC_Val::int($js['staffUser']) : 0;
				},
			),
			'unread'       => array( 'type' => 'Int!', 'description' => __( 'Unread count.', 'flx-chat' ), 'resolve' => static fn( $c ): int => FLXC_Val::int(flxc_gql_chat( $c )->js()['unread']) ),
			'pinned'       => array( 'type' => 'Boolean!', 'description' => __( 'Pinned.', 'flx-chat' ), 'resolve' => static fn( $c ): bool => FLXC_Val::bool(flxc_gql_chat( $c )->js()['pinned']) ),
			'lastMs'       => array( 'type' => 'String!', 'description' => __( 'Last message time, milliseconds.', 'flx-chat' ), 'resolve' => static fn( $c ): string => flxc_gql_ms( flxc_gql_chat( $c )->js()['lastTs'] ) ),
			'lastAt'       => array( 'type' => 'FlxDateTime', 'description' => __( 'Last message time. Null when the chat is empty.', 'flx-chat' ), 'resolve' => static fn( $c ): ?array => flxc_gql_ms_time( flxc_gql_chat( $c )->js()['lastTs'] ) ),
			'lastInMs'     => array( 'type' => 'String!', 'description' => __( 'Last incoming time, milliseconds.', 'flx-chat' ), 'resolve' => static fn( $c ): string => flxc_gql_ms( flxc_gql_chat( $c )->js()['lastInTs'] ) ),
			'group'        => array( 'type' => 'Boolean!', 'description' => __( 'Group chat.', 'flx-chat' ), 'resolve' => static fn( $c ): bool => FLXC_Val::bool(flxc_gql_chat( $c )->js()['group']) ),
			'typing'       => array( 'type' => 'Int!', 'description' => __( '0 idle, 1 composing, 2 recording.', 'flx-chat' ), 'resolve' => static fn( $c ): int => FLXC_Val::int(flxc_gql_chat( $c )->js()['typing']) ),
			'rev'          => array( 'type' => 'String!', 'description' => __( 'Revision milliseconds.', 'flx-chat' ), 'resolve' => static fn( $c ): string => flxc_gql_ms( flxc_gql_chat( $c )->js()['rev'] ) ),
			'createdMs'    => array( 'type' => 'String!', 'description' => __( 'Created at, milliseconds.', 'flx-chat' ), 'resolve' => static fn( $c ): string => flxc_gql_ms( flxc_gql_chat( $c )->js()['created'] ) ),
			'createdAt'    => array( 'type' => 'FlxDateTime', 'description' => __( 'Created at.', 'flx-chat' ), 'resolve' => static fn( $c ): ?array => flxc_gql_ms_time( flxc_gql_chat( $c )->js()['created'] ) ),
			'unknownName'  => array( 'type' => 'Boolean!', 'description' => __( 'No saved name.', 'flx-chat' ), 'resolve' => static fn( $c ): bool => FLXC_Val::bool(( flxc_gql_chat( $c )->js()['unknownName'] ?? false )) ),
			'blocked'      => array(
				'type'        => 'Boolean!',
				'description' => __( 'On the block list.', 'flx-chat' ),
				'resolve'     => static function ( $c ): bool {
					$meta = flxc_gql_chat( $c )->js()['meta'] ?? null;
					return is_array( $meta ) && ! empty( $meta['blocked'] );
				},
			),
			'optOut'       => array( 'type' => 'Boolean!', 'description' => __( 'The contact opted out.', 'flx-chat' ), 'resolve' => static fn( $c ): bool => FLXC_Val::bool(( flxc_gql_chat( $c )->js()['optout'] ?? false )) ),
			'bot'          => array(
				'type'        => 'FlxChatBot',
				'description' => __( 'Assistant state. Null when the assistant is not in use on this chat.', 'flx-chat' ),
				'resolve'     => static function ( $c ): ?string {
					$bot = FLXC_Val::str(( flxc_gql_chat( $c )->js()['bot'] ?? '' ));
					return '' !== $bot ? $bot : null;
				},
			),
			'botBusy'      => array( 'type' => 'Boolean!', 'description' => __( 'The assistant is writing.', 'flx-chat' ), 'resolve' => static fn( $c ): bool => FLXC_Val::bool(( flxc_gql_chat( $c )->js()['botBusy'] ?? false )) ),
			'ai'           => array( 'type' => 'Boolean!', 'description' => __( 'The assistant has handled this chat.', 'flx-chat' ), 'resolve' => static fn( $c ): bool => FLXC_Val::bool(( flxc_gql_chat( $c )->js()['ai'] ?? false )) ),
			'lastMessage'  => array(
				'type'        => 'FlxChatLastMessage',
				'description' => __( 'Latest line. Null when the chat is empty.', 'flx-chat' ),
				'resolve'     => static function ( $c ): ?array {
					$last = flxc_gql_chat( $c )->js()['last'] ?? null;
					if ( ! is_array( $last ) ) {
						return null;
					}
					$status = FLXC_Val::str(( $last['status'] ?? '' ));
					return array(
						'dir'    => FLXC_Val::str(( $last['dir'] ?? 'in' )),
						'type'   => FLXC_Val::str(( $last['type'] ?? 'text' )),
						'body'   => FLXC_Val::str(( $last['body'] ?? '' )),
						'status' => '' !== $status ? $status : null,
						'user'   => FLXC_Val::str(( $last['user'] ?? '' )),
					);
				},
			),
			'person'       => array(
				'type'        => 'FlxPerson',
				'description' => __( 'Shared person for this phone. Null when CRM has no record or the viewer cannot see a chat with this phone.', 'flx-chat' ),
				'resolve'     => static function ( $c, array $args, $context ) {
					unset( $args );
					$phone = FLXC_Val::str(flxc_gql_chat( $c )->js()['phone']);
					if ( '' === $phone || ! is_object( $context ) || ! method_exists( $context, 'get_loader' ) || ! class_exists( 'FLXCRM_Contact_Node' ) ) {
						return null;
					}
					$loader = $context->get_loader( 'flxcPhonePerson' );
					return is_object( $loader ) && method_exists( $loader, 'load_deferred' ) ? $loader->load_deferred( $phone ) : null;
				},
			),
			'sections'     => array(
				'type'        => '[FlxChatSection!]!',
				'description' => __( 'Contact panel. Empty on a list row; filled on flxChat.', 'flx-chat' ),
				'resolve'     => static function ( $c ): array {
					$node = flxc_gql_chat( $c );
					if ( ! $node->panel ) {
						return array();
					}
					$sections = flxc_contact_sections( $node->row );
					return is_array( $sections ) ? $sections : array();
				},
			),
			'log'          => array(
				'type'        => '[FlxChatLogEntry!]!',
				'description' => __( 'Contact change log. Empty on a list row.', 'flx-chat' ),
				'resolve'     => static function ( $c ): array {
					$node = flxc_gql_chat( $c );
					if ( ! $node->panel || ! function_exists( 'flxc_clog_for_js' ) ) {
						return array();
					}
					$log = flxc_clog_for_js( FLXC_Val::int($node->row['id']), 100 );
					return is_array( $log ) ? $log : array();
				},
			),
		),
	)
);

flx_gql_connection( 'FlxChat', __( 'A page of chats.', 'flx-chat' ) );

flx_gql_type(
	'FlxChatMessagePage',
	array(
		'description' => __( 'Older lines of one chat. before is a message id, not an offset cursor.', 'flx-chat' ),
		'fields'      => array(
			'messages' => array( 'type' => '[FlxChatMessage!]!', 'description' => __( 'Lines, oldest first in this page.', 'flx-chat' ) ),
			'hasMore'  => array( 'type' => 'Boolean!', 'description' => __( 'More history exists.', 'flx-chat' ) ),
			'note'     => array( 'type' => 'String!', 'description' => __( 'History fetch note. Empty when none.', 'flx-chat' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatIncoming',
	array(
		'description' => __( 'A new incoming line, for the notification.', 'flx-chat' ),
		'fields'      => array(
			'databaseId' => array( 'type' => 'Int!', 'description' => __( 'Message id.', 'flx-chat' ) ),
			'chatId'     => array( 'type' => 'Int!', 'description' => __( 'Chat id.', 'flx-chat' ) ),
			'title'      => array( 'type' => 'String!', 'description' => __( 'Chat title.', 'flx-chat' ) ),
			'body'       => array( 'type' => 'String!', 'description' => __( 'Preview.', 'flx-chat' ) ),
		),
	)
);
flx_gql_type(
	'FlxChatConnState',
	array(
		'description' => __( 'Connection state. No keys.', 'flx-chat' ),
		'fields'      => array(
			'id'    => array( 'type' => 'String!', 'description' => __( 'Id.', 'flx-chat' ) ),
			'label' => array( 'type' => 'String!', 'description' => __( 'Label.', 'flx-chat' ) ),
			'color' => array( 'type' => 'String!', 'description' => __( 'Color.', 'flx-chat' ) ),
			'state' => array( 'type' => 'String!', 'description' => __( 'State.', 'flx-chat' ) ),
			'at'    => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'When the state changed. Null when unknown.', 'flx-chat' ),
				'resolve'     => static function ( $c ): ?array {
					if ( ! is_array( $c ) || empty( $c['at'] ) ) {
						return null;
					}
					return flx_gql_datetime( FLXC_Val::int( $c['at'] ) );
				},
			),
		),
	)
);
flx_gql_type(
	'FlxChatUpdates',
	array(
		'description' => __( 'Changes since a revision. A truncated page is ordered by rev ascending; continue from continueRev and continueId.', 'flx-chat' ),
		'fields'      => array(
			'rev'               => array( 'type' => 'String!', 'description' => __( 'Current revision. Do not use this as since while truncated is true.', 'flx-chat' ) ),
			'continueRev'       => array( 'type' => 'String!', 'description' => __( 'Pass as since on the next poll.', 'flx-chat' ) ),
			'continueId'        => array( 'type' => 'Int!', 'description' => __( 'Pass as afterChat when truncated, otherwise 0.', 'flx-chat' ) ),
			'truncated'         => array( 'type' => 'Boolean!', 'description' => __( 'More chats changed than this page holds.', 'flx-chat' ) ),
			'messagesTruncated' => array( 'type' => 'Boolean!', 'description' => __( 'More new lines in the open chat. Ask again with a higher after.', 'flx-chat' ) ),
			'chats'             => array( 'type' => '[FlxChat!]!', 'description' => __( 'Changed chats, oldest revision first, at most 50.', 'flx-chat' ) ),
			'messages'          => array( 'type' => '[FlxChatMessage!]!', 'description' => __( 'New lines of the open chat, at most 50.', 'flx-chat' ) ),
			'incoming'          => array( 'type' => '[FlxChatIncoming!]!', 'description' => __( 'Incoming lines for notifications.', 'flx-chat' ) ),
			'connections'       => array( 'type' => '[FlxChatConnState!]!', 'description' => __( 'Connection states. No secrets.', 'flx-chat' ) ),
			'unread'            => array( 'type' => 'Int!', 'description' => __( 'Unread total the viewer can see.', 'flx-chat' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatAgent',
	array(
		'description' => __( 'Someone who can be assigned a chat.', 'flx-chat' ),
		'fields'      => array(
			'databaseId' => array( 'type' => 'Int!', 'description' => __( 'User id.', 'flx-chat' ), 'resolve' => static fn( $a ): int => FLXC_Val::int(( is_array( $a ) ? ( $a['id'] ?? 0 ) : 0 )) ),
			'name'       => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-chat' ), 'resolve' => static fn( $a ): string => FLXC_Val::str(( is_array( $a ) ? ( $a['name'] ?? '' ) : '' )) ),
		),
	)
);
flx_gql_type(
	'FlxChatLabel',
	array(
		'description' => __( 'A chat label.', 'flx-chat' ),
		'fields'      => array(
			'id'    => array( 'type' => 'String!', 'description' => __( 'Id.', 'flx-chat' ) ),
			'name'  => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-chat' ) ),
			'color' => array( 'type' => 'String!', 'description' => __( 'Color.', 'flx-chat' ) ),
		),
	)
);
flx_gql_type(
	'FlxChatReply',
	array(
		'description' => __( 'A quick reply.', 'flx-chat' ),
		'fields'      => array(
			'databaseId' => array( 'type' => 'Int!', 'description' => __( 'Id.', 'flx-chat' ), 'resolve' => static fn( $r ): int => FLXC_Val::int(( is_array( $r ) ? ( $r['id'] ?? 0 ) : 0 )) ),
			'shortcut'   => array( 'type' => 'String!', 'description' => __( 'Shortcut.', 'flx-chat' ), 'resolve' => static fn( $r ): string => FLXC_Val::str(( is_array( $r ) ? ( $r['shortcut'] ?? '' ) : '' )) ),
			'title'      => array( 'type' => 'String!', 'description' => __( 'Title.', 'flx-chat' ), 'resolve' => static fn( $r ): string => FLXC_Val::str(( is_array( $r ) ? ( $r['title'] ?? '' ) : '' )) ),
			'body'       => array( 'type' => 'String!', 'description' => __( 'Body.', 'flx-chat' ), 'resolve' => static fn( $r ): string => FLXC_Val::str(( is_array( $r ) ? ( $r['body'] ?? '' ) : '' )) ),
		),
	)
);
flx_gql_type(
	'FlxChatMe',
	array(
		'description' => __( 'The viewer.', 'flx-chat' ),
		'fields'      => array(
			'databaseId' => array( 'type' => 'Int!', 'description' => __( 'User id.', 'flx-chat' ) ),
			'name'       => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-chat' ) ),
			'manage'     => array( 'type' => 'Boolean!', 'description' => __( 'Manager.', 'flx-chat' ) ),
		),
	)
);
flx_gql_type(
	'FlxChatInbox',
	array(
		'description' => __( 'What the chat page needs to open. No secrets.', 'flx-chat' ),
		'fields'      => array(
			'me'          => array(
				'type'        => 'FlxChatMe!',
				'description' => __( 'Viewer.', 'flx-chat' ),
				'resolve'     => static function ( $b ): array {
					$me = flx_gql_arr( flx_gql_arr( $b )['me'] ?? null );
					return array(
						'databaseId' => FLXC_Val::int( $me['id'] ?? 0 ),
						'name'        => FLXC_Val::str( $me['name'] ?? '' ),
						'manage'      => FLXC_Val::bool( $me['manage'] ?? false ),
					);
				},
			),
			'delHours'    => array( 'type' => 'Int!', 'description' => __( 'Hours a line can be deleted for everyone.', 'flx-chat' ) ),
			'agents'      => array( 'type' => '[FlxChatAgent!]!', 'description' => __( 'Assignees.', 'flx-chat' ) ),
			'labels'      => array( 'type' => '[FlxChatLabel!]!', 'description' => __( 'Labels.', 'flx-chat' ) ),
			'connections' => array( 'type' => '[FlxChatConnState!]!', 'description' => __( 'Connections without keys.', 'flx-chat' ) ),
			'replies'     => array( 'type' => '[FlxChatReply!]!', 'description' => __( 'Quick replies.', 'flx-chat' ) ),
			'unread'      => array( 'type' => 'Int!', 'description' => __( 'Unread total.', 'flx-chat' ) ),
			'ai'          => array(
				'type'        => 'String',
				'description' => __( 'AI provider label. Null when AI is off.', 'flx-chat' ),
				'resolve'     => static function ( $b ): ?string {
					$ai = flx_gql_arr( flx_gql_arr( $b )['ai'] ?? null );
					return isset( $ai['label'] ) ? FLXC_Val::str( $ai['label'] ) : null;
				},
			),
			'bot'         => array( 'type' => 'Boolean!', 'description' => __( 'The assistant is enabled.', 'flx-chat' ) ),
			'rev'         => array( 'type' => 'String!', 'description' => __( 'Current revision.', 'flx-chat' ), 'resolve' => static fn( $b ): string => flxc_gql_ms( flx_gql_arr( $b )['rev'] ?? 0 ) ),
			'classicUrl'  => array( 'type' => 'String!', 'description' => __( 'Classic chat page.', 'flx-chat' ), 'resolve' => static fn(): string => admin_url( 'admin.php?page=flx-chat' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatCheck',
	array(
		'description' => __( 'Whether a number is on WhatsApp. A chat the viewer cannot see is omitted.', 'flx-chat' ),
		'fields'      => array(
			'valid'    => array( 'type' => 'Boolean!', 'description' => __( 'The number could be parsed.', 'flx-chat' ) ),
			'phone'    => array( 'type' => 'String!', 'description' => __( 'Digits. Empty when invalid.', 'flx-chat' ), 'resolve' => static fn( $r ): string => FLXC_Val::str( flx_gql_arr( $r )['phone'] ?? '' ) ),
			'phoneFmt' => array( 'type' => 'String!', 'description' => __( 'Display. Empty when invalid.', 'flx-chat' ), 'resolve' => static fn( $r ): string => FLXC_Val::str( flx_gql_arr( $r )['phoneFmt'] ?? '' ) ),
			'exists'   => array( 'type' => 'Boolean', 'description' => __( 'On WhatsApp. Null when the connection could not be asked.', 'flx-chat' ) ),
			'chatId'   => array( 'type' => 'Int!', 'description' => __( 'Existing visible chat. 0 when none.', 'flx-chat' ), 'resolve' => static fn( $r ): int => FLXC_Val::int( flx_gql_arr( $r )['chat'] ?? 0 ) ),
			'name'     => array( 'type' => 'String!', 'description' => __( 'Name. Not the title of a hidden chat.', 'flx-chat' ), 'resolve' => static fn( $r ): string => FLXC_Val::str( flx_gql_arr( $r )['name'] ?? '' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatUsageDay',
	array(
		'description' => __( 'Assistant use on one day.', 'flx-chat' ),
		'fields'      => array(
			'day'   => array( 'type' => 'String!', 'description' => __( 'Day.', 'flx-chat' ) ),
			'calls' => array( 'type' => 'Int!', 'description' => __( 'Calls.', 'flx-chat' ) ),
			'in'    => array( 'type' => 'Int!', 'description' => __( 'Input tokens.', 'flx-chat' ) ),
			'out'   => array( 'type' => 'Int!', 'description' => __( 'Output tokens.', 'flx-chat' ) ),
		),
	)
);
flx_gql_type(
	'FlxChatUsage',
	array(
		'description' => __( 'Assistant use of one visible chat.', 'flx-chat' ),
		'fields'      => array(
			'replies' => array( 'type' => 'Int!', 'description' => __( 'Replies today.', 'flx-chat' ) ),
			'limit'   => array( 'type' => 'Int!', 'description' => __( 'Daily limit.', 'flx-chat' ) ),
			'limited' => array( 'type' => 'Boolean!', 'description' => __( 'The limit is reached.', 'flx-chat' ) ),
			'days'    => array( 'type' => '[FlxChatUsageDay!]!', 'description' => __( 'Recent days.', 'flx-chat' ) ),
		),
	)
);

/* ---------- queries ---------- */

flx_gql_query(
	'flxChatInbox',
	array(
		'type'        => 'FlxChatInbox!',
		'description' => __( 'Inbox bootstrap. No secrets.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXC_Chat_Service::CACHE );
			$b = FLXC_Chat_Service::bootstrap();
			return $b;
		},
	)
);

flx_gql_query(
	'flxChats',
	array(
		'type'        => 'FlxChatConnection!',
		'description' => __( 'Chats the viewer can see.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'args'        => flx_gql_connection_args(
			array(
				'filter'  => array( 'type' => 'FlxChatFilter', 'description' => __( 'Filter. Open chats when omitted.', 'flx-chat' ) ),
				'search'  => array( 'type' => 'String', 'description' => __( 'Name, number, note or message text.', 'flx-chat' ) ),
				'label'   => array( 'type' => 'String', 'description' => __( 'Label id.', 'flx-chat' ) ),
				'channel' => array( 'type' => 'String', 'description' => __( 'Channel id.', 'flx-chat' ) ),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
			unset( $root );
			$filter = array(
				'filter'  => sanitize_key( FLXC_Val::str(( $args['filter'] ?? 'all' )) ),
				'q'       => sanitize_text_field( FLXC_Val::str(( $args['search'] ?? '' )) ),
				'label'   => sanitize_key( FLXC_Val::str(( $args['label'] ?? '' )) ),
				'channel' => sanitize_key( FLXC_Val::str(( $args['channel'] ?? '' )) ),
			);
			flx_gql_cache_tag( FLXC_Chat_Service::CACHE );
			return flx_gql_paginate(
				flx_gql_arr( $args ),
				static function ( int $offset, int $limit ) use ( $filter ): array {
					$rows = FLXC_Chat_Service::query_rows( $filter + array( 'offset' => $offset, 'limit' => $limit ) );
					return array(
						'items' => array_map( static fn( $row ): FLXC_Chat_Node => new FLXC_Chat_Node( $row ), $rows ),
						'total' => FLXC_Chat_Service::count( $filter ),
					);
				}
			);
		},
	)
);

flx_gql_query(
	'flxChat',
	array(
		'type'        => 'FlxChat',
		'description' => __( 'One chat, with the contact panel. Null when missing or hidden.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'args'        => array(
			'id' => array( 'type' => 'ID!', 'description' => __( 'Global id or chat number.', 'flx-chat' ) ),
		),
		'resolve'     => static function ( $root, array $args ): ?FLXC_Chat_Node {
			unset( $root );
			$row = FLXC_Chat_Service::visible( flxc_gql_chat_id( $args['id'] ) );
			flx_gql_cache_tag( FLXC_Chat_Service::CACHE );
			return null === $row ? null : new FLXC_Chat_Node( $row, true );
		},
	)
);

flx_gql_query(
	'flxChatMessages',
	array(
		'type'        => 'FlxChatMessagePage!',
		'description' => __( 'Lines of a chat the viewer can see.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'args'        => array(
			'chatId' => array( 'type' => 'ID!', 'description' => __( 'Chat.', 'flx-chat' ) ),
			'before' => array( 'type' => 'Int', 'description' => __( 'Return lines older than this message id.', 'flx-chat' ) ),
			'first'  => array( 'type' => 'Int', 'description' => __( 'Page size, at most 50.', 'flx-chat' ) ),
		),
		'resolve'     => static function ( $root, array $args ) {
			unset( $root );
			$id = flxc_gql_chat_id( $args['chatId'] );
			flxc_gql_visible( $id );
			$page = FLXC_Message_Service::list_for( flxc_gql_req( array( 'id' => $id, 'before' => FLXC_Val::int( flx_gql_arr( $args )['before'] ?? 0 ) ) ), flx_gql_page_size( flx_gql_arr( $args ), 40 ) );
			if ( $page instanceof WP_Error ) {
				throw flx_gql_from_wp_error( $page );
			}
			flx_gql_cache_tag( FLXC_Chat_Service::CACHE );
			$msgs = array();
			foreach ( $page['messages'] as $js ) {
				$msgs[] = flxc_gql_message( $js );
			}
			return array(
				'messages' => $msgs,
				'hasMore'  => FLXC_Val::bool( $page['more'] ),
				'note'     => FLXC_Val::str( $page['note'] ),
			);
		},
	)
);

flx_gql_query(
	'flxChatUpdates',
	array(
		'type'        => 'FlxChatUpdates!',
		'description' => __( 'Changes since a revision. Poll at 10 seconds or slower, and stop while the tab is hidden.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'args'        => array(
			'since'     => array( 'type' => 'String!', 'description' => __( 'Revision milliseconds already applied. Decimal string.', 'flx-chat' ) ),
			'after'     => array( 'type' => 'Int', 'description' => __( 'Last message id the client has for the open chat.', 'flx-chat' ) ),
			'chatId'    => array( 'type' => 'ID', 'description' => __( 'Open chat. Lines of a hidden chat are omitted.', 'flx-chat' ) ),
			'afterChat' => array( 'type' => 'Int', 'description' => __( 'continueId from a truncated page.', 'flx-chat' ) ),
		),
		'resolve'     => static function ( $root, array $args ): array {
			unset( $root );
			$since = FLXC_Val::int(preg_replace( '/\D+/', '', FLXC_Val::str($args['since']) ));
			$after = FLXC_Val::int(( $args['after'] ?? 0 ));
			$hold  = FLXC_Val::int(( $args['afterChat'] ?? 0 ));
			$rows  = $since > 0 ? FLXC_Chat_Service::changes_since( $since, $hold, 51 ) : array();
			$cut   = count( $rows ) > 50;
			$rows  = array_slice( $rows, 0, 50 );
			$last  = $rows ? $rows[ count( $rows ) - 1 ] : null;
			$nodes = array();
			foreach ( $rows as $row ) {
				$nodes[] = new FLXC_Chat_Node( $row );
			}
			$messages = array();
			$mcut     = false;
			if ( ! empty( $args['chatId'] ) ) {
				$cid = flxc_gql_chat_id( $args['chatId'] );
				if ( null !== FLXC_Chat_Service::visible( $cid ) ) {
					$raw      = flxc_messages_since( $cid, $since, $after, 51 );
					$mcut     = count( (array) $raw ) > 50;
					$raw      = array_slice( (array) $raw, 0, 50 );
					$messages = array_map( 'flxc_gql_message', flxc_messages_for_js( $raw ) );
				}
			}
			$incoming = array();
			if ( $since > 0 ) {
				foreach ( flxc_new_incoming_since( $since ) as $in ) {
					if ( ! is_array( $in ) ) {
						continue;
					}
					$incoming[] = array(
						'databaseId' => FLXC_Val::int(( $in['id'] ?? 0 )),
						'chatId'     => FLXC_Val::int(( $in['chat'] ?? 0 )),
						'title'      => FLXC_Val::str(( $in['title'] ?? '' )),
						'body'       => FLXC_Val::str(( $in['body'] ?? '' )),
					);
				}
			}
			return array(
				'rev'               => flxc_gql_ms( flxc_rev() ),
				'continueRev'       => $cut && is_array( $last ) ? flxc_gql_ms( $last['rev'] ) : flxc_gql_ms( flxc_rev() ),
				'continueId'        => $cut && is_array( $last ) ? FLXC_Val::int($last['id']) : 0,
				'truncated'         => $cut,
				'messagesTruncated' => $mcut,
				'chats'             => $nodes,
				'messages'          => $messages,
				'incoming'          => $incoming,
				'connections'       => flxc_connections_for_js(),
				'unread'            => flxc_unread_total(),
			);
		},
	)
);

flx_gql_query(
	'flxChatCheckNumber',
	array(
		'type'        => 'FlxChatCheck!',
		'description' => __( 'Look up a number. Does not reveal a chat the viewer cannot see.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'args'        => array(
			'phone' => array( 'type' => 'String!', 'description' => __( 'Number.', 'flx-chat' ) ),
			'conn'  => array( 'type' => 'String', 'description' => __( 'Connection id.', 'flx-chat' ) ),
		),
		'resolve'     => static function ( $root, array $args ): array {
			unset( $root );
			return FLXC_Chat_Service::check_number_for_viewer( flxc_gql_req( array( 'phone' => FLXC_Val::str($args['phone']), 'conn' => FLXC_Val::str(( $args['conn'] ?? '' )) ) ) );
		},
	)
);

flx_gql_query(
	'flxChatLinkPreview',
	array(
		'type'        => 'FlxChatLinkPreview',
		'description' => __( 'Card for a URL. Null when it cannot be read.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'args'        => array(
			'url' => array( 'type' => 'String!', 'description' => __( 'URL.', 'flx-chat' ) ),
		),
		'resolve'     => static function ( $root, array $args ): ?array {
			unset( $root );
			$r = FLXC_Val::arr( flxc_link_preview( FLXC_Val::str($args['url']) ) );
			if ( array() === $r || empty( $r['ok'] ) ) {
				return null;
			}
			return array(
				'url'    => FLXC_Val::str(( $r['url'] ?? $args['url'] )),
				'title'  => FLXC_Val::str(( $r['title'] ?? '' )),
				'desc'   => FLXC_Val::str(( $r['desc'] ?? '' )),
				'image'  => FLXC_Val::str(( $r['image'] ?? '' )),
				'site'   => FLXC_Val::str(( $r['site'] ?? '' )),
				'domain' => FLXC_Val::str(( $r['domain'] ?? '' )),
			);
		},
	)
);

flx_gql_query(
	'flxChatUsage',
	array(
		'type'        => 'FlxChatUsage',
		'description' => __( 'Assistant use of a visible chat. Null when the chat is hidden.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'args'        => array(
			'chatId' => array( 'type' => 'ID!', 'description' => __( 'Chat.', 'flx-chat' ) ),
		),
		'resolve'     => static function ( $root, array $args ): ?array {
			unset( $root );
			$row = FLXC_Chat_Service::visible( flxc_gql_chat_id( $args['chatId'] ) );
			if ( null === $row ) {
				return null;
			}
			$u = flxc_chat_usage_for_js( $row );
			return array(
				'replies' => FLXC_Val::int(( $u['replies'] ?? 0 )),
				'limit'   => FLXC_Val::int(( $u['limit'] ?? 0 )),
				'limited' => FLXC_Val::bool(( $u['limited'] ?? false )),
				'days'    => (array) ( $u['days'] ?? array() ),
			);
		},
	)
);

flx_gql_query(
	'flxChatReplies',
	array(
		'type'        => '[FlxChatReply!]!',
		'description' => __( 'Quick replies.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'resolve'     => static function (): array {
			return FLXC_Desk_Service::replies()['replies'];
		},
	)
);

/* ---------- mutations ---------- */

/**
 * @param array<string,mixed>|WP_Error $result Service result with a chat key.
 * @return array<string,mixed>|WP_Error
 */
function flxc_gql_chat_payload( $result ) {
	if ( $result instanceof WP_Error ) {
		return $result;
	}
	if ( ! isset( $result['chat'] ) || ! is_array( $result['chat'] ) ) {
		return array();
	}
	return array( 'chat' => flxc_gql_visible_node( FLXC_Val::int(( $result['chat']['id'] ?? 0 )) ) );
}

/**
 * The chat as a full node, or null when the viewer may not see it (restrict agents). Every mutation
 * payload goes through this, so a chat that became hidden, or one found by number, never leaks.
 *
 * @return FLXC_Chat_Node|null
 */
function flxc_gql_visible_node( int $id ) {
	$row = FLXC_Chat_Service::visible( $id );
	return null === $row ? null : new FLXC_Chat_Node( $row, true );
}

flx_gql_mutation(
	'flxChatCreate',
	array(
		'description' => __( 'Open a chat for a number.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'input'       => array(
			'phone' => array( 'type' => 'String!', 'description' => __( 'Number.', 'flx-chat' ) ),
			'name'  => array( 'type' => 'String', 'description' => __( 'Name.', 'flx-chat' ) ),
			'conn'  => array( 'type' => 'String', 'description' => __( 'Connection id.', 'flx-chat' ) ),
			'check' => array( 'type' => 'Boolean', 'description' => __( 'Refuse when the number is not on WhatsApp.', 'flx-chat' ) ),
		),
		'output'      => array(
			'chat' => array( 'type' => 'FlxChat', 'description' => __( 'The chat.', 'flx-chat' ) ),
		),
		'resolve'     => static fn( array $input ) => flxc_gql_chat_payload( FLXC_Chat_Service::create( flxc_gql_req( flx_gql_arr( $input ) ), true ) ),
	)
);

flx_gql_mutation(
	'flxChatUpdate',
	array(
		'description' => __( 'Rename, assign, pin, close or note a visible chat. staffUser is manager-only.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'input'       => array(
			'id'        => array( 'type' => 'ID!', 'description' => __( 'Chat.', 'flx-chat' ) ),
			'name'      => array( 'type' => 'String', 'description' => __( 'Name.', 'flx-chat' ) ),
			'notes'     => array( 'type' => 'String', 'description' => __( 'Notes.', 'flx-chat' ) ),
			'labels'    => array( 'type' => '[String!]', 'description' => __( 'Label ids.', 'flx-chat' ) ),
			'status'    => array( 'type' => 'FlxChatStatus', 'description' => __( 'Open or closed.', 'flx-chat' ) ),
			'assigned'  => array( 'type' => 'Int', 'description' => __( 'Agent id. 0 clears it.', 'flx-chat' ) ),
			'pinned'    => array( 'type' => 'Boolean', 'description' => __( 'Pinned.', 'flx-chat' ) ),
			'unread'    => array( 'type' => 'Int', 'description' => __( 'Unread count.', 'flx-chat' ) ),
			'staffUser' => array( 'type' => 'Int', 'description' => __( 'Staff user. Managers only.', 'flx-chat' ) ),
		),
		'output'      => array(
			'chat' => array( 'type' => 'FlxChat', 'description' => __( 'The chat.', 'flx-chat' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$params = $input;
			$params['id'] = flxc_gql_chat_id( $input['id'] );
			if ( isset( $input['staffUser'] ) ) {
				$params['staff_user'] = $input['staffUser'];
			}
			return flxc_gql_chat_payload( FLXC_Chat_Service::update( flxc_gql_req( flx_gql_arr( $params ) ) ) );
		},
	)
);

flx_gql_mutation(
	'flxChatMessageSend',
	array(
		'description' => __( 'Send a line. voice true is a voice note (audio + ptt); voice false keeps an audio file.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'input'       => array(
			'chatId'   => array( 'type' => 'ID!', 'description' => __( 'Chat.', 'flx-chat' ) ),
			'body'     => array( 'type' => 'String', 'description' => __( 'Text.', 'flx-chat' ) ),
			'type'     => array( 'type' => 'String', 'description' => __( 'text, note, or a file type.', 'flx-chat' ) ),
			'uploadId' => array( 'type' => 'String', 'description' => __( 'File from flx/v1/upload, context flx-chat/outbound.', 'flx-chat' ) ),
			'voice'    => array( 'type' => 'Boolean', 'description' => __( 'Voice note. Distinct from an audio file.', 'flx-chat' ) ),
			'quoted'   => array( 'type' => 'Int', 'description' => __( 'Quoted message id.', 'flx-chat' ) ),
			'source'   => array( 'type' => 'String', 'description' => __( 'agent, quick or ai.', 'flx-chat' ) ),
			'preview'  => array( 'type' => 'Boolean', 'description' => __( 'Build a link card.', 'flx-chat' ) ),
		),
		'output'      => array(
			'message' => array( 'type' => 'FlxChatMessage', 'description' => __( 'The line.', 'flx-chat' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$chat = flxc_gql_visible( flxc_gql_chat_id( $input['chatId'] ) );
			$upload = trim( FLXC_Val::str(( $input['uploadId'] ?? '' )) );
			if ( '' !== $upload ) {
				$file = flx_gql_upload_take( $upload, 'flx-chat/outbound' );
				if ( $file instanceof WP_Error ) {
					return array( 'errors' => $file );
				}
				$result = FLXC_Message_Service::send_upload(
					$chat,
					FLXC_Val::str(( $input['type'] ?? '' )),
					FLXC_Val::str(( $input['body'] ?? '' )),
					FLXC_Val::int(( $input['quoted'] ?? 0 )),
					FLXC_Val::str(( $input['source'] ?? 'agent' )),
					! isset( $input['preview'] ) || FLXC_Val::bool($input['preview']),
					array(
						'path' => FLXC_Val::str( $file['path'] ?? '' ),
						'name' => FLXC_Val::str( $file['name'] ?? '' ),
						'mime' => FLXC_Val::str( $file['mime'] ?? '' ),
						'size' => FLXC_Val::int( $file['size'] ?? 0 ),
					),
					! empty( $input['voice'] )
				);
				return flxc_gql_message_payload( $result );
			}
			$result = FLXC_Message_Service::send(
				flxc_gql_req(
					array(
						'id'      => FLXC_Val::int($chat['id']),
						'body'    => FLXC_Val::str(( $input['body'] ?? '' )),
						'type'    => FLXC_Val::str(( $input['type'] ?? 'text' )),
						'quoted'  => FLXC_Val::int(( $input['quoted'] ?? 0 )),
						'source'  => FLXC_Val::str(( $input['source'] ?? 'agent' )),
						'preview' => ! isset( $input['preview'] ) || FLXC_Val::bool($input['preview']),
					)
				)
			);
			return flxc_gql_message_payload( $result );
		},
	)
);

/**
 * @param callable(WP_REST_Request): (array<string,mixed>|WP_Error) $call Service call.
 * @param array<string,mixed>                                       $params Params including the message id.
 * @return array<string,mixed>|WP_Error
 */
function flxc_gql_message_op( callable $call, array $params ) {
	$m = flxc_get_message( FLXC_Val::int(( $params['id'] ?? 0 )) );
	if ( ! is_array( $m ) ) {
		throw flx_gql_error( 'NOT_FOUND', __( 'Message not found.', 'flx-chat' ) );
	}
	flxc_gql_visible( FLXC_Val::int($m['chat_id']) );
	return flxc_gql_message_payload( $call( flxc_gql_req( $params ) ) );
}

flx_gql_mutation(
	'flxChatMessageRetry',
	array(
		'description' => __( 'Resend a failed outgoing line on a visible chat.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'input'       => array( 'id' => array( 'type' => 'Int!', 'description' => __( 'Message id.', 'flx-chat' ) ) ),
		'output'      => array( 'message' => array( 'type' => 'FlxChatMessage', 'description' => __( 'The line.', 'flx-chat' ) ) ),
		'resolve'     => static fn( array $input ) => flxc_gql_message_op( array( FLXC_Message_Service::class, 'retry' ), flx_gql_arr( $input ) ),
	)
);
flx_gql_mutation(
	'flxChatMessageReact',
	array(
		'description' => __( 'React on a visible chat.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'input'       => array(
			'id'    => array( 'type' => 'Int!', 'description' => __( 'Message id.', 'flx-chat' ) ),
			'emoji' => array( 'type' => 'String!', 'description' => __( 'Emoji. Empty removes it.', 'flx-chat' ) ),
		),
		'output'      => array( 'message' => array( 'type' => 'FlxChatMessage', 'description' => __( 'The line.', 'flx-chat' ) ) ),
		'resolve'     => static fn( array $input ) => flxc_gql_message_op( array( FLXC_Message_Service::class, 'react' ), flx_gql_arr( $input ) ),
	)
);
flx_gql_mutation(
	'flxChatMessageEdit',
	array(
		'description' => __( 'Edit a line. The author or a manager, and only on a visible chat.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'input'       => array(
			'id'   => array( 'type' => 'Int!', 'description' => __( 'Message id.', 'flx-chat' ) ),
			'text' => array( 'type' => 'String!', 'description' => __( 'New text.', 'flx-chat' ) ),
		),
		'output'      => array( 'message' => array( 'type' => 'FlxChatMessage', 'description' => __( 'The line.', 'flx-chat' ) ) ),
		'resolve'     => static function ( array $input ) {
			$m = flxc_get_message( FLXC_Val::int($input['id']) );
			if ( ! is_array( $m ) ) {
				throw flx_gql_error( 'NOT_FOUND', __( 'Message not found.', 'flx-chat' ) );
			}
			flxc_gql_visible( FLXC_Val::int($m['chat_id']) );
			if ( FLXC_Val::int($m['user_id']) !== get_current_user_id() && ! flxc_can_manage() ) {
				throw flx_gql_error( 'FORBIDDEN', __( 'You do not have permission for this action.', 'flx-chat' ) );
			}
			return flxc_gql_message_payload( FLXC_Message_Service::edit( flxc_gql_req( array( 'id' => FLXC_Val::int($input['id']), 'text' => FLXC_Val::str($input['text']) ) ) ) );
		},
	)
);
flx_gql_mutation(
	'flxChatMessageDelete',
	array(
		'description' => __( 'Hide a note or delete for everyone. The author or a manager, on a visible chat.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'input'       => array(
			'id'    => array( 'type' => 'Int!', 'description' => __( 'Message id.', 'flx-chat' ) ),
			'scope' => array( 'type' => 'String', 'description' => __( 'panel or everyone.', 'flx-chat' ) ),
		),
		'output'      => array( 'message' => array( 'type' => 'FlxChatMessage', 'description' => __( 'The line.', 'flx-chat' ) ) ),
		'resolve'     => static function ( array $input ) {
			$m = flxc_get_message( FLXC_Val::int($input['id']) );
			if ( ! is_array( $m ) ) {
				throw flx_gql_error( 'NOT_FOUND', __( 'Message not found.', 'flx-chat' ) );
			}
			flxc_gql_visible( FLXC_Val::int($m['chat_id']) );
			$scope = FLXC_Val::str(( $input['scope'] ?? 'panel' ));
			$note  = 'note' === FLXC_Val::str($m['direction']);
			if ( ( $note || 'everyone' === $scope ) && FLXC_Val::int($m['user_id']) !== get_current_user_id() && ! flxc_can_manage() ) {
				throw flx_gql_error( 'FORBIDDEN', __( 'You do not have permission for this action.', 'flx-chat' ) );
			}
			return flxc_gql_message_payload( FLXC_Message_Service::delete( flxc_gql_req( array( 'id' => FLXC_Val::int($input['id']), 'scope' => $scope ) ) ) );
		},
	)
);
flx_gql_mutation(
	'flxChatMarkRead',
	array(
		'description' => __( 'Mark a visible chat read.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'input'       => array( 'id' => array( 'type' => 'ID!', 'description' => __( 'Chat.', 'flx-chat' ) ) ),
		'output'      => array( 'chat' => array( 'type' => 'FlxChat', 'description' => __( 'The chat.', 'flx-chat' ) ) ),
		'resolve'     => static function ( array $input ) {
			$id = flxc_gql_chat_id( $input['id'] );
			$r  = FLXC_Message_Service::read( flxc_gql_req( array( 'id' => $id ) ) );
			if ( $r instanceof WP_Error ) {
				return $r;
			}
			return array( 'chat' => flxc_gql_visible_node( $id ) );
		},
	)
);
flx_gql_mutation(
	'flxChatTyping',
	array(
		'description' => __( 'Typing state on a visible chat.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'input'       => array(
			'id'    => array( 'type' => 'ID!', 'description' => __( 'Chat.', 'flx-chat' ) ),
			'state' => array( 'type' => 'String', 'description' => __( 'composing or recording.', 'flx-chat' ) ),
		),
		'output'      => array( 'ok' => array( 'type' => 'Boolean!', 'description' => __( 'Accepted.', 'flx-chat' ) ) ),
		'resolve'     => static function ( array $input ) {
			$r = FLXC_Message_Service::typing( flxc_gql_req( array( 'id' => flxc_gql_chat_id( $input['id'] ), 'state' => FLXC_Val::str(( $input['state'] ?? 'composing' )) ) ) );
			if ( $r instanceof WP_Error ) {
				throw flx_gql_from_wp_error( $r );
			}
			return array( 'ok' => true );
		},
	)
);
flx_gql_mutation(
	'flxChatSuggest',
	array(
		'description' => __( 'One suggested reply for a visible chat. Not a stream.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'input'       => array(
			'id'    => array( 'type' => 'ID!', 'description' => __( 'Chat.', 'flx-chat' ) ),
			'fresh' => array( 'type' => 'Boolean', 'description' => __( 'Ignore a cached suggestion.', 'flx-chat' ) ),
			'hint'  => array( 'type' => 'String', 'description' => __( 'Hint for the model.', 'flx-chat' ) ),
		),
		'output'      => array( 'text' => array( 'type' => 'String', 'description' => __( 'Suggestion. Null on failure.', 'flx-chat' ) ) ),
		'resolve'     => static function ( array $input ) {
			flxc_gql_visible( flxc_gql_chat_id( $input['id'] ) );
			$r = FLXC_Message_Service::suggest( flxc_gql_req( array( 'id' => flxc_gql_chat_id( $input['id'] ), 'fresh' => ! empty( $input['fresh'] ), 'hint' => FLXC_Val::str(( $input['hint'] ?? '' )) ) ) );
			if ( $r instanceof WP_Error ) {
				return $r;
			}
			return array( 'text' => FLXC_Val::str( $r['text'] ) );
		},
	)
);
flx_gql_mutation(
	'flxChatBotSet',
	array(
		'description' => __( 'Turn the assistant on or off for a visible chat.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'input'       => array(
			'id' => array( 'type' => 'ID!', 'description' => __( 'Chat.', 'flx-chat' ) ),
			'on' => array( 'type' => 'Boolean!', 'description' => __( 'On.', 'flx-chat' ) ),
		),
		'output'      => array( 'chat' => array( 'type' => 'FlxChat', 'description' => __( 'The chat.', 'flx-chat' ) ) ),
		'resolve'     => static function ( array $input ) {
			$id = flxc_gql_chat_id( $input['id'] );
			flxc_gql_visible( $id );
			return flxc_gql_chat_payload( FLXC_Desk_Service::bot( flxc_gql_req( array( 'id' => $id, 'state' => ! empty( $input['on'] ) ? 'on' : 'off' ) ) ) );
		},
	)
);
flx_gql_mutation(
	'flxChatReplyUse',
	array(
		'description' => __( 'Fill a quick reply for a visible chat.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'input'       => array(
			'id'     => array( 'type' => 'Int!', 'description' => __( 'Reply id.', 'flx-chat' ) ),
			'chatId' => array( 'type' => 'ID!', 'description' => __( 'Chat.', 'flx-chat' ) ),
		),
		'output'      => array( 'text' => array( 'type' => 'String!', 'description' => __( 'Filled text.', 'flx-chat' ) ) ),
		'resolve'     => static function ( array $input ) {
			$chat = flxc_gql_chat_id( $input['chatId'] );
			flxc_gql_visible( $chat );
			$r = FLXC_Desk_Service::use_reply( flxc_gql_req( array( 'id' => FLXC_Val::int($input['id']), 'chat' => $chat ) ) );
			return array( 'text' => FLXC_Val::str( $r['text'] ) );
		},
	)
);
flx_gql_mutation(
	'flxChatExport',
	array(
		'description' => __( 'Text of one visible chat. The bulk file download stays on admin-post.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'input'       => array(
			'id'     => array( 'type' => 'ID!', 'description' => __( 'Chat.', 'flx-chat' ) ),
			'format' => array( 'type' => 'String', 'description' => __( 'md or json.', 'flx-chat' ) ),
			'mask'   => array( 'type' => 'Boolean', 'description' => __( 'Mask phones and emails.', 'flx-chat' ) ),
			'notes'  => array( 'type' => 'Boolean', 'description' => __( 'Include notes.', 'flx-chat' ) ),
		),
		'output'      => array(
			'body'     => array( 'type' => 'String!', 'description' => __( 'File body.', 'flx-chat' ) ),
			'filename' => array( 'type' => 'String!', 'description' => __( 'File name.', 'flx-chat' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$chat = flxc_gql_visible( flxc_gql_chat_id( $input['id'] ) );
			$o    = array(
				'format' => 'json' === ( $input['format'] ?? '' ) ? 'json' : 'md',
				'mask'   => ! isset( $input['mask'] ) || FLXC_Val::bool($input['mask']),
				'notes'  => ! isset( $input['notes'] ) || FLXC_Val::bool($input['notes']),
			);
			$c    = flxc_export_chat( $chat, $o );
			$name = 'sohbet-' . FLXC_Val::int($chat['id']) . '-' . wp_date( 'Ymd-Hi' ) . '.' . $o['format'];
			if ( 'json' === $o['format'] ) {
				$body = FLXC_Val::str(wp_json_encode( array( 'items' => array( $c ) ), JSON_UNESCAPED_UNICODE ));
			} else {
				$body = flxc_export_md( $c );
			}
			return array( 'body' => $body, 'filename' => $name );
		},
	)
);

flx_gql_mutation(
	'flxChatReceiptAttach',
	array(
		'description' => __( 'Match a receipt. Donation or certificate capability, and a visible chat.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'input'       => array(
			'id'    => array( 'type' => 'Int!', 'description' => __( 'Message id.', 'flx-chat' ) ),
			'kind'  => array( 'type' => 'String!', 'description' => __( 'donation or certificate.', 'flx-chat' ) ),
			'ref'   => array( 'type' => 'Int!', 'description' => __( 'Record id.', 'flx-chat' ) ),
			'paid'  => array( 'type' => 'Boolean', 'description' => __( 'Mark the donation paid.', 'flx-chat' ) ),
			'note'  => array( 'type' => 'String', 'description' => __( 'Note.', 'flx-chat' ) ),
			'reply' => array( 'type' => 'String', 'description' => __( 'Optional reply text.', 'flx-chat' ) ),
		),
		'output'      => array( 'ok' => array( 'type' => 'Boolean!', 'description' => __( 'Matched.', 'flx-chat' ) ) ),
		'resolve'     => static function ( array $input ) {
			$m = flxc_get_message( FLXC_Val::int($input['id']) );
			if ( ! is_array( $m ) ) {
				throw flx_gql_error( 'NOT_FOUND', __( 'Message not found.', 'flx-chat' ) );
			}
			flxc_gql_visible( FLXC_Val::int($m['chat_id']) );
			$r = flxc_rest_attach_receipt( flxc_gql_req( flx_gql_arr( $input ) ) );
			if ( $r instanceof WP_Error ) {
				throw flx_gql_from_wp_error( $r );
			}
			return array( 'ok' => true );
		},
	)
);
flx_gql_mutation(
	'flxChatReceiptDetach',
	array(
		'description' => __( 'Unmatch a receipt. Same capability as attach, on a visible chat.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'input'       => array(
			'id'   => array( 'type' => 'Int!', 'description' => __( 'Message id.', 'flx-chat' ) ),
			'kind' => array( 'type' => 'String!', 'description' => __( 'donation or certificate.', 'flx-chat' ) ),
			'ref'  => array( 'type' => 'Int!', 'description' => __( 'Record id.', 'flx-chat' ) ),
		),
		'output'      => array( 'ok' => array( 'type' => 'Boolean!', 'description' => __( 'Removed.', 'flx-chat' ) ) ),
		'resolve'     => static function ( array $input ) {
			$m = flxc_get_message( FLXC_Val::int($input['id']) );
			if ( ! is_array( $m ) ) {
				throw flx_gql_error( 'NOT_FOUND', __( 'Message not found.', 'flx-chat' ) );
			}
			flxc_gql_visible( FLXC_Val::int($m['chat_id']) );
			$kind = 'certificate' === ( $input['kind'] ?? '' ) ? 'certificate' : 'donation';
			$ok   = 'donation' === $kind ? flxc_can_record_donation() : flxc_can_record_certificate();
			if ( ! $ok ) {
				throw flx_gql_error( 'FORBIDDEN', __( 'You are not allowed to do this.', 'flx-chat' ) );
			}
			$r = flxc_rest_detach_receipt( flxc_gql_req( flx_gql_arr( $input ) ) );
			if ( $r instanceof WP_Error ) {
				throw flx_gql_from_wp_error( $r );
			}
			return array( 'ok' => true );
		},
	)
);

flx_gql_mutation(
	'flxChatDonationRecord',
	array(
		'description' => __( 'Create a donation from a visible chat, through Flux Donate\'s PHP. Temporary until Donate\'s own mutation exists.', 'flx-chat' ),
		'cap'         => $flxc_gql_use,
		'input'       => array(
			'chatId' => array( 'type' => 'ID', 'description' => __( 'Chat. Checked with flxc_user_can_see_chat.', 'flx-chat' ) ),
			'typeId' => array( 'type' => 'Int', 'description' => __( 'Donation type.', 'flx-chat' ) ),
			'amount' => array( 'type' => 'Float', 'description' => __( 'Amount.', 'flx-chat' ) ),
			'name'   => array( 'type' => 'String', 'description' => __( 'Donor name.', 'flx-chat' ) ),
			'phone'  => array( 'type' => 'String', 'description' => __( 'Phone.', 'flx-chat' ) ),
			'note'   => array( 'type' => 'String', 'description' => __( 'Note.', 'flx-chat' ) ),
		),
		'output'      => array( 'ok' => array( 'type' => 'Boolean!', 'description' => __( 'Created.', 'flx-chat' ) ) ),
		'resolve'     => static function ( array $input ) {
			$chat = null;
			if ( ! empty( $input['chatId'] ) ) {
				$chat = flxc_gql_visible( flxc_gql_chat_id( $input['chatId'] ) );
			}
			$p = array(
				'chat'     => $chat ? FLXC_Val::int($chat['id']) : 0,
				'type_id'  => FLXC_Val::int(( $input['typeId'] ?? 0 )),
				'amount'   => $input['amount'] ?? 0,
				'name'     => FLXC_Val::str(( $input['name'] ?? '' )),
				'phone'    => FLXC_Val::str(( $input['phone'] ?? '' )),
				'note'     => FLXC_Val::str(( $input['note'] ?? '' )),
			);
			$r = FLXC_Desk_Service::create_donation( $p, $chat );
			if ( $r instanceof WP_Error ) {
				throw flx_gql_from_wp_error( $r );
			}
			return array( 'ok' => true );
		},
	)
);

flx_gql_mutation(
	'flxChatClear',
	array(
		'description' => __( 'Clear a visible chat. Managers only, confirm must be yes.', 'flx-chat' ),
		'cap'         => $flxc_gql_manage,
		'input'       => array(
			'id'      => array( 'type' => 'ID!', 'description' => __( 'Chat.', 'flx-chat' ) ),
			'confirm' => array( 'type' => 'String!', 'description' => __( 'Must be yes.', 'flx-chat' ) ),
		),
		'output'      => array( 'ok' => array( 'type' => 'Boolean!', 'description' => __( 'Cleared.', 'flx-chat' ) ) ),
		'resolve'     => static function ( array $input ) {
			$id = flxc_gql_chat_id( $input['id'] );
			flxc_gql_visible( $id );
			$r = FLXC_Desk_Service::clear( flxc_gql_req( array( 'id' => $id, 'confirm' => FLXC_Val::str($input['confirm']) ) ) );
			if ( $r instanceof WP_Error ) {
				return $r;
			}
			return array( 'ok' => true );
		},
	)
);
