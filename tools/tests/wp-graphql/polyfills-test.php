<?php
/**
 * Polyfill behaviour against the native functions (run on PHP 8.5 for comparison, on 8.1 for coverage).
 *   php tools/tests/wp-graphql/polyfills-test.php
 * Loads the polyfills with a "flxp_" prefix so the native function (when present) can be compared.
 */

define( 'ABSPATH', __DIR__ );
$src = file_get_contents( __DIR__ . '/../../../plugins/flx-graphql/includes/kit/polyfills.php' );
$names = array( 'json_validate', 'mb_str_pad', 'str_increment', 'str_decrement', 'array_find_key', 'array_find', 'array_any', 'array_all', 'mb_trim', 'mb_ltrim', 'mb_rtrim', 'mb_ucfirst', 'mb_lcfirst', 'array_first', 'array_last', 'flx_gql_mb_trim' );
foreach ( $names as $n ) {
	$src = preg_replace( '/\b' . $n . '\b/', 'flxp_' . $n, $src );
}
eval( '?>' . $src ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test only.

$fail = 0;
$pass = 0;
function check( string $name, $want, $got ): void {
	global $fail, $pass;
	if ( $want === $got ) {
		$pass++;
		return;
	}
	$fail++;
	echo "FAIL $name: want " . var_export( $want, true ) . ' got ' . var_export( $got, true ) . "\n";
}
function both( string $fn, array $args, $want = null ): void {
	$got = call_user_func_array( 'flxp_' . $fn, $args );
	if ( function_exists( $fn ) ) {
		check( "$fn(" . json_encode( $args ) . ') vs native', call_user_func_array( $fn, $args ), $got );
	}
	if ( func_num_args() > 2 ) {
		check( "$fn(" . json_encode( $args ) . ')', $want, $got );
	}
}

both( 'json_validate', array( '{"a":1}' ), true );
both( 'json_validate', array( '{a:1}' ), false );
both( 'json_validate', array( '' ), false );
both( 'json_validate', array( '[[1]]', 1 ), false );
both( 'mb_str_pad', array( 'ğü', 5, '*' ), 'ğü***' );
both( 'mb_str_pad', array( 'ğü', 5, 'ab', STR_PAD_LEFT ), 'abağü' );
both( 'mb_str_pad', array( 'ğ', 4, 'x', STR_PAD_BOTH ), 'xğxx' );
both( 'mb_str_pad', array( 'abcdef', 3 ), 'abcdef' );
foreach ( array( 'a' => 'b', 'z' => 'aa', 'Az' => 'Ba', 'zz' => 'aaa', 'a9' => 'b0', '9' => '10', 'Zz' => 'AAa', 'b1' => 'b2' ) as $in => $out ) {
	both( 'str_increment', array( $in ), $out );
}
foreach ( array( 'b' => 'a', 'aa' => 'z', 'Ba' => 'Az', 'aaa' => 'zz', '10' => '9', '100' => '99', 'b0' => 'a9', 'ab' => 'aa' ) as $in => $out ) {
	both( 'str_decrement', array( $in ), $out );
}
$even = static fn( $v ) => 0 === $v % 2;
both( 'array_find', array( array( 1, 3, 4, 6 ), $even ), 4 );
both( 'array_find', array( array( 1, 3 ), $even ), null );
both( 'array_find_key', array( array( 'a' => 1, 'b' => 2 ), $even ), 'b' );
both( 'array_any', array( array( 1, 2 ), $even ), true );
both( 'array_any', array( array(), $even ), false );
both( 'array_all', array( array( 2, 4 ), $even ), true );
both( 'array_all', array( array(), $even ), true );
both( 'array_all', array( array( 2, 3 ), $even ), false );
both( 'mb_trim', array( "\u{00A0} şu \u{3000}" ), 'şu' );
both( 'mb_trim', array( '--ğ--', '-' ), 'ğ' );
both( 'mb_ltrim', array( '  ğ ' ), 'ğ ' );
both( 'mb_rtrim', array( '  ğ ' ), '  ğ' );
both( 'mb_ucfirst', array( 'ğül' ), 'Ğül' );
both( 'mb_lcfirst', array( 'ÇAY' ), 'çAY' );
both( 'array_first', array( array( 'x' => 1, 'y' => 2 ) ), 1 );
both( 'array_first', array( array() ), null );
both( 'array_last', array( array( 'x' => 1, 'y' => 2 ) ), 2 );
both( 'array_last', array( array() ), null );

printf( "polyfills (PHP %s): %d passed, %d failed\n", PHP_VERSION, $pass, $fail );
exit( $fail ? 1 : 0 );
