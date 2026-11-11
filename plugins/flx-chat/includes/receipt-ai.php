<?php
/**
 * Dekont ön kontrolü: kişi fotoğraf ya da belge gönderince görsel okuyabilen model dekontu okur,
 * tutar, alıcı ve IBAN'ı sohbetin bağışıyla karşılaştırır. Sonuç kişiye söylenir, dosya bağış kaydına eklenir,
 * son onay her zaman ekiptedir (ödendi işaretlenmez).
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

function flxc_rcheck_on() {
	return (int) flxc_setting( 'bot_receipt_ai' ) && flxc_ai_ready();
}

/**
 * Dekont okuyan model. Seçili model (ya da asistan modeli) görsel okuyamıyorsa (1.9.1) görsel okuyan
 * ucuz bir modele geçilir; böylece "No endpoints found that support image input" hatası olmaz.
 */
function flxc_rcheck_model() {
	$m = trim( (string) flxc_setting( 'ai_model_vision' ) );
	$m = '' !== $m ? $m : flxc_ai_model( 'bot' );
	return false === flxc_rcheck_vision( $m ) ? flxc_rcheck_fallback_model( $m ) : $m;
}

/** true/false: model listesine göre görsel okuyor mu; null: bilinmiyor. */
function flxc_rcheck_vision( $model ) {
	$list = function_exists( 'flxc_ai_models' ) ? flxc_ai_models() : array();
	if ( ! is_array( $list ) || ! $list ) {
		return null;
	}
	foreach ( $list as $x ) {
		if ( $x['id'] === $model ) {
			return ! empty( $x['vision'] );
		}
	}
	return null;
}

/** Görsel okuyan yedek model: önce bilinen ucuz Google modelleri, yoksa listedeki en ucuz görsel model. */
function flxc_rcheck_fallback_model( $not = '' ) {
	$pref = apply_filters( 'flxc_receipt_fallback_models', array( 'google/gemini-2.5-flash-lite', 'google/gemini-2.0-flash-lite-001', 'google/gemini-2.0-flash-001', 'google/gemini-2.5-flash', 'openai/gpt-4o-mini' ) );
	$list = function_exists( 'flxc_ai_models' ) ? flxc_ai_models() : array();
	if ( ! is_array( $list ) || ! $list ) {
		return $pref[0] !== $not ? $pref[0] : ( $pref[1] ?? $pref[0] );
	}
	$vis = array();
	foreach ( $list as $x ) {
		if ( ! empty( $x['vision'] ) && $x['id'] !== $not ) {
			$vis[ $x['id'] ] = $x;
		}
	}
	foreach ( $pref as $p ) {
		if ( isset( $vis[ $p ] ) ) {
			return $p;
		}
	}
	// Ücretsiz (":free") modeller sık kapanır; ücretlilerin en ucuzu.
	$paid = array_filter(
		$vis,
		function ( $x ) {
			return $x['in'] > 0 && false === strpos( $x['id'], ':free' );
		}
	);
	uasort(
		$paid,
		function ( $a, $b ) {
			return ( $a['in'] + $a['out'] ) <=> ( $b['in'] + $b['out'] );
		}
	);
	return $paid ? (string) key( $paid ) : $pref[0];
}

