<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Core;

use Closure;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Small dependency container: bindings, singletons, contextual parameters and autowiring.
 */
class Container {

	/** @var array<string,array{0:mixed,1:bool}> */
	private array $bindings = [];

	/** @var array<string,mixed> */
	private array $instances = [];

	/** @var array<string,array<string,mixed>> */
	private array $contextual = [];

	/** @var array<string,mixed> */
	private array $vars = [];

	/** @var array<string,true> */
	private array $resolving = [];

	public function bind( string $id, $implementation = null ): void {
		unset( $this->instances[ $id ] );
		$this->bindings[ $id ] = [ $implementation ?? $id, false ];
	}

	public function singleton( string $id, $implementation = null ): void {
		unset( $this->instances[ $id ] );
		$this->bindings[ $id ] = [ $implementation ?? $id, true ];
	}

	public function has( string $id ): bool {
		return isset( $this->instances[ $id ] ) || isset( $this->bindings[ $id ] ) || class_exists( $id );
	}

	public function setVar( string $key, $value ): void {
		$this->vars[ $key ] = $value;
	}

	public function getVar( string $key, $default = null ) {
		return $this->vars[ $key ] ?? $default;
	}

	/**
	 * Resolves an id to an instance.
	 *
	 * @param string $id Class name or bound id.
	 *
	 * @return mixed
	 */
	public function get( string $id ) {
		if ( array_key_exists( $id, $this->instances ) ) {
			return $this->instances[ $id ];
		}

		if ( isset( $this->bindings[ $id ] ) ) {
			[ $implementation, $shared ] = $this->bindings[ $id ];
			$object                      = $this->build( $implementation, $id );
			if ( $shared ) {
				$this->instances[ $id ] = $object;
			}

			return $object;
		}

		if ( class_exists( $id ) ) {
			return $this->make( $id );
		}

		throw new InvalidArgumentException( sprintf( 'Nothing is bound to "%s".', $id ) );
	}

	/**
	 * Lazily resolves a class and calls a method on it (for WordPress hooks).
	 */
	public function callback( string $id, string $method ): Closure {
		return function ( ...$args ) use ( $id, $method ) {
			return $this->get( $id )->{$method}( ...$args );
		};
	}

	/**
	 * Starts a contextual binding: when( A )->needs( '$param' )->give( value ).
	 */
	public function when( string $class ): Contextual_Binding {
		return new Contextual_Binding( $this, $class );
	}

	/**
	 * @internal Used by Contextual_Binding.
	 */
	public function add_contextual( string $class, string $need, $give ): void {
		$this->contextual[ $class ][ $need ] = $give;
		unset( $this->instances[ $class ] );
	}

	/**
	 * Registers a service provider.
	 */
	public function register( string $provider ): void {
		$instance = new $provider( $this );
		$instance->register();
	}

	/**
	 * @param mixed  $implementation Class name, closure or object.
	 * @param string $id             The id being resolved.
	 *
	 * @return mixed
	 */
	private function build( $implementation, string $id ) {
		if ( $implementation instanceof Closure ) {
			return $implementation( $this );
		}

		if ( is_string( $implementation ) && class_exists( $implementation ) ) {
			return $this->make( $implementation );
		}

		return $implementation;
	}

	/**
	 * Builds a class, autowiring its constructor.
	 *
	 * @return object
	 */
	public function make( string $class ) {
		if ( isset( $this->resolving[ $class ] ) ) {
			throw new InvalidArgumentException( sprintf( 'Circular dependency while resolving "%s".', $class ) );
		}

		$this->resolving[ $class ] = true;

		try {
			$reflection  = new ReflectionClass( $class );
			$constructor = $reflection->getConstructor();
			if ( ! $constructor ) {
				return new $class();
			}

			$args = [];
			foreach ( $constructor->getParameters() as $parameter ) {
				$name    = '$' . $parameter->getName();
				$type    = $parameter->getType();
				$tname   = $type instanceof ReflectionNamedType && ! $type->isBuiltin() ? $type->getName() : null;
				$context = $this->contextual[ $class ] ?? [];

				if ( array_key_exists( $name, $context ) ) {
					$args[] = $this->contextual_value( $context[ $name ] );
				} elseif ( $tname && array_key_exists( $tname, $context ) ) {
					$args[] = $this->contextual_value( $context[ $tname ] );
				} elseif ( $tname && ( isset( $this->bindings[ $tname ] ) || isset( $this->instances[ $tname ] ) || class_exists( $tname ) ) ) {
					$args[] = $this->get( $tname );
				} elseif ( $parameter->isDefaultValueAvailable() ) {
					$args[] = $parameter->getDefaultValue();
				} elseif ( $type && $type->allowsNull() ) {
					$args[] = null;
				} else {
					throw new InvalidArgumentException( sprintf( 'Cannot resolve parameter %s of "%s".', $name, $class ) );
				}
			}

			return $reflection->newInstanceArgs( $args );
		} finally {
			unset( $this->resolving[ $class ] );
		}
	}

	/**
	 * @param mixed $value Contextual value.
	 *
	 * @return mixed
	 */
	private function contextual_value( $value ) {
		if ( $value instanceof Closure ) {
			return $value( $this );
		}

		if ( is_string( $value ) && class_exists( $value ) ) {
			return $this->get( $value );
		}

		return $value;
	}
}
