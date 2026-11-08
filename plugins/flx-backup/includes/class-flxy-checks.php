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
		$group    = __( 'Backups', 'flx-backup' );
		$settings = admin_url( 'admin.php?page=flx-backup-settings' );
		$panel    = admin_url( 'admin.php?page=flx-backup' );

		// Depolama klasörü.
		$dir      = FLXY_Storage::dir();
		$writable = FLXY_Storage::is_writable();
		$checks[] = array(
			'id'      => 'flxy.storage',
			'source'  => $source,
			'group'   => $group,
			'label'   => __( 'Backup folder writable', 'flx-backup' ),
			'status'  => $writable ? 'ok' : 'error',
			'message' => $writable
				/* translators: %s: directory */
				? sprintf( __( 'Backups are written to %s.', 'flx-backup' ), FLXY_Backup::rel( $dir ) )
				/* translators: %s: directory */
				: sprintf( __( 'The %s folder cannot be created or written. Check the file permissions (755).', 'flx-backup' ), FLXY_Backup::rel( $dir ) ),
		);

		// ZipArchive.
		$zip      = class_exists( 'ZipArchive' ) && function_exists( 'gzopen' );
		$checks[] = array(
			'id'      => 'flxy.zip',
			'source'  => $source,
			'group'   => $group,
			'label'   => __( 'ZipArchive and zlib', 'flx-backup' ),
			'status'  => $zip ? 'ok' : 'error',
			'message' => $zip ? __( 'The PHP zip and zlib extensions are installed.', 'flx-backup' ) : __( 'The PHP zip (ZipArchive) or zlib extension is missing; backups cannot be created. On Hostinger, turn on "zip" under PHP Configuration › PHP Extensions.', 'flx-backup' ),
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
			'label'        => __( 'Backup quota', 'flx-backup' ),
			'status'       => $status,
			'message'      => sprintf(
				/* translators: 1: used, 2: quota, 3: percent */
				__( '%1$s / %2$s used (%3$s%%).', 'flx-backup' ),
				size_format( $used, 1 ),
				size_format( $quota, 1 ),
				number_format_i18n( $pct, 0 )
			) . ( 'ok' === $status ? '' : ' ' . __( 'The oldest unlocked backups will be deleted to make room for new ones; increase the quota or review the locked backups.', 'flx-backup' ) ),
			'action_url'   => $settings,
			'action_label' => __( 'Settings', 'flx-backup' ),
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
				'label'        => __( 'Last successful backup', 'flx-backup' ),
				'status'       => 'warning',
				'message'      => __( 'There are no backups yet. Take the first one from the dashboard with "Back up now".', 'flx-backup' ),
				'action_url'   => $panel,
				'action_label' => __( 'Backups', 'flx-backup' ),
			);
		} else {
			$age      = time() - $last['created'];
			$limit    = 'weekly' === FLXY_Settings::get( 'schedule' ) ? 8 * DAY_IN_SECONDS : 2 * DAY_IN_SECONDS;
			$schedule = 'off' !== FLXY_Settings::get( 'schedule' );
			$checks[] = array(
				'id'           => 'flxy.last',
				'source'       => $source,
				'group'        => $group,
				'label'        => __( 'Last successful backup', 'flx-backup' ),
				'status'       => $schedule && $age > $limit ? 'warning' : ( $age > 30 * DAY_IN_SECONDS ? 'warning' : 'ok' ),
				'message'      => sprintf(
					/* translators: 1: date, 2: human time diff, 3: backup title */
					__( '%1$s (%2$s): %3$s', 'flx-backup' ),
					flxy_date( $last['created'] ),
					flx_time_ago( $last['created'] ),
					FLXY_Storage::title( $last )
				),
				'action_url'   => $panel,
				'action_label' => __( 'Backups', 'flx-backup' ),
			);
		}

		// Güncelleme öncesi yedek.
		$scope    = (string) FLXY_Settings::get( 'pre_scope' );
		$labels   = array(
			'all' => __( 'for all plugin and theme updates', 'flx-backup' ),
			'flx'  => __( 'for FluxWP package updates only', 'flx-backup' ),
		);
		$checks[] = array(
			'id'           => 'flxy.pre_update',
			'source'       => $source,
			'group'        => $group,
			'label'        => __( 'Pre-update backup', 'flx-backup' ),
			'status'       => 'off' === $scope ? 'warning' : 'ok',
			'message'      => 'off' === $scope
				? __( 'Off: no backup is taken before updates.', 'flx-backup' )
				/* translators: 1: scope, 2: days */
				: sprintf( __( 'On, %1$s. Backups are kept for %2$s days.', 'flx-backup' ), $labels[ $scope ] ?? $scope, number_format_i18n( (int) FLXY_Settings::get( 'pre_keep_days' ) ) ),
			'action_url'   => $settings,
			'action_label' => __( 'Settings', 'flx-backup' ),
		);

		// Cron.
		$beat     = (int) get_option( FLXY_Cron::HEARTBEAT, 0 );
		$next     = wp_next_scheduled( FLXY_Cron::DAILY );
		$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		if ( $beat && $beat > time() - 2 * DAY_IN_SECONDS ) {
			$status = 'ok';
			/* translators: %s: human time diff */
			$msg = sprintf( __( 'The daily cleanup task ran %s.', 'flx-backup' ), flx_time_ago( $beat ) );
		} elseif ( ! $beat && $next && $next > time() - HOUR_IN_SECONDS ) {
			$status = 'info';
			$msg    = __( 'The daily task is scheduled but has not run yet.', 'flx-backup' );
		} else {
			$status = 'warning';
			$msg    = $disabled
				? __( 'DISABLE_WP_CRON is on and the daily job has not run for 2 days. In the Hostinger panel, call wp-cron.php every 5–15 minutes under Advanced › Cron Jobs.', 'flx-backup' )
				: __( 'The daily task has not run for 2 days; WP-Cron is triggered by visits. Old backups may not be getting deleted.', 'flx-backup' );
		}
		$checks[] = array(
			'id'      => 'flxy.cron',
			'source'  => $source,
			'group'   => 'Cron',
			'label'   => __( 'Flux Backup scheduled tasks', 'flx-backup' ),
			'status'  => $status,
			'message' => $msg,
		);

		return $checks;
	}
}
