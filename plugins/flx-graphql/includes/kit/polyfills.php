<?php
/**
 * PHP 8.2–8.5 functions for PHP 8.1.
 *
 * FluxWP runs on PHP 8.1–8.5. Syntax stays at 8.1; newer *functions* are used freely and fall back to
 * these copies on older runtimes. Each one is guarded by function_exists, so the native function wins
 * on a newer PHP and another plugin's polyfill (symfony/polyfill-php8x) does not clash.
 *
 * Behaviour follows the PHP manual; edge cases are covered by tools/tests/wp-graphql/polyfills.php.
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

/* ---------------- PHP 8.3 ---------------- */

if ( ! function_exists( 'json_validate' ) ) {
	/**
	 * PHP 8.3: whether a string is valid JSON.
	 *
	 * @param string $json  JSON text.
	 * @param int    $depth Maximum nesting depth.
	 * @param int    $flags Only JSON_INVALID_UTF8_IGNORE is accepted, like the native function.
	 */
	function json_validate( string $json, int $depth = 512, int $flags = 0 ): bool {
		if ( '' === $json ) {
			return false;
		}
		json_decode( $json, null, max( 1, $depth ), $flags & JSON_INVALID_UTF8_IGNORE );
		return JSON_ERROR_NONE === json_last_error();
	}
}

if ( ! function_exists( 'mb_str_pad' ) ) {
	/**
	 * PHP 8.3: multibyte str_pad.
	 *
	 * @param string      $string     Input.
	 * @param int         $length     Target length in characters.
	 * @param string      $pad_string Padding (not empty).
	 * @param int         $pad_type   STR_PAD_RIGHT | STR_PAD_LEFT | STR_PAD_BOTH.
	 * @param string|null $encoding   Character encoding.
	 */
	function mb_str_pad( string $string, int $length, string $pad_string = ' ', int $pad_type = STR_PAD_RIGHT, ?string $encoding = null ): string {
		if ( '' === $pad_string ) {
			throw new ValueError( 'mb_str_pad(): Argument #3 ($pad_string) must be a non-empty string' );
		}
		$encoding = $encoding ?? mb_internal_encoding();
		$have     = mb_strlen( $string, $encoding );
		$missing  = $length - $have;
		if ( $missing <= 0 ) {
			return $string;
		}
		$fill = static function ( int $n ) use ( $pad_string, $encoding ): string {
			if ( $n <= 0 ) {
				return '';
			}
			$unit = mb_strlen( $pad_string, $encoding );
			return mb_substr( str_repeat( $pad_string, intdiv( $n, $unit ) + 1 ), 0, $n, $encoding );
		};
		if ( STR_PAD_LEFT === $pad_type ) {
			return $fill( $missing ) . $string;
		}
		if ( STR_PAD_BOTH === $pad_type ) {
			$left = intdiv( $missing, 2 );
			return $fill( $left ) . $string . $fill( $missing - $left );
		}
		return $string . $fill( $missing );
	}
}

if ( ! function_exists( 'str_increment' ) ) {
	/**
	 * PHP 8.3: alphanumeric increment ("a" → "b", "Az" → "Ba", "zz" → "aaa").
	 *
	 * @param string $string Non-empty ASCII alphanumeric string.
	 */
	function str_increment( string $string ): string {
		if ( '' === $string || ! preg_match( '/^[a-zA-Z0-9]+$/', $string ) ) {
			throw new ValueError( 'str_increment(): Argument #1 ($string) must be a non-empty alphanumeric string' );
		}
		$chars = str_split( $string );
		for ( $i = count( $chars ) - 1; $i >= 0; $i-- ) {
			$c = $chars[ $i ];
			if ( 'z' === $c ) {
				$chars[ $i ] = 'a';
			} elseif ( 'Z' === $c ) {
				$chars[ $i ] = 'A';
			} elseif ( '9' === $c ) {
				$chars[ $i ] = '0';
			} else {
				$chars[ $i ] = chr( ord( $c ) + 1 );
				return implode( '', $chars );
			}
		}
		$first = $string[0];
		$lead  = ctype_digit( $first ) ? '1' : ( ctype_upper( $first ) ? 'A' : 'a' );
		return $lead . implode( '', $chars );
	}
}

