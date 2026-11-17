<?php
/**
 * Flux Mail GraphQL contract (part 1: settings, templates, log, system; part 2: connections and WhatsApp Cloud).
 * Documents are the persisted operations in plugins/flx-mail/graphql/persisted-queries.json.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-mail/contract-test.php
 *
 * Mail is not sent: pre_wp_mail short-circuits. The IMAP test is not called (it dials a server).
 * Meta, Evolution and Netgsm are answered by a pre_http_request stub; nothing leaves the test site.
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
	'FlxMailConnectionMeta',
	'FlxMailConnections',
	'FlxMailConnection',
	'FlxMailWaState',
	'FlxMailWaInstances',
	'FlxMailWaGroups',
	'FlxMailWaServer',
	'FlxMailWacloudSetup',
	'FlxMailWacloudTemplates',
	'FlxMailConnectionSave',
	'FlxMailConnectionDelete',
	'FlxMailConnectionSetDefault',
	'FlxMailConnectionTest',
	'FlxMailWaConnect',
	'FlxMailWaRestart',
	'FlxMailWaLogout',
	'FlxMailWaServerUpdate',
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

// Discovered emails are grouped by the plugin that sends them, like the classic page.
update_option(
	'flxi_discovered',
	array(
		'forms/contract' => array(
			'source'  => 'flx-forms',
			'caller'  => 'contract',
			'subject' => 'Contract form',
			'count'   => 3,
			'last'    => time(),
		),
	)
);
$r      = $run( 'FlxMailTemplates', array( 'first' => 50 ), $admin );
$groups = array();
foreach ( $r['data']['flxMailTemplates']['nodes'] ?? array() as $node ) {
	$groups[ (string) $node['key'] ] = (string) $node['group'];
}
flxt_same( flxi_source_label( 'flx-forms' ), $groups['forms/contract'] ?? null, 'discovered: the list groups by source plugin' );
$r = $run( 'FlxMailTemplate', array( 'key' => 'forms/contract' ), $admin );
flxt_same( flxi_source_label( 'flx-forms' ), $r['data']['flxMailTemplate']['group'] ?? null, 'discovered: the editor shows the same group' );
delete_option( 'flxi_discovered' );

// WooCommerce emails: the editor shows WooCommerce's own enabled state, and a sender-only save keeps it.
if ( ! function_exists( 'WC' ) && ! class_exists( 'WC_Emails' ) ) {
	class WC_Emails {} // phpcs:ignore
	class Flxt_WC_Email { // phpcs:ignore
		public $id = 'flxt_off';
		public function get_title() {
			return 'Contract Woo email';
		}
		public function get_description() {
			return 'Disabled in WooCommerce';
		}
		public function is_enabled() {
			return false;
		}
		public function is_manual() {
			return false;
		}
		public function is_customer_email() {
			return true;
		}
		public function get_option_key() {
			return 'woocommerce_flxt_off_settings';
		}
	}
	class Flxt_WC_Mailer { // phpcs:ignore
		public function get_emails() {
			return array( new Flxt_WC_Email() );
		}
	}
	class Flxt_WC { // phpcs:ignore
		public function mailer() {
			return new Flxt_WC_Mailer();
		}
	}
	function WC() { // phpcs:ignore
		return new Flxt_WC();
	}
}
if ( function_exists( 'WC' ) && class_exists( 'Flxt_WC_Email' ) ) {
	$r = $run( 'FlxMailTemplate', array( 'key' => 'wc/flxt_off' ), $admin );
	flxt_same( false, $r['data']['flxMailTemplate']['enabled'] ?? null, 'woocommerce: the editor shows WooCommerce disabled' );
	$list = $run( 'FlxMailTemplates', array( 'first' => 50, 'search' => 'flxt_off' ), $admin );
	flxt_same( false, $list['data']['flxMailTemplates']['nodes'][0]['enabled'] ?? null, 'woocommerce: the list shows the same enabled state' );
	$enabled = ! empty( $r['data']['flxMailTemplate']['enabled'] );
	$r       = $run( 'FlxMailTemplateUpdate', array( 'input' => array( 'key' => 'wc/flxt_off', 'enabled' => $enabled, 'sender' => 'NOREPLY' ) ), $admin );
	flxt_same( array(), $r['data']['flxMailTemplateUpdate']['errors'] ?? null, 'woocommerce: sender-only save has no errors' );
	flxt_same( false, $r['data']['flxMailTemplateUpdate']['template']['enabled'] ?? null, 'woocommerce: a sender-only save does not re-enable the email' );
}

/* ------------------------------------------------------------------ */
/* Part 2: connections and WhatsApp Cloud                              */
/* ------------------------------------------------------------------ */

$token  = 'CLOUD-TOKEN-SECRET-77';
$apisec = 'META-APP-SECRET-55';
$apikey = 'EVO-API-KEY-SECRET-33';
$nspass = 'NETGSM-PASS-SECRET-11';
$dbpass = 'BRIDGE-DB-PASS-SECRET-22';
$secrets2 = array( $token, $apisec, $apikey, $nspass, $dbpass );