/** Asistan bu turda henüz bakılmamış fotoğraf/belgeleri (son 30 dk) kontrol eder. @return array|null gönderilecek cevap */
function flxc_bot_receipt_turn( $chat ) {
	if ( ! flxc_rcheck_on() ) {
		return null;
	}
	$state   = flxc_bot_state( $chat );
	$pending = array();
	// 1.42.0: bağış bu sohbette açılmadıysa (siteden yapıldıysa) dekont kişinin kendi bekleyen bağışıyla karşılaştırılır.
	$own     = flxc_rcheck_own_donations( $chat, $state );
	$state['own_donations'] = array_keys( $own );
	if ( empty( $state['donation'] ) ) {
		$state['donation'] = flxc_rcheck_pick_donation( $own );
	}
	foreach ( flxc_recent_messages( $chat['id'], 1, 12, 30 ) as $m ) {
		if ( 'in' !== $m['direction'] || ! in_array( $m['type'], array( 'image', 'document' ), true ) ) {
			continue;
		}
		$meta = flxc_decode( $m['meta'] );
		if ( isset( $meta['rcheck'] ) || (int) $m['ts'] < ( time() - 30 * MINUTE_IN_SECONDS ) * 1000 ) {
			continue;
		}
		$pending[] = $m;
	}
	if ( ! $pending ) {
		return null;
	}
	$best = null;
	foreach ( array_slice( $pending, -2 ) as $m ) {
		$res  = flxc_receipt_ai_check( $m, $state );
		$kind = array( 'match' => 'rc_match', 'mismatch' => 'rc_bad', 'not_receipt' => 'rc_not' );
		flxc_bot_stat_add( array( 'rc' => 1 ) + ( isset( $kind[ $res['verdict'] ] ) ? array( $kind[ $res['verdict'] ] => 1 ) : array() ), isset( $res['u'] ) ? array( $res['u'] ) : array(), (int) $m['chat_id'] );
		unset( $res['u'] );
		$res = flxc_rcheck_rematch( $res, $own );
		if ( ! empty( $res['donation'] ) ) {
			$state['donation'] = (int) $res['donation'];
		}
		if ( 'not_receipt' !== $res['verdict'] ) {
			$res = flxc_rcheck_register( $res, $m, $state );
		}
		$meta = flxc_decode( $m['meta'] );
		// Bakılmayan eski dosyalar bir daha kontrol edilmesin.
		$meta['rcheck'] = $res;
		flxc_update_message( $m['id'], array( 'meta' => $meta ) );
		// Kişi aynı dekontu bu bağış için zaten yüklediyse (siteden ya da bu sohbetten) ikinci kez eklenmez.
		if ( 'not_receipt' !== $res['verdict'] && ! empty( $state['donation'] ) && empty( $res['dup']['same'] ) ) {
			flxc_attach_receipt( $m['id'], 'donation', (int) $state['donation'], false, __( 'AI pre-check', 'flx-chat' ) . ': ' . flxc_rcheck_summary( $res ) );
		}
		if ( ! $best || 'not_receipt' === $best['verdict'] ) {
			$best = $res + array( 'mid' => (int) $m['id'] );
		}
	}
	foreach ( array_slice( $pending, 0, -2 ) as $m ) {
		$meta           = flxc_decode( $m['meta'] );
		$meta['rcheck'] = array( 'verdict' => 'skipped' );
		flxc_update_message( $m['id'], array( 'meta' => $meta ) );
	}
	// Dekont değilse normal sohbet sürer: asistan dosyanın ne olduğunu notta görür, esprili bir dille fidana döner.
	if ( 'not_receipt' === $best['verdict'] ) {
		return null;
	}
	// 1.15.0: bilgiler uysa bile bağış yetkili onayına kalır. İleride yapay zekâ onayı bu filtreyle açılabilir.
	$auto = 'match' === $best['verdict'] && ! empty( $state['donation'] ) && apply_filters( 'flxc_receipt_auto_approve', false, $best, $chat, $state );
	if ( $auto ) {
		flxc_attach_receipt( (int) $best['mid'], 'donation', (int) $state['donation'], true, __( 'AI approval', 'flx-chat' ) );
		$best['approved'] = true;
	}
	flxc_system_message( $chat, __( 'Receipt pre-check (AI): ', 'flx-chat' ) . flxc_rcheck_summary( $best ) . ( $auto ? '' : ' · ' . __( 'Awaiting staff approval', 'flx-chat' ) ) );
	// 1.9.1: asistan kapanmaz (kişi sorularına cevap vermeye devam eder); son onay ekipte, ekibe haber gider.
	$did              = (int) ( $best['donation'] ?? 0 );
	$state            = flxc_bot_state( flxc_get_chat( $chat['id'] ) );
	$state['receipt'] = array( 'donation' => $did ? $did : (int) ( $state['donation'] ?? 0 ), 'verdict' => $best['verdict'], 'at' => time() );
	flxc_bot_save_state( $chat['id'], $state );
	return array(
		'texts'  => array( flxc_rcheck_reply( $best ) ),
		// Tutar farkını kişi düzeltebilir; sahte/tekrar dekont ve yanlış alıcı ekibe hemen haber verir.
		// Kişinin kendi dekontunu yeniden göndermesi (same) yeni bir iş değildir, ekip yeniden uyarılmaz.
		'notify' => ! ( 'mismatch' === $best['verdict'] && 'amount' === ( $best['why'] ?? '' ) ) && empty( $best['dup']['same'] ),
		'reason' => __( 'Receipt received, final check by the team', 'flx-chat' ),
	);
}

