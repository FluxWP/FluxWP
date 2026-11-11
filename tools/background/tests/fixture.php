<?php
/** Disposable localhost test fixture. All mail and external HTTP are intercepted. */
register_shutdown_function( function () {
	$error = error_get_last();
	if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_COMPILE_ERROR ), true ) ) { fwrite( STDERR, $error['message'] . ' at ' . $error['file'] . ':' . $error['line'] . "\n" ); }
} );
$operation = defined( 'WP_CLI' ) && WP_CLI ? ( $args[0] ?? '' ) : ( $argv[2] ?? '' );
if ( ! defined( 'ABSPATH' ) ) {
	$root = $argv[1] ?? '';
	require_once $root . '/wp-includes/plugin.php';
	add_filter( 'pre_wp_mail', '__return_true', 0 );
	add_filter( 'pre_http_request', function () { return new WP_Error( 'worker_qa_offline', 'External HTTP disabled in worker QA.' ); }, PHP_INT_MAX );
	require $root . '/wp-load.php';
}
if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) || ! function_exists( 'flx_background_should_defer' ) ) {
	throw new RuntimeException( 'Only a disposable localhost WordPress with all three plugins is supported.' );
}
add_filter( 'pre_wp_mail', function ( $return, $atts ) {
	if ( in_array( 'worker-qa@example.invalid', (array) $atts['to'], true ) ) {
		update_option( 'flx_bg_test_deliveries', (int) get_option( 'flx_bg_test_deliveries' ) + 1, false );
	}
	return true;
}, 0, 2 );
function flx_bg_test_output( $data ) { echo 'FLX_TEST_JSON=' . wp_json_encode( $data ) . "\n"; }
function flx_bg_test_assert( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
function flx_bg_test_enqueue_mail() {
	global $wpdb;
	$data = array( 'subject' => 'Worker QA', 'heading' => 'Worker QA', 'body' => 'Test', 'button_text' => '', 'button_url' => '', 'sender' => 'info', 'unsubscribe' => false );
	$wpdb->insert( flxi_table( 'campaigns' ), array( 'name' => 'Worker QA', 'channel' => 'email', 'data' => wp_json_encode( $data ), 'status' => 'queued', 'total' => 1, 'created_at' => flxi_now() ) );
	$id = (int) $wpdb->insert_id;
	flx_bg_test_assert( $id > 0, 'Campaign insert failed' );
	$wpdb->insert( flxi_table( 'queue' ), array( 'campaign_id' => $id, 'channel' => 'email', 'recipient' => 'worker-qa@example.invalid', 'name' => 'Worker QA', 'status' => 'queued', 'scheduled_at' => flxi_now() ) );
	$fixture = get_option( 'flx_bg_test_fixture' );
	$fixture['campaigns'][] = $id;
	update_option( 'flx_bg_test_fixture', $fixture, false );
	flxi_kick_queue();
	return $id;
}
function flx_bg_test_certificate() {
	$f = get_option( 'flx_bg_test_fixture' );
	$c = flxs_create_certificate( $f['template'], 0, array() );
	flx_bg_test_assert( ! is_wp_error( $c ), 'Certificate create failed' );
	$f['certificates'][] = (int) $c['id'];
	update_option( 'flx_bg_test_fixture', $f, false );
	return $c;
}
function flx_bg_test_backup( $sync = false ) {
	$f = get_option( 'flx_bg_test_fixture' );
	$r = FLXY_Backup::create( array( 'type' => 'files', 'db' => false, 'paths' => array( 'wp-content/uploads/flx-background-qa' ), 'interactive' => true, 'sync' => $sync ) );
	flx_bg_test_assert( ! is_wp_error( $r ), 'Backup create failed' );
	$f['backups'][] = $r['id'];
	update_option( 'flx_bg_test_fixture', $f, false );
	return $r;
}
global $wpdb;
switch ( $operation ) {
	case 'setup':
		flx_bg_test_assert( ! get_option( 'flx_bg_test_fixture' ) && ! FLXY_Runner::busy(), 'Fixture already exists or a backup is busy' );
		$f = array( 'saved' => array(), 'campaigns' => array(), 'certificates' => array(), 'backups' => array() );
		foreach ( array( 'flx_background_worker', 'flxi_settings', 'flxi_quota', 'flxi_quota_paused', 'flxi_netgsm_active', 'flxy_job', 'flxy_indicator', 'cron', 'active_plugins' ) as $key ) { $f['saved'][ $key ] = get_option( $key ); }
		$f['saved']['flx_docker_bootstrap'] = get_option( 'flx_docker_bootstrap' );
		foreach ( array( 'mail' => flxi_table( 'queue' ), 'cert' => flxs_table( 'messages' ) ) as $name => $table ) {
			$f['old_' . $name] = $wpdb->get_results( "SELECT id,status FROM $table WHERE status IN ('queued','processing')", ARRAY_A );
			$wpdb->query( "UPDATE $table SET status = 'cancelled' WHERE status IN ('queued','processing')" );
		}
		update_option( 'flx_bg_test_fixture', $f, false );
		delete_option( 'flx_background_worker' );
		update_option( 'flxi_netgsm_active', array(), false );
		update_option( 'flxi_quota', array(), false );
		$settings = flxi_settings(); $settings['bulk_per_run'] = 1; $settings['limit_daily'] = 100; $settings['limit_reserve'] = 0;
		update_option( 'flxi_settings', $settings, false );
		update_option( 'flx_bg_test_deliveries', 0, false );
		wp_mkdir_p( WP_CONTENT_DIR . '/uploads/flx-background-qa' );
		file_put_contents( WP_CONTENT_DIR . '/uploads/flx-background-qa/probe.txt', str_repeat( 'background QA ', 200 ) );
		$f = get_option( 'flx_bg_test_fixture' );
		$f['template'] = flxs_save_template( 0, 'Background worker QA', array( 'title' => 'Background worker QA', 'fields' => array(), 'elements' => array() ) );
		$f['provider'] = flxs_save_provider( 0, 'wpmail', 'Background worker QA', array(), true );
		flx_bg_test_assert( ! is_wp_error( $f['provider'] ), 'Provider create failed' );
		update_option( 'flx_bg_test_fixture', $f, false );
		flx_bg_test_enqueue_mail();
		$c = flx_bg_test_certificate();
		flx_bg_test_output( array( 'setup' => true, 'native_as_scheduled' => function_exists( 'as_has_scheduled_action' ) && (bool) as_has_scheduled_action( 'flxs_render_files', array( (int) $c['id'] ), 'flx-certificates' ) ) );
		break;
	case 'mail':
		$r = flxi_run_queue( 6 );
		flx_bg_test_output( array( 'processed' => $r['processed'], 'sent' => $r['sent'] ?? 0, 'deferred' => ! empty( $r['deferred'] ), 'paused' => ! empty( $r['paused_until'] ), 'deliveries' => (int) get_option( 'flx_bg_test_deliveries' ), 'scheduled' => (bool) wp_next_scheduled( 'flxi_run_queue' ) ) );
		break;
	case 'prepare-worker':
		flx_bg_test_enqueue_mail();
		update_option( 'flx_background_worker', array( 'seen_at' => time(), 'expires_at' => time() + 300 ), false );
		$f = get_option( 'flx_bg_test_fixture' );
		flxs_enqueue( array( 'channel' => 'email', 'recipient' => 'worker-qa@example.invalid', 'subject' => 'Certificate worker QA', 'body' => 'Worker QA', 'provider_id' => $f['provider'] ) );
		flx_bg_test_backup();
		flx_bg_test_output( array( 'prepared' => true ) );
		break;
	case 'pdf':
		$f = get_option( 'flx_bg_test_fixture' ); $c = flxs_get_certificate( end( $f['certificates'] ) );
		$r = flxs_render_certificate_files( $c );
		$c = flxs_get_certificate( $c['id'] );
		flx_bg_test_output( array( 'pending' => is_wp_error( $r ) && 'flxs_render_busy' === $r->get_error_code(), 'pdf' => ! empty( $c['pdf_file'] ), 'scheduled' => (bool) wp_next_scheduled( 'flxs_render_files', array( (int) $c['id'] ) ) ) );
		break;
	case 'cert-mail':
		$r = flxs_run_queue( 5 );
		flx_bg_test_output( array( 'processed' => $r['processed'], 'deferred' => ! empty( $r['deferred'] ), 'scheduled' => (bool) wp_next_scheduled( 'flxs_run_queue' ) ) );
		break;
	case 'backup':
		$before = FLXY_Runner::current(); $after = FLXY_Runner::run_step( true );
		flx_bg_test_output( array( 'phase' => $after['phase'], 'unchanged' => $before === $after ) );
		break;
	case 'worker':
		flx_bg_test_assert( flx_background_worker_context(), 'The real worker marker is missing' );
		flx_background_worker_heartbeat();
		$mr = flxi_run_queue( 6 ); $cr = flxs_run_queue( 5 );
		$f = get_option( 'flx_bg_test_fixture' ); $id = end( $f['certificates'] );
		flxs_render_files_job( $id ); $c = flxs_get_certificate( $id );
		for ( $i = 0; $i < 20 && FLXY_Runner::busy(); ++$i ) { FLXY_Runner::cron_tick(); }
		$s = FLXY_Runner::current();
		$pdf_path = flxs_certificate_file_path( $c, 'pdf' );
		$record = FLXY_Storage::get( $s['id'] ); $zip = new ZipArchive();
		$valid_zip = true === $zip->open( $record['file'] ) && false !== $zip->locateName( 'manifest.json' ) && false !== $zip->locateName( 'files/wp-content/uploads/flx-background-qa/probe.txt' );
		$zip->close();
		flx_bg_test_output( array( 'worker' => true, 'mail_sent' => $mr['sent'], 'cert_processed' => $cr['processed'], 'pdf' => ! empty( $c['pdf_file'] ) && '%PDF-' === file_get_contents( $pdf_path, false, null, 0, 5 ), 'image' => ! empty( $c['image_file'] ), 'backup' => $s['phase'], 'valid_zip' => $valid_zip, 'heartbeat' => flx_background_worker_active(), 'deliveries' => (int) get_option( 'flx_bg_test_deliveries' ) ) );
		break;
	case 'stale-pdf':
		$f = get_option( 'flx_bg_test_fixture' ); $c = flxs_get_certificate( end( $f['certificates'] ) );
		$path = flxs_certificate_file_path( $c, 'pdf' ); touch( $path, 1700000000 );
		$c['pdf_file'] = null;
		flxs_render_certificate_files( $c, array( 'pdf' ) ); clearstatcache( true, $path );
		flx_bg_test_output( array( 'preserved' => 1700000000 === filemtime( $path ) ) );
		break;
	case 'sync-backup':
		$r = flx_bg_test_backup( true );
		flx_bg_test_output( array( 'status' => $r['status'] ) );
		break;
	case 'stale':
		update_option( 'flx_background_worker', array( 'seen_at' => time() - 600, 'expires_at' => time() - 1 ), false );
		flx_bg_test_enqueue_mail(); flx_bg_test_certificate(); flx_bg_test_backup();
		flx_bg_test_output( array( 'active' => flx_background_worker_active() ) );
		break;
	case 'quota':
		$s = flxi_settings(); $s['limit_daily'] = 1; $s['limit_reserve'] = 0; update_option( 'flxi_settings', $s, false );
		update_option( 'flxi_quota', array( flxi_quota_mailbox( 'info' ) => array( array( time(), 1 ) ) ), false );
		flx_bg_test_enqueue_mail(); flx_bg_test_output( array( 'quota' => true ) );
		break;
	case 'prepare-retry':
		flx_bg_test_enqueue_mail(); flx_bg_test_output( array( 'prepared' => true ) );
		break;
	case 'mail-failure':
		$throw = function () { throw new RuntimeException( 'Worker QA temporary failure' ); };
		add_filter( 'flxi_mail_atts', $throw ); $r = flxi_run_queue( 6 ); remove_filter( 'flxi_mail_atts', $throw );
		$f = get_option( 'flx_bg_test_fixture' ); $cid = end( $f['campaigns'] );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT status,attempts,scheduled_at FROM ' . flxi_table( 'queue' ) . ' WHERE campaign_id = %d', $cid ), ARRAY_A );
		flx_bg_test_output( array( 'processed' => $r['processed'], 'sent' => $r['sent'], 'queued' => 'queued' === $row['status'], 'attempts' => (int) $row['attempts'], 'delayed' => strtotime( $row['scheduled_at'] . ' UTC' ) > time() + 60 ) );
		break;
	case 'retry-now':
		$f = get_option( 'flx_bg_test_fixture' ); $cid = end( $f['campaigns'] );
		$wpdb->update( flxi_table( 'queue' ), array( 'scheduled_at' => flxi_now() ), array( 'campaign_id' => $cid ) );
		$r = flxi_run_queue( 6 ); flx_bg_test_output( array( 'sent' => $r['sent'] ) );
		break;
	case 'interrupted':
		$t = flxi_table( 'queue' ); $cid = flx_bg_test_enqueue_mail();
		$wpdb->update( $t, array( 'status' => 'processing', 'attempts' => 1, 'scheduled_at' => flxi_now() ), array( 'campaign_id' => $cid ) );
		wp_clear_scheduled_hook( 'flxi_run_queue' ); flxi_run_queue( 6 );
		$mail_pending = (bool) wp_next_scheduled( 'flxi_run_queue' );
		$wpdb->update( $t, array( 'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() - 601 ) ), array( 'campaign_id' => $cid ) );
		$r = flxi_run_queue( 6 ); $mail_resumed = 1 === $r['sent'];
		$wpdb->update( $t, array( 'status' => 'processing', 'attempts' => 3, 'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() - 601 ) ), array( 'campaign_id' => $cid ) );
		flxi_run_queue( 6 ); $mail_stopped = 'failed' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $t WHERE campaign_id=%d", $cid ) );
		$t = flxs_table( 'messages' ); $id = (int) $wpdb->get_var( "SELECT MAX(id) FROM $t WHERE recipient='worker-qa@example.invalid'" );
		$wpdb->update( $t, array( 'status' => 'processing', 'attempts' => 1, 'locked_at' => flxs_now() ), array( 'id' => $id ) );
		wp_clear_scheduled_hook( 'flxs_run_queue' ); flxs_run_queue( 6 );
		$cert_pending = (bool) wp_next_scheduled( 'flxs_run_queue' ) || ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( 'flxs_run_queue', array(), 'flx-certificates' ) );
		$wpdb->update( $t, array( 'locked_at' => gmdate( 'Y-m-d H:i:s', time() - 601 ) ), array( 'id' => $id ) );
		$r = flxs_run_queue( 6 ); $cert_resumed = 1 === $r['sent'];
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET status='processing', attempts=max_attempts, locked_at=%s WHERE id=%d", gmdate( 'Y-m-d H:i:s', time() - 601 ), $id ) );
		flxs_run_queue( 6 ); $cert_stopped = 'failed' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $t WHERE id=%d", $id ) );
		flx_bg_test_output( array( 'scheduled' => $mail_pending && $cert_pending, 'resumed' => $mail_resumed && $cert_resumed, 'bounded' => $mail_stopped && $cert_stopped ) );
		break;
	case 'pdf-retry':
		$c = flx_bg_test_certificate();
		$unwritable = function ( $name, $ext, $dir ) {
			return 0 === strpos( $name, 'flxs' ) && rtrim( $dir, '/' ) === rtrim( get_temp_dir(), '/' ) ? 'missing-worker-qa-dir/' . $name : $name;
		};
		add_filter( 'wp_unique_filename', $unwritable, 99, 3 );
		flxs_render_files_job( $c['id'] );
		remove_filter( 'wp_unique_filename', $unwritable, 99 );
		$retry = (int) get_transient( 'flxs_render_retry_' . $c['id'] );
		flxs_render_files_job( $c['id'] ); $after = flxs_get_certificate( $c['id'] );
		flx_bg_test_output( array( 'retried' => 1 === $retry && ! get_transient( 'flxs_render_retry_' . $c['id'] ) && ! empty( $after['pdf_file'] ), 'attempts' => $retry, 'pdf' => ! empty( $after['pdf_file'] ), 'remaining_retry' => (int) get_transient( 'flxs_render_retry_' . $c['id'] ) ) );
		break;
	case 'minimal-plugins':
		delete_option( 'flx_background_worker' );
		$f = get_option( 'flx_bg_test_fixture' );
		if ( function_exists( 'as_unschedule_all_actions' ) ) { foreach ( $f['certificates'] as $id ) { as_unschedule_all_actions( 'flxs_render_files', array( (int) $id ), 'flx-certificates' ); } }
		update_option( 'active_plugins', array( 'flx-mail/flx-mail.php', 'flx-certificates/flx-certificates.php', 'flx-backup/flx-backup.php' ) );
		flx_bg_test_output( array( 'minimal' => true ) );
		break;
	case 'no-as':
		flx_bg_test_assert( ! function_exists( 'as_schedule_single_action' ), 'Action Scheduler is unexpectedly loaded' );
		$c = flx_bg_test_certificate();
		$scheduled = (bool) wp_next_scheduled( 'flxs_render_files', array( (int) $c['id'] ) );
		$r = flxs_render_certificate_files( $c );
		flxs_kick_queue();
		flx_bg_test_output( array( 'native_cron' => $scheduled, 'pdf' => ! is_wp_error( $r ) && ! empty( flxs_get_certificate( $c['id'] )['pdf_file'] ), 'message_cron' => (bool) wp_next_scheduled( 'flxs_run_queue' ) ) );
		break;
	case 'locks':
		$a = flx_background_lock( 'qa-lock', 30 );
		flx_bg_test_assert( $a && ! flx_background_lock( 'qa-lock', 30 ), 'Lock exclusion failed' );
		flx_background_unlock( 'qa-lock', 'not-the-owner' );
		flx_bg_test_assert( ! flx_background_lock( 'qa-lock', 30 ), 'Foreign lock release succeeded' );
		$key = 'flx_bg_lock_' . md5( 'qa-lock' );
		update_option( $key, wp_json_encode( array( 'token' => $a, 'expires_at' => time() - 1 ) ), false );
		$b = flx_background_lock( 'qa-lock', 30 );
		flx_bg_test_assert( $b && $b !== $a, 'Expired lock not reclaimed' );
		flx_background_unlock( 'qa-lock', $a );
		flx_bg_test_assert( ! flx_background_lock( 'qa-lock', 30 ), 'Old owner deleted successor lock' );
		flx_background_unlock( 'qa-lock', $b ); delete_option( $key );
		flx_bg_test_output( array( 'owner_locks' => true ) );
		break;
	case 'library':
		delete_option( 'flx_background_worker' );
		$hook = 'flx_bg_test_guard'; $args = array( 17 ); $calls = 0;
		$callback = function () use ( &$calls ) { ++$calls; return 'finished'; };
		$owner = flx_background_lock( 'qa-guard', 60 );
		$r = flx_background_run( 'qa-guard', $callback, array( 'hook' => $hook, 'args' => $args ) );
		$blocked = ! empty( $r['locked'] ) && 0 === $calls && (bool) wp_next_scheduled( $hook, $args );
		flx_background_unlock( 'qa-guard', $owner );
		$failed = false;
		try { flx_background_run( 'qa-guard', function () { throw new RuntimeException( 'guard-test' ); } ); }
		catch ( RuntimeException $e ) { $failed = 'guard-test' === $e->getMessage(); }
		$completed = 'finished' === flx_background_run( 'qa-guard', $callback ) && 1 === $calls;
		$key = 'flx_bg_lock_' . md5( 'qa-corrupt' ); update_option( $key, '{broken', false );
		$token = flx_background_lock( 'qa-corrupt', 60 );
		$recovered = (bool) $token; flx_background_unlock( 'qa-corrupt', $token );
		wp_clear_scheduled_hook( $hook, $args );
		flx_bg_test_output( array( 'blocked' => $blocked, 'exception_released' => $failed && $completed, 'corrupt_recovered' => $recovered ) );
		break;
	case 'pool-setup':
		$mu = <<<'QA'
<?php
    add_filter( 'pre_wp_mail', static function ( $return, $atts ) {
        if ( getenv( 'FLX_BACKGROUND_CHILD' ) && in_array( 'worker-qa@example.invalid', (array) $atts['to'], true ) ) {
            global $wpdb;
            $wpdb->query( "UPDATE {$wpdb->options} SET option_value = CAST(option_value AS UNSIGNED) + 1 WHERE option_name = 'flx_bg_test_deliveries'" );
            wp_cache_delete( 'flx_bg_test_deliveries', 'options' );
        }
        return true;
    }, 0, 2 );
    add_filter( 'pre_http_request', static function () { return new WP_Error( 'worker_qa_offline', 'External HTTP disabled in worker QA.' ); }, PHP_INT_MAX );
add_action( 'init', static function () {
    if ( ! class_exists( 'FLX_Background_Pool' ) ) { return; } // core is-installed may skip ordinary plugins.
    FLX_Background_Pool::register( 'flx_bg_qa_sleep', 'flx-background-qa' );
    FLX_Background_Pool::register( 'flx_bg_qa_fail', 'flx-background-qa' );
    FLX_Background_Pool::register( 'flx_bg_qa_hang', 'flx-background-qa' );
    FLX_Background_Pool::register( 'flx_bg_qa_other', 'flx-background-qa-other' );
} );
foreach ( array( 'flx_bg_qa_sleep', 'flx_bg_qa_fail', 'flx_bg_qa_hang', 'flx_bg_qa_other' ) as $hook ) {
    add_action( $hook, static function ( $id ) use ( $hook ) {
        if ( FLX_Background_Pool::dispatch( $hook, array( $id ) ) ) { return; }
        $started = microtime( true );
        if ( 'flx_bg_qa_fail' === $hook ) { throw new RuntimeException( 'QA callback failed' ); }
        if ( 'flx_bg_qa_hang' === $hook ) { sleep( 20 ); }
        else { sleep( 2 ); }
        update_option( 'flx_bg_qa_pool_' . $id, array( 'start' => $started, 'end' => microtime( true ), 'pid' => getmypid() ), false );
        flx_background_schedule( 'flx_bg_qa_followup', array( $id ), 120 );
    } );
}
QA;
		file_put_contents( WPMU_PLUGIN_DIR . '/flx-background-qa.php', $mu );
		flx_bg_test_output( array( 'setup' => true ) );
		break;
	case 'pool':
		defined( 'DOING_CRON' ) || define( 'DOING_CRON', true );
		for ( $i = 0; $i < 6; ++$i ) { do_action( 'flx_bg_qa_sleep', $i ); }
		FLX_Background_Pool::finish();
		$rows = array(); for ( $i = 0; $i < 6; ++$i ) { $rows[] = get_option( 'flx_bg_qa_pool_' . $i ); }
		$scheduled = 0; wp_cache_delete( 'cron', 'options' );
		for ( $i = 0; $i < 6; ++$i ) { $scheduled += (int) (bool) wp_next_scheduled( 'flx_bg_qa_followup', array( $i ) ); }
		flx_bg_test_output( array( 'rows' => $rows, 'followups' => $scheduled ) );
		break;
	case 'pool-failure':
		defined( 'DOING_CRON' ) || define( 'DOING_CRON', true );
		add_filter( 'flx_background_timeout', static function () { return 1; } );
		do_action( 'flx_bg_qa_fail', 'fail' ); do_action( 'flx_bg_qa_hang', 'hang' );
		FLX_Background_Pool::finish();
		$names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'flx_bg_task_%'" );
		$recoverable = count( $names ) === 2;
		for ( $i = 0; $i < FLX_Background_Pool::limit(); ++$i ) { delete_option( 'flx_bg_lock_' . md5( 'process-slot-' . $i ) ); }
		for ( $i = 0; $i < FLX_Background_Pool::plugin_limit( 'flx-background-qa' ); ++$i ) { delete_option( 'flx_bg_lock_' . md5( 'plugin-slot-flx-background-qa-' . $i ) ); }
		foreach ( $names as $name ) {
			$id = substr( $name, strlen( 'flx_bg_task_' ) );
			$recoverable = $recoverable && (bool) wp_next_scheduled( 'flx_background_watchdog', array( $id ) );
			// Emulate the lease expiry after the killed test process, without waiting.
			delete_option( 'flx_bg_lock_' . md5( 'dispatch-' . $id ) );
			$task = get_option( $name ); $task['hook'] = 'flx_bg_qa_sleep'; $task['args'] = array( $id ); update_option( $name, $task, false );
			FLX_Background_Pool::watchdog( $id );
			$recoverable = $recoverable && ! get_option( $name ) && (bool) get_option( 'flx_bg_qa_pool_' . $id );
		}
		flx_bg_test_output( array( 'recovered' => $recoverable ) );
		break;
	case 'pool-slots':
		defined( 'DOING_CRON' ) || define( 'DOING_CRON', true );
		$slots = array(); for ( $i = 0; $i < FLX_Background_Pool::limit(); ++$i ) { $slots[ $i ] = flx_background_lock( 'process-slot-' . $i, 180 ); }
		try {
			do_action( 'flx_bg_qa_sleep', 'slots' ); FLX_Background_Pool::finish();
			$names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'flx_bg_task_%'" );
			$blocked = count( $names ) === 1 && ! get_option( 'flx_bg_qa_pool_slots' );
		} finally { foreach ( $slots as $i => $owner ) { flx_background_unlock( 'process-slot-' . $i, $owner ); } }
		$id = substr( $names[0], strlen( 'flx_bg_task_' ) ); FLX_Background_Pool::watchdog( $id );
		$recovered = ! get_option( $names[0] ) && (bool) get_option( 'flx_bg_qa_pool_slots' );
		// A permanently failing dispatch must terminate rather than retry forever.
		$id = wp_generate_uuid4(); $name = 'flx_bg_task_' . $id;
		add_option( $name, array( 'hook' => 'flx_bg_qa_fail', 'args' => array( 'max' ), 'attempts' => 3, 'status' => 'failed' ), '', false );
		$failed = 0; add_action( 'flx_background_failed', function () use ( &$failed ) { ++$failed; } );
		FLX_Background_Pool::watchdog( $id );
		flx_bg_test_output( array( 'global_limit' => $blocked && $recovered, 'bounded_retry' => 1 === $failed && ! get_option( $name ) ) );
		break;
	case 'parallel-prepare':
		$settings = flxi_settings(); $settings['limit_daily'] = 100; update_option( 'flxi_settings', $settings, false );
		update_option( 'flxi_quota', array(), false ); delete_option( 'flxi_quota_paused' );
		$f = get_option( 'flx_bg_test_fixture' ); $f['parallel_before'] = (int) get_option( 'flx_bg_test_deliveries' ); update_option( 'flx_bg_test_fixture', $f, false );
		flx_bg_test_enqueue_mail(); flx_bg_test_certificate(); flx_bg_test_backup();
		flxs_enqueue( array( 'channel' => 'email', 'recipient' => 'worker-qa@example.invalid', 'subject' => 'Parallel certificate QA', 'body' => 'QA', 'provider_id' => $f['provider'] ) );
		flx_bg_test_output( array( 'prepared' => true ) );
		break;
	case 'pool-native-prepare':
		for ( $i = 0; $i < 6; ++$i ) { flx_background_schedule( 'flx_bg_qa_sleep', array( 'native' . $i ), 1 ); }
		flx_bg_test_output( array( 'prepared' => true ) );
		break;
	case 'pool-native-results':
		$rows = array(); for ( $i = 0; $i < 6; ++$i ) { $rows[] = get_option( 'flx_bg_qa_pool_native' . $i ); }
		flx_bg_test_output( array( 'rows' => $rows ) );
		break;
	case 'pool-groups':
		defined( 'DOING_CRON' ) || define( 'DOING_CRON', true );
		for ( $i = 0; $i < 3; ++$i ) { do_action( 'flx_bg_qa_sleep', 'group-a-' . $i ); do_action( 'flx_bg_qa_other', 'group-b-' . $i ); }
		FLX_Background_Pool::finish(); $rows = array();
		foreach ( array( 'a', 'b' ) as $group ) { for ( $i = 0; $i < 3; ++$i ) { $row = get_option( 'flx_bg_qa_pool_group-' . $group . '-' . $i ); $row['group'] = $group; $rows[] = $row; } }
		flx_bg_test_output( array( 'rows' => $rows, 'global' => FLX_Background_Pool::limit(), 'plugin' => FLX_Background_Pool::plugin_limit( 'flx-background-qa' ) ) );
		break;
	case 'wake-check':
		delete_option( 'flx_background_worker' ); flx_background_schedule( 'flx_bg_test_inactive_signal', array(), 12345 );
		$t = wp_next_scheduled( 'flx_bg_test_inactive_signal' );
		$inactive = ! file_exists( WP_CONTENT_DIR . '/.flx-background-wake/' . $t );
		update_option( 'flx_background_worker', array( 'seen_at' => time(), 'expires_at' => time() + 300, 'wake' => false ), false );
		flx_background_schedule( 'flx_bg_test_legacy_signal', array(), 12346 ); $legacy = wp_next_scheduled( 'flx_bg_test_legacy_signal' );
		$unsupported = ! file_exists( WP_CONTENT_DIR . '/.flx-background-wake/' . $legacy );
		update_option( 'flx_background_worker', array( 'seen_at' => time(), 'expires_at' => time() + 300, 'wake' => true ), false );
		flx_background_schedule( 'flx_bg_test_signal', array(), 2 ); $t = wp_next_scheduled( 'flx_bg_test_signal' );
		$signal = WP_CONTENT_DIR . '/.flx-background-wake/' . $t;
		flx_bg_test_output( array( 'inactive' => $inactive, 'unsupported' => $unsupported, 'signaled' => is_file( $signal ) && 0 === filesize( $signal ) ) );
		break;
	case 'wake-prepare':
		$excluded = array();
		foreach ( _get_cron_array() as $events ) { foreach ( $events as $hook => $items ) { $excluded[ $hook ] = true; } }
		foreach ( array( 'wp_version_check', 'wp_update_plugins', 'wp_update_themes', 'wp_maybe_auto_update', 'action_scheduler_run_queue' ) as $hook ) { $excluded[ $hook ] = true; }
		unset( $excluded['flx_bg_qa_sleep'] );
		update_option( 'cron', array( 'version' => 2 ), false ); update_option( 'flx_docker_bootstrap', 'done', false );
		foreach ( glob( WP_CONTENT_DIR . '/.flx-background-wake/[0-9]*' ) ?: array() as $signal ) { @unlink( $signal ); }
		flx_bg_test_output( array( 'prepared' => true, 'excluded' => implode( ',', array_keys( $excluded ) ) ) );
		break;
	case 'channels':
		require __DIR__ . '/netgsm.php';
		break;
	case 'wake-enqueue':
		flx_background_schedule( 'flx_bg_qa_sleep', array( 'wake' ), 1 );
		flx_bg_test_output( array( 'enqueued' => microtime( true ) ) );
		break;
	case 'wake-result':
		flx_bg_test_output( array( 'row' => get_option( 'flx_bg_qa_pool_wake' ) ) );
		break;
	case 'parallel-real':
		defined( 'DOING_CRON' ) || define( 'DOING_CRON', true );
		$f = get_option( 'flx_bg_test_fixture' ); $id = end( $f['certificates'] );
		do_action( 'flxi_run_queue' ); do_action( 'flxs_run_queue' ); do_action( 'flxs_render_files', $id ); do_action( 'flxy_job_tick' );
		FLX_Background_Pool::finish();
		$c = flxs_get_certificate( $id ); $s = FLXY_Runner::current();
		wp_cache_delete( 'flx_bg_test_deliveries', 'options' );
		$cid = end( $f['campaigns'] );
		flx_bg_test_output( array( 'delivered' => (int) get_option( 'flx_bg_test_deliveries' ) - $f['parallel_before'], 'pdf' => ! empty( $c['pdf_file'] ), 'backup' => $s['phase'], 'campaign' => $wpdb->get_col( $wpdb->prepare( 'SELECT status FROM ' . flxi_table( 'queue' ) . ' WHERE campaign_id=%d', $cid ) ), 'cert' => $wpdb->get_col( "SELECT status FROM " . flxs_table( 'messages' ) . " WHERE recipient='worker-qa@example.invalid'" ) ) );
		break;
	case 'race':
		$barrier = (float) get_option( 'flx_bg_test_barrier' );
		while ( microtime( true ) < $barrier ) { usleep( 500 ); }
		flx_bg_test_output( array( 'claimed' => (bool) flx_background_lock( 'qa-race', 60 ) ) );
		break;
	case 'race-prepare':
		update_option( 'flx_bg_test_barrier', microtime( true ) + 3, false );
		flx_bg_test_output( array( 'prepared' => true ) );
		break;
	case 'journal':
		$limit = static function () { return 100; }; add_filter( 'flx_background_log_limit', $limit );
		$marker = 'flx_background_' . 'journal_pruned';
		$old_key = 'flx_background_event_' . ( time() - 20 * DAY_IN_SECONDS ) . '_old'; add_option( $old_key, array( 'event' => 'old', 'time' => time() - 20 * DAY_IN_SECONDS ), '', false );
		add_option( $marker, time(), '', false );
		$state = get_option( 'flx_background_log_db', array() ); if ( ! is_array( $state ) ) { $state = array(); }
		$state['version'] = 1; $state['legacy'] = 0; $state['pruned'] = time(); update_option( 'flx_background_log_db', $state, true );
		for ( $i = 0; $i < 105; ++$i ) { flx_background_log( 'qa.bounded', array( 'plugin' => 'flx-mail', 'count' => $i ) ); }
		flx_background_log_flush();
		$table = $wpdb->prefix . 'flx_background_log';
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (plugin, event, `time`, campaign, context) VALUES (%s,%s,%d,%d,%s)", 'flx-mail', 'qa.old', time() - 20 * DAY_IN_SECONDS, 0, '{}' ) );
		$state = get_option( 'flx_background_log_db', array() ); $state['pruned'] = 0; update_option( 'flx_background_log_db', $state, true );
		flx_background_log_retain();
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$autoload = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s AND autoload IN ('yes','on','auto-on','auto')", 'flx_background_log_db' ) );
		wp_cache_delete( $old_key, 'options' ); wp_cache_delete( $marker, 'options' );
		$aged = true; foreach ( flx_background_events( 200, 0, 'flx-mail' ) as $event ) { if ( 'qa.old' === ( $event['event'] ?? '' ) ) { $aged = false; } }
		$expired = false === get_option( $old_key ) && false === get_option( $marker ) && $aged;
		remove_filter( 'flx_background_log_limit', $limit );
		flx_bg_test_output( array( 'bounded' => $count <= 100 && $count >= 1, 'autoloaded' => 1 === $autoload, 'expired' => $expired ) );
		break;
	case 'cleanup':
		@unlink( WPMU_PLUGIN_DIR . '/flx-background-qa.php' );
		foreach ( $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'flx_bg_qa_pool_%' OR option_name LIKE 'flx_bg_task_%'" ) as $name ) {
			if ( 0 === strpos( $name, 'flx_bg_task_' ) ) { delete_option( 'flx_bg_lock_' . md5( 'dispatch-' . substr( $name, strlen( 'flx_bg_task_' ) ) ) ); }
			delete_option( $name );
		}
		for ( $i = 0; $i < 64; ++$i ) { delete_option( 'flx_bg_lock_' . md5( 'process-slot-' . $i ) ); }
		for ( $i = 0; $i < 16; ++$i ) { delete_option( 'flx_bg_lock_' . md5( 'plugin-slot-flx-background-qa-' . $i ) ); }
		foreach ( glob( WP_CONTENT_DIR . '/.flx-background-wake/[0-9]*' ) ?: array() as $signal ) { @unlink( $signal ); }
		@rmdir( WP_CONTENT_DIR . '/.flx-background-wake' );
		$f = get_option( 'flx_bg_test_fixture' );
		if ( $f ) {
			foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'flxi_netgsm_batch_' ) . '%' ) ) as $key ) {
				$batch = get_option( $key );
				if ( is_array( $batch ) && ( in_array( (int) $batch['campaign'], array_map( 'intval', $f['campaigns'] ), true ) || in_array( (int) $batch['connection'], array_map( 'intval', $f['connections'] ?? array() ), true ) ) ) { delete_option( $key ); }
			}
			delete_option( 'flxi_netgsm_report_rate_' . hash( 'sha256', 'qa' ) );
			foreach ( $f['backups'] as $id ) { FLXY_Storage::delete( $id ); }
			foreach ( $f['certificates'] as $id ) {
				if ( function_exists( 'as_unschedule_all_actions' ) ) { as_unschedule_all_actions( 'flxs_render_files', array( (int) $id ), 'flx-certificates' ); }
				delete_transient( 'flxs_render_retry_' . $id );
				$c = flxs_get_certificate( $id );
				if ( $c && function_exists( 'flxs_delete_certificate' ) ) { flxs_delete_certificate( $id ); }
			}
			foreach ( $f['direct_rows'] ?? array() as $id ) { $wpdb->delete( flxi_table( 'queue' ), array( 'id' => $id ) ); }
			foreach ( $f['campaigns'] as $id ) { $wpdb->delete( flxi_table( 'queue' ), array( 'campaign_id' => $id ) ); $wpdb->delete( flxi_table( 'campaigns' ), array( 'id' => $id ) ); }
			foreach ( $f['connections'] ?? array() as $id ) { flxi_delete_connection( $id ); }
			$wpdb->query( "DELETE FROM " . flxs_table( 'messages' ) . " WHERE recipient = 'worker-qa@example.invalid'" );
			$wpdb->delete( flxs_table( 'providers' ), array( 'id' => $f['provider'] ) ); $wpdb->delete( flxs_table( 'templates' ), array( 'id' => $f['template'] ) );
			foreach ( array( 'mail' => flxi_table( 'queue' ), 'cert' => flxs_table( 'messages' ) ) as $name => $table ) { foreach ( $f['old_' . $name] as $r ) { $wpdb->update( $table, array( 'status' => $r['status'] ), array( 'id' => $r['id'] ) ); } }
			foreach ( $f['saved'] as $key => $value ) { false === $value ? delete_option( $key ) : update_option( $key, $value ); }
		}
		@unlink( WP_CONTENT_DIR . '/uploads/flx-background-qa/probe.txt' ); @rmdir( WP_CONTENT_DIR . '/uploads/flx-background-qa' );
		delete_option( 'flx_bg_lock_' . md5( 'qa-race' ) ); delete_option( 'flx_bg_test_fixture' ); delete_option( 'flx_bg_test_deliveries' );
		delete_option( 'flx_bg_test_barrier' );
		flx_bg_test_output( array( 'cleaned' => true ) );
		break;
	default: throw new RuntimeException( 'Unknown test operation' );
}
