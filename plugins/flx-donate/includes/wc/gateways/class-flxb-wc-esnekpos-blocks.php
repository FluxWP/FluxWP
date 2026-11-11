<?php
/**
 * Flux Donate 2.21.0: EsnekPOS on the checkout block with card fields (FLXB_WC_Esnekpos_Direct).
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class FLXB_WC_Esnekpos_Blocks extends AbstractPaymentMethodType {

	protected $name = 'esnekpospos';

	public function initialize() {
		$this->settings = FLXB_WC_Esnekpos::settings();
	}

	public function is_active() {
		return FLXB_WC_Esnekpos::enabled();
	}

	public function get_payment_method_script_handles() {
		$deps = array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-data', 'wp-html-entities' );
		if ( function_exists( 'flx_field_enqueue' ) ) {
			flx_field_enqueue(); // 2.23.0: the card fields use the shared FluxWP field look and icons
			$deps[] = 'flx-field';
		}
		wp_register_script( 'flxb-esnekpos-blocks', FLXB_URL . 'assets/wc/esnekpos-blocks.js', $deps, FLXB_VERSION, true );
		wp_enqueue_style( 'flxb-esnekpos', FLXB_URL . 'assets/wc/esnekpos.css', array(), FLXB_VERSION );
		return array( 'flxb-esnekpos-blocks' );
	}

	public function get_payment_method_data() {
		$gw = FLXB_WC_Gateways::all()[ FLXB_WC_Esnekpos::ID ] ?? null;
		return FLXB_WC_Esnekpos_Direct::script_data() + array(
			'description' => $gw ? wp_strip_all_tags( (string) $gw->get_description() ) : '',
			'supports'    => array( 'products' ),
		);
	}
}
