<?php
/**
 * Standalone checks for flx_private_dir and flx_send_file. No WordPress.
 * php tools/brand/tests/files.php
 */

define( 'ABSPATH', __DIR__ );

function add_action() {}
function add_filter() {}
function add_shortcode() {}
function wp_mkdir_p( $dir ) {
	return is_dir( $dir ) || mkdir( $dir, 0777, true );
}
function wp_is_writable( $dir ) {
	return is_writable( $dir );
}
function sanitize_file_name( $name ) {
	return (string) $name;
}

require dirname( __DIR__ ) . '/flx-brand/core.php';

$failed = 0;

function files_assert( $label, $expected, $got ) {
	global $failed;
	if ( $expected === $got ) {
		echo "ok  $label\n";
		return;
	}
	++$failed;
	fwrite( STDERR, "FAIL $label\n expected: " . var_export( $expected, true ) . "\n got:      " . var_export( $got, true ) . "\n" );
}

$root = sys_get_temp_dir() . '/flx-private-' . bin2hex( random_bytes( 4 ) );
files_assert( 'creates dir', true, flx_private_dir( $root ) );
files_assert( 'dir exists', true, is_dir( $root ) );
$ht = file_get_contents( $root . '/.htaccess' );
files_assert( 'apache 2.4', true, is_string( $ht ) && false !== strpos( $ht, 'Require all denied' ) );
files_assert( 'apache 2.2', true, is_string( $ht ) && false !== strpos( $ht, 'Deny from all' ) );
files_assert( 'index html', '', file_get_contents( $root . '/index.html' ) );

$custom = "# keep\nAllow from none\n";
file_put_contents( $root . '/.htaccess', $custom );
files_assert( 'second call', true, flx_private_dir( $root ) );
files_assert( 'htaccess kept', $custom, file_get_contents( $root . '/.htaccess' ) );

ob_start();
$missing = flx_send_file( $root . '/no-such-file' );
$dir_no  = flx_send_file( $root );
$silent  = ob_get_clean();
files_assert( 'missing file', false, $missing );
files_assert( 'directory', false, $dir_no );
files_assert( 'no output', '', $silent );

if ( $failed ) {
	fwrite( STDERR, "$failed failed\n" );
	exit( 1 );
}
echo "files.php ok\n";
