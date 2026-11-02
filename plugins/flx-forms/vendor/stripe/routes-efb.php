<?php
/**
 * Stripe add-on (AdnSPF) route bootstrap.
 *
 * Required by includes/class-Emsfb.php when the AdnSPF add-on flag is on.
 * Registers, through the core 'efb_register_payment_rest_routes' action
 * (fired from _Public's rest_api_init with the _Public instance):
 *   POST Emsfb/v1/forms/payment/stripe/card/add  (stripe_pay-efb.js)
 *   POST Emsfb/v1/forms/payment/stripe/confirm   (stripe_pay-efb.js)
 *   GET  Emsfb/v1/forms/payment/stripe/pkey      (stripe_pay-efb.js fallback)
 *   POST Emsfb/v1/forms/payment/stripe/webhook   (Stripe -> site)
 *
 * @package Easy_Form_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-Emsfb-stripe-handler.php';

if ( ! defined( 'EMSFB_STRIPE_ROUTES_BOOTED' ) ) {
	define( 'EMSFB_STRIPE_ROUTES_BOOTED', true );
	\Emsfb\StripeHandlerEfb::boot();
}
