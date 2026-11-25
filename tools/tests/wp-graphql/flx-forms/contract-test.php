<?php
/**
 * Flux Forms public GraphQL contract. The documents are the persisted operations in
 * plugins/flx-forms/graphql/persisted-queries.json.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-forms/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux Forms GraphQL\n";

$GLOBALS['flxf_ops'] = array();
$manifest            = dirname( FLXF_PLUGIN_FILE ) . '/graphql/persisted-queries.json';
foreach ( (array) json_decode( (string) file_get_contents( $manifest ), true ) as $doc ) {
	if ( is_string( $doc ) && preg_match( '/(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxf_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 30, count( $GLOBALS['flxf_ops'] ), 'Flux Forms ships 30 persisted operations' );

/**
 * @param array<string,mixed> $vars Variables.
 */
function flxf_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxf_ops'][ $name ], $vars, $user );
}

/**
 * @return list<array{0:string,1:?string}>
 */
function flxf_t_errs( array $r, string $field ): array {
	$rows = $r['data'][ $field ]['errors'] ?? array();
	$out  = array();
	foreach ( is_array( $rows ) ? $rows : array() as $e ) {
		if ( is_array( $e ) ) {
			$out[] = array( (string) ( $e['code'] ?? '' ), isset( $e['field'] ) ? (string) $e['field'] : null );
		}
	}
	return $out;
}

/**
 * @param mixed        $value Any JSON value.
 * @param list<string> $keys  Collected object keys.
 */
function flxf_t_keys( $value, array &$keys ): void {
	if ( ! is_array( $value ) ) {
		return;
	}
	foreach ( $value as $key => $child ) {
		if ( is_string( $key ) ) {
			$keys[] = $key;
		}
		flxf_t_keys( $child, $keys );
	}
}

/**
 * @param array<string,mixed> $structure Form structure, head first.
 */
function flxf_t_form( string $name, string $type, array $structure ): int {
	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . 'emsfb_form',
		array(
			'form_name'       => $name,
			'form_structer'   => (string) wp_json_encode( $structure ),
			'form_email'      => 'secret-notify@example.com',
			'form_type'       => $type,
			'form_created_by' => '1',
			'form_access_by'  => 'admin',
		)
	);
	return (int) $wpdb->insert_id;
}

/**
 * Stores the plugin settings row the classic code reads (the newest row wins) and drops the caches.
 *
 * @param array<string,mixed> $extra Keys added to the base settings.
 */
function flxf_t_settings( array $extra ): void {
	global $wpdb;
	$base = array(
		'smtp'           => '1',
		'emailSupporter' => 'supporter@example.com',
		'femail'         => 'noreply@example.com',
		'email_key'      => 'contractemailkey123',
		'emailTemp'      => '',
	);
	$wpdb->insert(
		$wpdb->prefix . 'emsfb_setting',
		array(
			'setting' => (string) wp_json_encode( array_merge( $base, $extra ) ),
			'email'   => 'supporter@example.com',
			'date'    => current_time( 'mysql' ),
		)
	);
	if ( class_exists( 'Emsfb' ) ) {
		Emsfb::get_setting_Emsfb( '_clear_cache' );
	}
	delete_transient( 'emsfb_settings_transient' );
	wp_cache_delete( 'settings:decoded', 'emsfb' );
	wp_cache_delete( 'settings:raw', 'emsfb' );
	wp_cache_delete( 'emsfb_settings', 'emsfb' );
}

/**
 * Calls a classic REST handler of the public class and returns the JSON it printed. The classic
 * handlers print and exit, so the ajax die handler is swapped for an exception.
 *
 * @param array<string,mixed> $json Request body.
 * @return array<string,mixed>
 */
function flxf_t_classic( string $method, array $json ): array {
	$req = new WP_REST_Request( 'POST', '/Emsfb/v1/test' );
	$req->set_header( 'content-type', 'application/json' );
	$req->set_body( (string) wp_json_encode( $json ) );
	$ajax   = '__return_true';
	$thrower = static fn() => static function () {
		throw new RuntimeException( 'flxf-die' );
	};
	add_filter( 'wp_doing_ajax', $ajax );
	add_filter( 'wp_die_ajax_handler', $thrower );
	$pub          = \Emsfb\_Public::flxf_live();
	$pub->setting = null; // A classic request starts with no settings loaded; GraphQL fills them on the shared instance.
	$pub->efb_uid = get_current_user_id();
	ob_start();
	try {
		$pub->$method( $req );
	} catch ( RuntimeException $e ) {
		unset( $e );
	}
	$out = (string) ob_get_clean();
	remove_filter( 'wp_doing_ajax', $ajax );
	remove_filter( 'wp_die_ajax_handler', $thrower );
	$decoded = json_decode( $out, true );
	return is_array( $decoded ) ? $decoded : array();
}

$secret = 'secret-notify@example.com';
$head   = array(
	'type'                 => 'form',
	'steps'                => 2,
	'formName'             => 'Contact',
	'email'                => $secret,
	'email_to'             => $secret,
	'sendEmail'            => true,
	'email_temp'           => 'secret-template',
	'button_single_text'   => 'Send now',
	'thank_you_message'    => array( 'thankYou' => 'Thanks', 'done' => 'Sent.' ),
	'stateForm'            => 0,
	'notification_rules'   => array( array( 'recipient' => $secret, 'cc' => 'cc@example.com', 'bcc' => 'bcc@example.com', 'subject' => 'Secret subject' ) ),
	'webhook_rules'        => array( array( 'url' => 'https://hooks.example/secret', 'method' => 'POST', 'payload_fields' => array( 'name' ) ) ),
	'confirmation_rules'   => array( array( 'url' => 'https://example.com/secret-redirect', 'action' => 'redirect' ) ),
	'rePage'               => 'https://example.com/secret-redirect',
	'telegram_bot_token'   => '123456:secret-token',
	'telegram_admin_chat_ids' => '999001',
	'sms_admins_phone_no'  => '+15550001111',
	'logic_rules'          => array( array( 'scope' => 'notification', 'id' => 'n1' ) ),
);
$fields = array(
	array( 'id' => 'name', 'type' => 'text', 'name' => 'Name', 'required' => true, 'placeholder' => 'Your name' ),
	array( 'id' => 'mail', 'type' => 'email', 'name' => 'Email', 'required' => true ),
	array( 'id' => 'note', 'type' => 'textarea', 'name' => 'Note', 'required' => false ),
	array( 'id' => 'file', 'type' => 'file', 'name' => 'File', 'required' => false ),
	array( 'id' => 'hide', 'type' => 'text', 'name' => 'Hidden secret', 'hidden' => true, 'required' => true ),
	array( 'id' => 'lock', 'type' => 'text', 'name' => 'Locked', 'disabled' => true ),
	array(
		'id'       => 'pick',
		'type'     => 'select',
		'name'     => 'Pick',
		'required' => false,
		'options'  => array( array( 'value' => 'a', 'label' => 'Choice A', 'registered_count' => 4, 'mlen' => 9, 'dateExp' => '2099-01-01' ) ),
	),
);
$public_id  = flxf_t_form( 'Contact', 'form', array_merge( array( $head ), $fields ) );
$private    = $head;
$private['stateForm'] = 1;
$private_id = flxf_t_form( 'Private', 'form', array( $private ) );
$login_id   = flxf_t_form( 'Sign in', 'login', array( array( 'type' => 'login', 'stateForm' => 0, 'button_single_text' => 'Go' ), array( 'id' => 'user', 'type' => 'text', 'name' => 'User', 'required' => true ) ) );

add_filter(
	'flxf_gql_rate_max',
	static function () {
		return 400;
	}
);

$r = flxf_t_op( 'FlxForm', array( 'id' => (string) $public_id ) );
flxt_same( null, $r['errors'] ?? null, 'public form: no errors' );
$form = $r['data']['flxForm'] ?? null;
flxt_same( 'Contact', is_array( $form ) ? ( $form['title'] ?? null ) : null, 'public form: title' );
flxt_same( 'FORM', is_array( $form ) ? ( $form['formType'] ?? null ) : null, 'public form: type' );
flxt_same( 'Send now', is_array( $form ) ? ( $form['buttonLabel'] ?? null ) : null, 'public form: button' );
$ids = array();
foreach ( is_array( $form ) ? ( $form['fields'] ?? array() ) : array() as $field ) {
	if ( is_array( $field ) ) {
		$ids[] = $field['id'] ?? '';
	}
}
flxt_same( array( 'name', 'mail', 'note', 'file', 'pick' ), $ids, 'public form: hidden and disabled fields are omitted' );
$keys = array();
flxf_t_keys( $r['data'] ?? array(), $keys );
$forbidden = array_merge( FLXF_Form_Service::FORBIDDEN_HEAD, FLXF_Form_Service::FORBIDDEN_FIELD );
flxt_same( array(), array_values( array_intersect( $forbidden, $keys ) ), 'public form: forbidden keys are absent' );
$blob = (string) wp_json_encode( $r['data'] ?? array() );
flxt_ok( ! str_contains( $blob, $secret ), 'public form: notification address is absent' );
flxt_ok( ! str_contains( $blob, 'secret-token' ), 'public form: telegram token is absent' );
flxt_ok( ! str_contains( $blob, 'hooks.example' ), 'public form: webhook url is absent' );
flxt_ok( ! str_contains( $blob, '2099-01-01' ), 'public form: booking expiry is absent' );
flxt_ok( ! str_contains( $blob, 'registered_count' ), 'public form: booking counter name is absent' );

$r = flxf_t_op( 'FlxForm', array( 'id' => (string) $private_id ) );
flxt_same( null, array_key_exists( 'flxForm', $r['data'] ?? array() ) ? $r['data']['flxForm'] : null, 'private form: visitor gets null' );
flxt_same( null, $r['errors'] ?? null, 'private form: not an error' );
$r = flxf_t_op( 'FlxForm', array( 'id' => (string) $private_id ), 1 );
flxt_same( 'Private', $r['data']['flxForm']['title'] ?? null, 'private form: a signed-in visitor can read it' );
$r = flxf_t_op( 'FlxForm', array( 'id' => '999999' ) );
flxt_same( null, array_key_exists( 'flxForm', $r['data'] ?? array() ) ? $r['data']['flxForm'] : null, 'missing form: null' );

