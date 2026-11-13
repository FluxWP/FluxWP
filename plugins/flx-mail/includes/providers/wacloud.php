<?php
/**
 * WhatsApp · Cloud API (Meta, resmi). QR yok: numara Meta'da eklenir, burada Phone Number ID + kalıcı token girilir.
 * Gelen mesaj ve tikler Meta webhook'u ile flxi/v1/wacloud/{id}/webhook ucuna gelir; Evolution biçimine çevrilip
 * `flxi_whatsapp_event` eylemiyle yayılır (Flux Chat Evolution olaylarını aynı biçimde bekliyor).
 *
 * Yok: grup mesajlaşması, mesaj düzenleme, geçmiş içe aktarma (findChats/findMessages). 24 saat penceresi dışında
 * yalnız onaylı şablon gider.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flxi_connection_types',
	function ( $types ) {
		$types['wacloud'] = array(
			'label'   => __( 'WhatsApp · Cloud API (Meta)', 'flx-mail' ),
			'channel' => 'whatsapp',
			'help'    => __( 'Meta\'s official WhatsApp Cloud API; no extra server. From Meta Business › WhatsApp Manager copy the Phone Number ID and WhatsApp Business account (WABA) ID, and from Business settings › System users copy a permanent access token. After saving, paste the webhook URL from "Setup" into the Meta app.', 'flx-mail' ),
			'fields'  => array(
				array( 'key' => 'phone_id', 'label' => __( 'Phone Number ID', 'flx-mail' ), 'type' => 'text', 'required' => true, 'placeholder' => '1234567890' ),
				array( 'key' => 'waba_id', 'label' => __( 'WhatsApp Business account ID (WABA)', 'flx-mail' ), 'type' => 'text', 'placeholder' => '1234567890', 'help' => __( 'Needed for the template list.', 'flx-mail' ) ),
				array( 'key' => 'token', 'label' => __( 'Permanent access token (System User token)', 'flx-mail' ), 'type' => 'password', 'required' => true ),
				array( 'key' => 'app_secret', 'label' => __( 'App Secret', 'flx-mail' ), 'type' => 'password', 'required' => true, 'help' => __( 'Verifies that incoming webhooks come from Meta. Incoming events are rejected until it is entered.', 'flx-mail' ) ),
				array( 'key' => 'graph', 'label' => __( 'Graph API version', 'flx-mail' ), 'type' => 'text', 'default' => 'v23.0', 'placeholder' => 'v23.0' ),
				array( 'key' => 'link_preview', 'label' => __( 'Show link preview', 'flx-mail' ), 'type' => 'checkbox', 'default' => 1 ),
				array( 'key' => 'delay', 'label' => __( 'Delay between messages in bulk send (sec)', 'flx-mail' ), 'type' => 'number', 'default' => 1 ),
			),
			'send'    => 'flxi_wacloud_send',
			'test'    => 'flxi_wacloud_test',
			'cloud'   => true,
		);
		return $types;
	}
);

/** Kayıtta bağlantının webhook doğrulama anahtarı (ilk kayıtta üretilir, gizli değil ama tahmin edilemez). */
add_action(
	'flxi_connection_saved',
	function ( $id ) {
		$c = flxi_get_connection( $id );
		if ( $c && 'wacloud' === $c['type'] && ! get_option( 'flxi_wac_verify_' . (int) $id ) ) {
			update_option( 'flxi_wac_verify_' . (int) $id, wp_generate_password( 24, false, false ), false );
		}
	}
);
add_action(
	'flxi_connection_deleted',
	function ( $id ) {
		delete_option( 'flxi_wac_verify_' . (int) $id );
		delete_option( 'flxi_wac_in_' . (int) $id );
		delete_option( 'flxi_wac_hook_' . (int) $id );
	}
);

function flxi_wacloud_verify_token( $id ) {
	return (string) get_option( 'flxi_wac_verify_' . (int) $id, '' );
}

