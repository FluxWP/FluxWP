<?php
/**
 * Typed reads of WordPress' and the updater's untyped values (options, catalog rows, upgrader results).
 * The services use these instead of casting by hand, so strict analysis can follow the data.
 *
 * @package FLX_Setup
 */

defined( 'ABSPATH' ) || exit;

final class FLXK_Val {

	/**
	 * A scalar as string (anything else: the default).
	 *
	 * @param mixed $v       Value.
	 * @param string $default Default.
	 */
	public static function str( $v, string $default = '' ): string {
		return is_scalar( $v ) ? (string) $v : $default;
	}

	/**
	 * A number or numeric string as int (anything else: the default).
	 *
	 * @param mixed $v       Value.
	 * @param int   $default Default.
	 */
	public static function int( $v, int $default = 0 ): int {
		if ( is_int( $v ) ) {
			return $v;
		}
		return is_numeric( $v ) || is_bool( $v ) ? (int) $v : $default;
	}

	/**
	 * Truthy flags the updater stores as 0/1, "1", true.
	 *
	 * @param mixed $v Value.
	 */
	public static function bool( $v ): bool {
		return is_bool( $v ) ? $v : ( is_scalar( $v ) && '' !== (string) $v && '0' !== (string) $v );
	}

	/**
	 * An array with string keys (anything else: empty).
	 *
	 * @param mixed $v Value.
	 * @return array<string,mixed>
	 */
	public static function arr( $v ): array {
		$out = array();
		if ( is_array( $v ) ) {
			foreach ( $v as $k => $x ) {
				$out[ (string) $k ] = $x;
			}
		}
		return $out;
	}

	/**
	 * A list of arrays with string keys (rows of a table).
	 *
	 * @param mixed $v Value.
	 * @return list<array<string,mixed>>
	 */
	public static function rows( $v ): array {
		$out = array();
		if ( is_array( $v ) ) {
			foreach ( $v as $row ) {
				if ( is_array( $row ) ) {
					$out[] = self::arr( $row );
				}
			}
		}
		return $out;
	}

	/**
	 * A list of strings (non-scalars dropped).
	 *
	 * @param mixed $v Value.
	 * @return list<string>
	 */
	public static function strings( $v ): array {
		$out = array();
		if ( is_array( $v ) ) {
			foreach ( $v as $x ) {
				if ( is_scalar( $x ) ) {
					$out[] = (string) $x;
				}
			}
		}
		return $out;
	}
}
