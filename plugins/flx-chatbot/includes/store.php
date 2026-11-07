<?php
/**
 * Sohbet ve mesaj kayıtları.
 *
 * Sohbet durumları: bot (asistan cevaplıyor), queued (temsilci bekleniyor), agent (temsilci bağlandı),
 * offline (mesaj bırakıldı, e-postayla dönülecek), closed (kapandı).
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

function flxcb_token_hash( $token ) {
	return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
}

function flxcb_new_token() {
	return wp_generate_password( 40, false, false );
}

function flxcb_get_conversation( $id ) {
	global $wpdb;
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxcb_table( 'conversations' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return $r ? $r : null;
}

function flxcb_conversation_by_token( $token ) {
	global $wpdb;
	$token = (string) $token;
	if ( strlen( $token ) < 20 || strlen( $token ) > 80 ) {
		return null;
	}
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxcb_table( 'conversations' ) . ' WHERE token = %s', flxcb_token_hash( $token ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return $r ? $r : null;
}

function flxcb_conversation_by_flxc( $chat_id ) {
	global $wpdb;
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxcb_table( 'conversations' ) . ' WHERE flxc_chat = %d ORDER BY id DESC LIMIT 1', (int) $chat_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return $r ? $r : null;
}

/**
 * Yeni sohbet.
 *
 * @return array{0: array, 1: string} sohbet ve ziyaretçi anahtarı
 */
function flxcb_create_conversation( array $data ) {
	global $wpdb;
	$token = flxcb_new_token();
	$row   = array(
		'token'      => flxcb_token_hash( $token ),
		'status'     => 'bot',
		'name'       => mb_substr( (string) ( $data['name'] ?? '' ), 0, 190 ),
		'email'      => mb_substr( (string) ( $data['email'] ?? '' ), 0, 190 ),
		'user_id'    => (int) ( $data['user_id'] ?? 0 ),
		'page_url'   => mb_substr( (string) ( $data['page_url'] ?? '' ), 0, 500 ),
		'page_title' => mb_substr( (string) ( $data['page_title'] ?? '' ), 0, 190 ),
		'locale'     => mb_substr( (string) ( $data['locale'] ?? '' ), 0, 12 ),
		'last_ts'    => flxcb_now_ms(),
		'seen_ts'    => flxcb_now_ms(),
		'created_at' => current_time( 'mysql', true ),
		'meta'       => '{}',
	);
	$wpdb->insert( flxcb_table( 'conversations' ), $row );
	$id = (int) $wpdb->insert_id;
	if ( ! $id ) {
		return array( null, '' );
	}
	$conv = flxcb_get_conversation( $id );
	do_action( 'flxcb_conversation_created', $conv );
	return array( $conv, $token );
}

function flxcb_update_conversation( $id, array $fields ) {
	global $wpdb;
	if ( isset( $fields['meta'] ) && is_array( $fields['meta'] ) ) {
		$fields['meta'] = flxcb_json( (object) $fields['meta'] );
	}
	if ( ! $fields ) {
		return;
	}
	$wpdb->update( flxcb_table( 'conversations' ), $fields, array( 'id' => (int) $id ) );
	if ( array_intersect_key( $fields, array( 'name' => 1, 'email' => 1, 'phone' => 1 ) ) ) {
		do_action( 'flxcb_contact_changed', (int) $id, $fields );
	}
}

function flxcb_conv_meta( $conv, $key = null ) {
	$m = flxcb_decode( $conv['meta'] ?? '' );
	return null === $key ? $m : ( $m[ $key ] ?? null );
}

function flxcb_set_conv_meta( $conv_id, array $values ) {
	$conv = flxcb_get_conversation( $conv_id );
	if ( ! $conv ) {
		return;
	}
	$m = array_merge( flxcb_conv_meta( $conv ), $values );
	flxcb_update_conversation( $conv_id, array( 'meta' => $m ) );
}

/** Sohbeti ve dosyalarını siler. */
function flxcb_delete_conversation( $id ) {
	global $wpdb;
	$dir = flxcb_upload_dir();
	foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT file_path FROM ' . flxcb_table( 'messages' ) . " WHERE conv_id = %d AND file_path <> ''", (int) $id ) ) as $p ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$f = $dir . '/' . basename( (string) $p );
		if ( is_file( $f ) ) {
			wp_delete_file( $f );
		}
	}
	$wpdb->delete( flxcb_table( 'messages' ), array( 'conv_id' => (int) $id ) );
	$wpdb->delete( flxcb_table( 'conversations' ), array( 'id' => (int) $id ) );
	do_action( 'flxcb_conversation_deleted', (int) $id );
}

/**
 * Mesaj ekler.
 *
 * @param array $m conv_id, role (visitor|bot|agent|system), type (text|image|file), body, file_*, user_id, ext_id, meta.
 * @return int mesaj id
 */
