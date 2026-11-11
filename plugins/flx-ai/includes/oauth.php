<?php
/**
 * MCP için OAuth 2.1 yetkilendirme sunucusu (Flux AI & MCP'nin kendi girişi; Flux App gerekmez).
 *
 * Claude.ai, ChatGPT, Cursor, VS Code gibi istemciler yalnız site.com/mcp adresiyle bağlanır:
 * 401 + WWW-Authenticate → /.well-known/oauth-protected-resource (RFC 9728) → /.well-known/oauth-authorization-server
 * (RFC 8414) → dinamik istemci kaydı (RFC 7591) → site.com/mcp/authorize (WordPress girişi + onay, PKCE S256) →
 * belirteç (/wp-json/flx-mcp/v1/oauth/token, yenileme belirteci döner) → MCP isteklerinde Bearer.
 *
 * Onaylanan her bağlantı MCP anahtar listesinde (flxai_mcp_keys, type=oauth) durur; Flux AI & MCP › MCP'den iptal edilir.
 * Elle oluşturulan anahtarlar (Bearer, X-MCP-Key) aynen çalışır. Sorgu dizesindeki anahtar kabul edilmez.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

const FLXAI_OAUTH_PREFIX = array(
	'access'  => 'flxmcpat_',
	'refresh' => 'flxmcprt_',
	'code'    => 'flxmcpcd_',
	'client'  => 'flxmcpc_',
	'secret'  => 'flxmcpcs_',
);

/** OAuth girişi açık mı? MCP açıksa varsayılan açık (Flux AI & MCP › MCP'den kapatılır). */
function flxai_oauth_enabled() {
	if ( flxai_mcp_legacy() || ! flxai_mcp_enabled() ) {
		return false;
	}
	$s = get_option( 'flxai_settings', array() );
	return ! is_array( $s ) || ! array_key_exists( 'mcp_oauth', $s ) || ! empty( $s['mcp_oauth'] );
}

function flxai_oauth_issuer() {
	return untrailingslashit( home_url() );
}

/** Erişim belirteci ömrü (sn). Filtre: flx_mcp_oauth_access_ttl. */
function flxai_oauth_access_ttl() {
	return max( 300, (int) apply_filters( 'flx_mcp_oauth_access_ttl', HOUR_IN_SECONDS ) );
}

/** Kullanılmazsa bağlantının düşeceği süre (sn); her yenilemede baştan sayar. Filtre: flx_mcp_oauth_refresh_ttl. */
function flxai_oauth_refresh_ttl() {
	return max( DAY_IN_SECONDS, (int) apply_filters( 'flx_mcp_oauth_refresh_ttl', 90 * DAY_IN_SECONDS ) );
}

function flxai_oauth_authorize_url() {
	$short = flxai_mcp_short_path();
	if ( '' !== $short && get_option( 'permalink_structure' ) ) {
		return home_url( '/' . $short . '/authorize' );
	}
	return add_query_arg( 'flxai_oauth', 'authorize', home_url( '/' ) );
}

function flxai_oauth_metadata() {
	return (array) apply_filters(
		'flx_mcp_oauth_metadata',
		array(
			'issuer'                                => flxai_oauth_issuer(),
			'authorization_endpoint'                => flxai_oauth_authorize_url(),
			'token_endpoint'                        => rest_url( 'flx-mcp/v1/oauth/token' ),
			'registration_endpoint'                 => rest_url( 'flx-mcp/v1/oauth/register' ),
			'revocation_endpoint'                   => rest_url( 'flx-mcp/v1/oauth/revoke' ),
			'response_types_supported'              => array( 'code' ),
			'response_modes_supported'              => array( 'query' ),
			'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
			'code_challenge_methods_supported'      => array( 'S256' ),
			'token_endpoint_auth_methods_supported' => array( 'none', 'client_secret_post', 'client_secret_basic' ),
			'revocation_endpoint_auth_methods_supported' => array( 'none', 'client_secret_post', 'client_secret_basic' ),
			'scopes_supported'                      => array( 'mcp', 'offline_access' ),
			'authorization_response_iss_parameter_supported' => true,
		)
	);
}

