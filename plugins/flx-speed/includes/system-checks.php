<?php
/**
 * Ortak Sistem Kontrolü: Flux Speed'ın satırları (flx_system_checks), yapay zekâ düzeltmeleri
 * (flx_system_fixes) ve ekranın yeni yeri (Performans › Sistem Kontrolü). Kontroller ucuz, dış HTTP yok.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_system_checks',
	function ( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		$src    = __( 'Flux Speed', 'flx-speed' );
		$page   = admin_url( 'admin.php?page=flx-speed&tab=records' );
		$s      = flxp_settings();

		if ( (int) get_option( 'flxp_db_version', 0 ) < 1 ) {
			$checks[] = array(
				'id'      => 'flxp.table',
				'source'  => $src,
				'group'   => 'Plugins',
				'label'   => __( 'Kayıt tablosu', 'flx-speed' ),
				'status'  => 'error',
				'message' => __( 'Tablo kurulmamış. Eklentiyi devre dışı bırakıp yeniden etkinleştirin.', 'flx-speed' ),
			);
			return $checks;
		}

		$checks[] = array(
			'id'           => 'flxp.record',
			'source'       => $src,
			'group'        => 'Plugins',
			'label'        => __( 'İstek kaydı', 'flx-speed' ),
			'status'       => $s['record'] ? 'ok' : 'info',
			'message'      => $s['record']
				/* translators: 1: örnekleme yüzdesi, 2: yavaş eşiği ms */
				? sprintf( __( 'Açık: isteklerin %%%1$d kadarı örnekleniyor, %2$d ms üstü ve hatalı istekler kaydediliyor.', 'flx-speed' ), (int) $s['sample'], (int) $s['slow_ms'] )
				: __( 'Kapalı: yalnız anlık panel çalışıyor, yapay zekâ geçmişi okuyamaz.', 'flx-speed' ),
			'action_url'   => admin_url( 'admin.php?page=flx-speed&tab=settings' ),
			'action_label' => __( 'Ayarlar', 'flx-speed' ),
		);

		$fatal  = FLXP_Store::count_flag( 'fatal', 24 );
		$errors = FLXP_Store::count_flag( 'error', 24 ) + $fatal + FLXP_Store::count_flag( '5xx', 24 );
		$checks[] = array(
			'id'           => 'flxp.errors_24h',
			'source'       => $src,
			'group'        => 'Plugins',
			'label'        => __( 'Son 24 saatte hatalı istek', 'flx-speed' ),
			'status'       => $fatal ? 'error' : ( $errors ? 'warning' : 'ok' ),
			'message'      => $errors
				/* translators: 1: hatalı istek sayısı, 2: ölümcül hata sayısı */
				? sprintf( __( '%1$d istekte PHP/sunucu hatası, %2$d ölümcül. Ayrıntı Kayıtlar\'da.', 'flx-speed' ), $errors, $fatal )
				: __( 'Kayıtlı hata yok.', 'flx-speed' ),
			'action_url'   => add_query_arg( array( 'hours' => 24, 'flag' => $fatal ? 'fatal' : 'error' ), $page ),
			'action_label' => __( 'Kayıtlar', 'flx-speed' ),
		);

		$slow     = FLXP_Store::count_flag( 'slow', 24 );
		$checks[] = array(
			'id'           => 'flxp.slow_24h',
			'source'       => $src,
			'group'        => 'Plugins',
			'label'        => __( 'Son 24 saatte yavaş istek', 'flx-speed' ),
			'status'       => $slow > 20 ? 'warning' : ( $slow ? 'info' : 'ok' ),
			/* translators: 1: sayı, 2: eşik ms */
			'message'      => sprintf( __( '%1$d istek %2$d ms eşiğini aştı.', 'flx-speed' ), $slow, (int) $s['slow_ms'] ),
			'action_url'   => add_query_arg( array( 'hours' => 24, 'flag' => 'slow', 'order' => 'slow' ), $page ),
			'action_label' => __( 'Kayıtlar', 'flx-speed' ),
		);

		$http     = FLXP_Store::count_flag( 'http_fail', 24 );
		$checks[] = array(
			'id'           => 'flxp.http_24h',
			'source'       => $src,
			'group'        => 'Plugins',
			'label'        => __( 'Başarısız dış istek', 'flx-speed' ),
			'status'       => $http ? 'warning' : 'ok',
			/* translators: %d: sayı */
			'message'      => $http ? sprintf( __( 'Son 24 saatte %d kayıtlı istekte dış servis hatası.', 'flx-speed' ), $http ) : __( 'Kayıtlı dış servis hatası yok.', 'flx-speed' ),
			'action_url'   => add_query_arg( array( 'hours' => 24, 'flag' => 'http_fail' ), $page ),
			'action_label' => __( 'Kayıtlar', 'flx-speed' ),
		);

		if ( FLXP_Collector::$sq_preset ) {
			$checks[] = array(
				'id'      => 'flxp.savequeries',
				'source'  => $src,
				'group'   => 'Config',
				'label'   => 'SAVEQUERIES',
				'status'  => 'warning',
				'message' => __( 'wp-config.php\'de SAVEQUERIES açık: her istekte tüm sorgular bellekte tutuluyor. Flux Speed bunu gerektiğinde kendisi açar; wp-config\'ten kaldırın.', 'flx-speed' ),
			);
		}

		$tools    = function_exists( 'flxseo_mcp_tools' ) ? flxseo_mcp_tools() : array();
		$checks[] = array(
			'id'      => 'flxp.mcp',
			'source'  => $src,
			'group'   => 'Plugins',
			'label'   => __( 'Yapay zekâ araçları (flxp_*)', 'flx-speed' ),
			'status'  => isset( $tools['flxp_summary'] ) ? 'ok' : 'info',
			'message' => isset( $tools['flxp_summary'] )
				? __( 'MCP ve Flux AI sohbeti kayıtları okuyabilir.', 'flx-speed' )
				: __( 'Flux SEO & MCP etkin değil ya da MCP kapalı; araçlar görünmüyor.', 'flx-speed' ),
		);
		// Tema kendi cron satırlarını (theme.cron.*) veriyorsa aynı şeyi iki kez gösterme.
		$own = array_filter(
			flxp_server_checks(),
			function ( $c ) {
				return ! ( 'flxp.cron' === $c['id'] && function_exists( 'flx_sk_item' ) );
			}
		);
		return array_merge( $checks, array_values( $own ) );
	}
);

