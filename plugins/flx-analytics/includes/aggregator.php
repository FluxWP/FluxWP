<?php
/**
 * Toplayıcı: kuyruk dosyasındaki ham satırları günlük özet tablolara işler.
 * WP-Cron (5 dk), panel açılışı ve herhangi bir isteğin sonu ("pompa", en az 10 dk arayla) tetikler;
 * Hostinger'da gerçek cron çalışmasa da veri birikmez.
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

class FLXAN_Aggregator {

	const BATCH = 2000;
	/** Yolculuk için ziyaret başına saklanan en fazla sayfa. */
	const SEQ_MAX = 8;

	public static function init() {
		add_action( 'flxan_aggregate', array( __CLASS__, 'run' ) );
		add_action( 'flxan_daily', array( __CLASS__, 'daily' ) );
		add_action( 'shutdown', array( __CLASS__, 'pump' ), PHP_INT_MAX - 10 );
	}

	/** Kuyruk dosyası. */
	public static function buffer_file() {
		return flxan_c_buffer_file( flxan_data_dir(), array( 'token' => (string) get_option( 'flxan_token', 'x' ) ) );
	}

	/** İşlenmeyi bekleyen bayt. */
	public static function pending_bytes() {
		$n = 0;
		$f = self::buffer_file();
		if ( is_file( $f ) ) {
			$n += (int) filesize( $f );
		}
		foreach ( (array) glob( flxan_data_dir() . '/work-*.ndjson' ) as $w ) {
			$n += (int) @filesize( $w ); // phpcs:ignore
		}
		return $n;
	}

	/** Bir isteğin sonunda, son çalışmadan 10 dk geçtiyse ve kuyruk doluysa çalışır. */
	public static function pump() {
		if ( wp_doing_cron() || ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) ) {
			return;
		}
		$last = (int) get_option( 'flxan_last_agg', 0 );
		if ( time() - $last < 10 * MINUTE_IN_SECONDS || ! is_file( self::buffer_file() ) ) {
			return;
		}
		if ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		} elseif ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}
		self::run( 8 );
	}

	/**
	 * Schedule the existing aggregate hook when it is missing.
	 * A report request must not run the queue itself.
	 */
	public static function nudge() {
		if ( time() - (int) get_option( 'flxan_last_agg', 0 ) < MINUTE_IN_SECONDS ) {
			return;
		}
		if ( wp_next_scheduled( 'flxan_aggregate' ) ) {
			return;
		}
		wp_schedule_single_event( time() + 15, 'flxan_aggregate' );
	}

	/** Son çalışma 60 sn'den eskiyse çalıştırır (panel ve anlık görünüm). */
	public static function maybe_run( $min_age = 60 ) {
		if ( time() - (int) get_option( 'flxan_last_agg', 0 ) >= $min_age ) {
			self::run( 10 );
		}
	}

	/**
	 * Kuyruğu işler. Dönüş: işlenen satır sayısı (kilitliyse -1).
	 *
	 * @param int $budget Saniye.
	 */
	public static function run( $budget = 20 ) {
		global $wpdb;
		$lock = (int) get_option( 'flxan_agg_lock', 0 );
		if ( $lock && time() - $lock < 120 ) {
			return -1;
		}
		if ( $lock ) {
			delete_option( 'flxan_agg_lock' );
		}
		if ( ! add_option( 'flxan_agg_lock', time(), '', false ) ) {
			return -1;
		}
		$start = microtime( true );
		$done  = 0;
		try {
			$dir = flxan_data_dir();
			$buf = self::buffer_file();
			if ( is_file( $buf ) && filesize( $buf ) > 0 ) {
				@rename( $buf, $dir . '/work-' . time() . '-' . wp_generate_password( 6, false, false ) . '.ndjson' ); // phpcs:ignore
			}
			$files = (array) glob( $dir . '/work-*.ndjson' );
			sort( $files );
			foreach ( $files as $file ) {
				if ( microtime( true ) - $start > $budget ) {
					break;
				}
				$done += self::process_file( $file, $start, $budget );
			}
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . flxan_table( 'recent' ) . ' WHERE ts < %d', time() - HOUR_IN_SECONDS ) ); // phpcs:ignore
			update_option( 'flxan_last_agg', time() );
			if ( $done ) {
				update_option( 'flxan_last_rows', array( 'time' => time(), 'rows' => $done ), false );
			}
		} catch ( Throwable $e ) {
			update_option( 'flxan_last_error', array( 'time' => time(), 'msg' => substr( $e->getMessage(), 0, 300 ) ), false );
		}
		delete_option( 'flxan_agg_lock' );
		return $done;
	}

	/** Bir iş dosyasını satır satır işler; süre biterse kalan satırlar yeni dosyaya yazılır. */
	private static function process_file( $file, $start, $budget ) {
		$fh = @fopen( $file, 'rb' ); // phpcs:ignore
		if ( ! $fh ) {
			return 0;
		}
		$batch = array();
		$n     = 0;
		$rest  = '';
		while ( ( $line = fgets( $fh ) ) !== false ) { // phpcs:ignore
			$r = json_decode( $line, true );
			if ( is_array( $r ) && isset( $r['k'], $r['d'], $r['h'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $r['d'] ) ) {
				$batch[] = $r;
			}
			if ( count( $batch ) >= self::BATCH ) {
				self::process( $batch );
				$n    += count( $batch );
				$batch = array();
				if ( microtime( true ) - $start > $budget ) {
					$rest = stream_get_contents( $fh );
					break;
				}
			}
		}
		fclose( $fh ); // phpcs:ignore
		if ( $batch ) {
			self::process( $batch );
			$n += count( $batch );
		}
		if ( '' !== (string) $rest ) {
			@file_put_contents( dirname( $file ) . '/work-' . time() . '-' . wp_generate_password( 6, false, false ) . '.ndjson', $rest ); // phpcs:ignore
		}
		@unlink( $file ); // phpcs:ignore
		return $n;
	}

	/** Gün içinde ilk kez mi görüldü? (d,h,k) üçlülerinden yeni olanları döndürür ve kaydeder. */
	private static function mark_seen( array $keys ) {
		global $wpdb;
		$t   = flxan_table( 'seen' );
		$new = array();
		$by  = array();
		foreach ( $keys as $key => $_ ) {
			list( $d, $h ) = explode( '|', $key, 3 );
			$by[ $d ][ $h ] = true;
		}
		$exists = array();
		foreach ( $by as $d => $hs ) {
			foreach ( array_chunk( array_keys( $hs ), 400 ) as $chunk ) {
				$in   = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT h, k FROM {$t} WHERE d = %s AND h IN ({$in})", array_merge( array( $d ), $chunk ) ), ARRAY_N ); // phpcs:ignore
				foreach ( (array) $rows as $row ) {
					$exists[ $d . '|' . $row[0] . '|' . $row[1] ] = true;
				}
			}
		}
		foreach ( $keys as $key => $_ ) {
			if ( ! isset( $exists[ $key ] ) ) {
				$new[ $key ] = true;
			}
		}
		foreach ( array_chunk( array_keys( $new ), 500 ) as $chunk ) {
			$vals = array();
			$args = array();
			foreach ( $chunk as $key ) {
				$p      = explode( '|', $key, 3 );
				$vals[] = '(%s,%s,%s)';
				array_push( $args, $p[0], $p[1], $p[2] );
			}
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$t} (d,h,k) VALUES " . implode( ',', $vals ), $args ) ); // phpcs:ignore
		}
		return $new;
	}

	/** Bir grup satırı işler ve tablolara yazar. */
	public static function process( array $rows ) {
		global $wpdb;
		$own     = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$funnels = flxan_funnels();
		$roles   = function_exists( 'wp_roles' ) ? wp_roles()->get_names() : array();
		$keys    = array();
		$prep    = array();
		foreach ( $rows as $r ) {
			$d   = $r['d'];
			$h   = substr( preg_replace( '/[^a-z0-9]/', '', (string) $r['h'] ), 0, 16 );
			$seg = (string) ( $r['g'] ?? '' );
			if ( '' !== $seg && ! isset( $roles[ $seg ] ) ) {
				$seg = ''; // Bilinmeyen rol adı (elle uydurulmuş istek) dışarıdan sayılır.
			}
			$rc  = (string) ( $r['rc'] ?? '' );
			if ( 'e' === $r['k'] ) {
				$name  = (string) ( $r['n'] ?? '' );
				$param = (string) ( $r['v'] ?? '' );
				if ( '' === $name ) {
					continue;
				}
				$ek                  = 'e' . substr( md5( $name . '|' . $param ), 0, 7 );
				$keys[ "$d|$h|$ek" ] = true;
				$prep[]              = array(
					'k'      => 'e',
					'd'      => $d,
					'h'      => $h,
					'seg'    => $seg,
					'rc'     => $rc,
					'ek'     => $ek,
					'name'   => $name,
					'param'  => $param,
					'amount' => (float) ( $r['a'] ?? 0 ),
					't'      => (int) $r['t'],
				);
				continue;
			}
			$path = (string) ( $r['p'] ?? '' );
			if ( '' === $path ) {
				continue;
			}
			$pk                  = substr( md5( $path ), 0, 8 );
			$keys[ "$d|$h|" ]    = true;
			$keys[ "$d|$h|$pk" ] = true;
			if ( '' !== $rc ) {
				$keys[ "$d|$h|r" . substr( md5( $rc ), 0, 7 ) ] = true;
			}
			$ua      = (string) ( $r['u'] ?? '' );
			$country = (string) ( $r['c'] ?? '' );
			if ( '' === $country ) {
				$country = flxan_tz_country( (string) ( $r['z'] ?? '' ) );
			}
			$prep[] = array(
				'k'       => 'p',
				'd'       => $d,
				'h'       => $h,
				'seg'     => $seg,
				'rc'      => $rc,
				'pk'      => $pk,
				'path'    => $path,
				'post_id' => (int) ( $r['i'] ?? 0 ),
				'is404'   => ! empty( $r['x'] ),
				'ref'     => flxan_normalize_referrer( (string) ( $r['r'] ?? '' ), $own ),
				'country' => $country,
				'dims'    => array(
					'device'       => flxan_ua_device( $ua, (int) ( $r['w'] ?? 0 ) ),
					'browser'      => flxan_ua_browser( $ua ),
					'os'           => flxan_ua_os( $ua ),
					'lang'         => (string) ( $r['l'] ?? '' ),
					'country'      => $country,
					'utm_source'   => (string) ( $r['us'] ?? '' ),
					'utm_medium'   => (string) ( $r['um'] ?? '' ),
					'utm_campaign' => (string) ( $r['uc'] ?? '' ),
				),
				't'       => (int) $r['t'],
			);
		}
		if ( ! $prep ) {
			return;
		}
		$new    = self::mark_seen( $keys );
		$used   = array();
		$is_new = function ( $key ) use ( &$new, &$used ) {
			if ( isset( $new[ $key ] ) && ! isset( $used[ $key ] ) ) {
				$used[ $key ] = true;
				return 1;
			}
			return 0;
		};
		$add    = function ( array &$acc, array $key, $v, $p, $extra = 0.0 ) {
			$k = implode( "\0", $key );
			if ( ! isset( $acc[ $k ] ) ) {
				$acc[ $k ] = array_merge( $key, array( 0, 0 ) );
			}
			$n                  = count( $key );
			$acc[ $k ][ $n ]   += $v;
			$acc[ $k ][ $n + 1 ] += $p;
			if ( $extra ) {
				$acc[ $k ][ $n + 2 ] = ( $acc[ $k ][ $n + 2 ] ?? 0.0 ) + $extra;
			}
		};
		$fstep  = function ( array &$acc, $d, $h, array $p ) use ( $funnels ) {
			foreach ( $funnels as $f ) {
				foreach ( $f['steps'] as $i => $st ) {
					if ( flxan_step_match( $st, $p['k'], $p['path'] ?? '', $p['name'] ?? '', $p['param'] ?? '' ) ) {
						$k         = "$d|$h|" . (int) $f['id'];
						$acc[ $k ] = array( $d, $h, (int) $f['id'], max( $i + 1, $acc[ $k ][3] ?? 0 ) );
					}
				}
			}
		};

		$site   = array();
		$pages  = array();
		$refs   = array();
		$dims   = array();
		$events = array();
		$visits = array();
		$fsteps = array();
		$recent = array();
		$paths  = array();
		$hosts  = array();
		$hour   = time() - HOUR_IN_SECONDS;

		$late = array();
		$jset = (array) apply_filters( 'flxan_journey_events', array( 'add_to_cart', 'remove_from_cart', 'coupon', 'checkout', 'payment', 'order', 'whatsapp', 'tel', 'download' ) );
		$jev  = function ( $name, $param ) use ( $jset ) {
			if ( ! in_array( $name, $jset, true ) ) {
				return '';
			}
			return '@' . $name . ( 'checkout' === $name && in_array( $param, array( 'form', 'submit' ), true ) ? ':' . $param : '' );
		};
		usort(
			$prep,
			function ( $a, $b ) {
				return $a['t'] <=> $b['t'];
			}
		);
		foreach ( $prep as $p ) {
			$d   = $p['d'];
			$h   = $p['h'];
			$seg = $p['seg'];
			$rc  = $p['rc'];
			if ( 'e' === $p['k'] ) {
				$add( $events, array( $d, $p['name'], $p['param'], $seg, $rc ), 1, $is_new( "$d|$h|{$p['ek']}" ), $p['amount'] );
				$fstep( $fsteps, $d, $h, $p );
				// Yolculukta görünen olaylar (sepete ekleme, ödeme adımları…) ziyaretin sayfa sırasına "@olay" olarak girer.
				$jk = $jev( $p['name'], $p['param'] );
				if ( '' !== $jk && isset( $visits[ "$d|$h" ] ) ) {
					$v = &$visits[ "$d|$h" ];
					if ( count( $v['seq'] ) < self::SEQ_MAX && end( $v['seq'] ) !== $jk ) {
						$v['seq'][]    = $jk;
						$paths[ $jk ] = array( 0, false );
					}
					unset( $v );
				} elseif ( '' !== $jk && ! isset( $visits[ "$d|$h" ] ) ) {
					$late[] = array( $d, $h, $jk );
					$paths[ $jk ] = array( 0, false );
				}
				continue;
			}
			$nv   = $is_new( "$d|$h|" );
			$np   = $is_new( "$d|$h|{$p['pk']}" );
			$path = $p['path'];
			$ref  = $p['ref'];
			$dm   = $p['dims'];
			$add( $site, array( $d, $seg, $rc ), $nv, 1 );
			$paths[ $path ] = array( max( $p['post_id'], $paths[ $path ][0] ?? 0 ), $p['is404'] || ! empty( $paths[ $path ][1] ) );
			$add( $pages, array( $d, $path, $seg, $rc ), $np, 1 );
			if ( '' !== $ref ) {
				$hosts[ $ref ] = true;
				$add( $refs, array( $d, $ref, $seg, $rc ), $nv, 1 );
			}
			if ( $nv || '' !== $ref || '' !== $dm['utm_source'] || ( '' !== $rc && $is_new( "$d|$h|r" . substr( md5( $rc ), 0, 7 ) ) ) ) {
				$dm['channel'] = '' !== $rc && '' === $ref && '' === $dm['utm_source'] ? 'ref' : flxan_channel( $ref, $dm['utm_source'], $dm['utm_medium'] );
			}
			foreach ( $dm as $kind => $val ) {
				if ( '' !== $val ) {
					$add( $dims, array( $d, $kind, $val, $seg, $rc ), $nv, 1 );
				}
			}
			$vk = "$d|$h";
			if ( ! isset( $visits[ $vk ] ) ) {
				$visits[ $vk ] = array(
					'd'       => $d,
					'h'       => $h,
					'seg'     => $seg,
					'rc'      => $rc,
					'channel' => $dm['channel'] ?? flxan_channel( $ref, $dm['utm_source'], $dm['utm_medium'] ),
					'ref'     => $ref,
					'entry'   => $path,
					'country' => $p['country'],
					'pages'   => 0,
					'first'   => $p['t'],
					'last'    => $p['t'],
					'seq'     => array(),
					'exit'    => $path,
				);
			}
			$v            = &$visits[ $vk ];
			$v['pages']++;
			if ( count( $v['seq'] ) < self::SEQ_MAX && end( $v['seq'] ) !== $path ) {
				$v['seq'][] = $path;
			}
			if ( $p['t'] >= $v['last'] ) {
				$v['exit'] = $path;
			}
			$v['first']   = min( $v['first'], $p['t'] );
			$v['last']    = max( $v['last'], $p['t'] );
			$v['rc']      = '' !== $v['rc'] ? $v['rc'] : $rc;
			$v['seg']     = '' !== $v['seg'] ? $v['seg'] : $seg;
			$v['country'] = '' !== $v['country'] ? $v['country'] : $p['country'];
			unset( $v );
			$fstep( $fsteps, $d, $h, $p );
			if ( $p['t'] >= $hour ) {
				$recent[] = array( $p['t'], $path, $ref, $h, $seg, $rc, $p['country'] );
			}
		}

		$path_ids = self::path_ids( $paths );
		$ref_ids  = self::ref_ids( array_keys( $hosts ) );
		$sum      = array( 'visitors', 'pageviews' );

		self::upsert( flxan_table( 'site' ), array( 'd', 'seg', 'rc', 'visitors', 'pageviews' ), array_values( $site ), $sum );

		$map = function ( array $acc, $i, array $ids ) {
			$out = array();
			foreach ( $acc as $row ) {
				if ( isset( $ids[ $row[ $i ] ] ) ) {
					$row[ $i ] = $ids[ $row[ $i ] ];
					$out[]     = $row;
				}
			}
			return $out;
		};
		self::upsert( flxan_table( 'pages' ), array( 'd', 'path_id', 'seg', 'rc', 'visitors', 'pageviews' ), $map( $pages, 1, $path_ids ), $sum );
		self::upsert( flxan_table( 'referrers' ), array( 'd', 'ref_id', 'seg', 'rc', 'visitors', 'pageviews' ), $map( $refs, 1, $ref_ids ), $sum );
		self::upsert( flxan_table( 'dims' ), array( 'd', 'kind', 'val', 'seg', 'rc', 'visitors', 'pageviews' ), array_values( $dims ), $sum );
		self::upsert( flxan_table( 'events' ), array( 'd', 'name', 'param', 'seg', 'rc', 'hits', 'uniques', 'amount' ), array_map(
			function ( $r ) {
				return array( $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6], (float) ( $r[7] ?? 0 ) );
			},
			array_values( $events )
		), array( 'hits', 'uniques', 'amount' ) );

		$rows = array();
		foreach ( $visits as $v ) {
			$seq = array();
			foreach ( $v['seq'] as $sp ) {
				if ( isset( $path_ids[ $sp ] ) ) {
					$seq[] = (int) $path_ids[ $sp ];
				}
			}
			$rows[] = array( $v['d'], $v['h'], $v['seg'], $v['rc'], (string) $v['channel'], '' !== $v['ref'] ? (int) ( $ref_ids[ $v['ref'] ] ?? 0 ) : 0, (int) ( $path_ids[ $v['entry'] ] ?? 0 ), $v['country'], (int) $v['pages'], (int) $v['first'], (int) $v['last'], implode( ',', $seq ), (int) ( $path_ids[ $v['exit'] ] ?? 0 ) );
		}
		// Ziyaretin sayfası önceki toplu işlemde kalmışsa olay yalnız sırasına eklenir (satır zaten var).
		foreach ( $late as $l ) {
			if ( isset( $path_ids[ $l[2] ] ) ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ' . flxan_table( 'visits' ) . " SET seq = IF(seq = '', %s, IF(SUBSTRING_INDEX(seq, ',', -1) = %s OR (LENGTH(seq) - LENGTH(REPLACE(seq, ',', ''))) >= %d, seq, CONCAT(seq, ',', %s))) WHERE d = %s AND h = %s", (string) $path_ids[ $l[2] ], (string) $path_ids[ $l[2] ], self::SEQ_MAX - 1, (string) $path_ids[ $l[2] ], $l[0], $l[1] ) ); // phpcs:ignore
			}
		}
		self::upsert(
			flxan_table( 'visits' ),
			array( 'd', 'h', 'seg', 'rc', 'channel', 'ref_id', 'entry_id', 'country', 'pages', 'first_ts', 'last_ts', 'seq', 'exit_id' ),
			$rows,
			array( 'pages' ),
			array( 'exit_id = IF(VALUES(exit_id) > 0 AND VALUES(last_ts) >= last_ts, VALUES(exit_id), exit_id)', "seq = IF(seq = '', VALUES(seq), IF(VALUES(seq) = '', seq, SUBSTRING_INDEX(CONCAT(seq, ',', VALUES(seq)), ',', " . self::SEQ_MAX . ')))', 'first_ts = LEAST(first_ts, VALUES(first_ts))', 'last_ts = GREATEST(last_ts, VALUES(last_ts))', "rc = IF(rc = '', VALUES(rc), rc)", "seg = IF(seg = '', VALUES(seg), seg)", "country = IF(country = '', VALUES(country), country)" )
		);
		self::upsert( flxan_table( 'fsteps' ), array( 'd', 'h', 'f', 'step' ), array_values( $fsteps ), array(), array( 'step = GREATEST(step, VALUES(step))' ) );

		$rows = array();
		foreach ( $recent as $v ) {
			$rows[] = array( $v[0], (int) ( $path_ids[ $v[1] ] ?? 0 ), '' !== $v[2] ? (int) ( $ref_ids[ $v[2] ] ?? 0 ) : 0, $v[3], $v[4], $v[5], $v[6] );
		}
		self::upsert( flxan_table( 'recent' ), array( 'ts', 'path_id', 'ref_id', 'h', 'seg', 'rc', 'country' ), $rows, array() );
	}

	/** Yol → kimlik (yoksa eklenir). */
	private static function path_ids( array $paths ) {
		global $wpdb;
		$t = flxan_table( 'paths' );
		if ( ! $paths ) {
			return array();
		}
		$rows = array();
		foreach ( $paths as $path => $v ) {
			$rows[] = array( $path, (int) $v[0], $v[1] ? 1 : 0 );
		}
		foreach ( array_chunk( $rows, 300 ) as $chunk ) {
			$vals = array();
			$args = array();
			foreach ( $chunk as $r ) {
				$vals[] = '(%s,%d,%d)';
				array_push( $args, $r[0], $r[1], $r[2] );
			}
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$t} (path,post_id,is404) VALUES " . implode( ',', $vals ) . ' ON DUPLICATE KEY UPDATE post_id = IF(VALUES(post_id) > 0, VALUES(post_id), post_id), is404 = VALUES(is404)', $args ) ); // phpcs:ignore
		}
		return self::lookup( $t, 'path', array_keys( $paths ) );
	}

	/** Alan adı → kimlik (yoksa eklenir). */
	private static function ref_ids( array $hosts ) {
		global $wpdb;
		$t = flxan_table( 'refs' );
		if ( ! $hosts ) {
			return array();
		}
		foreach ( array_chunk( $hosts, 300 ) as $chunk ) {
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$t} (host) VALUES " . implode( ',', array_fill( 0, count( $chunk ), '(%s)' ) ), $chunk ) ); // phpcs:ignore
		}
		return self::lookup( $t, 'host', $hosts );
	}

	private static function lookup( $t, $col, array $vals ) {
		global $wpdb;
		$out = array();
		foreach ( array_chunk( $vals, 300 ) as $chunk ) {
			$in   = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, {$col} FROM {$t} WHERE {$col} IN ({$in})", $chunk ), ARRAY_N ); // phpcs:ignore
			foreach ( (array) $rows as $r ) {
				$out[ $r[1] ] = (int) $r[0];
			}
		}
		return $out;
	}

	/** Toplu INSERT … ON DUPLICATE KEY UPDATE col = col + VALUES(col). */
	public static function upsert( $table, array $cols, array $rows, array $sum, array $raw = array() ) {
		global $wpdb;
		if ( ! $rows ) {
			return;
		}
		$upd = $raw;
		foreach ( $sum as $c ) {
			$upd[] = "{$c} = {$c} + VALUES({$c})";
		}
		foreach ( array_chunk( $rows, 400 ) as $chunk ) {
			$vals = array();
			$args = array();
			foreach ( $chunk as $r ) {
				$ph = array();
				foreach ( array_values( $r ) as $v ) {
					if ( is_float( $v ) ) {
						$ph[] = '%f';
					} elseif ( is_int( $v ) ) {
						$ph[] = '%d';
					} else {
						$ph[] = '%s';
					}
					$args[] = $v;
				}
				$vals[] = '(' . implode( ',', $ph ) . ')';
			}
			$sql = "INSERT INTO {$table} (" . implode( ',', $cols ) . ') VALUES ' . implode( ',', $vals ) . ( $upd ? ' ON DUPLICATE KEY UPDATE ' . implode( ', ', $upd ) : '' );
			$wpdb->query( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore
		}
	}

	/** Günlük: eski tekillik kayıtları, eski tuzlar ve (ayarlıysa) eski istatistikler silinir. */
	public static function daily() {
		global $wpdb;
		$today = wp_date( 'Y-m-d' );
		$keep  = gmdate( 'Y-m-d', strtotime( $today . ' -1 day' ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . flxan_table( 'seen' ) . ' WHERE d < %s', $keep ) ); // phpcs:ignore
		$ok = array( str_replace( '-', '', $today ), str_replace( '-', '', $keep ) );
		foreach ( (array) glob( flxan_data_dir() . '/salt-*.php' ) as $f ) {
			if ( preg_match( '/salt-(\d{8})\.php$/', $f, $m ) && ! in_array( $m[1], $ok, true ) ) {
				@unlink( $f ); // phpcs:ignore
			}
		}
		$months = (int) flxan_settings( 'keep_months' );
		if ( $months > 0 ) {
			$cut = gmdate( 'Y-m-d', strtotime( $today . ' -' . $months . ' months' ) );
			foreach ( array( 'site', 'pages', 'referrers', 'dims', 'events', 'visits', 'fsteps' ) as $t ) {
				$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . flxan_table( $t ) . ' WHERE d < %s', $cut ) ); // phpcs:ignore
			}
		}
		update_option( 'flxan_last_daily', time(), false );
	}

	/** Tüm istatistikleri siler (Ayarlar › Verileri sıfırla). */
	public static function reset() {
		global $wpdb;
		foreach ( array( 'paths', 'refs', 'site', 'pages', 'referrers', 'dims', 'events', 'visits', 'fsteps', 'seen', 'recent' ) as $t ) {
			$wpdb->query( 'TRUNCATE TABLE ' . flxan_table( $t ) ); // phpcs:ignore
		}
		foreach ( array( 'buffer', 'work' ) as $prefix ) {
			foreach ( glob( flxan_data_dir() . '/' . $prefix . '-*.ndjson' ) ?: array() as $f ) {
				@unlink( $f ); // phpcs:ignore
			}
		}
		delete_option( 'flxan_last_rows' );
	}
}
FLXAN_Aggregator::init();

