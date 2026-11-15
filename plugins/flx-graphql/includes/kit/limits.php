<?php
/**
 * Query limits for shared hosting (PHP stops at ~60 s, no persistent object cache).
 *
 *   - Depth: WPGraphQL's depth rule (embedded copy: our setting; separate copy: our rule added too).
 *   - Complexity: graphql-php QueryComplexity with the "max_complexity" setting. A list field counts
 *     its children × page size; packages may set 'complexity' on a field.
 *   - Page size: WPGraphQL connections and flx_gql_paginate() take at most "max_page" records.
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'graphql_validation_rules',
	static function ( $rules ) {
		$rules = is_array( $rules ) ? $rules : array();
		if ( class_exists( 'GraphQL\Validator\Rules\QueryComplexity' ) ) {
			$rules['flx_query_complexity'] = new \GraphQL\Validator\Rules\QueryComplexity( (int) flx_gql_opt( 'max_complexity' ) );
		}
		if ( 'external' === flx_gql_state()['source'] && class_exists( 'GraphQL\Validator\Rules\QueryDepth' ) ) {
			$rules['flx_query_depth'] = new \GraphQL\Validator\Rules\QueryDepth( (int) flx_gql_opt( 'max_depth' ) );
		}
		return $rules;
	}
);

add_filter(
	'graphql_connection_max_query_amount',
	static function ( $max ) {
		return min( (int) $max, (int) flx_gql_opt( 'max_page' ) );
	}
);
