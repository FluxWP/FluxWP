<?php
/**
 * Flux AI GraphQL contract tests. The documents are the persisted operations the Flux AI screens send
 * (plugins/flx-ai/graphql/persisted-queries.json). No provider is called: there are no keys on the test
 * site, so the model and renderer tests only check the error path.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-ai/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux AI GraphQL\n";

$GLOBALS['flxai_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXAI_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxai_ops'][ $m[1] ] = $doc;
	}
}

function flxai_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxai_ops'][ $name ], $vars, $user );
}

/** Any text of the response (to check that no key leaks). */
function flxai_t_json( array $r ): string {
	return (string) wp_json_encode( $r );
}

$admin = 1;
$sub   = (int) wp_insert_user( array( 'user_login' => 'flxai_sub_' . wp_rand(), 'user_email' => 'flxai_sub_' . wp_rand() . '@example.com', 'user_pass' => wp_generate_password(), 'role' => 'subscriber', 'display_name' => 'Ayşe Test' ) );

// Clean slate: settings and log of this test site.
delete_option( 'flxai_settings' );
$GLOBALS['flxai_settings_cache'] = null;
delete_option( 'flxai_or_credits' );
global $wpdb;
$wpdb->query( 'DELETE FROM ' . flxai_table( 'log' ) ); // phpcs:ignore
$wpdb->query( 'DELETE FROM ' . flxai_table( 'log_body' ) ); // phpcs:ignore

// Permissions: every operation is for administrators.
$vars = array(
	'FlxAiOverview'         => array(),
	'FlxAiLogs'             => array(),
	'FlxAiLogBody'          => array( 'id' => 1 ),
	'FlxAiBilling'          => array(),
	'FlxAiCreditsRefresh'   => array(),
	'FlxAiSystems'          => array(),
	'FlxAiSystemsUpdate'    => array( 'input' => array( 'systems' => array() ) ),
	'FlxAiSettingsScreen'   => array(),
	'FlxAiSettingsUpdate'   => array( 'input' => array() ),
	'FlxAiModels'           => array(),
	'FlxAiModelTest'        => array( 'input' => array( 'model' => '' ) ),
	'FlxAiRenderTest'       => array( 'input' => array( 'url' => '' ) ),
	'FlxAiApprovals'        => array(),
	'FlxAiApprovalDecide'   => array( 'input' => array( 'chatId' => 1, 'callId' => 'x', 'approve' => true ) ),
	'FlxAiChangeDecide'     => array( 'input' => array( 'id' => 1, 'merge' => false ) ),
	'FlxAiTasks'            => array(),
	'FlxAiRecipients'       => array( 'search' => 'a' ),
	'FlxAiTaskSave'         => array( 'input' => array( 'prompt' => 'x' ) ),
	'FlxAiTaskRun'          => array( 'input' => array( 'id' => 1 ) ),
	'FlxAiTaskSetActive'    => array( 'input' => array( 'id' => 1, 'active' => false ) ),
	'FlxAiTaskDelete'       => array( 'input' => array( 'id' => 1 ) ),
	'FlxAiTaskMarkRead'     => array( 'input' => array( 'id' => 1 ) ),
	'FlxAiTasksToggle'      => array( 'input' => array( 'enabled' => true ) ),
);
foreach ( $vars as $op => $v ) {
	flxt_same( 'UNAUTHENTICATED', flxt_code( flxai_t_op( $op, $v ) ), "$op: visitor → UNAUTHENTICATED" );
	flxt_same( 'FORBIDDEN', flxt_code( flxai_t_op( $op, $v, $sub ) ), "$op: subscriber → FORBIDDEN" );
}

// The generic settings types must not list Flux AI (they would expose the key_* fields).
$r = flxt_gql( '{ __type(name: "FlxSettingsFlxaiSettings") { name } }', array(), $admin );
flxt_ok( array_key_exists( '__type', $r['data'] ?? array() ) && null === $r['data']['__type'], 'generic settings types leave Flux AI out', $r );
$r = flxt_gql( '{ __type(name: "FlxAiSettings") { fields { name } } }', array(), $admin );
$names = array_column( $r['data']['__type']['fields'] ?? array(), 'name' );
flxt_ok( ! preg_grep( '/^key[A-Z]/', $names ), 'FlxAiSettings has no key field', $names );

