<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Projection;

/**
 * The single CSS scope every projector emits its custom properties under.
 *
 * Bare :root makes the variables live everywhere KB prints them (front end and editor iframe alike).
 * :where(.fxb-tokens) is an additional zero-specificity hook for opt-in / preset scoping. Neither
 * selector weighs more than a bare :root, and nothing emitted under this scope is !important, so a
 * per-instance override always wins by ordinary cascade.
 *
 */
final class Scope {

	/**
	 * The root scope selector shared by the projection CSS builders.
	 *
	 *
	 * @var string
	 */
	private const ROOT = ':root,:root:where(.fxb-tokens)';

	/**
	 * The root scope selector shared by the projection CSS builders.
	 *
	 *
	 * @return string
	 */
	public static function root(): string {
		return self::ROOT;
	}
}
