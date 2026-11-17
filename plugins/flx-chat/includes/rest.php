<?php
defined( 'ABSPATH' ) || exit;

/*
 * Bu eklentinin REST cevapları önbelleğe alınmaz. Hostinger/LiteSpeed GET cevabını saklayınca
 * yenilemede eski veri (ör. boş benchmark listesi) geliyordu. Kendi Cache-Control'ü olan uçlar (medya) korunur.
 */
add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		if ( 0 === strpos( $request->get_route(), '/flx-chat/v1/' ) ) {
			do_action( 'litespeed_control_set_nocache', 'flx-chat rest' );
		}
		return $result;
	},
	10,
	3
);
add_filter(
	'rest_post_dispatch',
	function ( $response, $server, $request ) {
		if ( 0 === strpos( $request->get_route(), '/flx-chat/v1/' ) && $response instanceof WP_REST_Response ) {
			$h = $response->get_headers();
			if ( empty( $h['Cache-Control'] ) ) {
				flx_rest_nocache( $response );
			}
		}
		return $response;
	},
	10,
	3
);

add_action( 'rest_api_init', 'flxc_register_rest' );

function flxc_register_rest() {
	$ns   = 'flx-chat/v1';
	$use  = function () {
		return flxc_can_use();
	};
	$mng  = function () {
		return flxc_can_manage();
	};
	$routes = array(
		array( '/bootstrap', 'GET', 'flxc_rest_bootstrap', $use ),
		array( '/sync', 'GET', 'flxc_rest_sync', $use ),
		array( '/chats', 'GET', 'flxc_rest_chats', $use ),
		array( '/chats', 'POST', 'flxc_rest_new_chat', $use ),
		array( '/chats/(?P<id>\d+)', 'GET', 'flxc_rest_chat', $use ),
		array( '/chats/(?P<id>\d+)', 'POST', 'flxc_rest_update_chat', $use ),
		array( '/chats/(?P<id>\d+)/messages', 'GET', 'flxc_rest_messages', $use ),
		array( '/chats/(?P<id>\d+)/messages', 'POST', 'flxc_rest_send', $use ),
		array( '/chats/(?P<id>\d+)/read', 'POST', 'flxc_rest_read', $use ),
		array( '/chats/(?P<id>\d+)/typing', 'POST', 'flxc_rest_typing', $use ),
		array( '/chats/(?P<id>\d+)/avatar', 'POST', 'flxc_rest_avatar', $use ),
		array( '/chats/(?P<id>\d+)/ai', 'POST', 'flxc_rest_ai', $use ),
		array( '/chats/(?P<id>\d+)/export', 'POST', 'flxc_rest_export', $use ),
		array( '/messages/(?P<id>\d+)/retry', 'POST', 'flxc_rest_retry', $use ),
		array( '/messages/(?P<id>\d+)/react', 'POST', 'flxc_rest_react', $use ),
		array( '/messages/(?P<id>\d+)/edit', 'POST', 'flxc_rest_edit_message', $use ),
		array( '/messages/(?P<id>\d+)', 'DELETE', 'flxc_rest_delete_message', $use ),
		array( '/media/(?P<id>\d+)', 'GET', 'flxc_rest_media', $use ),
		array( '/uploads', 'POST', 'flxc_rest_upload', $use ),
		array( '/uploads/(?P<token>[a-z0-9]+)', 'DELETE', 'flxc_rest_upload_delete', $use ),
		array( '/check-number', 'POST', 'flxc_rest_check_number', $use ),
		array( '/link-preview', 'GET', 'flxc_rest_link_preview', $use ),
		array( '/replies', 'GET', 'flxc_rest_replies', $use ),
		array( '/replies', 'POST', 'flxc_rest_save_reply', $use ),
		array( '/replies/(?P<id>\d+)', 'DELETE', 'flxc_rest_delete_reply', $use ),
		array( '/replies/(?P<id>\d+)/use', 'POST', 'flxc_rest_use_reply', $use ),
		array( '/admin/connections/(?P<conn>[a-z0-9]+)/test', 'POST', 'flxc_rest_conn_test', $mng ),
		array( '/admin/connections/(?P<conn>[a-z0-9]+)/webhook', 'POST', 'flxc_rest_conn_webhook', $mng ),
		array( '/admin/connections/(?P<conn>[a-z0-9]+)/qr', 'POST', 'flxc_rest_conn_qr', $mng ),
		array( '/admin/connections/(?P<conn>[a-z0-9]+)/import', 'POST', 'flxc_rest_conn_import', $mng ),
	);
	foreach ( $routes as $r ) {
		register_rest_route(
			$ns,
			$r[0],
			array(
				'methods'             => $r[1],
				'callback'            => $r[2],
				'permission_callback' => $r[3],
			)
		);
	}
}

