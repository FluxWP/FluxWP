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
		$this->method_title       = __( 'Bank transfer (with receipt upload)', 'flx-commerce' );
		$this->method_description = __( 'The customer pays with an order-specific reference and uploads the receipt on the site. When you confirm the payment the order moves on to processing. Flux Commerce.', 'flx-commerce' );
		$this->supports           = array( 'products' );
		$this->init_form_fields();
		$this->init_settings();
		$this->title       = $this->get_option( 'title' ) ? $this->get_option( 'title' ) : __( 'Bank transfer', 'flx-commerce' );
		$this->description = $this->get_option( 'description' ) ? $this->get_option( 'description' ) : FLXCM_Bank::default_description();
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enabled', 'flx-commerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Accept payments by bank transfer', 'flx-commerce' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'       => __( 'Title', 'flx-commerce' ),
				'type'        => 'text',
				'description' => __( 'Empty = "Bank transfer" in the site language.', 'flx-commerce' ),
				'default'     => '',
				'placeholder' => __( 'Bank transfer', 'flx-commerce' ),
			),
			'description' => array(
				'title'       => __( 'Text on the checkout page', 'flx-commerce' ),
				'type'        => 'textarea',
				'description' => __( 'Empty = the default text in the site language.', 'flx-commerce' ),
				'default'     => '',
				'placeholder' => FLXCM_Bank::default_description(),
			),
			'holder'      => array(
				'title'       => __( 'Recipient (account holder)', 'flx-commerce' ),
				'type'        => 'text',
				'description' => __( 'Empty = the first account of WooCommerce\'s "Direct bank transfer".', 'flx-commerce' ),
				'default'     => '',
			),
			'iban'        => array(
				'title'       => 'IBAN',
				'type'        => 'text',
				'description' => __( 'Empty = the first account of WooCommerce\'s "Direct bank transfer" (recipient and bank come from it too).', 'flx-commerce' ),
				'default'     => '',
			),
			'bank'        => array(
				'title'   => __( 'Bank (optional)', 'flx-commerce' ),
				'type'    => 'text',
				'default' => '',
			),
			'ref_format'  => array(
				'title'       => __( 'Reference', 'flx-commerce' ),
				'type'        => 'text',
				'description' => __( 'What the customer writes in the transfer description. Placeholders: {kod} (prefix + order no), {siparis}, {ad}, {site}. Empty: {kod} {ad}', 'flx-commerce' ),
				'default'     => '',
				'placeholder' => '{kod} {ad}',
			),
			'ref_prefix'  => array(
				'title'   => __( 'Code prefix', 'flx-commerce' ),
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
		$order->update_status( 'on-hold', __( 'Bank transfer: awaiting payment.', 'flx-commerce' ) );
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
