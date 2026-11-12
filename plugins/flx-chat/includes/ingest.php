<?php
defined( 'ABSPATH' ) || exit;

/**
 * Evolution webhook olaylarını işler. Mesajlar (gelen ve telefondan/başka sistemden giden), durum (tik),
 * silme/düzenleme, tepki, bağlantı durumu, "yazıyor…" ve kişi adı/fotoğrafı.
 */
function flxc_handle_event( $conn_id, array $payload ) {
	$event = strtolower( str_replace( '_', '.', (string) ( $payload['event'] ?? '' ) ) );
	$data  = $payload['data'] ?? array();
	if ( ! is_array( $data ) ) {
		return;
	}
	switch ( $event ) {
		case 'messages.upsert':
		case 'send.message':
			$list = isset( $data['messages'] ) && is_array( $data['messages'] ) ? $data['messages'] : ( isset( $data[0] ) ? $data : array( $data ) );
			foreach ( $list as $d ) {
				if ( is_array( $d ) ) {
					flxc_ingest_message( $conn_id, $d );
				}
			}
			break;
		case 'messages.update':
			foreach ( isset( $data[0] ) ? $data : array( $data ) as $d ) {
				if ( is_array( $d ) ) {
					flxc_ingest_status( $conn_id, $d );
				}
			}
			break;
		case 'messages.delete':
			foreach ( isset( $data[0] ) ? $data : array( $data ) as $d ) {
				$id = is_array( $d ) ? ( $d['id'] ?? ( $d['key']['id'] ?? ( $d['keyId'] ?? '' ) ) ) : '';
				flxc_mark_deleted( $conn_id, (string) $id );
			}
			break;
		case 'connection.update':
			if ( ! empty( $data['state'] ) ) {
				flxc_set_connection_state( $conn_id, (string) $data['state'] );
			}
			break;
		case 'presence.update':
			flxc_ingest_presence( $conn_id, $data );
			break;
		case 'contacts.upsert':
		case 'contacts.update':
			foreach ( isset( $data[0] ) ? $data : array( $data ) as $d ) {
				if ( is_array( $d ) ) {
					flxc_ingest_contact( $conn_id, $d );
				}
			}
			break;
	}
	do_action( 'flxc_webhook_event', $event, $data, $conn_id );
}

