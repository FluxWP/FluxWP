<?php
/**
 * Menünün bu sitedeki adresleri: yol (/menu/) ve alt alan adı (menu1.alanadi.com), düzenleyenler.
 *
 * Eşleme `flxq_routes` seçeneğinde (autoload) durur: {slugs:{menu:12}, hosts:{"menu1.menu.example.org":12}}.
 * Her istekte yalnız bu dizi okunur; yeniden yazma kuralı (rewrite flush) gerekmez.
 *
 * @package FLX_QR_Menu
 */

defined( 'ABSPATH' ) || exit;

add_action( 'transition_post_status', 'flxq_routes_on_status', 10, 3 );

/** Menülerin kullanamayacağı yollar (WordPress'in kendi adresleri). */
function flxq_reserved_slugs() {
	return (array) apply_filters(
		'flxq_reserved_slugs',
		array( 'wp-admin', 'wp-json', 'wp-login', 'wp-content', 'wp-includes', 'feed', 'admin', 'login', 'xmlrpc', 'tesekkur', 'sitemap', 'robots', 'favicon', 'comments', 'search', 'page', 'author', 'category', 'tag', 'embed', 'trackback' )
	);
}

function flxq_menu_slug( $id ) {
	return (string) get_post_meta( (int) $id, '_flxq_slug', true );
}

function flxq_menu_host( $id ) {
	return (string) get_post_meta( (int) $id, '_flxq_host', true );
}

function flxq_home_host() {
	return strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
}

/** "https://Menu1.Example.org/" → "menu1.example.org"; geçersizse ''. */
function flxq_clean_host( $h ) {
	$h = strtolower( trim( (string) $h ) );
	$h = preg_replace( '#^[a-z]+://#', '', $h );
	$h = preg_replace( '#[/?\#].*$#', '', $h );
	$h = rtrim( $h, '.' );
	if ( '' === $h || strlen( $h ) > 253 || ! preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $h ) ) {
		return '';
	}
	return $h;
}

/** @return array{slugs:array<string,int>,hosts:array<string,int>} */
function flxq_routes() {
	$r = get_option( 'flxq_routes', array() );
	$r = is_array( $r ) ? $r : array();
	return array(
		'slugs' => isset( $r['slugs'] ) && is_array( $r['slugs'] ) ? $r['slugs'] : array(),
		'hosts' => isset( $r['hosts'] ) && is_array( $r['hosts'] ) ? $r['hosts'] : array(),
	);
}

