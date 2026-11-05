<?php
/**
 * Keşif ve hafif uçlar (masaüstü + mobil ortak):
 *
 *   GET  /app/bootstrap   herkese açık (oran sınırlı, 5 dk önbellek): site, giriş yolları, istemciler, tema önizlemesi,
 *                         etkin FluxWP paketleri, preset, marka istemcileri, min_app
 *   GET  /sync?since=ts   giriş: { counts, hashes: { me, config, manifest }, events[], server_time }
 *   GET  /prefs           giriş: kullanıcı tercihleri (cihazlar arası)
 *   POST /prefs           kısmi güncelleme
 *   GET  /app/brand       manage_options: white-label taslağı (brand.json önerisi)
 *
 * `/wp-json/` dizinine `flxapp: { version, bootstrap }` eklenir; uygulama tek istekte Flux App'i tanır.
 */

defined( 'ABSPATH' ) || exit;

const FLXAPP_BOOTSTRAP_SCHEMA = 'flx-app-bootstrap/1';
const FLXAPP_EVENTS_MAX       = 20;

/** Etkin FluxWP paketleri (eklentiler + tema): [ { slug, name, type } ]. */
function flxapp_active_packages() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$all = get_plugins();
	$out = array();
	foreach ( (array) get_option( 'active_plugins', array() ) as $file ) {
		$slug = dirname( (string) $file );
		if ( 0 !== strpos( $slug, 'flx-' ) || ! isset( $all[ $file ] ) ) {
			continue;
		}
		$out[] = array(
			'slug'    => $slug,
			'name'    => (string) $all[ $file ]['Name'],
			'type'    => 'plugin',
			'version' => (string) $all[ $file ]['Version'],
		);
	}
	$theme = wp_get_theme();
	if ( $theme->exists() && ( current_theme_supports( 'flx-theme' ) || 0 === strpos( $theme->get_stylesheet(), 'flx' ) ) ) {
		$out[] = array( 'slug' => $theme->get_stylesheet(), 'name' => (string) $theme->get( 'Name' ), 'type' => 'theme', 'version' => (string) $theme->get( 'Version' ) );
	}
	return $out;
}

function flxapp_bootstrap() {
	$cache = get_transient( 'flxapp_bootstrap_' . determine_locale() );
	if ( is_array( $cache ) && ( $cache['flx_app'] ?? '' ) === FLXAPP_VERSION ) {
		return $cache;
	}
	$theme   = flxapp_theme();
	$methods = array( 'oauth' );
	if ( flxapp_opt( 'otp_enabled' ) ) {
		$methods[] = 'otp';
	}
	$methods[] = 'apikey';
	$clients   = array();
	foreach ( flxapp_brand_clients() as $c ) {
		$clients[] = array( 'id' => $c['id'], 'name' => $c['name'], 'platform' => $c['platform'] );
	}
	$generic = (bool) flxapp_opt( 'generic_app' );
	$out     = array(
		'schema'      => FLXAPP_BOOTSTRAP_SCHEMA,
		'site'        => array(
			'name'        => html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ),
			'description' => html_entity_decode( get_bloginfo( 'description' ), ENT_QUOTES, 'UTF-8' ),
			'url'         => flxapp_issuer(),
			'admin_url'   => admin_url(),
			'rest_url'    => rest_url(),
			'icon'        => (string) get_site_icon_url( 256 ),
			'locale'      => get_locale(),
			'timezone'    => wp_timezone_string(),
			'https'       => 0 === strpos( home_url(), 'https://' ),
		),
		'auth'        => array(
			'methods'      => $methods,
			'clients'      => $generic ? array( 'mobile' => FLXAPP_MOBILE_CLIENT, 'desktop' => FLXAPP_DESKTOP_CLIENT ) : (object) array(),
			'redirects'    => array(
				'mobile'  => array( 'fluxwp://oauth/callback' ),
				'desktop' => array( 'http://127.0.0.1/callback', 'fluxapp://oauth/callback' ),
				'loopback_any_port' => true,
			),
			'authorize'    => flxapp_authorize_url(),
			'login_url'    => wp_login_url(),
			'token'        => rest_url( 'flxapp/v1/oauth/token' ),
			'revoke'       => rest_url( 'flxapp/v1/oauth/revoke' ),
			'scopes'       => FLXAPP_SCOPES,
			'pkce'         => 'S256',
			'prompt_login' => true,
			'otp'          => in_array( 'otp', $methods, true ) ? array( 'start' => rest_url( 'flxapp/v1/otp/start' ), 'verify' => rest_url( 'flxapp/v1/otp/verify' ) ) : null,
		),
		'apps'        => array(
			'generic' => $generic,
			'clients' => $clients,
			'preset'  => flxapp_site_preset(),
			'store'   => (object) array_filter( array( 'ios' => (string) flxapp_opt( 'store_ios' ), 'android' => (string) flxapp_opt( 'store_android' ) ) ),
		),
		'brand'       => array_intersect_key( flxapp_brand_info(), array_flip( array( 'name', 'short_name', 'logo', 'logo_dark', 'icon' ) ) ),
		'theme'       => array(
			'mode'  => $theme['mode'],
			'light' => $theme['light'],
			'dark'  => $theme['dark'],
		),
		'packages'    => array_map( fn( $p ) => array_intersect_key( $p, array_flip( array( 'slug', 'name', 'type' ) ) ), flxapp_active_packages() ),
		'min_app'     => flxapp_min_app()['mobile'],
		'min_app_desktop' => flxapp_min_app()['desktop'],
		'flx_app'     => FLXAPP_VERSION,
		'api'         => 'flxapp/v1',
		'endpoints'   => array(
			'me'        => rest_url( 'flxapp/v1/me' ),
			'config'    => rest_url( 'flxapp/v1/config' ),
			'manifest'  => rest_url( 'flxapp/v1/manifest' ),
			'dashboard' => rest_url( 'flxapp/v1/dashboard' ),
			'sync'      => rest_url( 'flxapp/v1/sync' ),
		),
	);
	/**
	 * Herkese açık keşif yanıtı. Gizli bilgi eklemeyin.
	 *
	 * @param array $out
	 */
	$out = (array) apply_filters( 'flxapp_bootstrap', $out );
	set_transient( 'flxapp_bootstrap_' . determine_locale(), $out, 5 * MINUTE_IN_SECONDS );
	return $out;
}

