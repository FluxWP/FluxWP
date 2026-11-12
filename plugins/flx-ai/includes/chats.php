<?php
/**
 * Ortak sohbetler: her kullanıcı yalnız kendi sohbetlerini görür.
 *
 * Mesajlar OpenAI biçiminde saklanır; Flux AI alanları "_" ile başlar
 * (_id, _ts, _page, _thinking, _raw, _raw_model, _usage, _ms, _ok, _name, _args, _error, _rejected).
 * state: pending (onay bekleyen araç çağrı kimlikleri), queue (sıradaki okuma araçları), turn (tur ölçümleri).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

function flxai_chat_decode( $row ) {
	if ( ! $row ) {
		return null;
	}
	$row             = (array) $row;
	$row['id']       = (int) $row['id'];
	$row['user_id']  = (int) $row['user_id'];
	$row['archived'] = (int) $row['archived'];
	$row['created']  = (int) $row['created'];
	$row['updated']  = (int) $row['updated'];
	$m               = json_decode( (string) $row['messages'], true );
	$s               = json_decode( (string) $row['state'], true );
	$row['messages'] = is_array( $m ) ? $m : array();
	$row['state']    = is_array( $s ) ? $s : array();
	$row['state']   += array(
		'pending' => array(),
		'queue'   => array(),
		'turn'    => array(),
	);
	return $row;
}

/** Sohbet (yalnız sahibi). */
function flxai_chat_get( $id, $user_id = 0 ) {
	global $wpdb;
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	$row     = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxai_table( 'chats' ) . ' WHERE id = %d AND user_id = %d', (int) $id, $user_id ) ); // phpcs:ignore
	return flxai_chat_decode( $row );
}

function flxai_chat_create( $user_id = 0 ) {
	global $wpdb;
	$now = time();
	$wpdb->insert( // phpcs:ignore
		flxai_table( 'chats' ),
		array(
			'user_id'  => $user_id ? (int) $user_id : get_current_user_id(),
			'title'    => '',
			'archived' => 0,
			'status'   => 'idle',
			'model'    => '',
			'created'  => $now,
			'updated'  => $now,
			'messages' => '[]',
			'state'    => '{}',
		)
	);
	return flxai_chat_get( (int) $wpdb->insert_id, $user_id );
}

