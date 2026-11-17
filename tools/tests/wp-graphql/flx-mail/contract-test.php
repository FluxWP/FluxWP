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
flxt_same( 'Contract Sender', flxi_setting( 'from_name' ), 'classic reader sees the GraphQL sender name' );
flxt_same( 'bilgi@example.com', flxi_setting( 'from_email' ), 'classic reader sees the GraphQL sender address' );
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
flxt_same( 'bilgi@example.com', flxi_setting( 'from_email' ), 'invalid email leaves the classic stored address' );

$classic = FLXI_Settings_Service::save( array( 'from_name' => 'Classic Sender' ), 'form', true );
flxt_same( true, true === $classic, 'classic settings writer succeeds' );
$r = $run( 'FlxMailSettings', array(), $admin );
flxt_same( 'Classic Sender', $r['data']['flxMailSettings']['fromName'] ?? null, 'GraphQL reads the classic sender name' );

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

	// Direction 1: Save all editable fields via GraphQL, verify partial update keeps them, and verify classic renderer matches byte-for-byte.
	$r = $run(
		'FlxMailTemplateUpdate',
		array(
			'input' => array(
				'key'        => $key,
				'enabled'    => true,
				'sender'     => 'NOREPLY',
				'subject'    => 'GQL Subject {site}',
				'heading'    => 'GQL Heading',
				'body'       => '<p>GQL Body content for {site}</p>',
				'buttonText' => 'Open Site',
				'buttonUrl'  => 'https://example.com/go',
			),
		),
		$admin
	);
	flxt_same( array(), $r['data']['flxMailTemplateUpdate']['errors'] ?? null, 'template update: full save has no field errors' );
	flxt_same( 'GQL Subject {site}', $r['data']['flxMailTemplateUpdate']['template']['subject'] ?? null, 'template update: subject is stored' );
	flxt_same( 'NOREPLY', $r['data']['flxMailTemplateUpdate']['template']['sender'] ?? null, 'template update: sender is stored' );

	// Partial GraphQL save preserves omitted fields.
	$r = $run( 'FlxMailTemplateUpdate', array( 'input' => array( 'key' => $key, 'subject' => 'Contract subject' ) ), $admin );
	flxt_same( array(), $r['data']['flxMailTemplateUpdate']['errors'] ?? null, 'template partial update: no field errors' );
	$tpl_after = $r['data']['flxMailTemplateUpdate']['template'] ?? array();
	flxt_same( 'Contract subject', $tpl_after['subject'] ?? null, 'template partial update: subject updated' );
	flxt_same( 'GQL Heading', $tpl_after['heading'] ?? null, 'template partial update: heading preserved' );
	flxt_same( 'NOREPLY', $tpl_after['sender'] ?? null, 'template partial update: sender preserved' );

	$classic_built = flxi_build_template( $key, array(), array( 'use_sample' => true ) );
	$gql_preview   = $run( 'FlxMailPreview', array( 'input' => array( 'key' => $key ) ), $admin );
	flxt_same( $classic_built['subject'] ?? null, $gql_preview['data']['flxMailPreview']['preview']['subject'] ?? null, 'GraphQL-saved template: subject renders identically in classic sender and GraphQL preview' );
	flxt_same( $classic_built['html'] ?? null, $gql_preview['data']['flxMailPreview']['preview']['html'] ?? null, 'GraphQL-saved template: html renders identically in classic sender and GraphQL preview' );
	flxt_same( 'noreply', $classic_built['sender'] ?? null, 'GraphQL-saved template: classic sender sees NOREPLY' );

	$fires = 0;
	$hook  = static function () use ( &$fires ) {
		++$fires;
	};
	add_action( 'flxi_template_saved', $hook );
	$r = $run( 'FlxMailTemplateUpdate', array( 'input' => array( 'key' => $key, 'subject' => 'Round trip subject', 'enabled' => true, 'sender' => 'INFO' ) ), $admin );
	remove_action( 'flxi_template_saved', $hook );
	flxt_same( 1, $fires, 'template save fires flxi_template_saved once' );
	flxt_same( 'Round trip subject', $r['data']['flxMailTemplateUpdate']['template']['subject'] ?? null, 'second template save is stored' );

	// Direction 2: Save via classic service, verify GraphQL reads & renders identically to classic sender.
	$classic_save = FLXI_Template_Service::save_post(
		array(
			'key'         => $key,
			'enabled'     => 1,
			'sender'      => 'info',
			'subject'     => 'Classic subject line',
			'heading'     => 'Classic heading',
			'body'        => '<p>Classic body text</p>',
			'button_text' => 'Classic Button',
			'button_url'  => 'https://example.com/classic',
		)
	);
	flxt_same( true, true === $classic_save, 'classic template writer succeeds' );
	$r      = $run( 'FlxMailTemplate', array( 'key' => $key ), $admin );
	$loaded = is_array( $r['data'] ?? null ) ? ( $r['data']['flxMailTemplate'] ?? null ) : null;
	flxt_same( 'Classic subject line', is_array( $loaded ) ? ( $loaded['subject'] ?? null ) : null, 'GraphQL reads the classic template subject' );
	flxt_same( 'Classic heading', is_array( $loaded ) ? ( $loaded['heading'] ?? null ) : null, 'GraphQL reads the classic template heading' );
	$classic_built = flxi_build_template( $key, array(), array( 'use_sample' => true ) );
	$gql_preview   = $run( 'FlxMailPreview', array( 'input' => array( 'key' => $key ) ), $admin );
	flxt_same( $classic_built['subject'] ?? null, $gql_preview['data']['flxMailPreview']['preview']['subject'] ?? null, 'Classic-saved template: subject renders identically in GraphQL preview and classic sender' );
	flxt_same( $classic_built['html'] ?? null, $gql_preview['data']['flxMailPreview']['preview']['html'] ?? null, 'Classic-saved template: html renders identically in GraphQL preview and classic sender' );

	// Preview with temporary overrides must not leak filters to subsequent classic/GraphQL calls.
	$r = $run( 'FlxMailPreview', array( 'input' => array( 'key' => $key, 'subject' => 'Temp preview override' ) ), $admin );
	flxt_ok( is_string( $r['data']['flxMailPreview']['preview']['html'] ?? null ), 'preview: html is returned' );
	flxt_same( 'Temp preview override', $r['data']['flxMailPreview']['preview']['subject'] ?? null, 'preview: temporary override applied' );
	$after_preview = flxi_template( $key );
	flxt_same( 'Classic subject line', is_array( $after_preview ) ? ( $after_preview['subject'] ?? null ) : null, 'preview: temporary template override does not leak' );
	$brand_preview = $run( 'FlxMailPreview', array( 'input' => array( 'key' => 'brand', 'brand' => array( 'orgName' => 'Temp Preview Org' ) ) ), $admin );
	flxt_ok( false !== strpos( (string) ( $brand_preview['data']['flxMailPreview']['preview']['html'] ?? '' ), 'Temp Preview Org' ), 'preview: temporary brand override applied' );
	flxt_ok( 'Temp Preview Org' !== flxi_setting( 'org_name' ), 'preview: temporary brand override does not leak' );

	$r = $run( 'FlxMailTemplateUpdate', array( 'input' => array( 'key' => '' ) ), $admin );
	flxt_same( 'INVALID_INPUT', $r['data']['flxMailTemplateUpdate']['errors'][0]['code'] ?? null, 'template update: missing key is INVALID_INPUT' );
	$r = $run( 'FlxMailTemplateReset', array( 'input' => array( 'key' => $key ) ), $admin );
	flxt_same( array(), $r['data']['flxMailTemplateReset']['errors'] ?? null, 'template reset: no field errors' );
	$r = $run( 'FlxMailTestSend', array( 'input' => array( 'key' => $key, 'to' => 'tester@example.com' ) ), $admin );
	flxt_ok( is_bool( $r['data']['flxMailTestSend']['ok'] ?? null ) || isset( $r['data']['flxMailTestSend']['errors'] ), 'test send: ok or payload errors' );
}

