<?php
defined( 'ABSPATH' ) || exit;

/*
 * Bu eklentinin REST cevapları önbelleğe alınmaz. Hostinger/LiteSpeed GET cevabını saklayınca
 * yenilemede eski veri (ör. boş benchmark listesi) geliyordu. Kendi Cache-Control'ü olan uçlar (medya) korunur.
 */
add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		if ( 0 === strpos( $request->get_route(), '/flx-chat/v1/' ) ) {
			do_action( 'litespeed_control_set_nocache', 'flx-chat rest' );
		}
		return $result;
	},
	10,
	3
);
add_filter(
	'rest_post_dispatch',
	function ( $response, $server, $request ) {
		if ( 0 === strpos( $request->get_route(), '/flx-chat/v1/' ) && $response instanceof WP_REST_Response ) {
			$h = $response->get_headers();
			if ( empty( $h['Cache-Control'] ) ) {
				$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );
				$response->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
			}
		}
		return $response;
	},
	10,
	3
);

add_action( 'rest_api_init', 'flxc_register_rest' );

function flxc_register_rest() {
	$ns   = 'flx-chat/v1';
	$use  = function () {
		return flxc_can_use();
	};
	$mng  = function () {
		return flxc_can_manage();
	};
	$routes = array(
		array( '/bootstrap', 'GET', 'flxc_rest_bootstrap', $use ),
		array( '/sync', 'GET', 'flxc_rest_sync', $use ),
		array( '/chats', 'GET', 'flxc_rest_chats', $use ),
		array( '/chats', 'POST', 'flxc_rest_new_chat', $use ),
		array( '/chats/(?P<id>\d+)', 'GET', 'flxc_rest_chat', $use ),
		array( '/chats/(?P<id>\d+)', 'POST', 'flxc_rest_update_chat', $use ),
		array( '/chats/(?P<id>\d+)/messages', 'GET', 'flxc_rest_messages', $use ),
		array( '/chats/(?P<id>\d+)/messages', 'POST', 'flxc_rest_send', $use ),
		array( '/chats/(?P<id>\d+)/read', 'POST', 'flxc_rest_read', $use ),
		array( '/chats/(?P<id>\d+)/typing', 'POST', 'flxc_rest_typing', $use ),
		array( '/chats/(?P<id>\d+)/avatar', 'POST', 'flxc_rest_avatar', $use ),
		array( '/chats/(?P<id>\d+)/ai', 'POST', 'flxc_rest_ai', $use ),
		array( '/chats/(?P<id>\d+)/export', 'POST', 'flxc_rest_export', $use ),
		array( '/messages/(?P<id>\d+)/retry', 'POST', 'flxc_rest_retry', $use ),
		array( '/messages/(?P<id>\d+)/react', 'POST', 'flxc_rest_react', $use ),
		array( '/messages/(?P<id>\d+)', 'DELETE', 'flxc_rest_delete_note', $use ),
		array( '/media/(?P<id>\d+)', 'GET', 'flxc_rest_media', $use ),
		array( '/check-number', 'POST', 'flxc_rest_check_number', $use ),
		array( '/link-preview', 'GET', 'flxc_rest_link_preview', $use ),
		array( '/replies', 'GET', 'flxc_rest_replies', $use ),
		array( '/replies', 'POST', 'flxc_rest_save_reply', $use ),
		array( '/replies/(?P<id>\d+)', 'DELETE', 'flxc_rest_delete_reply', $use ),
		array( '/replies/(?P<id>\d+)/use', 'POST', 'flxc_rest_use_reply', $use ),
		array( '/admin/connections/(?P<conn>[a-z0-9]+)/test', 'POST', 'flxc_rest_conn_test', $mng ),
		array( '/admin/connections/(?P<conn>[a-z0-9]+)/webhook', 'POST', 'flxc_rest_conn_webhook', $mng ),
		array( '/admin/connections/(?P<conn>[a-z0-9]+)/qr', 'POST', 'flxc_rest_conn_qr', $mng ),
		array( '/admin/connections/(?P<conn>[a-z0-9]+)/import', 'POST', 'flxc_rest_conn_import', $mng ),
	);
	foreach ( $routes as $r ) {
		register_rest_route(
			$ns,
			$r[0],
			array(
				'methods'             => $r[1],
				'callback'            => $r[2],
				'permission_callback' => $r[3],
			)
		);
	}
}

function flxc_rest_chat_or_error( $id ) {
	$chat = flxc_get_chat( (int) $id );
	if ( ! $chat || ! flxc_user_can_see_chat( $chat ) ) {
		return new WP_Error( 'flxc_not_found', __( 'Chat not found.', 'flx-chat' ), array( 'status' => 404 ) );
	}
	return $chat;
}

