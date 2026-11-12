<?php declare( strict_types=1 );
// cspell:ignore palette .

namespace FluxWP\Blocks\Design_Tokens\Projection;

use FluxWP\Blocks\Design_Tokens\Registry\Token_Definition;

/**
 * Normalizes a token's "flxbk_slot" projection into a Flux color-palette slot.
 *
 * The Flux ecosystem keys fifteen color slots palette1…palette15. That slug is the identifier in KB's own
 * flxbk_colors option, in the Flux theme's flxbk_global_palette option, and as the suffix
 * of the --global-paletteN CSS variable. Every consumer resolves through this one class so the slot they
 * target can never drift: the Css_Var legacy bridge (which rewrites the --global-paletteN filter on
 * non-Flux sites), the Flxbk_Option projector (which writes KB's own option), and the palette filter
 * that answers the theme's reads.
 *
 * A token opts in with a bare slug string under "flxbk_slot":
 *
 *   'flxbk_slot' => 'palette1'   // semantic.color.button-bg => slot "palette1"
 *
 * Font-size slots (sm/md/lg/…) also travel under "flxbk_slot" but are NOT palette slots; from_token()
 * returns null for them, so callers that only care about colors skip them cleanly.
 *
 */
final class Flxbk_Palette_Slot {

	/**
	 * The projection key a token declares to claim a Flux slot.
	 *
	 *
	 * @var string
	 */
	private const PROJECTION = 'flxbk_slot';

	/**
	 * Matches the fifteen Flux palette slots, palette1…palette15.
	 *
	 *
	 * @var string
	 */
	private const SLOT_PATTERN = '/^palette([1-9]|1[0-5])$/';

	/**
	 * The palette slot slug, e.g. "palette1". This is the entry slug in both palette options and the
	 * suffix of --global-paletteN.
	 *
	 *
	 * @var string
	 */
	public string $slug;

	/**
	 * @param string $slug Palette slot slug (palette1…palette15).
	 */
	private function __construct( string $slug ) {
		$this->slug = $slug;
	}

	/**
	 * The projection key.
	 *
	 *
	 * @return string
	 */
	public static function get_projection_key(): string {
		return self::PROJECTION;
	}

	/**
	 * Resolve a token's flxbk_slot config to a palette slot, or null when the token declares no
	 * flxbk_slot, or declares one that is not a palette slot (e.g. a font-size key).
	 *
	 *
	 * @param Token_Definition $token The token definition.
	 *
	 * @return self|null
	 */
	public static function from_token( Token_Definition $token ): ?self {
		// A missing projection (?? null), a non-string value, and a string that is not palette1…palette15
		// all collapse to "not a palette slot" here, so no separate has_projection() guard is needed.
		$slot = $token->projections[ self::PROJECTION ] ?? null;

		if ( ! is_string( $slot ) || preg_match( self::SLOT_PATTERN, $slot ) !== 1 ) {
			return null;
		}

		return new self( $slot );
	}
}
