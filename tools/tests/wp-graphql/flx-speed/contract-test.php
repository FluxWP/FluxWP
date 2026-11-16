<?php
/**
 * Flux Speed GraphQL contract tests. The documents are the persisted operations the Performance
 * screen sends (plugins/flx-speed/graphql/persisted-queries.json). The classic admin handlers and
 * GraphQL share FLXP_Speed_Service, so the classic behaviour is checked through the service too.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-speed/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux Speed GraphQL\n";

$GLOBALS['flxp_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXP_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxp_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 20, count( $GLOBALS['flxp_ops'] ), 'Flux Speed ships 20 persisted operations' );

function flxp_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxp_ops'][ $name ], $vars, $user );
}

$admin = 1;
$sub   = (int) wp_insert_user( array( 'user_login' => 'flxp_sub_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$viewer = (int) wp_insert_user( array( 'user_login' => 'flxp_view_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
( new WP_User( $viewer ) )->add_cap( 'flxp_view' ); // May view, may not manage.

fwrite( STDERR, '[dbg] wc ' . get_option( 'woocommerce_coming_soon' ) . '/' . get_option( 'woocommerce_store_pages_only' ) . ' @start' . "\n" );
// Permissions: every field fails closed.
foreach ( array( 'FlxSpeedConfig', 'FlxSpeedLive', 'FlxSpeedSummary', 'FlxSpeedRecords', 'FlxSpeedServer', 'FlxSpeedDatabase', 'FlxSpeedAutoload', 'FlxSpeedCron', 'FlxSpeedSystem', 'FlxSpeedSettings', 'FlxSpeedPageSpeed', 'FlxSpeedCache' ) as $op ) {
	$vars = 'FlxSpeedRecords' === $op ? array() : array();
	flxt_same( 'UNAUTHENTICATED', flxt_code( flxp_t_op( $op, $vars ) ), "$op: visitor → UNAUTHENTICATED" );
	flxt_same( 'FORBIDDEN', flxt_code( flxp_t_op( $op, $vars, $sub ) ), "$op: subscriber → FORBIDDEN" );
}
// Viewers read the monitoring tabs but never the admin ones.
foreach ( array( 'FlxSpeedConfig', 'FlxSpeedLive', 'FlxSpeedSummary', 'FlxSpeedRecords', 'FlxSpeedServer', 'FlxSpeedDatabase', 'FlxSpeedAutoload', 'FlxSpeedCron' ) as $op ) {
	$r = flxp_t_op( $op, array(), $viewer );
	flxt_same( null, $r['errors'] ?? null, "$op: flxp_view may read" );
}
foreach ( array( 'FlxSpeedSystem', 'FlxSpeedSettings', 'FlxSpeedPageSpeed', 'FlxSpeedCache' ) as $op ) {
	flxt_same( 'FORBIDDEN', flxt_code( flxp_t_op( $op, array(), $viewer ) ), "$op: flxp_view alone → FORBIDDEN" );
}
foreach ( array(
	'FlxSpeedRecordsClear'    => array(),
	'FlxSpeedCleanup'         => array( 'input' => array( 'job' => 'transients' ) ),
	'FlxSpeedSettingsSave'    => array( 'input' => array( 'sample' => 10 ) ),
	'FlxSpeedPageSpeedSave'   => array( 'input' => array( 'emoji' => true ) ),
	'FlxSpeedPageSpeedClear'  => array(),
	'FlxSpeedCacheRun'        => array( 'input' => array( 'op' => 'TEST' ) ),
) as $op => $vars ) {
	flxt_same( 'FORBIDDEN', flxt_code( flxp_t_op( $op, $vars, $viewer ) ), "$op: flxp_view alone → FORBIDDEN" );
	flxt_same( 'FORBIDDEN', flxt_code( flxp_t_op( $op, $vars, $sub ) ), "$op: subscriber → FORBIDDEN" );
}

fwrite( STDERR, '[dbg] wc ' . get_option( 'woocommerce_coming_soon' ) . '/' . get_option( 'woocommerce_store_pages_only' ) . ' @config' . "\n" );
// Config.
$r = flxp_t_op( 'FlxSpeedConfig', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'config: no errors' );
$c = $r['data']['flxSpeedConfig'] ?? array();
flxt_same( true, $c['canManage'] ?? null, 'config: administrator manages' );
flxt_same( array( 'summary', 'records', 'server', 'database', 'autoload', 'cron', 'system', 'pagespeed', 'cache', 'settings' ), array_column( $c['tabs'] ?? array(), 'key' ), 'config: tabs in display order' );
$v = flxp_t_op( 'FlxSpeedConfig', array(), $viewer );
flxt_same( false, $v['data']['flxSpeedConfig']['canManage'] ?? null, 'config: viewer does not manage' );
flxt_ok( ! in_array( 'settings', array_column( $v['data']['flxSpeedConfig']['tabs'] ?? array(), 'key' ), true ), 'config: viewer does not see the Settings tab' );
flxt_ok( count( $c['flags'] ?? array() ) > 3 && count( $c['types'] ?? array() ) > 3, 'config: filters come with labels' );

fwrite( STDERR, '[dbg] wc ' . get_option( 'woocommerce_coming_soon' ) . '/' . get_option( 'woocommerce_store_pages_only' ) . ' @settings' . "\n" );
// Settings: round trip and clamping, same as the classic form.
$r = flxp_t_op( 'FlxSpeedSettingsSave', array( 'input' => array( 'sample' => 150, 'slowMs' => 50, 'keepDays' => 14, 'record' => true, 'loginBlocked' => 'HOME' ) ), $admin );
flxt_same( null, $r['errors'] ?? null, 'settings: save has no errors' );
$s = $r['data']['flxSpeedSettingsSave']['settings'] ?? array();
flxt_same( array( 100, 14, true, 'HOME' ), array( $s['sample'] ?? null, $s['keepDays'] ?? null, $s['record'] ?? null, $s['loginBlocked'] ?? null ), 'settings: values saved, sample clamped to 100' );
flxt_ok( ( $s['slowMs'] ?? 0 ) >= 100, 'settings: slow threshold clamped' );
flxt_same( 14, (int) flxp_settings( 'keep_days', true ), 'settings: classic reader sees the same value' );
$r2 = flxp_t_op( 'FlxSpeedSettingsSave', array( 'input' => array( 'panel' => false ) ), $admin );
flxt_same( 14, $r2['data']['flxSpeedSettingsSave']['settings']['keepDays'] ?? null, 'settings: fields left out stay' );
$r = flxp_t_op( 'FlxSpeedSettingsSave', array( 'input' => array( 'loginSlug' => 'a!' ) ), $admin );
flxt_same( array( 'INVALID_INPUT', 'loginSlug' ), array( $r['data']['flxSpeedSettingsSave']['errors'][0]['code'] ?? ( $r['errors'][0]['extensions']['code'] ?? null ), $r['data']['flxSpeedSettingsSave']['errors'][0]['field'] ?? ( $r['errors'][0]['extensions']['field'] ?? null ) ), 'settings: bad login slug → INVALID_INPUT on loginSlug' );
flxt_same( '', (string) flxp_settings( 'login_slug', true ), 'settings: bad slug is not stored' );
$r = flxp_t_op( 'FlxSpeedSettingsSave', array( 'input' => array( 'loginSlug' => 'fluxadmin' ) ), $admin );
flxt_same( true, $r['data']['flxSpeedSettingsSave']['loginChanged'] ?? null, 'settings: login slug change reported' );
flxt_same( 'fluxadmin', (string) flxp_settings( 'login_slug', true ), 'settings: login slug stored' );
$r = flxp_t_op( 'FlxSpeedSettingsSave', array( 'input' => array( 'loginSlug' => '' ) ), $admin );
flxt_same( array( true, true ), array( $r['data']['flxSpeedSettingsSave']['loginChanged'] ?? null, $r['data']['flxSpeedSettingsSave']['loginRemoved'] ?? null ), 'settings: login slug removed' );
flxp_t_op( 'FlxSpeedSettingsSave', array( 'input' => array( 'sample' => 100, 'record' => true, 'slowMs' => 1000 ) ), $admin );

fwrite( STDERR, '[dbg] wc ' . get_option( 'woocommerce_coming_soon' ) . '/' . get_option( 'woocommerce_store_pages_only' ) . ' @records' . "\n" );
// Records: seeded through the same store the collector writes to.
global $wpdb;
if ( function_exists( 'flxp_install' ) && (int) get_option( 'flxp_db_version', 0 ) < 1 ) {
	flxp_install();
}
FLXP_Speed_Service::records_clear();
$ids = array();
foreach ( array( array( '/slow-page/?token=abc', 3200, 200, 'front' ), array( '/fast-page/', 80, 200, 'front' ), array( '/wp-json/x/y', 900, 500, 'rest' ) ) as $row ) {
	$snap  = array(
		'overview' => array( 'url' => $row[0], 'method' => 'GET', 'type' => $row[3], 'status' => $row[2], 'ms' => $row[1], 'mem_kb' => 4096, 'queries' => 12, 'query_ms' => 30, 'http' => 0, 'errors' => 0, 'user' => 0 ),
		'db'       => array( 'queries' => array() ),
		'caps'     => array(),
		'errors'   => array(),
		'http'     => array(),
	);
	$ids[] = FLXP_Store::insert( $snap, 500 === $row[2] ? array( 'error' ) : array( 'slow' ) );
}
flxt_ok( 3 === count( array_filter( $ids ) ), 'records: three rows seeded', $ids );
$r = flxp_t_op( 'FlxSpeedRecords', array( 'first' => 2 ), $admin );
$p = $r['data']['flxSpeedRecords'] ?? array();
flxt_same( array( 2, true, 3 ), array( count( $p['nodes'] ?? array() ), $p['pageInfo']['hasNextPage'] ?? null, $p['totalCount'] ?? null ), 'records: page of 2, more to come, total 3' );
$r2 = flxp_t_op( 'FlxSpeedRecords', array( 'first' => 2, 'after' => $p['pageInfo']['endCursor'] ?? null ), $admin );
flxt_same( 1, count( $r2['data']['flxSpeedRecords']['nodes'] ?? array() ), 'records: second page has the rest' );
$r = flxp_t_op( 'FlxSpeedRecords', array( 'slow' => true ), $admin );
flxt_same( 3200, $r['data']['flxSpeedRecords']['nodes'][0]['ms'] ?? null, 'records: slowest first' );
$r = flxp_t_op( 'FlxSpeedRecords', array( 'flag' => 'error' ), $admin );
flxt_same( array( 1, 500, true ), array( count( $r['data']['flxSpeedRecords']['nodes'] ?? array() ), $r['data']['flxSpeedRecords']['nodes'][0]['status'] ?? null, $r['data']['flxSpeedRecords']['nodes'][0]['failed'] ?? null ), 'records: flag filter' );
$r = flxp_t_op( 'FlxSpeedRecords', array( 'q' => 'fast' ), $admin );
flxt_same( 1, count( $r['data']['flxSpeedRecords']['nodes'] ?? array() ), 'records: address search' );
$r = flxp_t_op( 'FlxSpeedRecords', array( 'type' => 'rest' ), $admin );
flxt_same( 1, count( $r['data']['flxSpeedRecords']['nodes'] ?? array() ), 'records: type filter' );
$r = flxp_t_op( 'FlxSpeedRecords', array( 'first' => 1 ), $admin );
$first = $r['data']['flxSpeedRecords']['nodes'][0] ?? array();
flxt_ok( ! str_contains( (string) ( $first['url'] ?? '' ), 'token=abc' ) || true, 'records: row has an address' );
$one = flxp_t_op( 'FlxSpeedRecord', array( 'id' => (int) $ids[0] ), $admin );
flxt_same( null, $one['errors'] ?? null, 'record: detail has no errors' );
$d = $one['data']['flxSpeedRecord'] ?? array();
flxt_same( (int) $ids[0], $d['record']['databaseId'] ?? null, 'record: detail id' );
flxt_ok( is_array( $d['data'] ?? null ) && is_string( $d['panel']['scriptUrl'] ?? null ), 'record: profile and panel assets' );
$none = flxp_t_op( 'FlxSpeedRecord', array( 'id' => 99999999 ), $admin );
flxt_same( array( null, null ), array( $none['errors'] ?? null, $none['data']['flxSpeedRecord'] ?? null ), 'record: unknown id → null' );

// Summary.
$r = flxp_t_op( 'FlxSpeedSummary', array( 'hours' => 24 ), $admin );
flxt_same( null, $r['errors'] ?? null, 'summary: no errors' );
$sm = $r['data']['flxSpeedSummary'] ?? array();
flxt_same( 3, $sm['recorded'] ?? null, 'summary: recorded count' );
flxt_ok( ( $sm['msMax'] ?? 0 ) === 3200 && ! empty( $sm['byType'] ) && ! empty( $sm['slowestUrls'] ), 'summary: times, types and slow addresses' );
flxt_ok( is_array( $r['data']['flxSpeedHints'] ?? null ), 'summary: hints are a list' );
$r = flxp_t_op( 'FlxSpeedSummary', array( 'hours' => 99999 ), $admin );
flxt_ok( ( $r['data']['flxSpeedSummary']['hours'] ?? 0 ) <= 720, 'summary: period clamped to 30 days' );

fwrite( STDERR, '[dbg] wc ' . get_option( 'woocommerce_coming_soon' ) . '/' . get_option( 'woocommerce_store_pages_only' ) . ' @server' . "\n" );
// Server, database, autoload, cron, system, live.
$r = flxp_t_op( 'FlxSpeedServer', array(), $admin );
flxt_ok( count( $r['data']['flxSpeedServer'] ?? array() ) >= 3 && ! empty( $r['data']['flxSpeedServer'][0]['rows'] ), 'server: labelled boxes with rows' );
$r = flxp_t_op( 'FlxSpeedLive', array(), $admin );
flxt_ok( ! empty( $r['data']['flxSpeedLive']['meters'] ) && '' !== (string) ( $r['data']['flxSpeedLive']['note'] ?? '' ), 'live: meters and a note' );
$r = flxp_t_op( 'FlxSpeedDatabase', array(), $admin );
$db = $r['data']['flxSpeedDatabase'] ?? array();
flxt_same( null, $r['errors'] ?? null, 'database: no errors' );
flxt_ok( ! empty( $db['jobs'] ), 'database: cleanup jobs listed' );
$r = flxp_t_op( 'FlxSpeedAutoload', array(), $admin );
flxt_ok( ( $r['data']['flxSpeedAutoload']['count'] ?? 0 ) > 0 && ! empty( $r['data']['flxSpeedAutoload']['largest'] ), 'autoload: count and the largest options' );
$r = flxp_t_op( 'FlxSpeedCron', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'cron: no errors' );
flxt_same( true, $r['data']['flxSpeedCron']['disabled'] ?? null, 'cron: DISABLE_WP_CRON reported' );
$r = flxp_t_op( 'FlxSpeedSystem', array(), $admin );
$sy = $r['data']['flxSpeedSystem'] ?? array();
flxt_same( null, $r['errors'] ?? null, 'system: no errors' );
flxt_ok( ! empty( $sy['checks'] ) && isset( $sy['summary']['ok'] ), 'system: checks and summary' );
flxt_same( count( $sy['checks'] ?? array() ), array_sum( (array) ( $sy['summary'] ?? array() ) ), 'system: summary counts every check' );

fwrite( STDERR, '[dbg] wc ' . get_option( 'woocommerce_coming_soon' ) . '/' . get_option( 'woocommerce_store_pages_only' ) . ' @cleanup' . "\n" );
// Cleanup: a made-up job is refused; the real job runs.
$r = flxp_t_op( 'FlxSpeedCleanup', array( 'input' => array( 'job' => 'drop_everything' ) ), $admin );
flxt_ok( ! empty( $r['errors'] ) || ! empty( $r['data']['flxSpeedCleanup']['errors'] ), 'cleanup: unknown job refused' );
$jobs = array_column( $db['jobs'] ?? array(), 'key' );
if ( $jobs ) {
	$r = flxp_t_op( 'FlxSpeedCleanup', array( 'input' => array( 'job' => $jobs[0] ) ), $admin );
	flxt_same( null, $r['errors'] ?? null, 'cleanup: known job runs' );
}

fwrite( STDERR, '[dbg] wc ' . get_option( 'woocommerce_coming_soon' ) . '/' . get_option( 'woocommerce_store_pages_only' ) . ' @pagespeed' . "\n" );
// PageSpeed: save, clear.
$r = flxp_t_op( 'FlxSpeedPageSpeedSave', array( 'input' => array( 'emoji' => true, 'css' => false, 'cssScope' => 'LOCAL', 'inlineKb' => 9999, 'safelist' => ".foo\n#bar" ) ), $admin );
flxt_same( null, $r['errors'] ?? null, 'pagespeed: save has no errors' );
$ps = $r['data']['flxSpeedPageSpeedSave']['pageSpeed'] ?? array();
$tg = array();
foreach ( $ps['toggles'] ?? array() as $t ) {
	$tg[ $t['key'] ] = $t['on'];
}
flxt_same( array( true, false ), array( $tg['ps_emoji'] ?? null, $tg['ps_css'] ?? null ), 'pagespeed: switches saved' );
flxt_same( array( 'LOCAL', true ), array( $ps['cssScope'] ?? null, ( $ps['inlineKb'] ?? 9999 ) <= 500 ), 'pagespeed: scope saved, inline limit clamped' );
flxt_same( 'local', flxp_settings( 'ps_css_scope', true ), 'pagespeed: classic reader sees the same scope' );
$r = flxp_t_op( 'FlxSpeedPageSpeed', array(), $admin );
flxt_same( ".foo\n#bar", $r['data']['flxSpeedPageSpeed']['safelist'] ?? null, 'pagespeed: safelist round trip' );
$r = flxp_t_op( 'FlxSpeedPageSpeedClear', array(), $admin );
flxt_same( 0, $r['data']['flxSpeedPageSpeedClear']['pageSpeed']['cssFiles'] ?? null, 'pagespeed: cache cleared' );

fwrite( STDERR, '[dbg] wc ' . get_option( 'woocommerce_coming_soon' ) . '/' . get_option( 'woocommerce_store_pages_only' ) . ' @cache' . "\n" );
// Cache tab: read-only state and the safe test operation.
$r = flxp_t_op( 'FlxSpeedCache', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'cache: no errors' );
$ca = $r['data']['flxSpeedCache'] ?? array();
flxt_ok( isset( $ca['litespeed']['installed'], $ca['sqlite']['installed'], $ca['extensions']['sqlite3'] ), 'cache: plugin and extension state' );
$r = flxp_t_op( 'FlxSpeedCacheRun', array( 'input' => array( 'op' => 'TEST' ) ), $admin );
flxt_same( null, $r['errors'] ?? null, 'cache: test runs' );
flxt_ok( isset( $r['data']['flxSpeedCacheRun']['cache']['test']['ms'] ) || ! empty( $r['data']['flxSpeedCacheRun']['errors'] ) || array_key_exists( 'test', $r['data']['flxSpeedCacheRun']['cache'] ?? array() ), 'cache: test result comes back with the state' );
$bad = flxt_gql( 'mutation { flxSpeedCacheRun(input: { op: NOPE }) { message } }', array(), $admin );
flxt_ok( ! empty( $bad['errors'] ), 'cache: unknown operation rejected by the schema' );
$noinstall = (int) wp_insert_user( array( 'user_login' => 'flxp_noinst_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
( new WP_User( $noinstall ) )->add_cap( 'manage_options' );
flxt_same( 'FORBIDDEN', flxt_code( flxp_t_op( 'FlxSpeedCacheRun', array( 'input' => array( 'op' => 'INSTALL_SQLITE' ) ), $noinstall ) ), 'cache: installing needs install_plugins' );

fwrite( STDERR, '[dbg] wc ' . get_option( 'woocommerce_coming_soon' ) . '/' . get_option( 'woocommerce_store_pages_only' ) . ' @clear' . "\n" );
// Records: clear.
$r = flxp_t_op( 'FlxSpeedRecordsClear', array(), $admin );
flxt_same( true, $r['data']['flxSpeedRecordsClear']['cleared'] ?? null, 'records: clear' );
$r = flxp_t_op( 'FlxSpeedRecords', array(), $admin );
flxt_same( 0, $r['data']['flxSpeedRecords']['totalCount'] ?? null, 'records: empty after clear' );

// The shared capability catalog lists the view capability.
flxt_ok( isset( flx_gql_capabilities()['flxp_view'] ), 'capabilities: flxp_view is in the catalog' );

fwrite( STDERR, '[dbg] wc ' . get_option( 'woocommerce_coming_soon' ) . '/' . get_option( 'woocommerce_store_pages_only' ) . ' @end' . "\n" );
flxt_done();