function flxc_replies_for_js() {
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT * FROM ' . flxc_table( 'replies' ) . ' ORDER BY sort ASC, id ASC', ARRAY_A );
	$out  = array();
	foreach ( $rows as $r ) {
		$out[] = array(
			'id'       => (int) $r['id'],
			'shortcut' => $r['shortcut'],
			'title'    => $r['title'],
			'body'     => (string) $r['body'],
			'category' => $r['category'],
			'uses'     => (int) $r['uses'],
		);
	}
	return $out;
}

function flxc_labels_for_js() {
	$out = array();
	foreach ( (array) flxc_setting( 'labels' ) as $l ) {
		if ( ! empty( $l['id'] ) ) {
			$out[] = array( 'id' => (string) $l['id'], 'name' => (string) $l['name'], 'color' => (string) $l['color'] );
		}
	}
	return $out;
}

function flxc_rest_bootstrap() {
	$u  = wp_get_current_user();
	$ai = flxc_ai_active();
	return array(
		'me'          => array(
			'id'     => (int) $u->ID,
			'name'   => $u->display_name,
			'manage' => flxc_can_manage(),
		),
		'agents'      => flxc_agents(),
		'labels'      => flxc_labels_for_js(),
		'connections' => flxc_connections_for_js(),
		'channels'    => flxc_channels_for_js(),
		'replies'     => flxc_replies_for_js(),
		'unread'      => flxc_unread_total(),
		'ai'          => $ai ? array( 'id' => $ai['id'], 'label' => (string) ( $ai['label'] ?? $ai['id'] ), 'auto' => (bool) flxc_setting( 'ai_auto_suggest' ) ) : null,
		'bot'         => function_exists( 'flxc_bot_enabled' ) && flxc_bot_enabled(),
		'records'     => flxc_records_boot(),
		'staff'       => flxc_can_manage() && function_exists( 'flxc_staff_link_users' ) ? flxc_staff_link_users() : array(),
		'rev'         => flxc_rev(),
	);
}

function flxc_rest_chats( WP_REST_Request $req ) {
	$rows = flxc_query_chats(
		array(
			'filter' => sanitize_key( (string) $req->get_param( 'filter' ) ),
			'q'      => sanitize_text_field( (string) $req->get_param( 'q' ) ),
			'label'  => sanitize_key( (string) $req->get_param( 'label' ) ),
			'channel' => sanitize_key( (string) $req->get_param( 'channel' ) ),
			'offset' => max( 0, (int) $req->get_param( 'offset' ) ),
			'limit'  => min( 100, max( 10, (int) ( $req->get_param( 'limit' ) ? $req->get_param( 'limit' ) : 50 ) ) ),
		)
	);
	return array( 'chats' => flxc_chats_for_js( $rows ) );
}

/** Canlı güncelleme: istemci birkaç saniyede bir son gördüğü rev ile sorar. */
function flxc_rest_sync( WP_REST_Request $req ) {
	$now   = flxc_rev();
	$since = (int) $req->get_param( 'since' );
	$chat  = (int) $req->get_param( 'chat' );
	$out   = array(
		'rev'         => $now,
		'chats'       => array(),
		'messages'    => array(),
		'incoming'    => array(),
		'connections' => flxc_connections_for_js(),
		'unread'      => flxc_unread_total(),
	);
	if ( $since > 0 ) {
		$out['chats']    = flxc_chats_for_js( flxc_query_chats( array( 'since' => $since, 'limit' => 200 ) ) );
		$out['incoming'] = flxc_new_incoming_since( $since );
		if ( $chat ) {
			$c = flxc_get_chat( $chat );
			if ( $c && flxc_user_can_see_chat( $c ) ) {
				$out['messages'] = flxc_messages_for_js( flxc_messages_since( $chat, $since, (int) $req->get_param( 'after' ) ) );
			}
		}
	}
	// Oturum yenilendiğinde istemci yeni nonce'u kullansın.
	$out['nonce'] = wp_create_nonce( 'wp_rest' );
	return $out;
}

function flxc_rest_chat( WP_REST_Request $req ) {
	$chat = flxc_rest_chat_or_error( $req['id'] );
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	return array(
		'chat'     => flxc_chats_for_js( array( $chat ) )[0],
		'sections' => flxc_contact_sections( $chat ),
	);
}

