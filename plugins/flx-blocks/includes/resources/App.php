<?php declare( strict_types=1 );
// cspell:ignore Adbar .

namespace FluxWP\Blocks;

use InvalidArgumentException;
use FluxWP\Blocks\Admin\Admin_Provider;
use FluxWP\Blocks\Asset\Asset_Provider;
use FluxWP\Blocks\Design_Tokens\Design_Tokens_Provider;
use FluxWP\Blocks\Site_Styles\Site_Styles_Provider;
use FluxWP\Blocks\Core\Container;
use FluxWP\Blocks\Core\Database\Table;
use FluxWP\Blocks\Core\Logger;
use FluxWP\Blocks\Design_Tokens\Database\Token_History_Table;
use FluxWP\Blocks\Design_Tokens\Database\Token_Table;
use RuntimeException;

/**
 * The Core Flux Block Editor Application, with container support.
 */
final class App {

	private static self $instance;

	/**
	 * @var Container
	 */
	private Container $container;

	/**
	 * Add any custom providers here.
	 *
	 * @note The order is important.
	 *
	 * @var class-string<Providable>
	 */
	private $providers = [
		Asset_Provider::class,
		Admin_Provider::class,
		Site_Styles_Provider::class,
		Design_Tokens_Provider::class,
	];

	/**
	 * @param Container $container
	 */
	private function __construct(
		Container $container
	) {
		$this->container = $container;

		$this->init();
	}

	/**
	 * @param Container|null $container
	 *
	 * @return self
	 * @throws InvalidArgumentException If no container is provided.
	 */
	public static function instance( ?Container $container = null ): App {
		if ( ! isset( self::$instance ) ) {
			if ( ! $container ) {
				throw new InvalidArgumentException( 'You need to provide a concrete Contracts\Container instance!' );
			}

			self::$instance = new self( $container );
		}

		return self::$instance;
	}

	public function container(): Container {
		return $this->container;
	}

	private function init(): void {
		$this->container->bind( Container::class, $this->container );
		$this->container->singleton( Logger::class, new Logger() );

		try {
			Table::register_all( [ Token_Table::class, Token_History_Table::class ] );
		} catch ( \Throwable $e ) {
			$this->container->get( Logger::class )->error( 'Unable to create or update database tables', [ 'message' => $e->getMessage() ] );
		}

		foreach ( $this->providers as $provider ) {
			$this->container->register( $provider );
		}
	}

	private function __clone() {
	}

	public function __wakeup(): void {
		throw new RuntimeException( 'method not implemented' );
	}

	public function __sleep(): array {
		throw new RuntimeException( 'method not implemented' );
	}
}
