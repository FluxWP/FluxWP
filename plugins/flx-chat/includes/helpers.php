<?php
defined( 'ABSPATH' ) || exit;

function flxc_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'flxc_' . $name;
}

function flxc_default_settings() {
	return array(
		'connections'      => array(),
		'accent'           => '#00a884',
		'brand_name'       => '',
		'poll_interval'    => 10,
		'send_read'        => 1,
		'delete_everyone_hours' => 60, // 1.38.0: WhatsApp "herkesten sil" sınırı ~2 gün 12 saat
		'show_groups'      => 0,
		'agent_signature'  => 0,
		'restrict_agents'  => 0,
		'labels'           => array(),
		'welcome_enabled'  => 0,
		'welcome_text'     => '',
		'away_enabled'     => 0,
		'away_text'        => '',
		'away_days'        => array( 1, 2, 3, 4, 5 ),
		'away_start'       => '09:00',
		'away_end'         => '18:00',
		'away_throttle'    => 6,
		'ai_provider'      => '',
		'ai_model'         => '',
		'ai_model_bot'     => '',
		'ai_model_vision'  => '',
		'bot_receipt_ai'   => 1,
		'ai_temperature'   => 0.4,
		'ai_auto_suggest'  => 1,
		'ai_instructions'  => '',
		'bot_enabled'      => 0,
		'bot_scope'        => 'all',
		'bot_test_only'    => 0,
		'bot_test_numbers' => '',
		'bot_handoff_words' => '',
		'bot_context_hours' => 24,
		'bot_pause_hours'  => 12,
		'bot_daily_limit'  => 40,
		'bot_types'        => array(),
		'bot_presets'      => '1, 3, 5, 10',
		'bot_price_footer' => '',
		'bot_after_payment' => '',
		'bot_handoff_text' => '',
		'bot_notify_numbers' => '',
		'bot_optout_text'  => '',
		'bot_site_search'  => 1,
		'bot_site_types'   => array(),
		'bot_site_exclude' => '',
		'mem_enabled'      => 1,
		'mem_after_hours'  => 24,
		'workers_max'      => 20,      // 1.20.0: 15-20 eş zamanlı sohbet (Hostinger: 60 PHP işçisi, 4 site ortak)
		'workers_reserved' => 5,       // 1.8.1: yalnız cevaplara ayrılan işçi (özet/hatırlatıcı almaz)
		'mem_window_from'  => '03:00', // özetler yalnız bu saatler arasında
		'mem_window_to'    => '05:00',
		'mirror_enabled'   => 0,
		'mirror_group'     => '',
		'notify_to_group'  => 1,
		'bot_time_budget'  => 50,     // 1.8.0: bir cevabın en uzun süresi (sn); aşılırsa yedek mesaj
		'bot_fallback_text' => '',
		'rem_enabled'      => 1,      // 1.8.0: kişinin istediği hatırlatıcılar
		'rem_time'         => '09:00', // saat söylenmezse
		'rem_max'          => 10,     // kişi başına en fazla etkin hatırlatıcı
		'email_verify'     => 1,      // kayıt sorgusunda kod doğrulama (telefon + e-posta)
		'verify_ttl'       => 5,      // 1.9.0: kod geçerliliği (dk)
		'verify_phone_channel' => 'auto', // auto (WhatsApp, olmazsa SMS) | whatsapp | sms
		'verify_msg'       => '',
		'verify_max_fails' => 3,      // 1.9.2: bu kadar başarısız denemeden sonra doğrulama 24 sa kapanır, ekibe haber gider
		'verify_fail_text' => '',
		'bot_cancel_text'  => '',
		'bot_limit_text'   => '',
		'staff_on'         => 1,
		'ref_notify'       => 1,
		'benef_cert_notify' => 1,
		'ref_cert_notify'  => 1,      // 1.29.0: sertifika referans personele de gider
		'ref_cert_text'    => '',
		'staff_cert_max'   => 3,      // personelin bir bağışın sertifikasını yeniden gönderme sınırı
		'bot_reactions'    => 1,      // 1.42.0: asistan kapanış / teşekkür / dua mesajlarına emoji tepkisi bırakır
		'bot_voice'        => 1,      // 1.26.0: sesli mesaj metne çevrilip cevaplanır
		'bot_voice_max'    => 60,     // bundan uzun ses dinlenmez (sn)
		'ai_model_audio'   => '',     // boşsa ses okuyan ucuz OpenRouter modeli
		'bot_widgets'      => 1,      // 1.24.0: Flux AI 0.2.0+ ve çevirici varsa asistan görsel (SVG → PNG) gönderebilir     // 1.23.0: başkası adına bağışta sertifika kişi kartındaki / verilen numaraya da gider
		'benef_cert_text'  => '',
		'staff_donor_text' => '',
		'bot_fallback_wait' => 30,    // 1.9.0: kişinin mesajından bu kadar sn geçmeden bekleme mesajı gitmez
		'bot_instructions' => '',
		'bank_holder'      => '',
		'bank_iban'        => '',
		'bank_desc'        => '',
		'donate_url'       => '',
		'chips_bar'        => 1,
		'agents_donations' => 1,
		'agents_certificates' => 1,
		'bank_text'        => '',
		'msg_donation_pending' => '',
		'msg_donation_done'    => '',
		'msg_receipt'          => '',
		'delete_data'      => 0,
		'ui_flux'          => 0,
	);
}