/** Dosyayı modele okutur ve beklenenle karşılaştırır. */
function flxc_receipt_ai_check( $m, array $state ) {
	$exp  = flxc_rcheck_expected( $state );
	$base = array(
		'verdict'  => 'unclear',
		'at'       => time(),
		'model'    => flxc_rcheck_model(),
		'expected' => $exp['amount'],
		'currency' => $exp['currency'],
		'donation' => $exp['id'],
	);
	$path = flxc_message_media_path( $m );
	if ( is_wp_error( $path ) || ! is_readable( $path ) || filesize( $path ) > 8 * MB_IN_BYTES ) {
		return $base + array( 'note' => __( 'File could not be read.', 'flx-chat' ) );
	}
	$m    = flxc_get_message( $m['id'] );
	$mime = strtolower( explode( ';', (string) $m['media_mime'] )[0] );
	$data = 'data:' . ( '' !== $mime ? $mime : 'application/octet-stream' ) . ';base64,' . base64_encode( (string) file_get_contents( $path ) ); // phpcs:ignore
	return flxc_receipt_ai_read( $base, $data, $mime, (string) $m['media_name'], (string) $m['body'], array( 'cache' => '' ) );
}

/**
 * Dosyayı (data URL) dekont olarak okur ve beklenenle karşılaştırır. Test sohbeti de kullanır (1.19.0).
 *
 * @param array $base flxc_receipt_ai_check'teki temel sonuç (beklenen tutar, bağış).
 */
