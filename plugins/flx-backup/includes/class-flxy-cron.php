<?php
/**
 * Zamanlanmış görevler: günlük temizlik (saklama süresi, zamanlanmış yedek sayısı, yarım kalanlar, geçmiş örneği)
 * ve zamanlanmış tam yedek.
 */

defined( 'ABSPATH' ) || exit;

final class FLXY_Cron {

	const DAILY     = 'flxy_daily';
	const SCHEDULED = 'flxy_scheduled_backup';
	const HEARTBEAT = 'flxy_cron_heartbeat';

	public static function init(): void {
		add_action( self::DAILY, array( __CLASS__, 'daily' ) );
		add_action( self::SCHEDULED, array( __CLASS__, 'scheduled_backup' ) );
		add_action( 'init', array( __CLASS__, 'ensure_events' ) );
		add_action( 'update_option_' . FLXY_Settings::OPTION, array( __CLASS__, 'reschedule' ) );
		add_action( 'add_option_' . FLXY_Settings::OPTION, array( __CLASS__, 'reschedule' ) );
	}

	public static function activate(): void {
		FLXY_Storage::ensure();
		if ( false === get_option( FLXY_Settings::OPTION, false ) ) {
			add_option( FLXY_Settings::OPTION, FLXY_Settings::defaults(), '', false );
		}
		self::ensure_events();
		FLXY_Storage::sample();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::DAILY );
		wp_clear_scheduled_hook( self::SCHEDULED );
		wp_clear_scheduled_hook( FLXY_Runner::CRON_TICK );
	}

	public static function ensure_events(): void {
		if ( ! wp_next_scheduled( self::DAILY ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::DAILY );
		}
		$on = 'off' !== FLXY_Settings::get( 'schedule' );
		if ( $on && ! wp_next_scheduled( self::SCHEDULED ) ) {
			wp_schedule_single_event( self::next_run(), self::SCHEDULED );
		} elseif ( ! $on && wp_next_scheduled( self::SCHEDULED ) ) {
			wp_clear_scheduled_hook( self::SCHEDULED );
		}
	}

	public static function reschedule(): void {
		wp_clear_scheduled_hook( self::SCHEDULED );
		self::ensure_events();
	}

	/** Bir sonraki zamanlanmış yedeğin zamanı (site saat dilimine göre seçilen saat). */
	public static function next_run( int $after = 0 ): int {
		$after = $after ? $after : time();
		$s     = FLXY_Settings::all();
		$tz    = wp_timezone();
		$now   = ( new DateTimeImmutable( '@' . $after ) )->setTimezone( $tz );
		$run   = $now->setTime( (int) $s['schedule_hour'], 0, 0 );
		if ( 'weekly' === $s['schedule'] ) {
			$diff = ( (int) $s['schedule_day'] - (int) $run->format( 'w' ) + 7 ) % 7;
			$run  = $run->modify( '+' . $diff . ' days' );
			if ( $run->getTimestamp() <= $after ) {
				$run = $run->modify( '+7 days' );
			}
		} elseif ( $run->getTimestamp() <= $after ) {
			$run = $run->modify( '+1 day' );
		}
		return $run->getTimestamp();
	}

	public static function next_scheduled(): ?int {
		$t = wp_next_scheduled( self::SCHEDULED );
		return $t ? (int) $t : null;
	}

	public static function scheduled_backup(): void {
		update_option( self::HEARTBEAT, time(), false );
		if ( 'off' === FLXY_Settings::get( 'schedule' ) ) {
			return;
		}
		wp_schedule_single_event( self::next_run( time() + 60 ), self::SCHEDULED );
		$res = FLXY_Backup::create(
			array(
				'type'      => 'full',
				'context'   => array( 'source' => 'schedule' ),
				'keep_days' => 0,
			)
		);
		if ( is_wp_error( $res ) ) {
			FLXY_Storage::log(
				'schedule',
				/* translators: %s: error */
				sprintf( __( 'Could not start the scheduled backup: %s', 'flx-backup' ), $res->get_error_message() ),
				'error'
			);
		}
	}

	/** Günlük: süresi dolanları ve fazla zamanlanmış yedekleri siler, yarım kalanları kapatır, örnek kaydeder. */
	public static function daily(): void {
		update_option( self::HEARTBEAT, time(), false );
		FLXY_Storage::sync();
		self::cleanup();
		FLXY_Storage::sample();
	}

	public static function cleanup(): array {
		$deleted = array();
		$now     = time();
		$records = FLXY_Storage::records();

		foreach ( $records as $r ) {
			if ( $r['locked'] || null === $r['keep_until'] || $r['keep_until'] > $now || 'running' === $r['status'] ) {
				continue;
			}
			if ( FLXY_Storage::delete( $r['id'] ) ) {
				$deleted[] = $r['id'];
			}
		}

		// Zamanlanmış tam yedeklerden yalnız son N tanesi (kilitliler sayılmaz, silinmez).
		$keep = (int) FLXY_Settings::get( 'schedule_keep' );
		$n    = 0;
		foreach ( FLXY_Storage::records() as $r ) {
			if ( 'schedule' !== ( $r['context']['source'] ?? '' ) || 'ok' !== $r['status'] || $r['locked'] ) {
				continue;
			}
			++$n;
			if ( $n > $keep && FLXY_Storage::delete( $r['id'] ) ) {
				$deleted[] = $r['id'];
			}
		}

		// Başarısız kayıtlar 7 gün sonra listeden kalkar.
		foreach ( FLXY_Storage::records() as $r ) {
			if ( 'failed' === $r['status'] && $r['created'] < $now - 7 * DAY_IN_SECONDS ) {
				FLXY_Storage::forget( $r['id'] );
			}
		}

		// Yarım kalmış "running" kayıtlar (iş yok ve 6 saatten eski).
		foreach ( FLXY_Storage::records() as $r ) {
			if ( 'running' === $r['status'] && $r['created'] < $now - 6 * HOUR_IN_SECONDS && ! FLXY_Runner::job_is_for( $r['id'] ) ) {
				if ( $r['file'] && FLXY_Storage::is_inside( $r['file'] ) && file_exists( $r['file'] ) ) {
					wp_delete_file( $r['file'] );
				}
				FLXY_Storage::patch(
					$r['id'],
					array(
						'status' => 'failed',
						'error'  => __( 'Left unfinished (timed out).', 'flx-backup' ),
						'size'   => 0,
					)
				);
			}
		}

		// Sahipsiz geçici klasörler.
		$job  = FLXY_Runner::current();
		$keep_tmp = array();
		if ( $job && ! empty( $job['tmp'] ) ) {
			$keep_tmp[] = wp_normalize_path( $job['tmp'] );
		}
		if ( $job && is_array( $job['safety'] ?? null ) && ! empty( $job['safety']['tmp'] ) ) {
			$keep_tmp[] = wp_normalize_path( $job['safety']['tmp'] );
		}
		$dirs = glob( FLXY_Storage::tmp_dir() . '/*', GLOB_ONLYDIR );
		foreach ( $dirs ? $dirs : array() as $d ) {
			$d = wp_normalize_path( $d );
			if ( ! in_array( $d, $keep_tmp, true ) && filemtime( $d ) < $now - 6 * HOUR_IN_SECONDS ) {
				FLXY_Storage::rrmdir( $d );
			}
		}
		// ZipArchive'in yarıda kalmış geçici kopyaları (flxy-….zip.XXXXXX; 1.3.0 öncesi takılan yedeklerden).
		$temps = glob( FLXY_Storage::dir() . '/*.zip.*' );
		foreach ( $temps ? $temps : array() as $f ) {
			if ( is_file( $f ) && filemtime( $f ) < $now - HOUR_IN_SECONDS && ! FLXY_Runner::busy() ) {
				wp_delete_file( $f );
			}
		}
		// Geri yüklemeden kalan kenara alınmış paket klasörleri (yarıda kesilen geri yükleme).
		foreach ( array( WP_PLUGIN_DIR, get_theme_root() ) as $root ) {
			$old = glob( $root . '/*.flxy-old-*', GLOB_ONLYDIR );
			foreach ( $old ? $old : array() as $d ) {
				if ( filemtime( $d ) < $now - DAY_IN_SECONDS && ! FLXY_Runner::busy() ) {
					FLXY_Storage::rrmdir( $d );
				}
			}
		}

		if ( $deleted ) {
			FLXY_Storage::log(
				'retention',
				/* translators: %s: number of backups */
				sprintf( _n( '%s backup past its retention period was deleted.', '%s backups past their retention period were deleted.', count( $deleted ), 'flx-backup' ), number_format_i18n( count( $deleted ) ) )
			);
		}
		return $deleted;
	}
}
