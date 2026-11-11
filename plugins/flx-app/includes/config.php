<?php
/**
 * Uygulama görünümü (`flx-app-config/1`): GET /flxapp/v1/config. Masaüstü ve mobil aynı uçtan alır;
 * `X-FluxWP-Platform: mobile | desktop` (ya da `?platform=`) kabuğu belirler. Belge: docs/flx-app/API.md › Config.
 *
 * Katmanlar (sunucu): site (`flxapp_app` seçeneği; Uygulama › Görünüm sekmesi ya da preset `settings.flxapp_app`)
 * + rol (Flux Admin rol menüsü, platform gizlemesi) → çözümlenmiş `shell`. Marka, kullanıcı ve cihaz katmanlarını
 * istemci uygular (`@fluxwp/manifest` mergeShell).
 *
 *   mobil    shell: { nav, home, tabs[], drawer{enabled, header, pins, groups, footer}, topbar{style, show_logo, site_switcher, actions} }
 *   masaüstü shell: { home, sidebar{pins, groups, footer}, topbar{site_switcher, actions}, commands[] }
 *   platform yoksa: `shell` mobil, `shells: { mobile, desktop }` ikisi birden.
 */

defined( 'ABSPATH' ) || exit;

const FLXAPP_CONFIG_SCHEMA = 'flx-app-config/1';

/** Site katmanı (yönetici/preset). Bilinmeyen alanlar atılır. */
function flxapp_app_option() {
	$o = get_option( 'flxapp_app', array() );
	return is_array( $o ) ? $o : array();
}

/** Hedef sözdizimi (§6) geçerli mi. */
function flxapp_clean_target( $t ) {
	$t = trim( (string) $t );
	if ( in_array( $t, array( 'more', 'accounts', 'settings', 'search', 'notifications' ), true ) ) {
		return $t;
	}
	if ( preg_match( '#^(screen:[a-z0-9_\-]+/[a-z0-9_\-]+|module:[a-z0-9_\-]+|native:[a-z0-9_\-]+)$#', $t ) ) {
		return $t;
	}
	if ( preg_match( '#^(menu|web):[A-Za-z0-9_\-./?=&%]+$#', $t ) && false === strpos( $t, '..' ) ) {
		return $t;
	}
	if ( preg_match( '#^url:(https://|tel:|mailto:)#', $t ) ) {
		return esc_url_raw( substr( $t, 4 ), array( 'https', 'tel', 'mailto' ) ) ? $t : '';
	}
	return '';
}

function flxapp_clean_color( $c ) {
	$c = sanitize_hex_color( (string) $c );
	return $c ? strtoupper( $c ) : '';
}

/**
 * `flxapp_app` seçeneğini temizler (yönetici formu, preset ve REST aynı yoldan).
 *
 * @return array
 */
