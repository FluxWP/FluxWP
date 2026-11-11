<?php
/**
 * Favicon: Google arama sonuçlarında görünmesi için PNG (48'in katları) + /favicon.ico.
 * Ayarlar › Genel › Site simgesi seçildiyse WordPress'inki kullanılır.
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Tarayıcı çubuğu rengi: tema paletinin ana rengi (FluxWP temasında indigo). */
function flxseo_theme_color() {
	if ( function_exists( 'wp_get_global_settings' ) ) {
		$p = wp_get_global_settings( array( 'color', 'palette' ) );
		foreach ( array( 'custom', 'theme' ) as $origin ) {
			foreach ( (array) ( $p[ $origin ] ?? array() ) as $c ) {
				if ( 'primary' === ( $c['slug'] ?? '' ) && sanitize_hex_color( $c['color'] ?? '' ) ) {
					return $c['color'];
				}
			}
		}
	}
	return '#1F2937';
}

function flxseo_icon( $file ) {
	return FLXSEO_URL . 'assets/icons/' . $file . '?v=' . FLXSEO_VERSION;
}

/**
 * Sitenin kendi simgesi (WordPress site simgesi seçilmemişken): tema kurum profili simgesi ve ön ayar
 * filtreleri (flx_logo_files), tema yoksa kurum profili. Boşsa FluxWP simgesi kullanılır.
 * Filtre `flx_custom_favicon` (adres) ile değiştirilebilir.
 */
function flxseo_custom_favicon() {
	static $url = null;
	if ( null !== $url ) {
		return $url;
	}
	$url = '';
	if ( function_exists( 'flx_logo_files' ) ) {
		$url = (string) ( flx_logo_files()['icon'] ?? '' );
	} elseif ( function_exists( 'flx_profile' ) ) {
		$icon = flx_profile( 'icon', '' );
		if ( is_numeric( $icon ) && (int) $icon > 0 ) {
			$url = (string) wp_get_attachment_url( (int) $icon );
		} elseif ( is_string( $icon ) && preg_match( '#^(https?:)?//|^/#', $icon ) ) {
			$url = $icon;
		}
	}
	$url = (string) apply_filters( 'flx_custom_favicon', $url );
	return $url;
}

/** Simge adresinin MIME türü (link type özniteliği için). */
function flxseo_icon_type( $url ) {
	$ext = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	$map = array(
		'svg'  => 'image/svg+xml',
		'png'  => 'image/png',
		'ico'  => 'image/x-icon',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'webp' => 'image/webp',
	);
	return $map[ $ext ] ?? '';
}

/**
 * Favicon tek yerden ve ilk HTML'de: sitenin simgesi varsa yalnız o yazılır, FluxWP simgesi hiç basılmaz.
 * Önceden FluxWP simgesi burada, sitenin simgesi temada (öncelik 2) yazılıyordu; tarayıcı yenilemede önce
 * FluxWP'yi gösterip sonra sitenin simgesine geçiyordu. Yazıldıktan sonra `flx_favicon_printed` çalışır;
 * tema ve ön ayarlar bunu görünce kendi favicon'larını yazmaz.
 */
add_action(
	'wp_head',
	function () {
		if ( has_site_icon() ) {
			return;
		}
		$custom = flxseo_custom_favicon();
		if ( '' !== $custom ) {
			$type = flxseo_icon_type( $custom );
			echo '<link rel="icon"' . ( $type ? ' type="' . esc_attr( $type ) . '"' : '' ) . ( 'image/svg+xml' === $type ? ' sizes="any"' : '' ) . ' href="' . esc_url( $custom ) . '">' . "\n";
			// iOS SVG ana ekran simgesini okumaz; yalnız bitmap ise apple-touch-icon.
			if ( in_array( $type, array( 'image/png', 'image/jpeg' ), true ) ) {
				echo '<link rel="apple-touch-icon" href="' . esc_url( $custom ) . '">' . "\n";
			}
		} else {
			// SVG modern tarayıcılar için (açık/koyu uyumlu); Google PNG/ICO'yu daha güvenilir okur.
			echo '<link rel="icon" type="image/svg+xml" href="' . esc_url( flxseo_icon( 'favicon.svg' ) ) . '">' . "\n";
			echo '<link rel="icon" type="image/x-icon" sizes="48x48" href="' . esc_url( home_url( '/favicon.ico' ) ) . '">' . "\n";
			echo '<link rel="icon" type="image/png" sizes="96x96" href="' . esc_url( flxseo_icon( 'icon-96.png' ) ) . '">' . "\n";
			echo '<link rel="icon" type="image/png" sizes="192x192" href="' . esc_url( flxseo_icon( 'icon-192.png' ) ) . '">' . "\n";
			echo '<link rel="apple-touch-icon" type="image/png" sizes="180x180" href="' . esc_url( flxseo_icon( 'icon-180.png' ) ) . '">' . "\n";
		}
		echo '<link rel="manifest" href="' . esc_url( home_url( '/site.webmanifest' ) ) . '">' . "\n";
		do_action( 'flx_favicon_printed' );
	},
	1
);

