<?php
/**
 * WooCommerce siparişleri → kişi + "Sipariş" olayı. Flux Commerce şart değil; yalnız WooCommerce.
 * Tüm satırları Flux Donate bağışı olan siparişler atlanır (onları Flux Donate kaynağı getirir).
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Adapter_Commerce {

	const KEY = 'commerce';

	public static function enabled() {
		return (bool) flxcrm_setting( 'src_commerce' ) && class_exists( 'WooCommerce' ) && function_exists( 'wc_get_order' );
	}

	public static function label() {
		return 'WooCommerce';
	}

	public static function init() {
		if ( ! self::enabled() ) {
			return;
		}
		add_action( 'woocommerce_new_order', array( __CLASS__, 'on_change' ), 30, 1 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_change' ), 30, 1 );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'on_change' ), 30, 1 );
		add_action( 'woocommerce_trash_order', array( __CLASS__, 'on_remove' ), 30, 1 );
		add_action( 'woocommerce_delete_order', array( __CLASS__, 'on_remove' ), 30, 1 );
		add_action( 'woocommerce_untrash_order', array( __CLASS__, 'on_change' ), 30, 1 );
	}

	public static function paid_statuses() {
		$s = function_exists( 'flxcm_paid_statuses' ) ? flxcm_paid_statuses() : array( 'processing', 'completed' );
		return (array) apply_filters( 'flxcrm_paid_order_statuses', $s );
	}

	public static function on_change( $order_id ) {
		try {
			self::sync( $order_id );
		} catch ( \Throwable $e ) {
			error_log( 'flx-crm commerce: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	public static function on_remove( $order_id ) {
		FLXCRM_Events::delete_by_uniq( 'wc:order:' . (int) $order_id );
	}

	private static function is_donation( $order ) {
		if ( function_exists( 'flxcm_is_donation_order' ) ) {
			return flxcm_is_donation_order( $order );
		}
		if ( ! class_exists( 'FLXB_WC_Cart' ) || ! method_exists( 'FLXB_WC_Cart', 'item_line' ) ) {
			return false;
		}
		$items = $order->get_items( 'line_item' );
		if ( ! $items ) {
			return false;
		}
		foreach ( $items as $item ) {
			if ( ! FLXB_WC_Cart::item_line( $item ) ) {
				return false;
			}
		}
		return true;
	}

	public static function sync( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( (int) $order );
		if ( ! $order || ! $order instanceof WC_Order || $order instanceof WC_Order_Refund ) {
			return 0;
		}
		$status = $order->get_status();
		if ( in_array( $status, array( 'checkout-draft', 'auto-draft', 'trash' ), true ) ) {
			return 0;
		}
		if ( FLXCRM_Adapter_Donate::enabled() && self::is_donation( $order ) ) {
			return 0;
		}
		$email = (string) $order->get_billing_email();
		$name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$paid  = in_array( $status, self::paid_statuses(), true );
		$cid   = FLXCRM_Contacts::upsert(
			array(
				'name'         => $name,
				'email'        => $email,
				'phone'        => (string) $order->get_billing_phone(),
				'user_id'      => (int) $order->get_customer_id(),
				'company'      => (string) $order->get_billing_company(),
				'city'         => (string) $order->get_billing_city(),
				'country'      => (string) $order->get_billing_country(),
				'roles'        => $paid ? array( (string) flxcrm_setting( 'role_commerce' ) ) : array(),
				'source'       => self::KEY,
				'link'         => '' !== flxcrm_email( $email ) ? array( self::KEY, 'email:' . flxcrm_email( $email ) ) : null,
				'utm_source'   => (string) $order->get_meta( '_wc_order_attribution_utm_source' ),
				'utm_campaign' => (string) $order->get_meta( '_wc_order_attribution_utm_campaign' ),
				'at'           => $order->get_date_created() ? gmdate( 'Y-m-d H:i:s', $order->get_date_created()->getTimestamp() ) : '',
			)
		);
		if ( ! $cid ) {
			return 0;
		}
		$items = array();
		foreach ( $order->get_items( 'line_item' ) as $it ) {
			$items[] = $it->get_name() . ( $it->get_quantity() > 1 ? ' × ' . $it->get_quantity() : '' );
		}
		$total = (float) $order->get_total() - (float) $order->get_total_refunded();
		/* translators: %s: order number */
		$title = sprintf( __( 'Order #%s', 'flx-crm' ), $order->get_order_number() );
		if ( $items ) {
			$title .= ': ' . implode( ', ', array_slice( $items, 0, 3 ) ) . ( count( $items ) > 3 ? '…' : '' );
		}
		FLXCRM_Events::add(
			$cid,
			'order',
			array(
				'source'   => self::KEY,
				'uniq'     => 'wc:order:' . (int) $order->get_id(),
				'title'    => $title,
				'amount'   => max( 0, $total ),
				'currency' => $order->get_currency(),
				'paid'     => $paid && $total > 0,
				'url'      => self::edit_path( $order ),
				'data'     => array( 'status' => $status, 'items' => count( $items ) ),
				'at'       => $order->get_date_created() ? gmdate( 'Y-m-d H:i:s', $order->get_date_created()->getTimestamp() ) : flxcrm_now(),
			)
		);
		return $cid;
	}

	private static function edit_path( WC_Order $order ) {
		$url = $order->get_edit_order_url();
		$adm = admin_url();
		return 0 === strpos( $url, $adm ) ? substr( $url, strlen( $adm ) ) : $url;
	}

	public static function backfill( $page, $per ) {
		$args  = array(
			'limit'    => $per,
			'paged'    => $page,
			'orderby'  => 'ID',
			'order'    => 'ASC',
			'type'     => 'shop_order',
			'status'   => array_keys( wc_get_order_statuses() ),
			'paginate' => true,
		);
		$res   = wc_get_orders( $args );
		$total = (int) ( $res->total ?? 0 );
		foreach ( (array) ( $res->orders ?? array() ) as $o ) {
			self::sync( $o );
		}
		return array( 'done' => count( (array) ( $res->orders ?? array() ) ) < $per, 'scanned' => min( $total, $page * $per ), 'total' => $total );
	}
}
