<?php
/**
 * Asistan stüdyosu: talimat (prompt) sürümleri, ek belgeler, düzenleyici sohbet ve test sohbeti.
 *
 * Talimat metni sürümlü tutulur (flxc_bot_prompt seçeneği); "Bilinenler", bağış türleri, fiyatlar ve
 * banka bilgisi her turda koddan eklenir. Belgeler flxc_bot_docs seçeneğinde: "always" olanlar talimata
 * eklenir, "tool" olanları asistan gerektiğinde read_doc ile okur; talimatta {{belge:kisa-ad}} yazılan
 * yere belgenin tamamı konur.
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Talimat sürümleri ---------- */

function flxc_bot_default_prompt() {
	static $t = null;
	if ( null === $t ) {
		$t = trim( (string) file_get_contents( FLXC_DIR . 'includes/bot-prompt.md' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	return $t;
}

/** {active: id, versions: [{id, text, note, by, at}]} — en yeni sürüm sonda. */
function flxc_bot_prompt_store() {
	$s = get_option( 'flxc_bot_prompt', array() );
	$s = is_array( $s ) ? $s : array();
	return wp_parse_args( $s, array( 'active' => '', 'versions' => array() ) );
}

function flxc_bot_prompt_version( $id ) {
	foreach ( flxc_bot_prompt_store()['versions'] as $v ) {
		if ( (string) $v['id'] === (string) $id ) {
			return $v;
		}
	}
	return null;
}

/** Yayındaki talimat metni (sürüm yoksa yerleşik metin). */
function flxc_bot_prompt_template( $id = null ) {
	$v = flxc_bot_prompt_version( null === $id ? flxc_bot_prompt_store()['active'] : $id );
	return $v ? (string) $v['text'] : flxc_bot_default_prompt();
}

function flxc_bot_prompt_add( $text, $note = '', $activate = true ) {
	$text = trim( str_replace( "\r\n", "\n", (string) $text ) );
	if ( '' === $text ) {
		return new WP_Error( 'flxc_prompt_empty', __( 'Instructions cannot be empty.', 'flx-chat' ) );
	}
	$s = flxc_bot_prompt_store();
	$v = array(
		'id'   => substr( md5( uniqid( '', true ) ), 0, 8 ),
		'text' => mb_substr( $text, 0, 30000 ),
		'note' => mb_substr( sanitize_text_field( (string) $note ), 0, 200 ),
		'by'   => get_current_user_id(),
		'at'   => time(),
	);
	$s['versions'][] = $v;
	// En fazla 40 sürüm; yayındaki silinmez.
	while ( count( $s['versions'] ) > 40 ) {
		$i = (string) $s['versions'][0]['id'] === (string) $s['active'] ? 1 : 0;
		array_splice( $s['versions'], $i, 1 );
	}
	if ( $activate ) {
		$s['active'] = $v['id'];
	}
	update_option( 'flxc_bot_prompt', $s, false );
	return $v;
}

/** Talimattaki {brand} ve {{belge:kisa-ad}} yer tutucuları doldurulur. */
function flxc_bot_render_prompt( $template ) {
	$out = strtr( (string) $template, array( '{brand}' => flxc_brand_name() ) );
	return preg_replace_callback(
		'/\{\{\s*(?:belge|doc)\s*:\s*([a-z0-9_-]+)\s*\}\}/i',
		function ( $m ) {
			$d = flxc_bot_doc( $m[1] );
			return $d ? trim( $d['body'] ) : '';
		},
		$out
	);
}

/* ---------- Belgeler ---------- */

function flxc_bot_docs() {
	$d = get_option( 'flxc_bot_docs', array() );
	return is_array( $d ) ? array_values( $d ) : array();
}

function flxc_bot_doc( $slug ) {
	foreach ( flxc_bot_docs() as $d ) {
		if ( $d['slug'] === sanitize_title( $slug ) ) {
			return $d;
		}
	}
	return null;
}

function flxc_bot_doc_save( array $in ) {
	$title = mb_substr( sanitize_text_field( (string) ( $in['title'] ?? '' ) ), 0, 120 );
	$body  = mb_substr( str_replace( "\r\n", "\n", wp_kses_no_null( (string) ( $in['body'] ?? '' ) ) ), 0, 40000 );
	if ( '' === $title || '' === trim( $body ) ) {
		return new WP_Error( 'flxc_doc', __( 'Title and content are required.', 'flx-chat' ) );
	}
	$docs = flxc_bot_docs();
	$id   = sanitize_key( (string) ( $in['id'] ?? '' ) );
	$slug = sanitize_title( '' !== (string) ( $in['slug'] ?? '' ) ? $in['slug'] : $title );
	foreach ( $docs as $d ) {
		if ( $d['slug'] === $slug && $d['id'] !== $id ) {
			$slug .= '-' . wp_rand( 2, 99 );
		}
	}
	$doc = array(
		'id'    => '' !== $id ? $id : substr( md5( uniqid( '', true ) ), 0, 8 ),
		'title' => $title,
		'slug'  => $slug,
		'body'  => $body,
		'mode'  => in_array( $in['mode'] ?? '', array( 'always', 'tool', 'off' ), true ) ? $in['mode'] : 'always',
		'at'    => time(),
	);
	$found = false;
	foreach ( $docs as $i => $d ) {
		if ( $d['id'] === $doc['id'] ) {
			$docs[ $i ] = $doc;
			$found      = true;
		}
	}
	if ( ! $found ) {
		$docs[] = $doc;
	}
	update_option( 'flxc_bot_docs', array_slice( $docs, 0, 50 ), false );
	return $doc;
}

function flxc_bot_doc_delete( $id ) {
	$docs = array_values(
		array_filter(
			flxc_bot_docs(),
			function ( $d ) use ( $id ) {
				return $d['id'] !== $id;
			}
		)
	);
	update_option( 'flxc_bot_docs', $docs, false );
}

/** Talimata eklenecek belge bölümü. */
function flxc_bot_docs_prompt() {
	$always = array();
	$tool   = array();
	foreach ( flxc_bot_docs() as $d ) {
		if ( 'always' === $d['mode'] ) {
			$always[] = '### ' . $d['title'] . "\n" . trim( $d['body'] );
		} elseif ( 'tool' === $d['mode'] ) {
			$tool[] = '- ' . $d['slug'] . ': ' . $d['title'];
		}
	}
	$out = '';
	if ( $always ) {
		$out .= "## Bilgi belgeleri\n" . implode( "\n\n", $always );
	}
	if ( $tool ) {
		$out .= ( '' !== $out ? "\n\n" : '' ) . "## Başvuru belgeleri\nKişi bu konulardan birini sorarsa cevaplamadan önce read_doc(slug) ile belgeyi oku:\n" . implode( "\n", $tool );
	}
	return $out;
}

function flxc_bot_has_tool_docs() {
	foreach ( flxc_bot_docs() as $d ) {
		if ( 'tool' === $d['mode'] ) {
			return true;
		}
	}
	return false;
}

/* ---------- Mesaj şablonları (görüntüleme) ---------- */

function flxc_bot_templates_preview() {
	$types = flxc_ai_types();
	$o     = null;
	if ( $types && $types[0]['options'] ) {
		$built = flxc_bot_build_order( array( 'type_id' => $types[0]['id'], 'option' => $types[0]['options'][0]['key'], 'quantity' => 2 ) );
		$o     = is_wp_error( $built ) ? null : $built;
	}
	$name = __( 'Jane Smith', 'flx-chat' );
	return array(
		array( 'key' => 'price', 'label' => __( 'Price list', 'flx-chat' ), 'text' => flxc_bot_price_text() ),
		array( 'key' => 'summary', 'label' => __( 'Donation summary', 'flx-chat' ), 'text' => $o ? flxc_bot_order_summary( $o, array( 'full' => $name, 'email' => '' ) ) : '' ),
		array( 'key' => 'payment', 'label' => __( 'Payment messages sent after confirmation', 'flx-chat' ), 'text' => $o ? implode( "\n\n— — —\n\n", flxc_bot_payment_messages( $o, $name ) ) : '' ),
		array( 'key' => 'handoff', 'label' => __( 'Hand-off message', 'flx-chat' ), 'text' => flxc_bot_handoff_text() ),
		array( 'key' => 'words', 'label' => __( 'Agent request phrases', 'flx-chat' ), 'text' => implode( ', ', flxc_bot_handoff_words() ) ),
	);
}

/** Örnek bir sohbet için modele giden tam sistem metni. */
function flxc_bot_sample_prompt( $template = null, $state = array() ) {
	$chat = flxc_bot_sandbox_chat();
	return flxc_bot_system_prompt( $chat, wp_parse_args( $state, flxc_bot_state( $chat ) ), $template );
}

/* ---------- Test sohbeti ---------- */

function flxc_bot_sandbox_chat() {
	return array(
		'id'          => 0,
		'conn'        => '',
		'jid'         => '',
		'phone'       => '',
		'name'        => '',
		'push_name'   => __( 'Test', 'flx-chat' ),
		'meta'        => '',
		'assigned_to' => 0,
		'unread'      => 0,
		'status'      => 'open',
	);
}

/**
 * Test turunu çalıştırır; hiçbir şey kaydedilmez ve gönderilmez.
 *
 * @param array  $history [{role: user|assistant, content}]
 * @param array  $state   asistan durumu (istemcide tutulur)
 * @param string $template talimat metni
 */
function flxc_bot_sandbox_turn( array $history, array $state, $template, $model = '', $deadline = 0, $cache = '', array $files = array() ) {
	$chat     = flxc_bot_sandbox_chat();
	$state    = wp_parse_args( $state, flxc_bot_state( $chat ) );
	// 1.19.0: eklenen dosyalar gerçek hattaki gibi: dekont ön kontrolü, sonra modele yer tutucu metin.
	$fx = $files ? flxc_bot_sandbox_files( $files, $history, $state, $model, $cache ) : null;
	if ( $fx && null !== $fx['reply'] ) {
		return array(
			'replies'  => array( $fx['reply'] ),
			'state'    => $state,
			'log'      => $fx['log'],
			'handoff'  => false,
			'bg'       => flxc_bot_sandbox_bg( $state ),
			'ms'       => $fx['ms'],
			'usage'    => flxc_ai_usage_sum( $fx['usage'], '' !== $model ? $model : flxc_ai_model( 'bot' ) ),
			'userText' => $fx['text'],
		);
	}
	$messages = array( array( 'role' => 'system', 'content' => flxc_bot_system_prompt( $chat, $state, $template ) ) );
	$prev     = null;
	foreach ( array_slice( $history, -60 ) as $m ) {
		$role = 'user' === ( $m['role'] ?? '' ) ? 'user' : 'assistant';
		$text = mb_substr( (string) ( $m['content'] ?? '' ), 0, 4000 );
		if ( 'assistant' === $role ) {
			$text = flxc_bot_mask_urls( $text ); // gerçek hatta olduğu gibi: link modele dönmez
		}
		if ( '' === trim( $text ) ) {
			continue;
		}
		if ( $prev === $role ) {
			$messages[ count( $messages ) - 1 ]['content'] .= "\n\n" . $text;
		} else {
			$messages[] = array( 'role' => $role, 'content' => $text );
		}
		$prev = $role;
	}
	if ( 'user' !== $prev ) {
		return new WP_Error( 'flxc_test', __( 'Write a message first.', 'flx-chat' ) );
	}
	// Gerçek hatta olduğu gibi: temsilci ifadesi geçerse model çağrılmadan devredilir.
	$last = flxc_bot_lower( (string) $messages[ count( $messages ) - 1 ]['content'] );
	$w    = flxc_bot_keyword_hit( $last );
	if ( '' !== $w ) {
		return array(
			'replies' => array( flxc_bot_handoff_text() ),
			'auto'    => array( true ),
			'state'   => $state,
			'log'     => array(),
			'handoff' => true,
			'keyword' => $w,
			'bg'      => flxc_bot_sandbox_bg( $state ),
			'ms'      => 0,
			'usage'   => flxc_ai_usage_sum( array(), '' !== $model ? $model : flxc_ai_model( 'bot' ) ),
		);
	}
	$ctx   = array(
		'after'   => array(),
		'sandbox'  => array( 'state' => $state ),
		'model'    => $model,
		'deadline' => (int) $deadline,
		'cache'    => (string) $cache,
	);
	if ( $fx ) {
		$ctx['log']   = $fx['log'];
		$ctx['usage'] = $fx['usage'];
	}
	$t0    = microtime( true );
	$reply = flxc_bot_complete( $messages, $chat, $ctx );
	if ( is_wp_error( $reply ) ) {
		return $reply;
	}
	$reply = flxc_bot_final_reply( $reply, $ctx );
	$wg    = flxc_widgets_split( $reply );
	$reply = $wg['text'];
	$parts = '' === $reply ? array() : preg_split( '/\n\s*-{3,}\s*\n/', $reply );
	$out   = array();
	$auto  = array(); // sistemin kendi gönderdiği mesajlar (fiyat listesi, ödeme / sertifika linki): asistan yazmadı
	$imgs  = array(); // 1.24.0: replies sırasıyla görsel (data:image/png) ya da çevrilemediyse hata metni
	foreach ( array_merge( $parts, flxc_widgets_on() ? $wg['codes'] : array(), $ctx['after'] ) as $i => $t ) {
		if ( $i >= count( $parts ) && $i < count( $parts ) + ( flxc_widgets_on() ? count( $wg['codes'] ) : 0 ) ) {
			$png    = flxc_widget_png( $t );
			$out[]  = is_wp_error( $png ) ? '[' . __( 'Image could not be sent', 'flx-chat' ) . ': ' . $png->get_error_message() . ']' : '[' . __( 'Image', 'flx-chat' ) . ']';
			$auto[] = false;
			$imgs[ count( $out ) - 1 ] = is_wp_error( $png ) ? '' : 'data:image/png;base64,' . base64_encode( $png['data'] );
			continue;
		}
		$t = trim( (string) $t );
		if ( '' !== $t ) {
			$out[]  = $t;
			$auto[] = $i >= count( $parts );
		}
	}
	return array(
		'replies' => $out,
		'auto'    => $auto,
		'images'  => (object) $imgs,
		'state'   => $ctx['sandbox']['state'],
		'log'     => $ctx['log'],
		'handoff' => ! empty( $ctx['handoff'] ),
		'bg'      => flxc_bot_sandbox_bg( $ctx['sandbox']['state'] ),
		'ms'      => (int) ( ( microtime( true ) - $t0 ) * 1000 ),
		'usage'   => flxc_ai_usage_sum( $ctx['usage'], '' !== $model ? $model : flxc_ai_model( 'bot' ) ),
	) + ( $fx ? array( 'userText' => $fx['text'] ) : array() );
}

/**
 * Test sohbetinde eklenen dosyalar (en çok 5; data URL). Son mesaja "[Fotoğraf]" / "[Belge: ad]" yer tutucusu
 * ve dekont ön kontrolü notu eklenir. Dekontsa (gerçek hattaki gibi) model çağrılmaz, ön kontrol cevabı döner.
 * Dekont defterine yazılmaz; daha önce yüklenmiş dekont yalnız okunarak bulunur.
 *
 * @return array {text, reply|null, log, usage, ms}
 */
function flxc_bot_sandbox_files( array $files, array &$history, array &$state, $model, $cache ) {
	$t0    = microtime( true );
	$log   = array();
	$usage = array();
	$tags  = array();
	$check = array();
	foreach ( array_slice( $files, 0, 5 ) as $f ) {
		$name = sanitize_file_name( (string) ( $f['name'] ?? 'dosya' ) );
		$data = (string) ( $f['data'] ?? '' );
		$mime = preg_match( '#^data:([a-z0-9.+/-]+);base64,#i', $data, $mm ) ? strtolower( $mm[1] ) : '';
		if ( '' === $mime || strlen( $data ) > 11 * MB_IN_BYTES ) {
			continue;
		}
		$img    = 0 === strpos( $mime, 'image/' );
		$tags[] = '[' . ( $img ? __( 'Photo', 'flx-chat' ) : __( 'Document', 'flx-chat' ) . ': ' . $name ) . ']';
		$check[] = array( 'i' => count( $tags ) - 1, 'name' => $name, 'mime' => $mime, 'data' => $data );
	}
	$last    = count( $history ) - 1;
	$caption = $last >= 0 && 'user' === ( $history[ $last ]['role'] ?? '' ) ? trim( (string) $history[ $last ]['content'] ) : '';
	$best    = null;
	if ( $check && function_exists( 'flxc_rcheck_on' ) && flxc_rcheck_on() ) {
		$exp = array( 'id' => 0, 'amount' => 0.0, 'currency' => 'TRY' );
		foreach ( (array) ( $state['tdons'] ?? array() ) as $td ) {
			if ( (int) $td['id'] === (int) ( $state['donation'] ?? 0 ) ) {
				$exp = array( 'id' => (int) $td['id'], 'amount' => (float) ( $td['amt'] ?? preg_replace( '/[^\d,]/', '', str_replace( '.', '', (string) $td['amount'] ) ) ), 'currency' => (string) ( $td['cur'] ?? 'TRY' ) );
			}
		}
		// Gerçek hattaki gibi en çok son iki dosyaya bakılır.
		foreach ( array_slice( $check, -2 ) as $c ) {
			$base = array( 'verdict' => 'unclear', 'at' => time(), 'model' => flxc_rcheck_model(), 'expected' => $exp['amount'], 'currency' => $exp['currency'], 'donation' => $exp['id'] );
			$res  = flxc_receipt_ai_read( $base, $c['data'], $c['mime'], $c['name'], $caption, array( 'cache' => $cache ) );
			if ( isset( $res['u'] ) ) {
				$usage[] = $res['u'];
				unset( $res['u'] );
			}
			if ( ! in_array( $res['verdict'], array( 'not_receipt', 'unclear' ), true ) ) {
				$res = flxc_bot_sandbox_receipt_dup( $res, sha1( (string) base64_decode( substr( $c['data'], strpos( $c['data'], ',' ) + 1 ) ) ), $state );
			}
			$tags[ $c['i'] ] .= ' (' . __( 'automatic receipt check', 'flx-chat' ) . ': ' . flxc_rcheck_summary( $res ) . ')';
			$log[] = array(
				'tool'   => 'receipt_ai',
				'args'   => array( 'file' => $c['name'], 'mime' => $c['mime'], 'model' => $res['model'] ?? '' ),
				'result' => $res,
			);
			if ( ! $best || 'not_receipt' === $best['verdict'] ) {
				$best = $res;
			}
		}
	}
	$text = trim( implode( ' ', $tags ) . ( '' !== $caption ? ' ' . $caption : '' ) );
	if ( $last >= 0 && 'user' === ( $history[ $last ]['role'] ?? '' ) ) {
		$history[ $last ]['content'] = $text;
	} else {
		$history[] = array( 'role' => 'user', 'content' => $text );
	}
	$reply = null;
	if ( $best && 'not_receipt' !== $best['verdict'] ) {
		$reply            = flxc_rcheck_reply( $best );
		$state['receipt'] = array( 'donation' => (int) ( $state['donation'] ?? 0 ), 'verdict' => $best['verdict'], 'at' => time() );
	}
	return array( 'text' => $text, 'reply' => $reply, 'log' => $log, 'usage' => $usage, 'ms' => (int) ( ( microtime( true ) - $t0 ) * 1000 ) );
}

/** Test dekontu: bu testte ya da gerçek defterde daha önce görülmüş mü (deftere yazmaz). */
function flxc_bot_sandbox_receipt_dup( array $res, $hash, array &$state ) {
	$key  = flxc_dekont_key( (string) ( $res['dekont_no'] ?? '' ) );
	$seen = (array) ( $state['rc_seen'] ?? array() );
	$dup  = null;
	foreach ( $seen as $x ) {
		if ( $x['h'] === $hash || ( strlen( $key ) >= 4 && $x['k'] === $key ) ) {
			$dup = array( 'id' => 0, 'at' => time(), 'source' => 'whatsapp', 'chat' => 0, 'donation' => (int) $x['d'], 'same' => (int) $x['d'] === (int) ( $state['donation'] ?? 0 ) );
			break;
		}
	}
	if ( ! $dup && function_exists( 'flxc_dekont_find' ) ) {
		$rows = flxc_dekont_find( array( 'payer' => $res['payer'] ?? '', 'dekont_no' => $res['dekont_no'] ?? '', 'hash' => $hash ) );
		if ( $rows ) {
			$dup = array( 'id' => $rows[0]['id'], 'at' => (int) $rows[0]['created_at'], 'source' => $rows[0]['source'], 'chat' => $rows[0]['chat_id'], 'donation' => $rows[0]['donation_id'] ?? 0, 'same' => false );
		}
	}
	$seen[]           = array( 'h' => $hash, 'k' => $key, 'd' => (int) ( $state['donation'] ?? 0 ) );
	$state['rc_seen'] = array_slice( $seen, -10 );
	if ( $dup ) {
		$res['dup'] = $dup;
		if ( ! ( 'mismatch' === $res['verdict'] && 'recipient' === ( $res['why'] ?? '' ) ) ) {
			$res['verdict'] = 'duplicate';
		}
	}
	return $res;
}

/** Bir turdaki istekleri (araç çağrıları dahil) toplar. */
function flxc_ai_usage_sum( array $list, $model ) {
	$s = array( 'in' => 0, 'out' => 0, 'cost' => 0.0, 'calls' => count( $list ), 'est' => false, 'model' => (string) $model, 'cr' => 0, 'cw' => 0, 'full' => 0.0, 'cache' => '' );
	foreach ( $list as $u ) {
		$s['in']   += (int) $u['in'];
		$s['out']  += (int) $u['out'];
		$s['cost'] += (float) $u['cost'];
		// Önbellek (yalnız Claude): okunan/yazılan token ve önbelleksiz olsaydı maliyet.
		$s['cr']   += (int) ( $u['cr'] ?? 0 );
		$s['cw']   += (int) ( $u['cw'] ?? 0 );
		$s['full'] += (float) ( $u['full'] ?? $u['cost'] );
		if ( ! empty( $u['cache'] ) ) {
			$s['cache'] = (string) $u['cache'];
		}
		$s['est']   = $s['est'] || ! empty( $u['est'] );
		if ( ! empty( $u['model'] ) ) {
			$s['model'] = (string) $u['model'];
		}
	}
	return $s;
}

/* ---------- Düzenleyici sohbet ---------- */

/**
 * Yöneticinin isteğine göre talimatın yeni hâlini önerir. Yanıt: {reply, prompt|null}.
 */
function flxc_bot_editor_turn( array $history, $current, $model = '' ) {
	$docs = array();
	foreach ( flxc_bot_docs() as $d ) {
		$docs[] = '- ' . $d['slug'] . ' (' . $d['mode'] . '): ' . $d['title'];
	}
	$sys = "Sen bir WhatsApp bağış asistanının talimat (system prompt) metnini düzenleyen yardımcısın. Yönetici ne değiştirmek istediğini söyler; sen mevcut metni buna göre güncellersin.\n"
		. "Kurallar:\n"
		. "- Yalnız istenen değişikliği yap; geri kalan metni, başlıkları ve sırayı olduğu gibi koru.\n"
		. "- {brand} ve {{belge:kisa-ad}} yer tutucularını koru; yeni belgeye referans gerekiyorsa {{belge:kisa-ad}} yaz.\n"
		. "- Fiyat, IBAN, bağış türleri ve saat asistanın tool'larıyla (donation_options, payment_info, current_time) canlı alınır, kişinin bilgileri koddan eklenir; bunları metne yazma.\n"
		. "- Araç adları: save_details, send_price_list, set_order, create_donation, handoff" . ( flxc_bot_has_tool_docs() ? ', read_doc' : '' ) . ".\n"
		. "- Yöneticiyle onun yazdığı dilde, kısa konuş.\n"
		. "- Cevabını YALNIZ şu JSON ile ver: {\"reply\": \"yaptığın değişikliğin kısa açıklaması ya da sorunun\", \"prompt\": \"talimatın TAM yeni metni ya da değişiklik yoksa null\"}\n\n"
		. ( $docs ? "Mevcut belgeler:\n" . implode( "\n", $docs ) . "\n\n" : '' )
		. "Mevcut talimat metni:\n<<<\n" . $current . "\n>>>";
	$messages = array( array( 'role' => 'system', 'content' => $sys ) );
	foreach ( array_slice( $history, -12 ) as $m ) {
		$messages[] = array(
			'role'    => 'user' === ( $m['role'] ?? '' ) ? 'user' : 'assistant',
			'content' => mb_substr( (string) ( $m['content'] ?? '' ), 0, 4000 ),
		);
	}
	$opts = array(
		'purpose'     => 'suggest',
		'max_tokens'  => 6000,
		'temperature' => 0.2,
		'timeout'     => 90,
	);
	if ( '' !== $model ) {
		$opts['model'] = $model;
	}
	$r = flxc_ai_chat( $messages, $opts );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$usage = flxc_ai_usage_sum( isset( $r['_usage'] ) ? array( $r['_usage'] ) : array(), '' !== $model ? $model : flxc_ai_model() );
	$raw  = trim( (string) ( $r['content'] ?? '' ) );
	$json = null;
	if ( preg_match( '/\{.*\}/s', $raw, $m ) ) {
		$json = json_decode( $m[0], true );
	}
	if ( ! is_array( $json ) ) {
		return array( 'reply' => $raw, 'prompt' => null, 'usage' => $usage );
	}
	$prompt = isset( $json['prompt'] ) && is_string( $json['prompt'] ) && '' !== trim( $json['prompt'] ) ? trim( $json['prompt'] ) : null;
	if ( null !== $prompt && trim( $current ) === $prompt ) {
		$prompt = null;
	}
	return array(
		'reply'  => (string) ( $json['reply'] ?? '' ),
		'prompt' => $prompt,
		'usage'  => $usage,
	);
}

/* ---------- AI sohbetleri listesi ---------- */

function flxc_bot_chats_list( $offset = 0, $limit = 40 ) {
	$rows = flxc_query_chats(
		array(
			'filter' => 'ai',
			'limit'  => $limit,
			'offset' => $offset,
		)
	);
	// Kapalı sohbetler de listelensin.
	global $wpdb;
	if ( count( $rows ) < $limit ) {
		$ids    = wp_list_pluck( $rows, 'id' );
		$closed = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'chats' ) . " WHERE status = 'closed' AND meta LIKE %s ORDER BY last_ts DESC LIMIT %d", '%"ai":1%', $limit ), ARRAY_A ); // phpcs:ignore
		foreach ( $closed as $c ) {
			if ( ! in_array( $c['id'], $ids, true ) && flxc_user_can_see_chat( $c ) ) {
				$rows[] = $c;
			}
		}
	}
	$out = array();
	$t   = flxc_table( 'messages' );
	foreach ( $rows as $c ) {
		$st    = flxc_bot_state( $c );
		$stats = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) n, MAX(ts) last FROM $t WHERE chat_id = %d AND source = 'bot'", $c['id'] ), ARRAY_A ); // phpcs:ignore
		$note  = $wpdb->get_var( $wpdb->prepare( "SELECT body FROM $t WHERE chat_id = %d AND direction = 'system' ORDER BY id DESC LIMIT 1", $c['id'] ) ); // phpcs:ignore
		$out[] = array(
			'id'       => (int) $c['id'],
			'title'    => flxc_chat_title( $c ),
			'phone'    => flxc_format_phone( $c['phone'] ),
			'status'   => flxc_bot_chat_status( $c ),
			'chat'     => $c['status'],
			'hitap'    => (string) $st['name'],
			'full'     => (string) $st['full'],
			'order'    => $st['order'] ? flxc_bot_order_summary( $st['order'] ) : '',
			'donation' => (int) $st['donation'],
			'botMsgs'  => (int) $stats['n'],
			'lastBot'  => (int) $stats['last'],
			'lastTs'   => (int) $c['last_ts'],
			'note'     => (string) $note,
			'url'      => admin_url( 'admin.php?page=flx-chat&chat=' . (int) $c['id'] ),
		);
	}
	return $out;
}

