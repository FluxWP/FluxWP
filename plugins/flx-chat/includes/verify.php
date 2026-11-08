<?php
/**
 * Ortak kod doğrulama (1.9.0): e-posta, WhatsApp ve SMS için aynı akış.
 * 6 haneli kod, varsayılan 5 dk geçerli, sohbet başına saatte 3 gönderim, 5 deneme.
 * Kod saklanmaz; yalnız hmac'i bot durumunda durur. İstatistik günlük sayaçlara yazılır.
 *
 * Başka kod da kullanabilir:
 *   flxc_verify_send( $chat, 'phone', '+90532...', $state )  // ya da 'email'
 *   flxc_verify_check( $chat, '123456', $state )
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

function flxc_verify_on() {
	return (bool) flxc_setting( 'email_verify' );
}

/** Kodun geçerlilik süresi (dakika). */
function flxc_verify_ttl() {
	return min( 30, max( 1, (int) flxc_setting( 'verify_ttl' ) ) );
}

/** Telefon kodu kanalları, sırayla denenir: whatsapp (Çağrı Merkezi bağlantısı), sms (Flux Mail). */
function flxc_verify_phone_channels() {
	$pref = (string) flxc_setting( 'verify_phone_channel' );
	$sms  = function_exists( 'flxi_send_message' ) && function_exists( 'flxi_resolve_connection' ) && flxi_resolve_connection( 'sms', 0 );
	$out  = array();
	if ( 'sms' !== $pref ) {
		$out[] = 'whatsapp';
	}
	if ( $sms && 'whatsapp' !== $pref ) {
		$out[] = 'sms';
	}
	return apply_filters( 'flxc_verify_phone_channels', $out ? $out : array( 'whatsapp' ) );
}

/** WhatsApp/SMS kod mesajı. */
function flxc_verify_text( $code ) {
	$t = trim( (string) flxc_setting( 'verify_msg' ) );
	if ( '' === $t ) {
		$t = flxc_verify_default_text();
	}
	return apply_filters( 'flxc_verify_message', strtr( $t, array( '{kod}' => $code, '{dk}' => flxc_verify_ttl(), '{marka}' => flxc_brand_name() ) ), $code );
}

function flxc_verify_default_text() {
	return __( 'Your {marka} verification code: {kod}', 'flx-chat' ) . "\n\n" . __( 'The code is valid for {dk} minutes. Type it in your WhatsApp chat with us. If you did not ask for it, ignore this message.', 'flx-chat' );
}

function flxc_verify_hash( $code, $chat_id, $type, $value ) {
	return hash_hmac( 'sha256', $code . '|' . (int) $chat_id . '|' . $type . '|' . strtolower( (string) $value ), wp_salt( 'auth' ) );
}

function flxc_verify_stat( array $inc ) {
	if ( function_exists( 'flxc_bot_stat_add' ) ) {
		flxc_bot_stat_add( $inc );
	}
}

/** Bu sohbette kodla doğrulanmış telefonlar (rakam, ülke kodlu). */
function flxc_verify_phones( array $state ) {
	return array_values( array_filter( array_map( 'strval', (array) ( $state['phones_ok'] ?? array() ) ) ) );
}

/** WhatsApp kodunu doğrudan Evolution'a yollar (panelde ayrı sohbet açılmaz). */
function flxc_verify_send_whatsapp( $chat, $phone, $text ) {
	$conn = ! empty( $chat['conn'] ) ? $chat['conn'] : ( flxc_default_connection()['id'] ?? 0 );
	if ( ! $conn ) {
		return false;
	}
	// 1.32.0: kodun telefondan dönen yankısı panelde sohbet olarak kaydedilmez, asistanı susturmaz.
	flxc_notify_remember( '', $text );
	$r = flxc_evo_send_text( $conn, $phone, $text, null, false );
	if ( is_wp_error( $r ) ) {
		$GLOBALS['flxc_verify_err'] = $r->get_error_message();
		return false;
	}
	flxc_notify_remember( (string) ( $r['json']['key']['id'] ?? '' ), $text );
	if ( is_array( $r ) && isset( $r['ok'] ) && ! $r['ok'] ) {
		$GLOBALS['flxc_verify_err'] = (string) ( $r['error'] ?? '' );
		return false;
	}
	return true;
}

