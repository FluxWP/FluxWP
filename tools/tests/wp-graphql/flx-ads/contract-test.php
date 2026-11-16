<?php
/**
 * Flux Ads GraphQL contract tests. The documents are the persisted operations the Ad conversions screen
 * sends (plugins/flx-ads/graphql/persisted-queries.json). Nothing here reaches an ad platform: every HTTP
 * request is blocked, and the tests check that none was attempted for the paths that must stay local.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-ads/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux Ads GraphQL\n";

$GLOBALS['flxads_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXADS_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxads_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 44, count( $GLOBALS['flxads_ops'] ), 'Flux Ads ships 44 persisted operations' );

function flxads_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxads_ops'][ $name ], $vars, $user );
}

// No real ad account is touched: count and block every outgoing request.
$GLOBALS['flxads_http'] = array();
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		$GLOBALS['flxads_http'][] = $url;
		return new WP_Error( 'flxads_blocked', 'Blocked in tests.' );
	},
	1,
	3
);

$admin = 1;
$sub   = (int) wp_insert_user( array( 'user_login' => 'flxads_sub_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$shop  = (int) wp_insert_user( array( 'user_login' => 'flxads_shop_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
( new WP_User( $shop ) )->add_cap( 'flxads_view' ); // May look, not change.

// Permissions.
$r = flxads_t_op( 'FlxAdsContext' );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'context: visitor → UNAUTHENTICATED' );
$r = flxads_t_op( 'FlxAdsContext', array(), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'context: subscriber → FORBIDDEN' );
$r = flxads_t_op( 'FlxAdsContext', array(), $shop );
flxt_same( null, $r['errors'] ?? null, 'context: viewer has no errors' );
flxt_same( false, $r['data']['flxAdsContext']['canManage'] ?? null, 'context: viewer cannot manage' );
flxt_ok( ! in_array( 'settings', $r['data']['flxAdsContext']['tabs'] ?? array(), true ), 'context: no Settings tab for the viewer' );
$r = flxads_t_op( 'FlxAdsContext', array(), $admin );
flxt_same( true, $r['data']['flxAdsContext']['canManage'] ?? null, 'context: administrator manages' );
flxt_same( 'settings', ( $r['data']['flxAdsContext']['tabs'] ?? array( '' ) )[ count( $r['data']['flxAdsContext']['tabs'] ?? array( '' ) ) - 1 ], 'context: Settings is the last tab' );
flxt_same( FLXADS_VERSION, $r['data']['flxAdsContext']['version'] ?? null, 'context: version' );

$r = flxads_t_op( 'FlxAdsSettings', array(), $shop );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'settings: viewer → FORBIDDEN' );
$r = flxads_t_op( 'FlxAdsSettingsSave', array( 'input' => array( 'settings' => array( 'metaPixel' => '123456789012' ) ) ), $shop );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'settings save: viewer → FORBIDDEN' );
$r = flxads_t_op( 'FlxAdsSpendDelete', array( 'input' => array( 'id' => 1 ) ), $shop );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'spend delete: viewer → FORBIDDEN' );

// Reads match the classic page (same legacy classes).
$r = flxads_t_op( 'FlxAdsReport', array( 'filter' => array( 'range' => '30' ) ), $shop );
flxt_same( null, $r['errors'] ?? null, 'report: viewer reads' );
$rep = $r['data']['flxAdsReport'] ?? array();
flxt_same( '30', $rep['range'] ?? null, 'report: range echoed' );
flxt_same( FLXADS_Report::base_currency(), $rep['base'] ?? null, 'report: base currency' );
flxt_ok( isset( $rep['totals']['amount']['formatted'] ), 'report: totals carry formatted money' );
foreach ( array( 'FlxAdsValue', 'FlxAdsSendLog', 'FlxAdsLinkBuilder', 'FlxAdsAudiences', 'FlxAdsSuggestions', 'FlxAdsExperiments', 'FlxAdsExperimentChoices', 'FlxAdsSeasons', 'FlxAdsStudio', 'FlxAdsSpend' ) as $op ) {
	$r = flxads_t_op( $op, array(), $shop );
	flxt_same( null, $r['errors'] ?? null, $op . ': viewer reads without errors' );
}

// Meta is not set up: lists come back with an error text instead of failing, and changes are refused.
$r = flxads_t_op( 'FlxAdsCampaigns', array(), $shop );
flxt_same( null, $r['errors'] ?? null, 'campaigns: no GraphQL error without Meta' );
flxt_same( array(), $r['data']['flxAdsCampaigns']['rows'] ?? 1, 'campaigns: empty list without Meta' );
$r = flxads_t_op( 'FlxAdsCampaignStatus', array( 'input' => array( 'id' => '123', 'status' => 'PAUSED' ) ), $admin );
flxt_ok( ! empty( $r['data']['flxAdsCampaignStatus']['errors'] ) || ! empty( $r['errors'] ), 'campaign status: refused without Meta' );

// Settings: secrets are write-only.
$token = 'EAAB-secret-token-' . wp_rand();
$r     = flxads_t_op(
	'FlxAdsSettingsSave',
	array(
		'input' => array(
			'settings' => array(
				'metaOn'      => true,
				'metaPixel'   => '123456789012',
				'metaToken'   => $token,
				'metaTest'    => 'TEST1234',
				'windowDays'  => 30,
				'optRoas'     => 2.5,
			),
		),
	),
	$admin
);
flxt_same( array(), $r['data']['flxAdsSettingsSave']['errors'] ?? null, 'settings save: no input errors' );
$s = $r['data']['flxAdsSettingsSave']['settings'] ?? array();
flxt_same( array( true, '123456789012', 30, 2.5 ), array( $s['metaOn'] ?? null, $s['metaPixel'] ?? null, $s['windowDays'] ?? null, $s['optRoas'] ?? null ), 'settings save: values stored' );
flxt_same( true, $s['metaToken']['set'] ?? null, 'settings save: secret is set' );
flxt_same( '…' . substr( $token, -4 ), $s['metaToken']['mask'] ?? null, 'settings save: only the last four characters' );
flxt_ok( false === strpos( (string) wp_json_encode( $r ), $token ), 'settings save: the token is not in the response' );
flxt_ok( false === strpos( (string) wp_json_encode( flxads_t_op( 'FlxAdsSettings', array(), $admin ) ), $token ), 'settings: the token is not returned' );
flxt_same( $token, flxads_secret( 'meta_token' ), 'settings save: the classic code reads the same secret' );
$r = flxads_t_op( 'FlxAdsSettingsSave', array( 'input' => array( 'settings' => array( 'metaPixel' => '555666777888' ) ) ), $admin );
flxt_same( true, $r['data']['flxAdsSettingsSave']['settings']['metaToken']['set'] ?? null, 'settings save: an empty secret keeps the stored one' );

// Connection test: only a test event (blocked here); the result is stored.
$before = count( $GLOBALS['flxads_http'] );
$r      = flxads_t_op( 'FlxAdsConnectionTest', array( 'input' => array( 'platform' => 'META' ) ), $admin );
flxt_same( null, $r['errors'] ?? null, 'connection test: runs' );
flxt_same( false, $r['data']['flxAdsConnectionTest']['test']['ok'] ?? null, 'connection test: blocked request is a failed test, not an error' );
flxt_ok( count( $GLOBALS['flxads_http'] ) > $before, 'connection test: tried the platform' );
flxt_ok( '' !== (string) ( $r['data']['flxAdsConnectionTest']['test']['message'] ?? '' ), 'connection test: message' );
$r = flxads_t_op( 'FlxAdsSettingsSave', array( 'input' => array( 'settings' => array( 'metaToken' => '__clear__' ) ) ), $admin );
flxt_same( false, $r['data']['flxAdsSettingsSave']['settings']['metaToken']['set'] ?? null, 'settings save: "__clear__" removes the secret' );

// Spend by hand.
$before = count( $GLOBALS['flxads_http'] );
$r      = flxads_t_op( 'FlxAdsSpendAdd', array( 'input' => array( 'channel' => 'google', 'day' => gmdate( 'Y-m-d' ), 'campaign' => 'Test campaign', 'ad' => '', 'spend' => '12,5', 'currency' => '' ) ), $admin );
flxt_same( true, $r['data']['flxAdsSpendAdd']['ok'] ?? null, 'spend add: ok' );
$r = flxads_t_op( 'FlxAdsSpend', array( 'filter' => array( 'range' => '7' ) ), $admin );
$m = $r['data']['flxAdsSpend']['manual'] ?? array();
flxt_same( 12.5, $m[0]['spend']['amount'] ?? null, 'spend: the row is listed for the manager' );
$id = (int) ( $m[0]['id'] ?? 0 );
$r  = flxads_t_op( 'FlxAdsSpend', array( 'filter' => array( 'range' => '7' ) ), $shop );
flxt_same( array(), $r['data']['flxAdsSpend']['manual'] ?? null, 'spend: the viewer does not see manual rows' );
$r = flxads_t_op( 'FlxAdsSpendAdd', array( 'input' => array( 'channel' => 'google', 'day' => 'nope', 'campaign' => '', 'ad' => '', 'spend' => 'x', 'currency' => '' ) ), $admin );
flxt_ok( ! empty( $r['data']['flxAdsSpendAdd']['errors'] ), 'spend add: bad input comes back as payload errors' );
$r = flxads_t_op( 'FlxAdsSpendDelete', array( 'input' => array( 'id' => $id ) ), $admin );
flxt_same( true, $r['data']['flxAdsSpendDelete']['ok'] ?? null, 'spend delete: ok' );
flxt_same( $before, count( $GLOBALS['flxads_http'] ), 'spend by hand: no request left the site' );

// Season plan.
$r = flxads_t_op( 'FlxAdsSeasons', array(), $shop );
flxt_ok( count( $r['data']['flxAdsSeasons'] ?? array() ) > 3, 'seasons: the built-in periods are listed' );
$r = flxads_t_op( 'FlxAdsSeasonAdd', array( 'input' => array( 'season' => array( 'name' => 'Our campaign day', 'start' => gmdate( 'Y-m-d', time() + 20 * DAY_IN_SECONDS ), 'end' => '', 'prep' => 10 ) ) ), $admin );
flxt_same( true, $r['data']['flxAdsSeasonAdd']['ok'] ?? null, 'season add: ok' );
$r      = flxads_t_op( 'FlxAdsSeasons', array(), $admin );
$mine   = array_values( array_filter( $r['data']['flxAdsSeasons'] ?? array(), static fn( $s ) => ! empty( $s['custom'] ) ) );
flxt_same( 'Our campaign day', $mine[0]['name'] ?? null, 'seasons: the added date is listed' );
$r = flxads_t_op( 'FlxAdsSeasonDelete', array( 'input' => array( 'id' => (string) ( $mine[0]['id'] ?? '' ) ) ), $admin );
flxt_same( true, $r['data']['flxAdsSeasonDelete']['ok'] ?? null, 'season delete: ok' );
$r = flxads_t_op( 'FlxAdsSeasonAdd', array( 'input' => array( 'season' => array( 'name' => '', 'start' => '' ) ) ), $admin );
flxt_ok( ! empty( $r['data']['flxAdsSeasonAdd']['errors'] ), 'season add: empty name → payload error' );

// Studio drafts stay local until sent.
$before = count( $GLOBALS['flxads_http'] );
$draft  = array(
	'name'        => 'Test draft',
	'objective'   => 'traffic',
	'link'        => home_url( '/' ),
	'dailyBudget' => 100,
	'adsets'      => array( array( 'name' => 'Everyone', 'ageMin' => 18, 'ageMax' => 65, 'gender' => 'all', 'countries' => array( 'TR' ), 'share' => 100 ) ),
	'ads'         => array( array( 'name' => 'Ad 1', 'text' => 'Plant a tree', 'headline' => 'Plant', 'cta' => 'DONATE_NOW' ) ),
);
$r  = flxads_t_op( 'FlxAdsDraftSave', array( 'input' => array( 'draft' => $draft ) ), $admin );
$id = (string) ( $r['data']['flxAdsDraftSave']['id'] ?? '' );
flxt_ok( '' !== $id, 'draft save: new draft id' );
flxt_same( 'Test draft', $r['data']['flxAdsDraftSave']['draft']['name'] ?? null, 'draft save: returns the draft' );
$r = flxads_t_op( 'FlxAdsDraft', array( 'id' => $id ), $shop );
flxt_same( array( 1, 1 ), array( count( $r['data']['flxAdsDraft']['adsets'] ?? array() ), count( $r['data']['flxAdsDraft']['ads'] ?? array() ) ), 'draft: ad set and ad are stored' );
$r  = flxads_t_op( 'FlxAdsDraftDuplicate', array( 'input' => array( 'id' => $id ) ), $admin );
$cp = (string) ( $r['data']['flxAdsDraftDuplicate']['id'] ?? '' );
flxt_ok( '' !== $cp && $cp !== $id, 'draft duplicate: a new id' );
$r = flxads_t_op( 'FlxAdsStudio', array(), $admin );
flxt_ok( count( $r['data']['flxAdsStudio']['drafts'] ?? array() ) >= 2, 'studio: both drafts listed' );
flxt_same( true, flxads_t_op( 'FlxAdsDraftDelete', array( 'input' => array( 'id' => $id ) ), $admin )['data']['flxAdsDraftDelete']['ok'] ?? null, 'draft delete: ok' );
flxads_t_op( 'FlxAdsDraftDelete', array( 'input' => array( 'id' => $cp ) ), $admin );
$r = flxads_t_op( 'FlxAdsDraftDelete', array( 'input' => array( 'id' => $id ) ), $admin );
flxt_ok( ! empty( $r['data']['flxAdsDraftDelete']['errors'] ) || ! empty( $r['errors'] ), 'draft delete: a missing draft is an error' );
$r = flxads_t_op( 'FlxAdsDraftSend', array( 'input' => array( 'id' => 'missing' ) ), $admin );
flxt_ok( ! empty( $r['data']['flxAdsDraftSend']['errors'] ) || ! empty( $r['errors'] ), 'draft send: a missing draft is refused' );
flxt_same( $before, count( $GLOBALS['flxads_http'] ), 'drafts: no request left the site' );

// Heartbeat: the viewer may call it; it writes nothing to an ad account.
$r = flxads_t_op( 'FlxAdsHeartbeat', array( 'input' => (object) array() ), $shop );
flxt_same( true, $r['data']['flxAdsHeartbeat']['ok'] ?? null, 'heartbeat: viewer may call it' );

// Tests (experiments).
$r = flxads_t_op( 'FlxAdsExperimentCreate', array( 'input' => array( 'experiment' => array( 'type' => 'holdout', 'name' => 'x' ) ) ), $admin );
flxt_ok( ! empty( $r['data']['flxAdsExperimentCreate']['errors'] ) || ! empty( $r['errors'] ), 'experiment create: incomplete input is refused' );

flxt_done();
