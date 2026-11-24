<?php
/**
 * InterVPOS: Denizbank.
 *
 * Modeller: 3d_pay, 3d (3DModel + provizyon), 3d_host, regular. API düz form POST alır ve "anahtar=değer;;"
 * biçiminde yanıt verir. Form imzası: base64(SHA-1(ShopCode OrderId PurchAmount OkUrl FailUrl TxnType
 * InstallmentCount Rnd MerchantPass)); dönüş imzası HASHPARAMS'taki alanlar + MerchantPass.
 *
 * Protokol başvurusu: Denizbank InterVPOS kılavuzu; açık kaynak mews/pos (MIT).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Interpos extends FLXPOS_Driver {

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

	protected function card_type( $pan ) {
		if ( preg_match( '/^4/', $pan ) ) {
			return '0';
		}
		if ( preg_match( '/^(5[1-5]|2[2-7])/', $pan ) ) {
			return '1';
		}
		if ( preg_match( '/^3[47]/', $pan ) ) {
			return '2';
		}
		return '3';
	}

	public static function parse( $body ) {
		$out = array();
		foreach ( explode( ';;', (string) $body ) as $part ) {
			$pos = strpos( $part, '=' );
			if ( false !== $pos ) {
				$out[ trim( substr( $part, 0, $pos ) ) ] = trim( substr( $part, $pos + 1 ) );
			}
		}
		return $out;
	}

	protected function api( array $body ) {
		$r = flxpos_http(
			$this->url( 'api' ),
			array_merge(
				array(
					'ShopCode' => $this->c( 'shop_code' ),
					'UserCode' => $this->c( 'user_code' ),
					'UserPass' => $this->c( 'user_pass' ),
				),
				$body
			),
			array( 'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ) )
		);
		return is_wp_error( $r ) ? $r : self::parse( $r['body'] );
	}

	public function start( FLXPOS_Payment $p ) {
		if ( 'regular' === $this->mode() ) {
			$r = $this->api(
				array(
					'TxnType'          => 'Auth',
					'SecureType'       => 'NonSecure',
					'OrderId'          => $p->ref,
					'PurchAmount'      => flxpos_dec( $p->amount ),
					'Currency'         => flxpos_currency_num( $p->currency ),
					'InstallmentCount' => $p->inst(),
					'MOTO'             => '0',
					'Lang'             => $p->lang,
					'CardType'         => $this->card_type( $p->pan() ),
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
			'ShopCode'         => $this->c( 'shop_code' ),
			'TxnType'          => 'Auth',
			'SecureType'       => $this->secure(),
			'PurchAmount'      => flxpos_dec( $p->amount ),
			'OrderId'          => $p->ref,
			'OkUrl'            => $p->return_url,
			'FailUrl'          => $p->fail_url ? $p->fail_url : $p->return_url,
			'Rnd'              => $this->rnd( 20 ),
			'Lang'             => $p->lang,
			'Currency'         => flxpos_currency_num( $p->currency ),
			'InstallmentCount' => $p->inst(),
		);
		if ( $this->needs_card() ) {
			$f['CardType'] = $this->card_type( $p->pan() );
			$f['Pan']      = $p->pan();
			$f['Expiry']   = $p->mm() . $p->yy();
			$f['Cvv2']     = $p->cvv();
		}
		$f['Hash'] = self::h( $f['ShopCode'] . $f['OrderId'] . $f['PurchAmount'] . $f['OkUrl'] . $f['FailUrl'] . $f['TxnType'] . $f['InstallmentCount'] . $f['Rnd'] . $this->c( 'store_key' ) );
		return array(
			'form' => array(
				'url'    => $this->url( '3d_host' === $this->mode() ? 'host' : 'gate' ),
				'fields' => $f,
			),
		);
	}

	public function finish( array $txn, array $req ) {
		$d      = $this->all( $req );
		$params = (string) ( $d['HASHPARAMS'] ?? '' );
		$s      = '';
		foreach ( explode( ':', $params ) as $k ) {
			if ( '' !== $k ) {
				$s .= (string) ( $d[ $k ] ?? '' );
			}
		}
		if ( '' === $params || ! hash_equals( self::h( $s . $this->c( 'store_key' ) ), (string) ( $d['HASH'] ?? '' ) ) ) {
			return $this->fail( '' !== ( $d['ErrorMessage'] ?? '' ) ? $this->reason( $d['ErrorMessage'] ) : __( 'Banka dönüşünün imzası tutmadı.', 'flx-pos' ), array( 'data' => array( 'hash' => 'mismatch' ) ) );
		}
		if ( ( $d['OrderId'] ?? '' ) !== $txn['ref'] ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		$st = (string) ( $d['3DStatus'] ?? '' );
		if ( '1' !== $st ) {
			return $this->fail( $this->reason( $d['ErrorMessage'] ?? '', '3D' . $st ) );
		}
		if ( '3d' === $this->mode() ) {
			$r = $this->api(
				array(
					'TxnType'                 => 'Auth',
					'SecureType'              => 'NonSecure',
					'OrderId'                 => $txn['ref'],
					'PurchAmount'             => flxpos_dec( $txn['amount'] ),
					'Currency'                => flxpos_currency_num( $txn['currency'] ),
					'InstallmentCount'        => $txn['installment'] > 1 ? (string) $txn['installment'] : '',
					'MD'                      => (string) ( $d['MD'] ?? '' ),
					'PayerTxnId'              => (string) ( $d['PayerTxnId'] ?? '' ),
					'Eci'                     => (string) ( $d['Eci'] ?? '' ),
					'PayerAuthenticationCode' => (string) ( $d['PayerAuthenticationCode'] ?? '' ),
					'MOTO'                    => '0',
					'Lang'                    => 'tr',
				)
			);
			if ( is_wp_error( $r ) ) {
				return $this->pending( $r->get_error_message() );
			}
			return $this->api_result( $r, array( 'amount' => (float) $txn['amount'] ) );
		}
		if ( '00' === ( $d['ProcReturnCode'] ?? '' ) ) {
			return $this->ok(
				array(
					'bank_ref'  => (string) ( $d['TransId'] ?? '' ),
					'auth_code' => (string) ( $d['AuthCode'] ?? '' ),
					'amount'    => isset( $d['PurchAmount'] ) ? flxpos_parse_amount( $d['PurchAmount'] ) : null,
					'data'      => array( 'HostRefNum' => $d['HostRefNum'] ?? '' ),
				)
			);
		}
		return $this->fail( $this->reason( $d['ErrorMessage'] ?? '', $d['ProcReturnCode'] ?? '' ) );
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
		return $this->fail( $this->reason( $r['ErrorMessage'] ?? '', $r['ProcReturnCode'] ?? '' ), $more );
	}

	protected function simple( $r ) {
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$ok = '00' === (string) ( $r['ProcReturnCode'] ?? '' );
		return array(
			'ok'       => $ok,
			'message'  => $ok ? '' : $this->reason( $r['ErrorMessage'] ?? '', $r['ProcReturnCode'] ?? '' ),
			'bank_ref' => (string) ( $r['TransId'] ?? '' ),
		);
	}

	public function refund( array $txn, $amount ) {
		return $this->simple(
			$this->api(
				array(
					'orgOrderId'  => $txn['ref'],
					'PurchAmount' => flxpos_dec( $amount ),
					'TxnType'     => 'Refund',
					'SecureType'  => 'NonSecure',
					'Lang'        => 'tr',
					'MOTO'        => '0',
					'Currency'    => flxpos_currency_num( $txn['currency'] ),
				)
			)
		);
	}

	public function cancel( array $txn ) {
		return $this->simple(
			$this->api(
				array(
					'orgOrderId' => $txn['ref'],
					'TxnType'    => 'Void',
					'SecureType' => 'NonSecure',
					'Lang'       => 'tr',
				)
			)
		);
	}

	public function status( array $txn ) {
		$r = $this->api(
			array(
				'orgOrderId' => $txn['ref'],
				'TxnType'    => 'StatusHistory',
				'SecureType' => 'NonSecure',
				'Lang'       => 'tr',
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( '00' === (string) ( $r['ProcReturnCode'] ?? '' ) && '' !== (string) ( $r['AuthCode'] ?? '' ) ) {
			return $this->ok(
				array(
					'bank_ref'  => (string) ( $r['TransId'] ?? '' ),
					'auth_code' => (string) $r['AuthCode'],
					'amount'    => isset( $r['PurchAmount'] ) ? flxpos_parse_amount( $r['PurchAmount'] ) : null,
				)
			);
		}
		return $this->fail( $this->reason( $r['ErrorMessage'] ?? '', $r['ProcReturnCode'] ?? '' ) );
	}
}