function flxapp_app_sanitize( $in ) {
	$in  = is_array( $in ) ? $in : array();
	$out = array();
	$t   = (array) ( $in['theme'] ?? array() );
	$out['theme'] = array(
		'mode'    => in_array( $t['mode'] ?? '', array( 'system', 'light', 'dark' ), true ) ? $t['mode'] : 'system',
		'radius'  => in_array( $t['radius'] ?? '', array( 'none', 'small', 'medium', 'large' ), true ) ? $t['radius'] : '',
		'density' => in_array( $t['density'] ?? '', array( 'comfortable', 'compact' ), true ) ? $t['density'] : '',
		'font'    => array(
			'body'    => in_array( $t['font']['body'] ?? '', flxapp_fonts(), true ) ? $t['font']['body'] : '',
			'heading' => in_array( $t['font']['heading'] ?? '', flxapp_fonts(), true ) ? $t['font']['heading'] : '',
		),
	);
	foreach ( array( 'light', 'dark' ) as $m ) {
		foreach ( flxapp_theme_color_keys() as $k ) {
			$c = flxapp_clean_color( $t[ $m ][ $k ] ?? '' );
			if ( '' !== $c ) {
				$out['theme'][ $m ][ $k ] = $c;
			}
		}
	}
	$s   = (array) ( $in['shell'] ?? array() );
	$mob = (array) ( $s['mobile'] ?? array() );
	$tabs = array();
	foreach ( array_slice( (array) ( $mob['tabs'] ?? array() ), 0, 5 ) as $i => $tab ) {
		$target = flxapp_clean_target( is_array( $tab ) ? ( $tab['target'] ?? '' ) : $tab );
		if ( '' === $target ) {
			continue;
		}
		$tabs[] = array_filter(
			array(
				'id'     => sanitize_key( (string) ( $tab['id'] ?? '' ) ) ? sanitize_key( (string) $tab['id'] ) : 't' . $i,
				'target' => $target,
				'icon'   => sanitize_key( (string) ( $tab['icon'] ?? '' ) ),
				'label'  => sanitize_text_field( (string) ( $tab['label'] ?? '' ) ),
				'badge'  => sanitize_key( (string) ( $tab['badge'] ?? '' ) ),
			),
			'strlen'
		);
	}
	$out['shell']['mobile'] = array(
		'nav'    => in_array( $mob['nav'] ?? '', array( 'tabs', 'drawer', 'tabs+drawer' ), true ) ? $mob['nav'] : 'tabs+drawer',
		'home'   => flxapp_clean_target( $mob['home'] ?? '' ),
		'tabs'   => $tabs,
		'drawer' => array(
			'enabled' => ! isset( $mob['drawer']['enabled'] ) || ! empty( $mob['drawer']['enabled'] ),
			'groups'  => flxapp_groups_sanitize( $mob['drawer']['groups'] ?? null ),
			'pins'    => flxapp_targets_sanitize( $mob['drawer']['pins'] ?? array() ),
		),
		'topbar' => array(
			'style'         => in_array( $mob['topbar']['style'] ?? '', array( 'large', 'compact' ), true ) ? $mob['topbar']['style'] : 'large',
			'show_logo'     => ! isset( $mob['topbar']['show_logo'] ) || ! empty( $mob['topbar']['show_logo'] ),
			'site_switcher' => ! isset( $mob['topbar']['site_switcher'] ) || ! empty( $mob['topbar']['site_switcher'] ),
			'actions'       => flxapp_actions_sanitize( $mob['topbar']['actions'] ?? null ),
		),
	);
	$desk = (array) ( $s['desktop'] ?? array() );
	$out['shell']['desktop'] = array(
		'home'     => flxapp_clean_target( $desk['home'] ?? '' ),
		'sidebar'  => array(
			'groups' => flxapp_groups_sanitize( $desk['sidebar']['groups'] ?? null ),
			'pins'   => flxapp_targets_sanitize( $desk['sidebar']['pins'] ?? array() ),
		),
		'topbar'   => array(
			'site_switcher' => ! isset( $desk['topbar']['site_switcher'] ) || ! empty( $desk['topbar']['site_switcher'] ),
			'actions'       => flxapp_actions_sanitize( $desk['topbar']['actions'] ?? null ),
		),
		'commands' => flxapp_targets_sanitize( $desk['commands'] ?? array() ),
	);
	$f = (array) ( $in['features'] ?? array() );
	$out['features'] = array(
		'search'         => ! isset( $f['search'] ) || ! empty( $f['search'] ),
		'biometric_lock' => ! empty( $f['biometric_lock'] ),
	);
	$l = (array) ( $in['links'] ?? array() );
	$out['links'] = array_filter(
		array(
			'privacy' => esc_url_raw( (string) ( $l['privacy'] ?? '' ), array( 'https', 'http' ) ),
			'support' => esc_url_raw( (string) ( $l['support'] ?? '' ), array( 'https', 'http', 'mailto', 'tel' ) ),
		)
	);
	$b = (array) ( $in['brand'] ?? array() );
	$out['brand'] = array_filter(
		array(
			'name'       => sanitize_text_field( (string) ( $b['name'] ?? '' ) ),
			'short_name' => sanitize_text_field( (string) ( $b['short_name'] ?? '' ) ),
			'logo'       => esc_url_raw( (string) ( $b['logo'] ?? '' ) ),
			'logo_dark'  => esc_url_raw( (string) ( $b['logo_dark'] ?? '' ) ),
			'icon'       => esc_url_raw( (string) ( $b['icon'] ?? '' ) ),
		)
	);
	return $out;
}

/** null = otomatik (Flux Admin rol düzeni). */
function flxapp_groups_sanitize( $groups ) {
	if ( null === $groups || ! is_array( $groups ) ) {
		return null;
	}
	$out = array();
	foreach ( array_slice( $groups, 0, 12 ) as $i => $g ) {
		$items = flxapp_targets_sanitize( $g['items'] ?? array() );
		if ( ! $items ) {
			continue;
		}
		$out[] = array(
			'id'        => sanitize_key( (string) ( $g['id'] ?? '' ) ) ? sanitize_key( (string) $g['id'] ) : 'g' . $i,
			'label'     => sanitize_text_field( (string) ( $g['label'] ?? '' ) ),
			'collapsed' => ! empty( $g['collapsed'] ),
			'items'     => $items,
		);
	}
	return $out;
}

