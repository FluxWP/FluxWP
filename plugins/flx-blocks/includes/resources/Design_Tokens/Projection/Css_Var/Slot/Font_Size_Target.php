<?php declare( strict_types=1 );
// cspell:ignore xxl xxxl .

namespace FluxWP\Blocks\Design_Tokens\Projection\Css_Var\Slot;

use FluxWP\Blocks\Design_Tokens\Projection\Css_Var\Slot\Contracts\Abstract_Target;

/**
 * Normalizes a token's "fxb_font_size_slot" projection into one of Flux Block Editor' fixed font-size slugs.
 *
 * Flux Block Editor renders font-size attributes that hold a preset slug (sm/md/lg/…) as
 * `var(--global-fxb-font-size-<slug>, <literal>)`, and emits those slugs' fluid `clamp()` values through
 * the `flxbk_variable_font_sizes` filter. The `primitive.dimension.font-size.<slug>` token claims
 * that slug with `'fxb_font_size_slot' => 'lg'`; the Css_Var builder then redefines `--global-fxb-font-size-lg`
 * as the token variable, so every block already storing that slug follows the token with no block change —
 * the same slug-on-the-primitive shape spacing and gap use (the button's per-instance default font size is a
 * separate token, semantic.font-size.control, not a scale step).
 *
 * The slug is validated against the set Flux Block Editor ships, so a typo never emits a dead override.
 *
 */
final class Font_Size_Target extends Abstract_Target {

	/**
	 * The projection key a token declares to claim a font-size slug.
	 *
	 *
	 * @var string
	 */
	protected const PROJECTION = 'fxb_font_size_slot';

	/**
	 * The custom-property prefix Flux Block Editor emits each font-size slug under.
	 *
	 *
	 * @var string
	 */
	protected const VAR_PREFIX = '--global-fxb-font-size-';

	/**
	 * The font-size slugs Flux Block Editor defines (see includes/init.php / class-flx-blocks-css.php).
	 * A claim on any other slug is ignored so the override can never point at a slug no block reads.
	 *
	 *
	 * @var string[]
	 */
	protected const SLOTS = [ 'sm', 'md', 'lg', 'xl', 'xxl', 'xxxl' ];

	/**
	 * The primitive dimension tokens that back the font-size slugs; the slug is claimed on the primitive
	 * itself, so this prefix plus the slug is the projection-holding token's own id (and resolves to its
	 * fluid clamp()).
	 *
	 *
	 * @var string
	 */
	protected const PRIMITIVE_PREFIX = 'primitive.dimension.font-size.';
}