// Settings: defaults.
$r = flxai_t_op( 'FlxAiSettingsScreen', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'settings screen: no errors' );
$s = $r['data']['flxAiSettings'] ?? array();
flxt_same( 'FIVE_MINUTES', $s['cache'] ?? null, 'settings: cache default' );
flxt_same( 'LOW', $s['effort'] ?? null, 'settings: effort default' );
flxt_same( 'BLOCK', $s['limitAction'] ?? null, 'settings: limit action default' );
flxt_same( 'BUBBLE', $s['chatMode'] ?? null, 'settings: chat mode default' );
flxt_same( 80, $s['limitWarnPct'] ?? null, 'settings: warn threshold default' );
flxt_same( array( 'administrator' ), $s['chatRoles'] ?? null, 'settings: chat roles default' );
$rows = array_column( $s['sourceModels'] ?? array(), 'source' );
flxt_ok( in_array( 'flx-chat', $rows, true ) && ! in_array( 'flx-ai', $rows, true ), 'settings: per-plugin models list the plugins, not Flux AI itself', $rows );
$sec = array_column( $r['data']['flxAiSecrets'] ?? array(), null, 'name' );
flxt_ok( isset( $sec['key_openrouter'], $sec['key_anthropic'], $sec['key_fal'], $sec['key_render'], $sec['key_brave'] ), 'secrets: provider, media, render and web keys listed', array_keys( $sec ) );
flxt_same( false, $sec['key_openrouter']['configured'] ?? null, 'secrets: nothing configured yet' );
$o = $r['data']['flxAiSettingsOptions'] ?? array();
flxt_ok( in_array( 'administrator', array_column( $o['roles'] ?? array(), 'id' ), true ), 'options: roles' );
flxt_ok( in_array( 'brave', array_column( $o['webProviders'] ?? array(), 'id' ), true ), 'options: web providers' );
flxt_ok( count( $o['mediaModels'] ?? array() ) > 3, 'options: media catalog' );
flxt_same( false, $o['providersReady'] ?? null, 'options: no provider key yet' );

// Settings: partial update, clamps, validation.
$r = flxai_t_op( 'FlxAiSettingsUpdate', array( 'input' => array(
	'model'         => 'google/gemini-2.5-flash',
	'cache'         => 'ONE_HOUR',
	'effort'        => 'HIGH',
	'limitMonth'    => 12.345,
	'limitWarnPct'  => 5,
	'chatMaxRounds' => 99,
	'chatMode'      => 'DOCK',
	'chatRoles'     => array( 'administrator', 'editor', 'nonexistent' ),
	'logDays'       => 30,
	'sourceModels'  => array( array( 'source' => 'flx-chat', 'model' => 'claude:claude-haiku-5-5' ), array( 'source' => 'flx-tasks', 'model' => '' ) ),
	'webProvider'   => 'brave',
) ), $admin );
flxt_same( null, $r['errors'] ?? null, 'settings update: no errors' );
$p = $r['data']['flxAiSettingsUpdate'] ?? array();
flxt_same( array(), $p['errors'] ?? null, 'settings update: no input errors' );
$s = $p['settings'] ?? array();
flxt_same( 'ONE_HOUR', $s['cache'] ?? null, 'settings update: cache' );
flxt_same( 'HIGH', $s['effort'] ?? null, 'settings update: effort' );
flxt_same( 12.35, $s['limitMonth'] ?? null, 'settings update: limit rounded to cents' );
flxt_same( 10, $s['limitWarnPct'] ?? null, 'settings update: warn threshold clamped to 10' );
flxt_same( 30, $s['chatMaxRounds'] ?? null, 'settings update: max steps clamped to 30' );
flxt_same( 'DOCK', $s['chatMode'] ?? null, 'settings update: chat mode' );
flxt_same( array( 'administrator', 'editor' ), $s['chatRoles'] ?? null, 'settings update: unknown roles dropped' );
flxt_same( 30, $s['logDays'] ?? null, 'settings update: log days' );
flxt_same( 'google/gemini-2.5-flash', $s['model'] ?? null, 'settings update: model' );
$sm = array_column( $s['sourceModels'] ?? array(), 'model', 'source' );
flxt_same( 'claude:claude-haiku-5-5', $sm['flx-chat'] ?? null, 'settings update: model of a plugin' );
flxt_same( '', $sm['flx-tasks'] ?? null, 'settings update: empty model = default' );
flxt_same( 'HIGH', flxai_t_op( 'FlxAiSettingsScreen', array(), $admin )['data']['flxAiSettings']['effort'] ?? null, 'settings update: persisted' );
flxt_same( 40, flxai_t_op( 'FlxAiSettingsScreen', array(), $admin )['data']['flxAiSettings']['chatHistory'] ?? null, 'settings update: untouched field keeps its value' );
flxt_same( 'high', flxai_setting( 'effort' ), 'settings update: classic reader sees it' );

foreach ( array(
	'webProvider'     => 'nosuchprovider',
	'mediaImageModel' => 'no-such-model',
) as $field => $bad ) {
	$r = flxai_t_op( 'FlxAiSettingsUpdate', array( 'input' => array( $field => $bad ) ), $admin );
	$e = $r['data']['flxAiSettingsUpdate']['errors'][0] ?? array();
	flxt_same( 'INVALID_INPUT', $e['code'] ?? null, "settings update: $field invalid → INVALID_INPUT" );
}
$r = flxai_t_op( 'FlxAiSettingsUpdate', array( 'input' => array( 'effort' => 'LOW', 'mediaVideoModel' => 'gpt-image-2.5-sunburst' ) ), $admin );
flxt_same( 'INVALID_INPUT', $r['data']['flxAiSettingsUpdate']['errors'][0]['code'] ?? null, 'settings update: an image model is not a video model' );
flxt_same( 'high', flxai_setting( 'effort' ), 'settings update: nothing written when a value is invalid' );

