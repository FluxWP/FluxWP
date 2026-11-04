<?php
/**
 * "AI ile düzenle" (1.10.0): talimatı yöneticiyle birlikte düzenleyen ajan.
 *
 * - Oturumlar ayrı saklanır (flxc_editor_sessions): yeniden açılır, adı değişir, arşive alınır, silinir.
 * - Ajan sohbet arar (find_chats), yöneticinin onayıyla bir sohbetin son mesajlarını okur (read_chat),
 *   taslak önerir (propose_prompt) ve taslağı test sohbetinde kendisi dener (test_message).
 * - Hostinger ~60 sn'de isteği kestiği için her istek tek ağır iş yapar (bir AI çağrısı ya da bir test
 *   turu); istemci "done" gelene kadar /bot/editor/step'i tekrar çağırır.
 * - Yayına alma her zaman yöneticide: taslak onay kartıyla sunulur, mevcut prompt kaydetme akışı kullanılır.
 *
 * @package FLX_Cagri_Merkezi
 */

defined( 'ABSPATH' ) || exit;

/** Bir kullanıcı mesajından sonra en fazla bu kadar adım (AI çağrısı + araç). */
const FLXC_EDITOR_MAX_STEPS = 24;

/* ---------- Oturum deposu ---------- */

function flxc_editor_blank( $base ) {
	return array(
		'msgs'       => array(),
		'view'       => array(),
		'base'       => (string) $base,
		'draft'      => null,
		'test'       => array( 'history' => array(), 'state' => array(), 'bg' => null ),
		'queue'      => array(),
		'pending'    => null,
		'allow_read' => false,
		'read'       => array(),
		'usage'      => array( 'in' => 0, 'out' => 0, 'cost' => 0.0, 'calls' => 0 ),
		'steps'      => 0,
	);
}