function flxc_receipt_ai_read( array $base, $data, $mime, $name, $caption, array $extra = array() ) {
	$exp  = array( 'amount' => $base['expected'], 'id' => $base['donation'] );
	$mime = strtolower( (string) $mime );
	if ( 0 === strpos( $mime, 'image/' ) ) {
		$part = array( 'type' => 'image_url', 'image_url' => array( 'url' => $data ) );
	} elseif ( 'application/pdf' === $mime ) {
		$part = array( 'type' => 'file', 'file' => array( 'filename' => '' !== $name ? $name : 'dekont.pdf', 'file_data' => $data ) );
	} else {
		return array_merge( $base, array( 'verdict' => 'not_receipt', 'note' => __( 'Not a photo or PDF.', 'flx-chat' ) ) );
	}
	$sys = "Sana bir WhatsApp sohbetinden gelen dosya veriliyor. Bir banka havale/EFT dekontu ya da ödeme ekran görüntüsü olup olmadığını anla ve bilgileri oku. Tahmin etme: okuyamadığın alanı null bırak.\n"
		. 'Yalnız şu JSON ile cevap ver: {"is_receipt": true|false, "amount": sayı|null, "currency": "TRY", "date": "GG.AA.YYYY"|null, "recipient": "alıcı adı"|null, "iban": "görünen IBAN (maskeli olabilir)"|null, "payer": "gönderen"|null, "dekont_no": "dekont/işlem/referans numarası"|null, "description": "açıklama"|null, "what": "dekont değilse ne olduğu, kısa"}';
	$msgs = array(
		array( 'role' => 'system', 'content' => $sys ),
		array(
			'role'    => 'user',
			'content' => array(
				array( 'type' => 'text', 'text' => '' !== trim( (string) $caption ) ? 'Dosyanın açıklaması: ' . mb_substr( (string) $caption, 0, 300 ) : 'Dosya:' ),
				$part,
			),
		),
	);
	$opts = array(
		'temperature' => 0,
		'max_tokens'  => 700,
		'timeout'     => 40,
	);
	if ( 'file' === $part['type'] ) {
		// PDF her modelde okunsun: OpenRouter metni çıkarır (pdf-text ücretsiz; taranmış PDF'te model kendisi okur).
		$opts['plugins'] = array( array( 'id' => 'file-parser', 'pdf' => array( 'engine' => 'pdf-text' ) ) );
	}
	// Model görseli/PDF'i okuyamazsa (404 "image input", bozuk cevap) görsel okuyan yedek modelle bir kez daha.
	$model = flxc_rcheck_model();
	$j     = null;
	$err   = '';
	foreach ( array_unique( array( $model, flxc_rcheck_fallback_model( $model ) ) ) as $try ) {
		$base['model'] = $try;
		$r             = flxc_ai_chat( $msgs, $opts + array( 'model' => $try, 'cache' => (string) ( $extra['cache'] ?? '' ) ) );
		if ( is_wp_error( $r ) ) {
			$err  = $r->get_error_message();
			$http = (int) ( $r->get_error_data()['http'] ?? 0 );
			if ( in_array( $http, array( 401, 402, 403 ), true ) ) {
				break; // anahtar/bütçe: başka model de çalışmaz
			}
			continue;
		}
		if ( isset( $r['_usage'] ) ) {
			$base['cost'] = (float) ( $base['cost'] ?? 0 ) + (float) $r['_usage']['cost'];
			$base['u']    = $r['_usage'];
		}
		$j = preg_match( '/\{.*\}/s', (string) ( $r['content'] ?? '' ), $mm ) ? json_decode( $mm[0], true ) : null;
		if ( is_array( $j ) ) {
			break;
		}
		$err = __( 'The model could not read the file.', 'flx-chat' );
	}
	if ( ! is_array( $j ) ) {
		return $base + array( 'note' => $err );
	}
	$s   = function ( $k, $len = 120 ) use ( $j ) {
		return isset( $j[ $k ] ) && is_scalar( $j[ $k ] ) ? mb_substr( sanitize_text_field( (string) $j[ $k ] ), 0, $len ) : '';
	};
	$out = array_merge(
		$base,
		array(
			'amount'    => isset( $j['amount'] ) && is_numeric( str_replace( ',', '.', (string) $j['amount'] ) ) ? (float) str_replace( ',', '.', (string) $j['amount'] ) : null,
			'date'      => $s( 'date', 20 ),
			'recipient' => $s( 'recipient' ),
			'iban'      => $s( 'iban', 40 ),
			'payer'     => $s( 'payer' ),
			'dekont_no' => $s( 'dekont_no', 80 ),
			'desc'      => $s( 'description', 200 ),
			'what'      => $s( 'what', 120 ),
		)
	);
	if ( empty( $j['is_receipt'] ) || 'false' === $j['is_receipt'] ) {
		$out['verdict'] = 'not_receipt';
		return $out;
	}
	$bank           = flxc_bot_bank_account();
	$out['iban_ok'] = flxc_rcheck_iban_ok( $out['iban'], $bank['iban'] );
	$out['name_ok'] = flxc_rcheck_name_ok( $out['recipient'], $bank['holder'] );
	$out['amt_ok']  = null === $out['amount'] || ! $exp['amount'] ? null : abs( $out['amount'] - $exp['amount'] ) < 1;
	// 1.15.0: alıcı adı ya da görünen IBAN bizim değilse tek başına yeter, bağış olmasa da uyarılır.
	if ( false === $out['name_ok'] || false === $out['iban_ok'] ) {
		$out['verdict'] = 'mismatch';
		$out['why']     = 'recipient';
	} elseif ( ! $exp['id'] ) {
		$out['verdict'] = 'no_donation';
	} elseif ( false === $out['amt_ok'] ) {
		$out['verdict'] = 'mismatch';
		$out['why']     = 'amount';
	} elseif ( true === $out['amt_ok'] && false !== $out['iban_ok'] && false !== $out['name_ok'] ) {
		$out['verdict'] = 'match';
	}
	return $out;
}

/**
 * 1.42.0: kişinin kendi bağışları (yazdığı numara, kodla doğrulanmış numara/e-posta, bu sohbette açılan).
 * Canlıda görülen hata: kişi bağışı siteden yapıp dekontu oradan yükledi, sonra aynı dekontu WhatsApp'tan da gönderdi;
 * sohbette bağış kaydı olmadığı için dekont "başka kayıt için kullanılmış" sayıldı ve beklenen tutar boş kaldı.
 *
 * @return array[] id => { id, amount, currency, status, receipts }
 */
