<?php
/**
 * My Account: "Pending donations" page, transfer panel and certificates on the order page,
 * reminder on the account dashboard.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

class FLXB_WC_Account {

	const ENDPOINT = 'bekleyen-bagislar';

	public static function init() {
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'init', array( __CLASS__, 'rewrite' ), 99 );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu' ), 30 );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( __CLASS__, 'title' ) );
		add_filter( 'woocommerce_account_menu_item_classes', array( __CLASS__, 'menu_classes' ), 10, 2 );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'endpoint' ) );
		add_action( 'woocommerce_order_details_before_order_table', array( __CLASS__, 'order_details' ), 5 );
		add_filter( 'wc_order_statuses', array( __CLASS__, 'statuses' ) );
		add_action( 'flx_hesap_panel_ust', array( __CLASS__, 'reminder' ) );
		add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'reminder' ), 1 );
	}

	public static function query_vars( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	/** The endpoint is written once (first run, version change, flag `flxb_flush_rewrite`). */
	public static function rewrite() {
		if ( get_option( 'flxb_flush_rewrite' ) || FLXB_VERSION !== get_option( 'flxb_wc_rewrite_version' ) ) {
			add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
			if ( class_exists( 'FLXB_WC_Feed' ) ) {
				FLXB_WC_Feed::rule();
			}
			flush_rewrite_rules( false );
			delete_option( 'flxb_flush_rewrite' );
			update_option( 'flxb_wc_rewrite_version', FLXB_VERSION, false );
		}
	}

	/** The user's bank transfer donations waiting for payment. */
	public static function pending_orders( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}
		return wc_get_orders(
			array(
				'customer_id'    => $user_id,
				'status'         => array( 'wc-on-hold', 'wc-pending' ),
				'payment_method' => FLXB_WC_Havale::ID,
				'limit'          => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	public static function label() {
		return __( 'Pending donations', 'flx-donate' );
	}

	public static function menu( $items ) {
		$out   = array();
		$added = false;
		foreach ( $items as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'orders' === $k ) {
				$out[ self::ENDPOINT ] = self::label();
				$added                 = true;
			}
		}
		if ( ! $added ) {
			$out = array_slice( $items, 0, 1, true ) + array( self::ENDPOINT => self::label() ) + array_slice( $items, 1, null, true );
		}
		return $out;
	}

	public static function title() {
		return self::label();
	}

	public static function menu_classes( $classes, $endpoint ) {
		if ( self::ENDPOINT === $endpoint && is_user_logged_in() && self::pending_orders() ) {
			$classes[] = 'has-pending';
		}
		return $classes;
	}

	public static function endpoint() {
		FLXB_WC_Form::enqueue();
		$orders = self::pending_orders();
		echo '<div class="flxwb-pending">';
		if ( ! $orders ) {
			echo '<div class="flxwb-empty">' . FLXB_WC::icon( 'check' ) . '<p><strong>' . esc_html__( 'You have no pending donations.', 'flx-donate' ) . '</strong><br>' . esc_html__( 'Donations made by bank transfer appear here until the payment is confirmed.', 'flx-donate' ) . '</p><a class="flxwb-btn flxwb-btn--primary" href="' . esc_url( FLXB_WC::page_url() ) . '">' . esc_html__( 'Donate', 'flx-donate' ) . '</a></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG
			return;
		}
		echo '<p class="flxwb-pending__lead">' . esc_html__( 'Your bank transfer donations. If you have made the payment, upload your receipt; your certificate is sent once it is confirmed.', 'flx-donate' ) . '</p>';
		foreach ( $orders as $order ) {
			$uploaded = (bool) FLXB_WC_Receipts::of( $order );
			$summary  = array();
			foreach ( $order->get_items() as $item ) {
				$summary[] = $item->get_name() . ( $item->get_quantity() > 1 ? ' × ' . $item->get_quantity() : '' );
			}
			echo '<details class="flxwb-pcard"' . ( $uploaded ? '' : ' open' ) . '><summary class="flxwb-pcard__sum"><span class="flxwb-pcard__main"><strong>'
				/* translators: %s: donation number */
				. esc_html( sprintf( __( 'Donation #%s', 'flx-donate' ), $order->get_order_number() ) ) . '</strong><small>'
				. esc_html( wc_format_datetime( $order->get_date_created() ) ) . ' · ' . esc_html( implode( ', ', $summary ) ) . '</small></span>'
				. '<span class="flxwb-pcard__amount">' . wp_kses_post( $order->get_formatted_order_total() ) . '</span>'
				. '<span class="flxwb-pill flxwb-pill--' . ( $uploaded ? 'review' : 'wait' ) . '">' . ( $uploaded ? esc_html__( 'Receipt under review', 'flx-donate' ) : esc_html__( 'Awaiting payment', 'flx-donate' ) ) . '</span></summary>'
				. '<div class="flxwb-pcard__body">' . FLXB_WC_Havale::panel( $order, 'account' ) . '</div></details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- panel() escapes
		}
		echo '</div>';
	}

	/** Order page (My Account › Orders › view): transfer panel or certificates. */
	public static function order_details( $order ) {
		if ( ! $order instanceof WC_Order || ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) ) {
			return;
		}
		if ( FLXB_WC_Havale::awaiting( $order ) ) {
			FLXB_WC_Form::enqueue();
			echo FLXB_WC_Havale::panel( $order, 'view' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( FLXB_WC_Certificates::order_paid( $order ) && FLXB_WC_Cart::order_lines( $order ) ) {
			FLXB_WC_Form::enqueue();
			echo FLXB_WC_Certificates::cards_html( $order, 'view' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/** "Awaiting payment" instead of "On hold", only while My Account is drawn. */
	public static function statuses( $statuses ) {
		if ( isset( $statuses['wc-on-hold'] ) && ! is_admin() && did_action( 'wp' ) && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && function_exists( 'is_account_page' ) && is_account_page() ) {
			$statuses['wc-on-hold'] = __( 'Awaiting payment', 'flx-donate' );
		}
		return $statuses;
	}

	public static function reminder() {
		static $done = false;
		if ( $done || ! is_user_logged_in() ) {
			return;
		}
		$done = true;
		$n    = count( self::pending_orders() );
		if ( ! $n ) {
			return;
		}
		FLXB_WC_Form::enqueue();
		echo '<a class="flxwb-remind" href="' . esc_url( wc_get_account_endpoint_url( self::ENDPOINT ) ) . '">' . FLXB_WC::icon( 'clock' ) . '<span><strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG
			/* translators: %s: number of donations */
			. esc_html( sprintf( _n( '%s donation is awaiting payment', '%s donations are awaiting payment', $n, 'flx-donate' ), number_format_i18n( $n ) ) ) . '</strong><small>'
			. esc_html__( 'If you have made the transfer, upload your receipt.', 'flx-donate' ) . '</small></span></a>';
	}
}
