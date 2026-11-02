<?php
/**
 * Kişi hafızası: asistanın kişi hakkında hatırlaması gereken kalıcı bilgiler (anahtar/değer) ve
 * süresi dolan sohbetlerin özetleri. Her cevapta asistanın bağlamına eklenir; yalnız yöneticiler görür.
 * Kişi telefon numarasıyla tanınır (gizli numarada sohbet kimliğiyle).
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

function flxc_mem_on() {
	return (bool) flxc_setting( 'mem_enabled' );
}

/** Kişinin hafıza anahtarı. */
function flxc_mem_who( $chat ) {
	$p = (string) ( $chat['phone'] ?? '' );
	return '' !== $p ? 'p:' . $p : 'c:' . (int) ( $chat['id'] ?? 0 );
}

function flxc_mem_key( $k ) {
	$k = trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( (string) $k ) ) );
	return mb_substr( mb_strtolower( $k ), 0, 120 );
}

function flxc_mem_value( $v ) {
	return mb_substr( trim( sanitize_textarea_field( (string) $v ) ), 0, 1500 );
}

/** Yaklaşık token sayısı (Türkçe metinde ~3,5 karakter bir token). */
function flxc_mem_tokens( $text ) {
	return (int) ceil( mb_strlen( (string) $text ) / 3.5 );
}

/** @return array[] kalıcı bilgiler (en yeni önce), ardından özetler (en yeni önce). */
function flxc_mem_items( $chat ) {
	global $wpdb;
	return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'memory' ) . " WHERE who = %s ORDER BY kind = 'summary', updated_at DESC, id DESC", flxc_mem_who( $chat ) ), ARRAY_A ); // phpcs:ignore
}

/**
 * Bilgi kaydeder ya da günceller (aynı anahtar varsa üstüne yazar). Özetler her zaman yeni satırdır; son 3'ü kalır.
 *
 * @return int|false satır id
 */
function flxc_mem_set( $chat, $k, $v, $source = 'bot', $kind = 'fact', $at = 0 ) {
	global $wpdb;
	$t   = flxc_table( 'memory' );
	$who = flxc_mem_who( $chat );
	$k   = 'summary' === $kind ? 'özet' : flxc_mem_key( $k );
	$v   = flxc_mem_value( $v );
	$now = time();
	if ( '' === $k || '' === $v ) {
		return false;
	}
	if ( 'fact' === $kind ) {
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE who = %s AND kind = 'fact' AND k = %s LIMIT 1", $who, $k ) ); // phpcs:ignore
		if ( $id ) {
			$wpdb->update( $t, array( 'v' => $v, 'source' => $source, 'updated_at' => $now, 'chat_id' => (int) $chat['id'] ), array( 'id' => $id ) );
			return $id;
		}
	}
	$wpdb->insert(
		$t,
		array(
			'who'        => $who,
			'chat_id'    => (int) $chat['id'],
			'k'          => $k,
			'v'          => $v,
			'kind'       => $kind,
			'source'     => $source,
			'created_at' => $at ? (int) $at : $now,
			'updated_at' => $now,
		)
	);
	$id = (int) $wpdb->insert_id;
	// Sınır: kişi başına 3 özet, 60 bilgi (en eskiler silinir).
	$keep = 'summary' === $kind ? 3 : 60;
	$old  = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $t WHERE who = %s AND kind = %s ORDER BY updated_at DESC, id DESC LIMIT 1000 OFFSET %d", $who, $kind, $keep ) ); // phpcs:ignore
	if ( $old ) {
		$wpdb->query( "DELETE FROM $t WHERE id IN (" . implode( ',', array_map( 'intval', $old ) ) . ')' ); // phpcs:ignore
	}
	return $id;
}

