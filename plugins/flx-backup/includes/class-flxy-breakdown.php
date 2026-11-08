<?php
/**
 * Bir yedeğin boyut dökümü: kategori (veritabanı, çekirdek, eklentiler, temalar, medya, diğer) ve kategori
 * içindeki en büyük öğeler (eklenti/tema klasörü, uploads yılı, tablo).
 *
 * Zip'in merkezi dizininden (statIndex: ad, özgün boyut, arşivdeki boyut) hesaplanır; tablolar için yedekleme
 * sırasında tutulan SQL bayt + satır istatistiği kullanılır. Eski yedeklerde istatistik yoksa database.sql.gz
 * bir kez okunup tablo başına bayt ve (yaklaşık) satır sayısı çıkarılır. Sonuç kayıtta `breakdown` alanında durur.
 *
 * Biçim:
 *   v: sürüm, size: zip boyutu, raw: özgün toplam, zip: arşivdeki toplam,
 *   cats: { key: { raw, zip, files, items: [ {n, raw, zip, c} ] , more: {raw, zip, c, k} } }
 *   (db kategorisinde c = satır sayısı, diğerlerinde dosya sayısı; more = ilk N dışında kalanlar, k = kaç öğe).
 */

defined( 'ABSPATH' ) || exit;

final class FLXY_Breakdown {

	const VERSION = 1;
	const TOP     = 15;
	const CATS    = array( 'db', 'core', 'plugins', 'themes', 'uploads', 'other' );

	public static function labels(): array {
		return array(
			'db'      => __( 'Database', 'flx-backup' ),
			'core'    => __( 'WordPress core', 'flx-backup' ),
			'plugins' => __( 'Plugins', 'flx-backup' ),
			'themes'  => __( 'Themes', 'flx-backup' ),
			'uploads' => __( 'Media (uploads)', 'flx-backup' ),
			'other'   => __( 'Other wp-content', 'flx-backup' ),
		);
	}

	public static function colors(): array {
		return array(
			'db'      => '#2a78d6',
			'core'    => '#6250d6',
			'plugins' => '#eb6834',
			'themes'  => '#1baf7a',
			'uploads' => '#eda100',
			'other'   => '#e87ba4',
		);
	}

	/**
	 * Kayıt için dökümü döndürür; yoksa hesaplayıp kayda yazar.
	 */
	public static function for_record( array $rec ): ?array {
		if ( isset( $rec['breakdown']['v'] ) && (int) $rec['breakdown']['v'] === self::VERSION && (int) ( $rec['breakdown']['size'] ?? 0 ) === (int) $rec['size'] ) {
			return $rec['breakdown'];
		}
		if ( 'ok' !== $rec['status'] || empty( $rec['file'] ) || ! is_readable( $rec['file'] ) ) {
			return null;
		}
		$b = self::compute( $rec['file'], null );
		if ( $b ) {
			FLXY_Storage::patch( $rec['id'], array( 'breakdown' => $b ) );
		}
		return $b;
	}

	/**
	 * @param string     $zip_path
	 * @param array|null $stats Tablo => [sql_bayt, satır]. null ise database.sql.gz okunur.
	 * @param string     $content_rel wp-content klasörünün ABSPATH'e göre yolu (yüklenen yedekte manifest'ten).
	 */
	public static function compute( string $zip_path, ?array $stats, string $content_rel = '' ): ?array {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return null;
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			return null;
		}
		if ( '' === $content_rel ) {
			$content_rel = FLXY_Backup::content_rel();
		}
		$content = trim( $content_rel, '/' ) . '/';
		$clen    = strlen( $content );
		$acc     = array();
		foreach ( self::CATS as $c ) {
			$acc[ $c ] = array();
		}
		$db_zip = 0;
		$db_gz  = 0;
		$n      = $zip->numFiles;
		for ( $i = 0; $i < $n; $i++ ) {
			$st = $zip->statIndex( $i );
			if ( ! $st ) {
				continue;
			}
			$name = (string) $st['name'];
			if ( 'database.sql.gz' === $name ) {
				$db_zip = (int) $st['comp_size'];
				$db_gz  = (int) $st['size'];
				continue;
			}
			if ( 0 !== strpos( $name, 'files/' ) || '/' === substr( $name, -1 ) ) {
				continue;
			}
			$rel = substr( $name, 6 );
			list( $cat, $item ) = self::classify( $rel, $content, $clen );
			if ( ! isset( $acc[ $cat ][ $item ] ) ) {
				$acc[ $cat ][ $item ] = array( 0, 0, 0 );
			}
			$acc[ $cat ][ $item ][0] += (int) $st['size'];
			$acc[ $cat ][ $item ][1] += (int) $st['comp_size'];
			++$acc[ $cat ][ $item ][2];
		}
		$size = (int) @filesize( $zip_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		if ( $db_gz > 0 ) {
			if ( null === $stats ) {
				$stats = self::scan_dump( $zip );
			}
			$sum_sql = 0;
			foreach ( (array) $stats as $t ) {
				$sum_sql += (int) $t[0];
			}
			foreach ( (array) $stats as $table => $t ) {
				$share                  = $sum_sql > 0 ? $t[0] / $sum_sql : 0;
				$acc['db'][ (string) $table ] = array( (int) $t[0], (int) round( $db_zip * $share ), (int) $t[1] );
			}
			if ( ! $stats ) {
				$acc['db'][ 'database.sql.gz' ] = array( $db_gz, $db_zip, 0 );
			}
		}
		$zip->close();

		$out = array(
			'v'    => self::VERSION,
			'size' => $size,
			'raw'  => 0,
			'zip'  => 0,
			'cats' => array(),
		);
		foreach ( $acc as $cat => $items ) {
			if ( ! $items ) {
				continue;
			}
			uasort(
				$items,
				static function ( $a, $b ) {
					return $b[0] <=> $a[0];
				}
			);
			$c = array(
				'raw'   => 0,
				'zip'   => 0,
				'files' => 0,
				'items' => array(),
				'more'  => null,
			);
			$i = 0;
			foreach ( $items as $name => $v ) {
				$c['raw'] += $v[0];
				$c['zip'] += $v[1];
				$c['files'] += $v[2]; // db: satır sayısı.
				if ( $i < self::TOP ) {
					$c['items'][] = array(
						'n'   => (string) $name,
						'raw' => $v[0],
						'zip' => $v[1],
						'c'   => $v[2],
					);
				} else {
					if ( ! $c['more'] ) {
						$c['more'] = array(
							'raw' => 0,
							'zip' => 0,
							'c'   => 0,
							'k'   => 0,
						);
					}
					$c['more']['raw'] += $v[0];
					$c['more']['zip'] += $v[1];
					$c['more']['c']   += $v[2];
					++$c['more']['k'];
				}
				++$i;
			}
			$out['raw']         += $c['raw'];
			$out['zip']         += $c['zip'];
			$out['cats'][ $cat ] = $c;
		}
		return $out;
	}

