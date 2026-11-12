<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Projection\Preset;

use FluxWP\Blocks\Design_Tokens\Projection\Traits\Sanitizes_Css_Identifier;

/**
 * Single source for the preset class a selected design-token preset outputs, so the class the editor
 * adds and the class the projected CSS targets can never drift apart.
 *
 * A block's preset is an ADDITIVE class (not a register_block_style() block style), so it composes
 * with any other block style rather than replacing it. The editor adds the class via the shared fxbPreset
 * save/preview filters (see src/early-filters.js), mirroring the same sanitizer used here.
 *
 */
final class Style {

	use Sanitizes_Css_Identifier;

	/**
	 * The class prefix a selected preset outputs, shared by every Flux block.
	 *
	 *
	 * @var string
	 */
	private const CLASS_PREFIX = 'fxb-preset--';

	/**
	 * The prefix every theme-discovered preset slug carries. Reserved: a client may not create one.
	 *
	 *
	 * @var string
	 */
	private const THEME_PREFIX = 'theme-';

	/**
	 * The class a preset slug outputs, e.g. "secondary" => "fxb-preset--secondary". Mirrors the JS
	 * fxbPresetClassName() sanitizer so the class always matches the selector the projected CSS targets.
	 *
	 *
	 * @param string $preset The preset slug.
	 *
	 * @return string
	 */
	public static function preset_class( string $preset ): string {
		return self::CLASS_PREFIX . self::sanitize_identifier( $preset );
	}

	/**
	 * The class prefix every selected preset shares, e.g. "fxb-preset--". Read by the projector to build the
	 * "no preset selected" test its `$default` state rule is scoped by.
	 *
	 *
	 * @return string
	 */
	public static function get_class_prefix(): string {
		return self::CLASS_PREFIX;
	}

	/**
	 * The slug prefix every theme-discovered preset carries, e.g. "theme-".
	 *
	 *
	 * @return string
	 */
	public static function get_theme_prefix(): string {
		return self::THEME_PREFIX;
	}

	/**
	 * Whether a slug names a theme-discovered preset.
	 *
	 *
	 * @param string $slug The preset slug.
	 *
	 * @return bool
	 */
	public static function is_theme_slug( string $slug ): bool {
		return strlen( $slug ) > strlen( self::THEME_PREFIX ) && strpos( $slug, self::THEME_PREFIX ) === 0;
	}
}
