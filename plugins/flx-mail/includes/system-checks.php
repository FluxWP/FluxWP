<?php
/**
 * Ortak sistem kontrolü satırları (flx_system_checks) ve yapay zekânın MCP ile uygulayabileceği düzeltmeler (flx_system_fixes).
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'flx_system_checks', 'flxi_system_checks' );

function flxi_system_checks( $checks ) {
	global $wpdb;
	$src  = 'Flux Mail';
	$s    = flxi_settings();
	$site = flxi_site_domain();
	$set  = flxi_admin_url( 'flxi-ayarlar' );
	$add  = function ( $id, $group, $label, $status, $message, $url = '', $alabel = '' ) use ( &$checks, $src ) {
		$checks[] = array(
			'id'           => 'flxi.' . $id,
			'source'       => $src,
			'group'        => $group,
			'label'        => $label,
			'status'       => $status,
			'message'      => $message,
			'action_url'   => $url,
			'action_label' => $alabel,
		);
	};

	// Gönderen adresleri
	$from_dom = strtolower( substr( strrchr( (string) $s['from_email'], '@' ), 1 ) );
	if ( ! is_email( $s['from_email'] ) ) {
		$add( 'from', 'Email', __( 'Gönderen adresi', 'flx-mail' ), 'error', __( 'Gönderen e-posta adresi geçersiz. İletişim › Ayarlar\'dan bilgi@ adresinizi girin.', 'flx-mail' ), $set, __( 'Ayarlar', 'flx-mail' ) );
	} elseif ( $from_dom !== $site && substr( $from_dom, -strlen( '.' . $site ) ) !== '.' . $site ) {
		/* translators: 1: adres, 2: alan adı */
		$add( 'from', 'Email', __( 'Gönderen adresi', 'flx-mail' ), 'warning', sprintf( __( '%1$s sitenin alan adında (%2$s) değil. Gmail gibi servisler başka alan adından gelen e-postayı spama atabilir.', 'flx-mail' ), $s['from_email'], $site ), $set, __( 'Ayarlar', 'flx-mail' ) );
	} else {
		/* translators: 1: ad, 2: adres, 3: no-reply adresi */
		$add( 'from', 'Email', __( 'Gönderen adresi', 'flx-mail' ), 'ok', sprintf( __( '%1$s <%2$s>; yanıt beklenmeyen e-postalar %3$s adresinden.', 'flx-mail' ), $s['from_name'], $s['from_email'], $s['noreply_email'] ) );
	}

	// Gönderim yöntemi
	$other = flxi_other_smtp_plugin();
	if ( $other ) {
		/* translators: %s: eklenti adı */
		$add( 'mailer', 'Email', __( 'Gönderim yöntemi', 'flx-mail' ), 'info', sprintf( __( 'E-postaları %s eklentisi gönderiyor; buradaki SMTP ayarı kullanılmaz. Şablon, gönderen adı ve kayıt yine Flux Mail\'den.', 'flx-mail' ), $other ) );
	} elseif ( 'smtp' === $s['mailer'] ) {
		if ( '' === trim( (string) $s['smtp_host'] ) || ( $s['smtp_auth'] && ( '' === (string) $s['smtp_user'] || '' === flxi_smtp_password() ) ) ) {
			$add( 'mailer', 'Email', __( 'Gönderim yöntemi', 'flx-mail' ), 'error', __( 'SMTP seçili ama sunucu, kullanıcı adı ya da şifre eksik. E-postalar gönderilemeyebilir.', 'flx-mail' ), $set . '#smtp', __( 'SMTP ayarları', 'flx-mail' ) );
		} else {
			/* translators: 1: sunucu, 2: port */
			$add( 'mailer', 'Email', __( 'Gönderim yöntemi', 'flx-mail' ), 'ok', sprintf( __( 'SMTP: %1$s:%2$s', 'flx-mail' ), $s['smtp_host'], $s['smtp_port'] ) );
		}
	} else {
		$add( 'mailer', 'Email', __( 'Gönderim yöntemi', 'flx-mail' ), 'warning', __( 'E-postalar sunucunun PHP mail() fonksiyonuyla gidiyor. Gmail gönderenin yanında "via srv….main-hosting.eu" gösterir ve spama düşme riski yüksektir. Hostinger\'da bilgi@ e-posta hesabı açıp SMTP\'yi seçin.', 'flx-mail' ), $set . '#smtp', __( 'SMTP ayarları', 'flx-mail' ) );
	}

	// SMTP hesapları (bilgi@ / no-reply@ girişi)
	foreach ( flxi_smtp_account_notes() as $acc => $note ) {
		if ( 'ok' !== $note['level'] ) {
			$add( 'smtp_' . $acc, 'Email', 'noreply' === $acc ? __( 'No-reply hesabı', 'flx-mail' ) : __( 'SMTP hesabı', 'flx-mail' ), $note['level'], $note['text'], $set . '#smtp', __( 'SMTP ayarları', 'flx-mail' ) );
		}
	}

	// Son test
	$test = get_option( 'flxi_last_test' );
	if ( is_array( $test ) ) {
		$when = flx_date( (int) $test['at'], 'datetime' );
		if ( $test['ok'] ) {
			/* translators: 1: adres, 2: tarih */
			$add( 'test', 'Email', __( 'Test e-postası', 'flx-mail' ), 'ok', sprintf( __( '%1$s adresine gönderildi (%2$s). Gelen kutusuna düştüğünü kontrol edin.', 'flx-mail' ), $test['to'], $when ), $set . '#test', __( 'Yeniden test et', 'flx-mail' ) );
		} else {
			/* translators: 1: tarih, 2: hata */
			$add( 'test', 'Email', __( 'Test e-postası', 'flx-mail' ), 'error', sprintf( __( 'Gönderilemedi (%1$s): %2$s', 'flx-mail' ), $when, $test['error'] ? $test['error'] : 'wp_mail false' ), $set . '#test', __( 'Yeniden test et', 'flx-mail' ) );
		}
	} else {
		$add( 'test', 'Email', __( 'Test e-postası', 'flx-mail' ), 'info', __( 'Henüz test edilmedi.', 'flx-mail' ), $set . '#test', __( 'Test e-postası gönder', 'flx-mail' ) );
	}

	// Son 24 saatte hatalar
	$table = flxi_table( 'log' );
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) { // phpcs:ignore
		$since  = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		$failed = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = 'failed' AND created_at >= %s", $since ) ); // phpcs:ignore
		$sent   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = 'sent' AND created_at >= %s", $since ) ); // phpcs:ignore
		if ( $failed ) {
			$last = (string) $wpdb->get_var( $wpdb->prepare( "SELECT error FROM $table WHERE status = 'failed' AND created_at >= %s ORDER BY id DESC LIMIT 1", $since ) ); // phpcs:ignore
			/* translators: 1: hatalı, 2: gönderilen, 3: son hata */
			$add( 'failures', 'Email', __( 'Son 24 saat', 'flx-mail' ), 'error', sprintf( __( '%1$s gönderim başarısız, %2$s gönderildi. Son hata: %3$s', 'flx-mail' ), number_format_i18n( $failed ), number_format_i18n( $sent ), mb_substr( $last, 0, 200 ) ), flxi_admin_url( 'flxi-kayitlar', array( 'status' => 'failed' ) ), __( 'Kayıtlar', 'flx-mail' ) );
		} else {
			/* translators: %s: sayı */
			$add( 'failures', 'Email', __( 'Son 24 saat', 'flx-mail' ), 'ok', sprintf( __( '%s gönderim, hata yok.', 'flx-mail' ), number_format_i18n( $sent ) ), flxi_admin_url( 'flxi-kayitlar' ), __( 'Kayıtlar', 'flx-mail' ) );
		}
		$stuck = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxi_table( 'queue' ) . " WHERE status = 'queued' AND scheduled_at < %s", gmdate( 'Y-m-d H:i:s', time() - 30 * MINUTE_IN_SECONDS ) ) ); // phpcs:ignore
		if ( $stuck ) {
			/* translators: %s: sayı */
			$add( 'queue', 'Cron', __( 'Toplu gönderim kuyruğu', 'flx-mail' ), 'warning', sprintf( __( '%s mesaj 30 dakikadan uzun süredir bekliyor. WP-Cron çalışmıyor olabilir; Gönderim sayfasını açık tutmak kuyruğu ilerletir. Hostinger\'da gerçek cron önerilir.', 'flx-mail' ), number_format_i18n( $stuck ) ), flxi_admin_url( 'flxi-gonder' ), __( 'Gönderimler', 'flx-mail' ) );
		}
	} else {
		$add( 'tables', 'Config', __( 'Veritabanı tabloları', 'flx-mail' ), 'error', __( 'Kayıt tabloları yok. Eklentiyi kapatıp açın.', 'flx-mail' ) );
	}

	// Kara liste (Flux Chat)
	if ( has_filter( 'flx_contact_blocked' ) ) {
		$add( 'blocklist', 'Email', __( 'Kara liste', 'flx-mail' ), 'ok', __( 'Etkin: kara listedeki kişilere e-posta, WhatsApp ve SMS gitmez (parola sıfırlama gibi hesap güvenliği e-postaları hariç).', 'flx-mail' ) );
	}

	// Posta kutusu (IMAP)
	if ( flxi_setting( 'mailbox_enabled' ) ) {
		$st = flxi_mailbox_state();
		if ( ! flxi_mailbox_enabled() ) {
			$add( 'mailbox', 'Email', __( 'Posta kutusu', 'flx-mail' ), 'warning', __( 'Açık ama IMAP sunucusu, kullanıcı ya da şifre eksik (İletişim › Ayarlar › Posta kutusu).', 'flx-mail' ) );
		} elseif ( $st['error'] ) {
			$add( 'mailbox', 'Email', __( 'Posta kutusu', 'flx-mail' ), 'error', $st['error'] );
		} elseif ( $st['last_ok'] && $st['last_ok'] < time() - 6 * HOUR_IN_SECONDS ) {
			/* translators: %s: süre */
			$add( 'mailbox', 'Email', __( 'Posta kutusu', 'flx-mail' ), 'warning', sprintf( __( 'Son eşitleme %s. Zamanlanmış görevler (WP-Cron) çalışmıyor olabilir; Posta kutusu ekranı açıkken eşitleme yine yapılır.', 'flx-mail' ), flx_time_ago( $st['last_ok'] ) ) );
		} elseif ( $st['last_ok'] ) {
			/* translators: %s: süre */
			$add( 'mailbox', 'Email', __( 'Posta kutusu', 'flx-mail' ), 'ok', sprintf( __( 'Eşitleniyor; son eşitleme %s.', 'flx-mail' ), flx_time_ago( $st['last_ok'] ) ) . ( empty( $st['names']['sent'] ) ? ' ' . __( 'Gönderilmiş klasörü bulunamadı: web postadan gönderilenler görünmez.', 'flx-mail' ) : '' ) );
		}
		$add(
			'mailparse',
			'Email',
			__( 'E-posta ayrıştırıcı', 'flx-mail' ),
			flxi_mime_has_mailparse() ? 'ok' : 'info',
			flxi_mime_has_mailparse()
				? __( 'PHP mailparse uzantısı açık: gelen e-postaların başlık ve adresleri onunla okunuyor.', 'flx-mail' )
				: __( 'PHP mailparse uzantısı yok; eklentinin kendi ayrıştırıcısı kullanılıyor (çalışır). İsterseniz hPanel › PHP Yapılandırması › PHP Uzantıları\'ndan "mailparse" açılabilir.', 'flx-mail' )
		);
	}

	// Posta kutusu günlük gönderim sınırı
	$lim = flxi_limits();
	if ( $lim['daily'] ) {
		$full = get_option( 'flxi_quota_full_days', array() );
		$full = is_array( $full ) ? count( array_filter( $full, function ( $x ) {
			return strtotime( $x ) > time() - 30 * DAY_IN_SECONDS;
		} ) ) : 0;
		foreach ( flxi_quota_summary() as $q ) {
			/* translators: 1: mailbox, 2: used, 3: limit */
			$msg = sprintf( __( '%1$s: son 24 saatte %2$s / %3$s alıcı.', 'flx-mail' ), $q['mailbox'], number_format_i18n( $q['used'] ), number_format_i18n( $q['limit'] ) );
			if ( $q['percent'] >= 100 ) {
				/* translators: %s: time */
				$add( 'quota_' . $q['type'], 'Email', __( 'Günlük gönderim sınırı', 'flx-mail' ), 'error', $msg . ' ' . sprintf( __( 'Sınır doldu; bu adresten e-posta %s civarına kadar gitmeyebilir. Sık oluyorsa Hostinger\'da üst e-posta paketine geçin.', 'flx-mail' ), flx_date( $q['next'], 'time' ) . ', ' . flx_date( $q['next'], 'short' ) ), $set . '#sinirlar', __( 'Sınırlar', 'flx-mail' ) );
			} elseif ( $q['percent'] >= 80 ) {
				$add( 'quota_' . $q['type'], 'Email', __( 'Günlük gönderim sınırı', 'flx-mail' ), 'warning', $msg . ' ' . __( 'Sınıra yaklaşıldı; toplu gönderim tekil e-postalara pay bırakıp duraklar.', 'flx-mail' ), $set . '#sinirlar', __( 'Sınırlar', 'flx-mail' ) );
			} elseif ( $full >= 3 ) {
				/* translators: %s: days */
				$add( 'quota_' . $q['type'], 'Email', __( 'Günlük gönderim sınırı', 'flx-mail' ), 'warning', $msg . ' ' . sprintf( __( 'Son 30 günde %s gün sınıra dayanıldı; Hostinger\'da üst e-posta paketine geçmeyi düşünün.', 'flx-mail' ), number_format_i18n( $full ) ), $set . '#sinirlar', __( 'Sınırlar', 'flx-mail' ) );
			} else {
				$add( 'quota_' . $q['type'], 'Email', __( 'Günlük gönderim sınırı', 'flx-mail' ), 'ok', $msg, $set . '#sinirlar', __( 'Sınırlar', 'flx-mail' ) );
			}
		}
	}

	// DNS
	$dns = get_option( 'flxi_dns_check' );
	$dns_url = $set . '#dns';
	if ( is_array( $dns ) ) {
		$smtp_host = strtolower( (string) $s['smtp_host'] );
		if ( '' === $dns['spf'] ) {
			/* translators: %s: alan adı */
			$add( 'spf', 'Email', 'SPF', 'warning', sprintf( __( '%s için SPF kaydı bulunamadı. Hostinger\'da e-posta açınca DNS\'e otomatik eklenir; alan adı başka yerdeyse "v=spf1 include:_spf.mail.hostinger.com ~all" ekleyin.', 'flx-mail' ), $dns['domain'] ), $dns_url, __( 'Yeniden kontrol et', 'flx-mail' ) );
		} elseif ( false !== strpos( $smtp_host, 'hostinger' ) && false === stripos( $dns['spf'], 'hostinger' ) ) {
			$add( 'spf', 'Email', 'SPF', 'warning', __( 'SPF kaydı Hostinger\'ı içermiyor: ', 'flx-mail' ) . $dns['spf'], $dns_url, __( 'Yeniden kontrol et', 'flx-mail' ) );
		} else {
			$add( 'spf', 'Email', 'SPF', 'ok', $dns['spf'] );
		}
		$add( 'dmarc', 'Email', 'DMARC', $dns['dmarc'] ? 'ok' : 'info', $dns['dmarc'] ? $dns['dmarc'] : __( 'DMARC kaydı yok. Toplu gönderimde Gmail ve Yahoo DMARC ister; DNS\'e "_dmarc" TXT kaydı olarak "v=DMARC1; p=none; rua=mailto:bilgi@…" ekleyin.', 'flx-mail' ), $dns_url, __( 'Yeniden kontrol et', 'flx-mail' ) );
	} else {
		$add( 'spf', 'Email', __( 'SPF / DMARC (DNS)', 'flx-mail' ), 'info', __( 'Kontrol edilmedi.', 'flx-mail' ), $dns_url, __( 'DNS\'i kontrol et', 'flx-mail' ) );
	}

	// Google ile giriş
	$social = flxi_active_social_plugins();
	if ( $social ) {
		/* translators: %s: eklenti adları */
		$msg = sprintf( __( 'Google ile giriş eklentisi: %s.', 'flx-mail' ), implode( ', ', $social ) ) . ' ';
		$msg .= $s['social_block_core'] ? __( 'Google ile kayıt olanlara parola belirleme e-postası gitmiyor, e-postaları doğrulanmış sayılıyor.', 'flx-mail' ) : __( 'Google ile kayıt olanlara parola belirleme e-postası gidiyor (ayar kapalı).', 'flx-mail' );
		$add( 'social', 'Plugins', __( 'Google ile giriş', 'flx-mail' ), $s['social_block_core'] ? 'ok' : 'warning', $msg, $set . '#google', __( 'Ayar', 'flx-mail' ) );
	} else {
		$add( 'social', 'Plugins', __( 'Google ile giriş', 'flx-mail' ), 'info', __( 'Bilinen bir Google giriş eklentisi etkin değil. Başka bir eklenti kullanılıyorsa da Google dönüşü algılanır ve parola e-postası durdurulur.', 'flx-mail' ), $set . '#google', __( 'Ayar', 'flx-mail' ) );
	}

	// Bağlantılar
	$tests = (array) get_option( 'flxi_conn_tests', array() );
	foreach ( array( 'whatsapp' => 'WhatsApp', 'sms' => 'SMS' ) as $ch => $label ) {
		$list = flxi_list_connections( $ch, true );
		if ( ! $list ) {
			/* translators: %s: kanal */
			$add( 'conn.' . $ch, 'Config', $label, 'info', sprintf( __( '%s bağlantısı yok.', 'flx-mail' ), $label ), flxi_admin_url( 'flxi-baglantilar' ), __( 'Bağlantılar', 'flx-mail' ) );
			continue;
		}
		foreach ( $list as $c ) {
			$t = $tests[ $c['id'] ] ?? null;
			if ( ! $t ) {
				$add( 'conn.' . $c['id'], 'Config', $label . ' · ' . $c['name'], 'info', __( 'Ayarlar girilmiş, henüz test edilmedi.', 'flx-mail' ), flxi_admin_url( 'flxi-baglantilar' ), __( 'Test et', 'flx-mail' ) );
			} else {
				$when = flx_date( (int) $t['at'], 'datetime' );
				$add( 'conn.' . $c['id'], 'Config', $label . ' · ' . $c['name'], $t['ok'] ? 'ok' : 'error', $t['ok'] ? sprintf( /* translators: %s: tarih */ __( 'Test başarılı (%s).', 'flx-mail' ), $when ) : sprintf( /* translators: 1: tarih, 2: hata */ __( 'Test başarısız (%1$s): %2$s', 'flx-mail' ), $when, $t['error'] ), flxi_admin_url( 'flxi-baglantilar' ), __( 'Test et', 'flx-mail' ) );
			}
		}
	}

	if ( flxi_flxs_active() ) {
		$add( 'flxs', 'Plugins', __( 'Flux Certificates', 'flx-mail' ), 'ok', __( 'Sertifika e-postaları kurumsal şablonla, WhatsApp/SMS buradaki bağlantılarla gidiyor.', 'flx-mail' ) );
	}
	return $checks;
}

