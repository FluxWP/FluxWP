<?php
defined( 'ABSPATH' ) || exit;

/** API'ye gidecek alıcı: bireysel sohbette telefon, grupta jid. */
function flxc_chat_number( $chat ) {
	$phone = (string) $chat['phone'];
	// @lid sohbette telefon alanına gizli kimliğin rakamları yazılmış olabilir; o bir numara değildir.
	if ( flxc_is_group_jid( $chat['jid'] ) || '' === $phone || $phone === flxc_lid_digits( $chat['jid'] ) ) {
		return $chat['jid'];
	}
	return $phone;
}

/** Hazır cevap değişkenlerini doldurur. */
function flxc_fill_vars( $text, $chat, $user_id = 0 ) {
	$title = flxc_chat_title( $chat );
	$named = '' !== (string) $chat['name'] || '' !== (string) $chat['push_name'];
	$full  = $named ? $title : '';
	$first = $full ? preg_split( '/\s+/u', trim( $full ) )[0] : '';
	$vars  = array(
		'{first_name}' => $first,
		'{name}'       => $full,
		'{phone}'      => flxc_format_phone( $chat['phone'] ),
		'{agent}'      => flxc_user_name( $user_id ? $user_id : get_current_user_id() ),
		'{site}'       => home_url( '/' ),
		'{brand}'      => flxc_brand_name(),
		'{date}'       => flx_date( time() ),
	);
	$vars = apply_filters( 'flxc_reply_vars', $vars, $chat, $user_id );
	$out  = strtr( (string) $text, $vars );
	// Ad bilinmiyorsa "Merhaba , ..." gibi boşlukları topla.
	$out = preg_replace( '/ +([,.!?])/u', '$1', $out );
	return preg_replace( '/ {2,}/', ' ', $out );
}

/**
 * Mesaj gönderir (ya da dahili not ekler).
 *
 * @param array $chat sohbet satırı
 * @param array $args type (text|image|video|document|audio|note), body, quoted_id, user_id, source,
 *                    file (tmp yol), mime, name, preview (metindeki linkin önizlemesi, varsayılan açık),
 *                    delay (ms; göndermeden önce karşı tarafta "yazıyor…" gösterilir)
 * @return array|WP_Error mesaj satırı
 */
