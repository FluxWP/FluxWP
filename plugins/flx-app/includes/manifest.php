<?php
/**
 * Manifest sistemi (`flx-app-manifest/1`): paketler uygulamadaki ekranlarını JSON ile bir kez tanımlar; masaüstü
 * ve mobil aynı manifesti kendi görüntüleyicisiyle çizer. Sözleşme: docs/flx-app/API.md › Manifest.
 *
 *   add_filter( 'flxapp_manifest', function ( array $m, WP_User $user ) {
 *       $m['crm'] = flxapp_manifest_file( FLXCRM_DIR . 'app/manifest.json', 'flx-crm', array(
 *           'version' => FLXCRM_VERSION,
 *           'caps'    => array( 'view' => 'flxcrm_view', 'manage' => fn( $u ) => user_can( $u, 'flxcrm_manage' ) ),
 *       ) );
 *       return $m;
 *   }, 10, 2 );
 *
 *   GET /flxapp/v1/manifest        { schema, hash, platform, modules: { id: manifest } }  (ETag / 304)
 *   GET /flxapp/v1/manifest/<id>   tek modül
 *
 * Sunucu: kullanıcı diline geçer, `caps` → `can` hesaplar, `can` koşulu false olan ekran/aksiyon/alan/bölümü
 * çıkarır, `can.view` false modülü göndermez, platforma kapalı olanı (`platforms`, Flux Admin platform menüsü)
 * süzer, yönetici kapattığı modül/ekranı (`modules_off`, `screens_off`) çıkarır. Yetki yine her REST ucunda ayrıca
 * denetlenir; manifest yalnız arayüzü belirler ve kod çalıştıramaz.
 */

defined( 'ABSPATH' ) || exit;

const FLXAPP_MANIFEST_SCHEMA = 'flx-app-manifest/1';
const FLXAPP_SCREEN_TYPES    = array( 'list', 'detail', 'form', 'dashboard', 'settings', 'content', 'tabs', 'native', 'web' );
const FLXAPP_ACTION_KINDS    = array( 'request', 'navigate', 'web', 'open', 'share', 'copy', 'native', 'refresh' );
const FLXAPP_FIELD_TYPES     = array( 'text', 'textarea', 'email', 'phone', 'url', 'number', 'money', 'switch', 'select', 'multiselect', 'radio', 'segmented', 'date', 'datetime', 'time', 'color', 'image', 'file', 'markdown', 'html', 'user', 'post', 'term', 'tags', 'hidden', 'readonly', 'secret', 'repeater' );
const FLXAPP_SECTION_TYPES   = array( 'fields', 'stats', 'chips', 'timeline', 'list', 'content', 'media', 'chart', 'actions', 'notice' );
const FLXAPP_TONES           = array( 'default', 'accent', 'ok', 'warn', 'danger', 'muted', 'info' );
const FLXAPP_I18N_KEYS       = array( 'title', 'label', 'placeholder', 'help', 'body', 'ok', 'toast', 'message' );

/**
 * Statik JSON manifesti okur ve çevirir. Türkçe msgid'ler `translate( $s, $domain )` ile kullanıcı diline geçer
 * (yalnız FLXAPP_I18N_KEYS anahtarları; `{{…}}` şablonları çevrilmez). $extra üst düzeye yazılır (version, caps).
 *
 * @return array
 */
function flxapp_manifest_file( $path, $domain, array $extra = array() ) {
	static $cache = array();
	if ( ! isset( $cache[ $path ] ) ) {
		$json           = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$cache[ $path ] = is_array( $json ) ? $json : array();
	}
	return array_merge( flxapp_manifest_translate( $cache[ $path ], (string) $domain ), $extra );
}

function flxapp_manifest_translate( $node, $domain, $key = '' ) {
	if ( is_array( $node ) ) {
		foreach ( $node as $k => $v ) {
			$node[ $k ] = flxapp_manifest_translate( $v, $domain, is_string( $k ) ? $k : $key );
		}
		return $node;
	}
	if ( is_string( $node ) && '' !== $node && in_array( $key, FLXAPP_I18N_KEYS, true ) && false === strpos( $node, '{{' ) ) {
		return translate( $node, $domain ); // phpcs:ignore WordPress.WP.I18n
	}
	return $node;
}

