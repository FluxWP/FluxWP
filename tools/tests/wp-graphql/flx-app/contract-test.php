<?php
/**
 * Flux App GraphQL contract tests. The documents are the persisted operations the app sends
 * (plugins/flx-app/graphql/persisted-queries.json). REST and GraphQL read the same builders, so each
 * query is compared with the REST answer of the same user.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-app/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux App GraphQL\n";

$GLOBALS['flxapp_ops'] = array();
$GLOBALS['flxapp_ids'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXAPP_DIR . 'graphql/persisted-queries.json' ), true ) as $id => $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxapp_ops'][ $m[1] ] = $doc;
		$GLOBALS['flxapp_ids'][ $m[1] ] = $id;
	}
}
flxt_same( 12, count( $GLOBALS['flxapp_ops'] ), 'Flux App ships 12 persisted operations' );

function flxapp_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxapp_ops'][ $name ], $vars, $user );
}

/** REST answer of a flxapp/v1 route as the given user. */
function flxapp_t_rest( string $route, int $user, string $method = 'GET', array $params = array() ): array {
	wp_set_current_user( $user );
	$req = new WP_REST_Request( $method, '/flxapp/v1/' . $route );
	foreach ( $params as $k => $v ) {
		$req->set_param( $k, $v );
	}
	$res = rest_do_request( $req );
	return (array) $res->get_data();
}

/** JSON text: PHP objects (open documents) and arrays compare as REST sends them. */
function flxapp_t_json( $v ): string {
	return (string) wp_json_encode( $v );
}

$admin = 1;
$sub   = (int) wp_insert_user( array( 'user_login' => 'flxapp_sub_' . wp_rand(), 'user_email' => 'flxapp_sub_' . wp_rand() . '@example.com', 'user_pass' => wp_generate_password(), 'role' => 'subscriber', 'display_name' => 'Ayşe Test' ) );
update_option( 'flxapp_settings', array_merge( (array) get_option( 'flxapp_settings', array() ), array( 'web_session' => 1 ) ) );
wp_cache_delete( 'flxapp_settings', 'options' );

// Permissions.
foreach ( array( 'FlxAppMe', 'FlxAppCounts', 'FlxAppSync', 'FlxAppConfig', 'FlxAppManifest', 'FlxAppDashboard', 'FlxAppPrefs' ) as $op ) {
	flxt_same( 'UNAUTHENTICATED', flxt_code( flxapp_t_op( $op ) ), "$op: visitor → UNAUTHENTICATED" );
}
foreach ( array( 'FlxAppPrefsUpdate' => array( 'input' => array() ), 'FlxAppSessionCreate' => array( 'input' => array( 'path' => 'index.php' ) ), 'FlxAppSessionEnd' => array(), 'FlxAppLogout' => array() ) as $op => $vars ) {
	flxt_same( 'UNAUTHENTICATED', flxt_code( flxapp_t_op( $op, $vars ) ), "$op: visitor → UNAUTHENTICATED" );
}

// Bootstrap is public and equals REST.
$r = flxapp_t_op( 'FlxAppBootstrap' );
flxt_same( null, $r['errors'] ?? null, 'bootstrap: visitor, no errors' );
$doc = $r['data']['flxAppBootstrap']['document'] ?? array();
flxt_same( 'flx-app-bootstrap/1', ( (array) $doc )['schema'] ?? null, 'bootstrap: schema' );
flxt_same( flxapp_t_json( flxapp_t_rest( 'app/bootstrap', 0 ) ), flxapp_t_json( $doc ), 'bootstrap: same document as REST' );

// Me. The panel menu is recorded when the panel renders; seed the catalog the way it stores it.
update_option(
	'flxapp_menu_catalog',
	array(
		'at'    => time(),
		'items' => array(
			array(
				'id'    => 'index-php',
				'slug'  => 'index.php',
				'title' => 'Dashboard',
				'url'   => admin_url( 'index.php' ),
				'icon'  => 'dashboard',
				'badge' => 0,
				'cap'   => 'read',
				'items' => array( array( 'slug' => 'update-core.php', 'title' => 'Updates', 'url' => admin_url( 'update-core.php' ), 'badge' => 3, 'cap' => 'update_core' ) ),
			),
		),
	),
	false
);
delete_user_meta( 1, 'flxapp_menu' );

