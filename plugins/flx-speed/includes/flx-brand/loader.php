<?php
/**
 * FluxWP brand signature: loader.
 *
 * Every FluxWP plugin and theme ships an identical copy of this folder. Each copy registers itself here;
 * on `after_setup_theme` (priority 0, after all plugins and the theme are loaded) only the highest version's
 * core.php is loaded, so the signature is printed once whatever the number of FluxWP packages.
 * On a tie the first registered copy (a plugin, not the theme) wins.
 *
 * Source: FluxWP/fluxwp tools/brand/flx-brand (copy with tools/brand/sync.sh, do not edit the copies).
 *
 * @package flx-brand
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $GLOBALS['flx_brand_copies'] ) || ! is_array( $GLOBALS['flx_brand_copies'] ) ) {
	$GLOBALS['flx_brand_copies'] = array();
}
$GLOBALS['flx_brand_copies'][] = array(
	'version' => '1.2.5',
	'file'    => __DIR__ . '/core.php',
);

if ( ! function_exists( 'flx_brand_boot' ) ) {
	/** Loads the newest registered core.php once. */
	function flx_brand_boot() {
		if ( defined( 'FLX_BRAND_VERSION' ) || empty( $GLOBALS['flx_brand_copies'] ) ) {
			return;
		}
		$best = null;
		foreach ( $GLOBALS['flx_brand_copies'] as $copy ) {
			if ( null === $best || version_compare( $copy['version'], $best['version'], '>' ) ) {
				$best = $copy;
			}
		}
		if ( $best && is_readable( $best['file'] ) ) {
			define( 'FLX_BRAND_VERSION', $best['version'] );
			require_once $best['file'];
		}
	}
	if ( did_action( 'after_setup_theme' ) ) {
		flx_brand_boot();
	} else {
		add_action( 'after_setup_theme', 'flx_brand_boot', 0 );
	}
}
