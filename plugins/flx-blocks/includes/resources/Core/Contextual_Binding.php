<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Core;

/**
 * Fluent helper for Container::when()->needs()->give().
 */
final class Contextual_Binding {

	private Container $container;

	private string $class;

	private string $need = '';

	public function __construct( Container $container, string $class ) {
		$this->container = $container;
		$this->class     = $class;
	}

	public function needs( string $need ): self {
		$this->need = $need;

		return $this;
	}

	/**
	 * @param mixed $value Value, class name or closure.
	 */
	public function give( $value ): void {
		$this->container->add_contextual( $this->class, $this->need, $value );
	}
}