// Keys are write-only.
$secret = 'sk-or-v1-' . str_repeat( 'a1b2c3d4', 6 ) . 'WXYZ';
$r      = flxai_t_op( 'FlxAiSettingsUpdate', array( 'input' => array( 'secrets' => array( array( 'name' => 'key_openrouter', 'value' => $secret ) ) ) ), $admin );
flxt_same( array(), $r['data']['flxAiSettingsUpdate']['errors'] ?? null, 'secret: saved' );
flxt_ok( false === strpos( flxai_t_json( $r ), $secret ), 'secret: the response never carries the key' );
$sec = array_column( $r['data']['flxAiSettingsUpdate']['secrets'] ?? array(), null, 'name' );
flxt_same( true, $sec['key_openrouter']['configured'] ?? null, 'secret: configured' );
flxt_same( 'WXYZ', $sec['key_openrouter']['hint'] ?? null, 'secret: only the last four characters' );
flxt_ok( false === strpos( (string) wp_json_encode( get_option( 'flxai_settings' ) ), $secret ), 'secret: stored sealed, not as text' );
flxt_same( $secret, flxai_setting( 'key_openrouter' ), 'secret: the plugin still reads the key' );
$all = flxai_t_json( flxai_t_op( 'FlxAiSettingsScreen', array(), $admin ) );
flxt_ok( false === strpos( $all, $secret ) && false === strpos( $all, substr( $secret, 0, 20 ) ), 'secret: queries never carry the key' );
flxt_same( true, flxai_t_op( 'FlxAiSettingsScreen', array(), $admin )['data']['flxAiSettingsOptions']['providersReady'] ?? null, 'options: provider ready once a key is saved' );
$r = flxai_t_op( 'FlxAiSettingsUpdate', array( 'input' => array( 'effort' => 'MEDIUM', 'secrets' => array( array( 'name' => 'key_openrouter', 'value' => '' ) ) ) ), $admin );
flxt_same( $secret, flxai_setting( 'key_openrouter' ), 'secret: an empty value leaves the key alone' );
$r = flxai_t_op( 'FlxAiSettingsUpdate', array( 'input' => array( 'secrets' => array( array( 'name' => 'key_nonexistent', 'value' => 'x' ) ) ) ), $admin );
flxt_same( 'INVALID_INPUT', $r['data']['flxAiSettingsUpdate']['errors'][0]['code'] ?? null, 'secret: unknown key name → INVALID_INPUT' );
$r = flxai_t_op( 'FlxAiSettingsUpdate', array( 'input' => array( 'secrets' => array( array( 'name' => 'key_openrouter', 'clear' => true ) ) ) ), $admin );
flxt_same( '', flxai_setting( 'key_openrouter' ), 'secret: removed' );
flxt_same( false, array_column( $r['data']['flxAiSettingsUpdate']['secrets'] ?? array(), null, 'name' )['key_openrouter']['configured'] ?? null, 'secret: not configured after removing' );

// Model and renderer tests: no keys, so only the error path (no provider is called).
$r = flxai_t_op( 'FlxAiModelTest', array( 'input' => array( 'model' => '' ) ), $admin );
flxt_ok( ! empty( $r['data']['flxAiModelTest']['errors'] ) || ! empty( $r['errors'] ), 'model test: no key → an error, no provider call', $r );
$r = flxai_t_op( 'FlxAiRenderTest', array( 'input' => array( 'url' => '' ) ), $admin );
// Without Gotenberg the local Imagick renderer may still work (CI has it), so both outcomes are fine; what matters is a clean answer.
$rt = $r['data']['flxAiRenderTest'] ?? array();
flxt_ok( ! empty( $rt['errors'] ) || ! empty( $r['errors'] ) || ! empty( $rt['result']['via'] ), 'render test: an error or a local render, never a crash', $r );

