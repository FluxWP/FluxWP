<?php
/** Read-only queue snapshots and explicit administrative actions. */
defined( 'ABSPATH' ) || exit;

function flxi_queue_labels() {
	return array( 'queued' => __( 'Queued', 'flx-mail' ), 'processing' => __( 'Processing', 'flx-mail' ), 'submitted' => __( 'Accepted; awaiting delivery report', 'flx-mail' ), 'delivered' => __( 'Delivered', 'flx-mail' ), 'sent' => __( 'Sent', 'flx-mail' ), 'failed' => __( 'Failed', 'flx-mail' ), 'skipped' => __( 'Skipped', 'flx-mail' ), 'cancelled' => __( 'Cancelled', 'flx-mail' ), 'unknown' => __( 'Needs review', 'flx-mail' ), 'reporting' => __( 'Awaiting delivery reports', 'flx-mail' ), 'review' => __( 'Needs review', 'flx-mail' ), 'running' => __( 'Sending', 'flx-mail' ), 'done' => __( 'Done', 'flx-mail' ) );
}

add_action( 'rest_api_init', static function () {
	$permission = static function () { return flxi_can_manage(); };
	register_rest_route( 'flxi/v1', '/queue', array( 'methods' => 'GET', 'callback' => 'flxi_queue_snapshot', 'permission_callback' => $permission ) );
	register_rest_route( 'flxi/v1', '/queue/action', array( 'methods' => 'POST', 'callback' => 'flxi_queue_action', 'permission_callback' => $permission ) );
} );

function flxi_queue_snapshot( WP_REST_Request $request ) {
	global $wpdb;
	$cid = absint( $request['campaign'] ); $page = max( 1, absint( $request['page'] ) );
	$camps = $wpdb->get_results( 'SELECT id,name,channel,status,total,sent,failed,skipped,created_at FROM ' . flxi_table( 'campaigns' ) . ' ORDER BY id DESC LIMIT 30', ARRAY_A );
	foreach ( $camps as &$camp ) { $camp['date'] = flx_date( $camp['created_at'], 'datetime' ); } unset( $camp );
	$campaign = $cid ? flxi_get_campaign( $cid ) : null;
	if ( $cid && ! $campaign ) { return new WP_Error( 'flxi_not_found', __( 'Campaign not found.', 'flx-mail' ), array( 'status' => 404 ) ); }
	$where = $cid ? $wpdb->prepare( ' WHERE campaign_id=%d', $cid ) : '';
	$counts = $wpdb->get_results( 'SELECT status,COUNT(*) n FROM ' . flxi_table( 'queue' ) . $where . ' GROUP BY status', ARRAY_A );
	$by = array(); foreach ( $counts as $count ) { $by[ $count['status'] ] = (int) $count['n']; }
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id,recipient,channel,status,attempts,error,vars,scheduled_at,sent_at FROM ' . flxi_table( 'queue' ) . $where . ' ORDER BY id DESC LIMIT 50 OFFSET %d', ( $page - 1 ) * 50 ), ARRAY_A );
	$batches = array();
	foreach ( $rows as &$row ) {
		$vars = json_decode( (string) $row['vars'], true ); $token = $vars['netgsm']['batch'] ?? '';
		$row['jobid'] = (string) ( $vars['netgsm']['jobid'] ?? '' ); unset( $row['vars'] );
		$row['date'] = flx_date( $row['sent_at'] ?: $row['scheduled_at'], 'datetime' );
		if ( $token && ! isset( $batches[ $token ] ) ) {
			$batch = flxi_netgsm_batch( $token );
			if ( $batch ) { $batches[ $token ] = array( 'token' => $token, 'jobid' => $batch['jobid'], 'state' => $batch['state'], 'counts' => flxi_netgsm_counts( $batch ), 'can_cancel' => $batch['send_at'] > time() + 10 && flxi_netgsm_counts( $batch )['submitted'] > 0, 'checked' => $batch['last_checked'] ? flx_date( $batch['last_checked'], 'datetime' ) : '', 'error' => $batch['last_error'] ?? '' ); }
		}
	} unset( $row );
	$events = flx_background_events( 50, $cid ); foreach ( $events as &$event ) { $event['date'] = flx_date( $event['time'], 'datetime' ); } unset( $event );
	return array( 'campaigns' => $camps, 'campaign' => $campaign ? array_intersect_key( $campaign, array_flip( array( 'id', 'name', 'status' ) ) ) : null, 'counts' => $by, 'rows' => $rows, 'page' => $page, 'pages' => max( 1, (int) ceil( array_sum( $by ) / 50 ) ), 'batches' => array_values( $batches ), 'events' => $events, 'worker' => flx_background_worker_active() );
}

function flxi_queue_action( WP_REST_Request $request ) {
	$action = sanitize_key( (string) $request['action'] );
	if ( 'stop' === $action ) {
		$id = absint( $request['campaign'] ); if ( ! flxi_get_campaign( $id ) ) { return new WP_Error( 'flxi_not_found', __( 'Campaign not found.', 'flx-mail' ), array( 'status' => 404 ) ); }
		flxi_cancel_campaign( $id ); return array( 'ok' => true );
	}
	$token = sanitize_text_field( (string) $request['batch'] );
	if ( ! preg_match( '/^[a-f0-9-]{36}$/', $token ) ) { return new WP_Error( 'flxi_batch', __( 'Batch not found.', 'flx-mail' ), array( 'status' => 404 ) ); }
	if ( 'cancel' === $action ) { return flxi_netgsm_cancel( $token ); }
	if ( 'report' === $action && flxi_netgsm_queue_report( $token ) ) { return array( 'ok' => true ); }
	return new WP_Error( 'flxi_action', __( 'Action unavailable.', 'flx-mail' ), array( 'status' => 409 ) );
}
