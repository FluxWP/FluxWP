<?php
/**
 * Standalone checks for flx_ip_is_public, flx_url_is_public and flx_safe_remote_request.
 * No WordPress, no network. php tools/brand/tests/http.php
 */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	public $code;
	public function __construct( $code = '', $message = '' ) {
		$this->code = $code;
	}
	public function get_error_code() {
		return $this->code;
	}
}
class WP_Http {
	public static function make_absolute_url( $maybe, $url ) {
		if ( preg_match( '#^https?://#i', $maybe ) ) {
			return $maybe;
		}
		$p = parse_url( $url );
		return $p['scheme'] . '://' . $p['host'] . ( 0 === strpos( $maybe, '/' ) ? '' : '/' ) . $maybe;
	}
}
function is_wp_error( $x ) {
	return $x instanceof WP_Error;
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}
function home_url() {
	return 'https://site.example';
}
function get_locale() {
	return 'en_US';
}
function add_action() {}
function remove_action() {}

$GLOBALS['dns']   = array(
	'good.example'    => array( '93.184.216.34' ),
	'cdn.example'     => array( '93.184.216.35', '2606:2800:220:1:248:1893:25c8:1946' ),
	'meta.example'    => array( '169.254.169.254' ),
	'mixed.example'   => array( '93.184.216.34', '10.0.0.5' ),
	'v6local.example' => array( 'fd00::1' ),
	'nowhere.example' => array(),
);
$GLOBALS['allow'] = null;
function apply_filters( $tag, $value, $arg = null ) {
	if ( 'flx_host_ips' === $tag ) {
		return isset( $GLOBALS['dns'][ $arg ] ) ? $GLOBALS['dns'][ $arg ] : array();
	}
	if ( 'flx_url_is_public' === $tag && null !== $GLOBALS['allow'] ) {
		return $GLOBALS['allow'];
	}
	return $value;
}

$GLOBALS['script'] = array();
$GLOBALS['calls']  = array();
function wp_remote_request( $url, $args ) {
	$GLOBALS['calls'][] = array( $url, $args );
	return isset( $GLOBALS['script'][ $url ] ) ? $GLOBALS['script'][ $url ] : array( 'code' => 200, 'location' => '' );
}
function wp_remote_retrieve_response_code( $r ) {
	return $r['code'];
}
function wp_remote_retrieve_header( $r, $h ) {
	return isset( $r[ $h ] ) ? $r[ $h ] : '';
}

require dirname( __DIR__ ) . '/flx-brand/http.php';

$failed = 0;
function http_assert( $label, $expected, $got ) {
	global $failed;
	if ( $expected === $got ) {
		echo "ok  $label\n";
		return;
	}
	++$failed;
	fwrite( STDERR, "FAIL $label\n" );
}

$ips = array(
	'8.8.8.8'              => true,
	'93.184.216.34'        => true,
	'2606:4700:4700::1111' => true,
	'127.0.0.1'            => false,
	'10.1.2.3'             => false,
	'172.16.0.1'           => false,
	'192.168.1.1'          => false,
	'169.254.169.254'      => false,
	'100.64.0.1'           => false,
	'0.0.0.0'              => false,
	'224.0.0.1'            => false,
	'255.255.255.255'      => false,
	'198.18.0.1'           => false,
	'::1'                  => false,
	'::'                   => false,
	'fe80::1'              => false,
	'fd00::1'              => false,
	'fec0::1'              => false,
	'ff02::1'              => false,
	'2001:db8::1'          => false,
	'::ffff:127.0.0.1'     => false,
	'::ffff:169.254.169.254' => false,
	'::ffff:8.8.8.8'       => false, // IPv4-mapped literals are refused as a whole.
	'64:ff9b::a00:1'       => false,
	'64:ff9b::808:808'     => true,
	'2002:7f00:1::'        => false,
	'2002:a9fe:a9fe::'     => false,
	'[::1]'                => false,
	'not-an-ip'            => false,
);
foreach ( $ips as $ip => $want ) {
	http_assert( 'ip ' . $ip, $want, flx_ip_is_public( $ip ) );
}

