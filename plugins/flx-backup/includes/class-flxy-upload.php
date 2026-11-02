<?php
/**
 * Dışarıdan yedek yükleme (başka bir siteden veya bilgisayardan Flux Backup zip'i).
 *
 * Tarayıcı dosyayı parçalar halinde gönderir (her parça upload_max_filesize / post_max_size sınırının altında),
 * parçalar depolama klasöründeki geçici bir dosyaya eklenir. Son parçadan sonra zip doğrulanır (açılabiliyor mu,
 * Flux Backup manifest.json'u var mı, yollar güvenli mi, boyutlar makul mü), kota kontrol edilir ve yedek listeye
 * "Yüklendi: <dosya adı>" olarak eklenir. Doğrulanamayan dosya silinir.
 *
 * Saklama: yüklenen yedeğin süresi yoktur ve kilitli değildir; saklama süresi temizliği onu silmez, kotaya sayılır
 * (kota dolarsa diğer kilitsiz yedekler gibi en eskiden silinebilir; kayıt tarihi yükleme anıdır).
 */

defined( 'ABSPATH' ) || exit;

final class FLXY_Upload {

	const CAP = 'manage_options';

	public static function init(): void {
		add_action( 'wp_ajax_flxy_upload_start', array( __CLASS__, 'ajax_start' ) );
		add_action( 'wp_ajax_flxy_upload_chunk', array( __CLASS__, 'ajax_chunk' ) );
		add_action( 'wp_ajax_flxy_upload_finish', array( __CLASS__, 'ajax_finish' ) );
		add_action( 'wp_ajax_flxy_upload_abort', array( __CLASS__, 'ajax_abort' ) );
	}

	/** Bir parçanın boyutu: sunucunun yükleme sınırının biraz altında, en çok 8 MB. */
	public static function chunk_size(): int {
		$max   = (int) wp_max_upload_size();
		$chunk = 8 * MB_IN_BYTES;
		if ( $max > 0 ) {
			$chunk = min( $chunk, $max - 256 * KB_IN_BYTES );
		}
		return (int) apply_filters( 'flxy_upload_chunk_size', max( 256 * KB_IN_BYTES, $chunk ) );
	}

	private static function guard(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Yetkiniz yok.', 'flx-backup' ) ), 403 );
		}
		if ( ! check_ajax_referer( 'flxy', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Oturum doğrulanamadı. Sayfayı yenileyin.', 'flx-backup' ) ), 403 );
		}
	}

	private static function dir_for( string $uid ): string {
		return FLXY_Storage::tmp_dir() . '/upload-' . $uid;
	}