/** MCP sunucusunun yolları (site köküne göre): kısa adres ve REST adresi. */
function flxai_oauth_resource_paths() {
	$rest  = ltrim( substr( (string) wp_parse_url( rest_url( 'flx-mcp/v1/mcp' ), PHP_URL_PATH ), strlen( flxai_home_path() ) ), '/' );
	$paths = array( untrailingslashit( $rest ) );
	if ( '' !== flxai_mcp_short_path() ) {
		$paths[] = flxai_mcp_short_path();
	}
	return $paths;
}

/** Korunan kaynak bilgisi (RFC 9728). $path: istemcinin bağlandığı yol (ör. "mcp"); resource bununla eşleşmeli. */
function flxai_oauth_resource_metadata( $path = '' ) {
	$path     = trim( (string) $path, '/' );
	$resource = in_array( $path, flxai_oauth_resource_paths(), true ) ? flxai_oauth_issuer() . '/' . $path : flxai_mcp_url();
	return array(
		'resource'                 => $resource,
		'authorization_servers'    => array( flxai_oauth_issuer() ),
		'bearer_methods_supported' => array( 'header' ),
		'scopes_supported'         => array( 'mcp', 'offline_access' ),
		'resource_name'            => get_bloginfo( 'name' ),
	);
}

/**
 * Bu .well-known isteğine Flux AI mı yanıt veriyor? Flux App kendi uçları (flxapp, flx-studio) için yanıt vermeye devam eder.
 *
 * @param string $rest .well-known/ sonrası, ör. "oauth-protected-resource/mcp".
 */
function flxai_oauth_owns( $rest ) {
	if ( ! flxai_oauth_enabled() ) {
		return false;
	}
	$rest = trim( (string) $rest, '/' );
	if ( 'oauth-authorization-server' === $rest || 0 === strpos( $rest, 'oauth-authorization-server/' ) ) {
		return true;
	}
	if ( 'oauth-protected-resource' === $rest ) {
		return true;
	}
	if ( 0 === strpos( $rest, 'oauth-protected-resource/' ) ) {
		return in_array( substr( $rest, strlen( 'oauth-protected-resource/' ) ), flxai_oauth_resource_paths(), true );
	}
	return false;
}

/* ---------------------------------------------------------------------------
 * İstemciler (RFC 7591)
 * ------------------------------------------------------------------------- */

function flxai_oauth_clients() {
	return (array) get_option( 'flxai_oauth_clients', array() );
}

function flxai_oauth_client( $id ) {
	$all = flxai_oauth_clients();
	return is_string( $id ) && isset( $all[ $id ] ) ? $all[ $id ] : null;
}

function flxai_oauth_is_loopback( $host ) {
	return in_array( strtolower( trim( (string) $host, '[]' ) ), array( '127.0.0.1', 'localhost', '::1' ), true );
}

/** Kayıtta kabul edilen dönüş adresi: https, loopback http ya da uygulama şeması (cursor://, vscode://). */
function flxai_oauth_redirect_format_ok( $uri ) {
	$p = wp_parse_url( (string) $uri );
	if ( ! $p || empty( $p['scheme'] ) || ! empty( $p['fragment'] ) || strlen( (string) $uri ) > 500 ) {
		return false;
	}
	$scheme = strtolower( $p['scheme'] );
	if ( 'https' === $scheme ) {
		return ! empty( $p['host'] );
	}
	if ( 'http' === $scheme ) {
		return flxai_oauth_is_loopback( $p['host'] ?? '' );
	}
	if ( in_array( $scheme, array( 'javascript', 'data', 'file', 'vbscript', 'blob', 'about' ), true ) ) {
		return false;
	}
	return (bool) preg_match( '/^[a-z][a-z0-9+.\-]{2,}$/', $scheme );
}

