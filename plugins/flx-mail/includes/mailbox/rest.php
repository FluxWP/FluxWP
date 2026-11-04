<?php
/**
 * Posta kutusu ekranının REST uçları (yalnız yöneticiler, /flxi/v1/mailbox/…).
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	function () {
		$ns   = 'flxi/v1';
		$perm = function () {
			return flxi_can_manage();
		};
		$r    = function ( $path, $methods, $cb ) use ( $ns, $perm ) {
			register_rest_route( $ns, '/mailbox' . $path, array( 'methods' => $methods, 'callback' => $cb, 'permission_callback' => $perm ) );
		};
		$r( '/list', 'GET', 'flxi_rest_mb_list' );
		$r( '/item/(?P<id>\d+)', 'GET', 'flxi_rest_mb_item' );
		$r( '/item/(?P<id>\d+)/mark', 'POST', 'flxi_rest_mb_mark' );
		$r( '/item/(?P<id>\d+)/attachment', 'GET', 'flxi_rest_mb_attachment' );
		$r( '/sync', 'POST', 'flxi_rest_mb_sync' );
		$r( '/test', 'POST', 'flxi_rest_mb_test' );
		$r( '/drafts', 'GET', 'flxi_rest_mb_drafts' );
		$r( '/draft', 'POST', 'flxi_rest_mb_draft_save' );
		$r( '/new-draft', 'POST', 'flxi_rest_mb_new_draft' );
		$r( '/draft/(?P<id>\d+)', 'GET', 'flxi_rest_mb_draft_get' );
		$r( '/draft/(?P<id>\d+)/delete', 'POST', 'flxi_rest_mb_draft_delete' );
		$r( '/draft/(?P<id>\d+)/send', 'POST', 'flxi_rest_mb_draft_send' );
		$r( '/reply', 'POST', 'flxi_rest_mb_reply' );
		$r( '/preview', 'POST', 'flxi_rest_mb_preview' );
		$r( '/ai', 'POST', 'flxi_rest_mb_ai' );
		$r( '/layouts', 'GET', 'flxi_rest_mb_layouts' );
		$r( '/layout', 'POST', 'flxi_rest_mb_layout_save' );
		$r( '/layout/(?P<id>[a-z0-9]+)/delete', 'POST', 'flxi_rest_mb_layout_delete' );
	}
);

/** Başka ekrandan (Flux Tasks belgesi vb.) yeni taslak: { subject, body, to, layout, note } → { id, url }. */
function flxi_rest_mb_new_draft( WP_REST_Request $q ) {
	$res = flxi_mailbox_new_draft( (array) $q->get_params() );
	if ( is_wp_error( $res ) ) {
		return new WP_Error( $res->get_error_code(), $res->get_error_message(), array( 'status' => 400 ) );
	}
	return $res;
}

function flxi_rest_mb_nocache( $data ) {
	$res = rest_ensure_response( $data );
	$res->header( 'Cache-Control', 'no-store' );
	return $res;
}

/** Sol menü sayaçları ve eşitleme durumu. */
function flxi_mailbox_status() {
	$st = flxi_mailbox_state();
	return array(
		'enabled'  => flxi_mailbox_enabled(),
		'unread'   => flxi_mailbox_unread_count(),
		'review'   => flxi_drafts_count( 'review' ),
		'drafts'   => flxi_drafts_count( 'draft' ) + flxi_drafts_count( 'failed' ),
		'last_ok'  => $st['last_ok'] ? flx_time_ago( $st['last_ok'] ) : '',
		'error'    => $st['error'],
		'sent_box' => $st['names']['sent'] ?? '',
		'more'     => (bool) array_filter(
			(array) $st['folders'],
			function ( $f ) {
				return ! empty( $f['backfill'] );
			}
		),
		'ai'       => flxi_mailbox_ai_ready(),
	);
}

function flxi_mailbox_list_row( array $r ) {
	$att = is_array( $r['attachments'] ) ? $r['attachments'] : (array) json_decode( (string) $r['attachments'], true );
	$att = array_filter(
		$att,
		function ( $a ) {
			return empty( $a['inline'] );
		}
	);
	return array(
		'id'      => (int) $r['id'],
		'folder'  => $r['folder'],
		'source'  => $r['source'],
		'from'    => $r['from_name'] ? $r['from_name'] : $r['from_email'],
		'from_email' => $r['from_email'],
		'to'      => $r['to_list'],
		'subject' => '' !== (string) $r['subject'] ? $r['subject'] : __( '(konu yok)', 'flx-mail' ),
		'snippet' => $r['snippet'],
		'date'    => flxi_mailbox_date( $r['mail_date'] ),
		'iso'     => mysql2date( 'c', get_date_from_gmt( $r['mail_date'] ) ),
		'read'    => (bool) $r['is_read'],
		'starred' => (bool) $r['is_starred'],
		'system'  => (bool) $r['is_system'],
		'replied' => (bool) $r['replied'],
		'att'     => count( $att ),
	);
}

