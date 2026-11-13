<?php
defined( 'ABSPATH' ) || exit;

/**
 * Evolution API v2 istemcisi. Tüm çağrılar array( ok, code, json, body, error ) döner.
 */
function flxc_evo( $conn, $method, $path, $body = null, $timeout = 25 ) {
	if ( flxc_is_virtual_conn( $conn ) ) {
		/**
		 * 1.37.0: sanal kanal (site sohbeti) isteği. Evolution v2 yolu ve gövdesi aynen gelir
		 * (/message/sendText/{instance}, /message/sendMedia/{instance}, /chat/sendPresence/{instance}, /chat/markMessageAsRead/{instance} …).
		 * Kanal eklentisi array( ok, code, json, body, error ) döner; json.key.id mesajın kanal kimliği olur.
		 */
		$r = apply_filters( 'flxc_virtual_request', null, is_array( $conn ) ? (string) $conn['id'] : (string) $conn, $method, $path, $body );
		return is_array( $r ) ? wp_parse_args( $r, array( 'ok' => false, 'code' => 0, 'json' => null, 'body' => '', 'error' => '' ) ) : array( 'ok' => false, 'code' => 0, 'json' => null, 'body' => '', 'error' => __( 'This channel is not available right now.', 'flx-chat' ) );
	}
	$cfg = flxc_connection_config( $conn );
	if ( is_wp_error( $cfg ) ) {
		return array( 'ok' => false, 'code' => 0, 'json' => null, 'body' => '', 'error' => $cfg->get_error_message() );
	}
	if ( ! empty( $cfg['wacloud'] ) ) {
		return flxc_wacloud_evo( $conn, (int) $cfg['wacloud'], $method, $path, $body ); // 1.34.0: resmi Cloud API hattı
	}
	$url  = untrailingslashit( $cfg['base_url'] ) . str_replace( '{instance}', rawurlencode( $cfg['instance'] ), $path );
	$args = array(
		'method'  => $method,
		'timeout' => $timeout,
		'headers' => array(
			'Content-Type' => 'application/json',
			'apikey'       => $cfg['api_key'],
		),
	);
	if ( null !== $body ) {
		$args['body'] = flxc_json( $body );
	}
	$res = wp_remote_request( $url, $args );
	if ( is_wp_error( $res ) ) {
		return array( 'ok' => false, 'code' => 0, 'json' => null, 'body' => '', 'error' => $res->get_error_message() );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$raw  = (string) wp_remote_retrieve_body( $res );
	$json = json_decode( $raw, true );
	$err  = '';
	if ( $code < 200 || $code >= 300 ) {
		$err = flxc_evo_error_text( $code, $json, $raw );
	}
	return array(
		'ok'    => '' === $err,
		'code'  => $code,
		'json'  => is_array( $json ) ? $json : null,
		'body'  => mb_substr( $raw, 0, 2000 ),
		'error' => $err,
	);
}

function flxc_evo_error_text( $code, $json, $raw ) {
	$detail = '';
	if ( is_array( $json ) ) {
		$m = $json['response']['message'] ?? ( $json['message'] ?? ( $json['error'] ?? '' ) );
		$detail = is_array( $m ) ? wp_json_encode( $m, JSON_UNESCAPED_UNICODE ) : (string) $m;
	}
	if ( false !== strpos( $detail, '"exists":false' ) ) {
		return __( 'This number is not on WhatsApp.', 'flx-chat' );
	}
	if ( 401 === $code || 403 === $code ) {
		return __( 'Evolution API key is invalid.', 'flx-chat' );
	}
	if ( 404 === $code ) {
		return __( 'Evolution instance not found or offline.', 'flx-chat' );
	}
	return 'Evolution HTTP ' . $code . ( $detail ? ': ' . mb_substr( $detail, 0, 300 ) : ( $raw ? ': ' . mb_substr( wp_strip_all_tags( $raw ), 0, 200 ) : '' ) );
}

function flxc_evo_send_text( $conn, $number, $text, $quoted = null, $preview = true, $delay = 0 ) {
	$body = array(
		'number'      => $number,
		'text'        => $text,
		'linkPreview' => (bool) $preview,
	);
	if ( $delay > 0 ) {
		$body['delay'] = (int) $delay; // Evolution bu süre "yazıyor…" gösterip sonra gönderir
	}
	if ( $quoted ) {
		$body['quoted'] = $quoted;
	}
	return flxc_evo( $conn, 'POST', '/message/sendText/{instance}', $body );
}

/**
 * @param string $mediatype image|video|document
 * @param string $media base64 (data: öneki olmadan) ya da https adres
 */
function flxc_evo_send_media( $conn, $number, $mediatype, $mime, $media, $file_name = '', $caption = '', $quoted = null ) {
	$body = array(
		'number'    => $number,
		'mediatype' => $mediatype,
		'mimetype'  => $mime,
		'media'     => $media,
		'fileName'  => $file_name,
		'caption'   => $caption,
	);
	if ( $quoted ) {
		$body['quoted'] = $quoted;
	}
	return flxc_evo( $conn, 'POST', '/message/sendMedia/{instance}', $body, 60 );
}

function flxc_evo_send_audio( $conn, $number, $base64 ) {
	return flxc_evo(
		$conn,
		'POST',
		'/message/sendWhatsAppAudio/{instance}',
		array(
			'number'   => $number,
			'audio'    => $base64,
			'encoding' => true,
		),
		60
	);
}

function flxc_evo_send_reaction( $conn, $remote_jid, $from_me, $wa_id, $emoji ) {
	return flxc_evo(
		$conn,
		'POST',
		'/message/sendReaction/{instance}',
		array(
			'key'      => array( 'remoteJid' => $remote_jid, 'fromMe' => (bool) $from_me, 'id' => $wa_id ),
			'reaction' => $emoji,
		)
	);
}

/** 1.38.0: WhatsApp "herkesten sil" (Evolution v2 DELETE /chat/deleteMessageForEveryone). */
function flxc_evo_delete_for_everyone( $conn, $remote_jid, $wa_id, $participant = '' ) {
	$body = array(
		'id'        => (string) $wa_id,
		'remoteJid' => (string) $remote_jid,
		'fromMe'    => true,
	);
	if ( '' !== $participant ) {
		$body['participant'] = (string) $participant;
	}
	return flxc_evo( $conn, 'DELETE', '/chat/deleteMessageForEveryone/{instance}', $body, 15 );
}

/** 1.45.0: WhatsApp "düzenle" (Evolution v2 POST /chat/updateMessage). Yalnız kendi gönderdiğimiz mesaj, ~15 dk içinde. */
function flxc_evo_edit_message( $conn, $remote_jid, $wa_id, $text ) {
	return flxc_evo(
		$conn,
		'POST',
		'/chat/updateMessage/{instance}',
		array(
			'number' => (string) $remote_jid,
			'key'    => array( 'remoteJid' => (string) $remote_jid, 'fromMe' => true, 'id' => (string) $wa_id ),
			'text'   => (string) $text,
		),
		15
	);
}

function flxc_evo_mark_read( $conn, $remote_jid, array $wa_ids ) {
	$list = array();
	foreach ( $wa_ids as $id ) {
		$list[] = array( 'remoteJid' => $remote_jid, 'fromMe' => false, 'id' => $id );
	}
	return flxc_evo( $conn, 'POST', '/chat/markMessageAsRead/{instance}', array( 'readMessages' => $list ), 10 );
}

function flxc_evo_presence( $conn, $number, $presence = 'composing', $delay = 3000 ) {
	return flxc_evo(
		$conn,
		'POST',
		'/chat/sendPresence/{instance}',
		array( 'number' => $number, 'presence' => $presence, 'delay' => $delay ),
		8
	);
}

/**
 * "Yazıyor…" göstergesini cevap hazırlanırken açık tutar: Evolution sendPresence isteği
 * `delay` boyunca bekleyip sonra "paused" gönderir. İsteği gönderip cevabını beklemeyiz
 * (curl_multi ile yolda bırakılır); bizim mesajımız gidince WhatsApp göstergeyi zaten kapatır.
 * curl yoksa kısa, bekleyen bir istekle yetinilir.
 */
function flxc_evo_typing( $conn, $number, $ms = 20000 ) {
	static $multi = null, $handles = array();
	if ( 'stop' === $number ) {
		foreach ( $handles as $h ) {
			curl_multi_remove_handle( $multi, $h );
			curl_close( $h );
		}
		$handles = array();
		return true;
	}
	if ( flxc_is_virtual_conn( $conn ) ) {
		return flxc_evo_presence( $conn, $number, 'composing', $ms )['ok'];
	}
	$cfg = flxc_connection_config( $conn );
	if ( is_wp_error( $cfg ) || '' === (string) $number ) {
		return false;
	}
	if ( ! function_exists( 'curl_multi_init' ) || ! empty( $cfg['wacloud'] ) ) {
		return flxc_evo_presence( $conn, $number, 'composing', 1500 )['ok'];
	}
	$url  = untrailingslashit( $cfg['base_url'] ) . str_replace( '{instance}', rawurlencode( $cfg['instance'] ), '/chat/sendPresence/{instance}' );
	$body = flxc_json( array( 'number' => $number, 'presence' => 'composing', 'delay' => (int) $ms ) );
	$h    = curl_init( $url );
	curl_setopt_array(
		$h,
		array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_HTTPHEADER     => array( 'Content-Type: application/json', 'apikey: ' . $cfg['api_key'] ),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => (int) ceil( $ms / 1000 ) + 10,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_CAINFO         => ABSPATH . WPINC . '/certificates/ca-bundle.crt',
		)
	);
	if ( null === $multi ) {
		$multi = curl_multi_init();
	}
	curl_multi_add_handle( $multi, $h );
	$handles[] = $h;
	// İstek gövdesi karşıya ulaşana kadar sür (en fazla 3 sn), sonra bekleme.
	$until = microtime( true ) + 3;
	do {
		curl_multi_exec( $multi, $running );
		$info = curl_getinfo( $h );
		if ( (float) ( $info['size_upload'] ?? 0 ) >= strlen( $body ) || ! $running ) {
			break;
		}
		curl_multi_select( $multi, 0.05 );
	} while ( microtime( true ) < $until );
	return true;
}