/** Anahtarla siler (tam eşleşme yoksa anahtarı içerenler). '' tümünü siler. @return int silinen */
function flxc_mem_forget( $chat, $k = '' ) {
	global $wpdb;
	$t   = flxc_table( 'memory' );
	$who = flxc_mem_who( $chat );
	if ( '' === (string) $k ) {
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE who = %s", $who ) ); // phpcs:ignore
	}
	$k = flxc_mem_key( $k );
	$n = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE who = %s AND kind = 'fact' AND k = %s", $who, $k ) ); // phpcs:ignore
	if ( ! $n && mb_strlen( $k ) >= 3 ) {
		$n = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE who = %s AND kind = 'fact' AND k LIKE %s", $who, '%' . $wpdb->esc_like( $k ) . '%' ) ); // phpcs:ignore
	}
	return $n;
}

function flxc_mem_when( $ts ) {
	return wp_date( 'd.m.Y H:i', (int) $ts );
}

/**
 * Asistanın bağlamına giden bölüm.
 *
 * @param array $items flxc_mem_items() satırları ya da test sohbetindeki liste (aynı alanlar).
 */
function flxc_mem_prompt( array $items ) {
	$facts = array();
	$sums  = array();
	foreach ( $items as $r ) {
		if ( 'summary' === ( $r['kind'] ?? 'fact' ) ) {
			if ( count( $sums ) < 2 ) {
				$sums[] = '- ' . flxc_mem_when( $r['created_at'] ) . ': ' . str_replace( "\n", ' ', (string) $r['v'] );
			}
		} elseif ( count( $facts ) < 40 ) {
			$facts[] = '- ' . $r['k'] . ': ' . str_replace( "\n", ' ', (string) $r['v'] ) . ' (' . flxc_mem_when( $r['updated_at'] ) . ')';
		}
	}
	$p  = "## Kişi hafızası\n";
	$p .= "Kişinin önceki konuşmalardan bilinen kalıcı bilgileri. Hitap tercihi varsa öyle hitap et; bunları yeniden sorma ama kişi değiştirirse güncelle.\n";
	$p .= "Kişi ileride işe yarayacak kalıcı bir bilgi verirse (nasıl hitap edilmek istediği, yakınlarının adı ve yakınlığı, telefon numaraları, tercih ettiği bağış türü, özel günler) remember ile kaydet; bilgi değişirse aynı anahtarla yeniden kaydet. Geçici ayrıntıları, ödeme ve kart bilgilerini kaydetme. Önceki bağışlarda adına sertifika verdiği yakınlar da burada (ör. \"annesi: Fatma Yılmaz · Tel: …\"): kişi \"annem adına bir tane daha\" derse adı ve numarayı buradan al, sormadan set_order beneficiaries'e yaz (relation ile), özette göster ve yine onay iste; ad ya da numara değişirse yeni haliyle kullan. Kişi bir bilgisinin ya da tüm bilgilerinin silinmesini isterse forget çağır ve silindiğini kısaca söyle. Hafızadan söz ederken \"kayıtlarımızda\" de, iç işleyişten bahsetme.\n";
	$p .= $facts ? implode( "\n", $facts ) : '- (henüz yok)';
	if ( $sums ) {
		$p .= "\n\nÖnceki konuşma özetleri:\n" . implode( "\n", $sums );
	}
	return mb_strlen( $p ) > 5000 ? mb_substr( $p, 0, 5000 ) . '…' : $p;
}

/**
 * 1.28.0 (Ahmet): bağışta adına sertifika verilen yakınları kişinin hafızasına yazar: anahtar yakınlık ("annesi"),
 * yoksa "bağış yapılan kişi: Ad"; değer "Ad · Tel: …". Numara bu sefer gelmediyse eski kayıttaki numara korunur.
 * Kendi adına olan sertifika yazılmaz.
 */