$meta_calls = array();
$stub = static function ( $pre, $args, $url ) use ( &$meta_calls, $dbpass ) {
	$u    = (string) $url;
	$body = null;
	$code = 200;
	if ( false !== strpos( $u, 'graph.facebook.com' ) ) {
		$meta_calls[] = $u;
		if ( false !== strpos( $u, 'message_templates' ) ) {
			$body = array( 'data' => array( array( 'name' => 'order_update', 'language' => 'tr', 'status' => 'APPROVED', 'category' => 'UTILITY', 'components' => array( array( 'type' => 'BODY', 'text' => 'Hello {{1}}, order {{2}} shipped.' ) ) ) ) );
		} else {
			$body = array( 'display_phone_number' => '+90 555 111 22 33', 'verified_name' => 'Contract Shop', 'quality_rating' => 'GREEN', 'name_status' => 'APPROVED', 'code_verification_status' => 'VERIFIED', 'platform_type' => 'CLOUD_API' );
		}
	} elseif ( false !== strpos( $u, 'evo.example.test' ) ) {
		$meta_calls[] = $u;
		if ( false !== strpos( $u, '/instance/connectionState/' ) ) {
			$body = array( 'instance' => array( 'state' => 'open' ) );
		} elseif ( false !== strpos( $u, '/instance/fetchInstances?instanceName=' ) ) {
			$body = array( array( 'name' => 'shop', 'ownerJid' => '905551112233@s.whatsapp.net', 'profileName' => 'Contract Shop' ) );
		} elseif ( false !== strpos( $u, '/instance/fetchInstances' ) ) {
			$body = array( array( 'name' => 'shop', 'connectionStatus' => 'open', 'ownerJid' => '905551112233@s.whatsapp.net' ), array( 'name' => 'spare', 'connectionStatus' => 'close' ) );
		} elseif ( false !== strpos( $u, '/instance/connect/' ) ) {
			$body = array( 'base64' => 'iVBORw0KGgo=', 'pairingCode' => 'AB12CD34' );
		} elseif ( false !== strpos( $u, '/group/fetchAllGroups/' ) ) {
			$body = array( array( 'id' => '120363000000000001@g.us', 'subject' => 'Team', 'size' => 4, 'announce' => false ) );
		} elseif ( false !== strpos( $u, '/flxw/server/db/test' ) ) {
			$body = array( 'ok' => true );
		} elseif ( false !== strpos( $u, '/flxw/server' ) ) {
			$body = array( 'version' => '1.4.0', 'baileys' => '6.7', 'node' => '22.1', 'store' => 'mysql', 'publicUrl' => 'https://evo.example.test', 'keepAlive' => true, 'locked' => array( 'db' => false ), 'db' => array( 'driver' => 'mysql', 'host' => 'db.example.test', 'port' => 3306, 'database' => 'flxw', 'user' => 'flxw', 'password' => $dbpass, 'passwordSet' => true, 'prefix' => 'flxw_' ), 'apiKey' => $dbpass );
		} else {
			$body = array( 'status' => 'SUCCESS' );
		}
	} elseif ( false !== strpos( $u, 'api.netgsm.com.tr/balance' ) ) {
		$body = array( 'code' => '00', 'balance' => 12 );
	} else {
		return $pre;
	}
	return array( 'headers' => array(), 'body' => (string) wp_json_encode( $body ), 'response' => array( 'code' => $code, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
};
add_filter( 'pre_http_request', $stub, 10, 3 );

$saved_hook   = 0;
$deleted_hook = 0;
add_action( 'flxi_connection_saved', static function () use ( &$saved_hook ) { $saved_hook++; } );
add_action( 'flxi_connection_deleted', static function () use ( &$deleted_hook ) { $deleted_hook++; } );

$run2 = static function ( string $name, array $vars = array(), int $user = 0 ) use ( $op, &$seen, $secrets2 ): array {
	$r      = $op( $name, $vars, $user );
	$seen[] = $r;
	$blob   = (string) wp_json_encode( $r );
	foreach ( $secrets2 as $sec ) {
		flxt_ok( false === strpos( $blob, $sec ), $name . ': a secret value is absent' );
	}
	return $r;
};

// Permissions on every root field of this part.
foreach ( array( 'FlxMailConnectionMeta' => array(), 'FlxMailConnections' => array(), 'FlxMailConnection' => array( 'id' => 1 ), 'FlxMailWaState' => array( 'id' => 1 ), 'FlxMailWacloudSetup' => array( 'id' => 1 ), 'FlxMailWacloudTemplates' => array( 'id' => 1 ), 'FlxMailWaServer' => array( 'id' => 1 ), 'FlxMailWaInstances' => array( 'id' => 1 ), 'FlxMailWaGroups' => array( 'id' => 1 ) ) as $name => $vars ) {
	$r = $run2( $name, $vars, 0 );
	flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), $name . ': visitor is unauthenticated' );
	$r = $run2( $name, $vars, $sub );
	flxt_same( 'FORBIDDEN', flxt_code( $r ), $name . ': subscriber is forbidden' );
}
foreach ( array( 'FlxMailConnectionSave' => array( 'type' => 'netgsm' ), 'FlxMailConnectionDelete' => array( 'id' => 1 ), 'FlxMailConnectionSetDefault' => array( 'id' => 1 ), 'FlxMailConnectionTest' => array( 'id' => 1 ), 'FlxMailWaConnect' => array( 'id' => 1 ), 'FlxMailWaRestart' => array( 'id' => 1 ), 'FlxMailWaLogout' => array( 'id' => 1 ), 'FlxMailWaServerUpdate' => array( 'id' => 1 ) ) as $name => $in ) {
	$r = $run2( $name, array( 'input' => $in ), $sub );
	flxt_same( 'FORBIDDEN', flxt_code( $r ), $name . ': subscriber is forbidden' );
	$r = $run2( $name, array( 'input' => $in ), 0 );
	flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), $name . ': visitor is unauthenticated' );
}
flxt_same( 0, count( flxi_list_connections() ), 'denied calls created no connection' );

