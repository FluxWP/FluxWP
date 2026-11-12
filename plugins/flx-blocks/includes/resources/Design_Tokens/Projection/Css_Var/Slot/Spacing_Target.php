<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Projection\Css_Var\Slot;

use FluxWP\Blocks\Design_Tokens\Projection\Css_Var\Slot\Contracts\Abstract_Target;

/**
 * Normalizes a token's "fxb_spacing_slot" projection into one of Flux Block Editor' fixed spacing slugs.
 *
 * Flux Block Editor renders spacing attributes that hold a preset slug (sm/md/lg/…) as
 * `var(--global-fxb-spacing-<slug>, <literal>)`, and emits those slugs' values as plain literals it owns
 * (no filter hook, unlike colors and font sizes). A spacing token claims one of those slugs with
 * `'fxb_spacing_slot' => 'lg'`; the Css_Var builder then redefines `--global-fxb-spacing-lg` as the token
 * variable, so every block already storing that slug follows the token with no block change.
 *
 * The slug is validated against the set Flux Block Editor ships, so a typo never emits a dead override.
 *
 */
final class Spacing_Target extends Abstract_Target {

	/**
	 * The projection key a token declares to claim a spacing slug.
	 *
	 *
	 * @var string
	 */
	protected const PROJECTION = 'fxb_spacing_slot';

	/**
	 * The custom-property prefix Flux Block Editor emits each spacing slug under.
	 *
	 *
	 * @var string
	 */
	protected const VAR_PREFIX = '--global-fxb-spacing-';

	/**
	 * The spacing slugs Flux Block Editor defines (see includes/init.php / class-flx-blocks-css.php).
	 * A claim on any other slug is ignored so the override can never point at a slug no block reads.
	 *
	 *
	 * @var string[]
	 */
	protected const SLOTS = [ 'ss-auto', 'none', 'xxs', 'xs', 'sm', 'md', 'lg', 'xl', 'xxl', '3xl', '4xl', '5xl' ];

	/**
	 * The primitive dimension tokens that back the spacing slugs; the slug is claimed on the primitive
	 * itself, so this prefix plus the slug is the projection-holding token's own id.
	 *
	 *
	 * @var string
	 */
	protected const PRIMITIVE_PREFIX = 'primitive.dimension.spacing.';
}
