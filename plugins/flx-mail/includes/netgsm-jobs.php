<?php
/** Netgsm batching and delivery reconciliation. Existing queue vars hold receipt metadata. */
defined( 'ABSPATH' ) || exit;
FLX_Background_Pool::register( 'flxi_netgsm_poll', 'flx-mail' );
add_action( 'flxi_netgsm_poll', 'flxi_netgsm_poll' );

/** Re-arm durable reports after reactivation or a missing cron event. */
function flxi_netgsm_resume() {
	if ( wp_next_scheduled( 'flxi_netgsm_poll' ) ) { return; }
	$active = get_option( 'flxi_netgsm_active', array() );
	if ( $active ) { flx_background_schedule( 'flxi_netgsm_poll', array(), max( 1, min( $active ) - time() ) ); }
}
add_action( 'init', 'flxi_netgsm_resume', 99 );

function flxi_netgsm_batch_key( $token ) { return 'flxi_netgsm_batch_' . md5( $token ); }
function flxi_netgsm_batch( $token ) { $key = flxi_netgsm_batch_key( $token ); wp_cache_delete( $key, 'options' ); return get_option( $key, array() ); }
function flxi_netgsm_batch_save( $batch ) { update_option( flxi_netgsm_batch_key( $batch['token'] ), $batch, false ); }
function flxi_netgsm_registry( $token, $when = null ) {
	$until = microtime( true ) + 2;
	do { $owner = flx_background_lock( 'netgsm-registry', 10 ); if ( $owner ) { break; } usleep( 5000 ); } while ( microtime( true ) < $until );
	if ( ! $owner ) { return false; }
	try {
		wp_cache_delete( 'flxi_netgsm_active', 'options' ); $active = get_option( 'flxi_netgsm_active', array() );
		if ( null === $when ) { unset( $active[ $token ] ); } else { $active[ $token ] = (int) $when; }
		update_option( 'flxi_netgsm_active', $active, false );
		wp_cache_delete( 'flxi_netgsm_active', 'options' ); $active = get_option( 'flxi_netgsm_active', array() );
		if ( $active ) { flx_background_schedule( 'flxi_netgsm_poll', array(), max( 1, min( $active ) - time() ) ); }
		return true;
	} finally { flx_background_unlock( 'netgsm-registry', $owner ); }
}
function flxi_netgsm_rows( $batch ) {
	global $wpdb;
	$ids = array_filter( array_map( 'absint', $batch['row_ids'] ?? array() ) );
	return $ids ? $wpdb->get_results( 'SELECT * FROM ' . flxi_table( 'queue' ) . ' WHERE id IN (' . implode( ',', $ids ) . ')', ARRAY_A ) : array();
}
function flxi_netgsm_set_row( $row, $status, $receipt, $error = '' ) {
	global $wpdb;
	$vars = json_decode( (string) ( $row['vars'] ?? '' ), true ); $vars = is_array( $vars ) ? $vars : array();
	$vars['netgsm'] = array_merge( (array) ( $vars['netgsm'] ?? array() ), $receipt );
	$data = array( 'status' => $status, 'vars' => flxi_json( $vars ), 'error' => mb_substr( (string) $error, 0, 1000 ) );
	if ( 'delivered' === $status ) { $data['sent_at'] = flxi_now(); }
	$where = $wpdb->prepare( 'id=%d', $row['id'] );
	if ( 'delivered' !== $status ) { $where .= " AND status <> 'delivered'"; }
	if ( 'submitted' === $status ) { $where .= " AND status NOT IN ('failed','cancelled')"; }
	$set = array(); foreach ( $data as $key => $value ) { $set[] = $wpdb->prepare( "$key=%s", $value ); }
	$wpdb->query( 'UPDATE ' . flxi_table( 'queue' ) . ' SET ' . implode( ',', $set ) . ' WHERE ' . $where );
	if ( ! empty( $row['log_id'] ) ) {
		$log = $wpdb->get_row( $wpdb->prepare( 'SELECT status FROM ' . flxi_table( 'log' ) . ' WHERE id=%d', $row['log_id'] ), ARRAY_A );
		if ( $log && ( 'delivered' !== $log['status'] || 'delivered' === $status ) && ( 'submitted' !== $status || ! in_array( $log['status'], array( 'failed', 'cancelled' ), true ) ) ) { flxi_log_update( $row['log_id'], array( 'status' => $status, 'error' => $data['error'] ) ); }
	}
}
function flxi_netgsm_counts( $batch ) {
	$out = array( 'submitted' => 0, 'delivered' => 0, 'failed' => 0, 'cancelled' => 0, 'unknown' => 0 );
	foreach ( flxi_netgsm_rows( $batch ) as $row ) { if ( isset( $out[ $row['status'] ] ) ) { ++$out[ $row['status'] ]; } }
	return $out;
}
function flxi_netgsm_log( $event, $batch, $extra = array() ) {
	flx_background_log( $event, array_merge( array( 'plugin' => 'flx-mail', 'campaign' => (int) $batch['campaign'], 'batch' => $batch['token'], 'job' => $batch['jobid'] ?? '', 'count' => count( $batch['row_ids'] ?? array() ) ), $extra ) );
}

