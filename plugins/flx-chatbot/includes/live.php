<?php
/**
 * Canlı destek: temsilciye devir, sıra, temsilci cevabı, kapatma, puanlama, mesaj bırakma,
 * e-posta bildirimleri ve potansiyel müşteri kaydı (Flux CRM).
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sohbeti temsilciye devreder.
 *
 * @param array  $conv    Sohbet.
 * @param string $by      visitor | bot | auto.
 * @param string $summary Asistanın temsilciye notu.
 * @return string Yeni durum ('' = devredilemedi).
 */
function flxcb_handoff( $conv, $by = 'visitor', $summary = '' ) {
	$target = flxcb_handoff_target();
	if ( '' === $target || ! $conv ) {
		return '';
	}
	if ( in_array( $conv['status'], array( 'queued', 'agent' ), true ) ) {
		return $conv['status'];
	}
	$online = flxcb_is_online();
	$status = $online ? 'queued' : 'offline';
	flxcb_update_conversation(
		(int) $conv['id'],
		array(
			'status'    => $status,
			'queued_ts' => flxcb_now_ms(),
		)
	);
	if ( '' !== trim( (string) $summary ) ) {
		flxcb_set_conv_meta( (int) $conv['id'], array( 'summary' => mb_substr( $summary, 0, 600 ) ) );
	}
	flxcb_event(
		(int) $conv['id'],
		$online ? 'queued' : 'offline',
		$online ? __( 'Connecting you to an agent…', 'flx-chatbot' ) : flxcb_text( 'offline_text' )
	);
	$conv = flxcb_get_conversation( (int) $conv['id'] );
	if ( 'flx-chat' === $target ) {
		flxcb_fc_push( $conv, $summary );
	}
	if ( 'inbox' === $target || ! $online ) {
		flxcb_notify_team( $conv, $online ? 'handoff' : 'offline' );
	}
	do_action( 'flxcb_handoff', $conv, $by, $target );
	return $status;
}

/**
 * Temsilci cevabı (Flux Chat'ten ya da eklentinin gelen kutusundan).
 *
 * @param array $file { path, name, mime, size } yükleme klasöründeki dosya.
 * @return int mesaj id
 */
function flxcb_agent_reply( $conv, $user_id, $text, array $file = array(), $ext_id = 0 ) {
	if ( ! $conv ) {
		return 0;
	}
	$user_id = (int) $user_id;
	if ( 'agent' !== $conv['status'] || ( $user_id && (int) $conv['assigned_to'] !== $user_id ) ) {
		$upd = array( 'status' => 'agent' );
		if ( $user_id ) {
			$upd['assigned_to'] = $user_id;
		}
		flxcb_update_conversation( (int) $conv['id'], $upd );
		if ( $user_id && (int) $conv['assigned_to'] !== $user_id ) {
			/* translators: %s: agent first name */
			flxcb_event( (int) $conv['id'], 'joined', sprintf( __( '%s joined the chat', 'flx-chatbot' ), flxcb_user_name( $user_id ) ) );
		}
	}
	$id = flxcb_add_message(
		array(
			'conv_id'   => (int) $conv['id'],
			'role'      => 'agent',
			'type'      => $file ? ( 0 === strpos( (string) $file['mime'], 'image/' ) ? 'image' : 'file' ) : 'text',
			'body'      => (string) $text,
			'file_path' => (string) ( $file['path'] ?? '' ),
			'file_name' => (string) ( $file['name'] ?? '' ),
			'file_mime' => (string) ( $file['mime'] ?? '' ),
			'file_size' => (int) ( $file['size'] ?? 0 ),
			'user_id'   => $user_id,
			'ext_id'    => (int) $ext_id,
		)
	);
	// Cevap veren temsilci önceki mesajları okumuştur.
	global $wpdb;
	$last_in = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(id) FROM ' . flxcb_table( 'messages' ) . " WHERE conv_id = %d AND role = 'visitor'", (int) $conv['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	flxcb_update_conversation( (int) $conv['id'], array( 'agent_read' => $last_in, 'unread' => 0 ) );
	delete_transient( 'flxcb_typing_a_' . (int) $conv['id'] );
	flxcb_maybe_mail_visitor( (int) $conv['id'] );
	return $id;
}

/** Sohbeti kapatır. */
function flxcb_close( $conv, $by = 'visitor' ) {
	if ( ! $conv || 'closed' === $conv['status'] ) {
		return;
	}
	flxcb_update_conversation( (int) $conv['id'], array( 'status' => 'closed', 'unread' => 0 ) );
	flxcb_event( (int) $conv['id'], 'closed', 'visitor' === $by ? __( 'You ended the chat.', 'flx-chatbot' ) : __( 'The chat has ended.', 'flx-chatbot' ) );
	if ( 'visitor' === $by && $conv['flxc_chat'] && function_exists( 'flxc_system_message' ) && function_exists( 'flxc_get_chat' ) ) {
		$chat = flxc_get_chat( (int) $conv['flxc_chat'] );
		if ( $chat ) {
			flxc_system_message( $chat, __( 'The visitor ended the site chat.', 'flx-chatbot' ) );
		}
	}
	do_action( 'flxcb_closed', flxcb_get_conversation( (int) $conv['id'] ), $by );
}

