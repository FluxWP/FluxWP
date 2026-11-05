<?php
/**
 * PayFlex (VPOS 7/24): Vakıfbank, Ziraat Bankası (PayFlex).
 *
 * 3D: (1) MPI'ye kayıt sorgusu (Enrollment) → ACS adresi ve PaReq, (2) ödeyen ACS'ye POST edilir, (3) dönüşte
 * Status=Y ise ECI/CAVV ile VPOS'a "Sale" gönderilir. Bu son adım kart bilgisini yeniden ister: kart, işleme
 * bağlı şifreli olarak 20 dakika saklanır (bkz. FLXPOS_Vault::put_return), dönüşte bir kez açılır.
 *
 * Protokol başvurusu: Vakıfbank VPOS 7/24 ve MPI kılavuzları; açık kaynak mews/pos (MIT).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Payflex extends FLXPOS_Driver {

	/** Dönüşte kartı yeniden ister. */
	public function card_on_return() {
		return true;
	}

	protected function brand( $pan ) {
		$pan = (string) $pan;
		if ( preg_match( '/^4/', $pan ) ) {
			return '100';
		}
		if ( preg_match( '/^(5[1-5]|2[2-7])/', $pan ) ) {
			return '200';
		}
		if ( preg_match( '/^9792|^65/', $pan ) ) {
			return '300';
		}
		if ( preg_match( '/^3[47]/', $pan ) ) {
			return '400';
		}
		return '100';
	}

	public function start( FLXPOS_Payment $p ) {
		$f = array(
			'MerchantId'                => $this->c( 'merchant_id' ),
			'MerchantPassword'          => $this->c( 'merchant_pass' ),
			'MerchantType'              => $this->c( 'merchant_type', '0' ),
			'PurchaseAmount'            => flxpos_dec( $p->amount ),
			'VerifyEnrollmentRequestId' => $p->ref,
			'Currency'                  => flxpos_currency_num( $p->currency ),
			'SuccessUrl'                => $p->return_url,
			'FailureUrl'                => $p->fail_url ? $p->fail_url : $p->return_url,
			'Pan'                       => $p->pan(),
			'ExpiryDate'                => $p->yy() . $p->mm(),
			'BrandName'                 => $this->brand( $p->pan() ),
			'IsRecurring'               => 'false',
		);
		if ( $p->installment > 1 ) {
			$f['InstallmentCount'] = (string) $p->installment;
		}
		$r = flxpos_http( $this->url( 'mpi' ), $f, array( 'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ) ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$x   = flxpos_xml_to_array( $r['body'] );
		$ver = is_array( $x ) ? (array) flxpos_find( $x, 'VERes', array() ) : array();
		if ( 'Y' !== (string) ( $ver['Status'] ?? '' ) ) {
			$msg = is_array( $x ) ? (string) flxpos_find( $x, 'ErrorMessage', '' ) : '';
			return $this->err( '' !== $msg ? $msg : __( 'Kart 3D Secure doğrulamasına uygun değil.', 'flx-pos' ) );
		}
		return array(
			'form' => array(
				'url'    => (string) $ver['ACSUrl'],
				'fields' => array(
					'PaReq'   => (string) $ver['PaReq'],
					'TermUrl' => (string) $ver['TermUrl'],
					'MD'      => (string) $ver['MD'],
				),
			),
		);
	}

	protected function vpos( array $body ) {
		$xml = flxpos_array_to_xml(
			'VposRequest',
			array_merge(
				array(
					'MerchantId' => $this->c( 'merchant_id' ),
					'Password'   => $this->c( 'merchant_pass' ),
					'TerminalNo' => $this->c( 'terminal_id' ),
				),
				$body
			),
			'utf-8'
		);
		return $this->post_xml( $this->url( 'api' ), $xml, array(), 'prmstr' );
	}

	public function finish( array $txn, array $req ) {
		$status = $this->in( $req, 'Status' );
		if ( 'Y' !== $status ) {
			return $this->fail( $this->reason( $this->in( $req, 'ErrorMessage' ), $this->in( $req, 'ErrorCode', $status ) ) );
		}
		if ( $this->in( $req, 'VerifyEnrollmentRequestId' ) !== $txn['ref'] ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		$card = (array) ( $req['card'] ?? array() );
		if ( empty( $card['pan'] ) ) {
			return $this->fail( __( 'Ödeme süresi doldu; lütfen kart bilgilerini yeniden girin.', 'flx-pos' ) );
		}
		$pan  = preg_replace( '/\D/', '', (string) $card['pan'] );
		$body = array(
			'TransactionType'         => 'Sale',
			'TransactionId'           => '',
			'CurrencyAmount'          => flxpos_dec( $txn['amount'] ),
			'CurrencyCode'            => flxpos_currency_num( $txn['currency'] ),
			'ECI'                     => $this->in( $req, 'Eci' ),
			'CAVV'                    => $this->in( $req, 'Cavv' ),
			'MpiTransactionId'        => $txn['ref'],
			'OrderId'                 => $txn['ref'],
			'ClientIp'                => $txn['ip'],
			'TransactionDeviceSource' => '0',
			'CardHoldersName'         => flxpos_ascii( (string) ( $card['name'] ?? '' ), 60 ),
			'Cvv'                     => (string) ( $card['cvv'] ?? '' ),
			'Pan'                     => $pan,
			'Expiry'                  => (string) $card['year'] . str_pad( (string) (int) $card['month'], 2, '0', STR_PAD_LEFT ),
		);
		if ( $txn['installment'] > 1 ) {
			$body['NumberOfInstallments'] = (string) $txn['installment'];
		}
		$r = $this->vpos( $body );
		unset( $card, $body );
		if ( is_wp_error( $r ) ) {
			return $this->pending( $r->get_error_message() );
		}
		if ( '0000' === (string) ( $r['ResultCode'] ?? '' ) ) {
			return $this->ok(
				array(
					'bank_ref'  => (string) ( $r['TransactionId'] ?? '' ),
					'auth_code' => (string) ( $r['AuthCode'] ?? '' ),
					'amount'    => (float) $txn['amount'],
					'card'      => $this->mask( $pan ),
					'data'      => array( 'Rrn' => $r['Rrn'] ?? '' ),
				)
			);
		}
		return $this->fail( $this->reason( $r['ResultDetail'] ?? '', $r['ResultCode'] ?? '' ), array( 'card' => $this->mask( $pan ) ) );
	}

	protected function after( array $body ) {
		$r = $this->vpos( $body );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$ok = '0000' === (string) ( $r['ResultCode'] ?? '' );
		return array(
			'ok'       => $ok,
			'message'  => $ok ? '' : $this->reason( $r['ResultDetail'] ?? '', $r['ResultCode'] ?? '' ),
			'bank_ref' => (string) ( $r['TransactionId'] ?? '' ),
		);
	}

	public function refund( array $txn, $amount ) {
		return $this->after(
			array(
				'TransactionType'        => 'Refund',
				'ReferenceTransactionId' => $txn['bank_ref'],
				'ClientIp'               => flxpos_server_ip(),
				'CurrencyAmount'         => flxpos_dec( $amount ),
			)
		);
	}

	public function cancel( array $txn ) {
		return $this->after(
			array(
				'TransactionType'        => 'Cancel',
				'ReferenceTransactionId' => $txn['bank_ref'],
				'ClientIp'               => flxpos_server_ip(),
			)
		);
	}
}