/** Üst üste başarısız deneme sınırı (gönderilemeyen kod, yanlış ya da süresi dolan kod). */
function flxc_verify_max_fails() {
	return max( 1, min( 10, (int) flxc_setting( 'verify_max_fails' ) ) );
}

function flxc_verify_fail_text() {
	$t = trim( (string) flxc_setting( 'verify_fail_text' ) );
	return '' !== $t ? $t : __( 'Something went wrong; our team will contact you as soon as possible 🌱 Meanwhile, feel free to ask anything else.', 'flx-chat' );
}

/** Doğrulama bu sohbette kilitli mi (sınır aşıldı; 24 sa). */
function flxc_verify_locked( array $state ) {
	return (int) ( $state['vc_lock'] ?? 0 ) > time();
}

/**
 * Başarısız denemeyi sayar; sınıra ulaşınca doğrulama 24 sa kilitlenir, ekibe haber gider.
 *
 * @return array araç sonucu (locked_now: kişiye sabit metin gider)
 */
function flxc_verify_fail( $chat, array &$state, $sandbox, $error, $why ) {
	$state['vc_fails'] = (int) ( $state['vc_fails'] ?? 0 ) + 1;
	if ( ! $sandbox ) {
		/* translators: 1: attempt, 2: limit, 3: reason */
		flxc_system_message( $chat, sprintf( __( 'Verification attempt failed (%1$d/%2$d): %3$s', 'flx-chat' ), $state['vc_fails'], flxc_verify_max_fails(), $why ) );
	}
	if ( $state['vc_fails'] < flxc_verify_max_fails() ) {
		return array( 'ok' => false, 'error' => $error . ' (Başarısız deneme ' . $state['vc_fails'] . '/' . flxc_verify_max_fails() . '.)' );
	}
	$state['vc_lock'] = time() + DAY_IN_SECONDS;
	unset( $state['vc'] );
	if ( ! $sandbox ) {
		flxc_verify_stat( array( 'vc_locked' => 1 ) );
		$why = __( 'The person wanted to see their records but could not be verified; someone from the team should get in touch', 'flx-chat' );
		flxc_system_message( $chat, $why );
		$fresh = flxc_get_chat( $chat['id'] );
		flxc_update_chat( $chat['id'], array( 'unread' => max( 1, (int) $fresh['unread'] ), 'status' => 'open' ) );
		do_action( 'flxc_bot_handoff', $fresh, $why );
	}
	return array(
		'ok'         => false,
		'locked_now' => true,
		'error'      => 'Doğrulama ' . flxc_verify_max_fails() . ' kez başarısız oldu; ekibe haber verildi. Kişiye bunu bildiren mesaj otomatik gidiyor, sen ayrıca yazma. Bundan sonra bu kişiye kayıt bilgisi verme ve yeni kod gönderme; diğer sorularına normal cevap ver.',
	);
}

/**
 * Kod gönderir.
 *
 * @param array  $chat  sohbet
 * @param string $type  email|phone
 * @param string $value e-posta ya da telefon
 * @param array  $state bot durumu (değişir)
 * @param bool   $sandbox test sohbeti: gönderilmez, kod test panelinde görünür (state vc_test)
 * @return array ok, channel, error, note
 */
