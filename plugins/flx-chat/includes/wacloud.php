<?php
defined( 'ABSPATH' ) || exit;

/*
 * Resmi WhatsApp Cloud API hattı (1.34.0). Hesap Flux Mail'de `wacloud` türünde eklenir;
 * Meta webhook'u İletişim'e gelir, Evolution v2 olayına çevrilip `flxi_whatsapp_event` ile yayılır.
 * Gönderimde flxc_evo() Evolution yollarını burada Graph gövdesine çevirir (flxi_wacloud_post).
 * Cloud'da olmayanlar (QR, grup, geçmiş, numara kontrolü, profil fotoğrafı, düzenleme) hata döner.
 */

/** Flux Mail'deki Cloud API türleri. */
function flxc_wacloud_type( $type ) {
	return in_array( (string) $type, (array) apply_filters( 'flxc_wacloud_types', array( 'wacloud' ) ), true );
}

/** Bağlantı Cloud API hattıysa Flux Mail bağlantı id'si, değilse 0. */
function flxc_conn_wacloud( $conn ) {
	$cfg = flxc_connection_config( $conn );
	return is_wp_error( $cfg ) ? 0 : (int) ( $cfg['wacloud'] ?? 0 );
}

/** Gelen olaylar: İletişim'in Cloud webhook'u bu hatlara Evolution gövdesiyle düşer. */
add_action(
	'flxi_whatsapp_event',
	function ( $flxi_id, $event ) {
		if ( ! is_array( $event ) || ! function_exists( 'flxi_get_channel_connection' ) ) {
			return;
		}
		$c = flxi_get_channel_connection( 'whatsapp', (int) $flxi_id );
		if ( ! $c || (int) $c['id'] !== (int) $flxi_id || ! flxc_wacloud_type( $c['type'] ?? '' ) ) {
			return; // Evolution / Flux WPAPI hatları kendi webhook'umuzdan gelir.
		}
		foreach ( flxc_connections() as $conn ) {
			if ( 'flxi' !== $conn['source'] || (int) $conn['provider_id'] !== (int) $flxi_id ) {
				continue;
			}
			try {
				flxc_handle_event( $conn['id'], $event );
			} catch ( Throwable $e ) {
				flxc_log_error( 'wacloud: ' . $e->getMessage() );
			}
			update_option( 'flxc_last_webhook_' . $conn['id'], time(), false );
		}
	},
	10,
	2
);

/** Graph için alıcı: yalnız rakam. @lid ve grup adresine Cloud API gönderemez. */
function flxc_wacloud_to( $number ) {
	$n = (string) $number;
	if ( '@lid' === substr( $n, -4 ) || '@g.us' === substr( $n, -5 ) ) {
		return '';
	}
	$at = strpos( $n, '@' );
	return preg_replace( '/\D+/', '', false === $at ? $n : substr( $n, 0, $at ) );
}

function flxc_wacloud_unsupported() {
	return array(
		'ok'    => false,
		'code'  => 501,
		'json'  => null,
		'body'  => '',
		'error' => __( 'This action is not available on an official WhatsApp Cloud API line.', 'flx-chat' ),
	);
}

/** flxi_wacloud_post() sonucunu flxc_evo() biçimine çevirir. */
function flxc_wacloud_result( $r ) {
	if ( is_wp_error( $r ) ) {
		return array( 'ok' => false, 'code' => 0, 'json' => null, 'body' => '', 'error' => $r->get_error_message() );
	}
	$r   = (array) $r;
	$ok  = ! empty( $r['ok'] );
	$err = $ok ? '' : (string) ( $r['error'] ?? '' );
	if ( ! $ok && 131047 === (int) ( $r['code'] ?? 0 ) ) {
		$err = __( 'WhatsApp 24-hour rule: the contact has not written in the last 24 hours, so the Cloud API will not send a free-form message; only an approved template can be sent. Try again once they write.', 'flx-chat' );
	}
	if ( ! $ok && '' === $err ) {
		$err = 'Cloud API ' . (int) ( $r['code'] ?? 0 );
	}
	return array(
		'ok'    => $ok,
		'code'  => $ok ? 200 : (int) ( $r['code'] ?? 0 ),
		'json'  => $ok ? array( 'key' => array( 'id' => (string) ( $r['external_id'] ?? '' ) ) ) : null,
		'body'  => '',
		'error' => $err,
	);
}

