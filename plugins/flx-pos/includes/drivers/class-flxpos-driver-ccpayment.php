<?php
/**
 * "ccpayment" altyapısı: Sipay, QNBpay, Vepara, PayBull, Halk Öde ve aynı altyapıyı kullanan kuruluşlar.
 *
 * 3D: kart bilgisi ve imza (hash_key) ödeyenin tarayıcısından {api}/api/paySmart3D adresine POST edilir;
 * "payment_completed_by=merchant" olduğundan dönüşte ödeme sunucudan /payment/complete ile tamamlanır.
 * hash_key = iv:salt:AES-256-CBC(metin; anahtar = SHA-256(SHA-1(app_secret) + salt)[0..32]); dönüşteki
 * hash_key aynı yolla açılır ve sipariş no ile tutar eşlenir. Hosted: /purchase/link ile ödeme sayfası.
 *
 * Protokol başvurusu: Sipay ccpayment API kılavuzu; açık kaynak mews/pos ve CP.VPOS (MIT).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Ccpayment extends FLXPOS_Driver {

	protected function base() {
		return rtrim( $this->url( 'api' ), '/' );
	}

	/** hash_key üret. */
	public static function seal( $text, $secret ) {
		$iv   = substr( sha1( (string) wp_rand() . microtime() ), 0, 16 );
		$salt = substr( sha1( (string) wp_rand() . microtime() ), 0, 4 );
		$key  = substr( hash( 'sha256', sha1( $secret ) . $salt ), 0, 32 );
		$enc  = openssl_encrypt( $text, 'aes-256-cbc', $key, 0, $iv );
		return str_replace( '/', '__', $iv . ':' . $salt . ':' . $enc );
	}

	/** hash_key aç: değer listesi ya da boş dizi. */
	public static function open( $hash, $secret ) {
		$parts = explode( ':', str_replace( '__', '/', (string) $hash ) );
		if ( 3 !== count( $parts ) ) {
			return array();
		}
		list( $iv, $salt, $enc ) = $parts;
		$key = substr( hash( 'sha256', sha1( $secret ) . $salt ), 0, 32 );
		$out = openssl_decrypt( $enc, 'aes-256-cbc', $key, 0, $iv );
		return false === $out ? array() : explode( '|', $out );
	}

	protected function token() {
		$key = 'flxpos_tok_' . md5( $this->base() . $this->c( 'app_id' ) );
		$t   = get_transient( $key );
		if ( $t ) {
			return $t;
		}
		$r = $this->post_json(
			$this->base() . '/api/token',
			array(
				'app_id'     => $this->c( 'app_id' ),
				'app_secret' => $this->c( 'app_secret' ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( '100' !== (string) ( $r['status_code'] ?? '' ) || empty( $r['data']['token'] ) ) {
			return $this->err( $this->msg( $r ) );
		}
		set_transient( $key, (string) $r['data']['token'], 90 * MINUTE_IN_SECONDS );
		return (string) $r['data']['token'];
	}

	protected function api( $path, array $body ) {
		$t = $this->token();
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		return $this->post_json( $this->base() . $path, $body, array( 'Authorization' => 'Bearer ' . $t ) );
	}

	protected function msg( $r ) {
		return $this->reason( $r['status_description'] ?? ( $r['error'] ?? '' ), $r['status_code'] ?? ( $r['error_code'] ?? '' ) );
	}

	protected function items( FLXPOS_Payment $p ) {
		$out = array();
		foreach ( $p->lines() as $l ) {
			$out[] = array(
				'name'        => flxpos_ascii( $l['name'], 100 ),
				'price'       => flxpos_dec( $l['price'] ),
				'quantity'    => (int) $l['qty'],
				'description' => flxpos_ascii( $l['name'], 100 ),
			);
		}
		return $out;
	}

	protected function names( FLXPOS_Payment $p ) {
		return array(
			'name'    => '' !== (string) $p->customer['first'] ? flxpos_ascii( $p->customer['first'], 60 ) : '-',
			'surname' => '' !== (string) $p->customer['last'] ? flxpos_ascii( $p->customer['last'], 60 ) : '-',
		);
	}

	public function start( FLXPOS_Payment $p ) {
		$total = flxpos_dec( $p->amount );
		$inst  = (string) max( 1, (int) $p->installment );
		$cur   = strtoupper( $p->currency );
		$mk    = $this->c( 'merchant_key' );
		if ( 'hosted' === $this->mode() ) {
			$r = $this->api(
				'/purchase/link',
				array_merge(
					$this->names( $p ),
					array(
						'merchant_key'        => $mk,
						'invoice'             => wp_json_encode(
							array(
								'invoice_id'          => $p->ref,
								'invoice_description' => $p->description,
								'total'               => (float) $total,
								'return_url'          => $p->return_url,
								'cancel_url'          => $p->fail_url ? $p->fail_url : $p->return_url,
								'items'               => $this->items( $p ),
							)
						),
						'currency_code'       => $cur,
					)
				)
			);
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			if ( empty( $r['link'] ) ) {
				return $this->err( $this->msg( $r ) );
			}
			return array( 'redirect' => (string) $r['link'] );
		}
		$f = array_merge(
			$this->names( $p ),
			array(
				'cc_holder_name'       => flxpos_ascii( $p->holder(), 60 ),
				'cc_no'                => $p->pan(),
				'expiry_month'         => $p->mm(),
				'expiry_year'          => (string) ( 2000 + (int) $p->yy() ),
				'cvv'                  => $p->cvv(),
				'currency_code'        => $cur,
				'installments_number'  => $inst,
				'invoice_id'           => $p->ref,
				'invoice_description'  => $p->description,
				'total'                => $total,
				'merchant_key'         => $mk,
				'transaction_type'     => 'Auth',
				'hash_key'             => self::seal( $total . '|' . $inst . '|' . $cur . '|' . $mk . '|' . $p->ref, $this->c( 'app_secret' ) ),
			)
		);
		if ( 'regular' === $this->mode() ) {
			$f['items'] = $this->items( $p );
			$r          = $this->api( '/api/paySmart2D', $f );
			if ( is_wp_error( $r ) ) {
				return array( 'result' => $this->pending( $r->get_error_message() ) );
			}
			if ( '100' === (string) ( $r['status_code'] ?? '' ) ) {
				return array(
					'result' => $this->ok(
						array(
							'bank_ref'  => (string) ( $r['data']['order_no'] ?? ( $r['data']['order_id'] ?? '' ) ),
							'auth_code' => (string) ( $r['data']['auth_code'] ?? '' ),
							'amount'    => $p->amount,
							'card'      => $this->mask( $p->pan() ),
						)
					),
				);
			}
			return array( 'result' => $this->fail( $this->msg( $r ), array( 'card' => $this->mask( $p->pan() ) ) ) );
		}
		$f['items']                = wp_json_encode( $this->items( $p ) );
		$f['ip']                   = $p->ip;
		$f['response_method']      = 'POST';
		$f['payment_completed_by'] = 'merchant';
		$f['return_url']           = $p->return_url;
		$f['cancel_url']           = $p->fail_url ? $p->fail_url : $p->return_url;
		return array(
			'form' => array(
				'url'    => $this->base() . '/api/paySmart3D',
				'fields' => $f,
			),
		);
	}

	public function finish( array $txn, array $req ) {
		$d    = $this->all( $req );
		$vals = self::open( $d['hash_key'] ?? '', $this->c( 'app_secret' ) );
		if ( ! in_array( $txn['ref'], $vals, true ) || ( $d['invoice_id'] ?? '' ) !== $txn['ref'] ) {
			if ( 'hosted' === $this->mode() ) {
				return $this->status( $txn );
			}
			return $this->fail( '' !== ( $d['error'] ?? '' ) ? $this->reason( $d['error'] ) : __( 'Banka dönüşünün imzası tutmadı.', 'flx-pos' ), array( 'data' => array( 'hash' => 'mismatch' ) ) );
		}
		if ( 'hosted' === $this->mode() ) {
			return $this->status( $txn );
		}
		if ( '1' !== (string) ( $d['md_status'] ?? '' ) ) {
			return $this->fail( $this->reason( $d['error'] ?? ( $d['status_description'] ?? '' ), 'md' . ( $d['md_status'] ?? '' ) ) );
		}
		$mk  = $this->c( 'merchant_key' );
		$oid = (string) ( $d['order_id'] ?? '' );
		$r   = $this->api(
			'/payment/complete',
			array(
				'merchant_key' => $mk,
				'invoice_id'   => $txn['ref'],
				'order_id'     => $oid,
				'status'       => 'complete',
				'hash_key'     => self::seal( $mk . '|' . $txn['ref'] . '|' . $oid . '|complete', $this->c( 'app_secret' ) ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $this->pending( $r->get_error_message() );
		}
		if ( '100' === (string) ( $r['status_code'] ?? '' ) ) {
			return $this->ok(
				array(
					'bank_ref'  => '' !== $oid ? $oid : (string) ( $r['data']['order_id'] ?? '' ),
					'auth_code' => (string) ( $r['data']['auth_code'] ?? ( $d['auth_code'] ?? '' ) ),
					'amount'    => (float) $txn['amount'],
				)
			);
		}
		return $this->fail( $this->msg( $r ) );
	}

	public function status( array $txn ) {
		$mk = $this->c( 'merchant_key' );
		$r  = $this->api(
			'/api/checkstatus',
			array(
				'merchant_key'           => $mk,
				'invoice_id'             => $txn['ref'],
				'include_pending_status' => 'true',
				'hash_key'               => self::seal( $txn['ref'] . '|' . $mk, $this->c( 'app_secret' ) ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$st = strtolower( (string) ( $r['transaction_status'] ?? ( $r['status'] ?? '' ) ) );
		if ( '100' === (string) ( $r['status_code'] ?? '' ) && in_array( $st, array( 'completed', 'complete', 'success' ), true ) ) {
			return $this->ok(
				array(
					'bank_ref' => (string) ( $r['order_no'] ?? ( $r['order_id'] ?? '' ) ),
					'amount'   => isset( $r['total'] ) ? flxpos_parse_amount( $r['total'] ) : null,
				)
			);
		}
		if ( 'pending' === $st ) {
			return $this->pending( __( 'Ödeme sağlayıcıda beklemede.', 'flx-pos' ) );
		}
		return $this->fail( $this->msg( $r ) );
	}

	public function refund( array $txn, $amount ) {
		$total = flxpos_dec( $amount );
		$mk    = $this->c( 'merchant_key' );
		$r     = $this->api(
			'/api/refund',
			array(
				'invoice_id'   => $txn['ref'],
				'amount'       => $total,
				'app_id'       => $this->c( 'app_id' ),
				'app_secret'   => $this->c( 'app_secret' ),
				'merchant_key' => $mk,
				'hash_key'     => self::seal( $total . '|' . $txn['ref'] . '|' . $mk, $this->c( 'app_secret' ) ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$ok = '100' === (string) ( $r['status_code'] ?? '' );
		return array(
			'ok'       => $ok,
			'message'  => $ok ? '' : $this->msg( $r ),
			'bank_ref' => '',
		);
	}

	/** İptal: gün içinde tam tutar iadesi iptal olarak işlenir. */
	public function cancel( array $txn ) {
		return $this->refund( $txn, $txn['amount'] );
	}

	public function installments( $bin, $amount, $currency ) {
		$r = $this->api(
			'/api/getpos',
			array(
				'credit_card'   => substr( preg_replace( '/\D/', '', (string) $bin ), 0, 6 ),
				'amount'        => flxpos_dec( $amount ),
				'currency_code' => strtoupper( $currency ),
				'merchant_key'  => $this->c( 'merchant_key' ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( '100' !== (string) ( $r['status_code'] ?? '' ) ) {
			return null;
		}
		$out = array();
		foreach ( (array) ( $r['data'] ?? array() ) as $i ) {
			$n = (int) ( $i['installments_number'] ?? 0 );
			if ( $n > 0 ) {
				$out[ $n ] = array(
					'total' => (float) ( $i['amount_to_be_paid'] ?? ( $i['payable_amount'] ?? $amount ) ),
					'rate'  => (float) ( $i['user_commission_percentage'] ?? 0 ),
				);
			}
		}
		return $out;
	}

	public function test_connection() {
		delete_transient( 'flxpos_tok_' . md5( $this->base() . $this->c( 'app_id' ) ) );
		$t = $this->token();
		return is_wp_error( $t ) ? $t : true;
	}
}