// Logo upload context is registered and enforces management capability.
$upload_contexts = function_exists( 'flx_gql_upload_contexts' ) ? flx_gql_upload_contexts() : array();
flxt_ok( isset( $upload_contexts['flx-mail/logo'] ), 'upload context flx-mail/logo is registered' );
if ( isset( $upload_contexts['flx-mail/logo'] ) && function_exists( 'flx_gql_rest_upload_permission' ) ) {
	$req = new WP_REST_Request( 'POST', '/flx/v1/upload' );
	$req->set_param( 'context', 'flx-mail/logo' );
	wp_set_current_user( $admin );
	flxt_same( true, true === flx_gql_rest_upload_permission( $req ), 'upload context flx-mail/logo allows admin' );
	wp_set_current_user( $sub );
	flxt_ok( is_wp_error( flx_gql_rest_upload_permission( $req ) ), 'upload context flx-mail/logo denies subscriber' );
	wp_set_current_user( $admin );
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

$viewer = flxt_gql( 'query { flxViewer { capabilities } }', array(), $admin );
$caps   = $viewer['data']['flxViewer']['capabilities'] ?? null;
flxt_ok( is_array( $caps ) && in_array( 'flxi_manage', $caps, true ), 'flxi_manage is in flxViewer.capabilities' );

update_option( 'timezone_string', 'Europe/Istanbul' );
flxi_update_settings( array( 'log_enabled' => 1, 'log_body' => 1 ) );
$log_id = flxi_log_message( 'email', 'roundtrip@example.com', 'Hello from the contract', true, '', array( 'template' => 'contract', 'source' => 'contract', 'status' => 'sent' ) );
/** @var wpdb $wpdb */
global $wpdb;
$stored = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT created_at FROM ' . flxi_table( 'log' ) . ' WHERE id = %d', $log_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
$utc    = new DateTimeImmutable( $stored, new DateTimeZone( 'UTC' ) );
$local  = new DateTimeImmutable( $stored, wp_timezone() );
$r      = $run( 'FlxMailLog', array( 'id' => $log_id ), $admin );
$iso    = $r['data']['flxMailLog']['createdAt']['iso'] ?? null;
flxt_ok( $log_id > 0, 'the real log writer inserted a row' );
flxt_ok( 'Europe/Istanbul' === wp_timezone()->getName(), 'the site timezone is Europe/Istanbul' );
flxt_same( $utc->format( 'Y-m-d\TH:i:s\Z' ), $iso, 'log iso is the stored GMT instant when the site is Europe/Istanbul' );
flxt_ok( $utc->getTimestamp() !== $local->getTimestamp(), 'reading the stored GMT string in the site timezone is a different instant' );

$r = $run( 'FlxMailTestSend', array( 'input' => array( 'to' => 'bookkeeping@example.com' ) ), $admin );
flxt_same( true, $r['data']['flxMailTestSend']['ok'] ?? null, 'settings test send is accepted' );
$last = get_option( 'flxi_last_test' );
flxt_same( 'bookkeeping@example.com', is_array( $last ) ? ( $last['to'] ?? null ) : null, 'settings test writes flxi_last_test' );
flxt_ok( is_array( $last ) && is_int( $last['at'] ?? null ) && $last['at'] > 0, 'settings test stores one timestamp' );

// Secrets: the mask is the fixed classic placeholder, never a piece of the secret.
$mask   = '••••••••';
$secs   = array(
	'smtpPassword'        => 'Smtp-Tail-Secret-AB12',
	'smtpNoreplyPassword' => 'Noreply-Tail-Secret-CD34',
	'imapPassword'        => 'Imap-Tail-Secret-EF56',
);
$r = $run( 'FlxMailSettingsUpdate', array( 'input' => array( 'settings' => $secs ) ), $admin );
flxt_same( array(), $r['data']['flxMailSettingsUpdate']['errors'] ?? null, 'secrets: all three are stored' );
$r = $run( 'FlxMailSettings', array(), $admin );
foreach ( $secs as $name => $plain ) {
	$view = $r['data']['flxMailSettings'][ $name ] ?? array();
	flxt_same( true, $view['set'] ?? null, 'secrets: ' . $name . ' is set' );
	flxt_same( $mask, $view['mask'] ?? null, 'secrets: ' . $name . ' mask is the fixed placeholder' );
	flxt_ok( false === strpos( (string) wp_json_encode( $view ), substr( $plain, -2 ) . '"' ), 'secrets: ' . $name . ' view has no tail of the secret' );
}
// Classic form: a password field with the GraphQL sentinel is stored as typed, not cleared.
$classic = FLXI_Settings_Service::save( array( 'imap_pass' => '__clear__' ), 'form', true );
flxt_same( true, true === $classic, 'classic form: sentinel text is accepted as a password' );
flxt_same( '__clear__', flxi_decrypt( (string) flxi_setting( 'imap_pass' ) ), 'classic form: __clear__ is stored as typed, only GraphQL treats it as the sentinel' );
// Classic and MCP: an invalid email is dropped to the default and the rest of the form still saves.
$classic = FLXI_Settings_Service::save( array( 'from_name' => 'Mixed Save', 'from_email' => 'not-an-email' ), 'form', true );
flxt_same( true, true === $classic, 'classic form: invalid email does not reject the form' );
flxt_same( 'Mixed Save', flxi_setting( 'from_name' ), 'classic form: other fields are saved with an invalid email' );
flxt_same( flxi_defaults()['from_email'], flxi_setting( 'from_email' ), 'classic form: invalid email falls back to the default' );
flxt_same( '', flxi_sanitize_setting( 'reply_to', 'not-an-email' ), 'MCP and classic sanitizer: invalid email becomes empty' );
FLXI_Settings_Service::save( array( 'from_email' => 'bilgi@example.com' ), 'form', true );

// wp-config constants: same fixed mask, read-only over GraphQL, classic keeps the main behaviour.
define( 'FLXI_SMTP_PASS', 'Const-Smtp-Tail-GH78' );
define( 'FLXI_SMTP_NOREPLY_PASS', 'Const-Noreply-Tail-IJ90' );
define( 'FLXI_IMAP_PASS', 'Const-Imap-Tail-KL12' );
$r = $run( 'FlxMailSettings', array(), $admin );
foreach ( array( 'smtpPassword', 'smtpNoreplyPassword', 'imapPassword' ) as $name ) {
	$view = $r['data']['flxMailSettings'][ $name ] ?? array();
	flxt_same( true, $view['fromConfig'] ?? null, 'constant: ' . $name . ' is from wp-config' );
	flxt_same( $mask, $view['mask'] ?? null, 'constant: ' . $name . ' mask is the fixed placeholder' );
}
$r = $run( 'FlxMailSettingsUpdate', array( 'input' => array( 'settings' => array( 'smtpPassword' => 'Typed-Over-Const' ) ) ), $admin );
flxt_same( 'INVALID_INPUT', $r['data']['flxMailSettingsUpdate']['errors'][0]['code'] ?? null, 'constant: GraphQL refuses a password that wp-config defines' );
$classic = FLXI_Settings_Service::save( array( 'smtp_nr_pass' => 'Classic-Nr-Pass' ), 'form', true );
flxt_same( true, true === $classic, 'constant: classic form still saves the no-reply password' );
flxt_same( 'Classic-Nr-Pass', flxi_decrypt( (string) flxi_setting( 'smtp_nr_pass' ) ), 'constant: classic form stores the posted no-reply password like main' );

// One more pass over every captured response.
$blob = (string) wp_json_encode( $seen );
flxt_ok( false === strpos( $blob, $secret ), 'no captured response contains the secret' );
flxt_ok( false === strpos( $blob, 'smtp_pass' ), 'stored secret key name is not a response field' );

flxt_done();
