<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Projection\Palette;

use FluxWP\Blocks\Design_Tokens\Projection\Traits\Sanitizes_Css_Identifier;

/**
 * Shared helper for a dynamic (PHP-rendered) block that adds its selected per-block color-palette override to
 * its own wrapper markup. A static (save.js) block gets this attribute from the editor save filter instead, but
 * a dynamic block builds its wrapper attributes in PHP, so it composes this trait and merges
 * {@see self::palette_attributes()} into that list.
 *
 * The attribute is the `data-fxb-palette="<id>"` the palette projector's `[data-fxb-palette]` switch layer
 * re-points the block's canonical color vars through. The id runs through the shared
 * {@see Sanitizes_Css_Identifier} sanitizer so it always matches the switch selector; this trait is only the
 * "read the attribute, produce the wrapper args" seam so every block does it identically.
 *
 */
trait Renders_Palette_Attribute {

	use Sanitizes_Css_Identifier;

	/**
	 * The `data-fxb-palette` wrapper attribute a block's `fxbPalette` selection outputs, as a single-entry map
	 * (or an empty map when nothing is selected). A non-string or empty selection yields nothing, so a block can
	 * pass the raw attribute straight through.
	 *
	 *
	 * @param mixed $fxb_palette The block's `fxbPalette` attribute (the selected palette id).
	 *
	 * @return array<string, string>
	 */
	protected function palette_attributes( $fxb_palette ): array {
		if ( ! is_string( $fxb_palette ) || $fxb_palette === '' ) {
			return [];
		}

		return [ 'data-fxb-palette' => self::sanitize_identifier( $fxb_palette ) ];
	}
}
