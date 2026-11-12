<?php
/**
 * Plugin Name:       Flux Block Editor
 * Description:       Block editor toolkit: row and column layouts, advanced heading, buttons, icons, gallery, tabs, accordion, forms, header builder and more. Design tokens, block defaults and preset support.
 * Version:           0.2.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flx-blocks
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-blocks
 *
 * @package FLX_Blocks
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

if ( defined( 'FLXBK_VERSION' ) ) {
	return;
}

define( 'FLXBK_VERSION', '0.2.0' );
define( 'FLXBK_FILE', __FILE__ );
define( 'FLXBK_PATH', realpath( plugin_dir_path( __FILE__ ) ) . DIRECTORY_SEPARATOR );
define( 'FLXBK_URL', plugin_dir_url( __FILE__ ) );
define( 'FLXBK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * PSR-4 autoloader: FluxWP\Blocks\X\Y => includes/resources/X/Y.php
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'FluxWP\\Blocks\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$file = FLXBK_PATH . 'includes/resources/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

use FluxWP\Blocks\App;
use FluxWP\Blocks\Core\Container;

/**
 * Add a check before redirecting.
 */
function flxbk_activate(): void {
	add_option( 'flxbk_redirect_on_activation', true );
}
register_activation_hook( __FILE__, 'flxbk_activate' );

/**
 * Load Plugin.
 */
function flxbk_init(): void {
	App::instance( new Container() );

	require_once FLXBK_PATH . 'includes/resources/Design_Tokens/functions.php';
	require_once FLXBK_PATH . 'includes/init.php';
	require_once FLXBK_PATH . 'includes/form-ajax.php';
	require_once FLXBK_PATH . 'includes/helper-functions.php';
	require_once FLXBK_PATH . 'includes/flx-integration.php';
	require_once FLXBK_PATH . 'includes/class-flx-blocks-design-library.php';
	require_once FLXBK_PATH . 'includes/class-flx-blocks-editor-assets.php';
	require_once FLXBK_PATH . 'includes/class-flx-blocks-schema-updater.php';
	require_once FLXBK_PATH . 'includes/class-flx-blocks-google-fonts.php';
	require_once FLXBK_PATH . 'includes/settings/class-flx-blocks-helper.php';
	require_once FLXBK_PATH . 'includes/class-flx-blocks-css.php';
	require_once FLXBK_PATH . 'includes/class-flx-blocks-frontend.php';
	require_once FLXBK_PATH . 'includes/class-flx-blocks-table-of-contents.php';
	require_once FLXBK_PATH . 'includes/class-flx-blocks-duplicate-post.php';
	require_once FLXBK_PATH . 'includes/class-flx-blocks-cpt-import-export.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-abstract-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-row-layout-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-column-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-accordion-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-advancedgallery-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-advancedbtn-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-singlebtn-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-advanced-heading-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-countdown-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-countup-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-form-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-googlemaps-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-icon-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-single-icon-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-icon-list-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-single-icon-list-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-infobox-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-image-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-lottie-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-identity-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-posts-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-progress-bar-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-search-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-show-more-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-spacer-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-table-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-table-data-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-table-row-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-table-of-contents-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-tabs-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-testimonials-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-testimonial-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-navigation-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-navigation-link-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-header-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-videopopup-block.php';
	require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-vector-block.php';
	require_once FLXBK_PATH . 'includes/settings/class-flx-blocks-settings.php';
	require_once FLXBK_PATH . 'includes/class-flx-blocks-posts-rest-api.php';
	require_once FLXBK_PATH . 'includes/class-mailerlite-form-rest-api.php';
	require_once FLXBK_PATH . 'includes/class-fluentcrm-form-rest-api.php';
	require_once FLXBK_PATH . 'includes/class-flx-blocks-lottie-sanitizer.php';
	require_once FLXBK_PATH . 'includes/class-lottieanimation-get-rest-api.php';
	require_once FLXBK_PATH . 'includes/class-lottieanimation-post-rest-api.php';
	require_once FLXBK_PATH . 'includes/class-vector-post-rest-api.php';
	require_once FLXBK_PATH . 'includes/advanced-form/advanced-form-init.php';
	require_once FLXBK_PATH . 'includes/navigation/class-flxbk-navigation-cpt.php';
	require_once FLXBK_PATH . 'includes/navigation/class-flxbk-navigation-rest.php';
	require_once FLXBK_PATH . 'includes/header/class-flxbk-header-cpt.php';
	require_once FLXBK_PATH . 'includes/header/class-flxbk-header-rest.php';
	require_once FLXBK_PATH . 'includes/blocks/header/class-flx-blocks-header-container-desktop-block.php';
	require_once FLXBK_PATH . 'includes/blocks/header/class-flx-blocks-header-container-tablet-block.php';
	require_once FLXBK_PATH . 'includes/blocks/header/class-flx-blocks-header-section-block.php';
	require_once FLXBK_PATH . 'includes/blocks/header/class-flx-blocks-header-row-block.php';
	require_once FLXBK_PATH . 'includes/blocks/header/class-flx-blocks-header-column-block.php';
	require_once FLXBK_PATH . 'includes/blocks/header/class-flx-blocks-off-canvas-block.php';
	require_once FLXBK_PATH . 'includes/blocks/header/class-flx-blocks-off-canvas-trigger-block.php';
	require_once FLXBK_PATH . 'includes/class-flx-blocks-svg.php';
	require_once FLXBK_PATH . 'includes/class-local-gfonts.php';
	require_once FLXBK_PATH . 'includes/settings/class-flx-blocks-site-health.php';
}
add_action( 'plugins_loaded', 'flxbk_init', 1 );

/**
 * Load the plugin textdomain.
 */
function flxbk_lang(): void {
	load_plugin_textdomain( 'flx-blocks', false, dirname( FLXBK_PLUGIN_BASENAME ) . '/languages' );
}
add_action( 'init', 'flxbk_lang', 1 );

/**
 * Point each Flux Block Editor script at its own JSON translation file in languages/.
 *
 * @param string|false $file   Translation file WordPress resolved.
 * @param string       $handle Script handle.
 * @param string       $domain Text domain.
 * @return string|false
 */
function flxbk_script_translation_file( $file, $handle, $domain ) {
	if ( 'flx-blocks' !== $domain ) {
		return $file;
	}
	$script = wp_scripts()->query( $handle );
	if ( ! $script || empty( $script->src ) ) {
		return $file;
	}
	$name = basename( (string) wp_parse_url( $script->src, PHP_URL_PATH ), '.js' );
	$own  = FLXBK_PATH . 'languages/flx-blocks-' . determine_locale() . '-' . $name . '.json';
	return is_readable( $own ) ? $own : $file;
}
add_filter( 'load_script_translation_file', 'flxbk_script_translation_file', 10, 3 );
