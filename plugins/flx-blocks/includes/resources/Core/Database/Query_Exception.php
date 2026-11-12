<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Core\Database;

use RuntimeException;

/**
 * Thrown when a database write fails.
 */
class Query_Exception extends RuntimeException {

	private string $query;

	private array $query_errors;

	public function __construct( string $message = '', string $query = '', array $query_errors = [] ) {
		parent::__construct( $message );
		$this->query        = $query;
		$this->query_errors = $query_errors;
	}

	public function getQuery(): string {
		return $this->query;
	}

	public function getQueryErrors(): array {
		return $this->query_errors;
	}
}
