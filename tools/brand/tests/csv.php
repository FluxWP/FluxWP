<?php
/**
 * Standalone checks for flx_csv_cell and flx_csv_stream. No WordPress.
 * php tools/brand/tests/csv.php
 */

define( 'ABSPATH', __DIR__ );

function add_action() {}
function add_filter() {}
function add_shortcode() {}
function nocache_headers() {}
function sanitize_file_name( $name ) {
	return (string) $name;
}

require dirname( __DIR__ ) . '/flx-brand/core.php';

$failed = 0;

function csv_assert( $label, $expected, $got ) {
	global $failed;
	if ( $expected === $got ) {
		echo "ok  $label\n";
		return;
	}
	++$failed;
	fwrite( STDERR, "FAIL $label\n expected: " . var_export( $expected, true ) . "\n got:      " . var_export( $got, true ) . "\n" );
}

$with_bom = csv_capture( true, ',' );
$semi     = csv_capture( false, ';' );

csv_assert( 'int 1', 1, flx_csv_cell( 1 ) );
csv_assert( 'int -5', -5, flx_csv_cell( -5 ) );
csv_assert( 'numeric string -5.20', '-5.20', flx_csv_cell( '-5.20' ) );
csv_assert( 'formula', "'=SUM(A1)", flx_csv_cell( '=SUM(A1)' ) );
csv_assert( 'plus space', "'+90 555", flx_csv_cell( '+90 555' ) );
csv_assert( 'minus word', "'-cmd", flx_csv_cell( '-cmd' ) );
csv_assert( 'at', "'@x", flx_csv_cell( '@x' ) );
csv_assert( 'tab formula', "'\t=1", flx_csv_cell( "\t=1" ) );
csv_assert( 'space formula', "' =1", flx_csv_cell( ' =1' ) );
csv_assert( 'empty', '', flx_csv_cell( '' ) );
csv_assert( 'plain', 'abc', flx_csv_cell( 'abc' ) );

function csv_capture( $bom, $delimiter ) {
	ob_start();
	flx_csv_stream(
		't.csv',
		array( 'n', 's' ),
		( static function () {
			yield array( 1, '=SUM(A1)' );
		} )(),
		array(
			'bom'       => $bom,
			'delimiter' => $delimiter,
		)
	);
	return ob_get_clean();
}

csv_assert( 'stream bom comma', "\xEF\xBB\xBF" . "n,s\n1,'=SUM(A1)\n", $with_bom );
csv_assert( 'stream no bom semicolon', "n;s\n1;'=SUM(A1)\n", $semi );

if ( $failed ) {
	fwrite( STDERR, "$failed failed\n" );
	exit( 1 );
}
echo "csv.php ok\n";
