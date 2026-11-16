<?php
/**
 * Chats, the live update page and the inbox figures. REST callbacks and GraphQL resolvers both call
 * this class. REST responses stay the arrays the classic client already reads.
 *
 * @package FLX_Chat
 */

defined( 'ABSPATH' ) || exit;

final class FLXC_Chat_Service {

	public const CACHE = 'flx-chat';

	public static function changed(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::CACHE ) );
		}
	}

	/**
	 * Whether $user_id may see a chat with this phone (decision: person fields follow the chat).
	 */
	public static function phone_visible_to( string $phone, int $user_id ): bool {
		global $wpdb;
		/** @var wpdb $wpdb */
		$phone = preg_replace( '/\D+/', '', $phone );
		if ( '' === $phone || $user_id < 1 ) {
			return false;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'chats' ) . ' WHERE phone = %s', $phone ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared // @phpstan-ignore argument.type
		foreach ( (array) $rows as $row ) {
			if ( is_array( $row ) && flxc_user_can_see_chat( $row, $user_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Bootstrap payload (no secrets).
	 *
	 * @return array<string,mixed>
	 */
	public static function bootstrap(): array {
		$u  = wp_get_current_user();
		$ai = flxc_ai_active();
		return array(
			'me'          => array(
				'id'     => FLXC_Val::int($u->ID),
				'name'   => $u->display_name,
				'manage' => flxc_can_manage(),
			),
			'delHours'    => flxc_revoke_limit_hours(),
			'agents'      => flxc_agents(),
			'labels'      => flxc_labels_for_js(),
			'connections' => flxc_connections_for_js(),
			'channels'    => flxc_channels_for_js(),
			'replies'     => flxc_replies_for_js(),
			'unread'      => flxc_unread_total(),
			'ai'          => $ai ? array( 'id' => $ai['id'], 'label' => FLXC_Val::str(( $ai['label'] ?? $ai['id'] )), 'auto' => FLXC_Val::bool(flxc_setting( 'ai_auto_suggest' )) ) : null,
			'bot'         => function_exists( 'flxc_bot_enabled' ) && flxc_bot_enabled(),
			'records'     => flxc_records_boot(),
			'staff'       => flxc_can_manage() && function_exists( 'flxc_staff_link_users' ) ? flxc_staff_link_users() : array(),
			'rev'         => flxc_rev(),
		);
	}

	/**
	 * @return array{chats: array<int,array<string,mixed>>}
	 */
	public static function chats( WP_REST_Request $req ): array {
		$rows = self::query_rows(
			array(
				'filter'  => sanitize_key( FLXC_Val::str($req->get_param( 'filter' )) ),
				'q'       => sanitize_text_field( FLXC_Val::str($req->get_param( 'q' )) ),
				'label'   => sanitize_key( FLXC_Val::str($req->get_param( 'label' )) ),
				'channel' => sanitize_key( FLXC_Val::str($req->get_param( 'channel' )) ),
				'offset'  => max( 0, FLXC_Val::int($req->get_param( 'offset' )) ),
				'limit'   => min( 100, max( 10, FLXC_Val::int(( $req->get_param( 'limit' ) ? $req->get_param( 'limit' ) : 50 )) ) ),
			)
		);
		return array( 'chats' => flxc_chats_for_js( $rows ) );
	}

	/**
	 * Live page without the REST nonce. $chat_limit and $message_limit are the REST sizes (200)
	 * or the GraphQL cap (ask for one extra to know the page was cut).
	 *
	 * @return array<string,mixed>
	 */
	public static function sync_payload( int $since, int $chat_id, int $after, int $chat_limit = 200, int $message_limit = 200 ): array {
		$out = array(
			'rev'         => flxc_rev(),
			'chats'       => array(),
			'messages'    => array(),
			'incoming'    => array(),
			'connections' => flxc_connections_for_js(),
			'unread'      => flxc_unread_total(),
		);
		if ( $since > 0 ) {
			$rows             = self::query_rows( array( 'since' => $since, 'limit' => max( 1, $chat_limit ) ) );
			$out['chats']     = flxc_chats_for_js( $rows );
			$out['incoming']  = flxc_new_incoming_since( $since );
			if ( $chat_id ) {
				$c = flxc_get_chat( $chat_id );
				if ( $c && flxc_user_can_see_chat( $c ) ) {
					$out['messages'] = flxc_messages_for_js( flxc_messages_since( $chat_id, $since, $after, $message_limit ) );
				}
			}
		}
		return $out;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function chat( WP_REST_Request $req ) {
		$chat = flxc_rest_chat_or_error( $req['id'] );
		if ( is_wp_error( $chat ) ) {
			return $chat;
		}
		return array(
			'chat'     => flxc_chats_for_js( array( $chat ) )[0],
			'sections' => flxc_contact_sections( $chat ),
			'log'      => function_exists( 'flxc_clog_for_js' ) ? flxc_clog_for_js( FLXC_Val::int($chat['id']), 100 ) : array(),
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function create( WP_REST_Request $req ) {
		$phone = flxc_normalize_phone( FLXC_Val::str($req->get_param( 'phone' )) );
		if ( ! $phone ) {
			return new WP_Error( 'flxc_phone', __( 'Invalid phone number.', 'flx-chat' ), array( 'status' => 400 ) );
		}
		$conn_id = sanitize_key( FLXC_Val::str($req->get_param( 'conn' )) );
		$conn    = $conn_id ? flxc_get_connection( $conn_id ) : flxc_default_connection();
		if ( ! is_array( $conn ) ) {
			return new WP_Error( 'flxc_conn', __( 'First add a WhatsApp connection under Settings › Connections.', 'flx-chat' ), array( 'status' => 400 ) );
		}
		$conn_key = FLXC_Val::str( $conn['id'] ?? '' );
		$jid      = $phone . '@s.whatsapp.net';
		if ( $req->get_param( 'check' ) ) {
			$chk = flxc_evo_check_numbers( $conn_key, array( $phone ) );
			$one = is_array( $chk[ $phone ] ?? null ) ? $chk[ $phone ] : null;
			if ( is_array( $one ) ) {
				if ( empty( $one['exists'] ) ) {
					return new WP_Error( 'flxc_no_wa', __( 'This number is not on WhatsApp.', 'flx-chat' ), array( 'status' => 400 ) );
				}
				if ( ! empty( $one['jid'] ) ) {
					$jid = FLXC_Val::str( $one['jid'] );
				}
			}
		}
		$chat = flxc_ensure_chat( $conn_key, $jid );
		if ( ! is_array( $chat ) ) {
			return new WP_Error( 'flxc_chat', __( 'The chat could not be opened.', 'flx-chat' ), array( 'status' => 400 ) );
		}
		$name = sanitize_text_field( FLXC_Val::str($req->get_param( 'name' )) );
		if ( $name && $name !== FLXC_Val::str( $chat['name'] ?? '' ) ) {
			flxc_update_chat( $chat['id'] ?? 0, array( 'name' => $name ) );
		}
		if ( 'closed' === ( $chat['status'] ?? '' ) ) {
			flxc_update_chat( $chat['id'] ?? 0, array( 'status' => 'open' ) );
		}
		self::changed();
		return array( 'chat' => flxc_chats_for_js( array( flxc_get_chat( $chat['id'] ) ) )[0] );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function check_number( WP_REST_Request $req ): array {
		$phone = flxc_normalize_phone( FLXC_Val::str($req->get_param( 'phone' )) );
		if ( ! $phone ) {
			return array( 'valid' => false );
		}
		$conn   = flxc_get_connection( sanitize_key( FLXC_Val::str($req->get_param( 'conn' )) ) );
		$conn   = is_array( $conn ) ? $conn : flxc_default_connection();
		$exists = null;
		if ( is_array( $conn ) ) {
			$chk = flxc_evo_check_numbers( FLXC_Val::str( $conn['id'] ?? '' ), array( $phone ) );
			$one = is_array( $chk[ $phone ] ?? null ) ? $chk[ $phone ] : null;
			if ( is_array( $one ) ) {
				$exists = $one['exists'];
			}
		}
		$found = is_array( $conn ) ? flxc_find_chat_by_phone( FLXC_Val::str( $conn['id'] ?? '' ), $phone ) : null;
		return array(
			'valid'    => true,
			'phone'    => $phone,
			'phoneFmt' => flxc_format_phone( $phone ),
			'exists'   => $exists,
			'chat'     => $found ? FLXC_Val::int($found['id']) : 0,
			'name'     => $found ? flxc_chat_title( $found ) : flxc_lookup_contact_name( $phone ),
		);
	}

	/**
	 * GraphQL check. REST keeps check_number(), which still returns a chat the viewer cannot see.
	 * This one drops that chat's id and title.
	 *
	 * @return array<string,mixed>
	 */
	public static function check_number_for_viewer( WP_REST_Request $req ): array {
		$out = self::check_number( $req );
		$id  = FLXC_Val::int(( $out['chat'] ?? 0 ));
		if ( $id < 1 ) {
			return $out;
		}
		$row = flxc_get_chat( $id );
		if ( is_array( $row ) && flxc_user_can_see_chat( $row ) ) {
			return $out;
		}
		$out['chat'] = 0;
		$out['name'] = flxc_lookup_contact_name( FLXC_Val::str(( $out['phone'] ?? '' )) );
		return $out;
	}

	/**
	 * Chats changed since a rev, oldest rev first, so a 50-row page can be continued.
	 * REST sync keeps flxc_query_chats() (pinned, then last message).
	 *
	 * When $after_id is set, the page starts after that chat at the same rev
	 * (`rev > since OR (rev = since AND id > after_id)`).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function changes_since( int $since, int $after_id, int $limit ): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$where = array();
		$prm   = array();
		if ( $after_id > 0 ) {
			$where[] = '(rev > %d OR (rev = %d AND id > %d))';
			array_push( $prm, $since, $since, $after_id );
		} else {
			$where[] = 'rev > %d';
			$prm[]   = $since;
		}
		if ( ! flxc_setting( 'show_groups' ) ) {
			$where[] = "jid NOT LIKE '%@g.us'";
		}
		if ( flxc_setting( 'restrict_agents' ) && ! flxc_can_manage() ) {
			$where[] = '(assigned_to = 0 OR assigned_to = %d)';
			$prm[]   = get_current_user_id();
		}
		$prm[] = max( 1, $limit );
		$sql   = 'SELECT * FROM ' . flxc_table( 'chats' ) . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY rev ASC, id ASC LIMIT %d';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $prm ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared // @phpstan-ignore argument.type
		$out  = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$row = flx_gql_arr( $row );
				if ( flxc_user_can_see_chat( $row ) ) {
					$out[] = $row;
				}
			}
		}
		return $out;
	}

	/**
	 * @return array{chat: array<string,mixed>}|WP_Error
	 */
	public static function update( WP_REST_Request $req ) {
		$chat = flxc_rest_chat_or_error( $req['id'] );
		if ( is_wp_error( $chat ) ) {
			return $chat;
		}
		$upd = array();
		if ( null !== $req->get_param( 'name' ) ) {
			$upd['name'] = mb_substr( sanitize_text_field( FLXC_Val::str($req->get_param( 'name' )) ), 0, 190 );
		}
		if ( null !== $req->get_param( 'notes' ) ) {
			$upd['notes'] = sanitize_textarea_field( FLXC_Val::str($req->get_param( 'notes' )) );
		}
		if ( null !== $req->get_param( 'labels' ) ) {
			$valid = array();
			foreach ( flxc_labels_for_js() as $lab ) {
				$valid[] = FLXC_Val::str( $lab['id'] ?? '' );
			}
			$picked = $req->get_param( 'labels' );
			$picked = is_array( $picked ) ? $picked : array();
			$ls     = array_values( array_intersect( array_map( static fn( $v ): string => sanitize_key( FLXC_Val::str( $v ) ), $picked ), $valid ) );
			$upd['labels'] = implode( ',', $ls );
		}
		if ( null !== $req->get_param( 'status' ) ) {
			$st = sanitize_key( FLXC_Val::str($req->get_param( 'status' )) );
			if ( in_array( $st, array( 'open', 'closed' ), true ) ) {
				$upd['status'] = $st;
				if ( 'closed' === $st ) {
					$upd['unread'] = 0;
				}
			}
		}
		if ( null !== $req->get_param( 'assigned' ) ) {
			$a = FLXC_Val::int($req->get_param( 'assigned' ));
			if ( 0 === $a || in_array( $a, wp_list_pluck( flxc_agents(), 'id' ), true ) ) {
				$upd['assigned_to'] = $a;
			}
		}
		if ( null !== $req->get_param( 'pinned' ) ) {
			$upd['pinned'] = $req->get_param( 'pinned' ) ? 1 : 0;
		}
		if ( null !== $req->get_param( 'unread' ) ) {
			$upd['unread'] = max( 0, FLXC_Val::int($req->get_param( 'unread' )) );
		}
		if ( null !== $req->get_param( 'staff_user' ) && function_exists( 'flxc_staff_link_chat' ) ) {
			if ( ! flxc_can_manage() ) {
				return new WP_Error( 'flxc_forbidden', __( 'You do not have permission for this action.', 'flx-chat' ), array( 'status' => 403 ) );
			}
			$r = flxc_staff_link_chat( $chat, FLXC_Val::int($req->get_param( 'staff_user' )) );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		if ( $upd ) {
			flxc_update_chat( $chat['id'], $upd );
			if ( isset( $upd['assigned_to'] ) && FLXC_Val::int($upd['assigned_to']) !== FLXC_Val::int($chat['assigned_to']) ) {
				$who = $upd['assigned_to'] ? flxc_user_name( $upd['assigned_to'] ) : '';
				/* translators: 1: agent who changed, 2: assignee */
				$txt = $who ? sprintf( __( '%1$s assigned the chat to %2$s', 'flx-chat' ), flxc_user_name( get_current_user_id() ), $who ) : sprintf( __( '%s removed the chat assignment', 'flx-chat' ), flxc_user_name( get_current_user_id() ) );
				flxc_system_message( $chat, $txt );
			}
			if ( isset( $upd['status'] ) && $upd['status'] !== $chat['status'] ) {
				/* translators: %s: agent name */
				$txt = 'closed' === $upd['status'] ? sprintf( __( '%s closed the chat', 'flx-chat' ), flxc_user_name( get_current_user_id() ) ) : sprintf( __( '%s reopened the chat', 'flx-chat' ), flxc_user_name( get_current_user_id() ) );
				flxc_system_message( $chat, $txt );
			}
		}
		self::changed();
		return array( 'chat' => flxc_chats_for_js( array( flxc_get_chat( $chat['id'] ) ) )[0] );
	}

	/**
	 * @param array<string,mixed> $args Query arguments for flxc_query_chats (count must be false).
	 * @return array<int,array<string,mixed>>
	 */
	public static function query_rows( array $args ): array {
		$rows = flxc_query_chats( $args );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array<string,mixed> $args Filter arguments (no since).
	 */
	public static function count( array $args ): int {
		$n = flxc_query_chats( array_merge( $args, array( 'count' => true ) ) );
		return is_int( $n ) ? $n : FLXC_Val::int($n);
	}

	/**
	 * A visible chat row, or null.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function visible( int $id ): ?array {
		$row = flxc_get_chat( $id );
		if ( ! is_array( $row ) || ! flxc_user_can_see_chat( $row ) ) {
			return null;
		}
		return $row;
	}
}
