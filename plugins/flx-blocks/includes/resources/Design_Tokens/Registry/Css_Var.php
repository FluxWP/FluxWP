<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Registry;

use FluxWP\Blocks\Design_Tokens\Projection\Traits\Sanitizes_Css_Identifier;

/**
 * Derives the canonical CSS custom-property name from a token id.
 *
 * The id is a DTCG dot-path (e.g. "semantic.color.button-bg"). The variable name is never
 * declared — it is produced by a single deterministic rule so ids and var names cannot drift:
 *
 *   prefix "--fxb-token--", then replace each "." with "--".
 *
 *   semantic.color.button-bg  →  --fxb-token--semantic--color--button-bg
 *
 * The --fxb-token-- prefix keeps the variable inside KB's --global-fxb-* family.
 *
 */
final class Css_Var {

	use Sanitizes_Css_Identifier;

	private const PREFIX = '--fxb-token--';

	/**
	 * The shared custom-property prefix every token variable carries ("--fxb-token--").
	 *
	 *
	 * @return string
	 */
	public static function get_prefix(): string {
		return self::PREFIX;
	}

	/**
	 * Derive the CSS custom-property name from a token id, optionally namespaced to a token library.
	 *
	 * With no namespace the canonical name is produced (`semantic.color.text` →
	 * `--fxb-token--semantic--color--text`). With a namespace the library slug is inserted as a leading
	 * segment after the prefix (`semantic.color.text`, `dark` →
	 * `--fxb-token--dark--semantic--color--text`), so every library's tokens occupy their own namespace
	 * while sharing the one derivation rule — names and ids cannot drift, namespaced or not.
	 *
	 *
	 * @param string $id      The DTCG dot-path id.
	 * @param string $library Optional token-library slug to namespace the variable under. Empty yields the
	 *                        canonical (un-namespaced) name.
	 *
	 * @return string The derived CSS custom-property name.
	 */
	public static function from_id( string $id, string $library = '' ): string {
		$name = str_replace( '.', '--', $id );

		$prefix = '';

		if ( $library !== '' ) {
			$prefix = self::sanitize_identifier( $library ) . '--';
		}

		return self::PREFIX . $prefix . $name;
	}
}