function flxi_wacloud_webhook_url( $id ) {
	return rest_url( 'flxi/v1/wacloud/' . (int) $id . '/webhook' );
}

function flxi_wacloud_request( array $cfg, $method, $path, $body = null, $timeout = 25 ) {
	$ver  = preg_match( '/^v\d+\.\d+$/', (string) ( $cfg['graph'] ?? '' ) ) ? $cfg['graph'] : 'v23.0';
	$args = array(
		'headers' => array( 'Authorization' => 'Bearer ' . (string) $cfg['token'], 'Content-Type' => 'application/json' ),
		'timeout' => $timeout,
	);
	if ( null !== $body ) {
		$args['body'] = flxi_json( $body );
	}
	return flxi_http( $method, apply_filters( 'flxi_wacloud_base', 'https://graph.facebook.com/' ) . $ver . '/' . ltrim( $path, '/' ), $args );
}

/** Graph hata gövdesinden okunur metin (+ bilinen kodlar Türkçe). */
function flxi_wacloud_error( $r ) {
	if ( $r['error'] ) {
		return __( 'Couldn\'t reach Meta:', 'flx-mail' ) . ' ' . $r['error'];
	}
	$e    = is_array( $r['json'] ) ? (array) ( $r['json']['error'] ?? array() ) : array();
	$code = (int) ( $e['code'] ?? 0 );
	$map  = array(
		131047 => __( '24-hour window closed: the person hasn\'t written in the last 24 hours. Only an approved template can be sent.', 'flx-mail' ),
		131026 => __( 'Message not delivered: the number has no WhatsApp or has blocked you.', 'flx-mail' ),
		190    => __( 'Access token is invalid or expired.', 'flx-mail' ),
		131030 => __( 'This number isn\'t on the test list (Meta test account).', 'flx-mail' ),
		131056 => __( 'Messages sent to the same person too fast; wait a little.', 'flx-mail' ),
		132001 => __( 'Template not found or not approved in this language.', 'flx-mail' ),
	);
	$msg = $map[ $code ] ?? (string) ( $e['error_data']['details'] ?? ( $e['message'] ?? '' ) );
	return 'Cloud API ' . ( $code ? '#' . $code : 'HTTP ' . $r['code'] ) . ( $msg ? ': ' . mb_substr( $msg, 0, 300 ) : '' );
}

/** Son 24 saatte kişiden mesaj geldi mi (webhook'tan kaydedilir). */
function flxi_wacloud_window( $id, $phone ) {
	$in = (array) get_option( 'flxi_wac_in_' . (int) $id, array() );
	$ts = (int) ( $in[ preg_replace( '/\D/', '', (string) $phone ) ] ?? 0 );
	return array(
		'open'    => $ts > time() - DAY_IN_SECONDS,
		'last_in' => $ts,
		'closes'  => $ts ? $ts + DAY_IN_SECONDS : 0,
	);
}

function flxi_wacloud_note_inbound( $id, $phone, $ts ) {
	$in   = (array) get_option( 'flxi_wac_in_' . (int) $id, array() );
	$cut  = time() - 2 * DAY_IN_SECONDS;
	$in   = array_filter(
		$in,
		function ( $t ) use ( $cut ) {
			return (int) $t > $cut;
		}
	);
	$key        = preg_replace( '/\D/', '', (string) $phone );
	$in[ $key ] = max( (int) ( $in[ $key ] ?? 0 ), (int) $ts );
	update_option( 'flxi_wac_in_' . (int) $id, $in, false );
}

/**
 * Ham Graph mesaj gövdesi gönderir (diğer eklentiler için: alıntı, tepki, medya id'si…).
 *
 * @param array $payload messaging_product ve to dışındaki alanlar; ör. ['type'=>'reaction','reaction'=>[...]].
 * @return array ok, external_id, error, retry, response
 */