function flxc_verify_send( $chat, $type, $value, array &$state, $sandbox = false ) {
	$now = time();
	if ( flxc_verify_locked( $state ) ) {
		return array( 'ok' => false, 'error' => 'Doğrulama bu kişi için geçici olarak kapalı (çok sayıda başarısız deneme); ekip iletişime geçecek. Kayıt bilgisi verme, kişiye ekibin döneceğini söyle, diğer sorularına cevap ver.' );
	}
	$ttl = flxc_verify_ttl();
	if ( 'email' === $type ) {
		$value = strtolower( sanitize_email( (string) $value ) );
		if ( ! is_email( $value ) ) {
			return array( 'ok' => false, 'error' => 'E-posta adresi geçersiz görünüyor, kişiden kontrol etmesini iste.' );
		}
		if ( in_array( $value, flxc_ev_emails( $state ), true ) ) {
			return array( 'ok' => true, 'verified' => true, 'note' => 'Bu e-posta zaten doğrulanmış; my_records çağırabilirsin.' );
		}
		if ( function_exists( 'flx_contact_blocked' ) && flx_contact_blocked( '', $value, 'email' ) ) {
			return array( 'ok' => false, 'error' => 'Bu adrese e-posta gönderilemiyor. Kişiye ekibe bağlanabileceğini söyle.' );
		}
	} elseif ( 'phone' === $type ) {
		$value = flxc_normalize_phone( (string) $value );
		if ( ! $value || strlen( $value ) < 10 ) {
			return array( 'ok' => false, 'error' => 'Numara geçersiz görünüyor; kişiden ülke koduyla ya da 05xx ile başlayarak yazmasını iste.' );
		}
		if ( $value === (string) $chat['phone'] || in_array( $value, flxc_verify_phones( $state ), true ) ) {
			return array( 'ok' => true, 'verified' => true, 'note' => 'Bu numara zaten bu kişinin; my_records çağırabilirsin.' );
		}
		if ( function_exists( 'flx_contact_blocked' ) && flx_contact_blocked( $value, '', 'whatsapp' ) ) {
			return array( 'ok' => false, 'error' => 'Bu numaraya mesaj gönderilemiyor. Kişiye ekibe bağlanabileceğini söyle.' );
		}
	} else {
		return array( 'ok' => false, 'error' => 'type email ya da phone olmalı.' );
	}
	$sent = array_values(
		array_filter(
			(array) ( $state['vc_sent'] ?? array() ),
			function ( $t ) use ( $now ) {
				return (int) $t > $now - HOUR_IN_SECONDS;
			}
		)
	);
	if ( count( $sent ) >= 3 ) {
		return array( 'ok' => false, 'error' => 'Son bir saatte 3 kod gönderildi. Kişiye biraz sonra yeniden deneyebileceğini söyle.' );
	}
	$code    = str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
	$channel = '';
	$GLOBALS['flxc_verify_err'] = '';
	$errs                      = array();
	if ( $sandbox ) {
		$channel = 'email' === $type ? 'email' : flxc_verify_phone_channels()[0];
		// 1.18.0: benchmark senaryosu telefon kanalını seçebilir (SMS ya da WhatsApp kodu).
		if ( 'email' !== $type && in_array( $state['test_channel'] ?? '', array( 'sms', 'whatsapp' ), true ) ) {
			$channel = $state['test_channel'];
		}
		if ( '000' === substr( $value, -3 ) ) {
			$channel = ''; // test sohbeti: 000 ile biten değer "gönderilemedi" sayılır
		} else {
			// Hiçbir şey gönderilmez; kod test panelinde görünür (Arka plan › Doğrulama kodu).
			$state['vc_test']  = array( 'code' => $code, 'value' => $value, 'channel' => $channel, 'exp' => time() + flxc_verify_ttl() * MINUTE_IN_SECONDS );
		}
	} elseif ( 'email' === $type ) {
		$channel = flxc_ev_send_mail( $value, $code, '' !== $state['name'] ? $state['name'] : $state['full'] ) ? 'email' : '';
		if ( '' === $channel ) {
			$errs[] = __( 'e-mail could not be sent (check Flux Mail › SMTP settings)', 'flx-chat' );
		}
	} else {
		$text = flxc_verify_text( $code );
		foreach ( flxc_verify_phone_channels() as $ch ) {
			if ( 'whatsapp' === $ch ) {
				$ok = flxc_verify_send_whatsapp( $chat, $value, $text );
			} else {
				$r  = flxi_send_message( 'sms', '+' . $value, $text, array( 'source' => 'flx-chat-verify', 'log' => false ) );
				$ok = ! empty( $r['ok'] );
				if ( ! $ok ) {
					$GLOBALS['flxc_verify_err'] = (string) ( $r['error'] ?? '' );
				}
			}
			if ( $ok ) {
				$channel = $ch;
				break;
			}
			$errs[] = flxc_verify_channel_label( $ch ) . ': ' . ( '' !== $GLOBALS['flxc_verify_err'] ? $GLOBALS['flxc_verify_err'] : __( 'not sent', 'flx-chat' ) );
		}
	}
	if ( '' === $channel ) {
		if ( ! $sandbox ) {
			flxc_verify_stat( array( 'vc_fail' => 1 ) );
		}
		$no_wa = false !== strpos( implode( ' ', $errs ), __( 'This number is not on WhatsApp.', 'flx-chat' ) );
		return flxc_verify_fail(
			$chat,
			$state,
			$sandbox,
			( 'email' === $type ? 'E-posta' : 'Kod mesajı' ) . ' gönderilemedi' . ( $no_wa ? ' (bu numarada WhatsApp yok; numarayı kontrol etmesini iste)' : '' ) . '. Kişiye kısaca özür dile ve diğer yolu (telefon/e-posta) öner; ekibe aktarma.',
			( 'email' === $type ? $value : flxc_format_phone( $value ) ) . ' — ' . ( $errs ? implode( '; ', $errs ) : __( 'not sent', 'flx-chat' ) )
		);
	}
	$sent[]           = $now;
	$state['vc_sent'] = $sent;
	$state['vc']      = array(
		'type'    => $type,
		'value'   => $value,
		'channel' => $channel,
		'hash'    => flxc_verify_hash( $code, (int) ( $chat['id'] ?? 0 ), $type, $value ),
		'exp'     => $now + $ttl * MINUTE_IN_SECONDS,
		'tries'   => 0,
	);
	if ( ! $sandbox ) {
		flxc_verify_stat( array( 'vc_' . $channel => 1 ) );
		$shown = 'email' === $type ? $value : flxc_format_phone( $value );
		/* translators: 1: e-mail or phone, 2: channel */
		flxc_system_message( $chat, sprintf( __( 'The assistant sent a verification code for %1$s (%2$s).', 'flx-chat' ), $shown, flxc_verify_channel_label( $channel ) ) );
	}
	$where = array(
		'email'    => 'e-postasına gelen (spam klasörüne de bakmasını söyle)',
		'whatsapp' => 'o numaranın WhatsApp\'ına gelen',
		'sms'      => 'o numaraya SMS ile gelen',
	);
	return array(
		'ok'      => true,
		'channel' => $channel,
		'to'      => 'email' === $type ? $value : flxc_format_phone( $value ),
		// 1.20.0 (Ahmet): kod nereye gittiyse kişiye aynen yazılır; yanlış yazdıysa hemen düzeltebilir.
		'note'    => 'Kod gönderildi (' . $ttl . ' dk geçerli). Kişiye kodun "to" alanındaki ' . ( 'email' === $type ? 'adrese' : 'numaraya' ) . ' gittiğini bu adresi/numarayı aynen yazarak söyle ve ' . $where[ $channel ] . ' 6 haneli kodu buraya yazmasını iste; yanlışsa doğrusunu yazabileceğini ekle. Kodu sen bilmezsin.',
	);
}