/** Tam eşleşme; loopback adreslerde port serbest (RFC 8252 §7.3). */
function flxai_oauth_redirect_ok( array $client, $uri ) {
	if ( ! is_string( $uri ) || '' === $uri || strlen( $uri ) > 500 ) {
		return false;
	}
	$p = wp_parse_url( $uri );
	if ( ! $p || empty( $p['scheme'] ) || ! empty( $p['fragment'] ) ) {
		return false;
	}
	foreach ( (array) $client['redirect_uris'] as $reg ) {
		if ( $reg === $uri ) {
			return true;
		}
		$r = wp_parse_url( $reg );
		if ( $r && 'http' === ( $r['scheme'] ?? '' ) && 'http' === $p['scheme'] && flxai_oauth_is_loopback( $r['host'] ?? '' ) && flxai_oauth_is_loopback( $p['host'] ?? '' )
			&& ( $r['path'] ?? '/' ) === ( $p['path'] ?? '/' ) && ( $r['query'] ?? '' ) === ( $p['query'] ?? '' ) ) {
			return true;
		}
	}
	return false;
}

/** Hiç bağlantı açmamış, bir günden eski istemcileri siler (açık kayıt ucu dolmasın). */
function flxai_oauth_prune_clients( array $all ) {
	$used = array();
	foreach ( flxai_mcp_keys() as $k ) {
		if ( ! empty( $k['client'] ) ) {
			$used[ $k['client'] ] = true;
		}
	}
	foreach ( $all as $id => $c ) {
		if ( empty( $used[ $id ] ) && time() - (int) ( $c['created'] ?? 0 ) > DAY_IN_SECONDS ) {
			unset( $all[ $id ] );
		}
	}
	return $all;
}

/** Basit oran sınırı: IP başına pencerede en çok $max. */
function flxai_oauth_rate( $bucket, $max, $window ) {
	$ip  = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$key = 'flxai_orl_' . md5( $bucket . '|' . $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= $max ) {
		return false;
	}
	set_transient( $key, $n + 1, $window );
	return true;
}

function flxai_oauth_random( $prefix ) {
	return $prefix . wp_generate_password( 43, false, false );
}

/* ---------------------------------------------------------------------------
 * Bağlantılar (belirteçler flxai_mcp_keys içinde, yalnız özet saklanır)
 * ------------------------------------------------------------------------- */

/** Süresi dolmuş OAuth bağlantılarını atar. */
function flxai_oauth_prune_grants( array $keys ) {
	foreach ( $keys as $id => $k ) {
		if ( 'oauth' === ( $k['type'] ?? '' ) && (int) ( $k['rexp'] ?? 0 ) < time() ) {
			unset( $keys[ $id ] );
		}
	}
	return $keys;
}

/** Yeni erişim + yenileme belirteci üretir, bağlantıya yazar ve OAuth yanıtını döner. */
function flxai_oauth_issue( array $keys, $id ) {
	$access  = flxai_oauth_random( FLXAI_OAUTH_PREFIX['access'] );
	$refresh = flxai_oauth_random( FLXAI_OAUTH_PREFIX['refresh'] );
	$ttl     = flxai_oauth_access_ttl();
	if ( ! empty( $keys[ $id ]['rhash'] ) ) {
		$keys[ $id ]['prev_rhash'] = $keys[ $id ]['rhash']; // Eski yenileme belirteci tekrar gelirse bağlantı kapatılır.
	}
	$keys[ $id ]['hash']  = hash( 'sha256', $access );
	$keys[ $id ]['exp']   = time() + $ttl;
	$keys[ $id ]['rhash'] = hash( 'sha256', $refresh );
	$keys[ $id ]['rexp']  = time() + flxai_oauth_refresh_ttl();
	update_option( 'flxai_mcp_keys', flxai_oauth_prune_grants( $keys ), false );
	return flxai_oauth_json(
		array(
			'access_token'  => $access,
			'token_type'    => 'Bearer',
			'expires_in'    => $ttl,
			'refresh_token' => $refresh,
			'scope'         => 'mcp',
		)
	);
}

function flxai_oauth_json( $data, $status = 200 ) {
	$r = new WP_REST_Response( $data, $status );
	$r->header( 'Cache-Control', 'no-store' );
	$r->header( 'Pragma', 'no-cache' );
	$r->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
	return $r;
}