/* ------------------------------------------------------------------ */
/* MCP ile uygulanabilen düzeltmeler                                   */
/* ------------------------------------------------------------------ */

add_filter( 'flx_system_fixes', 'flxi_system_fixes' );

function flxi_system_fixes( $fixes ) {
	$src    = 'Flux Mail';
	$string = function ( $key, $label, $desc, $checks, $validate ) use ( $src ) {
		return array(
			'label'       => $label,
			'description' => $desc,
			'source'      => $src,
			'checks'      => $checks,
			'value'       => array( 'type' => 'string' ),
			'current'     => function () use ( $key ) {
				return flxi_setting( $key );
			},
			'run'         => function ( $value ) use ( $key, $validate, $label ) {
				$value = call_user_func( $validate, $value );
				$old   = flxi_setting( $key );
				flxi_update_settings( array( $key => $value ) );
				/* translators: 1: alan, 2: eski, 3: yeni */
				return sprintf( __( '%1$s: "%2$s" → "%3$s"', 'flx-mail' ), $label, $old, $value );
			},
		);
	};
	$email = function ( $v ) {
		$v = sanitize_email( (string) $v );
		if ( ! is_email( $v ) ) {
			throw new Exception( __( 'Geçerli bir e-posta adresi girin.', 'flx-mail' ) );
		}
		return $v;
	};
	$text  = function ( $v ) {
		$v = sanitize_text_field( (string) $v );
		if ( '' === $v ) {
			throw new Exception( __( 'Boş olamaz.', 'flx-mail' ) );
		}
		return $v;
	};

	$fixes['flxi.from_email']    = $string( 'from_email', __( 'Gönderen e-posta adresi', 'flx-mail' ), __( 'Yanıt beklenen e-postaların adresi (ör. bilgi@alanadi).', 'flx-mail' ), array( 'flxi.from' ), $email );
	$fixes['flxi.from_name']     = $string( 'from_name', __( 'Gönderen adı', 'flx-mail' ), __( 'Gelen kutusunda görünen ad.', 'flx-mail' ), array( 'flxi.from' ), $text );
	$fixes['flxi.noreply_email'] = $string( 'noreply_email', __( 'No-reply adresi', 'flx-mail' ), __( 'Yanıt beklenmeyen e-postaların adresi (parola, bildirim).', 'flx-mail' ), array( 'flxi.from' ), $email );
	$fixes['flxi.reply_to']      = $string(
		'reply_to',
		__( 'Yanıt adresi (Reply-To)', 'flx-mail' ),
		__( 'Boş: yanıtlar gönderen adrese gider.', 'flx-mail' ),
		array( 'flxi.from' ),
		function ( $v ) {
			$v = sanitize_email( (string) $v );
			return is_email( $v ) ? $v : '';
		}
	);
	$fixes['flxi.smtp_server']   = array(
		'label'       => __( 'SMTP sunucusu (şifre hariç)', 'flx-mail' ),
		'description' => __( 'Değer "sunucu:port:ssl|tls|yok:kullanıcı" biçiminde, ör. "smtp.hostinger.com:465:ssl:bilgi@alanadi". Şifreyi yönetici panelden girer.', 'flx-mail' ),
		'source'      => $src,
		'checks'      => array( 'flxi.mailer' ),
		'value'       => array( 'type' => 'string' ),
		'current'     => function () {
			$s = flxi_settings();
			return $s['smtp_host'] ? $s['smtp_host'] . ':' . $s['smtp_port'] . ':' . ( $s['smtp_secure'] ? $s['smtp_secure'] : 'yok' ) . ':' . $s['smtp_user'] : '';
		},
		'run'         => function ( $value ) {
			$p = explode( ':', (string) $value, 4 );
			if ( count( $p ) < 2 || '' === trim( $p[0] ) || ! is_numeric( $p[1] ) ) {
				throw new Exception( __( 'Biçim: sunucu:port:ssl|tls|yok:kullanıcı', 'flx-mail' ) );
			}
			$sec = strtolower( $p[2] ?? 'ssl' );
			flxi_update_settings(
				array(
					'smtp_host'   => sanitize_text_field( $p[0] ),
					'smtp_port'   => (int) $p[1],
					'smtp_secure' => in_array( $sec, array( 'ssl', 'tls' ), true ) ? $sec : '',
					'smtp_user'   => sanitize_text_field( $p[3] ?? '' ),
					'smtp_auth'   => '' !== trim( $p[3] ?? '' ) ? 1 : 0,
				)
			);
			return __( 'SMTP sunucu bilgisi kaydedildi. Yöntem değişmedi; şifre girildikten sonra yönetici "SMTP" seçmeli.', 'flx-mail' );
		},
	);
	$fixes['flxi.mailer']        = array(
		'label'       => __( 'Gönderim yöntemi', 'flx-mail' ),
		'description' => __( 'php: sunucunun mail() fonksiyonu; smtp: SMTP hesabı (sunucu ve şifre girilmiş olmalı).', 'flx-mail' ),
		'source'      => $src,
		'checks'      => array( 'flxi.mailer' ),
		'value'       => array( 'type' => 'string', 'enum' => array( 'php', 'smtp' ) ),
		'current'     => function () {
			return flxi_setting( 'mailer' );
		},
		'run'         => function ( $value ) {
			if ( 'smtp' === $value && ( '' === trim( (string) flxi_setting( 'smtp_host' ) ) || ( flxi_setting( 'smtp_auth' ) && '' === flxi_smtp_password() ) ) ) {
				throw new Exception( __( 'SMTP sunucusu ya da şifresi eksik; önce yönetici panelden girmeli.', 'flx-mail' ) );
			}
			flxi_update_settings( array( 'mailer' => 'smtp' === $value ? 'smtp' : 'php' ) );
			return 'smtp' === $value ? __( 'SMTP seçildi.', 'flx-mail' ) : __( 'PHP mail() seçildi.', 'flx-mail' );
		},
	);
	$fixes['flxi.social_block']  = array(
		'label'       => __( 'Google ile kayıtta parola e-postasını durdur', 'flx-mail' ),
		'source'      => $src,
		'checks'      => array( 'flxi.social' ),
		'value'       => array( 'type' => 'string', 'enum' => array( 'on', 'off' ) ),
		'current'     => function () {
			return flxi_setting( 'social_block_core' ) ? 'on' : 'off';
		},
		'run'         => function ( $value ) {
			$on = 'on' === $value ? 1 : 0;
			flxi_update_settings( array( 'social_block_core' => $on, 'social_block_wc' => $on, 'social_verified' => $on ) );
			return $on ? __( 'Açıldı.', 'flx-mail' ) : __( 'Kapatıldı.', 'flx-mail' );
		},
	);
	$fixes['flxi.send_test']     = array(
		'label'       => __( 'Test e-postası gönder', 'flx-mail' ),
		'description' => __( 'Değer alıcı adresi; boşsa site yöneticisinin adresi.', 'flx-mail' ),
		'source'      => $src,
		'checks'      => array( 'flxi.test', 'flxi.mailer' ),
		'value'       => array( 'type' => 'string' ),
		'current'     => function () {
			$t = get_option( 'flxi_last_test' );
			return is_array( $t ) ? ( $t['ok'] ? 'ok ' : 'hata ' ) . wp_date( 'Y-m-d H:i', (int) $t['at'] ) : '';
		},
		'run'         => function ( $value ) {
			$req = new WP_REST_Request( 'POST' );
			$req->set_param( 'to', is_email( $value ) ? $value : get_option( 'admin_email' ) );
			$r = flxi_rest_test_email( $req );
			if ( ! $r['ok'] ) {
				throw new Exception( __( 'Gönderilemedi: ', 'flx-mail' ) . $r['error'] );
			}
			/* translators: %s: adres */
			return sprintf( __( '%s adresine gönderildi.', 'flx-mail' ), $r['to'] );
		},
	);
	$fixes['flxi.dns_check']     = array(
		'label'   => __( 'SPF / DMARC kayıtlarını yeniden kontrol et', 'flx-mail' ),
		'source'  => $src,
		'checks'  => array( 'flxi.spf', 'flxi.dmarc' ),
		'current' => function () {
			$d = get_option( 'flxi_dns_check' );
			return is_array( $d ) ? wp_date( 'Y-m-d H:i', (int) $d['at'] ) : '';
		},
		'run'     => function () {
			$d = flxi_rest_dns();
			return 'SPF: ' . ( $d['spf'] ? $d['spf'] : '-' ) . ' · DMARC: ' . ( $d['dmarc'] ? $d['dmarc'] : '-' );
		},
	);
	return $fixes;
}

