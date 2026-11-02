<?php
/**
 * Flux Donate 2.0: bank transfer method in the checkout block.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class FLXB_WC_Havale_Blocks extends AbstractPaymentMethodType {

	protected $name = 'flxb_havale';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_flxb_havale_settings', array() );
	}

	public function is_active() {
		return 'no' !== ( $this->settings['enabled'] ?? 'yes' );
	}

	public function get_payment_method_script_handles() {
		wp_register_script( 'flxwb-havale-blocks', FLXB_URL . 'assets/wc/havale-blocks.js', array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ), FLXB_VERSION, true );
		return array( 'flxwb-havale-blocks' );
	}

	public function get_payment_method_data() {
		$title = trim( (string) ( $this->settings['title'] ?? '' ) );
		$desc  = trim( (string) ( $this->settings['description'] ?? '' ) );
		return array(
			'title'       => '' !== $title ? $title : __( 'Bank transfer', 'flx-donate' ),
			'description' => '' !== $desc ? $desc : FLXB_WC_Havale::default_description(),
			'holder'      => FLXB_WC_Havale::option( 'holder' ),
			'iban'        => FLXB_WC_Havale::format_iban( FLXB_WC_Havale::option( 'iban' ) ),
			'supports'    => array( 'products' ),
		);
	}
}