/** Kişi panelindeki ek bölümler (Flux Certificates sertifikaları vb.). */
function flxc_contact_sections( $chat ) {
	$sections = array();
	if ( $chat['phone'] && function_exists( 'flxs_find_person_by_phone' ) && function_exists( 'flxs_query_certificates' ) ) {
		$person = flxs_find_person_by_phone( $chat['phone'] );
		if ( $person ) {
			$res   = flxs_query_certificates( array( 'person_id' => (int) $person['id'], 'per_page' => 10 ) );
			$rows  = isset( $res['rows'] ) ? $res['rows'] : ( isset( $res['items'] ) ? $res['items'] : ( is_array( $res ) && isset( $res[0] ) ? $res : array() ) );
			$items = array();
			foreach ( $rows as $c ) {
				$c   = isset( $c['data'] ) && is_array( $c['data'] ) ? $c : flxs_prepare_certificate( $c );
				$url = flxs_certificate_url( $c );
				$items[] = array(
					'label'  => (string) ( $c['title'] ? $c['title'] : $c['number'] ),
					'sub'    => trim( $c['number'] . ' · ' . flx_date( strtotime( $c['created_at'] . ' UTC' ) ), ' ·' ),
					'url'    => $url,
					'insert' => $url,
					'cert'   => array(
						'id'    => (int) $c['id'],
						'name'  => function_exists( 'flxs_certificate_name' ) ? flxs_certificate_name( $c, $person ) : (string) $person['name'],
						'title' => (string) $c['title'],
						'url'   => $url,
					),
				);
			}
			if ( $items ) {
				$sections[] = array(
					'id'    => 'flxs',
					'title' => __( 'Certificates', 'flx-chat' ),
					'items' => $items,
				);
			}
		}
	}
	/**
	 * Kişi paneline bölüm ekleyin: array( id, title, items => array( label, sub, url, insert ) ).
	 * insert: tıklanınca mesaj kutusuna eklenecek metin.
	 */
	return apply_filters( 'flxc_contact_sections', $sections, $chat );
}

function flxc_rest_new_chat( WP_REST_Request $req ) {
	$phone = flxc_normalize_phone( (string) $req->get_param( 'phone' ) );
	if ( ! $phone ) {
		return new WP_Error( 'flxc_phone', __( 'Invalid phone number.', 'flx-chat' ), array( 'status' => 400 ) );
	}
	$conn_id = sanitize_key( (string) $req->get_param( 'conn' ) );
	$conn    = $conn_id ? flxc_get_connection( $conn_id ) : flxc_default_connection();
	if ( ! $conn ) {
		return new WP_Error( 'flxc_conn', __( 'First add a WhatsApp connection under Settings › Connections.', 'flx-chat' ), array( 'status' => 400 ) );
	}
	$jid    = $phone . '@s.whatsapp.net';
	$exists = null;
	if ( $req->get_param( 'check' ) ) {
		$chk = flxc_evo_check_numbers( $conn['id'], array( $phone ) );
		if ( ! is_wp_error( $chk ) && isset( $chk[ $phone ] ) ) {
			$exists = $chk[ $phone ]['exists'];
			if ( ! $exists ) {
				return new WP_Error( 'flxc_no_wa', __( 'This number is not on WhatsApp.', 'flx-chat' ), array( 'status' => 400 ) );
			}
			if ( $chk[ $phone ]['jid'] ) {
				$jid = $chk[ $phone ]['jid'];
			}
		}
	}
	$chat = flxc_ensure_chat( $conn['id'], $jid, array( 'name' => sanitize_text_field( (string) $req->get_param( 'name' ) ) ) );
	$name = sanitize_text_field( (string) $req->get_param( 'name' ) );
	if ( $name && $name !== $chat['name'] ) {
		flxc_update_chat( $chat['id'], array( 'name' => $name ) );
	}
	if ( 'closed' === $chat['status'] ) {
		flxc_update_chat( $chat['id'], array( 'status' => 'open' ) );
	}
	return array( 'chat' => flxc_chats_for_js( array( flxc_get_chat( $chat['id'] ) ) )[0] );
}

function flxc_rest_check_number( WP_REST_Request $req ) {
	$phone = flxc_normalize_phone( (string) $req->get_param( 'phone' ) );
	if ( ! $phone ) {
		return array( 'valid' => false );
	}
	$conn   = flxc_get_connection( sanitize_key( (string) $req->get_param( 'conn' ) ) );
	$conn   = $conn ? $conn : flxc_default_connection();
	$exists = null;
	if ( $conn ) {
		$chk = flxc_evo_check_numbers( $conn['id'], array( $phone ) );
		if ( ! is_wp_error( $chk ) && isset( $chk[ $phone ] ) ) {
			$exists = $chk[ $phone ]['exists'];
		}
	}
	$found = $conn ? flxc_find_chat_by_phone( $conn['id'], $phone ) : null;
	return array(
		'valid'    => true,
		'phone'    => $phone,
		'phoneFmt' => flxc_format_phone( $phone ),
		'exists'   => $exists,
		'chat'     => $found ? (int) $found['id'] : 0,
		'name'     => $found ? flxc_chat_title( $found ) : flxc_lookup_contact_name( $phone ),
	);
}

