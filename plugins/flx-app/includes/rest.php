<?php
/**
 * Uygulama REST uçları (flxapp/v1). Belirteçle (OAuth ya da API anahtarı) ya da çerez+nonce ile çağrılır.
 *
 *   GET  /me        kullanıcı, site, rol bazlı menü (+icon_url), menü düzeni (layout: pins, groups), alt bar kısayolları, yerel modüller, sayaçlar
 *   GET  /counts    yalnız sayaçlar (hafif yoklama)
 *   POST /session   { path } → tek seferlik panel oturumu adresi (uygulamadaki web görünümü)
 *   POST /session/end  bu oturumun açtığı webview çerez oturumlarını bitirir
 *   POST /logout    bu oturumu (grant) kapatır
 */

defined( 'ABSPATH' ) || exit;

function flxapp_logged_in() {
	return is_user_logged_in() ? true : new WP_Error( 'rest_forbidden', __( 'Giriş gerekli.', 'flx-app' ), array( 'status' => 401 ) );
}

function flxapp_counts() {
	$c = function_exists( 'flxa_counts' ) ? (array) flxa_counts() : array();
	return (array) apply_filters( 'flxapp_counts', $c );
}

function flxapp_rest_me() {
	$u     = wp_get_current_user();
	$tok   = flxapp_current_token();
	$icon  = get_site_icon_url( 256 );
	$roles = array();
	$menu  = flxapp_menu_with_icons( flxapp_user_menu( $u->ID ) );
	foreach ( (array) $u->roles as $r ) {
		$roles[] = array(
			'id'   => $r,
			'name' => translate_user_role( wp_roles()->role_names[ $r ] ?? $r ),
		);
	}
	return array(
		'user'    => array(
			'id'           => $u->ID,
			'login'        => $u->user_login,
			'name'         => $u->display_name,
			'email'        => $u->user_email,
			'avatar'       => get_avatar_url( $u->ID, array( 'size' => 128 ) ),
			'roles'        => $roles,
			'locale'       => get_user_locale( $u ),
			'is_admin'     => user_can( $u, 'manage_options' ),
			'profile_url'  => admin_url( 'profile.php' ),
		),
		'site'    => array(
			'name'      => get_bloginfo( 'name' ),
			'url'       => home_url( '/' ),
			'admin_url' => admin_url(),
			'rest_url'  => rest_url(),
			'icon'      => $icon ? $icon : '',
			'locale'    => get_locale(),
			'timezone'  => wp_timezone_string(),
		),
		'auth'    => $tok ? array(
			'kind'    => $tok['kind'],
			'client'  => $tok['client_id'],
			'scope'   => $tok['scope'],
			'expires' => (int) $tok['expires'],
		) : array( 'kind' => 'cookie' ),
		'menu'    => $menu,
		'modules' => flxapp_me_modules( $u ),
		'counts'  => flxapp_counts(),
		'bottom'      => flxapp_user_bottom( $u ),
		'web_session' => (bool) flxapp_opt( 'web_session' ),
		'caps'        => flxapp_me_caps( $u ),
		'versions'    => flxapp_me_versions(),
	) + flxapp_user_layout( $u, $menu );
}

/** Çekirdek yetki özeti (uygulama ekran/menü kararları için; yetki yine sunucuda denetlenir). */
function flxapp_me_caps( $u ) {
	$out = array();
	foreach ( (array) apply_filters( 'flxapp_me_caps', array( 'read', 'edit_posts', 'publish_posts', 'edit_pages', 'upload_files', 'moderate_comments', 'list_users', 'edit_users', 'manage_options', 'activate_plugins', 'manage_woocommerce', 'edit_shop_orders', 'edit_products' ) ) as $cap ) {
		$out[ $cap ] = user_can( $u, $cap );
	}
	return $out;
}

