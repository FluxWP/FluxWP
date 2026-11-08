<?php
/**
 * REST `flx-chatbot/v1`.
 *
 * Ziyaretçi uçları herkese açıktır; sohbet, tarayıcıdaki rastgele anahtarla (t) bulunur (veritabanında yalnız özeti).
 * Asistan cevabı NDJSON akışıyla gelir: {e:conv|msg|text|tool|done|error}.
 * Temsilci uçları (/agent/*) flxcb_can_reply() ister.
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'flxcb_rest_routes' );

function flxcb_rest_routes() {
	$ns  = 'flx-chatbot/v1';
	$pub = '__return_true';
	$agt = function () {
		return flxcb_can_reply();
	};
	register_rest_route( $ns, '/session', array( 'methods' => 'POST', 'callback' => 'flxcb_rest_session', 'permission_callback' => $pub ) );
	register_rest_route( $ns, '/message', array( 'methods' => 'POST', 'callback' => 'flxcb_rest_message', 'permission_callback' => $pub ) );
	register_rest_route( $ns, '/poll', array( 'methods' => 'GET', 'callback' => 'flxcb_rest_poll', 'permission_callback' => $pub ) );
	register_rest_route( $ns, '/handoff', array( 'methods' => 'POST', 'callback' => 'flxcb_rest_handoff', 'permission_callback' => $pub ) );
	register_rest_route( $ns, '/contact', array( 'methods' => 'POST', 'callback' => 'flxcb_rest_contact', 'permission_callback' => $pub ) );
	register_rest_route( $ns, '/upload', array( 'methods' => 'POST', 'callback' => 'flxcb_rest_upload', 'permission_callback' => $pub ) );
	register_rest_route( $ns, '/file/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => 'flxcb_rest_file', 'permission_callback' => $pub ) );
	register_rest_route( $ns, '/rate', array( 'methods' => 'POST', 'callback' => 'flxcb_rest_rate', 'permission_callback' => $pub ) );
	register_rest_route( $ns, '/close', array( 'methods' => 'POST', 'callback' => 'flxcb_rest_close', 'permission_callback' => $pub ) );
	register_rest_route( $ns, '/typing', array( 'methods' => 'POST', 'callback' => 'flxcb_rest_typing', 'permission_callback' => $pub ) );

	register_rest_route( $ns, '/agent/conversations', array( 'methods' => 'GET', 'callback' => 'flxcb_rest_agent_list', 'permission_callback' => $agt ) );
	register_rest_route( $ns, '/agent/conversations/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => 'flxcb_rest_agent_get', 'permission_callback' => $agt ) );
	register_rest_route( $ns, '/agent/conversations/(?P<id>\d+)', array( 'methods' => 'DELETE', 'callback' => 'flxcb_rest_agent_delete', 'permission_callback' => function () { return current_user_can( 'manage_options' ); } ) );
	register_rest_route( $ns, '/agent/conversations/(?P<id>\d+)/reply', array( 'methods' => 'POST', 'callback' => 'flxcb_rest_agent_reply', 'permission_callback' => $agt ) );
	register_rest_route( $ns, '/agent/conversations/(?P<id>\d+)/close', array( 'methods' => 'POST', 'callback' => 'flxcb_rest_agent_close', 'permission_callback' => $agt ) );
	register_rest_route( $ns, '/agent/conversations/(?P<id>\d+)/typing', array( 'methods' => 'POST', 'callback' => 'flxcb_rest_agent_typing', 'permission_callback' => $agt ) );
}

/* ---------------------------------------------------------------- */
/* Yardımcılar                                                       */
/* ---------------------------------------------------------------- */

function flxcb_rest_out( $data, $status = 200 ) {
	$r = new WP_REST_Response( $data, $status );
	$r->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
	$r->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
	return $r;
}

function flxcb_rest_err( $code, $msg, $status = 400 ) {
	return new WP_Error( $code, $msg, array( 'status' => $status ) );
}

