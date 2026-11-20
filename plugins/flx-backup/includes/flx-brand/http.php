<?php
/**
 * Shared outbound requests for addresses the site did not choose itself (1.10.0).
 *
 * Use these whenever the address comes from a visitor, a chat message, an AI tool argument, a remote
 * API answer or any other data the site does not control. wp_safe_remote_get() alone still reaches
 * 169.254.0.0/16 (cloud metadata), 100.64.0.0/10 and every IPv6 special range, and does not re-check
 * the name after a redirect.
 *
 *  - flx_url_is_public( $url )                        may this address be requested at all?
 *  - flx_safe_remote_request( $url, $args, $hops )    wp_remote_request() with the checks below
 *  - flx_safe_remote_get() / flx_safe_remote_post()
 *  - flx_safe_download( $url, $timeout, $max_bytes )  download_url() replacement (temp file path)
 *
 * Checks: http(s) only, no user:password, default ports, every A and AAAA address of the name must be
 * public, each redirect hop is checked again, the connection is pinned to the checked address (cURL
 * transport) so a second DNS answer cannot move it, and Authorization / Cookie headers are dropped when
 * a redirect leaves the first host.
 *
 * Do NOT use them for addresses an administrator configured on purpose (a Gotenberg or Evolution server
 * on the internal network) or for the site's own loopback requests.
 *
 * Source: FluxWP/fluxwp tools/brand/flx-brand (copy with tools/brand/sync.py, do not edit the copies).
 *
 * @package flx-brand
 */

defined( 'ABSPATH' ) || exit;

/**
 * Is this IPv4 / IPv6 address a public one (not private, loopback, link-local, CGNAT, documentation,
 * multicast, or an IPv6 form that embeds a non-public IPv4)?
 *
 * @param string $ip Address, IPv6 optionally in brackets.
 */
function flx_ip_is_public( $ip ) {
	$ip = trim( (string) $ip, '[] ' );
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
		return false;
	}
	if ( defined( 'FILTER_FLAG_GLOBAL_RANGE' ) && ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE ) ) {
		return false; // PHP 8.2+: not "global" in RFC 6890.
	}
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
		$n = ip2long( $ip );
		foreach ( array( '0.0.0.0/8', '100.64.0.0/10', '169.254.0.0/16', '192.0.0.0/24', '192.0.2.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4' ) as $cidr ) {
			list( $net, $bits ) = explode( '/', $cidr );
			$mask               = -1 << ( 32 - (int) $bits );
			if ( ( $n & $mask ) === ( ip2long( $net ) & $mask ) ) {
				return false;
			}
		}
		return true;
	}
	$hex = bin2hex( (string) inet_pton( $ip ) );
	if ( 32 !== strlen( $hex ) ) {
		return false;
	}
	// IPv4-mapped ::ffff:0:0/96 and IPv4-compatible ::/96: the inner IPv4 decides.
	if ( 0 === strpos( $hex, '00000000000000000000ffff' ) || 0 === strpos( $hex, '000000000000000000000000' ) ) {
		return flx_ip_is_public( (string) long2ip( (int) hexdec( substr( $hex, 24, 8 ) ) ) );
	}
	// NAT64 64:ff9b::/96 and 6to4 2002::/16 embed an IPv4 too.
	if ( 0 === strpos( $hex, '0064ff9b0000000000000000' ) ) {
		return flx_ip_is_public( (string) long2ip( (int) hexdec( substr( $hex, 24, 8 ) ) ) );
	}
	if ( 0 === strpos( $hex, '2002' ) ) {
		return flx_ip_is_public( (string) long2ip( (int) hexdec( substr( $hex, 4, 8 ) ) ) );
	}
	// Unique local fc00::/7, link-local fe80::/10, site-local fec0::/10, multicast ff00::/8,
	// documentation 2001:db8::/32, Teredo 2001::/32.
	$b = hexdec( substr( $hex, 0, 2 ) );
	if ( 0xfc === ( $b & 0xfe ) || 0xfe === $b || 0xff === $b || 0 === strpos( $hex, '20010db8' ) || 0 === strpos( $hex, '20010000' ) ) {
		return false;
	}
	return true;
}

/**
 * Every address a name resolves to (A + AAAA); the address itself when one was written.
 *
 * @return string[]
 */
function flx_host_ips( $host ) {
	$host = trim( (string) $host, '[]' );
	if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
		return array( $host );
	}
	/**
	 * Resolver override (tests, split DNS). Return an array of addresses to skip the lookup.
	 *
	 * @param null|string[] $ips
	 * @param string        $host
	 */
	$pre = apply_filters( 'flx_host_ips', null, $host );
	if ( is_array( $pre ) ) {
		return array_values( array_unique( array_map( 'strval', $pre ) ) );
	}
	$ips = gethostbynamel( $host );
	$ips = is_array( $ips ) ? $ips : array();
	if ( function_exists( 'dns_get_record' ) ) {
		$aaaa = @dns_get_record( $host, DNS_AAAA ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		foreach ( is_array( $aaaa ) ? $aaaa : array() as $rr ) {
			if ( ! empty( $rr['ipv6'] ) ) {
				$ips[] = (string) $rr['ipv6'];
			}
		}
	}
	return array_values( array_unique( $ips ) );
}

