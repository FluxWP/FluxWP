<?php
/**
 * Abonelikte kartla otomatik yenileme: ortak kart sağlayıcı katmanı.
 *
 * Ödeme eklentileri (ör. Flux Donate iyzico) `flxcm_card_providers` ile sağlayıcı kaydeder. İlk siparişi
 * (ya da elle ödenen bir yenilemeyi) kart sağlayıcısı öderse kart, sağlayıcının verdiği şekliyle (mühürlü
 * token) aboneliğin meta'sına yazılır. Yenileme siparişi dönem bitmeden açılır, müşteriye "kartınızdan
 * çekilecek" e-postası gider; dönem bitince kart çekilir. Çekilemezse 1 ve 2 gün sonra yeniden denenir,
 * son denemede de olmazsa yenileme siparişinin ödeme bağlantısı e-postayla gider (Faz 1 akışı).
 *
 * Sağlayıcı:
 *   array(
 *     'label'    => 'iyzico',
 *     'gateways' => array( 'flxb_iyzico' ),
 *     'card'     => callable( WC_Order $order ): ?array{ data: array, label: string },
 *     'charge'   => callable( WC_Order $renewal, array $data, array $sub ): array{ result: paid|failed|uncertain, txn?: string, error?: string },
 *   )
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Sub_Cards {

	const META = '_card';

	public static function init() {
		add_action( 'flxcm_subscription_started', array( __CLASS__, 'capture' ), 5, 2 );
		add_action( 'flxcm_subscription_renewed', array( __CLASS__, 'capture' ), 5, 2 );
		add_action( 'flxcm_subscription_ended', array( __CLASS__, 'on_end' ), 5 );
	}

	/** @return array<string,array> Kayıtlı sağlayıcılar. */
	public static function providers() {
		$out = array();
		foreach ( (array) apply_filters( 'flxcm_card_providers', array() ) as $id => $p ) {
			if ( is_array( $p ) && ! empty( $p['gateways'] ) && is_callable( $p['card'] ?? null ) && is_callable( $p['charge'] ?? null ) ) {
				$p['id']    = sanitize_key( $id );
				$out[ $id ] = $p;
			}
		}
		return $out;
	}

	public static function provider( $id ) {
		$all = self::providers();
		return $all[ $id ] ?? null;
	}

	public static function provider_for_gateway( $gateway ) {
		foreach ( self::providers() as $p ) {
			if ( in_array( (string) $gateway, (array) $p['gateways'], true ) ) {
				return $p;
			}
		}
		return null;
	}

	private static function meta_of( $row ) {
		$m = json_decode( (string) ( $row['meta'] ?? '' ), true );
		return is_array( $m ) ? $m : array();
	}

	/**
	 * Aboneliğin kayıtlı kartı (sağlayıcısı hâlâ yüklüyse).
	 *
	 * @param array $row Ham satır.
	 * @return array|null { p, d, label, gateway }
	 */
	public static function card( $row ) {
		$c = self::meta_of( $row )[ self::META ] ?? null;
		if ( ! is_array( $c ) || empty( $c['p'] ) || ! self::provider( $c['p'] ) ) {
			return null;
		}
		return $c;
	}

	public static function label( $row ) {
		$c = self::meta_of( $row )[ self::META ] ?? null;
		return is_array( $c ) ? (string) ( $c['label'] ?? '' ) : '';
	}

	/** İlk sipariş ya da elle ödenen yenileme kartla ödendiyse kartı sakla. */
	public static function capture( $sub, $order ) {
		if ( ! $order instanceof WC_Order || empty( $sub['id'] ) ) {
			return;
		}
		$p = self::provider_for_gateway( $order->get_payment_method() );
		if ( ! $p ) {
			return;
		}
		$card = call_user_func( $p['card'], $order );
		if ( ! is_array( $card ) || empty( $card['data'] ) ) {
			return;
		}
		FLXCM_Subscriptions::update_meta(
			(int) $sub['id'],
			self::META,
			array(
				'p'       => $p['id'],
				'd'       => $card['data'],
				'label'   => sanitize_text_field( (string) ( $card['label'] ?? '' ) ),
				'gateway' => $order->get_payment_method(),
				'at'      => time(),
			)
		);
		/* translators: %s: card */
		$order->add_order_note( sprintf( __( 'Card stored for automatic subscription renewals: %s.', 'flx-commerce' ), '' !== (string) ( $card['label'] ?? '' ) ? $card['label'] : $p['label'] ) );
	}

	public static function forget( $id ) {
		FLXCM_Subscriptions::update_meta( (int) $id, self::META, null );
	}

	public static function on_end( $sub ) {
		if ( ! empty( $sub['id'] ) ) {
			self::forget( (int) $sub['id'] );
		}
	}

	/** Tekrar deneme aralıkları (gün): varsayılan dönem sonu + 1. gün + 2. gün (toplam 3 deneme); son başarısızlıkta ek süreden (varsayılan 3 gün) en az 1 gün kalır. */
	public static function delays() {
		$d = array_values( array_filter( array_map( 'absint', (array) apply_filters( 'flxcm_card_retry_days', array( 1, 1 ) ) ) ) );
		return $d;
	}

	/** Bu yenileme siparişinde otomatik çekim sürüyor mu (son deneme başarısız olmadıysa). */
	public static function auto_pending( WC_Order $order ) {
		return (bool) $order->get_meta( '_flxcm_card_auto' ) && ! (int) $order->get_meta( '_flxcm_charge_failed' );
	}

	/**
	 * Yenileme siparişinden kartı çeker. Tick bu fonksiyonu dönem bitince ve tekrar deneme zamanlarında çağırır.
	 *
	 * @return string paid | failed | retry | uncertain | skipped
	 */
	public static function charge( array $row, $force = false ) {
		$card  = self::card( $row );
		$order = (int) $row['renewal_order_id'] ? wc_get_order( (int) $row['renewal_order_id'] ) : null;
		if ( ! $card || ! $order || $order->is_paid() || ! $order->has_status( array( 'pending', 'failed' ) ) || ! self::auto_pending( $order ) ) {
			return 'skipped';
		}
		$next = (int) $order->get_meta( '_flxcm_charge_next' );
		$next = $next ? $next : (int) strtotime( $row['period_end'] . ' UTC' );
		if ( ! $force && time() < $next ) {
			return 'skipped';
		}
		$p   = self::provider( $card['p'] );
		$sub = FLXCM_Subscriptions::export( $row );
		try {
			$r = call_user_func( $p['charge'], $order, (array) $card['d'], $sub );
		} catch ( Throwable $e ) {
			$r = array( 'result' => 'uncertain', 'error' => $e->getMessage() );
		}
		$r      = is_array( $r ) ? $r : array( 'result' => true === $r ? 'paid' : 'failed' );
		$result = (string) ( $r['result'] ?? 'failed' );
		$error  = sanitize_text_field( (string) ( $r['error'] ?? '' ) );
		$order  = wc_get_order( $order->get_id() ); // Sağlayıcı meta yazmış olabilir.

		if ( 'paid' === $result ) {
			$order->delete_meta_data( '_flxcm_charge_next' );
			$order->save_meta_data();
			/* translators: 1: card, 2: transaction id */
			$order->add_order_note( sprintf( __( 'Subscription renewal charged to the stored card (%1$s), transaction %2$s.', 'flx-commerce' ), '' !== $card['label'] ? $card['label'] : $p['label'], '' !== (string) ( $r['txn'] ?? '' ) ? $r['txn'] : '-' ) );
			$order->payment_complete( sanitize_text_field( (string) ( $r['txn'] ?? '' ) ) );
			return 'paid';
		}
		if ( 'uncertain' === $result ) {
			// Cevap gelmedi: deneme sayılmaz, sağlayıcı bir sonraki çalışmada önce durumu sorar.
			$order->update_meta_data( '_flxcm_charge_next', time() + HOUR_IN_SECONDS );
			$order->save_meta_data();
			return 'uncertain';
		}
		$attempt = (int) $order->get_meta( '_flxcm_charge_attempts' ) + 1;
		$delays  = self::delays();
		$order->update_meta_data( '_flxcm_charge_attempts', $attempt );
		if ( $attempt > count( $delays ) ) {
			$order->update_meta_data( '_flxcm_charge_failed', time() );
			$order->delete_meta_data( '_flxcm_charge_next' );
			$order->save_meta_data();
			/* translators: %s: error */
			$order->add_order_note( sprintf( __( 'Automatic card charge failed after the last attempt: %s. The customer was sent the payment link.', 'flx-commerce' ), '' !== $error ? $error : '-' ) );
			FLXCM_Subscriptions::mail( FLXCM_Subscriptions::export( (int) $row['id'] ), 'card_failed', $order );
			do_action( 'flxcm_subscription_charge_failed', FLXCM_Subscriptions::export( (int) $row['id'] ), $order, $error );
			return 'failed';
		}
		$order->update_meta_data( '_flxcm_charge_next', time() + $delays[ $attempt - 1 ] * DAY_IN_SECONDS );
		$order->save_meta_data();
		/* translators: 1: error, 2: days */
		$order->add_order_note( sprintf( __( 'Automatic card charge failed: %1$s. Retrying in %2$d day(s).', 'flx-commerce' ), '' !== $error ? $error : '-', $delays[ $attempt - 1 ] ) );
		return 'retry';
	}

	/** Tick: zamanı gelen otomatik çekimler. */
	public static function run() {
		global $wpdb;
		$t   = FLXCM_Subscriptions::table();
		$now = gmdate( 'Y-m-d H:i:s' );
		// phpcs:ignore WordPress.DB
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE status IN ('active','past_due') AND renewal_order_id > 0 AND period_end <= %s AND meta LIKE %s ORDER BY period_end ASC LIMIT 25", $now, '%"' . self::META . '"%' ), ARRAY_A );
		foreach ( $rows as $row ) {
			self::charge( $row );
		}
	}
}

/** Sipariş abonelik satırı içeriyor mu (ilk sipariş ya da yenileme). Kart sağlayıcıları kartı saklamak için bakar. */
function flxcm_order_has_subscription( $order ) {
	$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
	if ( ! $order ) {
		return false;
	}
	foreach ( $order->get_items() as $item ) {
		if ( '' !== (string) $item->get_meta( '_flxcm_period' ) ) {
			return true;
		}
	}
	return false;
}

/** Sepette abonelik var mı. */
function flxcm_cart_has_subscription() {
	return FLXCM_Sub_Product::cart_has_sub();
}
