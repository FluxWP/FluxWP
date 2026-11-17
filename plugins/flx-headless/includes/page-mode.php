<?php
/**
 * Whole-page mode: a front end asks WordPress for a page exactly as the theme renders it
 * (template, header, footer, blocks, styles and scripts) and serves it under its own address.
 *
 * Request: GET <WordPress page>?flxhl_page=<front end id>. WordPress answers the page as an anonymous
 * visitor would see it, with links rewritten to the front end:
 * - pages and posts           → <front end><path>
 * - /wp-content/, /wp-includes/, /wp-json/ and admin-ajax.php → <front end><path> (the front end proxies them, so
 *   fonts, REST and AJAX stay same-origin and share the visitor's cookies)
 * - wp-admin, login and other .php addresses stay on WordPress.
 * Headers: X-Flx-Cache-Tags (comma separated, same tags as the revalidate webhook sends), X-Flx-Live: 1 on
 * per-visitor pages (cart, checkout, account; the front end must not cache them) and, for a redirect, a
 * Location already on the front end.
 *
 * @package FLX_Headless
 */

defined( 'ABSPATH' ) || exit;

/*
 * The request URL carries ?flxhl_page; nothing else (links, canonical, language switchers, pagination) may see it.
 * Taken off as soon as this plugin loads, before most plugins build URLs from the request.
 */
