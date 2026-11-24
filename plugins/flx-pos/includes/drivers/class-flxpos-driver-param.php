<?php
/**
 * Param (TurkPOS SOAP).
 *
 * 3D: TP_WMD_UCD kart bilgisini alır ve 3D sayfasının HTML'ini (UCD_HTML) döner; dönüşte islemHash
 * (base64(SHA-1(islemGUID md mdStatus orderId GUID))) doğrulanır, mdStatus=1 ise TP_WMD_Pay ile çekim yapılır.
 * Tutarlar "1000,00" biçiminde. İşlem imzası: base64(SHA-1(CLIENT_CODE GUID Taksit Islem_Tutar Toplam_Tutar
 * Siparis_ID)).
 *
 * Protokol başvurusu: Param TurkPOS entegrasyon kılavuzu; açık kaynak mews/pos ve CP.VPOS (MIT).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Param extends FLXPOS_Driver {

	const NS = 'https://turkpos.com.tr/';

	protected static function h( $s ) {
		return base64_encode( sha1( $s, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	protected function amt( $v ) {
		return number_format( round( (float) $v, 2 ), 2, ',', '' );
	}

	protected function g() {
		return array(
			'CLIENT_CODE'     => $this->c( 'client_code' ),
			'CLIENT_USERNAME' => $this->c( 'client_user' ),
			'CLIENT_PASSWORD' => $this->c( 'client_pass' ),
		);
	}

	/** SOAP çağrısı; Result düğümü. */
	protected function call( $method, array $body ) {
		$inner = '<' . $method . ' xmlns="' . self::NS . '">' . substr( flxpos_xml_el( 'x', array_merge( array( 'G' => $this->g() ), $body ) ), 3, -4 ) . '</' . $method . '>';
		$r     = $this->soap( $this->url( 'api' ), self::NS . $method, $inner );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return (array) ( $r[ $method . 'Result' ] ?? $r );
	}

	protected function msg( $r ) {
		return $this->reason( $r['Sonuc_Str'] ?? ( $r['Sonuc_Ack'] ?? '' ), $r['Sonuc'] ?? '' );
	}

	public function start( FLXPOS_Payment $p ) {
		$inst  = max( 1, (int) $p->installment );
		$amt   = $this->amt( $p->amount );
		$total = $amt;
		$guid  = $this->c( 'guid' );
		$fail  = $p->fail_url ? $p->fail_url : $p->return_url;
		$r     = $this->call(
			'TP_WMD_UCD',
			array(
				'GUID'               => $guid,
				'KK_Sahibi'          => flxpos_ascii( $p->holder(), 60 ),
				'KK_No'              => $p->pan(),
				'KK_SK_Ay'           => $p->mm(),
				'KK_SK_Yil'          => (string) ( 2000 + (int) $p->yy() ),
				'KK_CVC'             => $p->cvv(),
				'KK_Sahibi_GSM'      => preg_replace( '/\D/', '', (string) $p->customer['phone'] ),
				'Hata_URL'           => $fail,
				'Basarili_URL'       => $p->return_url,
				'Siparis_ID'         => $p->ref,
				'Siparis_Aciklama'   => mb_substr( $p->description, 0, 250 ),
				'Taksit'             => (string) $inst,
				'Islem_Tutar'        => $amt,
				'Toplam_Tutar'       => $total,
				'Islem_Hash'         => self::h( $this->c( 'client_code' ) . $guid . $inst . $amt . $total . $p->ref ),
				'Islem_Guvenlik_Tip' => 'regular' === $this->mode() ? 'NS' : '3D',
				'Islem_ID'           => '',
				'IPAdr'              => $p->ip,
				'Ref_URL'            => home_url( '/' ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return 'regular' === $this->mode() ? array( 'result' => $this->pending( $r->get_error_message() ) ) : $r;
		}
		$html = (string) ( $r['UCD_HTML'] ?? '' );
		if ( (int) ( $r['Sonuc'] ?? -1 ) <= 0 || '' === $html ) {
			return 'regular' === $this->mode() ? array( 'result' => $this->fail( $this->msg( $r ) ) ) : $this->err( $this->msg( $r ) );
		}
		if ( 'NONSECURE' === strtoupper( $html ) ) {
			return array(
				'result' => $this->ok(
					array(
						'bank_ref' => (string) ( $r['Islem_ID'] ?? '' ),
						'amount'   => $p->amount,
						'card'     => $this->mask( $p->pan() ),
					)
				),
			);
		}
		return array( 'html' => $html );
	}

	public function finish( array $txn, array $req ) {
		$d    = $this->all( $req );
		$want = self::h( ( $d['islemGUID'] ?? '' ) . ( $d['md'] ?? '' ) . ( $d['mdStatus'] ?? '' ) . ( $d['orderId'] ?? '' ) . $this->c( 'guid' ) );
		if ( ! hash_equals( $want, (string) ( $d['islemHash'] ?? '' ) ) ) {
			return $this->fail( __( 'Banka dönüşünün imzası tutmadı.', 'flx-pos' ), array( 'data' => array( 'hash' => 'mismatch' ) ) );
		}
		if ( ( $d['orderId'] ?? '' ) !== $txn['ref'] ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		if ( '1' !== (string) ( $d['mdStatus'] ?? '' ) ) {
			return $this->fail( $this->reason( $d['mdErrorMessage'] ?? '', 'md' . ( $d['mdStatus'] ?? '' ) ) );
		}
		$r = $this->call(
			'TP_WMD_Pay',
			array(
				'GUID'       => $this->c( 'guid' ),
				'UCD_MD'     => (string) $d['md'],
				'Islem_GUID' => (string) $d['islemGUID'],
				'Siparis_ID' => $txn['ref'],
			)
		);
		if ( is_wp_error( $r ) ) {
			return $this->pending( $r->get_error_message() );
		}
		if ( (int) ( $r['Sonuc'] ?? -1 ) > 0 && (int) ( $r['Dekont_ID'] ?? 0 ) > 0 ) {
			return $this->ok(
				array(
					'bank_ref'  => (string) $r['Dekont_ID'],
					'auth_code' => (string) ( $r['Banka_Sonuc_Kod'] ?? '' ),
					'amount'    => (float) $txn['amount'],
				)
			);
		}
		return $this->fail( $this->msg( $r ) );
	}

	protected function back( $state, array $txn, $amount ) {
		$r = $this->call(
			'TP_Islem_Iptal_Iade_Kismi2',
			array(
				'GUID'       => $this->c( 'guid' ),
				'Durum'      => $state,
				'Siparis_ID' => $txn['ref'],
				'Tutar'      => $this->amt( $amount ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$ok = (int) ( $r['Sonuc'] ?? -1 ) > 0;
		return array(
			'ok'       => $ok,
			'message'  => $ok ? '' : $this->msg( $r ),
			'bank_ref' => '',
		);
	}

	public function refund( array $txn, $amount ) {
		return $this->back( 'IADE', $txn, $amount );
	}

	public function cancel( array $txn ) {
		return $this->back( 'IPTAL', $txn, 0 );
	}

	public function status( array $txn ) {
		$r = $this->call(
			'TP_Islem_Sorgulama4',
			array(
				'GUID'       => $this->c( 'guid' ),
				'Dekont_ID'  => (string) $txn['bank_ref'],
				'Siparis_ID' => $txn['ref'],
				'Islem_ID'   => '',
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$dt = (array) ( $r['DT_Bilgi'] ?? array() );
		if ( (int) ( $r['Sonuc'] ?? -1 ) > 0 && in_array( strtoupper( (string) flxpos_find( $dt, 'Durum', '' ) ), array( 'SUCCESS', 'BASARILI', '1' ), true ) ) {
			return $this->ok(
				array(
					'bank_ref' => (string) flxpos_find( $dt, 'Dekont_ID', $txn['bank_ref'] ),
					'amount'   => flxpos_parse_amount( (string) flxpos_find( $dt, 'Toplam_Tutar', '' ) ),
				)
			);
		}
		return $this->fail( $this->msg( $r ) );
	}

	public function test_connection() {
		$r = $this->call(
			'TP_Ozel_Oran_SK_Liste',
			array( 'GUID' => $this->c( 'guid' ) )
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return (int) ( $r['Sonuc'] ?? -1 ) > 0 ? true : $this->err( $this->msg( $r ) );
	}
}
