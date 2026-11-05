<?php
/**
 * Sitedeki tüm WooCommerce ödeme yöntemleri: yetenek tablosu, tek kart kuralı, blok ödeme yedeği, ödeme logoları.
 *
 *  - Yetenekler WooCommerce'in kendisinden (supports(), process_refund, blok kaydı) ve bağdaştırıcılardan gelir.
 *    Bağdaştırıcı bir eklenti için kısa tanımdır; `flxpos_gateway_adapters` filtresiyle eklenir:
 *
 *        add_filter( 'flxpos_gateway_adapters', function ( $a ) {
 *            $a['my_pos'] = array(
 *                'label' => 'My POS',
 *                'logo'  => 'https://…/logo.svg',
 *                'caps'  => array( 'installments' => array( 'page', 'Sağlayıcının sayfasında' ) ),
 *            );
 *            return $a;
 *        } );
 *
 *    Durumlar: yes | no | page (sağlayıcının sayfasında) | flux (Flux POS yapar) | unknown.
 *  - Tek kart yöntemi (ayar card_method): auto | flxpos | <yöntem id> | all. Birden çok kartlı POS açıkken ödeyen
 *    yalnız birini görür. Atlamak için `flxpos_one_card_skip` (ör. Flux Donate aylık bağış iyzico ile).
 *  - Blok ödeme: kendi blok desteği olmayan ve kartı kendi sayfasında alan (has_fields = false) yöntemler
 *    blok ödemeye eklenir (ayar block_fallback).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

final class FLXPOS_Methods {

	/** Bizim eklediğimiz blok yöntemleri (id => başlık). */
	private static $fallback = array();

	public static function init() {
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( __CLASS__, 'blocks' ), 99 );
		add_filter( 'flx_pay_logos_builtin', array( __CLASS__, 'pay_logos' ), 20 );
		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'one_card' ), 60 );
	}

	/** @return WC_Payment_Gateway[] */
	public static function all() {
		return function_exists( 'WC' ) && WC()->payment_gateways() ? (array) WC()->payment_gateways()->payment_gateways() : array();
	}

	public static function adapters() {
		return (array) apply_filters( 'flxpos_gateway_adapters', array() );
	}

	/** Kartsız yöntemler (havale, kapıda ödeme, cüzdanlarımız…). */
	public static function non_card() {
		$ids = array( 'bacs', 'cheque', 'cod', 'flxb_havale', 'flxcm_bank' );
		foreach ( array_keys( FLXPOS_Accounts::wallets() ) as $id ) {
			$ids[] = 'flxpos_' . $id;
		}
		return (array) apply_filters( 'flxpos_non_card_gateways', $ids );
	}

	public static function is_card( $id ) {
		return ! in_array( $id, self::non_card(), true );
	}

	/** Etkin kartlı yöntemler (bizimki hariç), id => ad. */
	public static function third_party_cards() {
		$out = array();
		foreach ( self::all() as $id => $gw ) {
			if ( 'flxpos_card' !== $id && 'yes' === $gw->enabled && self::is_card( $id ) ) {
				$out[ $id ] = wp_strip_all_tags( (string) $gw->get_method_title() );
			}
		}
		return $out;
	}

	/**
	 * Ödeyenin gördüğü kart yöntemi: 'flxpos', bir yöntem id'si ya da 'all'.
	 * Otomatik: Flux POS kart hesabı varsa o; yoksa ilk etkin kartlı yöntem (iyzico resmi eklentisi en sonda).
	 */
	public static function card_method() {
		$set   = (string) flxpos_setting( 'card_method' );
		$third = self::third_party_cards();
		if ( 'all' === $set || ( 'flxpos' === $set ) ) {
			return $set;
		}
		if ( '' !== $set && 'auto' !== $set && isset( $third[ $set ] ) ) {
			return $set;
		}
		if ( FLXPOS_Accounts::for_card( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'TRY' ) ) {
			return 'flxpos';
		}
		if ( ! $third ) {
			return 'all';
		}
		$ids = array_keys( $third );
		usort(
			$ids,
			static function ( $a, $b ) {
				return (int) ( 'iyzico' === $a ) - (int) ( 'iyzico' === $b );
			}
		);
		return (string) $ids[0];
	}

	/** Bu yöntem tek kart kuralıyla gizleniyor mu? */
	public static function hidden( $id ) {
		$keep = self::card_method();
		if ( 'all' === $keep || ! self::is_card( $id ) ) {
			return false;
		}
		return ( 'flxpos' === $keep ? 'flxpos_card' : $keep ) !== $id;
	}

	/** Ödeme sayfasında tek kart yöntemi. */
	public static function one_card( $gateways ) {
		if ( ! is_array( $gateways ) || ( is_admin() && ! wp_doing_ajax() ) || apply_filters( 'flxpos_one_card_skip', false, $gateways ) ) {
			return $gateways;
		}
		if ( 'all' === self::card_method() ) {
			return $gateways;
		}
		global $wp;
		$own = '';
		if ( ! empty( $wp->query_vars['order-pay'] ) ) {
			$o   = wc_get_order( absint( $wp->query_vars['order-pay'] ) );
			$own = $o ? (string) $o->get_payment_method() : ''; // var olan sipariş kendi yöntemini korur
		}
		foreach ( array_keys( $gateways ) as $id ) {
			if ( $id !== $own && self::hidden( $id ) ) {
				unset( $gateways[ $id ] );
			}
		}
		return $gateways;
	}

	public static function cap_labels() {
		return array(
			'blocks'       => __( 'Blok ödeme', 'flx-pos' ),
			'no_cart'      => __( 'Ödeme bağlantısı', 'flx-pos' ),
			'verified'     => __( 'Sunucu doğrulaması', 'flx-pos' ),
			'installments' => __( 'Taksit', 'flx-pos' ),
			'tokenization' => __( 'Kayıtlı kart', 'flx-pos' ),
			'refunds'      => __( 'Siteden iade', 'flx-pos' ),
		);
	}

	public static function cap_help() {
		return array(
			'blocks'       => __( 'Ödeme bloğuyla yapılmış ödeme sayfasında görünür. Klasik ödeme ([woocommerce_checkout]) tüm yöntemleri gösterir.', 'flx-pos' ),
			'no_cart'      => __( 'Var olan bir siparişi sepetsiz, ödeme bağlantısından öder (çağrı merkezi, "tekrar öde").', 'flx-pos' ),
			'verified'     => __( 'Sipariş "ödendi" olmadan önce ödeme sağlayıcıyla sunucudan doğrulanır.', 'flx-pos' ),
			'installments' => __( 'Ödeyen taksitle ödeyebilir.', 'flx-pos' ),
			'tokenization' => __( 'Ödeyen kartını sonraki ödeme için kaydedebilir.', 'flx-pos' ),
			'refunds'      => __( 'Siparişteki İade düğmesi iadeyi sağlayıcıya gönderir.', 'flx-pos' ),
		);
	}

	private static function overrides( $gw, $method ) {
		if ( ! method_exists( $gw, $method ) ) {
			return false;
		}
		try {
			$m = new ReflectionMethod( $gw, $method );
			return ! in_array( $m->getDeclaringClass()->getName(), array( 'WC_Payment_Gateway', 'WC_Settings_API' ), true );
		} catch ( ReflectionException $e ) {
			return false;
		}
	}

	public static function block_ids() {
		try {
			if ( class_exists( '\Automattic\WooCommerce\Blocks\Package' ) ) {
				$reg = \Automattic\WooCommerce\Blocks\Package::container()->get( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry::class );
				return array_keys( (array) $reg->get_all_registered() );
			}
		} catch ( Throwable $e ) {
			return array();
		}
		return array();
	}

	public static function can_fallback( $gw ) {
		return $gw instanceof WC_Payment_Gateway && ! $gw->has_fields && 0 !== strpos( $gw->id, 'flxpos_' );
	}

	/** Kalan yönlendirmeli yöntemleri blok ödemeye ekle. */
	public static function blocks( $registry ) {
		if ( ! flxpos_setting( 'block_fallback' ) || ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}
		require_once FLXPOS_DIR . 'includes/class-flxpos-generic-blocks.php';
		foreach ( self::all() as $id => $gw ) {
			if ( 'yes' !== $gw->enabled || ! self::can_fallback( $gw ) || $registry->is_registered( $id ) ) {
				continue;
			}
			$registry->register( new FLXPOS_Generic_Blocks( $gw ) );
			self::$fallback[ $id ] = $gw->get_title();
		}
	}

	/**
	 * Bir yöntemin yetenekleri.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function caps( WC_Payment_Gateway $gw, $block_ids = null ) {
		$id        = $gw->id;
		$block_ids = is_array( $block_ids ) ? $block_ids : self::block_ids();
		$caps      = array();
		if ( in_array( $id, $block_ids, true ) ) {
			$caps['blocks'] = isset( self::$fallback[ $id ] ) ? array( 'flux', __( 'Flux POS ekledi (eklentinin blok desteği yok).', 'flx-pos' ) ) : array( 'yes', '' );
		} elseif ( self::can_fallback( $gw ) ) {
			$caps['blocks'] = flxpos_setting( 'block_fallback' )
				? array( 'flux', __( 'Yöntem açılınca Flux POS ekler.', 'flx-pos' ) )
				: array( 'no', __( '"Diğer ödeme eklentilerini blok ödemede göster" ayarını açın.', 'flx-pos' ) );
		} else {
			$caps['blocks'] = array( 'no', __( 'Eklenti kart alanlarını yalnız klasik ödemede gösteriyor. Klasik ödeme sayfası kullanın ya da sağlayıcıdan blok desteği isteyin.', 'flx-pos' ) );
		}
		$caps['no_cart']      = array( 'unknown', __( 'Çoğu eklenti çalışır; bir kez ödeme bağlantısıyla deneyin.', 'flx-pos' ) );
		$caps['verified']     = array( 'unknown', __( 'Eklentiye bağlı; sağlayıcıya sorun.', 'flx-pos' ) );
		$caps['installments'] = $gw->has_fields ? array( 'unknown', '' ) : array( 'page', __( 'Sağlayıcı sunuyorsa ödeme sayfasında.', 'flx-pos' ) );
		$caps['tokenization'] = $gw->supports( 'tokenization' ) ? array( 'yes', '' ) : array( 'no', '' );
		$caps['refunds']      = $gw->supports( 'refunds' ) && self::overrides( $gw, 'process_refund' )
			? array( 'yes', '' )
			: array( 'no', __( 'İadeyi sağlayıcının panelinden yapın, sonra siparişi iade edildi olarak işaretleyin.', 'flx-pos' ) );

		if ( $gw instanceof FLXPOS_WC_Base ) {
			$acc  = $gw->account( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'TRY' );
			$p    = $acc ? FLXPOS_Providers::get( $acc['provider'] ) : null;
			$pc   = $p ? (array) $p['caps'] : array();
			$caps = array_merge(
				$caps,
				array(
					'no_cart'      => array( 'yes', '' ),
					'verified'     => array( 'yes', __( 'Banka dönüşü imzayla ya da sunucu sorgusuyla doğrulanır, tutar eşleşmeli.', 'flx-pos' ) ),
					'installments' => $acc && $acc['installments'] ? array( 'yes', '' ) : array( 'no', __( 'Hesapta taksit tablosu boş.', 'flx-pos' ) ),
					'refunds'      => ! empty( $pc['refund'] ) ? array( 'yes', '' ) : array( 'no', __( 'Sağlayıcı siteden iadeyi desteklemiyor.', 'flx-pos' ) ),
				)
			);
		} elseif ( in_array( $id, self::non_card(), true ) ) {
			$caps = array_merge(
				$caps,
				array(
					'no_cart'      => array( 'yes', '' ),
					'verified'     => array( 'page', __( 'Dekonttan sonra elle onaylanır.', 'flx-pos' ) ),
					'installments' => array( 'no', '' ),
				)
			);
		}
		$a = self::adapters()[ $id ] ?? null;
		if ( is_array( $a ) && ! empty( $a['caps'] ) ) {
			foreach ( (array) $a['caps'] as $k => $v ) {
				if ( isset( $caps[ $k ] ) && 'blocks' !== $k ) {
					$caps[ $k ] = array( (string) ( is_array( $v ) ? ( $v[0] ?? 'unknown' ) : $v ), (string) ( is_array( $v ) ? ( $v[1] ?? '' ) : '' ) );
				}
			}
		}
		return (array) apply_filters( 'flxpos_gateway_caps', $caps, $gw );
	}

	public static function block_checkout() {
		$id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'checkout' ) : 0;
		return $id > 0 && has_block( 'woocommerce/checkout', $id );
	}

	/** Alt bilgi ödeme logoları (FluxWP teması): bağdaştırıcı logoları; başka POS kartı alıyorsa iyzico şeridi varsayılan değil. */
	public static function pay_logos( $list ) {
		$gws = self::all();
		$cm  = self::card_method();
		if ( isset( $list['iyzico'] ) && ! in_array( $cm, array( 'iyzico', 'all' ), true ) ) {
			$list['iyzico']['default'] = false;
		}
		foreach ( self::adapters() as $id => $a ) {
			if ( ! isset( $gws[ $id ] ) || empty( $a['logo'] ) || isset( $list[ 'gw-' . $id ] ) ) {
				continue;
			}
			$list[ 'gw-' . $id ] = array(
				'label'   => (string) ( $a['label'] ?? $gws[ $id ]->get_method_title() ),
				'url'     => (string) $a['logo'],
				'default' => 'yes' === $gws[ $id ]->enabled && ( 'all' === $cm || $id === $cm ),
				'chip'    => true,
			);
		}
		return $list;
	}
}
