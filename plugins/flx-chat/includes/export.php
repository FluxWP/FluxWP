<?php
/**
 * Sohbet dışa aktarma (tek sohbet ya da toplu, .md / .json) ve yöneticiye özel sohbet geçmişini temizleme.
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_flxc_export', 'flxc_export_download' );

/** Mesajın yazarı (dışa aktarmada). */
function flxc_export_author( array $m, array $meta ) {
	if ( 'in' === $m['direction'] ) {
		return ! empty( $meta['sender'] ) ? (string) $meta['sender'] : __( 'Kişi', 'flx-chat' );
	}
	if ( 'system' === $m['direction'] ) {
		return __( 'Sistem', 'flx-chat' );
	}
	$user = flxc_user_name( $m['user_id'] );
	switch ( (string) $m['source'] ) {
		case 'bot':
			return __( 'Asistan', 'flx-chat' );
		case 'agent':
			/* translators: %s: agent name */
			return '' !== $user ? sprintf( __( 'Temsilci (%s)', 'flx-chat' ), $user ) : __( 'Temsilci', 'flx-chat' );
		case 'note':
			/* translators: %s: agent name */
			return '' !== $user ? sprintf( __( 'Dahili not (%s)', 'flx-chat' ), $user ) : __( 'Dahili not', 'flx-chat' );
		case 'auto':
			return __( 'Otomatik yanıt', 'flx-chat' );
		case '':
		case 'phone':
			return __( 'Telefondan', 'flx-chat' );
		case 'certificate':
			return 'Flux Certificates';
		default:
			return __( 'Otomatik', 'flx-chat' );
	}
}

/** Numara maskesi: 905321234567 → 90532•••4567. Metindeki numaralara da uygulanır. */
function flxc_export_mask_phone( $p ) {
	$p = (string) $p;
	$n = strlen( $p );
	return $n > 7 ? substr( $p, 0, $n - 7 ) . '•••' . substr( $p, -4 ) : $p;
}

function flxc_export_mask_text( $s ) {
	$s = preg_replace_callback(
		'/\+?\d[\d \-]{8,}\d/',
		function ( $x ) {
			$d = preg_replace( '/\D/', '', $x[0] );
			return strlen( $d ) >= 10 && strlen( $d ) <= 13 ? flxc_export_mask_phone( $d ) : $x[0];
		},
		(string) $s
	);
	return preg_replace( '/([A-Za-z0-9._%+-])[A-Za-z0-9._%+-]*@([A-Za-z0-9.-]+\.[A-Za-z]{2,})/', '$1•••@$2', $s );
}

/** Bir sohbet: başlık bilgileri + tüm mesajlar (eskiden yeniye). */
function flxc_export_chat( array $chat, array $o ) {
	global $wpdb;
	$t     = flxc_table( 'messages' );
	$state = function_exists( 'flxc_bot_state' ) ? flxc_bot_state( $chat ) : array();
	$phone = (string) $chat['phone'];
	$out   = array(
		'id'        => (int) $chat['id'],
		'title'     => $o['mask'] && '' === (string) $chat['name'] && '' === (string) $chat['push_name'] ? flxc_export_mask_phone( $phone ) : flxc_chat_title( $chat ),
		'phone'     => $o['mask'] ? flxc_export_mask_phone( $phone ) : $phone,
		'status'    => (string) $chat['status'],
		'assistant' => ! empty( $state['ai'] ),
		'created'   => (string) $chat['created_at'],
		'messages'  => array(),
	);
	$after = 0;
	$ts    = -1;
	do {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE chat_id = %d AND type <> 'reaction' AND (ts > %d OR (ts = %d AND id > %d)) ORDER BY ts, id LIMIT 500", $chat['id'], $ts, $ts, $after ), ARRAY_A ); // phpcs:ignore
		foreach ( $rows as $m ) {
			$after = (int) $m['id'];
			$ts    = (int) $m['ts'];
			if ( 'note' === $m['source'] && ! $o['notes'] ) {
				continue;
			}
			$meta = flxc_decode( $m['meta'] );
			$type = (string) $m['type'];
			$body = (string) $m['body'];
			$del  = 'deleted' === $type;
			if ( $del && ! empty( $meta['deleted_type'] ) && 'deleted' !== $meta['deleted_type'] ) {
				$type = (string) $meta['deleted_type'];
				$body = (string) ( $meta['deleted_body'] ?? '' );
			}
			$row = array(
				'time'   => wp_date( 'Y-m-d H:i', (int) floor( (int) $m['ts'] / 1000 ) ),
				'author' => flxc_export_author( $m, $meta ),
				'type'   => $type,
				'text'   => $o['mask'] ? flxc_export_mask_text( $body ) : $body,
			);
			if ( '' !== (string) $m['media_name'] ) {
				$row['file'] = (string) $m['media_name'];
			}
			if ( $del ) {
				$row['deleted'] = true;
			}
			if ( ! empty( $meta['edited'] ) ) {
				$row['edited'] = true;
			}
			if ( 'failed' === $m['status'] ) {
				$row['failed'] = true;
			}
			if ( ! empty( $meta['rcheck']['verdict'] ) && 'skipped' !== $meta['rcheck']['verdict'] && function_exists( 'flxc_rcheck_summary' ) ) {
				$row['receipt_ai'] = flxc_rcheck_summary( $meta['rcheck'] );
			}
			$out['messages'][] = $row;
		}
	} while ( count( $rows ) === 500 );
	return $out;
}

