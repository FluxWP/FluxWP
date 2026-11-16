<?php
/**
 * Strict PHPStan bootstrap (phpstan.neon): the shared constants (bootstrap.php), then
 * packages/<slug>.bootstrap.php, then every other constant a plugin or theme defines in its main file,
 * includes/*.php or inc/*.php, with a stand-in value, so a package pull request does not edit bootstrap.php:
 *
 *   int / bool / quoted string literal → that value
 *   *_URL → 'https://example.com/', *_DIR / *_PATH → this folder, *_FILE → this file, *_VERSION → '0.0.0'
 *   anything else → ''
 *
 * Comments and docblocks are removed first, so a define() shown as an example is not a constant.
 *
 * A constant whose value matters to the analysis goes in packages/<slug>.bootstrap.php.
 */
require __DIR__ . '/bootstrap.php';

foreach ( glob( __DIR__ . '/packages/*.bootstrap.php' ) ?: array() as $flx_file ) {
	require $flx_file;
}

( static function (): void {
	$root  = dirname( __DIR__, 2 );
	$files = array_merge(
		glob( $root . '/plugins/*/*.php' ) ?: array(),
		glob( $root . '/plugins/*/includes/*.php' ) ?: array(),
		glob( $root . '/themes/*/functions.php' ) ?: array(),
		glob( $root . '/themes/*/inc/*.php' ) ?: array()
	);
	foreach ( $files as $file ) {
		$src  = (string) file_get_contents( $file );
		$code = '';
		foreach ( token_get_all( $src ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$code .= is_array( $token ) ? $token[1] : $token;
		}
		if ( ! preg_match_all( '/define\(\s*([\'"])([A-Z][A-Z0-9_]+)\1\s*,\s*([^;]*?)\s*\)\s*;/', $code, $m, PREG_SET_ORDER ) ) {
			continue;
		}
		foreach ( $m as $d ) {
			$name = $d[2];
			if ( defined( $name ) ) {
				continue;
			}
			$v = trim( $d[3] );
			if ( preg_match( '/^-?\d+$/', $v ) ) {
				$value = (int) $v;
			} elseif ( in_array( strtolower( $v ), array( 'true', 'false' ), true ) ) {
				$value = 'true' === strtolower( $v );
			} elseif ( preg_match( '/^([\'"])([^\'"\\\\]*)\1$/', $v, $s ) ) {
				$value = $s[2];
			} elseif ( str_ends_with( $name, '_URL' ) ) {
				$value = 'https://example.com/';
			} elseif ( str_ends_with( $name, '_DIR' ) || str_ends_with( $name, '_PATH' ) ) {
				$value = __DIR__ . '/';
			} elseif ( str_ends_with( $name, '_FILE' ) ) {
				$value = __FILE__;
			} elseif ( str_ends_with( $name, '_VERSION' ) ) {
				$value = '0.0.0';
			} else {
				$value = '';
			}
			define( $name, $value );
		}
	}
} )();
