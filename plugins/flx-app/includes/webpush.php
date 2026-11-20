<?php
/**
 * Web Push (tarayıcı, PWA, Android Chrome, ana ekrana eklenmiş iOS PWA). Expo push'un tarayıcı karşılığı:
 * `do_action( 'flxapp_push', … )` ile gelen her bildirim, kullanıcının bu sitede abone olduğu tarayıcılara da gider.
 *
 *   POST /flxapp/v1/webpush/subscribe    { endpoint, keys: { p256dh, auth } } → { ok }   (oturum açık tarayıcı)
 *   POST /flxapp/v1/webpush/unsubscribe  { endpoint } → { ok }
 *   GET  /?flxapp-sw=1                   service worker (kapsam `/`)
 *
 * Ek kütüphane yok: VAPID (RFC 8292, ES256) ve aes128gcm (RFC 8291) OpenSSL ile. Abonelik, açıldığı WordPress
 * oturumuna bağlıdır: çıkış ya da oturum süresi bitince o tarayıcıya bildirim gitmez (cihazların OAuth oturumu
 * kuralıyla aynı). Uç adresi ve anahtarlar şifreli saklanır. Push servisi adresi izinli listede olmalı
 * (`flxapp_webpush_hosts` filtresi), başka adrese istek atılmaz.
 */

defined( 'ABSPATH' ) || exit;

const FLXAPP_WEBPUSH_META = 'flxapp_webpush';
const FLXAPP_WEBPUSH_MAX  = 10; // Kullanıcı başına tarayıcı.

