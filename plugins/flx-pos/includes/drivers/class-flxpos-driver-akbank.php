<?php
/**
 * Akbank yeni sanal POS (JSON API + securepay).
 *
 * Modeller: 3d (3D doğrulama + provizyon "1000"), 3d_pay (doğrulama ve çekim bankada), 3d_host (bankanın ödeme
 * sayfası), regular. Form imzası: base64(HMAC-SHA512(alanlar, Gizli anahtar)). API isteğinin gövdesi aynı
 * anahtarla imzalanıp "auth-hash" başlığında gider. Dönüş imzası hashParams ("+" ayraçlı) alanlarıyla doğrulanır.
 *
 * Protokol başvurusu: Akbank sanal POS kılavuzu; açık kaynak mews/pos ve CP.VPOS (MIT).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Akbank extends FLXPOS_Driver {

	protected function hmac( $s ) {
		return base64_encode( hash_hmac( 'sha512', $s, $this->c( 'secret_key' ), true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	protected function now() {
		$d = new DateTime( 'now', new DateTimeZone( 'Europe/Istanbul' ) );
		return $d->format( 'Y-m-d\TH:i:s.v' );
	}

	protected function random() {
		return strtoupper( bin2hex( random_bytes( 64 ) ) );
	}

	protected function model() {
		$m = array(
			'3d'      => '3D',
			'3d_pay'  => '3D_PAY',
			'3d_host' => '3D_PAY_HOSTING',
		);
		return $m[ $this->mode() ] ?? '3D';
	}

	protected function terminal() {
		return array(
			'merchantSafeId' => $this->c( 'merchant_safe_id' ),
			'terminalSafeId' => $this->c( 'terminal_safe_id' ),
		);
	}

	protected function api( array $body ) {
		$body = array_merge(
			array(
				'version'         => '1.00',
				'requestDateTime' => $this->now(),
				'randomNumber'    => $this->random(),
				'terminal'        => $this->terminal(),
			),
			$body
		);
		$json = wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$r    = flxpos_http(
			$this->url( 'api' ),
			$json,
			array(
				'headers' => array(
					'Content-Type' => 'application/json; charset=utf-8',
					'auth-hash'    => $this->hmac( $json ),
				),
			)
		);
		return flxpos_json( $r, $this->name() );
	}

	protected function approved( $r ) {
		return is_array( $r ) && 'VPS-0000' === (string) ( $r['responseCode'] ?? '' );
	}

	protected function msg( $r ) {
		if ( '401' === (string) ( $r['code'] ?? '' ) ) {
			return __( 'Üye işyeri bilgileri hatalı; hesap ayarlarını kontrol edin.', 'flx-pos' );
		}
		return $this->reason( $r['responseMessage'] ?? '', $r['responseCode'] ?? '' );
	}

	public function start( FLXPOS_Payment $p ) {
		$exp = $p->mm() . $p->yy();
		if ( 'regular' === $this->mode() ) {
			$r = $this->api(
				array(
					'txnCode'     => '1000',
					'order'       => array( 'orderId' => $p->ref ),
					'card'        => array(
						'cardHolderName' => flxpos_ascii( $p->holder(), 60 ),
						'cardNumber'     => $p->pan(),
						'cvv2'           => $p->cvv(),
						'expireDate'     => $exp,
					),
					'transaction' => array(
						'amount'       => flxpos_dec( $p->amount ),
						'currencyCode' => (int) flxpos_currency_num( $p->currency ),
						'motoInd'      => 0,
						'installCount' => max( 1, (int) $p->installment ),
					),
					'customer'    => array(
						'emailAddress' => $p->customer['email'],
						'ipAddress'    => $p->ip,
					),
				)
			);
			if ( is_wp_error( $r ) ) {
				return array( 'result' => $this->pending( $r->get_error_message() ) );
			}
			return array( 'result' => $this->api_result( $r, array( 'card' => $this->mask( $p->pan() ), 'amount' => $p->amount ) ) );
		}
		$f = array(
			'paymentModel'    => $this->model(),
			'txnCode'         => '3000',
			'merchantSafeId'  => $this->c( 'merchant_safe_id' ),
			'terminalSafeId'  => $this->c( 'terminal_safe_id' ),
			'orderId'         => $p->ref,
			'lang'            => 'tr' === $p->lang ? 'TR' : 'EN',
			'amount'          => flxpos_dec( $p->amount ),
			'currencyCode'    => flxpos_currency_num( $p->currency ),
			'installCount'    => (string) max( 1, (int) $p->installment ),
			'okUrl'           => $p->return_url,
			'failUrl'         => $p->fail_url ? $p->fail_url : $p->return_url,
			'emailAddress'    => (string) $p->customer['email'],
			'creditCard'      => '',
			'expiredDate'     => '',
			'cvv'             => '',
			'randomNumber'    => $this->random(),
			'requestDateTime' => $this->now(),
		);
		if ( $this->needs_card() ) {
			$f['creditCard']  = $p->pan();
			$f['expiredDate'] = $exp;
			$f['cvv']         = $p->cvv();
		} else {
			unset( $f['creditCard'], $f['expiredDate'], $f['cvv'] );
		}
		$f['hash'] = $this->hmac( implode( '', $f ) );
		return array(
			'form' => array(
				'url'    => $this->url( '3d_host' === $this->mode() ? 'host' : 'gate' ),
				'fields' => $f,
			),
		);
	}

	/** Dönüş imzası: hashParams alanları sırayla, HMAC-SHA512. */
	protected function verify( array $d ) {
		$params = (string) ( $d['hashParams'] ?? '' );
		if ( '' === $params || '' === (string) ( $d['hash'] ?? '' ) ) {
			return false;
		}
		$s = '';
		foreach ( explode( '+', $params ) as $k ) {
			if ( '' !== $k ) {
				$s .= (string) ( $d[ $k ] ?? '' );
			}
		}
		return hash_equals( $this->hmac( $s ), (string) $d['hash'] );
	}

	public function finish( array $txn, array $req ) {
		$d = $this->all( $req );
		if ( ! $this->verify( $d ) ) {
			return $this->fail( '' !== ( $d['responseMessage'] ?? '' ) ? $this->reason( $d['responseMessage'] ) : __( 'Banka dönüşünün imzası tutmadı.', 'flx-pos' ), array( 'data' => array( 'hash' => 'mismatch' ) ) );
		}
		if ( ( $d['orderId'] ?? '' ) !== $txn['ref'] ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		if ( 'VPS-0000' !== ( $d['responseCode'] ?? '' ) ) {
			return $this->fail( $this->reason( $d['responseMessage'] ?? '', $d['responseCode'] ?? '' ) );
		}
		if ( '3d' === $this->mode() ) {
			if ( '1' !== (string) ( $d['mdStatus'] ?? '' ) ) {
				return $this->fail( $this->reason( $d['mdErrorMessage'] ?? '', 'md' . ( $d['mdStatus'] ?? '' ) ) );
			}
			$r = $this->api(
				array(
					'txnCode'           => '1000',
					'order'             => array( 'orderId' => $txn['ref'] ),
					'transaction'       => array(
						'amount'       => flxpos_dec( $txn['amount'] ),
						'currencyCode' => (int) flxpos_currency_num( $txn['currency'] ),
						'motoInd'      => 0,
						'installCount' => max( 1, (int) $txn['installment'] ),
					),
					'secureTransaction' => array(
						'secureId'      => (string) ( $d['secureId'] ?? '' ),
						'secureEcomInd' => (string) ( $d['secureEcomInd'] ?? '' ),
						'secureData'    => (string) ( $d['secureData'] ?? '' ),
						'secureMd'      => (string) ( $d['secureMd'] ?? '' ),
					),
				)
			);
			if ( is_wp_error( $r ) ) {
				return $this->pending( $r->get_error_message() );
			}
			return $this->api_result( $r, array( 'amount' => (float) $txn['amount'] ) );
		}
		return $this->ok(
			array(
				'bank_ref'  => (string) ( $d['rrn'] ?? $d['orderId'] ),
				'auth_code' => (string) ( $d['authCode'] ?? '' ),
				'amount'    => isset( $d['amount'] ) ? flxpos_parse_amount( $d['amount'] ) : null,
			)
		);
	}

	protected function api_result( array $r, array $more = array() ) {
		if ( $this->approved( $r ) ) {
			$t = (array) ( $r['transaction'] ?? array() );
			return $this->ok(
				array_merge(
					$more,
					array(
						'bank_ref'  => (string) ( $t['rrn'] ?? ( $r['order']['orderId'] ?? '' ) ),
						'auth_code' => (string) ( $t['authCode'] ?? '' ),
					)
				)
			);
		}
		return $this->fail( $this->msg( $r ), $more );
	}

	protected function simple( $r ) {
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array(
			'ok'       => $this->approved( $r ),
			'message'  => $this->approved( $r ) ? '' : $this->msg( $r ),
			'bank_ref' => (string) ( $r['transaction']['rrn'] ?? '' ),
		);
	}

	public function refund( array $txn, $amount ) {
		return $this->simple(
			$this->api(
				array(
					'txnCode'     => '1002',
					'order'       => array( 'orderId' => $txn['ref'] ),
					'transaction' => array(
						'amount'       => flxpos_dec( $amount ),
						'currencyCode' => (int) flxpos_currency_num( $txn['currency'] ),
					),
					'customer'    => array( 'ipAddress' => flxpos_server_ip() ),
				)
			)
		);
	}

	public function cancel( array $txn ) {
		return $this->simple(
			$this->api(
				array(
					'txnCode'  => '1003',
					'order'    => array( 'orderId' => $txn['ref'] ),
					'customer' => array( 'ipAddress' => flxpos_server_ip() ),
				)
			)
		);
	}

	public function status( array $txn ) {
		$r = $this->api(
			array(
				'txnCode' => '1010',
				'order'   => array( 'orderId' => $txn['ref'] ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( ! $this->approved( $r ) ) {
			return $this->fail( $this->msg( $r ) );
		}
		foreach ( (array) ( $r['txnDetailList'] ?? array() ) as $t ) {
			if ( is_array( $t ) && in_array( (string) ( $t['txnCode'] ?? '' ), array( '1000', '3000' ), true ) && 'S' === strtoupper( (string) ( $t['txnStatus'] ?? '' ) ) ) {
				return $this->ok(
					array(
						'bank_ref'  => (string) ( $t['rrn'] ?? '' ),
						'auth_code' => (string) ( $t['authCode'] ?? '' ),
						'amount'    => isset( $t['amount'] ) ? flxpos_parse_amount( $t['amount'] ) : null,
					)
				);
			}
		}
		return $this->fail( __( 'Bankada başarılı bir satış bulunamadı.', 'flx-pos' ) );
	}

	public function test_connection() {
		$r = $this->api(
			array(
				'txnCode' => '1010',
				'order'   => array( 'orderId' => 'FXTEST' . time() ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( '401' === (string) ( $r['code'] ?? '' ) || preg_match( '/VPS-1(0|1)0[0-9]/', (string) ( $r['responseCode'] ?? '' ) ) && preg_match( '/merchant|terminal|hash|yetki|üye/i', (string) ( $r['responseMessage'] ?? '' ) ) ) {
			return $this->err( $this->msg( $r ) );
		}
		return true;
	}
}
