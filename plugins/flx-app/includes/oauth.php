<?php
/**
 * OAuth 2.0 yetkilendirme sunucusu (RFC 6749 + PKCE RFC 7636, yalnız S256).
 *
 * - Yetkilendirme: wp-login.php?action=flxapp_authorize (WordPress girişi + onay ekranı)
 * - Belirteç:      POST /wp-json/flxapp/v1/oauth/token  (authorization_code, refresh_token)
 * - İptal:         POST /wp-json/flxapp/v1/oauth/revoke (RFC 7009)
 * - Dinamik kayıt: POST /wp-json/flxapp/v1/oauth/register (RFC 7591, ayardan kapatılabilir)
 * - Metadata:      /.well-known/oauth-authorization-server (RFC 8414),
 *                  /.well-known/oauth-protected-resource[/<yol>] (RFC 9728, MCP istemcileri için)
 * - Panel oturumu: wp-login.php?action=flxapp_sso&t=… (uygulamanın açtığı web görünümüne tek seferlik giriş)
 */

defined( 'ABSPATH' ) || exit;

const FLXAPP_SCOPES = array( 'flx', 'mcp', 'offline_access' );

function flxapp_authorize_url() {
	return add_query_arg( 'action', 'flxapp_authorize', site_url( 'wp-login.php', 'login' ) );
}

function flxapp_metadata() {
	$m = array(
		'issuer'                                => flxapp_issuer(),
		'authorization_endpoint'                => flxapp_authorize_url(),
		'token_endpoint'                        => rest_url( 'flxapp/v1/oauth/token' ),
		'revocation_endpoint'                   => rest_url( 'flxapp/v1/oauth/revoke' ),
		'response_types_supported'              => array( 'code' ),
		'response_modes_supported'              => array( 'query' ),
		'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
		'code_challenge_methods_supported'      => array( 'S256' ),
		'token_endpoint_auth_methods_supported' => array( 'none', 'client_secret_post', 'client_secret_basic' ),
		'revocation_endpoint_auth_methods_supported' => array( 'none', 'client_secret_post', 'client_secret_basic' ),
		'scopes_supported'                      => FLXAPP_SCOPES,
		'authorization_response_iss_parameter_supported' => true,
		'service_documentation'                 => admin_url( 'users.php?page=flx-app' ),
	);
	if ( flxapp_opt( 'dynamic_clients' ) ) {
		$m['registration_endpoint'] = rest_url( 'flxapp/v1/oauth/register' );
	}
	return (array) apply_filters( 'flxapp_oauth_metadata', $m );
}

function flxapp_json_exit( $data, $status = 200 ) {
	flxapp_nocache_headers();
	status_header( $status );
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Access-Control-Allow-Origin: *' );
	echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
}

/* ---------------------------------------------------------------------------
 * .well-known
 * ------------------------------------------------------------------------- */

add_action(
	'init',
	function () {
		$uri  = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$base = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );
		if ( '' === $uri || 0 !== strpos( $uri, $base . '/.well-known/oauth-' ) ) {
			return;
		}
		$rest = substr( $uri, strlen( $base . '/.well-known/' ) );
		if ( 'OPTIONS' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Access-Control-Allow-Headers: Authorization, Content-Type, MCP-Protocol-Version' );
			exit;
		}
		if ( 0 === strpos( $rest, 'oauth-authorization-server' ) ) {
			flxapp_json_exit( flxapp_metadata() );
		}
		if ( 0 === strpos( $rest, 'oauth-protected-resource' ) ) {
			$path     = (string) substr( $rest, strlen( 'oauth-protected-resource' ) );
			$resource = '' !== $path && '/' !== $path ? flxapp_issuer() . '/' . ltrim( $path, '/' ) : rest_url( 'flx-mcp/v1/mcp' );
			flxapp_json_exit(
				array(
					'resource'                 => esc_url_raw( $resource ),
					'authorization_servers'    => array( flxapp_issuer() ),
					'bearer_methods_supported' => array( 'header' ),
					'scopes_supported'         => FLXAPP_SCOPES,
					'resource_name'            => get_bloginfo( 'name' ),
				)
			);
		}
	},
	1
);