// Types and form fields come from the same definitions the classic form renders.
$r     = $run2( 'FlxMailConnectionMeta', array(), $admin );
$types = array();
foreach ( (array) ( $r['data']['flxMailConnectionMeta']['types'] ?? array() ) as $t ) {
	$types[ (string) $t['type'] ] = $t;
}
foreach ( array( 'evolution', 'flxwpapi', 'wacloud', 'netgsm' ) as $tp ) {
	flxt_ok( isset( $types[ $tp ] ), 'meta: type ' . $tp . ' is offered' );
}
flxt_same( count( flxi_connection_types() ), count( $types ), 'meta: as many types as the classic form offers' );
foreach ( flxi_connection_types() as $tp => $def ) {
	$want_keys = array();
	foreach ( $def['fields'] as $f ) {
		$want_keys[] = FLXI_Connection_Service::field_name( (string) $f['key'] );
	}
	$got_keys = array_map( static fn( $f ) => (string) $f['name'], (array) ( $types[ $tp ]['fields'] ?? array() ) );
	flxt_same( $want_keys, $got_keys, 'meta: ' . $tp . ' lists the classic form fields in order' );
}
$tok_field = null;
foreach ( (array) ( $types['wacloud']['fields'] ?? array() ) as $f ) {
	if ( 'token' === $f['name'] ) {
		$tok_field = $f;
	}
}
flxt_ok( is_array( $tok_field ) && 'password' === $tok_field['kind'] && true === $tok_field['required'], 'meta: the Cloud token is a required password field' );
$iys = array();
foreach ( (array) ( $types['netgsm']['fields'] ?? array() ) as $f ) {
	if ( 'iysfilter' === $f['name'] ) {
		$iys = array_map( static fn( $o ) => (string) $o['key'], (array) $f['options'] );
	}
}
flxt_same( array( '0', '11', '12' ), $iys, 'meta: numeric select option keys survive' );
flxt_same( array( 'text', 'image', 'audio', 'document', 'template' ), $types['wacloud']['scenarios'] ?? null, 'meta: Cloud API scenarios' );
flxt_same( array( 'text' ), $types['netgsm']['scenarios'] ?? null, 'meta: SMS has the text scenario only' );
flxt_same( true, $types['flxwpapi']['evolution'] ?? null, 'meta: Flux WPAPI talks the Evolution API' );
flxt_same( FLXI_URL . 'assets/test/ornek.jpg', $r['data']['flxMailConnectionMeta']['sampleImageUrl'] ?? null, 'meta: sample image URL' );

// Empty list.
$r = $run2( 'FlxMailConnections', array( 'first' => 50 ), $admin );
flxt_same( null, $r['errors'] ?? null, 'connections: administrator has no errors' );
flxt_same( array(), $r['data']['flxMailConnections']['nodes'] ?? null, 'connections: empty list' );
$r = $run2( 'FlxMailConnection', array( 'id' => 99999 ), $admin );
flxt_same( null, array_key_exists( 'flxMailConnection', (array) ( $r['data'] ?? array() ) ) ? $r['data']['flxMailConnection'] : 'missing', 'unknown connection is null' );

// Validation: payload errors with the GraphQL field name, nothing stored.
$r = $run2( 'FlxMailConnectionSave', array( 'input' => array( 'type' => 'no-such-type', 'name' => 'x' ) ), $admin );
flxt_same( null, $r['errors'] ?? null, 'unknown type: stays in the payload' );
flxt_same( 'INVALID_INPUT', $r['data']['flxMailConnectionSave']['errors'][0]['code'] ?? null, 'unknown type: INVALID_INPUT' );
flxt_same( 'type', $r['data']['flxMailConnectionSave']['errors'][0]['field'] ?? null, 'unknown type: field is type' );
$r = $run2( 'FlxMailConnectionSave', array( 'input' => array( 'type' => 'netgsm', 'name' => 'No creds' ) ), $admin );
flxt_same( 'INVALID_INPUT', $r['data']['flxMailConnectionSave']['errors'][0]['code'] ?? null, 'missing required: INVALID_INPUT' );
flxt_same( 'username', $r['data']['flxMailConnectionSave']['errors'][0]['field'] ?? null, 'missing required: field is username' );
$r = $run2( 'FlxMailConnectionSave', array( 'input' => array( 'type' => 'netgsm', 'username' => '8501112233', 'header' => 'CONTRACT' ) ), $admin );
flxt_same( 'password', $r['data']['flxMailConnectionSave']['errors'][0]['field'] ?? null, 'missing secret: field is password' );
$r = $run2( 'FlxMailConnectionSave', array( 'input' => array( 'id' => 99999, 'name' => 'Ghost' ) ), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'update of an unknown id: NOT_FOUND' );
flxt_same( 0, count( flxi_list_connections() ), 'invalid saves stored nothing' );
flxt_same( 0, $saved_hook, 'invalid saves fired no flxi_connection_saved' );

