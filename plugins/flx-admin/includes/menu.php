<?php
/**
 * Rol bazlı sol menü: sıralama, gruplar (başlıklı ayraçlar), yeniden adlandırma, gizleme, alt menü gizleme.
 * Gizlemek yalnızca menüden kaldırır; WordPress yetkileri değişmez (adresi bilen yetkili kişi sayfayı açabilir).
 *
 * Seçenek `flxa_menus` = { roles: { <rol | _default>: { entries: [...], bottom: [slug ×4] } } }
 *   entries: { t: 'g', id, label } | { t: 'i', slug, label?, hidden?, sub_hidden?: [slug], pf_off?: [platform] }
 *   pf_off: öğenin gösterilmeyeceği platformlar (web / mobile / desktop; filtre `flxa_menu_platforms`).
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'flxa_menu_apply', PHP_INT_MAX - 5 );
add_filter( 'custom_menu_order', 'flxa_menu_custom_order', PHP_INT_MAX );
add_filter( 'menu_order', 'flxa_menu_order', PHP_INT_MAX );

function flxa_menu_state( $key = null, $value = null ) {
	static $state = array(
		'order'  => array(),
		'groups' => array(),
		'icons'  => array(),
		'all'    => array(),
	);
	if ( null !== $value ) {
		$state[ $key ] = $value;
	}
	return null === $key ? $state : ( $state[ $key ] ?? null );
}

function flxa_is_separator( $item ) {
	return isset( $item[4] ) && false !== strpos( (string) $item[4], 'wp-menu-separator' );
}

/**
 * Varsayılan gruplar (kayıtlı düzen yoksa): [ id => [ ad, [ menü slug'ları ], kapalı mı ] ].
 * Sık kullanılanlar ayrıca üstte kutucuk olarak sabitlenir (flxa_default_pins). Filtre `flxa_default_groups`.
 */
function flxa_default_groups() {
	return (array) apply_filters(
		'flxa_default_groups',
		array(
			'general' => array( __( 'Genel', 'flx-admin' ), array( 'index.php' ), false ),
			'comms'   => array( __( 'İletişim', 'flx-admin' ), array( 'flx-chat', 'flxi-posta', 'flx-tasks', 'flxi' ), false ),
			'donate'  => array( __( 'Bağış', 'flx-admin' ), array( 'flxb-dashboard', 'flxs', 'Emsfb', 'woocommerce', 'wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM', 'flx-wc-bagis', 'edit.php?post_type=product' ), false ),
			'content' => array( __( 'İçerik', 'flx-admin' ), array( 'edit.php?post_type=page', 'edit.php', 'edit.php?post_type=proje', 'edit.php?post_type=etkinlik', 'upload.php', 'edit-comments.php', 'flx-studio' ), false ),
			'growth'  => array( __( 'Büyüme', 'flx-admin' ), array( 'woocommerce-marketing', 'flx-social', 'flxseo', 'flx-analytics', 'wc-admin&path=/analytics/overview', 'googlesitekit-dashboard' ), true ),
			'screens' => array( __( 'Ekranlar', 'flx-admin' ), array( 'flx-tv-board', 'flx-tv-remote', 'flxapp-settings' ), true ),
			'system'  => array( __( 'Sistem', 'flx-admin' ), array( 'flx-updater', 'flx-backup', 'flx-speed', 'flx-ai', 'flx-roles', 'plugins.php', 'themes.php', 'users.php', 'options-general.php', 'tools.php', 'hostinger', 'litespeed' ), true ),
		)
	);
}

/** Kişi henüz bir şey sabitlemediyse üstte kutucuk olarak gelenler. Filtre `flxa_default_pins`. */
function flxa_default_pins() {
	return array_map( 'strval', (array) apply_filters( 'flxa_default_pins', array( 'index.php', 'flx-chat', 'flx-tasks', 'flxb-dashboard' ) ) );
}

/** Kişinin sabitledikleri; hiç düzenlemediyse varsayılanlar. */
function flxa_user_pins( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( $user_id && ! metadata_exists( 'user', $user_id, 'flxa_pins' ) ) {
		return flxa_default_pins();
	}
	return flxa_user_list( 'flxa_pins', $user_id );
}

