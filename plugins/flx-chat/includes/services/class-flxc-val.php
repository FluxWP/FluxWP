<?php
/**
 * Scalar coercion for database rows (PHPStan level max). Same idea as FLXCRM_Val.
 *
 * @package FLX_Chat
 */

defined( 'ABSPATH' ) || exit;

final class FLXC_Val {

	/**
	 * @param mixed $v Value.
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
	public static function float( $v ): float {
		return is_numeric( $v ) ? (float) $v : 0.0;
	}

	/**
	 * @param mixed $v Value.
	 */
	public static function bool( $v ): bool {
		return ! empty( $v );
	}

	/**
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	public static function arr( $v ): array {
		return is_array( $v ) ? $v : array();
	}

	/**
	 * Plain PHP casts for REST input, so edge inputs behave exactly as the classic callbacks did
	 * ("12abc" is 12, an array becomes "Array"). GraphQL code uses str()/int() above instead.
	 *
	 * @param mixed $v Value.
	 */
	public static function raw_int( $v ): int {
		return (int) $v; // @phpstan-ignore cast.int
	}

	/**
	 * @param mixed $v Value.
	 */
	public static function raw_str( $v ): string {
		return (string) $v; // @phpstan-ignore cast.string
	}

	/**
	 * @param mixed $v Value.
	 */
	public static function raw_bool( $v ): bool {
		return (bool) $v;
	}

	/**
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	public static function raw_arr( $v ): array {
		return (array) $v;
	}
}
