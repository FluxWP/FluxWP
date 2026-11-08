<?php
/**
 * Flux Forms: server-authoritative payment amount (front-end strings + cache busting).
 *
 * The REST route itself (Emsfb/v1/forms/payment/quote) lives with the other Stripe
 * routes in vendor/stripe/class-Emsfb-stripe-handler.php; the provider-neutral layer
 * (includes/flx/payments/assets/flxf-pay.js, loaded before stripe_pay-efb.js) calls it
 * and shows the server amount. This file only hands it its translated strings and
 * versions the Stripe script by file time so visitors never run a stale copy.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function flxf_payment_quote_script_data() {
	if ( is_admin() || ! wp_script_is( 'stripe_js', 'enqueued' ) ) {
		return;
	}
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	$file = EMSFB_PLUGIN_DIRECTORY . 'public/assets/js/stripe_pay-efb.js';
	$reg  = wp_scripts()->query( 'stripe_js', 'registered' );
	if ( $reg && is_readable( $file ) ) {
		$reg->ver = EMSFB_PLUGIN_VERSION . '.' . filemtime( $file );
	}

	$strings = array(
		'updating' => __( '\\nUpdating amount...\\n', 'flx-forms' ),
		'failed'   => __( '\\nThe amount could not be verified. Please check your selections and try again.\\n', 'flx-forms' ),
	);
	wp_add_inline_script( 'stripe_js', 'window.flxfPayI18n = ' . wp_json_encode( $strings ) . ';', 'before' );
}
/* EFB enqueues stripe_js while the shortcode renders; footer scripts print at wp_footer:20. */
add_action( 'wp_footer', 'flxf_payment_quote_script_data', 1 );