function flxc_settings( $reset = false ) {
	static $cache = null;
	if ( null === $cache || $reset ) {
		$saved = get_option( 'flxc_settings', array() );
		$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), flxc_default_settings() );
	}
	return $cache;
}

function flxc_setting( $key ) {
	$s = flxc_settings();
	return $s[ $key ] ?? null;
}

function flxc_update_settings( array $values ) {
	$s = array_merge( flxc_settings(), $values );
	if ( isset( $s['connections'] ) && is_array( $s['connections'] ) ) {
		foreach ( $s['connections'] as $i => $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			foreach ( array( 'api_key', 'secret' ) as $k ) {
				$v = (string) ( $c[ $k ] ?? '' );
				if ( '' === $v || 0 === strpos( $v, 'flx1:' ) || ! function_exists( 'flx_secret_seal' ) ) {
					continue;
				}
				$sealed = flx_secret_seal( $v, 'flx-chat' );
				if ( '' !== $sealed ) {
					$s['connections'][ $i ][ $k ] = $sealed;
				}
			}
		}
	}
	update_option( 'flxc_settings', $s, false );
	return flxc_settings( true );
}

/** Open a stored connection secret. A value from before sealing is returned unchanged. */
function flxc_open_secret( $value ) {
	$value = (string) $value;
	if ( '' === $value || 0 !== strpos( $value, 'flx1:' ) ) {
		return $value;
	}
	if ( ! function_exists( 'flx_secret_open' ) && function_exists( 'flx_brand_boot' ) ) {
		flx_brand_boot(); // Read before after_setup_theme (e.g. mail sent while plugins load): load the shared core now.
	}
	if ( ! function_exists( 'flx_secret_open' ) ) {
		return '';
	}
	return flx_secret_open( $value, 'flx-chat' );
}

/** Varsayılan etiketler: ilk kurulumda site dilinde yazılır, sonra panelden değiştirilir. */
function flxc_default_labels() {
	return array(
		array( 'id' => 'yeni', 'name' => __( 'New', 'flx-chat' ), 'color' => '#25d366' ),
		array( 'id' => 'bagis', 'name' => __( 'Donation', 'flx-chat' ), 'color' => '#53bdeb' ),
		array( 'id' => 'oneli', 'name' => __( 'Important', 'flx-chat' ), 'color' => '#ff6b6b' ),
		array( 'id' => 'takip', 'name' => __( 'Follow-up', 'flx-chat' ), 'color' => '#ffb347' ),
	);
}

function flxc_now() {
	return current_time( 'mysql', true );
}

/** Milisaniye sırası: istemci "bundan sonra değişenleri" bu değerle sorar. Aynı istek içinde tekrar etmez. */
function flxc_rev() {
	static $last = 0;
	$r = (int) floor( microtime( true ) * 1000 );
	if ( $r <= $last ) {
		$r = $last + 1;
	}
	$last = $r;
	return $r;
}

function flxc_json( $data ) {
	return wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

function flxc_decode( $json, $default = array() ) {
	if ( is_array( $json ) ) {
		return $json;
	}
	$v = json_decode( (string) $json, true );
	return is_array( $v ) ? $v : $default;
}

/**
 * Telefonu uluslararası rakamlara çevirir (905321234567). Flux Certificates yüklüyse onun kuralı kullanılır
 * ki iki eklenti aynı kişiyi aynı numarayla eşleştirsin.
 */
function flxc_normalize_phone( $raw ) {
	if ( function_exists( 'flxs_normalize_phone' ) ) {
		return flxs_normalize_phone( $raw );
	}
	return flx_phone_digits( $raw, 'tr' );
}

/** 905321234567 => +90 532 123 45 67 */
function flxc_format_phone( $phone ) {
	$phone = preg_replace( '/\D+/', '', (string) $phone );
	if ( '' === $phone ) {
		return '';
	}
	if ( 0 === strpos( $phone, '90' ) && 12 === strlen( $phone ) ) {
		return '+90 ' . substr( $phone, 2, 3 ) . ' ' . substr( $phone, 5, 3 ) . ' ' . substr( $phone, 8, 2 ) . ' ' . substr( $phone, 10, 2 );
	}
	return '+' . $phone;
}

/** "905321234567@s.whatsapp.net" => "905321234567" */
function flxc_jid_phone( $jid ) {
	$jid = (string) $jid;
	// @lid gizli kimliktir, telefon numarası değildir; @web (site sohbeti, 1.37.0) de numara taşımaz.
	if ( '@lid' === substr( $jid, -4 ) || '@web' === substr( $jid, -4 ) ) {
		return '';
	}
	$at  = strpos( $jid, '@' );
	$num = false === $at ? $jid : substr( $jid, 0, $at );
	$num = preg_replace( '/:\d+$/', '', $num );
	return preg_replace( '/\D+/', '', $num );
}

/** "1592...@lid" => "1592..." (gizli kimliğin rakamları; telefon değildir). */
function flxc_lid_digits( $jid ) {
	$jid = (string) $jid;
	return '@lid' === substr( $jid, -4 ) ? preg_replace( '/\D+/', '', substr( $jid, 0, -4 ) ) : '';
}

function flxc_is_group_jid( $jid ) {
	return '@g.us' === substr( (string) $jid, -5 );
}

function flxc_can_use() {
	return current_user_can( 'flxc_use' ) || current_user_can( 'manage_options' );
}

function flxc_can_manage() {
	return current_user_can( 'flxc_manage' ) || current_user_can( 'manage_options' );
}

/** Temsilci listesi (atama menüsü için). */
function flxc_agents() {
	static $list = null;
	if ( null !== $list ) {
		return $list;
	}
	$users = get_users(
		array(
			'capability__in' => array( 'flxc_use', 'flxc_manage' ),
			'fields'         => array( 'ID', 'display_name' ),
			'orderby'        => 'display_name',
		)
	);
	$list = array();
	foreach ( $users as $u ) {
		$list[] = array( 'id' => (int) $u->ID, 'name' => $u->display_name );
	}
	return $list;
}

function flxc_user_name( $user_id ) {
	static $cache = array();
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return '';
	}
	if ( ! isset( $cache[ $user_id ] ) ) {
		$u                  = get_userdata( $user_id );
		$cache[ $user_id ] = $u ? $u->display_name : '';
	}
	return $cache[ $user_id ];
}