/** Sohbet penceresi kapalıysa (ayar) uçlar çalışmaz; yöneticiler önizleme için kullanabilir. */
function flxcb_rest_enabled() {
	return flxcb_setting( 'enabled' ) || current_user_can( 'manage_options' );
}

/** Metin parametresi (dizi/nesne gelirse boş). */
function flxcb_p( WP_REST_Request $req, $key ) {
	$v = $req->get_param( $key );
	return is_scalar( $v ) ? (string) $v : '';
}

function flxcb_req_conv( WP_REST_Request $req ) {
	$t = flxcb_p( $req, 't' );
	return '' !== $t ? flxcb_conversation_by_token( $t ) : null;
}

function flxcb_page_param( WP_REST_Request $req ) {
	$url   = $req->get_param( 'page' );
	$title = $req->get_param( 'title' );
	$url   = is_scalar( $url ) ? esc_url_raw( (string) $url ) : '';
	// Yalnız bu sitenin sayfaları.
	if ( '' !== $url && 0 !== strpos( $url, home_url() ) ) {
		$url = '';
	}
	return array(
		'url'   => $url,
		'title' => is_scalar( $title ) ? mb_substr( sanitize_text_field( (string) $title ), 0, 190 ) : '',
	);
}

/** Ziyaretçinin yeni mesajları + sohbet durumu. */
function flxcb_state( $conv, $token, $after = 0 ) {
	return array(
		'conv'     => flxcb_conv_for_js( $conv ),
		'messages' => flxcb_messages_for_js( flxcb_messages( (int) $conv['id'], (int) $after, 200 ), $conv, $token ),
		'online'   => flxcb_is_online(),
	);
}

/** Giriş yapmış kullanıcının adı/e-postası sohbete. */
function flxcb_fill_user( array &$data ) {
	$u = wp_get_current_user();
	if ( $u && $u->ID ) {
		$data['user_id'] = (int) $u->ID;
		$data['name']    = trim( $u->first_name . ' ' . $u->last_name ) ? trim( $u->first_name . ' ' . $u->last_name ) : $u->display_name;
		$data['email']   = $u->user_email;
	}
}

/** Mesajdan sonra işaretlenen ziyaretçi okuma noktası. */
function flxcb_mark_visitor_read( $conv, $upto ) {
	$upto = (int) $upto;
	if ( $upto > (int) $conv['visitor_read'] ) {
		flxcb_update_conversation( (int) $conv['id'], array( 'visitor_read' => $upto, 'seen_ts' => flxcb_now_ms() ) );
		do_action( 'flxcb_visitor_read', $conv, (int) $conv['visitor_read'], $upto );
	} else {
		flxcb_update_conversation( (int) $conv['id'], array( 'seen_ts' => flxcb_now_ms() ) );
	}
}

/* ---------------------------------------------------------------- */
/* Ziyaretçi uçları                                                  */
/* ---------------------------------------------------------------- */

function flxcb_rest_session( WP_REST_Request $req ) {
	$conv = flxcb_req_conv( $req );
	if ( ! $conv ) {
		return flxcb_rest_out( array( 'conv' => null, 'messages' => array(), 'online' => flxcb_is_online() ) );
	}
	$conv  = flxcb_fc_sync_status( $conv );
	$state = flxcb_state( $conv, flxcb_p( $req, 't' ) );
	$last  = $state['messages'] ? end( $state['messages'] )['id'] : 0;
	if ( $req->get_param( 'open' ) ) {
		flxcb_mark_visitor_read( $conv, $last );
	}
	return flxcb_rest_out( $state );
}