$filled = array(
	array( 'fieldId' => 'name', 'value' => 'Ada' ),
	array( 'fieldId' => 'mail', 'value' => 'ada@example.com' ),
	array( 'fieldId' => 'pick', 'value' => 'a' ),
);
$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $public_id, 'fields' => $filled, 'honeypot' => '' ) ) );
flxt_same( array(), flxf_t_errs( $r, 'flxFormSubmit' ), 'submit: no field errors' );
$track = $r['data']['flxFormSubmit']['submission']['trackCode'] ?? '';
flxt_ok( is_string( $track ) && 12 === strlen( $track ), 'submit: tracking code', $track );
flxt_ok( (int) ( $r['data']['flxFormSubmit']['submission']['id'] ?? 0 ) > 0, 'submit: row id' );

$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $public_id, 'fields' => array( array( 'fieldId' => 'name', 'value' => '' ) ), 'honeypot' => '' ) ) );
$errs = flxf_t_errs( $r, 'flxFormSubmit' );
flxt_same( 'INVALID_INPUT', $errs[0][0] ?? '', 'submit: missing required field' );
flxt_ok( str_starts_with( (string) ( $errs[0][1] ?? '' ), 'fields.' ), 'submit: error points at a field', $errs );

$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $public_id, 'fields' => $filled, 'honeypot' => 'http://spam.example' ) ) );
flxt_same( array( array( 'INVALID_INPUT', 'honeypot' ) ), flxf_t_errs( $r, 'flxFormSubmit' ), 'submit: honeypot' );
$payload = is_array( $r['data']['flxFormSubmit'] ?? null ) ? $r['data']['flxFormSubmit'] : array();
flxt_same( null, array_key_exists( 'submission', $payload ) ? $payload['submission'] : null, 'submit: honeypot writes no row' );

$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $login_id, 'fields' => array( array( 'fieldId' => 'user', 'value' => 'ada' ) ) ) ) );
flxt_same( array( array( 'INVALID_INPUT', 'formId' ) ), flxf_t_errs( $r, 'flxFormSubmit' ), 'submit: login stays on REST' );

$gif  = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff\x21\xf9\x04\x01\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3b";
$bits = wp_upload_bits( 'not-pending.gif', null, $gif );
$r    = flxf_t_op(
	'FlxFormSubmit',
	array(
		'input' => array(
			'formId' => (string) $public_id,
			'fields' => array_merge( $filled, array( array( 'fieldId' => 'file', 'value' => (string) ( $bits['url'] ?? '' ) ) ) ),
		),
	)
);
flxt_same( array( array( 'INVALID_INPUT', 'fields.file' ) ), flxf_t_errs( $r, 'flxFormSubmit' ), 'submit: arbitrary uploads URL is rejected' );

$ok_bits = wp_upload_bits( 'efb-PLG-contract.gif', null, $gif );
$pending = get_option( 'emsfb_pending_uploads', array() );
$pending = is_array( $pending ) ? $pending : array();
$pending[ wp_basename( (string) ( $ok_bits['file'] ?? '' ) ) ] = time();
update_option( 'emsfb_pending_uploads', $pending, false );
$r = flxf_t_op(
	'FlxFormSubmit',
	array(
		'input' => array(
			'formId' => (string) $public_id,
			'fields' => array_merge( $filled, array( array( 'fieldId' => 'file', 'value' => (string) ( $ok_bits['url'] ?? '' ) ) ) ),
		),
	)
);
flxt_same( array(), flxf_t_errs( $r, 'flxFormSubmit' ), 'submit: a file reserved by the upload endpoint is accepted' );
$tracked = (string) ( $r['data']['flxFormSubmit']['submission']['trackCode'] ?? $track );

$r = flxf_t_op( 'FlxFormTrack', array( 'code' => $tracked ) );
flxt_same( null, $r['errors'] ?? null, 'track: valid code' );
flxt_same( 'Ada', $r['data']['flxFormTrack']['fields'][0]['value'] ?? null, 'track: first answer' );
flxt_ok( is_string( $r['data']['flxFormTrack']['submittedAt']['iso'] ?? null ) && '' !== ( $r['data']['flxFormTrack']['submittedAt']['iso'] ?? '' ), 'track: submittedAt iso' );
flxt_ok( (int) ( $r['data']['flxFormTrack']['submittedAt']['timestamp'] ?? 0 ) > 0, 'track: submittedAt timestamp' );
$track_labels = array_column( is_array( $r['data']['flxFormTrack']['fields'] ?? null ) ? $r['data']['flxFormTrack']['fields'] : array(), 'value' );
flxt_ok( in_array( (string) ( $ok_bits['url'] ?? '' ), $track_labels, true ), 'track: file answer unwraps @file@ to url' );

global $wpdb;
$saved_content = (string) $wpdb->get_var( $wpdb->prepare( "SELECT content FROM {$wpdb->prefix}emsfb_msg_ WHERE track = %s LIMIT 1", $tracked ) );
$saved_rows    = json_decode( str_replace( '\\', '', $saved_content ), true );
flxt_ok( is_array( $saved_rows ), 'submit: stored message decodes via classic stripslashes' );
$file_rows = array_values( array_filter( is_array( $saved_rows ) ? $saved_rows : array(), static fn( $item ) => is_array( $item ) && 'file' === ( $item['id_'] ?? '' ) ) );
flxt_same( '@file@', $file_rows[0]['value'] ?? null, 'submit: file row stores @file@ value' );
flxt_same( (string) ( $ok_bits['url'] ?? '' ), $file_rows[0]['url'] ?? null, 'submit: file row stores url key' );
$last_row = is_array( $saved_rows ) && $saved_rows ? end( $saved_rows ) : array();
flxt_same( 'w_link', is_array( $last_row ) ? ( $last_row['type'] ?? null ) : null, 'submit: trailing row is classic w_link' );
$field_values_only = array_column( is_array( $r['data']['flxFormTrack']['fields'] ?? null ) ? $r['data']['flxFormTrack']['fields'] : array(), 'value' );
flxt_ok( ! in_array( home_url(), $field_values_only, true ), 'track: w_link marker is omitted from answers' );

$r = flxf_t_op( 'FlxFormTrack', array( 'code' => 'not-a-code' ) );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'track: invalid code' );

$r = flxf_t_op( 'FlxFormTrackReply', array( 'input' => array( 'trackCode' => $tracked, 'message' => 'Following up' ) ) );
flxt_same( array(), flxf_t_errs( $r, 'flxFormTrackReply' ), 'reply: stored' );
$r = flxf_t_op( 'FlxFormTrack', array( 'code' => $tracked ) );
flxt_same( 'Following up', $r['data']['flxFormTrack']['replies'][0]['body'] ?? null, 'reply: visible on the track query' );
flxt_ok( (int) ( $r['data']['flxFormTrack']['replies'][0]['createdAt']['timestamp'] ?? 0 ) > 0, 'reply: createdAt timestamp' );
$r = flxf_t_op( 'FlxFormTrackReply', array( 'input' => array( 'trackCode' => $tracked, 'message' => 'See file', 'attachments' => array( (string) ( $bits['url'] ?? '' ) ) ) ) );
flxt_same( array( array( 'INVALID_INPUT', 'attachments' ) ), flxf_t_errs( $r, 'flxFormTrackReply' ), 'reply: unreserved attachment is rejected' );

// Configure plugin settings for reply adminCode + submit/reply side-effect tests.
$email_key = 'contractemailkey123';
flxf_t_settings( array() );

// Reply with reserved attachment + adminCode + quote handling, and classic-format reply interop.
$tracked_msg_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT msg_id FROM {$wpdb->prefix}emsfb_msg_ WHERE track = %s LIMIT 1", $tracked ) );
$reply_bits     = wp_upload_bits( 'efb-PLG-reply.gif', null, $gif );
$reply_url      = (string) ( $reply_bits['url'] ?? '' );
flxt_ok( \Emsfb\Upload_Guard::reserve_response_attachment( $reply_url, $tracked_msg_id, $tracked ), 'reply: reserve attachment for ticket' );

$r = flxf_t_op(
	'FlxFormTrackReply',
	array(
		'input' => array(
			'trackCode' => $tracked,
			'message'   => 'Bad admin code',
			'adminCode' => 'deadbeef0000',
		),
	)
);
flxt_same( array( array( 'INVALID_INPUT', 'adminCode' ) ), flxf_t_errs( $r, 'flxFormTrackReply' ), 'reply: wrong adminCode is rejected' );

$r = flxf_t_op(
	'FlxFormTrackReply',
	array(
		'input' => array(
			'trackCode'   => $tracked,
			'message'     => 'Admin "quoted" reply',
			'attachments' => array( $reply_url ),
			'adminCode'   => md5( $tracked . $email_key ),
		),
	)
);
flxt_same( array(), flxf_t_errs( $r, 'flxFormTrackReply' ), 'reply: adminCode with reserved attachment is accepted' );
$rsp_id  = (int) ( $r['data']['flxFormTrackReply']['submission']['id'] ?? 0 );
$rsp_row = $wpdb->get_row( $wpdb->prepare( "SELECT content, read_ FROM {$wpdb->prefix}emsfb_rsp_ WHERE rsp_id = %d LIMIT 1", $rsp_id ), ARRAY_A );
flxt_same( '1', (string) ( $rsp_row['read_'] ?? '' ), 'reply: adminCode marks reply read' );
$rsp_decoded = json_decode( str_replace( '\\', '', (string) ( $rsp_row['content'] ?? '' ) ), true );
flxt_ok( is_array( $rsp_decoded ) && isset( $rsp_decoded[0]['id_'], $rsp_decoded[1]['id_'] ), 'reply: stored as classic JSON array' );
flxt_same( '@file@', $rsp_decoded[1]['value'] ?? null, 'reply: stored attachment uses @file@' );
flxt_same( $reply_url, $rsp_decoded[1]['url'] ?? null, 'reply: stored attachment includes url' );

