<?php
/**
 * Flux Ads: typed value helpers the services share. The legacy classes (FLXADS_Report, FLXADS_Manager …)
 * return loose arrays; the services read them through these casts so what they return has a known shape.
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type FlxadsMoney array{amount: float, currency: string, formatted: string}
 */
final class FLXADS_V {

	/**
	 * @param mixed $v Value.
	 */
	public static function s( $v, string $default = '' ): string {
		return is_scalar( $v ) ? (string) $v : $default;
	}

	/**
	 * Empty text → null.
	 *
	 * @param mixed $v Value.
	 */
	public static function sn( $v ): ?string {
		$s = self::s( $v );
		return '' === $s ? null : $s;
	}

	/**
	 * @param mixed $v Value.
	 */
	public static function i( $v, int $default = 0 ): int {
		return is_numeric( $v ) ? (int) $v : $default;
	}

	/**
	 * @param mixed $v Value.
	 */
	public static function f( $v, float $default = 0.0 ): float {
		return is_numeric( $v ) ? (float) $v : $default;
	}

	/**
	 * Number or null (null stays null).
	 *
	 * @param mixed $v Value.
	 */
	public static function fn( $v ): ?float {
		return is_numeric( $v ) ? (float) $v : null;
	}

	/**
	 * @param mixed $v Value.
	 */
	public static function b( $v ): bool {
		return ! empty( $v );
	}

	/**
	 * @param mixed $v Value.
	 * @return array<string,mixed>
	 */
	public static function a( $v ): array {
		$out = array();
		if ( is_array( $v ) ) {
			foreach ( $v as $k => $x ) {
				$out[ (string) $k ] = $x;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $v Value.
	 * @return list<mixed>
	 */
	public static function l( $v ): array {
		return is_array( $v ) ? array_values( $v ) : array();
	}

	/**
	 * @param mixed $v Value.
	 * @return list<string>
	 */
	public static function ls( $v ): array {
		$out = array();
		foreach ( self::l( $v ) as $x ) {
			if ( is_scalar( $x ) ) {
				$out[] = (string) $x;
			}
		}
		return $out;
	}

	/**
	 * Each row as a string-keyed array.
	 *
	 * @param mixed $v Value.
	 * @return list<array<string,mixed>>
	 */
	public static function rows( $v ): array {
		$out = array();
		foreach ( self::l( $v ) as $x ) {
			$out[] = self::a( $x );
		}
		return $out;
	}

	/**
	 * Amount with its formatted text (what flxads_money shows).
	 *
	 * @param mixed $amount   Number.
	 * @param mixed $currency ISO code; empty = shop currency.
	 * @return FlxadsMoney
	 */
	public static function money( $amount, $currency = '' ): array {
		$cur = self::s( $currency );
		$amt = self::f( $amount );
		return array(
			'amount'    => $amt,
			'currency'  => '' !== $cur ? strtoupper( $cur ) : self::s( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'TRY', 'TRY' ),
			'formatted' => self::s( flxads_money( $amt, $cur ) ),
		);
	}

	/**
	 * @param mixed $amount   Number or null.
	 * @param mixed $currency ISO code.
	 * @return FlxadsMoney|null
	 */
	public static function moneyn( $amount, $currency = '' ): ?array {
		return is_numeric( $amount ) ? self::money( $amount, $currency ) : null;
	}

	/**
	 * Error with a code the GraphQL layer shows to the user: the legacy classes' messages are already
	 * translated and meant for the screen (Meta's own wording included), so they travel as INVALID_INPUT.
	 *
	 * @param WP_Error $e Error.
	 */
	public static function fail( WP_Error $e ): WP_Error {
		$data = $e->get_error_data();
		if ( is_array( $data ) && ! empty( $data['graphql_code'] ) ) {
			return $e;
		}
		return new WP_Error( (string) $e->get_error_code(), $e->get_error_message(), array( 'graphql_code' => 'INVALID_INPUT' ) );
	}

	/**
	 * A plain failure with a message.
	 *
	 * @param string $code    Machine code.
	 * @param string $message Message in the user's language.
	 * @param string $field   Input field.
	 * @param string $gql     GraphQL code.
	 */
	public static function error( string $code, string $message, string $field = '', string $gql = 'INVALID_INPUT' ): WP_Error {
		$data = array( 'graphql_code' => $gql );
		if ( '' !== $field ) {
			$data['field'] = $field;
		}
		return new WP_Error( $code, $message, $data );
	}

	/**
	 * Rate of a ratio that may be null (ROAS colour level).
	 *
	 * @param float|null $v Ratio.
	 */
	public static function roas_level( ?float $v ): string {
		return null === $v ? 'none' : ( $v < 1 ? 'bad' : ( $v < 3 ? 'mid' : 'good' ) );
	}

	/**
	 * Tags the current GraphQL read for the cache (no-op without Flux GraphQL).
	 */
	public static function tag( string $tag ): void {
		if ( function_exists( 'flx_gql_cache_tag' ) ) {
			flx_gql_cache_tag( $tag );
		}
	}

	/**
	 * Clears a cache tag after a write (no-op without Flux GraphQL).
	 */
	public static function purge( string $tag ): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( $tag ) );
		}
	}
}
