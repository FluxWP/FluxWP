<?php
/**
 * 1.52.0: asistanın kişinin dilinde konuşması.
 *
 * - Kişinin dili: gelen mesajdan ücretsiz tespit (yazı sistemi + sık kelimeler) ya da modelin araçta verdiği
 *   `lang`. Sohbet durumunda (meta.bot.lang) saklanır; bilinmiyorsa sitenin dili.
 * - Sistemin gönderdiği hazır mesajlar (fiyat listesi, özet, ödeme, iptal, devir…) site dilinde üretilir ve
 *   gönderilirken kişinin diline çevrilir (personelin gördüğü sistem notları site dilinde kalır).
 * - Değişkenli şablon: rakam, tutar, IBAN, link, e-posta, {değişken} ve isimler çeviriden önce {{n}} yer tutucusu
 *   olur. Çeviri dil başına bir kez yapılır ve saklanır; gönderirken güncel değerler yerine konur. Yer tutucusu
 *   eksik ya da fazladan rakam içeren çeviri kullanılmaz (kaynak metin gider): fiyatı model yazmaz.
 * - Araç sonucu önbelleği: kişiye özel olmayan bilgi araçları kısa süre (dil başına) saklanır; tür, ayar ya da
 *   yazı değişince boşalır. Kalıcı nesne önbelleği (Redis) varsa onu, yoksa transient kullanır.
 * - flxc_in_lang(): başka eklentiler için WordPress'i bir dile geçirip çalıştırma yardımcısı.
 *
 * Filtreler: flxc_bot_lang (sohbetin dili), flxc_tr_skip (çeviriyi atla), flxc_bot_tool_cacheable.
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Dil kodları ---------- */

