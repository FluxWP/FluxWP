<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Projection\Css_Var\Slot\Contracts;

use FluxWP\Blocks\Design_Tokens\Registry\Token_Definition;

/**
 * Base for slot targets: supplies the projection-key, token resolution and custom-property logic from
 * three consts, so a concrete slot family only declares its projection key, variable prefix and the
 * slugs Flux Block Editor ships.
 *
 * The consts are protected, not private: a private const is visible only inside its declaring class, so
 * the inherited methods could not read it through late static binding. Protected is the minimum that
 * lets the logic defined here resolve the child's values via static::PROJECTION / VAR_PREFIX / SLOTS.
 *
 */
abstract class Abstract_Target implements Target {

	/**
	 * The projection key a token declares to claim a slot in this family, e.g. "fxb_spacing_slot". Concrete
	 * families override.
	 *
	 *
	 * @var string
	 */
	protected const PROJECTION = '';

	/**
	 * The custom-property prefix Flux Block Editor emits each slug under, e.g. "--global-fxb-spacing-".
	 * Concrete families override.
	 *
	 *
	 * @var string
	 */
	protected const VAR_PREFIX = '';

	/**
	 * The slugs Flux Block Editor ships for this family; a claim on any other slug is ignored so the override
	 * can never point at a slug no block reads. Concrete families override.
	 *
	 *
	 * @var string[]
	 */
	protected const SLOTS = [];

	/**
	 * The dot-path prefix of the primitive dimension token backing a slug in this family, e.g.
	 * "primitive.dimension.spacing.". Concrete families override.
	 *
	 *
	 * @var string
	 */
	protected const PRIMITIVE_PREFIX = '';

	/**
	 * The claimed slug, e.g. "lg".
	 *
	 *
	 * @var string
	 */
	public string $slot;

	/**
	 * Final so `new static()` in from_token() is safe: no subclass can change the constructor signature.
	 * Protected (not public) keeps from_token() the only way to build a target.
	 *
	 * @param string $slot The claimed slug.
	 */
	final protected function __construct( string $slot ) {
		$this->slot = $slot;
	}

	/**
	 * @inheritDoc
	 *
	 */
	final public static function get_projection_key(): string {
		return static::PROJECTION;
	}

	/**
	 * @inheritDoc
	 *
	 * @param Token_Definition $token The token definition.
	 *
	 */
	final public static function from_token( Token_Definition $token ): ?self {
		if ( ! $token->has_projection( static::PROJECTION ) ) {
			return null;
		}

		$slot = $token->projections[ static::PROJECTION ] ?? null;

		if ( ! is_string( $slot ) || ! in_array( $slot, static::SLOTS, true ) ) {
			return null;
		}

		return new static( $slot );
	}

	/**
	 * @inheritDoc
	 *
	 */
	final public function css_property(): string {
		return static::VAR_PREFIX . $this->slot;
	}

	/**
	 * @inheritDoc
	 *
	 */
	final public function slot(): string {
		return $this->slot;
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $slug The claimed slug, e.g. "lg".
	 *
	 */
	final public static function get_primitive_id( string $slug ): string {
		return static::PRIMITIVE_PREFIX . $slug;
	}
}