// Direction 1: GraphQL writes, the classic readers see it.
$r = $run2(
	'FlxMailConnectionSave',
	array( 'input' => array( 'type' => 'netgsm', 'name' => 'Contract SMS', 'username' => '8501112233', 'password' => $nspass, 'header' => 'CONTRACT', 'iysfilter' => '11', 'batchSize' => 500 ) ),
	$admin
);
flxt_same( array(), $r['data']['flxMailConnectionSave']['errors'] ?? null, 'netgsm save: no field errors' );
$sms = $r['data']['flxMailConnectionSave']['connection'] ?? array();
$sms_id = (int) ( $sms['databaseId'] ?? 0 );
flxt_ok( $sms_id > 0, 'netgsm save: connection id' );
flxt_same( 'SMS', $sms['channel'] ?? null, 'netgsm save: channel is SMS' ) ;
flxt_same( true, $sms['isDefault'] ?? null, 'netgsm save: the first SMS connection becomes the default' );
flxt_same( true, $sms['password']['set'] ?? null, 'netgsm save: password is marked set' );
flxt_ok( ! array_key_exists( 'value', (array) ( $sms['password'] ?? array() ) ), 'netgsm save: the secret has no value field' );
flxt_same( '••••••••', $sms['password']['mask'] ?? null, 'netgsm save: the mask is the fixed placeholder, not a piece of the password' );
flxt_same( 1, $saved_hook, 'netgsm save: flxi_connection_saved ran once' );
$classic_row = flxi_get_connection( $sms_id );
flxt_same( '8501112233', $classic_row['config']['username'] ?? null, 'classic reader sees the GraphQL username' );
flxt_same( $nspass, $classic_row['config']['password'] ?? null, 'classic reader decrypts the GraphQL password' );
flxt_same( '11', $classic_row['config']['iysfilter'] ?? null, 'classic reader sees the İYS filter' );
flxt_same( 500, (int) ( $classic_row['config']['batch_size'] ?? 0 ), 'classic reader sees the batch size' );
$raw_cfg = (string) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT config FROM ' . flxi_table( 'connections' ) . ' WHERE id = %d', $sms_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
flxt_ok( false === strpos( $raw_cfg, $nspass ), 'the password is stored encrypted' );

// Partial update keeps everything else, an empty secret keeps the password.
$r = $run2( 'FlxMailConnectionSave', array( 'input' => array( 'id' => $sms_id, 'name' => 'Renamed SMS', 'password' => '' ) ), $admin );
$sms2 = $r['data']['flxMailConnectionSave']['connection'] ?? array();
flxt_same( 'Renamed SMS', $sms2['name'] ?? null, 'update: name changed' );
flxt_same( '11', $sms2['iysfilter'] ?? null, 'update: omitted fields keep their value' );
flxt_same( true, $sms2['password']['set'] ?? null, 'update: an empty secret keeps the stored password' );
flxt_same( $nspass, flxi_get_connection( $sms_id )['config']['password'] ?? null, 'update: the classic reader still decrypts the password' );
flxt_same( 2, $saved_hook, 'update: flxi_connection_saved ran once more' );
// Clearing a required secret is refused and keeps the stored one.
$r = $run2( 'FlxMailConnectionSave', array( 'input' => array( 'id' => $sms_id, 'password' => '__clear__' ) ), $admin );
flxt_same( 'password', $r['data']['flxMailConnectionSave']['errors'][0]['field'] ?? null, '__clear__ on a required secret: field error' );
flxt_same( $nspass, flxi_get_connection( $sms_id )['config']['password'] ?? null, '__clear__ on a required secret keeps the stored password' );
flxt_same( 2, $saved_hook, 'a refused save fires no hook' );

// Direction 2: the classic form writer (what admin-post calls), GraphQL reads.
$wac_id = FLXI_Connection_Service::save_form(
	0,
	'wacloud',
	'Classic Cloud',
	array( 'phone_id' => '109876543210', 'waba_id' => '556677', 'token' => $token, 'app_secret' => $apisec, 'graph' => 'v23.0', 'link_preview' => 1, 'delay' => 2 ),
	true
);
flxt_ok( is_int( $wac_id ) && $wac_id > 0, 'classic form writer creates a Cloud connection' );
flxt_same( 3, $saved_hook, 'classic form save fires flxi_connection_saved once' );
flxt_ok( '' !== flxi_wacloud_verify_token( $wac_id ), 'the webhook verify value is created on first save' );
$verify = flxi_wacloud_verify_token( $wac_id );
$r      = $run2( 'FlxMailConnection', array( 'id' => $wac_id ), $admin );
$wac    = $r['data']['flxMailConnection'] ?? array();
flxt_same( 'Classic Cloud', $wac['name'] ?? null, 'GraphQL reads the classic name' );
flxt_same( 'WHATSAPP', $wac['channel'] ?? null, 'GraphQL reads the classic channel' );
flxt_same( '109876543210', $wac['phoneId'] ?? null, 'GraphQL reads the classic phone id' );
flxt_same( '556677', $wac['wabaId'] ?? null, 'GraphQL reads the classic WABA id' );
flxt_same( true, $wac['linkPreview'] ?? null, 'GraphQL reads the classic checkbox' );
flxt_same( 2, $wac['delay'] ?? null, 'GraphQL reads the classic number' );
flxt_same( true, $wac['token']['set'] ?? null, 'GraphQL sees the classic token as set' );
flxt_same( false, $wac['appSecretMissing'] ?? null, 'App Secret is stored, so the webhook is not off' );
flxt_same( true, $wac['isDefault'] ?? null, 'the first WhatsApp connection is the default' );
flxt_ok( array_key_exists( 'waState', $wac ), 'WhatsApp state field is present' );
$r = $run2( 'FlxMailConnections', array( 'first' => 50 ), $admin );
flxt_same( 2, $r['data']['flxMailConnections']['totalCount'] ?? null, 'list: both connections' );
$r = $run2( 'FlxMailConnections', array( 'channel' => 'SMS', 'first' => 50 ), $admin );
flxt_same( array( $sms_id ), array_map( static fn( $n ) => $n['databaseId'], (array) ( $r['data']['flxMailConnections']['nodes'] ?? array() ) ), 'list: channel filter' );

