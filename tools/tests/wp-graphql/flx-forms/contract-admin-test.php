<?php
/**
 * Flux Forms panel contract: responses and settings on GraphQL, in both directions with the classic
 * panel AJAX handlers and the FluxApp REST routes. Included by contract-test.php (helpers and the
 * persisted operations are already loaded).
 */

echo "Flux Forms panel (responses and settings)\n";

add_filter(
	'flxf_gql_rate_max',
	static function () {
		return 200;
	},
	30
);

$GLOBALS['flxf_mails'] = 0;
add_filter(
	'pre_wp_mail',
	static function ( $pre ) {
		unset( $pre );
		$GLOBALS['flxf_mails']++;
		return true;
	},
	10
);

global $wpdb;
$wpdb->query( 'DELETE FROM `' . $wpdb->prefix . 'emsfb_msg_`' ); // phpcs:ignore WordPress.DB.PreparedSQL
$wpdb->query( 'DELETE FROM `' . $wpdb->prefix . 'emsfb_rsp_`' ); // phpcs:ignore WordPress.DB.PreparedSQL

$admin_id = 1;
$staff_id = (int) wp_insert_user(
	array(
		'user_login' => 'flxf_staff',
		'user_pass'  => wp_generate_password(),
		'user_email' => 'flxf-staff@example.com',
		'role'       => 'subscriber',
	)
);
( new WP_User( $staff_id ) )->add_cap( 'Emsfb' );
$sub_id = (int) wp_insert_user(
	array(
		'user_login' => 'flxf_sub',
		'user_pass'  => wp_generate_password(),
		'user_email' => 'flxf-sub@example.com',
		'role'       => 'subscriber',
	)
);

/**
 * Classic admin AJAX handler, run for real. Returns the decoded JSON.
 *
 * @param array<string,mixed> $post Request body.
 * @return array<string,mixed>
 */
function flxf_t_ajax( string $method, string $action, array $post, int $user ): array {
	wp_set_current_user( $user );
	$_POST    = $post + array( 'action' => $action, 'nonce' => wp_create_nonce( 'wp_rest' ) );
	$_REQUEST = $_POST;
	$throw    = static function (): void {
		throw new RuntimeException( 'ajax-die' );
	};
	$handler = static fn() => $throw;
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', $handler );
	if ( ! class_exists( '\\Emsfb\\Admin' ) ) {
		require_once EMSFB_PLUGIN_DIRECTORY . 'includes/admin/class-Emsfb-admin.php';
	}
	$ref   = new ReflectionClass( '\\Emsfb\\Admin' );
	$admin = $ref->newInstanceWithoutConstructor();
	$admin->ip = '203.0.113.9';
	$db = $ref->getProperty( 'db' );
	$db->setAccessible( true );
	$db->setValue( $admin, $GLOBALS['wpdb'] );
	ob_start();
	try {
		$admin->$method();
	} catch ( RuntimeException $e ) {
		unset( $e );
	}
	$out = (string) ob_get_clean();
	remove_filter( 'wp_doing_ajax', '__return_true' );
	remove_filter( 'wp_die_ajax_handler', $handler );
	$json = json_decode( $out, true );
	return is_array( $json ) ? $json : array( 'raw' => $out );
}

/**
 * @param array<string,mixed> $row Submission columns to override.
 */
function flxf_t_msg( int $form_id, string $track, array $content, int $read, string $date, array $row = array() ): int {
	global $wpdb;
	$stored = str_replace( '"', '\\"', (string) wp_json_encode( $content, JSON_UNESCAPED_UNICODE ) );
	$wpdb->insert(
		$wpdb->prefix . 'emsfb_msg_',
		$row + array(
			'form_id'      => $form_id,
			'track'        => $track,
			'ip'           => '198.51.100.7',
			'form_title_x' => 'Stored title',
			'content'      => $stored,
			'date'         => $date,
			'read_date'    => $date,
			'read_'        => $read,
			'read_by'      => '',
		)
	);
	return (int) $wpdb->insert_id;
}

function flxf_t_rsp( int $msg_id, array $content, int $by, string $date ): int {
	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . 'emsfb_rsp_',
		array(
			'msg_id'    => $msg_id,
			'ip'        => '198.51.100.7',
			'content'   => str_replace( '"', '\\"', (string) wp_json_encode( $content, JSON_UNESCAPED_UNICODE ) ),
			'date'      => $date,
			'read_by'   => '',
			'read_date' => $date,
			'read_'     => 0,
			'reader_ip' => '',
			'rsp_by'    => $by,
		)
	);
	return (int) $wpdb->insert_id;
}

function flxf_t_count( string $table, string $where ): int {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $wpdb->prefix . $table . '` WHERE ' . $where ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function flxf_t_read( int $id ): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT read_ FROM %i WHERE msg_id = %d', $wpdb->prefix . 'emsfb_msg_', $id ) );
}

/**
 * Message e-mail address field of the stored form; head asks the form to mail the visitor.
 */
$mail_head   = array(
	'type'     => 'form',
	'steps'    => 1,
	'formName' => 'Support',
	'sendEmail' => true,
	'email_to' => 'mail',
);
$panel_form  = flxf_t_form( 'Support', 'form', array( $mail_head, array( 'id_' => 'mail', 'id' => 'mail', 'type' => 'email', 'name' => 'Email', 'required' => true ), array( 'id_' => 'note', 'id' => 'note', 'type' => 'textarea', 'name' => 'Note' ) ) );
$survey_form = flxf_t_form( 'Poll', 'survey', array( array( 'type' => 'survey', 'steps' => 1 ), array( 'id_' => 'q', 'id' => 'q', 'type' => 'select', 'name' => 'Q' ) ) );
global $wpdb;
$wpdb->update( $wpdb->prefix . 'emsfb_form', array( 'status' => 0 ), array( 'form_id' => 0 ) );

