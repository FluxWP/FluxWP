<?php
/**
 * Uygulama OAuth istemcileri ve white-label kuralları. Masaüstü ve mobil aynı sözleşmeyi kullanır.
 *
 * Yerleşik genel istemciler (ana FluxWP uygulaması, çoklu site; her Flux App sitesinde vardır):
 *   - `flx-mobil`    mobil (Expo):    fluxwp://oauth/callback
 *   - `flx-masaustu` masaüstü (Tauri): http://127.0.0.1:<port>/callback (loopback), flxmasaustu:// ve fluxapp://
 * İstemci platforma kilitli değildir: ikisi de genel şemaları ve loopback'i (RFC 8252 §7.3, port serbest) kabul eder.
 * Marka (white-label) istemcileri: `app_clients` ayarı (preset yazar) + `flxapp_app_clients` filtresi,
 *   [ { id: "flx-mobil-<marka>" | "flx-masaustu-<marka>", name, platform: mobile|desktop, redirect_uris: [...] } ].
 *   Markalı derleme yalnız kendisini tanıyan sitelere bağlanır (tanımayan site client_id'yi reddeder).
 * Site ana uygulamayı kapatabilir (`generic_app` = 0): yalnız kendi markalı uygulaması bağlanır.
 */

defined( 'ABSPATH' ) || exit;

const FLXAPP_MOBILE_CLIENT   = 'flx-mobil';
const FLXAPP_GENERIC_SCHEMES = array( 'fluxwp', 'fluxapp', 'flxmasaustu' );
const FLXAPP_CLIENT_PLATFORMS = array( 'mobile', 'desktop' );

function flxapp_clean_scheme( $s ) {
	$s = strtolower( trim( (string) $s ) );
	return preg_match( '/^[a-z][a-z0-9+.\-]{2,40}$/', $s ) && ! in_array( $s, array( 'http', 'https', 'javascript', 'data', 'file', 'about', 'blob', 'vbscript', 'exp', 'ftp', 'ws', 'wss' ), true ) ? $s : '';
}

function flxapp_clean_app_id( $id ) {
	$id = strtolower( trim( (string) $id ) );
	return preg_match( '/^[a-z0-9][a-z0-9_\-.]{1,99}$/', $id ) ? $id : '';
}

function flxapp_loopback_redirects() {
	return array( 'http://127.0.0.1/callback', 'http://localhost/callback', 'http://[::1]/callback' );
}

/** Marka istemcisinin dönüş adresi geçerli mi: özel şema (genel şemalar hariç) ya da loopback. */
function flxapp_brand_redirect_ok( $uri ) {
	$p = wp_parse_url( (string) $uri );
	if ( ! $p || empty( $p['scheme'] ) || ! empty( $p['fragment'] ) || strlen( (string) $uri ) > 300 ) {
		return false;
	}
	$scheme = strtolower( $p['scheme'] );
	if ( 'http' === $scheme ) {
		return flxapp_is_loopback( $p['host'] ?? '' );
	}
	return '' !== flxapp_clean_scheme( $scheme ) && ! in_array( $scheme, FLXAPP_GENERIC_SCHEMES, true );
}

/** Marka istemcileri (temizlenmiş), kimliğe göre. */
function flxapp_brand_clients() {
	$out = array();
	/**
	 * Marka istemcileri:
	 * `$c[] = [ 'id' => 'flx-mobil-yesilturkiye', 'name' => 'Yeşil Türkiye', 'platform' => 'mobile', 'redirect_uris' => [ 'yesilturkiyeist://oauth/callback' ] ]`.
	 *
	 * @param array $clients
	 */
	$list = (array) apply_filters( 'flxapp_app_clients', (array) flxapp_opt( 'app_clients' ) );
	foreach ( $list as $c ) {
		if ( ! is_array( $c ) ) {
			continue;
		}
		$id   = sanitize_key( (string) ( $c['id'] ?? '' ) );
		$pf   = in_array( $c['platform'] ?? '', FLXAPP_CLIENT_PLATFORMS, true ) ? $c['platform'] : 'mobile';
		$uris = array_values( array_filter( array_map( 'trim', (array) ( $c['redirect_uris'] ?? array() ) ), 'flxapp_brand_redirect_ok' ) );
		if ( '' === $id || ! $uris || in_array( $id, array( FLXAPP_MOBILE_CLIENT, FLXAPP_DESKTOP_CLIENT ), true ) || isset( $out[ $id ] ) ) {
			continue;
		}
		$out[ $id ] = array(
			'id'            => $id,
			'name'          => sanitize_text_field( (string) ( $c['name'] ?? $id ) ),
			'platform'      => $pf,
			'redirect_uris' => array_slice( $uris, 0, 5 ),
			'bundle'        => flxapp_clean_app_id( $c['bundle'] ?? '' ),
			'icon'          => esc_url_raw( (string) ( $c['icon'] ?? '' ) ),
			'secret_hash'   => '',
			'builtin'       => true,
			'dynamic'       => false,
			'native'        => true,
			'brand'         => true,
			'created'       => 0,
		);
	}
	return $out;
}

