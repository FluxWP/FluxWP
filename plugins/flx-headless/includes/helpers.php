<?php
/**
 * Small helpers shared by the services, the hooks and the admin page.
 *
 * @package FLX_Headless
 */

defined( 'ABSPATH' ) || exit;

/** Admin page of the plugin. */
function flxhl_admin_url(): string {
	return admin_url( 'admin.php?page=flx-headless' );
}

/** Scalar → string, anything else → ''. */
function flxhl_str( $v ): string {
	return is_scalar( $v ) ? (string) $v : '';
}

/** Numeric → int, anything else → $default. */
function flxhl_int( $v, int $default = 0 ): int {
	return is_numeric( $v ) ? (int) $v : $default;
}

/**
 * Array → array, anything else → [].
 *
 * @return array<array-key,mixed>
 */
function flxhl_arr( $v ): array {
	return is_array( $v ) ? $v : array();
}

/**
 * Origin (scheme://host[:port], lower case) of a URL; '' when it has none.
 */
function flxhl_origin( string $url ): string {
	$p = wp_parse_url( $url );
	if ( ! is_array( $p ) || empty( $p['scheme'] ) || empty( $p['host'] ) ) {
		return '';
	}
	return strtolower( $p['scheme'] . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' ) );
}

/**
 * Path of the WordPress home URL without the trailing slash ('' at the domain root).
 */
/**
 * WordPress address without a language prefix. home_url() carries the current language's prefix (Flux Lang),
 * which would hide addresses of the other languages from the front-end mapping.
 */
function flxhl_home(): string {
	return untrailingslashit( (string) get_option( 'home' ) );
}

function flxhl_home_path(): string {
	$p = wp_parse_url( flxhl_home() . '/', PHP_URL_PATH );
	return untrailingslashit( is_string( $p ) ? $p : '' );
}

/**
 * Whether a WordPress path (relative to the home path) belongs to WordPress itself and never to a front end:
 * admin, files, REST, GraphQL, login, sitemaps.
 */
function flxhl_path_is_backend( string $path ): bool {
	$path = '/' . ltrim( $path, '/' );
	foreach ( array( '/wp-admin', '/wp-content', '/wp-includes', '/wp-json', '/graphql', '/wp-login.php', '/wp-cron.php', '/xmlrpc.php', '/wp-sitemap', '/feed' ) as $prefix ) {
		if ( 0 === strpos( $path, $prefix ) ) {
			return true;
		}
	}
	return (bool) preg_match( '#\.(xml|txt|php|js|css|map|json|ico|png|jpe?g|gif|webp|avif|svg|woff2?)$#i', (string) wp_parse_url( $path, PHP_URL_PATH ) );
}

/**
 * Locale of the current answer: the Flux Lang language's own locale when there is one (the site locale can
 * differ from the default language's), else WordPress'.
 */
function flxhl_locale(): string {
	if ( function_exists( 'flxl_current_language' ) && class_exists( 'FLXL_Language_Service' ) ) {
		$code = flxhl_str( flxl_current_language() );
		foreach ( FLXL_Language_Service::languages() as $l ) {
			if ( $l->code === $code && '' !== $l->locale ) {
				return $l->locale;
			}
		}
	}
	return determine_locale();
}
