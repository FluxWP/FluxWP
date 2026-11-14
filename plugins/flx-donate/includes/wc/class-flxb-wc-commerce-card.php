<?php
/**
 * Flux Commerce aboneliklerinde kartla otomatik yenileme için iyzico sağlayıcısı.
 *
 * Flux Commerce (0.5.0+) ortak kart katmanı `flxcm_card_providers` filtresiyle sağlayıcı ister. İlk abonelik
 * siparişi iyzico ile ödenince kart iyzico'da saklanır (aylık bağıştaki gibi: paymentGroup SUBSCRIPTION,
 * registerCard) ve mühürlü cardUserKey/cardToken Commerce'e verilir. Yenilemede Commerce yenileme siparişini
 * açar, bu sınıf kartı iyzico'dan çeker; cevap gelmezse bir sonraki denemede önce ödeme durumunu sorar.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

class FLXB_WC_Commerce_Card {

	const PROVIDER = 'flxb_iyzico';

	public static function init() {
		add_filter( 'flxcm_card_providers', array( __CLASS__, 'register' ) );
	}

	public static function register( $providers ) {
		if ( FLXB_WC_Iyzico::configured() ) {
			$providers[ self::PROVIDER ] = array(
				'label'    => 'iyzico',
				'gateways' => array( FLXB_WC_Iyzico::ID ),
				'card'     => array( __CLASS__, 'card' ),
				'charge'   => array( __CLASS__, 'charge' ),
			);
		}
		return $providers;
	}

	/** Siparişte saklanan kart (mühürlü). */
	public static function card( WC_Order $order ) {
		$c = $order->get_meta( '_flxb_iyz_card' );
		if ( ! is_array( $c ) || empty( $c['k'] ) || empty( $c['t'] ) ) {
			return null;
		}
		return array(
			'data'  => array(
				'k' => (string) $c['k'],
				't' => (string) $c['t'],
			),
			'label' => (string) ( $c['label'] ?? '' ),
		);
	}

	/**
	 * Yenileme siparişini saklanan karttan çeker.
	 *
	 * @return array{result: string, txn?: string, error?: string}
	 */
	public static function charge( WC_Order $order, array $data, array $sub ) {
		$api = FLXB_Iyzico_Api::make();
		if ( ! $api ) {
			return array( 'result' => 'uncertain', 'error' => __( 'iyzico keys are missing.', 'flx-donate' ) );
		}
		$amount = (float) $order->get_total();
		$cur    = strtoupper( $order->get_currency() );

		/* Önceki denemenin cevabı gelmediyse önce iyzico'ya sor, iki kez çekme. */
		$prev = (string) $order->get_meta( '_flxb_iyz_uncertain' );
		if ( '' !== $prev ) {
			try {
				$d = $api->post( '/payment/detail', array( 'locale' => FLXB_Iyzico_Api::locale(), 'conversationId' => $prev, 'paymentConversationId' => $prev ) );
			} catch ( FLXB_Iyzico_Error $e ) {
				return array( 'result' => 'uncertain', 'error' => $e->getMessage() );
			}
			$order->delete_meta_data( '_flxb_iyz_uncertain' );
			if ( FLXB_Iyzico_Api::ok( $d ) && 'SUCCESS' === ( $d['paymentStatus'] ?? '' ) && FLXB_Iyzico_Api::minor( $d['paidPrice'] ?? 0 ) >= FLXB_Iyzico_Api::minor( $amount ) ) {
				return self::paid( $order, $d );
			}
			$order->save_meta_data();
		}

		$k = FLXB_Iyzico_Api::open( $data['k'] ?? '' );
		$t = FLXB_Iyzico_Api::open( $data['t'] ?? '' );
		if ( '' === $k || '' === $t ) {
			return array( 'result' => 'failed', 'error' => __( 'No stored card for this subscription.', 'flx-donate' ) );
		}
		$attempt = (int) $order->get_meta( '_flxcm_charge_attempts' ) + 1;
		$conv    = 'flxcmr-' . $order->get_id() . '-' . $attempt;
		$contact = array(
			'name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'email' => $order->get_billing_email(),
			'phone' => $order->get_billing_phone(),
		);
		list( $buyer, $address ) = FLXB_Iyzico_Api::buyer( $contact, $order->get_billing_email(), (string) $order->get_customer_ip_address() );
		if ( '' === (string) $buyer['ip'] ) {
			$buyer['ip'] = '127.0.0.1';
		}
		if ( '' !== $order->get_billing_city() ) {
			$address['city'] = $order->get_billing_city();
			$buyer['city']   = $order->get_billing_city();
		}
		$name = (string) ( $sub['product_name'] ?? '' );
		$body = array(
			'locale'         => FLXB_Iyzico_Api::locale(),
			'conversationId' => $conv,
			'price'          => FLXB_Iyzico_Api::price( $amount ),
			'paidPrice'      => FLXB_Iyzico_Api::price( $amount ),
			'installment'    => 1,
			'paymentChannel' => 'WEB',
			'basketId'       => $conv,
			'paymentGroup'   => 'SUBSCRIPTION',
			'paymentCard'    => array( 'cardUserKey' => $k, 'cardToken' => $t ),
			'buyer'          => $buyer,
			'billingAddress' => $address,
			'basketItems'    => FLXB_Iyzico_Api::basket( $amount, FLXB_WC_Iyzico::basket_lines( $order ), '' !== $name ? $name : __( 'Subscription', 'flx-donate' ), 'flxcm' . $order->get_id() ),
			'currency'       => $cur,
		);
		$body = (array) apply_filters( 'flxb_commerce_renewal_request', $body, $order, $sub );
		$order->update_meta_data( '_flxb_iyz_uncertain', $conv );
		$order->save_meta_data();
		try {
			$r = $api->payment_create( $body );
		} catch ( FLXB_Iyzico_Error $e ) {
			return array( 'result' => 'uncertain', 'error' => $e->getMessage() ); // Cevap kayboldu: sonraki denemede sorulur.
		}
		unset( $body, $k, $t );
		$order->delete_meta_data( '_flxb_iyz_uncertain' );
		$order->save_meta_data();
		$ok = FLXB_Iyzico_Api::ok( $r )
			&& ( ! isset( $r['paymentStatus'] ) || 'SUCCESS' === $r['paymentStatus'] )
			&& FLXB_Iyzico_Api::minor( $r['paidPrice'] ?? 0 ) >= FLXB_Iyzico_Api::minor( $amount )
			&& strtoupper( (string) ( $r['currency'] ?? $cur ) ) === $cur
			&& -1 !== (int) ( $r['fraudStatus'] ?? 1 );
		if ( $ok ) {
			return self::paid( $order, $r );
		}
		$err = FLXB_Iyzico_Api::error_text( $r );
		return array( 'result' => 'failed', 'error' => '' !== $err ? 'iyzico: ' . $err : __( 'The card was not charged.', 'flx-donate' ) );
	}

	private static function paid( WC_Order $order, array $r ) {
		$info = FLXB_Iyzico_Api::payment_info( $r );
		$order->update_meta_data( '_flxb_iyzico', $info );
		$order->save_meta_data();
		return array( 'result' => 'paid', 'txn' => (string) $info['paymentId'] );
	}
}
