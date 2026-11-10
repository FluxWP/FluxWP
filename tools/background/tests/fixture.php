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
		foreach ( array( 'flx_background_worker', 'flxi_settings', 'flxi_quota', 'flxi_quota_paused', 'flxy_job', 'flxy_indicator', 'cron', 'active_plugins' ) as $key ) { $f['saved'][ $key ] = get_option( $key ); }
		foreach ( array( 'mail' => flxi_table( 'queue' ), 'cert' => flxs_table( 'messages' ) ) as $name => $table ) {
			$f['old_' . $name] = $wpdb->get_results( "SELECT id,status FROM $table WHERE status IN ('queued','processing')", ARRAY_A );
			$wpdb->query( "UPDATE $table SET status = 'cancelled' WHERE status IN ('queued','processing')" );
		}
		update_option( 'flx_bg_test_fixture', $f, false );
		delete_option( 'flx_background_worker' );
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
	case 'race':
		flx_bg_test_output( array( 'claimed' => (bool) flx_background_lock( 'qa-race', 60 ) ) );
		break;
	case 'cleanup':
		$f = get_option( 'flx_bg_test_fixture' );
		if ( $f ) {
			foreach ( $f['backups'] as $id ) { FLXY_Storage::delete( $id ); }
			foreach ( $f['certificates'] as $id ) {
				if ( function_exists( 'as_unschedule_all_actions' ) ) { as_unschedule_all_actions( 'flxs_render_files', array( (int) $id ), 'flx-certificates' ); }
				delete_transient( 'flxs_render_retry_' . $id );
				$c = flxs_get_certificate( $id );
				if ( $c && function_exists( 'flxs_delete_certificate' ) ) { flxs_delete_certificate( $id ); }
			}
			foreach ( $f['campaigns'] as $id ) { $wpdb->delete( flxi_table( 'queue' ), array( 'campaign_id' => $id ) ); $wpdb->delete( flxi_table( 'campaigns' ), array( 'id' => $id ) ); }
			$wpdb->query( "DELETE FROM " . flxs_table( 'messages' ) . " WHERE recipient = 'worker-qa@example.invalid'" );
			$wpdb->delete( flxs_table( 'providers' ), array( 'id' => $f['provider'] ) ); $wpdb->delete( flxs_table( 'templates' ), array( 'id' => $f['template'] ) );
			foreach ( array( 'mail' => flxi_table( 'queue' ), 'cert' => flxs_table( 'messages' ) ) as $name => $table ) { foreach ( $f['old_' . $name] as $r ) { $wpdb->update( $table, array( 'status' => $r['status'] ), array( 'id' => $r['id'] ) ); } }
			foreach ( $f['saved'] as $key => $value ) { false === $value ? delete_option( $key ) : update_option( $key, $value ); }
		}
		@unlink( WP_CONTENT_DIR . '/uploads/flx-background-qa/probe.txt' ); @rmdir( WP_CONTENT_DIR . '/uploads/flx-background-qa' );
		delete_option( 'flx_bg_lock_' . md5( 'qa-race' ) ); delete_option( 'flx_bg_test_fixture' ); delete_option( 'flx_bg_test_deliveries' );
		flx_bg_test_output( array( 'cleaned' => true ) );
		break;
	default: throw new RuntimeException( 'Unknown test operation' );
}
