<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Projection;

use FluxWP\Blocks\Design_Tokens\Projection\Contracts\Css_Projector;

/**
 * A collection of the CSS projectors that contribute to the combined editor CSS.
 *
 * Each CSS projector's own provider adds it here, so {@see Editor_Css} gathers them without a central list: a
 * new editor-CSS projector joins entirely from its own module, with no change to Editor_Css or the Projection
 * provider. Projectors are returned in the order they were added, which follows the Projection provider's
 * sub-provider order and so mirrors the load-time enqueue.
 *
 */
final class Css_Projectors {

	/**
	 * The projectors, in registration order.
	 *
	 *
	 * @var Css_Projector[]
	 */
	private array $projectors;

	/**
	 *
	 * @param Css_Projector[] $projectors The projectors to seed the collection with.
	 */
	public function __construct( array $projectors = [] ) {
		$this->projectors = $projectors;
	}

	/**
	 * Add a CSS projector to the collection.
	 *
	 *
	 * @param Css_Projector $projector The projector to add.
	 *
	 * @return void
	 */
	public function add( Css_Projector $projector ): void {
		$this->projectors[] = $projector;
	}

	/**
	 * The projectors, in registration order.
	 *
	 *
	 * @return Css_Projector[]
	 */
	public function all(): array {
		return $this->projectors;
	}
}
