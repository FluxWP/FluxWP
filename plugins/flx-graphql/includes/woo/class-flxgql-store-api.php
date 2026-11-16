<?php
/**
 * Transport to the WooCommerce Store API cart routes (`/wc/store/v1/cart/*`) from inside a GraphQL request.
 *
 * The bridge never re-implements cart or checkout logic: every read and write is an internal
 * `rest_do_request()` to the Store API, so prices, coupons, stock, fees and every payment gateway hook
 * (EsnekPOS, iyzico, the bank transfer gateways) behave exactly as they do for the block checkout.
 *
 * Session, in this order:
 *   1. `Cart-Token` request header (headless front end, mobile): the Store API token session. The token is
 *      validated here and the Store API session handler is used for the whole request (WooCommerce starts
 *      its session before the resolver runs, so the switch happens on `woocommerce_session_handler`).
 *   2. `Nonce` request header (same-site browser on the WooCommerce cookie session): passed through, the
 *      Store API checks it (CSRF protection stays WooCommerce's).
 *   3. Neither, and the browser has no WooCommerce session or sign-in cookie: a fresh cart; nothing to
 *      forge, so the bridge supplies the nonce itself. The response carries the new Cart-Token.
 *   Otherwise a write is refused with FORBIDDEN (a cookie session without its nonce = possible CSRF).
 *
 * Card data never passes through here: the checkout (payment) stays on POST /wc/store/v1/checkout.
 * Request and response bodies are not logged.
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Store API transport.
 */
final class FLXGQL_Store_Api {

	/** Store API namespace. */
	public const NS = '/wc/store/v1';

	/** Cart token of this request's answer (new or refreshed), sent back as the Cart-Token header. */
	private static string $token = '';

	/** Store API nonce of this request's answer. */
	private static string $nonce = '';

	/** Whether a cart route ran in this request (the response must not be cached). */
	private static bool $touched = false;

	/**
	 * Whether WooCommerce and its Store API are loaded.
	 */
	public static function available(): bool {
		$on = function_exists( 'WC' ) && class_exists( '\Automattic\WooCommerce\StoreApi\StoreApi' );
		/**
		 * Whether the Store API cart routes can be called (tests use a stand-in without WooCommerce).
		 *
		 * @since 0.3.0
		 * @param bool $on Available.
		 */
		return (bool) apply_filters( 'flx_gql_store_api_available', $on );
	}

	/**
	 * Hooks: token session, response headers.
	 */
	public static function init(): void {
		add_filter( 'woocommerce_session_handler', array( __CLASS__, 'session_handler' ), 20 );
		add_filter( 'graphql_response_headers_to_send', array( __CLASS__, 'headers' ), 25 );
		add_filter( 'graphql_access_control_allow_headers', array( __CLASS__, 'allow_headers' ) );
	}