$m1 = flxf_t_msg( $panel_form, 'TRACKAAA111', array( array( 'id_' => 'mail', 'name' => 'Email', 'type' => 'email', 'value' => 'visitor@example.com' ), array( 'id_' => 'note', 'name' => 'Note', 'type' => 'textarea', 'value' => 'Printer is on fire' ) ), 0, '2026-03-01 10:00:00' );
$m2 = flxf_t_msg( $panel_form, 'TRACKBBB222', array( array( 'id_' => 'note', 'name' => 'Note', 'type' => 'textarea', 'value' => 'Second @efb!choice-b@efb!choice-c' ), array( 'id_' => 'file', 'name' => 'File', 'type' => 'file', 'value' => '@file@', 'url' => 'https://example.com/wp-content/uploads/emsfb-PLG-keep.txt' ) ), 1, '2026-03-02 10:00:00' );
$m3 = flxf_t_msg( $panel_form, 'TRACKCCC333', array( array( 'id_' => 'note', 'name' => 'Note', 'type' => 'textarea', 'value' => 'Follow up please' ) ), 3, '2026-03-03 10:00:00' );
$m4 = flxf_t_msg( $panel_form, 'TRACKDDD444', array( array( 'id_' => 'note', 'name' => 'Note', 'type' => 'textarea', 'value' => 'Unpaid draft' ) ), 2, '2026-03-04 10:00:00' );
$m5 = flxf_t_msg( $survey_form, 'TRACKEEE555', array( array( 'id_' => 'q', 'name' => 'Q', 'type' => 'select', 'value' => 'Yes' ) ), 0, '2026-03-05 10:00:00' );
flxf_t_rsp( $m3, array( array( 'id_' => 'message', 'name' => 'message', 'type' => 'text', 'value' => 'Staff said hello', 'by' => 'Staff' ) ), $admin_id, '2026-03-03 11:00:00' );
flxf_t_rsp( $m3, array( array( 'id_' => 'message', 'name' => 'message', 'type' => 'text', 'value' => 'Visitor wrote again' ) ), 0, '2026-03-03 12:00:00' );
flxf_t_rsp( $m3, array( array( 'id_' => 'message', 'name' => 'message', 'type' => 'closed', 'value' => 'Closed by staff' ) ), -1, '2026-03-03 13:00:00' );

/* ------------------------------------------------------------ Capabilities */

foreach ( array( 'FlxFormsPanel' => array(), 'FlxFormResponses' => array(), 'FlxFormsSettings' => array() ) as $op => $vars ) {
	$r = flxf_t_op( $op, $vars, 0 );
	flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), "$op: visitor is rejected" );
	$r = flxf_t_op( $op, $vars, $sub_id );
	flxt_same( 'FORBIDDEN', flxt_code( $r ), "$op: a user without the panel capability is rejected" );
	$r = flxf_t_op( $op, $vars, $staff_id );
	flxt_same( null, flxt_code( $r ), "$op: Emsfb capability opens it" );
	$r = flxf_t_op( $op, $vars, $admin_id );
	flxt_same( null, flxt_code( $r ), "$op: administrator opens it" );
}
foreach ( array(
	'FlxFormResponse'        => array( 'id' => (string) $m1 ),
	'FlxFormResponseByTrack' => array( 'code' => 'TRACKAAA111' ),
	'FlxFormResponseRead'    => array( 'input' => array( 'id' => (string) $m5 ) ),
	'FlxFormResponsesRead'   => array( 'input' => array( 'ids' => array( (string) $m5 ) ) ),
	'FlxFormResponseDelete'  => array( 'input' => array( 'id' => (string) $m5 ) ),
	'FlxFormResponsesDelete' => array( 'input' => array( 'ids' => array( (string) $m5 ) ) ),
	'FlxFormResponseReply'   => array( 'input' => array( 'submissionId' => (string) $m1, 'message' => 'hi' ) ),
	'FlxFormsStorageClear'   => array( 'input' => new stdClass() ),
	'FlxFormsSettingsUpdate' => array( 'input' => array( 'showIp' => true ) ),
) as $op => $vars ) {
	$r = flxf_t_op( $op, $vars, 0 );
	flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), "$op: visitor is rejected" );
	$r = flxf_t_op( $op, $vars, $sub_id );
	flxt_same( 'FORBIDDEN', flxt_code( $r ), "$op: a user without the panel capability is rejected" );
}
flxt_same( 0, flxf_t_read( $m1 ), 'rejected calls changed nothing' );
flxt_same( 1, flxf_t_count( 'emsfb_msg_', 'msg_id = ' . $m5 ), 'rejected delete removed nothing' );

/* ------------------------------------------------------------------- Forms */

$r     = flxf_t_op( 'FlxFormsPanel', array(), $staff_id );
$nodes = $r['data']['flxForms']['nodes'] ?? array();
$by_id = array();
foreach ( $nodes as $node ) {
	$by_id[ (string) $node['id'] ] = $node;
}
flxt_same( 2, $by_id[ (string) $panel_form ]['unread'] ?? null, 'forms: unread counts new and new follow-up, not the unpaid draft' );
flxt_same( 3, $by_id[ (string) $panel_form ]['total'] ?? null, 'forms: total leaves the unpaid draft out' );
flxt_same( '[EMS_Form_Builder id=' . $panel_form . ']', $by_id[ (string) $panel_form ]['shortcode'] ?? null, 'forms: shortcode' );
flxt_same( 'SURVEY', $by_id[ (string) $survey_form ]['formType'] ?? null, 'forms: type enum' );
flxt_ok( isset( $nodes[0]['unread'] ) && $nodes[0]['unread'] > 0, 'forms: a form with new responses comes first' );

/* --------------------------------------------------------------- Responses */