// Classic form update with a blank secret keeps the stored one (what the form posts).
$again = FLXI_Connection_Service::save_form( $wac_id, 'wacloud', 'Classic Cloud', array( 'phone_id' => '109876543210', 'waba_id' => '556677', 'token' => '', 'app_secret' => '', 'graph' => 'v23.0' ), true );
flxt_same( $wac_id, $again, 'classic form update returns the id' );
flxt_same( $token, flxi_get_connection( $wac_id )['config']['token'] ?? null, 'classic blank secret keeps the stored token' );
flxt_same( $verify, flxi_wacloud_verify_token( $wac_id ), 'the verify value does not change on update' );
// A secret stays write-only: update it through GraphQL, the classic reader sees the new value.
$r = $run2( 'FlxMailConnectionSave', array( 'input' => array( 'id' => $wac_id, 'token' => $token . '-2' ) ), $admin );
flxt_same( true, $r['data']['flxMailConnectionSave']['connection']['token']['set'] ?? null, 'GraphQL secret write: set' );
flxt_same( $token . '-2', flxi_get_connection( $wac_id )['config']['token'] ?? null, 'classic reader sees the new GraphQL token' );
flxt_same( 'v23.0', $r['data']['flxMailConnectionSave']['connection']['graph'] ?? null, 'GraphQL update keeps the classic Graph version' );
FLXI_Connection_Service::save_form( $wac_id, 'wacloud', 'Classic Cloud', array( 'phone_id' => '109876543210', 'waba_id' => '556677', 'token' => $token, 'app_secret' => '', 'graph' => 'v23.0' ), true );

// WhatsApp Cloud setup: manager-only, matches REST, App Secret never returned.
$r     = $run2( 'FlxMailWacloudSetup', array( 'id' => $wac_id ), $admin );
$setup = $r['data']['flxMailWacloudSetup'] ?? array();
flxt_same( rest_url( 'flxi/v1/wacloud/' . $wac_id . '/webhook' ), $setup['webhookUrl'] ?? null, 'setup: webhook URL is the REST webhook route' );
flxt_same( $verify, $setup['webhookVerifyValue'] ?? null, 'setup: verify value is the stored handshake string' );
flxt_same( true, $setup['signed'] ?? null, 'setup: signed' );
flxt_same( true, $setup['hasWaba'] ?? null, 'setup: has WABA' );
flxt_same( '+90 555 111 22 33', $setup['phone']['displayPhoneNumber'] ?? null, 'setup: phone details come from Meta' );
flxt_same( 'GREEN', $setup['phone']['qualityRating'] ?? null, 'setup: quality rating' );
flxt_ok( array_key_exists( 'lastEventAt', $setup ) && null === $setup['lastEventAt'], 'setup: no webhook event yet' );
update_option( 'flxi_wac_hook_' . $wac_id, array( 'verify' => 1700000000, 'event' => 1700000100, 'bad_signature' => 1700000200 ) );
$r     = $run2( 'FlxMailWacloudSetup', array( 'id' => $wac_id ), $admin );
$setup = $r['data']['flxMailWacloudSetup'] ?? array();
flxt_same( '2023-11-14T22:15:00Z', $setup['lastEventAt']['iso'] ?? null, 'setup: lastEventAt is FlxDateTime from the stored Unix time' );
flxt_same( '2023-11-14T22:16:40Z', $setup['badSignatureAt']['iso'] ?? null, 'setup: badSignatureAt' );
wp_set_current_user( $admin );
$rest = rest_do_request( new WP_REST_Request( 'GET', '/flxi/v1/wacloud/' . $wac_id . '/setup' ) );
flxt_same( 200, $rest->get_status(), 'REST setup route still answers' );
flxt_same( $verify, $rest->get_data()['verify'] ?? null, 'REST setup route returns the same verify value' );
flxt_same( rest_url( 'flxi/v1/wacloud/' . $wac_id . '/webhook' ), $rest->get_data()['webhook'] ?? null, 'REST setup route returns the same webhook URL' );
foreach ( array( 'GET', 'POST' ) as $m ) {
	$routes = rest_get_server()->get_routes();
	$found  = false;
	foreach ( $routes as $route => $handlers ) {
		if ( false !== strpos( (string) $route, '/wacloud/' ) && false !== strpos( (string) $route, '/webhook' ) ) {
			foreach ( $handlers as $h ) {
				if ( isset( $h['methods'][ $m ] ) ) {
					$found = true;
				}
			}
		}
	}
	flxt_same( true, $found, 'the Cloud webhook stays a REST route (' . $m . ')' );
}
$r = $run2( 'FlxMailWacloudSetup', array( 'id' => $sms_id ), $admin );
flxt_ok( array_key_exists( 'flxMailWacloudSetup', (array) $r['data'] ) && null === $r['data']['flxMailWacloudSetup'], 'setup of a non-Cloud connection is null' );
$r = $run2( 'FlxMailWacloudSetup', array( 'id' => 99999 ), $admin );
flxt_ok( array_key_exists( 'flxMailWacloudSetup', (array) $r['data'] ) && null === $r['data']['flxMailWacloudSetup'], 'setup of an unknown connection is null' );
flxt_ok( false === get_option( 'flxi_wac_verify_99999', false ), 'setup of an unknown connection creates no verify value' );

// Templates: GraphQL equals the REST list.
$r    = $run2( 'FlxMailWacloudTemplates', array( 'id' => $wac_id, 'first' => 50 ), $admin );
$tpls = $r['data']['flxMailWacloudTemplates']['nodes'] ?? array();
$rest = rest_do_request( new WP_REST_Request( 'GET', '/flxi/v1/wacloud/' . $wac_id . '/templates' ) );
flxt_same( $rest->get_data(), $tpls, 'templates: GraphQL nodes equal the REST list' );
flxt_same( 'order_update', $tpls[0]['name'] ?? null, 'templates: name' );
flxt_same( 2, $tpls[0]['params'] ?? null, 'templates: placeholder count' );
$no_waba = FLXI_Connection_Service::save_form( 0, 'wacloud', 'No WABA', array( 'phone_id' => '1', 'token' => 'T', 'app_secret' => 'S' ), false );
$r       = $run2( 'FlxMailWacloudTemplates', array( 'id' => (int) $no_waba, 'first' => 50 ), $admin );
flxt_same( 'INVALID_INPUT', flxt_code( $r ), 'templates: no WABA id is INVALID_INPUT' );
FLXI_Connection_Service::delete( (int) $no_waba, false, false );