// Usage: log rows (the model test above wrote a failed row of its own).
$wpdb->query( 'DELETE FROM ' . flxai_table( 'log' ) ); // phpcs:ignore
$wpdb->query( 'DELETE FROM ' . flxai_table( 'log_body' ) ); // phpcs:ignore
$now  = time();
$rows = array(
	array( 'ts' => $now - 60, 'kind' => 'model', 'source' => 'flx-chat', 'purpose' => 'chat', 'user_id' => $admin, 'chat_id' => 7, 'provider' => 'openrouter', 'model' => 'google/gemini-2.5-flash', 'in_tok' => 1000, 'out_tok' => 200, 'cache_read' => 400, 'cache_write' => 0, 'cost' => 0.012, 'full_cost' => 0.02, 'est' => 0, 'ms' => 1500, 'ok' => 1 ),
	array( 'ts' => $now - 120, 'kind' => 'tool', 'source' => 'flx-chat', 'purpose' => '', 'user_id' => $admin, 'tool' => 'flxseo_audit', 'ms' => 300, 'ok' => 1 ),
	array( 'ts' => $now - 180, 'kind' => 'model', 'source' => 'flx-tasks', 'purpose' => 'chat', 'provider' => 'anthropic', 'model' => 'claude:claude-haiku-5-5', 'in_tok' => 500, 'out_tok' => 100, 'cost' => 0.005, 'full_cost' => 0.005, 'est' => 1, 'ms' => 900, 'ok' => 0, 'error' => 'limit' ),
	array( 'ts' => $now - 9 * DAY_IN_SECONDS, 'kind' => 'model', 'source' => 'flx-chat', 'purpose' => 'chat', 'model' => 'google/gemini-2.5-flash', 'in_tok' => 10, 'out_tok' => 10, 'cost' => 1.5, 'full_cost' => 1.5, 'ms' => 100, 'ok' => 1 ),
);
$ids = array();
foreach ( $rows as $row ) {
	$wpdb->insert( flxai_table( 'log' ), $row ); // phpcs:ignore
	$ids[] = (int) $wpdb->insert_id;
}
$wpdb->insert( flxai_table( 'log_body' ), array( 'log_id' => $ids[0], 'ts' => $now, 'req' => wp_json_encode( array( array( 'system', 'Be brief.' ), array( 'user', 'Hello t***@e***.com' ) ) ), 'res' => wp_json_encode( array( 'text' => 'Hi!', 'tools' => array( 'flxseo_audit {}' ) ) ) ) ); // phpcs:ignore
flxai_spent( true );

$r = flxai_t_op( 'FlxAiOverview', array( 'days' => 7 ), $admin );
flxt_same( null, $r['errors'] ?? null, 'overview: no errors' );
$d = $r['data'] ?? array();
$b = array_column( $d['flxAiBudget'] ?? array(), null, 'key' );
flxt_same( array( 'day', 'week', 'month' ), array_keys( $b ), 'overview: three limit periods' );
flxt_ok( abs( ( $b['day']['spent'] ?? -1 ) - 0.017 ) < 0.0001, 'overview: spent today', $b['day'] ?? null );
$t = $d['flxAiReport']['totals'] ?? array();
flxt_same( 2, $t['calls'] ?? null, 'overview: model requests of the last 7 days (old row excluded)' );
flxt_same( 1, $t['tools'] ?? null, 'overview: tool calls' );
flxt_same( 1500, $t['inTokens'] ?? null, 'overview: input tokens' );
flxt_ok( abs( ( $t['cost'] ?? 0 ) - 0.017 ) < 0.0001, 'overview: cost', $t );
flxt_ok( abs( ( $t['saved'] ?? 0 ) - 0.008 ) < 0.0001, 'overview: cache savings', $t );
flxt_same( 1, $t['errors'] ?? null, 'overview: errors' );
flxt_same( 7, count( $d['flxAiReport']['daily'] ?? array() ), 'overview: one entry per day' );
$bs = array_column( $d['flxAiReport']['bySource'] ?? array(), null, 'key' );
flxt_same( 'Flux Chat', $bs['flx-chat']['label'] ?? null, 'overview: by plugin label' );
flxt_same( 1, $bs['flx-chat']['tools'] ?? null, 'overview: by plugin tool calls' );
flxt_same( 'flxseo_audit', $d['flxAiReport']['byTool'][0]['key'] ?? null, 'overview: by tool' );
$r = flxai_t_op( 'FlxAiOverview', array( 'days' => 7, 'source' => 'flx-tasks' ), $admin );
flxt_same( 1, $r['data']['flxAiReport']['totals']['calls'] ?? null, 'overview: source filter' );
flxt_ok( in_array( 'flx-chat', array_column( $r['data']['flxAiLogSources'] ?? array(), 'id' ), true ), 'overview: source choices' );

$r = flxai_t_op( 'FlxAiLogs', array( 'days' => 7, 'first' => 2 ), $admin );
$l = $r['data']['flxAiLogs'] ?? array();
flxt_same( 3, $l['totalCount'] ?? null, 'logs: 3 rows in 7 days' );
flxt_same( 2, count( $l['nodes'] ?? array() ), 'logs: page size' );
flxt_same( true, $l['pageInfo']['hasNextPage'] ?? null, 'logs: next page' );
flxt_same( array( $ids[2], $ids[1] ), array_column( $l['nodes'] ?? array(), 'databaseId' ), 'logs: newest row first' );
flxt_same( 'Spending limit reached', $l['nodes'][0]['errorLabel'] ?? null, 'logs: failure label' );
flxt_same( false, $l['nodes'][0]['ok'] ?? null, 'logs: failed row' );
flxt_same( true, $l['nodes'][0]['estimated'] ?? null, 'logs: estimated cost flag' );
$r   = flxai_t_op( 'FlxAiLogs', array( 'days' => 7, 'first' => 2, 'after' => $l['pageInfo']['endCursor'] ), $admin );
$one = $r['data']['flxAiLogs']['nodes'][0] ?? array();
flxt_same( 1, count( $r['data']['flxAiLogs']['nodes'] ?? array() ), 'logs: second page' );
flxt_same( $ids[0], $one['databaseId'] ?? null, 'logs: second page row' );
flxt_same( true, $one['hasBody'] ?? null, 'logs: has content' );
flxt_same( get_userdata( $admin )->display_name, $one['userName'] ?? null, 'logs: user name' );
flxt_same( 'Flux Chat', $one['sourceLabel'] ?? null, 'logs: source label' );
flxt_same( 'chat', $one['purposeLabel'] ?? null, 'logs: purpose label' );
flxt_same( 'MODEL', $one['kind'] ?? null, 'logs: kind' );
flxt_same( 2, flxai_t_op( 'FlxAiLogs', array( 'days' => 7, 'kind' => 'MODEL' ), $admin )['data']['flxAiLogs']['totalCount'] ?? null, 'logs: kind filter' );
flxt_same( 1, flxai_t_op( 'FlxAiLogs', array( 'days' => 7, 'errorsOnly' => true ), $admin )['data']['flxAiLogs']['totalCount'] ?? null, 'logs: errors only' );
flxt_same( 1, flxai_t_op( 'FlxAiLogs', array( 'days' => 7, 'source' => 'flx-tasks' ), $admin )['data']['flxAiLogs']['totalCount'] ?? null, 'logs: source filter' );
flxt_same( 4, flxai_t_op( 'FlxAiLogs', array( 'days' => 30 ), $admin )['data']['flxAiLogs']['totalCount'] ?? null, 'logs: 30 days include the old row' );

