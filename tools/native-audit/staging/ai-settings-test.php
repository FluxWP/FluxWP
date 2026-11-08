<?php
/** Disposable WordPress/database regression. No provider or render service is contacted. */
if ( ! defined( 'FLUX_NATIVE_QA' ) || ! FLUX_NATIVE_QA || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Isolated local QA required.' ); }
function settings_assert( $yes, $message ) { if ( ! $yes ) { throw new RuntimeException( $message ); } }
function settings_request( $body = null, $bridge = false ) {
	$r = new WP_REST_Request( null === $body ? 'GET' : 'POST', $bridge ? '/flxapp/v1/settings/flxai_settings' : '/flx-ai/v1/admin/settings' );
	if ( null !== $body ) { $r->set_header( 'Content-Type', 'application/json' ); $r->set_body( wp_json_encode( $body ) ); }
	return rest_do_request( $r );
}
$original = get_option( 'flxai_settings', null ); $tasks = get_option( 'flxg_settings', null ); $user = get_current_user_id(); $subscriber = 0; $other = null; $http = 0;
$network = static function () use ( &$http ) { ++$http; return new WP_Error( 'qa_no_network', 'No external network in this regression.' ); };
add_filter( 'pre_http_request', $network, 1 );
try {
	wp_set_current_user( 0 ); foreach ( array( false, true ) as $bridge ) { foreach ( array( null, array() ) as $body ) { settings_assert( in_array( settings_request( $body, $bridge )->get_status(), array( 401, 403 ), true ), 'Anonymous settings access allowed.' ); } }
	$subscriber = wp_insert_user( array( 'user_login' => 'qa-settings-' . bin2hex( random_bytes( 4 ) ), 'user_pass' => wp_generate_password( 32 ), 'role' => 'subscriber' ) ); settings_assert( ! is_wp_error( $subscriber ), 'Fixture user failed.' ); wp_set_current_user( $subscriber );
	foreach ( array( false, true ) as $bridge ) { settings_assert( 403 === settings_request( null, $bridge )->get_status() && 403 === settings_request( array(), $bridge )->get_status(), 'Subscriber settings access allowed.' ); }
	wp_set_current_user( get_user_by( 'login', 'fluxqa' )->ID );
	flxai_update_settings( array( 'key_openrouter' => 'QA-SETTINGS-SECRET', 'source_models' => array( 'uninstalled-plugin' => 'qa/keep' ), 'tools_off' => array( 'uninstalled_tool' ), 'future_setting' => 'KEEP', 'model' => 'qa/default' ) );
	$stored_secret = get_option( 'flxai_settings' )['key_openrouter']; $state = settings_request()->get_data();
	$fields = (array) $state['fields']; $values = (array) $state['values']; $secrets = (array) $state['secrets'];
	$defaults = array_diff( array_keys( flxai_defaults() ), array( 'source_models', 'sources_off', 'tools_off', 'tool_pick' ) );
	settings_assert( 37 === count( $fields ) && ! array_diff( $defaults, array_keys( $fields ) ), 'Full defaults are not represented.' );
	settings_assert( 0 === $http && ! isset( $values['key_openrouter'] ) && $secrets['key_openrouter']['configured'] && false === strpos( wp_json_encode( $state ), 'QA-SETTINGS-SECRET' ) && false === strpos( wp_json_encode( $state ), $stored_secret ), 'Snapshot contacted a provider or exposed plaintext/ciphertext.' );
	$save = static function ( $values, $secrets = array(), $extra = array() ) { return settings_request( array_merge( array( 'revision' => settings_request()->get_data()['revision'], 'values' => $values, 'secrets' => $secrets ), $extra ) ); };
	foreach ( array( array( 'limit_day' => '0.25' ), array( 'limit_week' => -1 ), array( 'chat_max_rounds' => 2.5 ), array( 'limit_warn_pct' => 100 ), array( 'cache' => 'invalid' ), array( 'chat_roles' => array( 'invented-role' ) ), array( 'render_url' => 'javascript:alert(1)' ), array( 'media_image_model' => 'seedance-2.5' ), array( 'key_openrouter' => 'invalid-path' ), array( 'mcp_enabled' => true ) ) as $bad ) {
		$before = get_option( 'flxai_settings' ); $bad['model'] = 'qa/must-not-save';
		settings_assert( 400 === $save( $bad, array( 'key_openrouter' => array( 'action' => 'clear' ) ) )->get_status() && $before === get_option( 'flxai_settings' ), 'Invalid field partially applied or bypassed the contract.' );
	}
	$revision = $state['revision'];
	$valid = array( 'limit_day' => .25, 'limit_week' => 12.34, 'limit_month' => 100.125, 'limit_warn_pct' => 85, 'limit_notify' => false, 'chat_roles' => array( 'administrator', 'editor' ), 'chat_max_rounds' => 17, 'chat_history' => 100, 'chat_mode' => 'dock', 'chat_widgets' => false, 'anthropic_workspace' => 'wrkspc_<b>123</b>', 'title_model' => 'qa/title', 'chat_instructions' => "Line one\nLine two", 'render_url' => 'http://127.0.0.1:3000', 'render_user' => 'renderer', 'media_days' => 7, 'log_days' => 0, 'log_body_days' => 0, 'log_bodies' => false, 'delete_data' => true );
	$good = $save( $valid ); settings_assert( 200 === $good->get_status(), 'Complete settings patch failed.' );
	settings_assert( .25 === flxai_setting( 'limit_day' ) && 12.34 === flxai_setting( 'limit_week' ) && 100.13 === flxai_setting( 'limit_month' ) && 0 === flxai_setting( 'log_days' ) && 0 === flxai_setting( 'log_body_days' ) && "Line one\nLine two" === flxai_setting( 'chat_instructions' ), 'Fractional limits, multiline text or unlimited retention changed.' );
	settings_assert( $stored_secret === get_option( 'flxai_settings' )['key_openrouter'] && 'qa/keep' === flxai_setting( 'source_models' )['uninstalled-plugin'] && 'KEEP' === flxai_setting( 'future_setting' ) && array( 'uninstalled_tool' ) === flxai_setting( 'tools_off' ), 'Unrelated preference or unchanged secret lost.' );
	settings_assert( 409 === settings_request( array( 'revision' => $revision, 'values' => array( 'model' => 'qa/stale' ) ) )->get_status(), 'Stale native form saved.' );
	settings_assert( 400 === $save( array(), array( 'key_openrouter' => array( 'action' => 'replace', 'value' => '' ) ) )->get_status(), 'Empty secret replacement accepted.' );
	$replace = $save( array(), array( 'key_openrouter' => array( 'action' => 'replace', 'value' => 'QA-REPLACEMENT' ) ) );
	settings_assert( 200 === $replace->get_status() && 'QA-REPLACEMENT' === flxai_setting( 'key_openrouter' ) && 'QA-REPLACEMENT' !== get_option( 'flxai_settings' )['key_openrouter'] && false === strpos( wp_json_encode( $replace->get_data() ), 'QA-REPLACEMENT' ), 'Secret replacement not encrypted or returned plaintext.' );
	$legacy = settings_request( array( 'values' => array( 'limit_day' => .75, 'key_openrouter' => '', 'thinking' => 0 ) ), true );
	settings_assert( 200 === $legacy->get_status() && .75 === flxai_setting( 'limit_day' ) && 'QA-REPLACEMENT' === flxai_setting( 'key_openrouter' ), 'Legacy adapter truncated a budget or cleared blank secret.' );
	settings_assert( 400 === settings_request( array( 'values' => 'bad' ), true )->get_status() && .25 === flxapp_settings_clean_value( .25, array( 'type' => 'number' ) ), 'Bridge shape/decimal cleaning failed.' );
	$encoded = new WP_REST_Request( 'POST', '/flxapp/v1/settings/flxai_settings' ); $encoded->set_header( 'Content-Type', 'application/x-www-form-urlencoded' ); $encoded->set_body_params( array( 'values' => array( 'limit_day' => '0.85', 'thinking' => 'false' ) ) );
	settings_assert( 200 === rest_do_request( $encoded )->get_status() && .85 === flxai_setting( 'limit_day' ) && 0 === flxai_setting( 'thinking' ), 'Legacy form-encoded decimals/boolean strings regressed.' );
	$clear = $save( array(), array( 'key_openrouter' => array( 'action' => 'clear' ) ) ); settings_assert( 200 === $clear->get_status() && '' === flxai_setting( 'key_openrouter' ), 'Explicit secret clear failed.' );
	define( 'FLXAI_KEY_GEMINI', 'QA-CONSTANT-SECRET' );
	settings_assert( 409 === $save( array( 'model' => 'qa/no-partial' ), array( 'key_gemini' => array( 'action' => 'clear' ) ) )->get_status() && 'qa/default' === flxai_setting( 'model' ), 'Locked constant accepted or partially applied.' );
	global $wpdb; $other = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST ); settings_assert( 1 === (int) $other->get_var( $other->prepare( 'SELECT GET_LOCK(%s,0)', flxai_settings_lock_name() ) ), 'Nested settings lock leaked.' );
	settings_assert( 409 === $save( array( 'limit_day' => 2 ) )->get_status() && 409 === settings_request( array( 'values' => array( 'limit_day' => 2 ) ), true )->get_status(), 'Competing native/legacy writer not blocked.' );
	$result = flxai_update_settings( array( 'model' => 'qa/helper-overwrite' ) ); settings_assert( is_wp_error( $result ) && 409 === $result->get_error_data()['status'], 'Internal settings writer bypassed lock.' );
	$result = flxai_set_source_model( 'flx-tasks', 'qa/must-not-save' ); settings_assert( is_wp_error( $result ), 'Source-model writer bypassed lock.' );
	if ( function_exists( 'flxg_ai_migrate' ) ) { update_option( 'flxg_settings', array( 'ai_key' => 'QA-TASK-MIGRATION', 'ai_model' => 'qa/task' ), false ); flxg_ai_migrate(); settings_assert( 'QA-TASK-MIGRATION' === get_option( 'flxg_settings' )['ai_key'], 'Busy migration deleted the only credential copy.' ); }
	$other->get_var( $other->prepare( 'SELECT RELEASE_LOCK(%s)', flxai_settings_lock_name() ) ); $other->close(); $other = null;
	if ( function_exists( 'flxai_save_settings' ) ) {
		$post = $_POST; $state = settings_request()->get_data(); $input = (array) $state['values'];
		foreach ( $fields as $key => $field ) { if ( 'toggle' === $field['type'] ) { if ( empty( $input[ $key ] ) ) { unset( $input[ $key ] ); } else { $input[ $key ] = '1'; } } }
		$_POST = $input + array( 'revision' => $state['revision'], 'flxai_settings_nonce' => wp_create_nonce( 'flxai_settings' ), 'source_models' => array() );
		$_POST['limit_week'] = '2,75'; $wp = flxai_save_settings(); settings_assert( ! is_wp_error( $wp ) && 2.75 === flxai_setting( 'limit_week' ) && 'qa/keep' === flxai_setting( 'source_models' )['uninstalled-plugin'], 'WordPress form does not share decimal/preservation validation.' );
		$_POST['model'] = 'qa/stale-form'; settings_assert( is_wp_error( flxai_save_settings() ) && 'qa/default' === flxai_setting( 'model' ), 'Stale WordPress form saved.' ); $_POST = $post;
	}
	settings_assert( 0 === $http, 'Settings saving unexpectedly contacted a service.' );
	$boot = flxai_rest_boot(); settings_assert( 'dock' === $boot['mode'] && is_bool( $boot['showAdmin'] ), 'Chat bootstrap omitted the saved layout/visibility preference.' );
	echo "PASS: 37 settings, roles, strict types/bounds, fractional budgets, retention zero, encrypted write-only secrets, constants, partial validation, stale forms, legacy adapter, shared writers and migration safety.\n";
} finally {
	if ( $other ) { $other->get_var( $other->prepare( 'SELECT RELEASE_LOCK(%s)', flxai_settings_lock_name() ) ); $other->close(); }
	foreach ( array( 'flxai_settings' => $original, 'flxg_settings' => $tasks ) as $key => $value ) { if ( null === $value ) { delete_option( $key ); } else { update_option( $key, $value ); } }
	$GLOBALS['flxai_settings_cache'] = null; wp_set_current_user( $user ); remove_filter( 'pre_http_request', $network, 1 );
	if ( $subscriber && ! is_wp_error( $subscriber ) ) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $subscriber ); }
}