/** Mesaj içeriğini sadeleştirir. */
function flxc_parse_wa_message( $message ) {
	$m = is_array( $message ) ? $message : array();
	// Sarmalayıcıları aç.
	for ( $i = 0; $i < 4; $i++ ) {
		foreach ( array( 'ephemeralMessage', 'viewOnceMessage', 'viewOnceMessageV2', 'viewOnceMessageV2Extension', 'documentWithCaptionMessage' ) as $w ) {
			if ( isset( $m[ $w ]['message'] ) && is_array( $m[ $w ]['message'] ) ) {
				$m = $m[ $w ]['message'] + array_diff_key( $m, array( $w => 1 ) );
			}
		}
	}
	$out = array(
		'kind'    => 'message',
		'type'    => 'text',
		'body'    => '',
		'mime'    => '',
		'name'    => '',
		'quoted'  => '',
		'meta'    => array(),
		'base64'  => (string) ( $m['base64'] ?? '' ),
		'target'  => '',
	);
	$ctx = null;

	if ( isset( $m['protocolMessage'] ) ) {
		$p    = $m['protocolMessage'];
		$type = $p['type'] ?? '';
		if ( 0 === $type || 'REVOKE' === $type ) {
			$out['kind']   = 'revoke';
			$out['target'] = (string) ( $p['key']['id'] ?? '' );
			return $out;
		}
		if ( 14 === $type || 'MESSAGE_EDIT' === $type ) {
			$out['kind']   = 'edit';
			$out['target'] = (string) ( $p['key']['id'] ?? '' );
			$e             = flxc_parse_wa_message( $p['editedMessage'] ?? array() );
			$out['body']   = $e['body'];
			return $out;
		}
		$out['kind'] = 'ignore';
		return $out;
	}
	if ( isset( $m['editedMessage']['message'] ) ) {
		return flxc_parse_wa_message( $m['editedMessage']['message'] );
	}
	if ( isset( $m['reactionMessage'] ) ) {
		$out['kind']   = 'reaction';
		$out['target'] = (string) ( $m['reactionMessage']['key']['id'] ?? '' );
		$out['body']   = (string) ( $m['reactionMessage']['text'] ?? '' );
		return $out;
	}
	if ( isset( $m['conversation'] ) ) {
		$out['body'] = (string) $m['conversation'];
	} elseif ( isset( $m['extendedTextMessage'] ) ) {
		$out['body'] = (string) ( $m['extendedTextMessage']['text'] ?? '' );
		$ctx         = $m['extendedTextMessage']['contextInfo'] ?? null;
		$x           = $m['extendedTextMessage'];
		$lp_url      = (string) ( $x['matchedText'] ?? ( $x['canonicalUrl'] ?? '' ) );
		if ( '' !== $lp_url && ( ! empty( $x['title'] ) || ! empty( $x['description'] ) ) ) {
			$lp = array(
				'url'   => esc_url_raw( $lp_url ),
				'title' => mb_substr( (string) ( $x['title'] ?? '' ), 0, 200 ),
				'desc'  => mb_substr( (string) ( $x['description'] ?? '' ), 0, 300 ),
			);
			if ( ! empty( $x['jpegThumbnail'] ) && is_string( $x['jpegThumbnail'] ) && strlen( $x['jpegThumbnail'] ) < 40000 ) {
				$lp['thumb'] = 'data:image/jpeg;base64,' . $x['jpegThumbnail'];
			}
			$out['meta'] = array( 'lp' => $lp );
		}
	} elseif ( isset( $m['imageMessage'] ) ) {
		$x           = $m['imageMessage'];
		$out['type'] = 'image';
		$out['body'] = (string) ( $x['caption'] ?? '' );
		$out['mime'] = (string) ( $x['mimetype'] ?? 'image/jpeg' );
		$out['meta'] = array( 'w' => (int) ( $x['width'] ?? 0 ), 'h' => (int) ( $x['height'] ?? 0 ) );
		if ( ! empty( $x['jpegThumbnail'] ) && is_string( $x['jpegThumbnail'] ) && strlen( $x['jpegThumbnail'] ) < 20000 ) {
			$out['meta']['thumb'] = 'data:image/jpeg;base64,' . $x['jpegThumbnail'];
		}
		$ctx = $x['contextInfo'] ?? null;
	} elseif ( isset( $m['videoMessage'] ) ) {
		$x           = $m['videoMessage'];
		$out['type'] = 'video';
		$out['body'] = (string) ( $x['caption'] ?? '' );
		$out['mime'] = (string) ( $x['mimetype'] ?? 'video/mp4' );
		$out['meta'] = array( 'seconds' => (int) ( $x['seconds'] ?? 0 ) );
		$ctx         = $x['contextInfo'] ?? null;
	} elseif ( isset( $m['audioMessage'] ) ) {
		$x           = $m['audioMessage'];
		$out['type'] = 'audio';
		$out['mime'] = (string) ( $x['mimetype'] ?? 'audio/ogg' );
		$out['meta'] = array( 'ptt' => ! empty( $x['ptt'] ), 'seconds' => (int) ( $x['seconds'] ?? 0 ) );
		$ctx         = $x['contextInfo'] ?? null;
	} elseif ( isset( $m['documentMessage'] ) ) {
		$x           = $m['documentMessage'];
		$out['type'] = 'document';
		$out['body'] = (string) ( $x['caption'] ?? '' );
		$out['mime'] = (string) ( $x['mimetype'] ?? 'application/octet-stream' );
		$out['name'] = (string) ( $x['fileName'] ?? ( $x['title'] ?? '' ) );
		$ctx         = $x['contextInfo'] ?? null;
	} elseif ( isset( $m['stickerMessage'] ) ) {
		$out['type'] = 'sticker';
		$out['mime'] = (string) ( $m['stickerMessage']['mimetype'] ?? 'image/webp' );
	} elseif ( isset( $m['locationMessage'] ) || isset( $m['liveLocationMessage'] ) ) {
		$x           = $m['locationMessage'] ?? $m['liveLocationMessage'];
		$out['type'] = 'location';
		$out['body'] = trim( (string) ( $x['name'] ?? '' ) . ' ' . (string) ( $x['address'] ?? '' ) );
		$out['meta'] = array(
			'lat'      => (float) ( $x['degreesLatitude'] ?? 0 ),
			'lng'      => (float) ( $x['degreesLongitude'] ?? 0 ),
			'loc_name' => (string) ( $x['name'] ?? '' ),
		);
	} elseif ( isset( $m['contactMessage'] ) ) {
		$x           = $m['contactMessage'];
		$out['type'] = 'contact';
		$out['body'] = (string) ( $x['displayName'] ?? '' );
		$ph          = flxc_vcard_phone( (string) ( $x['vcard'] ?? '' ) );
		if ( '' !== $ph ) {
			$out['meta']['vcard_phone'] = $ph;
			$out['meta']['vcards']      = array( array( 'name' => $out['body'], 'phone' => $ph ) );
		}
	} elseif ( isset( $m['contactsArrayMessage'] ) ) {
		$out['type'] = 'contact';
		$names       = array();
		$cards       = array();
		foreach ( (array) ( $m['contactsArrayMessage']['contacts'] ?? array() ) as $c ) {
			$n       = (string) ( $c['displayName'] ?? '' );
			$names[] = $n;
			$cards[] = array( 'name' => $n, 'phone' => flxc_vcard_phone( (string) ( $c['vcard'] ?? '' ) ) );
		}
		$out['body'] = implode( ', ', array_filter( $names ) );
		$cards       = array_values( array_filter( $cards, function ( $c ) { return '' !== $c['phone']; } ) );
		if ( $cards ) {
			$out['meta']['vcard_phone'] = $cards[0]['phone'];
			$out['meta']['vcards']      = $cards;
		}
	} elseif ( isset( $m['buttonsResponseMessage'] ) ) {
		$out['body'] = (string) ( $m['buttonsResponseMessage']['selectedDisplayText'] ?? '' );
	} elseif ( isset( $m['listResponseMessage'] ) ) {
		$out['body'] = (string) ( $m['listResponseMessage']['title'] ?? '' );
	} elseif ( isset( $m['templateButtonReplyMessage'] ) ) {
		$out['body'] = (string) ( $m['templateButtonReplyMessage']['selectedDisplayText'] ?? '' );
	} elseif ( isset( $m['pollCreationMessage'] ) || isset( $m['pollCreationMessageV3'] ) ) {
		$x           = $m['pollCreationMessage'] ?? $m['pollCreationMessageV3'];
		$out['type'] = 'poll';
		$opts        = array();
		foreach ( (array) ( $x['options'] ?? array() ) as $o ) {
			$opts[] = '• ' . ( $o['optionName'] ?? '' );
		}
		$out['body'] = trim( (string) ( $x['name'] ?? '' ) . "\n" . implode( "\n", $opts ) );
	} elseif ( isset( $m['pollUpdateMessage'] ) || isset( $m['senderKeyDistributionMessage'] ) || isset( $m['messageContextInfo'] ) && 1 === count( $m ) ) {
		$out['kind'] = 'ignore';
	} else {
		$keys = array_diff( array_keys( $m ), array( 'messageContextInfo', 'base64' ) );
		if ( ! $keys ) {
			$out['kind'] = 'ignore';
		} else {
			$out['type'] = 'unknown';
		}
	}
	if ( is_array( $ctx ) && ! empty( $ctx['stanzaId'] ) ) {
		$out['quoted'] = (string) $ctx['stanzaId'];
	}
	return $out;
}

