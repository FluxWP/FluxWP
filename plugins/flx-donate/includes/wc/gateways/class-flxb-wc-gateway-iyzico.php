<?php
/**
 * Flux Donate 2.0: Flux Donate's own iyzico card gateway for WooCommerce (id `flxb_iyzico`).
 *
 * Used for monthly donations (iyzico stores the card at the first payment; renewals are charged by
 * Flux Donate's cron as WooCommerce renewal orders) and for one-time donations when the official
 * iyzico WooCommerce plugin is not active. Keys: the official plugin's settings when present,
 * otherwise Flux Donate › Payments › iyzico (FLXB_Iyzico_Api::credentials()).
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLXB_WC_Gateway_Iyzico extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = FLXB_WC_Iyzico::ID;
		$this->method_title       = __( '\\nCard (iyzico) - Flux Donate\\n', 'flx-donate' );
		$this->method_description = __( '\\nCard payments on iyzico\'s secure page. Required for monthly donations (the card is stored by iyzico and charged each month); also used for one-time donations when the official iyzico plugin is not active.\\n', 'flx-donate' );
		$this->has_fields         = FLXB_WC_Iyzico_Card::on();
		$this->supports           = array( 'products', 'refunds' );
		$this->init_form_fields();
		$this->init_settings();
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		if ( FLXB_WC_Iyzico_Card::on() && self::default_description() === $this->description ) {
			$this->description = '';
		}
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable', 'flx-donate' ),
				'type'    => 'checkbox',
				'label'   => __( '\\nCard payments through Flux Donate (iyzico)\\n', 'flx-donate' ),
				'default' => 'yes',
			),
			'title'       => array(
				'title'   => __( 'Title', 'flx-donate' ),
				'type'    => 'text',
				'default' => __( '\\nCredit / debit card (iyzico)\\n', 'flx-donate' ),
			),
			'description' => array(
				'title'   => __( 'Description', 'flx-donate' ),
				'type'    => 'textarea',
				'default' => self::default_description(),
			),
			'keys'        => array(
				'title'       => __( 'API keys', 'flx-donate' ),
				'type'        => 'title',
				'description' => FLXB_WC_Iyzico::keys_text(),
			),
		);
	}

	public static function default_description() {
		return __( '\\nYou will pay on iyzico\'s secure page. For monthly donations your card is stored by iyzico and charged once a month; you can cancel at any time.\\n', 'flx-donate' );
	}

	/** 2.1.0: our own card fields (setting "Card payment screen"). */
	public function payment_fields() {
		if ( ! FLXB_WC_Iyzico_Card::on() ) {
			parent::payment_fields();
			return;
		}
		if ( '' !== $this->description ) {
			echo wpautop( wp_kses_post( $this->description ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		FLXB_WC_Iyzico_Card::fields_html();
	}

	public function is_available() {
		return parent::is_available() && FLXB_WC_Iyzico::configured();
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			$res = new WP_Error( 'flxb_iyz', __( 'Order not found.', 'flx-donate' ) );
		} else {
			$res = FLXB_WC_Iyzico_Card::on() ? FLXB_WC_Iyzico_Card::start( $order ) : FLXB_WC_Iyzico::start( $order );
		}
		if ( is_wp_error( $res ) ) {
			wc_add_notice( $res->get_error_message(), 'error' );
			return array( 'result' => 'failure' );
		}
		return array( 'result' => 'success', 'redirect' => $res );
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'flxb_iyz', __( 'Order not found.', 'flx-donate' ) );
		}
		return FLXB_WC_Iyzico::refund( $order, null === $amount ? (float) $order->get_remaining_refund_amount() : (float) $amount, (string) $reason );
	}
}
