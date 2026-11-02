<?php
defined( 'ABSPATH' ) || exit;

/*
 * Asistan benchmark'ı: çok mesajlı senaryolar test sohbetinde (WhatsApp'a gitmeden, kayıt açmadan)
 * seçilen model ve talimatla oynatılır, beklentilere göre geçti/kaldı yazılır.
 * Çalıştırma tarayıcıdan adım adım ilerler (her istekte bir mesaj turu); uzun süren tek istek yok.
 */

require_once FLXC_DIR . 'includes/bench-default.php';

function flxc_bench_groups() {
	// 1.36.0: başka eklentiler kendi senaryo grubunu ekleyebilir (ör. Qurban); filtre boşken liste aynı.
	return (array) apply_filters( 'flxc_bench_groups', array(
		'karsilama' => __( 'Karşılama ve yönlendirme', 'flx-chat' ),
		'bagis'     => __( 'Bağış süreci ve kayıt', 'flx-chat' ),
		'devir'     => __( 'Temsilciye devir', 'flx-chat' ),
		'gereksiz'  => __( 'Gereksiz devir yapmama', 'flx-chat' ),
		'uydurma'   => __( 'Bilgi uydurmama', 'flx-chat' ),
		'kalite'    => __( 'Cevap kalitesi ve üslup', 'flx-chat' ),
		'araclar'   => __( 'Güncel bilgi ve araçlar', 'flx-chat' ),
		'kayitlar'  => __( 'Kayıt, iptal ve dekont', 'flx-chat' ),
		'personel'  => __( 'Personel asistanı', 'flx-chat' ),
		'islemler'  => __( 'İşlem sonuçları (izole)', 'flx-chat' ),
	) );
}

/**
 * 1.18.0: senaryo başlangıç durumu (test sohbetindeki gibi izole; gerçek kayıt yoktur).
 * Anahtar => etiket.
 */
function flxc_bench_setups() {
	return array(
		'don_pending'    => __( 'Ödeme bekleyen bir bağışı var', 'flx-chat' ),
		'don_paid'       => __( 'Ödenmiş bir bağışı ve sertifikası var', 'flx-chat' ),
		'verified_phone' => __( 'Numarası doğrulanmış', 'flx-chat' ),
		'verified_email' => __( 'E-postası doğrulanmış', 'flx-chat' ),
		'ch_whatsapp'    => __( 'Telefon kodu WhatsApp\'tan gider', 'flx-chat' ),
		'ch_sms'         => __( 'Telefon kodu SMS ile gider', 'flx-chat' ),
	);
}

/**
 * 1.18.0: sonda bakılan durum beklentileri ("!" öneki: olmamalı). Anahtar => array( etiket, gereken araçlar ).
 * Gereken araç bu sitede kapalıysa beklenti geçti sayılır (bakılmaz).
 */
function flxc_bench_state_keys() {
	return array(
		'don_new'       => array( __( 'Yeni bağış açıldı', 'flx-chat' ), array( 'create_donation', 'staff_create_donation' ) ),
		'don_canceled'  => array( __( 'Bağış iptal edildi', 'flx-chat' ), array( 'cancel_donation' ) ),
		'cert_sent'     => array( __( 'Sertifika gönderildi', 'flx-chat' ), array( 'certificate_resend' ) ),
		'code_sent'     => array( __( 'Doğrulama kodu gönderildi', 'flx-chat' ), array( 'verify_send' ) ),
		'code_whatsapp' => array( __( 'Kod WhatsApp\'tan gönderildi', 'flx-chat' ), array( 'verify_send' ) ),
		'code_sms'      => array( __( 'Kod SMS ile gönderildi', 'flx-chat' ), array( 'verify_send' ) ),
		'code_email'    => array( __( 'Kod e-postayla gönderildi', 'flx-chat' ), array( 'verify_send' ) ),
		'verified'      => array( __( 'Numara ya da e-posta doğrulandı', 'flx-chat' ), array( 'verify_check' ) ),
		'reminder'      => array( __( 'Hatırlatıcı kuruldu', 'flx-chat' ), array( 'reminder_set' ) ),
		'memory'        => array( __( 'Hafızaya bilgi yazıldı', 'flx-chat' ), array( 'remember' ) ),
	);
}

/* ---------- Senaryolar ---------- */

/** Senaryoda yazılı asistan mesajı varsa "devam" senaryosudur. */
function flxc_bench_is_script( array $turns ) {
	foreach ( $turns as $t ) {
		if ( is_array( $t ) && 'assistant' === ( $t['role'] ?? '' ) ) {
			return true;
		}
	}
	return false;
}

function flxc_bench_normalize( array $in ) {
	$yn    = function ( $v ) {
		$v = is_bool( $v ) ? ( $v ? 'yes' : 'no' ) : (string) $v;
		return in_array( $v, array( 'yes', 'no' ), true ) ? $v : '';
	};
	$lines = function ( $v ) {
		$v   = is_array( $v ) ? $v : preg_split( '/\n+/', (string) $v );
		$out = array();
		foreach ( $v as $x ) {
			$x = trim( sanitize_text_field( (string) $x ) );
			if ( '' !== $x ) {
				$out[] = mb_substr( $x, 0, 200 );
			}
		}
		return array_slice( $out, 0, 20 );
	};
	$e     = isset( $in['expect'] ) && is_array( $in['expect'] ) ? $in['expect'] : array();
	$turns = array();
	foreach ( (array) ( $in['turns'] ?? array() ) as $t ) {
		// Eski biçim düz metin (müşteri); yeni biçim {role: user|assistant, text}.
		$role = is_array( $t ) && 'assistant' === ( $t['role'] ?? '' ) ? 'assistant' : 'user';
		$t    = trim( sanitize_textarea_field( (string) ( is_array( $t ) ? ( $t['text'] ?? '' ) : $t ) ) );
		if ( '' !== $t ) {
			$turns[] = array( 'role' => $role, 'text' => mb_substr( $t, 0, 1000 ) );
		}
	}
	$id = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) ( $in['id'] ?? '' ) ) );
	return array(
		'id'      => '' !== $id ? $id : 's-' . substr( md5( uniqid( '', true ) ), 0, 8 ),
		'group'   => mb_substr( sanitize_text_field( (string) ( $in['group'] ?? '' ) ), 0, 60 ),
		'title'   => mb_substr( sanitize_text_field( (string) ( $in['title'] ?? '' ) ), 0, 120 ),
		'desc'    => mb_substr( sanitize_textarea_field( (string) ( $in['desc'] ?? '' ) ), 0, 500 ),
		'turns'   => array_slice( $turns, 0, 16 ),
		'expect'  => array(
			'handoff'      => $yn( $e['handoff'] ?? '' ),
			'donation'     => $yn( $e['donation'] ?? '' ),
			'price_list'   => $yn( $e['price_list'] ?? '' ),
			'order_qty'    => max( 0, min( 1000, (int) ( $e['order_qty'] ?? 0 ) ) ),
			'contains'     => $lines( $e['contains'] ?? array() ),
			'not_contains' => $lines( $e['not_contains'] ?? array() ),
			'judge'        => mb_substr( sanitize_textarea_field( (string) ( $e['judge'] ?? '' ) ), 0, 1000 ),
			// 1.16.0: çağrılması gereken / çağrılmaması gereken araçlar (her satır bir araç, seçenek a|b).
			'tools'        => array_map( 'flxc_bench_tool_line', $lines( $e['tools'] ?? array() ) ),
			'no_tools'     => array_map( 'flxc_bench_tool_line', $lines( $e['no_tools'] ?? array() ) ),
			// 1.18.0: sonda durumda olması (ya da "!" ile olmaması) gerekenler.
			'state'        => array_values(
				array_filter(
					array_map(
						function ( $x ) {
							$x   = trim( (string) $x );
							$neg = 0 === strpos( $x, '!' );
							$k   = sanitize_key( ltrim( $x, '!' ) );
							return isset( flxc_bench_state_keys()[ $k ] ) ? ( $neg ? '!' : '' ) . $k : '';
						},
						$lines( $e['state'] ?? array() )
					)
				)
			),
		),
		// 1.16.0: kim yazıyor: '' müşteri, staff = Flux Roles'de personel aracı açık ilk kullanıcı.
		'as'      => 'staff' === ( $in['as'] ?? '' ) ? 'staff' : '',
		// 1.18.0: başlangıç durumu.
		'setup'   => array_values( array_intersect( array_map( 'sanitize_key', (array) ( $in['setup'] ?? array() ) ), array_keys( flxc_bench_setups() ) ) ),
		'builtin' => empty( $in['builtin'] ) ? 0 : 1,
		// 1.36.0: boş = Çağrı asistanı; dolu = senaryoyu flxc_bench_turn filtresiyle başka eklenti oynatır.
		'runner'  => sanitize_key( (string) ( $in['runner'] ?? '' ) ),
		'sort'    => (int) ( $in['sort'] ?? 0 ),
	);
}

function flxc_bench_tool_line( $x ) {
	return implode( '|', array_filter( array_map( 'sanitize_key', explode( '|', (string) $x ) ) ) );
}

/** Personel senaryolarında yazan kişi: personel bağışı açabilen ilk test kullanıcısı (yoksa ilk personel). */
function flxc_bench_staff_user() {
	if ( ! function_exists( 'flxc_staff_test_users' ) ) {
		return 0;
	}
	$users = flxc_staff_test_users();
	foreach ( $users as $u ) {
		$id = flxc_staff_identity( flxc_bot_sandbox_chat(), array( 'as_user' => $u['id'] ) );
		if ( $id && flxc_staff_can( $id, 'flxb_create_ref' ) ) {
			return (int) $u['id'];
		}
	}
	return $users ? (int) $users[0]['id'] : 0;
}

/** 1.16.0: emekliye ayrılan hazır senaryolar (yerine güncel sürümü eklendi); düzenlenmemişlerse kayıtlı setten çıkar. */
function flxc_bench_retired() {
	// 1.18.0: d-p1 yerine kayıt kontrolü de yapan d-p4. 1.20.0: d-t4 yerine onay turlu d-t7, d-ck yerine d-cm (fiyat metinde de olabilir).
	// 1.22.0: d-b3 yerine eksik bilgi sormayı da kabul eden d-b7, d-g1 yerine fiyatı metinde de kabul eden d-g7.
	return array( 'd-u4', 'd-p1', 'd-t4', 'd-ck', 'd-b3', 'd-g1' );
}

/** Kayıtlı senaryolar; hiç kaydedilmediyse hazır set. Sonraki sürümlerde eklenen hazır senaryolar kayıtlı sete de katılır. */
function flxc_bench_scenarios() {
	$s = get_option( 'flxc_bot_bench' );
	if ( ! is_array( $s ) || ! isset( $s['items'] ) ) {
		return flxc_bench_default_scenarios();
	}
	$items = array();
	foreach ( (array) $s['items'] as $it ) {
		$it = flxc_bench_normalize( (array) $it );
		if ( ! empty( $it['builtin'] ) && in_array( $it['id'], flxc_bench_retired(), true ) ) {
			continue;
		}
		$items[] = $it;
	}
	$seen = flxc_bench_seen( $s );
	$max  = 0;
	foreach ( $items as $it ) {
		$max = max( $max, (int) $it['sort'] );
	}
	foreach ( flxc_bench_default_scenarios() as $d ) {
		if ( ! in_array( $d['id'], $seen, true ) ) {
			$d['sort'] = ++$max;
			$items[]   = $d;
		}
	}
	return $items;
}

/** Kullanıcıya bir kez sunulmuş hazır senaryo kimlikleri (silinen hazır senaryo geri gelmesin). */
function flxc_bench_seen( $opt ) {
	if ( isset( $opt['seen'] ) && is_array( $opt['seen'] ) ) {
		return $opt['seen'];
	}
	// 1.6.2 öncesi kayıt: o zamanki 30 hazır senaryo görülmüş sayılır, sonradan eklenenler yeni.
	$old = 'k1 k2 k3 k4 k5 b1 b2 b3 b4 b5 b6 d1 d2 d3 d4 d5 g1 g2 g3 g4 u1 u2 u3 u4 u5 q1 q2 q3 q4 q5';
	return array_map(
		function ( $k ) {
			return 'd-' . $k;
		},
		explode( ' ', $old )
	);
}

