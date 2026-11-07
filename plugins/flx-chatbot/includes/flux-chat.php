<?php
/**
 * Flux Chat (çağrı merkezi) köprüsü: devredilen site sohbeti Flux Chat gelen kutusunda "Site sohbeti" olarak açılır.
 * Ziyaretçi mesajları oraya yazılır; temsilcinin cevabı, dosyası, "yazıyor…" ve okundu bilgisi Flux Chat'in sanal kanal
 * filtresiyle (`flxc_virtual_request`, Flux Chat 1.37.0+) buraya döner.
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

const FLXCB_FC_CONN = 'flx-web';

/** Flux Chat bu köprüyü destekliyor mu (1.37.0+). */
function flxcb_fc_ready() {
	return flxcb_flux_chat_active() && function_exists( 'flxc_is_virtual_conn' );
}

function flxcb_fc_jid( $conv ) {
	return 'site-' . (int) $conv['id'] . '@web';
}

function flxcb_fc_conv_from_jid( $jid ) {
	if ( preg_match( '/^site-(\d+)@web$/', (string) $jid, $m ) ) {
		return flxcb_get_conversation( (int) $m[1] );
	}
	return null;
}

add_filter(
	'flxc_virtual_conn',
	function ( $is, $id ) {
		return $is || FLXCB_FC_CONN === $id;
	},
	10,
	2
);

/** Gelen kutusunda telefon yerine görünen satır. */
add_filter(
	'flxc_virtual_label',
	function ( $label, $chat ) {
		if ( FLXCB_FC_CONN !== ( $chat['conn'] ?? '' ) ) {
			return $label;
		}
		$conv = flxcb_fc_conv_from_jid( $chat['jid'] );
		if ( $conv && '' !== (string) $conv['email'] ) {
			/* translators: %s: visitor email */
			return sprintf( __( 'Site sohbeti · %s', 'flx-chatbot' ), $conv['email'] );
		}
		return __( 'Site sohbeti', 'flx-chatbot' );
	},
	10,
	2
);

/** Bu istek sırasında eklenen mesajlar Flux Chat'e geri yazılmaz (döngü olmasın). */
function flxcb_fc_quiet( $set = null ) {
	static $q = false;
	if ( null !== $set ) {
		$q = (bool) $set;
	}
	return $q;
}

/** Bir mesajı Flux Chat sohbetine yazar. */
/** Asistanın Markdown'ını WhatsApp biçimine çevirir (Flux Chat aynı biçimi gösterir). */
function flxcb_md_to_wa( $t ) {
	$t = preg_replace( '/\[([^\]]+)\]\((https?:[^\s)]+)\)/', '$1: $2', (string) $t );
	$t = preg_replace( '/\*\*([^*\n]+)\*\*/', '*$1*', $t );
	$t = preg_replace( '/^#{1,6}\s+(.+)$/m', '*$1*', $t );
	return preg_replace( '/^[ \t]*[-*][ \t]+/m', '• ', $t );
}