function flxcb_rest_poll( WP_REST_Request $req ) {
	$conv = flxcb_req_conv( $req );
	if ( ! $conv ) {
		return flxcb_rest_err( 'flxcb_conv', __( 'Chat not found.', 'flx-chatbot' ), 404 );
	}
	if ( ! flxcb_rate( 'poll|' . $conv['id'], 60, MINUTE_IN_SECONDS ) ) {
		return flxcb_rest_err( 'flxcb_rate', __( 'Too many requests. Please wait a moment.', 'flx-chatbot' ), 429 );
	}
	$conv  = flxcb_fc_sync_status( $conv );
	$state = flxcb_state( $conv, flxcb_p( $req, 't' ), (int) $req->get_param( 'after' ) );
	if ( $req->get_param( 'open' ) ) {
		$last = $state['messages'] ? end( $state['messages'] )['id'] : (int) $req->get_param( 'after' );
		flxcb_mark_visitor_read( $conv, $last );
	}
	return flxcb_rest_out( $state );
}

/** Ziyaretçi mesajı; asistan açıksa cevap NDJSON akışıyla gelir. */
function flxcb_rest_message( WP_REST_Request $req ) {
	if ( ! flxcb_rest_enabled() ) {
		return flxcb_rest_err( 'flxcb_off', __( 'Chat is closed right now.', 'flx-chatbot' ), 403 );
	}
	$text = trim( sanitize_textarea_field( flxcb_p( $req, 'text' ) ) );
	if ( '' === $text ) {
		return flxcb_rest_err( 'flxcb_empty', __( 'The message is empty.', 'flx-chatbot' ) );
	}
	$text = mb_substr( $text, 0, 2000 );
	if ( ! flxcb_rate( 'msg|' . flxcb_ip_key(), 20, MINUTE_IN_SECONDS ) ) {
		return flxcb_rest_err( 'flxcb_rate', __( 'You\'re typing too fast. Wait a moment and try again.', 'flx-chatbot' ), 429 );
	}
	$page  = flxcb_page_param( $req );
	$conv  = flxcb_req_conv( $req );
	$token = flxcb_p( $req, 't' );
	$new   = false;
	if ( ! $conv ) {
		if ( ! flxcb_rate( 'new|' . flxcb_ip_key(), 10, HOUR_IN_SECONDS ) ) {
			return flxcb_rest_err( 'flxcb_rate', __( 'Too many new chats were started. Please try again later.', 'flx-chatbot' ), 429 );
		}
		$data = array(
			'page_url'   => $page['url'],
			'page_title' => $page['title'],
			'locale'     => determine_locale(),
		);
		flxcb_fill_user( $data );
		list( $conv, $token ) = flxcb_create_conversation( $data );
		if ( ! $conv ) {
			return flxcb_rest_err( 'flxcb_db', __( 'Could not start the chat.', 'flx-chatbot' ), 500 );
		}
		$new = true;
		// Karşılama mesajı ilk satır olsun (asistan geçmişinde de görünür).
		if ( 'live' !== flxcb_setting( 'mode' ) ) {
			flxcb_add_message( array( 'conv_id' => (int) $conv['id'], 'role' => 'bot', 'body' => flxcb_text( 'welcome' ), 'ts' => flxcb_now_ms() - 1 ) );
		}
	} else {
		$conv = flxcb_reopen( flxcb_fc_sync_status( $conv ) );
		$upd  = array();
		if ( '' !== $page['url'] && $page['url'] !== $conv['page_url'] ) {
			$upd['page_url']   = $page['url'];
			$upd['page_title'] = $page['title'];
		}
		if ( ! $conv['user_id'] && get_current_user_id() ) {
			$d = array();
			flxcb_fill_user( $d );
			$upd = array_merge( $upd, array_intersect_key( $d, array_flip( array( 'user_id', 'name', 'email' ) ) ) );
		}
		if ( $upd ) {
			flxcb_update_conversation( (int) $conv['id'], $upd );
			$conv = flxcb_get_conversation( (int) $conv['id'] );
		}
	}
	if ( ! flxcb_rate( 'cmsg|' . $conv['id'], 12, MINUTE_IN_SECONDS ) ) {
		return flxcb_rest_err( 'flxcb_rate', __( 'You\'re typing too fast. Wait a moment and try again.', 'flx-chatbot' ), 429 );
	}
	$before = (int) $req->get_param( 'after' );
	$mid    = flxcb_add_message( array( 'conv_id' => (int) $conv['id'], 'role' => 'visitor', 'body' => $text ) );
	$conv   = flxcb_get_conversation( (int) $conv['id'] );

	// Yalnız canlı destek modu: ilk mesaj doğrudan temsilciye.
	if ( 'live' === flxcb_setting( 'mode' ) && 'bot' === $conv['status'] ) {
		flxcb_handoff( $conv, 'auto' );
		$conv = flxcb_get_conversation( (int) $conv['id'] );
	}

	if ( ! flxcb_bot_active( $conv ) ) {
		$state          = flxcb_state( $conv, $token, $new ? 0 : $before );
		$state['token'] = $new ? $token : '';
		return flxcb_rest_out( $state );
	}

	/* Asistan: NDJSON akışı. */
	ignore_user_abort( true );
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 90 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	if ( ! headers_sent() ) {
		nocache_headers();
		header( 'Content-Type: application/x-ndjson; charset=utf-8' );
		header( 'X-Accel-Buffering: no' );
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
		header( 'Cache-Control: no-store' );
	}
	while ( ob_get_level() > 0 ) {
		ob_end_flush();
	}
	$line = function ( array $ev ) {
		echo flxcb_json( $ev ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		flush();
	};
	$line(
		array(
			'e'     => 'conv',
			'token' => $new ? $token : '',
			'conv'  => flxcb_conv_for_js( $conv ),
			'msg'   => flxcb_message_for_js( flxcb_get_message( $mid ), $conv, $token ),
		)
	);
	if ( function_exists( 'wc_load_cart' ) && function_exists( 'WC' ) && null === WC()->cart ) {
		try {
			wc_load_cart();
		} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		}
	}
	$res = flxcb_bot_reply(
		$conv,
		function ( $ev ) use ( $line ) {
			$line( array( 'e' => $ev['t'], 'd' => $ev['d'] ) );
		},
		$page
	);
	$conv = flxcb_get_conversation( (int) $conv['id'] );
	if ( is_wp_error( $res ) ) {
		// Asistan cevap veremedi: temsilci varsa devret, yoksa mesaj bırakmayı öner.
		if ( '' !== flxcb_handoff_target() ) {
			flxcb_handoff( $conv, 'auto' );
		} else {
			flxcb_event( (int) $conv['id'], 'error', __( 'I can\'t answer right now. Please write again in a moment or reach us through our contact details.', 'flx-chatbot' ) );
		}
	} elseif ( ! empty( $res['handoff'] ) ) {
		flxcb_handoff( $conv, 'bot', (string) $res['handoff']['summary'] );
	}
	$conv  = flxcb_get_conversation( (int) $conv['id'] );
	$state = flxcb_state( $conv, $token, $mid );
	$line(
		array(
			'e'        => 'done',
			'conv'     => $state['conv'],
			'messages' => $state['messages'],
			'online'   => $state['online'],
		)
	);
	exit;
}

