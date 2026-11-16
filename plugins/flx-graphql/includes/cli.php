<?php
/**
 * wp flx-gql schema: the schema snapshot with the owner of every type and field.
 *
 *     wp flx-gql schema --output=/tmp/schema.graphql --owners=/tmp/owners.json
 *
 * packages/graphql/scripts/schema.sh calls this and splits the result into one file per package
 * (docs/graphql/KURALLAR.md §7).
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

WP_CLI::add_command(
	'flx-gql schema',
	/**
	 * Prints the GraphQL schema (SDL) and its owners map.
	 *
	 * ## OPTIONS
	 *
	 * --output=<file>
	 * : SDL file to write.
	 *
	 * --owners=<file>
	 * : JSON file to write: { "FlxTask": "plugins/flx-tasks", "RootQuery.flxTasks": "plugins/flx-tasks", … }.
	 *
	 * @param array<int,string>    $args       Positional arguments.
	 * @param array<string,string> $assoc_args Options.
	 */
	static function ( array $args, array $assoc_args ): void {
		unset( $args );
		$output = is_string( $assoc_args['output'] ?? null ) ? $assoc_args['output'] : '';
		$owners = is_string( $assoc_args['owners'] ?? null ) ? $assoc_args['owners'] : '';
		if ( '' === $output || '' === $owners ) {
			WP_CLI::error( '--output and --owners are required.' );
		}
		if ( ! flx_gql_ready() || ! class_exists( 'WPGraphQL' ) ) {
			WP_CLI::error( 'WPGraphQL is not loaded.' );
		}
		if ( ! defined( 'GRAPHQL_REQUEST' ) ) {
			define( 'GRAPHQL_REQUEST', true );
		}
		flx_gql_schema_owners_start();
		do_action( 'init_graphql_request' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPGraphQL hook.
		WPGraphQL::set_is_introspection_query( true );
		$sdl = \GraphQL\Utils\SchemaPrinter::doPrint( WPGraphQL::get_schema() );
		WPGraphQL::set_is_introspection_query( false );
		$map = wp_json_encode( flx_gql_schema_owners(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- wp-cli output files.
		if ( false === file_put_contents( $output, $sdl ) || false === file_put_contents( $owners, (string) $map ) ) {
			WP_CLI::error( 'The output files could not be written.' );
		}
		// phpcs:enable
		WP_CLI::success( sprintf( '%d schema lines, %d owned coordinates.', substr_count( $sdl, "\n" ), count( flx_gql_schema_owners() ) ) );
	}
);
