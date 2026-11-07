<?php
/**
 * Flux Donate 2.0: donation orders created in code, and the Flux Chat mirror.
 *
 * flxb_create_order() makes a WooCommerce order from donation lines (for call centres, imports or
 * other plugins). Flux Chat records its donations straight into the ledger
 * (flxc_create_donation → FLXB_Donations::record, source "callcenter"); each such row is mirrored to
 * a WooCommerce order (created_via "flx-chat", bank transfer when the method is bank transfer,
 * Pending payment or Completed). The order line carries `_flxb_donation_id`, so the ledger completes
 * the same row instead of adding another one; Flux Chat keeps its receipts, certificate and
 * beneficiary_contacts on the donation. Status changes made later on either side follow the other.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

class FLXB_WC_Callcenter {

	const VIA = 'flx-chat';

	/** @var bool */
	private static $busy = false;

	public static function init() {
		add_action( 'flxb_donation_recorded', array( __CLASS__, 'mirror' ), 20, 2 );
		add_action( 'flxb_donation_updated', array( __CLASS__, 'follow' ), 20, 2 );
	}

	/**
	 * Create a donation order.
	 *
	 * @param array $a lines: [ { type_id, option, units, amount (required for a free amount or a fixed total),
	 *                 cert, name, for_other, ben_name, ben_phone, ben_email, donation_id } ],
	 *                 billing: { first_name, last_name, email, phone }, customer_id,
	 *                 Lines may also carry beneficiaries (names, one per share) and proxy_consent
	 *                 { version, accepted_at, source } for proxy types (2.0.1).
	 *                 payment_method (flxb_havale | any gateway id | ''), payment_title,
	 *                 status (pending | on-hold | completed), created_via, note, emails (bool, default false),
	 *                 currency (2.10.0: ISO code, default the store currency),
	 *                 ref_user_id (2.3.0: the referral, written as order meta `_flx_ref_user`).
	 * @return WC_Order|WP_Error
	 */
	public static function create_order( array $a ) {
		$lines = isset( $a['lines'] ) && is_array( $a['lines'] ) ? $a['lines'] : array();
		if ( ! $lines ) {
			return new WP_Error( 'flxb_lines', __( 'No donation lines.', 'flx-donate' ) );
		}
		$billing = wp_parse_args( (array) ( $a['billing'] ?? array() ), array( 'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '' ) );
		$cust    = (int) ( $a['customer_id'] ?? 0 );
		if ( ! $cust && is_email( $billing['email'] ) ) {
			$u    = get_user_by( 'email', $billing['email'] );
			$cust = $u ? (int) $u->ID : 0;
		}
		$quiet = empty( $a['emails'] );
		if ( $quiet ) {
			add_filter( 'woocommerce_email_enabled_customer_on_hold_order', '__return_false', 99 );
			add_filter( 'woocommerce_email_enabled_customer_processing_order', '__return_false', 99 );
			add_filter( 'woocommerce_email_enabled_customer_completed_order', '__return_false', 99 );
			add_filter( 'woocommerce_email_enabled_new_order', '__return_false', 99 );
		}
		try {
			$order = wc_create_order( array( 'customer_id' => $cust, 'created_via' => sanitize_key( (string) ( $a['created_via'] ?? 'flxb-api' ) ) ) );
			if ( is_wp_error( $order ) ) {
				return $order;
			}
			/* 2.10.0: an order in another currency (e.g. a kurban request from an organisation abroad). */
			if ( ! empty( $a['currency'] ) && preg_match( '/^[A-Za-z]{3}$/', (string) $a['currency'] ) ) {
				$order->set_currency( strtoupper( (string) $a['currency'] ) );
			}
			/* 2.11.0: older records (Excel import) keep their own date; the donation rows take it from the order. */
			if ( ! empty( $a['created_at'] ) && strtotime( (string) $a['created_at'] ) ) {
				$order->set_date_created( strtotime( get_gmt_from_date( (string) $a['created_at'] ) ) );
			}
			foreach ( $lines as $l ) {
				$t = FLXB_Types::get( absint( $l['type_id'] ?? 0 ) );
				if ( ! $t ) {
					$order->delete( true );
					return new WP_Error( 'flxb_type', __( 'Unknown donation type.', 'flx-donate' ) );
				}
				$pid  = FLXB_WC_Products::product_id( $t );
				$prod = $pid ? wc_get_product( $pid ) : null;
				if ( ! $prod ) {
					$order->delete( true );
					return new WP_Error( 'flxb_product', __( 'The donation product could not be created.', 'flx-donate' ) );
				}
				$key    = sanitize_key( (string) ( $l['option'] ?? '' ) );
				$opt    = '' !== $key ? FLXB_Types::option( $t, $key ) : null;
				$units  = max( 1, absint( $l['units'] ?? 1 ) );
				$amount = isset( $l['amount'] ) ? round( (float) $l['amount'], 2 ) : ( $opt ? round( FLXB_Types::option_price( $t, $opt, $order->get_currency() ) * $units, 2 ) : 0 );
				if ( $amount <= 0 ) {
					$order->delete( true );
					return new WP_Error( 'flxb_amount', __( 'Enter an amount.', 'flx-donate' ) );
				}
				$line = array_filter(
					array(
						'type_id'   => (int) $t['id'],
						'option'    => $opt ? $key : '',
						'units'     => $units,
						'amount'    => $amount,
						'cert'      => (string) ( $l['cert'] ?? '' ),
						'name'      => (string) ( $l['name'] ?? '' ),
						'for_other' => ! empty( $l['for_other'] ),
						'ben_name'  => (string) ( $l['ben_name'] ?? '' ),
						'ben_phone' => (string) ( $l['ben_phone'] ?? '' ),
						'ben_email' => (string) ( $l['ben_email'] ?? '' ),
					),
					static function ( $v ) {
						return '' !== $v && false !== $v;
					}
				);
				$line['option'] = $opt ? $key : '';
				/* 2.0.1: names per share and the proxy consent (proxy types), kept as given. */
				if ( ! empty( $l['beneficiaries'] ) ) {
					$names = flxb_split_names( is_array( $l['beneficiaries'] ) ? implode( "\n", array_filter( $l['beneficiaries'], 'is_scalar' ) ) : (string) $l['beneficiaries'] );
					if ( $names ) {
						$line['beneficiaries'] = array_slice( $names, 0, $units );
					}
				}
				$consent = FLXB_WC_Ledger::consent_of( array( 'proxy_consent' => $l['proxy_consent'] ?? null ) );
				if ( $consent ) {
					$line['proxy_consent'] = $consent;
				}
				$item           = new WC_Order_Item_Product();
				$item->set_product( $prod );
				$item->set_quantity( 1 );
				$item->set_subtotal( $amount );
				$item->set_total( $amount );
				FLXB_WC_Cart::write_line_meta( $item, $line, 1 );
				if ( ! empty( $l['donation_id'] ) ) {
					$item->add_meta_data( '_flxb_donation_id', (int) $l['donation_id'], true );
				}
				$order->add_item( $item );
			}
			$order->set_address( $billing, 'billing' );
			$ref = FLXB_Refs::valid( absint( $a['ref_user_id'] ?? 0 ) ); // 2.3.0
			if ( $ref ) {
				$order->update_meta_data( '_flx_ref_user', $ref );
			}
			$method = (string) ( $a['payment_method'] ?? '' );
			$order->set_payment_method( $method );
			if ( '' !== (string) ( $a['payment_title'] ?? '' ) ) {
				$order->set_payment_method_title( (string) $a['payment_title'] );
			} elseif ( FLXB_WC_Havale::ID === $method ) {
				$order->set_payment_method_title( __( 'Bank transfer', 'flx-donate' ) );
			}
			$order->calculate_totals( false );
			$order->save();
			if ( FLXB_WC_Havale::ID === $method ) {
				FLXB_WC_Havale::reference( $order );
				$order->update_meta_data( '_flxb_state', 'awaiting' );
			}
			if ( '' !== (string) ( $a['note'] ?? '' ) ) {
				$order->add_order_note( sanitize_textarea_field( (string) $a['note'] ) );
			}
			$status = (string) ( $a['status'] ?? 'pending' );
			if ( 'completed' === $status ) {
				$order->save();
				$order->payment_complete();
				$order = wc_get_order( $order->get_id() );
				if ( ! $order->has_status( 'completed' ) ) {
					$order->update_status( 'completed' );
				}
			} else {
				$order->set_status( in_array( $status, array( 'pending', 'on-hold' ), true ) ? $status : 'pending' );
				$order->save();
				FLXB_WC_Ledger::sync( $order );
			}
			return wc_get_order( $order->get_id() );
		} finally {
			if ( $quiet ) {
				remove_filter( 'woocommerce_email_enabled_customer_on_hold_order', '__return_false', 99 );
				remove_filter( 'woocommerce_email_enabled_customer_processing_order', '__return_false', 99 );
				remove_filter( 'woocommerce_email_enabled_customer_completed_order', '__return_false', 99 );
				remove_filter( 'woocommerce_email_enabled_new_order', '__return_false', 99 );
			}
		}
	}

	/** Flux Chat recorded a donation: open its WooCommerce order. */
	public static function mirror( $id, $d ) {
		if ( self::$busy || FLXB_WC_Ledger::$syncing || 'callcenter' !== ( $d['source'] ?? '' ) || ! empty( $d['wc_order_id'] ) || ! apply_filters( 'flxb_wc_mirror_callcenter', true, $id, $d ) ) {
			return;
		}
		$row = FLXB_Donations::get( (int) $id );
		if ( ! $row || (int) $row['wc_order_id'] || ! (int) $row['type_id'] ) {
			return;
		}
		$donor = (int) $row['donor_id'] ? FLXB_Donors::get( (int) $row['donor_id'] ) : null;
		$name  = $donor ? trim( (string) $donor['name'] ) : '';
		$parts = '' !== $name ? preg_split( '/\s+/u', $name ) : array();
		$last  = count( $parts ) > 1 ? array_pop( $parts ) : '';
		$m     = FLXB_Donations::meta( $row );
		$names = isset( $d['beneficiaries'] ) && is_array( $d['beneficiaries'] ) ? array_values( array_filter( array_map( 'strval', $d['beneficiaries'] ) ) ) : array();
		$meth  = (string) ( $m['method'] ?? $row['provider'] );
		$type  = FLXB_Types::get( (int) $row['type_id'] );
		$cert  = $type && FLXB_WC_Cart::type_has_certificate( $type ) && '' === (string) $row['certificate_code'] ? 'online' : ''; // 2.2.1
		$title = function_exists( 'flxc_donation_methods' ) ? (string) ( flxc_donation_methods()[ $meth ] ?? '' ) : '';
		self::$busy = true;
		try {
			$order = self::create_order(
				array(
					'lines'          => array(
						array(
							'type_id'     => (int) $row['type_id'],
							'option'      => (string) $row['option_key'],
							'units'       => max( 1, (int) $row['quantity'] ),
							'amount'      => (float) $row['amount'],
							'for_other'   => (bool) $names,
							'ben_name'    => $names ? implode( ', ', array_slice( $names, 0, 3 ) ) . ( count( $names ) > 3 ? ' …' : '' ) : '',
							'donation_id' => (int) $id,
							'cert'        => $cert,
						),
					),
					'billing'        => array(
						'first_name' => $parts ? implode( ' ', $parts ) : $name,
						'last_name'  => $last,
						'email'      => $donor ? (string) $donor['email'] : '',
						'phone'      => $donor ? (string) $donor['phone'] : '',
					),
					'payment_method' => 'bank_transfer' === $meth ? FLXB_WC_Havale::ID : '',
					'payment_title'  => 'bank_transfer' === $meth ? '' : $title,
					'status'         => 'completed' === $row['status'] ? 'completed' : 'pending',
					'created_via'    => self::VIA,
					/* translators: %d: donation id */
					'note'           => sprintf( __( 'Recorded in Flux Chat (donation #%d).', 'flx-donate' ), (int) $id ),
					'emails'         => (bool) apply_filters( 'flxb_wc_callcenter_emails', false, $id ),
				)
			);
		} finally {
			self::$busy = false;
		}
		if ( is_wp_error( $order ) ) {
			FLXB_Donations::update_meta( (int) $id, array( 'wc_error' => $order->get_error_message() ) );
			return;
		}
		if ( '' !== (string) ( $m['reference'] ?? '' ) ) {
			$order->update_meta_data( '_flxb_callcenter_ref', (string) $m['reference'] );
			$order->save_meta_data();
		}
	}

	/** A donation's status changed outside WooCommerce (Flux Donate admin, Flux Chat): follow it. */
	public static function follow( $id, $upd ) {
		if ( self::$busy || FLXB_WC_Ledger::$syncing || ! isset( $upd['status'] ) ) {
			return;
		}
		$oid   = FLXB_WC_Ledger::order_of( (int) $id );
		$order = $oid ? wc_get_order( $oid ) : null;
		if ( ! $order ) {
			return;
		}
		self::$busy = true;
		try {
			switch ( $upd['status'] ) {
				case 'completed':
					if ( ! $order->is_paid() ) {
						$order->payment_complete();
					}
					break;
				case 'canceled':
					if ( ! $order->has_status( array( 'cancelled', 'refunded' ) ) ) {
						$order->update_status( 'cancelled', __( 'Canceled in Flux Donate.', 'flx-donate' ) );
					}
					break;
				case 'pending':
					/* 2.14.0: put on hold by hand in Flux Donate (also a gift that was marked paid). */
					if ( ! empty( $upd['manual'] ) && ! $order->has_status( array( 'on-hold', 'pending', 'refunded' ) ) ) {
						$order->update_status( 'on-hold', __( 'Put on hold in Flux Donate.', 'flx-donate' ) );
					}
					break;
				case 'failed':
					if ( ! $order->is_paid() ) {
						$order->update_status( 'failed', __( 'Marked as failed in Flux Donate.', 'flx-donate' ) );
					}
					break;
			}
		} finally {
			self::$busy = false;
		}
	}
}