function flxc_rcheck_own_donations( $chat, array $state ) {
	$out = array();
	if ( ! function_exists( 'flxc_bot_own_records' ) || ! flxc_flxb_on() || empty( $chat['id'] ) ) {
		return $out;
	}
	$r = flxc_bot_own_records(
		$chat,
		function_exists( 'flxc_ev_emails' ) ? flxc_ev_emails( $state ) : array(),
		function_exists( 'flxc_verify_phones' ) ? flxc_verify_phones( $state ) : array(),
		$state
	);
	foreach ( (array) $r['donations'] as $d ) {
		$id = (int) ( $d['id'] ?? 0 );
		if ( $id ) {
			$out[ $id ] = array(
				'id'       => $id,
				'amount'   => (float) ( $d['amount'] ?? 0 ),
				'status'   => (string) ( $d['status'] ?? '' ),
				'receipts' => (int) ( $d['receipts'] ?? 0 ),
			);
		}
	}
	return $out;
}

/** Dekontun karşılaştırılacağı bağış: ödeme bekleyenlerden dekontu olmayan, yoksa en yenisi (0 = yok). */
function flxc_rcheck_pick_donation( array $own ) {
	$pend = array_filter(
		$own,
		function ( $d ) {
			return 'pending' === $d['status'];
		}
	);
	if ( ! $pend ) {
		return 0;
	}
	$bare = array_filter(
		$pend,
		function ( $d ) {
			return ! $d['receipts'];
		}
	);
	return (int) max( array_keys( $bare ? $bare : $pend ) );
}

/** Tutar seçilen bağışa uymuyor ama kişinin bekleyen başka bir bağışına uyuyorsa dekont ona aittir. */
function flxc_rcheck_rematch( array $res, array $own ) {
	if ( 'mismatch' !== ( $res['verdict'] ?? '' ) || 'amount' !== ( $res['why'] ?? '' ) || ! isset( $res['amount'] ) ) {
		return $res;
	}
	foreach ( $own as $d ) {
		if ( 'pending' === $d['status'] && (int) $d['id'] !== (int) ( $res['donation'] ?? 0 ) && abs( (float) $res['amount'] - $d['amount'] ) < 1 ) {
			$res['donation'] = (int) $d['id'];
			$res['expected'] = $d['amount'];
			$res['amt_ok']   = true;
			$res['verdict']  = 'match';
			unset( $res['why'] );
			break;
		}
	}
	return $res;
}

/**
 * Dekontu deftere yazar; daha önce yüklenmişse sonuç "duplicate" olur (yanlış alıcı uyarısı önce gelir).
 * Aynı kişinin kendi bağışı için yeniden gönderdiği dekont sahte sayılmaz (same=true): aynı sohbetten ya da
 * kişinin kendi bağışına (siteden) daha önce yüklenmiş olması yeter.
 */
function flxc_rcheck_register( array $res, array $m, array $state ) {
	$path = flxc_message_media_path( $m );
	$reg  = flxc_dekont_register(
		array(
			'payer'       => $res['payer'] ?? '',
			'dekont_no'   => $res['dekont_no'] ?? '',
			'amount'      => $res['amount'] ?? null,
			'currency'    => $res['currency'] ?? '',
			'date'        => $res['date'] ?? '',
			'recipient'   => $res['recipient'] ?? '',
			'iban'        => $res['iban'] ?? '',
			'source'      => 'whatsapp',
			'chat_id'     => (int) $m['chat_id'],
			'message_id'  => (int) $m['id'],
			'donation_id' => (int) ( $state['donation'] ?? 0 ),
			'verdict'     => $res['verdict'],
			'file'        => is_wp_error( $path ) ? '' : $path,
		)
	);
	$res['dekont_id'] = $reg['id'];
	if ( $reg['duplicates'] ) {
		$dup  = $reg['duplicates'][0];
		$same = true;
		$target = (int) ( $state['donation'] ?? 0 );
		$mine   = array_map( 'intval', (array) ( $state['own_donations'] ?? array() ) );
		foreach ( $reg['duplicates'] as $x ) {
			$did = (int) $x['donation_id'];
			// 1.42.0: önceki yükleme kişinin kendi bağışına yapıldıysa (siteden de olsa) ve dekont şimdi de o bağış içinse
			// aynı kişinin dekontudur. Kişinin BAŞKA bir bağışı için yeniden kullanılıyorsa uyarı sürer.
			$own_dup  = $did && ( $did === $target || ( ! $target && in_array( $did, $mine, true ) ) );
			$chat_dup = (int) $m['chat_id'] === (int) $x['chat_id'] && $target === $did;
			$same     = $same && ( $own_dup || $chat_dup );
		}
		$res['dup'] = array(
			'id'       => $dup['id'],
			'at'       => $dup['created_at'],
			'source'   => $dup['source'],
			'chat'     => $dup['chat_id'],
			'donation' => $dup['donation_id'],
			'same'     => $same,
		);
		if ( ! ( 'mismatch' === $res['verdict'] && 'recipient' === ( $res['why'] ?? '' ) ) ) {
			$res['verdict'] = 'duplicate';
		}
		flxc_dekont_update( $reg['id'], array( 'verdict' => $res['verdict'] ) );
	}
	return $res;
}