function flxapp_webpush_b64( string $s ): string {
	return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

function flxapp_webpush_unb64( string $s ): string {
	$r = base64_decode( strtr( $s, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	return false === $r ? '' : $r;
}

/** Bu sunucu Web Push gönderebilir mi (OpenSSL + P-256 + ECDH). */
function flxapp_webpush_supported(): bool {
	return function_exists( 'openssl_pkey_derive' ) && function_exists( 'hash_hkdf' ) && in_array( 'prime256v1', (array) openssl_get_curve_names(), true );
}

/** 65 baytlık sıkıştırılmamış P-256 açık anahtarı (0x04 || X || Y). */
function flxapp_webpush_point( $key ): string {
	$d = openssl_pkey_get_details( $key );
	if ( empty( $d['ec']['x'] ) || empty( $d['ec']['y'] ) ) {
		return '';
	}
	return "\x04" . str_pad( $d['ec']['x'], 32, "\0", STR_PAD_LEFT ) . str_pad( $d['ec']['y'], 32, "\0", STR_PAD_LEFT );
}

/** Ham noktayı OpenSSL'in okuyacağı PEM'e çevirir (SubjectPublicKeyInfo, P-256). */
function flxapp_webpush_point_pem( string $point ): string {
	$der = hex2bin( '3059301306072a8648ce3d020106082a8648ce3d030107034200' ) . $point;
	return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END PUBLIC KEY-----\n"; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/** Sitenin VAPID anahtar çifti; ilk kullanımda üretilir. @return array{0: string, 1: string}|null [ private PEM, public b64url ] */
function flxapp_webpush_vapid(): ?array {
	if ( ! flxapp_webpush_supported() ) {
		return null;
	}
	$pem = flxapp_secret_get( 'webpush_vapid' );
	$key = '' !== $pem ? openssl_pkey_get_private( $pem ) : false;
	if ( ! $key ) {
		$key = openssl_pkey_new( array( 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1' ) );
		if ( ! $key || ! openssl_pkey_export( $key, $pem ) ) {
			return null;
		}
		flxapp_secret_put( 'webpush_vapid', $pem );
		if ( flxapp_secret_get( 'webpush_vapid' ) !== $pem ) {
			return null; // Şifreleme yok: anahtar saklanamadı, her istekte değişmesin.
		}
	}
	$pub = flxapp_webpush_point( $key );
	return '' === $pub ? null : array( $pem, flxapp_webpush_b64( $pub ) );
}

/** İstemcinin `applicationServerKey` değeri; Web Push kullanılamıyorsa ''. */
function flxapp_webpush_public_key(): string {
	$v = flxapp_webpush_vapid();
	return $v ? $v[1] : '';
}

/** İzinli push servisleri (son ek eşleşmesi). */
function flxapp_webpush_host_ok( string $endpoint ): bool {
	$p = wp_parse_url( $endpoint );
	if ( 'https' !== ( $p['scheme'] ?? '' ) || empty( $p['host'] ) || isset( $p['port'] ) || isset( $p['user'] ) ) {
		return false;
	}
	$host  = strtolower( $p['host'] );
	$hosts = (array) apply_filters(
		'flxapp_webpush_hosts',
		array( 'fcm.googleapis.com', 'android.googleapis.com', 'updates.push.services.mozilla.com', 'push.services.mozilla.com', 'push.apple.com', 'notify.windows.com' )
	);
	foreach ( $hosts as $h ) {
		$h = strtolower( (string) $h );
		if ( $host === $h || ( strlen( $host ) > strlen( $h ) && substr( $host, -strlen( $h ) - 1 ) === '.' . $h ) ) {
			return true;
		}
	}
	return false;
}

/** ES256 imzası: OpenSSL DER → JWS ham r||s. */
function flxapp_webpush_der_to_raw( string $der ): string {
	$pos = 3 + ( ord( $der[1] ) & 0x80 ? 1 : 0 );
	$out = '';
	for ( $i = 0; $i < 2; $i++ ) {
		$len  = ord( $der[ $pos ] );
		$int  = substr( $der, $pos + 1, $len );
		$out .= str_pad( ltrim( $int, "\0" ), 32, "\0", STR_PAD_LEFT );
		$pos += 1 + $len + 1;
	}
	return $out;
}

function flxapp_webpush_jwt( string $endpoint, string $pem ): string {
	$p      = wp_parse_url( $endpoint );
	$admin  = (string) get_option( 'admin_email' );
	$claims = array(
		'aud' => 'https://' . $p['host'],
		'exp' => time() + 12 * HOUR_IN_SECONDS,
		'sub' => is_email( $admin ) ? 'mailto:' . $admin : home_url( '/' ),
	);
	$input  = flxapp_webpush_b64( '{"typ":"JWT","alg":"ES256"}' ) . '.' . flxapp_webpush_b64( (string) wp_json_encode( $claims ) );
	$sig    = '';
	if ( ! openssl_sign( $input, $sig, $pem, OPENSSL_ALGO_SHA256 ) ) {
		return '';
	}
	return $input . '.' . flxapp_webpush_b64( flxapp_webpush_der_to_raw( $sig ) );
}

/**
 * RFC 8291 aes128gcm: tek kayıtlık şifreli gövde.
 *
 * @param string $payload   açık metin (JSON)
 * @param string $ua_public 65 bayt (p256dh)
 * @param string $auth      16 bayt
 * @return string gövde ya da hata olursa ''
 */
function flxapp_webpush_encrypt( string $payload, string $ua_public, string $auth ): string {
	if ( 65 !== strlen( $ua_public ) || "\x04" !== $ua_public[0] || 16 !== strlen( $auth ) || strlen( $payload ) > 3800 ) {
		return '';
	}
	$peer = openssl_pkey_get_public( flxapp_webpush_point_pem( $ua_public ) );
	$mine = openssl_pkey_new( array( 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1' ) );
	if ( ! $peer || ! $mine ) {
		return '';
	}
	$shared = openssl_pkey_derive( $peer, $mine, 32 );
	$as_pub = flxapp_webpush_point( $mine );
	if ( false === $shared || '' === $as_pub ) {
		return '';
	}
	$ikm   = hash_hkdf( 'sha256', $shared, 32, "WebPush: info\0" . $ua_public . $as_pub, $auth );
	$salt  = random_bytes( 16 );
	$cek   = hash_hkdf( 'sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt );
	$nonce = hash_hkdf( 'sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt );
	$tag   = '';
	$ct    = openssl_encrypt( $payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16 );
	if ( false === $ct ) {
		return '';
	}
	return $salt . pack( 'N', 4096 ) . chr( 65 ) . $as_pub . $ct . $tag;
}

/** Kullanıcının abonelikleri (şifreli satırlar). @return array<string, array> hash => satır */
function flxapp_webpush_subs( int $user_id ): array {
	$list = get_user_meta( $user_id, FLXAPP_WEBPUSH_META, true );
	return is_array( $list ) ? $list : array();
}

/** WordPress oturumu hâlâ geçerli mi (varsayılan oturum deposu; başka depoda kontrol atlanır). */
function flxapp_webpush_session_ok( int $user_id, string $verifier ): bool {
	if ( '' === $verifier ) {
		return false;
	}
	if ( 'WP_User_Meta_Session_Tokens' !== apply_filters( 'session_token_manager', 'WP_User_Meta_Session_Tokens' ) ) {
		return true;
	}
	$sessions = get_user_meta( $user_id, 'session_tokens', true );
	return is_array( $sessions ) && isset( $sessions[ $verifier ]['expiration'] ) && (int) $sessions[ $verifier ]['expiration'] >= time();
}

function flxapp_webpush_subscribe( int $user_id, string $endpoint, string $p256dh, string $auth, string $session_token ) {
	$ua_pub = flxapp_webpush_unb64( $p256dh );
	$secret = flxapp_webpush_unb64( $auth );
	if ( strlen( $endpoint ) > 1000 || ! flxapp_webpush_host_ok( $endpoint ) ) {
		return new WP_Error( 'flxapp_webpush_endpoint', __( 'This push service is not allowed.', 'flx-app' ), array( 'status' => 400 ) );
	}
	if ( 65 !== strlen( $ua_pub ) || 16 !== strlen( $secret ) ) {
		return new WP_Error( 'flxapp_webpush_keys', __( 'Invalid subscription keys.', 'flx-app' ), array( 'status' => 400 ) );
	}
	if ( '' === $session_token ) {
		return new WP_Error( 'flxapp_webpush_session', __( 'Browser notifications need a signed-in browser session.', 'flx-app' ), array( 'status' => 400 ) );
	}
	$hash = flxapp_hash( $endpoint );
	// Aynı tarayıcı başka kullanıcıda kalmasın (tarayıcıda hesap değişti).
	foreach ( get_users( array( 'meta_key' => FLXAPP_WEBPUSH_META, 'fields' => 'ID', 'number' => 200 ) ) as $other ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( (int) $other !== $user_id && isset( flxapp_webpush_subs( (int) $other )[ $hash ] ) ) {
			flxapp_webpush_remove( (int) $other, $hash );
		}
	}
	$subs          = flxapp_webpush_subs( $user_id );
	$subs[ $hash ] = array(
		'endpoint' => flxapp_encrypt( $endpoint ),
		'keys'     => flxapp_encrypt( $p256dh . '|' . $auth ),
		'session'  => hash( 'sha256', $session_token ),
		'created'  => time(),
	);
	uasort( $subs, static fn( $a, $b ) => (int) $b['created'] <=> (int) $a['created'] );
	update_user_meta( $user_id, FLXAPP_WEBPUSH_META, array_slice( $subs, 0, FLXAPP_WEBPUSH_MAX, true ) );
	return true;
}

function flxapp_webpush_remove( int $user_id, string $hash ): void {
	$subs = flxapp_webpush_subs( $user_id );
	if ( isset( $subs[ $hash ] ) ) {
		unset( $subs[ $hash ] );
		$subs ? update_user_meta( $user_id, FLXAPP_WEBPUSH_META, $subs ) : delete_user_meta( $user_id, FLXAPP_WEBPUSH_META );
	}
}

/** Bildirimin tıklanınca açılacağı adres. */
function flxapp_webpush_url( array $msg ): string {
	$target = (string) ( $msg['target'] ?? '' );
	$url    = admin_url();
	if ( 0 === strpos( $target, 'web:' ) ) {
		$url = admin_url( ltrim( substr( $target, 4 ), '/' ) );
	} elseif ( 0 === strpos( $target, 'url:' ) ) {
		$url = wp_validate_redirect( substr( $target, 4 ), admin_url() );
	}
	return (string) apply_filters( 'flxapp_webpush_url', $url, $msg );
}

/**
 * flxapp_push_deliver içinden: bu kullanıcıların tarayıcılarına gönderir.
 *
 * @return array{sent: int, failed: int, skipped: int}
 */
function flxapp_webpush_deliver( array $user_ids, string $event, array $m, array $msg ): array {
	$out   = array( 'sent' => 0, 'failed' => 0, 'skipped' => 0 );
	$vapid = flxapp_webpush_vapid();
	if ( ! $vapid ) {
		return $out;
	}
	$payload = wp_json_encode(
		array(
			'title' => $m['title'],
			'body'  => $m['body'],
			'tag'   => '' !== $event ? $event : null,
			'url'   => flxapp_webpush_url( $msg ),
			'icon'  => (string) get_site_icon_url( 192 ),
			'data'  => $m['data'],
		)
	);
	$jwt = array();
	foreach ( $user_ids as $uid ) {
		$subs = flxapp_webpush_subs( (int) $uid );
		if ( ! $subs ) {
			continue;
		}
		if ( ! flxapp_push_wanted( (int) $uid, $event ) ) {
			$out['skipped'] += count( $subs );
			continue;
		}
		foreach ( $subs as $hash => $s ) {
			if ( ! flxapp_webpush_session_ok( (int) $uid, (string) ( $s['session'] ?? '' ) ) ) {
				flxapp_webpush_remove( (int) $uid, $hash ); // Oturum bitti: bu tarayıcı artık bildirim almaz.
				++$out['skipped'];
				continue;
			}
			$endpoint = flxapp_decrypt( $s['endpoint'] ?? '' );
			$keys     = explode( '|', flxapp_decrypt( $s['keys'] ?? '' ) );
			$body     = 2 === count( $keys ) ? flxapp_webpush_encrypt( (string) $payload, flxapp_webpush_unb64( $keys[0] ), flxapp_webpush_unb64( $keys[1] ) ) : '';
			if ( '' === $endpoint || '' === $body || ! flxapp_webpush_host_ok( $endpoint ) ) {
				++$out['failed'];
				continue;
			}
			$aud = wp_parse_url( $endpoint, PHP_URL_HOST );
			if ( ! isset( $jwt[ $aud ] ) ) {
				$jwt[ $aud ] = flxapp_webpush_jwt( $endpoint, $vapid[0] );
			}
			$http = wp_safe_remote_post(
				$endpoint,
				array(
					'timeout'     => 10,
					'redirection' => 0,
					'headers'     => array(
						'Content-Type'     => 'application/octet-stream',
						'Content-Encoding' => 'aes128gcm',
						'TTL'              => '86400',
						'Urgency'          => 'high',
						'Authorization'    => 'vapid t=' . $jwt[ $aud ] . ', k=' . $vapid[1],
					),
					'body'        => $body,
				)
			);
			$code = is_wp_error( $http ) ? 0 : (int) wp_remote_retrieve_response_code( $http );
			if ( $code >= 200 && $code < 300 ) {
				++$out['sent'];
				continue;
			}
			++$out['failed'];
			$GLOBALS['flxapp_push_error'] = is_wp_error( $http ) ? $http->get_error_message() : 'Web Push HTTP ' . $code;
			if ( 404 === $code || 410 === $code ) {
				flxapp_webpush_remove( (int) $uid, $hash ); // Abonelik tarayıcıda kalkmış.
			}
		}
	}
	return $out;
}

/** Service worker: push gelince bildirimi gösterir, tıklanınca adresi açar ya da açık sekmeyi öne getirir. */
function flxapp_webpush_sw(): string {
	return <<<'JS'
/* FluxWP App - Web Push service worker */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));
self.addEventListener('push', (e) => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch (_) { d = { title: e.data ? e.data.text() : '' }; }
  e.waitUntil(self.registration.showNotification(d.title || self.location.hostname, {
    body: d.body || '', icon: d.icon || undefined, tag: d.tag || undefined, renotify: !!d.tag,
    data: { url: d.url || '/', data: d.data || {} },
  }));
});
self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || '/';
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
    for (const c of list) {
      if (c.url === url && 'focus' in c) return c.focus();
    }
    return self.clients.openWindow(url);
  }));
});
JS;
}

add_action(
	'init',
	static function () {
		if ( ! isset( $_GET['flxapp-sw'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		nocache_headers();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: /' );
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
		header( 'X-Content-Type-Options: nosniff' );
		echo flxapp_webpush_sw(); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	},
	0
);

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'flxapp/v1',
			'/webpush/subscribe',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'flxapp_logged_in',
				'callback'            => static function ( WP_REST_Request $req ) {
					if ( ! flxapp_opt( 'push_enabled' ) || '' === flxapp_webpush_public_key() ) {
						return new WP_Error( 'flxapp_webpush_off', __( 'Notifications are turned off on this site.', 'flx-app' ), array( 'status' => 503 ) );
					}
					$keys = (array) $req->get_param( 'keys' );
					$res  = flxapp_webpush_subscribe( get_current_user_id(), (string) $req->get_param( 'endpoint' ), (string) ( $keys['p256dh'] ?? '' ), (string) ( $keys['auth'] ?? '' ), (string) wp_get_session_token() );
					return is_wp_error( $res ) ? $res : array( 'ok' => true );
				},
			)
		);
		register_rest_route(
			'flxapp/v1',
			'/webpush/unsubscribe',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'flxapp_logged_in',
				'callback'            => static function ( WP_REST_Request $req ) {
					flxapp_webpush_remove( get_current_user_id(), flxapp_hash( (string) $req->get_param( 'endpoint' ) ) );
					return array( 'ok' => true );
				},
			)
		);
	}
);

