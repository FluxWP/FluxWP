<?php
/**
 * Bağışçı girişi: e-posta ya da telefona tek kullanımlık kod (mobil/masaüstü uygulama).
 *
 *   POST /flxapp/v1/otp/start  { login, client_id }        → { sent, via, via_label, ttl }
 *   POST /flxapp/v1/otp/verify { login, code, client_id }  → OAuth belirteç cevabı (access + refresh)
 *
 * Kod 6 hane, 10 dk geçerli, 5 deneme. Deneme sayacı hedefe bağlıdır; yeni kod istemek sıfırlamaz.
 * Yanıt, hesabın olup olmadığını belli etmez.
 * E-posta wp_mail ile; telefon Flux Chat (WhatsApp) varsa onunla. Başka kanal: filtre `flxapp_otp_send`.
 * Telefon kodu yalnız kayıtlı tam numarası (`flx_phone`) o numara olan hesaba girer.
 * Sipariş veya fatura bilgisinden hesap açılmaz ve bağlanmaz.
 * Yetkili personel (edit_posts) varsayılan olarak kodla giremez; OAuth (tarayıcı) kullanır.
 *
 * Kayıtlı bağışçı kendi numarasını kanıtlar: GET/POST/DELETE /flxapp/v1/me/phone.
 * Kod yalnız o telefona gider (WhatsApp ya da flxapp_otp_send). E-postaya düşmez.
 */

defined( 'ABSPATH' ) || exit;

const FLXAPP_OTP_TTL   = 600;
const FLXAPP_OTP_TRIES = 5;

/** Giriş değeri: e-posta ya da telefon (ülke kodlu tam rakam; yerelde varsayılan 90). */
function flxapp_otp_normalize( $login ) {
	$login = trim( (string) $login );
	if ( is_email( $login ) ) {
		return array( 'email', strtolower( $login ) );
	}
	if ( function_exists( 'flxr_normalize_phone' ) ) {
		$p = (string) flxr_normalize_phone( $login );
	} elseif ( function_exists( 'flxc_normalize_phone' ) ) {
		$p = (string) flxc_normalize_phone( $login );
	} else {
		$p = preg_replace( '/\D/', '', $login );
		if ( 0 === strpos( $p, '00' ) ) {
			$p = substr( $p, 2 );
		} elseif ( 11 === strlen( $p ) && '0' === $p[0] ) {
			$p = '90' . substr( $p, 1 );
		} elseif ( 10 === strlen( $p ) && '5' === $p[0] ) {
			$p = '90' . $p;
		}
		if ( strlen( $p ) < 10 || strlen( $p ) > 15 ) {
			$p = '';
		}
	}
	return '' !== $p && strlen( $p ) >= 10 && strlen( $p ) <= 15 ? array( 'phone', $p ) : array( '', '' );
}

/** Kayıtlı WhatsApp numarası (flx_phone) bu tam numaraya eşit mi? */
function flxapp_otp_phone_owner( $user, $value ) {
	if ( ! $user instanceof WP_User || ! function_exists( 'flxr_user_phone' ) || ! function_exists( 'flxr_normalize_phone' ) ) {
		return false;
	}
	$saved = flxr_normalize_phone( flxr_user_phone( $user->ID ) );
	$want  = flxr_normalize_phone( $value );
	return '' !== $saved && '' !== $want && $saved === $want;
}

/**
 * Mevcut hesabı bul. Telefon: yalnız kayıtlı tam numara. E-posta: hesabın kendi adresi.
 * Filtrenin döndürdüğü kullanıcı da aynı kurala uymazsa yok sayılır. Siparişten hesap açılmaz.
 */
function flxapp_otp_find_user( $type, $value ) {
	$user = null;
	if ( 'email' === $type ) {
		$found = get_user_by( 'email', $value );
		$user  = $found instanceof WP_User ? $found : null;
	} elseif ( 'phone' === $type && function_exists( 'flxr_user_by_phone' ) ) {
		$found = flxr_user_by_phone( $value );
		$user  = $found instanceof WP_User ? $found : null;
	}
	$user = apply_filters( 'flxapp_otp_find_user', $user, $type, $value );
	if ( ! $user instanceof WP_User ) {
		return null;
	}
	if ( 'email' === $type && strtolower( $user->user_email ) !== $value ) {
		return null;
	}
	if ( 'phone' === $type && ! flxapp_otp_phone_owner( $user, $value ) ) {
		return null;
	}
	return $user;
}

