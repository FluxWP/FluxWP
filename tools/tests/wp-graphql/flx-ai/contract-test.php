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
	'FlxAiSkills'           => array(),
	'FlxAiSkill'            => array( 'slug' => 'x' ),
	'FlxAiSkillFile'        => array( 'slug' => 'x', 'path' => 'references/a.md' ),
	'FlxAiBrand'            => array(),
	'FlxAiSkillSave'        => array( 'input' => array( 'name' => 'x' ) ),
	'FlxAiSkillToggle'      => array( 'input' => array( 'slug' => 'x' ) ),
	'FlxAiSkillDelete'      => array( 'input' => array( 'slug' => 'x' ) ),
	'FlxAiSkillsSettings'   => array( 'input' => array( 'restrict' => true ) ),
	'FlxAiSkillImport'      => array( 'input' => array( 'markdown' => 'x' ) ),
	'FlxAiSkillFileSave'    => array( 'input' => array( 'slug' => 'x', 'path' => 'references/a.md', 'content' => 'x' ) ),
	'FlxAiSkillFileDelete'  => array( 'input' => array( 'slug' => 'x', 'path' => 'references/a.md' ) ),
	'FlxAiSkillFileUpload'  => array( 'input' => array( 'slug' => 'x', 'fileName' => 'a.md', 'contentBase64' => 'eA==' ) ),
	'FlxAiSkillEvalsSave'   => array( 'input' => array( 'slug' => 'x' ) ),
	'FlxAiSkillTestRun'     => array( 'input' => array( 'slug' => 'x', 'kind' => 'trigger' ) ),
	'FlxAiBrandSave'        => array( 'input' => array( 'name' => 'x' ) ),
	'FlxAiMcp'              => array(),
	'FlxAiMcpServerUpdate'  => array( 'input' => array( 'enabled' => true, 'oauth' => true ) ),
	'FlxAiMcpKeyCreate'     => array( 'input' => array( 'name' => 'x' ) ),
	'FlxAiMcpKeyRevoke'     => array( 'input' => array( 'id' => 'x' ) ),
	'FlxAiConnections'      => array(),
	'FlxAiConnectionSave'   => array( 'input' => array( 'url' => 'https://mcp.example.test/mcp' ) ),
	'FlxAiConnectionDelete' => array( 'input' => array( 'id' => 'x' ) ),
	'FlxAiConnectionRefresh' => array( 'input' => array( 'id' => 'x' ) ),
	'FlxAiConnectionSignIn' => array( 'input' => array( 'id' => 'x' ) ),
	'FlxAiConnectionSignOut' => array( 'input' => array( 'id' => 'x' ) ),
	'FlxAiConnectionPermissions' => array( 'input' => array( 'id' => 'x', 'permissions' => array() ) ),
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

// Skills: list, create, validate, change, folder files, tests, import, brand kit.
$r = flxai_t_op( 'FlxAiSkills', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'skills: no errors' );
$list = array_column( $r['data']['flxAiSkills']['skills'] ?? array(), null, 'slug' );
flxt_ok( count( $list ) > 3, 'skills: the shipped skills are listed', array_keys( $list ) );
$shipped = (string) array_key_first( array_filter( $list, fn( $x ) => $x['bundled'] ) );
flxt_ok( '' !== $shipped && false === $list[ $shipped ]['saved'], 'skills: a shipped skill has no site copy yet', $shipped );
flxt_ok( false !== strpos( $list[ $shipped ]['exportUrl'] ?? '', '_wpnonce=' ), 'skills: the export link is signed' );
$sc = static fn( array $r, string $f ) => $r['data'][ $f ]['errors'][0]['code'] ?? $r['errors'][0]['extensions']['code'] ?? null;
$sf = static fn( array $r, string $f ) => $r['data'][ $f ]['errors'][0]['field'] ?? null;

$r = flxai_t_op( 'FlxAiSkillSave', array( 'input' => array( 'name' => 'Donor review', 'description' => 'Use when asked for a donor review.', 'body' => '   ' ) ), $admin );
flxt_same( 'INVALID_INPUT', $sc( $r, 'flxAiSkillSave' ), 'skill save: empty instructions are refused' );
flxt_same( 'body', $sf( $r, 'flxAiSkillSave' ), 'skill save: the field is named' );
$r = flxai_t_op( 'FlxAiSkillSave', array( 'input' => array( 'name' => 'x', 'slug' => 'my-claude-skill', 'description' => 'd', 'body' => 'b' ) ), $admin );
flxt_same( 'INVALID_INPUT', $sc( $r, 'flxAiSkillSave' ), 'skill save: a reserved name is refused' );
$r = flxai_t_op( 'FlxAiSkillSave', array( 'input' => array( 'slug' => $shipped, 'name' => 'x', 'description' => 'd', 'body' => 'b' ) ), $admin );
flxt_same( 'slug', $sf( $r, 'flxAiSkillSave' ), 'skill save: a taken name is refused on the name field' );

