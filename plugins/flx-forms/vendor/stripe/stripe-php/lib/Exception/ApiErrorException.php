<?php
namespace Stripe\Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base API error (shim). Mirrors the getters of the real SDK that callers use.
 */
class ApiErrorException extends \Exception {
	protected $httpStatus = 0;
	protected $stripeCode = '';
	protected $declineCode = '';
	protected $stripeParam = '';
	protected $jsonBody = null;
	protected $errorType = '';

	public static function fromEfbError( \Emsfb\StripeApiErrorEfb $e ) {
		$status = (int) $e->http_status;
		$type   = (string) $e->error_type;
		if ( 'api_connection_error' === $type ) {
			$class = ApiConnectionException::class;
		} elseif ( 'card_error' === $type || 402 === $status ) {
			$class = CardException::class;
		} elseif ( 401 === $status || 'authentication_error' === $type ) {
			$class = AuthenticationException::class;
		} elseif ( 403 === $status ) {
			$class = PermissionException::class;
		} elseif ( 429 === $status ) {
			$class = RateLimitException::class;
		} elseif ( 'invalid_request_error' === $type || 400 === $status || 404 === $status ) {
			$class = InvalidRequestException::class;
		} else {
			$class = self::class;
		}
		$x              = new $class( $e->getMessage(), $status, $e );
		$x->httpStatus  = $status;
		$x->stripeCode  = (string) $e->stripe_code;
		$x->declineCode = (string) $e->decline_code;
		$x->stripeParam = (string) $e->param;
		$x->jsonBody    = $e->error_body;
		$x->errorType   = $type;
		return $x;
	}

	public function getHttpStatus() { return $this->httpStatus; }
	public function getStripeCode() { return $this->stripeCode; }
	public function getDeclineCode() { return $this->declineCode; }
	public function getStripeParam() { return $this->stripeParam; }
	public function getJsonBody() { return $this->jsonBody; }
	public function getError() {
		return ( is_array( $this->jsonBody ) && isset( $this->jsonBody['error'] ) ) ? \Stripe\StripeObject::constructFrom( $this->jsonBody['error'] ) : null;
	}
}