/* ---------- REST ---------- */

add_action(
	'rest_api_init',
	function () {
		$ns  = 'flx-chat/v1';
		$mng = function () {
			return flxc_can_manage();
		};
		$routes = array(
			array( '/bot/studio', 'GET', 'flxc_rest_bot_studio' ),
			array( '/bot/chats', 'GET', 'flxc_rest_bot_chats' ),
			array( '/bot/prompt', 'POST', 'flxc_rest_bot_prompt_save' ),
			array( '/bot/prompt/activate', 'POST', 'flxc_rest_bot_prompt_activate' ),
			array( '/bot/prompt/preview', 'POST', 'flxc_rest_bot_prompt_preview' ),
			array( '/bot/docs', 'POST', 'flxc_rest_bot_doc_save' ),
			array( '/bot/docs/(?P<id>[a-z0-9]+)', 'DELETE', 'flxc_rest_bot_doc_delete' ),
			array( '/bot/editor', 'POST', 'flxc_rest_bot_editor' ),
			array( '/bot/test', 'POST', 'flxc_rest_bot_test' ),
		);
		foreach ( $routes as $r ) {
			register_rest_route(
				$ns,
				$r[0],
				array(
					'methods'             => $r[1],
					'callback'            => $r[2],
					'permission_callback' => $mng,
				)
			);
		}
	}
);