$r  = flxapp_t_op( 'FlxAppMe', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'me: no errors for the administrator' );
$me   = $r['data']['flxAppMe'] ?? array();
$rest = flxapp_t_rest( 'me', $admin );
flxt_same( array( 1, 'admin', true ), array( $me['user']['databaseId'] ?? null, $me['user']['login'] ?? null, $me['user']['isAdmin'] ?? null ), 'me: user' );
flxt_same( array( $rest['user']['roles'][0]['id'] ?? 'x' ), array_column( $me['user']['roles'] ?? array(), 'id' ), 'me: roles match REST' );
flxt_same( array( $rest['site']['name'], $rest['site']['url'], $rest['site']['admin_url'], $rest['site']['rest_url'], $rest['site']['timezone'] ), array( $me['site']['name'] ?? null, $me['site']['url'] ?? null, $me['site']['adminUrl'] ?? null, $me['site']['restUrl'] ?? null, $me['site']['timezone'] ?? null ), 'me: site matches REST' );
flxt_same( array_column( $rest['menu'], 'slug' ), array_column( $me['menu'] ?? array(), 'slug' ), 'me: menu matches REST' );
flxt_same( array( 'index.php', 'update-core.php', '3' ), array( $me['menu'][0]['slug'] ?? null, $me['menu'][0]['items'][0]['slug'] ?? null, $me['menu'][0]['items'][0]['badge'] ?? null ), 'me: menu entry, sub entry and badge as text' );
flxt_same( array_keys( (array) $rest['modules'] ), array_column( $me['modules'] ?? array(), 'id' ), 'me: modules match REST' );
flxt_same( (array) $rest['hashes'], $me['hashes'] ?? null, 'me: hashes match REST' );
flxt_same( 'cookie', strtolower( (string) ( $me['auth']['kind'] ?? '' ) ), 'me: cookie session' );
$granted = array_column( $me['caps'] ?? array(), 'granted', 'key' );
flxt_same( true, $granted['manage_options'] ?? null, 'me: manage_options granted' );
$sub_menu = flxapp_t_op( 'FlxAppMe', array(), 1 )['data']['flxAppMe']['menu'] ?? array();
flxt_same( array_keys( $rest['caps'] ), array_keys( $granted ), 'me: caps list matches REST' );
flxt_same( array_keys( $rest['versions'] ), array_column( $me['versions'] ?? array(), 'slug' ), 'me: versions match REST' );
flxt_same( $rest['versions']['flx-app'] ?? 'x', array_column( $me['versions'] ?? array(), 'version', 'slug' )['flx-app'] ?? 'y', 'me: flx-app version' );
flxt_same( (bool) $rest['web_session'], $me['webSession'] ?? null, 'me: web session flag' );
$r  = flxapp_t_op( 'FlxAppMe', array(), $sub );
flxt_same( array( false, $sub ), array( $r['data']['flxAppMe']['user']['isAdmin'] ?? null, $r['data']['flxAppMe']['user']['databaseId'] ?? null ), 'me: subscriber sees their own profile, not admin' );
flxt_same( false, array_column( $r['data']['flxAppMe']['caps'] ?? array(), 'granted', 'key' )['manage_options'] ?? null, 'me: subscriber has no manage_options' );

// Counts and sync.
$r = flxapp_t_op( 'FlxAppCounts', array(), $admin );
flxt_same( count( flxapp_t_rest( 'counts', $admin ) ), count( $r['data']['flxAppCounts'] ?? array() ), 'counts: same number of counters as REST' );
flxapp_event_record( $sub, array( 'event' => 'flxapp.test', 'title' => 'Hello', 'body' => 'World', 'target' => 'menu:index.php', 'params' => array( 'a' => 1 ), 'public' => true ) );
$r = flxapp_t_op( 'FlxAppSync', array( 'since' => 0 ), $sub );
$s = $r['data']['flxAppSync'] ?? array();
flxt_same( array( 'flxapp.test', 'Hello', 'World', 'menu:index.php' ), array( $s['events'][0]['event'] ?? null, $s['events'][0]['title'] ?? null, $s['events'][0]['body'] ?? null, $s['events'][0]['target'] ?? null ), 'sync: stored event comes back' );
flxt_same( '{"a":1}', flxapp_t_json( $s['events'][0]['params'] ?? null ), 'sync: event params' );
$rs = flxapp_t_rest( 'sync', $sub, 'GET', array( 'since' => 0 ) );
flxt_same( $rs['hashes'], $s['hashes'] ?? null, 'sync: hashes match REST' );
flxt_ok( ( $s['serverTime'] ?? 0 ) >= time() - 5, 'sync: server time' );
$r = flxapp_t_op( 'FlxAppSync', array( 'since' => time() + 5 ), $sub );
flxt_same( array(), $r['data']['flxAppSync']['events'] ?? null, 'sync: nothing after a later time' );
$r = flxapp_t_op( 'FlxAppSync', array(), $sub );
flxt_same( 1, count( $r['data']['flxAppSync']['events'] ?? array() ), 'sync: no since → all events' );
delete_user_meta( $sub, 'flxapp_events' );

