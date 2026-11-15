<?php
/**
 * Legacy PHPStan: constants the plugins and themes define at runtime (define( 'FLXX_URL', plugin_dir_url( … ) )).
 *
 * Included twice: from legacy.neon (returns dynamicConstantNames, so the values count as their general type)
 * and from the bootstrap (defines them). Scans main plugin files and theme functions.php; no list to maintain.
 */

if ( ! function_exists( 'flx_phpstan_legacy_constants' ) ) {
	/**
	 * @return array<string, scalar>
	 */
	function flx_phpstan_legacy_constants(): array {
		$root  = dirname( __DIR__, 2 );
		$files = array_merge(
			glob( $root . '/plugins/*/*.php' ) ?: array(),
			glob( $root . '/plugins/*/includes/*.php' ) ?: array(),
			glob( $root . '/themes/*/functions.php' ) ?: array(),
			glob( $root . '/themes/*/inc/*.php' ) ?: array(),
			glob( $root . '/tools/brand/flx-brand/*.php' ) ?: array()
		);
		$out = array();
		foreach ( $files as $file ) {
			$src = (string) file_get_contents( $file );
			if ( ! preg_match_all( '/define\(\s*([\'"])([A-Z][A-Z0-9_]+)\1\s*,\s*([^;]*?)\s*\)\s*;/', $src, $m, PREG_SET_ORDER ) ) {
				continue;
			}
			foreach ( $m as $d ) {
				$v = trim( $d[3] );
				if ( preg_match( '/^-?\d+$/', $v ) ) {
					$v = (int) $v;
				} elseif ( in_array( strtolower( $v ), array( 'true', 'false' ), true ) ) {
					$v = 'true' === strtolower( $v );
				} else {
					$v = '';
				}
				$out[ $d[2] ] ??= $v;
			}
		}
		return $out;
	}
}

if ( defined( 'FLX_PHPSTAN_DEFINE' ) ) {
	foreach ( flx_phpstan_legacy_constants() as $name => $value ) {
		defined( $name ) || define( $name, $value );
	}
	return array();
}

return array(
	'parameters' => array(
		'dynamicConstantNames' => array_keys( flx_phpstan_legacy_constants() ),
	),
);
