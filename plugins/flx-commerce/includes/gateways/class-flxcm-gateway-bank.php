<?php
/**
 * Havale/EFT ödeme yöntemi (id flxcm_bank).
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

class FLXCM_Gateway_Bank extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = FLXCM_Bank::ID;
		$this->icon               = '';
		$this->has_fields         = false;
		$this->method_title       = __( 'Havale/EFT (dekont yüklemeli)', 'flx-commerce' );
		$this->method_description = __( 'Müşteri siparişe özel referansla ödeme yapar ve dekontu sitede yükler. Ödemeyi onaylayınca sipariş hazırlanmaya geçer. Flux Commerce.', 'flx-commerce' );
		$this->supports           = array( 'products' );
		$this->init_form_fields();
		$this->init_settings();
		$this->title       = $this->get_option( 'title' ) ? $this->get_option( 'title' ) : __( 'Havale/EFT', 'flx-commerce' );
		$this->description = $this->get_option( 'description' ) ? $this->get_option( 'description' ) : FLXCM_Bank::default_description();
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Açık', 'flx-commerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Havale/EFT ile ödeme al', 'flx-commerce' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'       => __( 'Başlık', 'flx-commerce' ),
				'type'        => 'text',
				'description' => __( 'Boş = site dilinde "Havale/EFT".', 'flx-commerce' ),
				'default'     => '',
				'placeholder' => __( 'Havale/EFT', 'flx-commerce' ),
			),
			'description' => array(
				'title'       => __( 'Ödeme sayfasındaki metin', 'flx-commerce' ),
				'type'        => 'textarea',
				'description' => __( 'Boş = site dilinde varsayılan metin.', 'flx-commerce' ),
				'default'     => '',
				'placeholder' => FLXCM_Bank::default_description(),
			),
			'holder'      => array(
				'title'       => __( 'Alıcı (hesap sahibi)', 'flx-commerce' ),
				'type'        => 'text',
				'description' => __( 'Boş = WooCommerce "Doğrudan banka havalesi"nin ilk hesabı.', 'flx-commerce' ),
				'default'     => '',
			),
			'iban'        => array(
				'title'       => 'IBAN',
				'type'        => 'text',
				'description' => __( 'Boş = WooCommerce "Doğrudan banka havalesi"nin ilk hesabı (alıcı ve banka da oradan gelir).', 'flx-commerce' ),
				'default'     => '',
			),
			'bank'        => array(
				'title'   => __( 'Banka (isteğe bağlı)', 'flx-commerce' ),
				'type'    => 'text',
				'default' => '',
			),
			'ref_format'  => array(
				'title'       => __( 'Açıklama', 'flx-commerce' ),
				'type'        => 'text',
				'description' => __( 'Müşterinin havale açıklamasına yazacağı metin. Yer tutucular: {kod} (önek + sipariş no), {siparis}, {ad}, {site}. Boş: {kod} {ad}', 'flx-commerce' ),
				'default'     => '',
				'placeholder' => '{kod} {ad}',
			),
			'ref_prefix'  => array(
				'title'   => __( 'Kod öneki', 'flx-commerce' ),
				'type'    => 'text',
				'default' => 'SP',
			),
		);
	}

	public function is_available() {
		return parent::is_available() && ! FLXCM_Bank::deferred() && '' !== FLXCM_Bank::option( 'iban' );
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array( 'result' => 'failure' );
		}
		FLXCM_Bank::reference( $order );
		$order->update_status( 'on-hold', __( 'Havale/EFT: ödeme bekleniyor.', 'flx-commerce' ) );
		wc_maybe_reduce_stock_levels( $order_id );
		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}
		do_action( 'flxcm_bank_placed', $order );
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}
