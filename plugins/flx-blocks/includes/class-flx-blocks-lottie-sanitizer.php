<?php
/**
 * Sanitizes Lottie animation data before it is stored or served.
 *
 * @package Flux Block Editor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes executable expressions from Lottie animation data.
 *
 */
class Flx_Blocks_Lottie_Sanitizer {

	/**
	 * Recursively removes expressions from decoded Lottie data.
	 *
	 *
	 * @param array<mixed>|object $data Decoded Lottie JSON.
	 *
	 * @return array<mixed>|object The data without expressions.
	 */
	public static function strip_expressions( $data ) {
		if ( is_object( $data ) ) {
			foreach ( get_object_vars( $data ) as $key => $value ) {
				if ( 'x' === $key && ! self::is_numeric_value( $value ) ) {
					unset( $data->$key );
				} elseif ( is_array( $value ) || is_object( $value ) ) {
					$data->$key = self::strip_expressions( $value );
				}
			}

			return $data;
		}

		foreach ( $data as $key => $value ) {
			if ( 'x' === $key && ! self::is_numeric_value( $value ) ) {
				unset( $data[ $key ] );
			} elseif ( is_array( $value ) || is_object( $value ) ) {
				$data[ $key ] = self::strip_expressions( $value );
			}
		}

		return $data;
	}

	/**
	 * Whether a value is a number or an array made only of numbers.
	 *
	 *
	 * @param mixed $value The value of an `x` key.
	 *
	 * @return bool
	 */
	private static function is_numeric_value( $value ): bool {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( ! self::is_numeric_value( $item ) ) {
					return false;
				}
			}

			return true;
		}

		return is_int( $value ) || is_float( $value );
	}
}