function flxc_mem_save_benef( $chat, array $benef, $self = '' ) {
	if ( ! flxc_mem_on() || ! $benef || empty( $chat['id'] ) ) {
		return;
	}
	$self  = flxc_bot_lower( trim( (string) $self ) );
	$facts = array();
	foreach ( flxc_mem_items( $chat ) as $r ) {
		if ( 'fact' === $r['kind'] ) {
			$facts[ $r['k'] ] = (string) $r['v'];
		}
	}
	$done = array();
	foreach ( $benef as $b ) {
		$name = trim( (string) ( $b['name'] ?? '' ) );
		$low  = flxc_bot_lower( $name );
		if ( '' === $name || $low === $self || isset( $done[ $low ] ) ) {
			continue;
		}
		$done[ $low ] = 1;
		$plain        = flxc_mem_key( __( 'bağış yapılan kişi', 'flx-chat' ) . ': ' . $name );
		$key          = ! empty( $b['relation'] ) ? flxc_mem_key( $b['relation'] ) : $plain;
		// Tek olan yakınlık (anne, baba, eş) güncellenir; kardeş, çocuk, arkadaş gibi birden çok olabilecekler adla ayrılır.
		if ( $key !== $plain && isset( $facts[ $key ] ) && 0 !== mb_stripos( $facts[ $key ], $name ) && ! preg_match( '/^(anne|baba|eş|annem|babam|eşim|annesi|babası|eşi|hanımı|kocası)$/u', $key ) ) {
			$key = flxc_mem_key( $b['relation'] . ': ' . $name );
		}
		$phone        = ! empty( $b['phone'] ) ? flxc_format_phone( $b['phone'] ) : '';
		if ( '' === $phone ) {
			foreach ( array( $key, $plain ) as $k ) {
				$old = $facts[ $k ] ?? '';
				if ( '' !== $old && 0 === mb_stripos( $old, $name ) && preg_match( '/Tel: ([+\d ]+)/u', $old, $m ) ) {
					$phone = trim( $m[1] );
				}
			}
		}
		flxc_mem_set( $chat, $key, $name . ( '' !== $phone ? ' · Tel: ' . $phone : '' ) . ( ! empty( $b['email'] ) ? ' · ' . $b['email'] : '' ), 'bot' );
		if ( $key !== $plain && isset( $facts[ $plain ] ) ) {
			flxc_mem_forget( $chat, $plain );
		}
	}
}

/** Yönetici görünümü için. */
function flxc_mem_for_js( $chat ) {
	$items = array();
	$tok   = 0;
	foreach ( flxc_mem_items( $chat ) as $r ) {
		$tok    += flxc_mem_tokens( $r['k'] . ': ' . $r['v'] );
		$items[] = array(
			'id'      => (int) $r['id'],
			'key'     => (string) $r['k'],
			'value'   => (string) $r['v'],
			'kind'    => (string) $r['kind'],
			'source'  => (string) $r['source'],
			'created' => (int) $r['created_at'] * 1000,
			'updated' => (int) $r['updated_at'] * 1000,
		);
	}
	global $wpdb;
	$job = $wpdb->get_row( $wpdb->prepare( 'SELECT status, note, finished_at FROM ' . flxc_table( 'jobs' ) . " WHERE kind = 'summary' AND chat_id = %d ORDER BY id DESC LIMIT 1", (int) $chat['id'] ), ARRAY_A ); // phpcs:ignore
	return array(
		'chat'    => (int) $chat['id'],
		'enabled' => flxc_mem_on(),
		'items'   => $items,
		'tokens'  => $tok,
		'memTs'   => (int) ( $chat['mem_ts'] ?? 0 ),
		'job'     => $job ? array( 'status' => $job['status'], 'note' => $job['note'], 'at' => (int) $job['finished_at'] * 1000 ) : null,
	);
}