/** Modüllere `manifest: bool` (manifesti olan modül uygulamada manifestle açılır). */
function flxapp_me_modules( $u ) {
	$mods = flxapp_user_modules( $u->ID );
	$man  = function_exists( 'flxapp_user_manifest' ) ? flxapp_user_manifest( $u ) : array();
	foreach ( $mods as $id => $m ) {
		$mods[ $id ]['manifest'] = isset( $man[ $id ] );
	}
	return (object) $mods;
}

function flxapp_me_versions() {
	$v = array( 'flx-app' => FLXAPP_VERSION );
	if ( function_exists( 'flxapp_active_packages' ) ) {
		foreach ( flxapp_active_packages() as $p ) {
			$v[ $p['slug'] ] = $p['version'];
		}
	}
	return $v;
}

/** /me + config + manifest özetleri: uygulama değişmeyeni yeniden indirmez. */
function flxapp_rest_me_full() {
	$me       = flxapp_rest_me();
	$u        = wp_get_current_user();
	$platform = flxapp_request_platform();
	$me['hashes'] = array(
		'me'       => substr( md5( wp_json_encode( $me ) ), 0, 12 ),
		'config'   => function_exists( 'flxapp_config_hash' ) ? flxapp_config_hash( $u, $platform ) : '',
		'manifest' => function_exists( 'flxapp_manifest_hash' ) ? flxapp_manifest_hash( $u, $platform ) : '',
	);
	return $me;
}

/**
 * Menü öğelerine paketin renkli logosu (`icon_url`): Flux Admin'in ortak logoları (flxa_logo_for_slug), sonra
 * `flxapp_menu_icon_url` filtresi ( $url, $slug ). Logosu olmayan öğede alan yok; uygulama `icon`'u kullanır.
 */
function flxapp_menu_with_icons( $menu ) {
	foreach ( $menu as $i => $m ) {
		$slug = (string) ( $m['slug'] ?? '' );
		$url  = '';
		if ( function_exists( 'flxa_logo_for_slug' ) && defined( 'FLXA_URL' ) ) {
			$file = flxa_logo_for_slug( $slug );
			$url  = '' === $file ? '' : ( function_exists( 'flxa_logo_file_url' ) ? flxa_logo_file_url( $file ) : FLXA_URL . 'assets/logos/' . $file );
		}
		// Paket kendi logosunu taşıyorsa (plugins/<slug>/assets/icon.svg, ör. flx-analytics, flx-speed).
		if ( '' === $url && 0 === strpos( $slug, 'flx-' ) ) {
			$dir    = WP_PLUGIN_DIR . '/' . sanitize_file_name( $slug ) . '/assets/';
			$pastel = function_exists( 'flxa_logo_style' ) && 'pastel' === flxa_logo_style();
			foreach ( $pastel ? array( 'icon-pastel.svg', 'icon.svg' ) : array( 'icon.svg' ) as $f ) {
				if ( is_readable( $dir . $f ) ) {
					$url = plugins_url( sanitize_file_name( $slug ) . '/assets/' . $f );
					break;
				}
			}
		}
		$url = (string) apply_filters( 'flxapp_menu_icon_url', $url, $slug );
		if ( '' !== $url ) {
			$menu[ $i ]['icon_url'] = $url;
		}
	}
	return $menu;
}

/**
 * Sol menü düzeni, paneldeki gibi (Flux Admin): `layout: { pins: [slug], groups: [ { id, label, collapsed, items: [slug] } ] }`.
 * Rolün menü düzeni (yoksa varsayılan gruplar) + kişinin sabitledikleri. Yalnız kullanıcının menüsündeki öğeler;
 * sabitlenenler gruplarda tekrar etmez, "Diğer"e taşınanlar Diğer grubunda. Flux Admin yoksa alan hiç gönderilmez.
 */
