<?php
/**
 * Flux AI SDK: loader.
 *
 * Every plugin that offers AI features ships an identical copy of this folder (includes/flx-ai-sdk/).
 * Each copy registers itself here; on `plugins_loaded` (priority -100) only the highest version's core.php
 * is loaded, so the API exists once whatever the number of copies. On a tie the first registered copy wins.
 *
 * Plugins do not call the API while their main file loads; they hook `flx_ai_register`:
 *
 *     add_action( 'flx_ai_register', function () {
 *         flx_ai_plugin( 'my-plugin', array( 'label' => 'My Plugin', 'prefix' => 'myp' ) );
 *         flx_ai_tool( 'my-plugin', 'summary', array( ... ) ); // registered as myp_summary
 *     } );
 *
 * Source: FluxWP/fluxwp tools/ai-sdk/flx-ai-sdk (copy with tools/ai-sdk/sync.py, do not edit the copies).
 *
 * @package flx-ai-sdk
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $GLOBALS['flx_ai_sdk_copies'] ) || ! is_array( $GLOBALS['flx_ai_sdk_copies'] ) ) {
	$GLOBALS['flx_ai_sdk_copies'] = array();
}
$GLOBALS['flx_ai_sdk_copies'][] = array(
	'version' => '1.0.0',
	'file'    => __DIR__ . '/core.php',
);

if ( ! function_exists( 'flx_ai_sdk_boot' ) ) {
	/** Loads the newest registered core.php once. */
	function flx_ai_sdk_boot() {
		if ( defined( 'FLX_AI_SDK_VERSION' ) || empty( $GLOBALS['flx_ai_sdk_copies'] ) ) {
			return;
		}
		$best = null;
		foreach ( $GLOBALS['flx_ai_sdk_copies'] as $copy ) {
			if ( null === $best || version_compare( $copy['version'], $best['version'], '>' ) ) {
				$best = $copy;
			}
		}
		if ( $best && is_readable( $best['file'] ) ) {
			define( 'FLX_AI_SDK_VERSION', $best['version'] );
			define( 'FLX_AI_SDK_FILE', $best['file'] );
			require_once $best['file'];
		}
	}
	if ( did_action( 'plugins_loaded' ) ) {
		flx_ai_sdk_boot();
	} else {
		add_action( 'plugins_loaded', 'flx_ai_sdk_boot', -100 );
	}
}
