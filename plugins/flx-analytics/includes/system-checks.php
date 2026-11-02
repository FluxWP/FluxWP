<?php
/**
 * Ortak Sistem Kontrolü satırları (flx_system_checks) ve düzeltme (flx_system_fixes). Dış HTTP yok.
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_system_checks',
	function ( $checks ) {
		$checks   = is_array( $checks ) ? $checks : array();
		$src      = __( 'Site Analitik', 'flx-analytics' );
		$settings = admin_url( 'admin.php?page=flx-analytics-settings' );
		$s        = flxan_settings();

		if ( (int) get_option( 'flxan_db_version', 0 ) < 1 ) {
			$checks[] = array(
				'id'      => 'flxan.tables',
				'source'  => $src,
				'group'   => 'Plugins',
				'label'   => __( 'İstatistik tabloları', 'flx-analytics' ),
				'status'  => 'error',
				'message' => __( 'Tablolar kurulmamış. Eklentiyi devre dışı bırakıp yeniden etkinleştirin.', 'flx-analytics' ),
			);
			return $checks;
		}

		if ( ! flxan_schema_ok() ) {
			$checks[] = array(
				'id'      => 'flxan.schema',
				'source'  => $src,
				'group'   => 'Plugins',
				'label'   => __( 'İstatistik tabloları', 'flx-analytics' ),
				'status'  => 'error',
				'message' => __( 'Tablolar eski sürümde kalmış; ziyaretler kaydedilemiyor.', 'flx-analytics' ),
			);
		}

		$checks[] = array(
			'id'           => 'flxan.on',
			'source'       => $src,
			'group'        => 'Plugins',
			'label'        => __( 'Ziyaret sayımı', 'flx-analytics' ),
			'status'       => $s['on'] ? 'ok' : 'warning',
			'message'      => $s['on'] ? __( 'Açık. Çerez kullanılmaz, kişisel veri saklanmaz.', 'flx-analytics' ) : __( 'Kapalı: ziyaretler sayılmıyor.', 'flx-analytics' ),
			'action_url'   => $settings,
			'action_label' => __( 'Ayarlar', 'flx-analytics' ),
		);

		$dir_ok   = is_dir( flxan_data_dir() ) && is_writable( flxan_data_dir() ) && is_file( flxan_data_dir() . '/config.php' );
		$checks[] = array(
			'id'      => 'flxan.dir',
			'source'  => $src,
			'group'   => 'Plugins',
			'label'   => __( 'Veri dizini', 'flx-analytics' ),
			'status'  => $dir_ok ? 'ok' : 'error',
			'message' => $dir_ok
				? __( 'wp-content/uploads/flx-analytics yazılabilir ve web erişimine kapalı.', 'flx-analytics' )
				: __( 'wp-content/uploads/flx-analytics oluşturulamadı ya da yazılamıyor; ziyaretler kaydedilemez. Dizin izinlerini (755) kontrol edin.', 'flx-analytics' ),
		);

		$method   = flxan_method();
		$test     = get_option( 'flxan_fast_test', '' );
		$checks[] = array(
			'id'           => 'flxan.method',
			'source'       => $src,
			'group'        => 'Plugins',
			'label'        => __( 'Toplama yöntemi', 'flx-analytics' ),
			'status'       => 'fast' === $method ? 'ok' : ( 'rest' === $s['method'] ? 'info' : 'warning' ),
			'message'      => 'fast' === $method
				? __( 'Hızlı uç: ziyaret WordPress yüklenmeden kaydedilir.', 'flx-analytics' )
				: ( 'fail' === $test
					? __( 'Hızlı uç testten geçmedi (güvenlik eklentisi ya da sunucu eklenti klasöründeki PHP\'yi engelliyor olabilir); REST ucu kullanılıyor. Çalışır ama her ziyarette WordPress yüklenir.', 'flx-analytics' )
					: __( 'REST ucu kullanılıyor. Çalışır ama her ziyarette WordPress yüklenir.', 'flx-analytics' ) ),
			'action_url'   => $settings,
			'action_label' => __( 'Ayarlar', 'flx-analytics' ),
		);

		$last     = (int) get_option( 'flxan_last_agg', 0 );
		$pending  = FLXAN_Aggregator::pending_bytes();
		$stale    = $pending > 0 && ( ! $last || time() - $last > HOUR_IN_SECONDS );
		$checks[] = array(
			'id'      => 'flxan.aggregate',
			'source'  => $src,
			'group'   => 'Cron',
			'label'   => __( 'İstatistik işleme', 'flx-analytics' ),
			'status'  => $stale ? 'warning' : ( $last ? 'ok' : 'info' ),
			'message' => $last
				/* translators: 1: geçen süre, 2: bekleyen veri boyutu */
				? sprintf( __( 'Son işleme %1$s önce. Bekleyen: %2$s.', 'flx-analytics' ), human_time_diff( $last ), size_format( $pending ) )
				: __( 'Henüz işlenen ziyaret yok.', 'flx-analytics' ),
		);

		$err = get_option( 'flxan_last_error' );
		if ( is_array( $err ) && ! empty( $err['time'] ) && time() - (int) $err['time'] < DAY_IN_SECONDS ) {
			$checks[] = array(
				'id'      => 'flxan.error',
				'source'  => $src,
				'group'   => 'Plugins',
				'label'   => __( 'İşleme hatası', 'flx-analytics' ),
				'status'  => 'warning',
				'message' => (string) $err['msg'],
			);
		}

		$cached   = defined( 'LSCWP_V' ) || defined( 'WP_ROCKET_VERSION' ) || defined( 'W3TC' ) || function_exists( 'wp_cache_postload' );
		$checks[] = array(
			'id'      => 'flxan.cache',
			'source'  => $src,
			'group'   => 'Plugins',
			'label'   => __( 'Sayfa önbelleği uyumu', 'flx-analytics' ),
			'status'  => 'ok',
			'message' => $cached
				? __( 'Önbellek eklentisi var; sayaç tarayıcıda çalıştığı için önbellekteki sayfalar da sayılır. Sayaç script\'i birleştirme/geciktirme dışında tutulur.', 'flx-analytics' )
				: __( 'Sayaç tarayıcıda çalışır; önbellek eklentisi kurulursa da sayım sürer.', 'flx-analytics' ),
		);

		return $checks;
	}
);