/* MCP (Flux AI & MCP) ve kendi uçlarımız 401 dönünce istemciye metadata adresini göster (MCP yetkilendirme şartı). */
add_filter(
	'rest_post_dispatch',
	function ( $res, $server, $req ) {
		if ( $res instanceof WP_HTTP_Response && 401 === $res->get_status() ) {
			$route = (string) $req->get_route();
			if ( 0 === strpos( $route, '/flx-mcp/' ) || 0 === strpos( $route, '/flxapp/' ) || 0 === strpos( $route, '/flx-studio/' ) ) {
				$meta = flxapp_issuer() . '/.well-known/oauth-protected-resource/' . ltrim( (string) wp_parse_url( rest_url( ltrim( $route, '/' ) ), PHP_URL_PATH ), '/' );
				$res->header( 'WWW-Authenticate', 'Bearer resource_metadata="' . esc_url_raw( $meta ) . '"' );
			}
		}
		return $res;
	},
	10,
	3
);

/* Kapsamı olan belirteç yalnız o kapsamın rotasına girer (RFC 6750). Kapsamsız kayıt tam erişimde kalır. */
add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $req ) {
		if ( null !== $result || ! $req instanceof WP_REST_Request || 'OPTIONS' === $req->get_method() ) {
			return $result;
		}
		$row = flxapp_current_token();
		if ( ! is_array( $row ) ) {
			return $result;
		}
		$have = flxapp_token_scopes( $row );
		if ( null === $have ) {
			return $result;
		}
		$need = flxapp_route_scope( (string) $req->get_route() );
		if ( '' === $need || in_array( $need, $have, true ) ) {
			return $result;
		}
		return new WP_Error(
			'insufficient_scope',
			__( 'The token does not include the scope this request needs.', 'flx-app' ),
			array(
				'status' => 403,
				'scope'  => $need,
			)
		);
	},
	10,
	3
);

add_filter(
	'rest_post_dispatch',
	function ( $res, $server, $req ) {
		if ( ! $res instanceof WP_HTTP_Response || 403 !== $res->get_status() ) {
			return $res;
		}
		$data = $res->get_data();
		if ( ! is_array( $data ) || 'insufficient_scope' !== ( $data['code'] ?? '' ) ) {
			return $res;
		}
		$need = preg_replace( '/[^a-z_]/', '', (string) ( $data['data']['scope'] ?? '' ) );
		$res->header( 'WWW-Authenticate', 'Bearer error="insufficient_scope", error_description="The token scope does not cover this request.", scope="' . $need . '"' );
		return $res;
	},
	11,
	3
);

