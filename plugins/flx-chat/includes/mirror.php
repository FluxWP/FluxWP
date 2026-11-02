<?php
/**
 * Ekip grubu: kişilerden gelen mesajlar ve verilen cevaplar (asistan, ekip, otomatik) panelde seçilen
 * WhatsApp grubuna düzenli biçimde akar. Aynı kişinin son mesajı gruptaki en son mesajsa ve hâlâ
 * düzenlenebiliyorsa (WhatsApp 15 dk) yeni satır ona eklenir; değilse yeni mesaj gider. Metin kodla
 * hazırlanır, yapay zekâ kullanılmaz. Devir bildirimi de grup seçiliyse buraya, numaralar etiketlenerek gider.
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

function flxc_mirror_group() {
	$g = trim( (string) flxc_setting( 'mirror_group' ) );
	return '@g.us' === substr( $g, -5 ) ? $g : '';
}

/**
 * Gruba yazacak hat (1.34.0): resmi Cloud API grup mesajı göndermez; sohbet öyle bir hattaysa
 * ilk Evolution / Flux WPAPI hattı kullanılır. Hiç yoksa ''.
 */
function flxc_mirror_conn( $conn ) {
	if ( '' !== (string) $conn && ! flxc_conn_wacloud( $conn ) ) {
		return (string) $conn;
	}
	foreach ( flxc_connections() as $c ) {
		if ( ! flxc_conn_wacloud( $c ) ) {
			return $c['id'];
		}
	}
	return '';
}

function flxc_mirror_on() {
	return '' !== flxc_mirror_group() && (bool) flxc_setting( 'mirror_enabled' );
}

/** Bu sohbet gruba yansıtılır mı? */
function flxc_mirror_chat_ok( $chat ) {
	if ( ! $chat || flxc_is_group_jid( $chat['jid'] ) || '@broadcast' === substr( (string) $chat['jid'], -10 ) ) {
		return false;
	}
	return ! flxc_is_notify_number( $chat['phone'] ?? '' ) && (bool) apply_filters( 'flxc_mirror_chat', true, $chat );
}

/** Grupta görünen satır. */
function flxc_mirror_line( $m ) {
	$text = 'text' === $m['type'] ? trim( (string) $m['body'] ) : '[' . flxc_preview_text( $m ) . ']' . ( '' !== trim( (string) $m['body'] ) && $m['body'] !== flxc_preview_text( $m ) ? ' ' . trim( (string) $m['body'] ) : '' );
	if ( '' === $text ) {
		return '';
	}
	if ( 'in' === $m['direction'] ) {
		$who = __( 'Müşteri', 'flx-chat' );
	} elseif ( 'bot' === $m['source'] ) {
		$who = __( 'AI Chat', 'flx-chat' );
	} elseif ( (int) $m['user_id'] ) {
		/* translators: %s: agent name */
		$who = sprintf( __( 'Ekip (%s)', 'flx-chat' ), flxc_user_name( (int) $m['user_id'] ) );
	} elseif ( 'phone' === $m['source'] ) {
		$who = __( 'Ekip (telefon)', 'flx-chat' );
	} else {
		$who = __( 'Otomatik', 'flx-chat' );
	}
	if ( mb_strlen( $text ) > 1200 ) {
		$text = mb_substr( $text, 0, 1200 ) . '…';
	}
	return '*' . $who . ':* ' . $text;
}

function flxc_mirror_head( $chat ) {
	$name  = flxc_chat_title( $chat );
	$phone = '' !== (string) $chat['phone'] ? '+' . $chat['phone'] : __( 'Gizli numara (WhatsApp)', 'flx-chat' );
	$head  = '💬 *' . $name . '*' . ( $name !== $phone && false === strpos( $name, (string) $chat['phone'] ) ? ' · ' . $phone : '' );
	return (string) apply_filters( 'flxc_mirror_head', $head . "\n" . admin_url( 'admin.php?page=flx-chat&chat=' . (int) $chat['id'] ), $chat );
}

