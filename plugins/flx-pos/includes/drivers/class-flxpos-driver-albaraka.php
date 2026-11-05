<?php
/**
 * Albaraka Türk (Posnet V1, JSON API).
 *
 * 3D: kart bilgisi MAC ile imzalanıp bankanın 3D sayfasına POST edilir; dönüşte 3D verisiyle JSON "Sale"
 * çağrılır (tutar bizim kaydımızdan gider). MAC = base64(SHA-256(değerler + ENCKEY)).
 *
 * Protokol başvurusu: Albaraka Posnet V1 kılavuzu; açık kaynak mews/pos ve better-payment (MIT).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Albaraka extends FLXPOS_Driver {

	protected function mac( array $vals ) {
		return base64_encode( hash( 'sha256', implode( '', $vals ) . $this->c( 'enc_key' ), true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	protected function cur( $c ) {
		$m = array(
			'TRY' => 'TL',
			'USD' => 'US',
			'EUR' => 'EU',
			'GBP' => 'GB',
		);
		return $m[ strtoupper( $c ) ] ?? 'TL';
	}

	protected function oid( $ref ) {
		return str_pad( preg_replace( '/[^A-Za-z0-9]/', '', (string) $ref ), 20, '0', STR_PAD_LEFT );
	}

	protected function api( $op, array $body ) {
		return $this->post_json( rtrim( $this->url( 'api' ), '/' ) . '/' . $op, $body );
	}

	protected function approved( $r ) {
		return is_array( $r ) && '00' === (string) ( $r['ServiceResponseData']['ResponseCode'] ?? '' );
	}

	protected function msg( $r ) {
		return $this->reason( $r['ServiceResponseData']['ResponseDescription'] ?? '', $r['ServiceResponseData']['ResponseCode'] ?? '' );
	}

	public function start( FLXPOS_Payment $p ) {
		$amount = flxpos_minor( $p->amount );
		$exp    = $p->yy() . $p->mm();
		$f      = array(
			'MerchantNo'        => $this->c( 'merchant_id' ),
			'TerminalNo'        => $this->c( 'terminal_id' ),
			'PosnetID'          => $this->c( 'posnet_id' ),
			'TransactionType'   => 'Sale',
			'OrderId'           => $this->oid( $p->ref ),
			'Amount'            => $amount,
			'CurrencyCode'      => $this->cur( $p->currency ),
			'MerchantReturnURL' => $p->return_url,
			'InstallmentCount'  => $p->installment > 1 ? (string) $p->installment : '0',
			'InstallmentType'   => $p->installment > 1 ? 'Y' : 'N',
			'Language'          => 'tr' === $p->lang ? 'tr' : 'en',
			'TxnState'          => 'INITIAL',
			'OpenNewWindow'     => '0',
			'CardNo'            => $p->pan(),
			'ExpiredDate'       => $exp,
			'Cvv'               => $p->cvv(),
			'CardHolderName'    => flxpos_ascii( $p->holder(), 60 ),
			'MacParams'         => 'MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate:Amount',
			'UseOOS'            => '0',
		);
		$f['MAC'] = $this->mac( array( $f['MerchantNo'], $f['TerminalNo'], $f['CardNo'], $f['Cvv'], $exp, $amount ) );
		return array(
			'form' => array(
				'url'    => $this->url( 'gate' ),
				'fields' => $f,
			),
		);
	}

	public function finish( array $txn, array $req ) {
		$md = $this->in( $req, 'MdStatus' );
		if ( '1' !== $md ) {
			return $this->fail( $this->reason( $this->in( $req, 'MdErrorMessage' ), 'md' . $md ) );
		}
		$sid  = $this->in( $req, 'SecureTransactionId' );
		$cavv = $this->in( $req, 'CAVV' );
		$eci  = $this->in( $req, 'ECI' );
		$r    = $this->api(
			'Sale',
			array(
				'ApiType'               => 'JSON',
				'ApiVersion'            => 'V100',
				'MerchantNo'            => $this->c( 'merchant_id' ),
				'TerminalNo'            => $this->c( 'terminal_id' ),
				'PaymentInstrumentType' => 'CARD',
				'IsEncrypted'           => 'N',
				'IsTDSecureMerchant'    => 'Y',
				'IsMailOrder'           => 'N',
				'ThreeDSecureData'      => array(
					'SecureTransactionId' => $sid,
					'CavvData'            => $cavv,
					'Eci'                 => $eci,
					'MdStatus'            => (int) $md,
					'MD'                  => $this->in( $req, 'MD' ),
				),
				'MACParams'             => 'MerchantNo:TerminalNo:SecureTransactionId:CavvData:Eci:MdStatus',
				'Amount'                => (int) flxpos_minor( $txn['amount'] ),
				'CurrencyCode'          => $this->cur( $txn['currency'] ),
				'PointAmount'           => 0,
				'OrderId'               => $this->oid( $txn['ref'] ),
				'InstallmentCount'      => $txn['installment'] > 1 ? (int) $txn['installment'] : 0,
				'InstallmentType'       => $txn['installment'] > 1 ? 'Y' : 'N',
				'MAC'                   => $this->mac( array( $this->c( 'merchant_id' ), $this->c( 'terminal_id' ), $sid, $cavv, $eci, $md ) ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $this->pending( $r->get_error_message() );
		}
		if ( $this->approved( $r ) ) {
			return $this->ok(
				array(
					'bank_ref'  => (string) ( $r['ReferenceCode'] ?? '' ),
					'auth_code' => (string) ( $r['AuthCode'] ?? '' ),
					'amount'    => (float) $txn['amount'],
				)
			);
		}
		return $this->fail( $this->msg( $r ) );
	}

	protected function ref_call( $op, array $txn, array $more ) {
		$r = $this->api(
			$op,
			array_merge(
				array(
					'ApiType'                => 'JSON',
					'ApiVersion'             => 'V100',
					'MACParams'              => 'MerchantNo:TerminalNo:ReferenceCode:OrderId',
					'CipheredData'           => null,
					'DealerData'             => null,
					'IsEncrypted'            => null,
					'PaymentFacilitatorData' => null,
					'OrderId'                => null,
					'MerchantNo'             => $this->c( 'merchant_id' ),
					'TerminalNo'             => $this->c( 'terminal_id' ),
					'ReferenceCode'          => $txn['bank_ref'],
					'TransactionType'        => 'Sale',
					'MAC'                    => $this->mac( array( $this->c( 'merchant_id' ), $this->c( 'terminal_id' ), $txn['bank_ref'] ) ),
				),
				$more
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array(
			'ok'       => $this->approved( $r ),
			'message'  => $this->approved( $r ) ? '' : $this->msg( $r ),
			'bank_ref' => (string) ( $r['ReferenceCode'] ?? '' ),
		);
	}

	public function refund( array $txn, $amount ) {
		return $this->ref_call(
			'Return',
			$txn,
			array(
				'Amount'       => (int) flxpos_minor( $amount ),
				'CurrencyCode' => $this->cur( $txn['currency'] ),
			)
		);
	}

	public function cancel( array $txn ) {
		return $this->ref_call( 'Reverse', $txn, array() );
	}

	public function status( array $txn ) {
		$r = $this->api(
			'TransactionInquiry',
			array(
				'ApiType'                => 'JSON',
				'ApiVersion'             => 'V100',
				'MerchantNo'             => $this->c( 'merchant_id' ),
				'TerminalNo'             => $this->c( 'terminal_id' ),
				'MACParams'              => 'MerchantNo:TerminalNo',
				'CipheredData'           => null,
				'DealerData'             => null,
				'IsEncrypted'            => 'N',
				'PaymentFacilitatorData' => null,
				'OrderId'                => $this->oid( $txn['ref'] ),
				'MAC'                    => $this->mac( array( $this->c( 'merchant_id' ), $this->c( 'terminal_id' ) ) ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( ! $this->approved( $r ) ) {
			return $this->fail( $this->msg( $r ) );
		}
		$list = (array) ( $r['TransactionDataList'] ?? array() );
		foreach ( $list as $t ) {
			if ( is_array( $t ) && 'Sale' === ( $t['TransactionType'] ?? '' ) && in_array( (string) ( $t['TransactionStatus'] ?? '' ), array( '1', 'S', 'Success' ), true ) ) {
				return $this->ok(
					array(
						'bank_ref'  => (string) ( $t['ReferenceCode'] ?? '' ),
						'auth_code' => (string) ( $t['AuthCode'] ?? '' ),
						'amount'    => isset( $t['Amount'] ) ? flxpos_parse_amount( $t['Amount'], true ) : null,
					)
				);
			}
		}
		return $this->fail( __( 'Bankada başarılı bir satış bulunamadı.', 'flx-pos' ) );
	}
}
