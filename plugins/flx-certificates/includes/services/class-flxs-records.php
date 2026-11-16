<?php
/**
 * Records, people and the public verification/lookup. REST, admin-post and GraphQL call these
 * methods; the classic JSON and redirect texts stay the ones the screens already return.
 *
 * @package flx-certificates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shared helpers for the records services.
 */
final class FLXS_Records {

	public const TAG_LIST     = 'flx-certificates:list';
	public const TAG_PEOPLE   = 'flx-certificates:people';
	public const TAG_SETTINGS = 'flx-certificates:settings';

	/**
	 * A WP_Error the GraphQL layer maps to a stable code.
	 *
	 * @param string $gql   INVALID_INPUT | NOT_FOUND | CONFLICT | FORBIDDEN | RATE_LIMITED.
	 * @param string $field Input field.
	 */
	/**
	 * The classic REST error, typed for the services. flxs_rest_error() is untyped.
	 *
	 * @param mixed $e Error or message.
	 */
	public static function rest_response( $e, int $status = 400 ): WP_REST_Response {
		$response = flxs_rest_error( $e, $status );
		if ( $response instanceof WP_REST_Response ) {
			return $response;
		}
		return new WP_REST_Response( array( 'ok' => false, 'message' => is_string( $e ) ? $e : '' ), $status );
	}

	public static function error( string $code, string $message, string $gql = 'INVALID_INPUT', string $field = '', int $status = 400 ): WP_Error {
		$data = array(
			'graphql_code' => $gql,
			'status'       => $status,
		);
		if ( '' !== $field ) {
			$data['field'] = $field;
		}
		return new WP_Error( $code, $message, $data );
	}

	/**
	 * Gives an existing WP_Error a GraphQL code when it has none.
	 */
	public static function classify( WP_Error $e ): WP_Error {
		$data = $e->get_error_data();
		if ( is_array( $data ) && ! empty( $data['graphql_code'] ) ) {
			return $e;
		}
		$code = FLXS_Val::str($e->get_error_code());
		$gql  = 'INVALID_INPUT';
		if ( in_array( $code, array( 'flxs_404', 'flxs_template' ), true ) ) {
			$gql = 'NOT_FOUND';
		} elseif ( 'flxs_has_certs' === $code ) {
			$gql = 'CONFLICT';
		} elseif ( in_array( $code, array( 'flxs_db', 'flxs_person_db' ), true ) ) {
			$gql = 'INTERNAL';
		}
		$field = 'flxs_phone' === $code ? 'phone' : ( 'flxs_email' === $code ? 'email' : ( 'flxs_name' === $code ? 'name' : '' ) );
		return self::error( $code, FLXS_Val::str($e->get_error_message()), $gql, $field );
	}

	/**
	 * Drops the GraphQL cache of the records that changed.
	 *
	 * @param list<string> $tags Tags.
	 */
	public static function purge( array $tags ): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( $tags );
		}
	}

	/**
	 * @param int $id Certificate id.
	 * @return list<string>
	 */
	public static function certificate_tags( int $id, string $code = '' ): array {
		$tags = array( self::TAG_LIST, 'flx-certificates:certificate:' . $id );
		if ( '' !== $code ) {
			$tags[] = 'flx-certificates:verify:' . $code;
		}
		return $tags;
	}
}

/**
 * Scalar coercion for database rows. Services run without the GraphQL kit, so this does not call it.
 */
final class FLXS_Val {

	/**
	 * @param mixed $v Value.
	 */
	public static function str( $v, string $default = '' ): string {
		return is_scalar( $v ) ? (string) $v : $default;
	}

	/**
	 * @param mixed $v Value.
	 */
	public static function int( $v, int $default = 0 ): int {
		return is_numeric( $v ) ? (int) $v : $default;
	}

	/**
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	public static function arr( $v ): array {
		return is_array( $v ) ? $v : array();
	}

	/**
	 * @param mixed $v Value.
	 */
	public static function mix( $v ): mixed {
		return $v;
	}

	/**
	 * @param mixed $v Value.
	 * @return array<string,mixed>
	 */
	public static function row( $v ): array {
		$out = array();
		if ( is_array( $v ) ) {
			foreach ( $v as $k => $x ) {
				$out[ (string) $k ] = $x;
			}
		}
		return $out;
	}
}