/** Ayar ya da paket değişince keşif önbelleği boşalır. */
function flxapp_bootstrap_flush() {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_flxapp\\_bootstrap\\_%' OR option_name LIKE '\\_transient\\_timeout\\_flxapp\\_bootstrap\\_%'" ); // phpcs:ignore WordPress.DB
}
foreach ( array( 'update_option_flxapp_settings', 'update_option_flxapp_app', 'update_option_flx_profile', 'activated_plugin', 'deactivated_plugin', 'switch_theme', 'update_option_blogname', 'update_option_site_icon' ) as $flxapp_hook ) {
	add_action( $flxapp_hook, 'flxapp_bootstrap_flush' );
}

function flxapp_rest_bootstrap() {
	$r = new WP_REST_Response( flxapp_bootstrap(), 200 );
	$r->header( 'Access-Control-Allow-Origin', '*' );
	return $r;
}

/* ───────── Kullanıcı tercihleri ───────── */

const FLXAPP_PREF_LISTS = array( 'order', 'hidden', 'pinned', 'forceWeb', 'tabs', 'cardOrder', 'cardHidden' );

function flxapp_user_prefs( $user_id ) {
	$p = get_user_meta( (int) $user_id, 'flxapp_prefs', true );
	return is_array( $p ) ? $p : array();
}

function flxapp_prefs_sanitize( array $in, array $cur ) {
	foreach ( FLXAPP_PREF_LISTS as $k ) {
		if ( array_key_exists( $k, $in ) ) {
			$list = array();
			foreach ( array_slice( (array) $in[ $k ], 0, 100 ) as $v ) {
				$v = substr( preg_replace( '/[^A-Za-z0-9_\-.:\/?=&%]/', '', (string) $v ), 0, 190 );
				if ( '' !== $v ) {
					$list[] = $v;
				}
			}
			$cur[ $k ] = array_values( array_unique( $list ) );
			if ( 'tabs' === $k ) {
				$cur[ $k ] = array_slice( $cur[ $k ], 0, 5 );
			}
		}
	}
	if ( isset( $in['push'] ) && is_array( $in['push'] ) ) {
		$known = flxapp_push_events();
		$push  = (array) ( $cur['push'] ?? array() );
		foreach ( $in['push'] as $ev => $on ) {
			$ev = preg_replace( '/[^a-z0-9_.\-]/', '', strtolower( (string) $ev ) );
			if ( isset( $known[ $ev ] ) ) {
				$push[ $ev ] = (bool) $on;
			}
		}
		$cur['push'] = $push;
	}
	$cur['updated'] = time();
	return $cur;
}

