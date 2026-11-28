<?php
/**
 * Flux Donate 2.0: monthly donations through WooCommerce.
 *
 * The cart line carries "monthly". The first order is paid on the WooCommerce checkout with Flux POS's card
 * method; Flux Donate marks such an order (`_flxpos_save` = yes) so Flux POS stores the card at the payment
 * provider. When that order is paid, each monthly line becomes a Flux Donate recurring donation (provider
 * flxpos, reference "wc-<order>-<item>", card ids in its meta, meta.wc = parent order / item / line). The
 * renewal cron (FLXB_Renewals) opens a pending WooCommerce renewal order on each due date (reused for retries)
 * and charges it through Flux POS; Flux POS pays the order and the ledger records it like any other paid
 * order. Past due and cancel work as before.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

class FLXB_WC_Recurring {

	const VIA = 'flxb-renewal';

	public static function init() {
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_status' ), 10, 4 );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'maybe_start' ), 5 );
		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'gateways' ), 50 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'mark_order' ), 20 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'mark_order' ), 20 );
		add_action( 'woocommerce_before_pay_action', array( __CLASS__, 'mark_order' ), 5 );
	}

	/** Monthly lines in the cart, or in the order being paid (order-pay page). */
	public static function context_monthly() {
		global $wp;
		if ( isset( $wp->query_vars['order-pay'] ) && function_exists( 'is_checkout_pay_page' ) && is_checkout_pay_page() ) {
			$o = wc_get_order( absint( $wp->query_vars['order-pay'] ) );
			return $o && FLXB_WC_Cart::order_has_monthly( $o );
		}
		return FLXB_WC_Cart::cart_has_monthly();
	}

	/** A monthly gift needs a stored card: only Flux POS's card method is offered. */
	public static function gateways( $gateways ) {
		if ( ! is_array( $gateways ) || ( is_admin() && ! wp_doing_ajax() ) || ! self::context_monthly() ) {
			return $gateways;
		}
		return isset( $gateways[ FLXB_Renewals::GATEWAY ] ) ? array( FLXB_Renewals::GATEWAY => $gateways[ FLXB_Renewals::GATEWAY ] ) : array();
	}

	/** An order with monthly lines asks Flux POS to store the card. */
	public static function mark_order( $order ) {
		if ( ! $order instanceof WC_Order || ! FLXB_WC_Cart::order_has_monthly( $order ) ) {
			return;
		}
		$order->update_meta_data( '_flxpos_save', 'yes' );
		if ( ! doing_action( 'woocommerce_checkout_create_order' ) ) {
			$order->save_meta_data();
		}
	}

	public static function on_status( $order_id, $from, $to, $order = null ) {
		if ( in_array( $to, array( 'processing', 'completed' ), true ) ) {
			self::maybe_start( $order instanceof WC_Order ? $order : $order_id );
		}
	}

	/** A paid first order: turn its monthly lines into recurring donations. */
	public static function maybe_start( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order || self::VIA === $order->get_created_via() || ! $order->is_paid() ) {
			return;
		}
		$card = function_exists( 'flxpos_saved_card' ) ? flxpos_saved_card( $order ) : null;
		foreach ( FLXB_WC_Cart::order_lines( $order ) as $item_id => $item ) {
			if ( ! (int) $item->get_meta( 'flxb_monthly' ) || (int) $item->get_meta( '_flxb_subscription_id' ) || $item->get_meta( '_flxb_monthly_not_saved' ) ) {
				continue;
			}
			if ( ! is_array( $card ) ) {
				$item->update_meta_data( '_flxb_monthly_not_saved', 1 );
				$item->save();
				/* translators: %s: line name */
				$order->add_order_note( sprintf( __( '"%s" was paid once: the card was not stored, so it will not repeat monthly.', 'flx-donate' ), $item->get_name() ) );
				continue;
			}
			self::start_line( $order, $item, $card );
		}
	}

	private static function start_line( WC_Order $order, WC_Order_Item_Product $item, array $card ) {
		$l      = FLXB_WC_Cart::item_line( $item );
		$amount = FLXB_WC_Ledger::line_amount( $item );
		$ref    = 'wc-' . $order->get_id() . '-' . $item->get_id();
		$t      = FLXB_Types::get( (int) $l['type_id'] );
		$opt    = $t && '' !== (string) ( $l['option'] ?? '' ) ? FLXB_Types::option( $t, (string) $l['option'] ) : null;
		$units  = max( 1, (int) ( $l['units'] ?? 1 ) ) * max( 1, (int) $item->get_quantity() );
		$label  = $opt ? (string) $opt['label'] : __( 'Free amount', 'flx-donate' );
		$buyer  = array(
			'name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'email' => FLXB_WC_Contact::email( $order ),
			'phone' => $order->get_billing_phone(),
		);
		$donor  = FLXB_Donors::upsert( array_merge( $buyer, array( 'allow_anonymous' => true ) ) );
		$form   = isset( $l['form'] ) && is_array( $l['form'] ) ? $l['form'] : array();
		$id     = FLXB_Subscriptions::upsert(
			array(
				'donor_id'       => $donor,
				'amount'         => $amount,
				'currency'       => $order->get_currency(),
				'interval_unit'  => 'month',
				'status'         => 'active',
				'provider'       => FLXB_Renewals::PROVIDER,
				'provider_ref'   => $ref,
				'form_id'        => (int) ( $form['id'] ?? 0 ),
				'type_id'        => (int) $l['type_id'],
				'track'          => (string) ( $form['track'] ?? '' ),
				'ref_user_id'    => FLXB_Refs::of_order( $order ),
				'next_charge_at' => FLXB_Subscriptions::next_charge( 'month', current_time( 'mysql', true ) ),
			)
		);
		if ( ! $id ) {
			return 0;
		}
		$line = $l;
		unset( $line['cert'], $line['name'], $line['address'], $line['send_me'] );
		FLXB_Subscriptions::update(
			$id,
			array(
				'card'          => $card,
				'snapshot'      => array(
					'option_key'   => $opt ? (string) $opt['key'] : '',
					'option_label' => $label,
					'quantity'     => $units,
					'unit_price'   => $units ? round( $amount / $units, 4 ) : $amount,
					'lines'        => array(
						array(
							'key'        => $opt ? (string) $opt['key'] : '',
							'label'      => $label,
							'units'      => $units,
							'unit_price' => $opt ? FLXB_Types::option_price( $t, $opt, $order->get_currency() ) : $amount,
							'amount'     => $amount,
							'unit'       => $opt ? (string) $opt['unit'] : '',
							'units_per'  => $opt ? (int) $opt['units_per'] : 1,
						),
					),
				),
				'buyer'         => $buyer,
				'buyer_ip'      => (string) $order->get_customer_ip_address(),
				'form_name'     => $item->get_name(),
				'first_payment' => (string) $order->get_transaction_id(),
				'retry_count'   => 0,
				'wc'            => array(
					'order'    => (int) $order->get_id(),
					'item'     => (int) $item->get_id(),
					'product'  => (int) $item->get_product_id(),
					'customer' => (int) $order->get_customer_id(),
					'line'     => $line,
				),
			),
			array( 'status' => 'active' )
		);
		$item->update_meta_data( '_flxb_subscription_id', $id );
		$item->save();
		/* translators: 1: line name, 2: recurring donation id */
		$order->add_order_note( sprintf( __( '"%1$s" repeats monthly (recurring donation #%2$d); the card is stored at the payment provider.', 'flx-donate' ), $item->get_name(), $id ) );
		FLXB_Renewals::mail_donor( FLXB_Subscriptions::get( $id ), 'started' );
		do_action( 'flxb_wc_recurring_started', $id, $order, $item );
		return $id;
	}

	/* ------------------------------------------------------------------
	 * Renewals (called by FLXB_Renewals)
	 * ------------------------------------------------------------------ */

	/** Is this recurring donation charged through WooCommerce renewal orders? */
	public static function handles( array $s ) {
		$m = FLXB_Subscriptions::meta( $s );
		return ! empty( $m['wc']['order'] ) && FLXB_WC::ready();
	}

	/**
	 * The pending renewal order of a due period (created once, reused for retries).
	 *
	 * @return WC_Order|null
	 */
	public static function renewal_order( array $s, $period ) {
		/* Callers may hold the row from before the order was opened: read it again. */
		$fresh = FLXB_Subscriptions::get( (int) $s['id'] );
		$s     = $fresh ? $fresh : $s;
		$m     = FLXB_Subscriptions::meta( $s );
		$wc   = (array) ( $m['wc'] ?? array() );
		$open = (array) ( $m['wc_renewal'] ?? array() );
		if ( ! empty( $open[ $period ] ) ) {
			$o = wc_get_order( (int) $open[ $period ] );
			if ( $o && $o->has_status( array( 'pending', 'failed' ) ) ) {
				return $o;
			}
			if ( $o && $o->is_paid() ) {
				return $o;
			}
		}
		$parent = wc_get_order( (int) ( $wc['order'] ?? 0 ) );
		$line   = (array) ( $wc['line'] ?? array() );
		$type   = FLXB_Types::get( (int) ( $line['type_id'] ?? $s['type_id'] ) );
		$pid    = $type ? FLXB_WC_Products::product_id( $type ) : (int) ( $wc['product'] ?? 0 );
		$prod   = $pid ? wc_get_product( $pid ) : null;
		if ( ! $prod ) {
			return null;
		}
		$order = wc_create_order(
			array(
				'customer_id' => (int) ( $wc['customer'] ?? 0 ),
				'created_via' => self::VIA,
			)
		);
		if ( is_wp_error( $order ) ) {
			return null;
		}
		$item = new WC_Order_Item_Product();
		$item->set_product( $prod );
		$item->set_quantity( 1 );
		$item->set_subtotal( (float) $s['amount'] );
		$item->set_total( (float) $s['amount'] );
		$line['monthly'] = 1;
		$line['cert']    = ''; // certificates come with the first payment
		FLXB_WC_Cart::write_line_meta( $item, $line, 1 );
		$item->add_meta_data( '_flxb_subscription_id', (int) $s['id'], true );
		$order->add_item( $item );
		if ( $parent ) {
			$order->set_address( $parent->get_address( 'billing' ), 'billing' );
		} else {
			$b = (array) ( $m['buyer'] ?? array() );
			$order->set_billing_first_name( (string) ( $b['name'] ?? '' ) );
			$order->set_billing_email( (string) ( $b['email'] ?? '' ) );
			$order->set_billing_phone( (string) ( $b['phone'] ?? '' ) );
		}
		$order->set_currency( (string) $s['currency'] );
		$order->set_payment_method( FLXB_Renewals::GATEWAY );
		$order->set_payment_method_title( __( 'Card, monthly', 'flx-donate' ) );
		$order->update_meta_data( '_flxb_subscription_id', (int) $s['id'] );
		$order->update_meta_data( '_flxb_renewal_of', (int) ( $wc['order'] ?? 0 ) );
		$order->update_meta_data( '_flxb_renewal_period', (string) $period );
		$order->calculate_totals( false );
		$order->set_status( 'pending' );
		/* translators: 1: recurring donation id, 2: parent order number */
		$order->add_order_note( sprintf( __( 'Monthly renewal of recurring donation #%1$d (first order #%2$s).', 'flx-donate' ), (int) $s['id'], $parent ? $parent->get_order_number() : '-' ) );
		$order->save();
		$open[ $period ] = (int) $order->get_id();
		FLXB_Subscriptions::update( (int) $s['id'], array( 'wc_renewal' => array_slice( $open, -6, null, true ) ) );
		return $order;
	}

	/**
	 * The card was charged (Flux POS paid the renewal order): record it in the ledger.
	 *
	 * @return int Donation id.
	 */
	public static function renewal_paid( array $s, $period ) {
		$order = self::renewal_order( $s, $period );
		if ( ! $order ) {
			return 0;
		}
		$ids  = FLXB_WC_Ledger::sync( wc_get_order( $order->get_id() ) );
		$m    = FLXB_Subscriptions::meta( FLXB_Subscriptions::get( (int) $s['id'] ) );
		$open = (array) ( $m['wc_renewal'] ?? array() );
		unset( $open[ $period ] );
		FLXB_Subscriptions::update( (int) $s['id'], array( 'wc_renewal' => $open ? $open : null ) );
		return $ids ? (int) reset( $ids ) : 0;
	}

	/** A charge failed: note it on the renewal order; fail the order after the last attempt. */
	public static function renewal_failed( array $s, $period, $error, $final ) {
		$order = self::renewal_order( $s, $period );
		if ( ! $order ) {
			return;
		}
		if ( $final ) {
			/* translators: %s: error */
			$order->update_status( 'failed', sprintf( __( 'Monthly charge failed after the last attempt: %s', 'flx-donate' ), $error ) );
		} else {
			/* translators: %s: error */
			$order->add_order_note( sprintf( __( 'Monthly charge failed, it will be retried: %s', 'flx-donate' ), $error ) );
		}
	}
}