/**
 * /favicon.ico: WordPress site simgesi yoksa WP logosuna yönlendiriyor; bunun yerine bizim ikon.
 */
add_action(
	'do_faviconico',
	function () {
		if ( has_site_icon() ) {
			return;
		}
		$custom = flxseo_custom_favicon();
		if ( '' !== $custom ) {
			wp_safe_redirect( $custom, 302, 'Flux SEO' );
			exit;
		}
		header( 'Content-Type: image/x-icon' );
		header( 'Cache-Control: public, max-age=604800' );
		readfile( FLXSEO_DIR . 'assets/icons/favicon.ico' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	},
	1
);

/**
 * /site.webmanifest (Android ana ekran simgesi).
 */
add_action(
	'parse_request',
	function ( $wp ) {
		$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( ltrim( substr( $path, strlen( $home ) ), '/' ) !== 'site.webmanifest' ) {
			return;
		}
		$custom = has_site_icon() ? '' : flxseo_custom_favicon();
		if ( has_site_icon() ) {
			$icons = array(
				array( 'src' => get_site_icon_url( 192 ), 'sizes' => '192x192', 'type' => 'image/png' ),
				array( 'src' => get_site_icon_url( 512 ), 'sizes' => '512x512', 'type' => 'image/png' ),
			);
		} elseif ( '' !== $custom ) {
			$type  = flxseo_icon_type( $custom );
			$icons = array( array_filter( array( 'src' => $custom, 'sizes' => 'image/svg+xml' === $type ? 'any' : '512x512', 'type' => $type ) ) );
		} else {
			$icons = array(
				array( 'src' => flxseo_icon( 'icon-192.png' ), 'sizes' => '192x192', 'type' => 'image/png' ),
				array( 'src' => flxseo_icon( 'icon-512.png' ), 'sizes' => '512x512', 'type' => 'image/png' ),
			);
		}
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		echo wp_json_encode(
			array(
				'name'             => flxseo_opt( 'site_name' ),
				'short_name'       => flxseo_opt( 'site_name' ),
				'start_url'        => home_url( '/' ),
				'display'          => 'browser',
				'background_color' => '#ffffff',
				'theme_color'      => flxseo_theme_color(),
				'icons'            => $icons,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		exit;
	},
	1
);

/**
 * Kök dizinde gerçek bir favicon.ico dosyası: Hostinger/LiteSpeed gibi sunucular .ico isteğini
 * WordPress'e hiç iletmeden 404 döndürebilir. Site simgesi yoksa dosya bir kez kopyalanır.
 */
function flxseo_root_favicon() {
	if ( has_site_icon() || '' !== flxseo_custom_favicon() || get_option( 'flxseo_root_favicon' ) === FLXSEO_VERSION ) {
		return;
	}
	$target = ABSPATH . 'favicon.ico';
	// Eski sürümlerin kopyaladığı yaprak simgesi yenisiyle değişir; sitenin kendi dosyasına dokunulmaz.
	$old = array( '0f51008ee404c8d60867988ea1fc2aba', 'e245c5a9c1fc945c774d2a799aad08ea' );
	if ( file_exists( $target ) && in_array( md5_file( $target ), $old, true ) && wp_is_writable( $target ) ) {
		copy( FLXSEO_DIR . 'assets/icons/favicon.ico', $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	} elseif ( ! file_exists( $target ) && wp_is_writable( ABSPATH ) ) {
		copy( FLXSEO_DIR . 'assets/icons/favicon.ico', $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	update_option( 'flxseo_root_favicon', FLXSEO_VERSION, false );
}
add_action( 'admin_init', 'flxseo_root_favicon' );