function flxc_wa_timestamp( $ts ) {
	if ( is_array( $ts ) ) {
		$ts = $ts['low'] ?? 0;
	}
	$ts = (float) $ts;
	if ( $ts <= 0 ) {
		return (int) floor( microtime( true ) * 1000 );
	}
	return $ts > 1e12 ? (int) $ts : (int) ( $ts * 1000 );
}

function flxc_map_status( $s ) {
	if ( is_numeric( $s ) ) {
		$n = array( 0 => 'failed', 1 => 'pending', 2 => 'sent', 3 => 'delivered', 4 => 'read', 5 => 'played' );
		return $n[ (int) $s ] ?? '';
	}
	$map = array(
		'ERROR'        => 'failed',
		'PENDING'      => 'pending',
		'SERVER_ACK'   => 'sent',
		'DELIVERY_ACK' => 'delivered',
		'READ'         => 'read',
		'PLAYED'       => 'played',
	);
	return $map[ strtoupper( (string) $s ) ] ?? '';
}

/** @lid adreslerinde telefon numarası ayrı alanda gelir. */
function flxc_resolve_jid( array $key, array $d = array() ) {
	$jid = (string) ( $key['remoteJid'] ?? '' );
	if ( '@lid' === substr( $jid, -4 ) ) {
		$lid = flxc_lid_digits( $jid );
		// Evolution sürümüne göre gerçek numara key içinde ya da mesajın kökünde gelir.
		foreach ( array( $key, $d ) as $src ) {
			foreach ( array( 'remoteJidAlt', 'senderPn', 'participantAlt', 'participantPn' ) as $k ) {
				$v = isset( $src[ $k ] ) && is_string( $src[ $k ] ) ? $src[ $k ] : '';
				if ( false !== strpos( $v, '@lid' ) || false !== strpos( $v, '@g.us' ) ) {
					continue;
				}
				$num = flxc_jid_phone( $v );
				if ( strlen( $num ) >= 7 && $num !== $lid ) {
					return $num . '@s.whatsapp.net';
				}
			}
		}
	}
	return $jid;
}

