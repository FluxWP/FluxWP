<?php
/**
 * Standalone checks for flx_phone_normalize, flx_phone_digits and flx_phone_equal.
 * Country is forced to tr unless a case says otherwise. No WordPress.
 * php tools/brand/tests/phone.php
 */

define( 'ABSPATH', __DIR__ );

function add_action() {}
function add_filter() {}
function add_shortcode() {}
function apply_filters( $tag, $value ) {
	return $value;
}

require dirname( __DIR__ ) . '/flx-brand/core.php';

$failed = 0;

function phone_assert( $label, $expected, $got ) {
	global $failed;
	if ( $expected === $got ) {
		echo "ok  $label\n";
		return;
	}
	++$failed;
	fwrite( STDERR, "FAIL $label\n expected: " . var_export( $expected, true ) . "\n got:      " . var_export( $got, true ) . "\n" );
}

$expect = array(
	'0532 123 45 67'              => '+905321234567',
	'532 123 45 67'               => '+905321234567',
	'5321234567'                  => '+905321234567',
	'05321234567'                 => '+905321234567',
	'(0532) 123-45-67'            => '+905321234567',
	'0212 123 45 67'              => '+902121234567',
	'2121234567'                  => '+902121234567',
	'+90 532 123 45 67'           => '+905321234567',
	'90 532 123 45 67'            => '+905321234567',
	'905321234567'                => '+905321234567',
	'0090 532 123 45 67'          => '+905321234567',
	'00905321234567'              => '+905321234567',
	'+90 0532 123 45 67'          => '+905321234567',
	'+49 151 23456789'            => '+4915123456789',
	'0049 151 23456789'           => '+4915123456789',
	'4915123456789'               => '+4915123456789',
	'+1 415 555 2671'             => '+14155552671',
	'14155552671'                 => '+14155552671',
	'+994 50 123 45 67'           => '+994501234567',
	'905321234567@s.whatsapp.net' => '+905321234567',
	'532-123-4567 ext 5'          => '+53212345675',
	'90532123456'                 => '',
	'9053212345678'               => '',
	'+90 632 123 45 67'           => '',
	'111111'                      => '',
	'12345'                       => '',
	'0'                           => '',
	''                            => '',
	'abc'                         => '',
	'+'                           => '',
);

foreach ( $expect as $raw => $e164 ) {
	$label = '' === $raw ? '(empty)' : $raw;
	phone_assert( 'normalize ' . $label, $e164, flx_phone_normalize( $raw, 'tr' ) );
	phone_assert( 'digits ' . $label, '' === $e164 ? '' : substr( $e164, 1 ), flx_phone_digits( $raw, 'tr' ) );
}

phone_assert( 'de local', '+4915123456789', flx_phone_normalize( '0151 23456789', 'de' ) );
phone_assert( 'us local', '+14155552671', flx_phone_normalize( '4155552671', 'us' ) );

phone_assert( 'local equals +90', true, flx_phone_equal( '0532 123 45 67', '+90 532 123 45 67', 'tr' ) );
phone_assert( 'de does not equal tr', false, flx_phone_equal( '+49 151 23456789', '+90 151 23456789', 'tr' ) );
phone_assert( 'empty is not equal', false, flx_phone_equal( '', '', 'tr' ) );
phone_assert( 'invalid is not equal', false, flx_phone_equal( 'abc', '+', 'tr' ) );
phone_assert( 'same invalid is not equal', false, flx_phone_equal( '12345', '12345', 'tr' ) );

if ( $failed ) {
	fwrite( STDERR, "$failed failed\n" );
	exit( 1 );
}
echo "phone.php ok\n";