/* Belirteç ucuna gelen Basic başlığı (client_secret_basic) WordPress uygulama parolası sanılmasın. */
add_filter(
	'application_password_is_api_request',
	function ( $is ) {
		$uri = (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return false !== strpos( $uri, 'flxapp/v1/oauth' ) ? false : $is;
	}
);

/* ---------------------------------------------------------------------------
 * Yetkilendirme ekranı
 * ------------------------------------------------------------------------- */

function flxapp_clean_scope( $scope ) {
	$out = array();
	foreach ( preg_split( '/\s+/', trim( (string) $scope ) ) as $s ) {
		if ( in_array( $s, FLXAPP_SCOPES, true ) && ! in_array( $s, $out, true ) ) {
			$out[] = $s;
		}
	}
	return $out ? implode( ' ', $out ) : 'flx';
}

/** Rotanın istediği kapsam: flx, mcp, ya da '' (belirteç gerekmez). */
function flxapp_route_scope( $route ) {
	$route = (string) $route;
	if ( 0 === strpos( $route, '/flxapp/v1/oauth/' ) || 0 === strpos( $route, '/flxapp/v1/otp/' ) || '/flxapp/v1/app/bootstrap' === $route ) {
		return '';
	}
	if ( 0 === strpos( $route, '/flx-mcp/' ) ) {
		return 'mcp';
	}
	return 'flx';
}

/** Kayıtlı kapsamlar. null: kapsam boş, tam erişim. */
function flxapp_token_scopes( array $row ) {
	$scope = trim( (string) ( $row['scope'] ?? '' ) );
	if ( '' === $scope ) {
		return null;
	}
	return preg_split( '/\s+/', $scope );
}

function flxapp_authorize_error_page( $message ) {
	login_header( __( 'App connection', 'flx-app' ) );
	echo '<div id="login_error" class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
	echo '<p id="backtoblog"><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( '← Back to site', 'flx-app' ) . '</a></p>';
	login_footer();
	exit;
}

/** Onay sayfasındaki marka logosu: site simgesi (yoksa eklenti simgesi). Filtre: flxapp_brand_logo. */
function flxapp_brand_logo() {
	$icon = get_site_icon_url( 192 );
	return (string) apply_filters( 'flxapp_brand_logo', $icon ? $icon : FLXAPP_URL . 'assets/icon.svg' );
}

/* Onay sayfası: tek kart; WP başlık logosu, alt bağlantılar ve Google One Tap gizli. */
add_action(
	'login_enqueue_scripts',
	function () {
		if ( 'flxapp_authorize' !== ( $_REQUEST['action'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		wp_enqueue_style( 'flxapp-consent', FLXAPP_URL . 'assets/consent.css', array( 'login' ), FLXAPP_VERSION );
	},
	20
);

function flxapp_redirect_with( $uri, array $params ) {
	$params['iss'] = flxapp_issuer();
	$sep           = false === strpos( $uri, '?' ) ? '?' : '&';
	$target        = $uri . $sep . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	flxapp_nocache_headers();
	// Uygulama şemasına (flxmasaustu://) da gidebilmeli; wp_safe_redirect yalnız aynı alan adına izin verir.
	header( 'Location: ' . $target, true, 302 );
	exit;
}

add_action(
	'login_form_flxapp_authorize',
	function () {
		$src = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ? $_POST : $_GET; // phpcs:ignore WordPress.Security.NonceVerification
		$p   = array();
		foreach ( array( 'response_type', 'client_id', 'redirect_uri', 'state', 'scope', 'code_challenge', 'code_challenge_method', 'resource', 'app_id', 'prompt' ) as $k ) {
			$p[ $k ] = isset( $src[ $k ] ) ? trim( (string) wp_unslash( $src[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		$client = flxapp_client( $p['client_id'] );
		if ( ! $client ) {
			flxapp_authorize_error_page( __( 'Unknown application (client_id).', 'flx-app' ) );
		}
		if ( ! flxapp_redirect_ok( $client, $p['redirect_uri'] ) ) {
			flxapp_authorize_error_page( __( 'This redirect URI is not registered for the application.', 'flx-app' ) );
		}
		// Buradan sonra hatalar istemciye yönlendirilir.
		$back = array( 'state' => $p['state'] );
		if ( 'code' !== $p['response_type'] ) {
			flxapp_redirect_with( $p['redirect_uri'], $back + array( 'error' => 'unsupported_response_type' ) );
		}
		if ( 'S256' !== $p['code_challenge_method'] || ! preg_match( '/^[A-Za-z0-9_\-]{43,128}$/', $p['code_challenge'] ) ) {
			flxapp_redirect_with( $p['redirect_uri'], $back + array( 'error' => 'invalid_request', 'error_description' => 'PKCE (S256) required' ) );
		}
		if ( ! flxapp_client_usable( $client ) ) {
			flxapp_redirect_with( $p['redirect_uri'], $back + array( 'error' => 'unauthorized_client', 'error_description' => 'app_not_allowed' ) );
		}
		$app_id = ! empty( $client['native'] ) ? flxapp_app_id_for( $client, $p['app_id'] ) : '';
		// prompt=login: aynı siteye ikinci hesap. Açık oturum kapatılır, giriş ekranı istenir (bir kez).
		$prompt = $p['prompt'];
		unset( $p['prompt'] );
		if ( 'login' === $prompt && is_user_logged_in() && 'GET' === ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
			wp_logout();
		}
		if ( ! is_user_logged_in() ) {
			$here = add_query_arg( array_map( 'rawurlencode', array_filter( $p ) ), flxapp_authorize_url() );
			wp_safe_redirect( wp_login_url( $here, 'login' === $prompt ) );
			exit;
		}
		$user  = wp_get_current_user();
		$scope = flxapp_clean_scope( $p['scope'] );

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			if ( ! isset( $_POST['_flxapp'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_flxapp'] ) ), 'flxapp_authorize_' . $p['client_id'] ) ) {
				flxapp_authorize_error_page( __( 'Your session expired. Try again from the app.', 'flx-app' ) );
			}
			if ( empty( $_POST['allow'] ) ) {
				flxapp_redirect_with( $p['redirect_uri'], $back + array( 'error' => 'access_denied' ) );
			}
			$code = flxapp_random( 'flxcd_' );
			set_transient(
				'flxapp_code_' . flxapp_hash( $code ),
				array(
					'client'    => $client['id'],
					'user'      => $user->ID,
					'redirect'  => $p['redirect_uri'],
					'challenge' => $p['code_challenge'],
					'scope'     => $scope,
					'app'       => $app_id,
				),
				10 * MINUTE_IN_SECONDS
			);
			flxapp_redirect_with( $p['redirect_uri'], $back + array( 'code' => $code ) );
		}

		flxapp_nocache_headers();
		add_filter( 'login_display_language_dropdown', '__return_false' );
		login_header( __( 'App connection', 'flx-app' ) );
		$switch  = wp_logout_url( add_query_arg( array_map( 'rawurlencode', array_filter( $p ) ), flxapp_authorize_url() ) );
		$host    = 'https' === wp_parse_url( $p['redirect_uri'], PHP_URL_SCHEME ) ? wp_parse_url( $p['redirect_uri'], PHP_URL_HOST ) : '';
		$privacy = get_privacy_policy_url();
		$brand   = get_bloginfo( 'name' );
		?>
		<div class="flxapp-consent">
			<div class="flxapp-brand">
				<img src="<?php echo esc_url( ! empty( $client['icon'] ) ? $client['icon'] : flxapp_brand_logo() ); ?>" alt="" width="56" height="56">
				<span><?php echo esc_html( $brand ); ?></span>
			</div>
			<h1 class="flxapp-title">
				<?php
				printf(
					/* translators: %s: app name */
					esc_html__( '%s wants to access your account', 'flx-app' ),
					'<strong>' . esc_html( $client['name'] ) . '</strong>'
				);
				?>
			</h1>
			<div class="flxapp-account">
				<?php echo get_avatar( $user->ID, 40, '', '', array( 'class' => 'flxapp-avatar' ) ); ?>
				<div class="flxapp-who">
					<strong><?php echo esc_html( $user->display_name ); ?></strong>
					<span><?php echo esc_html( $user->user_email ); ?></span>
				</div>
				<a class="flxapp-switch" href="<?php echo esc_url( $switch ); ?>"><?php esc_html_e( 'Change', 'flx-app' ); ?></a>
			</div>
			<ul class="flxapp-points">
				<li><?php esc_html_e( 'The app works with your permissions: the menus and actions your role can see.', 'flx-app' ); ?></li>
				<li><?php esc_html_e( 'You can revoke access at any time under Users › App access.', 'flx-app' ); ?></li>
				<?php if ( $host && ! flxapp_is_loopback( $host ) ) : ?>
					<li>
						<?php
						/* translators: %s: host name */
						printf( esc_html__( 'After approval you will return to %s.', 'flx-app' ), '<code>' . esc_html( $host ) . '</code>' );
						?>
					</li>
				<?php endif; ?>
			</ul>
			<form method="post" action="<?php echo esc_url( flxapp_authorize_url() ); ?>" class="flxapp-actions">
				<?php foreach ( $p as $k => $v ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $v ); ?>">
				<?php endforeach; ?>
				<?php wp_nonce_field( 'flxapp_authorize_' . $p['client_id'], '_flxapp' ); ?>
				<button type="submit" name="deny" value="1" class="flxapp-btn"><?php esc_html_e( 'Deny', 'flx-app' ); ?></button>
				<button type="submit" name="allow" value="1" class="flxapp-btn is-primary"><?php esc_html_e( 'Allow', 'flx-app' ); ?></button>
			</form>
			<div class="flxapp-links">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to site', 'flx-app' ); ?></a>
				<?php if ( $privacy ) : ?>
					<span aria-hidden="true">·</span>
					<a href="<?php echo esc_url( $privacy ); ?>"><?php echo esc_html( get_the_title( (int) get_option( 'wp_page_for_privacy_policy' ) ) ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php
		login_footer();
		exit;
	}
);

/* ---------------------------------------------------------------------------
 * Panel oturumu (uygulamadaki web görünümü için tek seferlik giriş)
 * ------------------------------------------------------------------------- */

/** Göreli wp-admin yolu mu? (dış adres, şema, ../ yok) */
function flxapp_clean_admin_path( $path ) {
	$path = ltrim( trim( (string) $path ), '/' );
	if ( 0 === strpos( $path, 'wp-admin/' ) ) {
		$path = substr( $path, 9 );
	}
	if ( '' === $path ) {
		return 'index.php';
	}
	if ( preg_match( '#(^|/)\.\.|//|\\\\|^[a-z][a-z0-9+.\-]*:#i', $path ) || ! preg_match( '#^[A-Za-z0-9_\-./]+\.php(\?.*)?$#', $path ) ) {
		return '';
	}
	return $path;
}

/** Sitenin kendi ön yüz adresi mi (aynı host, http/https). Göreli yol ana adrese eklenir. */
function flxapp_clean_front_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}
	if ( '/' === $url[0] && ( ! isset( $url[1] ) || '/' !== $url[1] ) ) {
		$url = home_url( $url );
	}
	$u = wp_parse_url( $url );
	$h = wp_parse_url( home_url() );
	if ( ! $u || ! in_array( $u['scheme'] ?? '', array( 'http', 'https' ), true ) || strtolower( $u['host'] ?? '' ) !== strtolower( $h['host'] ?? '' ) || ( $u['port'] ?? null ) !== ( $h['port'] ?? null ) ) {
		return '';
	}
	return esc_url_raw( $url );
}

/**
 * Tek seferlik oturum devri adresi (60 sn). Hedef wp-admin yolu ya da sitenin ön yüz adresi.
 *
 * @param int    $user_id
 * @param string $path  wp-admin göreli yolu (ör. edit.php?post_type=page) ya da boş
 * @param array  $opts  url (ön yüz adresi, path yerine), embed (bool, varsayılan true: uygulama görünümü)
 */
function flxapp_sso_url( $user_id, $path, $opts = array() ) {
	$front = flxapp_clean_front_url( $opts['url'] ?? '' );
	$path  = '' === $front ? flxapp_clean_admin_path( $path ) : '';
	if ( '' === $path && '' === $front ) {
		return '';
	}
	$t = flxapp_random( 'flxss_' );
	set_transient(
		'flxapp_sso_' . flxapp_hash( $t ),
		array(
			'user'  => (int) $user_id,
			'path'  => $path,
			'url'   => $front,
			'embed' => ! isset( $opts['embed'] ) || (bool) $opts['embed'],
			'grant' => (string) ( $opts['grant'] ?? '' ),
		),
		MINUTE_IN_SECONDS
	);
	return add_query_arg( array( 'action' => 'flxapp_sso', 't' => $t ), site_url( 'wp-login.php', 'login' ) );
}

/* `flxapp_session` eşdeğer ad (runtime `classifyNavigation` bu adı tanır). */
add_action( 'login_form_flxapp_session', 'flxapp_sso_handle' );
add_action( 'login_form_flxapp_sso', 'flxapp_sso_handle' );

function flxapp_sso_handle() {
	$t   = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) wp_unslash( $_GET['t'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$key = 'flxapp_sso_' . flxapp_hash( $t );
	$d   = '' !== $t ? get_transient( $key ) : false;
	delete_transient( $key );
	if ( ! $d || ! flxapp_opt( 'web_session' ) || ! get_userdata( (int) $d['user'] ) ) {
		flxapp_authorize_error_page( __( 'This link has expired. Open it again from the app.', 'flx-app' ) );
	}
	wp_set_current_user( (int) $d['user'] );
	$grant = (string) ( $d['grant'] ?? '' );
	$tag   = function ( $info ) use ( $grant ) {
		$info['flxapp'] = 1;
		if ( '' !== $grant ) {
			$info['flxapp_grant'] = $grant;
		}
		return $info;
	};
	add_filter( 'attach_session_information', $tag );
	wp_set_auth_cookie( (int) $d['user'], true, is_ssl() );
	remove_filter( 'attach_session_information', $tag );
	if ( ! empty( $d['embed'] ) ) {
		flxapp_set_embed_cookie( true );
	}
	$target = ! empty( $d['url'] ) ? $d['url'] : admin_url( $d['path'] );
	flxapp_nocache_headers();
	wp_safe_redirect( $target );
	exit;
}

/**
 * Uygulamanın açtığı WordPress çerez oturumlarını bitirir (webview). $grant boşsa kullanıcının tüm uygulama oturumları.
 *
 * @return int bitirilen oturum sayısı
 */
function flxapp_end_web_sessions( $user_id, $grant = '' ) {
	$all = get_user_meta( (int) $user_id, 'session_tokens', true );
	if ( ! is_array( $all ) ) {
		return 0;
	}
	$n = 0;
	foreach ( $all as $verifier => $sess ) {
		if ( empty( $sess['flxapp'] ) || ( '' !== (string) $grant && (string) ( $sess['flxapp_grant'] ?? '' ) !== (string) $grant ) ) {
			continue;
		}
		unset( $all[ $verifier ] );
		++$n;
	}
	if ( $n ) {
		update_user_meta( (int) $user_id, 'session_tokens', $all );
	}
	return $n;
}

/* Grant kapanınca (çıkış, cihaz iptali) o grant'ın webview oturumları da biter. */
add_action(
	'flxapp_grant_revoked',
	function ( $grant_id ) {
		global $wpdb;
		$uid = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ' . flxapp_table() . ' WHERE grant_id = %s LIMIT 1', (string) $grant_id ) ); // phpcs:ignore WordPress.DB
		if ( $uid ) {
			flxapp_end_web_sessions( $uid, (string) $grant_id );
		}
	}
);

/* Flux Speed özel giriş adresi (/fluxadmin) açıkken wp-login.php'deki uygulama işlemleri engellenmesin. */
add_filter(
	'flxp_login_passthrough_actions',
	function ( $allow ) {
		return array_values( array_unique( array_merge( (array) $allow, array( 'flxapp_authorize', 'flxapp_sso', 'flxapp_session' ) ) ) );
	}
);

/* ---------------------------------------------------------------------------
 * REST: token, revoke, register
 * ------------------------------------------------------------------------- */

function flxapp_oauth_error( $error, $desc = '', $status = 400 ) {
	$r = new WP_REST_Response( array_filter( array( 'error' => $error, 'error_description' => $desc ) ), $status );
	$r->header( 'Cache-Control', 'no-store' );
	$r->header( 'Pragma', 'no-cache' );
	return $r;
}

/** client_id + secret: gövde ya da Basic başlık. */
function flxapp_request_client( WP_REST_Request $req ) {
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
	$client = flxapp_client( $id );
	if ( ! $client || ! flxapp_client_auth_ok( $client, $secret ) ) {
		return null;
	}
	return $client;
}

function flxapp_token_pair( $client_id, $user_id, $scope, $grant_id, $app_id = '' ) {
	$access_ttl  = max( 5, (int) flxapp_opt( 'access_minutes' ) ) * MINUTE_IN_SECONDS;
	$refresh_ttl = max( 1, (int) flxapp_opt( 'refresh_days' ) ) * DAY_IN_SECONDS;
	$client      = flxapp_client( $client_id );
	$base        = array(
		'user_id'   => $user_id,
		'client_id' => $client_id,
		'grant_id'  => $grant_id,
		'app_id'    => (string) $app_id,
		'name'      => $client ? $client['name'] : $client_id,
		'scope'     => $scope,
	);
	$access  = flxapp_issue( 'access', $base + array( 'ttl' => $access_ttl ) );
	$refresh = flxapp_issue( 'refresh', $base + array( 'ttl' => $refresh_ttl ) );
	$r       = new WP_REST_Response(
		array(
			'access_token'  => $access,
			'token_type'    => 'Bearer',
			'expires_in'    => $access_ttl,
			'refresh_token' => $refresh,
			'scope'         => $scope,
		),
		200
	);
	$r->header( 'Cache-Control', 'no-store' );
	$r->header( 'Pragma', 'no-cache' );
	return $r;
}

function flxapp_rest_token( WP_REST_Request $req ) {
	$client = flxapp_request_client( $req );
	if ( ! $client ) {
		return flxapp_oauth_error( 'invalid_client', '', 401 );
	}
	$grant = (string) $req->get_param( 'grant_type' );
	if ( ! flxapp_client_usable( $client ) ) {
		return flxapp_oauth_error( 'unauthorized_client', 'app_not_allowed', 400 );
	}

	if ( 'authorization_code' === $grant ) {
		$code = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $req->get_param( 'code' ) );
		$key  = 'flxapp_code_' . flxapp_hash( $code );
		$d    = '' !== $code ? get_transient( $key ) : false;
		delete_transient( $key ); // tek kullanım
		if ( ! $d || $d['client'] !== $client['id'] ) {
			return flxapp_oauth_error( 'invalid_grant', 'code' );
		}
		if ( (string) $req->get_param( 'redirect_uri' ) !== $d['redirect'] ) {
			return flxapp_oauth_error( 'invalid_grant', 'redirect_uri' );
		}
		$verifier = (string) $req->get_param( 'code_verifier' );
		$calc     = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		if ( ! preg_match( '/^[A-Za-z0-9\-._~]{43,128}$/', $verifier ) || ! hash_equals( $d['challenge'], $calc ) ) {
			return flxapp_oauth_error( 'invalid_grant', 'code_verifier' );
		}
		if ( ! get_userdata( (int) $d['user'] ) ) {
			return flxapp_oauth_error( 'invalid_grant', 'user' );
		}
		return flxapp_token_pair( $client['id'], (int) $d['user'], $d['scope'], strtolower( wp_generate_password( 24, false ) ), (string) ( $d['app'] ?? '' ) );
	}

	if ( 'refresh_token' === $grant ) {
		$row = flxapp_find( (string) $req->get_param( 'refresh_token' ) );
		if ( ! $row || 'refresh' !== $row['kind'] || $row['client_id'] !== $client['id'] ) {
			return flxapp_oauth_error( 'invalid_grant' );
		}
		if ( (int) $row['revoked'] ) {
			// Kullanılmış yenileme belirteci tekrar geldi: çalınmış olabilir, tüm oturumu kapat.
			flxapp_revoke_grant( $row['grant_id'] );
			return flxapp_oauth_error( 'invalid_grant' );
		}
		if ( ! flxapp_is_live( $row ) || ! get_userdata( (int) $row['user_id'] ) ) {
			return flxapp_oauth_error( 'invalid_grant' );
		}
		if ( ! flxapp_client_usable( $client ) ) {
			// Site, genel uygulamayı sonradan kapattı.
			flxapp_revoke_grant( $row['grant_id'] );
			return flxapp_oauth_error( 'unauthorized_client', 'app_not_allowed' );
		}
		flxapp_revoke_id( $row['id'] );
		return flxapp_token_pair( $client['id'], (int) $row['user_id'], $row['scope'], $row['grant_id'], (string) $row['app_id'] );
	}

	return flxapp_oauth_error( 'unsupported_grant_type' );
}

function flxapp_rest_revoke( WP_REST_Request $req ) {
	$client = flxapp_request_client( $req );
	$row    = flxapp_find( (string) $req->get_param( 'token' ) );
	if ( $client && $row && $row['client_id'] === $client['id'] ) {
		if ( 'refresh' === $row['kind'] ) {
			flxapp_revoke_grant( $row['grant_id'] );
		} else {
			flxapp_revoke_id( $row['id'] );
		}
	}
	return new WP_REST_Response( null, 200 );
}

function flxapp_rest_register( WP_REST_Request $req ) {
	if ( ! flxapp_opt( 'dynamic_clients' ) ) {
		return flxapp_oauth_error( 'access_denied', 'Dynamic client registration is disabled.', 403 );
	}
	if ( ! flxapp_rate( 'register', 10, HOUR_IN_SECONDS ) ) {
		return flxapp_oauth_error( 'temporarily_unavailable', '', 429 );
	}
	$body   = (array) $req->get_json_params();
	$method = (string) ( $body['token_endpoint_auth_method'] ?? 'none' );
	$res    = flxapp_add_client( (string) ( $body['client_name'] ?? 'MCP' ), (array) ( $body['redirect_uris'] ?? array() ), 'none' !== $method, true );
	if ( is_wp_error( $res ) ) {
		return flxapp_oauth_error( $res->get_error_code(), $res->get_error_message() );
	}
	list( $client, $secret ) = $res;
	$out = array(
		'client_id'                  => $client['id'],
		'client_id_issued_at'        => $client['created'],
		'client_name'                => $client['name'],
		'redirect_uris'              => $client['redirect_uris'],
		'grant_types'                => array( 'authorization_code', 'refresh_token' ),
		'response_types'             => array( 'code' ),
		'token_endpoint_auth_method' => $secret ? 'client_secret_post' : 'none',
	);
	if ( $secret ) {
		$out['client_secret']            = $secret;
		$out['client_secret_expires_at'] = 0;
	}
	$r = new WP_REST_Response( $out, 201 );
	$r->header( 'Cache-Control', 'no-store' );
	return $r;
}

/** Açık uçlar: herkes çağırabilir ama oran sınırlı. */
function flxapp_public_permission() {
	return flxapp_rate( 'oauth', 60, MINUTE_IN_SECONDS ) ? true : new WP_Error( 'flxapp_rate', 'Too many requests', array( 'status' => 429 ) );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route( 'flxapp/v1', '/oauth/token', array( 'methods' => 'POST', 'callback' => 'flxapp_rest_token', 'permission_callback' => 'flxapp_public_permission' ) );
		register_rest_route( 'flxapp/v1', '/oauth/revoke', array( 'methods' => 'POST', 'callback' => 'flxapp_rest_revoke', 'permission_callback' => 'flxapp_public_permission' ) );
		register_rest_route( 'flxapp/v1', '/oauth/register', array( 'methods' => 'POST', 'callback' => 'flxapp_rest_register', 'permission_callback' => 'flxapp_public_permission' ) );
		register_rest_route(
			'flxapp/v1',
			'/oauth/metadata',
			array(
				'methods'             => 'GET',
				'callback'            => function () {
					return flxapp_metadata();
				},
				'permission_callback' => 'flxapp_public_permission',
			)
		);
	}
);
