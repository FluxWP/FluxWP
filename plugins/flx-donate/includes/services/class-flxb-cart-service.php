<?php
/**
 * Add a donation to the cart. Validation is FLXB_WC_Cart::build_line() (the classic form uses it too,
 * so flxb_wc_line fires on both paths). The line is stored with FLXGQL_Cart::add_item().
 *
 * Several names on one line stay one cart line: a proxy (vekâlet) type keeps them as beneficiaries
 * (one certificate per name); any other certificate type keeps the first name on the line and the
 * rest as people, each with their own certificate.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-import-type FlxCartShape from FLXGQL_Cart
 * @phpstan-type FlxbCartAdd array{typeId: int, option: string, units: int, amount: string, names: list<string>, contacts: list<array{phone?: string, email?: string}>, feeCover: bool, proxyConsent: bool}
 * @phpstan-type FlxbCartResult array{cart: FlxCartShape, itemKey: string}
 * @phpstan-type FlxbStoredLine array{type_id: int, option: string, units: int, currency: string, amount: float|null, beneficiaries: list<string>, ben_name: string, people: list<array{name: string}>}
 */
final class FLXB_Cart_Service {

	/** @var array<string, FlxbStoredLine> Lines remembered this request, keyed by cart item key. */
	private static array $lines = array();