	private static function uid(): string {
		$uid = isset( $_POST['uid'] ) ? sanitize_key( wp_unslash( $_POST['uid'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return preg_match( '/^[a-z0-9]{16}$/', $uid ) ? $uid : '';
	}

	private static function meta( string $uid ): ?array {
		$f = self::dir_for( $uid ) . '/meta.json';
		if ( '' === $uid || ! file_exists( $f ) ) {
			return null;
		}
		$m = json_decode( (string) file_get_contents( $f ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return is_array( $m ) && (int) ( $m['user'] ?? 0 ) === get_current_user_id() ? $m : null;
	}

	/** Yüklemeye başla: ad ve boyut denetlenir, geçici klasör açılır. */
	public static function ajax_start(): void {
		self::guard();
		$name = sanitize_file_name( wp_unslash( $_POST['name'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$size = (int) ( $_POST['size'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' === $name || '.zip' !== strtolower( substr( $name, -4 ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Yalnızca Flux Backup ile alınmış .zip yedek dosyaları yüklenebilir.', 'flx-backup' ) ) );
		}
		if ( $size < 100 ) {
			wp_send_json_error( array( 'message' => __( 'Dosya boş ya da çok küçük.', 'flx-backup' ) ) );
		}
		$quota = FLXY_Settings::quota_bytes();
		if ( $size > $quota ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: 1: file size, 2: quota */
						__( 'Dosya (%1$s) yedek kotasından (%2$s) büyük. Ayarlar\'dan kotayı artırın.', 'flx-backup' ),
						size_format( $size, 1 ),
						size_format( $quota, 1 )
					),
				)
			);
		}
		if ( ! FLXY_Storage::ensure() ) {
			/* translators: %s: directory */
			wp_send_json_error( array( 'message' => sprintf( __( 'Yedek klasörü yazılabilir değil: %s', 'flx-backup' ), FLXY_Storage::dir() ) ) );
		}
		$free = function_exists( 'disk_free_space' ) ? @disk_free_space( FLXY_Storage::dir() ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false !== $free && $free < $size * 1.05 ) {
			wp_send_json_error(
				array(
					/* translators: %s: free disk space */
					'message' => sprintf( __( 'Sunucuda yeterli boş disk alanı yok (boş: %s).', 'flx-backup' ), size_format( (int) $free, 1 ) ),
				)
			);
		}
		$uid = strtolower( wp_generate_password( 16, false, false ) );
		$dir = self::dir_for( $uid );
		if ( ! wp_mkdir_p( $dir ) ) {
			wp_send_json_error( array( 'message' => __( 'Geçici dosya yazılamadı.', 'flx-backup' ) ) );
		}
		file_put_contents( $dir . '/data.part', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions
			$dir . '/meta.json',
			wp_json_encode(
				array(
					'name'    => $name,
					'size'    => $size,
					'user'    => get_current_user_id(),
					'started' => time(),
				)
			)
		);
		wp_send_json_success(
			array(
				'uid'   => $uid,
				'chunk' => self::chunk_size(),
			)
		);
	}

	/** Bir parçayı ekler. offset geçici dosyanın şu anki boyutundan büyük olamaz (tekrar gönderimde kesilir). */
	public static function ajax_chunk(): void {
		self::guard();
		$uid  = self::uid();
		$meta = self::meta( $uid );
		if ( ! $meta ) {
			wp_send_json_error( array( 'message' => __( 'Yükleme bulunamadı; yeniden başlatın.', 'flx-backup' ) ) );
		}
		$offset = (int) ( $_POST['offset'] ?? -1 ); // phpcs:ignore WordPress.Security.NonceVerification
		$file   = $_FILES['chunk'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
		if ( ! $file || ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$code = $file ? (int) $file['error'] : -1;
			/* translators: %d: PHP upload error code */
			wp_send_json_error( array( 'message' => sprintf( __( 'Parça sunucuya ulaşmadı (hata %d).', 'flx-backup' ), $code ) ) );
		}
		$part = self::dir_for( $uid ) . '/data.part';
		clearstatcache( true, $part );
		$have = (int) filesize( $part );
		if ( $offset < 0 || $offset > $have ) {
			wp_send_json_error(
				array(
					'message'  => __( 'Parça sırası bozuldu.', 'flx-backup' ),
					'received' => $have,
				)
			);
		}
		$len = (int) $file['size'];
		if ( $offset + $len > (int) $meta['size'] ) {
			wp_send_json_error( array( 'message' => __( 'Dosya bildirilen boyuttan büyük.', 'flx-backup' ) ) );
		}
		$out = fopen( $part, 'c+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$in  = fopen( $file['tmp_name'], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $out || ! $in ) {
			wp_send_json_error( array( 'message' => __( 'Geçici dosya yazılamadı.', 'flx-backup' ) ) );
		}
		ftruncate( $out, $offset );
		fseek( $out, $offset );
		$copied = stream_copy_to_stream( $in, $out );
		fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		wp_delete_file( $file['tmp_name'] );
		if ( $copied !== $len ) {
			wp_send_json_error( array( 'message' => __( 'Geçici dosya yazılamadı (disk dolu olabilir).', 'flx-backup' ) ) );
		}
		touch( self::dir_for( $uid ) ); // Eski geçici klasör temizliği yarım yüklemeyi silmesin.
		wp_send_json_success( array( 'received' => $offset + $len ) );
	}

	/** Son parçadan sonra: doğrula, kotaya yerleştir, kayıt oluştur. */
	public static function ajax_finish(): void {
		self::guard();
		$uid  = self::uid();
		$meta = self::meta( $uid );
		if ( ! $meta ) {
			wp_send_json_error( array( 'message' => __( 'Yükleme bulunamadı; yeniden başlatın.', 'flx-backup' ) ) );
		}
		FLXY_Backup::raise_limits();
		$rec = self::register( self::dir_for( $uid ) . '/data.part', (string) $meta['name'], (int) $meta['size'] );
		FLXY_Storage::rrmdir( self::dir_for( $uid ) );
		if ( is_wp_error( $rec ) ) {
			wp_send_json_error( array( 'message' => $rec->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				'id'      => $rec['id'],
				'title'   => FLXY_Storage::title( $rec ),
				'size_h'  => size_format( $rec['size'], 1 ),
				'url'     => admin_url( 'admin.php?page=flx-backup-list&view=' . rawurlencode( $rec['id'] ) ),
				'message' => sprintf(
					/* translators: 1: file name, 2: size */
					__( '%1$s yüklendi (%2$s) ve yedek listesine eklendi.', 'flx-backup' ),
					$meta['name'],
					size_format( $rec['size'], 1 )
				),
			)
		);
	}

	public static function ajax_abort(): void {
		self::guard();
		$uid = self::uid();
		if ( self::meta( $uid ) ) {
			FLXY_Storage::rrmdir( self::dir_for( $uid ) );
		}
		wp_send_json_success();
	}

	/**
	 * Geçici dosyayı doğrular ve yedek olarak kaydeder. Başarısızsa dosyaya dokunmaz (çağıran siler).
	 *
	 * @return array|WP_Error Kayıt.
	 */
	public static function register( string $path, string $orig_name, int $expect_size = 0 ) {
		clearstatcache( true, $path );
		$size = file_exists( $path ) ? (int) filesize( $path ) : 0;
		if ( $expect_size && $size !== $expect_size ) {
			return new WP_Error(
				'flxy_upload_size',
				sprintf(
					/* translators: 1: received bytes, 2: expected bytes */
					__( 'Dosya eksik yüklendi (%1$s / %2$s). Yeniden deneyin.', 'flx-backup' ),
					size_format( $size, 1 ),
					size_format( $expect_size, 1 )
				)
			);
		}
		$m = self::validate( $path );
		if ( is_wp_error( $m ) ) {
			return $m;
		}
		$room = FLXY_Storage::make_room( $size );
		if ( is_wp_error( $room ) ) {
			return $room;
		}

		$type = in_array( $m['type'] ?? '', FLXY_Backup::TYPES, true ) ? $m['type'] : 'manual';
		$now  = time();
		$dest = FLXY_Storage::dir() . '/flxy-upload-' . gmdate( 'Ymd-His', $now ) . '-' . strtolower( wp_generate_password( 16, false, false ) ) . '.zip';
		if ( ! @rename( $path, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
			if ( ! @copy( $path, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				return new WP_Error( 'flxy_upload_move', __( 'Yedek klasörüne taşınamadı.', 'flx-backup' ) );
			}
			wp_delete_file( $path );
		}

		$id = sanitize_text_field( (string) $m['id'] );
		if ( '' === $id || ! preg_match( '/^[A-Za-z0-9._-]{1,64}$/', $id ) || FLXY_Storage::get( $id ) ) {
			$id = gmdate( 'Ymd-His', $now ) . '-' . strtolower( wp_generate_password( 4, false, false ) );
		}
		$mctx    = isset( $m['context'] ) && is_array( $m['context'] ) ? $m['context'] : array();
		$context = array(
			'source'            => 'upload',
			'user'              => get_current_user_id(),
			'original_name'     => $orig_name,
			'original_site_url' => esc_url_raw( (string) ( $m['home_url'] ?? $m['site_url'] ?? '' ) ),
			'original_created'  => (int) ( $m['created'] ?? 0 ),
			'original_label'    => sanitize_text_field( (string) ( $m['label'] ?? '' ) ),
			'original_source'   => sanitize_key( (string) ( $mctx['source'] ?? '' ) ),
			'original_prefix'   => sanitize_text_field( (string) ( $m['db']['prefix'] ?? '' ) ),
			'original_version'  => sanitize_text_field( (string) ( $m['version'] ?? '' ) ),
		);
		if ( ! empty( $mctx['packages'] ) && is_array( $mctx['packages'] ) ) {
			$context['packages'] = $mctx['packages'];
		}
		$rec = FLXY_Storage::put(
			array(
				'id'         => $id,
				'type'       => $type,
				/* translators: %s: original file name */
				'label'      => sprintf( __( 'Yüklendi: %s', 'flx-backup' ), $orig_name ),
				'status'     => 'ok',
				'created'    => $now,
				'finished'   => $now,
				'size'       => (int) filesize( $dest ),
				'db'         => ! empty( $m['db']['included'] ),
				'paths'      => array_values( array_filter( array_map( 'strval', (array) ( $m['paths'] ?? array() ) ), array( __CLASS__, 'safe_rel' ) ) ),
				'items'      => array_values( array_filter( array_map( 'strval', (array) ( $m['contents'] ?? array() ) ), array( __CLASS__, 'safe_rel' ) ) ),
				'files'      => (int) ( $m['sizes']['files_count'] ?? 0 ),
				'context'    => $context,
				'keep_until' => null,
				'locked'     => false,
				'file'       => $dest,
				'wp_version' => sanitize_text_field( (string) ( $m['wp_version'] ?? '' ) ),
			)
		);
		$b = FLXY_Breakdown::compute( $dest, null );
		if ( $b ) {
			$rec = FLXY_Storage::patch( $id, array( 'breakdown' => $b ) );
		}
		FLXY_Storage::log(
			'upload',
			sprintf(
				/* translators: 1: file name, 2: size, 3: site address */
				__( 'Yedek yüklendi: %1$s (%2$s, %3$s)', 'flx-backup' ),
				$orig_name,
				size_format( $rec['size'], 1 ),
				'' !== $context['original_site_url'] ? $context['original_site_url'] : '?'
			)
		);
		FLXY_Storage::sample();
		/**
		 * Dışarıdan bir yedek yüklendi.
		 *
		 * @param array $record
		 */
		do_action( 'flxy_backup_uploaded', $rec );
		return $rec;
	}

	public static function safe_rel( string $rel ): bool {
		return '' !== $rel && '/' !== $rel[0] && false === strpos( $rel, '\\' ) && false === strpos( '/' . $rel . '/', '/../' ) && false === strpos( $rel, ':' );
	}

	/**
	 * Zip'in bir Flux Backup yedeği olduğunu doğrular.
	 *
	 * @return array|WP_Error manifest
	 */
	public static function validate( string $path ) {
		$bad = new WP_Error( 'flxy_upload_invalid', __( 'Bu dosya bir Flux Backup yedeği değil (zip açılamadı ya da manifest.json yok).', 'flx-backup' ) );
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'flxy_zip', __( 'Sunucuda ZipArchive (PHP zip eklentisi) yok.', 'flx-backup' ) );
		}
		$fh   = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$head = $fh ? (string) fread( $fh, 4 ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( $fh ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		if ( "PK\x03\x04" !== $head ) {
			return $bad;
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return $bad;
		}
		$json = $zip->getFromName( 'manifest.json' );
		$m    = is_string( $json ) && strlen( $json ) < 4 * MB_IN_BYTES ? json_decode( $json, true ) : null;
		if ( ! is_array( $m ) || 'flx-backup' !== ( $m['plugin'] ?? '' ) || empty( $m['id'] ) ) {
			$zip->close();
			return $bad;
		}
		$size  = (int) filesize( $path );
		$raw   = 0;
		$has_db = false;
		$n     = $zip->numFiles;
		for ( $i = 0; $i < $n; $i++ ) {
			$st = $zip->statIndex( $i );
			if ( ! $st ) {
				$zip->close();
				return $bad;
			}
			$name = (string) $st['name'];
			if ( 'manifest.json' === $name ) {
				continue;
			}
			if ( 'database.sql.gz' === $name ) {
				$has_db = true;
			} elseif ( 0 !== strpos( $name, 'files/' ) || ! self::safe_rel( substr( $name, 6 ) ) ) {
				$zip->close();
				return new WP_Error(
					'flxy_upload_path',
					/* translators: %s: entry name */
					sprintf( __( 'Yedekte beklenmeyen veya güvenli olmayan bir yol var: %s', 'flx-backup' ), $name )
				);
			}
			$raw += (int) $st['size'];
		}
		$zip->close();
		if ( ! empty( $m['db']['included'] ) && ! $has_db ) {
			return new WP_Error( 'flxy_upload_nodb', __( 'Manifest veritabanı içerdiğini söylüyor ama database.sql.gz yok; dosya bozuk olabilir.', 'flx-backup' ) );
		}
		if ( ! $has_db && $n <= 1 ) {
			return new WP_Error( 'flxy_upload_empty', __( 'Yedek boş görünüyor.', 'flx-backup' ) );
		}
		/** Açılmış boyutun zip boyutuna en fazla oranı (sıkıştırma bombasına karşı). */
		$ratio = (float) apply_filters( 'flxy_upload_max_ratio', 200 );
		if ( $raw > max( 50 * MB_IN_BYTES, $size * $ratio ) ) {
			return new WP_Error( 'flxy_upload_ratio', __( 'Yedeğin açılmış boyutu makul değil; dosya yüklenmedi.', 'flx-backup' ) );
		}
		return $m;
	}
}
