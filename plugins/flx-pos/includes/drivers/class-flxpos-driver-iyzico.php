<?php
/**
 * iyzico.
 *
 * Modeller: 3d (kart sitede; /payment/3dsecure/initialize → bankanın 3D sayfası → /payment/3dsecure/auth),
 * hosted (iyzico ortak ödeme formu; iyzico ile Öde cüzdanı da bu sayfadadır), regular (/payment/auth).
 * İstekler IYZWSv2 ile imzalanır: HMAC-SHA256(randomKey + URI yolu + JSON gövde, gizli anahtar).
 * Dönüşte sonuç her zaman sunucudan alınır; tutar, sepet ve sipariş no eşlenir.
 *
 * Protokol başvurusu: iyzico API belgeleri ve resmi iyzipay-php SDK'sı (MIT); açık kaynak mews/pos (MIT).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Iyzico extends FLXPOS_Driver {

	public static function price( $v ) {
		$s = rtrim( number_format( round( (float) $v, 2 ), 2, '.', '' ), '0' );
		return '.' === substr( $s, -1 ) ? $s . '0' : $s;
	}

	protected static function strip( array $a ) {
		$out = array();
		foreach ( $a as $k => $v ) {
			if ( null === $v || '' === $v || ( is_array( $v ) && ! $v ) ) {
				continue;
			}
			$out[ $k ] = is_array( $v ) ? self::strip( $v ) : $v;
		}
		return $out;
	}

	protected function api( $path, array $body, $method = 'POST' ) {
		$json = wp_json_encode( self::strip( $body ) );
		$rnd  = uniqid( '', true );
		$sig  = bin2hex( hash_hmac( 'sha256', $rnd . $path . $json, $this->c( 'secret_key' ), true ) );
		$auth = 'IYZWSv2 ' . base64_encode( 'apiKey:' . $this->c( 'api_key' ) . '&randomKey:' . $rnd . '&signature:' . $sig ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$r    = flxpos_http(
			rtrim( $this->url( 'api' ), '/' ) . $path,
			$json,
			array(
				'method'  => $method,
				'headers' => array(
					'Content-Type'          => 'application/json',
					'Accept'                => 'application/json',
					'Authorization'         => $auth,
					'x-iyzi-rnd'            => $rnd,
					'x-iyzi-client-version' => 'flx-pos-' . FLXPOS_VERSION,
				),
			)
		);
		return flxpos_json( $r, $this->name() );
	}

	protected function approved( $r ) {
		return is_array( $r ) && 'success' === ( $r['status'] ?? '' );
	}

	protected function msg( $r ) {
		return $this->reason( $r['errorMessage'] ?? '', $r['errorCode'] ?? '' );
	}

	protected function lang( FLXPOS_Payment $p ) {
		return 'tr' === $p->lang ? 'tr' : 'en';
	}

	protected function buyer( FLXPOS_Payment $p ) {
		$c     = $p->customer;
		$first = '' !== (string) $c['first'] ? (string) $c['first'] : ( '' !== (string) $c['name'] ? (string) $c['name'] : '-' );
		$last  = '' !== (string) $c['last'] ? (string) $c['last'] : $first;
		$email = is_email( $c['email'] ) ? (string) $c['email'] : 'odeme@' . preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$city  = '' !== (string) $c['city'] ? (string) $c['city'] : 'Istanbul';
		$cntry = '' !== (string) $c['country'] ? (string) $c['country'] : 'Turkey';
		$addr  = '' !== (string) $c['address'] ? (string) $c['address'] : '-';
		$buyer = array(
			'id'                  => '' !== (string) $c['id'] ? 'u' . $c['id'] : 'g' . substr( md5( strtolower( $email ) ), 0, 12 ),
			'name'                => mb_substr( $first, 0, 50 ),
			'surname'             => mb_substr( $last, 0, 50 ),
			'identityNumber'      => '11111111111',
			'email'               => $email,
			'gsmNumber'           => (string) $c['phone'],
			'registrationAddress' => $addr,
			'city'                => $city,
			'country'             => $cntry,
			'zipCode'             => (string) $c['zip'],
			'ip'                  => $p->ip,
		);
		$address = array(
			'contactName' => trim( $buyer['name'] . ' ' . $buyer['surname'] ),
			'city'        => $city,
			'country'     => $cntry,
			'address'     => $addr,
			'zipCode'     => (string) $c['zip'],
		);
		return array( $buyer, $address );
	}

	protected function basket( FLXPOS_Payment $p ) {
		$out   = array();
		$total = flxpos_minor( $p->amount );
		$sum   = 0;
		foreach ( $p->lines() as $i => $l ) {
			$m = (int) round( (float) $l['price'] * (int) $l['qty'] * 100 );
			if ( $m <= 0 || $sum + $m > $total ) {
				continue;
			}
			$sum  += $m;
			$out[] = array(
				'id'        => substr( (string) $l['id'], 0, 64 ),
				'name'      => mb_substr( (string) $l['name'], 0, 100 ),
				'category1' => mb_substr( (string) ( $l['category'] ?? __( 'Genel', 'flx-pos' ) ), 0, 100 ),
				'itemType'  => 'VIRTUAL',
				'price'     => self::price( $m / 100 ),
			);
		}
		if ( $sum < $total ) {
			$out[] = array(
				'id'        => 'rest',
				'name'      => __( 'Diğer', 'flx-pos' ),
				'category1' => __( 'Genel', 'flx-pos' ),
				'itemType'  => 'VIRTUAL',
				'price'     => self::price( ( $total - $sum ) / 100 ),
			);
		}
		return $out;
	}

	protected function body( FLXPOS_Payment $p ) {
		list( $buyer, $address ) = $this->buyer( $p );
		return array(
			'locale'          => $this->lang( $p ),
			'conversationId'  => $p->ref,
			'price'           => self::price( $p->amount ),
			'paidPrice'       => self::price( $p->amount ),
			'currency'        => strtoupper( $p->currency ),
			'basketId'        => $p->ref,
			'paymentChannel'  => 'WEB',
			'paymentGroup'    => 'PRODUCT',
			'buyer'           => $buyer,
			'billingAddress'  => $address,
			'shippingAddress' => $address,
			'basketItems'     => $this->basket( $p ),
		);
	}

	protected function card( FLXPOS_Payment $p ) {
		return array(
			'cardHolderName' => $p->holder(),
			'cardNumber'     => $p->pan(),
			'expireMonth'    => $p->mm(),
			'expireYear'     => (string) ( 2000 + (int) $p->yy() ),
			'cvc'            => $p->cvv(),
			'registerCard'   => 0,
		);
	}

	public function start( FLXPOS_Payment $p ) {
		$b = $this->body( $p );
		if ( 'hosted' === $this->mode() ) {
			$b['callbackUrl']         = $p->return_url;
			$b['enabledInstallments'] = array( 1, 2, 3, 6, 9, 12 );
			$r                        = $this->api( '/payment/iyzipos/checkoutform/initialize/auth/ecom', $b );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			if ( ! $this->approved( $r ) || empty( $r['paymentPageUrl'] ) ) {
				return $this->err( $this->msg( $r ) );
			}
			return array(
				'redirect' => (string) $r['paymentPageUrl'],
				'data'     => array( 'checkout_ref' => (string) ( $r['token'] ?? '' ) ),
			);
		}
		$b['installment'] = max( 1, (int) $p->installment );
		$b['paymentCard'] = $this->card( $p );
		if ( 'regular' === $this->mode() ) {
			$r = $this->api( '/payment/auth', $b );
			if ( is_wp_error( $r ) ) {
				return array( 'result' => $this->pending( $r->get_error_message() ) );
			}
			return array( 'result' => $this->paid( $r, $p->ref, $this->mask( $p->pan() ) ) );
		}
		$b['callbackUrl'] = $p->return_url;
		$r                = $this->api( '/payment/3dsecure/initialize', $b );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( ! $this->approved( $r ) || empty( $r['threeDSHtmlContent'] ) ) {
			return $this->err( $this->msg( $r ) );
		}
		return array( 'html' => (string) base64_decode( (string) $r['threeDSHtmlContent'] ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}

	/** Ödeme yanıtını sonuca çevir; sepet/sipariş no eşleşmesi şart. */
	protected function paid( $r, $ref, $card = '' ) {
		if ( ! $this->approved( $r ) ) {
			return $this->fail( $this->msg( $r ), array( 'card' => $card ) );
		}
		if ( ( $r['basketId'] ?? $ref ) !== $ref ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		if ( isset( $r['paymentStatus'] ) && 'SUCCESS' !== strtoupper( (string) $r['paymentStatus'] ) ) {
			return $this->fail( $this->reason( $r['errorMessage'] ?? '', $r['paymentStatus'] ) );
		}
		if ( isset( $r['fraudStatus'] ) && 1 !== (int) $r['fraudStatus'] ) {
			return $this->pending( __( 'iyzico ödemeyi incelemeye aldı.', 'flx-pos' ), array( 'bank_ref' => (string) ( $r['paymentId'] ?? '' ) ) );
		}
		$items = array();
		foreach ( (array) ( $r['itemTransactions'] ?? array() ) as $it ) {
			if ( ! empty( $it['paymentTransactionId'] ) ) {
				$items[] = array(
					'tx'   => (string) $it['paymentTransactionId'],
					'paid' => (float) ( $it['paidPrice'] ?? 0 ),
				);
			}
		}
		$last4 = (string) ( $r['lastFourDigits'] ?? '' );
		return $this->ok(
			array(
				'bank_ref'    => (string) ( $r['paymentId'] ?? '' ),
				'auth_code'   => (string) ( $r['authCode'] ?? '' ),
				'amount'      => isset( $r['paidPrice'] ) ? (float) $r['paidPrice'] : null,
				'installment' => isset( $r['installment'] ) ? (int) $r['installment'] : null,
				'card'        => '' !== $card ? $card : ( '' !== $last4 ? (string) ( $r['binNumber'] ?? '' ) . '******' . $last4 : '' ),
				'data'        => array( 'iyzi_items' => $items ),
			)
		);
	}

	public function finish( array $txn, array $req ) {
		if ( 'hosted' === $this->mode() ) {
			$token = $this->in( $req, 'token', (string) ( $txn['data']['checkout_ref'] ?? '' ) );
			if ( '' === $token || ( ! empty( $txn['data']['checkout_ref'] ) && $token !== $txn['data']['checkout_ref'] ) ) {
				return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
			}
			$r = $this->api(
				'/payment/iyzipos/checkoutform/auth/ecom/detail',
				array(
					'locale'         => 'tr',
					'conversationId' => $txn['ref'],
					'token'          => $token,
				)
			);
			if ( is_wp_error( $r ) ) {
				return $this->pending( $r->get_error_message() );
			}
			return $this->paid( $r, $txn['ref'] );
		}
		$d = $this->all( $req );
		if ( 'success' !== ( $d['status'] ?? '' ) || ! in_array( (string) ( $d['mdStatus'] ?? '' ), array( '1' ), true ) ) {
			return $this->fail( $this->reason( $d['errorMessage'] ?? '', 'md' . ( $d['mdStatus'] ?? '' ) ) );
		}
		if ( ( $d['conversationId'] ?? $txn['ref'] ) !== $txn['ref'] ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		if ( isset( $d['signature'] ) ) {
			$want = bin2hex( hash_hmac( 'sha256', implode( ':', array( $d['conversationData'] ?? '', $d['conversationId'] ?? '', $d['mdStatus'] ?? '', $d['paymentId'] ?? '', $d['status'] ?? '' ) ), $this->c( 'secret_key' ), true ) );
			if ( ! hash_equals( $want, (string) $d['signature'] ) ) {
				return $this->fail( __( 'Banka dönüşünün imzası tutmadı.', 'flx-pos' ), array( 'data' => array( 'hash' => 'mismatch' ) ) );
			}
		}
		$r = $this->api(
			'/payment/3dsecure/auth',
			array(
				'locale'           => 'tr',
				'conversationId'   => $txn['ref'],
				'paymentId'        => (string) ( $d['paymentId'] ?? '' ),
				'conversationData' => (string) ( $d['conversationData'] ?? '' ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $this->pending( $r->get_error_message() );
		}
		return $this->paid( $r, $txn['ref'] );
	}

	public function status( array $txn ) {
		$r = $this->api(
			'/payment/detail',
			array(
				'locale'                => 'tr',
				'conversationId'        => $txn['ref'],
				'paymentId'             => $txn['bank_ref'],
				'paymentConversationId' => $txn['ref'],
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return $this->paid( $r, $txn['ref'] );
	}

	public function refund( array $txn, $amount ) {
		$r = $this->api(
			'/v2/payment/refund',
			array(
				'locale'         => 'tr',
				'conversationId' => $txn['ref'],
				'paymentId'      => $txn['bank_ref'],
				'price'          => self::price( $amount ),
				'currency'       => strtoupper( $txn['currency'] ),
				'ip'             => flxpos_server_ip(),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array(
			'ok'       => $this->approved( $r ),
			'message'  => $this->approved( $r ) ? '' : $this->msg( $r ),
			'bank_ref' => (string) ( $r['paymentId'] ?? '' ),
		);
	}

	public function cancel( array $txn ) {
		$r = $this->api(
			'/payment/cancel',
			array(
				'locale'         => 'tr',
				'conversationId' => $txn['ref'],
				'paymentId'      => $txn['bank_ref'],
				'ip'             => flxpos_server_ip(),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array(
			'ok'       => $this->approved( $r ),
			'message'  => $this->approved( $r ) ? '' : $this->msg( $r ),
			'bank_ref' => (string) ( $r['paymentId'] ?? '' ),
		);
	}

	public function installments( $bin, $amount, $currency ) {
		$r = $this->api(
			'/payment/iyzipos/installment',
			array(
				'locale'         => 'tr',
				'conversationId' => 'inst' . time(),
				'binNumber'      => substr( preg_replace( '/\D/', '', (string) $bin ), 0, 8 ),
				'price'          => self::price( $amount ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( ! $this->approved( $r ) ) {
			return null;
		}
		$out = array();
		foreach ( (array) ( $r['installmentDetails'][0]['installmentPrices'] ?? array() ) as $i ) {
			$n     = (int) ( $i['installmentNumber'] ?? 0 );
			$total = (float) ( $i['totalPrice'] ?? 0 );
			if ( $n > 0 && $total > 0 ) {
				$out[ $n ] = array(
					'total' => $total,
					'rate'  => (float) $amount > 0 ? round( ( $total - (float) $amount ) / (float) $amount * 100, 2 ) : 0,
				);
			}
		}
		return $out;
	}

	public function test_connection() {
		$r = $this->api(
			'/payment/bin/check',
			array(
				'locale'         => 'tr',
				'conversationId' => 'test' . time(),
				'binNumber'      => '552608',
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return $this->approved( $r ) ? true : $this->err( $this->msg( $r ) );
	}
}