	public static function init(): void {
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'remember' ), 1, 6 );
		add_filter( 'flx_gql_cart_item_kind', array( __CLASS__, 'kind' ), 10, 3 );
	}

	/**
	 * @param mixed $key  Cart item key.
	 * @param mixed $id   Product id.
	 * @param mixed $qty  Quantity.
	 * @param mixed $vid  Variation id.
	 * @param mixed $var  Variation.
	 * @param mixed $data Cart item data.
	 */
	public static function remember( $key, $id = 0, $qty = 0, $vid = 0, $var = array(), $data = array() ): void {
		unset( $id, $qty, $vid, $var );
		if ( ! class_exists( 'FLXB_WC_Cart' ) || ! is_array( $data ) || ! isset( $data[ FLXB_WC_Cart::KEY ] ) || ! is_array( $data[ FLXB_WC_Cart::KEY ] ) ) {
			return;
		}
		self::$lines[ self::str( $key ) ] = self::stored( $data[ FLXB_WC_Cart::KEY ] );
	}

	/**
	 * Donation line of a cart item, from the WooCommerce cart or from the line just added.
	 *
	 * @return FlxbStoredLine|null
	 */
	public static function line_of( string $key ): ?array {
		if ( class_exists( 'FLXGQL_Cart' ) && class_exists( 'FLXB_WC_Cart' ) ) {
			$wc = FLXGQL_Cart::wc_item( $key );
			if ( is_array( $wc ) && isset( $wc[ FLXB_WC_Cart::KEY ] ) && is_array( $wc[ FLXB_WC_Cart::KEY ] ) ) {
				return self::stored( $wc[ FLXB_WC_Cart::KEY ] );
			}
		}
		return self::$lines[ $key ] ?? null;
	}

	/**
	 * @param mixed $kind  Current kind.
	 * @param mixed $wc    WooCommerce cart item.
	 * @param mixed $store Store API item.
	 */
	public static function kind( $kind, $wc = null, $store = null ): string {
		if ( class_exists( 'FLXB_WC_Cart' ) && is_array( $wc ) && isset( $wc[ FLXB_WC_Cart::KEY ] ) ) {
			return 'donation';
		}
		$key = is_array( $store ) ? self::str( $store['key'] ?? '' ) : '';
		if ( '' !== $key && isset( self::$lines[ $key ] ) ) {
			return 'donation';
		}
		return is_string( $kind ) && '' !== $kind ? $kind : 'product';
	}

	/**
	 * Names carried by a line (certificate recipients).
	 *
	 * @param FlxbStoredLine $line Line.
	 * @return list<string>
	 */
	public static function names_of( array $line ): array {
		if ( $line['beneficiaries'] ) {
			return $line['beneficiaries'];
		}
		$out = array();
		if ( '' !== $line['ben_name'] ) {
			$out[] = $line['ben_name'];
		}
		foreach ( $line['people'] as $p ) {
			if ( '' !== $p['name'] ) {
				$out[] = $p['name'];
			}
		}
		return $out;
	}

	/**
	 * Classic form input for build_line().
	 *
	 * @param array<string, mixed> $input GraphQL input.
	 * @return array{type_id: int, option: string, units: int, amount: string, ben_phone?: string, ben_email?: string, beneficiaries?: list<string>, proxy_consent?: string, for_other?: bool, ben_name?: string, people?: list<array{name: string, phone: string, email: string}>}
	 */
	public static function classic_input( array $input ): array {
		self::load_form();
		$names = array();
		foreach ( (array) ( $input['names'] ?? array() ) as $n ) {
			$n = trim( sanitize_text_field( is_scalar( $n ) ? (string) $n : '' ) );
			if ( '' !== $n ) {
				$names[] = mb_substr( $n, 0, 80 );
			}
		}
		$contacts = array();
		foreach ( array_values( (array) ( $input['contacts'] ?? array() ) ) as $c ) {
			$c          = is_array( $c ) ? $c : array();
			$contacts[] = array(
				'phone' => FLXB_WC_Form::phone_value( trim( sanitize_text_field( self::str( $c['phone'] ?? '' ) ) ) ),
				'email' => trim( sanitize_text_field( self::str( $c['email'] ?? '' ) ) ),
			);
		}
		$units = absint( is_scalar( $input['units'] ?? null ) ? $input['units'] : 0 );
		if ( $units < 1 ) {
			$units = max( 1, count( $names ) );
		}
		$in = array(
			'type_id' => absint( is_scalar( $input['typeId'] ?? null ) ? $input['typeId'] : 0 ),
			'option'  => sanitize_key( self::str( $input['option'] ?? '' ) ),
			'units'   => $units,
			'amount'  => self::str( $input['amount'] ?? '' ),
		);
		$t = $in['type_id'] ? FLXB_Types::get( (int) $in['type_id'] ) : null;
		if ( is_array( $t ) && FLXB_Types::requires_proxy( $t ) ) {
			$in['beneficiaries'] = $names;
			$in['proxy_consent'] = empty( $input['proxyConsent'] ) ? '' : '1';
			return $in;
		}
		$in['ben_phone'] = $contacts[0]['phone'] ?? '';
		$in['ben_email'] = $contacts[0]['email'] ?? '';
		if ( $names ) {
			$in['for_other'] = true;
			$in['ben_name']  = $names[0];
			$in['people']    = array();
			foreach ( array_slice( $names, 1, null, true ) as $i => $n ) {
				$in['people'][] = array(
					'name'  => $n,
					'phone' => $contacts[ $i ]['phone'] ?? '',
					'email' => $contacts[ $i ]['email'] ?? '',
				);
			}
			$in['units'] = max( $units, count( $names ) );
		}
		return $in;
	}

	/**
	 * Add the donation. Returns the cart payload, or a WP_Error the mutation shows on the form.
	 *
	 * @param array<string,mixed> $input GraphQL input.
	 * @return FlxbCartResult|WP_Error
	 */
	public static function add( array $input ) {
		self::load_cart();
		if ( ! class_exists( 'FLXGQL_Cart' ) || ! class_exists( 'FLXGQL_Store_Api' ) || ! FLXGQL_Store_Api::available() ) {
			return self::invalid( 'flxb_cart', __( 'The shop is not available on this site.', 'flx-donate' ), 'typeId' );
		}
		self::load_form();
		$classic = self::classic_input( $input );
		if ( ! empty( $input['currency'] ) && class_exists( 'FLXB_WC_Currency' ) ) {
			$cur = strtoupper( sanitize_text_field( self::str( $input['currency'] ) ) );
			if ( ! FLXB_WC_Currency::set( $cur ) ) {
				/* translators: %s: currency code */
				return self::invalid( 'flxb_currency', sprintf( __( 'Donations in %s cannot be paid here at the moment.', 'flx-donate' ), $cur ), 'currency' );
			}
		}
		/* The classic form's contact gate: a certificate needs a WhatsApp number or an e-mail address. */
		$need = FLXB_WC_Form::contact_missing( self::gate_line( $classic ) );
		if ( '' !== $need ) {
			return self::invalid( 'flxb_contact', $need, 'contacts' );
		}
		$line = FLXB_WC_Cart::build_line( $classic );
		if ( is_wp_error( $line ) ) {
			$code = (string) $line->get_error_code();
			return self::invalid( $code, $line->get_error_message(), self::field_of( $code ) );
		}
		self::load_products();
		$pid = FLXB_WC_Products::product_id( self::int( $line['type_id'] ?? 0 ) );
		if ( $pid <= 0 ) {
			return self::invalid( 'flxb_product', __( 'The donation product could not be found.', 'flx-donate' ), 'typeId' );
		}
		$added = FLXGQL_Cart::add_item( $pid, 1, array(), array( FLXB_WC_Cart::KEY => $line ) );
		if ( is_wp_error( $added ) ) {
			return self::invalid( (string) $added->get_error_code(), $added->get_error_message(), 'typeId' );
		}
		/* The classic form's side effects: the donor's contact fills the checkout, the fee choice is kept. */
		FLXB_WC_Form::remember_contact( self::gate_line( $classic ) );
		if ( class_exists( 'FLXB_WC_Fee', false ) ) {
			FLXB_WC_Fee::remember( array( 'fee_cover' => empty( $input['feeCover'] ) ? 0 : 1 ) );
		}
		return array(
			'cart'    => $added['cart'],
			'itemKey' => $added['itemKey'],
		);
	}

	/**
	 * The classic input with the keys the form's contact helpers read.
	 *
	 * @param array<string, mixed> $classic Output of classic_input().
	 * @return array<string, mixed>
	 */
	private static function gate_line( array $classic ): array {
		return $classic + array(
			'for_other' => false,
			'ben_phone' => '',
			'ben_email' => '',
		);
	}

	private static function load_form(): void {
		if ( ! class_exists( 'FLXB_WC_Form', false ) ) {
			require_once dirname( __DIR__ ) . '/wc/class-flxb-wc-form.php';
		}
	}

	private static function load_cart(): void {
		if ( class_exists( 'FLXB_WC_Cart', false ) ) {
			return;
		}
		require_once dirname( __DIR__ ) . '/wc/class-flxb-wc-cart.php';
	}

	private static function load_products(): void {
		if ( class_exists( 'FLXB_WC_Products', false ) ) {
			return;
		}
		require_once dirname( __DIR__ ) . '/wc/class-flxb-wc-products.php';
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function str( $v ): string {
		return is_scalar( $v ) ? (string) $v : '';
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function int( $v ): int {
		return is_numeric( $v ) ? (int) $v : 0;
	}

	/**
	 * The classic line, limited to the keys GraphQL and the certificate names read.
	 *
	 * @param array<mixed> $raw Line from build_line() or the cart.
	 * @return FlxbStoredLine
	 */
	private static function stored( array $raw ): array {
		$names = array();
		if ( isset( $raw['beneficiaries'] ) && is_array( $raw['beneficiaries'] ) ) {
			foreach ( $raw['beneficiaries'] as $n ) {
				$n = trim( self::str( $n ) );
				if ( '' !== $n ) {
					$names[] = $n;
				}
			}
		}
		$people = array();
		if ( isset( $raw['people'] ) && is_array( $raw['people'] ) ) {
			foreach ( $raw['people'] as $p ) {
				if ( is_array( $p ) ) {
					$people[] = array( 'name' => self::str( $p['name'] ?? '' ) );
				}
			}
		}
		return array(
			'type_id'        => self::int( $raw['type_id'] ?? 0 ),
			'option'         => self::str( $raw['option'] ?? '' ),
			'units'          => max( 1, self::int( $raw['units'] ?? 1 ) ),
			'currency'       => self::str( $raw['currency'] ?? '' ),
			'amount'         => is_numeric( $raw['amount'] ?? null ) ? (float) $raw['amount'] : null,
			'beneficiaries'  => $names,
			'ben_name'       => self::str( $raw['ben_name'] ?? '' ),
			'people'         => $people,
		);
	}

	private static function field_of( string $code ): string {
		$map = array(
			'flxb_type'     => 'typeId',
			'flxb_closed'   => 'typeId',
			'flxb_option'   => 'option',
			'flxb_units'    => 'units',
			'flxb_amount'   => 'amount',
			'flxb_names'    => 'names',
			'flxb_proxy'    => 'proxyConsent',
			'flxb_currency' => 'currency',
			'flxb_people'   => 'names',
			'flxb_ben'      => 'names',
		);
		return $map[ $code ] ?? 'typeId';
	}

	private static function invalid( string $code, string $message, string $field ): WP_Error {
		return new WP_Error(
			$code,
			$message,
			array(
				'status'       => 400,
				'graphql_code' => 'INVALID_INPUT',
				'field'        => $field,
			)
		);
	}
}