/** Claim and submit one provider packet; an uncertain acceptance is never resent automatically. */
function flxi_netgsm_dispatch( $config, $messages, $connection, $campaign = array() ) {
	global $wpdb;
	$token = wp_generate_uuid4(); $key = flxi_netgsm_batch_key( $token );
	$batch = array( 'token' => $token, 'campaign' => (int) ( $campaign['id'] ?? ( $messages[0]['campaign_id'] ?? 0 ) ), 'connection' => (int) $connection['id'], 'account' => hash( 'sha256', (string) $config['username'] ), 'created_at' => time(), 'send_at' => (int) ( $campaign['data']['send_at'] ?? 0 ), 'state' => 'prepared', 'jobid' => '', 'row_ids' => array(), 'last_checked' => 0, 'page' => 1, 'seen' => array() );
	add_option( $key, $batch, '', false );
	if ( ! flxi_netgsm_registry( $token, time() + 600 ) ) { delete_option( $key ); return array( 'ok' => false, 'processed' => 0, 'retry' => true, 'error' => __( 'The queue is busy. Try again shortly.', 'flx-mail' ) ); }
	$owner = flx_background_lock( 'netgsm-batch-' . $token, 180 );
	if ( ! $owner ) { flxi_netgsm_registry( $token ); delete_option( $key ); return array( 'ok' => false, 'processed' => 0, 'retry' => true ); }
	$rows = array(); $packet = array(); $seen = array(); $skipped = 0; $invalid = 0;
	try {
		foreach ( $messages as $message ) {
			$phone = flxi_normalize_phone( $message['recipient'] );
			$blocked = $phone && flxi_contact_blocked( $phone, '', 'sms' );
			$duplicate = $phone && isset( $seen[ $phone ] );
			if ( ! $phone || $blocked || $duplicate ) {
				$error = $blocked ? __( 'On the blocklist: not sent', 'flx-mail' ) : ( $duplicate ? __( 'Duplicate recipient', 'flx-mail' ) : sprintf( __( 'Invalid phone: %s', 'flx-mail' ), $message['recipient'] ) );
				if ( ! empty( $message['id'] ) ) { $wpdb->update( flxi_table( 'queue' ), array( 'status' => $blocked || $duplicate ? 'skipped' : 'failed', 'error' => $error ), array( 'id' => $message['id'], 'status' => 'queued' ) ); if ( $blocked || $duplicate ) { ++$skipped; } else { ++$invalid; } }
				continue;
			}
			$seen[ $phone ] = true;
			$vars = json_decode( (string) ( $message['vars'] ?? '' ), true ); $vars = is_array( $vars ) ? $vars : array(); $vars['netgsm'] = array( 'batch' => $token, 'submitted_at' => 0 );
			if ( ! empty( $message['id'] ) ) {
				$claimed = $wpdb->query( $wpdb->prepare( 'UPDATE ' . flxi_table( 'queue' ) . " SET status='processing',attempts=attempts+1,scheduled_at=%s,vars=%s WHERE id=%d AND status='queued'", flxi_now(), flxi_json( $vars ), $message['id'] ) );
				if ( 1 !== (int) $claimed ) { continue; }
			} else {
				$wpdb->insert( flxi_table( 'queue' ), array( 'campaign_id' => $batch['campaign'], 'channel' => 'sms', 'recipient' => $phone, 'name' => '', 'status' => 'processing', 'attempts' => 1, 'scheduled_at' => flxi_now(), 'vars' => flxi_json( $vars ) ) ); $message['id'] = (int) $wpdb->insert_id;
			}
			if ( empty( $message['id'] ) ) { continue; }
			$message['recipient'] = $phone; $message['vars'] = flxi_json( $vars ); $message['attempts'] = (int) ( $message['attempts'] ?? 0 ) + 1;
			$rows[] = $message; $packet[] = array( 'recipient' => $phone, 'body' => (string) $message['body'] ); $batch['row_ids'][] = (int) $message['id'];
		}
		if ( ! $rows ) { $batch['state'] = 'cancelled'; flxi_netgsm_batch_save( $batch ); flxi_netgsm_registry( $token ); return array( 'ok' => false, 'processed' => $skipped + $invalid, 'failed' => $invalid, 'skipped' => $skipped, 'error' => __( 'There are no recipients to send to.', 'flx-mail' ), 'retry' => false, 'logged' => true ); }
		$current = $batch['campaign'] ? flxi_get_campaign( $batch['campaign'] ) : null;
		if ( $current && ( 'cancelled' === $current['status'] || ! empty( $current['data']['stopped'] ) ) ) {
			foreach ( $rows as $row ) { flxi_netgsm_set_row( $row, 'cancelled', array( 'batch' => $token ) ); }
			$batch['state'] = 'cancelled'; flxi_netgsm_batch_save( $batch ); flxi_netgsm_registry( $token ); return array( 'ok' => false, 'processed' => count( $rows ), 'retry' => false, 'logged' => true );
		}
		$batch['state'] = 'sending'; flxi_netgsm_batch_save( $batch ); flxi_netgsm_log( 'sms.batch.sending', $batch );
		$result = flxi_netgsm_send_batch( $config, $packet, array( 'reference' => $token, 'send_at' => $batch['send_at'] ) );
		$batch['jobid'] = (string) ( $result['external_id'] ?? '' );
		$batch['state'] = ! empty( $result['ok'] ) ? 'accepted' : ( ! empty( $result['uncertain'] ) ? 'unknown' : 'rejected' );
		$batch['code'] = (string) ( $result['code'] ?? '' ); $batch['last_error'] = $result['error'] ?? '';
		if ( '00' !== $batch['code'] ) { $batch['send_at'] = 0; }
		// Persist the job id before updating recipients, so an interrupted update can recover safely.
		flxi_netgsm_batch_save( $batch );
		$failed = $invalid; $submitted = 0; $log_id = 0;
		foreach ( $rows as $row ) {
			$status = ! empty( $result['ok'] ) ? 'submitted' : ( ! empty( $result['uncertain'] ) ? 'unknown' : ( ! empty( $result['retry'] ) && $batch['campaign'] && $row['attempts'] < 3 ? 'queued' : 'failed' ) );
			$log_id = isset( $row['log'] ) && ! $row['log'] ? 0 : flxi_log_message( 'sms', $row['recipient'], $row['body'], ! empty( $result['ok'] ), $result['error'] ?? '', array( 'status' => $status, 'source' => $row['source'] ?? 'flx-mail', 'template' => $batch['campaign'] ? 'flx-mail/campaign' : 'netgsm/direct', 'campaign_id' => $batch['campaign'], 'mailer' => 'netgsm' ) );
			$wpdb->update( flxi_table( 'queue' ), array( 'log_id' => $log_id ), array( 'id' => $row['id'] ) ); $row['log_id'] = $log_id;
			flxi_netgsm_set_row( $row, $status, array( 'batch' => $token, 'jobid' => $batch['jobid'], 'submitted_at' => ! empty( $result['ok'] ) ? time() : 0, 'send_at' => $batch['send_at'] ), $result['error'] ?? '' );
			if ( 'queued' === $status ) { $wpdb->update( flxi_table( 'queue' ), array( 'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() + 120 ) ), array( 'id' => $row['id'] ) ); }
			if ( 'failed' === $status ) { ++$failed; } if ( 'submitted' === $status ) { ++$submitted; }
		}
		flxi_netgsm_log( 'sms.batch.' . $batch['state'], $batch, array( 'code' => $batch['code'] ) );
		if ( $submitted ) { flxi_netgsm_registry( $token, max( time() + 60, $batch['send_at'] + 60 ) ); }
		else { flxi_netgsm_registry( $token ); }
		if ( $batch['campaign'] ) { flxi_refresh_campaign( $batch['campaign'] ); }
		return array_merge( $result, array( 'processed' => count( $rows ) + $skipped + $invalid, 'submitted' => $submitted, 'failed' => $failed, 'skipped' => $skipped, 'sent' => 0, 'logged' => true, 'log_id' => $log_id, 'delivery_status' => $batch['state'], 'batch' => $token ) );
	} finally { flx_background_unlock( 'netgsm-batch-' . $token, $owner ); }
}

function flxi_netgsm_queue_batch( $first, $campaign ) {
	global $wpdb;
	$connection = flxi_resolve_connection( 'sms', (int) $campaign['data']['connection_id'] );
	if ( ! $connection || 'netgsm' !== $connection['type'] ) { return null; }
	$size = max( 1, min( 50000, (int) ( $connection['config']['batch_size'] ?? 1000 ) ) );
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxi_table( 'queue' ) . " WHERE campaign_id=%d AND status='queued' AND scheduled_at<=%s ORDER BY id LIMIT %d", $campaign['id'], flxi_now(), $size ), ARRAY_A );
	foreach ( $rows as &$row ) { $row['body'] = flxi_fill( flxi_spin( (string) $campaign['data']['text'] ), array_merge( flxi_row_vars( $row ), array( 'ad' => preg_split( '/\s+/u', trim( $row['name'] ) )[0], 'ad_soyad' => $row['name'], 'telefon' => $row['recipient'] ) ) ); } unset( $row );
	// Tanıtım SMS'i: IPTAL yazmış kişi atlanır, çıkış satırı (açıksa) eklenir.
	if ( 'marketing' === flxi_message_kind( $campaign['data']['kind'] ?? 'marketing' ) ) {
		$keep = array();
		foreach ( $rows as $row ) {
			if ( flxi_optout_has( 'sms', $row['recipient'] ) ) {
				$wpdb->update( flxi_table( 'queue' ), array( 'status' => 'skipped', 'error' => __( 'Opted out of promotional messages (replied STOP): not sent', 'flx-mail' ) ), array( 'id' => (int) $row['id'] ) );
				continue;
			}
			$row['body'] = flxi_optout_append( $row['body'], 'sms' );
			$keep[]      = $row;
		}
		if ( ! $keep ) {
			flxi_refresh_campaign( (int) $campaign['id'] );
			return array( 'processed' => count( $rows ), 'sent' => 0, 'failed' => 0, 'skipped' => count( $rows ) );
		}
		$rows = $keep;
	}
	return flxi_netgsm_dispatch( $connection['config'], $rows, $connection, $campaign );
}

function flxi_netgsm_recover_processing( $cutoff ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxi_table( 'queue' ) . " WHERE status='processing' AND scheduled_at<=%s AND vars IS NOT NULL", $cutoff ), ARRAY_A );
	foreach ( $rows as $row ) {
		$vars = json_decode( (string) $row['vars'], true ); $token = $vars['netgsm']['batch'] ?? '';
		if ( ! $token ) { continue; } $batch = flxi_netgsm_batch( $token );
		if ( ! empty( $batch['jobid'] ) ) { flxi_netgsm_set_row( $row, 'submitted', array( 'jobid' => $batch['jobid'] ) ); flxi_netgsm_registry( $token, time() + 1 ); }
		elseif ( $batch && 'prepared' === $batch['state'] ) { flxi_netgsm_set_row( $row, $row['attempts'] >= 3 ? 'failed' : 'queued', array() ); }
		else { flxi_netgsm_set_row( $row, 'unknown', array(), __( 'Delivery acceptance is unknown. Check Netgsm before sending again.', 'flx-mail' ) ); }
	}
}