function flxc_rest_chat_or_error( $id ) {
	$chat = flxc_get_chat( (int) $id );
	if ( ! $chat || ! flxc_user_can_see_chat( $chat ) ) {
		return new WP_Error( 'flxc_not_found', __( 'Chat not found.', 'flx-chat' ), array( 'status' => 404 ) );
	}
	return $chat;
}

function flxc_replies_for_js() {
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT * FROM ' . flxc_table( 'replies' ) . ' ORDER BY sort ASC, id ASC', ARRAY_A );
	$out  = array();
	foreach ( $rows as $r ) {
		$out[] = array(
			'id'       => (int) $r['id'],
			'shortcut' => $r['shortcut'],
			'title'    => $r['title'],
			'body'     => (string) $r['body'],
			'category' => $r['category'],
			'uses'     => (int) $r['uses'],
		);
	}
	return $out;
}

function flxc_labels_for_js() {
	$out = array();
	foreach ( (array) flxc_setting( 'labels' ) as $l ) {
		if ( ! empty( $l['id'] ) ) {
			$out[] = array( 'id' => (string) $l['id'], 'name' => (string) $l['name'], 'color' => (string) $l['color'] );
		}
	}
	return $out;
}

function flxc_rest_bootstrap() {
	return FLXC_Chat_Service::bootstrap();
}

function flxc_rest_chats( WP_REST_Request $req ) {
	return FLXC_Chat_Service::chats( $req );
}

/** Canlı güncelleme: istemci birkaç saniyede bir son gördüğü rev ile sorar. */
function flxc_rest_sync( WP_REST_Request $req ) {
	$out          = FLXC_Chat_Service::sync_payload( (int) $req->get_param( 'since' ), (int) $req->get_param( 'chat' ), (int) $req->get_param( 'after' ), 200, 200 );
	$out['nonce'] = wp_create_nonce( 'wp_rest' );
	return $out;
}

function flxc_rest_chat( WP_REST_Request $req ) {
	return FLXC_Chat_Service::chat( $req );
}

/** Kişi panelindeki ek bölümler (Flux Certificates sertifikaları vb.). */
function flxc_contact_sections( $chat ) {
	$sections = array();
	if ( $chat['phone'] && function_exists( 'flxs_find_person_by_phone' ) && function_exists( 'flxs_query_certificates' ) ) {
		$person = flxs_find_person_by_phone( $chat['phone'] );
		if ( $person ) {
			$res   = flxs_query_certificates( array( 'person_id' => (int) $person['id'], 'per_page' => 10 ) );
			$rows  = isset( $res['rows'] ) ? $res['rows'] : ( isset( $res['items'] ) ? $res['items'] : ( is_array( $res ) && isset( $res[0] ) ? $res : array() ) );
			$items = array();
			foreach ( $rows as $c ) {
				$c   = isset( $c['data'] ) && is_array( $c['data'] ) ? $c : flxs_prepare_certificate( $c );
				$url = flxs_certificate_url( $c );
				$items[] = array(
					'label'  => (string) ( $c['title'] ? $c['title'] : $c['number'] ),
					'sub'    => trim( $c['number'] . ' · ' . flx_date( strtotime( $c['created_at'] . ' UTC' ) ), ' ·' ),
					'url'    => $url,
					'insert' => $url,
					'cert'   => array(
						'id'    => (int) $c['id'],
						'name'  => function_exists( 'flxs_certificate_name' ) ? flxs_certificate_name( $c, $person ) : (string) $person['name'],
						'title' => (string) $c['title'],
						'url'   => $url,
					),
				);
			}
			if ( $items ) {
				$sections[] = array(
					'id'    => 'flxs',
					'title' => __( 'Certificates', 'flx-chat' ),
					'items' => $items,
				);
			}
		}
	}
	/**
	 * Kişi paneline bölüm ekleyin: array( id, title, items => array( label, sub, url, insert ) ).
	 * insert: tıklanınca mesaj kutusuna eklenecek metin.
	 */
	return apply_filters( 'flxc_contact_sections', $sections, $chat );
}

function flxc_rest_new_chat( WP_REST_Request $req ) {
	return FLXC_Chat_Service::create( $req );
}

