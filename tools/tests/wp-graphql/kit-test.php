<?php
/**
 * Flux GraphQL kit contract tests. Run on the test site (fixtures on):
 *   wp eval-file tools/tests/wp-graphql/kit-test.php
 */

require __DIR__ . '/lib.php';

echo "Flux GraphQL kit\n";
update_option( 'flxgql_settings', array() );

$s = flx_gql_state();
flxt_same( 'embedded', $s['source'], 'embedded WPGraphQL loads' );
flxt_ok( $s['ok'], 'embedded version is supported', $s );
flxt_same( trim( (string) file_get_contents( FLXGQL_DIR . 'vendor/WPGRAPHQL_VERSION' ) ), WPGRAPHQL_VERSION, 'pinned version file matches the loaded copy' );
flxt_ok( ! class_exists( 'Appsero\Client' ), 'Appsero telemetry client is not shipped' );

// Registry rules.
$r = flxt_gql( '{ flxTestNoCap }' );
flxt_ok( isset( $r['errors'] ) && ! isset( $r['data']['flxTestNoCap'] ), 'a root field without cap is refused (fail closed)' );
flxt_ok( isset( flx_gql_registration_errors()['RootQuery.flxTestNoCap'] ), 'the refusal is recorded for the system check' );

$r = flxt_gql( '{ flxTestAdminOnly }' );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'visitor gets UNAUTHENTICATED' );
$sub = wp_insert_user( array( 'user_login' => 'flxt_sub_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$r   = flxt_gql( '{ flxTestAdminOnly }', array(), (int) $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'subscriber gets FORBIDDEN' );
$r = flxt_gql( '{ flxTestAdminOnly }', array(), 1 );
flxt_same( 'secret', $r['data']['flxTestAdminOnly'] ?? null, 'administrator passes the cap' );

// Errors.
$r = flxt_gql( '{ flxTestFail }' );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'WP_Error status 404 → NOT_FOUND' );
flxt_same( 'flx_test_missing', $r['errors'][0]['extensions']['data']['reason'] ?? null, 'WP_Error code is kept as extensions.data.reason' );

// Mutations.
$r = flxt_gql( 'mutation { flxTestSave(input:{name:"x"}) { name errors { code } } }' );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'mutation cap is checked' );
$r = flxt_gql( 'mutation { flxTestSave(input:{name:"ab"}) { name errors { code } } }', array(), 1 );
flxt_same( array( 'name' => 'AB', 'errors' => array() ), $r['data']['flxTestSave'] ?? null, 'mutation payload + empty errors' );
$r = flxt_gql( 'mutation { flxTestSave(input:{name:" "}) { name errors { code field } } }', array(), 1 );
flxt_same( array( array( 'code' => 'INVALID_INPUT', 'field' => 'name' ) ), $r['data']['flxTestSave']['errors'] ?? null, 'input problems come back as FlxUserError' );

// Connections.
$r = flxt_gql( '{ flxTestItems(first:3) { totalCount pageInfo { hasNextPage hasPreviousPage endCursor } nodes { n } } }' );
$c = $r['data']['flxTestItems'] ?? array();
flxt_same( array( 1, 2, 3 ), array_column( $c['nodes'] ?? array(), 'n' ), 'first page' );
flxt_same( 7, $c['totalCount'] ?? null, 'totalCount' );
$r = flxt_gql( '{ flxTestItems(first:3, after:"' . $c['pageInfo']['endCursor'] . '") { pageInfo { hasNextPage hasPreviousPage } nodes { n } } }' );
flxt_same( array( 4, 5, 6 ), array_column( $r['data']['flxTestItems']['nodes'] ?? array(), 'n' ), 'second page after endCursor' );
$r = flxt_gql( '{ flxTestItems(first:500) { nodes { n } } }' );
flxt_same( 7, count( $r['data']['flxTestItems']['nodes'] ?? array() ), 'page size is clamped (7 items, max 50)' );
$r = flxt_gql( '{ flxTestItems(after:"nope") { nodes { n } } }' );
flxt_same( 'INVALID_INPUT', flxt_code( $r ), 'foreign cursor → INVALID_INPUT' );