/**
 * Checks an address and returns what the request needs.
 *
 * @param string $url  Address.
 * @param array  $opts own (bool, default true: the site's own host is allowed, as in WordPress),
 *                     https (bool, default false: require https).
 * @return array|WP_Error array( host, port, ips (checked addresses, empty for the own host), own ).
 */
function flx_url_inspect( $url, array $opts = array() ) {
	$url  = (string) $url;
	$bad  = new WP_Error( 'flx_url_blocked', flx_http_str( 'blocked' ) );
	$opts = $opts + array( 'own' => true, 'https' => false );
	if ( ! preg_match( $opts['https'] ? '#^https://#i' : '#^https?://#i', $url ) || preg_match( '/[\x00-\x20\\\\]/', $url ) ) {
		return $bad;
	}
	$p = wp_parse_url( $url );
	if ( ! is_array( $p ) || empty( $p['host'] ) || isset( $p['user'] ) || isset( $p['pass'] ) ) {
		return $bad;
	}
	$host   = strtolower( (string) $p['host'] );
	$scheme = strtolower( (string) $p['scheme'] );
	$port   = isset( $p['port'] ) ? (int) $p['port'] : ( 'https' === $scheme ? 443 : 80 );
	$home   = wp_parse_url( home_url() );
	$own    = $opts['own'] && is_array( $home ) && ! empty( $home['host'] ) && strtolower( (string) $home['host'] ) === $host;
	/**
	 * Ports an outside address may use.
	 *
	 * @param int[]  $ports
	 * @param string $url
	 */
	$ports = (array) apply_filters( 'flx_url_public_ports', array( 80, 443 ), $url );
	if ( ! $own && ! in_array( $port, array_map( 'intval', $ports ), true ) ) {
		return $bad;
	}
	$ips = array();
	$ok  = $own;
	if ( ! $own ) {
		$ips = flx_host_ips( $host );
		$ok  = (bool) $ips;
		foreach ( $ips as $ip ) {
			if ( ! flx_ip_is_public( $ip ) ) {
				$ok = false;
				break;
			}
		}
	}
	/**
	 * Final say on an outbound address (e.g. to open one internal service on purpose).
	 *
	 * @param bool   $ok
	 * @param string $url
	 */
	if ( ! apply_filters( 'flx_url_is_public', $ok, $url ) ) {
		return $bad;
	}
	return array( 'host' => $host, 'port' => $port, 'ips' => $ok && ! $own ? $ips : array(), 'own' => $own );
}

/** May this address be requested? See flx_url_inspect() for $opts. */
function flx_url_is_public( $url, array $opts = array() ) {
	return ! is_wp_error( flx_url_inspect( $url, $opts ) );
}

/** The two texts of this file, TR and EN (no text domain: shared by every package). */
function flx_http_str( $key ) {
	$locale = function_exists( 'determine_locale' ) ? determine_locale() : ( function_exists( 'get_locale' ) ? get_locale() : 'en_US' );
	$tr     = 0 === strpos( (string) $locale, 'tr' );
	$s      = array(
		'blocked'  => $tr ? 'Geçersiz ya da engelli adres. Yalnız herkese açık http(s) adresleri kullanılabilir.' : 'Invalid or blocked address. Only public http(s) addresses can be used.',
		'too_big'  => $tr ? 'Dosya izin verilen boyuttan büyük.' : 'The file is larger than allowed.',
		'download' => $tr ? 'Dosya indirilemedi.' : 'The file could not be downloaded.',
	);
	return isset( $s[ $key ] ) ? $s[ $key ] : '';
}

/**
 * wp_remote_request() for an address the site did not choose. Redirects are followed here, one checked
 * hop at a time; 'redirection' in $args is ignored, use $max_redirects.
 *
 * @param string $url           Address.
 * @param array  $args          wp_remote_request() arguments, plus 'flx_url' => flx_url_inspect() options.
 * @param int    $max_redirects Redirect hops to follow (0 = none: a 3xx answer is returned as it is).
 * @return array|WP_Error
 */
