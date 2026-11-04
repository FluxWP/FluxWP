<?php
/**
 * wp_mail üzerinden giden her e-postayı yakalar:
 * kimin gönderdiğini bulur (şablon anahtarı), kapalıysa durdurur, gönderen adını/adresini ayarlar,
 * düz metni kurumsal şablona sarar, SMTP'yi ayarlar ve kayıt tutar.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/** Gönderilmekte olan e-postanın bağlamı (wp_mail_from, phpmailer_init vb. için). */
$GLOBALS['flxi_current'] = null;

add_filter( 'wp_mail', 'flxi_filter_wp_mail', 1000 );
add_filter( 'pre_wp_mail', 'flxi_pre_wp_mail', 1, 2 );
add_filter( 'pre_wp_mail', 'flxi_pre_wp_mail_after', PHP_INT_MAX, 2 );
add_filter( 'wp_mail_from', 'flxi_filter_from', 999 );
add_filter( 'wp_mail_from_name', 'flxi_filter_from_name', 999 );
add_filter( 'wp_mail_content_type', 'flxi_filter_content_type', 999 );
add_action( 'phpmailer_init', 'flxi_phpmailer_init', 999 );
add_action( 'wp_mail_succeeded', 'flxi_mail_succeeded' );
add_action( 'wp_mail_failed', 'flxi_mail_failed' );

/** Başlıkları satır dizisine çevirir. */
function flxi_header_lines( $headers ) {
	if ( empty( $headers ) ) {
		return array();
	}
	if ( ! is_array( $headers ) ) {
		$headers = explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
	}
	return array_values( array_filter( array_map( 'trim', $headers ), 'strlen' ) );
}

/** Başlık değerini döndürür (yoksa ''). */
function flxi_header_get( array $lines, $name ) {
	foreach ( $lines as $l ) {
		if ( 0 === stripos( $l, $name . ':' ) ) {
			return trim( substr( $l, strlen( $name ) + 1 ) );
		}
	}
	return '';
}

function flxi_header_remove( array $lines, $name ) {
	return array_values(
		array_filter(
			$lines,
			function ( $l ) use ( $name ) {
				return 0 !== stripos( $l, $name . ':' );
			}
		)
	);
}

/**
 * wp_mail'i kimin çağırdığını bulur.
 *
 * @return array source (eklenti klasörü / wordpress / tema), caller (fonksiyon), key
 */
function flxi_detect_caller() {
	$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 40 ); // phpcs:ignore
	$n     = count( $trace );
	for ( $i = 0; $i < $n; $i++ ) {
		if ( 'wp_mail' !== ( $trace[ $i ]['function'] ?? '' ) || isset( $trace[ $i ]['class'] ) ) {
			continue;
		}
		$file = wp_normalize_path( (string) ( $trace[ $i ]['file'] ?? '' ) );
		$j    = $i + 1;
		// Kendi gönderim fonksiyonlarımızı ve sarmalayıcıları atla, asıl çağıranı bul.
		while ( $j < $n && ( 0 === strpos( wp_normalize_path( (string) ( $trace[ $j - 1 ]['file'] ?? '' ) ), wp_normalize_path( FLXI_DIR ) ) ) ) {
			$file = wp_normalize_path( (string) ( $trace[ $j ]['file'] ?? '' ) );
			$j++;
		}
		$fn     = $trace[ $j ] ?? array();
		$caller = isset( $fn['function'] ) ? ( isset( $fn['class'] ) ? $fn['class'] . '::' . $fn['function'] : $fn['function'] ) : 'main';
		if ( false !== strpos( $caller, '{closure}' ) ) {
			$caller = '{closure}@' . basename( $file );
		}
		$source = flxi_source_from_file( $file );
		return array(
			'source' => $source,
			'caller' => $caller,
			'key'    => $source . ':' . $caller,
		);
	}
	return array( 'source' => 'unknown', 'caller' => '', 'key' => 'unknown' );
}

function flxi_source_from_file( $file ) {
	$file = wp_normalize_path( $file );
	$dirs = array(
		wp_normalize_path( WP_PLUGIN_DIR ) . '/',
		wp_normalize_path( WPMU_PLUGIN_DIR ) . '/',
	);
	foreach ( $dirs as $d ) {
		if ( 0 === strpos( $file, $d ) ) {
			$rest = substr( $file, strlen( $d ) );
			$slug = strtok( $rest, '/' );
			return preg_replace( '/\.php$/', '', (string) $slug );
		}
	}
	if ( 0 === strpos( $file, wp_normalize_path( get_theme_root() ) ) ) {
		return 'tema';
	}
	if ( false !== strpos( $file, '/wp-includes/' ) || false !== strpos( $file, '/wp-admin/' ) || '' === $file ) {
		return 'wordpress';
	}
	return 'unknown';
}