function flxi_netgsm_queue_report( $token ) {
	$batch = flxi_netgsm_batch( $token ); if ( ! $batch || ! $batch['jobid'] || ! flxi_netgsm_counts( $batch )['submitted'] ) { return false; }
	flxi_netgsm_registry( $token, max( time() + 1, (int) $batch['last_checked'] + 60 ) ); return true;
}

function flxi_netgsm_poll() {
	return flx_background_run( 'netgsm-reports', static function () {
		wp_cache_delete( 'flxi_netgsm_active', 'options' ); $active = get_option( 'flxi_netgsm_active', array() ); asort( $active );
		foreach ( $active as $token => $due ) {
			if ( $due > time() ) { break; }
			$batch = flxi_netgsm_batch( $token );
			if ( ! $batch ) { flxi_netgsm_registry( $token ); continue; }
			if ( ! $batch['jobid'] ) { flxi_netgsm_recover_processing( gmdate( 'Y-m-d H:i:s', time() - 600 ) ); flxi_netgsm_registry( $token ); continue; }
			$owner = flx_background_lock( 'netgsm-batch-' . $token, 90 );
			if ( ! $owner ) { flxi_netgsm_registry( $token, time() + 30 ); continue; }
			try {
				$batch = flxi_netgsm_batch( $token );
				if ( time() > max( $batch['created_at'], $batch['send_at'] ) + 2 * DAY_IN_SECONDS ) {
					foreach ( flxi_netgsm_rows( $batch ) as $row ) { if ( in_array( $row['status'], array( 'submitted', 'processing' ), true ) ) { flxi_netgsm_set_row( $row, 'unknown', array(), __( 'No final delivery report received. Check Netgsm.', 'flx-mail' ) ); } }
					$batch['state'] = 'review'; flxi_netgsm_batch_save( $batch ); flxi_netgsm_registry( $token );
					flxi_netgsm_log( 'sms.report.expired', $batch ); if ( $batch['campaign'] ) { flxi_refresh_campaign( $batch['campaign'] ); }
					continue;
				}
				$connection = flxi_get_connection( $batch['connection'] );
				if ( ! $connection || empty( $connection['active'] ) || hash( 'sha256', (string) $connection['config']['username'] ) !== $batch['account'] ) {
					$batch['last_error'] = __( 'The original Netgsm connection is unavailable.', 'flx-mail' ); flxi_netgsm_batch_save( $batch ); flxi_netgsm_registry( $token, time() + 300 ); continue;
				}
				$rate_key = 'flxi_netgsm_report_rate_' . $batch['account']; wp_cache_delete( $rate_key, 'options' );
				$allowed = max( (int) $batch['last_checked'] + 60, (int) get_option( $rate_key ) + 2 );
				if ( $allowed > time() ) { flxi_netgsm_registry( $token, $allowed ); continue; }
				$batch['last_checked'] = time(); update_option( $rate_key, time(), false ); flxi_netgsm_batch_save( $batch );
				$r = flxi_netgsm_request( $connection['config'], 'report', array( 'jobids' => array( $batch['jobid'] ), 'pagesize' => min( 1000, max( 1, count( $batch['row_ids'] ) ) ), 'pagenumber' => (int) $batch['page'] ) );
				$json = is_array( $r['json'] ) ? $r['json'] : array(); $code = (string) ( $json['code'] ?? $r['code'] );
				$rows = flxi_netgsm_rows( $batch );
				foreach ( $rows as &$row ) { if ( 'processing' === $row['status'] ) { flxi_netgsm_set_row( $row, 'submitted', array( 'jobid' => $batch['jobid'] ) ); $row['status'] = 'submitted'; } } unset( $row );
				$by_phone = array(); foreach ( $rows as $row ) { $by_phone[ flxi_normalize_phone( $row['recipient'] ) ] = $row; }
				if ( '00' === $code && is_array( $json['jobs'] ?? null ) ) {
					foreach ( $json['jobs'] as $receipt ) {
						if ( (string) ( $receipt['jobid'] ?? '' ) !== $batch['jobid'] ) { continue; }
						$phone = flxi_normalize_phone( $receipt['number'] ?? '' ); if ( ! isset( $by_phone[ $phone ] ) ) { continue; }
						$row = $by_phone[ $phone ]; $value = (int) ( $receipt['status'] ?? -1 );
						$status = 1 === $value ? 'delivered' : ( in_array( $value, array( 2, 3, 4, 11, 12, 13, 14, 15, 16, 17, 22 ), true ) ? 'failed' : 'submitted' );
						flxi_netgsm_set_row( $row, $status, array( 'provider_status' => $value, 'provider_error' => (string) ( $receipt['errorCode'] ?? '' ), 'checked_at' => time(), 'delivered_date' => sanitize_text_field( $receipt['deliveredDate'] ?? '' ) ), 'failed' === $status ? 'Netgsm ' . $value . ' / ' . ( $receipt['errorCode'] ?? '' ) : '' );
						$batch['seen'][ $row['id'] ] = true;
					}
					$batch['page'] = ! $json['jobs'] || count( $batch['seen'] ) >= count( $batch['row_ids'] ) ? 1 : $batch['page'] + 1;
					if ( 1 === $batch['page'] ) { $batch['seen'] = array(); } $batch['last_error'] = '';
				} elseif ( '60' === $code ) { $batch['page'] = 1; $batch['seen'] = array(); }
				else { $batch['last_error'] = 'Netgsm ' . $code; }
				$counts = flxi_netgsm_counts( $batch );
				if ( $counts['submitted'] && time() > max( $batch['created_at'], $batch['send_at'] ) + 2 * DAY_IN_SECONDS ) {
					foreach ( flxi_netgsm_rows( $batch ) as $row ) { if ( 'submitted' === $row['status'] ) { flxi_netgsm_set_row( $row, 'unknown', array(), __( 'No final delivery report received. Check Netgsm.', 'flx-mail' ) ); } }
					$counts = flxi_netgsm_counts( $batch );
				}
				$batch['state'] = $counts['submitted'] ? 'reporting' : ( $counts['unknown'] ? 'review' : 'finished' ); flxi_netgsm_batch_save( $batch );
				flxi_netgsm_registry( $token, $counts['submitted'] ? time() + ( $batch['last_error'] ? 300 : 60 ) : null );
				flxi_netgsm_log( 'sms.report.received', $batch, array( 'code' => $code, 'page' => $batch['page'], 'delivered' => $counts['delivered'], 'failed' => $counts['failed'], 'pending' => $counts['submitted'] ) );
				if ( $batch['campaign'] ) { flxi_refresh_campaign( $batch['campaign'] ); }
				return array( 'processed' => 1 );
			} finally { flx_background_unlock( 'netgsm-batch-' . $token, $owner ); }
		}
		wp_cache_delete( 'flxi_netgsm_active', 'options' ); $active = get_option( 'flxi_netgsm_active', array() );
		if ( $active ) { flx_background_schedule( 'flxi_netgsm_poll', array(), max( 1, min( $active ) - time() ) ); }
		return array( 'processed' => 0 );
	}, array( 'hook' => 'flxi_netgsm_poll', 'ttl' => 90 ) );
}