/** Ham manifestler (kullanıcıya göre; filtre kullanıcıyı alır). */
function flxapp_manifests_raw( $user = null ) {
	static $cache = array();
	$user = $user ? $user : wp_get_current_user();
	$uid  = $user ? (int) $user->ID : 0;
	if ( isset( $cache[ $uid ] ) ) {
		return $cache[ $uid ];
	}
	/**
	 * Paketlerin manifestleri. Anahtar = modül kimliği (`flxapp_modules` anahtarıyla aynı).
	 *
	 * @param array   $modules
	 * @param WP_User $user
	 */
	$all = (array) apply_filters( 'flxapp_manifest', array(), $user );
	$out = array();
	foreach ( $all as $id => $m ) {
		$id = sanitize_key( (string) ( is_array( $m ) && ! empty( $m['id'] ) ? $m['id'] : $id ) );
		if ( '' !== $id && is_array( $m ) ) {
			$m['id']    = $id;
			$out[ $id ] = $m;
		}
	}
	uasort( $out, fn( $a, $b ) => ( (int) ( $a['order'] ?? 50 ) ) <=> ( (int) ( $b['order'] ?? 50 ) ) );
	$cache[ $uid ] = $out;
	return $out;
}

/** `caps` tanımlarından `can` haritası: değer yetki adı, yetki dizisi (herhangi biri) ya da callable( $user ). */
function flxapp_manifest_can( array $caps, $user ) {
	$can = array();
	foreach ( $caps as $name => $def ) {
		if ( is_callable( $def ) && ! is_string( $def ) ) {
			$ok = (bool) call_user_func( $def, $user );
		} else {
			$ok = false;
			foreach ( (array) $def as $cap ) {
				if ( is_string( $cap ) && user_can( $user, $cap ) ) {
					$ok = true;
					break;
				}
			}
		}
		$can[ (string) $name ] = $ok;
	}
	return $can;
}

/** Düğüm görünür mü: `can` ("manage", "!manage" ya da dizi = hepsi) ve `platforms`. */
function flxapp_manifest_visible( $node, array $can, $platform ) {
	if ( ! is_array( $node ) ) {
		return true;
	}
	if ( isset( $node['can'] ) && is_string( $node['can'] ) || isset( $node['can'] ) && flxapp_is_list( $node['can'] ) ) {
		foreach ( (array) $node['can'] as $c ) {
			$neg = 0 === strpos( (string) $c, '!' );
			$v   = ! empty( $can[ ltrim( (string) $c, '!' ) ] );
			if ( $neg ? $v : ! $v ) {
				return false;
			}
		}
	}
	if ( '' !== $platform && ! empty( $node['platforms'] ) && ! in_array( $platform, (array) $node['platforms'], true ) ) {
		return false;
	}
	return true;
}

/** PHP 7.4 için array_is_list karşılığı. */
function flxapp_is_list( $a ) {
	if ( ! is_array( $a ) ) {
		return false;
	}
	$i = 0;
	foreach ( $a as $k => $v ) {
		if ( $k !== $i++ ) {
			return false;
		}
	}
	return true;
}

/** Listeyi (ya da haritayı) görünürlüğe göre süzer, iç içe alan/bölüm/öğe listelerine iner. */
function flxapp_manifest_prune( $list, array $can, $platform ) {
	if ( ! is_array( $list ) ) {
		return $list;
	}
	$is_list = flxapp_is_list( $list );
	$out     = array();
	foreach ( $list as $k => $node ) {
		if ( ! flxapp_manifest_visible( $node, $can, $platform ) ) {
			continue;
		}
		if ( is_array( $node ) ) {
			foreach ( array( 'fields', 'sections', 'items', 'filters', 'columns', 'badges', 'tabs' ) as $sub ) {
				if ( isset( $node[ $sub ] ) && is_array( $node[ $sub ] ) ) {
					$node[ $sub ] = flxapp_manifest_prune( $node[ $sub ], $can, $platform );
				}
			}
			if ( isset( $node['prompt']['fields'] ) ) {
				$node['prompt']['fields'] = flxapp_manifest_prune( $node['prompt']['fields'], $can, $platform );
			}
		}
		if ( $is_list ) {
			$out[] = $node;
		} else {
			$out[ $k ] = $node;
		}
	}
	return $out;
}

