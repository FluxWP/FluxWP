<?php
/**
 * FluxWP ortak "Sistem kontrolü" ekranına satırlar (flx_system_checks filtresi). Hepsi ucuz: seçenek ve dosya kontrolü.
 */

defined( 'ABSPATH' ) || exit;

final class FLXY_Checks {

	public static function init(): void {
		add_filter( 'flx_system_checks', array( __CLASS__, 'checks' ) );
	}

	public static function checks( $checks ) {
		$checks   = is_array( $checks ) ? $checks : array();
		$source   = __( 'Flux Backup', 'flx-backup' );
		$group    = __( 'Yedekleme', 'flx-backup' );
		$settings = admin_url( 'admin.php?page=flx-backup-settings' );
		$panel    = admin_url( 'admin.php?page=flx-backup' );

		// Depolama klasörü.
		$dir      = FLXY_Storage::dir();
		$writable = FLXY_Storage::is_writable();
		$checks[] = array(
			'id'      => 'flxy.storage',
			'source'  => $source,
			'group'   => $group,
			'label'   => __( 'Yedek klasörü yazılabilir', 'flx-backup' ),
			'status'  => $writable ? 'ok' : 'error',
			'message' => $writable
				/* translators: %s: directory */
				? sprintf( __( 'Yedekler %s klasörüne yazılıyor.', 'flx-backup' ), FLXY_Backup::rel( $dir ) )
				/* translators: %s: directory */
				: sprintf( __( '%s klasörü oluşturulamıyor veya yazılamıyor. Dosya izinlerini (755) kontrol edin.', 'flx-backup' ), FLXY_Backup::rel( $dir ) ),
		);

		// ZipArchive.
		$zip      = class_exists( 'ZipArchive' ) && function_exists( 'gzopen' );
		$checks[] = array(
			'id'      => 'flxy.zip',
			'source'  => $source,
			'group'   => $group,
			'label'   => __( 'ZipArchive ve zlib', 'flx-backup' ),
			'status'  => $zip ? 'ok' : 'error',
			'message' => $zip ? __( 'PHP zip ve zlib eklentileri yüklü.', 'flx-backup' ) : __( 'PHP zip (ZipArchive) veya zlib eklentisi yok; yedek alınamaz. Hostinger panelinde PHP Yapılandırması › PHP Uzantıları\'ndan "zip" açın.', 'flx-backup' ),
		);

		// Kota.
		$used  = FLXY_Storage::used_bytes();
		$quota = max( 1, FLXY_Settings::quota_bytes() );
		$pct   = 100 * $used / $quota;
		$status = $pct > 95 ? 'error' : ( $pct > 80 ? 'warning' : 'ok' );
		$checks[] = array(
			'id'           => 'flxy.quota',
			'source'       => $source,
			'group'        => $group,
			'label'        => __( 'Yedek kotası', 'flx-backup' ),
			'status'       => $status,
			'message'      => sprintf(
				/* translators: 1: used, 2: quota, 3: percent */
				__( '%1$s / %2$s kullanılıyor (%%%3$s).', 'flx-backup' ),
				size_format( $used, 1 ),
				size_format( $quota, 1 ),
				number_format_i18n( $pct, 0 )
			) . ( 'ok' === $status ? '' : ' ' . __( 'Yeni yedek için en eski kilitsiz yedekler silinecek; kotayı artırın veya kilitli yedekleri gözden geçirin.', 'flx-backup' ) ),
			'action_url'   => $settings,
			'action_label' => __( 'Ayarlar', 'flx-backup' ),
		);

		// Son başarılı yedek.
		$last = null;
		foreach ( FLXY_Storage::records() as $r ) {
			if ( 'ok' === $r['status'] ) {
				$last = $r;
				break;
			}
		}
		if ( ! $last ) {
			$checks[] = array(
				'id'           => 'flxy.last',
				'source'       => $source,
				'group'        => $group,
				'label'        => __( 'Son başarılı yedek', 'flx-backup' ),
				'status'       => 'warning',
				'message'      => __( 'Henüz hiç yedek yok. Panelden "Şimdi yedek al" ile ilk yedeği alın.', 'flx-backup' ),
				'action_url'   => $panel,
				'action_label' => __( 'Yedekler', 'flx-backup' ),
			);
		} else {
			$age      = time() - $last['created'];
			$limit    = 'weekly' === FLXY_Settings::get( 'schedule' ) ? 8 * DAY_IN_SECONDS : 2 * DAY_IN_SECONDS;
			$schedule = 'off' !== FLXY_Settings::get( 'schedule' );
			$checks[] = array(
				'id'           => 'flxy.last',
				'source'       => $source,
				'group'        => $group,
				'label'        => __( 'Son başarılı yedek', 'flx-backup' ),
				'status'       => $schedule && $age > $limit ? 'warning' : ( $age > 30 * DAY_IN_SECONDS ? 'warning' : 'ok' ),
				'message'      => sprintf(
					/* translators: 1: date, 2: human time diff, 3: backup title */
					__( '%1$s (%2$s): %3$s', 'flx-backup' ),
					flxy_date( $last['created'] ),
					flx_time_ago( $last['created'] ),
					FLXY_Storage::title( $last )
				),
				'action_url'   => $panel,
				'action_label' => __( 'Yedekler', 'flx-backup' ),
			);
		}

		// Güncelleme öncesi yedek.
		$scope    = (string) FLXY_Settings::get( 'pre_scope' );
		$labels   = array(
			'all' => __( 'tüm eklenti ve tema güncellemelerinde', 'flx-backup' ),
			'flx'  => __( 'yalnız FluxWP paketlerinin güncellemelerinde', 'flx-backup' ),
		);
		$checks[] = array(
			'id'           => 'flxy.pre_update',
			'source'       => $source,
			'group'        => $group,
			'label'        => __( 'Güncelleme öncesi yedek', 'flx-backup' ),
			'status'       => 'off' === $scope ? 'warning' : 'ok',
			'message'      => 'off' === $scope
				? __( 'Kapalı: güncellemelerden önce yedek alınmıyor.', 'flx-backup' )
				/* translators: 1: scope, 2: days */
				: sprintf( __( 'Açık, %1$s. Yedekler %2$s gün saklanır.', 'flx-backup' ), $labels[ $scope ] ?? $scope, number_format_i18n( (int) FLXY_Settings::get( 'pre_keep_days' ) ) ),
			'action_url'   => $settings,
			'action_label' => __( 'Ayarlar', 'flx-backup' ),
		);

		// Cron.
		$beat     = (int) get_option( FLXY_Cron::HEARTBEAT, 0 );
		$next     = wp_next_scheduled( FLXY_Cron::DAILY );
		$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		if ( $beat && $beat > time() - 2 * DAY_IN_SECONDS ) {
			$status = 'ok';
			/* translators: %s: human time diff */
			$msg = sprintf( __( 'Günlük temizlik görevi %s çalıştı.', 'flx-backup' ), flx_time_ago( $beat ) );
		} elseif ( ! $beat && $next && $next > time() - HOUR_IN_SECONDS ) {
			$status = 'info';
			$msg    = __( 'Günlük görev zamanlandı, henüz çalışmadı.', 'flx-backup' );
		} else {
			$status = 'warning';
			$msg    = $disabled
				? __( 'DISABLE_WP_CRON açık ve günlük görev 2 gündür çalışmadı. Hostinger panelinde Gelişmiş › Cron Jobs ile wp-cron.php\'yi 5–15 dakikada bir çağırın.', 'flx-backup' )
				: __( 'Günlük görev 2 gündür çalışmadı; WP-Cron ziyaretle tetiklenir. Eski yedekler silinmiyor olabilir.', 'flx-backup' );
		}
		$checks[] = array(
			'id'      => 'flxy.cron',
			'source'  => $source,
			'group'   => 'Cron',
			'label'   => __( 'Flux Backup zamanlanmış görevleri', 'flx-backup' ),
			'status'  => $status,
			'message' => $msg,
		);

		return $checks;
	}
}
