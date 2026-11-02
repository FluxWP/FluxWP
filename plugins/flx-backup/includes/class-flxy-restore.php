<?php
/**
 * Geri yükleme: güvenlik yedeği → veritabanı (zip'ten çıkar, aç, ifade ifade çalıştır) → dosyalar → bitir.
 *
 * Korunanlar: siteurl/home bu sitenin değerleri kalır; flxy_* seçenekleri (yedek dizini, ayarlar, geçmiş)
 * güncel halleriyle geri yazılır; Flux Backup etkin eklentilerde kalır; işlemi başlatan kullanıcının oturumu korunur.
 * Eklenti/tema klasörleri "değiştir" kipinde geri yüklenir (mevcut klasör kenara alınır, yedekteki açılır,
 * başarıyla bitince kenardaki silinir; hata olursa geri alınır). Diğer yollar üzerine yazılır.
 */

defined( 'ABSPATH' ) || exit;

final class FLXY_Restore {

	/**
	 * Arka plan işi olarak başlatır (yönetim ekranı).
	 *
	 * @param array $parts db (bool), paths (ABSPATH'e göre), safety (bool, boşsa ayar).
	 * @return array|WP_Error Durum.
	 */
	public static function start( string $id, array $parts, bool $interactive = true ) {
		$state = self::new_state( $id, $parts );
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		$res = FLXY_Runner::start( $state, $interactive );
		if ( is_wp_error( $res ) ) {
			self::cleanup( $state );
			return $res;
		}
		return FLXY_Runner::load_restore();
	}

	/**
	 * Aynı istekte sonuna kadar çalıştırır (API, otomatik geri alma).
	 *
	 * @return true|WP_Error
	 */
	public static function run_sync( string $id, array $parts ) {
		if ( ! empty( $parts['db'] ) && FLXY_Runner::busy() ) {
			return new WP_Error( 'flxy_busy', __( 'Şu anda başka bir yedekleme veya geri yükleme çalışıyor. Bitmesini bekleyin.', 'flx-backup' ) );
		}
		$state = self::new_state( $id, $parts );
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		FLXY_Backup::raise_limits();
		while ( ! in_array( $state['phase'], array( 'done', 'failed' ), true ) ) {
			$state = self::step( $state, microtime( true ) + 20 );
		}
		if ( 'failed' === $state['phase'] ) {
			return new WP_Error( 'flxy_restore_failed', $state['error'] );
		}
		return true;
	}

	/** @return array|WP_Error */
	private static function new_state( string $id, array $parts ) {
		$rec = FLXY_Storage::get( $id );
		if ( ! $rec || 'ok' !== $rec['status'] ) {
			return new WP_Error( 'flxy_missing', __( 'Yedek bulunamadı.', 'flx-backup' ) );
		}
		if ( ! file_exists( $rec['file'] ) ) {
			return new WP_Error( 'flxy_missing', __( 'Yedek dosyası diskte yok.', 'flx-backup' ) );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'flxy_zip', __( 'Sunucuda ZipArchive (PHP zip eklentisi) yok.', 'flx-backup' ) );
		}
		$db    = ! empty( $parts['db'] );
		$paths = array();
		foreach ( (array) ( $parts['paths'] ?? array() ) as $p ) {
			$p = trim( str_replace( '\\', '/', (string) $p ), '/' );
			$p = FLXY_Backup::rel( $p );
			if ( '' === $p || false !== strpos( '/' . $p . '/', '/../' ) ) {
				continue;
			}
			$covered = false;
			foreach ( $rec['paths'] as $bp ) {
				if ( $p === $bp || 0 === strpos( $p, $bp . '/' ) ) {
					$covered = true;
				}
			}
			if ( ! $covered ) {
				return new WP_Error(
					'flxy_path',
					/* translators: %s: path */
					sprintf( __( 'Bu yol yedekte yok: %s', 'flx-backup' ), $p )
				);
			}
			$paths[] = $p;
		}
		$paths = array_values( array_unique( $paths ) );
		if ( $db && ! $rec['db'] ) {
			return new WP_Error( 'flxy_nodb', __( 'Bu yedekte veritabanı yok.', 'flx-backup' ) );
		}
		if ( ! $db && ! $paths ) {
			return new WP_Error( 'flxy_empty', __( 'Geri yüklenecek bir şey seçilmedi.', 'flx-backup' ) );
		}
		$safety = array_key_exists( 'safety', $parts ) ? (bool) $parts['safety'] : (bool) FLXY_Settings::get( 'safety_restore' );
		$tmp    = FLXY_Storage::tmp_dir() . '/restore-' . strtolower( wp_generate_password( 8, false, false ) );
		wp_mkdir_p( $tmp );