$r = flxai_t_op( 'FlxAiLogBody', array( 'id' => $ids[0] ), $admin );
$b = $r['data']['flxAiLogBody'] ?? array();
flxt_same( 'Hi!', $b['reply'] ?? null, 'log body: reply' );
flxt_same( 'system', $b['request'][0]['role'] ?? null, 'log body: request messages' );
flxt_same( array( 'flxseo_audit {}' ), $b['toolCalls'] ?? null, 'log body: tool calls' );
$r = flxai_t_op( 'FlxAiLogBody', array( 'id' => $ids[1] ), $admin );
flxt_ok( array_key_exists( 'flxAiLogBody', $r['data'] ?? array() ) && null === $r['data']['flxAiLogBody'], 'log body: none stored → null', $r );

// Billing.
$r  = flxai_t_op( 'FlxAiBilling', array(), $admin );
$bl = $r['data']['flxAiBilling'] ?? array();
flxt_same( wp_date( 'Y-m' ), $bl['month'] ?? null, 'billing: this month by default' );
flxt_same( 12, count( $bl['months'] ?? array() ), 'billing: 12 months' );
flxt_ok( ( $bl['report']['totals']['cost'] ?? 0 ) > 0.01, 'billing: month cost', $bl['report']['totals'] ?? null );
$prov = array_column( $bl['report']['byProvider'] ?? array(), null, 'key' );
flxt_same( 'OpenRouter', $prov['openrouter']['label'] ?? null, 'billing: provider label' );
flxt_same( true, $prov['anthropic']['partial'] ?? null, 'billing: estimated rows flagged' );
flxt_ok( array_key_exists( 'credits', $bl ) && null === $bl['credits'], 'billing: no credit state before a refresh', $bl['credits'] ?? 'missing' );
$r = flxai_t_op( 'FlxAiBilling', array( 'month' => '2020-01' ), $admin );
flxt_same( '2020-01', $r['data']['flxAiBilling']['month'] ?? null, 'billing: another month' );
flxt_same( 0.0, (float) ( $r['data']['flxAiBilling']['report']['totals']['cost'] ?? -1 ), 'billing: empty month' );
$r = flxai_t_op( 'FlxAiCreditsRefresh', array(), $admin );
flxt_ok( ! empty( $r['data']['flxAiCreditsRefresh']['errors'] ) || ! empty( $r['errors'] ), 'credits: no key → an error', $r );
update_option( 'flxai_or_credits', array( 'checked' => $now, 'usage' => 1.5, 'limit' => null, 'remaining' => null, 'credits' => 8.25 ), false );
$cr = flxai_t_op( 'FlxAiBilling', array(), $admin )['data']['flxAiBilling']['credits'] ?? array();
flxt_same( 8.25, $cr['credits'] ?? null, 'billing: stored credit state' );
flxt_ok( array_key_exists( 'limit', $cr ) && null === $cr['limit'], 'billing: no key limit → null', $cr );

