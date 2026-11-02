<?php
/**
 * Donation ledger for WooCommerce orders.
 *
 * Every donation line of an order is one row in wp_flxb_donations (uniq "wc:<order>:<item>",
 * provider "woocommerce", column wc_order_id), so the dashboard, donors, reports, recurring
 * donations and Flux Forms/Flux Chat integrations keep working on the same table.
 * Idempotent: the same order may be synced any number of times (status changes, payment,
 * refunds). Links both ways: item meta `_flxb_donation_id`, order meta
 * `_flxb_donation_ids`, donation column `wc_order_id`.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

class FLXB_WC_Ledger {

	/** @var bool Set while the ledger writes, so mirrors do not echo the change back. */
	public static $syncing = false;

	public static function init() {
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_status' ), 20, 4 );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'sync' ), 20 );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'sync' ), 20 );
		add_action( 'woocommerce_order_partially_refunded', array( __CLASS__, 'sync' ), 20 );
		/* 2.5.0: an order sent to the trash, restored or deleted takes its donations along (HPOS and posts). */
		add_action( 'woocommerce_trash_order', array( __CLASS__, 'on_trash' ), 20 );
		add_action( 'woocommerce_untrash_order', array( __CLASS__, 'on_untrash' ), 20 );
		add_action( 'woocommerce_delete_order', array( __CLASS__, 'on_delete' ), 20 );
		/* The posts order screen trashes and deletes the post directly, without the hooks above. */
		add_action( 'trashed_post', array( __CLASS__, 'post_hook' ), 20 );
		add_action( 'untrashed_post', array( __CLASS__, 'post_hook' ), 20 );
		add_action( 'deleted_post', array( __CLASS__, 'post_hook' ), 20, 2 );
	}

	public static function post_hook( $post_id, $post = null ) {
		$type = $post instanceof WP_Post ? $post->post_type : get_post_type( $post_id );
		if ( 'shop_order' !== $type ) {
			return;
		}
		switch ( current_action() ) {
			case 'trashed_post':
				self::on_trash( $post_id );
				break;
			case 'untrashed_post':
				self::on_untrash( $post_id );
				break;
			default:
				self::on_delete( $post_id );
		}
	}

	public static function on_trash( $order_id ) {
		FLXB_Donations::trash_rows( FLXB_Donations::ids_of_order( (int) $order_id ) );
	}

	public static function on_untrash( $order_id ) {
		FLXB_Donations::restore_rows( FLXB_Donations::ids_of_order( (int) $order_id ) );
		self::sync( (int) $order_id );
	}

	public static function on_delete( $order_id ) {
		FLXB_Donations::delete_rows( FLXB_Donations::ids_of_order( (int) $order_id ) );
	}

	/**
	 * Donations whose WooCommerce order is gone (deleted before 2.5.0) are deleted; those whose
	 * order is in the trash go to the trash. Runs from the donations list, at most twice a day.
	 *
	 * @return array deleted, trashed
	 */
	public static function reconcile( $force = false ) {
		if ( ! $force && get_transient( 'flxb_reconciled' ) ) {
			return array( 'deleted' => 0, 'trashed' => 0 );
		}
		set_transient( 'flxb_reconciled', 1, 12 * HOUR_IN_SECONDS );
		global $wpdb;
		$out = array( 'deleted' => 0, 'trashed' => 0 );
		$ids = array_map( 'intval', (array) $wpdb->get_col( 'SELECT DISTINCT wc_order_id FROM ' . flxb_table( 'donations' ) . " WHERE wc_order_id > 0 AND status <> 'trash' ORDER BY wc_order_id DESC LIMIT 2000" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $ids as $oid ) {
			$order = wc_get_order( $oid );
			if ( ! $order ) {
				$out['deleted'] += FLXB_Donations::delete_rows( FLXB_Donations::ids_of_order( $oid ) );
			} elseif ( 'trash' === $order->get_status() ) {
				$out['trashed'] += FLXB_Donations::trash_rows( FLXB_Donations::ids_of_order( $oid ) );
			}
		}
		return $out;
	}

	public static function on_status( $order_id, $from, $to, $order = null ) {
		self::sync( $order instanceof WC_Order ? $order : $order_id );
	}

	/** WooCommerce status → donation status ('' = do not create a row yet). */
	public static function status_of( WC_Order $order ) {
		switch ( $order->get_status() ) {
			case 'processing':
			case 'completed':
				return 'completed';
			case 'on-hold':
				return 'pending';
			case 'pending':
				/* Unpaid card attempts stay out of the ledger; bank transfers and call-centre orders do not. */
				/* 2.3.0: so are staff requests (flxb-ref), so the referral sees the gift before it is paid. */
				return in_array( $order->get_payment_method(), array( FLXB_WC_Havale::ID ), true ) || in_array( $order->get_created_via(), array( 'flx-chat', 'flxb-ref' ), true ) ? 'pending' : '';
			case 'cancelled':
				return 'canceled';
			case 'failed':
				return 'failed';
			case 'refunded':
				return 'refunded';
		}
		return '';
	}

	/** Ledger source of an order: callcenter (Flux Chat), renewal (monthly charge), staff (2.3.0 request), woocommerce. */
	public static function source_of( WC_Order $order ) {
		switch ( $order->get_created_via() ) {
			case 'flx-chat':
				return 'callcenter';
			case FLXB_WC_Recurring::VIA:
				return 'renewal';
			case 'flxb-ref':
				return 'staff';
		}
		return 'woocommerce';
	}

	/** Amount of a line (tax included, the printed certificate fee is a separate fee line and not counted). */
	public static function line_amount( WC_Order_Item_Product $item ) {
		return round( (float) $item->get_total() + (float) $item->get_total_tax(), 2 );
	}

	/**
	 * Write the order's donation lines to the ledger.
	 *
	 * @param int|WC_Order $order Order.
	 * @return int[] Donation ids by item id.
	 */
	public static function sync( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order || $order instanceof WC_Order_Refund || 'trash' === $order->get_status() ) {
			return array();
		}
		$lines = FLXB_WC_Cart::order_lines( $order );
		if ( ! $lines ) {
			return array();
		}
		$base = self::status_of( $order );
		$ids  = array();
		$prev = self::$syncing;
		self::$syncing = true;
		foreach ( $lines as $item_id => $item ) {
			$id = self::sync_line( $order, $item, $base );
			if ( $id ) {
				$ids[ $item_id ] = $id;
			}
		}
		self::$syncing = $prev;
		if ( $ids ) {
			$old = array_map( 'intval', (array) $order->get_meta( '_flxb_donation_ids' ) );
			if ( array_values( $old ) !== array_values( $ids ) ) {
				$order->update_meta_data( '_flxb_donation_ids', $ids );
				$order->save_meta_data();
			}
		}
		return $ids;
	}

	private static function sync_line( WC_Order $order, WC_Order_Item_Product $item, $base ) {
		$l        = FLXB_WC_Cart::item_line( $item );
		$item_id  = (int) $item->get_id();
		$amount   = self::line_amount( $item );
		$refunded = abs( (float) $order->get_total_refunded_for_item( $item_id ) ) + abs( (float) $order->get_tax_refunded_for_item( $item_id, 0 ) );
		$status   = $base;
		if ( $refunded > 0.004 && in_array( $base, array( 'completed', 'refunded' ), true ) ) {
			$status = $refunded + 0.004 >= $amount ? 'refunded' : 'partially_refunded';
		}
		/* A row made elsewhere first (Flux Chat, Flux Forms) is completed in place. */
		$linked   = (int) $item->get_meta( '_flxb_donation_id' );
		$existing = $linked ? FLXB_Donations::get( $linked ) : null;
		$uniq     = $existing ? (string) $existing['uniq'] : 'wc:' . $order->get_id() . ':' . $item_id;
		if ( ! $existing ) {
			$existing = FLXB_Donations::get_by_uniq( $uniq );
		}
		if ( '' === $status ) {
			return $existing ? (int) $existing['id'] : 0;
		}
		if ( ! $existing && in_array( $status, array( 'canceled', 'failed' ), true ) ) {
			/* Never paid, never registered: nothing to keep. */
			return 0;
		}
		$t      = FLXB_Types::get( (int) $l['type_id'] );
		$opt    = $t && '' !== (string) ( $l['option'] ?? '' ) ? FLXB_Types::option( $t, (string) $l['option'] ) : null;
		$qty    = max( 1, (int) $item->get_quantity() );
		$units  = max( 1, (int) ( $l['units'] ?? 1 ) ) * $qty;
		$label  = $opt ? (string) $opt['label'] : ( '' === (string) ( $l['option'] ?? '' ) ? __( 'Free amount', 'flx-donate' ) : (string) $l['option'] );
		$sub_id = (int) $item->get_meta( '_flxb_subscription_id' );
		$sub_id = $sub_id ? $sub_id : (int) $order->get_meta( '_flxb_subscription_id' );
		$form   = isset( $l['form'] ) && is_array( $l['form'] ) ? $l['form'] : array();
		$ben    = ! empty( $l['for_other'] ) ? trim( (string) ( $l['ben_name'] ?? '' ) ) : '';
		/* 2.0.1: names per share (proxy types) are kept one by one, so each item gets its own name. */
		$names  = isset( $l['beneficiaries'] ) && is_array( $l['beneficiaries'] ) ? array_values( array_filter( array_map( 'strval', $l['beneficiaries'] ), 'strlen' ) ) : array();
		$proxy  = self::consent_of( $l );
		$d      = array(
			'uniq'         => $uniq,
			'provider'     => 'woocommerce',
			'provider_ref' => $order->get_id() . ':' . $item_id,
			'amount'       => $amount,
			'currency'     => $order->get_currency(),
			'type'         => $sub_id ? 'recurring' : 'one_time',
			'status'       => $status,
			'donor'        => array(
				'name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'email' => FLXB_WC_Contact::email( $order ),
				'phone' => $order->get_billing_phone(),
			),
			'type_id'         => (int) $l['type_id'],
			'option_key'      => $opt ? (string) $opt['key'] : '',
			'option_label'    => $label,
			'quantity'        => $units,
			'unit_price'      => $units > 0 ? round( $amount / $units, 4 ) : $amount,
			'subscription_id' => $sub_id,
			'form_id'         => (int) ( $form['id'] ?? 0 ),
			'track'           => (string) ( $form['track'] ?? '' ),
			'pay_id'          => (int) $order->get_meta( '_flxb_pay_id' ),
			'certificate_code' => self::cert_code( $item ),
			'source'          => self::source_of( $order ),
			'wc_order_id'     => (int) $order->get_id(),
			'ref_user_id'     => FLXB_Refs::of_order( $order ),
			'created_at'      => $order->get_date_created() ? gmdate( 'Y-m-d H:i:s', $order->get_date_created()->getTimestamp() ) : flxb_now(),
			'meta'            => array_filter(
				array(
					'method'       => $order->get_payment_method(),
					'method_title' => $order->get_payment_method_title(),
					'wc_order'     => (int) $order->get_id(),
					'wc_item'      => $item_id,
					'transaction'  => (string) $order->get_transaction_id(),
					'cert'         => (string) ( $l['cert'] ?? '' ),
					'on_behalf'    => $ben,
					'proxy_consent' => $proxy,
					'monthly'      => ! empty( $l['monthly'] ) ? 1 : 0,
					'renewal'      => FLXB_WC_Recurring::VIA === $order->get_created_via() ? true : null,
					'period'       => (string) $order->get_meta( '_flxb_renewal_period' ),
				)
			),
			'lines'           => array(
				array(
					'key'        => $opt ? (string) $opt['key'] : '',
					'label'      => $label,
					'units'      => $units,
					'unit_price' => $opt ? (float) $opt['price'] : $amount,
					'amount'     => $amount,
					'unit'       => $opt ? (string) $opt['unit'] : '',
					'units_per'  => $opt ? (int) $opt['units_per'] : 1,
				),
			),
			'beneficiaries'   => $names ? $names : ( '' !== $ben ? array_fill( 0, $units, $ben ) : array() ),
		);
		$d  = (array) apply_filters( 'flxb_wc_ledger_row', $d, $order, $item );
		$id = FLXB_Donations::record( $d );
		if ( ! $id ) {
			return 0;
		}
		$row = FLXB_Donations::get( $id );
		if ( $row ) {
			$m   = FLXB_Donations::meta( $row );
			$add = array();
			if ( abs( (float) ( $m['refunded'] ?? 0 ) - $refunded ) > 0.004 ) {
				$add['refunded'] = round( $refunded, 2 );
			}
			if ( $proxy && empty( $m['proxy_consent'] ) ) {
				$add['proxy_consent'] = $proxy;
			}
			if ( empty( $m['wc_order'] ) ) {
				$add['wc_order'] = (int) $order->get_id();
				$add['wc_item']  = $item_id;
			}
			if ( $add ) {
				FLXB_Donations::update_meta( $id, $add );
			}
			if ( '' !== $d['certificate_code'] && $d['certificate_code'] !== (string) $row['certificate_code'] ) {
				FLXB_Donations::set_certificate( $id, $d['certificate_code'] );
			}
			if ( ! (int) $row['wc_order_id'] ) {
				global $wpdb;
				$wpdb->update( flxb_table( 'donations' ), array( 'wc_order_id' => (int) $order->get_id() ), array( 'id' => $id ) );
			}
		}
		if ( (int) $item->get_meta( '_flxb_donation_id' ) !== $id ) {
			$item->update_meta_data( '_flxb_donation_id', $id );
			$item->save();
		}
		return $id;
	}

	/** Proxy (vekâlet) consent of a line: { version, accepted_at, source } or null. */
	public static function consent_of( array $l ) {
		$c = $l['proxy_consent'] ?? null;
		if ( ! is_array( $c ) || empty( $c['version'] ) ) {
			return null;
		}
		return array(
			'version'     => (int) $c['version'],
			'accepted_at' => sanitize_text_field( (string) ( $c['accepted_at'] ?? '' ) ),
			'source'      => sanitize_key( (string) ( $c['source'] ?? 'woocommerce' ) ),
		);
	}

	/** Flux Certificates verification code of the line's certificate (the ledger keeps codes, not numbers). */
	private static function cert_code( WC_Order_Item_Product $item ) {
		$cid = (int) $item->get_meta( '_flxb_cert_id' );
		if ( ! $cid || ! function_exists( 'flxs_get_certificate' ) ) {
			return '';
		}
		$c = flxs_get_certificate( $cid );
		return is_array( $c ) ? (string) ( $c['code'] ?? '' ) : '';
	}

	/** Order of a donation row (0 when it was not paid through WooCommerce). */
	public static function order_of( $donation ) {
		$d = is_array( $donation ) ? $donation : FLXB_Donations::get( (int) $donation );
		if ( ! $d ) {
			return 0;
		}
		if ( ! empty( $d['wc_order_id'] ) ) {
			return (int) $d['wc_order_id'];
		}
		$m = FLXB_Donations::meta( $d );
		return (int) ( $m['wc_order'] ?? 0 );
	}

	/** Item of a donation row. */
	public static function item_of( $donation ) {
		$d = is_array( $donation ) ? $donation : FLXB_Donations::get( (int) $donation );
		$m = $d ? FLXB_Donations::meta( $d ) : array();
		return (int) ( $m['wc_item'] ?? 0 );
	}
}