function flxcb_rest_handoff( WP_REST_Request $req ) {
	$conv = flxcb_req_conv( $req );
	if ( ! $conv ) {
		return flxcb_rest_err( 'flxcb_conv', __( 'Chat not found.', 'flx-chatbot' ), 404 );
	}
	if ( '' === flxcb_handoff_target() ) {
		return flxcb_rest_err( 'flxcb_live', __( 'Live chat is off.', 'flx-chatbot' ), 403 );
	}
	$conv = flxcb_reopen( $conv );
	flxcb_handoff( $conv, 'visitor' );
	return flxcb_rest_out( flxcb_state( flxcb_get_conversation( (int) $conv['id'] ), flxcb_p( $req, 't' ), (int) $req->get_param( 'after' ) ) );
}

/** İletişim bilgisi (sohbet öncesi ya da mesai dışı form). Sohbet yoksa açılır. */
function flxcb_rest_contact( WP_REST_Request $req ) {
	if ( ! flxcb_rest_enabled() ) {
		return flxcb_rest_err( 'flxcb_off', __( 'Chat is closed right now.', 'flx-chatbot' ), 403 );
	}
	$email = sanitize_email( flxcb_p( $req, 'email' ) );
	$name  = mb_substr( sanitize_text_field( flxcb_p( $req, 'name' ) ), 0, 190 );
	$phone = mb_substr( preg_replace( '/[^0-9+ ()-]/', '', flxcb_p( $req, 'phone' ) ), 0, 40 );
	$text  = mb_substr( trim( sanitize_textarea_field( flxcb_p( $req, 'message' ) ) ), 0, 2000 );
	if ( ! is_email( $email ) ) {
		return flxcb_rest_err( 'flxcb_email', __( 'Enter a valid email address.', 'flx-chatbot' ) );
	}
	if ( ! flxcb_rate( 'contact|' . flxcb_ip_key(), 6, HOUR_IN_SECONDS ) ) {
		return flxcb_rest_err( 'flxcb_rate', __( 'Too many requests. Please try again later.', 'flx-chatbot' ), 429 );
	}
	$conv  = flxcb_req_conv( $req );
	$token = flxcb_p( $req, 't' );
	$new   = false;
	if ( ! $conv ) {
		$page = flxcb_page_param( $req );
		$data = array(
			'page_url'   => $page['url'],
			'page_title' => $page['title'],
			'locale'     => determine_locale(),
		);
		flxcb_fill_user( $data );
		list( $conv, $token ) = flxcb_create_conversation( $data );
		if ( ! $conv ) {
			return flxcb_rest_err( 'flxcb_db', __( 'Could not start the chat.', 'flx-chatbot' ), 500 );
		}
		$new = true;
	}
	$upd = array( 'email' => $email, 'lead' => 1 );
	if ( '' !== $name ) {
		$upd['name'] = $name;
	}
	if ( '' !== $phone ) {
		$upd['phone'] = $phone;
	}
	flxcb_update_conversation( (int) $conv['id'], $upd );
	if ( '' !== $text ) {
		flxcb_add_message( array( 'conv_id' => (int) $conv['id'], 'role' => 'visitor', 'body' => $text ) );
	}
	flxcb_save_lead( (int) $conv['id'], $text );
	$conv = flxcb_get_conversation( (int) $conv['id'] );
	if ( $req->get_param( 'handoff' ) && '' !== flxcb_handoff_target() ) {
		flxcb_handoff( flxcb_reopen( $conv ), 'visitor' );
	} elseif ( 'offline' === $conv['status'] || $new ) {
		flxcb_event( (int) $conv['id'], 'contact', __( 'Thanks! We got your message and will reply to your email.', 'flx-chatbot' ) );
		flxcb_notify_team( $conv, 'offline' );
		if ( 'flx-chat' === flxcb_handoff_target() && ! $conv['flxc_chat'] ) {
			flxcb_fc_push( $conv );
		}
	}
	$state          = flxcb_state( flxcb_get_conversation( (int) $conv['id'] ), $token, $new ? 0 : (int) $req->get_param( 'after' ) );
	$state['token'] = $new ? $token : '';
	return flxcb_rest_out( $state );
}