function flxapp_targets_sanitize( $list ) {
	return array_values( array_unique( array_filter( array_map( 'flxapp_clean_target', array_slice( (array) $list, 0, 40 ) ) ) ) );
}

function flxapp_actions_sanitize( $list ) {
	if ( null === $list ) {
		return null;
	}
	$out = array();
	foreach ( array_slice( (array) $list, 0, 4 ) as $a ) {
		$t = flxapp_clean_target( is_array( $a ) ? ( $a['target'] ?? '' ) : $a );
		if ( '' !== $t ) {
			$out[] = array( 'target' => $t, 'icon' => sanitize_key( (string) ( is_array( $a ) ? ( $a['icon'] ?? '' ) : '' ) ) );
		}
	}
	return $out;
}

function flxapp_fonts() {
	return array( 'Inter', 'Figtree', 'Nunito', 'Nunito Sans', 'System' );
}

function flxapp_theme_color_keys() {
	return array( 'primary', 'accent', 'bg', 'surface', 'text' );
}

/* Preset ayar eşitlemesi bu alanları tanısın. */
add_filter(
	'flx_settings_schema',
	function ( $s ) {
		$s['flxapp_app']      = array(
			'label'  => 'Flux App › Görünüm',
			'fields' => array(
				'theme'    => array( 'type' => 'object', 'desc' => 'App theme overrides: mode, light/dark {primary, accent, bg, surface, text}, radius, density, font {body, heading}.' ),
				'shell'    => array( 'type' => 'object', 'desc' => 'App shell: mobile {nav, home, tabs[], drawer, topbar}, desktop {home, sidebar, topbar, commands[]}. Targets: screen:, module:, menu:, web:, url:.' ),
				'features' => array( 'type' => 'object', 'desc' => 'search, biometric_lock.' ),
				'links'    => array( 'type' => 'object', 'desc' => 'privacy, support.' ),
				'brand'    => array( 'type' => 'object', 'desc' => 'name, short_name, logo, logo_dark, icon (URLs).' ),
			),
		);
		$s['flxapp_settings'] = array(
			'label'  => 'Flux App',
			'fields' => array(
				'app_clients'   => array( 'type' => 'list', 'desc' => 'Brand OAuth clients: [{id, name, platform: mobile|desktop, redirect_uris[]}].' ),
				'generic_app'   => array( 'type' => 'bool', 'default' => 1, 'desc' => 'Main FluxWP apps (flx-mobil, flx-masaustu) may connect.' ),
				'push_enabled'  => array( 'type' => 'bool', 'default' => 1 ),
				'push_hide'     => array( 'type' => 'bool', 'default' => 0, 'desc' => 'Hide notification body on the lock screen.' ),
				'web_session'   => array( 'type' => 'bool', 'default' => 1 ),
				'dynamic_clients' => array( 'type' => 'bool', 'default' => 0, 'desc' => 'Allow OAuth dynamic client registration (RFC 7591). Built-in clients stay available when this is off.' ),
				'otp_enabled'       => array( 'type' => 'bool', 'default' => 1 ),
				'phone_self_verify' => array( 'type' => 'bool', 'default' => 1, 'desc' => 'Signed-in donors may confirm and save their own phone number.' ),
				'store_ios'     => array( 'type' => 'url', 'default' => '' ),
				'store_android' => array( 'type' => 'url', 'default' => '' ),
				'screens_off'   => array( 'type' => 'list', 'desc' => 'Disabled manifest screens: ["core/plugins"].' ),
				'modules_off'   => array( 'type' => 'list', 'desc' => 'Disabled app modules.' ),
			),
		);
		return $s;
	}
);

/* ───────── Tema (§8.2) ───────── */

function flxapp_on_color( $hex ) {
	if ( function_exists( 'flxa_on_color' ) ) {
		return strtoupper( flxa_on_color( $hex ) );
	}
	$h = ltrim( (string) $hex, '#' );
	if ( 6 !== strlen( $h ) || ! ctype_xdigit( $h ) ) {
		return '#FFFFFF';
	}
	$l = 0;
	foreach ( array( 0.2126, 0.7152, 0.0722 ) as $i => $w ) {
		$c  = hexdec( substr( $h, $i * 2, 2 ) ) / 255;
		$l += $w * ( $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 ) );
	}
	return ( 1.05 / ( $l + 0.05 ) ) >= 3 ? '#FFFFFF' : '#12141B';
}