/** Eşlemeyi yayındaki menülerden yeniden kurar. Çakışmada önce açılan menü kazanır. */
function flxq_rebuild_routes() {
	$map = array( 'slugs' => array(), 'hosts' => array() );
	$ids = get_posts(
		array(
			'post_type'      => 'flxq_menu',
			'post_status'    => 'publish',
			'posts_per_page' => 500,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	foreach ( $ids as $id ) {
		$slug = flxq_menu_slug( $id );
		$host = flxq_menu_host( $id );
		if ( '' !== $slug && ! isset( $map['slugs'][ $slug ] ) ) {
			$map['slugs'][ $slug ] = (int) $id;
		}
		if ( '' !== $host && ! isset( $map['hosts'][ $host ] ) ) {
			$map['hosts'][ $host ] = (int) $id;
		}
	}
	if ( flxq_routes() !== $map ) {
		update_option( 'flxq_routes', $map, true );
	}
	return $map;
}

function flxq_routes_on_status( $new, $old, $post ) {
	if ( $post instanceof WP_Post && 'flxq_menu' === $post->post_type && $new !== $old ) {
		flxq_rebuild_routes();
	}
}

/**
 * Menünün açık adresleri (eşlemede bu menüye ait olanlar).
 *
 * @return array<int,array{type:string,url:string}>
 */
function flxq_menu_urls( $id ) {
	$id     = (int) $id;
	$routes = flxq_routes();
	$out    = array();
	$slug   = flxq_menu_slug( $id );
	$host   = flxq_menu_host( $id );
	if ( '' !== $host && isset( $routes['hosts'][ $host ] ) && (int) $routes['hosts'][ $host ] === $id ) {
		$scheme = (string) wp_parse_url( home_url(), PHP_URL_SCHEME );
		$out[]  = array( 'type' => 'host', 'url' => ( $scheme ? $scheme : 'https' ) . '://' . $host . '/' );
	}
	if ( '' !== $slug && isset( $routes['slugs'][ $slug ] ) && (int) $routes['slugs'][ $slug ] === $id ) {
		$out[] = array( 'type' => 'slug', 'url' => home_url( '/' . $slug . '/' ) );
	}
	$s = flxq_settings();
	if ( 'menu' === $s['front_mode'] && $id === $s['front_menu'] ) {
		$out[] = array( 'type' => 'front', 'url' => home_url( '/' ) );
	}
	return $out;
}

/**
 * Yol ve alt alan adını doğrulayıp kaydeder. Boş değer adresi kaldırır.
 *
 * @return array|WP_Error {changed, warnings[], summary}
 */
function flxq_set_address( $id, $slug, $host ) {
	$id   = (int) $id;
	$slug = strtolower( trim( (string) $slug, " \t\n\r\0\x0B/" ) );
	$raw  = trim( (string) $host );
	$host = flxq_clean_host( $raw );
	$warn = array();

	if ( '' !== $slug ) {
		if ( ! preg_match( '/^[a-z0-9](?:[a-z0-9-]{0,58}[a-z0-9])?$/', $slug ) ) {
			return new WP_Error( 'flxq_bad_slug', __( 'Adres yalnız küçük harf (a-z), rakam ve tire içerebilir; tireyle başlayıp bitemez.', 'flx-qr-menu' ), array( 'status' => 400 ) );
		}
		if ( in_array( $slug, flxq_reserved_slugs(), true ) || 0 === strpos( $slug, 'wp-' ) ) {
			/* translators: %s: path */
			return new WP_Error( 'flxq_reserved_slug', sprintf( __( '"%s" WordPress tarafından kullanılıyor, başka bir adres seçin.', 'flx-qr-menu' ), $slug ), array( 'status' => 400 ) );
		}
		$routes = flxq_routes();
		if ( isset( $routes['slugs'][ $slug ] ) && (int) $routes['slugs'][ $slug ] !== $id ) {
			/* translators: 1: path, 2: menu name */
			return new WP_Error( 'flxq_slug_taken', sprintf( __( '/%1$s/ adresi "%2$s" menüsünde kullanılıyor.', 'flx-qr-menu' ), $slug, get_the_title( $routes['slugs'][ $slug ] ) ), array( 'status' => 409 ) );
		}
		$clash = get_posts(
			array(
				'name'           => $slug,
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		if ( $clash ) {
			/* translators: 1: path, 2: page title */
			$warn[] = sprintf( __( '/%1$s/ adresinde "%2$s" sayfası da var. Bu adreste artık menü açılır; sayfaya ulaşmak için sayfanın adresini değiştirin.', 'flx-qr-menu' ), $slug, get_the_title( $clash[0] ) );
		}
	}

	if ( '' !== $raw && '' === $host ) {
		return new WP_Error( 'flxq_bad_host', __( 'Alt alan adı geçerli değil (ör. menu1.menu.example.org).', 'flx-qr-menu' ), array( 'status' => 400 ) );
	}
	if ( '' !== $host ) {
		$home = flxq_home_host();
		if ( $host === $home || 'www.' . $home === $host || 'www.' . $host === $home ) {
			return new WP_Error( 'flxq_bad_host', __( 'Sitenin kendi alan adı menüye verilemez. Ana sayfada menü göstermek için QR Menüler › Ayarlar › Ana sayfa seçeneğini kullanın.', 'flx-qr-menu' ), array( 'status' => 400 ) );
		}
		$routes = flxq_routes();
		if ( isset( $routes['hosts'][ $host ] ) && (int) $routes['hosts'][ $host ] !== $id ) {
			/* translators: 1: host name, 2: menu name */
			return new WP_Error( 'flxq_host_taken', sprintf( __( '%1$s "%2$s" menüsünde kullanılıyor.', 'flx-qr-menu' ), $host, get_the_title( $routes['hosts'][ $host ] ) ), array( 'status' => 409 ) );
		}
		if ( $home && substr( $host, -strlen( '.' . $home ) ) !== '.' . $home ) {
			/* translators: %s: site domain */
			$warn[] = sprintf( __( 'Bu alan adı %s altında değil. Alan adının bu siteyle aynı klasöre yönlendiğinden ve SSL sertifikasının onu da kapsadığından emin olun.', 'flx-qr-menu' ), $home );
		}
	}

	$old_slug = flxq_menu_slug( $id );
	$old_host = flxq_menu_host( $id );
	$lines    = array();
	if ( $slug !== $old_slug ) {
		if ( '' === $slug ) {
			$lines[] = __( 'Adres kaldırıldı', 'flx-qr-menu' );
			delete_post_meta( $id, '_flxq_slug' );
		} else {
			/* translators: %s: path like /menu/ */
			$lines[] = sprintf( __( 'Adres: %s', 'flx-qr-menu' ), '/' . $slug . '/' );
			update_post_meta( $id, '_flxq_slug', $slug );
		}
	}
	if ( $host !== $old_host ) {
		if ( '' === $host ) {
			$lines[] = __( 'Alt alan adı kaldırıldı', 'flx-qr-menu' );
			delete_post_meta( $id, '_flxq_host' );
		} else {
			/* translators: %s: host name */
			$lines[] = sprintf( __( 'Alt alan adı: %s', 'flx-qr-menu' ), $host );
			update_post_meta( $id, '_flxq_host', $host );
		}
	}
	if ( ! $lines ) {
		return array( 'changed' => false, 'warnings' => $warn, 'summary' => '' );
	}
	flxq_rebuild_routes();
	$summary = implode( '; ', $lines );
	flxq_history_add( $id, flxq_menu_version( $id ), 'address', $summary, null );
	return array( 'changed' => true, 'warnings' => $warn, 'summary' => $summary );
}

/**
 * Menüyü düzenleyebilecek menü yöneticilerini belirler (yalnız tüm menüleri yöneten).
 *
 * @param int[] $uids Kullanıcı kimlikleri.
 * @return array {changed, summary}
 */
function flxq_set_editors( $id, $uids ) {
	$id    = (int) $id;
	$clean = array();
	foreach ( (array) $uids as $u ) {
		$u = (int) $u;
		if ( $u > 0 && get_userdata( $u ) && user_can( $u, FLXQ_EDIT_CAP ) && ! in_array( $u, $clean, true ) ) {
			$clean[] = $u;
		}
	}
	sort( $clean );
	$old = flxq_menu_editors( $id );
	sort( $old );
	if ( $clean === $old ) {
		return array( 'changed' => false, 'summary' => '' );
	}
	$names = function ( $ids ) {
		return implode( ', ', array_map( 'flxq_user_name', $ids ) );
	};
	$lines = array();
	$add   = array_diff( $clean, $old );
	$del   = array_diff( $old, $clean );
	if ( $add ) {
		/* translators: %s: user names */
		$lines[] = sprintf( __( 'Düzenleyen eklendi: %s', 'flx-qr-menu' ), $names( $add ) );
	}
	if ( $del ) {
		/* translators: %s: user names */
		$lines[] = sprintf( __( 'Düzenleyen çıkarıldı: %s', 'flx-qr-menu' ), $names( $del ) );
	}
	if ( $clean ) {
		update_post_meta( $id, '_flxq_editors', $clean );
	} else {
		delete_post_meta( $id, '_flxq_editors' );
	}
	$summary = implode( '; ', $lines );
	flxq_history_add( $id, flxq_menu_version( $id ), 'editors', $summary, null );
	return array( 'changed' => true, 'summary' => $summary );
}