/** Raporlar: toplam ve en çok hafızası olan kişiler. */
function flxc_mem_stats( $limit = 20 ) {
	global $wpdb;
	$t    = flxc_table( 'memory' );
	$rows = (array) $wpdb->get_results( "SELECT who, MAX(chat_id) chat_id, COUNT(*) n, SUM(kind = 'summary') s, SUM(CHAR_LENGTH(k) + CHAR_LENGTH(v) + 2) c, MAX(updated_at) u FROM $t GROUP BY who", ARRAY_A ); // phpcs:ignore
	$tot  = array( 'people' => 0, 'items' => 0, 'summaries' => 0, 'tokens' => 0 );
	foreach ( $rows as &$r ) {
		$r['tok']             = (int) ceil( (int) $r['c'] / 3.5 );
		$tot['people']       += 1;
		$tot['items']        += (int) $r['n'];
		$tot['summaries']    += (int) $r['s'];
		$tot['tokens']       += $r['tok'];
	}
	unset( $r );
	usort(
		$rows,
		function ( $a, $b ) {
			return $b['tok'] <=> $a['tok'];
		}
	);
	$top = array();
	foreach ( array_slice( $rows, 0, max( 1, (int) $limit ) ) as $r ) {
		$chat  = flxc_get_chat( (int) $r['chat_id'] );
		$top[] = array(
			'chat'      => (int) $r['chat_id'],
			'title'     => $chat ? flxc_chat_title( $chat ) : '#' . (int) $r['chat_id'],
			'phone'     => 0 === strpos( $r['who'], 'p:' ) ? flxc_format_phone( substr( $r['who'], 2 ) ) : '',
			'items'     => (int) $r['n'] - (int) $r['s'],
			'summaries' => (int) $r['s'],
			'tokens'    => $r['tok'],
			'updated'   => (int) $r['u'] * 1000,
			'url'       => admin_url( 'admin.php?page=flx-chat&chat=' . (int) $r['chat_id'] ),
		);
	}
	return $tot + array( 'top' => $top );
}

/* ---------- Süresi dolan sohbetin özeti ---------- */

function flxc_mem_transcript( $chat_id, $since_ms ) {
	global $wpdb;
	$rows  = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'messages' ) . " WHERE chat_id = %d AND ts > %d AND direction IN ('in','out') AND type NOT IN ('reaction','deleted') ORDER BY ts DESC, id DESC LIMIT 300", $chat_id, $since_ms ), ARRAY_A ); // phpcs:ignore
	$lines = array();
	$last  = 0;
	$in    = 0;
	foreach ( array_reverse( $rows ) as $m ) {
		$text = 'text' === $m['type'] ? trim( (string) $m['body'] ) : '[' . flxc_preview_text( $m ) . ']';
		if ( '' === $text ) {
			continue;
		}
		if ( 'in' === $m['direction'] ) {
			$who = 'Kişi';
			$in++;
		} else {
			$who = 'bot' === $m['source'] ? 'Asistan' : 'Ekip';
		}
		$lines[] = wp_date( 'd.m H:i', (int) floor( $m['ts'] / 1000 ) ) . ' ' . $who . ': ' . ( mb_strlen( $text ) > 600 ? mb_substr( $text, 0, 600 ) . '…' : $text );
		$last    = max( $last, (int) $m['ts'] );
	}
	return array( 'text' => implode( "\n", $lines ), 'in' => $in, 'last' => $last );
}

function flxc_mem_json( $s ) {
	$s = trim( (string) $s );
	$s = preg_replace( '/^```(?:json)?\s*|\s*```$/', '', $s );
	$a = strpos( $s, '{' );
	$b = strrpos( $s, '}' );
	if ( false === $a || false === $b ) {
		return null;
	}
	$j = json_decode( substr( $s, $a, $b - $a + 1 ), true );
	return is_array( $j ) ? $j : null;
}

/**
 * Sohbetin son özetten bu yana olan kısmını özetler, kalıcı bilgileri hafızaya yazar.
 *
 * @return array{status:string,note:string,usage:array}
 */
