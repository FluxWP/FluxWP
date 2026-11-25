<?php
/**
 * Flux Speed service layer: the data and the actions of the Performance screens, in one place.
 * The GraphQL schema, REST and the AI tools call these methods, so the three
 * cannot drift apart. Output is plain arrays whose keys match the GraphQL field names (camelCase);
 * nothing here needs Flux GraphQL.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type Row array<string,mixed>
 * @phpstan-type Rows list<array<string,mixed>>
 */
final class FLXP_Speed_Service {

	/** Seconds a computed summary is reused. */
	public const SUMMARY_TTL = 30;

	/** Cache tag of the settings-like data (kept for the GraphQL cache purge convention). */
	public const CACHE_TAG = 'flx-speed:data';

	/** Page size of the records list (the screen's list). */
	public const RECORDS_PAGE = 30;

	/* ------------------------------------------------------------ Typed readers */

	/**
	 * @param mixed $v Value.
	 */
	private static function s( $v, string $default = '' ): string {
		return is_scalar( $v ) ? (string) $v : $default;
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function i( $v, int $default = 0 ): int {
		return is_numeric( $v ) ? (int) $v : $default;
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function f( $v, float $default = 0.0 ): float {
		return is_numeric( $v ) ? (float) $v : $default;
	}

	/** Nesne önbelleği sahibinin okunur adı. */
	private static function object_cache_label( string $owner ): string {
		$names = array( 'redis' => 'Redis', 'memcached' => 'Memcached', 'litespeed' => 'LiteSpeed Cache', 'sqlite' => 'SQLite Object Cache' );
		return $names[ $owner ] ?? __( 'External', 'flx-speed' );
	}

	/**
	 * @param mixed $v Value.
	 * @return array<string,mixed>
	 */
	private static function a( $v ): array {
		$out = array();
		if ( is_array( $v ) ) {
			foreach ( $v as $k => $x ) {
				$out[ (string) $k ] = $x;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $v Value.
	 * @return list<array<string,mixed>>
	 */
	private static function rows( $v ): array {
		$out = array();
		if ( is_array( $v ) ) {
			foreach ( $v as $x ) {
				$out[] = self::a( $x );
			}
		}
		return $out;
	}

	/**
	 * Section rows: label → value, as a list for GraphQL.
	 *
	 * @param array<string,mixed> $kv Pairs.
	 * @return list<array{label:string,value:string}>
	 */
	private static function kv( array $kv ): array {
		$out = array();
		foreach ( $kv as $k => $v ) {
			$out[] = array(
				'label' => (string) $k,
				'value' => is_bool( $v ) ? ( $v ? __( 'Yes', 'flx-speed' ) : __( 'No', 'flx-speed' ) ) : self::s( $v ),
			);
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $kv Pairs.
	 * @return array{key:string,title:string,rows:list<array{label:string,value:string}>}
	 */
	private static function section( string $key, string $title, array $kv ): array {
		return array(
			'key'   => $key,
			'title' => $title,
			'rows'  => self::kv( $kv ),
		);
	}

	/**
	 * A point in time for display: site-time short and long forms plus the relative text.
	 *
	 * @return array{timestamp:int,short:string,date:string,relative:string}
	 */
	public static function when( int $ts ): array {
		return array(
			'timestamp' => $ts,
			'short'     => (string) wp_date( 'd.m H:i:s', $ts ),
			'date'      => self::s( flx_date( $ts ) ),
			'relative'  => self::s( flx_time_ago( $ts ) ),
		);
	}

	private static function kb( int $kb ): string {
		return $kb >= 1024 ? number_format_i18n( $kb / 1024, 1 ) . ' MB' : number_format_i18n( $kb ) . ' KB';
	}

	/* ------------------------------------------------------------ Access */

	public static function can_view(): bool {
		return (bool) flxp_can_view();
	}

	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Tabs the current person may open, in menu order.
	 *
	 * @return list<array{key:string,label:string,adminOnly:bool}>
	 */
	public static function tabs(): array {
		$all = array(
			array( 'summary', __( 'Summary', 'flx-speed' ), false ),
			array( 'records', __( 'Records', 'flx-speed' ), false ),
			array( 'server', __( 'Server', 'flx-speed' ), false ),
			array( 'database', __( 'Database', 'flx-speed' ), false ),
			array( 'autoload', 'Autoload', false ),
			array( 'cron', 'Cron', false ),
			array( 'system', __( 'System check', 'flx-speed' ), true ),
			array( 'pagespeed', 'PageSpeed', true ),
			array( 'cache', __( 'Cache', 'flx-speed' ), true ),
			array( 'settings', __( 'Settings', 'flx-speed' ), true ),
		);
		$out = array();
		foreach ( $all as $t ) {
			if ( $t[2] && ! self::can_manage() ) {
				continue;
			}
			$out[] = array(
				'key'       => $t[0],
				'label'     => $t[1],
				'adminOnly' => $t[2],
			);
		}
		return $out;
	}

	/**
	 * Links and facts the screen header needs.
	 *
	 * @return array{canManage:bool,recording:bool,classicUrl:string,adminUrl:string,tabs:list<array{key:string,label:string,adminOnly:bool}>,flags:list<array{key:string,label:string}>,types:list<array{key:string,label:string}>}
	 */
	public static function config(): array {
		$flags = array();
		foreach ( self::a( FLXP_Store::flag_labels() ) as $k => $l ) {
			$flags[] = array(
				'key'   => (string) $k,
				'label' => self::s( $l ),
			);
		}
		$types = array();
		foreach ( self::a( FLXP_Store::type_labels() ) as $k => $l ) {
			$types[] = array(
				'key'   => (string) $k,
				'label' => self::s( $l ),
			);
		}
		return array(
			'canManage'  => self::can_manage(),
			'recording'  => ! empty( flxp_settings( 'record' ) ),
			'classicUrl' => admin_url( 'admin.php?page=flx-speed' ), // Old field kept for app clients: the same (React) page.
			'adminUrl'   => admin_url(),
			'tabs'       => self::tabs(),
			'flags'      => $flags,
			'types'      => $types,
		);
	}

	/* ------------------------------------------------------------ Live meters and hints */

	/**
	 * The live cards (CPU, memory, disk, PHP memory, uptime) and the note under them.
	 *
	 * @return array{meters:list<array{key:string,label:string,text:string,sub:string,pct:?int}>,note:string}
	 */
	public static function live(): array {
		$l      = self::a( flxp_server_live( 1 ) );
		$meters = array();
		foreach ( self::a( flxp_live_view( $l ) ) as $k => $c ) {
			$c        = self::a( $c );
			$meters[] = array(
				'key'   => $k,
				'label' => self::s( $c['label'] ?? '' ),
				'text'  => self::s( $c['text'] ?? '' ),
				'sub'   => self::s( $c['sub'] ?? '' ),
				'pct'   => isset( $c['pct'] ) && is_numeric( $c['pct'] ) ? (int) $c['pct'] : null,
			);
		}
		return array(
			'meters' => $meters,
			'note'   => self::s( flxp_live_note( $l ) ),
		);
	}

	/**
	 * Performance checks that need attention (the Suggestions box of the summary).
	 *
	 * @return list<array{status:string,label:string,message:string,actionUrl:?string,actionLabel:?string}>
	 */
	public static function hints(): array {
		$out = array();
		foreach ( self::rows( flxp_server_checks() ) as $c ) {
			$st = self::s( $c['status'] ?? '' );
			if ( 'error' !== $st && 'warning' !== $st ) {
				continue;
			}
			$url   = self::s( $c['action_url'] ?? '' );
			$out[] = array(
				'status'      => $st,
				'label'       => self::s( $c['label'] ?? '' ),
				'message'     => self::s( $c['message'] ?? '' ),
				'actionUrl'   => '' === $url ? null : $url,
				'actionLabel' => '' === $url ? null : self::s( $c['action_label'] ?? '' ),
			);
		}
		return $out;
	}

	/* ------------------------------------------------------------ Summary */

	/**
	 * @return array<string,mixed>
	 */
	public static function summary( int $hours ): array {
		$hours = max( 1, min( 720, $hours ) );
		// The summary scans the records and their details (a few hundred ms on a busy log); the screen reads
		// it on every open, so a result younger than SUMMARY_TTL is reused. Clearing the log drops it.
		$key = 'flxp_summary_' . self::i( FLXP_Store::generation() ) . '_' . $hours;
		$hit = get_transient( $key );
		if ( is_array( $hit ) ) {
			return self::a( $hit );
		}
		$s     = self::a( FLXP_Store::summary( $hours ) );
		$fl    = self::a( FLXP_Store::flag_labels() );
		$tl    = self::a( FLXP_Store::type_labels() );

		$flags = array();
		foreach ( self::a( $s['flags'] ?? array() ) as $k => $n ) {
			if ( in_array( $k, array( 'fatal', 'error', '5xx', 'slow', 'slow_query', 'http_fail' ), true ) && self::i( $n ) > 0 ) {
				$flags[] = array(
					'key'   => $k,
					'label' => self::s( $fl[ $k ] ?? $k ),
					'count' => self::i( $n ),
					'tone'  => in_array( $k, array( 'fatal', 'error', '5xx', 'http_fail' ), true ) ? 'ERROR' : 'WARNING',
				);
			}
		}
		$by_type = array();
		foreach ( self::a( $s['by_type'] ?? array() ) as $ty => $r ) {
			$r         = self::a( $r );
			$by_type[] = array(
				'type'  => $ty,
				'label' => self::s( $tl[ $ty ] ?? $ty ),
				'count' => self::i( $r['count'] ?? 0 ),
				'msAvg' => self::i( $r['ms_avg'] ?? 0 ),
				'msP50' => self::i( $r['ms_p50'] ?? 0 ),
				'msP95' => self::i( $r['ms_p95'] ?? 0 ),
				'msMax' => self::i( $r['ms_max'] ?? 0 ),
			);
		}
		$urls = array();
		foreach ( self::rows( $s['slowest_urls'] ?? array() ) as $u ) {
			$urls[] = array(
				'url'    => self::s( $u['url'] ?? '' ),
				'count'  => self::i( $u['count'] ?? 0 ),
				'msAvg'  => self::i( $u['ms_avg'] ?? 0 ),
				'msMax'  => self::i( $u['ms_max'] ?? 0 ),
				'errors' => self::i( $u['errors'] ?? 0 ),
			);
		}
		$comps = array();
		foreach ( self::rows( $s['db_components'] ?? array() ) as $c ) {
			$comps[] = array(
				'component'    => self::s( $c['component'] ?? '' ),
				'requests'     => self::i( $c['requests'] ?? 0 ),
				'queries'      => self::i( $c['queries'] ?? 0 ),
				'msPerRequest' => self::f( $c['ms_per_request'] ?? 0 ),
			);
		}
		$queries = array();
		foreach ( self::rows( FLXP_Store::slow_queries( $hours, 10 ) ) as $q ) {
			$err       = self::s( $q['error'] ?? '' );
			$queries[] = array(
				'sql'       => self::s( $q['sql'] ?? '' ),
				'error'     => '' === $err ? null : $err,
				'component' => self::s( $q['component'] ?? '' ),
				'count'     => self::i( $q['count'] ?? 0 ),
				'msAvg'     => self::f( $q['ms_avg'] ?? 0 ),
				'msMax'     => self::f( $q['ms_max'] ?? 0 ),
				'lastId'    => self::i( $q['last_id'] ?? 0 ),
			);
		}
		$errors = array();
		foreach ( self::rows( FLXP_Store::php_errors( $hours, 10 ) ) as $e ) {
			$errors[] = array(
				'level'     => self::s( $e['level'] ?? '' ),
				'message'   => self::s( $e['message'] ?? '' ),
				'file'      => self::s( $e['file'] ?? '' ),
				'component' => self::s( $e['component'] ?? '' ),
				'requests'  => self::i( $e['requests'] ?? 0 ),
				'lastId'    => self::i( $e['last_id'] ?? 0 ),
			);
		}
		$http = array();
		foreach ( self::rows( FLXP_Store::http_calls( $hours, false, 10 ) ) as $h ) {
			$http[] = array(
				'endpoint'  => self::s( $h['endpoint'] ?? '' ),
				'component' => self::s( $h['component'] ?? '' ),
				'count'     => self::i( $h['count'] ?? 0 ),
				'failed'    => self::i( $h['failed'] ?? 0 ),
				'msAvg'     => self::f( $h['ms_avg'] ?? 0 ),
				'lastError' => self::s( $h['last_error'] ?? '' ),
			);
		}
		$out = array(
			'hours'        => $hours,
			'recorded'     => self::i( $s['recorded'] ?? 0 ),
			'msAvg'        => self::i( $s['ms_avg'] ?? 0 ),
			'msP95'        => self::i( $s['ms_p95'] ?? 0 ),
			'msMax'        => self::i( $s['ms_max'] ?? 0 ),
			'flags'        => $flags,
			'byType'       => $by_type,
			'slowestUrls'  => $urls,
			'components'   => $comps,
			'slowQueries'  => $queries,
			'phpErrors'    => $errors,
			'httpCalls'    => $http,
		);
		set_transient( $key, $out, self::SUMMARY_TTL );
		return $out;
	}

	/* ------------------------------------------------------------ Records */

	/**
	 * One record row, GraphQL-ready.
	 *
	 * @param array<string,mixed> $r Store row.
	 * @return array<string,mixed>
	 */
	private static function record_row( array $r ): array {
		$fl    = self::a( FLXP_Store::flag_labels() );
		$tl    = self::a( FLXP_Store::type_labels() );
		$flags = array();
		foreach ( (array) ( $r['flags'] ?? array() ) as $f ) {
			$f       = self::s( $f );
			$flags[] = array(
				'key'   => $f,
				'label' => self::s( $fl[ $f ] ?? $f ),
			);
		}
		$ty = self::s( $r['type'] ?? '' );
		$ts = (int) strtotime( self::s( $r['created'] ?? '' ) );
		return array(
			'databaseId' => self::i( $r['id'] ?? 0 ),
			'at'         => self::when( $ts ),
			'url'        => self::s( $r['url'] ?? '' ),
			'method'     => self::s( $r['method'] ?? '' ),
			'type'       => $ty,
			'typeLabel'  => self::s( $tl[ $ty ] ?? $ty ),
			'status'     => self::i( $r['status'] ?? 0 ),
			'ms'         => self::i( $r['ms'] ?? 0 ),
			'memKb'      => self::i( $r['mem_kb'] ?? 0 ),
			'queries'    => self::i( $r['queries'] ?? 0 ),
			'queryMs'    => self::i( $r['query_ms'] ?? 0 ),
			'http'       => self::i( $r['http'] ?? 0 ),
			'errors'     => self::i( $r['errors'] ?? 0 ),
			'flags'      => $flags,
			'failed'     => (bool) array_intersect( array( 'fatal', 'error', '5xx' ), array_map( static fn( $x ): string => self::s( $x ), (array) ( $r['flags'] ?? array() ) ) ),
		);
	}

	/**
	 * A page of the records list.
	 *
	 * @param array{hours?:int,flag?:string,type?:string,q?:string,order?:string} $filter Filter.
	 * @return array{items:list<array<string,mixed>>,total:int}
	 */
	public static function records( array $filter, int $offset, int $limit ): array {
		$limit = max( 1, $limit );
		$res   = self::a(
			FLXP_Store::query(
				array(
					'hours'    => max( 0, (int) ( $filter['hours'] ?? 0 ) ),
					'flag'     => sanitize_key( (string) ( $filter['flag'] ?? '' ) ),
					'type'     => sanitize_key( (string) ( $filter['type'] ?? '' ) ),
					'q'        => sanitize_text_field( (string) ( $filter['q'] ?? '' ) ),
					'order'    => 'slow' === ( $filter['order'] ?? '' ) ? 'slow' : 'new',
					'offset'   => max( 0, $offset ),
					'per_page' => $limit,
				)
			)
		);
		$items = array();
		foreach ( self::rows( $res['items'] ?? array() ) as $r ) {
			$items[] = self::record_row( $r );
		}
		return array(
			'items' => $items,
			'total' => self::i( $res['total'] ?? 0 ),
		);
	}

	/**
	 * One record with its stored profile (the panel's data).
	 *
	 * @return array<string,mixed>|null
	 */
	public static function record( int $id ): ?array {
		$r = FLXP_Store::get( $id );
		if ( ! is_array( $r ) ) {
			return null;
		}
		$r    = self::a( $r );
		$data = self::a( $r['data'] ?? array() );
		if ( empty( $data['overview'] ) ) {
			return null;
		}
		return self::record_row( $r ) + array(
			'data'   => $data,
			'labels' => self::a( FLXP_Panel::component_labels( $data ) ),
		);
	}

	/**
	 * What the shared request panel script needs next to a record: thresholds, texts and asset URLs.
	 *
	 * @return array{slowQueryMs:int,slowMs:int,strings:array<string,mixed>,scriptUrl:string,styleUrl:string}
	 */
	public static function panel_assets(): array {
		return array(
			'slowQueryMs' => self::i( flxp_settings( 'slow_query_ms' ) ),
			'slowMs'      => self::i( flxp_settings( 'slow_ms' ) ),
			'strings'     => self::a( flxp_js_strings() ),
			'scriptUrl'   => FLXP_URL . 'assets/panel.js?ver=' . FLXP_VERSION,
			'styleUrl'    => FLXP_URL . 'assets/panel.css?ver=' . FLXP_VERSION,
		);
	}

	/**
	 * Deletes every record.
	 */
	public static function records_clear(): void {
		FLXP_Store::clear();
	}

	/**
	 * Opens a page of this site as a visitor and records it in full detail.
	 *
	 * @return array{id:int,status:int,url:string}|WP_Error
	 */
	public static function profile( string $url ) {
		$r = flxp_profile_url( $url );
		if ( $r instanceof WP_Error ) {
			return $r;
		}
		$r = self::a( $r );
		return array(
			'id'     => self::i( $r['id'] ?? 0 ),
			'status' => self::i( $r['status'] ?? 0 ),
			'url'    => self::s( $r['url'] ?? '' ),
		);
	}

	/* ------------------------------------------------------------ Server */

	/**
	 * Key-value boxes of the Server tab, same rows on every client.
	 *
	 * @return list<array{key:string,title:string,rows:list<array{label:string,value:string}>}>
	 */
	public static function server_sections(): array {
		$st  = self::a( flxp_server_stats() );
		$e   = self::a( flxp_environment() );
		$srv = self::a( $st['server'] ?? array() );
		$esv = self::a( $e['server'] ?? array() );
		$php = self::a( $st['php'] ?? array() );
		$live = self::a( $st['live'] ?? array() );
		$out = array();

		$scope = self::s( $live['scope'] ?? '' );
		if ( 'account' === $scope ) {
			$scope_label = __( 'Account limit (cgroup)', 'flx-speed' );
		} elseif ( 'shared' === $scope ) {
			$scope_label = __( 'Shared hosting: account limit cannot be read', 'flx-speed' );
		} else {
			$scope_label = __( 'Server belongs to this site (VPS/dedicated)', 'flx-speed' );
		}
		$out[] = self::section(
			'server',
			__( 'Server', 'flx-speed' ),
			array(
				__( 'Operating system', 'flx-speed' ) => $srv['os'] ?? '',
				__( 'Software', 'flx-speed' )         => $srv['software'] ?? '',
				__( 'Hostname', 'flx-speed' )         => $srv['hostname'] ?? '',
				__( 'IP address', 'flx-speed' )       => $srv['ip'] ?? '',
				__( 'Port', 'flx-speed' )             => $srv['port'] ?? '',
				__( 'Document root', 'flx-speed' )    => $srv['doc_root'] ?? '',
				__( 'Resource values', 'flx-speed' )  => $scope_label,
				'CloudLinux'                          => ! empty( $srv['cloudlinux'] ),
				'HTTPS'                               => ! empty( $esv['https'] ),
				'LiteSpeed'                           => ! empty( $esv['litespeed'] ),
			)
		);

		$fmt = static fn( $b ): string => ( null === $b || self::f( $b ) <= 0 ) ? '—' : (string) size_format( (int) self::f( $b ), 1 );
		$du  = self::a( $live['disk_usage'] ?? array() );
		if ( $du ) {
			$out[] = self::section(
				'account-disk',
				__( 'Account disk', 'flx-speed' ),
				array(
					__( 'Scanned folder', 'flx-speed' )             => $du['root'] ?? '',
					__( 'Files', 'flx-speed' )                      => null === ( $du['bytes'] ?? null ) ? __( 'Calculating…', 'flx-speed' ) : $fmt( $du['bytes'] ),
					__( 'Files and folders (inodes)', 'flx-speed' ) => null === ( $du['files'] ?? null ) ? '—' : number_format_i18n( self::i( $du['files'] ) ),
					__( 'Database', 'flx-speed' )                   => $fmt( $du['db'] ?? null ),
					__( 'Disk quota', 'flx-speed' )                 => self::f( flxp_settings( 'disk_quota_gb' ) ) > 0 ? $fmt( self::f( flxp_settings( 'disk_quota_gb' ) ) * 1073741824 ) : __( 'Not set (Settings)', 'flx-speed' ),
					__( 'Last measured', 'flx-speed' )              => self::i( $du['time'] ?? 0 ) > 0 ? self::s( flx_time_ago( self::i( $du['time'] ) ) ) . ( ! empty( $du['running'] ) ? ' · ' . __( 'refreshing', 'flx-speed' ) : '' ) : '—',
				)
			);
		}
		if ( 'machine' !== $scope || ! empty( $live['disk_shared'] ) ) {
			$m    = self::a( $live['machine'] ?? array() );
			$load = array_map( static fn( $x ): string => self::s( $x ), (array) ( $m['load'] ?? array() ) );
			$ld   = $load ? implode( ' / ', $load ) . ( null === ( $m['load_pct'] ?? null ) ? '' : ' (%' . self::s( $m['load_pct'] ) . ')' ) : '—';
			$out[] = self::section(
				'machine',
				__( 'Whole machine (shared, all accounts)', 'flx-speed' ),
				array(
					__( 'CPU cores', 'flx-speed' )         => self::i( $m['cores'] ?? 0 ) > 0 ? $m['cores'] : '—',
					__( 'Load (1/5/15 min)', 'flx-speed' ) => $ld,
					__( 'Memory', 'flx-speed' )            => self::i( $m['ram_total_kb'] ?? 0 ) > 0 ? $fmt( self::f( $m['ram_used_kb'] ?? 0 ) * 1024 ) . ' / ' . $fmt( self::f( $m['ram_total_kb'] ) * 1024 ) : '—',
					__( 'Disk', 'flx-speed' )              => self::f( $m['disk_total'] ?? 0 ) > 0 ? sprintf( /* translators: %s: free space */ __( '%s free', 'flx-speed' ), $fmt( $m['disk_free'] ?? null ) ) . ' / ' . $fmt( $m['disk_total'] ) : '—',
					__( 'Note', 'flx-speed' )              => __( 'These values belong to every account on the machine; they are not your site\'s usage or limit.', 'flx-speed' ),
				)
			);
		}

		$ext = array();
		foreach ( self::a( self::a( $e['php'] ?? array() )['extensions'] ?? array() ) as $k => $v ) {
			$ext[] = ( $v ? '✓ ' : '✗ ' ) . $k;
		}
		$phpe  = self::a( $e['php'] ?? array() );
		$out[] = self::section(
			'php',
			'PHP',
			array(
				__( 'Version', 'flx-speed' )                => self::s( $php['version'] ?? '' ) . ' (' . self::s( $php['sapi'] ?? '' ) . ')',
				'memory_limit'                              => $php['memory_limit'] ?? '',
				'WP_MEMORY_LIMIT'                           => $php['wp_memory_limit'] ?? '',
				'WP_MAX_MEMORY_LIMIT'                       => $php['wp_max_memory_limit'] ?? '',
				__( 'Memory (this request)', 'flx-speed' )  => self::kb( self::i( $php['usage_kb'] ?? 0 ) ) . ' · ' . __( 'peak', 'flx-speed' ) . ' ' . self::kb( self::i( $php['peak_kb'] ?? 0 ) ),
				'max_execution_time'                        => $php['max_execution_time'] ?? '',
				'max_input_time'                            => $php['max_input_time'] ?? '',
				'max_input_vars'                            => $php['max_input_vars'] ?? '',
				'upload_max_filesize'                       => $php['upload_max_filesize'] ?? '',
				'post_max_size'                             => $php['post_max_size'] ?? '',
				'allow_url_fopen'                           => ! empty( $php['allow_url_fopen'] ),
				'display_errors'                            => $phpe['display_errors'] ?? '',
				__( 'Disabled functions', 'flx-speed' )     => '' !== self::s( $php['disabled_functions'] ?? '' ) ? $php['disabled_functions'] : '—',
				__( 'Extensions', 'flx-speed' )             => implode( ', ', $ext ),
			)
		);

		$o = $st['opcache'] ?? null;
		if ( is_array( $o ) ) {
			$o     = self::a( $o );
			$out[] = self::section(
				'opcache',
				'OPcache',
				array(
					__( 'Status', 'flx-speed' )       => ! empty( $o['enabled'] ) ? __( 'On', 'flx-speed' ) : __( 'Off', 'flx-speed' ),
					__( 'Memory', 'flx-speed' )       => null === ( $o['used_mb'] ?? null ) ? '—' : self::s( $o['used_mb'] ) . ' MB / ' . ( self::f( $o['used_mb'] ) + self::f( $o['free_mb'] ?? 0 ) ) . ' MB',
					__( 'Hit rate', 'flx-speed' )     => null === ( $o['hit_rate'] ?? null ) ? '—' : self::s( $o['hit_rate'] ) . '%',
					__( 'Cached files', 'flx-speed' ) => null === ( $o['cached_files'] ?? null ) ? '—' : number_format_i18n( self::i( $o['cached_files'] ) ) . ' / ' . number_format_i18n( self::i( $o['max_files'] ?? 0 ) ),
					__( 'Wasted', 'flx-speed' )       => null === ( $o['wasted_pct'] ?? null ) ? '—' : self::s( $o['wasted_pct'] ) . '%',
					__( 'Restarts', 'flx-speed' )     => self::i( $o['restarts'] ?? 0 ),
				)
			);
		} else {
			$out[] = self::section( 'opcache', 'OPcache', array( __( 'Status', 'flx-speed' ) => __( 'No data (disabled or not allowed by the server).', 'flx-speed' ) ) );
		}

		$c     = self::a( $st['caches'] ?? array() );
		$out[] = self::section(
			'cache',
			__( 'Cache', 'flx-speed' ),
			array(
				__( 'Object cache', 'flx-speed' )          => ! empty( $c['object_cache'] ) ? self::object_cache_label( self::s( $c['object_cache_owner'] ?? '' ) ) : __( 'None', 'flx-speed' ),
				__( 'Page cache (WP_CACHE)', 'flx-speed' ) => ! empty( $c['page_cache'] ),
				'LiteSpeed Cache'                          => ! empty( $c['litespeed'] ),
				'APCu'                                     => ! empty( $c['apcu'] ),
				'Memcached'                                => ! empty( $c['memcached'] ),
				'Redis'                                    => ! empty( $c['redis'] ),
			)
		);

		$wp    = self::a( $e['wordpress'] ?? array() );
		$rows  = array(
			__( 'Version', 'flx-speed' )          => $wp['version'] ?? '',
			__( 'Locale', 'flx-speed' )           => $wp['locale'] ?? '',
			__( 'Timezone', 'flx-speed' )         => $wp['timezone'] ?? '',
			__( 'Permalinks', 'flx-speed' )       => '' !== self::s( $wp['permalinks'] ?? '' ) ? $wp['permalinks'] : '—',
			__( 'Environment type', 'flx-speed' ) => $wp['environment'] ?? '',
			__( 'Theme', 'flx-speed' )            => self::s( $wp['theme'] ?? '' ) . ( '' !== self::s( $wp['parent_theme'] ?? '' ) ? ' (' . self::s( $wp['parent_theme'] ) . ')' : '' ),
		);
		foreach ( self::a( $wp['constants'] ?? array() ) as $k => $v ) {
			$rows[ $k ] = null === $v ? __( 'undefined', 'flx-speed' ) : $v;
		}
		$out[] = self::section( 'wordpress', 'WordPress', $rows );

		$pl = array();
		foreach ( self::rows( $wp['plugins'] ?? array() ) as $pp ) {
			$pl[ self::s( $pp['name'] ?? '' ) ] = $pp['version'] ?? '';
		}
		foreach ( self::rows( $wp['mu_plugins'] ?? array() ) as $pp ) {
			$pl[ 'MU: ' . self::s( $pp['name'] ?? '' ) ] = $pp['version'] ?? '';
		}
		foreach ( (array) ( $wp['dropins'] ?? array() ) as $d ) {
			$pl[ 'Drop-in: ' . self::s( $d ) ] = '';
		}
		$out[] = self::section( 'plugins', __( 'Active plugins', 'flx-speed' ), $pl );
		return $out;
	}

	/* ------------------------------------------------------------ Database */

	/**
	 * @return array<string,mixed>
	 */
	public static function database(): array {
		/** @var wpdb $wpdb */
		global $wpdb;
		try {
			$db = self::a( flxp_database( true ) );
		} catch ( Throwable $e ) { // A database server that does not answer SHOW queries (SQLite): the boxes show dashes.
			$db = array();
		}
		$v  = self::a( $db['variables'] ?? array() );
		$s  = self::a( $db['status'] ?? array() );
		$t  = self::a( $db['totals'] ?? array() );
		$d  = self::a( $db['derived'] ?? array() );
		$mb = static fn( $bytes ): string => '' === self::s( $bytes ) ? '—' : (string) size_format( (int) self::f( $bytes ), 1 );

		$cards = array(
			array( 'label' => __( 'Data', 'flx-speed' ), 'value' => number_format_i18n( self::f( $t['data_mb'] ?? 0 ), 1 ) . ' MB', 'tone' => 'NONE' ),
			array( 'label' => __( 'Index', 'flx-speed' ), 'value' => number_format_i18n( self::f( $t['index_mb'] ?? 0 ), 1 ) . ' MB', 'tone' => 'NONE' ),
			array( 'label' => __( 'Free space', 'flx-speed' ), 'value' => number_format_i18n( self::f( $t['free_mb'] ?? 0 ), 1 ) . ' MB', 'tone' => self::f( $t['free_mb'] ?? 0 ) > 50 ? 'WARNING' : 'NONE' ),
			array( 'label' => __( 'Table', 'flx-speed' ), 'value' => number_format_i18n( self::i( $t['tables'] ?? 0 ) ), 'tone' => 'NONE' ),
			array( 'label' => __( 'Rows (approx.)', 'flx-speed' ), 'value' => number_format_i18n( self::i( $t['rows'] ?? 0 ) ), 'tone' => 'NONE' ),
		);
		if ( isset( $s['Threads_connected'] ) ) {
			$cards[] = array(
				'label' => __( 'Open connections', 'flx-speed' ),
				'value' => number_format_i18n( self::i( $s['Threads_connected'] ) ) . ( isset( $v['max_connections'] ) ? ' / ' . number_format_i18n( self::i( $v['max_connections'] ) ) : '' ),
				'tone'  => 'NONE',
			);
		}

		$sections = array(
			self::section(
				'db-server',
				__( 'Server', 'flx-speed' ),
				array(
					__( 'Software', 'flx-speed' )  => trim( self::s( $v['version_comment'] ?? '' ) . ' ' . self::s( $v['version'] ?? $wpdb->db_server_info() ) ),
					'max_connections'              => $v['max_connections'] ?? '—',
					'max_user_connections'         => $v['max_user_connections'] ?? '—',
					'max_allowed_packet'           => isset( $v['max_allowed_packet'] ) ? $mb( $v['max_allowed_packet'] ) : '—',
					'innodb_buffer_pool_size'      => isset( $v['innodb_buffer_pool_size'] ) ? $mb( $v['innodb_buffer_pool_size'] ) : '—',
					'tmp_table_size'               => isset( $v['tmp_table_size'] ) ? $mb( $v['tmp_table_size'] ) : '—',
					'wait_timeout'                 => $v['wait_timeout'] ?? '—',
					'slow_query_log'               => self::s( $v['slow_query_log'] ?? '—' ) . ( isset( $v['long_query_time'] ) ? ' (' . self::s( $v['long_query_time'] ) . ' s)' : '' ),
					__( 'Charset', 'flx-speed' )   => self::s( $v['character_set_server'] ?? '' ) . ' / ' . self::s( $v['collation_server'] ?? '' ),
					'sql_mode'                     => $v['sql_mode'] ?? '—',
				)
			),
			self::section(
				'db-status',
				__( 'Status', 'flx-speed' ),
				array(
					__( 'Uptime', 'flx-speed' )                    => isset( $s['Uptime'] ) ? flxp_fmt_uptime( self::i( $s['Uptime'] ) ) : '—',
					__( 'Queries per second', 'flx-speed' )        => isset( $d['queries_per_sec'] ) ? number_format_i18n( self::f( $d['queries_per_sec'] ), 2 ) : '—',
					__( 'Slow queries (server)', 'flx-speed' )     => isset( $s['Slow_queries'] ) ? number_format_i18n( self::i( $s['Slow_queries'] ) ) : '—',
					__( 'Max concurrent connections', 'flx-speed' ) => $s['Max_used_connections'] ?? '—',
					__( 'Running threads', 'flx-speed' )           => $s['Threads_running'] ?? '—',
					__( 'Buffer pool hit rate', 'flx-speed' )      => isset( $d['buffer_pool_hit_pct'] ) ? self::s( $d['buffer_pool_hit_pct'] ) . '%' : '—',
					__( 'Temp tables on disk', 'flx-speed' )       => isset( $d['tmp_disk_pct'] ) ? self::s( $d['tmp_disk_pct'] ) . '%' : '—',
					__( 'Aborted connects', 'flx-speed' )          => $s['Aborted_connects'] ?? '—',
				)
			),
		);

		$tables = array();
		$free   = 0;
		foreach ( self::rows( $db['tables'] ?? array() ) as $x ) {
			$free     += self::i( $x['free'] ?? 0 ) > 1048576 ? 1 : 0;
			$tables[] = array(
				'name'    => self::s( $x['name'] ?? '' ),
				'engine'  => self::s( $x['engine'] ?? '' ),
				'rows'    => self::i( $x['rows'] ?? 0 ),
				'data'    => self::f( $x['data'] ?? 0 ),
				'index'   => self::f( $x['index'] ?? 0 ),
				'free'    => self::f( $x['free'] ?? 0 ),
				'collate' => self::s( $x['collate'] ?? '' ),
			);
		}

		$cl    = self::a( $db['cleanup'] ?? array() );
		$count = array(
			'expired_transients' => self::i( $cl['expired_transients'] ?? 0 ),
			'auto_drafts'        => self::i( $cl['auto_drafts'] ?? 0 ),
			'spam_comments'      => self::i( $cl['spam_comments'] ?? 0 ),
			'trashed_comments'   => self::i( $cl['trashed_comments'] ?? 0 ),
			'optimize'           => $free,
		);
		$jobs  = array();
		foreach ( self::a( flxp_cleanup_jobs() ) as $k => $label ) {
			$jobs[] = array(
				'key'      => $k,
				'label'    => self::s( $label ),
				'count'    => $count[ $k ] ?? 0,
				'runnable' => self::can_manage() && ( $count[ $k ] ?? 0 ) > 0,
			);
		}
		$info = array(
			array( 'label' => __( 'Revisions', 'flx-speed' ), 'count' => self::i( $cl['revisions'] ?? 0 ) ),
			array( 'label' => __( 'Trashed posts', 'flx-speed' ), 'count' => self::i( $cl['trashed_posts'] ?? 0 ) ),
			array( 'label' => __( 'All transients', 'flx-speed' ), 'count' => self::i( $cl['transients'] ?? 0 ) ),
		);
		return array(
			'cards'    => $cards,
			'sections' => $sections,
			'jobs'     => $jobs,
			'info'     => $info,
			'tables'   => $tables,
		);
	}

	/**
	 * Runs one safe cleanup job.
	 *
	 * @return int|WP_Error Number of items cleaned.
	 */
	public static function cleanup( string $job ) {
		$n = flxp_cleanup( sanitize_key( $job ) );
		return $n instanceof WP_Error ? $n : self::i( $n );
	}

	/* ------------------------------------------------------------ Autoload and cron */

	/**
	 * @return array<string,mixed>
	 */
	public static function autoload(): array {
		$a       = self::a( flxp_autoload( 40 ) );
		$total   = self::f( $a['total_kb'] ?? 0 );
		$largest = array();
		foreach ( self::rows( $a['largest'] ?? array() ) as $o ) {
			$owner     = self::s( $o['owner'] ?? '' );
			$largest[] = array(
				'name'  => self::s( $o['name'] ?? '' ),
				'owner' => '' === $owner ? null : $owner,
				'kb'    => self::f( $o['kb'] ?? 0 ),
			);
		}
		return array(
			'count'   => self::i( $a['count'] ?? 0 ),
			'totalKb' => $total,
			'tone'    => $total > 1600 ? 'ERROR' : ( $total > 800 ? 'WARNING' : 'NONE' ),
			'largest' => $largest,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function cron(): array {
		$c    = self::a( flxp_cron() );
		$last = self::i( $c['last_seen'] ?? 0 );
		$now  = time();
		$tim  = array();
		foreach ( self::rows( FLXP_Cron_Timing::stats() ) as $t ) {
			$t     = self::a( $t );
			$tim[] = array(
				'hook'      => self::s( $t['hook'] ?? '' ),
				'component' => self::s( $t['component'] ?? '' ),
				'runs'      => self::i( $t['runs'] ?? 0 ),
				'avgMs'     => self::i( $t['avg_ms'] ?? 0 ),
				'maxMs'     => self::i( $t['max_ms'] ?? 0 ),
				'lastMs'    => self::i( $t['last_ms'] ?? 0 ),
				'lastAt'    => self::i( $t['last_at'] ?? 0 ) > 0 ? self::when( self::i( $t['last_at'] ) ) : null,
				'cut'       => ! empty( $t['cut'] ),
				'slow'      => self::i( $t['avg_ms'] ?? 0 ) >= 5000,
			);
		}
		$items = array();
		foreach ( self::rows( $c['items'] ?? array() ) as $e ) {
			$items[] = array(
				'hook'     => self::s( $e['hook'] ?? '' ),
				'next'     => self::when( self::i( $e['next'] ?? 0 ) ),
				'schedule' => self::s( $e['schedule'] ?? '' ),
				'args'     => self::i( $e['args'] ?? 0 ),
				'overdue'  => ! empty( $e['overdue'] ),
			);
		}
		return array(
			'disabled' => ! empty( $c['disabled'] ),
			'events'   => self::i( $c['events'] ?? 0 ),
			'overdue'  => self::i( $c['overdue'] ?? 0 ),
			'lastSeen' => $last > 0 ? self::when( $last ) : null,
			'lastWarn' => 0 === $last || $last < $now - HOUR_IN_SECONDS,
			'timings'  => $tim,
			'items'    => $items,
		);
	}

	/* ------------------------------------------------------------ System check */

	/**
	 * The shared system check list and the recent AI fixes.
	 *
	 * @return array<string,mixed>
	 */
	public static function system(): array {
		$list   = self::rows( flx_system_checks_sorted() );
		$labels = self::a( flx_system_checks_labels() );
		$groups = self::a( $labels['groups'] ?? array() );
		$checks = array();
		foreach ( $list as $c ) {
			$group     = self::s( $c['group'] ?? 'Other' );
			$url       = self::s( $c['action_url'] ?? '' );
			$checks[]  = array(
				'id'          => self::s( $c['id'] ?? '' ),
				'source'      => self::s( $c['source'] ?? '' ),
				'group'       => $group,
				'groupLabel'  => self::s( $groups[ $group ] ?? $group ),
				'label'       => self::s( $c['label'] ?? '' ),
				'status'      => self::s( $c['status'] ?? 'info' ),
				'message'     => self::s( $c['message'] ?? '' ),
				'actionUrl'   => '' === $url ? null : $url,
				'actionLabel' => '' === $url ? null : self::s( $c['action_label'] ?? '' ),
			);
		}
		$sum   = self::a( flx_system_checks_summary( $list ) );
		$fixes = array();
		if ( function_exists( 'flxseo_system_fix_log' ) ) {
			foreach ( self::rows( flxseo_system_fix_log( 20 ) ) as $r ) {
				$u       = get_userdata( self::i( $r['u'] ?? 0 ) );
				$fixes[] = array(
					'at'        => self::when( self::i( $r['t'] ?? 0 ) ),
					'user'      => $u ? $u->user_login : '?',
					'label'     => self::s( $r['label'] ?? '' ),
					'value'     => self::s( $r['value'] ?? '' ),
					'before'    => self::s( $r['before'] ?? '' ),
					'after'     => self::s( $r['after'] ?? '' ),
					'ok'        => ! empty( $r['ok'] ),
					'message'   => self::s( $r['msg'] ?? '' ),
				);
			}
		}
		return array(
			'checks'  => $checks,
			'summary' => array(
				'ok'      => self::i( $sum['ok'] ?? 0 ),
				'warning' => self::i( $sum['warning'] ?? 0 ),
				'error'   => self::i( $sum['error'] ?? 0 ),
				'info'    => self::i( $sum['info'] ?? 0 ),
			),
			'fixes'   => $fixes,
			'hasFixes' => function_exists( 'flxseo_system_fix_log' ),
		);
	}

	/* ------------------------------------------------------------ Settings */

	/**
	 * General settings and the custom login URL.
	 *
	 * @return array<string,mixed>
	 */
	public static function settings(): array {
		$s          = self::a( flxp_settings( null, true ) );
		$const_slug = defined( 'FLXP_LOGIN_SLUG' );
		$const_val  = $const_slug ? self::s( constant( 'FLXP_LOGIN_SLUG' ) ) : '';
		$active     = FLXP_Login_URL::slug();
		$value      = $const_slug ? FLXP_Login_URL::clean( $const_val ) : self::s( $s['login_slug'] ?? '' );
		$prefix     = get_option( 'permalink_structure' ) ? trailingslashit( home_url() ) : trailingslashit( home_url() ) . '?';
		return array(
			'panel'          => ! empty( $s['panel'] ),
			'panelFront'     => ! empty( $s['panel_front'] ),
			'caps'           => ! empty( $s['caps'] ),
			'record'         => ! empty( $s['record'] ),
			'recordAdmins'   => ! empty( $s['record_admins'] ),
			'sample'         => self::i( $s['sample'] ?? 2 ),
			'slowMs'         => self::i( $s['slow_ms'] ?? 1500 ),
			'slowQueryMs'    => self::i( $s['slow_query_ms'] ?? 100 ),
			'keepDays'       => self::i( $s['keep_days'] ?? 7 ),
			'maxRows'        => self::i( $s['max_rows'] ?? 5000 ),
			'diskQuotaGb'    => self::f( $s['disk_quota_gb'] ?? 0 ),
			'loginSlug'      => $value,
			'loginBlocked'   => 'home' === self::s( $s['login_blocked'] ?? '404' ) ? 'HOME' : 'NOT_FOUND',
			'loginSlugLocked' => $const_slug,
			'loginDisabled'  => FLXP_Login_URL::disabled_by_constant(),
			'loginActive'    => (bool) $active,
			'loginUrl'       => $active ? FLXP_Login_URL::url() : '',
			'loginPrefix'    => $prefix,
			'loginMovedUrl'  => $active ? wp_logout_url( add_query_arg( array( 'flxp_moved' => 1, 'redirect_to' => rawurlencode( admin_url( 'admin.php?page=flx-speed&tab=settings' ) ) ), FLXP_Login_URL::url() ) ) : '',
			'mcpActive'      => function_exists( 'flxseo_mcp_tools' ),
			'mcpTools'       => 'flxp_summary, flxp_requests, flxp_request, flxp_slow_queries, flxp_php_errors, flxp_http_calls, flxp_environment, flxp_server, flxp_database, flxp_autoload, flxp_cron, flxp_profile_url',
		);
	}

	/**
	 * Saves the general settings (one set of rules for every client).
	 *
	 * @param array<string,mixed> $in Raw values with the stored setting names.
	 * @return array{loginChanged:bool,loginRemoved:bool}|WP_Error
	 */
	public static function settings_save( array $in ) {
		$out  = self::a( flxp_sanitize_settings( $in ) );
		$prev = self::s( flxp_settings( 'login_slug' ) );
		if ( ! defined( 'FLXP_LOGIN_SLUG' ) && array_key_exists( 'login_slug', $in ) ) {
			$slug = FLXP_Login_URL::clean( self::s( $in['login_slug'] ) );
			$ok   = '' === $slug ? true : FLXP_Login_URL::validate( self::s( $slug ) );
			if ( $ok instanceof WP_Error ) {
				return new WP_Error(
					$ok->get_error_code(),
					$ok->get_error_message(),
					array(
						'field'        => 'loginSlug',
						'graphql_code' => 'INVALID_INPUT',
					)
				);
			}
			$out['login_slug'] = $slug;
		}
		update_option( 'flxp_settings', $out, false );
		flxp_settings( null, true );
		FLXP_Collector::sync_marker( ! empty( $out['panel'] ) );
		$changed = self::s( $out['login_slug'] ?? '' ) !== $prev;
		if ( $changed ) {
			flxp_login_slug_changed();
		}
		return array(
			'loginChanged' => $changed,
			'loginRemoved' => $changed && '' === self::s( $out['login_slug'] ?? '' ),
		);
	}

	/* ------------------------------------------------------------ PageSpeed */

	/**
	 * @return array<string,mixed>
	 */
	public static function pagespeed(): array {
		$s       = self::a( flxp_settings() );
		$toggles = array();
		foreach ( array_map( static fn( $x ): string => self::s( $x ), (array) FLXP_PageSpeed::toggles() ) as $k ) {
			$on        = ! empty( $s[ $k ] );
			$toggles[] = array(
				'key'    => $k,
				'on'     => $on,
				'forced' => ( (bool) apply_filters( 'flxp_pagespeed_enabled', $on, $k ) ) !== $on,
			);
		}
		$stats = self::a( FLXP_PageSpeed::cache_stats() );
		$anim  = array();
		foreach ( self::rows( FLXP_PageSpeed::animation_report() ) as $a ) {
			$anim[] = array(
				'name'    => self::s( $a['name'] ?? '' ),
				'props'   => array_values( array_map( static fn( $x ): string => self::s( $x ), (array) ( $a['props'] ?? array() ) ) ),
				'file'    => self::s( $a['file'] ?? '' ),
				'reduced' => ! empty( $a['reduced'] ),
			);
		}
		$seen  = get_option( 'flxp_ps_seen' );
		$seen  = is_array( $seen ) ? self::a( $seen ) : array();
		$home  = home_url( '/' );
		$audit = function_exists( 'flxseo_admin_url' ) ? self::s( flxseo_admin_url( 'denetim' ) ) : '';
		return array(
			'toggles'    => $toggles,
			'cssScope'   => 'local' === self::s( $s['ps_css_scope'] ?? 'theme' ) ? 'LOCAL' : 'THEME',
			'inlineKb'   => self::i( $s['ps_inline_kb'] ?? 60 ),
			'safelist'   => self::s( $s['ps_safelist'] ?? '' ),
			'exclude'    => self::s( $s['ps_exclude'] ?? '' ),
			'skipUrls'   => self::s( $s['ps_skip_urls'] ?? '' ),
			'cssFiles'   => self::i( $stats['css'] ?? 0 ),
			'jsFiles'    => self::i( $stats['js'] ?? 0 ),
			'cacheBytes' => self::f( $stats['bytes'] ?? 0 ),
			'homeUrl'    => $home,
			'offUrl'     => add_query_arg( 'flxp_off', '1', $home ),
			'auditUrl'   => '' === $audit ? null : $audit,
			'animations' => $anim,
			'seenFiles'  => isset( $seen['files'] ) && is_array( $seen['files'] ) ? count( $seen['files'] ) : null,
			'seenAt'     => self::i( $seen['t'] ?? 0 ) > 0 ? self::when( self::i( $seen['t'] ) ) : null,
		);
	}

	/**
	 * Saves the PageSpeed switches and clears the caches.
	 *
	 * @param array<string,mixed> $in Raw values with the stored setting names (ps_*).
	 */
	public static function pagespeed_save( array $in ): void {
		update_option( 'flxp_settings', flxp_sanitize_settings( $in, 'pagespeed' ), false );
		flxp_settings( null, true );
		self::pagespeed_clear();
	}

	/**
	 * Clears the PageSpeed cache and the LiteSpeed page cache.
	 */
	public static function pagespeed_clear(): void {
		FLXP_PageSpeed::clear_cache();
		delete_transient( 'flxp_ps_anim' );
		do_action( 'litespeed_purge_all' );
	}

	/* ------------------------------------------------------------ Cache tab */

	/**
	 * @return array<string,mixed>
	 */
	public static function cache(): array {
		$fin = FLXP_Cache::maybe_finish_pending();
		$st  = self::a( FLXP_Cache::status() );
		$ls  = self::a( $st['litespeed'] ?? array() );
		$sq  = self::a( $st['sqlite'] ?? array() );
		$dr  = self::a( $st['dropin'] ?? array() );
		$ext = self::a( self::a( $st['server'] ?? array() )['ext'] ?? array() );
		$db  = self::a( $sq['db'] ?? array() );
		$srv = self::a( $st['server'] ?? array() );

		$conflicts = array();
		foreach ( (array) ( $st['conflicts'] ?? array() ) as $code ) {
			$code        = self::s( $code );
			$conflicts[] = array(
				'code'       => $code,
				'text'       => self::s( FLXP_Cache::conflict_text( $code, $st ) ),
				'repairable' => in_array( $code, array( 'sqlite_dropin_missing', 'sqlite_orphan', 'ls_dropin_taken' ), true ),
			);
		}
		$plugin = static function ( array $p ): array {
			return array(
				'name'      => self::s( $p['name'] ?? '' ),
				'installed' => ! empty( $p['installed'] ),
				'active'    => ! empty( $p['active'] ),
				'version'   => self::s( $p['version'] ?? '' ),
			);
		};
		$test = $st['test'] ?? null;
		if ( is_array( $test ) ) {
			$test = self::a( $test );
			$test = array(
				'at'         => self::when( self::i( $test['t'] ?? 0 ) ),
				'provider'   => self::s( $test['provider'] ?? '' ),
				'persistent' => ! empty( $test['persistent'] ),
				'ms'         => self::f( $test['ms'] ?? 0 ),
				'error'      => '' === self::s( $test['error'] ?? '' ) ? null : self::s( $test['error'] ),
			);
		} else {
			$test = null;
		}
		return array(
			'pendingError' => $fin instanceof WP_Error ? $fin->get_error_message() : null,
			'litespeedServer' => ! empty( $srv['litespeed'] ),
			'extensions'   => array(
				'memcached' => ! empty( $ext['memcached'] ),
				'redis'     => ! empty( $ext['redis'] ),
				'sqlite3'   => ! empty( $ext['sqlite3'] ),
				'igbinary'  => ! empty( $ext['igbinary'] ),
			),
			'litespeed'    => $plugin( $ls ) + array(
				'pageCache'  => ! empty( $ls['page_cache'] ),
				'object'     => ! empty( $ls['object'] ),
				'objectKind' => 'redis' === self::s( $ls['object_kind'] ?? '' ) ? 'REDIS' : 'MEMCACHED',
				'objectHost' => self::s( $ls['object_host'] ?? '' ) . ( '' !== self::s( $ls['object_port'] ?? '' ) ? ':' . self::s( $ls['object_port'] ) : '' ),
			),
			'sqlite'       => $plugin( $sq ) + array(
				'fileName' => '' === self::s( $db['path'] ?? '' ) ? null : basename( self::s( $db['path'] ) ),
				'fileSize' => self::f( $db['size'] ?? 0 ),
			),
			'dropinExists' => ! empty( $dr['exists'] ),
			'dropinOwner'  => self::s( $dr['owner'] ?? 'none' ),
			'dropinName'   => self::s( $dr['name'] ?? '' ),
			'provider'     => self::s( $st['provider'] ?? 'none' ),
			'persistent'   => ! empty( $st['persistent'] ),
			'conflicts'    => $conflicts,
			'test'         => $test,
			'settingsUrls' => array(
				'litespeedPage'   => admin_url( 'admin.php?page=litespeed-cache' ),
				'litespeedObject' => admin_url( 'admin.php?page=litespeed-cache#object' ),
				'sqlitePlugin'    => admin_url( 'plugins.php?plugin_status=active&s=sqlite+object+cache' ),
			),
			'canInstall'   => current_user_can( 'install_plugins' ),
		);
	}

	/**
	 * Runs one operation of the Cache tab (the screen's buttons).
	 *
	 * @return array{code:string,message:string}|WP_Error
	 */
	public static function cache_run( string $op ) {
		$msg = '';
		$r   = true;
		switch ( $op ) {
			case 'install_litespeed':
			case 'install_sqlite':
				$key = substr( $op, 8 );
				$r   = FLXP_Cache::install( $key );
				if ( ! ( $r instanceof WP_Error ) ) {
					$r = 'sqlite' === $key ? FLXP_Cache::use_sqlite() : FLXP_Cache::activate( $key );
				}
				$msg = 'sqlite' === $key ? 'sqlite' : 'installed';
				break;
			case 'activate_litespeed':
			case 'activate_sqlite':
				$key = substr( $op, 9 );
				$r   = 'sqlite' === $key ? FLXP_Cache::use_sqlite() : FLXP_Cache::activate( $key );
				$msg = 'sqlite' === $key ? 'sqlite' : 'activated';
				break;
			case 'use_sqlite':
				$r   = FLXP_Cache::use_sqlite();
				$msg = 'sqlite';
				break;
			case 'use_litespeed':
				$r   = FLXP_Cache::use_litespeed();
				$msg = 'pending' === $r ? 'pending' : 'litespeed';
				break;
			case 'object_off':
				$r   = FLXP_Cache::object_off();
				$msg = 'object_off';
				break;
			case 'repair':
				$r   = FLXP_Cache::repair();
				$msg = 'repaired';
				break;
			case 'page_on':
			case 'page_off':
				$r   = FLXP_Cache::page_cache( 'page_on' === $op );
				$msg = $op;
				break;
			case 'purge':
				$r   = FLXP_Cache::purge_all();
				$msg = 'purged';
				break;
			case 'flush':
				wp_cache_flush();
				$msg = 'flushed';
				break;
			case 'test':
				FLXP_Cache::run_test();
				$msg = 'tested';
				break;
			default:
				return new WP_Error( 'flxp_op', __( 'Unknown operation.', 'flx-speed' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'op' ) );
		}
		if ( $r instanceof WP_Error ) {
			return $r;
		}
		return array(
			'code'    => $msg,
			'message' => self::cache_message( $msg ),
		);
	}

	/**
	 * Success text of a Cache operation.
	 */
	public static function cache_message( string $code ): string {
		$all = array(
			'installed'  => __( 'Plugin installed and activated.', 'flx-speed' ),
			'activated'  => __( 'Plugin activated.', 'flx-speed' ),
			'sqlite'     => __( 'Object cache is now SQLite Object Cache. Run the test below to confirm.', 'flx-speed' ),
			'litespeed'  => __( 'Object cache is now LiteSpeed Cache. Run the test below to confirm.', 'flx-speed' ),
			'pending'    => __( 'LiteSpeed Cache was activated. Its object cache setting is finished when this page loads; run the test below.', 'flx-speed' ),
			'object_off' => __( 'Object cache turned off.', 'flx-speed' ),
			'repaired'   => __( 'Object cache repaired.', 'flx-speed' ),
			'page_on'    => __( 'Page cache turned on and purged.', 'flx-speed' ),
			'page_off'   => __( 'Page cache turned off and purged.', 'flx-speed' ),
			'purged'     => __( 'All caches cleared (LiteSpeed page, CSS/JS and object cache).', 'flx-speed' ),
			'flushed'    => __( 'Object cache emptied.', 'flx-speed' ),
			'tested'     => '',
		);
		return $all[ $code ] ?? '';
	}
}
