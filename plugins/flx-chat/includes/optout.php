<?php
/**
 * 1.44.0: Flux Mail IPTAL (tanıtım mesajlarından çıkış) ile uyum.
 *
 * Flux Mail (1.17.0+) flxc_message_received'i öncelik 5'te dinler; mesajın TAMAMI IPTAL / BAŞLA ise kişiyi
 * listeye yazar ve onay mesajını kendisi gönderir. Flux Chat burada:
 * - Bu mesaja asistan, karşılama ve mesai dışı yanıtı ayrıca cevap yazmaz.
 * - Flux Mail'in onay mesajı telefondan yazılmış sayılıp asistanı susturmaz.
 * - IPTAL yazmış kişiye hatırlatıcı (asistanın kendiliğinden başlattığı mesaj) gitmez; kişi yazarsa cevap verilir.
 * - Kişi bilgisinde "Tanıtım mesajları kapalı" görünür, asistan bunu bilir.
 * Flux Mail kurulu değilse (flxi_optout_match yok) hiçbiri çalışmaz, her şey eskisi gibi.
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

/** Flux Mail IPTAL desteği var mı? */
function flxc_optout_on() {
	return function_exists( 'flxi_optout_match' ) && function_exists( 'flxi_optout_has' );
}

/**
 * Metin bir IPTAL / BAŞLA komutu mu?
 *
 * @return string 'out' | 'in' | ''
 */
function flxc_optout_command( $text ) {
	if ( ! flxc_optout_on() ) {
		return '';
	}
	return (string) flxi_optout_match( (string) $text );
}

/** Bu sohbetteki kişi WhatsApp tanıtım mesajlarından çıkmış mı? (grup: hayır) */
function flxc_optout_chat( $chat ) {
	if ( ! flxc_optout_on() || ! is_array( $chat ) || '' === (string) ( $chat['phone'] ?? '' ) || flxc_is_group_jid( (string) ( $chat['jid'] ?? '' ) ) ) {
		return false;
	}
	return (bool) flxi_optout_has( 'whatsapp', (string) $chat['phone'] );
}

/**
 * $after_id'den sonra gelen mesajların hepsi IPTAL / BAŞLA komutu mu? (Asistan bu turda cevap yazmaz.)
 * Komutun yanında başka bir soru da yazıldıysa false: asistan soruya cevap verir.
 */
function flxc_optout_only_commands( $chat_id, $after_id ) {
	if ( ! flxc_optout_on() ) {
		return false;
	}
	global $wpdb;
	$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT type, body FROM ' . flxc_table( 'messages' ) . " WHERE chat_id = %d AND direction = 'in' AND id > %d ORDER BY id ASC LIMIT 6", (int) $chat_id, (int) $after_id ), ARRAY_A ); // phpcs:ignore
	if ( ! $rows || count( $rows ) > 5 ) {
		return false;
	}
	foreach ( $rows as $m ) {
		if ( 'text' !== $m['type'] || '' === flxc_optout_command( (string) $m['body'] ) ) {
			return false;
		}
	}
	return true;
}

/* Karşılama / mesai dışı yanıtı IPTAL / BAŞLA mesajına gitmez (onayı Flux Mail gönderir). */
add_filter(
	'flxc_auto_reply_text',
	function ( $text, $chat, $message_id = 0 ) {
		if ( '' === trim( (string) $text ) || ! $message_id || ! flxc_optout_on() ) {
			return $text;
		}
		$m = flxc_get_message( (int) $message_id );
		return ( $m && 'text' === ( $m['type'] ?? 'text' ) && '' !== flxc_optout_command( (string) $m['body'] ) ) ? '' : $text;
	},
	20,
	3
);

/*
 * Komut gelince kısa bir süre (2 dk) Flux Mail'in onay mesajı beklenir. Evolution'ın giden mesaj bildirimi
 * Flux Mail mesaj kimliğini kaydetmeden önce gelirse mesaj "telefondan" görünür; metin onay metniyle aynıysa
 * asistan susturulmaz.
 */
add_action(
	'flxc_message_received',
	function ( $id, $chat ) {
		if ( ! flxc_optout_on() || ! is_array( $chat ) || empty( $chat['id'] ) ) {
			return;
		}
		$m = flxc_get_message( (int) $id );
		if ( ! $m || 'text' !== ( $m['type'] ?? 'text' ) ) {
			return;
		}
		$dir = flxc_optout_command( (string) $m['body'] );
		if ( '' !== $dir ) {
			set_transient( 'flxc_optout_wait_' . (int) $chat['id'], $dir, 2 * MINUTE_IN_SECONDS );
		}
	},
	4,
	2
);

/** Telefondan görünen bu giden mesaj aslında Flux Mail'in IPTAL / BAŞLA onayı mı? */
function flxc_optout_is_confirmation( $message_id, $chat ) {
	if ( ! flxc_optout_on() || ! function_exists( 'flxi_optout_reply_text' ) || ! is_array( $chat ) || empty( $chat['id'] ) ) {
		return false;
	}
	$dir = (string) get_transient( 'flxc_optout_wait_' . (int) $chat['id'] );
	if ( '' === $dir ) {
		return false;
	}
	$m     = flxc_get_message( (int) $message_id );
	$reply = trim( (string) flxi_optout_reply_text( $dir ) );
	if ( ! $m || '' === $reply || trim( (string) $m['body'] ) !== $reply ) {
		return false;
	}
	delete_transient( 'flxc_optout_wait_' . (int) $chat['id'] );
	return true;
}

/* Flux Mail gönderimleri (kampanya, IPTAL onayı) temsilci mesajı değildir: asistanı susturmaz. */
add_filter(
	'flxc_bot_pause_sources',
	function ( $list ) {
		return array_values( array_diff( (array) $list, array( 'flx-mail', 'flx-mail-optout' ) ) );
	},
	99
);
add_filter(
	'flxc_bot_auto_sources',
	function ( $list ) {
		return array_merge( (array) $list, array( 'flx-mail', 'flx-mail-optout' ) );
	},
	99
);

/* Kişi bilgisi: tanıtım mesajları kapalıysa görünür. */
add_filter(
	'flxc_contact_sections',
	function ( $sections, $chat ) {
		if ( ! flxc_optout_chat( $chat ) ) {
			return $sections;
		}
		$url = function_exists( 'flxi_admin_url' ) ? flxi_admin_url( 'flxi-ayarlar' ) . '#iptal' : '';
		array_unshift(
			$sections,
			array(
				'id'    => 'optout',
				'title' => __( 'Promotional messages', 'flx-chat' ),
				'items' => array(
					array(
						'label' => __( 'Off (the person replied STOP)', 'flx-chat' ),
						'sub'   => __( 'Promotional messages and reminders are not sent. Replies to the person\'s own messages still go.', 'flx-chat' ),
						'url'   => $url,
						'tone'  => 'pending',
					),
				),
			)
		);
		return $sections;
	},
	3,
	2
);