/**
 * Tek mesajı kaydeder.
 * @param array $opts history (geçmiş içe aktarımı: bildirim/otomatik yanıt yok, okunmamış artmaz)
 * @return int mesaj id (0: atlandı)
 */
function flxc_ingest_message( $conn_id, array $d, $opts = array() ) {
	$history = ! empty( $opts['history'] );
	$key     = isset( $d['key'] ) && is_array( $d['key'] ) ? $d['key'] : array();
	$jid     = flxc_resolve_jid( $key, $d );
	$wa_id   = (string) ( $key['id'] ?? '' );
	$from_me = ! empty( $key['fromMe'] );
	if ( '' === $jid || '' === $wa_id ) {
		return 0;
	}
	if ( false !== strpos( $jid, '@broadcast' ) || false !== strpos( $jid, '@newsletter' ) ) {
		return 0;
	}
	if ( flxc_is_group_jid( $jid ) && ! flxc_setting( 'show_groups' ) ) {
		return 0;
	}
	$message = isset( $d['message'] ) && is_array( $d['message'] ) ? $d['message'] : array();
	if ( ! empty( $d['base64'] ) && empty( $message['base64'] ) ) {
		$message['base64'] = $d['base64'];
	}
	$p = flxc_parse_wa_message( $message );
	if ( $from_me && function_exists( 'flxc_notify_is_echo' ) && flxc_notify_is_echo( $wa_id, $p['body'] ) ) {
		return 0; // Ekibe giden devir bildirimi: sohbet olarak kaydedilmez.
	}

	if ( 'ignore' === $p['kind'] ) {
		return 0;
	}
	if ( 'reaction' === $p['kind'] ) {
		flxc_apply_reaction( $conn_id, $p['target'], $from_me ? 'out' : 'in', $p['body'] );
		return 0;
	}
	if ( 'revoke' === $p['kind'] ) {
		flxc_mark_deleted( $conn_id, $p['target'] );
		return 0;
	}
	if ( 'edit' === $p['kind'] ) {
		$t = flxc_find_message_by_wa( $conn_id, $p['target'] );
		if ( $t ) {
			$meta           = flxc_decode( $t['meta'] );
			$meta['edited'] = 1;
			flxc_update_message( $t['id'], array( 'body' => $p['body'], 'meta' => $meta ) );
		}
		return 0;
	}

	$existing = flxc_find_message_by_wa( $conn_id, $wa_id );
	if ( $existing ) {
		// Medya sonradan base64 ile geldiyse kaydet.
		if ( '' === $existing['media_path'] && '' !== $p['base64'] ) {
			$saved = flxc_save_media_base64( $p['base64'], $p['mime'], $p['name'] );
			if ( $saved ) {
				flxc_update_message( $existing['id'], array( 'media_path' => $saved['path'], 'media_size' => $saved['size'] ) );
			}
		}
		return (int) $existing['id'];
	}

	$push = $from_me ? '' : (string) ( $d['pushName'] ?? '' );
	$raw_jid = (string) ( $key['remoteJid'] ?? '' );
	if ( empty( $opts['chat'] ) && '@lid' === substr( $raw_jid, -4 ) && $raw_jid !== $jid ) {
		// Gizli numarayla açılmış sohbetin gerçek numarası geldi: yeni sohbet açma, eskisine numarayı yaz.
		$opts['chat'] = flxc_lid_chat_learn_phone( $conn_id, $raw_jid, flxc_jid_phone( $jid ) );
	}
	$chat = ! empty( $opts['chat'] ) ? $opts['chat'] : flxc_ensure_chat( $conn_id, $jid, array( 'push_name' => $push ) );
	if ( ! $chat || empty( $chat['id'] ) ) {
		return 0;
	}
	if ( $raw_jid && $raw_jid !== $chat['jid'] ) {
		flxc_remember_alias( $chat, $raw_jid );
	}
	$ts = flxc_wa_timestamp( $d['messageTimestamp'] ?? 0 );
	if ( function_exists( 'flxc_chat_cleared_before' ) && flxc_chat_cleared_before( $chat, $ts ) ) {
		return 0; // Yönetici bu sohbetin geçmişini temizledi.
	}

	// Bu panelden gönderilen mesajın yankısı: wa_id henüz yazılmadıysa bekleyen mesajla eşle.
	if ( $from_me ) {
		$pending = flxc_match_pending_outgoing( $chat['id'], $p['body'], $p['type'] );
		if ( $pending ) {
			flxc_update_message( $pending['id'], array( 'wa_id' => $wa_id, 'status' => 'sent' ) );
			return (int) $pending['id'];
		}
	}

	$status = '';
	$source = '';
	if ( $from_me ) {
		$status = flxc_map_status( $d['status'] ?? '' );
		if ( isset( $d['MessageUpdate'] ) && is_array( $d['MessageUpdate'] ) ) {
			foreach ( $d['MessageUpdate'] as $u ) {
				$s = flxc_map_status( $u['status'] ?? '' );
				if ( flxc_status_rank( $s ) > flxc_status_rank( $status ) ) {
					$status = $s;
				}
			}
		}
		$status = $status && 'pending' !== $status ? $status : 'sent';
		$source = flxc_detect_external_source( $wa_id, '' !== (string) ( $chat['phone'] ?? '' ) ? (string) $chat['phone'] : flxc_jid_phone( $jid ), (string) $p['body'] );
	}

	$meta = $p['meta'];
	if ( flxc_is_group_jid( $jid ) && ! $from_me ) {
		$meta['sender'] = (string) ( $d['pushName'] ?? flxc_jid_phone( $key['participant'] ?? '' ) );
	}

	$row = array(
		'chat_id'     => (int) $chat['id'],
		'conn'        => $conn_id,
		'wa_id'       => $wa_id,
		'direction'   => $from_me ? 'out' : 'in',
		'type'        => $p['type'],
		'body'        => $p['body'],
		'media_mime'  => $p['mime'],
		'media_name'  => $p['name'],
		'quoted_wa'   => $p['quoted'],
		'status'      => $status,
		'source'      => $source,
		'ts'          => $ts,
		'meta'        => $meta,
		'skip_unread' => $history,
	);
	if ( '' !== $p['base64'] ) {
		$saved = flxc_save_media_base64( $p['base64'], $p['mime'], $p['name'] );
		if ( $saved ) {
			$row['media_path'] = $saved['path'];
			$row['media_size'] = $saved['size'];
		}
	}
	$id = flxc_insert_message( $row, $chat );
	if ( $id && ! $from_me ) {
		delete_transient( 'flxc_typing_' . $chat['id'] );
		if ( ! $history ) {
			/**
			 * Yeni gelen mesaj. Otomatik yanıt, yapay zekâ botu ya da bildirim için kullanılır.
			 * @param int   $id   mesaj id
			 * @param array $chat sohbet satırı (mesajdan önceki hali)
			 */
			do_action( 'flxc_message_received', $id, $chat );
		}
	} elseif ( $id && 'phone' === $source && ! $history ) {
		/** Telefondan yazılan mesaj: asistan bu sohbette susar. Flux Certificates gönderimi (1.40.0) susturmaz. */
		do_action( 'flxc_message_from_phone', $id, $chat );
	}
	return $id;
}

