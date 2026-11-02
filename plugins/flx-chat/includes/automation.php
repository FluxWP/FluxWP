<?php
defined( 'ABSPATH' ) || exit;

/*
 * Otomatik yanıtlar: ilk mesajda karşılama ve mesai dışı yanıtı. Yapay zekâ bağlantı noktası.
 */

add_action( 'flxc_message_received', 'flxc_auto_replies', 20, 2 );
function flxc_auto_replies( $message_id, $chat ) {
	if ( flxc_is_group_jid( $chat['jid'] ) ) {
		return;
	}
	$now      = time();
	$is_new   = 0 === (int) $chat['last_ts'];
	$throttle = max( 1, (int) flxc_setting( 'away_throttle' ) ) * HOUR_IN_SECONDS;
	$text     = '';

	if ( flxc_setting( 'away_enabled' ) && ! flxc_is_open_hours() && ( $now - (int) $chat['last_auto_ts'] ) > $throttle ) {
		$text = (string) flxc_setting( 'away_text' );
	} elseif ( $is_new && flxc_setting( 'welcome_enabled' ) ) {
		$text = (string) flxc_setting( 'welcome_text' );
	}
	$text = (string) apply_filters( 'flxc_auto_reply_text', $text, $chat, $message_id );
	if ( '' === trim( $text ) ) {
		return;
	}
	$fresh = flxc_get_chat( $chat['id'] );
	flxc_update_chat( $chat['id'], array( 'last_auto_ts' => $now ) );
	flxc_send(
		$fresh ? $fresh : $chat,
		array(
			'type'    => 'text',
			'body'    => flxc_fill_vars( $text, $fresh ? $fresh : $chat, 0 ),
			'source'  => 'auto',
			'user_id' => 0,
		)
	);
}

/** Şu an mesai saati mi? (Site saat dilimine göre) */
function flxc_is_open_hours( $ts = null ) {
	$tz   = wp_timezone();
	$dt   = new DateTime( '@' . ( $ts ? $ts : time() ) );
	$dt->setTimezone( $tz );
	$day  = (int) $dt->format( 'N' ); // 1 = Pazartesi
	$days = array_map( 'intval', (array) flxc_setting( 'away_days' ) );
	if ( ! in_array( $day, $days, true ) ) {
		return false;
	}
	$hm    = $dt->format( 'H:i' );
	$start = (string) flxc_setting( 'away_start' );
	$end   = (string) flxc_setting( 'away_end' );
	if ( $start <= $end ) {
		return $hm >= $start && $hm < $end;
	}
	return $hm >= $start || $hm < $end; // gece vardiyası
}

/* ---------- Yapay zekâ ---------- */

/**
 * Kayıtlı yapay zekâ sağlayıcıları. Bir eklenti şöyle ekler:
 *
 * add_filter( 'flxc_ai_providers', function ( $list ) {
 *     $list['benim-ai'] = array(
 *         'label'   => 'Benim AI',
 *         'suggest' => function ( array $chat, array $messages, array $context ) {
 *             return 'Önerilen cevap metni'; // ya da WP_Error
 *         },
 *     );
 *     return $list;
 * } );
 *
 * $messages: son mesajlar (eskiden yeniye) — dir (in|out|note), type, body, ts.
 * $context: replies (hazır cevaplar), agent, brand, locale.
 */
function flxc_ai_providers() {
	$list = apply_filters( 'flxc_ai_providers', array() );
	return is_array( $list ) ? $list : array();
}

function flxc_ai_active() {
	$list = flxc_ai_providers();
	if ( ! $list ) {
		return null;
	}
	$id = (string) flxc_setting( 'ai_provider' );
	if ( $id && isset( $list[ $id ] ) ) {
		return array( 'id' => $id ) + $list[ $id ];
	}
	$first = array_key_first( $list );
	return array( 'id' => $first ) + $list[ $first ];
}

/** @return string|WP_Error */
function flxc_ai_suggest( $chat, $hint = '' ) {
	$p = flxc_ai_active();
	if ( ! $p || empty( $p['suggest'] ) || ! is_callable( $p['suggest'] ) ) {
		return new WP_Error( 'flxc_ai', __( 'Yapay zekâ sağlayıcısı tanımlı değil.', 'flx-chat' ) );
	}
	$msgs = array();
	foreach ( flxc_chat_messages( $chat['id'], 0, 30 ) as $m ) {
		$msgs[] = array(
			'dir'  => $m['direction'],
			'type' => $m['type'],
			'body' => flxc_preview_text( $m ),
			'ts'   => (int) $m['ts'],
		);
	}
	global $wpdb;
	$replies = $wpdb->get_results( 'SELECT shortcut, title, body FROM ' . flxc_table( 'replies' ) . ' ORDER BY sort, id', ARRAY_A );
	$ctx     = array(
		'replies' => $replies,
		'agent'   => flxc_user_name( get_current_user_id() ),
		'brand'   => flxc_brand_name(),
		'locale'  => get_user_locale(),
		'hint'    => (string) $hint,
	);
	try {
		$r = call_user_func( $p['suggest'], $chat, $msgs, $ctx );
	} catch ( Throwable $e ) {
		return new WP_Error( 'flxc_ai', $e->getMessage() );
	}
	return is_wp_error( $r ) ? $r : (string) $r;
}