function flxi_wacloud_post( $id_or_cfg, $to, array $payload ) {
	$cfg = is_array( $id_or_cfg ) ? $id_or_cfg : ( flxi_get_connection( (int) $id_or_cfg )['config'] ?? array() );
	if ( empty( $cfg['phone_id'] ) || empty( $cfg['token'] ) ) {
		return array( 'ok' => false, 'error' => __( 'Cloud API connection is incomplete.', 'flx-mail' ), 'retry' => false );
	}
	// Okundu / "yazıyor" ({status: read, message_id, typing_indicator}) alıcısız gider; diğerlerinde to + recipient_type.
	$is_status = isset( $payload['status'] );
	$to        = $is_status ? '' : preg_replace( '/\D/', '', (string) $to );
	$body      = array_merge( array( 'messaging_product' => 'whatsapp' ), $is_status ? array() : array( 'recipient_type' => 'individual' ), $to ? array( 'to' => $to ) : array(), $payload );
	$r    = flxi_wacloud_request( $cfg, 'POST', rawurlencode( (string) $cfg['phone_id'] ) . '/messages', $body );
	$resp = array( 'http' => $r['code'], 'body' => is_array( $r['json'] ) ? $r['json'] : $r['body'] );
	if ( ! $r['ok'] ) {
		$code = (int) ( $r['json']['error']['code'] ?? 0 );
		return array( 'ok' => false, 'error' => flxi_wacloud_error( $r ), 'retry' => $r['code'] >= 500 || 0 === $r['code'] || in_array( $code, array( 4, 80007, 130429, 131056 ), true ), 'response' => $resp, 'code' => $code );
	}
	return array( 'ok' => true, 'external_id' => (string) ( $r['json']['messages'][0]['id'] ?? '' ), 'response' => $resp );
}

/**
 * flxi_send_message sürücüsü. $msg['template'] = ['name', 'lang', 'params' => [...]] verilirse şablon gider
 * (24 saat penceresi dışında tek yol).
 */
function flxi_wacloud_send( $config, $msg, $conn = array() ) {
	if ( flxi_is_wa_group( $msg['recipient'] ) ) {
		return flxi_wa_group_unsupported();
	}
	$phone = flxi_normalize_phone( $msg['recipient'] );
	if ( ! $phone ) {
		/* translators: %s: telefon */
		return array( 'ok' => false, 'error' => sprintf( __( 'Invalid phone: %s', 'flx-mail' ), $msg['recipient'] ), 'retry' => false );
	}
	if ( ! empty( $msg['template']['name'] ) ) {
		$t    = $msg['template'];
		$tpl  = array( 'name' => (string) $t['name'], 'language' => array( 'code' => (string) ( $t['lang'] ?? 'tr' ) ) );
		$prms = array_values( (array) ( $t['params'] ?? array() ) );
		if ( $prms ) {
			$tpl['components'] = array(
				array(
					'type'       => 'body',
					'parameters' => array_map(
						function ( $p ) {
							return array( 'type' => 'text', 'text' => (string) $p );
						},
						$prms
					),
				),
			);
		}
		return flxi_wacloud_post( $config, $phone, array( 'type' => 'template', 'template' => $tpl ) );
	}
	$r = flxi_wacloud_post(
		$config,
		$phone,
		array(
			'type' => 'text',
			'text' => array( 'body' => (string) $msg['body'], 'preview_url' => ! empty( $config['link_preview'] ) ),
		)
	);
	if ( $r['ok'] && ! empty( $msg['media'] ) ) {
		$mk  = function_exists( 'flxi_media_kind' ) ? flxi_media_kind( $msg['media'], $msg['file_name'] ?? '' ) : 'document';
		$obj = array( 'link' => (string) $msg['media'] );
		if ( 'document' === $mk ) {
			$obj['filename'] = ! empty( $msg['file_name'] ) ? $msg['file_name'] : basename( (string) wp_parse_url( $msg['media'], PHP_URL_PATH ) );
		}
		$m                    = flxi_wacloud_post( $config, $phone, array( 'type' => $mk, $mk => $obj ) );
		$r['response']['pdf'] = array( 'ok' => $m['ok'], 'error' => $m['error'] ?? '' );
	}
	return $r;
}

