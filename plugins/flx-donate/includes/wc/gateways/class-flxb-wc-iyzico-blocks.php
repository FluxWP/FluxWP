<?php
/**
 * Flux Donate 2.0: Flux Donate iyzico card method in the checkout block. Which carts may use it
 * (monthly lines, official plugin present) is decided on the server: the Store API cart lists only
 * the gateways left by `woocommerce_available_payment_gateways`.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class FLXB_WC_Iyzico_Blocks extends AbstractPaymentMethodType {

	protected $name = 'flxb_iyzico';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_flxb_iyzico_settings', array() );
	}

	public function is_active() {
		return 'no' !== ( $this->settings['enabled'] ?? 'yes' ) && FLXB_WC_Iyzico::configured();
	}

	public function get_payment_method_script_handles() {
		if ( ! wp_script_is( 'flxb-card', 'registered' ) ) {
			FLXB_WC_Iyzico_Card::register_assets();
		}
		if ( FLXB_WC_Iyzico_Card::on() ) {
			wp_enqueue_style( 'flxb-card' );
		}
		wp_register_script( 'flxb-iyzico-blocks', FLXB_URL . 'assets/wc/iyzico-blocks.js', array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'flxb-card' ), FLXB_VERSION, true );
		return array( 'flxb-iyzico-blocks' );
	}

	public function get_payment_method_data() {
		$title = trim( (string) ( $this->settings['title'] ?? '' ) );
		$desc  = trim( (string) ( $this->settings['description'] ?? '' ) );
		$desc  = FLXB_WC_Iyzico_Card::on() && ( '' === $desc || FLXB_WC_Gateway_Iyzico::default_description() === $desc ) ? '' : $desc;
		return array(
			'title'       => '' !== $title ? $title : __( 'Credit / debit card (iyzico)', 'flx-donate' ),
			'description' => '' !== $desc || FLXB_WC_Iyzico_Card::on() ? $desc : __( 'You will pay on iyzico\'s secure page. For monthly donations your card is stored by iyzico and charged once a month; you can cancel at any time.', 'flx-donate' ),
			'fields'      => FLXB_WC_Iyzico_Card::on(),
			'supports'    => array( 'products' ),
		);
	}
}