/**
 * Doğrulanmış e-posta için müşteri hesabı. Sipariş, fatura e-postası ve fatura telefonu kullanılmaz;
 * eski siparişler bağlanmaz.
 *
 * @param string $email Kodun gittiği adres.
 * @return WP_User|null
 */
function flxapp_otp_create_customer( $email ) {
	if ( ! is_email( $email ) ) {
		return null;
	}
	$existing = get_user_by( 'email', $email );
	if ( $existing instanceof WP_User ) {
		return $existing;
	}
	$local = sanitize_user( (string) strstr( $email, '@', true ), true );
	if ( '' === $local ) {
		$local = 'donor';
	}
	$id = wp_insert_user(
		array(
			'user_login' => $local . wp_rand( 1000, 9999 ),
			'user_email' => $email,
			'user_pass'  => wp_generate_password( 32 ),
			'role'       => get_role( 'customer' ) ? 'customer' : 'subscriber',
		)
	);
	return is_wp_error( $id ) ? null : get_userdata( $id );
}

/** Kodla girişe izin var mı (personel varsayılan olarak hayır). */
function flxapp_otp_allowed( WP_User $user ) {
	$ok = flxapp_opt( 'otp_staff' ) || ! user_can( $user, 'edit_posts' );
	return (bool) apply_filters( 'flxapp_otp_allowed', $ok, $user );
}

function flxapp_otp_key( $login ) {
	return 'flxapp_otp_' . flxapp_hash( 'otp|' . $login );
}

function flxapp_otp_code_hash( $code ) {
	return hash_hmac( 'sha256', (string) $code, wp_salt( 'auth' ) );
}

/** Sabit sürede karşılaştırır. Kayıtlı özet yoksa da aynı uzunlukta sahte özete bakar. */
function flxapp_otp_code_matches( $code, $hash ) {
	$given  = flxapp_otp_code_hash( $code );
	$expect = is_string( $hash ) && strlen( $hash ) === strlen( $given ) ? $hash : str_repeat( '0', strlen( $given ) );
	return hash_equals( $expect, $given );
}

function flxapp_otp_fail_key( $id ) {
	return 'flxapp_otpf_' . flxapp_hash( 'fail|' . $id );
}

/** Hedefin penceredeki başarısız doğrulama sayısı. Yeni kod bunu silmez. */
function flxapp_otp_fail_count( $id ) {
	$row = get_transient( flxapp_otp_fail_key( $id ) );
	if ( ! is_array( $row ) || (int) ( $row['until'] ?? 0 ) <= time() ) {
		return 0;
	}
	return (int) $row['n'];
}

function flxapp_otp_fail_hit( $id ) {
	$key = flxapp_otp_fail_key( $id );
	$row = get_transient( $key );
	$now = time();
	if ( ! is_array( $row ) || (int) ( $row['until'] ?? 0 ) <= $now ) {
		$row = array(
			'n'     => 0,
			'until' => $now + FLXAPP_OTP_TTL,
		);
	}
	$row['n'] = (int) $row['n'] + 1;
	set_transient( $key, $row, max( 1, (int) $row['until'] - $now ) );
	return (int) $row['n'];
}

function flxapp_otp_fail_clear( $id ) {
	delete_transient( flxapp_otp_fail_key( $id ) );
}

/**
 * Başarısız doğrulamayı hem hedefe hem koda yazar. $burn true ise kodu hemen düşürür.
 *
 * @param array<string,mixed> $d
 */
function flxapp_otp_note_failure( $value, $uid, $key, array $d, $burn = false ) {
	flxapp_otp_fail_hit( 'login|' . $value );
	if ( $uid ) {
		flxapp_otp_fail_hit( 'user|' . $uid );
	}
	if ( ! $d ) {
		return;
	}
	$d['tries'] = (int) ( $d['tries'] ?? 0 ) + 1;
	$ttl        = isset( $d['exp'] ) ? (int) $d['exp'] - time() : FLXAPP_OTP_TTL;
	if ( $burn || $d['tries'] >= FLXAPP_OTP_TRIES || $ttl <= 0 ) {
		delete_transient( $key );
		return;
	}
	set_transient( $key, $d, $ttl );
}