$classic_rsp_payload = str_replace(
	'"',
	'\\"',
	(string) wp_json_encode(
		array(
			array( 'id_' => 'message', 'name' => 'Message', 'type' => 'text', 'value' => 'Classic response text' ),
			array( 'id_' => 'file', 'name' => 'File', 'type' => 'allformat', 'value' => '@file@', 'url' => 'https://example.com/classic-attachment.pdf' ),
		)
	)
);
$wpdb->insert(
	$wpdb->prefix . 'emsfb_rsp_',
	array(
		'msg_id'    => $tracked_msg_id,
		'ip'        => '127.0.0.1',
		'content'   => $classic_rsp_payload,
		'date'      => current_time( 'mysql' ),
		'read_by'   => '',
		'read_'     => 0,
		'reader_ip' => '',
		'rsp_by'    => 0,
	)
);
$r = flxf_t_op( 'FlxFormTrack', array( 'code' => $tracked ) );
flxt_same( array( $reply_url ), $r['data']['flxFormTrack']['replies'][1]['attachments'] ?? null, 'track: GraphQL reply attachments returned' );
flxt_same( 'Classic response text', $r['data']['flxFormTrack']['replies'][2]['body'] ?? null, 'track: reads classic JSON array reply body' );
flxt_same( array( 'https://example.com/classic-attachment.pdf' ), $r['data']['flxFormTrack']['replies'][2]['attachments'] ?? null, 'track: reads classic JSON array reply attachments' );

// Builder / block-converter form shape + validation + booking + side effects.
$b_head = array(
	'type'               => 'form',
	'steps'              => 1,
	'formName'           => 'Builder Form',
	'email'              => 'formowner@example.com',
	'email_to'           => 'bemail',
	'sendEmail'          => true,
	'trackingCode'       => true,
	'booking'            => 1,
	'button_single_text' => 'Submit',
	'thank_you_message'  => array( 'thankYou' => 'Done', 'done' => 'Saved' ),
	'stateForm'          => 0,
);
$b_rows = array(
	$b_head,
	array( 'id_' => '1', 'type' => 'step', 'name' => 'Step 1' ),
	array( 'id_' => 'bname', 'type' => 'text', 'name' => 'Name', 'required' => 1, 'milen' => 3, 'mlen' => 12 ),
	array( 'id_' => 'bemail', 'type' => 'email', 'name' => 'Email', 'required' => 1, 'noti' => 1 ),
	array( 'id_' => 'burl', 'type' => 'url', 'name' => 'Website', 'required' => 0, 'mlen' => 40 ),
	array( 'id_' => 'bcolor', 'type' => 'color', 'name' => 'Color', 'required' => 0 ),
	array( 'id_' => 'bdate', 'type' => 'date', 'name' => 'Date', 'required' => 0, 'milen' => '2025-01-01', 'mlen' => '2025-12-31' ),
	array( 'id_' => 'brange', 'type' => 'range', 'name' => 'Range', 'required' => 0, 'milen' => 1, 'mlen' => 10 ),
	array( 'id_' => 'bnum', 'type' => 'number', 'name' => 'Code', 'required' => 0, 'milen' => 2, 'mlen' => 4 ),
	array( 'id_' => 'brat', 'type' => 'rating', 'name' => 'Rating', 'required' => 0 ),
	array( 'id_' => 'bp5', 'type' => 'pointr5', 'name' => 'Score 5', 'required' => 0 ),
	array( 'id_' => 'bp10', 'type' => 'pointr10', 'name' => 'NPS', 'required' => 0 ),
	array( 'id_' => 'bpick', 'type' => 'select', 'name' => 'Slot', 'required' => 0 ),
	array( 'id_' => 'opt_red', 'type' => 'option', 'parent' => 'bpick', 'value' => 'Red', 'mlen' => 1, 'registered_count' => 0, 'dateExp' => '2099-01-01' ),
	array( 'id_' => 'opt_exp', 'type' => 'option', 'parent' => 'bpick', 'value' => 'Expired', 'mlen' => 5, 'registered_count' => 0, 'dateExp' => '2000-01-01' ),
	array( 'id_' => 'bmulti', 'type' => 'multiselect', 'name' => 'Tags', 'required' => 0 ),
	array( 'id_' => 'opt_m1', 'type' => 'option', 'parent' => 'bmulti', 'value' => 'm1' ),
	array( 'id_' => 'opt_m2', 'type' => 'option', 'parent' => 'bmulti', 'value' => 'm2' ),
	array( 'id_' => 'bmtx', 'type' => 'table_matrix', 'name' => 'Matrix', 'required' => 0 ),
	array( 'id_' => 'rm1', 'type' => 'r_matrix', 'parent' => 'bmtx', 'value' => 'Row 1' ),
	array( 'id_' => 'bpdf', 'type' => 'file', 'name' => 'PDF Only', 'required' => 0, 'file' => 'customize', 'file_ctype' => 'pdf', 'max_fsize' => 1 ),
	array( 'id_' => 'brec', 'type' => 'audio_recorder', 'name' => 'Voice', 'required' => 0 ),
);
$builder_id = flxf_t_form( 'Builder Form', 'form', $b_rows );

$r      = flxf_t_op( 'FlxForm', array( 'id' => (string) $builder_id ) );
$b_form = $r['data']['flxForm'] ?? array();
$b_fids = array();
$b_opts = array();
foreach ( is_array( $b_form['fields'] ?? null ) ? $b_form['fields'] : array() as $bf ) {
	if ( is_array( $bf ) ) {
		$b_fids[] = $bf['id'] ?? '';
		if ( 'bpick' === ( $bf['id'] ?? '' ) ) {
			$b_opts = $bf['options'] ?? array();
		}
	}
}
flxt_same( array( 'bname', 'bemail', 'burl', 'bcolor', 'bdate', 'brange', 'bnum', 'brat', 'bp5', 'bp10', 'bpick', 'bmulti', 'bmtx', 'bpdf', 'brec' ), $b_fids, 'builder form: option, r_matrix and step rows do not leak into fields' );
flxt_same( array( array( 'value' => 'Red', 'label' => 'Red' ), array( 'value' => 'Expired', 'label' => 'Expired' ) ), $b_opts, 'builder form: sibling option rows attach to parent choice field' );

$b_base = array(
	array( 'fieldId' => 'bname', 'value' => 'Grace' ),
	array( 'fieldId' => 'bemail', 'value' => 'grace@example.com' ),
);
$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $builder_id, 'fields' => array() ) ) );
flxt_same( array( array( 'INVALID_INPUT', 'fields.bname' ), array( 'INVALID_INPUT', 'fields.bemail' ) ), flxf_t_errs( $r, 'flxFormSubmit' ), 'validation: required fields rejected when empty' );

$opt_only_id = flxf_t_form( 'Optional Only', 'form', array( array( 'type' => 'form', 'stateForm' => 0 ), array( 'id_' => 'optnote', 'type' => 'text', 'name' => 'Note', 'required' => 0 ) ) );
$r           = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $opt_only_id, 'fields' => array() ) ) );
flxt_same( array( array( 'INVALID_INPUT', 'fields' ) ), flxf_t_errs( $r, 'flxFormSubmit' ), 'validation: empty fields rejected' );

$invalid_cases = array(
	'bname'  => array( array( 'fieldId' => 'bname', 'value' => 'Hi' ), array( 'fieldId' => 'bemail', 'value' => 'grace@example.com' ) ),
	'burl'   => array_merge( $b_base, array( array( 'fieldId' => 'burl', 'value' => 'https://example.com/' . str_repeat( 'a', 40 ) ) ) ),
	'bcolor' => array_merge( $b_base, array( array( 'fieldId' => 'bcolor', 'value' => 'red' ) ) ),
	'bdate'  => array_merge( $b_base, array( array( 'fieldId' => 'bdate', 'value' => '2024-05-01' ) ) ),
	'brange' => array_merge( $b_base, array( array( 'fieldId' => 'brange', 'value' => '99' ) ) ),
	'bnum'   => array_merge( $b_base, array( array( 'fieldId' => 'bnum', 'value' => '1' ) ) ),
	'brat'   => array_merge( $b_base, array( array( 'fieldId' => 'brat', 'value' => '6' ) ) ),
	'bp5'    => array_merge( $b_base, array( array( 'fieldId' => 'bp5', 'value' => '0' ) ) ),
	'bp10'   => array_merge( $b_base, array( array( 'fieldId' => 'bp10', 'value' => '11' ) ) ),
	'bpick'  => array_merge( $b_base, array( array( 'fieldId' => 'bpick', 'value' => 'Blue' ) ) ),
);
foreach ( $invalid_cases as $fid => $case_fields ) {
	$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $builder_id, 'fields' => $case_fields ) ) );
	flxt_same( array( array( 'INVALID_INPUT', 'fields.' . $fid ) ), flxf_t_errs( $r, 'flxFormSubmit' ), 'validation: rejects invalid ' . $fid );
}

$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $builder_id, 'fields' => array_merge( $b_base, array( array( 'fieldId' => 'bpick', 'value' => 'Expired' ) ) ) ) ) );
flxt_same( array( array( 'INVALID_INPUT', 'fields.bpick' ) ), flxf_t_errs( $r, 'flxFormSubmit' ), 'booking: rejects expired choice' );

// Upload guard check on a restricted field and recorder field using a pending GIF.
$gif2_bits = wp_upload_bits( 'efb-PLG-wrongtype.gif', null, $gif );
$pending   = get_option( 'emsfb_pending_uploads', array() );
$pending   = is_array( $pending ) ? $pending : array();
$pending[ wp_basename( (string) ( $gif2_bits['file'] ?? '' ) ) ] = time();
update_option( 'emsfb_pending_uploads', $pending, false );

