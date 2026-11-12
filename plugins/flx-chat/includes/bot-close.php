<?php
/**
 * 1.42.0 (Ahmet): sohbetin "tamamlandı" durumu ve emoji tepkileri.
 *
 * Sorun: kişi "teşekkürler", "iyi günler", "her zaman 💐" yazdıkça asistan her birine yeni bir veda mesajı
 * yazıyordu. Çözüm yumuşak bir kapanış:
 *  - Kişi vedalaşınca asistan tek kısa veda cümlesi yazar ve close_chat ile sohbeti tamamlandı işaretler
 *    (asistan durumunda `closed` = zaman; arka plan değişkeni, sohbet kapanmaz, panelde bir sistem satırı düşer).
 *  - Sohbet tamamlandıyken gelen yalnız teşekkür / veda / dua / emoji mesajına metin yazılmaz; mesaja tepki
 *    (👍, dua ve teşekkürde 🤲) bırakılır. Bu karar model çağrılmadan kelime listesiyle verilir (hızlı, ücretsiz);
 *    arka arkaya en çok iki tepki, sonrası sessiz.
 *  - Kişi yeni bir şey yazarsa (soru, istek, listede olmayan herhangi bir kelime) asistan normal cevap verir ve
 *    `closed` silinir. Sıkı kapatma yok.
 *
 * Tepki ayrıca kapanış dışında da kullanılabilir (react aracı): "tamam" anlamında 👍, duaya 🤲.
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

/** Asistan mesajlara emoji tepkisi bırakabilir mi (ayar). */
function flxc_bot_react_on() {
	return (bool) flxc_setting( 'bot_reactions' );
}

/**
 * Asistanın kullanabileceği tepkiler. 1.43.2 (Ahmet): yalnız 👍 ve 🤲; yasaklı emojiler filtreyle de eklenemez.
 */
function flxc_bot_react_emojis() {
	$list = (array) apply_filters( 'flxc_bot_react_emojis', array( '👍', '🤲' ) );
	return array_values( array_diff( array_map( 'strval', $list ), flxc_bot_banned_emojis() ) );
}

/**
 * 1.43.2 (Ahmet): asistanın hiç kullanmayacağı emojiler (tepki de metin de). 🙏 (birleşik eller) her zaman listededir;
 * filtre yalnız ekleyebilir.
 */
function flxc_bot_banned_emojis() {
	return array_values( array_unique( array_merge( array( '🙏' ), (array) apply_filters( 'flxc_bot_banned_emojis', array() ) ) ) );
}

/**
 * Asistanın metninden yasaklı emojileri atar (ten rengi ve varyasyon seçicisiyle birlikte). 🙏 dua anlamında
 * kullanılmışsa yerine 🤲 konur, metin anlamsız kalmasın.
 */
function flxc_bot_strip_banned( $text ) {
	$text = (string) $text;
	foreach ( flxc_bot_banned_emojis() as $e ) {
		if ( '' === $e || false === strpos( $text, $e ) ) {
			continue;
		}
		$to   = '🙏' === $e ? '🤲' : '';
		$text = (string) preg_replace( '/(?:' . preg_quote( $e, '/' ) . '[\x{1F3FB}-\x{1F3FF}]?\x{FE0F}?)+/u', $to, $text );
	}
	$text = (string) preg_replace( '/(🤲)(?:\s*🤲)+/u', '$1', $text );
	$text = (string) preg_replace( '/[ \t]{2,}/u', ' ', $text );
	return preg_replace( '/[ \t]+(\R|$)/u', '$1', $text );
}

/** Tamamlandı işareti bu kadar süre geçerli; sonrasında gelen teşekkür normal karşılanır. */
function flxc_bot_closed_ttl() {
	return (int) apply_filters( 'flxc_bot_closed_ttl', 12 * HOUR_IN_SECONDS );
}

function flxc_bot_is_closed( array $state ) {
	return ! empty( $state['closed'] ) && time() - (int) $state['closed'] < flxc_bot_closed_ttl();
}

/**
 * Mesaj yalnız teşekkür / veda / dua / onay mı? Soru işareti, sayı ya da listede olmayan tek bir kelime varsa değildir
 * (karar modele kalır).
 *
 * @return string '' değil · 'dua' teşekkür ya da dua (🤲) · 'ok' tamam / iyi günler / yalnız emoji (👍)
 */
