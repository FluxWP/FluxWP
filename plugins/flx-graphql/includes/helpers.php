<?php
/**
 * Small helpers shared by the kit.
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

/**
 * "flxb_wc_look" / "flx-tasks" → "FlxbWcLook" / "FlxTasks".
 *
 * @param string $s Snake, kebab or space separated name.
 */
function flx_gql_pascal( string $s ): string {
	$parts = preg_split( '/[^a-zA-Z0-9]+/', $s ) ?: array();
	return implode( '', array_map( static fn( $p ) => ucfirst( strtolower( $p ) ), array_filter( $parts, 'strlen' ) ) );
}

/**
 * "flxb_wc_look" → "flxbWcLook".
 *
 * @param string $s Name.
 */
function flx_gql_camel( string $s ): string {
	return lcfirst( flx_gql_pascal( $s ) );
}

/**
 * Records a kit registration mistake. Shown in the system check and, with WP_DEBUG, as _doing_it_wrong.
 *
 * @param string $what    Type or field name.
 * @param string $message English developer message (not translated: it is for the package author).
 */
function flx_gql_registration_error( string $what, string $message ): void {
	global $flx_gql_registration_errors;
	$flx_gql_registration_errors          = is_array( $flx_gql_registration_errors ) ? $flx_gql_registration_errors : array();
	$flx_gql_registration_errors[ $what ] = $message;
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		_doing_it_wrong( esc_html( 'flx_gql:' . $what ), esc_html( $message ), esc_html( FLXGQL_VERSION ) );
	}
}

/**
 * Registration mistakes of this request.
 *
 * @return array<string,string>
 */
function flx_gql_registration_errors(): array {
	global $flx_gql_registration_errors;
	return is_array( $flx_gql_registration_errors ) ? $flx_gql_registration_errors : array();
}

/**
 * Public GraphQL endpoint URL.
 */
function flx_gql_endpoint(): string {
	return function_exists( 'graphql_get_endpoint_url' ) ? (string) graphql_get_endpoint_url() : home_url( '/graphql' );
}

/**
 * Whether the current request is a GraphQL HTTP request.
 */
function flx_gql_is_request(): bool {
	return function_exists( 'is_graphql_http_request' ) && is_graphql_http_request();
}