$r     = flxf_t_op( 'FlxFormResponses', array(), $staff_id );
$items = $r['data']['flxFormResponses']['nodes'] ?? array();
$ids   = array_map( static fn( $n ) => (int) $n['id'], $items );
flxt_same( array( $m5, $m3, $m2, $m1 ), $ids, 'responses: newest first, the unpaid draft is not listed' );
flxt_same( 4, $r['data']['flxFormResponses']['totalCount'] ?? null, 'responses: total count' );
flxt_same( 'UNREAD', $items[3]['status'] ?? null, 'responses: status of a new response' );
flxt_same( 'UNREAD_FOLLOWUP', $items[1]['status'] ?? null, 'responses: status of a new follow-up' );
flxt_same( 'Email: visitor@example.com | Note: Printer is on fire', $items[3]['preview'] ?? null, 'responses: preview' );
flxt_same( 'Note: Second , choice-b, choice-c', $items[2]['preview'] ?? null, 'responses: multi-value marker is readable in the preview (files are left out)' );
flxt_same( gmdate( 'Y-m-d\\TH:i:s\\Z', (int) strtotime( get_gmt_from_date( '2026-03-01 10:00:00' ) . ' UTC' ) ), $items[3]['createdAt']['iso'] ?? null, 'responses: site-local time becomes the right instant' );

$r = flxf_t_op( 'FlxFormResponses', array( 'formId' => (string) $survey_form ), $staff_id );
flxt_same( array( (string) $m5 ), array_map( static fn( $n ) => (string) $n['id'], $r['data']['flxFormResponses']['nodes'] ?? array() ), 'responses: form filter' );
$r = flxf_t_op( 'FlxFormResponses', array( 'unread' => true ), $staff_id );
flxt_same( array( (string) $m5, (string) $m3, (string) $m1 ), array_map( static fn( $n ) => (string) $n['id'], $r['data']['flxFormResponses']['nodes'] ?? array() ), 'responses: unread filter' );
$r = flxf_t_op( 'FlxFormResponses', array( 'status' => 'READ' ), $staff_id );
flxt_same( array( (string) $m2 ), array_map( static fn( $n ) => (string) $n['id'], $r['data']['flxFormResponses']['nodes'] ?? array() ), 'responses: status filter' );
$r = flxf_t_op( 'FlxFormResponses', array( 'status' => 'PENDING_PAYMENT' ), $staff_id );
flxt_same( array(), $r['data']['flxFormResponses']['nodes'] ?? null, 'responses: unpaid drafts are never listed, even by status' );
foreach ( array( 'printer' => $m1, 'TRACKBBB' => $m2, 'visitor wrote again' => $m3, 'stored title' => $m5 ) as $needle => $want ) {
	$r = flxf_t_op( 'FlxFormResponses', array( 'search' => $needle ), $staff_id );
	$got = array_map( static fn( $n ) => (int) $n['id'], $r['data']['flxFormResponses']['nodes'] ?? array() );
	flxt_ok( in_array( $want, $got, true ), "responses: search '$needle' finds it (content, track, reply text, title)", $got );
}
$r = flxf_t_op( 'FlxFormResponses', array( 'search' => '100%_nothing' ), $staff_id );
flxt_same( array(), $r['data']['flxFormResponses']['nodes'] ?? null, 'responses: LIKE wildcards in the search are escaped' );
$r1 = flxf_t_op( 'FlxFormResponses', array( 'first' => 2 ), $staff_id );
flxt_same( 2, count( $r1['data']['flxFormResponses']['nodes'] ?? array() ), 'responses: page size' );
flxt_same( true, $r1['data']['flxFormResponses']['pageInfo']['hasNextPage'] ?? null, 'responses: has next page' );
$r2 = flxf_t_op( 'FlxFormResponses', array( 'first' => 2, 'after' => $r1['data']['flxFormResponses']['pageInfo']['endCursor'] ?? null ), $staff_id );
flxt_same( array( (string) $m2, (string) $m1 ), array_map( static fn( $n ) => (string) $n['id'], $r2['data']['flxFormResponses']['nodes'] ?? array() ), 'responses: second page continues' );
$r = flxf_t_op( 'FlxFormResponses', array( 'first' => 500 ), $staff_id );
flxt_ok( count( $r['data']['flxFormResponses']['nodes'] ?? array() ) <= 50, 'responses: page size is capped at 50' );

/* ----------------------------------------- REST (FluxApp) shares the service */

$req = new WP_REST_Request( 'GET', '/flx-forms/v1/responses' );
$req->set_param( 'page', 1 );
$req->set_param( 'per_page', 25 );
wp_set_current_user( $admin_id );
$rest = flxf_app_responses( $req );
flxt_same( 4, $rest['total'] ?? null, 'REST list: total is unchanged' );
$item = $rest['items'][0] ?? array();
flxt_same( array( 'id', 'form_id', 'form', 'track', 'preview', 'unread', 'date', 'date_label' ), array_keys( $item ), 'REST list: item keys are unchanged' );
flxt_same( array( $m5, $survey_form, 'Poll', 'TRACKEEE555', true, gmdate( 'c', strtotime( '2026-03-05 10:00:00' ) ), flx_date( strtotime( '2026-03-05 10:00:00' ), 'datetime' ) ), array( $item['id'] ?? null, $item['form_id'] ?? null, $item['form'] ?? null, $item['track'] ?? null, $item['unread'] ?? null, $item['date'] ?? null, $item['date_label'] ?? null ), 'REST list: item values are unchanged' );
$req->set_param( 'unread', 1 );
$rest = flxf_app_responses( $req );
flxt_same( 3, $rest['total'] ?? null, 'REST list: unread filter' );
$req = new WP_REST_Request( 'GET', '/flx-forms/v1/responses/' . $m1 );
$req->set_param( 'id', $m1 );
$one = flxf_app_response( $req );
flxt_same( array( $m1, 'TRACKAAA111', true ), array( $one['id'] ?? null, $one['track'] ?? null, $one['unread'] ?? null ), 'REST single: row shape is unchanged' );
$req = new WP_REST_Request( 'GET', '/flx-forms/v1/responses/' . $m4 );
$req->set_param( 'id', $m4 );
flxt_same( 404, ( flxf_app_response( $req ) )->get_error_data()['status'] ?? null, 'REST single: an unpaid draft is 404' );