function flxc_evo_profile_picture( $conn, $number ) {
	$r = flxc_evo( $conn, 'POST', '/chat/fetchProfilePictureUrl/{instance}', array( 'number' => $number ), 10 );
	return $r['ok'] ? (string) ( $r['json']['profilePictureUrl'] ?? '' ) : '';
}

/**
 * Numaralar WhatsApp'ta var mı? @return array number => array(exists, jid)
 */
function flxc_evo_check_numbers( $conn, array $numbers ) {
	$r   = flxc_evo( $conn, 'POST', '/chat/whatsappNumbers/{instance}', array( 'numbers' => array_values( $numbers ) ), 15 );
	$out = array();
	if ( ! $r['ok'] ) {
		return new WP_Error( 'flxc_check', $r['error'] );
	}
	foreach ( (array) $r['json'] as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$num         = flxc_jid_phone( $row['number'] ?? ( $row['jid'] ?? '' ) );
		$out[ $num ] = array(
			'exists' => ! empty( $row['exists'] ),
			'jid'    => (string) ( $row['jid'] ?? '' ),
		);
	}
	return $out;
}

/**
 * Evolution API sunucu sürümü (kök adres "version" döner). 12 saat önbellekte tutulur.
 * @param bool $refresh true: sunucuya sor; false: yalnız önbellek (sistem kontrolü dış istek atmaz)
 */