// Systems.
$r   = flxai_t_op( 'FlxAiSystems', array(), $admin );
$sys = array_column( $r['data']['flxAiSystems'] ?? array(), null, 'source' );
flxt_ok( isset( $sys['flx-chat'], $sys['flx-tasks'], $sys['flx-ai'] ), 'systems: known systems listed', array_keys( $sys ) );
flxt_same( true, $sys['flx-chat']['on'] ?? null, 'systems: on by default' );
flxt_same( 2, $sys['flx-chat']['calls'] ?? null, 'systems: calls in 30 days (the 9-day-old row counts)' );
flxt_same( 1, $sys['flx-chat']['tools'] ?? null, 'systems: tool calls in 30 days' );
flxt_same( 'claude:claude-haiku-5-5', $sys['flx-chat']['model'] ?? null, 'systems: the model it uses' );
$r = flxai_t_op( 'FlxAiSystemsUpdate', array( 'input' => array( 'systems' => array( array( 'source' => 'flx-chat', 'on' => false ) ) ) ), $admin );
flxt_same( array(), $r['data']['flxAiSystemsUpdate']['errors'] ?? null, 'systems update: no errors' );
flxt_same( false, array_column( $r['data']['flxAiSystemsUpdate']['systems'] ?? array(), null, 'source' )['flx-chat']['on'] ?? null, 'systems update: switched off' );
flxt_same( false, flxai_source_enabled( 'flx-chat' ), 'systems update: classic reader sees it' );
flxt_same( true, flxai_source_enabled( 'flx-tasks' ), 'systems update: others keep their state' );
$r = flxai_t_op( 'FlxAiSystemsUpdate', array( 'input' => array( 'systems' => array( array( 'source' => 'flx-chat', 'on' => true ) ) ) ), $admin );
flxt_same( true, flxai_source_enabled( 'flx-chat' ), 'systems update: switched on again' );

// Model catalog from a stored list (no provider call).
set_transient( 'flxai_models_all', array(
	array( 'id' => 'google/gemini-2.5-flash', 'name' => 'Google: Gemini 2.5 Flash', 'family' => 'Google', 'provider' => 'openrouter', 'ctx' => 1000000, 'in' => 0.3, 'out' => 2.5, 'tools' => true, 'vision' => true, 'thinking' => true ),
	array( 'id' => 'claude:claude-haiku-5-5', 'name' => 'Claude Haiku 5.5', 'family' => 'Claude', 'provider' => 'anthropic', 'ctx' => 200000, 'in' => 1, 'out' => 5, 'tools' => true, 'vision' => true, 'thinking' => false ),
), HOUR_IN_SECONDS );
$r = flxai_t_op( 'FlxAiModels', array( 'search' => 'haiku' ), $admin );
flxt_same( array( 'claude:claude-haiku-5-5' ), array_column( $r['data']['flxAiModels'] ?? array(), 'id' ), 'models: search' );
flxt_same( 'ANTHROPIC', $r['data']['flxAiModels'][0]['provider'] ?? null, 'models: provider enum' );
flxt_same( 2, count( flxai_t_op( 'FlxAiModels', array(), $admin )['data']['flxAiModels'] ?? array() ), 'models: all without a search' );
delete_transient( 'flxai_models_all' );


// Tools.
$r     = flxai_t_op( 'FlxAiTools', array(), $admin );
$tools = $r['data']['flxAiTools'] ?? array();
flxt_ok( is_array( $tools ) && count( $tools ) > 0, 'tools: listed', $r['errors'] ?? null );
$first = (string) ( $tools[0]['name'] ?? '' );
flxt_ok( '' !== $first && isset( $tools[0]['calls'], $tools[0]['sourceLabel'] ), 'tools: row has name, calls and source' );
$r = flxai_t_op( 'FlxAiToolsUpdate', array( 'input' => array( 'tools' => array( array( 'name' => $first, 'on' => false ) ) ) ), $admin );
flxt_same( array(), $r['data']['flxAiToolsUpdate']['errors'] ?? null, 'tools update: no errors' );
flxt_same( true, array_column( $r['data']['flxAiToolsUpdate']['tools'] ?? array(), null, 'name' )[ $first ]['off'] ?? null, 'tools update: switched off' );
flxt_same( true, flxai_tool_is_off( $first ), 'tools update: classic reader sees it' );
flxai_t_op( 'FlxAiToolsUpdate', array( 'input' => array( 'tools' => array( array( 'name' => $first, 'on' => true ) ) ) ), $admin );
flxt_same( false, flxai_tool_is_off( $first ), 'tools update: switched on again' );
$r = flxai_t_op( 'FlxAiToolsUpdate', array( 'input' => array( 'tools' => array( array( 'name' => 'no_such_tool', 'on' => false ) ) ) ), $admin );
flxt_same( 'INVALID_INPUT', $r['data']['flxAiToolsUpdate']['errors'][0]['code'] ?? $r['errors'][0]['extensions']['code'] ?? null, 'tools update: unknown tool is refused' );
$r = flxai_t_op( 'FlxAiToolPick', array( 'input' => array( 'name' => $first, 'source' => 'flx-nope' ) ), $admin );
flxt_same( 'INVALID_INPUT', $r['data']['flxAiToolPick']['errors'][0]['code'] ?? $r['errors'][0]['extensions']['code'] ?? null, 'tool pick: not a rival is refused' );
foreach ( array( 'FlxAiTools' => array(), 'FlxAiToolsUpdate' => array( 'input' => array( 'tools' => array() ) ), 'FlxAiToolPick' => array( 'input' => array( 'name' => 'x', 'source' => 'y' ) ) ) as $op => $v ) {
	flxt_same( 'FORBIDDEN', flxt_code( flxai_t_op( $op, $v, $sub ) ), "$op: subscriber → FORBIDDEN" );
}

