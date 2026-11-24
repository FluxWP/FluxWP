<?php
/**
 * Tami (Garanti BBVA ödeme kuruluşu).
 *
 * 3D: /payment/auth (callbackUrl ile) bankanın 3D sayfasının HTML'ini (base64) döner; dönüşte
 * /payment/complete-3ds ile çekim sunucudan tamamlanır. Her istek "PG-Auth-Token" (merchantId:terminalId:
 * base64(SHA-256(merchantId terminalId secretKey))) başlığı ve gövde imzası "securityHash" (HS512 JWT,
 * Tami'nin verdiği kid ve k anahtarıyla) taşır.
 *
 * Protokol başvurusu: Tami Payment API v3 belgeleri; açık kaynak CP.VPOS (MIT).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Tami extends FLXPOS_Driver {

	protected static function b64( $s ) {
		return base64_encode( $s ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	protected function sign( array $body ) {
		$head = self::b64( wp_json_encode( array( 'alg' => 'HS512', 'typ' => 'JWT', 'kid' => $this->c( 'kid' ) ) ) );
		$pay  = self::b64( wp_json_encode( $body ) );
		$k    = base64_decode( strtr( $this->c( 'k' ), '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $this->c( 'k' ) ) % 4 ) % 4 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return $head . '.' . $pay . '.' . self::b64( hash_hmac( 'sha512', $head . '.' . $pay, (string) $k, true ) );
	}

	protected function api( $path, array $body ) {
		$mid  = $this->c( 'merchant_id' );
		$tid  = $this->c( 'terminal_id' );
		$body = array_filter( $body, static fn( $v ) => null !== $v );
		$body['securityHash'] = $this->sign( $body );
		return $this->post_json(
			rtrim( $this->url( 'api' ), '/' ) . $path,
			$body,
			array(
				'Accept-Language' => 'tr',
				'PG-Api-Version'  => 'v3',
				'PG-Auth-Token'   => $mid . ':' . $tid . ':' . self::b64( hash( 'sha256', $mid . $tid . $this->c( 'secret_key' ), true ) ),
				'correlationId'   => 'Correlation' . $this->rnd( 32 ),
			)
		);
	}

	protected function success( $r ) {
		return is_array( $r ) && ! empty( $r['success'] ) && 'false' !== (string) $r['success'];
	}

	protected function msg( $r ) {
		return $this->reason( $r['errorMessage'] ?? '', $r['errorCode'] ?? '' );
	}

	public function start( FLXPOS_Payment $p ) {
		$c    = $p->customer;
		$name = trim( $c['first'] . ' ' . $c['last'] );
		$addr = array(
			'emailAddress' => (string) $c['email'],
			'address'      => '' !== (string) $c['address'] ? (string) $c['address'] : '-',
			'city'         => '' !== (string) $c['city'] ? (string) $c['city'] : 'Istanbul',
			'companyName'  => $name,
			'country'      => '' !== (string) $c['country'] ? (string) $c['country'] : 'TR',
			'district'     => '-',
			'contactName'  => $name,
			'phoneNumber'  => (string) $c['phone'],
			'zipCode'      => (string) $c['zip'],
		);
		$body = array(
			'amount'           => round( (float) $p->amount, 2 ),
			'orderId'          => $p->ref,
			'currency'         => strtoupper( $p->currency ),
			'installmentCount' => max( 1, (int) $p->installment ),
			'paymentGroup'     => 'OTHER',
			'card'             => array(
				'holderName'  => $p->holder(),
				'cvv'         => $p->cvv(),
				'number'      => $p->pan(),
				'expireMonth' => (int) $p->mm(),
				'expireYear'  => 2000 + (int) $p->yy(),
			),
			'buyer'            => array(
				'buyerId'             => '' !== (string) $c['id'] ? (string) $c['id'] : 'guest',
				'ipAddress'           => $p->ip,
				'name'                => (string) ( $c['first'] ? $c['first'] : '-' ),
				'surName'             => (string) ( $c['last'] ? $c['last'] : '-' ),
				'identityNumber'      => '11111111111',
				'city'                => $addr['city'],
				'country'             => $addr['country'],
				'zipCode'             => $addr['zipCode'],
				'emailAddress'        => $addr['emailAddress'],
				'phoneNumber'         => $addr['phoneNumber'],
				'registrationAddress' => $addr['address'],
			),
			'shippingAddress'  => $addr,
			'billingAddress'   => $addr,
		);
		if ( 'regular' !== $this->mode() ) {
			$body['callbackUrl'] = $p->return_url;
		}
		$r = $this->api( '/payment/auth', $body );
		if ( is_wp_error( $r ) ) {
			return 'regular' === $this->mode() ? array( 'result' => $this->pending( $r->get_error_message() ) ) : $r;
		}
		if ( 'regular' === $this->mode() ) {
			if ( $this->success( $r ) ) {
				return array(
					'result' => $this->ok(
						array(
							'bank_ref'  => (string) ( $r['bankReferenceNumber'] ?? '' ),
							'auth_code' => (string) ( $r['bankAuthCode'] ?? '' ),
							'amount'    => $p->amount,
							'card'      => $this->mask( $p->pan() ),
						)
					),
				);
			}
			return array( 'result' => $this->fail( $this->msg( $r ), array( 'card' => $this->mask( $p->pan() ) ) ) );
		}
		if ( ! $this->success( $r ) || empty( $r['threeDSHtmlContent'] ) ) {
			return $this->err( $this->msg( $r ) );
		}
		return array( 'html' => (string) base64_decode( (string) $r['threeDSHtmlContent'] ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}

	public function finish( array $txn, array $req ) {
		$back = $this->in( $req, 'orderId' );
		if ( '' !== $back && $back !== $txn['ref'] ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		if ( 'true' !== strtolower( $this->in( $req, 'success' ) ) ) {
			return $this->fail( $this->reason( $this->in( $req, 'errorMessage' ), $this->in( $req, 'errorCode' ) ) );
		}
		$r = $this->api( '/payment/complete-3ds', array( 'orderId' => $txn['ref'] ) );
		if ( is_wp_error( $r ) ) {
			return $this->pending( $r->get_error_message() );
		}
		if ( $this->success( $r ) ) {
			return $this->ok(
				array(
					'bank_ref'  => (string) ( $r['bankReferenceNumber'] ?? '' ),
					'auth_code' => (string) ( $r['bankAuthCode'] ?? '' ),
					'amount'    => isset( $r['amount'] ) ? (float) $r['amount'] : (float) $txn['amount'],
				)
			);
		}
		return $this->fail( $this->msg( $r ) );
	}

	protected function simple( $r ) {
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array(
			'ok'       => $this->success( $r ),
			'message'  => $this->success( $r ) ? '' : $this->msg( $r ),
			'bank_ref' => (string) ( $r['bankReferenceNumber'] ?? '' ),
		);
	}

	public function refund( array $txn, $amount ) {
		return $this->simple(
			$this->api(
				'/payment/reverse',
				array(
					'orderId' => $txn['ref'],
					'amount'  => round( (float) $amount, 2 ),
				)
			)
		);
	}

	public function cancel( array $txn ) {
		return $this->simple( $this->api( '/payment/reverse', array( 'orderId' => $txn['ref'] ) ) );
	}

	public function installments( $bin, $amount, $currency ) {
		$r = $this->api( '/installment/installment-info', array( 'binNumber' => substr( preg_replace( '/\D/', '', (string) $bin ), 0, 8 ) ) );
		if ( is_wp_error( $r ) || ! $this->success( $r ) ) {
			return is_wp_error( $r ) ? $r : null;
		}
		$out = array();
		foreach ( (array) ( $r['installments'] ?? array() ) as $i ) {
			$n = (int) ( $i['installmentNumber'] ?? ( $i['installmentCount'] ?? 0 ) );
			if ( $n > 0 ) {
				$rate      = (float) ( $i['rate'] ?? ( $i['commissionRate'] ?? 0 ) );
				$out[ $n ] = array(
					'rate'  => $rate,
					'total' => round( (float) $amount * ( 1 + $rate / 100 ), 2 ),
				);
			}
		}
		return $out;
	}

	public function test_connection() {
		$r = $this->api( '/installment/installment-info', array( 'binNumber' => '45467112' ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return $this->success( $r ) ? true : $this->err( $this->msg( $r ) );
	}
}