function flxapp_user_layout( $user, $menu ) {
	if ( ! function_exists( 'flxa_menu_config_for_user' ) || ! function_exists( 'flxa_user_pins' ) ) {
		return array();
	}
	$slugs   = array_values( array_filter( array_map( fn( $m ) => (string) ( $m['slug'] ?? '' ), (array) $menu ) ) );
	$have    = array_flip( $slugs );
	$cfg     = flxa_menu_config_for_user( $user );
	$entries = ! empty( $cfg['entries'] ) ? (array) $cfg['entries'] : ( function_exists( 'flxa_default_entries' ) ? flxa_default_entries( $slugs ) : array() );
	$pins    = array_values( array_filter( array_map( 'strval', (array) flxa_user_pins( $user->ID ) ), fn( $s ) => isset( $have[ $s ] ) ) );
	$away    = function_exists( 'flxa_user_list' ) ? array_diff( array_filter( array_map( 'strval', (array) flxa_user_list( 'flxa_away', $user->ID ) ), fn( $s ) => isset( $have[ $s ] ) ), $pins ) : array();
	$skip    = array_flip( array_merge( $pins, $away ) );
	$groups  = array();
	$cur     = -1;
	$used    = array();
	foreach ( $entries as $e ) {
		if ( ! is_array( $e ) ) {
			continue;
		}
		if ( 'g' === ( $e['t'] ?? '' ) ) {
			$id       = sanitize_key( (string) ( $e['id'] ?? '' ) );
			$groups[] = array(
				'id'        => '' !== $id ? $id : 'g' . count( $groups ),
				'label'     => (string) ( $e['label'] ?? '' ),
				'collapsed' => isset( $e['c'] ) ? (bool) $e['c'] : 'other' === $id,
				'items'     => array(),
			);
			$cur      = count( $groups ) - 1;
			continue;
		}
		$slug = (string) ( $e['slug'] ?? '' );
		if ( '' === $slug || ! isset( $have[ $slug ] ) || isset( $used[ $slug ] ) ) {
			continue;
		}
		$used[ $slug ] = true;
		if ( ! empty( $e['hidden'] ) || ! empty( $e['blocked'] ) || isset( $skip[ $slug ] ) ) {
			continue;
		}
		if ( $cur < 0 ) {
			$groups[] = array( 'id' => 'general', 'label' => '', 'collapsed' => false, 'items' => array() );
			$cur      = 0;
		}
		$groups[ $cur ]['items'][] = $slug;
	}
	// Düzende olmayan (sonradan kurulan) ve "Diğer"e taşınan öğeler Diğer grubunda.
	$left = array_merge( array_values( array_diff( $slugs, array_keys( $used ), $pins, $away ) ), array_values( $away ) );
	if ( $left ) {
		$oi = null;
		foreach ( $groups as $i => $g ) {
			if ( 'other' === $g['id'] ) {
				$oi = $i;
			}
		}
		if ( null === $oi ) {
			$groups[] = array( 'id' => 'other', 'label' => __( 'Diğer', 'flx-app' ), 'collapsed' => true, 'items' => array() );
			$oi       = count( $groups ) - 1;
		}
		$groups[ $oi ]['items'] = array_values( array_unique( array_merge( $groups[ $oi ]['items'], $left ) ) );
	}
	return array(
		'layout' => array(
			'pins'   => $pins,
			'groups' => array_values( array_filter( $groups, fn( $g ) => (bool) $g['items'] ) ),
		),
	);
}

/** Telefonda alt bar kısayolları: paneldeki mobil alt bar ayarı (Flux Admin, rol bazlı). Uygulama menüde olmayanları atlar. */
function flxapp_user_bottom( $user ) {
	if ( ! function_exists( 'flxa_menu_config_for_user' ) ) {
		return array();
	}
	$cfg = flxa_menu_config_for_user( $user );
	$b   = ! empty( $cfg['bottom'] ) ? (array) $cfg['bottom'] : ( function_exists( 'flxa_default_bottom' ) ? flxa_default_bottom() : array() );
	return array_values( array_map( 'strval', $b ) );
}