function flxcb_fc_copy( array $chat, array $m ) {
	if ( 'system' === $m['role'] ) {
		return 0;
	}
	$type = 'text';
	$path = '';
	$size = 0;
	if ( '' !== $m['file_path'] && function_exists( 'flxc_save_media_binary' ) ) {
		$file = flxcb_upload_dir() . '/' . basename( $m['file_path'] );
		if ( is_readable( $file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions
			$saved = flxc_save_media_binary( file_get_contents( $file ), $m['file_mime'], $m['file_name'] );
			if ( $saved ) {
				$path = (string) $saved['path'];
				$size = (int) $saved['size'];
				$type = 0 === strpos( (string) $m['file_mime'], 'image/' ) ? 'image' : ( 0 === strpos( (string) $m['file_mime'], 'audio/' ) ? 'audio' : 'document' );
			}
		}
	}
	$body = (string) $m['body'];
	if ( 'bot' === $m['role'] ) {
		$body = flxcb_md_to_wa( $body );
		$meta = flxcb_decode( $m['meta'] );
		foreach ( (array) ( $meta['cards'] ?? array() ) as $c ) {
			$body .= "\n• " . $c['title'] . ( '' !== (string) ( $c['price'] ?? '' ) ? ' (' . $c['price'] . ')' : '' ) . ' ' . $c['url'];
		}
	}
	return (int) flxc_insert_message(
		array(
			'chat_id'    => (int) $chat['id'],
			'conn'       => FLXCB_FC_CONN,
			'wa_id'      => 'cb' . (int) $m['id'],
			'direction'  => 'visitor' === $m['role'] ? 'in' : 'out',
			'type'       => $type,
			'body'       => $body,
			'media_path' => $path,
			'media_mime' => $path ? (string) $m['file_mime'] : '',
			'media_name' => $path ? (string) $m['file_name'] : '',
			'media_size' => $size,
			'status'     => 'visitor' === $m['role'] ? '' : 'sent',
			'user_id'    => 'agent' === $m['role'] ? (int) $m['user_id'] : 0,
			'source'     => 'visitor' === $m['role'] ? '' : ( 'bot' === $m['role'] ? 'bot' : 'agent' ),
			'ts'         => (int) $m['ts'],
		)
	);
}

/** Sohbeti Flux Chat'e taşır (ilk devirde geçmişiyle). */
function flxcb_fc_push( $conv, $summary = '' ) {
	if ( ! flxcb_fc_ready() || ! $conv ) {
		return 0;
	}
	$chat = flxc_ensure_chat(
		FLXCB_FC_CONN,
		flxcb_fc_jid( $conv ),
		array(
			'name'      => '' !== (string) $conv['name'] ? $conv['name'] : '',
			'push_name' => flxcb_conv_title( $conv ),
		)
	);
	if ( ! $chat || empty( $chat['id'] ) ) {
		return 0;
	}
	$first = ! (int) $conv['flxc_chat'];
	if ( $first ) {
		flxcb_update_conversation( (int) $conv['id'], array( 'flxc_chat' => (int) $chat['id'] ) );
		foreach ( flxcb_messages( (int) $conv['id'], 0, 60 ) as $m ) {
			flxcb_fc_copy( $chat, $m );
		}
	}
	$lines = array( __( 'Site sohbeti temsilci bekliyor.', 'flx-chatbot' ) );
	if ( '' !== (string) $conv['page_url'] ) {
		$lines[] = __( 'Sayfa:', 'flx-chatbot' ) . ' ' . ( '' !== (string) $conv['page_title'] ? $conv['page_title'] . ' · ' : '' ) . $conv['page_url'];
	}
	if ( '' !== (string) $conv['email'] || '' !== (string) $conv['phone'] ) {
		$lines[] = __( 'İletişim:', 'flx-chatbot' ) . ' ' . trim( $conv['email'] . ' ' . $conv['phone'] );
	}
	if ( '' !== trim( (string) $summary ) ) {
		$lines[] = __( 'Asistan notu:', 'flx-chatbot' ) . ' ' . $summary;
	}
	if ( function_exists( 'flxc_system_message' ) ) {
		flxc_system_message( $chat, implode( "\n", $lines ) );
	}
	flxc_update_chat( (int) $chat['id'], array( 'status' => 'open' ) );
	$chat = flxc_get_chat( (int) $chat['id'] );
	do_action( 'flxc_bot_handoff', $chat, 'human' ); // ekip bildirimi (WhatsApp / grup), Flux Chat ayarına göre
	return (int) $chat['id'];
}

/* Devirden sonra yeni mesajlar Flux Chat'e de yazılır. */
add_action(
	'flxcb_message_added',
	function ( $m ) {
		if ( ! $m || flxcb_fc_quiet() || (int) $m['ext_id'] || ! flxcb_fc_ready() ) {
			return;
		}
		$conv = flxcb_get_conversation( (int) $m['conv_id'] );
		if ( ! $conv || ! (int) $conv['flxc_chat'] ) {
			return;
		}
		$chat = flxc_get_chat( (int) $conv['flxc_chat'] );
		if ( $chat ) {
			flxcb_fc_copy( $chat, $m );
		}
	}
);

/* Ziyaretçi yazarken Flux Chat'te "yazıyor…". */
add_action(
	'flxcb_visitor_typing',
	function ( $conv ) {
		if ( ! (int) $conv['flxc_chat'] || ! flxcb_fc_ready() ) {
			return;
		}
		if ( ! get_transient( 'flxc_typing_' . (int) $conv['flxc_chat'] ) ) {
			set_transient( 'flxc_typing_' . (int) $conv['flxc_chat'], 1, 12 );
			flxc_update_chat( (int) $conv['flxc_chat'], array() );
		}
	}
);

/* Ziyaretçi temsilci mesajını görünce Flux Chat'te çift mavi tik. */
add_action(
	'flxcb_visitor_read',
	function ( $conv, $from, $to ) {
		if ( ! (int) $conv['flxc_chat'] || ! flxcb_fc_ready() || ! function_exists( 'flxc_find_message_by_wa' ) ) {
			return;
		}
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxcb_table( 'messages' ) . " WHERE conv_id = %d AND role IN ('agent','bot') AND id > %d AND id <= %d ORDER BY id DESC LIMIT 20", (int) $conv['id'], (int) $from, (int) $to ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $ids as $id ) {
			$fm = flxc_find_message_by_wa( FLXCB_FC_CONN, 'cb' . (int) $id );
			if ( $fm && 'read' !== $fm['status'] && 'out' === $fm['direction'] ) {
				flxc_update_message( (int) $fm['id'], array( 'status' => 'read' ) );
			}
		}
	},
	10,
	3
);

