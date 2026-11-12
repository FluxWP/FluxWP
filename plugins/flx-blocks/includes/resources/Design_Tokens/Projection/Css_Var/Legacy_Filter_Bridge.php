<?php declare( strict_types=1 );
// cspell:ignore palette .

namespace FluxWP\Blocks\Design_Tokens\Projection\Css_Var;

use FluxWP\Blocks\Design_Tokens\Projection\Flxbk_Palette_Slot;
use FluxWP\Blocks\Design_Tokens\Registry\Token_Registry;
use FluxWP\Blocks\Design_Tokens\Resolver\Token_Resolver;
use RuntimeException;

/**
 * Feeds resolved token values into KB's legacy color palette so existing blocks inherit tokens without
 * per-block changes.
 *
 * Filter flxbk_pattern_global_colors — tokens declaring a palette flxbk_slot (palette1…palette15) override that
 * --global-paletteN entry. The callback is a transform of the incoming array: a token-claimed slot is
 * rewritten to "var(--fxb-token--…, <resolved literal>)" so legacy blocks react to preset overrides of
 * --fxb-token--* with the resolved literal as a fallback for contexts that lack the token definitions
 * (e.g. prebuilt-library preview iframes). Everything else passes through untouched.
 *
 * The font-size scale is delivered separately, through the --global-fxb-font-size-<slug> slot bridge
 * (Font_Size_Target) in Css_Builder, not this filter.
 *
 * A no-op when the Flux theme owns the palette. Activation is gated upstream by the Projector (it
 * only calls this when Token_Registry::is_active()).
 *
 */
final class Legacy_Filter_Bridge {

	/**
	 * @var Token_Registry
	 */
	private Token_Registry $registry;

	/**
	 * @var Token_Resolver
	 */
	private Token_Resolver $resolver;

	/**
	 * @param Token_Registry $registry
	 * @param Token_Resolver $resolver
	 */
	public function __construct( Token_Registry $registry, Token_Resolver $resolver ) {
		$this->registry = $registry;
		$this->resolver = $resolver;
	}

	/**
	 * Filter callback for flxbk_pattern_global_colors.
	 *
	 * A no-op when the Flux theme is active: KB's existing CSS emits --global-palette* only when
	 * the theme is not active, so mirroring that guard here prevents the editor's JS color swatches
	 * (which use this filter unconditionally) from diverging from the rendered CSS. Under the Flux
	 * theme, token colors reach the palette through the flxbk_palette_option filter that answers the
	 * theme's own palette reads; the theme's stored Style Guide palette is never written.
	 *
	 *
	 * @param array<string,string> $colors The current --global-palette* map.
	 *
	 * @return array<string,string>
	 */
	public function global_colors( array $colors ): array {
		if ( class_exists( 'Flux\Theme' ) ) {
			return $colors;
		}

		try {
			$resolved = $this->resolver->resolve();
		} catch ( RuntimeException $e ) {
			// A corrupt stored document (alias cycle or dangling alias) must not kill the page.
			// The REST write gate rejects such documents, but a direct DB write could bypass it.
			// Fail open: return the incoming array untouched so existing palette values survive.
			return $colors;
		}

		foreach ( $this->registry->by_projection( Flxbk_Palette_Slot::get_projection_key() ) as $id => $token ) {
			$slot = Flxbk_Palette_Slot::from_token( $token );
			if ( $slot === null ) {
				continue; // not a palette slot (e.g. a font-size flxbk_slot) — leave to font_sizes().
			}

			$value = $resolved->value( $id );
			if ( $value === null ) {
				continue;
			}

			// Point the slot at the token var so blocks react to preset overrides, with the resolved
			// literal as a fallback for preview iframes that build their own palette CSS.
			$colors[ '--global-' . $slot->slug ] = sprintf( 'var(%s, %s)', $token->css_var, $value );
		}

		return $colors;
	}
}