/* -------------------------------------------------------------- One response */

$r   = flxf_t_op( 'FlxFormResponse', array( 'id' => (string) $m3 ), $staff_id );
$one = $r['data']['flxFormResponse'] ?? array();
flxt_same( 'UNREAD_FOLLOWUP', $one['status'] ?? null, 'response: status' );
flxt_same( false, $one['open'] ?? null, 'response: closed ticket' );
flxt_same( array( 'USER', 'GUEST', 'SUPPORT' ), array_map( static fn( $x ) => $x['author'], $one['replies'] ?? array() ), 'response: reply authors' );
flxt_same( array( 'REPLY', 'REPLY', 'CLOSED' ), array_map( static fn( $x ) => $x['kind'], $one['replies'] ?? array() ), 'response: reply kinds' );
flxt_same( 'Staff said hello', $one['replies'][0]['body'] ?? null, 'response: classic reply row is readable' );
flxt_same( 'admin', strtolower( (string) ( $one['replies'][0]['by'] ?? '' ) ), 'response: staff name' );
flxt_same( array( 'Note' ), array_map( static fn( $x ) => $x['label'], $one['fields'] ?? array() ), 'response: fields' );
$r = flxf_t_op( 'FlxFormResponse', array( 'id' => (string) $m2 ), $staff_id );
flxt_same( true, $r['data']['flxFormResponse']['fields'][1]['isFile'] ?? null, 'response: a file answer is flagged' );
flxt_same( 'https://example.com/wp-content/uploads/emsfb-PLG-keep.txt', $r['data']['flxFormResponse']['fields'][1]['value'] ?? null, 'response: a file answer carries its URL' );
$r = flxf_t_op( 'FlxFormResponse', array( 'id' => (string) $m4 ), $staff_id );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'response: an unpaid draft is NOT_FOUND' );
$r = flxf_t_op( 'FlxFormResponse', array( 'id' => '999999' ), $staff_id );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'response: missing id is NOT_FOUND' );
$r = flxf_t_op( 'FlxFormResponseByTrack', array( 'code' => 'TRACKAAA111' ), $staff_id );
flxt_same( (string) $m1, $r['data']['flxFormResponseByTrack']['id'] ?? null, 'by track: found' );
$r = flxf_t_op( 'FlxFormResponseByTrack', array( 'code' => 'NOPE' ), $staff_id );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'by track: missing code is NOT_FOUND' );

/* Visitor's tracker (classic code) reads what staff see; closed marker is not an answer. */
$track = FLXF_Submission_Service::track( 'TRACKCCC333' );
flxt_ok( is_array( $track ) && count( $track['replies'] ) >= 2, 'classic tracker: sees the replies written by the classic panel', $track );

/* -------------------------------------------------------------------- Read */

$r = flxf_t_op( 'FlxFormResponseRead', array( 'input' => array( 'id' => (string) $m1 ) ), $staff_id );
flxt_same( 'READ', $r['data']['flxFormResponseRead']['response']['status'] ?? null, 'read: new becomes read' );
flxt_same( 1, flxf_t_read( $m1 ), 'read: stored as 1' );
$r = flxf_t_op( 'FlxFormResponseRead', array( 'input' => array( 'id' => (string) $m3 ) ), $staff_id );
flxt_same( 'READ_FOLLOWUP', $r['data']['flxFormResponseRead']['response']['status'] ?? null, 'read: new follow-up becomes read follow-up' );
flxt_same( 4, flxf_t_read( $m3 ), 'read: stored as 4' );
flxt_ok( flxf_t_count( 'emsfb_rsp_', "msg_id = $m3 AND read_ = 1" ) === 3, 'read: replies are marked read' );
$r = flxf_t_op( 'FlxFormResponseRead', array( 'input' => array( 'id' => '999999' ) ), $staff_id );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'read: missing id' );
$r = flxf_t_op( 'FlxFormResponsesRead', array( 'input' => array( 'ids' => array( (string) $m5, '999999' ) ) ), $staff_id );
flxt_same( 1, $r['data']['flxFormResponsesRead']['updated'] ?? null, 'bulk read: counts existing responses' );
flxt_same( 1, flxf_t_read( $m5 ), 'bulk read: stored' );
$r = flxf_t_op( 'FlxFormResponsesRead', array( 'input' => array( 'ids' => array() ) ), $staff_id );
flxt_same( array( array( 'INVALID_INPUT', 'ids' ) ), flxf_t_errs( $r, 'flxFormResponsesRead' ), 'bulk read: empty list' );
/* The FluxApp REST route does the same state change. */
wp_set_current_user( $admin_id );
flxf_t_msg( $panel_form, 'TRACKFFF666', array( array( 'id_' => 'note', 'name' => 'Note', 'type' => 'textarea', 'value' => 'rest read' ) ), 3, '2026-03-06 10:00:00' );
$m6  = (int) $wpdb->insert_id;
$req = new WP_REST_Request( 'POST', '/flx-forms/v1/responses/' . $m6 . '/read' );
$req->set_param( 'id', $m6 );
flxt_same( array( 'ok' => true, 'id' => $m6 ), flxf_app_response_read( $req ), 'REST read: output is unchanged' );
flxt_same( 4, flxf_t_read( $m6 ), 'REST read: 3 becomes 4, same as GraphQL' );

/* ------------------------------------------------------------------- Reply */