/** Kapanan sohbet yeniden yazılınca açılır. */
function flxcb_reopen( $conv ) {
	if ( ! $conv || 'closed' !== $conv['status'] ) {
		return $conv;
	}
	$status = 'live' === flxcb_setting( 'mode' ) ? 'queued' : 'bot';
	flxcb_update_conversation(
		(int) $conv['id'],
		array(
			'status'    => $status,
			'rating'    => 0,
			'queued_ts' => 'queued' === $status ? flxcb_now_ms() : 0,
		)
	);
	return flxcb_get_conversation( (int) $conv['id'] );
}

/** Potansiyel müşteri: Flux CRM'e kişi ve olay. */
function flxcb_save_lead( $conv_id, $note = '' ) {
	$conv = flxcb_get_conversation( $conv_id );
	if ( ! $conv || ( '' === (string) $conv['email'] && '' === (string) $conv['phone'] ) ) {
		return 0;
	}
	$cid = 0;
	if ( class_exists( 'FLXCRM_Contacts' ) && method_exists( 'FLXCRM_Contacts', 'upsert' ) ) {
		try {
			$cid = (int) FLXCRM_Contacts::upsert(
				array(
					'name'   => (string) $conv['name'],
					'email'  => (string) $conv['email'],
					'phone'  => (string) $conv['phone'],
					'source' => 'flx-chatbot',
					'link'   => array( 'chatbot', (int) $conv['id'] ),
				)
			);
			if ( $cid && class_exists( 'FLXCRM_Events' ) ) {
				FLXCRM_Events::add(
					$cid,
					'note',
					array(
						/* translators: %d: conversation number */
						'title' => sprintf( __( 'Site chat #%d', 'flx-chatbot' ), (int) $conv['id'] ),
						'uniq'  => 'chatbot:conv:' . (int) $conv['id'],
						'data'  => array(
							'text' => '' !== $note ? $note : (string) $conv['page_title'],
							'url'  => flxcb_inbox_url( (int) $conv['id'] ),
						),
					)
				);
			}
		} catch ( Throwable $e ) {
			$cid = 0;
		}
	}
	if ( $cid ) {
		flxcb_set_conv_meta( (int) $conv['id'], array( 'crm' => $cid ) );
	}
	do_action( 'flxcb_lead', $conv, $note, $cid );
	return $cid;
}

/** Ekibe e-posta: yeni devir ya da mesai dışı mesaj. */
function flxcb_notify_team( $conv, $kind ) {
	$to = trim( (string) flxcb_setting( 'notify_email' ) );
	$to = '' !== $to ? $to : (string) get_option( 'admin_email' );
	if ( ! is_email( $to ) || get_transient( 'flxcb_mail_team_' . (int) $conv['id'] ) ) {
		return;
	}
	set_transient( 'flxcb_mail_team_' . (int) $conv['id'], 1, 10 * MINUTE_IN_SECONDS );
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$subj = 'offline' === $kind
		/* translators: 1: site name, 2: visitor */
		? sprintf( __( '[%1$s] Message from site chat: %2$s', 'flx-chatbot' ), $site, flxcb_conv_title( $conv ) )
		/* translators: 1: site name, 2: visitor */
		: sprintf( __( '[%1$s] Site chat waiting for an agent: %2$s', 'flx-chatbot' ), $site, flxcb_conv_title( $conv ) );
	$link = flxcb_conv_admin_link( $conv );
	$body = flxcb_transcript( $conv, 12 ) . "\n\n" . __( 'To reply:', 'flx-chatbot' ) . ' ' . $link;
	$headers = array();
	if ( is_email( (string) $conv['email'] ) ) {
		$headers[] = 'Reply-To: ' . $conv['email'];
	}
	wp_mail( $to, $subj, $body, $headers );
}

/** Ziyaretçi sayfada değilse temsilci cevaplarını e-postayla gönderir (en sık 3 dakikada bir). */
function flxcb_maybe_mail_visitor( $conv_id ) {
	$conv = flxcb_get_conversation( $conv_id );
	if ( ! $conv || ! flxcb_setting( 'email_replies' ) || ! is_email( (string) $conv['email'] ) ) {
		return;
	}
	$now = flxcb_now_ms();
	if ( $now - (int) $conv['seen_ts'] < 60000 || $now - (int) $conv['mailed_ts'] < 180000 ) {
		return;
	}
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxcb_table( 'messages' ) . " WHERE conv_id = %d AND role = 'agent' AND id > %d ORDER BY id ASC LIMIT 20", (int) $conv['id'], (int) $conv['visitor_read'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $rows ) {
		return;
	}
	$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$lines = array();
	foreach ( $rows as $m ) {
		$lines[] = flxcb_user_name( (int) $m['user_id'] ) . ': ' . ( '' !== $m['file_name'] ? '[' . $m['file_name'] . '] ' : '' ) . $m['body'];
	}
	$url  = '' !== (string) $conv['page_url'] ? add_query_arg( 'flxcb', 'open', $conv['page_url'] ) : home_url( '/?flxcb=open' );
	/* translators: %s: site name */
	$subj = sprintf( __( '%s: you have a reply to your chat', 'flx-chatbot' ), $site );
	$body = implode( "\n\n", $lines ) . "\n\n" . __( 'To continue the chat, open the site in the same browser:', 'flx-chatbot' ) . ' ' . $url;
	if ( wp_mail( (string) $conv['email'], $subj, $body ) ) {
		flxcb_update_conversation( (int) $conv['id'], array( 'mailed_ts' => $now ) );
	}
}

