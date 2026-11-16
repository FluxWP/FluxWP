<?php
/**
 * Agent side of the site chat: list, read, reply, close, delete, typing indicator, figures.
 * The classic REST routes (`/agent/*`), the Chatbot inbox and the GraphQL schema all call this class,
 * so who may do what and what a write triggers is decided in one place.
 *
 * Visitor routes (session, message stream, poll, upload, file) are not here: they are the public chat
 * protocol and stay on REST (docs/graphql/ISTISNALAR.md).
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

final class FLXCB_Chat_Service {

	public const CACHE_TAG = 'flx-chatbot:chats';

	/** Statuses a conversation can have (the `status` column). */
	public const STATUSES = array( 'bot', 'queued', 'agent', 'offline', 'closed' );

	/** Most messages one call returns. */
	public const MESSAGE_LIMIT = 100;

	/**
	 * Row of the conversations table → typed conversation.
	 *
	 * @param array<mixed> $c Row.
	 */
	public static function conversation( array $c ): FLXCB_Conversation {
		$id    = FLXCB_Val::int( $c['id'] ?? 0 );
		$last  = flxcb_last_message( $id );
		$order = FLXCB_Val::int( $c['order_id'] ?? 0 );
		$url   = '';
		if ( $order && function_exists( 'wc_get_order' ) ) {
			$o   = wc_get_order( $order );
			$url = $o instanceof WC_Order ? $o->get_edit_order_url() : '';
		}
		$chat = FLXCB_Val::int( $c['flxc_chat'] ?? 0 );
		$ts   = FLXCB_Val::int( $c['last_ts'] ?? 0 );
		$body = '';
		if ( is_array( $last ) ) {
			$body = '' !== FLXCB_Val::str( $last['body'] ?? '' ) ? FLXCB_Val::str( $last['body'] ) : FLXCB_Val::str( $last['file_name'] ?? '' );
		}
		return new FLXCB_Conversation(
			$id,
			FLXCB_Val::str( flxcb_conv_title( $c ) ),
			FLXCB_Val::str( $c['name'] ?? '' ),
			FLXCB_Val::str( $c['email'] ?? '' ),
			FLXCB_Val::str( $c['phone'] ?? '' ),
			FLXCB_Val::str( $c['status'] ?? 'bot', 'bot' ),
			FLXCB_Val::int( $c['unread'] ?? 0 ),
			FLXCB_Val::int( $c['rating'] ?? 0 ),
			FLXCB_Val::str( $c['rating_note'] ?? '' ),
			FLXCB_Val::int( $c['lead'] ?? 0 ) > 0,
			$order,
			$url,
			FLXCB_Val::str( $c['page_url'] ?? '' ),
			FLXCB_Val::str( $c['page_title'] ?? '' ),
			FLXCB_Val::int( $c['user_id'] ?? 0 ),
			FLXCB_Val::int( $c['assigned_to'] ?? 0 ) ? FLXCB_Val::str( flxcb_user_name( FLXCB_Val::int( $c['assigned_to'] ) ) ) : '',
			$chat && flxcb_fc_ready() ? admin_url( 'admin.php?page=flx-chat&chat=' . $chat ) : '',
			FLXCB_Val::str( flxcb_conv_meta( $c, 'summary' ) ),
			$ts,
			flxcb_now_ms() - FLXCB_Val::int( $c['seen_ts'] ?? 0 ) < 45000,
			(bool) get_transient( 'flxcb_typing_v_' . $id ),
			is_array( $last ) ? new FLXCB_Last( FLXCB_Val::str( $last['role'] ?? '' ), mb_substr( $body, 0, 120 ) ) : null
		);
	}

	/**
	 * Row of the messages table → typed message.
	 *
	 * @param array<mixed> $m Row.
	 */
	public static function message( array $m ): FLXCB_Message {
		$meta  = flxcb_decode( FLXCB_Val::str( $m['meta'] ?? '' ) );
		$role  = FLXCB_Val::str( $m['role'] ?? '' );
		$id    = FLXCB_Val::int( $m['id'] ?? 0 );
		$cards = array();
		foreach ( FLXCB_Val::arr( is_array( $meta ) ? ( $meta['cards'] ?? array() ) : array() ) as $card ) {
			$c = FLXCB_Card::from_array( $card );
			if ( null !== $c ) {
				$cards[] = $c;
			}
		}
		$has_file = '' !== FLXCB_Val::str( $m['file_path'] ?? '' );
		return new FLXCB_Message(
			$id,
			$role,
			FLXCB_Val::str( $m['type'] ?? 'text', 'text' ),
			FLXCB_Val::str( $m['body'] ?? '' ),
			FLXCB_Val::int( $m['ts'] ?? 0 ),
			'agent' === $role ? FLXCB_Val::str( flxcb_user_name( FLXCB_Val::int( $m['user_id'] ?? 0 ) ) ) : ( 'bot' === $role ? FLXCB_Val::str( flxcb_bot_name() ) : '' ),
			$has_file ? FLXCB_Val::str( $m['file_name'] ?? '' ) : '',
			$has_file ? FLXCB_Val::str( $m['file_mime'] ?? '' ) : '',
			$has_file ? add_query_arg( '_wpnonce', wp_create_nonce( 'wp_rest' ), rest_url( 'flx-chatbot/v1/file/' . $id ) ) : '',
			$cards
		);
	}

	/**
	 * Conversation by id (no side effects).
	 */
	public static function find( int $id ): ?FLXCB_Conversation {
		$c = $id > 0 ? flxcb_get_conversation( $id ) : null;
		return is_array( $c ) ? self::conversation( $c ) : null;
	}

	/**
	 * One page of conversations, newest activity first.
	 *
	 * @return array{items:list<FLXCB_Conversation>,total:int}
	 */
	public static function page( string $status, string $search, int $offset, int $limit ): array {
		$status = 'open' === $status || in_array( $status, self::STATUSES, true ) ? $status : '';
		$rows   = flxcb_query_conversations( array( 'status' => $status, 'q' => $search, 'limit' => $limit, 'offset' => $offset ) );
		$items  = array();
		foreach ( $rows as $row ) {
			$items[] = self::conversation( $row );
		}
		return array( 'items' => $items, 'total' => self::count( $status, $search ) );
	}

	/**
	 * How many conversations match the filter.
	 */
	public static function count( string $status, string $search ): int {
		/** @var \wpdb $wpdb */
		global $wpdb;
		$open = 'open' === $status ? 1 : 0;
		$stat = $open ? '' : $status;
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE ( %d = 0 OR status IN ('queued','agent','offline') ) AND ( %s = '' OR status = %s ) AND ( %s = '' OR name LIKE %s OR email LIKE %s OR phone LIKE %s OR page_title LIKE %s )",
				flxcb_table( 'conversations' ),
				$open,
				$stat,
				$stat,
				$search,
				$like,
				$like,
				$like,
				$like
			)
		);
	}

	/**
	 * Messages after a message id, oldest first. Without $peek the agent's read mark moves to the last
	 * visitor message (the classic inbox does the same when it opens a chat).
	 */
	public static function messages( int $conv_id, int $after, int $limit = self::MESSAGE_LIMIT, bool $peek = false ): FLXCB_Message_Page|WP_Error {
		$c = flxcb_get_conversation( $conv_id );
		if ( ! is_array( $c ) ) {
			return new WP_Error( 'flxcb_conv', __( 'Chat not found.', 'flx-chatbot' ), array( 'status' => 404, 'graphql_code' => 'NOT_FOUND' ) );
		}
		flxcb_fc_sync_status( $c );
		$limit = max( 1, min( 300, $limit ) );
		$rows  = flxcb_messages( $conv_id, max( 0, $after ), $limit + 1 );
		$more  = count( $rows ) > $limit;
		$rows  = array_slice( $rows, 0, $limit );
		if ( $rows && ! $peek ) {
			/** @var \wpdb $wpdb */
			global $wpdb;
			$last_in = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(id) FROM %i WHERE conv_id = %d AND role = 'visitor'", flxcb_table( 'messages' ), $conv_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			flxcb_update_conversation( $conv_id, array( 'agent_read' => $last_in, 'unread' => 0 ) );
		}
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = self::message( $row );
		}
		return new FLXCB_Message_Page( $out, $out ? $out[ count( $out ) - 1 ]->id : max( 0, $after ), $more );
	}

	/**
	 * Agent reply, with an optional stored file (see flxcb_store_upload / flxcb_store_local).
	 *
	 * @param array<string,mixed> $file Stored file: path, name, mime, size.
	 */
	public static function reply( int $conv_id, int $user_id, string $text, array $file = array() ): FLXCB_Conversation|WP_Error {
		$c = flxcb_get_conversation( $conv_id );
		if ( ! is_array( $c ) ) {
			return new WP_Error( 'flxcb_conv', __( 'Chat not found.', 'flx-chatbot' ), array( 'status' => 404, 'graphql_code' => 'NOT_FOUND' ) );
		}
		$text = trim( sanitize_textarea_field( $text ) );
		if ( '' === $text && ! $file ) {
			return new WP_Error( 'flxcb_empty', __( 'The message is empty.', 'flx-chatbot' ), array( 'status' => 400, 'graphql_code' => 'INVALID_INPUT', 'field' => 'text' ) );
		}
		flxcb_agent_reply( $c, $user_id, $text, $file );
		self::changed();
		return self::find( $conv_id ) ?? new WP_Error( 'flxcb_conv', __( 'Chat not found.', 'flx-chatbot' ), array( 'status' => 404, 'graphql_code' => 'NOT_FOUND' ) );
	}

	/**
	 * An agent ends the chat.
	 */
	public static function close( int $conv_id ): FLXCB_Conversation|WP_Error {
		$c = flxcb_get_conversation( $conv_id );
		if ( ! is_array( $c ) ) {
			return new WP_Error( 'flxcb_conv', __( 'Chat not found.', 'flx-chatbot' ), array( 'status' => 404, 'graphql_code' => 'NOT_FOUND' ) );
		}
		flxcb_close( $c, 'agent' );
		self::changed();
		return self::find( $conv_id ) ?? new WP_Error( 'flxcb_conv', __( 'Chat not found.', 'flx-chatbot' ), array( 'status' => 404, 'graphql_code' => 'NOT_FOUND' ) );
	}

	/**
	 * Deletes the chat and its files (administrators only; the caller checks).
	 */
	public static function delete( int $conv_id ): bool {
		if ( ! is_array( flxcb_get_conversation( $conv_id ) ) ) {
			return false;
		}
		flxcb_delete_conversation( $conv_id );
		self::changed();
		return true;
	}

	/**
	 * "The agent is typing" for the visitor (8 seconds).
	 */
	public static function typing( int $conv_id ): void {
		set_transient( 'flxcb_typing_a_' . $conv_id, 1, 8 );
	}

	/**
	 * Figures of the last N days.
	 */
	public static function stats( int $days = 30 ): FLXCB_Stats {
		$s = flxcb_stats( max( 1, min( 365, $days ) ) );
		return new FLXCB_Stats(
			$s['total'],
			$s['human'],
			$s['resolved'],
			$s['leads'],
			$s['orders'],
			(float) $s['rating'],
			$s['rated'],
			$s['open']
		);
	}

	/**
	 * Cache tag of the list readers is purged: the list changed.
	 */
	public static function changed(): void {
		delete_transient( 'flxcb_open_count' );
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::CACHE_TAG ) );
		}
	}

	/* -------- Classic REST shapes (kept byte for byte) -------- */

	/**
	 * `/agent/conversations` item.
	 *
	 * @return array<string,mixed>
	 */
	public static function rest_conversation( FLXCB_Conversation $c ): array {
		return array(
			'id'       => $c->id,
			'title'    => $c->title,
			'name'     => $c->name,
			'email'    => $c->email,
			'phone'    => $c->phone,
			'status'   => $c->status,
			'unread'   => $c->unread,
			'rating'   => $c->rating,
			'note'     => $c->note,
			'lead'     => $c->lead ? 1 : 0,
			'order'    => $c->order_id,
			'orderUrl' => $c->order_url,
			'page'     => array( 'url' => $c->page_url, 'title' => $c->page_title ),
			'user'     => $c->user_id,
			'agent'    => $c->agent,
			'fluxChat' => $c->flux_chat_url,
			'summary'  => $c->summary,
			'lastTs'   => $c->last_ts,
			'ago'      => function_exists( 'flx_time_ago' ) ? flx_time_ago( (int) floor( $c->last_ts / 1000 ) ) : '',
			'online'   => $c->online ? 1 : 0,
			'typing'   => $c->typing ? 1 : 0,
			'last'     => null === $c->last ? null : array( 'role' => $c->last->role, 'body' => $c->last->body ),
		);
	}

	/**
	 * `/agent/conversations/<id>` message.
	 *
	 * @return array<string,mixed>
	 */
	public static function rest_message( FLXCB_Message $m ): array {
		$o = array(
			'id'     => $m->id,
			'role'   => $m->role,
			'type'   => $m->type,
			'body'   => $m->body,
			'ts'     => $m->ts,
			'time'   => function_exists( 'flx_date' ) ? flx_date( (int) floor( $m->ts / 1000 ), 'datetime' ) : '',
			'author' => $m->author,
		);
		if ( '' !== $m->file_url ) {
			$o['file'] = array( 'name' => $m->file_name, 'mime' => $m->file_mime, 'url' => $m->file_url );
		}
		if ( $m->cards ) {
			$o['cards'] = array_map(
				static fn( FLXCB_Card $c ): array => array(
					'kind'  => $c->kind,
					'title' => $c->title,
					'url'   => $c->url,
					'image' => $c->image,
					'price' => $c->price,
					'stock' => $c->stock,
					'cart'  => $c->cart,
				),
				$m->cards
			);
		}
		return $o;
	}
}
