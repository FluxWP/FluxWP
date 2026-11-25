<?php
/**
 * Flux Forms: payments through Flux POS (flx-pos, flxpos_charge). Registered only when Flux POS is active.
 *
 * Flow: the payment box shows the card fields (or none for a hosted-page account) → POST forms/payment/start →
 * flxpos_charge() opens a POS transaction → the browser opens the POS bridge (3D Secure / payment page) in a
 * window → POS sends it back to ?flxf_pay_return=1 (flxpos_status, flxpos_ref) → that page tells the form →
 * forms/payment/confirm checks the transaction (flxpos_txn: paid, same amount and currency). flxpos_txn_settled
 * marks the record as well, so a payment is recorded even when the window is closed early.
 *
 * Part of Flux Forms (GPL v2 or later), a fork of Easy Form Builder 4.2.0 by WhiteStudio.
 *
 * @package FLX_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLXF_Payment_POS extends FLXF_Payment_Provider_Base {

	const ID = 'flxpos';

	/** Flux POS is active and has its PHP API. */
	public static function available() {
		return function_exists( 'flxpos_charge' ) && function_exists( 'flxpos_txn' ) && class_exists( 'FLXPOS_Accounts' );
	}

	public function id() {
		return self::ID;
	}

	public function label() {
		return __( 'Flux POS', 'flx-forms' );
	}

	/** The POS account that would take a card payment in this currency (null when none). */
	public static function account( $currency ) {
		if ( ! self::available() ) {
			return null;
		}
		$a = FLXPOS_Accounts::for_card( strtoupper( (string) $currency ) );
		return is_array( $a ) && ! empty( $a['enabled'] ) ? $a : null;
	}

	/** One-time charges in a currency a POS account accepts (POS has no recurring charge for forms). */
	public function supports( $currency, $method ) {
		return 'charge' === $method && null !== self::account( $currency );
	}

	public function is_configured() {
		return self::available() && array() !== FLXPOS_Accounts::active();
	}

	/**
	 * Currencies the active accounts take: currency => {card: the payment box shows card fields, test: test mode}.
	 *
	 * @return array<string,array{card:bool,test:bool}>
	 */
	public static function currencies() {
		$out = array();
		if ( ! self::available() ) {
			return $out;
		}
		foreach ( FLXPOS_Accounts::active() as $a ) {
			$p    = class_exists( 'FLXPOS_Providers' ) ? FLXPOS_Providers::get( $a['provider'] ) : null;
			$curs = ! empty( $a['currencies'] ) ? (array) $a['currencies'] : (array) ( is_array( $p ) ? ( $p['currencies'] ?? array() ) : array() );
			foreach ( $curs as $c ) {
				$c   = strtoupper( (string) $c );
				$acc = self::account( $c );
				if ( $acc && ! isset( $out[ $c ] ) ) {
					$out[ $c ] = array(
						'card' => FLXPOS_Accounts::takes_card( $acc ),
						'test' => ! empty( $acc['test'] ),
					);
				}
			}
		}
		return $out;
	}

	public function front_assets() {
		return array(
			'scripts' => array(
				'flxf-pay-pos' => array(
					'src'  => plugins_url( 'assets/flxf-pay-pos.js', dirname( __FILE__ ) ),
					'ver'  => flxf_payments_asset_ver( 'flxf-pay-pos.js' ),
					'data' => array(
						'flxfPayPos' => array(
							'currencies' => self::currencies(),
							'i18n'       => array(
								'name'     => __( 'Name on card', 'flx-forms' ),
								'number'   => __( 'Card number', 'flx-forms' ),
								'expiry'   => __( 'Expiry (MM/YY)', 'flx-forms' ),
								'cvc'      => __( 'Security code (CVC)', 'flx-forms' ),
								'secure'   => __( 'Your card is checked by your bank (3D Secure) and is not stored on this site.', 'flx-forms' ),
								'hosted'   => __( 'You will complete the payment on the secure payment page.', 'flx-forms' ),
								'test'     => __( 'Test mode: no money is taken.', 'flx-forms' ),
								'window'   => __( 'Complete the payment in the window that opened. This page continues when it is done.', 'flx-forms' ),
								'blocked'  => __( 'The payment window was blocked. Allow pop-ups for this site and try again.', 'flx-forms' ),
								'closed'   => __( 'The payment window was closed before the payment was completed.', 'flx-forms' ),
								'failed'   => __( 'The payment was not completed. Please check your card details and try again.', 'flx-forms' ),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * @param float  $amount   Server quote.
	 * @param string $currency ISO 4217.
	 * @param array  $meta     See FLXF_Payment_Provider::create_payment().
	 */
	public function create_payment( $amount, $currency, array $meta ) {
		$acc = self::account( $currency );
		if ( ! $acc ) {
			return FLXF_Payment_Result::failed( __( 'The currency of this form is not supported.', 'flx-forms' ) );
		}
		$card = null;
		if ( FLXPOS_Accounts::takes_card( $acc ) ) {
			$in  = isset( $meta['params']['card'] ) && is_array( $meta['params']['card'] ) ? $meta['params']['card'] : array();
			$g   = static function ( $k ) use ( $in ) {
				return isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ? trim( sanitize_text_field( (string) $in[ $k ] ) ) : '';
			};
			$mm  = 0;
			$yy  = 0;
			if ( preg_match( '#^(\d{1,2})\s*/?\s*(\d{2}|\d{4})$#', preg_replace( '/\s/', '', $g( 'exp' ) ), $m ) ) {
				$mm = (int) $m[1];
				$yy = 2 === strlen( $m[2] ) ? 2000 + (int) $m[2] : (int) $m[2];
			}
			$card = array(
				'pan'   => preg_replace( '/\D/', '', $g( 'pan' ) ),
				'name'  => $g( 'name' ),
				'month' => $mm,
				'year'  => $yy,
				'cvv'   => preg_replace( '/\D/', '', $g( 'cvc' ) ),
			);
		}
		$r = flxpos_charge(
			array(
				'amount'      => $amount,
				'currency'    => $currency,
				'account'     => (string) $acc['id'],
				'description' => (string) ( $meta['description'] ?? '' ),
				'customer'    => array( 'email' => (string) ( $meta['email'] ?? '' ) ),
				'back'        => add_query_arg( 'flxf_pay_return', '1', home_url( '/' ) ),
				'card'        => $card,
				'meta'        => array(
					'flxf_ref' => (string) ( $meta['reference'] ?? '' ),
					'form_id'  => (int) ( $meta['form_id'] ?? 0 ),
				),
			)
		);
		if ( is_wp_error( $r ) ) {
			return FLXF_Payment_Result::failed( $r->get_error_message() );
		}
		$ref = (string) ( $r['txn']['ref'] ?? '' );
		if ( '' === $ref || empty( $r['redirect'] ) ) {
			return FLXF_Payment_Result::failed( __( 'The payment could not be started. Please try again.', 'flx-forms' ) );
		}
		return FLXF_Payment_Result::redirect(
			$ref,
			(string) $r['redirect'],
			array( 'record' => array( 'meta' => array( 'account' => (string) $acc['id'], 'test' => ! empty( $acc['test'] ) ) ) )
		);
	}

	/** A record is paid when its POS transaction is paid for the stored amount and currency. */
	public function verify( $record, array $params = array() ) {
		if ( ! self::available() ) {
			return FLXF_Payment_Result::failed( __( 'The payment could not be verified yet.', 'flx-forms' ) );
		}
		$txn = flxpos_txn( (string) $record->transaction_id );
		if ( ! is_array( $txn ) ) {
			return FLXF_Payment_Result::failed( __( 'Payment not found.', 'flx-forms' ) );
		}
		$status = (string) ( $txn['status'] ?? '' );
		$same   = abs( (float) ( $txn['amount'] ?? 0 ) - (float) $record->amount ) < 0.005
			&& strtoupper( (string) ( $txn['currency'] ?? '' ) ) === strtoupper( (string) $record->currency );
		if ( 'paid' === $status && $same ) {
			return FLXF_Payment_Result::completed( (string) $record->transaction_id );
		}
		if ( 'pending' === $status ) {
			return FLXF_Payment_Result::pending( (string) $record->transaction_id, array( 'message' => __( 'The payment could not be verified yet.', 'flx-forms' ) ) );
		}
		return FLXF_Payment_Result::failed( __( 'The payment was not completed. Please check your card details and try again.', 'flx-forms' ) );
	}

	/** flxpos_txn_settled: mark the form's record (paid or failed) as soon as POS settles the transaction. */
	public static function settled( $txn, $status ) {
		if ( ! is_array( $txn ) || empty( $txn['data']['meta']['flxf_ref'] ) || empty( $txn['ref'] ) ) {
			return;
		}
		$row = FLXF_Payment_Records::get_by_reference( self::ID, (string) $txn['ref'] );
		if ( ! $row || 'completed' === $row->status ) {
			return;
		}
		$p = flxf_payment_provider( self::ID );
		if ( ! $p ) {
			return;
		}
		$v = $p->verify( $row );
		if ( $v->is_paid() ) {
			FLXF_Payment_Records::update( (int) $row->id, array( 'status' => 'completed' ) );
		} elseif ( 'pending' !== $v->status ) {
			FLXF_Payment_Records::update( (int) $row->id, array( 'status' => 'failed' ) );
		}
	}

	/**
	 * ?flxf_pay_return=1: where POS sends the payment window back. It tells the form page (opener) and closes; opened
	 * without a form page it says what happened.
	 */
	public static function return_page() {
		if ( ! isset( $_GET['flxf_pay_return'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- POS signs its own return; this page only relays it to the form, which confirms on the server.
		$status = isset( $_GET['flxpos_status'] ) ? sanitize_key( wp_unslash( $_GET['flxpos_status'] ) ) : '';
		$ref    = isset( $_GET['flxpos_ref'] ) ? sanitize_text_field( wp_unslash( $_GET['flxpos_ref'] ) ) : '';
		// phpcs:enable
		nocache_headers();
		$msg = 'paid' === $status
			? __( 'Payment completed. You can close this window and return to the form.', 'flx-forms' )
			: __( 'The payment was not completed. You can close this window and try again on the form.', 'flx-forms' );
		$data = array(
			'type'   => 'flxf-pay',
			'status' => $status,
			'ref'    => $ref,
		);
		echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . esc_html( get_bloginfo( 'name' ) ) . '</title>';
		echo '<style>body{margin:0;font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;display:flex;min-height:100vh;align-items:center;justify-content:center;text-align:center;padding:24px;box-sizing:border-box}</style></head><body>';
		echo '<p>' . esc_html( $msg ) . '</p>';
		$u      = wp_parse_url( home_url() );
		$origin = ( $u['scheme'] ?? 'https' ) . '://' . ( $u['host'] ?? '' ) . ( isset( $u['port'] ) ? ':' . $u['port'] : '' );
		/* The opener can be lost on the way (a bank page with its own window policy), so the same-origin form page also
		 * hears it through BroadcastChannel and a storage event. */
		echo '<script>(function(d,o){var s=false;try{if(window.opener&&!window.opener.closed){window.opener.postMessage(d,o);s=true;}}catch(e){}try{var c=new BroadcastChannel("flxf-pay");c.postMessage(d);c.close();s=true;}catch(e){}try{localStorage.setItem("flxf-pay",JSON.stringify(Object.assign({t:Date.now()},d)));s=true;}catch(e){}if(s){setTimeout(function(){window.close();},300);}})(' . wp_json_encode( $data ) . ',' . wp_json_encode( $origin ) . ');</script>';
		echo '</body></html>';
		exit;
	}
}

add_filter(
	'flxf_payment_providers',
	static function ( $providers ) {
		if ( FLXF_Payment_POS::available() ) {
			$providers[ FLXF_Payment_POS::ID ] = 'FLXF_Payment_POS';
		}
		return $providers;
	}
);
add_action( 'flxpos_txn_settled', array( 'FLXF_Payment_POS', 'settled' ), 10, 2 );
add_action( 'template_redirect', array( 'FLXF_Payment_POS', 'return_page' ), 0 );