function flxc_verify_channel_label( $ch ) {
	$l = array(
		'email'    => __( 'e-mail', 'flx-chat' ),
		'whatsapp' => 'WhatsApp',
		'sms'      => 'SMS',
	);
	return $l[ $ch ] ?? $ch;
}

/**
 * Kodu kontrol eder; doğruysa e-posta/telefon sohbete bağlanır.
 *
 * @return array ok, type, value, error, note
 */
function flxc_verify_check( $chat, $code, array &$state, $sandbox = false ) {
	$now = time();
	if ( flxc_verify_locked( $state ) ) {
		return array( 'ok' => false, 'error' => 'Doğrulama bu kişi için geçici olarak kapalı; ekip iletişime geçecek. Kayıt bilgisi verme.' );
	}
	$vc  = (array) ( $state['vc'] ?? array() );
	if ( empty( $vc['hash'] ) ) {
		return array( 'ok' => false, 'error' => 'Gönderilmiş bir kod yok. Kişi isterse verify_send ile kod gönder.' );
	}
	if ( (int) $vc['exp'] < $now ) {
		unset( $state['vc'] );
		if ( ! $sandbox ) {
			flxc_verify_stat( array( 'vc_expired' => 1 ) );
		}
		return flxc_verify_fail( $chat, $state, $sandbox, 'Kodun süresi doldu (' . flxc_verify_ttl() . ' dk). Kişi isterse verify_send ile yeni kod gönder.', __( 'code expired', 'flx-chat' ) );
	}
	if ( (int) $vc['tries'] >= 5 ) {
		return array( 'ok' => false, 'error' => 'Çok fazla yanlış deneme. Yeni kod gönderilmesi gerekiyor.' );
	}
	$code = preg_replace( '/\D/', '', (string) $code );
	if ( 6 !== strlen( $code ) || ! hash_equals( $vc['hash'], flxc_verify_hash( $code, (int) ( $chat['id'] ?? 0 ), $vc['type'], $vc['value'] ) ) ) {
		$vc['tries'] = (int) $vc['tries'] + 1;
		$state['vc'] = $vc;
		if ( ! $sandbox ) {
			flxc_verify_stat( array( 'vc_wrong' => 1 ) );
		}
		return flxc_verify_fail( $chat, $state, $sandbox, 'Kod yanlış. Kişiye kodun yanlış olduğunu söyle ve gelen 6 haneli kodu kontrol edip yeniden yazmasını iste; yeni kod gönderme, kayıt bilgisi verme.', __( 'wrong code', 'flx-chat' ) );
	}
	unset( $state['vc'] );
	$state['vc_fails'] = 0;
	$mem = function_exists( 'flxc_mem_on' ) && flxc_mem_on() && ! $sandbox;
	if ( 'email' === $vc['type'] ) {
		$state['emails_ok'] = array_values( array_unique( array_merge( flxc_ev_emails( $state ), array( $vc['value'] ) ) ) );
		if ( '' === $state['email'] ) {
			$state['email'] = $vc['value'];
		}
		if ( $mem ) {
			flxc_mem_set( $chat, 'doğrulanmış e-posta', implode( ', ', $state['emails_ok'] ), 'bot' );
		}
	} else {
		$state['phones_ok'] = array_values( array_unique( array_merge( flxc_verify_phones( $state ), array( $vc['value'] ) ) ) );
		if ( $mem ) {
			flxc_mem_set( $chat, 'doğrulanmış telefon', implode( ', ', array_map( 'flxc_format_phone', $state['phones_ok'] ) ), 'bot' );
		}
	}
	if ( ! $sandbox ) {
		flxc_verify_stat( array( 'verified' => 1, 'vc_ok_' . ( 'email' === $vc['type'] ? 'email' : 'phone' ) => 1 ) );
		$shown = 'email' === $vc['type'] ? $vc['value'] : flxc_format_phone( $vc['value'] );
		/* translators: %s: e-mail or phone */
		flxc_system_message( $chat, sprintf( __( 'The person verified the code for %s; it is added to their record lookups.', 'flx-chat' ), $shown ) );
		do_action( 'flxc_verified', $chat, $vc['type'], $vc['value'] );
		if ( 'email' === $vc['type'] ) {
			do_action( 'flxc_email_verified', $chat, $vc['value'] );
		}
	}
	$ask = 'email' === $vc['type'] && ( ! isset( $state['email_notify'] ) || '' === $state['email_notify'] );
	return array(
		'ok'    => true,
		'type'  => $vc['type'],
		'value' => $vc['value'],
		'note'  => 'Doğrulandı. Kişinin kayıtları aşağıda (records); bunlarla kişinin sorusunu kendin cevapla, ekibe aktarma. Sertifikasını isterse certificate_resend.' . ( $ask ? ' Ayrıca bundan sonra bilgilendirmelerin bu e-postaya gönderilmesini isteyip istemediğini sor ve cevabı set_email_notify ile kaydet.' : '' ),
	);
}

