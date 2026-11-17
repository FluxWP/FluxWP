<?php
/**
 * The rest of the classic chat page: quick replies, blocklist, memory, reminders, cleanup,
 * the assistant switch, avatars, and donation / certificate records.
 *
 * Donation and certificate mutations call the plugins' existing PHP. When those packages publish
 * their own GraphQL, the screen switches; this is not a second permanent API.
 *
 * @package FLX_Chat
 */

defined( 'ABSPATH' ) || exit;

final class FLXC_Desk_Service {

	/**
	 * @return array{replies: list<array<string,mixed>>}
	 */
	public static function replies(): array {
		return array( 'replies' => flxc_replies_for_js() );
	}

	/**
	 * @return array{replies: array<int,array<string,mixed>>}|WP_Error
	 */
	public static function save_reply( WP_REST_Request $req ) {
		global $wpdb;
		/** @var wpdb $wpdb */
		$id    = FLXC_Val::raw_int( $req->get_param( 'id' ) );
		$title = mb_substr( sanitize_text_field( FLXC_Val::raw_str( $req->get_param( 'title' ) ) ), 0, 190 );
		$body  = sanitize_textarea_field( FLXC_Val::raw_str( $req->get_param( 'body' ) ) );
		$short = mb_substr( FLXC_Val::str(preg_replace( '/[^\p{L}\p{N}_-]+/u', '', mb_strtolower( FLXC_Val::raw_str( $req->get_param( 'shortcut' ) ) ) )), 0, 60 );
		$cat   = mb_substr( sanitize_text_field( FLXC_Val::raw_str( $req->get_param( 'category' ) ) ), 0, 100 );
		if ( '' === trim( $body ) ) {
			return new WP_Error( 'flxc_reply', __( 'Reply text cannot be empty.', 'flx-chat' ), array( 'status' => 400 ) );
		}
		if ( '' === $title ) {
			$title = mb_substr( $body, 0, 40 );
		}
		$data = array(
			'shortcut'   => $short,
			'title'      => $title,
			'body'       => $body,
			'category'   => $cat,
			'updated_at' => flxc_now(),
		);
		if ( $id ) {
			$wpdb->update( flxc_table( 'replies' ), $data, array( 'id' => $id ) );
		} else {
			$data['created_by'] = get_current_user_id();
			$data['sort']       = FLXC_Val::int($wpdb->get_var( 'SELECT COALESCE(MAX(sort),0)+1 FROM ' . flxc_table( 'replies' ) )); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->insert( flxc_table( 'replies' ), $data );
		}
		FLXC_Chat_Service::changed();
		return array( 'replies' => flxc_replies_for_js() );
	}