function flxc_brand_name() {
	$n = trim( (string) flxc_setting( 'brand_name' ) );
	return '' !== $n ? $n : get_bloginfo( 'name' );
}

/** Medya klasörü: uploads/flx-chat. Dışarıdan erişim kapalı; dosyalar yetkili REST ucu üzerinden verilir. */
function flxc_media_dir() {
	$up  = wp_upload_dir( null, false );
	$dir = trailingslashit( $up['basedir'] ) . 'flx-chat';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions
		@file_put_contents( $dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
		@file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" );
	}
	return $dir;
}

function flxc_log_error( $msg, $context = array() ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		error_log( '[flx-chat] ' . $msg . ( $context ? ' ' . flxc_json( $context ) : '' ) );
	}
}

/**
 * 1.20.0: sitenin kendine attığı arka plan isteği (benchmark adımı, sıradaki cevap). Eskiden 0,01 sn'lik
 * engellemeyen istekti; HTTPS el sıkışması o sürede bitmeyince istek hiç ulaşmıyor, iş takılı kalıyordu.
 * Şimdi kısa süre beklenir: karşı taraf flxc_detach_response() ile hemen 202 döner, işi arkada sürdürür.
 * Ulaşamazsa (bağlantı hatası, 4xx/5xx) false döner ve son hata kaydedilir (Sistem kontrolü gösterir).
 */
function flxc_loopback( $route, array $body = array() ) {
	$r = wp_remote_post(
		rest_url( $route ),
		array(
			'timeout'   => 3,
			'blocking'  => true,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			'body'      => $body + array( 'key' => flxc_bench_key() ),
		)
	);
	if ( is_wp_error( $r ) ) {
		// Zaman aşımı: istek ulaştı, sunucu cevabı erken kesemiyor (iş yine sürer). Bağlantı kurulamadıysa hata.
		$ok = false !== stripos( $r->get_error_message(), 'timed out' ) && false === stripos( $r->get_error_message(), 'connect' );
		$err = $r->get_error_message();
	} else {
		$code = (int) wp_remote_retrieve_response_code( $r );
		$ok   = $code >= 200 && $code < 300;
		$err  = 'HTTP ' . $code;
	}
	if ( ! $ok ) {
		update_option( 'flxc_loopback_err', array( 'at' => time(), 'msg' => mb_substr( $err, 0, 200 ) ), false );
	} elseif ( get_option( 'flxc_loopback_err' ) ) {
		update_option( 'flxc_loopback_ok', time(), false );
	}
	return $ok;
}

/**
 * Arka plan isteğine hemen "kabul edildi" cevabı verir, bağlantıyı kapatır; PHP işi sürdürür.
 * Sunucu desteklemiyorsa (cevap erken gönderilemiyorsa) false; iş yine çalışır.
 */
function flxc_detach_response() {
	ignore_user_abort( true );
	if ( headers_sent() || ( ! function_exists( 'fastcgi_finish_request' ) && ! function_exists( 'litespeed_finish_request' ) ) ) {
		return false;
	}
	$body = '{"ok":true,"detached":true}';
	while ( ob_get_level() > 0 ) {
		ob_end_clean();
	}
	status_header( 202 );
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Length: ' . strlen( $body ) );
	header( 'Connection: close' );
	echo $body; // phpcs:ignore WordPress.Security.EscapeOutput
	flush();
	if ( function_exists( 'fastcgi_finish_request' ) ) {
		fastcgi_finish_request();
	} else {
		litespeed_finish_request();
	}
	return true;
}
