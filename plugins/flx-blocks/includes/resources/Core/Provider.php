<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Core;

/**
 * Base service provider.
 */
abstract class Provider {

	protected Container $container;

	public function __construct( Container $container ) {
		$this->container = $container;
	}

	abstract public function register(): void;
}
