<?php
/**
 * Paket imzası (Ed25519). Release workflow'u her <slug>.zip için <slug>.zip.sig üretir.
 *
 *   FLX_RELEASE_SIGNING_KEY=<anahtar> php bin/sign-package.php dist/flx-ai.zip flx-ai 0.33.4
 *
 * Anahtar: base64 ya da base64url; 32 bayt tohum (seed) veya 64 bayt sodium gizli anahtarı.
 * İmzalanan ileti:  "flx-sig/1\n<slug>\n<sürüm>\n<zip'in sha256'sı (hex)>"
 * Böylece imza yalnız o paketin o sürümüne geçer: başka paketin ya da eski sürümün imzası taşınamaz.
 * .sig dosyası JSON: {"v":1,"kid":"<public key sha256'sının ilk 16 hex>","sig":"<base64>"}.
 * FluxWP Panel (flx-setup) gömülü public key ile doğrular.
 *
 * Anahtar yoksa (secret tanımlı değil) çıkış kodu 3: workflow uyarı verip imzasız yayınlar.
 */

if ( $argc < 4 ) {
	fwrite( STDERR, "usage: sign-package.php <zip> <slug> <version>\n" );
	exit( 2 );
}
list( , $zip, $slug, $version ) = $argv;
if ( ! function_exists( 'sodium_crypto_sign_detached' ) ) {
	fwrite( STDERR, "PHP sodium extension missing\n" );
	exit( 1 );
}
if ( ! preg_match( '/^[a-z0-9][a-z0-9-]*$/', $slug ) || ! preg_match( '/^[0-9][0-9A-Za-z.+-]*$/', $version ) ) {
	fwrite( STDERR, "bad slug or version\n" );
	exit( 2 );
}
if ( ! is_file( $zip ) ) {
	fwrite( STDERR, "no such file: $zip\n" );
	exit( 2 );
}
$raw = trim( (string) getenv( 'FLX_RELEASE_SIGNING_KEY' ) );
if ( '' === $raw ) {
	fwrite( STDERR, "FLX_RELEASE_SIGNING_KEY is not set\n" );
	exit( 3 );
}
$key = base64_decode( strtr( $raw, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $raw ) % 4 ) % 4 ), true );
if ( false !== $key && SODIUM_CRYPTO_SIGN_SEEDBYTES === strlen( $key ) ) {
	$key = sodium_crypto_sign_secretkey( sodium_crypto_sign_seed_keypair( $key ) );
}
if ( false === $key || SODIUM_CRYPTO_SIGN_SECRETKEYBYTES !== strlen( $key ) ) {
	fwrite( STDERR, "FLX_RELEASE_SIGNING_KEY is not a 32-byte seed or 64-byte secret key\n" );
	exit( 1 );
}
$pk  = sodium_crypto_sign_publickey_from_secretkey( $key );
$msg = "flx-sig/1\n" . $slug . "\n" . $version . "\n" . hash_file( 'sha256', $zip );
$sig = sodium_crypto_sign_detached( $msg, $key );
if ( ! sodium_crypto_sign_verify_detached( $sig, $msg, $pk ) ) {
	fwrite( STDERR, "self-check failed\n" );
	exit( 1 );
}
$out = json_encode(
	array(
		'v'   => 1,
		'kid' => substr( hash( 'sha256', $pk ), 0, 16 ),
		'sig' => base64_encode( $sig ),
	)
);
file_put_contents( $zip . '.sig', $out . "\n" );
sodium_memzero( $key );
echo $zip . ".sig (kid " . substr( hash( 'sha256', $pk ), 0, 16 ) . ")\n";