function flxc_evo_version( $conn, $refresh = null ) {
	$key = 'flxc_evo_ver_' . sanitize_key( $conn );
	$v   = get_transient( $key );
	if ( false !== $v && true !== $refresh ) {
		return (string) $v;
	}
	if ( false === $refresh ) {
		return '';
	}
	$r = flxc_evo( $conn, 'GET', '', null, 8 );
	$v = is_array( $r['json'] ) ? (string) ( $r['json']['version'] ?? '' ) : '';
	$v = preg_match( '/^v?(\d+(?:\.\d+){0,3})/', $v, $m ) ? $m[1] : '';
	set_transient( $key, $v, '' === $v ? HOUR_IN_SECONDS : 12 * HOUR_IN_SECONDS );
	return $v;
}

function flxc_evo_connection_state( $conn ) {
	$r     = flxc_evo( $conn, 'GET', '/instance/connectionState/{instance}', null, 10 );
	$state = $r['json']['instance']['state'] ?? ( $r['json']['state'] ?? '' );
	return array( 'ok' => $r['ok'], 'state' => (string) $state, 'error' => $r['error'] );
}

/** QR ile eşleştirme: instance bağlı değilse QR (base64 görsel) ve eşleştirme kodu döner. */
function flxc_evo_connect( $conn ) {
	$r = flxc_evo( $conn, 'GET', '/instance/connect/{instance}', null, 20 );
	return array(
		'ok'    => $r['ok'],
		'qr'    => (string) ( $r['json']['base64'] ?? '' ),
		'code'  => (string) ( $r['json']['pairingCode'] ?? '' ),
		'state' => (string) ( $r['json']['instance']['state'] ?? '' ),
		'error' => $r['error'],
	);
}

