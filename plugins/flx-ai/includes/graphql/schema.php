<?php
/**
 * Flux AI GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a GraphQL
 * request. Resolvers call the services in includes/services/; the Flux AI screens (wp-admin and the
 * FluxWP app) read and write only through these fields.
 *
 * Not in GraphQL (own protocols, docs/graphql/ISTISNALAR.md): the MCP server and its OAuth, the chat
 * stream (SSE) and file upload. API keys are write-only: a query only says whether a key is set.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Typed parent value of a field.
 *
 * @template T of object
 * @param mixed           $source Parent.
 * @param class-string<T> $class  Expected class.
 * @return T
 * @throws FLXGQL_Error When the parent has another type.
 */
function flxai_gql_src( $source, string $class ) {
	if ( ! $source instanceof $class ) {
		throw flx_gql_error( 'INTERNAL' );
	}
	return $source;
}

/**
 * Service result → value, or the WP_Error as a typed GraphQL error.
 *
 * @template T
 * @param T|WP_Error $r Result.
 * @return T
 * @throws FLXGQL_Error On WP_Error.
 */
function flxai_gql_ok( $r ) {
	if ( $r instanceof WP_Error ) {
		throw flx_gql_from_wp_error( $r );
	}
	return $r;
}

/**
 * Field map of an object type that reads a service value object. Each entry:
 * name => array( type, description, [property], [kind] ). The property defaults to the snake_case of
 * the name; kind `time` turns a Unix timestamp (or null) into FlxDateTime.
 *
 * @param class-string                                       $class Value object class.
 * @param array<string,array{0:string,1:string,2?:string,3?:string}> $spec  Fields.
 * @return array<string,array<string,mixed>>
 */
function flxai_gql_fields( string $class, array $spec ): array {
	$out = array();
	foreach ( $spec as $name => $f ) {
		$prop = $f[2] ?? strtolower( (string) preg_replace( '/(?<!^)[A-Z]/', '_$0', $name ) );
		$time = 'time' === ( $f[3] ?? '' );
		$out[ $name ] = array(
			'type'        => $f[0],
			'description' => $f[1],
			'resolve'     => static function ( $source ) use ( $class, $prop, $time ) {
				$obj = flxai_gql_src( $source, $class );
				$v   = $obj->{$prop};
				return $time ? flx_gql_datetime( $v ) : $v;
			},
		);
	}
	return $out;
}

require_once __DIR__ . '/schema-usage.php';
require_once __DIR__ . '/schema-settings.php';
require_once __DIR__ . '/schema-tools.php';
require_once __DIR__ . '/schema-approvals.php';