/** Ekran aksiyon yerleşiminden var olmayan aksiyonları çıkarır (header, row, bulk, footer, fab, quick). */
function flxapp_manifest_prune_refs( $refs, array $actions ) {
	if ( is_string( $refs ) ) {
		return isset( $actions[ $refs ] ) ? $refs : null;
	}
	if ( ! is_array( $refs ) ) {
		return $refs;
	}
	$out = array();
	foreach ( $refs as $k => $v ) {
		if ( is_string( $v ) && ! is_string( $k ) && ! isset( $actions[ $v ] ) ) {
			continue;
		}
		$v = is_array( $v ) || ( is_string( $k ) && is_string( $v ) ) ? flxapp_manifest_prune_refs( $v, $actions ) : $v;
		if ( null === $v ) {
			continue;
		}
		if ( is_string( $k ) ) {
			$out[ $k ] = $v;
		} else {
			$out[] = $v;
		}
	}
	return $out;
}

/**
 * Bir modülü kullanıcıya ve platforma göre çözer. Görünmeyecekse null.
 *
 * @return array|null
 */
function flxapp_manifest_resolve( array $m, $user, $platform = '' ) {
	$caps = (array) ( $m['caps'] ?? array() );
	$can  = flxapp_manifest_can( $caps, $user );
	if ( isset( $can['view'] ) && ! $can['view'] ) {
		return null;
	}
	if ( ! flxapp_manifest_visible( $m, $can, $platform ) ) {
		return null;
	}
	$id = (string) $m['id'];
	if ( in_array( $id, (array) flxapp_opt( 'modules_off' ), true ) ) {
		return null;
	}
	if ( ! empty( $m['menu'] ) && '' !== $platform && in_array( (string) $m['menu'], flxapp_platform_off_slugs( $user, $platform ), true ) ) {
		return null;
	}
	unset( $m['caps'] );
	$m['schema'] = FLXAPP_MANIFEST_SCHEMA;
	$m['can']    = (object) $can;
	$off         = (array) flxapp_opt( 'screens_off' );
	$screens     = array();
	foreach ( flxapp_manifest_prune( (array) ( $m['screens'] ?? array() ), $can, $platform ) as $sid => $s ) {
		if ( ! in_array( $id . '/' . $sid, $off, true ) ) {
			$screens[ (string) $sid ] = $s;
		}
	}
	$actions = flxapp_manifest_prune( (array) ( $m['actions'] ?? array() ), $can, $platform );
	foreach ( $actions as $aid => $a ) {
		$t = (string) ( $a['navigate']['target'] ?? '' );
		if ( 0 === strpos( $t, 'screen:' . $id . '/' ) && ! isset( $screens[ substr( $t, strlen( 'screen:' . $id . '/' ) ) ] ) ) {
			unset( $actions[ $aid ] );
		}
	}
	foreach ( $screens as $sid => $s ) {
		if ( isset( $s['actions'] ) ) {
			$screens[ $sid ]['actions'] = flxapp_manifest_prune_refs( $s['actions'], $actions );
		}
		if ( ! empty( $s['header']['quick'] ) ) {
			$screens[ $sid ]['header']['quick'] = flxapp_manifest_prune_refs( (array) $s['header']['quick'], $actions );
		}
		foreach ( (array) ( $s['sections'] ?? array() ) as $i => $sec ) {
			if ( 'actions' === ( $sec['type'] ?? '' ) ) {
				$screens[ $sid ]['sections'][ $i ]['items'] = flxapp_manifest_prune_refs( (array) ( $sec['items'] ?? array() ), $actions );
			}
			foreach ( array( 'add', 'remove' ) as $k ) {
				if ( isset( $sec[ $k ] ) && ! isset( $actions[ $sec[ $k ] ] ) ) {
					unset( $screens[ $sid ]['sections'][ $i ][ $k ] );
				}
			}
		}
	}
	if ( ! $screens ) {
		return null;
	}
	$m['screens'] = (object) $screens;
	$m['actions'] = (object) $actions;
	if ( isset( $m['tabs'] ) ) {
		$m['tabs'] = array_values( array_filter( (array) $m['tabs'], fn( $t ) => isset( $screens[ $t ] ) ) );
	}
	if ( empty( $m['entry'] ) || ! isset( $screens[ $m['entry'] ] ) ) {
		$m['entry'] = ! empty( $m['tabs'] ) ? $m['tabs'][0] : (string) array_key_first( $screens );
	}
	if ( isset( $m['routes'] ) ) {
		$m['routes'] = (object) array_filter( (array) $m['routes'], fn( $r ) => isset( $screens[ $r['screen'] ?? '' ] ) );
	}
	if ( isset( $m['sources'] ) ) {
		$m['sources'] = (object) $m['sources'];
	}
	return $m;
}