// Config and manifest: same documents as REST, ETag semantics via ifHash.
$r   = flxapp_t_op( 'FlxAppConfig', array(), $admin );
$cfg = $r['data']['flxAppConfig'] ?? array();
flxt_same( null, $r['errors'] ?? null, 'config: no errors' );
$restc = flxapp_t_rest( 'config', $admin );
flxt_same( flxapp_t_json( $restc ), flxapp_t_json( $cfg['document'] ?? null ), 'config: same document as REST' );
flxt_same( array( false, $restc['hash'] ), array( $cfg['notModified'] ?? null, $cfg['hash'] ?? null ), 'config: hash' );
flxt_same( 'flx-app-config/1', ( (array) ( $cfg['document'] ?? array() ) )['schema'] ?? null, 'config: schema' );
$r = flxapp_t_op( 'FlxAppConfig', array( 'ifHash' => $cfg['hash'] ?? 'x' ), $admin );
flxt_same( array( true, null ), array( $r['data']['flxAppConfig']['notModified'] ?? null, array_key_exists( 'document', $r['data']['flxAppConfig'] ?? array() ) ? $r['data']['flxAppConfig']['document'] : 'missing' ), 'config: ifHash match → not modified, no document' );
$r = flxapp_t_op( 'FlxAppConfig', array( 'ifHash' => 'stale' ), $admin );
flxt_same( false, $r['data']['flxAppConfig']['notModified'] ?? null, 'config: stale ifHash → full document' );

$r   = flxapp_t_op( 'FlxAppManifest', array(), $admin );
$man = $r['data']['flxAppManifest'] ?? array();
$restm = flxapp_t_rest( 'manifest', $admin );
flxt_same( null, $r['errors'] ?? null, 'manifest: no errors' );
flxt_same( array( $restm['schema'], $restm['hash'], false ), array( $man['schema'] ?? null, $man['hash'] ?? null, $man['notModified'] ?? null ), 'manifest: schema and hash match REST' );
flxt_same( array_keys( (array) $restm['modules'] ), array_column( $man['modules'] ?? array(), 'id' ), 'manifest: modules match REST' );
$first = $man['modules'][0] ?? array();
flxt_same( flxapp_t_json( ( (array) $restm['modules'] )[ $first['id'] ?? '' ] ?? 'x' ), flxapp_t_json( $first['document'] ?? 'y' ), 'manifest: module document equals REST' );
$r = flxapp_t_op( 'FlxAppManifest', array( 'ifHash' => $man['hash'] ?? 'x' ), $admin );
flxt_same( array( true, array() ), array( $r['data']['flxAppManifest']['notModified'] ?? null, $r['data']['flxAppManifest']['modules'] ?? null ), 'manifest: ifHash match → not modified, no modules' );

// Dashboard.
$r  = flxapp_t_op( 'FlxAppDashboard', array(), $admin );
$d  = $r['data']['flxAppDashboard'] ?? array();
$rd = flxapp_t_rest( 'dashboard', $admin );
flxt_same( null, $r['errors'] ?? null, 'dashboard: no errors' );
flxt_same( array_column( $rd['cards'], 'id' ), array_column( $d['cards'] ?? array(), 'id' ), 'dashboard: cards match REST' );
flxt_same( array_column( $rd['lists'], 'id' ), array_column( $d['lists'] ?? array(), 'id' ), 'dashboard: lists match REST' );
flxt_same( array_column( $rd['cards'], 'value' ), array_column( $d['cards'] ?? array(), 'value' ), 'dashboard: card values match REST' );