/** Kodu gönderir. $user boş olabilir (henüz açılmamış e-posta hesabı). @return string|false kanal */
function flxapp_otp_deliver( ?WP_User $user, $type, $value, $code ) {
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	/* translators: 1: code, 2: site name */
	$text = sprintf( __( '%1$s is your sign-in code for the %2$s app. Valid for 10 minutes. Do not share this code with anyone.', 'flx-app' ), $code, $site );
	$via  = apply_filters( 'flxapp_otp_send', null, $user, $type, $value, $code, $text );
	if ( $via ) {
		return (string) $via;
	}
	// Chat numarayı yeniden yazarsa kod başka hatta gider; o zaman WhatsApp'a gönderme.
	$whatsapp = (string) $value;
	if ( 'phone' === $type && function_exists( 'flxc_normalize_phone' ) && (string) flxc_normalize_phone( $value ) !== $whatsapp ) {
		$whatsapp = '';
	}
	if ( 'phone' === $type && $user instanceof WP_User && '' !== $whatsapp && function_exists( 'flxc_send_private_text' ) ) {
		// Gizli gönderim: kod panelde görünmez, sohbette asistanı susturmaz.
		$r = flxc_send_private_text( $whatsapp, $text, array( 'source' => 'flxapp-otp' ) );
		if ( true === $r ) {
			return 'whatsapp';
		}
	} elseif ( 'phone' === $type && $user instanceof WP_User && '' !== $whatsapp && function_exists( 'flxc_send_text' ) ) {
		$r = flxc_send_text( $whatsapp, $text, array( 'source' => 'flxapp-otp', 'name' => $user->display_name ) );
		if ( ! is_wp_error( $r ) ) {
			return 'whatsapp';
		}
	}
	$email = 'email' === $type && is_email( $value ) ? $value : ( $user instanceof WP_User ? $user->user_email : '' );
	if ( is_email( $email ) && ( 'email' === $type || $user instanceof WP_User ) ) {
		/* translators: %s: site name */
		$subject = sprintf( __( '%s sign-in code', 'flx-app' ), $site );
		$body    = '<div style="font:16px/1.5 -apple-system,Segoe UI,sans-serif;max-width:420px">'
			. '<p>' . esc_html__( 'Your app sign-in code:', 'flx-app' ) . '</p>'
			. '<p style="font-size:32px;letter-spacing:8px;font-weight:700;margin:16px 0">' . esc_html( $code ) . '</p>'
			. '<p style="color:#5f6b64">' . esc_html__( 'The code is valid for 10 minutes. If you did not request it, ignore this email.', 'flx-app' ) . '</p></div>';
		if ( wp_mail( $email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) ) ) {
			return 'email';
		}
	}
	return false;
}

function flxapp_otp_via_label( $via ) {
	$l = array(
		'email'    => __( 'Email', 'flx-app' ),
		'whatsapp' => 'WhatsApp',
		'sms'      => 'SMS',
	);
	return $l[ $via ] ?? $via;
}

function flxapp_rest_otp_start( WP_REST_Request $req ) {
	if ( ! flxapp_opt( 'otp_enabled' ) ) {
		return new WP_Error( 'flxapp_otp_off', __( 'Sign in with code is turned off.', 'flx-app' ), array( 'status' => 403 ) );
	}
	if ( ! flxapp_client( (string) $req->get_param( 'client_id' ) ) ) {
		return flxapp_oauth_error( 'invalid_client', '', 401 );
	}
	list( $type, $value ) = flxapp_otp_normalize( $req->get_param( 'login' ) );
	if ( '' === $type ) {
		return new WP_Error( 'flxapp_otp_login', __( 'Enter a valid email or phone number.', 'flx-app' ), array( 'status' => 400 ) );
	}
	// Çağıran (IP) ve hedef (e-posta veya telefon) ayrı. IP değiştirmek hedef sınırını sıfırlamaz.
	if ( ! flxapp_rate( 'otp_ip', 6, 10 * MINUTE_IN_SECONDS ) || ! flxapp_rate( 'otp_login|' . $value, 3, 10 * MINUTE_IN_SECONDS, false ) ) {
		return new WP_Error( 'flxapp_rate', __( 'Too many attempts. Try again in a little while.', 'flx-app' ), array( 'status' => 429 ) );
	}
	$via  = 'phone' === $type ? ( function_exists( 'flxc_send_text' ) ? 'whatsapp' : 'email' ) : 'email';
	$user = flxapp_otp_find_user( $type, $value );
	$uid  = $user instanceof WP_User ? (int) $user->ID : 0;
	if ( $uid && ! flxapp_rate( 'otp_user|' . $uid, 3, 10 * MINUTE_IN_SECONDS, false ) ) {
		return new WP_Error( 'flxapp_rate', __( 'Too many attempts. Try again in a little while.', 'flx-app' ), array( 'status' => 429 ) );
	}
	$fails = flxapp_otp_fail_count( 'login|' . $value );
	if ( $uid ) {
		$fails = max( $fails, flxapp_otp_fail_count( 'user|' . $uid ) );
	}
	// Kilitliyken yeni kod yazılmaz ve başarısız deneme sayısı durur.
	if ( $fails >= FLXAPP_OTP_TRIES ) {
		return new WP_Error( 'flxapp_rate', __( 'Too many attempts. Try again in a little while.', 'flx-app' ), array( 'status' => 429 ) );
	}
	$pending = ! $user && 'email' === $type && flxapp_opt( 'otp_create' );
	if ( ( $user && flxapp_otp_allowed( $user ) ) || $pending ) {
		$code = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
		$sent = flxapp_otp_deliver( $user instanceof WP_User ? $user : null, $type, $value, $code );
		if ( $sent ) {
			$via = $sent;
			set_transient(
				flxapp_otp_key( $value ),
				array(
					'user'    => $uid,
					'pending' => $pending ? 1 : 0,
					'hash'    => flxapp_otp_code_hash( $code ),
					'tries'   => $fails,
					'exp'     => time() + FLXAPP_OTP_TTL,
					'client'  => (string) $req->get_param( 'client_id' ),
				),
				FLXAPP_OTP_TTL
			);
		}
	}
	// Hesap olsun olmasın aynı cevap (hesap varlığı sızmasın).
	return array(
		'sent'      => true,
		'via'       => $via,
		'via_label' => flxapp_otp_via_label( $via ),
		'ttl'       => FLXAPP_OTP_TTL,
	);
}

