<?php
/**
 * Asistan sohbeti ekibe devredince panelde girilen numaralara WhatsApp bildirimi:
 * kişinin adı (biliniyorsa), telefonu, devir nedeni, son mesajları ve panel linki.
 * Bildirimin kendi yankısı yeni sohbet açmaz; bu numaralarla asistan konuşmaz.
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

/** Bildirim numaraları (yalnız rakamlar, ülke koduyla). */
function flxc_notify_numbers() {
	$out = array();
	foreach ( preg_split( '/[\s,;]+/', (string) flxc_setting( 'bot_notify_numbers' ) ) as $n ) {
		$n = flxc_normalize_phone( $n );
		if ( $n ) {
			$out[] = $n;
		}
	}
	return array_values( array_unique( $out ) );
}

function flxc_is_notify_number( $phone ) {
	return '' !== (string) $phone && in_array( (string) $phone, flxc_notify_numbers(), true );
}

/** Kişinin son gelen mesajları (en çok 3, son 24 saat), eskiden yeniye. */
function flxc_notify_last_messages( $chat_id ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT type, body, media_name, ts FROM ' . flxc_table( 'messages' ) . " WHERE chat_id = %d AND direction = 'in' AND type NOT IN ('reaction','deleted') AND ts >= %d ORDER BY ts DESC, id DESC LIMIT 3", $chat_id, ( time() - DAY_IN_SECONDS ) * 1000 ), ARRAY_A ); // phpcs:ignore
	$out  = array();
	foreach ( array_reverse( (array) $rows ) as $m ) {
		$b = trim( (string) $m['body'] );
		if ( 'text' !== $m['type'] ) {
			$b = '[' . ( '' !== (string) $m['media_name'] ? $m['media_name'] : $m['type'] ) . ']' . ( '' !== $b ? ' ' . $b : '' );
		}
		if ( '' !== $b ) {
			$out[] = mb_strlen( $b ) > 300 ? mb_substr( $b, 0, 300 ) . '…' : $b;
		}
	}
	return $out;
}

function flxc_notify_text( $chat, $reason ) {
	$state = function_exists( 'flxc_bot_state' ) ? flxc_bot_state( $chat ) : array();
	$name  = '' !== (string) ( $state['full'] ?? '' ) ? $state['full'] : ( '' !== (string) ( $state['name'] ?? '' ) ? $state['name'] : ( '' !== (string) $chat['name'] ? $chat['name'] : (string) $chat['push_name'] ) );
	$why   = 'human' === $reason ? __( 'Kişi temsilci istedi', 'flx-chat' ) : ( '' !== (string) $reason ? $reason : '-' );
	$lines = array( '🔔 *' . __( 'Asistan bir sohbeti ekibe devretti', 'flx-chat' ) . '*' );
	if ( '' !== trim( $name ) ) {
		$lines[] = __( 'Kişi', 'flx-chat' ) . ': ' . $name;
	}
	$lines[] = __( 'Telefon', 'flx-chat' ) . ': ' . ( '' !== (string) $chat['phone'] ? '+' . $chat['phone'] : __( 'Gizli numara (WhatsApp)', 'flx-chat' ) );
	$lines[] = __( 'Neden', 'flx-chat' ) . ': ' . $why;
	$msgs    = flxc_notify_last_messages( $chat['id'] );
	if ( $msgs ) {
		$lines[] = '';
		$lines[] = __( 'Son mesajlar', 'flx-chat' ) . ':';
		foreach ( $msgs as $m ) {
			$lines[] = '› ' . str_replace( "\n", ' ', $m );
		}
	}
	$lines[] = '';
	$lines[] = __( 'Lütfen sohbeti devralın', 'flx-chat' ) . ': ' . admin_url( 'admin.php?page=flx-chat&chat=' . (int) $chat['id'] );
	return (string) apply_filters( 'flxc_notify_text', implode( "\n", $lines ), $chat, $reason );
}