if ( ! function_exists( 'str_decrement' ) ) {
	/**
	 * PHP 8.3: alphanumeric decrement ("b" → "a", "Ba" → "Az", "aaa" → "zz").
	 *
	 * @param string $string Non-empty ASCII alphanumeric string, not "a", "A" or "0".
	 */
	function str_decrement( string $string ): string {
		if ( '' === $string || ! preg_match( '/^[a-zA-Z0-9]+$/', $string ) ) {
			throw new ValueError( 'str_decrement(): Argument #1 ($string) must be a non-empty alphanumeric string' );
		}
		if ( preg_match( '/^[aA0]$/', $string ) ) {
			throw new ValueError( sprintf( 'str_decrement(): Argument #1 ($string) "%s" is out of decrement range', $string ) );
		}
		$chars = str_split( $string );
		for ( $i = count( $chars ) - 1; $i >= 0; $i-- ) {
			$c = $chars[ $i ];
			if ( 'a' === $c ) {
				$chars[ $i ] = 'z';
			} elseif ( 'A' === $c ) {
				$chars[ $i ] = 'Z';
			} elseif ( '0' === $c ) {
				$chars[ $i ] = '9';
			} else {
				$chars[ $i ] = chr( ord( $c ) - 1 );
				break;
			}
		}
		$out = implode( '', $chars );
		// A leading digit/letter that rolled over drops ("aaa" → "zz", "100" → "99").
		if ( strlen( $out ) > 1 && in_array( $string[0], array( 'a', 'A', '1' ), true ) && $out[0] !== $string[0] ) {
			$out = substr( $out, 1 );
		}
		return $out;
	}
}

/* ---------------- PHP 8.4 ---------------- */

if ( ! function_exists( 'array_find' ) ) {
	/**
	 * PHP 8.4: first value for which the callback returns true, or null.
	 *
	 * @param array<array-key,mixed> $array    Input.
	 * @param callable               $callback fn( $value, $key ): bool.
	 * @return mixed
	 */
	function array_find( array $array, callable $callback ) {
		foreach ( $array as $key => $value ) {
			if ( $callback( $value, $key ) ) {
				return $value;
			}
		}
		return null;
	}
}

if ( ! function_exists( 'array_find_key' ) ) {
	/**
	 * PHP 8.4: first key for which the callback returns true, or null.
	 *
	 * @param array<array-key,mixed> $array    Input.
	 * @param callable               $callback fn( $value, $key ): bool.
	 * @return int|string|null
	 */
	function array_find_key( array $array, callable $callback ) {
		foreach ( $array as $key => $value ) {
			if ( $callback( $value, $key ) ) {
				return $key;
			}
		}
		return null;
	}
}

