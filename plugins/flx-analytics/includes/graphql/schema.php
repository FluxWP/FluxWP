<?php
/**
 * Flux Analytics GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a
 * GraphQL request. Resolvers call FLXAN_Service / FLXAN_Telemetry_Service; the Statistics, Journeys,
 * Settings and Product telemetry screens (wp-admin and the FluxWP app) read and write only through
 * these fields.
 *
 * Out of scope on purpose: visit collection (endpoint.php, POST /flxan/v1/c) stays a public,
 * high-volume write path; the telemetry ingest endpoint stays REST; CSV downloads stay admin-post.
 * Reports read stored daily totals (no raw visit scans), so one query stays within the Hostinger limits.
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

/**
 * Service result → value, or the WP_Error as a typed GraphQL error.
 *
 * @template T
 * @param T|WP_Error $r Result.
 * @return T
 * @throws FLXGQL_Error On WP_Error.
 */
function flxan_gql_ok( $r ) {
	if ( $r instanceof WP_Error ) {
		throw flx_gql_from_wp_error( $r );
	}
	return $r;
}

/**
 * The shared filter argument as the service takes it.
 *
 * @param array<string,mixed> $args Field arguments.
 * @return array{range?: string|null, start?: string|null, end?: string|null, seg?: string|null, rc?: string|null}
 */
function flxan_gql_filter( array $args ): array {
	$f = isset( $args['filter'] ) && is_array( $args['filter'] ) ? $args['filter'] : array();
	return array(
		'range' => isset( $f['range'] ) ? FLXAN_Service::str( $f['range'] ) : null,
		'start' => isset( $f['start'] ) ? FLXAN_Service::str( $f['start'] ) : null,
		'end'   => isset( $f['end'] ) ? FLXAN_Service::str( $f['end'] ) : null,
		'seg'   => isset( $f['seg'] ) ? FLXAN_Service::str( $f['seg'] ) : null,
		'rc'    => isset( $f['rc'] ) ? FLXAN_Service::str( $f['rc'] ) : null,
	);
}

/** Statistics permission: managers and people with flxan_view. */
function flxan_gql_can_view(): bool {
	return (bool) flxan_can_view();
}

/** Referral name permission. */
function flxan_gql_can_refs(): bool {
	return (bool) flxan_can_manage_refs();
}

/** Settings and data permission. */
function flxan_gql_can_manage(): bool {
	return (bool) current_user_can( 'manage_options' );
}

/** Telemetry hub permission: managers, and the hub is switched on. */
function flxan_gql_can_hub(): bool {
	return current_user_can( 'manage_options' ) && FLXAN_Telemetry_Service::on();
}

/**
 * Registers an object type from a compact field map.
 *
 * @param string                                                  $name   Type name.
 * @param string                                                  $desc   Description.
 * @param array<string,array{0: string, 1: string, 2?: callable}> $fields name => [ type, description, resolve? ].
 */
function flxan_gql_t( string $name, string $desc, array $fields ): void {
	$out = array();
	foreach ( $fields as $key => $d ) {
		$out[ $key ] = array( 'type' => $d[0], 'description' => $d[1] );
		if ( isset( $d[2] ) ) {
			$out[ $key ]['resolve'] = $d[2];
		}
	}
	flx_gql_type( $name, array( 'description' => $desc, 'fields' => $out ) );
}

/**
 * Registers a connection node type with its list.
 *
 * @param string $node Node type.
 */
function flxan_gql_conn( string $node ): void {
	flx_gql_connection( $node );
}

/**
 * A FlxDateTime from a stored timestamp.
 *
 * @param mixed $source Parent shape.
 * @param string $key   Key of the timestamp in the shape.
 * @return array<string,mixed>|null
 */
function flxan_gql_time( $source, string $key ) {
	$ts = is_array( $source ) ? FLXAN_Service::int( $source[ $key ] ?? 0 ) : 0;
	return $ts > 0 ? flx_gql_datetime( $ts ) : null;
}

/** Cache tag every report reads (purged when data or settings change). */
function flxan_gql_tag(): void {
	flx_gql_cache_tag( FLXAN_Service::CACHE_TAG );
}

/* ---------------------------------------------------------------------- */
/* Enums and inputs                                                        */
/* ---------------------------------------------------------------------- */

flx_gql_enum(
	'FlxAnalyticsRangePreset',
	array(
		'description' => 'Ready-made date range of a report.',
		'values'      => array(
			'TODAY'      => array( 'value' => 'today' ),
			'YESTERDAY'  => array( 'value' => 'yesterday' ),
			'DAYS_7'     => array( 'value' => '7d' ),
			'DAYS_30'    => array( 'value' => '30d' ),
			'DAYS_90'    => array( 'value' => '90d' ),
			'THIS_MONTH' => array( 'value' => 'this_month' ),
			'LAST_MONTH' => array( 'value' => 'last_month' ),
			'MONTHS_12'  => array( 'value' => '12m' ),
			'THIS_YEAR'  => array( 'value' => 'this_year' ),
			'LAST_YEAR'  => array( 'value' => 'last_year' ),
			'ALL'        => array( 'value' => 'all' ),
			'CUSTOM'     => array( 'value' => 'custom' ),
		),
	)
);
flx_gql_enum(
	'FlxAnalyticsSeriesGroup',
	array(
		'description' => 'How a time series is grouped.',
		'values'      => array(
			'DAY'   => array( 'value' => 'day' ),
			'WEEK'  => array( 'value' => 'week' ),
			'MONTH' => array( 'value' => 'month' ),
		),
	)
);
flx_gql_enum(
	'FlxAnalyticsDimKind',
	array(
		'description' => 'What a breakdown splits the traffic by.',
		'values'      => array(
			'CHANNEL'      => array( 'value' => 'channel' ),
			'DEVICE'       => array( 'value' => 'device' ),
			'BROWSER'      => array( 'value' => 'browser' ),
			'OS'           => array( 'value' => 'os' ),
			'LANG'         => array( 'value' => 'lang' ),
			'COUNTRY'      => array( 'value' => 'country' ),
			'UTM_SOURCE'   => array( 'value' => 'utm_source' ),
			'UTM_MEDIUM'   => array( 'value' => 'utm_medium' ),
			'UTM_CAMPAIGN' => array( 'value' => 'utm_campaign' ),
		),
	)
);
flx_gql_enum(
	'FlxAnalyticsFunnelBy',
	array(
		'description' => 'How a funnel is split into groups.',
		'values'      => array(
			'TOTAL'   => array( 'value' => 'total' ),
			'CHANNEL' => array( 'value' => 'channel' ),
			'REF'     => array( 'value' => 'ref' ),
			'RC'      => array( 'value' => 'rc' ),
			'SEG'     => array( 'value' => 'seg' ),
			'COUNTRY' => array( 'value' => 'country' ),
		),
	)
);
flx_gql_enum(
	'FlxAnalyticsStepType',
	array(
		'description' => 'What a funnel step matches.',
		'values'      => array(
			'ANY'   => array( 'value' => 'any' ),
			'PATH'  => array( 'value' => 'path' ),
			'EVENT' => array( 'value' => 'event' ),
		),
	)
);
flx_gql_enum(
	'FlxAnalyticsCollectMethod',
	array(
		'description' => 'How visits are sent to the site.',
		'values'      => array(
			'AUTO' => array( 'value' => 'auto' ),
			'FAST' => array( 'value' => 'fast' ),
			'REST' => array( 'value' => 'rest' ),
		),
	)
);

