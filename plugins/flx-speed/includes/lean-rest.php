<?php
/**
 * Lean REST: a REST request to a FluxWP namespace (Tasks, Chat, Flux App, CRM) does not register
 * WooCommerce's REST routes.
 *
 * WordPress registers every route on every REST request. On a site with WooCommerce that is the
 * wc/v1-v3, wc-admin, wc-analytics and Store API controllers: measured with the Qurban Global plugin
 * set it was ~170 ms of a ~330 ms /flx-tasks/v1/sync request (pure PHP CPU, no queries). A request to
 * /flx-tasks/v1 never dispatches to those routes, so they are skipped there.
 *
 * WooCommerce's own wc_rest_should_load_namespace() cannot do this: it answers "load" before its
 * filter for any route outside the wc namespaces.
 *
 * Only callbacks defined inside the WooCommerce plugin folder are removed, and only for the listed
 * namespaces. Internal REST calls from other requests (GraphQL → Store API) are not affected: there
 * is no rest_route in those requests.
 *
 * Filter flxp_lean_rest_namespaces: add or remove namespaces; return an empty array to turn it off.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

/** Namespaces (without slashes) whose requests skip WooCommerce route registration. */
function flxp_lean_rest_namespaces() {
	$ns = apply_filters( 'flxp_lean_rest_namespaces', array( 'flx-tasks/v1', 'flx-chat/v1', 'flxapp/v1', 'flx-crm/v1' ) );
	return is_array( $ns ) ? array_values( array_filter( array_map( 'strval', $ns ) ) ) : array();
}

/**
 * Whether the current REST request is for one of the lean namespaces.
 *
 * @param string $route Route of the request, e.g. /flx-tasks/v1/sync.
 */
function flxp_lean_rest_match( $route ) {
	$route = trim( (string) $route, '/' ) . '/';
	if ( '/' === $route ) {
		return false;
	}
	foreach ( flxp_lean_rest_namespaces() as $ns ) {
		if ( 0 === strpos( $route, trim( $ns, '/' ) . '/' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Folder of the file that defines a hook callback, or '' when it cannot be told.
 *
 * @param mixed $fn Callback.
 */
function flxp_lean_rest_callback_file( $fn ) {
	try {
		if ( is_array( $fn ) && isset( $fn[0], $fn[1] ) ) {
			return (string) ( new ReflectionMethod( $fn[0], (string) $fn[1] ) )->getFileName();
		}
		if ( $fn instanceof Closure || ( is_string( $fn ) && false === strpos( $fn, '::' ) && function_exists( $fn ) ) ) {
			return (string) ( new ReflectionFunction( $fn ) )->getFileName();
		}
		if ( is_string( $fn ) && false !== strpos( $fn, '::' ) ) {
			list( $class, $method ) = explode( '::', $fn, 2 );
			return (string) ( new ReflectionMethod( $class, $method ) )->getFileName();
		}
	} catch ( Throwable $e ) {
		unset( $e );
	}
	return '';
}

/** Runs first on rest_api_init: drops WooCommerce's route registration for a lean namespace request. */
function flxp_lean_rest_init() {
	global $wp_filter;
	if ( ! defined( 'WC_PLUGIN_FILE' ) || empty( $wp_filter['rest_api_init'] ) || ! ( $wp_filter['rest_api_init'] instanceof WP_Hook ) ) {
		return;
	}
	$route = isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) ? ( $GLOBALS['wp']->query_vars['rest_route'] ?? '' ) : '';
	if ( ! flxp_lean_rest_match( $route ) ) {
		return;
	}
	$dir = wp_normalize_path( dirname( (string) WC_PLUGIN_FILE ) ) . '/';
	$drop = array();
	foreach ( $wp_filter['rest_api_init']->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $cb ) {
			$file = flxp_lean_rest_callback_file( $cb['function'] );
			if ( '' !== $file && 0 === strpos( wp_normalize_path( $file ), $dir ) ) {
				$drop[] = array( $cb['function'], $priority );
			}
		}
	}
	foreach ( $drop as $d ) {
		remove_action( 'rest_api_init', $d[0], $d[1] );
	}
}
add_action( 'rest_api_init', 'flxp_lean_rest_init', PHP_INT_MIN );
