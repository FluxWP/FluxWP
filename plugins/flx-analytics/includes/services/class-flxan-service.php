<?php
/**
 * Flux Analytics reports and settings: the one place the Statistics, Journeys and Settings screens
 * (GraphQL), the classic pages (admin-post handlers) and the REST/MCP write endpoints read and change
 * data. The read layer itself stays FLXAN_Stats / FLXAN_Journeys (REST and Flux AI tools use it as before);
 * this class puts a typed face on it: camelCase array shapes the GraphQL schema reads as they are.
 *
 * Permission checks stay with the caller (flxan_can_view for reports, manage_options for settings and
 * data, flxan_can_manage_refs for referral names).
 *
 * Visit collection (endpoint.php, /flxan/v1/c) is not here: it is the public, high-volume write path.
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type Option array{value: string, label: string}
 * @phpstan-type Range array{preset: string, start: string, end: string, label: string, days: int, prevStart: string, prevEnd: string}
 * @phpstan-type Filter array{range?: string|null, start?: string|null, end?: string|null, seg?: string|null, rc?: string|null}
 * @phpstan-type Point array{date: string, visitors: int, pageviews: int}
 * @phpstan-type Series array{group: string, items: list<Point>}
 * @phpstan-type Totals array{visitors: int, pageviews: int}
 * @phpstan-type Visits array{sessions: int, avgDuration: int, bounceRate: float, pagesPer: float}
 * @phpstan-type Change array{visitors: float|null, pageviews: float|null, sessions: float|null, avgDuration: float|null, bounceRate: float|null, pagesPer: float|null}
 * @phpstan-type Kpi array{totals: Totals, prev: Totals, visits: Visits, visitsPrev: Visits, change: Change, series: Series}
 * @phpstan-type PageRow array{path: string, url: string, postId: int, title: string, is404: bool, visitors: int, pageviews: int}
 * @phpstan-type PageList array{total: int, items: list<PageRow>}
 * @phpstan-type ReferrerRow array{host: string, channel: string, channelLabel: string, visitors: int, pageviews: int}
 * @phpstan-type ReferrerList array{total: int, items: list<ReferrerRow>}
 * @phpstan-type DimRow array{value: string, label: string, visitors: int, pageviews: int}
 * @phpstan-type DimList array{kind: string, total: int, items: list<DimRow>}
 * @phpstan-type EventSum array{name: string, label: string, hits: int, uniques: int, amount: float}
 * @phpstan-type EventRow array{name: string, label: string, param: string, hits: int, uniques: int, amount: float}
 * @phpstan-type EventList array{total: int, items: list<EventRow>}
 * @phpstan-type Segment array{value: string, label: string, visitors: int, pageviews: int}
 * @phpstan-type Ref array{code: string, name: string, label: string, url: string, visitors: int, pageviews: int, sessions: int, pagesPer: float, avgDuration: int, donations: int, amount: float, deepest: int, deepestLabel: string, completed: int}
 * @phpstan-type FunnelStep array{label: string, type: string, value: string, count: int, rate: float, drop: float}
 * @phpstan-type FunnelGroup array{value: string, label: string, counts: list<int>, rate: float}
 * @phpstan-type FunnelName array{id: int, name: string}
 * @phpstan-type Funnel array{found: bool, id: int|null, name: string|null, by: string, steps: list<FunnelStep>, groups: list<FunnelGroup>, funnels: list<FunnelName>}
 * @phpstan-type Live array{value: string, label: string, visitors: int}
 * @phpstan-type Realtime array{minutes: int, active: int, visitors: int, pageviews: int, perMinute: list<int>, pages: list<array{path: string, title: string, pageviews: int}>, referrers: list<array{host: string, pageviews: int}>, countries: list<Live>, codes: list<array{code: string, label: string, visitors: int}>, updated: int, kpi: Kpi|null}
 * @phpstan-type Report array{range: Range, filter: array{seg: string, rc: string}, kpi: Kpi, pages: PageList, notFound: PageList, referrers: ReferrerList, dims: list<DimList>, segments: list<Segment>, refs: list<Ref>, funnel: Funnel, eventSummary: list<EventSum>, events: EventList, realtime: Realtime}
 * @phpstan-type PageStats array{found: bool, paths: list<string>, visitors: int, pageviews: int, series: list<Point>}
 * @phpstan-type JNode array{key: string, label: string, count: int, pct: float}
 * @phpstan-type JStep array{key: string, label: string, path: string, ev: bool}
 * @phpstan-type JFlowNode array{key: string, label: string, path: string, ev: bool, count: int, ends: int}
 * @phpstan-type JPath array{steps: list<JStep>, sessions: int, reached: int, share: float, prev: int, change: float|null, rate: float}
 * @phpstan-type Journeys array{range: Range, options: array{steps: int, from: string, to: string, goal: string, group: bool, top: int}, goals: list<Option>, summary: array{sessions: int, multi: int, multiPct: float, multiChange: float|null, avgLen: float, avgLenChange: float|null, topEntry: JNode|null, topExit: JNode|null, goalRate: float|null, goalReached: int, goalChange: float|null}, flow: array{columns: list<array{nodes: list<JFlowNode>}>, links: list<array{step: int, from: string, to: string, count: int}>}, paths: array{top: int, total: int, items: list<JPath>}}
 * @phpstan-type StepDef array{label: string, type: string, value: string}
 * @phpstan-type FunnelDef array{id: int, name: string, steps: list<StepDef>}
 * @phpstan-type Koko array{days: int, first: string, last: string}
 * @phpstan-type SettingsOut array{on: bool, method: string, methodNow: string, dnt: bool, excludeRoles: list<string>, excludeIps: string, evOutbound: bool, evDownload: bool, evContact: bool, evSearch: bool, evDonation: bool, postsColumn: bool, adminbar: bool, keepMonths: int, tmHub: bool, hubEndpoint: string|null, lastAggregated: int, pendingBytes: int, pendingLabel: string, fastPossible: bool, fastTest: string, roles: list<Option>, funnels: list<FunnelDef>, funnelDefaults: list<FunnelDef>, funnelEvents: list<Option>, funnelPages: list<array{path: string, title: string}>, koko: Koko|null, exportUrl: string}
 * @phpstan-type Config array{presets: list<Option>, kinds: list<Option>, events: list<Option>, channels: list<Option>, segments: list<Option>, funnelBy: list<Option>, goals: list<Option>, defaultGoal: string, canManageRefs: bool, canManage: bool, ignoreBrowser: bool, fastEndpoint: string, exportUrl: string, homeUrl: string, settingsUrl: string, funnelSettingsUrl: string, hasWorldMap: bool}
 * @phpstan-type RefOut array{code: string, name: string, url: string}
 */