	/**
	 * Cart-Token of the incoming HTTP request ('' when none).
	 */
	public static function request_token(): string {
		$raw = isset( $_SERVER['HTTP_CART_TOKEN'] ) ? flx_gql_str( wp_unslash( $_SERVER['HTTP_CART_TOKEN'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JWT, validated below.
		$raw = trim( $raw );
		return preg_match( '/^[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+$/', $raw ) ? $raw : '';
	}

	/**
	 * Store API nonce of the incoming HTTP request ('' when none).
	 */
	public static function request_nonce(): string {
		foreach ( array( 'HTTP_NONCE', 'HTTP_X_WC_STORE_API_NONCE' ) as $h ) {
			if ( isset( $_SERVER[ $h ] ) ) {
				return sanitize_text_field( flx_gql_str( wp_unslash( $_SERVER[ $h ] ) ) );
			}
		}
		return '';
	}

	/**
	 * Whether a cart token is signed by this site and not expired.
	 *
	 * @param string $token JWT.
	 */
	public static function token_valid( string $token ): bool {
		$jwt = '\Automattic\WooCommerce\StoreApi\Utilities\JsonWebToken';
		if ( '' === $token || ! class_exists( $jwt ) || ! is_callable( array( $jwt, 'validate' ) ) ) {
			return false;
		}
		// Same secret the Store API signs with (AbstractCartRoute::get_cart_token_secret).
		return (bool) call_user_func( array( $jwt, 'validate' ), $token, '@' . wp_salt() );
	}

	/**
	 * GraphQL request with a valid Cart-Token → the Store API token session for this request.
	 *
	 * @param mixed $class Session handler class.
	 * @return mixed
	 */
	public static function session_handler( $class ) {
		$handler = '\Automattic\WooCommerce\StoreApi\SessionHandler';
		if ( ! flx_gql_is_request() || ! class_exists( $handler ) ) {
			return $class;
		}
		return self::token_valid( self::request_token() ) ? ltrim( $handler, '\\' ) : $class;
	}

	/**
	 * Whether the browser carries a WooCommerce session or a sign-in cookie.
	 */
	private static function has_session_cookie(): bool {
		foreach ( array_keys( $_COOKIE ) as $name ) {
			$name = (string) $name;
			if ( str_starts_with( $name, 'wp_woocommerce_session_' ) || str_starts_with( $name, 'wordpress_logged_in_' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Calls a Store API cart route.
	 *
	 * @param string              $method GET, POST, PUT or DELETE.
	 * @param string              $route  Route under /wc/store/v1, e.g. "/cart/add-item".
	 * @param array<string,mixed> $params Body (or query) parameters.
	 * @return array<string,mixed>|WP_Error Store API cart response.
	 */
	public static function request( string $method, string $route, array $params = array() ) {
		if ( ! self::available() ) {
			return new WP_Error( 'flx_cart_unavailable', __( 'The shop is not available on this site.', 'flx-graphql' ), array( 'graphql_code' => 'NOT_FOUND' ) );
		}
		self::$touched = true;
		flx_gql_no_store();
		$method        = strtoupper( $method );
		$req           = new WP_REST_Request( $method, self::NS . $route );
		$token         = self::request_token();
		if ( '' !== $token && self::token_valid( $token ) ) {
			$req->set_header( 'Cart-Token', $token );
		} elseif ( '' !== $token ) {
			return new WP_Error( 'flx_cart_token', __( 'Your cart has expired. Please reload the page.', 'flx-graphql' ), array( 'graphql_code' => 'UNAUTHENTICATED' ) );
		} elseif ( 'GET' !== $method ) {
			$nonce = self::request_nonce();
			if ( '' === $nonce && ! self::has_session_cookie() ) {
				$nonce = wp_create_nonce( 'wc_store_api' ); // A fresh cart: no session to forge (see the class note).
			}
			if ( '' === $nonce ) {
				return new WP_Error( 'flx_cart_nonce', __( 'The cart could not be changed. Please reload the page.', 'flx-graphql' ), array( 'graphql_code' => 'FORBIDDEN' ) );
			}
			$req->set_header( 'Nonce', $nonce );
		}
		if ( 'GET' === $method ) {
			$req->set_query_params( $params );
		} else {
			$req->set_header( 'Content-Type', 'application/json' );
			$req->set_body_params( $params );
		}

		$res = rest_do_request( $req );
		if ( $res->is_error() ) {
			$err = $res->as_error();
			return $err instanceof WP_Error ? self::error( $err, $res->get_status() ) : new WP_Error( 'flx_cart', flx_gql_error_message( 'INTERNAL' ) );
		}
		$headers = $res->get_headers();
		foreach ( $headers as $name => $value ) {
			$name = strtolower( (string) $name );
			if ( 'cart-token' === $name ) {
				self::$token = flx_gql_str( $value );
			} elseif ( 'nonce' === $name ) {
				self::$nonce = flx_gql_str( $value );
			}
		}
		$data = $res->get_data();
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'flx_cart', flx_gql_error_message( 'INTERNAL' ) );
		}
		return flx_gql_arr( $data );
	}

	/**
	 * Store API error → kit error code + the input field it is about.
	 *
	 * @param WP_Error $e      Store API error.
	 * @param int      $status HTTP status.
	 */
	private static function error( WP_Error $e, int $status ): WP_Error {
		$code = (string) $e->get_error_code();
		$msg  = trim( html_entity_decode( wp_strip_all_tags( (string) $e->get_error_message() ), ENT_QUOTES, 'UTF-8' ) );
		$gql  = match ( true ) {
			str_contains( $code, 'missing_nonce' ), str_contains( $code, 'invalid_nonce' ) => 'FORBIDDEN',
			409 === $status => 'CONFLICT',
			404 === $status => 'NOT_FOUND',
			403 === $status, 401 === $status => 'FORBIDDEN',
			429 === $status => 'RATE_LIMITED',
			$status >= 400 && $status < 500 => 'INVALID_INPUT',
			default => 'INTERNAL',
		};
		$field = match ( true ) {
			str_contains( $code, 'coupon' ) => 'code',
			str_contains( $code, 'quantity' ), str_contains( $code, 'stock' ) => 'quantity',
			str_contains( $code, 'variation' ) || str_contains( $code, 'attribute' ) => 'variation',
			str_contains( $code, 'invalid_key' ) || str_contains( $code, 'cart_item' ) => 'key',
			str_contains( $code, 'product' ) || str_contains( $code, 'purchas' ) => 'productId',
			str_contains( $code, 'shipping' ) || str_contains( $code, 'rate' ) => 'rateId',
			default => null,
		};
		$data = array(
			'graphql_code' => $gql,
			'status'       => $status,
		);
		if ( null !== $field ) {
			$data['field'] = $field;
		}
		if ( 'INTERNAL' === $gql ) {
			$msg = flx_gql_error_message( 'INTERNAL' ); // No inner details for unexpected errors.
		}
		return new WP_Error( $code, '' !== $msg ? $msg : flx_gql_error_message( $gql ), $data );
	}

	/**
	 * Cart token of this request's answer ('' before any cart route ran).
	 */
	public static function token(): string {
		return self::$token;
	}

	/**
	 * Store API nonce of this request's answer ('' before any cart route ran).
	 */
	public static function nonce(): string {
		return self::$nonce;
	}

	/**
	 * Cart answers are personal: never cached; the cart token and nonce go back as headers.
	 *
	 * @param mixed $headers Response headers.
	 * @return array<string,mixed>
	 */
	public static function headers( $headers ): array {
		$headers = flx_gql_arr( $headers );
		if ( ! self::$touched ) {
			return $headers;
		}
		flx_gql_no_store();
		if ( '' !== self::$token ) {
			$headers['Cart-Token'] = self::$token;
		}
		if ( '' !== self::$nonce ) {
			$headers['Nonce'] = self::$nonce;
		}
		$expose                                   = flx_gql_str( $headers['Access-Control-Expose-Headers'] ?? '' );
		$headers['Access-Control-Expose-Headers'] = trim( $expose . ', Cart-Token, Nonce', ', ' );
		return $headers;
	}

	/**
	 * Browsers may send Cart-Token and Nonce to /graphql (CORS preflight).
	 *
	 * @param mixed $allowed Allowed request headers.
	 * @return array<int,string>
	 */
	public static function allow_headers( $allowed ): array {
		return array_values( array_unique( array_merge( array_map( 'flx_gql_str', (array) $allowed ), array( 'Cart-Token', 'Nonce' ) ) ) );
	}

	/**
	 * Test helper: forget this request's state (contract tests run many operations in one PHP process).
	 */
	public static function reset(): void {
		self::$token   = '';
		self::$nonce   = '';
		self::$touched = false;
	}
}
