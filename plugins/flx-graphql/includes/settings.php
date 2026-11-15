<?php
/**
 * Flux GraphQL settings: one option (flxgql_settings), entered in the panel (Settings › Flux GraphQL).
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Defaults. Kept small and safe for shared hosting (Hostinger: PHP stops at ~60 s, no Redis).
 *
 * @return array<string,mixed>
 */
function flx_gql_defaults(): array {
	return array(
		// Persisted queries: 'strict' runs only queries shipped by FluxWP packages (queryId);
		// 'admins' also lets administrators send any query (IDE, debugging); 'open' allows any query for anyone.
		'query_mode'     => 'strict',
		'ide'            => false,  // WPGraphQL GraphiQL IDE in wp-admin.
		'introspection'  => false,  // Public schema introspection (administrators can always introspect).
		'debug'          => false,  // Debug messages in GraphQL responses (administrators only).
		'max_depth'      => 12,
		'max_complexity' => 1000,
		'max_page'       => 50,     // Largest "first" / "last" a connection accepts.
		'batch_limit'    => 10,
		'cache_ttl'      => 300,    // Seconds a public persisted GET may be cached (0 = never).
		'cors_origins'   => '',     // One origin per line; empty = same origin only.
		'upload_max_mb'  => 20,
	);
}

/**
 * Current settings merged with defaults.
 *
 * @return array<string,mixed>
 */
function flx_gql_settings(): array {
	global $flx_gql_settings_cache;
	if ( ! is_array( $flx_gql_settings_cache ) ) {
		$stored                 = get_option( 'flxgql_settings', array() );
		$flx_gql_settings_cache = array_merge( flx_gql_defaults(), is_array( $stored ) ? $stored : array() );
	}
	return $flx_gql_settings_cache;
}

foreach ( array( 'add_option_flxgql_settings', 'update_option_flxgql_settings', 'delete_option_flxgql_settings' ) as $flx_gql_hook ) {
	add_action(
		$flx_gql_hook,
		static function () {
			$GLOBALS['flx_gql_settings_cache'] = null;
		}
	);
}
unset( $flx_gql_hook );

/**
 * One setting.
 *
 * @param string $key Setting name.
 * @return mixed
 */
function flx_gql_opt( string $key ) {
	$s = flx_gql_settings();
	return $s[ $key ] ?? null;
}

/**
 * Validates raw panel input into a settings array.
 *
 * @param array<string,mixed> $in Raw input.
 * @return array<string,mixed>
 */
function flx_gql_sanitize_settings( array $in ): array {
	$d   = flx_gql_defaults();
	$out = array();

	$mode              = (string) ( $in['query_mode'] ?? $d['query_mode'] );
	$out['query_mode'] = in_array( $mode, array( 'strict', 'admins', 'open' ), true ) ? $mode : $d['query_mode'];

	foreach ( array( 'ide', 'introspection', 'debug' ) as $k ) {
		$out[ $k ] = ! empty( $in[ $k ] );
	}

	$ranges = array(
		'max_depth'      => array( 3, 50 ),
		'max_complexity' => array( 100, 20000 ),
		'max_page'       => array( 10, 100 ),
		'batch_limit'    => array( 1, 50 ),
		'cache_ttl'      => array( 0, 86400 ),
		'upload_max_mb'  => array( 1, 512 ),
	);
	foreach ( $ranges as $k => list( $min, $max ) ) {
		$v         = isset( $in[ $k ] ) && is_numeric( $in[ $k ] ) ? (int) $in[ $k ] : (int) $d[ $k ];
		$out[ $k ] = max( $min, min( $max, $v ) );
	}

	$origins = array();
	foreach ( preg_split( '/[\s,]+/', (string) ( $in['cors_origins'] ?? '' ) ) ?: array() as $o ) {
		$o = untrailingslashit( trim( $o ) );
		if ( preg_match( '#^https?://[a-z0-9.-]+(:\d+)?$#i', $o ) ) {
			$origins[] = strtolower( $o );
		}
	}
	$out['cors_origins'] = implode( "\n", array_unique( $origins ) );

	return $out;
}

/**
 * Preset / AI writable settings (flx_settings_schema). Same keys as the panel.
 */
add_filter(
	'flx_settings_schema',
	static function ( $s ) {
		$s                    = is_array( $s ) ? $s : array();
		$s['flxgql_settings'] = array(
			'label'  => 'Flux GraphQL',
			'fields' => array(
				'query_mode'     => array( 'type' => 'enum', 'enum' => array( 'strict', 'admins', 'open' ), 'default' => 'strict', 'desc' => 'Which GraphQL queries run: strict (only queries shipped by FluxWP packages), admins (administrators may also send any query), open (anyone may send any query).' ),
				'ide'            => array( 'type' => 'bool', 'default' => false, 'desc' => 'GraphiQL IDE in wp-admin for administrators.' ),
				'introspection'  => array( 'type' => 'bool', 'default' => false, 'desc' => 'Public schema introspection (administrators can always introspect).' ),
				'max_depth'      => array( 'type' => 'int', 'default' => 12, 'desc' => 'Deepest query nesting allowed (3-50).' ),
				'max_complexity' => array( 'type' => 'int', 'default' => 1000, 'desc' => 'Highest query complexity score allowed (100-20000).' ),
				'max_page'       => array( 'type' => 'int', 'default' => 50, 'desc' => 'Most records one page of a list returns (10-100).' ),
				'cache_ttl'      => array( 'type' => 'int', 'default' => 300, 'desc' => 'Seconds a public read may be cached by LiteSpeed/CDN (0 = never).' ),
				'cors_origins'   => array( 'type' => 'text', 'default' => '', 'desc' => 'Front-end origins allowed to call /graphql from the browser, one per line (https://www.example.com).' ),
			),
		);
		return $s;
	}
);

/**
 * Every write of the option (panel, GraphQL flxSettingsUpdate, presets) goes through the same validation.
 */
add_filter(
	'pre_update_option_flxgql_settings',
	static function ( $value ) {
		$value = is_array( $value ) ? $value : array();
		return flx_gql_sanitize_settings( array_merge( flx_gql_defaults(), $value ) );
	}
);
