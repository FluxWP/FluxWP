<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Schema;

use FluxWP\Blocks\Design_Tokens\Schema\Validation\Dtcg_Validator;
use FluxWP\Blocks\Core\Provider as Provider_Contract;

/**
 * Wires the DTCG schema layer: binds the validator as a singleton so the REST write endpoints and the
 * Resolver dry-run resolve the same instance, and binds the runtime accessor for the committed schema
 * file that the REST schema endpoint serves. Both are stateless singletons with no hooks.
 *
 */
final class Provider extends Provider_Contract {

	/**
	 *
	 * @return void
	 */
	public function register(): void {
		$this->container->singleton( Dtcg_Validator::class );

		// Bind the runtime accessor to the committed, read-only dtcg.schema.json. The version keys the
		// decoded-schema cache, so a schema shipped with a new plugin build invalidates it automatically.
		$this->container->singleton(
			Dtcg_Schema::class,
			new Dtcg_Schema( __DIR__ . '/dtcg.schema.json', FLXBK_VERSION )
		);
	}
}
