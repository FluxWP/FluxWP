<?php
/**
 * PayNKolay (Nkolay İşlem).
 *
 * 3D: /Vpos/v1/Payment (use3D=true) bankanın 3D sayfasının HTML'ini (BANK_REQUEST_MESSAGE) döner; dönüşte
 * REFERENCE_CODE ile /Vpos/v1/CompletePayment çağrılır, onay ve sipariş no sunucudan alınır.
 * İmza: base64(SHA-512(sx|clientRefCode|amount|successUrl|failUrl|rnd|customerKey|gizli anahtar)).
 *
 * Protokol başvurusu: PayNKolay API belgeleri; açık kaynak CP.VPOS (MIT).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Paynkolay extends FLXPOS_Driver {

	protected static function h( $s ) {
		return base64_encode( hash( 'sha512', $s, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	protected function api( $path, array $f ) {
		return flxpos_json( $this->post_form( rtrim( $this->url( 'api' ), '/' ) . $path, $f ), $this->name() );
	}

	protected function done( $r ) {
		return is_array( $r ) && 2 === (int) ( $r['RESPONSE_CODE'] ?? 0 );
	}

	protected function msg( $r ) {
		return $this->reason( $r['RESPONSE_DATA'] ?? ( $r['ERROR_MESSAGE'] ?? '' ), $r['ERROR_CODE'] ?? '' );
	}

	protected function now() {
		return gmdate( 'd.m.Y H:i:s', time() + 3 * HOUR_IN_SECONDS );
	}

	public function start( FLXPOS_Payment $p ) {
		$three = 'regular' !== $this->mode();
		$f     = array(
			'sx'             => $this->c( 'sx' ),
			'clientRefCode'  => $p->ref,
			'amount'         => flxpos_dec( $p->amount ),
			'installmentNo'  => (string) max( 1, (int) $p->installment ),
			'cardHolderName' => flxpos_ascii( $p->holder(), 60 ),
			'month'          => $p->mm(),
			'year'           => (string) ( 2000 + (int) $p->yy() ),
			'cvv'            => $p->cvv(),
			'cardNumber'     => $p->pan(),
			'transactionType' => 'SALES',
			'rnd'            => $this->now(),
			'environment'    => 'API',
			'currencyNumber' => flxpos_currency_num( $p->currency ),
			'cardHolderIP'   => $p->ip,
			'successUrl'     => $three ? $p->return_url : '',
			'failUrl'        => $three ? ( $p->fail_url ? $p->fail_url : $p->return_url ) : '',
			'customerKey'    => '',
			'use3D'          => $three ? 'true' : 'false',
		);
		$f['hashDatav2'] = self::h( implode( '|', array( $f['sx'], $f['clientRefCode'], $f['amount'], $f['successUrl'], $f['failUrl'], $f['rnd'], $f['customerKey'], $this->c( 'secret_key' ) ) ) );
		$r               = $this->api( '/Vpos/v1/Payment', $f );
		if ( is_wp_error( $r ) ) {
			return $three ? $r : array( 'result' => $this->pending( $r->get_error_message() ) );
		}
		if ( $this->done( $r ) && 'true' === (string) ( $r['USE_3D'] ?? '' ) && $three ) {
			return array( 'html' => (string) $r['BANK_REQUEST_MESSAGE'] );
		}
		if ( ! $three && $this->done( $r ) && ! in_array( (string) ( $r['AUTH_CODE'] ?? '' ), array( '', '0' ), true ) ) {
			return array(
				'result' => $this->ok(
					array(
						'bank_ref'  => (string) ( $r['REFERENCE_CODE'] ?? '' ),
						'auth_code' => (string) $r['AUTH_CODE'],
						'amount'    => $p->amount,
						'card'      => $this->mask( $p->pan() ),
					)
				),
			);
		}
		return $three ? $this->err( $this->msg( $r ) ) : array( 'result' => $this->fail( $this->msg( $r ), array( 'card' => $this->mask( $p->pan() ) ) ) );
	}

	public function finish( array $txn, array $req ) {
		$d = $this->all( $req );
		if ( ( $d['CLIENT_REFERENCE_CODE'] ?? $txn['ref'] ) !== $txn['ref'] ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		if ( 2 !== (int) ( $d['RESPONSE_CODE'] ?? 0 ) ) {
			return $this->fail( $this->msg( $d ) );
		}
		$r = $this->api(
			'/Vpos/v1/CompletePayment',
			array(
				'sx'            => $this->c( 'sx' ),
				'referenceCode' => (string) ( $d['REFERENCE_CODE'] ?? '' ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $this->pending( $r->get_error_message() );
		}
		if ( ( $r['CLIENT_REFERENCE_CODE'] ?? $txn['ref'] ) !== $txn['ref'] ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		if ( $this->done( $r ) && ! in_array( (string) ( $r['AUTH_CODE'] ?? '' ), array( '', '0' ), true ) ) {
			return $this->ok(
				array(
					'bank_ref'  => (string) ( $r['REFERENCE_CODE'] ?? '' ),
					'auth_code' => (string) $r['AUTH_CODE'],
					'amount'    => isset( $r['AUTHORIZATION_AMOUNT'] ) ? flxpos_parse_amount( $r['AUTHORIZATION_AMOUNT'] ) : (float) $txn['amount'],
				)
			);
		}
		return $this->fail( $this->msg( $r ) );
	}

	protected function back( $type, array $txn, $amount ) {
		$f               = array(
			'sx'            => $this->c( 'sx_cancel' ),
			'referenceCode' => $txn['bank_ref'],
			'type'          => $type,
			'amount'        => 'refund' === $type ? flxpos_dec( $amount ) : '',
			'trxDate'       => '',
		);
		$f['hashDatav2'] = self::h( implode( '|', array( $f['sx'], $f['referenceCode'], $f['type'], $f['amount'], $f['trxDate'], $this->c( 'secret_key' ) ) ) );
		$r               = $this->api( '/Vpos/v1/CancelRefundPayment', $f );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array(
			'ok'       => $this->done( $r ),
			'message'  => $this->done( $r ) ? '' : $this->msg( $r ),
			'bank_ref' => (string) ( $r['REFERENCE_CODE'] ?? '' ),
		);
	}

	public function refund( array $txn, $amount ) {
		return $this->back( 'refund', $txn, $amount );
	}

	public function cancel( array $txn ) {
		return $this->back( 'cancel', $txn, 0 );
	}

	public function test_connection() {
		$day = gmdate( 'd.m.Y', time() + 3 * HOUR_IN_SECONDS );
		$r   = $this->api(
			'/Vpos/Payment/GetMerchandInformation',
			array(
				'sx'         => $this->c( 'sx' ),
				'date'       => $day,
				'hashDatav2' => self::h( $this->c( 'sx' ) . '|' . $day . '|' . $this->c( 'secret_key' ) ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return $this->done( $r ) ? true : $this->err( $this->msg( $r ) );
	}
}
