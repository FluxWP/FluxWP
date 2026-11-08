<?php
/**
 * Flux App yerel ekranı (1.33.0): WhatsApp menüsü uygulamada kendi ekranıyla açılır.
 * Sözleşme: docs/fluxapp/MODUL-API.md › 1. WhatsApp. Uçlar flx-chat/v1/app/*,
 * yetki flxc_can_use (Flux App belirteci ya da çerez + nonce), sohbet görme kuralı panel ile aynı.
 */
defined( 'ABSPATH' ) || exit;

add_filter(
	'flxapp_modules',
	function ( $m ) {
		$m['flxc-inbox'] = array(
			'title'   => __( 'WhatsApp', 'flx-chat' ),
			'cap'     => 'flxc_use',
			'menu'    => 'flx-chat',
			'api'     => rest_url( 'flx-chat/v1/app' ),
			'icon'    => 'chat',
			'version' => FLXC_VERSION,
			'order'   => 20,
		);
		return $m;
	}
);

add_action( 'rest_api_init', 'flxc_app_register_rest' );

function flxc_app_register_rest() {
	$ns  = 'flx-chat/v1';
	$use = function () {
		return flxc_can_use();
	};
	$routes = array(
		array( '/app/chats', 'GET', 'flxc_app_rest_chats', $use ),
		array( '/app/chats/(?P<id>\d+)', 'GET', 'flxc_app_rest_chat', $use ),
		array( '/app/chats/(?P<id>\d+)/messages', 'GET', 'flxc_app_rest_messages', $use ),
		array( '/app/chats/(?P<id>\d+)/send', 'POST', 'flxc_app_rest_send', $use ),
		array( '/app/chats/(?P<id>\d+)/read', 'POST', 'flxc_app_rest_read', $use ),
		array( '/app/chats/(?P<id>\d+)/bot', 'POST', 'flxc_app_rest_bot', $use ),
		// Medya <img>/<audio> ile açılır, başlık gönderemez: imzalı ve süreli adres.
		array( '/app/media/(?P<id>\d+)', 'GET', 'flxc_app_rest_media', '__return_true' ),
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

/* ---------- Biçimler ---------- */

/** ms zaman damgası → array( ISO 8601, site saat diliminde gösterim ). */
function flxc_app_time( $ms ) {
	$ts = (int) floor( (int) $ms / 1000 );
	if ( $ts <= 0 ) {
		return array( '', '' );
	}
	$today = wp_date( 'Y-m-d' ) === wp_date( 'Y-m-d', $ts );
	return array( wp_date( 'c', $ts ), $today ? flx_date( $ts, 'time' ) : flx_date( $ts, 'datetime' ) );
}

/** Mesajı kim yazdı: customer | bot | staff. */
function flxc_app_by( $m ) {
	if ( 'in' === $m['direction'] ) {
		return 'customer';
	}
	return in_array( $m['source'], array( 'bot', 'auto' ), true ) ? 'bot' : 'staff';
}

function flxc_app_labels() {
	static $map = null;
	if ( null === $map ) {
		$map = array();
		foreach ( flxc_labels_for_js() as $l ) {
			$map[ $l['id'] ] = $l['name'];
		}
	}
	return $map;
}

function flxc_app_chats( array $rows ) {
	global $wpdb;
	$ids = array();
	foreach ( $rows as $r ) {
		if ( $r['last_msg_id'] ) {
			$ids[] = (int) $r['last_msg_id'];
		}
	}
	$last = array();
	if ( $ids ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $wpdb->get_results( 'SELECT * FROM ' . flxc_table( 'messages' ) . ' WHERE id IN (' . implode( ',', $ids ) . ')', ARRAY_A ) as $m ) {
			$last[ (int) $m['id'] ] = $m;
		}
	}
	$out = array();
	foreach ( $rows as $r ) {
		$out[] = flxc_app_chat( $r, $last[ (int) $r['last_msg_id'] ] ?? null );
	}
	return $out;
}

function flxc_app_chat( $r, $last = null ) {
	$labels = flxc_app_labels();
	$tags   = array();
	foreach ( '' === (string) $r['labels'] ? array() : explode( ',', $r['labels'] ) as $l ) {
		if ( isset( $labels[ $l ] ) ) {
			$tags[] = $labels[ $l ];
		}
	}
	$at  = $last ? flxc_app_time( $last['ts'] ) : flxc_app_time( $r['last_ts'] );
	$bot = function_exists( 'flxc_bot_chat_status' ) ? flxc_bot_chat_status( $r ) : '';
	return array(
		'id'       => (int) $r['id'],
		'name'     => flxc_chat_title( $r ),
		'phone'    => '' === (string) $r['phone'] && '@lid' === substr( (string) $r['jid'], -4 ) ? '' : flxc_format_phone( $r['phone'] ),
		'avatar'   => (string) $r['avatar'],
		'unread'   => (int) $r['unread'],
		'last'     => $last ? array(
			'text'     => flxc_preview_text( $last ),
			'at'       => $at[0],
			'at_label' => $at[1],
			'dir'      => 'in' === $last['direction'] ? 'in' : 'out',
			'by'       => flxc_app_by( $last ),
		) : null,
		'bot'      => array(
			'on'        => 'on' === $bot,
			'available' => '' !== $bot,
		),
		'tags'     => $tags,
		'assigned' => (int) $r['assigned_to'] ? array( 'id' => (int) $r['assigned_to'], 'name' => flxc_user_name( $r['assigned_to'] ) ) : null,
		'blocked'  => function_exists( 'flxc_chat_blocked' ) && flxc_chat_blocked( $r ),
		'closed'   => 'closed' === $r['status'],
	);
}

function flxc_app_message( $r, $chat ) {
	$meta = flxc_decode( $r['meta'] );
	if ( 'deleted' === $r['type'] && ! empty( $meta['deleted_type'] ) && 'deleted' !== $meta['deleted_type'] ) {
		$r['type'] = (string) $meta['deleted_type'];
		$r['body'] = (string) ( $meta['deleted_body'] ?? '' );
	}
	$by    = flxc_app_by( $r );
	$at    = flxc_app_time( $r['ts'] );
	$media = null;
	$types = array( 'image' => 'image', 'sticker' => 'image', 'audio' => 'audio', 'video' => 'video', 'document' => 'document' );
	if ( isset( $types[ $r['type'] ] ) ) {
		$media = array(
			'type' => $types[ $r['type'] ],
			'url'  => flxc_app_media_url( $r['id'] ),
			'name' => (string) $r['media_name'],
			'mime' => (string) $r['media_mime'],
		);
	}
	$text = in_array( $r['type'], array( 'text', 'image', 'video', 'document', 'audio' ), true ) ? (string) $r['body'] : flxc_preview_text( $r );
	return array(
		'id'       => (int) $r['id'],
		'dir'      => 'in' === $r['direction'] ? 'in' : 'out',
		'by'       => $by,
		'author'   => 'customer' === $by ? flxc_chat_title( $chat ) : ( 'bot' === $by ? __( 'Assistant', 'flx-chat' ) : flxc_user_name( $r['user_id'] ) ),
		'text'     => $text,
		'media'    => $media,
		'at'       => $at[0],
		'at_label' => $at[1],
		'status'   => in_array( $r['status'], array( 'pending', 'sent', 'delivered', 'read', 'failed' ), true ) ? $r['status'] : ( 'in' === $r['direction'] ? 'read' : 'sent' ),
	);
}

/* ---------- Medya: imzalı adres ---------- */

function flxc_app_media_sig( $id, $exp, $uid ) {
	return hash_hmac( 'sha256', (int) $id . '|' . (int) $exp . '|' . (int) $uid, wp_salt( 'auth' ) . 'flxc-app-media' );
}

function flxc_app_media_url( $id ) {
	$uid = get_current_user_id();
	$exp = ( (int) floor( time() / HOUR_IN_SECONDS ) + 2 ) * HOUR_IN_SECONDS; // 1-2 saat; aynı saatte aynı adres (önbellek)
	return add_query_arg(
		array(
			'u'   => $uid,
			'e'   => $exp,
			's'   => flxc_app_media_sig( $id, $exp, $uid ),
		),
		rest_url( 'flx-chat/v1/app/media/' . (int) $id )
	);
}

function flxc_app_rest_media( WP_REST_Request $req ) {
	$id  = (int) $req['id'];
	$uid = (int) $req->get_param( 'u' );
	$exp = (int) $req->get_param( 'e' );
	$sig = (string) $req->get_param( 's' );
	if ( ! $uid || $exp < time() || ! hash_equals( flxc_app_media_sig( $id, $exp, $uid ), $sig ) ) {
		return new WP_Error( 'flxc_media', __( 'This link has expired.', 'flx-chat' ), array( 'status' => 403 ) );
	}
	wp_set_current_user( $uid );
	if ( ! flxc_can_use() ) {
		return new WP_Error( 'flxc_media', __( 'You do not have permission for this action.', 'flx-chat' ), array( 'status' => 403 ) );
	}
	return flxc_rest_media( $req );
}

/* ---------- Uçlar ---------- */

function flxc_app_rest_chats( WP_REST_Request $req ) {
	$map    = array( 'open' => 'all', 'mine' => 'mine', 'all' => 'any', 'unread' => 'unread' );
	$filter = $map[ sanitize_key( (string) $req->get_param( 'filter' ) ) ] ?? 'all';
	$per    = min( 50, max( 1, (int) ( $req->get_param( 'per_page' ) ? $req->get_param( 'per_page' ) : 30 ) ) );
	$page   = max( 1, (int) $req->get_param( 'page' ) );
	$args   = array(
		'filter' => $filter,
		'q'      => sanitize_text_field( (string) $req->get_param( 'q' ) ),
		'limit'  => $per,
		'offset' => ( $page - 1 ) * $per,
	);
	$total  = flxc_query_chats( $args + array( 'count' => true ) );
	return array(
		'items' => flxc_app_chats( flxc_query_chats( $args ) ),
		'total' => $total,
		'page'  => $page,
		'pages' => max( 1, (int) ceil( $total / $per ) ),
	);
}

function flxc_app_rest_chat( WP_REST_Request $req ) {
	$chat = flxc_rest_chat_or_error( $req['id'] );
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	$out          = flxc_app_chats( array( $chat ) )[0];
	$out['notes'] = (string) $chat['notes'];
	$out['donor'] = null;
	if ( '' !== (string) $chat['phone'] && function_exists( 'flxc_records_for_phone' ) ) {
		$rec = flxc_records_for_phone( $chat['phone'] );
		if ( $rec['donor'] ) {
			$out['donor'] = array(
				'name'         => (string) $rec['donor']['name'],
				'email'        => (string) $rec['donor']['email'],
				'donations'    => count( $rec['donations'] ),
				'certificates' => count( $rec['certificates'] ),
			);
		}
	}
	return $out;
}

function flxc_app_rest_messages( WP_REST_Request $req ) {
	global $wpdb;
	$chat = flxc_rest_chat_or_error( $req['id'] );
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	$after = (int) $req->get_param( 'after' );
	$limit = min( 100, max( 1, (int) ( $req->get_param( 'limit' ) ? $req->get_param( 'limit' ) : 40 ) ) );
	if ( $after ) {
		// Poll: son görülen id'den sonrakiler (yalnız bir sorgu, hafif).
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'messages' ) . " WHERE chat_id = %d AND id > %d AND direction IN ('in','out') AND type <> 'reaction' ORDER BY ts ASC, id ASC LIMIT 200", $chat['id'], $after ), ARRAY_A ); // phpcs:ignore
		$more = false;
	} else {
		$rows = flxc_chat_messages( $chat['id'], (int) $req->get_param( 'before' ), $limit );
		$more = count( $rows ) >= $limit;
		$rows = array_values(
			array_filter(
				$rows,
				function ( $m ) {
					return in_array( $m['direction'], array( 'in', 'out' ), true );
				}
			)
		);
	}
	$items = array();
	foreach ( $rows as $m ) {
		$items[] = flxc_app_message( $m, $chat );
	}
	return array(
		'items'    => $items,
		'has_more' => $more,
	);
}