/** Gönderilen bildirimler: yankısı sohbet olarak kaydedilmesin (wa id ve metin özeti, 10 dk). */
function flxc_notify_remember( $wa_id, $text ) {
	$l   = get_option( 'flxc_notify_sent', array() );
	$l   = is_array( $l ) ? $l : array();
	$now = time();
	$l   = array_values(
		array_filter(
			$l,
			function ( $x ) use ( $now ) {
				return $now - (int) $x[2] < 600;
			}
		)
	);
	$l[] = array( (string) $wa_id, md5( trim( (string) $text ) ), $now );
	update_option( 'flxc_notify_sent', array_slice( $l, -100 ), false );
}

function flxc_notify_is_echo( $wa_id, $body ) {
	$l = get_option( 'flxc_notify_sent', array() );
	if ( ! is_array( $l ) || ! $l ) {
		return false;
	}
	$h = md5( trim( (string) $body ) );
	foreach ( $l as $x ) {
		if ( ( '' !== (string) $wa_id && (string) $x[0] === (string) $wa_id ) || $x[1] === $h ) {
			return true;
		}
	}
	return false;
}

function flxc_notify_handoff( $chat, $reason ) {
	$nums  = array_diff( flxc_notify_numbers(), array( (string) $chat['phone'] ) );
	$group = function_exists( 'flxc_mirror_group' ) && flxc_setting( 'notify_to_group' ) ? flxc_mirror_group() : '';
	if ( ( ! $nums && '' === $group ) || empty( $chat['id'] ) ) {
		return;
	}
	// Aynı sohbet için 5 dakikada bir bildirim yeter.
	$lock = 'flxc_notify_' . (int) $chat['id'];
	if ( get_transient( $lock ) ) {
		return;
	}
	set_transient( $lock, 1, 5 * MINUTE_IN_SECONDS );
	$fresh = flxc_get_chat( $chat['id'] );
	$chat  = $fresh ? $fresh : $chat;
	$text  = flxc_notify_text( $chat, $reason );
	$ok    = array();
	$bad   = array();
	if ( '' !== $group ) {
		// Ekip grubu seçiliyse bildirim oraya gider, numaralar etiketlenir.
		$r = flxc_mirror_send_mentions( $chat['conn'], $text, array_values( $nums ) );
		if ( empty( $r['ok'] ) ) {
			/* translators: %s: error */
			flxc_system_message( $chat, sprintf( __( 'Ekip grubuna bildirim gönderilemedi: %s', 'flx-chat' ), '' !== (string) $r['error'] ? $r['error'] : 'HTTP ' . $r['code'] ) );
		} else {
			flxc_system_message( $chat, __( 'Ekip grubuna devir bildirimi gönderildi.', 'flx-chat' ) );
		}
		return;
	}
	foreach ( $nums as $n ) {
		flxc_notify_remember( '', $text );
		$r = flxc_evo_send_text( $chat['conn'], $n, $text, null, false );
		if ( empty( $r['ok'] ) ) {
			$bad[] = '+' . $n . ' (' . ( '' !== (string) $r['error'] ? $r['error'] : 'HTTP ' . $r['code'] ) . ')';
			continue;
		}
		flxc_notify_remember( (string) ( $r['json']['key']['id'] ?? '' ), $text );
		$ok[] = '+' . $n;
	}
	if ( $ok ) {
		/* translators: %s: phone numbers */
		flxc_system_message( $chat, sprintf( __( 'Ekibe WhatsApp bildirimi gönderildi: %s', 'flx-chat' ), implode( ', ', $ok ) ) );
	}
	if ( $bad ) {
		/* translators: %s: phone numbers and errors */
		flxc_system_message( $chat, sprintf( __( 'Ekip bildirimi gönderilemedi: %s', 'flx-chat' ), implode( ', ', $bad ) ) );
	}
}
add_action( 'flxc_bot_handoff', 'flxc_notify_handoff', 20, 2 );
