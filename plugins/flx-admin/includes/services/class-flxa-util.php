<?php
/**
 * Typed access to WordPress' mixed values (services must work without Flux GraphQL).
 *
 * @package flx-admin
 */

defined( 'ABSPATH' ) || exit;

final class FLXA_Util {

	/**
	 * A scalar as string (anything else: the default).
	 *
	 * @param mixed $v       Value.
	 * @param string $default Default.
	 */
	public static function str( $v, string $default = '' ): string {
		return is_scalar( $v ) ? (string) $v : $default;
	}

	/**
	 * A number or numeric string as int (anything else: the default).
	 *
	 * @param mixed $v       Value.
	 * @param int   $default Default.
	 */
	public static function int( $v, int $default = 0 ): int {
		if ( is_int( $v ) ) {
			return $v;
		}
		return is_numeric( $v ) || is_bool( $v ) ? (int) $v : $default;
	}

	/**
	 * An array with string keys (anything else: empty).
	 *
	 * @param mixed $v Value.
	 * @return array<string,mixed>
	 */
	public static function arr( $v ): array {
		$out = array();
		if ( is_array( $v ) ) {
			foreach ( $v as $k => $x ) {
				$out[ (string) $k ] = $x;
			}
		}
		return $out;
	}

	/**
	 * A list of strings (non-scalars dropped).
	 *
	 * @param mixed $v Value.
	 * @return list<string>
	 */
	public static function strings( $v ): array {
		$out = array();
		foreach ( is_array( $v ) ? $v : array() as $x ) {
			if ( is_scalar( $x ) ) {
				$out[] = (string) $x;
			}
		}
		return $out;
	}

	/**
	 * Clears GraphQL caches when Flux GraphQL is there.
	 *
	 * @param list<string> $tags Cache tags.
	 */
	public static function purge( array $tags ): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( $tags );
		}
	}

	/**
	 * Error the GraphQL layer maps to a code (and the form field).
	 *
	 * @param string $id      Error id.
	 * @param string $message Message in the user's language.
	 * @param string $code    INVALID_INPUT / NOT_FOUND / FORBIDDEN.
	 * @param string $field   Input field.
	 */
	public static function error( string $id, string $message, string $code = 'INVALID_INPUT', string $field = '' ): WP_Error {
		$data = array( 'graphql_code' => $code );
		if ( '' !== $field ) {
			$data['field'] = $field;
		}
		return new WP_Error( $id, $message, $data );
	}
}