/* ---------- asistan araçları ---------- */

function flxc_verify_tools( $f ) {
	$ttl = flxc_verify_ttl();
	$out = array(
		$f(
			'verify_send',
			'Kişinin kendi kayıtlarını görebilmesi (ya da personel numarasını doğrulaması) için verdiği telefon numarasına (WhatsApp, olmazsa SMS) ya da e-postasına 6 haneli doğrulama kodu gönderir; kod ' . $ttl . ' dk geçerli. Kodu sen bilmezsin.',
			array(
				'type'  => array( 'type' => 'string', 'enum' => array( 'phone', 'email' ) ),
				'value' => array( 'type' => 'string', 'description' => 'Telefon numarası ya da e-posta' ),
			),
			array( 'type', 'value' )
		),
		$f(
			'verify_check',
			'Kişinin yazdığı doğrulama kodunu kontrol eder. Doğruysa numara/e-posta bu kişiye bağlanır ve my_records onun kayıtlarını da getirir.',
			array( 'code' => array( 'type' => 'string' ) ),
			array( 'code' )
		),
	);
	if ( flxc_ev_on() ) {
		$out[] = $f(
			'set_email_notify',
			'Kişi bilgilendirmelerin (bağış makbuzu, sertifika, duyurular) e-postasına gelmesini isteyip istemediğini söyleyince kaydeder.',
			array( 'allow' => array( 'type' => 'boolean' ) ),
			array( 'allow' )
		);
	}
	return $out;
}