/**
 * Sunucu tarafı olay (ör. ödenen bağış). Kuyruğa yazılır, toplayıcı işler.
 *
 * @param string $name   a-z0-9_- (en fazla 40).
 * @param string $param  Ayrıntı (en fazla 190).
 * @param float  $amount Tutar (varsa).
 * @param string $uniq   Tekillik anahtarı (ör. sipariş no); boşsa her çağrı tekil sayılır.
 * @param string $ref    Referans kodu (?r=…) varsa.
 */
function flxan_track_event( $name, $param = '', $amount = 0, $uniq = '', $ref = '' ) {
	if ( ! flxan_settings( 'on' ) || ! preg_match( '/^[a-z0-9_-]{1,40}$/i', (string) $name ) ) {
		return false;
	}
	$dir = flxan_data_dir();
	$cfg = is_file( $dir . '/config.php' ) ? include $dir . '/config.php' : null;
	if ( ! is_array( $cfg ) ) {
		return false;
	}
	$rec = array(
		'k' => 'e',
		't' => time(),
		'd' => wp_date( 'Y-m-d' ),
		'h' => substr( md5( 'srv|' . ( '' !== (string) $uniq ? $uniq : wp_generate_uuid4() ) ), 0, 16 ),
		'p' => '',
		'n' => strtolower( $name ),
		'v' => flxan_c_text( $param, 190 ),
	);
	if ( $amount ) {
		$rec['a'] = round( (float) $amount, 2 );
	}
	$rc = flxan_clean_ref( $ref );
	if ( '' !== $rc ) {
		$rec['rc'] = $rc;
	}
	return flxan_c_write( $dir, $cfg, $rec );
}
