<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Projection;

use FluxWP\Blocks\Core\Provider as Provider_Contract;

/**
 * Registers the Design Tokens projection providers.
 *
 * Each projector lives in its own sub-namespace (Css_Var, Preset, …) with its own Provider,
 * keeping bindings and hook registrations isolated per target format. This provider boots them.
 *
 */
final class Provider extends Provider_Contract {

	/**
	 * Projector-specific providers to register, in order.
	 *
	 *
	 * @var class-string<Provider_Contract>[]
	 */
	private const PROVIDERS = [
		Css_Var\Provider::class,
		Block_Preset\Provider::class,
		Flxbk_Option\Provider::class,
		Preset\Provider::class,
		Palette\Provider::class,
		Block_Default_Css\Provider::class,
		Adapter\Provider::class,
	];

	/**
	 * @inheritDoc
	 *
	 */
	public function register(): void {
		// The shared collection each CSS projector's provider adds itself to; bound before the sub-providers so
		// they can add to it as they register.
		$this->container->singleton( Css_Projectors::class );

		foreach ( self::PROVIDERS as $provider ) {
			$this->container->register( $provider );
		}

		// Aggregates every CSS projector's editor CSS (from the collection above) for the projected-CSS REST
		// endpoint. Autowired — a new editor-CSS projector joins from its own provider, not here.
		$this->container->singleton( Editor_Css::class );
	}
}
