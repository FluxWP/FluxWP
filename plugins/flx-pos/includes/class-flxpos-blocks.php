<?php
/**
 * Blok ödeme (Checkout block) entegrasyonu: kart yöntemi kendi kart formuyla, cüzdanlar başlık + açıklamayla.
 * Kart alanları flxpos_* anahtarlarıyla gönderilir; WooCommerce bunları klasik yöntemin $_POST'u olarak verir.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class FLXPOS_Blocks extends AbstractPaymentMethodType {

	/** @var string[] Kayıtlı yöntemler (tek betik için). */
	private static $names = array();

	public function __construct( $gateway_id ) {
		$this->name    = (string) $gateway_id;
		self::$names[] = $this->name;
	}

	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . $this->name . '_settings', array() );
	}

	private function gateway() {
		$all = function_exists( 'WC' ) && WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		return $all[ $this->name ] ?? null;
	}

	public function is_active() {
		$g = $this->gateway();
		return $g && 'yes' === $g->enabled;
	}

	public function get_payment_method_script_handles() {
		if ( ! wp_script_is( 'flxpos-blocks', 'registered' ) ) {
			wp_register_style( 'flxpos-checkout', FLXPOS_URL . 'assets/checkout.css', array(), FLXPOS_VERSION );
			if ( ! is_admin() ) {
				wp_enqueue_style( 'flxpos-checkout' );
			}
			wp_register_script( 'flxpos-blocks', FLXPOS_URL . 'assets/blocks.js', array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ), FLXPOS_VERSION, true );
			wp_add_inline_script( 'flxpos-blocks', 'window.FLXPOS=' . wp_json_encode( FLXPOS_Gateways::js_config() ) . ';window.flxposBlocks=' . wp_json_encode( array_values( array_unique( self::$names ) ) ) . ';', 'before' );
		}
		return array( 'flxpos-blocks' );
	}

	public function get_payment_method_data() {
		$g    = $this->gateway();
		$cur  = get_woocommerce_currency();
		$acc  = $g ? $g->account( $cur ) : null;
		$card = $acc && FLXPOS_Accounts::takes_card( $acc );
		return array(
			'title'       => $g ? wp_strip_all_tags( (string) $g->get_title() ) : $this->name,
			'description' => $g ? wp_strip_all_tags( (string) $g->get_description() ) : '',
			'icon'        => $g ? esc_url_raw( (string) $g->icon ) : '',
			'card'        => $card,
			'account'     => $acc ? $acc['id'] : '',
			'inst'        => $acc && $acc['installments'] ? 1 : 0,
			'test'        => $acc ? (int) $acc['test'] : 0,
			'supports'    => $g ? array_values( array_filter( (array) $g->supports, 'is_string' ) ) : array( 'products' ),
		);
	}
}