/** Netgsm documents cancellation only for future scheduled messages. */
function flxi_netgsm_cancel( $token ) {
	$batch = flxi_netgsm_batch( $token );
	if ( ! $batch || ! $batch['jobid'] || $batch['send_at'] <= time() + 10 || ! flxi_netgsm_counts( $batch )['submitted'] ) { return new WP_Error( 'flxi_cancel_window', __( 'Netgsm only supports cancellation of future scheduled sends. Delivered messages cannot be recalled.', 'flx-mail' ), array( 'status' => 409 ) ); }
	$owner = flx_background_lock( 'netgsm-batch-' . $token, 90 ); if ( ! $owner ) { return new WP_Error( 'flxi_busy', __( 'The queue is busy. Try again shortly.', 'flx-mail' ), array( 'status' => 409 ) ); }
	try {
		$batch = flxi_netgsm_batch( $token );
		if ( ! $batch || $batch['send_at'] <= time() + 10 || ! flxi_netgsm_counts( $batch )['submitted'] ) { return new WP_Error( 'flxi_cancel_window', __( 'The cancellation window has closed.', 'flx-mail' ), array( 'status' => 409 ) ); }
		$connection = flxi_get_connection( $batch['connection'] ); if ( ! $connection || empty( $connection['active'] ) || hash( 'sha256', (string) $connection['config']['username'] ) !== $batch['account'] ) { return new WP_Error( 'flxi_connection', __( 'The original Netgsm connection is unavailable.', 'flx-mail' ) ); }
		flxi_netgsm_log( 'sms.cancel.requested', $batch );
		$r = flxi_netgsm_request( $connection['config'], 'cancel', array( 'jobid' => $batch['jobid'] ) ); $code = (string) ( $r['json']['code'] ?? $r['code'] );
		if ( '00' !== $code ) { flxi_netgsm_log( 'sms.cancel.rejected', $batch, array( 'code' => $code ) ); return new WP_Error( 'flxi_cancel_rejected', 'Netgsm ' . $code, array( 'status' => 409 ) ); }
		foreach ( flxi_netgsm_rows( $batch ) as $row ) { if ( 'submitted' === $row['status'] ) { flxi_netgsm_set_row( $row, 'cancelled', array( 'cancelled_at' => time() ) ); } }
		$batch['state'] = 'cancelled'; $batch['cancelled_at'] = time(); flxi_netgsm_batch_save( $batch ); flxi_netgsm_registry( $token );
		flxi_netgsm_log( 'sms.cancel.confirmed', $batch, array( 'code' => $code ) ); if ( $batch['campaign'] ) { flxi_refresh_campaign( $batch['campaign'] ); }
		return array( 'ok' => true );
	} finally { flx_background_unlock( 'netgsm-batch-' . $token, $owner ); }
}

/** Keep completed provider metadata for the configured delivery-log retention period. */
add_action( 'flxi_daily', static function () {
	global $wpdb;
	$cutoff = time() - max( 2, (int) flxi_setting( 'log_days' ) ) * DAY_IN_SECONDS;
	$keys = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id LIMIT 2000", $wpdb->esc_like( 'flxi_netgsm_batch_' ) . '%' ) );
	foreach ( $keys as $key ) {
		$batch = get_option( $key );
		if ( is_array( $batch ) && $batch['created_at'] < $cutoff && ! in_array( $batch['state'], array( 'prepared', 'sending', 'accepted', 'reporting' ), true ) ) { delete_option( $key ); }
	}
} );
