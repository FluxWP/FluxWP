<?php
/**
 * Flux Ads: ön yüz. Pixel'ler (Meta, TikTok, GA4) çerez onayından sonra yüklenir; URL'deki UTM ve tıklama
 * kimlikleri birinci taraf çereze (flxads_t: ilk + son dokunuş) yazılır; bağış formu, ödeme ve teşekkür sayfasında
 * ViewContent / InitiateCheckout / Purchase olayları sunucudakiyle aynı olay kimliğiyle gönderilir (çift sayım olmaz).
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

final class FLXADS_Front {

	/** @var array Bu sayfada tarayıcının göndereceği olaylar. */
	private static $events = array();

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'config' ), 5 );
	}

	/** Herhangi bir platform açık mı (atıf toplamak için en az biri ya da rapor isteniyor)? Rapor için her zaman açık. */
	private static function active() {
		return ! is_admin() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && (bool) apply_filters( 'flxads_front_active', true );
	}

	public static function enqueue() {
		if ( ! self::active() ) {
			return;
		}
		wp_enqueue_script( 'flxads-front', FLXADS_URL . 'assets/front.js', array(), FLXADS_VERSION, true );
		if ( function_exists( 'wp_script_add_data' ) ) {
			wp_script_add_data( 'flxads-front', 'strategy', 'defer' );
		}
	}

	/** Bir olay ekle (başka paketler de kullanabilir: flxads_track( 'Lead', [ 'value' => 0 ] )). */
	public static function add( $name, array $data = array() ) {
		self::$events[] = array_merge( array( 'name' => (string) $name ), $data );
	}

	public static function config() {
		if ( ! wp_script_is( 'flxads-front', 'enqueued' ) ) {
			return;
		}
		self::page_events();
		$s       = flxads_settings();
		$browser = ! empty( $s['browser'] );
		$theme   = function_exists( 'flx_cookie_active' );
		$cfg     = array(
			'consent' => 'always' === $s['consent'] ? 'always' : 'auto',
			'theme'   => $theme,
			'days'    => max( 1, min( 365, (int) $s['window_days'] ) ),
			'keys'    => flxads_param_keys(),
			'meta'    => $browser && flxads_platform_on( 'meta' ) ? (string) $s['meta_pixel'] : '',
			'tiktok'  => $browser && flxads_platform_on( 'tiktok' ) ? strtoupper( (string) $s['tiktok_pixel'] ) : '',
			'ga4'     => $browser && flxads_platform_on( 'ga4' ) ? flxads_ga4_id() : '',
			'loadGa'  => ! flxads_theme_loads_ga4(),
			'events'  => array_values( (array) apply_filters( 'flxads_browser_events', self::$events ) ),
		);
		wp_add_inline_script( 'flxads-front', 'window.flxAds=' . wp_json_encode( $cfg ) . ';', 'before' );
	}

	/** Sayfaya göre standart olaylar. */
	private static function page_events() {
		if ( ! function_exists( 'is_checkout' ) ) {
			return;
		}
		$cur = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			self::purchase_event();
			return;
		}
		if ( is_checkout() && WC()->cart && ! WC()->cart->is_empty() ) {
			self::add(
				'InitiateCheckout',
				array(
					'value'    => round( (float) WC()->cart->get_total( 'edit' ), 2 ),
					'currency' => $cur,
					'items'    => self::cart_items(),
				)
			);
			return;
		}
		/* Bağış formu (Flux Donate) ya da ürün sayfası: ViewContent. */
		if ( wp_script_is( 'flxwb-front', 'enqueued' ) || ( function_exists( 'is_product' ) && is_product() ) ) {
			self::add( 'ViewContent', array( 'currency' => $cur, 'title' => wp_strip_all_tags( get_the_title() ) ) );
		}
	}

	/** Teşekkür sayfası: sipariş ödenmişse ve daha önce tarayıcıdan gönderilmediyse Purchase. */
	private static function purchase_event() {
		global $wp;
		$id    = isset( $wp->query_vars['order-received'] ) ? absint( $wp->query_vars['order-received'] ) : 0;
		$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$order = $id ? wc_get_order( $id ) : null;
		if ( ! $order || ! hash_equals( (string) $order->get_order_key(), $key ) ) {
			return;
		}
		/* Havale/EFT gibi ödemesi bekleyen siparişte tarayıcıdan dönüşüm gitmez; onaylanınca sunucu gönderir. */
		if ( ! $order->is_paid() || $order->get_meta( '_flxads_browser' ) ) {
			return;
		}
		$order->update_meta_data( '_flxads_browser', time() );
		$order->save_meta_data();
		self::add(
			'Purchase',
			array(
				'id'       => FLXADS_Orders::event_id( $order ),
				'tx'       => (string) $order->get_order_number(),
				'value'    => round( (float) $order->get_total(), 2 ),
				'currency' => $order->get_currency(),
				'items'    => FLXADS_Orders::items( $order ),
			)
		);
	}

	private static function cart_items() {
		$out = array();
		foreach ( WC()->cart->get_cart() as $line ) {
			$p     = isset( $line['data'] ) && is_object( $line['data'] ) ? $line['data'] : null;
			$out[] = array(
				'id'    => (string) ( $line['product_id'] ?? '' ),
				'name'  => $p ? wp_strip_all_tags( $p->get_name() ) : '',
				'price' => round( (float) ( $line['line_total'] ?? 0 ) / max( 1, (int) ( $line['quantity'] ?? 1 ) ), 2 ),
				'qty'   => (int) ( $line['quantity'] ?? 1 ),
			);
		}
		return array_slice( $out, 0, 20 );
	}
}

/**
 * Başka paket bu sayfada tarayıcıdan bir olay gönderir (ör. form gönderildi: 'Lead').
 * Olay, çerez onayı varsa ve ilgili pixel açıksa gider.
 */
function flxads_track( $name, array $data = array() ) {
	FLXADS_Front::add( $name, $data );
}