function flxc_editor_get( $id ) {
	global $wpdb;
	$t   = flxc_table( 'editor_sessions' );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore
	if ( ! $row || ( (int) $row['user_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) ) {
		return null;
	}
	$data        = json_decode( (string) $row['data'], true );
	$row['data'] = array_merge( flxc_editor_blank( '' ), is_array( $data ) ? $data : array() );
	return $row;
}

function flxc_editor_save( array $s ) {
	global $wpdb;
	$t    = flxc_table( 'editor_sessions' );
	$now  = current_time( 'mysql', true );
	$data = wp_json_encode( $s['data'], JSON_UNESCAPED_UNICODE );
	if ( empty( $s['id'] ) ) {
		$wpdb->insert(
			$t,
			array(
				'user_id'    => get_current_user_id(),
				'title'      => mb_substr( (string) $s['title'], 0, 190 ),
				'status'     => 'active',
				'data'       => $data,
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
		$s['id'] = (int) $wpdb->insert_id;
	} else {
		$wpdb->update(
			$t,
			array(
				'title'      => mb_substr( (string) $s['title'], 0, 190 ),
				'status'     => 'archived' === $s['status'] ? 'archived' : 'active',
				'data'       => $data,
				'updated_at' => $now,
			),
			array( 'id' => (int) $s['id'] )
		);
	}
	$s['updated_at'] = $now;
	return $s;
}

function flxc_editor_list( $status = 'active', $q = '' ) {
	global $wpdb;
	$t     = flxc_table( 'editor_sessions' );
	$where = array( $wpdb->prepare( 'user_id = %d', get_current_user_id() ) );
	if ( in_array( $status, array( 'active', 'archived' ), true ) ) {
		$where[] = $wpdb->prepare( 'status = %s', $status );
	}
	if ( '' !== $q ) {
		$where[] = $wpdb->prepare( 'title LIKE %s', '%' . $wpdb->esc_like( $q ) . '%' );
	}
	$rows = $wpdb->get_results( "SELECT id, title, status, created_at, updated_at, data FROM $t WHERE " . implode( ' AND ', $where ) . ' ORDER BY updated_at DESC, id DESC LIMIT 100', ARRAY_A ); // phpcs:ignore
	$out  = array();
	foreach ( $rows as $r ) {
		$d     = json_decode( (string) $r['data'], true );
		$out[] = array(
			'id'       => (int) $r['id'],
			'title'    => (string) $r['title'],
			'status'   => (string) $r['status'],
			'updated'  => flxc_local_date( $r['updated_at'] ),
			'draft'    => is_array( $d ) && null !== ( $d['draft'] ?? null ),
		);
	}
	return $out;
}

/** İstemciye giden oturum (ham model mesajları hariç). */
function flxc_editor_for_js( array $s ) {
	$d = $s['data'];
	return array(
		'id'        => (int) $s['id'],
		'title'     => (string) $s['title'],
		'status'    => (string) $s['status'],
		'view'      => $d['view'],
		'base'      => $d['base'],
		'draft'     => $d['draft'],
		'test'      => array(
			'history' => $d['test']['history'],
			'bg'      => $d['test']['bg'],
		),
		'pending'   => $d['pending'],
		'allowRead' => ! empty( $d['allow_read'] ),
		'usage'     => $d['usage'],
	);
}

/* ---------- Ajanın araçları ---------- */

function flxc_editor_tools() {
	$f = function ( $name, $desc, $props, $req = array() ) {
		return array(
			'type'     => 'function',
			'function' => array(
				'name'        => $name,
				'description' => $desc,
				'parameters'  => array( 'type' => 'object', 'properties' => (object) $props, 'required' => $req ),
			),
		);
	};
	return array(
		$f( 'find_chats', 'WhatsApp sohbetlerini kişi adı ya da telefon numarasıyla arar. Mesaj içeriği dönmez.', array( 'query' => array( 'type' => 'string' ) ), array( 'query' ) ),
		$f(
			'read_chat',
			'Bir sohbetin son mesajlarını okur (kişi, asistan, temsilci). Yöneticinin onayı istenir. Önce limit 10 ile oku; yetmezse 20.',
			array(
				'chat_id' => array( 'type' => 'integer' ),
				'limit'   => array( 'type' => 'integer', 'description' => '10, gerekirse 20 (en fazla 40)' ),
				'why'     => array( 'type' => 'string', 'description' => 'Yöneticiye gösterilecek kısa neden' ),
			),
			array( 'chat_id' )
		),
		$f(
			'propose_prompt',
			'Talimatın TAM yeni metnini taslak olarak kaydeder (yayına almaz). Her değişiklikten sonra çağır.',
			array(
				'prompt'  => array( 'type' => 'string' ),
				'summary' => array( 'type' => 'string', 'description' => 'Değişikliğin kısa özeti' ),
			),
			array( 'prompt', 'summary' )
		),
		$f(
			'test_message',
			'Taslağı test sohbetinde dener: kişi gibi bir mesaj yazar, asistanın cevaplarını ve kullandığı araçları döndürür. Hiçbir şey kaydedilmez, kimseye mesaj gitmez.',
			array(
				'text'  => array( 'type' => 'string', 'description' => 'Kişinin yazacağı mesaj' ),
				'reset' => array( 'type' => 'boolean', 'description' => 'Yeni bir test sohbeti başlat' ),
			),
			array( 'text' )
		),
	);
}

function flxc_editor_system( array $d ) {
	$docs = array();
	foreach ( flxc_bot_docs() as $doc ) {
		$docs[] = '- ' . $doc['slug'] . ' (' . $doc['mode'] . '): ' . $doc['title'];
	}
	$tools = array();
	foreach ( flxc_bot_tools() as $t ) {
		$tools[] = $t['function']['name'];
	}
	$cur = null !== $d['draft'] ? $d['draft'] : $d['base'];
	return "Sen bir WhatsApp bağış asistanının talimat (system prompt) metnini düzenleyen yardımcısın; yöneticiyle birlikte çalışan bir talimat düzenleme ajanısın.\n"
		. "Çalışma şekli:\n"
		. "1. Yönetici bir sohbetten ya da kişiden bahsederse find_chats ile bul, read_chat ile önce son 10 mesajı oku; yetmezse 20. Okuma yöneticinin onayına sunulur; izin verilmezse okumadan devam et.\n"
		. "2. Sorunu kısaca tespit et, talimatı güncelle ve propose_prompt ile TAM metni taslak olarak kaydet.\n"
		. "3. test_message ile taslağı 1-3 gerçekçi mesajla dene (ilkinde reset=true). Asistan beklendiği gibi davranmıyorsa taslağı düzeltip tekrar dene.\n"
		. "4. Bitince yöneticiye kısaca ne değiştirdiğini ve testte ne gördüğünü yaz; taslak onayına sunulur. Yayına almak yöneticinin işi.\n"
		. "Kurallar:\n"
		. "- Yalnız istenen değişikliği yap; geri kalan metni, başlıkları ve sırayı koru.\n"
		. "- {brand} ve {{belge:kisa-ad}} yer tutucularını koru; yeni belgeye referans gerekiyorsa {{belge:kisa-ad}} yaz.\n"
		. "- Fiyat, IBAN, bağış türleri ve saat asistanın tool'larıyla (donation_options, payment_info, current_time) canlı alınır, kişinin bilgileri koddan eklenir; bunları metne yazma.\n"
		. '- Asistanın araçları: ' . implode( ', ', $tools ) . ".\n"
		. "- Sohbetlerden okuduğun kişisel bilgileri (ad, telefon, e-posta) talimata yazma; genel kural çıkar.\n"
		. "- Yöneticiyle onun yazdığı dilde, kısa konuş.\n\n"
		. ( $docs ? "Mevcut belgeler:\n" . implode( "\n", $docs ) . "\n\n" : '' )
		. ( null !== $d['draft'] ? "Şu anki TASLAK (henüz yayında değil):\n" : "Yayındaki talimat metni:\n" )
		. "<<<\n" . $cur . "\n>>>";
}

function flxc_editor_find_chats( $q ) {
	global $wpdb;
	$q = trim( (string) $q );
	if ( '' === $q ) {
		return array( 'ok' => false, 'error' => 'query gerekli.' );
	}
	$t     = flxc_table( 'chats' );
	$like  = '%' . $wpdb->esc_like( $q ) . '%';
	$digit = preg_replace( '/\D/', '', $q );
	$sql   = "SELECT * FROM $t WHERE name LIKE %s OR push_name LIKE %s";
	$args  = array( $like, $like );
	if ( strlen( $digit ) >= 4 ) {
		// 0532… / +90 532… / 532… aynı numara.
		$sql   .= ' OR phone LIKE %s';
		$args[] = '%' . ltrim( substr( $digit, -10 ), '0' ) . '%';
	}
	$rows = $wpdb->get_results( $wpdb->prepare( $sql . ' ORDER BY last_ts DESC LIMIT 10', $args ), ARRAY_A ); // phpcs:ignore
	$out  = array();
	foreach ( $rows as $c ) {
		$out[] = array(
			'chat_id' => (int) $c['id'],
			'title'   => flxc_chat_title( $c ),
			'phone'   => $c['phone'] ? flxc_format_phone( $c['phone'] ) : '',
			'last'    => $c['last_ts'] ? flx_date( (int) floor( $c['last_ts'] / 1000 ), 'datetime' ) : '',
		);
	}
	return $out ? array( 'ok' => true, 'chats' => $out ) : array( 'ok' => true, 'chats' => array(), 'note' => 'Bulunamadı; yöneticiye başka ad ya da numara sor.' );
}

function flxc_editor_read_chat( $id, $limit ) {
	$chat = flxc_get_chat( (int) $id );
	if ( ! $chat ) {
		return array( 'ok' => false, 'error' => 'Sohbet yok.' );
	}
	$limit = max( 1, min( 40, (int) $limit ? (int) $limit : 10 ) );
	$rows  = array_reverse( flxc_chat_messages( (int) $chat['id'], 0, $limit + 10 ) );
	$out   = array();
	foreach ( $rows as $m ) {
		if ( 'reaction' === $m['type'] || 'deleted' === $m['type'] ) {
			continue;
		}
		if ( 'in' === $m['direction'] ) {
			$who = 'kişi';
		} elseif ( 'out' === $m['direction'] ) {
			$who = 'bot' === $m['source'] ? 'asistan' : 'temsilci';
		} else {
			$who = 'sistem';
		}
		$text = 'text' === $m['type'] ? (string) $m['body'] : '[' . flxc_preview_text( $m ) . ']' . ( '' !== trim( (string) $m['body'] ) ? ' ' . $m['body'] : '' );
		$out[] = array(
			'time' => flx_date( (int) floor( $m['ts'] / 1000 ), 'j M H:i' ),
			'from' => $who,
			'text' => mb_substr( $text, 0, 1500 ),
		);
	}
	$out = array_slice( $out, -$limit );
	return array(
		'ok'       => true,
		'title'    => flxc_chat_title( $chat ),
		'messages' => $out,
		'note'     => count( $out ) < $limit ? 'Sohbetin tamamı bu.' : 'Daha eskisi gerekirse limit 20 ile tekrar oku.',
	);
}

/** Bir araç çağrısını çalıştırır; görünüm öğesini $view'a ekler. */
function flxc_editor_run_tool( array &$d, array $c, $approved ) {
	$name = $c['name'];
	$a    = $c['args'];
	switch ( $name ) {
		case 'find_chats':
			$r = flxc_editor_find_chats( $a['query'] ?? '' );
			/* translators: 1: query, 2: count */
			$d['view'][] = array( 't' => 'tool', 'text' => sprintf( __( 'Sohbet arandı: "%1$s" (%2$d sonuç)', 'flx-chat' ), (string) ( $a['query'] ?? '' ), count( (array) ( $r['chats'] ?? array() ) ) ) );
			return $r;

		case 'read_chat':
			$r = flxc_editor_read_chat( (int) ( $a['chat_id'] ?? 0 ), (int) ( $a['limit'] ?? 10 ) );
			if ( ! empty( $r['ok'] ) ) {
				$d['read'][] = (int) $a['chat_id'];
				/* translators: 1: chat title, 2: count */
				$d['view'][] = array( 't' => 'tool', 'text' => sprintf( __( '%1$s sohbetinin son %2$d mesajı okundu', 'flx-chat' ), $r['title'], count( $r['messages'] ) ) );
			}
			return $r;

		case 'propose_prompt':
			$p = trim( (string) ( $a['prompt'] ?? '' ) );
			if ( mb_strlen( $p ) < 40 ) {
				return array( 'ok' => false, 'error' => 'Talimatın TAM metnini ver.' );
			}
			$d['draft']  = $p;
			$d['view'][] = array( 't' => 'proposal', 'text' => (string) ( $a['summary'] ?? '' ) );
			return array( 'ok' => true, 'note' => 'Taslak kaydedildi (yayında değil). Şimdi test_message ile dene.' );

		case 'test_message':
			$text = trim( (string) ( $a['text'] ?? '' ) );
			if ( '' === $text ) {
				return array( 'ok' => false, 'error' => 'text gerekli.' );
			}
			if ( ! empty( $a['reset'] ) ) {
				$d['test'] = array( 'history' => array(), 'state' => array(), 'bg' => null );
			}
			$hist   = $d['test']['history'];
			$hist[] = array( 'role' => 'user', 'content' => $text );
			$r      = flxc_bot_sandbox_turn( $hist, (array) $d['test']['state'], null !== $d['draft'] ? $d['draft'] : $d['base'], '', time() + 45 );
			if ( is_wp_error( $r ) ) {
				$d['view'][] = array( 't' => 'error', 'text' => $r->get_error_message() );
				return array( 'ok' => false, 'error' => $r->get_error_message() );
			}
			foreach ( $r['replies'] as $rep ) {
				$hist[] = array( 'role' => 'assistant', 'content' => $rep );
			}
			$names = array();
			foreach ( (array) $r['log'] as $l ) {
				$names[] = $l['tool'];
			}
			$d['test']   = array( 'history' => array_slice( $hist, -40 ), 'state' => $r['state'], 'bg' => $r['bg'] ?? null );
			flxc_editor_add_usage( $d, $r['usage'] );
			/* translators: %s: test message */
			$d['view'][] = array( 't' => 'tool', 'text' => sprintf( __( 'Test edildi: "%s"', 'flx-chat' ), mb_substr( $text, 0, 80 ) ) );
			$res = array(
				'ok'      => true,
				'replies' => $r['replies'],
				'tools'   => $names,
				'handoff' => ! empty( $r['handoff'] ),
			);
			if ( ! empty( $r['bg']['code']['code'] ) ) {
				// Test kodu hiçbir yere gitmez; doğrulamayı denemek için kişi gibi bu kodu yazabilirsin.
				$res['test_code'] = $r['bg']['code']['code'];
			}
			return $res;
	}
	return array( 'ok' => false, 'error' => 'Bilinmeyen araç.' );
}

function flxc_editor_add_usage( array &$d, $u ) {
	if ( ! is_array( $u ) ) {
		return;
	}
	foreach ( array( 'in', 'out', 'calls' ) as $k ) {
		$d['usage'][ $k ] = (int) ( $d['usage'][ $k ] ?? 0 ) + (int) ( $u[ $k ] ?? 0 );
	}
	$d['usage']['cost'] = (float) ( $d['usage']['cost'] ?? 0 ) + (float) ( $u['cost'] ?? 0 );
}

/** Onay bekleyen okuma için yöneticiye gösterilen metin. */
function flxc_editor_pending_label( array $c ) {
	$chat = flxc_get_chat( (int) ( $c['args']['chat_id'] ?? 0 ) );
	$lim  = max( 1, min( 40, (int) ( $c['args']['limit'] ?? 10 ) ) );
	/* translators: 1: chat title, 2: count */
	$l = sprintf( __( '%1$s sohbetinin son %2$d mesajını okumak istiyor.', 'flx-chat' ), $chat ? flxc_chat_title( $chat ) : '#' . (int) ( $c['args']['chat_id'] ?? 0 ), $lim );
	$why = trim( (string) ( $c['args']['why'] ?? '' ) );
	return '' !== $why ? $l . ' ' . $why : $l;
}

/** Bekleyen tüm araç çağrılarını cevapsız bırakmadan kapatır (model mesaj sırası bozulmasın). */
function flxc_editor_cancel_queue( array &$d, $why ) {
	$all = $d['queue'];
	if ( $d['pending'] ) {
		array_unshift( $all, $d['pending'] );
	}
	foreach ( $all as $c ) {
		$d['msgs'][] = array( 'role' => 'tool', 'tool_call_id' => $c['id'], 'content' => wp_json_encode( array( 'ok' => false, 'error' => $why ), JSON_UNESCAPED_UNICODE ) );
	}
	$d['queue']   = array();
	$d['pending'] = null;
}

/** Modele gidecek mesajlar: sistem + son mesajlar; eski araç sonuçları kısaltılır. */
function flxc_editor_model_messages( array $d ) {
	$msgs = $d['msgs'];
	// Kesme noktası kullanıcı mesajında olmalı (araç çağrısı ile sonucu ayrılmasın).
	if ( count( $msgs ) > 50 ) {
		$cut = count( $msgs ) - 50;
		while ( $cut < count( $msgs ) && 'user' !== $msgs[ $cut ]['role'] ) {
			++$cut;
		}
		$msgs = array_slice( $msgs, $cut < count( $msgs ) ? $cut : count( $msgs ) - 1 );
	}
	$n = count( $msgs );
	foreach ( $msgs as $i => $m ) {
		if ( 'tool' === $m['role'] && $i < $n - 8 && mb_strlen( $m['content'] ) > 600 ) {
			$msgs[ $i ]['content'] = mb_substr( $m['content'], 0, 600 ) . '…';
		}
	}
	return array_merge( array( array( 'role' => 'system', 'content' => flxc_editor_system( $d ) ) ), $msgs );
}

/** Seçilen model araç desteklemiyor mu? (bilinmiyorsa destekliyor sayılır) */
function flxc_editor_model_no_tools( $model ) {
	$model  = '' !== $model ? $model : flxc_ai_model();
	$models = flxc_ai_models();
	if ( is_array( $models ) ) {
		foreach ( $models as $m ) {
			if ( $m['id'] === $model ) {
				return ! $m['tools'];
			}
		}
	}
	return false;
}

/**
 * Bir adım: kuyruktaki araçları çalıştırır ya da bir AI çağrısı yapar.
 *
 * @return array{done: bool}|WP_Error
 */
function flxc_editor_step( array &$d, $model ) {
	if ( $d['pending'] ) {
		return array( 'done' => true );
	}
	if ( ++$d['steps'] > FLXC_EDITOR_MAX_STEPS ) {
		flxc_editor_cancel_queue( $d, 'Adım sınırı doldu.' );
		$d['view'][] = array( 't' => 'error', 'text' => __( 'Adım sınırı doldu; devam etmek için bir mesaj yazın.', 'flx-chat' ) );
		return array( 'done' => true );
	}
	if ( $d['queue'] ) {
		$t0 = microtime( true );
		while ( $d['queue'] ) {
			$c = $d['queue'][0];
			if ( 'read_chat' === $c['name'] && empty( $c['approved'] ) && empty( $d['allow_read'] ) ) {
				array_shift( $d['queue'] );
				$c['label']   = flxc_editor_pending_label( $c );
				$d['pending'] = $c;
				return array( 'done' => true );
			}
			// Test turu ağır: aynı istekte ikincisi çalışmaz.
			if ( 'test_message' === $c['name'] && microtime( true ) - $t0 > 1 ) {
				return array( 'done' => false );
			}
			array_shift( $d['queue'] );
			$res         = flxc_editor_run_tool( $d, $c, ! empty( $c['approved'] ) );
			$d['msgs'][] = array( 'role' => 'tool', 'tool_call_id' => $c['id'], 'content' => wp_json_encode( $res, JSON_UNESCAPED_UNICODE ) );
			if ( 'test_message' === $c['name'] ) {
				return array( 'done' => false );
			}
		}
		return array( 'done' => false );
	}
	$last = end( $d['msgs'] );
	if ( ! $last || 'assistant' === $last['role'] ) {
		return array( 'done' => true );
	}
	$opts = array(
		'purpose'     => 'suggest',
		'tools'       => flxc_editor_tools(),
		'max_tokens'  => 8000,
		'temperature' => 0.2,
		'timeout'     => 50,
	);
	if ( '' !== $model ) {
		$opts['model'] = $model;
	}
	$r = flxc_ai_chat( flxc_editor_model_messages( $d ), $opts );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	if ( isset( $r['_usage'] ) ) {
		flxc_editor_add_usage( $d, flxc_ai_usage_sum( array( $r['_usage'] ), '' !== $model ? $model : flxc_ai_model() ) );
	}
	$calls = (array) ( $r['tool_calls'] ?? array() );
	$text  = flxc_ai_clean_text( (string) ( $r['content'] ?? '' ) );
	if ( ! $calls ) {
		$d['msgs'][] = array( 'role' => 'assistant', 'content' => $text );
		$d['view'][] = array( 't' => 'ai', 'text' => '' !== $text ? $text : '…' );
		return array( 'done' => true );
	}
	$d['msgs'][] = array( 'role' => 'assistant', 'content' => $text, 'tool_calls' => $calls );
	if ( '' !== trim( $text ) ) {
		$d['view'][] = array( 't' => 'ai', 'text' => $text );
	}
	foreach ( $calls as $c ) {
		$args         = json_decode( (string) ( $c['function']['arguments'] ?? '{}' ), true );
		$d['queue'][] = array(
			'id'   => (string) ( $c['id'] ?? '' ),
			'name' => (string) ( $c['function']['name'] ?? '' ),
			'args' => is_array( $args ) ? $args : array(),
		);
	}
	return array( 'done' => false );
}

/** Araç desteklemeyen model: eski tek seferlik JSON düzenleyici. */
function flxc_editor_step_json( array &$d, $model ) {
	$hist = array();
	foreach ( $d['view'] as $v ) {
		if ( 'user' === $v['t'] || 'ai' === $v['t'] ) {
			$hist[] = array( 'role' => 'user' === $v['t'] ? 'user' : 'assistant', 'content' => $v['text'] );
		}
	}
	$r = flxc_bot_editor_turn( $hist, null !== $d['draft'] ? $d['draft'] : $d['base'], $model );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	flxc_editor_add_usage( $d, $r['usage'] );
	$d['msgs'][] = array( 'role' => 'assistant', 'content' => $r['reply'] );
	$d['view'][] = array( 't' => 'ai', 'text' => '' !== $r['reply'] ? $r['reply'] : '…' );
	if ( null !== $r['prompt'] ) {
		$d['draft']  = $r['prompt'];
		$d['view'][] = array( 't' => 'proposal', 'text' => '' );
	}
	return array( 'done' => true );
}

/* ---------- REST ---------- */

add_action(
	'rest_api_init',
	function () {
		$perm = function () {
			return flxc_can_manage();
		};
		register_rest_route( 'flx-chat/v1', '/bot/editor/sessions', array( 'methods' => 'GET', 'callback' => 'flxc_rest_editor_sessions', 'permission_callback' => $perm ) );
		register_rest_route(
			'flx-chat/v1',
			'/bot/editor/sessions/(?P<id>\d+)',
			array(
				array( 'methods' => 'GET', 'callback' => 'flxc_rest_editor_session', 'permission_callback' => $perm ),
				array( 'methods' => 'POST', 'callback' => 'flxc_rest_editor_session_update', 'permission_callback' => $perm ),
				array( 'methods' => 'DELETE', 'callback' => 'flxc_rest_editor_session_delete', 'permission_callback' => $perm ),
			)
		);
		register_rest_route( 'flx-chat/v1', '/bot/editor/step', array( 'methods' => 'POST', 'callback' => 'flxc_rest_editor_step', 'permission_callback' => $perm ) );
	}
);

function flxc_rest_editor_sessions( WP_REST_Request $req ) {
	return array( 'sessions' => flxc_editor_list( sanitize_key( (string) $req->get_param( 'status' ) ), sanitize_text_field( (string) $req->get_param( 'q' ) ) ) );
}

function flxc_rest_editor_not_found() {
	return new WP_Error( 'flxc_editor', __( 'Oturum bulunamadı.', 'flx-chat' ), array( 'status' => 404 ) );
}

function flxc_rest_editor_session( WP_REST_Request $req ) {
	$s = flxc_editor_get( (int) $req['id'] );
	return $s ? array( 'session' => flxc_editor_for_js( $s ) ) : flxc_rest_editor_not_found();
}

/** Ad, arşiv, test sıfırlama ve taslağın akıbeti (published / discarded). */
function flxc_rest_editor_session_update( WP_REST_Request $req ) {
	$s = flxc_editor_get( (int) $req['id'] );
	if ( ! $s ) {
		return flxc_rest_editor_not_found();
	}
	$title = $req->get_param( 'title' );
	if ( is_string( $title ) && '' !== trim( $title ) ) {
		$s['title'] = sanitize_text_field( $title );
	}
	$status = $req->get_param( 'status' );
	if ( in_array( $status, array( 'active', 'archived' ), true ) ) {
		$s['status'] = $status;
	}
	if ( $req->get_param( 'test_reset' ) ) {
		$s['data']['test'] = array( 'history' => array(), 'state' => array(), 'bg' => null );
	}
	$draft = $req->get_param( 'draft' );
	if ( 'published' === $draft && null !== $s['data']['draft'] ) {
		$s['data']['base']   = $s['data']['draft'];
		$s['data']['draft']  = null;
		$s['data']['view'][] = array( 't' => 'note', 'text' => __( 'Taslak yayına alındı.', 'flx-chat' ) );
		$s['data']['msgs'][] = array( 'role' => 'user', 'content' => '[Yönetici taslağı onayladı ve yayına aldı.]' );
		$s['data']['msgs'][] = array( 'role' => 'assistant', 'content' => 'Tamam.' );
	} elseif ( 'saved' === $draft && null !== $s['data']['draft'] ) {
		$s['data']['view'][] = array( 't' => 'note', 'text' => __( 'Taslak sürüm olarak kaydedildi.', 'flx-chat' ) );
	} elseif ( 'discarded' === $draft ) {
		$s['data']['draft']  = null;
		$s['data']['view'][] = array( 't' => 'note', 'text' => __( 'Taslaktan vazgeçildi.', 'flx-chat' ) );
		$s['data']['msgs'][] = array( 'role' => 'user', 'content' => '[Yönetici taslaktan vazgeçti; yayındaki metin geçerli.]' );
		$s['data']['msgs'][] = array( 'role' => 'assistant', 'content' => 'Tamam.' );
	}
	$s = flxc_editor_save( $s );
	return array( 'session' => flxc_editor_for_js( $s ), 'sessions' => flxc_editor_list( 'all' ) );
}

function flxc_rest_editor_session_delete( WP_REST_Request $req ) {
	global $wpdb;
	$s = flxc_editor_get( (int) $req['id'] );
	if ( ! $s ) {
		return flxc_rest_editor_not_found();
	}
	$wpdb->delete( flxc_table( 'editor_sessions' ), array( 'id' => (int) $s['id'] ) );
	return array( 'ok' => true );
}

/**
 * Body: session_id (0 = yeni), text?, approve? {ok, always}, model?.
 * Cevap: {session, done}. done=false ise istemci aynı oturumla tekrar çağırır.
 */
function flxc_rest_editor_step( WP_REST_Request $req ) {
	if ( ! flxc_ai_ready() ) {
		return new WP_Error( 'flxc_ai', __( 'Önce Ayarlar › Yapay zekâ bölümünden anahtar ve model girin.', 'flx-chat' ), array( 'status' => 400 ) );
	}
	$id   = (int) $req->get_param( 'session_id' );
	$text = trim( (string) $req->get_param( 'text' ) );
	if ( $id ) {
		$s = flxc_editor_get( $id );
		if ( ! $s ) {
			return flxc_rest_editor_not_found();
		}
	} else {
		if ( '' === $text ) {
			return new WP_Error( 'flxc_editor', __( 'Ne değiştirmek istediğinizi yazın.', 'flx-chat' ), array( 'status' => 400 ) );
		}
		$s = array(
			'id'     => 0,
			'title'  => wp_trim_words( $text, 8, '…' ),
			'status' => 'active',
			'data'   => flxc_editor_blank( flxc_bot_prompt_template() ),
		);
	}
	$d       = &$s['data'];
	$approve = $req->get_param( 'approve' );
	if ( '' !== $text ) {
		flxc_editor_cancel_queue( $d, 'Yönetici yeni mesaj yazdı; bu işlem iptal edildi.' );
		$d['msgs'][] = array( 'role' => 'user', 'content' => mb_substr( $text, 0, 6000 ) );
		$d['view'][] = array( 't' => 'user', 'text' => $text );
		$d['steps']  = 0;
	} elseif ( is_array( $approve ) && $d['pending'] ) {
		$c            = $d['pending'];
		$d['pending'] = null;
		if ( ! empty( $approve['ok'] ) ) {
			$c['approved'] = true;
			array_unshift( $d['queue'], $c );
			if ( ! empty( $approve['always'] ) ) {
				$d['allow_read'] = true;
			}
		} else {
			$d['msgs'][] = array( 'role' => 'tool', 'tool_call_id' => $c['id'], 'content' => wp_json_encode( array( 'ok' => false, 'error' => 'Yönetici bu sohbetin okunmasına izin vermedi. Okumadan devam et.' ), JSON_UNESCAPED_UNICODE ) );
			$d['view'][] = array( 't' => 'tool', 'text' => __( 'Okumaya izin verilmedi', 'flx-chat' ) );
		}
	}
	$model = flxc_clean_model_id( $req->get_param( 'model' ) );
	$r     = flxc_editor_model_no_tools( $model ) && ! $d['queue'] ? flxc_editor_step_json( $d, $model ) : flxc_editor_step( $d, $model );
	if ( is_wp_error( $r ) ) {
		$d['view'][] = array( 't' => 'error', 'text' => $r->get_error_message() );
		$r           = array( 'done' => true );
	}
	unset( $d );
	$s = flxc_editor_save( $s );
	return array(
		'session' => flxc_editor_for_js( $s ),
		'done'    => (bool) $r['done'],
		'month'   => flxc_ai_month_usage(),
	);
}

/* ---------- Test sohbeti: arka plan bilgisi ---------- */

/** Test durumundaki hafıza, hatırlatıcı ve doğrulamalar (Test sohbeti sol paneli). */
function flxc_bot_sandbox_bg( array $state ) {
	$mem = array();
	foreach ( (array) ( $state['mem'] ?? array() ) as $m ) {
		$mem[] = array( 'k' => (string) ( $m['k'] ?? '' ), 'v' => (string) ( $m['v'] ?? '' ) );
	}
	$rems = array();
	foreach ( (array) ( $state['rems'] ?? array() ) as $r ) {
		$rems[] = array(
			'title' => (string) ( $r['title'] ?? '' ),
			'task'  => (string) ( $r['task'] ?? '' ),
			'when'  => function_exists( 'flxc_rem_when' ) ? flxc_rem_when( $r ) : '',
			'next'  => ! empty( $r['next_at'] ) ? flx_date( (int) $r['next_at'], 'datetime' ) : '',
		);
	}
	$draft = '';
	if ( ! empty( $state['rem_draft'] ) && function_exists( 'flxc_rem_summary' ) ) {
		$draft = flxc_rem_summary( $state['rem_draft'] );
	}
	$code = null;
	if ( ! empty( $state['vc_test'] ) && ! empty( $state['vc'] ) ) {
		$v    = $state['vc_test'];
		$code = array(
			'code'    => (string) $v['code'],
			'to'      => 'email' === $v['channel'] ? (string) $v['value'] : flxc_format_phone( (string) $v['value'] ),
			'channel' => function_exists( 'flxc_verify_channel_label' ) ? flxc_verify_channel_label( (string) $v['channel'] ) : (string) $v['channel'],
			'until'   => wp_date( 'H:i', (int) $v['exp'] ),
		);
	}
	$dons = array();
	foreach ( (array) ( $state['tdons'] ?? array() ) as $i => $td ) {
		$dons[] = array(
			'i'      => $i,
			'id'     => (int) $td['id'],
			'title'  => (string) $td['title'],
			'amount' => (string) $td['amount'],
			'method' => 'card' === $td['method'] ? __( 'Kart', 'flx-chat' ) : ( 'havale' === $td['method'] ? __( 'Havale / EFT', 'flx-chat' ) : __( 'Seçmeli', 'flx-chat' ) ),
			'status' => (string) $td['status'],
			'date'   => (string) $td['date'],
			'seed'   => ! empty( $td['seed'] ),
		);
	}
	return array(
		'code'     => $code,
		'dons'     => $dons,
		'mem'      => $mem,
		'rems'     => $rems,
		'remDraft' => $draft,
		'phones'   => array_values( (array) ( $state['phones_ok'] ?? array() ) ),
		'emails'   => array_values( (array) ( $state['emails_ok'] ?? array() ) ),
		'locked'   => function_exists( 'flxc_verify_locked' ) && flxc_verify_locked( $state ),
	);
}