$GLOBALS['flxf_mails'] = 0;
$r = flxf_t_op( 'FlxFormResponseReply', array( 'input' => array( 'submissionId' => (string) $m1, 'message' => 'We are on it.' ) ), $staff_id );
flxt_same( array(), flxf_t_errs( $r, 'flxFormResponseReply' ), 'reply: no errors' );
$resp = $r['data']['flxFormResponseReply']['response'] ?? array();
flxt_same( 'We are on it.', end( $resp['replies'] )['body'] ?? null, 'reply: returned with the new reply' );
flxt_same( 'USER', end( $resp['replies'] )['author'] ?? null, 'reply: written as a signed-in user' );
flxt_same( 1, $GLOBALS['flxf_mails'], 'reply: the visitor mail goes out exactly once' );
$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE msg_id = %d ORDER BY rsp_id DESC LIMIT 1', $wpdb->prefix . 'emsfb_rsp_', $m1 ), ARRAY_A );
flxt_same( $staff_id, (int) ( $row['rsp_by'] ?? 0 ), 'reply: stored with the staff user id' );
$decoded = json_decode( str_replace( '\\', '', (string) ( $row['content'] ?? '' ) ), true );
flxt_same( 'message', $decoded[0]['id_'] ?? null, 'reply: classic stored shape (readable by the classic viewer)' );
flxt_same( 'We are on it.', $decoded[0]['value'] ?? null, 'reply: classic stored value' );
flxt_same( 1, flxf_t_read( $m1 ), 'reply: the response is marked read' );
$track = FLXF_Submission_Service::track( 'TRACKAAA111' );
flxt_same( 'We are on it.', is_array( $track ) ? ( $track['replies'][0]['body'] ?? null ) : null, 'classic tracker: shows the GraphQL reply' );

$r = flxf_t_op( 'FlxFormResponseReply', array( 'input' => array( 'submissionId' => (string) $m1, 'message' => '<b>bold</b>' ) ), $staff_id );
flxt_same( array( array( 'INVALID_INPUT', 'message' ) ), flxf_t_errs( $r, 'flxFormResponseReply' ), 'reply: HTML is rejected like the classic panel' );
$r = flxf_t_op( 'FlxFormResponseReply', array( 'input' => array( 'submissionId' => (string) $m1, 'message' => '' ) ), $staff_id );
flxt_same( array( array( 'INVALID_INPUT', 'message' ) ), flxf_t_errs( $r, 'flxFormResponseReply' ), 'reply: empty reply is rejected' );
$r = flxf_t_op( 'FlxFormResponseReply', array( 'input' => array( 'submissionId' => '999999', 'message' => 'x' ) ), $staff_id );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'reply: missing response' );
flxt_same( 1, $GLOBALS['flxf_mails'], 'reply: rejected replies send no mail' );

/* Attachment: only a file the upload endpoint reserved for this ticket. */
$upload = wp_upload_dir();
$file   = $upload['basedir'] . '/emsfb-PLG-reply-test.txt';
file_put_contents( $file, 'hello' );
$url = $upload['baseurl'] . '/emsfb-PLG-reply-test.txt';
$r   = flxf_t_op( 'FlxFormResponseReply', array( 'input' => array( 'submissionId' => (string) $m1, 'message' => 'See file', 'attachments' => array( $url ) ) ), $staff_id );
flxt_same( array( array( 'INVALID_INPUT', 'attachments' ) ), flxf_t_errs( $r, 'flxFormResponseReply' ), 'reply: an unreserved file is rejected (the classic upload hole is not inherited)' );
flxt_same( 1, flxf_t_count( 'emsfb_rsp_', "msg_id = $m1" ), 'reply: the rejected reply wrote nothing' );
\Emsfb\Upload_Guard::reserve_response_attachment( $file, $m1, 'TRACKAAA111' );
\Emsfb\Upload_Guard::track_pending_upload( $file );
$other = flxf_t_msg( $panel_form, 'TRACKOTHER99', array( array( 'id_' => 'note', 'name' => 'Note', 'type' => 'textarea', 'value' => 'other' ) ), 1, '2026-03-07 10:00:00' );
$r     = flxf_t_op( 'FlxFormResponseReply', array( 'input' => array( 'submissionId' => (string) $other, 'message' => 'wrong ticket', 'attachments' => array( $url ) ) ), $staff_id );
flxt_same( array( array( 'INVALID_INPUT', 'attachments' ) ), flxf_t_errs( $r, 'flxFormResponseReply' ), 'reply: a file reserved for another ticket is rejected' );
$r = flxf_t_op( 'FlxFormResponseReply', array( 'input' => array( 'submissionId' => (string) $m1, 'message' => 'See file', 'attachments' => array( $url ) ) ), $staff_id );
flxt_same( array(), flxf_t_errs( $r, 'flxFormResponseReply' ), 'reply: a reserved file is accepted' );
$resp = $r['data']['flxFormResponseReply']['response'] ?? array();
flxt_same( array( $url ), end( $resp['replies'] )['attachments'] ?? null, 'reply: attachment is returned' );
$row     = $wpdb->get_row( $wpdb->prepare( 'SELECT content FROM %i WHERE msg_id = %d ORDER BY rsp_id DESC LIMIT 1', $wpdb->prefix . 'emsfb_rsp_', $m1 ), ARRAY_A );
$decoded = json_decode( str_replace( '\\', '', (string) ( $row['content'] ?? '' ) ), true );
flxt_same( array( 'allformat', '@file@', $url ), array( $decoded[1]['type'] ?? null, $decoded[1]['value'] ?? null, $decoded[1]['url'] ?? null ), 'reply: file row is stored the classic way' );
flxt_ok( ! \Emsfb\Upload_Guard::response_attachment_is_reserved( $url, $m1, 'TRACKAAA111' ), 'reply: the reservation is released' );
$r = flxf_t_op( 'FlxFormResponseReply', array( 'input' => array( 'submissionId' => (string) $m1, 'message' => 'again', 'attachments' => array( $url ) ) ), $staff_id );
flxt_same( array( array( 'INVALID_INPUT', 'attachments' ) ), flxf_t_errs( $r, 'flxFormResponseReply' ), 'reply: a used file cannot be attached twice' );
wp_delete_file( $file );