// Shared types.
$dt = flx_gql_datetime( '2026-11-15 14:59:00' );
flxt_same( '2026-11-15T14:59:00Z', $dt['iso'] ?? null, 'FlxDateTime from GMT string' );
flxt_same( null, flx_gql_datetime( '0000-00-00 00:00:00' ), 'empty date → null' );
flxt_same( 'TRY', flx_gql_money( 150, 'try' )['currency'], 'FlxMoney currency upper-cased' );
flxt_same( 'FlxTask', \GraphQLRelay\Relay::fromGlobalId( flx_gql_global_id( 'FlxTask', 12 ) )['type'], 'global id round trip' );
flxt_same( 12, flx_gql_decode_id( flx_gql_global_id( 'FlxTask', 12 ), 'FlxTask' ), 'decode global id' );
flxt_same( 12, flx_gql_decode_id( '12', 'FlxTask' ), 'decode database id' );
try {
	flx_gql_decode_id( flx_gql_global_id( 'FlxOther', 12 ), 'FlxTask' );
	flxt_ok( false, 'decode refuses another type' );
} catch ( FLXGQL_Error $e ) {
	flxt_same( 'INVALID_INPUT', $e->flx_code, 'decode refuses another type' );
}

// Settings types from flx_settings_schema.
$r = flxt_gql( '{ flxSettings { flxgqlSettings { queryMode maxPage } } }', array(), 1 );
flxt_same( 'STRICT', $r['data']['flxSettings']['flxgqlSettings']['queryMode'] ?? null, 'settings enum read' );
$r = flxt_gql( 'mutation { flxSettingsUpdate(input:{flxgqlSettings:{maxPage: 5000}}) { settings { flxgqlSettings { maxPage } } errors { field } } }', array(), 1 );
flxt_same( 100, $r['data']['flxSettingsUpdate']['settings']['flxgqlSettings']['maxPage'] ?? null, 'own option is clamped on every write' );
update_option( 'flxgql_settings', array() );
$r = flxt_gql( '{ flxSettings { flxgqlSettings { queryMode } } }', array(), (int) $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'settings need manage_options' );

// Secrets in settings (fail closed).
foreach ( array( 'key_live', 'live_key', 'stripe_key', 'api_key', 'apikey', 'apiKey', 'clientSecret', 'client_secret', 'access-token', 'accessToken', 'refresh_token', 'smtp_password', 'smtp_pass', 'passwd', 'pwd', 'auth', 'private_key', 'privateKey', 'webhook.secret', 'license_key', 'flxai_keys', 'secretkey' ) as $k ) {
	flxt_ok( flx_gql_is_secret_key( $k ), "secret key: $k" );
}
foreach ( array( 'title', 'max_page', 'cookie_text', 'web_session', 'monkey', 'keyboard_hint', 'author', 'passage', 'brand' ) as $k ) {
	flxt_ok( ! flx_gql_is_secret_key( $k ), "not a secret: $k" );
}
update_option(
	'flxt_conf',
	array(
		'title'     => 'Hello',
		'key_live'  => 'sk_live_1',
		'api_key'   => 'sk_2',
		'providers' => array(
			'openai' => array( 'model' => 'gpt', 'api_key' => 'sk_3', 'token' => 'tk' ),
			'list'   => array( array( 'name' => 'a', 'secret' => 's1' ) ),
		),
	)
);
$fields = array();
foreach ( flx_gql_settings_schema()['flxt_conf']['fields'] ?? array() as $gql => $f ) {
	$fields[] = $f['key'];
}
flxt_same( array( 'title', 'providers' ), $fields, 'only non-secret fields become GraphQL fields' );
flxt_ok( ! isset( flx_gql_settings_schema()['flxt_api_keys'] ), 'an option whose name is a secret is left out' );
$r    = flxt_gql( '{ flxSettings { flxtConf { title providers } } }', array(), 1 );
$json = wp_json_encode( $r );
flxt_same( 'Hello', $r['data']['flxSettings']['flxtConf']['title'] ?? null, 'secret group: plain field is readable' );
flxt_ok( false === strpos( (string) $json, 'sk_' ) && false === strpos( (string) $json, '"tk"' ) && false === strpos( (string) $json, 's1' ), 'secrets inside object values are removed', $r );
flxt_same( 'gpt', $r['data']['flxSettings']['flxtConf']['providers']['openai']['model'] ?? null, 'non-secret nested values stay' );
$r = flxt_gql( '{ flxSettings { flxtConf { keyLive } } }', array(), 1 );
flxt_ok( isset( $r['errors'] ), 'a secret field cannot be queried' );
$r = flxt_gql( 'mutation { flxSettingsUpdate(input:{flxtConf:{providers: {openai: {model: "o4"}, list: [{name: "b"}]}}}) { errors { field } } }', array(), 1 );
$stored = get_option( 'flxt_conf' );
flxt_same( array( 'model' => 'o4', 'api_key' => 'sk_3', 'token' => 'tk' ), $stored['providers']['openai'] ?? null, 'writing an object keeps the stored secrets' );
flxt_same( array( array( 'name' => 'b', 'secret' => 's1' ) ), $stored['providers']['list'] ?? null, 'list items keep their secrets by index' );
flxt_same( 'sk_live_1', $stored['key_live'] ?? null, 'secret fields are not touched by a write' );
delete_option( 'flxt_conf' );