function flxc_send( $chat, array $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'type'      => 'text',
			'body'      => '',
			'quoted_id' => 0,
			'user_id'   => get_current_user_id(),
			'source'    => 'agent',
			'file'      => '',
			'mime'      => '',
			'name'      => '',
			'retry_id'  => 0,
			'preview'   => true,
			'delay'     => 0,
			'allow_blocked' => false,
		)
	);
	$type = $args['type'];
	$body = (string) $args['body'];
	// 1.43.2 (Ahmet): asistanın hiçbir mesajında yasaklı emoji (🙏) gitmez; hatırlatıcı, kapanış, araç metinleri dahil.
	if ( 'text' === $type && 'bot' === $args['source'] && function_exists( 'flxc_bot_strip_banned' ) ) {
		$body         = flxc_bot_strip_banned( $body );
		$args['body'] = $body;
	}
	if ( 'text' === $type && 'bot' === $args['source'] && ( false !== strpos( $body, '```' ) || false !== stripos( $body, '<svg' ) ) && function_exists( 'flxc_widgets_strip' ) ) {
		$body = flxc_widgets_strip( $body );
		if ( '' === $body ) {
			return new WP_Error( 'flxc_empty', __( 'The message was only image code; it was not sent.', 'flx-chat' ) );
		}
		$args['body'] = $body;
	}

	if ( 'note' === $type ) {
		$id = flxc_insert_message(
			array(
				'chat_id'   => $chat['id'],
				'conn'      => $chat['conn'],
				'direction' => 'note',
				'type'      => 'text',
				'body'      => $body,
				'user_id'   => $args['user_id'],
				'source'    => 'note',
				'status'    => '',
			),
			$chat
		);
		return flxc_get_message( $id );
	}

	// Kara listedeki kişiye hiçbir mesaj gitmez (yalnız "artık yazmayacağız" onayı, aynı turda).
	if ( empty( $args['allow_blocked'] ) && function_exists( 'flxc_chat_blocked' ) && flxc_chat_blocked( $chat ) ) {
		return new WP_Error( 'flxc_blocked', __( 'This contact is blocklisted; no messages are sent. Remove them from the blocklist to write again.', 'flx-chat' ), array( 'status' => 403 ) );
	}

	$quoted_row = $args['quoted_id'] ? flxc_get_message( $args['quoted_id'] ) : null;
	if ( $quoted_row && ( (int) $quoted_row['chat_id'] !== (int) $chat['id'] || '' === $quoted_row['wa_id'] ) ) {
		$quoted_row = null;
	}

	$send_body = $body;
	if ( $args['user_id'] && flxc_setting( 'agent_signature' ) && 'agent' === $args['source'] && '' !== trim( $body ) ) {
		$send_body = '*' . flxc_user_name( $args['user_id'] ) . ":*\n" . $body;
	}

	$saved = null;
	if ( in_array( $type, array( 'image', 'video', 'document', 'audio' ), true ) ) {
		if ( $args['retry_id'] ) {
			$old   = flxc_get_message( $args['retry_id'] );
			$saved = $old && $old['media_path'] ? array( 'path' => $old['media_path'], 'size' => (int) $old['media_size'] ) : null;
		} elseif ( $args['file'] && is_readable( $args['file'] ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions
			$saved = flxc_save_media_binary( file_get_contents( $args['file'] ), $args['mime'], $args['name'] );
		}
		if ( ! $saved ) {
			return new WP_Error( 'flxc_file', __( 'File could not be read.', 'flx-chat' ) );
		}
	}

	if ( $args['retry_id'] ) {
		$id = (int) $args['retry_id'];
		flxc_update_message( $id, array( 'status' => 'pending', 'error' => '' ) );
	} else {
		$id = flxc_insert_message(
			array(
				'chat_id'    => $chat['id'],
				'conn'       => $chat['conn'],
				'direction'  => 'out',
				'type'       => $type,
				'body'       => $body,
				'media_path' => $saved['path'] ?? '',
				'media_mime' => $args['mime'],
				'media_name' => $args['name'],
				'media_size' => $saved['size'] ?? 0,
				'quoted_id'  => $quoted_row ? (int) $quoted_row['id'] : 0,
				'quoted_wa'  => $quoted_row ? $quoted_row['wa_id'] : '',
				'status'     => 'pending',
				'user_id'    => $args['user_id'],
				'source'     => $args['source'],
				'meta'       => 'audio' === $type ? array( 'ptt' => 1 ) : ( $args['preview'] ? array() : array( 'nolp' => 1 ) ),
			),
			$chat
		);
	}
	if ( ! $id ) {
		return new WP_Error( 'flxc_db', __( 'Message could not be saved.', 'flx-chat' ) );
	}

	$number = flxc_chat_number( $chat );
	$quoted = null;
	if ( $quoted_row ) {
		$quoted = array(
			'key'     => array( 'id' => $quoted_row['wa_id'] ),
			'message' => array( 'conversation' => (string) $quoted_row['body'] ),
		);
	}
	if ( 'text' === $type ) {
		$r = flxc_evo_send_text( $chat['conn'], $number, $send_body, $quoted, (bool) $args['preview'], (int) $args['delay'] );
	} else {
		// phpcs:ignore WordPress.WP.AlternativeFunctions
		$b64 = base64_encode( file_get_contents( flxc_media_dir() . '/' . $saved['path'] ) );
		if ( 'audio' === $type ) {
			$r = flxc_evo_send_audio( $chat['conn'], $number, $b64 );
		} else {
			$r = flxc_evo_send_media( $chat['conn'], $number, $type, $args['mime'], $b64, $args['name'], $send_body, $quoted );
		}
	}

	if ( $r['ok'] ) {
		$wa  = (string) ( $r['json']['key']['id'] ?? '' );
		$cur = flxc_get_message( $id );
		$upd = array( 'status' => flxc_status_rank( $cur['status'] ) > flxc_status_rank( 'sent' ) ? $cur['status'] : 'sent', 'error' => '' );
		if ( $wa && '' === $cur['wa_id'] ) {
			$upd['wa_id'] = $wa;
		}
		flxc_update_message( $id, $upd );
		if ( 'agent' === $args['source'] && $args['user_id'] && ! (int) $chat['assigned_to'] && apply_filters( 'flxc_auto_assign_on_reply', true ) ) {
			flxc_update_chat( $chat['id'], array( 'assigned_to' => (int) $args['user_id'] ) );
		}
		do_action( 'flxc_message_sent', $id, $chat );
	} else {
		$err = $r['error'];
		if ( '@lid' === substr( flxc_chat_number( $chat ), -4 ) && ( false !== strpos( $r['body'] ?? '', 'exists' ) || 400 === (int) ( $r['code'] ?? 0 ) ) ) {
			$ver = flxc_evo_version( $chat['conn'] );
			$err = '' !== $ver
				/* translators: %s: Evolution API version */
				? sprintf( __( 'This contact writes from a hidden WhatsApp number (@lid) and Evolution API (version %s) could not send to this address. Update the Evolution API server to 2.3 or later; if the contact\'s real number arrives, the plugin sends there.', 'flx-chat' ), $ver )
				: __( 'This contact writes from a hidden WhatsApp number (@lid) and Evolution API could not send to this address. Update the Evolution API server to 2.3 or later; if the contact\'s real number arrives, the plugin sends there.', 'flx-chat' );
		}
		flxc_update_message( $id, array( 'status' => 'failed', 'error' => mb_substr( $err, 0, 500 ) ) );
	}
	return flxc_get_message( $id );
}