/** theme.json değeri (yol yoksa boş). */
function flxapp_global_setting( array $path ) {
	if ( ! function_exists( 'wp_get_global_settings' ) ) {
		return '';
	}
	$v = wp_get_global_settings( $path );
	return is_string( $v ) ? $v : '';
}

function flxapp_theme_palette_color( $slug ) {
	if ( ! function_exists( 'wp_get_global_settings' ) ) {
		return '';
	}
	foreach ( array( 'custom', 'theme' ) as $origin ) {
		foreach ( (array) ( wp_get_global_settings( array( 'color', 'palette' ) )[ $origin ] ?? array() ) as $p ) {
			if ( ( $p['slug'] ?? '' ) === $slug ) {
				return flxapp_clean_color( $p['color'] ?? '' );
			}
		}
	}
	return '';
}

function flxapp_media_url( $id, $size = 'full' ) {
	$u = (int) $id ? wp_get_attachment_image_url( (int) $id, $size ) : '';
	return $u ? $u : '';
}

/** Çözümlenmiş tema: { mode, light{…}, dark{…}, radius, density, font }. */
function flxapp_theme() {
	$o       = (array) ( flxapp_app_option()['theme'] ?? array() );
	$fa      = function_exists( 'flxa_theme' ) ? flxa_theme() : array();
	$profile = (array) get_option( 'flx_profile', array() );
	$pc      = (array) ( $profile['colors'] ?? array() );
	$def     = array(
		'light' => array( 'bg' => '#F3F2EF', 'surface' => '#FFFFFF', 'text' => '#16181D' ),
		'dark'  => array( 'bg' => '#101114', 'surface' => '#191B20', 'text' => '#E8E8EA' ),
	);
	$pick    = function ( ...$c ) {
		foreach ( $c as $v ) {
			$v = flxapp_clean_color( $v );
			if ( '' !== $v ) {
				return $v;
			}
		}
		return '';
	};
	$accent  = $pick( $o['light']['accent'] ?? '', $pc['accent'] ?? '', '#F59E0B' );
	$out     = array( 'mode' => in_array( $o['mode'] ?? '', array( 'system', 'light', 'dark' ), true ) ? $o['mode'] : 'system' );
	$p_light = $pick( $o['light']['primary'] ?? '', function_exists( 'flxa_accent' ) ? flxa_accent() : '', flxapp_theme_palette_color( 'primary' ), $pc['primary'] ?? '', '#4338CA' );
	$p_dark  = $pick( $o['dark']['primary'] ?? '', function_exists( 'flxa_accent_dark' ) ? flxa_accent_dark() : '', flxapp_global_setting( array( 'custom', 'dark', 'primary' ) ), $p_light );
	foreach ( array( 'light' => $p_light, 'dark' => $p_dark ) as $m => $primary ) {
		$row = array(
			'primary'   => $primary,
			'onPrimary' => flxapp_on_color( $primary ),
			'accent'    => $pick( $o[ $m ]['accent'] ?? '', $accent ),
		);
		foreach ( array( 'bg' => 'bg', 'surface' => 'surface', 'text' => 'text' ) as $k => $fk ) {
			$row[ $k ] = $pick( $o[ $m ][ $k ] ?? '', $fa[ $m ][ $fk ] ?? '', 'light' === $m && 'bg' === $k ? flxapp_theme_palette_color( 'base' ) : '', $def[ $m ][ $k ] );
		}
		$row['onAccent'] = flxapp_on_color( $row['accent'] );
		$out[ $m ]       = $row;
	}
	$radius = (string) ( $o['radius'] ?? '' );
	if ( '' === $radius ) {
		$radius = (string) ( $fa['radius'] ?? '' );
	}
	if ( '' === $radius ) {
		$card   = (int) flxapp_global_setting( array( 'custom', 'radius', 'card' ) );
		$radius = $card >= 18 ? 'large' : ( $card >= 10 ? 'medium' : ( $card > 0 ? 'small' : 'medium' ) );
	}
	// Ortak şema: small | medium | large (Flux Admin "none" en yakın değere düşer).
	$out['radius']  = 'none' === $radius ? 'small' : $radius;
	$out['density'] = (string) ( $o['density'] ?? '' ) ? $o['density'] : (string) ( $fa['density'] ?? 'comfortable' );
	$fonts          = array();
	foreach ( (array) ( function_exists( 'wp_get_global_settings' ) ? ( wp_get_global_settings( array( 'typography', 'fontFamilies' ) )['theme'] ?? array() ) : array() ) as $f ) {
		$fonts[ (string) ( $f['slug'] ?? '' ) ] = (string) ( $f['name'] ?? '' );
	}
	$fa_font        = array( 'figtree' => 'Figtree', 'system' => 'System' )[ $fa['font'] ?? '' ] ?? '';
	$match          = function ( $name ) {
		return in_array( $name, flxapp_fonts(), true ) ? $name : '';
	};
	$out['font']    = array(
		'body'    => (string) ( $o['font']['body'] ?? '' ) ? $o['font']['body'] : ( $match( $fonts['body'] ?? '' ) ? $fonts['body'] : ( $fa_font ? $fa_font : 'System' ) ),
		'heading' => (string) ( $o['font']['heading'] ?? '' ) ? $o['font']['heading'] : ( $match( $fonts['heading'] ?? '' ) ? $fonts['heading'] : ( $fa_font ? $fa_font : 'System' ) ),
		'files'   => null,
	);
	/**
	 * Çözümlenmiş uygulama teması.
	 *
	 * @param array $theme
	 */
	return (array) apply_filters( 'flxapp_theme', $out );
}

