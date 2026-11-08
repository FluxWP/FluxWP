<?php
/**
 * Havale/EFT, ödeme bloğunda.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class FLXCM_Bank_Blocks extends AbstractPaymentMethodType {

	protected $name = 'flxcm_bank';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_flxcm_bank_settings', array() );
	}

	public function is_active() {
		return 'yes' === ( $this->settings['enabled'] ?? 'no' ) && ! FLXCM_Bank::deferred() && '' !== FLXCM_Bank::option( 'iban' );
	}

	public function get_payment_method_script_handles() {
		wp_register_script( 'flxcm-bank-blocks', FLXCM_URL . 'assets/bank-blocks.js', array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ), FLXCM_VERSION, true );
		return array( 'flxcm-bank-blocks' );
	}

	public function get_payment_method_data() {
		$title = trim( (string) ( $this->settings['title'] ?? '' ) );
		$desc  = trim( (string) ( $this->settings['description'] ?? '' ) );
		return array(
			'title'       => '' !== $title ? $title : __( 'Bank transfer', 'flx-commerce' ),
			'description' => '' !== $desc ? $desc : FLXCM_Bank::default_description(),
			'holder'      => FLXCM_Bank::option( 'holder' ),
			'iban'        => FLXCM_Bank::format_iban( FLXCM_Bank::option( 'iban' ) ),
			'supports'    => array( 'products' ),
		);
	}
}
