<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Core;

/**
 * Minimal logger: writes to the PHP error log only when WP_DEBUG_LOG is on.
 *
 * @method void emergency( string $message, array $context = [] )
 * @method void alert( string $message, array $context = [] )
 * @method void critical( string $message, array $context = [] )
 * @method void error( string $message, array $context = [] )
 * @method void warning( string $message, array $context = [] )
 * @method void notice( string $message, array $context = [] )
 * @method void info( string $message, array $context = [] )
 * @method void debug( string $message, array $context = [] )
 */
class Logger {

	/**
	 * @param string $level   Log level.
	 * @param string $message Message.
	 * @param array  $context Extra data.
	 */
	public function log( string $level, string $message, array $context = [] ): void {
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		unset( $context['exception'] );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		error_log( sprintf( '[flx-blocks] %s: %s %s', strtoupper( $level ), $message, $context ? wp_json_encode( $context ) : '' ) );
	}

	/**
	 * @param string $name Level.
	 * @param array  $args Message and context.
	 */
	public function __call( $name, $args ) {
		$this->log( (string) $name, (string) ( $args[0] ?? '' ), (array) ( $args[1] ?? [] ) );
	}
}