// Connection test: one run, one stored result, visible to both sides.
$tests_before = (array) get_option( 'flxi_conn_tests', array() );
$r = $run2( 'FlxMailConnectionTest', array( 'input' => array( 'id' => $sms_id ) ), $admin );
flxt_same( true, $r['data']['flxMailConnectionTest']['ok'] ?? null, 'test: balance check passes' );
$stored = (array) get_option( 'flxi_conn_tests', array() );
flxt_same( true, ! empty( $stored[ $sms_id ]['ok'] ), 'test: the result is stored in flxi_conn_tests' );
flxt_same( true, $r['data']['flxMailConnectionTest']['connection']['lastTest']['ok'] ?? null, 'test: lastTest is on the returned connection' );
flxt_ok( is_string( $r['data']['flxMailConnectionTest']['connection']['lastTest']['at']['iso'] ?? null ), 'test: lastTest.at is FlxDateTime' );
$rest = rest_do_request( ( static function () use ( $wac_id ) {
	$q = new WP_REST_Request( 'POST', '/flxi/v1/connection-test' );
	$q->set_param( 'id', $wac_id );
	return $q;
} )() );
flxt_same( 200, $rest->get_status(), 'REST connection-test route still answers' );
flxt_same( true, $rest->get_data()['ok'] ?? null, 'REST connection-test returns ok for the Cloud check' );
$r = $run2( 'FlxMailConnection', array( 'id' => $wac_id ), $admin );
flxt_same( true, $r['data']['flxMailConnection']['lastTest']['ok'] ?? null, 'GraphQL reads the test result the REST route stored' );
$r = $run2( 'FlxMailConnectionTest', array( 'input' => array( 'id' => $sms_id, 'scenario' => 'image', 'to' => '05551112233' ) ), $admin );
flxt_same( false, $r['data']['flxMailConnectionTest']['ok'] ?? null, 'test: an unsupported scenario fails' );
flxt_ok( '' !== ( $r['data']['flxMailConnectionTest']['error'] ?? '' ), 'test: an unsupported scenario says why' );
$r = $run2( 'FlxMailConnectionTest', array( 'input' => array( 'id' => $wac_id, 'scenario' => 'template', 'to' => '05551112233' ) ), $admin );
flxt_same( false, $r['data']['flxMailConnectionTest']['ok'] ?? null, 'test: the template scenario needs a template' );

// Evolution and Flux WPAPI: state, QR, restart, logout, instances, groups, server.
$evo_cfg = array( 'base_url' => 'https://evo.example.test', 'api_key' => $apikey, 'instance' => 'shop', 'version' => 'v2' );
$r       = $run2( 'FlxMailConnectionSave', array( 'input' => array_merge( array( 'type' => 'evolution', 'name' => 'Shop line', 'baseUrl' => $evo_cfg['base_url'], 'apiKey' => $apikey, 'instance' => 'shop', 'delay' => 6, 'dailyLimit' => 120, 'quietFrom' => '22' ) ) ), $admin );
flxt_same( array(), $r['data']['flxMailConnectionSave']['errors'] ?? null, 'evolution save: no field errors' );
$evo    = $r['data']['flxMailConnectionSave']['connection'] ?? array();
$evo_id = (int) ( $evo['databaseId'] ?? 0 );
flxt_same( 6, $evo['delay'] ?? null, 'evolution save: delay' );
flxt_same( 120, $evo['dailyLimit'] ?? null, 'evolution save: daily limit (ban protection field)' );
flxt_same( '22', $evo['quietFrom'] ?? null, 'evolution save: quiet hours select' );
flxt_same( $apikey, flxi_get_connection( $evo_id )['config']['api_key'] ?? null, 'classic reader decrypts the API key' );
flxt_same( 120, (int) ( flxi_wa_guard_conf( flxi_get_connection( $evo_id ) )['daily'] ?? 0 ), 'classic ban protection reads the GraphQL daily limit' );
$copy = $run2( 'FlxMailConnectionSave', array( 'input' => array( 'type' => 'evolution', 'name' => 'Second number', 'instance' => 'spare', 'copyFrom' => $evo_id ) ), $admin );
flxt_same( array(), $copy['data']['flxMailConnectionSave']['errors'] ?? null, 'copyFrom: no field errors' );
$copy_id = (int) ( $copy['data']['flxMailConnectionSave']['connection']['databaseId'] ?? 0 );
flxt_same( 'https://evo.example.test', flxi_get_connection( $copy_id )['config']['base_url'] ?? null, 'copyFrom: the server address is copied' );
flxt_same( $apikey, flxi_get_connection( $copy_id )['config']['api_key'] ?? null, 'copyFrom: the API key is copied without leaving the server' );
$bad_copy = $run2( 'FlxMailConnectionSave', array( 'input' => array( 'type' => 'evolution', 'name' => 'Bad copy', 'instance' => 'x', 'copyFrom' => $sms_id ) ), $admin );
flxt_same( 'copyFrom', $bad_copy['data']['flxMailConnectionSave']['errors'][0]['field'] ?? null, 'copyFrom from another type: field error' );
$r = $run2( 'FlxMailConnection', array( 'id' => $evo_id ), $admin );
flxt_same( 'WHATSAPP', $r['data']['flxMailConnection']['channel'] ?? null, 'evolution is a WhatsApp connection' );