function flxc_rest_check_number( WP_REST_Request $req ) {
	return FLXC_Chat_Service::check_number( $req );
}

function flxc_rest_update_chat( WP_REST_Request $req ) {
	return FLXC_Chat_Service::update( $req );
}


/** Sohbet akışına sistem satırı (atama, kapatma). Son mesaj önizlemesini değiştirmez. */
function flxc_system_message( $chat, $text, array $meta = array() ) {
	global $wpdb;
	$wpdb->insert(
		flxc_table( 'messages' ),
		array(
			'chat_id'    => (int) $chat['id'],
			'conn'       => $chat['conn'],
			'direction'  => 'system',
			'type'       => 'text',
			'body'       => $text,
			'user_id'    => get_current_user_id(),
			'source'     => 'system',
			'ts'         => (int) floor( microtime( true ) * 1000 ),
			'rev'        => flxc_rev(),
			'created_at' => flxc_now(),
			'meta'       => $meta ? flxc_json( $meta ) : '{}',
		)
	);
}

function flxc_rest_messages( WP_REST_Request $req ) {
	return FLXC_Message_Service::list_for( $req );
}


function flxc_rest_send( WP_REST_Request $req ) {
	return FLXC_Message_Service::send( $req );
}


/**
 * Yüklenen dosyayı denetler: boyut, yasak uzantı, gerçek MIME, mesaj türü.
 *
 * @return array|WP_Error type, mime, name
 */
function flxc_upload_check( $f, $name, $type ) {
	$max = (int) apply_filters( 'flxc_max_upload', 16 * MB_IN_BYTES );
	if ( $f['size'] > $max ) {
		/* translators: %s: size */
		return new WP_Error( 'flxc_big', sprintf( __( 'File is too large (max %s).', 'flx-chat' ), size_format( $max ) ), array( 'status' => 400 ) );
	}
	$name = sanitize_file_name( '' !== $name ? $name : (string) $f['name'] );
	$mime = (string) $f['type'];
	$chk  = wp_check_filetype_and_ext( $f['tmp_name'], $name );
	if ( ! empty( $chk['type'] ) ) {
		$mime = $chk['type'];
	}
	$mime = $mime ? $mime : 'application/octet-stream';
	if ( preg_match( '/\.(php\d?|phtml|phar|js|html?|svg)$/i', $name ) ) {
		return new WP_Error( 'flxc_type', __( 'This file type cannot be sent.', 'flx-chat' ), array( 'status' => 400 ) );
	}
	if ( 'audio' !== $type ) {
		if ( 0 === strpos( $mime, 'image/' ) && 'image/svg+xml' !== $mime ) {
			$type = 'image';
		} elseif ( 0 === strpos( $mime, 'video/' ) ) {
			$type = 'video';
		} else {
			$type = 'document';
		}
	} elseif ( ! $mime ) {
		$mime = 'audio/ogg';
	}
	return array( 'type' => $type, 'mime' => $mime, 'name' => $name );
}

/** Ek kartı için ön yükleme: dosya geçici klasöre alınır, token döner (1 saat geçerli, yalnız yükleyen kullanır). */
function flxc_rest_upload( WP_REST_Request $req ) {
	$files = $req->get_file_params();
	if ( empty( $files['file'] ) || ! empty( $files['file']['error'] ) ) {
		return new WP_Error( 'flxc_file', __( 'File could not be uploaded.', 'flx-chat' ), array( 'status' => 400 ) );
	}
	$chk = flxc_upload_check( $files['file'], (string) $req->get_param( 'name' ), '' );
	if ( is_wp_error( $chk ) ) {
		return $chk;
	}
	$dir = flxc_media_dir() . '/up-tmp';
	wp_mkdir_p( $dir );
	flxc_upload_gc( $dir );
	$token = strtolower( wp_generate_password( 24, false, false ) );
	$file  = $dir . '/' . $token . '.' . flxc_mime_ext( $chk['mime'], $chk['name'] );
	// phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! @copy( $files['file']['tmp_name'], $file ) ) {
		return new WP_Error( 'flxc_file', __( 'File could not be uploaded.', 'flx-chat' ), array( 'status' => 500 ) );
	}
	set_transient( 'flxc_up_' . $token, array_merge( $chk, array( 'file' => $file, 'user' => get_current_user_id() ) ), HOUR_IN_SECONDS );
	return array( 'token' => $token, 'type' => $chk['type'] );
}