function flxc_bench_store( array $items ) {
	usort(
		$items,
		function ( $a, $b ) {
			return (int) $a['sort'] - (int) $b['sort'];
		}
	);
	$seen = flxc_bench_seen( get_option( 'flxc_bot_bench' ) );
	foreach ( $items as $it ) {
		if ( ! empty( $it['builtin'] ) && ! in_array( $it['id'], $seen, true ) ) {
			$seen[] = $it['id'];
		}
	}
	update_option( 'flxc_bot_bench', array( 'items' => array_values( $items ), 'seen' => $seen ), false );
}

function flxc_bench_scenario( $id ) {
	foreach ( flxc_bench_scenarios() as $s ) {
		if ( $s['id'] === $id ) {
			return $s;
		}
	}
	return null;
}

function flxc_bench_save_scenario( array $in ) {
	$sc = flxc_bench_normalize( $in );
	if ( '' === $sc['title'] || ! $sc['turns'] ) {
		return new WP_Error( 'flxc_bench', __( 'Başlık ve en az bir mesaj gerekli.', 'flx-chat' ) );
	}
	if ( 'user' !== end( $sc['turns'] )['role'] ) {
		return new WP_Error( 'flxc_bench', __( 'Son mesaj müşterinin olmalı; asistan ona cevap verir.', 'flx-chat' ) );
	}
	$items = flxc_bench_scenarios();
	$found = false;
	$max   = 0;
	foreach ( $items as $i => $s ) {
		$max = max( $max, (int) $s['sort'] );
		if ( $s['id'] === $sc['id'] ) {
			$sc['builtin'] = $s['builtin'];
			$sc['sort']    = $s['sort'];
			$items[ $i ]   = $sc;
			$found         = true;
		}
	}
	if ( ! $found ) {
		$sc['builtin'] = 0;
		$sc['sort']    = $max + 1;
		$items[]       = $sc;
	}
	flxc_bench_store( $items );
	return $sc;
}

function flxc_bench_delete_scenario( $id ) {
	flxc_bench_store(
		array_values(
			array_filter(
				flxc_bench_scenarios(),
				function ( $s ) use ( $id ) {
					return $s['id'] !== $id;
				}
			)
		)
	);
}

/** Hazır senaryoları ilk hâline döndürür; kendi eklenenler kalır. */
function flxc_bench_reset_defaults() {
	$own = array_filter(
		flxc_bench_scenarios(),
		function ( $s ) {
			return empty( $s['builtin'] );
		}
	);
	$def = flxc_bench_default_scenarios();
	$n   = count( $def );
	foreach ( $own as $s ) {
		$s['sort'] = $n++;
		$def[]     = $s;
	}
	flxc_bench_store( $def );
}

/** Mesajlardaki {tur}, {birim}, {brand} yer tutucuları. */
function flxc_bench_fill( $text, array $state = array() ) {
	$types = flxc_ai_types();
	$tur   = __( 'bağış', 'flx-chat' );
	$birim = $tur;
	if ( $types ) {
		$tur = mb_strtolower( $types[0]['title'], 'UTF-8' );
		foreach ( $types[0]['options'] as $o ) {
			if ( '' !== $o['unit'] ) {
				$birim = mb_strtolower( $o['unit'], 'UTF-8' );
				break;
			}
		}
	}
	// {kod}: test panelindeki son doğrulama kodu (müşteri kodu "almış" gibi yazar); kod yoksa 000000.
	$kod = (string) ( $state['vc_test']['code'] ?? '000000' );
	return strtr( (string) $text, array( '{tur}' => $tur, '{birim}' => $birim, '{brand}' => flxc_brand_name(), '{kod}' => $kod ) );
}

/* ---------- Kayıtlar ---------- */

function flxc_bench_run_row( $id ) {
	global $wpdb;
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'bench_runs' ) . ' WHERE id = %d', $id ), ARRAY_A );
	if ( $r ) {
		$r['meta'] = flxc_decode( $r['meta'] );
	}
	return $r;
}

function flxc_bench_update_run( $id, array $f ) {
	global $wpdb;
	if ( isset( $f['meta'] ) && is_array( $f['meta'] ) ) {
		$f['meta'] = flxc_json( $f['meta'] );
	}
	$wpdb->update( flxc_table( 'bench_runs' ), $f, array( 'id' => (int) $id ) );
}

function flxc_bench_prompt_choice( $choice ) {
	$choice = (string) $choice;
	if ( 'builtin' === $choice ) {
		return array( flxc_bot_default_prompt(), __( 'Yerleşik metin', 'flx-chat' ) );
	}
	if ( '' !== $choice && 'live' !== $choice ) {
		$v = flxc_bot_prompt_version( $choice );
		if ( $v ) {
			/* translators: %s: version id */
			return array( (string) $v['text'], '' !== $v['note'] ? $v['note'] : sprintf( __( 'Sürüm %s', 'flx-chat' ), $v['id'] ) );
		}
	}
	$active = flxc_bot_prompt_version( flxc_bot_prompt_store()['active'] );
	/* translators: %s: version label */
	return array( flxc_bot_prompt_template(), sprintf( __( 'Yayındaki talimat (%s)', 'flx-chat' ), $active ? ( '' !== $active['note'] ? $active['note'] : $active['id'] ) : __( 'yerleşik', 'flx-chat' ) ) );
}

/** Yeni çalıştırma kaydı açar. */
function flxc_bench_create_run( array $p ) {
	global $wpdb;
	$ids = array_map( 'strval', (array) ( $p['scenarios'] ?? array() ) );
	$all = flxc_bench_scenarios();
	$q   = array();
	foreach ( $all as $s ) {
		if ( ! $ids || in_array( $s['id'], $ids, true ) ) {
			$q[] = $s['id'];
		}
	}
	if ( ! $q ) {
		return new WP_Error( 'flxc_bench', __( 'Çalıştırılacak senaryo yok.', 'flx-chat' ) );
	}
	list( $text, $label ) = flxc_bench_prompt_choice( $p['prompt'] ?? 'live' );
	$model = flxc_clean_model_id( $p['model'] ?? '' );
	$judge = flxc_clean_model_id( $p['judge'] ?? '' );
	$limit = (float) ( $p['limit'] ?? 0.5 );
	$limit = $limit > 0 ? min( 100, $limit ) : 0.5;
	// Claude önbelleği bu çalıştırma için (boş = genel ayar); karşılaştırma için kayda yazılır.
	$cache = flxc_ai_cache_mode( sanitize_key( (string) ( $p['cache'] ?? '' ) ) );
	$wpdb->insert(
		flxc_table( 'bench_runs' ),
		array(
			'created_at'   => flxc_now(),
			'user_id'      => get_current_user_id(),
			'model'        => '' !== $model ? $model : flxc_ai_model( 'bot' ),
			'judge_model'  => '' !== $judge ? $judge : flxc_ai_model(),
			'prompt_label' => mb_substr( $label, 0, 190 ),
			'prompt_text'  => $text,
			'status'       => 'running',
			'limit_usd'    => $limit,
			'cost'         => 0,
			'total'        => count( $q ),
			'meta'         => flxc_json( array( 'queue' => $q, 'cache' => $cache ) ),
		)
	);
	if ( ! $wpdb->insert_id ) {
		// Tablo yoksa ya da yazılamıyorsa sessizce kaybolmasın.
		flxc_install_tables();
		/* translators: %s: database error */
		return new WP_Error( 'flxc_bench_db', sprintf( __( 'Çalıştırma kaydedilemedi (veritabanı: %s). Sayfayı yenileyip tekrar deneyin.', 'flx-chat' ), $wpdb->last_error ? $wpdb->last_error : '?' ) );
	}
	return flxc_bench_run_row( (int) $wpdb->insert_id );
}

/* ---------- Çalıştırma adımı ---------- */

/**
 * Bir adım: sıradaki senaryonun bir mesaj turu ya da değerlendirmesi.
 * @return array|WP_Error {run, result, done}
 */
/**
 * Bir adımın üst süresi (sn). Paylaşımlı hostinglerde istekler ~60 sn'de kesilebilir; PHP süre sınırı
 * daha kısaysa ona göre kısalır.
 */
function flxc_bench_step_sec() {
	$max = (int) ini_get( 'max_execution_time' );
	$sec = (int) apply_filters( 'flxc_bench_step_sec', 45 );
	return $max > 0 ? max( 15, min( $sec, $max - 8 ) ) : $sec;
}

function flxc_bench_step( $run_id ) {
	global $wpdb;
	// Aynı çalıştırmayı iki sekme sürerse adımlar sıraya girer. Adım en çok flxc_bench_step_sec() sürer; süreç
	// yarıda ölürse (sunucu süre sınırı) kilit kısa sürede düşer ve adım yeniden denenir.
	$now = time();
	$got = $wpdb->query( $wpdb->prepare( 'UPDATE ' . flxc_table( 'bench_runs' ) . ' SET locked_until = %d WHERE id = %d AND locked_until < %d', $now + flxc_bench_step_sec() + 15, $run_id, $now ) ); // phpcs:ignore
	if ( ! $got ) {
		$run = flxc_bench_run_row( $run_id );
		if ( ! $run ) {
			return new WP_Error( 'flxc_bench', __( 'Çalıştırma bulunamadı.', 'flx-chat' ) );
		}
		return array( 'run' => flxc_bench_run_for_js( $run ), 'result' => null, 'done' => 'running' !== $run['status'], 'busy' => true );
	}
	try {
		return flxc_bench_step_locked( $run_id );
	} finally {
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . flxc_table( 'bench_runs' ) . ' SET locked_until = 0, beat = %d WHERE id = %d', time(), $run_id ) ); // phpcs:ignore
	}
}

/**
 * Hesap hatası (OpenRouter bütçesi/kredisi, anahtar, hız sınırı): yarım senaryo silinir, çalıştırma "paused" olur
 * ve nedeni kaydedilir. "Devam et" ile o senaryodan sürer.
 */
function flxc_bench_pause( array $run, array $res, WP_Error $e ) {
	global $wpdb;
	$wpdb->delete( flxc_table( 'bench_results' ), array( 'id' => (int) $res['id'] ) );
	$meta           = (array) $run['meta'];
	$meta['reason'] = $e->get_error_message();
	flxc_bench_update_run( $run['id'], array( 'status' => 'paused', 'meta' => $meta ) );
	return array( 'run' => flxc_bench_run_for_js( flxc_bench_run_row( $run['id'] ) ), 'result' => null, 'done' => true );
}

/** Zaman aşımı / bağlantı hatası: aynı adım hemen yeniden denenebilir. */
function flxc_bench_retryable( $e ) {
	return is_wp_error( $e ) && in_array( $e->get_error_code(), array( 'flxc_bot_deadline', 'flxc_ai_http' ), true );
}

/**
 * Yarım kalan adımı (zaman aşımı ya da sunucunun kestiği süreç) yeniden deneme sayacı. Adımın verisi
 * değiştirilmeden yalnız sayaç yazılır; 2 denemeden sonra false döner ve hata kaydedilir.
 */
function flxc_bench_retry( array $res ) {
	global $wpdb;
	$d     = flxc_decode( $res['data'] );
	$tries = (int) ( $d['tries'] ?? 0 ) + 1;
	if ( $tries > 2 ) {
		return false;
	}
	$d['tries'] = $tries;
	$wpdb->update( flxc_table( 'bench_results' ), array( 'data' => flxc_json( $d ) ), array( 'id' => (int) $res['id'] ) );
	return true;
}