/** Asistanın bilmesi gereken kural. */
function flxc_verify_rule( $chat ) {
	$ttl    = flxc_verify_ttl();
	$hidden = '' === (string) $chat['phone'];
	return '- Kayıt sorgusu ve doğrulama: kişi kendi kayıtlarını sorarsa önce my_records çağır. '
		. ( $hidden
			? 'Bu kişinin numarası WhatsApp gizliliği nedeniyle bize görünmüyor; bu yüzden kayıtları numarasından bulunamaz. Önce telefon numarasını paylaşırsa kısa bir doğrulamayla kayıtlarını görebileceğini söyle; numarayı verirse verify_send type=phone (kod o numaranın WhatsApp\'ına gider). Telefon vermek istemezse ya da kayıt e-postayla yapıldıysa e-postasını iste, verify_send type=email. '
			: 'Numarası görünüyor; my_records bu numaranın kayıtlarını doğrudan getirir, ek doğrulama isteme. Kayıt çıkmazsa ve kişi başka bir numara ya da e-postayla bağış yaptığını söylerse o numarayı/e-postayı verify_send ile doğrula. ' )
		. 'Kişinin yazdığı kodu verify_check ile kontrol et; doğrulanmadan o numara/e-posta hakkında hiçbir şey söyleme. Kod ' . $ttl . ' dk geçerli. Kişi "telefon numaram yetmiyor mu", "neden" gibi itiraz ederse aynı cümleyi tekrarlama; nedenini kısaca açıkla ve telefon doğrulamasını teklif et.'
		. ( flxc_ev_on() ? ' E-posta doğrulanınca bilgilendirmelerin bu e-postaya gelmesini isteyip istemediğini sor ve set_email_notify ile kaydet; istemezse o e-postaya bir şey gönderilmez.' : '' );
}
