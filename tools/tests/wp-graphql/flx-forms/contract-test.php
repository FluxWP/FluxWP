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
flxt_same( 4, count( $GLOBALS['flxf_ops'] ), 'Flux Forms ships 4 persisted operations' );

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
$r = flxf_t_op( 'FlxFormTrack', array( 'code' => 'not-a-code' ) );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'track: invalid code' );

$r = flxf_t_op( 'FlxFormTrackReply', array( 'input' => array( 'trackCode' => $tracked, 'message' => 'Following up' ) ) );
flxt_same( array(), flxf_t_errs( $r, 'flxFormTrackReply' ), 'reply: stored' );
$r = flxf_t_op( 'FlxFormTrack', array( 'code' => $tracked ) );
flxt_same( 'Following up', $r['data']['flxFormTrack']['replies'][0]['body'] ?? null, 'reply: visible on the track query' );
$r = flxf_t_op( 'FlxFormTrackReply', array( 'input' => array( 'trackCode' => $tracked, 'message' => 'See file', 'attachments' => array( (string) ( $bits['url'] ?? '' ) ) ) ) );
flxt_same( array( array( 'INVALID_INPUT', 'attachments' ) ), flxf_t_errs( $r, 'flxFormTrackReply' ), 'reply: unreserved attachment is rejected' );

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

flxt_done();
