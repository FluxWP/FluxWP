<?php
/**
 * Cache tags and HTTP caching of public persisted reads.
 *
 * Resolvers tag what they read:   flx_gql_cache_tag( 'flx-tasks:list:12' );
 * Writers purge what they change: flx_gql_purge( array( 'flx-tasks:list:12' ) );
 *
 * A GET with a persisted queryId, from a visitor who is not signed in, is cacheable for the
 * "cache_ttl" setting: Cache-Control public + LiteSpeed (X-LiteSpeed-Cache-Control, X-LiteSpeed-Tag)
 * + X-GraphQL-Keys (Smart Cache / Varnish style). Signed-in requests and POSTs are never cached
 * (WPGraphQL sends no-cache for them). flx_gql_purge() purges the LiteSpeed tags and fires
 * graphql_purge for other caches.
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds cache tags to the current GraphQL response.
 *
 * @param string|array<int,string> $tags Tags ("<package>:<thing>[:<id>]").
 */
function flx_gql_cache_tag( $tags ): void {
	global $flx_gql_cache_tags;
	$flx_gql_cache_tags = is_array( $flx_gql_cache_tags ) ? $flx_gql_cache_tags : array();
	foreach ( (array) $tags as $t ) {
		$t = preg_replace( '/[^A-Za-z0-9:_.-]/', '', (string) $t );
		if ( '' !== $t ) {
			$flx_gql_cache_tags[ $t ] = true;
		}
	}
}

/**
 * Tags collected for this response.
 *
 * @return array<int,string>
 */
function flx_gql_cache_tags(): array {
	global $flx_gql_cache_tags;
	return is_array( $flx_gql_cache_tags ) ? array_keys( $flx_gql_cache_tags ) : array();
}

/**
 * LiteSpeed tag form (short, site-scoped).
 *
 * @param string $tag Tag.
 */
function flx_gql_ls_tag( string $tag ): string {
	return 'flxgql_' . substr( md5( $tag ), 0, 12 );
}

/**
 * Purges cached responses that carry any of the tags.
 *
 * @param string|array<int,string> $tags Tags.
 */
function flx_gql_purge( $tags ): void {
	foreach ( (array) $tags as $t ) {
		$t = (string) $t;
		if ( '' === $t ) {
			continue;
		}
		do_action( 'litespeed_purge', flx_gql_ls_tag( $t ) );
		do_action( 'graphql_purge', $t, 'flx_gql_purge', wp_parse_url( home_url(), PHP_URL_HOST ) );
	}
	/**
	 * Cache tags were purged.
	 *
	 * @param array<int,string> $tags Tags.
	 */
	do_action( 'flx_gql_purged', (array) $tags );
}

add_filter(
	'graphql_response_headers_to_send',
	static function ( $headers ) {
		$headers = is_array( $headers ) ? $headers : array();
		$tags    = flx_gql_cache_tags();
		if ( $tags ) {
			$headers['X-GraphQL-Keys'] = implode( ' ', $tags );
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		$ttl    = (int) flx_gql_opt( 'cache_ttl' );
		if ( 'GET' === $method && $ttl > 0 && flx_gql_persisted_only() && ! is_user_logged_in() && ! isset( $headers['Cache-Control'] ) ) {
			$headers['Cache-Control']            = 'public, max-age=' . $ttl;
			$headers['X-LiteSpeed-Cache-Control'] = 'public,max-age=' . $ttl;
			if ( $tags ) {
				$headers['X-LiteSpeed-Tag'] = implode( ',', array_map( 'flx_gql_ls_tag', $tags ) );
			}
		} else {
			$headers['X-LiteSpeed-Cache-Control'] = 'no-cache';
		}
		return $headers;
	},
	30
);
