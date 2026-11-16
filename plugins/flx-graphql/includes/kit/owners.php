<?php
/**
 * Schema owners: which package registered each type and field.
 *
 * Only used by `wp flx-gql schema` (includes/cli.php), which writes the schema snapshot as one part per
 * package (plugins/<slug>/graphql/schema.graphql, packages/graphql/schema/core.graphql), so parallel
 * package pull requests never edit the same snapshot file. Recording is off unless that command turns it
 * on: normal GraphQL requests pay nothing.
 *
 * An owner is the package folder relative to the monorepo (or wp-content) root: "plugins/flx-tasks",
 * "themes/fluxwp". It is found from the call stack: the first caller outside this kit folder.
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Starts recording owners (wp flx-gql schema).
 */
function flx_gql_schema_owners_start(): void {
	global $flx_gql_schema_owners;
	$flx_gql_schema_owners = array();
}

/**
 * Recorded owners: [ 'FlxTask' => 'plugins/flx-tasks', 'RootQuery.flxTasks' => 'plugins/flx-tasks', … ].
 *
 * @return array<string,string>
 */
function flx_gql_schema_owners(): array {
	global $flx_gql_schema_owners;
	$out = array();
	foreach ( is_array( $flx_gql_schema_owners ) ? $flx_gql_schema_owners : array() as $coordinate => $owner ) {
		if ( is_string( $owner ) ) {
			$out[ (string) $coordinate ] = $owner;
		}
	}
	ksort( $out );
	return $out;
}

/**
 * Whether owners are being recorded.
 */
function flx_gql_schema_owners_on(): bool {
	global $flx_gql_schema_owners;
	return is_array( $flx_gql_schema_owners );
}

/**
 * Records the owner of a type ("FlxTask") or a field ("RootQuery.flxTasks"). The first registration wins.
 *
 * @param string      $coordinate Type name or Type.field.
 * @param string|null $owner      Owner; null = the calling package.
 */
function flx_gql_schema_own( string $coordinate, ?string $owner = null ): void {
	global $flx_gql_schema_owners;
	if ( ! is_array( $flx_gql_schema_owners ) || isset( $flx_gql_schema_owners[ $coordinate ] ) ) {
		return;
	}
	$flx_gql_schema_owners[ $coordinate ] = $owner ?? flx_gql_schema_owner();
}

/**
 * Package folder of a PHP file: "plugins/<slug>" or "themes/<slug>", '' when it is in neither.
 *
 * @param string $file Absolute path.
 */
function flx_gql_owner_of_file( string $file ): string {
	$file = str_replace( '\\', '/', $file );
	$root = str_replace( '\\', '/', dirname( (string) realpath( dirname( __DIR__, 2 ) ), 2 ) ) . '/';
	if ( str_starts_with( $file, $root ) ) {
		$parts = explode( '/', substr( $file, strlen( $root ) ) );
		if ( count( $parts ) > 2 && in_array( $parts[0], array( 'plugins', 'themes' ), true ) ) {
			return $parts[0] . '/' . $parts[1];
		}
		return '';
	}
	// A package outside the root (a plugin folder that is not a link into the monorepo).
	if ( preg_match( '#/(plugins|themes)/([A-Za-z0-9_.-]+)/[^/]#', $file, $m ) ) {
		return $m[1] . '/' . $m[2];
	}
	return '';
}

/**
 * Owner of the code registering right now: an override (settings groups), else the first caller
 * outside the kit. Kit and WPGraphQL core registrations belong to plugins/flx-graphql.
 */
function flx_gql_schema_owner(): string {
	global $flx_gql_schema_owner_override;
	if ( is_string( $flx_gql_schema_owner_override ) && '' !== $flx_gql_schema_owner_override ) {
		return $flx_gql_schema_owner_override;
	}
	$kit = str_replace( '\\', '/', (string) realpath( __DIR__ ) ) . '/';
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- owner lookup, wp-cli only.
	foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $frame ) {
		$file = isset( $frame['file'] ) ? str_replace( '\\', '/', (string) $frame['file'] ) : '';
		if ( '' === $file || str_starts_with( $file, $kit ) ) {
			continue;
		}
		$owner = flx_gql_owner_of_file( $file );
		if ( '' !== $owner ) {
			return $owner;
		}
	}
	return 'plugins/flx-graphql';
}

/**
 * Runs $register with every registration inside it owned by $owner.
 *
 * @param string   $owner    Owner ('' = no override).
 * @param callable $register Registration code.
 */
function flx_gql_schema_owned_by( string $owner, callable $register ): void {
	global $flx_gql_schema_owner_override;
	$previous                      = $flx_gql_schema_owner_override;
	$flx_gql_schema_owner_override = $owner;
	try {
		$register();
	} finally {
		$flx_gql_schema_owner_override = $previous;
	}
}

/**
 * Owner of each `flx_settings_schema` option: the package whose filter callback added it.
 * Only computed while owners are recorded.
 *
 * @return array<string,string> [ option => owner ]
 */
function flx_gql_settings_owners(): array {
	global $wp_filter;
	$hook = is_array( $wp_filter ) ? ( $wp_filter['flx_settings_schema'] ?? null ) : null;
	if ( ! flx_gql_schema_owners_on() || ! $hook instanceof WP_Hook ) {
		return array();
	}
	$value  = array();
	$owners = array();
	$sorted = (array) $hook->callbacks;
	ksort( $sorted );
	foreach ( $sorted as $callbacks ) {
		foreach ( is_array( $callbacks ) ? $callbacks : array() as $cb ) {
			$fn = flx_gql_arr( $cb )['function'] ?? null;
			if ( ! is_callable( $fn ) ) {
				continue;
			}
			$before = array_keys( $value );
			$next   = call_user_func( $fn, $value );
			$value  = is_array( $next ) ? $next : array();
			$owner  = flx_gql_owner_of_file( flx_gql_callable_file( $fn ) );
			foreach ( array_diff( array_keys( $value ), $before ) as $option ) {
				$owners[ (string) $option ] = '' !== $owner ? $owner : 'plugins/flx-graphql';
			}
		}
	}
	return $owners;
}

/**
 * File that defines a callable ('' when unknown).
 *
 * @param callable $callable Callable.
 */
function flx_gql_callable_file( callable $callable ): string {
	try {
		if ( is_array( $callable ) ) {
			$ref = new ReflectionMethod( $callable[0], $callable[1] );
		} elseif ( is_string( $callable ) && str_contains( $callable, '::' ) ) {
			[ $class, $method ] = explode( '::', $callable, 2 );
			$ref                = new ReflectionMethod( $class, $method );
		} elseif ( $callable instanceof Closure || is_string( $callable ) ) {
			$ref = new ReflectionFunction( $callable );
		} elseif ( is_object( $callable ) ) {
			$ref = new ReflectionMethod( $callable, '__invoke' );
		} else {
			return '';
		}
		return (string) $ref->getFileName();
	} catch ( ReflectionException $e ) {
		return '';
	}
}