final class FLXAN_Service {

	/** Admin menu slug of the classic pages (the settings link is built from it). */
	public const PAGE = 'flx-analytics';

	public const CACHE_TAG = 'flx-analytics:stats';

	/* ------------------------------------------------------------------ */
	/* Value helpers                                                       */
	/* ------------------------------------------------------------------ */

	/** @param mixed $v Value. */
	public static function str( $v, string $default = '' ): string {
		return is_scalar( $v ) ? (string) $v : $default;
	}

	/** @param mixed $v Value. */
	public static function int( $v ): int {
		return is_numeric( $v ) ? (int) $v : 0;
	}

	/** @param mixed $v Value. */
	public static function num( $v ): float {
		return is_numeric( $v ) ? (float) $v : 0.0;
	}

	/** @param mixed $v Value. */
	public static function opt_num( $v ): ?float {
		return is_numeric( $v ) ? (float) $v : null;
	}

	/**
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	public static function arr( $v ): array {
		return is_array( $v ) ? $v : array();
	}

	/**
	 * Rows of a legacy list.
	 *
	 * @param mixed $v Value.
	 * @return list<array<mixed>>
	 */
	public static function rows( $v ): array {
		$out = array();
		foreach ( self::arr( $v ) as $row ) {
			if ( is_array( $row ) ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * @param array<string,string> $map Value => label.
	 * @return list<Option>
	 */
	public static function options( array $map ): array {
		$out = array();
		foreach ( $map as $value => $label ) {
			$out[] = array( 'value' => (string) $value, 'label' => $label );
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Range and filter                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * Applies the segment / referral-code filter and resolves the range.
	 *
	 * @param Filter $f Filter input (range preset key, custom dates, seg, rc).
	 * @return Range
	 */
	public static function range( array $f = array() ): array {
		FLXAN_Stats::set_filter( self::str( $f['seg'] ?? 'all', 'all' ), self::str( $f['rc'] ?? '' ) );
		return self::map_range( self::legacy_range( $f ) );
	}

	/**
	 * Legacy range array (start, end, prev_start …) the read layer takes.
	 *
	 * @param Filter $f Filter input.
	 * @return array<mixed>
	 */
	private static function legacy_range( array $f ): array {
		$preset = self::str( $f['range'] ?? '', '30d' );
		return self::arr( FLXAN_Stats::range( '' !== $preset ? $preset : '30d', self::str( $f['start'] ?? '' ), self::str( $f['end'] ?? '' ) ) );
	}

	/**
	 * @param array<mixed> $r Legacy range.
	 * @return Range
	 */
	private static function map_range( array $r ): array {
		return array(
			'preset'    => self::str( $r['preset'] ?? '30d', '30d' ),
			'start'     => self::str( $r['start'] ?? '' ),
			'end'       => self::str( $r['end'] ?? '' ),
			'label'     => self::str( $r['label'] ?? '' ),
			'days'      => self::int( $r['days'] ?? 0 ),
			'prevStart' => self::str( $r['prev_start'] ?? '' ),
			'prevEnd'   => self::str( $r['prev_end'] ?? '' ),
		);
	}

	/**
	 * The filter the legacy layer holds now.
	 *
	 * @return array{seg: string, rc: string}
	 */
	private static function current_filter(): array {
		$f = FLXAN_Stats::$filter;
		return array( 'seg' => self::str( $f['seg'] ?? 'all', 'all' ), 'rc' => self::str( $f['rc'] ?? '' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Mappers (legacy arrays → typed shapes)                              */
	/* ------------------------------------------------------------------ */

	/**
	 * @param mixed $v Legacy series.
	 * @return Series
	 */
	private static function map_series( $v ): array {
		$s     = self::arr( $v );
		$items = array();
		foreach ( self::rows( $s['items'] ?? array() ) as $r ) {
			$items[] = self::map_point( $r );
		}
		return array( 'group' => self::str( $s['group'] ?? 'day', 'day' ), 'items' => $items );
	}

	/**
	 * @param array<mixed> $r Legacy point (d, visitors, pageviews).
	 * @return Point
	 */
	private static function map_point( array $r ): array {
		return array( 'date' => self::str( $r['d'] ?? '' ), 'visitors' => self::int( $r['visitors'] ?? 0 ), 'pageviews' => self::int( $r['pageviews'] ?? 0 ) );
	}

	/**
	 * @param mixed $v Legacy totals.
	 * @return Totals
	 */
	private static function map_totals( $v ): array {
		$t = self::arr( $v );
		return array( 'visitors' => self::int( $t['visitors'] ?? 0 ), 'pageviews' => self::int( $t['pageviews'] ?? 0 ) );
	}

	/**
	 * @param mixed $v Legacy visit metrics.
	 * @return Visits
	 */
	private static function map_visits( $v ): array {
		$t = self::arr( $v );
		return array(
			'sessions'    => self::int( $t['sessions'] ?? 0 ),
			'avgDuration' => self::int( $t['avg_duration'] ?? 0 ),
			'bounceRate'  => self::num( $t['bounce_rate'] ?? 0 ),
			'pagesPer'    => self::num( $t['pages_per'] ?? 0 ),
		);
	}

	/**
	 * @param array<mixed> $k Legacy FLXAN_Stats::kpi() result.
	 * @return Kpi
	 */
	private static function map_kpi( array $k ): array {
		$c = self::arr( $k['change'] ?? array() );
		return array(
			'totals'     => self::map_totals( $k['totals'] ?? array() ),
			'prev'       => self::map_totals( $k['prev'] ?? array() ),
			'visits'     => self::map_visits( $k['visits'] ?? array() ),
			'visitsPrev' => self::map_visits( $k['visits_prev'] ?? array() ),
			'change'     => array(
				'visitors'    => self::opt_num( $c['visitors'] ?? null ),
				'pageviews'   => self::opt_num( $c['pageviews'] ?? null ),
				'sessions'    => self::opt_num( $c['sessions'] ?? null ),
				'avgDuration' => self::opt_num( $c['avg_duration'] ?? null ),
				'bounceRate'  => self::opt_num( $c['bounce_rate'] ?? null ),
				'pagesPer'    => self::opt_num( $c['pages_per'] ?? null ),
			),
			'series'     => self::map_series( $k['series'] ?? array() ),
		);
	}

	/**
	 * @param mixed $v Legacy page list.
	 * @return PageList
	 */
	private static function map_pages( $v ): array {
		$l     = self::arr( $v );
		$items = array();
		foreach ( self::rows( $l['items'] ?? array() ) as $r ) {
			$items[] = array(
				'path'      => self::str( $r['path'] ?? '' ),
				'url'       => self::str( $r['url'] ?? '' ),
				'postId'    => self::int( $r['post_id'] ?? 0 ),
				'title'     => self::str( $r['title'] ?? '' ),
				'is404'     => ! empty( $r['is404'] ),
				'visitors'  => self::int( $r['visitors'] ?? 0 ),
				'pageviews' => self::int( $r['pageviews'] ?? 0 ),
			);
		}
		return array( 'total' => self::int( $l['total'] ?? 0 ), 'items' => $items );
	}

	/**
	 * @param mixed $v Legacy referrer list.
	 * @return ReferrerList
	 */
	private static function map_referrers( $v ): array {
		$l     = self::arr( $v );
		$items = array();
		foreach ( self::rows( $l['items'] ?? array() ) as $r ) {
			$ch      = self::str( $r['channel'] ?? 'referral', 'referral' );
			$items[] = array(
				'host'         => self::str( $r['host'] ?? '' ),
				'channel'      => $ch,
				'channelLabel' => self::str( flxan_dim_label( 'channel', $ch ) ),
				'visitors'     => self::int( $r['visitors'] ?? 0 ),
				'pageviews'    => self::int( $r['pageviews'] ?? 0 ),
			);
		}
		return array( 'total' => self::int( $l['total'] ?? 0 ), 'items' => $items );
	}

	/**
	 * @param string $kind Dimension kind.
	 * @param mixed  $v    Legacy dimension list.
	 * @return DimList
	 */
	private static function map_dim( string $kind, $v ): array {
		$l     = self::arr( $v );
		$items = array();
		foreach ( self::rows( $l['items'] ?? array() ) as $r ) {
			$items[] = array(
				'value'     => self::str( $r['value'] ?? '' ),
				'label'     => self::str( $r['label'] ?? '' ),
				'visitors'  => self::int( $r['visitors'] ?? 0 ),
				'pageviews' => self::int( $r['pageviews'] ?? 0 ),
			);
		}
		return array( 'kind' => $kind, 'total' => self::int( $l['total'] ?? 0 ), 'items' => $items );
	}

	/**
	 * @param mixed $v Legacy event list.
	 * @return EventList
	 */
	private static function map_events( $v ): array {
		$l     = self::arr( $v );
		$items = array();
		foreach ( self::rows( $l['items'] ?? array() ) as $r ) {
			$items[] = array(
				'name'    => self::str( $r['name'] ?? '' ),
				'label'   => self::str( $r['label'] ?? '' ),
				'param'   => self::str( $r['param'] ?? '' ),
				'hits'    => self::int( $r['hits'] ?? 0 ),
				'uniques' => self::int( $r['uniques'] ?? 0 ),
				'amount'  => self::num( $r['amount'] ?? 0 ),
			);
		}
		return array( 'total' => self::int( $l['total'] ?? 0 ), 'items' => $items );
	}

	/**
	 * @param mixed $v Legacy event totals.
	 * @return list<EventSum>
	 */
	private static function map_event_sum( $v ): array {
		$out = array();
		foreach ( self::rows( $v ) as $r ) {
			$out[] = array(
				'name'    => self::str( $r['name'] ?? '' ),
				'label'   => self::str( $r['label'] ?? '' ),
				'hits'    => self::int( $r['hits'] ?? 0 ),
				'uniques' => self::int( $r['uniques'] ?? 0 ),
				'amount'  => self::num( $r['amount'] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * @param mixed $v Legacy segments.
	 * @return list<Segment>
	 */
	private static function map_segments( $v ): array {
		$out = array();
		foreach ( self::rows( $v ) as $r ) {
			$out[] = array(
				'value'     => self::str( $r['value'] ?? '' ),
				'label'     => self::str( $r['label'] ?? '' ),
				'visitors'  => self::int( $r['visitors'] ?? 0 ),
				'pageviews' => self::int( $r['pageviews'] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * @param mixed $v Legacy referral codes.
	 * @return list<Ref>
	 */
	private static function map_refs( $v ): array {
		$out = array();
		foreach ( self::rows( $v ) as $r ) {
			$out[] = array(
				'code'         => self::str( $r['code'] ?? '' ),
				'name'         => self::str( $r['name'] ?? '' ),
				'label'        => self::str( $r['label'] ?? '' ),
				'url'          => self::str( $r['url'] ?? '' ),
				'visitors'     => self::int( $r['visitors'] ?? 0 ),
				'pageviews'    => self::int( $r['pageviews'] ?? 0 ),
				'sessions'     => self::int( $r['sessions'] ?? 0 ),
				'pagesPer'     => self::num( $r['pages_per'] ?? 0 ),
				'avgDuration'  => self::int( $r['avg_duration'] ?? 0 ),
				'donations'    => self::int( $r['donations'] ?? 0 ),
				'amount'       => self::num( $r['amount'] ?? 0 ),
				'deepest'      => self::int( $r['deepest'] ?? 0 ),
				'deepestLabel' => self::str( $r['deepest_label'] ?? '' ),
				'completed'    => self::int( $r['completed'] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * @param mixed $v Legacy funnel.
	 * @return Funnel
	 */
	private static function map_funnel( $v ): array {
		$f = self::arr( $v );
		if ( empty( $f['found'] ) ) {
			return array( 'found' => false, 'id' => null, 'name' => null, 'by' => '', 'steps' => array(), 'groups' => array(), 'funnels' => array() );
		}
		$steps = array();
		foreach ( self::rows( $f['steps'] ?? array() ) as $s ) {
			$steps[] = array(
				'label' => self::str( $s['label'] ?? '' ),
				'type'  => self::str( $s['type'] ?? 'path', 'path' ),
				'value' => self::str( $s['value'] ?? '' ),
				'count' => self::int( $s['count'] ?? 0 ),
				'rate'  => self::num( $s['rate'] ?? 0 ),
				'drop'  => self::num( $s['drop'] ?? 0 ),
			);
		}
		$groups = array();
		foreach ( self::rows( $f['groups'] ?? array() ) as $g ) {
			$groups[] = array(
				'value'  => self::str( $g['value'] ?? '' ),
				'label'  => self::str( $g['label'] ?? '' ),
				'counts' => array_values( array_map( array( __CLASS__, 'int' ), self::arr( $g['counts'] ?? array() ) ) ),
				'rate'   => self::num( $g['rate'] ?? 0 ),
			);
		}
		$names = array();
		foreach ( self::rows( $f['funnels'] ?? array() ) as $n ) {
			$names[] = array( 'id' => self::int( $n['id'] ?? 0 ), 'name' => self::str( $n['name'] ?? '' ) );
		}
		return array(
			'found'   => true,
			'id'      => self::int( $f['id'] ?? 0 ),
			'name'    => self::str( $f['name'] ?? '' ),
			'by'      => self::str( $f['by'] ?? '' ),
			'steps'   => $steps,
			'groups'  => $groups,
			'funnels' => $names,
		);
	}

	/**
	 * @param array<mixed> $r Legacy FLXAN_Stats::realtime() result.
	 * @return Realtime
	 */
	private static function map_realtime( array $r ): array {
		$pages = array();
		foreach ( self::rows( $r['pages'] ?? array() ) as $p ) {
			$pages[] = array( 'path' => self::str( $p['path'] ?? '' ), 'title' => self::str( $p['title'] ?? '' ), 'pageviews' => self::int( $p['pageviews'] ?? 0 ) );
		}
		$refs = array();
		foreach ( self::rows( $r['referrers'] ?? array() ) as $p ) {
			$refs[] = array( 'host' => self::str( $p['host'] ?? '' ), 'pageviews' => self::int( $p['pageviews'] ?? 0 ) );
		}
		$ctry = array();
		foreach ( self::rows( $r['countries'] ?? array() ) as $p ) {
			$ctry[] = array( 'value' => self::str( $p['value'] ?? '' ), 'label' => self::str( $p['label'] ?? '' ), 'visitors' => self::int( $p['visitors'] ?? 0 ) );
		}
		$codes = array();
		foreach ( self::rows( $r['codes'] ?? array() ) as $p ) {
			$codes[] = array( 'code' => self::str( $p['code'] ?? '' ), 'label' => self::str( $p['label'] ?? '' ), 'visitors' => self::int( $p['visitors'] ?? 0 ) );
		}
		return array(
			'minutes'   => self::int( $r['minutes'] ?? 30 ),
			'active'    => self::int( $r['active'] ?? 0 ),
			'visitors'  => self::int( $r['visitors'] ?? 0 ),
			'pageviews' => self::int( $r['pageviews'] ?? 0 ),
			'perMinute' => array_values( array_map( array( __CLASS__, 'int' ), self::arr( $r['per_minute'] ?? array() ) ) ),
			'pages'     => $pages,
			'referrers' => $refs,
			'countries' => $ctry,
			'codes'     => $codes,
			'updated'   => self::int( $r['updated'] ?? 0 ),
			'kpi'       => null,
		);
	}

	/* ------------------------------------------------------------------ */
	/* Reports                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Everything the Statistics screen shows in one request.
	 *
	 * @param Filter $f Filter.
	 * @return Report
	 */
	public static function report( array $f = array() ): array {
		FLXAN_Aggregator::nudge();
		$range = self::range( $f );
		$g     = self::legacy_range( $f );
		$start = self::str( $g['start'] ?? '' );
		$end   = self::str( $g['end'] ?? '' );
		$dims  = array();
		foreach ( array_keys( FLXAN_Stats::kinds() ) as $kind ) {
			$kind   = (string) $kind;
			$dims[] = self::map_dim( $kind, FLXAN_Stats::dim( $kind, $start, $end, 'country' === $kind ? 250 : 8 ) );
		}
		return array(
			'range'        => $range,
			'filter'       => self::current_filter(),
			'kpi'          => self::map_kpi( self::arr( FLXAN_Stats::kpi( $g ) ) ),
			'pages'        => self::map_pages( FLXAN_Stats::pages( $start, $end, 10 ) ),
			'notFound'     => self::map_pages( FLXAN_Stats::pages( $start, $end, 5, 0, '', true ) ),
			'referrers'    => self::map_referrers( FLXAN_Stats::referrers( $start, $end, 10 ) ),
			'dims'         => $dims,
			'segments'     => self::map_segments( FLXAN_Stats::segments( $start, $end ) ),
			'refs'         => self::map_refs( FLXAN_Stats::refs( $start, $end, 20 ) ),
			'funnel'       => self::map_funnel( FLXAN_Stats::funnel( 0, $start, $end, 'channel' ) ),
			'eventSummary' => self::map_event_sum( FLXAN_Stats::event_names( $start, $end ) ),
			'events'       => self::map_events( FLXAN_Stats::events( $start, $end, '', 10 ) ),
			'realtime'     => self::map_realtime( self::arr( FLXAN_Stats::realtime( 30 ) ) ),
		);
	}

	/**
	 * Live box (and, for an open screen, the headline numbers and chart).
	 *
	 * @param Filter $f       Filter.
	 * @param int    $minutes Window, 1–60.
	 * @param bool   $kpi     Include the headline numbers.
	 * @return Realtime
	 */
	public static function realtime( array $f = array(), int $minutes = 30, bool $kpi = false ): array {
		FLXAN_Aggregator::maybe_run( 5 );
		self::range( $f );
		$g   = self::legacy_range( $f );
		$out = self::map_realtime( self::arr( FLXAN_Stats::realtime( $minutes ) ) );
		if ( $kpi ) {
			$out['kpi'] = self::map_kpi( self::arr( FLXAN_Stats::kpi( $g ) ) );
		}
		return $out;
	}

	/**
	 * Most viewed pages (also the 404 list).
	 *
	 * @param Filter $f      Filter.
	 * @param string $q      Address text.
	 * @param bool   $only404 Only addresses that were not found.
	 * @return PageList
	 */
	public static function pages( array $f, string $q, bool $only404, int $offset, int $limit ): array {
		$g = self::legacy_range( $f );
		FLXAN_Stats::set_filter( self::str( $f['seg'] ?? 'all', 'all' ), self::str( $f['rc'] ?? '' ) );
		return self::map_pages( FLXAN_Stats::pages( self::str( $g['start'] ?? '' ), self::str( $g['end'] ?? '' ), $limit, $offset, $q, $only404 ) );
	}

	/**
	 * @param Filter $f Filter.
	 * @return ReferrerList
	 */
	public static function referrers( array $f, string $q, int $offset, int $limit ): array {
		$g = self::legacy_range( $f );
		FLXAN_Stats::set_filter( self::str( $f['seg'] ?? 'all', 'all' ), self::str( $f['rc'] ?? '' ) );
		return self::map_referrers( FLXAN_Stats::referrers( self::str( $g['start'] ?? '' ), self::str( $g['end'] ?? '' ), $limit, $offset, $q ) );
	}

	/**
	 * @param Filter $f Filter.
	 * @return DimList
	 */
	public static function breakdown( array $f, string $kind, int $offset, int $limit ): array {
		$g = self::legacy_range( $f );
		FLXAN_Stats::set_filter( self::str( $f['seg'] ?? 'all', 'all' ), self::str( $f['rc'] ?? '' ) );
		return self::map_dim( $kind, FLXAN_Stats::dim( $kind, self::str( $g['start'] ?? '' ), self::str( $g['end'] ?? '' ), $limit, $offset ) );
	}

	/**
	 * @param Filter $f Filter.
	 * @return EventList
	 */
	public static function events( array $f, string $name, int $offset, int $limit ): array {
		$g = self::legacy_range( $f );
		FLXAN_Stats::set_filter( self::str( $f['seg'] ?? 'all', 'all' ), self::str( $f['rc'] ?? '' ) );
		return self::map_events( FLXAN_Stats::events( self::str( $g['start'] ?? '' ), self::str( $g['end'] ?? '' ), $name, $limit, $offset ) );
	}

	/**
	 * One page or post: totals and the daily series.
	 *
	 * @param Filter $f      Filter.
	 * @param string $target Post ID, path or address.
	 * @return PageStats
	 */
	public static function page_stats( array $f, string $target ): array {
		$g = self::legacy_range( $f );
		FLXAN_Stats::set_filter( self::str( $f['seg'] ?? 'all', 'all' ), self::str( $f['rc'] ?? '' ) );
		$r      = self::arr( FLXAN_Stats::page_stats( $target, self::str( $g['start'] ?? '' ), self::str( $g['end'] ?? '' ) ) );
		$series = array();
		foreach ( self::rows( $r['series'] ?? array() ) as $p ) {
			$series[] = self::map_point( $p );
		}
		return array(
			'found'     => ! empty( $r['found'] ),
			'paths'     => array_values( array_map( array( __CLASS__, 'str' ), self::arr( $r['paths'] ?? array() ) ) ),
			'visitors'  => self::int( $r['visitors'] ?? 0 ),
			'pageviews' => self::int( $r['pageviews'] ?? 0 ),
			'series'    => $series,
		);
	}

	/**
	 * Funnel steps (and the per-group table when $by is set).
	 *
	 * @param Filter $f  Filter.
	 * @param int    $id Funnel id (0 = the first).
	 * @param string $by channel | ref | rc | seg | country | ''.
	 * @return Funnel
	 */
	public static function funnel( array $f, int $id, string $by ): array {
		$g = self::legacy_range( $f );
		FLXAN_Stats::set_filter( self::str( $f['seg'] ?? 'all', 'all' ), self::str( $f['rc'] ?? '' ) );
		return self::map_funnel( FLXAN_Stats::funnel( $id, self::str( $g['start'] ?? '' ), self::str( $g['end'] ?? '' ), $by ) );
	}

	/**
	 * Referral codes with their results.
	 *
	 * @param Filter $f     Filter.
	 * @param int    $limit Rows.
	 * @return list<Ref>
	 */
	public static function refs( array $f, int $limit = 200 ): array {
		$g = self::legacy_range( $f );
		FLXAN_Stats::set_filter( self::str( $f['seg'] ?? 'all', 'all' ), self::str( $f['rc'] ?? '' ) );
		return self::map_refs( FLXAN_Stats::refs( self::str( $g['start'] ?? '' ), self::str( $g['end'] ?? '' ), $limit ) );
	}

	/**
	 * Adds, renames or removes a referral code name. Null when the code is not valid or there are too many.
	 *
	 * @return RefOut|null
	 */
	public static function ref_save( string $code, string $name, bool $delete ): ?array {
		$saved = self::str( flxan_ref_save( $code, $delete ? null : $name ) );
		if ( '' === $saved ) {
			return null;
		}
		flx_gql_purge_safe( self::CACHE_TAG );
		return array(
			'code' => $saved,
			'name' => self::str( flxan_ref_names()[ $saved ] ?? '' ),
			'url'  => add_query_arg( 'r', $saved, home_url( '/' ) ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Journeys                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * @param array<mixed>|null $n Legacy node (key, label, count, pct).
	 * @return JNode|null
	 */
	private static function map_jnode( ?array $n ): ?array {
		if ( null === $n ) {
			return null;
		}
		return array( 'key' => self::str( $n['key'] ?? '' ), 'label' => self::str( $n['label'] ?? '' ), 'count' => self::int( $n['count'] ?? 0 ), 'pct' => self::num( $n['pct'] ?? 0 ) );
	}

	/**
	 * Journeys report.
	 *
	 * @param Filter                                                                           $f Filter.
	 * @param array{steps?: int, from?: string, to?: string, goal?: string|null, group?: bool, top?: int} $o Options (goal null = automatic).
	 * @return Journeys
	 */
	public static function journeys( array $f, array $o ): array {
		FLXAN_Aggregator::maybe_run( 30 );
		$range = self::range( $f );
		$g     = self::legacy_range( $f );
		$goal  = $o['goal'] ?? null;
		$rep   = self::arr(
			FLXAN_Journeys::report(
				self::str( $g['start'] ?? '' ),
				self::str( $g['end'] ?? '' ),
				self::str( $g['prev_start'] ?? '' ),
				self::str( $g['prev_end'] ?? '' ),
				array(
					'steps' => (int) ( $o['steps'] ?? 4 ),
					'start' => (string) ( $o['from'] ?? '' ),
					'end'   => (string) ( $o['to'] ?? '' ),
					'goal'  => null === $goal ? null : (string) $goal,
					'group' => ! empty( $o['group'] ),
					'top'   => (int) ( $o['top'] ?? 15 ),
				)
			)
		);
		$opt     = self::arr( $rep['options'] ?? array() );
		$sum     = self::arr( $rep['summary'] ?? array() );
		$flow    = self::arr( $rep['flow'] ?? array() );
		$paths   = self::arr( $rep['paths'] ?? array() );
		$columns = array();
		foreach ( self::arr( $flow['steps'] ?? array() ) as $col ) {
			$nodes = array();
			foreach ( self::rows( $col ) as $n ) {
				$nodes[] = array(
					'key'   => self::str( $n['key'] ?? '' ),
					'label' => self::str( $n['label'] ?? '' ),
					'path'  => self::str( $n['path'] ?? '' ),
					'ev'    => ! empty( $n['ev'] ),
					'count' => self::int( $n['count'] ?? 0 ),
					'ends'  => self::int( $n['ends'] ?? 0 ),
				);
			}
			$columns[] = array( 'nodes' => $nodes );
		}
		$links = array();
		foreach ( self::rows( $flow['links'] ?? array() ) as $l ) {
			$links[] = array( 'step' => self::int( $l['step'] ?? 0 ), 'from' => self::str( $l['from'] ?? '' ), 'to' => self::str( $l['to'] ?? '' ), 'count' => self::int( $l['count'] ?? 0 ) );
		}
		$items = array();
		foreach ( self::rows( $paths['items'] ?? array() ) as $p ) {
			$steps = array();
			foreach ( self::rows( $p['steps'] ?? array() ) as $s ) {
				$steps[] = array( 'key' => self::str( $s['key'] ?? '' ), 'label' => self::str( $s['label'] ?? '' ), 'path' => self::str( $s['path'] ?? '' ), 'ev' => ! empty( $s['ev'] ) );
			}
			$items[] = array(
				'steps'    => $steps,
				'sessions' => self::int( $p['sessions'] ?? 0 ),
				'reached'  => self::int( $p['reached'] ?? 0 ),
				'share'    => self::num( $p['share'] ?? 0 ),
				'prev'     => self::int( $p['prev'] ?? 0 ),
				'change'   => self::opt_num( $p['change'] ?? null ),
				'rate'     => self::num( $p['rate'] ?? 0 ),
			);
		}
		$top_entry = isset( $sum['top_entry'] ) && is_array( $sum['top_entry'] ) ? $sum['top_entry'] : null;
		$top_exit  = isset( $sum['top_exit'] ) && is_array( $sum['top_exit'] ) ? $sum['top_exit'] : null;
		return array(
			'range'   => $range,
			'options' => array(
				'steps' => self::int( $opt['steps'] ?? 4 ),
				'from'  => self::str( $opt['start'] ?? '' ),
				'to'    => self::str( $opt['end'] ?? '' ),
				'goal'  => self::str( $opt['goal'] ?? '' ),
				'group' => ! empty( $opt['group'] ),
				'top'   => self::int( $opt['top'] ?? 15 ),
			),
			'goals'   => self::options( array_map( array( __CLASS__, 'str' ), self::arr( $rep['goals'] ?? array() ) ) ),
			'summary' => array(
				'sessions'     => self::int( $sum['sessions'] ?? 0 ),
				'multi'        => self::int( $sum['multi'] ?? 0 ),
				'multiPct'     => self::num( $sum['multi_pct'] ?? 0 ),
				'multiChange'  => self::opt_num( $sum['multi_change'] ?? null ),
				'avgLen'       => self::num( $sum['avg_len'] ?? 0 ),
				'avgLenChange' => self::opt_num( $sum['avg_len_change'] ?? null ),
				'topEntry'     => self::map_jnode( $top_entry ),
				'topExit'      => self::map_jnode( $top_exit ),
				'goalRate'     => self::opt_num( $sum['goal_rate'] ?? null ),
				'goalReached'  => self::int( $sum['goal_reached'] ?? 0 ),
				'goalChange'   => self::opt_num( $sum['goal_change'] ?? null ),
			),
			'flow'    => array( 'columns' => $columns, 'links' => $links ),
			'paths'   => array( 'top' => self::int( $paths['top'] ?? 15 ), 'total' => self::int( $paths['total'] ?? 0 ), 'items' => $items ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Config and settings                                                 */
	/* ------------------------------------------------------------------ */

	/**
	 * Static data the Statistics and Journeys screens need once.
	 *
	 * @return Config
	 */
	public static function config(): array {
		$roles   = (array) wp_get_current_user()->roles;
		$ignored = (bool) array_intersect( $roles, array_map( 'strval', self::arr( flxan_settings( 'exclude_roles' ) ) ) );
		$test    = current_user_can( 'manage_options' ) && 'rest' !== flxan_settings( 'method' ) && flxan_fast_possible()
			&& ( time() - (int) get_option( 'flxan_fast_test_time', 0 ) > DAY_IN_SECONDS );
		$segs    = array(
			'all'     => __( 'All visitors', 'flx-analytics' ),
			'guest'   => __( 'Outside visitors', 'flx-analytics' ),
			'members' => __( 'Logged-in people', 'flx-analytics' ),
		);
		foreach ( FLXAN_Stats::seg_options() as $seg => $label ) {
			/* translators: %s: role name */
			$segs[ (string) $seg ] = sprintf( __( 'Role: %s', 'flx-analytics' ), $label );
		}
		$channels = array();
		foreach ( array( 'direct', 'search', 'social', 'ai', 'email', 'paid', 'campaign', 'referral', 'ref' ) as $c ) {
			$channels[ $c ] = self::str( flxan_dim_label( 'channel', $c ) );
		}
		$goals = array_map( array( __CLASS__, 'str' ), FLXAN_Journeys::goals() );
		return array(
			'presets'           => self::options( array_map( 'strval', FLXAN_Stats::presets() ) ),
			'kinds'             => self::options( array_map( 'strval', FLXAN_Stats::kinds() ) ),
			'events'            => self::options( array_map( 'strval', FLXAN_Stats::event_labels() ) ),
			'channels'          => self::options( $channels ),
			'segments'          => self::options( $segs ),
			'funnelBy'          => self::options( array_map( 'strval', FLXAN_Stats::funnel_by() ) ),
			'goals'             => self::options( $goals ),
			'defaultGoal'       => self::str( FLXAN_Journeys::default_goal() ),
			'canManageRefs'     => flxan_can_manage_refs(),
			'canManage'         => current_user_can( 'manage_options' ),
			'ignoreBrowser'     => $ignored,
			'fastEndpoint'      => $test ? FLXAN_URL . 'endpoint.php' : '',
			'exportUrl'         => wp_nonce_url( admin_url( 'admin-post.php?action=flxan_export' ), 'flxan_export' ),
			'homeUrl'           => home_url(),
			'settingsUrl'       => current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=' . self::PAGE . '-settings' ) : '',
			'funnelSettingsUrl' => current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=' . self::PAGE . '-settings#flxan-funnels' ) : '',
			'hasWorldMap'       => is_file( FLXAN_DIR . 'assets/world.js' ),
		);
	}

	/**
	 * Funnel definitions as shapes.
	 *
	 * @param mixed $v Stored funnels.
	 * @return list<FunnelDef>
	 */
	private static function map_funnel_defs( $v ): array {
		$out = array();
		foreach ( self::rows( $v ) as $f ) {
			$steps = array();
			foreach ( self::rows( $f['steps'] ?? array() ) as $s ) {
				$steps[] = array( 'label' => self::str( $s['label'] ?? '' ), 'type' => self::str( $s['type'] ?? 'path', 'path' ), 'value' => self::str( $s['value'] ?? '' ) );
			}
			$out[] = array( 'id' => self::int( $f['id'] ?? 0 ), 'name' => self::str( $f['name'] ?? '' ), 'steps' => $steps );
		}
		return $out;
	}

	/**
	 * Settings screen data.
	 *
	 * @return SettingsOut
	 */
	public static function settings(): array {
		$s     = self::arr( flxan_settings() );
		$roles = array();
		foreach ( wp_roles()->get_names() as $key => $name ) {
			$roles[ (string) $key ] = translate_user_role( $name );
		}
		$events = array_map( 'strval', flxan_funnel_event_options() );
		$pages  = array();
		foreach ( self::rows( flxan_funnel_page_options() ) as $p ) {
			$pages[] = array( 'path' => self::str( $p['path'] ?? '' ), 'title' => self::str( $p['title'] ?? '' ) );
		}
		$koko = flxan_koko_available();
		$pend = (int) FLXAN_Aggregator::pending_bytes();
		return array(
			'on'             => ! empty( $s['on'] ),
			'method'         => self::str( $s['method'] ?? 'auto', 'auto' ),
			'methodNow'      => 'fast' === flxan_method() ? 'fast' : 'rest',
			'dnt'            => ! empty( $s['dnt'] ),
			'excludeRoles'   => array_values( array_map( array( __CLASS__, 'str' ), self::arr( $s['exclude_roles'] ?? array() ) ) ),
			'excludeIps'     => self::str( $s['exclude_ips'] ?? '' ),
			'evOutbound'     => ! empty( $s['ev_outbound'] ),
			'evDownload'     => ! empty( $s['ev_download'] ),
			'evContact'      => ! empty( $s['ev_contact'] ),
			'evSearch'       => ! empty( $s['ev_search'] ),
			'evDonation'     => ! empty( $s['ev_donation'] ),
			'postsColumn'    => ! empty( $s['posts_column'] ),
			'adminbar'       => ! empty( $s['adminbar'] ),
			'keepMonths'     => self::int( $s['keep_months'] ?? 0 ),
			'tmHub'          => ! empty( $s['tm_hub'] ),
			'hubEndpoint'    => class_exists( 'FLXAN_Telemetry_Hub' ) ? self::str( FLXAN_Telemetry_Hub::endpoint() ) : null,
			'lastAggregated' => (int) get_option( 'flxan_last_agg', 0 ),
			'pendingBytes'   => $pend,
			'pendingLabel'   => self::str( size_format( $pend ), '0 B' ),
			'fastPossible'   => (bool) flxan_fast_possible(),
			'fastTest'       => self::str( get_option( 'flxan_fast_test', '' ) ),
			'roles'          => self::options( $roles ),
			'funnels'        => self::map_funnel_defs( flxan_funnels() ),
			'funnelDefaults' => self::map_funnel_defs( flxan_default_funnels() ),
			'funnelEvents'   => self::options( $events ),
			'funnelPages'    => $pages,
			'koko'           => is_array( $koko ) ? array( 'days' => self::int( $koko['days'] ?? 0 ), 'first' => self::str( $koko['first'] ?? '' ), 'last' => self::str( $koko['last'] ?? '' ) ) : null,
			'exportUrl'      => wp_nonce_url( admin_url( 'admin-post.php?action=flxan_export' ), 'flxan_export' ),
		);
	}

	/**
	 * Stores sanitized settings (the classic form and GraphQL end here).
	 *
	 * @param array<mixed> $in Raw settings (form keys).
	 */
	public static function store_settings( array $in ): void {
		update_option( 'flxan_settings', flxan_sanitize_settings( $in ), false );
		flxan_settings( 'flush' );
	}

	/**
	 * Stores funnels; an empty list or the defaults remove the custom set.
	 *
	 * @param list<FunnelDef>|list<array<mixed>> $funnels Sanitized funnels.
	 */
	public static function store_funnels( array $funnels ): void {
		$def = flxan_funnels_to_text( flxan_default_funnels() );
		if ( ! $funnels || trim( flxan_funnels_to_text( $funnels ) ) === trim( $def ) ) {
			delete_option( 'flxan_funnels' );
		} else {
			update_option( 'flxan_funnels', $funnels, false );
		}
	}

	/**
	 * Changes settings. Fields that are not given stay as they are; the funnels list replaces the funnels when given.
	 *
	 * @param array<string,mixed> $in Fields: on, method, dnt, excludeRoles, excludeIps, ev*, postsColumn, adminbar, keepMonths, tmHub, funnels.
	 * @return SettingsOut
	 */
	public static function save_settings( array $in ): array {
		$map = array(
			'on'           => 'on',
			'method'       => 'method',
			'dnt'          => 'dnt',
			'excludeRoles' => 'exclude_roles',
			'excludeIps'   => 'exclude_ips',
			'evOutbound'   => 'ev_outbound',
			'evDownload'   => 'ev_download',
			'evContact'    => 'ev_contact',
			'evSearch'     => 'ev_search',
			'evDonation'   => 'ev_donation',
			'postsColumn'  => 'posts_column',
			'adminbar'     => 'adminbar',
			'keepMonths'   => 'keep_months',
			'tmHub'        => 'tm_hub',
		);
		$cur = self::arr( flxan_settings() );
		foreach ( $map as $gql => $key ) {
			if ( array_key_exists( $gql, $in ) && null !== $in[ $gql ] ) {
				$cur[ $key ] = is_bool( $in[ $gql ] ) ? (int) $in[ $gql ] : $in[ $gql ];
			}
		}
		self::store_settings( $cur );
		if ( array_key_exists( 'funnels', $in ) && is_array( $in['funnels'] ) ) {
			self::store_funnels( self::rows( flxan_funnels_sanitize( $in['funnels'] ) ) );
		}
		flxan_write_config();
		flx_gql_purge_safe( self::CACHE_TAG );
		return self::settings();
	}

	/** Processes the visit queue now; returns the rows handled. */
	public static function aggregate(): int {
		return max( 0, (int) FLXAN_Aggregator::run( 25 ) );
	}

	/** Deletes every statistic. */
	public static function reset(): void {
		FLXAN_Aggregator::reset();
		flx_gql_purge_safe( self::CACHE_TAG );
	}

	/** Imports Koko Analytics data; returns the rows written. */
	public static function koko_import(): int {
		$r = flxan_koko_import();
		return is_array( $r ) ? (int) array_sum( array_map( array( __CLASS__, 'int' ), $r ) ) : 0;
	}

	/**
	 * Remembers the result of the fast-endpoint test the browser ran.
	 *
	 * @return string The collection method in use now (fast | rest).
	 */
	public static function fast_test( bool $ok ): string {
		update_option( 'flxan_fast_test', $ok ? 'ok' : 'fail', false );
		update_option( 'flxan_fast_test_time', time(), false );
		return 'fast' === flxan_method() ? 'fast' : 'rest';
	}
}

/** Purges the GraphQL cache tag when Flux GraphQL is there (the service also runs without it). */
function flx_gql_purge_safe( string $tag ): void {
	if ( function_exists( 'flx_gql_purge' ) ) {
		flx_gql_purge( array( $tag ) );
	}
}
