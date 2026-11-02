<?php
/**
 * Yedek alma: durum makinesi (tara → kota → veritabanı → dosyalar → bitir).
 *
 * Durum düz bir dizidir; step() onu süre bütçesi içinde ilerletir. Arka planda çalışan iş FLXY_Runner tarafından
 * seçenekte (flxy_job) saklanır, eşzamanlı (sync) iş aynı isteğin içinde sonuna kadar döner.
 *
 * Bir yedek tek zip: manifest.json, database.sql.gz (varsa) ve files/<ABSPATH'e göre yol>.
 * ZipArchive her close()'da arşivi yeniden yazar; bu yüzden her adımda tek close yapılır ve adımın bayt
 * hedefi önceki adımların ölçülen hızına göre ayarlanır. Zaten sıkıştırılmış dosyalar (jpg, zip, mp4 …)
 * sıkıştırılmadan (STORE) eklenir.
 */

defined( 'ABSPATH' ) || exit;

final class FLXY_Backup {

	const TYPES = array( 'full', 'db', 'files', 'pre_update', 'manual' );

	const STORED_EXT = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'heic', 'zip', 'gz', 'tgz', 'bz2', 'xz', '7z', 'rar', 'mp3', 'mp4', 'm4a', 'm4v', 'mov', 'avi', 'mkv', 'webm', 'ogg', 'ogv', 'woff', 'woff2', 'pdf', 'docx', 'xlsx', 'pptx', 'odt', 'jar', 'wpress' );

	/* ------------------------------------------------------------------ */
	/* Oluşturma                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * @param array $args type, label, db, paths, context, keep_days (anahtar yoksa varsayılan, null = kilitli),
	 *                    sync, include_core (yalnız full).
	 * @return array|WP_Error Kayıt.
	 */
	public static function create( array $args ) {
		$type = in_array( $args['type'] ?? '', self::TYPES, true ) ? $args['type'] : 'manual';
		$db   = array_key_exists( 'db', $args ) ? (bool) $args['db'] : in_array( $type, array( 'full', 'db', 'pre_update', 'manual' ), true );
		$paths = isset( $args['paths'] ) ? (array) $args['paths'] : array();
		if ( ! $paths && 'full' === $type ) {
			$core  = array_key_exists( 'include_core', $args ) ? (bool) $args['include_core'] : (bool) FLXY_Settings::get( 'include_core' );
			$paths = self::default_paths( $core );
		} elseif ( ! $paths && 'files' === $type ) {
			$paths = array( self::content_rel() );
		}
		if ( 'db' === $type ) {
			$db    = true;
			$paths = array();
		}
		$paths = self::clean_paths( $paths );
		if ( is_wp_error( $paths ) ) {
			return $paths;
		}
		if ( ! $db && ! $paths ) {
			return new WP_Error( 'flxy_empty', __( 'Yedeklenecek bir şey seçilmedi.', 'flx-backup' ) );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'flxy_zip', __( 'Sunucuda ZipArchive (PHP zip eklentisi) yok.', 'flx-backup' ) );
		}
		if ( ! function_exists( 'gzopen' ) ) {
			return new WP_Error( 'flxy_zlib', __( 'Sunucuda zlib (gzip) yok.', 'flx-backup' ) );
		}
		if ( ! FLXY_Storage::ensure() ) {
			return new WP_Error(
				'flxy_storage',
				/* translators: %s: directory */
				sprintf( __( 'Yedek klasörü yazılabilir değil: %s', 'flx-backup' ), FLXY_Storage::dir() )
			);
		}
		$sync = ! empty( $args['sync'] );
		if ( ! $sync && empty( $args['_nested'] ) && FLXY_Runner::busy() ) {
			return new WP_Error( 'flxy_busy', __( 'Şu anda başka bir yedekleme veya geri yükleme çalışıyor. Bitmesini bekleyin.', 'flx-backup' ) );
		}

		$context = isset( $args['context'] ) ? (array) $args['context'] : array();
		if ( empty( $context['source'] ) ) {
			$context['source'] = 'api';
		}

		// Saklama: anahtar yoksa varsayılan; null = kilitli; 0 = süresiz; n = n gün.
		$locked     = false;
		$keep_until = null;
		if ( array_key_exists( 'keep_days', $args ) ) {
			if ( null === $args['keep_days'] ) {
				$locked = true;
			} elseif ( (int) $args['keep_days'] > 0 ) {
				$keep_until = time() + (int) $args['keep_days'] * DAY_IN_SECONDS;
			}
		} elseif ( 'pre_update' === $type ) {
			$keep_until = time() + (int) FLXY_Settings::get( 'pre_keep_days' ) * DAY_IN_SECONDS;
		}

		$now  = time();
		$id   = gmdate( 'Ymd-His', $now ) . '-' . strtolower( wp_generate_password( 4, false, false ) );
		$file = FLXY_Storage::dir() . '/flxy-' . str_replace( '_', '-', $type ) . '-' . gmdate( 'Ymd-His', $now ) . '-' . strtolower( wp_generate_password( 16, false, false ) ) . '.zip';

		$record = FLXY_Storage::put(
			array(
				'id'         => $id,
				'type'       => $type,
				'label'      => sanitize_text_field( (string) ( $args['label'] ?? '' ) ),
				'status'     => 'running',
				'created'    => $now,
				'db'         => $db,
				'paths'      => $paths,
				'context'    => $context,
				'keep_until' => $keep_until,
				'locked'     => $locked,
				'file'       => $file,
				'wp_version' => get_bloginfo( 'version' ),
			)
		);

		$state = self::new_state( $record );

		if ( ! empty( $args['_nested'] ) ) {
			// Başka bir işin (geri yükleme) içinde adım adım yürütülecek.
			return array(
				'record' => $record,
				'state'  => $state,
			);
		}

		if ( $sync ) {
			$state = self::run_sync( $state );
			$rec   = FLXY_Storage::get( $id );
			if ( 'failed' === $state['phase'] ) {
				return new WP_Error( 'flxy_failed', $state['error'], $rec );
			}
			return $rec ? $rec : new WP_Error( 'flxy_failed', __( 'Yedek kaydı bulunamadı.', 'flx-backup' ) );
		}

		$started = FLXY_Runner::start( $state, ! empty( $args['interactive'] ) );
		if ( is_wp_error( $started ) ) {
			self::fail( $state, $started->get_error_message() );
			return $started;
		}
		return $record;
	}

	/** Var olan bir yedeğe yeni klasörler ekler (aynı istekteki ikinci güncelleme paketi). */
	public static function append( string $id, array $paths, array $context_packages = array() ) {
		$rec = FLXY_Storage::get( $id );
		if ( ! $rec || 'ok' !== $rec['status'] || ! file_exists( $rec['file'] ) ) {
			return new WP_Error( 'flxy_missing', __( 'Yedek bulunamadı.', 'flx-backup' ) );
		}
		$paths = self::clean_paths( $paths );
		if ( is_wp_error( $paths ) ) {
			return $paths;
		}
		$paths = array_values( array_diff( $paths, $rec['paths'] ) );
		if ( $context_packages ) {
			$ctx             = $rec['context'];
			$ctx['packages'] = array_merge( (array) ( $ctx['packages'] ?? array() ), $context_packages );
			FLXY_Storage::patch( $id, array( 'context' => $ctx ) );
			$rec = FLXY_Storage::get( $id );
		}
		if ( ! $paths ) {
			return $rec;
		}
		FLXY_Storage::patch( $id, array( 'status' => 'running' ) );
		$state           = self::new_state( $rec );
		$state['db']     = false;
		$state['append'] = true;
		$state['paths']  = $paths;
		$state['scan']['queue'] = array();
		$state           = self::seed_scan( $state );
		$state           = self::run_sync( $state );
		if ( 'failed' === $state['phase'] ) {
			// Arşivin önceki hali bozulmaz (ZipArchive close başarısızsa eskisi kalır): kaydı geri "ok" yap.
			FLXY_Storage::patch(
				$id,
				array(
					'status' => file_exists( $rec['file'] ) ? 'ok' : 'failed',
					'error'  => $state['error'],
				)
			);
			return new WP_Error( 'flxy_failed', $state['error'] );
		}
		return FLXY_Storage::get( $id );
	}

	public static function content_rel(): string {
		return self::rel( WP_CONTENT_DIR );
	}

	/** Tam yedeğin yolları: wp-content (+ isteğe bağlı çekirdek). */
	public static function default_paths( bool $core ): array {
		$paths = array( self::content_rel() );
		if ( $core ) {
			$paths[] = 'wp-admin';
			$paths[] = 'wp-includes';
			$root    = @scandir( ABSPATH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			foreach ( $root ? $root : array() as $f ) {
				if ( '.' !== $f && '..' !== $f && is_file( ABSPATH . $f ) ) {
					$paths[] = $f;
				}
			}
		}
		return $paths;
	}

	/** ABSPATH'e göre göreli yol. */
	public static function rel( string $abs ): string {
		$abs  = wp_normalize_path( $abs );
		$base = trailingslashit( wp_normalize_path( ABSPATH ) );
		return 0 === strpos( $abs, $base ) ? ltrim( substr( $abs, strlen( $base ) ), '/' ) : ltrim( $abs, '/' );
	}

	public static function abs( string $rel ): string {
		return trailingslashit( wp_normalize_path( ABSPATH ) ) . ltrim( $rel, '/' );
	}

	/** @return string[]|WP_Error */
	public static function clean_paths( array $paths ) {
		$out = array();
		foreach ( $paths as $p ) {
			$p = trim( str_replace( '\\', '/', (string) $p ), '/' );
			$p = self::rel( $p );
			if ( '' === $p ) {
				continue;
			}
			if ( false !== strpos( '/' . $p . '/', '/../' ) ) {
				return new WP_Error( 'flxy_path', __( 'Geçersiz yol.', 'flx-backup' ) );
			}
			if ( ! file_exists( self::abs( $p ) ) ) {
				return new WP_Error(
					'flxy_path',
					/* translators: %s: path */
					sprintf( __( 'Yol bulunamadı: %s', 'flx-backup' ), $p )
				);
			}
			$out[] = $p;
		}
		return array_values( array_unique( $out ) );
	}

	/* ------------------------------------------------------------------ */
	/* Durum                                                               */
	/* ------------------------------------------------------------------ */

	private static function new_state( array $rec ): array {
		$tmp   = FLXY_Storage::tmp_dir() . '/' . $rec['id'];
		$state = array(
			'kind'     => 'backup',
			'id'       => $rec['id'],
			'type'     => $rec['type'],
			'zip'      => $rec['file'],
			'tmp'      => $tmp,
			'db'       => (bool) $rec['db'],
			'paths'    => $rec['paths'],
			'append'   => false,
			'phase'    => 'scan',
			'scan'     => array(
				'queue' => array(),
				'files' => 0,
				'bytes' => 0,
				'items' => array(),
			),
			'excl'     => self::exclusions(),
			'dump'     => null,
			'zip_pos'  => 0,
			'zip_done' => 0,
			'zip_n'    => 0,
			'rate'     => 6000000,
			'started'  => time(),
			'steps'    => 0,
			'error'    => '',
			'warn'     => array(),
		);
		return self::seed_scan( $state );
	}

	private static function seed_scan( array $state ): array {
		wp_mkdir_p( $state['tmp'] );
		$list = $state['tmp'] . '/files.txt';
		file_put_contents( $list, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$queue = array();
		$fh    = fopen( $list, 'ab' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		foreach ( $state['paths'] as $rel ) {
			$abs = self::abs( $rel );
			if ( is_dir( $abs ) ) {
				$queue[] = $rel;
				self::add_item( $state, $rel, true );
			} elseif ( is_file( $abs ) && ! self::excluded( $rel, $state['excl'] ) ) {
				$size = (int) filesize( $abs );
				fwrite( $fh, $rel . "\t" . $size . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				++$state['scan']['files'];
				$state['scan']['bytes'] += $size;
				self::add_item( $state, $rel, true );
			}
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$state['scan']['queue'] = $queue;
		return $state;
	}

	/** Geri yüklemede seçilebilecek parçalar: wp-content altı, eklenti ve tema klasörleri. */
	private static function add_item( array &$state, string $rel, bool $explicit = false ): void {
		if ( $explicit || ! in_array( $rel, $state['scan']['items'], true ) ) {
			$state['scan']['items'][] = $rel;
		}
	}

	/** Varsayılan + ayarlardaki hariç tutmalar (ABSPATH'e göre yollar ve * kalıpları). */
	public static function exclusions(): array {
		$c    = self::content_rel();
		$list = array(
			self::rel( FLXY_Storage::dir() ),
			$c . '/wp-snapshots',
			$c . '/ai1wm-backups',
			$c . '/updraft',
			$c . '/wpvividbackups',
			$c . '/backups-dup-*',
			$c . '/backup-db',
			$c . '/cache',
			$c . '/litespeed',
			$c . '/upgrade',
			$c . '/upgrade-temp-backup',
			$c . '/uploads/backwpup-*',
			$c . '/et-cache',
		);
		$list = array_merge( $list, FLXY_Settings::exclude_list() );
		/**
		 * Yedeğe alınmayacak yollar (ABSPATH'e göre; * joker karakteri kullanılabilir).
		 *
		 * @param string[] $list
		 */
		return array_values( array_unique( (array) apply_filters( 'flxy_exclude_paths', $list ) ) );
	}

	public static function excluded( string $rel, array $excl ): bool {
		foreach ( $excl as $pat ) {
			if ( $rel === $pat || 0 === strpos( $rel, $pat . '/' ) ) {
				return true;
			}
			if ( false !== strpos( $pat, '*' ) && ( fnmatch( $pat, $rel ) || fnmatch( $pat . '/*', $rel ) ) ) {
				return true;
			}
		}
		return false;
	}

	/* ------------------------------------------------------------------ */
	/* Adımlar                                                             */
	/* ------------------------------------------------------------------ */

	public static function run_sync( array $state ): array {
		self::raise_limits();
		while ( ! in_array( $state['phase'], array( 'done', 'failed' ), true ) ) {
			$state = self::step( $state, microtime( true ) + 20 );
		}
		return $state;
	}

	public static function raise_limits(): void {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		wp_raise_memory_limit( 'admin' );
	}

	/** Durumu $deadline'a kadar ilerletir. */
	public static function step( array $state, float $deadline ): array {
		++$state['steps'];
		try {
			while ( microtime( true ) < $deadline && ! in_array( $state['phase'], array( 'done', 'failed' ), true ) ) {
				switch ( $state['phase'] ) {
					case 'scan':
						$state = self::step_scan( $state, $deadline );
						break;
					case 'quota':
						$state = self::step_quota( $state );
						break;
					case 'db':
						$state = self::step_db( $state, $deadline );
						break;
					case 'files':
						$state = self::step_files( $state, $deadline );
						break;
					case 'finalize':
						$state = self::step_finalize( $state );
						break;
					default:
						$state = self::fail( $state, 'Bilinmeyen aşama' );
				}
			}
		} catch ( Throwable $e ) {
			$state = self::fail( $state, $e->getMessage() );
		}
		return $state;
	}

	private static function step_scan( array $state, float $deadline ): array {
		$fh = fopen( $state['tmp'] . '/files.txt', 'ab' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			return self::fail( $state, __( 'Geçici dosya yazılamadı.', 'flx-backup' ) );
		}
		$content = self::content_rel();
		$n       = 0;
		while ( $state['scan']['queue'] ) {
			if ( 0 === ( ++$n % 20 ) && microtime( true ) > $deadline ) {
				break;
			}
			$rel   = array_pop( $state['scan']['queue'] );
			$abs   = self::abs( $rel );
			$items = @scandir( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( false === $items ) {
				$state['warn'][] = $rel;
				continue;
			}
			$parent_is_pkg_root = in_array( $rel, array( $content, $content . '/plugins', $content . '/themes' ), true );
			foreach ( $items as $item ) {
				if ( '.' === $item || '..' === $item ) {
					continue;
				}
				$crel = $rel . '/' . $item;
				$cabs = $abs . '/' . $item;
				if ( self::excluded( $crel, $state['excl'] ) ) {
					continue;
				}
				if ( is_dir( $cabs ) ) {
					if ( is_link( $cabs ) ) {
						continue; // Döngüye girmemek için bağlantılı klasörler izlenmez.
					}
					$state['scan']['queue'][] = $crel;
					if ( $parent_is_pkg_root ) {
						self::add_item( $state, $crel );
					}
				} elseif ( is_file( $cabs ) ) {
					$size = (int) @filesize( $cabs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
					fwrite( $fh, $crel . "\t" . $size . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
					++$state['scan']['files'];
					$state['scan']['bytes'] += $size;
					if ( $rel === $content . '/plugins' && '.php' === substr( $item, -4 ) && 'index.php' !== $item ) {
						self::add_item( $state, $crel ); // Tek dosyalı eklenti (hello.php gibi).
					}
				}
			}
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $state['scan']['queue'] ) {
			$state['phase'] = 'quota';
		}
		return $state;
	}

	/** Tahmini boyut kadar yer açar (gerekirse en eski kilitsiz yedekleri siler). */
	private static function step_quota( array $state ): array {
		$estimate = (int) ( $state['scan']['bytes'] * 0.9 );
		if ( $state['db'] ) {
			$estimate += (int) ( FLXY_DB::estimate_size() * 0.25 );
		}
		$state['estimate'] = $estimate;
		$room              = FLXY_Storage::make_room( $estimate, array( $state['id'] ) );
		if ( is_wp_error( $room ) ) {
			return self::fail( $state, $room->get_error_message() );
		}
		$state['phase'] = $state['db'] ? 'db' : ( $state['scan']['files'] ? 'files' : 'finalize' );
		return $state;
	}

	private static function step_db( array $state, float $deadline ): array {
		if ( null === $state['dump'] ) {
			$state['dump'] = FLXY_DB::dump_init();
		}
		$dump = $state['dump'];
		$res  = FLXY_DB::dump_step( $dump, $state['tmp'] . '/database.sql.gz', $deadline );
		$state['dump'] = $dump;
		if ( is_wp_error( $res ) ) {
			return self::fail( $state, $res->get_error_message() );
		}
		if ( $dump['done'] ) {
			$state['phase'] = $state['scan']['files'] ? 'files' : 'finalize';
		}
		return $state;
	}

	private static function step_files( array $state, float $deadline ): array {
		$list = $state['tmp'] . '/files.txt';
		$fh   = fopen( $list, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			return self::fail( $state, __( 'Dosya listesi okunamadı.', 'flx-backup' ) );
		}
		$zip = new ZipArchive();
		$res = $zip->open( $state['zip'], ZipArchive::CREATE );
		if ( true !== $res ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			/* translators: %s: error code */
			return self::fail( $state, sprintf( __( 'Zip dosyası açılamadı (kod %s).', 'flx-backup' ), $res ) );
		}
		$careful = ! empty( $state['careful'] );
		fseek( $fh, (int) $state['zip_pos'] );

		// Kalan süreye ve ölçülen hıza göre bu adımın bayt hedefi; mevcut arşivin yeniden yazılma süresi düşülür.
		$existing = file_exists( $state['zip'] ) ? (int) filesize( $state['zip'] ) : 0;
		$left     = max( 2.0, $deadline - microtime( true ) - $existing / 150000000 );
		$target   = max( 4 * MB_IN_BYTES, (int) ( $state['rate'] * $left * 0.8 ) );
		/**
		 * Bir dosya adımında arşive eklenecek en çok bayt (yavaş sunucularda adımları kısaltmak için).
		 *
		 * @param int   $target
		 * @param array $state
		 */
		$target = max( MB_IN_BYTES, (int) apply_filters( 'flxy_step_bytes', $target, $state ) );
		$mem      = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		$mem_cap  = $mem > 0 ? max( 8 * MB_IN_BYTES, (int) ( ( $mem - memory_get_usage() ) / 4 ) ) : 64 * MB_IN_BYTES;

		$batch = 0;
		$count = 0;
		$pos   = (int) $state['zip_pos'];
		$store = method_exists( $zip, 'setCompressionName' );
		$mem_b = 0;
		while ( true ) {
			$line = fgets( $fh );
			if ( false === $line ) {
				break;
			}
			$line = rtrim( $line, "\n" );
			$tab  = strrpos( $line, "\t" );
			if ( false === $tab ) {
				$pos = ftell( $fh );
				continue;
			}
			$rel  = substr( $line, 0, $tab );
			$size = (int) substr( $line, $tab + 1 );
			$abs  = self::abs( $rel );
			$name = 'files/' . $rel;
			if ( ! is_readable( $abs ) || ! is_file( $abs ) ) {
				$pos = ftell( $fh );
				continue;
			}
			if ( $careful && $size < 8 * MB_IN_BYTES ) {
				// Önceki close başarısız olduysa: içeriği şimdi oku, okunamayanı atla.
				$data = @file_get_contents( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
				if ( false === $data ) {
					$pos = ftell( $fh );
					continue;
				}
				$zip->addFromString( $name, $data );
				$mem_b += strlen( $data );
				unset( $data );
			} else {
				$zip->addFile( $abs, $name );
			}
			if ( $store ) {
				$ext = strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) );
				if ( in_array( $ext, self::STORED_EXT, true ) ) {
					$zip->setCompressionName( $name, ZipArchive::CM_STORE );
				}
			}
			$batch += $size;
			++$count;
			$pos = ftell( $fh );
			if ( $batch >= $target || $count >= 3000 || $mem_b >= $mem_cap ) {
				break;
			}
		}
		$eof = false === fgets( $fh );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$t0 = microtime( true );
		$ok = $zip->close();
		$dt = max( 0.05, microtime( true ) - $t0 );
		if ( ! $ok ) {
			if ( ! $careful ) {
				$state['careful'] = true; // Aynı grubu dikkatli kipte tekrar dene.
				return $state;
			}
			/* translators: %s: zip error */
			return self::fail( $state, sprintf( __( 'Zip yazılamadı: %s', 'flx-backup' ), $zip->getStatusString() ) );
		}
		$state['careful']   = false;
		$state['zip_pos']   = $pos;
		$state['zip_done'] += $batch;
		$state['zip_n']    += $count;
		if ( $batch > MB_IN_BYTES ) {
			$state['rate'] = (int) ( 0.5 * $state['rate'] + 0.5 * ( $batch / $dt ) );
		}
		if ( $eof ) {
			$state['phase'] = 'finalize';
		}
		return $state;
	}

	private static function step_finalize( array $state ): array {
		global $wpdb;
		$rec = FLXY_Storage::get( $state['id'] );
		if ( ! $rec ) {
			return self::fail( $state, __( 'Yedek kaydı silinmiş.', 'flx-backup' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $state['zip'], ZipArchive::CREATE ) ) {
			return self::fail( $state, __( 'Zip dosyası açılamadı.', 'flx-backup' ) );
		}
		$old = array();
		if ( $state['append'] ) {
			$json = $zip->getFromName( 'manifest.json' );
			$old  = is_string( $json ) ? (array) json_decode( $json, true ) : array();
		}
		$gz        = $state['tmp'] . '/database.sql.gz';
		$checksums = isset( $old['checksums'] ) ? (array) $old['checksums'] : array();
		$gz_size   = (int) ( $old['sizes']['database_gz'] ?? 0 );
		if ( $state['db'] && file_exists( $gz ) ) {
			$zip->addFile( $gz, 'database.sql.gz' );
			if ( method_exists( $zip, 'setCompressionName' ) ) {
				$zip->setCompressionName( 'database.sql.gz', ZipArchive::CM_STORE );
			}
			$checksums['database.sql.gz'] = 'sha256:' . hash_file( 'sha256', $gz );
			$gz_size                      = (int) filesize( $gz );
		}
		$items = array_values( array_unique( array_merge( (array) ( $old['contents'] ?? array() ), $state['scan']['items'] ) ) );
		sort( $items );
		$paths = array_values( array_unique( array_merge( (array) ( $old['paths'] ?? array() ), $state['paths'] ) ) );
		$rec   = FLXY_Storage::get( $state['id'] );

		$manifest = array(
			'plugin'      => 'flx-backup',
			'format'      => 1,
			'version'     => FLXY_VERSION,
			'id'          => $rec['id'],
			'type'        => $rec['type'],
			'label'       => $rec['label'],
			'created'     => $rec['created'],
			'created_gmt' => gmdate( 'c', $rec['created'] ),
			'site_url'    => site_url(),
			'home_url'    => home_url(),
			'wp_version'  => get_bloginfo( 'version' ),
			'php_version' => PHP_VERSION,
			'db'          => array(
				'included' => $state['db'] || ! empty( $old['db']['included'] ),
				'prefix'   => $wpdb->base_prefix,
				'engine'   => FLXY_DB::is_sqlite() ? 'sqlite' : 'mysql',
				'server'   => FLXY_DB::is_sqlite() ? '' : (string) $wpdb->db_server_info(),
				'tables'   => $state['dump'] ? $state['dump']['tables'] : ( $old['db']['tables'] ?? array() ),
				'rows'     => $state['dump'] ? $state['dump']['rows_done'] : ( $old['db']['rows'] ?? 0 ),
			),
			'paths'       => $paths,
			'contents'    => $items,
			'context'     => $rec['context'],
			'sizes'       => array(
				'database_gz' => $gz_size,
				'files_raw'   => (int) ( $old['sizes']['files_raw'] ?? 0 ) + (int) $state['zip_done'],
				'files_count' => (int) ( $old['sizes']['files_count'] ?? 0 ) + (int) $state['zip_n'],
			),
			'checksums'   => $checksums,
			'excluded'    => $state['excl'],
		);
		$zip->addFromString( 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		if ( ! $zip->close() ) {
			/* translators: %s: zip error */
			return self::fail( $state, sprintf( __( 'Zip yazılamadı: %s', 'flx-backup' ), $zip->getStatusString() ) );
		}
		clearstatcache( true, $state['zip'] );
		FLXY_Storage::rrmdir( $state['tmp'] );
		$breakdown = null;
		if ( ! $state['append'] ) {
			$stats     = $state['dump'] ? (array) ( $state['dump']['stats'] ?? array() ) : array();
			$breakdown = FLXY_Breakdown::compute( $state['zip'], $stats );
		}

		$rec = FLXY_Storage::patch(
			$state['id'],
			array(
				'status'    => 'ok',
				'size'      => (int) filesize( $state['zip'] ),
				'finished'  => time(),
				'paths'     => $paths,
				'items'     => $items,
				'files'     => $manifest['sizes']['files_count'],
				'db'        => $manifest['db']['included'],
				'error'     => '',
				'breakdown' => $breakdown,
			)
		);
		$state['phase'] = 'done';
		FLXY_Storage::sample();
		if ( ! $state['append'] ) {
			FLXY_Storage::log(
				'backup',
				sprintf(
					/* translators: 1: backup title, 2: size */
					__( 'Yedek alındı: %1$s (%2$s)', 'flx-backup' ),
					FLXY_Storage::title( $rec ),
					size_format( $rec['size'], 1 )
				)
			);
			/**
			 * Yeni bir yedek başarıyla tamamlandı.
			 *
			 * @param array $record Kayıt (id, type, label, status, created, size, db, paths, context, keep_until, locked, file, error).
			 */
			do_action( 'flxy_backup_created', $rec );
		} else {
			/** Var olan yedeğe paket eklendi. */
			do_action( 'flxy_backup_appended', $rec, $state['paths'] );
		}
		return $state;
	}

	public static function fail( array $state, string $message ): array {
		$state['phase'] = 'failed';
		$state['error'] = $message;
		if ( ! empty( $state['append'] ) ) {
			return $state;
		}
		if ( ! empty( $state['zip'] ) && FLXY_Storage::is_inside( $state['zip'] ) && file_exists( $state['zip'] ) ) {
			wp_delete_file( $state['zip'] );
		}
		if ( ! empty( $state['tmp'] ) && FLXY_Storage::is_inside( $state['tmp'] ) ) {
			FLXY_Storage::rrmdir( $state['tmp'] );
		}
		$rec = FLXY_Storage::patch(
			$state['id'],
			array(
				'status'   => 'failed',
				'error'    => $message,
				'size'     => 0,
				'finished' => time(),
			)
		);
		FLXY_Storage::log(
			'backup',
			sprintf(
				/* translators: 1: backup title, 2: error */
				__( 'Yedek alınamadı (%1$s): %2$s', 'flx-backup' ),
				$rec ? FLXY_Storage::title( $rec ) : $state['id'],
				$message
			),
			'error'
		);
		/**
		 * Yedek alınamadı.
		 *
		 * @param array|null $record
		 * @param string     $message
		 */
		do_action( 'flxy_backup_failed', $rec, $message );
		return $state;
	}

	/** İlerleme: yüzde ve ekranda gösterilecek metin. */
	public static function progress( array $state ): array {
		$w = array(
			'scan'     => 4,
			'quota'    => 1,
			'db'       => $state['db'] ? 35 : 0,
			'files'    => $state['scan']['files'] || 'scan' === $state['phase'] ? 55 : 0,
			'finalize' => 5,
		);
		$sum   = array_sum( $w );
		$done  = 0.0;
		$order = array_keys( $w );
		$cur   = array_search( $state['phase'], $order, true );
		foreach ( $order as $i => $ph ) {
			if ( false === $cur || $i < $cur ) {
				$done += $w[ $ph ];
			}
		}
		$text = '';
		switch ( $state['phase'] ) {
			case 'scan':
				/* translators: %s: number of files */
				$text  = sprintf( __( 'Dosyalar taranıyor… %s dosya', 'flx-backup' ), number_format_i18n( $state['scan']['files'] ) );
				$done += 2;
				break;
			case 'quota':
				$text = __( 'Kota kontrol ediliyor…', 'flx-backup' );
				break;
			case 'db':
				$d     = $state['dump'];
				$frac  = $d ? min( 1, $d['rows_done'] / max( 1, $d['rows_total'] ) ) : 0;
				$done += $w['db'] * $frac;
				$table = $d && isset( $d['tables'][ $d['ti'] ] ) ? $d['tables'][ $d['ti'] ] : '';
				/* translators: 1: table name, 2: rows done */
				$text = sprintf( __( 'Veritabanı yedekleniyor: %1$s (%2$s satır)', 'flx-backup' ), $table, number_format_i18n( $d ? $d['rows_done'] : 0 ) );
				break;
			case 'files':
				$frac  = min( 1, $state['zip_done'] / max( 1, $state['scan']['bytes'] ) );
				$done += $w['files'] * $frac;
				/* translators: 1: files done, 2: total files, 3: size done, 4: total size */
				$text = sprintf( __( 'Dosyalar arşivleniyor: %1$s / %2$s dosya (%3$s / %4$s)', 'flx-backup' ), number_format_i18n( $state['zip_n'] ), number_format_i18n( $state['scan']['files'] ), size_format( $state['zip_done'], 1 ), size_format( $state['scan']['bytes'], 1 ) );
				break;
			case 'finalize':
				$text = __( 'Tamamlanıyor…', 'flx-backup' );
				break;
			case 'done':
				$done = $sum;
				$text = __( 'Yedek tamamlandı.', 'flx-backup' );
				break;
			case 'failed':
				$text = $state['error'];
				break;
		}
		return array(
			'percent' => (int) floor( 100 * $done / max( 1, $sum ) ),
			'text'    => $text,
		);
	}
}