function flxcb_rest_upload( WP_REST_Request $req ) {
	$conv = flxcb_req_conv( $req );
	if ( ! $conv ) {
		return flxcb_rest_err( 'flxcb_conv', __( 'Write a message first.', 'flx-chatbot' ), 404 );
	}
	if ( ! flxcb_setting( 'uploads' ) ) {
		return flxcb_rest_err( 'flxcb_upload', __( 'File sharing is off.', 'flx-chatbot' ), 403 );
	}
	if ( ! flxcb_rate( 'up|' . $conv['id'], 10, HOUR_IN_SECONDS ) ) {
		return flxcb_rest_err( 'flxcb_rate', __( 'Too many files were sent. Please try again later.', 'flx-chatbot' ), 429 );
	}
	$files = $req->get_file_params();
	if ( empty( $files['file'] ) ) {
		return flxcb_rest_err( 'flxcb_file', __( 'No file selected.', 'flx-chatbot' ) );
	}
	$f = flxcb_store_upload( $files['file'] );
	if ( is_wp_error( $f ) ) {
		return $f;
	}
	$conv = flxcb_reopen( $conv );
	flxcb_add_message(
		array(
			'conv_id'   => (int) $conv['id'],
			'role'      => 'visitor',
			'type'      => 0 === strpos( $f['mime'], 'image/' ) ? 'image' : 'file',
			'body'      => mb_substr( sanitize_textarea_field( flxcb_p( $req, 'caption' ) ), 0, 1000 ),
			'file_path' => $f['path'],
			'file_name' => $f['name'],
			'file_mime' => $f['mime'],
			'file_size' => $f['size'],
		)
	);
	return flxcb_rest_out( flxcb_state( flxcb_get_conversation( (int) $conv['id'] ), flxcb_p( $req, 't' ), (int) $req->get_param( 'after' ) ) );
}