function flxapp_rest_otp_verify( WP_REST_Request $req ) {
	$client = flxapp_client( (string) $req->get_param( 'client_id' ) );
	if ( ! $client ) {
		return flxapp_oauth_error( 'invalid_client', '', 401 );
	}
	list( $type, $value ) = flxapp_otp_normalize( $req->get_param( 'login' ) );
	$code = preg_replace( '/\D/', '', (string) $req->get_param( 'code' ) );
	$bad  = new WP_Error( 'flxapp_otp_code', __( 'The code is wrong or has expired.', 'flx-app' ), array( 'status' => 400 ) );
	if ( ! flxapp_rate( 'otp_verify', 20, 10 * MINUTE_IN_SECONDS ) ) {
		return new WP_Error( 'flxapp_rate', __( 'Too many attempts. Try again in a little while.', 'flx-app' ), array( 'status' => 429 ) );
	}
	if ( '' === $type ) {
		return $bad;
	}
	if ( ! flxapp_rate( 'otp_verify|' . $value, FLXAPP_OTP_TRIES, 10 * MINUTE_IN_SECONDS, false ) ) {
		return new WP_Error( 'flxapp_rate', __( 'Too many attempts. Try again in a little while.', 'flx-app' ), array( 'status' => 429 ) );
	}
	$key = flxapp_otp_key( $value );
	$raw = get_transient( $key );
	$d   = is_array( $raw ) ? $raw : array();
	$uid = (int) ( $d['user'] ?? 0 );
	if ( $uid && ! flxapp_rate( 'otp_verify_user|' . $uid, FLXAPP_OTP_TRIES, 10 * MINUTE_IN_SECONDS, false ) ) {
		return new WP_Error( 'flxapp_rate', __( 'Too many attempts. Try again in a little while.', 'flx-app' ), array( 'status' => 429 ) );
	}
	$locked    = flxapp_otp_fail_count( 'login|' . $value ) >= FLXAPP_OTP_TRIES
		|| ( $uid && flxapp_otp_fail_count( 'user|' . $uid ) >= FLXAPP_OTP_TRIES )
		|| (int) ( $d['tries'] ?? 0 ) >= FLXAPP_OTP_TRIES;
	$client_ok = isset( $d['client'] ) && hash_equals( hash( 'sha256', (string) $d['client'] ), hash( 'sha256', (string) $client['id'] ) );
	$match     = flxapp_otp_code_matches( $code, isset( $d['hash'] ) ? (string) $d['hash'] : '' );
	if ( $locked || ! $match || ! $client_ok || ( ! $uid && empty( $d['pending'] ) ) ) {
		flxapp_otp_note_failure( $value, $uid, $key, $d );
		return $bad;
	}
	if ( ! flxapp_client_usable( $client ) ) {
		return flxapp_oauth_error( 'unauthorized_client', 'app_not_allowed', 403 );
	}
	if ( ! $uid && ! empty( $d['pending'] ) && 'email' === $type && flxapp_opt( 'otp_create' ) ) {
		$created = flxapp_otp_create_customer( $value );
		$uid     = $created instanceof WP_User ? (int) $created->ID : 0;
	}
	$user = $uid ? get_userdata( $uid ) : null;
	$owns = $user instanceof WP_User && flxapp_otp_allowed( $user )
		&& ( 'email' !== $type || strtolower( $user->user_email ) === $value )
		&& ( 'phone' !== $type || flxapp_otp_phone_owner( $user, $value ) );
	if ( ! $owns ) {
		flxapp_otp_note_failure( $value, $uid, $key, $d, true );
		return $bad;
	}
	delete_transient( $key );
	flxapp_otp_fail_clear( 'login|' . $value );
	flxapp_otp_fail_clear( 'user|' . $user->ID );
	$app_id = flxapp_app_id_for( $client, (string) $req->get_param( 'app_id' ) );
	do_action( 'flxapp_otp_login', $user->ID, $type );
	return flxapp_token_pair( $client['id'], $user->ID, 'flx', strtolower( wp_generate_password( 24, false ) ), $app_id );
}

