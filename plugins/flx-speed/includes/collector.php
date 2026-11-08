<?php
/**
 * İstek boyunca veri toplayan sınıf.
 *
 * Kipler:
 *  - off:   hiçbir şey toplanmaz (kayıt kapalı ve kimse giriş yapmamış).
 *  - light: ucuz kancalar (PHP hatası, HTTP, doing-it-wrong, log, süre/bellek). Her istekte.
 *  - full:  ayrıca SAVEQUERIES + sorgu başına bileşen, şablon, istek, dil, transient.
 *           Örneklenen isteklerde, giriş yapmış kullanıcıda (panel) ve profil isteğinde.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

class FLXP_Collector {

	const MAX_ITEMS = 200;

	public static $mode      = 'off';
	public static $panel     = false;
	public static $sampled   = false;
	public static $profile   = false;
	public static $sq_preset = false;
	public static $start     = 0.0;

	public static $errors      = array();
	public static $wrong       = array();
	public static $http        = array();
	public static $http_start  = array();
	public static $logs        = array();
	public static $timers      = array();
	public static $milestones  = array();
	public static $template    = array();
	public static $parts       = array();
	public static $request     = array();
	public static $caps        = array();
	public static $lang        = array();
	public static $transients  = array();
	public static $snapshot    = null;
	public static $recorded_id = 0;

	/** Eklenti yüklenirken çağrılır. */
	public static function boot() {
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || PHP_SAPI === 'cli' || ( defined( 'FLXP_DISABLED' ) && FLXP_DISABLED ) ) {
			return;
		}
		self::$start = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime( true );

		$s = flxp_settings();
		// Profil isteği: MCP aracı flxp_profile_url kendi sitesine tek kullanımlık jetonla istek atar.
		$tok = isset( $_SERVER['HTTP_X_FLXP_PROFILE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FLXP_PROFILE'] ) ) : '';
		if ( $tok && preg_match( '/^[A-Za-z0-9]{32}$/', $tok ) && get_transient( 'flxp_prof_' . md5( $tok ) ) ) {
			self::$profile = true;
			delete_transient( 'flxp_prof_' . md5( $tok ) );
		}
		$logged_in     = defined( 'LOGGED_IN_COOKIE' ) && ! empty( $_COOKIE[ LOGGED_IN_COOKIE ] );
		self::$sampled = $s['record'] && $s['sample'] > 0 && mt_rand( 1, 100 ) <= (int) $s['sample']; // phpcs:ignore -- wp_rand henüz yüklü değil (pluggable).

		if ( self::$profile || self::$sampled || ( $logged_in && $s['panel'] ) ) {
			self::$mode = 'full';
		} elseif ( $s['record'] || $logged_in ) {
			self::$mode = 'light';
		} else {
			return;
		}

		self::hooks_light();
		if ( 'full' === self::$mode ) {
			self::$sq_preset = defined( 'SAVEQUERIES' );
			if ( ! defined( 'SAVEQUERIES' ) ) {
				define( 'SAVEQUERIES', true );
			}
			self::hooks_full();
		}
		register_shutdown_function( array( __CLASS__, 'on_php_shutdown' ) );
	}

	/** Her istekte çalışan ucuz kancalar. */
	private static function hooks_light() {
		$prev = set_error_handler( array( __CLASS__, 'on_error' ) ); // phpcs:ignore
		self::$prev_handler = $prev;

		add_filter( 'http_request_args', array( __CLASS__, 'on_http_args' ), PHP_INT_MAX, 2 );
		add_filter( 'pre_http_request', array( __CLASS__, 'on_http_pre' ), PHP_INT_MAX, 3 );
		add_action( 'http_api_debug', array( __CLASS__, 'on_http_done' ), 10, 5 );

		add_action( 'doing_it_wrong_run', array( __CLASS__, 'on_doing_it_wrong' ), 10, 3 );
		add_action( 'deprecated_function_run', array( __CLASS__, 'on_deprecated_function' ), 10, 3 );
		add_action( 'deprecated_argument_run', array( __CLASS__, 'on_deprecated_argument' ), 10, 3 );
		add_action( 'deprecated_hook_run', array( __CLASS__, 'on_deprecated_hook' ), 10, 4 );
		add_action( 'deprecated_file_included', array( __CLASS__, 'on_deprecated_file' ), 10, 4 );
		add_action( 'deprecated_class_run', array( __CLASS__, 'on_deprecated_class' ), 10, 3 );

		// Kendi log kancamız + Query Monitor uyumlu kancalar (qm/debug ... qm/emergency).
		add_action( 'flx_log', array( __CLASS__, 'log' ), 10, 3 );
		foreach ( array( 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug' ) as $lvl ) {
			add_action(
				'qm/' . $lvl,
				function ( $message, $context = array() ) use ( $lvl ) {
					FLXP_Collector::log( $lvl, $message, $context );
				},
				10,
				2
			);
		}
		add_action( 'flx_timer_start', array( __CLASS__, 'timer_start' ) );
		add_action( 'flx_timer_stop', array( __CLASS__, 'timer_stop' ) );

		foreach ( array( 'plugins_loaded', 'setup_theme', 'after_setup_theme', 'init', 'wp_loaded', 'parse_request', 'wp', 'template_redirect', 'wp_head', 'wp_footer', 'admin_init', 'admin_head', 'admin_footer', 'rest_api_init', 'shutdown' ) as $hook ) {
			add_action(
				$hook,
				function () use ( $hook ) {
					if ( ! isset( FLXP_Collector::$milestones[ $hook ] ) ) {
						FLXP_Collector::$milestones[ $hook ] = array( round( ( microtime( true ) - FLXP_Collector::$start ) * 1000, 1 ), round( memory_get_usage() / 1024 ) );
					}
				},
				PHP_INT_MIN
			);
		}

		add_action( 'init', array( __CLASS__, 'on_init' ), 0 );
		add_action( 'shutdown', array( __CLASS__, 'on_wp_shutdown' ), 0 );
	}

	/** Ayrıntılı (full) kipte eklenen kancalar. */
	private static function hooks_full() {
		add_filter( 'log_query_custom_data', array( __CLASS__, 'on_query' ), 10, 5 );
		add_filter( 'template_include', array( __CLASS__, 'on_template' ), PHP_INT_MAX );
		add_action( 'get_template_part', array( __CLASS__, 'on_template_part' ), 10, 3 );
		add_action( 'wp', array( __CLASS__, 'on_wp' ), PHP_INT_MAX );
		add_action( 'load_textdomain', array( __CLASS__, 'on_textdomain' ), 10, 2 );
		// WP 6.8 setted_* kancalarını set_transient / set_site_transient yaptı; eskisini dinlemek "eskimiş" uyarısı üretir.
		$new_hooks = version_compare( get_bloginfo( 'version' ), '6.8', '>=' );
		add_action( $new_hooks ? 'set_transient' : 'setted_transient', array( __CLASS__, 'on_transient' ), 10, 3 );
		add_action( $new_hooks ? 'set_site_transient' : 'setted_site_transient', array( __CLASS__, 'on_transient' ), 10, 3 );
	}

	/** Kullanıcı belli olunca panel kararı. */
	public static function on_init() {
		$s = flxp_settings();
		if ( 'full' !== self::$mode || ! $s['panel'] || ! is_user_logged_in() || ! flxp_can_view() ) {
			return;
		}
		if ( ! is_admin() && ! $s['panel_front'] ) {
			return;
		}
		self::$panel = true;
		if ( $s['caps'] ) {
			add_filter( 'user_has_cap', array( __CLASS__, 'on_cap' ), PHP_INT_MAX, 4 );
		}
	}

	/* ---------------------------------------------------------------- PHP hataları */

	private static $prev_handler = null;

	public static function on_error( $no, $str, $file = '', $line = 0 ) {
		if ( error_reporting() & $no ) { // @ ile susturulanlar sayılmaz.
			$key = md5( $no . $file . $line . $str );
			if ( isset( self::$errors[ $key ] ) ) {
				self::$errors[ $key ]['count']++;
			} elseif ( count( self::$errors ) < self::MAX_ITEMS ) {
				$t                    = flxp_trace_info( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 20 ) ); // phpcs:ignore
				$c                    = flxp_component_from_file( $file );
				self::$errors[ $key ] = array(
					'level'   => flxp_error_level( $no ),
					'message' => flxp_cut( flxp_mask( str_replace( wp_normalize_path( ABSPATH ), '', (string) $str ) ), 1000 ),
					'file'    => flxp_short_path( $file ),
					'line'    => (int) $line,
					'comp'    => 'core' === $c['type'] ? $t['component'] : $c,
					'stack'   => $t['stack'],
					'count'   => 1,
				);
			}
		}
		if ( self::$prev_handler ) {
			return call_user_func( self::$prev_handler, $no, $str, $file, $line );
		}
		return false;
	}

	/* ---------------------------------------------------------------- Doing it wrong */

	private static function wrong( $kind, $subject, $message ) {
		$key = md5( $kind . $subject . $message );
		if ( isset( self::$wrong[ $key ] ) ) {
			self::$wrong[ $key ]['count']++;
			return;
		}
		if ( count( self::$wrong ) >= self::MAX_ITEMS ) {
			return;
		}
		$t                   = flxp_trace_info( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 25 ) ); // phpcs:ignore
		self::$wrong[ $key ] = array(
			'kind'    => $kind,
			'subject' => (string) $subject,
			'message' => flxp_cut( wp_strip_all_tags( (string) $message ), 600 ),
			'comp'    => $t['component'],
			'caller'  => $t['caller'],
			'stack'   => $t['stack'],
			'count'   => 1,
		);
	}

	public static function on_doing_it_wrong( $function, $message, $version ) {
		self::wrong( 'doing_it_wrong', $function, $message . ( $version ? ' (' . $version . ')' : '' ) );
	}

	public static function on_deprecated_function( $function, $replacement, $version ) {
		/* translators: 1: sürüm, 2: yerine kullanılacak */
		self::wrong( 'deprecated', $function . '()', sprintf( __( 'Deprecated since %1$s. Use instead: %2$s', 'flx-speed' ), $version, $replacement ? $replacement : '—' ) );
	}

	public static function on_deprecated_argument( $function, $message, $version ) {
		self::wrong( 'deprecated', $function . '()', $message . ' (' . $version . ')' );
	}

	public static function on_deprecated_hook( $hook, $replacement, $version, $message ) {
		/* translators: 1: sürüm, 2: yerine kullanılacak */
		self::wrong( 'deprecated', $hook, sprintf( __( 'Deprecated since %1$s. Use instead: %2$s', 'flx-speed' ), $version, $replacement ? $replacement : '—' ) . ( $message ? ' ' . $message : '' ) );
	}

	public static function on_deprecated_file( $file, $replacement, $version, $message ) {
		/* translators: 1: sürüm, 2: yerine kullanılacak */
		self::wrong( 'deprecated', $file, sprintf( __( 'Deprecated since %1$s. Use instead: %2$s', 'flx-speed' ), $version, $replacement ? $replacement : '—' ) . ( $message ? ' ' . $message : '' ) );
	}

	public static function on_deprecated_class( $class, $replacement, $version ) {
		/* translators: 1: sürüm, 2: yerine kullanılacak */
		self::wrong( 'deprecated', $class, sprintf( __( 'Deprecated since %1$s. Use instead: %2$s', 'flx-speed' ), $version, $replacement ? $replacement : '—' ) );
	}

	/* ---------------------------------------------------------------- HTTP API */

	public static function on_http_args( $args, $url ) {
		if ( empty( $args['_flxp'] ) ) {
			$args['_flxp'] = uniqid( '', true );
		}
		return $args;
	}

	public static function on_http_pre( $pre, $args, $url ) {
		if ( ! empty( $args['_flxp'] ) ) {
			self::$http_start[ $args['_flxp'] ] = microtime( true );
		}
		return $pre;
	}

	public static function on_http_done( $response, $context, $class, $args, $url ) {
		if ( 'response' !== $context || count( self::$http ) >= self::MAX_ITEMS ) {
			return;
		}
		$id    = isset( $args['_flxp'] ) ? $args['_flxp'] : '';
		$start = ( $id && isset( self::$http_start[ $id ] ) ) ? self::$http_start[ $id ] : microtime( true );
		$t     = flxp_trace_info( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 30 ) ); // phpcs:ignore
		$row   = array(
			'method' => isset( $args['method'] ) ? strtoupper( $args['method'] ) : 'GET',
			'url'    => flxp_cut( flxp_mask_url( $url ), 500 ),
			'ms'     => round( ( microtime( true ) - $start ) * 1000, 1 ),
			'status' => 0,
			'error'  => '',
			'size'   => 0,
			'timeout'=> isset( $args['timeout'] ) ? (float) $args['timeout'] : 0,
			'comp'   => $t['component'],
			'caller' => $t['caller'],
			'stack'  => $t['stack'],
		);
		if ( is_wp_error( $response ) ) {
			$row['error'] = flxp_cut( flxp_mask( $response->get_error_message() ), 400 );
		} elseif ( is_array( $response ) ) {
			$row['status'] = (int) wp_remote_retrieve_response_code( $response );
			$row['size']   = strlen( (string) wp_remote_retrieve_body( $response ) );
		}
		self::$http[] = $row;
	}

	/* ---------------------------------------------------------------- Log ve zamanlayıcı */

	/**
	 * Diğer paketler: do_action( 'flx_log', 'warning', 'Mesaj {ad}', array( 'ad' => 'x' ) ).
	 */
	public static function log( $level, $message = '', $context = array() ) {
		if ( count( self::$logs ) >= self::MAX_ITEMS ) {
			return;
		}
		$level = strtolower( (string) $level );
		if ( ! in_array( $level, array( 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug' ), true ) ) {
			$level = 'debug';
		}
		if ( $message instanceof Throwable ) {
			$message = get_class( $message ) . ': ' . $message->getMessage();
		} elseif ( ! is_scalar( $message ) ) {
			$message = wp_json_encode( $message, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
		}
		$message = (string) $message;
		if ( is_array( $context ) ) {
			foreach ( $context as $k => $v ) {
				if ( is_scalar( $v ) ) {
					$message = str_replace( '{' . $k . '}', (string) $v, $message );
				}
			}
		}
		$t            = flxp_trace_info( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 15 ) ); // phpcs:ignore
		self::$logs[] = array(
			'level'   => $level,
			'message' => flxp_cut( flxp_mask( $message ), 2000 ),
			'comp'    => $t['component'],
			'caller'  => $t['caller'],
			'ms'      => round( ( microtime( true ) - self::$start ) * 1000, 1 ),
		);
	}

	public static function timer_start( $name ) {
		self::$timers[ (string) $name ] = array( 'start' => microtime( true ), 'ms' => null, 'mem' => memory_get_usage() );
	}

	public static function timer_stop( $name ) {
		$name = (string) $name;
		if ( isset( self::$timers[ $name ] ) && null === self::$timers[ $name ]['ms'] ) {
			self::$timers[ $name ]['ms']  = round( ( microtime( true ) - self::$timers[ $name ]['start'] ) * 1000, 2 );
			self::$timers[ $name ]['mem'] = memory_get_usage() - self::$timers[ $name ]['mem'];
		}
	}

	/* ---------------------------------------------------------------- Ayrıntılı kip */

	/** Her sorguya sorumlu bileşen + hata ekler (SAVEQUERIES açıkken WordPress çağırır). */
	public static function on_query( $data, $query, $time, $callstack, $start ) {
		global $wpdb;
		$t                = flxp_trace_info( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 30 ) ); // phpcs:ignore
		$data['flxp_c']   = $t['component'];
		$data['flxp_f']   = $t['caller'];
		$data['flxp_s']   = $t['stack'];
		$err              = '';
		if ( isset( $wpdb->dbh ) && $wpdb->dbh instanceof mysqli ) {
			$err = mysqli_error( $wpdb->dbh ); // phpcs:ignore
		}
		if ( $err ) {
			$data['flxp_e'] = flxp_cut( flxp_mask( $err ), 500 );
		}
		return $data;
	}

	public static function on_template( $template ) {
		self::$template['file'] = flxp_short_path( $template );
		return $template;
	}

	public static function on_template_part( $slug, $name, $templates ) {
		if ( count( self::$parts ) < 100 ) {
			$found         = locate_template( (array) $templates );
			self::$parts[] = array(
				'slug'  => (string) $slug . ( $name ? '-' . $name : '' ),
				'file'  => $found ? flxp_short_path( $found ) : '',
				'found' => (bool) $found,
			);
		}
	}

	public static function on_wp( $wp ) {
		$qo            = get_queried_object();
		self::$request = array(
			'request'       => (string) $wp->request,
			'matched_rule'  => (string) $wp->matched_rule,
			'matched_query' => (string) $wp->matched_query,
			'query_vars'    => array_map(
				function ( $v ) {
					return is_scalar( $v ) ? flxp_cut( (string) $v, 200 ) : '[' . gettype( $v ) . ']';
				},
				array_filter(
					(array) $wp->query_vars,
					function ( $v ) {
						return '' !== $v && null !== $v && array() !== $v;
					}
				)
			),
			'queried'       => $qo ? array(
				'type' => get_class( $qo ),
				'id'   => (int) get_queried_object_id(),
				'name' => $qo instanceof WP_Post ? get_the_title( $qo ) : ( isset( $qo->name ) ? $qo->name : ( isset( $qo->display_name ) ? $qo->display_name : '' ) ),
			) : null,
		);
	}

	public static function on_textdomain( $domain, $mofile ) {
		if ( count( self::$lang ) >= 300 ) {
			return;
		}
		$l10n         = preg_replace( '/\.mo$/', '.l10n.php', (string) $mofile );
		self::$lang[] = array(
			'domain' => (string) $domain,
			'file'   => flxp_short_path( $mofile ),
			'found'  => is_readable( $mofile ) || is_readable( $l10n ),
		);
	}

	public static function on_transient( $name, $value, $expiration ) {
		if ( count( self::$transients ) >= self::MAX_ITEMS ) {
			return;
		}
		$t                  = flxp_trace_info( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 20 ) ); // phpcs:ignore
		self::$transients[] = array(
			'name'   => (string) $name,
			'site'   => in_array( current_action(), array( 'set_site_transient', 'setted_site_transient' ), true ),
			'exp'    => (int) $expiration,
			'size'   => strlen( maybe_serialize( $value ) ),
			'comp'   => $t['component'],
			'caller' => $t['caller'],
		);
	}

	public static function on_cap( $allcaps, $caps, $args, $user ) {
		if ( count( self::$caps ) < 1000 && ! empty( $args[0] ) ) {
			$ok = true;
			foreach ( (array) $caps as $c ) {
				if ( empty( $allcaps[ $c ] ) ) {
					$ok = false;
					break;
				}
			}
			$key = md5( wp_json_encode( array( $args[0], isset( $args[2] ) && is_scalar( $args[2] ) ? $args[2] : '', $user->ID, $ok ) ) );
			if ( isset( self::$caps[ $key ] ) ) {
				self::$caps[ $key ]['count']++;
			} else {
				$t                  = flxp_trace_info( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 20 ) ); // phpcs:ignore
				self::$caps[ $key ] = array(
					'cap'    => (string) $args[0],
					'arg'    => isset( $args[2] ) && is_scalar( $args[2] ) ? (string) $args[2] : '',
					'user'   => (int) $user->ID,
					'result' => $ok,
					'comp'   => $t['component'],
					'caller' => $t['caller'],
					'count'  => 1,
				);
			}
		}
		return $allcaps;
	}

	/* ---------------------------------------------------------------- Bitiş */

	/** İstek türü. */
	public static function request_type() {
		if ( wp_doing_cron() ) {
			return 'cron';
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}
		if ( wp_doing_ajax() ) {
			return 'ajax';
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return 'xmlrpc';
		}
		if ( is_admin() ) {
			return 'admin';
		}
		return 'front';
	}

	/** WordPress shutdown (öncelik 0): veriyi dondur, gerekirse paneli bas. */
	public static function on_wp_shutdown() {
		self::$snapshot = self::snapshot();
		if ( self::$panel ) {
			FLXP_Panel::maybe_output( self::$snapshot );
		}
	}

	/** PHP kapanışı: ölümcül hata yakala, gerekirse kaydet. WordPress'in shutdown'ından sonra çalışır. */
	public static function on_php_shutdown() {
		$e = error_get_last();
		if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ) ) {
			$key = md5( $e['type'] . $e['file'] . $e['line'] . $e['message'] );
			if ( ! isset( self::$errors[ $key ] ) ) {
				// "Uncaught Error: ... Stack trace: #0 ..." mesajını ayır: yığın ayrı alana.
				$msg   = (string) $e['message'];
				$stack = array();
				if ( preg_match( '/^(.*?)\s*Stack trace:\s*(.*)$/s', $msg, $m ) ) {
					$msg   = $m[1];
					$stack = array_slice( array_filter( array_map( 'trim', preg_split( '/\s*#\d+\s+/', $m[2] ) ) ), 0, 12 );
				}
				$abs                  = wp_normalize_path( ABSPATH );
				self::$errors[ $key ] = array(
					'level'   => 'fatal',
					'message' => flxp_cut( flxp_mask( str_replace( $abs, '', wp_normalize_path( $msg ) ) ), 1500 ),
					'file'    => flxp_short_path( $e['file'] ),
					'line'    => (int) $e['line'],
					'comp'    => flxp_component_from_file( $e['file'] ),
					'stack'   => array_values( array_map( function ( $l ) use ( $abs ) { return str_replace( $abs, '', wp_normalize_path( $l ) ); }, $stack ) ),
					'count'   => 1,
				);
			}
			self::$snapshot = null; // Ölümcül hatada WordPress shutdown'ı yarım kalmış olabilir.
		}
		try {
			$snap = self::$snapshot ? self::$snapshot : self::snapshot();
			// Panelde gösterilen ama sonradan eklenen hataları da al.
			$snap['php']                  = array_values( self::$errors );
			$snap['overview']['errors']   = self::count_errors( $snap['php'] );
			$snap['overview']['fatal']    = self::has_fatal( $snap['php'] );
			$snap['overview']['ms']       = round( ( microtime( true ) - self::$start ) * 1000 );
			if ( $snap['overview']['fatal'] && $snap['overview']['status'] < 500 ) {
				$snap['overview']['status'] = 500;
			}
			FLXP_Store::maybe_record( $snap );
		} catch ( Throwable $ex ) { // phpcs:ignore
			// Flux Speed asla isteği bozmaz.
		}
	}

	private static function count_errors( $php ) {
		$n = 0;
		foreach ( $php as $e ) {
			if ( in_array( $e['level'], array( 'fatal', 'warning' ), true ) ) {
				$n += $e['count'];
			}
		}
		return $n;
	}

	private static function has_fatal( $php ) {
		foreach ( $php as $e ) {
			if ( 'fatal' === $e['level'] ) {
				return true;
			}
		}
		return false;
	}

	/** O ana kadar toplanan her şeyi tek dizide döndürür. */
	public static function snapshot() {
		global $wpdb, $wp_actions;
		$s    = flxp_settings();
		$ms   = round( ( microtime( true ) - self::$start ) * 1000 );
		$url  = ( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' ); // phpcs:ignore
		$type = self::request_type();
		$url  = self::label_url( $url, $type );

		// Veritabanı.
		$db = array(
			'queries'      => array(),
			'total'        => (int) $wpdb->num_queries,
			'ms'           => 0,
			'dupes'        => array(),
			'by_component' => array(),
			'by_type'      => array(),
			'slow'         => 0,
			'errors'       => 0,
			'detailed'     => false,
		);
		if ( 'full' === self::$mode && ! empty( $wpdb->queries ) ) {
			$db['detailed'] = true;
			$slow_ms        = (float) $s['slow_query_ms'];
			$groups         = array();
			foreach ( $wpdb->queries as $i => $q ) {
				$sql   = (string) $q[0];
				$qms   = round( (float) $q[1] * 1000, 2 );
				$extra = isset( $q[4] ) && is_array( $q[4] ) ? $q[4] : array();
				$comp  = isset( $extra['flxp_c'] ) ? $extra['flxp_c'] : array( 'type' => 'other', 'slug' => 'other' );
				$qt    = flxp_sql_type( $sql );
				$err   = isset( $extra['flxp_e'] ) ? $extra['flxp_e'] : '';
				// dbDelta yeni tabloyu kurmadan önce DESCRIBE ile yokluğunu yoklar; bu beklenen bir sonuç, hata değil.
				if ( $err && 0 === stripos( ltrim( $sql ), 'DESCRIBE ' ) && false !== stripos( $err, "doesn't exist" ) ) {
					$err = '';
				}
				$slow  = $qms >= $slow_ms;
				$db['ms'] += $qms;
				$db['slow'] += $slow ? 1 : 0;
				$db['errors'] += $err ? 1 : 0;
				$db['by_type'][ $qt ] = ( $db['by_type'][ $qt ] ?? 0 ) + 1;
				$ck = flxp_component_key( $comp );
				if ( ! isset( $db['by_component'][ $ck ] ) ) {
					$db['by_component'][ $ck ] = array( 'comp' => $comp, 'count' => 0, 'ms' => 0 );
				}
				$db['by_component'][ $ck ]['count']++;
				$db['by_component'][ $ck ]['ms'] += $qms;

				$norm = md5( $sql );
				if ( ! isset( $groups[ $norm ] ) ) {
					$groups[ $norm ] = array( 'sql' => flxp_cut( flxp_mask( $sql ), 600 ), 'count' => 0, 'ms' => 0, 'callers' => array() );
				}
				$groups[ $norm ]['count']++;
				$groups[ $norm ]['ms'] += $qms;
				$caller = isset( $extra['flxp_f'] ) ? $extra['flxp_f'] : '';
				if ( $caller ) {
					$groups[ $norm ]['callers'][ $caller ] = ( $groups[ $norm ]['callers'][ $caller ] ?? 0 ) + 1;
				}

				$row = array(
					'i'      => $i + 1,
					'sql'    => flxp_cut( flxp_mask( $sql ), 2000 ),
					'ms'     => $qms,
					'type'   => $qt,
					'comp'   => $comp,
					'caller' => $caller,
					'rows'   => null,
				);
				if ( $err ) {
					$row['error'] = $err;
				}
				if ( $slow || $err ) {
					$row['stack'] = isset( $extra['flxp_s'] ) ? $extra['flxp_s'] : array();
				}
				$db['queries'][] = $row;
			}
			foreach ( $groups as $g ) {
				if ( $g['count'] > 1 ) {
					arsort( $g['callers'] );
					$g['ms']         = round( $g['ms'], 2 );
					$db['dupes'][]   = $g;
				}
			}
			usort(
				$db['dupes'],
				function ( $a, $b ) {
					return $b['count'] <=> $a['count'];
				}
			);
			$db['dupes']        = array_slice( $db['dupes'], 0, 50 );
			$db['by_component'] = array_values( $db['by_component'] );
			foreach ( $db['by_component'] as &$bc ) {
				$bc['ms'] = round( $bc['ms'], 2 );
			}
			unset( $bc );
			usort(
				$db['by_component'],
				function ( $a, $b ) {
					return $b['ms'] <=> $a['ms'];
				}
			);
			$db['ms'] = round( $db['ms'], 2 );
		}

		// HTTP.
		$http_ms   = 0;
		$http_fail = 0;
		foreach ( self::$http as $h ) {
			$http_ms += $h['ms'];
			if ( $h['error'] || $h['status'] >= 400 ) {
				$http_fail++;
			}
		}

		// Script/stil.
		$assets = array();
		if ( self::$panel ) {
			$assets = array(
				'scripts' => self::assets( isset( $GLOBALS['wp_scripts'] ) ? $GLOBALS['wp_scripts'] : null ),
				'styles'  => self::assets( isset( $GLOBALS['wp_styles'] ) ? $GLOBALS['wp_styles'] : null ),
			);
		}

		// Koşullar (yalnız ön yüz).
		$cond = array();
		if ( 'front' === $type && did_action( 'wp' ) ) {
			foreach ( array( 'is_404', 'is_admin', 'is_archive', 'is_attachment', 'is_author', 'is_category', 'is_date', 'is_embed', 'is_feed', 'is_front_page', 'is_home', 'is_page', 'is_page_template', 'is_paged', 'is_post_type_archive', 'is_preview', 'is_privacy_policy', 'is_search', 'is_single', 'is_singular', 'is_sticky', 'is_tag', 'is_tax', 'is_user_logged_in', 'is_ssl', 'is_rtl', 'is_shop', 'is_product', 'is_product_category', 'is_cart', 'is_checkout', 'is_account_page', 'is_woocommerce' ) as $fn ) {
				if ( function_exists( $fn ) ) {
					$cond[ $fn ] = (bool) call_user_func( $fn );
				}
			}
		}

		// Hook'lar.
		$hooks = array( 'total' => 0, 'top' => array() );
		if ( is_array( $wp_actions ) ) {
			$hooks['total'] = count( $wp_actions );
			$top            = $wp_actions;
			arsort( $top );
			foreach ( array_slice( $top, 0, 40, true ) as $name => $n ) {
				$hooks['top'][] = array( 'hook' => (string) $name, 'count' => (int) $n );
			}
		}

		// Şablon.
		$template = self::$template;
		if ( 'front' === $type ) {
			$template['theme']      = get_stylesheet();
			$template['parent']     = get_template() !== get_stylesheet() ? get_template() : '';
			$template['block']      = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
			$template['block_id']   = isset( $GLOBALS['_wp_current_template_id'] ) ? (string) $GLOBALS['_wp_current_template_id'] : '';
			$template['parts']      = self::$parts;
			$template['body_class'] = did_action( 'wp_head' ) ? implode( ' ', get_body_class() ) : '';
		}

		$timers = array();
		foreach ( self::$timers as $name => $t ) {
			$timers[] = array( 'name' => $name, 'ms' => $t['ms'], 'mem_kb' => null === $t['ms'] ? null : round( $t['mem'] / 1024 ) );
		}
		$milestones = array();
		foreach ( self::$milestones as $name => $m ) {
			$milestones[] = array( 'hook' => $name, 'ms' => $m[0], 'mem_kb' => $m[1] );
		}

		$php = array_values( self::$errors );
		$uid = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;

		return array(
			'v'          => 1,
			'overview'   => array(
				'url'       => flxp_cut( flxp_mask_url( $url ), 500 ),
				'method'    => isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( $_SERVER['REQUEST_METHOD'] ) : 'get', // phpcs:ignore
				'type'      => $type,
				'status'    => (int) http_response_code(),
				'ms'        => $ms,
				'mem_kb'    => round( memory_get_peak_usage() / 1024 ),
				'mem_limit' => (string) ini_get( 'memory_limit' ),
				'queries'   => (int) $wpdb->num_queries,
				'query_ms'  => $db['ms'],
				'http'      => count( self::$http ),
				'http_ms'   => round( $http_ms, 1 ),
				'http_fail' => $http_fail,
				'errors'    => self::count_errors( $php ),
				'fatal'     => self::has_fatal( $php ),
				'notices'   => count( $php ),
				'wrong'     => count( self::$wrong ),
				'user'      => (int) $uid,
				'time'      => time(),
				'mode'      => self::$mode,
				'sampled'   => self::$sampled,
				'profile'   => self::$profile,
				'php'       => PHP_VERSION,
				'wp'        => get_bloginfo( 'version' ),
			),
			'milestones' => $milestones,
			'db'         => $db,
			'php'        => $php,
			'wrong'      => array_values( self::$wrong ),
			'http'       => self::$http,
			'template'   => $template,
			'request'    => self::$request,
			'cond'       => $cond,
			'caps'       => array_values( self::$caps ),
			'assets'     => $assets,
			'lang'       => self::$lang,
			'transients' => self::$transients,
			'hooks'      => $hooks,
			'logs'       => self::$logs,
			'timers'     => $timers,
			'cron'       => 'cron' === $type && class_exists( 'FLXP_Cron_Timing' ) ? FLXP_Cron_Timing::current() : array(),
		);
	}

	/**
	 * admin-ajax.php isteklerinde "action" gövdede (POST) gelir; adres hep aynı görünür ve hangi eklentinin
	 * yavaş olduğu anlaşılmaz. Adrese ?action=... (Heartbeat'te ayrıca hangi paketlerin veri istediği) eklenir.
	 */
	public static function label_url( $url, $type ) {
		if ( 'ajax' !== $type || empty( $_REQUEST['action'] ) || false !== strpos( $url, 'action=' ) ) { // phpcs:ignore
			return $url;
		}
		$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) ); // phpcs:ignore
		$extra  = array( 'action' => $action );
		if ( 'heartbeat' === $action && ! empty( $_POST['data'] ) && is_array( $_POST['data'] ) ) { // phpcs:ignore
			$keys = array_slice( array_map( 'sanitize_key', array_keys( wp_unslash( $_POST['data'] ) ) ), 0, 12 ); // phpcs:ignore
			if ( $keys ) {
				$extra['data'] = implode( ',', $keys );
			}
		}
		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . str_replace( '%2C', ',', http_build_query( $extra, '', '&' ) );
	}

	/** Kuyruktaki script/stil listesi + eksik bağımlılıklar. */
	private static function assets( $deps ) {
		if ( ! $deps instanceof WP_Dependencies ) {
			return array();
		}
		$out     = array();
		$handles = array_unique( array_merge( (array) $deps->done, (array) $deps->queue ) );
		foreach ( $handles as $h ) {
			if ( ! isset( $deps->registered[ $h ] ) ) {
				continue;
			}
			$d       = $deps->registered[ $h ];
			$missing = array();
			foreach ( (array) $d->deps as $dep ) {
				if ( ! isset( $deps->registered[ $dep ] ) ) {
					$missing[] = $dep;
				}
			}
			$src   = is_string( $d->src ) ? $d->src : '';
			$path  = '';
			if ( $src && 0 === strpos( $src, content_url() ) ) {
				$path = WP_CONTENT_DIR . substr( strtok( $src, '?' ), strlen( content_url() ) );
			}
			$out[] = array(
				'handle'  => (string) $h,
				'src'     => flxp_cut( $src ? str_replace( home_url(), '', $src ) : '', 300 ),
				'ver'     => is_scalar( $d->ver ) ? (string) $d->ver : '',
				'deps'    => array_values( (array) $d->deps ),
				'missing' => $missing,
				'footer'  => ! empty( $d->extra['group'] ),
				'done'    => in_array( $h, (array) $deps->done, true ),
				'comp'    => $path ? flxp_component_from_file( $path ) : array( 'type' => $src ? 'other' : 'core', 'slug' => $src ? 'other' : 'core' ),
			);
		}
		return $out;
	}
}