/** Çekirdek fonksiyonlarını şablon anahtarlarına eşler (çekirdek filtreleri işaretlemediyse). */
function flxi_core_caller_key( $caller, $to ) {
	$admin = strtolower( (string) get_option( 'admin_email' ) );
	$to    = strtolower( is_array( $to ) ? implode( ',', $to ) : (string) $to );
	switch ( $caller ) {
		case 'wp_new_user_notification':
			return false !== strpos( $to, $admin ) ? 'core/new_user_admin' : 'core/new_user_user';
		case 'retrieve_password':
			return 'core/password_reset';
		case 'wp_notify_postauthor':
			return 'core/comment_author';
		case 'wp_notify_moderator':
			return 'core/comment_moderation';
		case 'wp_password_change_notification':
			return 'core/password_changed_admin';
	}
	return '';
}

/**
 * 1) wp_mail filtresi: bağlamı belirle, içeriği hazırla.
 */
function flxi_filter_wp_mail( $atts ) {
	if ( ! is_array( $atts ) ) {
		return $atts;
	}
	$lines   = flxi_header_lines( $atts['headers'] ?? array() );
	$key     = flxi_header_get( $lines, 'X-FLXI-Template' );
	$lines   = flxi_header_remove( $lines, 'X-FLXI-Template' );
	$ready   = '1' === flxi_header_get( $lines, 'X-FLXI-Rendered' );
	$lines   = flxi_header_remove( $lines, 'X-FLXI-Rendered' );
	$camp    = (int) flxi_header_get( $lines, 'X-FLXI-Campaign' );
	$lines   = flxi_header_remove( $lines, 'X-FLXI-Campaign' );
	$sender  = flxi_header_get( $lines, 'X-FLXI-Sender' );
	$lines   = flxi_header_remove( $lines, 'X-FLXI-Sender' );
	$caller  = flxi_detect_caller();
	$source  = $caller['source'];

	if ( '' === $key && 'wordpress' === $source ) {
		$key = flxi_core_caller_key( $caller['caller'], $atts['to'] ?? '' );
	}
	if ( '' === $key ) {
		$key = $caller['key'];
		flxi_remember_discovered( $key, $source, $caller['caller'], (string) ( $atts['subject'] ?? '' ) );
	} elseif ( 0 === strpos( $key, 'wc/' ) ) {
		$source = 'woocommerce';
	} elseif ( isset( flxi_email_types()[ $key ] ) ) {
		$source = strtok( $key, '/' );
		$source = 'core' === $source ? 'wordpress' : $source;
	} elseif ( 0 === strpos( $key, 'flx-mail/' ) || 'flx-certificates/test' === $key ) {
		$source = 'flx-mail/campaign' === $key ? 'flx-mail' : strtok( $key, '/' );
	} else {
		// API ile gelen ama şablon olarak kaydolmamış anahtar: listede "bulunan" olarak görünsün.
		flxi_remember_discovered( $key, $source, $caller['caller'], (string) ( $atts['subject'] ?? '' ) );
	}

	$conf = flxi_template( $key );
	if ( '' === $sender ) {
		$sender = $conf['sender'] ? $conf['sender'] : flxi_default_sender( $key, $source );
	}

	$ctx = array(
		'key'      => $key,
		'source'   => $source,
		'caller'   => $caller['caller'],
		'sender'   => 'noreply' === $sender ? 'noreply' : 'info',
		'block'    => ! $conf['enabled'],
		'block_by' => ! $conf['enabled'] ? 'template' : '',
		'html'     => false,
		'alt'      => '',
		'campaign' => $camp,
	);
	// no-reply hesabına giriş yapılamıyorsa (şifre yok/yanlış) bilgi@ ile gönder.
	if ( 'noreply' === $ctx['sender'] && empty( $GLOBALS['flxi_strict_sender'] ) ) {
		$why = flxi_nr_fallback_reason();
		if ( $why ) {
			$ctx['sender']   = 'info';
			$ctx['fallback'] = $why;
		}
	}
	$ctx = apply_filters( 'flxi_mail_context', $ctx, $atts );

	if ( ! $ctx['block'] ) {
		$message = (string) ( $atts['message'] ?? '' );
		$type    = flxi_header_get( $lines, 'Content-Type' );
		$is_html = false !== stripos( $type, 'text/html' ) || 'text/html' === apply_filters( 'wp_mail_content_type', 'text/plain' ) || preg_match( '~^\s*(<!doctype|<html)~i', $message );
		$is_doc  = (bool) preg_match( '~<html[\s>]~i', $message );

		if ( $ready ) {
			$ctx['html'] = true;
		} elseif ( $conf['wrap'] && ! $is_doc && ( ( ! $is_html && flxi_setting( 'wrap_plain' ) ) || ( $is_html && flxi_setting( 'wrap_html' ) ) ) && apply_filters( 'flxi_wrap_email', true, $key, $atts ) ) {
			$subject         = (string) ( $atts['subject'] ?? '' );
			$heading         = trim( preg_replace( '/^\[[^\]]+\]\s*/', '', $subject ) );
			$body            = $is_html ? $message : flxi_text_to_html( $message );
			$atts['message'] = flxi_render_email(
				array(
					'subject' => $subject,
					'heading' => esc_html( $heading ),
					'body'    => $body,
					'sender'  => $ctx['sender'],
				)
			);
			$ctx['html']     = true;
			$ctx['alt']      = $is_html ? '' : $message;
		} else {
			$ctx['html'] = $is_html;
		}
		if ( $ctx['html'] ) {
			$lines   = flxi_header_remove( $lines, 'Content-Type' );
			$lines[] = 'Content-Type: text/html; charset=UTF-8';
		}
		if ( ! flxi_header_get( $lines, 'Reply-To' ) ) {
			$reply = flxi_setting( 'reply_to' );
			if ( $reply && is_email( $reply ) ) {
				$lines[] = 'Reply-To: ' . $reply;
			}
		}
	}

	$atts['headers'] = $lines;
	$atts['flxi']     = $ctx;
	return apply_filters( 'flxi_mail_atts', $atts );
}