flx_gql_input(
	'FlxAnalyticsFilterInput',
	array(
		'description' => 'Date range and visitor filters shared by every report.',
		'fields'      => array(
			'range' => array( 'type' => 'FlxAnalyticsRangePreset', 'description' => 'Ready-made range; default is the last 30 days.' ),
			'start' => array( 'type' => 'String', 'description' => 'First day (YYYY-MM-DD) when the range is custom.' ),
			'end'   => array( 'type' => 'String', 'description' => 'Last day (YYYY-MM-DD) when the range is custom.' ),
			'seg'   => array( 'type' => 'String', 'description' => 'Visitor type: all (default), guest, members or a role name.' ),
			'rc'    => array( 'type' => 'String', 'description' => 'Referral code filter: empty = all, * = only referral links, or a code.' ),
		),
	)
);
flx_gql_input(
	'FlxAnalyticsJourneysInput',
	array(
		'description' => 'Options of the journeys report.',
		'fields'      => array(
			'steps' => array( 'type' => 'Int', 'description' => 'Steps to show, 2–6 (default 4).' ),
			'from'  => array( 'type' => 'String', 'description' => 'Only journeys that start on this path.' ),
			'to'    => array( 'type' => 'String', 'description' => 'Only journeys that end on this path.' ),
			'goal'  => array( 'type' => 'String', 'description' => 'Goal "funnel:step"; empty = no goal; not given = the default goal.' ),
			'group' => array( 'type' => 'Boolean', 'description' => 'Group sub pages (/news/…) into one box.' ),
			'top'   => array( 'type' => 'Int', 'description' => 'Paths to list, 5–100 (default 15).' ),
		),
	)
);
flx_gql_input(
	'FlxAnalyticsFunnelStepInput',
	array(
		'description' => 'One funnel step.',
		'fields'      => array(
			'label' => array( 'type' => 'String!', 'description' => 'Name shown in the report.' ),
			'type'  => array( 'type' => 'FlxAnalyticsStepType!', 'description' => 'Site entry, a page path prefix or an event.' ),
			'value' => array( 'type' => 'String', 'description' => 'Path (/donate/) or event (name or name:detail); empty for site entry.' ),
		),
	)
);
flx_gql_input(
	'FlxAnalyticsFunnelInput',
	array(
		'description' => 'A funnel: steps visitors go through in order.',
		'fields'      => array(
			'name'  => array( 'type' => 'String!', 'description' => 'Funnel name.' ),
			'steps' => array( 'type' => '[FlxAnalyticsFunnelStepInput!]!', 'description' => 'Steps, 10 at most.' ),
		),
	)
);

/* ---------------------------------------------------------------------- */
/* Shared report types                                                     */
/* ---------------------------------------------------------------------- */

