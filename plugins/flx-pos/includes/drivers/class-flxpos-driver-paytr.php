<?php
/**
 * PayTR.
 *
 * Modeller: iframe (PayTR iFrame API: get-token → /odeme/guvenli/{token}) ve 3d_pay (Direkt API: kart
 * bilgisi ödeyenin tarayıcısından /odeme adresine POST edilir). Kesin sonuç PayTR'ın sunucu bildiriminden
 * (Bildirim URL'si, PayTR panelinde tanımlanır) gelir: hash = base64(HMAC-SHA256(merchant_oid salt status
 * total_amount, merchant_key)); yanıt "OK". Ödeyen siteye döndüğünde durum sorgusu (durum-sorgu) ile
 * beklemeden sonuçlanır.
 *
 * Protokol başvurusu: PayTR iFrame / Direkt API belgeleri; açık kaynak mews/pos (MIT).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Paytr extends FLXPOS_Driver {

	protected function token( $s ) {
		return base64_encode( hash_hmac( 'sha256', $s . $this->c( 'merchant_salt' ), $this->c( 'merchant_key' ), true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	protected function cur( $c ) {
		$c = strtoupper( (string) $c );
		return 'TRY' === $c ? 'TL' : $c;
	}

	protected function basket( FLXPOS_Payment $p ) {
		$out = array();
		foreach ( $p->lines() as $l ) {
			$out[] = array( mb_substr( (string) $l['name'], 0, 100 ), flxpos_dec( $l['price'] ), (int) $l['qty'] );
		}
		return wp_json_encode( $out );
	}

	public function start( FLXPOS_Payment $p ) {
		$test  = $this->test ? '1' : '0';
		$email = is_email( $p->customer['email'] ) ? (string) $p->customer['email'] : 'odeme@example.com';
		$cur   = $this->cur( $p->currency );
		$name  = '' !== (string) $p->customer['name'] ? (string) $p->customer['name'] : $p->holder();
		$addr  = '' !== (string) $p->customer['address'] ? (string) $p->customer['address'] : '-';
		$phone = '' !== (string) $p->customer['phone'] ? (string) $p->customer['phone'] : '0000000000';
		$fail  = $p->fail_url ? $p->fail_url : $p->return_url;
		if ( 'iframe' === $this->mode() ) {
			$amount = flxpos_minor( $p->amount );
			$basket = base64_encode( $this->basket( $p ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			$no     = $p->installment > 1 ? '0' : ( $this->c( 'allow_installment' ) ? '0' : '1' );
			$max    = $p->installment > 1 ? (string) (int) $p->installment : '0';
			$f      = array(
				'merchant_id'       => $this->c( 'merchant_id' ),
				'user_ip'           => $p->ip,
				'merchant_oid'      => $p->ref,
				'email'             => $email,
				'payment_amount'    => $amount,
				'user_basket'       => $basket,
				'no_installment'    => $no,
				'max_installment'   => $max,
				'currency'          => $cur,
				'test_mode'         => $test,
				'user_name'         => flxpos_ascii( $name, 60 ),
				'user_address'      => mb_substr( $addr, 0, 400 ),
				'user_phone'        => mb_substr( $phone, 0, 20 ),
				'merchant_ok_url'   => $p->return_url,
				'merchant_fail_url' => $fail,
				'timeout_limit'     => '30',
				'debug_on'          => '0',
				'lang'              => 'tr' === $p->lang ? 'tr' : 'en',
			);
			$f['paytr_token'] = $this->token( $f['merchant_id'] . $f['user_ip'] . $f['merchant_oid'] . $f['email'] . $f['payment_amount'] . $f['user_basket'] . $f['no_installment'] . $f['max_installment'] . $f['currency'] . $f['test_mode'] );
			$r                = flxpos_json( $this->post_form( $this->url( 'token' ), $f ), $this->name() );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			if ( 'success' !== ( $r['status'] ?? '' ) || empty( $r['token'] ) ) {
				return $this->err( $this->reason( $r['reason'] ?? '' ) );
			}
			return array( 'iframe' => array( 'url' => rtrim( $this->url( 'iframe' ), '/' ) . '/' . rawurlencode( (string) $r['token'] ) ) );
		}
		$f = array(
			'merchant_id'       => $this->c( 'merchant_id' ),
			'user_ip'           => $p->ip,
			'merchant_oid'      => $p->ref,
			'email'             => $email,
			'payment_type'      => 'card',
			'payment_amount'    => flxpos_dec( $p->amount ),
			'installment_count' => $p->installment > 1 ? (string) (int) $p->installment : '0',
			'currency'          => $cur,
			'test_mode'         => $test,
			'non_3d'            => '0',
			'merchant_ok_url'   => $p->return_url,
			'merchant_fail_url' => $fail,
			'user_name'         => flxpos_ascii( $name, 60 ),
			'user_address'      => mb_substr( $addr, 0, 400 ),
			'user_phone'        => mb_substr( $phone, 0, 20 ),
			'user_basket'       => $this->basket( $p ),
			'debug_on'          => '0',
			'client_lang'       => 'tr' === $p->lang ? 'tr' : 'en',
			'cc_owner'          => flxpos_ascii( $p->holder(), 50 ),
			'card_number'       => $p->pan(),
			'expiry_month'      => $p->mm(),
			'expiry_year'       => $p->yy(),
			'cvv'               => $p->cvv(),
		);
		$f['paytr_token'] = $this->token( $f['merchant_id'] . $f['user_ip'] . $f['merchant_oid'] . $f['email'] . $f['payment_amount'] . $f['payment_type'] . $f['installment_count'] . $f['currency'] . $f['test_mode'] . $f['non_3d'] );
		return array(
			'form' => array(
				'url'    => $this->url( 'direct' ),
				'fields' => $f,
			),
		);
	}

	/** Ödeyen döndü: sonucu bildirimden önce sorguyla al; sorgu olmazsa beklemede. */
	public function finish( array $txn, array $req ) {
		$st = $this->status( $txn );
		if ( is_wp_error( $st ) ) {
			return $this->pending( __( 'Sonuç PayTR bildirimiyle gelecek.', 'flx-pos' ) );
		}
		if ( 'failed' === $st['status'] && '' === $this->in( $req, 'fail' ) && '' === $this->in( $req, 'failed_reason_msg' ) ) {
			return $this->pending( __( 'Sonuç PayTR bildirimiyle gelecek.', 'flx-pos' ) );
		}
		return $st;
	}

	public function notify( array $req ) {
		$d    = $this->all( $req );
		$want = base64_encode( hash_hmac( 'sha256', ( $d['merchant_oid'] ?? '' ) . $this->c( 'merchant_salt' ) . ( $d['status'] ?? '' ) . ( $d['total_amount'] ?? '' ), $this->c( 'merchant_key' ), true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		if ( ! hash_equals( $want, (string) ( $d['hash'] ?? '' ) ) ) {
			return $this->err( 'PAYTR notification failed: bad hash' );
		}
		if ( 'success' === ( $d['status'] ?? '' ) ) {
			$res = $this->ok(
				array(
					'bank_ref'    => (string) $d['merchant_oid'],
					'amount'      => isset( $d['total_amount'] ) ? flxpos_parse_amount( $d['total_amount'], true ) : null,
					'installment' => isset( $d['installment_count'] ) ? max( 1, (int) $d['installment_count'] ) : null,
					'data'        => array( 'payment_amount' => $d['payment_amount'] ?? '' ),
				)
			);
		} else {
			$res = $this->fail( $this->reason( $d['failed_reason_msg'] ?? '', $d['failed_reason_code'] ?? '' ) );
		}
		return array(
			'ref'    => (string) $d['merchant_oid'],
			'result' => $res,
			'reply'  => 'OK',
		);
	}

	public function status( array $txn ) {
		$id = $this->c( 'merchant_id' );
		$r  = flxpos_json(
			$this->post_form(
				$this->url( 'status' ),
				array(
					'merchant_id'  => $id,
					'merchant_oid' => $txn['ref'],
					'paytr_token'  => $this->token( $id . $txn['ref'] ),
				)
			),
			$this->name()
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( 'success' === ( $r['status'] ?? '' ) ) {
			return $this->ok(
				array(
					'bank_ref' => $txn['ref'],
					'amount'   => isset( $r['payment_total'] ) ? flxpos_parse_amount( $r['payment_total'] ) : ( isset( $r['payment_amount'] ) ? flxpos_parse_amount( $r['payment_amount'] ) : null ),
				)
			);
		}
		return $this->fail( $this->reason( $r['err_msg'] ?? ( $r['reason'] ?? '' ), $r['err_no'] ?? '' ) );
	}

	public function refund( array $txn, $amount ) {
		$id  = $this->c( 'merchant_id' );
		$amt = flxpos_dec( $amount );
		$r   = flxpos_json(
			$this->post_form(
				$this->url( 'refund' ),
				array(
					'merchant_id'   => $id,
					'merchant_oid'  => $txn['ref'],
					'return_amount' => $amt,
					'paytr_token'   => $this->token( $id . $txn['ref'] . $amt ),
				)
			),
			$this->name()
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$ok = 'success' === ( $r['status'] ?? '' );
		return array(
			'ok'       => $ok,
			'message'  => $ok ? '' : $this->reason( $r['err_msg'] ?? '', $r['err_no'] ?? '' ),
			'bank_ref' => '',
		);
	}

	public function cancel( array $txn ) {
		return $this->refund( $txn, $txn['amount'] );
	}

	public function test_connection() {
		$st = $this->status( array( 'ref' => 'FXTEST' . time() ) );
		if ( is_wp_error( $st ) ) {
			return $st;
		}
		if ( preg_match( '/token|merchant|yetki|hash/i', (string) $st['message'] ) ) {
			return $this->err( $st['message'] );
		}
		return true;
	}
}