/** Varsayılan gönderen: kullanıcıya giden sistem e-postaları no-reply, diğerleri bilgi@. */
function flxi_default_sender( $key, $source ) {
	if ( 0 === strpos( $key, 'wc/' ) ) {
		return 'info';
	}
	return 'wordpress' === $source ? 'noreply' : 'info';
}

/**
 * 2) pre_wp_mail (erken): kapalıysa durdur, kayıt satırını aç.
 */
function flxi_pre_wp_mail( $return, $atts ) {
	if ( null !== $return || empty( $atts['flxi'] ) ) {
		return $return;
	}
	$ctx = $atts['flxi'];
	$log = flxi_log_start( $atts, $ctx );
	if ( $ctx['block'] ) {
		$GLOBALS['flxi_last_block'] = $ctx['block_by'] ? $ctx['block_by'] : 'template';
		$why = array(
			'social'    => __( 'Google ile kayıt: gönderilmedi', 'flx-mail' ),
			'blocklist' => __( 'Kara listede: gönderilmedi', 'flx-mail' ),
		);
		flxi_log_finish( $log, 'blocked', $why[ $ctx['block_by'] ] ?? __( 'Şablon kapalı: gönderilmedi', 'flx-mail' ) );
		$GLOBALS['flxi_current'] = null;
		return true;
	}
	$ctx['log']             = $log;
	$ctx['atts']            = $atts;
	$GLOBALS['flxi_current'] = $ctx;
	return null;
}

/** pre_wp_mail (en son): başka bir eklenti gönderimi kendisi yaptıysa (ör. SMTP eklentisi) kaydı kapat. */
function flxi_pre_wp_mail_after( $return, $atts ) {
	$cur = $GLOBALS['flxi_current'];
	if ( null !== $return && $cur && ! empty( $cur['log'] ) ) {
		flxi_log_finish( $cur['log'], $return ? 'sent' : 'failed', $return ? '' : __( 'Başka bir e-posta eklentisi gönderemedi.', 'flx-mail' ), 'external' );
		$GLOBALS['flxi_current'] = null;
	}
	return $return;
}

