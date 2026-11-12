<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Rest\V1;

use FluxWP\Blocks\Design_Tokens\Rest\V1\Contracts\Controller;
use FluxWP\Blocks\Core\Provider as Provider_Contract;

/**
 * Registers the v1 Design Tokens REST controllers onto rest_api_init.
 *
 */
final class Provider extends Provider_Contract {

	/**
	 * Concrete v1 REST controllers registered on rest_api_init.
	 *
	 * Each entry is resolved from the container and has its routes registered.
	 * Endpoint controllers are added here as the read, write, and preset
	 * surfaces land.
	 *
	 *
	 * @var class-string<Controller>[]
	 */
	private const CONTROLLERS = [
		Documents_Controller::class,
		Schema_Controller::class,
		Presets_Controller::class,
		Palettes_Controller::class,
		Active_Token_Library_Controller::class,
		Projected_Css_Controller::class,
		User_Primitives_Controller::class,
		Feed_Controller::class,
	];

	/**
	 * @inheritDoc
	 *
	 */
	public function register(): void {
		$this->container->singleton( Document_Write_Pipeline::class, Document_Write_Pipeline::class );

		foreach ( self::CONTROLLERS as $controller ) {
			add_action( 'rest_api_init', $this->container->callback( $controller, 'register_routes' ) );
		}
	}
}