/** "pt_BR" → "pt-br", "TR" → "tr"; geçersizse ''. */
function flxc_lang_clean( $code ) {
	if ( class_exists( 'FLXB_Lang' ) ) {
		return FLXB_Lang::clean( $code );
	}
	$code = strtolower( str_replace( '_', '-', trim( (string) $code ) ) );
	return preg_match( '/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $code ) ? $code : '';
}

/** Sitenin dili (Flux Lang varsayılanı, yoksa WordPress dili). */
function flxc_lang_site() {
	static $site = null;
	if ( null === $site ) {
		if ( class_exists( 'FLXB_Lang' ) ) {
			$site = FLXB_Lang::site();
		} else {
			$c    = function_exists( 'flxl_default_language' ) ? flxc_lang_clean( flxl_default_language() ) : '';
			$site = '' !== $c ? $c : flxc_lang_clean( substr( get_locale(), 0, 2 ) );
		}
	}
	return '' !== $site ? $site : 'tr';
}

/** Dilin İngilizce adı (çeviri isteği ve talimat için). */
function flxc_lang_name( $code ) {
	$n = array(
		'tr' => 'Turkish', 'en' => 'English', 'de' => 'German', 'fr' => 'French', 'es' => 'Spanish', 'pt' => 'Portuguese',
		'pt-br' => 'Brazilian Portuguese', 'it' => 'Italian', 'nl' => 'Dutch', 'ru' => 'Russian', 'uk' => 'Ukrainian',
		'ar' => 'Arabic', 'fa' => 'Persian', 'ur' => 'Urdu', 'ja' => 'Japanese', 'zh' => 'Chinese (Simplified)', 'ko' => 'Korean',
		'lv' => 'Latvian', 'lt' => 'Lithuanian', 'et' => 'Estonian', 'pl' => 'Polish', 'az' => 'Azerbaijani', 'bs' => 'Bosnian',
		'sq' => 'Albanian', 'id' => 'Indonesian', 'ms' => 'Malay', 'he' => 'Hebrew', 'el' => 'Greek', 'ka' => 'Georgian',
		'hi' => 'Hindi', 'bn' => 'Bengali', 'sv' => 'Swedish', 'da' => 'Danish', 'no' => 'Norwegian', 'fi' => 'Finnish',
		'ro' => 'Romanian', 'bg' => 'Bulgarian', 'hu' => 'Hungarian', 'cs' => 'Czech', 'kk' => 'Kazakh', 'uz' => 'Uzbek',
	);
	$code = flxc_lang_clean( $code );
	return $n[ $code ] ?? ( $n[ substr( $code, 0, 2 ) ] ?? $code );
}

/** Flux Chat'in bu dil için hazır çeviri paketi var mı (yoksa __() İngilizce döner). */
function flxc_lang_has_pack( $code ) {
	$code = flxc_lang_clean( $code );
	if ( 'en' === substr( $code, 0, 2 ) ) {
		return true;
	}
	$map = array( 'tr' => 'tr_TR', 'de' => 'de_DE', 'es' => 'es_ES', 'fr' => 'fr_FR', 'pt' => 'pt_BR', 'pt-br' => 'pt_BR', 'ja' => 'ja', 'zh' => 'zh_CN', 'ar' => 'ar', 'ru' => 'ru_RU' );
	$loc = $map[ $code ] ?? ( $map[ substr( $code, 0, 2 ) ] ?? '' );
	return '' !== $loc && is_readable( FLXC_DIR . 'languages/flx-chat-' . $loc . '.l10n.php' );
}

/* ---------- Dil tespiti ---------- */

/** Latin yazılı dillerin sık kelimeleri (küçük harf). Kısa selamlaşmalar da dahil. */
function flxc_lang_words() {
	return array(
		'tr' => 'merhaba selam slm sa selamün aleyküm teşekkür teşekkürler sağol sağolun tamam evet hayır ve bir bu ne için mı mi mu mü nasıl nedir ben sen biz var yok istiyorum olur fidan bağış bağışı yapmak yapabilir yapabilirim kaç tane lütfen nerede neden ama çok değil şimdi bilgi kurban hesap dekont',
		'en' => 'the and is are how can i you to what hello hi hey thanks thank please want would like donate donation tree trees my for of it yes no do does where when price much many cost me we our your this that with have info about',
		'de' => 'der die das und ist ich nicht wie was hallo danke bitte ein eine mit für ich möchte spenden spende baum bäume ja nein wo kann können ihr sie mein wir guten tag',
		'fr' => 'le la les et est je vous pas comment bonjour merci un une pour avec que qui oui non où combien je veux faire don arbre arbres mon ma nous est-ce salut',
		'es' => 'el la los las y es yo no como cómo hola gracias por para una con que quiero sí dónde cuánto donar donación árbol árboles mi nosotros buenos días puedo',
		'pt' => 'o os as e é eu não como olá obrigado obrigada por para uma com que quero sim onde quanto doar doação árvore árvores meu nós bom dia posso você',
		'it' => 'il lo gli e è io non come ciao grazie per una con che voglio sì dove quanto donare donazione albero alberi mio noi buongiorno posso',
		'nl' => 'de het een en is ik niet hoe wat hallo dank bedankt alstublieft voor met wil ja nee waar hoeveel doneren donatie boom bomen mijn wij',
		'lv' => 'un ir es kā ko labdien sveiki paldies vai nē jā man ar par cik kur gribu ziedot ziedojums koks koki mans mēs lūdzu labrīt',
		'az' => 'salam necəsiniz təşəkkür edirəm bəli xeyr və bir bu nə üçün necə mən sən biz var yox istəyirəm ağac ianə zəhmət',
		'id' => 'dan yang ini itu saya anda tidak apa bagaimana halo terima kasih tolong untuk dengan mau ya pohon donasi berapa',
	);
}

/**
 * Metnin dili. Kısa ya da belirsiz metinde '' (sayı, emoji, "ok", "2 tane" gibi).
 *
 * @return array{0:string,1:int} [kod, güven 0-3]
 */
function flxc_lang_detect( $text ) {
	$t = (string) $text;
	$t = preg_replace( '~https?://\S+|\S+@\S+|\[[^\]]*\]~u', ' ', $t );
	$t = preg_replace( '/[\p{N}\p{P}\p{S}]+/u', ' ', $t );
	$t = trim( preg_replace( '/\s+/u', ' ', $t ) );
	if ( mb_strlen( $t ) < 2 ) {
		return array( '', 0 );
	}
	// Yazı sistemi.
	$scripts = array(
		'ar' => '\p{Arabic}', 'ru' => '\p{Cyrillic}', 'ja' => '[\p{Hiragana}\p{Katakana}]', 'zh' => '\p{Han}',
		'he' => '\p{Hebrew}', 'el' => '\p{Greek}', 'ka' => '\p{Georgian}', 'ko' => '\p{Hangul}', 'hi' => '\p{Devanagari}',
	);
	$letters = max( 1, preg_match_all( '/\p{L}/u', $t ) );
	foreach ( $scripts as $code => $re ) {
		$n = preg_match_all( '/' . $re . '/u', $t );
		if ( $n && $n / $letters > 0.4 ) {
			if ( 'zh' === $code && preg_match( '/[\p{Hiragana}\p{Katakana}]/u', $t ) ) {
				$code = 'ja';
			}
			if ( 'ru' === $code && preg_match( '/[іїєґ]/u', mb_strtolower( $t ) ) ) {
				$code = 'uk';
			}
			return array( $code, $n >= 3 ? 3 : 2 );
		}
	}
	$low    = mb_strtolower( $t );
	$tokens = preg_split( '/\s+/u', $low );
	$tokens = is_array( $tokens ) ? $tokens : array();
	// Ayırt edici harfler (2 puan) ve sık kelimeler (1 puan).
	$marks = array(
		'tr' => '/[ğış]|İ/u',
		'lv' => '/[āēīūķļņģ]/u',
		'az' => '/ə/u',
		'de' => '/[ßä]/u',
		'es' => '/[ñ¿¡]/u',
		'pt' => '/[ãõ]/u',
	);
	$score = array();
	foreach ( flxc_lang_words() as $code => $list ) {
		$score[ $code ] = count( array_intersect( $tokens, explode( ' ', $list ) ) );
	}
	foreach ( $marks as $code => $re ) {
		if ( 1 === preg_match( $re, 'tr' === $code ? $t : $low ) ) {
			$score[ $code ] = (int) ( $score[ $code ] ?? 0 ) + 2;
		}
	}
	arsort( $score );
	$vals = array_values( $score );
	$best = (int) ( $vals[0] ?? 0 );
	$next = (int) ( $vals[1] ?? 0 );
	if ( $best < 1 || $best === $next ) {
		return array( '', 0 );
	}
	$conf = 1;
	if ( $best >= 3 && $best >= 2 * $next ) {
		$conf = 3;
	} elseif ( $best >= 2 ) {
		$conf = 2;
	}
	return array( (string) array_key_first( $score ), $conf );
}

/**
 * Gelen metne göre durumdaki dili günceller. Tek kelimelik belirsiz mesaj dili değiştirmez; model dili verdiyse
 * (lang_src = model) yalnız güçlü tespit ezer.
 */
function flxc_bot_lang_from_text( array $state, $text ) {
	list( $code, $conf ) = flxc_lang_detect( $text );
	if ( '' === $code ) {
		return $state;
	}
	$cur = (string) ( $state['lang'] ?? '' );
	$min = '' === $cur ? 1 : ( 'model' === ( $state['lang_src'] ?? '' ) ? 3 : 2 );
	if ( $code !== $cur && $conf >= $min ) {
		$state['lang']     = $code;
		$state['lang_src'] = 'text';
	}
	return $state;
}

/** Sohbetin dili: durumdaki, yoksa sitenin dili. */
function flxc_bot_state_lang( array $state ) {
	$c = flxc_lang_clean( $state['lang'] ?? '' );
	return (string) apply_filters( 'flxc_bot_lang', '' !== $c ? $c : flxc_lang_site(), $state );
}

/** Bu turun dili (flxc_bot_lang_set ile; yoksa sitenin dili). */
function flxc_bot_cur_lang() {
	$c = (string) ( $GLOBALS['flxc_bot_lang'] ?? '' );
	return '' !== $c ? $c : flxc_lang_site();
}

function flxc_bot_lang_set( $code ) {
	$GLOBALS['flxc_bot_lang'] = flxc_lang_clean( $code );
}

/**
 * Gerçek sohbette son gelen mesajlardan dili günceller, kaydeder ve turun dilini ayarlar.
 *
 * @return string dil kodu
 */
function flxc_bot_lang_turn( $chat ) {
	global $wpdb;
	$state = flxc_bot_state( $chat );
	$mt    = flxc_table( 'messages' );
	$rows  = $wpdb->get_col( $wpdb->prepare( "SELECT body FROM $mt WHERE chat_id = %d AND direction = 'in' AND type = 'text' ORDER BY id DESC LIMIT 3", (int) $chat['id'] ) ); // phpcs:ignore
	$text  = implode( "\n", array_reverse( array_map( 'strval', (array) $rows ) ) );
	$new   = flxc_bot_lang_from_text( $state, $text );
	if ( ( $new['lang'] ?? '' ) !== ( $state['lang'] ?? '' ) ) {
		flxc_bot_save_state( $chat['id'], $new );
	}
	$lang = flxc_bot_state_lang( $new );
	flxc_bot_lang_set( $lang );
	return $lang;
}

/** Talimatın "Bilinenler" kısmına: kişinin dili. */
function flxc_bot_lang_known( array $state ) {
	$c = flxc_bot_state_lang( $state );
	return 'Kişinin dili: ' . flxc_lang_name( $c ) . ' (' . $c . ')' . ( empty( $state['lang'] ) ? ' (henüz belli değil, sitenin dili)' : '' ) . '. Kişi hangi dilde yazıyorsa o dilde cevap ver.';
}

/** Talimatın sabit kısmına (önbellekli): dil kuralı. */
function flxc_bot_lang_rule() {
	return '- Kişinin son mesajının dilinde yaz (Türkçe dışındaki dillerde de). send_price_list, set_order, create_donation, payment_link, cancel_donation ve certificate_resend çağırırken lang alanına kişinin dilinin ISO 639-1 kodunu yaz (en, tr, de, ar, ru, lv…); sistemin gönderdiği fiyat listesi, özet ve ödeme mesajları o dilde gider. Araç sonuçlarındaki fiyat ve tutarları olduğu gibi kullan, çevirirken rakamı değiştirme.';
}

/** Kişinin dilini alan araçlar. */
function flxc_bot_lang_tools() {
	return (array) apply_filters( 'flxc_bot_lang_tools', array( 'send_price_list', 'set_order', 'create_donation', 'payment_link', 'cancel_donation', 'certificate_resend' ) );
}

/** Araç tanımlarına `lang` alanını ekler. */
function flxc_bot_lang_tool_defs( array $list ) {
	$names = flxc_bot_lang_tools();
	foreach ( $list as $i => $t ) {
		if ( ! in_array( $t['function']['name'] ?? '', $names, true ) ) {
			continue;
		}
		$props         = (array) $t['function']['parameters']['properties'];
		$props['lang'] = array( 'type' => 'string', 'description' => 'Kişinin yazdığı dilin ISO 639-1 kodu (en, tr, de, ar…)' );
		$list[ $i ]['function']['parameters']['properties'] = (object) $props;
	}
	return $list;
}

/** Tek hazır mesaj (sohbetin dilinde). */
function flxc_bot_tr_text( $text, array $state, $ai = true ) {
	$r = flxc_bot_tr_out( array( (string) $text ), $state, $ai );
	return (string) $r[0];
}

/**
 * Model araçta `lang` verdiyse sohbetin dili güncellenir. Araç sonucu ve hazır mesajlar site dilinde üretilir;
 * gönderirken flxc_bot_tr_out ile çevrilir (personele giden sistem notları site dilinde kalır).
 */
function flxc_bot_tool_lang( $name, array $args, $chat, array &$ctx ) {
	$given = isset( $args['lang'] ) && in_array( $name, flxc_bot_lang_tools(), true ) ? flxc_lang_clean( $args['lang'] ) : '';
	if ( '' !== $given ) {
		if ( ! empty( $ctx['sandbox'] ) ) {
			$ctx['sandbox']['state']['lang']     = $given;
			$ctx['sandbox']['state']['lang_src'] = 'model';
		} elseif ( ! empty( $chat['id'] ) ) {
			$st = flxc_bot_state( flxc_get_chat( $chat['id'] ) );
			if ( ( $st['lang'] ?? '' ) !== $given ) {
				$st['lang']     = $given;
				$st['lang_src'] = 'model';
				flxc_bot_save_state( $chat['id'], $st );
			}
		}
		flxc_bot_lang_set( $given );
	}
}

/** WordPress'i (ve Flux Lang'i) bir dile geçirip çalıştırır. Aynı dildeyse doğrudan. */
function flxc_in_lang( $code, callable $fn ) {
	static $depth = null;
	$depth = is_array( $depth ) ? $depth : array();
	$code  = flxc_lang_clean( $code );
	if ( '' === $code || ( $depth && end( $depth ) === $code ) ) {
		return $fn();
	}
	$depth[] = $code;
	try {
		if ( function_exists( 'flxb_in_lang' ) ) {
			return flxb_in_lang(
				$code,
				function () use ( $fn ) {
					return $fn();
				}
			);
		}
		$map = array( 'tr' => 'tr_TR', 'en' => 'en_US', 'de' => 'de_DE', 'fr' => 'fr_FR', 'es' => 'es_ES', 'ru' => 'ru_RU', 'ar' => 'ar', 'ja' => 'ja', 'pt' => 'pt_BR', 'pt-br' => 'pt_BR', 'zh' => 'zh_CN' );
		$sw  = switch_to_locale( $map[ $code ] ?? $code );
		try {
			return $fn();
		} finally {
			if ( $sw ) {
				restore_previous_locale();
			}
		}
	} finally {
		array_pop( $depth );
	}
}

/* ---------- Değişkenli çeviri şablonu ---------- */

/**
 * Metni şablona çevirir: değişen değerler {{n}} olur.
 *
 * @param string[] $keep Aynen kalacak metinler (isimler, alıcı adı, kod).
 * @return array{0:string,1:string[]} [şablon, değerler]
 */
function flxc_tr_mask( $text, array $keep = array() ) {
	$vals = array();
	// Maskeleme sırasında işaret rakamsızdır (\u{E000}harf\u{E001}); rakam kuralı onu yakalamaz. Sonda {{n}} olur.
	$put  = function ( $v ) use ( &$vals ) {
		$i = array_search( $v, $vals, true );
		if ( false === $i ) {
			$vals[] = $v;
			$i      = count( $vals ) - 1;
		}
		$s = '';
		for ( $n = $i + 1; $n > 0; $n = intdiv( $n - 1, 26 ) ) {
			$s = chr( 97 + ( $n - 1 ) % 26 ) . $s;
		}
		return "\u{E000}" . $s . "\u{E001}";
	};
	$keep = array_values(
		array_unique(
			array_filter(
				array_map( 'trim', array_map( 'strval', $keep ) ),
				function ( $k ) {
					return '' !== $k;
				}
			)
		)
	);
	usort(
		$keep,
		function ( $a, $b ) {
			return mb_strlen( $b ) - mb_strlen( $a );
		}
	);
	// Kaynakta {{ }} olmamalı; varsa kırılır.
	$text = str_replace( array( '{{', '}}', "\u{E000}", "\u{E001}" ), array( '{ {', '} }', '', '' ), (string) $text );
	foreach ( $keep as $k ) {
		if ( mb_strlen( $k ) >= 2 ) {
			$text = str_replace( $k, $put( $k ), $text );
		}
	}
	$re   = '~https?://[^\s<>"\]]+'                                     // link
		. '|\[\[link\d+\]\]'                                          // link işareti
		. '|[\w.+-]+@[\w-]+(?:\.[\w-]+)+'                               // e-posta
		. '|\b[A-Z]{2}\d{2}(?:\s?[A-Z0-9]{4}){2,7}(?:\s?[A-Z0-9]{1,4})?\b' // IBAN
		. '|\{[a-z0-9_]+\}'                                              // ayardaki {değişken}
		. '|[+]?\p{Nd}(?:[\p{Nd}.,:/\'’\x{00A0}\x{202F}-]|\x20(?=\p{Nd}))*~ui'; // rakam, tutar, tarih, telefon
	$text = preg_replace_callback(
		$re,
		function ( $m ) use ( $put ) {
			return $put( rtrim( $m[0], '.,:-' ) ) . substr( $m[0], strlen( rtrim( $m[0], '.,:-' ) ) );
		},
		$text
	);
	$text = preg_replace_callback(
		'/\x{E000}([a-z]+)\x{E001}/u',
		function ( $m ) {
			$n = 0;
			foreach ( str_split( $m[1] ) as $c ) {
				$n = $n * 26 + ( ord( $c ) - 96 );
			}
			return '{{' . $n . '}}';
		},
		$text
	);
	return array( $text, $vals );
}

/** Yer tutucuların yerine değerleri koyar. */
function flxc_tr_unmask( $tpl, array $vals ) {
	return preg_replace_callback(
		'/\{\{(\d+)\}\}/',
		function ( $m ) use ( $vals ) {
			return $vals[ (int) $m[1] - 1 ] ?? '';
		},
		(string) $tpl
	);
}

/** Çeviri şablona uyuyor mu: aynı yer tutucular, fazladan rakam/link yok. */
function flxc_tr_valid( $src, $out ) {
	$out = trim( (string) $out );
	if ( '' === $out ) {
		return false;
	}
	preg_match_all( '/\{\{\d+\}\}/', $src, $a );
	preg_match_all( '/\{\{\d+\}\}/', $out, $b );
	$x = $a[0];
	$y = $b[0];
	sort( $x );
	sort( $y );
	if ( $x !== $y ) {
		return false;
	}
	$rest = preg_replace( '/\{\{\d+\}\}/', '', $out );
	return ! preg_match( '/\p{Nd}|:\/\/|@\w/u', $rest );
}

/** Dilin saklanan çevirileri: md5(şablon) => [s => çeviri, t => zaman]. */
function flxc_tr_store( $lang, $set = null ) {
	static $mem = null;
	$mem = is_array( $mem ) ? $mem : array();
	$key = 'flxc_tr_' . str_replace( '-', '_', $lang );
	if ( null !== $set ) {
		if ( count( $set ) > 400 ) {
			uasort(
				$set,
				function ( $a, $b ) {
					return (int) $b['t'] - (int) $a['t'];
				}
			);
			$set = array_slice( $set, 0, 300, true );
		}
		$mem[ $lang ] = $set;
		update_option( $key, $set, false );
		$langs = (array) get_option( 'flxc_tr_langs', array() );
		if ( ! in_array( $lang, $langs, true ) ) {
			$langs[] = $lang;
			update_option( 'flxc_tr_langs', $langs, false );
		}
		return $set;
	}
	if ( ! isset( $mem[ $lang ] ) ) {
		$v            = get_option( $key, array() );
		$mem[ $lang ] = is_array( $v ) ? $v : array();
	}
	return $mem[ $lang ];
}

/** Çeviri önbelleğini siler (tüm diller). */
function flxc_tr_clear() {
	foreach ( (array) get_option( 'flxc_tr_langs', array() ) as $l ) {
		delete_option( 'flxc_tr_' . str_replace( '-', '_', (string) $l ) );
	}
	delete_option( 'flxc_tr_langs' );
}

/** Önbellek özeti: [dil => metin sayısı]. */
function flxc_tr_stats() {
	$out = array();
	foreach ( (array) get_option( 'flxc_tr_langs', array() ) as $l ) {
		$out[ (string) $l ] = count( flxc_tr_store( (string) $l ) );
	}
	return $out;
}

/**
 * Hazır mesajları kişinin diline çevirir (değişkenli şablon + önbellek). Eksik çeviriler tek AI isteğiyle
 * yapılır; çeviri yoksa ya da şablona uymuyorsa metin olduğu gibi kalır.
 *
 * @param string[] $texts
 * @param string   $lang
 * @param string[] $keep Aynen kalacak değerler (isimler, alıcı adı).
 * @param bool     $ai   false: yalnız saklanan çeviriler (AI hatasından sonra giden yedek mesaj gibi).
 * @return string[] aynı anahtarlarla
 */
function flxc_tr_many( array $texts, $lang, array $keep = array(), $ai = true ) {
	$lang = flxc_lang_clean( $lang );
	if ( '' === $lang || $lang === flxc_lang_site() || apply_filters( 'flxc_tr_skip', false, $lang, $texts ) ) {
		return $texts;
	}
	$store = flxc_tr_store( $lang );
	$need  = array();
	$jobs  = array();
	foreach ( $texts as $k => $t ) {
		$t = (string) $t;
		if ( '' === trim( $t ) ) {
			continue;
		}
		list( $tpl, $vals ) = flxc_tr_mask( $t, $keep );
		if ( '' === trim( preg_replace( '/\{\{\d+\}\}|[\s\p{P}\p{S}]/u', '', $tpl ) ) ) {
			continue; // yalnız değer (IBAN, alıcı adı, link): çevrilecek bir şey yok
		}
		$h          = md5( $tpl );
		$jobs[ $k ] = array( $tpl, $vals, $h );
		if ( ! isset( $store[ $h ] ) ) {
			$need[ $h ] = $tpl;
		}
	}
	if ( $need && $ai ) {
		$got = flxc_tr_ai( $need, $lang );
		if ( $got ) {
			foreach ( $got as $h => $s ) {
				$store[ $h ] = array( 's' => $s, 't' => time() );
			}
			$store = flxc_tr_store( $lang, $store );
		}
	}
	$out = $texts;
	foreach ( $jobs as $k => $j ) {
		if ( isset( $store[ $j[2] ] ) ) {
			$out[ $k ] = flxc_tr_unmask( $store[ $j[2] ]['s'], $j[1] );
		}
	}
	return $out;
}

/** Tek metin. */
function flxc_tr( $text, $lang = null, array $keep = array() ) {
	$r = flxc_tr_many( array( (string) $text ), null === $lang ? flxc_bot_cur_lang() : $lang, $keep );
	return (string) $r[0];
}

/**
 * Şablonları AI ile çevirir. Doğrulamadan geçmeyen çeviri dönmez. Hata olursa bu dil 10 dk denenmez.
 *
 * @param array $tpls md5 => şablon
 * @return array md5 => çeviri
 */
function flxc_tr_ai( array $tpls, $lang ) {
	if ( ! function_exists( 'flxc_ai_chat' ) || ! function_exists( 'flxc_ai_ready' ) || ! flxc_ai_ready() || get_transient( 'flxc_tr_wait_' . $lang ) ) {
		return array();
	}
	$tpls = array_slice( $tpls, 0, 30, true );
	$in   = array();
	$i    = 0;
	$ids  = array();
	foreach ( $tpls as $h => $t ) {
		$ids[ 't' . ( ++$i ) ] = $h;
		$in[ 't' . $i ]        = $t;
	}
	$sys = sprintf(
		'You translate WhatsApp messages of a charity into %1$s (%2$s). The input is a JSON object; reply with a JSON object with the same keys and the translated texts, nothing else. '
		. 'The source may mix languages; the whole output must be in %1$s. Keep every {{n}} token exactly as it is (same tokens, same count); they stand for numbers, prices, links and names. '
		. 'Never write digits, prices, links or e-mail addresses yourself. Keep emoji, *bold* markers, bullet characters and line breaks. Warm, natural, short; do not add or drop information.',
		flxc_lang_name( $lang ),
		$lang
	);
	$r = flxc_ai_chat(
		array(
			array( 'role' => 'system', 'content' => $sys ),
			array( 'role' => 'user', 'content' => wp_json_encode( $in, JSON_UNESCAPED_UNICODE ) ),
		),
		array(
			'purpose'     => 'translate',
			'max_tokens'  => 3000,
			'temperature' => 0,
			'timeout'     => 25,
		)
	);
	if ( is_wp_error( $r ) ) {
		set_transient( 'flxc_tr_wait_' . $lang, 1, 10 * MINUTE_IN_SECONDS );
		return array();
	}
	$raw = trim( (string) ( $r['content'] ?? '' ) );
	$raw = preg_replace( '/^```[a-z]*\s*|\s*```$/i', '', $raw );
	$js  = json_decode( $raw, true );
	if ( ! is_array( $js ) && preg_match( '/\{.*\}/s', $raw, $m ) ) {
		$js = json_decode( $m[0], true );
	}
	$out = array();
	foreach ( is_array( $js ) ? $js : array() as $k => $v ) {
		if ( isset( $ids[ $k ] ) && is_string( $v ) && flxc_tr_valid( $in[ $k ], $v ) ) {
			$out[ $ids[ $k ] ] = trim( $v );
		}
	}
	return $out;
}

/**
 * Sohbetin hazır mesajlarında aynen kalacak değerler: kişinin adları, alıcı adı, kurum adı.
 *
 * @return string[]
 */
function flxc_bot_tr_keep( array $state ) {
	// Vekâlet metni hukuki metindir: AI ile çevrilmez (Flux Donate'taki dil karşılığı ya da site dili aynen gider).
	$keep = array( $state['full'] ?? '', $state['name'] ?? '', $state['email'] ?? '', (string) ( $state['order']['proxy'] ?? '' ) );
	foreach ( (array) ( $state['order']['benef'] ?? array() ) as $b ) {
		$keep[] = (string) ( $b['name'] ?? '' );
	}
	$acc    = function_exists( 'flxc_bot_bank_account' ) ? flxc_bot_bank_account() : array();
	$keep[] = (string) ( $acc['holder'] ?? '' );
	$keep[] = (string) ( $acc['bank'] ?? '' );
	$keep[] = function_exists( 'flxc_brand_name' ) ? (string) flxc_brand_name() : '';
	return $keep;
}

/**
 * Sistemin göndereceği hazır mesajları sohbetin diline çevirir (site dilindeyse olduğu gibi).
 *
 * @param string[] $texts
 */
function flxc_bot_tr_out( array $texts, array $state, $ai = true ) {
	$lang = flxc_bot_state_lang( $state );
	if ( $lang === flxc_lang_site() ) {
		return $texts;
	}
	return flxc_tr_many( array_map( 'strval', $texts ), $lang, flxc_bot_tr_keep( $state ), $ai );
}

/* ---------- Araç sonucu önbelleği ---------- */

/** Önbelleğe alınabilen (kişiye özel olmayan) araçlar. */
function flxc_bot_tool_cacheable( $name ) {
	$list = (array) apply_filters( 'flxc_bot_tool_cacheable', array( 'donation_options', 'payment_info', 'search_site', 'read_doc' ) );
	return in_array( $name, $list, true );
}

/** Önbellek sürümü: tür, ayar, ödeme ayarı ya da yazı değişince artar. */
function flxc_bot_tool_cache_ver() {
	return (int) get_option( 'flxc_tcache_v', 1 );
}

function flxc_bot_tool_cache_bump() {
	static $done = false;
	if ( ! $done ) {
		$done = true;
		update_option( 'flxc_tcache_v', flxc_bot_tool_cache_ver() + 1, false );
	}
}

foreach ( array( 'flxb_type_saved', 'update_option_flxc_settings', 'update_option_woocommerce_flxb_havale_settings', 'update_option_woocommerce_bacs_accounts', 'update_option_flxb_proxy_texts', 'save_post', 'deleted_post', 'flxl_settings_saved' ) as $flxc_hook ) {
	add_action(
		$flxc_hook,
		function ( $id = 0 ) {
			// Yazı kaydında yalnız herkese açık içerik (sipariş, revizyon, otomatik kayıt boşaltmaz).
			if ( in_array( current_action(), array( 'save_post', 'deleted_post' ), true ) ) {
				$pt = get_post_type( (int) $id );
				if ( ! $pt || wp_is_post_revision( (int) $id ) || wp_is_post_autosave( (int) $id ) || ! is_post_type_viewable( $pt ) ) {
					return;
				}
			}
			flxc_bot_tool_cache_bump();
		}
	);
}
unset( $flxc_hook );

/**
 * Aracı önbellekten verir ya da çalıştırıp saklar (10 dk). Hatalı sonuç saklanmaz.
 *
 * @param callable $run function(): array
 */
function flxc_bot_tool_cached( $name, array $args, callable $run ) {
	unset( $args['lang'] );
	ksort( $args );
	$key = 'flxc_t_' . md5( $name . '|' . wp_json_encode( $args ) . '|' . flxc_bot_tool_cache_ver() . '|' . FLXC_VERSION );
	$ext = function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache();
	$hit = $ext ? wp_cache_get( $key, 'flxc_tools' ) : get_transient( $key );
	if ( is_array( $hit ) ) {
		$GLOBALS['flxc_tool_cache_hits'] = (int) ( $GLOBALS['flxc_tool_cache_hits'] ?? 0 ) + 1;
		return $hit;
	}
	$res = $run();
	if ( is_array( $res ) && ! empty( $res['ok'] ) ) {
		if ( $ext ) {
			wp_cache_set( $key, $res, 'flxc_tools', 10 * MINUTE_IN_SECONDS );
		} else {
			set_transient( $key, $res, 10 * MINUTE_IN_SECONDS );
		}
	}
	return $res;
}