function flxc_bot_closer_kind( $text ) {
	$t = flxc_bot_lower( trim( (string) $text ) );
	if ( '' === $t || false !== strpos( $t, '?' ) || mb_strlen( $t ) > 80 ) {
		return '';
	}
	$words = preg_split( '/\s+/u', trim( (string) preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $t ) ), -1, PREG_SPLIT_NO_EMPTY );
	if ( ! $words ) {
		// Yalnız emoji / işaret (👍, 💐, 🙏🙏).
		return mb_strlen( $t ) <= 16 ? 'ok' : '';
	}
	if ( count( $words ) > 8 ) {
		return '';
	}
	$sets = array(
		'dua'    => '/^(te[sş]ekk[uü]r\p{L}*|sa[gğ]ol\p{L}*|sa[gğ]|ol|olun|olas[ıi]n\p{L}*|saol\p{L}*|eyvallah|eyv|t[sş]k\p{L}*|thanks|thank|thx|merci|allah|raz[ıi]|olsun|amin|[iı]n[sş]allah|in[sş]aallah|ma[sş]allah|hay[ıi]rl[ıi]\p{L}*|bereket\p{L}*|dua\p{L}*|ecrini|versin|kabul|eylesin|etsin|rabbim|eksik|olmay[ıi]n|var)$/u',
		'ok'     => '/^(tamam\p{L}*|tmm|ok|okey|okay|oki|peki|olur|anlad[ıi]m|anla[sş][ıi]ld[ıi]|s[uü]per|harika|g[uü]zel|iyi|g[uü]nler|ak[sş]amlar|geceler|[cç]al[ıi][sş]malar|kolay|gelsin|g[oö]r[uü][sş][uü]r[uü]z|g[oö]r[uü][sş]mek|[uü]zere|ho[sş][cç]a|ho[sş][cç]akal\p{L}*|kal|kal[ıi]n|selametle|bye|goodbye|zaman|herzaman|rica|esta[gğ]furullah)$/u',
		'filler' => '/^([cç]ok|ben|bende|biz|bizde|de|da|size|sizlere|sana|siz|sizin|ederim|ederiz|ediyorum|ediyoruz|efendim|hocam|abi|abla|karde[sş]im|her|as[ıi]l|you|very|much|so|good|day|night|have|a|nice|emek\p{L}*|elleriniz\p{L}*|sa[gğ]l[ıi]k)$/u',
	);
	$kind = '';
	foreach ( $words as $w ) {
		$hit = '';
		foreach ( $sets as $k => $re ) {
			if ( preg_match( $re, $w ) ) {
				$hit = $k;
				break;
			}
		}
		if ( '' === $hit ) {
			return '';
		}
		if ( 'dua' === $hit ) {
			$kind = 'dua';
		} elseif ( 'ok' === $hit && '' === $kind ) {
			$kind = 'ok';
		}
	}
	return $kind;
}

/** Kapanış türüne uyan tepki. */
function flxc_bot_closer_emoji( $kind ) {
	return (string) apply_filters( 'flxc_bot_closer_emoji', 'dua' === $kind ? '🤲' : '👍', $kind );
}

/**
 * Kişinin son mesajına tepki bırakır.
 *
 * @return bool gönderildi mi
 */
function flxc_bot_react_send( $chat, $emoji ) {
	global $wpdb;
	if ( ! flxc_bot_react_on() || empty( $chat['id'] ) || ! in_array( $emoji, flxc_bot_react_emojis(), true ) ) {
		return false;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$m = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'messages' ) . " WHERE chat_id = %d AND direction = 'in' AND wa_id <> '' ORDER BY id DESC LIMIT 1", (int) $chat['id'] ), ARRAY_A );
	if ( ! $m || 'deleted' === $m['type'] ) {
		return false;
	}
	$r = flxc_evo_send_reaction( $chat['conn'], $chat['jid'], false, $m['wa_id'], $emoji );
	if ( empty( $r['ok'] ) ) {
		return false;
	}
	flxc_apply_reaction( $chat['conn'], $m['wa_id'], 'out', $emoji );
	flxc_update_chat( (int) $chat['id'], array() ); // panel satırı yenilensin
	return true;
}