function flxc_wacloud_post( $flxi_id, $to, array $payload ) {
	if ( ! function_exists( 'flxi_wacloud_post' ) ) {
		return array( 'ok' => false, 'code' => 0, 'json' => null, 'body' => '', 'error' => __( 'A Cloud API line needs Flux Mail 1.10.0 or later.', 'flx-chat' ) );
	}
	if ( '' === $to ) {
		return array( 'ok' => false, 'code' => 400, 'json' => null, 'body' => '', 'error' => __( 'The Cloud API cannot send to this address (hidden number or group).', 'flx-chat' ) );
	}
	return flxc_wacloud_result( flxi_wacloud_post( (int) $flxi_id, $to, $payload ) );
}

/**
 * Base64 medyayı Meta'nın indirebileceği geçici adrese koyar (1 saat). Dosya yükleme klasörü
 * dışarı kapalı olduğu için tahmin edilemez anahtarlı REST adresiyle verilir.
 */
function flxc_wacloud_temp_link( $base64, $mime, $ext ) {
	$bin = base64_decode( (string) preg_replace( '/^data:[^,]+,/', '', (string) $base64 ), true );
	if ( false === $bin || '' === $bin ) {
		return '';
	}
	$dir = flxc_media_dir() . '/wacloud-tmp';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	$key = strtolower( wp_generate_password( 32, false, false ) );
	$ext = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $ext ) );
	// phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( false === file_put_contents( $dir . '/' . $key . ( $ext ? '.' . $ext : '' ), $bin ) ) {
		return '';
	}
	set_transient( 'flxc_wct_' . $key, array( 'file' => $key . ( $ext ? '.' . $ext : '' ), 'mime' => (string) $mime ), HOUR_IN_SECONDS );
	wp_schedule_single_event( time() + HOUR_IN_SECONDS + 60, 'flxc_wacloud_temp_clean' );
	return rest_url( 'flx-chat/v1/wacloud-media/' . $key );
}

add_action(
	'flxc_wacloud_temp_clean',
	function () {
		foreach ( (array) glob( flxc_media_dir() . '/wacloud-tmp/*' ) as $f ) {
			if ( is_file( $f ) && time() - (int) filemtime( $f ) > HOUR_IN_SECONDS ) {
				wp_delete_file( $f );
			}
		}
	}
);

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'flx-chat/v1',
			'/wacloud-media/(?P<key>[a-z0-9]{32})',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function ( WP_REST_Request $req ) {
					$t    = get_transient( 'flxc_wct_' . $req['key'] );
					$path = is_array( $t ) ? flxc_media_dir() . '/wacloud-tmp/' . basename( (string) $t['file'] ) : '';
					if ( ! $path || ! is_file( $path ) ) {
						return new WP_REST_Response( array( 'ok' => false ), 404 );
					}
					nocache_headers();
					header( 'Content-Type: ' . ( '' !== $t['mime'] ? $t['mime'] : 'application/octet-stream' ) );
					header( 'Content-Length: ' . filesize( $path ) );
					readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
					exit;
				},
			)
		);
	}
);

/** Base64 sesin türü (Cloud API audio/ogg opus, mp3, m4a, aac, amr kabul eder). */
function flxc_wacloud_audio_type( $base64 ) {
	$head = (string) base64_decode( substr( (string) preg_replace( '/^data:[^,]+,/', '', (string) $base64 ), 0, 16 ) );
	if ( 0 === strpos( $head, 'OggS' ) ) {
		return array( 'audio/ogg', 'ogg' );
	}
	if ( 0 === strpos( $head, 'ID3' ) || "\xFF" === substr( $head, 0, 1 ) ) {
		return array( 'audio/mpeg', 'mp3' );
	}
	if ( false !== strpos( $head, 'ftyp' ) ) {
		return array( 'audio/mp4', 'm4a' );
	}
	return array( 'audio/ogg', 'ogg' );
}

