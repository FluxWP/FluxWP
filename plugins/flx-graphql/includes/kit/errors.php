<?php
/**
 * Error model.
 *
 *  - A resolver throws FLXGQL_Error (or returns WP_Error): the response carries
 *    errors[].extensions = { code, field? } and a message in the user's language.
 *  - A mutation that fails on form input returns { <object>: null, errors: [FlxUserError] } instead,
 *    so a form can show the message next to the field (flx_gql_user_errors()).
 *
 * Codes are UPPER_SNAKE: FORBIDDEN, UNAUTHENTICATED, NOT_FOUND, INVALID_INPUT, CONFLICT, RATE_LIMITED, INTERNAL.
 * A WP_Error code (rest_forbidden, flxb_not_found …) is mapped by its HTTP status when it has one.
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'GraphQL\Error\UserError' ) || ! interface_exists( 'GraphQL\Error\ProvidesExtensions' ) ) {
	return;
}

/**
 * Client-safe GraphQL error with a stable code.
 */
class FLXGQL_Error extends \GraphQL\Error\UserError implements \GraphQL\Error\ProvidesExtensions {

	/**
	 * Stable machine code.
	 *
	 * @var string
	 */
	public string $flx_code;

	/**
	 * Input field the error belongs to (dotted path), if any.
	 *
	 * @var string|null
	 */
	public ?string $field;

	/**
	 * Extra client-safe data.
	 *
	 * @var array<string,mixed>
	 */
	public array $data;

	/**
	 * @param string              $code    UPPER_SNAKE code.
	 * @param string              $message Message in the user's language.
	 * @param string|null         $field   Input field.
	 * @param array<string,mixed> $data    Extra client-safe data.
	 */
	public function __construct( string $code, string $message, ?string $field = null, array $data = array() ) {
		parent::__construct( $message );
		$this->flx_code = $code;
		$this->field    = $field;
		$this->data     = $data;
	}

	/**
	 * Data under errors[].extensions.
	 *
	 * @return array<string,mixed>
	 */
	public function getExtensions(): ?array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$ext = array( 'code' => $this->flx_code );
		if ( null !== $this->field ) {
			$ext['field'] = $this->field;
		}
		return $this->data ? $ext + array( 'data' => $this->data ) : $ext;
	}
}

/**
 * Builds a client-safe error. Throw the result.
 *
 * @param string              $code    UPPER_SNAKE code.
 * @param string              $message Message in the user's language.
 * @param string|null         $field   Input field.
 * @param array<string,mixed> $data    Extra client-safe data.
 */
function flx_gql_error( string $code, string $message = '', ?string $field = null, array $data = array() ): FLXGQL_Error {
	if ( '' === $message ) {
		$message = flx_gql_error_message( $code );
	}
	return new FLXGQL_Error( $code, $message, $field, $data );
}

/**
 * Default message of a code, in the user's language.
 *
 * @param string $code UPPER_SNAKE code.
 */
function flx_gql_error_message( string $code ): string {
	switch ( $code ) {
		case 'UNAUTHENTICATED':
			return __( 'Please sign in again.', 'flx-graphql' );
		case 'FORBIDDEN':
			return __( 'You do not have permission to do this.', 'flx-graphql' );
		case 'NOT_FOUND':
			return __( 'The record was not found.', 'flx-graphql' );
		case 'INVALID_INPUT':
			return __( 'Some fields are not valid.', 'flx-graphql' );
		case 'CONFLICT':
			return __( 'The record was changed by someone else. Reload and try again.', 'flx-graphql' );
		case 'RATE_LIMITED':
			return __( 'Too many requests. Wait a moment and try again.', 'flx-graphql' );
		default:
			return __( 'Something went wrong. Please try again.', 'flx-graphql' );
	}
}

/**
 * Maps a WP_Error to a code: explicit "graphql_code" data, then HTTP status, then INTERNAL.
 *
 * @param WP_Error $e Error.
 */
function flx_gql_code_of( WP_Error $e ): string {
	$data = $e->get_error_data();
	if ( is_array( $data ) && ! empty( $data['graphql_code'] ) && is_string( $data['graphql_code'] ) ) {
		return $data['graphql_code'];
	}
	$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
	$map    = array(
		400 => 'INVALID_INPUT',
		401 => 'UNAUTHENTICATED',
		403 => 'FORBIDDEN',
		404 => 'NOT_FOUND',
		409 => 'CONFLICT',
		422 => 'INVALID_INPUT',
		429 => 'RATE_LIMITED',
	);
	if ( isset( $map[ $status ] ) ) {
		return $map[ $status ];
	}
	$code = (string) $e->get_error_code();
	if ( str_contains( $code, 'forbidden' ) || str_contains( $code, 'cannot' ) ) {
		return 'FORBIDDEN';
	}
	if ( str_contains( $code, 'not_found' ) || str_contains( $code, 'invalid_id' ) ) {
		return 'NOT_FOUND';
	}
	return 'INTERNAL';
}

/**
 * WP_Error → FLXGQL_Error (keeps the message; it is already translated by the package).
 *
 * @param WP_Error $e Error.
 */
function flx_gql_from_wp_error( WP_Error $e ): FLXGQL_Error {
	$data  = $e->get_error_data();
	$field = is_array( $data ) && isset( $data['field'] ) && is_string( $data['field'] ) ? $data['field'] : null;
	$code  = flx_gql_code_of( $e );
	$msg   = (string) $e->get_error_message();
	return new FLXGQL_Error( $code, '' !== $msg ? $msg : flx_gql_error_message( $code ), $field, array( 'reason' => (string) $e->get_error_code() ) );
}

/**
 * FlxUserError list for a mutation payload.
 *
 * @param WP_Error|FLXGQL_Error|array<int,array{code?:string,message?:string,field?:string}> $errors Errors.
 * @return array<int,array{code:string,message:string,field:?string}>
 */
function flx_gql_user_errors( $errors ): array {
	if ( $errors instanceof WP_Error ) {
		$out = array();
		foreach ( $errors->get_error_codes() as $code ) {
			$data = $errors->get_error_data( $code );
			foreach ( $errors->get_error_messages( $code ) as $msg ) {
				$out[] = array(
					'code'    => flx_gql_code_of( new WP_Error( $code, $msg, $data ) ),
					'message' => $msg,
					'field'   => is_array( $data ) && isset( $data['field'] ) ? (string) $data['field'] : null,
				);
			}
		}
		return $out;
	}
	if ( $errors instanceof FLXGQL_Error ) {
		return array(
			array(
				'code'    => $errors->flx_code,
				'message' => $errors->getMessage(),
				'field'   => $errors->field,
			),
		);
	}
	$out = array();
	foreach ( (array) $errors as $e ) {
		$code  = (string) ( $e['code'] ?? 'INVALID_INPUT' );
		$out[] = array(
			'code'    => $code,
			'message' => (string) ( $e['message'] ?? flx_gql_error_message( $code ) ),
			'field'   => isset( $e['field'] ) ? (string) $e['field'] : null,
		);
	}
	return $out;
}
