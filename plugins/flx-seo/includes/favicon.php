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
	return '#4338CA';
}

function flxseo_icon( $file ) {
	return FLXSEO_URL . 'assets/icons/' . $file . '?v=' . FLXSEO_VERSION;
}

add_action(
	'wp_head',
	function () {
		if ( has_site_icon() ) {
			return;
		}
		// Temanın SVG favicon'u da kalır; Google PNG/ICO'yu daha güvenilir okur.
		echo '<link rel="icon" type="image/x-icon" sizes="48x48" href="' . esc_url( home_url( '/favicon.ico' ) ) . '">' . "\n";
		echo '<link rel="icon" type="image/png" sizes="96x96" href="' . esc_url( flxseo_icon( 'icon-96.png' ) ) . '">' . "\n";
		echo '<link rel="icon" type="image/png" sizes="192x192" href="' . esc_url( flxseo_icon( 'icon-192.png' ) ) . '">' . "\n";
		echo '<link rel="apple-touch-icon" type="image/png" sizes="180x180" href="' . esc_url( flxseo_icon( 'icon-180.png' ) ) . '">' . "\n";
		echo '<link rel="manifest" href="' . esc_url( home_url( '/site.webmanifest' ) ) . '">' . "\n";
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
		$icon = has_site_icon() ? array( get_site_icon_url( 192 ), get_site_icon_url( 512 ) ) : array( flxseo_icon( 'icon-192.png' ), flxseo_icon( 'icon-512.png' ) );
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		echo wp_json_encode(
			array(
				'name'             => flxseo_opt( 'site_name' ),
				'short_name'       => flxseo_opt( 'site_name' ),
				'start_url'        => home_url( '/' ),
				'display'          => 'browser',
				'background_color' => '#ffffff',
				'theme_color'      => flxseo_theme_color(),
				'icons'            => array(
					array( 'src' => $icon[0], 'sizes' => '192x192', 'type' => 'image/png' ),
					array( 'src' => $icon[1], 'sizes' => '512x512', 'type' => 'image/png' ),
				),
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
	if ( has_site_icon() || get_option( 'flxseo_root_favicon' ) === FLXSEO_VERSION ) {
		return;
	}
	$target = ABSPATH . 'favicon.ico';
	if ( ! file_exists( $target ) && wp_is_writable( ABSPATH ) ) {
		copy( FLXSEO_DIR . 'assets/icons/favicon.ico', $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	update_option( 'flxseo_root_favicon', FLXSEO_VERSION, false );
}
add_action( 'admin_init', 'flxseo_root_favicon' );
