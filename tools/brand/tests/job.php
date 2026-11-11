<?php
/**
 * Standalone checks for flx_job_sig, flx_job_check and flx_job_kick.
 * No WordPress. php tools/brand/tests/job.php
 */

define( 'ABSPATH', __DIR__ );

function add_action() {}
function add_filter() {}
function add_shortcode() {}

function apply_filters( $tag, $value ) {
	return $value;
}

function wp_salt( $scheme = 'auth' ) {
	return 'stub-salt-' . $scheme;
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . $path;
}

function is_wp_error( $thing ) {
	return false;
}

function wp_remote_post( $url, $args = array() ) {
	$GLOBALS['flx_test_posts'][] = array(
		'url'  => $url,
		'args' => $args,
	);
	return array( 'response' => array( 'code' => 200 ) );
}

require dirname( __DIR__ ) . '/flx-brand/core.php';

$failed = 0;

function job_assert( $label, $expected, $got ) {
	global $failed;
	if ( $expected === $got ) {
		echo "ok  $label\n";
		return;
	}
	++$failed;
	fwrite( STDERR, "FAIL $label\n expected: " . var_export( $expected, true ) . "\n got:      " . var_export( $got, true ) . "\n" );
}

$action = 'flxsf_wa_job';
$id     = 42;
$old    = hash_hmac( 'sha256', $action . '|' . $id, wp_salt( 'auth' ) );
job_assert( 'sig matches inline', $old, flx_job_sig( $action, $id ) );
job_assert( 'qurban formula', hash_hmac( 'sha256', 'flxqb_wa_job|' . (int) $id, wp_salt( 'auth' ) ), flx_job_sig( 'flxqb_wa_job', $id ) );

$_POST = array(
	'id'  => '42',
	'sig' => flx_job_sig( $action, 42 ),
);
job_assert( 'good sig', 42, flx_job_check( $action ) );

$_POST['sig'] = 'not-the-signature';
job_assert( 'wrong sig', 0, flx_job_check( $action ) );

unset( $_POST['sig'] );
job_assert( 'missing sig', 0, flx_job_check( $action ) );

$_POST = array(
	'id'  => '0',
	'sig' => flx_job_sig( $action, 0 ),
);
job_assert( 'id 0', 0, flx_job_check( $action ) );

$GLOBALS['flx_test_posts'] = array();
job_assert( 'kick returns', true, flx_job_kick( 'flxqb_wa_job', 7 ) );
$post = $GLOBALS['flx_test_posts'][0];
job_assert( 'kick url', 'https://example.test/wp-admin/admin-ajax.php', $post['url'] );
job_assert( 'kick blocking', false, $post['args']['blocking'] );
job_assert( 'kick timeout', 0.5, $post['args']['timeout'] );
job_assert( 'kick action', 'flxqb_wa_job', $post['args']['body']['action'] );
job_assert( 'kick id', 7, $post['args']['body']['id'] );
job_assert( 'kick sig', flx_job_sig( 'flxqb_wa_job', 7 ), $post['args']['body']['sig'] );

if ( $failed ) {
	fwrite( STDERR, "$failed failed\n" );
	exit( 1 );
}
echo "job.php ok\n";
