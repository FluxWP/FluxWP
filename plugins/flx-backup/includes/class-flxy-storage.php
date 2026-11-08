<?php
/**
 * Depolama klasörü, yedek dizini (kayıtlar), kota, depolama geçmişi ve olay günlüğü.
 *
 * Kayıt dizini neden bir seçenek (flxy_backups) ve özel tablo değil:
 * - Bir sitede en fazla birkaç düzine yedek olur; liste, panel ve kota hesabı zaten hepsini birlikte okur,
 *   sorgulanacak/sayfalanacak bir veri yok.
 * - Tablo şeması, dbDelta ve sürüm göçü gerekmez; kaldırmada tek delete_option yeter.
 * - Veritabanı geri yüklenince tablo da seçenek de eski haline döner; ikisinde de güncel dizin geri yazılmalı.
 *   Bunu FLXY_Restore yapar, ayrıca zip'lerdeki manifest.json'dan dizin yeniden kurulabilir (sync()).
 * Seçenek autoload=no tutulur; her yazmadan önce önbellek atlanıp taze okunur (aynı anda çalışan iki istek
 * birbirinin kaydını ezmesin diye).
 */

defined( 'ABSPATH' ) || exit;

final class FLXY_Storage {

	const INDEX   = 'flxy_backups';
	const HISTORY = 'flxy_history';
	const LOG     = 'flxy_log';

	/** Hostinger'in kendi yedeklerine almadığı klasör altında. */
	public static function dir(): string {
		$dir = WP_CONTENT_DIR . '/wp-snapshots/flx-backup';
		/**
		 * Yedeklerin yazılacağı klasör (mutlak yol).
		 *
		 * @param string $dir Varsayılan: wp-content/wp-snapshots/flx-backup
		 */
		$dir = (string) apply_filters( 'flxy_storage_dir', $dir );
		return untrailingslashit( wp_normalize_path( $dir ) );
	}

	public static function tmp_dir(): string {
		return self::dir() . '/tmp';
	}