function flxc_mem_summarize( $chat_id, $deadline = 0 ) {
	global $wpdb;
	$chat = flxc_get_chat( $chat_id );
	if ( ! $chat ) {
		return array( 'status' => 'skipped', 'note' => __( 'Sohbet bulunamadı.', 'flx-chat' ), 'usage' => array() );
	}
	$tr  = flxc_mem_transcript( $chat_id, (int) $chat['mem_ts'] );
	$set = function ( $ts ) use ( $wpdb, $chat_id ) {
		// rev artmaz: panel listesi bu yüzden yeniden çizilmez.
		$wpdb->update( flxc_table( 'chats' ), array( 'mem_ts' => (int) $ts ), array( 'id' => (int) $chat_id ) );
	};
	if ( ! $tr['in'] ) {
		$set( max( (int) $chat['last_ts'], (int) $chat['mem_ts'] ) );
		return array( 'status' => 'skipped', 'note' => __( 'Özetlenecek yeni mesaj yok.', 'flx-chat' ), 'usage' => array() );
	}
	$known = array();
	foreach ( flxc_mem_items( $chat ) as $r ) {
		if ( 'fact' === $r['kind'] ) {
			$known[] = '- ' . $r['k'] . ': ' . $r['v'];
		}
	}
	$sys = "Bir yardım kuruluşunun WhatsApp konuşmasını kısa ve tarafsız özetliyorsun. Yalnız JSON döndür:\n"
		. '{"summary":"2-4 cümle: kişi ne istedi, ne yapıldı, açık kalan konu","facts":[{"key":"kısa anahtar","value":"değer"}],"forget":["anahtar"]}' . "\n"
		. "facts: kişi hakkında ileride işe yarayacak kalıcı bilgiler (nasıl hitap edilmek istediği, ad soyadı, yakınlarının adı ve yakınlığı (ör. \"eşinin adı\"), bağışta adına sertifika istediği kişiler ve numaraları (anahtar yakınlık, ör. \"annesi\": \"Fatma Yılmaz · Tel: +90 …\"), telefon numaraları, e-posta, tercih ettiği bağış türü, özel günler, alışkanlıkları). Anahtarlar Türkçe, küçük harf ve kısa olsun. Mevcut bir bilgi değiştiyse aynı anahtarla yeni değeri yaz; aynısını tekrar yazma. Ödeme, kart ve geçici ayrıntıları yazma. Sistemin gönderdiği sertifika ve bildirim mesajlarındaki isimler kişinin adı ya da hitabı değildir (başkası adına olabilir).\n"
		. "forget: kişinin silinmesini istediği bilgilerin anahtarları; tümünü istediyse [\"*\"].\n"
		. 'Konuşmada yer almayan bir şey uydurma. Özet konuşmanın dilinde olsun.';
	$usr = "Mevcut bilgiler:\n" . ( $known ? implode( "\n", $known ) : '-' ) . "\n\nKonuşma:\n" . $tr['text'];
	$opt = array(
		'purpose'     => 'bot',
		'max_tokens'  => 900,
		'temperature' => 0.2,
		'timeout'     => $deadline ? max( 10, min( 60, $deadline - time() ) ) : 60,
	);
	$r = flxc_ai_chat(
		array(
			array( 'role' => 'system', 'content' => $sys ),
			array( 'role' => 'user', 'content' => $usr ),
		),
		$opt
	);
	if ( is_wp_error( $r ) ) {
		return array( 'status' => 'error', 'note' => $r->get_error_message(), 'usage' => array(), 'error' => $r );
	}
	$usage = isset( $r['_usage'] ) ? array( $r['_usage'] ) : array();
	$j     = flxc_mem_json( (string) ( $r['content'] ?? '' ) );
	if ( ! $j ) {
		return array( 'status' => 'error', 'note' => __( 'Yapay zekâ özeti okunamadı.', 'flx-chat' ), 'usage' => $usage );
	}
	$nf = 0;
	foreach ( (array) ( $j['forget'] ?? array() ) as $k ) {
		flxc_mem_forget( $chat, '*' === $k ? '' : (string) $k );
	}
	foreach ( (array) ( $j['facts'] ?? array() ) as $f ) {
		if ( is_array( $f ) && ! empty( $f['key'] ) && isset( $f['value'] ) && '' !== trim( (string) $f['value'] ) ) {
			if ( flxc_mem_set( $chat, $f['key'], $f['value'], 'summary' ) ) {
				$nf++;
			}
		}
	}
	$sum = trim( (string) ( $j['summary'] ?? '' ) );
	if ( '' !== $sum ) {
		flxc_mem_set( $chat, 'özet', $sum, 'summary', 'summary', (int) floor( $tr['last'] / 1000 ) );
	}
	$set( $tr['last'] );
	flxc_bot_stat_add( array( 'summaries' => 1 ), $usage, (int) $chat_id );
	/* translators: 1: message count, 2: fact count */
	return array( 'status' => 'done', 'note' => sprintf( __( '%1$d gelen mesaj özetlendi, %2$d bilgi yazıldı.', 'flx-chat' ), $tr['in'], $nf ), 'usage' => $usage );
}

