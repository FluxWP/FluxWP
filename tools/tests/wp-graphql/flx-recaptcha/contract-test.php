<?php
/**
 * Flux reCAPTCHA GraphQL contract tests. The documents are the persisted operations the reCAPTCHA
 * screen sends (plugins/flx-recaptcha/graphql/persisted-queries.json). The classic page handlers and
 * GraphQL share FLXRC_Settings_Service, so the classic behaviour is checked through the service too.
 * Google is mocked (pre_http_request); the real service is only reachable from a live site.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-recaptcha/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux reCAPTCHA GraphQL\n";

$GLOBALS['flxrc_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXRC_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxrc_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 4, count( $GLOBALS['flxrc_ops'] ), 'Flux reCAPTCHA ships 4 persisted operations' );

function flxrc_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxrc_ops'][ $name ], $vars, $user );
}

/** [code, field] pairs of a payload's errors. */
function flxrc_t_errs( array $r, string $field ): array {
	return array_map( static fn( $e ) => array( $e['code'], $e['field'] ), $r['data'][ $field ]['errors'] ?? array() );
}

// Google is mocked: $GLOBALS['flxt_google'] is the siteverify body (false = unreachable).
$GLOBALS['flxt_google'] = array( 'success' => true, 'score' => 0.9, 'hostname' => 'example.com', 'action' => 'login' );
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		if ( ! str_contains( (string) $url, 'siteverify' ) ) {
			return $pre;
		}
		$GLOBALS['flxt_google_seen'] = $args['body'] ?? array();
		if ( false === $GLOBALS['flxt_google'] ) {
			return new WP_Error( 'http_request_failed', 'boom' );
		}
		return array( 'headers' => array(), 'body' => wp_json_encode( $GLOBALS['flxt_google'] ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
	},
	10,
	3
);

// A package form (context) and a public mutation that gates itself with the captcha service.
add_filter(
	'flx_captcha_contexts',
	static function ( $c ) {
		$c['flxt_form'] = array( 'label' => 'Test form', 'desc' => 'Contract test', 'group' => 'other', 'default' => 1, 'action' => 'flxt_form' );
		return $c;
	}
);
add_action(
	'flx_gql_register',
	static function () {
		flx_gql_mutation(
			'flxRecaptchaTestGate',
			array(
				'description' => 'Contract test: a public mutation behind the captcha.',
				'cap'         => 'public',
				'input'       => array( 'captchaToken' => array( 'type' => 'String', 'description' => 'Token.' ) ),
				'output'      => array( 'done' => array( 'type' => 'Boolean', 'description' => 'Ran.' ) ),
				'resolve'     => static function ( array $input ) {
					do_action( 'flx_captcha_gql_verify', 'flxt_form', $input['captchaToken'] ?? null );
					return array( 'done' => true );
				},
			)
		);
	}
);
$gate = 'mutation($t: String) { flxRecaptchaTestGate(input: { captchaToken: $t }) { done errors { code field message } } }';