/* Close and open. */
$r = flxf_t_op( 'FlxFormResponseReply', array( 'input' => array( 'submissionId' => (string) $m2, 'state' => 'CLOSE' ) ), $staff_id );
flxt_same( false, $r['data']['flxFormResponseReply']['response']['open'] ?? null, 'close: ticket is closed' );
flxt_same( 1, flxf_t_read( $m2 ), 'close: the submission ends read, like the classic panel' );
$r = flxf_t_op( 'FlxFormResponseReply', array( 'input' => array( 'submissionId' => (string) $m2, 'state' => 'OPEN', 'message' => 'Back again' ) ), $staff_id );
flxt_same( true, $r['data']['flxFormResponseReply']['response']['open'] ?? null, 'open: ticket is open again' );
flxt_same( array( 'CLOSED', 'OPENED' ), array_map( static fn( $x ) => $x['kind'], $r['data']['flxFormResponseReply']['response']['replies'] ?? array() ), 'open: the marker row carries the message' );
flxt_same( 'Back again', $r['data']['flxFormResponseReply']['response']['replies'][1]['body'] ?? null, 'open: message text of the marker row' );

/* ---------------------------------------- Classic AJAX handlers, same data */

$GLOBALS['flxf_mails'] = 0;
$msg_json = wp_json_encode( array( array( 'id_' => 'message', 'name' => 'message', 'type' => 'text', 'amount' => 0, 'value' => 'Classic reply', 'by' => 'Admin' ) ) );
$out      = flxf_t_ajax( 'set_replyMessage_id_Emsfb', 'set_replyMessage_id_Emsfb', array( 'id' => $m3, 'message' => $msg_json ), $admin_id );
flxt_same( true, $out['data']['success'] ?? null, 'classic reply: still succeeds' );
flxt_same( get_userdata( $admin_id )->display_name, $out['data']['by'] ?? null, 'classic reply: author name in the JSON' );
$resp = flxf_t_op( 'FlxFormResponse', array( 'id' => (string) $m3 ), $staff_id )['data']['flxFormResponse'] ?? array();
flxt_same( 'Classic reply', end( $resp['replies'] )['body'] ?? null, 'classic reply: GraphQL reads what the classic panel wrote' );
flxt_same( 1, flxf_t_read( $m3 ), 'classic reply: marks the response read' );
$denied = flxf_t_ajax( 'set_replyMessage_id_Emsfb', 'set_replyMessage_id_Emsfb', array( 'id' => $m3, 'message' => $msg_json ), $sub_id );
flxt_same( false, $denied['data']['success'] ?? null, 'classic reply: still refuses a user without the capability' );

/* ------------------------------------------------------------------ Delete */

$gone = flxf_t_msg( $panel_form, 'TRACKDEL001', array( array( 'id_' => 'note', 'name' => 'Note', 'type' => 'textarea', 'value' => 'delete me' ) ), 1, '2026-03-08 10:00:00' );
$gtwo = flxf_t_msg( $panel_form, 'TRACKDEL002', array( array( 'id_' => 'note', 'name' => 'Note', 'type' => 'textarea', 'value' => 'delete me too' ) ), 1, '2026-03-08 11:00:00' );
$gtre = flxf_t_msg( $panel_form, 'TRACKDEL003', array( array( 'id_' => 'note', 'name' => 'Note', 'type' => 'textarea', 'value' => 'delete me three' ) ), 1, '2026-03-08 12:00:00' );
flxf_t_rsp( $gone, array( array( 'id_' => 'message', 'type' => 'text', 'value' => 'r1' ) ), 0, '2026-03-08 10:30:00' );
flxf_t_rsp( $gtwo, array( array( 'id_' => 'message', 'type' => 'text', 'value' => 'r2' ) ), 0, '2026-03-08 11:30:00' );
flxf_t_rsp( $gtre, array( array( 'id_' => 'message', 'type' => 'text', 'value' => 'r3' ) ), 0, '2026-03-08 12:30:00' );
$r = flxf_t_op( 'FlxFormResponseDelete', array( 'input' => array( 'id' => (string) $gone ) ), $staff_id );
flxt_same( true, $r['data']['flxFormResponseDelete']['deleted'] ?? null, 'delete: removes the response' );
flxt_same( 0, flxf_t_count( 'emsfb_msg_', "msg_id = $gone" ) + flxf_t_count( 'emsfb_rsp_', "msg_id = $gone" ), 'delete: the replies go with it' );
$r = flxf_t_op( 'FlxFormResponseDelete', array( 'input' => array( 'id' => (string) $gone ) ), $staff_id );
flxt_same( false, $r['data']['flxFormResponseDelete']['deleted'] ?? null, 'delete: a missing response is not deleted' );
$r = flxf_t_op( 'FlxFormResponsesDelete', array( 'input' => array( 'ids' => array( (string) $gtwo, (string) $gtre, '999999' ) ) ), $staff_id );
flxt_same( 2, $r['data']['flxFormResponsesDelete']['deleted'] ?? null, 'bulk delete: counts what was deleted' );
flxt_same( 0, flxf_t_count( 'emsfb_rsp_', "msg_id IN ($gtwo, $gtre)" ), 'bulk delete: replies go too' );
$r = flxf_t_op( 'FlxFormResponsesDelete', array( 'input' => array( 'ids' => array_map( 'strval', range( 1000, 1200 ) ) ) ), $staff_id );
flxt_same( array( array( 'INVALID_INPUT', 'ids' ) ), flxf_t_errs( $r, 'flxFormResponsesDelete' ), 'bulk delete: more than 200 is rejected' );
/* The classic bulk delete is the same service. */
$c1 = flxf_t_msg( $panel_form, 'TRACKCLS001', array( array( 'id_' => 'note', 'name' => 'Note', 'type' => 'textarea', 'value' => 'classic del' ) ), 1, '2026-03-09 10:00:00' );
flxf_t_rsp( $c1, array( array( 'id_' => 'message', 'type' => 'text', 'value' => 'rc' ) ), 0, '2026-03-09 10:30:00' );
$out = flxf_t_ajax( 'delete_messages_Emsfb', 'remove_messages_Emsfb', array( 'state' => 'msg', 'val' => wp_json_encode( array( array( 'msg_id' => $c1 ) ) ) ), $admin_id );
flxt_same( true, $out['data']['success'] ?? null, 'classic bulk delete: still succeeds' );
flxt_same( 0, flxf_t_count( 'emsfb_msg_', "msg_id = $c1" ) + flxf_t_count( 'emsfb_rsp_', "msg_id = $c1" ), 'classic bulk delete: removes the response and its replies' );