/** Sohbetin yönetim adresi: Flux Chat'e devredildiyse orası, değilse eklentinin gelen kutusu. */
function flxcb_conv_admin_link( $conv ) {
	return $conv['flxc_chat'] && flxcb_flux_chat_active() ? admin_url( 'admin.php?page=flx-chat&chat=' . (int) $conv['flxc_chat'] ) : flxcb_inbox_url( (int) $conv['id'] );
}

/** Düz metin döküm (son N mesaj). */
function flxcb_transcript( $conv, $limit = 50 ) {
	$out   = array();
	$out[] = flxcb_conv_title( $conv ) . ( '' !== (string) $conv['email'] ? ' <' . $conv['email'] . '>' : '' ) . ( '' !== (string) $conv['phone'] ? ' · ' . $conv['phone'] : '' );
	if ( '' !== (string) $conv['page_url'] ) {
		$out[] = $conv['page_title'] . ' ' . $conv['page_url'];
	}
	$sum = (string) flxcb_conv_meta( $conv, 'summary' );
	if ( '' !== $sum ) {
		$out[] = __( 'Assistant note:', 'flx-chatbot' ) . ' ' . $sum;
	}
	$out[] = '';
	foreach ( flxcb_messages( (int) $conv['id'], 0, $limit ) as $m ) {
		if ( 'system' === $m['role'] ) {
			continue;
		}
		$who   = 'visitor' === $m['role'] ? flxcb_conv_title( $conv ) : ( 'bot' === $m['role'] ? flxcb_bot_name() : flxcb_user_name( (int) $m['user_id'] ) );
		$out[] = $who . ': ' . ( '' !== $m['file_name'] ? '[' . $m['file_name'] . '] ' : '' ) . $m['body'];
	}
	return implode( "\n", $out );
}

/** Ziyaretçi dosyasını kaydeder. */
function flxcb_store_upload( array $f ) {
	$max = max( 1, (int) flxcb_setting( 'upload_mb' ) ) * MB_IN_BYTES;
	if ( empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) || (int) $f['size'] <= 0 ) {
		return new WP_Error( 'flxcb_file', __( 'The file could not be uploaded.', 'flx-chatbot' ), array( 'status' => 400 ) );
	}
	if ( (int) $f['size'] > $max ) {
		/* translators: %d: megabytes */
		return new WP_Error( 'flxcb_file', sprintf( __( 'Files can be up to %d MB.', 'flx-chatbot' ), (int) flxcb_setting( 'upload_mb' ) ), array( 'status' => 413 ) );
	}
	$check = wp_check_filetype_and_ext( $f['tmp_name'], (string) $f['name'] );
	$mime  = (string) ( $check['type'] ?? '' );
	if ( '' === $mime && function_exists( 'mime_content_type' ) ) {
		$mime = (string) mime_content_type( $f['tmp_name'] );
	}
	if ( ! isset( flxcb_allowed_mimes()[ $mime ] ) ) {
		return new WP_Error( 'flxcb_file', __( 'This file type can\'t be sent. Send an image, PDF or office document.', 'flx-chatbot' ), array( 'status' => 415 ) );
	}
	return flxcb_store_bytes( file_get_contents( $f['tmp_name'] ), $mime, sanitize_file_name( (string) $f['name'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}

/** Ham baytları yükleme klasörüne yazar. */
function flxcb_store_bytes( $bin, $mime, $name ) {
	$ext  = strtolower( pathinfo( (string) $name, PATHINFO_EXTENSION ) );
	$ext  = preg_match( '/^[a-z0-9]{1,5}$/', $ext ) ? $ext : 'bin';
	$path = gmdate( 'Ymd' ) . '-' . wp_generate_password( 20, false, false ) . '.' . $ext;
	// phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( false === file_put_contents( flxcb_upload_dir() . '/' . $path, $bin ) ) {
		return new WP_Error( 'flxcb_file', __( 'Could not save the file.', 'flx-chatbot' ), array( 'status' => 500 ) );
	}
	return array(
		'path' => $path,
		'name' => '' !== (string) $name ? mb_substr( (string) $name, 0, 190 ) : $path,
		'mime' => (string) $mime,
		'size' => strlen( (string) $bin ),
	);
}