	/** Klasörü oluşturur ve web erişimine kapatır. */
	public static function ensure(): bool {
		$dir = self::dir();
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$files = array(
			'.htaccess'  => "# Flux Backup: doğrudan erişim kapalı.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\nOptions -Indexes\n",
			'index.php'  => "<?php\n// Silence is golden.\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n\t<system.webServer>\n\t\t<authorization>\n\t\t\t<deny users=\"*\" />\n\t\t</authorization>\n\t</system.webServer>\n</configuration>\n",
		);
		foreach ( $files as $name => $body ) {
			if ( ! file_exists( $dir . '/' . $name ) ) {
				@file_put_contents( $dir . '/' . $name, $body ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
			}
		}
		if ( ! is_dir( self::tmp_dir() ) ) {
			wp_mkdir_p( self::tmp_dir() );
		}
		return is_dir( $dir ) && wp_is_writable( $dir );
	}

	public static function is_writable(): bool {
		$dir = self::dir();
		if ( ! is_dir( $dir ) ) {
			return self::ensure();
		}
		return wp_is_writable( $dir );
	}

	/* ------------------------------------------------------------------ */
	/* Kayıt dizini                                                        */
	/* ------------------------------------------------------------------ */

	/** @return array<string,array> id => kayıt */
	public static function index(): array {
		wp_cache_delete( self::INDEX, 'options' );
		$all = get_option( self::INDEX, array() );
		return is_array( $all ) ? $all : array();
	}

	/** Kayıtlar, yeniden eskiye. */
	public static function records(): array {
		$all = array_values( self::index() );
		usort(
			$all,
			static function ( $a, $b ) {
				return ( (int) $b['created'] <=> (int) $a['created'] ) ?: strcmp( (string) $b['id'], (string) $a['id'] );
			}
		);
		return $all;
	}

	public static function get( string $id ): ?array {
		$all = self::index();
		return isset( $all[ $id ] ) ? self::normalize( $all[ $id ] ) : null;
	}

	public static function put( array $record ): array {
		$record = self::normalize( $record );
		$all    = self::index();
		$all[ $record['id'] ] = $record;
		update_option( self::INDEX, $all, false );
		return $record;
	}

	/** Kaydı birkaç alanla günceller (taze okuma + yazma). */
	public static function patch( string $id, array $fields ): ?array {
		$all = self::index();
		if ( ! isset( $all[ $id ] ) ) {
			return null;
		}
		$all[ $id ] = self::normalize( array_merge( $all[ $id ], $fields ) );
		update_option( self::INDEX, $all, false );
		return $all[ $id ];
	}

	public static function forget( string $id ): void {
		$all = self::index();
		unset( $all[ $id ] );
		update_option( self::INDEX, $all, false );
	}

	/** Dosyayı ve kaydı siler. Çalışan yedek silinmez. */
	public static function delete( string $id ): bool {
		$r = self::get( $id );
		if ( ! $r ) {
			return false;
		}
		if ( 'running' === $r['status'] && FLXY_Runner::job_is_for( $id ) ) {
			return false;
		}
		if ( $r['file'] && self::is_inside( $r['file'] ) && file_exists( $r['file'] ) ) {
			wp_delete_file( $r['file'] );
			if ( file_exists( $r['file'] ) ) {
				return false;
			}
		}
		self::forget( $id );
		self::log( 'delete', sprintf( /* translators: %s: backup label */ __( 'Backup deleted: %s', 'flx-backup' ), self::title( $r ) ) );
		self::sample();
		/**
		 * Bir yedek silindikten sonra.
		 *
		 * @param array $record Silinen kayıt.
		 */
		do_action( 'flxy_backup_deleted', $r );
		return true;
	}

	public static function is_inside( string $path ): bool {
		$path = wp_normalize_path( $path );
		return 0 === strpos( $path, self::dir() . '/' );
	}

	public static function normalize( array $r ): array {
		$r = array_merge(
			array(
				'id'         => '',
				'type'       => 'manual',
				'label'      => '',
				'status'     => 'running',
				'created'    => 0,
				'finished'   => 0,
				'size'       => 0,
				'db'         => false,
				'paths'      => array(),
				'items'      => array(),
				'files'      => 0,
				'context'    => array(),
				'keep_until' => null,
				'locked'     => false,
				'file'       => '',
				'error'      => '',
				'wp_version' => '',
			),
			$r
		);
		$r['created']    = (int) $r['created'];
		$r['finished']   = (int) $r['finished'];
		$r['size']       = (int) $r['size'];
		$r['files']      = (int) $r['files'];
		$r['db']         = (bool) $r['db'];
		$r['locked']     = (bool) $r['locked'];
		$r['keep_until'] = null === $r['keep_until'] ? null : (int) $r['keep_until'];
		$r['paths']      = array_values( (array) $r['paths'] );
		$r['items']      = array_values( (array) $r['items'] );
		$r['context']    = (array) $r['context'];
		return $r;
	}

	/** Bir yedeğin listede görünen adı. */
	public static function title( array $r ): string {
		if ( '' !== (string) $r['label'] ) {
			return (string) $r['label'];
		}
		$ctx  = (array) $r['context'];
		$pkgs = isset( $ctx['packages'] ) ? (array) $ctx['packages'] : array();
		if ( 'pre_update' === $r['type'] && $pkgs ) {
			if ( 1 === count( $pkgs ) ) {
				$p    = $pkgs[0];
				$name = '' !== (string) ( $p['name'] ?? '' ) ? $p['name'] : $p['slug'];
				if ( ! empty( $p['from_version'] ) && ! empty( $p['to_version'] ) ) {
					/* translators: 1: package name, 2: old version, 3: new version */
					return sprintf( __( 'Before %1$s %2$s → %3$s', 'flx-backup' ), $name, $p['from_version'], $p['to_version'] );
				}
				/* translators: %s: package name */
				return sprintf( __( 'Before updating %s', 'flx-backup' ), $name );
			}
			/* translators: %s: number of packages */
			return sprintf( _n( 'Before updating %s package', 'Before updating %s packages', count( $pkgs ), 'flx-backup' ), number_format_i18n( count( $pkgs ) ) );
		}
		$labels = self::type_labels();
		return $labels[ $r['type'] ] ?? (string) $r['type'];
	}

	public static function type_labels(): array {
		return array(
			'full'       => __( 'Full backup', 'flx-backup' ),
			'pre_update' => __( 'Pre-update', 'flx-backup' ),
			'db'         => __( 'Database', 'flx-backup' ),
			'files'      => __( 'Files', 'flx-backup' ),
			'manual'     => __( 'Manual', 'flx-backup' ),
		);
	}

	/** Sabit sırada kategorik renkler (tür kimliği; sıralamaya göre değişmez). */
	public static function type_colors(): array {
		return array(
			'full'       => '#2a78d6',
			'pre_update' => '#eb6834',
			'db'         => '#1baf7a',
			'files'      => '#eda100',
			'manual'     => '#e87ba4',
		);
	}

	public static function source_labels(): array {
		return array(
			'manual'   => __( 'Manual', 'flx-backup' ),
			'schedule' => __( 'Scheduled', 'flx-backup' ),
			'auto'     => __( 'Automatic update', 'flx-backup' ),
			'update'   => __( 'Update', 'flx-backup' ),
			'cli'      => __( 'WP-CLI', 'flx-backup' ),
			'safety'   => __( 'Before restore', 'flx-backup' ),
			'api'      => __( 'Plugin (API)', 'flx-backup' ),
			'upload'   => __( 'Uploaded', 'flx-backup' ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Boyutlar ve kota                                                    */
	/* ------------------------------------------------------------------ */

	public static function used_bytes(): int {
		$sum = 0;
		foreach ( self::index() as $r ) {
			$sum += (int) ( $r['size'] ?? 0 );
		}
		return $sum;
	}

	/** @return array<string,int> tür => bayt */
	public static function bytes_by_type(): array {
		$out = array_fill_keys( array_keys( self::type_labels() ), 0 );
		foreach ( self::index() as $r ) {
			$t = (string) ( $r['type'] ?? 'manual' );
			if ( ! isset( $out[ $t ] ) ) {
				$out[ $t ] = 0;
			}
			$out[ $t ] += (int) ( $r['size'] ?? 0 );
		}
		return $out;
	}

	/**
	 * Kotada $needed bayt yer açar: en eski kilitsiz yedekleri siler.
	 *
	 * @param int      $needed  Yeni yedeğin tahmini boyutu.
	 * @param string[] $keep    Silinmeyecek kimlikler (çalışan yedek).
	 * @return true|WP_Error
	 */
	public static function make_room( int $needed, array $keep = array() ) {
		$quota = FLXY_Settings::quota_bytes();
		if ( $needed > $quota ) {
			return new WP_Error(
				'flxy_quota',
				sprintf(
					/* translators: 1: estimated size, 2: quota */
					__( 'The estimated backup size (%1$s) is larger than the quota (%2$s). Raise the quota in Settings.', 'flx-backup' ),
					size_format( $needed, 1 ),
					size_format( $quota, 1 )
				)
			);
		}
		$used = self::used_bytes();
		if ( $used + $needed <= $quota ) {
			return true;
		}
		$candidates = array_reverse( self::records() ); // Eskiden yeniye.
		$deleted    = array();
		foreach ( $candidates as $r ) {
			if ( $used + $needed <= $quota ) {
				break;
			}
			if ( $r['locked'] || 'running' === $r['status'] || in_array( $r['id'], $keep, true ) || (int) $r['size'] <= 0 ) {
				continue;
			}
			if ( self::delete( $r['id'] ) ) {
				$used     -= (int) $r['size'];
				$deleted[] = self::title( $r );
			}
		}
		if ( $deleted ) {
			self::log(
				'quota',
				sprintf(
					/* translators: 1: number of deleted backups, 2: list */
					_n( '%1$s old backup deleted for the quota: %2$s', '%1$s old backups deleted for the quota: %2$s', count( $deleted ), 'flx-backup' ),
					number_format_i18n( count( $deleted ) ),
					implode( ', ', $deleted )
				)
			);
		}
		if ( $used + $needed > $quota ) {
			return new WP_Error(
				'flxy_quota',
				sprintf(
					/* translators: 1: estimated size, 2: used, 3: quota */
					__( 'Not enough quota: the new backup is about %1$s, used %2$s, quota %3$s. The remaining backups are locked; unlock or delete some, or increase the quota.', 'flx-backup' ),
					size_format( $needed, 1 ),
					size_format( $used, 1 ),
					size_format( $quota, 1 )
				)
			);
		}
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Depolama geçmişi (grafik)                                           */
	/* ------------------------------------------------------------------ */

	/** Bugünün örneğini yazar (günde bir satır, son 180 gün). */
	public static function sample(): void {
		$hist = get_option( self::HISTORY, array() );
		$hist = is_array( $hist ) ? $hist : array();
		$day  = wp_date( 'Y-m-d' );
		$by   = array_filter( self::bytes_by_type() );
		$hist[ $day ] = array(
			't'  => array_sum( $by ),
			'by' => $by,
			'n'  => count( self::index() ),
		);
		ksort( $hist );
		if ( count( $hist ) > 180 ) {
			$hist = array_slice( $hist, -180, null, true );
		}
		update_option( self::HISTORY, $hist, false );
	}

	public static function history(): array {
		$hist = get_option( self::HISTORY, array() );
		return is_array( $hist ) ? $hist : array();
	}

	/* ------------------------------------------------------------------ */
	/* Olay günlüğü                                                        */
	/* ------------------------------------------------------------------ */

	public static function log( string $kind, string $message, string $level = 'info' ): void {
		$log   = get_option( self::LOG, array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array(
			'at'    => time(),
			'kind'  => $kind,
			'level' => $level,
			'msg'   => $message,
		);
		if ( count( $log ) > 60 ) {
			$log = array_slice( $log, -60 );
		}
		update_option( self::LOG, $log, false );
		if ( 'error' === $level && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'Flux Backup: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	public static function events( int $limit = 8 ): array {
		$log = get_option( self::LOG, array() );
		return array_slice( array_reverse( is_array( $log ) ? $log : array() ), 0, $limit );
	}

	/* ------------------------------------------------------------------ */
	/* Disk ile eşitleme                                                   */
	/* ------------------------------------------------------------------ */

	/**
	 * Dizini klasörle eşitler: dosyası kaybolan "ok" kayıtları atar, dizinde olmayan zip'leri manifest'ten ekler.
	 * Ucuz: yalnızca glob + bilinmeyen zip başına bir manifest okuması.
	 */
	public static function sync(): void {
		$dir = self::dir();
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$all     = self::index();
		$known   = array();
		$changed = false;
		foreach ( $all as $id => $r ) {
			if ( ! empty( $r['file'] ) ) {
				$known[ wp_normalize_path( $r['file'] ) ] = true;
			}
			if ( 'ok' === ( $r['status'] ?? '' ) && ( empty( $r['file'] ) || ! file_exists( $r['file'] ) ) ) {
				unset( $all[ $id ] );
				$changed = true;
			}
		}
		$zips = glob( $dir . '/*.zip' );
		foreach ( $zips ? $zips : array() as $zip ) {
			$zip = wp_normalize_path( $zip );
			if ( isset( $known[ $zip ] ) ) {
				continue;
			}
			$m = self::read_manifest( $zip );
			if ( ! $m || empty( $m['id'] ) || isset( $all[ $m['id'] ] ) ) {
				continue;
			}
			$all[ $m['id'] ] = self::normalize(
				array(
					'id'         => $m['id'],
					'type'       => $m['type'] ?? 'manual',
					'label'      => $m['label'] ?? '',
					'status'     => 'ok',
					'created'    => (int) ( $m['created'] ?? filemtime( $zip ) ),
					'finished'   => (int) ( $m['created'] ?? filemtime( $zip ) ),
					'size'       => (int) filesize( $zip ),
					'db'         => ! empty( $m['db']['included'] ),
					'paths'      => $m['paths'] ?? array(),
					'items'      => $m['contents'] ?? array(),
					'files'      => (int) ( $m['sizes']['files_count'] ?? 0 ),
					'context'    => $m['context'] ?? array(),
					'keep_until' => null,
					'locked'     => false,
					'file'       => $zip,
					'wp_version' => $m['wp_version'] ?? '',
				)
			);
			$changed = true;
		}
		if ( $changed ) {
			update_option( self::INDEX, $all, false );
		}
	}

	public static function read_manifest( string $zip_path ): ?array {
		if ( ! class_exists( 'ZipArchive' ) || ! is_readable( $zip_path ) ) {
			return null;
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			return null;
		}
		$json = $zip->getFromName( 'manifest.json' );
		$zip->close();
		$data = is_string( $json ) ? json_decode( $json, true ) : null;
		return is_array( $data ) ? $data : null;
	}

	/* ------------------------------------------------------------------ */
	/* Dosya yardımcıları                                                  */
	/* ------------------------------------------------------------------ */

	/** Klasörü içindekilerle siler (yalnızca depolama klasörü içinde veya açıkça izin verilen yol). */
	public static function rrmdir( string $dir ): bool {
		$dir = untrailingslashit( wp_normalize_path( $dir ) );
		if ( '' === $dir || ! file_exists( $dir ) ) {
			return true;
		}
		if ( is_link( $dir ) || is_file( $dir ) ) {
			return @unlink( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
		}
		$items = @scandir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		foreach ( $items ? $items : array() as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			self::rrmdir( $dir . '/' . $item );
		}
		return @rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
	}

	/** Klasör boyutu (bayt); bağlantılar izlenmez. */
	public static function path_size( string $path, int $limit_seconds = 10 ): int {
		if ( is_file( $path ) ) {
			return (int) filesize( $path );
		}
		if ( ! is_dir( $path ) ) {
			return 0;
		}
		$size  = 0;
		$until = microtime( true ) + $limit_seconds;
		$stack = array( $path );
		while ( $stack ) {
			$d     = array_pop( $stack );
			$items = @scandir( $d ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			foreach ( $items ? $items : array() as $item ) {
				if ( '.' === $item || '..' === $item ) {
					continue;
				}
				$p = $d . '/' . $item;
				if ( is_link( $p ) && is_dir( $p ) ) {
					continue;
				}
				if ( is_dir( $p ) ) {
					$stack[] = $p;
				} else {
					$size += (int) @filesize( $p ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				}
			}
			if ( microtime( true ) > $until ) {
				break;
			}
		}
		return $size;
	}
}