function flxc_rest_update_chat( WP_REST_Request $req ) {
	$chat = flxc_rest_chat_or_error( $req['id'] );
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	$upd = array();
	if ( null !== $req->get_param( 'name' ) ) {
		$upd['name'] = mb_substr( sanitize_text_field( (string) $req->get_param( 'name' ) ), 0, 190 );
	}
	if ( null !== $req->get_param( 'notes' ) ) {
		$upd['notes'] = sanitize_textarea_field( (string) $req->get_param( 'notes' ) );
	}
	if ( null !== $req->get_param( 'labels' ) ) {
		$valid = wp_list_pluck( flxc_labels_for_js(), 'id' );
		$ls    = array_values( array_intersect( array_map( 'sanitize_key', (array) $req->get_param( 'labels' ) ), $valid ) );
		$upd['labels'] = implode( ',', $ls );
	}
	if ( null !== $req->get_param( 'status' ) ) {
		$st = sanitize_key( (string) $req->get_param( 'status' ) );
		if ( in_array( $st, array( 'open', 'closed' ), true ) ) {
			$upd['status'] = $st;
			if ( 'closed' === $st ) {
				$upd['unread'] = 0;
			}
		}
	}
	if ( null !== $req->get_param( 'assigned' ) ) {
		$a = (int) $req->get_param( 'assigned' );
		if ( 0 === $a || in_array( $a, wp_list_pluck( flxc_agents(), 'id' ), true ) ) {
			$upd['assigned_to'] = $a;
		}
	}
	if ( null !== $req->get_param( 'pinned' ) ) {
		$upd['pinned'] = $req->get_param( 'pinned' ) ? 1 : 0;
	}
	if ( null !== $req->get_param( 'unread' ) ) {
		$upd['unread'] = max( 0, (int) $req->get_param( 'unread' ) );
	}
	// 1.36.0: a manager links a hidden-number chat to a staff user (the assistant then opens staff tools).
	if ( null !== $req->get_param( 'staff_user' ) && function_exists( 'flxc_staff_link_chat' ) ) {
		if ( ! flxc_can_manage() ) {
			return new WP_Error( 'flxc_forbidden', __( 'You do not have permission for this action.', 'flx-chat' ), array( 'status' => 403 ) );
		}
		$r = flxc_staff_link_chat( $chat, (int) $req->get_param( 'staff_user' ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	if ( $upd ) {
		flxc_update_chat( $chat['id'], $upd );
		if ( isset( $upd['assigned_to'] ) && (int) $upd['assigned_to'] !== (int) $chat['assigned_to'] ) {
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
	return array( 'chat' => flxc_chats_for_js( array( flxc_get_chat( $chat['id'] ) ) )[0] );
}

/** Sohbet akışına sistem satırı (atama, kapatma). Son mesaj önizlemesini değiştirmez. */
function flxc_system_message( $chat, $text, array $meta = array() ) {
	global $wpdb;
	$wpdb->insert(
		flxc_table( 'messages' ),
		array(
			'chat_id'    => (int) $chat['id'],
			'conn'       => $chat['conn'],
			'direction'  => 'system',
			'type'       => 'text',
			'body'       => $text,
			'user_id'    => get_current_user_id(),
			'source'     => 'system',
			'ts'         => (int) floor( microtime( true ) * 1000 ),
			'rev'        => flxc_rev(),
			'created_at' => flxc_now(),
			'meta'       => $meta ? flxc_json( $meta ) : '{}',
		)
	);
}

function flxc_rest_messages( WP_REST_Request $req ) {
	$chat = flxc_rest_chat_or_error( $req['id'] );
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	$limit  = 40;
	$before = (int) $req->get_param( 'before' );
	$rows   = flxc_chat_messages( $chat['id'], $before, $limit );
	$hist   = (array) ( flxc_decode( $chat['meta'] )['hist'] ?? array() );
	$note   = '';
	// Yerelde yeterli mesaj yoksa geçmişi Evolution'dan getir (WhatsApp'taki eski mesajlar).
	if ( count( $rows ) < $limit && empty( $hist['done'] ) ) {
		$r = flxc_fetch_chat_history( $chat );
		if ( is_wp_error( $r ) ) {
			$note = $r->get_error_message();
		} elseif ( $r ) {
			$rows = flxc_chat_messages( $chat['id'], $before, $limit );
		}
		$chat = flxc_get_chat( $chat['id'] );
		$hist = (array) ( flxc_decode( $chat['meta'] )['hist'] ?? array() );
	}
	return array(
		'messages' => flxc_messages_for_js( $rows ),
		'more'     => count( $rows ) >= $limit || ( empty( $hist['done'] ) && ! $note && $rows ),
		'note'     => $note,
	);
}

function flxc_rest_send( WP_REST_Request $req ) {
	$chat = flxc_rest_chat_or_error( $req['id'] );
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	$type  = sanitize_key( (string) $req->get_param( 'type' ) );
	$type  = $type ? $type : 'text';
	$body  = (string) $req->get_param( 'body' );
	$body  = str_replace( "\r\n", "\n", wp_check_invalid_utf8( $body ) );
	$args  = array(
		'type'      => $type,
		'body'      => $body,
		'quoted_id' => (int) $req->get_param( 'quoted' ),
		'source'    => in_array( $req->get_param( 'source' ), array( 'quick', 'ai' ), true ) ? $req->get_param( 'source' ) : 'agent',
		'preview'   => false !== $req->get_param( 'preview' ) && '0' !== (string) $req->get_param( 'preview' ),
	);
	$files = $req->get_file_params();
	if ( ! empty( $files['file'] ) && empty( $files['file']['error'] ) ) {
		$f    = $files['file'];
		$max  = (int) apply_filters( 'flxc_max_upload', 16 * MB_IN_BYTES );
		if ( $f['size'] > $max ) {
			/* translators: %s: size */
			return new WP_Error( 'flxc_big', sprintf( __( 'File is too large (max %s).', 'flx-chat' ), size_format( $max ) ), array( 'status' => 400 ) );
		}
		$name = sanitize_file_name( (string) ( $req->get_param( 'name' ) ? $req->get_param( 'name' ) : $f['name'] ) );
		$mime = (string) $f['type'];
		$chk  = wp_check_filetype_and_ext( $f['tmp_name'], $name );
		if ( ! empty( $chk['type'] ) ) {
			$mime = $chk['type'];
		}
		$mime = $mime ? $mime : 'application/octet-stream';
		if ( preg_match( '/\.(php\d?|phtml|phar|js|html?|svg)$/i', $name ) ) {
			return new WP_Error( 'flxc_type', __( 'This file type cannot be sent.', 'flx-chat' ), array( 'status' => 400 ) );
		}
		if ( 'audio' !== $type ) {
			if ( 0 === strpos( $mime, 'image/' ) && 'image/svg+xml' !== $mime ) {
				$type = 'image';
			} elseif ( 0 === strpos( $mime, 'video/' ) ) {
				$type = 'video';
			} else {
				$type = 'document';
			}
		}
		$args['type'] = $type;
		$args['file'] = $f['tmp_name'];
		$args['mime'] = 'audio' === $type ? ( $mime ? $mime : 'audio/ogg' ) : $mime;
		$args['name'] = $name;
	} elseif ( 'text' !== $type && 'note' !== $type ) {
		return new WP_Error( 'flxc_file', __( 'File could not be uploaded.', 'flx-chat' ), array( 'status' => 400 ) );
	}
	if ( in_array( $type, array( 'text', 'note' ), true ) && '' === trim( $body ) ) {
		return new WP_Error( 'flxc_empty', __( 'Message is empty.', 'flx-chat' ), array( 'status' => 400 ) );
	}
	$m = flxc_send( $chat, $args );
	if ( is_wp_error( $m ) ) {
		$m->add_data( array( 'status' => 400 ) );
		return $m;
	}
	return array( 'message' => flxc_messages_for_js( array( $m ) )[0] );
}

function flxc_rest_retry( WP_REST_Request $req ) {
	$m = flxc_get_message( (int) $req['id'] );
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
			'quoted_id' => (int) $m['quoted_id'],
			'mime'      => $m['media_mime'],
			'name'      => $m['media_name'],
			'retry_id'  => (int) $m['id'],
			'user_id'   => get_current_user_id(),
			'preview'   => empty( flxc_decode( $m['meta'] )['nolp'] ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return array( 'message' => flxc_messages_for_js( array( $res ) )[0] );
}

function flxc_rest_react( WP_REST_Request $req ) {
	$m = flxc_get_message( (int) $req['id'] );
	if ( ! $m || '' === $m['wa_id'] ) {
		return new WP_Error( 'flxc_react', __( 'Can\'t react to this message.', 'flx-chat' ), array( 'status' => 400 ) );
	}
	$chat = flxc_rest_chat_or_error( $m['chat_id'] );
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	$emoji = mb_substr( (string) $req->get_param( 'emoji' ), 0, 8 );
	$r     = flxc_evo_send_reaction( $chat['conn'], $chat['jid'], 'out' === $m['direction'], $m['wa_id'], $emoji );
	if ( ! $r['ok'] ) {
		return new WP_Error( 'flxc_react', $r['error'], array( 'status' => 400 ) );
	}
	flxc_apply_reaction( $chat['conn'], $m['wa_id'], 'out', $emoji );
	return array( 'message' => flxc_messages_for_js( array( flxc_get_message( $m['id'] ) ) )[0] );
}

function flxc_rest_delete_note( WP_REST_Request $req ) {
	global $wpdb;
	$m = flxc_get_message( (int) $req['id'] );
	if ( ! $m || 'note' !== $m['direction'] || ( (int) $m['user_id'] !== get_current_user_id() && ! flxc_can_manage() ) ) {
		return new WP_Error( 'flxc_del', __( 'You can only delete your own notes.', 'flx-chat' ), array( 'status' => 403 ) );
	}
	flxc_update_message( $m['id'], array( 'type' => 'deleted', 'body' => '' ) );
	unset( $wpdb );
	return array( 'ok' => true );
}

function flxc_rest_read( WP_REST_Request $req ) {
	global $wpdb;
	$chat = flxc_rest_chat_or_error( $req['id'] );
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	$n = (int) $chat['unread'];
	if ( $n > 0 ) {
		flxc_update_chat( $chat['id'], array( 'unread' => 0 ) );
		if ( flxc_setting( 'send_read' ) ) {
			$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT wa_id FROM ' . flxc_table( 'messages' ) . " WHERE chat_id = %d AND direction = 'in' AND wa_id <> '' ORDER BY ts DESC LIMIT %d", $chat['id'], min( 20, $n ) ) ); // phpcs:ignore
			if ( $ids ) {
				flxc_evo_mark_read( $chat['conn'], $chat['jid'], $ids );
			}
		}
	}
	return array( 'ok' => true );
}

function flxc_rest_typing( WP_REST_Request $req ) {
	$chat = flxc_rest_chat_or_error( $req['id'] );
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	$key = 'flxc_mytyping_' . $chat['id'];
	if ( ! get_transient( $key ) ) {
		set_transient( $key, 1, 4 );
		flxc_evo_presence( $chat['conn'], flxc_chat_number( $chat ), 'recording' === $req->get_param( 'state' ) ? 'recording' : 'composing', 4000 );
	}
	return array( 'ok' => true );
}

function flxc_rest_avatar( WP_REST_Request $req ) {
	$chat = flxc_rest_chat_or_error( $req['id'] );
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	if ( time() - (int) $chat['avatar_at'] > DAY_IN_SECONDS || $req->get_param( 'force' ) ) {
		$url = flxc_group_safe_avatar( $chat );
		flxc_update_chat( $chat['id'], array( 'avatar' => $url, 'avatar_at' => time() ) );
		$chat['avatar'] = $url;
	}
	return array( 'avatar' => $chat['avatar'] );
}

function flxc_group_safe_avatar( $chat ) {
	$url = flxc_evo_profile_picture( $chat['conn'], flxc_chat_number( $chat ) );
	return $url ? esc_url_raw( $url ) : '';
}

function flxc_rest_ai( WP_REST_Request $req ) {
	$chat = flxc_rest_chat_or_error( $req['id'] );
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	$hint = mb_substr( sanitize_text_field( (string) $req->get_param( 'hint' ) ), 0, 300 );
	// Aynı son mesaj için hazırlanan öneri tekrar ücretlendirilmez (sohbeti yeniden açınca).
	global $wpdb;
	$last  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(id) FROM ' . flxc_table( 'messages' ) . ' WHERE chat_id = %d', $chat['id'] ) );
	$ckey  = 'flxc_ais_' . $chat['id'] . '_' . $last;
	$fresh = (bool) $req->get_param( 'fresh' ) || '' !== $hint;
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

/** Medya dosyası: yalnız yetkili temsilciye verilir. Yoksa Evolution'dan indirilip saklanır. */
function flxc_rest_media( WP_REST_Request $req ) {
	$m = flxc_get_message( (int) $req['id'] );
	if ( ! $m ) {
		return new WP_Error( 'flxc_media', 'Not found', array( 'status' => 404 ) );
	}
	$chat = flxc_rest_chat_or_error( $m['chat_id'] );
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	$path = flxc_message_media_path( $m );
	if ( is_wp_error( $path ) ) {
		return $path;
	}
	$m = flxc_get_message( $m['id'] );
	flxc_stream_file( $path, $m['media_mime'], $m['media_name'], (bool) $req->get_param( 'download' ) );
	exit;
}

/** Mesajın medya dosyası; yerelde yoksa Evolution'dan indirilip kaydedilir. @return string|WP_Error */
function flxc_message_media_path( $m ) {
	$dir = flxc_media_dir();
	if ( '' !== $m['media_path'] && is_readable( $dir . '/' . $m['media_path'] ) ) {
		return $dir . '/' . $m['media_path'];
	}
	if ( '' === $m['wa_id'] ) {
		return new WP_Error( 'flxc_media', 'Not found', array( 'status' => 404 ) );
	}
	$r = flxc_evo_media_base64( $m['conn'], $m['wa_id'] );
	if ( is_wp_error( $r ) || '' === $r['base64'] ) {
		return new WP_Error( 'flxc_media', is_wp_error( $r ) ? $r->get_error_message() : 'Empty', array( 'status' => 404 ) );
	}
	$mime  = $m['media_mime'] ? $m['media_mime'] : $r['mimetype'];
	$saved = flxc_save_media_base64( $r['base64'], $mime, $m['media_name'] ? $m['media_name'] : $r['fileName'] );
	if ( ! $saved ) {
		return new WP_Error( 'flxc_media', 'Save failed', array( 'status' => 500 ) );
	}
	flxc_update_message( $m['id'], array( 'media_path' => $saved['path'], 'media_size' => $saved['size'], 'media_mime' => $mime ) );
	return $dir . '/' . $saved['path'];
}

function flxc_stream_file( $path, $mime, $name, $download = false ) {
	$size  = filesize( $path );
	$start = 0;
	$end   = $size - 1;
	$mime  = $mime ? explode( ';', $mime )[0] : 'application/octet-stream';
	// Tarayıcıda çalıştırılabilecek türleri indirmeye zorla.
	if ( preg_match( '#(html|javascript|svg|xml)#i', $mime ) ) {
		$mime     = 'application/octet-stream';
		$download = true;
	}
	while ( ob_get_level() ) {
		ob_end_clean();
	}
	header( 'Content-Type: ' . $mime );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Cache-Control: private, max-age=86400' );
	header( 'Accept-Ranges: bytes' );
	$fname = $name ? $name : basename( $path );
	header( 'Content-Disposition: ' . ( $download ? 'attachment' : 'inline' ) . "; filename*=UTF-8''" . rawurlencode( $fname ) );
	if ( isset( $_SERVER['HTTP_RANGE'] ) && preg_match( '/bytes=(\d*)-(\d*)/', (string) $_SERVER['HTTP_RANGE'], $mm ) ) {
		if ( '' !== $mm[1] ) {
			$start = (int) $mm[1];
			$end   = '' !== $mm[2] ? min( (int) $mm[2], $size - 1 ) : $size - 1;
		} elseif ( '' !== $mm[2] ) {
			$start = max( 0, $size - (int) $mm[2] );
		}
		if ( $start > $end ) {
			status_header( 416 );
			header( 'Content-Range: bytes */' . $size );
			return;
		}
		status_header( 206 );
		header( "Content-Range: bytes $start-$end/$size" );
	} else {
		status_header( 200 );
	}
	header( 'Content-Length: ' . ( $end - $start + 1 ) );
	// phpcs:ignore WordPress.WP.AlternativeFunctions
	$fh = fopen( $path, 'rb' );
	fseek( $fh, $start );
	$left = $end - $start + 1;
	while ( $left > 0 && ! feof( $fh ) ) {
		$chunk = fread( $fh, min( 65536, $left ) ); // phpcs:ignore
		echo $chunk; // phpcs:ignore
		$left -= strlen( $chunk );
	}
	fclose( $fh ); // phpcs:ignore
}

/* ---------- Hazır cevaplar ---------- */

function flxc_rest_replies() {
	return array( 'replies' => flxc_replies_for_js() );
}

function flxc_rest_save_reply( WP_REST_Request $req ) {
	global $wpdb;
	$id    = (int) $req->get_param( 'id' );
	$title = mb_substr( sanitize_text_field( (string) $req->get_param( 'title' ) ), 0, 190 );
	$body  = sanitize_textarea_field( (string) $req->get_param( 'body' ) );
	$short = mb_substr( preg_replace( '/[^\p{L}\p{N}_-]+/u', '', mb_strtolower( (string) $req->get_param( 'shortcut' ) ) ), 0, 60 );
	$cat   = mb_substr( sanitize_text_field( (string) $req->get_param( 'category' ) ), 0, 100 );
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
		$data['sort']       = (int) $wpdb->get_var( 'SELECT COALESCE(MAX(sort),0)+1 FROM ' . flxc_table( 'replies' ) );
		$wpdb->insert( flxc_table( 'replies' ), $data );
	}
	return array( 'replies' => flxc_replies_for_js() );
}

function flxc_rest_delete_reply( WP_REST_Request $req ) {
	global $wpdb;
	$wpdb->delete( flxc_table( 'replies' ), array( 'id' => (int) $req['id'] ) );
	return array( 'replies' => flxc_replies_for_js() );
}

function flxc_rest_use_reply( WP_REST_Request $req ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . flxc_table( 'replies' ) . ' SET uses = uses + 1 WHERE id = %d', (int) $req['id'] ) ); // phpcs:ignore
	$chat_id = (int) $req->get_param( 'chat' );
	$text    = '';
	$row     = $wpdb->get_row( $wpdb->prepare( 'SELECT body FROM ' . flxc_table( 'replies' ) . ' WHERE id = %d', (int) $req['id'] ), ARRAY_A ); // phpcs:ignore
	if ( $row && $chat_id ) {
		$chat = flxc_get_chat( $chat_id );
		if ( $chat && flxc_user_can_see_chat( $chat ) ) {
			$text = flxc_fill_vars( $row['body'], $chat );
		}
	}
	return array( 'text' => $text );
}

/* ---------- Bağlantı yönetimi (ayarlar sayfası) ---------- */

function flxc_rest_conn_or_error( $id ) {
	$c = flxc_get_connection( sanitize_key( $id ) );
	return $c ? $c : new WP_Error( 'flxc_conn', __( 'Connection not found. Save the settings first.', 'flx-chat' ), array( 'status' => 404 ) );
}

function flxc_rest_conn_test( WP_REST_Request $req ) {
	$c = flxc_rest_conn_or_error( $req['conn'] );
	if ( is_wp_error( $c ) ) {
		return $c;
	}
	$st = flxc_evo_connection_state( $c['id'] );
	if ( $st['state'] ) {
		flxc_set_connection_state( $c['id'], $st['state'] );
	}
	$hook = flxc_evo_find_webhook( $c['id'] );
	$url  = (string) ( $hook['json']['url'] ?? ( $hook['json']['webhook']['url'] ?? '' ) );
	$count = $st['ok'] ? flxc_evo_message_count( $c['id'] ) : null;
	return array(
		'stored'      => is_wp_error( $count ) || null === $count ? -1 : (int) $count,
		'ok'          => $st['ok'],
		'state'       => $st['state'],
		'error'       => $st['error'],
		'webhook_ok'  => $url && 0 === strpos( $url, strtok( flxc_webhook_url( $c ), '?' ) ),
		'webhook_url' => $url ? preg_replace( '/([?&]t=)[^&]+/', '$1•••', $url ) : '',
		'version'     => $st['ok'] ? flxc_evo_version( $c['id'], true ) : '',
	);
}

function flxc_rest_conn_webhook( WP_REST_Request $req ) {
	$c = flxc_rest_conn_or_error( $req['conn'] );
	if ( is_wp_error( $c ) ) {
		return $c;
	}
	$r = flxc_evo_set_webhook( $c['id'] );
	return array( 'ok' => $r['ok'], 'error' => $r['error'] );
}

function flxc_rest_conn_qr( WP_REST_Request $req ) {
	$c = flxc_rest_conn_or_error( $req['conn'] );
	if ( is_wp_error( $c ) ) {
		return $c;
	}
	$st = flxc_evo_connection_state( $c['id'] );
	if ( 'open' === $st['state'] ) {
		flxc_set_connection_state( $c['id'], 'open' );
		return array( 'ok' => true, 'state' => 'open' );
	}
	return flxc_evo_connect( $c['id'] );
}

function flxc_rest_conn_import( WP_REST_Request $req ) {
	$c = flxc_rest_conn_or_error( $req['conn'] );
	if ( is_wp_error( $c ) ) {
		return $c;
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore
	}
	$r = flxc_import_history( $c['id'], min( 200, max( 1, (int) ( $req->get_param( 'chats' ) ? $req->get_param( 'chats' ) : 30 ) ) ), 30 );
	if ( is_wp_error( $r ) ) {
		return array( 'ok' => false, 'error' => $r->get_error_message() );
	}
	return array( 'ok' => true ) + $r;
}