$urls = array(
	'https://good.example/a'           => true,
	'http://good.example/a'            => true,
	'https://cdn.example/x.png'        => true,
	'https://site.example/wp-json/'    => true,
	'https://meta.example/latest'      => false,
	'https://mixed.example/'           => false,
	'https://v6local.example/'         => false,
	'https://nowhere.example/'         => false,
	'https://169.254.169.254/'         => false,
	'http://127.0.0.1/'                => false,
	'http://[::1]/'                    => false,
	'https://user:pw@good.example/'    => false,
	'https://good.example:8443/'       => false,
	'https://good.example:22/'         => false,
	'ftp://good.example/'              => false,
	'file:///etc/passwd'               => false,
	'gopher://good.example/'           => false,
	'//good.example/'                  => false,
	"https://good.example/\r\nX: y"    => false,
	'https://good.example\\@meta.example/' => false,
	''                                 => false,
);
foreach ( $urls as $url => $want ) {
	http_assert( 'url ' . json_encode( $url ), $want, flx_url_is_public( $url ) );
}
http_assert( 'https only refuses http', false, flx_url_is_public( 'http://good.example/', array( 'https' => true ) ) );
http_assert( 'own host can be refused', false, flx_url_is_public( 'https://site.example/', array( 'own' => false ) ) );
$GLOBALS['allow'] = true;
http_assert( 'filter can open an address', true, flx_url_is_public( 'https://meta.example/' ) );
$GLOBALS['allow'] = null;

// Redirect to the metadata address is refused, and the second request is never sent.
$GLOBALS['script'] = array( 'https://good.example/r' => array( 'code' => 302, 'location' => 'http://169.254.169.254/latest/meta-data/' ) );
$GLOBALS['calls']  = array();
$r                 = flx_safe_remote_get( 'https://good.example/r' );
http_assert( 'redirect to metadata refused', true, is_wp_error( $r ) && 'flx_url_blocked' === $r->get_error_code() );
http_assert( 'redirect to metadata not requested', 1, count( $GLOBALS['calls'] ) );
http_assert( 'WordPress redirects are off', 0, $GLOBALS['calls'][0][1]['redirection'] );

// Redirect to a name that resolves inside.
$GLOBALS['script'] = array( 'https://good.example/r' => array( 'code' => 301, 'location' => 'https://mixed.example/x' ) );
$GLOBALS['calls']  = array();
http_assert( 'redirect to inside name refused', true, is_wp_error( flx_safe_remote_get( 'https://good.example/r' ) ) && 1 === count( $GLOBALS['calls'] ) );

// A public redirect is followed; secrets do not travel to another host; POST becomes GET on 302.
$GLOBALS['script'] = array( 'https://good.example/r' => array( 'code' => 302, 'location' => 'https://cdn.example/x.png' ) );
$GLOBALS['calls']  = array();
$r                 = flx_safe_remote_post( 'https://good.example/r', array( 'headers' => array( 'Authorization' => 'Bearer t', 'X-Api-Key' => 'k', 'Accept' => 'a/b' ), 'body' => 'b' ) );
http_assert( 'public redirect followed', true, ! is_wp_error( $r ) && 200 === $r['code'] && 2 === count( $GLOBALS['calls'] ) );
http_assert( 'first hop keeps the secret', 'Bearer t', $GLOBALS['calls'][0][1]['headers']['Authorization'] );
http_assert( 'second host gets no secret', array( 'Accept' => 'a/b' ), $GLOBALS['calls'][1][1]['headers'] );
http_assert( 'POST becomes GET after 302', true, 'GET' === $GLOBALS['calls'][1][1]['method'] && ! isset( $GLOBALS['calls'][1][1]['body'] ) );

// Same host redirect keeps the headers; relative Location.
$GLOBALS['script'] = array( 'https://good.example/r' => array( 'code' => 307, 'location' => '/next' ) );
$GLOBALS['calls']  = array();
$r                 = flx_safe_remote_post( 'https://good.example/r', array( 'headers' => array( 'Authorization' => 'Bearer t' ), 'body' => 'b' ) );
http_assert( 'same host keeps the secret and the method', true, 'https://good.example/next' === $GLOBALS['calls'][1][0] && 'Bearer t' === $GLOBALS['calls'][1][1]['headers']['Authorization'] && 'POST' === $GLOBALS['calls'][1][1]['method'] );

// Redirect loop stops.
$GLOBALS['script'] = array( 'https://good.example/loop' => array( 'code' => 302, 'location' => 'https://good.example/loop' ) );
$GLOBALS['calls']  = array();
$r                 = flx_safe_remote_get( 'https://good.example/loop', array(), 3 );
http_assert( 'redirect loop stops after the limit', true, is_wp_error( $r ) && 4 === count( $GLOBALS['calls'] ) );

// max_redirects 0: the 3xx answer comes back untouched.
$GLOBALS['calls'] = array();
$r                = flx_safe_remote_get( 'https://good.example/loop', array(), 0 );
http_assert( 'no redirects: 3xx returned', true, ! is_wp_error( $r ) && 302 === $r['code'] && 1 === count( $GLOBALS['calls'] ) );

// A blocked first address sends nothing.
$GLOBALS['calls'] = array();
http_assert( 'blocked address sends nothing', true, is_wp_error( flx_safe_remote_get( 'https://meta.example/' ) ) && 0 === count( $GLOBALS['calls'] ) );

echo $failed ? "\n$failed failed\n" : "\nall passed\n";
exit( $failed ? 1 : 0 );