$GLOBALS['flxhl_page_id'] = isset( $_GET['flxhl_page'] ) ? sanitize_key( wp_unslash( $_GET['flxhl_page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
if ( '' !== $GLOBALS['flxhl_page_id'] ) {
	unset( $_GET['flxhl_page'], $_REQUEST['flxhl_page'] );
	foreach ( array( 'REQUEST_URI', 'QUERY_STRING' ) as $flxhl_key ) {
		if ( isset( $_SERVER[ $flxhl_key ] ) && is_string( $_SERVER[ $flxhl_key ] ) ) {
			$_SERVER[ $flxhl_key ] = flxhl_page_strip( $_SERVER[ $flxhl_key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}
	}
	unset( $flxhl_key );
	/*
	 * The rewrite buffer opens here, before any other plugin's, so it is the outermost one and runs last: page
	 * optimizers (LiteSpeed, Flux Speed, WP Rocket...) only combine and minify files on this site's own address, and
	 * they must see the page before its links move to the front end. Pages that are not a whole-page response pass
	 * through untouched (see the template_redirect action below).
	 */
	ob_start( 'flxhl_page_buffer' );
}

/** Output buffer of a whole-page request: rewrites the finished page once template_redirect marked it as one. */
function flxhl_page_buffer( string $html ): string {
	$fe = $GLOBALS['flxhl_page_rewrite'] ?? null;
	return $fe instanceof FLXHL_Frontend ? flxhl_page_rewrite( $html, $fe ) : $html;
}

/** Removes the flxhl_page argument from a URL or query string. */
function flxhl_page_strip( string $url ): string {
	$out = preg_replace( array( '/([?&])flxhl_page=[^&#"\'\s]*&/', '/[?&]flxhl_page=[^&#"\'\s]*/' ), array( '$1', '' ), $url );
	return is_string( $out ) ? $out : $url;
}

/** Front end of a whole-page request, null for any other request. */
function flxhl_page_frontend(): ?FLXHL_Frontend {
	static $fe = false;
	if ( false !== $fe ) {
		return $fe;
	}
	$fe = null;
	$id = flxhl_str( $GLOBALS['flxhl_page_id'] ?? '' );
	if ( '' === $id || ! did_action( 'plugins_loaded' ) ) {
		if ( '' !== $id ) {
			$fe = false; // Ask again once settings can be read.
		}
		return null;
	}
	$found = 'default' === $id ? FLXHL_Frontend_Service::default() : FLXHL_Frontend_Service::get( $id );
	$fe    = null !== $found && $found->enabled ? $found : null;
	return $fe;
}

/**
 * Paths the front end must never cache: cart, checkout and account (per-visitor sessions). The front end serves
 * them live, with the visitor's cookies. Filter: flx_headless_live_paths.
 *
 * @return list<string>
 */
function flxhl_live_paths(): array {
	static $paths = null;
	if ( null !== $paths ) {
		return $paths;
	}
	$paths = array();
	if ( function_exists( 'wc_get_page_id' ) ) {
		foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
			$id = (int) wc_get_page_id( $page );
			if ( $id > 0 ) {
				$ids = array( $id );
				if ( function_exists( 'flxl_get_translations' ) ) {
					foreach ( flxhl_arr( flxl_get_translations( $id ) ) as $t ) {
						$ids[] = (int) $t;
					}
				}
				foreach ( array_unique( $ids ) as $pid ) {
					$path = FLXHL_Frontend_Service::front_path( flxhl_str( get_permalink( $pid ) ) );
					if ( null !== $path && '/' !== $path ) {
						$paths[] = $path;
					}
				}
			}
		}
	}
	$paths = array_values( array_unique( array_map( 'flxhl_str', flxhl_arr( apply_filters( 'flx_headless_live_paths', $paths ) ) ) ) );
	return $paths;
}

/** Whether the page being rendered belongs to one visitor (cart, checkout, account or a live path). */
function flxhl_page_is_live(): bool {
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
		return true;
	}
	$path = FLXHL_Frontend_Service::front_path( flxhl_str( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ) ) ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	foreach ( flxhl_live_paths() as $live ) {
		if ( '' !== $path && 0 === strpos( $path, $live ) ) {
			return true;
		}
	}
	return false;
}

/** Address on the front end for a WordPress path, or null when it stays on WordPress. */
function flxhl_page_target( string $path ): ?string {
	$p = flxhl_str( wp_parse_url( $path, PHP_URL_PATH ) );
	$p = '' === $p ? '/' : $p;
	foreach ( array( '/wp-content/', '/wp-includes/', '/wp-json/' ) as $prefix ) {
		if ( 0 === strpos( $p, $prefix ) ) {
			return $path;
		}
	}
	if ( '/wp-admin/admin-ajax.php' === $p ) {
		return $path;
	}
	return flxhl_path_is_backend( $p ) ? null : $path;
}

/** Rewrites every WordPress address in a page to the front end (plain, protocol-relative and JSON-escaped). */
function flxhl_page_rewrite( string $html, FLXHL_Frontend $fe ): string {
	$home  = flxhl_home();
	$parts = wp_parse_url( $home );
	if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
		return $html;
	}
	$host  = $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
	$base  = isset( $parts['path'] ) ? rtrim( flxhl_str( $parts['path'] ), '/' ) : '';
	$front = rtrim( $fe->url, '/' );
	$slash = '(?:/|\\\\/)';
	$re    = '#(?:https?:)?' . $slash . $slash . preg_quote( $host, '#' ) . '(?![\w.-])(' . str_replace( '/', $slash, preg_quote( $base, '#' ) ) . ')?((?:' . $slash . '[^\s"\'<>()\\\\]*(?:\\\\/[^\s"\'<>()\\\\]*)*)?)#i';
	$out   = preg_replace_callback(
		$re,
		static function ( array $m ) use ( $front, $base ): string {
			$escaped = false !== strpos( $m[0], '\\/' );
			$path    = str_replace( '\\/', '/', $m[2] ?? '' );
			if ( '' !== $base && '' === ( $m[1] ?? '' ) ) {
				return $m[0]; // Another folder on the same host.
			}
			$path   = '' === $path ? '/' : $path;
			$target = flxhl_page_target( $path );
			if ( null === $target ) {
				return $m[0];
			}
			$url = $front . flxhl_page_strip( $target );
			return $escaped ? str_replace( '/', '\\/', $url ) : $url;
		},
		$html
	);
	return is_string( $out ) ? $out : $html;
}

/**
 * Cache tags of the page being rendered: its post or term, the listings it may show, the site and menus.
 *
 * @return list<string>
 */
function flxhl_page_tags(): array {
	$tags = array( 'flx-headless:all', 'flx-headless:html', FLXHL_Frontend_Service::CACHE_TAG, FLXHL_Site_Service::MENU_TAG, 'flx-headless:routes', 'flx-headless:archive:post' );
	$obj  = get_queried_object();
	if ( $obj instanceof WP_Post ) {
		$tags[] = 'flx-headless:post:' . $obj->ID;
		$tags[] = 'flx-headless:archive:' . $obj->post_type;
	} elseif ( $obj instanceof WP_Term ) {
		$tags[] = 'flx-headless:term:' . $obj->term_id;
	} elseif ( $obj instanceof WP_Post_Type ) {
		$tags[] = 'flx-headless:archive:' . $obj->name;
	} elseif ( $obj instanceof WP_User ) {
		$tags[] = 'flx-headless:author:' . $obj->ID;
	}
	return array_values( array_unique( array_map( 'flxhl_str', flxhl_arr( apply_filters( 'flx_headless_page_tags', $tags, $obj ) ) ) ) );
}

/* Anonymous view, never the admin bar; caches keep this variant apart (the query string differs). */
add_action(
	'init',
	static function () {
		if ( null === flxhl_page_frontend() ) {
			return;
		}
		add_filter( 'show_admin_bar', '__return_false' );
		header( 'X-Robots-Tag: noindex', true );
	},
	0
);

/* Pages, redirects and 404s of a whole-page request. Runs before the "WordPress pages" redirect (priority 1). */
add_action(
	'template_redirect',
	static function () {
		$fe = flxhl_page_frontend();
		if ( null === $fe ) {
			return;
		}
		header( 'X-Flx-Cache-Tags: ' . implode( ',', flxhl_page_tags() ), true );
		if ( flxhl_page_is_live() ) {
			header( 'X-Flx-Live: 1', true );
		}
		// The buffer opened when the plugin loaded rewrites the page after every optimizer has run.
		$GLOBALS['flxhl_page_rewrite'] = $fe;
	},
	0
);

/* Redirects (canonical, old slugs, Flux SEO) point at the front end. */
add_filter(
	'wp_redirect',
	static function ( $location ) {
		$fe = flxhl_page_frontend();
		if ( null === $fe || ! is_string( $location ) ) {
			return $location;
		}
		return flxhl_page_rewrite( $location, $fe );
	},
	99
);