add_filter(
	'flxapp_builtin_clients',
	function ( $clients ) {
		$loop                            = flxapp_loopback_redirects();
		$clients[ FLXAPP_MOBILE_CLIENT ] = array(
			'id'            => FLXAPP_MOBILE_CLIENT,
			'name'          => __( 'FluxWP Mobil', 'flx-app' ),
			'redirect_uris' => array_merge( array( 'fluxwp://oauth/callback', 'fluxapp://oauth/callback' ), $loop ),
			'secret_hash'   => '',
			'builtin'       => true,
			'dynamic'       => false,
			'native'        => true,
			'generic'       => true,
			'platform'      => 'mobile',
			'created'       => 0,
		);
		if ( isset( $clients[ FLXAPP_DESKTOP_CLIENT ] ) ) {
			$clients[ FLXAPP_DESKTOP_CLIENT ]['redirect_uris'] = array_values( array_unique( array_merge( (array) $clients[ FLXAPP_DESKTOP_CLIENT ]['redirect_uris'], array( 'fluxapp://oauth/callback', 'fluxwp://oauth/callback' ), $loop ) ) );
			$clients[ FLXAPP_DESKTOP_CLIENT ]['native']        = true;
			$clients[ FLXAPP_DESKTOP_CLIENT ]['generic']       = true;
			$clients[ FLXAPP_DESKTOP_CLIENT ]['platform']      = 'desktop';
		}
		return $clients + flxapp_brand_clients();
	}
);

/** Genel uygulama sitede kapalıysa genel istemciler giriş yapamaz ve oturum yenileyemez. */
function flxapp_client_usable( array $client ) {
	return empty( $client['generic'] ) || (bool) flxapp_opt( 'generic_app' );
}

/* Yerel istemcide geliştirici modunda Expo dev client / Expo Go dönüşü: exp://<host>[:port]/--/oauth/callback. */
add_filter(
	'flxapp_redirect_ok',
	function ( $ok, $client, $uri ) {
		if ( $ok || empty( $client['native'] ) || ! flxapp_opt( 'dev_redirects' ) ) {
			return $ok;
		}
		$p = wp_parse_url( (string) $uri );
		return $p && 'exp' === ( $p['scheme'] ?? '' ) && ! empty( $p['host'] ) && '/--/oauth/callback' === ( $p['path'] ?? '' ) && empty( $p['query'] );
	},
	10,
	3
);

/** İsteği yapan uygulamanın kimliği: `X-FluxWP-App` başlığı (bundle id); yoksa oturumun kaydı. */
function flxapp_request_app_id() {
	$id = flxapp_clean_app_id( (string) wp_unslash( $_SERVER['HTTP_X_FLUXWP_APP'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( '' === $id ) {
		$tok = flxapp_current_token();
		$id  = $tok ? (string) ( $tok['app_id'] ?? '' ) : '';
	}
	return $id;
}

/** Oturuma yazılacak uygulama kimliği: markalı istemcide bundle, genelde istemcinin bildirdiği. */
function flxapp_app_id_for( array $client, $given = '' ) {
	if ( ! empty( $client['bundle'] ) ) {
		return (string) $client['bundle'];
	}
	$given = flxapp_clean_app_id( $given );
	return '' !== $given ? $given : flxapp_request_app_id();
}

/** Sitenin etkin preset'i (white-label derlemesi buna göre kilitlenebilir). */
function flxapp_site_preset() {
	$preset = '';
	foreach ( (array) get_option( 'active_plugins', array() ) as $p ) {
		$slug = dirname( (string) $p );
		if ( 0 === strpos( $slug, 'flx-preset-' ) ) {
			$preset = $slug;
			break;
		}
	}
	return (string) apply_filters( 'flxapp_site_preset', $preset );
}