function flxc_bench_step_locked( $run_id ) {
	global $wpdb;
	$run = flxc_bench_run_row( $run_id );
	if ( ! $run ) {
		return new WP_Error( 'flxc_bench', __( 'Çalıştırma bulunamadı.', 'flx-chat' ) );
	}
	if ( 'running' !== $run['status'] ) {
		return array( 'run' => flxc_bench_run_for_js( $run ), 'result' => null, 'done' => true );
	}
	if ( (float) $run['cost'] >= (float) $run['limit_usd'] ) {
		flxc_bench_update_run( $run_id, array( 'status' => 'limit' ) );
		return array( 'run' => flxc_bench_run_for_js( flxc_bench_run_row( $run_id ) ), 'result' => null, 'done' => true );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 180 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	$rt  = flxc_table( 'bench_results' );
	$res = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $rt WHERE run_id = %d AND status = 'running' ORDER BY id LIMIT 1", $run_id ), ARRAY_A ); // phpcs:ignore
	if ( ! $res ) {
		$done = $wpdb->get_col( $wpdb->prepare( "SELECT scenario_id FROM $rt WHERE run_id = %d", $run_id ) ); // phpcs:ignore
		$next = null;
		foreach ( (array) ( $run['meta']['queue'] ?? array() ) as $sid ) {
			if ( ! in_array( $sid, $done, true ) ) {
				$next = $sid;
				break;
			}
		}
		$sc = null;
		while ( null !== $next && ! ( $sc = flxc_bench_scenario( $next ) ) ) {
			// Senaryo bu arada silindiyse atla.
			$wpdb->insert( $rt, array( 'run_id' => $run_id, 'scenario_id' => $next, 'status' => 'skipped', 'created_at' => flxc_now(), 'data' => '{}' ) );
			$done[] = $next;
			$next   = null;
			foreach ( (array) $run['meta']['queue'] as $sid ) {
				if ( ! in_array( $sid, $done, true ) ) {
					$next = $sid;
					break;
				}
			}
		}
		if ( ! $sc ) {
			flxc_bench_update_run( $run_id, array( 'status' => 'done' ) );
			return array( 'run' => flxc_bench_run_for_js( flxc_bench_run_row( $run_id ) ), 'result' => null, 'done' => true );
		}
		$wpdb->insert(
			$rt,
			array(
				'run_id'      => $run_id,
				'scenario_id' => $sc['id'],
				'grp'         => $sc['group'],
				'title'       => $sc['title'],
				'status'      => 'running',
				'created_at'  => flxc_now(),
				'data'        => flxc_json( flxc_bench_initial_data( $sc ) ),
			)
		);
		$res = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $rt WHERE id = %d", $wpdb->insert_id ), ARRAY_A ); // phpcs:ignore
	}
	$d     = flxc_decode( $res['data'] );
	$sc    = $d['sc'];
	// Önceki deneme sunucu tarafından kesildiyse (adım kaydı yarım) say; üçüncüde hata yazılıp geçilir.
	if ( ! empty( $d['open'] ) && ! flxc_bench_retry( $res ) ) {
		$d['open']         = 0;
		$d['transcript'][] = array( 'role' => 'error', 'content' => __( 'Bu adım üç kez yarıda kaldı (sunucu isteği kesti ya da yapay zekâ cevap vermedi); senaryo hatalı sayıldı.', 'flx-chat' ) );
		$d['checks']       = array();
		$wpdb->update( $rt, array( 'status' => 'error', 'data' => flxc_json( $d ) ), array( 'id' => (int) $res['id'] ) );
		flxc_bench_recount( $run_id );
		return array( 'run' => flxc_bench_run_for_js( flxc_bench_run_row( $run_id ) ), 'result' => null, 'done' => false );
	}
	$d['tries'] = (int) ( flxc_decode( $wpdb->get_var( $wpdb->prepare( "SELECT data FROM $rt WHERE id = %d", $res['id'] ) ) )['tries'] ?? 0 ); // phpcs:ignore
	$wpdb->update( $rt, array( 'data' => flxc_json( array_merge( $d, array( 'open' => 1 ) ) ) ), array( 'id' => (int) $res['id'] ) );
	$res['data'] = flxc_json( $d );
	$upd   = array();
	$usage = null;
	$role  = 'bot';
	$t0    = microtime( true );

	$turns = flxc_bench_normalize( $sc )['turns'];
	if ( (int) $d['i'] < count( $turns ) && empty( $d['handoff'] ) ) {
		if ( flxc_bench_is_script( $turns ) ) {
			// Devam senaryosu: yazılı geçmişin tamamı hazır eklenir, asistan yalnız en sonda cevap verir.
			foreach ( $turns as $tn ) {
				$msg               = flxc_bench_fill( $tn['text'] );
				$d['history'][]    = array( 'role' => $tn['role'], 'content' => $msg );
				$d['transcript'][] = array( 'role' => $tn['role'], 'content' => $msg, 'scripted' => 1 );
			}
			$d['i'] = count( $turns ) - 1;
		} else {
			$msg               = flxc_bench_fill( $turns[ (int) $d['i'] ]['text'], (array) $d['state'] );
			$d['history'][]    = array( 'role' => 'user', 'content' => $msg );
			$d['transcript'][] = array( 'role' => 'user', 'content' => $msg );
		}
		$bm = $run['model'] === flxc_ai_model( 'bot' ) ? '' : $run['model'];
		if ( '' !== (string) ( $sc['runner'] ?? '' ) ) {
			// 1.36.0: başka eklentinin senaryosu; aynı dönüş biçimi (replies, state, log, handoff, usage).
			$r = apply_filters( 'flxc_bench_turn', new WP_Error( 'flxc_bench_runner', __( 'Bu senaryoyu oynatacak eklenti kapalı.', 'flx-chat' ) ), $sc['runner'], $d['history'], (array) $d['state'], $bm, $sc );
		} else {
			$r = flxc_bot_sandbox_turn( $d['history'], (array) $d['state'], $run['prompt_text'], $bm, time() + flxc_bench_step_sec(), (string) ( $run['meta']['cache'] ?? '' ) );
		}
		if ( flxc_ai_is_account_error( $r ) ) {
			return flxc_bench_pause( $run, $res, $r );
		}
		if ( flxc_bench_retryable( $r ) && flxc_bench_retry( $res ) ) {
			return array( 'run' => flxc_bench_run_for_js( flxc_bench_run_row( $run_id ) ), 'result' => null, 'done' => false );
		}
		$d['i'] = (int) $d['i'] + 1;
		if ( is_wp_error( $r ) ) {
			$d['transcript'][] = array( 'role' => 'error', 'content' => $r->get_error_message() );
			$upd['status']     = 'error';
			$d['checks']       = array();
		} else {
			$d['state'] = $r['state'];
			$usage      = $r['usage'];
			$role       = 'bot';
			$tools      = array();
			foreach ( (array) $r['log'] as $l ) {
				$tools[]      = $l['tool'] . ( isset( $l['result']['ok'] ) && false === $l['result']['ok'] ? ' ✕' : '' );
				$d['tools'][] = $l['tool'];
			}
			if ( ! empty( $r['keyword'] ) ) {
				$tools[]      = 'handoff (' . __( 'anahtar kelime', 'flx-chat' ) . ')';
				$d['tools'][] = 'handoff';
			}
			$replies = $r['replies'] ? $r['replies'] : array( '' );
			foreach ( $replies as $k => $text ) {
				if ( '' !== $text ) {
					$d['history'][] = array( 'role' => 'assistant', 'content' => $text );
				}
				$d['transcript'][] = array(
					'role'    => 'assistant',
					'content' => $text,
					'tools'   => 0 === $k ? implode( ' · ', $tools ) : '',
					// Rapor için araç çağrılarının tamamı (argüman ve sonuç).
					'calls'   => 0 === $k ? array_map( function ( $l ) {
						return array( 'tool' => $l['tool'], 'args' => $l['args'], 'result' => $l['result'] );
					}, (array) $r['log'] ) : array(),
					'usage'   => count( $replies ) - 1 === $k ? $usage : null,
				) + ( empty( $r['auto'][ $k ] ) ? array() : array( 'auto' => 1 ) );
			}
			if ( ! empty( $r['handoff'] ) ) {
				$d['handoff'] = true;
				if ( $d['i'] < count( $turns ) ) {
					$d['transcript'][] = array( 'role' => 'note', 'content' => __( 'Sohbet ekibe devredildi; kalan mesajlar gönderilmedi.', 'flx-chat' ) );
				}
			}
		}
	} else {
		// Tüm mesajlar oynandı: beklentiler ve değerlendirici.
		$checks = flxc_bench_checks( $sc, $d );
		if ( '' !== $sc['expect']['judge'] ) {
			$j = flxc_bench_judge( $sc, $d['transcript'], $run['judge_model'], time() + flxc_bench_step_sec(), (string) ( $run['meta']['cache'] ?? '' ) );
			if ( flxc_ai_is_account_error( $j ) ) {
				return flxc_bench_pause( $run, $res, $j );
			}
			if ( flxc_bench_retryable( $j ) && flxc_bench_retry( $res ) ) {
				return array( 'run' => flxc_bench_run_for_js( flxc_bench_run_row( $run_id ) ), 'result' => null, 'done' => false );
			}
			if ( is_wp_error( $j ) ) {
				$checks[] = array( 'key' => 'judge', 'pass' => false, 'detail' => $j->get_error_message() );
			} else {
				$usage    = $j['usage'];
				$role     = 'judge';
				$checks[] = array( 'key' => 'judge', 'pass' => (bool) $j['pass'], 'detail' => $j['reason'], 'score' => $j['score'], 'log' => $j['log'] );
			}
		}
		$d['checks']   = $checks;
		$pass          = true;
		foreach ( $checks as $c ) {
			$pass = $pass && $c['pass'];
		}
		$upd['status'] = $pass ? 'pass' : 'fail';
		if ( '' !== $sc['expect']['handoff'] ) {
			$exp           = 'yes' === $sc['expect']['handoff'];
			$got           = ! empty( $d['handoff'] );
			$upd['hclass'] = $exp ? ( $got ? 'tp' : 'fn' ) : ( $got ? 'fp' : 'tn' );
		}
	}

	if ( $usage ) {
		// Model ve rol başına kullanım (Benchmark üstündeki toplamlar için).
		$meta = (array) $run['meta'];
		$mk   = 'judge' === $role ? (string) $run['judge_model'] : (string) $run['model'];
		$cur  = $meta['by'][ $mk ][ $role ] ?? array( 'in' => 0, 'out' => 0, 'cost' => 0.0, 'n' => 0 );
		$meta['by'][ $mk ][ $role ] = array(
			'in'   => (int) $cur['in'] + (int) $usage['in'],
			'out'  => (int) $cur['out'] + (int) $usage['out'],
			'cost' => (float) $cur['cost'] + (float) $usage['cost'],
			'n'    => (int) $cur['n'] + max( 1, (int) ( $usage['calls'] ?? 1 ) ),
		);
		// Önbellek etkisi: okunan/yazılan token, gerçek maliyet ve önbelleksiz olsaydı maliyet.
		$cs            = (array) ( $meta['cs'] ?? array() ) + array( 'cr' => 0, 'cw' => 0, 'cost' => 0.0, 'full' => 0.0 );
		$meta['cs']    = array(
			'cr'   => (int) $cs['cr'] + (int) ( $usage['cr'] ?? 0 ),
			'cw'   => (int) $cs['cw'] + (int) ( $usage['cw'] ?? 0 ),
			'cost' => (float) $cs['cost'] + (float) $usage['cost'],
			'full' => (float) $cs['full'] + (float) ( $usage['full'] ?? $usage['cost'] ),
		);
		flxc_bench_update_run( $run_id, array( 'meta' => $meta ) );
		$upd['cost']       = (float) $res['cost'] + (float) $usage['cost'];
		$upd['tokens_in']  = (int) $res['tokens_in'] + (int) $usage['in'];
		$upd['tokens_out'] = (int) $res['tokens_out'] + (int) $usage['out'];
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . flxc_table( 'bench_runs' ) . ' SET cost = cost + %f, tokens_in = tokens_in + %d, tokens_out = tokens_out + %d WHERE id = %d', (float) $usage['cost'], (int) $usage['in'], (int) $usage['out'], $run_id ) ); // phpcs:ignore
	}
	$upd['ms']   = (int) $res['ms'] + (int) ( ( microtime( true ) - $t0 ) * 1000 );
	$d['open']   = 0;
	$d['tries']  = 0;
	$upd['data'] = flxc_json( $d );
	$wpdb->update( $rt, $upd, array( 'id' => (int) $res['id'] ) );
	if ( isset( $upd['status'] ) ) {
		flxc_bench_recount( $run_id );
	}
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $rt WHERE id = %d", $res['id'] ), ARRAY_A ); // phpcs:ignore
	return array( 'run' => flxc_bench_run_for_js( flxc_bench_run_row( $run_id ) ), 'result' => flxc_bench_result_for_js( $row ), 'done' => false );
}

