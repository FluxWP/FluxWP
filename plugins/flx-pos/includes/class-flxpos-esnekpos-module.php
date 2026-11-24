<?php
/**
 * EsnekPOS (Hoppa) resmi WooCommerce modülü (yöntem `esnekpospos`, sınıf WC_Gateway_Esnekpospos) Flux POS üzerinden.
 *
 * Modülün ayar ekranı ve yöntem listesindeki yeri kalır; ödemeyi Flux POS yürütür:
 *  - Modül ayarlarından (üye işyeri, anahtar) sanal bir hesap kurulur: esnekpos_module. TEST1234 test servisidir.
 *  - Sipariş ödeme sayfası (order-pay) doğrudan Flux POS köprüsüne gider; köprü EsnekPOS ortak ödeme sayfasını
 *    açar. Sepetsiz çalışır (ödeme bağlantıları).
 *  - Dönüşte gönderilen alanlara güvenilmez: ProcessQuery ile sunucudan sorulur, referans ve tutar eşleşmeli.
 *  - Modülün kendi dönüş denetimi (her `init`te) ve sepet isteyen makbuz sayfası kaldırılır.
 *  - Panelde EsnekPOS hesabı açılmışsa (kart yöntemi) modül yine çalışır; tek kart kuralı hangisinin görüneceğini seçer.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

final class FLXPOS_Esnekpos_Module {

	const ID      = 'esnekpospos';
	const MODULE  = 'WC_Gateway_Esnekpospos';
	const ACCOUNT = 'esnekpos_module';

	public static function init() {
		add_filter( 'flxpos_virtual_accounts', array( __CLASS__, 'account' ) );
		add_action( 'init', array( __CLASS__, 'unhook_module' ), 9 );
		add_action( 'init', array( __CLASS__, 'unhook_module' ), 10 );
		add_action( 'wc_payment_gateways_initialized', array( __CLASS__, 'unhook_module' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_start' ), 5 );
		add_action( 'woocommerce_api_' . strtolower( self::MODULE ), array( __CLASS__, 'old_return' ), 1 );
		add_filter( 'woocommerce_settings_api_form_fields_' . self::ID, array( __CLASS__, 'form_fields' ) );
		add_filter( 'flxpos_gateway_adapters', array( __CLASS__, 'adapter' ) );
	}

	public static function present() {
		return class_exists( self::MODULE );
	}

	public static function settings() {
		return (array) get_option( 'woocommerce_' . self::ID . '_settings', array() );
	}

	public static function enabled() {
		$s = self::settings();
		return self::present() && 'yes' === ( $s['enabled'] ?? 'no' );
	}

	/** Modül ayarlarından sanal hesap. */
	public static function account( $list ) {
		if ( ! self::present() ) {
			return $list;
		}
		$s        = self::settings();
		$merchant = trim( (string) ( $s['esnekpos_username'] ?? '' ) );
		$key      = trim( (string) ( $s['esnekpos_password'] ?? '' ) );
		if ( '' === $merchant || '' === $key ) {
			return $list;
		}
		$list[ self::ACCOUNT ] = array(
			'provider' => 'esnekpos',
			'label'    => __( 'EsnekPOS (resmi modül)', 'flx-pos' ),
			'enabled'  => self::enabled() ? 1 : 0,
			'test'     => 'TEST1234' === $merchant ? 1 : 0,
			'mode'     => 'hosted',
			'creds'    => array(
				'merchant'     => $merchant,
				'merchant_key' => $key,
			),
		);
		return $list;
	}

	/** Modülün kendi işleyicilerini kaldır: her istekteki dönüş denetimi, sepet isteyen makbuz sayfası. */
	public static function unhook_module() {
		global $wp_filter;
		foreach ( array( 'init', 'woocommerce_receipt_' . self::ID, 'woocommerce_api_' . strtolower( self::MODULE ) ) as $hook ) {
			if ( empty( $wp_filter[ $hook ] ) || ! is_object( $wp_filter[ $hook ] ) ) {
				continue;
			}
			foreach ( (array) $wp_filter[ $hook ]->callbacks as $prio => $cbs ) {
				foreach ( (array) $cbs as $cb ) {
					$f = $cb['function'] ?? null;
					if ( is_array( $f ) && isset( $f[0] ) && is_object( $f[0] ) && is_a( $f[0], self::MODULE ) ) {
						remove_action( $hook, $f, $prio );
					}
				}
			}
		}
	}

	public static function form_fields( $fields ) {
		if ( isset( $fields['esnekpos_password'] ) ) {
			$fields['esnekpos_password']['type'] = 'password';
		}
		if ( isset( $fields['esnekpos_tdmode'] ) ) {
			$fields['esnekpos_tdmode']['description'] = __( 'Flux POS her zaman EsnekPOS güvenli ödeme sayfasını kullanır; gerekiyorsa banka 3D Secure doğrulamasını orada ister.', 'flx-pos' );
		}
		return array(
			'flxpos_note' => array(
				'title'       => 'Flux POS',
				'type'        => 'title',
				'description' => __( 'Bu ödeme yöntemini Flux POS yürütür: klasik ve blok ödeme, sepetsiz ödeme bağlantıları; her ödeme sipariş "ödendi" olmadan önce EsnekPOS\'a sunucudan sorulur. Test için üye işyeri TEST1234 ve EsnekPOS geliştirici sayfasındaki test anahtarı.', 'flx-pos' ),
			),
		) + (array) $fields;
	}

	/** EsnekPOS siparişinin ödeme sayfası (ödeme adımından sonra): Flux POS köprüsüne. */
	public static function maybe_start() {
		global $wp;
		self::unhook_module();
		if ( empty( $wp->query_vars['order-pay'] ) || isset( $_GET['pay_for_order'] ) || ! self::present() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$order = wc_get_order( absint( $wp->query_vars['order-pay'] ) );
		$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $order || self::ID !== $order->get_payment_method() || '' === $key || ! hash_equals( $order->get_order_key(), $key ) ) {
			return;
		}
		if ( ! $order->needs_payment() ) {
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}
		$a = FLXPOS_Accounts::get( self::ACCOUNT );
		$r = $a ? FLXPOS_Gateways::start_order( $order, $a, null ) : new WP_Error( 'flxpos_esnek', __( 'EsnekPOS üye işyeri bilgileri eksik.', 'flx-pos' ) );
		if ( is_wp_error( $r ) ) {
			wp_safe_redirect( add_query_arg( FLXPOS_Flow::ERR, rawurlencode( $r->get_error_message() ), $order->get_checkout_payment_url() ) );
			exit;
		}
		nocache_headers();
		wp_safe_redirect( $r['redirect'] );
		exit;
	}

	/** Modülün eski dönüş adresi: güvenilmez, ödeme sayfasına geri gönder (asıl sonuç Flux POS dönüşünde). */
	public static function old_return() {
		$id    = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = $id ? wc_get_order( $id ) : null;
		wp_safe_redirect( $order ? ( $order->is_paid() ? $order->get_checkout_order_received_url() : $order->get_checkout_payment_url() ) : home_url( '/' ) );
		exit;
	}

	public static function logo_url() {
		if ( ! self::present() ) {
			return '';
		}
		$file = dirname( ( new ReflectionClass( self::MODULE ) )->getFileName() ) . '/img/logo.png';
		return file_exists( $file ) ? plugin_dir_url( $file ) . 'logo.png' : '';
	}

	public static function adapter( $a ) {
		if ( ! self::present() ) {
			return $a;
		}
		$acc           = FLXPOS_Accounts::get( self::ACCOUNT );
		$a[ self::ID ] = array(
			'label' => 'EsnekPOS',
			'logo'  => self::logo_url(),
			'note'  => $acc && $acc['test'] ? __( 'Test modu (üye işyeri TEST1234).', 'flx-pos' ) : '',
			'caps'  => array(
				'no_cart'      => array( 'flux', __( 'Flux POS sipariş satırlarını gönderir, sepet gerekmez.', 'flx-pos' ) ),
				'verified'     => array( 'flux', __( 'Flux POS sipariş "ödendi" olmadan önce EsnekPOS\'a sorar (ProcessQuery).', 'flx-pos' ) ),
				'installments' => array( 'page', __( 'EsnekPOS ödeme sayfasında, EsnekPOS panelindeki ayara göre.', 'flx-pos' ) ),
				'tokenization' => array( 'no', '' ),
				'refunds'      => array( 'no', __( 'İadeyi EsnekPOS panelinden yapın, sonra siparişi iade edildi olarak işaretleyin.', 'flx-pos' ) ),
			),
		);
		return $a;
	}
}
