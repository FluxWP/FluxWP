<?php
/**
 * Veritabanı dökümü ve geri yükleme: yalnız $wpdb, adım adım ve kaldığı yerden devam eden.
 *
 * Döküm biçimi (database.sql.gz): her INSERT tek satırdır (satır sonu ve özel karakterler kaçışlıdır), her ifade
 * ";\n" ile biter. CREATE TABLE birden çok satır olabilir; okuyucu satırları ";" ile biten ve tırnak dengesi
 * kapalı olana kadar birleştirir. Böylece geri yükleme bayt konumuyla kesilip devam edebilir.
 *
 * MySQL/MariaDB (Hostinger) ve SQLite (sqlite-database-integration) ile çalışır: SQLite çevirmeni SHOW CREATE
 * TABLE'ı tek sütunla ve ters tırnaksız tablo adıyla destekler; ikili veriyi 0x… olarak değil metin olarak alır.
 */

defined( 'ABSPATH' ) || exit;

final class FLXY_DB {

	const BATCH_ROWS      = 500;
	const MAX_INSERT_SIZE = 1048576; // 1 MB: bir INSERT ifadesinin üst sınırı (max_allowed_packet'in çok altında).

	public static function is_sqlite(): bool {
		global $wpdb;
		return ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) || ( defined( 'DATABASE_TYPE' ) && 'sqlite' === DATABASE_TYPE ) || ( is_object( $wpdb ) && false !== stripos( get_class( $wpdb ), 'sqlite' ) );
	}

	/** Bu sitenin tabloları (önek ile başlayan). */
	public static function tables(): array {
		global $wpdb;
		$prefix = $wpdb->base_prefix;
		if ( self::is_sqlite() ) {
			$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $prefix ) . '%' ) );
		} else {
			// Görünümler (VIEW) atlanır: SHOW CREATE TABLE farklı sütunlar döndürür, tanımlayıcı/izin sorunu çıkarır.
			$tables = array();
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SHOW FULL TABLES LIKE %s', $wpdb->esc_like( $prefix ) . '%' ), ARRAY_N ) as $row ) {
				if ( ! isset( $row[1] ) || 'BASE TABLE' === strtoupper( (string) $row[1] ) ) {
					$tables[] = $row[0];
				}
			}
		}
		$tables = array_values( array_filter( array_map( 'strval', (array) $tables ) ) );
		sort( $tables );
		// Seçenekler tablosu önce: geri yüklemede siteurl/active_plugins en erken yerine konur.
		$opt = $wpdb->base_prefix . 'options';
		if ( in_array( $opt, $tables, true ) ) {
			$tables = array_merge( array( $opt ), array_values( array_diff( $tables, array( $opt ) ) ) );
		}
		/**
		 * Yedeklenecek tablolar.
		 *
		 * @param string[] $tables
		 */
		return array_values( (array) apply_filters( 'flxy_db_tables', $tables ) );
	}

	/** Tahmini ham boyut (bayt). */
	public static function estimate_size(): int {
		global $wpdb;
		if ( self::is_sqlite() ) {
			$file = defined( 'FQDB' ) ? FQDB : ( defined( 'DB_DIR' ) && defined( 'DB_FILE' ) ? DB_DIR . DB_FILE : '' );
			return $file && file_exists( $file ) ? (int) filesize( $file ) : 0;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $wpdb->base_prefix ) . '%' ), ARRAY_A );
		$sum  = 0;
		foreach ( (array) $rows as $r ) {
			$sum += (int) ( $r['Data_length'] ?? 0 ) + (int) ( $r['Index_length'] ?? 0 );
		}
		return $sum;
	}

	public static function ident( string $name ): string {
		return '`' . str_replace( '`', '``', $name ) . '`';
	}

	/** SHOW ... komutlarında tablo adı: SQLite çevirmeni ters tırnağı anlamaz. */
	private static function show_ident( string $name ): string {
		return preg_match( '/^[A-Za-z0-9_$]+$/', $name ) ? $name : self::ident( $name );
	}

	/** mysql_real_escape_string ile aynı kaçış (utf8/utf8mb4 için güvenli, % işaretine dokunmaz). */
	public static function quote( string $value ): string {
		return "'" . strtr(
			$value,
			array(
				'\\'   => '\\\\',
				"\0"   => '\\0',
				"\n"   => '\\n',
				"\r"   => '\\r',
				"'"    => "\\'",
				'"'    => '\\"',
				"\x1a" => '\\Z',
			)
		) . "'";
	}

	/* ------------------------------------------------------------------ */
	/* Döküm                                                               */
	/* ------------------------------------------------------------------ */

	public static function dump_init(): array {
		global $wpdb;
		$tables = self::tables();
		$total  = 0;
		if ( ! self::is_sqlite() ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $wpdb->base_prefix ) . '%' ), ARRAY_A );
			foreach ( (array) $rows as $r ) {
				if ( in_array( $r['Name'], $tables, true ) ) {
					$total += (int) $r['Rows'];
				}
			}
		} else {
			foreach ( $tables as $t ) {
				$total += (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::ident( $t ) ); // phpcs:ignore WordPress.DB.PreparedSQL
			}
		}
		return array(
			'tables'     => $tables,
			'ti'         => 0,
			'table'      => null,
			'rows_done'  => 0,
			'rows_total' => max( 1, $total ),
			'header'     => false,
			'done'       => false,
			'batch'      => self::batch_rows(),
		);
	}

	private static function batch_rows(): int {
		$limit = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		if ( $limit > 0 && $limit < 128 * 1024 * 1024 ) {
			return 200;
		}
		return (int) apply_filters( 'flxy_db_batch_rows', self::BATCH_ROWS );
	}

	/**
	 * Dökümü $deadline'a kadar ilerletir, gz dosyasına ekler.
	 *
	 * @return true|WP_Error
	 */
	public static function dump_step( array &$st, string $gz_path, float $deadline ) {
		global $wpdb;
		$gz = gzopen( $gz_path, 'ab6' );
		if ( ! $gz ) {
			return new WP_Error( 'flxy_gz', __( 'Could not write the SQL file.', 'flx-backup' ) );
		}
		if ( ! $st['header'] ) {
			$head  = '-- Flux Backup ' . FLXY_VERSION . " SQL\n";
			$head .= '-- ' . home_url() . ' ' . gmdate( 'Y-m-d H:i:s' ) . " UTC\n";
			$head .= '-- prefix: ' . $wpdb->base_prefix . "\n";
			$head .= "/*!40101 SET NAMES utf8mb4 */;\n";
			$head .= "/*!40014 SET FOREIGN_KEY_CHECKS=0 */;\n";
			$head .= "/*!40101 SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n\n";
			gzwrite( $gz, $head );
			$st['header'] = true;
		}
		$suppress = $wpdb->suppress_errors( true );
		$error    = null;
		while ( $st['ti'] < count( $st['tables'] ) && microtime( true ) < $deadline ) {
			$name = $st['tables'][ $st['ti'] ];
			if ( null === $st['table'] ) {
				$t = self::table_start( $name );
				if ( is_wp_error( $t ) ) {
					$error = $t;
					break;
				}
				gzwrite( $gz, $t['sql'] );
				self::stat( $st, $name, strlen( $t['sql'] ), 0 );
				unset( $t['sql'] );
				$st['table'] = $t;
			}
			$t    = &$st['table'];
			$rows = self::fetch_rows( $name, $t, (int) $st['batch'] );
			if ( null === $rows ) {
				$error = new WP_Error(
					'flxy_db_read',
					/* translators: 1: table, 2: database error */
					sprintf( __( 'Could not read table %1$s: %2$s', 'flx-backup' ), $name, $wpdb->last_error )
				);
				break;
			}
			if ( ! $rows ) {
				gzwrite( $gz, "\n" );
				unset( $t );
				$st['table'] = null;
				++$st['ti'];
				continue;
			}
			$sql = self::insert_sql( $name, $rows, $t );
			gzwrite( $gz, $sql );
			self::stat( $st, $name, strlen( $sql ), count( $rows ) );
			unset( $sql );
			$st['rows_done'] += count( $rows );
			if ( $t['int_pk'] ) {
				$last        = end( $rows );
				$t['last']   = $last[ $t['pk_index'] ];
			} else {
				$t['offset'] += count( $rows );
			}
			if ( count( $rows ) < (int) $st['batch'] ) {
				gzwrite( $gz, "\n" );
				unset( $t );
				$st['table'] = null;
				++$st['ti'];
				continue;
			}
			unset( $t );
		}
		$wpdb->suppress_errors( $suppress );
		gzclose( $gz );
		if ( $error ) {
			return $error;
		}
		if ( $st['ti'] >= count( $st['tables'] ) ) {
			$st['done'] = true;
		}
		return true;
	}

	/** Tablo başına döküm istatistiği (SQL bayt + satır), ayrıntı görünümü için. */
	private static function stat( array &$st, string $name, int $bytes, int $rows ): void {
		if ( ! isset( $st['stats'][ $name ] ) ) {
			$st['stats'][ $name ] = array( 0, 0 );
		}
		$st['stats'][ $name ][0] += $bytes;
		$st['stats'][ $name ][1] += $rows;
	}

	/** Tablonun DROP/CREATE ifadeleri ve sütun bilgisi. */
	private static function table_start( string $name ) {
		global $wpdb;
		$row = $wpdb->get_row( 'SHOW CREATE TABLE ' . self::show_ident( $name ), ARRAY_N ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( ! $row ) {
			return new WP_Error(
				'flxy_db_create',
				/* translators: 1: table, 2: database error */
				sprintf( __( 'Could not get the structure of table %1$s: %2$s', 'flx-backup' ), $name, $wpdb->last_error )
			);
		}
		// MySQL: [Table, Create Table]; SQLite çevirmeni: [Create Table].
		$create = rtrim( trim( (string) ( isset( $row[1] ) ? $row[1] : $row[0] ) ), ';' );
		if ( 0 !== stripos( $create, 'CREATE TABLE' ) ) {
			$create = ''; // Görünüm veya beklenmeyen yanıt: atla.
		}

		$cols    = $wpdb->get_results( 'SHOW COLUMNS FROM ' . self::show_ident( $name ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$names   = array();
		$types   = array();
		$kinds   = array();
		$pk      = array();
		$sqlite  = self::is_sqlite();
		foreach ( (array) $cols as $c ) {
			if ( false !== stripos( (string) ( $c['Extra'] ?? '' ), 'GENERATED' ) ) {
				continue; // Üretilen sütunlar INSERT'e yazılamaz; MySQL yeniden hesaplar.
			}
			$type    = strtolower( (string) $c['Type'] );
			$names[] = $c['Field'];
			$types[] = $type;
			if ( ! $sqlite && preg_match( '/(blob|binary|bit\()/', $type ) ) {
				$kinds[] = 'bin';
			} elseif ( preg_match( '/^(tinyint|smallint|mediumint|int|integer|bigint|decimal|float|double|real|numeric)/', $type ) ) {
				$kinds[] = 'num';
			} else {
				$kinds[] = 'str';
			}
			if ( 'PRI' === ( $c['Key'] ?? '' ) ) {
				$pk[] = count( $names ) - 1;
			}
		}
		$int_pk = 1 === count( $pk ) && 'num' === $kinds[ $pk[0] ] && preg_match( '/int/i', (string) $types[ $pk[0] ] );

		$sql = '';
		if ( '' !== $create ) {
			$sql  = "--\n-- " . $name . "\n--\n";
			$sql .= 'DROP TABLE IF EXISTS ' . self::ident( $name ) . ";\n";
			$sql .= $create . ";\n";
		}
		return array(
			'sql'      => $sql,
			'skip'     => '' === $create,
			'cols'     => $names,
			'kinds'    => $kinds,
			'pk'       => $pk,
			'int_pk'   => (bool) $int_pk,
			'pk_index' => $int_pk ? $pk[0] : 0,
			'last'     => null,
			'offset'   => 0,
		);
	}

	/** @return array[]|null Satırlar (sayısal dizi), hata halinde null. */
	private static function fetch_rows( string $name, array $t, int $batch ) {
		global $wpdb;
		if ( ! empty( $t['skip'] ) ) {
			return array();
		}
		$table  = self::ident( $name );
		$select = $t['cols'] ? implode( ', ', array_map( array( __CLASS__, 'ident' ), $t['cols'] ) ) : '*';
		if ( $t['int_pk'] ) {
			$col = self::ident( $t['cols'][ $t['pk_index'] ] );
			$sql = "SELECT $select FROM $table";
			if ( null !== $t['last'] ) {
				$sql .= " WHERE $col > " . ( (string) (int) $t['last'] === (string) $t['last'] ? (string) $t['last'] : self::quote( (string) $t['last'] ) );
			}
			$sql .= " ORDER BY $col ASC LIMIT " . (int) $batch;
		} else {
			$order = '';
			if ( $t['pk'] ) {
				$order = ' ORDER BY ' . implode(
					', ',
					array_map(
						function ( $i ) use ( $t ) {
							return self::ident( $t['cols'][ $i ] );
						},
						$t['pk']
					)
				);
			}
			$sql = "SELECT $select FROM $table$order LIMIT " . (int) $batch . ' OFFSET ' . (int) $t['offset'];
		}
		$wpdb->last_error = '';
		$rows             = $wpdb->get_results( $sql, ARRAY_N ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( '' !== $wpdb->last_error ) {
			return null;
		}
		return is_array( $rows ) ? $rows : array();
	}

	private static function insert_sql( string $name, array $rows, array $t ): string {
		$head  = 'INSERT INTO ' . self::ident( $name ) . ( $t['cols'] ? ' (' . implode( ', ', array_map( array( __CLASS__, 'ident' ), $t['cols'] ) ) . ')' : '' ) . ' VALUES ';
		$out   = '';
		$cur   = '';
		$kinds = $t['kinds'];
		foreach ( $rows as $row ) {
			$vals = array();
			foreach ( $row as $i => $v ) {
				if ( null === $v ) {
					$vals[] = 'NULL';
				} elseif ( 'bin' === ( $kinds[ $i ] ?? 'str' ) ) {
					$vals[] = '' === $v ? "''" : '0x' . bin2hex( $v );
				} elseif ( 'num' === ( $kinds[ $i ] ?? 'str' ) && is_numeric( $v ) && preg_match( '/^-?[0-9]+(\.[0-9]+)?$/', (string) $v ) ) {
					$vals[] = (string) $v;
				} else {
					$vals[] = self::quote( (string) $v );
				}
			}
			$tuple = '(' . implode( ',', $vals ) . ')';
			if ( '' !== $cur && strlen( $cur ) + strlen( $tuple ) > self::MAX_INSERT_SIZE ) {
				$out .= $head . $cur . ";\n";
				$cur  = '';
			}
			$cur .= ( '' === $cur ? '' : ',' ) . $tuple;
		}
		if ( '' !== $cur ) {
			$out .= $head . $cur . ";\n";
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Geri yükleme                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Düz .sql dosyasını $st['offset']'ten itibaren ifade ifade çalıştırır.
	 *
	 * @return true|WP_Error
	 */
	public static function import_step( array &$st, string $sql_path, float $deadline ) {
		global $wpdb;
		$fh = fopen( $sql_path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			return new WP_Error( 'flxy_sql_open', __( 'Could not open the SQL file.', 'flx-backup' ) );
		}
		fseek( $fh, (int) $st['offset'] );
		$suppress = $wpdb->suppress_errors( true );
		$sqlite   = self::is_sqlite();
		if ( ! $sqlite ) {
			$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );
			$wpdb->query( "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'" );
		}
		$options = $wpdb->base_prefix . 'options';
		$buffer  = '';
		$lines   = 0;
		while ( microtime( true ) < $deadline ) {
			$line = fgets( $fh );
			if ( false === $line ) {
				if ( '' !== trim( $buffer ) ) {
					self::run_statement( $buffer, $st, $options, $sqlite );
				}
				$st['offset'] = ftell( $fh );
				$st['done']   = true;
				break;
			}
			if ( '' === $buffer ) {
				$trim = ltrim( $line );
				if ( '' === trim( $trim ) || 0 === strpos( $trim, '--' ) || 0 === strpos( $trim, '#' ) ) {
					$st['offset'] = ftell( $fh );
					continue;
				}
			}
			$buffer .= $line;
			++$lines;
			if ( ';' !== substr( rtrim( $line ), -1 ) ) {
				continue;
			}
			if ( $lines > 1 && ! self::balanced( $buffer ) ) {
				continue;
			}
			self::run_statement( $buffer, $st, $options, $sqlite );
			$buffer       = '';
			$lines        = 0;
			$st['offset'] = ftell( $fh );
		}
		$wpdb->suppress_errors( $suppress );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return true;
	}

	/** Tırnak/ters tırnak dengesi kapalı mı (ifade gerçekten bitti mi). */
	private static function balanced( string $sql ): bool {
		$len   = strlen( $sql );
		$quote = '';
		for ( $i = 0; $i < $len; $i++ ) {
			$ch = $sql[ $i ];
			if ( '' !== $quote ) {
				if ( '\\' === $ch && '`' !== $quote ) {
					++$i;
				} elseif ( $ch === $quote ) {
					$quote = '';
				}
			} elseif ( "'" === $ch || '"' === $ch || '`' === $ch ) {
				$quote = $ch;
			}
		}
		return '' === $quote;
	}

	private static function run_statement( string $sql, array &$st, string $options_table, bool $sqlite ): void {
		global $wpdb;
		$sql = rtrim( trim( $sql ), ';' );
		if ( '' === $sql ) {
			return;
		}
		$is_set = (bool) preg_match( '#^(/\*!\d+\s+)?SET\s#i', $sql );
		if ( $is_set ) {
			if ( $sqlite ) {
				return;
			}
			$sql = preg_replace( '#^/\*!\d+\s+(.*)\*/$#s', '$1', $sql );
		}
		if ( preg_match( '/^(?:INSERT\s+INTO|CREATE\s+TABLE|DROP\s+TABLE\s+IF\s+EXISTS)\s+`([^`]+)`/i', $sql, $m ) ) {
			$st['table'] = $m[1];
			if ( $m[1] === $options_table ) {
				$st['options_touched'] = true;
				// Satırları sabitlenmiş değerlerle çakışmasın diye REPLACE: geri yükleme sırasında siteurl,
				// active_plugins vb. her adım sonunda yeniden yazılır.
				$sql = preg_replace( '/^INSERT\s+INTO/i', 'REPLACE INTO', $sql );
			}
			if ( 0 === stripos( $sql, 'INSERT' ) || 0 === stripos( $sql, 'REPLACE' ) ) {
				++$st['inserts'];
			}
		}
		$wpdb->check_current_query = false;
		$wpdb->last_error          = '';
		$res                       = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( false === $res && ! $is_set ) {
			++$st['errors'];
			if ( count( $st['error_list'] ) < 10 ) {
				$st['error_list'][] = substr( $wpdb->last_error, 0, 300 ) . ' [' . substr( $sql, 0, 80 ) . ']';
			}
		}
	}

	/** Dökümün başlığındaki önek. */
	public static function dump_prefix( string $sql_path ): string {
		$fh = fopen( $sql_path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			return '';
		}
		$head = (string) fread( $fh, 1024 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return preg_match( '/^-- prefix: (\S+)/m', $head, $m ) ? $m[1] : '';
	}
}
