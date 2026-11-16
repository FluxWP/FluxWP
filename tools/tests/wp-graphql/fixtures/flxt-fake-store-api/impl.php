<?php
/** Implementation of mu-fake-store-api.php (loaded only without WooCommerce). */

// phpcs:disable

namespace Automattic\WooCommerce\StoreApi\Utilities {
	final class JsonWebToken {
		private static function b64( string $s ): string {
			return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' );
		}
		public static function create( array $payload, string $secret ): string {
			$h = self::b64( (string) wp_json_encode( array( 'alg' => 'HS256', 'typ' => 'JWT' ) ) );
			$p = self::b64( (string) wp_json_encode( $payload ) );
			return "$h.$p." . self::b64( hash_hmac( 'sha256', "$h.$p", $secret, true ) );
		}
		public static function validate( string $token, string $secret ): bool {
			$parts = explode( '.', $token );
			if ( 3 !== count( $parts ) || ! hash_equals( self::b64( hash_hmac( 'sha256', $parts[0] . '.' . $parts[1], $secret, true ) ), $parts[2] ) ) {
				return false;
			}
			$p = json_decode( (string) base64_decode( strtr( $parts[1], '-_', '+/' ) ), true );
			return is_array( $p ) && (int) ( $p['exp'] ?? 0 ) > time();
		}
		public static function payload( string $token ): array {
			$parts = explode( '.', $token );
			$p     = json_decode( (string) base64_decode( strtr( $parts[1] ?? '', '-_', '+/' ) ), true );
			return is_array( $p ) ? $p : array();
		}
	}
}

namespace {
	use Automattic\WooCommerce\StoreApi\Utilities\JsonWebToken;

	final class FLXT_Fake_Store_Api {
		private static string $session = '';

		public static function secret(): string {
			return '@' . wp_salt();
		}

		/** Session of the request: Cart-Token, else the fake cookie (needs a valid nonce for writes). */
		private static function session( WP_REST_Request $r ) {
			$token = (string) $r->get_header( 'Cart-Token' );
			if ( '' !== $token ) {
				if ( ! JsonWebToken::validate( $token, self::secret() ) ) {
					return new WP_Error( 'woocommerce_rest_cart_token_invalid', 'Invalid cart token.', array( 'status' => 401 ) );
				}
				return (string) ( JsonWebToken::payload( $token )['user_id'] ?? '' );
			}
			if ( 'GET' !== $r->get_method() && ! wp_verify_nonce( (string) $r->get_header( 'Nonce' ), 'wc_store_api' ) ) {
				return new WP_Error( 'woocommerce_rest_missing_nonce', 'Missing the Nonce header. This endpoint requires a valid nonce.', array( 'status' => 401 ) );
			}
			if ( '' === self::$session ) {
				$c              = isset( $_COOKIE['wp_woocommerce_session_fake'] ) ? sanitize_key( wp_unslash( $_COOKIE['wp_woocommerce_session_fake'] ) ) : '';
				self::$session  = '' !== $c ? $c : 't_' . wp_generate_password( 20, false );
			}
			return self::$session;
		}

		private static function load( string $sid ): array {
			$c = get_option( 'flxt_fake_cart_' . md5( $sid ), array() );
			return is_array( $c ) ? $c + array( 'items' => array(), 'coupons' => array(), 'billing' => array(), 'shipping' => array() ) : array( 'items' => array(), 'coupons' => array(), 'billing' => array(), 'shipping' => array() );
		}

		private static function save( string $sid, array $c ): void {
			update_option( 'flxt_fake_cart_' . md5( $sid ), $c, false );
		}

		private static function minor( $v ): string {
			return (string) (int) round( (float) $v * 100 );
		}

		private static function cur(): array {
			return array( 'currency_code' => 'TRY', 'currency_symbol' => '₺', 'currency_minor_unit' => 2 );
		}