/** Verilen menü sırasından varsayılan düzen üretir. */
function flxa_default_entries( array $slugs ) {
	$groups = flxa_default_groups();
	$bucket = array();
	foreach ( $groups as $gid => $g ) {
		$bucket[ $gid ] = array();
	}
	$other = array();
	foreach ( $slugs as $slug ) {
		$placed = false;
		foreach ( $groups as $gid => $g ) {
			if ( in_array( $slug, $g[1], true ) ) {
				$bucket[ $gid ][ array_search( $slug, $g[1], true ) ] = $slug;
				$placed = true;
				break;
			}
		}
		if ( ! $placed ) {
			$other[] = $slug;
		}
	}
	$entries = array();
	foreach ( $groups as $gid => $g ) {
		if ( ! $bucket[ $gid ] ) {
			continue;
		}
		ksort( $bucket[ $gid ] );
		$entries[] = array( 't' => 'g', 'id' => $gid, 'label' => $g[0], 'c' => empty( $g[2] ) ? 0 : 1 );
		foreach ( $bucket[ $gid ] as $slug ) {
			$entries[] = array( 't' => 'i', 'slug' => $slug );
		}
	}
	if ( $other ) {
		$entries[] = array( 't' => 'g', 'id' => 'other', 'label' => __( 'Diğer', 'flx-admin' ), 'c' => 1 );
		foreach ( $other as $slug ) {
			$entries[] = array( 't' => 'i', 'slug' => $slug );
		}
	}
	return $entries;
}

/**
 * Menü öğesi başına seçilebilen platformlar: [ anahtar => ad ]. Uygulama paketi (Flux App) doldurur:
 *   add_filter( 'flxa_menu_platforms', fn( $p ) => $p + array( 'web' => 'Web', 'mobile' => 'Mobil', 'desktop' => 'Masaüstü' ) );
 * Boşsa menü düzenleyicide platform düğmeleri çıkmaz.
 */
function flxa_menu_platforms() {
	return array_map( 'strval', (array) apply_filters( 'flxa_menu_platforms', array() ) );
}

/** Kullanıcının rol düzeninde platforma göre gizlenen öğeler: [ menü slug'ı => [ platform, … ] ]. */
function flxa_menu_platform_off( $user = null ) {
	$out = array();
	foreach ( (array) ( flxa_menu_config_for_user( $user )['entries'] ?? array() ) as $e ) {
		if ( is_array( $e ) && ! empty( $e['slug'] ) && ! empty( $e['pf_off'] ) ) {
			$out[ (string) $e['slug'] ] = array_values( array_map( 'strval', (array) $e['pf_off'] ) );
		}
	}
	return $out;
}

function flxa_menus_option() {
	$o = get_option( 'flxa_menus', array() );
	return is_array( $o ) ? $o : array();
}

/** Kullanıcının rolüne ait düzen (ilk eşleşen rol, sonra "_default"). Boş dizi = varsayılan düzen. */
function flxa_menu_config_for_user( $user = null ) {
	$user = $user ? $user : wp_get_current_user();
	$all  = flxa_menus_option();
	$keys = array_merge( (array) ( $user->roles ?? array() ), array( '_default' ) );
	foreach ( $keys as $r ) {
		if ( ! empty( $all['roles'][ $r ] ) && is_array( $all['roles'][ $r ] ) ) {
			return $all['roles'][ $r ];
		}
	}
	return array();
}

/** Yöneticinin gördüğü menünün kopyası (menü düzenleyici bunu kullanır). */
function flxa_catalog_snapshot() {
	global $menu, $submenu;
	$items = array();
	foreach ( (array) $menu as $it ) {
		if ( flxa_is_separator( $it ) || empty( $it[2] ) ) {
			continue;
		}
		list( $label ) = flxa_split_label( $it[0] );
		$subs          = array();
		foreach ( (array) ( $submenu[ $it[2] ] ?? array() ) as $s ) {
			list( $sl ) = flxa_split_label( $s[0] );
			$subs[]     = array(
				'slug'  => (string) $s[2],
				'label' => $sl,
				'cap'   => (string) $s[1],
			);
		}
		$items[] = array(
			'slug'  => (string) $it[2],
			'label' => '' !== $label ? $label : (string) $it[2],
			'cap'   => (string) $it[1],
			'icon'  => flxa_icon_for_slug( $it[2] ),
			'url'   => flxa_menu_url( $it[2] ),
			'sub'   => $subs,
		);
	}
	$hash = md5( wp_json_encode( $items ) . get_user_locale() );
	$old  = get_option( 'flxa_menu_catalog', array() );
	if ( ! is_array( $old ) || ( $old['hash'] ?? '' ) !== $hash ) {
		update_option(
			'flxa_menu_catalog',
			array(
				'hash'  => $hash,
				'at'    => time(),
				'items' => $items,
			),
			false
		);
	}
}

function flxa_menu_catalog() {
	$c = get_option( 'flxa_menu_catalog', array() );
	return is_array( $c ) && ! empty( $c['items'] ) ? $c['items'] : array();
}