/** Numara bilgisi: display_phone_number, verified_name, quality_rating, name_status, throughput. */
function flxi_wacloud_phone_info( array $cfg ) {
	$r = flxi_wacloud_request( $cfg, 'GET', rawurlencode( (string) $cfg['phone_id'] ) . '?fields=display_phone_number,verified_name,quality_rating,name_status,code_verification_status,platform_type,throughput' );
	if ( ! $r['ok'] ) {
		return new WP_Error( 'flxi_wac', flxi_wacloud_error( $r ) );
	}
	return (array) $r['json'];
}

function flxi_wacloud_test( $config, $to, $conn ) {
	$info = flxi_wacloud_phone_info( $config );
	if ( is_wp_error( $info ) ) {
		return array( 'ok' => false, 'error' => $info->get_error_message() );
	}
	if ( $to ) {
		/* translators: %s: site adı */
		$r = flxi_wacloud_send( $config, array( 'recipient' => $to, 'body' => sprintf( __( 'Test message · %s 🌱', 'flx-mail' ), get_bloginfo( 'name' ) ), 'media' => '', 'file_name' => '' ), $conn );
		if ( ! $r['ok'] && 131047 === (int) ( $r['code'] ?? 0 ) ) {
			$r['error'] .= ' ' . __( 'To test, first send a message from that number to this line.', 'flx-mail' );
		}
		return $r;
	}
	return array( 'ok' => true, 'response' => $info );
}

/** Onaylı şablonlar (WABA gerekir). */
function flxi_wacloud_templates( $id ) {
	$c = flxi_get_connection( (int) $id );
	if ( ! $c || 'wacloud' !== $c['type'] ) {
		return new WP_Error( 'flxi_wac', __( 'Cloud API connection not found.', 'flx-mail' ), array( 'status' => 404 ) );
	}
	if ( empty( $c['config']['waba_id'] ) ) {
		return new WP_Error( 'flxi_wac', __( 'WABA ID is required for templates.', 'flx-mail' ), array( 'status' => 400 ) );
	}
	$r = flxi_wacloud_request( $c['config'], 'GET', rawurlencode( (string) $c['config']['waba_id'] ) . '/message_templates?fields=name,language,status,category,components&limit=100' );
	if ( ! $r['ok'] ) {
		return new WP_Error( 'flxi_wac', flxi_wacloud_error( $r ), array( 'status' => 400 ) );
	}
	$out = array();
	foreach ( (array) ( $r['json']['data'] ?? array() ) as $t ) {
		$body = '';
		foreach ( (array) ( $t['components'] ?? array() ) as $cmp ) {
			if ( 'BODY' === ( $cmp['type'] ?? '' ) ) {
				$body = (string) ( $cmp['text'] ?? '' );
			}
		}
		$out[] = array(
			'name'     => sanitize_text_field( (string) ( $t['name'] ?? '' ) ),
			'lang'     => sanitize_text_field( (string) ( $t['language'] ?? '' ) ),
			'status'   => sanitize_key( (string) ( $t['status'] ?? '' ) ),
			'category' => sanitize_key( (string) ( $t['category'] ?? '' ) ),
			'body'     => sanitize_textarea_field( $body ),
			'params'   => preg_match_all( '/\{\{\d+\}\}/', $body ),
		);
	}
	return $out;
}

/** Durum (flxi_wa_state ile aynı biçim): numara bilgisi okunabiliyorsa open. */
function flxi_wacloud_state( $c ) {
	$info = flxi_wacloud_phone_info( $c['config'] );
	if ( is_wp_error( $info ) ) {
		return array( 'state' => 'error', 'error' => $info->get_error_message() );
	}
	return array(
		'state'   => 'open',
		'me'      => (string) ( $info['display_phone_number'] ?? '' ),
		'name'    => sanitize_text_field( (string) ( $info['verified_name'] ?? '' ) ),
		'quality' => sanitize_key( (string) ( $info['quality_rating'] ?? '' ) ),
		'error'   => '',
	);
}