function flxc_bench_initial_data( array $sc ) {
	$d = array( 'sc' => $sc, 'i' => 0, 'history' => array(), 'state' => flxc_bench_setup_state( (array) ( $sc['setup'] ?? array() ) ), 'transcript' => array(), 'tools' => array(), 'handoff' => false );
	if ( ! empty( $sc['setup'] ) ) {
		$labels = array_intersect_key( flxc_bench_setups(), array_flip( $sc['setup'] ) );
		/* translators: %s: setup list */
		$d['transcript'][] = array( 'role' => 'note', 'content' => sprintf( __( 'Başlangıç (test): %s', 'flx-chat' ), implode( ', ', $labels ) ) );
	}
	$d['seed'] = count( (array) ( $d['state']['tdons'] ?? array() ) );
	if ( $d['seed'] ) {
		// Başlangıçta bağışı olan senaryoya örnek geçmiş bağış eklenmez.
		$d['state']['seeded'] = 1;
	}
	if ( 'staff' === ( $sc['as'] ?? '' ) ) {
		$uid = flxc_bench_staff_user();
		if ( $uid ) {
			$d['state']['as_user'] = $uid;
			$u                     = get_userdata( $uid );
			/* translators: %s: user name */
			$d['transcript'][] = array( 'role' => 'note', 'content' => sprintf( __( 'Personel olarak yazıyor: %s', 'flx-chat' ), $u ? $u->display_name : '#' . $uid ) );
		} else {
			$d['transcript'][] = array( 'role' => 'note', 'content' => __( 'Personel aracı açık kullanıcı yok (Flux Roles, Profil › WhatsApp numarası); müşteri olarak oynatıldı.', 'flx-chat' ) );
		}
	}
	return $d;
}

/** 1.18.0: senaryonun başlangıç durumu; test sohbetindeki test bağışı ve doğrulama yapısıyla aynı. */
function flxc_bench_setup_state( array $setup ) {
	$st   = array();
	$o    = array( 'title' => flxc_bench_fill( '{tur}' ), 'qty' => 1, 'unit' => '', 'amount' => 150, 'currency' => 'TRY' );
	$types = flxc_ai_types();
	if ( $types ) {
		$t  = $types[0];
		$op = $t['options'][0] ?? array();
		$o  = array( 'title' => $t['title'], 'qty' => 1, 'unit' => (string) ( $op['unit'] ?? '' ), 'amount' => (float) ( $op['price'] ?? 0 ), 'currency' => '' !== $t['currency'] ? $t['currency'] : 'TRY' );
	}
	$add = function ( $status, $days ) use ( &$st, $o ) {
		$list   = (array) ( $st['tdons'] ?? array() );
		$id     = 900001 + count( $list );
		$list[] = array(
			'id'     => $id,
			'title'  => $o['title'] . ' · ' . number_format_i18n( $o['qty'] ) . ( '' !== $o['unit'] ? ' ' . $o['unit'] : '' ),
			'amount' => flxc_money( (float) $o['amount'], (string) $o['currency'] ),
			'method' => 'havale',
			'status' => $status,
			'date'   => wp_date( 'j F Y H:i', time() - $days * DAY_IN_SECONDS ),
		);
		$st['tdons'] = $list;
		return $id;
	};
	if ( in_array( 'don_paid', $setup, true ) ) {
		$add( 'completed', 20 );
	}
	if ( in_array( 'don_pending', $setup, true ) ) {
		$st['donation'] = $add( 'pending', 1 );
	}
	if ( in_array( 'verified_phone', $setup, true ) ) {
		$st['phones_ok'] = array( '905321112233' );
	}
	if ( in_array( 'verified_email', $setup, true ) ) {
		$st['emails_ok'] = array( 'test.bagisci@example.com' );
		$st['email']     = 'test.bagisci@example.com';
	}
	foreach ( array( 'ch_whatsapp' => 'whatsapp', 'ch_sms' => 'sms' ) as $k => $ch ) {
		if ( in_array( $k, $setup, true ) ) {
			$st['test_channel'] = $ch;
		}
	}
	return $st;
}

/**
 * 1.16.0: çalıştırma öncesi ücret tahmini için geçmiş ortalamalar (son 400 sonuç).
 * Asistan ve değerlendirici token'ları ayrı tutulur; fiyat tarayıcıda seçilen modelle çarpılır.
 * by: senaryo başına ortalama {bi, bo, ji, jo, n}; turn: müşteri mesajı başına asistan ortalaması; judge: değerlendirici ortalaması.
 */
function flxc_bench_estimate_data() {
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT scenario_id, tokens_in, tokens_out, data FROM ' . flxc_table( 'bench_results' ) . " WHERE status IN ('pass','fail') ORDER BY id DESC LIMIT 400", ARRAY_A ); // phpcs:ignore
	$by   = array();
	$tt   = array( 'i' => 0, 'o' => 0, 'n' => 0 );
	$jt   = array( 'i' => 0, 'o' => 0, 'n' => 0 );
	foreach ( (array) $rows as $r ) {
		$d  = flxc_decode( $r['data'] );
		$bi = 0;
		$bo = 0;
		$u  = 0;
		foreach ( (array) ( $d['transcript'] ?? array() ) as $m ) {
			if ( 'user' === ( $m['role'] ?? '' ) ) {
				$u++;
			}
			if ( 'assistant' === ( $m['role'] ?? '' ) && ! empty( $m['usage'] ) ) {
				$bi += (int) ( $m['usage']['in'] ?? 0 );
				$bo += (int) ( $m['usage']['out'] ?? 0 );
			}
		}
		if ( ! $bi ) {
			continue;
		}
		$ji = max( 0, (int) $r['tokens_in'] - $bi );
		$jo = max( 0, (int) $r['tokens_out'] - $bo );
		$k  = (string) $r['scenario_id'];
		if ( ! isset( $by[ $k ] ) ) {
			$by[ $k ] = array( 'bi' => 0, 'bo' => 0, 'ji' => 0, 'jo' => 0, 'n' => 0 );
		}
		$by[ $k ]['bi'] += $bi;
		$by[ $k ]['bo'] += $bo;
		$by[ $k ]['ji'] += $ji;
		$by[ $k ]['jo'] += $jo;
		$by[ $k ]['n']++;
		$tt['i'] += $bi;
		$tt['o'] += $bo;
		$tt['n'] += max( 1, $u );
		if ( $ji ) {
			$jt['i'] += $ji;
			$jt['o'] += $jo;
			$jt['n']++;
		}
	}
	foreach ( $by as $k => $x ) {
		$by[ $k ] = array(
			'bi' => (int) round( $x['bi'] / $x['n'] ),
			'bo' => (int) round( $x['bo'] / $x['n'] ),
			'ji' => (int) round( $x['ji'] / $x['n'] ),
			'jo' => (int) round( $x['jo'] / $x['n'] ),
			'n'  => $x['n'],
		);
	}
	// Geçmiş yoksa varsayılan: talimat + araç tanımları müşteri mesajı başına ~7000 giriş (araç turlarıyla), ~250 çıkış.
	return array(
		'by'      => (object) $by,
		'turn'    => $tt['n'] ? array( 'in' => (int) round( $tt['i'] / $tt['n'] ), 'out' => (int) round( $tt['o'] / $tt['n'] ) ) : array( 'in' => 7000, 'out' => 250 ),
		'judge'   => $jt['n'] ? array( 'in' => (int) round( $jt['i'] / $jt['n'] ), 'out' => (int) round( $jt['o'] / $jt['n'] ) ) : array( 'in' => 2500, 'out' => 200 ),
		'samples' => count( (array) $rows ),
		'history' => $tt['n'] > 0,
	);
}

function flxc_bench_recount( $run_id ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) n FROM ' . flxc_table( 'bench_results' ) . ' WHERE run_id = %d GROUP BY status', $run_id ), ARRAY_A ); // phpcs:ignore
	$c    = array( 'pass' => 0, 'fail' => 0, 'error' => 0 );
	foreach ( (array) $rows as $r ) {
		$c[ $r['status'] ] = (int) $r['n'];
	}
	flxc_bench_update_run( $run_id, array( 'passed' => $c['pass'], 'failed' => $c['fail'] + $c['error'] ) );
}

/** Kurala dayalı beklentiler. */
function flxc_bench_checks( array $sc, array $d ) {
	$e     = $sc['expect'];
	$state = (array) $d['state'];
	$tools = (array) $d['tools'];
	$bot   = array();
	foreach ( (array) $d['transcript'] as $m ) {
		if ( 'assistant' === $m['role'] && empty( $m['scripted'] ) ) {
			$bot[] = (string) $m['content'];
		}
	}
	$text = flxc_bot_lower( implode( "\n", $bot ) );
	$out  = array();
	$yn   = function ( $v ) {
		return $v ? __( 'evet', 'flx-chat' ) : __( 'hayır', 'flx-chat' );
	};
	if ( '' !== $e['handoff'] ) {
		$got   = ! empty( $d['handoff'] );
		$out[] = array( 'key' => 'handoff', 'pass' => ( 'yes' === $e['handoff'] ) === $got, 'detail' => sprintf( /* translators: 1: expected, 2: actual */ __( 'Beklenen: %1$s, olan: %2$s', 'flx-chat' ), $yn( 'yes' === $e['handoff'] ), $yn( $got ) ) );
	}
	if ( '' !== $e['donation'] ) {
		$got   = ! empty( $state['donation'] );
		$out[] = array( 'key' => 'donation', 'pass' => ( 'yes' === $e['donation'] ) === $got, 'detail' => sprintf( /* translators: 1: expected, 2: actual */ __( 'Beklenen: %1$s, olan: %2$s', 'flx-chat' ), $yn( 'yes' === $e['donation'] ), $yn( $got ) ) );
	}
	if ( '' !== $e['price_list'] ) {
		$got   = in_array( 'send_price_list', $tools, true );
		$out[] = array( 'key' => 'price_list', 'pass' => ( 'yes' === $e['price_list'] ) === $got, 'detail' => sprintf( /* translators: 1: expected, 2: actual */ __( 'Beklenen: %1$s, olan: %2$s', 'flx-chat' ), $yn( 'yes' === $e['price_list'] ), $yn( $got ) ) );
	}
	if ( $e['order_qty'] ) {
		$got   = (int) ( $state['order']['qty'] ?? 0 );
		$out[] = array( 'key' => 'order_qty', 'pass' => $got === (int) $e['order_qty'], 'detail' => sprintf( /* translators: 1: expected, 2: actual */ __( 'Beklenen: %1$s, olan: %2$s', 'flx-chat' ), (int) $e['order_qty'], $got ? $got : '–' ) );
	}
	foreach ( $e['contains'] as $w ) {
		$ok = false;
		foreach ( explode( '|', $w ) as $alt ) {
			$alt = flxc_bot_lower( trim( flxc_bench_fill( $alt ) ) );
			$ok  = $ok || ( '' !== $alt && false !== mb_strpos( $text, $alt ) );
		}
		$out[] = array( 'key' => 'contains', 'pass' => $ok, 'detail' => $w );
	}
	if ( ! empty( $e['tools'] ) || ! empty( $e['no_tools'] ) ) {
		// Bu sitede kapalı olan araç (ör. hatırlatıcı kapalı) beklenemez: kontrol geçti sayılır, not düşülür.
		$have = array();
		if ( '' !== (string) ( $sc['runner'] ?? '' ) ) {
			// Başka eklentinin senaryosu: araçlarını kendisi bilir.
			foreach ( array_merge( (array) ( $e['tools'] ?? array() ), (array) ( $e['no_tools'] ?? array() ) ) as $w ) {
				foreach ( array_filter( explode( '|', $w ) ) as $x ) {
					$have[ $x ] = true;
				}
			}
		}
		$defs = '' !== (string) ( $sc['runner'] ?? '' ) ? array() : array_merge( flxc_bot_tools(), function_exists( 'flxc_staff_tools_for' ) ? flxc_staff_tools_for( flxc_bot_sandbox_chat(), $state ) : array() );
		foreach ( $defs as $t ) {
			$have[ $t['function']['name'] ] = true;
		}
		foreach ( (array) ( $e['tools'] ?? array() ) as $w ) {
			$alts = array_filter( explode( '|', $w ) );
			$open = array_filter(
				$alts,
				function ( $x ) use ( $have ) {
					return isset( $have[ $x ] );
				}
			);
			if ( ! $open ) {
				$out[] = array( 'key' => 'tools', 'pass' => true, 'detail' => $w . ' · ' . __( 'bu sitede kapalı, bakılmadı', 'flx-chat' ) );
				continue;
			}
			$out[] = array( 'key' => 'tools', 'pass' => (bool) array_intersect( $alts, $tools ), 'detail' => $w );
		}
		foreach ( (array) ( $e['no_tools'] ?? array() ) as $w ) {
			$out[] = array( 'key' => 'no_tools', 'pass' => ! array_intersect( array_filter( explode( '|', $w ) ), $tools ), 'detail' => $w );
		}
	}
	if ( ! empty( $e['state'] ) ) {
		$out = array_merge( $out, flxc_bench_state_checks( (array) $e['state'], $d ) );
	}
	foreach ( $e['not_contains'] as $w ) {
		$alt   = flxc_bot_lower( trim( flxc_bench_fill( $w ) ) );
		$out[] = array( 'key' => 'not_contains', 'pass' => '' === $alt || false === mb_strpos( $text, $alt ), 'detail' => $w );
	}
	return $out;
}

