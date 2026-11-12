<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Rest;

use FluxWP\Blocks\Core\Provider as Provider_Contract;

/**
 * Registers the versioned Design Tokens REST providers.
 *
 * Each REST API version lives in its own namespace (V1, V2, ...) so controllers
 * stay isolated across versions; this provider boots the version-specific
 * providers, which in turn register their controllers.
 *
 */
final class Provider extends Provider_Contract {

	/**
	 * Version-specific REST providers to register, in order.
	 *
	 *
	 * @var class-string<Provider_Contract>[]
	 */
	private const PROVIDERS = [
		V1\Provider::class,
	];

	/**
	 * @inheritDoc
	 *
	 */
	public function register(): void {
		foreach ( self::PROVIDERS as $provider ) {
			$this->container->register( $provider );
		}
	}
}