add_filter(
	'flx_system_fixes',
	function ( $fixes ) {
		$fixes                       = is_array( $fixes ) ? $fixes : array();
		$fixes['flxan_aggregate_now'] = array(
			'label'       => __( 'İstatistikleri şimdi işle', 'flx-analytics' ),
			'description' => __( 'Kuyrukta bekleyen ziyaretleri hemen tablolara işler.', 'flx-analytics' ),
			'source'      => __( 'Site Analitik', 'flx-analytics' ),
			'checks'      => array( 'flxan.aggregate' ),
			'value'       => null,
			'current'     => function () {
				return size_format( FLXAN_Aggregator::pending_bytes() );
			},
			'run'         => function () {
				$n = FLXAN_Aggregator::run( 25 );
				if ( $n < 0 ) {
					throw new Exception( __( 'İşleme zaten çalışıyor; birkaç saniye sonra yeniden deneyin.', 'flx-analytics' ) );
				}
				/* translators: %s: satır sayısı */
				return sprintf( __( '%s satır işlendi.', 'flx-analytics' ), number_format_i18n( $n ) );
			},
		);
		$fixes['flxan_repair_tables'] = array(
			'label'       => __( 'İstatistik tablolarını onar', 'flx-analytics' ),
			'description' => __( 'Eksik sütun ve tabloları ekler, anahtarları günceller. Veri silinmez.', 'flx-analytics' ),
			'source'      => __( 'Site Analitik', 'flx-analytics' ),
			'checks'      => array( 'flxan.schema' ),
			'value'       => null,
			'current'     => function () {
				return flxan_schema_ok() ? 'ok' : __( 'eski', 'flx-analytics' );
			},
			'run'         => function () {
				flxan_install();
				if ( ! flxan_schema_ok() ) {
					throw new Exception( __( 'Tablolar güncellenemedi. Veritabanı kullanıcısının ALTER yetkisi olmalı.', 'flx-analytics' ) );
				}
				return __( 'Tablolar güncellendi.', 'flx-analytics' );
			},
		);
		return $fixes;
	}
);
