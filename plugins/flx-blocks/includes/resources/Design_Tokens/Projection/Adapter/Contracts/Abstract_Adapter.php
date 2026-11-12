<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Projection\Adapter\Contracts;

/**
 * Base for per-block adapters: supplies get_block() from a block const so a concrete adapter only
 * declares its block type and the transform.
 *
 * The const is protected, not private: a private const is visible only inside its declaring class, so
 * the inherited get_block() could not read it through late static binding. Protected is the minimum
 * that lets the accessor defined here resolve the child's value via static::BLOCK.
 *
 */
abstract class Abstract_Adapter implements Adapter_Interface {

	/**
	 * The Flux Block Editor block type this adapter is keyed to, e.g. "flx/advancedheading". Concrete
	 * adapters override with their own block.
	 *
	 *
	 * @var string
	 */
	protected const BLOCK = '';

	/**
	 * @inheritDoc
	 *
	 */
	final public function get_block(): string {
		return static::BLOCK;
	}
}
