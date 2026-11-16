<?php
/**
 * Flux SEO GraphQL contract tests. The documents are the persisted operations the SEO screen sends
 * (plugins/flx-seo/graphql/persisted-queries.json); the content fields and the content mutation are checked
 * with free documents. The classic tabs and GraphQL share the services (FLXSEO_Audit_Service …).
 *
 *   wp eval-file tools/tests/wp-graphql/flx-seo/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux SEO GraphQL\n";

$GLOBALS['flxs_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXSEO_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxs_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 9, count( $GLOBALS['flxs_ops'] ), 'Flux SEO ships 9 persisted operations' );

function flxs_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxs_ops'][ $name ], $vars, $user );
}

function flxs_user( string $role, string $name ): int {
	return (int) wp_insert_user( array( 'user_login' => 'flxs_' . $name . wp_rand(), 'user_email' => 'flxs_' . $name . wp_rand() . '@example.com', 'user_pass' => wp_generate_password(), 'role' => $role ) );
}

function flxs_finding( array $audit, string $id ): array {
	foreach ( $audit['findings'] ?? array() as $f ) {
		if ( ( $f['id'] ?? '' ) === $id ) {
			return $f;
		}
	}
	return array();
}

$admin = 1;
$sub   = flxs_user( 'subscriber', 'sub' );
$edit  = flxs_user( 'editor', 'edit' );
$auth  = flxs_user( 'author', 'auth' );

// --- Permissions: every screen operation is manage_options. ---
$screen_ops = array( 'FlxSeoSettingsScreen', 'FlxSeoAuditScreen', 'FlxSeoContentCheck' );
foreach ( $screen_ops as $op ) {
	flxt_same( 'UNAUTHENTICATED', flxt_code( flxs_op( $op ) ), "$op: visitor → UNAUTHENTICATED" );
	flxt_same( 'FORBIDDEN', flxt_code( flxs_op( $op, array(), $sub ) ), "$op: subscriber → FORBIDDEN" );
	flxt_same( 'FORBIDDEN', flxt_code( flxs_op( $op, array(), $edit ) ), "$op: editor → FORBIDDEN" );
	flxt_same( null, flxs_op( $op, array(), $admin )['errors'] ?? null, "$op: administrator → no errors" );
}
flxt_same( 'FORBIDDEN', flxt_code( flxs_op( 'FlxSeoSettingsSave', array( 'flxseo' => array( 'siteName' => 'x' ) ), $edit ) ), 'settings save: editor → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxs_op( 'FlxSeoAuditRun', array( 'input' => array( 'mode' => 'LOCAL' ) ), $edit ) ), 'audit run: editor → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxs_op( 'FlxSeoFixApply', array( 'input' => array( 'id' => 'flxseo_llms' ) ), $edit ) ), 'fix apply: editor → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxs_op( 'FlxSeoPsiKeySave', array( 'input' => array( 'key' => 'abc' ) ), $edit ) ), 'psi key: editor → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxs_op( 'FlxSeoIndexNowSubmit', array( 'input' => array() ), $edit ) ), 'indexnow: editor → FORBIDDEN' );
flxt_same( 'UNAUTHENTICATED', flxt_code( flxs_op( 'FlxSeoGuide' ) ), 'guide: visitor → UNAUTHENTICATED' );
flxt_same( 'FORBIDDEN', flxt_code( flxs_op( 'FlxSeoGuide', array(), $sub ) ), 'guide: subscriber → FORBIDDEN' );

// --- Settings screen. ---
delete_option( 'flxseo' );
$r = flxs_op( 'FlxSeoSettingsScreen', array(), $admin );
$s = $r['data']['flxSettings']['flxseo'] ?? array();
$u = $r['data']['flxSeoSetup'] ?? array();
flxt_same( true, $s['enabled'] ?? null, 'settings: SEO output on by default' );
flxt_same( '–', $s['sep'] ?? null, 'settings: separator default' );
flxt_same( 'ORGANIZATION', $s['orgType'] ?? null, 'settings: organization type enum' );
flxt_same( 'DAILY', $s['auditFreq'] ?? null, 'settings: audit frequency enum' );
flxt_same( array( 'yes', 'yes', 'no' ), array( $s['csSearch'] ?? null, $s['csAiInput'] ?? null, $s['csAiTrain'] ?? null ), 'settings: content signals' );
flxt_same( '3', $s['auditHour'] ?? null, 'settings: audit hour' );
flxt_same( true, $s['indexnow'] ?? null, 'settings: IndexNow flag is a plain on/off (not a secret)' );
flxt_same( 6, count( $u['engines'] ?? array() ), 'setup: six search engines' );
flxt_same( array( 'verify_google', 'verifyGoogle' ), array( $u['engines'][0]['key'] ?? null, $u['engines'][0]['field'] ?? null ), 'setup: engine key and settings field' );
flxt_same( home_url( '/wp-sitemap.xml' ), $u['sitemapUrl'] ?? null, 'setup: sitemap address' );
flxt_ok( 1 === preg_match( '/^[a-f0-9]{32}$/', (string) ( $u['indexnowKey'] ?? '' ) ) && str_ends_with( (string) ( $u['indexnowUrl'] ?? '' ), '.txt' ), 'setup: IndexNow key file' );
flxt_same( false, $u['psiKeySaved'] ?? null, 'setup: no PageSpeed key yet' );
flxt_same( false, $u['otherPlugin'] ?? null, 'setup: no other SEO plugin' );
flxt_ok( count( $u['guideSections'] ?? array() ) > 3, 'setup: guide sections listed' );
flxt_ok( ! str_contains( (string) wp_json_encode( $r ), 'flxseo_psi_key' ), 'setup: key option name is not in the answer' );

// --- Settings save: partial, validated like the classic form. ---
$r = flxs_op(
	'FlxSeoSettingsSave',
	array(
		'flxseo' => array(
			'siteName'     => 'Test Org <b>',
			'orgType'      => 'NGO',
			'country'      => 'tr',
			'verifyGoogle' => 'abc123XYZ',
			'noindexTags'  => false,
			'csAiTrain'    => 'yes',
			'auditFreq'    => 'WEEKLY',
			'auditDay'     => '7',
			'auditMailTo'  => 'not-an-email',
		),
	),
	$admin
);
$p = $r['data']['flxSettingsUpdate']['settings']['flxseo'] ?? array();
flxt_same( null, $r['errors'] ?? null, 'settings save: no errors' );
flxt_same( 'Test Org', $p['siteName'] ?? null, 'settings save: tags stripped' );
flxt_same( 'NGO', $p['orgType'] ?? null, 'settings save: enum' );
flxt_same( 'TR', $p['country'] ?? null, 'settings save: country upper-cased' );
flxt_same( 'abc123XYZ', $p['verifyGoogle'] ?? null, 'settings save: verification value' );
flxt_same( 'abc123XYZ', flxseo_sanitize( array( 'verify_google' => '<meta name="google-site-verification" content="abc123XYZ" />' ) )['verify_google'] ?? null, 'a pasted meta tag becomes its content value (classic form, any option write)' );
flxt_same( false, $p['noindexTags'] ?? null, 'settings save: switch off' );
flxt_same( 'yes', $p['csAiTrain'] ?? null, 'settings save: content signal' );
flxt_same( 'WEEKLY', $p['auditFreq'] ?? null, 'settings save: audit frequency' );
flxt_same( '7', $p['auditDay'] ?? null, 'settings save: audit day' );
$r9 = flxs_op( 'FlxSeoSettingsSave', array( 'flxseo' => array( 'auditDay' => '9' ) ), $admin );
flxt_same( 'INVALID_INPUT', $r9['data']['flxSettingsUpdate']['errors'][0]['code'] ?? null, 'settings save: a day outside 1–7 is refused' );
flxt_same( '', $p['auditMailTo'] ?? null, 'settings save: invalid email dropped' );
flxt_same( true, $p['noindexShop'] ?? null, 'settings save: fields not sent stay as they were' );
$o = get_option( 'flxseo' );
flxt_same( array( 1, 0 ), array( $o['enabled'] ?? null, $o['noindex_tags'] ?? null ), 'settings save: stored as 1/0 like the classic form' );
update_option( 'flxseo', array( 'country' => 'Turkey', 'audit_freq' => 'hourly', 'cs_search' => 'maybe' ) );
$o = get_option( 'flxseo' );
flxt_same( array( '', 'daily', 'yes', 1 ), array( $o['country'] ?? null, $o['audit_freq'] ?? null, $o['cs_search'] ?? null, $o['enabled'] ?? null ), 'any write of the option is validated (presets, MCP, code)' );
$r = flxs_op( 'FlxSeoSettingsSave', array( 'flxseo' => array( 'orgType' => 'COMPANY' ) ), $admin );
flxt_ok( ! empty( $r['errors'] ) || ! empty( $r['data']['flxSettingsUpdate']['errors'] ), 'settings save: unknown enum value refused' );
update_option( 'flxseo', flxseo_defaults() );

// --- Audit report mapping (seeded store). ---
delete_option( 'flxseo_audit' );
delete_option( 'flxseo_audit_history' );
delete_option( 'flxseo_audit_job' );
$r = flxs_op( 'FlxSeoAuditScreen', array(), $admin );
$a = $r['data']['flxSeoAudit'] ?? array();
flxt_same( array( 0, 0, 0, 0 ), array_values( $a['counts'] ?? array( 1 ) ), 'audit: counts zero before the first audit' );
flxt_same( null, array_key_exists( 'local', $a ) ? $a['local'] : 'x', 'audit: no local run yet' );
flxt_same( array(), $a['findings'] ?? null, 'audit: no findings yet' );
flxt_same( array(), $a['pageSpeed'] ?? null, 'audit: no PageSpeed run yet' );
flxt_ok( count( $a['agentic'] ?? array() ) >= 7, 'audit: agent readiness checks listed' );
flxt_same( home_url( '/llms.txt' ), $a['llmsUrl'] ?? null, 'audit: llms.txt address' );
flxt_same( array( 0, false, null ), array( $r['data']['flxSeoAuditHistory']['total'] ?? null, $r['data']['flxSeoAuditHistory']['running'] ?? null, null ), 'history: empty, not running' );

update_option(
	'flxseo_audit',
	array(
		'local' => array(
			'time'     => time() - 90,
			'url'      => home_url( '/' ),
			'ms'       => 321,
			'code'     => 200,
			'findings' => array(
				flxseo_audit_finding( 'llms-txt', 'medium', array( 'a', 'b' ), 'detail text' ),
				flxseo_audit_finding( 'heading-h1', 'critical' ),
				flxseo_audit_finding( 'tabindex', 'low' ),
			),
		),
		'psi'   => array(
			'mobile' => array(
				'strategy'   => 'mobile',
				'time'       => time() - 60,
				'url'        => home_url( '/' ),
				'lighthouse' => '12.0.0',
				'scores'     => array( 'performance' => array( 'title' => 'Performance', 'score' => 87, 'passed' => 20, 'total' => 30 ), 'seo' => array( 'title' => 'SEO', 'score' => null, 'passed' => 0, 'total' => 0 ) ),
				'metrics'    => array( 'largest-contentful-paint' => array( 'title' => 'LCP', 'value' => '2.1 s', 'score' => 0.8 ) ),
				'findings'   => array( flxseo_audit_finding( 'heading-h1', 'high', array(), '', 'psi-mobile' ) ),
			),
		),
	),
	false
);
update_option( 'flxseo', array( 'llms' => 0 ) );
$r = flxs_op( 'FlxSeoAuditScreen', array(), $admin );
$a = $r['data']['flxSeoAudit'] ?? array();
flxt_same( array( 1, 0, 1, 1 ), array_values( $a['counts'] ?? array() ), 'audit: counts by priority (the higher priority of a merged finding wins)' );
flxt_same( array( 'heading-h1', 'llms-txt', 'tabindex' ), array_column( $a['findings'] ?? array(), 'id' ), 'audit: findings, most important first' );
$f = flxs_finding( $a, 'llms-txt' );
flxt_same( array( 'MEDIUM', 'agentic', 'detail text', array( 'a', 'b' ), array( 'LOCAL' ), 'flxseo_llms' ), array( $f['priority'] ?? null, $f['category'] ?? null, $f['detail'] ?? null, $f['items'] ?? null, $f['sources'] ?? null, $f['fixId'] ?? null ), 'audit: finding fields and one-click fix' );
flxt_ok( '' !== ( $f['fixLabel'] ?? '' ) && '' !== ( $f['fix'] ?? '' ) && '' !== ( $f['ownerLabel'] ?? '' ), 'audit: finding fix text, owner and fix label' );
$h1 = flxs_finding( $a, 'heading-h1' );
flxt_same( array( 'CRITICAL', array( 'LOCAL', 'PSI_MOBILE' ), array( 'accessibility' ) ), array( $h1['priority'] ?? null, $h1['sources'] ?? null, $h1['also'] ?? null ), 'audit: merged sources and extra sections' );
flxt_same( array( 321, 200 ), array( $a['local']['durationMs'] ?? null, $a['local']['httpStatus'] ?? null ), 'audit: local run' );
$ps = $a['pageSpeed'][0] ?? array();
flxt_same( array( 'MOBILE', '12.0.0', 87, null ), array( $ps['strategy'] ?? null, $ps['lighthouse'] ?? null, $ps['scores'][0]['score'] ?? null, array_key_exists( 'score', $ps['scores'][1] ?? array() ) ? $ps['scores'][1]['score'] : 'x' ), 'audit: PageSpeed run with a null score' );
flxt_same( array( 'largest-contentful-paint', '2.1 s' ), array( $ps['metrics'][0]['key'] ?? null, $ps['metrics'][0]['value'] ?? null ), 'audit: PageSpeed metric' );
flxt_ok( ! empty( $a['local']['time']['label'] ) && ! empty( $a['local']['time']['relative'] ), 'audit: times are FlxDateTime (label, relative)' );

// One-click fix.
$r = flxs_op( 'FlxSeoFixApply', array( 'input' => array( 'id' => 'flxseo_llms' ) ), $admin );
flxt_same( array(), $r['data']['flxSeoFixApply']['errors'] ?? null, 'fix: no errors' );
flxt_same( 1, (int) get_option( 'flxseo' )['llms'], 'fix: the setting is on' );
flxt_same( 'flxseo_llms', get_option( 'flxseo_system_fix_log' )[0]['fix'] ?? null, 'fix: logged in the change log' );
flxt_same( 'graphql', get_option( 'flxseo_system_fix_log' )[0]['via'] ?? null, 'fix: logged as coming from GraphQL' );
$r = flxs_op( 'FlxSeoFixApply', array( 'input' => array( 'id' => 'not_a_fix' ) ), $admin );
flxt_same( 'INVALID_INPUT', $r['data']['flxSeoFixApply']['errors'][0]['code'] ?? null, 'fix: unknown fix → INVALID_INPUT' );
flxt_same( 'id', $r['data']['flxSeoFixApply']['errors'][0]['field'] ?? null, 'fix: the error names the field' );

// --- History. ---
update_option(
	'flxseo_audit_history',
	array(
		array(
			't'       => time() - 100,
			'trigger' => 'fix',
			'url'     => home_url( '/' ),
			'counts'  => array( 'critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3 ),
			'scores'  => array( 'mobile' => array( 'performance' => 90, 'seo' => null ) ),
			'metrics' => array( 'mobile' => array( 'fcp' => '1 s', 'lcp' => '2 s', 'tbt' => '3 ms', 'cls' => '0', 'si' => '4 s' ) ),
			'agentic' => '5/7',
			'items'   => array( array( 'llms-txt', 'medium', 'No llms.txt' ), array( 'zzz-unknown', 'low', 'Old finding' ) ),
			'new'     => array( 'zzz-unknown' ),
			'fixed'   => array( 'llms-txt-format' ),
		),
		array( 't' => time() - 5000, 'trigger' => 'auto', 'url' => home_url( '/' ), 'counts' => array( 'critical' => 1, 'high' => 0, 'medium' => 0, 'low' => 0 ), 'scores' => array(), 'metrics' => array(), 'items' => array(), 'new' => array(), 'fixed' => array() ),
	),
	false
);
$r = flxs_op( 'FlxSeoAuditScreen', array( 'history' => 1 ), $admin );
$h = $r['data']['flxSeoAuditHistory'] ?? array();
flxt_same( array( 2, 1 ), array( $h['total'] ?? null, count( $h['entries'] ?? array() ) ), 'history: limit applies, total counts all' );
$e = $h['entries'][0] ?? array();
flxt_same( array( 'FIX', 0, 1, 2, 3, '5/7' ), array( $e['trigger'] ?? null, $e['counts']['critical'] ?? null, $e['counts']['high'] ?? null, $e['counts']['medium'] ?? null, $e['counts']['low'] ?? null, $e['agentic'] ?? null ), 'history: entry' );
flxt_same( array( 'MOBILE', 'performance', 90 ), array( $e['scores'][0]['strategy'] ?? null, $e['scores'][0]['category'] ?? null, $e['scores'][0]['score'] ?? null ), 'history: scores' );
flxt_same( array( 'MOBILE', '2 s' ), array( $e['metrics'][0]['strategy'] ?? null, $e['metrics'][0]['lcp'] ?? null ), 'history: metrics' );
flxt_same( array( 'zzz-unknown', 'Old finding' ), array( $e['added'][0]['id'] ?? null, $e['added'][0]['title'] ?? null ), 'history: new recommendation keeps its saved title' );
flxt_same( 'llms-txt-format', $e['resolved'][0]['id'] ?? null, 'history: resolved recommendation' );
flxt_ok( '' !== ( $e['resolved'][0]['title'] ?? '' ) && 'llms-txt-format' !== ( $e['resolved'][0]['title'] ?? '' ), 'history: resolved title comes from the catalog' );
flxt_ok( '' !== ( $e['triggerLabel'] ?? '' ), 'history: trigger label' );
$r = flxs_op( 'FlxSeoAuditScreen', array( 'history' => 500 ), $admin );
flxt_same( 2, count( $r['data']['flxSeoAuditHistory']['entries'] ?? array() ), 'history: a large limit is clamped to what exists' );
update_option( 'flxseo_audit_job', array( 'steps' => array( 'local' ), 'started' => time(), 'errors' => array(), 'token' => 'x' ), false );
update_option( 'flxseo_audit_last_errors', array( 'mobile: timed out' ), false );
$r = flxs_op( 'FlxSeoAuditScreen', array(), $admin );
flxt_same( array( true, array( 'mobile: timed out' ) ), array( $r['data']['flxSeoAuditHistory']['running'] ?? null, $r['data']['flxSeoAuditHistory']['errors'] ?? null ), 'history: running job and last errors' );
delete_option( 'flxseo_audit_job' );
delete_option( 'flxseo_audit_last_errors' );

// --- Running audits. ---
delete_option( 'flxseo_audit' );
delete_option( 'flxseo_audit_history' );
$r = flxs_op( 'FlxSeoAuditRun', array( 'input' => array( 'mode' => 'PAGESPEED', 'strategy' => 'MOBILE' ) ), $admin );
flxt_same( 'INVALID_INPUT', $r['data']['flxSeoAuditRun']['errors'][0]['code'] ?? null, 'run: PageSpeed refuses an address that is not reachable from the internet' );
$r = flxs_op( 'FlxSeoAuditRun', array( 'input' => array( 'mode' => 'LOCAL', 'url' => 'https://elsewhere.example/page' ) ), $admin );
$run = $r['data']['flxSeoAuditRun'] ?? array();
if ( ! empty( $run['audit']['local'] ) ) {
	flxt_same( home_url( '/' ), $run['audit']['local']['url'] ?? null, 'run: an address of another site audits the home page' );
	flxt_ok( ( $run['audit']['local']['time']['timestamp'] ?? 0 ) >= time() - 30 && ( $run['history']['total'] ?? 0 ) >= 1, 'run: the on-site audit is saved and the history grows' );
} else {
	flxt_ok( 'INVALID_INPUT' === ( $run['errors'][0]['code'] ?? '' ), 'run: the on-site audit answers with INVALID_INPUT when the page is not reachable', $run );
}

// --- PageSpeed key: saved, never read back. ---
$r = flxs_op( 'FlxSeoPsiKeySave', array( 'input' => array( 'key' => 'AIza-test-key' ) ), $admin );
flxt_same( true, $r['data']['flxSeoPsiKeySave']['saved'] ?? null, 'psi key: saved' );
flxt_same( 'AIza-test-key', flxseo_psi_key(), 'psi key: stored for the audit' );
$r = flxs_op( 'FlxSeoSettingsScreen', array(), $admin );
flxt_same( true, $r['data']['flxSeoSetup']['psiKeySaved'] ?? null, 'psi key: setup says a key is saved' );
flxt_ok( ! str_contains( (string) wp_json_encode( $r ), 'AIza-test-key' ), 'psi key: never returned' );
$r = flxs_op( 'FlxSeoPsiKeySave', array( 'input' => array( 'key' => '' ) ), $admin );
flxt_same( 'AIza-test-key', flxseo_psi_key(), 'psi key: an empty key keeps the saved one' );
$r = flxs_op( 'FlxSeoPsiKeySave', array( 'input' => array( 'clear' => true ) ), $admin );
flxt_same( array( false, '' ), array( $r['data']['flxSeoPsiKeySave']['saved'] ?? null, flxseo_psi_key() ), 'psi key: deleted' );

// --- IndexNow: not on a local test site. ---
$r = flxs_op( 'FlxSeoIndexNowSubmit', array( 'input' => array() ), $admin );
flxt_same( 'INVALID_INPUT', $r['data']['flxSeoIndexNowSubmit']['errors'][0]['code'] ?? null, 'indexnow: refused when it cannot send (local site)' );

// --- Content check (SEO check tab) and the service. ---
$short = (int) wp_insert_post( array( 'post_title' => 'Hi', 'post_content' => '<h1>Big</h1><p>Short.</p><img src="x.jpg">', 'post_status' => 'publish', 'post_type' => 'post' ) );
$good  = (int) wp_insert_post( array( 'post_title' => 'A reasonable page title here', 'post_content' => '<p>' . str_repeat( 'word ', 400 ) . '</p>', 'post_status' => 'publish', 'post_type' => 'page' ) );
update_post_meta( $good, '_flxseo_desc', str_repeat( 'd', 120 ) );
$r  = flxs_op( 'FlxSeoContentCheck', array(), $admin );
$cc = $r['data']['flxSeoContentCheck'] ?? array();
flxt_ok( ( $cc['checked'] ?? 0 ) >= 2, 'content check: published items are checked' );
$row = array();
foreach ( $cc['items'] ?? array() as $it ) {
	if ( ( $it['databaseId'] ?? 0 ) === $short ) {
		$row = $it;
	}
}
$codes = array_column( $row['issues'] ?? array(), 'code' );
flxt_ok( in_array( 'h1', $codes, true ) && in_array( 'desc_missing', $codes, true ) && in_array( 'img_alt', $codes, true ), 'content check: the codes of a weak post', $codes );
$levels = array_column( $row['issues'] ?? array(), 'level', 'code' );
flxt_same( 'ERROR', $levels['h1'] ?? null, 'content check: an H1 inside post content is an error' );
flxt_same( 'WARNING', $levels['desc_missing'] ?? null, 'content check: a missing description is a warning' );
flxt_ok( str_contains( (string) ( $row['editUrl'] ?? '' ), 'post.php' ) && 'post' === ( $row['postType'] ?? '' ), 'content check: edit address and type' );
flxt_ok( '' !== ( $row['issues'][0]['message'] ?? '' ) && ! preg_match( '/[ığşçöüİĞŞÇÖÜ]/u', (string) ( $row['issues'][0]['message'] ?? '' ) ), 'content check: messages are translatable English source, not hardcoded Turkish' );
$gids = array_column( $cc['items'] ?? array(), 'databaseId' );
flxt_ok( ! in_array( $good, $gids, true ) || count( array_filter( $cc['items'], static fn( $i ) => ( $i['databaseId'] ?? 0 ) === $good && in_array( 'desc_missing', array_column( $i['issues'], 'code' ), true ) ) ) === 0, 'content check: a page with its own description does not get desc_missing' );
$svc = FLXSEO_Audit_Service::content_check( 'page', 10 );
flxt_ok( 0 === count( array_filter( $svc->items, static fn( $i ) => 'post' === $i->type ) ), 'service: post type filter' );

// --- Guide. ---
$r = flxs_op( 'FlxSeoGuide', array( 'lang' => 'EN' ), $edit );
$g = $r['data']['flxSeoGuide'] ?? array();
flxt_same( null, $r['errors'] ?? null, 'guide: an editor may read it' );
flxt_same( 'EN', $g['lang'] ?? null, 'guide: language' );
flxt_ok( str_starts_with( (string) ( $g['markdown'] ?? '' ), '#' ) && count( $g['sections'] ?? array() ) > 3, 'guide: Markdown and sections' );
$r = flxs_op( 'FlxSeoGuide', array( 'lang' => 'TR' ), $admin );
flxt_same( 'TR', $r['data']['flxSeoGuide']['lang'] ?? null, 'guide: Turkish' );
flxt_ok( ( $r['data']['flxSeoGuide']['markdown'] ?? '' ) !== $g['markdown'], 'guide: the Turkish text differs' );

// --- flxSeo on content and terms. ---
update_option( 'flxseo', array( 'sep' => '|', 'alt_name' => 'Site' ) );
update_post_meta( $good, '_flxseo_title', 'Own title' );
update_post_meta( $good, '_flxseo_focus', 'secret keyword' );
update_post_meta( $good, '_flxseo_noindex', true );
$q = 'query($id: ID!) { page(id: $id, idType: DATABASE_ID) { flxSeo { title description noindex ogImage effectiveTitle effectiveDescription } } post(id: ' . $short . ', idType: DATABASE_ID) { flxSeo { title effectiveTitle } } }';
$r = flxt_gql( $q, array( 'id' => $good ), 0 );
$d = $r['data']['page']['flxSeo'] ?? array();
flxt_same( array( 'Own title', true, str_repeat( 'd', 120 ) ), array( $d['effectiveTitle'] ?? null, $d['noindex'] ?? null, $d['description'] ?? null ), 'flxSeo: public data of the page head, readable by a visitor' );
$r = flxt_gql( 'query($id: ID!) { page(id: $id, idType: DATABASE_ID) { flxSeo { focusKeyword } } }', array( 'id' => $good ), 0 );
flxt_same( 'FORBIDDEN', 'UNAUTHENTICATED' === flxt_code( $r ) ? 'FORBIDDEN' : flxt_code( $r ), 'flxSeo: asking a visitor for the focus keyword is an error' );
$r = flxt_gql( 'query($id: ID!) { page(id: $id, idType: DATABASE_ID) { flxSeo { focusKeyword } } }', array( 'id' => $good ), $edit );
flxt_same( 'secret keyword', $r['data']['page']['flxSeo']['focusKeyword'] ?? null, 'flxSeo: an editor reads the focus keyword' );
$r = flxt_gql( 'query { post(id: ' . $short . ', idType: DATABASE_ID) { flxSeo { title effectiveTitle } } }', array(), 0 );
flxt_same( array( '', 'Hi | Site' ), array( $r['data']['post']['flxSeo']['title'] ?? null, $r['data']['post']['flxSeo']['effectiveTitle'] ?? null ), 'flxSeo: automatic title uses the separator and site name' );
$cat = (int) wp_insert_term( 'Seo cat ' . wp_rand(), 'category', array( 'description' => 'About the category.' ) )['term_id'];
update_term_meta( $cat, '_flxseo_title', 'Cat SEO title' );
$r = flxt_gql( 'query($id: ID!) { category(id: $id, idType: DATABASE_ID) { flxSeo { title effectiveTitle effectiveDescription } } }', array( 'id' => $cat ), 0 );
flxt_same( array( 'Cat SEO title', 'About the category.' ), array( $r['data']['category']['flxSeo']['effectiveTitle'] ?? null, $r['data']['category']['flxSeo']['effectiveDescription'] ?? null ), 'flxSeo: terms (own title, description from the term)' );

// --- flxSeoContentSet. ---
$m = 'mutation($i: FlxSeoContentSetInput!) { flxSeoContentSet(input: $i) { seo { title description focusKeyword noindex ogImage effectiveTitle } errors { code message field } } }';
$own = (int) wp_insert_post( array( 'post_title' => 'Author post', 'post_status' => 'publish', 'post_author' => $auth ) );
$r   = flxt_gql( $m, array( 'i' => array( 'databaseId' => $own, 'title' => 'New <i>title</i>', 'description' => 'Desc', 'focusKeyword' => 'kw', 'noindex' => true, 'ogImage' => 'https://example.com/a.jpg' ) ), $auth );
$s   = $r['data']['flxSeoContentSet']['seo'] ?? array();
flxt_same( array( 'New title', 'Desc', 'kw', true, 'https://example.com/a.jpg' ), array( $s['title'] ?? null, $s['description'] ?? null, $s['focusKeyword'] ?? null, $s['noindex'] ?? null, $s['ogImage'] ?? null ), 'content set: the author saves SEO fields of their own post' );
flxt_same( 'New title', get_post_meta( $own, '_flxseo_title', true ), 'content set: stored in the post meta the editor panel uses' );
$r = flxt_gql( $m, array( 'i' => array( 'databaseId' => $own, 'noindex' => false ) ), $auth );
flxt_same( array( 'New title', false ), array( $r['data']['flxSeoContentSet']['seo']['title'] ?? null, $r['data']['flxSeoContentSet']['seo']['noindex'] ?? null ), 'content set: fields that are left out stay' );
$r = flxt_gql( $m, array( 'i' => array( 'databaseId' => $good, 'title' => 'Hack' ) ), $auth );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'content set: an author cannot change another user\'s content' );
flxt_same( 'Own title', get_post_meta( $good, '_flxseo_title', true ), 'content set: nothing was written' );
$r = flxt_gql( $m, array( 'i' => array( 'databaseId' => 99999999, 'title' => 'x' ) ), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'content set: unknown post → NOT_FOUND' );
$r = flxt_gql( $m, array( 'i' => array( 'databaseId' => $own, 'title' => 'x' ) ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'content set: a subscriber is refused' );
$r = flxt_gql( $m, array( 'i' => array( 'databaseId' => $own, 'title' => 'x' ) ), 0 );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'content set: a visitor is refused' );

// --- Classic handlers and the service agree. ---
flxt_same( count( FLXSEO_Audit_Service::audit()->findings ), count( flxseo_audit_report()['findings'] ), 'service: the audit lists what the legacy report lists' );
flxt_same( flxseo_audit_report()['counts']['critical'], FLXSEO_Audit_Service::audit()->counts->critical, 'service: counts' );

// Clean up what the test created.
foreach ( array( $short, $good, $own ) as $pid ) {
	wp_delete_post( $pid, true );
}
wp_delete_term( $cat, 'category' );
foreach ( array( 'flxseo', 'flxseo_audit', 'flxseo_audit_history', 'flxseo_system_fix_log' ) as $opt ) {
	delete_option( $opt );
}

flxt_done();