/** Sohbetin son bağış kaydı: beklenen tutar. */
function flxc_rcheck_expected( array $state ) {
	$out = array( 'id' => 0, 'amount' => 0.0, 'currency' => 'TRY' );
	if ( ! empty( $state['donation'] ) && flxc_flxb_on() ) {
		$d = FLXB_Donations::get( (int) $state['donation'] );
		if ( $d ) {
			$out = array( 'id' => (int) $d['id'], 'amount' => (float) $d['amount'], 'currency' => (string) $d['currency'] );
		}
	}
	return $out;
}

/** null = görünmüyor/bilinmiyor. Maskeli IBAN'da görünen son haneler karşılaştırılır. */
function flxc_rcheck_iban_ok( $seen, $ours ) {
	$ours = preg_replace( '/\D/', '', (string) $ours );
	$seen = preg_replace( '/[^0-9*]/', '', (string) $seen );
	if ( '' === $ours || ! preg_match( '/(\d{4,})$/', $seen, $m ) ) {
		return null;
	}
	return substr( $ours, -strlen( $m[1] ) ) === $m[1];
}

function flxc_rcheck_name_ok( $seen, $ours ) {
	$norm = function ( $x ) {
		return preg_split( '/[^\p{L}\p{N}]+/u', flxc_bot_lower( remove_accents( (string) $x ) ), -1, PREG_SPLIT_NO_EMPTY );
	};
	$a = array_filter( $norm( $ours ), function ( $w ) {
		return mb_strlen( $w ) >= 3;
	} );
	$b = $norm( $seen );
	if ( ! $a || ! $b ) {
		return null;
	}
	$hit = 0;
	foreach ( $a as $w ) {
		foreach ( $b as $v ) {
			if ( 0 === strpos( $v, $w ) || 0 === strpos( $w, $v ) && mb_strlen( $v ) >= 3 ) {
				$hit++;
				break;
			}
		}
	}
	return $hit / count( $a ) >= 0.5;
}