	/** @return array{0:string,1:string} kategori, öğe */
	private static function classify( string $rel, string $content, int $clen ): array {
		if ( 0 === strpos( $rel, $content ) ) {
			$sub   = substr( $rel, $clen );
			$parts = explode( '/', $sub );
			$first = $parts[0];
			if ( ( 'plugins' === $first || 'themes' === $first ) && count( $parts ) > 1 ) {
				return array( $first, $parts[1] );
			}
			if ( 'uploads' === $first ) {
				if ( count( $parts ) < 3 ) {
					return array( 'uploads', '/' );
				}
				return array( 'uploads', $parts[1] );
			}
			return array( 'other', count( $parts ) > 1 ? $first : '/' );
		}
		$parts = explode( '/', $rel );
		if ( count( $parts ) < 2 ) {
			return array( 'core', '/' );
		}
		return array( 'core', $parts[0] );
	}

	/**
	 * Eski yedekler için: database.sql.gz'yi okuyup tablo başına SQL baytı ve yaklaşık satır sayısı.
	 * Satır sayısı INSERT içindeki "),(" ayraçlarından sayılır (değer içinde aynı dizi geçerse fazla sayabilir).
	 */
	private static function scan_dump( ZipArchive $zip ): array {
		$tmp = wp_tempnam( 'flxy-bd' );
		$in  = $zip->getStream( 'database.sql.gz' );
		if ( ! $in || ! $tmp ) {
			return array();
		}
		$out = fopen( $tmp, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		stream_copy_to_stream( $in, $out );
		fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$stats = array();
		$gz    = gzopen( $tmp, 'rb' );
		$cur   = '';
		$limit = microtime( true ) + 20;
		while ( $gz && ! gzeof( $gz ) ) {
			$line = gzgets( $gz, 8 * MB_IN_BYTES );
			if ( false === $line ) {
				break;
			}
			if ( 0 === strncmp( $line, 'DROP TABLE', 10 ) || 0 === strncmp( $line, 'CREATE TABLE', 12 ) || 0 === strncmp( $line, 'INSERT INTO', 11 ) ) {
				if ( preg_match( '/^(?:DROP TABLE IF EXISTS|CREATE TABLE(?: IF NOT EXISTS)?|INSERT INTO)\s+`((?:[^`]|``)+)`/', $line, $m ) ) {
					$cur = str_replace( '``', '`', $m[1] );
				}
			}
			if ( '' === $cur ) {
				continue;
			}
			if ( ! isset( $stats[ $cur ] ) ) {
				$stats[ $cur ] = array( 0, 0 );
			}
			$stats[ $cur ][0] += strlen( $line );
			if ( 0 === strncmp( $line, 'INSERT INTO', 11 ) ) {
				$stats[ $cur ][1] += 1 + substr_count( $line, '),(' );
			}
			if ( microtime( true ) > $limit ) {
				break;
			}
		}
		if ( $gz ) {
			gzclose( $gz );
		}
		wp_delete_file( $tmp );
		return $stats;
	}
}