/* ---------- Asistan araçları ---------- */

function flxc_mem_tools( $f ) {
	return array(
		$f(
			'remember',
			'Kişinin ileride hatırlanması gereken kalıcı bir bilgisini kaydeder ya da günceller (ör. hitap tercihi, eşinin/annesinin adı, bir yakınının telefonu, tercih ettiği bağış türü). Aynı anahtar yeniden verilirse eski değerin üstüne yazılır.',
			array(
				'key'   => array( 'type' => 'string', 'description' => 'Kısa Türkçe anahtar, küçük harf (ör. hitap, eşinin adı, kızının telefonu)' ),
				'value' => array( 'type' => 'string' ),
			),
			array( 'key', 'value' )
		),
		$f(
			'forget',
			'Kişi isterse hafızadaki bir bilgiyi (key) ya da tüm bilgilerini (all=true) siler.',
			array(
				'key' => array( 'type' => 'string' ),
				'all' => array( 'type' => 'boolean' ),
			)
		),
	);
}

/** Araç çağrısı. Test sohbetinde liste $state['mem'] içinde tutulur. */
function flxc_mem_tool( $name, array $a, $chat, array &$state, $sandbox ) {
	if ( 'remember' === $name ) {
		$k = flxc_mem_key( $a['key'] ?? '' );
		$v = flxc_mem_value( $a['value'] ?? '' );
		if ( '' === $k || '' === $v ) {
			return array( 'ok' => false, 'error' => 'key ve value gerekli.' );
		}
		if ( $sandbox ) {
			$list = array_values(
				array_filter(
					(array) ( $state['mem'] ?? array() ),
					function ( $r ) use ( $k ) {
						return $r['k'] !== $k;
					}
				)
			);
			array_unshift( $list, array( 'k' => $k, 'v' => $v, 'kind' => 'fact', 'created_at' => time(), 'updated_at' => time() ) );
			$state['mem'] = array_slice( $list, 0, 60 );
		} else {
			flxc_mem_set( $chat, $k, $v, 'bot' );
		}
		return array( 'ok' => true );
	}
	$all = ! empty( $a['all'] );
	$k   = flxc_mem_key( $a['key'] ?? '' );
	if ( ! $all && '' === $k ) {
		return array( 'ok' => false, 'error' => 'key ya da all=true gerekli.' );
	}
	if ( $sandbox ) {
		$before       = count( (array) ( $state['mem'] ?? array() ) );
		$state['mem'] = $all ? array() : array_values(
			array_filter(
				(array) ( $state['mem'] ?? array() ),
				function ( $r ) use ( $k ) {
					return $r['k'] !== $k && false === mb_strpos( $r['k'], $k );
				}
			)
		);
		$n = $before - count( $state['mem'] );
	} else {
		$n = flxc_mem_forget( $chat, $all ? '' : $k );
	}
	return array( 'ok' => true, 'deleted' => $n );
}

/* ---------- REST (yalnız yönetici) ---------- */

