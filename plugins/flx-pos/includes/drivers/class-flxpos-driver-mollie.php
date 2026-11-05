<?php
/**
 * Mollie (yurt dışı: EUR, USD, GBP…; TRY desteklemez).
 *
 * Hosted: /v2/payments ödeme oluşturur, ödeyen Mollie'nin ödeme sayfasına gider. Kesin sonuç webhook ile
 * gelir (yalnız ödeme kimliği; durum Mollie'den GET ile okunur). Test/canlı, anahtarın önekinden (test_/live_)
 * anlaşılır.
 *
 * Protokol başvurusu: Mollie API v2 belgeleri.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Mollie extends FLXPOS_Driver {

	protected function api( $path, $body = null, $method = 'POST' ) {
		return $this->post_json( rtrim( $this->url( 'api' ), '/' ) . $path, $body, array( 'Authorization' => 'Bearer ' . $this->c( 'api_key' ) ), null === $body && 'POST' === $method ? 'GET' : $method );
	}

	protected function msg( $r ) {
		return $this->reason( $r['detail'] ?? ( $r['title'] ?? '' ), $r['status'] ?? '' );
	}

	public function start( FLXPOS_Payment $p ) {
		$r = $this->api(
			'/v2/payments',
			array(
				'amount'      => array(
					'currency' => strtoupper( $p->currency ),
					'value'    => flxpos_dec( $p->amount ),
				),
				'description' => mb_substr( '' !== $p->description ? $p->description : $p->ref, 0, 255 ),
				'redirectUrl' => $p->return_url,
				'webhookUrl'  => $p->notify_url,
				'locale'      => 'tr' === $p->lang ? 'tr_TR' : 'en_US',
				'metadata'    => array( 'ref' => $p->ref ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$url = (string) ( $r['_links']['checkout']['href'] ?? '' );
		if ( '' === $url ) {
			return $this->err( $this->msg( $r ) );
		}
		return array(
			'redirect' => $url,
			'data'     => array( 'mollie_id' => (string) ( $r['id'] ?? '' ) ),
		);
	}

	protected function read( $id, $ref ) {
		$r = $this->api( '/v2/payments/' . rawurlencode( (string) $id ), null, 'GET' );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( ( $r['metadata']['ref'] ?? '' ) !== $ref ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		$st = (string) ( $r['status'] ?? '' );
		if ( 'paid' === $st ) {
			return $this->ok(
				array(
					'bank_ref' => (string) $r['id'],
					'amount'   => isset( $r['amount']['value'] ) ? (float) $r['amount']['value'] : null,
				)
			);
		}
		if ( in_array( $st, array( 'open', 'pending', 'authorized' ), true ) ) {
			return $this->pending( __( 'Ödeme sağlayıcıda beklemede.', 'flx-pos' ), array( 'bank_ref' => (string) $r['id'] ) );
		}
		/* translators: %s: Mollie ödeme durumu */
		return $this->fail( sprintf( __( 'Ödeme tamamlanmadı (%s).', 'flx-pos' ), $st ) );
	}

	public function finish( array $txn, array $req ) {
		$id = (string) ( $txn['data']['mollie_id'] ?? '' );
		if ( '' === $id ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		$r = $this->read( $id, $txn['ref'] );
		return is_wp_error( $r ) ? $this->pending( $r->get_error_message() ) : $r;
	}

	public function notify( array $req ) {
		$id = $this->in( $req, 'id' );
		if ( ! preg_match( '/^tr_[A-Za-z0-9]+$/', $id ) ) {
			return $this->err( 'bad id' );
		}
		$r = $this->api( '/v2/payments/' . rawurlencode( $id ), null, 'GET' );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$ref = (string) ( $r['metadata']['ref'] ?? '' );
		return array(
			'ref'    => $ref,
			'result' => $this->read( $id, $ref ),
			'reply'  => 'OK',
		);
	}

	public function status( array $txn ) {
		$id = (string) ( $txn['data']['mollie_id'] ?? $txn['bank_ref'] );
		return $this->read( $id, $txn['ref'] );
	}

	public function refund( array $txn, $amount ) {
		$r = $this->api(
			'/v2/payments/' . rawurlencode( (string) $txn['bank_ref'] ) . '/refunds',
			array(
				'amount' => array(
					'currency' => strtoupper( $txn['currency'] ),
					'value'    => flxpos_dec( $amount ),
				),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$ok = isset( $r['id'] ) && 0 === strpos( (string) $r['id'], 're_' );
		return array(
			'ok'       => $ok,
			'message'  => $ok ? '' : $this->msg( $r ),
			'bank_ref' => (string) ( $r['id'] ?? '' ),
		);
	}

	public function test_connection() {
		$r = $this->api( '/v2/methods', null, 'GET' );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return isset( $r['_embedded'] ) ? true : $this->err( $this->msg( $r ) );
	}
}