/* ---------- Asistan araçları ---------- */

function flxc_bot_close_tools( $f ) {
	$emoji = array( 'type' => 'string', 'enum' => flxc_bot_react_emojis(), 'description' => 'Yalnız iki tepki var: 👍 tamam / anlaşıldı / görüşürüz · 🤲 dua ve teşekkür (Allah razı olsun, inşallah, çok teşekkürler). 🙏 YOK, asla kullanma.' );
	$list  = array(
		$f(
			'close_chat',
			'Sohbeti tamamlandı olarak işaretler (arka plan durumu; sohbet kapanmaz, kişi yeniden yazarsa normal devam edilir). Kişi yalnız teşekkür ediyor, vedalaşıyor ya da dua ediyorsa ve cevaplanacak yeni bir soru ya da istek yoksa çağır. İlk vedada ayrıca tek kısa veda cümlesi yaz. Sohbet zaten tamamlandıysa ya da vedayı az önce yazdıysan silent=true ver: metin gitmez, yalnız tepki bırakılır.',
			array(
				'silent' => array( 'type' => 'boolean', 'description' => 'true: metin gönderme, yalnız tepki (veda zaten yazıldı, kişi yine teşekkür/veda/dua/emoji yazdı)' ),
			) + ( flxc_bot_react_on() ? array( 'reaction' => $emoji ) : array() )
		),
	);
	if ( flxc_bot_react_on() ) {
		$list[] = $f(
			'react',
			'Kişinin son mesajına WhatsApp emoji tepkisi bırakır. Kısa bir onaya ("tamam", "anlaştık") 👍, duaya ya da teşekküre 🤲 gibi; cevabın yerine geçmez, soru ya da istek varsa cevabı da yaz. Her mesaja tepki bırakma.',
			array( 'emoji' => $emoji ),
			array( 'emoji' )
		);
	}
	return $list;
}

function flxc_bot_close_tool( $name, array $a, $chat, array &$state, array &$ctx, $sandbox ) {
	$emoji = (string) ( $a['react' === $name ? 'emoji' : 'reaction'] ?? '' );
	$emoji = in_array( $emoji, flxc_bot_react_emojis(), true ) && flxc_bot_react_on() ? $emoji : '';
	$sent  = false;
	if ( '' !== $emoji ) {
		if ( $sandbox ) {
			$state['reacts']   = array_slice( (array) ( $state['reacts'] ?? array() ), -9 );
			$state['reacts'][] = $emoji;
			$sent              = true;
		} else {
			$sent = flxc_bot_react_send( $chat, $emoji );
		}
	}
	if ( $sent ) {
		$ctx['quiet_ok'] = true; // Tepki gitti: model metin yazmazsa bekleme mesajı gönderilmez.
	}
	if ( 'react' === $name ) {
		if ( '' === $emoji ) {
			return array( 'ok' => false, 'error' => 'Tepki kapalı ya da emoji listede yok; tepki bırakmadan devam et.' );
		}
		return $sent
			? array( 'ok' => true, 'note' => 'Tepki bırakıldı. Yazılacak bir cevap varsa şimdi yaz; yoksa çok kısa tut.' )
			: array( 'ok' => false, 'error' => 'Tepki gönderilemedi; kısa bir metinle karşılık ver.' );
	}
	$was             = flxc_bot_is_closed( $state );
	$silent          = ! empty( $a['silent'] ) || $was;
	$state['closed'] = time();
	if ( ! $was ) {
		$state['closed_n'] = 0;
		if ( ! $sandbox ) {
			flxc_system_message( $chat, __( 'The assistant marked the chat as completed (the person said goodbye). It continues normally if they write again.', 'flx-chat' ) );
		}
	}
	$ctx['closed'] = true;
	if ( $was ) {
		$ctx['quiet_ok'] = true;
	}
	// Sessiz kapanış yalnız tepki gittiyse sessizdir; tepki gidemediyse kişi cevapsız kalmasın, kısa veda yazılır.
	if ( $silent && ( $sent || $was ) ) {
		$state['closed_n'] = (int) ( $state['closed_n'] ?? 0 ) + 1;
		$ctx['stop']       = true;
		$ctx['mute']       = true;
		return array( 'ok' => true );
	}
	return array( 'ok' => true, 'note' => 'Sohbet tamamlandı işaretlendi' . ( $sent ? ', tepki bırakıldı' : '' ) . '. Şimdi TEK kısa veda cümlesi yaz: soru sorma, yeni teklif ekleme, "başka bir konuda…" kalıbını ve bağış teşekkürünü tekrarlama.' );
}

