<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Projection\Css_Var\Slot;

use FluxWP\Blocks\Design_Tokens\Projection\Css_Var\Slot\Contracts\Abstract_Target;

/**
 * Normalizes a token's "fxb_gap_slot" projection into one of Flux Block Editor' fixed gap slugs.
 *
 * Flux Block Editor renders gap/gutter attributes that hold a preset slug as
 * `var(--global-fxb-gap-<slug>, <literal>)`. Unlike spacing, KB does not even define those `--global-fxb-gap-*`
 * properties (they resolve to the literal fallback today), so a gap token claims one with
 * `'fxb_gap_slot' => 'md'`; the Css_Var builder then defines `--global-fxb-gap-md` as the token variable,
 * and every block already storing that slug — including the alias slugs that point at it, e.g. "default"
 * → `--global-fxb-gap-md` — follows the token with no block change.
 *
 * The slot is validated against the gap variables KB references, so a typo never emits a dead override.
 * The literal-only gutter presets KB ships (narrow/wide/widest) have no variable to redirect and are not
 * slots.
 *
 */
final class Gap_Target extends Abstract_Target {

	/**
	 * The projection key a token declares to claim a gap slot.
	 *
	 *
	 * @var string
	 */
	protected const PROJECTION = 'fxb_gap_slot';

	/**
	 * The custom-property prefix Flux Block Editor references each gap slug under.
	 *
	 *
	 * @var string
	 */
	protected const VAR_PREFIX = '--global-fxb-gap-';

	/**
	 * The gap variable slugs Flux Block Editor references (see class-flx-blocks-css.php $gap_sizes). A
	 * claim on any other value — including the literal-only gutter presets (narrow/wide/widest) — is
	 * ignored so the override can never point at a slug no block reads.
	 *
	 *
	 * @var string[]
	 */
	protected const SLOTS = [ 'none', 'xs', 'sm', 'md', 'lg' ];

	/**
	 * The primitive dimension tokens that back the gap slugs; the slug is claimed on the primitive itself,
	 * so this prefix plus the slug is the projection-holding token's own id.
	 *
	 *
	 * @var string
	 */
	protected const PRIMITIVE_PREFIX = 'primitive.dimension.gap.';
}