$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $builder_id, 'fields' => array_merge( $b_base, array( array( 'fieldId' => 'bpdf', 'value' => (string) ( $gif2_bits['url'] ?? '' ) ) ) ) ) ) );
flxt_same( array( array( 'INVALID_INPUT', 'fields.bpdf' ) ), flxf_t_errs( $r, 'flxFormSubmit' ), 'upload guard: rejects disallowed extension for field' );

$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $builder_id, 'fields' => array_merge( $b_base, array( array( 'fieldId' => 'brec', 'value' => (string) ( $gif2_bits['url'] ?? '' ) ) ) ) ) ) );
flxt_same( array( array( 'INVALID_INPUT', 'fields.brec' ) ), flxf_t_errs( $r, 'flxFormSubmit' ), 'upload guard: rejects non-audio file on audio_recorder' );

// Side effects: wp_mail, 3rd-party integration, survey transient clear, booking increment.
$sent_mails   = array();
$integrations = array();
$mail_spy     = static function ( $null, $atts ) use ( &$sent_mails ) {
	unset( $null );
	$sent_mails[] = $atts;
	return true;
};
$int_spy = static function ( $ctx ) use ( &$integrations ) {
	$integrations[] = $ctx;
};
add_filter( 'pre_wp_mail', $mail_spy, 10, 2 );
add_action( 'efb_after_form_integration', $int_spy, 10, 1 );
set_transient( 'emsfb_survey_' . $builder_id, array( 'cached' => 1 ), 120 );

$valid_builder_fields = array_merge(
	$b_base,
	array(
		array( 'fieldId' => 'burl', 'value' => 'https://example.com' ),
		array( 'fieldId' => 'bcolor', 'value' => '#ff00aa' ),
		array( 'fieldId' => 'bdate', 'value' => '2025-06-15' ),
		array( 'fieldId' => 'brange', 'value' => '5' ),
		array( 'fieldId' => 'bnum', 'value' => '42' ),
		array( 'fieldId' => 'brat', 'value' => '5' ),
		array( 'fieldId' => 'bp5', 'value' => '4' ),
		array( 'fieldId' => 'bp10', 'value' => '9' ),
		array( 'fieldId' => 'bpick', 'value' => 'Red' ),
		array( 'fieldId' => 'bmulti', 'value' => 'm1, m2, m1' ),
	)
);
$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $builder_id, 'fields' => $valid_builder_fields ) ) );
flxt_same( array(), flxf_t_errs( $r, 'flxFormSubmit' ), 'builder form: valid submission accepted' );
$builder_track = (string) ( $r['data']['flxFormSubmit']['submission']['trackCode'] ?? '' );

flxt_same( false, get_transient( 'emsfb_survey_' . $builder_id ), 'effects: survey transient cleared on submit' );
flxt_ok( count( $sent_mails ) >= 1, 'effects: wp_mail fires on submit', count( $sent_mails ) );
$mail_to_list = implode( ',', array_map( static fn( $m ) => is_array( $m['to'] ?? null ) ? implode( ',', $m['to'] ) : (string) ( $m['to'] ?? '' ), $sent_mails ) );
flxt_ok( str_contains( $mail_to_list, 'formowner@example.com' ) || str_contains( $mail_to_list, 'supporter@example.com' ), 'effects: admin email notified', $mail_to_list );
flxt_ok( str_contains( $mail_to_list, 'grace@example.com' ), 'effects: visitor email notified', $mail_to_list );
$mail_targets = array_map( static fn( $m ) => is_array( $m['to'] ?? null ) ? implode( ',', $m['to'] ) : (string) ( $m['to'] ?? '' ), $sent_mails );
flxt_same( 1, count( array_keys( $mail_targets, 'formowner@example.com', true ) ), 'effects: the form owner is mailed once on submit' );
flxt_same( 1, count( array_keys( $mail_targets, 'supporter@example.com', true ) ), 'effects: the settings support address is mailed once on submit' );
flxt_same( 1, count( array_keys( $mail_targets, 'grace@example.com', true ) ), 'effects: the visitor is mailed once on submit' );
flxt_ok( 3 === count( $sent_mails ), 'effects: a submit sends three mails and no more', $mail_targets );
flxt_same( 1, count( $integrations ), 'effects: efb_after_form_integration fires once on submit' );
flxt_same( 'form_submit', $integrations[0]['event_type'] ?? null, 'effects: integration event_type' );
flxt_same( $builder_track, $integrations[0]['track_code'] ?? null, 'effects: integration track_code' );

$b_msg_content = (string) $wpdb->get_var( $wpdb->prepare( "SELECT content FROM {$wpdb->prefix}emsfb_msg_ WHERE track = %s LIMIT 1", $builder_track ) );
$b_msg_rows    = json_decode( str_replace( '\\', '', $b_msg_content ), true );
$b_by_id       = array();
foreach ( is_array( $b_msg_rows ) ? $b_msg_rows : array() as $mr ) {
	if ( is_array( $mr ) && isset( $mr['id_'] ) ) {
		$b_by_id[ (string) $mr['id_'] ] = $mr;
	}
}
flxt_same( 'opt_red', $b_by_id['bpick']['id_ob'] ?? null, 'builder form: stored option carries id_ob' );
flxt_same( 'm1@efb!m2@efb!', $b_by_id['bmulti']['value'] ?? null, 'builder form: multiselect dedupes and uses @efb! delimiter' );

$updated_struct = json_decode( (string) $wpdb->get_var( $wpdb->prepare( "SELECT form_structer FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", $builder_id ) ), true );
$opt_red_count  = null;
foreach ( is_array( $updated_struct ) ? $updated_struct : array() as $sr ) {
	if ( is_array( $sr ) && 'opt_red' === ( $sr['id_'] ?? '' ) ) {
		$opt_red_count = (int) ( $sr['registered_count'] ?? 0 );
	}
}
flxt_same( 1, $opt_red_count, 'booking: registered_count increments after submit' );

$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $builder_id, 'fields' => array_merge( $b_base, array( array( 'fieldId' => 'bpick', 'value' => 'Red' ) ) ) ) ) );
flxt_same( array( array( 'INVALID_INPUT', 'fields.bpick' ) ), flxf_t_errs( $r, 'flxFormSubmit' ), 'booking: rejects choice once mlen quota is full' );

// Reply side effects + Human Shield veto.
$sent_mails   = array();
$integrations = array();
$r = flxf_t_op( 'FlxFormTrackReply', array( 'input' => array( 'trackCode' => $builder_track, 'message' => 'Follow up' ) ) );
flxt_same( array(), flxf_t_errs( $r, 'flxFormTrackReply' ), 'effects: reply succeeds' );
flxt_ok( count( $sent_mails ) >= 1, 'effects: wp_mail fires on reply' );
flxt_same( 'received_reply', $integrations[0]['event_type'] ?? null, 'effects: integration fires received_reply' );

$integrations = array();
$shield_veto  = static fn() => false;
add_filter( 'efb_shield_allow_side_effect', $shield_veto );
$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $builder_id, 'fields' => $b_base ) ) );
flxt_same( array(), flxf_t_errs( $r, 'flxFormSubmit' ), 'shield: submit succeeds when side effect vetoed' );
flxt_same( array(), $integrations, 'shield: efb_shield_allow_side_effect blocks integration dispatch' );
remove_filter( 'efb_shield_allow_side_effect', $shield_veto );
remove_filter( 'pre_wp_mail', $mail_spy, 10 );
remove_action( 'efb_after_form_integration', $int_spy, 10 );

// Gates and rules the GraphQL submit shares with the classic submit: form captcha, Human Shield,
// conditional logic, mobile rules and the track code style.
$msg_count = static function ( int $form_id ) use ( $wpdb ): int {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}emsfb_msg_ WHERE form_id = %d", $form_id ) );
};
$sent_mails = array();
add_filter( 'pre_wp_mail', $mail_spy, 10, 2 );

$cap_id = flxf_t_form(
	'Captcha form',
	'form',
	array(
		array( 'type' => 'form', 'steps' => 1, 'formName' => 'Captcha form', 'email' => 'owner@example.com', 'email_to' => 'cmail', 'captcha' => 1, 'stateForm' => 0 ),
		array( 'id_' => 'cname', 'type' => 'text', 'name' => 'Name', 'required' => 1 ),
		array( 'id_' => 'cmail', 'type' => 'email', 'name' => 'Email', 'required' => 1 ),
	)
);
$cap_fields = array(
	array( 'fieldId' => 'cname', 'value' => 'Ada' ),
	array( 'fieldId' => 'cmail', 'value' => 'ada@example.com' ),
);
flxf_t_settings( array( 'secretKey' => 'contract-recaptcha-secret' ) );
$GLOBALS['flxf_siteverify'] = true;
$verify_mock                = static function ( $pre, $args, $url ) {
	unset( $args );
	if ( ! str_contains( (string) $url, 'siteverify' ) ) {
		return $pre;
	}
	return array(
		'headers'  => array(),
		'body'     => (string) wp_json_encode( array( 'success' => (bool) $GLOBALS['flxf_siteverify'] ) ),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
		'filename' => null,
	);
};
add_filter( 'pre_http_request', $verify_mock, 10, 3 );
$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $cap_id, 'fields' => $cap_fields ) ) );
flxt_same( array( array( 'INVALID_INPUT', 'captchaToken' ) ), flxf_t_errs( $r, 'flxFormSubmit' ), 'form captcha: a form with captcha on refuses a missing token' );
flxt_same( 0, $msg_count( $cap_id ), 'form captcha: nothing is stored without a token' );
flxt_same( array(), $sent_mails, 'form captcha: no mail without a token' );
$GLOBALS['flxf_siteverify'] = false;
$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $cap_id, 'fields' => $cap_fields, 'captchaToken' => 'bad-token-value' ) ) );
flxt_same( array( array( 'INVALID_INPUT', 'captchaToken' ) ), flxf_t_errs( $r, 'flxFormSubmit' ), 'form captcha: Google refuses the token' );
flxt_same( 0, $msg_count( $cap_id ), 'form captcha: nothing is stored when Google refuses' );
flxt_same( array(), $sent_mails, 'form captcha: no mail when Google refuses' );
$GLOBALS['flxf_siteverify'] = true;
$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $cap_id, 'fields' => $cap_fields, 'captchaToken' => 'good-token-value' ) ) );
flxt_same( array(), flxf_t_errs( $r, 'flxFormSubmit' ), 'form captcha: a verified token is accepted' );
flxt_same( 1, $msg_count( $cap_id ), 'form captcha: the accepted submission is stored' );
flxf_t_settings( array( 'secretKey' => '' ) );
$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $cap_id, 'fields' => $cap_fields, 'captchaToken' => 'good-token-value' ) ) );
flxt_same( 'INTERNAL', flxt_code( $r ), 'form captcha: no secret key configured is refused' );
flxt_same( 1, $msg_count( $cap_id ), 'form captcha: no secret key stores nothing more' );
remove_filter( 'pre_http_request', $verify_mock, 10 );
flxf_t_settings( array() );