function flxi_sender_address( $type ) {
	$s = flxi_settings();
	if ( 'noreply' === $type && is_email( $s['noreply_email'] ) ) {
		return array( $s['noreply_email'], $s['noreply_name'] ? $s['noreply_name'] : $s['from_name'] );
	}
	return array( is_email( $s['from_email'] ) ? $s['from_email'] : '', $s['from_name'] );
}

function flxi_filter_from( $from ) {
	$cur = $GLOBALS['flxi_current'];
	list( $email ) = flxi_sender_address( $cur ? $cur['sender'] : 'info' );
	if ( ! $email ) {
		return $from;
	}
	// WordPress varsayılanı (wordpress@…) her zaman değişir; başka bir eklentinin özel adresi "Gönderen adresini zorla" açıksa değişir.
	if ( 0 === stripos( (string) $from, 'wordpress@' ) || flxi_setting( 'force_from' ) ) {
		return $email;
	}
	return $from;
}

function flxi_filter_from_name( $name ) {
	$cur = $GLOBALS['flxi_current'];
	list( , $label ) = flxi_sender_address( $cur ? $cur['sender'] : 'info' );
	if ( '' === (string) $label ) {
		return $name;
	}
	if ( 'WordPress' === $name || '' === (string) $name || flxi_setting( 'force_from' ) ) {
		return $label;
	}
	return $name;
}

function flxi_filter_content_type( $type ) {
	$cur = $GLOBALS['flxi_current'];
	return $cur && ! empty( $cur['html'] ) ? 'text/html' : $type;
}

/** Başka bir SMTP eklentisi etkinse kendi SMTP ayarımızı uygulamayız. */
function flxi_other_smtp_plugin() {
	$known = array(
		'wp-mail-smtp/wp_mail_smtp.php'       => 'WP Mail SMTP',
		'fluent-smtp/fluent-smtp.php'         => 'FluentSMTP',
		'post-smtp/postman-smtp.php'          => 'Post SMTP',
		'easy-wp-smtp/easy-wp-smtp.php'       => 'Easy WP SMTP',
		'wp-smtp/wp-smtp.php'                 => 'WP SMTP',
		'smtp-mailer/main.php'                => 'SMTP Mailer',
		'gosmtp/gosmtp.php'                   => 'GoSMTP',
		'suremails/suremails.php'             => 'SureMail',
	);
	$active = (array) get_option( 'active_plugins', array() );
	foreach ( $known as $file => $name ) {
		if ( in_array( $file, $active, true ) ) {
			return $name;
		}
	}
	return '';
}

function flxi_smtp_ready() {
	$s = flxi_settings();
	return 'smtp' === $s['mailer'] && '' !== trim( (string) $s['smtp_host'] ) && ! flxi_other_smtp_plugin();
}

/**
 * 3) PHPMailer ayarı: SMTP, Return-Path, düz metin alternatifi.
 */
function flxi_phpmailer_init( $mailer ) {
	$cur = $GLOBALS['flxi_current'];
	$s   = flxi_settings();
	if ( flxi_smtp_ready() ) {
		$nr   = $cur && 'noreply' === $cur['sender'] && '' !== trim( (string) $s['smtp_nr_user'] );
		$user = $nr ? $s['smtp_nr_user'] : $s['smtp_user'];
		if ( $cur ) {
			$GLOBALS['flxi_current']['account'] = $nr ? 'noreply' : 'info';
		}
		$mailer->isSMTP();
		$mailer->Host        = trim( $s['smtp_host'] );
		$mailer->Port        = (int) ( $s['smtp_port'] ? $s['smtp_port'] : 465 );
		$mailer->SMTPSecure  = in_array( $s['smtp_secure'], array( 'ssl', 'tls' ), true ) ? $s['smtp_secure'] : '';
		$mailer->SMTPAutoTLS = '' !== $mailer->SMTPSecure;
		$mailer->SMTPAuth    = (bool) $s['smtp_auth'];
		if ( $mailer->SMTPAuth ) {
			$mailer->Username = $user;
			$mailer->Password = flxi_smtp_password( $nr );
		}
		$mailer->Timeout = 20;
		if ( $cur ) {
			$GLOBALS['flxi_current']['mailer'] = 'smtp';
		}
	}
	if ( $s['set_sender'] && is_email( $mailer->From ) && 0 !== stripos( $mailer->From, 'wordpress@' ) ) {
		$mailer->Sender = $mailer->From;
	}
	if ( $cur && ! empty( $cur['html'] ) && 'text/html' === $mailer->ContentType && '' === (string) $mailer->AltBody ) {
		$mailer->AltBody = '' !== $cur['alt'] ? $cur['alt'] : flxi_html_to_text( $mailer->Body );
	}
	if ( $cur && ! empty( $cur['log'] ) ) {
		flxi_log_update(
			$cur['log'],
			array(
				'sender' => mb_substr( ( $mailer->FromName ? $mailer->FromName . ' ' : '' ) . '<' . $mailer->From . '>', 0, 190 ),
				'mailer' => flxi_smtp_ready() ? 'smtp' : ( 'smtp' === $mailer->Mailer ? 'smtp:' . $mailer->Host : 'php' ),
			)
		);
	}
}

