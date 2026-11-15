<?php
/**
 * Relay cursor connections for package lists.
 *
 *     flx_gql_connection( 'FlxTask' );                       // FlxTaskConnection + FlxTaskEdge
 *     flx_gql_query( 'flxTasks', array(
 *         'type' => 'FlxTaskConnection!', 'cap' => 'read',
 *         'args' => flx_gql_connection_args( array( 'list' => array( 'type' => 'ID' ) ) ),
 *         'resolve' => fn( $root, $args ) => flx_gql_paginate( $args, fn( $offset, $limit ) => FLXG_Task_Service::page( $args, $offset, $limit ) ),
 *     ) );
 *
 * Cursors are opaque offsets (forward paging: first / after). A page holds at most the
 * "max_page" setting (50 by default, shared hosting).
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'flx_gql_register_core',
	static function () {
		flx_gql_type(
			'FlxPageInfo',
			array(
				'description' => __( 'Where a page sits in a list.', 'flx-graphql' ),
				'fields'      => array(
					'hasNextPage'     => array(
						'type'        => 'Boolean!',
						'description' => __( 'More records follow this page.', 'flx-graphql' ),
					),
					'hasPreviousPage' => array(
						'type'        => 'Boolean!',
						'description' => __( 'Records come before this page.', 'flx-graphql' ),
					),
					'startCursor'     => array(
						'type'        => 'String',
						'description' => __( 'Cursor of the first record on this page.', 'flx-graphql' ),
					),
					'endCursor'       => array(
						'type'        => 'String',
						'description' => __( 'Cursor of the last record on this page; pass it as "after" for the next page.', 'flx-graphql' ),
					),
				),
			)
		);
	}
);

/**
 * Registers <Node>Connection and <Node>Edge.
 *
 * @param string $node        Node type name (FlxTask).
 * @param string $description Optional connection description.
 */
function flx_gql_connection( string $node, string $description = '' ): bool {
	$ok = flx_gql_type(
		$node . 'Edge',
		array(
			/* translators: %s: GraphQL type name. */
			'description' => sprintf( __( 'One %s in a list, with its cursor.', 'flx-graphql' ), $node ),
			'fields'      => array(
				'cursor' => array(
					'type'        => 'String!',
					'description' => __( 'Opaque cursor of this record.', 'flx-graphql' ),
				),
				'node'   => array(
					'type'        => $node . '!',
					'description' => __( 'The record.', 'flx-graphql' ),
				),
			),
		)
	);
	return $ok && flx_gql_type(
		$node . 'Connection',
		array(
			/* translators: %s: GraphQL type name. */
			'description' => '' !== $description ? $description : sprintf( __( 'A page of %s records.', 'flx-graphql' ), $node ),
			'fields'      => array(
				'edges'      => array(
					'type'        => '[' . $node . 'Edge!]!',
					'description' => __( 'Records with their cursors.', 'flx-graphql' ),
				),
				'nodes'      => array(
					'type'        => '[' . $node . '!]!',
					'description' => __( 'Records of this page.', 'flx-graphql' ),
				),
				'pageInfo'   => array(
					'type'        => 'FlxPageInfo!',
					'description' => __( 'Paging information.', 'flx-graphql' ),
				),
				'totalCount' => array(
					'type'        => 'Int',
					'description' => __( 'All matching records (null when counting would be slow).', 'flx-graphql' ),
				),
			),
		)
	);
}

/**
 * Standard connection arguments plus the field's own filters.
 *
 * @param array<string,mixed> $extra Extra arguments.
 * @return array<string,mixed>
 */
function flx_gql_connection_args( array $extra = array() ): array {
	return array(
		'first' => array(
			'type'        => 'Int',
			/* translators: %d: largest page size. */
			'description' => sprintf( __( 'Records per page (at most %d).', 'flx-graphql' ), flx_gql_settings()['max_page'] ),
		),
		'after' => array(
			'type'        => 'String',
			'description' => __( 'Cursor to continue after (pageInfo.endCursor).', 'flx-graphql' ),
		),
	) + $extra;
}

/**
 * Cursor of an offset.
 *
 * @param int $offset Zero-based offset.
 */
function flx_gql_cursor( int $offset ): string {
	return base64_encode( 'flxo:' . $offset ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/**
 * Offset after a cursor (0 for none).
 *
 * @param mixed $cursor Cursor.
 * @throws FLXGQL_Error When the cursor is not ours.
 */
function flx_gql_cursor_offset( $cursor ): int {
	if ( null === $cursor || '' === $cursor ) {
		return 0;
	}
	$raw = base64_decode( flx_gql_str( $cursor ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	if ( false === $raw || ! preg_match( '/^flxo:(\d+)$/', $raw, $m ) ) {
		throw flx_gql_error( 'INVALID_INPUT', __( 'The page cursor is not valid.', 'flx-graphql' ), 'after' );
	}
	return (int) $m[1] + 1;
}

/**
 * Page size from "first", clamped to the setting.
 *
 * @param array<string,mixed> $args    Field arguments.
 * @param int                 $default Size when "first" is missing.
 */
function flx_gql_page_size( array $args, int $default = 20 ): int {
	$max   = max( 1, flx_gql_settings()['max_page'] );
	$first = flx_gql_int( $args['first'] ?? null, $default );
	return max( 1, min( $max, $first ) );
}

/**
 * Builds a connection from an offset fetcher.
 *
 * @param array<string,mixed> $args  Field arguments (first, after).
 * @param callable            $fetch fn( int $offset, int $limit ): array{items: array, total?: int|null}
 *                                   or a plain list. Ask for $limit + 1 to know whether a next page exists.
 * @return array<string,mixed>
 */
function flx_gql_paginate( array $args, callable $fetch ): array {
	$offset = flx_gql_cursor_offset( $args['after'] ?? null );
	$limit  = flx_gql_page_size( $args );
	$res    = flx_gql_normalize_value( call_user_func( $fetch, $offset, $limit + 1 ) );
	$items  = is_array( $res ) && array_key_exists( 'items', $res ) ? (array) $res['items'] : (array) $res;
	$total  = is_array( $res ) && array_key_exists( 'total', $res ) && null !== $res['total'] ? flx_gql_int( $res['total'] ) : null;
	$items  = array_values( $items );
	$more   = count( $items ) > $limit;
	$items  = array_slice( $items, 0, $limit );
	$edges  = array();
	foreach ( $items as $i => $node ) {
		$edges[] = array(
			'cursor' => flx_gql_cursor( $offset + $i ),
			'node'   => flx_gql_normalize_value( $node ),
		);
	}
	return array(
		'edges'      => $edges,
		'nodes'      => array_column( $edges, 'node' ),
		'pageInfo'   => array(
			'hasNextPage'     => $more,
			'hasPreviousPage' => $offset > 0,
			'startCursor'     => $edges ? $edges[0]['cursor'] : null,
			'endCursor'       => $edges ? $edges[ count( $edges ) - 1 ]['cursor'] : null,
		),
		'totalCount' => $total,
	);
}
