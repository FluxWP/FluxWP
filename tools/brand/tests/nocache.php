<?php
/**
 * Standalone checks for flx_rest_nocache. No WordPress.
 * php tools/brand/tests/nocache.php
 */

define( 'ABSPATH', __DIR__ );

function add_action() {}
function add_filter() {}
function add_shortcode() {}

class WP_HTTP_Response {
	public $headers = array();

	public function header( $name, $value ) {
		$this->headers[ $name ] = $value;
	}
}

require dirname( __DIR__ ) . '/flx-brand/core.php';

$failed = 0;

function nocache_assert( $label, $expected, $got ) {
	global $failed;
	if ( $expected === $got ) {
		echo "ok  $label\n";
		return;
	}
	++$failed;
	fwrite( STDERR, "FAIL $label\n expected: " . var_export( $expected, true ) . "\n got:      " . var_export( $got, true ) . "\n" );
}

$res = new WP_HTTP_Response();
$out = flx_rest_nocache( $res );
nocache_assert( 'returns the object', true, $out === $res );
nocache_assert( 'cache-control', 'no-store, no-cache, must-revalidate, max-age=0, private', $res->headers['Cache-Control'] ?? null );
nocache_assert( 'pragma', 'no-cache', $res->headers['Pragma'] ?? null );
nocache_assert( 'litespeed', 'no-cache', $res->headers['X-LiteSpeed-Cache-Control'] ?? null );
nocache_assert( 'plain value', 'leave', flx_rest_nocache( 'leave' ) );

if ( $failed ) {
	fwrite( STDERR, "$failed failed\n" );
	exit( 1 );
}
echo "nocache.php ok\n";