/** İstek sonunda topluca gönderilecek mesajlar (webhook beklemesin). */
function flxc_mirror_queue( $message_id, $chat ) {
	static $q = null;
	if ( ! flxc_mirror_on() || ! flxc_mirror_chat_ok( $chat ) ) {
		return;
	}
	if ( null === $q ) {
		$q = array();
		register_shutdown_function(
			function () use ( &$q ) {
				if ( function_exists( 'fastcgi_finish_request' ) ) {
					fastcgi_finish_request();
				} elseif ( function_exists( 'litespeed_finish_request' ) ) {
					litespeed_finish_request();
				}
				ignore_user_abort( true );
				flxc_mirror_flush( $q );
				$q = array();
			}
		);
	}
	$q[] = (int) $message_id;
}

add_action( 'flxc_message_received', 'flxc_mirror_queue', 30, 2 );
add_action( 'flxc_message_sent', 'flxc_mirror_queue', 30, 2 );
add_action( 'flxc_message_from_phone', 'flxc_mirror_queue', 30, 2 );

/** Mesajları sohbet sırasıyla gruba yazar. */
function flxc_mirror_flush( array $ids ) {
	// Aynı anda iki istek aynı grup mesajını düzenlemesin.
	for ( $i = 0; $i < 20 && ! add_option( 'flxc_mirror_lock', time(), '', 'no' ); $i++ ) {
		if ( time() - (int) get_option( 'flxc_mirror_lock' ) > 30 ) {
			delete_option( 'flxc_mirror_lock' );
		}
		usleep( 250000 );
	}
	try {
		$by = array();
		foreach ( array_unique( $ids ) as $id ) {
			$m = flxc_get_message( $id );
			if ( $m && 'reaction' !== $m['type'] ) {
				$by[ (int) $m['chat_id'] ][] = $m;
			}
		}
		foreach ( $by as $chat_id => $list ) {
			$chat  = flxc_get_chat( $chat_id );
			$lines = array_filter( array_map( 'flxc_mirror_line', $list ) );
			if ( $chat && $lines ) {
				flxc_mirror_write( $chat, array_values( $lines ) );
			}
		}
	} finally {
		delete_option( 'flxc_mirror_lock' );
	}
}

function flxc_mirror_write( $chat, array $lines ) {
	$group = flxc_mirror_group();
	$conn  = flxc_mirror_conn( $chat['conn'] );
	if ( '' === $conn ) {
		return false;
	}
	$last  = get_option( 'flxc_mirror_last', array() );
	$last  = is_array( $last ) ? $last : array();
	$text  = implode( "\n\n", $lines );
	// Gruptaki son mesaj bu kişinin ve 12 dk içinde gönderildiyse düzenlenir.
	if ( ! empty( $last['wa'] ) && (int) ( $last['chat'] ?? 0 ) === (int) $chat['id'] && time() - (int) $last['at'] < 12 * MINUTE_IN_SECONDS && mb_strlen( $last['text'] . $text ) < 3500 ) {
		$new = $last['text'] . "\n\n" . $text;
		flxc_notify_remember( $last['wa'], $new );
		$r = flxc_evo(
			$conn,
			'POST',
			'/chat/updateMessage/{instance}',
			array(
				'number' => $group,
				'key'    => array( 'remoteJid' => $group, 'fromMe' => true, 'id' => $last['wa'] ),
				'text'   => $new,
			),
			15
		);
		if ( ! empty( $r['ok'] ) ) {
			$last['text'] = $new;
			update_option( 'flxc_mirror_last', $last, false );
			return true;
		}
	}
	// 1.26.0: çok uzun metin parçalara bölünür, her parça yeni mesaj (WhatsApp'ta uzun mesaj "devamını oku"ya düşer).
	$head   = flxc_mirror_head( $chat );
	$chunks = flxc_mirror_chunks( $text, 3500 - mb_strlen( $head ) );
	foreach ( $chunks as $ci => $chunk ) {
		$body = $head . ( $ci ? ' (' . ( $ci + 1 ) . '/' . count( $chunks ) . ')' : '' ) . "\n\n" . $chunk;
		flxc_notify_remember( '', $body );
		$r = flxc_evo_send_text( $conn, $group, $body, null, false );
		if ( empty( $r['ok'] ) ) {
			update_option( 'flxc_mirror_error', array( 'at' => time(), 'error' => '' !== (string) $r['error'] ? $r['error'] : 'HTTP ' . $r['code'] ), false );
			return false;
		}
		if ( $ci < count( $chunks ) - 1 ) {
			flxc_notify_remember( (string) ( $r['json']['key']['id'] ?? '' ), $body );
		}
	}
	$wa = (string) ( $r['json']['key']['id'] ?? '' );
	flxc_notify_remember( $wa, $body );
	update_option(
		'flxc_mirror_last',
		array(
			'wa'   => $wa,
			'chat' => (int) $chat['id'],
			'at'   => time(),
			'text' => $body,
		),
		false
	);
	delete_option( 'flxc_mirror_error' );
	return true;
}

