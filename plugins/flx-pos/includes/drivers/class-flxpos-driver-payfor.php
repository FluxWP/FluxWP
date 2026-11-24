<?php
/**
 * PayFor: QNB (Finansbank) ve Ziraat Katılım.
 *
 * Modeller: 3d_pay, 3d (3DModel + "3DModelPayment" provizyonu), 3d_host (bankanın ödeme sayfası), regular.
 * Form imzası: base64(SHA-1(MbrId OrderId PurchAmount OkUrl FailUrl TxnType InstallmentCount Rnd StoreKey)).
 * Dönüş imzası: base64(SHA-1(MerchantId StoreKey OrderId AuthCode ProcReturnCode 3DStatus ResponseRnd UserCode)).
 *
 * Protokol başvurusu: QNB PayFor kılavuzu; açık kaynak mews/pos (MIT).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Payfor extends FLXPOS_Driver {

	protected static function h( $s ) {
		return base64_encode( sha1( $s, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	protected function secure() {
		$m = array(
			'3d'      => '3DModel',
			'3d_pay'  => '3DPay',
			'3d_host' => '3DHost',
		);
		return $m[ $this->mode() ] ?? '3DPay';
	}

	protected function mbr() {
		return $this->c( 'mbr_id', (string) $this->opt( 'mbr_id', '5' ) );
	}

	public function start( FLXPOS_Payment $p ) {
		if ( 'regular' === $this->mode() ) {
			$r = $this->api(
				array(
					'MOTO'             => '0',
					'OrderId'          => $p->ref,
					'SecureType'       => 'NonSecure',
					'TxnType'          => 'Auth',
					'PurchAmount'      => flxpos_dec( $p->amount ),
					'Currency'         => flxpos_currency_num( $p->currency ),
					'InstallmentCount' => $p->inst( '0' ),
					'Lang'             => 'tr' === $p->lang ? 'TR' : 'EN',
					'CardHolderName'   => flxpos_ascii( $p->holder(), 60 ),
					'Pan'              => $p->pan(),
					'Expiry'           => $p->mm() . $p->yy(),
					'Cvv2'             => $p->cvv(),
				)
			);
			if ( is_wp_error( $r ) ) {
				return array( 'result' => $this->pending( $r->get_error_message() ) );
			}
			return array( 'result' => $this->api_result( $r, array( 'card' => $this->mask( $p->pan() ), 'amount' => $p->amount ) ) );
		}
		$f = array(
			'MbrId'            => $this->mbr(),
			'MerchantID'       => $this->c( 'merchant_id' ),
			'UserCode'         => $this->c( 'user_code' ),
			'OrderId'          => $p->ref,
			'Lang'             => 'tr' === $p->lang ? 'TR' : 'EN',
			'SecureType'       => $this->secure(),
			'TxnType'          => 'Auth',
			'PurchAmount'      => flxpos_dec( $p->amount ),
			'InstallmentCount' => $p->inst( '0' ),
			'Currency'         => flxpos_currency_num( $p->currency ),
			'OkUrl'            => $p->return_url,
			'FailUrl'          => $p->fail_url ? $p->fail_url : $p->return_url,
			'Rnd'              => $this->rnd( 20 ),
		);
		if ( $this->needs_card() ) {
			$f['CardHolderName'] = flxpos_ascii( $p->holder(), 60 );
			$f['Pan']            = $p->pan();
			$f['Expiry']         = $p->mm() . $p->yy();
			$f['Cvv2']           = $p->cvv();
		}
		$f['Hash'] = self::h( $f['MbrId'] . $f['OrderId'] . $f['PurchAmount'] . $f['OkUrl'] . $f['FailUrl'] . $f['TxnType'] . $f['InstallmentCount'] . $f['Rnd'] . $this->c( 'store_key' ) );
		return array(
			'form' => array(
				'url'    => $this->url( '3d_host' === $this->mode() ? 'host' : 'gate' ),
				'fields' => $f,
			),
		);
	}

	public function finish( array $txn, array $req ) {
		$d    = $this->all( $req );
		$want = self::h( $this->c( 'merchant_id' ) . $this->c( 'store_key' ) . ( $d['OrderId'] ?? '' ) . ( $d['AuthCode'] ?? '' ) . ( $d['ProcReturnCode'] ?? '' ) . ( $d['3DStatus'] ?? '' ) . ( $d['ResponseRnd'] ?? '' ) . $this->c( 'user_code' ) );
		if ( ! $this->opt( 'no_hash_check' ) && ! hash_equals( $want, (string) ( $d['ResponseHash'] ?? '' ) ) ) {
			return $this->fail( '' !== ( $d['ErrMsg'] ?? '' ) ? $this->reason( $d['ErrMsg'] ) : __( 'Banka dönüşünün imzası tutmadı.', 'flx-pos' ), array( 'data' => array( 'hash' => 'mismatch' ) ) );
		}
		if ( ( $d['OrderId'] ?? '' ) !== $txn['ref'] ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		$st = (string) ( $d['3DStatus'] ?? '' );
		if ( '1' !== $st ) {
			return $this->fail( $this->reason( $d['ErrMsg'] ?? '', '3D' . $st ) );
		}
		if ( '3d' === $this->mode() ) {
			$r = $this->api(
				array(
					'RequestGuid' => (string) ( $d['RequestGuid'] ?? '' ),
					'OrderId'     => $txn['ref'],
					'SecureType'  => '3DModelPayment',
				)
			);
			if ( is_wp_error( $r ) ) {
				return $this->pending( $r->get_error_message() );
			}
			$res = $this->api_result( $r, array( 'amount' => (float) $txn['amount'] ) );
			if ( 'paid' === $res['status'] && $this->opt( 'no_hash_check' ) ) {
				return $this->verify_by_status( $txn, $res );
			}
			return $res;
		}
		if ( '00' === ( $d['ProcReturnCode'] ?? '' ) ) {
			$res = $this->ok(
				array(
					'bank_ref'  => (string) ( $d['TransId'] ?? '' ),
					'auth_code' => (string) ( $d['AuthCode'] ?? '' ),
					'amount'    => isset( $d['PurchAmount'] ) ? flxpos_parse_amount( $d['PurchAmount'] ) : null,
					'card'      => (string) ( $d['MaskedPan'] ?? '' ),
					'data'      => array( 'HostRefNum' => $d['HostRefNum'] ?? '' ),
				)
			);
			return $this->opt( 'no_hash_check' ) ? $this->verify_by_status( $txn, $res ) : $res;
		}
		return $this->fail( $this->reason( $d['ErrMsg'] ?? '', $d['ProcReturnCode'] ?? '' ) );
	}

	/** İmzası çalışmayan altyapıda (Ziraat Katılım) başarı sorguyla doğrulanır. */
	protected function verify_by_status( array $txn, array $res ) {
		$st = $this->status( $txn );
		if ( is_wp_error( $st ) ) {
			return $this->pending( $st->get_error_message() );
		}
		return 'paid' === $st['status'] ? array_merge( $res, array( 'amount' => $st['amount'] ?? $res['amount'] ) ) : $st;
	}

	protected function api( array $body ) {
		$xml = flxpos_array_to_xml(
			'PayforRequest',
			array_merge(
				array(
					'MbrId'      => $this->mbr(),
					'MerchantId' => $this->c( 'merchant_id' ),
					'UserCode'   => $this->c( 'user_code' ),
					'UserPass'   => $this->c( 'user_pass' ),
				),
				$body
			),
			'UTF-8'
		);
		return $this->post_xml( $this->url( 'api' ), $xml );
	}

	protected function api_result( array $r, array $more = array() ) {
		if ( '00' === (string) ( $r['ProcReturnCode'] ?? '' ) ) {
			return $this->ok(
				array_merge(
					$more,
					array(
						'bank_ref'  => (string) ( $r['TransId'] ?? '' ),
						'auth_code' => (string) ( $r['AuthCode'] ?? '' ),
					)
				)
			);
		}
		return $this->fail( $this->reason( $r['ErrMsg'] ?? '', $r['ProcReturnCode'] ?? '' ), $more );
	}

	protected function simple( $r ) {
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$ok = '00' === (string) ( $r['ProcReturnCode'] ?? '' );
		return array(
			'ok'       => $ok,
			'message'  => $ok ? '' : $this->reason( $r['ErrMsg'] ?? '', $r['ProcReturnCode'] ?? '' ),
			'bank_ref' => (string) ( $r['TransId'] ?? '' ),
		);
	}

	public function refund( array $txn, $amount ) {
		return $this->simple(
			$this->api(
				array(
					'SecureType'  => 'NonSecure',
					'Lang'        => 'TR',
					'OrgOrderId'  => $txn['ref'],
					'TxnType'     => 'Refund',
					'PurchAmount' => flxpos_dec( $amount ),
					'Currency'    => flxpos_currency_num( $txn['currency'] ),
				)
			)
		);
	}

	public function cancel( array $txn ) {
		return $this->simple(
			$this->api(
				array(
					'SecureType' => 'NonSecure',
					'Lang'       => 'TR',
					'OrgOrderId' => $txn['ref'],
					'TxnType'    => 'Void',
					'Currency'   => flxpos_currency_num( $txn['currency'] ),
				)
			)
		);
	}

	public function status( array $txn ) {
		$r = $this->api(
			array(
				'OrgOrderId' => $txn['ref'],
				'SecureType' => 'Inquiry',
				'Lang'       => 'TR',
				'TxnType'    => 'OrderInquiry',
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( '00' === (string) ( $r['ProcReturnCode'] ?? '' ) && '' !== (string) ( $r['AuthCode'] ?? '' ) && 'V' !== strtoupper( (string) ( $r['VoidDate'] ?? '' ) ) ) {
			return $this->ok(
				array(
					'bank_ref'  => (string) ( $r['TransId'] ?? '' ),
					'auth_code' => (string) $r['AuthCode'],
					'amount'    => isset( $r['PurchAmount'] ) ? flxpos_parse_amount( $r['PurchAmount'] ) : null,
				)
			);
		}
		return $this->fail( $this->reason( $r['ErrMsg'] ?? '', $r['ProcReturnCode'] ?? '' ) );
	}

	public function test_connection() {
		$r = $this->status( array( 'ref' => 'FXTEST' . time() ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( preg_match( '/user|kullan|şifre|sifre|password|yetki|uye|merchant/i', (string) $r['message'] ) ) {
			return $this->err( $r['message'] );
		}
		return true;
	}
}