/** Bugünse saat, bu yılsa gün ay, değilse tarih. */
function flxi_mailbox_date( $gmt ) {
	$ts  = strtotime( $gmt . ' UTC' );
	$now = time();
	if ( wp_date( 'Ymd', $ts ) === wp_date( 'Ymd', $now ) ) {
		return wp_date( 'H:i', $ts );
	}
	if ( wp_date( 'Y', $ts ) === wp_date( 'Y', $now ) ) {
		return flx_date( $ts, 'short' );
	}
	return wp_date( 'd.m.Y', $ts );
}

function flxi_rest_mb_list( WP_REST_Request $q ) {
	$r = flxi_mailbox_query(
		array(
			'folder' => sanitize_key( (string) $q['folder'] ) ?: 'inbox',
			'search' => sanitize_text_field( (string) $q['search'] ),
			'system' => ! empty( $q['system'] ) && 'false' !== $q['system'],
			'unread' => ! empty( $q['unread'] ) && 'false' !== $q['unread'],
			'limit'  => 40,
			'offset' => (int) $q['offset'],
		)
	);
	return flxi_rest_mb_nocache(
		array(
			'rows'   => array_map( 'flxi_mailbox_list_row', $r['rows'] ),
			'total'  => $r['total'],
			'status' => flxi_mailbox_status(),
		)
	);
}

function flxi_rest_mb_item( WP_REST_Request $q ) {
	global $wpdb;
	$m = flxi_mailbox_get( (int) $q['id'] );
	if ( ! $m ) {
		return new WP_Error( 'flxi_404', __( 'E-posta bulunamadı.', 'flx-mail' ), array( 'status' => 404 ) );
	}
	if ( ! (int) $m['is_read'] ) {
		flxi_mailbox_mark( $m['id'], 'is_read', 1 );
		$m['is_read'] = 1;
	}
	$html = (string) $m['body_html'];
	if ( '' === $html && $m['log_id'] ) {
		$html = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT body FROM ' . flxi_table( 'log' ) . ' WHERE id = %d', (int) $m['log_id'] ) ); // phpcs:ignore
		if ( '' === $html ) {
			$html = '<p style="font-family:sans-serif;color:#667">' . esc_html__( 'İçerik kaydedilmemiş (Ayarlar › Kayıt › içerik kaydı kapalı).', 'flx-mail' ) . '</p>';
		}
	}
	if ( '' === $html ) {
		$html = '<div style="font-family:sans-serif;font-size:14px;line-height:1.6;white-space:pre-wrap">' . make_clickable( esc_html( (string) $m['body_text'] ) ) . '</div>';
	}
	// Gövdedeki cid: görselleri ek adresine çevir.
	$nonce = wp_create_nonce( 'wp_rest' );
	$atts  = array();
	foreach ( $m['attachments'] as $a ) {
		$url = add_query_arg(
			array(
				'section'  => $a['section'],
				'_wpnonce' => $nonce,
			),
			rest_url( 'flxi/v1/mailbox/item/' . (int) $m['id'] . '/attachment' )
		);
		if ( ! empty( $a['cid'] ) ) {
			$html = str_ireplace( 'cid:' . $a['cid'], esc_url( $url . '&inline=1' ), $html );
		}
		if ( empty( $a['inline'] ) ) {
			$atts[] = array(
				'name' => $a['name'],
				'size' => size_format( (int) $a['size'] ),
				'type' => $a['type'],
				'url'  => $url,
			);
		}
	}
	$thread = array();
	if ( $m['thread_key'] ) {
		foreach ( flxi_mailbox_query( array( 'folder' => 'all', 'thread' => $m['thread_key'], 'system' => 1, 'limit' => 30 ) )['rows'] as $t ) {
			$thread[] = flxi_mailbox_list_row( $t );
		}
	}
	$row = flxi_mailbox_list_row( $m );
	return flxi_rest_mb_nocache(
		array_merge(
			$row,
			array(
				'from_name'  => $m['from_name'],
				'cc'         => $m['cc_list'],
				'reply_to'   => $m['reply_to'],
				'full_date'  => get_date_from_gmt( $m['mail_date'], 'd.m.Y H:i' ),
				'html'       => $html,
				'attachments' => $atts,
				'thread'     => count( $thread ) > 1 ? $thread : array(),
				'log_url'    => $m['log_id'] ? flxi_admin_url( 'flxi-kayitlar', array( 's' => $m['subject'] ) ) : '',
			)
		)
	);
}

