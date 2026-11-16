<?php
/**
 * Plain-value casts for the service layer. The older Flux AI code returns untyped arrays; the services
 * read them through these helpers so every value they hand out has a known type.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

final class FLXAI_Cast {

	/**
	 * @param mixed $v       Value.
	 * @param string $default Used when the value is not a scalar.
	 */
	public static function str( $v, string $default = '' ): string {
		return is_scalar( $v ) ? (string) $v : $default;
	}

	/**
	 * @param mixed $v Value.
	 */
	public static function int( $v, int $default = 0 ): int {
		return is_numeric( $v ) ? (int) $v : $default;
	}

	/**
	 * @param mixed $v Value.
	 */
	public static function float( $v, float $default = 0.0 ): float {
		return is_numeric( $v ) ? (float) $v : $default;
	}

	/**
	 * @param mixed $v Value.
	 */
	public static function bool( $v, bool $default = false ): bool {
		if ( is_bool( $v ) ) {
			return $v;
		}
		return is_scalar( $v ) ? (bool) $v : $default;
	}

	/**
	 * @param mixed $v Value.
	 * @return array<array-key,mixed>
	 */
	public static function arr( $v ): array {
		return is_array( $v ) ? $v : array();
	}

	/**
	 * Every scalar of a list, as a string; the rest is dropped.
	 *
	 * @param mixed $v Value.
	 * @return list<string>
	 */
	public static function strings( $v ): array {
		$out = array();
		foreach ( self::arr( $v ) as $item ) {
			if ( is_scalar( $item ) ) {
				$out[] = (string) $item;
			}
		}
		return $out;
	}

	/**
	 * Nullable timestamp: 0 and empty become null.
	 *
	 * @param mixed $v Value.
	 */
	public static function ts( $v ): ?int {
		$n = self::int( $v );
		return $n > 0 ? $n : null;
	}
}