		$user = get_current_user_id();
		return array(
			'kind'      => 'restore',
			'id'        => $id,
			'zip'       => $rec['file'],
			'title'     => FLXY_Storage::title( $rec ),
			'tmp'       => $tmp,
			'db'        => $db,
			'paths'     => $paths,
			'phase'     => $db && $safety ? 'safety' : ( $db ? 'db_extract' : 'files_prepare' ),
			'safety'    => null,
			'safety_id' => '',
			'gz_pos'    => 0,
			'sql'       => array(
				'offset'          => 0,
				'done'            => false,
				'errors'          => 0,
				'error_list'      => array(),
				'inserts'         => 0,
				'table'           => '',
				'options_touched' => false,
			),
			'sql_size'  => 0,
			'keep'      => null,
			'user'      => $user,
			'session'   => $user ? get_user_meta( $user, 'session_tokens', true ) : null,
			'entries'   => array(),
			'epos'      => 0,
			'moved'     => array(),
			'restored'  => 0,
			'error'     => '',
			'result'    => '',
			'started'   => time(),
		);
	}

	public static function step( array $state, float $deadline ): array {
		try {
			while ( microtime( true ) < $deadline && ! in_array( $state['phase'], array( 'done', 'failed' ), true ) ) {
				switch ( $state['phase'] ) {
					case 'safety':
						$state = self::step_safety( $state, $deadline );
						break;
					case 'db_extract':
						$state = self::step_db_extract( $state );
						break;
					case 'db_gunzip':
						$state = self::step_gunzip( $state, $deadline );
						break;
					case 'db_import':
						$state = self::step_import( $state, $deadline );
						break;
					case 'files_prepare':
						$state = self::step_files_prepare( $state );
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

	/** Güvenlik yedeği: yalnız veritabanı, 7 gün saklanır; geri yüklemenin adımları içinde yürür. */
	private static function step_safety( array $state, float $deadline ): array {
		if ( null === $state['safety'] ) {
			$res = FLXY_Backup::create(
				array(
					'type'      => 'db',
					/* translators: %s: backup title */
					'label'     => sprintf( __( 'Geri yükleme öncesi güvenlik yedeği (%s)', 'flx-backup' ), $state['title'] ),
					'context'   => array(
						'source'     => 'safety',
						'restore_of' => $state['id'],
					),
					'keep_days' => 7,
					'_nested'   => true,
				)
			);
			if ( is_wp_error( $res ) ) {
				/* translators: %s: error */
				return self::fail( $state, sprintf( __( 'Güvenlik yedeği alınamadı, geri yükleme yapılmadı: %s', 'flx-backup' ), $res->get_error_message() ) );
			}
			$state['safety_id'] = $res['record']['id'];
			$state['safety']    = $res['state'];
		}
		$state['safety'] = FLXY_Backup::step( $state['safety'], $deadline );
		if ( 'failed' === $state['safety']['phase'] ) {
			$err                = $state['safety']['error'];
			$state['safety_id'] = '';
			/* translators: %s: error */
			return self::fail( $state, sprintf( __( 'Güvenlik yedeği alınamadı, geri yükleme yapılmadı: %s', 'flx-backup' ), $err ) );
		}
		if ( 'done' === $state['safety']['phase'] ) {
			$state['safety'] = true;
			$state['phase']  = 'db_extract';
		}
		return $state;
	}

	private static function step_db_extract( array $state ): array {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $state['zip'] ) ) {
			return self::fail( $state, __( 'Zip dosyası açılamadı.', 'flx-backup' ) );
		}
		$in = $zip->getStream( 'database.sql.gz' );
		if ( ! $in ) {
			$zip->close();
			return self::fail( $state, __( 'Yedekte database.sql.gz yok.', 'flx-backup' ) );
		}
		$gz  = $state['tmp'] . '/database.sql.gz';
		$out = fopen( $gz, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		stream_copy_to_stream( $in, $out );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$manifest = $zip->getFromName( 'manifest.json' );
		$zip->close();

		$m = is_string( $manifest ) ? json_decode( $manifest, true ) : array();
		if ( ! empty( $m['checksums']['database.sql.gz'] ) ) {
			$expect = (string) $m['checksums']['database.sql.gz'];
			if ( 'sha256:' . hash_file( 'sha256', $gz ) !== $expect ) {
				return self::fail( $state, __( 'Veritabanı dosyasının sağlama toplamı tutmuyor; yedek bozuk olabilir.', 'flx-backup' ) );
			}
		}
		file_put_contents( $state['tmp'] . '/database.sql', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$state['phase'] = 'db_gunzip';
		return $state;
	}

	private static function step_gunzip( array $state, float $deadline ): array {
		$gz = gzopen( $state['tmp'] . '/database.sql.gz', 'rb' );
		if ( ! $gz ) {
			return self::fail( $state, __( 'SQL dosyası açılamadı.', 'flx-backup' ) );
		}
		if ( $state['gz_pos'] > 0 ) {
			gzseek( $gz, (int) $state['gz_pos'] );
		}
		$out = fopen( $state['tmp'] . '/database.sql', 'ab' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		while ( ! gzeof( $gz ) && microtime( true ) < $deadline ) {
			$chunk = gzread( $gz, 1048576 );
			if ( false === $chunk ) {
				break;
			}
			fwrite( $out, $chunk ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$state['gz_pos'] += strlen( $chunk );
		}
		$eof = gzeof( $gz );
		gzclose( $gz );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( $eof ) {
			global $wpdb;
			$sql_file          = $state['tmp'] . '/database.sql';
			$state['sql_size'] = (int) filesize( $sql_file );
			$prefix            = FLXY_DB::dump_prefix( $sql_file );
			if ( '' !== $prefix && $prefix !== $wpdb->base_prefix ) {
				return self::fail(
					$state,
					/* translators: 1: prefix in backup, 2: current prefix */
					sprintf( __( 'Yedekteki tablo öneki (%1$s) bu sitenin önekinden (%2$s) farklı; veritabanı geri yüklenmedi.', 'flx-backup' ), $prefix, $wpdb->base_prefix )
				);
			}
			$state['keep']  = self::snapshot_keep();
			$state['phase'] = 'db_import';
		}
		return $state;
	}

	/** Geri yüklemeden sonra yeniden yazılacak güncel seçenekler. */
	private static function snapshot_keep(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name IN ('siteurl','home') OR option_name LIKE %s",
				$wpdb->esc_like( 'flxy_' ) . '%'
			),
			ARRAY_A
		);
		$keep = array();
		foreach ( (array) $rows as $r ) {
			if ( FLXY_Runner::OPTION === $r['option_name'] ) {
				continue; // Çalışan yedek işi değil, geri yükleme sürüyor.
			}
			$keep[ $r['option_name'] ] = array( base64_encode( $r['option_value'] ), $r['autoload'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		}
		/**
		 * Veritabanı geri yüklenirken güncel değeri korunacak ek seçenek adları.
		 *
		 * @param string[] $names
		 */
		$extra = (array) apply_filters( 'flxy_restore_keep_options', array() );
		foreach ( $extra as $name ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $name ), ARRAY_A );
			if ( $row ) {
				$keep[ $name ] = array( base64_encode( $row['option_value'] ), $row['autoload'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			}
		}
		return $keep;
	}

	/** Korunan seçenekleri yazar ve Flux Backup'in etkin kalmasını sağlar. */
	private static function pin( array $state ): void {
		global $wpdb;
		$suppress = $wpdb->suppress_errors( true );
		foreach ( (array) $state['keep'] as $name => $v ) {
			$wpdb->replace(
				$wpdb->options,
				array(
					'option_name'  => $name,
					'option_value' => base64_decode( $v[0] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
					'autoload'     => $v[1],
				)
			);
		}
		$raw    = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'active_plugins'" );
		$active = $raw ? maybe_unserialize( $raw ) : array();
		$active = is_array( $active ) ? $active : array();
		$me     = plugin_basename( FLXY_FILE );
		if ( ! in_array( $me, $active, true ) ) {
			$active[] = $me;
			$wpdb->replace(
				$wpdb->options,
				array(
					'option_name'  => 'active_plugins',
					'option_value' => maybe_serialize( array_values( $active ) ),
					'autoload'     => 'yes',
				)
			);
		}
		$wpdb->suppress_errors( $suppress );
		wp_cache_flush();
	}

	private static function step_import( array $state, float $deadline ): array {
		$sql = $state['sql'];
		$res = FLXY_DB::import_step( $sql, $state['tmp'] . '/database.sql', $deadline );
		$state['sql'] = $sql;
		if ( is_wp_error( $res ) ) {
			return self::fail( $state, $res->get_error_message() );
		}
		if ( $sql['options_touched'] ) {
			self::pin( $state );
		}
		if ( $sql['done'] ) {
			self::pin( $state );
			self::restore_session( $state );
			$state['phase'] = $state['paths'] ? 'files_prepare' : 'finalize';
		}
		return $state;
	}

	private static function restore_session( array $state ): void {
		global $wpdb;
		if ( empty( $state['user'] ) || empty( $state['session'] ) ) {
			return;
		}
		$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE ID = %d", $state['user'] ) );
		if ( ! $exists ) {
			return;
		}
		$wpdb->delete(
			$wpdb->usermeta,
			array(
				'user_id'  => $state['user'],
				'meta_key' => 'session_tokens', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		$wpdb->insert(
			$wpdb->usermeta,
			array(
				'user_id'    => $state['user'],
				'meta_key'   => 'session_tokens', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => maybe_serialize( $state['session'] ), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		wp_cache_flush();
	}

	/** Paket klasörü mü (eklenti/tema): değiştir kipinde geri yüklenir. */
	private static function is_package_dir( string $rel ): bool {
		$c = FLXY_Backup::content_rel();
		return (bool) preg_match( '#^' . preg_quote( $c, '#' ) . '/(plugins|themes|mu-plugins)/[^/]+$#', $rel ) && is_dir( FLXY_Backup::abs( $rel ) );
	}

	/** Korunan yollar: Flux Backup'in kendi klasörü ve depolama klasörü asla üzerine yazılmaz. */
	private static function protected_rel(): array {
		return array(
			FLXY_Backup::rel( untrailingslashit( FLXY_DIR ) ),
			FLXY_Backup::rel( FLXY_Storage::dir() ),
		);
	}

	private static function step_files_prepare( array $state ): array {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $state['zip'] ) ) {
			return self::fail( $state, __( 'Zip dosyası açılamadı.', 'flx-backup' ) );
		}
		$protected = self::protected_rel();
		$entries   = array();
		$found     = array_fill_keys( $state['paths'], false );
		$n         = $zip->numFiles;
		for ( $i = 0; $i < $n; $i++ ) {
			$name = (string) $zip->getNameIndex( $i );
			if ( 0 !== strpos( $name, 'files/' ) ) {
				continue;
			}
			$rel = substr( $name, 6 );
			if ( '' === $rel || '/' === $rel[0] || false !== strpos( $rel, '\\' ) || false !== strpos( '/' . $rel . '/', '/../' ) ) {
				continue; // Güvenli olmayan yol (zip slip): yazılmaz.
			}
			foreach ( $state['paths'] as $p ) {
				if ( $rel === $p || 0 === strpos( $rel, $p . '/' ) ) {
					$found[ $p ] = true;
					$skip        = false;
					foreach ( $protected as $pr ) {
						if ( $rel === $pr || 0 === strpos( $rel, $pr . '/' ) ) {
							$skip = true;
						}
					}
					if ( ! $skip ) {
						$entries[] = $i;
					}
					break;
				}
			}
		}
		$zip->close();
		$missing = array_keys( array_filter( $found, static function ( $v ) {
			return ! $v;
		} ) );
		if ( $missing ) {
			/* translators: %s: paths */
			return self::fail( $state, sprintf( __( 'Yedekte bulunamadı: %s', 'flx-backup' ), implode( ', ', $missing ) ) );
		}
		// Paket klasörlerini kenara al (yedekte olmayan yeni dosyalar kalmasın).
		foreach ( $state['paths'] as $p ) {
			if ( in_array( $p, $protected, true ) || ! self::is_package_dir( $p ) ) {
				continue;
			}
			$abs   = FLXY_Backup::abs( $p );
			$aside = $abs . '.flxy-old-' . strtolower( wp_generate_password( 6, false, false ) );
			if ( ! @rename( $abs, $aside ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
				$state = self::undo_moves( $state );
				/* translators: %s: path */
				return self::fail( $state, sprintf( __( 'Klasör taşınamadı (izin sorunu?): %s', 'flx-backup' ), $p ) );
			}
			$state['moved'][ $abs ] = $aside;
		}
		$state['entries'] = $entries;
		$state['epos']    = 0;
		$state['phase']   = 'files';
		return $state;
	}

	private static function step_files( array $state, float $deadline ): array {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $state['zip'] ) ) {
			return self::fail( $state, __( 'Zip dosyası açılamadı.', 'flx-backup' ) );
		}
		$base  = trailingslashit( wp_normalize_path( ABSPATH ) );
		$total = count( $state['entries'] );
		while ( $state['epos'] < $total && microtime( true ) < $deadline ) {
			$i    = $state['entries'][ $state['epos'] ];
			$name = (string) $zip->getNameIndex( $i );
			$rel  = substr( $name, 6 );
			$dest = $base . $rel;
			if ( '/' === substr( $name, -1 ) ) {
				wp_mkdir_p( $dest );
			} else {
				if ( ! is_dir( dirname( $dest ) ) && ! wp_mkdir_p( dirname( $dest ) ) ) {
					$zip->close();
					/* translators: %s: path */
					return self::fail( $state, sprintf( __( 'Klasör oluşturulamadı: %s', 'flx-backup' ), dirname( $rel ) ) );
				}
				$in  = $zip->getStream( $name );
				$out = $in ? @fopen( $dest, 'wb' ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
				if ( ! $in || ! $out ) {
					if ( $in ) {
						fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions
					}
					$zip->close();
					/* translators: %s: path */
					return self::fail( $state, sprintf( __( 'Dosya yazılamadı: %s', 'flx-backup' ), $rel ) );
				}
				stream_copy_to_stream( $in, $out );
				fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				if ( defined( 'FS_CHMOD_FILE' ) ) {
					@chmod( $dest, FS_CHMOD_FILE ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				}
				if ( '.php' === substr( $dest, -4 ) && function_exists( 'wp_opcache_invalidate' ) ) {
					wp_opcache_invalidate( $dest, true );
				}
				++$state['restored'];
			}
			++$state['epos'];
		}
		$zip->close();
		if ( $state['epos'] >= $total ) {
			$state['phase'] = 'finalize';
		}
		return $state;
	}

	private static function step_finalize( array $state ): array {
		foreach ( $state['moved'] as $aside ) {
			FLXY_Storage::rrmdir( $aside );
		}
		$state['moved'] = array();
		self::cleanup( $state );
		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache( false );
		}
		wp_cache_flush();

		$parts = array();
		if ( $state['db'] ) {
			$parts[] = __( 'veritabanı', 'flx-backup' );
		}
		if ( $state['paths'] ) {
			/* translators: %s: number of files */
			$parts[] = sprintf( _n( '%s dosya', '%s dosya', $state['restored'], 'flx-backup' ), number_format_i18n( $state['restored'] ) );
		}
		/* translators: 1: backup title, 2: restored parts */
		$msg = sprintf( __( 'Geri yüklendi: %1$s (%2$s).', 'flx-backup' ), $state['title'], implode( ', ', $parts ) );
		if ( $state['db'] && $state['sql']['errors'] ) {
			/* translators: %s: number of failed SQL statements */
			$msg .= ' ' . sprintf( __( '%s SQL ifadesi hata verdi; ayrıntı günlükte.', 'flx-backup' ), number_format_i18n( $state['sql']['errors'] ) );
			FLXY_Storage::log( 'restore', implode( ' | ', $state['sql']['error_list'] ), 'error' );
		}
		$state['result'] = $msg;
		$state['phase']  = 'done';
		FLXY_Storage::log( 'restore', $msg );
		update_option(
			'flxy_last_restore',
			array(
				'at'     => time(),
				'id'     => $state['id'],
				'db'     => $state['db'],
				'paths'  => $state['paths'],
				'result' => $msg,
			),
			false
		);
		/**
		 * Geri yükleme bitti.
		 *
		 * @param string $id    Yedek kimliği.
		 * @param array  $parts db, paths.
		 */
		do_action( 'flxy_backup_restored', $state['id'], array( 'db' => $state['db'], 'paths' => $state['paths'] ) );
		return $state;
	}

	/** Kenara alınan klasörleri geri koyar. */
	private static function undo_moves( array $state ): array {
		foreach ( $state['moved'] as $orig => $aside ) {
			if ( file_exists( $orig ) ) {
				FLXY_Storage::rrmdir( $orig );
			}
			@rename( $aside, $orig ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
		}
		$state['moved'] = array();
		return $state;
	}

	private static function cleanup( array $state ): void {
		if ( ! empty( $state['tmp'] ) && FLXY_Storage::is_inside( $state['tmp'] ) ) {
			FLXY_Storage::rrmdir( $state['tmp'] );
		}
	}

	public static function fail( array $state, string $message ): array {
		$state = self::undo_moves( $state );
		if ( ! empty( $state['sql']['options_touched'] ) && ! empty( $state['keep'] ) ) {
			self::pin( $state );
		}
		self::cleanup( $state );
		$state['phase'] = 'failed';
		$state['error'] = $message;
		if ( ! empty( $state['safety_id'] ) && $state['db'] && $state['sql']['inserts'] > 0 ) {
			/* translators: %s: error */
			$state['error'] = sprintf( __( '%s Veritabanı yarım kalmış olabilir; "Geri yükleme öncesi güvenlik yedeği"ni geri yükleyin.', 'flx-backup' ), $message );
		}
		FLXY_Storage::log(
			'restore',
			/* translators: 1: backup title, 2: error */
			sprintf( __( 'Geri yükleme başarısız (%1$s): %2$s', 'flx-backup' ), $state['title'] ?? '', $state['error'] ),
			'error'
		);
		return $state;
	}

	public static function progress( array $state ): array {
		$pct  = 0;
		$text = '';
		switch ( $state['phase'] ) {
			case 'safety':
				$sub  = is_array( $state['safety'] ) ? FLXY_Backup::progress( $state['safety'] ) : array( 'percent' => 0, 'text' => '' );
				$pct  = (int) ( $sub['percent'] / 10 );
				/* translators: %s: progress of the safety backup */
				$text = sprintf( __( 'Güvenlik yedeği alınıyor: %s', 'flx-backup' ), $sub['text'] );
				break;
			case 'db_extract':
			case 'db_gunzip':
				$pct  = 10;
				$text = __( 'Veritabanı dosyası açılıyor…', 'flx-backup' );
				break;
			case 'db_import':
				$frac = $state['sql_size'] ? min( 1, $state['sql']['offset'] / $state['sql_size'] ) : 0;
				$pct  = 12 + (int) ( ( $state['paths'] ? 50 : 83 ) * $frac );
				/* translators: %s: table name */
				$text = sprintf( __( 'Veritabanı geri yükleniyor: %s', 'flx-backup' ), $state['sql']['table'] );
				break;
			case 'files_prepare':
				$pct  = $state['db'] ? 62 : 3;
				$text = __( 'Dosyalar hazırlanıyor…', 'flx-backup' );
				break;
			case 'files':
				$total = max( 1, count( $state['entries'] ) );
				$start = $state['db'] ? 62 : 5;
				$pct   = $start + (int) ( ( 97 - $start ) * $state['epos'] / $total );
				/* translators: 1: done, 2: total */
				$text = sprintf( __( 'Dosyalar geri yükleniyor: %1$s / %2$s', 'flx-backup' ), number_format_i18n( $state['epos'] ), number_format_i18n( count( $state['entries'] ) ) );
				break;
			case 'finalize':
				$pct  = 98;
				$text = __( 'Tamamlanıyor…', 'flx-backup' );
				break;
			case 'done':
				$pct  = 100;
				$text = $state['result'];
				break;
			case 'failed':
				$text = $state['error'];
				break;
		}
		return array(
			'percent' => $pct,
			'text'    => $text,
		);
	}
}