function flxi_mail_succeeded( $data ) {
	$cur = $GLOBALS['flxi_current'];
	if ( $cur && ! empty( $cur['account'] ) ) {
		flxi_smtp_auth_record( $cur['account'], true );
	}
	if ( $cur && ! empty( $cur['log'] ) ) {
		flxi_log_finish( $cur['log'], 'sent', ! empty( $cur['fallback'] ) ? flxi_fallback_note() : '' );
	}
	$GLOBALS['flxi_current'] = null;
}

function flxi_mail_failed( $error ) {
	$cur = $GLOBALS['flxi_current'];
	$msg = is_wp_error( $error ) ? $error->get_error_message() : '';
	$GLOBALS['flxi_current'] = null;
	$auth = $cur && ! empty( $cur['account'] ) && flxi_is_auth_error( $msg );
	if ( $auth ) {
		flxi_smtp_auth_record( $cur['account'], false, $msg );
	}
	// no-reply hesabı doğrulanamadı: aynı e-postayı bir kez bilgi@ ile yeniden gönder.
	$retry = $auth && 'noreply' === $cur['account'] && empty( $GLOBALS['flxi_strict_sender'] ) && empty( $GLOBALS['flxi_retrying'] ) && ! empty( $cur['atts'] );
	if ( $cur && ! empty( $cur['log'] ) ) {
		flxi_log_finish( $cur['log'], 'failed', $retry ? $msg . ' ' . __( 'bilgi@ ile yeniden gönderildi.', 'flx-mail' ) : $msg );
	}
	if ( $retry ) {
		$a = $cur['atts'];
		$h = flxi_header_lines( $a['headers'] ?? array() );
		$h = flxi_header_remove( $h, 'Content-Type' );
		$h[] = 'Content-Type: ' . ( ! empty( $cur['html'] ) ? 'text/html' : 'text/plain' ) . '; charset=UTF-8';
		$h[] = 'X-FLXI-Template: ' . $cur['key'];
		$h[] = 'X-FLXI-Rendered: ' . ( ! empty( $cur['html'] ) ? '1' : '0' );
		$h[] = 'X-FLXI-Sender: info';
		if ( ! empty( $cur['campaign'] ) ) {
			$h[] = 'X-FLXI-Campaign: ' . (int) $cur['campaign'];
		}
		$GLOBALS['flxi_retrying'] = true;
		$GLOBALS['flxi_retry_ok'] = wp_mail( $a['to'], $a['subject'] ?? '', $a['message'] ?? '', $h, $a['attachments'] ?? array() );
		$GLOBALS['flxi_retrying'] = false;
	}
}

/* ------------------------------------------------------------------ */
/* SMTP hesap sağlığı (bilgi@ / no-reply@)                              */
/* ------------------------------------------------------------------ */

function flxi_is_auth_error( $msg ) {
	return (bool) preg_match( '/authenticat|kimlik do[gğ]rula|\b535\b/iu', (string) $msg );
}

/** no-reply hesap bilgisinin parmak izi: kullanıcı ya da şifre değişince eski "başarısız" kaydı geçersizleşir. */
function flxi_nr_signature() {
	$s = flxi_settings();
	return md5( strtolower( trim( (string) $s['smtp_nr_user'] ) ) . '|' . ( defined( 'FLXI_SMTP_NOREPLY_PASS' ) ? 'const:' . md5( (string) FLXI_SMTP_NOREPLY_PASS ) : (string) $s['smtp_nr_pass'] ) );
}

function flxi_info_signature() {
	$s = flxi_settings();
	return md5( strtolower( trim( (string) $s['smtp_user'] ) ) . '|' . ( defined( 'FLXI_SMTP_PASS' ) ? 'const:' . md5( (string) FLXI_SMTP_PASS ) : (string) $s['smtp_pass'] ) );
}