function flxc_export_md( array $c ) {
	$s  = '## ' . $c['title'] . "\n\n";
	$s .= '- ' . __( 'Telefon', 'flx-chat' ) . ': ' . ( '' !== $c['phone'] ? '+' . $c['phone'] : '-' ) . "\n";
	$s .= '- ' . __( 'Asistan yazdı', 'flx-chat' ) . ': ' . ( $c['assistant'] ? __( 'Evet', 'flx-chat' ) : __( 'Hayır', 'flx-chat' ) ) . "\n";
	$s .= '- ' . __( 'Mesaj', 'flx-chat' ) . ': ' . count( $c['messages'] ) . "\n\n";
	foreach ( $c['messages'] as $m ) {
		$flags = array();
		if ( 'text' !== $m['type'] ) {
			$flags[] = $m['type'] . ( isset( $m['file'] ) ? ': ' . $m['file'] : '' );
		}
		if ( ! empty( $m['deleted'] ) ) {
			$flags[] = __( 'kişi sildi', 'flx-chat' );
		}
		if ( ! empty( $m['edited'] ) ) {
			$flags[] = __( 'düzenlendi', 'flx-chat' );
		}
		if ( ! empty( $m['failed'] ) ) {
			$flags[] = __( 'gönderilemedi', 'flx-chat' );
		}
		$text = str_replace( "\n", "\n  ", trim( $m['text'] ) );
		$s   .= '- **' . $m['time'] . ' · ' . $m['author'] . '**' . ( $flags ? ' _(' . implode( ', ', $flags ) . ')_' : '' ) . ( '' !== $text ? ': ' . $text : '' ) . "\n";
		if ( isset( $m['receipt_ai'] ) ) {
			$s .= '  - ' . __( 'Dekont kontrolü', 'flx-chat' ) . ': ' . $m['receipt_ai'] . "\n";
		}
	}
	return $s . "\n";
}

/** Toplu dışa aktarmada seçilecek sohbetler (en yeni önce, en çok 1000). */
function flxc_export_chat_ids( array $o ) {
	global $wpdb;
	$ct    = flxc_table( 'chats' );
	$mt    = flxc_table( 'messages' );
	$where = array( "jid NOT LIKE '%@g.us'" );
	if ( $o['days'] > 0 ) {
		$where[] = $wpdb->prepare( 'last_ts >= %d', ( time() - $o['days'] * DAY_IN_SECONDS ) * 1000 );
	}
	if ( 'bot' === $o['who'] ) {
		$where[] = "meta LIKE '%\"ai\":1%'";
	} elseif ( 'team' === $o['who'] ) {
		$where[] = "EXISTS (SELECT 1 FROM $mt m WHERE m.chat_id = $ct.id AND m.source = 'agent')";
	}
	return array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM $ct WHERE " . implode( ' AND ', $where ) . ' ORDER BY last_ts DESC LIMIT 1000' ) ); // phpcs:ignore
}

