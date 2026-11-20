<?php
/**
 * Paket imzası (FLXU_Signature) birim testi, WordPress gerekmez: php plugins/flx-setup/tests/package-signature.php
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'FLXU_DEFAULT_SERVER', 'https://updater.fluxwp.com' );
class WP_Error { public $code; public function __construct( $code, $msg = '' ) { $this->code = $code; } }
function __( $s ) { return $s; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
// KEYS boş gelir; target()'ı sınamak için anahtar listesi dolu bir kopya yüklenir.
$src = file_get_contents( dirname( __DIR__ ) . '/includes/updater/class-flxu-signature.php' );
$src = str_replace( 'const KEYS = array();', "const KEYS = array( 'test' => 'test' );", $src );
eval( '?>' . $src );
$fail = 0;
function sig_check( $ok, $name ) { global $fail; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $name . "\n"; $fail += $ok ? 0 : 1; }

$kp   = sodium_crypto_sign_keypair();
$pk   = base64_encode( sodium_crypto_sign_publickey( $kp ) );
$kid  = substr( hash( 'sha256', sodium_crypto_sign_publickey( $kp ) ), 0, 16 );
$keys = array( $kid => $pk );
$zip  = tempnam( sys_get_temp_dir(), 'flxsig' );
file_put_contents( $zip, 'PK zip body' );
$msg  = "flx-sig/1\nflx-ai\n0.33.4\n" . hash_file( 'sha256', $zip );
$json = json_encode( array( 'v' => 1, 'kid' => $kid, 'sig' => base64_encode( sodium_crypto_sign_detached( $msg, sodium_crypto_sign_secretkey( $kp ) ) ) ) );

sig_check( true === FLXU_Signature::verify( $json, 'flx-ai', '0.33.4', $zip, $keys ), 'Valid signature passes' );
sig_check( true !== FLXU_Signature::verify( $json, 'flx-ai', '0.33.3', $zip, $keys ), 'Signature does not move to another version' );
sig_check( true !== FLXU_Signature::verify( $json, 'flx-x', '0.33.4', $zip, $keys ), 'Signature does not move to another package' );
sig_check( true !== FLXU_Signature::verify( $json, 'flx-ai', '0.33.4', $zip, array( '0000000000000000' => $pk ) ), 'Unknown key id fails' );
sig_check( true !== FLXU_Signature::verify( '<html>', 'flx-ai', '0.33.4', $zip, $keys ), 'Garbage signature file fails' );
file_put_contents( $zip, 'PK zip body!' );
sig_check( true !== FLXU_Signature::verify( $json, 'flx-ai', '0.33.4', $zip, $keys ), 'Changed zip fails' );
unlink( $zip );

$t = FLXU_Signature::target( 'https://updater.fluxwp.com/v1/download/flx-ai/0.33.4.zip?key=k' );
sig_check( 'flx-ai' === ( $t['slug'] ?? '' ) && '0.33.4' === ( $t['version'] ?? '' ) && 'https://updater.fluxwp.com/v1/download/flx-ai/0.33.4.zip.sig?key=k' === ( $t['sig'] ?? '' ), 'Official server package needs a signature next to the zip' );
$t = FLXU_Signature::target( 'https://github.com/FluxWP/fluxwp/releases/download/flx-ai-v0.33.4/flx-ai.zip' );
sig_check( 'flx-ai' === ( $t['slug'] ?? '' ) && '0.33.4' === ( $t['version'] ?? '' ), 'FluxWP GitHub release needs a signature' );
foreach ( array(
	'http://updater.fluxwp.com/v1/download/flx-ai/0.33.4.zip',
	'https://updater.fluxwp.com/other/flx-ai.zip',
	'https://updater.fluxwp.com:8443/v1/download/flx-ai/0.33.4.zip',
	'http://github.com/fluxwp/fluxwp/releases/download/flx-ai-v0.33.4/flx-ai.zip',
	'https://github.com/fluxwp/fluxwp/releases/download/flx-ai-v0.33.4/flx-x.zip',
	'https://github.com/fluxwp/fluxwp/archive/main.zip',
) as $u ) {
	sig_check( ! empty( FLXU_Signature::target( $u )['reject'] ), 'Non-canonical official address is rejected: ' . $u );
}
sig_check( null === FLXU_Signature::target( 'https://github.com/someone/pkg/releases/download/x-v1/x.zip' ), 'Other GitHub owners are unchanged' );
sig_check( null === FLXU_Signature::target( 'https://updates.example.com/v1/download/x/1.zip' ), 'Own update servers are unchanged' );
sig_check( FLXU_Signature::matches( 'https://updater.fluxwp.com/v1/download/flx-ai/0.33.4.zip', 'flx-ai', '0.33.4' ), 'Advertised version matches the signed address' );
sig_check( ! FLXU_Signature::matches( 'https://updater.fluxwp.com/v1/download/flx-ai/0.30.0.zip', 'flx-ai', '0.33.4' ), 'Old signed package cannot be offered as a newer version' );
sig_check( ! FLXU_Signature::matches( 'https://updater.fluxwp.com/v1/download/flx-ai/0.33.4.zip', 'flx-seo', '0.33.4' ), 'Package cannot be offered under another slug' );
sig_check( FLXU_Signature::matches( 'https://updates.example.com/pkg.zip', 'x', '1' ), 'Own server addresses keep working' );
exit( $fail ? 1 : 0 );