function flxi_smtp_auth_state() {
	$st = get_option( 'flxi_smtp_auth', array() );
	return is_array( $st ) ? $st : array();
}

function flxi_smtp_auth_record( $account, $ok, $error = '' ) {
	$st             = flxi_smtp_auth_state();
	$st[ $account ] = array(
		'ok'    => (bool) $ok,
		'at'    => time(),
		'error' => mb_substr( (string) $error, 0, 300 ),
		'sig'   => 'noreply' === $account ? flxi_nr_signature() : flxi_info_signature(),
	);
	update_option( 'flxi_smtp_auth', $st, false );
}

/** Hesabın son giriş durumu (bilgi değişmişse null: henüz denenmedi). */
function flxi_smtp_auth_status( $account ) {
	$st = flxi_smtp_auth_state()[ $account ] ?? null;
	if ( ! $st || ( $st['sig'] ?? '' ) !== ( 'noreply' === $account ? flxi_nr_signature() : flxi_info_signature() ) ) {
		return null;
	}
	return $st;
}

/** no-reply@ e-postaları neden bilgi@ ile gidiyor ('' = gitmiyor, nopass = şifre yok, auth = giriş başarısız). */
function flxi_nr_fallback_reason() {
	$s = flxi_settings();
	if ( ! flxi_smtp_ready() || ! $s['smtp_auth'] || '' === trim( (string) $s['smtp_nr_user'] ) ) {
		return '';
	}
	if ( '' === flxi_smtp_password( true ) ) {
		return 'nopass';
	}
	$st = flxi_smtp_auth_status( 'noreply' );
	return ( $st && ! $st['ok'] ) ? 'auth' : '';
}

/** Panel ve sistem kontrolü için hesap durumları: level ok|warning|error, text. */
function flxi_smtp_account_notes() {
	$s   = flxi_settings();
	$out = array();
	if ( ! flxi_smtp_ready() || ! $s['smtp_auth'] ) {
		return $out;
	}
	$info = flxi_smtp_auth_status( 'info' );
	if ( $info && ! $info['ok'] ) {
		$out['info'] = array(
			'level' => 'error',
			/* translators: 1: account, 2: date, 3: error */
			'text'  => sprintf( __( '%1$s hesabına giriş yapılamadı (%2$s): %3$s Kullanıcı adını ve şifreyi kontrol edin; hPanel\'de şifre değiştiyse buraya da yazın.', 'flx-mail' ), $s['smtp_user'], flx_date( $info['at'], 'datetime' ), $info['error'] ),
		);
	} elseif ( $info ) {
		/* translators: 1: account, 2: date */
		$out['info'] = array( 'level' => 'ok', 'text' => sprintf( __( '%1$s: giriş başarılı (son gönderim %2$s).', 'flx-mail' ), $s['smtp_user'], flx_date( $info['at'], 'datetime' ) ) );
	}
	if ( '' !== trim( (string) $s['smtp_nr_user'] ) ) {
		$why = flxi_nr_fallback_reason();
		if ( 'nopass' === $why ) {
			$out['noreply'] = array(
				'level' => 'warning',
				/* translators: 1: no-reply account, 2: info address */
				'text'  => sprintf( __( '%1$s için şifre girilmemiş; no-reply e-postaları şimdilik %2$s ile gidiyor. Şifreyi girin ya da no-reply@ bilgi@ hesabının takma adıysa kullanıcı alanını boşaltın.', 'flx-mail' ), $s['smtp_nr_user'], $s['from_email'] ),
			);
		} elseif ( 'auth' === $why ) {
			$st = flxi_smtp_auth_status( 'noreply' );
			$out['noreply'] = array(
				'level' => 'warning',
				/* translators: 1: no-reply account, 2: date, 3: error, 4: info address */
				'text'  => sprintf( __( '%1$s hesabına giriş yapılamadı (%2$s): %3$s no-reply e-postaları şimdilik %4$s ile gidiyor. Şifreyi düzeltip kaydedin (hesap yoksa hPanel\'de açın ya da kullanıcı alanını boşaltın); sonra no-reply@ ile test e-postası gönderin.', 'flx-mail' ), $s['smtp_nr_user'], flx_date( $st['at'], 'datetime' ), $st['error'], $s['from_email'] ),
			);
		} else {
			$st = flxi_smtp_auth_status( 'noreply' );
			if ( $st ) {
				/* translators: 1: account, 2: date */
				$out['noreply'] = array( 'level' => 'ok', 'text' => sprintf( __( '%1$s: giriş başarılı (son gönderim %2$s).', 'flx-mail' ), $s['smtp_nr_user'], flx_date( $st['at'], 'datetime' ) ) );
			}
		}
	}
	return $out;
}

