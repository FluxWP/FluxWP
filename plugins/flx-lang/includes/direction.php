<?php
/** Front-end text direction, including sites without an installed WordPress language pack. */
defined( 'ABSPATH' ) || exit;

/** A language's writing direction follows its locale, never the visitor's browser or country. */
function flxl_language_direction( $code = '' ) {
	$code = $code ?: flxl_current_language();
	$locale = strtolower( str_replace( '-', '_', flxl_languages()[ $code ]['locale'] ?? flxl_language_locale( $code ) ) );
	$base = explode( '_', $locale )[0];
	$rtl = in_array( $base, array( 'ar', 'arc', 'azb', 'ckb', 'dv', 'fa', 'he', 'ps', 'sd', 'ug', 'ur', 'yi' ), true );
	return 'rtl' === apply_filters( 'flxl_language_direction', $rtl ? 'rtl' : 'ltr', $code, $locale ) ? 'rtl' : 'ltr';
}

/** Keep core is_rtl(), theme assets and plugins in agreement with the URL language. */
function flxl_apply_text_direction() {
	if ( flxl_is_backend() ) { return; }
	global $wp_locale;
	if ( $wp_locale instanceof WP_Locale ) {
		$wp_locale->text_direction = 'rtl' === flxl_language_direction() ? 'rtl' : 'ltr';
	}
}
add_action( 'init', 'flxl_apply_text_direction', 0 );
add_action( 'change_locale', 'flxl_apply_text_direction', 20 );

add_filter( 'language_attributes', function ( $attributes ) {
	if ( flxl_is_backend() ) { return $attributes; }
	$language = str_replace( '_', '-', flxl_languages()[ flxl_current_language() ]['locale'] ?? flxl_current_language() );
	$attributes = preg_replace( '/(?<![\w:-])((?:xml:)?lang)\s*=\s*([\'"]).*?\2/i', '$1="' . esc_attr( $language ) . '"', $attributes );
	$attributes = preg_replace( '/\s*\bdir\s*=\s*([\'"]).*?\1/i', '', $attributes );
	return trim( $attributes ) . ' dir="' . esc_attr( flxl_language_direction() ) . '"';
}, 20 );

add_filter( 'body_class', function ( $classes ) {
	if ( flxl_is_backend() ) { return $classes; }
	$classes = array_values( array_diff( $classes, array( 'rtl' ) ) );
	if ( 'rtl' === flxl_language_direction() ) { $classes[] = 'rtl'; }
	return $classes;
}, 20 );