/**
 * Bu mesaj Flux Certificates'dan mı gönderildi? Diğer paketler flxc_external_source ile kaynak verir.
 * 1.44.0: filtre numara ve metni de alır (Flux Mail echo kimlikten önce gelirse numara + metinle tanır).
 */
function flxc_detect_external_source( $wa_id, $phone = '', $body = null ) {
	$source = 'phone';
	if ( function_exists( 'flxs_table' ) ) {
		global $wpdb;
		$t = flxs_table( 'messages' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE external_id = %s LIMIT 1", $wa_id ) ) ) {
			$source = 'certificate';
		}
	}
	return (string) apply_filters( 'flxc_external_source', $source, $wa_id, (string) $phone, $body );
}

function flxc_match_pending_outgoing( $chat_id, $body, $type ) {
	global $wpdb;
	$t   = flxc_table( 'messages' );
	$min = (int) floor( microtime( true ) * 1000 ) - 120000;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE chat_id = %d AND direction = 'out' AND wa_id = '' AND status IN ('pending','sending') AND ts > %d ORDER BY id ASC LIMIT 5", $chat_id, $min ), ARRAY_A );
	foreach ( $rows as $r ) {
		if ( $r['type'] === $type && ( 'text' !== $type || trim( $r['body'] ) === trim( $body ) || false !== strpos( $body, trim( $r['body'] ) ) ) ) {
			return $r;
		}
	}
	return null;
}

