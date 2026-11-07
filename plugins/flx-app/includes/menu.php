<?php
/**
 * Rol bazlı menü: uygulama, kullanıcının wp-admin'de gördüğü sol menünün aynısını gösterir.
 *
 * WordPress menüyü yalnız wp-admin'de kurar. Bu yüzden:
 * - Kullanıcı wp-admin'i her açtığında kendi (yetkiye göre süzülmüş) menüsü user meta'ya yazılır (değiştiyse).
 * - Yönetici açtığında tüm öğeler yetki adlarıyla katalog olarak saklanır; hiç panele girmemiş
 *   kullanıcıya katalog, yetkilerine göre süzülerek verilir.
 * Uygulamada yerel (native) ekranı olan modüller `flxapp_modules` filtresiyle ayrıca kaydolur.
 */

defined( 'ABSPATH' ) || exit;

function flxapp_menu_url( $slug, $parent = '' ) {
	$slug = html_entity_decode( (string) $slug );
	if ( preg_match( '#^https?://#', $slug ) ) {
		return $slug;
	}
	if ( false !== strpos( $slug, '.php' ) ) {
		return admin_url( $slug );
	}
	$parent = html_entity_decode( (string) $parent );
	if ( '' !== $parent && false !== strpos( $parent, '.php' ) && 'admin.php' !== $parent ) {
		return admin_url( $parent . ( false === strpos( $parent, '?' ) ? '?' : '&' ) . 'page=' . rawurlencode( $slug ) );
	}
	return admin_url( 'admin.php?page=' . rawurlencode( $slug ) );
}

/** Menü başlığındaki rozeti ayırır: "Görevler <span class=…>3</span>" → [ "Görevler", 3 ]. */
function flxapp_menu_title( $html ) {
	$badge = 0;
	if ( preg_match( '#<span[^>]*>\s*(?:<span[^>]*>)?\s*([0-9][0-9.,]*)#', (string) $html, $m ) ) {
		$badge = (int) preg_replace( '/\D/', '', $m[1] );
	}
	$title = trim( wp_strip_all_tags( preg_replace( '#<span.*$#s', '', (string) $html ) ) );
	return array( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ), $badge );
}

function flxapp_menu_icon( $icon ) {
	$icon = (string) $icon;
	return 0 === strpos( $icon, 'dashicons-' ) ? $icon : '';
}

/** Global $menu/$submenu → düz yapı. $with_caps katalog için yetki adlarını da yazar. */
function flxapp_menu_from_globals( $with_caps = false ) {
	global $menu, $submenu;
	$out = array();
	foreach ( (array) $menu as $item ) {
		if ( empty( $item[0] ) || empty( $item[2] ) || false !== strpos( (string) ( $item[4] ?? '' ), 'wp-menu-separator' ) ) {
			continue;
		}
		list( $title, $badge ) = flxapp_menu_title( $item[0] );
		if ( '' === $title ) {
			continue;
		}
		$slug = (string) $item[2];
		$row  = array(
			'id'    => sanitize_title( $slug ),
			'slug'  => $slug,
			'title' => $title,
			'url'   => flxapp_menu_url( $slug ),
			'icon'  => flxapp_menu_icon( $item[6] ?? '' ),
			'badge' => $badge,
			'items' => array(),
		);
		if ( $with_caps ) {
			$row['cap'] = (string) $item[1];
		}
		foreach ( (array) ( $submenu[ $slug ] ?? array() ) as $sub ) {
			if ( empty( $sub[0] ) || empty( $sub[2] ) ) {
				continue;
			}
			list( $st, $sb ) = flxapp_menu_title( $sub[0] );
			if ( '' === $st ) {
				continue;
			}
			$s = array(
				'slug'  => (string) $sub[2],
				'title' => $st,
				'url'   => flxapp_menu_url( $sub[2], $slug ),
				'badge' => $sb,
			);
			if ( $with_caps ) {
				$s['cap'] = (string) $sub[1];
			}
			$row['items'][] = $s;
		}
		// Ana öğenin adresi ilk alt öğeyle aynı olur (WordPress davranışı).
		if ( $row['items'] && $row['items'][0]['slug'] === $slug ) {
			$row['url'] = $row['items'][0]['url'];
		}
		$out[] = $row;
	}
	return $out;
}

/* Menü basıldıktan sonra (yetkiye göre süzülmüş hâli) sakla. */
add_action(
	'adminmenu',
	function () {
		$uid = get_current_user_id();
		if ( ! $uid ) {
			return;
		}
		$mine = flxapp_menu_from_globals( false );
		$hash = md5( wp_json_encode( $mine ) );
		if ( get_user_meta( $uid, 'flxapp_menu_hash', true ) !== $hash ) {
			update_user_meta( $uid, 'flxapp_menu', array( 'at' => time(), 'items' => $mine ) );
			update_user_meta( $uid, 'flxapp_menu_hash', $hash );
		}
		if ( current_user_can( 'manage_options' ) ) {
			$cat  = flxapp_menu_from_globals( true );
			$hash = md5( wp_json_encode( $cat ) );
			if ( get_option( 'flxapp_menu_catalog_hash' ) !== $hash ) {
				update_option( 'flxapp_menu_catalog', array( 'at' => time(), 'items' => $cat ), false );
				update_option( 'flxapp_menu_catalog_hash', $hash, false );
			}
		}
	}
);

/**
 * Kullanıcının menüsü. Kendi kaydı katalogdan yeniyse o; değilse katalog yetkiye göre süzülür.
 * Rozetler (okunmamış vb.) Flux Admin sayaçlarından canlı gelir (`counts`).
 */