const FLXAPP_PHONE_SELF_AT  = 'flx_phone_self_at';
const FLXAPP_PHONE_SELF_KEY = 'flx_phone_self_key';

/** Görünen kuyruk: 8+ hanede son 4, daha kısada son 2. Tam numara dönmez. */
function flxapp_phone_tail( $phone ) {
	$phone = preg_replace( '/\D/', '', (string) $phone );
	$n     = strlen( $phone );
	if ( $n < 2 ) {
		return '';
	}
	$show = $n >= 8 ? 4 : 2;
	return substr( $phone, -$show );
}

function flxapp_phone_mask( $phone ) {
	$phone = preg_replace( '/\D/', '', (string) $phone );
	$tail  = flxapp_phone_tail( $phone );
	if ( '' === $tail ) {
		return '';
	}
	return str_repeat( '•', strlen( $phone ) - strlen( $tail ) ) . $tail;
}

/** Kod (kullanıcı, telefon) ikilisine bağlı. Başka hesaba ya da başka numaraya taşınamaz. */
function flxapp_phone_code_hash( $uid, $phone, $code ) {
	return hash_hmac( 'sha256', (int) $uid . '|' . $phone . '|' . (string) $code, wp_salt( 'auth' ) );
}

function flxapp_phone_code_matches( $uid, $phone, $code, $hash ) {
	$given  = flxapp_phone_code_hash( $uid, $phone, $code );
	$expect = is_string( $hash ) && strlen( $hash ) === strlen( $given ) ? $hash : str_repeat( '0', strlen( $given ) );
	return hash_equals( $expect, $given );
}

function flxapp_phone_code_key( $uid, $phone ) {
	return 'flxapp_ph_' . flxapp_hash( 'self|' . (int) $uid . '|' . $phone );
}

/** Deneme sayaçları: kullanıcı, numara ve çağıran. Yeni kod bunları silmez. */
function flxapp_phone_fail_ids( $uid, $phone, $caller ) {
	$caller = '' !== (string) $caller ? (string) $caller : 'unknown';
	return array(
		'selfu|' . (int) $uid,
		'selfp|' . $phone,
		'selfc|' . $caller,
	);
}

function flxapp_phone_fail_max( $uid, $phone, $caller ) {
	$n = 0;
	foreach ( flxapp_phone_fail_ids( $uid, $phone, $caller ) as $id ) {
		$n = max( $n, flxapp_otp_fail_count( $id ) );
	}
	return $n;
}

function flxapp_phone_fail_note( $uid, $phone, $caller ) {
	foreach ( flxapp_phone_fail_ids( $uid, $phone, $caller ) as $id ) {
		flxapp_otp_fail_hit( $id );
	}
}

function flxapp_phone_fail_clear_all( $uid, $phone, $caller ) {
	foreach ( flxapp_phone_fail_ids( $uid, $phone, $caller ) as $id ) {
		flxapp_otp_fail_clear( $id );
	}
}

/**
 * Başarısız doğrulama. $burn true ise kodu hemen düşürür (başka hesaba ait numara).
 *
 * @param array<string,mixed> $d
 */
function flxapp_phone_note_failure( $uid, $phone, $caller, $key, array $d, $burn = false ) {
	flxapp_phone_fail_note( $uid, $phone, $caller );
	if ( ! $d ) {
		return;
	}
	$d['tries'] = (int) ( $d['tries'] ?? 0 ) + 1;
	$ttl        = isset( $d['exp'] ) ? (int) $d['exp'] - time() : FLXAPP_OTP_TTL;
	if ( $burn || $d['tries'] >= FLXAPP_OTP_TRIES || $ttl <= 0 ) {
		delete_transient( $key );
		return;
	}
	set_transient( $key, $d, $ttl );
}