function flxapp_prefs_out( $user_id ) {
	$p      = flxapp_user_prefs( $user_id );
	$events = array();
	$user   = get_userdata( (int) $user_id );
	foreach ( flxapp_push_events() as $id => $e ) {
		if ( ! empty( $e['cap'] ) && ! user_can( $user, $e['cap'] ) ) {
			continue;
		}
		$events[] = array(
			'id'      => $id,
			'title'   => (string) $e['title'],
			'source'  => (string) ( $e['source'] ?? '' ),
			'default' => ! empty( $e['default'] ),
			'on'      => isset( $p['push'][ $id ] ) ? (bool) $p['push'][ $id ] : ! empty( $e['default'] ),
		);
	}
	$out = array();
	foreach ( FLXAPP_PREF_LISTS as $k ) {
		$out[ $k ] = array_values( (array) ( $p[ $k ] ?? array() ) );
	}
	$out['push']    = (object) (array) ( $p['push'] ?? array() );
	$out['events']  = $events;
	$out['updated'] = (int) ( $p['updated'] ?? 0 );
	return $out;
}

function flxapp_rest_prefs_get() {
	return flxapp_prefs_out( get_current_user_id() );
}

function flxapp_rest_prefs_set( WP_REST_Request $req ) {
	$uid = get_current_user_id();
	$in  = (array) $req->get_json_params() + (array) $req->get_body_params();
	update_user_meta( $uid, 'flxapp_prefs', flxapp_prefs_sanitize( $in, flxapp_user_prefs( $uid ) ) );
	return flxapp_prefs_out( $uid );
}

/* ───────── Olay kaydı (masaüstü bildirimi; /sync events) ───────── */

/** Kullanıcının son olaylarına ekler (en çok 20). Tercihte kapalı türler yazılmaz. */
function flxapp_event_record( $user_id, array $e ) {
	$event = (string) ( $e['event'] ?? '' );
	if ( function_exists( 'flxapp_push_wanted' ) && ! flxapp_push_wanted( (int) $user_id, $event ) ) {
		return;
	}
	$list   = get_user_meta( (int) $user_id, 'flxapp_events', true );
	$list   = is_array( $list ) ? $list : array();
	$list[] = array(
		'event'  => $event,
		'title'  => (string) ( $e['title'] ?? '' ),
		'body'   => flxapp_opt( 'push_hide' ) && empty( $e['public'] ) ? '' : (string) ( $e['body'] ?? '' ),
		'target' => (string) ( $e['target'] ?? '' ),
		'params' => (object) (array) ( $e['params'] ?? array() ),
		'at'     => time(),
	);
	update_user_meta( (int) $user_id, 'flxapp_events', array_slice( $list, -FLXAPP_EVENTS_MAX ) );
}

function flxapp_events_since( $user_id, $since ) {
	$list = get_user_meta( (int) $user_id, 'flxapp_events', true );
	return array_values( array_filter( is_array( $list ) ? $list : array(), fn( $e ) => (int) $e['at'] > (int) $since ) );
}

function flxapp_rest_sync( WP_REST_Request $req ) {
	$user     = wp_get_current_user();
	$platform = flxapp_request_platform();
	$me       = flxapp_rest_me();
	return array(
		'counts'      => (object) flxapp_counts(),
		'hashes'      => array(
			'me'       => substr( md5( wp_json_encode( $me ) ), 0, 12 ),
			'config'   => flxapp_config_hash( $user, $platform ),
			'manifest' => flxapp_manifest_hash( $user, $platform ),
		),
		'events'      => flxapp_events_since( $user->ID, max( 0, (int) $req->get_param( 'since' ) ) ),
		'server_time' => time(),
	);
}

/* ───────── White-label taslağı ───────── */