// Preferences: partial update, push choices, invalid input is ignored, shared with REST.
$r = flxapp_t_op( 'FlxAppPrefs', array(), $sub );
flxt_same( array( array(), 0 ), array( $r['data']['flxAppPrefs']['pinned'] ?? null, $r['data']['flxAppPrefs']['updated'] ?? null ), 'prefs: empty to start with' );
$events = array_column( $r['data']['flxAppPrefs']['events'] ?? array(), 'id' );
$r = flxapp_t_op( 'FlxAppPrefsUpdate', array( 'input' => array( 'pinned' => array( 'flx-tasks', 'bad slug!' ), 'tabs' => array( 'a', 'b', 'c', 'd', 'e', 'f' ) ) ), $sub );
$p = $r['data']['flxAppPrefsUpdate'] ?? array();
flxt_same( array(), $p['errors'] ?? null, 'prefs update: no errors' );
flxt_same( array( 'flx-tasks', 'badslug' ), $p['prefs']['pinned'] ?? null, 'prefs update: values are cleaned like REST' );
flxt_same( array( 'a', 'b', 'c', 'd', 'e' ), $p['prefs']['tabs'] ?? null, 'prefs update: at most 5 tabs' );
flxt_ok( ( $p['prefs']['updated'] ?? 0 ) > 0, 'prefs update: updated time set' );
$r = flxapp_t_op( 'FlxAppPrefsUpdate', array( 'input' => array( 'hidden' => array( 'x' ) ) ), $sub );
flxt_same( array( array( 'flx-tasks', 'badslug' ), array( 'x' ) ), array( $r['data']['flxAppPrefsUpdate']['prefs']['pinned'] ?? null, $r['data']['flxAppPrefsUpdate']['prefs']['hidden'] ?? null ), 'prefs update: a key that is not sent stays' );
flxt_same( array( 'flx-tasks', 'badslug' ), flxapp_t_rest( 'prefs', $sub )['pinned'] ?? null, 'prefs: REST reads what GraphQL wrote' );
if ( $events ) {
	$ev = $events[0];
	$r  = flxapp_t_op( 'FlxAppPrefsUpdate', array( 'input' => array( 'push' => array( array( 'event' => $ev, 'on' => false ), array( 'event' => 'not.an.event', 'on' => true ) ) ) ), $sub );
	$pe = array_column( $r['data']['flxAppPrefsUpdate']['prefs']['events'] ?? array(), 'on', 'id' );
	flxt_same( false, $pe[ $ev ] ?? null, 'prefs update: notification choice is saved' );
	flxt_same( array( array( 'event' => $ev, 'on' => false ) ), $r['data']['flxAppPrefsUpdate']['prefs']['push'] ?? null, 'prefs update: unknown notification type is ignored' );
} else {
	flxt_ok( true, 'prefs: no notification types registered (skipped)' );
}
delete_user_meta( $sub, 'flxapp_prefs' );

// Web session: one-time address; wrong path is INVALID_INPUT.
$r = flxapp_t_op( 'FlxAppSessionCreate', array( 'input' => array( 'path' => 'index.php' ) ), $admin );
$s = $r['data']['flxAppSessionCreate'] ?? array();
flxt_same( array( 60, array() ), array( $s['expiresIn'] ?? null, $s['errors'] ?? null ), 'session: expires in 60 s' );
flxt_ok( str_contains( (string) ( $s['url'] ?? '' ), home_url() ) || str_contains( (string) ( $s['url'] ?? '' ), site_url() ), 'session: address is on the site', $s );
$r = flxapp_t_op( 'FlxAppSessionCreate', array( 'input' => array( 'path' => 'https://evil.example/' ) ), $admin );
flxt_same( array( 'INVALID_INPUT', 'path' ), array( $r['data']['flxAppSessionCreate']['errors'][0]['code'] ?? null, $r['data']['flxAppSessionCreate']['errors'][0]['field'] ?? null ), 'session: foreign path → INVALID_INPUT' );
$rest_url = flxapp_t_rest( 'session', $admin, 'POST', array( 'path' => 'index.php' ) );
flxt_same( 60, $rest_url['expires_in'] ?? null, 'session: REST still answers (shared service)' );
update_option( 'flxapp_settings', array_merge( (array) get_option( 'flxapp_settings', array() ), array( 'web_session' => 0 ) ) );
wp_cache_delete( 'flxapp_settings', 'options' );
$r = flxapp_t_op( 'FlxAppSessionCreate', array( 'input' => array( 'path' => 'index.php' ) ), $admin );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'session: turned off in settings → FORBIDDEN' );
$r = flxapp_t_op( 'FlxAppSessionEnd', array(), $admin );
flxt_same( array( 0 ), array( $r['data']['flxAppSessionEnd']['ended'] ?? null ), 'session end: nothing to end for a cookie session' );
$r = flxapp_t_op( 'FlxAppLogout', array(), $admin );
flxt_same( true, $r['data']['flxAppLogout']['ok'] ?? null, 'logout: ok' );

