<?php
/**
 * Flux Social GraphQL contract tests. The documents are the persisted operations the Social Media screen
 * sends (plugins/flx-social/graphql/persisted-queries.json). REST and GraphQL share the services, so the
 * REST output is checked against them too. Nothing is sent to a real network: accounts are fixtures with
 * fake tokens and every post is saved as a draft or scheduled far in the future.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-social/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux Social GraphQL\n";

$GLOBALS['flxsm_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXSM_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxsm_ops'][ $m[1] ] = $doc;
	}
}
flxt_ok( count( $GLOBALS['flxsm_ops'] ) >= 34, 'Flux Social ships its persisted operations (' . count( $GLOBALS['flxsm_ops'] ) . ')' );

function flxsm_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxsm_ops'][ $name ], $vars, $user );
}

$admin = 1;
$mk    = static function ( string $role, array $caps = array() ): int {
	$id = (int) wp_insert_user( array( 'user_login' => 'flxsm_' . $role . wp_rand(), 'user_email' => 'flxsm_' . wp_rand() . '@example.com', 'user_pass' => wp_generate_password(), 'role' => $role, 'display_name' => 'Test ' . $role ) );
	foreach ( $caps as $c ) {
		( new WP_User( $id ) )->add_cap( $c );
	}
	return $id;
};
$sub    = $mk( 'subscriber' );
$author = $mk( 'subscriber', array( FLXSM_CAP_POST, 'upload_files' ) ); // prepares posts only
$other  = $mk( 'subscriber', array( FLXSM_CAP_POST ) );
$pub    = $mk( 'subscriber', array( FLXSM_CAP_POST, FLXSM_CAP_PUBLISH ) );

// Fixture accounts (fake tokens, nothing is contacted).
wp_set_current_user( $admin );
$fb = flxsm_upsert_account( array( 'provider' => 'facebook', 'via' => 'meta', 'ext_id' => 'fx_fb_' . wp_rand(), 'name' => 'Test Page', 'username' => 'testpage', 'avatar' => 'https://example.com/a.png', 'token' => array( 'access_token' => 'SECRET-TOKEN-123' ), 'expires' => time() + DAY_IN_SECONDS * 30, 'meta' => array( 'secret_meta' => 'SECRET-META-456' ) ) );
$tw = flxsm_upsert_account( array( 'provider' => 'x', 'via' => 'x', 'ext_id' => 'fx_x_' . wp_rand(), 'name' => 'Test X', 'username' => 'testx', 'token' => array( 'access_token' => 'SECRET-TOKEN-789' ) ) );

// Permissions.
$r = flxsm_t_op( 'FlxSocialBoot' );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'boot: visitor → UNAUTHENTICATED' );
$r = flxsm_t_op( 'FlxSocialBoot', array(), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'boot: subscriber → FORBIDDEN' );
$r = flxsm_t_op( 'FlxSocialSettings', array(), $author );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'settings: post-only user → FORBIDDEN' );
$r = flxsm_t_op( 'FlxSocialAccountUpdate', array( 'input' => array( 'id' => $fb, 'name' => 'X' ) ), $author );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'account update: post-only user → FORBIDDEN' );
$r = flxsm_t_op( 'FlxSocialLog', array(), $pub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'log: publisher without manage → FORBIDDEN' );

// Boot data matches the services.
$r = flxsm_t_op( 'FlxSocialBoot', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'boot: no errors for the administrator' );
$d = $r['data'] ?? array();
flxt_same( array( true, true, FLXSM_VERSION ), array( $d['flxSocialConfig']['canPublish'] ?? null, $d['flxSocialConfig']['canManage'] ?? null, $d['flxSocialConfig']['version'] ?? null ), 'boot: administrator can publish and manage' );
flxt_same( array_keys( flxsm_providers() ), array_column( $d['flxSocialConfig']['networks'] ?? array(), 'id' ), 'boot: networks match the providers' );
flxt_same( array_column( flxsm_accounts(), 'id' ), array_map( static fn( $a ) => $a['databaseId'], $d['flxSocialAccounts'] ?? array() ), 'boot: accounts match flxsm_accounts()' );
$acc = array_values( array_filter( $d['flxSocialAccounts'] ?? array(), static fn( $a ) => $a['databaseId'] === $fb ) )[0] ?? array();
flxt_same( array( 'Test Page', 'ACTIVE', 'facebook' ), array( $acc['name'] ?? null, $acc['status'] ?? null, $acc['provider'] ?? null ), 'boot: account fields' );
$r = flxsm_t_op( 'FlxSocialBoot', array(), $author );
flxt_same( array( false, false ), array( $r['data']['flxSocialConfig']['canPublish'] ?? null, $r['data']['flxSocialConfig']['canManage'] ?? null ), 'boot: post-only user cannot publish or manage' );

// Secrets never leave.
$blob = wp_json_encode( array( flxsm_t_op( 'FlxSocialBoot', array(), $admin ), flxsm_t_op( 'FlxSocialSettings', array(), $admin ), flxsm_t_op( 'FlxSocialAccounts', array(), $admin ) ) );
flxt_ok( false === strpos( (string) $blob, 'SECRET-TOKEN' ) && false === strpos( (string) $blob, 'SECRET-META' ) && false === strpos( (string) $blob, 'access_token' ), 'secrets: no token or secret meta in any account/settings field' );
flxsm_set_secret( 'client_secret', 'APP-SECRET-999' );
$r    = flxsm_t_op( 'FlxSocialSettings', array(), $admin );
$blob = (string) wp_json_encode( $r );
flxt_ok( false === strpos( $blob, 'APP-SECRET-999' ), 'secrets: app secret value is never returned' );
$sets = array();
foreach ( $r['data']['flxSocialApps'] ?? array() as $a ) {
	foreach ( $a['fields'] as $f ) {
		if ( $f['secret'] ) {
			$sets[] = $f['value'];
		}
	}
}
flxt_same( array( '' ), array_values( array_unique( $sets ) ), 'secrets: secret field values are empty' );
flxt_ok( false === strpos( (string) ( $r['data']['flxSocialSettings']['tickUrl'] ?? '' ), (string) get_option( 'flxsm_tick_key', 'none' ) ) || '' === (string) get_option( 'flxsm_tick_key', '' ), 'secrets: tick key is masked in settings' );
$r = flxsm_t_op( 'FlxSocialTickKeyReveal', array( 'input' => array() ), $admin );
flxt_ok( str_contains( (string) ( $r['data']['flxSocialTickKeyReveal']['url'] ?? '' ), 'key=' ), 'tick key: manager can reveal the full address' );
$r = flxsm_t_op( 'FlxSocialTickKeyReveal', array( 'input' => array() ), $pub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'tick key: publisher without manage → FORBIDDEN' );

// Create a post (draft) with two targets.
$in = array(
	'title'    => 'Merhaba',
	'content'  => "Ağaç dikiyoruz 🌳\nKatıl!",
	'tags'     => array( 'orman' ),
	'targets'  => array(
		array( 'accountId' => $fb, 'options' => array( 'link' => 'https://example.com/x', 'notify' => true ) ),
		array( 'accountId' => $tw, 'content' => 'Kısa metin' ),
	),
	'action'   => 'DRAFT',
);
$r  = flxsm_t_op( 'FlxSocialPostCreate', array( 'input' => $in ), $author );
$p  = $r['data']['flxSocialPostCreate']['post'] ?? array();
flxt_same( array(), $r['data']['flxSocialPostCreate']['errors'] ?? null, 'create: no errors' );
$pid = (int) ( $p['databaseId'] ?? 0 );
flxt_ok( $pid > 0, 'create: post id' );
flxt_same( array( 'DRAFT', 'Merhaba', array( 'orman' ), 2 ), array( $p['status'] ?? null, $p['title'] ?? null, $p['tags'] ?? null, count( $p['targets'] ?? array() ) ), 'create: draft with two targets' );
flxt_same( array( 'https://example.com/x', true ), array( $p['targets'][0]['options']['link'] ?? null, $p['targets'][0]['options']['notify'] ?? null ), 'create: target options round-trip' );
flxt_same( array( true, null, 'Kısa metin' ), array( array_key_exists( 'content', $p['targets'][0] ), $p['targets'][0]['content'], $p['targets'][1]['content'] ?? null ), 'create: own text only where given' );
flxt_same( ( new WP_User( $author ) )->display_name, $p['author'] ?? null, 'create: author name' );

// GraphQL and REST agree.
wp_set_current_user( $author );
$legacy = flxsm_post_public( flxsm_post_row( $pid ) );
flxt_same( array( $legacy['title'], $legacy['content'], $legacy['tags'], count( $legacy['targets'] ) ), array( $p['title'], $p['content'], $p['tags'], count( $p['targets'] ) ), 'parity: GraphQL post equals the REST post' );
$req = new WP_REST_Request( 'GET', '/' . FLXSM_NS . '/posts/' . $pid );
$res = rest_do_request( $req );
flxt_same( wp_json_encode( $legacy ), wp_json_encode( $res->get_data() ), 'parity: REST output is unchanged (served from the same builder)' );
wp_set_current_user( 0 );

// List.
$r = flxsm_t_op( 'FlxSocialPosts', array( 'first' => 10, 'status' => array( 'DRAFT' ), 'search' => 'Merhaba' ), $author );
flxt_same( array( $pid, 1 ), array( $r['data']['flxSocialPosts']['nodes'][0]['databaseId'] ?? null, $r['data']['flxSocialPosts']['totalCount'] ?? null ), 'list: filter by status and search' );
$r = flxsm_t_op( 'FlxSocialPosts', array( 'first' => 1, 'accountId' => $tw ), $author );
flxt_same( true, ( $r['data']['flxSocialPosts']['totalCount'] ?? 0 ) >= 1, 'list: filter by account' );
$r = flxsm_t_op( 'FlxSocialPosts', array( 'first' => 5, 'search' => 'zzz-yok-zzz' ), $author );
flxt_same( array( array(), 0 ), array( $r['data']['flxSocialPosts']['nodes'] ?? null, $r['data']['flxSocialPosts']['totalCount'] ?? null ), 'list: empty result' );
$r = flxsm_t_op( 'FlxSocialPosts', array( 'first' => 5, 'status' => array( 'NOPE' ) ), $author );
flxt_ok( ! empty( $r['errors'] ), 'list: unknown status enum is refused' );

// Update + permissions.
$r = flxsm_t_op( 'FlxSocialPostUpdate', array( 'input' => array( 'id' => $p['id'], 'content' => 'Yeni metin' ) ), $other );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'update: someone else\'s post → FORBIDDEN' );
$r = flxsm_t_op( 'FlxSocialPostUpdate', array( 'input' => array( 'id' => $p['id'], 'content' => 'Yeni metin' ) ), $author );
flxt_same( array( 'Yeni metin', 'Merhaba', 2 ), array( $r['data']['flxSocialPostUpdate']['post']['content'] ?? null, $r['data']['flxSocialPostUpdate']['post']['title'] ?? null, count( $r['data']['flxSocialPostUpdate']['post']['targets'] ?? array() ) ), 'update: only given fields change' );
$r = flxsm_t_op( 'FlxSocialPostUpdate', array( 'input' => array( 'id' => flx_gql_global_id( 'FlxSocialPost', 999999 ), 'title' => 'x' ) ), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $r ) ?? ( $r['data']['flxSocialPostUpdate']['errors'][0]['code'] ?? null ), 'update: unknown post → NOT_FOUND' );
$r = flxsm_t_op( 'FlxSocialPostUpdate', array( 'input' => array( 'id' => flx_gql_global_id( 'FlxSocialAccount', $fb ), 'title' => 'x' ) ), $admin );
flxt_same( 'INVALID_INPUT', $r['data']['flxSocialPostUpdate']['errors'][0]['code'] ?? flxt_code( $r ), 'update: global ID of another type → INVALID_INPUT' );

// State changes.
$r = flxsm_t_op( 'FlxSocialPostAct', array( 'input' => array( 'id' => $p['id'], 'action' => 'SUBMIT' ) ), $author );
flxt_same( 'PENDING', $r['data']['flxSocialPostAct']['post']['status'] ?? null, 'act: submit → PENDING' );
$r = flxsm_t_op( 'FlxSocialPostAct', array( 'input' => array( 'id' => $p['id'], 'action' => 'APPROVE' ) ), $author );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'act: author cannot approve' );
$r = flxsm_t_op( 'FlxSocialPostAct', array( 'input' => array( 'id' => $p['id'], 'action' => 'REJECT', 'note' => 'Düzelt' ) ), $pub );
flxt_same( array( 'DRAFT', 'Düzelt' ), array( $r['data']['flxSocialPostAct']['post']['status'] ?? null, $r['data']['flxSocialPostAct']['post']['note'] ?? null ), 'act: publisher sends it back with a note' );
$when = time() + 30 * DAY_IN_SECONDS;
$r    = flxsm_t_op( 'FlxSocialPostUpdate', array( 'input' => array( 'id' => $p['id'], 'scheduledAt' => $when, 'action' => 'SCHEDULE' ) ), $pub );
$sp   = $r['data']['flxSocialPostUpdate']['post'] ?? array();
flxt_same( array( 'SCHEDULED', $when ), array( $sp['status'] ?? null, $sp['scheduledAt']['timestamp'] ?? null ), 'schedule: status and time (far future, nothing is posted)' );
flxt_same( array( 'SCHEDULED', 'SCHEDULED' ), array_column( $sp['targets'] ?? array(), 'status' ), 'schedule: targets are scheduled' );
$r = flxsm_t_op( 'FlxSocialCounts', array(), $pub );
flxt_ok( ( $r['data']['flxSocialCounts']['scheduled'] ?? 0 ) >= 1 && 0 === ( $r['data']['flxSocialCounts']['due'] ?? -1 ), 'counts: scheduled, nothing due' );
$r = flxsm_t_op( 'FlxSocialTickRun', array( 'input' => array() ), $pub );
flxt_same( array( 0, 0 ), array( $r['data']['flxSocialTickRun']['processed'] ?? null, $r['data']['flxSocialTickRun']['due'] ?? null ), 'tick: nothing due, nothing processed' );
$r = flxsm_t_op( 'FlxSocialPostAct', array( 'input' => array( 'id' => $p['id'], 'action' => 'CANCEL' ) ), $pub );
flxt_same( 'CANCELLED', $r['data']['flxSocialPostAct']['post']['status'] ?? null, 'act: cancel' );

// Validation errors come back as payload errors.
$r = flxsm_t_op( 'FlxSocialPostCreate', array( 'input' => array( 'content' => 'x', 'targets' => array(), 'action' => 'SCHEDULE' ) ), $pub );
$e = $r['data']['flxSocialPostCreate']['errors'][0] ?? array();
flxt_same( 'INVALID_INPUT', $e['code'] ?? flxt_code( $r ), 'create: scheduling without an account → INVALID_INPUT' );

// Duplicate, bulk, delete.
$r  = flxsm_t_op( 'FlxSocialPostDuplicate', array( 'input' => array( 'id' => $p['id'] ) ), $pub );
$dp = $r['data']['flxSocialPostDuplicate']['post'] ?? array();
flxt_same( array( 'DRAFT', 'Merhaba (copy)', 2 ), array( $dp['status'] ?? null, $dp['title'] ?? null, count( $dp['targets'] ?? array() ) ), 'duplicate: a copy as a draft' );
$r = flxsm_t_op( 'FlxSocialPostBulkAct', array( 'input' => array( 'ids' => array( $dp['id'], $p['id'] ), 'action' => 'DELETE' ) ), $other );
flxt_same( 0, $r['data']['flxSocialPostBulkAct']['done'] ?? null, 'bulk: skips posts the user may not change' );
$r = flxsm_t_op( 'FlxSocialPostBulkAct', array( 'input' => array( 'ids' => array( $dp['id'] ), 'action' => 'DELETE' ) ), $pub );
flxt_same( 1, $r['data']['flxSocialPostBulkAct']['done'] ?? null, 'bulk: delete' );
$r = flxsm_t_op( 'FlxSocialPostDelete', array( 'input' => array( 'id' => $p['id'] ) ), $other );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'delete: someone else\'s post → FORBIDDEN' );
$r = flxsm_t_op( 'FlxSocialPostDelete', array( 'input' => array( 'id' => $p['id'] ) ), $pub );
flxt_same( $p['id'], $r['data']['flxSocialPostDelete']['deletedId'] ?? null, 'delete: returns the deleted ID' );
$gone = flxsm_t_op( 'FlxSocialPost', array( 'id' => $p['id'] ), $pub );
flxt_same( array( true, null ), array( array_key_exists( 'flxSocialPost', $gone['data'] ?? array() ), $gone['data']['flxSocialPost'] ?? null ), 'post: gone after delete' );

// Remote actions are refused without publish permission and never reach a network here.
$r = flxsm_t_op( 'FlxSocialTargetRemoteDelete', array( 'input' => array( 'id' => 1 ) ), $author );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'remote delete: post-only user → FORBIDDEN' );
$r = flxsm_t_op( 'FlxSocialTargetRetry', array( 'input' => array( 'id' => 999999 ) ), $pub );
flxt_same( 'INVALID_INPUT', $r['data']['flxSocialTargetRetry']['errors'][0]['code'] ?? flxt_code( $r ), 'retry: a target that did not fail cannot be retried' );

// Accounts.
$r = flxsm_t_op( 'FlxSocialAccountUpdate', array( 'input' => array( 'id' => $fb, 'name' => 'Yeni ad', 'disabled' => true ) ), $admin );
flxt_same( array( 'Yeni ad', 'DISABLED' ), array( $r['data']['flxSocialAccountUpdate']['account']['name'] ?? null, $r['data']['flxSocialAccountUpdate']['account']['status'] ?? null ), 'account: rename and switch off' );
$r = flxsm_t_op( 'FlxSocialAccountReorder', array( 'input' => array( 'ids' => array_merge( array( $tw, $fb ), array_values( array_diff( array_column( flxsm_accounts(), 'id' ), array( $tw, $fb ) ) ) ) ) ), $admin );
flxt_same( array( $tw, $fb ), array_slice( array_map( static fn( $a ) => $a['databaseId'], $r['data']['flxSocialAccountReorder']['accounts'] ?? array() ), 0, 2 ), 'account: reorder' );
$r = flxsm_t_op( 'FlxSocialAccountDelete', array( 'input' => array( 'id' => 999999 ) ), $admin );
flxt_ok( 'NOT_FOUND' === flxt_code( $r ) || ! empty( $r['errors'] ), 'account: unknown → error' );
$r = flxsm_t_op( 'FlxSocialConnectUrl', array( 'for' => 'facebook' ), $admin );
flxt_ok( str_contains( (string) ( $r['data']['flxSocialConnectUrl'] ?? '' ), 'oauth/start' ), 'connect url: points at the REST OAuth start' );
$r = flxsm_t_op( 'FlxSocialConnectUrl', array( 'for' => 'nope' ), $admin );
flxt_ok( ! empty( $r['errors'] ), 'connect url: unknown network → error' );

// Settings.
$r = flxsm_t_op( 'FlxSocialSettingsUpdate', array( 'input' => array( 'signature' => '#orman', 'xTier' => 'basic', 'auto' => array( 'enabled' => true, 'delay' => 5 ) ) ), $admin );
$s = $r['data']['flxSocialSettingsUpdate']['settings'] ?? array();
flxt_same( array( '#orman', 'basic', true, 5 ), array( $s['signature'] ?? null, $s['xTier'] ?? null, $s['auto']['enabled'] ?? null, $s['auto']['delay'] ?? null ), 'settings: update' );
flxt_same( array( '#orman', 'basic' ), array( flxsm_setting( 'signature' ), flxsm_setting( 'x_tier' ) ), 'settings: classic reads the same values' );
flxt_same( array( array( 'post' ), "{title}\n\n{excerpt}\n\n{link}" ), array( $s['auto']['postTypes'] ?? null, $s['auto']['template'] ?? null ), 'settings: auto keeps what was not sent' );
$r = flxsm_t_op( 'FlxSocialSettingsUpdate', array( 'input' => array( 'xTier' => 'nope' ) ), $admin );
flxt_same( 'basic', flxsm_setting( 'x_tier' ), 'settings: unknown X plan is ignored' );
$r = flxsm_t_op( 'FlxSocialAppUpdate', array( 'input' => array( 'id' => 'meta', 'values' => array( array( 'key' => 'app_id', 'value' => '12345' ) ) ) ), $admin );
$meta = array_values( array_filter( $r['data']['flxSocialAppUpdate']['apps'] ?? array(), static fn( $a ) => 'meta' === $a['id'] ) )[0] ?? array();
flxt_same( array( true ), array_values( array_unique( array_map( static fn( $f ) => $f['isSet'], array_filter( $meta['fields'] ?? array(), static fn( $f ) => 'app_id' === $f['key'] ) ) ) ), 'apps: a saved field reports isSet' );
$r = flxsm_t_op( 'FlxSocialAppUpdate', array( 'input' => array( 'id' => 'nope', 'values' => array() ) ), $admin );
flxt_ok( ! empty( $r['errors'] ) || ! empty( $r['data']['flxSocialAppUpdate']['errors'] ), 'apps: unknown app → error' );

// Dashboard, log, media.
$r = flxsm_t_op( 'FlxSocialStats', array( 'days' => 7 ), $author );
$st = $r['data']['flxSocialStats'] ?? array();
flxt_same( array( 7, 7 ), array( $st['days'] ?? null, count( $st['byDay'] ?? array() ) ), 'stats: 7 days of activity' );
$r = flxsm_t_op( 'FlxSocialStats', array( 'days' => 5 ), $author );
flxt_same( 30, $r['data']['flxSocialStats']['days'] ?? null, 'stats: unknown period falls back to 30' );
flxsm_log( 'contract test', 'info' );
$r = flxsm_t_op( 'FlxSocialLog', array(), $admin );
flxt_same( 'contract test', $r['data']['flxSocialLog'][0]['message'] ?? null, 'log: newest row first' );
$r = flxsm_t_op( 'FlxSocialMedia', array( 'page' => 1 ), $author );
flxt_same( array(), $r['errors'] ?? array(), 'media: library page' );
$r = flxsm_t_op( 'FlxSocialStudioMedia', array(), $author );
flxt_same( array(), $r['data']['flxSocialStudioMedia'] ?? null, 'studio: empty without Flux Studio' );
$r = flxsm_t_op( 'FlxSocialMediaAdd', array( 'input' => array( 'uploadId' => 'nope.nope' ) ), $author );
flxt_ok( ! empty( $r['errors'] ) || ! empty( $r['data']['flxSocialMediaAdd']['errors'] ), 'media add: unknown upload is an error' );
$r = flxsm_t_op( 'FlxSocialMediaAdd', array( 'input' => array( 'uploadId' => 'nope.nope' ) ), $pub );
flxt_same( 'FORBIDDEN', flxt_code( $r ) ?? ( $r['data']['flxSocialMediaAdd']['errors'][0]['code'] ?? null ), 'media add: without upload_files → FORBIDDEN' );
$r = flxsm_t_op( 'FlxSocialAiWrite', array( 'input' => array( 'mode' => 'WRITE', 'brief' => 'x' ) ), $author );
flxt_ok( ! empty( $r['errors'] ) || ! empty( $r['data']['flxSocialAiWrite']['errors'] ), 'ai: without Flux AI the call fails cleanly' );

flxt_done();
