<?php
/**
 * Append-only, resumable zip writer.
 *
 * ZipArchive rewrites the whole archive on every close(); on a 1 GB archive a single step then takes longer than
 * the host allows (LiteSpeed / ~60 s PHP limit) and the job loops forever. This writer only appends:
 * local headers + data go to the zip, central directory entries go to a side file, and the central directory is
 * written once at the end. The committed byte length of both files lives in the job state; every step first
 * truncates both files back to the committed length, so a step that was killed half way leaves no trace.
 *
 * ZIP64 end records are written when needed (more than 65535 entries or offsets above 4 GB). Single files of
 * 4 GB or more are not supported and are skipped by the caller.
 */

defined( 'ABSPATH' ) || exit;

final class FLXY_Zip_Writer {

	const MAX32 = 0xFFFFFFFF;
	const CHUNK = 1048576;

	/** Files larger than this are stored (not deflated) so one file always fits in a step. */
	const DEFLATE_MAX = 67108864;

	/** @var resource */
	private $zh;
	/** @var resource */
	private $ch;
	/** @var array{size:int,cd_size:int,n:int} */
	public $st;

	/**
	 * @param array|null $st Committed state (size, cd_size, n) or null for a new archive.
	 * @return self|WP_Error
	 */
	public static function open( string $zip, string $cd, ?array $st ) {
		$st = is_array( $st ) ? $st : array(
			'size'    => 0,
			'cd_size' => 0,
			'n'       => 0,
		);
		$zh = @fopen( $zip, 'c+b' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
		$ch = $zh ? @fopen( $cd, 'c+b' ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
		if ( ! $zh || ! $ch ) {
			if ( $zh ) {
				fclose( $zh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
			return new WP_Error( 'flxy_zip', __( 'Could not open the zip file.', 'flx-backup' ) );
		}
		// Drop anything a killed step wrote after the last committed point.
		ftruncate( $zh, (int) $st['size'] );
		ftruncate( $ch, (int) $st['cd_size'] );
		fseek( $zh, 0, SEEK_END );
		fseek( $ch, 0, SEEK_END );
		$w     = new self();
		$w->zh = $zh;
		$w->ch = $ch;
		$w->st = array(
			'size'    => (int) $st['size'],
			'cd_size' => (int) $st['cd_size'],
			'n'       => (int) $st['n'],
		);
		return $w;
	}

	public function close(): void {
		if ( $this->zh ) {
			fflush( $this->zh );
			fclose( $this->zh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$this->zh = null;
		}
		if ( $this->ch ) {
			fflush( $this->ch );
			fclose( $this->ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$this->ch = null;
		}
	}

	private static function dos_time( int $ts ): array {
		$d = getdate( $ts > 315532800 ? $ts : 315532800 );
		return array(
			( $d['hours'] << 11 ) | ( $d['minutes'] << 5 ) | ( $d['seconds'] >> 1 ),
			( ( $d['year'] - 1980 ) << 9 ) | ( $d['mon'] << 5 ) | $d['mday'],
		);
	}

	/** Rolls the archive back to the end of the last complete entry. */
	private function rollback(): void {
		ftruncate( $this->zh, $this->st['size'] );
		fseek( $this->zh, 0, SEEK_END );
	}

	/**
	 * Adds a file from disk.
	 *
	 * @return int|false Bytes read from the source, false when the file could not be read (nothing written).
	 */
	public function add_file( string $abs, string $name, bool $store ) {
		$in = @fopen( $abs, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
		if ( ! $in ) {
			return false;
		}
		$mtime = (int) @filemtime( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$size  = (int) @filesize( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( $size >= self::MAX32 ) {
			fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return false;
		}
		$store = $store || $size > self::DEFLATE_MAX || ! function_exists( 'deflate_init' );
		$ok    = $this->write_entry(
			$name,
			$mtime,
			$store,
			function () use ( $in ) {
				$buf = fread( $in, self::CHUNK ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				return ( false === $buf || '' === $buf ) ? null : $buf;
			}
		);
		fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $ok;
	}

	/** Adds an entry from a string. */
	public function add_string( string $name, string $data, bool $store = false ): bool {
		$done = false;
		return false !== $this->write_entry(
			$name,
			time(),
			$store || ! function_exists( 'deflate_init' ),
			function () use ( $data, &$done ) {
				if ( $done ) {
					return null;
				}
				$done = true;
				return $data;
			}
		);
	}

	/**
	 * @param callable $read Returns the next chunk or null at the end.
	 * @return int|false Uncompressed size.
	 */
	private function write_entry( string $name, int $mtime, bool $store, callable $read ) {
		$offset           = $this->st['size'];
		list( $tm, $dt ) = self::dos_time( $mtime );
		$method           = $store ? 0 : 8;
		$flags            = 0x0800; // UTF-8 names.
		$hdr              = pack( 'VvvvvvVVVvv', 0x04034b50, 20, $flags, $method, $tm, $dt, 0, 0, 0, strlen( $name ), 0 ) . $name;
		if ( false === fwrite( $this->zh, $hdr ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			$this->rollback();
			return false;
		}
		$crc   = hash_init( 'crc32b' );
		$def   = $store ? null : deflate_init( ZLIB_ENCODING_RAW, array( 'level' => 6 ) );
		$usize = 0;
		$csize = 0;
		while ( null !== ( $buf = $read() ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
			$usize += strlen( $buf );
			hash_update( $crc, $buf );
			$out = $store ? $buf : deflate_add( $def, $buf, ZLIB_NO_FLUSH );
			if ( '' !== $out ) {
				if ( false === fwrite( $this->zh, $out ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
					$this->rollback();
					return false;
				}
				$csize += strlen( $out );
			}
			if ( $usize >= self::MAX32 ) {
				$this->rollback();
				return false;
			}
		}
		if ( ! $store ) {
			$out = deflate_add( $def, '', ZLIB_FINISH );
			if ( '' !== $out && false === fwrite( $this->zh, $out ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
				$this->rollback();
				return false;
			}
			$csize += strlen( $out );
		}
		if ( $csize >= self::MAX32 ) {
			$this->rollback();
			return false;
		}
		$crc_v = unpack( 'N', hash_final( $crc, true ) )[1];
		$end   = $offset + strlen( $hdr ) + $csize;
		// Patch CRC and sizes into the local header.
		fseek( $this->zh, $offset + 14 );
		fwrite( $this->zh, pack( 'VVV', $crc_v, $csize, $usize ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fseek( $this->zh, 0, SEEK_END );
		if ( ftell( $this->zh ) !== $end ) {
			$this->rollback();
			return false;
		}

		$zip64 = $offset >= self::MAX32;
		$extra = $zip64 ? pack( 'vvP', 0x0001, 8, $offset ) : '';
		$cd    = pack(
			'VvvvvvvVVVvvvvvVV',
			0x02014b50,
			( 3 << 8 ) | 45,
			$zip64 ? 45 : 20,
			$flags,
			$method,
			$tm,
			$dt,
			$crc_v,
			$csize,
			$usize,
			strlen( $name ),
			strlen( $extra ),
			0,
			0,
			0,
			( 0100644 << 16 ),
			$zip64 ? self::MAX32 : $offset
		) . $name . $extra;
		if ( false === fwrite( $this->ch, $cd ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			ftruncate( $this->ch, $this->st['cd_size'] );
			fseek( $this->ch, 0, SEEK_END );
			$this->rollback();
			return false;
		}
		$this->st['size']     = $end;
		$this->st['cd_size'] += strlen( $cd );
		++$this->st['n'];
		return $usize;
	}

	/** Appends the central directory and end records. The archive is complete afterwards. */
	public function finish(): bool {
		fflush( $this->ch );
		rewind( $this->ch );
		$cd_offset = $this->st['size'];
		$copied    = stream_copy_to_stream( $this->ch, $this->zh );
		if ( (int) $copied !== $this->st['cd_size'] ) {
			$this->rollback();
			return false;
		}
		$cd_size = $this->st['cd_size'];
		$n       = $this->st['n'];
		$end     = '';
		if ( $n >= 0xFFFF || $cd_offset >= self::MAX32 || $cd_size >= self::MAX32 ) {
			$z64  = $cd_offset + $cd_size;
			$end .= pack( 'VPvvVVPPPP', 0x06064b50, 44, ( 3 << 8 ) | 45, 45, 0, 0, $n, $n, $cd_size, $cd_offset );
			$end .= pack( 'VVPV', 0x07064b50, 0, $z64, 1 );
			$end .= pack( 'VvvvvVVv', 0x06054b50, 0, 0, 0xFFFF, 0xFFFF, self::MAX32, self::MAX32, 0 );
		} else {
			$end .= pack( 'VvvvvVVv', 0x06054b50, 0, 0, $n, $n, $cd_size, $cd_offset, 0 );
		}
		if ( false === fwrite( $this->zh, $end ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			$this->rollback();
			return false;
		}
		$this->st['size'] = $cd_offset + $cd_size + strlen( $end );
		return true;
	}
}