$r = $run2( 'FlxMailWaState', array( 'id' => $evo_id, 'fresh' => true ), $admin );
flxt_same( 'open', $r['data']['flxMailWaState']['state'] ?? null, 'wa state: fresh asks the server' );
flxt_same( '+905551112233', $r['data']['flxMailWaState']['me'] ?? null, 'wa state: linked number' );
flxt_same( 'Contract Shop', $r['data']['flxMailWaState']['name'] ?? null, 'wa state: profile name' );
$rest = rest_do_request( new WP_REST_Request( 'GET', '/flxi/v1/wa/' . $evo_id . '/state' ) );
flxt_same( 'open', $rest->get_data()['state'] ?? null, 'REST wa state sees what GraphQL stored' );
flxt_same( $rest->get_data()['me'] ?? null, $r['data']['flxMailWaState']['me'] ?? null, 'REST and GraphQL agree on the number' );
$r = $run2( 'FlxMailConnection', array( 'id' => $evo_id ), $admin );
flxt_same( 'open', $r['data']['flxMailConnection']['waState']['state'] ?? null, 'the connection carries the cached state' );

$r = $run2( 'FlxMailWaConnect', array( 'input' => array( 'id' => $evo_id ) ), $admin );
flxt_same( array(), $r['data']['flxMailWaConnect']['errors'] ?? null, 'wa connect: no errors' );
flxt_same( 'AB12CD34', $r['data']['flxMailWaConnect']['pairing'] ?? null, 'wa connect: pairing code' );
flxt_ok( 0 === strpos( (string) ( $r['data']['flxMailWaConnect']['qr'] ?? '' ), 'data:image/png;base64,' ), 'wa connect: QR is an image data URI' );
$r = $run2( 'FlxMailWaConnect', array( 'input' => array( 'id' => $wac_id ) ), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'wa connect on a Cloud connection is NOT_FOUND' );
$r = $run2( 'FlxMailWaRestart', array( 'input' => array( 'id' => $evo_id ) ), $admin );
flxt_same( 'open', $r['data']['flxMailWaRestart']['state']['state'] ?? null, 'wa restart: state' );
$r = $run2( 'FlxMailWaLogout', array( 'input' => array( 'id' => $evo_id ) ), $admin );
flxt_same( true, $r['data']['flxMailWaLogout']['ok'] ?? null, 'wa logout: ok' );
flxt_same( 'close', $r['data']['flxMailWaLogout']['state']['state'] ?? null, 'wa logout: state is close' );
flxt_same( 'close', flxi_wa_cached_state( $evo_id )['state'], 'classic cached state sees the logout' );
$r = $run2( 'FlxMailWaInstances', array( 'id' => $evo_id ), $admin );
flxt_same( array( 'shop', 'spare' ), array_map( static fn( $i ) => $i['name'], (array) ( $r['data']['flxMailWaInstances'] ?? array() ) ), 'wa instances: names' );
flxt_same( $evo_id, $r['data']['flxMailWaInstances'][0]['used'] ?? null, 'wa instances: the one used here is marked' );
$rest = rest_do_request( new WP_REST_Request( 'GET', '/flxi/v1/wa/' . $evo_id . '/instances' ) );
flxt_same( $rest->get_data(), $r['data']['flxMailWaInstances'] ?? null, 'wa instances: GraphQL equals REST' );
$r = $run2( 'FlxMailWaGroups', array( 'id' => $evo_id, 'fresh' => true ), $admin );
flxt_same( '120363000000000001@g.us', $r['data']['flxMailWaGroups'][0]['id'] ?? null, 'wa groups: id' );
$rest = rest_do_request( new WP_REST_Request( 'GET', '/flxi/v1/wa/' . $evo_id . '/groups' ) );
flxt_same( $rest->get_data(), $r['data']['flxMailWaGroups'] ?? null, 'wa groups: GraphQL equals REST' );
$r = $run2( 'FlxMailWaGroups', array( 'id' => $wac_id ), $admin );
flxt_same( 'INVALID_INPUT', flxt_code( $r ), 'wa groups on Cloud API: not supported' );
$r = $run2( 'FlxMailWaServer', array( 'id' => $evo_id ), $admin );
flxt_ok( null !== flxt_code( $r ), 'wa server on a plain Evolution connection is refused' );