/** Sistem talimatına: kapanış ve tepki kuralları (sabit kısım). */
function flxc_bot_close_rule() {
	$r = array(
		'## Kapanış' . ( flxc_bot_react_on() ? ' ve tepki' : '' ),
		'- Kişi yalnız teşekkür ediyor, vedalaşıyor ("iyi günler", "kolay gelsin", "hayırlı akşamlar") ya da dua ediyorsa ve cevaplanacak yeni bir soru ya da istek yoksa: tek kısa veda cümlesi yaz ve close_chat çağır. Vedada soru sorma, yeni teklif ekleme; "başka bir konuda yardımcı olabileceğimiz olursa yazabilirsiniz" gibi kalıpları ve bağış teşekkürünü her mesajda tekrarlama.',
		'- Bilinenler\'de "Sohbet durumu: tamamlandı" yazıyorsa ya da vedayı bir önceki mesajında zaten yazdıysan ve kişi yine yalnız teşekkür / veda / dua / emoji yazdıysa ("ben de teşekkür ederim", "iyi günler", "her zaman 💐") metin YAZMA: close_chat(silent=true) çağır. Vedalaşma karşılıklı uzamaz.',
		'- Tamamlanmış sohbette kişi yeni bir soru sorar, bir istekte bulunur ya da yeni bir konu açarsa ("önümüzdeki ay babam için de yapacağım") hiçbir şey olmamış gibi normal cevap ver; close_chat çağırma.',
		'- Hitabı (ad + Bey/Hanım) her mesajda tekrarlama; konuşmanın başında ve arada bir yeter.',
	);
	if ( flxc_bot_react_on() ) {
		$r[] = '- Tepki: kapanışta close_chat\'e reaction ver; "tamam / anlaştık / görüşürüz" gibi kısa onaya 👍, duaya ve teşekküre ("Allah razı olsun", "inşallah", "çok teşekkürler") 🤲. Başka tepki yok. Kapanış dışında react aracıyla da tepki bırakabilirsin; tepki cevabın yerine geçmez ve her mesaja bırakılmaz.';
	}
	$r[] = '- 🙏 (birleşik eller) emojisini ne tepkide ne metinde ASLA kullanma; dua ve teşekkürde 🤲 kullan.';
	return implode( "\n", $r );
}

/* ---------- Model çağırmadan: tamamlanmış sohbette gelen teşekkür / veda ---------- */

/**
 * Verilen mesajların hepsi kapanış sözü mü ve asistanın son mesajı bir soru değil mi?
 *
 * @param string[] $texts    Kişinin son mesajları (eski → yeni).
 * @param string   $last_out Asistanın / ekibin son mesajı.
 * @return string '' ya da en son mesajın türü (dua | ok)
 */
function flxc_bot_closers_kind( array $texts, $last_out ) {
	if ( ! $texts || false !== strpos( (string) $last_out, '?' ) ) {
		return '';
	}
	$kind = '';
	foreach ( $texts as $t ) {
		$kind = flxc_bot_closer_kind( $t );
		if ( '' === $kind ) {
			return '';
		}
	}
	return $kind;
}

/**
 * Tamamlanmış sohbette kişi yalnız teşekkür / veda yazdıysa model çağrılmaz: mesaja tepki bırakılır
 * (arka arkaya en çok iki kez), metin gitmez.
 *
 * @return bool bu mesajlar burada karşılandı mı
 */