function flxcb_add_message( array $m ) {
	global $wpdb;
	$m = wp_parse_args(
		$m,
		array(
			'conv_id'   => 0,
			'role'      => 'visitor',
			'type'      => 'text',
			'body'      => '',
			'file_path' => '',
			'file_name' => '',
			'file_mime' => '',
			'file_size' => 0,
			'user_id'   => 0,
			'ext_id'    => 0,
			'ts'        => flxcb_now_ms(),
			'meta'      => array(),
		)
	);
	$m         = array_intersect_key( $m, array_flip( array( 'conv_id', 'role', 'type', 'body', 'file_path', 'file_name', 'file_mime', 'file_size', 'user_id', 'ext_id', 'ts', 'meta' ) ) );
	$m['meta'] = flxcb_json( (object) $m['meta'] );
	$wpdb->insert( flxcb_table( 'messages' ), $m );
	$id = (int) $wpdb->insert_id;
	if ( ! $id ) {
		return 0;
	}
	$upd = array( 'last_ts' => (int) $m['ts'] );
	if ( 'visitor' === $m['role'] ) {
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . flxcb_table( 'conversations' ) . ' SET unread = unread + 1, last_ts = %d WHERE id = %d', (int) $m['ts'], (int) $m['conv_id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	} else {
		flxcb_update_conversation( (int) $m['conv_id'], $upd );
	}
	$row = flxcb_get_message( $id );
	do_action( 'flxcb_message_added', $row );
	return $id;
}

function flxcb_get_message( $id ) {
	global $wpdb;
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxcb_table( 'messages' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return $r ? $r : null;
}

function flxcb_update_message( $id, array $fields ) {
	global $wpdb;
	if ( isset( $fields['meta'] ) && is_array( $fields['meta'] ) ) {
		$fields['meta'] = flxcb_json( (object) $fields['meta'] );
	}
	$wpdb->update( flxcb_table( 'messages' ), $fields, array( 'id' => (int) $id ) );
}

function flxcb_message_by_ext( $ext_id ) {
	global $wpdb;
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxcb_table( 'messages' ) . ' WHERE ext_id = %d LIMIT 1', (int) $ext_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return $r ? $r : null;
}

/** Sohbet mesajları (id sırasıyla). $after verilirse yalnız sonrakiler. */
function flxcb_messages( $conv_id, $after = 0, $limit = 200 ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxcb_table( 'messages' ) . ' WHERE conv_id = %d AND id > %d ORDER BY id DESC LIMIT %d', (int) $conv_id, (int) $after, (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return array_reverse( (array) $rows );
}

function flxcb_last_message( $conv_id ) {
	global $wpdb;
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxcb_table( 'messages' ) . " WHERE conv_id = %d AND role <> 'system' ORDER BY id DESC LIMIT 1", (int) $conv_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return $r ? $r : null;
}

/** Ziyaretçiye gösterilecek mesaj. */
function flxcb_message_for_js( array $m, $conv, $token = '' ) {
	$meta = flxcb_decode( $m['meta'] );
	$out  = array(
		'id'   => (int) $m['id'],
		'role' => $m['role'],
		'type' => $m['type'],
		'body' => (string) $m['body'],
		'ts'   => (int) $m['ts'],
	);
	if ( 'agent' === $m['role'] && $m['user_id'] ) {
		$out['author'] = flxcb_user_name( (int) $m['user_id'] );
		$out['avatar'] = get_avatar_url( (int) $m['user_id'], array( 'size' => 64 ) );
	}
	if ( '' !== $m['file_path'] ) {
		$out['file'] = array(
			'name' => (string) $m['file_name'],
			'mime' => (string) $m['file_mime'],
			'size' => (int) $m['file_size'],
			'url'  => add_query_arg( array( 't' => $token ), rest_url( 'flx-chatbot/v1/file/' . (int) $m['id'] ) ),
		);
	}
	if ( ! empty( $meta['cards'] ) && is_array( $meta['cards'] ) ) {
		$out['cards'] = $meta['cards'];
	}
	if ( ! empty( $meta['event'] ) ) {
		$out['event'] = (string) $meta['event'];
	}
	if ( 'visitor' === $m['role'] && $conv ) {
		$out['read'] = (int) $m['id'] <= (int) $conv['agent_read'] ? 1 : 0;
	}
	return $out;
}

function flxcb_messages_for_js( array $rows, $conv, $token = '' ) {
	return array_values(
		array_map(
			function ( $m ) use ( $conv, $token ) {
				return flxcb_message_for_js( $m, $conv, $token );
			},
			$rows
		)
	);
}

/** Ziyaretçiye gösterilecek sohbet durumu. */
function flxcb_conv_for_js( $conv ) {
	if ( ! $conv ) {
		return null;
	}
	$agent = null;
	if ( $conv['assigned_to'] && in_array( $conv['status'], array( 'agent', 'closed' ), true ) ) {
		$agent = array(
			'name'   => flxcb_user_name( (int) $conv['assigned_to'] ),
			'avatar' => get_avatar_url( (int) $conv['assigned_to'], array( 'size' => 64 ) ),
		);
	}
	return array(
		'status'   => $conv['status'],
		'name'     => (string) $conv['name'],
		'email'    => '' !== (string) $conv['email'] ? 1 : 0,
		'rating'   => (int) $conv['rating'],
		'agent'    => $agent,
		'queue'    => 'queued' === $conv['status'] ? flxcb_queue_position( $conv ) : 0,
		'typing'   => get_transient( 'flxcb_typing_a_' . (int) $conv['id'] ) ? 1 : 0,
		'readUpTo' => (int) $conv['agent_read'],
	);
}

/** Sıradaki yer (1 = ilk). */
function flxcb_queue_position( $conv ) {
	global $wpdb;
	$n = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxcb_table( 'conversations' ) . " WHERE status = 'queued' AND queued_ts > 0 AND queued_ts < %d", (int) $conv['queued_ts'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return $n + 1;
}

/** Yönetim listesi. */
function flxcb_query_conversations( array $args = array() ) {
	global $wpdb;
	$args  = wp_parse_args( $args, array( 'status' => '', 'q' => '', 'limit' => 50, 'offset' => 0 ) );
	$where = array( '1=1' );
	$prm   = array();
	if ( 'open' === $args['status'] ) {
		$where[] = "status IN ('queued','agent','offline')";
	} elseif ( '' !== $args['status'] ) {
		$where[] = 'status = %s';
		$prm[]   = $args['status'];
	}
	if ( '' !== $args['q'] ) {
		$like    = '%' . $wpdb->esc_like( $args['q'] ) . '%';
		$where[] = '(name LIKE %s OR email LIKE %s OR phone LIKE %s OR page_title LIKE %s)';
		array_push( $prm, $like, $like, $like, $like );
	}
	$sql   = 'SELECT * FROM ' . flxcb_table( 'conversations' ) . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY last_ts DESC LIMIT %d OFFSET %d';
	$prm[] = (int) $args['limit'];
	$prm[] = (int) $args['offset'];
	return (array) $wpdb->get_results( $wpdb->prepare( $sql, $prm ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/** Sayaçlar: son N gün. */
function flxcb_stats( $days = 30 ) {
	global $wpdb;
	$since = current_time( 'mysql', true );
	$since = gmdate( 'Y-m-d H:i:s', strtotime( $since . ' UTC' ) - $days * DAY_IN_SECONDS );
	$t     = flxcb_table( 'conversations' );
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT COUNT(*) AS total,
				SUM(flxc_chat > 0 OR assigned_to > 0 OR status IN ('queued','agent','offline')) AS human,
				SUM(lead > 0) AS leads,
				SUM(order_id > 0) AS orders,
				SUM(rating > 0) AS rated,
				AVG(NULLIF(rating,0)) AS rating
			FROM $t WHERE created_at >= %s",
			$since
		),
		ARRAY_A
	);
	$open = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status IN ('queued','agent','offline')" );
	// phpcs:enable
	$total = (int) ( $row['total'] ?? 0 );
	$human = (int) ( $row['human'] ?? 0 );
	return array(
		'total'    => $total,
		'human'    => $human,
		'resolved' => $total ? (int) round( 100 * ( $total - $human ) / $total ) : 0,
		'leads'    => (int) ( $row['leads'] ?? 0 ),
		'orders'   => (int) ( $row['orders'] ?? 0 ),
		'rating'   => $row['rating'] ? round( (float) $row['rating'], 1 ) : 0,
		'rated'    => (int) ( $row['rated'] ?? 0 ),
		'open'     => $open,
	);
}

/** Ziyaretçi adı (boşsa "Ziyaretçi #id"). */
function flxcb_conv_title( $conv ) {
	if ( '' !== (string) $conv['name'] ) {
		return $conv['name'];
	}
	if ( '' !== (string) $conv['email'] ) {
		return $conv['email'];
	}
	/* translators: %d: conversation number */
	return sprintf( __( 'Ziyaretçi #%d', 'flx-chatbot' ), (int) $conv['id'] );
}

/** Sistem mesajı (ziyaretçiye görünen olay satırı). */
function flxcb_event( $conv_id, $event, $text ) {
	return flxcb_add_message(
		array(
			'conv_id' => (int) $conv_id,
			'role'    => 'system',
			'body'    => $text,
			'meta'    => array( 'event' => $event ),
		)
	);
}