function flxai_chat_save( array $chat ) {
	global $wpdb;
	$wpdb->update( // phpcs:ignore
		flxai_table( 'chats' ),
		array(
			'title'    => mb_substr( (string) $chat['title'], 0, 190 ),
			'archived' => (int) $chat['archived'],
			'status'   => (string) $chat['status'],
			'model'    => (string) $chat['model'],
			'updated'  => time(),
			'messages' => wp_json_encode( array_values( $chat['messages'] ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'state'    => wp_json_encode( $chat['state'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
		),
		array( 'id' => (int) $chat['id'] )
	);
}

function flxai_chat_delete( $id, $user_id = 0 ) {
	global $wpdb;
	$chat = flxai_chat_get( $id, $user_id );
	if ( $chat && function_exists( 'flxai_attach_delete_for_chat' ) ) {
		flxai_attach_delete_for_chat( $chat );
	}
	return (bool) $wpdb->delete( flxai_table( 'chats' ), array( 'id' => (int) $id, 'user_id' => $user_id ? (int) $user_id : get_current_user_id() ) ); // phpcs:ignore
}

/**
 * Sohbet listesi: arama başlıkta ve içerikte.
 *
 * @param array $args q, archived (0|1|-1 hepsi), limit, offset
 */
function flxai_chat_list( array $args = array(), $user_id = 0 ) {
	global $wpdb;
	$user_id  = $user_id ? (int) $user_id : get_current_user_id();
	$q        = trim( (string) ( $args['q'] ?? '' ) );
	$archived = (int) ( $args['archived'] ?? 0 );
	$limit    = max( 1, min( 100, (int) ( $args['limit'] ?? 50 ) ) );
	$offset   = max( 0, (int) ( $args['offset'] ?? 0 ) );
	$where    = $wpdb->prepare( 'user_id = %d', $user_id );
	if ( $archived >= 0 ) {
		$where .= $wpdb->prepare( ' AND archived = %d', $archived ? 1 : 0 );
	}
	if ( '' !== $q ) {
		$like   = '%' . $wpdb->esc_like( $q ) . '%';
		$where .= $wpdb->prepare( ' AND (title LIKE %s OR messages LIKE %s)', $like, $like );
	}
	$rows = $wpdb->get_results( 'SELECT id, title, archived, status, updated, ' . ( '' !== $q ? 'messages' : "'' AS messages" ) . ' FROM ' . flxai_table( 'chats' ) . " WHERE {$where} ORDER BY updated DESC LIMIT {$limit} OFFSET {$offset}", ARRAY_A ); // phpcs:ignore
	$out  = array();
	foreach ( (array) $rows as $r ) {
		$item = array(
			'id'       => (int) $r['id'],
			'title'    => (string) $r['title'],
			'archived' => (int) $r['archived'],
			'status'   => (string) $r['status'],
			'updated'  => (int) $r['updated'],
			'snippet'  => '',
		);
		if ( '' !== $q && false === mb_stripos( $item['title'], $q ) ) {
			$item['snippet'] = flxai_chat_snippet( (string) $r['messages'], $q );
		}
		$out[] = $item;
	}
	return $out;
}

/** Aranan kelimenin geçtiği kısa parça (kullanıcı ve asistan metinlerinde). */
function flxai_chat_snippet( $json, $q ) {
	$msgs = json_decode( $json, true );
	foreach ( is_array( $msgs ) ? $msgs : array() as $m ) {
		if ( ! in_array( $m['role'] ?? '', array( 'user', 'assistant' ), true ) || ! is_string( $m['content'] ?? null ) ) {
			continue;
		}
		$pos = mb_stripos( $m['content'], $q );
		if ( false !== $pos ) {
			$start = max( 0, $pos - 40 );
			return ( $start ? '…' : '' ) . trim( mb_substr( $m['content'], $start, 120 ) ) . '…';
		}
	}
	return '';
}

/** İstemciye giden sohbet: ham Anthropic blokları çıkarılır. */
function flxai_chat_public( array $chat, $since = 0 ) {
	$msgs = array();
	foreach ( array_slice( $chat['messages'], max( 0, (int) $since ) ) as $m ) {
		unset( $m['_raw'], $m['_raw_model'] );
		$msgs[] = $m;
	}
	$pending = array();
	if ( $chat['state']['pending'] ) {
		$tools = flxai_tools_all();
		foreach ( flxai_chat_tool_calls( $chat, $chat['state']['pending'] ) as $c ) {
			$name      = (string) $c['function']['name'];
			$args      = json_decode( (string) $c['function']['arguments'], true );
			$pending[] = array(
				'id'     => (string) $c['id'],
				'name'   => $name,
				'title'  => (string) ( $tools[ $name ]['title'] ?? $name ),
				'source' => flxai_source_label( (string) ( $tools[ $name ]['source'] ?? '' ) ),
				'args'   => is_array( $args ) ? $args : new stdClass(),
			);
		}
	}
	return array(
		'id'       => (int) $chat['id'],
		'title'    => (string) $chat['title'],
		'archived' => (int) $chat['archived'],
		'status'   => (string) $chat['status'],
		'updated'  => (int) $chat['updated'],
		'model'    => (string) $chat['model'],
		'since'    => (int) $since,
		'total'    => count( $chat['messages'] ),
		'messages' => $msgs,
		'pending'  => $pending,
		'turn'     => $chat['state']['turn'],
	);
}

/** Son asistan mesajındaki araç çağrılarından verilen kimliklere ait olanlar. */
function flxai_chat_tool_calls( array $chat, array $ids ) {
	$out = array();
	for ( $i = count( $chat['messages'] ) - 1; $i >= 0; $i-- ) {
		$m = $chat['messages'][ $i ];
		if ( 'assistant' === ( $m['role'] ?? '' ) && ! empty( $m['tool_calls'] ) ) {
			foreach ( $m['tool_calls'] as $c ) {
				if ( in_array( (string) $c['id'], $ids, true ) ) {
					$out[] = $c;
				}
			}
			break;
		}
	}
	return $out;
}

/** Kullanıcının bir sohbet için kalan sohbet sayısı vb. için kısa özet (dock rozetinde). */
function flxai_chat_counts( $user_id = 0 ) {
	global $wpdb;
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	return array(
		'approval' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxai_table( 'chats' ) . " WHERE user_id = %d AND status = 'approval'", $user_id ) ), // phpcs:ignore
	);
}
