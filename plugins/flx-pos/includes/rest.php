<?php
/**
 * REST: flxpos/v1/installments (ödeme formu, herkese açık) ve flxpos/v1/test (panel, bağlantı testi).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

final class FLXPOS_Rest {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			'flxpos/v1',
			'/installments',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'installments' ),
				'args'                => array(
					'account' => array( 'type' => 'string', 'required' => true ),
					'amount'  => array( 'type' => 'number', 'required' => true ),
					'bin'     => array( 'type' => 'string', 'default' => '' ),
				),
			)
		);
		register_rest_route(
			'flxpos/v1',
			'/test/(?P<id>[a-z0-9_]+)',
			array(
				'methods'             => 'POST',
				'permission_callback' => static fn() => current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ),
				'callback'            => array( __CLASS__, 'test' ),
			)
		);
	}

	/** Taksit seçenekleri: yalnız etkin hesap, TRY; tutar yalnız gösterim içindir (siparişte yeniden hesaplanır). */
	public static function installments( WP_REST_Request $r ) {
		$id  = sanitize_key( (string) $r['account'] );
		$all = FLXPOS_Accounts::active();
		if ( ! isset( $all[ $id ] ) ) {
			return new WP_REST_Response( array( 'options' => array() ), 200 );
		}
		$a      = $all[ $id ];
		$amount = min( 10000000, max( 0, (float) $r['amount'] ) );
		$bin    = substr( preg_replace( '/\D/', '', (string) $r['bin'] ), 0, 8 );
		$cur    = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'TRY';
		$out    = array();
		foreach ( FLXPOS_Installments::options( $a, $amount, $cur, $bin ) as $n => $o ) {
			$money = static fn( $x ) => flxpos_money( $x, $cur );
			$out[] = array(
				'n'       => (int) $n,
				'total'   => $o['total'],
				'monthly' => $o['monthly'],
				'rate'    => $o['rate'],
				/* translators: 1: taksit sayısı, 2: aylık tutar, 3: toplam */
				'label'   => $n > 1 ? sprintf( __( '%1$s taksit × %2$s (toplam %3$s)', 'flx-pos' ), $n, $money( $o['monthly'] ), $money( $o['total'] ) ) : __( 'Tek çekim', 'flx-pos' ),
			);
		}
		$res = new WP_REST_Response( array( 'options' => $out ), 200 );
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	/** Bağlantı testi. */
	public static function test( WP_REST_Request $r ) {
		$drv = FLXPOS_Accounts::driver( sanitize_key( (string) $r['id'] ) );
		if ( is_wp_error( $drv ) ) {
			return array( 'ok' => false, 'message' => $drv->get_error_message() );
		}
		$t = $drv->test_connection();
		if ( is_wp_error( $t ) ) {
			return array( 'ok' => false, 'message' => $t->get_error_message() );
		}
		if ( null === $t ) {
			return array( 'ok' => null, 'message' => __( 'Bu sağlayıcıda bağlantı testi yok; test kartıyla küçük bir ödeme deneyin.', 'flx-pos' ) );
		}
		return array( 'ok' => true, 'message' => __( 'Bağlantı başarılı, anahtarlar kabul edildi.', 'flx-pos' ) );
	}
}