/* ---------------- Webhook ---------------- */

/** Meta'nın GET doğrulaması (hub.challenge) ve POST olayları. */
function flxi_wacloud_webhook( WP_REST_Request $req ) {
	$id = (int) $req['id'];
	$c  = flxi_get_connection( $id );
	if ( ! $c || 'wacloud' !== $c['type'] ) {
		return new WP_Error( 'flxi_wac', 'Not found', array( 'status' => 404 ) );
	}
	if ( 'GET' === $req->get_method() ) {
		$tok = flxi_wacloud_verify_token( $id );
		if ( 'subscribe' === $req->get_param( 'hub_mode' ) && $tok && hash_equals( $tok, (string) $req->get_param( 'hub_verify_token' ) ) ) {
			flxi_wacloud_hook_seen( $id, 'verify' );
			$res = new WP_REST_Response( null, 200 );
			$res->header( 'Content-Type', 'text/plain; charset=utf-8' );
			add_filter(
				'rest_pre_serve_request',
				function ( $served ) use ( $req ) {
					echo esc_html( preg_replace( '/[^0-9A-Za-z_-]/', '', (string) $req->get_param( 'hub_challenge' ) ) );
					return true;
				}
			);
			return $res;
		}
		return new WP_Error( 'flxi_wac', 'Forbidden', array( 'status' => 403 ) );
	}
	$secret = (string) ( $c['config']['app_secret'] ?? '' );
	if ( '' === $secret ) {
		flxi_wacloud_hook_seen( $id, 'no_secret' );
		return new WP_Error( 'flxi_wac', 'App Secret not configured', array( 'status' => 403 ) );
	}
	$raw = (string) $req->get_body();
	$sig = (string) $req->get_header( 'x_hub_signature_256' );
	if ( ! $sig || ! hash_equals( 'sha256=' . hash_hmac( 'sha256', $raw, $secret ), $sig ) ) {
		flxi_wacloud_hook_seen( $id, 'bad_signature' );
		return new WP_Error( 'flxi_wac', 'Bad signature', array( 'status' => 401 ) );
	}
	$payload = json_decode( $raw, true );
	if ( ! is_array( $payload ) ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
	flxi_wacloud_hook_seen( $id, 'event' );
	do_action( 'flxi_wacloud_event', $id, $payload );
	foreach ( flxi_wacloud_to_evolution( $c, $payload ) as $ev ) {
		/**
		 * WhatsApp olayı, Evolution v2 webhook biçiminde (event: messages.upsert | messages.update).
		 *
		 * @param int   $id    İletişim bağlantı id'si.
		 * @param array $event Evolution webhook gövdesi.
		 */
		do_action( 'flxi_whatsapp_event', $id, $ev );
	}
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

function flxi_wacloud_hook_seen( $id, $kind ) {
	$h          = (array) get_option( 'flxi_wac_hook_' . (int) $id, array() );
	$h[ $kind ] = time();
	update_option( 'flxi_wac_hook_' . (int) $id, $h, false );
}

/** Medyayı indirip base64 verir (Evolution webhookBase64 gibi). Büyükse boş. */
function flxi_wacloud_media_base64( array $cfg, $media_id, $max = 10485760 ) {
	$r = flxi_wacloud_request( $cfg, 'GET', rawurlencode( (string) $media_id ) );
	if ( ! $r['ok'] || empty( $r['json']['url'] ) || (int) ( $r['json']['file_size'] ?? 0 ) > $max ) {
		return '';
	}
	$res = wp_remote_get( (string) $r['json']['url'], array( 'timeout' => 25, 'headers' => array( 'Authorization' => 'Bearer ' . (string) $cfg['token'] ), 'limit_response_size' => $max ) );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return '';
	}
	return base64_encode( (string) wp_remote_retrieve_body( $res ) ); // phpcs:ignore
}

/**
 * Cloud webhook gövdesi → Evolution v2 webhook olayları.
 *
 * @return array[]
 */
function flxi_wacloud_to_evolution( array $c, array $payload ) {
	$out  = array();
	$inst = 'wacloud-' . (int) $c['id'];
	foreach ( (array) ( $payload['entry'] ?? array() ) as $entry ) {
		foreach ( (array) ( $entry['changes'] ?? array() ) as $ch ) {
			$v = (array) ( $ch['value'] ?? array() );
			if ( (string) ( $v['metadata']['phone_number_id'] ?? '' ) !== (string) $c['config']['phone_id'] ) {
				continue; // Aynı uygulamadaki başka numara.
			}
			$names = array();
			foreach ( (array) ( $v['contacts'] ?? array() ) as $ct ) {
				$names[ (string) ( $ct['wa_id'] ?? '' ) ] = (string) ( $ct['profile']['name'] ?? '' );
			}
			foreach ( (array) ( $v['messages'] ?? array() ) as $m ) {
				$from = preg_replace( '/\D/', '', (string) ( $m['from'] ?? '' ) );
				$ts   = (int) ( $m['timestamp'] ?? time() );
				flxi_wacloud_note_inbound( $c['id'], $from, $ts );
				$type    = (string) ( $m['type'] ?? 'text' );
				$message = array();
				$mtype   = 'conversation';
				switch ( $type ) {
					case 'text':
						$message['conversation'] = (string) ( $m['text']['body'] ?? '' );
						break;
					case 'image':
					case 'video':
					case 'audio':
					case 'document':
					case 'sticker':
						$md    = (array) ( $m[ $type ] ?? array() );
						$mtype = $type . 'Message';
						$message[ $mtype ] = array_filter(
							array(
								'mimetype' => (string) ( $md['mime_type'] ?? '' ),
								'caption'  => (string) ( $md['caption'] ?? '' ),
								'fileName' => (string) ( $md['filename'] ?? '' ),
								'ptt'      => 'audio' === $type ? ! empty( $md['voice'] ) : null,
								'mediaId'  => (string) ( $md['id'] ?? '' ),
							),
							function ( $x ) {
								return null !== $x && '' !== $x;
							}
						);
						$b64 = ! empty( $md['id'] ) ? flxi_wacloud_media_base64( $c['config'], $md['id'] ) : '';
						if ( $b64 ) {
							$message['base64'] = $b64;
						}
						break;
					case 'reaction':
						$mtype                       = 'reactionMessage';
						$message['reactionMessage'] = array(
							'key'  => array( 'remoteJid' => $from . '@s.whatsapp.net', 'id' => (string) ( $m['reaction']['message_id'] ?? '' ) ),
							'text' => (string) ( $m['reaction']['emoji'] ?? '' ),
						);
						break;
					case 'location':
						$mtype                       = 'locationMessage';
						$message['locationMessage'] = array( 'degreesLatitude' => (float) ( $m['location']['latitude'] ?? 0 ), 'degreesLongitude' => (float) ( $m['location']['longitude'] ?? 0 ), 'name' => (string) ( $m['location']['name'] ?? '' ), 'address' => (string) ( $m['location']['address'] ?? '' ) );
						break;
					case 'button':
						$message['conversation'] = (string) ( $m['button']['text'] ?? '' );
						break;
					case 'interactive':
						$it                      = (array) ( $m['interactive'] ?? array() );
						$message['conversation'] = (string) ( $it['button_reply']['title'] ?? ( $it['list_reply']['title'] ?? '' ) );
						break;
					default:
						$message['conversation'] = '';
				}
				$data = array(
					'key'              => array( 'remoteJid' => $from . '@s.whatsapp.net', 'fromMe' => false, 'id' => (string) ( $m['id'] ?? '' ) ),
					'pushName'         => $names[ $from ] ?? '',
					'message'          => $message,
					'messageType'      => $mtype,
					'messageTimestamp' => $ts,
					'source'           => 'wacloud',
				);
				if ( ! empty( $m['context']['id'] ) ) {
					$data['contextInfo'] = array( 'stanzaId' => (string) $m['context']['id'] );
				}
				$out[] = array( 'event' => 'messages.upsert', 'instance' => $inst, 'data' => $data, 'date_time' => gmdate( 'c', $ts ), 'sender' => (string) $c['config']['phone_id'] );
			}
			$smap = array( 'sent' => 'SERVER_ACK', 'delivered' => 'DELIVERY_ACK', 'read' => 'READ', 'failed' => 'ERROR' );
			foreach ( (array) ( $v['statuses'] ?? array() ) as $s ) {
				$to    = preg_replace( '/\D/', '', (string) ( $s['recipient_id'] ?? '' ) );
				$out[] = array(
					'event'    => 'messages.update',
					'instance' => $inst,
					'data'     => array(
						'keyId'     => (string) ( $s['id'] ?? '' ),
						'remoteJid' => $to . '@s.whatsapp.net',
						'fromMe'    => true,
						'status'    => $smap[ (string) ( $s['status'] ?? '' ) ] ?? strtoupper( (string) ( $s['status'] ?? '' ) ),
						'error'     => isset( $s['errors'][0] ) ? (string) ( $s['errors'][0]['title'] ?? '' ) : '',
						'pricing'   => (array) ( $s['pricing'] ?? array() ),
					),
				);
			}
		}
	}
	return $out;
}

/** Kurulum penceresi bilgisi: webhook adresi, doğrulama anahtarı, son olaylar, numara bilgisi. */
function flxi_wacloud_setup( $id ) {
	$c = flxi_get_connection( (int) $id );
	if ( ! $c || 'wacloud' !== $c['type'] ) {
		return new WP_Error( 'flxi_wac', __( 'Cloud API connection not found.', 'flx-mail' ), array( 'status' => 404 ) );
	}
	if ( ! flxi_wacloud_verify_token( $id ) ) {
		update_option( 'flxi_wac_verify_' . (int) $id, wp_generate_password( 24, false, false ), false );
	}
	$info = flxi_wacloud_phone_info( $c['config'] );
	return array(
		'webhook'    => flxi_wacloud_webhook_url( $id ),
		'verify'     => flxi_wacloud_verify_token( $id ),
		'hook'       => (array) get_option( 'flxi_wac_hook_' . (int) $id, array() ),
		'signed'     => '' !== (string) ( $c['config']['app_secret'] ?? '' ),
		'info'       => is_wp_error( $info ) ? null : $info,
		'error'      => is_wp_error( $info ) ? $info->get_error_message() : '',
		'has_waba'   => '' !== (string) ( $c['config']['waba_id'] ?? '' ),
		'open_chats' => count(
			array_filter(
				(array) get_option( 'flxi_wac_in_' . (int) $id, array() ),
				function ( $t ) {
					return (int) $t > time() - DAY_IN_SECONDS;
				}
			)
		),
	);
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'flxi/v1',
			'/wacloud/(?P<id>\d+)/webhook',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => 'flxi_wacloud_webhook',
				'permission_callback' => '__return_true', // Doğrulama anahtarı (GET) ve App Secret imzası (POST) ile korunur.
			)
		);
		$perm = function () {
			return flxi_can_manage();
		};
		register_rest_route( 'flxi/v1', '/wacloud/(?P<id>\d+)/setup', array( 'methods' => 'GET', 'callback' => function ( WP_REST_Request $r ) {
			return flxi_wacloud_setup( (int) $r['id'] );
		}, 'permission_callback' => $perm ) );
		register_rest_route( 'flxi/v1', '/wacloud/(?P<id>\d+)/templates', array( 'methods' => 'GET', 'callback' => function ( WP_REST_Request $r ) {
			return flxi_wacloud_templates( (int) $r['id'] );
		}, 'permission_callback' => $perm ) );
	}
);
