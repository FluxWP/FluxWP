<?php
/**
 * Craftgate.
 *
 * Modeller: 3d (3ds-init → bankanın 3D sayfası (base64 HTML) → 3ds-complete) ve hosted (Ortak Ödeme Sayfası,
 * checkout-payments; sonuç token ile sunucudan okunur). İstek imzası: base64(SHA-256(tam URL + apiKey +
 * secretKey + rnd + gövde)); başlıklar x-api-key, x-rnd-key, x-auth-version: v1, x-signature.
 *
 * Protokol başvurusu: Craftgate API belgeleri ve resmi craftgate-php-client (MIT).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Craftgate extends FLXPOS_Driver {

	protected function api( $path, $body = null, $method = 'POST' ) {
		$url  = rtrim( $this->url( 'api' ), '/' ) . $path;
		$json = null === $body ? '' : wp_json_encode( $body );
		$rnd  = $this->rnd( 16 );
		$sig  = base64_encode( hash( 'sha256', $url . $this->c( 'api_key' ) . $this->c( 'secret_key' ) . $rnd . $json, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$r    = flxpos_http(
			$url,
			null === $body ? null : $json,
			array(
				'method'  => $method,
				'headers' => array(
					'Content-Type'   => 'application/json',
					'Accept'         => 'application/json',
					'x-api-key'      => $this->c( 'api_key' ),
					'x-rnd-key'      => $rnd,
					'x-auth-version' => 'v1',
					'x-signature'    => $sig,
					'lang'           => flxpos_locale(),
				),
			)
		);
		$j = flxpos_json( $r, $this->name() );
		if ( is_wp_error( $j ) ) {
			return $j;
		}
		if ( isset( $j['errors'] ) ) {
			return array( 'error' => (array) $j['errors'] );
		}
		return (array) ( $j['data'] ?? $j );
	}

	protected function msg( $r ) {
		return $this->reason( $r['error']['errorDescription'] ?? ( $r['paymentError']['description'] ?? '' ), $r['error']['errorCode'] ?? ( $r['paymentError']['errorCode'] ?? '' ) );
	}

	protected function items( FLXPOS_Payment $p ) {
		$out   = array();
		$total = flxpos_minor( $p->amount );
		$sum   = 0;
		foreach ( $p->lines() as $l ) {
			$m = (int) round( (float) $l['price'] * (int) $l['qty'] * 100 );
			if ( $m <= 0 || $sum + $m > $total ) {
				continue;
			}
			$sum  += $m;
			$out[] = array(
				'name'       => mb_substr( (string) $l['name'], 0, 100 ),
				'externalId' => (string) $l['id'],
				'price'      => $m / 100,
			);
		}
		if ( $sum < $total ) {
			$out[] = array(
				'name'       => __( 'Diğer', 'flx-pos' ),
				'externalId' => 'rest',
				'price'      => ( $total - $sum ) / 100,
			);
		}
		return $out;
	}

	protected function body( FLXPOS_Payment $p ) {
		return array(
			'price'          => round( (float) $p->amount, 2 ),
			'paidPrice'      => round( (float) $p->amount, 2 ),
			'walletPrice'    => 0,
			'currency'       => strtoupper( $p->currency ),
			'conversationId' => $p->ref,
			'externalId'     => $p->ref,
			'paymentGroup'   => 'PRODUCT',
			'paymentPhase'   => 'AUTH',
			'items'          => $this->items( $p ),
		);
	}

	public function start( FLXPOS_Payment $p ) {
		$b = $this->body( $p );
		if ( 'hosted' === $this->mode() ) {
			$b['callbackUrl'] = $p->return_url;
			$r                = $this->api( '/payment/v1/checkout-payments/init', $b );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			if ( empty( $r['pageUrl'] ) ) {
				return $this->err( $this->msg( $r ) );
			}
			return array(
				'redirect' => (string) $r['pageUrl'],
				'data'     => array( 'checkout_ref' => (string) ( $r['token'] ?? '' ) ),
			);
		}
		$b['installment'] = max( 1, (int) $p->installment );
		$b['card']        = array(
			'cardHolderName' => $p->holder(),
			'cardNumber'     => $p->pan(),
			'expireYear'     => (string) ( 2000 + (int) $p->yy() ),
			'expireMonth'    => $p->mm(),
			'cvc'            => $p->cvv(),
		);
		if ( 'regular' === $this->mode() ) {
			$r = $this->api( '/payment/v1/card-payments', $b );
			if ( is_wp_error( $r ) ) {
				return array( 'result' => $this->pending( $r->get_error_message() ) );
			}
			return array( 'result' => $this->paid( $r, $p->ref, $this->mask( $p->pan() ) ) );
		}
		$b['callbackUrl'] = $p->return_url;
		$r                = $this->api( '/payment/v1/card-payments/3ds-init', $b );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( empty( $r['htmlContent'] ) ) {
			return $this->err( $this->msg( $r ) );
		}
		return array(
			'html' => (string) base64_decode( (string) $r['htmlContent'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			'data' => array( 'payment_id' => (string) ( $r['paymentId'] ?? '' ) ),
		);
	}

	protected function paid( $r, $ref, $card = '' ) {
		if ( isset( $r['error'] ) || 'SUCCESS' !== strtoupper( (string) ( $r['paymentStatus'] ?? '' ) ) ) {
			return $this->fail( $this->msg( $r ), array( 'card' => $card ) );
		}
		if ( ( $r['conversationId'] ?? $ref ) !== $ref ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		$tx = array();
		foreach ( (array) ( $r['paymentTransactions'] ?? array() ) as $t ) {
			if ( ! empty( $t['id'] ) ) {
				$tx[] = array(
					'tx'   => (string) $t['id'],
					'paid' => (float) ( $t['paidPrice'] ?? 0 ),
				);
			}
		}
		return $this->ok(
			array(
				'bank_ref'    => (string) ( $r['id'] ?? '' ),
				'auth_code'   => (string) ( $r['authCode'] ?? '' ),
				'amount'      => isset( $r['paidPrice'] ) ? (float) $r['paidPrice'] : null,
				'installment' => isset( $r['installment'] ) ? (int) $r['installment'] : null,
				'card'        => '' !== $card ? $card : (string) ( $r['binNumber'] ?? '' ) . '******' . (string) ( $r['lastFourDigits'] ?? '' ),
				'data'        => array( 'cg_tx' => $tx ),
			)
		);
	}

	public function finish( array $txn, array $req ) {
		if ( 'hosted' === $this->mode() ) {
			$token = (string) ( $txn['data']['checkout_ref'] ?? '' );
			if ( '' === $token ) {
				return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
			}
			$r = $this->api( '/payment/v1/checkout-payments/' . rawurlencode( $token ), null, 'GET' );
			if ( is_wp_error( $r ) ) {
				return $this->pending( $r->get_error_message() );
			}
			return $this->paid( $r, $txn['ref'] );
		}
		$pid = (string) ( $txn['data']['payment_id'] ?? '' );
		if ( '' === $pid || $this->in( $req, 'paymentId', $pid ) !== $pid ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		if ( 'SUCCESS' !== strtoupper( $this->in( $req, 'status' ) ) ) {
			return $this->fail( $this->reason( $this->in( $req, 'errorDescription' ), $this->in( $req, 'errorCode' ) ) );
		}
		$r = $this->api( '/payment/v1/card-payments/3ds-complete', array( 'paymentId' => (int) $pid ) );
		if ( is_wp_error( $r ) ) {
			return $this->pending( $r->get_error_message() );
		}
		return $this->paid( $r, $txn['ref'] );
	}

	public function status( array $txn ) {
		$r = $this->api( '/payment/v1/card-payments/' . rawurlencode( (string) $txn['bank_ref'] ), null, 'GET' );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return $this->paid( $r, $txn['ref'] );
	}

	public function refund( array $txn, $amount ) {
		if ( flxpos_same_amount( $amount, (float) $txn['amount'] - (float) $txn['refunded'] ) && (float) $txn['refunded'] <= 0 ) {
			$r = $this->api(
				'/payment/v1/refunds',
				array(
					'paymentId'             => (int) $txn['bank_ref'],
					'refundDestinationType' => 'PROVIDER',
					'conversationId'        => $txn['ref'],
				)
			);
		} else {
			$tx = (array) ( $txn['data']['cg_tx'] ?? array() );
			if ( 1 !== count( $tx ) ) {
				return $this->err( __( 'Çok satırlı ödemede kısmi iade Craftgate panelinden yapılır.', 'flx-pos' ) );
			}
			$r = $this->api(
				'/payment/v1/refund-transactions',
				array(
					'paymentTransactionId'  => (int) $tx[0]['tx'],
					'refundPrice'           => round( (float) $amount, 2 ),
					'refundDestinationType' => 'PROVIDER',
					'conversationId'        => $txn['ref'],
				)
			);
		}
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$ok = ! isset( $r['error'] ) && in_array( strtoupper( (string) ( $r['status'] ?? '' ) ), array( 'SUCCESS', 'WAITING' ), true );
		return array(
			'ok'       => $ok,
			'message'  => $ok ? '' : $this->msg( $r ),
			'bank_ref' => (string) ( $r['id'] ?? '' ),
		);
	}

	public function cancel( array $txn ) {
		return $this->refund( $txn, $txn['amount'] );
	}

	public function installments( $bin, $amount, $currency ) {
		$r = $this->api( '/installment/v1/installments?binNumber=' . rawurlencode( substr( preg_replace( '/\D/', '', (string) $bin ), 0, 8 ) ) . '&price=' . rawurlencode( flxpos_dec( $amount ) ) . '&currency=' . rawurlencode( strtoupper( $currency ) ), null, 'GET' );
		if ( is_wp_error( $r ) || isset( $r['error'] ) ) {
			return is_wp_error( $r ) ? $r : null;
		}
		$out = array();
		foreach ( (array) ( $r['items'][0]['installmentPrices'] ?? array() ) as $i ) {
			$n = (int) ( $i['installmentNumber'] ?? 0 );
			if ( $n > 0 ) {
				$out[ $n ] = array(
					'total' => (float) ( $i['totalPrice'] ?? $amount ),
					'rate'  => (float) ( $i['bankCommissionRate'] ?? 0 ),
				);
			}
		}
		return $out;
	}

	public function test_connection() {
		$r = $this->api( '/installment/v1/bins/552608', null, 'GET' );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return isset( $r['error'] ) && preg_match( '/auth|signature|api ?key|yetki/i', $this->msg( $r ) ) ? $this->err( $this->msg( $r ) ) : true;
	}
}