/** Dosyayı yalnız o sohbetin ziyaretçisine ya da temsilciye verir. */
function flxcb_rest_file( WP_REST_Request $req ) {
	$m = flxcb_get_message( (int) $req['id'] );
	if ( ! $m || '' === $m['file_path'] ) {
		return flxcb_rest_err( 'flxcb_file', __( 'File not found.', 'flx-chatbot' ), 404 );
	}
	$conv = flxcb_req_conv( $req );
	if ( ! flxcb_can_reply() && ( ! $conv || (int) $conv['id'] !== (int) $m['conv_id'] ) ) {
		return flxcb_rest_err( 'flxcb_file', __( 'File not found.', 'flx-chatbot' ), 404 );
	}
	$path = flxcb_upload_dir() . '/' . basename( $m['file_path'] );
	if ( ! is_readable( $path ) ) {
		return flxcb_rest_err( 'flxcb_file', __( 'File not found.', 'flx-chatbot' ), 404 );
	}
	$mime   = (string) $m['file_mime'];
	$inline = 0 === strpos( $mime, 'image/' ) || 0 === strpos( $mime, 'audio/' ) || 'application/pdf' === $mime;
	nocache_headers();
	header( 'Content-Type: ' . ( $mime ? $mime : 'application/octet-stream' ) );
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Security-Policy: default-src \'none\'; img-src \'self\'; media-src \'self\'; style-src \'unsafe-inline\'' );
	header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . '; filename="' . rawurlencode( (string) $m['file_name'] ) . '"' );
	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

function flxcb_rest_rate( WP_REST_Request $req ) {
	$conv = flxcb_req_conv( $req );
	if ( ! $conv ) {
		return flxcb_rest_err( 'flxcb_conv', __( 'Chat not found.', 'flx-chatbot' ), 404 );
	}
	$r = max( 1, min( 5, (int) $req->get_param( 'rating' ) ) );
	flxcb_update_conversation(
		(int) $conv['id'],
		array(
			'rating'      => $r,
			'rating_note' => mb_substr( sanitize_textarea_field( flxcb_p( $req, 'note' ) ), 0, 1000 ),
		)
	);
	if ( $conv['flxc_chat'] && function_exists( 'flxc_system_message' ) && flxcb_fc_ready() ) {
		$chat = flxc_get_chat( (int) $conv['flxc_chat'] );
		if ( $chat ) {
			/* translators: 1: stars, 2: note */
			flxc_system_message( $chat, trim( sprintf( __( 'Visitor rating: %1$s %2$s', 'flx-chatbot' ), str_repeat( '★', $r ) . str_repeat( '☆', 5 - $r ), flxcb_p( $req, 'note' ) ) ) );
		}
	}
	do_action( 'flxcb_rated', flxcb_get_conversation( (int) $conv['id'] ), $r );
	return flxcb_rest_out( array( 'ok' => true, 'conv' => flxcb_conv_for_js( flxcb_get_conversation( (int) $conv['id'] ) ) ) );
}