/** Kullanıcının çözümlenmiş modülleri: id => manifest. */
function flxapp_user_manifest( $user = null, $platform = null ) {
	static $cache = array();
	$user     = $user ? $user : wp_get_current_user();
	$platform = null === $platform ? flxapp_request_platform() : (string) $platform;
	$key      = (int) $user->ID . '|' . $platform . '|' . determine_locale();
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$switched = function_exists( 'switch_to_user_locale' ) && ! is_admin() ? switch_to_user_locale( $user->ID ) : false;
	$out      = array();
	foreach ( flxapp_manifests_raw( $user ) as $id => $m ) {
		$r = flxapp_manifest_resolve( $m, $user, $platform );
		if ( $r ) {
			$out[ $id ] = $r;
		}
	}
	if ( $switched ) {
		restore_previous_locale();
	}
	$cache[ $key ] = $out;
	return $out;
}

function flxapp_manifest_hash( $user = null, $platform = null ) {
	return substr( md5( wp_json_encode( flxapp_user_manifest( $user, $platform ) ) . FLXAPP_VERSION ), 0, 12 );
}

/** Ekran kataloğu (Görünüm sekmesi): "module/screen" => başlık, yalnız giriş noktası olan türler. */
function flxapp_screen_catalog( $user = null ) {
	$out = array();
	foreach ( flxapp_manifests_raw( $user ) as $id => $m ) {
		foreach ( (array) ( $m['screens'] ?? array() ) as $sid => $s ) {
			// Detay ve form ekranları bir listeden açılır; kapatılacak giriş noktaları bunlar.
			if ( ! in_array( $s['type'] ?? '', array( 'detail', 'form' ), true ) ) {
				$out[ $id . '/' . $sid ] = (string) ( $m['title'] ?? $id ) . ' › ' . (string) ( $s['title'] ?? $sid );
			}
		}
	}
	return $out;
}

/**
 * Manifesti doğrular. Hata listesi döner (boş = geçerli).
 *
 * @return string[]
 */