	/**
	 * @return array{replies: array<int,array<string,mixed>>}
	 */
	public static function delete_reply( WP_REST_Request $req ): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$wpdb->delete( flxc_table( 'replies' ), array( 'id' => FLXC_Val::raw_int( $req['id'] ) ) );
		FLXC_Chat_Service::changed();
		return array( 'replies' => flxc_replies_for_js() );
	}

	/**
	 * @return array{text: string}
	 */
	public static function use_reply( WP_REST_Request $req ): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$used = $wpdb->prepare( 'UPDATE ' . flxc_table( 'replies' ) . ' SET uses = uses + 1 WHERE id = %d', FLXC_Val::raw_int( $req['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared // @phpstan-ignore argument.type
		if ( is_string( $used ) ) {
			$wpdb->query( $used );
		}
		$chat_id = FLXC_Val::raw_int( $req->get_param( 'chat' ) );
		$text    = '';
		$row     = $wpdb->get_row( $wpdb->prepare( 'SELECT body FROM ' . flxc_table( 'replies' ) . ' WHERE id = %d', FLXC_Val::raw_int( $req['id'] ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared // @phpstan-ignore argument.type
		if ( $row && $chat_id ) {
			$chat = flxc_get_chat( $chat_id );
			if ( $chat && flxc_user_can_see_chat( $chat ) ) {
				$text = flxc_fill_vars( $row['body'], $chat );
			}
		}
		return array( 'text' => $text );
	}

	/**
	 * @return array{items: array<int,array<string,mixed>>, total: int}
	 */
	public static function block_list( WP_REST_Request $req ): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$t = flxc_table( 'blocklist' );
		$q = trim( sanitize_text_field( FLXC_Val::raw_str( $req->get_param( 'q' ) ) ) );
		if ( '' !== $q ) {
			$like = '%' . $wpdb->esc_like( $q ) . '%';
			$dig  = FLXC_Val::str(preg_replace( '/\D+/', '', $q ));
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE email LIKE %s OR reason LIKE %s OR ( %s <> '' AND phone LIKE %s ) ORDER BY id DESC LIMIT 200", $like, $like, $dig, '%' . $dig . '%' ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared // @phpstan-ignore argument.type
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM $t ORDER BY id DESC LIMIT 200", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		return array(
			'items' => array_map( 'flxc_block_for_js', (array) $rows ),
			'total' => FLXC_Val::int($wpdb->get_var( "SELECT COUNT(*) FROM $t" )), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function block_add( WP_REST_Request $req ) {
		$reason = FLXC_Val::raw_str( $req->get_param( 'reason' ) );
		$cid    = FLXC_Val::raw_int( $req->get_param( 'chat' ) );
		if ( $cid ) {
			$chat = flxc_get_chat( $cid );
			if ( ! $chat || ! flxc_user_can_see_chat( $chat ) ) {
				return new WP_Error( 'flxc_404', __( 'Chat not found.', 'flx-chat' ), array( 'status' => 404 ) );
			}
			$id = flxc_block_chat( $chat, $reason, 'agent' );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			FLXC_Chat_Service::changed();
			return array( 'id' => $id, 'chat' => flxc_chat_for_js( flxc_get_chat( $cid ) ) );
		}
		$id = flxc_block_add( FLXC_Val::raw_str( $req->get_param( 'phone' ) ), FLXC_Val::raw_str( $req->get_param( 'email' ) ), $reason, 'agent' );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		FLXC_Chat_Service::changed();
		return array( 'id' => $id );
	}

	/**
	 * @return array{ok: true}|WP_Error
	 */
	public static function block_remove( WP_REST_Request $req ) {
		$ok = flxc_block_remove( FLXC_Val::raw_int( $req['id'] ) );
		if ( ! $ok ) {
			return new WP_Error( 'flxc_404', __( 'No records found.', 'flx-chat' ), array( 'status' => 404 ) );
		}
		FLXC_Chat_Service::changed();
		return array( 'ok' => true );
	}

	/**
	 * @return array{chat: array<string,mixed>}|WP_Error
	 */
	public static function unblock( WP_REST_Request $req ) {
		$chat = flxc_get_chat( FLXC_Val::raw_int( $req['id'] ) );
		if ( ! $chat || ! flxc_user_can_see_chat( $chat ) ) {
			return new WP_Error( 'flxc_404', __( 'Chat not found.', 'flx-chat' ), array( 'status' => 404 ) );
		}
		$id = flxc_block_find( $chat['phone'], '', $chat['jid'] );
		if ( $id ) {
			flxc_block_remove( $id );
			flxc_system_message( $chat, __( 'Contact removed from the blocklist.', 'flx-chat' ) );
			FLXC_Chat_Service::changed();
		}
		return array( 'chat' => flxc_chat_for_js( flxc_get_chat( $chat['id'] ) ) );
	}

	/**
	 * @param array<string,mixed> $chat Chat row.
	 * @return array<string,mixed>
	 */
	public static function memory( array $chat ): array {
		return flxc_mem_for_js( $chat );
	}

	/**
	 * @param array<string,mixed> $chat Chat row.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function memory_save( array $chat, WP_REST_Request $req ) {
		global $wpdb;
		/** @var wpdb $wpdb */
		$k = flxc_mem_key( $req->get_param( 'key' ) );
		$v = flxc_mem_value( $req->get_param( 'value' ) );
		if ( '' === $k || '' === $v ) {
			return new WP_Error( 'flxc_mem', __( 'Key and value are required.', 'flx-chat' ), array( 'status' => 400 ) );
		}
		$item = FLXC_Val::raw_int( $req->get_param( 'item' ) );
		$t    = flxc_table( 'memory' );
		if ( $item ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d AND who = %s", $item, flxc_mem_who( $chat ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared // @phpstan-ignore argument.type
			if ( ! $row ) {
				return new WP_Error( 'flxc_404', __( 'No records found.', 'flx-chat' ), array( 'status' => 404 ) );
			}
			if ( 'fact' === $row['kind'] && $k !== $row['k'] ) {
				$gone = $wpdb->prepare( "DELETE FROM $t WHERE who = %s AND kind = 'fact' AND k = %s AND id <> %d", FLXC_Val::str( $row['who'] ?? '' ), $k, $item ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared // @phpstan-ignore argument.type
				if ( is_string( $gone ) ) {
					$wpdb->query( $gone );
				}
			}
			$wpdb->update( $t, array( 'k' => 'summary' === $row['kind'] ? $row['k'] : $k, 'v' => $v, 'source' => 'agent', 'updated_at' => time() ), array( 'id' => $item ) );
			if ( 'fact' === $row['kind'] ) {
				$old = FLXC_Val::str( $row['k'] ?? '' ) !== $k ? FLXC_Val::str( $row['k'] ?? '' ) . ': ' . FLXC_Val::str( $row['v'] ?? '' ) : FLXC_Val::str( $row['v'] ?? '' );
				flxc_mem_log( $chat, $k, $old, $v, 'agent' );
			}
		} else {
			flxc_mem_set( $chat, $k, $v, 'agent' );
		}
		FLXC_Chat_Service::changed();
		return flxc_mem_for_js( $chat );
	}

	/**
	 * @param array<string,mixed> $chat Chat row.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function memory_forget( array $chat, bool $confirmed ) {
		if ( ! $confirmed ) {
			return new WP_Error( 'flxc_confirm', __( 'Confirmation text is wrong.', 'flx-chat' ), array( 'status' => 400 ) );
		}
		flxc_mem_forget( $chat );
		FLXC_Chat_Service::changed();
		return flxc_mem_for_js( $chat );
	}

	/**
	 * @return array{enabled: bool, items: array<int,array<string,mixed>>}
	 */
	public static function reminders( int $chat_id ): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$t    = flxc_table( 'reminders' );
		$rows = $chat_id
			? flxc_rem_for_chat( $chat_id, 'all' )
			: array_map( 'flxc_rem_row', (array) $wpdb->get_results( "SELECT * FROM $t WHERE status <> 'deleted' ORDER BY (status = 'active') DESC, next_at ASC LIMIT 300", ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array(
			'enabled' => flxc_rem_on(),
			'items'   => array_map( 'flxc_rem_for_js', $rows ),
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function reminder_status( int $id, string $status ) {
		if ( ! in_array( $status, array( 'active', 'paused' ), true ) || ! flxc_rem_set_status( $id, $status ) ) {
			return new WP_Error( 'flxc_rem', __( 'The reminder could not be updated.', 'flx-chat' ), array( 'status' => 400 ) );
		}
		FLXC_Chat_Service::changed();
		$row = flxc_rem_get( $id );
		return is_array( $row ) ? flxc_rem_for_js( $row ) : array();
	}

	public static function reminder_delete( int $id ): bool {
		$ok = FLXC_Val::bool(flxc_rem_set_status( $id, 'deleted' ));
		if ( $ok ) {
			FLXC_Chat_Service::changed();
		}
		return $ok;
	}

	/**
	 * @param array<string,mixed> $chat Chat row.
	 * @param array<string,mixed> $fields Reminder fields for flxc_rem_build.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function reminder_save( array $chat, array $fields, int $id = 0 ) {
		$built = flxc_rem_build( $fields );
		if ( is_wp_error( $built ) ) {
			$built->add_data( array( 'status' => 400 ) );
			return $built;
		}
		$saved = flxc_rem_save( $chat, $built, $id, 'agent' );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		FLXC_Chat_Service::changed();
		$row = flxc_rem_get( FLXC_Val::int($saved) );
		return is_array( $row ) ? flxc_rem_for_js( $row ) : new WP_Error( 'flxc_rem', __( 'The reminder could not be updated.', 'flx-chat' ), array( 'status' => 400 ) );
	}

	/**
	 * @return array{deleted: int, chat: array<string,mixed>}|WP_Error
	 */
	public static function clear( WP_REST_Request $req ) {
		$id   = FLXC_Val::raw_int( $req['id'] );
		$chat = flxc_get_chat( $id );
		if ( ! $chat || ! flxc_user_can_see_chat( $chat ) ) {
			return new WP_Error( 'flxc_404', __( 'Chat not found.', 'flx-chat' ), array( 'status' => 404 ) );
		}
		if ( 'yes' !== FLXC_Val::raw_str( $req->get_param( 'confirm' ) ) ) {
			return new WP_Error( 'flxc_confirm', __( 'Confirmation text is wrong.', 'flx-chat' ), array( 'status' => 400 ) );
		}
		$n = flxc_clear_chat_history( $id, FLXC_Val::raw_bool( $req->get_param( 'keep_receipts' ) ) );
		if ( $req->get_param( 'forget_memory' ) && function_exists( 'flxc_mem_forget' ) ) {
			flxc_mem_forget( $chat );
		}
		FLXC_Chat_Service::changed();
		return array(
			'deleted' => $n,
			'chat'    => flxc_chat_for_js( flxc_get_chat( $id ) ),
		);
	}

	/**
	 * @return array{chat: array<string,mixed>}|WP_Error
	 */
	public static function bot( WP_REST_Request $req ) {
		$chat = flxc_rest_chat_or_error( $req['id'] );
		if ( is_wp_error( $chat ) ) {
			return $chat;
		}
		$b = flxc_bot_state( $chat );
		$wake = false;
		if ( 'on' === $req->get_param( 'state' ) ) {
			$b['off']     = 0;
			$b['until']   = 0;
			$b['last_in'] = flxc_bot_last_in_id( $chat['id'] );
			if ( $b['day'] === wp_date( 'Y-m-d' ) && FLXC_Val::int($b['n']) >= flxc_bot_daily_limit() ) {
				$b['n']   = 0;
				$b['lim'] = '';
			}
			global $wpdb;
			/** @var wpdb $wpdb */
			$mt   = flxc_table( 'messages' );
			$last = $wpdb->get_row( $wpdb->prepare( "SELECT id, direction, ts FROM $mt WHERE chat_id = %d AND direction IN ('in','out') ORDER BY id DESC LIMIT 1", FLXC_Val::int($chat['id']) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared // @phpstan-ignore argument.type
			$wake = $last && 'in' === $last['direction'] && FLXC_Val::int($last['ts']) > ( time() - 30 * MINUTE_IN_SECONDS ) * 1000;
			if ( $wake ) {
				$b['last_in'] = FLXC_Val::int($wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(MAX(id),0) FROM $mt WHERE chat_id = %d AND direction = 'in' AND id < (SELECT COALESCE(MAX(id),0) FROM $mt WHERE chat_id = %d AND direction = 'out')", FLXC_Val::int($chat['id']), FLXC_Val::int($chat['id']) ) )); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared // @phpstan-ignore argument.type
			}
		} else {
			$b['off'] = 1;
		}
		flxc_bot_save_state( $chat['id'], $b );
		if ( $wake ) {
			flxc_bot_queue( FLXC_Val::int($chat['id']) );
		}
		/* translators: %s: user */
		flxc_system_message( $chat, sprintf( 'on' === $req->get_param( 'state' ) ? __( 'Assistant turned on (%s)', 'flx-chat' ) : __( 'Assistant turned off (%s)', 'flx-chat' ), flxc_user_name( get_current_user_id() ) ) );
		FLXC_Chat_Service::changed();
		return array( 'chat' => flxc_chat_for_js( flxc_get_chat( $chat['id'] ) ) );
	}

	/**
	 * @return array{avatar: string, changed: bool}|WP_Error
	 */
	public static function avatar( WP_REST_Request $req ) {
		$chat = flxc_rest_chat_or_error( $req['id'] );
		if ( is_wp_error( $chat ) ) {
			return $chat;
		}
		$changed = flxc_avatar_check( $chat, $req->get_param( 'force' ) ? 0 : flxc_avatar_open_ttl() );
		return array(
			'avatar'  => flxc_avatar_url( flxc_get_chat( $chat['id'] ) ),
			'changed' => FLXC_Val::bool($changed),
		);
	}

	/**
	 * @param array<string,mixed>      $p    Same keys as the classic donation form.
	 * @param array<string,mixed>|null $chat Chat row, or null.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function create_donation( array $p, ?array $chat ) {
		if ( ! flxc_can_record_donation() ) {
			return new WP_Error( 'flxc_forbidden', __( 'You are not allowed to create donation records.', 'flx-chat' ), array( 'status' => 403 ) );
		}
		$p['user_id'] = get_current_user_id();
		$r            = flxc_create_donation( $p, $chat );
		if ( ! is_wp_error( $r ) ) {
			FLXC_Chat_Service::changed();
		}
		return $r;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function create_certificate( WP_REST_Request $req ) {
		if ( ! flxc_can_record_certificate() ) {
			return new WP_Error( 'flxc_forbidden', __( 'You are not allowed to create certificates.', 'flx-chat' ), array( 'status' => 403 ) );
		}
		$p    = (array) $req->get_json_params();
		$chat = ! empty( $p['chat'] ) ? flxc_rest_chat_or_error( FLXC_Val::int($p['chat']) ) : null;
		if ( is_wp_error( $chat ) ) {
			return $chat;
		}
		$phone = FLXC_Val::str(( $p['phone'] ?? ( $chat ? $chat['phone'] : '' ) ));
		$cert  = flxc_make_certificate(
			array(
				'template_id' => FLXC_Val::int(( $p['template_id'] ?? 0 )),
				'values'      => (array) ( $p['values'] ?? array() ),
				'event_id'    => FLXC_Val::int(( $p['event_id'] ?? 0 )),
				'name'        => FLXC_Val::str(( $p['name'] ?? '' )),
				'phone'       => $phone,
				'email'       => FLXC_Val::str(( $p['email'] ?? '' )),
			)
		);
		if ( is_wp_error( $cert ) ) {
			$cert->add_data( array( 'status' => 400 ) );
			return $cert;
		}
		if ( ! empty( $p['donation_id'] ) && flxc_flxb_on() && class_exists( 'FLXB_Donations' ) ) {
			FLXB_Donations::set_certificate( FLXC_Val::int($p['donation_id']), FLXC_Val::str( $cert['code'] ?? '' ) );
		}
		if ( ! empty( $p['receipt'] ) ) {
			flxc_attach_receipt( FLXC_Val::int($p['receipt']), 'certificate', FLXC_Val::int($cert['id']), false );
		}
		if ( $chat ) {
			/* translators: %s: certificate number */
			flxc_system_message( $chat, sprintf( __( 'Certificate created: %s', 'flx-chat' ), FLXC_Val::str( $cert['number'] ?? '' ) ) );
		}
		FLXC_Chat_Service::changed();
		return array( 'certificate' => flxc_certificate_for_js( $cert ) );
	}
}