$r = flxai_t_op( 'FlxAiSkillSave', array( 'input' => array(
	'name'        => 'Donor review',
	'description' => 'Use when the user asks for a donor review or loyal donors.',
	'body'        => "# Donor review\n\n1. Look at the donors.",
	'tools'       => array( 'flxcrm_*', 'flxdonate_list' ),
	'example'     => 'Example',
) ), $admin );
flxt_same( null, $r['errors'] ?? null, 'skill save: no errors' );
flxt_same( array(), $r['data']['flxAiSkillSave']['errors'] ?? null, 'skill save: accepted' );
$d = $r['data']['flxAiSkillSave']['skill'] ?? array();
flxt_same( 'donor-review', $d['skill']['slug'] ?? null, 'skill save: the name comes from the title' );
flxt_same( array( 'flxcrm_*', 'flxdonate_list' ), $d['tools'] ?? null, 'skill save: allowed tools' );
flxt_same( 1, $d['skill']['version'] ?? null, 'skill save: version 1' );
flxt_same( 'active', $d['skill']['status'] ?? null, 'skill save: on by default' );
flxt_ok( null !== flxai_skill_get( 'donor-review' ), 'skill save: the classic reader sees it' );

$r = flxai_t_op( 'FlxAiSkillSave', array( 'input' => array( 'current' => 'donor-review', 'body' => "# Donor review v2\n\nChanged." ) ), $admin );
$d = $r['data']['flxAiSkillSave']['skill'] ?? array();
flxt_same( 2, $d['skill']['version'] ?? null, 'skill update: version grows with the instructions' );
flxt_same( 'Donor review', $d['skill']['name'] ?? null, 'skill update: fields left out keep their value' );
flxt_same( array( 'flxcrm_*', 'flxdonate_list' ), $d['tools'] ?? null, 'skill update: tools kept' );

$r = flxai_t_op( 'FlxAiSkillToggle', array( 'input' => array( 'slug' => 'donor-review' ) ), $admin );
flxt_same( 'off', $r['data']['flxAiSkillToggle']['skill']['skill']['status'] ?? null, 'skill toggle: off' );
$r = flxai_t_op( 'FlxAiSkillToggle', array( 'input' => array( 'slug' => 'donor-review' ) ), $admin );
flxt_same( 'active', $r['data']['flxAiSkillToggle']['skill']['skill']['status'] ?? null, 'skill toggle: on again' );

// Folder files.
$r = flxai_t_op( 'FlxAiSkillFileSave', array( 'input' => array( 'slug' => 'donor-review', 'path' => 'references/guide.md', 'content' => "Line 1\r\nLine 2" ) ), $admin );
flxt_same( array(), $r['data']['flxAiSkillFileSave']['errors'] ?? null, 'file save: accepted' );
flxt_ok( in_array( 'references/guide.md', array_column( $r['data']['flxAiSkillFileSave']['skill']['files'] ?? array(), 'path' ), true ), 'file save: listed', $r['data']['flxAiSkillFileSave'] ?? null );
$r = flxai_t_op( 'FlxAiSkillFile', array( 'slug' => 'donor-review', 'path' => 'references/guide.md' ), $admin );
flxt_same( "Line 1\nLine 2", $r['data']['flxAiSkillFile']['content'] ?? null, 'file read: line endings are normalised' );
flxt_same( true, $r['data']['flxAiSkillFile']['text'] ?? null, 'file read: text' );
$r = flxai_t_op( 'FlxAiSkillFile', array( 'slug' => 'donor-review', 'path' => 'references/none.md' ), $admin );
flxt_ok( array_key_exists( 'flxAiSkillFile', $r['data'] ?? array() ) && null === $r['data']['flxAiSkillFile'], 'file read: a missing file is null', $r );
$r = flxai_t_op( 'FlxAiSkillFileSave', array( 'input' => array( 'slug' => 'donor-review', 'path' => '../evil.md', 'content' => 'x' ) ), $admin );
flxt_same( 'INVALID_INPUT', $sc( $r, 'flxAiSkillFileSave' ), 'file save: a path outside the folder is refused' );
flxt_same( 'path', $sf( $r, 'flxAiSkillFileSave' ), 'file save: the path field is named' );
$r = flxai_t_op( 'FlxAiSkillFileSave', array( 'input' => array( 'slug' => 'donor-review', 'path' => 'scripts/run.php', 'content' => '<?php' ) ), $admin );
flxt_same( 'INVALID_INPUT', $sc( $r, 'flxAiSkillFileSave' ), 'file save: a blocked file type is refused' );
$r = flxai_t_op( 'FlxAiSkillFileUpload', array( 'input' => array( 'slug' => 'donor-review', 'fileName' => 'notes.txt', 'folder' => 'assets', 'contentBase64' => base64_encode( 'hello' ) ) ), $admin );
flxt_ok( in_array( 'assets/notes.txt', array_column( $r['data']['flxAiSkillFileUpload']['skill']['files'] ?? array(), 'path' ), true ), 'file upload: stored in the folder', $r['data']['flxAiSkillFileUpload'] ?? null );
$r = flxai_t_op( 'FlxAiSkillFileUpload', array( 'input' => array( 'slug' => 'donor-review', 'fileName' => 'a.txt', 'contentBase64' => '***' ) ), $admin );
flxt_same( 'INVALID_INPUT', $sc( $r, 'flxAiSkillFileUpload' ), 'file upload: broken base64 is refused' );
$r = flxai_t_op( 'FlxAiSkillFileDelete', array( 'input' => array( 'slug' => 'donor-review', 'path' => 'assets/notes.txt' ) ), $admin );
flxt_ok( ! in_array( 'assets/notes.txt', array_column( $r['data']['flxAiSkillFileDelete']['skill']['files'] ?? array(), 'path' ), true ), 'file delete: gone' );
$r = flxai_t_op( 'FlxAiSkillFileDelete', array( 'input' => array( 'slug' => 'donor-review', 'path' => 'assets/notes.txt' ) ), $admin );
flxt_same( 'NOT_FOUND', $sc( $r, 'flxAiSkillFileDelete' ), 'file delete: an unknown file is NOT_FOUND' );
$r = flxai_t_op( 'FlxAiSkillFileSave', array( 'input' => array( 'slug' => 'nope', 'path' => 'references/a.md', 'content' => 'x' ) ), $admin );
flxt_same( 'NOT_FOUND', $sc( $r, 'flxAiSkillFileSave' ), 'file save: an unknown skill is NOT_FOUND' );

