<?php
/**
 * Kuveyt Türk ve Vakıf Katılım (BOA sanal POS).
 *
 * 3D Model: kart bilgisi imzalı XML ile ThreeDModelPayGate'e gönderilir; banka 3D sayfasının HTML'ini döner,
 * ödeyene olduğu gibi gösterilir. Dönüşte (Kuveyt: AuthenticationResponse XML; Vakıf Katılım: düz alanlar)
 * ResponseCode=00 ve MD gelirse ThreeDModelProvisionGate'e provizyon gönderilir; tutar ve sipariş no bizim
 * kaydımızdan gider (dönüş imzasız olduğu için).
 * HashData = base64(SHA-1(MerchantId MerchantOrderId Amount [OkUrl FailUrl] UserName base64(SHA-1(Şifre)))).
 *
 * Protokol başvurusu: Kuveyt Türk ve Vakıf Katılım sanal POS kılavuzları; açık kaynak mews/pos ve CP.VPOS (MIT).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Kuveyt extends FLXPOS_Driver {

	protected static function h( $s ) {
		return base64_encode( sha1( $s, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/** Vakıf Katılım çeşidi mi? */
	protected function vk() {
		return 'vakif_katilim' === $this->opt( 'kind' );
	}

	protected function pass_hash() {
		return self::h( $this->c( 'password' ) );
	}

	protected function cur( $c ) {
		return str_pad( flxpos_currency_num( $c ), 4, '0', STR_PAD_LEFT );
	}

	protected function base( $ref, $minor, $currency, $installment ) {
		$b = array(
			'APIVersion'          => $this->vk() ? '1.0.0' : 'TDV2.0.0',
			'MerchantId'          => $this->c( 'merchant_id' ),
			'CustomerId'          => $this->c( 'customer_id' ),
			'UserName'            => $this->c( 'user_name' ),
			'TransactionType'     => 'Sale',
			'InstallmentCount'    => $installment > 1 ? (string) (int) $installment : '0',
			'Amount'              => $minor,
			'DisplayAmount'       => $minor,
			'CurrencyCode'        => $this->cur( $currency ),
			'MerchantOrderId'     => $ref,
			'TransactionSecurity' => '3',
		);
		if ( $this->vk() ) {
			$b['HashPassword']    = $this->pass_hash();
			$b['PaymentType']     = '1';
			$b['FECCurrencyCode'] = $b['CurrencyCode'];
		} else {
			$b['BatchID'] = '0';
		}
		return $b;
	}

	protected function root() {
		return $this->vk() ? 'VPosMessageContract' : 'KuveytTurkVPosMessage';
	}

	protected function post( $key, array $body ) {
		$r = flxpos_http( $this->url( $key ), flxpos_array_to_xml( $this->root(), $body, 'utf-8' ), array( 'headers' => array( 'Content-Type' => 'application/xml' ) ) );
		return $r;
	}

	public function start( FLXPOS_Payment $p ) {
		$minor = flxpos_minor( $p->amount );
		$f     = array_merge(
			$this->base( $p->ref, $minor, $p->currency, $p->installment ),
			array(
				'OkUrl'               => $p->return_url,
				'FailUrl'             => $p->fail_url ? $p->fail_url : $p->return_url,
				'CardNumber'          => $p->pan(),
				'CardExpireDateYear'  => $p->yy(),
				'CardExpireDateMonth' => $p->mm(),
				'CardCVV2'            => $p->cvv(),
				'CardHolderName'      => flxpos_ascii( $p->holder(), 60 ),
			)
		);
		if ( ! $this->vk() ) {
			$phone              = substr( preg_replace( '/\D/', '', (string) $p->customer['phone'] ), -10 );
			$f['DeviceData']    = array(
				'DeviceChannel' => '02',
				'ClientIP'      => $p->ip,
			);
			$f['CardHolderData'] = array(
				'BillAddrCity'     => flxpos_ascii( (string) $p->customer['city'], 50 ),
				'BillAddrCountry'  => '792',
				'BillAddrLine1'    => flxpos_ascii( (string) $p->customer['address'], 150 ),
				'BillAddrPostCode' => (string) $p->customer['zip'],
				'Email'            => (string) $p->customer['email'],
				'MobilePhone'      => array(
					'Cc'         => '90',
					'Subscriber' => $phone,
				),
			);
		}
		$f['HashData'] = self::h( $f['MerchantId'] . $f['MerchantOrderId'] . $f['Amount'] . $f['OkUrl'] . $f['FailUrl'] . $f['UserName'] . $this->pass_hash() );
		$r             = $this->post( 'gate', $f );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$body = (string) $r['body'];
		if ( false === stripos( $body, '<form' ) ) {
			$x = flxpos_xml_to_array( $body );
			return $this->err( $this->reason( is_array( $x ) ? (string) flxpos_find( $x, 'ResponseMessage', '' ) : '', is_array( $x ) ? (string) flxpos_find( $x, 'ResponseCode', '' ) : '' ) );
		}
		return array( 'html' => $body );
	}

	/** Dönüş verisi: Kuveyt'te URL kodlu XML, Vakıf Katılım'da düz alanlar. */
	protected function back( array $req ) {
		$auth = $this->in( $req, 'AuthenticationResponse' );
		if ( '' !== $auth ) {
			$x = flxpos_xml_to_array( false !== strpos( $auth, '%3C' ) || false !== strpos( $auth, '%3c' ) ? urldecode( $auth ) : $auth );
			return is_array( $x ) ? $x : array();
		}
		return $this->all( $req );
	}

	public function finish( array $txn, array $req ) {
		$d = $this->back( $req );
		if ( '00' !== (string) ( $d['ResponseCode'] ?? '' ) ) {
			return $this->fail( $this->reason( $d['ResponseMessage'] ?? '', $d['ResponseCode'] ?? '' ) );
		}
		if ( (string) ( $d['MerchantOrderId'] ?? '' ) !== $txn['ref'] ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		$md    = (string) ( $d['MD'] ?? flxpos_find( $d, 'MD', '' ) );
		$minor = flxpos_minor( $txn['amount'] );
		$b     = $this->base( $txn['ref'], $minor, $txn['currency'], (int) $txn['installment'] );
		unset( $b['DisplayAmount'] );
		$add = array(
			'Key'  => 'MD',
			'Data' => $md,
		);
		if ( $this->vk() ) {
			$b['AdditionalData'] = array( 'AdditionalDataList' => array( 'VPosAdditionalData' => $add ) );
		} else {
			$b['KuveytTurkVPosAdditionalData'] = array( 'AdditionalData' => $add );
		}
		$b['HashData'] = self::h( $b['MerchantId'] . $b['MerchantOrderId'] . $b['Amount'] . $b['UserName'] . $this->pass_hash() );
		$r             = $this->post( 'provision', $b );
		if ( is_wp_error( $r ) ) {
			return $this->pending( $r->get_error_message() );
		}
		$x = flxpos_xml_to_array( $r['body'] );
		if ( ! is_array( $x ) ) {
			/* translators: %s: sağlayıcı */
			return $this->pending( sprintf( __( '%s provizyon yanıtı okunamadı; bankadan kontrol edin.', 'flx-pos' ), $this->name() ) );
		}
		if ( '00' === (string) ( $x['ResponseCode'] ?? '' ) ) {
			return $this->ok(
				array(
					'bank_ref'  => (string) ( $x['OrderId'] ?? '' ),
					'auth_code' => (string) ( $x['ProvisionNumber'] ?? '' ),
					'amount'    => (float) $txn['amount'],
					'data'      => array( 'RRN' => $x['RRN'] ?? '' ),
				)
			);
		}
		return $this->fail( $this->reason( $x['ResponseMessage'] ?? '', $x['ResponseCode'] ?? '' ) );
	}

	/** Vakıf Katılım: iptal ve kısmi iade (Kuveyt Türk'te SOAP servisi ayrı; panelden yapılır). */
	protected function after( $key, array $txn, $amount ) {
		if ( ! $this->vk() ) {
			return parent::refund( $txn, $amount );
		}
		$minor = flxpos_minor( $amount );
		$b     = array(
			'MerchantId'      => $this->c( 'merchant_id' ),
			'CustomerId'      => $this->c( 'customer_id' ),
			'UserName'        => $this->c( 'user_name' ),
			'HashPassword'    => $this->pass_hash(),
			'MerchantOrderId' => $txn['ref'],
			'OrderId'         => $txn['bank_ref'],
			'PaymentType'     => '1',
			'Amount'          => $minor,
		);
		$b['HashData'] = self::h( $b['MerchantId'] . $b['MerchantOrderId'] . $b['Amount'] . $b['UserName'] . $b['HashPassword'] );
		$r             = $this->post( $key, $b );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$x  = (array) flxpos_xml_to_array( $r['body'] );
		$ok = '00' === (string) ( $x['ResponseCode'] ?? '' );
		return array(
			'ok'       => $ok,
			'message'  => $ok ? '' : $this->reason( $x['ResponseMessage'] ?? '', $x['ResponseCode'] ?? '' ),
			'bank_ref' => (string) ( $x['OrderId'] ?? '' ),
		);
	}

	public function refund( array $txn, $amount ) {
		return $this->after( 'refund', $txn, $amount );
	}

	public function cancel( array $txn ) {
		if ( ! $this->vk() ) {
			return parent::cancel( $txn );
		}
		return $this->after( 'cancel', $txn, $txn['amount'] );
	}
}
