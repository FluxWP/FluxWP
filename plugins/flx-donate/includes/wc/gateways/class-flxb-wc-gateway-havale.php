<?php
/**
 * Flux Donate 2.0: bank transfer gateway class (id flxb_havale).
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLXB_WC_Gateway_Havale extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = FLXB_WC_Havale::ID;
		$this->has_fields         = false;
		$this->method_title       = __( 'Bank transfer (donation)', 'flx-donate' );
		$this->method_description = __( 'The donor transfers the money with an order-specific reference and uploads the receipt on the site. The certificate is issued when you confirm the payment.', 'flx-donate' );
		$this->supports           = array( 'products' );
		$this->init_form_fields();
		$this->init_settings();
		$this->title       = $this->get_option( 'title' ) ? $this->get_option( 'title' ) : __( 'Bank transfer', 'flx-donate' );
		$this->description = $this->get_option( 'description' ) ? $this->get_option( 'description' ) : FLXB_WC_Havale::default_description();
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enabled', 'flx-donate' ),
				'type'    => 'checkbox',
				'label'   => __( 'Take donations by bank transfer', 'flx-donate' ),
				'default' => 'yes',
			),
			'title'       => array(
				'title'       => __( 'Title', 'flx-donate' ),
				'type'        => 'text',
				'description' => __( 'Empty = "Bank transfer" in the site language.', 'flx-donate' ),
				'default'     => '',
				'placeholder' => __( 'Bank transfer', 'flx-donate' ),
			),
			'description' => array(
				'title'       => __( 'Text on the checkout page', 'flx-donate' ),
				'type'        => 'textarea',
				'description' => __( 'Empty = the default text in the site language.', 'flx-donate' ),
				'default'     => '',
				'placeholder' => FLXB_WC_Havale::default_description(),
			),
			'holder'      => array(
				'title'       => __( 'Recipient (account holder)', 'flx-donate' ),
				'type'        => 'text',
				'description' => __( 'Empty = the holder of the bank account below or, without an IBAN here, of WooCommerce\'s "Direct bank transfer" account (else the theme settings).', 'flx-donate' ),
				'default'     => '',
			),
			'iban'        => array(
				'title'       => 'IBAN',
				'type'        => 'text',
				'description' => __( 'The account shown everywhere (donation page, checkout, call center). Empty = the first account of WooCommerce\'s "Direct bank transfer", else the theme settings; recipient and bank then come from that account too.', 'flx-donate' ),
				'default'     => '',
			),
			'bank'        => array(
				'title'   => __( 'Bank (optional)', 'flx-donate' ),
				'type'    => 'text',
				'default' => '',
			),
			'ref_format'  => array(
				'title'       => __( 'Reference', 'flx-donate' ),
				'type'        => 'text',
				'description' => __( 'What the donor writes in the transfer description. Placeholders: {tur} (sapling donation / donation), {kod} (prefix + order no), {siparis}, {ad}. Empty: {tur} {kod} {ad}', 'flx-donate' ),
				'default'     => '',
				'placeholder' => '{tur} {kod} {ad}',
			),
			'ref_prefix'  => array(
				'title'   => __( 'Code prefix', 'flx-donate' ),
				'type'    => 'text',
				'default' => 'FluxWP',
			),
		);
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array( 'result' => 'failure' );
		}
		FLXB_WC_Havale::reference( $order );
		$order->update_meta_data( '_flxb_state', 'awaiting' );
		$order->update_status( 'on-hold', __( 'Bank transfer pre-registration: waiting for the payment.', 'flx-donate' ) );
		wc_maybe_reduce_stock_levels( $order_id );
		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}
		do_action( 'flxb_wc_preregistered', $order );
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}