// A shipped skill gets a site copy when its folder is edited.
flxt_same( false, flxai_skill_is_saved( $shipped ), 'shipped skill: no copy before' );
$r = flxai_t_op( 'FlxAiSkillFileSave', array( 'input' => array( 'slug' => $shipped, 'path' => 'references/site-note.md', 'content' => 'note' ) ), $admin );
flxt_same( array(), $r['data']['flxAiSkillFileSave']['errors'] ?? null, 'shipped skill: a file can be added' );
flxt_same( true, $r['data']['flxAiSkillFileSave']['skill']['skill']['saved'] ?? null, 'shipped skill: a site copy now exists (can be reset)' );
$r = flxai_t_op( 'FlxAiSkillDelete', array( 'input' => array( 'slug' => $shipped ) ), $admin );
flxt_same( true, $r['data']['flxAiSkillDelete']['deleted'] ?? null, 'shipped skill: reset' );
flxt_same( false, flxai_skill_is_saved( $shipped ), 'shipped skill: the copy is gone after the reset' );

// Tests.
$r = flxai_t_op( 'FlxAiSkillTestRun', array( 'input' => array( 'slug' => 'donor-review', 'kind' => 'trigger' ) ), $admin );
flxt_same( 'INVALID_INPUT', $sc( $r, 'flxAiSkillTestRun' ), 'test run: no prompts to run is refused (no model call)' );
$r = flxai_t_op( 'FlxAiSkillEvalsSave', array( 'input' => array(
	'slug'     => 'donor-review',
	'triggers' => array( array( 'query' => 'Who are my loyal donors?' ), array( 'query' => 'Weather tomorrow', 'shouldTrigger' => false ), array( 'query' => '  ' ) ),
	'cases'    => array( array( 'prompt' => 'Review my donors', 'expectations' => array( 'Lists donors', '' ) ), array( 'prompt' => '' ) ),
) ), $admin );
flxt_same( array(), $r['data']['flxAiSkillEvalsSave']['errors'] ?? null, 'evals save: accepted' );
$ev = $r['data']['flxAiSkillEvalsSave']['skill']['evals'] ?? array();
flxt_same( 2, count( $ev['triggers'] ?? array() ), 'evals save: empty prompts are dropped' );
flxt_same( false, $ev['triggers'][1]['shouldTrigger'] ?? null, 'evals save: should-not-trigger kept' );
flxt_same( true, $ev['triggers'][0]['shouldTrigger'] ?? null, 'evals save: should-trigger is the default' );
flxt_same( array( 'Lists donors' ), $ev['cases'][0]['expectations'] ?? null, 'evals save: empty checks are dropped' );
flxt_same( 1, count( $ev['cases'] ?? array() ), 'evals save: empty tests are dropped' );
$r = flxai_t_op( 'FlxAiSkillTestRun', array( 'input' => array( 'slug' => 'nope', 'kind' => 'trigger' ) ), $admin );
flxt_same( 'NOT_FOUND', $sc( $r, 'flxAiSkillTestRun' ), 'test run: an unknown skill is NOT_FOUND' );
$r = flxai_t_op( 'FlxAiSkillTestRun', array( 'input' => array( 'slug' => 'donor-review', 'kind' => 'functional', 'id' => '99' ) ), $admin );
flxt_same( 'INVALID_INPUT', $sc( $r, 'flxAiSkillTestRun' ), 'test run: an unknown functional test is refused' );
$r = flxai_t_op( 'FlxAiSkill', array( 'slug' => 'donor-review' ), $admin );
flxt_ok( array_key_exists( 'trigger', $r['data']['flxAiSkill']['results'] ?? array() ) && null === $r['data']['flxAiSkill']['results']['trigger'], 'skill: no trigger result before a run', $r );
flxt_same( array(), $r['data']['flxAiSkill']['results']['functional'] ?? null, 'skill: no functional results before a run' );
$r = flxai_t_op( 'FlxAiSkill', array( 'slug' => 'nope' ), $admin );
flxt_ok( array_key_exists( 'flxAiSkill', $r['data'] ?? array() ) && null === $r['data']['flxAiSkill'], 'skill: an unknown skill is null', $r );

