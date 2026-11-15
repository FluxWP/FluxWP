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
	return flxan_can_view();
}

/** Referral name permission. */
function flxan_gql_can_refs(): bool {
	return flxan_can_manage_refs();
}

/** Settings and data permission. */
function flxan_gql_can_manage(): bool {
	return current_user_can( 'manage_options' );
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
		'description' => __( 'Ready-made date range of a report.', 'flx-analytics' ),
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
		'description' => __( 'How a time series is grouped.', 'flx-analytics' ),
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
		'description' => __( 'What a breakdown splits the traffic by.', 'flx-analytics' ),
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
		'description' => __( 'How a funnel is split into groups.', 'flx-analytics' ),
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
		'description' => __( 'What a funnel step matches.', 'flx-analytics' ),
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
		'description' => __( 'How visits are sent to the site.', 'flx-analytics' ),
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
		'description' => __( 'Date range and visitor filters shared by every report.', 'flx-analytics' ),
		'fields'      => array(
			'range' => array( 'type' => 'FlxAnalyticsRangePreset', 'description' => __( 'Ready-made range; default is the last 30 days.', 'flx-analytics' ) ),
			'start' => array( 'type' => 'String', 'description' => __( 'First day (YYYY-MM-DD) when the range is custom.', 'flx-analytics' ) ),
			'end'   => array( 'type' => 'String', 'description' => __( 'Last day (YYYY-MM-DD) when the range is custom.', 'flx-analytics' ) ),
			'seg'   => array( 'type' => 'String', 'description' => __( 'Visitor type: all (default), guest, members or a role name.', 'flx-analytics' ) ),
			'rc'    => array( 'type' => 'String', 'description' => __( 'Referral code filter: empty = all, * = only referral links, or a code.', 'flx-analytics' ) ),
		),
	)
);
flx_gql_input(
	'FlxAnalyticsJourneysInput',
	array(
		'description' => __( 'Options of the journeys report.', 'flx-analytics' ),
		'fields'      => array(
			'steps' => array( 'type' => 'Int', 'description' => __( 'Steps to show, 2–6 (default 4).', 'flx-analytics' ) ),
			'from'  => array( 'type' => 'String', 'description' => __( 'Only journeys that start on this path.', 'flx-analytics' ) ),
			'to'    => array( 'type' => 'String', 'description' => __( 'Only journeys that end on this path.', 'flx-analytics' ) ),
			'goal'  => array( 'type' => 'String', 'description' => __( 'Goal "funnel:step"; empty = no goal; not given = the default goal.', 'flx-analytics' ) ),
			'group' => array( 'type' => 'Boolean', 'description' => __( 'Group sub pages (/news/…) into one box.', 'flx-analytics' ) ),
			'top'   => array( 'type' => 'Int', 'description' => __( 'Paths to list, 5–100 (default 15).', 'flx-analytics' ) ),
		),
	)
);
flx_gql_input(
	'FlxAnalyticsFunnelStepInput',
	array(
		'description' => __( 'One funnel step.', 'flx-analytics' ),
		'fields'      => array(
			'label' => array( 'type' => 'String!', 'description' => __( 'Name shown in the report.', 'flx-analytics' ) ),
			'type'  => array( 'type' => 'FlxAnalyticsStepType!', 'description' => __( 'Site entry, a page path prefix or an event.', 'flx-analytics' ) ),
			'value' => array( 'type' => 'String', 'description' => __( 'Path (/donate/) or event (name or name:detail); empty for site entry.', 'flx-analytics' ) ),
		),
	)
);
flx_gql_input(
	'FlxAnalyticsFunnelInput',
	array(
		'description' => __( 'A funnel: steps visitors go through in order.', 'flx-analytics' ),
		'fields'      => array(
			'name'  => array( 'type' => 'String!', 'description' => __( 'Funnel name.', 'flx-analytics' ) ),
			'steps' => array( 'type' => '[FlxAnalyticsFunnelStepInput!]!', 'description' => __( 'Steps, 10 at most.', 'flx-analytics' ) ),
		),
	)
);

/* ---------------------------------------------------------------------- */
/* Shared report types                                                     */
/* ---------------------------------------------------------------------- */

