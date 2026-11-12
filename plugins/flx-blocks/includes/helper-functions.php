<?php
/**
 * Flux Block Editor Helper Functions
 *
 * @package Flux Block Editor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Whether the Flux theme runs in its Full Site Editing mode.
 *
 * The mode can change within a request when its setting is saved, so the
 * result is never cached.
 *
 *
 * @return bool
 */
function flxbk_is_fse_mode(): bool {
	$is_fse_mode = function_exists( 'Flux\is_fse_mode' ) && Flux\is_fse_mode();

	/**
	 * Filters whether the Flux theme runs in its Full Site Editing mode.
	 *
	 *
	 * @param bool $is_fse_mode Whether the mode is on.
	 */
	return (bool) apply_filters( 'flxbk_is_fse_mode', $is_fse_mode );
}

/**
 * Check if we are in AMP Mode.
 */
function flxbk_is_not_amp() {
	$not_amp = true;
	if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
		$not_amp = false;
	}
	return $not_amp;
}

/**
 * Get the asset file produced by wp scripts.
 *
 * @param string $filepath the file path.
 * @return array
 */
function flxbk_get_asset_file( $filepath ) {
	$asset_path = FLXBK_PATH . $filepath . '.asset.php';
	return file_exists( $asset_path )
		? include $asset_path
		: [
			'dependencies' => [ 'lodash', 'react', 'react-dom', 'wp-block-editor', 'wp-blocks', 'wp-data', 'wp-element', 'wp-i18n', 'wp-polyfill', 'wp-primitives', 'wp-api' ],
			'version'      => FLXBK_VERSION,
		];
}

/**
 * Check if we are in a rest api call.
 */
function flxbk_is_rest() {
	$prefix = rest_get_url_prefix();
	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( isset( $_GET['rest_route'] ) && strpos( $_GET['rest_route'], '/', 0 ) === 0 ) ) {
		return true;
	}
	// (#3).
	global $wp_rewrite;
	if ( $wp_rewrite === null ) {
		$wp_rewrite = new WP_Rewrite();
	}
	// (#4).
	$rest_url    = wp_parse_url( trailingslashit( rest_url() ) );
	$current_url = wp_parse_url( add_query_arg( [] ) );

	if ( isset( $current_url['path'] ) && isset( $rest_url['path'] ) ) {
		return strpos( $current_url['path'], $rest_url['path'], 0 ) === 0;
	}
	return false;
}

/**
 * Hex to RGBA
 *
 * @param string $hex string hex code.
 * @param number $alpha alpha number.
 */
function flxbk_hex2rgba( $hex, $alpha ) {
	if ( empty( $hex ) ) {
		return '';
	}
	if ( 'transparent' === $hex ) {
		return $hex;
	}
	$hex = str_replace( '#', '', $hex );
	if ( strlen( $hex ) == 3 ) {
		$r = hexdec( substr( $hex, 0, 1 ) . substr( $hex, 0, 1 ) );
		$g = hexdec( substr( $hex, 1, 1 ) . substr( $hex, 1, 1 ) );
		$b = hexdec( substr( $hex, 2, 1 ) . substr( $hex, 2, 1 ) );
	} else {
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
	}
	return 'rgba(' . $r . ', ' . $g . ', ' . $b . ', ' . $alpha . ')';
}

/**
 * Check to see if variable contains a number including 0.
 *
 * @access public
 *
 * @param  string $value - the css property.
 * @return boolean
 */
function flxbk_is_number( &$value ) {
	return isset( $value ) && is_numeric( $value );
}

/**
 * Adds Animate on Scroll attributes to a wrapper args array, if animation attributes are present
 *
 * @param array $attributes The attributes.
 * @param array $wrapper_args The args array to apply aos data to.
 */
function flxbk_apply_aos_wrapper_args( $attributes, &$wrapper_args ) {
	if ( isset( $attributes['flxbkAnimation'] ) && $attributes['flxbkAnimation'] ) {
		$wrapper_args['data-aos'] = $attributes['flxbkAnimation'];
		if ( isset( $attributes['flxbkAOSOptions'] ) && $attributes['flxbkAOSOptions'] && isset( $attributes['flxbkAOSOptions'][0] ) ) {
			$flxbk_aos_options = $attributes['flxbkAOSOptions'][0];

			if ( isset( $flxbk_aos_options['offset'] ) && $flxbk_aos_options['offset'] ) {
				$wrapper_args['data-aos-offset'] = $flxbk_aos_options['offset'];
			}
			if ( isset( $flxbk_aos_options['duration'] ) && $flxbk_aos_options['duration'] ) {
				$wrapper_args['data-aos-duration'] = $flxbk_aos_options['duration'];
			}
			if ( isset( $flxbk_aos_options['easing'] ) && $flxbk_aos_options['easing'] ) {
				$wrapper_args['data-aos-easing'] = $flxbk_aos_options['easing'];
			}
			if ( isset( $flxbk_aos_options['delay'] ) && $flxbk_aos_options['delay'] ) {
				$wrapper_args['data-aos-delay'] = $flxbk_aos_options['delay'];
			}
			if ( isset( $flxbk_aos_options['once'] ) && '' !== $flxbk_aos_options['once'] ) {
				$wrapper_args['data-aos-once'] = $flxbk_aos_options['once'];
			}
		}
	}

	return $wrapper_args;
}

/**
 * Clone of WooCommerce wc_clean function.
 *
 * @param $var
 *
 * @return array|mixed
 */
function flxbk_wc_clean( $var ) {
	if ( is_array( $var ) ) {
		return array_map( 'flxbk_wc_clean', $var );
	} else {
		return is_scalar( $var ) ? sanitize_text_field( $var ) : $var;
	}
}
/**
 * Remote AI/licensing were removed; AI runs through Flux AI (see includes/ai).
 *
 * @return bool
 */
function flxbk_is_ai_disabled() {
	return ! apply_filters( 'flxbk_ai_enabled', false );
}

/**
 * Whether Google Fonts are served from this site (default) instead of fonts.googleapis.com.
 *
 * @return bool
 */
function flxbk_fonts_local() {
	$settings = get_option( 'flxbk_font_settings' );
	if ( ! is_array( $settings ) || ! isset( $settings['load_fonts_local'] ) ) {
		return true;
	}
	return 'false' !== $settings['load_fonts_local'] && false !== $settings['load_fonts_local'];
}
