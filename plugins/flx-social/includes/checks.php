<?php
/**
 * Sistem kontrolü (filter `flx_system_checks`). Dış HTTP isteği yapmaz.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_system_checks',
	function ( $checks ) {
		return array_merge( is_array( $checks ) ? $checks : array(), flxsm_system_checks() );
	}
);

add_filter(
	'flx_system_checks_versions',
	function ( $v ) {
		$v                = (array) $v;
		$v['flx-social'] = FLXSM_VERSION;
		return $v;
	}
);

function flxsm_system_checks() {
	global $wpdb;
	$out = array();
	$url = flxsm_app_url( '/settings' );
	$add = function ( $id, $label, $status, $message, $action = '', $action_label = '' ) use ( &$out ) {
		$c = array(
			'id'      => 'flx-social/' . $id,
			'source'  => 'Flux Social',
			'group'   => __( 'Sosyal medya', 'flx-social' ),
			'label'   => $label,
			'status'  => $status,
			'message' => $message,
		);
		if ( $action ) {
			$c['action_url']   = $action;
			$c['action_label'] = $action_label ? $action_label : __( 'Aç', 'flx-social' );
		}
		$out[] = $c;
	};

	$missing = array();
	foreach ( array( 'accounts', 'posts', 'targets', 'metrics', 'log' ) as $t ) {
		$name = flxsm_table( $t );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ) !== $name ) { // phpcs:ignore WordPress.DB
			$missing[] = $name;
		}
	}
	/* translators: %s: table names */
	$add( 'tables', __( 'Veritabanı tabloları', 'flx-social' ), $missing ? 'error' : 'ok', $missing ? sprintf( __( 'Eksik tablo: %s. Eklentiyi devre dışı bırakıp yeniden etkinleştirin.', 'flx-social' ), implode( ', ', $missing ) ) : __( 'Tablolar hazır.', 'flx-social' ) );

	$sodium = function_exists( 'sodium_crypto_secretbox' );
	$add( 'crypto', __( 'Anahtar şifreleme', 'flx-social' ), $sodium ? 'ok' : 'warning', $sodium ? __( 'Anahtarlar ve belirteçler şifreli saklanıyor (sodium).', 'flx-social' ) : __( 'PHP sodium uzantısı kapalı; anahtarlar daha zayıf yöntemle saklanıyor. Hostinger › PHP uzantılarından sodium’u açın.', 'flx-social' ) );

	$ready = array();
	$none  = array();
	foreach ( flxsm_apps_public() as $a ) {
		if ( 'whatsapp' === $a['id'] ) {
			continue;
		}
		if ( $a['ready'] ) {
			$ready[] = $a['label'];
		} else {
			$none[] = $a['label'];
		}
	}
	/* translators: %s: app names */
	$add( 'apps', __( 'Ağ uygulamaları', 'flx-social' ), $ready ? 'ok' : 'warning', $ready ? sprintf( __( 'Anahtarı girilmiş: %s.', 'flx-social' ), implode( ', ', $ready ) ) : __( 'Hiçbir ağın uygulama anahtarı girilmemiş. Sosyal Medya › Ayarlar › Uygulamalar.', 'flx-social' ), $url, __( 'Ayarlar', 'flx-social' ) );

	$accs = flxsm_accounts();
	$bad  = array_filter( $accs, fn( $a ) => 'active' !== $a['status'] );
	if ( ! $accs ) {
		$add( 'accounts', __( 'Bağlı hesaplar', 'flx-social' ), 'info', __( 'Henüz hesap bağlanmadı.', 'flx-social' ), flxsm_app_url( '/accounts' ), __( 'Hesaplar', 'flx-social' ) );
	} elseif ( $bad ) {
		/* translators: %s: account names */
		$add( 'accounts', __( 'Bağlı hesaplar', 'flx-social' ), 'warning', sprintf( __( 'Yeniden bağlanması gereken hesap: %s.', 'flx-social' ), implode( ', ', array_map( fn( $a ) => $a['network'] . ' › ' . $a['name'], $bad ) ) ), flxsm_app_url( '/accounts' ), __( 'Hesaplar', 'flx-social' ) );
	} else {
		/* translators: %d: count */
		$add( 'accounts', __( 'Bağlı hesaplar', 'flx-social' ), 'ok', sprintf( __( '%d hesap bağlı ve etkin.', 'flx-social' ), count( $accs ) ) );
	}

	$beat  = (array) get_option( 'flxsm_last_tick', array() );
	$ext   = (int) ( $beat['external'] ?? 0 );
	$any   = (int) ( $beat['any'] ?? 0 );
	$due   = flxsm_due_count( -300 );
	$lerr  = (array) get_option( 'flxsm_loopback_err', array() );
	if ( $ext > time() - 10 * MINUTE_IN_SECONDS ) {
		$add( 'tick', __( 'Zamanlanmış gönderiler', 'flx-social' ), 'ok', __( 'Dışarıdan dakikalık tetik çalışıyor.', 'flx-social' ) );
	} elseif ( $due ) {
		/* translators: %d: count */
		$add( 'tick', __( 'Zamanlanmış gönderiler', 'flx-social' ), 'error', sprintf( __( '%d gönderi 5 dakikadan uzun süredir sırasını bekliyor. Ayarlar › Genel’deki tetik adresini cron-job.org’a (her dakika) ekleyin.', 'flx-social' ), $due ), $url, __( 'Ayarlar', 'flx-social' ) );
	} else {
		$msg = $any ? __( 'Gönderiler WP-Cron ve arka plan isteğiyle işleniyor; zamanında çıkması için Ayarlar › Genel’deki tetik adresini cron-job.org’a (her dakika) eklemeniz önerilir.', 'flx-social' ) : __( 'Tetik henüz hiç çalışmadı. Ayarlar › Genel’deki tetik adresini cron-job.org’a (her dakika) ekleyin.', 'flx-social' );
		if ( ! empty( $lerr['at'] ) && $lerr['at'] > time() - DAY_IN_SECONDS ) {
			$msg .= ' ' . __( 'Arka plan isteği hatası:', 'flx-social' ) . ' ' . $lerr['msg'];
		}
		$add( 'tick', __( 'Zamanlanmış gönderiler', 'flx-social' ), 'warning', $msg, $url, __( 'Ayarlar', 'flx-social' ) );
	}

	$failed = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxsm_table( 'targets' ) . " WHERE status = 'failed' AND updated > %d", time() - 7 * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DB
	/* translators: %d: count */
	$add( 'failed', __( 'Başarısız paylaşımlar', 'flx-social' ), $failed ? 'warning' : 'ok', $failed ? sprintf( __( 'Son 7 günde %d paylaşım başarısız. Gönderiler › Başarısız.', 'flx-social' ), $failed ) : __( 'Son 7 günde başarısız paylaşım yok.', 'flx-social' ), $failed ? flxsm_app_url( '/posts?status=failed' ) : '' );

	$https = 0 === strpos( home_url(), 'https:' );
	$add( 'https', __( 'Medya adresleri', 'flx-social' ), $https ? 'ok' : 'error', $https ? __( 'Instagram, TikTok ve Facebook görselleri sitenin HTTPS adresinden çeker.', 'flx-social' ) : __( 'Site HTTPS değil; Instagram/TikTok/Facebook görsel ve videoları çekemez.', 'flx-social' ) );

	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'flxsm';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	$ok = is_dir( $dir ) && wp_is_writable( $dir );
	$add( 'uploads', __( 'Dosya klasörü', 'flx-social' ), $ok ? 'ok' : 'warning', $ok ? __( 'wp-content/uploads/flxsm yazılabilir (Instagram için JPEG kopyalar).', 'flx-social' ) : __( 'wp-content/uploads/flxsm yazılamıyor; PNG/WebP görseller Instagram’a gönderilemez.', 'flx-social' ) );

	$add( 'ai', __( 'AI yazım yardımı', 'flx-social' ), flxsm_ai_ready() ? 'ok' : 'info', flxsm_ai_ready() ? __( 'Flux AI hazır: metin yazma, kısaltma, ağlara uyarlama.', 'flx-social' ) : __( 'Flux AI & MCP kurulu değil ya da anahtar yok; AI düğmeleri gizlenir, paylaşım çalışır.', 'flx-social' ), admin_url( 'admin.php?page=flx-ai-settings' ), 'Flux AI' );
	return $out;
}
