<?php
/**
 * Bootstrap for the minimal Stripe SDK shim bundled with the Stripe add-on.
 *
 * Only the surface the plugin core uses is implemented: StripeClient with the
 * paymentIntents, products, prices, customers and subscriptions services
 * (create / retrieve / update / all / cancel), StripeObject and the
 * Stripe\Exception\* classes. Requests go through \Emsfb\StripeApiEfb, i.e.
 * wp_remote_request() against the Stripe REST API.
 *
 * Loaded by includes/class-Emsfb-public.php only when \Stripe\StripeClient
 * does not exist yet (a real SDK loaded by another plugin wins).
 *
 * @package Easy_Form_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Stripe\StripeClient', false ) ) {
	require_once dirname( __DIR__ ) . '/class-Emsfb-stripe-api.php';
	require_once __DIR__ . '/lib/Exception/ApiErrorException.php';
	require_once __DIR__ . '/lib/Exception/CardException.php';
	require_once __DIR__ . '/lib/Exception/InvalidRequestException.php';
	require_once __DIR__ . '/lib/Exception/AuthenticationException.php';
	require_once __DIR__ . '/lib/Exception/PermissionException.php';
	require_once __DIR__ . '/lib/Exception/RateLimitException.php';
	require_once __DIR__ . '/lib/Exception/ApiConnectionException.php';
	require_once __DIR__ . '/lib/StripeObject.php';
	require_once __DIR__ . '/lib/Service/ResourceService.php';
	require_once __DIR__ . '/lib/StripeClient.php';
}