function flxapp_rest_session( WP_REST_Request $req ) {
	if ( ! flxapp_opt( 'web_session' ) ) {
		return new WP_Error( 'flxapp_web_session_off', __( 'Panel oturumu ayarlardan kapalı.', 'flx-app' ), array( 'status' => 403 ) );
	}
	if ( ! flxapp_rate( 'session', 30, MINUTE_IN_SECONDS ) ) {
		return new WP_Error( 'flxapp_rate', __( 'Çok fazla istek. Biraz bekleyin.', 'flx-app' ), array( 'status' => 429 ) );
	}
	$embed = $req->get_param( 'embed' );
	$path  = (string) $req->get_param( 'path' );
	$front = (string) $req->get_param( 'url' );
	if ( '' === $front && rest_sanitize_boolean( $req->get_param( 'front' ) ) ) {
		$front = '/' . ltrim( $path, '/' );
		$path  = '';
	}
	if ( '' !== $front && '' === flxapp_clean_front_url( $front ) ) {
		return new WP_Error( 'flxapp_bad_path', __( 'Geçersiz panel yolu.', 'flx-app' ), array( 'status' => 400 ) );
	}
	$tok = flxapp_current_token();
	$url = flxapp_sso_url(
		get_current_user_id(),
		$path,
		array(
			'url'   => $front,
			'embed' => null === $embed ? true : rest_sanitize_boolean( $embed ),
			'grant' => $tok ? (string) $tok['grant_id'] : '',
		)
	);
	if ( '' === $url ) {
		return new WP_Error( 'flxapp_bad_path', __( 'Geçersiz panel yolu.', 'flx-app' ), array( 'status' => 400 ) );
	}
	return array( 'url' => $url, 'expires_in' => 60 );
}

/** Bu oturumun (grant) açtığı webview çerez oturumlarını bitirir. */
function flxapp_rest_session_end() {
	$tok = flxapp_current_token();
	$n   = flxapp_end_web_sessions( get_current_user_id(), $tok ? (string) $tok['grant_id'] : '' );
	return array( 'ok' => true, 'ended' => $n );
}

function flxapp_rest_logout() {
	$tok = flxapp_current_token();
	if ( $tok ) {
		if ( '' !== $tok['grant_id'] ) {
			flxapp_revoke_grant( $tok['grant_id'] );
		} elseif ( 'access' === $tok['kind'] ) {
			flxapp_revoke_id( $tok['id'] );
		}
	}
	return array( 'ok' => true );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route( 'flxapp/v1', '/me', array( 'methods' => 'GET', 'callback' => 'flxapp_rest_me_full', 'permission_callback' => 'flxapp_logged_in' ) );
		register_rest_route(
			'flxapp/v1',
			'/counts',
			array(
				'methods'             => 'GET',
				'callback'            => function () {
					return flxapp_counts();
				},
				'permission_callback' => 'flxapp_logged_in',
			)
		);
		register_rest_route( 'flxapp/v1', '/session', array( 'methods' => 'POST', 'callback' => 'flxapp_rest_session', 'permission_callback' => 'flxapp_logged_in' ) );
		register_rest_route( 'flxapp/v1', '/session/end', array( 'methods' => 'POST', 'callback' => 'flxapp_rest_session_end', 'permission_callback' => 'flxapp_logged_in' ) );
		register_rest_route( 'flxapp/v1', '/logout', array( 'methods' => 'POST', 'callback' => 'flxapp_rest_logout', 'permission_callback' => 'flxapp_logged_in' ) );
	}
);

/* Değişken veri: önbelleğe girmesin. */
add_filter(
	'rest_post_dispatch',
	function ( $res, $server, $req ) {
		if ( $res instanceof WP_HTTP_Response && 0 === strpos( (string) $req->get_route(), '/flxapp/' ) ) {
			$res->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
			$res->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
			$res->header( 'CDN-Cache-Control', 'no-store' );
			do_action( 'litespeed_control_set_nocache', 'flx-app rest' );
		}
		return $res;
	},
	10,
	3
);
