<?php
/**
 * Flux Headless GraphQL contract tests. The documents are the persisted operations the Headless screen and
 * front ends send (plugins/flx-headless/graphql/persisted-queries.json). Webhook calls are mocked
 * (pre_http_request); nothing leaves the test site.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-headless/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux Headless GraphQL\n";

$GLOBALS['flxhl_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXHL_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/m', $doc, $m ) ) {
		$GLOBALS['flxhl_ops'][ $m[1] ] = $doc;
	}
}
foreach ( array( 'FlxHeadlessScreen', 'FlxHeadlessFrontendSave', 'FlxHeadlessFrontendDelete', 'FlxHeadlessWpFrontSet', 'FlxHeadlessRevalidateTest', 'FlxHeadlessRevalidateAll', 'FlxHeadlessLogClear', 'FlxRoute', 'FlxSite' ) as $op ) {
	flxt_ok( isset( $GLOBALS['flxhl_ops'][ $op ] ), "persisted operation $op ships" );
}

function flxhl_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxhl_ops'][ $name ], $vars, $user );
}

/** [code, field] pairs of a payload's errors. */
function flxhl_t_errs( array $r, string $field ): array {
	return array_map( static fn( $e ) => array( $e['code'], $e['field'] ), $r['data'][ $field ]['errors'] ?? array() );
}

// Webhook calls are mocked: the last call is kept, $GLOBALS['flxhl_t_status'] is the answer.
$GLOBALS['flxhl_t_status'] = 200;
$GLOBALS['flxhl_t_calls']  = array();
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		if ( ! str_contains( (string) $url, 'front.example.com' ) ) {
			return $pre;
		}
		$GLOBALS['flxhl_t_calls'][] = array( 'url' => (string) $url, 'args' => $args );
		return array( 'headers' => array(), 'body' => '{}', 'response' => array( 'code' => $GLOBALS['flxhl_t_status'], 'message' => '' ), 'cookies' => array(), 'filename' => null );
	},
	10,
	3
);