/** Ön yüklenen dosyayı gönderim için alır (token tek kullanımlık). */
function flxc_upload_take( $token ) {
	$up = get_transient( 'flxc_up_' . $token );
	delete_transient( 'flxc_up_' . $token );
	if ( ! is_array( $up ) || (int) $up['user'] !== get_current_user_id() || ! is_readable( $up['file'] ) ) {
		return new WP_Error( 'flxc_file', __( 'The upload has expired. Please add the file again.', 'flx-chat' ), array( 'status' => 400 ) );
	}
	return $up;
}

/** Ek kartından kaldırılan dosyayı siler. */
function flxc_rest_upload_delete( WP_REST_Request $req ) {
	$up = get_transient( 'flxc_up_' . sanitize_key( (string) $req['token'] ) );
	if ( is_array( $up ) && (int) $up['user'] === get_current_user_id() ) {
		delete_transient( 'flxc_up_' . sanitize_key( (string) $req['token'] ) );
		wp_delete_file( $up['file'] );
	}
	return array( 'ok' => true );
}

/** Gönderilmeden bırakılan ön yüklemeleri (2 saatten eski) temizler. */
function flxc_upload_gc( $dir ) {
	foreach ( (array) glob( $dir . '/*' ) as $f ) {
		if ( is_file( $f ) && filemtime( $f ) < time() - 2 * HOUR_IN_SECONDS ) {
			wp_delete_file( $f );
		}
	}
}

function flxc_rest_retry( WP_REST_Request $req ) {
	return FLXC_Message_Service::retry( $req );
}


function flxc_rest_react( WP_REST_Request $req ) {
	return FLXC_Message_Service::react( $req );
}


/**
 * 1.38.0: mesaj silme. scope=everyone WhatsApp'ta karşı taraftan da siler (yalnız giden mesaj, süre sınırı içinde);
 * scope=panel yalnız panelde "Bu mesaj silindi" yapar. Her iki durumda da satır ve dosya sunucuda kalır.
 */
function flxc_rest_delete_message( WP_REST_Request $req ) {
	return FLXC_Message_Service::delete( $req );
}


/**
 * 1.45.0: giden metin mesajını WhatsApp'ta düzenler (WhatsApp süresi içinde, ~15 dk). Kişinin telefonunda da
 * "Düzenlendi" görünür. İlk metin meta'da (orig_body) saklanır.
 */
function flxc_rest_edit_message( WP_REST_Request $req ) {
	return FLXC_Message_Service::edit( $req );
}


function flxc_rest_read( WP_REST_Request $req ) {
	return FLXC_Message_Service::read( $req );
}


function flxc_rest_typing( WP_REST_Request $req ) {
	return FLXC_Message_Service::typing( $req );
}


function flxc_rest_avatar( WP_REST_Request $req ) {
	return FLXC_Desk_Service::avatar( $req );
}


function flxc_rest_ai( WP_REST_Request $req ) {
	return FLXC_Message_Service::suggest( $req );
}


/** Medya dosyası: yalnız yetkili temsilciye verilir. Yoksa Evolution'dan indirilip saklanır. */
function flxc_rest_media( WP_REST_Request $req ) {
	$m = flxc_get_message( (int) $req['id'] );
	if ( ! $m ) {
		return new WP_Error( 'flxc_media', 'Not found', array( 'status' => 404 ) );
	}
	$chat = flxc_rest_chat_or_error( $m['chat_id'] );
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	$path = flxc_message_media_path( $m );
	if ( is_wp_error( $path ) ) {
		return $path;
	}
	$m = flxc_get_message( $m['id'] );
	flxc_stream_file( $path, $m['media_mime'], $m['media_name'], (bool) $req->get_param( 'download' ) );
	exit;
}

/** Mesajın medya dosyası; yerelde yoksa Evolution'dan indirilip kaydedilir. @return string|WP_Error */
function flxc_message_media_path( $m ) {
	$dir = flxc_media_dir();
	if ( '' !== $m['media_path'] && is_readable( $dir . '/' . $m['media_path'] ) ) {
		return $dir . '/' . $m['media_path'];
	}
	if ( '' === $m['wa_id'] ) {
		return new WP_Error( 'flxc_media', 'Not found', array( 'status' => 404 ) );
	}
	$r = flxc_evo_media_base64( $m['conn'], $m['wa_id'] );
	if ( is_wp_error( $r ) || '' === $r['base64'] ) {
		return new WP_Error( 'flxc_media', is_wp_error( $r ) ? $r->get_error_message() : 'Empty', array( 'status' => 404 ) );
	}
	$mime  = $m['media_mime'] ? $m['media_mime'] : $r['mimetype'];
	$saved = flxc_save_media_base64( $r['base64'], $mime, $m['media_name'] ? $m['media_name'] : $r['fileName'] );
	if ( ! $saved ) {
		return new WP_Error( 'flxc_media', 'Save failed', array( 'status' => 500 ) );
	}
	flxc_update_message( $m['id'], array( 'media_path' => $saved['path'], 'media_size' => $saved['size'], 'media_mime' => $mime ) );
	return $dir . '/' . $saved['path'];
}