if ( ! function_exists( 'array_any' ) ) {
	/**
	 * PHP 8.4: whether the callback returns true for at least one element.
	 *
	 * @param array<array-key,mixed> $array    Input.
	 * @param callable               $callback fn( $value, $key ): bool.
	 */
	function array_any( array $array, callable $callback ): bool {
		foreach ( $array as $key => $value ) {
			if ( $callback( $value, $key ) ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'array_all' ) ) {
	/**
	 * PHP 8.4: whether the callback returns true for every element (true for an empty array).
	 *
	 * @param array<array-key,mixed> $array    Input.
	 * @param callable               $callback fn( $value, $key ): bool.
	 */
	function array_all( array $array, callable $callback ): bool {
		foreach ( $array as $key => $value ) {
			if ( ! $callback( $value, $key ) ) {
				return false;
			}
		}
		return true;
	}
}

if ( ! function_exists( 'mb_trim' ) ) {
	/**
	 * PHP 8.4: multibyte trim. Default characters are the Unicode white space set of the native function.
	 *
	 * @param string      $string     Input.
	 * @param string|null $characters Characters to strip.
	 * @param string|null $encoding   Ignored (UTF-8).
	 */
	function mb_trim( string $string, ?string $characters = null, ?string $encoding = null ): string {
		return flx_gql_mb_trim( $string, $characters, 'both' );
	}
}

if ( ! function_exists( 'mb_ltrim' ) ) {
	/**
	 * PHP 8.4: multibyte ltrim.
	 *
	 * @param string      $string     Input.
	 * @param string|null $characters Characters to strip.
	 * @param string|null $encoding   Ignored (UTF-8).
	 */
	function mb_ltrim( string $string, ?string $characters = null, ?string $encoding = null ): string {
		return flx_gql_mb_trim( $string, $characters, 'left' );
	}
}

if ( ! function_exists( 'mb_rtrim' ) ) {
	/**
	 * PHP 8.4: multibyte rtrim.
	 *
	 * @param string      $string     Input.
	 * @param string|null $characters Characters to strip.
	 * @param string|null $encoding   Ignored (UTF-8).
	 */
	function mb_rtrim( string $string, ?string $characters = null, ?string $encoding = null ): string {
		return flx_gql_mb_trim( $string, $characters, 'right' );
	}
}

/**
 * Shared body of the mb_*trim polyfills.
 *
 * @param string      $string     Input.
 * @param string|null $characters Characters to strip; null = Unicode white space.
 * @param string      $side       both|left|right.
 */
function flx_gql_mb_trim( string $string, ?string $characters, string $side ): string {
	$set = null === $characters
		? '\s\x{0000}\x{000B}\x{0085}\x{00A0}\x{1680}\x{180E}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}'
		: preg_quote( $characters, '/' );
	if ( '' === $set ) {
		return $string;
	}
	$pattern = array(
		'both'  => '/^[' . $set . ']+|[' . $set . ']+$/u',
		'left'  => '/^[' . $set . ']+/u',
		'right' => '/[' . $set . ']+$/u',
	)[ $side ];
	return (string) preg_replace( $pattern, '', $string );
}

if ( ! function_exists( 'mb_ucfirst' ) ) {
	/**
	 * PHP 8.4: multibyte ucfirst (title case of the first character).
	 *
	 * @param string      $string   Input.
	 * @param string|null $encoding Character encoding.
	 */
	function mb_ucfirst( string $string, ?string $encoding = null ): string {
		$encoding = $encoding ?? mb_internal_encoding();
		return mb_convert_case( mb_substr( $string, 0, 1, $encoding ), MB_CASE_TITLE, $encoding ) . mb_substr( $string, 1, null, $encoding );
	}
}

if ( ! function_exists( 'mb_lcfirst' ) ) {
	/**
	 * PHP 8.4: multibyte lcfirst.
	 *
	 * @param string      $string   Input.
	 * @param string|null $encoding Character encoding.
	 */
	function mb_lcfirst( string $string, ?string $encoding = null ): string {
		$encoding = $encoding ?? mb_internal_encoding();
		return mb_strtolower( mb_substr( $string, 0, 1, $encoding ), $encoding ) . mb_substr( $string, 1, null, $encoding );
	}
}

/* ---------------- PHP 8.5 ---------------- */

if ( ! function_exists( 'array_first' ) ) {
	/**
	 * PHP 8.5: first value of an array, or null when empty.
	 *
	 * @param array<array-key,mixed> $array Input.
	 * @return mixed
	 */
	function array_first( array $array ) {
		foreach ( $array as $value ) {
			return $value;
		}
		return null;
	}
}

if ( ! function_exists( 'array_last' ) ) {
	/**
	 * PHP 8.5: last value of an array, or null when empty.
	 *
	 * @param array<array-key,mixed> $array Input.
	 * @return mixed
	 */
	function array_last( array $array ) {
		if ( ! $array ) {
			return null;
		}
		return $array[ array_key_last( $array ) ];
	}
}