// Approvals: an empty queue, refused decisions and invalid input.
$r = flxai_t_op( 'FlxAiApprovals', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'approvals: no errors' );
flxt_same( 0, $r['data']['flxAiApprovals']['count'] ?? null, 'approvals: the queue is empty' );
$r = flxai_t_op( 'FlxAiApprovalDecide', array( 'input' => array( 'chatId' => 999999, 'callId' => 'nope', 'approve' => true ) ), $admin );
flxt_same( 'NOT_FOUND', $r['data']['flxAiApprovalDecide']['errors'][0]['code'] ?? $r['errors'][0]['extensions']['code'] ?? null, 'approval decide: an unknown action is NOT_FOUND' );
$r = flxai_t_op( 'FlxAiApprovalDecide', array( 'input' => array( 'chatId' => 1, 'callId' => 'nope', 'approve' => true, 'args' => '{not json' ) ), $admin );
flxt_same( 'INVALID_INPUT', $r['data']['flxAiApprovalDecide']['errors'][0]['code'] ?? $r['errors'][0]['extensions']['code'] ?? null, 'approval decide: invalid JSON is refused' );
$r = flxai_t_op( 'FlxAiChangeDecide', array( 'input' => array( 'id' => 999999, 'merge' => false ) ), $admin );
flxt_same( 'NOT_FOUND', $r['data']['flxAiChangeDecide']['errors'][0]['code'] ?? $r['errors'][0]['extensions']['code'] ?? null, 'change decide: an unknown change request is NOT_FOUND' );

// Scheduled tasks: create, validate, change, pause, run, delete.
$GLOBALS['wpdb']->query( 'DELETE FROM ' . flxai_table( 'tasks' ) ); // phpcs:ignore
$code = static function ( array $r, string $field ) {
	return $r['data'][ $field ]['errors'][0]['code'] ?? $r['errors'][0]['extensions']['code'] ?? null;
};
$r = flxai_t_op( 'FlxAiTasks', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'tasks: no errors' );
flxt_same( array(), $r['data']['flxAiTasks']['tasks'] ?? null, 'tasks: none yet' );
$r = flxai_t_op( 'FlxAiTaskSave', array( 'input' => array( 'prompt' => '   ' ) ), $admin );
flxt_same( 'INVALID_INPUT', $code( $r, 'flxAiTaskSave' ), 'task save: empty instructions are refused' );
$r = flxai_t_op( 'FlxAiTaskSave', array( 'input' => array( 'prompt' => 'Summarise donations.', 'schedule' => array( 'freq' => 'once', 'at' => '2001-01-01 10:00' ) ) ), $admin );
flxt_same( 'INVALID_INPUT', $code( $r, 'flxAiTaskSave' ), 'task save: a one-time task in the past is refused' );
$r = flxai_t_op( 'FlxAiTaskSave', array( 'input' => array( 'prompt' => 'Summarise donations.', 'skill' => 'no-such-skill' ) ), $admin );
flxt_same( 'INVALID_INPUT', $code( $r, 'flxAiTaskSave' ), 'task save: an unknown skill is refused' );
$r = flxai_t_op( 'FlxAiTaskSave', array( 'input' => array(
	'prompt'   => 'Summarise yesterday’s donations.',
	'schedule' => array( 'freq' => 'weekly', 'time' => '08:30', 'days' => array( 3, 1, 3, 9 ) ),
	'notify'   => array( 'mode' => 'important', 'panel' => true, 'to' => array( array( 'kind' => 'email', 'email' => 'ops@example.com', 'channels' => array( 'email' ) ) ) ),
) ), $admin );
flxt_same( null, $r['errors'] ?? null, 'task save: no errors' );
flxt_same( array(), $r['data']['flxAiTaskSave']['errors'] ?? null, 'task save: accepted' );
$t  = $r['data']['flxAiTaskSave']['task'] ?? array();
$tid = (int) ( $t['id'] ?? 0 );
flxt_ok( $tid > 0, 'task save: id returned', $t );
flxt_same( 'weekly', $t['schedule']['freq'] ?? null, 'task save: frequency' );
flxt_same( '08:30', $t['schedule']['time'] ?? null, 'task save: time' );
flxt_same( array( 1, 3 ), $t['schedule']['days'] ?? null, 'task save: weekdays are cleaned and sorted' );
flxt_same( 'active', $t['status'] ?? null, 'task save: active by default' );
flxt_ok( ! empty( $t['nextRun']['timestamp'] ), 'task save: next run is planned', $t );
flxt_same( 'Summarise yesterday’s donations.', $t['title'] ?? null, 'task save: the title comes from the instructions' );
flxt_same( true, $t['mine'] ?? null, 'task save: owned by the caller' );
flxt_ok( 'ops@example.com' === ( $t['notify']['to'][0]['email'] ?? '' ), 'task save: recipient kept', $t['notify'] ?? null );
flxt_ok( flxai_task_get( $tid ) !== null, 'task save: classic reader sees it' );