/** Ekip için tek satır özet. */
function flxc_rcheck_summary( array $r ) {
	$mark  = function ( $ok ) {
		return true === $ok ? ' ✓' : ( false === $ok ? ' ✕' : ' ?' );
	};
	$parts = array( flxc_rcheck_labels()[ $r['verdict'] ] ?? $r['verdict'] );
	if ( 'not_receipt' === $r['verdict'] ) {
		if ( ! empty( $r['what'] ) ) {
			$parts[] = $r['what'];
		}
	} else {
		if ( isset( $r['amount'] ) ) {
			/* translators: 1: amount on receipt, 2: expected amount */
			$parts[] = sprintf( __( 'Amount %1$s (expected %2$s)', 'flx-chat' ), isset( $r['amount'] ) ? flxc_money( $r['amount'], $r['currency'] ?? 'TRY' ) : '?', ! empty( $r['expected'] ) ? flxc_money( $r['expected'], $r['currency'] ?? 'TRY' ) : '-' ) . $mark( $r['amt_ok'] ?? null );
		}
		if ( ! empty( $r['recipient'] ) ) {
			$parts[] = __( 'Recipient', 'flx-chat' ) . ': ' . $r['recipient'] . $mark( $r['name_ok'] ?? null );
		}
		if ( ! empty( $r['iban'] ) ) {
			$parts[] = 'IBAN ' . $r['iban'] . $mark( $r['iban_ok'] ?? null );
		}
		if ( ! empty( $r['date'] ) ) {
			$parts[] = $r['date'];
		}
		if ( ! empty( $r['payer'] ) ) {
			$parts[] = __( 'Sender', 'flx-chat' ) . ': ' . $r['payer'];
		}
		if ( ! empty( $r['dekont_no'] ) ) {
			$parts[] = __( 'Receipt no', 'flx-chat' ) . ': ' . $r['dekont_no'];
		}
		if ( ! empty( $r['dup'] ) ) {
			$parts[] = ( empty( $r['dup']['same'] ) ? '⚠ ' : 'ℹ ' ) . __( 'This receipt was', 'flx-chat' ) . ' ' . flxc_dekont_dup_text( array( 'created_at' => $r['dup']['at'], 'source' => $r['dup']['source'] ) ) . ( ! empty( $r['dup']['donation'] ) ? ' (' . __( 'donation', 'flx-chat' ) . ' #' . (int) $r['dup']['donation'] . ')' : '' ) . ( empty( $r['dup']['same'] ) ? '' : ' · ' . __( 'same person and donation, not a copy', 'flx-chat' ) );
		}
	}
	if ( ! empty( $r['note'] ) ) {
		$parts[] = $r['note'];
	}
	return implode( ' · ', $parts );
}

function flxc_rcheck_labels() {
	return array(
		'match'       => __( 'Matches the donation', 'flx-chat' ),
		'mismatch'    => __( 'Does not match the donation', 'flx-chat' ),
		'duplicate'   => __( 'Previously uploaded receipt', 'flx-chat' ),
		'no_donation' => __( 'Receipt, no donation to match', 'flx-chat' ),
		'not_receipt' => __( 'Not a receipt', 'flx-chat' ),
		'unclear'     => __( 'Could not read', 'flx-chat' ),
		'skipped'     => __( 'Not checked', 'flx-chat' ),
	);
}

/** Kişiye giden cevap. */
function flxc_rcheck_reply( array $r ) {
	$cur    = $r['currency'] ?? 'TRY';
	$thanks = __( 'We received your receipt, thank you 🌱 We\'ll get back to you about your payment as soon as possible.', 'flx-chat' );
	switch ( $r['verdict'] ) {
		case 'match':
			if ( ! empty( $r['approved'] ) ) {
				return __( 'We received your receipt, thank you 🌱 Your donation is confirmed.', 'flx-chat' );
			}
			return $thanks;
		case 'duplicate':
			if ( ! empty( $r['dup']['same'] ) ) {
				return __( 'We had already received this receipt, thank you 🌱 We\'ll get back to you about your payment as soon as possible.', 'flx-chat' );
			}
			return __( 'This receipt seems to have been uploaded to our system before. The same receipt cannot be used for a second donation. If you made a different payment, please send that receipt; our team will also check.', 'flx-chat' );
		case 'mismatch':
			if ( 'amount' === ( $r['why'] ?? '' ) ) {
				/* translators: 1: amount on receipt, 2: expected amount */
				return sprintf( __( 'We received your receipt. The amount on it (%1$s) does not seem to match your donation amount (%2$s). Could you check it? If you made a different payment, you can write here; our team will also take a look.', 'flx-chat' ), flxc_money( $r['amount'], $cur ), flxc_money( $r['expected'], $cur ) );
			}
			$bank = flxc_bot_bank_account();
			if ( '' !== (string) $bank['holder'] ) {
				/* translators: %s: account holder name */
				return sprintf( __( 'We received your receipt, but the recipient on it does not seem to be our association. Donations are only made to our account in the name of "%s". Could you check? Our team will also take a look.', 'flx-chat' ), $bank['holder'] );
			}
			return __( 'We received your receipt, but the recipient on it does not seem to match our account. Could you check? Our team will also take a look.', 'flx-chat' );
		case 'not_receipt':
			return __( 'The file you sent does not look like a payment receipt. If you have paid, you can send the receipt or a screenshot of the payment screen here.', 'flx-chat' );
	}
	return $thanks;
}
