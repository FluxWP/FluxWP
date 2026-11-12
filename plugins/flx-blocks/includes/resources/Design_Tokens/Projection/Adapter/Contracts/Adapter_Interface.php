<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Projection\Adapter\Contracts;

/**
 * A per-block adapter: a named transform keyed to a single Flux Block Editor block type, applied to that
 * block's attributes while KB assembles them. Adapters are scoped to Flux Block Editor blocks only — they
 * run through KB's own block-attributes seam, so a core or third-party block is never touched.
 *
 * Most blocks need none — when an attribute already holds a reference (a palette slug, a preset slug)
 * it flows through an ownable CSS variable, so changing a token updates it for free. An adapter exists
 * for the minority of blocks whose attributes are shaped so they cannot be expressed as a single such
 * reference: a binding maps one property to one token, but an adapter is free-form and may read several
 * tokens to build one CSS rule. For example flx/image stores borderRadius as a four-element array
 * of raw per-corner numbers with a separate unit attribute; it renders to literal lengths with no var()
 * indirection, and the rule it feeds can draw on more than one radius token, so an adapter does the
 * imperative work of turning the stored value into something the projected variables can carry. The
 * default path is "no adapter — the CSS variable just works."
 *
 * The block key is read through a get_*() accessor over a const rather than a public const, matching
 * the module's idiom (e.g. {@see \FluxWP\Blocks\Design_Tokens\Registry\Binding::get_flxbk_slot_key()}).
 * {@see Abstract_Adapter} supplies get_block() so a concrete adapter only declares its block const and
 * the transform.
 *
 */
interface Adapter_Interface {

	/**
	 * The Flux Block Editor block type this adapter is keyed to, e.g. "flx/advancedheading".
	 *
	 *
	 * @return string
	 */
	public function get_block(): string;

	/**
	 * Transform a block's attributes before its CSS is built, returning the rewritten attributes.
	 *
	 * Runs in the render path, so it must be a pure, side-effect-free function of its input: it receives
	 * the block's attributes and returns them, leaving any value it does not handle untouched.
	 *
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 *
	 * @return array<string, mixed> The transformed attributes.
	 */
	public function apply( array $attributes ): array;
}
