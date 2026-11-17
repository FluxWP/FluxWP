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
flxt_same( 17, count( $GLOBALS['flxf_ops'] ), 'Flux Forms ships 17 persisted operations' );

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
		return 40;
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
$email_key   = 'contractemailkey123';
$cfg_setting = array(
	'smtp'           => '1',
	'emailSupporter' => 'supporter@example.com',
	'femail'         => 'noreply@example.com',
	'email_key'      => $email_key,
	'emailTemp'      => '',
);
$wpdb->insert(
	$wpdb->prefix . 'emsfb_setting',
	array(
		'setting' => (string) wp_json_encode( $cfg_setting ),
		'email'   => 'supporter@example.com',
		'date'    => current_time( 'mysql' ),
	)
);
if ( class_exists( '\Emsfb\Emsfb' ) ) {
	\Emsfb\Emsfb::get_setting_Emsfb( '_clear_cache' );
}
delete_transient( 'emsfb_settings_transient' );
wp_cache_delete( 'settings:decoded', 'emsfb' );
wp_cache_delete( 'settings:raw', 'emsfb' );
wp_cache_delete( 'emsfb_settings', 'emsfb' );

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
	array( 'id_' => 'burl', 'type' => 'url', 'name' => 'Website', 'required' => 0 ),
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
	'burl'   => array_merge( $b_base, array( array( 'fieldId' => 'burl', 'value' => 'not-a-url' ) ) ),
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

require __DIR__ . '/contract-admin-test.php';

flxt_done();
