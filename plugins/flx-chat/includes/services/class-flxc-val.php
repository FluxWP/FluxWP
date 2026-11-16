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
}
