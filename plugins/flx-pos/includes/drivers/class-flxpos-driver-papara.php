<?php
/**
 * Papara (cüzdan ile öde).
 *
 * Wallet: /payments ödeme kaydı açar (paymentUrl), ödeyen Papara'ya gider. Papara, bildirim adresine sonucu
 * POST eder; durum her zaman /payments?id= ile Papara'dan okunur (status 1 = tamamlandı) ve referans + tutar
 * eşlenir.
 *
 * Protokol başvurusu: Papara Merchant API belgeleri.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Papara extends FLXPOS_Driver {

	protected function api( $path, $body = null, $method = 'POST' ) {
		return $this->post_json( rtrim( $this->url( 'api' ), '/' ) . $path, $body, array( 'ApiKey' => $this->c( 'api_key' ) ), $method );
	}

	protected function msg( $r ) {
		return $this->reason( $r['error']['message'] ?? '', $r['error']['code'] ?? '' );
	}

	public function start( FLXPOS_Payment $p ) {
		$r = $this->api(
			'/payments',
			array(
				'amount'           => round( (float) $p->amount, 2 ),
				'referenceId'      => $p->ref,
				'orderDescription' => mb_substr( '' !== $p->description ? $p->description : $p->ref, 0, 100 ),
				'notificationUrl'  => $p->notify_url,
				'redirectUrl'      => $p->return_url,
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( empty( $r['succeeded'] ) || empty( $r['data']['paymentUrl'] ) ) {
			return $this->err( $this->msg( $r ) );
		}
		return array(
			'redirect' => (string) $r['data']['paymentUrl'],
			'data'     => array( 'papara_id' => (string) ( $r['data']['id'] ?? '' ) ),
		);
	}

	protected function read( $id, array $txn ) {
		$r = $this->api( '/payments?id=' . rawurlencode( (string) $id ), null, 'GET' );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$d = (array) ( $r['data'] ?? array() );
		if ( empty( $r['succeeded'] ) || ( $d['referenceId'] ?? '' ) !== $txn['ref'] ) {
			return $this->fail( $this->msg( $r ) );
		}
		if ( 1 === (int) ( $d['status'] ?? 0 ) ) {
			return $this->ok(
				array(
					'bank_ref' => (string) $d['id'],
					'amount'   => isset( $d['amount'] ) ? (float) $d['amount'] : null,
				)
			);
		}
		if ( 0 === (int) ( $d['status'] ?? 0 ) ) {
			return $this->pending( __( 'Ödeme sağlayıcıda beklemede.', 'flx-pos' ), array( 'bank_ref' => (string) $d['id'] ) );
		}
		return $this->fail( __( 'Ödeme tamamlanmadı.', 'flx-pos' ) );
	}

	public function finish( array $txn, array $req ) {
		$id = (string) ( $txn['data']['papara_id'] ?? '' );
		if ( '' === $id ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		$r = $this->read( $id, $txn );
		return is_wp_error( $r ) ? $this->pending( $r->get_error_message() ) : $r;
	}

	public function notify( array $req ) {
		$body = json_decode( (string) ( $req['raw'] ?? '' ), true );
		$id   = is_array( $body ) ? (string) ( $body['id'] ?? '' ) : $this->in( $req, 'id' );
		$ref  = is_array( $body ) ? (string) ( $body['referenceId'] ?? '' ) : $this->in( $req, 'referenceId' );
		if ( '' === $id || '' === $ref ) {
			return $this->err( 'bad notification' );
		}
		$res = $this->read( $id, array( 'ref' => $ref ) );
		return array(
			'ref'    => $ref,
			'result' => is_wp_error( $res ) ? $this->pending( $res->get_error_message() ) : $res,
			'reply'  => 'OK',
		);
	}

	public function status( array $txn ) {
		return $this->read( (string) ( $txn['data']['papara_id'] ?? $txn['bank_ref'] ), $txn );
	}

	public function refund( array $txn, $amount ) {
		if ( ! flxpos_same_amount( $amount, $txn['amount'] ) ) {
			return $this->err( __( 'Papara yalnız tam iade yapar.', 'flx-pos' ) );
		}
		$r = $this->api( '/payments/refund?id=' . rawurlencode( (string) $txn['bank_ref'] ), null, 'PUT' );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array(
			'ok'       => ! empty( $r['succeeded'] ),
			'message'  => ! empty( $r['succeeded'] ) ? '' : $this->msg( $r ),
			'bank_ref' => '',
		);
	}

	public function cancel( array $txn ) {
		return $this->refund( $txn, $txn['amount'] );
	}

	public function test_connection() {
		$r = $this->api( '/account', null, 'GET' );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return ! empty( $r['succeeded'] ) ? true : $this->err( $this->msg( $r ) );
	}
}
