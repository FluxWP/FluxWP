<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Foundation_Presets;

use FluxWP\Blocks\Core\Provider as Provider_Contract;

/**
 * Wires the foundation-preset catalogue reader and its applier into the container.
 *
 * Both depend on already-registered services (the baseline document and the token store), so this
 * provider only binds the two singletons; no hooks of its own.
 *
 */
final class Provider extends Provider_Contract {

	/**
	 * @inheritDoc
	 *
	 */
	public function register(): void {
		$this->container->singleton( Catalog::class, Catalog::class );
		$this->container->singleton( Selector::class, Selector::class );
	}
}