/** Marka: override → preset profili → Flux Admin logosu → site simgesi. */
function flxapp_brand_info() {
	$o       = (array) ( flxapp_app_option()['brand'] ?? array() );
	$profile = (array) get_option( 'flx_profile', array() );
	$icon    = get_site_icon_url( 512 );
	$logo    = flxapp_media_url( $profile['logo'] ?? 0 );
	$square  = $icon ? $icon : flxapp_custom_logo_url();
	return array(
		'name'       => (string) ( $o['name'] ?? '' ) ? $o['name'] : html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ),
		'short_name' => (string) ( $o['short_name'] ?? '' ) ? $o['short_name'] : (string) ( $profile['name'] ?? '' ),
		'logo'       => (string) ( $o['logo'] ?? '' ) ? $o['logo'] : ( $logo ? $logo : ( function_exists( 'flxa_logo_url' ) ? flxa_logo_url( 256 ) : ( $icon ? $icon : '' ) ) ),
		'logo_dark'  => (string) ( $o['logo_dark'] ?? '' ) ? $o['logo_dark'] : flxapp_media_url( $profile['logo_light'] ?? 0 ),
		'icon'       => (string) ( $o['icon'] ?? '' ) ? $o['icon'] : ( flxapp_media_url( $profile['icon'] ?? 0 ) ? flxapp_media_url( $profile['icon'] ) : (string) $square ),
	);
}

/** Tema logosu (Görünüm › Özelleştir › Site kimliği). Site simgesi yoksa hesap işareti budur. */
function flxapp_custom_logo_url() {
	$id = (int) get_theme_mod( 'custom_logo' );
	if ( ! $id ) {
		return '';
	}
	$u = wp_get_attachment_image_url( $id, array( 512, 512 ) );
	if ( ! $u ) {
		$u = wp_get_attachment_url( $id );
	}
	return $u ? (string) $u : '';
}

/** Uygulamadaki site işareti: kare ikon, yoksa tema / Flux Admin / profil logosu. */
function flxapp_site_mark() {
	$b = flxapp_brand_info();
	foreach ( array( 'icon', 'logo', 'logo_dark' ) as $key ) {
		if ( ! empty( $b[ $key ] ) ) {
			return (string) $b[ $key ];
		}
	}
	return '';
}

/* ───────── Kabuk ───────── */

/** Kullanıcının erişebildiği hedef mi (menüsünde, manifestinde ya da modüllerinde). */
function flxapp_target_ok( $target, array $ctx ) {
	if ( in_array( $target, array( 'more', 'accounts', 'settings', 'search', 'notifications' ), true ) ) {
		return true;
	}
	list( $kind, $rest ) = array_pad( explode( ':', $target, 2 ), 2, '' );
	if ( 'menu' === $kind ) {
		return isset( $ctx['menu'][ $rest ] );
	}
	if ( 'screen' === $kind ) {
		list( $mod, $scr ) = array_pad( explode( '/', $rest, 2 ), 2, '' );
		return isset( $ctx['manifest'][ $mod ] ) && isset( ( (array) $ctx['manifest'][ $mod ]['screens'] )[ $scr ] );
	}
	if ( 'module' === $kind ) {
		return isset( $ctx['manifest'][ $rest ] ) || isset( $ctx['modules'][ $rest ] );
	}
	return in_array( $kind, array( 'web', 'url', 'native' ), true );
}