$bot_seen = array();
$bot_veto = static function ( $block, $ctx ) use ( &$bot_seen ) {
	unset( $block );
	$bot_seen[] = $ctx;
	return true;
};
add_filter( 'efb_submit_bot_decision', $bot_veto, 10, 2 );
$before_bot = $msg_count( $builder_id );
$sent_mails = array();
$r          = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $builder_id, 'fields' => $b_base ) ) );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'human shield: efb_submit_bot_decision veto refuses the submission' );
flxt_same( $before_bot, $msg_count( $builder_id ), 'human shield: nothing is stored on a veto' );
flxt_same( array(), $sent_mails, 'human shield: no mail on a veto' );
flxt_same( 'Builder Form', $bot_seen[0]['form']['formName'] ?? null, 'human shield: the filter gets the form head' );
flxt_ok( array_key_exists( 'ip', $bot_seen[0] ?? array() ) && array_key_exists( 'setting', $bot_seen[0] ?? array() ), 'human shield: the filter gets ip and settings' );
remove_filter( 'efb_submit_bot_decision', $bot_veto, 10 );

$rule_show  = array(
	'id'         => 'r_show',
	'scope'      => 'field',
	'conditions' => array( 'items' => array( array( 'source' => 'field', 'field_id' => 'ltype', 'compare' => 'is', 'value' => 'b' ) ) ),
	'actions'    => array( array( 'type' => 'show_field', 'target' => 'lhid' ) ),
);
$rule_block = array(
	'id'         => 'r_block',
	'scope'      => 'field',
	'conditions' => array( 'items' => array( array( 'source' => 'field', 'field_id' => 'ltype', 'compare' => 'is', 'value' => 'x' ) ) ),
	'actions'    => array( array( 'type' => 'block_submit', 'value' => 'Blocked by rule' ) ),
);
$logic_id   = flxf_t_form(
	'Logic form',
	'form',
	array(
		array( 'type' => 'form', 'steps' => 1, 'formName' => 'Logic form', 'email' => '', 'stateForm' => 0, 'logic_rules' => array( $rule_show, $rule_block ) ),
		array( 'id_' => 'ltype', 'type' => 'text', 'name' => 'Kind', 'required' => 1 ),
		array( 'id_' => 'lhid', 'type' => 'text', 'name' => 'Only for b', 'required' => 1 ),
	)
);
$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $logic_id, 'fields' => array( array( 'fieldId' => 'ltype', 'value' => 'a' ) ) ) ) );
flxt_same( array(), flxf_t_errs( $r, 'flxFormSubmit' ), 'logic: a required field hidden by a rule is accepted when empty' );
$logic_track = (string) ( $r['data']['flxFormSubmit']['submission']['trackCode'] ?? '' );
$r           = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $logic_id, 'fields' => array( array( 'fieldId' => 'ltype', 'value' => 'b' ) ) ) ) );
flxt_same( array( array( 'INVALID_INPUT', 'fields.lhid' ) ), flxf_t_errs( $r, 'flxFormSubmit' ), 'logic: the same field is required once the rule shows it' );
$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $logic_id, 'fields' => array( array( 'fieldId' => 'ltype', 'value' => 'b' ), array( 'fieldId' => 'lhid', 'value' => 'shown' ) ) ) ) );
flxt_same( array(), flxf_t_errs( $r, 'flxFormSubmit' ), 'logic: a shown required field is accepted when filled' );
$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $logic_id, 'fields' => array( array( 'fieldId' => 'ltype', 'value' => 'a' ), array( 'fieldId' => 'lhid', 'value' => 'leaked-value' ) ) ) ) );
flxt_same( array(), flxf_t_errs( $r, 'flxFormSubmit' ), 'logic: a value sent for a hidden field is dropped, not refused' );
$leak_track   = (string) ( $r['data']['flxFormSubmit']['submission']['trackCode'] ?? '' );
$leak_content = (string) $wpdb->get_var( $wpdb->prepare( "SELECT content FROM {$wpdb->prefix}emsfb_msg_ WHERE track = %s LIMIT 1", $leak_track ) );
flxt_ok( '' !== $leak_content && ! str_contains( $leak_content, 'leaked-value' ), 'logic: a hidden field value is never stored' );
$before_block = $msg_count( $logic_id );
$r            = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $logic_id, 'fields' => array( array( 'fieldId' => 'ltype', 'value' => 'x' ) ) ) ) );
flxt_same( array( array( 'INVALID_INPUT', 'fields' ) ), flxf_t_errs( $r, 'flxFormSubmit' ), 'logic: a block_submit rule refuses the submission' );
flxt_same( 'Blocked by rule', $r['data']['flxFormSubmit']['errors'][0]['message'] ?? null, 'logic: the refusal carries the rule message' );
flxt_same( $before_block, $msg_count( $logic_id ), 'logic: a blocked submission is not stored' );

// The classic REST submit refuses the same answers with the same messages: it runs the same code.
$rest_body = static fn( array $rows ): array => array(
	'sid'     => 'contractsid',
	'id'      => (string) $logic_id,
	'page_id' => '1',
	'url'     => 'http://example.org/page',
	'type'    => 'form',
	'name'    => 'Logic form',
	'valid'   => '',
	'value'   => (string) wp_json_encode( $rows ),
);
$rest = flxf_t_classic( 'get_form_public_efb', $rest_body( array( array( 'id_' => 'ltype', 'name' => 'Kind', 'type' => 'text', 'value' => 'x' ) ) ) );
flxt_same( 'Blocked by rule', $rest['data']['m'] ?? null, 'REST parity: a block_submit rule gives the same refusal' );
$rest = flxf_t_classic( 'get_form_public_efb', $rest_body( array( array( 'id_' => 'ltype', 'name' => 'Kind', 'type' => 'text', 'value' => 'b' ) ) ) );
flxt_same( 'lhid', $rest['data']['field_id'] ?? null, 'REST parity: a field a rule shows is required on REST too' );
flxt_same( $before_block, $msg_count( $logic_id ), 'REST parity: refused REST submissions store nothing' );

$phone_id = flxf_t_form(
	'Phone form',
	'form',
	array(
		array( 'type' => 'form', 'steps' => 1, 'formName' => 'Phone form', 'email' => '', 'stateForm' => 0 ),
		array( 'id_' => 'mob', 'type' => 'mobile', 'name' => 'Phone', 'required' => 0, 'c_n' => array( '90' ) ),
	)
);
$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $phone_id, 'fields' => array( array( 'fieldId' => 'mob', 'value' => '+1 555 111 2233' ) ) ) ) );
flxt_same( array( array( 'INVALID_INPUT', 'fields.mob' ) ), flxf_t_errs( $r, 'flxFormSubmit' ), 'mobile: a number outside the allowed countries is refused' );
$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $phone_id, 'fields' => array( array( 'fieldId' => 'mob', 'value' => '+90 555 111 2233' ) ) ) ) );
flxt_same( array(), flxf_t_errs( $r, 'flxFormSubmit' ), 'mobile: a number from an allowed country is accepted' );
$phone_track = (string) ( $r['data']['flxFormSubmit']['submission']['trackCode'] ?? '' );
flxt_ok( 1 === preg_match( '/^[0-9A-Z]{12}$/', $phone_track ), 'track code: the default style is 12 upper-case letters and digits', $phone_track );
flxf_t_settings( array( 'trackCodeStyle' => 'date_num' ) );
$r           = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $phone_id, 'fields' => array( array( 'fieldId' => 'mob', 'value' => '+905551112233' ) ) ) ) );
$digit_track = (string) ( $r['data']['flxFormSubmit']['submission']['trackCode'] ?? '' );
flxt_ok( 1 === preg_match( '/^[0-9]{12}$/', $digit_track ), 'track code: trackCodeStyle date_num gives 12 digits', $digit_track );
flxf_t_settings( array() );

// Classic read: the tracker the visitor page uses reads what GraphQL wrote.
$classic = flxf_t_classic( 'get_track_public_api', array( 'value' => $builder_track ) );
flxt_same( true, $classic['data']['success'] ?? null, 'classic tracker: finds a GraphQL submission' );
flxt_same( $builder_track, $classic['data']['value']['track'] ?? null, 'classic tracker: same tracking code' );
flxt_ok( str_contains( (string) ( $classic['data']['value']['content'] ?? '' ), 'Grace' ), 'classic tracker: returns the GraphQL answers' );
flxt_ok( str_contains( (string) ( $classic['data']['value']['content'] ?? '' ), 'w_link' ), 'classic tracker: the stored message carries the classic w_link row' );
flxt_same( 1, count( (array) ( $classic['data']['content'] ?? array() ) ), 'classic tracker: lists the reply GraphQL stored' );
// Classic write: the response handler answers a GraphQL ticket and GraphQL reads it back.
$msg_row = $wpdb->get_row( $wpdb->prepare( "SELECT msg_id FROM {$wpdb->prefix}emsfb_msg_ WHERE track = %s LIMIT 1", $builder_track ), ARRAY_A );
$sent_mails = array();
$reply      = flxf_t_classic(
	'set_rMessage_id_Emsfb_api',
	array(
		'user_type' => 'guest',
		'sc'        => 'null',
		'track'     => $builder_track,
		'id'        => (string) ( $msg_row['msg_id'] ?? 0 ),
		'message'   => '[{"id_":"message","name":"Message","type":"text","value":"Classic says hi"}]',
	)
);
flxt_ok( true === ( $reply['data']['success'] ?? null ), 'classic reply: the response handler accepts a reply on a GraphQL ticket', $reply );
$r = flxf_t_op( 'FlxFormTrack', array( 'code' => $builder_track ) );
$bodies = array_column( is_array( $r['data']['flxFormTrack']['replies'] ?? null ) ? $r['data']['flxFormTrack']['replies'] : array(), 'body' );
flxt_ok( in_array( 'Classic says hi', $bodies, true ), 'classic reply: GraphQL reads the reply the classic handler stored', $bodies );
remove_filter( 'pre_wp_mail', $mail_spy, 10 );

