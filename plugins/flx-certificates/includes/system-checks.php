<?php
/**
 * Sistem kontrolü: kurulumun yerli yerinde olup olmadığını gösteren hızlı kontroller.
 *
 * Ortak sözleşme (FluxWP eklentileri + tema): 'flx_system_checks' filtresi. Her kontrol:
 * id, source, group, label, status (ok|warning|error|info), message, action_url?, action_label?
 * Kontroller ucuzdur ve dış HTTP isteği yapmaz. WhatsApp/SMS bağlantı testi Flux Mail'dedir.
 */
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'flx_system_checks_run' ) ) {
	/** Tüm FluxWP kontrollerini toplar ve biçimini düzeltir. */
	function flx_system_checks_run() {
		$out = array();
		foreach ( (array) apply_filters( 'flx_system_checks', array() ) as $c ) {
			if ( ! is_array( $c ) || empty( $c['label'] ) ) {
				continue;
			}
			$c = wp_parse_args( $c, array( 'id' => '', 'source' => '', 'group' => '', 'label' => '', 'status' => 'info', 'message' => '', 'action_url' => '', 'action_label' => '' ) );
			if ( ! in_array( $c['status'], array( 'ok', 'warning', 'error', 'info' ), true ) ) {
				$c['status'] = 'info';
			}
			$out[] = $c;
		}
		return $out;
	}
}

add_filter( 'flx_system_checks', 'flxs_add_system_checks', 20 );
function flxs_add_system_checks( $checks ) {
	if ( ! current_user_can( 'flxs_manage' ) && ! current_user_can( 'manage_options' ) ) {
		return $checks;
	}
	return array_merge( (array) $checks, flxs_system_checks() );
}

/** Kuyruk işleyicisinin son çalışma zamanı (kontrol için; en fazla dakikada bir yazılır) */
function flxs_mark_queue_run() {
	$last = (int) get_option( 'flxs_queue_last_run', 0 );
	if ( time() - $last >= 50 ) {
		update_option( 'flxs_queue_last_run', time(), false );
	}
}

function flxs_ago( $ts ) {
	/* translators: %s: human readable time difference, e.g. "5 mins" */
	return sprintf( __( '%s önce', 'flx-certificates' ), human_time_diff( (int) $ts, time() ) );
}