/** 1.18.0: senaryo sonundaki test durumu (bağış, iptal, sertifika, doğrulama, hatırlatıcı, hafıza). */
function flxc_bench_state_has( $key, array $d ) {
	$st   = (array) $d['state'];
	$dons = (array) ( $st['tdons'] ?? array() );
	$ch   = (string) ( $st['vc_test']['channel'] ?? '' );
	switch ( $key ) {
		case 'don_new':
			// Örnek geçmiş bağışlar (flxc_bot_sandbox_seed) yeni bağış sayılmaz.
			return count(
				array_filter(
					$dons,
					function ( $x ) {
						return empty( $x['seed'] );
					}
				)
			) > (int) ( $d['seed'] ?? 0 );
		case 'don_canceled':
			return (bool) array_filter(
				$dons,
				function ( $x ) {
					return 'canceled' === ( $x['status'] ?? '' );
				}
			);
		case 'cert_sent':
			return ! empty( $st['cert_sent'] );
		case 'code_sent':
			return '' !== $ch || ! empty( $st['vc'] );
		case 'code_whatsapp':
		case 'code_sms':
		case 'code_email':
			return substr( $key, 5 ) === $ch;
		case 'verified':
			$seed = flxc_bench_setup_state( (array) ( $d['sc']['setup'] ?? array() ) );
			return count( (array) ( $st['phones_ok'] ?? array() ) ) + count( (array) ( $st['emails_ok'] ?? array() ) ) > count( (array) ( $seed['phones_ok'] ?? array() ) ) + count( (array) ( $seed['emails_ok'] ?? array() ) );
		case 'reminder':
			return ! empty( $st['rems'] ) || ! empty( $st['rem_draft'] );
		case 'memory':
			return ! empty( $st['mem'] );
	}
	return false;
}

function flxc_bench_state_checks( array $want, array $d ) {
	$keys = flxc_bench_state_keys();
	$have = array();
	$defs = array_merge( flxc_bot_tools(), function_exists( 'flxc_staff_tools_for' ) ? flxc_staff_tools_for( flxc_bot_sandbox_chat(), (array) $d['state'] ) : array() );
	foreach ( $defs as $t ) {
		$have[ $t['function']['name'] ] = true;
	}
	$out = array();
	foreach ( $want as $w ) {
		$neg = 0 === strpos( $w, '!' );
		$k   = ltrim( $w, '!' );
		if ( ! isset( $keys[ $k ] ) ) {
			continue;
		}
		$label = ( $neg ? __( 'Olmamalı', 'flx-chat' ) . ': ' : '' ) . $keys[ $k ][0];
		if ( ! array_intersect( $keys[ $k ][1], array_keys( $have ) ) ) {
			$out[] = array( 'key' => 'state', 'pass' => true, 'detail' => $label . ' · ' . __( 'bu sitede kapalı, bakılmadı', 'flx-chat' ) );
			continue;
		}
		$got   = flxc_bench_state_has( $k, $d );
		$out[] = array( 'key' => 'state', 'pass' => $neg ? ! $got : $got, 'detail' => $label );
	}
	return $out;
}

/** Hakem için araç çağrıları: ad, kısaltılmış argüman ve sonuç (gizli/uzun alanlar kısalır). */
function flxc_bench_judge_calls( array $calls ) {
	$out = array();
	foreach ( array_slice( $calls, 0, 8 ) as $c ) {
		$args = wp_json_encode( (object) (array) ( $c['args'] ?? array() ), JSON_UNESCAPED_UNICODE );
		$res  = (array) ( $c['result'] ?? array() );
		unset( $res['summary_html'] );
		$rs = wp_json_encode( $res, JSON_UNESCAPED_UNICODE );
		$out[] = '  [araç: ' . sanitize_key( (string) ( $c['tool'] ?? '' ) ) . '(' . mb_substr( (string) $args, 0, 160 ) . ') → ' . mb_substr( (string) $rs, 0, 360 ) . ']';
	}
	return implode( "\n", $out );
}

/** Araçların anlamı: hakem "kayıt açılmadı", "onaysız iptal" gibi yanlış sonuç çıkarmasın. */
function flxc_bench_judge_glossary() {
	return "Araçların anlamı:\n"
		. "- set_order: yalnız bağış seçimini kaydeder ve özet hazırlar, kayıt açmaz; kayıt create_donation ile açılır (confirmed=true kişi onay verdikten sonra).\n"
		. "- cancel_donation confirmed=false: iptal etmez, yalnız kişiye onay sorusu gönderir; confirmed=true kişi onayladıktan sonra iptal eder.\n"
		. "- save_details: ad/e-posta/telefon kaydeder; need_confirm dönerse asistan bilgiyi kişiye doğrulatmalıdır.\n"
		. "- verify_send kod gönderir, verify_check kodu doğrular ve kişinin kayıtlarını (records) döndürür; my_records kayıtları getirir.\n"
		. "- send_price_list, payment_link, create_donation, handoff, opt_out: metinleri sistem asistanın mesajından sonra ayrı mesaj olarak gönderir.\n"
		. "- receipt_check / dekont ön kontrolü: dekontu ekip onaylar; asistan ödemenin onaylandığını söylememelidir.\n"
		. "- reminder_set hatırlatıcı kurar; staff_* araçları yalnız personelde vardır (test bağışı, rapor, kendi açtığı bağışlar).\n";
}

