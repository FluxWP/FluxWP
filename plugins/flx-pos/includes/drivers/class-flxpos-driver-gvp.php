<?php
/**
 * Garanti BBVA Sanal POS (GVP) ve Garanti Pay.
 *
 * Modeller: 3d_pay, 3d (3D doğrulama + provizyon isteği), 3d_host (3D_OOS_PAY: Garanti'nin ortak ödeme
 * sayfası), regular (3D'siz). Garanti Pay (cüzdan) aynı kapıdan txntype=gpdatarequest ile açılır.
 * Tutarlar kuruş; imza SHA-512 (büyük harf), güvenlik verisi SHA-1(şifre + 9 haneli terminal no).
 *
 * Protokol başvurusu: Garanti BBVA GVP entegrasyon kılavuzu; açık kaynak mews/pos (MIT).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Gvp extends FLXPOS_Driver {

	const VERSION = '512';

	protected function security( $refund = false ) {
		$pass = $refund && '' !== $this->c( 'refund_pass' ) ? $this->c( 'refund_pass' ) : $this->c( 'prov_pass' );
		return strtoupper( sha1( $pass . str_pad( $this->c( 'terminal_id' ), 9, '0', STR_PAD_LEFT ) ) );
	}

	protected function level() {
		$m = array(
			'3d'      => '3D',
			'3d_pay'  => '3D_PAY',
			'3d_host' => '3D_OOS_PAY',
			'wallet'  => 'CUSTOM_PAY',
		);
		return $m[ $this->mode() ] ?? '3D_PAY';
	}

	public function start( FLXPOS_Payment $p ) {
		if ( 'regular' === $this->mode() ) {
			return array( 'result' => $this->direct( $p ) );
		}
		$wallet = 'wallet' === $this->mode();
		$f      = array(
			'secure3dsecuritylevel' => $this->level(),
			'mode'                  => $this->test ? 'TEST' : 'PROD',
			'apiversion'            => self::VERSION,
			'terminalprovuserid'    => $this->c( 'prov_user', 'PROVAUT' ),
			'terminaluserid'        => $this->c( 'prov_user', 'PROVAUT' ),
			'terminalmerchantid'    => $this->c( 'merchant_id' ),
			'terminalid'            => $this->c( 'terminal_id' ),
			'txntype'               => $wallet ? 'gpdatarequest' : 'sales',
			'txnamount'             => flxpos_minor( $p->amount ),
			'txncurrencycode'       => flxpos_currency_num( $p->currency ),
			'txninstallmentcount'   => $p->inst(),
			'orderid'               => $p->ref,
			'successurl'            => $p->return_url,
			'errorurl'              => $p->fail_url ? $p->fail_url : $p->return_url,
			'customeripaddress'     => $p->ip,
			'customeremailaddress'  => (string) ( $p->customer['email'] ?? '' ),
			'lang'                  => 'tr' === $p->lang ? 'tr' : 'en',
			'txntimestamp'          => gmdate( 'YmdHis' ),
			'refreshtime'           => '0',
		);
		if ( $wallet ) {
			$f['txnsubtype']  = 'sales';
			$f['garantipay']  = 'Y';
			$f['bnsuseflag']  = 'Y';
			$f['fbbuseflag']  = 'Y';
			$f['chequeuseflag'] = 'N';
			$f['mileuseflag'] = 'N';
			$f['companyname'] = flxpos_ascii( $this->c( 'company', get_bloginfo( 'name' ) ), 50 );
			$f['txnmotoind']  = 'N';
		}
		if ( $this->needs_card() && ! $wallet ) {
			$f['cardnumber']          = $p->pan();
			$f['cardexpiredatemonth'] = $p->mm();
			$f['cardexpiredateyear']  = $p->yy();
			$f['cardcvv2']            = $p->cvv();
		}
		$f['secure3dhash'] = strtoupper(
			hash(
				'sha512',
				$f['terminalid'] . $f['orderid'] . $f['txnamount'] . $f['txncurrencycode'] . $f['successurl'] . $f['errorurl'] . $f['txntype'] . $f['txninstallmentcount'] . $this->c( 'store_key' ) . $this->security()
			)
		);
		return array(
			'form' => array(
				'url'    => $this->url( 'gate' ),
				'fields' => $f,
			),
		);
	}

	/** Dönüş imzası: hashparams'taki alanların değerleri + store key, SHA-512. */
	protected function check_hash( array $d ) {
		$params = (string) ( $d['hashparams'] ?? '' );
		$given  = (string) ( $d['hash'] ?? '' );
		if ( '' === $params || '' === $given ) {
			return false;
		}
		$s = '';
		foreach ( explode( ':', $params ) as $k ) {
			if ( '' !== $k ) {
				$s .= (string) ( $d[ $k ] ?? '' );
			}
		}
		$s .= $this->c( 'store_key' );
		$hex = strtoupper( hash( 'sha512', $s ) );
		$b64 = base64_encode( hash( 'sha512', $s, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$sha1 = base64_encode( sha1( $s, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return hash_equals( $hex, strtoupper( $given ) ) || hash_equals( $b64, $given ) || hash_equals( $sha1, $given );
	}

	public function finish( array $txn, array $req ) {
		$d = array_change_key_case( $this->all( $req ), CASE_LOWER );
		if ( ! $this->check_hash( $d ) ) {
			$msg = $d['mderrormessage'] ?? ( $d['errmsg'] ?? '' );
			return $this->fail( '' !== $msg ? $this->reason( $msg ) : __( 'Banka dönüşünün imzası tutmadı.', 'flx-pos' ), array( 'data' => array( 'hash' => 'mismatch' ) ) );
		}
		if ( ( $d['orderid'] ?? '' ) !== $txn['ref'] ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		$card = (string) ( $d['maskedpan'] ?? ( $d['cardnumber'] ?? '' ) );
		if ( 'wallet' === $this->mode() ) {
			// Garanti Pay: dönüşte ödeme sonucu var, ayrıca sorguyla doğrulanır.
			$st = $this->status( $txn );
			return is_wp_error( $st ) ? $this->pending( $st->get_error_message() ) : $st;
		}
		$md = (string) ( $d['mdstatus'] ?? '' );
		if ( ! in_array( $md, array( '1', '2', '3', '4' ), true ) ) {
			return $this->fail( $this->reason( $d['mderrormessage'] ?? ( $d['errmsg'] ?? '' ), 'md' . $md ) );
		}
		if ( '3d' === $this->mode() ) {
			$r = $this->api(
				array(
					'Customer'    => array(
						'IPAddress'    => $txn['ip'],
						'EmailAddress' => '',
					),
					'Order'       => array( 'OrderID' => $txn['ref'] ),
					'Transaction' => array(
						'Type'                  => 'sales',
						'InstallmentCnt'        => $txn['installment'] > 1 ? (string) $txn['installment'] : '',
						'Amount'                => (string) ( $d['txnamount'] ?? flxpos_minor( $txn['amount'] ) ),
						'CurrencyCode'          => (string) ( $d['txncurrencycode'] ?? flxpos_currency_num( $txn['currency'] ) ),
						'CardholderPresentCode' => '13',
						'MotoInd'               => 'N',
						'Secure3D'              => array(
							'AuthenticationCode' => (string) ( $d['cavv'] ?? '' ),
							'SecurityLevel'      => (string) ( $d['eci'] ?? '' ),
							'TxnID'              => (string) ( $d['xid'] ?? '' ),
							'Md'                 => (string) ( $d['md'] ?? '' ),
						),
					),
				),
				$txn['ref'],
				'',
				(string) ( $d['txnamount'] ?? flxpos_minor( $txn['amount'] ) ),
				(string) ( $d['txncurrencycode'] ?? flxpos_currency_num( $txn['currency'] ) )
			);
			if ( is_wp_error( $r ) ) {
				return $this->pending( $r->get_error_message() );
			}
			return $this->api_result( $r, array( 'card' => $card ) );
		}
		if ( '00' === ( $d['procreturncode'] ?? '' ) && 'Approved' === ( $d['response'] ?? '' ) ) {
			return $this->ok(
				array(
					'bank_ref'    => (string) ( $d['hostrefnum'] ?? ( $d['retrefnum'] ?? '' ) ),
					'auth_code'   => (string) ( $d['authcode'] ?? '' ),
					'amount'      => isset( $d['txnamount'] ) ? flxpos_parse_amount( $d['txnamount'], true ) : null,
					'installment' => isset( $d['txninstallmentcount'] ) ? max( 1, (int) $d['txninstallmentcount'] ) : null,
					'card'        => $card,
				)
			);
		}
		return $this->fail( $this->reason( $d['errmsg'] ?? ( $d['mderrormessage'] ?? '' ), $d['procreturncode'] ?? '' ), array( 'card' => $card ) );
	}

	protected function direct( FLXPOS_Payment $p ) {
		$amount = flxpos_minor( $p->amount );
		$cur    = flxpos_currency_num( $p->currency );
		$r      = $this->api(
			array(
				'Customer'    => array(
					'IPAddress'    => $p->ip,
					'EmailAddress' => (string) ( $p->customer['email'] ?? '' ),
				),
				'Card'        => array(
					'Number'     => $p->pan(),
					'ExpireDate' => $p->mm() . $p->yy(),
					'CVV2'       => $p->cvv(),
				),
				'Order'       => array( 'OrderID' => $p->ref ),
				'Transaction' => array(
					'Type'                  => 'sales',
					'InstallmentCnt'        => $p->inst(),
					'Amount'                => $amount,
					'CurrencyCode'          => $cur,
					'CardholderPresentCode' => '0',
					'MotoInd'               => 'N',
				),
			),
			$p->ref,
			$p->pan(),
			$amount,
			$cur
		);
		if ( is_wp_error( $r ) ) {
			return $this->pending( $r->get_error_message() );
		}
		return $this->api_result( $r, array( 'card' => $this->mask( $p->pan() ), 'amount' => $p->amount ) );
	}

	/** GVPSRequest gönder. */
	protected function api( array $body, $order_id, $card_no, $amount, $currency, $refund = false ) {
		$user = $refund && '' !== $this->c( 'refund_user' ) ? $this->c( 'refund_user' ) : $this->c( 'prov_user', 'PROVAUT' );
		$type = (string) ( $body['Transaction']['Type'] ?? '' );
		$hash = strtoupper( hash( 'sha512', $order_id . $this->c( 'terminal_id' ) . $card_no . $amount . $currency . $this->security( $refund ) ) );
		if ( in_array( $type, array( 'orderinq', 'void', 'refund' ), true ) ) {
			$hash = strtoupper( hash( 'sha512', $order_id . $this->c( 'terminal_id' ) . $amount . $currency . $this->security( $refund ) ) );
		}
		$req = array_merge(
			array(
				'Mode'     => $this->test ? 'TEST' : 'PROD',
				'Version'  => self::VERSION,
				'Terminal' => array(
					'ProvUserID' => $user,
					'HashData'   => $hash,
					'UserID'     => $user,
					'ID'         => $this->c( 'terminal_id' ),
					'MerchantID' => $this->c( 'merchant_id' ),
				),
			),
			$body
		);
		return $this->post_xml( $this->url( 'api' ), flxpos_array_to_xml( 'GVPSRequest', $req, 'UTF-8' ), array(), 'data' );
	}

	protected function api_result( array $r, array $more = array() ) {
		$t = is_array( $r['Transaction'] ?? null ) ? $r['Transaction'] : array();
		$s = is_array( $t['Response'] ?? null ) ? $t['Response'] : array();
		if ( '00' === (string) ( $s['Code'] ?? '' ) ) {
			return $this->ok(
				array_merge(
					$more,
					array(
						'bank_ref'  => (string) ( $t['RetrefNum'] ?? '' ),
						'auth_code' => (string) ( $t['AuthCode'] ?? '' ),
					)
				)
			);
		}
		return $this->fail( $this->reason( $s['ErrorMsg'] ?? ( $s['SysErrMsg'] ?? ( $s['Message'] ?? '' ) ), $s['ReasonCode'] ?? ( $s['Code'] ?? '' ) ), $more );
	}

	protected function after( array $txn, $type, $amount ) {
		$a   = flxpos_minor( $amount );
		$cur = flxpos_currency_num( $txn['currency'] );
		$r   = $this->api(
			array(
				'Customer'    => array(
					'IPAddress'    => $txn['ip'],
					'EmailAddress' => '',
				),
				'Order'       => array( 'OrderID' => $txn['ref'] ),
				'Transaction' => array(
					'Type'                  => $type,
					'InstallmentCnt'        => '',
					'Amount'                => $a,
					'CurrencyCode'          => $cur,
					'CardholderPresentCode' => '0',
					'MotoInd'               => 'N',
					'OriginalRetrefNum'     => $txn['bank_ref'],
				),
			),
			$txn['ref'],
			'',
			$a,
			$cur,
			true
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$res = $this->api_result( $r );
		return array(
			'ok'       => 'paid' === $res['status'],
			'message'  => $res['message'],
			'bank_ref' => $res['bank_ref'],
		);
	}

	public function refund( array $txn, $amount ) {
		return $this->after( $txn, 'refund', $amount );
	}

	public function cancel( array $txn ) {
		return $this->after( $txn, 'void', $txn['amount'] );
	}

	public function status( array $txn ) {
		$cur = flxpos_currency_num( $txn['currency'] );
		$r   = $this->api(
			array(
				'Customer'    => array(
					'IPAddress'    => $txn['ip'] ? $txn['ip'] : flxpos_server_ip(),
					'EmailAddress' => '',
				),
				'Order'       => array( 'OrderID' => $txn['ref'] ),
				'Transaction' => array(
					'Type'                  => 'orderinq',
					'InstallmentCnt'        => '',
					'Amount'                => '100',
					'CurrencyCode'          => $cur,
					'CardholderPresentCode' => '0',
					'MotoInd'               => 'N',
				),
			),
			$txn['ref'],
			'',
			'100',
			$cur
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$res = $this->api_result( $r );
		if ( 'paid' !== $res['status'] ) {
			return $res;
		}
		$o    = is_array( $r['Order'] ?? null ) ? $r['Order'] : array();
		$info = is_array( $o['OrderInqResult'] ?? null ) ? $o['OrderInqResult'] : array();
		$st   = strtoupper( (string) ( $info['Status'] ?? '' ) );
		if ( '' !== $st && ! in_array( $st, array( 'APPROVED', 'WAITINGPOSTAUTH' ), true ) ) {
			/* translators: %s: banka durum metni */
			return $this->fail( sprintf( __( 'Bankadaki durum: %s', 'flx-pos' ), $st ) );
		}
		$res['amount'] = isset( $info['AuthAmount'] ) ? flxpos_parse_amount( $info['AuthAmount'], true ) : null;
		$res['card']   = (string) ( $info['CardNumberMasked'] ?? '' );
		return $res;
	}

	public function test_connection() {
		$r = $this->status(
			array(
				'ref'      => 'FXTEST' . time(),
				'currency' => 'TRY',
				'ip'       => flxpos_server_ip(),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( preg_match( '/hash|yetki|authent|kullan|user|password|şifre/i', (string) $r['message'] ) ) {
			return $this->err( $r['message'] );
		}
		return true;
	}
}
