<?php
/**
 * Moka (Moka United).
 *
 * 3D: DoDirectPaymentThreeD bir 3D adresi (Data.Url) döner, ödeyen oraya gönderilir. Dönüşte sonuç
 * GetDealerPaymentTrxDetailList ile sunucudan sorulur (OtherTrxCode = bizim sipariş no). CheckKey =
 * SHA-256(DealerCode "MK" Username "PD" Password).
 *
 * Protokol başvurusu: Moka API belgeleri; açık kaynak CP.VPOS (MIT).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Moka extends FLXPOS_Driver {

	protected function auth() {
		$code = $this->c( 'dealer_code' );
		$user = $this->c( 'username' );
		$pass = $this->c( 'password' );
		return array(
			'DealerCode' => $code,
			'Username'   => $user,
			'Password'   => $pass,
			'CheckKey'   => hash( 'sha256', $code . 'MK' . $user . 'PD' . $pass ),
		);
	}

	protected function api( $path, array $req, $key = 'PaymentDealerRequest' ) {
		return $this->post_json(
			rtrim( $this->url( 'api' ), '/' ) . $path,
			array(
				'PaymentDealerAuthentication' => $this->auth(),
				$key                          => $req,
			)
		);
	}

	protected function cur( $c ) {
		$c = strtoupper( (string) $c );
		return 'TRY' === $c ? 'TL' : $c;
	}

	protected function success( $r ) {
		return is_array( $r ) && 'success' === strtolower( (string) ( $r['ResultCode'] ?? '' ) ) && ! empty( $r['Data'] );
	}

	protected function msg( $r ) {
		$m = (string) ( $r['Data']['ResultMessage'] ?? ( $r['ResultMessage'] ?? '' ) );
		return $this->reason( $m, $r['Data']['ResultCode'] ?? ( $r['ResultCode'] ?? '' ) );
	}

	public function start( FLXPOS_Payment $p ) {
		$req = array(
			'CardHolderFullName' => flxpos_ascii( $p->holder(), 60 ),
			'CardNumber'         => $p->pan(),
			'ExpMonth'           => $p->mm(),
			'ExpYear'            => (string) ( 2000 + (int) $p->yy() ),
			'CvcNumber'          => $p->cvv(),
			'Amount'             => round( (float) $p->amount, 2 ),
			'Currency'           => $this->cur( $p->currency ),
			'InstallmentNumber'  => max( 1, (int) $p->installment ),
			'ClientIP'           => $p->ip,
			'OtherTrxCode'       => $p->ref,
			'IsPoolPayment'      => 0,
			'IsTokenized'        => 0,
			'Software'           => 'flx-pos',
			'IsPreAuth'          => 0,
			'Description'        => mb_substr( $p->description, 0, 200 ),
		);
		if ( 'regular' === $this->mode() ) {
			$r = $this->api( '/PaymentDealer/DoDirectPayment', $req );
			if ( is_wp_error( $r ) ) {
				return array( 'result' => $this->pending( $r->get_error_message() ) );
			}
			if ( $this->success( $r ) && ! empty( $r['Data']['IsSuccessful'] ) ) {
				return array(
					'result' => $this->ok(
						array(
							'bank_ref' => (string) ( $r['Data']['VirtualPosOrderId'] ?? '' ),
							'amount'   => $p->amount,
							'card'     => $this->mask( $p->pan() ),
						)
					),
				);
			}
			return array( 'result' => $this->fail( $this->msg( $r ), array( 'card' => $this->mask( $p->pan() ) ) ) );
		}
		$req['ReturnHash']  = 1;
		$req['RedirectUrl'] = $p->return_url;
		$r                  = $this->api( '/PaymentDealer/DoDirectPaymentThreeD', $req );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$url = (string) ( $r['Data']['Url'] ?? '' );
		if ( ! $this->success( $r ) || '' === $url ) {
			return $this->err( $this->msg( $r ) );
		}
		return array(
			'redirect' => $url,
			'data'     => array( 'code_hash' => (string) ( $r['Data']['CodeForHash'] ?? '' ) ),
		);
	}

	public function finish( array $txn, array $req ) {
		$back = $this->in( $req, 'OtherTrxCode' );
		if ( '' !== $back && $back !== $txn['ref'] ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		$st = $this->status( $txn );
		if ( is_wp_error( $st ) ) {
			return $this->pending( $st->get_error_message() );
		}
		if ( 'paid' !== $st['status'] && '' !== $this->in( $req, 'resultMessage' ) ) {
			$st['message'] = $this->reason( $this->in( $req, 'resultMessage' ), $this->in( $req, 'resultCode' ) );
		}
		return $st;
	}

	public function status( array $txn ) {
		$r = $this->api(
			'/PaymentDealer/GetDealerPaymentTrxDetailList',
			array(
				'PaymentId'    => '',
				'OtherTrxCode' => $txn['ref'],
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( ! $this->success( $r ) || empty( $r['Data']['IsSuccessful'] ) ) {
			return $this->fail( $this->msg( $r ) );
		}
		$pd = (array) ( $r['Data']['PaymentDetail'] ?? array() );
		if ( 2 === (int) ( $pd['PaymentStatus'] ?? 0 ) && 1 === (int) ( $pd['TrxStatus'] ?? 0 ) ) {
			return $this->ok(
				array(
					'bank_ref'    => (string) ( $pd['DealerPaymentId'] ?? '' ),
					'amount'      => isset( $pd['Amount'] ) ? (float) $pd['Amount'] : null,
					'installment' => isset( $pd['InstallmentNumber'] ) ? (int) $pd['InstallmentNumber'] : null,
					'card'        => (string) ( $pd['CardNumberFirstSix'] ?? '' ) . '******' . (string) ( $pd['CardNumberLastFour'] ?? '' ),
				)
			);
		}
		foreach ( (array) ( $r['Data']['ListItems'] ?? array() ) as $t ) {
			if ( is_array( $t ) && 1 === (int) ( $t['TrxStatus'] ?? 0 ) && 2 === (int) ( $t['TrxType'] ?? 0 ) ) {
				return $this->ok(
					array(
						'bank_ref' => (string) ( $t['VirtualPosOrderId'] ?? ( $pd['DealerPaymentId'] ?? '' ) ),
						'amount'   => isset( $t['Amount'] ) ? (float) $t['Amount'] : null,
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
		$ok = $this->success( $r ) && ! empty( $r['Data']['IsSuccessful'] );
		return array(
			'ok'       => $ok,
			'message'  => $ok ? '' : $this->msg( $r ),
			'bank_ref' => '',
		);
	}

	public function refund( array $txn, $amount ) {
		return $this->simple(
			$this->api(
				'/PaymentDealer/DoCreateRefundRequest',
				array(
					'VirtualPosOrderId' => '',
					'OtherTrxCode'      => $txn['ref'],
					'Amount'            => round( (float) $amount, 2 ),
				)
			)
		);
	}

	public function cancel( array $txn ) {
		return $this->simple(
			$this->api(
				'/PaymentDealer/DoVoid',
				array(
					'VirtualPosOrderId' => '',
					'OtherTrxCode'      => $txn['ref'],
					'ClientIP'          => flxpos_server_ip(),
					'VoidRefundReason'  => 2,
				)
			)
		);
	}

	public function installments( $bin, $amount, $currency ) {
		$r = $this->api(
			'/PaymentDealer/GetPaymentDealerCardInformation',
			array( 'BinNumber' => substr( preg_replace( '/\D/', '', (string) $bin ), 0, 6 ) ),
			'BankCardInformationRequest'
		);
		return is_wp_error( $r ) ? $r : null;
	}

	public function test_connection() {
		$r = $this->api( '/PaymentDealer/GetDealerPaymentTrxDetailList', array( 'OtherTrxCode' => 'FXTEST' . time() ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( false !== stripos( (string) ( $r['ResultCode'] ?? '' ), 'CheckPaymentDealerAuthentication' ) ) {
			return $this->err( $this->msg( $r ) );
		}
		return true;
	}
}