/** Flux Certificates kontrolleri */
function flxs_system_checks() {
	global $wpdb;
	$src   = 'flx-certificates';
	$g_set = __( 'Sertifika · Kurulum', 'flx-certificates' );
	$g_snd = __( 'Sertifika · Gönderim ve cron', 'flx-certificates' );
	$g_ste = __( 'Sertifika · Site', 'flx-certificates' );
	$page  = function ( $slug ) {
		return admin_url( 'admin.php?page=' . $slug );
	};
	$c = array();
	$add = function ( $id, $group, $label, $status, $message, $url = '', $alabel = '' ) use ( &$c, $src ) {
		$c[] = array( 'id' => 'flxs_' . $id, 'source' => $src, 'group' => $group, 'label' => $label, 'status' => $status, 'message' => $message, 'action_url' => $url, 'action_label' => $alabel );
	};

	/* ---------- Kurulum ---------- */
	$tables  = array( 'people', 'templates', 'certificates', 'batches', 'messages', 'providers', 'logs', 'events' );
	$found   = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'flxs_' ) . '%' ) );
	$missing = array();
	foreach ( $tables as $t ) {
		if ( ! in_array( flxs_table( $t ), $found, true ) ) {
			$missing[] = $t;
		}
	}
	$dbv = (string) get_option( 'flxs_db_version' );
	if ( $missing ) {
		/* translators: %s: comma separated table names */
		$add( 'db', $g_set, __( 'Veritabanı tabloları', 'flx-certificates' ), 'error', sprintf( __( 'Eksik tablo: %s. Eklentiyi devre dışı bırakıp yeniden etkinleştirin.', 'flx-certificates' ), implode( ', ', $missing ) ), admin_url( 'plugins.php' ), __( 'Eklentiler', 'flx-certificates' ) );
	} elseif ( FLXS_DB_VERSION !== $dbv ) {
		$add( 'db', $g_set, __( 'Veritabanı tabloları', 'flx-certificates' ), 'warning', __( 'Veritabanı güncellemesi bekliyor; bir yönetici sayfası açıldığında tamamlanır.', 'flx-certificates' ) );
	} else {
		/* translators: 1: plugin version, 2: database version */
		$add( 'db', $g_set, __( 'Veritabanı tabloları', 'flx-certificates' ), 'ok', sprintf( __( 'Tüm tablolar var. Eklenti %1$s, veritabanı sürümü %2$s.', 'flx-certificates' ), FLXS_VERSION, $dbv ) );
	}

	$up = flxs_upload_dir();
	if ( is_dir( $up['dir'] ) && wp_is_writable( $up['dir'] ) ) {
		$add( 'uploads', $g_set, __( 'Dosya klasörü', 'flx-certificates' ), 'ok', __( 'Sertifika PDF ve görselleri için klasör yazılabilir.', 'flx-certificates' ) );
	} else {
		/* translators: %s: folder path relative to wp-content */
		$add( 'uploads', $g_set, __( 'Dosya klasörü', 'flx-certificates' ), 'error', sprintf( __( '%s yazılamıyor; sertifika dosyaları kaydedilemez. Klasör izinlerini (755) kontrol edin.', 'flx-certificates' ), str_replace( WP_CONTENT_DIR, 'wp-content', $up['dir'] ) ) );
	}

	$rules = (array) get_option( 'rewrite_rules', array() );
	$key   = '^' . flxs_slug() . '/([A-Za-z0-9]{6,40})/?$';
	$cert_url = home_url( '/' . flxs_slug() . '/' );
	if ( ! get_option( 'permalink_structure' ) ) {
		$add( 'permalinks', $g_set, __( 'Sertifika sayfası adresi', 'flx-certificates' ), 'warning', __( 'Kalıcı bağlantılar "Düz" ayarında; sertifika linkleri ?flxs_kod= biçiminde çalışır. "Yazı adı" önerilir.', 'flx-certificates' ), admin_url( 'options-permalink.php' ), __( 'Kalıcı bağlantılar', 'flx-certificates' ) );
	} elseif ( ! isset( $rules[ $key ] ) ) {
		$add( 'permalinks', $g_set, __( 'Sertifika sayfası adresi', 'flx-certificates' ), 'error', __( 'Sertifika adres kuralı kayıtlı değil; linkler 404 verebilir. Kalıcı bağlantılar sayfasında "Değişiklikleri kaydet"e basın.', 'flx-certificates' ), admin_url( 'options-permalink.php' ), __( 'Kalıcı bağlantılar', 'flx-certificates' ) );
	} else {
		/* translators: %s: certificate base URL */
		$add( 'permalinks', $g_set, __( 'Sertifika sayfası adresi', 'flx-certificates' ), 'ok', sprintf( __( 'Sertifikalar %s{kod} adresinde açılır.', 'flx-certificates' ), $cert_url ) );
	}

	$tpls = flxs_list_templates( true );
	if ( $tpls ) {
		/* translators: %d: number of templates */
		$add( 'templates', $g_set, __( 'Sertifika şablonları', 'flx-certificates' ), 'ok', sprintf( _n( '%d aktif şablon var.', '%d aktif şablon var.', count( $tpls ), 'flx-certificates' ), count( $tpls ) ) );
	} else {
		$add( 'templates', $g_set, __( 'Sertifika şablonları', 'flx-certificates' ), 'error', __( 'Aktif şablon yok; sertifika oluşturulamaz.', 'flx-certificates' ), $page( 'flxs-sablonlar' ), __( 'Şablonlar', 'flx-certificates' ) );
	}

	$files = array( 'assets/vendor/pdf-lib.min.js', 'assets/vendor/fontkit.umd.min.js', 'assets/vendor/pdf.min.js', 'assets/vendor/pdf.worker.min.js', 'assets/vendor/qrcode.js', 'assets/js/renderer.js' );
	foreach ( flxs_builtin_fonts() as $f ) {
		$files[] = 'assets/fonts/' . $f[1];
	}
	$lost = array_filter( $files, function ( $f ) {
		return ! is_readable( FLXS_DIR . $f );
	} );
	if ( $lost ) {
		/* translators: %s: comma separated file names */
		$add( 'files', $g_set, __( 'Eklenti dosyaları', 'flx-certificates' ), 'error', sprintf( __( 'Eksik dosya: %s. Eklenti zip\'ini yeniden yükleyin.', 'flx-certificates' ), implode( ', ', array_map( 'basename', $lost ) ) ) );
	} else {
		$add( 'files', $g_set, __( 'Eklenti dosyaları', 'flx-certificates' ), 'ok', __( 'PDF çizici, QR ve yazı tipleri yerinde.', 'flx-certificates' ) );
	}

	if ( get_role( 'flxs_personel' ) ) {
		$add( 'role', $g_set, __( 'Personel rolü', 'flx-certificates' ), 'ok', __( '"Sertifika Personeli" rolü tanımlı.', 'flx-certificates' ) );
	} else {
		$add( 'role', $g_set, __( 'Personel rolü', 'flx-certificates' ), 'warning', __( '"Sertifika Personeli" rolü yok. Eklentiyi devre dışı bırakıp yeniden etkinleştirin.', 'flx-certificates' ), admin_url( 'plugins.php' ), __( 'Eklentiler', 'flx-certificates' ) );
	}

	/* ---------- Gönderim ve cron ---------- */
	$t       = flxs_table( 'messages' );
	$runner  = flxs_queue_runner_name();
	$last    = (int) get_option( 'flxs_queue_last_run', 0 );
	$waiting = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status = 'queued' AND scheduled_at <= %s", gmdate( 'Y-m-d H:i:s', time() - 600 ) ) ); // phpcs:ignore
	$no_cron = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	$uses_as = function_exists( 'as_schedule_single_action' );
	if ( ! $uses_as && ! wp_next_scheduled( 'flxs_queue_tick' ) ) {
		$add( 'cron', $g_snd, __( 'Gönderim kuyruğu (cron)', 'flx-certificates' ), 'error', __( 'Dakikalık kuyruk görevi zamanlanmamış. Herhangi bir yönetici sayfasını yenileyin; düzelmezse eklentiyi yeniden etkinleştirin.', 'flx-certificates' ), $page( 'flxs-kuyruk' ), __( 'Kuyruk', 'flx-certificates' ) );
	} elseif ( $waiting ) {
		/* translators: 1: number of messages, 2: queue runner name, 3: "x mins ago" or "never" */
		$add( 'cron', $g_snd, __( 'Gönderim kuyruğu (cron)', 'flx-certificates' ), 'error', sprintf( _n( '%1$d mesaj 10 dakikadan uzun süredir bekliyor; kuyruk çalışmıyor olabilir. İşleyici: %2$s, son çalışma: %3$s.', '%1$d mesaj 10 dakikadan uzun süredir bekliyor; kuyruk çalışmıyor olabilir. İşleyici: %2$s, son çalışma: %3$s.', $waiting, 'flx-certificates' ), $waiting, $runner, $last ? flxs_ago( $last ) : __( 'hiç', 'flx-certificates' ) ) . ( $no_cron ? ' ' . __( 'WP-Cron kapalı (DISABLE_WP_CRON); hosting panelinde wp-cron.php için dakikalık cron tanımlı olmalı.', 'flx-certificates' ) : '' ), $page( 'flxs-kuyruk' ), __( 'Kuyruk', 'flx-certificates' ) );
	} elseif ( $last && time() - $last > 3 * HOUR_IN_SECONDS ) {
		/* translators: 1: queue runner name, 2: "x hours ago" */
		$add( 'cron', $g_snd, __( 'Gönderim kuyruğu (cron)', 'flx-certificates' ), 'warning', sprintf( __( 'İşleyici: %1$s. Son çalışma %2$s; cron seyrek tetikleniyor. Bekleyen mesaj yok. Düzenli trafik yoksa hosting panelinden wp-cron.php için dakikalık cron önerilir.', 'flx-certificates' ), $runner, flxs_ago( $last ) ) );
	} else {
		/* translators: 1: queue runner name, 2: "x mins ago" or "not yet" */
		$add( 'cron', $g_snd, __( 'Gönderim kuyruğu (cron)', 'flx-certificates' ), $last ? 'ok' : 'info', sprintf( __( 'İşleyici: %1$s. Son çalışma: %2$s. Bekleyen eski mesaj yok.', 'flx-certificates' ), $runner, $last ? flxs_ago( $last ) : __( 'henüz kaydedilmedi', 'flx-certificates' ) ) );
	}

	$failed = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status = 'failed' AND updated_at >= %s", gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) ); // phpcs:ignore
	if ( $failed ) {
		/* translators: %d: number of failed messages */
		$add( 'failed', $g_snd, __( 'Son 24 saatte gönderim', 'flx-certificates' ), 'warning', sprintf( _n( '%d mesaj gönderilemedi.', '%d mesaj gönderilemedi.', $failed, 'flx-certificates' ), $failed ), add_query_arg( 'status', 'failed', $page( 'flxs-kuyruk' ) ), __( 'Başarısızları gör', 'flx-certificates' ) );
	} else {
		$sent = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status = 'sent' AND sent_at >= %s", gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) ); // phpcs:ignore
		/* translators: %d: number of sent messages */
		$add( 'failed', $g_snd, __( 'Son 24 saatte gönderim', 'flx-certificates' ), 'ok', sprintf( _n( '%d mesaj gönderildi, başarısız yok.', '%d mesaj gönderildi, başarısız yok.', $sent, 'flx-certificates' ), $sent ) );
	}

	if ( ! wp_next_scheduled( 'flxs_daily' ) ) {
		$add( 'daily', $g_snd, __( 'Günlük temizlik görevi', 'flx-certificates' ), 'warning', __( 'Günlük görev (eski kayıtların temizliği) zamanlanmamış; bir yönetici sayfasını yenileyin.', 'flx-certificates' ) );
	}

	// WhatsApp/SMS/e-posta gönderimi Flux Mail üzerinden (v1.3.0). Bağlantı testleri İletişim › Bağlantılar'da.
	$labels = array( 'whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => __( 'E-posta', 'flx-certificates' ) );
	if ( ! flxs_iletisim_active() ) {
		$add( 'iletisim', $g_snd, __( 'Flux Mail', 'flx-certificates' ), 'error', __( 'Flux Mail etkin değil. Sertifikalar WhatsApp ve SMS ile gönderilemez; e-posta WordPress\'in kendi gönderimiyle (wp_mail) gider. Flux Mail eklentisini kurup etkinleştirin.', 'flx-certificates' ), admin_url( 'plugins.php' ), __( 'Eklentiler', 'flx-certificates' ) );
	} else {
		flxs_sync_flxi_providers();
		foreach ( array( 'whatsapp', 'sms' ) as $ch ) {
			/* translators: %s: channel name (WhatsApp / SMS) */
			$label = sprintf( __( '%s gönderimi', 'flx-certificates' ), $labels[ $ch ] );
			$conns = flxi_list_connections( $ch, true );
			if ( $conns ) {
				$ok_at = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(sent_at) FROM $t WHERE channel = %s AND status = 'sent'", $ch ) ); // phpcs:ignore
				/* translators: %s: comma separated connection names */
				$msg = sprintf( __( 'Flux Mail bağlantısıyla gönderiliyor: %s.', 'flx-certificates' ), implode( ', ', wp_list_pluck( $conns, 'name' ) ) );
				if ( $ok_at ) {
					/* translators: %s: "x mins ago" */
					$msg .= ' ' . sprintf( __( 'Son başarılı gönderim %s.', 'flx-certificates' ), flxs_ago( strtotime( $ok_at . ' UTC' ) ) );
				}
				$add( 'provider_' . $ch, $g_snd, $label, 'ok', $msg, admin_url( 'admin.php?page=flxi-baglantilar' ), __( 'Bağlantılar', 'flx-certificates' ) );
			} elseif ( ! flxi_list_connections( $ch ) ) {
				// Kanal hiç kurulmamış: kullanılmıyor demektir, uyarı değil
				/* translators: %s: channel name */
				$add( 'provider_' . $ch, $g_snd, $label, 'info', sprintf( __( '%1$s bağlantısı kurulmamış; %1$s ile sertifika gönderilmez.', 'flx-certificates' ), $labels[ $ch ] ), admin_url( 'admin.php?page=flxi-baglantilar' ), __( 'Bağlantılar', 'flx-certificates' ) );
			} else {
				/* translators: %s: channel name */
				$add( 'provider_' . $ch, $g_snd, $label, 'warning', sprintf( __( 'Flux Mail\'de etkin %s bağlantısı yok; bu kanaldan sertifika gönderilemez.', 'flx-certificates' ), $labels[ $ch ] ), admin_url( 'admin.php?page=flxi-baglantilar' ), __( 'Bağlantılar', 'flx-certificates' ) );
			}
		}
		$add( 'provider_email', $g_snd, $labels['email'], 'ok', __( 'Sertifika e-postaları Flux Mail üzerinden kurumsal şablonla gider (İletişim › E-postalar).', 'flx-certificates' ), admin_url( 'admin.php?page=flxi' ), __( 'E-postalar', 'flx-certificates' ) );
	}

	/* ---------- Site ---------- */
	$theme = wp_get_theme();
	if ( current_theme_supports( 'flx-theme' ) ) {
		/* translators: %s: theme version */
		$add( 'theme', $g_ste, __( 'Tema uyumu', 'flx-certificates' ), 'ok', sprintf( __( 'FluxWP teması (%s) aktif; etkinlik bölümü ve sertifika sayfası tema ile uyumlu.', 'flx-certificates' ), $theme->get( 'Version' ) ) );
	} else {
		/* translators: %s: active theme name */
		$add( 'theme', $g_ste, __( 'Tema uyumu', 'flx-certificates' ), 'info', sprintf( __( 'Aktif tema: %s. Etkinlik bölümü yalnızca FluxWP temasında kendiliğinden görünür; diğer temalarda kısa kodla eklenir.', 'flx-certificates' ), $theme->get( 'Name' ) ) );
	}

	$open = flxs_get_events( 'open' );
	if ( ! $open ) {
		$add( 'events', $g_ste, __( 'Devam eden etkinlikler', 'flx-certificates' ), 'info', __( 'Açık etkinlik yok; sitede etkinlik bölümü gizlenir.', 'flx-certificates' ), $page( 'flxs-etkinlikler' ), __( 'Etkinlikler', 'flx-certificates' ) );
	} else {
		$off = array();
		foreach ( $open as $e ) {
			if ( ! (int) $e['post_id'] || 'publish' !== get_post_status( (int) $e['post_id'] ) ) {
				$off[] = $e['name'];
			}
		}
		if ( $off ) {
			/* translators: %s: comma separated event names */
			$add( 'events', $g_ste, __( 'Devam eden etkinlikler', 'flx-certificates' ), 'warning', sprintf( __( 'Sitede sayfası yayında olmayan açık etkinlik: %s. Etkinlik düzenleme ekranındaki "Sitede yayınla" ile düzeltin.', 'flx-certificates' ), implode( ', ', $off ) ), $page( 'flxs-etkinlikler' ), __( 'Etkinlikler', 'flx-certificates' ) );
		} else {
			/* translators: %d: number of open events */
			$add( 'events', $g_ste, __( 'Devam eden etkinlikler', 'flx-certificates' ), 'ok', sprintf( _n( '%d açık etkinlik var, sayfası sitede yayında.', '%d açık etkinlik var, sayfaları sitede yayında.', count( $open ), 'flx-certificates' ), count( $open ) ) );
		}
	}

	if ( flxs_setting( 'lookup_enabled' ) ) {
		$has = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE '%[flx\\_sertifika\\_sorgula%' LIMIT 1" ); // phpcs:ignore
		if ( $has ) {
			/* translators: %s: page URL */
			$add( 'lookup', $g_ste, __( 'Sertifika sorgulama', 'flx-certificates' ), 'ok', sprintf( __( 'Sorgulama formu %s sayfasında.', 'flx-certificates' ), wp_make_link_relative( get_permalink( $has ) ) ) );
		} else {
			$add( 'lookup', $g_ste, __( 'Sertifika sorgulama', 'flx-certificates' ), 'info', __( 'Sorgulama açık ama [flx_sertifika_sorgula] kısa kodu yayında bir sayfada yok.', 'flx-certificates' ) );
		}
	}

	if ( defined( 'FLXF_VERSION' ) || function_exists( 'flxf_submit_response' ) || class_exists( 'Emsfb' ) ) {
		$add( 'flxform', $g_ste, __( 'Flux Forms entegrasyonu', 'flx-certificates' ), 'ok', __( 'Flux Forms yüklü; form otomasyonları sertifika oluşturup gönderebilir.', 'flx-certificates' ) );
	}

	/* ---------- Sunucuda PDF ---------- */
	if ( ! flxs_render_available() ) {
		$add( 'render', $g_set, __( 'Sunucuda PDF üretimi', 'flx-certificates' ), 'warning', __( 'PHP GD (FreeType) eklentisi yok: PDF ve görseller yalnız tarayıcıda, sertifika sayfası ilk açıldığında oluşur.', 'flx-certificates' ) );
	} else {
		$miss = flxs_certificates_missing_files();
		if ( $miss ) {
			/* translators: %d: number of certificates */
			$add( 'render', $g_set, __( 'Sunucuda PDF üretimi', 'flx-certificates' ), 'warning', sprintf( __( '%d sertifikanın PDF\'i ya da görseli eksik.', 'flx-certificates' ), $miss ), add_query_arg( array( 'action' => 'flxs_render_missing', '_wpnonce' => wp_create_nonce( 'flxs_render_missing' ) ), admin_url( 'admin-post.php' ) ), __( 'Eksikleri oluştur', 'flx-certificates' ) );
		} else {
			$add( 'render', $g_set, __( 'Sunucuda PDF üretimi', 'flx-certificates' ), 'ok', __( 'Açık. Yeni sertifikaların PDF\'i ve görselleri sunucuda arka planda oluşur.', 'flx-certificates' ) );
		}
	}

	return $c;
}

