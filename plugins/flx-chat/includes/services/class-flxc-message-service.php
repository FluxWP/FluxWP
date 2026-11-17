<?php
/**
 * Messages of a chat: list, send, edit, delete, react, retry, read, typing, suggestion.
 * REST callbacks delegate here so GraphQL runs the same code. Return arrays match the classic client.
 *
 * @package FLX_Chat
 */

defined( 'ABSPATH' ) || exit;

final class FLXC_Message_Service {

	/**
	 * @return array{messages: list<array<string,mixed>>, more: bool, note: string}|WP_Error
	 */
	public static function list_for( WP_REST_Request $req, int $limit = 40 ) {
		$chat = flxc_rest_chat_or_error( $req['id'] );
		if ( is_wp_error( $chat ) ) {
			return $chat;
		}
		$limit  = max( 1, $limit );
		$before = FLXC_Val::raw_int( $req->get_param( 'before' ) );
		$rows   = flxc_chat_messages( $chat['id'], $before, $limit );
		$hist   = (array) ( flxc_decode( $chat['meta'] )['hist'] ?? array() );
		$note   = '';
		if ( count( $rows ) < $limit && empty( $hist['done'] ) ) {
			$r = flxc_fetch_chat_history( $chat );
			if ( is_wp_error( $r ) ) {
				$note = $r->get_error_message();
			} elseif ( $r ) {
				$rows = flxc_chat_messages( $chat['id'], $before, $limit );
			}
			$fresh = flxc_get_chat( $chat['id'] );
			if ( is_array( $fresh ) ) {
				$chat = $fresh;
			}
			$hist = (array) ( flxc_decode( $chat['meta'] )['hist'] ?? array() );
		}
		return array(
			'messages' => flxc_messages_for_js( $rows ),
			'more'     => count( $rows ) >= $limit || ( empty( $hist['done'] ) && ! $note && $rows ),
			'note'     => $note,
		);
	}

	/**
	 * @return array{message: array<string,mixed>}|WP_Error
	 */
	public static function send( WP_REST_Request $req ) {
		$chat = flxc_rest_chat_or_error( $req['id'] );
		if ( is_wp_error( $chat ) ) {
			return $chat;
		}
		$type  = sanitize_key( FLXC_Val::raw_str( $req->get_param( 'type' ) ) );
		$type  = $type ? $type : 'text';
		$body  = FLXC_Val::raw_str( $req->get_param( 'body' ) );
		$body  = str_replace( "\r\n", "\n", wp_check_invalid_utf8( $body ) );
		$args  = array(
			'type'      => $type,
			'body'      => $body,
			'quoted_id' => FLXC_Val::raw_int( $req->get_param( 'quoted' ) ),
			'source'    => in_array( $req->get_param( 'source' ), array( 'quick', 'ai' ), true ) ? $req->get_param( 'source' ) : 'agent',
			'preview'   => false !== $req->get_param( 'preview' ) && '0' !== FLXC_Val::raw_str( $req->get_param( 'preview' ) ),
		);
		$files  = $req->get_file_params();
		$upload = sanitize_key( FLXC_Val::raw_str( $req->get_param( 'upload' ) ) );
		$staged = '';
		if ( $upload ) {
			$up = flxc_upload_take( $upload );
			if ( is_wp_error( $up ) ) {
				return $up;
			}
			$type         = 'audio' === $type ? 'audio' : $up['type'];
			$args['type'] = $type;
			$args['file'] = $up['file'];
			$args['mime'] = $up['mime'];
			$args['name'] = $up['name'];
			$staged       = FLXC_Val::str($up['file']);
		} elseif ( is_array( $files['file'] ?? null ) && empty( $files['file']['error'] ) ) {
			$chk = flxc_upload_check( $files['file'], FLXC_Val::raw_str( $req->get_param( 'name' ) ), $type );
			if ( is_wp_error( $chk ) ) {
				return $chk;
			}
			$type         = $chk['type'];
			$args['type'] = $type;
			$args['file'] = FLXC_Val::str( $files['file']['tmp_name'] ?? '' );
			$args['mime'] = $chk['mime'];
			$args['name'] = $chk['name'];
		} elseif ( 'text' !== $type && 'note' !== $type ) {
			return new WP_Error( 'flxc_file', __( 'File could not be uploaded.', 'flx-chat' ), array( 'status' => 400 ) );
		}
		if ( in_array( $type, array( 'text', 'note' ), true ) && '' === trim( $body ) ) {
			return new WP_Error( 'flxc_empty', __( 'Message is empty.', 'flx-chat' ), array( 'status' => 400 ) );
		}
		$m = flxc_send( $chat, $args );
		if ( '' !== $staged ) {
			wp_delete_file( $staged );
		}
		if ( is_wp_error( $m ) ) {
			$m->add_data( array( 'status' => 400 ) );
			return $m;
		}
		FLXC_Chat_Service::changed();
		return array( 'message' => flxc_messages_for_js( array( $m ) )[0] );
	}

