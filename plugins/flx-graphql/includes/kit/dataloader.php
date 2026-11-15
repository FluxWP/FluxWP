<?php
/**
 * DataLoader base for packages (N+1 protection).
 *
 *     final class FLXG_Task_Loader extends FLXGQL_Loader {
 *         protected function fetch( array $ids ): array { return FLXG_Task_Service::by_ids( $ids ); } // [ id => FLXG_Task ]
 *         protected function can_see( $record ): bool { return flxg_can_view_task( $record->list ); }
 *     }
 *     flx_gql_type( 'FlxTask', array( 'node' => FLXG_Task_Loader::class, 'fields' => … ) );
 *     // In a resolver: return $context->get_loader( 'FlxTask' )->load_deferred( $id );
 *
 * The loader key is the GraphQL type name, so node(id: "<global id>") finds the record too. Records
 * the current user may not see come back as null (not an error), like WordPress' own loaders.
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPGraphQL\Data\Loader\AbstractDataLoader' ) ) {
	return;
}

/**
 * Batches ids within one GraphQL request and loads them with one query.
 */
abstract class FLXGQL_Loader extends \WPGraphQL\Data\Loader\AbstractDataLoader {

	/**
	 * Loads records by id.
	 *
	 * @param array<int,int> $ids Database ids (unique, positive).
	 * @return array<int,mixed> Records keyed by id; missing ids are left out.
	 */
	abstract protected function fetch( array $ids ): array;

	/**
	 * Whether the current user may see the record. Default: yes (the root field checked the cap).
	 *
	 * @param mixed $record Record.
	 */
	protected function can_see( $record ): bool {
		return true;
	}

	/**
	 * WPGraphQL batch callback.
	 *
	 * @param array<int,mixed> $keys Keys.
	 * @return array<int|string,mixed>
	 */
	protected function loadKeys( array $keys ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$ids  = array_values( array_unique( array_filter( array_map( static fn( $k ): int => flx_gql_int( $k ), $keys ), static fn( int $id ): bool => $id > 0 ) ) );
		$rows = $ids ? $this->fetch( $ids ) : array();
		$out  = array();
		foreach ( $keys as $key ) {
			$id         = flx_gql_int( $key );
			$row        = $rows[ $id ] ?? null;
			$out[ $id ] = null !== $row && $this->can_see( $row ) ? $row : null;
		}
		return $out;
	}
}