/* ---------------------------------------------------------- Storage clear */

$keep   = $upload['basedir'] . '/emsfb-PLG-keep.txt';
$orphan = $upload['basedir'] . '/emsfb-PLG-orphan.txt';
file_put_contents( $keep, 'k' );
file_put_contents( $orphan, 'o' );
$r = flxf_t_op( 'FlxFormsStorageClear', array( 'input' => new stdClass() ), $staff_id );
flxt_same( true, $r['data']['flxFormsStorageClear']['cleared'] ?? null, 'storage clear: runs' );
flxt_ok( file_exists( $keep ), 'storage clear: a file that a response points to stays' );
flxt_ok( ! file_exists( $orphan ), 'storage clear: an unused file is removed' );
wp_delete_file( $keep );

/* ---------------------------------------------------------------- Settings */

$secret = 'sk_live_contract_secret_value_123';
$r      = flxf_t_op( 'FlxFormsSettings', array(), $staff_id );
$s      = $r['data']['flxFormsSettings'] ?? array();
flxt_same( false, $s['stripeSecretSaved'] ?? null, 'settings: no Stripe secret saved at first' );
flxt_same( 1, $s['sessionDuration'] ?? null, 'settings: default session duration' );
flxt_same( 'DATE_EN_MIX', $s['trackCodeStyle'] ?? null, 'settings: default track code style' );

$r = flxf_t_op(
	'FlxFormsSettingsUpdate',
	array(
		'input' => array(
			'showIp'          => true,
			'sessionDuration' => 3,
			'trackCodeStyle'  => 'UNIQUE_NUM',
			'siteKey'         => 'public-site-key',
			'secretKey'       => 'recaptcha-secret-value',
			'mapKey'          => 'map-key-value',
			'stripePKey'      => 'pk_live_public',
			'stripeSecretKey' => $secret,
			'payToken'        => 'gateway-token-value',
			'emailSupporter'  => 'support@example.com',
			'smsConfig'       => 'WP_SMS',
			'smsPhoneNumbers' => '+12345678900, +10987654321',
			'responseStyle'   => array( 'primary' => '#112233', 'fontSize' => '1rem' ),
		),
	),
	$staff_id
);
flxt_same( array(), flxf_t_errs( $r, 'flxFormsSettingsUpdate' ), 'settings update: no errors', $r );
$s    = $r['data']['flxFormsSettingsUpdate']['settings'] ?? array();
$json = (string) wp_json_encode( $r );
foreach ( array( $secret, 'recaptcha-secret-value', 'map-key-value', 'gateway-token-value', '+12345678900' ) as $leak ) {
	flxt_ok( false === strpos( $json, $leak ), 'settings update: a secret never comes back (' . substr( $leak, 0, 8 ) . '…)' );
}
flxt_same( true, $s['stripeSecretSaved'] ?? null, 'settings update: secret flag' );
flxt_same( true, $s['secretKeySaved'] ?? null, 'settings update: reCAPTCHA secret flag' );
flxt_same( true, $s['mapKeySaved'] ?? null, 'settings update: map key flag' );
flxt_same( true, $s['payTokenSaved'] ?? null, 'settings update: payment token flag' );
flxt_same( true, $s['smsPhoneSaved'] ?? null, 'settings update: phone flag' );
flxt_same( 'WP_SMS', $s['smsConfig'] ?? null, 'settings update: SMS service' );
flxt_same( '#112233', $s['responseStyle']['primary'] ?? null, 'settings update: response colour' );
flxt_same( '#112233', flxf_t_op( 'FlxFormsSettings', array(), $staff_id )['data']['flxFormsSettings']['responseStyle']['primary'] ?? null, 'settings update: persisted' );
$stored = \Emsfb::get_setting_Emsfb( 'decoded' );
\Emsfb::get_setting_Emsfb( '_clear_cache' );
$stored = \Emsfb::get_setting_Emsfb( 'decoded' );
flxt_same( $secret, is_object( $stored ) ? ( $stored->stripeSKey ?? null ) : null, 'settings update: the classic code reads the stored secret' );
flxt_same( 'recaptcha-secret-value', is_object( $stored ) ? ( $stored->secretKey ?? null ) : null, 'settings update: classic secret key' );
flxt_same( '+12345678900, +10987654321', is_object( $stored ) ? ( $stored->phnNo ?? null ) : null, 'settings update: classic phone numbers' );
flxt_same( 'wpsms', is_object( $stored ) ? ( $stored->sms_config ?? null ) : null, 'settings update: classic SMS key' );
flxt_same( '3', (string) ( is_object( $stored ) ? ( $stored->sessionDuration ?? null ) : null ), 'settings update: classic session duration' );
flxt_same( 'unique_num', is_object( $stored ) ? ( $stored->trackCodeStyle ?? null ) : null, 'settings update: classic track style' );
flxt_same( 1, (int) ( is_object( $stored ) ? ( $stored->package_type ?? 0 ) : 0 ), 'settings update: package type stays full' );

