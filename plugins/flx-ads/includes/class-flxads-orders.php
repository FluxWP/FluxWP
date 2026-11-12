<?php
/**
 * Flux Ads: siparişin reklam atfı.
 *
 * Ödeme adımında atıf çerezi (flxads_t), pixel çerezleri (_fbp, _fbc, _ttp, _ga) ve WooCommerce'in kendi sipariş
 * atfı (UTM) siparişe `_flxads` olarak yazılır. Sipariş ödenince (kart: hemen; Havale/EFT: dekont onaylanınca,
 * Flux Donate payment_complete çağırır) atıf satırı kaydedilir ve açık platformlara sunucu dönüşümü kuyruğa girer.
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

final class FLXADS_Orders {

	public static function init() {
		if ( ! class_exists( 'WooCommerce' ) && ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'capture' ), 20 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'capture' ), 20 );
		/* Ödeme sayfası dışında ön yüzden açılan siparişler (ör. bağış formunun kendi akışı). */
		add_action(
			'woocommerce_new_order',
			static function ( $id ) {
				if ( ( ! is_admin() || wp_doing_ajax() ) && ( isset( $_COOKIE['flxads_ok'] ) || isset( $_COOKIE['flxads_t'] ) ) ) {
					self::capture( $id );
				}
			},
			20
		);
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'paid' ), 30 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'paid' ), 30 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'paid' ), 30 );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'refunded' ), 30 );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'refunded' ), 30 );
	}

	/** Olay kimliği: tarayıcı ve sunucu aynısını kullanır (Meta/TikTok çift sayımı eler). */
	public static function event_id( $order ) {
		return 'order-' . ( is_object( $order ) ? (int) $order->get_id() : (int) $order );
	}

	/** Siparişte Flux Donate bağış satırı var mı? */
	public static function is_donation( $order ) {
		foreach ( $order->get_items() as $item ) {
			$l = is_callable( array( $item, 'get_meta' ) ) ? $item->get_meta( '_flxb' ) : null;
			if ( is_array( $l ) && ! empty( $l['type_id'] ) ) {
				return (bool) apply_filters( 'flxads_is_donation', true, $order );
			}
		}
		return (bool) apply_filters( 'flxads_is_donation', false, $order );
	}

	public static function items( $order ) {
		$out = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$qty   = max( 1, (int) $item->get_quantity() );
			$out[] = array(
				'id'    => (string) $item->get_product_id(),
				'name'  => wp_strip_all_tags( $item->get_name() ),
				'price' => round( (float) $item->get_total() / $qty, 2 ),
				'qty'   => $qty,
			);
		}
		return array_slice( $out, 0, 20 );
	}

	/** Çerezden bir dokunuşu temizle. */
	private static function clean_touch( $t ) {
		$out = array();
		if ( ! is_array( $t ) ) {
			return $out;
		}
		foreach ( flxads_param_keys() as $k ) {
			if ( isset( $t[ $k ] ) && '' !== (string) $t[ $k ] ) {
				$out[ $k ] = mb_substr( sanitize_text_field( (string) $t[ $k ] ), 0, 150 );
			}
		}
		if ( ! empty( $t['lp'] ) ) {
			$out['lp'] = mb_substr( esc_url_raw( home_url( (string) $t['lp'] ) ), 0, 255 );
		}
		if ( ! empty( $t['ts'] ) ) {
			$out['ts'] = (int) $t['ts'];
		}
		return $out;
	}

	private static function cookie( $name, $max = 255 ) {
		return isset( $_COOKIE[ $name ] ) ? mb_substr( sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ), 0, $max ) : '';
	}

	/** Ödeme adımı: atıf bilgisini siparişe yaz. */
	public static function capture( $order ) {
		$order = is_object( $order ) ? $order : wc_get_order( $order );
		if ( ! $order || $order->get_meta( '_flxads' ) ) {
			return;
		}
		$raw = isset( $_COOKIE['flxads_t'] ) ? json_decode( wp_unslash( $_COOKIE['flxads_t'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$a   = array(
			'f' => self::clean_touch( $raw['f'] ?? array() ),
			'l' => self::clean_touch( $raw['l'] ?? array() ),
			'c' => ( '' !== self::cookie( 'flxads_ok', 4 ) || 'always' === flxads_setting( 'consent' ) ) ? 1 : 0,
		);
		foreach ( array( 'fbp' => '_fbp', 'fbc' => '_fbc', 'ttp' => '_ttp' ) as $k => $ck ) {
			$v = self::cookie( $ck );
			if ( '' !== $v && preg_match( '/^[A-Za-z0-9._\-]+$/', $v ) ) {
				$a[ $k ] = $v;
			}
		}
		/* _fbc yoksa son tıklamanın fbclid'inden Meta'nın beklediği biçimde türetilir. */
		if ( empty( $a['fbc'] ) && ! empty( $a['l']['fbclid'] ) ) {
			$a['fbc'] = 'fb.1.' . ( (int) ( $a['l']['ts'] ?? time() ) * 1000 ) . '.' . $a['l']['fbclid'];
		}
		$ga = self::cookie( '_ga' );
		if ( preg_match( '/^GA\d\.\d\.(\d+\.\d+)$/', $ga, $m ) ) {
			$a['ga'] = $m[1];
		}
		$order->update_meta_data( '_flxads', $a );
		$order->save_meta_data();
	}

	/**
	 * Siparişin atfı: son dokunuş (yoksa WooCommerce sipariş atfının UTM'leri) ve ilk dokunuş.
	 *
	 * @return array [ touch (son), first, meta ]
	 */
	public static function attribution( $order ) {
		$a = $order->get_meta( '_flxads' );
		$a = is_array( $a ) ? $a : array();
		$l = isset( $a['l'] ) && is_array( $a['l'] ) ? $a['l'] : array();
		$f = isset( $a['f'] ) && is_array( $a['f'] ) ? $a['f'] : array();
		if ( ! $l ) {
			foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' ) as $k ) {
				$v = (string) $order->get_meta( '_wc_order_attribution_' . $k );
				if ( '' !== $v && '(none)' !== $v ) {
					$l[ $k ] = mb_substr( $v, 0, 150 );
				}
			}
			$entry = (string) $order->get_meta( '_wc_order_attribution_session_entry' );
			if ( $l && '' !== $entry ) {
				$l['lp'] = mb_substr( esc_url_raw( $entry ), 0, 255 );
				$q       = wp_parse_url( $entry, PHP_URL_QUERY );
				if ( $q ) {
					parse_str( $q, $qs );
					foreach ( array( 'fbclid', 'gclid', 'gbraid', 'wbraid', 'ttclid', 'msclkid' ) as $k ) {
						if ( ! empty( $qs[ $k ] ) && is_string( $qs[ $k ] ) ) {
							$l[ $k ] = mb_substr( sanitize_text_field( $qs[ $k ] ), 0, 150 );
						}
					}
				}
			}
		}
		return array( $l, $f ? $f : $l, $a );
	}

	private static function click_of( array $t ) {
		foreach ( array( 'fbclid', 'gclid', 'gbraid', 'wbraid', 'ttclid', 'msclkid' ) as $k ) {
			if ( ! empty( $t[ $k ] ) ) {
				return $k;
			}
		}
		return '';
	}

	/** Sipariş ödendi: atıf satırı + dönüşüm kuyruğu (bir kez). */
	public static function paid( $order_id ) {
		global $wpdb;
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->is_paid() || $order->get_meta( '_flxads_done' ) || 'shop_order_refund' === $order->get_type() ) {
			return;
		}
		$order->update_meta_data( '_flxads_done', time() );
		$order->save_meta_data();

		list( $t, $f, $a ) = self::attribution( $order );
		$donation          = self::is_donation( $order );
		$email             = (string) $order->get_billing_email();
		$person            = function_exists( 'flx_person_find' ) ? (int) flx_person_find( array( 'email' => $email, 'phone' => (string) $order->get_billing_phone() ) ) : 0;
		$prev              = '' !== $email ? wc_get_orders(
			array(
				'billing_email' => $email,
				'status'        => array( 'processing', 'completed' ),
				'exclude'       => array( $order->get_id() ),
				'limit'         => 1,
				'return'        => 'ids',
			)
		) : array( 1 );
		$paid_at           = $order->get_date_paid() ? gmdate( 'Y-m-d H:i:s', $order->get_date_paid()->getTimestamp() ) : flxads_now();
		$row               = array(
			'order_id'    => $order->get_id(),
			'paid_at'     => $paid_at,
			'total'       => round( (float) $order->get_total(), 2 ),
			'currency'    => $order->get_currency(),
			'is_donation' => $donation ? 1 : 0,
			'person_id'   => $person,
			'new_person'  => $prev ? 0 : 1,
			'channel'     => flxads_channel( $t ),
			'source'      => mb_substr( strtolower( (string) ( $t['utm_source'] ?? '' ) ), 0, 100 ),
			'medium'      => mb_substr( strtolower( (string) ( $t['utm_medium'] ?? '' ) ), 0, 100 ),
			'campaign'    => mb_substr( (string) ( $t['utm_campaign'] ?? '' ), 0, 150 ),
			'content'     => mb_substr( (string) ( $t['utm_content'] ?? '' ), 0, 150 ),
			'term'        => mb_substr( (string) ( $t['utm_term'] ?? '' ), 0, 100 ),
			'click'       => self::click_of( $t ),
			'ft_channel'  => flxads_channel( $f ),
			'ft_campaign' => mb_substr( (string) ( $f['utm_campaign'] ?? '' ), 0, 150 ),
			'landing'     => mb_substr( (string) ( $t['lp'] ?? '' ), 0, 255 ),
			'status'      => 'paid',
		);
		$wpdb->replace( flxads_table( 'orders' ), $row ); // phpcs:ignore WordPress.DB

		if ( 'donations' !== flxads_setting( 'scope' ) || $donation ) {
			FLXADS_Send::queue( $order, ! empty( $a['c'] ) );
		}
		/** Atıf kaydedildi: CRM ve diğer paketler dinler. */
		do_action( 'flxads_order_attributed', $order->get_id(), $row, $order );
	}

	public static function refunded( $order_id ) {
		global $wpdb;
		$wpdb->update( flxads_table( 'orders' ), array( 'status' => 'refunded' ), array( 'order_id' => (int) $order_id ) ); // phpcs:ignore WordPress.DB
	}
}