// Non-UTC timezone conversion for FlxDateTime on FlxFormTrack.
$old_tz = (string) get_option( 'timezone_string', '' );
update_option( 'timezone_string', 'Europe/Istanbul' );
$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $builder_id, 'fields' => $b_base ) ) );
$tz_track = (string) ( $r['data']['flxFormSubmit']['submission']['trackCode'] ?? '' );
flxf_t_op( 'FlxFormTrackReply', array( 'input' => array( 'trackCode' => $tz_track, 'message' => 'TZ check' ) ) );
$tz_msg_date = (string) $wpdb->get_var( $wpdb->prepare( "SELECT date FROM {$wpdb->prefix}emsfb_msg_ WHERE track = %s LIMIT 1", $tz_track ) );
$tz_msg_id   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT msg_id FROM {$wpdb->prefix}emsfb_msg_ WHERE track = %s LIMIT 1", $tz_track ) );
$tz_rsp_date = (string) $wpdb->get_var( $wpdb->prepare( "SELECT date FROM {$wpdb->prefix}emsfb_rsp_ WHERE msg_id = %d ORDER BY rsp_id DESC LIMIT 1", $tz_msg_id ) );
$r_tz        = flxf_t_op( 'FlxFormTrack', array( 'code' => $tz_track ) );
$want_sub_ts = strtotime( get_gmt_from_date( $tz_msg_date ) . ' UTC' );
$want_rsp_ts = strtotime( get_gmt_from_date( $tz_rsp_date ) . ' UTC' );
flxt_same( $want_sub_ts, $r_tz['data']['flxFormTrack']['submittedAt']['timestamp'] ?? null, 'timezone: submittedAt converts site-local MySQL date to UTC' );
flxt_same( $want_rsp_ts, $r_tz['data']['flxFormTrack']['replies'][0]['createdAt']['timestamp'] ?? null, 'timezone: reply createdAt converts site-local MySQL date to UTC' );
update_option( 'timezone_string', $old_tz );

// Track brute force uses classic legacy limiter.
$prev_remote              = $_SERVER['REMOTE_ADDR'] ?? null;
$_SERVER['REMOTE_ADDR']   = '198.51.100.77';
$legacy_max_filter        = static fn() => 2;
add_filter( 'efb_track_legacy_fail_max', $legacy_max_filter );
$r = flxf_t_op( 'FlxFormTrack', array( 'code' => 'bad1' ) );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'track rate limit: miss 1 allowed' );
$r = flxf_t_op( 'FlxFormTrack', array( 'code' => 'bad2' ) );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'track rate limit: miss 2 allowed' );
$r = flxf_t_op( 'FlxFormTrack', array( 'code' => 'bad3' ) );
flxt_same( 'RATE_LIMITED', flxt_code( $r ), 'track rate limit: miss 3 returns RATE_LIMITED' );
$r = flxf_t_op( 'FlxFormTrackReply', array( 'input' => array( 'trackCode' => 'bad4', 'message' => 'Throttled' ) ) );
flxt_same( 'RATE_LIMITED', flxt_code( $r ), 'track rate limit: replies share the tracker throttle' );
$_SERVER['REMOTE_ADDR'] = '198.51.100.78';
$r = flxf_t_op( 'FlxFormTrackReply', array( 'input' => array( 'trackCode' => 'bad1', 'message' => 'Guess' ) ) );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'reply rate limit: miss 1 allowed' );
$r = flxf_t_op( 'FlxFormTrackReply', array( 'input' => array( 'trackCode' => 'bad2', 'message' => 'Guess' ) ) );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'reply rate limit: miss 2 allowed' );
$r = flxf_t_op( 'FlxFormTrackReply', array( 'input' => array( 'trackCode' => 'bad3', 'message' => 'Guess' ) ) );
flxt_same( 'RATE_LIMITED', flxt_code( $r ), 'reply rate limit: wrong codes count against the caller like the tracker' );
remove_filter( 'efb_track_legacy_fail_max', $legacy_max_filter );
if ( null === $prev_remote ) {
	unset( $_SERVER['REMOTE_ADDR'] );
} else {
	$_SERVER['REMOTE_ADDR'] = $prev_remote;
}

if ( function_exists( 'flxrc_update_settings' ) && function_exists( 'flxrc_seal' ) && function_exists( 'flxrc_key_fingerprint' ) ) {
	$plain = 'contractsecret123456';
	flxrc_update_settings(
		array(
			'site_key'  => 'contractsite123456',
			'secret'    => flxrc_seal( $plain ),
			'fail_open' => 0,
			'forms'     => array( 'flxf_submit' => 1 ),
		)
	);
	$saved             = flxrc_settings();
	$saved['verified'] = flxrc_key_fingerprint();
	flxrc_update_settings( is_array( $saved ) ? $saved : array() );
	add_filter(
		'pre_http_request',
		static function ( $pre, $args, $url ) {
			unset( $args );
			if ( ! str_contains( (string) $url, 'siteverify' ) ) {
				return $pre;
			}
			return array(
				'headers'  => array(),
				'body'     => (string) wp_json_encode( array( 'success' => false, 'error-codes' => array( 'invalid-input-response' ) ) ),
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => null,
			);
		},
		10,
		3
	);
	$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $public_id, 'fields' => $filled, 'captchaToken' => 'bad-token' ) ) );
	flxt_same( array( array( 'INVALID_INPUT', 'captchaToken' ) ), flxf_t_errs( $r, 'flxFormSubmit' ), 'submit: captcha failure' );
	flxrc_update_settings( array( 'forms' => array( 'flxf_submit' => 0 ), 'verified' => '' ) );
} else {
	flxt_ok( false, 'Flux reCAPTCHA is active so captcha failure can be tested' );
}

add_filter(
	'flx_client_ip',
	static function () {
		return '203.0.113.50';
	},
	99
);
add_filter(
	'flxf_gql_rate_max',
	static function ( $max, $bucket ) {
		unset( $max );
		return 'flxf_submit' === $bucket ? 1 : 40;
	},
	20,
	2
);
$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $public_id, 'fields' => $filled ) ) );
flxt_same( array(), flxf_t_errs( $r, 'flxFormSubmit' ), 'rate limit: first submit is allowed' );
$r = flxf_t_op( 'FlxFormSubmit', array( 'input' => array( 'formId' => (string) $public_id, 'fields' => $filled ) ) );
flxt_same( 'RATE_LIMITED', flxt_code( $r ), 'rate limit: second submit' );

/* ---------- 2.6.0: admin screen (Dashboard, All forms, Responses) ---------- */
$adm_id = wp_insert_user( array( 'user_login' => 'flxf_t_admin', 'user_pass' => wp_generate_password(), 'role' => 'administrator', 'user_email' => 'flxf-admin@example.com' ) );
$sub_id = wp_insert_user( array( 'user_login' => 'flxf_t_sub', 'user_pass' => wp_generate_password(), 'role' => 'subscriber', 'user_email' => 'flxf-sub@example.com' ) );
$adm_id = is_wp_error( $adm_id ) ? (int) get_user_by( 'login', 'flxf_t_admin' )->ID : (int) $adm_id;
$sub_id = is_wp_error( $sub_id ) ? (int) get_user_by( 'login', 'flxf_t_sub' )->ID : (int) $sub_id;

$r = flxf_t_op( 'FlxFormsAdminForms', array( 'first' => 50 ), $sub_id );
flxt_ok( null !== flxt_code( $r ) || empty( $r['data']['flxFormsAdminForms'] ), 'admin forms: a subscriber is refused' );
$r = flxf_t_op( 'FlxFormsAdminForms', array( 'first' => 50 ), $adm_id );
$rows = $r['data']['flxFormsAdminForms']['nodes'] ?? array();
$row  = null;
foreach ( $rows as $x ) {
	if ( (int) $x['databaseId'] === $public_id ) {
		$row = $x;
	}
}
flxt_ok( is_array( $row ), 'admin forms: the contact form is listed', $r );
flxt_same( '[EMS_Form_Builder id=' . $public_id . ']', $row['shortcode'] ?? '', 'admin forms: shortcode' );
flxt_ok( ( $row['responses'] ?? 0 ) >= 1, 'admin forms: response count' );
$r = flxf_t_op( 'FlxFormsAdminForms', array( 'first' => 50, 'search' => 'Contact' ), $adm_id );
flxt_same( array( 'Contact' ), array_values( array_unique( array_column( $r['data']['flxFormsAdminForms']['nodes'] ?? array(), 'title' ) ) ), 'admin forms: search by name' );

$r = flxf_t_op( 'FlxFormsDashboard', array(), $adm_id );
flxt_ok( isset( $r['data']['flxFormsDashboard']['totals']['n'], $r['data']['flxFormsDashboard']['csvUrl'] ), 'dashboard: totals and CSV link', $r );
$r = flxf_t_op( 'FlxFormsFormOptions', array(), $adm_id );
flxt_ok( in_array( $public_id, array_column( $r['data']['flxFormsFormOptions'] ?? array(), 'databaseId' ), true ), 'form options: contact form' );