		private static function shape( array $c ): array {
			$items = array();
			$sum   = 0.0;
			$count = 0;
			foreach ( $c['items'] as $key => $it ) {
				$price   = (float) get_post_meta( (int) $it['id'], '_price', true );
				$line    = $price * (int) $it['quantity'];
				$sum    += $line;
				$count  += (int) $it['quantity'];
				$fixed   = ! empty( $it['data']['flx_test_fixed'] );
				$items[] = array(
					'key'               => $key,
					'id'                => (int) $it['id'],
					'type'              => 'simple',
					'quantity'          => (int) $it['quantity'],
					'quantity_limits'   => array( 'minimum' => 1, 'maximum' => $fixed ? 1 : 9999, 'multiple_of' => 1, 'editable' => ! $fixed ),
					'name'              => get_the_title( (int) $it['id'] ),
					'sku'               => (string) get_post_meta( (int) $it['id'], '_sku', true ),
					'permalink'         => (string) get_permalink( (int) $it['id'] ),
					'images'            => array(),
					'variation'         => array(),
					'item_data'         => array_values( (array) apply_filters( 'woocommerce_get_item_data', array(), array( 'key' => $key, 'product_id' => (int) $it['id'] ) + $it['data'] ) ),
					'sold_individually' => false,
					'prices'            => array( 'price' => self::minor( $price ), 'regular_price' => self::minor( $price ) ) + self::cur(),
					'totals'            => array( 'line_subtotal' => self::minor( $line ), 'line_subtotal_tax' => '0', 'line_total' => self::minor( $line ), 'line_total_tax' => '0' ) + self::cur(),
				);
			}
			$discount = 0.0;
			$coupons  = array();
			foreach ( $c['coupons'] as $code => $amount ) {
				$d         = min( (float) $amount, $sum - $discount );
				$discount += $d;
				$coupons[] = array( 'code' => $code, 'discount_type' => 'fixed_cart', 'totals' => array( 'total_discount' => self::minor( $d ), 'total_discount_tax' => '0' ) + self::cur() );
			}
			$addr = static function ( array $a, bool $billing ): array {
				$keys = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone' );
				if ( $billing ) {
					$keys[] = 'email';
				}
				$out = array();
				foreach ( $keys as $k ) {
					$out[ $k ] = (string) ( $a[ $k ] ?? '' );
				}
				return $out;
			};
			return array(
				'items'                   => $items,
				'coupons'                 => $coupons,
				'fees'                    => array(),
				'totals'                  => array(
					'total_items'     => self::minor( $sum ),
					'total_items_tax' => '0',
					'total_fees'      => '0',
					'total_discount'  => self::minor( $discount ),
					'total_shipping'  => null,
					'total_tax'       => '0',
					'total_price'     => self::minor( $sum - $discount ),
					'tax_lines'       => array(),
				) + self::cur(),
				'shipping_address'        => $addr( (array) $c['shipping'], false ),
				'billing_address'         => $addr( (array) $c['billing'], true ),
				'needs_payment'           => $sum - $discount > 0,
				'needs_shipping'          => false,
				'has_calculated_shipping' => false,
				'shipping_rates'          => array(),
				'items_count'             => $count,
				'payment_methods'         => array( 'bacs' ),
				'payment_requirements'    => array( 'products' ),
				'errors'                  => array(),
			);
		}

		private static function respond( string $sid, array $c ): WP_REST_Response {
			$res = new WP_REST_Response( self::shape( $c ), 200 );
			$res->header( 'Cart-Token', JsonWebToken::create( array( 'user_id' => $sid, 'exp' => time() + DAY_IN_SECONDS, 'iss' => 'store-api' ), self::secret() ) );
			$res->header( 'Nonce', wp_create_nonce( 'wc_store_api' ) );
			return $res;
		}

