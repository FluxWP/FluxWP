<?php
/** Each capture context has its own locale, so concurrent EN/TR screenshots cannot race. */
function flx_capture_locale() {
	$locale = (string) ( $_COOKIE['capture_locale'] ?? '' );
	return in_array( $locale, array( 'en_US', 'tr_TR' ), true ) ? $locale : '';
}
add_filter( 'determine_locale', static function ( $locale ) { return flx_capture_locale() ?: $locale; }, 1 );
add_filter( 'locale', static function ( $locale ) { return flx_capture_locale() ?: ( get_option( 'demo_front_locale' ) ?: $locale ); } );