/**
 * Diğer eklentiler (yapay zekâ botu, bağış bildirimi vb.) için basit gönderim.
 *
 * @param int|string $chat_or_phone sohbet id ya da telefon
 * @param string     $text
 * @param array      $args conn, source (varsayılan 'api'), user_id
 * @return array|WP_Error
 */
function flxc_send_text( $chat_or_phone, $text, $args = array() ) {
	$args = wp_parse_args( $args, array( 'conn' => '', 'source' => 'api', 'user_id' => 0, 'name' => '' ) );
	if ( is_numeric( $chat_or_phone ) && (int) $chat_or_phone < 100000000 ) {
		$chat = flxc_get_chat( (int) $chat_or_phone );
	} else {
		$phone = flxc_normalize_phone( (string) $chat_or_phone );
		if ( ! $phone ) {
			return new WP_Error( 'flxc_phone', __( 'Invalid phone number.', 'flx-chat' ) );
		}
		$conn = $args['conn'] ? flxc_get_connection( $args['conn'] ) : flxc_default_connection();
		if ( ! $conn ) {
			return new WP_Error( 'flxc_conn', __( 'No WhatsApp connection.', 'flx-chat' ) );
		}
		$chat = flxc_ensure_chat( $conn['id'], $phone . '@s.whatsapp.net', array( 'name' => $args['name'] ) );
	}
	if ( ! $chat ) {
		return new WP_Error( 'flxc_chat', __( 'Chat not found.', 'flx-chat' ) );
	}
	return flxc_send(
		$chat,
		array(
			'type'    => 'text',
			'body'    => $text,
			'source'  => $args['source'],
			'user_id' => (int) $args['user_id'],
		)
	);
}

/**
 * Giriş / doğrulama kodu gibi panelde görünmemesi gereken metni doğrudan WhatsApp'a gönderir (1.32.0).
 * Sohbet açılmaz, mesaj kaydedilmez, telefondan dönen yankısı da kaydedilmez; asistan susturulmaz.
 * Örn. flxc_send_private_text( '+90532...', 'Giriş kodunuz: 123456', array( 'source' => 'flxapp-otp' ) ).
 *
 * @param string $phone
 * @param string $text
 * @param array  $args conn, source (yalnız kayıt/iz için)
 * @return true|WP_Error
 */
function flxc_send_private_text( $phone, $text, $args = array() ) {
	$args  = wp_parse_args( $args, array( 'conn' => '', 'source' => 'api' ) );
	$phone = flxc_normalize_phone( (string) $phone );
	if ( ! $phone ) {
		return new WP_Error( 'flxc_phone', __( 'Invalid phone number.', 'flx-chat' ) );
	}
	$conn = $args['conn'] ? flxc_get_connection( $args['conn'] ) : flxc_default_connection();
	if ( ! $conn ) {
		return new WP_Error( 'flxc_conn', __( 'No WhatsApp connection.', 'flx-chat' ) );
	}
	flxc_notify_remember( '', $text );
	$r = flxc_evo_send_text( $conn['id'], $phone, (string) $text, null, false );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	if ( empty( $r['ok'] ) ) {
		return new WP_Error( 'flxc_send', '' !== (string) ( $r['error'] ?? '' ) ? (string) $r['error'] : 'HTTP ' . (int) ( $r['code'] ?? 0 ) );
	}
	flxc_notify_remember( (string) ( $r['json']['key']['id'] ?? '' ), $text );
	do_action( 'flxc_private_text_sent', $phone, (string) $args['source'] );
	return true;
}
