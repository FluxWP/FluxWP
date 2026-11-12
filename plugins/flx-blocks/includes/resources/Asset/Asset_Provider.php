<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Asset;

use FluxWP\Blocks\Core\Provider;

final class Asset_Provider extends Provider {

	/**
	 * @inheritDoc
	 */
	public function register(): void {
		$this->container->when( Asset::class )
						->needs( '$plugin_url' )
						->give( static fn(): string => FLXBK_URL );
	}
}