/** Kişinin son gelen mesajının WhatsApp id'si ("yazıyor…" ve okundu bilgisi Cloud'da ona bağlı). */
function flxc_wacloud_last_in( $conn_id, $number ) {
	global $wpdb;
	$chat = flxc_find_chat_by_phone( $conn_id, flxc_normalize_phone( flxc_wacloud_to( $number ) ) );
	if ( ! $chat ) {
		return '';
	}
	return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT wa_id FROM ' . flxc_table( 'messages' ) . " WHERE chat_id = %d AND direction = 'in' AND wa_id <> '' ORDER BY id DESC LIMIT 1", (int) $chat['id'] ) ); // phpcs:ignore
}

/**
 * Evolution v2 yolunu Cloud API'ye çevirir. flxc_evo() ile aynı dönüş.
 *
 * @param string|array $conn Çağrı bağlantısı
 * @param int          $flxi_id Flux Mail bağlantı id'si
 */
function flxc_wacloud_evo( $conn, $flxi_id, $method, $path, $body ) {
	$conn_id = is_array( $conn ) ? $conn['id'] : (string) $conn;
	$op      = preg_replace( '#/\{instance\}.*$#', '', (string) $path );
	$b       = is_array( $body ) ? $body : array();
	switch ( $op ) {
		case '':
			return array( 'ok' => true, 'code' => 200, 'json' => array( 'version' => '' ), 'body' => '', 'error' => '' );

		case '/instance/connectionState':
			$st = function_exists( 'flxi_whatsapp_state' ) ? (array) flxi_whatsapp_state( (int) $flxi_id ) : array();
			$s  = (string) ( $st['state'] ?? '' );
			return array(
				'ok'    => 'error' !== $s && '' !== $s,
				'code'  => 200,
				'json'  => array( 'instance' => array( 'state' => 'open' === $s ? 'open' : 'close' ) ),
				'body'  => '',
				'error' => 'open' === $s ? '' : (string) ( $st['error'] ?? __( 'Could not reach the Cloud API account (İletişim › Connections).', 'flx-chat' ) ),
			);

		case '/instance/connect':
			return array( 'ok' => false, 'code' => 400, 'json' => null, 'body' => '', 'error' => __( 'A Cloud API line has no QR code; the account is set up in İletişim › Connections.', 'flx-chat' ) );

		case '/webhook/set':
		case '/webhook/find':
			// Meta webhook'u İletişim'e gelir; bize flxi_whatsapp_event ile ulaşır.
			return array( 'ok' => true, 'code' => 200, 'json' => array( 'url' => flxc_webhook_url( $conn_id ) ), 'body' => '', 'error' => '' );

		case '/message/sendText':
			$p = array(
				'type' => 'text',
				'text' => array( 'body' => (string) ( $b['text'] ?? '' ), 'preview_url' => ! empty( $b['linkPreview'] ) ),
			);
			if ( ! empty( $b['quoted']['key']['id'] ) ) {
				$p['context'] = array( 'message_id' => (string) $b['quoted']['key']['id'] );
			}
			return flxc_wacloud_post( $flxi_id, flxc_wacloud_to( $b['number'] ?? '' ), $p );

		case '/message/sendMedia':
			$kind  = in_array( $b['mediatype'] ?? '', array( 'image', 'video' ), true ) ? $b['mediatype'] : 'document';
			$media = (string) ( $b['media'] ?? '' );
			$name  = (string) ( $b['fileName'] ?? '' );
			$link  = preg_match( '#^https?://#', $media ) ? $media : flxc_wacloud_temp_link( $media, (string) ( $b['mimetype'] ?? '' ), pathinfo( $name, PATHINFO_EXTENSION ) );
			if ( '' === $link ) {
				return array( 'ok' => false, 'code' => 400, 'json' => null, 'body' => '', 'error' => __( 'The file could not be prepared.', 'flx-chat' ) );
			}
			$obj = array( 'link' => $link );
			if ( '' !== (string) ( $b['caption'] ?? '' ) ) {
				$obj['caption'] = (string) $b['caption'];
			}
			if ( 'document' === $kind && '' !== $name ) {
				$obj['filename'] = $name;
			}
			$p = array( 'type' => $kind, $kind => $obj );
			if ( ! empty( $b['quoted']['key']['id'] ) ) {
				$p['context'] = array( 'message_id' => (string) $b['quoted']['key']['id'] );
			}
			return flxc_wacloud_post( $flxi_id, flxc_wacloud_to( $b['number'] ?? '' ), $p );

		case '/message/sendWhatsAppAudio':
			list( $mime, $ext ) = flxc_wacloud_audio_type( $b['audio'] ?? '' );
			$link = preg_match( '#^https?://#', (string) ( $b['audio'] ?? '' ) ) ? (string) $b['audio'] : flxc_wacloud_temp_link( $b['audio'] ?? '', $mime, $ext );
			if ( '' === $link ) {
				return array( 'ok' => false, 'code' => 400, 'json' => null, 'body' => '', 'error' => __( 'The file could not be prepared.', 'flx-chat' ) );
			}
			return flxc_wacloud_post( $flxi_id, flxc_wacloud_to( $b['number'] ?? '' ), array( 'type' => 'audio', 'audio' => array( 'link' => $link ) ) );

		case '/message/sendReaction':
			return flxc_wacloud_post(
				$flxi_id,
				flxc_wacloud_to( $b['key']['remoteJid'] ?? '' ),
				array(
					'type'     => 'reaction',
					'reaction' => array( 'message_id' => (string) ( $b['key']['id'] ?? '' ), 'emoji' => (string) ( $b['reaction'] ?? '' ) ),
				)
			);

		case '/chat/markMessageAsRead':
			// Cloud'da en son mesajı okundu yapmak öncekileri de okundu yapar.
			$list = array_values( (array) ( $b['readMessages'] ?? array() ) );
			$last = $list ? end( $list ) : null;
			if ( ! $last || empty( $last['id'] ) ) {
				return array( 'ok' => true, 'code' => 200, 'json' => array(), 'body' => '', 'error' => '' );
			}
			return flxc_wacloud_post( $flxi_id, flxc_wacloud_to( $last['remoteJid'] ?? '' ), array( 'status' => 'read', 'message_id' => (string) $last['id'] ) );

		case '/chat/sendPresence':
			// "Yazıyor…" Cloud'da son gelen mesajı okundu yaparak açılır, 25 sn ya da cevap gidene kadar sürer.
			$to  = flxc_wacloud_to( $b['number'] ?? '' );
			$key = 'flxc_wctyp_' . md5( $conn_id . $to );
			if ( '' === $to || get_transient( $key ) ) {
				return array( 'ok' => true, 'code' => 200, 'json' => array(), 'body' => '', 'error' => '' );
			}
			$wa = flxc_wacloud_last_in( $conn_id, $to );
			if ( '' === $wa ) {
				return array( 'ok' => true, 'code' => 200, 'json' => array(), 'body' => '', 'error' => '' );
			}
			set_transient( $key, 1, 20 );
			return flxc_wacloud_post(
				$flxi_id,
				$to,
				array(
					'status'           => 'read',
					'message_id'       => $wa,
					'typing_indicator' => array( 'type' => 'text' ),
				)
			);
	}
	// findChats, findMessages, whatsappNumbers, fetchProfilePictureUrl, getBase64FromMediaMessage, group/*, updateMessage…
	return flxc_wacloud_unsupported();
}