/** Değerlendirici model: konuşmayı ölçüte göre geçti/kaldı diye puanlar. */
function flxc_bench_judge( array $sc, array $transcript, $model, $deadline = 0, $cache = '' ) {
	$lines = array();
	foreach ( $transcript as $m ) {
		if ( 'user' === $m['role'] ) {
			$lines[] = 'BAĞIŞÇI: ' . $m['content'];
		} elseif ( 'assistant' === $m['role'] && ! empty( $m['scripted'] ) ) {
			$lines[] = 'ASİSTAN (önceden yazılmış geçmiş, değerlendirme dışı): ' . $m['content'];
		} elseif ( 'assistant' === $m['role'] && ( '' !== (string) $m['content'] || ! empty( $m['calls'] ) ) ) {
			// 1.20.0: hakem araçların ne gönderip ne döndürdüğünü de görür (önceden yalnız adları; yanlış "kaldı" çıkıyordu).
			$calls = flxc_bench_judge_calls( (array) ( $m['calls'] ?? array() ) );
			// 1.22.0: fiyat listesi, ödeme / sertifika linki gibi sistemin kendi gönderdiği mesajlar ayrı etiketlenir
			// (benchmark 7'de hakem bunları asistan yazdı sanıp "link paylaştı" diye kaldırıyordu).
			$who     = empty( $m['auto'] ) ? 'ASİSTAN: ' : 'OTOMATİK SİSTEM MESAJI (asistan yazmadı, araç sonucu kendiliğinden gitti): ';
			$lines[] = $who . ( '' !== (string) $m['content'] ? $m['content'] : '(metin yok)' ) . ( $calls ? "\n" . $calls : ( ! empty( $m['tools'] ) ? "\n  [araçlar: " . $m['tools'] . ']' : '' ) );
		} elseif ( 'note' === $m['role'] ) {
			$lines[] = '[' . $m['content'] . ']';
		}
	}
	$sys = "Bir WhatsApp bağış asistanının konuşmasını değerlendiren tarafsız bir denetçisin. Asistanın mesajlarını verilen ölçüte göre değerlendir.\n"
		. "Yalnız değerlendirme dışı olarak işaretlenmeyen ASİSTAN mesajlarını puanla; önceden yazılmış geçmiş yalnız bağlamdır.\n"
		. "OTOMATİK SİSTEM MESAJI satırlarını (fiyat listesi, ödeme bilgileri, ödeme / sertifika linki, devir mesajı) asistan yazmadı: bunları asistanın sözü sayma, uydurma, link paylaşma, uzunluk ya da üslup hatası sayma. Asistanı yalnız kendi ASİSTAN satırlarına ve araç çağrılarına göre değerlendir.\n"
		. "Ölçüt \"ya da\" ile birkaç seçenek veriyorsa biri yeterlidir.\n"
		. "[araç: ad(argümanlar) → sonuç] satırları asistanın o mesajda çağırdığı araçlar ve sistemin döndürdüğü sonuçtur; asistanın söylediği bilgi bu sonuçlara dayanıyorsa doğrudur.\n"
		. flxc_bench_judge_glossary()
		. "Ölçütte istenen davranış görünüyorsa geç; ölçütte olmayan küçük üslup farkları için kaldırma.\n"
		. "Yalnız şu JSON ile cevap ver: {\"pass\": true|false, \"score\": 1-5, \"reason\": \"tek-iki cümle gerekçe, Türkçe\"}";
	$usr = 'Senaryo: ' . $sc['title'] . ( '' !== $sc['desc'] ? "\n" . $sc['desc'] : '' ) . "\n\nÖlçüt: " . $sc['expect']['judge'] . "\n\nKonuşma:\n" . implode( "\n", $lines );
	$all = array();
	// Boş cevap gelirse (düşünen model sınırı düşünmeye harcadı) bir kez daha geniş sınırla denenir.
	foreach ( array( 800, 2000 ) as $max ) {
		$left = $deadline ? $deadline - time() : 60;
		if ( $left < 8 ) {
			if ( $all ) {
				break;
			}
			return new WP_Error( 'flxc_bot_deadline', __( 'Yapay zekâ zamanında cevap vermedi.', 'flx-chat' ) );
		}
		$r = flxc_ai_chat(
			array(
				array( 'role' => 'system', 'content' => $sys ),
				array( 'role' => 'user', 'content' => $usr ),
			),
			array(
				'model'       => $model,
				'temperature' => 0,
				'max_tokens'  => $max,
				'timeout'     => min( 60, $left ),
				'cache'       => $cache,
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( isset( $r['_usage'] ) ) {
			$all[] = $r['_usage'];
		}
		if ( '' !== trim( (string) ( $r['content'] ?? '' ) ) ) {
			break;
		}
	}
	$usage = flxc_ai_usage_sum( $all, $model );
	// Değerlendiricinin tam isteği ve ham cevabı rapora girer.
	$log   = array( 'model' => $model, 'system' => $sys, 'user' => $usr, 'raw' => (string) ( $r['content'] ?? '' ), 'usage' => $usage );
	$json  = preg_match( '/\{.*\}/s', (string) ( $r['content'] ?? '' ), $m ) ? json_decode( $m[0], true ) : null;
	if ( ! is_array( $json ) || ! isset( $json['pass'] ) ) {
		return array( 'pass' => false, 'score' => 0, 'reason' => __( 'Değerlendirici anlaşılır cevap vermedi.', 'flx-chat' ), 'usage' => $usage, 'log' => $log );
	}
	return array(
		'pass'   => true === $json['pass'] || 'true' === $json['pass'],
		'score'  => max( 0, min( 5, (int) ( $json['score'] ?? 0 ) ) ),
		'reason' => mb_substr( sanitize_text_field( (string) ( $json['reason'] ?? '' ) ), 0, 500 ),
		'usage'  => $usage,
		'log'    => $log,
	);
}

/* ---------- AI ile senaryo yazma ---------- */

function flxc_bench_ai_write( $request, $current = null, $model = '' ) {
	$groups = array();
	foreach ( flxc_bench_groups() as $k => $v ) {
		$groups[] = $k . ' (' . $v . ')';
	}
	$sys = "Bir WhatsApp bağış asistanı için test senaryosu yazıyorsun. Senaryo, bağışçının sırayla göndereceği mesajlardan ve beklentilerden oluşur.\n"
		. "Asistanın araçları: " . implode( ', ', array_unique( array_merge( wp_list_pluck( wp_list_pluck( flxc_bot_tools(), 'function' ), 'name' ), array( 'staff_create_donation', 'staff_my_donations', 'staff_report' ) ) ) ) . ".\n"
		. "Senaryolar test sohbetindeki gibi izole oynar: kayıt açılmaz, mesaj gitmez; doğrulama kodu test panelinde kalır, müşteri mesajında {kod} yazarsan o kod yerine konur.\n"
		. "setup (başlangıç durumu, liste): " . implode( ', ', array_keys( flxc_bench_setups() ) ) . ". as: '' (bağışçı) ya da staff (personel yazar).\n"
		. "expect.tools / expect.no_tools: çağrılması / çağrılmaması gereken araçlar (a|b seçenekli). expect.state: sonda olması gerekenler, başında ! olursa olmaması gerekenler: " . implode( ', ', array_keys( flxc_bench_state_keys() ) ) . ".\n"
		. "Gruplar: " . implode( ', ', $groups ) . " (yeni grup adı da yazabilirsin).\n"
		. "turns her mesaj için {\"role\": \"user\"|\"assistant\", \"text\": \"...\"}. Yalnız user mesajlarında asistan her mesaja sırayla cevap verir. Araya assistant mesajı koyarsan bu bir devam senaryosudur: yazılan geçmiş hazır verilir, asistan yalnız en sondaki müşteri mesajına cevap verir (son mesaj user olmalı).\n"
		. "Mesajlarda {tur} (bağış türünün adı), {birim} (ör. fidan) ve {brand} kullanılabilir.\n"
		. "Beklentiler: handoff yes|no|'' (ekibe devretmeli mi), donation yes|no|'' (kayıt açılmalı mı), price_list yes|no|'' (fiyat listesi gönderilmeli mi), order_qty (beklenen adet, yoksa 0), contains (asistanın yazması gereken kelimeler), not_contains (yazmaması gerekenler), judge (değerlendirici modele kalite ölçütü, kısa).\n"
		. "Kesin kurallarla ölçülebileni yes/no ile ver, gerisini judge'a yaz. Mesajlar gerçekçi, kısa, günlük Türkçe olsun (istenirse başka dil).\n"
		. "Yalnız şu JSON ile cevap ver: {\"reply\": \"kısa açıklama\", \"scenario\": {\"group\": \"\", \"title\": \"\", \"desc\": \"\", \"turns\": [{\"role\": \"user\", \"text\": \"...\"}], \"expect\": {\"handoff\": \"\", \"donation\": \"\", \"price_list\": \"\", \"order_qty\": 0, \"contains\": [], \"not_contains\": [], \"tools\": [], \"no_tools\": [], \"state\": [], \"judge\": \"\"}, \"setup\": [], \"as\": \"\"}}";
	if ( $current ) {
		$sys .= "\n\nDüzenlenecek mevcut senaryo:\n" . wp_json_encode( $current, JSON_UNESCAPED_UNICODE );
	}
	$opts = array(
		'purpose'     => 'suggest',
		'temperature' => 0.4,
		'max_tokens'  => 1500,
		'timeout'     => 60,
	);
	if ( '' !== $model ) {
		$opts['model'] = $model;
	}
	$r = flxc_ai_chat(
		array(
			array( 'role' => 'system', 'content' => $sys ),
			array( 'role' => 'user', 'content' => mb_substr( (string) $request, 0, 2000 ) ),
		),
		$opts
	);
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$usage = flxc_ai_usage_sum( isset( $r['_usage'] ) ? array( $r['_usage'] ) : array(), '' !== $model ? $model : flxc_ai_model() );
	$json  = preg_match( '/\{.*\}/s', (string) ( $r['content'] ?? '' ), $m ) ? json_decode( $m[0], true ) : null;
	if ( ! is_array( $json ) || empty( $json['scenario'] ) || ! is_array( $json['scenario'] ) ) {
		return new WP_Error( 'flxc_bench_ai', __( 'Yapay zekâ senaryo üretemedi, isteği biraz daha açık yazıp tekrar deneyin.', 'flx-chat' ) );
	}
	$sc = $json['scenario'];
	if ( $current ) {
		$sc['id'] = $current['id'];
	} else {
		unset( $sc['id'] );
	}
	return array(
		'reply'    => mb_substr( sanitize_text_field( (string) ( $json['reply'] ?? '' ) ), 0, 500 ),
		'scenario' => flxc_bench_normalize( $sc ),
		'usage'    => $usage,
	);
}

/* ---------- Arayüz verisi ---------- */

function flxc_bench_run_for_js( $r ) {
	return array(
		'id'          => (int) $r['id'],
		'at'          => strtotime( $r['created_at'] . ' UTC' ) * 1000,
		'by'          => flxc_user_name( (int) $r['user_id'] ),
		'model'       => (string) $r['model'],
		'judge'       => (string) $r['judge_model'],
		'prompt'      => (string) $r['prompt_label'],
		'status'      => (string) $r['status'],
		'limit'       => (float) $r['limit_usd'],
		'cost'        => (float) $r['cost'],
		'in'          => (int) $r['tokens_in'],
		'out'         => (int) $r['tokens_out'],
		'total'       => (int) $r['total'],
		'passed'      => (int) $r['passed'],
		'failed'      => (int) $r['failed'],
		'beat'        => (int) ( $r['beat'] ?? 0 ) * 1000,
		'busy'        => (int) ( $r['locked_until'] ?? 0 ) > time(),
		'reason'      => 'paused' === $r['status'] ? (string) ( ( is_array( $r['meta'] ) ? $r['meta'] : flxc_decode( $r['meta'] ) )['reason'] ?? '' ) : '',
		'cache'       => flxc_bench_cache_info( $r ),
	);
}

/** Çalıştırmanın önbellek modu ve etkisi (1.19.0 öncesi çalıştırmalarda mode boş). */
function flxc_bench_cache_info( $r ) {
	$m  = is_array( $r['meta'] ) ? $r['meta'] : (array) flxc_decode( $r['meta'] );
	$cs = (array) ( $m['cs'] ?? array() );
	return array(
		'mode' => (string) ( $m['cache'] ?? '' ),
		'cr'   => (int) ( $cs['cr'] ?? 0 ),
		'cw'   => (int) ( $cs['cw'] ?? 0 ),
		'save' => round( (float) ( $cs['full'] ?? 0 ) - (float) ( $cs['cost'] ?? 0 ), 6 ),
	);
}

function flxc_bench_result_for_js( $r ) {
	$d = flxc_decode( $r['data'] );
	return array(
		'id'         => (int) $r['id'],
		'scenario'   => (string) $r['scenario_id'],
		'group'      => (string) $r['grp'],
		'title'      => (string) $r['title'],
		'status'     => (string) $r['status'],
		'hclass'     => (string) $r['hclass'],
		'cost'       => (float) $r['cost'],
		'in'         => (int) $r['tokens_in'],
		'out'        => (int) $r['tokens_out'],
		'ms'         => (int) $r['ms'],
		'turns'      => isset( $d['sc']['turns'] ) ? count( $d['sc']['turns'] ) : 0,
		'expect'     => $d['sc']['expect'] ?? null,
		// 1.18.0: test sohbetindeki gibi "Asistanın bildikleri" ve "Arka plan" (senaryonun izole durumu).
		'state'      => array_intersect_key( (array) ( $d['state'] ?? array() ), array_flip( array( 'name', 'full', 'email', 'order', 'donation', 'as_user' ) ) ),
		'bg'         => flxc_bot_sandbox_bg( (array) ( $d['state'] ?? array() ) ),
		// Araç ayrıntıları ve değerlendirici logu yalnız raporda (canlı izleme hafif kalsın).
		'transcript' => array_map(
			function ( $m ) {
				unset( $m['calls'] );
				return $m;
			},
			(array) ( $d['transcript'] ?? array() )
		),
		'checks'     => array_map(
			function ( $c ) {
				unset( $c['log'] );
				return $c;
			},
			(array) ( $d['checks'] ?? array() )
		),
	);
}

/**
 * İndirilebilir çalıştırma raporu: ayarlar, tam talimat, her senaryonun sohbeti, araç çağrıları (argüman ve sonuç),
 * kullanım, beklenti sonuçları ve değerlendirici modelin tam isteği ile ham cevabı.
 *
 * @param string $format md|json
 * @return array|WP_Error {filename, mime, content}
 */
function flxc_bench_report( $run_id, $format = 'md' ) {
	global $wpdb;
	$run = flxc_bench_run_row( $run_id );
	if ( ! $run ) {
		return new WP_Error( 'flxc_bench', __( 'Çalıştırma bulunamadı.', 'flx-chat' ), array( 'status' => 404 ) );
	}
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'bench_results' ) . " WHERE run_id = %d AND status <> 'skipped' ORDER BY id", $run_id ), ARRAY_A ); // phpcs:ignore
	$res  = array();
	foreach ( (array) $rows as $r ) {
		$d     = flxc_decode( $r['data'] );
		$res[] = array(
			'scenario'   => (string) $r['scenario_id'],
			'group'      => (string) $r['grp'],
			'title'      => (string) $r['title'],
			'status'     => (string) $r['status'],
			'handoff'    => (string) $r['hclass'],
			'cost'       => (float) $r['cost'],
			'tokens_in'  => (int) $r['tokens_in'],
			'tokens_out' => (int) $r['tokens_out'],
			'ms'         => (int) $r['ms'],
			'turns'      => (array) ( $d['sc']['turns'] ?? array() ),
			'desc'       => (string) ( $d['sc']['desc'] ?? '' ),
			'expect'     => $d['sc']['expect'] ?? null,
		// 1.18.0: test sohbetindeki gibi "Asistanın bildikleri" ve "Arka plan" (senaryonun izole durumu).
		'state'      => array_intersect_key( (array) ( $d['state'] ?? array() ), array_flip( array( 'name', 'full', 'email', 'order', 'donation', 'as_user' ) ) ),
		'bg'         => flxc_bot_sandbox_bg( (array) ( $d['state'] ?? array() ) ),
			'transcript' => (array) ( $d['transcript'] ?? array() ),
			'final'      => array( 'state' => $d['state'] ?? null, 'tools' => (array) ( $d['tools'] ?? array() ), 'handoff' => ! empty( $d['handoff'] ) ),
			'checks'     => (array) ( $d['checks'] ?? array() ),
		);
	}
	$info = flxc_bench_run_for_js( $run );
	unset( $info['beat'], $info['busy'] );
	$name = 'benchmark-' . (int) $run_id . '-' . gmdate( 'Ymd-His', strtotime( $run['created_at'] . ' UTC' ) );
	if ( 'json' === $format ) {
		$data = array(
			'plugin'    => 'flx-chat ' . FLXC_VERSION,
			'site'      => home_url( '/' ),
			'brand'     => flxc_brand_name(),
			'run'       => $info + array( 'prompt_text' => (string) $run['prompt_text'], 'usage_by_model' => $run['meta']['by'] ?? null, 'queue' => $run['meta']['queue'] ?? array() ),
			'groups'    => flxc_bench_groups(),
			'results'   => $res,
		);
		return array( 'filename' => $name . '.json', 'mime' => 'application/json', 'content' => wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
	}
	$j     = function ( $v ) {
		return wp_json_encode( $v, JSON_UNESCAPED_UNICODE );
	};
	$money = function ( $v ) {
		return '$' . number_format( (float) $v, 5 );
	};
	$fence = function ( $text ) {
		return "````text\n" . rtrim( (string) $text ) . "\n````\n";
	};
	$groups = flxc_bench_groups();
	$fin    = (int) $run['passed'] + (int) $run['failed'];
	$md     = '# ' . sprintf( /* translators: %d: run id */ __( 'Benchmark raporu #%d', 'flx-chat' ), (int) $run_id ) . "\n\n";
	$md    .= '- ' . __( 'Tarih', 'flx-chat' ) . ': ' . flxc_local_date( $run['created_at'] ) . "\n";
	$md    .= '- ' . __( 'Eklenti', 'flx-chat' ) . ': Flux Chat ' . FLXC_VERSION . ' · ' . flxc_brand_name() . "\n";
	$md    .= '- ' . __( 'Durum', 'flx-chat' ) . ': ' . $run['status'] . "\n";
	if ( ! empty( $run['meta']['reason'] ) && 'paused' === $run['status'] ) {
		$md .= '- ' . __( 'Durma nedeni', 'flx-chat' ) . ': ' . $run['meta']['reason'] . "\n";
	}
	$md    .= '- ' . __( 'Asistan modeli', 'flx-chat' ) . ': `' . $run['model'] . "`\n";
	$md    .= '- ' . __( 'Değerlendirici model', 'flx-chat' ) . ': `' . $run['judge_model'] . "`\n";
	$md    .= '- ' . __( 'Talimat', 'flx-chat' ) . ': ' . $run['prompt_label'] . "\n";
	$md    .= '- ' . __( 'Başarı', 'flx-chat' ) . ': ' . (int) $run['passed'] . ' / ' . $fin . ( $fin ? ' (' . round( 100 * (int) $run['passed'] / $fin ) . '%)' : '' ) . ' · ' . __( 'senaryo', 'flx-chat' ) . ': ' . (int) $run['total'] . "\n";
	$md    .= '- ' . __( 'Maliyet', 'flx-chat' ) . ': ' . $money( $run['cost'] ) . ' / ' . __( 'limit', 'flx-chat' ) . ' ' . $money( $run['limit_usd'] ) . ' · ' . __( 'Giriş / çıkış token', 'flx-chat' ) . ': ' . (int) $run['tokens_in'] . ' / ' . (int) $run['tokens_out'] . "\n";
	$ci = flxc_bench_cache_info( $run );
	if ( '' !== $ci['mode'] ) {
		$md .= '- ' . __( 'Claude önbelleği', 'flx-chat' ) . ': ' . flxc_ai_cache_label( $ci['mode'] ) . ' · ' . sprintf( /* translators: 1: read tokens, 2: written tokens */ __( 'okunan %1$d, yazılan %2$d token', 'flx-chat' ), $ci['cr'], $ci['cw'] ) . ' · ' . __( 'tasarruf', 'flx-chat' ) . ' ' . $money( $ci['save'] ) . "\n";
	}
	if ( ! empty( $run['meta']['by'] ) ) {
		$md .= "\n## " . __( 'Model kırılımı', 'flx-chat' ) . "\n\n| " . __( 'Model', 'flx-chat' ) . ' | ' . __( 'Rol', 'flx-chat' ) . ' | ' . __( 'İstek', 'flx-chat' ) . ' | ' . __( 'Giriş', 'flx-chat' ) . ' | ' . __( 'Çıkış', 'flx-chat' ) . ' | ' . __( 'Maliyet', 'flx-chat' ) . " |\n|---|---|---|---|---|---|\n";
		foreach ( $run['meta']['by'] as $m => $roles ) {
			foreach ( (array) $roles as $role => $u ) {
				$md .= '| `' . $m . '` | ' . ( 'judge' === $role ? __( 'Değerlendirici', 'flx-chat' ) : __( 'Asistan', 'flx-chat' ) ) . ' | ' . (int) $u['n'] . ' | ' . (int) $u['in'] . ' | ' . (int) $u['out'] . ' | ' . $money( $u['cost'] ) . " |\n";
			}
		}
	}
	// Grup ve devir özeti.
	$by = array();
	$hc = array( 'tp' => 0, 'tn' => 0, 'fp' => 0, 'fn' => 0 );
	foreach ( $res as $x ) {
		$g = $x['group'];
		$by[ $g ] = $by[ $g ] ?? array( 0, 0 );
		$by[ $g ][1]++;
		$by[ $g ][0] += 'pass' === $x['status'] ? 1 : 0;
		if ( isset( $hc[ $x['handoff'] ] ) ) {
			$hc[ $x['handoff'] ]++;
		}
	}
	$md .= "\n## " . __( 'Gruplar', 'flx-chat' ) . "\n\n";
	foreach ( $by as $g => $x ) {
		$md .= '- ' . ( $groups[ $g ] ?? $g ) . ': ' . $x[0] . ' / ' . $x[1] . "\n";
	}
	$md .= '- ' . __( 'Ekibe devir doğruluğu', 'flx-chat' ) . ': ' . sprintf( /* translators: 1-4: counts */ __( 'doğru devir %1$d, doğru devretmeme %2$d, gereksiz devir %3$d, kaçan devir %4$d', 'flx-chat' ), $hc['tp'], $hc['tn'], $hc['fp'], $hc['fn'] ) . "\n";
	$md .= "\n## " . __( 'Talimat (tam metin)', 'flx-chat' ) . "\n\n" . $fence( $run['prompt_text'] );
	$md .= "\n## " . __( 'Senaryolar', 'flx-chat' ) . "\n";
	foreach ( $res as $x ) {
		$mark = 'pass' === $x['status'] ? '✓' : ( 'running' === $x['status'] ? '…' : '✕' );
		$md  .= "\n### " . $mark . ' ' . $x['title'] . ' (' . ( $groups[ $x['group'] ] ?? $x['group'] ) . ', `' . $x['scenario'] . "`)\n\n";
		if ( '' !== $x['desc'] ) {
			$md .= $x['desc'] . "\n\n";
		}
		$md .= '- ' . __( 'Sonuç', 'flx-chat' ) . ': ' . $x['status'] . ( '' !== $x['handoff'] ? ' · ' . __( 'devir', 'flx-chat' ) . ': ' . $x['handoff'] : '' ) . ' · ' . $money( $x['cost'] ) . ' · ' . $x['tokens_in'] . '/' . $x['tokens_out'] . ' token · ' . round( $x['ms'] / 1000, 1 ) . " sn\n";
		$md .= '- ' . __( 'Beklentiler', 'flx-chat' ) . ': `' . $j( $x['expect'] ) . "`\n\n";
		$md .= '#### ' . __( 'Sohbet', 'flx-chat' ) . "\n\n";
		foreach ( $x['transcript'] as $m ) {
			$role = (string) ( $m['role'] ?? '' );
			$who  = 'user' === $role ? __( 'Müşteri', 'flx-chat' ) : ( 'assistant' === $role ? __( 'Asistan', 'flx-chat' ) : ( 'error' === $role ? __( 'Hata', 'flx-chat' ) : __( 'Not', 'flx-chat' ) ) );
			$md  .= '**' . $who . '**' . ( ! empty( $m['scripted'] ) ? ' _(' . __( 'yazılı geçmiş', 'flx-chat' ) . ')_' : '' ) . ( ! empty( $m['auto'] ) ? ' _(' . __( 'sistem mesajı', 'flx-chat' ) . ')_' : '' ) . ":\n\n" . ( '' !== (string) ( $m['content'] ?? '' ) ? '> ' . str_replace( "\n", "\n> ", (string) $m['content'] ) . "\n\n" : "> _(" . __( 'boş', 'flx-chat' ) . ")_\n\n" );
			foreach ( (array) ( $m['calls'] ?? array() ) as $c ) {
				$md .= '- ⚙ `' . $c['tool'] . '` ' . __( 'argüman', 'flx-chat' ) . ': `' . $j( $c['args'] ) . '` → ' . __( 'sonuç', 'flx-chat' ) . ': `' . $j( $c['result'] ) . "`\n";
			}
			if ( empty( $m['calls'] ) && ! empty( $m['tools'] ) ) {
				$md .= '- ⚙ ' . $m['tools'] . "\n";
			}
			if ( ! empty( $m['usage'] ) ) {
				$u   = $m['usage'];
				$md .= '- ' . __( 'Kullanım', 'flx-chat' ) . ': ' . (int) $u['in'] . '/' . (int) $u['out'] . ' token · ' . $money( $u['cost'] ) . ' · ' . (int) ( $u['calls'] ?? 1 ) . ' ' . __( 'istek', 'flx-chat' ) . ( ! empty( $u['model'] ) ? ' · `' . $u['model'] . '`' : '' ) . "\n";
			}
			if ( ! empty( $m['calls'] ) || ! empty( $m['usage'] ) || ! empty( $m['tools'] ) ) {
				$md .= "\n";
			}
		}
		$md .= '#### ' . __( 'Kontroller', 'flx-chat' ) . "\n\n";
		foreach ( $x['checks'] as $c ) {
			$md .= '- ' . ( $c['pass'] ? '✓' : '✕' ) . ' ' . $c['key'] . ( isset( $c['score'] ) ? ' (' . (int) $c['score'] . '/5)' : '' ) . ': ' . (string) ( $c['detail'] ?? '' ) . "\n";
		}
		$md .= '- ' . __( 'Son durum', 'flx-chat' ) . ': `' . $j( $x['final'] ) . "`\n";
		foreach ( $x['checks'] as $c ) {
			if ( empty( $c['log'] ) ) {
				continue;
			}
			$md .= "\n#### " . __( 'Değerlendirici logu', 'flx-chat' ) . ' (`' . $c['log']['model'] . "`)\n\n";
			$md .= __( 'Sistem mesajı', 'flx-chat' ) . ":\n\n" . $fence( $c['log']['system'] );
			$md .= "\n" . __( 'Gönderilen', 'flx-chat' ) . ":\n\n" . $fence( $c['log']['user'] );
			$md .= "\n" . __( 'Ham cevap', 'flx-chat' ) . ":\n\n" . $fence( $c['log']['raw'] );
		}
	}
	return array( 'filename' => $name . '.md', 'mime' => 'text/markdown', 'content' => $md );
}