function flxapp_phone_unavailable() {
	return new WP_Error( 'flxapp_phone_unavailable', __( 'Phone confirmation is not available.', 'flx-app' ), array( 'status' => 403 ) );
}

/** Ayar açık, bağışçı kodla girebilir ve numara yazıcıları var. */
function flxapp_phone_self_open( WP_User $user ) {
	if ( ! flxapp_opt( 'phone_self_verify' ) || ! flxapp_otp_allowed( $user ) ) {
		return false;
	}
	return function_exists( 'flxr_normalize_phone' ) && function_exists( 'flxr_set_user_phone' ) && function_exists( 'flxr_user_by_phone' ) && function_exists( 'flxr_user_phone' );
}

/** WhatsApp (gizli gönderim) ya da flxapp_otp_send kayıtlı. E-posta kanal sayılmaz. */
function flxapp_phone_channel_ready() {
	return function_exists( 'flxc_send_private_text' ) || function_exists( 'flxc_send_text' ) || has_filter( 'flxapp_otp_send' );
}

function flxapp_phone_self_current( $uid ) {
	$phone = function_exists( 'flxr_user_phone' ) && function_exists( 'flxr_normalize_phone' ) ? flxr_normalize_phone( flxr_user_phone( $uid ) ) : '';
	$key   = (string) get_user_meta( (int) $uid, FLXAPP_PHONE_SELF_KEY, true );
	$at    = (int) get_user_meta( (int) $uid, FLXAPP_PHONE_SELF_AT, true );
	$self  = '' !== $phone && $phone === $key && $at > 0;
	return array( $phone, $self );
}

/**
 * Kodu yalnız kanıtlanan numaraya gönderir. Filtre e-posta derse yok sayılır.
 *
 * @return string|false kanal
 */
function flxapp_phone_send_code( WP_User $user, $phone, $code ) {
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	/* translators: 1: code, 2: site name */
	$text = sprintf( __( '%1$s is your code to confirm the phone number on %2$s. Valid for 10 minutes. Do not share this code with anyone.', 'flx-app' ), $code, $site );
	$via  = apply_filters( 'flxapp_otp_send', null, $user, 'phone', $phone, $code, $text );
	if ( is_string( $via ) && '' !== $via && 'email' !== $via ) {
		return $via;
	}
	$whatsapp = (string) $phone;
	if ( function_exists( 'flxc_normalize_phone' ) && (string) flxc_normalize_phone( $phone ) !== $whatsapp ) {
		$whatsapp = '';
	}
	if ( '' !== $whatsapp && function_exists( 'flxc_send_private_text' ) ) {
		$r = flxc_send_private_text( $whatsapp, $text, array( 'source' => 'flxapp-phone' ) );
		if ( true === $r ) {
			return 'whatsapp';
		}
	} elseif ( '' !== $whatsapp && function_exists( 'flxc_send_text' ) ) {
		$r = flxc_send_text( $whatsapp, $text, array( 'source' => 'flxapp-phone', 'name' => $user->display_name ) );
		if ( ! is_wp_error( $r ) ) {
			return 'whatsapp';
		}
	}
	return false;
}

/** Hesap e-postasına kısa uyarı. Tam numara ve kod yazılmaz. */
function flxapp_phone_notify( WP_User $user, $kind, $phone ) {
	if ( ! is_email( $user->user_email ) ) {
		return;
	}
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$tail = flxapp_phone_tail( $phone );
	if ( 'changed' === $kind ) {
		/* translators: %s: site name */
		$subject = sprintf( __( 'The phone number on your %s account was changed', 'flx-app' ), $site );
		/* translators: %s: last digits of the phone number */
		$body = sprintf( __( 'The phone number on your account now ends in %s. If you did not do this, contact the site administrator.', 'flx-app' ), $tail );
	} elseif ( 'removed' === $kind ) {
		/* translators: %s: site name */
		$subject = sprintf( __( 'The phone number was removed from your %s account', 'flx-app' ), $site );
		/* translators: %s: last digits of the phone number */
		$body = sprintf( __( 'The phone number ending in %s was removed from your account. If you did not do this, contact the site administrator.', 'flx-app' ), $tail );
	} else {
		/* translators: %s: site name */
		$subject = sprintf( __( 'A phone number was added to your %s account', 'flx-app' ), $site );
		/* translators: %s: last digits of the phone number */
		$body = sprintf( __( 'A phone number ending in %s was added to your account. If you did not do this, contact the site administrator.', 'flx-app' ), $tail );
	}
	wp_mail( $user->user_email, $subject, $body );
}

