<?php
/**
 * Tosla (AkÖde).
 *
 * 3D: /threeDPayment ile oturum (ThreeDSessionId) açılır; kart bilgisi ödeyenin tarayıcısından
 * ProcessCardForm adresine POST edilir (3d_pay) ya da ödeyen Tosla'nın ödeme sayfasına gönderilir (3d_host).
 * İstek imzası: base64(SHA-512(apiPass clientId apiUser rnd timeSpan)). Dönüş imzası HashParameters
 * (virgüllü) alanlarıyla: base64(SHA-512(apiPass + değerler)).
 *
 * Protokol başvurusu: Tosla İşim API belgeleri; açık kaynak mews/pos (MIT).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Tosla extends FLXPOS_Driver {

	protected static function h( $s ) {
		return base64_encode( hash( 'sha512', $s, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	protected function auth() {
		$rnd  = $this->rnd( 24 );
		$time = gmdate( 'YmdHis', time() + 3 * HOUR_IN_SECONDS );
		return array(
			'clientId' => (int) $this->c( 'client_id' ),
			'apiUser'  => $this->c( 'api_user' ),
			'rnd'      => $rnd,
			'timeSpan' => $time,
			'hash'     => self::h( $this->c( 'api_pass' ) . $this->c( 'client_id' ) . $this->c( 'api_user' ) . $rnd . $time ),
		);
	}

	protected function api( $op, array $body ) {
		return $this->post_json( rtrim( $this->url( 'api' ), '/' ) . '/' . $op, array_merge( $this->auth(), $body ) );
	}

	protected function msg( $r ) {
		return $this->reason( $r['Message'] ?? ( $r['message'] ?? ( $r['BankResponseMessage'] ?? '' ) ), $r['BankResponseCode'] ?? ( $r['Code'] ?? '' ) );
	}

	protected function code0( $r ) {
		return is_array( $r ) && '0' === (string) ( $r['Code'] ?? ( $r['code'] ?? '' ) );
	}

	public function start( FLXPOS_Payment $p ) {
		$body = array(
			'orderId'          => $p->ref,
			'amount'           => (int) flxpos_minor( $p->amount ),
			'currency'         => (int) flxpos_currency_num( $p->currency ),
			'installmentCount' => max( 1, (int) $p->installment ),
		);
		if ( 'regular' === $this->mode() ) {
			$r = $this->api(
				'Payment',
				array_merge(
					$body,
					array(
						'cardHolderName' => flxpos_ascii( $p->holder(), 60 ),
						'cardNo'         => $p->pan(),
						'expireDate'     => $p->mm() . $p->yy(),
						'cvv'            => $p->cvv(),
					)
				)
			);
			if ( is_wp_error( $r ) ) {
				return array( 'result' => $this->pending( $r->get_error_message() ) );
			}
			if ( $this->code0( $r ) && '00' === (string) ( $r['BankResponseCode'] ?? '' ) ) {
				return array(
					'result' => $this->ok(
						array(
							'bank_ref'  => (string) ( $r['TransactionId'] ?? '' ),
							'auth_code' => (string) ( $r['AuthCode'] ?? '' ),
							'amount'    => $p->amount,
							'card'      => $this->mask( $p->pan() ),
						)
					),
				);
			}
			return array( 'result' => $this->fail( $this->msg( $r ), array( 'card' => $this->mask( $p->pan() ) ) ) );
		}
		$body['callbackUrl'] = $p->return_url;
		$r                   = $this->api( 'threeDPayment', $body );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$sid = (string) ( $r['ThreeDSessionId'] ?? '' );
		if ( ! $this->code0( $r ) || '' === $sid ) {
			return $this->err( $this->msg( $r ) );
		}
		if ( '3d_host' === $this->mode() ) {
			return array(
				'form' => array(
					'url'    => $this->url( 'host' ),
					'fields' => array( 'ThreeDSessionId' => $sid ),
				),
			);
		}
		return array(
			'form' => array(
				'url'    => $this->url( 'gate' ),
				'fields' => array(
					'ThreeDSessionId' => $sid,
					'CardHolderName'  => flxpos_ascii( $p->holder(), 60 ),
					'CardNo'          => $p->pan(),
					'ExpireDate'      => $p->mm() . '/' . $p->yy(),
					'Cvv'             => $p->cvv(),
				),
			),
		);
	}

	public function finish( array $txn, array $req ) {
		$d             = $this->all( $req );
		$d['ClientId'] = $this->c( 'client_id' );
		$d['ApiUser']  = $this->c( 'api_user' );
		$params        = (string) ( $d['HashParameters'] ?? '' );
		$s             = '';
		foreach ( explode( ',', $params ) as $k ) {
			if ( '' !== $k ) {
				$s .= (string) ( $d[ $k ] ?? '' );
			}
		}
		if ( '' === $params || ! hash_equals( self::h( $this->c( 'api_pass' ) . $s ), (string) ( $d['Hash'] ?? '' ) ) ) {
			return $this->fail( __( 'Banka dönüşünün imzası tutmadı.', 'flx-pos' ), array( 'data' => array( 'hash' => 'mismatch' ) ) );
		}
		if ( ( $d['OrderId'] ?? '' ) !== $txn['ref'] ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		if ( '00' !== (string) ( $d['BankResponseCode'] ?? '' ) ) {
			return $this->fail( $this->msg( $d ) );
		}
		return $this->ok(
			array(
				'bank_ref'    => (string) ( $d['TransactionId'] ?? '' ),
				'auth_code'   => (string) ( $d['AuthCode'] ?? '' ),
				'amount'      => isset( $d['Amount'] ) ? flxpos_parse_amount( $d['Amount'], true ) : null,
				'installment' => isset( $d['InstallmentCount'] ) ? max( 1, (int) $d['InstallmentCount'] ) : null,
				'card'        => (string) ( $d['MaskedNumber'] ?? '' ),
			)
		);
	}

	public function status( array $txn ) {
		$r = $this->api( 'inquiry', array( 'orderId' => $txn['ref'] ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( $this->code0( $r ) && '00' === (string) ( $r['BankResponseCode'] ?? '' ) && 0 === (int) ( $r['RequestStatus'] ?? ( $r['Status'] ?? 0 ) ) ) {
			return $this->ok(
				array(
					'bank_ref'  => (string) ( $r['TransactionId'] ?? '' ),
					'auth_code' => (string) ( $r['AuthCode'] ?? '' ),
					'amount'    => isset( $r['Amount'] ) ? flxpos_parse_amount( $r['Amount'], true ) : null,
				)
			);
		}
		return $this->fail( $this->msg( $r ) );
	}

	protected function simple( $r ) {
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$ok = $this->code0( $r ) && '00' === (string) ( $r['BankResponseCode'] ?? '00' );
		return array(
			'ok'       => $ok,
			'message'  => $ok ? '' : $this->msg( $r ),
			'bank_ref' => (string) ( $r['TransactionId'] ?? '' ),
		);
	}

	public function refund( array $txn, $amount ) {
		return $this->simple(
			$this->api(
				'refund',
				array(
					'orderId' => $txn['ref'],
					'amount'  => (int) flxpos_minor( $amount ),
				)
			)
		);
	}

	public function cancel( array $txn ) {
		return $this->simple( $this->api( 'void', array( 'orderId' => $txn['ref'] ) ) );
	}

	public function test_connection() {
		$r = $this->api( 'inquiry', array( 'orderId' => 'FXTEST' . time() ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( preg_match( '/hash|client|api ?user|yetki|auth/i', (string) ( $r['Message'] ?? '' ) ) ) {
			return $this->err( $this->msg( $r ) );
		}
		return true;
	}
}