// Import and the tools setting.
$r = flxai_t_op( 'FlxAiSkillImport', array( 'input' => array( 'markdown' => "---\nname: imported-one\ndescription: Use when testing imports.\n---\n\n# Imported\n\nBody." ) ), $admin );
flxt_same( array(), $r['data']['flxAiSkillImport']['errors'] ?? null, 'import: accepted' );
flxt_same( array( 'imported-one' ), $r['data']['flxAiSkillImport']['result']['imported'] ?? null, 'import: the skill is written' );
flxt_ok( null !== flxai_skill_get( 'imported-one' ), 'import: the classic reader sees it' );
$r = flxai_t_op( 'FlxAiSkillImport', array( 'input' => array( 'markdown' => 'no front matter at all' ) ), $admin );
flxt_same( 'INVALID_INPUT', $sc( $r, 'flxAiSkillImport' ), 'import: a file without front matter is refused' );
$r = flxai_t_op( 'FlxAiSkillImport', array( 'input' => array( 'contentBase64' => '***', 'fileName' => 'x.zip' ) ), $admin );
flxt_same( 'INVALID_INPUT', $sc( $r, 'flxAiSkillImport' ), 'import: broken base64 is refused' );
$r = flxai_t_op( 'FlxAiSkillsSettings', array( 'input' => array( 'restrict' => true ) ), $admin );
flxt_same( true, $r['data']['flxAiSkillsSettings']['overview']['restrict'] ?? null, 'skills setting: restrict on' );
flxt_same( true, flxai_skills_restrict_tools(), 'skills setting: the classic reader sees it' );
flxai_t_op( 'FlxAiSkillsSettings', array( 'input' => array( 'restrict' => false ) ), $admin );

// Delete (the skills of this site go away; tasks that use them keep running without).
$r = flxai_t_op( 'FlxAiSkillDelete', array( 'input' => array( 'slug' => 'imported-one' ) ), $admin );
flxt_same( true, $r['data']['flxAiSkillDelete']['deleted'] ?? null, 'skill delete: deleted' );
$r = flxai_t_op( 'FlxAiSkillDelete', array( 'input' => array( 'slug' => 'donor-review' ) ), $admin );
flxt_same( null, flxai_skill_get( 'donor-review' ), 'skill delete: gone for the classic reader too' );
$r = flxai_t_op( 'FlxAiSkillDelete', array( 'input' => array( 'slug' => 'donor-review' ) ), $admin );
flxt_same( 'NOT_FOUND', $sc( $r, 'flxAiSkillDelete' ), 'skill delete: a second delete is NOT_FOUND' );

// Brand kit.
delete_option( 'flxai_brand_kit' );
$r = flxai_t_op( 'FlxAiBrand', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'brand: no errors' );
$b = $r['data']['flxAiBrand'] ?? array();
flxt_same( '', $b['name'] ?? null, 'brand: empty before the first save' );
flxt_same( get_bloginfo( 'name' ), $b['defaultName'] ?? null, 'brand: the site name is the default' );
$r = flxai_t_op( 'FlxAiBrandSave', array( 'input' => array( 'colorPrimary' => 'green' ) ), $admin );
flxt_same( 'INVALID_INPUT', $sc( $r, 'flxAiBrandSave' ), 'brand save: a color that is not hex is refused' );
$r = flxai_t_op( 'FlxAiBrandSave', array( 'input' => array(
	'name'         => 'Green Turkey',
	'colorPrimary' => '#0A5A40',
	'partnerIds'   => array( 0, 7, 8 ),
	'docs'         => array( array( 'name' => 'voice.md', 'content' => "# Voice\n\nWarm." ), array( 'name' => 'about-me.md', 'content' => 'About' ) ),
) ), $admin );
flxt_same( array(), $r['data']['flxAiBrandSave']['errors'] ?? null, 'brand save: accepted' );
$b = $r['data']['flxAiBrandSave']['brand'] ?? array();
flxt_same( 'Green Turkey', $b['name'] ?? null, 'brand save: name' );
flxt_same( '#0A5A40', $b['colorPrimary'] ?? null, 'brand save: color' );
flxt_same( array( 'about-me.md', 'voice.md' ), array_column( $b['docs'] ?? array(), 'name' ), 'brand save: documents' );
flxt_same( 'Green Turkey', flxai_brand_raw()['profile']['name'] ?? null, 'brand save: the classic reader sees it' );
$r = flxai_t_op( 'FlxAiBrandSave', array( 'input' => array( 'tagline' => 'Plant a tree', 'docs' => array( array( 'name' => 'voice.md', 'content' => '' ) ) ) ), $admin );
$b = $r['data']['flxAiBrandSave']['brand'] ?? array();
flxt_same( 'Green Turkey', $b['name'] ?? null, 'brand save: fields left out keep their value' );
flxt_same( array( 'about-me.md' ), array_column( $b['docs'] ?? array(), 'name' ), 'brand save: an empty document is deleted' );
delete_option( 'flxai_brand_kit' );

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

