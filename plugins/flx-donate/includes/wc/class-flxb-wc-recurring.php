<?php
/**
 * Flux Donate 2.0: monthly donations through WooCommerce.
 *
 * The cart line carries "monthly". The first order is paid with the Flux Donate iyzico gateway, which
 * asks iyzico to store the card. When that order is paid, each monthly line becomes a Flux Donate
 * recurring donation (provider iyzico, reference "wc-<order>-<item>", card sealed in its meta,
 * meta.wc = parent order / item / line). Flux Donate's renewal cron (FLXB_Iyzico_Recurring) charges the
 * card on each due date; for these donations it first opens a pending WooCommerce renewal order
 * (reused for retries), completes it when iyzico accepts the charge and fails it after the last
 * attempt. The ledger then records the renewal like any other paid order. Past due and cancel work
 * as before.
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
		$card = $order->get_meta( '_flxb_iyz_card' );
		foreach ( FLXB_WC_Cart::order_lines( $order ) as $item_id => $item ) {
			if ( ! (int) $item->get_meta( 'flxb_monthly' ) || (int) $item->get_meta( '_flxb_subscription_id' ) || $item->get_meta( '_flxb_monthly_not_saved' ) ) {
				continue;
			}
			if ( FLXB_WC_Iyzico::ID !== $order->get_payment_method() || ! is_array( $card ) || empty( $card['k'] ) || empty( $card['t'] ) ) {
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
				'provider'       => 'iyzico',
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
		$info = $order->get_meta( '_flxb_iyzico' );
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
				'first_payment' => is_array( $info ) ? (string) ( $info['paymentId'] ?? '' ) : '',
				'retry_count'   => 0,
				'sandbox'       => (bool) $order->get_meta( '_flxb_iyz_sandbox' ),
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
		$order->add_order_note( sprintf( __( '"%1$s" repeats monthly (recurring donation #%2$d); the card is stored at iyzico.', 'flx-donate' ), $item->get_name(), $id ) );
		FLXB_Iyzico_Recurring::mail_donor( FLXB_Subscriptions::get( $id ), 'started' );
		do_action( 'flxb_wc_recurring_started', $id, $order, $item );
		return $id;
	}

	/* ------------------------------------------------------------------
	 * Renewals (called by FLXB_Iyzico_Recurring)
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
		$order->set_payment_method( FLXB_WC_Iyzico::ID );
		$order->set_payment_method_title( __( 'Card (iyzico), monthly', 'flx-donate' ) );
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
	 * The card was charged: complete the renewal order (the ledger records it).
	 *
	 * @return int Donation id.
	 */
	public static function renewal_paid( array $s, $period, array $r ) {
		$order = self::renewal_order( $s, $period );
		if ( ! $order ) {
			return 0;
		}
		$info = FLXB_Iyzico_Api::payment_info( $r );
		if ( ! $order->is_paid() ) {
			$order->update_meta_data( '_flxb_iyzico', $info );
			$order->save_meta_data();
			/* translators: 1: card, 2: payment id */
			$order->add_order_note( sprintf( __( 'Monthly charge accepted by iyzico: %1$s, payment id %2$s.', 'flx-donate' ), '' !== $info['card'] ? $info['card'] : '-', $info['paymentId'] ) );
			$order->payment_complete( $info['paymentId'] );
		}
		$ids = FLXB_WC_Ledger::sync( wc_get_order( $order->get_id() ) );
		$m   = FLXB_Subscriptions::meta( FLXB_Subscriptions::get( (int) $s['id'] ) );
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