/** Kişisel menü listeleri: `flxa_pins` (üste sabitlenenler, sıralı), `flxa_away` ("Diğer"e taşınanlar). */
function flxa_user_list( $key, $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$v       = $user_id ? get_user_meta( $user_id, $key, true ) : array();
	return is_array( $v ) ? array_values( array_unique( array_filter( array_map( 'strval', $v ) ) ) ) : array();
}

/** Grup anahtarı (katlama durumu kişi başı bu anahtarla saklanır). */
function flxa_group_key( $g ) {
	$id = (string) ( $g['id'] ?? '' );
	if ( in_array( $id, array( 'general', 'apps', 'comms', 'donate', 'content', 'growth', 'screens', 'system', 'site', 'other', 'pinned' ), true ) ) {
		return $id;
	}
	$label = (string) ( $g['label'] ?? '' );
	if ( __( 'Diğer', 'flx-admin' ) === $label ) {
		return 'other';
	}
	$k = sanitize_title( $label );
	return '' !== $k ? $k : ( '' !== $id ? sanitize_key( $id ) : 'g' );
}

/** Erişimi engellenemeyen menüler (kilitlenme olmasın). */
function flxa_unblockable() {
	return array( 'index.php', 'profile.php' );
}

function flxa_menu_apply() {
	global $menu, $submenu;
	if ( ! flxa_enabled() || ! is_array( $menu ) ) {
		return;
	}
	flxa_menu_merge_duplicates();
	if ( current_user_can( 'manage_options' ) ) {
		flxa_catalog_snapshot();
	}

	/* Tüm öğeler (alt bar ve palet için; gizlenenler dahil). */
	$present = array();
	$all     = array();
	foreach ( $menu as $k => $it ) {
		if ( flxa_is_separator( $it ) ) {
			unset( $menu[ $k ] );
			continue;
		}
		if ( empty( $it[2] ) ) {
			continue;
		}
		if ( empty( $it[5] ) ) {
			$menu[ $k ][5] = 'flxa-m-' . substr( md5( $it[2] ), 0, 10 );
		}
		$present[ $it[2] ] = $k;
		list( $label )     = flxa_split_label( $it[0] );
		$all[ $it[2] ]     = array(
			'label' => $label,
			'cap'   => (string) $it[1],
		);
	}
	flxa_menu_state( 'all', $all );

	$cfg     = flxa_menu_config_for_user();
	$entries = ! empty( $cfg['entries'] ) && is_array( $cfg['entries'] ) ? $cfg['entries'] : flxa_default_entries( array_keys( $present ) );
	$lines   = (bool) flxa_setting( 'line_icons' );

	/* 1) Rol düzenine göre normal sıra: [ gruplar => [ başlık, öğeler ] ]. */
	$groups  = array();
	$gorder  = array();
	$blocked = array();
	$used    = array();
	$cur     = null;
	$open_group = function ( $g ) use ( &$groups, &$gorder, &$cur ) {
		$key  = flxa_group_key( $g );
		$base = $key;
		$n    = 1;
		while ( isset( $groups[ $key ] ) ) {
			$key = $base . '-' . ( ++$n );
		}
		$groups[ $key ] = array(
			'label'     => (string) ( $g['label'] ?? '' ),
			'collapsed' => isset( $g['c'] ) ? (bool) $g['c'] : 'other' === $base,
			'items'     => array(),
		);
		$gorder[] = $key;
		$cur      = $key;
	};
	$visit = function ( $slug, $e ) use ( &$menu, &$submenu, &$groups, &$used, &$blocked, &$cur, $present, $open_group ) {
		$k             = $present[ $slug ];
		$used[ $slug ] = true;
		if ( ! empty( $e['blocked'] ) && ! in_array( $slug, flxa_unblockable(), true ) ) {
			$blocked[ $slug ] = wp_list_pluck( (array) ( $submenu[ $slug ] ?? array() ), 2 );
			unset( $menu[ $k ] );
			return;
		}
		if ( ! empty( $e['hidden'] ) ) {
			unset( $menu[ $k ] );
			return;
		}
		// Yalnız uygulamada görünecek öğe: menüden silinmez (uygulama menüsü bu listeden okunur), panelde gizlenir.
		if ( in_array( 'web', (array) ( $e['pf_off'] ?? array() ), true ) ) {
			$menu[ $k ][4] = trim( ( $menu[ $k ][4] ?? '' ) . ' flxa-noweb' );
		}
		$can = current_user_can( $menu[ $k ][1] );
		foreach ( (array) ( $submenu[ $slug ] ?? array() ) as $s ) {
			$can = $can || current_user_can( $s[1] );
		}
		if ( ! $can ) {
			return; // WordPress zaten gizleyecek; boş grup başlığı açmasın.
		}
		if ( null === $cur ) {
			$open_group( array( 'id' => 'general', 'label' => '' ) );
		}
		$groups[ $cur ]['items'][ $slug ] = $e;
	};

	foreach ( $entries as $e ) {
		if ( ! is_array( $e ) ) {
			continue;
		}
		if ( 'g' === ( $e['t'] ?? '' ) ) {
			$open_group( $e );
			continue;
		}
		$slug = (string) ( $e['slug'] ?? '' );
		if ( '' === $slug || ! isset( $present[ $slug ] ) || isset( $used[ $slug ] ) ) {
			continue;
		}
		$visit( $slug, $e );
	}
	/* Düzende olmayan (sonradan kurulan) menüler "Diğer" altına. */
	$left = array_diff_key( $present, $used );
	if ( $left ) {
		$other = null;
		foreach ( $gorder as $gk ) {
			if ( 'other' === $gk ) {
				$other = $gk;
			}
		}
		if ( $other ) {
			$cur = $other;
		} else {
			$open_group( array( 'id' => 'other', 'label' => __( 'Diğer', 'flx-admin' ) ) );
		}
		/* Diğer: önce bizim paketlerimiz, sonra ada göre. */
		uksort(
			$left,
			function ( $a, $b ) use ( $all ) {
				$ya = flxa_is_flx_slug( $a ) ? 0 : 1;
				$yb = flxa_is_flx_slug( $b ) ? 0 : 1;
				return $ya !== $yb ? $ya - $yb : strcasecmp( (string) ( $all[ $a ]['label'] ?? $a ), (string) ( $all[ $b ]['label'] ?? $b ) );
			}
		);
		foreach ( array_keys( $left ) as $slug ) {
			$visit( $slug, array() );
		}
	}
	flxa_menu_state( 'blocked', $blocked );

	/* Normal sıradaki komşular: sabitleme / Diğer'e taşıma geri alınınca öğe yerine döner. */
	$prev = array();
	$last = '';
	foreach ( $gorder as $gk ) {
		if ( ! $groups[ $gk ]['items'] ) {
			continue;
		}
		$last = 'flxa-g-' . $gk;
		foreach ( array_keys( $groups[ $gk ]['items'] ) as $slug ) {
			$prev[ $slug ] = $last;
			$last          = flxa_menu_dom_id( $menu[ $present[ $slug ] ][5] );
		}
	}

	/* 2) Kişisel sabitlenenler ve "Diğer"e taşınanlar. */
	$pins = array_values( array_filter( flxa_user_pins(), function ( $s ) use ( $prev ) { return isset( $prev[ $s ] ); } ) );
	$away = array_values( array_diff( array_filter( flxa_user_list( 'flxa_away' ), function ( $s ) use ( $prev ) { return isset( $prev[ $s ] ); } ), $pins ) );
	$find = function ( $slug ) use ( $groups, $gorder ) {
		foreach ( $gorder as $gk ) {
			if ( isset( $groups[ $gk ]['items'][ $slug ] ) ) {
				return array( $gk, $groups[ $gk ]['items'][ $slug ] );
			}
		}
		return array( '', array() );
	};
	$pinned = array();
	foreach ( array_merge( $pins, $away ) as $slug ) {
		list( $gk, $e ) = $find( $slug );
		unset( $groups[ $gk ]['items'][ $slug ] );
		if ( in_array( $slug, $pins, true ) ) {
			$pinned[ $slug ] = $e;
		} else {
			if ( ! isset( $groups['other'] ) ) {
				$groups['other'] = array( 'label' => __( 'Diğer', 'flx-admin' ), 'collapsed' => true, 'items' => array() );
				$gorder[]        = 'other';
			}
			$groups['other']['items'][ $slug ] = $e;
		}
	}
	if ( $pinned ) {
		$groups = array( 'pinned' => array( 'label' => __( 'Sabitlenenler', 'flx-admin' ), 'collapsed' => false, 'items' => $pinned ) ) + $groups;
		array_unshift( $gorder, 'pinned' );
	}

	/* 3) WordPress menüsüne uygula. */
	$order  = array();
	$gstate = array();
	$icons   = array();
	$ids     = array();
	$logos   = array();
	$letters = array();
	foreach ( $gorder as $gk ) {
		$g = $groups[ $gk ];
		if ( ! $g['items'] ) {
			continue;
		}
		$id     = 'flxa-g-' . $gk;
		$slug   = 'separator-' . $id;
		$menu[] = array( '', 'read', $slug, '', 'wp-menu-separator flxa-group', $id );
		$order[]       = $slug;
		$gstate[ $id ] = array(
			'key'       => $gk,
			'label'     => $g['label'],
			'collapsed' => (bool) $g['collapsed'],
			'n'         => count( $g['items'] ),
			'icon'      => flxa_icon( flxa_group_icon( $gk, (string) $g['label'], (string) array_key_first( $g['items'] ) ), 19 ),
		);
		foreach ( $g['items'] as $slug => $e ) {
			$k = $present[ $slug ];
			if ( ! empty( $e['label'] ) ) {
				list( , $badge ) = flxa_split_label( $menu[ $k ][0] );
				$menu[ $k ][0]   = esc_html( $e['label'] ) . ( '' !== $badge ? ' ' . $badge : '' );
			}
			if ( ! empty( $e['sub_hidden'] ) && ! empty( $submenu[ $slug ] ) ) {
				foreach ( $submenu[ $slug ] as $si => $s ) {
					if ( in_array( (string) $s[2], (array) $e['sub_hidden'], true ) ) {
						unset( $submenu[ $slug ][ $si ] );
					}
				}
			}
			$icon  = flxa_icon_for_slug( $slug );
			$dom   = flxa_menu_dom_id( $menu[ $k ][5] );
			$logo  = flxa_logo_for_slug( $slug );
			if ( '' !== $logo ) {
				/* FluxWP paketleri: her zaman ortak renkli logo. */
				$menu[ $k ][4]  = trim( ( $menu[ $k ][4] ?? '' ) . ' flxa-logo' );
				$logos[ $dom ]  = $logo;
			} elseif ( $lines && $icon && ! flxa_is_flx_slug( $slug ) ) {
				$menu[ $k ][4]  = trim( ( $menu[ $k ][4] ?? '' ) . ' flxa-lic flxa-lic-' . $icon );
				$icons[ $icon ] = true;
			} elseif ( flxa_menu_icon_missing( (string) ( $menu[ $k ][6] ?? '' ) ) ) {
				/* Simgesiz eklenti: addan tahmin edilen çizgi simge, o da yoksa baş harf. */
				list( $plain ) = flxa_split_label( $menu[ $k ][0] );
				$guess         = $icon ? $icon : flxa_guess_icon( $slug, $plain );
				if ( '' !== $guess ) {
					$menu[ $k ][4]   = trim( ( $menu[ $k ][4] ?? '' ) . ' flxa-lic flxa-lic-' . $guess );
					$icons[ $guess ] = true;
				} else {
					$menu[ $k ][4]   = trim( ( $menu[ $k ][4] ?? '' ) . ' flxa-letter' );
					$letters[ $dom ] = flxa_initial( $plain );
				}
			}
			$ids[ $dom ] = $slug;
			$order[] = $slug;
		}
	}

	flxa_menu_state( 'order', $order );
	flxa_menu_state( 'groups', $gstate );
	flxa_menu_state( 'icons', array_keys( $icons ) );
	flxa_menu_state( 'logos', $logos );
	flxa_menu_state( 'letters', $letters );
	flxa_menu_state( 'ids', $ids );
	flxa_menu_state( 'home', array_intersect_key( $prev, array_flip( array_merge( $pins, $away ) ) ) );
	flxa_menu_state( 'pins', $pins );
	flxa_menu_state( 'away', $away );

	flxa_block_check();
}