flxan_gql_t(
	'FlxAnalyticsOption',
	__( 'A value with its label.', 'flx-analytics' ),
	array(
		'value' => array( 'String!', __( 'Value.', 'flx-analytics' ) ),
		'label' => array( 'String!', __( 'Label in the user\'s language.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsRange',
	__( 'The resolved date range and the equal period before it.', 'flx-analytics' ),
	array(
		'preset'    => array( 'FlxAnalyticsRangePreset!', __( 'Range preset.', 'flx-analytics' ) ),
		'start'     => array( 'String!', __( 'First day (YYYY-MM-DD, site time zone).', 'flx-analytics' ) ),
		'end'       => array( 'String!', __( 'Last day (YYYY-MM-DD, site time zone).', 'flx-analytics' ) ),
		'label'     => array( 'String!', __( 'Name of the range.', 'flx-analytics' ) ),
		'days'      => array( 'Int!', __( 'Days in the range.', 'flx-analytics' ) ),
		'prevStart' => array( 'String!', __( 'First day of the previous equal period.', 'flx-analytics' ) ),
		'prevEnd'   => array( 'String!', __( 'Last day of the previous equal period.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsFilterState',
	__( 'The visitor filters in effect.', 'flx-analytics' ),
	array(
		'seg' => array( 'String!', __( 'Visitor type filter.', 'flx-analytics' ) ),
		'rc'  => array( 'String!', __( 'Referral code filter.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTotals',
	__( 'Visitors and page views.', 'flx-analytics' ),
	array(
		'visitors'  => array( 'Int!', __( 'Sum of each day\'s unique visitors.', 'flx-analytics' ) ),
		'pageviews' => array( 'Int!', __( 'Page views.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsVisits',
	__( 'Visit (session) measures.', 'flx-analytics' ),
	array(
		'sessions'    => array( 'Int!', __( 'Visits.', 'flx-analytics' ) ),
		'avgDuration' => array( 'Int!', __( 'Average visit length in seconds.', 'flx-analytics' ) ),
		'bounceRate'  => array( 'Float!', __( 'Percent of visits that saw one page.', 'flx-analytics' ) ),
		'pagesPer'    => array( 'Float!', __( 'Pages per visit.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsChange',
	__( 'Percent change against the previous equal period; empty when that period had no data.', 'flx-analytics' ),
	array(
		'visitors'    => array( 'Float', __( 'Visitors change.', 'flx-analytics' ) ),
		'pageviews'   => array( 'Float', __( 'Page views change.', 'flx-analytics' ) ),
		'sessions'    => array( 'Float', __( 'Visits change.', 'flx-analytics' ) ),
		'avgDuration' => array( 'Float', __( 'Average visit length change.', 'flx-analytics' ) ),
		'bounceRate'  => array( 'Float', __( 'Bounce rate change.', 'flx-analytics' ) ),
		'pagesPer'    => array( 'Float', __( 'Pages per visit change.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsPoint',
	__( 'One bar of the visitors chart.', 'flx-analytics' ),
	array(
		'date'      => array( 'String!', __( 'First day of the bar (YYYY-MM-DD).', 'flx-analytics' ) ),
		'visitors'  => array( 'Int!', __( 'Visitors.', 'flx-analytics' ) ),
		'pageviews' => array( 'Int!', __( 'Page views.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsSeries',
	__( 'Visitors over time; missing days are zero.', 'flx-analytics' ),
	array(
		'group' => array( 'FlxAnalyticsSeriesGroup!', __( 'Bar size (day for up to 92 days, week up to 400, then month).', 'flx-analytics' ) ),
		'items' => array( '[FlxAnalyticsPoint!]!', __( 'Bars, oldest first.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsKpi',
	__( 'Headline numbers and the chart.', 'flx-analytics' ),
	array(
		'totals'     => array( 'FlxAnalyticsTotals!', __( 'Totals in the range.', 'flx-analytics' ) ),
		'prev'       => array( 'FlxAnalyticsTotals!', __( 'Totals in the previous equal period.', 'flx-analytics' ) ),
		'visits'     => array( 'FlxAnalyticsVisits!', __( 'Visit measures in the range.', 'flx-analytics' ) ),
		'visitsPrev' => array( 'FlxAnalyticsVisits!', __( 'Visit measures in the previous period.', 'flx-analytics' ) ),
		'change'     => array( 'FlxAnalyticsChange!', __( 'Percent changes.', 'flx-analytics' ) ),
		'series'     => array( 'FlxAnalyticsSeries!', __( 'Chart data.', 'flx-analytics' ) ),
	)
);

flxan_gql_t(
	'FlxAnalyticsPageRow',
	__( 'A page and its views.', 'flx-analytics' ),
	array(
		'path'      => array( 'String!', __( 'Address path.', 'flx-analytics' ) ),
		'url'       => array( 'String!', __( 'Full address.', 'flx-analytics' ) ),
		'postId'    => array( 'Int!', __( 'Post ID; 0 when the address is not a post.', 'flx-analytics' ) ),
		'title'     => array( 'String!', __( 'Page title (the path when the page has none).', 'flx-analytics' ) ),
		'is404'     => array( 'Boolean!', __( 'The address was not found.', 'flx-analytics' ) ),
		'visitors'  => array( 'Int!', __( 'Visitors.', 'flx-analytics' ) ),
		'pageviews' => array( 'Int!', __( 'Page views.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsPageList',
	__( 'The first pages of a list and how many there are.', 'flx-analytics' ),
	array(
		'total' => array( 'Int!', __( 'All matching pages.', 'flx-analytics' ) ),
		'items' => array( '[FlxAnalyticsPageRow!]!', __( 'The first pages.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsReferrerRow',
	__( 'A site that sent visitors.', 'flx-analytics' ),
	array(
		'host'         => array( 'String!', __( 'Domain name.', 'flx-analytics' ) ),
		'channel'      => array( 'String!', __( 'Channel key (search, social, ai, referral …).', 'flx-analytics' ) ),
		'channelLabel' => array( 'String!', __( 'Channel name in the user\'s language.', 'flx-analytics' ) ),
		'visitors'     => array( 'Int!', __( 'Visitors.', 'flx-analytics' ) ),
		'pageviews'    => array( 'Int!', __( 'Page views.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsReferrerList',
	__( 'The first referring sites of a list and how many there are.', 'flx-analytics' ),
	array(
		'total' => array( 'Int!', __( 'All matching sites.', 'flx-analytics' ) ),
		'items' => array( '[FlxAnalyticsReferrerRow!]!', __( 'The first sites.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsDimRow',
	__( 'One value of a breakdown (a browser, a country …).', 'flx-analytics' ),
	array(
		'value'     => array( 'String!', __( 'Stored value.', 'flx-analytics' ) ),
		'label'     => array( 'String!', __( 'Name in the user\'s language.', 'flx-analytics' ) ),
		'visitors'  => array( 'Int!', __( 'Visitors.', 'flx-analytics' ) ),
		'pageviews' => array( 'Int!', __( 'Page views.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsDimList',
	__( 'The top values of one breakdown.', 'flx-analytics' ),
	array(
		'kind'  => array( 'FlxAnalyticsDimKind!', __( 'What the traffic is split by.', 'flx-analytics' ) ),
		'total' => array( 'Int!', __( 'All distinct values.', 'flx-analytics' ) ),
		'items' => array( '[FlxAnalyticsDimRow!]!', __( 'The top values.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsEventSum',
	__( 'Total of one event type.', 'flx-analytics' ),
	array(
		'name'    => array( 'String!', __( 'Event name.', 'flx-analytics' ) ),
		'label'   => array( 'String!', __( 'Event name in the user\'s language.', 'flx-analytics' ) ),
		'hits'    => array( 'Int!', __( 'Times it happened.', 'flx-analytics' ) ),
		'uniques' => array( 'Int!', __( 'Distinct visitors.', 'flx-analytics' ) ),
		'amount'  => array( 'Float!', __( 'Total amount (donations and orders).', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsEventRow',
	__( 'An event and its detail (the link, the file, the search term …).', 'flx-analytics' ),
	array(
		'name'    => array( 'String!', __( 'Event name.', 'flx-analytics' ) ),
		'label'   => array( 'String!', __( 'Event name in the user\'s language.', 'flx-analytics' ) ),
		'param'   => array( 'String!', __( 'Detail; empty when the event has none.', 'flx-analytics' ) ),
		'hits'    => array( 'Int!', __( 'Times it happened.', 'flx-analytics' ) ),
		'uniques' => array( 'Int!', __( 'Distinct visitors.', 'flx-analytics' ) ),
		'amount'  => array( 'Float!', __( 'Total amount.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsEventList',
	__( 'The first events of a list and how many there are.', 'flx-analytics' ),
	array(
		'total' => array( 'Int!', __( 'All matching events.', 'flx-analytics' ) ),
		'items' => array( '[FlxAnalyticsEventRow!]!', __( 'The first events.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsSegment',
	__( 'Traffic of one visitor type.', 'flx-analytics' ),
	array(
		'value'     => array( 'String!', __( 'Role name; empty for outside visitors.', 'flx-analytics' ) ),
		'label'     => array( 'String!', __( 'Name in the user\'s language.', 'flx-analytics' ) ),
		'visitors'  => array( 'Int!', __( 'Visitors.', 'flx-analytics' ) ),
		'pageviews' => array( 'Int!', __( 'Page views.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsRef',
	__( 'A referral link (?r=code) and what its visitors did.', 'flx-analytics' ),
	array(
		'code'         => array( 'String!', __( 'Referral code.', 'flx-analytics' ) ),
		'name'         => array( 'String!', __( 'Name given to the code; empty when none.', 'flx-analytics' ) ),
		'label'        => array( 'String!', __( 'Name, or the code when there is no name.', 'flx-analytics' ) ),
		'url'          => array( 'String!', __( 'The link to share.', 'flx-analytics' ) ),
		'visitors'     => array( 'Int!', __( 'Visitors.', 'flx-analytics' ) ),
		'pageviews'    => array( 'Int!', __( 'Page views.', 'flx-analytics' ) ),
		'sessions'     => array( 'Int!', __( 'Visits.', 'flx-analytics' ) ),
		'pagesPer'     => array( 'Float!', __( 'Pages per visit.', 'flx-analytics' ) ),
		'avgDuration'  => array( 'Int!', __( 'Average visit length in seconds.', 'flx-analytics' ) ),
		'donations'    => array( 'Int!', __( 'Paid donations.', 'flx-analytics' ) ),
		'amount'       => array( 'Float!', __( 'Donated amount.', 'flx-analytics' ) ),
		'deepest'      => array( 'Int!', __( 'Furthest first-funnel step reached (0 = none).', 'flx-analytics' ) ),
		'deepestLabel' => array( 'String!', __( 'Name of that step.', 'flx-analytics' ) ),
		'completed'    => array( 'Int!', __( 'Visits that completed the first funnel.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsRefSaved',
	__( 'A saved referral code.', 'flx-analytics' ),
	array(
		'code' => array( 'String!', __( 'Referral code.', 'flx-analytics' ) ),
		'name' => array( 'String!', __( 'Name; empty when removed.', 'flx-analytics' ) ),
		'url'  => array( 'String!', __( 'The link to share.', 'flx-analytics' ) ),
	)
);

flxan_gql_t(
	'FlxAnalyticsFunnelStep',
	__( 'One funnel step with the visits that reached it.', 'flx-analytics' ),
	array(
		'label' => array( 'String!', __( 'Step name.', 'flx-analytics' ) ),
		'type'  => array( 'FlxAnalyticsStepType!', __( 'What the step matches.', 'flx-analytics' ) ),
		'value' => array( 'String!', __( 'Path or event of the step.', 'flx-analytics' ) ),
		'count' => array( 'Int!', __( 'Visits that reached this step or went further.', 'flx-analytics' ) ),
		'rate'  => array( 'Float!', __( 'Percent of the first step.', 'flx-analytics' ) ),
		'drop'  => array( 'Float!', __( 'Percent lost since the previous step.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsFunnelGroup',
	__( 'A group (channel, site, code …) with its funnel counts.', 'flx-analytics' ),
	array(
		'value'  => array( 'String!', __( 'Group key.', 'flx-analytics' ) ),
		'label'  => array( 'String!', __( 'Group name.', 'flx-analytics' ) ),
		'counts' => array( '[Int!]!', __( 'Visits per step.', 'flx-analytics' ) ),
		'rate'   => array( 'Float!', __( 'Percent that completed the funnel.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsFunnelName',
	__( 'A defined funnel.', 'flx-analytics' ),
	array(
		'id'   => array( 'Int!', __( 'Funnel number.', 'flx-analytics' ) ),
		'name' => array( 'String!', __( 'Funnel name.', 'flx-analytics' ) ),
	)
);
flx_gql_type(
	'FlxAnalyticsFunnel',
	array(
		'description' => __( 'A funnel report.', 'flx-analytics' ),
		'fields'      => array(
			'found'   => array( 'type' => 'Boolean!', 'description' => __( 'A funnel is defined.', 'flx-analytics' ) ),
			'id'      => array( 'type' => 'Int', 'description' => __( 'Funnel number; empty when none is defined.', 'flx-analytics' ) ),
			'name'    => array( 'type' => 'String', 'description' => __( 'Funnel name; empty when none is defined.', 'flx-analytics' ) ),
			'by'      => array(
				'type'        => 'FlxAnalyticsFunnelBy!',
				'description' => __( 'How the groups were formed.', 'flx-analytics' ),
				'resolve'     => static fn( $f ): string => is_array( $f ) && '' !== FLXAN_Service::str( $f['by'] ?? '' ) ? FLXAN_Service::str( $f['by'] ) : 'total',
			),
			'steps'   => array( 'type' => '[FlxAnalyticsFunnelStep!]!', 'description' => __( 'Steps.', 'flx-analytics' ) ),
			'groups'  => array( 'type' => '[FlxAnalyticsFunnelGroup!]!', 'description' => __( 'Groups (up to 8) when the funnel is split.', 'flx-analytics' ) ),
			'funnels' => array( 'type' => '[FlxAnalyticsFunnelName!]!', 'description' => __( 'Every defined funnel.', 'flx-analytics' ) ),
		),
	)
);

flxan_gql_t(
	'FlxAnalyticsLivePage',
	__( 'A page people are on now.', 'flx-analytics' ),
	array(
		'path'      => array( 'String!', __( 'Address path.', 'flx-analytics' ) ),
		'title'     => array( 'String!', __( 'Page title.', 'flx-analytics' ) ),
		'pageviews' => array( 'Int!', __( 'Page views in the window.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsLiveReferrer',
	__( 'A site people came from just now.', 'flx-analytics' ),
	array(
		'host'      => array( 'String!', __( 'Domain name.', 'flx-analytics' ) ),
		'pageviews' => array( 'Int!', __( 'Page views in the window.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsLiveCountry',
	__( 'A country people are visiting from now.', 'flx-analytics' ),
	array(
		'value'    => array( 'String!', __( 'Country code.', 'flx-analytics' ) ),
		'label'    => array( 'String!', __( 'Country name in the user\'s language.', 'flx-analytics' ) ),
		'visitors' => array( 'Int!', __( 'Visitors in the window.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsLiveCode',
	__( 'A referral code of the people on the site now.', 'flx-analytics' ),
	array(
		'code'     => array( 'String!', __( 'Referral code.', 'flx-analytics' ) ),
		'label'    => array( 'String!', __( 'Name, or the code.', 'flx-analytics' ) ),
		'visitors' => array( 'Int!', __( 'Visitors in the window.', 'flx-analytics' ) ),
	)
);
flx_gql_type(
	'FlxAnalyticsRealtime',
	array(
		'description' => __( 'Who is on the site now.', 'flx-analytics' ),
		'fields'      => array(
			'minutes'   => array( 'type' => 'Int!', 'description' => __( 'Window in minutes.', 'flx-analytics' ) ),
			'active'    => array( 'type' => 'Int!', 'description' => __( 'People on the site in the last 5 minutes.', 'flx-analytics' ) ),
			'visitors'  => array( 'type' => 'Int!', 'description' => __( 'Visitors in the window.', 'flx-analytics' ) ),
			'pageviews' => array( 'type' => 'Int!', 'description' => __( 'Page views in the window.', 'flx-analytics' ) ),
			'perMinute' => array( 'type' => '[Int!]!', 'description' => __( 'Page views per minute, oldest first.', 'flx-analytics' ) ),
			'pages'     => array( 'type' => '[FlxAnalyticsLivePage!]!', 'description' => __( 'Busiest pages (5).', 'flx-analytics' ) ),
			'referrers' => array( 'type' => '[FlxAnalyticsLiveReferrer!]!', 'description' => __( 'Busiest referring sites (5).', 'flx-analytics' ) ),
			'countries' => array( 'type' => '[FlxAnalyticsLiveCountry!]!', 'description' => __( 'Countries (10).', 'flx-analytics' ) ),
			'codes'     => array( 'type' => '[FlxAnalyticsLiveCode!]!', 'description' => __( 'Referral codes (5).', 'flx-analytics' ) ),
			'updated'   => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'When the visit queue was last processed; empty when never.', 'flx-analytics' ),
				'resolve'     => static fn( $r ) => flxan_gql_time( $r, 'updated' ),
			),
			'kpi'       => array( 'type' => 'FlxAnalyticsKpi', 'description' => __( 'Headline numbers and chart, when asked for.', 'flx-analytics' ) ),
		),
	)
);

flxan_gql_t(
	'FlxAnalyticsReport',
	__( 'Everything the Statistics screen shows.', 'flx-analytics' ),
	array(
		'range'        => array( 'FlxAnalyticsRange!', __( 'The date range.', 'flx-analytics' ) ),
		'filter'       => array( 'FlxAnalyticsFilterState!', __( 'Visitor filters in effect.', 'flx-analytics' ) ),
		'kpi'          => array( 'FlxAnalyticsKpi!', __( 'Headline numbers and the chart.', 'flx-analytics' ) ),
		'pages'        => array( 'FlxAnalyticsPageList!', __( 'Most viewed pages (10).', 'flx-analytics' ) ),
		'notFound'     => array( 'FlxAnalyticsPageList!', __( 'Addresses that were not found (5).', 'flx-analytics' ) ),
		'referrers'    => array( 'FlxAnalyticsReferrerList!', __( 'Referring sites (10).', 'flx-analytics' ) ),
		'dims'         => array( '[FlxAnalyticsDimList!]!', __( 'Breakdowns: channel, device, browser, system, language, country and campaign values.', 'flx-analytics' ) ),
		'segments'     => array( '[FlxAnalyticsSegment!]!', __( 'Traffic by visitor type.', 'flx-analytics' ) ),
		'refs'         => array( '[FlxAnalyticsRef!]!', __( 'Referral links (20).', 'flx-analytics' ) ),
		'funnel'       => array( 'FlxAnalyticsFunnel!', __( 'The first funnel, split by channel.', 'flx-analytics' ) ),
		'eventSummary' => array( '[FlxAnalyticsEventSum!]!', __( 'Event totals.', 'flx-analytics' ) ),
		'events'       => array( 'FlxAnalyticsEventList!', __( 'Event details (10).', 'flx-analytics' ) ),
		'realtime'     => array( 'FlxAnalyticsRealtime!', __( 'Who is on the site now (30 minutes).', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsPageStats',
	__( 'One page or post.', 'flx-analytics' ),
	array(
		'found'     => array( 'Boolean!', __( 'The page has any recorded views.', 'flx-analytics' ) ),
		'paths'     => array( '[String!]!', __( 'Address paths that count for this page.', 'flx-analytics' ) ),
		'visitors'  => array( 'Int!', __( 'Visitors in the range.', 'flx-analytics' ) ),
		'pageviews' => array( 'Int!', __( 'Page views in the range.', 'flx-analytics' ) ),
		'series'    => array( '[FlxAnalyticsPoint!]!', __( 'Days with views; days without are left out.', 'flx-analytics' ) ),
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
	__( 'Options the journeys report used.', 'flx-analytics' ),
	array(
		'steps' => array( 'Int!', __( 'Steps shown.', 'flx-analytics' ) ),
		'from'  => array( 'String!', __( 'Start path filter; empty = all.', 'flx-analytics' ) ),
		'to'    => array( 'String!', __( 'End path filter; empty = all.', 'flx-analytics' ) ),
		'goal'  => array( 'String!', __( 'Goal "funnel:step"; empty = none.', 'flx-analytics' ) ),
		'group' => array( 'Boolean!', __( 'Sub pages are grouped.', 'flx-analytics' ) ),
		'top'   => array( 'Int!', __( 'Paths listed.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsJourneyNode',
	__( 'A page with its visit count.', 'flx-analytics' ),
	array(
		'key'   => array( 'String!', __( 'Path key.', 'flx-analytics' ) ),
		'label' => array( 'String!', __( 'Page title.', 'flx-analytics' ) ),
		'count' => array( 'Int!', __( 'Visits.', 'flx-analytics' ) ),
		'pct'   => array( 'Float!', __( 'Percent of multi-page visits.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsJourneySummary',
	__( 'Headline numbers of the journeys report.', 'flx-analytics' ),
	array(
		'sessions'     => array( 'Int!', __( 'All visits.', 'flx-analytics' ) ),
		'multi'        => array( 'Int!', __( 'Visits with at least two pages.', 'flx-analytics' ) ),
		'multiPct'     => array( 'Float!', __( 'Percent of all visits.', 'flx-analytics' ) ),
		'multiChange'  => array( 'Float', __( 'Percent change of multi-page visits.', 'flx-analytics' ) ),
		'avgLen'       => array( 'Float!', __( 'Average journey length in pages.', 'flx-analytics' ) ),
		'avgLenChange' => array( 'Float', __( 'Percent change of the average length.', 'flx-analytics' ) ),
		'topEntry'     => array( 'FlxAnalyticsJourneyNode', __( 'Most common first page; empty without data.', 'flx-analytics' ) ),
		'topExit'      => array( 'FlxAnalyticsJourneyNode', __( 'Most common last page after two or more pages; empty without data.', 'flx-analytics' ) ),
		'goalRate'     => array( 'Float', __( 'Percent that reached the goal; empty when no goal is chosen.', 'flx-analytics' ) ),
		'goalReached'  => array( 'Int!', __( 'Visits that reached the goal.', 'flx-analytics' ) ),
		'goalChange'   => array( 'Float', __( 'Percent change of the goal rate.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsFlowNode',
	__( 'A box of the flow chart.', 'flx-analytics' ),
	array(
		'key'   => array( 'String!', __( 'Path key; ~ is "other pages".', 'flx-analytics' ) ),
		'label' => array( 'String!', __( 'Page title.', 'flx-analytics' ) ),
		'path'  => array( 'String!', __( 'Path; empty for events and other pages.', 'flx-analytics' ) ),
		'ev'    => array( 'Boolean!', __( 'The step is an event (add to cart …), not a page.', 'flx-analytics' ) ),
		'count' => array( 'Int!', __( 'Visits through this box.', 'flx-analytics' ) ),
		'ends'  => array( 'Int!', __( 'Visits that ended here.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsFlowColumn',
	__( 'One step of the flow chart.', 'flx-analytics' ),
	array(
		'nodes' => array( '[FlxAnalyticsFlowNode!]!', __( 'Boxes, busiest first.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsFlowLink',
	__( 'A band between two boxes.', 'flx-analytics' ),
	array(
		'step'  => array( 'Int!', __( 'Column the band starts in (0 = first).', 'flx-analytics' ) ),
		'from'  => array( 'String!', __( 'Key of the box it starts in.', 'flx-analytics' ) ),
		'to'    => array( 'String!', __( 'Key of the box it ends in.', 'flx-analytics' ) ),
		'count' => array( 'Int!', __( 'Visits.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsFlow',
	__( 'The flow chart.', 'flx-analytics' ),
	array(
		'columns' => array( '[FlxAnalyticsFlowColumn!]!', __( 'Steps from the entry page.', 'flx-analytics' ) ),
		'links'   => array( '[FlxAnalyticsFlowLink!]!', __( 'Bands between steps.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsJourneyStep',
	__( 'A step of a path.', 'flx-analytics' ),
	array(
		'key'   => array( 'String!', __( 'Path key.', 'flx-analytics' ) ),
		'label' => array( 'String!', __( 'Page title or event name.', 'flx-analytics' ) ),
		'path'  => array( 'String!', __( 'Path; empty for events.', 'flx-analytics' ) ),
		'ev'    => array( 'Boolean!', __( 'The step is an event.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsJourneyPath',
	__( 'A frequent path.', 'flx-analytics' ),
	array(
		'steps'    => array( '[FlxAnalyticsJourneyStep!]!', __( 'Steps in order.', 'flx-analytics' ) ),
		'sessions' => array( 'Int!', __( 'Visits that took this path.', 'flx-analytics' ) ),
		'reached'  => array( 'Int!', __( 'Of them, visits that reached the goal.', 'flx-analytics' ) ),
		'share'    => array( 'Float!', __( 'Percent of multi-page visits.', 'flx-analytics' ) ),
		'prev'     => array( 'Int!', __( 'Visits in the previous equal period.', 'flx-analytics' ) ),
		'change'   => array( 'Float', __( 'Percent change; empty when the path is new.', 'flx-analytics' ) ),
		'rate'     => array( 'Float!', __( 'Percent that reached the goal.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsJourneyPaths',
	__( 'The most frequent paths.', 'flx-analytics' ),
	array(
		'top'   => array( 'Int!', __( 'Paths asked for.', 'flx-analytics' ) ),
		'total' => array( 'Int!', __( 'Distinct paths.', 'flx-analytics' ) ),
		'items' => array( '[FlxAnalyticsJourneyPath!]!', __( 'Paths (up to 200; the screen filters them).', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsJourneys',
	__( 'The journeys report.', 'flx-analytics' ),
	array(
		'range'   => array( 'FlxAnalyticsRange!', __( 'The date range.', 'flx-analytics' ) ),
		'options' => array( 'FlxAnalyticsJourneyOptions!', __( 'Options in effect.', 'flx-analytics' ) ),
		'goals'   => array( '[FlxAnalyticsOption!]!', __( 'Goals to choose from (funnel steps).', 'flx-analytics' ) ),
		'summary' => array( 'FlxAnalyticsJourneySummary!', __( 'Headline numbers.', 'flx-analytics' ) ),
		'flow'    => array( 'FlxAnalyticsFlow!', __( 'The flow chart.', 'flx-analytics' ) ),
		'paths'   => array( 'FlxAnalyticsJourneyPaths!', __( 'Frequent paths.', 'flx-analytics' ) ),
	)
);

/* ---------------------------------------------------------------------- */
/* Config and settings                                                     */
/* ---------------------------------------------------------------------- */

flxan_gql_t(
	'FlxAnalyticsConfig',
	__( 'What the screens need once: option lists, permissions and links.', 'flx-analytics' ),
	array(
		'presets'           => array( '[FlxAnalyticsOption!]!', __( 'Date range names (value is the range key).', 'flx-analytics' ) ),
		'kinds'             => array( '[FlxAnalyticsOption!]!', __( 'Breakdown names (value is the breakdown key).', 'flx-analytics' ) ),
		'events'            => array( '[FlxAnalyticsOption!]!', __( 'Event names.', 'flx-analytics' ) ),
		'channels'          => array( '[FlxAnalyticsOption!]!', __( 'Channel names.', 'flx-analytics' ) ),
		'segments'          => array( '[FlxAnalyticsOption!]!', __( 'Visitor types for the filter.', 'flx-analytics' ) ),
		'funnelBy'          => array( '[FlxAnalyticsOption!]!', __( 'Ways to split a funnel.', 'flx-analytics' ) ),
		'goals'             => array( '[FlxAnalyticsOption!]!', __( 'Journey goals (funnel steps).', 'flx-analytics' ) ),
		'defaultGoal'       => array( 'String!', __( 'The goal used when none is chosen.', 'flx-analytics' ) ),
		'canManageRefs'     => array( 'Boolean!', __( 'The user may add and rename referral links.', 'flx-analytics' ) ),
		'canManage'         => array( 'Boolean!', __( 'The user manages the site (settings, data).', 'flx-analytics' ) ),
		'ignoreBrowser'     => array( 'Boolean!', __( 'This browser is not counted (the user\'s role is excluded).', 'flx-analytics' ) ),
		'fastEndpoint'      => array( 'String!', __( 'Address of the fast endpoint to test now; empty when no test is due.', 'flx-analytics' ) ),
		'exportUrl'         => array( 'String!', __( 'CSV download address (add type, range, start, end, seg, rc, kind).', 'flx-analytics' ) ),
		'homeUrl'           => array( 'String!', __( 'Site address.', 'flx-analytics' ) ),
		'settingsUrl'       => array( 'String!', __( 'Settings page address; empty without permission.', 'flx-analytics' ) ),
		'funnelSettingsUrl' => array( 'String!', __( 'Funnel settings address; empty without permission.', 'flx-analytics' ) ),
		'hasWorldMap'       => array( 'Boolean!', __( 'The world map file is installed.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsFunnelStepDef',
	__( 'A funnel step as stored.', 'flx-analytics' ),
	array(
		'label' => array( 'String!', __( 'Step name.', 'flx-analytics' ) ),
		'type'  => array( 'FlxAnalyticsStepType!', __( 'What the step matches.', 'flx-analytics' ) ),
		'value' => array( 'String!', __( 'Path or event.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsFunnelDef',
	__( 'A funnel as stored.', 'flx-analytics' ),
	array(
		'id'    => array( 'Int!', __( 'Funnel number.', 'flx-analytics' ) ),
		'name'  => array( 'String!', __( 'Funnel name.', 'flx-analytics' ) ),
		'steps' => array( '[FlxAnalyticsFunnelStepDef!]!', __( 'Steps in order.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsPageOption',
	__( 'A page the funnel editor suggests.', 'flx-analytics' ),
	array(
		'path'  => array( 'String!', __( 'Path.', 'flx-analytics' ) ),
		'title' => array( 'String!', __( 'Title.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsKoko',
	__( 'Koko Analytics data that can be imported.', 'flx-analytics' ),
	array(
		'days'  => array( 'Int!', __( 'Days of data.', 'flx-analytics' ) ),
		'first' => array( 'String!', __( 'First day.', 'flx-analytics' ) ),
		'last'  => array( 'String!', __( 'Last day.', 'flx-analytics' ) ),
	)
);
flx_gql_type(
	'FlxAnalyticsSettings',
	array(
		'description' => __( 'Flux Analytics settings and the data status.', 'flx-analytics' ),
		'fields'      => array(
			'on'             => array( 'type' => 'Boolean!', 'description' => __( 'Visits are counted.', 'flx-analytics' ) ),
			'method'         => array( 'type' => 'FlxAnalyticsCollectMethod!', 'description' => __( 'Collection method setting.', 'flx-analytics' ) ),
			'methodNow'      => array( 'type' => 'FlxAnalyticsCollectMethod!', 'description' => __( 'Method in use now (fast or rest).', 'flx-analytics' ) ),
			'dnt'            => array( 'type' => 'Boolean!', 'description' => __( 'Visitors who send "Do Not Track" are not counted.', 'flx-analytics' ) ),
			'excludeRoles'   => array( 'type' => '[String!]!', 'description' => __( 'Roles that are not counted.', 'flx-analytics' ) ),
			'excludeIps'     => array( 'type' => 'String!', 'description' => __( 'IP addresses that are not counted, one per line.', 'flx-analytics' ) ),
			'evOutbound'     => array( 'type' => 'Boolean!', 'description' => __( 'Outbound link clicks are tracked.', 'flx-analytics' ) ),
			'evDownload'     => array( 'type' => 'Boolean!', 'description' => __( 'File downloads are tracked.', 'flx-analytics' ) ),
			'evContact'      => array( 'type' => 'Boolean!', 'description' => __( 'Phone, email and WhatsApp clicks are tracked.', 'flx-analytics' ) ),
			'evSearch'       => array( 'type' => 'Boolean!', 'description' => __( 'Site searches are tracked.', 'flx-analytics' ) ),
			'evDonation'     => array( 'type' => 'Boolean!', 'description' => __( 'Paid donations and orders are tracked.', 'flx-analytics' ) ),
			'postsColumn'    => array( 'type' => 'Boolean!', 'description' => __( 'The post and page lists have a views column.', 'flx-analytics' ) ),
			'adminbar'       => array( 'type' => 'Boolean!', 'description' => __( 'The admin bar shows the page\'s views.', 'flx-analytics' ) ),
			'keepMonths'     => array( 'type' => 'Int!', 'description' => __( 'Months to keep data; 0 = forever.', 'flx-analytics' ) ),
			'tmHub'          => array( 'type' => 'Boolean!', 'description' => __( 'This site receives telemetry from FluxWP installs.', 'flx-analytics' ) ),
			'hubEndpoint'    => array( 'type' => 'String', 'description' => __( 'Telemetry collection address; empty when the hub code is missing.', 'flx-analytics' ) ),
			'lastAggregated' => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'When the visit queue was last processed; empty when never.', 'flx-analytics' ),
				'resolve'     => static fn( $s ) => flxan_gql_time( $s, 'lastAggregated' ),
			),
			'pendingBytes'   => array( 'type' => 'Int!', 'description' => __( 'Bytes waiting in the visit queue.', 'flx-analytics' ) ),
			'pendingLabel'   => array( 'type' => 'String!', 'description' => __( 'The same as text (12 KB).', 'flx-analytics' ) ),
			'fastPossible'   => array( 'type' => 'Boolean!', 'description' => __( 'The fast endpoint can be used on this install.', 'flx-analytics' ) ),
			'fastTest'       => array( 'type' => 'String!', 'description' => __( 'Result of the last fast endpoint test: ok, fail or empty.', 'flx-analytics' ) ),
			'roles'          => array( 'type' => '[FlxAnalyticsOption!]!', 'description' => __( 'Roles to exclude from counting.', 'flx-analytics' ) ),
			'funnels'        => array( 'type' => '[FlxAnalyticsFunnelDef!]!', 'description' => __( 'Funnels in use.', 'flx-analytics' ) ),
			'funnelDefaults' => array( 'type' => '[FlxAnalyticsFunnelDef!]!', 'description' => __( 'The default funnel (for quick steps and reset).', 'flx-analytics' ) ),
			'funnelEvents'   => array( 'type' => '[FlxAnalyticsOption!]!', 'description' => __( 'Events a funnel step can use.', 'flx-analytics' ) ),
			'funnelPages'    => array( 'type' => '[FlxAnalyticsPageOption!]!', 'description' => __( 'Pages a funnel step can suggest.', 'flx-analytics' ) ),
			'koko'           => array( 'type' => 'FlxAnalyticsKoko', 'description' => __( 'Koko Analytics data to import; empty when there is none.', 'flx-analytics' ) ),
			'exportUrl'      => array( 'type' => 'String!', 'description' => __( 'CSV download address.', 'flx-analytics' ) ),
		),
	)
);

/* ---------------------------------------------------------------------- */
/* Product telemetry                                                       */
/* ---------------------------------------------------------------------- */

flxan_gql_t(
	'FlxAnalyticsTmCount',
	__( 'A value and the sites that have it.', 'flx-analytics' ),
	array(
		'key'   => array( 'String!', __( 'Value.', 'flx-analytics' ) ),
		'label' => array( 'String!', __( 'Name.', 'flx-analytics' ) ),
		'sites' => array( 'Int!', __( 'Sites.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmAppUsage',
	__( 'Daily active accounts of the mobile app.', 'flx-analytics' ),
	array(
		'day'      => array( 'String!', __( 'UTC day.', 'flx-analytics' ) ),
		'platform' => array( 'String!', __( 'ios or android.', 'flx-analytics' ) ),
		'accounts' => array( 'Int!', __( 'Consenting accounts.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmUsage',
	__( 'A package and how many sites use it.', 'flx-analytics' ),
	array(
		'slug'       => array( 'String!', __( 'Package slug.', 'flx-analytics' ) ),
		'name'       => array( 'String!', __( 'Package name.', 'flx-analytics' ) ),
		'sites'      => array( 'Int!', __( 'Sites.', 'flx-analytics' ) ),
		'topVersion' => array( 'String!', __( 'Most common version.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmPair',
	__( 'Two packages used together.', 'flx-analytics' ),
	array(
		'aName' => array( 'String!', __( 'First package.', 'flx-analytics' ) ),
		'bName' => array( 'String!', __( 'Second package.', 'flx-analytics' ) ),
		'sites' => array( 'Int!', __( 'Sites.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmCombo',
	__( 'A set of packages.', 'flx-analytics' ),
	array(
		'names' => array( '[String!]!', __( 'Package names; empty = FluxWP Panel only.', 'flx-analytics' ) ),
		'sites' => array( 'Int!', __( 'Sites.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmOverview',
	__( 'Telemetry overview.', 'flx-analytics' ),
	array(
		'installs'   => array( 'Int!', __( 'Reporting sites.', 'flx-analytics' ) ),
		'reports'    => array( 'Int!', __( 'Reports received.', 'flx-analytics' ) ),
		'errorsOpen' => array( 'Int!', __( 'Distinct errors.', 'flx-analytics' ) ),
		'fatalOpen'  => array( 'Int!', __( 'Distinct fatal errors.', 'flx-analytics' ) ),
		'events'     => array( 'Int!', __( 'Error occurrences.', 'flx-analytics' ) ),
		'appUsage'   => array( '[FlxAnalyticsTmAppUsage!]!', __( 'Mobile app daily active accounts.', 'flx-analytics' ) ),
		'usage'      => array( '[FlxAnalyticsTmUsage!]!', __( 'Package usage (25).', 'flx-analytics' ) ),
		'pairs'      => array( '[FlxAnalyticsTmPair!]!', __( 'Packages used together (15).', 'flx-analytics' ) ),
		'combos'     => array( '[FlxAnalyticsTmCombo!]!', __( 'Most common package sets (10).', 'flx-analytics' ) ),
		'php'        => array( '[FlxAnalyticsTmCount!]!', __( 'PHP versions.', 'flx-analytics' ) ),
		'wp'         => array( '[FlxAnalyticsTmCount!]!', __( 'WordPress versions.', 'flx-analytics' ) ),
		'locales'    => array( '[FlxAnalyticsTmCount!]!', __( 'Site languages (8).', 'flx-analytics' ) ),
		'countries'  => array( '[FlxAnalyticsTmCount!]!', __( 'Countries (15).', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmReach',
	__( 'A wizard step and how many runs reached it.', 'flx-analytics' ),
	array(
		'step'  => array( 'String!', __( 'Step key.', 'flx-analytics' ) ),
		'label' => array( 'String!', __( 'Step name.', 'flx-analytics' ) ),
		'count' => array( 'Int!', __( 'Runs.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmStop',
	__( 'Where wizard runs stopped.', 'flx-analytics' ),
	array(
		'step'    => array( 'String!', __( 'Step key.', 'flx-analytics' ) ),
		'label'   => array( 'String!', __( 'Step name.', 'flx-analytics' ) ),
		'outcome' => array( 'String!', __( 'skip (skipped) or left (closed).', 'flx-analytics' ) ),
		'count'   => array( 'Int!', __( 'Runs.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmCard',
	__( 'A wizard card and its choices.', 'flx-analytics' ),
	array(
		'slug'    => array( 'String!', __( 'Card key.', 'flx-analytics' ) ),
		'name'    => array( 'String!', __( 'Card name.', 'flx-analytics' ) ),
		'picked'  => array( 'Int!', __( 'Runs that picked it.', 'flx-analytics' ) ),
		'dropped' => array( 'Int!', __( 'Runs that picked it and removed it.', 'flx-analytics' ) ),
		'recOff'  => array( 'Int!', __( 'Runs that removed it from the suggestions.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmChoice',
	__( 'A choice and how many runs made it.', 'flx-analytics' ),
	array(
		'key'   => array( 'String!', __( 'Choice.', 'flx-analytics' ) ),
		'count' => array( 'Int!', __( 'Runs.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmOnboarding',
	__( 'Setup wizard numbers.', 'flx-analytics' ),
	array(
		'runs'          => array( 'Int!', __( 'Wizard runs (latest run per install).', 'flx-analytics' ) ),
		'done'          => array( 'Int!', __( 'Finished.', 'flx-analytics' ) ),
		'skipped'       => array( 'Int!', __( 'Skipped.', 'flx-analytics' ) ),
		'left'          => array( 'Int!', __( 'Closed before the end.', 'flx-analytics' ) ),
		'medianSeconds' => array( 'Int!', __( 'Median time of the runs that finished, in seconds.', 'flx-analytics' ) ),
		'reach'         => array( '[FlxAnalyticsTmReach!]!', __( 'Steps reached.', 'flx-analytics' ) ),
		'stops'         => array( '[FlxAnalyticsTmStop!]!', __( 'Where runs stopped.', 'flx-analytics' ) ),
		'cards'         => array( '[FlxAnalyticsTmCard!]!', __( 'Card choices.', 'flx-analytics' ) ),
		'presets'       => array( '[FlxAnalyticsTmChoice!]!', __( 'Presets of finished runs.', 'flx-analytics' ) ),
		'styles'        => array( '[FlxAnalyticsTmChoice!]!', __( 'Colour styles of finished runs.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmCountry',
	__( 'A country that reported.', 'flx-analytics' ),
	array(
		'code'  => array( 'String!', __( 'Lower case country code.', 'flx-analytics' ) ),
		'name'  => array( 'String!', __( 'Country name.', 'flx-analytics' ) ),
		'sites' => array( 'Int!', __( 'Sites.', 'flx-analytics' ) ),
	)
);
flxan_gql_t(
	'FlxAnalyticsTelemetry',
	__( 'Product telemetry received from FluxWP installs.', 'flx-analytics' ),
	array(
		'enabled'    => array( 'Boolean!', __( 'The hub is on.', 'flx-analytics' ) ),
		'endpoint'   => array( 'String!', __( 'Address FluxWP Panel sends to.', 'flx-analytics' ) ),
		'days'       => array( 'Int!', __( 'Range in days.', 'flx-analytics' ) ),
		'country'    => array( 'String!', __( 'Country filter; empty = all.', 'flx-analytics' ) ),
		'countries'  => array( '[FlxAnalyticsTmCountry!]!', __( 'Countries to filter by.', 'flx-analytics' ) ),
		'overview'   => array( 'FlxAnalyticsTmOverview!', __( 'Overview numbers.', 'flx-analytics' ) ),
		'onboarding' => array( 'FlxAnalyticsTmOnboarding!', __( 'Setup wizard numbers.', 'flx-analytics' ) ),
	)
);
flx_gql_type(
	'FlxAnalyticsTmError',
	array(
		'description' => __( 'An error FluxWP installs reported.', 'flx-analytics' ),
		'fields'      => array(
			'hash'       => array( 'type' => 'String!', 'description' => __( 'Record key.', 'flx-analytics' ) ),
			'type'       => array( 'type' => 'String!', 'description' => __( 'PHP error type (E_WARNING …).', 'flx-analytics' ) ),
			'fatal'      => array( 'type' => 'Boolean!', 'description' => __( 'The error stops the request.', 'flx-analytics' ) ),
			'message'    => array( 'type' => 'String!', 'description' => __( 'First line of the message (paths, domains, emails and IPs removed).', 'flx-analytics' ) ),
			'more'       => array( 'type' => 'String!', 'description' => __( 'The rest of the message.', 'flx-analytics' ) ),
			'file'       => array( 'type' => 'String!', 'description' => __( 'File inside the package.', 'flx-analytics' ) ),
			'line'       => array( 'type' => 'Int!', 'description' => __( 'Line; 0 when unknown.', 'flx-analytics' ) ),
			'plugin'     => array( 'type' => 'String!', 'description' => __( 'Package key.', 'flx-analytics' ) ),
			'pluginName' => array( 'type' => 'String!', 'description' => __( 'Package name.', 'flx-analytics' ) ),
			'version'    => array( 'type' => 'String!', 'description' => __( 'Package version.', 'flx-analytics' ) ),
			'sites'      => array( 'type' => 'Int!', 'description' => __( 'Different installs that saw it.', 'flx-analytics' ) ),
			'count'      => array( 'type' => 'Int!', 'description' => __( 'Occurrences.', 'flx-analytics' ) ),
			'last'       => array(
				'type'        => 'FlxDateTime!',
				'description' => __( 'When it last happened.', 'flx-analytics' ),
				'resolve'     => static fn( $e ) => flxan_gql_time( $e, 'lastTs' ) ?? flx_gql_datetime( time() ),
			),
		),
	)
);
flxan_gql_t(
	'FlxAnalyticsTmErrors',
	__( 'Error records and the packages to filter by.', 'flx-analytics' ),
	array(
		'items'   => array( '[FlxAnalyticsTmError!]!', __( 'Records, fatal first (300 at most).', 'flx-analytics' ) ),
		'plugins' => array( '[FlxAnalyticsOption!]!', __( 'Packages that have errors.', 'flx-analytics' ) ),
	)
);

/* ---------------------------------------------------------------------- */
/* Queries                                                                 */
/* ---------------------------------------------------------------------- */

$flxan_filter_arg = array( 'type' => 'FlxAnalyticsFilterInput', 'description' => __( 'Date range and visitor filters.', 'flx-analytics' ) );

flx_gql_query(
	'flxAnalyticsConfig',
	array(
		'type'        => 'FlxAnalyticsConfig!',
		'description' => __( 'Option lists, permissions and links the statistics screens need.', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_view',
		'resolve'     => static fn(): array => FLXAN_Service::config(),
	)
);
flx_gql_query(
	'flxAnalyticsReport',
	array(
		'type'        => 'FlxAnalyticsReport!',
		'description' => __( 'Everything the Statistics screen shows for a date range.', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_view',
		'args'        => array( 'filter' => $flxan_filter_arg ),
		'resolve'     => static function ( $root, array $args ): array {
			flxan_gql_tag();
			return FLXAN_Service::report( flxan_gql_filter( $args ) );
		},
	)
);
flx_gql_query(
	'flxAnalyticsRealtime',
	array(
		'type'        => 'FlxAnalyticsRealtime!',
		'description' => __( 'Who is on the site now; with kpi the headline numbers and chart too (a light query to repeat every few seconds).', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_view',
		'args'        => array(
			'filter'  => $flxan_filter_arg,
			'minutes' => array( 'type' => 'Int', 'description' => __( 'Window in minutes, 1–60 (default 30).', 'flx-analytics' ), 'default' => 30 ),
			'kpi'     => array( 'type' => 'Boolean', 'description' => __( 'Include the headline numbers and chart.', 'flx-analytics' ), 'default' => false ),
		),
		'resolve'     => static fn( $root, array $args ): array => FLXAN_Service::realtime( flxan_gql_filter( $args ), max( 1, min( 60, FLXAN_Service::int( $args['minutes'] ?? 30 ) ) ), ! empty( $args['kpi'] ) ),
	)
);
flx_gql_query(
	'flxAnalyticsPages',
	array(
		'type'        => 'FlxAnalyticsPageRowConnection!',
		'description' => __( 'Pages by views, a page at a time.', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_view',
		'args'        => flx_gql_connection_args(
			array(
				'filter'  => $flxan_filter_arg,
				'q'       => array( 'type' => 'String', 'description' => __( 'Text in the address.', 'flx-analytics' ) ),
				'only404' => array( 'type' => 'Boolean', 'description' => __( 'Only addresses that were not found.', 'flx-analytics' ), 'default' => false ),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
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
		'description' => __( 'Referring sites by visitors, a page at a time.', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_view',
		'args'        => flx_gql_connection_args(
			array(
				'filter' => $flxan_filter_arg,
				'q'      => array( 'type' => 'String', 'description' => __( 'Text in the domain name.', 'flx-analytics' ) ),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
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
		'description' => __( 'Traffic split by channel, device, browser, country or campaign, a page at a time.', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_view',
		'args'        => flx_gql_connection_args(
			array(
				'filter' => $flxan_filter_arg,
				'kind'   => array( 'type' => 'FlxAnalyticsDimKind!', 'description' => __( 'What to split by.', 'flx-analytics' ) ),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
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
		'description' => __( 'Events (clicks, downloads, donations …), a page at a time.', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_view',
		'args'        => flx_gql_connection_args(
			array(
				'filter' => $flxan_filter_arg,
				'name'   => array( 'type' => 'String', 'description' => __( 'Only this event; empty = all.', 'flx-analytics' ) ),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
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
		'description' => __( 'Views of one page or post.', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_view',
		'args'        => array(
			'filter' => $flxan_filter_arg,
			'target' => array( 'type' => 'String!', 'description' => __( 'Post ID, path or address.', 'flx-analytics' ) ),
		),
		'resolve'     => static function ( $root, array $args ): array {
			flxan_gql_tag();
			return FLXAN_Service::page_stats( flxan_gql_filter( $args ), FLXAN_Service::str( $args['target'] ?? '' ) );
		},
	)
);
flx_gql_query(
	'flxAnalyticsFunnel',
	array(
		'type'        => 'FlxAnalyticsFunnel!',
		'description' => __( 'A funnel, optionally split by channel, site, code, role or country.', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_view',
		'args'        => array(
			'filter' => $flxan_filter_arg,
			'id'     => array( 'type' => 'Int', 'description' => __( 'Funnel number; 0 or none = the first.', 'flx-analytics' ), 'default' => 0 ),
			'by'     => array( 'type' => 'FlxAnalyticsFunnelBy', 'description' => __( 'How to split it (default total).', 'flx-analytics' ), 'default' => 'total' ),
		),
		'resolve'     => static function ( $root, array $args ): array {
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
		'description' => __( 'Referral links with what their visitors did (50 at most).', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_view',
		'args'        => array( 'filter' => $flxan_filter_arg ),
		'resolve'     => static function ( $root, array $args ): array {
			flxan_gql_tag();
			return FLXAN_Service::refs( flxan_gql_filter( $args ), 50 );
		},
	)
);
flx_gql_query(
	'flxAnalyticsJourneys',
	array(
		'type'        => 'FlxAnalyticsJourneys!',
		'description' => __( 'How visitors move through the site: flow chart and frequent paths.', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_view',
		'args'        => array(
			'filter'  => $flxan_filter_arg,
			'options' => array( 'type' => 'FlxAnalyticsJourneysInput', 'description' => __( 'Steps, filters, goal and grouping.', 'flx-analytics' ) ),
		),
		'resolve'     => static function ( $root, array $args ): array {
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
		'description' => __( 'Flux Analytics settings and the data status.', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_manage',
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
		'description' => __( 'Anonymous product telemetry from FluxWP installs: usage, versions and the setup wizard.', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_hub',
		'args'        => array(
			'days'    => array( 'type' => 'Int', 'description' => __( 'Range in days: 7, 30 (default) or 90.', 'flx-analytics' ), 'default' => 30 ),
			'country' => array( 'type' => 'String', 'description' => __( 'Only installs of this country (two-letter code).', 'flx-analytics' ) ),
		),
		'resolve'     => static fn( $root, array $args ): array => FLXAN_Telemetry_Service::summary( FLXAN_Service::int( $args['days'] ?? 30 ), FLXAN_Service::str( $args['country'] ?? '' ) ),
	)
);
flx_gql_query(
	'flxAnalyticsTelemetryErrors',
	array(
		'type'        => 'FlxAnalyticsTmErrors!',
		'description' => __( 'Errors FluxWP installs reported, fatal first.', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_hub',
		'args'        => array(
			'days'    => array( 'type' => 'Int', 'description' => __( 'Range in days: 7, 30 (default) or 90.', 'flx-analytics' ), 'default' => 30 ),
			'country' => array( 'type' => 'String', 'description' => __( 'Only errors seen on installs of this country.', 'flx-analytics' ) ),
			'plugin'  => array( 'type' => 'String', 'description' => __( 'Only this package.', 'flx-analytics' ) ),
			'type'    => array( 'type' => 'String', 'description' => __( 'fatal, or one PHP error type such as E_WARNING.', 'flx-analytics' ) ),
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
		'description' => __( 'Adds a referral link name, renames it, or removes the name (the statistics stay).', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_refs',
		'input'       => array(
			'code'   => array( 'type' => 'String!', 'description' => __( 'Referral code (letters, digits, - and _).', 'flx-analytics' ) ),
			'name'   => array( 'type' => 'String', 'description' => __( 'Name shown in reports.', 'flx-analytics' ) ),
			'delete' => array( 'type' => 'Boolean', 'description' => __( 'Remove the name.', 'flx-analytics' ), 'default' => false ),
		),
		'output'      => array(
			'ref' => array( 'type' => 'FlxAnalyticsRefSaved', 'description' => __( 'The saved code and its link.', 'flx-analytics' ) ),
		),
		'resolve'     => static function ( array $input ): array {
			$ref = FLXAN_Service::ref_save( FLXAN_Service::str( $input['code'] ?? '' ), FLXAN_Service::str( $input['name'] ?? '' ), ! empty( $input['delete'] ) );
			if ( null === $ref ) {
				throw flx_gql_error( 'INVALID_INPUT', __( 'Invalid code. Use letters, digits, - and _.', 'flx-analytics' ), 'code' );
			}
			return array( 'ref' => $ref );
		},
	)
);
flx_gql_mutation(
	'flxAnalyticsSettingsSave',
	array(
		'description' => __( 'Changes settings and funnels. Fields you do not send stay as they are.', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_manage',
		'input'       => array(
			'on'           => array( 'type' => 'Boolean', 'description' => __( 'Count visits.', 'flx-analytics' ) ),
			'method'       => array( 'type' => 'FlxAnalyticsCollectMethod', 'description' => __( 'Collection method.', 'flx-analytics' ) ),
			'dnt'          => array( 'type' => 'Boolean', 'description' => __( 'Do not count visitors who send "Do Not Track".', 'flx-analytics' ) ),
			'excludeRoles' => array( 'type' => '[String!]', 'description' => __( 'Roles that are not counted.', 'flx-analytics' ) ),
			'excludeIps'   => array( 'type' => 'String', 'description' => __( 'IP addresses that are not counted, one per line (* and CIDR work).', 'flx-analytics' ) ),
			'evOutbound'   => array( 'type' => 'Boolean', 'description' => __( 'Track outbound link clicks.', 'flx-analytics' ) ),
			'evDownload'   => array( 'type' => 'Boolean', 'description' => __( 'Track file downloads.', 'flx-analytics' ) ),
			'evContact'    => array( 'type' => 'Boolean', 'description' => __( 'Track phone, email and WhatsApp clicks.', 'flx-analytics' ) ),
			'evSearch'     => array( 'type' => 'Boolean', 'description' => __( 'Track site searches.', 'flx-analytics' ) ),
			'evDonation'   => array( 'type' => 'Boolean', 'description' => __( 'Track paid donations and orders.', 'flx-analytics' ) ),
			'postsColumn'  => array( 'type' => 'Boolean', 'description' => __( 'Show a views column in the post and page lists.', 'flx-analytics' ) ),
			'adminbar'     => array( 'type' => 'Boolean', 'description' => __( 'Show the page\'s views in the admin bar.', 'flx-analytics' ) ),
			'keepMonths'   => array( 'type' => 'Int', 'description' => __( 'Months to keep data (0–120); 0 = forever.', 'flx-analytics' ) ),
			'tmHub'        => array( 'type' => 'Boolean', 'description' => __( 'Receive telemetry from FluxWP installs.', 'flx-analytics' ) ),
			'funnels'      => array( 'type' => '[FlxAnalyticsFunnelInput!]', 'description' => __( 'Replaces the funnels (5 at most); an empty list or the default set goes back to the defaults.', 'flx-analytics' ) ),
		),
		'output'      => array(
			'settings' => array( 'type' => 'FlxAnalyticsSettings', 'description' => __( 'Settings after the change.', 'flx-analytics' ) ),
		),
		'resolve'     => static function ( array $input ): array {
			return array( 'settings' => FLXAN_Service::save_settings( $input ) );
		},
	)
);
flx_gql_mutation(
	'flxAnalyticsAggregate',
	array(
		'description' => __( 'Processes the waiting visits now.', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_manage',
		'output'      => array(
			'processed' => array( 'type' => 'Int!', 'description' => __( 'Visit rows processed.', 'flx-analytics' ) ),
			'settings'  => array( 'type' => 'FlxAnalyticsSettings', 'description' => __( 'Settings and data status after the run.', 'flx-analytics' ) ),
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
		'description' => __( 'Deletes all statistics permanently.', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_manage',
		'output'      => array(
			'settings' => array( 'type' => 'FlxAnalyticsSettings', 'description' => __( 'Settings and data status after the reset.', 'flx-analytics' ) ),
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
		'description' => __( 'Imports Koko Analytics history from before Flux Analytics was installed.', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_manage',
		'output'      => array(
			'rows'     => array( 'type' => 'Int!', 'description' => __( 'Rows imported.', 'flx-analytics' ) ),
			'settings' => array( 'type' => 'FlxAnalyticsSettings', 'description' => __( 'Settings and data status after the import.', 'flx-analytics' ) ),
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
		'description' => __( 'Records whether this browser reached the fast endpoint.', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_manage',
		'input'       => array(
			'ok' => array( 'type' => 'Boolean!', 'description' => __( 'The endpoint answered.', 'flx-analytics' ) ),
		),
		'output'      => array(
			'method' => array( 'type' => 'FlxAnalyticsCollectMethod!', 'description' => __( 'Collection method in use now.', 'flx-analytics' ) ),
		),
		'resolve'     => static fn( array $input ): array => array( 'method' => FLXAN_Service::fast_test( ! empty( $input['ok'] ) ) ),
	)
);
flx_gql_mutation(
	'flxAnalyticsTelemetryForget',
	array(
		'description' => __( 'Removes one telemetry error record, or all of them with "all". A record returns when the error happens again.', 'flx-analytics' ),
		'cap'         => 'flxan_gql_can_hub',
		'input'       => array(
			'hash' => array( 'type' => 'String!', 'description' => __( 'Record key, or "all".', 'flx-analytics' ) ),
		),
		'output'      => array(
			'removed' => array( 'type' => 'Boolean!', 'description' => __( 'The record was removed.', 'flx-analytics' ) ),
		),
		'resolve'     => static function ( array $input ): array {
			if ( ! FLXAN_Telemetry_Service::forget( FLXAN_Service::str( $input['hash'] ?? '' ) ) ) {
				throw flx_gql_error( 'INVALID_INPUT', __( 'The record key is not valid.', 'flx-analytics' ), 'hash' );
			}
			return array( 'removed' => true );
		},
	)
);