		public static function route( WP_REST_Request $r ) {
			$sid = self::session( $r );
			if ( $sid instanceof WP_Error ) {
				return $sid;
			}
			$c     = self::load( $sid );
			$route = (string) preg_replace( '#^/wc/store/v1#', '', $r->get_route() );
			switch ( $route ) {
				case '/cart':
					break;
				case '/cart/add-item':
					$id = (int) $r['id'];
					if ( 'product' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
						return new WP_Error( 'woocommerce_rest_cart_invalid_product', 'This product cannot be added to the cart.', array( 'status' => 400 ) );
					}
					$qty  = max( 1, (int) $r['quantity'] );
					$data = (array) apply_filters( 'woocommerce_add_cart_item_data', array(), $id, 0, $qty );
					$key  = md5( $id . '|' . wp_json_encode( $data ) );
					if ( isset( $c['items'][ $key ] ) ) {
						$c['items'][ $key ]['quantity'] += $qty;
					} else {
						$c['items'][ $key ] = array( 'id' => $id, 'quantity' => $qty, 'data' => $data );
					}
					do_action( 'woocommerce_add_to_cart', $key, $id, $qty, 0, array(), $data );
					break;
				case '/cart/update-item':
				case '/cart/remove-item':
					$key = (string) $r['key'];
					if ( ! isset( $c['items'][ $key ] ) ) {
						return new WP_Error( 'woocommerce_rest_cart_invalid_key', 'Cart item no longer exists or is invalid.', array( 'status' => 409 ) );
					}
					if ( '/cart/remove-item' === $route ) {
						unset( $c['items'][ $key ] );
					} else {
						$c['items'][ $key ]['quantity'] = max( 1, (int) $r['quantity'] );
					}
					break;
				case '/cart/apply-coupon':
					$code = strtolower( (string) $r['code'] );
					$post = get_page_by_path( $code, OBJECT, 'shop_coupon' );
					if ( ! $post ) {
						return new WP_Error( 'woocommerce_rest_cart_coupon_error', sprintf( 'Coupon "%s" cannot be applied because it does not exist.', $code ), array( 'status' => 400 ) );
					}
					$c['coupons'][ $code ] = (float) get_post_meta( $post->ID, 'coupon_amount', true );
					break;
				case '/cart/remove-coupon':
					$code = strtolower( (string) $r['code'] );
					if ( ! isset( $c['coupons'][ $code ] ) ) {
						return new WP_Error( 'woocommerce_rest_cart_coupon_invalid_code', 'Coupon cannot be removed because it is not already applied to the cart.', array( 'status' => 409 ) );
					}
					unset( $c['coupons'][ $code ] );
					break;
				case '/cart/update-customer':
					$c['billing']  = array_merge( (array) $c['billing'], (array) ( $r['billing_address'] ?? array() ) );
					$c['shipping'] = array_merge( (array) $c['shipping'], (array) ( $r['shipping_address'] ?? array() ) );
					break;
				case '/cart/select-shipping-rate':
					return new WP_Error( 'woocommerce_rest_cart_invalid_rate', 'The provided rate does not exist.', array( 'status' => 400 ) );
				case '/cart/items':
					$c['items'] = array();
					self::save( $sid, $c );
					return new WP_REST_Response( array(), 200 );
			}
			self::save( $sid, $c );
			return self::respond( $sid, $c );
		}
	}

	add_filter( 'flx_gql_store_api_available', '__return_true' );

	add_action(
		'rest_api_init',
		static function () {
			foreach ( array( '/cart' => 'GET', '/cart/add-item' => 'POST', '/cart/update-item' => 'POST', '/cart/remove-item' => 'POST', '/cart/apply-coupon' => 'POST', '/cart/remove-coupon' => 'POST', '/cart/update-customer' => 'POST', '/cart/select-shipping-rate' => 'POST', '/cart/items' => 'DELETE' ) as $route => $method ) {
				register_rest_route( 'wc/store/v1', $route, array( 'methods' => $method, 'callback' => array( 'FLXT_Fake_Store_Api', 'route' ), 'permission_callback' => '__return_true' ) );
			}
		}
	);
	add_action(
		'init',
		static function () {
			register_post_type( 'product', array( 'public' => true, 'label' => 'Products' ) );
			register_post_type( 'shop_coupon', array( 'public' => false, 'label' => 'Coupons' ) );
		}
	);
}