function flxc_app_rest_send( WP_REST_Request $req ) {
	$chat = flxc_rest_chat_or_error( $req['id'] );
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	$text = str_replace( "\r\n", "\n", wp_check_invalid_utf8( (string) $req->get_param( 'text' ) ) );
	if ( '' === trim( $text ) ) {
		return new WP_Error( 'flxc_empty', __( 'Message is empty.', 'flx-chat' ), array( 'status' => 400 ) );
	}
	$m = flxc_send(
		$chat,
		array(
			'type'   => 'text',
			'body'   => $text,
			'source' => 'agent',
		)
	);
	if ( is_wp_error( $m ) ) {
		$m->add_data( array( 'status' => 400 ) );
		return $m;
	}
	return flxc_app_message( $m, $chat );
}

function flxc_app_rest_read( WP_REST_Request $req ) {
	return flxc_rest_read( $req );
}

function flxc_app_rest_bot( WP_REST_Request $req ) {
	$chat = flxc_rest_chat_or_error( $req['id'] );
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	if ( ! function_exists( 'flxc_bot_enabled' ) || ! flxc_bot_enabled() ) {
		return new WP_Error( 'flxc_bot_off', __( 'The assistant is off.', 'flx-chat' ), array( 'status' => 400 ) );
	}
	// Paneldeki aç/kapat ile aynı iş (bot.php).
	$sub = new WP_REST_Request( 'POST', '/flx-chat/v1/chats/' . (int) $chat['id'] . '/bot' );
	$sub->set_param( 'state', rest_sanitize_boolean( $req->get_param( 'on' ) ) ? 'on' : 'off' );
	$res = rest_do_request( $sub );
	if ( $res->is_error() ) {
		return $res->as_error();
	}
	return flxc_app_chats( array( flxc_get_chat( $chat['id'] ) ) )[0];
}
