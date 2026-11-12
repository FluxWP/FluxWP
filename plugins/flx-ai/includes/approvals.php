<?php
/**
 * Onay kuyruğu (0.12.0): kullanıcının tüm sohbetlerinde (görev sohbetleri dahil) onay bekleyen işlemler tek listede.
 * Her işlem tek tek onaylanır, reddedilir ya da girdisi düzenlenip onaylanır. Sohbetin son bekleyen işlemi
 * karara bağlanınca model kaldığı yerden devam eder (bir adım bu istekte, kalan sohbet açılınca).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** Onay bekleyen işlem sayısı (sohbet başına değil, işlem başına). */
function flxai_approvals_count( $user_id = 0 ) {
	global $wpdb;
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	$states  = $wpdb->get_col( $wpdb->prepare( 'SELECT state FROM ' . flxai_table( 'chats' ) . " WHERE user_id = %d AND status = 'approval'", $user_id ) ); // phpcs:ignore
	$n       = 0;
	foreach ( (array) $states as $s ) {
		$s  = json_decode( (string) $s, true );
		$n += count( (array) ( $s['pending'] ?? array() ) );
	}
	// Çalışma alanı: açık değişiklik istekleri (PR) de onay bekler.
	if ( function_exists( 'flxai_changes_open_count' ) ) {
		$n += flxai_changes_open_count( $user_id );
	}
	return $n;
}

/**
 * Onay bekleyen işlemler (en yeni üstte).
 *
 * @return array[] chat_id, chat_title, call_id, tool, title, args, note (modelin açıklaması), ts, task (görev ya da null).
 */
function flxai_approvals_list( $user_id = 0 ) {
	global $wpdb;
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	$ids     = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxai_table( 'chats' ) . " WHERE user_id = %d AND status = 'approval' ORDER BY updated DESC LIMIT 100", $user_id ) ); // phpcs:ignore
	$tools   = flxai_tools_for_user( $user_id );
	$out     = array();
	foreach ( (array) $ids as $cid ) {
		$chat = flxai_chat_get( (int) $cid, $user_id );
		if ( ! $chat || ! $chat['state']['pending'] ) {
			continue;
		}
		$task  = flxai_task_by_chat( $chat['id'] );
		$notes = array();
		$ts    = array();
		foreach ( $chat['messages'] as $m ) {
			if ( 'assistant' === ( $m['role'] ?? '' ) && ! empty( $m['tool_calls'] ) ) {
				foreach ( $m['tool_calls'] as $c ) {
					$notes[ (string) $c['id'] ] = trim( (string) ( $m['content'] ?? '' ) );
					$ts[ (string) $c['id'] ]    = (int) ( $m['_ts'] ?? $chat['updated'] );
				}
			}
		}
		foreach ( flxai_chat_tool_calls( $chat, $chat['state']['pending'] ) as $c ) {
			$name  = (string) $c['function']['name'];
			$args  = json_decode( (string) $c['function']['arguments'], true );
			$out[] = array(
				'chat_id'    => (int) $chat['id'],
				'chat_title' => (string) $chat['title'],
				'call_id'    => (string) $c['id'],
				'tool'       => $name,
				'title'      => (string) ( $tools[ $name ]['title'] ?? $name ),
				'args'       => is_array( $args ) ? $args : array(),
				'note'       => $notes[ (string) $c['id'] ] ?? '',
				'ts'         => $ts[ (string) $c['id'] ] ?? (int) $chat['updated'],
				'task'       => $task,
			);
		}
	}
	usort( $out, fn( $a, $b ) => $b['ts'] <=> $a['ts'] );
	return $out;
}

/**
 * Tek bir işlemi karara bağlar. $args verilirse işlem bu girdiyle çalışır (düzenleyip onayla).
 * Sohbette başka bekleyen işlem kalmadıysa model bir adım devam eder.
 *
 * @return array|WP_Error state (approval|continue|done|error|busy), chat.
 */
function flxai_approval_decide( $chat_id, $call_id, $approve, $args = null, $user_id = 0 ) {
	$chat = flxai_chat_get( (int) $chat_id, $user_id );
	if ( ! $chat || ! in_array( (string) $call_id, (array) $chat['state']['pending'], true ) ) {
		return new WP_Error( 'flxai_not_found', __( 'This action is no longer waiting for approval.', 'flx-ai' ), array( 'status' => 404 ) );
	}
	if ( null !== $args ) {
		foreach ( $chat['messages'] as $i => $m ) {
			foreach ( (array) ( $m['tool_calls'] ?? array() ) as $j => $c ) {
				if ( (string) $c['id'] === (string) $call_id ) {
					$chat['messages'][ $i ]['tool_calls'][ $j ]['function']['arguments'] = wp_json_encode( $args ? $args : new stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
					$chat['messages'][ $i ]['_edited']                                   = 1;
				}
			}
		}
	}
	$rest = array_values( array_diff( (array) $chat['state']['pending'], array( (string) $call_id ) ) );
	if ( ! $rest ) {
		flxai_chat_save( $chat );
		$r = flxai_agent_decide( $chat, array( (string) $call_id => $approve ? 'approve' : 'reject' ) );
		if ( 'continue' === $r['state'] ) {
			$r = flxai_agent_step( $r['chat'] );
		}
		return $r;
	}
	if ( ! flxai_agent_lock( $chat['id'] ) ) {
		return array( 'state' => 'busy', 'chat' => $chat );
	}
	$calls = flxai_chat_tool_calls( $chat, array( (string) $call_id ) );
	if ( $calls ) {
		flxai_agent_decide_call( $chat, $calls[0], (bool) $approve );
	}
	$chat['state']['pending'] = $rest;
	flxai_chat_save( $chat );
	flxai_agent_unlock( $chat['id'] );
	return array( 'state' => 'approval', 'chat' => $chat );
}

/* Görev sohbetinde onay verildi: görevin "onay bekliyor" durumu kalkar. */
add_action(
	'flxai_agent_decided',
	function ( $chat ) {
		$task = flxai_task_by_chat( (int) $chat['id'] );
		if ( $task && 'approval' === $task['last_state'] ) {
			flxai_task_patch( $task['id'], array( 'last_state' => 'ok' ) );
		}
	}
);

/* Görevin değişiklik isteği karara bağlandı: başka açık istek kalmadıysa "onay bekliyor" kalkar. */
add_action(
	'flxai_change_decided',
	function ( $change ) {
		$task = ! empty( $change['chat_id'] ) ? flxai_task_by_chat( (int) $change['chat_id'] ) : null;
		if ( $task && 'approval' === $task['last_state'] && ! flxai_change_open_pr( (int) $change['chat_id'] ) ) {
			$chat = flxai_chat_get( (int) $change['chat_id'], (int) $task['user_id'] );
			if ( ! $chat || 'approval' !== $chat['status'] ) {
				flxai_task_patch( $task['id'], array( 'last_state' => 'ok' ) );
			}
		}
	}
);