/** Kullanıcının menüsündeki slug'lar: alt menü yolları da (edit.php?post_type=page gibi) hedef olabilir. */
function flxapp_menu_index( array $menu ) {
	$idx = array();
	foreach ( $menu as $m ) {
		$idx[ (string) $m['slug'] ] = $m;
		foreach ( (array) ( $m['items'] ?? array() ) as $s ) {
			$idx[ (string) $s['slug'] ] = $s;
		}
	}
	return $idx;
}

/** Varsayılan gruplar: Flux Admin rol düzeni (pins, groups), yoksa menü sırası tek grupta. */
function flxapp_default_nav( $user, array $menu ) {
	$lay  = flxapp_user_layout( $user, $menu );
	$pins = array();
	$grps = array();
	if ( ! empty( $lay['layout'] ) ) {
		$pins = array_map( fn( $s ) => 'menu:' . $s, (array) $lay['layout']['pins'] );
		foreach ( (array) $lay['layout']['groups'] as $g ) {
			$grps[] = array(
				'id'        => (string) $g['id'],
				'label'     => (string) $g['label'],
				'collapsed' => (bool) $g['collapsed'],
				'items'     => array_map( fn( $s ) => 'menu:' . $s, (array) $g['items'] ),
			);
		}
	} else {
		$grps[] = array(
			'id'        => 'general',
			'label'     => '',
			'collapsed' => false,
			'items'     => array_values( array_map( fn( $m ) => 'menu:' . $m['slug'], $menu ) ),
		);
	}
	return array( $pins, $grps );
}

function flxapp_filter_groups( array $groups, array $ctx ) {
	$out = array();
	foreach ( $groups as $g ) {
		$g['items'] = array_values( array_filter( (array) $g['items'], fn( $t ) => flxapp_target_ok( $t, $ctx ) ) );
		if ( $g['items'] ) {
			$out[] = $g;
		}
	}
	return $out;
}

/** Menü slug'ına bağlı modül varsa `module:` hedefi (alt bar kısayolları için). */
function flxapp_tab_from_slug( $slug, array $ctx ) {
	foreach ( $ctx['manifest'] as $id => $m ) {
		if ( (string) ( $m['menu'] ?? '' ) === (string) $slug ) {
			return array( 'id' => sanitize_key( $id ), 'target' => 'module:' . $id, 'icon' => (string) ( $m['icon'] ?? '' ), 'label' => (string) ( $m['title'] ?? '' ), 'badge' => '' );
		}
		$routes = (array) ( $m['routes'] ?? array() );
		if ( isset( $routes[ $slug ] ) && empty( $routes[ $slug ]['params'] ) ) {
			$scr = (string) $routes[ $slug ]['screen'];
			return array( 'id' => sanitize_key( $id . '-' . $scr ), 'target' => 'screen:' . $id . '/' . $scr, 'icon' => '', 'label' => (string) ( ( (array) $m['screens'] )[ $scr ]['title'] ?? '' ), 'badge' => '' );
		}
	}
	foreach ( $ctx['modules'] as $id => $m ) {
		if ( (string) ( $m['menu'] ?? '' ) === (string) $slug ) {
			return array( 'id' => sanitize_key( $id ), 'target' => 'module:' . $id, 'icon' => (string) ( $m['icon'] ?? '' ), 'label' => (string) ( $m['title'] ?? '' ), 'badge' => '' );
		}
	}
	$item = $ctx['menu'][ $slug ] ?? null;
	return $item ? array( 'id' => sanitize_title( $slug ), 'target' => 'menu:' . $slug, 'icon' => (string) ( $item['icon'] ?? '' ), 'label' => (string) $item['title'], 'badge' => '' ) : null;
}