function flx_safe_remote_request( $url, array $args = array(), $max_redirects = 3 ) {
	$opts = isset( $args['flx_url'] ) ? (array) $args['flx_url'] : array();
	unset( $args['flx_url'] );
	$args['redirection']        = 0;
	$args['reject_unsafe_urls'] = false; // Checked here, more strictly; WordPress' own check would refuse the own host on a custom port.
	$first                      = '';
	for ( $i = 0; $i <= (int) $max_redirects; $i++ ) {
		$info = flx_url_inspect( $url, $opts );
		if ( is_wp_error( $info ) ) {
			return $info;
		}
		if ( '' === $first ) {
			$first = $info['host'];
		} elseif ( $info['host'] !== $first && ! empty( $args['headers'] ) && is_array( $args['headers'] ) ) {
			foreach ( array_keys( $args['headers'] ) as $h ) {
				if ( in_array( strtolower( (string) $h ), array( 'authorization', 'cookie', 'proxy-authorization' ), true ) || 0 === stripos( (string) $h, 'x-' ) ) {
					unset( $args['headers'][ $h ] );
				}
			}
			unset( $args['cookies'] );
		}
		$pin = null;
		if ( $info['ips'] && ! filter_var( $info['host'], FILTER_VALIDATE_IP ) ) {
			$v4  = array_values( array_filter( $info['ips'], function ( $ip ) { return (bool) filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ); } ) );
			$to  = $v4 ? $v4[0] : '[' . $info['ips'][0] . ']';
			$map = array( $info['host'] . ':' . $info['port'] . ':' . $to );
			$pin = function ( $handle ) use ( $map ) {
				if ( defined( 'CURLOPT_RESOLVE' ) ) {
					curl_setopt( $handle, CURLOPT_RESOLVE, $map ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				}
			};
			add_action( 'http_api_curl', $pin, 99 );
		}
		$r = wp_remote_request( $url, $args );
		if ( $pin ) {
			remove_action( 'http_api_curl', $pin, 99 );
		}
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		$loc  = wp_remote_retrieve_header( $r, 'location' );
		$loc  = is_array( $loc ) ? (string) end( $loc ) : (string) $loc;
		if ( $code < 300 || $code > 399 || 304 === $code || '' === $loc || (int) $max_redirects < 1 ) {
			return $r;
		}
		if ( $i >= (int) $max_redirects ) {
			break; // Too many redirects.
		}
		if ( ! empty( $args['stream'] ) && ! empty( $args['filename'] ) && is_file( $args['filename'] ) ) {
			file_put_contents( $args['filename'], '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- the redirect body is not the file
		}
		$method = strtoupper( isset( $args['method'] ) ? (string) $args['method'] : 'GET' );
		if ( in_array( $code, array( 301, 302, 303 ), true ) && ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
			$args['method'] = 'GET';
			unset( $args['body'] );
		}
		$url = WP_Http::make_absolute_url( $loc, $url );
	}
	return new WP_Error( 'flx_url_blocked', flx_http_str( 'blocked' ) );
}

/** GET, see flx_safe_remote_request(). */
function flx_safe_remote_get( $url, array $args = array(), $max_redirects = 3 ) {
	$args['method'] = 'GET';
	return flx_safe_remote_request( $url, $args, $max_redirects );
}

/** POST, see flx_safe_remote_request(). */
function flx_safe_remote_post( $url, array $args = array(), $max_redirects = 3 ) {
	$args['method'] = 'POST';
	return flx_safe_remote_request( $url, $args, $max_redirects );
}

/**
 * download_url() for an address the site did not choose: the file is streamed to a temp file.
 * The caller deletes the file (media_handle_sideload() does it when it succeeds).
 *
 * @param string $url       Address.
 * @param int    $timeout   Seconds.
 * @param int    $max_bytes Size limit (0 = wp_max_upload_size()).
 * @param array  $args      Extra wp_remote_request() arguments (headers, user-agent, flx_url).
 * @return string|WP_Error Temp file path.
 */
function flx_safe_download( $url, $timeout = 60, $max_bytes = 0, array $args = array() ) {
	if ( ! function_exists( 'wp_tempnam' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	$max = (int) $max_bytes > 0 ? (int) $max_bytes : (int) wp_max_upload_size();
	$tmp = wp_tempnam( basename( (string) wp_parse_url( (string) $url, PHP_URL_PATH ) ) );
	if ( ! $tmp ) {
		return new WP_Error( 'flx_download', flx_http_str( 'download' ) );
	}
	$r = flx_safe_remote_get(
		$url,
		array(
			'timeout'             => (int) $timeout,
			'stream'              => true,
			'filename'            => $tmp,
			'limit_response_size' => $max + 1,
		) + $args
	);
	$code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
	if ( 200 !== $code || ! is_file( $tmp ) || 0 === (int) filesize( $tmp ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		return is_wp_error( $r ) ? $r : new WP_Error( 'flx_download', flx_http_str( 'download' ) . ' (HTTP ' . $code . ')' );
	}
	if ( filesize( $tmp ) > $max ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		return new WP_Error( 'flx_download_size', flx_http_str( 'too_big' ) );
	}
	return $tmp;
}