/** İstemciye: Web Push açık mı, açık anahtar ve service worker adresi. */
function flxapp_webpush_client_config(): ?array {
	if ( ! flxapp_opt( 'push_enabled' ) ) {
		return null;
	}
	$key = flxapp_webpush_public_key();
	return '' === $key ? null : array(
		'key'       => $key,
		'sw'        => add_query_arg( 'flxapp-sw', '1', home_url( '/' ) ),
		'subscribe' => rest_url( 'flxapp/v1/webpush/subscribe' ),
		'remove'    => rest_url( 'flxapp/v1/webpush/unsubscribe' ),
	);
}

/** Yönetim çubuğunda "Bildirimler": bu tarayıcıyı Web Push'a abone eder ya da çıkarır. Tarayıcı desteklemiyorsa görünmez. */
function flxapp_webpush_bar_visible(): bool {
	return is_user_logged_in() && is_admin_bar_showing() && null !== flxapp_webpush_client_config()
		&& (bool) apply_filters( 'flxapp_webpush_show', current_user_can( 'edit_posts' ) );
}

add_action(
	'admin_bar_menu',
	static function ( $bar ) {
		if ( ! flxapp_webpush_bar_visible() ) {
			return;
		}
		$bar->add_node(
			array(
				'id'     => 'flxapp-webpush',
				'parent' => 'top-secondary',
				'title'  => '<span class="ab-icon dashicons dashicons-bell" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'Notifications', 'flx-app' ) . '</span>',
				'href'   => '#',
				'meta'   => array( 'html' => '', 'class' => 'flxapp-wp' ),
			)
		);
	},
	80
);

