<?php
/**
 * Strict PHPStan (phpstan.neon): every migrated package, found on disk, so a GraphQL package pull request
 * does not edit a shared list (docs/graphql/KURALLAR.md §12).
 *
 * - A plugin with includes/services or includes/graphql (a theme: inc/services, inc/graphql) is analysed
 *   at level max in those folders; the rest of its includes/ (inc/) is scanned for symbols.
 * - Package specifics (main file to scan, ignoreErrors …) go in packages/<slug>.php, which returns
 *   [ 'scanFiles' => [], 'scanDirectories' => [], 'paths' => [], 'excludePaths' => [], 'ignoreErrors' => [], 'stubFiles' => [] ]
 *   with absolute paths (__DIR__ . '/../../../plugins/<slug>/…').
 * - Constants the code reads come from legacy-constants.php (main files and includes/*.php); others go in
 *   packages/<slug>.bootstrap.php.
 */

$flx_root   = dirname( __DIR__, 2 );
$flx_params = array(
	'paths'           => array(),
	'scanDirectories' => array(),
	'scanFiles'       => array(),
	'excludePaths'    => array(),
	'ignoreErrors'    => array(),
	'stubFiles'       => array(),
);
$flx_dirs   = array_merge( glob( $flx_root . '/plugins/*', GLOB_ONLYDIR ) ?: array(), glob( $flx_root . '/themes/*', GLOB_ONLYDIR ) ?: array() );
sort( $flx_dirs );
foreach ( $flx_dirs as $flx_dir ) {
	if ( basename( $flx_dir ) === 'flx-graphql' ) {
		continue; // Listed in phpstan.neon (its own layout).
	}
	$flx_code   = str_contains( $flx_dir, '/themes/' ) ? $flx_dir . '/inc' : $flx_dir . '/includes';
	$flx_strict = array_values( array_filter( array( $flx_code . '/services', $flx_code . '/graphql' ), 'is_dir' ) );
	if ( ! $flx_strict ) {
		continue;
	}
	array_push( $flx_params['paths'], ...$flx_strict );
	$flx_params['scanDirectories'][] = $flx_code;
}
foreach ( glob( __DIR__ . '/packages/*.php' ) ?: array() as $flx_file ) {
	if ( str_ends_with( $flx_file, '.bootstrap.php' ) ) {
		continue;
	}
	$flx_extra = require $flx_file;
	foreach ( is_array( $flx_extra ) ? $flx_extra : array() as $flx_key => $flx_values ) {
		if ( isset( $flx_params[ $flx_key ] ) && is_array( $flx_values ) ) {
			array_push( $flx_params[ $flx_key ], ...array_values( $flx_values ) );
		}
	}
}

return array( 'parameters' => array_filter( $flx_params ) );