function flxapp_rest_phone_get() {
	$user = wp_get_current_user();
	if ( ! $user instanceof WP_User ) {
		return flxapp_phone_unavailable();
	}
	list( $phone, $self ) = flxapp_phone_self_current( $user->ID );
	$open = flxapp_phone_self_open( $user ) && flxapp_phone_channel_ready();
	return array(
		'phone'         => flxapp_phone_mask( $phone ),
		'available'     => $open,
		'self_verified' => $self,
	);
}

function flxapp_rest_phone_start( WP_REST_Request $req ) {
	$user = wp_get_current_user();
	if ( ! $user instanceof WP_User || ! flxapp_phone_self_open( $user ) ) {
		return flxapp_phone_unavailable();
	}
	$phone = flxr_normalize_phone( $req->get_param( 'phone' ) );
	if ( '' === $phone ) {
		return new WP_Error( 'flxapp_phone', __( 'Enter a valid phone number.', 'flx-app' ), array( 'status' => 400 ) );
	}
	$uid    = (int) $user->ID;
	$caller = flxapp_ip();
	if ( ! flxapp_rate( 'phone_ip', 6, 10 * MINUTE_IN_SECONDS ) || ! flxapp_rate( 'phone_num|' . $phone, 3, 10 * MINUTE_IN_SECONDS, false ) || ! flxapp_rate( 'phone_user|' . $uid, 3, 10 * MINUTE_IN_SECONDS, false ) ) {
		return new WP_Error( 'flxapp_rate', __( 'Too many attempts. Try again in a little while.', 'flx-app' ), array( 'status' => 429 ) );
	}
	$fails = flxapp_phone_fail_max( $uid, $phone, $caller );
	if ( $fails >= FLXAPP_OTP_TRIES ) {
		return new WP_Error( 'flxapp_rate', __( 'Too many attempts. Try again in a little while.', 'flx-app' ), array( 'status' => 429 ) );
	}
	if ( ! flxapp_phone_channel_ready() ) {
		return flxapp_phone_unavailable();
	}
	$code = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
	$via  = flxapp_phone_send_code( $user, $phone, $code );
	if ( ! $via ) {
		return flxapp_phone_unavailable();
	}
	set_transient(
		flxapp_phone_code_key( $uid, $phone ),
		array(
			'user'  => $uid,
			'hash'  => flxapp_phone_code_hash( $uid, $phone, $code ),
			'tries' => $fails,
			'exp'   => time() + FLXAPP_OTP_TTL,
		),
		FLXAPP_OTP_TTL
	);
	return array(
		'sent'      => true,
		'via'       => $via,
		'via_label' => flxapp_otp_via_label( $via ),
		'ttl'       => FLXAPP_OTP_TTL,
	);
}

