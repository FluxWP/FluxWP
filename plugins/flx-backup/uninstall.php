<?php
/**
 * Flux Backup kaldırılınca ayarlarını, kayıt dizinini ve zamanlanmış görevlerini siler.
 * Yedek dosyaları yalnız Ayarlar'da "Yedek dosyalarını da sil" işaretliyse silinir (varsayılan: kalır).
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$flxy_settings = get_option( 'flxy_settings', array() );
$flxy_delete   = is_array( $flxy_settings ) && ! empty( $flxy_settings['delete_on_uninstall'] );

foreach ( array( 'flxy_daily', 'flxy_scheduled_backup', 'flxy_job_tick' ) as $flxy_hook ) {
	wp_clear_scheduled_hook( $flxy_hook );
}

if ( $flxy_delete ) {
	$flxy_dir = untrailingslashit( wp_normalize_path( (string) apply_filters( 'flxy_storage_dir', WP_CONTENT_DIR . '/wp-snapshots/flx-backup' ) ) );
	// Yalnız bu eklentinin klasörü; içinde başka bir şey varsa (ör. filtre ile başka yer verilmişse) yalnız bizim dosyalarımız.
	$flxy_rm = static function ( $path ) use ( &$flxy_rm ) {
		if ( is_dir( $path ) && ! is_link( $path ) ) {
			foreach ( (array) scandir( $path ) as $item ) {
				if ( '.' !== $item && '..' !== $item ) {
					$flxy_rm( $path . '/' . $item );
				}
			}
			@rmdir( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
		} elseif ( file_exists( $path ) || is_link( $path ) ) {
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
		}
	};
	if ( is_dir( $flxy_dir ) ) {
		foreach ( (array) glob( $flxy_dir . '/flxy-*.zip' ) as $flxy_zip ) {
			@unlink( $flxy_zip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
		}
		$flxy_rm( $flxy_dir . '/tmp' );
		foreach ( array( '.htaccess', 'index.php', 'web.config', '.lock', '.restore-job.json', '.cancel' ) as $flxy_file ) {
			@unlink( $flxy_dir . '/' . $flxy_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
		}
		@rmdir( $flxy_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
	}
}

foreach ( array( 'flxy_settings', 'flxy_backups', 'flxy_job', 'flxy_history', 'flxy_log', 'flxy_cron_heartbeat', 'flxy_last_restore', 'flxy_indicator' ) as $flxy_option ) {
	delete_option( $flxy_option );
}