function flxcb_rest_close( WP_REST_Request $req ) {
	$conv = flxcb_req_conv( $req );
	if ( ! $conv ) {
		return flxcb_rest_err( 'flxcb_conv', __( 'Chat not found.', 'flx-chatbot' ), 404 );
	}
	flxcb_close( $conv, 'visitor' );
	return flxcb_rest_out( flxcb_state( flxcb_get_conversation( (int) $conv['id'] ), flxcb_p( $req, 't' ), (int) $req->get_param( 'after' ) ) );
}

function flxcb_rest_typing( WP_REST_Request $req ) {
	$conv = flxcb_req_conv( $req );
	if ( $conv && flxcb_rate( 'typ|' . $conv['id'], 20, MINUTE_IN_SECONDS ) ) {
		set_transient( 'flxcb_typing_v_' . (int) $conv['id'], 1, 10 );
		do_action( 'flxcb_visitor_typing', $conv );
	}
	return flxcb_rest_out( array( 'ok' => true ) );
}

/* ---------------------------------------------------------------- */
/* Temsilci uçları (eklentinin gelen kutusu)                         */
/* ---------------------------------------------------------------- */

function flxcb_conv_for_agent( $c ) {
	$last = flxcb_last_message( (int) $c['id'] );
	return array(
		'id'       => (int) $c['id'],
		'title'    => flxcb_conv_title( $c ),
		'name'     => (string) $c['name'],
		'email'    => (string) $c['email'],
		'phone'    => (string) $c['phone'],
		'status'   => (string) $c['status'],
		'unread'   => (int) $c['unread'],
		'rating'   => (int) $c['rating'],
		'note'     => (string) $c['rating_note'],
		'lead'     => (int) $c['lead'],
		'order'    => (int) $c['order_id'],
		'orderUrl' => (int) $c['order_id'] && function_exists( 'wc_get_order' ) && wc_get_order( (int) $c['order_id'] ) ? wc_get_order( (int) $c['order_id'] )->get_edit_order_url() : '',
		'page'     => array( 'url' => (string) $c['page_url'], 'title' => (string) $c['page_title'] ),
		'user'     => (int) $c['user_id'],
		'agent'    => (int) $c['assigned_to'] ? flxcb_user_name( (int) $c['assigned_to'] ) : '',
		'fluxChat' => (int) $c['flxc_chat'] && flxcb_fc_ready() ? admin_url( 'admin.php?page=flx-chat&chat=' . (int) $c['flxc_chat'] ) : '',
		'summary'  => (string) flxcb_conv_meta( $c, 'summary' ),
		'lastTs'   => (int) $c['last_ts'],
		'ago'      => function_exists( 'flx_time_ago' ) ? flx_time_ago( (int) floor( $c['last_ts'] / 1000 ) ) : '',
		'online'   => flxcb_now_ms() - (int) $c['seen_ts'] < 45000 ? 1 : 0,
		'typing'   => get_transient( 'flxcb_typing_v_' . (int) $c['id'] ) ? 1 : 0,
		'last'     => $last ? array(
			'role' => $last['role'],
			'body' => mb_substr( '' !== (string) $last['body'] ? (string) $last['body'] : (string) $last['file_name'], 0, 120 ),
		) : null,
	);
}

function flxcb_agent_messages_for_js( array $rows ) {
	return array_map(
		function ( $m ) {
			$meta = flxcb_decode( $m['meta'] );
			$o    = array(
				'id'     => (int) $m['id'],
				'role'   => $m['role'],
				'type'   => $m['type'],
				'body'   => (string) $m['body'],
				'ts'     => (int) $m['ts'],
				'time'   => function_exists( 'flx_date' ) ? flx_date( (int) floor( $m['ts'] / 1000 ), 'datetime' ) : '',
				'author' => 'agent' === $m['role'] ? flxcb_user_name( (int) $m['user_id'] ) : ( 'bot' === $m['role'] ? flxcb_bot_name() : '' ),
			);
			if ( '' !== $m['file_path'] ) {
				$o['file'] = array(
					'name' => (string) $m['file_name'],
					'mime' => (string) $m['file_mime'],
					'url'  => add_query_arg( '_wpnonce', wp_create_nonce( 'wp_rest' ), rest_url( 'flx-chatbot/v1/file/' . (int) $m['id'] ) ),
				);
			}
			if ( ! empty( $meta['cards'] ) ) {
				$o['cards'] = $meta['cards'];
			}
			return $o;
		},
		$rows
	);
}