function flxc_bot_prompt_for_js() {
	$s    = flxc_bot_prompt_store();
	$list = array();
	foreach ( array_reverse( $s['versions'] ) as $v ) {
		$list[] = array(
			'id'   => $v['id'],
			'text' => $v['text'],
			'note' => $v['note'],
			'by'   => flxc_user_name( (int) $v['by'] ),
			'at'   => (int) $v['at'] * 1000,
		);
	}
	return array(
		'active'   => (string) $s['active'],
		'versions' => $list,
		'default'  => flxc_bot_default_prompt(),
	);
}

function flxc_rest_bot_studio() {
	return array(
		'prompt'    => flxc_bot_prompt_for_js(),
		'docs'      => flxc_bot_docs(),
		'templates' => flxc_bot_templates_preview(),
		'chats'     => flxc_bot_chats_list(),
		'ready'     => flxc_ai_ready(),
		'enabled'   => flxc_bot_enabled(),
		'problem'   => flxc_ai_types_problem(),
		'testOnly'  => (bool) flxc_setting( 'bot_test_only' ),
		'model'     => flxc_ai_model( 'bot' ),
		'modelEdit' => flxc_ai_model(),
		'cache'     => flxc_ai_cache_mode(),
		'claude'    => '' !== flxc_ai_provider_key( 'anthropic' ),
		'month'     => flxc_ai_month_usage(),
		'staff'     => function_exists( 'flxc_staff_test_users' ) ? flxc_staff_test_users() : array(),
	);
}