	/**
	 * Sends a file already taken from the kit upload (GraphQL uploadId). REST keeps its own token.
	 *
	 * @param array<string,mixed> $chat Chat row.
	 * @param array{path:string,name:string,mime:string,size:int} $file Taken upload.
	 * @return array{message: array<string,mixed>}|WP_Error
	 */
	public static function send_upload( array $chat, string $type, string $body, int $quoted, string $source, bool $preview, array $file, bool $voice = false ) {
		$type = sanitize_key( $type );
		if ( $voice ) {
			$type = 'audio';
		}
		$args = array(
			'type'      => '' !== $type ? $type : 'document',
			'body'      => str_replace( "\r\n", "\n", wp_check_invalid_utf8( $body ) ),
			'quoted_id' => $quoted,
			'source'    => in_array( $source, array( 'quick', 'ai' ), true ) ? $source : 'agent',
			'preview'   => $preview,
			'file'      => $file['path'],
			'mime'      => $file['mime'],
			'name'      => $file['name'],
		);
		if ( 'audio' === $args['type'] ) {
			$args['ptt'] = $voice;
		}
		$chk = flxc_upload_check(
			array( 'size' => $file['size'], 'name' => $file['name'], 'type' => $file['mime'], 'tmp_name' => $file['path'] ),
			$file['name'],
			$args['type']
		);
		if ( is_wp_error( $chk ) ) {
			return $chk;
		}
		$args['type'] = $chk['type'];
		$args['mime'] = $chk['mime'];
		$args['name'] = $chk['name'];
		$m = flxc_send( $chat, $args );
		if ( is_wp_error( $m ) ) {
			$m->add_data( array( 'status' => 400 ) );
			return $m;
		}
		FLXC_Chat_Service::changed();
		return array( 'message' => flxc_messages_for_js( array( $m ) )[0] );
	}