function flxi_rest_mb_mark( WP_REST_Request $q ) {
	$f = sanitize_key( (string) $q['field'] );
	$map = array( 'read' => 'is_read', 'starred' => 'is_starred' );
	if ( ! isset( $map[ $f ] ) || ! flxi_mailbox_mark( (int) $q['id'], $map[ $f ], ! empty( $q['value'] ) && 'false' !== $q['value'] ) ) {
		return new WP_Error( 'flxi_bad', __( 'İşlem yapılamadı.', 'flx-mail' ), array( 'status' => 400 ) );
	}
	return array( 'ok' => true, 'status' => flxi_mailbox_status() );
}

function flxi_rest_mb_attachment( WP_REST_Request $q ) {
	$a = flxi_mailbox_attachment( (int) $q['id'], (string) $q['section'] );
	if ( is_wp_error( $a ) ) {
		return new WP_Error( 'flxi_att', $a->get_error_message(), array( 'status' => 404 ) );
	}
	$type = preg_match( '~^[a-z0-9.+-]+/[a-z0-9.+-]+$~i', $a['type'] ) ? $a['type'] : 'application/octet-stream';
	// Tarayıcıda yalnız güvenli türler açılır; HTML/SVG gibi betik çalıştırabilenler indirilir.
	$inline = preg_match( '~^(image/(png|jpe?g|gif|webp)|application/pdf|text/plain)$~i', $type );
	$name   = sanitize_file_name( $a['name'] ) ?: 'ek';
	nocache_headers();
	header( 'Content-Type: ' . ( $inline ? $type : 'application/octet-stream' ) );
	header( 'X-Content-Type-Options: nosniff' );
	header( "Content-Disposition: " . ( $inline ? 'inline' : 'attachment' ) . "; filename=\"" . str_replace( '"', '', $name ) . "\"; filename*=UTF-8''" . rawurlencode( $a['name'] ) );
	header( 'Content-Length: ' . strlen( $a['data'] ) );
	echo $a['data']; // phpcs:ignore
	exit;
}

function flxi_rest_mb_sync() {
	@set_time_limit( 60 ); // phpcs:ignore
	$r = flxi_mailbox_sync( 20 );
	return flxi_rest_mb_nocache( array_merge( $r, array( 'status' => flxi_mailbox_status() ) ) );
}

/** Bağlantı testi: giriş + klasörler (şifre döndürülmez). */
function flxi_rest_mb_test() {
	$imap = flxi_imap_open();
	if ( is_wp_error( $imap ) ) {
		return array( 'ok' => false, 'message' => $imap->get_error_message() );
	}
	$f   = flxi_mailbox_detect_folders( $imap );
	$sel = $imap->select( 'INBOX', true );
	$imap->logout();
	$st                     = flxi_mailbox_state();
	$st['names']            = $f;
	$st['names']['checked'] = time();
	flxi_mailbox_state_save( $st );
	return array(
		'ok'      => true,
		/* translators: 1: e-posta sayısı, 2: Gönderilmiş klasörü adı */
		'message' => sprintf( __( 'Bağlantı başarılı. Gelen kutusunda %1$s e-posta var. Gönderilmiş klasörü: %2$s', 'flx-mail' ), number_format_i18n( $sel ? $sel['exists'] : 0 ), $f['sent'] ? FLXI_Imap::utf7_decode( $f['sent'] ) : __( 'bulunamadı', 'flx-mail' ) ),
	);
}

function flxi_mailbox_draft_out( array $d ) {
	$orig = $d['reply_to_id'] ? flxi_mailbox_get( $d['reply_to_id'] ) : null;
	return array(
		'id'          => (int) $d['id'],
		'reply_to_id' => (int) $d['reply_to_id'],
		'reply_to'    => $orig ? array( 'id' => (int) $orig['id'], 'subject' => $orig['subject'], 'from' => $orig['from_name'] ? $orig['from_name'] : $orig['from_email'] ) : null,
		'mode'        => $d['mode'],
		'to'          => $d['to_list'],
		'cc'          => $d['cc_list'],
		'subject'     => $d['subject'],
		'body'        => $d['body'],
		'layout'      => $d['layout'],
		'status'      => $d['status'],
		'status_text' => flxi_draft_statuses()[ $d['status'] ] ?? $d['status'],
		'origin'      => $d['origin'],
		'note'        => $d['note'],
		'error'       => $d['error'],
		'date'        => flxi_mailbox_date( $d['updated_at'] ),
	);
}

