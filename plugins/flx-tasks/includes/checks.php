<?php
/**
 * Sistem kontrolü: FluxWP eklenti/tema ortak sözleşmesi (filter `flx_system_checks`).
 * Her kontrol ucuzdur, dış HTTP isteği yapmaz.
 *
 * @package FLX_Gorev
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'flx_system_checks', 'flxg_system_checks_filter' );

function flxg_system_checks_filter( $checks ) {
	return array_merge( is_array( $checks ) ? $checks : array(), flxg_system_checks() );
}

/** @return array[] {id, source, group, label, status ok|warning|error|info, message, action_url?, action_label?} */
function flxg_system_checks() {
	global $wpdb;
	$src      = 'Flux Tasks';
	$settings = admin_url( 'admin.php?page=flx-tasks-ayarlar' );
	$out      = array();
	$add      = function ( $id, $group, $label, $status, $message, $url = '', $url_label = '' ) use ( &$out, $src ) {
		$c = array(
			'id'      => 'flx-tasks/' . $id,
			'source'  => $src,
			'group'   => $group,
			'label'   => $label,
			'status'  => $status,
			'message' => $message,
		);
		if ( $url ) {
			$c['action_url']   = $url;
			$c['action_label'] = $url_label ? $url_label : __( 'Aç', 'flx-tasks' );
		}
		$out[] = $c;
	};
	$g_sys = __( 'Kurulum', 'flx-tasks' );

	// Sürümler.
	$wp_ok  = version_compare( get_bloginfo( 'version' ), '6.5', '>=' );
	$php_ok = version_compare( PHP_VERSION, '7.4', '>=' );
	/* translators: 1: WordPress version, 2: PHP version */
	$add( 'versions', $g_sys, __( 'WordPress ve PHP sürümü', 'flx-tasks' ), $wp_ok && $php_ok ? 'ok' : 'error', sprintf( __( 'WordPress %1$s, PHP %2$s. Gereken: WordPress 6.5+, PHP 7.4+.', 'flx-tasks' ), get_bloginfo( 'version' ), PHP_VERSION ) );

	// Tablolar.
	$need    = array( 'spaces', 'lists', 'tasks', 'comments', 'files', 'activity', 'notifications' );
	$missing = array();
	foreach ( $need as $t ) {
		$name = flxg_table( $t );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ) !== $name ) { // phpcs:ignore
			$missing[] = $name;
		}
	}
	if ( $missing ) {
		/* translators: %s: table names */
		$add( 'tables', $g_sys, __( 'Veritabanı tabloları', 'flx-tasks' ), 'error', sprintf( __( 'Eksik tablo: %s. Eklentiyi devre dışı bırakıp yeniden etkinleştirin.', 'flx-tasks' ), implode( ', ', $missing ) ), admin_url( 'plugins.php' ), __( 'Eklentiler', 'flx-tasks' ) );
	} else {
		/* translators: %s: db version */
		$add( 'tables', $g_sys, __( 'Veritabanı tabloları', 'flx-tasks' ), get_option( 'flxg_db_version' ) === FLXG_DB_VERSION ? 'ok' : 'warning', sprintf( __( 'Tablolar hazır (şema %s).', 'flx-tasks' ), (string) get_option( 'flxg_db_version' ) ) );
	}

	// Dosya klasörü.
	$up  = wp_upload_dir( null, false );
	$dir = trailingslashit( $up['basedir'] ) . 'flx-tasks';
	if ( ! is_dir( $dir ) || ! wp_is_writable( $dir ) ) {
		$add( 'uploads', $g_sys, __( 'Dosya klasörü', 'flx-tasks' ), 'error', __( 'wp-content/uploads/flx-tasks yok ya da yazılamıyor. Dosya ve görsel yüklenemez; klasör izinlerini (755) kontrol edin.', 'flx-tasks' ) );
	} elseif ( ! file_exists( $dir . '/.htaccess' ) ) {
		$add( 'uploads', $g_sys, __( 'Dosya klasörü', 'flx-tasks' ), 'warning', __( 'Klasör yazılabilir ama koruma dosyası (.htaccess) yok; dosyalar doğrudan adresle açılabilir. Eklentiyi yeniden etkinleştirin.', 'flx-tasks' ) );
	} else {
		$add( 'uploads', $g_sys, __( 'Dosya klasörü', 'flx-tasks' ), 'ok', __( 'Yazılabilir ve dışarıya kapalı.', 'flx-tasks' ) );
	}

	// Yükleme sınırı.
	$set_mb = (int) flxg_setting( 'max_upload_mb' );
	$php_b  = (int) wp_max_upload_size();
	/* translators: 1: effective limit, 2: server limit */
	$add( 'upload_limit', $g_sys, __( 'Yükleme sınırı', 'flx-tasks' ), $set_mb * MB_IN_BYTES > $php_b ? 'warning' : 'ok', sprintf( __( 'Geçerli sınır %1$s (sunucu sınırı %2$s).', 'flx-tasks' ), size_format( flxg_max_upload_bytes() ), size_format( $php_b ) ) . ( $set_mb * MB_IN_BYTES > $php_b ? ' ' . __( 'Ayardaki değer sunucu sınırından büyük; hosting panelinden upload_max_filesize ve post_max_size artırılabilir.', 'flx-tasks' ) : '' ), $settings, __( 'Ayarlar', 'flx-tasks' ) );

	// Zamanlanmış işler (WP-Cron).
	$g_cron = __( 'Zamanlanmış işler', 'flx-tasks' );
	$next   = wp_next_scheduled( 'flxg_daily' );
	if ( ! $next ) {
		$add( 'cron', $g_cron, __( 'Günlük görev hatırlatmaları', 'flx-tasks' ), 'error', __( 'Zamanlanmamış: geciken/yaklaşan görev bildirimleri ve eski geçici dosya temizliği çalışmaz. Görevler sayfasını bir kez açmak yeniden zamanlar.', 'flx-tasks' ), admin_url( 'admin.php?page=flx-tasks' ), __( 'Görevler', 'flx-tasks' ) );
	} elseif ( $next < time() - 2 * HOUR_IN_SECONDS ) {
		/* translators: %s: relative time, e.g. "3 saat önce" */
		$add( 'cron', $g_cron, __( 'Günlük görev hatırlatmaları', 'flx-tasks' ), 'warning', sprintf( __( 'Zamanlanmış iş gecikti (%s çalışmalıydı). WP-Cron tetiklenmiyor olabilir (site az ziyaret alıyorsa hosting panelinden gerçek cron önerilir).', 'flx-tasks' ), flx_time_ago( $next ) ) );
	} else {
		/* translators: %s: date */
		$add( 'cron', $g_cron, __( 'Günlük görev hatırlatmaları', 'flx-tasks' ), 'ok', sprintf( __( 'Sonraki çalışma: %s.', 'flx-tasks' ), flx_date( $next, 'datetime' ) ) );
	}
	if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
		$add( 'cron_mode', $g_cron, __( 'WP-Cron modu', 'flx-tasks' ), 'info', __( 'DISABLE_WP_CRON açık: zamanlanmış işler yalnız sunucu cron\'u wp-cron.php\'yi çağırırsa çalışır. Hosting panelinde bu cron tanımlı olmalı.', 'flx-tasks' ) );
	}

	// Canlı güncelleme ve önbellek.
	$g_live = __( 'Canlı güncelleme', 'flx-tasks' );
	/* translators: %d: seconds */
	$add( 'poll', $g_live, __( 'Güncelleme aralığı', 'flx-tasks' ), 'info', sprintf( __( 'Ekrandayken 10 sn, 2 dk hareketsizken %d sn; arka plandaki sekme istek atmaz.', 'flx-tasks' ), max( 15, (int) flxg_setting( 'poll_interval' ) ) ), $settings, __( 'Ayarlar', 'flx-tasks' ) );
	$add( 'cache', $g_live, __( 'API önbelleği', 'flx-tasks' ), 'ok', defined( 'LSCWP_V' ) ? __( 'LiteSpeed Cache açık; görev API yanıtları önbelleğe alınmaması için işaretleniyor.', 'flx-tasks' ) : __( 'Görev API yanıtları önbelleğe alınmaz.', 'flx-tasks' ) );

	// Bildirimler.
	$g_n = __( 'Bildirimler', 'flx-tasks' );
	$add( 'email', $g_n, __( 'E-posta bildirimleri', 'flx-tasks' ), 'info', flxg_setting( 'email_notify' ) ? __( 'Açık. E-postalar WordPress\'in e-posta ayarıyla gider (SMTP eklentisi önerilir).', 'flx-tasks' ) : __( 'Kapalı; bildirimler yalnız uygulama içinde.', 'flx-tasks' ), $settings, __( 'Ayarlar', 'flx-tasks' ) );

	// Yapay zekâ ve MCP.
	$g_ai = __( 'Yapay zekâ ve MCP', 'flx-tasks' );
	$ai_url = admin_url( flxg_ai_hub() ? 'admin.php?page=flx-ai-settings' : 'plugins.php' );
	if ( flxg_ai_ready() ) {
		/* translators: %s: model id */
		$add( 'ai', $g_ai, __( 'Yapay zekâ', 'flx-tasks' ), 'ok', sprintf( __( 'Flux AI üzerinden, model: %s.', 'flx-tasks' ), flxg_ai_model() ), $ai_url, __( 'Flux AI', 'flx-tasks' ) );
	} else {
		$add( 'ai', $g_ai, __( 'Yapay zekâ', 'flx-tasks' ), 'warning', flxg_ai_off_message(), $ai_url, flxg_ai_hub() ? __( 'Flux AI', 'flx-tasks' ) : __( 'Eklentiler', 'flx-tasks' ) );
	}
	$keys = count( flxg_mcp_keys() );
	/* translators: %d: key count */
	$add( 'mcp', $g_ai, __( 'MCP sunucusu', 'flx-tasks' ), 'info', flxg_setting( 'mcp_enabled' ) ? sprintf( __( 'Açık, %d kişisel anahtar var.', 'flx-tasks' ), $keys ) : __( 'Kapalı.', 'flx-tasks' ), $settings, __( 'Ayarlar', 'flx-tasks' ) );

	return $out;
}
