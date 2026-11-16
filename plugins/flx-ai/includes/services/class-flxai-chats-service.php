<?php
/**
 * Chat history: list, search, open a transcript, rename, archive and delete. Each user sees only their own
 * chats. Sending, streaming and approving inside a chat stay on the chat's own REST/SSE protocol
 * (docs/graphql/ISTISNALAR.md).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

final class FLXAI_Chats_Service {

	public const CACHE_TAG = 'flx-ai:chats';

	/**
	 * History page of the signed-in user.
	 *
	 * @param string $q        Search in title and content.
	 * @param int    $archived 0 active, 1 archived, -1 all.
	 * @param int    $limit    Page size (1..100).
	 * @param int    $offset   Offset.
	 */
	public static function page( string $q = '', int $archived = 0, int $limit = 30, int $offset = 0 ): FLXAI_Chat_Page {
		$limit = max( 1, min( 100, $limit ) );
		$rows  = FLXAI_Cast::arr( flxai_chat_list( array( 'q' => $q, 'archived' => $archived, 'limit' => $limit + 1, 'offset' => max( 0, $offset ) ) ) );
		$more  = count( $rows ) > $limit;
		$items = array();
		foreach ( array_slice( $rows, 0, $limit ) as $row ) {
			$items[] = self::summary( FLXAI_Cast::arr( $row ) );
		}
		return new FLXAI_Chat_Page( $items, $more );
	}

	/** A chat with its transcript, or NOT_FOUND. */
	public static function get( int $id ): FLXAI_Chat_Detail|WP_Error {
		$chat = flxai_chat_get( $id );
		if ( ! is_array( $chat ) ) {
			return self::not_found();
		}
		$msgs = array();
		foreach ( FLXAI_Cast::arr( $chat['messages'] ) as $i => $m ) {
			$msg = self::message( (int) $i, FLXAI_Cast::arr( $m ) );
			if ( null !== $msg ) {
				$msgs[] = $msg;
			}
		}
		$task = self::task_title( $id );
		return new FLXAI_Chat_Detail(
			new FLXAI_Chat_Summary( $id, FLXAI_Cast::str( $chat['title'] ), 0 !== FLXAI_Cast::int( $chat['archived'] ), FLXAI_Cast::str( $chat['status'] ), FLXAI_Cast::int( $chat['updated'] ), '', $task ),
			FLXAI_Cast::str( $chat['model'] ),
			count( FLXAI_Cast::arr( $chat['messages'] ) ),
			$msgs
		);
	}

	/**
	 * Renames and/or archives a chat; null leaves a field as it is.
	 *
	 * @return FLXAI_Chat_Summary|WP_Error
	 */
	public static function update( int $id, ?string $title, ?bool $archived ) {
		$chat = flxai_chat_get( $id );
		if ( ! is_array( $chat ) ) {
			return self::not_found();
		}
		if ( null !== $title ) {
			$chat['title'] = sanitize_text_field( $title );
		}
		if ( null !== $archived ) {
			$chat['archived'] = $archived ? 1 : 0;
		}
		flxai_chat_save( $chat );
		self::changed();
		return new FLXAI_Chat_Summary( $id, FLXAI_Cast::str( $chat['title'] ), 0 !== FLXAI_Cast::int( $chat['archived'] ), FLXAI_Cast::str( $chat['status'] ), time(), '', self::task_title( $id ) );
	}

	/** Deletes a chat with its attachments. */
	public static function delete( int $id ): bool|WP_Error {
		if ( ! is_array( flxai_chat_get( $id ) ) ) {
			return self::not_found();
		}
		$ok = flxai_chat_delete( $id );
		self::changed();
		return (bool) $ok;
	}

	public static function changed(): void {
		if ( function_exists( 'flx_gql_cache_purge' ) ) {
			flx_gql_cache_purge( self::CACHE_TAG );
		}
	}

	private static function not_found(): WP_Error {
		return new WP_Error( 'flxai_chat_missing', __( 'Chat not found.', 'flx-ai' ), array( 'graphql_code' => 'NOT_FOUND', 'field' => 'id' ) );
	}

	/** @param array<mixed> $r Row of flxai_chat_list(). */
	private static function summary( array $r ): FLXAI_Chat_Summary {
		return new FLXAI_Chat_Summary(
			FLXAI_Cast::int( $r['id'] ?? 0 ),
			FLXAI_Cast::str( $r['title'] ?? '' ),
			0 !== FLXAI_Cast::int( $r['archived'] ?? 0 ),
			FLXAI_Cast::str( $r['status'] ?? '' ),
			FLXAI_Cast::int( $r['updated'] ?? 0 ),
			FLXAI_Cast::str( $r['snippet'] ?? '' ),
			FLXAI_Cast::str( $r['task'] ?? '' )
		);
	}

	private static function task_title( int $chat_id ): string {
		/** @var wpdb $wpdb */
		global $wpdb;
		$table = FLXAI_Cast::str( flxai_table( 'tasks' ) );
		return FLXAI_Cast::str( $wpdb->get_var( $wpdb->prepare( 'SELECT title FROM ' . $table . ' WHERE chat_id = %d LIMIT 1', $chat_id ) ) ); // phpcs:ignore
	}

	/** @param array<mixed> $m Stored message (OpenAI shape with `_` fields). */
	private static function message( int $index, array $m ): ?FLXAI_Chat_Message {
		$role = FLXAI_Cast::str( $m['role'] ?? '' );
		if ( ! in_array( $role, array( 'user', 'assistant', 'tool' ), true ) ) {
			return null;
		}
		$content = $m['content'] ?? '';
		$text    = '';
		if ( is_string( $content ) ) {
			$text = $content;
		} elseif ( is_array( $content ) ) {
			foreach ( $content as $part ) {
				$p = FLXAI_Cast::arr( $part );
				if ( isset( $p['text'] ) && is_string( $p['text'] ) ) {
					$text .= ( '' === $text ? '' : "\n" ) . $p['text'];
				}
			}
		}
		$calls = array();
		foreach ( FLXAI_Cast::arr( $m['tool_calls'] ?? array() ) as $c ) {
			$name = FLXAI_Cast::str( FLXAI_Cast::arr( FLXAI_Cast::arr( $c )['function'] ?? array() )['name'] ?? '' );
			if ( '' !== $name ) {
				$calls[] = $name;
			}
		}
		if ( 'tool' === $role ) {
			$text = mb_substr( $text, 0, 4000 );
		}
		$ts = isset( $m['_ts'] ) ? FLXAI_Cast::int( $m['_ts'] ) : 0;
		return new FLXAI_Chat_Message(
			$index,
			$role,
			$text,
			$ts > 0 ? $ts : null,
			FLXAI_Cast::str( $m['_name'] ?? '' ),
			array_key_exists( '_ok', $m ) ? (bool) $m['_ok'] : null,
			FLXAI_Cast::str( $m['_error'] ?? '' ),
			implode( ', ', $calls )
		);
	}
}