function flxi_rest_mb_drafts( WP_REST_Request $q ) {
	$s = sanitize_key( (string) $q['status'] );
	return flxi_rest_mb_nocache(
		array(
			'rows'   => array_map( 'flxi_mailbox_draft_out', flxi_drafts_query( in_array( $s, array( 'draft', 'review', 'failed', 'sent' ), true ) ? $s : '' ) ),
			'status' => flxi_mailbox_status(),
		)
	);
}

function flxi_rest_mb_draft_get( WP_REST_Request $q ) {
	$d = flxi_draft_get( (int) $q['id'] );
	return $d ? flxi_rest_mb_nocache( flxi_mailbox_draft_out( $d ) ) : new WP_Error( 'flxi_404', __( 'Taslak bulunamadı.', 'flx-mail' ), array( 'status' => 404 ) );
}

/** Formdaki alanlar → taslak sütunları. */
function flxi_rest_mb_draft_fields( WP_REST_Request $q ) {
	$d = array();
	foreach ( array( 'to' => 'to_list', 'cc' => 'cc_list', 'subject' => 'subject', 'body' => 'body', 'layout' => 'layout' ) as $k => $col ) {
		if ( null !== $q[ $k ] ) {
			$d[ $col ] = (string) $q[ $k ];
		}
	}
	return $d;
}

function flxi_rest_mb_draft_save( WP_REST_Request $q ) {
	$id = (int) $q['id'];
	$d  = flxi_rest_mb_draft_fields( $q );
	if ( $id ) {
		$cur = flxi_draft_get( $id );
		if ( ! $cur || 'sent' === $cur['status'] ) {
			return new WP_Error( 'flxi_404', __( 'Taslak bulunamadı ya da gönderilmiş.', 'flx-mail' ), array( 'status' => 404 ) );
		}
		// Elle düzenlenen hatalı taslak yeniden taslak olur; AI taslağı onaylanana kadar "Onay bekliyor" kalır.
		if ( 'failed' === $cur['status'] ) {
			$d['status'] = 'draft';
			$d['error']  = '';
		}
	} else {
		$d['mode']   = 'new';
		$d['status'] = 'draft';
		$d['layout'] = $d['layout'] ?? 'kurumsal';
	}
	$id = flxi_draft_save( $d, $id );
	return flxi_mailbox_draft_out( flxi_draft_get( $id ) );
}

function flxi_rest_mb_draft_delete( WP_REST_Request $q ) {
	flxi_draft_delete( (int) $q['id'] );
	return array( 'ok' => true, 'status' => flxi_mailbox_status() );
}

function flxi_rest_mb_draft_send( WP_REST_Request $q ) {
	$id = (int) $q['id'];
	$d  = flxi_rest_mb_draft_fields( $q );
	if ( $d ) {
		flxi_draft_save( $d, $id );
	}
	$r = flxi_draft_send( $id );
	return array_merge( $r, array( 'status' => flxi_mailbox_status() ) );
}

function flxi_rest_mb_reply( WP_REST_Request $q ) {
	$mode = in_array( $q['mode'], array( 'reply', 'replyall', 'forward' ), true ) ? $q['mode'] : 'reply';
	$id   = flxi_mailbox_start_reply( (int) $q['mail_id'], $mode );
	if ( ! $id ) {
		return new WP_Error( 'flxi_404', __( 'E-posta bulunamadı.', 'flx-mail' ), array( 'status' => 404 ) );
	}
	return flxi_mailbox_draft_out( flxi_draft_get( $id ) );
}