add_action(
	'rest_api_init',
	function () {
		$ns  = 'flx-chat/v1';
		$mng = function () {
			return flxc_can_manage();
		};
		$get = function ( WP_REST_Request $req ) {
			$chat = flxc_get_chat( (int) $req['id'] );
			return $chat ? $chat : new WP_Error( 'flxc_404', __( 'Sohbet bulunamadı.', 'flx-chat' ), array( 'status' => 404 ) );
		};
		register_rest_route(
			$ns,
			'/memory/chat/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $mng,
					'callback'            => function ( WP_REST_Request $req ) use ( $get ) {
						$chat = $get( $req );
						return is_wp_error( $chat ) ? $chat : flxc_mem_for_js( $chat );
					},
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => $mng,
					'callback'            => function ( WP_REST_Request $req ) use ( $get ) {
						global $wpdb;
						$chat = $get( $req );
						if ( is_wp_error( $chat ) ) {
							return $chat;
						}
						$k = flxc_mem_key( $req->get_param( 'key' ) );
						$v = flxc_mem_value( $req->get_param( 'value' ) );
						if ( '' === $k || '' === $v ) {
							return new WP_Error( 'flxc_mem', __( 'Anahtar ve değer gerekli.', 'flx-chat' ), array( 'status' => 400 ) );
						}
						$item = (int) $req->get_param( 'item' );
						$t    = flxc_table( 'memory' );
						if ( $item ) {
							$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d AND who = %s", $item, flxc_mem_who( $chat ) ), ARRAY_A ); // phpcs:ignore
							if ( ! $row ) {
								return new WP_Error( 'flxc_404', __( 'Kayıt bulunamadı.', 'flx-chat' ), array( 'status' => 404 ) );
							}
							if ( 'fact' === $row['kind'] && $k !== $row['k'] ) {
								// Anahtar değiştiyse aynı anahtardaki eski kayıt kalkar.
								$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE who = %s AND kind = 'fact' AND k = %s AND id <> %d", $row['who'], $k, $item ) ); // phpcs:ignore
							}
							$wpdb->update( $t, array( 'k' => 'summary' === $row['kind'] ? $row['k'] : $k, 'v' => $v, 'source' => 'agent', 'updated_at' => time() ), array( 'id' => $item ) );
						} else {
							flxc_mem_set( $chat, $k, $v, 'agent' );
						}
						return flxc_mem_for_js( $chat );
					},
				),
				array(
					'methods'             => 'DELETE',
					'permission_callback' => $mng,
					'callback'            => function ( WP_REST_Request $req ) use ( $get ) {
						$chat = $get( $req );
						if ( is_wp_error( $chat ) ) {
							return $chat;
						}
						if ( 'yes' !== (string) $req->get_param( 'confirm' ) ) {
							return new WP_Error( 'flxc_confirm', __( 'Onay metni yanlış.', 'flx-chat' ), array( 'status' => 400 ) );
						}
						flxc_mem_forget( $chat );
						return flxc_mem_for_js( $chat );
					},
				),
			)
		);
		register_rest_route(
			$ns,
			'/memory/chat/(?P<id>\d+)/summarize',
			array(
				'methods'             => 'POST',
				'permission_callback' => $mng,
				'callback'            => function ( WP_REST_Request $req ) use ( $get ) {
					$chat = $get( $req );
					if ( is_wp_error( $chat ) ) {
						return $chat;
					}
					flxc_jobs_enqueue_summary( (int) $chat['id'], 'manual' );
					flxc_jobs_pump();
					return flxc_mem_for_js( flxc_get_chat( $chat['id'] ) );
				},
			)
		);
		register_rest_route(
			$ns,
			'/memory/item/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'permission_callback' => $mng,
				'callback'            => function ( WP_REST_Request $req ) {
					global $wpdb;
					$t   = flxc_table( 'memory' );
					$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", (int) $req['id'] ), ARRAY_A ); // phpcs:ignore
					if ( ! $row ) {
						return new WP_Error( 'flxc_404', __( 'Kayıt bulunamadı.', 'flx-chat' ), array( 'status' => 404 ) );
					}
					$wpdb->delete( $t, array( 'id' => (int) $row['id'] ) );
					$chat = flxc_get_chat( (int) $row['chat_id'] );
					return $chat ? flxc_mem_for_js( $chat ) : array( 'ok' => true );
				},
			)
		);
		register_rest_route(
			$ns,
			'/memory/stats',
			array(
				'methods'             => 'GET',
				'permission_callback' => $mng,
				'callback'            => function () {
					return flxc_mem_stats( 50 );
				},
			)
		);
	}
);