/** Düğme JS ile açılır (desteklemeyen tarayıcıda gizli kalır). */
function flxapp_webpush_assets(): void {
	if ( ! flxapp_webpush_bar_visible() ) {
		return;
	}
	$cfg = flxapp_webpush_client_config();
	wp_enqueue_script( 'flxapp-webpush', FLXAPP_URL . 'assets/js/webpush.js', array(), FLXAPP_VERSION, true );
	wp_localize_script(
		'flxapp-webpush',
		'flxappWebPush',
		$cfg + array(
			'scope' => (string) ( wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ?: '/' ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'i18n'  => array(
				'off'         => __( 'Notifications', 'flx-app' ),
				'on'          => __( 'Notifications on', 'flx-app' ),
				'blocked'     => __( 'Notifications blocked', 'flx-app' ),
				'turnOn'      => __( 'Get notifications in this browser, also when the site is closed.', 'flx-app' ),
				'turnOff'     => __( 'Stop notifications in this browser.', 'flx-app' ),
				'blockedHelp' => __( 'Notifications are blocked for this site in the browser settings.', 'flx-app' ),
				'failed'      => __( 'Notifications could not be turned on', 'flx-app' ),
			),
		)
	);
	wp_add_inline_style( 'admin-bar', '#wp-admin-bar-flxapp-webpush[hidden]{display:none!important}#wp-admin-bar-flxapp-webpush .ab-icon:before{top:2px}#wp-admin-bar-flxapp-webpush.flxapp-wp-on .ab-icon:before{color:#72aee6}@media screen and (max-width:782px){#wp-admin-bar-flxapp-webpush{display:block}#wp-admin-bar-flxapp-webpush .ab-label{display:none}}' );
}
add_action( 'admin_enqueue_scripts', 'flxapp_webpush_assets' );
add_action( 'wp_enqueue_scripts', 'flxapp_webpush_assets' );

/* Başta gizli: tarayıcı desteklemiyorsa hiç görünmesin. */
add_action(
	'wp_after_admin_bar_render',
	static function () {
		if ( flxapp_webpush_bar_visible() ) {
			echo '<script>(function(n){if(n)n.hidden=true;})(document.getElementById("wp-admin-bar-flxapp-webpush"));</script>';
		}
	}
);

/* Sistem kontrolü: tarayıcı bildirimi ve canlı bağlantı. Dış HTTP yok (canlı bağlantı için önbellekteki durum). */
add_filter(
	'flx_system_checks',
	static function ( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		$src    = __( 'Flux App', 'flx-app' );
		if ( flxapp_opt( 'push_enabled' ) ) {
			$https = 0 === strpos( home_url(), 'https://' );
			$ok    = $https && flxapp_webpush_supported();
			$checks[] = array(
				'id'      => 'flxapp.webpush',
				'source'  => $src,
				'group'   => 'Config',
				'label'   => __( 'Browser notifications (Web Push)', 'flx-app' ),
				'status'  => $ok ? 'ok' : 'warning',
				'message' => ! $https
					? __( 'Browsers allow notifications only on https sites.', 'flx-app' )
					: ( $ok ? __( 'Ready. Users turn them on per browser with Notifications in the admin bar.', 'flx-app' ) : __( 'PHP OpenSSL cannot sign P-256 keys here; browsers do not get notifications. Mobile app push still works.', 'flx-app' ) ),
			);
		}
		if ( function_exists( 'flx_realtime_enabled' ) && function_exists( 'flx_realtime_public_url' ) && flx_realtime_enabled() ) {
			$h        = get_transient( 'flx_realtime_health' );
			$url      = flx_realtime_public_url();
			$ok       = is_array( $h ) && ! empty( $h['ok'] );
			$checks[] = array(
				'id'      => 'flxapp.realtime',
				'source'  => $src,
				'group'   => 'Config',
				'label'   => __( 'Live connection (realtime)', 'flx-app' ),
				'status'  => $ok && '' !== $url ? 'ok' : ( false === $h ? 'info' : 'warning' ),
				'message' => '' === $url
					? __( 'The realtime service runs but has no public address (FLX_REALTIME_URL); apps poll.', 'flx-app' )
					: ( $ok ? __( 'Panel and app get changes at once.', 'flx-app' ) : ( false === $h ? __( 'Not checked yet; shown after the next app sign-in.', 'flx-app' ) : __( 'The realtime service is not answering; apps poll until it is back.', 'flx-app' ) ) ),
			);
		}
		return $checks;
	},
	20
);