function flxi_fallback_note() {
	/* translators: 1: no-reply address, 2: info address */
	return sprintf( __( '%1$s hesabına giriş yapılamadığı için %2$s ile gönderildi.', 'flx-mail' ), flxi_setting( 'smtp_nr_user' ), flxi_setting( 'from_email' ) );
}

/* ------------------------------------------------------------------ */
/* Kayıt                                                               */
/* ------------------------------------------------------------------ */

function flxi_log_start( $atts, $ctx ) {
	global $wpdb;
	if ( ! flxi_setting( 'log_enabled' ) ) {
		return 0;
	}
	$to = $atts['to'] ?? '';
	$to = is_array( $to ) ? implode( ', ', $to ) : (string) $to;
	$hd = flxi_header_lines( $atts['headers'] ?? array() );
	$wpdb->insert(
		flxi_table( 'log' ),
		array(
			'created_at'  => flxi_now(),
			'channel'     => 'email',
			'template'    => mb_substr( (string) $ctx['key'], 0, 160 ),
			'source'      => mb_substr( (string) $ctx['source'], 0, 80 ),
			'recipient'   => mb_substr( $to, 0, 2000 ),
			'subject'     => mb_substr( (string) ( $atts['subject'] ?? '' ), 0, 1000 ),
			'status'      => 'sending',
			'body'        => flxi_setting( 'log_body' ) ? mb_substr( (string) ( $atts['message'] ?? '' ), 0, 300000 ) : '',
			'headers'     => mb_substr( implode( "\n", $hd ), 0, 4000 ),
			'campaign_id' => (int) ( $ctx['campaign'] ?? 0 ),
			'user_id'     => get_current_user_id(),
		)
	);
	return (int) $wpdb->insert_id;
}

function flxi_log_update( $id, array $data ) {
	global $wpdb;
	if ( $id ) {
		$wpdb->update( flxi_table( 'log' ), $data, array( 'id' => (int) $id ) );
	}
}

function flxi_log_finish( $id, $status, $error = '', $mailer = '' ) {
	$data = array(
		'status' => $status,
		'error'  => mb_substr( (string) $error, 0, 2000 ),
	);
	if ( $mailer ) {
		$data['mailer'] = $mailer;
	}
	flxi_log_update( $id, $data );
	do_action( 'flxi_mail_logged', $id, $status );
}

/** WhatsApp / SMS gönderimlerini de aynı kayda yazar. */
function flxi_log_message( $channel, $recipient, $body, $ok, $error = '', $extra = array() ) {
	global $wpdb;
	if ( ! flxi_setting( 'log_enabled' ) ) {
		return 0;
	}
	$wpdb->insert(
		flxi_table( 'log' ),
		array(
			'created_at'  => flxi_now(),
			'channel'     => $channel,
			'template'    => mb_substr( (string) ( $extra['template'] ?? '' ), 0, 160 ),
			'source'      => mb_substr( (string) ( $extra['source'] ?? '' ), 0, 80 ),
			'recipient'   => (string) $recipient,
			'subject'     => '',
			'status'      => $extra['status'] ?? ( $ok ? 'sent' : 'failed' ),
			'error'       => mb_substr( (string) $error, 0, 2000 ),
			'mailer'      => mb_substr( (string) ( $extra['mailer'] ?? '' ), 0, 30 ),
			'body'        => mb_substr( (string) $body, 0, 20000 ),
			'campaign_id' => (int) ( $extra['campaign_id'] ?? 0 ),
			'user_id'     => get_current_user_id(),
		)
	);
	return (int) $wpdb->insert_id;
}

/** E-posta istemcileri emojiyi kendisi gösterir; WordPress'in emojiyi s.w.org görseline çevirmesi gereksiz (engelli görsellerde boş kutu çıkar). */
add_action(
	'init',
	function () {
		if ( apply_filters( 'flxi_native_emoji', true ) ) {
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		}
	}
);