function flxapp_user_menu( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return array();
	}
	$mine = get_user_meta( $user_id, 'flxapp_menu', true );
	$cat  = get_option( 'flxapp_menu_catalog', array() );
	if ( is_array( $mine ) && ! empty( $mine['items'] ) && (int) $mine['at'] >= (int) ( $cat['at'] ?? 0 ) - DAY_IN_SECONDS ) {
		$items = $mine['items'];
	} else {
		$items = array();
		foreach ( (array) ( $cat['items'] ?? array() ) as $row ) {
			if ( ! user_can( $user, $row['cap'] ) ) {
				continue;
			}
			$subs = array();
			foreach ( $row['items'] as $s ) {
				if ( user_can( $user, $s['cap'] ) ) {
					unset( $s['cap'] );
					$subs[] = $s;
				}
			}
			unset( $row['cap'] );
			$row['items'] = $subs;
			$items[]      = $row;
		}
	}
	return (array) apply_filters( 'flxapp_user_menu', $items, $user );
}

/**
 * Uygulamada yerel ekranı olan modüller. Paketler kendini ekler:
 *
 *   add_filter( 'flxapp_modules', function ( $m ) {
 *       $m['studio'] = array( 'title' => 'Stüdyo', 'cap' => 'flxst_use', 'api' => rest_url( 'flx-studio/v1' ),
 *                             'menu' => 'flx-studio', 'icon' => 'image', 'version' => '1.0.0' );
 *       return $m;
 *   } );
 *
 * `menu`: wp-admin menü slug'ı (uygulama o menü öğesini yerel ekranla açar), `api`: REST kökü,
 * `url` (isteğe bağlı; yoksa `menu`'den): yerel ekranı olmayan uygulama sürümünün açacağı panel sayfası.
 */
function flxapp_user_modules( $user_id ) {
	$user     = get_userdata( $user_id );
	$out      = array();
	$disabled = (array) flxapp_opt( 'modules_off' );
	$off      = $user ? flxapp_platform_off_slugs( $user, flxapp_request_platform() ) : array();
	foreach ( (array) apply_filters( 'flxapp_modules', array() ) as $id => $m ) {
		if ( ! $user || empty( $m['title'] ) || ( ! empty( $m['cap'] ) && ! user_can( $user, $m['cap'] ) ) ) {
			continue;
		}
		// Ayarlardan kapatılan modül ya da bu platformda menüsü kapalı modül uygulamada yer almaz.
		if ( in_array( sanitize_key( $id ), $disabled, true ) || ( ! empty( $m['menu'] ) && in_array( (string) $m['menu'], $off, true ) ) ) {
			continue;
		}
		unset( $m['cap'] );
		// Uygulamanın yerel ekranı olmayan sürümleri modülü panel sayfası olarak açar.
		if ( empty( $m['url'] ) && ! empty( $m['menu'] ) ) {
			$m['url'] = flxapp_menu_url( $m['menu'] );
		}
		$out[ sanitize_key( $id ) ] = $m;
	}
	return $out;
}

/* ───────── Platform: menü öğesi web / mobil / masaüstünde görünsün mü (Flux Admin menü düzenleyici) ───────── */

add_filter(
	'flxa_menu_platforms',
	function ( $p ) {
		return (array) $p + flxapp_platforms();
	}
);

function flxapp_platforms() {
	return array(
		'web'     => __( 'Web', 'flx-app' ),
		'mobile'  => __( 'Mobil', 'flx-app' ),
		'desktop' => __( 'Masaüstü', 'flx-app' ),
	);
}

/**
 * İsteği yapan uygulamanın platformu: `X-FluxWP-Platform` başlığı ya da `?platform=` (`mobile` | `desktop`;
 * `ios`/`android` → mobile, `macos`/`windows`/`linux` → desktop). Bilinmiyorsa boş: süzme yok, iki düzen de döner.
 */
function flxapp_request_platform() {
	$p = sanitize_key( wp_unslash( $_SERVER['HTTP_X_FLUXWP_PLATFORM'] ?? $_SERVER['HTTP_X_FLX_PLATFORM'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( '' === $p && isset( $_GET['platform'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$p = sanitize_key( wp_unslash( $_GET['platform'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	$map = array( 'ios' => 'mobile', 'android' => 'mobile', 'macos' => 'desktop', 'windows' => 'desktop', 'linux' => 'desktop' );
	$p   = $map[ $p ] ?? $p;
	return in_array( $p, array( 'mobile', 'desktop' ), true ) ? $p : '';
}

/** Rol düzeninde bu platformda kapalı menü slug'ları. */
function flxapp_platform_off_slugs( $user, $platform ) {
	if ( '' === $platform || ! function_exists( 'flxa_menu_platform_off' ) ) {
		return array();
	}
	$off = array();
	foreach ( (array) flxa_menu_platform_off( $user ) as $slug => $pfs ) {
		if ( in_array( $platform, (array) $pfs, true ) ) {
			$off[] = (string) $slug;
		}
	}
	return $off;
}

add_filter(
	'flxapp_user_menu',
	function ( $items, $user ) {
		$off = flxapp_platform_off_slugs( $user, flxapp_request_platform() );
		if ( ! $off ) {
			return $items;
		}
		return array_values( array_filter( $items, fn( $row ) => ! in_array( (string) ( $row['slug'] ?? '' ), $off, true ) ) );
	},
	10,
	2
);
