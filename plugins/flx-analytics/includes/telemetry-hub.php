<?php
/**
 * Telemetri merkezi: FluxWP Panel (flx-setup 1.0.11+) kurulumlarından gelen anonim kullanım verisini toplar.
 * Ayarlar › Telemetri merkezi açıkken POST /wp-json/flxan/v1/telemetry herkese açılır ve sol menüde
 * "Ürün telemetrisi" görünür. Kapalıyken uç 404 döner.
 *
 * Saklanmaz: IP, site adresi, User-Agent, çerez. Kurulumun rastgele kimliği bile sha256 özetiyle tutulur.
 * Saklama 90 gün (son görülmeden itibaren).
 *
 * Tablolar: tm_installs (kurulum başına son durum + son sihirbaz çalıştırması), tm_errors (hata başına),
 * tm_esites (hata × kurulum, tekil site sayısı için), tm_days (günlük rapor ve hata olayı).
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

class FLXAN_Telemetry_Hub {

	const DB_VERSION   = 3;
	const SLUG         = 'flx-analytics-telemetry';
	const KEEP_DAYS    = 90;
	const MAX_BODY     = 131072;
	const MIN_INTERVAL = 3600; // Aynı kurulumdan saatte en fazla bir rapor işlenir.
	const MAX_ERRORS   = 60;
	const TYPES        = array( 'E_ERROR', 'E_WARNING', 'E_PARSE', 'E_NOTICE', 'E_CORE_ERROR', 'E_CORE_WARNING', 'E_COMPILE_ERROR', 'E_COMPILE_WARNING', 'E_USER_ERROR', 'E_USER_WARNING', 'E_USER_NOTICE', 'E_RECOVERABLE_ERROR', 'E_DEPRECATED', 'E_USER_DEPRECATED', 'E_UNKNOWN' );
	const FATAL        = array( 'E_ERROR', 'E_PARSE', 'E_CORE_ERROR', 'E_COMPILE_ERROR', 'E_USER_ERROR', 'E_RECOVERABLE_ERROR' );

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		if ( ! self::on() ) {
			return;
		}
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_head', array( __CLASS__, 'menu_icon' ) );
		add_action( 'flxan_daily', array( __CLASS__, 'prune' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
	}

	public static function on() {
		return ! empty( flxan_settings( 'tm_hub' ) );
	}

	/** Bu sitenin uç adresi (FluxWP Panel › Ayarlar › Telemetri adresi alanına yazılır). */
	public static function endpoint() {
		return rest_url( 'flxan/v1/telemetry' );
	}

	public static function t( $name ) {
		return flxan_table( 'tm_' . $name );
	}

	/* ------------------------------------------------------------------ */
	/* Tablolar                                                            */
	/* ------------------------------------------------------------------ */

	public static function maybe_install() {
		if ( (int) get_option( 'flxan_tm_db', 0 ) >= self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		dbDelta(
			'CREATE TABLE ' . self::t( 'installs' ) . " (
				iid char(20) NOT NULL,
				first_ts int(10) unsigned NOT NULL DEFAULT 0,
				last_ts int(10) unsigned NOT NULL DEFAULT 0,
				n int(10) unsigned NOT NULL DEFAULT 0,
				wp varchar(20) NOT NULL DEFAULT '',
				php varchar(8) NOT NULL DEFAULT '',
				loc varchar(16) NOT NULL DEFAULT '',
				cc char(2) NOT NULL DEFAULT '',
				ms tinyint(1) unsigned NOT NULL DEFAULT 0,
				panel varchar(32) NOT NULL DEFAULT '',
				pk text NOT NULL,
				ob text NOT NULL,
				app_usage text NULL,
				ob_ts int(10) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (iid),
				KEY last_ts (last_ts),
				KEY cc (cc)
			) {$c};
			CREATE TABLE " . self::t( 'errors' ) . " (
				h char(16) NOT NULL,
				p varchar(64) NOT NULL DEFAULT '',
				k varchar(8) NOT NULL DEFAULT 'plugin',
				v varchar(32) NOT NULL DEFAULT '',
				t varchar(24) NOT NULL DEFAULT '',
				m text NOT NULL,
				f varchar(200) NOT NULL DEFAULT '',
				l int(10) unsigned NOT NULL DEFAULT 0,
				n bigint(20) unsigned NOT NULL DEFAULT 0,
				sites int(10) unsigned NOT NULL DEFAULT 0,
				first_ts int(10) unsigned NOT NULL DEFAULT 0,
				last_ts int(10) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (h),
				KEY last_ts (last_ts),
				KEY p (p)
			) {$c};
			CREATE TABLE " . self::t( 'esites' ) . " (
				h char(16) NOT NULL,
				iid char(20) NOT NULL,
				PRIMARY KEY  (h,iid)
			) {$c};
			CREATE TABLE " . self::t( 'days' ) . " (
				d date NOT NULL,
				r int(10) unsigned NOT NULL DEFAULT 0,
				e bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (d)
			) {$c};"
		);
		update_option( 'flxan_tm_db', self::DB_VERSION, false );
	}

	/* ------------------------------------------------------------------ */
	/* REST                                                                */
	/* ------------------------------------------------------------------ */

	public static function routes() {
		register_rest_route(
			'flxan/v1',
			'/telemetry',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'receive' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'ping' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/** GET: merkez açık mı (FluxWP Panel adres testi). */
	public static function ping() {
		if ( ! self::on() ) {
			return new WP_Error( 'flxan_tm_off', 'Not found', array( 'status' => 404 ) );
		}
		return array( 'ok' => true, 'service' => 'flx-telemetry', 'v' => 1 );
	}

	public static function receive( WP_REST_Request $req ) {
		if ( ! self::on() ) {
			return new WP_Error( 'flxan_tm_off', 'Not found', array( 'status' => 404 ) );
		}
		$raw = (string) $req->get_body();
		if ( strlen( $raw ) > self::MAX_BODY ) {
			return new WP_Error( 'flxan_tm_size', 'Payload too large', array( 'status' => 413 ) );
		}
		$b = json_decode( $raw, true );
		if ( ! is_array( $b ) || 1 !== (int) ( $b['v'] ?? 0 ) || ! preg_match( '/^[a-f0-9]{32}$/', (string) ( $b['id'] ?? '' ) ) ) {
			return new WP_Error( 'flxan_tm_bad', 'Invalid payload', array( 'status' => 400 ) );
		}
		self::maybe_install();
		$status = self::record( $b );
		if ( 'refused' === $status ) {
			return new WP_Error( 'flxan_tm_cap', 'Too many reports', array( 'status' => 429 ) );
		}
		$res    = new WP_REST_Response( array( 'ok' => true, 'status' => $status ) );
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	/**
	 * New install ids: at most 60 per hour, and at most 5000 rows.
	 * The counter lives in one option (`flxan_tm_newcap`); the window id is inside the value.
	 * A full table drops its 200 least recently seen rows first (ids that reported once and never again go before active installs).
	 * Returns false when the new id must not be stored.
	 */
	private static function allow_new_install() {
		global $wpdb;
		$window = 3600;
		$cap    = 60;
		$max    = 5000;
		$state  = get_option( 'flxan_tm_newcap', null );
		$missing = null === $state;
		if ( ! is_array( $state ) ) {
			$state = array();
		}
		$win = intdiv( time(), $window );
		if ( (int) ( $state['win'] ?? -1 ) !== $win ) {
			$state = array( 'win' => $win, 'n' => 0 );
		}
		if ( (int) $state['n'] >= $cap ) {
			return false;
		}
		$table = self::t( 'installs' );
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( $count >= $max ) {
			$wpdb->query( "DELETE FROM {$table} ORDER BY last_ts ASC LIMIT 200" ); // phpcs:ignore WordPress.DB.PreparedSQL
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
			if ( $count >= $max ) {
				return false;
			}
		}
		$state['n'] = (int) $state['n'] + 1;
		if ( $missing ) {
			add_option( 'flxan_tm_newcap', $state, '', false );
		} else {
			update_option( 'flxan_tm_newcap', $state, false );
		}
		return true;
	}

	/** Doğrulanmış gövdeyi kaydeder. Döner: ok | throttled | refused. */
	public static function record( array $b ) {
		global $wpdb;
		$now = time();
		$iid = substr( hash( 'sha256', 'flx-telemetry|' . $b['id'] ), 0, 20 );
		$ti  = self::t( 'installs' );
		$cur = $wpdb->get_row( $wpdb->prepare( "SELECT first_ts, last_ts, n, ob, ob_ts FROM {$ti} WHERE iid = %s", $iid ), ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( $cur && $now - (int) $cur['last_ts'] < self::MIN_INTERVAL ) {
			return 'throttled';
		}
		// A new client id does not get a row when the hourly cap or the table cap is full.
		if ( ! $cur && ! self::allow_new_install() ) {
			return 'refused';
		}
		$pk = array_merge( self::pkgs( $b['plugins'] ?? array(), 80 ), self::pkgs( $b['themes'] ?? array(), 4, 'theme:' ) );
		$ob = isset( $b['onboarding'] ) && is_array( $b['onboarding'] ) ? self::funnel( $b['onboarding'] ) : null;
		$row = array(
			'iid'      => $iid,
			'first_ts' => $cur ? (int) $cur['first_ts'] : $now,
			'last_ts'  => $now,
			'n'        => $cur ? (int) $cur['n'] + 1 : 1,
			'wp'       => self::ver( $b['wp'] ?? '' ),
			'php'      => preg_match( '/^\d{1,2}\.\d{1,2}$/', (string) ( $b['php'] ?? '' ) ) ? (string) $b['php'] : '',
			'loc'      => preg_match( '/^[A-Za-z]{2,3}(_[A-Za-z0-9]{2,8}){0,2}$/', (string) ( $b['locale'] ?? '' ) ) ? (string) $b['locale'] : '',
			'cc'       => self::cc( $b['cc'] ?? '' ),
			'ms'       => empty( $b['multisite'] ) ? 0 : 1,
			'panel'    => self::ver( $b['panel'] ?? '' ),
			'pk'       => wp_json_encode( $pk ),
			'ob'       => $ob ? wp_json_encode( $ob ) : (string) ( $cur['ob'] ?? '' ),
			'app_usage' => wp_json_encode( self::app_usage( $b['app_usage'] ?? array() ) ),
			'ob_ts'    => $ob ? $now : (int) ( $cur['ob_ts'] ?? 0 ),
		);
		$wpdb->replace( $ti, $row ); // phpcs:ignore WordPress.DB

		$events = 0;
		$te     = self::t( 'errors' );
		$ts     = self::t( 'esites' );
		$seen   = array();
		foreach ( array_slice( (array) ( $b['errors'] ?? array() ), 0, self::MAX_ERRORS ) as $e ) {
			$e = self::error( $e );
			if ( ! $e || isset( $seen[ $e['h'] ] ) ) {
				continue;
			}
			$seen[ $e['h'] ] = 1;
			$events         += $e['n'];
			// phpcs:disable WordPress.DB
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$te} (h, p, k, v, t, m, f, l, n, sites, first_ts, last_ts) VALUES (%s, %s, %s, %s, %s, %s, %s, %d, %d, 0, %d, %d)
					ON DUPLICATE KEY UPDATE n = n + VALUES(n), m = VALUES(m), last_ts = VALUES(last_ts)",
					$e['h'],
					$e['p'],
					$e['k'],
					$e['v'],
					$e['t'],
					$e['m'],
					$e['f'],
					$e['l'],
					$e['n'],
					$now,
					$now
				)
			);
			if ( $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$ts} (h, iid) VALUES (%s, %s)", $e['h'], $iid ) ) ) {
				$wpdb->query( $wpdb->prepare( "UPDATE {$te} SET sites = sites + 1 WHERE h = %s", $e['h'] ) );
			}
			// phpcs:enable
		}
		$day = gmdate( 'Y-m-d', $now );
		$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::t( 'days' ) . ' (d, r, e) VALUES (%s, 1, %d) ON DUPLICATE KEY UPDATE r = r + 1, e = e + VALUES(e)', $day, $events ) ); // phpcs:ignore WordPress.DB
		delete_transient( 'flxan_tm_sum' );
		return 'ok';
	}

	/** Strict aggregate schema; unknown keys, current/future days and small cohorts are discarded. */
	public static function app_usage( $input ) {
		$out = array();
		if ( ! is_array( $input ) ) {
			return $out;
		}
		for ( $i = 1; $i <= 7; ++$i ) {
			$day = gmdate( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
			if ( ! isset( $input[ $day ] ) || ! is_array( $input[ $day ] ) ) {
				continue;
			}
			foreach ( array( 'ios', 'android' ) as $platform ) {
				$n = $input[ $day ][ $platform ] ?? null;
				if ( is_int( $n ) && $n >= 5 && $n <= 1000000 ) {
					$out[ $day ][ $platform ] = $n;
				}
			}
		}
		return $out;
	}

	/** Ülke kodu (gönderen site ayarlarından; IP'ye bakılmaz). Geçersizse boş. */
	public static function cc( $v ) {
		$v = strtoupper( (string) $v );
		return preg_match( '/^[A-Z]{2}$/', $v ) && ! in_array( $v, array( 'XX', 'ZZ' ), true ) ? $v : '';
	}

	/** Ülke adı (site dilinde). */
	public static function cc_name( $cc ) {
		if ( '' === $cc || '?' === $cc ) {
			return __( 'Unknown', 'flx-analytics' );
		}
		return function_exists( 'flxan_dim_label' ) ? (string) flxan_dim_label( 'country', $cc ) : $cc;
	}

	/** Aralıkta rapor gönderen ülkeler (süzgeç listesi). */
	public static function countries( $days ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT cc, COUNT(*) n FROM ' . self::t( 'installs' ) . " WHERE last_ts >= %d AND cc <> '' GROUP BY cc ORDER BY n DESC", time() - $days * DAY_IN_SECONDS ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	private static function ver( $v ) {
		$v = is_scalar( $v ) ? (string) $v : '';
		return preg_match( '/^[0-9][0-9A-Za-z.+-]{0,31}$/', $v ) ? $v : '';
	}

	private static function pkgs( $list, $max, $prefix = '' ) {
		$out = array();
		foreach ( is_array( $list ) ? $list : array() as $slug => $ver ) {
			if ( is_string( $slug ) && preg_match( '/^[a-z0-9][a-z0-9-]{0,63}$/', $slug ) ) {
				$out[ $prefix . $slug ] = self::ver( $ver );
			}
			if ( count( $out ) >= $max ) {
				break;
			}
		}
		ksort( $out );
		return $out;
	}

	private static function keys( $list, $max = 60 ) {
		$out = array();
		foreach ( array_slice( is_array( $list ) ? $list : array(), 0, $max ) as $k ) {
			if ( is_string( $k ) && preg_match( '/^[a-z0-9][a-z0-9:_-]{0,63}$/', $k ) ) {
				$out[] = $k;
			}
		}
		return array_values( array_unique( $out ) );
	}

	private static function funnel( array $in ) {
		$one = function ( $v ) {
			return is_string( $v ) && preg_match( '/^[a-z0-9][a-z0-9:_.-]{0,63}$/i', $v ) ? $v : '';
		};
		return array(
			'out'     => in_array( $in['out'] ?? '', array( 'done', 'skip', 'left' ), true ) ? $in['out'] : 'left',
			'max'     => max( 0, min( 50, (int) ( $in['max'] ?? 0 ) ) ),
			'total'   => max( 0, min( 50, (int) ( $in['total'] ?? 0 ) ) ),
			'at'      => $one( $in['at'] ?? '' ),
			'path'    => self::keys( $in['path'] ?? array(), 30 ),
			'picked'  => self::keys( $in['picked'] ?? array() ),
			'dropped' => self::keys( $in['dropped'] ?? array() ),
			'rec_off' => self::keys( $in['rec_off'] ?? array() ),
			'preset'  => $one( $in['preset'] ?? '' ),
			'style'   => $one( $in['style'] ?? '' ),
			'secs'    => max( 0, min( 86400, (int) ( $in['secs'] ?? 0 ) ) ),
			'runs'    => max( 1, min( 1000, (int) ( $in['runs'] ?? 1 ) ) ),
		);
	}

	private static function error( $e ) {
		if ( ! is_array( $e ) ) {
			return null;
		}
		$p = (string) ( $e['p'] ?? '' );
		$t = (string) ( $e['t'] ?? '' );
		if ( ! preg_match( '/^[a-z0-9][a-z0-9-]{0,63}$/', $p ) || ! in_array( $t, self::TYPES, true ) ) {
			return null;
		}
		$f = (string) ( $e['f'] ?? '' );
		$f = preg_match( '#^[A-Za-z0-9._/-]{1,200}$#', $f ) && false === strpos( $f, '..' ) ? $f : '';
		$m = self::scrub( (string) ( $e['m'] ?? '' ) );
		$l = max( 0, min( 1000000, (int) ( $e['l'] ?? 0 ) ) );
		$v = self::ver( $e['v'] ?? '' );
		return array(
			'h' => substr( hash( 'sha256', $p . '|' . $v . '|' . $t . '|' . $f . '|' . $l . '|' . preg_replace( '/\d+/', '0', $m ) ), 0, 16 ),
			'p' => $p,
			'k' => 'theme' === ( $e['k'] ?? '' ) ? 'theme' : 'plugin',
			'v' => $v,
			't' => $t,
			'm' => $m,
			'f' => $f,
			'l' => $l,
			'n' => max( 1, min( 100000, (int) ( $e['n'] ?? 1 ) ) ),
		);
	}

	/** Sunucu tarafında ikinci temizlik (eski ya da hatalı istemcilere karşı). */
	public static function scrub( $m ) {
		$m = preg_replace(
			array(
				'#\b[a-z][a-z0-9+.-]*://\S+#i',
				'#[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}#i',
				'#\b(?:\d{1,3}\.){3}\d{1,3}\b#',
				'#\b(?:[0-9a-f]{1,4}:){3,7}[0-9a-f]{1,4}\b#i',
				'#(?<![\w{}])(?:/|[A-Za-z]:[/\\\\])(?:[^\s/\\\\:\'"()]+[/\\\\])+#',
				'#\d{6,}#',
			),
			array( '{url}', '{email}', '{ip}', '{ip}', '{path}/', '{n}' ),
			(string) $m
		);
		$m = (string) preg_replace( '/[^\P{C}\n\t]/u', '', (string) $m );
		return mb_substr( $m, 0, 1500 );
	}

	/** 90 günden eski kayıtları siler (flxan_daily). */
	public static function prune() {
		if ( (int) get_option( 'flxan_tm_db', 0 ) < 1 ) {
			return;
		}
		global $wpdb;
		$cut = time() - self::KEEP_DAYS * DAY_IN_SECONDS;
		// phpcs:disable WordPress.DB
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::t( 'installs' ) . ' WHERE last_ts < %d', $cut ) );
		$wpdb->query( $wpdb->prepare( 'DELETE s FROM ' . self::t( 'esites' ) . ' s JOIN ' . self::t( 'errors' ) . ' e ON e.h = s.h WHERE e.last_ts < %d', $cut ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::t( 'errors' ) . ' WHERE last_ts < %d', $cut ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::t( 'days' ) . ' WHERE d < %s', gmdate( 'Y-m-d', $cut ) ) );
		// phpcs:enable
	}

	/* ------------------------------------------------------------------ */
	/* Özet                                                                */
	/* ------------------------------------------------------------------ */

	public static function summary( $days, $cc = '' ) {
		$key  = 'flxan_tm_sum';
		$slot = $days . '|' . $cc;
		$all  = get_transient( $key );
		if ( is_array( $all ) && isset( $all[ $slot ] ) ) {
			return $all[ $slot ];
		}
		global $wpdb;
		$cut  = time() - $days * DAY_IN_SECONDS;
		$sql  = 'SELECT wp, php, loc, cc, pk, ob, ob_ts, app_usage FROM ' . self::t( 'installs' ) . ' WHERE last_ts >= %d';
		$rows = '' !== $cc ? $wpdb->get_results( $wpdb->prepare( $sql . ' AND cc = %s', $cut, $cc ), ARRAY_A ) : $wpdb->get_results( $wpdb->prepare( $sql, $cut ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$S    = array(
			'installs' => count( $rows ),
			'usage'    => array(),
			'versions' => array(),
			'pairs'    => array(),
			'combos'   => array(),
			'php'      => array(),
			'wp'       => array(),
			'locale'   => array(),
			'country'  => array(),
			'app_usage' => array(),
			'ob'       => array(
				'runs'    => 0,
				'out'     => array( 'done' => 0, 'skip' => 0, 'left' => 0 ),
				'reach'   => array(),
				'stop'    => array(),
				'picked'  => array(),
				'dropped' => array(),
				'rec_off' => array(),
				'preset'  => array(),
				'style'   => array(),
				'secs'    => array(),
			),
		);
		$inc = function ( &$arr, $k, $n = 1 ) {
			$arr[ $k ] = ( $arr[ $k ] ?? 0 ) + $n;
		};
		foreach ( $rows as $r ) {
			foreach ( self::app_usage( json_decode( (string) ( $r['app_usage'] ?? '' ), true ) ) as $day => $platforms ) {
				foreach ( $platforms as $platform => $count ) {
					$S['app_usage'][ $day ][ $platform ] = ( $S['app_usage'][ $day ][ $platform ] ?? 0 ) + $count;
				}
			}
			$pk = json_decode( (string) $r['pk'], true );
			$pk = is_array( $pk ) ? $pk : array();
			unset( $pk['flx-setup'] ); // Her sitede var; kombinasyonlarda gürültü.
			$slugs = array_keys( $pk );
			sort( $slugs );
			foreach ( $pk as $s => $v ) {
				$inc( $S['usage'], $s );
				$inc( $S['versions'][ $s ], '' !== $v ? $v : '?' );
			}
			$n = count( $slugs );
			for ( $i = 0; $i < $n; $i++ ) {
				for ( $j = $i + 1; $j < $n; $j++ ) {
					$inc( $S['pairs'], $slugs[ $i ] . '|' . $slugs[ $j ] );
				}
			}
			$inc( $S['combos'], implode( '|', $slugs ) );
			$inc( $S['php'], '' !== $r['php'] ? $r['php'] : '?' );
			$inc( $S['wp'], preg_match( '/^(\d+\.\d+)/', (string) $r['wp'], $m ) ? $m[1] : '?' );
			$inc( $S['locale'], '' !== $r['loc'] ? $r['loc'] : '?' );
			$inc( $S['country'], '' !== $r['cc'] ? $r['cc'] : '?' );

			$ob = json_decode( (string) $r['ob'], true );
			if ( is_array( $ob ) && (int) $r['ob_ts'] >= $cut ) {
				++$S['ob']['runs'];
				$inc( $S['ob']['out'], $ob['out'] ?? 'left' );
				foreach ( array_unique( (array) ( $ob['path'] ?? array() ) ) as $st ) {
					$inc( $S['ob']['reach'], $st );
				}
				if ( 'done' !== ( $ob['out'] ?? '' ) && '' !== (string) ( $ob['at'] ?? '' ) ) {
					$inc( $S['ob']['stop'], $ob['at'] . '|' . ( $ob['out'] ?? 'left' ) );
				}
				foreach ( array( 'picked', 'dropped', 'rec_off' ) as $k ) {
					foreach ( (array) ( $ob[ $k ] ?? array() ) as $s ) {
						$inc( $S['ob'][ $k ], $s );
					}
				}
				if ( 'done' === ( $ob['out'] ?? '' ) ) {
					if ( ! empty( $ob['preset'] ) ) {
						$inc( $S['ob']['preset'], $ob['preset'] );
					}
					if ( ! empty( $ob['style'] ) ) {
						$inc( $S['ob']['style'], $ob['style'] );
					}
					$S['ob']['secs'][] = (int) ( $ob['secs'] ?? 0 );
				}
			}
		}
		foreach ( array( 'usage', 'pairs', 'combos', 'php', 'wp', 'locale', 'country' ) as $k ) {
			arsort( $S[ $k ] );
		}
		foreach ( array( 'reach', 'stop', 'picked', 'dropped', 'rec_off', 'preset', 'style' ) as $k ) {
			arsort( $S['ob'][ $k ] );
		}
		foreach ( $S['versions'] as &$v ) {
			arsort( $v );
		}
		unset( $v );
		sort( $S['ob']['secs'] );
		$c                    = count( $S['ob']['secs'] );
		$S['ob']['median']    = $c ? $S['ob']['secs'][ (int) floor( ( $c - 1 ) / 2 ) ] : 0;
		unset( $S['ob']['secs'] );
		$S['pairs']           = array_slice( $S['pairs'], 0, 15, true );
		$S['combos']          = array_slice( $S['combos'], 0, 10, true );
		$S['locale']          = array_slice( $S['locale'], 0, 8, true );
		$S['reports']         = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(r),0) FROM ' . self::t( 'days' ) . ' WHERE d >= %s', gmdate( 'Y-m-d', $cut ) ) ); // phpcs:ignore WordPress.DB
		$S['events']          = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(e),0) FROM ' . self::t( 'days' ) . ' WHERE d >= %s', gmdate( 'Y-m-d', $cut ) ) ); // phpcs:ignore WordPress.DB
		$in_cc                = self::cc_where( $cc );
		if ( '' !== $cc ) {
			// Günlük toplamlar ülkesiz tutulur; ülke seçiliyken kurulum ve hata kayıtlarından sayılır.
			$S['reports'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(n),0) FROM ' . self::t( 'installs' ) . ' WHERE last_ts >= %d AND cc = %s', $cut, $cc ) ); // phpcs:ignore WordPress.DB
			$S['events']  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(e.n),0) FROM ' . self::t( 'errors' ) . ' e WHERE e.last_ts >= %d', $cut ) . $in_cc ); // phpcs:ignore WordPress.DB
		}
		$S['errors_open']     = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t( 'errors' ) . ' e WHERE e.last_ts >= %d', $cut ) . $in_cc ); // phpcs:ignore WordPress.DB
		$S['fatal_open']      = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t( 'errors' ) . " e WHERE e.last_ts >= %d AND e.t IN ('" . implode( "','", self::FATAL ) . "')", $cut ) . $in_cc ); // phpcs:ignore WordPress.DB
		$all                  = is_array( $all ) ? $all : array();
		$all[ $slot ]         = $S;
		set_transient( $key, $all, 10 * MINUTE_IN_SECONDS );
		return $S;
	}

	/** Hata sorgusuna ülke süzgeci: o ülkeden en az bir kurulumda görülen hatalar (e = errors takma adı). */
	private static function cc_where( $cc ) {
		if ( '' === $cc ) {
			return '';
		}
		global $wpdb;
		return $wpdb->prepare( ' AND EXISTS (SELECT 1 FROM ' . self::t( 'esites' ) . ' s JOIN ' . self::t( 'installs' ) . ' i ON i.iid = s.iid WHERE s.h = e.h AND i.cc = %s)', $cc ); // phpcs:ignore WordPress.DB
	}

	public static function errors( $days, $plugin = '', $type = '', $cc = '' ) {
		global $wpdb;
		$where = $wpdb->prepare( 'e.last_ts >= %d', time() - $days * DAY_IN_SECONDS );
		if ( '' !== $plugin ) {
			$where .= $wpdb->prepare( ' AND e.p = %s', $plugin );
		}
		if ( 'fatal' === $type ) {
			$where .= " AND e.t IN ('" . implode( "','", self::FATAL ) . "')";
		} elseif ( in_array( $type, self::TYPES, true ) ) {
			$where .= $wpdb->prepare( ' AND e.t = %s', $type );
		}
		$where .= self::cc_where( $cc );
		$sites  = 'e.sites';
		if ( '' !== $cc ) {
			// Ülke seçiliyken "Site": o ülkeden kaç kurulumda görüldüğü.
			$sites = $wpdb->prepare( '(SELECT COUNT(*) FROM ' . self::t( 'esites' ) . ' s JOIN ' . self::t( 'installs' ) . ' i ON i.iid = s.iid WHERE s.h = e.h AND i.cc = %s)', $cc ); // phpcs:ignore WordPress.DB
		}
		$fatal = "'" . implode( "','", self::FATAL ) . "'";
		return (array) $wpdb->get_results( "SELECT e.*, {$sites} AS sites FROM " . self::t( 'errors' ) . " e WHERE {$where} ORDER BY (e.t IN ({$fatal})) DESC, sites DESC, e.n DESC LIMIT 300", ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	public static function error_plugins() {
		global $wpdb;
		return (array) $wpdb->get_col( 'SELECT DISTINCT p FROM ' . self::t( 'errors' ) . ' ORDER BY p' ); // phpcs:ignore WordPress.DB
	}

	/* ------------------------------------------------------------------ */
	/* Yönetim                                                             */
	/* ------------------------------------------------------------------ */

	public static function menu() {
		add_menu_page( __( 'Product telemetry', 'flx-analytics' ), __( 'Product telemetry', 'flx-analytics' ), 'manage_options', self::SLUG, array( __CLASS__, 'page' ), 'dashicons-chart-area', 4 );
	}

	public static function menu_icon() {
		echo '<style>#toplevel_page_' . esc_attr( self::SLUG ) . ' .wp-menu-image:before{content:"\f239"}</style>'; // dashicons-chart-area
	}

	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/** Paket adı: FluxWP Panel kataloğundan (kuruluysa), yoksa slug. */
	public static function name( $slug ) {
		static $names = null;
		if ( null === $names ) {
			$names = array();
			if ( class_exists( 'FLXU_Updater' ) ) {
				foreach ( (array) FLXU_Updater::instance()->catalog() as $s => $p ) {
					$names[ $s ] = (string) ( $p['name'] ?? $s );
				}
			}
		}
		$theme = 0 === strpos( $slug, 'theme:' );
		$s     = $theme ? substr( $slug, 6 ) : $slug;
		if ( 0 === strpos( $s, 'core:' ) ) {
			$core = array(
				'admin'   => __( 'Admin theme', 'flx-analytics' ),
				'store'   => __( 'Store', 'flx-analytics' ),
				'charity' => __( 'Nonprofit and donations', 'flx-analytics' ),
			);
			$k = substr( $s, 5 );
			/* translators: %s: sihirbazın ilk ekranındaki kart adı */
			return sprintf( __( '%s card', 'flx-analytics' ), $core[ $k ] ?? $k );
		}
		return ( $names[ $s ] ?? $s ) . ( $theme ? ' ' . __( '(theme)', 'flx-analytics' ) : '' );
	}

	public static function step_name( $key ) {
		$map = array(
			'intro'      => __( 'Intro', 'flx-analytics' ),
			'base'       => __( 'Where to start?', 'flx-analytics' ),
			'appearance' => __( 'Appearance', 'flx-analytics' ),
			'install'    => __( 'Install', 'flx-analytics' ),
			'done'       => __( 'Done', 'flx-analytics' ),
		);
		if ( isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}
		if ( 0 !== strpos( $key, 'group:' ) ) {
			return $key;
		}
		$groups = array(
			'manage'  => __( 'Management', 'flx-analytics' ),
			'contact' => __( 'Communication', 'flx-analytics' ),
			'grow'    => __( 'Growth', 'flx-analytics' ),
			'ai'      => __( 'AI', 'flx-analytics' ),
			'system'  => __( 'System', 'flx-analytics' ),
			'other'   => __( 'Other', 'flx-analytics' ),
		);
		$g = substr( $key, 6 );
		/* translators: %s: eklenti kategorisi (Yönetim, İletişim…) */
		return sprintf( __( 'Plugins: %s', 'flx-analytics' ), $groups[ $g ] ?? $g );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'flx-analytics' ) );
		}
		self::maybe_install();
		flx_admin_screen_page();
	}
}

FLXAN_Telemetry_Hub::init();