$wpapi = $run2( 'FlxMailConnectionSave', array( 'input' => array( 'type' => 'flxwpapi', 'name' => 'Bridge', 'baseUrl' => 'https://evo.example.test', 'apiKey' => $apikey, 'instance' => 'bridge' ) ), $admin );
$wpapi_id = (int) ( $wpapi['data']['flxMailConnectionSave']['connection']['databaseId'] ?? 0 );
flxt_ok( $wpapi_id > 0, 'flxwpapi save: id' );
$r = $run2( 'FlxMailWaServer', array( 'id' => $wpapi_id ), $admin );
flxt_same( '1.4.0', $r['data']['flxMailWaServer']['version'] ?? null, 'wa server: version' );
flxt_same( true, $r['data']['flxMailWaServer']['db']['passwordSet'] ?? null, 'wa server: password is only marked set' );
$rest = rest_do_request( new WP_REST_Request( 'GET', '/flxi/v1/wa/' . $wpapi_id . '/server' ) );
flxt_ok( ! isset( $rest->get_data()['db']['password'] ) && ! isset( $rest->get_data()['apiKey'] ), 'REST wa server still strips the password and key' );
$r = $run2( 'FlxMailWaServerUpdate', array( 'input' => array( 'id' => $wpapi_id, 'publicUrl' => 'https://evo.example.test', 'keepAlive' => true, 'db' => array( 'driver' => 'mysql', 'host' => 'db.example.test', 'database' => 'flxw', 'user' => 'flxw', 'password' => $dbpass ) ) ), $admin );
flxt_same( true, $r['data']['flxMailWaServerUpdate']['ok'] ?? null, 'wa server update: ok' );
flxt_same( 'mysql', $r['data']['flxMailWaServerUpdate']['server']['db']['driver'] ?? null, 'wa server update: returns the clean server' );
$r = $run2( 'FlxMailWaServerUpdate', array( 'input' => array( 'id' => $wpapi_id, 'test' => true, 'db' => array( 'driver' => 'mysql', 'host' => 'db.example.test', 'password' => $dbpass ) ) ), $admin );
flxt_same( true, $r['data']['flxMailWaServerUpdate']['ok'] ?? null, 'wa server test: ok' );
flxt_ok( array_key_exists( 'server', (array) $r['data']['flxMailWaServerUpdate'] ) && null === $r['data']['flxMailWaServerUpdate']['server'], 'wa server test: saves nothing and returns no server' );
$dbpass_in_wp = false;
foreach ( array( 'flxi_conn_tests', 'flxi_wa_state' ) as $opt ) {
	if ( false !== strpos( (string) wp_json_encode( get_option( $opt ) ), $dbpass ) ) {
		$dbpass_in_wp = true;
	}
}
flxt_same( false, $dbpass_in_wp, 'the bridge database password is not kept in WordPress' );

// Default and delete, with their side effects.
$r = $run2( 'FlxMailConnectionSetDefault', array( 'input' => array( 'id' => $copy_id ) ), $admin );
flxt_same( true, $r['data']['flxMailConnectionSetDefault']['connection']['isDefault'] ?? null, 'set default: the connection is the default' );
flxt_same( $copy_id, (int) ( flxi_resolve_connection( 'whatsapp' )['id'] ?? 0 ), 'classic resolver picks the new default' );
$r = $run2( 'FlxMailConnectionSetDefault', array( 'input' => array( 'id' => 99999 ) ), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'set default of an unknown id: NOT_FOUND' );
$deleted_before = $deleted_hook;
$r = $run2( 'FlxMailConnectionDelete', array( 'input' => array( 'id' => $wac_id ) ), $admin );
flxt_same( true, $r['data']['flxMailConnectionDelete']['ok'] ?? null, 'delete: ok' );
flxt_same( $deleted_before + 1, $deleted_hook, 'delete: flxi_connection_deleted ran once' );
flxt_same( null, flxi_get_connection( $wac_id ), 'delete: the classic reader no longer finds it' );
flxt_same( false, get_option( 'flxi_wac_verify_' . $wac_id, false ), 'delete: the verify value is removed' );
$r = $run2( 'FlxMailConnectionDelete', array( 'input' => array( 'id' => $wac_id ) ), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'delete of a deleted connection: NOT_FOUND' );
$r = $run2( 'FlxMailConnectionDelete', array( 'input' => array( 'id' => $sms_id, 'remote' => true ) ), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'remote delete of an SMS connection is refused' );
flxt_ok( null !== flxi_get_connection( $sms_id ), 'a refused remote delete keeps the connection' );
$r = $run2( 'FlxMailConnectionDelete', array( 'input' => array( 'id' => $wpapi_id, 'remote' => true ) ), $admin );
flxt_same( true, $r['data']['flxMailConnectionDelete']['ok'] ?? null, 'remote delete: ok' );
// The classic delete handler path.
$deleted_before = $deleted_hook;
flxt_same( true, FLXI_Connection_Service::delete( $copy_id, false, false ), 'classic delete path succeeds' );
flxt_same( $deleted_before + 1, $deleted_hook, 'classic delete: flxi_connection_deleted ran once' );
flxt_same( true, FLXI_Connection_Service::set_default( $evo_id, false ), 'classic default path succeeds' );
flxt_same( $evo_id, (int) ( flxi_resolve_connection( 'whatsapp' )['id'] ?? 0 ), 'classic default path moves the default' );
FLXI_Connection_Service::delete( $evo_id, false, false );
FLXI_Connection_Service::delete( $sms_id, false, false );
flxt_same( 0, count( flxi_list_connections() ), 'every test connection is gone' );
remove_filter( 'pre_http_request', $stub, 10 );

// The Classic switch URL handed to the React screen is a plain URL (wp_nonce_url() would HTML-escape it).
if ( ! function_exists( 'flxi_ui_switch_url' ) ) {
	require_once FLXI_DIR . 'includes/admin/flux-ui.php';
}
$_GET['page'] = 'flxi-baglantilar';
$_GET['edit'] = '7';
$switch       = flxi_ui_switch_url( 'classic' );
unset( $_GET['page'], $_GET['edit'] );
flxt_ok( false === strpos( $switch, '&#038;' ) && false === strpos( $switch, '&amp;' ), 'classic switch URL is not HTML-escaped' );
flxt_ok( false !== strpos( $switch, '_wpnonce=' ) && false !== strpos( $switch, 'flxi_ui=classic' ) && false !== strpos( $switch, 'edit=7' ), 'classic switch URL carries the nonce, the ui flag and the edited connection' );

// One more pass over every captured response.
$blob = (string) wp_json_encode( $seen );
flxt_ok( false === strpos( $blob, $secret ), 'no captured response contains the secret' );
flxt_ok( false === strpos( $blob, 'smtp_pass' ), 'stored secret key name is not a response field' );
foreach ( $secrets2 as $sec ) {
	flxt_ok( false === strpos( $blob, $sec ), 'no captured response contains a connection secret' );
}

flxt_done();