/** Metni satır sınırından en fazla $max karakterlik parçalara böler. */
function flxc_mirror_chunks( $text, $max ) {
	$max = max( 500, (int) $max );
	$out = array();
	$cur = '';
	foreach ( preg_split( '/\n/u', (string) $text ) as $line ) {
		while ( mb_strlen( $line ) > $max ) {
			if ( '' !== $cur ) {
				$out[] = $cur;
				$cur   = '';
			}
			$out[] = mb_substr( $line, 0, $max );
			$line  = mb_substr( $line, $max );
		}
		if ( '' !== $cur && mb_strlen( $cur ) + 1 + mb_strlen( $line ) > $max ) {
			$out[] = $cur;
			$cur   = $line;
		} else {
			$cur = '' === $cur ? $line : $cur . "\n" . $line;
		}
	}
	if ( '' !== trim( $cur ) ) {
		$out[] = $cur;
	}
	return $out ? $out : array( '' );
}

/** Grup listesi (Evolution'dan, 10 dk önbellek; olmazsa paneldeki grup sohbetleri). */
function flxc_mirror_groups( $fresh = false ) {
	$c = get_transient( 'flxc_mirror_groups' );
	if ( is_array( $c ) && ! $fresh ) {
		return $c;
	}
	$out  = array();
	$conn = flxc_mirror_conn( '' );
	if ( $conn ) {
		$r = flxc_evo( $conn, 'GET', '/group/fetchAllGroups/{instance}?getParticipants=false', null, 12 );
		foreach ( (array) ( $r['ok'] ? $r['json'] : array() ) as $g ) {
			if ( is_array( $g ) && ! empty( $g['id'] ) ) {
				$out[ (string) $g['id'] ] = (string) ( $g['subject'] ?? $g['id'] );
			}
		}
	}
	global $wpdb;
	foreach ( (array) $wpdb->get_results( 'SELECT jid, name, push_name FROM ' . flxc_table( 'chats' ) . " WHERE jid LIKE '%@g.us' ORDER BY last_ts DESC LIMIT 100", ARRAY_A ) as $g ) { // phpcs:ignore
		if ( ! isset( $out[ $g['jid'] ] ) ) {
			$out[ $g['jid'] ] = '' !== (string) $g['name'] ? $g['name'] : ( '' !== (string) $g['push_name'] ? $g['push_name'] : $g['jid'] );
		}
	}
	asort( $out );
	set_transient( 'flxc_mirror_groups', $out, 10 * MINUTE_IN_SECONDS );
	return $out;
}

/** Gruba etiketli metin (devir bildirimi). */
function flxc_mirror_send_mentions( $conn, $text, array $numbers ) {
	$group = flxc_mirror_group();
	$conn  = flxc_mirror_conn( is_array( $conn ) ? $conn['id'] : (string) $conn );
	if ( $numbers ) {
		$text .= "\n\n" . implode(
			' ',
			array_map(
				function ( $n ) {
					return '@' . $n;
				},
				$numbers
			)
		);
	}
	flxc_notify_remember( '', $text );
	$body = array(
		'number'      => $group,
		'text'        => $text,
		'linkPreview' => false,
	);
	if ( $numbers ) {
		// 1.26.0: boş dizi Evolution'da 400 verir ("mentioned does not meet minimum length of 1").
		$body['mentioned'] = array_values( $numbers );
	}
	$r = flxc_evo( $conn, 'POST', '/message/sendText/{instance}', $body );
	if ( ! empty( $r['ok'] ) ) {
		flxc_notify_remember( (string) ( $r['json']['key']['id'] ?? '' ), $text );
		// Sonraki konuşma satırı bu bildirimi düzenlemesin.
		delete_option( 'flxc_mirror_last' );
	}
	return $r;
}