function flxi_rest_mb_preview( WP_REST_Request $q ) {
	$d = array_merge(
		array(
			'reply_to_id' => (int) $q['reply_to_id'],
			'mode'        => sanitize_key( (string) $q['mode'] ),
		),
		flxi_rest_mb_draft_fields( $q )
	);
	if ( ! empty( $q['layout_data'] ) && is_array( $q['layout_data'] ) ) {
		// Kaydedilmemiş alt şablonun önizlemesi.
		$add = function ( $all ) use ( $q ) {
			$all             = is_array( $all ) ? $all : array();
			$all['lpreview'] = (array) $q['layout_data'];
			return $all;
		};
		add_filter( 'option_flxi_layouts', $add );
		add_filter( 'default_option_flxi_layouts', $add );
		$d['layout'] = 'lpreview';
		if ( empty( $d['body'] ) ) {
			$d['body'] = wp_kses_post( (string) ( $q['layout_data']['body'] ?? '' ) );
		}
		if ( empty( $d['subject'] ) ) {
			$d['subject'] = (string) ( $q['layout_data']['subject'] ?? '' );
		}
	}
	return flxi_rest_mb_nocache( array( 'html' => flxi_draft_html( $d ) ) );
}

/**
 * AI: cevap taslağı (mail_id), yeni e-posta (instruction), mevcut taslağı düzelt (draft_id).
 */
function flxi_rest_mb_ai( WP_REST_Request $q ) {
	@set_time_limit( 60 ); // phpcs:ignore
	$instruction = sanitize_textarea_field( (string) $q['instruction'] );
	$draft_id    = (int) $q['draft_id'];
	if ( $draft_id ) {
		$cur = flxi_draft_get( $draft_id );
		if ( ! $cur ) {
			return new WP_Error( 'flxi_404', __( 'Taslak bulunamadı.', 'flx-mail' ), array( 'status' => 404 ) );
		}
		$body = null !== $q['body'] ? (string) $q['body'] : (string) $cur['body'];
		$w    = flxi_mailbox_ai_write(
			array(
				'mail_id'     => (int) $cur['reply_to_id'],
				'instruction' => $instruction,
				'current'     => trim( wp_strip_all_tags( $body ) ) ? $body : '',
				'subject'     => null !== $q['subject'] ? (string) $q['subject'] : $cur['subject'],
				'to'          => null !== $q['to'] ? (string) $q['to'] : $cur['to_list'],
			)
		);
		if ( is_wp_error( $w ) ) {
			return new WP_Error( 'flxi_ai', $w->get_error_message(), array( 'status' => 400 ) );
		}
		$upd = array(
			'body'   => $w['body'],
			'origin' => 'ai',
		);
		if ( ! $cur['reply_to_id'] && $w['subject'] && '' === trim( (string) ( null !== $q['subject'] ? $q['subject'] : $cur['subject'] ) ) ) {
			$upd['subject'] = $w['subject'];
		}
		flxi_draft_save( $upd, $draft_id );
		return flxi_mailbox_draft_out( flxi_draft_get( $draft_id ) );
	}
	if ( (int) $q['mail_id'] ) {
		$id = flxi_mailbox_ai_reply_draft( (int) $q['mail_id'], $instruction, in_array( $q['mode'], array( 'reply', 'replyall' ), true ) ? $q['mode'] : 'reply' );
	} else {
		$w = flxi_mailbox_ai_write(
			array(
				'instruction' => $instruction,
				'to'          => (string) $q['to'],
			)
		);
		$id = is_wp_error( $w ) ? $w : flxi_draft_save(
			array(
				'mode'    => 'new',
				'to_list' => (string) $q['to'],
				'subject' => $w['subject'],
				'body'    => $w['body'],
				'layout'  => flxi_layout_exists( (string) $q['layout'] ) ? (string) $q['layout'] : 'kurumsal',
				'status'  => 'review',
				'origin'  => 'ai',
				'note'    => mb_substr( $instruction, 0, 500 ),
			)
		);
	}
	if ( is_wp_error( $id ) ) {
		return new WP_Error( 'flxi_ai', $id->get_error_message(), array( 'status' => 400 ) );
	}
	return flxi_mailbox_draft_out( flxi_draft_get( $id ) );
}

function flxi_rest_mb_layouts() {
	$out = array();
	foreach ( flxi_layouts() as $id => $l ) {
		$out[] = array_merge( $l, array( 'id' => $id ) );
	}
	return flxi_rest_mb_nocache( $out );
}

function flxi_rest_mb_layout_save( WP_REST_Request $q ) {
	$id = flxi_layout_save( (array) $q->get_params(), sanitize_key( (string) $q['id'] ) );
	return array( 'id' => $id, 'layouts' => flxi_rest_mb_layouts()->get_data() );
}

function flxi_rest_mb_layout_delete( WP_REST_Request $q ) {
	flxi_layout_delete( sanitize_key( (string) $q['id'] ) );
	return array( 'ok' => true, 'layouts' => flxi_rest_mb_layouts()->get_data() );
}