// Persisted queries.
$pq = flx_gql_persisted();
flxt_ok( isset( $pq['queries']['ffddb6ac5762b8d7ceee75ad414d56112547858efbd5c677d267a7c8c50a11fd'] ), 'fixture manifest is loaded' );
flxt_same( flx_gql_document_hash( "query FlxTestPing {\n  flxTestEcho(text: \"pong\")\n}" ), flx_gql_document_hash( 'query FlxTestPing { flxTestEcho(text: "pong") }' ), 'allowlist hash ignores formatting' );

// Capability model (kit 0.2).
wp_set_current_user( (int) $sub );
flxt_same( true, ( flx_gql_any_cap( 'manage_options', 'read' ) )( null, array(), null ), 'any_cap: one capability is enough' );
flxt_same( false, ( flx_gql_all_caps( 'manage_options', 'read' ) )( null, array(), null ), 'all_caps: every capability is needed' );
flxt_same( false, ( flx_gql_all_caps() )( null, array(), null ), 'all_caps without capabilities refuses' );
wp_set_current_user( 1 );
flxt_same( true, ( flx_gql_all_caps( 'manage_options', 'read' ) )( null, array(), null ), 'all_caps: administrator passes' );
add_filter( 'flx_gql_capabilities', $flxt_caps = static fn( $c ) => (array) $c + array( 'flxt_cap' => array( 'label' => 'Test cap', 'group' => 'Test', 'package' => 'flx-test' ), 'Bad Cap' => 'x' ) );
flxt_ok( isset( flx_gql_capabilities()['flxt_cap'] ) && ! isset( flx_gql_capabilities()['Bad Cap'] ), 'catalog keeps valid capability names only' );
( new WP_User( (int) $sub ) )->add_cap( 'flxt_cap' );
$r = flxt_gql( '{ flxViewer { loggedIn databaseId isAdmin capabilities can(capability:"flxt_cap") } }', array(), (int) $sub );
$v = $r['data']['flxViewer'] ?? array();
flxt_same( array( true, (int) $sub, false, true ), array( $v['loggedIn'] ?? null, $v['databaseId'] ?? null, $v['isAdmin'] ?? null, $v['can'] ?? null ), 'viewer: signed in, id, not admin, can()' );
flxt_ok( in_array( 'flxt_cap', $v['capabilities'] ?? array(), true ), 'viewer: catalog capability the user has is listed' );
$r = flxt_gql( '{ flxViewer { loggedIn databaseId capabilities can(capability:"read") } }' );
flxt_same( array( 'loggedIn' => false, 'databaseId' => null, 'capabilities' => array(), 'can' => false ), $r['data']['flxViewer'] ?? null, 'viewer: visitor has nothing' );
$r = flxt_gql( '{ flxCapabilities { key granted } }' );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'capability catalog needs sign-in' );
$r = flxt_gql( '{ flxCapabilities { key label group package granted } }', array(), (int) $sub );
$row = array_values( array_filter( $r['data']['flxCapabilities'] ?? array(), static fn( $c ) => 'flxt_cap' === $c['key'] ) );
flxt_same( array( 'key' => 'flxt_cap', 'label' => 'Test cap', 'group' => 'Test', 'package' => 'flx-test', 'granted' => true ), $row[0] ?? null, 'capability catalog row with granted' );
remove_filter( 'flx_gql_capabilities', $flxt_caps );

wp_delete_user( (int) $sub );
flxt_done();
