<?php
/**
 * CORS for /graphql.
 *
 * WPGraphQL answers every origin with "*". Flux GraphQL narrows that: the site itself and the origins in
 * Settings › Flux GraphQL › Front-end origins get their Origin echoed back; others get no
 * Access-Control-Allow-Origin header, so a browser on a foreign site cannot read the response.
 * Server-to-server calls (no Origin header) and the FluxApp desktop/mobile apps are not affected.
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Allowed browser origins.
 *
 * @return array<int,string>
 */
function flx_gql_cors_origins(): array {
	$own = array();
	foreach ( array( home_url(), site_url() ) as $u ) {
		$p = wp_parse_url( $u );
		if ( ! empty( $p['scheme'] ) && ! empty( $p['host'] ) ) {
			$own[] = strtolower( $p['scheme'] . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' ) );
		}
	}
	$extra = array_filter( array_map( 'trim', explode( "\n", (string) flx_gql_opt( 'cors_origins' ) ) ) );
	/**
	 * Browser origins allowed to call /graphql.
	 *
	 * @param array<int,string> $origins Origins (scheme://host[:port]).
	 */
	return array_values( array_unique( (array) apply_filters( 'flx_gql_cors_origins', array_merge( $own, $extra ) ) ) );
}

add_filter(
	'graphql_response_headers_to_send',
	static function ( $headers ) {
		$headers = is_array( $headers ) ? $headers : array();
		$origin  = isset( $_SERVER['HTTP_ORIGIN'] ) ? strtolower( untrailingslashit( esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) ) ) : '';
		if ( 'open' === flx_gql_opt( 'query_mode' ) && '' === (string) flx_gql_opt( 'cors_origins' ) ) {
			return $headers; // Fully open API: keep WPGraphQL's "*".
		}
		unset( $headers['Access-Control-Allow-Origin'] );
		if ( '' !== $origin && in_array( $origin, flx_gql_cors_origins(), true ) ) {
			$headers['Access-Control-Allow-Origin'] = $origin;
			$headers['Vary']                        = trim( ( $headers['Vary'] ?? '' ) . ', Origin', ', ' );
		}
		return $headers;
	},
	20
);

add_filter(
	'graphql_access_control_allow_headers',
	static function ( $allowed ) {
		return array_values( array_unique( array_merge( (array) $allowed, array( 'X-WP-Nonce', 'X-FluxWP-Token', 'X-Flux-Locale' ) ) ) );
	}
);