function flxai_oauth_error( $error, $desc = '', $status = 400 ) {
	return flxai_oauth_json( array_filter( array( 'error' => $error, 'error_description' => $desc ) ), $status );
}

/** client_id + secret: gövde ya da Basic başlık. Genel istemcide (secret yok) yalnız client_id. */
function flxai_oauth_request_client( WP_REST_Request $req ) {
	$id     = (string) $req->get_param( 'client_id' );
	$secret = (string) $req->get_param( 'client_secret' );
	$auth   = (string) $req->get_header( 'authorization' );
	if ( 0 === stripos( $auth, 'Basic ' ) ) {
		$pair = explode( ':', (string) base64_decode( substr( $auth, 6 ) ), 2 ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( 2 === count( $pair ) ) {
			$id     = rawurldecode( $pair[0] );
			$secret = rawurldecode( $pair[1] );
		}
	}
	$client = flxai_oauth_client( $id );
	if ( ! $client ) {
		return null;
	}
	if ( ! empty( $client['secret_hash'] ) && ! hash_equals( $client['secret_hash'], hash( 'sha256', $secret ) ) ) {
		return null;
	}
	return $client;
}

/* ---------------------------------------------------------------------------
 * REST: metadata, kayıt, belirteç, iptal
 * ------------------------------------------------------------------------- */

function flxai_oauth_public_permission() {
	if ( ! flxai_oauth_enabled() ) {
		return new WP_Error( 'mcp_oauth_off', __( 'OAuth sign-in for MCP is off.', 'flx-ai' ), array( 'status' => 404 ) );
	}
	return flxai_oauth_rate( 'oauth', 120, MINUTE_IN_SECONDS ) ? true : new WP_Error( 'mcp_rate', 'Too many requests', array( 'status' => 429 ) );
}

add_action(
	'rest_api_init',
	function () {
		if ( flxai_mcp_legacy() ) {
			return;
		}
		$open = 'flxai_oauth_public_permission';
		register_rest_route(
			'flx-mcp/v1',
			'/oauth/metadata',
			array(
				'methods'             => 'GET',
				'callback'            => function () {
					return flxai_oauth_json( flxai_oauth_metadata() );
				},
				'permission_callback' => $open,
			)
		);
		register_rest_route(
			'flx-mcp/v1',
			'/oauth/resource',
			array(
				'methods'             => 'GET',
				'callback'            => function ( WP_REST_Request $req ) {
					return flxai_oauth_json( flxai_oauth_resource_metadata( (string) $req->get_param( 'path' ) ) );
				},
				'permission_callback' => $open,
			)
		);
		register_rest_route( 'flx-mcp/v1', '/oauth/register', array( 'methods' => 'POST', 'callback' => 'flxai_oauth_rest_register', 'permission_callback' => $open ) );
		register_rest_route( 'flx-mcp/v1', '/oauth/token', array( 'methods' => 'POST', 'callback' => 'flxai_oauth_rest_token', 'permission_callback' => $open ) );
		register_rest_route( 'flx-mcp/v1', '/oauth/revoke', array( 'methods' => 'POST', 'callback' => 'flxai_oauth_rest_revoke', 'permission_callback' => $open ) );
	}
);

function flxai_oauth_rest_register( WP_REST_Request $req ) {
	if ( ! flxai_oauth_rate( 'register', 20, HOUR_IN_SECONDS ) ) {
		return flxai_oauth_error( 'temporarily_unavailable', '', 429 );
	}
	$body = (array) $req->get_json_params();
	$name = mb_substr( sanitize_text_field( (string) ( $body['client_name'] ?? '' ) ), 0, 80 );
	$uris = array_values( array_unique( array_filter( array_map( 'trim', array_filter( (array) ( $body['redirect_uris'] ?? array() ), 'is_string' ) ) ) ) );
	if ( ! $uris || count( $uris ) > 10 ) {
		return flxai_oauth_error( 'invalid_redirect_uri', 'redirect_uris: 1-10 required' );
	}
	foreach ( $uris as $u ) {
		if ( ! flxai_oauth_redirect_format_ok( $u ) ) {
			return flxai_oauth_error( 'invalid_redirect_uri', 'Rejected: ' . $u );
		}
	}
	$all = flxai_oauth_clients();
	if ( count( $all ) >= 100 ) {
		$all = flxai_oauth_prune_clients( $all );
		if ( count( $all ) >= 100 ) {
			return flxai_oauth_error( 'invalid_client_metadata', 'Client limit reached' );
		}
	}
	$method = (string) ( $body['token_endpoint_auth_method'] ?? 'none' );
	$secret = 'none' !== $method ? flxai_oauth_random( FLXAI_OAUTH_PREFIX['secret'] ) : '';
	$id     = FLXAI_OAUTH_PREFIX['client'] . strtolower( wp_generate_password( 20, false, false ) );
	$client = array(
		'id'            => $id,
		'name'          => '' !== $name ? $name : 'MCP',
		'redirect_uris' => $uris,
		'secret_hash'   => $secret ? hash( 'sha256', $secret ) : '',
		'created'       => time(),
	);
	$all[ $id ] = $client;
	update_option( 'flxai_oauth_clients', $all, false );
	$out = array(
		'client_id'                  => $id,
		'client_id_issued_at'        => $client['created'],
		'client_name'                => $client['name'],
		'redirect_uris'              => $uris,
		'grant_types'                => array( 'authorization_code', 'refresh_token' ),
		'response_types'             => array( 'code' ),
		'token_endpoint_auth_method' => $secret ? ( 'client_secret_basic' === $method ? 'client_secret_basic' : 'client_secret_post' ) : 'none',
	);
	if ( $secret ) {
		$out['client_secret']            = $secret;
		$out['client_secret_expires_at'] = 0;
	}
	return flxai_oauth_json( $out, 201 );
}

function flxai_oauth_rest_token( WP_REST_Request $req ) {
	$client = flxai_oauth_request_client( $req );
	if ( ! $client ) {
		return flxai_oauth_error( 'invalid_client', '', 401 );
	}
	$grant = (string) $req->get_param( 'grant_type' );
	$keys  = flxai_mcp_keys();

	if ( 'authorization_code' === $grant ) {
		$code = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $req->get_param( 'code' ) );
		$tkey = 'flxai_ocode_' . hash( 'sha256', $code );
		$d    = '' !== $code ? get_transient( $tkey ) : false;
		delete_transient( $tkey ); // Tek kullanım.
		if ( ! is_array( $d ) || $d['client'] !== $client['id'] ) {
			return flxai_oauth_error( 'invalid_grant', 'code' );
		}
		if ( (string) $req->get_param( 'redirect_uri' ) !== $d['redirect'] ) {
			return flxai_oauth_error( 'invalid_grant', 'redirect_uri' );
		}
		$verifier = (string) $req->get_param( 'code_verifier' );
		$calc     = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		if ( ! preg_match( '/^[A-Za-z0-9\-._~]{43,128}$/', $verifier ) || ! hash_equals( $d['challenge'], $calc ) ) {
			return flxai_oauth_error( 'invalid_grant', 'code_verifier' );
		}
		$user = get_userdata( (int) $d['user'] );
		if ( ! $user || ! user_can( $user, 'edit_posts' ) ) {
			return flxai_oauth_error( 'invalid_grant', 'user' );
		}
		$id          = wp_generate_uuid4();
		$keys[ $id ] = array(
			'name'      => $client['name'],
			'type'      => 'oauth',
			'client'    => $client['id'],
			'user'      => (int) $user->ID,
			'created'   => time(),
			'last_used' => 0,
			'hint'      => '',
		);
		return flxai_oauth_issue( $keys, $id );
	}

	if ( 'refresh_token' === $grant ) {
		$hash = hash( 'sha256', (string) $req->get_param( 'refresh_token' ) );
		foreach ( $keys as $id => $k ) {
			if ( 'oauth' !== ( $k['type'] ?? '' ) || ( $k['client'] ?? '' ) !== $client['id'] ) {
				continue;
			}
			if ( ! empty( $k['prev_rhash'] ) && hash_equals( $k['prev_rhash'], $hash ) ) {
				// Kullanılmış yenileme belirteci tekrar geldi: çalınmış olabilir, bağlantıyı kapat.
				unset( $keys[ $id ] );
				update_option( 'flxai_mcp_keys', $keys, false );
				return flxai_oauth_error( 'invalid_grant' );
			}
			if ( ! empty( $k['rhash'] ) && hash_equals( $k['rhash'], $hash ) ) {
				if ( (int) ( $k['rexp'] ?? 0 ) < time() || ! user_can( (int) $k['user'], 'edit_posts' ) ) {
					return flxai_oauth_error( 'invalid_grant' );
				}
				return flxai_oauth_issue( $keys, $id );
			}
		}
		return flxai_oauth_error( 'invalid_grant' );
	}

	return flxai_oauth_error( 'unsupported_grant_type' );
}