// MCP server page: switch, keys (shown once), revoke, activity.
delete_option( 'flxai_mcp_keys' );
delete_option( 'flxai_mcp_log' );
update_option( 'flxai_mcp_log', array( array( 't' => time(), 'u' => $admin, 'tool' => 'order_summary', 'ok' => true, 'on' => '42' ) ), false );
$r = flxai_t_op( 'FlxAiMcp', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'mcp: no errors' );
$m = $r['data']['flxAiMcp'] ?? array();
flxt_same( array(), $m['keys'] ?? null, 'mcp: no keys yet' );
flxt_same( flxai_mcp_url(), $m['server']['url'] ?? null, 'mcp: server url' );
flxt_same( count( flxai_mcp_tools() ), $m['server']['toolCount'] ?? null, 'mcp: tool count' );
flxt_same( 'order_summary', $m['log'][0]['tool'] ?? null, 'mcp: activity listed' );
flxt_same( true, $m['log'][0]['ok'] ?? null, 'mcp: activity result' );
$r = flxai_t_op( 'FlxAiMcpServerUpdate', array( 'input' => array( 'enabled' => true, 'oauth' => false ) ), $admin );
flxt_same( array(), $r['data']['flxAiMcpServerUpdate']['errors'] ?? null, 'mcp server update: no errors' );
flxt_same( true, $r['data']['flxAiMcpServerUpdate']['overview']['server']['enabled'] ?? null, 'mcp server update: on' );
flxt_same( false, $r['data']['flxAiMcpServerUpdate']['overview']['server']['oauthActive'] ?? null, 'mcp server update: OAuth off' );
flxt_same( true, flxai_mcp_enabled(), 'mcp server update: classic reader sees it' );
flxt_same( false, flxai_oauth_enabled(), 'mcp server update: classic OAuth reader sees it' );
flxai_t_op( 'FlxAiMcpServerUpdate', array( 'input' => array( 'enabled' => true, 'oauth' => true ) ), $admin );
flxt_same( true, flxai_oauth_enabled(), 'mcp server update: OAuth back on' );
$all  = array_keys( flxai_mcp_tools() );
$tool = (string) ( $all[0] ?? '' );
$r    = flxai_t_op( 'FlxAiMcpKeyCreate', array( 'input' => array( 'name' => 'Claude – Test', 'tools' => array( $tool, 'no_such_tool' ) ) ), $admin );
$c    = $r['data']['flxAiMcpKeyCreate'] ?? array();
flxt_same( array(), $c['errors'] ?? null, 'mcp key create: no errors' );
$secret = (string) ( $c['key'] ?? '' );
flxt_ok( 0 === strpos( $secret, 'flxmcp_' ) && strlen( $secret ) > 30, 'mcp key create: the key is in the result' );
$k = $c['overview']['keys'][0] ?? array();
flxt_same( 'Claude – Test', $k['name'] ?? null, 'mcp key create: name' );
flxt_same( array( $tool ), $k['tools'] ?? null, 'mcp key create: limited to known tools only' );
flxt_same( substr( $secret, -4 ), $k['hint'] ?? null, 'mcp key create: hint' );
flxt_ok( array_key_exists( 'lastUsed', $k ) && null === $k['lastUsed'], 'mcp key create: never used' );
flxt_same( true, $k['canUse'] ?? null, 'mcp key create: the administrator may use MCP' );
flxt_ok( is_array( flxai_mcp_find_key( $secret ) ), 'mcp key create: the server accepts the key' );
$r = flxai_t_op( 'FlxAiMcp', array(), $admin );
flxt_ok( false === strpos( flxai_t_json( $r ), $secret ), 'mcp: the key is not in a later response' );
flxt_ok( false === strpos( (string) wp_json_encode( get_option( 'flxai_mcp_keys' ) ), $secret ), 'mcp key create: only the hash is stored' );
$id = (string) ( $k['id'] ?? '' );
$r  = flxai_t_op( 'FlxAiMcpKeyRevoke', array( 'input' => array( 'id' => 'nope' ) ), $admin );
flxt_same( 'NOT_FOUND', $r['data']['flxAiMcpKeyRevoke']['errors'][0]['code'] ?? $r['errors'][0]['extensions']['code'] ?? null, 'mcp key revoke: unknown key' );
$r = flxai_t_op( 'FlxAiMcpKeyRevoke', array( 'input' => array( 'id' => $id ) ), $admin );
flxt_same( array(), $r['data']['flxAiMcpKeyRevoke']['overview']['keys'] ?? null, 'mcp key revoke: gone' );
flxt_same( null, flxai_mcp_find_key( $secret ), 'mcp key revoke: the server refuses the key' );
// The classic form creates through the same service.
if ( ! function_exists( 'flxai_page_mcp' ) ) {
	require_once FLXAI_DIR . 'includes/admin-mcp.php';
}
$r = FLXAI_Mcp_Service::create_key( '', array() );
flxt_same( 'AI', $r->overview->keys[0]->name ?? null, 'mcp key create: empty name falls back' );
flxt_same( array(), $r->overview->keys[0]->tools ?? null, 'mcp key create: no tools means all' );
delete_option( 'flxai_mcp_keys' );
delete_option( 'flxai_mcp_log' );

