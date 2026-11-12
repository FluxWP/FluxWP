<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Resolver;

use FluxWP\Blocks\Design_Tokens\Projection\Preset\Style;
use FluxWP\Blocks\Design_Tokens\Resolver\Exception\Unknown_Preset_Exception;

/**
 * The chain a block's stored preset slug renders through when the library no longer defines it: a theme
 * preset the active theme lacks falls to the theme's base preset, anything else falls to the block's
 * `$default`. The stored value itself is never rewritten, so switching the theme back restores the look.
 *
 */
final class Preset_Fallback {

	/**
	 * The reason a theme preset was not rendered: the active theme does not offer it.
	 *
	 *
	 * @var string
	 */
	private const REASON_THEME = 'theme';

	/**
	 * The reason any other preset was not rendered: the library no longer defines it.
	 *
	 *
	 * @var string
	 */
	private const REASON_MISSING = 'missing';

	/**
	 * The unprefixed slug of the theme preset every other theme preset falls back to.
	 *
	 *
	 * @var string
	 */
	private const THEME_BASE = 'base';

	/**
	 *
	 * @var Preset_Resolver
	 */
	private Preset_Resolver $presets;

	/**
	 *
	 * @param Preset_Resolver $presets The preset resolver.
	 */
	public function __construct( Preset_Resolver $presets ) {
		$this->presets = $presets;
	}

	/**
	 * The slug a block renders with: the stored one when the library defines it, else theme-base for a
	 * theme slug the library also defines, else the block's $default.
	 *
	 *
	 * @param string $block  The block name.
	 * @param string $stored The block's fxbPreset attribute, left untouched.
	 * @param string $slug   The token library slug.
	 *
	 * @throws Unknown_Preset_Exception When the block defines no presets or declares no default.
	 *
	 * @return string
	 */
	public function resolve( string $block, string $stored, string $slug = 'default' ): string {
		if ( $stored !== '' && $this->presets->has_preset( $block, $stored, $slug ) ) {
			return $stored;
		}

		$base = self::get_theme_base();

		if ( Style::is_theme_slug( $stored ) && $this->presets->has_preset( $block, $base, $slug ) ) {
			return $base;
		}

		return $this->presets->default_preset( $block, $slug );
	}

	/**
	 * Why the stored slug was not rendered: 'theme' for a theme preset the active theme lacks, 'missing'
	 * for any other undefined slug, '' when the stored slug rendered.
	 *
	 *
	 * @param string $block  The block name.
	 * @param string $stored The block's fxbPreset attribute.
	 * @param string $slug   The token library slug.
	 *
	 * @return string
	 */
	public function reason( string $block, string $stored, string $slug = 'default' ): string {
		if ( $stored === '' || $this->presets->has_preset( $block, $stored, $slug ) ) {
			return '';
		}

		return Style::is_theme_slug( $stored ) ? self::REASON_THEME : self::REASON_MISSING;
	}

	/**
	 * The slug of the theme preset every other theme preset falls back to, e.g. "theme-base".
	 *
	 *
	 * @return string
	 */
	public static function get_theme_base(): string {
		return Style::get_theme_prefix() . self::THEME_BASE;
	}

	/**
	 * The reason answered for a theme preset the active theme lacks.
	 *
	 *
	 * @return string
	 */
	public static function get_reason_theme(): string {
		return self::REASON_THEME;
	}

	/**
	 * The reason answered for a slug the library no longer defines.
	 *
	 *
	 * @return string
	 */
	public static function get_reason_missing(): string {
		return self::REASON_MISSING;
	}
}