function flxc_rest_bot_chats( WP_REST_Request $req ) {
	return array( 'chats' => flxc_bot_chats_list( max( 0, (int) $req->get_param( 'offset' ) ) ) );
}

function flxc_rest_bot_prompt_save( WP_REST_Request $req ) {
	$v = flxc_bot_prompt_add( (string) $req->get_param( 'text' ), (string) $req->get_param( 'note' ), (bool) $req->get_param( 'activate' ) );
	if ( is_wp_error( $v ) ) {
		$v->add_data( array( 'status' => 400 ) );
		return $v;
	}
	return array( 'prompt' => flxc_bot_prompt_for_js() );
}

function flxc_rest_bot_prompt_activate( WP_REST_Request $req ) {
	$id = (string) $req->get_param( 'id' );
	$s  = flxc_bot_prompt_store();
	if ( '' !== $id && ! flxc_bot_prompt_version( $id ) ) {
		return new WP_Error( 'flxc_prompt', __( 'Version not found.', 'flx-chat' ), array( 'status' => 404 ) );
	}
	$s['active'] = $id; // '' = yerleşik metin
	update_option( 'flxc_bot_prompt', $s, false );
	return array( 'prompt' => flxc_bot_prompt_for_js() );
}

function flxc_rest_bot_prompt_preview( WP_REST_Request $req ) {
	$text = $req->get_param( 'text' );
	return array( 'full' => flxc_bot_sample_prompt( null === $text ? null : (string) $text ) );
}