/** Webhook'u Evolution'a kaydeder. */
function flxc_evo_set_webhook( $conn ) {
	$events = array( 'MESSAGES_UPSERT', 'MESSAGES_UPDATE', 'MESSAGES_DELETE', 'SEND_MESSAGE', 'CONNECTION_UPDATE', 'PRESENCE_UPDATE', 'CONTACTS_UPSERT', 'CONTACTS_UPDATE' );
	return flxc_evo(
		$conn,
		'POST',
		'/webhook/set/{instance}',
		array(
			'webhook' => array(
				'enabled'         => true,
				'url'             => flxc_webhook_url( $conn ),
				'webhookByEvents' => false,
				'webhookBase64'   => true,
				'events'          => $events,
			),
		)
	);
}

function flxc_evo_find_webhook( $conn ) {
	return flxc_evo( $conn, 'GET', '/webhook/find/{instance}', null, 10 );
}

/** Medyayı base64 olarak indirir (webhook'ta base64 gelmediyse). */
function flxc_evo_media_base64( $conn, $wa_id ) {
	$r = flxc_evo(
		$conn,
		'POST',
		'/chat/getBase64FromMediaMessage/{instance}',
		array(
			'message'      => array( 'key' => array( 'id' => $wa_id ) ),
			'convertToMp4' => false,
		),
		60
	);
	if ( ! $r['ok'] ) {
		return new WP_Error( 'flxc_media', $r['error'] );
	}
	return array(
		'base64'   => (string) ( $r['json']['base64'] ?? '' ),
		'mimetype' => (string) ( $r['json']['mimetype'] ?? '' ),
		'fileName' => (string) ( $r['json']['fileName'] ?? '' ),
	);
}

function flxc_evo_find_chats( $conn ) {
	$r = flxc_evo( $conn, 'POST', '/chat/findChats/{instance}', new stdClass(), 40 );
	if ( ! $r['ok'] ) {
		return new WP_Error( 'flxc_chats', $r['error'] );
	}
	return (array) $r['json'];
}

function flxc_evo_find_messages( $conn, $remote_jid, $limit = 50, $page = 1, &$pages = null ) {
	$where = $remote_jid ? array( 'key' => array( 'remoteJid' => $remote_jid ) ) : new stdClass();
	$r     = flxc_evo(
		$conn,
		'POST',
		'/chat/findMessages/{instance}',
		array(
			'where'  => $where,
			'page'   => max( 1, (int) $page ),
			'offset' => max( 1, (int) $limit ),
		),
		40
	);
	if ( ! $r['ok'] ) {
		return new WP_Error( 'flxc_msgs', $r['error'] );
	}
	$j     = (array) $r['json'];
	$pages = 1;
	if ( isset( $j['messages']['records'] ) ) {
		$pages        = (int) ( $j['messages']['pages'] ?? 1 );
		$pages        = $pages ? $pages : 0;
		$GLOBALS['flxc_evo_total'] = (int) ( $j['messages']['total'] ?? 0 );
		return (array) $j['messages']['records'];
	}
	if ( isset( $j['messages'] ) && is_array( $j['messages'] ) && isset( $j['messages'][0] ) ) {
		return $j['messages'];
	}
	return isset( $j[0] ) ? $j : array();
}

/** Evolution veritabanında kayıtlı toplam mesaj sayısı (geçmişin tutulup tutulmadığını anlamak için). */
function flxc_evo_message_count( $conn ) {
	$GLOBALS['flxc_evo_total'] = null;
	$recs = flxc_evo_find_messages( $conn, '', 1 );
	if ( is_wp_error( $recs ) ) {
		return $recs;
	}
	return null !== $GLOBALS['flxc_evo_total'] ? (int) $GLOBALS['flxc_evo_total'] : count( $recs );
}