function flxapp_manifest_validate( array $m ) {
	$e       = array();
	$screens = (array) ( $m['screens'] ?? array() );
	$actions = (array) ( $m['actions'] ?? array() );
	$sources = (array) ( $m['sources'] ?? array() );
	if ( isset( $m['schema'] ) && FLXAPP_MANIFEST_SCHEMA !== $m['schema'] ) {
		$e[] = 'schema';
	}
	if ( '' === (string) ( $m['title'] ?? '' ) ) {
		$e[] = 'title';
	}
	if ( ! $screens || flxapp_is_list( $screens ) ) {
		$e[] = 'screens (map)';
	}
	if ( ! isset( $m['sources'] ) || ! is_array( $m['sources'] ) ) {
		$e[] = 'sources';
	}
	if ( ! empty( $m['entry'] ) && ! isset( $screens[ $m['entry'] ] ) ) {
		$e[] = 'entry';
	}
	foreach ( (array) ( $m['tabs'] ?? array() ) as $t ) {
		if ( ! isset( $screens[ $t ] ) ) {
			$e[] = "tabs.$t";
		}
	}
	foreach ( $sources as $sid => $src ) {
		$path = (string) ( $src['path'] ?? '' );
		if ( '' === $path || preg_match( '#^[a-z][a-z0-9+.\-]*://#i', $path ) || 0 === strpos( $path, '//' ) ) {
			$e[] = "sources.$sid.path";
		}
		$mode = (string) ( $src['paging']['mode'] ?? 'none' );
		if ( ! in_array( $mode, array( 'page', 'wp', 'cursor', 'none' ), true ) ) {
			$e[] = "sources.$sid.paging.mode";
		}
		if ( isset( $src['poll'] ) && (int) $src['poll'] > 0 && (int) $src['poll'] < 10 ) {
			$e[] = "sources.$sid.poll (>=10)";
		}
	}
	$src_ok = function ( $ref ) use ( $sources ) {
		return is_array( $ref ) || isset( $sources[ (string) $ref ] );
	};
	foreach ( $screens as $sid => $s ) {
		$type = (string) ( $s['type'] ?? '' );
		if ( ! in_array( $type, FLXAPP_SCREEN_TYPES, true ) ) {
			$e[] = "screens.$sid.type";
			continue;
		}
		if ( in_array( $type, array( 'list', 'detail', 'content' ), true ) && ( empty( $s['source'] ) || ! $src_ok( $s['source'] ) ) ) {
			$e[] = "screens.$sid.source";
		}
		if ( 'dashboard' === $type ) {
			foreach ( (array) ( $s['sources'] ?? array() ) as $ref ) {
				if ( ! $src_ok( $ref ) ) {
					$e[] = "screens.$sid.sources.$ref";
				}
			}
		}
		if ( 'form' === $type && empty( $s['submit'] ) ) {
			$e[] = "screens.$sid.submit";
		}
		if ( 'form' === $type && ! empty( $s['load'] ) && ! $src_ok( $s['load'] ) ) {
			$e[] = "screens.$sid.load";
		}
		if ( 'settings' === $type && empty( $s['option'] ) ) {
			$e[] = "screens.$sid.option";
		}
		if ( 'native' === $type && empty( $s['component'] ) ) {
			$e[] = "screens.$sid.component";
		}
		if ( 'web' === $type && empty( $s['path'] ) ) {
			$e[] = "screens.$sid.path";
		}
		foreach ( (array) ( $s['fields'] ?? array() ) as $f ) {
			$fname = (string) ( $f['name'] ?? '?' );
			if ( ! in_array( (string) ( $f['type'] ?? '' ), FLXAPP_FIELD_TYPES, true ) ) {
				$e[] = "screens.$sid.fields.$fname.type";
			}
			if ( '' === (string) ( $f['label'] ?? '' ) && 'hidden' !== ( $f['type'] ?? '' ) ) {
				$e[] = "screens.$sid.fields.$fname.label";
			}
		}
		foreach ( (array) ( $s['sections'] ?? array() ) as $i => $sec ) {
			$st  = (string) ( $sec['type'] ?? '' );
			$req = array( 'fields' => 'fields', 'stats' => 'items', 'chips' => 'path', 'timeline' => 'path', 'list' => 'path', 'content' => 'value', 'actions' => 'items', 'notice' => 'text' );
			if ( ! in_array( $st, FLXAPP_SECTION_TYPES, true ) ) {
				$e[] = "screens.$sid.sections[$i].type";
			} elseif ( isset( $req[ $st ] ) && ! isset( $sec[ $req[ $st ] ] ) ) {
				$e[] = "screens.$sid.sections[$i].{$req[ $st ]}";
			}
		}
		if ( 'form' === $type && ! empty( $s['submit'] ) && empty( $s['submit']['path'] ) ) {
			$e[] = "screens.$sid.submit.path";
		}
		foreach ( (array) ( $s['item']['badges'] ?? array() ) as $bd ) {
			if ( isset( $bd['tone'] ) && ! in_array( $bd['tone'], FLXAPP_TONES, true ) ) {
				$e[] = "screens.$sid.item.badges.tone";
			}
		}
		$refs = array();
		foreach ( (array) ( $s['actions'] ?? array() ) as $slot => $list ) {
			$refs = array_merge( $refs, (array) $list );
		}
		foreach ( $refs as $ref ) {
			if ( is_string( $ref ) && ! isset( $actions[ $ref ] ) ) {
				$e[] = "screens.$sid.actions.$ref";
			}
		}
	}
	foreach ( $actions as $aid => $a ) {
		$kinds = array_intersect( array_keys( (array) $a ), FLXAPP_ACTION_KINDS );
		if ( 1 !== count( $kinds ) ) {
			$e[] = "actions.$aid (one of " . implode( '|', FLXAPP_ACTION_KINDS ) . ')';
		}
		if ( isset( $a['request'] ) && empty( $a['request']['path'] ) ) {
			$e[] = "actions.$aid.request.path";
		}
		if ( '' === (string) ( $a['label'] ?? '' ) ) {
			$e[] = "actions.$aid.label";
		}
		if ( isset( $a['tone'] ) && ! in_array( $a['tone'], FLXAPP_TONES, true ) ) {
			$e[] = "actions.$aid.tone";
		}
	}
	return $e;
}