function flxcb_rest_agent_list( WP_REST_Request $req ) {
	$rows = flxcb_query_conversations(
		array(
			'status' => sanitize_key( flxcb_p( $req, 'status' ) ),
			'q'      => sanitize_text_field( flxcb_p( $req, 'q' ) ),
			'limit'  => 60,
			'offset' => max( 0, (int) $req->get_param( 'offset' ) ),
		)
	);
	return flxcb_rest_out(
		array(
			'items' => array_map( 'flxcb_conv_for_agent', $rows ),
			'stats' => flxcb_stats( 30 ),
		)
	);
}

function flxcb_rest_agent_get( WP_REST_Request $req ) {
	$c = flxcb_get_conversation( (int) $req['id'] );
	if ( ! $c ) {
		return flxcb_rest_err( 'flxcb_conv', __( 'Chat not found.', 'flx-chatbot' ), 404 );
	}
	$c    = flxcb_fc_sync_status( $c );
	$rows = flxcb_messages( (int) $c['id'], (int) $req->get_param( 'after' ), 300 );
	if ( $rows && ! $req->get_param( 'peek' ) ) {
		global $wpdb;
		$last_in = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(id) FROM ' . flxcb_table( 'messages' ) . " WHERE conv_id = %d AND role = 'visitor'", (int) $c['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		flxcb_update_conversation( (int) $c['id'], array( 'agent_read' => $last_in, 'unread' => 0 ) );
		$c = flxcb_get_conversation( (int) $c['id'] );
	}
	return flxcb_rest_out(
		array(
			'conv'     => flxcb_conv_for_agent( $c ),
			'messages' => flxcb_agent_messages_for_js( $rows ),
		)
	);
}

function flxcb_rest_agent_reply( WP_REST_Request $req ) {
	$c = flxcb_get_conversation( (int) $req['id'] );
	if ( ! $c ) {
		return flxcb_rest_err( 'flxcb_conv', __( 'Chat not found.', 'flx-chatbot' ), 404 );
	}
	$text  = trim( sanitize_textarea_field( flxcb_p( $req, 'text' ) ) );
	$file  = array();
	$files = $req->get_file_params();
	if ( ! empty( $files['file'] ) ) {
		$f = flxcb_store_upload( $files['file'] );
		if ( is_wp_error( $f ) ) {
			return $f;
		}
		$file = $f;
	}
	if ( '' === $text && ! $file ) {
		return flxcb_rest_err( 'flxcb_empty', __( 'The message is empty.', 'flx-chatbot' ) );
	}
	flxcb_agent_reply( $c, get_current_user_id(), $text, $file );
	return flxcb_rest_agent_get( $req );
}

function flxcb_rest_agent_close( WP_REST_Request $req ) {
	$c = flxcb_get_conversation( (int) $req['id'] );
	if ( ! $c ) {
		return flxcb_rest_err( 'flxcb_conv', __( 'Chat not found.', 'flx-chatbot' ), 404 );
	}
	flxcb_close( $c, 'agent' );
	return flxcb_rest_agent_get( $req );
}

function flxcb_rest_agent_typing( WP_REST_Request $req ) {
	set_transient( 'flxcb_typing_a_' . (int) $req['id'], 1, 8 );
	return flxcb_rest_out( array( 'ok' => true ) );
}

function flxcb_rest_agent_delete( WP_REST_Request $req ) {
	flxcb_delete_conversation( (int) $req['id'] );
	return flxcb_rest_out( array( 'ok' => true ) );
}