function flxc_ingest_status( $conn_id, array $d ) {
	$wa_id   = (string) ( $d['keyId'] ?? ( $d['key']['id'] ?? ( $d['id'] ?? '' ) ) );
	$status  = flxc_map_status( $d['status'] ?? ( $d['update']['status'] ?? '' ) );
	$from_me = isset( $d['fromMe'] ) ? ! empty( $d['fromMe'] ) : ! empty( $d['key']['fromMe'] );
	if ( '' === $wa_id || '' === $status ) {
		return;
	}
	$m = flxc_find_message_by_wa( $conn_id, $wa_id );
	if ( ! $m ) {
		return;
	}
	if ( 'out' === $m['direction'] ) {
		if ( flxc_status_rank( $status ) > flxc_status_rank( $m['status'] ) || ( 'failed' === $status && 'sent' === $m['status'] ) ) {
			flxc_update_message( $m['id'], array( 'status' => $status ) );
		}
	} elseif ( ! $from_me && 'read' === $status ) {
		// Gelen mesaj başka bir cihazda (telefon) okundu.
		$chat = flxc_get_chat( $m['chat_id'] );
		if ( $chat && (int) $chat['unread'] > 0 ) {
			flxc_update_chat( $chat['id'], array( 'unread' => 0 ) );
		}
	}
}

function flxc_apply_reaction( $conn_id, $target_wa, $side, $emoji ) {
	$t = flxc_find_message_by_wa( $conn_id, $target_wa );
	if ( ! $t ) {
		return;
	}
	$meta = flxc_decode( $t['meta'] );
	$r    = isset( $meta['reactions'] ) ? (array) $meta['reactions'] : array();
	if ( '' === $emoji ) {
		unset( $r[ $side ] );
	} else {
		$r[ $side ] = $emoji;
	}
	$meta['reactions'] = $r;
	flxc_update_message( $t['id'], array( 'meta' => $meta ) );
}

function flxc_mark_deleted( $conn_id, $wa_id ) {
	$t = flxc_find_message_by_wa( $conn_id, $wa_id );
	if ( ! $t ) {
		return;
	}
	// Denetim için asıl içerik saklanır; arayüzde "Bu mesaj silindi" görünür, tıklayınca açılır.
	flxc_soft_delete_message( $t, 'in' === $t['direction'] ? 'contact' : 'phone' );
}

function flxc_ingest_presence( $conn_id, array $d ) {
	$pres = isset( $d['presences'] ) && is_array( $d['presences'] ) ? $d['presences'] : array();
	foreach ( $pres as $jid => $p ) {
		$chat_jid = (string) ( $d['id'] ?? $jid );
		$chat     = flxc_find_chat( $conn_id, $chat_jid );
		if ( ! $chat ) {
			$phone = flxc_jid_phone( $chat_jid );
			$chat  = $phone ? flxc_find_chat_by_phone( $conn_id, $phone ) : null;
		}
		if ( ! $chat ) {
			continue;
		}
		$state = (string) ( $p['lastKnownPresence'] ?? '' );
		if ( in_array( $state, array( 'composing', 'recording' ), true ) ) {
			set_transient( 'flxc_typing_' . $chat['id'], 'recording' === $state ? 2 : 1, 15 );
		} else {
			delete_transient( 'flxc_typing_' . $chat['id'] );
		}
		flxc_update_chat( $chat['id'], array() );
	}
}