$admin = 1;
$sub   = (int) wp_insert_user( array( 'user_login' => 'flxrc_sub_' . wp_rand(), 'user_email' => 'flxrc_sub_' . wp_rand() . '@example.com', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
delete_option( 'flxrc_settings' );
delete_option( 'flxrc_stats' );
delete_option( 'flxrc_status' );
flxrc_settings( 'flush' );

// Permissions.
$r = flxrc_t_op( 'FlxRecaptchaScreen' );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'screen: visitor → UNAUTHENTICATED' );
$r = flxrc_t_op( 'FlxRecaptchaScreen', array(), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'screen: subscriber → FORBIDDEN' );
$r = flxrc_t_op( 'FlxRecaptchaSettingsUpdate', array( 'input' => array( 'threshold' => 0.4 ) ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'update: subscriber → FORBIDDEN' );
$r = flxrc_t_op( 'FlxRecaptchaKeysTest', array( 'input' => array( 'version' => 'V3', 'token' => 'x' ) ), 0 );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'keys test: visitor → UNAUTHENTICATED' );

// Screen, defaults (no keys).
$r = flxrc_t_op( 'FlxRecaptchaScreen', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'screen: no errors for the administrator' );
$d = $r['data'] ?? array();
flxt_same( array( 'V3', '', false, '', 0.5, 'GOOGLE', 'AUTO', 'NORMAL', false, true, true, array(), false, false ), array(
	$d['flxRecaptchaSettings']['version'] ?? null,
	$d['flxRecaptchaSettings']['siteKey'] ?? null,
	$d['flxRecaptchaSettings']['secretSaved'] ?? null,
	$d['flxRecaptchaSettings']['secretHint'] ?? null,
	$d['flxRecaptchaSettings']['threshold'] ?? null,
	$d['flxRecaptchaSettings']['domain'] ?? null,
	$d['flxRecaptchaSettings']['v2Theme'] ?? null,
	$d['flxRecaptchaSettings']['v2Size'] ?? null,
	$d['flxRecaptchaSettings']['hideBadge'] ?? null,
	$d['flxRecaptchaSettings']['skipLoggedIn'] ?? null,
	$d['flxRecaptchaSettings']['failOpen'] ?? null,
	$d['flxRecaptchaSettings']['allowIps'] ?? null,
	$d['flxRecaptchaSettings']['keysVerified'] ?? null,
	$d['flxRecaptchaSettings']['disabledByConstant'] ?? null,
), 'screen: defaults (v3, 0.5, skip signed-in, fail open, nothing saved)' );
$forms = array_column( $d['flxRecaptchaForms'] ?? array(), null, 'key' );
flxt_same( array_keys( flxrc_contexts() ), array_keys( $forms ), 'screen: forms in the classic order (core + registered contexts)' );
flxt_same( array( 'Login', 'WORDPRESS', 'WordPress', true, true, 0, 0 ), array( $forms['login']['label'] ?? null, $forms['login']['group'] ?? null, $forms['login']['groupLabel'] ?? null, $forms['login']['available'] ?? null, $forms['login']['enabled'] ?? null, $forms['login']['passed'] ?? null, $forms['login']['blocked'] ?? null ), 'screen: login form' );
flxt_same( array( 'OTHER', 'Other plugins' ), array( $forms['flxt_form']['group'] ?? null, $forms['flxt_form']['groupLabel'] ?? null ), 'screen: a package form lands in Other plugins' );
flxt_same( array( 'AWAITING_TEST', 'V3', true, true ), array( $d['flxRecaptchaStatus']['state'] ?? null, $d['flxRecaptchaStatus']['version'] ?? null, array_key_exists( 'lastTest', $d['flxRecaptchaStatus'] ?? array() ) && null === $d['flxRecaptchaStatus']['lastTest'], array_key_exists( 'lastError', $d['flxRecaptchaStatus'] ?? array() ) && null === $d['flxRecaptchaStatus']['lastError'] ), 'screen: awaiting test, no log yet' );

// Keys test: missing keys, a failed test (nothing saved), then a passing one.
$r = flxrc_t_op( 'FlxRecaptchaKeysTest', array( 'input' => array( 'version' => 'V3', 'siteKey' => '', 'secret' => '', 'token' => 'tok' ) ), $admin );
flxt_same( array( array( 'INVALID_INPUT', 'siteKey' ) ), flxrc_t_errs( $r, 'flxRecaptchaKeysTest' ), 'keys test: no site key → INVALID_INPUT siteKey' );
$r = flxrc_t_op( 'FlxRecaptchaKeysTest', array( 'input' => array( 'version' => 'V3', 'siteKey' => 'SITE_key-1', 'secret' => '', 'token' => 'tok' ) ), $admin );
flxt_same( array( array( 'INVALID_INPUT', 'secret' ) ), flxrc_t_errs( $r, 'flxRecaptchaKeysTest' ), 'keys test: no secret key → INVALID_INPUT secret' );

$GLOBALS['flxt_google'] = array( 'success' => false, 'error-codes' => array( 'invalid-input-secret' ) );
$r = flxrc_t_op( 'FlxRecaptchaKeysTest', array( 'input' => array( 'version' => 'V3', 'siteKey' => 'SITE_key-1', 'secret' => 'SECRET_key-1', 'token' => 'tok' ) ), $admin );
$p = $r['data']['flxRecaptchaKeysTest'] ?? array();
flxt_same( array( false, array(), false, false ), array( $p['result']['ok'] ?? null, $p['errors'] ?? null, $p['settings']['secretSaved'] ?? null, $p['settings']['keysVerified'] ?? null ), 'keys test: Google rejects the secret → ok false, nothing saved' );
flxt_ok( str_contains( (string) ( $p['result']['message'] ?? '' ), 'secret key is invalid' ), 'keys test: the reason is in the message' );
flxt_same( array( false, 'tok' ), array( ( $p['status']['lastTest']['ok'] ?? null ), $GLOBALS['flxt_google_seen']['response'] ?? null ), 'keys test: the failed run is the last test; the token went to Google' );

$GLOBALS['flxt_google'] = array( 'success' => true, 'hostname' => 'example.com' ); // No score: v2 keys.
$r = flxrc_t_op( 'FlxRecaptchaKeysTest', array( 'input' => array( 'version' => 'V3', 'siteKey' => 'SITE_key-1', 'secret' => 'SECRET_key-1', 'token' => 'tok' ) ), $admin );
$p = $r['data']['flxRecaptchaKeysTest'] ?? array();
flxt_same( array( false, true ), array( $p['result']['ok'] ?? null, str_contains( (string) ( $p['result']['message'] ?? '' ), 'v2 (checkbox) keys' ) ), 'keys test: v2 keys with v3 selected → ok false' );

$GLOBALS['flxt_google'] = array( 'success' => true, 'score' => 0.9, 'hostname' => 'example.com' );
$r = flxrc_t_op( 'FlxRecaptchaKeysTest', array( 'input' => array( 'version' => 'V3', 'siteKey' => 'SITE_key-1', 'secret' => 'SECRET_key-1', 'token' => 'tok2' ) ), $admin );
$p = $r['data']['flxRecaptchaKeysTest'] ?? array();
flxt_same( array( true, 0.9, array(), 'SITE_key-1', true, '••••ey-1', true, 'ON' ), array(
	$p['result']['ok'] ?? null,
	$p['result']['score'] ?? null,
	$p['errors'] ?? null,
	$p['settings']['siteKey'] ?? null,
	$p['settings']['secretSaved'] ?? null,
	$p['settings']['secretHint'] ?? null,
	$p['settings']['keysVerified'] ?? null,
	$p['status']['state'] ?? null,
), 'keys test: passes → keys saved, verified, protection ON' );
flxt_ok( ! str_contains( (string) wp_json_encode( $r ), 'SECRET_key-1' ), 'keys test: the secret key is never in a response' );
flxt_same( 'SECRET_key-1', flxrc_secret(), 'keys test: the secret key is stored (sealed) and readable by the server' );
flxt_ok( 0 !== strpos( (string) flxrc_settings( 'secret' ), 'SECRET' ), 'keys test: the stored value is sealed, not plain text' );
flxt_same( true, $p['status']['lastTest']['ok'] ?? null, 'keys test: last test is ok' );

// Update: options, forms, IPs; the secret key is kept; verified stays while keys are the same.
$r = flxrc_t_op( 'FlxRecaptchaSettingsUpdate', array( 'input' => array(
	'threshold' => 0.7, 'domain' => 'RECAPTCHA', 'v2Theme' => 'DARK', 'v2Size' => 'COMPACT', 'hideBadge' => true, 'skipLoggedIn' => false, 'failOpen' => false,
	'allowIps' => array( '203.0.113.7', ' 198.51.100.0/24 ', '' ), 'forms' => array( array( 'key' => 'comments', 'enabled' => false ), array( 'key' => 'flxt_form', 'enabled' => true ) ),
) ), $admin );
$p = $r['data']['flxRecaptchaSettingsUpdate'] ?? array();
$s = $p['settings'] ?? array();
flxt_same( array( array(), 0.7, 'RECAPTCHA', 'DARK', 'COMPACT', true, false, false, array( '203.0.113.7', '198.51.100.0/24' ), true, 'V3' ), array(
	$p['errors'] ?? null, $s['threshold'] ?? null, $s['domain'] ?? null, $s['v2Theme'] ?? null, $s['v2Size'] ?? null, $s['hideBadge'] ?? null, $s['skipLoggedIn'] ?? null, $s['failOpen'] ?? null, $s['allowIps'] ?? null, $s['keysVerified'] ?? null, $s['version'] ?? null,
), 'update: options and IPs saved, keys still verified' );
$forms = array_column( $p['forms'] ?? array(), 'enabled', 'key' );
flxt_same( array( false, true, true ), array( $forms['comments'] ?? null, $forms['flxt_form'] ?? null, $forms['login'] ?? null ), 'update: only the listed forms change' );
flxt_same( 'SECRET_key-1', flxrc_secret(), 'update: the secret key is kept when none is sent' );
flxt_same( array( 'recaptcha', 0.7 ), array( flxrc_settings( 'domain' ), flxrc_settings( 'threshold' ) ), 'update: written to the classic options' );
flxt_same( true, flxrc_context_on( 'login' ) && ! flxrc_context_on( 'comments' ), 'update: the classic helpers see the switches' );

// Invalid input: nothing is written.
foreach ( array(
	array( array( 'threshold' => 2 ), 'threshold' ),
	array( array( 'threshold' => 0.05 ), 'threshold' ),
	array( array( 'siteKey' => 'bad key!' ), 'siteKey' ),
	array( array( 'secret' => 'bad<key>' ), 'secret' ),
	array( array( 'allowIps' => array( '1.1.1.1', 'not an ip' ) ), 'allowIps.1' ),
	array( array( 'forms' => array( array( 'key' => 'nope', 'enabled' => true ) ) ), 'forms.0.key' ),
) as $case ) {
	$r = flxrc_t_op( 'FlxRecaptchaSettingsUpdate', array( 'input' => $case[0] + array( 'failOpen' => true ) ), $admin );
	flxt_same( array( array( 'INVALID_INPUT', $case[1] ) ), flxrc_t_errs( $r, 'flxRecaptchaSettingsUpdate' ), 'update: ' . wp_json_encode( $case[0] ) . ' → INVALID_INPUT ' . $case[1] );
}
flxt_same( array( 0.7, false, array( '203.0.113.7', '198.51.100.0/24' ) ), array( flxrc_settings( 'threshold' ), (bool) flxrc_settings( 'fail_open' ), array_values( array_filter( explode( "\n", (string) flxrc_settings( 'allow_ips' ) ) ) ) ), 'update: nothing is written when a part is invalid' );

// A changed key drops the approval; an empty secret keeps the key.
$r = flxrc_t_op( 'FlxRecaptchaSettingsUpdate', array( 'input' => array( 'siteKey' => 'OTHER_site', 'secret' => '' ) ), $admin );
$p = $r['data']['flxRecaptchaSettingsUpdate'] ?? array();
flxt_same( array( false, 'AWAITING_TEST', 'OTHER_site', 'SECRET_key-1' ), array( $p['settings']['keysVerified'] ?? null, $p['status']['state'] ?? null, $p['settings']['siteKey'] ?? null, flxrc_secret() ), 'update: a changed site key turns protection off until tested' );
$GLOBALS['flxt_google'] = array( 'success' => true, 'score' => 0.8, 'hostname' => 'example.com' );
$r = flxrc_t_op( 'FlxRecaptchaKeysTest', array( 'input' => array( 'version' => 'V3', 'siteKey' => 'OTHER_site', 'secret' => '', 'token' => 'tok3' ) ), $admin );
flxt_same( array( true, 'ON' ), array( $r['data']['flxRecaptchaKeysTest']['result']['ok'] ?? null, $r['data']['flxRecaptchaKeysTest']['status']['state'] ?? null ), 'keys test: the saved secret key is used when none is typed' );
flxt_same( 'SECRET_key-1', $GLOBALS['flxt_google_seen']['secret'] ?? null, 'keys test: the saved secret key went to Google' );

// Public configuration.
$r   = flxt_gql( $GLOBALS['flxrc_ops']['FlxCaptcha'], array( 'context' => 'login' ), 0 );
$c   = $r['data']['flxCaptcha'] ?? array();
flxt_same( array( true, 'V3', 'OTHER_site', 'login', 'https://www.recaptcha.net/recaptcha/api.js?render=OTHER_site', 'DARK', 'COMPACT', true, 'flx-captcha-token', 'X-Flx-Captcha' ), array( $c['enabled'] ?? null, $c['version'] ?? null, $c['siteKey'] ?? null, $c['action'] ?? null, $c['scriptUrl'] ?? null, $c['theme'] ?? null, $c['size'] ?? null, $c['hideBadge'] ?? null, $c['tokenField'] ?? null, $c['tokenHeader'] ?? null ), 'flxCaptcha: a visitor gets what a client needs' );
$c = flxt_gql( $GLOBALS['flxrc_ops']['FlxCaptcha'], array( 'context' => 'comments' ), 0 )['data']['flxCaptcha'] ?? array();
flxt_same( array( false, null, null, null ), array( $c['enabled'] ?? null, array_key_exists( 'siteKey', $c ) ? $c['siteKey'] : 'x', array_key_exists( 'action', $c ) ? $c['action'] : 'x', array_key_exists( 'scriptUrl', $c ) ? $c['scriptUrl'] : 'x' ), 'flxCaptcha: a form switched off has no key, action or script' );
$r = flxt_gql( $GLOBALS['flxrc_ops']['FlxCaptcha'], array( 'context' => 'nope' ), 0 );
flxt_ok( array_key_exists( 'flxCaptcha', $r['data'] ?? array() ) && null === $r['data']['flxCaptcha'] && empty( $r['errors'] ), 'flxCaptcha: unknown context → null' );
$c = flxt_gql( $GLOBALS['flxrc_ops']['FlxCaptcha'], array( 'context' => 'flxt_form' ), $sub )['data']['flxCaptcha'] ?? array();
flxt_same( true, $c['enabled'] ?? null, 'flxCaptcha: does not depend on who is signed in (cacheable)' );
flxrc_t_op( 'FlxRecaptchaSettingsUpdate', array( 'input' => array( 'v2Theme' => 'AUTO', 'allowIps' => array() ) ), $admin );

// Verification for other packages (REST/forms: FLXRC_Captcha_Service::verify; GraphQL: the action).
$GLOBALS['flxt_google'] = array( 'success' => true, 'score' => 0.9, 'hostname' => 'example.com', 'action' => 'flxt_form' );
wp_set_current_user( 0 );
flxt_same( true, FLXRC_Captcha_Service::verify( 'flxt_form', 'good-1' ), 'verify: a good token passes' );
$GLOBALS['flxt_google'] = array( 'success' => true, 'score' => 0.1, 'hostname' => 'example.com', 'action' => 'flxt_form' );
$e = FLXRC_Captcha_Service::verify( 'flxt_form', 'low-score' );
flxt_same( array( true, 'flxrc_score' ), array( $e instanceof WP_Error, $e instanceof WP_Error ? $e->get_error_code() : null ), 'verify: a low score is refused' );
$GLOBALS['flxt_google'] = array( 'success' => true, 'score' => 0.9, 'hostname' => 'example.com', 'action' => 'other' );
flxt_same( 'flxrc_action', FLXRC_Captcha_Service::verify( 'flxt_form', 'bad-action' )->get_error_code(), 'verify: a token made for another action is refused' );
flxt_same( 'flxrc_empty', FLXRC_Captcha_Service::verify( 'flxt_form', '' )->get_error_code(), 'verify: no token is refused' );
flxt_same( true, FLXRC_Captcha_Service::verify( 'comments', 'whatever' ), 'verify: a form that is switched off passes' );
flxt_same( true, FLXRC_Captcha_Service::verify( 'unknown_form', '' ), 'verify: an unknown form passes' );
$GLOBALS['flxt_google'] = array( 'success' => true, 'score' => 0.9, 'hostname' => 'example.com', 'action' => 'flxt_form' );
flxt_same( true, apply_filters( 'flx_captcha_verify', true, 'flxt_form', 'filter-token' ), 'verify: the public filter is the same service' );
$GLOBALS['flxt_google'] = array( 'success' => true, 'score' => 0.1, 'hostname' => 'example.com', 'action' => 'flxt_form' );
flxt_same( 'flxrc_score', apply_filters( 'flx_captcha_verify', true, 'flxt_form', 'filter-low' )->get_error_code(), 'verify: the public filter returns the WP_Error' );

// A public GraphQL mutation behind the captcha.
$GLOBALS['flxt_google'] = array( 'success' => true, 'score' => 0.9, 'hostname' => 'example.com', 'action' => 'flxt_form' );
$r = flxt_gql( $gate, array( 't' => 'gate-ok' ), 0 );
flxt_same( array( true, array() ), array( $r['data']['flxRecaptchaTestGate']['done'] ?? null, $r['data']['flxRecaptchaTestGate']['errors'] ?? null ), 'gate: a good token runs the mutation' );
$r = flxt_gql( $gate, array(), 0 );
flxt_same( array( null, 'INVALID_INPUT', 'captchaToken' ), array( $r['data']['flxRecaptchaTestGate']['done'] ?? null, $r['data']['flxRecaptchaTestGate']['errors'][0]['code'] ?? null, $r['data']['flxRecaptchaTestGate']['errors'][0]['field'] ?? null ), 'gate: no token → INVALID_INPUT captchaToken (payload errors)' );
$GLOBALS['flxt_google'] = array( 'success' => false, 'error-codes' => array( 'timeout-or-duplicate' ) );
$r = flxt_gql( $gate, array( 't' => 'gate-bad' ), 0 );
flxt_same( array( 'INVALID_INPUT', 'captchaToken' ), array( $r['data']['flxRecaptchaTestGate']['errors'][0]['code'] ?? null, $r['data']['flxRecaptchaTestGate']['errors'][0]['field'] ?? null ), 'gate: Google refuses the token → INVALID_INPUT' );
$_SERVER['HTTP_X_FLX_CAPTCHA'] = 'from-header';
$GLOBALS['flxt_google']        = array( 'success' => true, 'score' => 0.9, 'hostname' => 'example.com', 'action' => 'flxt_form' );
$r = flxt_gql( $gate, array(), 0 );
flxt_same( true, $r['data']['flxRecaptchaTestGate']['done'] ?? null, 'gate: the token may come in the X-Flx-Captcha header' );
unset( $_SERVER['HTTP_X_FLX_CAPTCHA'] );
$r = flxt_gql( $gate, array(), $sub );
flxt_same( 'captchaToken', $r['data']['flxRecaptchaTestGate']['errors'][0]['field'] ?? null, 'gate: signed-in users are checked when "skip signed-in" is off' );
flxrc_t_op( 'FlxRecaptchaSettingsUpdate', array( 'input' => array( 'skipLoggedIn' => true ) ), $admin );
$r = flxt_gql( $gate, array(), $sub );
flxt_same( true, $r['data']['flxRecaptchaTestGate']['done'] ?? null, 'gate: signed-in users are skipped when "skip signed-in" is on' );

// Stats (the classic page numbers) and Google outage.
$totals = flxrc_stats_totals( 30 );
flxt_ok( $totals['flxt_form'][0] >= 1 && $totals['flxt_form'][1] >= 1, 'stats: passed and blocked are counted per form' );
$st = flxrc_t_op( 'FlxRecaptchaScreen', array(), $admin )['data']['flxRecaptchaStatus'] ?? array();
flxt_same( array( $totals['_all'][0], $totals['_all'][1] ), array( $st['passed'] ?? null, $st['blocked'] ?? null ), 'screen: status numbers equal the classic page numbers' );
flxrc_t_op( 'FlxRecaptchaSettingsUpdate', array( 'input' => array( 'failOpen' => true ) ), $admin );
wp_set_current_user( 0 );
$GLOBALS['flxt_google'] = false;
flxt_same( true, FLXRC_Captcha_Service::verify( 'flxt_form', 'outage-1' ), 'outage: Google unreachable + fail open → passes' );
$st = flxrc_t_op( 'FlxRecaptchaScreen', array(), $admin )['data']['flxRecaptchaStatus'] ?? array();
flxt_same( array( 'NETWORK', true ), array( $st['lastError']['kind'] ?? null, $st['lastError']['letPass'] ?? null ), 'outage: shown as the last Google error' );
flxrc_t_op( 'FlxRecaptchaSettingsUpdate', array( 'input' => array( 'failOpen' => false ) ), $admin );
wp_set_current_user( 0 );
flxt_same( 'flxrc_unreachable', FLXRC_Captcha_Service::verify( 'flxt_form', 'outage-2' )->get_error_code(), 'outage: fail open off → blocked' );

// Allowed IP and the recovery constant paths.
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
flxrc_t_op( 'FlxRecaptchaSettingsUpdate', array( 'input' => array( 'allowIps' => array( '203.0.113.0/24' ) ) ), $admin );
wp_set_current_user( 0 );
flxt_same( true, FLXRC_Captcha_Service::verify( 'flxt_form', 'ip-1' ), 'verify: an allowed IP range is skipped' );
$s = flxrc_t_op( 'FlxRecaptchaScreen', array(), $admin )['data']['flxRecaptchaSettings'] ?? array();
flxt_same( '203.0.113.7', $s['currentIp'] ?? null, 'screen: the caller\'s IP' );

// Persisted: the screen's documents are allowed in strict mode.
wp_set_current_user( $admin );
$r = graphql( array( 'queryId' => hash( 'sha256', $GLOBALS['flxrc_ops']['FlxRecaptchaScreen'] ) ) );
flxt_ok( ! empty( $r['data']['flxRecaptchaSettings'] ), 'persisted queryId runs (strict mode)' );

// Clean up.
unset( $_SERVER['REMOTE_ADDR'] );
delete_option( 'flxrc_settings' );
delete_option( 'flxrc_stats' );
delete_option( 'flxrc_status' );
wp_delete_user( $sub );
flxt_done();
