<?php
/** Real disposable REST/database audit; no AI service is called. */
if ( ! defined( 'FLUX_NATIVE_QA' ) || ! FLUX_NATIVE_QA || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Isolated local QA required.' ); }
function ailog_assert( $yes, $message ) { if ( ! $yes ) { throw new RuntimeException( $message ); } }
function ailog_request( $params = array(), $id = 0 ) { $r = new WP_REST_Request( 'GET', $id ? '/flx-ai/v1/log/' . $id : '/flx-ai/v1/log' ); $r->set_query_params( $params ); return rest_do_request( $r ); }
global $wpdb;
$original_user = get_current_user_id(); $original_settings = get_option( 'flxai_settings', null ); $subscriber = 0; $ids = array(); $http = 0;
$source = 'qa-log-' . bin2hex( random_bytes( 4 ) ); $log = flxai_table( 'log' ); $bdy = flxai_table( 'log_body' );
$network = static function () use ( &$http ) { ++$http; return new WP_Error( 'qa_no_network', 'Network disabled.' ); }; add_filter( 'pre_http_request', $network, 1 );
try {
	$subscriber = wp_insert_user( array( 'user_login' => $source, 'user_pass' => wp_generate_password( 32 ), 'role' => 'subscriber', 'display_name' => 'QA <actor> İpek' ) ); ailog_assert( ! is_wp_error( $subscriber ), 'Fixture user failed.' );
	foreach ( array( 0, $subscriber ) as $uid ) { wp_set_current_user( $uid ); foreach ( array( 0, 9999999 ) as $id ) { ailog_assert( in_array( ailog_request( array(), $id )->get_status(), array( 401,403 ), true ), 'Unauthorized log access allowed.' ); } }
	wp_set_current_user( get_user_by( 'login', 'fluxqa' )->ID );
	$now = time();
	for ( $i = 0; $i < 62; ++$i ) {
		$wpdb->insert( $log, array( 'ts' => $now - $i * 20000, 'source' => $source, 'purpose' => 'qa-purpose', 'kind' => $i % 3 ? 'model' : 'tool', 'user_id' => $i ? 0 : $subscriber, 'chat_id' => 15, 'provider' => 'qa', 'model' => 0 === $i ? 'qa/model%_quoted' : 'qa/model', 'tool' => $i % 3 ? '' : 'qa_tool', 'in_tok' => 1234, 'out_tok' => 567, 'cache_read' => 400, 'cache_write' => 50, 'cost' => .01234567, 'est' => 1, 'ms' => 1500, 'ok' => $i % 5 ? 1 : 0, 'error' => $i % 5 ? '' : 'limit' ) );
		ailog_assert( $wpdb->insert_id > 0, 'Fixture log failed.' ); $ids[] = (int) $wpdb->insert_id;
	}
	flxai_update_settings( array( 'log_bodies' => 1 ) );
	flxai_log_body( $ids[0], array( array( 'role' => 'user', 'content' => 'BODY-MARKER contact qa-log-secret@example.test ' . str_repeat( 'x', 2500 ) ) ), array( 'content' => '<script>not executable</script> RESPONSE-MARKER', 'tool_calls' => array( array( 'function' => array( 'name' => 'qa_tool', 'arguments' => '{"email":"qa-log-secret@example.test"}' ) ) ) ) );
	$base = array( 'source' => $source, 'per_page' => 25 ); $response = ailog_request( $base ); $data = $response->get_data();
	ailog_assert( 200 === $response->get_status() && 62 === $data['total'] && 3 === $data['pages'] && 25 === count( $data['items'] ) && true === $data['details_available'], 'Default all-time paging failed.' );
	ailog_assert( $ids[61] === $data['items'][0]['id'] && $ids[37] === $data['items'][24]['id'], 'Stable descending ordering failed.' );
	$last = ailog_request( $base + array( 'page' => 3 ) )->get_data(); ailog_assert( 12 === count( $last['items'] ) && $ids[0] === $last['items'][11]['id'], 'Last page failed.' );
	$row = $last['items'][11]; ailog_assert( 1234 === $row['in_tok'] && 567 === $row['out_tok'] && 400 === $row['cache_read'] && 50 === $row['cache_write'] && 15 === $row['chat_id'] && .01234567 === $row['cost'] && true === $row['est'] && true === $row['has_body'] && get_userdata( $subscriber )->display_name === $row['user_label'], 'Native metadata lost tokens, cost, actor or body flag.' );
	ailog_assert( false === strpos( wp_json_encode( $last ), 'BODY-MARKER' ) && false === strpos( wp_json_encode( $last ), 'RESPONSE-MARKER' ) && ! array_key_exists( 'req', $row ) && ! array_key_exists( 'res', $row ), 'List exposed request/response content.' );
	ailog_assert( in_array( $source, array_column( $data['sources'], 'id' ), true ), 'Historical/unregistered source omitted.' );
	$recent = ailog_request( $base + array( 'days' => 1 ) )->get_data(); ailog_assert( 5 === $recent['total'], '24-hour timestamp filter failed.' );
	foreach ( $recent['items'] as $r ) { ailog_assert( $r['ts'] >= $now - DAY_IN_SECONDS && ( '' === $r['user_label'] || $r['user_id'] === $subscriber ), 'Actor/time result incorrect.' ); }
	$filtered = ailog_request( $base + array( 'days' => 7, 'kind' => 'tool', 'errors' => true ) )->get_data(); ailog_assert( 3 === $filtered['total'], 'Combined period/type/error filter failed.' );
	$literal = ailog_request( $base + array( 'q' => '%_' ) )->get_data(); ailog_assert( 1 === $literal['total'] && $ids[0] === $literal['items'][0]['id'], 'LIKE wildcards were not escaped.' );
	ailog_assert( 62 === ailog_request( $base + array( 'q' => 'qa-purpose' ) )->get_data()['total'] && 0 === ailog_request( $base + array( 'q' => "' OR 1=1 --" ) )->get_data()['total'], 'Purpose search or SQL isolation failed.' );
	foreach ( array( array( 'days' => 2 ), array( 'kind' => 'invalid' ), array( 'errors' => 'invalid' ) ) as $bad ) { ailog_assert( 400 === ailog_request( $bad )->get_status(), 'Invalid filter accepted.' ); }
	$body = ailog_request( array(), $ids[0] ); $encoded = wp_json_encode( $body->get_data() );
	ailog_assert( 200 === $body->get_status() && false !== strpos( $encoded, 'BODY-MARKER' ) && false !== strpos( $encoded, 'RESPONSE-MARKER' ) && false === strpos( $encoded, 'qa-log-secret@example.test' ) && mb_strlen( $body->get_data()['req'][0][1] ) < 2100, 'Saved body was not masked/clipped or lost response.' );
	ailog_assert( 404 === ailog_request( array(), $ids[1] )->get_status(), 'Missing content was not 404.' );
	$server = rest_get_server(); $headers = flxai_app_nocache( $body, $server, new WP_REST_Request( 'GET', '/flx-ai/v1/log/' . $ids[0] ) )->get_headers(); ailog_assert( false !== strpos( $headers['Cache-Control'], 'no-store' ), 'Body can be HTTP cached.' );
	$wpdb->delete( $bdy, array( 'log_id' => $ids[0] ) ); ailog_assert( 404 === ailog_request( array(), $ids[0] )->get_status() && false === ailog_request( $base + array( 'q' => '%_' ) )->get_data()['items'][0]['has_body'], 'Expired body flag/content stale.' );
	ailog_assert( 0 === $http, 'Log browsing called an external service.' );
	echo "PASS: native AI log permissions, 62-row paging, historical sources, timestamp/type/error/search filters, escaped wildcards, token/cache/cost/actor metadata, masked/clipped body isolation and expiration.\n";
} finally {
	foreach ( $ids as $id ) { $wpdb->delete( $bdy, array( 'log_id' => $id ) ); $wpdb->delete( $log, array( 'id' => $id ) ); }
	if ( null === $original_settings ) { delete_option( 'flxai_settings' ); } else { update_option( 'flxai_settings', $original_settings ); } $GLOBALS['flxai_settings_cache'] = null;
	wp_set_current_user( $original_user ); remove_filter( 'pre_http_request', $network, 1 );
	if ( $subscriber && ! is_wp_error( $subscriber ) ) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $subscriber ); }
}
