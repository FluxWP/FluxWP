<?php
/**
 * 2.34.0: how an order was paid, in one place: method, provider, card (masked), installments,
 * amount, bank reference, auth code and test/live. Shown at the top of the WooCommerce order and
 * on the donation detail ("Card · EsnekPOS · ₺100 · single payment").
 *
 * Other payment plugins (Flux POS and the like) fill it through the `flxb_payment_details` filter.
 *
 * @package FLX_Donate
 */

defined( 'ABSPATH' ) || exit;

class FLXB_WC_Payinfo {

	/** Meta key of the masked card (first 6 + last 4) the direct card flows keep. */
	const CARD = '_flxb_card';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ), 5 );
	}

	/** First 6 + last 4 of a card number, the rest hidden. Never the full number. */
	public static function mask( $number ) {
		$n = preg_replace( '/\D+/', '', (string) $number );
		if ( strlen( $n ) < 12 ) {
			return '';
		}
		return substr( $n, 0, 6 ) . str_repeat( '*', strlen( $n ) - 10 ) . substr( $n, -4 );
	}

	/** Card scheme from the first digits (only for showing). */
	public static function brand( $number ) {
		$n = preg_replace( '/\D+/', '', (string) $number );
		if ( preg_match( '/^9792/', $n ) ) {
			return 'Troy';
		}
		if ( preg_match( '/^3[47]/', $n ) ) {
			return 'Amex';
		}
		if ( preg_match( '/^(5[1-5]|2(2[2-9]|[3-6]\d|7[01]|720))/', $n ) ) {
			return 'Mastercard';
		}
		if ( preg_match( '/^4/', $n ) ) {
			return 'Visa';
		}
		return '';
	}

	/** Keeps the masked card on the order (called before the number is thrown away). */
	public static function keep_card( WC_Order $order, $number, $owner = '' ) {
		$mask = self::mask( $number );
		if ( '' === $mask ) {
			return;
		}
		$order->update_meta_data(
			self::CARD,
			array(
				'mask'  => $mask,
				'brand' => self::brand( $number ),
				'owner' => sanitize_text_field( (string) $owner ),
			)
		);
	}

	/**
	 * Payment details of an order.
	 *
	 * @return array{method:string,provider:string,card:string,owner:string,installment:int,amount:float,currency:string,bank_ref:string,auth_code:string,txn:string,test:?bool}
	 */
	public static function get( WC_Order $order ) {
		$info = array(
			'method'      => (string) $order->get_payment_method_title(),
			'provider'    => '',
			'card'        => '',
			'owner'       => '',
			'installment' => 0,
			'amount'      => (float) $order->get_total(),
			'currency'    => (string) $order->get_currency(),
			'bank_ref'    => (string) $order->get_transaction_id(),
			'auth_code'   => '',
			'txn'         => '',
			'test'        => null,
		);
		$card = $order->get_meta( self::CARD );
		if ( is_array( $card ) && ! empty( $card['mask'] ) ) {
			$info['card']  = trim( (string) ( $card['brand'] ?? '' ) . ' ' . self::pretty( (string) $card['mask'] ) );
			$info['owner'] = (string) ( $card['owner'] ?? '' );
		}
		$esn = $order->get_meta( '_flxb_esnekpos' );
		if ( FLXB_WC_Esnekpos::ID === $order->get_payment_method() || is_array( $esn ) ) {
			$info['provider'] = 'EsnekPOS';
			if ( is_array( $esn ) ) {
				$info['installment'] = max( 1, (int) ( $esn['installment'] ?? 1 ) );
				$info['amount']      = (float) ( $esn['amount'] ?? $info['amount'] );
				$info['bank_ref']    = (string) ( '' !== (string) ( $esn['refno'] ?? '' ) ? $esn['refno'] : ( $esn['ref'] ?? '' ) );
				$info['txn']         = (string) ( $esn['txn'] ?? '' );
			} elseif ( $order->get_meta( '_flxb_esn_installments' ) ) {
				$info['installment'] = (int) $order->get_meta( '_flxb_esn_installments' );
			}
			if ( '' !== (string) $order->get_meta( '_flxb_esnek_test' ) ) {
				$info['test'] = (bool) (int) $order->get_meta( '_flxb_esnek_test' );
			}
		}
		$iz = $order->get_meta( '_flxb_iyzico' );
		if ( is_array( $iz ) && ! empty( $iz['paymentId'] ) ) {
			$info['provider'] = 'iyzico';
			$info['bank_ref'] = (string) $iz['paymentId'];
			$info['amount']   = (float) ( $iz['paidPrice'] ?? $info['amount'] );
			if ( '' === $info['card'] && ! empty( $iz['card'] ) ) {
				$info['card'] = (string) $iz['card'];
			}
			if ( ! empty( $iz['installment'] ) ) {
				$info['installment'] = (int) $iz['installment'];
			}
			if ( '' !== (string) $order->get_meta( '_flxb_iyz_sandbox' ) ) {
				$info['test'] = (bool) (int) $order->get_meta( '_flxb_iyz_sandbox' );
			}
		}
		/**
		 * Lets another payment plugin describe its own payment (Flux POS fills this).
		 *
		 * @param array    $info  Details above; '' / 0 / null = unknown.
		 * @param WC_Order $order The order.
		 */
		$info = (array) apply_filters( 'flxb_payment_details', $info, $order );
		$info['installment'] = (int) ( $info['installment'] ?? 0 );
		return $info;
	}

	/** "454360******4242" → "4543 60•• •••• 4242". */
	private static function pretty( $mask ) {
		return str_replace( '*', '•', trim( implode( ' ', str_split( $mask, 4 ) ) ) );
	}

	/** "tek çekim" / "3 taksit". */
	public static function installment_label( $n ) {
		$n = (int) $n;
		if ( $n < 1 ) {
			return '';
		}
		/* translators: %d: number of installments */
		return 1 === $n ? __( 'single payment', 'flx-donate' ) : sprintf( __( '%d installments', 'flx-donate' ), $n );
	}

	/** One line: "Card · EsnekPOS · ₺100.00 · single payment". */
	public static function summary( array $info ) {
		$bits = array();
		if ( '' !== (string) $info['card'] || $info['installment'] > 0 ) {
			$bits[] = __( 'Card', 'flx-donate' );
		} elseif ( '' !== (string) $info['method'] ) {
			$bits[] = (string) $info['method'];
		}
		if ( '' !== (string) $info['provider'] ) {
			$bits[] = (string) $info['provider'];
		}
		$bits[] = FLXB_WC::money_text( (float) $info['amount'], (string) $info['currency'] );
		$inst   = self::installment_label( $info['installment'] );
		if ( '' !== $inst ) {
			$bits[] = $inst;
		}
		return implode( ' · ', array_filter( $bits, static fn( $v ): bool => '' !== (string) $v ) );
	}

	/** Label => escaped HTML value, empty ones left out. */
	public static function rows( array $info ) {
		$rows = array(
			__( 'Payment method', 'flx-donate' )   => esc_html( (string) $info['method'] ),
			__( 'Payment provider', 'flx-donate' ) => esc_html( (string) $info['provider'] ),
			__( 'Card', 'flx-donate' )             => '' !== (string) $info['card'] ? '<code>' . esc_html( (string) $info['card'] ) . '</code>' : '',
			__( 'Card holder', 'flx-donate' )      => esc_html( (string) $info['owner'] ),
			__( 'Installments', 'flx-donate' )     => esc_html( self::installment_label( $info['installment'] ) ),
			__( 'Amount charged', 'flx-donate' )   => esc_html( FLXB_WC::money_text( (float) $info['amount'], (string) $info['currency'] ) ),
			__( 'Bank reference', 'flx-donate' )   => '' !== (string) $info['bank_ref'] ? '<code>' . esc_html( (string) $info['bank_ref'] ) . '</code>' : '',
			__( 'Authorization code', 'flx-donate' ) => '' !== (string) $info['auth_code'] ? '<code>' . esc_html( (string) $info['auth_code'] ) . '</code>' : '',
			__( 'Transaction ID', 'flx-donate' )   => '' !== (string) $info['txn'] ? '<code>' . esc_html( (string) $info['txn'] ) . '</code>' : '',
			__( 'Mode', 'flx-donate' )             => null === $info['test'] ? '' : ( $info['test'] ? '<strong>' . esc_html__( 'Test', 'flx-donate' ) . '</strong>' : esc_html__( 'Live', 'flx-donate' ) ),
		);
		return array_filter( $rows, static fn( $v ): bool => '' !== (string) $v );
	}

	/** Is there anything worth a box (a card or a provider)? */
	public static function worth( array $info ) {
		return '' !== (string) $info['provider'] || '' !== (string) $info['card'] || $info['installment'] > 0;
	}

	/* ---------- WooCommerce order screen ---------- */

	public static function meta_boxes() {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		foreach ( array_unique( $screens ) as $sc ) {
			add_meta_box( 'flxb-payinfo', __( 'Payment details', 'flx-donate' ), array( __CLASS__, 'order_box' ), $sc, 'normal', 'high' );
		}
	}

	public static function order_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		$info = self::get( $order );
		if ( ! $order->is_paid() && ! self::worth( $info ) ) {
			echo '<p>' . esc_html__( 'No payment has been taken for this order yet.', 'flx-donate' ) . '</p>';
			return;
		}
		echo '<p style="font-size:15px;font-weight:600;margin:4px 0 10px">' . esc_html( self::summary( $info ) ) . '</p>';
		echo '<table class="widefat striped" style="border:0"><tbody>';
		foreach ( self::rows( $info ) as $k => $v ) {
			echo '<tr><th scope="row" style="width:180px">' . esc_html( $k ) . '</th><td>' . $v . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in rows()
		}
		echo '</tbody></table>';
	}
}
