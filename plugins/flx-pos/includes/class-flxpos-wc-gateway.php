<?php
/**
 * WooCommerce ödeme yöntemi sınıfları: kart (flxpos_card) ve cüzdan hesapları (flxpos_<hesap>).
 * Ayarlar Flux POS panelinden gelir; WooCommerce'in kendi ayar ekranı panele bağlantı gösterir.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

/** Ortak taban. */
abstract class FLXPOS_WC_Base extends WC_Payment_Gateway {

	/** @return array|null Bu yöntemi işleyecek hesap. */
	abstract public function account( $currency = '' );

	public function __construct() {
		$this->has_fields = true;
		$this->supports   = array( 'products', 'refunds' );
		$this->init_form_fields();
		$this->init_settings();
		$this->enabled = 'yes' === ( $this->settings['enabled'] ?? 'yes' ) ? 'yes' : 'no';
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Etkin', 'flx-pos' ),
				'type'    => 'checkbox',
				'label'   => __( 'Bu ödeme yöntemini göster', 'flx-pos' ),
				'default' => 'yes',
			),
		);
	}

	public function admin_options() {
		echo '<h2>' . esc_html( $this->get_method_title() ) . '</h2>';
		echo '<p>' . esc_html__( 'Hesaplar, anahtarlar, taksitler ve görünen ad Flux POS panelinden yönetilir.', 'flx-pos' ) . ' <a class="button" href="' . esc_url( flxpos_admin_url() ) . '">' . esc_html__( 'Flux POS paneli', 'flx-pos' ) . '</a></p>';
		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
	}

	protected function current_currency() {
		$order_id = absint( get_query_var( 'order-pay' ) );
		if ( $order_id ) {
			$o = wc_get_order( $order_id );
			if ( $o ) {
				return $o->get_currency();
			}
		}
		return get_woocommerce_currency();
	}

	protected function current_total() {
		$order_id = absint( get_query_var( 'order-pay' ) );
		if ( $order_id ) {
			$o = wc_get_order( $order_id );
			if ( $o ) {
				return (float) $o->get_total();
			}
		}
		return WC()->cart ? (float) WC()->cart->get_total( 'edit' ) : 0.0;
	}

	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}
		return (bool) $this->account( $this->current_currency() );
	}

	/** Bu hesap kartı sitede alıyor mu? */
	public function takes_card( $currency = '' ) {
		$a = $this->account( $currency ? $currency : $this->current_currency() );
		return $a && FLXPOS_Accounts::takes_card( $a );
	}

	public function payment_fields() {
		$a = $this->account( $this->current_currency() );
		if ( '' !== (string) $this->description ) {
			echo wp_kses_post( wpautop( wptexturize( $this->description ) ) );
		}
		if ( $a && FLXPOS_Accounts::takes_card( $a ) ) {
			FLXPOS_Gateways::card_form( $a, $this->current_total() );
		}
	}

	public function validate_fields() {
		if ( ! $this->takes_card() ) {
			return true;
		}
		$err = FLXPOS_Gateways::card_error( FLXPOS_Gateways::posted_card() );
		if ( '' !== $err ) {
			wc_add_notice( $err, 'error' );
			return false;
		}
		return true;
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$a     = $order ? $this->account( $order->get_currency() ) : null;
		if ( ! $a ) {
			wc_add_notice( __( 'Bu para birimi için kart ödemesi kullanılamıyor.', 'flx-pos' ), 'error' );
			return array( 'result' => 'failure' );
		}
		$card = null;
		if ( FLXPOS_Accounts::takes_card( $a ) ) {
			$card = FLXPOS_Gateways::posted_card();
			$err  = FLXPOS_Gateways::card_error( $card );
			if ( '' !== $err ) {
				wc_add_notice( $err, 'error' );
				return array( 'result' => 'failure' );
			}
		}
		$r = FLXPOS_Gateways::start_order( $order, FLXPOS_Accounts::get( $a['id'] ), $card );
		if ( is_wp_error( $r ) ) {
			wc_add_notice( $r->get_error_message(), 'error' );
			return array( 'result' => 'failure' );
		}
		return $r;
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return FLXPOS_Gateways::refund( $order_id, $amount, $reason );
	}

	public function can_refund_order( $order ) {
		return $order && FLXPOS_Txn::paid_for_order( $order->get_id() );
	}
}

/** Tek "Kart ile öde" yöntemi. */
class FLXPOS_WC_Card extends FLXPOS_WC_Base {

	public function __construct() {
		$this->id                 = 'flxpos_card';
		$this->method_title       = __( 'Kart ile öde (Flux POS)', 'flx-pos' );
		$this->method_description = __( 'Banka veya ödeme kuruluşu üzerinden 3D Secure kartlı ödeme.', 'flx-pos' );
		parent::__construct();
		$t                 = (string) flxpos_setting( 'card_title' );
		$d                 = (string) flxpos_setting( 'card_desc' );
		$this->title       = '' !== $t ? $t : __( 'Banka / Kredi Kartı', 'flx-pos' );
		$this->description = $d;
		$this->icon        = apply_filters( 'flxpos_card_icon', '', $this );
	}

	public function account( $currency = '' ) {
		return FLXPOS_Accounts::for_card( $currency ? $currency : get_woocommerce_currency() );
	}
}

/** Cüzdan / yönlendirmeli hesap (Papara, Garanti Pay, GiroGate…). */
class FLXPOS_WC_Wallet extends FLXPOS_WC_Base {

	/** @var string */
	public $account_id;

	public function __construct( $account_id = '' ) {
		$this->account_id = (string) $account_id;
		$this->id         = 'flxpos_' . $this->account_id;
		$a                = FLXPOS_Accounts::get( $this->account_id );
		$p                = $a ? FLXPOS_Providers::get( $a['provider'] ) : null;
		$name             = $p ? $p['name'] : $this->account_id;
		/* translators: %s: sağlayıcı adı */
		$this->method_title       = sprintf( __( '%s (Flux POS)', 'flx-pos' ), $name );
		$this->method_description = __( 'Ödeme sağlayıcının sayfasında tamamlanır.', 'flx-pos' );
		parent::__construct();
		$this->title       = $a && '' !== $a['title'] ? $a['title'] : $name;
		$this->description = '';
		$this->has_fields  = false;
	}

	public function account( $currency = '' ) {
		$a = FLXPOS_Accounts::get( $this->account_id );
		if ( ! $a || empty( $a['enabled'] ) ) {
			return null;
		}
		return FLXPOS_Accounts::accepts( $a, $currency ? $currency : get_woocommerce_currency() ) ? $a : null;
	}
}