	/**
	 * @return array{message: array<string,mixed>}|WP_Error
	 */
	public static function retry( WP_REST_Request $req ) {
		$m = flxc_get_message( FLXC_Val::raw_int( $req['id'] ) );
		if ( ! $m || 'out' !== $m['direction'] || 'failed' !== $m['status'] ) {
			return new WP_Error( 'flxc_retry', __( 'This message cannot be resent.', 'flx-chat' ), array( 'status' => 400 ) );
		}
		$chat = flxc_rest_chat_or_error( $m['chat_id'] );
		if ( is_wp_error( $chat ) ) {
			return $chat;
		}
		$res = flxc_send(
			$chat,
			array(
				'type'      => $m['type'],
				'body'      => $m['body'],
				'quoted_id' => FLXC_Val::int($m['quoted_id']),
				'mime'      => $m['media_mime'],
				'name'      => $m['media_name'],
				'retry_id'  => FLXC_Val::int($m['id']),
				'user_id'   => get_current_user_id(),
				'preview'   => empty( flxc_decode( $m['meta'] )['nolp'] ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		FLXC_Chat_Service::changed();
		return array( 'message' => flxc_messages_for_js( array( $res ) )[0] );
	}

	/**
	 * @return array{message: array<string,mixed>}|WP_Error
	 */
	public static function react( WP_REST_Request $req ) {
		$m = flxc_get_message( FLXC_Val::raw_int( $req['id'] ) );
		if ( ! $m || '' === $m['wa_id'] ) {
			return new WP_Error( 'flxc_react', __( 'Can\'t react to this message.', 'flx-chat' ), array( 'status' => 400 ) );
		}
		$chat = flxc_rest_chat_or_error( $m['chat_id'] );
		if ( is_wp_error( $chat ) ) {
			return $chat;
		}
		$emoji = mb_substr( FLXC_Val::raw_str( $req->get_param( 'emoji' ) ), 0, 8 );
		$r     = flxc_evo_send_reaction( $chat['conn'], $chat['jid'], 'out' === $m['direction'], $m['wa_id'], $emoji );
		if ( empty( $r['ok'] ) ) {
			return new WP_Error( 'flxc_react', FLXC_Val::str( $r['error'] ?? '' ), array( 'status' => 400 ) );
		}
		flxc_apply_reaction( $chat['conn'], $m['wa_id'], 'out', $emoji );
		FLXC_Chat_Service::changed();
		return array( 'message' => self::message_js( FLXC_Val::int( $m['id'] ?? 0 ) ) );
	}

	/**
	 * @return array{message: array<string,mixed>}|WP_Error
	 */
	public static function delete( WP_REST_Request $req ) {
		$m = flxc_get_message( FLXC_Val::raw_int( $req['id'] ) );
		if ( ! $m || 'system' === $m['direction'] ) {
			return new WP_Error( 'flxc_del', __( 'Message not found.', 'flx-chat' ), array( 'status' => 404 ) );
		}
		$chat = flxc_rest_chat_or_error( $m['chat_id'] );
		if ( is_wp_error( $chat ) ) {
			return $chat;
		}
		if ( 'deleted' === $m['type'] ) {
			return new WP_Error( 'flxc_del', __( 'This message was already deleted.', 'flx-chat' ), array( 'status' => 400 ) );
		}
		$me    = get_current_user_id();
		$own   = FLXC_Val::int($m['user_id']) === $me;
		$scope = 'everyone' === $req->get_param( 'scope' ) ? 'everyone' : 'panel';
		if ( 'note' === $m['direction'] ) {
			if ( ! $own && ! flxc_can_manage() ) {
				return new WP_Error( 'flxc_del', __( 'You can only delete your own notes.', 'flx-chat' ), array( 'status' => 403 ) );
			}
			$scope = 'panel';
		}
		if ( 'everyone' === $scope ) {
			if ( ! $own && ! flxc_can_manage() ) {
				return new WP_Error( 'flxc_del', __( 'Only the sender or an administrator can delete this message for everyone.', 'flx-chat' ), array( 'status' => 403 ) );
			}
			$until = flxc_revoke_until( $m );
			if ( ! $until ) {
				return new WP_Error( 'flxc_del', __( 'This message can\'t be deleted for everyone on this channel. You can hide it in the panel instead.', 'flx-chat' ), array( 'status' => 400 ) );
			}
			if ( time() * 1000 > $until ) {
				return new WP_Error( 'flxc_del', __( 'The time limit for deleting for everyone has passed. You can hide it in the panel instead.', 'flx-chat' ), array( 'status' => 400 ) );
			}
			$r = flxc_evo_delete_for_everyone( $chat['conn'], $chat['jid'], $m['wa_id'] );
			if ( empty( $r['ok'] ) ) {
				return new WP_Error( 'flxc_del', '' !== FLXC_Val::str( $r['error'] ?? '' ) ? FLXC_Val::str( $r['error'] ?? '' ) : __( 'WhatsApp did not delete the message.', 'flx-chat' ), array( 'status' => 400 ) );
			}
		}
		flxc_soft_delete_message( $m, $scope, $me );
		do_action( 'flxc_message_deleted', FLXC_Val::int($m['id']), $scope, $me );
		FLXC_Chat_Service::changed();
		return array( 'message' => self::message_js( FLXC_Val::int( $m['id'] ?? 0 ) ) );
	}

	/**
	 * @return array{message: array<string,mixed>}|WP_Error
	 */
	public static function edit( WP_REST_Request $req ) {
		$m = flxc_get_message( FLXC_Val::raw_int( $req['id'] ) );
		if ( ! $m || 'out' !== $m['direction'] ) {
			return new WP_Error( 'flxc_edit', __( 'Message not found.', 'flx-chat' ), array( 'status' => 404 ) );
		}
		$chat = flxc_rest_chat_or_error( $m['chat_id'] );
		if ( is_wp_error( $chat ) ) {
			return $chat;
		}
		$me = get_current_user_id();
		if ( FLXC_Val::int($m['user_id']) !== $me && ! flxc_can_manage() ) {
			return new WP_Error( 'flxc_edit', __( 'Only the sender or an administrator can edit this message.', 'flx-chat' ), array( 'status' => 403 ) );
		}
		$until = flxc_edit_until( $m );
		if ( ! $until ) {
			return new WP_Error( 'flxc_edit', __( 'This message can\'t be edited on this channel.', 'flx-chat' ), array( 'status' => 400 ) );
		}
		if ( time() * 1000 > $until ) {
			return new WP_Error( 'flxc_edit', __( 'The time limit for editing has passed (WhatsApp allows 15 minutes).', 'flx-chat' ), array( 'status' => 400 ) );
		}
		$text = trim( FLXC_Val::raw_str( $req->get_param( 'text' ) ) );
		if ( '' === $text ) {
			return new WP_Error( 'flxc_edit', __( 'The message can\'t be empty.', 'flx-chat' ), array( 'status' => 400 ) );
		}
		if ( $text === $m['body'] ) {
			return array( 'message' => flxc_messages_for_js( array( $m ) )[0] );
		}
		$r = flxc_evo_edit_message( $chat['conn'], $chat['jid'], $m['wa_id'], $text );
		if ( empty( $r['ok'] ) ) {
			return new WP_Error( 'flxc_edit', '' !== FLXC_Val::str( $r['error'] ?? '' ) ? FLXC_Val::str( $r['error'] ?? '' ) : __( 'WhatsApp did not edit the message.', 'flx-chat' ), array( 'status' => 400 ) );
		}
		flxc_apply_edit( $m, $text, $me );
		do_action( 'flxc_message_edited', FLXC_Val::int($m['id']), $text, $me );
		FLXC_Chat_Service::changed();
		return array( 'message' => self::message_js( FLXC_Val::int( $m['id'] ?? 0 ) ) );
	}

	/**
	 * @return array{ok: true}|WP_Error
	 */
	public static function read( WP_REST_Request $req ) {
		global $wpdb;
		/** @var wpdb $wpdb */
		$chat = flxc_rest_chat_or_error( $req['id'] );
		if ( is_wp_error( $chat ) ) {
			return $chat;
		}
		$n = FLXC_Val::int($chat['unread']);
		if ( $n > 0 ) {
			flxc_update_chat( $chat['id'], array( 'unread' => 0 ) );
			if ( flxc_setting( 'send_read' ) ) {
				$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT wa_id FROM ' . flxc_table( 'messages' ) . " WHERE chat_id = %d AND direction = 'in' AND wa_id <> '' ORDER BY ts DESC LIMIT %d", $chat['id'], min( 20, $n ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared // @phpstan-ignore argument.type
				if ( $ids ) {
					flxc_evo_mark_read( $chat['conn'], $chat['jid'], $ids );
				}
			}
			FLXC_Chat_Service::changed();
		}
		return array( 'ok' => true );
	}

	/**
	 * @return array{ok: true}|WP_Error
	 */
	public static function typing( WP_REST_Request $req ) {
		$chat = flxc_rest_chat_or_error( $req['id'] );
		if ( is_wp_error( $chat ) ) {
			return $chat;
		}
		$key = 'flxc_mytyping_' . FLXC_Val::int( $chat['id'] ?? 0 );
		if ( ! get_transient( $key ) ) {
			set_transient( $key, 1, 4 );
			flxc_evo_presence( $chat['conn'], flxc_chat_number( $chat ), 'recording' === $req->get_param( 'state' ) ? 'recording' : 'composing', 4000 );
		}
		return array( 'ok' => true );
	}

	/**
	 * @return array{text: string, cached?: bool, last: int}|WP_Error
	 */
	public static function suggest( WP_REST_Request $req ) {
		$chat = flxc_rest_chat_or_error( $req['id'] );
		if ( is_wp_error( $chat ) ) {
			return $chat;
		}
		$hint = mb_substr( sanitize_text_field( FLXC_Val::raw_str( $req->get_param( 'hint' ) ) ), 0, 300 );
		global $wpdb;
		/** @var wpdb $wpdb */
		$last  = FLXC_Val::int($wpdb->get_var( $wpdb->prepare( 'SELECT MAX(id) FROM ' . flxc_table( 'messages' ) . ' WHERE chat_id = %d', $chat['id'] ) )); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared // @phpstan-ignore argument.type
		$ckey  = 'flxc_ais_' . FLXC_Val::int( $chat['id'] ?? 0 ) . '_' . $last;
		$fresh = FLXC_Val::raw_bool( $req->get_param( 'fresh' ) ) || '' !== $hint;
		if ( ! $fresh ) {
			$hit = get_transient( $ckey );
			if ( is_string( $hit ) && '' !== $hit ) {
				return array( 'text' => $hit, 'cached' => true, 'last' => $last );
			}
		}
		$r = flxc_ai_suggest( $chat, $hint );
		if ( is_wp_error( $r ) ) {
			$r->add_data( array( 'status' => 400 ) );
			return $r;
		}
		set_transient( $ckey, $r, 6 * HOUR_IN_SECONDS );
		return array( 'text' => $r, 'last' => $last );
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function message_js( int $id ): array {
		$row = flxc_get_message( $id );
		if ( ! is_array( $row ) ) {
			return array();
		}
		return flxc_messages_for_js( array( $row ) )[0];
	}
}
