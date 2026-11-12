<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens;

use FluxWP\Blocks\Core\Provider;

/**
 * Registers the Design Tokens system bindings and hooks.
 */
final class Design_Tokens_Provider extends Provider {

	/**
	 * Design Tokens sub-providers to register, in order.
	 *
	 * @var class-string<Provider>[]
	 */
	private const PROVIDERS = [
		Registry\Provider::class,
		Database\Provider::class,
		Schema\Provider::class,
		Theme_Style_Guide\Provider::class,
		Theme_Buttons\Provider::class,
		Resolver\Provider::class,
		Projection\Provider::class,
		Foundation_Presets\Provider::class,
		Rest\Provider::class,
		Admin\Provider::class,
		Editor\Provider::class,
	];

	public function register(): void {
		foreach ( self::PROVIDERS as $provider ) {
			$this->container->register( $provider );
		}
	}
}