/** RFC 7009: bilinmeyen belirteçte de 200. */
function flxai_oauth_rest_revoke( WP_REST_Request $req ) {
	$client = flxai_oauth_request_client( $req );
	$hash   = hash( 'sha256', (string) $req->get_param( 'token' ) );
	if ( $client ) {
		$keys = flxai_mcp_keys();
		foreach ( $keys as $id => $k ) {
			if ( 'oauth' === ( $k['type'] ?? '' ) && ( $k['client'] ?? '' ) === $client['id']
				&& ( hash_equals( (string) ( $k['hash'] ?? '' ), $hash ) || hash_equals( (string) ( $k['rhash'] ?? '' ), $hash ) ) ) {
				unset( $keys[ $id ] );
				update_option( 'flxai_mcp_keys', $keys, false );
				break;
			}
		}
	}
	return flxai_oauth_json( null, 200 );
}

/* Belirteç ucuna gelen Basic başlığı (client_secret_basic) WordPress uygulama parolası sanılmasın. */
add_filter(
	'application_password_is_api_request',
	function ( $is ) {
		$uri = (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$rr  = (string) wp_unslash( $_GET['rest_route'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification
		return false !== strpos( $uri, 'flx-mcp/v1/oauth' ) || 0 === strpos( $rr, '/flx-mcp/v1/oauth' ) ? false : $is;
	}
);

/* MCP 401 dönünce istemciye korunan kaynak bilgisinin adresini göster (MCP yetkilendirme şartı). */
add_filter(
	'rest_post_dispatch',
	function ( $res, $server, $req ) {
		if ( ! ( $res instanceof WP_HTTP_Response ) || 401 !== $res->get_status() || '/flx-mcp/v1/mcp' !== $req->get_route() || ! flxai_oauth_enabled() ) {
			return $res;
		}
		$path = flxai_mcp_is_short_request() ? flxai_mcp_short_path() : ltrim( substr( (string) wp_parse_url( rest_url( 'flx-mcp/v1/mcp' ), PHP_URL_PATH ), strlen( flxai_home_path() ) ), '/' );
		$meta = get_option( 'permalink_structure' )
			? flxai_oauth_issuer() . '/.well-known/oauth-protected-resource/' . $path
			: add_query_arg( 'path', rawurlencode( $path ), rest_url( 'flx-mcp/v1/oauth/resource' ) );
		$err  = '' !== flxai_mcp_request_key( $req ) ? 'error="invalid_token", ' : '';
		$res->header( 'WWW-Authenticate', 'Bearer ' . $err . 'resource_metadata="' . esc_url_raw( $meta ) . '"' );
		return $res;
	},
	20,
	3
);

/* ---------------------------------------------------------------------------
 * Onay ekranı: site.com/mcp/authorize
 * ------------------------------------------------------------------------- */

add_action(
	'wp_loaded',
	function () {
		if ( ! flxai_oauth_is_authorize_request() ) {
			return;
		}
		flxai_oauth_authorize();
	}
);

function flxai_oauth_is_authorize_request() {
	if ( 'authorize' === ( $_GET['flxai_oauth'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return true;
	}
	$short = flxai_mcp_short_path();
	return '' !== $short && $short . '/authorize' === flxai_request_rel_path();
}

function flxai_oauth_redirect_with( $uri, array $params ) {
	$params['iss'] = flxai_oauth_issuer();
	$sep           = false === strpos( $uri, '?' ) ? '?' : '&';
	nocache_headers();
	// Uygulama şemalarına (cursor://, vscode://) da gidebilmeli; wp_safe_redirect yalnız aynı alan adına izin verir.
	header( 'Location: ' . $uri . $sep . http_build_query( array_filter( $params, 'strlen' ), '', '&', PHP_QUERY_RFC3986 ), true, 302 );
	exit;
}

function flxai_oauth_authorize() {
	$post = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' );
	$src  = $post ? $_POST : $_GET; // phpcs:ignore WordPress.Security.NonceVerification
	$p    = array();
	foreach ( array( 'response_type', 'client_id', 'redirect_uri', 'state', 'scope', 'code_challenge', 'code_challenge_method', 'resource' ) as $k ) {
		$p[ $k ] = isset( $src[ $k ] ) && is_string( $src[ $k ] ) ? trim( wp_unslash( $src[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}
	if ( ! flxai_oauth_enabled() ) {
		flxai_oauth_page_error( __( 'Connecting assistants with sign-in is turned off on this site.', 'flx-ai' ) );
	}
	$client = flxai_oauth_client( $p['client_id'] );
	if ( ! $client ) {
		flxai_oauth_page_error( __( 'Unknown application. Remove the connector and add it again.', 'flx-ai' ) );
	}
	if ( ! flxai_oauth_redirect_ok( $client, $p['redirect_uri'] ) ) {
		flxai_oauth_page_error( __( 'This return address is not registered for the application.', 'flx-ai' ) );
	}
	// Buradan sonra hatalar istemciye yönlendirilir.
	$back = array( 'state' => $p['state'] );
	if ( 'code' !== $p['response_type'] ) {
		flxai_oauth_redirect_with( $p['redirect_uri'], $back + array( 'error' => 'unsupported_response_type' ) );
	}
	if ( 'S256' !== $p['code_challenge_method'] || ! preg_match( '/^[A-Za-z0-9_\-]{43,128}$/', $p['code_challenge'] ) ) {
		flxai_oauth_redirect_with( $p['redirect_uri'], $back + array( 'error' => 'invalid_request', 'error_description' => 'PKCE (S256) required' ) );
	}
	$here = add_query_arg( array_map( 'rawurlencode', array_filter( $p, 'strlen' ) ), flxai_oauth_authorize_url() );
	if ( ! is_user_logged_in() ) {
		nocache_headers();
		wp_safe_redirect( wp_login_url( $here ) );
		exit;
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		flxai_oauth_page_error( __( 'Your account can\'t connect an assistant (content editing permission is required).', 'flx-ai' ) );
	}
	$user = wp_get_current_user();

	if ( $post ) {
		if ( ! isset( $_POST['_flxai'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_flxai'] ) ), 'flxai_oauth_' . $client['id'] ) ) {
			flxai_oauth_page_error( __( 'The page expired. Start connecting again from the assistant.', 'flx-ai' ) );
		}
		if ( empty( $_POST['allow'] ) ) {
			flxai_oauth_redirect_with( $p['redirect_uri'], $back + array( 'error' => 'access_denied' ) );
		}
		$code = flxai_oauth_random( FLXAI_OAUTH_PREFIX['code'] );
		set_transient(
			'flxai_ocode_' . hash( 'sha256', $code ),
			array(
				'client'    => $client['id'],
				'user'      => $user->ID,
				'redirect'  => $p['redirect_uri'],
				'challenge' => $p['code_challenge'],
			),
			10 * MINUTE_IN_SECONDS
		);
		flxai_oauth_redirect_with( $p['redirect_uri'], $back + array( 'code' => $code ) );
	}

	$host   = wp_parse_url( $p['redirect_uri'], PHP_URL_HOST );
	$scheme = wp_parse_url( $p['redirect_uri'], PHP_URL_SCHEME );
	$dest   = 'https' === $scheme ? (string) $host : ( 'http' === $scheme ? '' : $scheme . '://' );
	$switch = wp_logout_url( $here );
	ob_start();
	?>
	<form method="post" action="<?php echo esc_url( flxai_oauth_authorize_url() ); ?>" class="flxai-oauth">
		<h1>
			<?php
			/* translators: 1: app name (e.g. Claude), 2: site name */
			printf( esc_html__( '%1$s wants to connect to %2$s', 'flx-ai' ), '<strong>' . esc_html( $client['name'] ) . '</strong>', '<strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>' );
			?>
		</h1>
		<div class="flxai-oauth-account">
			<?php echo get_avatar( $user->ID, 40 ); ?>
			<div><strong><?php echo esc_html( $user->display_name ); ?></strong><span><?php echo esc_html( $user->user_email ); ?></span></div>
			<a href="<?php echo esc_url( $switch ); ?>"><?php esc_html_e( 'Change', 'flx-ai' ); ?></a>
		</div>
		<ul>
			<li><?php esc_html_e( 'The assistant uses the site\'s MCP tools with your permissions.', 'flx-ai' ); ?></li>
			<li><?php esc_html_e( 'New content is created as a draft; deleting only moves to trash.', 'flx-ai' ); ?></li>
			<li><?php esc_html_e( 'You can end the connection any time under Flux AI & MCP › MCP.', 'flx-ai' ); ?></li>
			<?php if ( '' !== $dest ) : ?>
				<li>
					<?php
					/* translators: %s: host name or app scheme */
					printf( esc_html__( 'After approval you return to %s.', 'flx-ai' ), '<code>' . esc_html( $dest ) . '</code>' );
					?>
				</li>
			<?php endif; ?>
		</ul>
		<?php foreach ( $p as $k => $v ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $v ); ?>">
		<?php endforeach; ?>
		<?php wp_nonce_field( 'flxai_oauth_' . $client['id'], '_flxai', false ); ?>
		<div class="flxai-oauth-actions">
			<button type="submit" name="deny" value="1"><?php esc_html_e( 'Deny', 'flx-ai' ); ?></button>
			<button type="submit" name="allow" value="1" class="is-primary"><?php esc_html_e( 'Allow', 'flx-ai' ); ?></button>
		</div>
	</form>
	<?php
	flxai_oauth_page( __( 'Connect assistant', 'flx-ai' ), (string) ob_get_clean() );
}

function flxai_oauth_page_error( $message ) {
	flxai_oauth_page( __( 'Connect assistant', 'flx-ai' ), '<div class="flxai-oauth"><p class="flxai-oauth-error">' . esc_html( $message ) . '</p></div>', 400 );
}

/** Tema ve wp-login'den bağımsız tek kartlık sayfa (Flux Speed özel giriş adresi açıkken de çalışır). */
function flxai_oauth_page( $title, $body, $status = 200 ) {
	nocache_headers();
	status_header( $status );
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Frame-Options: DENY' );
	header( "Content-Security-Policy: frame-ancestors 'none'" );
	$icon = get_site_icon_url( 128 );
	$icon = $icon ? $icon : FLXAI_URL . 'assets/icon.svg';
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $title . ' · ' . get_bloginfo( 'name' ) ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( FLXAI_URL . 'assets/css/oauth.css?ver=' . FLXAI_VERSION ); ?>">
</head>
<body class="flxai-oauth-page">
<main>
	<div class="flxai-oauth-brand"><img src="<?php echo esc_url( $icon ); ?>" alt="" width="56" height="56"><span><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span></div>
	<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by callers ?>
	<p class="flxai-oauth-foot"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to site', 'flx-ai' ); ?></a> · Flux AI &amp; MCP</p>
</main>
</body>
</html>
	<?php
	exit;
}
