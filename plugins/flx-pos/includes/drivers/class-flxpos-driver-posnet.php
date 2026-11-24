<?php
/**
 * Posnet (Yapı Kredi, World Pay üye işyerleri).
 *
 * 3D akışı: (1) oosRequestData ile kart ve tutar şifrelenir (data1, data2, sign), (2) ödeyen Yapı Kredi 3D
 * sayfasına POST edilir, (3) dönüşte oosResolveMerchantData ile paket çözülür ve MAC doğrulanır,
 * (4) oosTranData ile provizyon alınır. Tutar kuruş, para birimi TL/US/EU, sipariş numarası 20 hane (XID).
 * MAC: SHA-256 base64( xid;amount;currency;mid;SHA-256 base64(encKey;tid) ).
 *
 * Protokol başvurusu: Yapı Kredi Posnet XML kılavuzu; açık kaynak mews/pos ve better-payment (MIT).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Posnet extends FLXPOS_Driver {

	protected function cur( $code ) {
		$m = array(
			'TRY' => 'TL',
			'USD' => 'US',
			'EUR' => 'EU',
		);
		return $m[ strtoupper( $code ) ] ?? 'TL';
	}

	public static function xid( $ref ) {
		return str_pad( preg_replace( '/[^A-Za-z0-9_]/', '', (string) $ref ), 20, '0', STR_PAD_LEFT );
	}

	protected function inst( $n ) {
		return $n > 1 ? str_pad( (string) (int) $n, 2, '0', STR_PAD_LEFT ) : '00';
	}

	protected static function b64sha( $s ) {
		return base64_encode( hash( 'sha256', $s, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	protected function first_hash() {
		return self::b64sha( $this->c( 'enc_key' ) . ';' . $this->c( 'terminal_id' ) );
	}

	protected function mac( $xid, $amount, $cur ) {
		return self::b64sha( implode( ';', array( $xid, $amount, $cur, $this->c( 'merchant_id' ), $this->first_hash() ) ) );
	}

	protected function api( array $body ) {
		$xml = flxpos_array_to_xml(
			'posnetRequest',
			array_merge(
				array(
					'mid' => $this->c( 'merchant_id' ),
					'tid' => $this->c( 'terminal_id' ),
				),
				$body
			)
		);
		$r   = flxpos_http(
			$this->url( 'api' ),
			array( 'xmldata' => $xml ),
			array( 'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8' ) )
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$x = flxpos_xml_to_array( str_replace( "\0", '', $r['body'] ) );
		/* translators: %s: sağlayıcı */
		return is_array( $x ) ? $x : $this->err( sprintf( __( '%s yanıt vermedi.', 'flx-pos' ), $this->name() ) );
	}

	public function start( FLXPOS_Payment $p ) {
		$amount = flxpos_minor( $p->amount );
		$cur    = $this->cur( $p->currency );
		if ( 'regular' === $this->mode() ) {
			$r = $this->api(
				array(
					'tranDateRequired' => '1',
					'sale'             => array(
						'amount'       => $amount,
						'ccno'         => $p->pan(),
						'currencyCode' => $cur,
						'cvc'          => $p->cvv(),
						'expDate'      => $p->yy() . $p->mm(),
						'orderID'      => str_pad( preg_replace( '/[^A-Za-z0-9_]/', '', $p->ref ), 24, '0', STR_PAD_LEFT ),
						'installment'  => $this->inst( $p->installment ),
					),
				)
			);
			if ( is_wp_error( $r ) ) {
				return array( 'result' => $this->pending( $r->get_error_message() ) );
			}
			return array( 'result' => $this->tran_result( $r, array( 'card' => $this->mask( $p->pan() ), 'amount' => $p->amount ) ) );
		}
		$r = $this->api(
			array(
				'oosRequestData' => array(
					'posnetid'       => $this->c( 'posnet_id' ),
					'ccno'           => $p->pan(),
					'expDate'        => $p->yy() . $p->mm(),
					'cvc'            => $p->cvv(),
					'amount'         => $amount,
					'currencyCode'   => $cur,
					'installment'    => $this->inst( $p->installment ),
					'XID'            => self::xid( $p->ref ),
					'cardHolderName' => flxpos_ascii( $p->holder(), 60 ),
					'tranType'       => 'Sale',
				),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$o = is_array( $r['oosRequestDataResponse'] ?? null ) ? $r['oosRequestDataResponse'] : array();
		if ( '1' !== (string) ( $r['approved'] ?? '' ) || empty( $o['data1'] ) ) {
			return $this->err( $this->reason( $r['respText'] ?? '', $r['respCode'] ?? '' ) );
		}
		return array(
			'form' => array(
				'url'    => $this->url( 'gate' ),
				'fields' => array(
					'mid'               => $this->c( 'merchant_id' ),
					'posnetID'          => $this->c( 'posnet_id' ),
					'posnetData'        => (string) $o['data1'],
					'posnetData2'       => (string) ( $o['data2'] ?? '' ),
					'digest'            => (string) ( $o['sign'] ?? '' ),
					'vftCode'           => '',
					'merchantReturnURL' => $p->return_url,
					'url'               => '',
					'lang'              => 'tr' === $p->lang ? 'tr' : 'en',
				),
			),
		);
	}

	public function finish( array $txn, array $req ) {
		$bank = $this->in( $req, 'BankPacket' );
		$merc = $this->in( $req, 'MerchantPacket' );
		$sign = $this->in( $req, 'Sign' );
		if ( '' === $bank || '' === $merc ) {
			return $this->fail( __( 'Banka 3D doğrulamasını tamamlamadı.', 'flx-pos' ) );
		}
		$xid    = self::xid( $txn['ref'] );
		$amount = flxpos_minor( $txn['amount'] );
		$cur    = $this->cur( $txn['currency'] );
		$r      = $this->api(
			array(
				'oosResolveMerchantData' => array(
					'bankData'     => $bank,
					'merchantData' => $merc,
					'sign'         => $sign,
					'mac'          => $this->mac( $xid, $amount, $cur ),
				),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $this->pending( $r->get_error_message() );
		}
		$o = is_array( $r['oosResolveMerchantDataResponse'] ?? null ) ? $r['oosResolveMerchantDataResponse'] : array();
		if ( '1' !== (string) ( $r['approved'] ?? '' ) || ! $o ) {
			return $this->fail( $this->reason( $r['respText'] ?? '', $r['respCode'] ?? '' ) );
		}
		$md   = (string) ( $o['mdStatus'] ?? '' );
		$want = self::b64sha( implode( ';', array( $md, (string) ( $o['xid'] ?? '' ), (string) ( $o['amount'] ?? '' ), (string) ( $o['currency'] ?? '' ), $this->c( 'merchant_id' ), $this->first_hash() ) ) );
		if ( ! hash_equals( $want, (string) ( $o['mac'] ?? '' ) ) ) {
			return $this->fail( __( 'Banka dönüşünün imzası tutmadı.', 'flx-pos' ), array( 'data' => array( 'mac' => 'mismatch' ) ) );
		}
		if ( (string) ( $o['xid'] ?? '' ) !== $xid || (string) ( $o['amount'] ?? '' ) !== $amount ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		if ( ! in_array( $md, array( '1', '2', '3', '4' ), true ) ) {
			return $this->fail( $this->reason( $o['mdErrorMessage'] ?? '', 'md' . $md ) );
		}
		$t = $this->api(
			array(
				'oosTranData' => array(
					'bankData'     => $bank,
					'merchantData' => $merc,
					'sign'         => $sign,
					'wpAmount'     => '0',
					'mac'          => $this->mac( $xid, $amount, $cur ),
				),
			)
		);
		if ( is_wp_error( $t ) ) {
			return $this->pending( $t->get_error_message() );
		}
		return $this->tran_result( $t, array( 'amount' => (float) $txn['amount'] ) );
	}

	protected function tran_result( array $r, array $more = array() ) {
		if ( '1' === (string) ( $r['approved'] ?? '' ) ) {
			$inst = is_array( $r['instInfo'] ?? null ) ? (int) ( $r['instInfo']['inst1'] ?? 0 ) : 0;
			return $this->ok(
				array_merge(
					$more,
					array(
						'bank_ref'    => (string) ( $r['hostlogkey'] ?? '' ),
						'auth_code'   => (string) ( $r['authCode'] ?? '' ),
						'installment' => $inst > 1 ? $inst : null,
					)
				)
			);
		}
		return $this->fail( $this->reason( $r['respText'] ?? '', $r['respCode'] ?? '' ), $more );
	}

	protected function simple( $r ) {
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array(
			'ok'       => '1' === (string) ( $r['approved'] ?? '' ),
			'message'  => '1' === (string) ( $r['approved'] ?? '' ) ? '' : $this->reason( $r['respText'] ?? '', $r['respCode'] ?? '' ),
			'bank_ref' => (string) ( $r['hostlogkey'] ?? '' ),
		);
	}

	public function refund( array $txn, $amount ) {
		return $this->simple(
			$this->api(
				array(
					'tranDateRequired' => '1',
					'return'           => array(
						'amount'       => flxpos_minor( $amount ),
						'currencyCode' => $this->cur( $txn['currency'] ),
						'hostLogKey'   => $txn['bank_ref'],
					),
				)
			)
		);
	}

	public function cancel( array $txn ) {
		return $this->simple(
			$this->api(
				array(
					'tranDateRequired' => '1',
					'reverse'          => array(
						'transaction' => 'sale',
						'hostLogKey'  => $txn['bank_ref'],
					),
				)
			)
		);
	}

	public function status( array $txn ) {
		$r = $this->api( array( 'agreement' => array( 'orderID' => 'TDS_' . self::xid( $txn['ref'] ) ) ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( '1' !== (string) ( $r['approved'] ?? '' ) ) {
			return $this->fail( $this->reason( $r['respText'] ?? '', $r['respCode'] ?? '' ) );
		}
		$list = flxpos_find( $r, 'transaction', array() );
		$list = isset( $list[0] ) ? $list : array( $list );
		foreach ( (array) $list as $t ) {
			if ( is_array( $t ) && 'sale' === strtolower( (string) ( $t['tranType'] ?? 'sale' ) ) && '1' === (string) ( $t['state'] ?? ( $t['approved'] ?? '1' ) ) ) {
				return $this->ok(
					array(
						'bank_ref'  => (string) ( $t['hostLogKey'] ?? ( $t['hostlogkey'] ?? '' ) ),
						'auth_code' => (string) ( $t['authCode'] ?? '' ),
						'amount'    => isset( $t['amount'] ) ? flxpos_parse_amount( $t['amount'] ) : null,
					)
				);
			}
		}
		return $this->fail( __( 'Bankada başarılı bir satış bulunamadı.', 'flx-pos' ) );
	}

	public function test_connection() {
		$r = $this->api( array( 'agreement' => array( 'orderID' => 'TDS_' . self::xid( 'FXTEST' . time() ) ) ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$msg = (string) ( $r['respText'] ?? '' );
		if ( preg_match( '/mid|tid|yetki|terminal|merchant|ip/i', $msg ) && ! preg_match( '/bulunamad|not found|kayit/i', $msg ) ) {
			return $this->err( $msg );
		}
		return true;
	}
}