function flxc_ingest_contact( $conn_id, array $d ) {
	$jid = (string) ( $d['remoteJid'] ?? ( $d['id'] ?? '' ) );
	if ( '' === $jid ) {
		return;
	}
	$chat = flxc_find_chat( $conn_id, $jid );
	if ( ! $chat ) {
		return;
	}
	$upd = array();
	if ( ! empty( $d['profilePicUrl'] ) && $d['profilePicUrl'] !== $chat['avatar'] ) {
		$upd['avatar']    = esc_url_raw( $d['profilePicUrl'] );
		$upd['avatar_at'] = time();
	}
	if ( ! empty( $d['pushName'] ) && '' === $chat['push_name'] ) {
		$upd['push_name'] = mb_substr( (string) $d['pushName'], 0, 190 );
	}
	if ( $upd ) {
		flxc_update_chat( $chat['id'], $upd );
	}
}

/* ---------- Medya dosyaları ---------- */

function flxc_mime_ext( $mime, $name = '' ) {
	$ext = $name ? strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) : '';
	if ( $ext && preg_match( '/^[a-z0-9]{1,6}$/', $ext ) && ! in_array( $ext, array( 'php', 'phtml', 'phar', 'htaccess', 'js', 'html', 'htm', 'svg' ), true ) ) {
		return $ext;
	}
	$base = strtolower( trim( explode( ';', (string) $mime )[0] ) );
	$map  = array(
		'image/jpeg'      => 'jpg',
		'image/png'       => 'png',
		'image/webp'      => 'webp',
		'image/gif'       => 'gif',
		'video/mp4'       => 'mp4',
		'video/3gpp'      => '3gp',
		'audio/ogg'       => 'ogg',
		'audio/mpeg'      => 'mp3',
		'audio/mp4'       => 'm4a',
		'audio/aac'       => 'aac',
		'audio/webm'      => 'webm',
		'application/pdf' => 'pdf',
	);
	return $map[ $base ] ?? 'bin';
}

/** @return array|null path (uploads/flx-chat'ye göre), size */
function flxc_save_media_base64( $b64, $mime, $name = '' ) {
	if ( 0 === strpos( $b64, 'data:' ) ) {
		$b64 = substr( $b64, strpos( $b64, ',' ) + 1 );
	}
	$bin = base64_decode( $b64, true ); // phpcs:ignore
	if ( false === $bin || '' === $bin ) {
		return null;
	}
	return flxc_save_media_binary( $bin, $mime, $name );
}

function flxc_save_media_binary( $bin, $mime, $name = '' ) {
	$dir = flxc_media_dir();
	$sub = gmdate( 'Y/m' );
	wp_mkdir_p( $dir . '/' . $sub );
	$file = $sub . '/' . wp_generate_password( 20, false, false ) . '.' . flxc_mime_ext( $mime, $name );
	// phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( false === file_put_contents( $dir . '/' . $file, $bin ) ) {
		return null;
	}
	return array( 'path' => $file, 'size' => strlen( $bin ) );
}

/* ---------- Geçmişi içe aktar ---------- */

/**
 * Evolution'daki son sohbetleri ve her birinin son mesajlarını içe aktarır.
 * @return array|WP_Error chats, messages
 */