/**
 * Aynı işi gören iki ana menüyü teke indirir (filtre `flxa_merge_duplicates` ile kapatılabilir):
 * - Flux Updater varsa Başlangıç altındaki WordPress "Güncellemeler" onun altına taşınır.
 * - Site Analitik varsa WooCommerce "Analiz" ana menüsü kalkar, raporları Site Analitik altına girer.
 */
function flxa_menu_merge_duplicates() {
	global $menu, $submenu;
	if ( ! apply_filters( 'flxa_merge_duplicates', true ) ) {
		return;
	}
	$top = array();
	foreach ( (array) $menu as $k => $it ) {
		if ( ! empty( $it[2] ) ) {
			$top[ $it[2] ] = $k;
		}
	}
	/* Hedefin kendi alt menüsü olmalı; yoksa ana bağlantı taşınan sayfaya gider. */
	if ( isset( $top['flx-updater'] ) && ! empty( $submenu['flx-updater'] ) && ! empty( $submenu['index.php'] ) ) {
		foreach ( $submenu['index.php'] as $i => $s ) {
			if ( 'update-core.php' === ( $s[2] ?? '' ) ) {
				list( , $badge ) = flxa_split_label( $s[0] );
				$s[0]            = esc_html__( 'WordPress güncellemeleri', 'flx-admin' ) . ( '' !== $badge ? ' ' . $badge : '' );
				array_splice( $submenu['flx-updater'], 1, 0, array( $s ) );
				unset( $submenu['index.php'][ $i ] );
			}
		}
	}
	$wca = 'wc-admin&path=/analytics/overview';
	if ( isset( $top['flx-analytics'], $top[ $wca ] ) && ! empty( $submenu['flx-analytics'] ) ) {
		foreach ( (array) ( $submenu[ $wca ] ?? array() ) as $s ) {
			$slug = (string) ( $s[2] ?? '' );
			if ( 0 === strpos( $slug, 'wc-admin' ) ) {
				$slug = 'admin.php?page=' . $slug;
			}
			list( $label ) = flxa_split_label( $s[0] );
			/* translators: %s: WooCommerce rapor adı (Gelir, Siparişler…) */
			$s[0]                   = esc_html( sprintf( __( 'Mağaza: %s', 'flx-admin' ), $label ) );
			$s[2]                   = $slug;
			$submenu['flx-analytics'][] = $s; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
		unset( $menu[ $top[ $wca ] ], $submenu[ $wca ] );
	}
}

/**
 * Daraltılmış menüde grubun simgesi: bilinen grup anahtarı, sonra grup adından tahmin, sonra ilk öğenin simgesi.
 * Kayıtlı rol düzenlerinde grup anahtarı addan türediği için ad da denenir. Filtre `flxa_group_icon`.
 */
function flxa_group_icon( $key, $label = '', $first = '' ) {
	$by_key = array(
		'general' => 'grid',
		'apps'    => 'grid',
		'comms'   => 'inbox',
		'donate'  => 'heart',
		'content' => 'file',
		'growth'  => 'chart',
		'screens' => 'box',
		'system'  => 'gear',
		'site'    => 'gear',
		'other'   => 'more',
		'pinned'  => 'pin',
	);
	$icon = $by_key[ preg_replace( '/-\d+$/', '', (string) $key ) ] ?? '';
	if ( '' === $icon ) {
		$t     = remove_accents( mb_strtolower( str_replace( array( 'İ', 'I' ), array( 'i', 'ı' ), (string) $label ) ) );
		$rules = array(
			'inbox'  => array( 'iletisim', 'communic', 'mesaj', 'message', 'posta', 'mail' ),
			'heart'  => array( 'bagis', 'donat', 'odeme', 'payment', 'satis', 'magaza', 'shop' ),
			'file'   => array( 'icerik', 'content', 'yayin', 'sayfa' ),
			'chart'  => array( 'buyume', 'growth', 'pazarlama', 'marketing', 'analiz', 'analyt', 'seo' ),
			'box'    => array( 'ekran', 'screen', 'tv', 'uygulama', 'app' ),
			'gear'   => array( 'sistem', 'system', 'ayar', 'setting', 'yonetim', 'admin' ),
			'grid'   => array( 'genel', 'general', 'baslangic' ),
			'check'  => array( 'gorev', 'task', 'is ' ),
			'users'  => array( 'kullanici', 'user', 'uye', 'personel' ),
			'more'   => array( 'diger', 'other' ),
		);
		foreach ( $rules as $i => $words ) {
			foreach ( $words as $w ) {
				if ( false !== strpos( $t, $w ) ) {
					$icon = $i;
					break 2;
				}
			}
		}
	}
	if ( '' === $icon && '' !== $first ) {
		$icon = flxa_icon_for_slug( $first );
	}
	return (string) apply_filters( 'flxa_group_icon', '' !== $icon ? $icon : 'grid', $key, $label );
}

/** WordPress'in menü <li> kimliği kuralı. */
function flxa_menu_dom_id( $raw ) {
	return preg_replace( '|[^a-zA-Z0-9_:.]|', '-', (string) $raw );
}

/**
 * Rol düzeninde "erişimi engelle" denen sayfalar: menüden kalkar ve adresle açılmaz.
 * Gizlemeden farkı budur. Yöneticinin bu ayar ekranı her zaman açıktır.
 */
function flxa_block_check() {
	global $pagenow;
	$blocked = (array) flxa_menu_state( 'blocked' );
	if ( ! $blocked || wp_doing_ajax() ) {
		return;
	}
	$get  = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
	$page = isset( $get['page'] ) ? (string) $get['page'] : '';
	if ( 'flx-admin' === $page && current_user_can( 'manage_options' ) ) {
		return;
	}
	$req_pt = '';
	if ( in_array( $pagenow, array( 'edit.php', 'post-new.php' ), true ) ) {
		$req_pt = isset( $get['post_type'] ) ? sanitize_key( $get['post_type'] ) : 'post';
	} elseif ( 'post.php' === $pagenow && ! empty( $get['post'] ) ) {
		$req_pt = (string) get_post_type( (int) $get['post'] );
	}
	foreach ( $blocked as $slug => $subs ) {
		foreach ( array_merge( array( $slug ), (array) $subs ) as $c ) {
			$c = (string) $c;
			if ( '' !== $page ) {
				if ( $c === $page ) {
					flxa_block_die();
				}
				continue;
			}
			$path = (string) wp_parse_url( $c, PHP_URL_PATH );
			parse_str( (string) wp_parse_url( $c, PHP_URL_QUERY ), $q );
			if ( 'edit.php' === $path && '' !== $req_pt && ( $q['post_type'] ?? 'post' ) === $req_pt ) {
				flxa_block_die();
			}
			if ( $path === $pagenow && false === strpos( $c, 'page=' ) ) {
				$match = true;
				foreach ( $q as $qk => $qv ) {
					if ( ! isset( $get[ $qk ] ) || (string) $get[ $qk ] !== (string) $qv ) {
						$match = false;
					}
				}
				if ( $match && 'edit.php' !== $path ) {
					flxa_block_die();
				}
			}
		}
	}
}

function flxa_block_die() {
	wp_die(
		esc_html__( 'Bu sayfaya erişiminiz kapatıldı. Gerekirse site yöneticisine başvurun.', 'flx-admin' ),
		esc_html__( 'Erişim kapalı', 'flx-admin' ),
		array(
			'response'  => 403,
			'back_link' => true,
		)
	);
}

/** Sağ tık menüsünden: sabitle, sabitlemeyi kaldır, Diğer'e taşı, yerine al, sabitlenenleri sırala. */
add_action( 'wp_ajax_flxa_menu_user', 'flxa_ajax_menu_user' );
function flxa_ajax_menu_user() {
	check_ajax_referer( 'flxa', 'nonce' );
	$op   = sanitize_key( wp_unslash( $_POST['op'] ?? '' ) );
	$slug = (string) wp_unslash( $_POST['slug'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$slug = substr( wp_strip_all_tags( $slug ), 0, 200 );
	$uid  = get_current_user_id();
	$pins = flxa_user_pins( $uid );
	$away = flxa_user_list( 'flxa_away' );
	if ( 'order' === $op ) {
		$want = json_decode( (string) wp_unslash( $_POST['pins'] ?? '[]' ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$want = is_array( $want ) ? array_map( 'strval', $want ) : array();
		$pins = array_values( array_merge( array_intersect( $want, $pins ), array_diff( $pins, $want ) ) );
	} elseif ( '' === $slug ) {
		wp_send_json_error( null, 400 );
	} elseif ( 'pin' === $op ) {
		$pins[] = $slug;
		$away   = array_diff( $away, array( $slug ) );
	} elseif ( 'unpin' === $op ) {
		$pins = array_diff( $pins, array( $slug ) );
	} elseif ( 'away' === $op ) {
		$away[] = $slug;
		$pins   = array_diff( $pins, array( $slug ) );
	} elseif ( 'back' === $op ) {
		$away = array_diff( $away, array( $slug ) );
	} elseif ( 'subaway' === $op || 'subback' === $op ) {
		// Alt menü öğesi (bağlantısıyla): "Diğer" altına taşı / yerine al. Otomatik "Diğer"e düşenleri geri alma `flxa_sub_keep`te.
		$sa = flxa_user_list( 'flxa_sub_away', $uid );
		$sk = flxa_user_list( 'flxa_sub_keep', $uid );
		if ( 'subaway' === $op ) {
			$sa[] = $slug;
			$sk   = array_diff( $sk, array( $slug ) );
		} else {
			$sa   = array_diff( $sa, array( $slug ) );
			$sk[] = $slug;
		}
		update_user_meta( $uid, 'flxa_sub_away', array_slice( array_values( array_unique( $sa ) ), -150 ) );
		update_user_meta( $uid, 'flxa_sub_keep', array_slice( array_values( array_unique( $sk ) ), -150 ) );
		wp_send_json_success();
	} else {
		wp_send_json_error( null, 400 );
	}
	update_user_meta( $uid, 'flxa_pins', array_slice( array_values( array_unique( $pins ) ), 0, 30 ) );
	update_user_meta( $uid, 'flxa_away', array_slice( array_values( array_unique( $away ) ), 0, 80 ) );
	wp_send_json_success();
}

function flxa_menu_custom_order( $v ) {
	return flxa_menu_state( 'order' ) ? true : $v;
}

function flxa_menu_order( $o ) {
	$order = flxa_menu_state( 'order' );
	return $order ? $order : $o;
}

/** CSS içerik dizesi (content: "…"). */
function flxa_css_string( $s ) {
	$s = str_replace( array( '\\', '"', "\n", "\r", '<', '>' ), array( '\\\\', '\\"', ' ', ' ', '\\3C ', '\\3E ' ), (string) $s );
	return '"' . $s . '"';
}

/** Grup başlıkları ve çizgi simgeler için satır içi CSS. */
function flxa_menu_inline_css() {
	$css = '';
	foreach ( (array) flxa_menu_state( 'groups' ) as $id => $g ) {
		$css .= '#' . $id . ' .separator::after{content:' . flxa_css_string( $g['label'] ) . '}';
	}
	foreach ( (array) flxa_menu_state( 'logos' ) as $dom => $file ) {
		$css .= '#adminmenu #' . $dom . ' div.wp-menu-image{background-image:url("' . esc_url( flxa_logo_file_url( $file ) ) . '")!important;background-position:center!important;background-size:var(--flxa-logo-s,20px) var(--flxa-logo-s,20px)!important;background-repeat:no-repeat!important}';
	}
	foreach ( (array) flxa_menu_state( 'letters' ) as $dom => $l ) {
		$css .= '#adminmenu #' . $dom . ' div.wp-menu-image::before{content:' . flxa_css_string( $l ) . '!important}';
	}
	foreach ( (array) flxa_menu_state( 'icons' ) as $key ) {
		$css .= '.flxa-lic-' . sanitize_html_class( $key ) . ' .wp-menu-image::before{--flxa-i:url("' . flxa_icon_data_uri( $key ) . '")}';
	}
	return $css;
}

/**
 * Mobil alt bar öğeleri (en fazla 4; "Daha" ayrıca eklenir).
 * Rol düzenindeki `bottom` listesi, yoksa uygun ilk dört varsayılan.
 */
function flxa_bottom_items() {
	$all = (array) flxa_menu_state( 'all' );
	$cfg = flxa_menu_config_for_user();
	$defaults = flxa_default_bottom();
	// Seçilenlerden erişilemeyen olursa boşluğu varsayılanlardan (menüde gizli olmayan) doldur.
	$picked = ! empty( $cfg['bottom'] ) ? array_values( (array) $cfg['bottom'] ) : array();
	$want   = $picked ? min( 4, count( $picked ) ) : 4;
	$cand   = array_values( array_unique( array_merge( $picked, $defaults ) ) );
	$hidden  = array();
	$blocked = (array) flxa_menu_state( 'blocked' );
	foreach ( (array) ( $cfg['entries'] ?? array() ) as $e ) {
		if ( 'i' === ( $e['t'] ?? '' ) && ! empty( $e['hidden'] ) ) {
			$hidden[ $e['slug'] ] = true;
		}
	}
	$badge = array(
		'flx-chat'        => 'whatsapp',
		'flx-tasks'        => 'tasks',
		'flxb-dashboard'   => 'pending',
		'flx-updater' => 'updates',
	);
	$short = array(
		'index.php'     => __( 'Panel', 'flx-admin' ),
		'flx-chat'      => __( 'WhatsApp', 'flx-admin' ),
		'flxb-dashboard' => __( 'Bağışlar', 'flx-admin' ),
	);
	$names = array();
	foreach ( (array) ( $cfg['entries'] ?? array() ) as $e ) {
		if ( 'i' === ( $e['t'] ?? '' ) && ! empty( $e['label'] ) ) {
			$names[ $e['slug'] ] = $e['label'];
		}
	}
	$out = array();
	foreach ( $cand as $slug ) {
		if ( count( $out ) >= $want ) {
			break;
		}
		if ( ! isset( $all[ $slug ] ) || ( $all[ $slug ]['cap'] && ! current_user_can( $all[ $slug ]['cap'] ) ) ) {
			continue;
		}
		if ( isset( $blocked[ $slug ] ) || ( isset( $hidden[ $slug ] ) && ! in_array( $slug, $picked, true ) ) ) {
			continue;
		}
		$out[] = array(
			'slug'  => $slug,
			'label' => $names[ $slug ] ?? ( $short[ $slug ] ?? $all[ $slug ]['label'] ),
			'url'   => flxa_menu_url( $slug ),
			'icon'  => flxa_icon_for_slug( $slug ) ? flxa_icon_for_slug( $slug ) : 'dot',
			'badge' => $badge[ $slug ] ?? '',
		);
	}
	return $out;
}

/** Mobil alt barın varsayılanları (sırayla ilk erişilebilir dördü). */
function flxa_default_bottom() {
	return array( 'index.php', 'flx-chat', 'flx-tasks', 'flxb-dashboard', 'flxs', 'edit.php?post_type=page', 'upload.php', 'profile.php' );
}

/** Şu anki ekran bu menü öğesine mi ait. */
function flxa_is_current_slug( $slug ) {
	global $parent_file, $plugin_page, $pagenow;
	if ( $plugin_page && $plugin_page === $slug ) {
		return true;
	}
	if ( 'index.php' === $slug ) {
		return 'index.php' === $pagenow;
	}
	return $parent_file === $slug;
}
