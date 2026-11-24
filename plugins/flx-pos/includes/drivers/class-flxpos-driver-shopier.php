<?php
/**
 * Shopier.
 *
 * Hosted: imzalı form Shopier ödeme sayfasına POST edilir. İmza: base64(HMAC-SHA256(random_nr
 * platform_order_id total_order_value currency, API gizli anahtarı)). Shopier sonucu, Shopier panelindeki
 * geri dönüş adresine POST eder: base64(HMAC-SHA256(random_nr platform_order_id, gizli anahtar)).
 * Shopier'da durum sorgusu ve iade API'si yok; iade Shopier panelinden yapılır.
 *
 * Protokol başvurusu: Shopier API (api_pay4) belgeleri.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Shopier extends FLXPOS_Driver {

	protected function sig( $s ) {
		return base64_encode( hash_hmac( 'sha256', $s, $this->c( 'api_secret' ), true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	protected function cur( $c ) {
		$m = array(
			'TRY' => '0',
			'USD' => '1',
			'EUR' => '2',
		);
		return $m[ strtoupper( $c ) ] ?? '0';
	}

	public function start( FLXPOS_Payment $p ) {
		$c     = $p->customer;
		$rnd   = (string) wp_rand( 100000, 999999 );
		$total = flxpos_dec( $p->amount );
		$cur   = $this->cur( $p->currency );
		$city  = '' !== (string) $c['city'] ? (string) $c['city'] : 'Istanbul';
		$addr  = '' !== (string) $c['address'] ? (string) $c['address'] : '-';
		$f     = array(
			'API_key'           => $this->c( 'api_key' ),
			'website_index'     => $this->c( 'website_index', '1' ),
			'platform_order_id' => $p->ref,
			'product_name'      => mb_substr( '' !== $p->description ? $p->description : $p->ref, 0, 100 ),
			'product_type'      => '1',
			'buyer_name'        => '' !== (string) $c['first'] ? (string) $c['first'] : '-',
			'buyer_surname'     => '' !== (string) $c['last'] ? (string) $c['last'] : '-',
			'buyer_email'       => (string) $c['email'],
			'buyer_account_age' => '0',
			'buyer_id_nr'       => '' !== (string) $c['id'] ? (string) $c['id'] : '0',
			'buyer_phone'       => (string) $c['phone'],
			'billing_address'   => $addr,
			'billing_city'      => $city,
			'billing_country'   => 'TR',
			'billing_postcode'  => (string) $c['zip'],
			'shipping_address'  => $addr,
			'shipping_city'     => $city,
			'shipping_country'  => 'TR',
			'shipping_postcode' => (string) $c['zip'],
			'total_order_value' => $total,
			'currency'          => $cur,
			'platform'          => '0',
			'is_in_frame'       => '0',
			'current_language'  => 'tr' === $p->lang ? '0' : '1',
			'modul_version'     => 'flx-pos-' . FLXPOS_VERSION,
			'random_nr'         => $rnd,
		);
		$f['signature'] = $this->sig( $rnd . $p->ref . $total . $cur );
		return array(
			'form' => array(
				'url'    => $this->url( 'gate' ),
				'fields' => $f,
			),
		);
	}

	protected function check( array $d, $ref ) {
		if ( ! hash_equals( $this->sig( ( $d['random_nr'] ?? '' ) . ( $d['platform_order_id'] ?? '' ) ), (string) ( $d['signature'] ?? '' ) ) ) {
			return $this->fail( __( 'Banka dönüşünün imzası tutmadı.', 'flx-pos' ), array( 'data' => array( 'hash' => 'mismatch' ) ) );
		}
		if ( '' !== $ref && ( $d['platform_order_id'] ?? '' ) !== $ref ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		if ( 'success' !== strtolower( (string) ( $d['status'] ?? '' ) ) ) {
			return $this->fail( $this->reason( $d['error_message'] ?? '' ) );
		}
		return $this->ok(
			array(
				'bank_ref'    => (string) ( $d['payment_id'] ?? '' ),
				'installment' => isset( $d['installment'] ) ? max( 1, (int) $d['installment'] ) : null,
			)
		);
	}

	public function finish( array $txn, array $req ) {
		$d = $this->all( $req );
		if ( empty( $d['signature'] ) ) {
			return $this->pending( __( 'Sonuç Shopier bildirimiyle gelecek.', 'flx-pos' ) );
		}
		return $this->check( $d, $txn['ref'] );
	}

	public function notify( array $req ) {
		$d = $this->all( $req );
		return array(
			'ref'    => (string) ( $d['platform_order_id'] ?? '' ),
			'result' => $this->check( $d, '' ),
			'reply'  => 'success',
		);
	}
}