function flxc_rest_bot_doc_save( WP_REST_Request $req ) {
	$d = flxc_bot_doc_save( (array) $req->get_json_params() );
	if ( is_wp_error( $d ) ) {
		$d->add_data( array( 'status' => 400 ) );
		return $d;
	}
	return array( 'docs' => flxc_bot_docs() );
}

function flxc_rest_bot_doc_delete( WP_REST_Request $req ) {
	flxc_bot_doc_delete( sanitize_key( $req['id'] ) );
	return array( 'docs' => flxc_bot_docs() );
}

function flxc_rest_bot_editor( WP_REST_Request $req ) {
	if ( ! flxc_ai_ready() ) {
		return new WP_Error( 'flxc_ai', __( 'First enter an API key and model under Settings › AI.', 'flx-chat' ), array( 'status' => 400 ) );
	}
	$current = (string) $req->get_param( 'current' );
	$r       = flxc_bot_editor_turn( (array) $req->get_param( 'history' ), '' !== trim( $current ) ? $current : flxc_bot_prompt_template(), flxc_clean_model_id( $req->get_param( 'model' ) ) );
	if ( is_wp_error( $r ) ) {
		$r->add_data( array( 'status' => 502 ) );
		return $r;
	}
	$r['month'] = flxc_ai_month_usage();
	return $r;
}