// Connections: add, secrets stay write-only, refresh (mocked server), permissions, OAuth, delete.
delete_option( 'flxai_mcpc_servers' );
$GLOBALS['flxai_mcpc_mock'] = array( 'calls' => 0, 'fail' => false );
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		if ( 0 !== strpos( (string) $url, 'https://mcp.example.test/' ) ) {
			return $pre;
		}
		$m = &$GLOBALS['flxai_mcpc_mock'];
		++$m['calls'];
		if ( $m['fail'] ) {
			return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 401, 'message' => 'Unauthorized' ), 'cookies' => array(), 'filename' => null );
		}
		$p   = json_decode( (string) ( $args['body'] ?? '' ), true );
		$res = array();
		if ( 'initialize' === ( $p['method'] ?? '' ) ) {
			$res = array( 'protocolVersion' => '2025-06-18', 'capabilities' => new stdClass(), 'serverInfo' => array( 'name' => 'Mock', 'version' => '1.2' ) );
		} elseif ( 'tools/list' === ( $p['method'] ?? '' ) ) {
			$res = array( 'tools' => array(
				array( 'name' => 'search', 'title' => 'Search', 'description' => 'Find things', 'inputSchema' => array( 'type' => 'object' ), 'annotations' => array( 'readOnlyHint' => true ) ),
				array( 'name' => 'create-item', 'description' => 'Add a thing', 'inputSchema' => array( 'type' => 'object' ) ),
			) );
		}
		$body = wp_json_encode( array( 'jsonrpc' => '2.0', 'id' => $p['id'] ?? null, 'result' => $res ) );
		return array( 'headers' => array( 'content-type' => 'application/json' ), 'body' => (string) $body, 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
	},
	10,
	3
);
$r = flxai_t_op( 'FlxAiConnections', array(), $admin );
flxt_same( array(), $r['data']['flxAiConnections']['connections'] ?? null, 'connections: none yet' );
flxt_ok( in_array( 'editor', array_column( $r['data']['flxAiConnections']['roles'] ?? array(), 'id' ), true ), 'connections: roles for the form' );
flxt_ok( ! in_array( 'administrator', array_column( $r['data']['flxAiConnections']['roles'] ?? array(), 'id' ), true ), 'connections: administrators are not listed' );
$r = flxai_t_op( 'FlxAiConnectionSave', array( 'input' => array( 'name' => 'Bad', 'url' => 'http://insecure.example.test/mcp' ) ), $admin );
flxt_same( 'INVALID_INPUT', $r['data']['flxAiConnectionSave']['errors'][0]['code'] ?? $r['errors'][0]['extensions']['code'] ?? null, 'connection save: a non-https address is refused' );
add_filter( 'flxai_mcpc_allow_local', '__return_true' ); // the sandbox cannot resolve the mock host
$r = flxai_t_op( 'FlxAiConnectionSave', array( 'input' => array( 'name' => 'Linear Test', 'url' => 'https://mcp.example.test/mcp', 'auth' => 'bearer', 'secret' => 'tok-super-secret-123', 'scope' => 'shared', 'roles' => array( 'editor', 'ghost' ) ) ), $admin );
flxt_same( array(), $r['data']['flxAiConnectionSave']['errors'] ?? null, 'connection save: no errors' );
$c = $r['data']['flxAiConnectionSave']['connection'] ?? array();
flxt_same( 'linear_test', $c['id'] ?? null, 'connection save: id from the name' );
flxt_same( 'connected', $c['status'] ?? null, 'connection save: connected after the tool list loaded' );
flxt_same( true, $c['hasSecret'] ?? null, 'connection save: secret saved' );
flxt_same( array( 'editor' ), $c['roles'] ?? null, 'connection save: unknown roles dropped' );
flxt_same( 'Mock', $c['serverName'] ?? null, 'connection save: server info' );
flxt_same( array( 'search', 'create-item' ), array_column( $c['tools'] ?? array(), 'name' ), 'connection save: tools listed' );
flxt_same( 'mcp_linear_test_search', $c['tools'][0]['toolName'] ?? null, 'connection save: chat tool name' );
flxt_same( 2, $c['toolsOn'] ?? null, 'connection save: tools on' );
flxt_ok( false === strpos( flxai_t_json( $r ), 'tok-super-secret-123' ), 'connection save: the secret is not in the response' );
flxt_ok( false === strpos( (string) wp_json_encode( get_option( 'flxai_mcpc_servers' ) ), 'tok-super-secret-123' ), 'connection save: the secret is stored encrypted' );
flxt_same( 'tok-super-secret-123', flxai_decrypt( flxai_mcpc_get( 'linear_test' )['secret'] ), 'connection save: the classic reader decrypts it' );
$r = flxai_t_op( 'FlxAiConnectionSave', array( 'input' => array( 'id' => 'linear_test', 'name' => 'Linear', 'secret' => '' ) ), $admin );
$c = $r['data']['flxAiConnectionSave']['connection'] ?? array();
flxt_same( 'Linear', $c['name'] ?? null, 'connection save: name changed' );
flxt_same( true, $c['hasSecret'] ?? null, 'connection save: an empty secret keeps the saved one' );
flxt_same( 'https://mcp.example.test/mcp', $c['url'] ?? null, 'connection save: fields left out keep their value' );
flxt_same( 'shared', $c['scope'] ?? null, 'connection save: scope kept' );
// Permissions.
$r = flxai_t_op( 'FlxAiConnectionPermissions', array( 'input' => array( 'id' => 'linear_test', 'permissions' => array( array( 'tool' => 'create-item', 'permission' => 'allow' ) ) ) ), $admin );
flxt_same( 'INVALID_INPUT', $r['data']['flxAiConnectionPermissions']['errors'][0]['code'] ?? $r['errors'][0]['extensions']['code'] ?? null, 'connection permissions: a tool that changes data cannot run without asking' );
$r = flxai_t_op( 'FlxAiConnectionPermissions', array( 'input' => array( 'id' => 'linear_test', 'permissions' => array( array( 'tool' => 'nope', 'permission' => 'ask' ) ) ) ), $admin );
flxt_same( 'INVALID_INPUT', $r['data']['flxAiConnectionPermissions']['errors'][0]['code'] ?? $r['errors'][0]['extensions']['code'] ?? null, 'connection permissions: unknown tool' );
$r = flxai_t_op( 'FlxAiConnectionPermissions', array( 'input' => array( 'id' => 'linear_test', 'permissions' => array( array( 'tool' => 'search', 'permission' => 'allow' ), array( 'tool' => 'create-item', 'permission' => 'off' ) ) ) ), $admin );
$c = $r['data']['flxAiConnectionPermissions']['connection'] ?? array();
flxt_same( array( 'allow', 'off' ), array_column( $c['tools'] ?? array(), 'permission' ), 'connection permissions: saved' );
flxt_same( 1, $c['toolsOn'] ?? null, 'connection permissions: tools on' );
flxt_same( 'allow', flxai_mcpc_perm( flxai_mcpc_get( 'linear_test' ), 'search' ), 'connection permissions: classic reader sees it' );
// Refresh: a failing server is reported in the connection.
$GLOBALS['flxai_mcpc_mock']['fail'] = true;
delete_transient( 'flxai_mcpc_sess_linear_test' );
$r = flxai_t_op( 'FlxAiConnectionRefresh', array( 'input' => array( 'id' => 'linear_test' ) ), $admin );
$c = $r['data']['flxAiConnectionRefresh']['connection'] ?? array();
flxt_same( 'error', $c['status'] ?? null, 'connection refresh: failure shows as error' );
flxt_ok( '' !== ( $c['error'] ?? '' ), 'connection refresh: the error text is returned' );
flxt_same( 2, count( $c['tools'] ?? array() ), 'connection refresh: the old tool list stays' );
$GLOBALS['flxai_mcpc_mock']['fail'] = false;
delete_transient( 'flxai_mcpc_sess_linear_test' );
$r = flxai_t_op( 'FlxAiConnectionRefresh', array( 'input' => array( 'id' => 'linear_test' ) ), $admin );
flxt_same( 'connected', $r['data']['flxAiConnectionRefresh']['connection']['status'] ?? null, 'connection refresh: recovers' );
$r = flxai_t_op( 'FlxAiConnectionRefresh', array( 'input' => array( 'id' => 'nope' ) ), $admin );
flxt_same( 'NOT_FOUND', $r['data']['flxAiConnectionRefresh']['errors'][0]['code'] ?? $r['errors'][0]['extensions']['code'] ?? null, 'connection refresh: unknown connection' );
// OAuth: sign-in is only for OAuth connections; sign out forgets the tokens.
$r = flxai_t_op( 'FlxAiConnectionSignIn', array( 'input' => array( 'id' => 'linear_test' ) ), $admin );
flxt_same( 'INVALID_INPUT', $r['data']['flxAiConnectionSignIn']['errors'][0]['code'] ?? $r['errors'][0]['extensions']['code'] ?? null, 'connection sign-in: not an OAuth connection' );
$r = flxai_t_op( 'FlxAiConnectionSave', array( 'input' => array( 'name' => 'OAuth One', 'url' => 'https://mcp.example.test/oauth', 'auth' => 'oauth', 'clientId' => 'cid-1', 'clientSecret' => 'client-secret-xyz', 'enabled' => false ) ), $admin );
$o = $r['data']['flxAiConnectionSave']['connection'] ?? array();
flxt_same( 'off', $o['status'] ?? null, 'oauth connection: off when not enabled' );
flxt_same( 'cid-1', $o['clientId'] ?? null, 'oauth connection: client id returned' );
flxt_same( true, $o['hasClientSecret'] ?? null, 'oauth connection: client secret saved' );
flxt_ok( false === strpos( flxai_t_json( $r ), 'client-secret-xyz' ), 'oauth connection: the client secret is not in the response' );
$s = flxai_mcpc_get( 'oauth_one' );
$s['oauth']['access'] = flxai_encrypt( 'access-token-abc' );
flxai_mcpc_put( $s );
$r = flxai_t_op( 'FlxAiConnections', array(), $admin );
$byid = array_column( $r['data']['flxAiConnections']['connections'] ?? array(), null, 'id' );
flxt_same( true, $byid['oauth_one']['signedIn'] ?? null, 'oauth connection: signed in' );
flxt_ok( false === strpos( flxai_t_json( $r ), 'access-token-abc' ), 'oauth connection: the access token is not in the response' );
$r = flxai_t_op( 'FlxAiConnectionSignOut', array( 'input' => array( 'id' => 'oauth_one' ) ), $admin );
flxt_same( false, $r['data']['flxAiConnectionSignOut']['connection']['signedIn'] ?? null, 'oauth connection: signed out' );
flxt_same( 'cid-1', $r['data']['flxAiConnectionSignOut']['connection']['clientId'] ?? null, 'oauth connection: sign-out keeps the client id' );
// Another user's personal connection can be seen but not changed.
$other = (int) wp_insert_user( array( 'user_login' => 'flxai_adm_' . wp_rand(), 'user_email' => 'flxai_adm_' . wp_rand() . '@example.com', 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
$r     = flxai_t_op( 'FlxAiConnectionSave', array( 'input' => array( 'name' => 'Mine', 'url' => 'https://mcp.example.test/mine', 'enabled' => false ) ), $other );
flxt_same( 'personal', $r['data']['flxAiConnectionSave']['connection']['scope'] ?? null, 'connection save: personal by default' );
$r = flxai_t_op( 'FlxAiConnections', array(), $admin );
$byid = array_column( $r['data']['flxAiConnections']['connections'] ?? array(), null, 'id' );
flxt_same( false, $byid['mine']['editable'] ?? null, 'connections: another user\'s personal connection is read-only' );
flxt_same( true, $byid['linear_test']['editable'] ?? null, 'connections: a shared connection is editable' );
$r = flxai_t_op( 'FlxAiConnectionDelete', array( 'input' => array( 'id' => 'mine' ) ), $admin );
flxt_same( 'FORBIDDEN', $r['data']['flxAiConnectionDelete']['errors'][0]['code'] ?? $r['errors'][0]['extensions']['code'] ?? null, 'connection delete: another user\'s personal connection is refused' );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $other );
$r = flxai_t_op( 'FlxAiConnectionDelete', array( 'input' => array( 'id' => 'linear_test' ) ), $admin );
flxt_same( true, $r['data']['flxAiConnectionDelete']['deleted'] ?? null, 'connection delete: deleted' );
flxt_same( null, flxai_mcpc_get( 'linear_test' ), 'connection delete: the classic reader sees it gone' );
remove_all_filters( 'pre_http_request' );
remove_filter( 'flxai_mcpc_allow_local', '__return_true' );
delete_option( 'flxai_mcpc_servers' );

// Persisted operations run by queryId (strict mode).
$hash = array_search( $GLOBALS['flxai_ops']['FlxAiSystems'], (array) json_decode( (string) file_get_contents( FLXAI_DIR . 'graphql/persisted-queries.json' ), true ), true );
wp_set_current_user( $admin );
$r = graphql( array( 'queryId' => $hash ) );
flxt_ok( ! empty( $r['data']['flxAiSystems'] ), 'persisted queryId runs (strict mode)', $r['errors'] ?? null );

flxt_done();