add_filter( 'flx_system_fixes', 'flxs_system_fixes' );
function flxs_system_fixes( $fixes ) {
	if ( ! flxs_render_available() ) {
		return $fixes;
	}
	$fixes['flxs.render_missing'] = array(
		'label'       => __( 'Eksik sertifika dosyalarını oluştur', 'flx-certificates' ),
		'description' => __( 'PDF\'i ya da görseli eksik en çok 25 sertifikanın dosyalarını sunucuda üretir.', 'flx-certificates' ),
		'source'      => 'Flux Certificates',
		'checks'      => array( 'flxs_render' ),
		'current'     => function () {
			return flxs_certificates_missing_files();
		},
		'run'         => function () {
			list( $ok, $fail ) = flxs_render_missing( 25 );
			/* translators: 1: done count, 2: failed count */
			return sprintf( __( '%1$d sertifikanın dosyaları oluşturuldu, %2$d hata.', 'flx-certificates' ), $ok, $fail );
		},
	);
	return $fixes;
}

add_action( 'admin_post_flxs_render_missing', 'flxs_handle_render_missing' );
function flxs_handle_render_missing() {
	check_admin_referer( 'flxs_render_missing' );
	if ( ! current_user_can( 'flxs_manage' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Yetki yok', 'flx-certificates' ) );
	}
	list( $ok, $fail ) = flxs_render_missing( 25 );
	$left = flxs_certificates_missing_files();
	/* translators: 1: done count, 2: failed count */
	$msg = sprintf( __( '%1$d sertifikanın dosyaları oluşturuldu, %2$d hata.', 'flx-certificates' ), $ok, $fail );
	if ( $left && $ok ) {
		/* translators: %d: remaining count */
		$msg .= ' ' . sprintf( __( '%d sertifika kaldı, tekrar basın.', 'flx-certificates' ), $left );
	}
	wp_safe_redirect( add_query_arg( 'flxs_msg', rawurlencode( $msg ), wp_get_referer() ? wp_get_referer() : admin_url() ) );
	exit;
}