function flxapp_rest_brand() {
	$b       = flxapp_brand_info();
	$t       = flxapp_theme();
	$host    = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$slug    = sanitize_title( '' !== $b['short_name'] ? $b['short_name'] : $b['name'] );
	$methods = array( 'oauth' );
	if ( flxapp_opt( 'otp_enabled' ) ) {
		$methods[] = 'otp';
	}
	$clients = flxapp_brand_clients();
	$pick    = function ( $pf ) use ( $clients ) {
		foreach ( $clients as $c ) {
			if ( $pf === $c['platform'] ) {
				return $c;
			}
		}
		return null;
	};
	$mob     = $pick( 'mobile' );
	$desk    = $pick( 'desktop' );
	$scheme  = function ( $c ) {
		foreach ( (array) ( $c['redirect_uris'] ?? array() ) as $u ) {
			$s = (string) wp_parse_url( $u, PHP_URL_SCHEME );
			if ( 'http' !== $s ) {
				return $s;
			}
		}
		return '';
	};
	return array(
		'brand_json'  => array(
			'slug'          => $slug,
			'name'          => $b['name'],
			'shortName'     => '' !== $b['short_name'] ? $b['short_name'] : $b['name'],
			'scheme'        => $desk ? $scheme( $desk ) : $slug . '-desktop',
			'oauthClientId' => $desk ? $desk['id'] : 'flx-masaustu-' . $slug,
			'colors'        => array( 'primary' => $t['light']['primary'], 'primaryContrast' => $t['light']['onPrimary'], 'accent' => $t['light']['accent'] ),
			'defaultSite'   => flxapp_issuer(),
			'allowedSites'  => array( $host ),
			'lockSite'      => true,
			'loginMethods'  => $methods,
			'defaultLocale' => 0 === strpos( get_locale(), 'en' ) ? 'en' : 'tr',
			'locales'       => array( 'tr', 'en' ),
			'mobile'        => array(
				'scheme'        => $mob ? $scheme( $mob ) : $slug,
				'oauthClientId' => $mob ? $mob['id'] : 'flx-mobil-' . $slug,
			),
			'supportUrl'    => 'mailto:' . get_option( 'admin_email' ),
		),
		'assets'      => array_filter( array( 'icon' => $b['icon'], 'logo' => $b['logo'], 'logo_dark' => $b['logo_dark'] ) ),
		'flxapp_app'  => (object) flxapp_app_option(),
		'app_clients' => array_values( array_map( fn( $c ) => array_intersect_key( $c, array_flip( array( 'id', 'name', 'platform', 'redirect_uris' ) ) ), $clients ) ),
		'preset'      => flxapp_site_preset(),
		'warnings'    => array_values(
			array_filter(
				array(
					$mob ? '' : __( 'Mobil marka istemcisi (app_clients) tanımlı değil; markalı mobil uygulama bu siteye bağlanamaz.', 'flx-app' ),
					$b['icon'] ? '' : __( 'Site simgesi yok; mağaza ikonu için 1024 px görsel gerekir.', 'flx-app' ),
				)
			)
		),
	);
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route( 'flxapp/v1', '/app/bootstrap', array( 'methods' => 'GET', 'callback' => 'flxapp_rest_bootstrap', 'permission_callback' => 'flxapp_public_permission' ) );
		register_rest_route( 'flxapp/v1', '/sync', array( 'methods' => 'GET', 'callback' => 'flxapp_rest_sync', 'permission_callback' => 'flxapp_logged_in' ) );
		register_rest_route(
			'flxapp/v1',
			'/prefs',
			array(
				array( 'methods' => 'GET', 'callback' => 'flxapp_rest_prefs_get', 'permission_callback' => 'flxapp_logged_in' ),
				array( 'methods' => 'POST', 'callback' => 'flxapp_rest_prefs_set', 'permission_callback' => 'flxapp_logged_in' ),
			)
		);
		register_rest_route(
			'flxapp/v1',
			'/app/brand',
			array(
				'methods'             => 'GET',
				'callback'            => 'flxapp_rest_brand',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ) ? true : new WP_Error( 'rest_forbidden', __( 'Bu işlem için yetkiniz yok.', 'flx-app' ), array( 'status' => is_user_logged_in() ? 403 : 401 ) );
				},
			)
		);
	}
);

/* /wp-json/ dizininde Flux App ipucu. */
add_filter(
	'rest_index',
	function ( $res ) {
		if ( $res instanceof WP_REST_Response ) {
			$d           = $res->get_data();
			$d['flxapp'] = array( 'version' => FLXAPP_VERSION, 'bootstrap' => rest_url( 'flxapp/v1/app/bootstrap' ) );
			$res->set_data( $d );
		}
		return $res;
	}
);