/** Kullanıcı için çözümlenmiş kabuk. $platform '' ise mobil. */
function flxapp_shell( $user, $platform ) {
	$menu = flxapp_user_menu( $user->ID );
	$ctx  = array(
		'menu'     => flxapp_menu_index( $menu ),
		'manifest' => flxapp_user_manifest( $user, $platform ),
		'modules'  => flxapp_user_modules( $user->ID ),
	);
	$site = (array) ( flxapp_app_option()['shell'] ?? array() );
	list( $pins, $groups ) = flxapp_default_nav( $user, $menu );
	$home_default = flxapp_target_ok( 'screen:core/dashboard', $ctx ) ? 'screen:core/dashboard' : 'more';
	$actions      = array( array( 'target' => 'search', 'icon' => 'search' ), array( 'target' => 'notifications', 'icon' => 'bell' ) );

	if ( 'desktop' === $platform ) {
		$d    = (array) ( $site['desktop'] ?? array() );
		$home = flxapp_clean_target( $d['home'] ?? '' );
		$cmds = ! empty( $d['commands'] ) ? (array) $d['commands'] : array( 'screen:core/post_form', 'screen:core/page_form', 'screen:core/media', 'screen:core/comments' );
		$sidebar = array(
			'pins'   => array_values( array_filter( ! empty( $d['sidebar']['pins'] ) ? (array) $d['sidebar']['pins'] : $pins, fn( $t ) => flxapp_target_ok( $t, $ctx ) ) ),
			'groups' => flxapp_filter_groups( is_array( $d['sidebar']['groups'] ?? null ) ? $d['sidebar']['groups'] : $groups, $ctx ),
			'footer' => array( 'accounts', 'settings' ),
		);
		// Ortak ShellConfig alanları (nav, tabs, drawer) da dolu: masaüstü drawer'ı kalıcı kenar çubuğu olarak çizer.
		return array(
			'nav'      => 'drawer',
			'home'     => $home && flxapp_target_ok( $home, $ctx ) ? $home : $home_default,
			'tabs'     => array(),
			'drawer'   => array( 'enabled' => true, 'header' => 'account' ) + $sidebar,
			'sidebar'  => $sidebar,
			'topbar'   => array(
				'style'         => 'compact',
				'show_logo'     => true,
				'site_switcher' => ! isset( $d['topbar']['site_switcher'] ) || ! empty( $d['topbar']['site_switcher'] ),
				'actions'       => is_array( $d['topbar']['actions'] ?? null ) ? $d['topbar']['actions'] : $actions,
			),
			'commands' => array_values( array_filter( $cmds, fn( $t ) => flxapp_target_ok( $t, $ctx ) ) ),
		);
	}

	$m    = (array) ( $site['mobile'] ?? array() );
	$home = flxapp_clean_target( $m['home'] ?? '' );
	$home = $home && flxapp_target_ok( $home, $ctx ) ? $home : $home_default;
	$tabs = array();
	if ( ! empty( $m['tabs'] ) ) {
		$tabs = array_values( array_filter( (array) $m['tabs'], fn( $t ) => flxapp_target_ok( $t['target'], $ctx ) ) );
	} else {
		$tabs[] = array( 'id' => 'home', 'target' => $home, 'icon' => 'home', 'label' => __( 'Home', 'flx-app' ) );
		foreach ( flxapp_user_bottom( $user ) as $slug ) {
			$t = flxapp_tab_from_slug( $slug, $ctx );
			if ( $t && count( $tabs ) < 4 && ! in_array( $t['target'], array_column( $tabs, 'target' ), true ) ) {
				$tabs[] = array_filter( $t, 'strlen' );
			}
		}
		foreach ( array( 'screen:core/comments' => array( 'comment', __( 'Comments', 'flx-app' ), 'comments' ), 'screen:core/posts' => array( 'post', __( 'Posts', 'flx-app' ), '' ) ) as $target => $d ) {
			if ( count( $tabs ) < 3 && flxapp_target_ok( $target, $ctx ) && ! in_array( $target, array_column( $tabs, 'target' ), true ) ) {
				$tabs[] = array_filter( array( 'id' => $d[0], 'target' => $target, 'icon' => $d[0], 'label' => $d[1], 'badge' => $d[2] ), 'strlen' );
			}
		}
	}
	$nav    = in_array( $m['nav'] ?? '', array( 'tabs', 'drawer', 'tabs+drawer' ), true ) ? $m['nav'] : 'tabs+drawer';
	$drawer = ! isset( $m['drawer']['enabled'] ) || ! empty( $m['drawer']['enabled'] );
	if ( ! in_array( 'more', array_column( $tabs, 'target' ), true ) && ( ! $drawer || 'tabs' === $nav ) ) {
		$tabs = array_slice( $tabs, 0, 4 );
		$tabs[] = array( 'id' => 'more', 'target' => 'more', 'icon' => 'menu', 'label' => __( 'Menu', 'flx-app' ) );
	}
	return array(
		'nav'    => $nav,
		'home'   => $home,
		'tabs'   => array_slice( $tabs, 0, 5 ),
		'drawer' => array(
			'enabled' => $drawer,
			'header'  => 'account',
			'pins'    => array_values( array_filter( ! empty( $m['drawer']['pins'] ) ? (array) $m['drawer']['pins'] : $pins, fn( $t ) => flxapp_target_ok( $t, $ctx ) ) ),
			'groups'  => flxapp_filter_groups( is_array( $m['drawer']['groups'] ?? null ) ? $m['drawer']['groups'] : $groups, $ctx ),
			'footer'  => array( 'accounts', 'settings' ),
		),
		'topbar' => array(
			'style'         => in_array( $m['topbar']['style'] ?? '', array( 'large', 'compact' ), true ) ? $m['topbar']['style'] : 'large',
			'show_logo'     => ! isset( $m['topbar']['show_logo'] ) || ! empty( $m['topbar']['show_logo'] ),
			'site_switcher' => ! isset( $m['topbar']['site_switcher'] ) || ! empty( $m['topbar']['site_switcher'] ),
			'actions'       => is_array( $m['topbar']['actions'] ?? null ) ? $m['topbar']['actions'] : $actions,
		),
	);
}

