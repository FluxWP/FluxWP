<?php
/**
 * Flux Mail GraphQL contract (part 1: settings, templates, log, system).
 * Documents are the persisted operations in plugins/flx-mail/graphql/persisted-queries.json.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-mail/contract-test.php
 *
 * Mail is not sent: pre_wp_mail short-circuits. The IMAP test is not called (it dials a server).
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux Mail GraphQL\n";

$secret = 'SUPER-SECRET-MAIL-99';
add_filter( 'pre_wp_mail', static fn() => true );

$ops = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXI_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', (string) $doc, $m ) ) {
		$ops[ $m[1] ] = (string) $doc;
	}
}
$want = array(
	'FlxMailSettings',
	'FlxMailTemplates',
	'FlxMailTemplate',
	'FlxMailLogs',
	'FlxMailLog',
	'FlxMailSystemChecks',
	'FlxMailSettingsUpdate',
	'FlxMailTemplateUpdate',
	'FlxMailTemplateReset',
	'FlxMailPreview',
	'FlxMailPreviewPdf',
	'FlxMailTestSend',
	'FlxMailDnsCheck',
	'FlxMailWcBrandApply',
	'FlxMailImapTest',
);
foreach ( $want as $name ) {
	flxt_ok( isset( $ops[ $name ] ), 'operation ' . $name . ' is shipped' );
}
flxt_same( count( $want ), count( $ops ), 'persisted operation count' );

$op = static function ( string $name, array $vars = array(), int $user = 0 ) use ( $ops ): array {
	return flxt_gql( $ops[ $name ], $vars, $user );
};

$admin = 1;
$sub   = (int) wp_insert_user(
	array(
		'user_login' => 'flxi_sub_' . wp_rand(),
		'user_email' => 'flxi_sub_' . wp_rand() . '@example.com',
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);

$seen = array();
$run  = static function ( string $name, array $vars = array(), int $user = 0 ) use ( $op, &$seen, $secret ): array {
	$r      = $op( $name, $vars, $user );
	$seen[] = $r;
	$blob   = (string) wp_json_encode( $r );
	flxt_ok( false === strpos( $blob, $secret ), $name . ': secret value is absent' );
	return $r;
};

// Permissions.
$r = $run( 'FlxMailSettings' );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'settings: visitor is unauthenticated' );
$r = $run( 'FlxMailSettings', array(), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'settings: subscriber is forbidden' );
$r = $run( 'FlxMailSettingsUpdate', array( 'input' => array( 'settings' => array( 'fromName' => 'X' ) ) ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'settings update: subscriber is forbidden' );
$r = $run( 'FlxMailTemplates', array( 'first' => 5 ), 0 );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'templates: visitor is unauthenticated' );

// Empty-enough read: secrets are a mask, never a value.
$r = $run( 'FlxMailSettings', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'settings: administrator has no errors' );
$s = $r['data']['flxMailSettings'] ?? array();
flxt_ok( is_string( $s['fromName'] ?? null ) && is_string( $s['fromEmail'] ?? null ), 'settings: sender fields are present' );
flxt_ok( isset( $s['smtpPassword']['set'], $s['smtpPassword']['mask'], $s['smtpPassword']['fromConfig'] ), 'settings: secret is set/mask/fromConfig' );
flxt_ok( ! array_key_exists( 'value', $s['smtpPassword'] ) && ! array_key_exists( 'value', $s['imapPassword'] ), 'settings: secret has no value field' );
flxt_same( false, $s['smtpPassword']['set'], 'settings: no smtp password is stored yet' );

// Filled settings, including a write-only secret.
$r = $run(
	'FlxMailSettingsUpdate',
	array(
		'input' => array(
			'settings' => array(
				'fromName'     => 'Contract Sender',
				'fromEmail'    => 'bilgi@example.com',
				'mailer'       => 'SMTP',
				'smtpHost'     => 'smtp.example.com',
				'smtpPort'     => 465,
				'smtpSecure'   => 'SSL',
				'smtpPassword' => $secret,
				'logEnabled'   => true,
			),
		),
	),
	$admin
);
flxt_same( array(), $r['data']['flxMailSettingsUpdate']['errors'] ?? null, 'settings update: no field errors' );
$saved = $r['data']['flxMailSettingsUpdate']['settings'] ?? array();
flxt_same( 'Contract Sender', $saved['fromName'] ?? null, 'settings update: name is stored' );
flxt_same( 'SMTP', $saved['mailer'] ?? null, 'settings update: mailer is stored' );
flxt_same( true, $saved['smtpPassword']['set'] ?? null, 'settings update: password is marked set' );
flxt_same( false, $saved['smtpPassword']['fromConfig'] ?? null, 'settings update: password is not from wp-config' );
flxt_ok( $secret !== ( $saved['smtpPassword']['mask'] ?? $secret ), 'settings update: mask is not the password' );

// Empty secret input keeps the stored value.
$r = $run( 'FlxMailSettingsUpdate', array( 'input' => array( 'settings' => array( 'smtpPassword' => '' ) ) ), $admin );
flxt_same( true, $r['data']['flxMailSettingsUpdate']['settings']['smtpPassword']['set'] ?? null, 'empty secret keeps the stored password' );

// __clear__ removes it.
$r = $run( 'FlxMailSettingsUpdate', array( 'input' => array( 'settings' => array( 'smtpPassword' => '__clear__' ) ) ), $admin );
flxt_same( false, $r['data']['flxMailSettingsUpdate']['settings']['smtpPassword']['set'] ?? null, '__clear__ removes the password' );

// Bad email is a payload error, not a thrown error.
$r = $run( 'FlxMailSettingsUpdate', array( 'input' => array( 'settings' => array( 'fromEmail' => 'not-an-email' ) ) ), $admin );
flxt_same( null, $r['errors'] ?? null, 'bad email: mutation stays in the payload' );
flxt_same( 'INVALID_INPUT', $r['data']['flxMailSettingsUpdate']['errors'][0]['code'] ?? null, 'bad email: INVALID_INPUT' );

// Templates.
$r = $run( 'FlxMailTemplates', array( 'first' => 50 ), $admin );
flxt_same( null, $r['errors'] ?? null, 'templates: administrator has no errors' );
$nodes = $r['data']['flxMailTemplates']['nodes'] ?? array();
flxt_ok( is_array( $nodes ) && count( $nodes ) > 0, 'templates: the list is not empty' );
flxt_ok( isset( $r['data']['flxMailLogStats']['sent'] ), 'templates: 7 day stats are present' );
$key = '';
foreach ( $nodes as $node ) {
	if ( ! empty( $node['editable'] ) ) {
		$key = (string) $node['key'];
		break;
	}
}
flxt_ok( '' !== $key, 'templates: an editable template exists' );

$r = $run( 'FlxMailTemplate', array( 'key' => 'no-such-template' ), $admin );
$template_data = is_array( $r['data'] ?? null ) ? $r['data'] : array();
flxt_same( null, array_key_exists( 'flxMailTemplate', $template_data ) ? $template_data['flxMailTemplate'] : 'missing', 'unknown template is null' );

if ( '' !== $key ) {
	$r = $run( 'FlxMailTemplate', array( 'key' => $key ), $admin );
	$loaded = is_array( $r['data'] ?? null ) ? ( $r['data']['flxMailTemplate'] ?? null ) : null;
	flxt_same( $key, is_array( $loaded ) ? ( $loaded['key'] ?? null ) : null, 'template: editor loads' );
	$r = $run( 'FlxMailTemplateUpdate', array( 'input' => array( 'key' => $key, 'subject' => 'Contract subject', 'enabled' => true, 'sender' => 'INFO' ) ), $admin );
	flxt_same( array(), $r['data']['flxMailTemplateUpdate']['errors'] ?? null, 'template update: no field errors' );
	flxt_same( 'Contract subject', $r['data']['flxMailTemplateUpdate']['template']['subject'] ?? null, 'template update: subject is stored' );
	$r = $run( 'FlxMailTemplateUpdate', array( 'input' => array( 'key' => '' ) ), $admin );
	flxt_same( 'INVALID_INPUT', $r['data']['flxMailTemplateUpdate']['errors'][0]['code'] ?? null, 'template update: missing key is INVALID_INPUT' );
	$r = $run( 'FlxMailTemplateReset', array( 'input' => array( 'key' => $key ) ), $admin );
	flxt_same( array(), $r['data']['flxMailTemplateReset']['errors'] ?? null, 'template reset: no field errors' );
	$r = $run( 'FlxMailPreview', array( 'input' => array( 'key' => $key, 'subject' => 'Preview subject' ) ), $admin );
	flxt_ok( is_string( $r['data']['flxMailPreview']['preview']['html'] ?? null ), 'preview: html is returned' );
	$r = $run( 'FlxMailTestSend', array( 'input' => array( 'key' => $key, 'to' => 'tester@example.com' ) ), $admin );
	flxt_ok( is_bool( $r['data']['flxMailTestSend']['ok'] ?? null ) || isset( $r['data']['flxMailTestSend']['errors'] ), 'test send: ok or payload errors' );
}

// Log.
$r = $run( 'FlxMailLogs', array( 'first' => 5, 'status' => 'sent', 'channel' => 'email', 'campaign' => 0 ), $admin );
flxt_same( null, $r['errors'] ?? null, 'log: filters are accepted' );
flxt_ok( is_array( $r['data']['flxMailLogs']['nodes'] ?? null ), 'log: nodes are a list' );
$r = $run( 'FlxMailLog', array( 'id' => 99999999 ), $admin );
$log_data = is_array( $r['data'] ?? null ) ? $r['data'] : array();
flxt_same( null, array_key_exists( 'flxMailLog', $log_data ) ? $log_data['flxMailLog'] : 'missing', 'missing log row is null' );

// System checks are a list (empty when Flux Speed owns the screen).
$r = $run( 'FlxMailSystemChecks', array(), $admin );
flxt_ok( is_array( $r['data']['flxMailSystemChecks'] ?? null ), 'system checks: a list is returned' );

// One more pass over every captured response.
$blob = (string) wp_json_encode( $seen );
flxt_ok( false === strpos( $blob, $secret ), 'no captured response contains the secret' );
flxt_ok( false === strpos( $blob, 'smtp_pass' ), 'stored secret key name is not a response field' );

flxt_done();
