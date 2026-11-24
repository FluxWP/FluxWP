<?php
/**
 * FluxWP Panel GraphQL contract tests. The documents are the persisted operations the Packages and
 * Settings screens send (plugins/flx-setup/graphql/persisted-queries.json). The classic handlers and
 * GraphQL share the FLXK_*_Service classes, so the classic behaviour is checked through them too.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-setup/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "FluxWP Panel GraphQL\n";

$GLOBALS['flxk_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXK_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxk_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 22, count( $GLOBALS['flxk_ops'] ), 'FluxWP Panel ships 22 persisted operations' );

function flxk_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxk_ops'][ $name ], $vars, $user );
}

function flxk_t_errors( array $r, string $mutation ): array {
	return array_map( static fn( $e ) => array( $e['code'] ?? null, $e['field'] ?? null ), $r['data'][ $mutation ]['errors'] ?? array() );
}

$admin = 1;
$sub   = (int) wp_insert_user( array( 'user_login' => 'flxk_sub_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$upd   = (int) wp_insert_user( array( 'user_login' => 'flxk_upd_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
( new WP_User( $upd ) )->add_cap( 'update_plugins' ); // May update, may not manage options.

// Permissions: every query and mutation is closed to a visitor and a subscriber.
foreach ( array( 'FlxSetupOverview', 'FlxSetupPackages', 'FlxSetupHistory', 'FlxSetupSettingsScreen', 'FlxSetupSystemChecks' ) as $op ) {
	flxt_same( 'UNAUTHENTICATED', flxt_code( flxk_t_op( $op ) ), "$op: visitor → UNAUTHENTICATED" );
	flxt_same( 'FORBIDDEN', flxt_code( flxk_t_op( $op, array(), $sub ) ), "$op: subscriber → FORBIDDEN" );
}
flxt_same( 'FORBIDDEN', flxt_code( flxk_t_op( 'FlxSetupCheck', array(), $sub ) ), 'check: subscriber → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxk_t_op( 'FlxSetupSettingsSave', array( 'input' => array( 'auto' => true ) ), $upd ) ), 'settings save: update_plugins without manage_options → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxk_t_op( 'FlxSetupSettingsScreen', array(), $upd ) ), 'settings: update_plugins without manage_options → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxk_t_op( 'FlxSetupWizardStep', array( 'input' => array( 'kind' => 'SKIP' ) ), $sub ) ), 'wizard: subscriber → FORBIDDEN' );
flxt_same( null, flxt_code( flxk_t_op( 'FlxSetupOverview', array(), $upd ) ), 'overview: an updater may open it' );

// Overview.
$r = flxk_t_op( 'FlxSetupOverview', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'overview: no errors' );
$o = $r['data']['flxSetupOverview'] ?? array();
flxt_same( array( 'ALL', 'FLUX' ), array_slice( array_column( $o['tabs'] ?? array(), 'tab' ), 0, 2 ), 'overview: tabs start with all and flux' );
flxt_ok( is_string( $o['connection']['state'] ?? null ) && is_int( $o['updateCount'] ?? null ), 'overview: connection state and update count' );
flxt_ok( ! str_contains( (string) wp_json_encode( $r ), 'flxu_key' ), 'overview: no key anywhere' );

// Packages: installed plugins show up as rows of other makers, with the active flag.
$r    = flxk_t_op( 'FlxSetupPackages', array( 'tab' => 'PLUGINS', 'first' => 200 ), $admin );
$rows = $r['data']['flxSetupPackages']['nodes'] ?? array();
flxt_same( null, $r['errors'] ?? null, 'packages: no errors' );
flxt_ok( count( $rows ) > 0 && 'PLUGINS' === ( $rows[0]['group'] ?? null ), 'packages: wp tab has rows' );
$hello = array_values( array_filter( $rows, static fn( $x ) => 'p:hello.php' === $x['key'] ) )[0] ?? array();
flxt_same( array( 'WP_PLUGIN', false ), array( $hello['kind'] ?? null, $hello['active'] ?? null ), 'packages: Hello Dolly is an inactive plugin row' );
flxt_same( true, $hello['canManage'] ?? null, 'packages: plugin rows can be selected' );
$r = flxk_t_op( 'FlxSetupPackages', array( 'search' => 'hello dolly' ), $admin );
flxt_same( true, in_array( 'p:hello.php', array_column( $r['data']['flxSetupPackages']['nodes'] ?? array(), 'key' ), true ), 'packages: search finds it' );
$r = flxk_t_op( 'FlxSetupPackages', array( 'search' => 'zzzz-nothing' ), $admin );
flxt_same( array( array(), 0 ), array( $r['data']['flxSetupPackages']['nodes'] ?? null, $r['data']['flxSetupPackages']['totalCount'] ?? null ), 'packages: search without a match is empty' );
$r = flxk_t_op( 'FlxSetupPackages', array( 'onlyUpdates' => true ), $admin );
flxt_same( null, $r['errors'] ?? null, 'packages: only updates works' );

// Manage: activate / deactivate through the service, same as the classic bulk form.
$r = flxk_t_op( 'FlxSetupManage', array( 'input' => array( 'op' => 'ACTIVATE', 'wpKeys' => array( 'p:hello.php' ) ) ), $admin );
flxt_same( array( 1, array() ), array( $r['data']['flxSetupManage']['done'] ?? null, $r['data']['flxSetupManage']['failed'] ?? null ), 'manage: activate one plugin' );
flxt_ok( is_plugin_active( 'hello.php' ), 'manage: plugin is active in WordPress' );
$r = flxk_t_op( 'FlxSetupManage', array( 'input' => array( 'op' => 'DEACTIVATE', 'wpKeys' => array( 'p:hello.php' ) ) ), $admin );
flxt_same( 1, $r['data']['flxSetupManage']['done'] ?? null, 'manage: deactivate' );
flxt_ok( ! is_plugin_active( 'hello.php' ), 'manage: plugin is inactive again' );
$r = flxk_t_op( 'FlxSetupManage', array( 'input' => array( 'op' => 'DEACTIVATE', 'slugs' => array( 'flx-setup' ) ) ), $admin );
flxt_same( 0, $r['data']['flxSetupManage']['done'] ?? null, 'manage: FluxWP Panel does not close itself' );
flxt_ok( is_plugin_active( 'flx-setup/flx-setup.php' ), 'manage: FluxWP Panel is still active' );

// Auto update switch (the test site turns WordPress' auto updates off: first the CONFLICT, then on).
$r = flxk_t_op( 'FlxSetupAutoUpdateSet', array( 'input' => array( 'type' => 'PLUGIN', 'id' => 'hello.php', 'on' => true ) ), $admin );
flxt_same( 'CONFLICT', flxk_t_errors( $r, 'flxSetupAutoUpdateSet' )[0][0] ?? null, 'auto update: turned off for the site → CONFLICT' );
add_filter( 'plugins_auto_update_enabled', '__return_true' );
$r = flxk_t_op( 'FlxSetupAutoUpdateSet', array( 'input' => array( 'type' => 'PLUGIN', 'id' => 'hello.php', 'on' => true ) ), $admin );
flxt_same( true, $r['data']['flxSetupAutoUpdateSet']['on'] ?? null, 'auto update: on' );
flxt_ok( in_array( 'hello.php', (array) get_site_option( 'auto_update_plugins', array() ), true ), 'auto update: saved in WordPress option' );
$r = flxk_t_op( 'FlxSetupAutoUpdateSet', array( 'input' => array( 'type' => 'PLUGIN', 'id' => 'hello.php', 'on' => false ) ), $admin );
flxt_same( false, $r['data']['flxSetupAutoUpdateSet']['on'] ?? null, 'auto update: off' );

// Plan.
$r = flxk_t_op( 'FlxSetupPlan', array( 'wpKeys' => array( 'p:hello.php' ) ), $admin );
flxt_same( null, $r['errors'] ?? null, 'plan: no errors for a selection' );
flxt_ok( is_array( $r['data']['flxSetupPlan'] ?? null ), 'plan: returns a list' );

// Step run: validation.
$r = flxk_t_op( 'FlxSetupStepRun', array( 'input' => array( 'kind' => 'UPDATE', 'slug' => 'no-such-package' ) ), $admin );
flxt_ok( false === ( $r['data']['flxSetupStepRun']['ok'] ?? null ) || ! empty( $r['data']['flxSetupStepRun']['errors'] ), 'step: unknown package does not succeed' );
flxt_same( 'FORBIDDEN', flxt_code( flxk_t_op( 'FlxSetupStepRun', array( 'input' => array( 'kind' => 'UPDATE', 'slug' => 'x' ) ), $sub ) ), 'step: subscriber → FORBIDDEN' );

// Check (update server is unreachable on the test site: the mutation still answers).
$r = flxk_t_op( 'FlxSetupCheck', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'check: no GraphQL errors' );
flxt_ok( is_bool( $r['data']['flxSetupCheck']['ok'] ?? null ) && is_string( $r['data']['flxSetupCheck']['summary'] ?? null ), 'check: ok and summary' );

// Settings: the key is saved but never returned.
$r = flxk_t_op( 'FlxSetupSettingsSave', array( 'input' => array( 'key' => 'secret-key-123', 'auto' => true, 'health' => true ) ), $admin );
$s = $r['data']['flxSetupSettingsSave']['settings'] ?? array();
flxt_same( array( true, true, true ), array( $s['hasKey'] ?? null, $s['auto'] ?? null, $s['health'] ?? null ), 'settings save: key, auto, health saved' );
flxt_ok( ! str_contains( (string) wp_json_encode( $r ), 'secret-key-123' ), 'settings save: the key is not in the answer' );
$r = flxk_t_op( 'FlxSetupSettingsScreen', array(), $admin );
flxt_ok( ! str_contains( (string) wp_json_encode( $r ), 'secret-key-123' ), 'settings: the key is not in the screen data' );
flxt_same( true, $r['data']['flxSetupSettings']['hasKey'] ?? null, 'settings: hasKey' );
$r = flxk_t_op( 'FlxSetupSettingsSave', array( 'input' => array( 'auto' => false ) ), $admin );
flxt_same( array( true, false ), array( $r['data']['flxSetupSettingsSave']['settings']['hasKey'] ?? null, $r['data']['flxSetupSettingsSave']['settings']['auto'] ?? null ), 'settings save: fields left out stay (key kept)' );
$r = flxk_t_op( 'FlxSetupSettingsSave', array( 'input' => array( 'server' => 'http://insecure.example.com' ) ), $admin );
flxt_same( array( array( 'INVALID_INPUT', 'server' ) ), flxk_t_errors( $r, 'flxSetupSettingsSave' ), 'settings save: http server → INVALID_INPUT server' );
$r = flxk_t_op( 'FlxSetupSettingsSave', array( 'input' => array( 'clearKey' => true ) ), $admin );
flxt_same( false, $r['data']['flxSetupSettingsSave']['settings']['hasKey'] ?? null, 'settings save: clearKey removes it' );

// Classic form uses the same service and the lenient mode (a bad address falls back to the default server).
FLXK_Settings_Service::save( array( 'server' => 'http://insecure.example.com' ), false );
flxt_same( '', FLXK_Val::str( FLXU_Updater::settings()['server'] ?? 'x' ), 'classic form: a bad address becomes the default server' );

// Sources.
$r = flxk_t_op( 'FlxSetupSourceAdd', array( 'input' => array( 'url' => 'http://insecure.example.com' ) ), $admin );
flxt_same( 'INVALID_INPUT', flxk_t_errors( $r, 'flxSetupSourceAdd' )[0][0] ?? null, 'source add: http address → INVALID_INPUT' );
$r = flxk_t_op( 'FlxSetupSourceChange', array( 'input' => array( 'op' => 'REMOVE', 'id' => 'nope' ) ), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'source change: unknown id → NOT_FOUND' );
$r = flxk_t_op( 'FlxSetupSourceChange', array( 'input' => array( 'op' => 'REMOVE', 'id' => 'main' ) ), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'source change: the main server cannot be removed' );
$r = flxk_t_op( 'FlxSetupSettingsScreen', array(), $admin );
$main = array_values( array_filter( $r['data']['flxSetupSources'] ?? array(), static fn( $x ) => $x['main'] ) )[0] ?? array();
flxt_same( 'main', $main['id'] ?? null, 'sources: the main server is listed first' );
flxt_ok( ! str_contains( (string) wp_json_encode( $r['data']['flxSetupSources'] ?? array() ), 'secret' ), 'sources: no key in the list' );

// History + backups.
$r = flxk_t_op( 'FlxSetupHistory', array( 'page' => 1 ), $admin );
flxt_same( null, $r['errors'] ?? null, 'history: no errors' );
$h = $r['data']['flxSetupHistory'] ?? array();
flxt_same( array( 1, true ), array( $h['page'] ?? null, is_array( $h['rows'] ?? null ) ), 'history: first page' );
$r = flxk_t_op( 'FlxSetupRetentionSave', array( 'input' => array( 'days' => 0 ) ), $admin );
flxt_ok( 'INVALID_INPUT' === ( flxk_t_errors( $r, 'flxSetupRetentionSave' )[0][0] ?? null ) || ! empty( $r['errors'] ), 'retention: 0 days is rejected' );
flxt_same( 'FORBIDDEN', flxt_code( flxk_t_op( 'FlxSetupBackupsDelete', array( 'input' => array( 'mode' => 'ALL' ) ), $sub ) ), 'backups delete: subscriber → FORBIDDEN' );

// System checks and Flux GraphQL status.
$r = flxk_t_op( 'FlxSetupSystemChecks', array(), $admin );
$checks = $r['data']['flxSetupSystemChecks'] ?? array();
flxt_ok( count( $checks ) > 0, 'system checks: rows' );
flxt_same( array(), array_values( array_diff( array_column( $checks, 'status' ), array( 'OK', 'INFO', 'WARNING', 'ERROR' ) ) ), 'system checks: statuses are known values' );
flxt_same( $checks === array() ? 0 : count( $checks ), count( FLXK_System_Service::checks() ), 'system checks: same rows as the service (classic system check)' );
$r = flxk_t_op( 'FlxSetupSettingsScreen', array(), $admin );
$g = $r['data']['flxSetupGraphqlStatus'] ?? array();
flxt_same( array( true, 'OK' ), array( $g['phpOk'] ?? null, $g['state'] ?? null ), 'graphql status: running on a supported PHP' );

// FluxWP account, usage data, Google cards (the cards of the classic settings and packages pages).
$r = flxk_t_op( 'FlxSetupSettingsScreen', array(), $admin );
$cl = $r['data']['flxSetupCloud'] ?? array();
flxt_same( array( false, 'flxk_cloud_connect' ), array( $cl['connected'] ?? null, $cl['actions'][0]['action'] ?? null ), 'cloud: not connected, sign-in button' );
flxt_ok( false !== wp_verify_nonce( $cl['actions'][0]['nonce'] ?? '', 'flxk_cloud_connect' ), 'cloud: button carries a valid nonce' );
flxt_same( true, json_decode( (string) ( $r['data']['flxSetupTelemetry']['payload'] ?? '' ), true ) !== null, 'telemetry: payload is JSON' );
flxt_same( 'FORBIDDEN', flxt_code( flxk_t_op( 'FlxSetupTelemetrySave', array( 'input' => array( 'url' => 'https://x.example' ) ), $upd ) ), 'telemetry save: no manage_options → FORBIDDEN' );
$r = flxk_t_op( 'FlxSetupTelemetrySave', array( 'input' => array( 'url' => 'http://insecure.example/hub' ) ), $admin );
flxt_same( array( '', null ), array( $r['data']['flxSetupTelemetrySave']['telemetry']['url'] ?? null, array_key_exists( 'sent', $r['data']['flxSetupTelemetrySave'] ?? array() ) ? $r['data']['flxSetupTelemetrySave']['sent'] : 'x' ), 'telemetry save: http address is dropped, nothing sent' );
$r = flxk_t_op( 'FlxSetupTelemetrySave', array( 'input' => array( 'url' => 'https://hub.example/wp-json/flxan/v1/telemetry' ) ), $admin );
flxt_same( 'https://hub.example/wp-json/flxan/v1/telemetry', get_option( 'flxk_telemetry_url' ), 'telemetry save: https address is kept' );
flxk_t_op( 'FlxSetupTelemetrySave', array( 'input' => array( 'url' => '' ) ), $admin );
$r = flxk_t_op( 'FlxSetupGoogleCards', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'google cards: no errors' );
$ids = array_column( $r['data']['flxSetupGoogleCards'] ?? array(), 'id' );
if ( in_array( 'site-kit', $ids, true ) ) {
	$r = flxk_t_op( 'FlxSetupGoogleCardDismiss', array( 'input' => array( 'id' => 'site-kit' ) ), $admin );
	flxt_same( true, $r['data']['flxSetupGoogleCardDismiss']['ok'] ?? null, 'google cards: "Not needed" closes the card' );
	$r = flxk_t_op( 'FlxSetupGoogleCards', array(), $admin );
	flxt_ok( ! in_array( 'site-kit', array_column( $r['data']['flxSetupGoogleCards'] ?? array(), 'id' ), true ), 'google cards: closed card is left out' );
	delete_option( 'flxk_google_cards' );
}

// Wizard.
$r = flxk_t_op( 'FlxSetupWizardStep', array( 'input' => array( 'kind' => 'NAME', 'value' => 'Panel Test' ) ), $admin );
flxt_same( array( true, 'Panel Test' ), array( $r['data']['flxSetupWizardStep']['ok'] ?? null, get_option( 'blogname' ) ), 'wizard: name step saves the site name' );
$r = flxk_t_op( 'FlxSetupWizardUpdates', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'wizard: updates answers (server unreachable here)' );
flxt_ok( is_array( $r['data']['flxSetupWizardUpdates']['items'] ?? null ), 'wizard: updates list' );
$r = flxk_t_op( 'FlxSetupWizardLogo', array( 'input' => array( 'uploadId' => 'nope.nope' ) ), $admin );
flxt_ok( ! empty( $r['data']['flxSetupWizardLogo']['errors'] ) || ! empty( $r['errors'] ), 'wizard logo: unknown upload id is an error' );
$ctx = flx_gql_upload_contexts();
flxt_ok( isset( $ctx['flx-setup/logo'] ), 'wizard logo: upload context is registered' );

flxt_done();