/**
 * Sunucu ve veritabanı sağlığı: autoload, cron, disk, yük, OPcache, süresi dolmuş geçici veriler.
 * Özet'teki "Öneriler" ve Başlangıç widget'ı da bunları kullanır.
 */
function flxp_server_checks() {
	global $wpdb;
	$src  = __( 'Flux Speed', 'flx-speed' );
	$url  = function ( $tab ) {
		return admin_url( 'admin.php?page=flx-speed&tab=' . $tab );
	};
	$out  = array();
	$live = flxp_server_live();

	$auto  = $wpdb->get_row( "SELECT COUNT(*) AS n, SUM(LENGTH(option_value)) AS b FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto-on','auto')", ARRAY_A ); // phpcs:ignore
	$kb    = (int) round( (float) ( $auto['b'] ?? 0 ) / 1024 );
	$out[] = array(
		'id'           => 'flxp.autoload',
		'source'       => $src,
		'group'        => 'Performance',
		'label'        => __( 'Autoload seçenekleri', 'flx-speed' ),
		'status'       => $kb > 1600 ? 'error' : ( $kb > 800 ? 'warning' : 'ok' ),
		/* translators: 1: KB, 2: adet */
		'message'      => sprintf( __( 'Her istekte %1$d KB (%2$d seçenek) yükleniyor.', 'flx-speed' ), $kb, (int) ( $auto['n'] ?? 0 ) ) . ( $kb > 800 ? ' ' . __( '800 KB altı önerilir; en büyük satırlara bakın.', 'flx-speed' ) : '' ),
		'action_url'   => $url( 'autoload' ),
		'action_label' => 'Autoload',
	);

	$cron  = flxp_cron();
	$stale = ! $cron['last_seen'] || $cron['last_seen'] < time() - HOUR_IN_SECONDS;
	$msg   = $cron['last_seen']
		/* translators: %s: süre */
		? sprintf( __( 'Son çalışma %s.', 'flx-speed' ), flx_time_ago( $cron['last_seen'] ) )
		: __( 'Cron\'un çalıştığı henüz görülmedi.', 'flx-speed' );
	if ( $cron['overdue'] ) {
		/* translators: %d: adet */
		$msg .= ' ' . sprintf( __( '%d görev gecikmiş.', 'flx-speed' ), $cron['overdue'] );
	}
	$out[] = array(
		'id'           => 'flxp.cron',
		'source'       => $src,
		'group'        => 'Cron',
		'label'        => __( 'Zamanlanmış görevler çalışıyor', 'flx-speed' ),
		'status'       => ( $stale && $cron['overdue'] ) ? 'error' : ( ( $stale || $cron['overdue'] > 5 ) ? 'warning' : 'ok' ),
		'message'      => $msg,
		'action_url'   => $url( 'cron' ),
		'action_label' => 'Cron',
	);

	if ( null !== $live['disk_pct'] ) {
		$out[] = array(
			'id'           => 'flxp.disk',
			'source'       => $src,
			'group'        => 'Performance',
			'label'        => __( 'Disk alanı', 'flx-speed' ),
			'status'       => $live['disk_pct'] >= 95 ? 'error' : ( $live['disk_pct'] >= 90 ? 'warning' : 'ok' ),
			/* translators: 1: yüzde, 2: boş alan */
			'message'      => sprintf( __( '%%%1$d dolu, %2$s boş.', 'flx-speed' ), $live['disk_pct'], size_format( (float) $live['disk_free'], 1 ) ) . ( $live['disk_shared'] ? ' ' . __( '(hesap kullanımı / Ayarlar\'daki kota)', 'flx-speed' ) : '' ),
			'action_url'   => $url( 'server' ),
			'action_label' => __( 'Sunucu', 'flx-speed' ),
		);
	}

	if ( null !== $live['load_pct'] ) {
		$out[] = array(
			'id'           => 'flxp.load',
			'source'       => $src,
			'group'        => 'Performance',
			'label'        => __( 'İşlemci yükü', 'flx-speed' ),
			'status'       => $live['load_pct'] >= 150 ? 'warning' : ( $live['load_pct'] >= 100 ? 'info' : 'ok' ),
			'message'      => 'account' === $live['scope']
				/* translators: 1: yüzde, 2: çekirdek sınırı */
				? sprintf( __( 'Hesap işlemci sınırının %%%1$d kadarı kullanılıyor (%2$s çekirdek).', 'flx-speed' ), $live['load_pct'], number_format_i18n( $live['cores'], 1 ) )
				/* translators: 1: yüzde, 2: çekirdek sayısı */
				: sprintf( __( 'Son 1 dakikada %%%1$d (%2$d çekirdek).', 'flx-speed' ), $live['load_pct'], $live['cores'] ),
			'action_url'   => $url( 'server' ),
			'action_label' => __( 'Sunucu', 'flx-speed' ),
		);
	}

	$opc = function_exists( 'opcache_get_status' ) ? @opcache_get_status( false ) : false; // phpcs:ignore
	$on  = is_array( $opc ) && ! empty( $opc['opcache_enabled'] );
	$out[] = array(
		'id'           => 'flxp.opcache',
		'source'       => $src,
		'group'        => 'Performance',
		'label'        => 'OPcache',
		'status'       => $on ? 'ok' : ( false === $opc && ini_get( 'opcache.enable' ) ? 'info' : 'warning' ),
		'message'      => $on
			? __( 'Açık: PHP dosyaları derlenmiş halde bellekte.', 'flx-speed' )
			: ( false === $opc && ini_get( 'opcache.enable' ) ? __( 'Açık görünüyor ama durum okunamıyor (sunucu kısıtlı).', 'flx-speed' ) : __( 'Kapalı: her istekte PHP dosyaları yeniden derleniyor. Hostinger › PHP yapılandırmasından açın.', 'flx-speed' ) ),
		'action_url'   => $url( 'server' ),
		'action_label' => __( 'Sunucu', 'flx-speed' ),
	);

	$exp   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( '_transient_timeout_' ) . '%', time() ) );
	$out[] = array(
		'id'           => 'flxp.transients',
		'source'       => $src,
		'group'        => 'Performance',
		'label'        => __( 'Süresi dolmuş geçici veriler', 'flx-speed' ),
		'status'       => $exp > 500 ? 'warning' : 'ok',
		/* translators: %d: adet */
		'message'      => $exp ? sprintf( __( '%d süresi dolmuş geçici veri tabloda duruyor.', 'flx-speed' ), $exp ) : __( 'Yok.', 'flx-speed' ),
		'action_url'   => $url( 'database' ),
		'action_label' => __( 'Veritabanı', 'flx-speed' ),
	);
	return $out;
}