/* Ekran metinleri: flx_system_checks_labels çevirisi (yalnız bu eklentinin ekranında). */
function flxi_system_checks_labels( $l ) {
	$tr = array(
		'title'       => __( 'Sistem kontrolü', 'flx-mail' ),
		'intro'       => __( 'Her satır sitenin ihtiyaç duyduğu bir şeyi ve yerinde olup olmadığını gösterir. Önce hataları düzeltin; uyarılara da göz atın.', 'flx-mail' ),
		'ok'          => __( 'Tamam', 'flx-mail' ),
		'warning'     => __( 'Uyarı', 'flx-mail' ),
		'error'       => __( 'Hata', 'flx-mail' ),
		'info'        => __( 'Bilgi', 'flx-mail' ),
		/* translators: 1: tamam, 2: uyarı, 3: hata */
		'summary'     => __( '%1$d tamam · %2$d uyarı · %3$d hata', 'flx-mail' ),
		'recheck'     => __( 'Yeniden kontrol et', 'flx-mail' ),
		'filter_hint' => __( 'Yalnız bunları göster (tekrar tıklayınca hepsi)', 'flx-mail' ),
		/* translators: %s: zaman */
		'checked'     => __( 'Kontrol: %s', 'flx-mail' ),
		'col_status'  => __( 'Durum', 'flx-mail' ),
	);
	return array_merge( (array) $l, $tr );
}
