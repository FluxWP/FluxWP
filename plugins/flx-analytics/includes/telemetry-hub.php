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

	const DB_VERSION   = 1;
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
		add_action( 'admin_post_flxan_tm_forget', array( __CLASS__, 'forget' ) );
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
				ms tinyint(1) unsigned NOT NULL DEFAULT 0,
				panel varchar(32) NOT NULL DEFAULT '',
				pk text NOT NULL,
				ob text NOT NULL,
				ob_ts int(10) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (iid),
				KEY last_ts (last_ts)
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
		$res    = new WP_REST_Response( array( 'ok' => true, 'status' => $status ) );
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	/** Doğrulanmış gövdeyi kaydeder. Döner: ok | throttled. */
	public static function record( array $b ) {
		global $wpdb;
		$now = time();
		$iid = substr( hash( 'sha256', 'flx-telemetry|' . $b['id'] ), 0, 20 );
		$ti  = self::t( 'installs' );
		$cur = $wpdb->get_row( $wpdb->prepare( "SELECT first_ts, last_ts, n, ob, ob_ts FROM {$ti} WHERE iid = %s", $iid ), ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( $cur && $now - (int) $cur['last_ts'] < self::MIN_INTERVAL ) {
			return 'throttled';
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
			'ms'       => empty( $b['multisite'] ) ? 0 : 1,
			'panel'    => self::ver( $b['panel'] ?? '' ),
			'pk'       => wp_json_encode( $pk ),
			'ob'       => $ob ? wp_json_encode( $ob ) : (string) ( $cur['ob'] ?? '' ),
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

	public static function summary( $days ) {
		$key = 'flxan_tm_sum';
		$all = get_transient( $key );
		if ( is_array( $all ) && isset( $all[ $days ] ) ) {
			return $all[ $days ];
		}
		global $wpdb;
		$cut  = time() - $days * DAY_IN_SECONDS;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT wp, php, loc, pk, ob, ob_ts FROM ' . self::t( 'installs' ) . ' WHERE last_ts >= %d', $cut ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$S    = array(
			'installs' => count( $rows ),
			'usage'    => array(),
			'versions' => array(),
			'pairs'    => array(),
			'combos'   => array(),
			'php'      => array(),
			'wp'       => array(),
			'locale'   => array(),
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
		foreach ( array( 'usage', 'pairs', 'combos', 'php', 'wp', 'locale' ) as $k ) {
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
		$S['errors_open']     = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t( 'errors' ) . ' WHERE last_ts >= %d', $cut ) ); // phpcs:ignore WordPress.DB
		$S['fatal_open']      = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t( 'errors' ) . " WHERE last_ts >= %d AND t IN ('" . implode( "','", self::FATAL ) . "')", $cut ) ); // phpcs:ignore WordPress.DB
		$all                  = is_array( $all ) ? $all : array();
		$all[ $days ]         = $S;
		set_transient( $key, $all, 10 * MINUTE_IN_SECONDS );
		return $S;
	}

	public static function errors( $days, $plugin = '', $type = '' ) {
		global $wpdb;
		$where = $wpdb->prepare( 'last_ts >= %d', time() - $days * DAY_IN_SECONDS );
		if ( '' !== $plugin ) {
			$where .= $wpdb->prepare( ' AND p = %s', $plugin );
		}
		if ( 'fatal' === $type ) {
			$where .= " AND t IN ('" . implode( "','", self::FATAL ) . "')";
		} elseif ( in_array( $type, self::TYPES, true ) ) {
			$where .= $wpdb->prepare( ' AND t = %s', $type );
		}
		$fatal = "'" . implode( "','", self::FATAL ) . "'";
		return (array) $wpdb->get_results( 'SELECT * FROM ' . self::t( 'errors' ) . " WHERE {$where} ORDER BY (t IN ({$fatal})) DESC, sites DESC, n DESC LIMIT 300", ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	public static function error_plugins() {
		global $wpdb;
		return (array) $wpdb->get_col( 'SELECT DISTINCT p FROM ' . self::t( 'errors' ) . ' ORDER BY p' ); // phpcs:ignore WordPress.DB
	}

	/* ------------------------------------------------------------------ */
	/* Yönetim                                                             */
	/* ------------------------------------------------------------------ */

	public static function menu() {
		add_menu_page( __( 'Ürün telemetrisi', 'flx-analytics' ), __( 'Ürün telemetrisi', 'flx-analytics' ), 'manage_options', self::SLUG, array( __CLASS__, 'page' ), 'dashicons-chart-area', 4 );
	}

	public static function menu_icon() {
		echo '<style>#toplevel_page_' . esc_attr( self::SLUG ) . ' .wp-menu-image:before{content:"\f239"}</style>'; // dashicons-chart-area
	}

	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	public static function forget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-analytics' ) );
		}
		check_admin_referer( 'flxan_tm_forget' );
		global $wpdb;
		$h = isset( $_POST['h'] ) ? sanitize_key( wp_unslash( $_POST['h'] ) ) : '';
		// phpcs:disable WordPress.DB
		if ( 'all' === $h ) {
			$wpdb->query( 'TRUNCATE TABLE ' . self::t( 'errors' ) );
			$wpdb->query( 'TRUNCATE TABLE ' . self::t( 'esites' ) );
		} elseif ( preg_match( '/^[a-f0-9]{16}$/', $h ) ) {
			$wpdb->delete( self::t( 'errors' ), array( 'h' => $h ) );
			$wpdb->delete( self::t( 'esites' ), array( 'h' => $h ) );
		}
		// phpcs:enable
		delete_transient( 'flxan_tm_sum' );
		$back = wp_get_referer() ? wp_get_referer() : self::url( array( 'tab' => 'errors' ) );
		wp_safe_redirect( add_query_arg( 'flxan_tm', 'forgot', $back ) );
		exit;
	}

	/** Paket adı: FluxWP Panel kataloğundan (kuruluysa), yoksa slug. */
	private static function name( $slug ) {
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
				'admin'   => __( 'Yönetim teması', 'flx-analytics' ),
				'store'   => __( 'Mağaza', 'flx-analytics' ),
				'charity' => __( 'Dernek ve bağış', 'flx-analytics' ),
			);
			$k = substr( $s, 5 );
			/* translators: %s: sihirbazın ilk ekranındaki kart adı */
			return sprintf( __( '%s kartı', 'flx-analytics' ), $core[ $k ] ?? $k );
		}
		return ( $names[ $s ] ?? $s ) . ( $theme ? ' ' . __( '(tema)', 'flx-analytics' ) : '' );
	}

	private static function step_name( $key ) {
		$map = array(
			'intro'      => __( 'Tanıtım', 'flx-analytics' ),
			'base'       => __( 'Neyle başlayalım?', 'flx-analytics' ),
			'appearance' => __( 'Görünüm', 'flx-analytics' ),
			'install'    => __( 'Kurulum', 'flx-analytics' ),
			'done'       => __( 'Bitti', 'flx-analytics' ),
		);
		if ( isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}
		if ( 0 !== strpos( $key, 'group:' ) ) {
			return $key;
		}
		$groups = array(
			'manage'  => __( 'Yönetim', 'flx-analytics' ),
			'contact' => __( 'İletişim', 'flx-analytics' ),
			'grow'    => __( 'Büyüme', 'flx-analytics' ),
			'ai'      => __( 'Yapay zekâ', 'flx-analytics' ),
			'system'  => __( 'Sistem', 'flx-analytics' ),
			'other'   => __( 'Diğer', 'flx-analytics' ),
		);
		$g = substr( $key, 6 );
		/* translators: %s: eklenti kategorisi (Yönetim, İletişim…) */
		return sprintf( __( 'Eklentiler: %s', 'flx-analytics' ), $groups[ $g ] ?? $g );
	}

	private static function pct( $n, $of ) {
		return $of ? round( $n / $of * 100 ) . '%' : '—';
	}

	private static function kpi( $label, $value, $sub, $tone ) {
		return '<div class="flxan-card flxan-kpi flxan-kpi--' . esc_attr( $tone ) . '"><div class="flxan-kpi__top"><span class="flxan-card__label">' . esc_html( $label ) . '</span></div><div class="flxan-kpi__row"><div class="flxan-card__value">' . esc_html( $value ) . '</div></div><div class="flxan-card__sub">' . esc_html( $sub ) . '</div></div>';
	}

	/** Çubuklu tablo: $rows = label => [değer, ...ek sütunlar]. */
	private static function bars( $title, $rows, $cols, $max_of = null, $empty = '' ) {
		echo '<div class="flxan-panel"><div class="flxan-panel__head"><h2>' . esc_html( $title ) . '</h2></div>';
		if ( ! $rows ) {
			echo '<p class="flxan-empty">' . esc_html( $empty ? $empty : __( 'Bu aralıkta kayıt yok.', 'flx-analytics' ) ) . '</p></div>';
			return;
		}
		$max = $max_of;
		if ( ! $max ) {
			foreach ( $rows as $vals ) {
				$vals = array_values( (array) $vals );
				$max  = max( (int) $max, (int) $vals[0] );
			}
		}
		echo '<table class="flxan-table"><tr>';
		foreach ( $cols as $i => $c ) {
			echo '<th' . ( $i ? ' class="n"' : '' ) . '>' . esc_html( $c ) . '</th>';
		}
		echo '</tr>';
		foreach ( $rows as $label => $vals ) {
			$vals = array_values( (array) $vals );
			$w    = $max ? min( 100, round( $vals[0] / $max * 100, 1 ) ) : 0;
			echo '<tr><td class="flxan-lbl"><span class="bar" style="width:' . esc_attr( $w ) . '%"></span><span class="txt">' . wp_kses( $label, array( 'small' => array() ) ) . '</span></td>';
			foreach ( $vals as $v ) {
				echo '<td class="n">' . esc_html( is_int( $v ) ? number_format_i18n( $v ) : $v ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</table></div>';
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bu sayfayı görme yetkiniz yok.', 'flx-analytics' ) );
		}
		self::maybe_install();
		// phpcs:disable WordPress.Security.NonceVerification
		$tab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		$tab   = in_array( $tab, array( 'overview', 'errors', 'onboarding' ), true ) ? $tab : 'overview';
		$range = isset( $_GET['r'] ) ? (int) $_GET['r'] : 30;
		$range = in_array( $range, array( 7, 30, 90 ), true ) ? $range : 30;
		// phpcs:enable
		$S    = self::summary( $range );
		$tabs = array(
			'overview'   => __( 'Genel bakış', 'flx-analytics' ),
			'errors'     => __( 'Hatalar', 'flx-analytics' ),
			'onboarding' => __( 'Kurulum sihirbazı', 'flx-analytics' ),
		);
		?>
		<div class="wrap flxan flxan-tm">
			<style>
				.flxan-tm .flxan-tabs a{display:inline-block;color:var(--flxan-muted);border-radius:6px;padding:5px 12px;font-size:13px;text-decoration:none}
				.flxan-tm .flxan-tabs a[aria-current]{background:var(--flxan-card);color:var(--flxan-text);box-shadow:var(--flxan-shadow)}
				.flxan-tm .flxan-cards{grid-template-columns:repeat(4,minmax(0,1fr))}
				.flxan-tm .tm-msg{white-space:pre-wrap;word-break:break-word;font:12px/1.5 ui-monospace,SFMono-Regular,Menlo,monospace;margin:0}
				.flxan-tm .tm-err td{height:auto;padding:8px 6px;vertical-align:top}
				.flxan-tm .tm-err details summary{cursor:pointer;list-style:none}
				.flxan-tm .tm-err details summary::-webkit-details-marker{display:none}
				.flxan-tm .tm-t{display:inline-block;font-size:11px;font-weight:600;padding:1px 7px;border-radius:99px;background:#f0f0f1;color:#50575e}
				.flxan-tm .tm-t.fatal{background:#fcf0f1;color:#b32d2e}.flxan-tm .tm-t.warn{background:#fcf3e6;color:#9a6700}
				.flxan-tm .tm-filter{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px}
				.flxan-tm .tm-funnel td{height:38px}
				@media(max-width:782px){.flxan-tm .flxan-cards,.flxan-tm .flxan-grid{grid-template-columns:1fr 1fr}.flxan-tm .flxan-grid{grid-template-columns:1fr}.flxan-tm .tm-hide-m{display:none}}
			</style>
			<div class="flxan-head">
				<h1><?php esc_html_e( 'Ürün telemetrisi', 'flx-analytics' ); ?></h1>
				<div class="flxan-tabs" role="tablist">
					<?php foreach ( array( 7, 30, 90 ) as $r ) : ?>
						<a href="<?php echo esc_url( self::url( array( 'tab' => $tab, 'r' => $r ) ) ); ?>"<?php echo $r === $range ? ' aria-current="page"' : ''; ?>><?php /* translators: %d: gün */ echo esc_html( sprintf( __( '%d gün', 'flx-analytics' ), $r ) ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>
			<p class="description" style="margin:-8px 0 14px"><?php esc_html_e( 'FluxWP Panel\'de "Ürünü geliştirmemize yardımcı olun" kutusunu işaretleyen sitelerden günde bir kez gelen anonim veri. IP ve site adresi saklanmaz; kayıtlar 90 gün tutulur.', 'flx-analytics' ); ?></p>
			<nav class="flxan-tabs" style="margin-bottom:14px">
				<?php foreach ( $tabs as $k => $l ) : ?>
					<a href="<?php echo esc_url( self::url( array( 'tab' => $k, 'r' => $range ) ) ); ?>"<?php echo $k === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $l ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php
			if ( 'errors' === $tab ) {
				self::view_errors( $range, $S );
			} elseif ( 'onboarding' === $tab ) {
				self::view_onboarding( $S );
			} else {
				self::view_overview( $S );
			}
			?>
		</div>
		<?php
	}

	private static function view_overview( $S ) {
		$n = $S['installs'];
		echo '<div class="flxan-cards">';
		echo self::kpi( __( 'Raporlayan site', 'flx-analytics' ), number_format_i18n( $n ), __( 'Tekil kurulum', 'flx-analytics' ), 'green' ); // phpcs:ignore
		echo self::kpi( __( 'Rapor', 'flx-analytics' ), number_format_i18n( $S['reports'] ), __( 'Günde en fazla bir / site', 'flx-analytics' ), 'blue' ); // phpcs:ignore
		/* translators: %s: sayı */
		echo self::kpi( __( 'Farklı hata', 'flx-analytics' ), number_format_i18n( $S['errors_open'] ), sprintf( __( '%s ölümcül', 'flx-analytics' ), number_format_i18n( $S['fatal_open'] ) ), 'rose' ); // phpcs:ignore
		echo self::kpi( __( 'Hata olayı', 'flx-analytics' ), number_format_i18n( $S['events'] ), __( 'Toplam tekrar', 'flx-analytics' ), 'amber' ); // phpcs:ignore
		echo '</div>';
		if ( ! $n ) {
			echo '<div class="flxan-panel flxan-empty--big"><b>' . esc_html__( 'Henüz rapor yok.', 'flx-analytics' ) . '</b><p>' . esc_html__( 'Sitelerdeki FluxWP Panel bu adrese gönderir:', 'flx-analytics' ) . '</p><code>' . esc_html( self::endpoint() ) . '</code></div>';
			return;
		}
		echo '<div class="flxan-grid">';
		$rows = array();
		foreach ( $S['usage'] as $s => $c ) {
			$top              = array_key_first( (array) ( $S['versions'][ $s ] ?? array() ) );
			$rows[ esc_html( self::name( $s ) ) ] = array( $c, self::pct( $c, $n ), (string) $top );
		}
		self::bars( __( 'Eklenti kullanımı', 'flx-analytics' ), array_slice( $rows, 0, 25, true ), array( __( 'Paket', 'flx-analytics' ), __( 'Site', 'flx-analytics' ), __( 'Pay', 'flx-analytics' ), __( 'Yaygın sürüm', 'flx-analytics' ) ), $n );
		$rows = array();
		foreach ( $S['pairs'] as $k => $c ) {
			list( $a, $b ) = explode( '|', $k );
			$rows[ esc_html( self::name( $a ) ) . ' + ' . esc_html( self::name( $b ) ) ] = array( $c, self::pct( $c, $n ) );
		}
		self::bars( __( 'Birlikte kullanılanlar', 'flx-analytics' ), $rows, array( __( 'İkili', 'flx-analytics' ), __( 'Site', 'flx-analytics' ), __( 'Pay', 'flx-analytics' ) ), $n );
		$rows = array();
		foreach ( $S['combos'] as $k => $c ) {
			$names = '' === $k ? array( __( 'Yalnız FluxWP Panel', 'flx-analytics' ) ) : array_map( array( __CLASS__, 'name' ), explode( '|', $k ) );
			$rows[ esc_html( implode( ', ', $names ) ) . ' <small>' . esc_html( count( '' === $k ? array() : explode( '|', $k ) ) ) . '</small>' ] = array( $c, self::pct( $c, $n ) );
		}
		echo '<div class="flxan-wide">';
		self::bars( __( 'En sık paket setleri', 'flx-analytics' ), $rows, array( __( 'Set (FluxWP Panel hariç)', 'flx-analytics' ), __( 'Site', 'flx-analytics' ), __( 'Pay', 'flx-analytics' ) ), $n );
		echo '</div>';
		foreach ( array( 'php' => 'PHP', 'wp' => 'WordPress' ) as $k => $l ) {
			$rows = array();
			foreach ( $S[ $k ] as $v => $c ) {
				$rows[ esc_html( $l . ' ' . $v ) ] = array( $c, self::pct( $c, $n ) );
			}
			self::bars( $l, $rows, array( __( 'Sürüm', 'flx-analytics' ), __( 'Site', 'flx-analytics' ), __( 'Pay', 'flx-analytics' ) ), $n );
		}
		$rows = array();
		foreach ( $S['locale'] as $v => $c ) {
			$rows[ esc_html( $v ) ] = array( $c, self::pct( $c, $n ) );
		}
		self::bars( __( 'Site dili', 'flx-analytics' ), $rows, array( __( 'Dil', 'flx-analytics' ), __( 'Site', 'flx-analytics' ), __( 'Pay', 'flx-analytics' ) ), $n );
		echo '</div>';
	}

	private static function view_errors( $range, $S ) {
		// phpcs:disable WordPress.Security.NonceVerification
		$plugin = isset( $_GET['p'] ) ? sanitize_key( wp_unslash( $_GET['p'] ) ) : '';
		$type   = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : '';
		if ( isset( $_GET['flxan_tm'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Hata kaydı silindi. Yeniden olursa tekrar görünür.', 'flx-analytics' ) . '</p></div>';
		}
		// phpcs:enable
		$list = self::errors( $range, $plugin, $type );
		?>
		<div class="flxan-panel">
			<form class="tm-filter" method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
				<input type="hidden" name="tab" value="errors">
				<input type="hidden" name="r" value="<?php echo esc_attr( $range ); ?>">
				<select name="p">
					<option value=""><?php esc_html_e( 'Tüm paketler', 'flx-analytics' ); ?></option>
					<?php foreach ( self::error_plugins() as $p ) : ?>
						<option value="<?php echo esc_attr( $p ); ?>" <?php selected( $plugin, $p ); ?>><?php echo esc_html( self::name( $p ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="t">
					<option value=""><?php esc_html_e( 'Tüm türler', 'flx-analytics' ); ?></option>
					<option value="fatal" <?php selected( $type, 'fatal' ); ?>><?php esc_html_e( 'Yalnız ölümcül', 'flx-analytics' ); ?></option>
					<?php foreach ( array( 'E_WARNING', 'E_NOTICE', 'E_DEPRECATED', 'E_USER_WARNING', 'E_USER_NOTICE', 'E_USER_DEPRECATED' ) as $t ) : ?>
						<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $type, $t ); ?>><?php echo esc_html( $t ); ?></option>
					<?php endforeach; ?>
				</select>
				<button class="button"><?php esc_html_e( 'Süz', 'flx-analytics' ); ?></button>
				<span class="flxan-muted" style="margin-left:auto"><?php /* translators: %s: sayı */ echo esc_html( sprintf( __( '%s kayıt', 'flx-analytics' ), number_format_i18n( count( $list ) ) ) ); ?></span>
			</form>
			<?php if ( ! $list ) : ?>
				<p class="flxan-empty"><?php esc_html_e( 'Bu aralıkta hata yok.', 'flx-analytics' ); ?></p>
			<?php else : ?>
			<div style="overflow-x:auto"><table class="flxan-table tm-err">
				<tr><th><?php esc_html_e( 'Hata', 'flx-analytics' ); ?></th><th><?php esc_html_e( 'Paket', 'flx-analytics' ); ?></th><th class="n"><?php esc_html_e( 'Site', 'flx-analytics' ); ?></th><th class="n"><?php esc_html_e( 'Tekrar', 'flx-analytics' ); ?></th><th class="n tm-hide-m"><?php esc_html_e( 'Son', 'flx-analytics' ); ?></th><th class="n"></th></tr>
				<?php foreach ( $list as $e ) : ?>
					<?php
					$fatal = in_array( $e['t'], self::FATAL, true );
					$cls   = $fatal ? 'fatal' : ( false !== strpos( $e['t'], 'WARNING' ) ? 'warn' : '' );
					$first = strtok( (string) $e['m'], "\n" );
					$more  = trim( substr( (string) $e['m'], strlen( (string) $first ) ) );
					?>
					<tr>
						<td>
							<span class="tm-t <?php echo esc_attr( $cls ); ?>"><?php echo esc_html( str_replace( 'E_', '', $e['t'] ) ); ?></span>
							<?php if ( '' !== $more ) : ?>
								<details><summary><p class="tm-msg"><?php echo esc_html( $first ); ?> <span class="flxan-muted">▾</span></p></summary><p class="tm-msg flxan-muted"><?php echo esc_html( $more ); ?></p></details>
							<?php else : ?>
								<p class="tm-msg"><?php echo esc_html( $first ); ?></p>
							<?php endif; ?>
							<code class="flxan-muted" style="font-size:11px"><?php echo esc_html( $e['f'] . ( $e['l'] ? ':' . $e['l'] : '' ) ); ?></code>
						</td>
						<td style="white-space:nowrap"><?php echo esc_html( self::name( $e['p'] ) ); ?><br><small class="flxan-muted"><?php echo esc_html( $e['v'] ); ?></small></td>
						<td class="n"><?php echo esc_html( number_format_i18n( (int) $e['sites'] ) ); ?></td>
						<td class="n"><?php echo esc_html( number_format_i18n( (int) $e['n'] ) ); ?></td>
						<td class="n tm-hide-m"><?php /* translators: %s: süre */ echo esc_html( flx_time_ago( (int) $e['last_ts'] ) ); ?></td>
						<td class="n">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="flxan_tm_forget"><input type="hidden" name="h" value="<?php echo esc_attr( $e['h'] ); ?>">
								<?php wp_nonce_field( 'flxan_tm_forget' ); ?>
								<button class="button button-small" title="<?php esc_attr_e( 'Çözüldü olarak kaldır', 'flx-analytics' ); ?>"><?php esc_html_e( 'Çözüldü', 'flx-analytics' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</table></div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxan-more" onsubmit="return confirm('<?php echo esc_js( __( 'Tüm hata kayıtları silinsin mi?', 'flx-analytics' ) ); ?>')">
				<input type="hidden" name="action" value="flxan_tm_forget"><input type="hidden" name="h" value="all">
				<?php wp_nonce_field( 'flxan_tm_forget' ); ?>
				<button class="button-link" style="color:#b32d2e"><?php esc_html_e( 'Tüm hataları temizle', 'flx-analytics' ); ?></button>
			</form>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'Mesajlardan sunucu yolları, alan adı, URL, e-posta ve IP silinmiş olarak gelir. Aynı paket, sürüm, dosya ve satırdaki aynı hata tek kayıttır; "Site" kaç farklı kurulumda görüldüğüdür.', 'flx-analytics' ); ?></p>
		</div>
		<?php
	}

	private static function view_onboarding( $S ) {
		$O    = $S['ob'];
		$runs = $O['runs'];
		echo '<div class="flxan-cards">';
		echo self::kpi( __( 'Sihirbaz açılışı', 'flx-analytics' ), number_format_i18n( $runs ), __( 'Kurulum başına son çalıştırma', 'flx-analytics' ), 'blue' ); // phpcs:ignore
		echo self::kpi( __( 'Bitiren', 'flx-analytics' ), self::pct( $O['out']['done'], $runs ), number_format_i18n( $O['out']['done'] ) . ' ' . __( 'site', 'flx-analytics' ), 'green' ); // phpcs:ignore
		echo self::kpi( __( 'Atlayan', 'flx-analytics' ), self::pct( $O['out']['skip'], $runs ), number_format_i18n( $O['out']['skip'] ) . ' ' . __( 'site', 'flx-analytics' ), 'amber' ); // phpcs:ignore
		/* translators: %s: dakika */
		echo self::kpi( __( 'Yarıda bırakan', 'flx-analytics' ), self::pct( $O['out']['left'], $runs ), $O['median'] ? sprintf( __( 'Bitirenlerde ortanca süre %s dk', 'flx-analytics' ), number_format_i18n( max( 1, round( $O['median'] / 60 ) ) ) ) : '—', 'rose' ); // phpcs:ignore
		echo '</div>';
		if ( ! $runs ) {
			echo '<div class="flxan-panel flxan-empty--big"><b>' . esc_html__( 'Henüz sihirbaz verisi yok.', 'flx-analytics' ) . '</b><p>' . esc_html__( 'FluxWP Panel 1.0.11 ve üstü, sihirbaz bitince ya da kapatılınca son adımı ve seçimleri gönderir.', 'flx-analytics' ) . '</p></div>';
			return;
		}
		echo '<div class="flxan-grid">';
		echo '<div class="tm-funnel">';
		$rows = array();
		foreach ( $O['reach'] as $st => $c ) {
			$rows[ esc_html( self::step_name( $st ) ) ] = array( $c, self::pct( $c, $runs ) );
		}
		self::bars( __( 'Adımlara ulaşan', 'flx-analytics' ), $rows, array( __( 'Adım', 'flx-analytics' ), __( 'Site', 'flx-analytics' ), __( 'Pay', 'flx-analytics' ) ), $runs );
		echo '</div>';
		$rows = array();
		foreach ( $O['stop'] as $k => $c ) {
			list( $st, $out ) = explode( '|', $k );
			$rows[ esc_html( self::step_name( $st ) ) . ' <small>' . esc_html( 'skip' === $out ? __( 'atladı', 'flx-analytics' ) : __( 'kapattı', 'flx-analytics' ) ) . '</small>' ] = array( $c, self::pct( $c, $runs ) );
		}
		self::bars( __( 'Nerede bıraktılar', 'flx-analytics' ), $rows, array( __( 'Son adım', 'flx-analytics' ), __( 'Site', 'flx-analytics' ), __( 'Pay', 'flx-analytics' ) ), $runs, __( 'Herkes bitirdi.', 'flx-analytics' ) );
		$slugs = array_unique( array_merge( array_keys( $O['picked'] ), array_keys( $O['dropped'] ), array_keys( $O['rec_off'] ) ) );
		$rows  = array();
		foreach ( $slugs as $s ) {
			$p                               = (int) ( $O['picked'][ $s ] ?? 0 );
			$rows[ esc_html( self::name( $s ) ) ] = array( $p, self::pct( $p, $runs ), (int) ( $O['dropped'][ $s ] ?? 0 ), (int) ( $O['rec_off'][ $s ] ?? 0 ) );
		}
		uasort(
			$rows,
			function ( $a, $b ) {
				return $b[0] <=> $a[0];
			}
		);
		echo '<div class="flxan-wide">';
		self::bars( __( 'Kart seçimleri', 'flx-analytics' ), $rows, array( __( 'Kart', 'flx-analytics' ), __( 'Seçen', 'flx-analytics' ), __( 'Pay', 'flx-analytics' ), __( 'Seçip vazgeçen', 'flx-analytics' ), __( 'Öneriyi kaldıran', 'flx-analytics' ) ), $runs );
		echo '</div>';
		foreach ( array( 'preset' => __( 'Ön ayar (bitirenler)', 'flx-analytics' ), 'style' => __( 'Renk stili (bitirenler)', 'flx-analytics' ) ) as $k => $l ) {
			$rows = array();
			foreach ( $O[ $k ] as $v => $c ) {
				$rows[ esc_html( $v ) ] = array( $c, self::pct( $c, $O['out']['done'] ) );
			}
			self::bars( $l, $rows, array( __( 'Seçim', 'flx-analytics' ), __( 'Site', 'flx-analytics' ), __( 'Pay', 'flx-analytics' ) ), $O['out']['done'] );
		}
		echo '</div>';
	}
}

FLXAN_Telemetry_Hub::init();
