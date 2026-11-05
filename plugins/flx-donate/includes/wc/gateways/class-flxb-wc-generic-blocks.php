<?php
/**
 * Flux Donate 2.13.0: a payment plugin without its own checkout block support, shown in the checkout
 * block. Only for methods that take the card on the provider's page (has_fields = false): the block
 * shows the title, icon and description, the order is placed through the Store API and WooCommerce
 * calls the plugin's own process_payment(), whose redirect the block follows.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class FLXB_WC_Generic_Blocks extends AbstractPaymentMethodType {

	/** @var WC_Payment_Gateway */
	private $gateway;

	/** Every method registered this way (for the one script). */
	private static $names = array();

	public function __construct( WC_Payment_Gateway $gateway ) {
		$this->gateway = $gateway;
		$this->name    = $gateway->id;
		self::$names[] = $gateway->id;
	}

	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . $this->name . '_settings', array() );
	}

	public function is_active() {
		return 'yes' === $this->gateway->enabled;
	}

	public function get_payment_method_script_handles() {
		if ( ! wp_script_is( 'flxb-generic-blocks', 'registered' ) ) {
			wp_register_script( 'flxb-generic-blocks', FLXB_URL . 'assets/wc/generic-blocks.js', array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ), FLXB_VERSION, true );
			wp_add_inline_script( 'flxb-generic-blocks', 'window.flxbGenericBlocks=' . wp_json_encode( array_values( array_unique( self::$names ) ) ) . ';', 'before' );
		}
		return array( 'flxb-generic-blocks' );
	}

	public function get_payment_method_data() {
		$g    = $this->gateway;
		$icon = (string) apply_filters( 'flxb_generic_blocks_icon', (string) ( $g->icon ?? '' ), $g );
		return array(
			'title'       => wp_strip_all_tags( (string) $g->get_title() ),
			'description' => wp_strip_all_tags( (string) $g->get_description() ),
			'icon'        => esc_url_raw( $icon ),
			'button'      => (string) ( $g->order_button_text ?? '' ),
			'supports'    => array_values( array_filter( (array) $g->supports, 'is_string' ) ),
		);
	}
}
