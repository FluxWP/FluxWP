<?php
/**
 * Parses a front-end path into WordPress query variables with WPGraphQL's request parser (the one nodeByUri
 * uses): rewrite rules, ?p= / ?page_id= / ?s=, post type query vars. A private WP object is used, so the
 * GraphQL request's own query variables stay untouched.
 *
 * Loaded only when WPGraphQL (Flux GraphQL) is active.
 *
 * @package FLX_Headless
 */

defined( 'ABSPATH' ) || exit;

final class FLXHL_Uri_Parser extends \WPGraphQL\Data\NodeResolver {

	/** No AppContext needed: only parse_request() is used. */
	public function __construct() { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod
		$this->wp               = new WP();
		$this->route            = \WPGraphQL\Router::$route . '/?$';
		$this->wp->matched_rule = $this->route;
	}

	/**
	 * Query variables of a URI (with the home path); null when it cannot be parsed.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function vars( string $uri ): ?array {
		$p = new self();
		if ( null === $p->parse_request( $uri ) ) {
			return null;
		}
		$out = array();
		foreach ( flxhl_arr( $p->wp->query_vars ) as $k => $v ) {
			$out[ (string) $k ] = $v;
		}
		unset( $out['nodeType'], $out['graphql'] );
		return $out;
	}
}