$r       = flxf_t_op( 'FlxFormsEntries', array( 'first' => 2, 'form' => $public_id ), $adm_id );
$entries = $r['data']['flxFormsEntries'] ?? array();
flxt_ok( count( $entries['nodes'] ?? array() ) >= 1, 'entries: listed by form', $r );
$entry_id = (int) ( $entries['nodes'][0]['databaseId'] ?? 0 );
if ( ( $entries['totalCount'] ?? 0 ) > 2 ) {
	flxt_ok( ! empty( $entries['pageInfo']['hasNextPage'] ), 'entries: next page' );
}

$r = flxf_t_op( 'FlxFormsEntriesMark', array( 'input' => array( 'ids' => array( $entry_id ), 'read' => true ) ), $adm_id );
flxt_same( array(), flxf_t_errs( $r, 'flxFormsEntriesMark' ), 'entries: mark read' );
$r = flxf_t_op( 'FlxFormsEntriesMark', array( 'input' => array( 'ids' => array( $entry_id ), 'read' => false ) ), $adm_id );
flxt_same( 1, $r['data']['flxFormsEntriesMark']['changed'] ?? null, 'entries: mark unread' );
$r = flxf_t_op( 'FlxFormsEntries', array( 'first' => 50, 'form' => $public_id, 'status' => 'unread' ), $adm_id );
flxt_ok( in_array( $entry_id, array_column( $r['data']['flxFormsEntries']['nodes'] ?? array(), 'databaseId' ), true ), 'entries: unread filter' );

$r = flxf_t_op( 'FlxFormsEntry', array( 'id' => $entry_id ), $adm_id );
flxt_ok( ! empty( $r['data']['flxFormsEntry']['fields'] ), 'entry: fields', $r );
$r = flxf_t_op( 'FlxFormsEntryReply', array( 'input' => array( 'id' => $entry_id, 'message' => 'Thanks "friend"' ) ), $adm_id );
$replies = $r['data']['flxFormsEntryReply']['entry']['replies'] ?? array();
$last    = end( $replies );
flxt_ok( is_array( $last ) && ! empty( $last['byStaff'] ) && false !== strpos( (string) $last['body'], 'friend' ), 'entry: staff reply', $r );
$r = flxf_t_op( 'FlxFormsEntryReply', array( 'input' => array( 'id' => $entry_id, 'message' => '', 'close' => true ) ), $adm_id );
flxt_same( true, $r['data']['flxFormsEntryReply']['entry']['closed'] ?? null, 'entry: close without text' );
$r = flxf_t_op( 'FlxFormsEntryReply', array( 'input' => array( 'id' => $entry_id, 'message' => '' ) ), $adm_id );
flxt_same( array( array( 'INVALID_INPUT', 'message' ) ), flxf_t_errs( $r, 'flxFormsEntryReply' ), 'entry: empty reply refused' );

$r      = flxf_t_op( 'FlxFormsFormDuplicate', array( 'input' => array( 'id' => $public_id ) ), $adm_id );
$copy   = $r['data']['flxFormsFormDuplicate']['form'] ?? array();
$copied = (int) ( $copy['databaseId'] ?? 0 );
flxt_ok( $copied > 0 && $copied !== $public_id, 'form: duplicate', $r );
$r = flxf_t_op( 'FlxFormsFormDelete', array( 'input' => array( 'id' => $copied ) ), $adm_id );
flxt_same( true, $r['data']['flxFormsFormDelete']['deleted'] ?? null, 'form: delete the copy' );
$r = flxf_t_op( 'FlxFormsEntriesDelete', array( 'input' => array( 'ids' => array( $entry_id ) ) ), $adm_id );
flxt_same( 1, $r['data']['flxFormsEntriesDelete']['deleted'] ?? null, 'entries: delete' );
$r = flxf_t_op( 'FlxFormsEntry', array( 'id' => $entry_id ), $adm_id );
flxt_ok( empty( $r['data']['flxFormsEntry'] ), 'entry: gone after delete', $r );

/* ---------- 2.7.0: settings ---------- */
$r = flxf_t_op( 'FlxFormsSettings', array(), $sub_id );
flxt_ok( null !== flxt_code( $r ), 'settings: a subscriber is refused' );
$r = flxf_t_op( 'FlxFormsSettings', array(), $adm_id );
flxt_ok( isset( $r['data']['flxFormsSettings']['sessionDuration'] ), 'settings: read', $r );
$r = flxf_t_op(
	'FlxFormsSettingsUpdate',
	array(
		'input' => array(
			'sessionDuration' => 4,
			'trackCodeStyle'  => 'unique_num',
			'respPrimary'     => '#123456',
			'adminEmail'      => 'forms-admin@example.com',
			'secretKey'       => 'secret-xyz',
			'devMode'         => false,
		),
	),
	$adm_id
);
$st = $r['data']['flxFormsSettingsUpdate']['settings'] ?? array();
flxt_same( array( 4, 'unique_num', '#123456', 'forms-admin@example.com', true ), array( $st['sessionDuration'] ?? null, $st['trackCodeStyle'] ?? null, $st['respPrimary'] ?? null, $st['adminEmail'] ?? null, $st['secretKeySet'] ?? null ), 'settings: save', $r );
flxt_ok( false === strpos( (string) wp_json_encode( $r ), 'secret-xyz' ), 'settings: secret is not returned' );
$dec = get_setting_Emsfb( 'decoded' );
flxt_same( array( '4', '#123456', 'secret-xyz', 1 ), array( (string) ( $dec->sessionDuration ?? '' ), $dec->respPrimary ?? '', $dec->secretKey ?? '', (int) ( $dec->package_type ?? 0 ) ), 'settings: classic row has the values' );
$r = flxf_t_op( 'FlxFormsSettingsUpdate', array( 'input' => array( 'sessionDuration' => 9 ) ), $adm_id );
flxt_same( array( array( 'INVALID_INPUT', 'sessionDuration' ) ), flxf_t_errs( $r, 'flxFormsSettingsUpdate' ), 'settings: days out of range' );
$r = flxf_t_op( 'FlxFormsSettingsUpdate', array( 'input' => array( 'emailTemplate' => '<p>No shortcode</p>' ) ), $adm_id );
flxt_same( array( array( 'INVALID_INPUT', 'emailTemplate' ) ), flxf_t_errs( $r, 'flxFormsSettingsUpdate' ), 'settings: template needs shortcode_message' );
$r = flxf_t_op( 'FlxFormsSettingsUpdate', array( 'input' => array( 'emailTemplate' => '<p onclick="x()">Hi</p><script>bad()</script> shortcode_message <a href="https://example.com/a">x</a>' ) ), $adm_id );
$tpl = (string) ( $r['data']['flxFormsSettingsUpdate']['settings']['emailTemplate'] ?? '' );
flxt_ok( false !== strpos( $tpl, 'shortcode_message' ) && false === strpos( $tpl, 'script' ) && false === strpos( $tpl, 'onclick' ) && false !== strpos( $tpl, 'https://example.com/a' ), 'settings: template is cleaned and keeps links', $tpl );
$r = flxf_t_op( 'FlxFormsSettingsUpdate', array( 'input' => array( 'clearSecretKey' => true ) ), $adm_id );
flxt_same( false, $r['data']['flxFormsSettingsUpdate']['settings']['secretKeySet'] ?? null, 'settings: clear the secret' );
$r = flxf_t_op( 'FlxFormsClearFiles', array( 'input' => array() ), $adm_id );
flxt_ok( isset( $r['data']['flxFormsClearFiles']['deleted'] ), 'settings: clear unused files', $r );

