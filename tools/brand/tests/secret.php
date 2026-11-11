<?php
/**
 * Standalone checks for flx_secret_seal and flx_secret_open.
 * No WordPress. php tools/brand/tests/secret.php
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

require dirname( __DIR__ ) . '/flx-brand/core.php';

$failed = 0;

function secret_assert( $label, $expected, $got ) {
	global $failed;
	if ( $expected === $got ) {
		echo "ok  $label\n";
		return;
	}
	++$failed;
	fwrite( STDERR, "FAIL $label\n" );
}

$plain = 'made-up-secret';
$a     = flx_secret_seal( $plain, 'flx-ai' );
$b     = flx_secret_seal( $plain, 'flx-ai' );

secret_assert( 'round trip', $plain, flx_secret_open( $a, 'flx-ai' ) );
secret_assert( 'two seals differ', true, $a !== $b && '' !== $a );
secret_assert( 'prefix', true, 0 === strpos( $a, 'flx1:' ) );
secret_assert( 'wrong context', '', flx_secret_open( $a, 'flx-mail' ) );
secret_assert( 'empty seal', '', flx_secret_seal( '', 'flx-ai' ) );
secret_assert( 'empty open', '', flx_secret_open( '', 'flx-ai' ) );
secret_assert( 'plain unchanged', 'not-sealed', flx_secret_open( 'not-sealed', 'flx-ai' ) );

$tampered = $a;
$tampered[ strlen( $tampered ) - 2 ] = 'A' === $tampered[ strlen( $tampered ) - 2 ] ? 'B' : 'A';
secret_assert( 'tampered', '', flx_secret_open( $tampered, 'flx-ai' ) );

$material = 'stub-salt-auth' . 'flxai';
$nonce    = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
$key      = sodium_crypto_generichash( $material, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
$legacy   = 'enc1:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $key ) );
secret_assert( 'legacy opens', $plain, flx_secret_open( $legacy, 'flx-ai', array( 'enc1:' => $material ) ) );
secret_assert( 'legacy wrong material', '', flx_secret_open( $legacy, 'flx-ai', array( 'enc1:' => 'other-material' ) ) );
secret_assert( 'legacy ignored without map', $legacy, flx_secret_open( $legacy, 'flx-ai' ) );

if ( $failed ) {
	fwrite( STDERR, "$failed failed\n" );
	exit( 1 );
}
echo "secret.php ok\n";