function flxc_export_download() {
	check_admin_referer( 'flxc_export' );
	$chat_id = isset( $_GET['chat'] ) ? absint( $_GET['chat'] ) : 0;
	if ( ! ( $chat_id ? flxc_can_use() : flxc_can_manage() ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-chat' ), '', array( 'response' => 403 ) );
	}
	// phpcs:disable WordPress.Security.NonceVerification
	$o = array(
		'format' => isset( $_GET['format'] ) && 'json' === $_GET['format'] ? 'json' : 'md',
		'mask'   => ! isset( $_GET['mask'] ) || '0' !== $_GET['mask'],
		'notes'  => ! isset( $_GET['notes'] ) || '0' !== $_GET['notes'],
		'days'   => isset( $_GET['days'] ) ? min( 3650, absint( $_GET['days'] ) ) : 30,
		'who'    => isset( $_GET['who'] ) && in_array( $_GET['who'], array( 'bot', 'team' ), true ) ? sanitize_key( $_GET['who'] ) : 'all',
	);
	// phpcs:enable
	if ( $chat_id ) {
		if ( ! flxc_get_chat( $chat_id ) ) {
			wp_die( esc_html__( 'Sohbet bulunamadı.', 'flx-chat' ), '', array( 'response' => 404 ) );
		}
		$ids = array( $chat_id );
	} else {
		$ids = flxc_export_chat_ids( $o );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore
	}
	$name = 'sohbet' . ( $chat_id ? '-' . $chat_id : 'ler' ) . '-' . wp_date( 'Ymd-Hi' ) . '.' . $o['format'];
	nocache_headers();
	header( 'Content-Type: ' . ( 'json' === $o['format'] ? 'application/json' : 'text/markdown' ) . '; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $name . '"' );
	header( 'X-LiteSpeed-Cache-Control: no-cache' );
	$head = array(
		'site'        => flxc_brand_name(),
		'exported_at' => wp_date( 'Y-m-d H:i' ),
		'chats'       => count( $ids ),
		'masked'      => $o['mask'],
	);
	if ( 'json' === $o['format'] ) {
		echo substr( wp_json_encode( $head, JSON_UNESCAPED_UNICODE ), 0, -1 ) . ',"items":['; // phpcs:ignore
	} else {
		// İndirilen düz metin (HTML değil).
		echo '# ' . flxc_brand_name() . ' · ' . __( 'WhatsApp sohbetleri', 'flx-chat' ) . "\n\n"; // phpcs:ignore
		/* translators: 1: date, 2: chat count */
		echo sprintf( __( 'Dışa aktarma: %1$s · %2$d sohbet', 'flx-chat' ), $head['exported_at'], count( $ids ) ) . ( $o['mask'] ? ' · ' . __( 'telefon ve e-postalar maskeli', 'flx-chat' ) : '' ) . "\n\n"; // phpcs:ignore
	}
	foreach ( $ids as $i => $id ) {
		$chat = flxc_get_chat( $id );
		if ( ! $chat ) {
			continue;
		}
		$c = flxc_export_chat( $chat, $o );
		if ( 'json' === $o['format'] ) {
			echo ( $i ? ',' : '' ) . wp_json_encode( $c, JSON_UNESCAPED_UNICODE ); // phpcs:ignore
		} else {
			echo flxc_export_md( $c ); // phpcs:ignore -- indirilen düz metin
		}
		if ( 0 === $i % 20 ) {
			flush();
		}
	}
	if ( 'json' === $o['format'] ) {
		echo ']}';
	}
	exit;
}

/**
 * REST karşılığı (1.35.0): POST flx-chat/v1/chats/{id}/export { format: md|json, mask, notes } → dosya.
 * Yeni arayüz ve uygulama indirme için kullanır; mask ve notes verilmezse açık (panel indirmesiyle aynı).
 */
function flxc_rest_export( WP_REST_Request $req ) {
	$chat = flxc_rest_chat_or_error( $req['id'] );
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	$o = array(
		'format' => 'json' === $req->get_param( 'format' ) ? 'json' : 'md',
		'mask'   => null === $req->get_param( 'mask' ) || rest_sanitize_boolean( $req->get_param( 'mask' ) ),
		'notes'  => null === $req->get_param( 'notes' ) || rest_sanitize_boolean( $req->get_param( 'notes' ) ),
	);
	$c    = flxc_export_chat( $chat, $o );
	$name = 'sohbet-' . (int) $chat['id'] . '-' . wp_date( 'Ymd-Hi' ) . '.' . $o['format'];
	if ( 'json' === $o['format'] ) {
		$body = wp_json_encode(
			array(
				'site'        => flxc_brand_name(),
				'exported_at' => wp_date( 'Y-m-d H:i' ),
				'chats'       => 1,
				'masked'      => $o['mask'],
				'items'       => array( $c ),
			),
			JSON_UNESCAPED_UNICODE
		);
	} else {
		/* translators: 1: date, 2: chat count */
		$body = '# ' . flxc_brand_name() . ' · ' . __( 'WhatsApp sohbetleri', 'flx-chat' ) . "\n\n" . sprintf( __( 'Dışa aktarma: %1$s · %2$d sohbet', 'flx-chat' ), wp_date( 'Y-m-d H:i' ), 1 ) . ( $o['mask'] ? ' · ' . __( 'telefon ve e-postalar maskeli', 'flx-chat' ) : '' ) . "\n\n" . flxc_export_md( $c );
	}
	nocache_headers();
	header( 'Content-Type: ' . ( 'json' === $o['format'] ? 'application/json' : 'text/markdown' ) . '; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $name . '"' );
	header( 'Content-Length: ' . strlen( $body ) );
	header( 'X-LiteSpeed-Cache-Control: no-cache' );
	echo $body; // phpcs:ignore -- indirilen düz metin / JSON
	exit;
}

/* ---------- Sohbet geçmişini temizle (yalnız yönetici) ---------- */

/**
 * Sohbetin mesajlarını ve medya dosyalarını siler; sohbet satırı (kişi, not, etiket) kalır.
 * Evolution'dan geçmiş yeniden içe aktarılırken temizlenen ana kadarki mesajlar atlanır (meta.cleared).
 * $keep_receipts: bağış kayıtlarına eklenmiş dekont mesajları silinmez.
 * @return int silinen mesaj sayısı
 */
function flxc_clear_chat_history( $chat_id, $keep_receipts = true ) {
	global $wpdb;
	$chat = flxc_get_chat( $chat_id );
	if ( ! $chat ) {
		return 0;
	}
	$mt   = flxc_table( 'messages' );
	$keep = $keep_receipts ? array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT message_id FROM ' . flxc_table( 'receipts' ) . ' WHERE chat_id = %d', $chat_id ) ) ) : array(); // phpcs:ignore
	$not  = $keep ? ' AND id NOT IN (' . implode( ',', $keep ) . ')' : '';
	$dir  = flxc_media_dir();
	foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT media_path FROM $mt WHERE chat_id = %d AND media_path <> ''$not", $chat_id ) ) as $p ) { // phpcs:ignore
		$f = $dir . '/' . $p;
		if ( '' !== $p && false === strpos( $p, '..' ) && is_file( $f ) ) {
			wp_delete_file( $f );
		}
	}
	$n = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM $mt WHERE chat_id = %d$not", $chat_id ) ); // phpcs:ignore
	if ( ! $keep_receipts ) {
		$wpdb->delete( flxc_table( 'receipts' ), array( 'chat_id' => (int) $chat_id ) );
	}
	$meta = flxc_decode( $chat['meta'] );
	$bot  = isset( $meta['bot'] ) && is_array( $meta['bot'] ) ? $meta['bot'] : array();
	// Asistan sıfırdan başlar; kapatılmışsa kapalı kalır.
	$meta['bot']     = array_intersect_key( $bot, array_flip( array( 'off', 'until', 'ai' ) ) );
	$meta['cleared'] = (int) floor( microtime( true ) * 1000 );
	$meta['hist']    = array( 'page' => 0, 'done' => true, 'per' => 50 );
	flxc_update_chat( $chat_id, array( 'meta' => flxc_json( $meta ), 'unread' => 0, 'last_msg_id' => 0 ) );
	/* translators: 1: message count, 2: user name */
	flxc_system_message( flxc_get_chat( $chat_id ), sprintf( __( 'Sohbet geçmişi temizlendi (%1$d mesaj, %2$s).', 'flx-chat' ), $n, flxc_user_name( get_current_user_id() ) ) );
	do_action( 'flxc_chat_cleared', $chat_id, $n );
	return $n;
}

/** Temizlenen sohbete Evolution geçmişinden eski mesaj geri gelmesin. */
function flxc_chat_cleared_before( $chat, $ts ) {
	$meta = flxc_decode( $chat['meta'] ?? '' );
	return ! empty( $meta['cleared'] ) && (int) $ts <= (int) $meta['cleared'];
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'flx-chat/v1',
			'/chats/(?P<id>\d+)/clear',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return flxc_can_manage();
				},
				'callback'            => function ( WP_REST_Request $req ) {
					$id   = (int) $req['id'];
					$chat = flxc_get_chat( $id );
					if ( ! $chat ) {
						return new WP_Error( 'flxc_404', __( 'Sohbet bulunamadı.', 'flx-chat' ), array( 'status' => 404 ) );
					}
					// Arayüz onay kelimesini yazdırır; uca yanlışlıkla gelen istek silmesin.
					if ( 'yes' !== (string) $req->get_param( 'confirm' ) ) {
						return new WP_Error( 'flxc_confirm', __( 'Onay metni yanlış.', 'flx-chat' ), array( 'status' => 400 ) );
					}
					$n = flxc_clear_chat_history( $id, (bool) $req->get_param( 'keep_receipts' ) );
					if ( $req->get_param( 'forget_memory' ) && function_exists( 'flxc_mem_forget' ) ) {
						flxc_mem_forget( $chat );
					}
					return array(
						'deleted' => $n,
						'chat'    => flxc_chat_for_js( flxc_get_chat( $id ) ),
					);
				},
			)
		);
	}
);