/* ---------- 2.8.0: payments, system check, add-ons, automations ---------- */
foreach ( array( 'FlxFormsPayments' => 'flxFormsPayments', 'FlxFormsSystem' => 'flxFormsSystem', 'FlxFormsAddons' => 'flxFormsAddons', 'FlxFormsAutomations' => 'flxFormsAutomations', 'FlxFormsAutomationRuns' => 'flxFormsAutomationRuns' ) as $flxf_op => $flxf_field ) {
	$r = flxf_t_op( $flxf_op, array(), $sub_id );
	flxt_ok( null !== flxt_code( $r ), 'tools: a subscriber cannot read ' . $flxf_field );
	$r = flxf_t_op( $flxf_op, array(), $adm_id );
	flxt_ok( isset( $r['data'][ $flxf_field ] ) && null === flxt_code( $r ), 'tools: admin reads ' . $flxf_field, $r );
}
$r   = flxf_t_op( 'FlxFormsPayments', array(), $adm_id );
$pay = $r['data']['flxFormsPayments'] ?? array();
flxt_same( array( 'active', 'installed', 'configured', 'currencies', 'test', 'url' ), array_keys( (array) ( $pay['pos'] ?? array() ) ), 'payments: Flux POS state is listed', $pay );
flxt_ok( false === strpos( (string) wp_json_encode( $GLOBALS['flxf_ops'] ), 'Stripe' ), 'payments: no Stripe operation is left' );
if ( ! FLXF_Tools_Service::payments_available() ) {
	// Flux Donate keeps the payment layer and its records.
	flxt_same( array( false, array() ), array( $pay['available'] ?? null, $pay['payments'] ?? null ), 'payments: with Flux Donate no records are listed here', $pay );
} else {
	global $wpdb;
	FLXF_Payment_Records::maybe_install_table();
	$wpdb->insert( FLXF_Payment_Records::table_name(), array( 'form_id' => 7, 'track' => 'CTRK1', 'gateway' => 'stripe', 'payment_type' => 'one-time', 'transaction_id' => 'pi_contract', 'amount' => 12.5, 'currency' => 'usd', 'status' => 'mismatch', 'payer_email' => 'payer@example.com', 'form_name' => 'Pay form', 'created_at' => gmdate( 'Y-m-d H:i:s' ) ) );
	$r   = flxf_t_op( 'FlxFormsPayments', array(), $adm_id );
	$row = array_values( array_filter( (array) ( $r['data']['flxFormsPayments']['payments'] ?? array() ), static fn( $p ) => 'CTRK1' === ( $p['track'] ?? '' ) ) )[0] ?? array();
	flxt_same( array( 'failed', 'pi_contract', 'USD', 'Stripe', 12.5 ), array( $row['tone'] ?? null, $row['reference'] ?? null, $row['currency'] ?? null, $row['provider'] ?? null, $row['amount'] ?? null ), 'payments: an old record is still listed', $row );
	$wpdb->delete( FLXF_Payment_Records::table_name(), array( 'track' => 'CTRK1' ) );

	if ( FLXF_Payment_POS::available() ) {
		flxt_same( array( 'flxpos' ), array_keys( flxf_payment_providers() ), 'pos: Flux POS is the only provider' );
		$acc = FLXPOS_Accounts::save( array( 'provider' => 'isbank', 'enabled' => 1, 'test' => 1, 'currencies' => array( 'TRY' ), 'creds' => array( 'client_id' => '700655000200', 'username' => 'u', 'password' => 'p', 'store_key' => 'TRPS0200' ) ) );
		$r   = flxf_t_op( 'FlxFormsPayments', array(), $adm_id );
		$pos = $r['data']['flxFormsPayments']['pos'] ?? array();
		flxt_same( array( true, true, array( 'TRY' ), true ), array( $pos['active'] ?? null, $pos['configured'] ?? null, $pos['currencies'] ?? null, $pos['test'] ?? null ), 'pos: a test account takes TRY', $pos );
		$prov = flxf_payment_provider( 'flxpos' );
		flxt_same( array( true, false, false ), array( $prov->supports( 'TRY', 'charge' ), $prov->supports( 'TRY', 'month' ), $prov->supports( 'JPY', 'charge' ) ), 'pos: one-time charges in an account currency only' );
		$bad = $prov->create_payment( 10, 'TRY', array( 'reference' => 'flxf_ct', 'params' => array( 'card' => array( 'pan' => '4111111111111112', 'name' => 'A B', 'exp' => '12/30', 'cvc' => '123' ) ) ) );
		flxt_same( 'failed', $bad->status, 'pos: a bad card number is refused before the bank' );
		$res = $prov->create_payment( 10, 'TRY', array( 'reference' => 'flxf_ct', 'form_id' => 7, 'description' => 'Contract', 'params' => array( 'card' => array( 'pan' => '4111 1111 1111 1111', 'name' => 'Ada Test', 'exp' => '12 / 30', 'cvc' => '123' ) ) ) );
		flxt_ok( 'redirect' === $res->status && '' !== $res->reference && false !== strpos( (string) ( $res->client['redirect_url'] ?? '' ), 'flxpos=bridge' ), 'pos: start opens the POS bridge', $res );
		$txn = flxpos_txn( $res->reference );
		flxt_same( array( 10.0, 'TRY', 'flxf_ct', '411111******1111' ), array( (float) $txn['amount'], $txn['currency'], $txn['data']['meta']['flxf_ref'] ?? '', $txn['card'] ), 'pos: the transaction has the server amount and a masked card' );
		$rid = FLXF_Payment_Records::insert( array( 'form_id' => 7, 'track' => 'CTRK2', 'gateway' => 'flxpos', 'transaction_id' => $res->reference, 'amount' => 10, 'currency' => 'TRY' ) );
		$rec = FLXF_Payment_Records::get_by_track( 'CTRK2' );
		flxt_same( 'pending', $prov->verify( $rec )->status, 'pos: an unsettled transaction is pending' );
		FLXPOS_Txn::settle( (int) $txn['id'], 'paid' );
		do_action( 'flxpos_txn_settled', flxpos_txn( $res->reference ), 'paid', array() );
		$rec = FLXF_Payment_Records::get_by_track( 'CTRK2' );
		flxt_same( array( 'completed', 'completed' ), array( $rec->status, $prov->verify( $rec )->status ), 'pos: a paid transaction completes the record' );
		$wpdb->update( FLXF_Payment_Records::table_name(), array( 'amount' => 11 ), array( 'id' => $rid ) );
		flxt_same( 'failed', $prov->verify( FLXF_Payment_Records::get_by_track( 'CTRK2' ) )->status, 'pos: a different amount is not paid' );
		$wpdb->delete( FLXF_Payment_Records::table_name(), array( 'track' => 'CTRK2' ) );
		$wpdb->delete( FLXPOS_Txn::table(), array( 'id' => (int) $txn['id'] ) );
		if ( is_string( $acc ) ) {
			FLXPOS_Accounts::delete( $acc );
		}
	}
}

$r      = flxf_t_op( 'FlxFormsSystem', array(), $adm_id );
$checks = (array) ( $r['data']['flxFormsSystem']['checks'] ?? array() );
flxt_ok( in_array( 'flxf.uploads', array_column( $checks, 'id' ), true ), 'system: the Forms rows are there', $checks );
flxt_ok( '' !== (string) ( $checks[0]['groupLabel'] ?? '' ), 'system: rows have a group name' );

$r    = flxf_t_op( 'FlxFormsAddons', array(), $adm_id );
$keys = array_column( (array) ( $r['data']['flxFormsAddons'] ?? array() ), 'key' );
flxt_same( array( 'AdnOF', 'AdnSMF' ), $keys, 'addons: the bundled add-ons are listed (no Stripe add-on)', $r );
flxt_ok( ! in_array( false, array_column( (array) ( $r['data']['flxFormsAddons'] ?? array() ), 'enabled' ), true ), 'addons: the bundled add-ons are always on' );

$r    = flxf_t_op( 'FlxFormsAutomationEditor', array( 'id' => 0 ), $adm_id );
$ed   = $r['data']['flxFormsAutomationEditor'] ?? array();
flxt_same( array( 0, 'form_submitted' ), array( $ed['flow']['id'] ?? null, $ed['flow']['trigger']['type'] ?? null ), 'automations: a blank flow for id 0', $r );
flxt_ok( is_array( $ed['forms'] ?? null ) && isset( $ed['channels'] ), 'automations: editor lists forms and channels' );
$flow = array(
	'id'         => 0,
	'title'      => 'Contract flow',
	'enabled'    => true,
	'trigger'    => array( 'type' => 'form_submitted', 'form_id' => 0 ),
	'match'      => 'all',
	'conditions' => array( array( 'field' => 'f1', 'op' => 'eq', 'value' => 'x' ) ),
	'actions'    => array(
		array( 'id' => 'w1', 'type' => 'webhook', 'url' => 'https://example.com/hook', 'secret' => '' ),
		array( 'id' => 'bad', 'type' => 'run_shell', 'cmd' => 'rm' ),
	),
);
$r     = flxf_t_op( 'FlxFormsAutomationSave', array( 'input' => array( 'flow' => $flow ) ), $adm_id );
$saved = $r['data']['flxFormsAutomationSave']['flow'] ?? array();
$aid   = (int) ( $saved['id'] ?? 0 );
flxt_same( array( true, 'Contract flow', array( 'webhook' ) ), array( $aid > 0, $saved['title'] ?? null, array_column( (array) ( $saved['actions'] ?? array() ), 'type' ) ), 'automations: save creates the flow and drops unknown steps', $r );
$r = flxf_t_op( 'FlxFormsAutomationToggle', array( 'input' => array( 'id' => $aid, 'enabled' => false ) ), $adm_id );
flxt_same( false, FLXF_Automations::get_flow( $aid )['enabled'] ?? null, 'automations: toggle off', $r );
$r   = flxf_t_op( 'FlxFormsAutomationDuplicate', array( 'input' => array( 'id' => $aid ) ), $adm_id );
$cid = (int) ( $r['data']['flxFormsAutomationDuplicate']['id'] ?? 0 );
flxt_ok( $cid > 0 && $cid !== $aid && false === ( FLXF_Automations::get_flow( $cid )['enabled'] ?? true ), 'automations: duplicate is off', $r );
$r = flxf_t_op( 'FlxFormsAutomations', array(), $adm_id );
flxt_ok( in_array( $aid, array_column( (array) ( $r['data']['flxFormsAutomations']['flows'] ?? array() ), 'id' ), true ), 'automations: the list has it' );
$r = flxf_t_op( 'FlxFormsAutomationEditor', array( 'id' => $aid ), $adm_id );
flxt_same( 'Contract flow', $r['data']['flxFormsAutomationEditor']['flow']['title'] ?? null, 'automations: editor opens it' );
foreach ( array( $aid, $cid ) as $flxf_id ) {
	$r = flxf_t_op( 'FlxFormsAutomationDelete', array( 'input' => array( 'id' => $flxf_id ) ), $adm_id );
	flxt_same( true, $r['data']['flxFormsAutomationDelete']['deleted'] ?? null, 'automations: delete ' . $flxf_id );
}
$r = flxf_t_op( 'FlxFormsAutomationEditor', array( 'id' => $aid ), $adm_id );
flxt_ok( is_array( $r['data']['flxFormsAutomationEditor'] ?? null ) && null === $r['data']['flxFormsAutomationEditor']['flow'], 'automations: a deleted flow is null', $r );
$r = flxf_t_op( 'FlxFormsAutomationDelete', array( 'input' => array( 'id' => $aid ) ), $adm_id );
flxt_same( array( array( 'INVALID_INPUT', 'id' ) ), flxf_t_errs( $r, 'flxFormsAutomationDelete' ), 'automations: deleting twice is an input error' );
FLXF_Automations::store_run( array( 'id' => 'run-contract', 'time' => time(), 'flow_id' => 1, 'flow_title' => 'F', 'form_title' => 'Fo', 'track' => 'T1', 'status' => 'ok', 'steps' => array( array( 'type' => 'webhook', 'status' => 'ok', 'message' => '200' ) ) ) );
$r = flxf_t_op( 'FlxFormsAutomationRuns', array(), $adm_id );
flxt_same( array( 'run-contract', 'webhook' ), array( $r['data']['flxFormsAutomationRuns'][0]['id'] ?? null, $r['data']['flxFormsAutomationRuns'][0]['steps'][0]['type'] ?? null ), 'automations: run log', $r );
$r = flxf_t_op( 'FlxFormsAutomationRunsClear', array( 'input' => array() ), $adm_id );
flxt_same( array(), FLXF_Automations::get_runs(), 'automations: clear the log', $r );

flxt_done();