function flxc_bot_close_fast( $chat, $after_id ) {
	global $wpdb;
	$state = flxc_bot_state( $chat );
	if ( ! flxc_bot_is_closed( $state ) ) {
		return false;
	}
	$mt   = flxc_table( 'messages' );
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT type, body FROM $mt WHERE chat_id = %d AND direction = 'in' AND id > %d ORDER BY id ASC LIMIT 6", (int) $chat['id'], (int) $after_id ), ARRAY_A ); // phpcs:ignore
	if ( ! $rows || count( $rows ) > 5 ) {
		return false;
	}
	$texts = array();
	foreach ( $rows as $m ) {
		if ( 'text' !== $m['type'] ) {
			return false;
		}
		$texts[] = (string) $m['body'];
	}
	$out  = (string) $wpdb->get_var( $wpdb->prepare( "SELECT body FROM $mt WHERE chat_id = %d AND direction = 'out' AND type <> 'deleted' ORDER BY id DESC LIMIT 1", (int) $chat['id'] ) ); // phpcs:ignore
	$kind = flxc_bot_closers_kind( $texts, $out );
	if ( '' === $kind ) {
		return false;
	}
	$n = (int) ( $state['closed_n'] ?? 0 );
	if ( $n < 2 ) {
		flxc_bot_react_send( $chat, flxc_bot_closer_emoji( $kind ) );
	}
	$state             = flxc_bot_state( flxc_get_chat( (int) $chat['id'] ) );
	$state['closed']   = time();
	$state['closed_n'] = $n + 1;
	flxc_bot_save_state( (int) $chat['id'], $state );
	flxc_bot_stat_add( array( 'closers' => 1 ) );
	return true;
}

/**
 * Test sohbeti / benchmark: aynı hızlı yol, durum istemcide.
 *
 * @return array|null { emoji, state } ya da null (model cevaplasın)
 */
function flxc_bot_close_fast_sandbox( array $history, array $state ) {
	if ( ! flxc_bot_is_closed( $state ) ) {
		return null;
	}
	$texts = array();
	$out   = '';
	for ( $i = count( $history ) - 1; $i >= 0; $i-- ) {
		if ( 'user' !== ( $history[ $i ]['role'] ?? '' ) ) {
			$out = (string) ( $history[ $i ]['content'] ?? '' );
			break;
		}
		array_unshift( $texts, (string) ( $history[ $i ]['content'] ?? '' ) );
	}
	$kind = flxc_bot_closers_kind( $texts, $out );
	if ( '' === $kind ) {
		return null;
	}
	$n     = (int) ( $state['closed_n'] ?? 0 );
	$emoji = $n < 2 && flxc_bot_react_on() ? flxc_bot_closer_emoji( $kind ) : '';
	if ( '' !== $emoji ) {
		$state['reacts']   = array_slice( (array) ( $state['reacts'] ?? array() ), -9 );
		$state['reacts'][] = $emoji;
	}
	$state['closed']   = time();
	$state['closed_n'] = $n + 1;
	return array( 'emoji' => $emoji, 'state' => $state );
}

/**
 * 1.42.0: tamamlandı durumu bir aksiyonla da kalkar. Sohbete asistan dışı bir mesaj gidince
 * (sertifika, bağış onayı, ödeme bildirimi, temsilci ya da telefondan yazılan mesaj) konuşma yeniden
 * açılmış sayılır; kişinin sonraki "teşekkürler"i tepkiyle geçiştirilmez, asistan normal cevap verir.
 * Asistanın kendi mesajları (source bot) durumu değiştirmez.
 */
function flxc_bot_reopen_on_send( $id, $chat = null ) {
	$m = flxc_get_message( (int) $id );
	if ( ! $m || 'out' !== $m['direction'] || 'bot' === (string) $m['source'] ) {
		return;
	}
	$fresh = flxc_get_chat( (int) $m['chat_id'] );
	if ( ! $fresh ) {
		return;
	}
	$state = flxc_bot_state( $fresh );
	if ( empty( $state['closed'] ) ) {
		return;
	}
	$state['closed'] = 0;
	unset( $state['closed_n'] );
	flxc_bot_save_state( (int) $fresh['id'], $state );
}
add_action( 'flxc_message_sent', 'flxc_bot_reopen_on_send', 20, 2 );
add_action( 'flxc_message_from_phone', 'flxc_bot_reopen_on_send', 20, 2 );