function flxc_stream_file( $path, $mime, $name, $download = false ) {
	$size  = filesize( $path );
	$start = 0;
	$end   = $size - 1;
	$mime  = $mime ? explode( ';', $mime )[0] : 'application/octet-stream';
	// Tarayıcıda çalıştırılabilecek türleri indirmeye zorla.
	if ( preg_match( '#(html|javascript|svg|xml)#i', $mime ) ) {
		$mime     = 'application/octet-stream';
		$download = true;
	}
	while ( ob_get_level() ) {
		ob_end_clean();
	}
	header( 'Content-Type: ' . $mime );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Cache-Control: private, max-age=86400' );
	header( 'Accept-Ranges: bytes' );
	$fname = $name ? $name : basename( $path );
	header( 'Content-Disposition: ' . ( $download ? 'attachment' : 'inline' ) . "; filename*=UTF-8''" . rawurlencode( $fname ) );
	if ( isset( $_SERVER['HTTP_RANGE'] ) && preg_match( '/bytes=(\d*)-(\d*)/', (string) $_SERVER['HTTP_RANGE'], $mm ) ) {
		if ( '' !== $mm[1] ) {
			$start = (int) $mm[1];
			$end   = '' !== $mm[2] ? min( (int) $mm[2], $size - 1 ) : $size - 1;
		} elseif ( '' !== $mm[2] ) {
			$start = max( 0, $size - (int) $mm[2] );
		}
		if ( $start > $end ) {
			status_header( 416 );
			header( 'Content-Range: bytes */' . $size );
			return;
		}
		status_header( 206 );
		header( "Content-Range: bytes $start-$end/$size" );
	} else {
		status_header( 200 );
	}
	header( 'Content-Length: ' . ( $end - $start + 1 ) );
	// phpcs:ignore WordPress.WP.AlternativeFunctions
	$fh = fopen( $path, 'rb' );
	fseek( $fh, $start );
	$left = $end - $start + 1;
	while ( $left > 0 && ! feof( $fh ) ) {
		$chunk = fread( $fh, min( 65536, $left ) ); // phpcs:ignore
		echo $chunk; // phpcs:ignore
		$left -= strlen( $chunk );
	}
	fclose( $fh ); // phpcs:ignore
}

/* ---------- Hazır cevaplar ---------- */

function flxc_rest_replies() {
	return FLXC_Desk_Service::replies();
}


function flxc_rest_save_reply( WP_REST_Request $req ) {
	return FLXC_Desk_Service::save_reply( $req );
}


function flxc_rest_delete_reply( WP_REST_Request $req ) {
	return FLXC_Desk_Service::delete_reply( $req );
}


function flxc_rest_use_reply( WP_REST_Request $req ) {
	return FLXC_Desk_Service::use_reply( $req );
}


/* ---------- Bağlantı yönetimi (ayarlar sayfası) ---------- */

function flxc_rest_conn_or_error( $id ) {
	return FLXC_Settings_Service::conn_or_error( FLXC_Val::raw_str( $id ) );
}

function flxc_rest_conn_test( WP_REST_Request $req ) {
	return FLXC_Settings_Service::connection_test( FLXC_Val::raw_str( $req['conn'] ) );
}

function flxc_rest_conn_webhook( WP_REST_Request $req ) {
	return FLXC_Settings_Service::connection_webhook( FLXC_Val::raw_str( $req['conn'] ) );
}

function flxc_rest_conn_qr( WP_REST_Request $req ) {
	return FLXC_Settings_Service::connection_qr( FLXC_Val::raw_str( $req['conn'] ) );
}

function flxc_rest_conn_import( WP_REST_Request $req ) {
	return FLXC_Settings_Service::connection_import( FLXC_Val::raw_str( $req['conn'] ), FLXC_Val::raw_int( $req->get_param( 'chats' ) ? $req->get_param( 'chats' ) : 30 ) );
}