/** Webview'in kalabileceği host'lar: site + ana adres. */
function flxapp_webview_hosts() {
	$hosts = array_unique( array_filter( array( wp_parse_url( home_url(), PHP_URL_HOST ), wp_parse_url( site_url(), PHP_URL_HOST ), wp_parse_url( admin_url(), PHP_URL_HOST ) ) ) );
	return array_values( (array) apply_filters( 'flxapp_webview_hosts', $hosts ) );
}

/** GET /config gövdesi. */
function flxapp_config( $user = null, $platform = null ) {
	$user     = $user ? $user : wp_get_current_user();
	$platform = null === $platform ? flxapp_request_platform() : (string) $platform;
	$site     = flxapp_app_option();
	$cfg      = array(
		'schema'   => FLXAPP_CONFIG_SCHEMA,
		'platform' => '' !== $platform ? $platform : null,
		'brand'    => flxapp_brand_info(),
		'theme'    => flxapp_theme(),
		'shell'    => flxapp_shell( $user, '' !== $platform ? $platform : 'mobile' ),
		'features' => array(
			'web_session'    => (bool) flxapp_opt( 'web_session' ),
			'push'           => (bool) flxapp_opt( 'push_enabled' ),
			'push_hide'      => (bool) flxapp_opt( 'push_hide' ),
			'otp'            => (bool) flxapp_opt( 'otp_enabled' ),
			'search'         => ! isset( $site['features']['search'] ) || ! empty( $site['features']['search'] ),
			'biometric_lock' => ! empty( $site['features']['biometric_lock'] ),
		),
		'transport' => array( 'method_override' => true, 'poll_min' => 10, 'token_header' => 'authorization' ),
		'webview'   => array( 'allow_hosts' => flxapp_webview_hosts(), 'external' => 'in_app_browser' ),
		'links'     => array_filter(
			array(
				'privacy' => (string) ( $site['links']['privacy'] ?? '' ) ? $site['links']['privacy'] : (string) get_privacy_policy_url(),
				'support' => (string) ( $site['links']['support'] ?? '' ) ? $site['links']['support'] : 'mailto:' . get_option( 'admin_email' ),
				'ios'     => (string) flxapp_opt( 'store_ios' ),
				'android' => (string) flxapp_opt( 'store_android' ),
			)
		),
		'versions'  => array(
			'flx-app'         => FLXAPP_VERSION,
			'min_app'         => flxapp_min_app()[ 'desktop' === $platform ? 'desktop' : 'mobile' ],
			'min_app_mobile'  => flxapp_min_app()['mobile'],
			'min_app_desktop' => flxapp_min_app()['desktop'],
		),
	);
	if ( '' === $platform ) {
		$cfg['shells'] = array(
			'mobile'  => $cfg['shell'],
			'desktop' => flxapp_shell( $user, 'desktop' ),
		);
	}
	/**
	 * Çözümlenmiş uygulama görünümü.
	 *
	 * @param array   $cfg
	 * @param WP_User $user
	 * @param string  $platform
	 */
	return (array) apply_filters( 'flxapp_config', $cfg, $user, $platform );
}

/** Desteklenen en eski uygulama sürümleri. */
function flxapp_min_app() {
	return (array) apply_filters( 'flxapp_min_app', array( 'mobile' => '1.0.0', 'desktop' => '0.3.0' ) );
}

function flxapp_config_hash( $user = null, $platform = null ) {
	$c = flxapp_config( $user, $platform );
	return substr( md5( wp_json_encode( $c ) ), 0, 12 );
}

function flxapp_rest_config( WP_REST_Request $req ) {
	$cfg         = flxapp_config();
	$hash        = substr( md5( wp_json_encode( $cfg ) ), 0, 12 );
	$cfg['hash'] = $hash;
	return flxapp_etag_response( $cfg, $hash, $req );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route( 'flxapp/v1', '/config', array( 'methods' => 'GET', 'callback' => 'flxapp_rest_config', 'permission_callback' => 'flxapp_logged_in' ) );
	}
);