/** Yapay zekânın (Flux SEO & MCP system_fix) güvenle çalıştırabileceği temizlik. */
add_filter(
	'flx_system_fixes',
	function ( $fixes ) {
		$fixes = is_array( $fixes ) ? $fixes : array();
		$fixes['flxp_expired_transients'] = array(
			'label'       => __( 'Süresi dolmuş geçici verileri sil', 'flx-speed' ),
			'description' => 'Süresi dolmuş transient satırlarını siler (delete_expired_transients). Geri alınması gerekmez; eklentiler gerekirse yeniden oluşturur.',
			'checks'      => array( 'flxp.transients' ),
			'source'      => 'Flux Speed',
			'run'         => function () {
				/* translators: %d: adet */
				return sprintf( __( '%d geçici veri silindi.', 'flx-speed' ), (int) flxp_cleanup( 'expired_transients' ) );
			},
		);
		return $fixes;
	}
);

/*
 * Sistem Kontrolü ekranı artık burada: ortak uyarı (Başlangıç + üst çubuk) bu sayfaya bağlanır.
 * Tema 20 öncelikle kendi ekranını verir; 30 ile Flux Speed kazanır.
 */
function flxp_system_checks_url() {
	return admin_url( 'admin.php?page=flx-speed&tab=system' );
}
add_filter( 'flx_system_checks_page_url', 'flxp_system_checks_url', 30 );
add_filter(
	'flx_system_checks_versions',
	function ( $v ) {
		$v                  = (array) $v;
		$v['flx-speed'] = FLXP_VERSION;
		return $v;
	}
);
add_filter(
	'flx_system_checks_labels',
	function ( $l ) {
		return array_merge(
			(array) $l,
			array(
				'title'       => __( 'Sistem Kontrolü', 'flx-speed' ),
				'intro'       => __( 'Her satır sitenin ihtiyaç duyduğu bir şeyi ve yerinde olup olmadığını gösterir. Önce hataları düzeltin; uyarılara da göz atın.', 'flx-speed' ),
				'ok'          => __( 'Tamam', 'flx-speed' ),
				'warning'     => __( 'Uyarı', 'flx-speed' ),
				'error'       => __( 'Hata', 'flx-speed' ),
				'info'        => __( 'Bilgi', 'flx-speed' ),
				/* translators: 1: tamam, 2: uyarı, 3: hata sayısı */
				'summary'     => __( '%1$d tamam · %2$d uyarı · %3$d hata', 'flx-speed' ),
				'recheck'     => __( 'Yeniden kontrol et', 'flx-speed' ),
				'filter_hint' => __( 'Yalnız bunları göster (tekrar tıklayınca hepsi)', 'flx-speed' ),
				/* translators: %s: saat */
				'checked'     => __( 'Kontrol: %s', 'flx-speed' ),
				'col_status'  => __( 'Durum', 'flx-speed' ),
				'col_check'   => __( 'Kontrol', 'flx-speed' ),
				'col_detail'  => __( 'Ayrıntı', 'flx-speed' ),
				'col_source'  => __( 'Kaynak', 'flx-speed' ),
				'col_action'  => __( 'İşlem', 'flx-speed' ),
				'none'        => __( 'Kayıtlı kontrol yok.', 'flx-speed' ),
				/* translators: %d: hata sayısı */
				'notice'      => __( 'Sistem Kontrolü bağışları ya da otomasyonları durdurabilecek %d sorun buldu.', 'flx-speed' ),
				'notice_link' => __( 'Sistem Kontrolü\'nü aç', 'flx-speed' ),
				'dismiss'     => __( 'Sonraki güncellemeye kadar gizle', 'flx-speed' ),
				/* translators: %d: hata sayısı */
				'bar'         => __( 'Sistem: %d hata', 'flx-speed' ),
				/* translators: %s: hata mesajı */
				'broken'      => __( 'Bu kontrol çalışamadı: %s', 'flx-speed' ),
				'groups'      => array(
					'Cron'        => __( 'Zamanlanmış görevler (cron)', 'flx-speed' ),
					'Payments'    => __( 'Ödemeler', 'flx-speed' ),
					'Plugins'     => __( 'Eklentiler', 'flx-speed' ),
					'Config'      => __( 'Site ayarları', 'flx-speed' ),
					'Email'       => __( 'E-posta', 'flx-speed' ),
					'Security'    => __( 'Güvenlik', 'flx-speed' ),
					'Forms'       => __( 'Formlar', 'flx-speed' ),
					'Performance' => __( 'Performans', 'flx-speed' ),
					'Other'       => __( 'Diğer', 'flx-speed' ),
				),
			)
		);
	},
	20
);
if ( is_admin() ) {
	add_action( 'init', 'flx_system_checks_boot' );
}
