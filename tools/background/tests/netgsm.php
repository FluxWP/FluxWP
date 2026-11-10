<?php
/** Netgsm contracts with fully intercepted HTTP; never contacts a real gateway. */
$f = get_option( 'flx_bg_test_fixture' ); $f['connections'] = array();
$id = flxi_save_connection( 0, 'netgsm', 'Background SMS QA', array( 'username' => 'qa', 'password' => 'not-a-real-credential', 'header' => 'FLXQA', 'iysfilter' => '11', 'encoding' => 'TR', 'batch_size' => 1000 ), true, false );
$f['connections'][] = $id; update_option( 'flx_bg_test_fixture', $f, false );
$manual = array(); for ( $i = 0; $i < 1000; ++$i ) { $manual[] = sprintf( 'QA %d, +90 555 %07d', $i, $i ); } $manual[] = 'Duplicate, 05550000000'; $manual[] = 'invalid-number';
$recipients = flxi_collect_recipients( array(), implode( "\n", $manual ), 'sms' );
$make = static function ( $name, $list, $date = '' ) use ( $id ) {
	$cid = flxi_create_campaign( array( 'name' => $name, 'channel' => 'sms', 'connection_id' => $id, 'text' => 'Merhaba {ad_soyad}, teşekkürler.', 'send_at' => $date ), $list );
	flx_bg_test_assert( ! is_wp_error( $cid ), 'Cannot create test campaign' );
	$f = get_option( 'flx_bg_test_fixture' ); $f['campaigns'][] = $cid; update_option( 'flx_bg_test_fixture', $f, false ); return $cid;
};
$cid = $make( 'Background SMS QA', $recipients );
$send_calls = 0; $report_calls = 0; $cancel_calls = 0; $mode = 'rate'; $packet = array(); $report_round = 0; $cancel_ok = false; $wa_limited = false;
$reply = static function ( $data ) { return array( 'headers' => array(), 'body' => wp_json_encode( $data ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() ); };
$mock = static function ( $pre, $request, $url ) use ( &$send_calls, &$report_calls, &$cancel_calls, &$mode, &$packet, &$report_round, &$cancel_ok, &$wa_limited, $reply ) {
	if ( str_ends_with( $url, '/sms/rest/v2/send' ) ) {
		++$send_calls; $packet = json_decode( $request['body'], true );
		if ( 'timeout' === $mode ) { return new WP_Error( 'timeout', 'Transport timed out' ); }
		return $reply( 'rate' === $mode ? array( 'code' => '80' ) : ( 'auth' === $mode ? array( 'code' => '30' ) : array( 'code' => '00', 'jobid' => '9223372036854775800' ) ) );
	}
	if ( str_ends_with( $url, '/sms/rest/v2/report' ) ) {
		++$report_calls; $jobs = array();
		for ( $i = 0; $i < ( $report_round ? 1000 : 999 ); ++$i ) { $jobs[] = array( 'jobid' => '9223372036854775800', 'number' => sprintf( '555%07d', $i ), 'status' => $i < 990 || ( $report_round && $i < 995 ) ? 1 : ( $i < 995 ? 0 : 3 ), 'errorCode' => $i >= 995 ? '103' : '0', 'deliveredDate' => '' ); }
		$jobs[] = array( 'jobid' => 'wrong-job', 'number' => '5550000999', 'status' => 1 );
		return $reply( array( 'code' => '00', 'jobs' => $jobs ) );
	}
	if ( str_ends_with( $url, '/sms/rest/v2/cancel' ) ) { ++$cancel_calls; return $reply( array( 'code' => $cancel_ok ? '00' : '30' ) ); }
	if ( str_starts_with( $url, 'https://example.invalid/message/sendText/' ) ) { return $wa_limited ? array( 'headers' => array(), 'body' => '{}', 'response' => array( 'code' => 429 ), 'cookies' => array() ) : $reply( array( 'key' => array( 'id' => 'qa-whatsapp' ) ) ); }
	return new WP_Error( 'qa_offline', 'External HTTP disabled' );
};
add_filter( 'pre_http_request', $mock, PHP_INT_MAX, 3 );
$t = flxi_table( 'queue' );
flxi_run_queue( 8 );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE campaign_id=%d LIMIT 1", $cid ), ARRAY_A );
$backoff = 'queued' === $row['status'] && 1 === (int) $row['attempts'] && strtotime( $row['scheduled_at'] . ' UTC' ) > time();
$mode = 'ok'; $wpdb->update( $t, array( 'scheduled_at' => flxi_now() ), array( 'campaign_id' => $cid ) ); flxi_run_queue( 8 );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE campaign_id=%d LIMIT 1", $cid ), ARRAY_A ); $vars = json_decode( $row['vars'], true ); $token = $vars['netgsm']['batch'];
$batch = flxi_netgsm_batch( $token );
$accepted = flxi_netgsm_counts( $batch )['submitted'] === 1000 && flxi_get_campaign( $cid )['sent'] == 0 && $send_calls === 2 && count( $packet['messages'] ) === 1000;
$payload = 'TR' === $packet['encoding'] && '11' === $packet['iysfilter'] && '5550000999' === $packet['messages'][999]['no'] && str_contains( $packet['messages'][999]['msg'], 'QA 999' );
$due = static function ( $token ) { $batch = flxi_netgsm_batch( $token ); $batch['last_checked'] = 0; flxi_netgsm_batch_save( $batch ); delete_option( 'flxi_netgsm_report_rate_' . $batch['account'] ); flxi_netgsm_registry( $token, time() - 1 ); };
$due( $token ); flxi_netgsm_poll();
$partial = flxi_netgsm_counts( $batch );
flxi_netgsm_poll(); $rate_safe = 1 === $report_calls;
$report_round = 1; $due( $token ); flxi_netgsm_poll(); flxi_run_queue( 8 );
$final = flxi_netgsm_counts( $batch ); $no_resend = 2 === $send_calls;
$not_cancellable = is_wp_error( flxi_netgsm_cancel( $token ) ) && 0 === $cancel_calls;
$mode = 'auth'; $connection = flxi_get_connection( $id ); $error = flxi_netgsm_send_batch( $connection['config'], array( array( 'recipient' => '905550000000', 'body' => 'Test' ) ) );
$mode = 'timeout'; $uncertain_id = $make( 'Uncertain SMS QA', array_slice( $recipients, 0, 1 ) ); flxi_run_queue( 8 ); $before = $send_calls; flxi_run_queue( 8 );
$uncertain = $before === $send_calls && 'review' === flxi_get_campaign( $uncertain_id )['status'];
$mode = 'ok'; $future_time = time() + 3600;
$date = ( new DateTimeImmutable( '@' . $future_time ) )->setTimezone( wp_timezone() )->format( 'Y-m-d\TH:i' );
$future_id = $make( 'Scheduled SMS QA', array_slice( $recipients, 0, 2 ), $date ); flxi_run_queue( 8 );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE campaign_id=%d LIMIT 1", $future_id ), ARRAY_A ); $v = json_decode( $row['vars'], true ); $future = $v['netgsm']['batch'];
$expected = ( new DateTimeImmutable( $date, wp_timezone() ) )->setTimezone( new DateTimeZone( 'Europe/Istanbul' ) )->format( 'dmYHi' );
$schedule = $packet['startdate'] === $expected;
$rejected = is_wp_error( flxi_netgsm_cancel( $future ) ) && 2 === flxi_netgsm_counts( flxi_netgsm_batch( $future ) )['submitted'];
$cancel_ok = true; flxi_netgsm_set_row( $row, 'delivered', array() ); $cancelled = flxi_netgsm_cancel( $future ); $fc = flxi_netgsm_counts( flxi_netgsm_batch( $future ) );
$cancelled = ! is_wp_error( $cancelled ) && 1 === $fc['delivered'] && 1 === $fc['cancelled'];
$local_id = $make( 'Local cancellation QA', array_slice( $recipients, 0, 1 ) ); $before = $send_calls; flxi_cancel_campaign( $local_id ); flxi_run_queue( 8 ); $local_cancel = $before === $send_calls && 'cancelled' === flxi_get_campaign( $local_id )['status'];
// Protect against interrupted submission: a saved job id is resumed as reporting, never resent.
$wpdb->update( $t, array( 'status' => 'processing', 'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() - 700 ) ), array( 'id' => $row['id'] ) ); flxi_netgsm_recover_processing( gmdate( 'Y-m-d H:i:s', time() - 600 ) );
$recovered = 'submitted' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $t WHERE id=%d", $row['id'] ) );
wp_clear_scheduled_hook( 'flxi_netgsm_poll' ); flxi_netgsm_resume(); $resume = (bool) wp_next_scheduled( 'flxi_netgsm_poll' );
$expired_batch = flxi_netgsm_batch( $future ); $expired_batch['created_at'] = time() - 3 * DAY_IN_SECONDS; $expired_batch['send_at'] = 0; flxi_netgsm_batch_save( $expired_batch );
$due( $future ); $wpdb->update( flxi_table( 'connections' ), array( 'active' => 0 ), array( 'id' => $id ) ); flxi_netgsm_poll();
$expiry = flxi_netgsm_counts( flxi_netgsm_batch( $future ) )['unknown'] === 1 && 'review' === flxi_get_campaign( $future_id )['status'];
$wpdb->update( flxi_table( 'connections' ), array( 'active' => 1 ), array( 'id' => $id ) );
// Journal filters out arbitrary fields and even broken diagnostic subscribers cannot stop work.
flx_background_log( 'qa.redaction', array( 'plugin' => 'flx-mail', 'campaign' => $cid, 'job' => 'redaction-test', 'password' => 'SECRET', 'body' => 'PRIVATE', 'recipient' => '90555' ) );
$journal = flx_background_events( 100, $cid ); $redacted = false; foreach ( $journal as $event ) { if ( 'qa.redaction' === $event['event'] ) { $redacted = ! array_intersect_key( $event['context'], array_flip( array( 'password', 'body', 'recipient' ) ) ) && 'redaction-test' === $event['context']['job']; } }
$throw_log = static function () { throw new RuntimeException( 'Diagnostic subscriber failure' ); }; add_action( 'flx_background_logged', $throw_log ); flx_background_log( 'qa.safe' ); remove_action( 'flx_background_logged', $throw_log );
// Read snapshots must not call the gateway; REST routes require management permission.
do_action( 'rest_api_init' ); wp_set_current_user( 0 ); $req = new WP_REST_Request( 'GET', '/flxi/v1/queue' ); $denied = rest_get_server()->dispatch( $req );
$permission = $denied->get_status() >= 400; wp_set_current_user( 1 ); $before = $send_calls + $report_calls; $req->set_param( 'campaign', $cid ); $snapshot = flxi_queue_snapshot( $req ); $read_only = $before === $send_calls + $report_calls && 995 === $snapshot['counts']['delivered'];
$mode = 'rate'; $direct = flxi_send_message( 'sms', '905550000123', 'Private direct test', array( 'connection_id' => $id, 'log' => false ) );
$direct_row = $wpdb->get_row( "SELECT id,status,log_id FROM $t WHERE campaign_id=0 ORDER BY id DESC LIMIT 1", ARRAY_A );
$direct_retry = ! $direct['ok'] && $direct['retry'] && 'failed' === $direct_row['status'] && ! $direct_row['log_id'];
$f = get_option( 'flx_bg_test_fixture' ); $f['direct_rows'][] = $direct_row['id']; update_option( 'flx_bg_test_fixture', $f, false ); $mode = 'ok';
$wa = flxi_save_connection( 0, 'evolution', 'Background WhatsApp QA', array( 'base_url' => 'https://example.invalid', 'api_key' => 'not-a-real-credential', 'instance' => 'qa', 'delay' => 4, 'typing' => 0 ), true, false ); $f = get_option( 'flx_bg_test_fixture' ); $f['connections'][] = $wa; update_option( 'flx_bg_test_fixture', $f, false );
$wc = flxi_create_campaign( array( 'name' => 'WhatsApp QA', 'channel' => 'whatsapp', 'connection_id' => $wa, 'text' => 'QA' ), array_slice( $recipients, 0, 3 ) ); $f['campaigns'][] = $wc; update_option( 'flx_bg_test_fixture', $f, false );
$dates = $wpdb->get_col( $wpdb->prepare( "SELECT scheduled_at FROM $t WHERE campaign_id=%d ORDER BY id", $wc ) ); flxi_run_queue( 8 ); $wa_sent = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE campaign_id=%d AND status='sent'", $wc ) );
$wa_limited = true; $limited = flxi_send_message( 'whatsapp', '905550000000', 'QA', array( 'connection_id' => $wa, 'log' => false ) );
remove_filter( 'pre_http_request', $mock, PHP_INT_MAX );
flx_bg_test_output( array( 'recipients' => count( $recipients ), 'accepted' => $accepted, 'payload' => $payload, 'backoff' => $backoff, 'partial' => $partial, 'final' => $final, 'rate_safe' => $rate_safe, 'no_resend' => $no_resend, 'auth_permanent' => ! $error['ok'] && ! $error['retry'], 'uncertain' => $uncertain, 'schedule' => $schedule, 'cancel_rejected' => $rejected, 'cancelled' => $cancelled, 'immediate_not_cancellable' => $not_cancellable, 'local_cancel' => $local_cancel, 'recovered' => $recovered, 'redacted' => $redacted, 'resume' => $resume, 'expiry' => $expiry, 'direct_retry' => $direct_retry, 'permission' => $permission, 'read_only' => $read_only, 'whatsapp_retry' => ! $limited['ok'] && $limited['retry'], 'whatsapp' => 1 === (int) $wa_sent && strtotime( $dates[1] ) - strtotime( $dates[0] ) === 4 ) );