function flxapp_rest_phone_verify( WP_REST_Request $req ) {
	$user = wp_get_current_user();
	if ( ! $user instanceof WP_User || ! flxapp_phone_self_open( $user ) ) {
		return flxapp_phone_unavailable();
	}
	$phone = flxr_normalize_phone( $req->get_param( 'phone' ) );
	$code  = preg_replace( '/\D/', '', (string) $req->get_param( 'code' ) );
	$bad   = new WP_Error( 'flxapp_phone_code', __( 'The code is wrong or has expired.', 'flx-app' ), array( 'status' => 400 ) );
	if ( '' === $phone ) {
		return new WP_Error( 'flxapp_phone', __( 'Enter a valid phone number.', 'flx-app' ), array( 'status' => 400 ) );
	}
	$uid    = (int) $user->ID;
	$caller = flxapp_ip();
	if ( ! flxapp_rate( 'phone_verify', 20, 10 * MINUTE_IN_SECONDS ) || ! flxapp_rate( 'phone_verify|' . $phone, FLXAPP_OTP_TRIES, 10 * MINUTE_IN_SECONDS, false ) || ! flxapp_rate( 'phone_verify_user|' . $uid, FLXAPP_OTP_TRIES, 10 * MINUTE_IN_SECONDS, false ) ) {
		return new WP_Error( 'flxapp_rate', __( 'Too many attempts. Try again in a little while.', 'flx-app' ), array( 'status' => 429 ) );
	}
	$key    = flxapp_phone_code_key( $uid, $phone );
	$raw    = get_transient( $key );
	$d      = is_array( $raw ) ? $raw : array();
	$locked = flxapp_phone_fail_max( $uid, $phone, $caller ) >= FLXAPP_OTP_TRIES || (int) ( $d['tries'] ?? 0 ) >= FLXAPP_OTP_TRIES;
	$bound  = $d && (int) ( $d['user'] ?? 0 ) === $uid;
	$match  = $bound && flxapp_phone_code_matches( $uid, $phone, $code, isset( $d['hash'] ) ? (string) $d['hash'] : '' );
	// Yanlış kodda da bakılır; doğru kodun süresi aramayı ele vermesin.
	$other  = flxr_user_by_phone( $phone );
	$taken  = $match && ! $locked && $other instanceof WP_User && (int) $other->ID !== $uid;
	if ( ! $d || $locked || ! $match || $taken ) {
		flxapp_phone_note_failure( $uid, $phone, $caller, $key, $d, $taken );
		return $bad;
	}
	$prev = flxr_normalize_phone( flxr_user_phone( $uid ) );
	$kind = '' !== $prev && $prev !== $phone ? 'changed' : 'added';
	$saved = flxr_set_user_phone( $uid, $phone );
	if ( is_wp_error( $saved ) ) {
		if ( 'flxr_phone_taken' === $saved->get_error_code() ) {
			flxapp_phone_note_failure( $uid, $phone, $caller, $key, $d, true );
			return $bad;
		}
		return new WP_Error( 'flxapp_phone', __( 'The phone number could not be saved.', 'flx-app' ), array( 'status' => 500 ) );
	}
	delete_transient( $key );
	flxapp_phone_fail_clear_all( $uid, $phone, $caller );
	update_user_meta( $uid, FLXAPP_PHONE_SELF_AT, time() );
	update_user_meta( $uid, FLXAPP_PHONE_SELF_KEY, $phone );
	do_action( 'flxapp_phone_self_verified', $uid, $phone );
	flxapp_phone_notify( $user, $kind, $phone );
	return array(
		'saved' => true,
		'phone' => flxapp_phone_mask( $phone ),
	);
}

function flxapp_rest_phone_delete() {
	$user = wp_get_current_user();
	if ( ! $user instanceof WP_User || ! flxapp_phone_self_open( $user ) ) {
		return flxapp_phone_unavailable();
	}
	$uid = (int) $user->ID;
	list( $phone, $self ) = flxapp_phone_self_current( $uid );
	if ( '' === $phone ) {
		return new WP_Error( 'flxapp_phone_none', __( 'No phone number is saved on this account.', 'flx-app' ), array( 'status' => 404 ) );
	}
	if ( ! $self ) {
		return new WP_Error( 'flxapp_phone_admin', __( 'An administrator manages this phone number.', 'flx-app' ), array( 'status' => 403 ) );
	}
	$saved = flxr_set_user_phone( $uid, '' );
	if ( is_wp_error( $saved ) || true !== $saved ) {
		return new WP_Error( 'flxapp_phone', __( 'The phone number could not be saved.', 'flx-app' ), array( 'status' => 500 ) );
	}
	delete_user_meta( $uid, FLXAPP_PHONE_SELF_AT );
	delete_user_meta( $uid, FLXAPP_PHONE_SELF_KEY );
	delete_transient( flxapp_phone_code_key( $uid, $phone ) );
	flxapp_phone_notify( $user, 'removed', $phone );
	return array( 'removed' => true );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route( 'flxapp/v1', '/otp/start', array( 'methods' => 'POST', 'callback' => 'flxapp_rest_otp_start', 'permission_callback' => '__return_true' ) );
		register_rest_route( 'flxapp/v1', '/otp/verify', array( 'methods' => 'POST', 'callback' => 'flxapp_rest_otp_verify', 'permission_callback' => '__return_true' ) );
		register_rest_route( 'flxapp/v1', '/me/phone', array( 'methods' => 'GET', 'callback' => 'flxapp_rest_phone_get', 'permission_callback' => 'flxapp_logged_in' ) );
		register_rest_route( 'flxapp/v1', '/me/phone/start', array( 'methods' => 'POST', 'callback' => 'flxapp_rest_phone_start', 'permission_callback' => 'flxapp_logged_in' ) );
		register_rest_route( 'flxapp/v1', '/me/phone/verify', array( 'methods' => 'POST', 'callback' => 'flxapp_rest_phone_verify', 'permission_callback' => 'flxapp_logged_in' ) );
		register_rest_route( 'flxapp/v1', '/me/phone', array( 'methods' => 'DELETE', 'callback' => 'flxapp_rest_phone_delete', 'permission_callback' => 'flxapp_logged_in' ) );
	}
);
