<?php
/**
 * EsnekPOS (Hoppa).
 *
 * Hosted: CommonPaymentDealer ortak ödeme sayfasını açar (URL_3DS); 3D doğrulamayı EsnekPOS sayfası yapar.
 * Dönüş verisine güvenilmez: ProcessQuery ile sunucudan sorulur; STATUS=SUCCESS, RETURN_CODE=0, referans
 * ve tutar tutmalı, işlem listesi varsa başarılı (STATUS_ID=3) bir satır olmalı.
 *
 * Protokol başvurusu: EsnekPOS geliştirici belgeleri. Flux Donate'teki EsnekPOS bağdaştırıcısından taşındı.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Esnekpos extends FLXPOS_Driver {

	/** EsnekPOS tutar biçimi: "150", "150.50". */
	public static function amount( $x ) {
		$x = round( (float) $x, 2 );
		return abs( $x - round( $x ) ) < 0.005 ? (string) (int) round( $x ) : number_format( $x, 2, '.', '' );
	}

	protected function base() {
		return rtrim( $this->url( 'api' ), '/' );
	}

	protected function approved( $r ) {
		return is_array( $r ) && 'SUCCESS' === strtoupper( (string) ( $r['STATUS'] ?? '' ) ) && '0' === trim( (string) ( $r['RETURN_CODE'] ?? '' ) );
	}

	protected function msg( $r ) {
		$m = '';
		foreach ( array( 'tr' === flxpos_locale() ? 'RETURN_MESSAGE_TR' : 'RETURN_MESSAGE', 'RETURN_MESSAGE', 'RETURN_MESSAGE_TR' ) as $k ) {
			if ( '' === $m && ! empty( $r[ $k ] ) && is_scalar( $r[ $k ] ) ) {
				$m = (string) $r[ $k ];
			}
		}
		return $this->reason( $m, $r['RETURN_CODE'] ?? '' );
	}

	protected function products( FLXPOS_Payment $p ) {
		$out = array();
		foreach ( $p->lines() as $l ) {
			$out[] = array(
				'PRODUCT_ID'          => (string) $l['id'],
				'PRODUCT_NAME'        => mb_substr( (string) $l['name'], 0, 100 ),
				'PRODUCT_CATEGORY'    => mb_substr( (string) ( $l['category'] ?? __( 'Genel', 'flx-pos' ) ), 0, 50 ),
				'PRODUCT_DESCRIPTION' => mb_substr( (string) $l['name'], 0, 100 ),
				'PRODUCT_AMOUNT'      => self::amount( (float) $l['price'] * (int) $l['qty'] ),
			);
		}
		return $out;
	}

	public function start( FLXPOS_Payment $p ) {
		$c     = $p->customer;
		$first = '' !== (string) $c['first'] ? (string) $c['first'] : ( '' !== (string) $c['name'] ? (string) $c['name'] : '-' );
		$city  = '' !== (string) $c['city'] ? (string) $c['city'] : 'Istanbul';
		$r     = $this->post_json(
			$this->base() . '/api/pay/CommonPaymentDealer',
			array(
				'Config'   => array(
					'MERCHANT'         => $this->c( 'merchant' ),
					'MERCHANT_KEY'     => $this->c( 'merchant_key' ),
					'BACK_URL'         => $p->return_url,
					'PRICES_CURRENCY'  => strtoupper( $p->currency ),
					'ORDER_REF_NUMBER' => $p->ref,
					'ORDER_AMOUNT'     => self::amount( $p->amount ),
					'LOCALE'           => 'tr' === $p->lang ? 'tr' : 'en',
				),
				'Customer' => array(
					'FIRST_NAME' => $first,
					'LAST_NAME'  => '' !== (string) $c['last'] ? (string) $c['last'] : '-',
					'MAIL'       => (string) $c['email'],
					'PHONE'      => (string) $c['phone'],
					'CITY'       => $city,
					'STATE'      => $city,
					'ADDRESS'    => '' !== (string) $c['address'] ? (string) $c['address'] : '-',
				),
				'Product'  => $this->products( $p ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$url = (string) ( $r['URL_3DS'] ?? '' );
		if ( ! $this->approved( $r ) || ! preg_match( '#^https://#', $url ) ) {
			return $this->err( $this->msg( $r ) );
		}
		return array( 'redirect' => $url );
	}

	public function finish( array $txn, array $req ) {
		$st = $this->status( $txn );
		if ( is_wp_error( $st ) ) {
			return $this->pending( $st->get_error_message() );
		}
		return $st;
	}

	public function status( array $txn ) {
		$q = $this->post_json(
			$this->base() . '/api/services/ProcessQuery',
			array(
				'MERCHANT'         => $this->c( 'merchant' ),
				'MERCHANT_KEY'     => $this->c( 'merchant_key' ),
				'ORDER_REF_NUMBER' => (string) $txn['ref'],
			)
		);
		if ( is_wp_error( $q ) ) {
			return $q;
		}
		if ( ! $this->approved( $q ) ) {
			return $this->fail( $this->msg( $q ) );
		}
		$qref = (string) ( $q['ORDER_REF_NUMBER'] ?? ( $q['ORDER_REF_NO'] ?? '' ) );
		if ( '' !== $qref && $qref !== (string) $txn['ref'] ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		if ( ! empty( $q['TRANSACTIONS'] ) && is_array( $q['TRANSACTIONS'] ) ) {
			$ok = false;
			foreach ( $q['TRANSACTIONS'] as $t ) {
				if ( is_array( $t ) && 3 === (int) ( $t['STATUS_ID'] ?? 0 ) ) {
					$ok = true;
				}
			}
			if ( ! $ok ) {
				return $this->fail( __( 'Bankada başarılı bir satış bulunamadı.', 'flx-pos' ) );
			}
		}
		return $this->ok(
			array(
				'bank_ref' => (string) ( $q['PAYMENT_ID'] ?? ( $q['ORDER_REF_NUMBER'] ?? $txn['ref'] ) ),
				'amount'   => isset( $q['AMOUNT'] ) ? flxpos_parse_amount( $q['AMOUNT'] ) : null,
			)
		);
	}

	public function test_connection() {
		$q = $this->post_json(
			$this->base() . '/api/services/ProcessQuery',
			array(
				'MERCHANT'         => $this->c( 'merchant' ),
				'MERCHANT_KEY'     => $this->c( 'merchant_key' ),
				'ORDER_REF_NUMBER' => 'FXTEST' . time(),
			)
		);
		if ( is_wp_error( $q ) ) {
			return $q;
		}
		if ( preg_match( '/merchant|üye|yetki|key|anahtar/i', $this->msg( $q ) ) ) {
			return $this->err( $this->msg( $q ) );
		}
		return true;
	}
}
