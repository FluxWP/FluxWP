<?php
/**
 * Kayıt tablosu: hangi istek kaydedilir, okuma, özetler, temizlik.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

class FLXP_Store {

	/** Bayrak listesi (filtrelerde ve MCP'de). */
	public static function flag_labels() {
		return array(
			'slow'       => __( 'Slow', 'flx-speed' ),
			'error'      => __( 'PHP error', 'flx-speed' ),
			'fatal'      => __( 'Fatal error', 'flx-speed' ),
			'5xx'        => __( 'Server error (5xx)', 'flx-speed' ),
			'http_fail'  => __( 'Failed HTTP call', 'flx-speed' ),
			'slow_query' => __( 'Slow query', 'flx-speed' ),
			'db_error'   => __( 'Query error', 'flx-speed' ),
			'log_error'  => __( 'Error log', 'flx-speed' ),
			'sample'     => __( 'Sample', 'flx-speed' ),
			'profile'    => __( 'Profile', 'flx-speed' ),
		);
	}

	/** İstek türü etiketleri. */
	public static function type_labels() {
		return array(
			'front'  => __( 'Front end', 'flx-speed' ),
			'admin'  => __( 'Admin', 'flx-speed' ),
			'ajax'   => 'Ajax',
			'rest'   => 'REST',
			'cron'   => 'Cron',
			'xmlrpc' => 'XML-RPC',
		);
	}

	/** Bir isteğin bayrakları. */
	public static function flags( $snap ) {
		$s  = flxp_settings();
		$o  = $snap['overview'];
		$fl = array();
		if ( $o['ms'] >= (int) $s['slow_ms'] ) {
			$fl[] = 'slow';
		}
		if ( $o['fatal'] ) {
			$fl[] = 'fatal';
		} elseif ( $o['errors'] > 0 ) {
			$fl[] = 'error';
		}
		if ( $o['status'] >= 500 ) {
			$fl[] = '5xx';
		}
		if ( $o['http_fail'] > 0 ) {
			$fl[] = 'http_fail';
		}
		if ( ! empty( $snap['db']['slow'] ) ) {
			$fl[] = 'slow_query';
		}
		if ( ! empty( $snap['db']['errors'] ) ) {
			$fl[] = 'db_error';
		}
		foreach ( $snap['logs'] as $l ) {
			if ( in_array( $l['level'], array( 'emergency', 'alert', 'critical', 'error' ), true ) ) {
				$fl[] = 'log_error';
				break;
			}
		}
		return $fl;
	}

	/** Kayıt koşulları sağlanıyorsa tabloya yazar. */
	public static function maybe_record( $snap ) {
		$s = flxp_settings();
		if ( ! $s['record'] && ! FLXP_Collector::$profile ) {
			return 0;
		}
		$url = (string) $snap['overview']['url'];
		// Kendi kayıt ekranlarımız ve kalp atışı gürültü üretmesin.
		if ( false !== strpos( $url, 'page=flx-speed' ) ) {
			return 0;
		}
		$flags = self::flags( $snap );
		if ( FLXP_Collector::$profile ) {
			$flags[] = 'profile';
		}
		if ( FLXP_Collector::$sampled && ! FLXP_Collector::$panel ) {
			$flags[] = 'sample';
		}
		if ( ! $flags ) {
			return 0;
		}
		// Panel açık yöneticinin kendi gezintisi: yalnız ayar açıksa ve gerçek bir sorun varsa.
		if ( FLXP_Collector::$panel && ! FLXP_Collector::$profile && ( ! $s['record_admins'] || array( 'sample' ) === $flags ) ) {
			return 0;
		}
		if ( ! function_exists( 'flxp_table' ) || (int) get_option( 'flxp_db_version', 0 ) < 1 ) {
			return 0;
		}
		return self::insert( $snap, array_values( array_unique( $flags ) ) );
	}

	/** Tabloya yazar; veri boyutunu sınırlar. */
	public static function insert( $snap, $flags ) {
		global $wpdb;
		$snap = self::trim( $snap );
		$json = wp_json_encode( $snap, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
		if ( strlen( (string) $json ) > 900000 ) {
			$snap['db']['queries'] = array();
			$snap['db']['trimmed'] = true;
			$json                  = wp_json_encode( $snap, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
		}
		$o  = $snap['overview'];
		$ok = $wpdb->insert( // phpcs:ignore
			flxp_table(),
			array(
				'created'  => gmdate( 'Y-m-d H:i:s' ),
				'url'      => substr( (string) $o['url'], 0, 500 ),
				'method'   => strtoupper( substr( (string) $o['method'], 0, 10 ) ),
				'type'     => (string) $o['type'],
				'status'   => (int) $o['status'],
				'ms'       => (int) $o['ms'],
				'mem_kb'   => (int) $o['mem_kb'],
				'queries'  => (int) $o['queries'],
				'query_ms' => (int) round( $o['query_ms'] ),
				'http'     => (int) $o['http'],
				'errors'   => (int) $o['errors'],
				'flags'    => implode( ',', $flags ),
				'user_id'  => (int) $o['user'],
				'data'     => $json,
			)
		);
		$id = $ok ? (int) $wpdb->insert_id : 0;
		FLXP_Collector::$recorded_id = $id;
		if ( $id && 1 === mt_rand( 1, 25 ) ) { // phpcs:ignore
			self::cleanup();
		}
		return $id;
	}

	/** Kaydedilen veriden gereksiz ağır alanları atar. */
	private static function trim( $snap ) {
		$max = 400;
		if ( count( $snap['db']['queries'] ) > $max ) {
			$q = $snap['db']['queries'];
			usort(
				$q,
				function ( $a, $b ) {
					return $b['ms'] <=> $a['ms'];
				}
			);
			$q = array_slice( $q, 0, $max );
			usort(
				$q,
				function ( $a, $b ) {
					return $a['i'] <=> $b['i'];
				}
			);
			$snap['db']['queries'] = $q;
			$snap['db']['trimmed'] = true;
		}
		$snap['caps']   = array_slice( $snap['caps'], 0, 300 );
		$snap['assets'] = array();
		return $snap;
	}

	/** Eski kayıtları ve sınırı aşanları siler. */
	public static function cleanup() {
		global $wpdb;
		$t    = flxp_table();
		$days = (int) flxp_settings( 'keep_days' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE created < %s", gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) ); // phpcs:ignore
		$max = (int) flxp_settings( 'max_rows' );
		$cut = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} ORDER BY id DESC LIMIT 1 OFFSET %d", $max ) ); // phpcs:ignore
		if ( $cut ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE id <= %d", $cut ) ); // phpcs:ignore
		}
		update_option( 'flxp_last_cleanup', time(), false );
	}

	/** Tümünü siler. */
	public static function clear() {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . flxp_table() ); // phpcs:ignore
	}

	/**
	 * Filtreli liste.
	 *
	 * @param array $args hours, flag, type, q (URL içinde), min_ms, page, per_page, order (new|slow).
	 * @return array{items:array,total:int}
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$t     = flxp_table();
		$where = array( '1=1' );
		$vals  = array();
		if ( ! empty( $args['hours'] ) ) {
			$where[] = 'created >= %s';
			$vals[]  = gmdate( 'Y-m-d H:i:s', time() - (int) $args['hours'] * HOUR_IN_SECONDS );
		}
		if ( ! empty( $args['flag'] ) && isset( self::flag_labels()[ $args['flag'] ] ) ) {
			$where[] = 'FIND_IN_SET(%s, flags)';
			$vals[]  = $args['flag'];
		}
		if ( ! empty( $args['type'] ) && isset( self::type_labels()[ $args['type'] ] ) ) {
			$where[] = 'type = %s';
			$vals[]  = $args['type'];
		}
		if ( ! empty( $args['q'] ) ) {
			$where[] = 'url LIKE %s';
			$vals[]  = '%' . $wpdb->esc_like( (string) $args['q'] ) . '%';
		}
		if ( ! empty( $args['min_ms'] ) ) {
			$where[] = 'ms >= %d';
			$vals[]  = (int) $args['min_ms'];
		}
		$per   = max( 1, min( 200, (int) ( $args['per_page'] ?? 30 ) ) );
		$page  = max( 1, (int) ( $args['page'] ?? 1 ) );
		$order = ( isset( $args['order'] ) && 'slow' === $args['order'] ) ? 'ms DESC' : 'id DESC';
		$w     = implode( ' AND ', $where );
		$cols  = 'id, created, url, method, type, status, ms, mem_kb, queries, query_ms, http, errors, flags, user_id';
		$sqlc  = "SELECT COUNT(*) FROM {$t} WHERE {$w}";
		$sql   = "SELECT {$cols} FROM {$t} WHERE {$w} ORDER BY {$order} LIMIT %d OFFSET %d";
		$total = (int) ( $vals ? $wpdb->get_var( $wpdb->prepare( $sqlc, $vals ) ) : $wpdb->get_var( $sqlc ) ); // phpcs:ignore
		$items = (array) $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $vals, array( $per, ( $page - 1 ) * $per ) ) ), ARRAY_A ); // phpcs:ignore
		foreach ( $items as &$it ) {
			$it = self::row( $it );
		}
		unset( $it );
		return array( 'items' => $items, 'total' => $total );
	}

	/** Satırı tiplere çevirir. */
	private static function row( $r ) {
		foreach ( array( 'id', 'status', 'ms', 'mem_kb', 'queries', 'query_ms', 'http', 'errors', 'user_id' ) as $k ) {
			$r[ $k ] = (int) $r[ $k ];
		}
		$r['flags']   = $r['flags'] ? explode( ',', $r['flags'] ) : array();
		$r['created'] = gmdate( 'c', (int) strtotime( $r['created'] . ' UTC' ) );
		return $r;
	}

	/** Tek kayıt (veriyle). */
	public static function get( $id ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxp_table() . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore
		if ( ! $r ) {
			return null;
		}
		$data      = json_decode( (string) $r['data'], true );
		unset( $r['data'] );
		$r         = self::row( $r );
		$r['data'] = is_array( $data ) ? $data : array();
		return $r;
	}

	/** Son X saatin satırlarının veri sütunlarını parça parça dolaşır. */
	private static function each_data( $hours, $callback, $limit = 2000 ) {
		global $wpdb;
		$t     = flxp_table();
		$since = gmdate( 'Y-m-d H:i:s', time() - (int) $hours * HOUR_IN_SECONDS );
		$last  = PHP_INT_MAX;
		$seen  = 0;
		while ( $seen < $limit ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, created, url, data FROM {$t} WHERE created >= %s AND id < %d ORDER BY id DESC LIMIT 50", $since, $last ), ARRAY_A ); // phpcs:ignore
			if ( ! $rows ) {
				break;
			}
			foreach ( $rows as $r ) {
				$last = (int) $r['id'];
				$seen++;
				$d = json_decode( (string) $r['data'], true );
				if ( is_array( $d ) ) {
					$callback( $d, $r );
				}
			}
		}
	}

	/** Özet: sayılar, süre dağılımı, en yavaş adresler, en ağır bileşenler. */
	public static function summary( $hours = 24 ) {
		global $wpdb;
		$t     = flxp_table();
		$since = gmdate( 'Y-m-d H:i:s', time() - (int) $hours * HOUR_IN_SECONDS );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT url, type, ms, queries, errors, flags, status FROM {$t} WHERE created >= %s ORDER BY id DESC LIMIT 5000", $since ), ARRAY_A ); // phpcs:ignore
		$ms    = array();
		$flags = array();
		$types = array();
		$urls  = array();
		$by_type = array();
		foreach ( (array) $rows as $r ) {
			$ms[] = (int) $r['ms'];
			foreach ( $r['flags'] ? explode( ',', $r['flags'] ) : array() as $f ) {
				$flags[ $f ] = ( $flags[ $f ] ?? 0 ) + 1;
			}
			$types[ $r['type'] ] = ( $types[ $r['type'] ] ?? 0 ) + 1;
			$by_type[ $r['type'] ][] = (int) $r['ms'];
			$u                   = self::url_group( $r['url'] );
			if ( ! isset( $urls[ $u ] ) ) {
				$urls[ $u ] = array( 'url' => $u, 'count' => 0, 'ms_sum' => 0, 'ms_max' => 0, 'errors' => 0 );
			}
			$urls[ $u ]['count']++;
			$urls[ $u ]['ms_sum'] += (int) $r['ms'];
			$urls[ $u ]['ms_max']  = max( $urls[ $u ]['ms_max'], (int) $r['ms'] );
			$urls[ $u ]['errors'] += (int) $r['errors'];
		}
		sort( $ms );
		$n   = count( $ms );
		$pct = function ( $p ) use ( $ms, $n ) {
			return $n ? $ms[ min( $n - 1, (int) floor( $p * ( $n - 1 ) ) ) ] : 0;
		};
		foreach ( $urls as &$u ) {
			$u['ms_avg'] = (int) round( $u['ms_sum'] / max( 1, $u['count'] ) );
			unset( $u['ms_sum'] );
		}
		unset( $u );
		usort(
			$urls,
			function ( $a, $b ) {
				return $b['ms_avg'] <=> $a['ms_avg'];
			}
		);

		// Bileşen başına sorgu süresi (ayrıntılı kayıtlardan).
		$comps = array();
		self::each_data(
			$hours,
			function ( $d ) use ( &$comps ) {
				foreach ( $d['db']['by_component'] ?? array() as $bc ) {
					$k = flxp_component_key( $bc['comp'] );
					if ( ! isset( $comps[ $k ] ) ) {
						$comps[ $k ] = array( 'component' => flxp_component_label( $bc['comp'] ), 'requests' => 0, 'queries' => 0, 'ms' => 0 );
					}
					$comps[ $k ]['requests']++;
					$comps[ $k ]['queries'] += $bc['count'];
					$comps[ $k ]['ms']      += $bc['ms'];
				}
			},
			500
		);
		foreach ( $comps as &$c ) {
			$c['ms_per_request'] = round( $c['ms'] / max( 1, $c['requests'] ), 1 );
			$c['ms']             = round( $c['ms'], 1 );
		}
		unset( $c );
		usort(
			$comps,
			function ( $a, $b ) {
				return $b['ms_per_request'] <=> $a['ms_per_request'];
			}
		);

		// Tür başına süre: ziyaretçinin beklediği (front) ile arka plan işleri (cron, ajax, rest) ayrı okunmalı.
		$type_stats = array();
		foreach ( $by_type as $ty => $list ) {
			sort( $list );
			$c                 = count( $list );
			$type_stats[ $ty ] = array(
				'count'  => $c,
				'ms_avg' => (int) round( array_sum( $list ) / max( 1, $c ) ),
				'ms_p50' => $list[ (int) floor( 0.5 * ( $c - 1 ) ) ],
				'ms_p95' => $list[ (int) floor( 0.95 * ( $c - 1 ) ) ],
				'ms_max' => $list[ $c - 1 ],
			);
		}

		return array(
			'hours'          => (int) $hours,
			'note'           => 'Only slow (>= slow_ms), erroneous and randomly sampled (sample %) PHP requests are recorded; page-cache hits (LiteSpeed) never reach PHP. So these numbers describe the problematic requests, not all traffic: do not report "slow/recorded" as the share of slow pages. Use by_type: "front" is what visitors wait for; cron, ajax (admin-ajax, incl. Heartbeat and background loopbacks such as flxy_tick) and rest are mostly background work. cron_hooks shows which scheduled task takes how long.',
			'recorded'       => $n,
			'by_type'        => $type_stats,
			'cron_hooks'     => class_exists( 'FLXP_Cron_Timing' ) ? array_slice( FLXP_Cron_Timing::stats(), 0, 10 ) : array(),
			'ms_avg'         => $n ? (int) round( array_sum( $ms ) / $n ) : 0,
			'ms_p50'         => $pct( 0.5 ),
			'ms_p95'         => $pct( 0.95 ),
			'ms_max'         => $n ? $ms[ $n - 1 ] : 0,
			'flags'          => $flags,
			'types'          => $types,
			'slowest_urls'   => array_slice( array_values( $urls ), 0, 10 ),
			'db_components'  => array_slice( $comps, 0, 10 ),
			'settings'       => array(
				'slow_ms'       => (int) flxp_settings( 'slow_ms' ),
				'slow_query_ms' => (int) flxp_settings( 'slow_query_ms' ),
				'sample'        => (int) flxp_settings( 'sample' ),
			),
		);
	}

	/** Özet gruplaması için adres: sorgu dizesi atılır, admin-ajax'ta action (ve Heartbeat verisi) kalır. */
	public static function url_group( $url ) {
		$path = strtok( (string) $url, '?' );
		if ( false === strpos( $path, 'admin-ajax.php' ) ) {
			return $path;
		}
		$q = array();
		parse_str( (string) wp_parse_url( (string) $url, PHP_URL_QUERY ), $q );
		$keep = array_intersect_key( $q, array( 'action' => 1, 'data' => 1 ) );
		return $keep ? $path . '?' . str_replace( '%2C', ',', http_build_query( $keep, '', '&' ) ) : $path;
	}

	/** Yavaş / hatalı sorgular: normalize edilip gruplanır. */
	public static function slow_queries( $hours = 24, $limit = 20 ) {
		$slow_ms = (float) flxp_settings( 'slow_query_ms' );
		$groups  = array();
		self::each_data(
			$hours,
			function ( $d, $r ) use ( &$groups, $slow_ms ) {
				foreach ( $d['db']['queries'] ?? array() as $q ) {
					if ( $q['ms'] < $slow_ms && empty( $q['error'] ) ) {
						continue;
					}
					$k = md5( flxp_normalize_sql( $q['sql'] ) );
					if ( ! isset( $groups[ $k ] ) ) {
						$groups[ $k ] = array(
							'sql'       => flxp_cut( flxp_normalize_sql( $q['sql'] ), 800 ),
							'example'   => flxp_cut( $q['sql'], 800 ),
							'component' => flxp_component_label( $q['comp'] ),
							'caller'    => $q['caller'],
							'count'     => 0,
							'ms_max'    => 0,
							'ms_sum'    => 0,
							'error'     => '',
							'last_url'  => '',
							'last_id'   => 0,
						);
					}
					$g = &$groups[ $k ];
					$g['count']++;
					$g['ms_sum'] += $q['ms'];
					$g['ms_max']  = max( $g['ms_max'], $q['ms'] );
					if ( ! empty( $q['error'] ) ) {
						$g['error'] = $q['error'];
					}
					if ( ! $g['last_id'] ) {
						$g['last_id']  = (int) $r['id'];
						$g['last_url'] = $r['url'];
					}
					unset( $g );
				}
			}
		);
		foreach ( $groups as &$g ) {
			$g['ms_avg'] = round( $g['ms_sum'] / max( 1, $g['count'] ), 2 );
			unset( $g['ms_sum'] );
		}
		unset( $g );
		usort(
			$groups,
			function ( $a, $b ) {
				return ( $b['ms_avg'] * $b['count'] ) <=> ( $a['ms_avg'] * $a['count'] );
			}
		);
		return array_slice( array_values( $groups ), 0, max( 1, (int) $limit ) );
	}

	/** PHP hataları ve doing-it-wrong: mesaja göre gruplanır. */
	public static function php_errors( $hours = 24, $limit = 30, $min_level = 'warning' ) {
		$rank   = array( 'deprecated' => 0, 'notice' => 1, 'warning' => 2, 'fatal' => 3 );
		$min    = $rank[ $min_level ] ?? 2;
		$groups = array();
		self::each_data(
			$hours,
			function ( $d, $r ) use ( &$groups, $rank, $min ) {
				foreach ( $d['php'] ?? array() as $e ) {
					if ( ( $rank[ $e['level'] ] ?? 0 ) < $min ) {
						continue;
					}
					$k = md5( $e['level'] . $e['file'] . $e['line'] . $e['message'] );
					if ( ! isset( $groups[ $k ] ) ) {
						$groups[ $k ] = array(
							'level'     => $e['level'],
							'message'   => $e['message'],
							'file'      => $e['file'] . ':' . $e['line'],
							'component' => flxp_component_label( $e['comp'] ),
							'stack'     => array_slice( (array) $e['stack'], 0, 8 ),
							'count'     => 0,
							'requests'  => 0,
							'last'      => $r['created'],
							'last_url'  => $r['url'],
							'last_id'   => (int) $r['id'],
						);
					}
					$groups[ $k ]['count']    += (int) $e['count'];
					$groups[ $k ]['requests'] += 1;
				}
			}
		);
		usort(
			$groups,
			function ( $a, $b ) use ( $rank ) {
				return array( $rank[ $b['level'] ] ?? 0, $b['requests'] ) <=> array( $rank[ $a['level'] ] ?? 0, $a['requests'] );
			}
		);
		return array_slice( array_values( $groups ), 0, max( 1, (int) $limit ) );
	}

	/** Dış HTTP istekleri: hosta göre gruplanır. */
	public static function http_calls( $hours = 24, $failed_only = false, $limit = 30 ) {
		$groups = array();
		self::each_data(
			$hours,
			function ( $d, $r ) use ( &$groups, $failed_only ) {
				foreach ( $d['http'] ?? array() as $h ) {
					$fail = $h['error'] || $h['status'] >= 400;
					if ( $failed_only && ! $fail ) {
						continue;
					}
					$p    = wp_parse_url( $h['url'] );
					$host = ( $p['host'] ?? '?' ) . ( isset( $p['path'] ) ? strtok( $p['path'], '?' ) : '' );
					$k    = $h['method'] . ' ' . $host;
					if ( ! isset( $groups[ $k ] ) ) {
						$groups[ $k ] = array( 'endpoint' => $k, 'component' => flxp_component_label( $h['comp'] ), 'count' => 0, 'failed' => 0, 'ms_sum' => 0, 'ms_max' => 0, 'last_error' => '', 'last_status' => 0, 'last_id' => 0 );
					}
					$g = &$groups[ $k ];
					$g['count']++;
					$g['ms_sum'] += $h['ms'];
					$g['ms_max']  = max( $g['ms_max'], $h['ms'] );
					if ( $fail ) {
						$g['failed']++;
						if ( ! $g['last_error'] ) {
							$g['last_error']  = $h['error'] ? $h['error'] : 'HTTP ' . $h['status'];
						}
					}
					if ( ! $g['last_id'] ) {
						$g['last_id']     = (int) $r['id'];
						$g['last_status'] = (int) $h['status'];
					}
					unset( $g );
				}
			}
		);
		foreach ( $groups as &$g ) {
			$g['ms_avg'] = round( $g['ms_sum'] / max( 1, $g['count'] ), 1 );
			unset( $g['ms_sum'] );
		}
		unset( $g );
		usort(
			$groups,
			function ( $a, $b ) {
				return array( $b['failed'], $b['ms_avg'] ) <=> array( $a['failed'], $a['ms_avg'] );
			}
		);
		return array_slice( array_values( $groups ), 0, max( 1, (int) $limit ) );
	}

	/** Tablodaki satır sayısı. */
	public static function count( $hours = 0 ) {
		global $wpdb;
		$t = flxp_table();
		if ( $hours ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE created >= %s", gmdate( 'Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS ) ) ); // phpcs:ignore
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" ); // phpcs:ignore
	}

	/** Bayrağa göre son X saatteki sayı. */
	public static function count_flag( $flag, $hours = 24 ) {
		global $wpdb;
		$t = flxp_table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE created >= %s AND FIND_IN_SET(%s, flags)", gmdate( 'Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS ), $flag ) ); // phpcs:ignore
	}
}

/**
 * Bir sayfayı ziyaretçi gözüyle ölçer: tek kullanımlık jetonla kendi sitesine GET atar,
 * o istek ayrıntılı toplanıp "profile" bayrağıyla kaydedilir.
 *
 * @param string $url Sitedeki adres (yol ya da tam adres; başka alan adı reddedilir).
 * @return array|WP_Error
 */
function flxp_profile_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		$url = home_url( '/' );
	} elseif ( 0 === strpos( $url, '/' ) ) {
		$url = home_url( $url );
	}
	$home = wp_parse_url( home_url() );
	$p    = wp_parse_url( $url );
	if ( empty( $p['host'] ) || strtolower( $p['host'] ) !== strtolower( $home['host'] ) ) {
		return new WP_Error( 'flxp_host', __( 'Only URLs on this site can be profiled.', 'flx-speed' ) );
	}
	if ( (int) get_option( 'flxp_db_version', 0 ) < 1 ) {
		return new WP_Error( 'flxp_table', __( 'The records table is not installed.', 'flx-speed' ) );
	}
	global $wpdb;
	$before = (int) $wpdb->get_var( 'SELECT MAX(id) FROM ' . flxp_table() ); // phpcs:ignore
	$token  = wp_generate_password( 32, false );
	set_transient( 'flxp_prof_' . md5( $token ), 1, 2 * MINUTE_IN_SECONDS );
	// Sayfa önbelleğini (LiteSpeed) atlamak için benzersiz parametre.
	$target = add_query_arg( 'flxp_profile', substr( md5( $token ), 0, 8 ), $url );
	$res    = wp_remote_get(
		$target,
		array(
			'timeout'     => 45,
			'redirection' => 0,
			'headers'     => array( 'X-FLXIZ-Profile' => $token ),
			'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
		)
	);
	delete_transient( 'flxp_prof_' . md5( $token ) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . flxp_table() . " WHERE id > %d AND FIND_IN_SET('profile', flags) ORDER BY id DESC LIMIT 1", $before ) ); // phpcs:ignore
	if ( ! $id ) {
		return new WP_Error( 'flxp_no_record', __( 'The page loaded but no record was created. It may have been served from cache, or Flux Performance did not load on that request.', 'flx-speed' ), array( 'status' => wp_remote_retrieve_response_code( $res ) ) );
	}
	return array(
		'id'     => $id,
		'status' => (int) wp_remote_retrieve_response_code( $res ),
		'url'    => $url,
	);
}
