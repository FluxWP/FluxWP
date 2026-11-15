<?php
/**
 * Flux Analytics GraphQL contract tests. The documents are the persisted operations the Analytics
 * screens send (plugins/flx-analytics/graphql/persisted-queries.json). The classic pages and REST
 * keep reading FLXAN_Stats / FLXAN_Journeys; GraphQL goes through FLXAN_Service, so parity is checked.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-analytics/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux Analytics GraphQL\n";

$GLOBALS['flxan_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXAN_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxan_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 21, count( $GLOBALS['flxan_ops'] ), 'Flux Analytics ships 21 persisted operations' );

function flxan_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxan_ops'][ $name ], $vars, $user );
}

$admin = 1;
$sub   = (int) wp_insert_user( array( 'user_login' => 'flxan_sub_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$viewer = (int) wp_insert_user( array( 'user_login' => 'flxan_view_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
( new WP_User( $viewer ) )->add_cap( 'flxan_view' );
$refer = (int) wp_insert_user( array( 'user_login' => 'flxan_ref_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'author' ) ); // may name referral links
( new WP_User( $refer ) )->add_cap( 'flxan_view' );

// Permissions: every operation is closed to visitors and plain members.
foreach ( array( 'FlxAnalyticsConfig', 'FlxAnalyticsReport', 'FlxAnalyticsRealtime', 'FlxAnalyticsRefs', 'FlxAnalyticsFunnel' ) as $op ) {
	flxt_same( 'UNAUTHENTICATED', flxt_code( flxan_t_op( $op ) ), "$op: visitor → UNAUTHENTICATED" );
	flxt_same( 'FORBIDDEN', flxt_code( flxan_t_op( $op, array(), $sub ) ), "$op: subscriber → FORBIDDEN" );
}
flxt_same( 'FORBIDDEN', flxt_code( flxan_t_op( 'FlxAnalyticsSettings', array(), $viewer ) ), 'settings: viewer without manage_options → FORBIDDEN' );
flxt_same( 'UNAUTHENTICATED', flxt_code( flxan_t_op( 'FlxAnalyticsSettings' ) ), 'settings: visitor → UNAUTHENTICATED' );
flxt_same( 'FORBIDDEN', flxt_code( flxan_t_op( 'FlxAnalyticsAggregate', array( 'input' => new stdClass() ), $viewer ) ), 'aggregate: viewer → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxan_t_op( 'FlxAnalyticsReset', array( 'input' => new stdClass() ), $viewer ) ), 'reset: viewer → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxan_t_op( 'FlxAnalyticsSettingsSave', array( 'input' => array( 'dnt' => true ) ), $viewer ) ), 'settingsSave: viewer → FORBIDDEN' );

// Empty data.
$r = flxan_t_op( 'FlxAnalyticsReport', array(), $viewer );
flxt_same( null, $r['errors'] ?? null, 'report: viewer reads an empty report without errors' );
$d = $r['data']['flxAnalyticsReport'] ?? array();
flxt_same( 0, $d['kpi']['totals']['visitors'] ?? null, 'report: empty visitors = 0' );
flxt_same( array(), $d['pages']['items'] ?? null, 'report: empty pages' );
flxt_same( 'DAYS_30', $d['range']['preset'] ?? null, 'report: default range is 30 days' );
$r = flxan_t_op( 'FlxAnalyticsJourneys', array(), $viewer );
flxt_same( null, $r['errors'] ?? null, 'journeys: empty report without errors' );
flxt_same( 0, $r['data']['flxAnalyticsJourneys']['summary']['sessions'] ?? null, 'journeys: empty sessions = 0' );

// Seed: 3 visitors, pages, referrers, an event and a referral code, then process them.
$day = gmdate( 'Y-m-d' );
$now = time();
$rec = static function ( string $h, string $path, string $ref = '', string $rc = '' ) use ( $day, $now ): array {
	$r = array( 'k' => 'p', 't' => $now, 'd' => $day, 'h' => $h, 'p' => $path, 'i' => 0, 'r' => $ref, 'u' => 'Mozilla/5.0 (X11; Linux x86_64) Chrome/120', 'w' => 1280, 'l' => 'tr', 'c' => 'TR' );
	if ( '' !== $rc ) {
		$r['rc'] = $rc;
	}
	return $r;
};
FLXAN_Aggregator::process(
	array(
		$rec( 'aaaaaaaaaaaaaaa1', '/', 'https://www.google.com/', 'ali' ),
		$rec( 'aaaaaaaaaaaaaaa1', '/donate/' ),
		$rec( 'aaaaaaaaaaaaaaa2', '/', 'https://t.co/x' ),
		$rec( 'aaaaaaaaaaaaaaa3', '/blog/', '' ),
		array( 'k' => 'e', 't' => $now, 'd' => $day, 'h' => 'aaaaaaaaaaaaaaa1', 'n' => 'outbound', 'v' => 'example.com', 'rc' => 'ali' ),
	)
);
flx_gql_purge( array( FLXAN_Service::CACHE_TAG ) );

$r = flxan_t_op( 'FlxAnalyticsReport', array(), $viewer );
flxt_same( null, $r['errors'] ?? null, 'report: full data without errors' );
$d = $r['data']['flxAnalyticsReport'] ?? array();
flxt_same( 3, $d['kpi']['totals']['visitors'] ?? null, 'report: 3 visitors' );
flxt_same( 4, $d['kpi']['totals']['pageviews'] ?? null, 'report: 4 page views' );
flxt_same( '/', $d['pages']['items'][0]['path'] ?? null, 'report: busiest page first' );
$hosts = array_column( $d['referrers']['items'] ?? array(), 'host' );
flxt_ok( in_array( 'www.google.com', $hosts, true ) || in_array( 'google.com', $hosts, true ), 'report: google listed as referrer', $hosts );
flxt_ok( count( $d['dims'] ?? array() ) > 0, 'report: breakdowns present' );
flxt_same( 1, count( array_filter( $d['refs'] ?? array(), static fn( $x ) => 'ali' === $x['code'] ) ), 'report: referral code listed' );
flxt_ok( ( $d['eventSummary'][0]['hits'] ?? 0 ) >= 1, 'report: event summary' );

// Parity with the classic read layer.
$legacy = FLXAN_Stats::report( FLXAN_Stats::range( '30d' ) );
flxt_same( (int) ( $legacy['totals']['visitors'] ?? -1 ), $d['kpi']['totals']['visitors'] ?? null, 'parity: visitors equal FLXAN_Stats::report' );
flxt_same( (int) ( $legacy['totals']['pageviews'] ?? -1 ), $d['kpi']['totals']['pageviews'] ?? null, 'parity: pageviews equal FLXAN_Stats::report' );

// Connections.
$r = flxan_t_op( 'FlxAnalyticsPages', array( 'first' => 2 ), $viewer );
$c = $r['data']['flxAnalyticsPages'] ?? array();
flxt_same( 3, $c['totalCount'] ?? null, 'pages: totalCount' );
flxt_same( 2, count( $c['nodes'] ?? array() ), 'pages: first=2 returns 2' );
flxt_same( true, $c['pageInfo']['hasNextPage'] ?? null, 'pages: hasNextPage' );
$r2 = flxan_t_op( 'FlxAnalyticsPages', array( 'first' => 2, 'after' => $c['pageInfo']['endCursor'] ?? null ), $viewer );
flxt_same( 1, count( $r2['data']['flxAnalyticsPages']['nodes'] ?? array() ), 'pages: second page has the rest' );
$r = flxan_t_op( 'FlxAnalyticsPages', array( 'q' => 'donate' ), $viewer );
flxt_same( 1, $r['data']['flxAnalyticsPages']['totalCount'] ?? null, 'pages: search filters' );
$r = flxan_t_op( 'FlxAnalyticsBreakdown', array( 'kind' => 'BROWSER' ), $viewer );
flxt_same( null, $r['errors'] ?? null, 'breakdown: browser without errors' );
$r = flxan_t_op( 'FlxAnalyticsReferrers', array( 'q' => 'zzz-none' ), $viewer );
flxt_same( 0, $r['data']['flxAnalyticsReferrers']['totalCount'] ?? null, 'referrers: no match' );
$r = flxan_t_op( 'FlxAnalyticsEvents', array( 'name' => 'outbound' ), $viewer );
flxt_same( 1, $r['data']['flxAnalyticsEvents']['totalCount'] ?? null, 'events: filtered by name' );
$r = flxan_t_op( 'FlxAnalyticsPage', array( 'target' => '/donate/' ), $viewer );
flxt_same( true, $r['data']['flxAnalyticsPage']['found'] ?? null, 'page: found' );
$r = flxan_t_op( 'FlxAnalyticsPage', array( 'target' => '/nope/' ), $viewer );
flxt_same( false, $r['data']['flxAnalyticsPage']['found'] ?? null, 'page: unknown path → found=false' );

// Filters.
$r = flxan_t_op( 'FlxAnalyticsReport', array( 'filter' => array( 'range' => 'CUSTOM', 'start' => '2001-01-01', 'end' => '2001-01-31' ) ), $viewer );
flxt_same( 0, $r['data']['flxAnalyticsReport']['kpi']['totals']['visitors'] ?? null, 'filter: custom range outside the data is empty' );
$r = flxan_t_op( 'FlxAnalyticsReport', array( 'filter' => array( 'rc' => 'ali' ) ), $viewer );
flxt_same( 1, $r['data']['flxAnalyticsReport']['kpi']['totals']['visitors'] ?? null, 'filter: referral code limits visitors' );

// Realtime, journeys, funnel, config, refs.
$r = flxan_t_op( 'FlxAnalyticsRealtime', array( 'minutes' => 30, 'kpi' => true ), $viewer );
flxt_same( null, $r['errors'] ?? null, 'realtime: no errors' );
flxt_ok( isset( $r['data']['flxAnalyticsRealtime']['kpi']['totals'] ), 'realtime: kpi on request' );
$r = flxan_t_op( 'FlxAnalyticsJourneys', array( 'options' => array( 'steps' => 3, 'group' => true ) ), $viewer );
flxt_same( null, $r['errors'] ?? null, 'journeys: full data without errors' );
flxt_same( 3, $r['data']['flxAnalyticsJourneys']['options']['steps'] ?? null, 'journeys: steps honoured' );
$r = flxan_t_op( 'FlxAnalyticsFunnel', array( 'by' => 'CHANNEL' ), $viewer );
flxt_same( null, $r['errors'] ?? null, 'funnel: split by channel without errors' );
$r = flxan_t_op( 'FlxAnalyticsConfig', array(), $viewer );
flxt_same( false, $r['data']['flxAnalyticsConfig']['canManage'] ?? null, 'config: viewer cannot manage settings' );
$r = flxan_t_op( 'FlxAnalyticsConfig', array(), $admin );
flxt_same( true, $r['data']['flxAnalyticsConfig']['canManage'] ?? null, 'config: administrator manages settings' );
flxt_same( 20, count( (array) ( flxan_t_op( 'FlxAnalyticsRefs', array(), $viewer )['data']['flxAnalyticsRefs'] ?? array() ) ) > 0 ? 20 : 0, 'refs: list present' );

// Referral names: add, rename, delete, invalid; REST keeps its own error.
flxt_same( 'FORBIDDEN', flxt_code( flxan_t_op( 'FlxAnalyticsRefSave', array( 'input' => array( 'code' => 'ali', 'name' => 'Ali' ) ), $viewer ) ), 'refSave: viewer without edit_posts → FORBIDDEN' );
$r = flxan_t_op( 'FlxAnalyticsRefSave', array( 'input' => array( 'code' => 'ali', 'name' => 'Ali' ) ), $refer );
flxt_same( 'Ali', $r['data']['flxAnalyticsRefSave']['ref']['name'] ?? null, 'refSave: name saved' );
$r = flxan_t_op( 'FlxAnalyticsRefSave', array( 'input' => array( 'code' => 'bad code!', 'name' => 'x' ) ), $refer );
flxt_same( 'code', $r['data']['flxAnalyticsRefSave']['errors'][0]['field'] ?? null, 'refSave: invalid code → errors[].field=code' );
$r = flxan_t_op( 'FlxAnalyticsRefSave', array( 'input' => array( 'code' => 'ali', 'delete' => true ) ), $refer );
flxt_same( '', $r['data']['flxAnalyticsRefSave']['ref']['name'] ?? null, 'refSave: name removed' );
wp_set_current_user( $refer );
$req = new WP_REST_Request( 'POST', '/flxan/v1/refs/save' );
$req->set_body_params( array( 'code' => 'bad code!', 'name' => 'x' ) );
$res = rest_do_request( $req );
flxt_same( 400, $res->get_status(), 'REST refs/save: invalid code still 400' );
flxt_same( 'flxan_bad_code', $res->get_data()['code'] ?? null, 'REST refs/save: error code unchanged' );

// Settings (administrator): read, partial save, validation of funnels, data actions.
$r = flxan_t_op( 'FlxAnalyticsSettings', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'settings: administrator reads' );
$before = $r['data']['flxAnalyticsSettings']['keepMonths'] ?? null;
$r = flxan_t_op( 'FlxAnalyticsSettingsSave', array( 'input' => array( 'dnt' => false, 'keepMonths' => 6 ) ), $admin );
flxt_same( 6, $r['data']['flxAnalyticsSettingsSave']['settings']['keepMonths'] ?? null, 'settingsSave: keepMonths saved' );
flxt_same( false, $r['data']['flxAnalyticsSettingsSave']['settings']['dnt'] ?? null, 'settingsSave: dnt saved' );
flxt_same( false, (bool) flxan_settings( 'dnt' ), 'settingsSave: classic settings read the same value' );
$r = flxan_t_op(
	'FlxAnalyticsSettingsSave',
	array( 'input' => array( 'funnels' => array( array( 'name' => 'Donate', 'steps' => array( array( 'label' => 'Home', 'type' => 'ANY' ), array( 'label' => 'Donate', 'type' => 'PATH', 'value' => '/donate/' ) ) ) ) ) ),
	$admin
);
flxt_same( 'Donate', $r['data']['flxAnalyticsSettingsSave']['settings']['funnels'][0]['name'] ?? null, 'settingsSave: funnel saved' );
$r = flxan_t_op( 'FlxAnalyticsFunnel', array(), $viewer );
flxt_same( 'Donate', $r['data']['flxAnalyticsFunnel']['name'] ?? null, 'funnel: new funnel is reported' );
$r = flxan_t_op( 'FlxAnalyticsFastTest', array( 'input' => array( 'ok' => true ) ), $admin );
flxt_ok( in_array( $r['data']['flxAnalyticsFastTest']['method'] ?? '', array( 'FAST', 'REST' ), true ), 'fastTest: returns the method in use' );
$r = flxan_t_op( 'FlxAnalyticsAggregate', array( 'input' => new stdClass() ), $admin );
flxt_same( null, $r['errors'] ?? null, 'aggregate: no errors' );
$r = flxan_t_op( 'FlxAnalyticsReset', array( 'input' => new stdClass() ), $admin );
flxt_same( null, $r['errors'] ?? null, 'reset: no errors' );
$r = flxan_t_op( 'FlxAnalyticsReport', array(), $viewer );
flxt_same( 0, $r['data']['flxAnalyticsReport']['kpi']['totals']['visitors'] ?? null, 'reset: report is empty and the cache was purged' );

// Product telemetry hub: closed until switched on.
flxt_same( 'FORBIDDEN', flxt_code( flxan_t_op( 'FlxAnalyticsTelemetry', array(), $admin ) ), 'telemetry: hub off → FORBIDDEN' );
flxan_t_op( 'FlxAnalyticsSettingsSave', array( 'input' => array( 'tmHub' => true ) ), $admin );
$r = flxan_t_op( 'FlxAnalyticsTelemetry', array( 'days' => 7 ), $admin );
flxt_same( null, $r['errors'] ?? null, 'telemetry: hub on, administrator reads' );
flxt_same( 7, $r['data']['flxAnalyticsTelemetry']['days'] ?? null, 'telemetry: days honoured' );
flxt_same( 'FORBIDDEN', flxt_code( flxan_t_op( 'FlxAnalyticsTelemetry', array(), $viewer ) ), 'telemetry: viewer → FORBIDDEN' );
$r = flxan_t_op( 'FlxAnalyticsTelemetryErrors', array(), $admin );
flxt_same( array(), $r['data']['flxAnalyticsTelemetryErrors']['items'] ?? null, 'telemetryErrors: none yet' );
$r = flxan_t_op( 'FlxAnalyticsTelemetryForget', array( 'input' => array( 'hash' => 'not a hash!' ) ), $admin );
flxt_same( 'hash', $r['data']['flxAnalyticsTelemetryForget']['errors'][0]['field'] ?? null, 'telemetryForget: bad key → errors[].field=hash' );
$r = flxan_t_op( 'FlxAnalyticsTelemetryForget', array( 'input' => array( 'hash' => 'all' ) ), $admin );
flxt_same( true, $r['data']['flxAnalyticsTelemetryForget']['removed'] ?? null, 'telemetryForget: all removed' );
flxan_t_op( 'FlxAnalyticsSettingsSave', array( 'input' => array( 'tmHub' => false, 'keepMonths' => (int) $before ) ), $admin );

flxt_done();