function flxc_bench_runs_list( $limit = 30 ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'bench_runs' ) . ' ORDER BY id DESC LIMIT %d', $limit ), ARRAY_A ); // phpcs:ignore
	$out  = array();
	$ids  = array();
	foreach ( (array) $rows as $r ) {
		$out[ (int) $r['id'] ] = flxc_bench_run_for_js( $r ) + array( 'groups' => array() );
		$ids[]                 = (int) $r['id'];
	}
	if ( $ids ) {
		// Grup başına geçen/toplam: çalıştırmaları karşılaştırmak için.
		$g = $wpdb->get_results( 'SELECT run_id, grp, SUM(status = \'pass\') p, SUM(status IN (\'pass\',\'fail\',\'error\')) n FROM ' . flxc_table( 'bench_results' ) . ' WHERE run_id IN (' . implode( ',', $ids ) . ') GROUP BY run_id, grp', ARRAY_A ); // phpcs:ignore
		foreach ( (array) $g as $x ) {
			$out[ (int) $x['run_id'] ]['groups'][ $x['grp'] ] = array( (int) $x['p'], (int) $x['n'] );
		}
	}
	return array_values( $out );
}

/**
 * Tüm çalıştırmaların toplamı: harcama, token ve model/rol kırılımı.
 * 1.6.3 öncesi çalıştırmalarda kırılım yok; tamamı asistan modeline yazılır (rol "bot+judge").
 */