function flxc_import_history( $conn_id, $max_chats = 30, $per_chat = 30 ) {
	$chats = flxc_evo_find_chats( $conn_id );
	if ( is_wp_error( $chats ) ) {
		return $chats;
	}
	usort(
		$chats,
		function ( $a, $b ) {
			return strcmp( (string) ( $b['updatedAt'] ?? '' ), (string) ( $a['updatedAt'] ?? '' ) );
		}
	);
	$n_chats = 0;
	$n_msgs  = 0;
	foreach ( $chats as $c ) {
		if ( $n_chats >= $max_chats ) {
			break;
		}
		$jid = (string) ( $c['remoteJid'] ?? ( $c['id'] ?? '' ) );
		if ( '' === $jid || false !== strpos( $jid, '@broadcast' ) || false !== strpos( $jid, '@newsletter' ) || ( flxc_is_group_jid( $jid ) && ! flxc_setting( 'show_groups' ) ) || false === strpos( $jid, '@' ) ) {
			continue;
		}
		$records = flxc_evo_find_messages( $conn_id, $jid, $per_chat );
		if ( is_wp_error( $records ) || ! $records ) {
			continue;
		}
		// @lid sohbeti: telefon jid'ini mesaj anahtarından bul, aynı kişiye iki sohbet açılmasın.
		$main = $jid;
		foreach ( $records as $rec ) {
			$r = flxc_resolve_jid( (array) ( $rec['key'] ?? array() ) );
			if ( $r && '@lid' !== substr( $r, -4 ) ) {
				$main = $r;
				break;
			}
		}
		$chat = flxc_ensure_chat( $conn_id, $main, array( 'push_name' => (string) ( $c['pushName'] ?? '' ) ) );
		if ( ! $chat || empty( $chat['id'] ) ) {
			continue;
		}
		if ( $main !== $jid ) {
			flxc_remember_alias( $chat, $jid );
		}
		if ( ! empty( $c['profilePicUrl'] ) && empty( $chat['avatar'] ) ) {
			flxc_update_chat( $chat['id'], array( 'avatar' => esc_url_raw( $c['profilePicUrl'] ), 'avatar_at' => time() ) );
		}
		foreach ( array_reverse( $records ) as $rec ) {
			if ( is_array( $rec ) && flxc_ingest_message( $conn_id, $rec, array( 'history' => true, 'chat' => $chat ) ) ) {
				++$n_msgs;
			}
		}
		$meta         = flxc_decode( ( flxc_get_chat( $chat['id'] ) ?: $chat )['meta'] );
		$meta['hist'] = array( 'page' => 1, 'done' => count( $records ) < $per_chat, 'per' => $per_chat );
		flxc_update_chat( $chat['id'], array( 'meta' => flxc_json( $meta ) ) );
		++$n_chats;
	}
	return array( 'chats' => $n_chats, 'messages' => $n_msgs );
}

/**
 * Sohbet açılınca / yukarı kaydırınca Evolution'dan bir sonraki geçmiş sayfasını çeker.
 * Telefon jid'i ve bilinen @lid kimliği ayrı ayrı sorgulanır (Evolution mesajı hangisiyle kaydettiyse).
 * @return int|WP_Error yeni eklenen mesaj sayısı
 */
function flxc_fetch_chat_history( $chat, $per = 50 ) {
	$meta = flxc_decode( $chat['meta'] );
	$hist = (array) ( $meta['hist'] ?? array() );
	if ( ! empty( $hist['done'] ) || flxc_is_group_jid( $chat['jid'] ) && ! flxc_setting( 'show_groups' ) ) {
		return 0;
	}
	if ( is_wp_error( flxc_connection_config( $chat['conn'] ) ) ) {
		return 0;
	}
	// Aynı anda iki istek aynı sayfayı çekmesin.
	$lock = 'flxc_hist_' . $chat['id'];
	if ( get_transient( $lock ) ) {
		return 0;
	}
	set_transient( $lock, 1, 30 );
	$per   = max( 10, (int) ( $hist['per'] ?? $per ) );
	$page  = (int) ( $hist['page'] ?? 0 ) + 1;
	$added = 0;
	$done  = true;
	$err   = null;
	foreach ( flxc_chat_jids( $chat ) as $jid ) {
		$pages   = 0;
		$records = flxc_evo_find_messages( $chat['conn'], $jid, $per, $page, $pages );
		if ( is_wp_error( $records ) ) {
			$err = $records;
			continue;
		}
		foreach ( array_reverse( $records ) as $rec ) {
			if ( ! is_array( $rec ) ) {
				continue;
			}
			$wa = (string) ( $rec['key']['id'] ?? '' );
			if ( $wa && flxc_find_message_by_wa( $chat['conn'], $wa ) ) {
				continue;
			}
			if ( flxc_ingest_message( $chat['conn'], $rec, array( 'history' => true, 'chat' => $chat ) ) ) {
				++$added;
			}
		}
		if ( count( $records ) >= $per && $page < max( 1, $pages ) ) {
			$done = false;
		}
	}
	delete_transient( $lock );
	if ( $err && ! $added && 1 === $page ) {
		return $err;
	}
	$fresh        = flxc_get_chat( $chat['id'] );
	$meta         = flxc_decode( $fresh ? $fresh['meta'] : $chat['meta'] );
	$meta['hist'] = array( 'page' => $page, 'done' => $done, 'per' => $per, 'at' => time() );
	flxc_update_chat( $chat['id'], array( 'meta' => flxc_json( $meta ) ) );
	return $added;
}