$admin  = 1;
$sub    = (int) wp_insert_user( array( 'user_login' => 'flxhl_sub_' . wp_rand(), 'user_email' => 'flxhl_sub_' . wp_rand() . '@example.com', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$secret = 'flxhl-test-secret-0123456789-abcdef';
delete_option( 'flxhl_settings' );
delete_option( 'flxhl_log' );

// Permissions.
flxt_same( 'UNAUTHENTICATED', flxt_code( flxhl_t_op( 'FlxHeadlessScreen' ) ), 'screen: visitor → UNAUTHENTICATED' );
flxt_same( 'FORBIDDEN', flxt_code( flxhl_t_op( 'FlxHeadlessScreen', array(), $sub ) ), 'screen: subscriber → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxhl_t_op( 'FlxHeadlessFrontendSave', array( 'input' => array( 'name' => 'x', 'url' => 'https://front.example.com' ) ), $sub ) ), 'save: subscriber → FORBIDDEN' );
flxt_same( 'UNAUTHENTICATED', flxt_code( flxhl_t_op( 'FlxHeadlessRevalidateAll', array(), 0 ) ), 'revalidate all: visitor → UNAUTHENTICATED' );
flxt_same( null, flxhl_t_op( 'FlxRoute', array( 'path' => '/' ) )['errors'] ?? null, 'flxRoute: public' );
flxt_same( null, flxhl_t_op( 'FlxSite' )['errors'] ?? null, 'flxSite: public' );

// Screen defaults.
$r = flxhl_t_op( 'FlxHeadlessScreen', array(), $admin );
$s = $r['data']['flxHeadlessSettings'] ?? array();
flxt_same( array( null, array(), 'KEEP', array() ), array( $r['errors'] ?? null, $s['frontends'] ?? null, $s['wpFront'] ?? null, $s['deliveries'] ?? null ), 'screen: no front ends, WordPress pages kept, empty log' );
flxt_ok( str_ends_with( (string) ( $s['graphqlUrl'] ?? '' ), '/graphql' ), 'screen: GraphQL address' );

// Validation: nothing is written.
foreach ( array(
	array( array( 'url' => 'https://front.example.com' ), 'name' ),
	array( array( 'name' => 'Front', 'url' => 'http://front.example.com' ), 'url' ),
	array( array( 'name' => 'Front', 'url' => 'ftp://front.example.com' ), 'url' ),
	array( array( 'name' => 'Front', 'url' => home_url() ), 'url' ),
	array( array( 'name' => 'Front', 'url' => 'https://front.example.com', 'revalidateUrl' => 'http://front.example.com/api' ), 'revalidateUrl' ),
	array( array( 'name' => 'Front', 'url' => 'https://front.example.com', 'revalidateUrl' => 'https://front.example.com/api/revalidate' ), 'secret' ),
	array( array( 'name' => 'Front', 'url' => 'https://front.example.com', 'secret' => 'short' ), 'secret' ),
	array( array( 'name' => 'Front', 'url' => 'https://front.example.com', 'previewPath' => 'api/preview' ), 'previewPath' ),
	array( array( 'name' => 'Front', 'url' => 'https://front.example.com', 'languages' => array( 'xx-nope' ) ), 'languages' ),
) as $case ) {
	$r = flxhl_t_op( 'FlxHeadlessFrontendSave', array( 'input' => $case[0] ), $admin );
	$e = flxhl_t_errs( $r, 'flxHeadlessFrontendSave' );
	flxt_same( $case[1], $e[0][1] ?? null, 'save: ' . wp_json_encode( $case[0] ) . ' → error on ' . $case[1] );
}
flxt_same( 'NOT_FOUND', flxt_code( flxhl_t_op( 'FlxHeadlessFrontendSave', array( 'input' => array( 'id' => 'nope', 'name' => 'Front' ) ), $admin ) ), 'save: unknown id → NOT_FOUND' );
flxt_same( array(), FLXHL_Frontend_Service::frontends(), 'save: nothing is written when the input is invalid' );

// A front end with a webhook.
$r  = flxhl_t_op(
	'FlxHeadlessFrontendSave',
	array(
		'input' => array(
			'name'          => 'Front',
			'url'           => 'https://front.example.com/',
			'revalidateUrl' => 'https://front.example.com/api/revalidate',
			'secret'        => $secret,
			'previewPath'   => '/api/preview',
		),
	),
	$admin
);
$fe = $r['data']['flxHeadlessFrontendSave']['frontend'] ?? array();
$id = (string) ( $fe['id'] ?? '' );
flxt_same(
	array( array(), 'Front', 'https://front.example.com', 'https://front.example.com', true, true, true, '/api/preview' ),
	array( $r['data']['flxHeadlessFrontendSave']['errors'] ?? null, $fe['name'] ?? null, $fe['url'] ?? null, $fe['origin'] ?? null, $fe['enabled'] ?? null, $fe['isDefault'] ?? null, $fe['secretSet'] ?? null, $fe['previewPath'] ?? null ),
	'save: front end added (no trailing slash, the first one is the default)'
);
flxt_ok( '' !== $id, 'save: an id is given' );
flxt_ok( str_ends_with( (string) ( $fe['secretHint'] ?? '' ), 'cdef' ) && ! str_contains( (string) $fe['secretHint'], '0123' ), 'save: only a masked hint of the secret' );
flxt_ok( ! str_contains( (string) wp_json_encode( $r ), $secret ), 'save: the secret is never in a response' );
flxt_ok( ! str_contains( (string) wp_json_encode( get_option( 'flxhl_settings' ) ), $secret ), 'save: the stored secret is sealed, not plain text' );
flxt_same( $secret, FLXHL_Frontend_Service::secret( $id ), 'save: the server can read the secret' );
flxt_ok( in_array( 'https://front.example.com', (array) apply_filters( 'flx_gql_cors_origins', array() ), true ), 'CORS: the front-end origin may call GraphQL' );

// The same address twice.
$r = flxhl_t_op( 'FlxHeadlessFrontendSave', array( 'input' => array( 'name' => 'Twin', 'url' => 'https://front.example.com' ) ), $admin );
flxt_same( 'url', flxhl_t_errs( $r, 'flxHeadlessFrontendSave' )[0][1] ?? null, 'save: another front end with the same address → error on url' );

// An empty secret keeps the saved one; the hint stays.
$r = flxhl_t_op( 'FlxHeadlessFrontendSave', array( 'input' => array( 'id' => $id, 'name' => 'Front 2', 'secret' => '' ) ), $admin );
flxt_same( array( 'Front 2', true ), array( $r['data']['flxHeadlessFrontendSave']['frontend']['name'] ?? null, $r['data']['flxHeadlessFrontendSave']['frontend']['secretSet'] ?? null ), 'save: a partial change keeps the other fields and the secret' );
flxt_same( $secret, FLXHL_Frontend_Service::secret( $id ), 'save: secret kept' );

// Screen: the secret is in no field.
$r = flxhl_t_op( 'FlxHeadlessScreen', array(), $admin );
flxt_ok( ! str_contains( (string) wp_json_encode( $r ), $secret ), 'screen: the secret is never in a response' );
flxt_ok( in_array( 'https://front.example.com', (array) ( $r['data']['flxHeadlessSettings']['corsOrigins'] ?? array() ), true ), 'screen: the origin is in the CORS list' );

// Webhook test: signed call, logged.
$GLOBALS['flxhl_t_calls'] = array();
$r    = flxhl_t_op( 'FlxHeadlessRevalidateTest', array( 'id' => $id ), $admin );
$d    = $r['data']['flxHeadlessRevalidateTest']['delivery'] ?? array();
$call = $GLOBALS['flxhl_t_calls'][0] ?? array();
flxt_same( array( true, 200, 'test', 'https://front.example.com/api/revalidate' ), array( $d['ok'] ?? null, $d['status'] ?? null, $d['event'] ?? null, $call['url'] ?? null ), 'webhook test: delivered to the revalidate address' );
$body = (string) ( $call['args']['body'] ?? '' );
$sig  = (string) ( $call['args']['headers']['X-Flx-Signature'] ?? '' );
flxt_ok( 1 === preg_match( '/^t=(\d+),v1=([0-9a-f]{64})$/', $sig, $m ), 'webhook test: X-Flx-Signature is t=<time>,v1=<hex>' );
flxt_same( hash_hmac( 'sha256', ( $m[1] ?? '' ) . '.' . $body, $secret ), $m[2] ?? null, 'webhook test: v1 = HMAC-SHA256(secret, "<time>.<body>")' );
$json = (array) json_decode( $body, true );
flxt_same( array( 'test', $id, array( 'flx-headless:site' ) ), array( $json['event'] ?? null, $json['frontend'] ?? null, $json['tags'] ?? null ), 'webhook test: body has event, front end and tags' );
flxt_ok( ! str_contains( $body, $secret ) && ! str_contains( (string) wp_json_encode( $r ), $secret ), 'webhook test: the secret is neither sent nor returned' );
flxt_same( 1, count( $r['data']['flxHeadlessRevalidateTest']['settings']['deliveries'] ?? array() ), 'webhook test: logged' );

$GLOBALS['flxhl_t_status'] = 401;
$d = flxhl_t_op( 'FlxHeadlessRevalidateTest', array( 'id' => $id ), $admin )['data']['flxHeadlessRevalidateTest']['delivery'] ?? array();
flxt_same( array( false, 401 ), array( $d['ok'] ?? null, $d['status'] ?? null ), 'webhook test: a refusal is reported' );
$GLOBALS['flxhl_t_status'] = 200;

// Content changes queue tags and paths.
$post = (int) wp_insert_post( array( 'post_title' => 'Headless test', 'post_name' => 'flxhl-headless-test', 'post_status' => 'publish', 'post_type' => 'post', 'post_content' => 'Hello' ) );
$p    = FLXHL_Revalidate_Service::pending();
flxt_ok( in_array( 'flx-headless:post:' . $post, $p['tags'] ?? array(), true ), 'purge: a published post queues its tag' );
flxt_ok( in_array( 'flx-headless:archive:post', $p['tags'] ?? array(), true ), 'purge: and its archive' );
$GLOBALS['flxhl_t_calls'] = array();
FLXHL_Revalidate_Service::flush();
flxt_same( 1, count( $GLOBALS['flxhl_t_calls'] ), 'purge: one webhook call per front end' );
flxt_same( 'purge', $GLOBALS['flxhl_t_calls'][0]['args']['headers']['X-Flx-Event'] ?? null, 'purge: event purge' );

// flxRoute.
$r = flxhl_t_op( 'FlxRoute', array( 'path' => '/' ) );
flxt_same( array( 'FRONT_PAGE', 200, $id ), array( $r['data']['flxRoute']['kind'] ?? null, $r['data']['flxRoute']['status'] ?? null, $r['data']['flxRoute']['frontendId'] ?? null ), 'flxRoute: / is the front page of the default front end' );

$path = (string) wp_parse_url( (string) get_permalink( $post ), PHP_URL_PATH );
$r    = flxhl_t_op( 'FlxRoute', array( 'path' => $path ) );
$rt   = $r['data']['flxRoute'] ?? array();
flxt_same( array( 'SINGULAR', 200, $post, 'Post', 'Headless test' ), array( $rt['kind'] ?? null, $rt['status'] ?? null, $rt['databaseId'] ?? null, $rt['node']['__typename'] ?? null, $rt['node']['title'] ?? null ), 'flxRoute: a post with its node' );
flxt_ok( str_starts_with( (string) ( $rt['head']['canonical'] ?? '' ), 'https://front.example.com/' ), 'flxRoute: canonical on the front end' );
flxt_ok( '' !== (string) ( $rt['head']['title'] ?? '' ) && false === ( $rt['head']['noindex'] ?? null ), 'flxRoute: title, indexable' );
flxt_ok( in_array( 'flx-headless:post:' . $post, $rt['cacheTags'] ?? array(), true ), 'flxRoute: cache tag of the post' );
flxt_ok( ! str_contains( (string) wp_json_encode( $rt['head'] ?? array() ), (string) wp_parse_url( home_url(), PHP_URL_HOST ) . '/' ) || str_contains( home_url(), 'front.example.com' ), 'flxRoute: no WordPress address in the head' );

$r = flxhl_t_op( 'FlxRoute', array( 'path' => 'https://front.example.com' . $path ) );
flxt_same( 'SINGULAR', $r['data']['flxRoute']['kind'] ?? null, 'flxRoute: a full front-end address works' );
$r = flxhl_t_op( 'FlxRoute', array( 'path' => 'https://evil.example.org' . $path ) );
flxt_same( 'NOT_FOUND', $r['data']['flxRoute']['kind'] ?? null, 'flxRoute: another domain → NOT_FOUND' );

$r = flxhl_t_op( 'FlxRoute', array( 'path' => untrailingslashit( $path ) ) );
if ( str_ends_with( $path, '/' ) ) {
	flxt_same( array( 'REDIRECT', 301, $path ), array( $r['data']['flxRoute']['kind'] ?? null, $r['data']['flxRoute']['status'] ?? null, $r['data']['flxRoute']['redirect'] ?? null ), 'flxRoute: missing trailing slash → 301' );
}

$r  = flxhl_t_op( 'FlxRoute', array( 'path' => '/flxhl-there-is-nothing-here/' ) );
$rt = $r['data']['flxRoute'] ?? array();
flxt_same( array( 'NOT_FOUND', 404, true, null ), array( $rt['kind'] ?? null, $rt['status'] ?? null, $rt['head']['noindex'] ?? null, $rt['node'] ?? null ), 'flxRoute: unknown address → 404, noindex, no node' );
foreach ( array( '/wp-admin/', '/wp-login.php', '/graphql' ) as $p ) {
	flxt_same( 'NOT_FOUND', flxhl_t_op( 'FlxRoute', array( 'path' => $p ) )['data']['flxRoute']['kind'] ?? null, "flxRoute: $p is not a front-end page" );
}

// Old slug → redirect.
wp_update_post( array( 'ID' => $post, 'post_name' => 'flxhl-headless-test-new' ) );
$r = flxhl_t_op( 'FlxRoute', array( 'path' => $path ) );
flxt_same( 'REDIRECT', $r['data']['flxRoute']['kind'] ?? null, 'flxRoute: the old slug redirects' );
flxt_ok( str_contains( (string) ( $r['data']['flxRoute']['redirect'] ?? '' ), 'flxhl-headless-test-new' ), 'flxRoute: to the new slug' );

// Drafts: hidden from visitors; preview only for editors.
$draft = (int) wp_insert_post( array( 'post_title' => 'Headless draft', 'post_status' => 'draft', 'post_type' => 'post' ) );
$r     = flxhl_t_op( 'FlxRoute', array( 'path' => '/', 'previewId' => $draft ), 0 );
flxt_ok( 'Headless draft' !== ( $r['data']['flxRoute']['title'] ?? null ), 'preview: a visitor cannot see a draft' );
$r = flxhl_t_op( 'FlxRoute', array( 'path' => '/', 'previewId' => $draft ), $admin );
flxt_same( array( 'Headless draft', true ), array( $r['data']['flxRoute']['title'] ?? null, $r['data']['flxRoute']['head']['noindex'] ?? null ), 'preview: an editor sees the draft, noindex' );
$link = (string) get_preview_post_link( $draft );
flxt_ok( str_starts_with( $link, 'https://front.example.com/api/preview?' ) && str_contains( $link, 'sig=' ), 'preview: the Preview button opens a signed link on the front end' );

// flxSite.
$r = flxhl_t_op( 'FlxSite' );
$s = $r['data']['flxSite'] ?? array();
flxt_same( array( null, 'https://front.example.com', $id ), array( $r['errors'] ?? null, $s['url'] ?? null, $s['frontendId'] ?? null ), 'flxSite: front-end address' );
flxt_ok( is_array( $s['menus'] ?? null ) && is_array( $s['palette'] ?? null ) && is_array( $s['languages'] ?? null ), 'flxSite: menus, palette and languages' );
flxt_ok( str_starts_with( (string) ( $s['sitemapUrl'] ?? '' ), home_url() ), 'flxSite: sitemap on WordPress for the proxy' );
flxt_ok( ! str_contains( (string) wp_json_encode( $r ), $secret ), 'flxSite: no secret' );

// WordPress pages mode.
$r = flxhl_t_op( 'FlxHeadlessWpFrontSet', array( 'mode' => 'NOINDEX' ), $admin );
flxt_same( 'NOINDEX', $r['data']['flxHeadlessWpFrontSet']['settings']['wpFront'] ?? null, 'WordPress pages: noindex saved' );

// Delete.
$r = flxhl_t_op( 'FlxHeadlessFrontendDelete', array( 'id' => $id ), $admin );
flxt_same( array( array(), array() ), array( $r['data']['flxHeadlessFrontendDelete']['errors'] ?? null, $r['data']['flxHeadlessFrontendDelete']['settings']['frontends'] ?? null ), 'delete: front end removed' );
flxt_same( '', FLXHL_Frontend_Service::secret( $id ), 'delete: its secret is gone' );
flxt_ok( ! in_array( 'https://front.example.com', (array) apply_filters( 'flx_gql_cors_origins', array() ), true ), 'delete: the origin is no longer allowed' );
$r = flxhl_t_op( 'FlxHeadlessLogClear', array(), $admin );
flxt_same( array(), $r['data']['flxHeadlessLogClear']['settings']['deliveries'] ?? null, 'log clear: empty' );

wp_delete_post( $post, true );
wp_delete_post( $draft, true );
wp_delete_user( $sub );
delete_option( 'flxhl_settings' );
delete_option( 'flxhl_log' );
flxt_done();
