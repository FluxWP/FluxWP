<?php
/**
 * Nestpay / EST (Asseco): Akbank, İş Bankası, Halkbank, Ziraat, TEB, Şekerbank, QNB (EST), ING, Anadolubank,
 * Türkiye Finans, Alternatif Bank, Halkbank Makedonya. GiroGate (İş Bankası alternatif ödeme) da bu altyapıda.
 *
 * Modeller: 3d_pay (banka 3D sonrası çekimi kendisi yapar), 3d (3D doğrulama + bizim provizyon isteğimiz),
 * 3d_host (bankanın ortak ödeme sayfası, kart sitemize girmez), regular (3D'siz, yalnız izinli üye işyeri).
 * Form ve dönüş imzası "ver3": tüm alanlar adına göre sıralanır, | ile birleştirilir, store key eklenir, SHA-512.
 *
 * Protokol başvurusu: Asseco/Payten Nestpay entegrasyon kılavuzu; açık kaynak mews/pos (MIT).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Nestpay extends FLXPOS_Driver {

	/** 3D doğrulamanın başarılı sayıldığı mdStatus değerleri (1 tam; 2,3,4 kart/banka 3D'ye kayıtlı değil, banka kabul etti). */
	const MD_OK = array( '1', '2', '3', '4' );

	protected function storetype() {
		$m = array(
			'3d'      => '3d',
			'3d_pay'  => '3d_pay',
			'3d_host' => '3d_pay_hosting',
			'hosted'  => '3d_pay_hosting',
		);
		return $m[ $this->mode() ] ?? '3d_pay';
	}

	/** ver3 imzası. */
	public static function hash_v3( array $fields, $store_key ) {
		foreach ( array_keys( $fields ) as $k ) {
			if ( in_array( strtolower( (string) $k ), array( 'hash', 'encoding', 'nationalidno', 'countdown' ), true ) ) {
				unset( $fields[ $k ] );
			}
		}
		uksort(
			$fields,
			static function ( $a, $b ) {
				return strnatcasecmp( (string) $a, (string) $b );
			}
		);
		$vals   = array_map(
			static function ( $v ) {
				return str_replace( '|', '\\|', str_replace( '\\', '\\\\', (string) $v ) );
			},
			array_values( $fields )
		);
		$vals[] = str_replace( '|', '\\|', str_replace( '\\', '\\\\', (string) $store_key ) );
		return base64_encode( hash( 'sha512', implode( '|', $vals ), true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	public function start( FLXPOS_Payment $p ) {
		if ( 'regular' === $this->mode() ) {
			return array( 'result' => $this->direct( $p ) );
		}
		$f = array(
			'clientid'      => $this->c( 'client_id' ),
			'storetype'     => $this->storetype(),
			'hashAlgorithm' => 'ver3',
			'amount'        => flxpos_dec( $p->amount ),
			'currency'      => flxpos_currency_num( $p->currency ),
			'oid'           => $p->ref,
			'okUrl'         => $p->return_url,
			'failUrl'       => $p->fail_url ? $p->fail_url : $p->return_url,
			'callbackUrl'   => $p->return_url,
			'rnd'           => $this->rnd( 20 ),
			'lang'          => 'tr' === $p->lang ? 'tr' : 'en',
			'TranType'      => 'Auth',
			'taksit'        => $p->inst(),
			'BillToName'    => flxpos_ascii( $p->customer['name'] ?? '', 60 ),
			'email'         => (string) ( $p->customer['email'] ?? '' ),
		);
		if ( 'giro' === $this->opt( 'kind' ) ) {
			$f = array_merge( $f, $this->giro_fields( $p ) );
		}
		if ( $this->needs_card() ) {
			$f['pan']                             = $p->pan();
			$f['Ecom_Payment_Card_ExpDate_Month'] = $p->mm();
			$f['Ecom_Payment_Card_ExpDate_Year']  = $p->yy();
			$f['cv2']                             = $p->cvv();
		}
		$f['hash'] = self::hash_v3( $f, $this->c( 'store_key' ) );
		return array(
			'form' => array(
				'url'    => $this->url( 'gate' ),
				'fields' => $f,
			),
		);
	}

	/** GiroGate: kart yerine alternatif yöntem (iDEAL, Sofort, Trustly…), bankanın sayfasında. */
	protected function giro_fields( FLXPOS_Payment $p ) {
		$method = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $this->c( 'giro_method', 'IDEAL' ) ) );
		$f      = array(
			'storetype'   => '3d_pay_hosting',
			'paymentType' => 'GIROGATE_' . $method,
			'ccode'       => strtoupper( (string) ( $p->customer['country'] ?? 'TR' ) ),
		);
		$need   = array(
			'P24'                => array( 'email' ),
			'ZIMPLER'            => array( 'email' ),
			'ENTERCASH'          => array( 'consumerid' ),
			'TRUSTLY'            => array( 'consumerid' ),
			'ASTROPAYCARD'       => array( 'consumerid' ),
			'ASTROPAYDIRECT'     => array( 'consumerid' ),
			'GIROPAY'            => array( 'mobile' ),
			'DRAGONPAY'          => array( 'email', 'phone' ),
			'ENETS'              => array( 'email', 'phone' ),
			'SINGPOST'           => array( 'email', 'phone' ),
			'KRUNGTHAIBANK'      => array( 'email', 'phone' ),
			'KRUNGSIBANK'        => array( 'email', 'phone' ),
			'SIAMCOMMERCIALBANK' => array( 'email', 'phone' ),
			'BANGKOKBANK'        => array( 'email', 'phone' ),
		);
		$vals = array(
			'email'      => (string) ( $p->customer['email'] ?? '' ),
			'phone'      => (string) ( $p->customer['phone'] ?? '' ),
			'mobile'     => (string) ( $p->customer['phone'] ?? '' ),
			'consumerid' => (string) ( $p->customer['id'] ?? $p->ref ),
		);
		foreach ( $need[ $method ] ?? array() as $k ) {
			$f[ 'girogate' . $k ] = $vals[ $k ];
		}
		return $f;
	}

	public function finish( array $txn, array $req ) {
		$d = $this->all( $req );
		$h = (string) ( $d['HASH'] ?? ( $d['hash'] ?? '' ) );
		if ( '' === $h || ! hash_equals( self::hash_v3( $d, $this->c( 'store_key' ) ), $h ) ) {
			$msg = $d['ErrMsg'] ?? ( $d['mdErrorMsg'] ?? '' );
			return $this->fail( '' !== $msg ? $this->reason( $msg ) : __( 'Banka dönüşünün imzası tutmadı.', 'flx-pos' ), array( 'data' => array( 'hash' => 'mismatch' ) ) );
		}
		if ( ( $d['oid'] ?? '' ) !== $txn['ref'] ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		$md   = (string) ( $d['mdStatus'] ?? '' );
		$card = (string) ( $d['maskedCreditCard'] ?? '' );
		$amt  = isset( $d['amount'] ) ? flxpos_parse_amount( $d['amount'] ) : null;
		if ( '' !== $md && ! in_array( $md, self::MD_OK, true ) ) {
			return $this->fail( $this->reason( $d['mdErrorMsg'] ?? ( $d['ErrMsg'] ?? '' ), 'md' . $md ), array( 'data' => array( 'mdStatus' => $md ) ) );
		}
		if ( '3d' === $this->mode() ) {
			$r = $this->api(
				array(
					'Type'                    => 'Auth',
					'IPAddress'               => $txn['ip'],
					'OrderId'                 => $txn['ref'],
					'Total'                   => flxpos_dec( $txn['amount'] ),
					'Currency'                => flxpos_currency_num( $txn['currency'] ),
					'Taksit'                  => $txn['installment'] > 1 ? (string) $txn['installment'] : '',
					'Number'                  => (string) ( $d['md'] ?? '' ),
					'PayerTxnId'              => (string) ( $d['xid'] ?? '' ),
					'PayerSecurityLevel'      => (string) ( $d['eci'] ?? '' ),
					'PayerAuthenticationCode' => (string) ( $d['cavv'] ?? '' ),
					'Mode'                    => 'P',
				)
			);
			if ( is_wp_error( $r ) ) {
				return $this->pending( $r->get_error_message() );
			}
			return $this->api_result( $r, array( 'card' => $card, 'amount' => (float) $txn['amount'] ) );
		}
		if ( 'Pending' === ( $d['Response'] ?? '' ) ) {
			return $this->pending( __( 'Ödeme yöntemi sonucu sonradan bildirecek.', 'flx-pos' ), array( 'bank_ref' => (string) ( $d['TransId'] ?? '' ) ) );
		}
		if ( 'Approved' === ( $d['Response'] ?? '' ) && '00' === ( $d['ProcReturnCode'] ?? '' ) ) {
			return $this->ok(
				array(
					'bank_ref'    => (string) ( $d['TransId'] ?? '' ),
					'auth_code'   => (string) ( $d['AuthCode'] ?? '' ),
					'amount'      => $amt,
					'installment' => isset( $d['taksit'] ) ? max( 1, (int) $d['taksit'] ) : null,
					'card'        => $card,
					'data'        => array(
						'HostRefNum' => $d['HostRefNum'] ?? '',
						'mdStatus'   => $md,
					),
				)
			);
		}
		return $this->fail( $this->reason( $d['ErrMsg'] ?? ( $d['mdErrorMsg'] ?? '' ), $d['ProcReturnCode'] ?? '' ), array( 'card' => $card ) );
	}

	/** 3D'siz çekim. */
	protected function direct( FLXPOS_Payment $p ) {
		$r = $this->api(
			array(
				'Type'      => 'Auth',
				'IPAddress' => $p->ip,
				'Email'     => (string) ( $p->customer['email'] ?? '' ),
				'OrderId'   => $p->ref,
				'Total'     => flxpos_dec( $p->amount ),
				'Currency'  => flxpos_currency_num( $p->currency ),
				'Taksit'    => $p->inst(),
				'Number'    => $p->pan(),
				'Expires'   => $p->mm() . '/' . $p->yy(),
				'Cvv2Val'   => $p->cvv(),
				'Mode'      => 'P',
			)
		);
		if ( is_wp_error( $r ) ) {
			return $this->pending( $r->get_error_message() );
		}
		return $this->api_result( $r, array( 'card' => $this->mask( $p->pan() ), 'amount' => $p->amount ) );
	}

	protected function api( array $body ) {
		$xml = flxpos_array_to_xml(
			'CC5Request',
			array_merge(
				array(
					'Name'     => $this->c( 'api_user' ),
					'Password' => $this->c( 'api_pass' ),
					'ClientId' => $this->c( 'client_id' ),
				),
				$body
			)
		);
		return $this->post_xml( $this->url( 'api' ), $xml, array( 'Content-Type' => 'application/x-www-form-urlencoded' ), 'DATA' );
	}

	protected function api_result( array $r, array $more = array() ) {
		if ( 'Approved' === ( $r['Response'] ?? '' ) && '00' === ( $r['ProcReturnCode'] ?? '' ) ) {
			return $this->ok(
				array_merge(
					$more,
					array(
						'bank_ref'  => (string) ( $r['TransId'] ?? '' ),
						'auth_code' => (string) ( $r['AuthCode'] ?? '' ),
						'data'      => array( 'HostRefNum' => $r['HostRefNum'] ?? '' ),
					)
				)
			);
		}
		$extra = is_array( $r['Extra'] ?? null ) ? $r['Extra'] : array();
		return $this->fail( $this->reason( $r['ErrMsg'] ?? '', $extra['ERRORCODE'] ?? ( $r['ProcReturnCode'] ?? '' ) ), $more );
	}

	public function refund( array $txn, $amount ) {
		$r = $this->api(
			array(
				'Type'     => 'Credit',
				'OrderId'  => $txn['ref'],
				'Total'    => flxpos_dec( $amount ),
				'Currency' => flxpos_currency_num( $txn['currency'] ),
			)
		);
		return $this->simple( $r );
	}

	public function cancel( array $txn ) {
		return $this->simple(
			$this->api(
				array(
					'Type'    => 'Void',
					'OrderId' => $txn['ref'],
				)
			)
		);
	}

	protected function simple( $r ) {
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( 'Approved' === ( $r['Response'] ?? '' ) ) {
			return array(
				'ok'       => true,
				'message'  => '',
				'bank_ref' => (string) ( $r['TransId'] ?? '' ),
			);
		}
		return array(
			'ok'      => false,
			'message' => $this->reason( $r['ErrMsg'] ?? '', $r['ProcReturnCode'] ?? '' ),
		);
	}

	public function status( array $txn ) {
		$r = $this->api(
			array(
				'OrderId' => $txn['ref'],
				'Extra'   => array( 'ORDERSTATUS' => 'QUERY' ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$e    = is_array( $r['Extra'] ?? null ) ? $r['Extra'] : array();
		$stat = strtoupper( (string) ( $e['TRANS_STAT'] ?? '' ) );
		if ( '00' === ( $r['ProcReturnCode'] ?? '' ) && in_array( $stat, array( 'C', 'S' ), true ) ) {
			return $this->ok(
				array(
					'bank_ref'  => (string) ( $r['TransId'] ?? '' ),
					'auth_code' => (string) ( $e['AUTH_CODE'] ?? '' ),
					'amount'    => isset( $e['CAPTURE_AMT'] ) && '' !== $e['CAPTURE_AMT'] ? flxpos_parse_amount( $e['CAPTURE_AMT'], true ) : null,
					'card'      => (string) ( $e['PAN'] ?? '' ),
				)
			);
		}
		if ( 'V' === $stat ) {
			return array_merge( $this->fail( __( 'İşlem bankada iptal edilmiş.', 'flx-pos' ) ), array( 'status' => 'voided' ) );
		}
		return $this->fail( $this->reason( $r['ErrMsg'] ?? '', $stat ) );
	}

	public function test_connection() {
		$r = $this->api(
			array(
				'OrderId' => 'FXTEST' . time(),
				'Extra'   => array( 'ORDERSTATUS' => 'QUERY' ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$msg = (string) ( $r['ErrMsg'] ?? '' );
		if ( preg_match( '/yetki|authoriz|authentic|password|şifre|sifre|kullanici|user/i', $msg ) ) {
			return $this->err( $msg );
		}
		return true;
	}
}