/** Tüm manifestlerin doğrulama hataları (yönetici görünümü): id => [hata]. */
function flxapp_manifest_errors() {
	$out = array();
	foreach ( flxapp_manifests_raw() as $id => $m ) {
		$e = flxapp_manifest_validate( $m );
		if ( $e ) {
			$out[ $id ] = $e;
		}
	}
	return $out;
}

/** ETag'li yanıt: If-None-Match eşleşirse 304. */
function flxapp_etag_response( array $data, $hash, WP_REST_Request $req ) {
	$etag = '"' . $hash . '"';
	if ( trim( (string) $req->get_header( 'if_none_match' ) ) === $etag ) {
		$r = new WP_REST_Response( null, 304 );
	} else {
		$r = new WP_REST_Response( $data, 200 );
	}
	$r->header( 'ETag', $etag );
	return $r;
}

function flxapp_rest_manifest( WP_REST_Request $req ) {
	$platform = flxapp_request_platform();
	$mods     = flxapp_user_manifest( null, $platform );
	$hash     = flxapp_manifest_hash( null, $platform );
	return flxapp_etag_response(
		array(
			'schema'   => FLXAPP_MANIFEST_SCHEMA,
			'hash'     => $hash,
			'platform' => '' !== $platform ? $platform : null,
			'modules'  => (object) $mods,
		),
		$hash,
		$req
	);
}

function flxapp_rest_manifest_one( WP_REST_Request $req ) {
	$mods = flxapp_user_manifest();
	$id   = sanitize_key( (string) $req['id'] );
	if ( ! isset( $mods[ $id ] ) ) {
		return new WP_Error( 'flxapp_not_found', __( 'Manifest bulunamadı.', 'flx-app' ), array( 'status' => 404 ) );
	}
	return flxapp_etag_response( $mods[ $id ], substr( md5( wp_json_encode( $mods[ $id ] ) ), 0, 12 ), $req );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route( 'flxapp/v1', '/manifest', array( 'methods' => 'GET', 'callback' => 'flxapp_rest_manifest', 'permission_callback' => 'flxapp_logged_in' ) );
		register_rest_route( 'flxapp/v1', '/manifest/(?P<id>[a-z0-9_\-]+)', array( 'methods' => 'GET', 'callback' => 'flxapp_rest_manifest_one', 'permission_callback' => 'flxapp_logged_in' ) );
	}
);

require_once __DIR__ . '/manifests/core.php';
require_once __DIR__ . '/manifests/woo.php';
require_once __DIR__ . '/manifests/donate.php';