$r = flxai_t_op( 'FlxAiTaskSave', array( 'input' => array( 'id' => $tid, 'title' => 'Weekly donations' ) ), $admin );
$t = $r['data']['flxAiTaskSave']['task'] ?? array();
flxt_same( 'Weekly donations', $t['title'] ?? null, 'task update: title changed' );
flxt_same( array( 1, 3 ), $t['schedule']['days'] ?? null, 'task update: fields left out keep their value' );
flxt_same( 'Summarise yesterday’s donations.', $t['prompt'] ?? null, 'task update: instructions kept' );

$r = flxai_t_op( 'FlxAiTaskSetActive', array( 'input' => array( 'id' => $tid, 'active' => false ) ), $admin );
flxt_same( 'paused', $r['data']['flxAiTaskSetActive']['task']['status'] ?? null, 'task pause: status' );
flxt_same( null, $r['data']['flxAiTaskSetActive']['task']['nextRun'] ?? null, 'task pause: no next run' );
$r = flxai_t_op( 'FlxAiTaskSetActive', array( 'input' => array( 'id' => $tid, 'active' => true ) ), $admin );
flxt_same( 'active', $r['data']['flxAiTaskSetActive']['task']['status'] ?? null, 'task resume: status' );

$r = flxai_t_op( 'FlxAiTasks', array(), $admin );
$o = $r['data']['flxAiTasks'] ?? array();
flxt_same( 1, count( $o['tasks'] ?? array() ), 'tasks: the list has the task' );
flxt_same( true, $o['enabled'] ?? null, 'tasks: enabled by default' );

$r = flxai_t_op( 'FlxAiTasksToggle', array( 'input' => array( 'enabled' => false ) ), $admin );
flxt_same( false, $r['data']['flxAiTasksToggle']['overview']['enabled'] ?? null, 'tasks toggle: turned off' );
flxai_t_op( 'FlxAiTasksToggle', array( 'input' => array( 'enabled' => true ) ), $admin );

$r = flxai_t_op( 'FlxAiRecipients', array( 'search' => 'ops@example.com' ), $admin );
flxt_same( null, $r['errors'] ?? null, 'recipients: no errors' );
flxt_ok( isset( $r['data']['flxAiRecipients']['channels']['email'] ), 'recipients: channels listed', $r['data'] ?? null );

foreach ( array( 'FlxAiTaskRun', 'FlxAiTaskSetActive', 'FlxAiTaskDelete', 'FlxAiTaskMarkRead' ) as $op ) {
	$r = flxai_t_op( $op, array( 'input' => 'FlxAiTaskSetActive' === $op ? array( 'id' => 999999, 'active' => true ) : array( 'id' => 999999 ) ), $admin );
	flxt_same( 'NOT_FOUND', $code( $r, lcfirst( $op ) ), "$op: an unknown task is NOT_FOUND" );
}

// Another administrator's task is theirs; a second administrator still manages it (manage_options), but "mine" is false.
$r = flxai_t_op( 'FlxAiTaskDelete', array( 'input' => array( 'id' => $tid ) ), $admin );
flxt_same( true, $r['data']['flxAiTaskDelete']['deleted'] ?? null, 'task delete: deleted' );
flxt_same( null, flxai_task_get( $tid ), 'task delete: gone for the classic reader too' );

// The classic settings form saves through the same service.
if ( ! function_exists( 'flxai_save_settings' ) ) {
	require_once FLXAI_DIR . 'includes/admin.php'; // only loaded in wp-admin requests
}
wp_set_current_user( $admin );
$_POST = array(
	'flxai_settings_nonce' => wp_create_nonce( 'flxai_settings' ),
	'model'                => 'anthropic/test-model',
	'limit_day'            => '2,5',
	'limit_action'         => 'warn',
	'web_on'               => '1',
	'key_openrouter'       => 'sk-or-classic-12345678',
);
$GLOBALS['flxai_settings_cache'] = null;
flxt_same( true, flxai_save_settings(), 'classic settings form: saves' );
flxt_same( 'anthropic/test-model', flxai_setting( 'model' ), 'classic settings form: model saved' );
flxt_same( 2.5, (float) flxai_setting( 'limit_day' ), 'classic settings form: a comma decimal is read' );
flxt_same( 1, (int) flxai_setting( 'web_on' ), 'classic settings form: a ticked box is on' );
flxt_same( 0, (int) flxai_setting( 'shell_on' ), 'classic settings form: an unticked box is off' );
flxt_same( 'sk-or-classic-12345678', flxai_setting( 'key_openrouter' ), 'classic settings form: key written' );
$r = flxai_t_op( 'FlxAiSettingsScreen', array(), $admin );
flxt_ok( false === strpos( flxai_t_json( $r ), 'sk-or-classic-12345678' ), 'classic settings form: the key is not in the GraphQL response' );
$_POST = array();

// Persisted operations run by queryId (strict mode).
$hash = array_search( $GLOBALS['flxai_ops']['FlxAiSystems'], (array) json_decode( (string) file_get_contents( FLXAI_DIR . 'graphql/persisted-queries.json' ), true ), true );
wp_set_current_user( $admin );
$r = graphql( array( 'queryId' => $hash ) );
flxt_ok( ! empty( $r['data']['flxAiSystems'] ), 'persisted queryId runs (strict mode)', $r['errors'] ?? null );

flxt_done();
