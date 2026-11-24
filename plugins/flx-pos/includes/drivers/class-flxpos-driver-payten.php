<?php
/**
 * Payten MSU (Merchant Safe Unipay): Paratika, VakıfPayS, ZiraatPay ve diğer MSU tabanlı kuruluşlar.
 *
 * 3D: SESSIONTOKEN ile oturum açılır; kart bilgisi ödeyenin tarayıcısından doğrudan {api}/post/sale3d/{token}
 * adresine POST edilir (3d) ya da ödeyen kuruluşun ödeme sayfasına gönderilir (hosted). Dönüş verisine
 * güvenilmez: sonuç QUERYTRANSACTION ile sunucudan sorulur, tutar ve sipariş no eşlenir.
 *
 * Protokol başvurusu: Paratika / MSU API v2 kılavuzu; açık kaynak mews/pos ve CP.VPOS (MIT).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Payten extends FLXPOS_Driver {

	protected function auth() {
		$a = array(
			'MERCHANT'         => $this->c( 'merchant' ),
			'MERCHANTUSER'     => $this->c( 'merchant_user' ),
			'MERCHANTPASSWORD' => $this->c( 'merchant_pass' ),
		);
		if ( '' !== $this->c( 'dealer_type' ) ) {
			$a['DEALERTYPENAME'] = $this->c( 'dealer_type' );
		}
		return $a;
	}

	protected function api( array $body ) {
		return flxpos_json( $this->post_form( rtrim( $this->url( 'api' ), '/' ), array_merge( $this->auth(), $body ) ), $this->name() );
	}

	protected function approved( $r ) {
		return is_array( $r ) && '00' === (string) ( $r['responseCode'] ?? '' );
	}

	protected function msg( $r ) {
		$m = trim( (string) ( $r['errorMsg'] ?? '' ) );
		if ( '' === $m ) {
			$m = (string) ( $r['pgTranErrorText'] ?? ( $r['responseMsg'] ?? '' ) );
		}
		return $this->reason( $m, $r['errorCode'] ?? ( $r['responseCode'] ?? '' ) );
	}

	protected function customer( FLXPOS_Payment $p ) {
		$c = $p->customer;
		return array(
			'CUSTOMER'          => '' !== (string) $c['id'] ? (string) $c['id'] : 'guest-' . substr( md5( (string) $c['email'] ), 0, 10 ),
			'CUSTOMERNAME'      => flxpos_ascii( (string) $c['name'], 60 ),
			'CUSTOMEREMAIL'     => (string) $c['email'],
			'CUSTOMERIP'        => $p->ip,
			'CUSTOMERPHONE'     => (string) $c['phone'],
			'BILLTOADDRESSLINE' => flxpos_ascii( (string) $c['address'], 100 ),
			'BILLTOCITY'        => flxpos_ascii( (string) $c['city'], 40 ),
			'BILLTOCOUNTRY'     => (string) ( $c['country'] ? $c['country'] : 'TR' ),
			'BILLTOPOSTALCODE'  => (string) $c['zip'],
			'BILLTOPHONE'       => (string) $c['phone'],
		);
	}

	protected function items( FLXPOS_Payment $p ) {
		$out = array();
		foreach ( $p->lines() as $i => $l ) {
			$out[] = array(
				'code'        => 'L' . ( $i + 1 ),
				'name'        => flxpos_ascii( $l['name'], 60 ),
				'description' => flxpos_ascii( $l['name'], 60 ),
				'quantity'    => (int) $l['qty'],
				'amount'      => (float) flxpos_dec( $l['price'] ),
			);
		}
		return wp_json_encode( $out );
	}

	public function start( FLXPOS_Payment $p ) {
		if ( 'regular' === $this->mode() ) {
			$r = $this->api(
				array_merge(
					$this->customer( $p ),
					array(
						'ACTION'            => 'SALE',
						'MERCHANTPAYMENTID' => $p->ref,
						'NAMEONCARD'        => flxpos_ascii( $p->holder(), 60 ),
						'CARDPAN'           => $p->pan(),
						'CARDEXPIRY'        => $p->mm() . '.' . ( 2000 + (int) $p->yy() ),
						'CARDCVV'           => $p->cvv(),
						'AMOUNT'            => flxpos_dec( $p->amount ),
						'CURRENCY'          => strtoupper( $p->currency ),
						'INSTALLMENTS'      => (string) max( 1, (int) $p->installment ),
					)
				)
			);
			if ( is_wp_error( $r ) ) {
				return array( 'result' => $this->pending( $r->get_error_message() ) );
			}
			if ( $this->approved( $r ) ) {
				return array(
					'result' => $this->ok(
						array(
							'bank_ref' => (string) ( $r['pgTranId'] ?? '' ),
							'amount'   => $p->amount,
							'card'     => $this->mask( $p->pan() ),
						)
					),
				);
			}
			return array( 'result' => $this->fail( $this->msg( $r ), array( 'card' => $this->mask( $p->pan() ) ) ) );
		}
		$r = $this->api(
			array_merge(
				$this->customer( $p ),
				array(
					'ACTION'            => 'SESSIONTOKEN',
					'SESSIONTYPE'       => 'PAYMENTSESSION',
					'MERCHANTPAYMENTID' => $p->ref,
					'ORDERITEMS'        => $this->items( $p ),
					'RETURNURL'         => $p->return_url,
					'AMOUNT'            => flxpos_dec( $p->amount ),
					'CURRENCY'          => strtoupper( $p->currency ),
				)
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( ! $this->approved( $r ) || empty( $r['sessionToken'] ) ) {
			return $this->err( $this->msg( $r ) );
		}
		$token = rawurlencode( (string) $r['sessionToken'] );
		if ( 'hosted' === $this->mode() ) {
			return array( 'redirect' => rtrim( $this->url( 'host' ), '/' ) . '/' . $token );
		}
		return array(
			'form' => array(
				'url'    => rtrim( $this->url( 'api' ), '/' ) . '/post/sale3d/' . $token,
				'fields' => array(
					'cardOwner'        => flxpos_ascii( $p->holder(), 60 ),
					'pan'              => $p->pan(),
					'expiryMonth'      => $p->mm(),
					'expiryYear'       => (string) ( 2000 + (int) $p->yy() ),
					'cvv'              => $p->cvv(),
					'installmentCount' => (string) max( 1, (int) $p->installment ),
				),
			),
		);
	}

	public function finish( array $txn, array $req ) {
		$back = $this->in( $req, 'merchantPaymentId' );
		if ( '' !== $back && $back !== $txn['ref'] ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		$st = $this->status( $txn );
		if ( is_wp_error( $st ) ) {
			return $this->pending( $st->get_error_message() );
		}
		if ( 'paid' !== $st['status'] && '00' !== $this->in( $req, 'responseCode' ) ) {
			$st['message'] = $this->msg( $this->all( $req ) );
		}
		return $st;
	}

	public function status( array $txn ) {
		$r = $this->api(
			array(
				'ACTION'            => 'QUERYTRANSACTION',
				'MERCHANTPAYMENTID' => $txn['ref'],
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( ! $this->approved( $r ) ) {
			return $this->fail( $this->msg( $r ) );
		}
		foreach ( (array) ( $r['transactionList'] ?? array() ) as $t ) {
			if ( ! is_array( $t ) ) {
				continue;
			}
			$type = strtoupper( (string) ( $t['transactionType'] ?? '' ) );
			$stat = strtoupper( (string) ( $t['transactionStatus'] ?? '' ) );
			if ( in_array( $type, array( 'SALE', 'SALE3D' ), true ) && 'AP' === $stat ) {
				return $this->ok(
					array(
						'bank_ref'    => (string) ( $t['pgTranId'] ?? '' ),
						'auth_code'   => (string) ( $t['pgTranApprCode'] ?? '' ),
						'amount'      => isset( $t['amount'] ) ? flxpos_parse_amount( $t['amount'] ) : null,
						'installment' => isset( $t['installment'] ) ? (int) $t['installment'] : null,
						'card'        => (string) ( $t['panNumber'] ?? '' ),
					)
				);
			}
		}
		return $this->fail( __( 'Bankada başarılı bir satış bulunamadı.', 'flx-pos' ) );
	}

	protected function simple( $r ) {
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array(
			'ok'       => $this->approved( $r ),
			'message'  => $this->approved( $r ) ? '' : $this->msg( $r ),
			'bank_ref' => (string) ( $r['pgTranId'] ?? '' ),
		);
	}

	public function refund( array $txn, $amount ) {
		return $this->simple(
			$this->api(
				array(
					'ACTION'            => 'REFUND',
					'PGTRANID'          => $txn['bank_ref'],
					'AMOUNT'            => flxpos_dec( $amount ),
					'CURRENCY'          => strtoupper( $txn['currency'] ),
					'REFLECTCOMMISSION' => 'No',
				)
			)
		);
	}

	public function cancel( array $txn ) {
		return $this->simple(
			$this->api(
				array(
					'ACTION'            => 'VOID',
					'PGTRANID'          => $txn['bank_ref'],
					'REFLECTCOMMISSION' => 'No',
				)
			)
		);
	}

	public function installments( $bin, $amount, $currency ) {
		$r = $this->api(
			array(
				'ACTION' => 'QUERYPAYMENTSYSTEMS',
				'BIN'    => substr( preg_replace( '/\D/', '', (string) $bin ), 0, 6 ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( ! $this->approved( $r ) ) {
			return null;
		}
		$out = array();
		foreach ( (array) ( $r['installmentPaymentSystem']['installmentList'] ?? array() ) as $i ) {
			$n = (int) ( $i['count'] ?? 0 );
			if ( $n < 1 ) {
				continue;
			}
			$rate      = (float) ( $i['commission']['rate'] ?? ( $i['customerCostCommissionRate'] ?? 0 ) );
			$out[ $n ] = array(
				'rate'  => $rate,
				'total' => round( (float) $amount * ( 1 + $rate / 100 ), 2 ),
			);
		}
		return $out;
	}

	public function test_connection() {
		$r = $this->api( array( 'ACTION' => 'QUERYMERCHANTPAYMENTSYSTEMS' ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return $this->approved( $r ) ? true : $this->err( $this->msg( $r ) );
	}
}
