<?php
/**
 * Kayıtlı kartla sonraki çekimler: diğer eklentilerin kullandığı ortak yardımcılar ve Flux Commerce
 * aboneliklerinin kart sağlayıcısı.
 *
 *  - flxpos_can_save_card( $currency ): bu para biriminde kartı saklayıp sonra çekebilen kartlı hesap var mı.
 *  - flxpos_saved_card( $order ): siparişin ödemesinde saklanan kart (hesap, müşteri, ödeme yöntemi, etiket).
 *  - Siparişte `_flxpos_save` = yes ise sürücü kartı saklar (Flux Donate aylık bağışta yazar; Flux Commerce
 *    aboneliğinde bu sınıf yazar).
 *  - `flxcm_card_providers`: Flux Commerce yenileme siparişini açar, bu sağlayıcı kartı flxpos_charge_saved ile çeker.
 *
 * Bugün kart saklayan sürücü yalnız Stripe (sürücüde charge_saved; kart Stripe'ta, sitede yalnız cus_/pm_ kimlikleri).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

final class FLXPOS_Saved_Cards {

	const PROVIDER = 'flxpos';

	public static function init() {
		add_filter( 'flxcm_card_providers', array( __CLASS__, 'commerce_provider' ) );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'mark_order' ), 20 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'mark_order' ), 20 );
	}

	/** Hesabın sürücüsü kayıtlı kartla çekebiliyor mu? */
	public static function account_saves( $acc ) {
		if ( ! is_array( $acc ) || empty( $acc['enabled'] ) ) {
			return false;
		}
		$drv = FLXPOS_Accounts::driver( $acc['id'] );
		return ! is_wp_error( $drv ) && method_exists( $drv, 'charge_saved' );
	}

	/** Flux Commerce aboneliği içeren siparişte kart saklansın. */
	public static function mark_order( $order ) {
		if ( ! $order instanceof WC_Order || ! function_exists( 'flxcm_order_has_subscription' ) || ! flxcm_order_has_subscription( $order ) ) {
			return;
		}
		$order->update_meta_data( '_flxpos_save', 'yes' );
		if ( did_action( 'woocommerce_store_api_checkout_update_order_meta' ) ) {
			$order->save_meta_data();
		}
	}

	/**
	 * Siparişin ödemesinde saklanan kart.
	 *
	 * @param WC_Order|int $order Sipariş.
	 * @return array{account:string,customer:string,payment_method:string,label:string}|null
	 */
	public static function of_order( $order ) {
		$order = $order instanceof WC_Order ? $order : ( function_exists( 'wc_get_order' ) ? wc_get_order( (int) $order ) : null );
		if ( ! $order ) {
			return null;
		}
		$txn = FLXPOS_Txn::get( (int) $order->get_meta( '_flxpos_txn' ) );
		if ( ! $txn || FLXPOS_Txn::PAID !== $txn['status'] ) {
			return null;
		}
		$d   = (array) $txn['data'];
		$cus = (string) ( $d['stripe_customer'] ?? '' );
		$pm  = (string) ( $d['stripe_pm'] ?? '' );
		if ( '' === $cus || '' === $pm ) {
			return null;
		}
		$brand = (string) ( $d['stripe_brand'] ?? $txn['card_brand'] ?? '' );
		$last4 = '' !== (string) $txn['card'] ? substr( (string) $txn['card'], -4 ) : '';
		return array(
			'account'        => (string) $txn['account'],
			'customer'       => $cus,
			'payment_method' => $pm,
			'label'          => trim( ucfirst( $brand ) . ( '' !== $last4 ? ' •••• ' . $last4 : '' ) ),
		);
	}

	/**
	 * Kayıtlı kartı bir siparişe çek (Flux POS siparişi kendisi öder ya da not düşer).
	 * Önceki denemenin cevabı gelmediyse (işlem beklemede) yeni çekim yapılmaz.
	 *
	 * @param WC_Order $order Ödenecek sipariş (yenileme).
	 * @param array    $card  of_order() sonucu.
	 * @return array{result:string, txn?:string, error?:string} result paid|failed|uncertain
	 */
	public static function charge_order( WC_Order $order, array $card, $description = '' ) {
		$prev = FLXPOS_Txn::get( (int) $order->get_meta( '_flxpos_saved_txn' ) );
		if ( $prev && FLXPOS_Txn::PENDING === $prev['status'] ) {
			if ( time() - strtotime( (string) $prev['created'] . ' UTC' ) < DAY_IN_SECONDS ) {
				return array( 'result' => 'uncertain', 'error' => __( 'Önceki çekimin sonucu bekleniyor.', 'flx-pos' ) );
			}
			FLXPOS_Txn::settle( $prev['id'], FLXPOS_Txn::FAILED, array( 'message' => __( 'Sonuç gelmedi.', 'flx-pos' ) ) );
		} elseif ( $prev && FLXPOS_Txn::PAID === $prev['status'] ) {
			return array( 'result' => 'paid', 'txn' => (string) $prev['ref'] );
		}
		if ( $order->is_paid() ) {
			return array( 'result' => 'paid' );
		}
		$r = flxpos_charge_saved(
			array(
				'account'        => (string) ( $card['account'] ?? '' ),
				'customer'       => (string) ( $card['customer'] ?? '' ),
				'payment_method' => (string) ( $card['payment_method'] ?? '' ),
				'amount'         => (float) $order->get_total(),
				'currency'       => $order->get_currency(),
				'description'    => '' !== (string) $description ? (string) $description : sprintf( /* translators: %s: sipariş no */ __( 'Sipariş #%s', 'flx-pos' ), $order->get_order_number() ),
				'customer_info'  => array(
					'name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					'email' => $order->get_billing_email(),
					'phone' => $order->get_billing_phone(),
				),
				'order_id'       => (int) $order->get_id(),
			)
		);
		if ( is_wp_error( $r ) ) {
			return array( 'result' => 'failed', 'error' => $r->get_error_message() );
		}
		$order->update_meta_data( '_flxpos_saved_txn', (string) (int) $r['id'] );
		$order->save_meta_data();
		if ( FLXPOS_Txn::PAID === $r['status'] ) {
			return array( 'result' => 'paid', 'txn' => (string) $r['ref'] );
		}
		if ( FLXPOS_Txn::PENDING === $r['status'] ) {
			return array( 'result' => 'uncertain', 'error' => (string) $r['message'] );
		}
		return array( 'result' => 'failed', 'error' => '' !== (string) $r['message'] ? (string) $r['message'] : __( 'Kart çekilemedi.', 'flx-pos' ) );
	}

	/* ------------------------------------------------------------------
	 * Flux Commerce
	 * ------------------------------------------------------------------ */

	public static function commerce_provider( $providers ) {
		$providers = is_array( $providers ) ? $providers : array();
		$providers[ self::PROVIDER ] = array(
			'label'    => 'Flux POS',
			'gateways' => array( 'flxpos_card' ),
			'card'     => array( __CLASS__, 'commerce_card' ),
			'charge'   => array( __CLASS__, 'commerce_charge' ),
		);
		return $providers;
	}

	public static function commerce_card( WC_Order $order ) {
		$c = self::of_order( $order );
		if ( ! $c ) {
			return null;
		}
		return array(
			'data'  => array_intersect_key( $c, array_flip( array( 'account', 'customer', 'payment_method' ) ) ),
			'label' => $c['label'],
		);
	}

	public static function commerce_charge( WC_Order $order, array $data, array $sub ) {
		$order->set_payment_method( 'flxpos_card' );
		$order->save();
		return self::charge_order( $order, $data, (string) ( $sub['product_name'] ?? '' ) );
	}
}

FLXPOS_Saved_Cards::init();