/** Temsilci Flux Chat'te sohbeti kapattıysa site sohbeti de kapanır. */
function flxcb_fc_sync_status( $conv ) {
	if ( ! $conv || ! (int) $conv['flxc_chat'] || ! flxcb_fc_ready() || ! in_array( $conv['status'], array( 'agent', 'queued', 'offline' ), true ) ) {
		return $conv;
	}
	$chat = flxc_get_chat( (int) $conv['flxc_chat'] );
	if ( $chat && 'closed' === $chat['status'] ) {
		flxcb_close( $conv, 'agent' );
		return flxcb_get_conversation( (int) $conv['id'] );
	}
	return $conv;
}

/**
 * Flux Chat'in Evolution v2 isteklerini site sohbetine çevirir.
 */
add_filter(
	'flxc_virtual_request',
	function ( $pre, $conn, $method, $path, $body ) {
		if ( FLXCB_FC_CONN !== $conn ) {
			return $pre;
		}
		$body = is_array( $body ) ? $body : array();
		$ok   = function ( $json = array() ) {
			return array( 'ok' => true, 'code' => 200, 'json' => $json, 'body' => '', 'error' => '' );
		};
		$fail = function ( $msg ) {
			return array( 'ok' => false, 'code' => 400, 'json' => null, 'body' => '', 'error' => $msg );
		};
		$route = preg_replace( '#/\{instance\}.*$#', '', (string) $path );

		if ( '/chat/markMessageAsRead' === $route ) {
			$max  = 0;
			$conv = null;
			foreach ( (array) ( $body['readMessages'] ?? array() ) as $r ) {
				$conv = $conv ? $conv : flxcb_fc_conv_from_jid( $r['remoteJid'] ?? '' );
				if ( preg_match( '/^cb(\d+)$/', (string) ( $r['id'] ?? '' ), $mm ) ) {
					$max = max( $max, (int) $mm[1] );
				}
			}
			if ( $conv && $max > (int) $conv['agent_read'] ) {
				flxcb_update_conversation( (int) $conv['id'], array( 'agent_read' => $max, 'unread' => 0 ) );
			}
			return $ok();
		}
		if ( '/chat/fetchProfilePictureUrl' === $route ) {
			return $ok( array( 'profilePictureUrl' => '' ) );
		}
		if ( '/message/sendReaction' === $route ) {
			return $ok( array( 'key' => array( 'id' => '' ) ) );
		}

		$conv = flxcb_fc_conv_from_jid( (string) ( $body['number'] ?? '' ) );
		if ( ! $conv ) {
			// Ekip numarasına giden bildirim vb. bu kanaldan gönderilemez.
			return $fail( __( 'Site sohbeti bulunamadı.', 'flx-chatbot' ) );
		}
		if ( '/chat/sendPresence' === $route ) {
			if ( in_array( (string) ( $body['presence'] ?? '' ), array( 'composing', 'recording' ), true ) ) {
				set_transient( 'flxcb_typing_a_' . (int) $conv['id'], 1, 8 );
			} else {
				delete_transient( 'flxcb_typing_a_' . (int) $conv['id'] );
			}
			return $ok();
		}
		if ( 'closed' === $conv['status'] ) {
			flxcb_update_conversation( (int) $conv['id'], array( 'status' => 'agent' ) );
			$conv['status'] = 'agent';
		}
		$user = get_current_user_id();
		$id   = 0;
		flxcb_fc_quiet( true );
		if ( '/message/sendText' === $route ) {
			$text = (string) ( $body['text'] ?? '' );
			$text = preg_replace( '/^\*[^*\n]{1,80}:\*\n/u', '', $text ); // Flux Chat temsilci imzası; pencerede ad zaten görünür.
			$id   = flxcb_agent_reply( $conv, $user, $text );
		} elseif ( '/message/sendMedia' === $route || '/message/sendWhatsAppAudio' === $route ) {
			$audio = '/message/sendWhatsAppAudio' === $route;
			$data  = (string) ( $audio ? ( $body['audio'] ?? '' ) : ( $body['media'] ?? '' ) );
			$mime  = $audio ? 'audio/ogg' : (string) ( $body['mimetype'] ?? 'application/octet-stream' );
			$name  = $audio ? 'audio.ogg' : (string) ( $body['fileName'] ?? '' );
			$bin   = preg_match( '#^https?://#', $data ) ? '' : base64_decode( preg_replace( '#^data:[^,]*,#', '', $data ), true );
			if ( ! $bin ) {
				flxcb_fc_quiet( false );
				return $fail( __( 'Dosya okunamadı.', 'flx-chatbot' ) );
			}
			if ( '' === $name ) {
				$name = 'file.' . ( wp_get_default_extension_for_mime_type( $mime ) ?: 'bin' );
			}
			$file = flxcb_store_bytes( $bin, $mime, sanitize_file_name( $name ) );
			if ( is_wp_error( $file ) ) {
				flxcb_fc_quiet( false );
				return $fail( $file->get_error_message() );
			}
			$id = flxcb_agent_reply( $conv, $user, (string) ( $body['caption'] ?? '' ), $file );
		} else {
			flxcb_fc_quiet( false );
			return $fail( __( 'Bu işlem site sohbetinde kullanılamaz.', 'flx-chatbot' ) );
		}
		flxcb_fc_quiet( false );
		return $id ? $ok( array( 'key' => array( 'id' => 'cb' . (int) $id ) ) ) : $fail( __( 'Mesaj kaydedilemedi.', 'flx-chatbot' ) );
	},
	10,
	5
);

/* Ziyaretçi adını/e-postasını verince Flux Chat'teki sohbet adı da güncellenir. */
add_action(
	'flxcb_contact_changed',
	function ( $id ) {
		$conv = flxcb_get_conversation( (int) $id );
		if ( ! $conv || ! (int) $conv['flxc_chat'] || ! flxcb_fc_ready() || ! function_exists( 'flxc_update_chat' ) ) {
			return;
		}
		$upd = array( 'push_name' => flxcb_conv_title( $conv ) );
		if ( '' !== (string) $conv['name'] ) {
			$upd['name'] = (string) $conv['name'];
		}
		flxc_update_chat( (int) $conv['flxc_chat'], $upd );
	}
);