flxan_gql_t(
	'FlxAnalyticsOption',
	'A value with its label.',
	array(
		'value' => array( 'String!', 'Value.' ),
		'label' => array( 'String!', 'Label in the user\'s language.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsRange',
	'The resolved date range and the equal period before it.',
	array(
		'preset'    => array( 'FlxAnalyticsRangePreset!', 'Range preset.' ),
		'start'     => array( 'String!', 'First day (YYYY-MM-DD, site time zone).' ),
		'end'       => array( 'String!', 'Last day (YYYY-MM-DD, site time zone).' ),
		'label'     => array( 'String!', 'Name of the range.' ),
		'days'      => array( 'Int!', 'Days in the range.' ),
		'prevStart' => array( 'String!', 'First day of the previous equal period.' ),
		'prevEnd'   => array( 'String!', 'Last day of the previous equal period.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsFilterState',
	'The visitor filters in effect.',
	array(
		'seg' => array( 'String!', 'Visitor type filter.' ),
		'rc'  => array( 'String!', 'Referral code filter.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTotals',
	'Visitors and page views.',
	array(
		'visitors'  => array( 'Int!', 'Sum of each day\'s unique visitors.' ),
		'pageviews' => array( 'Int!', 'Page views.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsVisits',
	'Visit (session) measures.',
	array(
		'sessions'    => array( 'Int!', 'Visits.' ),
		'avgDuration' => array( 'Int!', 'Average visit length in seconds.' ),
		'bounceRate'  => array( 'Float!', 'Percent of visits that saw one page.' ),
		'pagesPer'    => array( 'Float!', 'Pages per visit.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsChange',
	'Percent change against the previous equal period; empty when that period had no data.',
	array(
		'visitors'    => array( 'Float', 'Visitors change.' ),
		'pageviews'   => array( 'Float', 'Page views change.' ),
		'sessions'    => array( 'Float', 'Visits change.' ),
		'avgDuration' => array( 'Float', 'Average visit length change.' ),
		'bounceRate'  => array( 'Float', 'Bounce rate change.' ),
		'pagesPer'    => array( 'Float', 'Pages per visit change.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsPoint',
	'One bar of the visitors chart.',
	array(
		'date'      => array( 'String!', 'First day of the bar (YYYY-MM-DD).' ),
		'visitors'  => array( 'Int!', 'Visitors.' ),
		'pageviews' => array( 'Int!', 'Page views.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsSeries',
	'Visitors over time; missing days are zero.',
	array(
		'group' => array( 'FlxAnalyticsSeriesGroup!', 'Bar size (day for up to 92 days, week up to 400, then month).' ),
		'items' => array( '[FlxAnalyticsPoint!]!', 'Bars, oldest first.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsKpi',
	'Headline numbers and the chart.',
	array(
		'totals'     => array( 'FlxAnalyticsTotals!', 'Totals in the range.' ),
		'prev'       => array( 'FlxAnalyticsTotals!', 'Totals in the previous equal period.' ),
		'visits'     => array( 'FlxAnalyticsVisits!', 'Visit measures in the range.' ),
		'visitsPrev' => array( 'FlxAnalyticsVisits!', 'Visit measures in the previous period.' ),
		'change'     => array( 'FlxAnalyticsChange!', 'Percent changes.' ),
		'series'     => array( 'FlxAnalyticsSeries!', 'Chart data.' ),
	)
);

flxan_gql_t(
	'FlxAnalyticsPageRow',
	'A page and its views.',
	array(
		'path'      => array( 'String!', 'Address path.' ),
		'url'       => array( 'String!', 'Full address.' ),
		'postId'    => array( 'Int!', 'Post ID; 0 when the address is not a post.' ),
		'title'     => array( 'String!', 'Page title (the path when the page has none).' ),
		'is404'     => array( 'Boolean!', 'The address was not found.' ),
		'visitors'  => array( 'Int!', 'Visitors.' ),
		'pageviews' => array( 'Int!', 'Page views.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsPageList',
	'The first pages of a list and how many there are.',
	array(
		'total' => array( 'Int!', 'All matching pages.' ),
		'items' => array( '[FlxAnalyticsPageRow!]!', 'The first pages.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsReferrerRow',
	'A site that sent visitors.',
	array(
		'host'         => array( 'String!', 'Domain name.' ),
		'channel'      => array( 'String!', 'Channel key (search, social, ai, referral …).' ),
		'channelLabel' => array( 'String!', 'Channel name in the user\'s language.' ),
		'visitors'     => array( 'Int!', 'Visitors.' ),
		'pageviews'    => array( 'Int!', 'Page views.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsReferrerList',
	'The first referring sites of a list and how many there are.',
	array(
		'total' => array( 'Int!', 'All matching sites.' ),
		'items' => array( '[FlxAnalyticsReferrerRow!]!', 'The first sites.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsDimRow',
	'One value of a breakdown (a browser, a country …).',
	array(
		'value'     => array( 'String!', 'Stored value.' ),
		'label'     => array( 'String!', 'Name in the user\'s language.' ),
		'visitors'  => array( 'Int!', 'Visitors.' ),
		'pageviews' => array( 'Int!', 'Page views.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsDimList',
	'The top values of one breakdown.',
	array(
		'kind'  => array( 'FlxAnalyticsDimKind!', 'What the traffic is split by.' ),
		'total' => array( 'Int!', 'All distinct values.' ),
		'items' => array( '[FlxAnalyticsDimRow!]!', 'The top values.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsEventSum',
	'Total of one event type.',
	array(
		'name'    => array( 'String!', 'Event name.' ),
		'label'   => array( 'String!', 'Event name in the user\'s language.' ),
		'hits'    => array( 'Int!', 'Times it happened.' ),
		'uniques' => array( 'Int!', 'Distinct visitors.' ),
		'amount'  => array( 'Float!', 'Total amount (donations and orders).' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsEventRow',
	'An event and its detail (the link, the file, the search term …).',
	array(
		'name'    => array( 'String!', 'Event name.' ),
		'label'   => array( 'String!', 'Event name in the user\'s language.' ),
		'param'   => array( 'String!', 'Detail; empty when the event has none.' ),
		'hits'    => array( 'Int!', 'Times it happened.' ),
		'uniques' => array( 'Int!', 'Distinct visitors.' ),
		'amount'  => array( 'Float!', 'Total amount.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsEventList',
	'The first events of a list and how many there are.',
	array(
		'total' => array( 'Int!', 'All matching events.' ),
		'items' => array( '[FlxAnalyticsEventRow!]!', 'The first events.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsSegment',
	'Traffic of one visitor type.',
	array(
		'value'     => array( 'String!', 'Role name; empty for outside visitors.' ),
		'label'     => array( 'String!', 'Name in the user\'s language.' ),
		'visitors'  => array( 'Int!', 'Visitors.' ),
		'pageviews' => array( 'Int!', 'Page views.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsRef',
	'A referral link (?r=code) and what its visitors did.',
	array(
		'code'         => array( 'String!', 'Referral code.' ),
		'name'         => array( 'String!', 'Name given to the code; empty when none.' ),
		'label'        => array( 'String!', 'Name, or the code when there is no name.' ),
		'url'          => array( 'String!', 'The link to share.' ),
		'visitors'     => array( 'Int!', 'Visitors.' ),
		'pageviews'    => array( 'Int!', 'Page views.' ),
		'sessions'     => array( 'Int!', 'Visits.' ),
		'pagesPer'     => array( 'Float!', 'Pages per visit.' ),
		'avgDuration'  => array( 'Int!', 'Average visit length in seconds.' ),
		'donations'    => array( 'Int!', 'Paid donations.' ),
		'amount'       => array( 'Float!', 'Donated amount.' ),
		'deepest'      => array( 'Int!', 'Furthest first-funnel step reached (0 = none).' ),
		'deepestLabel' => array( 'String!', 'Name of that step.' ),
		'completed'    => array( 'Int!', 'Visits that completed the first funnel.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsRefSaved',
	'A saved referral code.',
	array(
		'code' => array( 'String!', 'Referral code.' ),
		'name' => array( 'String!', 'Name; empty when removed.' ),
		'url'  => array( 'String!', 'The link to share.' ),
	)
);

flxan_gql_t(
	'FlxAnalyticsFunnelStep',
	'One funnel step with the visits that reached it.',
	array(
		'label' => array( 'String!', 'Step name.' ),
		'type'  => array( 'FlxAnalyticsStepType!', 'What the step matches.' ),
		'value' => array( 'String!', 'Path or event of the step.' ),
		'count' => array( 'Int!', 'Visits that reached this step or went further.' ),
		'rate'  => array( 'Float!', 'Percent of the first step.' ),
		'drop'  => array( 'Float!', 'Percent lost since the previous step.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsFunnelGroup',
	'A group (channel, site, code …) with its funnel counts.',
	array(
		'value'  => array( 'String!', 'Group key.' ),
		'label'  => array( 'String!', 'Group name.' ),
		'counts' => array( '[Int!]!', 'Visits per step.' ),
		'rate'   => array( 'Float!', 'Percent that completed the funnel.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsFunnelName',
	'A defined funnel.',
	array(
		'id'   => array( 'Int!', 'Funnel number.' ),
		'name' => array( 'String!', 'Funnel name.' ),
	)
);
flx_gql_type(
	'FlxAnalyticsFunnel',
	array(
		'description' => 'A funnel report.',
		'fields'      => array(
			'found'   => array( 'type' => 'Boolean!', 'description' => 'A funnel is defined.' ),
			'id'      => array( 'type' => 'Int', 'description' => 'Funnel number; empty when none is defined.' ),
			'name'    => array( 'type' => 'String', 'description' => 'Funnel name; empty when none is defined.' ),
			'by'      => array(
				'type'        => 'FlxAnalyticsFunnelBy!',
				'description' => 'How the groups were formed.',
				'resolve'     => static fn( $f ): string => is_array( $f ) && '' !== FLXAN_Service::str( $f['by'] ?? '' ) ? FLXAN_Service::str( $f['by'] ) : 'total',
			),
			'steps'   => array( 'type' => '[FlxAnalyticsFunnelStep!]!', 'description' => 'Steps.' ),
			'groups'  => array( 'type' => '[FlxAnalyticsFunnelGroup!]!', 'description' => 'Groups (up to 8) when the funnel is split.' ),
			'funnels' => array( 'type' => '[FlxAnalyticsFunnelName!]!', 'description' => 'Every defined funnel.' ),
		),
	)
);

flxan_gql_t(
	'FlxAnalyticsLivePage',
	'A page people are on now.',
	array(
		'path'      => array( 'String!', 'Address path.' ),
		'title'     => array( 'String!', 'Page title.' ),
		'pageviews' => array( 'Int!', 'Page views in the window.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsLiveReferrer',
	'A site people came from just now.',
	array(
		'host'      => array( 'String!', 'Domain name.' ),
		'pageviews' => array( 'Int!', 'Page views in the window.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsLiveCountry',
	'A country people are visiting from now.',
	array(
		'value'    => array( 'String!', 'Country code.' ),
		'label'    => array( 'String!', 'Country name in the user\'s language.' ),
		'visitors' => array( 'Int!', 'Visitors in the window.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsLiveCode',
	'A referral code of the people on the site now.',
	array(
		'code'     => array( 'String!', 'Referral code.' ),
		'label'    => array( 'String!', 'Name, or the code.' ),
		'visitors' => array( 'Int!', 'Visitors in the window.' ),
	)
);
flx_gql_type(
	'FlxAnalyticsRealtime',
	array(
		'description' => 'Who is on the site now.',
		'fields'      => array(
			'minutes'   => array( 'type' => 'Int!', 'description' => 'Window in minutes.' ),
			'active'    => array( 'type' => 'Int!', 'description' => 'People on the site in the last 5 minutes.' ),
			'visitors'  => array( 'type' => 'Int!', 'description' => 'Visitors in the window.' ),
			'pageviews' => array( 'type' => 'Int!', 'description' => 'Page views in the window.' ),
			'perMinute' => array( 'type' => '[Int!]!', 'description' => 'Page views per minute, oldest first.' ),
			'pages'     => array( 'type' => '[FlxAnalyticsLivePage!]!', 'description' => 'Busiest pages (5).' ),
			'referrers' => array( 'type' => '[FlxAnalyticsLiveReferrer!]!', 'description' => 'Busiest referring sites (5).' ),
			'countries' => array( 'type' => '[FlxAnalyticsLiveCountry!]!', 'description' => 'Countries (10).' ),
			'codes'     => array( 'type' => '[FlxAnalyticsLiveCode!]!', 'description' => 'Referral codes (5).' ),
			'updated'   => array(
				'type'        => 'FlxDateTime',
				'description' => 'When the visit queue was last processed; empty when never.',
				'resolve'     => static fn( $r ) => flxan_gql_time( $r, 'updated' ),
			),
			'kpi'       => array( 'type' => 'FlxAnalyticsKpi', 'description' => 'Headline numbers and chart, when asked for.' ),
		),
	)
);

flxan_gql_t(
	'FlxAnalyticsReport',
	'Everything the Statistics screen shows.',
	array(
		'range'        => array( 'FlxAnalyticsRange!', 'The date range.' ),
		'filter'       => array( 'FlxAnalyticsFilterState!', 'Visitor filters in effect.' ),
		'kpi'          => array( 'FlxAnalyticsKpi!', 'Headline numbers and the chart.' ),
		'pages'        => array( 'FlxAnalyticsPageList!', 'Most viewed pages (10).' ),
		'notFound'     => array( 'FlxAnalyticsPageList!', 'Addresses that were not found (5).' ),
		'referrers'    => array( 'FlxAnalyticsReferrerList!', 'Referring sites (10).' ),
		'dims'         => array( '[FlxAnalyticsDimList!]!', 'Breakdowns: channel, device, browser, system, language, country and campaign values.' ),
		'segments'     => array( '[FlxAnalyticsSegment!]!', 'Traffic by visitor type.' ),
		'refs'         => array( '[FlxAnalyticsRef!]!', 'Referral links (20).' ),
		'funnel'       => array( 'FlxAnalyticsFunnel!', 'The first funnel, split by channel.' ),
		'eventSummary' => array( '[FlxAnalyticsEventSum!]!', 'Event totals.' ),
		'events'       => array( 'FlxAnalyticsEventList!', 'Event details (10).' ),
		'realtime'     => array( 'FlxAnalyticsRealtime!', 'Who is on the site now (30 minutes).' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsPageStats',
	'One page or post.',
	array(
		'found'     => array( 'Boolean!', 'The page has any recorded views.' ),
		'paths'     => array( '[String!]!', 'Address paths that count for this page.' ),
		'visitors'  => array( 'Int!', 'Visitors in the range.' ),
		'pageviews' => array( 'Int!', 'Page views in the range.' ),
		'series'    => array( '[FlxAnalyticsPoint!]!', 'Days with views; days without are left out.' ),
	)
);

flxan_gql_conn( 'FlxAnalyticsPageRow' );
flxan_gql_conn( 'FlxAnalyticsReferrerRow' );
flxan_gql_conn( 'FlxAnalyticsDimRow' );
flxan_gql_conn( 'FlxAnalyticsEventRow' );

/* ---------------------------------------------------------------------- */
/* Journeys                                                                */
/* ---------------------------------------------------------------------- */

flxan_gql_t(
	'FlxAnalyticsJourneyOptions',
	'Options the journeys report used.',
	array(
		'steps' => array( 'Int!', 'Steps shown.' ),
		'from'  => array( 'String!', 'Start path filter; empty = all.' ),
		'to'    => array( 'String!', 'End path filter; empty = all.' ),
		'goal'  => array( 'String!', 'Goal "funnel:step"; empty = none.' ),
		'group' => array( 'Boolean!', 'Sub pages are grouped.' ),
		'top'   => array( 'Int!', 'Paths listed.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsJourneyNode',
	'A page with its visit count.',
	array(
		'key'   => array( 'String!', 'Path key.' ),
		'label' => array( 'String!', 'Page title.' ),
		'count' => array( 'Int!', 'Visits.' ),
		'pct'   => array( 'Float!', 'Percent of multi-page visits.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsJourneySummary',
	'Headline numbers of the journeys report.',
	array(
		'sessions'     => array( 'Int!', 'All visits.' ),
		'multi'        => array( 'Int!', 'Visits with at least two pages.' ),
		'multiPct'     => array( 'Float!', 'Percent of all visits.' ),
		'multiChange'  => array( 'Float', 'Percent change of multi-page visits.' ),
		'avgLen'       => array( 'Float!', 'Average journey length in pages.' ),
		'avgLenChange' => array( 'Float', 'Percent change of the average length.' ),
		'topEntry'     => array( 'FlxAnalyticsJourneyNode', 'Most common first page; empty without data.' ),
		'topExit'      => array( 'FlxAnalyticsJourneyNode', 'Most common last page after two or more pages; empty without data.' ),
		'goalRate'     => array( 'Float', 'Percent that reached the goal; empty when no goal is chosen.' ),
		'goalReached'  => array( 'Int!', 'Visits that reached the goal.' ),
		'goalChange'   => array( 'Float', 'Percent change of the goal rate.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsFlowNode',
	'A box of the flow chart.',
	array(
		'key'   => array( 'String!', 'Path key; ~ is "other pages".' ),
		'label' => array( 'String!', 'Page title.' ),
		'path'  => array( 'String!', 'Path; empty for events and other pages.' ),
		'ev'    => array( 'Boolean!', 'The step is an event (add to cart …), not a page.' ),
		'count' => array( 'Int!', 'Visits through this box.' ),
		'ends'  => array( 'Int!', 'Visits that ended here.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsFlowColumn',
	'One step of the flow chart.',
	array(
		'nodes' => array( '[FlxAnalyticsFlowNode!]!', 'Boxes, busiest first.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsFlowLink',
	'A band between two boxes.',
	array(
		'step'  => array( 'Int!', 'Column the band starts in (0 = first).' ),
		'from'  => array( 'String!', 'Key of the box it starts in.' ),
		'to'    => array( 'String!', 'Key of the box it ends in.' ),
		'count' => array( 'Int!', 'Visits.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsFlow',
	'The flow chart.',
	array(
		'columns' => array( '[FlxAnalyticsFlowColumn!]!', 'Steps from the entry page.' ),
		'links'   => array( '[FlxAnalyticsFlowLink!]!', 'Bands between steps.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsJourneyStep',
	'A step of a path.',
	array(
		'key'   => array( 'String!', 'Path key.' ),
		'label' => array( 'String!', 'Page title or event name.' ),
		'path'  => array( 'String!', 'Path; empty for events.' ),
		'ev'    => array( 'Boolean!', 'The step is an event.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsJourneyPath',
	'A frequent path.',
	array(
		'steps'    => array( '[FlxAnalyticsJourneyStep!]!', 'Steps in order.' ),
		'sessions' => array( 'Int!', 'Visits that took this path.' ),
		'reached'  => array( 'Int!', 'Of them, visits that reached the goal.' ),
		'share'    => array( 'Float!', 'Percent of multi-page visits.' ),
		'prev'     => array( 'Int!', 'Visits in the previous equal period.' ),
		'change'   => array( 'Float', 'Percent change; empty when the path is new.' ),
		'rate'     => array( 'Float!', 'Percent that reached the goal.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsJourneyPaths',
	'The most frequent paths.',
	array(
		'top'   => array( 'Int!', 'Paths asked for.' ),
		'total' => array( 'Int!', 'Distinct paths.' ),
		'items' => array( '[FlxAnalyticsJourneyPath!]!', 'Paths (up to 200; the screen filters them).' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsJourneys',
	'The journeys report.',
	array(
		'range'   => array( 'FlxAnalyticsRange!', 'The date range.' ),
		'options' => array( 'FlxAnalyticsJourneyOptions!', 'Options in effect.' ),
		'goals'   => array( '[FlxAnalyticsOption!]!', 'Goals to choose from (funnel steps).' ),
		'summary' => array( 'FlxAnalyticsJourneySummary!', 'Headline numbers.' ),
		'flow'    => array( 'FlxAnalyticsFlow!', 'The flow chart.' ),
		'paths'   => array( 'FlxAnalyticsJourneyPaths!', 'Frequent paths.' ),
	)
);

/* ---------------------------------------------------------------------- */
/* Config and settings                                                     */
/* ---------------------------------------------------------------------- */

flxan_gql_t(
	'FlxAnalyticsConfig',
	'What the screens need once: option lists, permissions and links.',
	array(
		'presets'           => array( '[FlxAnalyticsOption!]!', 'Date range names (value is the range key).' ),
		'kinds'             => array( '[FlxAnalyticsOption!]!', 'Breakdown names (value is the breakdown key).' ),
		'events'            => array( '[FlxAnalyticsOption!]!', 'Event names.' ),
		'channels'          => array( '[FlxAnalyticsOption!]!', 'Channel names.' ),
		'segments'          => array( '[FlxAnalyticsOption!]!', 'Visitor types for the filter.' ),
		'funnelBy'          => array( '[FlxAnalyticsOption!]!', 'Ways to split a funnel.' ),
		'goals'             => array( '[FlxAnalyticsOption!]!', 'Journey goals (funnel steps).' ),
		'defaultGoal'       => array( 'String!', 'The goal used when none is chosen.' ),
		'canManageRefs'     => array( 'Boolean!', 'The user may add and rename referral links.' ),
		'canManage'         => array( 'Boolean!', 'The user manages the site (settings, data).' ),
		'ignoreBrowser'     => array( 'Boolean!', 'This browser is not counted (the user\'s role is excluded).' ),
		'fastEndpoint'      => array( 'String!', 'Address of the fast endpoint to test now; empty when no test is due.' ),
		'exportUrl'         => array( 'String!', 'CSV download address (add type, range, start, end, seg, rc, kind).' ),
		'homeUrl'           => array( 'String!', 'Site address.' ),
		'settingsUrl'       => array( 'String!', 'Settings page address; empty without permission.' ),
		'funnelSettingsUrl' => array( 'String!', 'Funnel settings address; empty without permission.' ),
		'hasWorldMap'       => array( 'Boolean!', 'The world map file is installed.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsFunnelStepDef',
	'A funnel step as stored.',
	array(
		'label' => array( 'String!', 'Step name.' ),
		'type'  => array( 'FlxAnalyticsStepType!', 'What the step matches.' ),
		'value' => array( 'String!', 'Path or event.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsFunnelDef',
	'A funnel as stored.',
	array(
		'id'    => array( 'Int!', 'Funnel number.' ),
		'name'  => array( 'String!', 'Funnel name.' ),
		'steps' => array( '[FlxAnalyticsFunnelStepDef!]!', 'Steps in order.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsPageOption',
	'A page the funnel editor suggests.',
	array(
		'path'  => array( 'String!', 'Path.' ),
		'title' => array( 'String!', 'Title.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsKoko',
	'Koko Analytics data that can be imported.',
	array(
		'days'  => array( 'Int!', 'Days of data.' ),
		'first' => array( 'String!', 'First day.' ),
		'last'  => array( 'String!', 'Last day.' ),
	)
);
flx_gql_type(
	'FlxAnalyticsSettings',
	array(
		'description' => 'Flux Analytics settings and the data status.',
		'fields'      => array(
			'on'             => array( 'type' => 'Boolean!', 'description' => 'Visits are counted.' ),
			'method'         => array( 'type' => 'FlxAnalyticsCollectMethod!', 'description' => 'Collection method setting.' ),
			'methodNow'      => array( 'type' => 'FlxAnalyticsCollectMethod!', 'description' => 'Method in use now (fast or rest).' ),
			'dnt'            => array( 'type' => 'Boolean!', 'description' => 'Visitors who send "Do Not Track" are not counted.' ),
			'excludeRoles'   => array( 'type' => '[String!]!', 'description' => 'Roles that are not counted.' ),
			'excludeIps'     => array( 'type' => 'String!', 'description' => 'IP addresses that are not counted, one per line.' ),
			'evOutbound'     => array( 'type' => 'Boolean!', 'description' => 'Outbound link clicks are tracked.' ),
			'evDownload'     => array( 'type' => 'Boolean!', 'description' => 'File downloads are tracked.' ),
			'evContact'      => array( 'type' => 'Boolean!', 'description' => 'Phone, email and WhatsApp clicks are tracked.' ),
			'evSearch'       => array( 'type' => 'Boolean!', 'description' => 'Site searches are tracked.' ),
			'evDonation'     => array( 'type' => 'Boolean!', 'description' => 'Paid donations and orders are tracked.' ),
			'postsColumn'    => array( 'type' => 'Boolean!', 'description' => 'The post and page lists have a views column.' ),
			'adminbar'       => array( 'type' => 'Boolean!', 'description' => 'The admin bar shows the page\'s views.' ),
			'keepMonths'     => array( 'type' => 'Int!', 'description' => 'Months to keep data; 0 = forever.' ),
			'tmHub'          => array( 'type' => 'Boolean!', 'description' => 'This site receives telemetry from FluxWP installs.' ),
			'hubEndpoint'    => array( 'type' => 'String', 'description' => 'Telemetry collection address; empty when the hub code is missing.' ),
			'lastAggregated' => array(
				'type'        => 'FlxDateTime',
				'description' => 'When the visit queue was last processed; empty when never.',
				'resolve'     => static fn( $s ) => flxan_gql_time( $s, 'lastAggregated' ),
			),
			'pendingBytes'   => array( 'type' => 'Int!', 'description' => 'Bytes waiting in the visit queue.' ),
			'pendingLabel'   => array( 'type' => 'String!', 'description' => 'The same as text (12 KB).' ),
			'fastPossible'   => array( 'type' => 'Boolean!', 'description' => 'The fast endpoint can be used on this install.' ),
			'fastTest'       => array( 'type' => 'String!', 'description' => 'Result of the last fast endpoint test: ok, fail or empty.' ),
			'roles'          => array( 'type' => '[FlxAnalyticsOption!]!', 'description' => 'Roles to exclude from counting.' ),
			'funnels'        => array( 'type' => '[FlxAnalyticsFunnelDef!]!', 'description' => 'Funnels in use.' ),
			'funnelDefaults' => array( 'type' => '[FlxAnalyticsFunnelDef!]!', 'description' => 'The default funnel (for quick steps and reset).' ),
			'funnelEvents'   => array( 'type' => '[FlxAnalyticsOption!]!', 'description' => 'Events a funnel step can use.' ),
			'funnelPages'    => array( 'type' => '[FlxAnalyticsPageOption!]!', 'description' => 'Pages a funnel step can suggest.' ),
			'koko'           => array( 'type' => 'FlxAnalyticsKoko', 'description' => 'Koko Analytics data to import; empty when there is none.' ),
			'exportUrl'      => array( 'type' => 'String!', 'description' => 'CSV download address.' ),
		),
	)
);

/* ---------------------------------------------------------------------- */
/* Product telemetry                                                       */
/* ---------------------------------------------------------------------- */

flxan_gql_t(
	'FlxAnalyticsTmCount',
	'A value and the sites that have it.',
	array(
		'key'   => array( 'String!', 'Value.' ),
		'label' => array( 'String!', 'Name.' ),
		'sites' => array( 'Int!', 'Sites.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmAppUsage',
	'Daily active accounts of the mobile app.',
	array(
		'day'      => array( 'String!', 'UTC day.' ),
		'platform' => array( 'String!', 'ios or android.' ),
		'accounts' => array( 'Int!', 'Consenting accounts.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmUsage',
	'A package and how many sites use it.',
	array(
		'slug'       => array( 'String!', 'Package slug.' ),
		'name'       => array( 'String!', 'Package name.' ),
		'sites'      => array( 'Int!', 'Sites.' ),
		'topVersion' => array( 'String!', 'Most common version.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmPair',
	'Two packages used together.',
	array(
		'aName' => array( 'String!', 'First package.' ),
		'bName' => array( 'String!', 'Second package.' ),
		'sites' => array( 'Int!', 'Sites.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmCombo',
	'A set of packages.',
	array(
		'names' => array( '[String!]!', 'Package names; empty = FluxWP Panel only.' ),
		'sites' => array( 'Int!', 'Sites.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmOverview',
	'Telemetry overview.',
	array(
		'installs'   => array( 'Int!', 'Reporting sites.' ),
		'reports'    => array( 'Int!', 'Reports received.' ),
		'errorsOpen' => array( 'Int!', 'Distinct errors.' ),
		'fatalOpen'  => array( 'Int!', 'Distinct fatal errors.' ),
		'events'     => array( 'Int!', 'Error occurrences.' ),
		'appUsage'   => array( '[FlxAnalyticsTmAppUsage!]!', 'Mobile app daily active accounts.' ),
		'usage'      => array( '[FlxAnalyticsTmUsage!]!', 'Package usage (25).' ),
		'pairs'      => array( '[FlxAnalyticsTmPair!]!', 'Packages used together (15).' ),
		'combos'     => array( '[FlxAnalyticsTmCombo!]!', 'Most common package sets (10).' ),
		'php'        => array( '[FlxAnalyticsTmCount!]!', 'PHP versions.' ),
		'wp'         => array( '[FlxAnalyticsTmCount!]!', 'WordPress versions.' ),
		'locales'    => array( '[FlxAnalyticsTmCount!]!', 'Site languages (8).' ),
		'countries'  => array( '[FlxAnalyticsTmCount!]!', 'Countries (15).' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmReach',
	'A wizard step and how many runs reached it.',
	array(
		'step'  => array( 'String!', 'Step key.' ),
		'label' => array( 'String!', 'Step name.' ),
		'count' => array( 'Int!', 'Runs.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmStop',
	'Where wizard runs stopped.',
	array(
		'step'    => array( 'String!', 'Step key.' ),
		'label'   => array( 'String!', 'Step name.' ),
		'outcome' => array( 'String!', 'skip (skipped) or left (closed).' ),
		'count'   => array( 'Int!', 'Runs.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmCard',
	'A wizard card and its choices.',
	array(
		'slug'    => array( 'String!', 'Card key.' ),
		'name'    => array( 'String!', 'Card name.' ),
		'picked'  => array( 'Int!', 'Runs that picked it.' ),
		'dropped' => array( 'Int!', 'Runs that picked it and removed it.' ),
		'recOff'  => array( 'Int!', 'Runs that removed it from the suggestions.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmChoice',
	'A choice and how many runs made it.',
	array(
		'key'   => array( 'String!', 'Choice.' ),
		'count' => array( 'Int!', 'Runs.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmOnboarding',
	'Setup wizard numbers.',
	array(
		'runs'          => array( 'Int!', 'Wizard runs (latest run per install).' ),
		'done'          => array( 'Int!', 'Finished.' ),
		'skipped'       => array( 'Int!', 'Skipped.' ),
		'left'          => array( 'Int!', 'Closed before the end.' ),
		'medianSeconds' => array( 'Int!', 'Median time of the runs that finished, in seconds.' ),
		'reach'         => array( '[FlxAnalyticsTmReach!]!', 'Steps reached.' ),
		'stops'         => array( '[FlxAnalyticsTmStop!]!', 'Where runs stopped.' ),
		'cards'         => array( '[FlxAnalyticsTmCard!]!', 'Card choices.' ),
		'presets'       => array( '[FlxAnalyticsTmChoice!]!', 'Presets of finished runs.' ),
		'styles'        => array( '[FlxAnalyticsTmChoice!]!', 'Colour styles of finished runs.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmCountry',
	'A country that reported.',
	array(
		'code'  => array( 'String!', 'Lower case country code.' ),
		'name'  => array( 'String!', 'Country name.' ),
		'sites' => array( 'Int!', 'Sites.' ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTelemetry',
	'Product telemetry received from FluxWP installs.',
	array(
		'enabled'    => array( 'Boolean!', 'The hub is on.' ),
		'endpoint'   => array( 'String!', 'Address FluxWP Panel sends to.' ),
		'days'       => array( 'Int!', 'Range in days.' ),
		'country'    => array( 'String!', 'Country filter; empty = all.' ),
		'countries'  => array( '[FlxAnalyticsTmCountry!]!', 'Countries to filter by.' ),
		'overview'   => array( 'FlxAnalyticsTmOverview!', 'Overview numbers.' ),
		'onboarding' => array( 'FlxAnalyticsTmOnboarding!', 'Setup wizard numbers.' ),
	)
);
flx_gql_type(
	'FlxAnalyticsTmError',
	array(
		'description' => 'An error FluxWP installs reported.',
		'fields'      => array(
			'hash'       => array( 'type' => 'String!', 'description' => 'Record key.' ),
			'type'       => array( 'type' => 'String!', 'description' => 'PHP error type (E_WARNING …).' ),
			'fatal'      => array( 'type' => 'Boolean!', 'description' => 'The error stops the request.' ),
			'message'    => array( 'type' => 'String!', 'description' => 'First line of the message (paths, domains, emails and IPs removed).' ),
			'more'       => array( 'type' => 'String!', 'description' => 'The rest of the message.' ),
			'file'       => array( 'type' => 'String!', 'description' => 'File inside the package.' ),
			'line'       => array( 'type' => 'Int!', 'description' => 'Line; 0 when unknown.' ),
			'plugin'     => array( 'type' => 'String!', 'description' => 'Package key.' ),
			'pluginName' => array( 'type' => 'String!', 'description' => 'Package name.' ),
			'version'    => array( 'type' => 'String!', 'description' => 'Package version.' ),
			'sites'      => array( 'type' => 'Int!', 'description' => 'Different installs that saw it.' ),
			'count'      => array( 'type' => 'Int!', 'description' => 'Occurrences.' ),
			'last'       => array(
				'type'        => 'FlxDateTime!',
				'description' => 'When it last happened.',
				'resolve'     => static fn( $e ) => flxan_gql_time( $e, 'lastTs' ) ?? flx_gql_datetime( time() ),
			),
		),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmErrors',
	'Error records and the packages to filter by.',
	array(
		'items'   => array( '[FlxAnalyticsTmError!]!', 'Records, fatal first (300 at most).' ),
		'plugins' => array( '[FlxAnalyticsOption!]!', 'Packages that have errors.' ),
	)
);

/* ---------------------------------------------------------------------- */
/* Queries                                                                 */
/* ---------------------------------------------------------------------- */

$flxan_filter_arg = array( 'type' => 'FlxAnalyticsFilterInput', 'description' => 'Date range and visitor filters.' );

flx_gql_query(
	'flxAnalyticsConfig',
	array(
		'type'        => 'FlxAnalyticsConfig!',
		'description' => 'Option lists, permissions and links the statistics screens need.',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_view' ),
		'resolve'     => static fn(): array => FLXAN_Service::config(),
	)
);
flx_gql_query(
	'flxAnalyticsReport',
	array(
		'type'        => 'FlxAnalyticsReport!',
		'description' => 'Everything the Statistics screen shows for a date range.',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_view' ),
		'args'        => array( 'filter' => $flxan_filter_arg ),
		'resolve'     => static function ( $root, array $args ): array {
			/** @var array<string,mixed> $args */
			flxan_gql_tag();
			return FLXAN_Service::report( flxan_gql_filter( $args ) );
		},
	)
);
flx_gql_query(
	'flxAnalyticsRealtime',
	array(
		'type'        => 'FlxAnalyticsRealtime!',
		'description' => 'Who is on the site now; with kpi the headline numbers and chart too (a light query to repeat every few seconds).',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_view' ),
		'args'        => array(
			'filter'  => $flxan_filter_arg,
			'minutes' => array( 'type' => 'Int', 'description' => 'Window in minutes, 1–60 (default 30).', 'default' => 30 ),
			'kpi'     => array( 'type' => 'Boolean', 'description' => 'Include the headline numbers and chart.', 'default' => false ),
		),
		'resolve'     => static function ( $root, array $args ): array {
			/** @var array<string,mixed> $args */
			return FLXAN_Service::realtime( flxan_gql_filter( $args ), max( 1, min( 60, FLXAN_Service::int( $args['minutes'] ?? 30 ) ) ), ! empty( $args['kpi'] ) );
		},
	)
);
flx_gql_query(
	'flxAnalyticsPages',
	array(
		'type'        => 'FlxAnalyticsPageRowConnection!',
		'description' => 'Pages by views, a page at a time.',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_view' ),
		'args'        => flx_gql_connection_args(
			array(
				'filter'  => $flxan_filter_arg,
				'q'       => array( 'type' => 'String', 'description' => 'Text in the address.' ),
				'only404' => array( 'type' => 'Boolean', 'description' => 'Only addresses that were not found.', 'default' => false ),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
			/** @var array<string,mixed> $args */
			flxan_gql_tag();
			return flx_gql_paginate(
				$args,
				static fn( int $offset, int $limit ): array => FLXAN_Service::pages( flxan_gql_filter( $args ), FLXAN_Service::str( $args['q'] ?? '' ), ! empty( $args['only404'] ), $offset, $limit )
			);
		},
	)
);
flx_gql_query(
	'flxAnalyticsReferrers',
	array(
		'type'        => 'FlxAnalyticsReferrerRowConnection!',
		'description' => 'Referring sites by visitors, a page at a time.',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_view' ),
		'args'        => flx_gql_connection_args(
			array(
				'filter' => $flxan_filter_arg,
				'q'      => array( 'type' => 'String', 'description' => 'Text in the domain name.' ),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
			/** @var array<string,mixed> $args */
			flxan_gql_tag();
			return flx_gql_paginate(
				$args,
				static fn( int $offset, int $limit ): array => FLXAN_Service::referrers( flxan_gql_filter( $args ), FLXAN_Service::str( $args['q'] ?? '' ), $offset, $limit )
			);
		},
	)
);
flx_gql_query(
	'flxAnalyticsBreakdown',
	array(
		'type'        => 'FlxAnalyticsDimRowConnection!',
		'description' => 'Traffic split by channel, device, browser, country or campaign, a page at a time.',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_view' ),
		'args'        => flx_gql_connection_args(
			array(
				'filter' => $flxan_filter_arg,
				'kind'   => array( 'type' => 'FlxAnalyticsDimKind!', 'description' => 'What to split by.' ),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
			/** @var array<string,mixed> $args */
			flxan_gql_tag();
			return flx_gql_paginate(
				$args,
				static fn( int $offset, int $limit ): array => FLXAN_Service::breakdown( flxan_gql_filter( $args ), FLXAN_Service::str( $args['kind'] ?? 'channel', 'channel' ), $offset, $limit )
			);
		},
	)
);
flx_gql_query(
	'flxAnalyticsEvents',
	array(
		'type'        => 'FlxAnalyticsEventRowConnection!',
		'description' => 'Events (clicks, downloads, donations …), a page at a time.',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_view' ),
		'args'        => flx_gql_connection_args(
			array(
				'filter' => $flxan_filter_arg,
				'name'   => array( 'type' => 'String', 'description' => 'Only this event; empty = all.' ),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
			/** @var array<string,mixed> $args */
			flxan_gql_tag();
			return flx_gql_paginate(
				$args,
				static fn( int $offset, int $limit ): array => FLXAN_Service::events( flxan_gql_filter( $args ), FLXAN_Service::str( $args['name'] ?? '' ), $offset, $limit )
			);
		},
	)
);
flx_gql_query(
	'flxAnalyticsPage',
	array(
		'type'        => 'FlxAnalyticsPageStats!',
		'description' => 'Views of one page or post.',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_view' ),
		'args'        => array(
			'filter' => $flxan_filter_arg,
			'target' => array( 'type' => 'String!', 'description' => 'Post ID, path or address.' ),
		),
		'resolve'     => static function ( $root, array $args ): array {
			/** @var array<string,mixed> $args */
			flxan_gql_tag();
			return FLXAN_Service::page_stats( flxan_gql_filter( $args ), FLXAN_Service::str( $args['target'] ?? '' ) );
		},
	)
);
flx_gql_query(
	'flxAnalyticsFunnel',
	array(
		'type'        => 'FlxAnalyticsFunnel!',
		'description' => 'A funnel, optionally split by channel, site, code, role or country.',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_view' ),
		'args'        => array(
			'filter' => $flxan_filter_arg,
			'id'     => array( 'type' => 'Int', 'description' => 'Funnel number; 0 or none = the first.', 'default' => 0 ),
			'by'     => array( 'type' => 'FlxAnalyticsFunnelBy', 'description' => 'How to split it (default total).', 'default' => 'total' ),
		),
		'resolve'     => static function ( $root, array $args ): array {
			/** @var array<string,mixed> $args */
			flxan_gql_tag();
			$by = FLXAN_Service::str( $args['by'] ?? 'total', 'total' );
			return FLXAN_Service::funnel( flxan_gql_filter( $args ), FLXAN_Service::int( $args['id'] ?? 0 ), 'total' === $by ? '' : $by );
		},
	)
);
flx_gql_query(
	'flxAnalyticsRefs',
	array(
		'type'        => '[FlxAnalyticsRef!]!',
		'description' => 'Referral links with what their visitors did (50 at most).',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_view' ),
		'args'        => array( 'filter' => $flxan_filter_arg ),
		'resolve'     => static function ( $root, array $args ): array {
			/** @var array<string,mixed> $args */
			flxan_gql_tag();
			return FLXAN_Service::refs( flxan_gql_filter( $args ), 50 );
		},
	)
);
flx_gql_query(
	'flxAnalyticsJourneys',
	array(
		'type'        => 'FlxAnalyticsJourneys!',
		'description' => 'How visitors move through the site: flow chart and frequent paths.',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_view' ),
		'args'        => array(
			'filter'  => $flxan_filter_arg,
			'options' => array( 'type' => 'FlxAnalyticsJourneysInput', 'description' => 'Steps, filters, goal and grouping.' ),
		),
		'resolve'     => static function ( $root, array $args ): array {
			/** @var array<string,mixed> $args */
			flxan_gql_tag();
			$o   = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array();
			$out = array(
				'steps' => FLXAN_Service::int( $o['steps'] ?? 4 ),
				'from'  => FLXAN_Service::str( $o['from'] ?? '' ),
				'to'    => FLXAN_Service::str( $o['to'] ?? '' ),
				'goal'  => array_key_exists( 'goal', $o ) && null !== $o['goal'] ? FLXAN_Service::str( $o['goal'] ) : null,
				'group' => ! empty( $o['group'] ),
				'top'   => FLXAN_Service::int( $o['top'] ?? 15 ),
			);
			return FLXAN_Service::journeys( flxan_gql_filter( $args ), $out );
		},
	)
);
flx_gql_query(
	'flxAnalyticsSettings',
	array(
		'type'        => 'FlxAnalyticsSettings!',
		'description' => 'Flux Analytics settings and the data status.',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_manage' ),
		'resolve'     => static function (): array {
			flxan_gql_tag();
			return FLXAN_Service::settings();
		},
	)
);
flx_gql_query(
	'flxAnalyticsTelemetry',
	array(
		'type'        => 'FlxAnalyticsTelemetry!',
		'description' => 'Anonymous product telemetry from FluxWP installs: usage, versions and the setup wizard.',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_hub' ),
		'args'        => array(
			'days'    => array( 'type' => 'Int', 'description' => 'Range in days: 7, 30 (default) or 90.', 'default' => 30 ),
			'country' => array( 'type' => 'String', 'description' => 'Only installs of this country (two-letter code).' ),
		),
		'resolve'     => static fn( $root, array $args ): array => FLXAN_Telemetry_Service::summary( FLXAN_Service::int( $args['days'] ?? 30 ), FLXAN_Service::str( $args['country'] ?? '' ) ),
	)
);
flx_gql_query(
	'flxAnalyticsTelemetryErrors',
	array(
		'type'        => 'FlxAnalyticsTmErrors!',
		'description' => 'Errors FluxWP installs reported, fatal first.',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_hub' ),
		'args'        => array(
			'days'    => array( 'type' => 'Int', 'description' => 'Range in days: 7, 30 (default) or 90.', 'default' => 30 ),
			'country' => array( 'type' => 'String', 'description' => 'Only errors seen on installs of this country.' ),
			'plugin'  => array( 'type' => 'String', 'description' => 'Only this package.' ),
			'type'    => array( 'type' => 'String', 'description' => 'fatal, or one PHP error type such as E_WARNING.' ),
		),
		'resolve'     => static fn( $root, array $args ): array => FLXAN_Telemetry_Service::errors(
			FLXAN_Service::int( $args['days'] ?? 30 ),
			FLXAN_Service::str( $args['country'] ?? '' ),
			FLXAN_Service::str( $args['plugin'] ?? '' ),
			FLXAN_Service::str( $args['type'] ?? '' )
		),
	)
);

/* ---------------------------------------------------------------------- */
/* Mutations                                                               */
/* ---------------------------------------------------------------------- */

flx_gql_mutation(
	'flxAnalyticsRefSave',
	array(
		'description' => 'Adds a referral link name, renames it, or removes the name (the statistics stay).',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_refs' ),
		'input'       => array(
			'code'   => array( 'type' => 'String!', 'description' => 'Referral code (letters, digits, - and _).' ),
			'name'   => array( 'type' => 'String', 'description' => 'Name shown in reports.' ),
			'delete' => array( 'type' => 'Boolean', 'description' => 'Remove the name.', 'default' => false ),
		),
		'output'      => array(
			'ref' => array( 'type' => 'FlxAnalyticsRefSaved', 'description' => 'The saved code and its link.' ),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			$ref = FLXAN_Service::ref_save( FLXAN_Service::str( $input['code'] ?? '' ), FLXAN_Service::str( $input['name'] ?? '' ), ! empty( $input['delete'] ) );
			if ( null === $ref ) {
				throw flx_gql_error( 'INVALID_INPUT', 'Invalid code. Use letters, digits, - and _.', 'code' );
			}
			return array( 'ref' => $ref );
		},
	)
);
flx_gql_mutation(
	'flxAnalyticsSettingsSave',
	array(
		'description' => 'Changes settings and funnels. Fields you do not send stay as they are.',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_manage' ),
		'input'       => array(
			'on'           => array( 'type' => 'Boolean', 'description' => 'Count visits.' ),
			'method'       => array( 'type' => 'FlxAnalyticsCollectMethod', 'description' => 'Collection method.' ),
			'dnt'          => array( 'type' => 'Boolean', 'description' => 'Do not count visitors who send "Do Not Track".' ),
			'excludeRoles' => array( 'type' => '[String!]', 'description' => 'Roles that are not counted.' ),
			'excludeIps'   => array( 'type' => 'String', 'description' => 'IP addresses that are not counted, one per line (* and CIDR work).' ),
			'evOutbound'   => array( 'type' => 'Boolean', 'description' => 'Track outbound link clicks.' ),
			'evDownload'   => array( 'type' => 'Boolean', 'description' => 'Track file downloads.' ),
			'evContact'    => array( 'type' => 'Boolean', 'description' => 'Track phone, email and WhatsApp clicks.' ),
			'evSearch'     => array( 'type' => 'Boolean', 'description' => 'Track site searches.' ),
			'evDonation'   => array( 'type' => 'Boolean', 'description' => 'Track paid donations and orders.' ),
			'postsColumn'  => array( 'type' => 'Boolean', 'description' => 'Show a views column in the post and page lists.' ),
			'adminbar'     => array( 'type' => 'Boolean', 'description' => 'Show the page\'s views in the admin bar.' ),
			'keepMonths'   => array( 'type' => 'Int', 'description' => 'Months to keep data (0–120); 0 = forever.' ),
			'tmHub'        => array( 'type' => 'Boolean', 'description' => 'Receive telemetry from FluxWP installs.' ),
			'funnels'      => array( 'type' => '[FlxAnalyticsFunnelInput!]', 'description' => 'Replaces the funnels (5 at most); an empty list or the default set goes back to the defaults.' ),
		),
		'output'      => array(
			'settings' => array( 'type' => 'FlxAnalyticsSettings', 'description' => 'Settings after the change.' ),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			return array( 'settings' => FLXAN_Service::save_settings( $input ) );
		},
	)
);
flx_gql_mutation(
	'flxAnalyticsAggregate',
	array(
		'description' => 'Processes the waiting visits now.',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_manage' ),
		'output'      => array(
			'processed' => array( 'type' => 'Int!', 'description' => 'Visit rows processed.' ),
			'settings'  => array( 'type' => 'FlxAnalyticsSettings', 'description' => 'Settings and data status after the run.' ),
		),
		'resolve'     => static function (): array {
			$n = FLXAN_Service::aggregate();
			return array( 'processed' => $n, 'settings' => FLXAN_Service::settings() );
		},
	)
);
flx_gql_mutation(
	'flxAnalyticsReset',
	array(
		'description' => 'Deletes all statistics permanently.',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_manage' ),
		'output'      => array(
			'settings' => array( 'type' => 'FlxAnalyticsSettings', 'description' => 'Settings and data status after the reset.' ),
		),
		'resolve'     => static function (): array {
			FLXAN_Service::reset();
			return array( 'settings' => FLXAN_Service::settings() );
		},
	)
);
flx_gql_mutation(
	'flxAnalyticsKokoImport',
	array(
		'description' => 'Imports Koko Analytics history from before Flux Analytics was installed.',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_manage' ),
		'output'      => array(
			'rows'     => array( 'type' => 'Int!', 'description' => 'Rows imported.' ),
			'settings' => array( 'type' => 'FlxAnalyticsSettings', 'description' => 'Settings and data status after the import.' ),
		),
		'resolve'     => static function (): array {
			$n = FLXAN_Service::koko_import();
			return array( 'rows' => $n, 'settings' => FLXAN_Service::settings() );
		},
	)
);
flx_gql_mutation(
	'flxAnalyticsFastTest',
	array(
		'description' => 'Records whether this browser reached the fast endpoint.',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_manage' ),
		'input'       => array(
			'ok' => array( 'type' => 'Boolean!', 'description' => 'The endpoint answered.' ),
		),
		'output'      => array(
			'method' => array( 'type' => 'FlxAnalyticsCollectMethod!', 'description' => 'Collection method in use now.' ),
		),
		'resolve'     => static fn( array $input ): array => array( 'method' => FLXAN_Service::fast_test( ! empty( $input['ok'] ) ) ),
	)
);
flx_gql_mutation(
	'flxAnalyticsTelemetryForget',
	array(
		'description' => 'Removes one telemetry error record, or all of them with "all". A record returns when the error happens again.',
		'cap'         => Closure::fromCallable( 'flxan_gql_can_hub' ),
		'input'       => array(
			'hash' => array( 'type' => 'String!', 'description' => 'Record key, or "all".' ),
		),
		'output'      => array(
			'removed' => array( 'type' => 'Boolean', 'description' => 'The record was removed.' ),
		),
		'resolve'     => static function ( array $input ): array {
			if ( ! FLXAN_Telemetry_Service::forget( FLXAN_Service::str( $input['hash'] ?? '' ) ) ) {
				throw flx_gql_error( 'INVALID_INPUT', 'The record key is not valid.', 'hash' );
			}
			return array( 'removed' => true );
		},
	)
);
