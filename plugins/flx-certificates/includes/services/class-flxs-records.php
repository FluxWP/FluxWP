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
		$code = (string) $e->get_error_code();
		$gql  = 'INVALID_INPUT';
		if ( in_array( $code, array( 'flxs_404', 'flxs_template' ), true ) ) {
			$gql = 'NOT_FOUND';
		} elseif ( 'flxs_has_certs' === $code ) {
			$gql = 'CONFLICT';
		} elseif ( in_array( $code, array( 'flxs_db', 'flxs_person_db' ), true ) ) {
			$gql = 'INTERNAL';
		}
		$field = 'flxs_phone' === $code ? 'phone' : ( 'flxs_email' === $code ? 'email' : ( 'flxs_name' === $code ? 'name' : '' ) );
		return self::error( $code, (string) $e->get_error_message(), $gql, $field );
	}

	/**
	 * Drops the GraphQL cache of the records that changed.
	 *
	 * @param string[] $tags Tags.
	 */
	public static function purge( array $tags ): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( $tags );
		}
	}

	/**
	 * @param int $id Certificate id.
	 * @return string[]
	 */
	public static function certificate_tags( int $id, string $code = '' ): array {
		$tags = array( self::TAG_LIST, 'flx-certificates:certificate:' . $id );
		if ( '' !== $code ) {
			$tags[] = 'flx-certificates:verify:' . $code;
		}
		return $tags;
	}
}