$r = flxf_t_op( 'FlxFormsSettingsUpdate', array( 'input' => array( 'secretKey' => '', 'stripeSecretKey' => '', 'showIp' => false ) ), $staff_id );
\Emsfb::get_setting_Emsfb( '_clear_cache' );
$stored = \Emsfb::get_setting_Emsfb( 'decoded' );
flxt_same( $secret, is_object( $stored ) ? ( $stored->stripeSKey ?? null ) : null, 'settings update: an empty secret keeps the saved one' );
flxt_same( 'support@example.com', is_object( $stored ) ? ( $stored->emailSupporter ?? null ) : null, 'settings update: fields left out stay as they are' );
flxt_same( false, $r['data']['flxFormsSettingsUpdate']['settings']['showIp'] ?? null, 'settings update: a boolean can be turned off' );
$r = flxf_t_op( 'FlxFormsSettingsUpdate', array( 'input' => array( 'stripeSecretKey' => '__clear__', 'smsPhoneNumbers' => '__clear__' ) ), $staff_id );
flxt_same( false, $r['data']['flxFormsSettingsUpdate']['settings']['stripeSecretSaved'] ?? null, 'settings update: __clear__ removes a secret' );
flxt_same( false, $r['data']['flxFormsSettingsUpdate']['settings']['smsPhoneSaved'] ?? null, 'settings update: __clear__ removes the phone numbers' );
flxt_same( true, $r['data']['flxFormsSettingsUpdate']['settings']['secretKeySaved'] ?? null, 'settings update: other secrets are not touched by __clear__' );

foreach ( array(
	'emailSupporter'  => array( 'emailSupporter', 'not-an-email' ),
	'femail'          => array( 'femail', 'nope' ),
	'sessionDuration' => array( 'sessionDuration', 9 ),
	'color'           => array( 'emailBtnBgColor', 'red' ),
	'phone'           => array( 'smsPhoneNumbers', '12345' ),
) as $name => $pair ) {
	$r = flxf_t_op( 'FlxFormsSettingsUpdate', array( 'input' => array( $pair[0] => $pair[1] ) ), $staff_id );
	flxt_same( array( array( 'INVALID_INPUT', $pair[0] ) ), flxf_t_errs( $r, 'flxFormsSettingsUpdate' ), "settings update: invalid $name" );
}
$r = flxf_t_op( 'FlxFormsSettingsUpdate', array( 'input' => array( 'responseStyle' => array( 'bgCard' => 'blue' ) ) ), $staff_id );
flxt_same( array( array( 'INVALID_INPUT', 'responseStyle' ) ), flxf_t_errs( $r, 'flxFormsSettingsUpdate' ), 'settings update: invalid response colour' );
$r = flxf_t_op( 'FlxFormsSettingsUpdate', array( 'input' => array( 'emailTemp' => '<p>no placeholder here at all</p>' ) ), $staff_id );
flxt_same( 1, count( flxf_t_errs( $r, 'flxFormsSettingsUpdate' ) ), 'settings update: a template without shortcode_message is rejected like the classic panel' );
$r = flxf_t_op( 'FlxFormsSettingsUpdate', array( 'input' => array( 'emailTemp' => '<p>Hi shortcode_message</p><script>alert(1)</script><a href="https://example.com/x">x</a>' ) ), $staff_id );
flxt_same( array(), flxf_t_errs( $r, 'flxFormsSettingsUpdate' ), 'settings update: a valid template is saved' );
$temp = (string) ( $r['data']['flxFormsSettingsUpdate']['settings']['emailTemp'] ?? '' );
flxt_ok( false === strpos( $temp, '<script' ), 'settings update: the template is sanitised exactly like the classic panel', $temp );
flxt_ok( false !== strpos( $temp, 'https://example.com/x' ), 'settings update: template links read back unpacked', $temp );
\Emsfb::get_setting_Emsfb( '_clear_cache' );
$stored = \Emsfb::get_setting_Emsfb( 'decoded' );
flxt_ok( is_object( $stored ) && false !== strpos( (string) ( $stored->emailTemp ?? '' ), '@efb@' ), 'settings update: the template is stored in the classic packed form' );

/* Classic panel write -> GraphQL read, through the real AJAX handler. */
$post_setting = array(
	'showIp'          => true,
	'activeDlBtn'     => false,
	'respAccent'      => '#abcdef',
	'emailSupporter'  => 'classic@example.com',
	'stripeSKey'      => 'sk_classic_written',
	'emailTemp'       => '<p>Classic shortcode_message</p>',
);
$out = flxf_t_ajax( 'set_settings_Emsfb', 'set_settings_Emsfb', array( 'message' => wp_json_encode( $post_setting ) ), $admin_id );
flxt_same( true, $out['data']['success'] ?? null, 'classic settings save: still succeeds' );
$s = flxf_t_op( 'FlxFormsSettings', array(), $staff_id )['data']['flxFormsSettings'] ?? array();
flxt_same( 'classic@example.com', $s['emailSupporter'] ?? null, 'classic settings save: GraphQL reads it' );
flxt_same( '#abcdef', $s['responseStyle']['accent'] ?? null, 'classic settings save: response colour' );
flxt_same( true, $s['stripeSecretSaved'] ?? null, 'classic settings save: secret flag set, value not returned' );
flxt_same( false, $s['activeDlBtn'] ?? null, 'classic settings save: boolean read' );
flxt_same( '<p>Classic shortcode_message</p>', $s['emailTemp'] ?? null, 'classic settings save: template unpacked on read' );
$out = flxf_t_ajax( 'set_settings_Emsfb', 'set_settings_Emsfb', array( 'message' => wp_json_encode( array( 'emailTemp' => '<p>no placeholder</p>' ) ) ), $admin_id );
flxt_same( false, $out['data']['success'] ?? null, 'classic settings save: the template check still rejects' );
$denied = flxf_t_ajax( 'set_settings_Emsfb', 'set_settings_Emsfb', array( 'message' => wp_json_encode( $post_setting ) ), $sub_id );
flxt_same( false, $denied['data']['success'] ?? null, 'classic settings save: still refuses a user without the capability' );
