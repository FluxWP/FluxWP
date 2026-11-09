<?php
/** Local capture fixtures must never send mail or contact real AI/payment providers. */
add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
add_filter( 'pre_http_request', static function ( $result, $args, $url ) {
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	return in_array( $host, array( '127.0.0.1', 'localhost' ), true ) ? $result : new WP_Error( 'capture_offline', 'External requests are disabled in the local capture site.' );
}, PHP_INT_MAX, 3 );