// HTTP: app tokens and X-Flux-Locale work on /graphql like on REST.
$id   = $GLOBALS['flxapp_ids'];
$post = static function ( string $op, array $headers, array $vars = array() ) use ( $id ): array {
	$res = wp_remote_post(
		home_url( '/graphql' ),
		array(
			'timeout' => 30,
			'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
			'body'    => wp_json_encode( array( 'queryId' => $id[ $op ], 'operationName' => $op, 'variables' => $vars ) ),
		)
	);
	return array(
		'status' => is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res ),
		'body'   => is_wp_error( $res ) ? array() : (array) json_decode( wp_remote_retrieve_body( $res ), true ),
		'error'  => is_wp_error( $res ) ? $res->get_error_message() : '',
	);
};
wp_set_current_user( 0 );
$access = flxapp_issue( 'access', array( 'user_id' => $sub, 'client_id' => 'flx-test', 'grant_id' => 'g' . wp_rand(), 'scope' => 'flx', 'ttl' => 600 ) );
$h      = $post( 'FlxAppMe', array( 'Authorization' => 'Bearer ' . $access ) );
flxt_same( 200, $h['status'], 'http: app token → 200', $h['error'] );
flxt_same( array( $sub, 'ACCESS' ), array( $h['body']['data']['flxAppMe']['user']['databaseId'] ?? null, $h['body']['data']['flxAppMe']['auth']['kind'] ?? null ), 'http: the token decides the user' );
$h = $post( 'FlxAppMe', array( 'X-FluxWP-Token' => $access ) );
flxt_same( $sub, $h['body']['data']['flxAppMe']['user']['databaseId'] ?? null, 'http: X-FluxWP-Token header works too' );
$mcp = flxapp_issue( 'access', array( 'user_id' => $sub, 'client_id' => 'flx-test', 'grant_id' => 'm' . wp_rand(), 'scope' => 'mcp', 'ttl' => 600 ) );
$h   = $post( 'FlxAppMe', array( 'Authorization' => 'Bearer ' . $mcp ) );
flxt_same( array( 403, null ), array( $h['status'], $h['body']['data']['flxAppMe'] ?? null ), 'http: a token without the flx scope (mcp only) cannot use GraphQL' );
$full = flxapp_issue( 'access', array( 'user_id' => $sub, 'client_id' => 'flx-test', 'grant_id' => 'f' . wp_rand(), 'scope' => 'flx offline_access', 'ttl' => 600 ) );
$h    = $post( 'FlxAppMe', array( 'Authorization' => 'Bearer ' . $full ) );
flxt_same( $sub, $h['body']['data']['flxAppMe']['user']['databaseId'] ?? null, 'http: a token with the flx scope works' );
$h = $post( 'FlxAppMe', array( 'Authorization' => 'Bearer flxat_' . str_repeat( 'x', 43 ) ) );
flxt_same( 401, $h['status'], 'http: unknown token → HTTP 401 (the app refreshes)' );
$h = $post( 'FlxAppMe', array() );
flxt_same( 'UNAUTHENTICATED', $h['body']['errors'][0]['extensions']['code'] ?? null, 'http: no token → UNAUTHENTICATED' );
$en = $post( 'FlxAppPrefs', array( 'Authorization' => 'Bearer ' . $access ) );
$tr = $post( 'FlxAppPrefs', array( 'Authorization' => 'Bearer ' . $access, 'X-Flux-Locale' => 'tr-TR' ) );
$t1 = array_column( $en['body']['data']['flxAppPrefs']['events'] ?? array(), 'title' );
$t2 = array_column( $tr['body']['data']['flxAppPrefs']['events'] ?? array(), 'title' );
flxt_ok( $t1 && $t2 && $t1 !== $t2, 'http: X-Flux-Locale changes the language of the answer', array( $t1[0] ?? null, $t2[0] ?? null ) );
$lo = $post( 'FlxAppLogout', array( 'Authorization' => 'Bearer ' . $access ) );
flxt_same( true, $lo['body']['data']['flxAppLogout']['ok'] ?? null, 'http: logout over GraphQL' );
$h = $post( 'FlxAppMe', array( 'Authorization' => 'Bearer ' . $access ) );
flxt_same( 401, $h['status'], 'http: the session is closed, the token no longer works' );

// Persisted: the documents are allowed in strict mode.
wp_set_current_user( $admin );
$r = graphql( array( 'queryId' => $GLOBALS['flxapp_ids']['FlxAppMe'] ) );
flxt_ok( ! empty( $r['data']['flxAppMe'] ), 'persisted queryId runs (strict mode)' );

// Clean up.
wp_delete_user( $sub );
delete_option( 'flxapp_menu_catalog' );
delete_user_meta( 1, 'flxapp_menu' );
update_option( 'flxapp_settings', array_merge( (array) get_option( 'flxapp_settings', array() ), array( 'web_session' => 1 ) ) );
flxt_done();
