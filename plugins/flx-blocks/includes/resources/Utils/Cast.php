<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Utils;

/**
 * Safe type-casting utilities for Design Token values.
 *
 */
final class Cast {

	/**
	 * Safely casts a value to a string; returns "" for non-scalar values.
	 *
	 *
	 * @param mixed $value
	 *
	 * @return string
	 */
	public static function to_string( $value ): string {
		if ( is_string( $value ) ) {
			return $value;
		}

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return strval( $value );
	}
}