function flxc_rest_bot_test( WP_REST_Request $req ) {
	if ( ! flxc_ai_ready() ) {
		return new WP_Error( 'flxc_ai', __( 'First enter an API key and model under Settings › AI.', 'flx-chat' ), array( 'status' => 400 ) );
	}
	$text = $req->get_param( 'text' );
	$r    = flxc_bot_sandbox_turn(
		(array) $req->get_param( 'history' ),
		(array) $req->get_param( 'state' ),
		is_string( $text ) && '' !== trim( $text ) ? $text : flxc_bot_prompt_template(),
		flxc_clean_model_id( $req->get_param( 'model' ) ),
		0,
		sanitize_key( (string) $req->get_param( 'cache' ) ),
		array_values( array_filter( (array) $req->get_param( 'files' ), 'is_array' ) )
	);
	if ( is_wp_error( $r ) ) {
		$r->add_data( array( 'status' => 400 ) );
		return $r;
	}
	$r['month'] = flxc_ai_month_usage();
	return $r;
}

/** Test/düzenleyici için seçilen model kimliği (OpenRouter biçimi; ~ korunur). */
function flxc_clean_model_id( $m ) {
	return mb_substr( preg_replace( '/[^A-Za-z0-9._:\/~@-]/', '', is_string( $m ) ? $m : '' ), 0, 120 );
}
