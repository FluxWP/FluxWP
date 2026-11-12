<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Core;

/**
 * Dot-notation access for nested arrays.
 */
final class Dot {

	private array $items;

	public function __construct( $items = [] ) {
		$this->items = is_array( $items ) ? $items : [];
	}

	/**
	 * @param string $key     Dot path.
	 * @param mixed  $default Fallback.
	 *
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		$value = $this->items;
		foreach ( explode( '.', $key ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $default;
			}
			$value = $value[ $segment ];
		}

		return $value;
	}

	/**
	 * @param string $key   Dot path.
	 * @param mixed  $value Value.
	 */
	public function set( string $key, $value ): self {
		$ref = &$this->items;
		foreach ( explode( '.', $key ) as $segment ) {
			if ( ! isset( $ref[ $segment ] ) || ! is_array( $ref[ $segment ] ) ) {
				$ref[ $segment ] = [];
			}
			$ref = &$ref[ $segment ];
		}
		$ref = $value;

		return $this;
	}

	public function all(): array {
		return $this->items;
	}
}