function flxc_bench_totals() {
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT model, judge_model, status, cost, tokens_in, tokens_out, passed, failed, meta FROM ' . flxc_table( 'bench_runs' ), ARRAY_A ); // phpcs:ignore
	$t    = array( 'runs' => 0, 'running' => 0, 'scenarios' => 0, 'passed' => 0, 'cost' => 0.0, 'in' => 0, 'out' => 0, 'models' => array() );
	$add  = function ( $model, $role, $in, $out, $cost, $n ) use ( &$t ) {
		$k = $model . '|' . $role;
		if ( ! isset( $t['models'][ $k ] ) ) {
			$t['models'][ $k ] = array( 'model' => $model, 'role' => $role, 'runs' => 0, 'calls' => 0, 'in' => 0, 'out' => 0, 'cost' => 0.0, 'passed' => 0, 'scenarios' => 0 );
		}
		$t['models'][ $k ]['in']    += (int) $in;
		$t['models'][ $k ]['out']   += (int) $out;
		$t['models'][ $k ]['cost']  += (float) $cost;
		$t['models'][ $k ]['calls'] += (int) $n;
		return $k;
	};
	foreach ( (array) $rows as $r ) {
		$t['runs']++;
		$t['running']   += 'running' === $r['status'] ? 1 : 0;
		$t['scenarios'] += (int) $r['passed'] + (int) $r['failed'];
		$t['passed']    += (int) $r['passed'];
		$t['cost']      += (float) $r['cost'];
		$t['in']        += (int) $r['tokens_in'];
		$t['out']       += (int) $r['tokens_out'];
		$meta = flxc_decode( $r['meta'] );
		$seen = array();
		if ( ! empty( $meta['by'] ) && is_array( $meta['by'] ) ) {
			foreach ( $meta['by'] as $model => $roles ) {
				foreach ( (array) $roles as $role => $u ) {
					$seen[] = $add( (string) $model, (string) $role, $u['in'] ?? 0, $u['out'] ?? 0, $u['cost'] ?? 0, $u['n'] ?? 0 );
				}
			}
		} elseif ( (float) $r['cost'] > 0 || (int) $r['tokens_in'] ) {
			$seen[] = $add( (string) $r['model'], 'mixed', $r['tokens_in'], $r['tokens_out'], $r['cost'], 0 );
		}
		// Başarı oranı asistan modeline yazılır.
		$bk = (string) $r['model'] . '|bot';
		if ( ! in_array( $bk, $seen, true ) ) {
			$bk = (string) $r['model'] . '|mixed';
			if ( ! in_array( $bk, $seen, true ) ) {
				$bk = $add( (string) $r['model'], 'bot', 0, 0, 0, 0 );
			}
		}
		$t['models'][ $bk ]['runs']++;
		$t['models'][ $bk ]['passed']    += (int) $r['passed'];
		$t['models'][ $bk ]['scenarios'] += (int) $r['passed'] + (int) $r['failed'];
		foreach ( array_unique( $seen ) as $k ) {
			if ( $k !== $bk ) {
				$t['models'][ $k ]['runs']++;
			}
		}
	}
	$t['models'] = array_values( $t['models'] );
	usort(
		$t['models'],
		function ( $a, $b ) {
			return $b['cost'] <=> $a['cost'];
		}
	);
	return $t;
}

/* ---------- Arka planda çalıştırma ---------- */

/*
 * Çalıştırma sunucuda kendi kendine ilerler: her adımın sonunda site kendine engellemeyen bir istek atar
 * (loopback), sıradaki adım o istekte çalışır. Sayfa kapalı olsa da sürer. Loopback düşerse
 * WP-Cron yedeği ve sayfa açıksa tarayıcı devralır.
 */
function flxc_bench_key() {
	$k = get_option( 'flxc_bench_key' );
	if ( ! $k ) {
		$k = wp_generate_password( 32, false, false );
		update_option( 'flxc_bench_key', $k, false );
	}
	return $k;
}

function flxc_bench_kick( $run_id ) {
	if ( ! flxc_loopback( 'flx-chat/v1/bot/bench/bg/' . (int) $run_id ) ) {
		// Ulaşmadı: "beat" eski kalır; panel açıksa tarayıcı, açık değilse sonraki panel isteği (flxc_bench_revive) devralır.
		flxc_bench_update_run( (int) $run_id, array( 'beat' => time() - 60 ) );
	}
	if ( ! wp_next_scheduled( 'flxc_bench_tick' ) ) {
		wp_schedule_single_event( time() + 120, 'flxc_bench_tick' );
	}
}

/** Bir adım çalıştırır ve bitmediyse sıradakini başlatır. */
function flxc_bench_step_and_continue( $run_id ) {
	$r = flxc_bench_step( $run_id );
	if ( ! is_wp_error( $r ) && empty( $r['done'] ) && empty( $r['busy'] ) ) {
		flxc_bench_kick( $run_id );
	}
	return $r;
}

/** Çalışıyor görünüp bir süredir ilerlemeyen çalıştırmaları yeniden başlatır. */
function flxc_bench_revive() {
	global $wpdb;
	$now = time();
	$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxc_table( 'bench_runs' ) . " WHERE status = 'running' AND locked_until < %d AND beat < %d", $now, $now - 20 ) ); // phpcs:ignore
	foreach ( (array) $ids as $id ) {
		// Dakikada en fazla bir kez dürt; beat yalnız gerçek adımda değişir (tarayıcı yedeği ona bakar).
		if ( get_transient( 'flxc_bench_kicked_' . $id ) ) {
			continue;
		}
		set_transient( 'flxc_bench_kicked_' . $id, 1, 20 );
		flxc_bench_kick( (int) $id );
	}
	return $ids;
}

add_action(
	'flxc_bench_tick',
	function () {
		global $wpdb;
		$ids = $wpdb->get_col( 'SELECT id FROM ' . flxc_table( 'bench_runs' ) . " WHERE status = 'running'" ); // phpcs:ignore
		if ( $ids ) {
			flxc_bench_revive();
			wp_schedule_single_event( time() + 120, 'flxc_bench_tick' );
		}
	}
);

/* ---------- REST ---------- */

function flxc_bench_need_ai() {
	return flxc_ai_ready() ? null : new WP_Error( 'flxc_ai', __( 'Önce Ayarlar › Yapay zekâ bölümünden anahtar ve model girin.', 'flx-chat' ), array( 'status' => 400 ) );
}

add_action(
	'rest_api_init',
	function () {
		$ns     = 'flx-chat/v1';
		$mng    = function () {
			return flxc_can_manage();
		};
		$routes = array(
			array(
				'/bot/bench',
				'GET',
				function ( WP_REST_Request $req ) {
					flxc_bench_revive();
					if ( $req->get_param( 'light' ) ) {
						return array( 'runs' => flxc_bench_runs_list(), 'month' => flxc_ai_month_usage(), 'totals' => flxc_bench_totals() );
					}
					return array(
						'scenarios' => flxc_bench_scenarios(),
						'groups'    => flxc_bench_groups(),
						'runs'      => flxc_bench_runs_list(),
						'totals'    => flxc_bench_totals(),
						'est'       => flxc_bench_estimate_data(),
						'models'    => array( 'bot' => flxc_ai_model( 'bot' ), 'judge' => flxc_ai_model() ),
						'fill'      => array( 'tur' => flxc_bench_fill( '{tur}' ), 'birim' => flxc_bench_fill( '{birim}' ), 'brand' => flxc_brand_name() ),
						'setups'    => flxc_bench_setups(),
						'stateKeys' => array_map(
							function ( $x ) {
								return $x[0];
							},
							flxc_bench_state_keys()
						),
					);
				},
			),
			array(
				'/bot/bench/scenarios',
				'POST',
				function ( WP_REST_Request $req ) {
					$s = flxc_bench_save_scenario( (array) $req->get_json_params() );
					if ( is_wp_error( $s ) ) {
						$s->add_data( array( 'status' => 400 ) );
						return $s;
					}
					return array( 'scenario' => $s, 'scenarios' => flxc_bench_scenarios() );
				},
			),
			array(
				'/bot/bench/scenarios/(?P<id>[a-z0-9\-]+)',
				'DELETE',
				function ( WP_REST_Request $req ) {
					flxc_bench_delete_scenario( (string) $req['id'] );
					return array( 'scenarios' => flxc_bench_scenarios() );
				},
			),
			array(
				'/bot/bench/scenarios/reset',
				'POST',
				function () {
					flxc_bench_reset_defaults();
					return array( 'scenarios' => flxc_bench_scenarios() );
				},
			),
			array(
				'/bot/bench/scenarios/ai',
				'POST',
				function ( WP_REST_Request $req ) {
					$e = flxc_bench_need_ai();
					if ( $e ) {
						return $e;
					}
					$cur = $req->get_param( 'current' );
					$r   = flxc_bench_ai_write( (string) $req->get_param( 'text' ), is_array( $cur ) ? flxc_bench_normalize( $cur ) : null, flxc_clean_model_id( $req->get_param( 'model' ) ) );
					if ( is_wp_error( $r ) ) {
						$r->add_data( array( 'status' => 502 ) );
						return $r;
					}
					$r['month'] = flxc_ai_month_usage();
					return $r;
				},
			),
			array(
				'/bot/bench/runs',
				'POST',
				function ( WP_REST_Request $req ) {
					$e = flxc_bench_need_ai();
					if ( $e ) {
						return $e;
					}
					$r = flxc_bench_create_run( (array) $req->get_json_params() );
					if ( is_wp_error( $r ) ) {
						$r->add_data( array( 'status' => 400 ) );
						return $r;
					}
					flxc_bench_update_run( $r['id'], array( 'beat' => time() ) );
					flxc_bench_kick( $r['id'] );
					return array( 'run' => flxc_bench_run_for_js( $r ) );
				},
			),
			array(
				'/bot/bench/runs/(?P<id>\d+)',
				'GET',
				function ( WP_REST_Request $req ) {
					global $wpdb;
					$run = flxc_bench_run_row( (int) $req['id'] );
					if ( ! $run ) {
						return new WP_Error( 'flxc_bench', __( 'Çalıştırma bulunamadı.', 'flx-chat' ), array( 'status' => 404 ) );
					}
					if ( 'running' === $run['status'] ) {
						flxc_bench_revive();
					}
					// since: canlı izlemede yalnız yeni ve süren sonuçlar.
					$since = (int) $req->get_param( 'since' );
					$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'bench_results' ) . " WHERE run_id = %d AND status <> 'skipped' AND ( id > %d OR status = 'running' ) ORDER BY id", $run['id'], $since ), ARRAY_A ); // phpcs:ignore
					return array(
						'run'     => flxc_bench_run_for_js( $run ) + array( 'promptText' => (string) $run['prompt_text'], 'queue' => (array) ( $run['meta']['queue'] ?? array() ) ),
						'results' => array_map( 'flxc_bench_result_for_js', (array) $rows ),
					);
				},
			),
			array(
				'/bot/bench/runs/(?P<id>\d+)/report',
				'GET',
				function ( WP_REST_Request $req ) {
					return flxc_bench_report( (int) $req['id'], 'json' === $req->get_param( 'format' ) ? 'json' : 'md' );
				},
			),
			array(
				'/bot/bench/runs/(?P<id>\d+)',
				'DELETE',
				function ( WP_REST_Request $req ) {
					global $wpdb;
					$wpdb->delete( flxc_table( 'bench_results' ), array( 'run_id' => (int) $req['id'] ) );
					$wpdb->delete( flxc_table( 'bench_runs' ), array( 'id' => (int) $req['id'] ) );
					return array( 'runs' => flxc_bench_runs_list() );
				},
			),
			array(
				'/bot/bench/runs/(?P<id>\d+)/step',
				'POST',
				function ( WP_REST_Request $req ) {
					$r = flxc_bench_step_and_continue( (int) $req['id'] );
					if ( is_wp_error( $r ) ) {
						$r->add_data( array( 'status' => 404 ) );
						return $r;
					}
					$r['month'] = flxc_ai_month_usage();
					return $r;
				},
			),
			array(
				'/bot/bench/runs/(?P<id>\d+)/state',
				'POST',
				function ( WP_REST_Request $req ) {
					$run = flxc_bench_run_row( (int) $req['id'] );
					if ( ! $run ) {
						return new WP_Error( 'flxc_bench', __( 'Çalıştırma bulunamadı.', 'flx-chat' ), array( 'status' => 404 ) );
					}
					$to = 'resume' === $req->get_param( 'action' ) ? 'running' : 'stopped';
					$f  = array( 'status' => $to );
					if ( 'running' === $to ) {
						$lim = (float) $req->get_param( 'limit' );
						if ( $lim > 0 ) {
							$f['limit_usd'] = min( 100, $lim );
						}
					}
					if ( 'done' !== $run['status'] ) {
						if ( 'running' === $to ) {
							$f['beat'] = time();
						}
						flxc_bench_update_run( $run['id'], $f );
						if ( 'running' === $to ) {
							flxc_bench_kick( $run['id'] );
						}
					}
					return array( 'run' => flxc_bench_run_for_js( flxc_bench_run_row( $run['id'] ) ) );
				},
			),
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
		// Sitenin kendine attığı arka plan isteği: oturum yok, gizli anahtarla.
		register_rest_route(
			$ns,
			'/bot/bench/bg/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'permission_callback' => function ( WP_REST_Request $req ) {
					return hash_equals( flxc_bench_key(), (string) $req->get_param( 'key' ) );
				},
				'callback'            => function ( WP_REST_Request $req ) {
					$early = flxc_detach_response();
					$r     = flxc_bench_step_and_continue( (int) $req['id'] );
					if ( $early ) {
						exit;
					}
					return array( 'ok' => ! is_wp_error( $r ) );
				},
			)
		);
	}
);
