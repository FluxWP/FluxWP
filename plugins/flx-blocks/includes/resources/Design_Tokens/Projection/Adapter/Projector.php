<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Projection\Adapter;

use FluxWP\Blocks\Design_Tokens\Registry\Token_Registry;
use Throwable;

/**
 * Applies a block's registered per-block adapter to its attributes while KB assembles them.
 *
 * The Provider wires apply() to KB's `flxbk_block_default_attributes` filter, which passes the
 * block name, so the adapter is looked up per render. It is a no-op — returning the attributes untouched
 * — when projection is fail-closed, when no adapter is registered for the block, or when the adapter
 * throws, so it is safe to run for every block on every render.
 *
 */
final class Projector {

	/**
	 * @var Token_Registry The registry the block's adapter is read from.
	 *
	 */
	private Token_Registry $registry;

	/**
	 *
	 * @param Token_Registry $registry The token registry.
	 */
	public function __construct( Token_Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Run the block's adapter over its attributes, returning the transformed attributes.
	 *
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 * @param string               $block      The Flux Block Editor block name, e.g. "flx/advancedheading".
	 *
	 * @return array<string, mixed> The transformed attributes; unchanged when the block has no adapter.
	 */
	public function apply( array $attributes, string $block ): array {
		// Respect the fail-closed guard: when projection is disabled (e.g. a declared token is missing
		// from the baseline) leave the attributes as KB stored them rather than half-transforming them.
		if ( ! $this->registry->is_active() ) {
			return $attributes;
		}

		$adapter = $this->registry->adapter_for_block( $block );

		if ( $adapter === null ) {
			return $attributes;
		}

		try {
			return $adapter->apply( $attributes );
		} catch ( Throwable $e ) {
			// This runs in the render path: a faulty adapter must never fatal a page, so fail soft to the
			// untransformed attributes, matching the block-preset projector.
			return $attributes;
		}
	}
}
