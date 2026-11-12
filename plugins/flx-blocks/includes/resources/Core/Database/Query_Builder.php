<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Core\Database;

/**
 * Tiny fluent query builder over $wpdb (only what the stores need).
 */
final class Query_Builder {

	private string $table;

	/** @var array<int,array{0:string,1:string,2:mixed}> */
	private array $wheres = [];

	/** @var string[] */
	private array $columns = [];

	private string $order = '';

	private ?int $limit = null;

	private ?int $offset = null;

	public function __construct( string $table ) {
		$this->table = $table;
	}

	public function select( string ...$columns ): self {
		$this->columns = $columns;

		return $this;
	}

	/**
	 * @param mixed $value Value.
	 */
	public function where( string $column, $value, string $operator = '=' ): self {
		$operator = in_array( $operator, [ '=', '!=', '<', '<=', '>', '>=' ], true ) ? $operator : '=';

		$this->wheres[] = [ $column, $operator, $value ];

		return $this;
	}

	public function orderBy( string $column, string $direction = 'ASC' ): self {
		$this->order = sprintf( ' ORDER BY %s %s', $this->col( $column ), 'DESC' === strtoupper( $direction ) ? 'DESC' : 'ASC' );

		return $this;
	}

	public function limit( int $limit ): self {
		$this->limit = max( 0, $limit );

		return $this;
	}

	public function offset( int $offset ): self {
		$this->offset = max( 0, $offset );

		return $this;
	}

	/**
	 * @return array|object|null First row.
	 */
	public function get( string $output = OBJECT ) {
		global $wpdb;
		$this->limit = 1;

		return $wpdb->get_row( $this->select_sql(), $output ); // phpcs:ignore WordPress.DB
	}

	/**
	 * @return array Rows.
	 */
	public function getAll( string $output = OBJECT ): array {
		global $wpdb;

		return (array) $wpdb->get_results( $this->select_sql(), $output ); // phpcs:ignore WordPress.DB
	}

	public function count(): int {
		global $wpdb;
		[ $where, $args ] = $this->where_sql();
		$sql              = "SELECT COUNT(*) FROM `{$this->table}`" . $where;

		return (int) $wpdb->get_var( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB
	}

	/**
	 * @throws Query_Exception On failure.
	 */
	public function insert( array $data ): int {
		global $wpdb;

		return $this->check( $wpdb->insert( $this->table, $data ) );
	}

	/**
	 * @throws Query_Exception On failure.
	 */
	public function update( array $data ): int {
		global $wpdb;
		[ $where, $args ] = $this->where_sql();
		$sets             = [];
		$values           = [];
		foreach ( $data as $column => $value ) {
			$sets[]   = $this->col( $column ) . ' = ' . $this->placeholder( $value );
			$values[] = $value;
		}
		$sql = "UPDATE `{$this->table}` SET " . implode( ', ', $sets ) . $where;

		return $this->check( $wpdb->query( $wpdb->prepare( $sql, array_merge( $values, $args ) ) ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * @throws Query_Exception On failure.
	 */
	public function delete(): int {
		global $wpdb;
		[ $where, $args ] = $this->where_sql();
		$sql              = "DELETE FROM `{$this->table}`" . $where;

		return $this->check( $wpdb->query( $args ? $wpdb->prepare( $sql, $args ) : $sql ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Updates the row matching the key columns, or inserts it.
	 *
	 * @param string[] $keys Unique key columns.
	 *
	 * @throws Query_Exception On failure.
	 */
	public function upsert( array $data, array $keys ): int {
		$match = new self( $this->table );
		foreach ( $keys as $key ) {
			$match->where( $key, $data[ $key ] ?? '' );
		}

		if ( $match->count() > 0 ) {
			return $match->update( $data );
		}

		return $this->insert( $data );
	}

	private function select_sql(): string {
		global $wpdb;
		[ $where, $args ] = $this->where_sql();
		$columns          = $this->columns ? implode( ', ', array_map( [ $this, 'col' ], $this->columns ) ) : '*';
		$sql              = "SELECT {$columns} FROM `{$this->table}`" . $where . $this->order;
		if ( null !== $this->limit ) {
			$sql .= ' LIMIT ' . (int) $this->limit;
			if ( null !== $this->offset ) {
				$sql .= ' OFFSET ' . (int) $this->offset;
			}
		}

		return $args ? $wpdb->prepare( $sql, $args ) : $sql; // phpcs:ignore WordPress.DB
	}

	/**
	 * @return array{0:string,1:array}
	 */
	private function where_sql(): array {
		if ( ! $this->wheres ) {
			return [ '', [] ];
		}
		$parts = [];
		$args  = [];
		foreach ( $this->wheres as [ $column, $operator, $value ] ) {
			$parts[] = $this->col( $column ) . " {$operator} " . $this->placeholder( $value );
			$args[]  = $value;
		}

		return [ ' WHERE ' . implode( ' AND ', $parts ), $args ];
	}

	/**
	 * @param mixed $value Value.
	 */
	private function placeholder( $value ): string {
		if ( is_int( $value ) || is_bool( $value ) ) {
			return '%d';
		}

		return is_float( $value ) ? '%f' : '%s';
	}

	private function col( string $column ): string {
		return '`' . preg_replace( '/[^A-Za-z0-9_]/', '', $column ) . '`';
	}

	/**
	 * @param int|false $result Result of a write.
	 *
	 * @throws Query_Exception On failure.
	 */
	private function check( $result ): int {
		global $wpdb;
		if ( false === $result ) {
			throw new Query_Exception( 'Database write failed: ' . $wpdb->last_error, (string) $wpdb->last_query, [ $wpdb->last_error ] );
		}

		return (int) $result;
	}
}
