<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Core\Database;

/**
 * Base class for table stores.
 */
abstract class Query {

	protected string $table;

	public function __construct( string $table ) {
		$this->table = $table;
	}

	public function table(): string {
		return $this->table;
	}

	public function table_with_prefix(): string {
		global $wpdb;

		return $wpdb->prefix . $this->table;
	}

	public function qb(): Query_Builder {
		return new Query_Builder( $this->table_with_prefix() );
	}
}
