<?php
/**
 * Flux Donate 2.2.0: referral person of a donation (Flux Roles).
 *
 *  - Column ref_user_id on donations and recurring donations. Where it comes from, in order:
 *    the order's `_flx_ref_user` (Flux Roles writes it from the ?ref= link), the call-centre agent who
 *    recorded the donation (when they may be a referral, cap `flx_referans`), the person picked on
 *    "Add donation". Monthly charges carry the first donation's referral.
 *  - Capabilities: `flxb_view_own` (Donations menu with only the donations they referred) and
 *    `flxb_dashboard` (the dashboard with overall totals). Reported to Flux Roles through
 *    `flx_role_caps_supported`.
 *  - 2.3.0 (Flux Roles 1.1.0): `flxb_create_ref` (donation request on behalf of a donor, the referral is the
 *    staff member, the donor gets a payment link), `flxb_donors_own` (donors of their referred gifts),
 *    `flxb_ref_notify` (action `flxb_ref_notify` when a referred gift is paid, cancelled or refunded;
 *    Flux Chat sends the WhatsApp message).
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

final class FLXB_Refs {

	const CAP_REF  = 'flx_referans';
	const CAP_OWN  = 'flxb_view_own';
	const CAP_DASH = 'flxb_dashboard';
	const CAP_CREATE = 'flxb_create_ref';
	const CAP_DONORS = 'flxb_donors_own';
	const CAP_NOTIFY = 'flxb_ref_notify';

	/** @var array Gifts whose status changed in this request: key => donation id (sent at shutdown). */
	private static $events = array();

	public static function init() {
		add_filter( 'flx_role_caps_supported', array( __CLASS__, 'supported' ) );
		add_action( 'flxb_donation_recorded', array( __CLASS__, 'on_recorded' ), 30 );
		add_action( 'flxb_donation_updated', array( __CLASS__, 'on_updated' ), 30, 2 );
	}

	public static function supported( $caps ) {
		return array_values( array_unique( array_merge( (array) $caps, array( self::CAP_OWN, self::CAP_DASH, self::CAP_CREATE, self::CAP_DONORS, self::CAP_NOTIFY ) ) ) );
	}

	/** May add donations: managers record them, staff with flxb_create_ref open a payment request. */
	public static function can_create() {
		return flxb_can_manage() || ( is_user_logged_in() && current_user_can( self::CAP_CREATE ) );
	}

	/** Staff request form (not the manager's "record a received gift"). */
	public static function staff_create() {
		return ! flxb_can_manage() && is_user_logged_in() && current_user_can( self::CAP_CREATE );
	}

	/** Sees only the donors of the gifts they referred. */
	public static function donors_own() {
		return is_user_logged_in() && ! flxb_can_view() && current_user_can( self::CAP_DONORS );
	}

	public static function can_donors() {
		return flxb_can_view() || self::donors_own();
	}

	/** Has this person given a gift referred by the user? */
	public static function is_own_donor( $donor_id, $user_id = 0 ) {
		global $wpdb;
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$t       = flxb_table( 'donations' );
		return $user_id && (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$t} WHERE donor_id = %d AND ref_user_id = %d LIMIT 1", (int) $donor_id, $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/* ---------- flxb_ref_notify ---------- */

	public static function on_recorded( $id ) {
		self::queue( (int) $id );
	}

	public static function on_updated( $id, $upd ) {
		if ( isset( $upd['status'] ) || isset( $upd['ref_user_id'] ) ) {
			self::queue( (int) $id );
		}
	}

	private static function queue( $id ) {
		$d = $id ? FLXB_Donations::get( $id ) : null;
		if ( ! $d || ! (int) ( $d['ref_user_id'] ?? 0 ) || apply_filters( 'flx_silent', false, $d ) ) { // 2.26.0: no notice for imported history
			return;
		}
		$key = (int) $d['wc_order_id'] ? 'o' . (int) $d['wc_order_id'] : 'd' . (int) $d['id'];
		if ( ! self::$events ) {
			add_action( 'shutdown', array( __CLASS__, 'send' ), 5 );
		}
		self::$events[ $key ] = (int) $d['id'];
	}

	/** The event of a gift's status: paid, cancelled, refunded ('' = nothing to tell). */
	private static function kind( array $statuses ) {
		if ( in_array( 'refunded', $statuses, true ) ) {
			return 'refunded';
		}
		if ( in_array( 'completed', $statuses, true ) || in_array( 'partially_refunded', $statuses, true ) ) {
			return 'paid';
		}
		if ( $statuses && ! array_diff( $statuses, array( 'canceled', 'failed' ) ) ) {
			return 'cancelled';
		}
		return '';
	}

	/** Once per gift (an order counts once) and event, after all its lines are written. */
	public static function send() {
		$events       = self::$events;
		self::$events = array();
		foreach ( $events as $id ) {
			$d = FLXB_Donations::get( $id );
			if ( ! $d ) {
				continue;
			}
			$rows = FLXB_Donations::siblings( $d );
			$head = $rows[0];
			$kind = self::kind( array_values( array_unique( wp_list_pluck( $rows, 'status' ) ) ) );
			$ref  = (int) $head['ref_user_id'];
			$m    = FLXB_Donations::meta( $head );
			$done = isset( $m['ref_notified'] ) && is_array( $m['ref_notified'] ) ? $m['ref_notified'] : array();
			if ( '' === $kind || ! $ref || in_array( $kind, $done, true ) ) {
				continue;
			}
			$done[] = $kind;
			FLXB_Donations::update_meta( (int) $head['id'], array( 'ref_notified' => $done ) );
			if ( ! user_can( $ref, self::CAP_NOTIFY ) ) {
				continue;
			}
			$donor = (int) $head['donor_id'] ? FLXB_Donors::get( (int) $head['donor_id'] ) : null;
			$items = array();
			foreach ( $rows as $r ) {
				$t       = (int) $r['type_id'] ? FLXB_Types::get( (int) $r['type_id'] ) : null;
				$items[] = array(
					'title'    => $t ? (string) $t['title'] : '',
					'option'   => (string) $r['option_label'],
					'quantity' => (int) $r['quantity'],
					'amount'   => (float) $r['amount'],
				);
			}
			$gift = array(
				'donation_id' => (int) $head['id'],
				'order_id'    => (int) $head['wc_order_id'],
				'amount'      => array_sum( array_map( 'floatval', wp_list_pluck( $rows, 'amount' ) ) ),
				'currency'    => (string) $head['currency'],
				'donor_name'  => $donor ? (string) $donor['name'] : '',
				'donor_phone' => $donor ? (string) $donor['phone'] : '',
				'items'       => $items,
				'admin_url'   => FLXB_Admin_Url::page( 'flxb-donations', array( 'donation' => (int) $head['id'] ) ),
			);
			/**
			 * A gift the user referred was paid, cancelled or refunded (once per gift and event).
			 * Only for referrals with the flxb_ref_notify capability. Flux Chat sends the message.
			 *
			 * @param string $kind    paid | cancelled | refunded
			 * @param int    $user_id The referral (staff member).
			 * @param array  $gift    donation_id, order_id, amount, currency, donor_name, donor_phone, items[], admin_url
			 */
			do_action( 'flxb_ref_notify', $kind, $ref, $gift );
		}
	}

	/** Sees only the donations they referred (no full view). */
	public static function own_only() {
		return is_user_logged_in() && ! flxb_can_view() && current_user_can( self::CAP_OWN );
	}

	/** May open the donation list (all, or own). */
	public static function can_list() {
		return flxb_can_view() || self::own_only();
	}

	public static function can_dashboard() {
		return flxb_can_view() || ( is_user_logged_in() && current_user_can( self::CAP_DASH ) );
	}

	/** May this user be a referral? (0 = none) */
	public static function valid( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return 0;
		}
		$u = get_userdata( $user_id );
		return $u && ( $u->has_cap( self::CAP_REF ) || $u->has_cap( 'manage_options' ) ) ? $user_id : 0;
	}

	/** People who can be picked as the referral. */
	public static function users() {
		if ( function_exists( 'flxr_ref_users' ) ) {
			$list = (array) flxr_ref_users();
		} else {
			$list = get_users( array( 'capability' => self::CAP_REF, 'fields' => array( 'ID', 'display_name' ), 'orderby' => 'display_name' ) );
		}
		$out = array();
		foreach ( $list as $u ) {
			$u = (object) $u;
			if ( ! empty( $u->ID ) ) {
				$out[ (int) $u->ID ] = (string) $u->display_name;
			}
		}
		return $out;
	}

	public static function name( $user_id ) {
		$u = (int) $user_id ? get_userdata( (int) $user_id ) : null;
		return $u ? (string) $u->display_name : ( (int) $user_id ? '#' . (int) $user_id : '' );
	}

	/** 2.20.0: the referral of the donor's most recent referred gift (0 when none). */
	public static function donor_ref( $donor_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT ref_user_id FROM ' . flxb_table( 'donations' ) . " WHERE donor_id = %d AND ref_user_id > 0 AND status <> 'trash' ORDER BY id DESC LIMIT 1", (int) $donor_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * 2.20.0: the same for many donors at once (list rows).
	 *
	 * @return array donor id => referral user id
	 */
	public static function donor_refs( array $donor_ids ) {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $donor_ids ) ) ) );
		if ( ! $ids ) {
			return array();
		}
		$t   = flxb_table( 'donations' );
		$out = array();
		$rows = (array) $wpdb->get_results( "SELECT donor_id, ref_user_id FROM {$t} WHERE id IN (SELECT MAX(id) FROM {$t} WHERE donor_id IN (" . implode( ',', $ids ) . ") AND ref_user_id > 0 AND status <> 'trash' GROUP BY donor_id)", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ( $rows as $r ) {
			$out[ (int) $r['donor_id'] ] = (int) $r['ref_user_id'];
		}
		return $out;
	}

	/** Referral written on a WooCommerce order by Flux Roles. */
	public static function of_order( $order ) {
		if ( function_exists( 'flxr_order_ref_user' ) ) {
			return (int) flxr_order_ref_user( $order );
		}
		$order = $order instanceof WC_Order ? $order : ( function_exists( 'wc_get_order' ) ? wc_get_order( $order ) : null );
		return $order ? (int) $order->get_meta( '_flx_ref_user' ) : 0;
	}

	/**
	 * Referral of a donation being recorded: given → recurring donation's → call-centre agent.
	 *
	 * @param array $d Data passed to FLXB_Donations::record().
	 */
	public static function resolve( array $d ) {
		$ref = (int) ( $d['ref_user_id'] ?? 0 );
		if ( ! $ref && ! empty( $d['subscription_id'] ) ) {
			$s   = FLXB_Subscriptions::get( (int) $d['subscription_id'] );
			$ref = $s ? (int) ( $s['ref_user_id'] ?? 0 ) : 0;
		}
		if ( ! $ref && 'callcenter' === ( $d['source'] ?? '' ) && ! empty( $d['meta']['created_by'] ) ) {
			$u   = get_userdata( (int) $d['meta']['created_by'] );
			$ref = $u && $u->has_cap( self::CAP_REF ) ? (int) $u->ID : 0;
		}
		return max( 0, $ref );
	}

	/**
	 * Change the referral of a donation (with the other lines of its order, its recurring donation and
	 * the certificates made from them).
	 */
	public static function set( $donation_id, $user_id ) {
		global $wpdb;
		$d = FLXB_Donations::get( (int) $donation_id );
		if ( ! $d ) {
			return false;
		}
		$user_id = $user_id ? self::valid( $user_id ) : 0;
		$old     = (int) ( $d['ref_user_id'] ?? 0 );
		if ( $old === $user_id ) {
			return true;
		}
		$t = flxb_table( 'donations' );
		foreach ( FLXB_Donations::siblings( $d ) as $s ) {
			$wpdb->update( $t, array( 'ref_user_id' => $user_id, 'updated_at' => flxb_now() ), array( 'id' => (int) $s['id'] ) );
			FLXB_Donations::add_audit( (int) $s['id'], 'ref', array( 'ref' => array( self::name( $old ), self::name( $user_id ) ) ) );
			if ( '' !== (string) $s['certificate_code'] && function_exists( 'flxs_get_certificate_by_code' ) && function_exists( 'flxs_set_certificate_ref' ) ) {
				$c = flxs_get_certificate_by_code( (string) $s['certificate_code'] );
				if ( $c ) {
					flxs_set_certificate_ref( (int) $c['id'], $user_id );
				}
			}
		}
		if ( (int) $d['subscription_id'] ) {
			$wpdb->update( flxb_table( 'subscriptions' ), array( 'ref_user_id' => $user_id, 'updated_at' => flxb_now() ), array( 'id' => (int) $d['subscription_id'] ) );
		}
		if ( (int) $d['wc_order_id'] && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( (int) $d['wc_order_id'] );
			if ( $order ) {
				$order->update_meta_data( '_flx_ref_user', $user_id );
				$order->save_meta_data();
			}
		}
		do_action( 'flxb_donation_ref_changed', (int) $d['id'], $user_id, $old );
		return true;
	}

	/* ---------- flxb_create_ref ---------- */

	/** @see flxb_create_ref_request() */
	public static function create_request( array $a ) {
		if ( ! flxb_wc_active() ) {
			return new WP_Error( 'flxb_wc_inactive', __( 'WooCommerce is not active.', 'flx-donate' ) );
		}
		$ref = absint( $a['ref_user_id'] ?? get_current_user_id() );
		if ( ! $ref || ! ( user_can( $ref, self::CAP_CREATE ) || user_can( $ref, (string) apply_filters( 'flxb_manage_capability', 'manage_options' ) ) ) ) {
			return new WP_Error( 'flxb_ref_cap', __( 'This person may not open donation requests.', 'flx-donate' ) );
		}
		$type = FLXB_Types::get( absint( $a['type_id'] ?? 0 ) );
		if ( ! $type || 'open' !== flxb_type_state( $type ) ) {
			return new WP_Error( 'flxb_type', __( 'Please choose an open donation type.', 'flx-donate' ) );
		}
		$key   = sanitize_key( (string) ( $a['option'] ?? '' ) );
		$opt   = '' !== $key ? FLXB_Types::option( $type, $key ) : null;
		$units = max( 1, min( 1000, absint( $a['units'] ?? 1 ) ) );
		if ( '' !== $key && ( ! $opt || empty( $opt['active'] ) || (float) $opt['price'] <= 0 ) ) {
			return new WP_Error( 'flxb_option', __( 'Please choose an option of the selected donation type.', 'flx-donate' ) );
		}
		$amount = $opt ? round( (float) $opt['price'] * $units, 2 ) : round( (float) ( $a['amount'] ?? 0 ), 2 );
		if ( $amount <= 0 ) {
			return new WP_Error( 'flxb_amount', __( 'Please enter an amount.', 'flx-donate' ) );
		}
		$donor = wp_parse_args( (array) ( $a['donor'] ?? array() ), array( 'name' => '', 'phone' => '', 'email' => '' ) );
		$name  = sanitize_text_field( (string) $donor['name'] );
		$phone = sanitize_text_field( (string) $donor['phone'] );
		$email = sanitize_email( (string) $donor['email'] );
		if ( '' === $name ) {
			return new WP_Error( 'flxb_donor', __( 'Please write the donor\'s name.', 'flx-donate' ) );
		}
		if ( '' === preg_replace( '/\D/', '', $phone ) && ! is_email( $email ) ) {
			return new WP_Error( 'flxb_contact', __( 'Please give the donor\'s phone number or e-mail address.', 'flx-donate' ) );
		}
		$names = flxb_split_names( is_array( $a['names'] ?? null ) ? implode( "\n", array_filter( $a['names'], 'is_scalar' ) ) : (string) ( $a['names'] ?? '' ) );
		$names = array_slice( $names, 0, $units );
		$cert  = isset( $a['cert'] ) ? sanitize_key( (string) $a['cert'] ) : ( FLXB_WC_Cart::type_has_certificate( $type ) ? 'online' : '' );
		$parts = preg_split( '/\s+/u', $name );
		$last  = count( $parts ) > 1 ? array_pop( $parts ) : '';
		$staff = get_userdata( $ref );
		$order = flxb_create_order(
			array(
				'lines'       => array(
					array(
						'type_id'       => (int) $type['id'],
						'option'        => $opt ? $key : '',
						'units'         => $units,
						'amount'        => $amount,
						'cert'          => 'online' === $cert ? 'online' : '',
						'name'          => $names ? $names[0] : $name,
						'for_other'     => (bool) $names,
						'ben_name'      => $names ? implode( ', ', array_slice( $names, 0, 3 ) ) . ( count( $names ) > 3 ? ' …' : '' ) : '',
						'beneficiaries' => $names,
					),
				),
				'billing'     => array( 'first_name' => implode( ' ', $parts ), 'last_name' => $last, 'email' => is_email( $email ) ? $email : '', 'phone' => $phone ),
				'status'      => 'pending',
				'created_via' => 'flxb-ref',
				'ref_user_id' => $ref,
				/* translators: %s: staff member */
				'note'        => trim( sprintf( __( 'Donation request opened by %s.', 'flx-donate' ), $staff ? $staff->display_name : '#' . $ref ) . "\n" . sanitize_textarea_field( (string) ( $a['note'] ?? '' ) ) ),
			)
		);
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		global $wpdb;
		$ids     = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxb_table( 'donations' ) . ' WHERE wc_order_id = %d ORDER BY id ASC', (int) $order->get_id() ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$emailed = false;
		if ( is_email( $email ) && ( ! isset( $a['send_email'] ) || ! empty( $a['send_email'] ) ) && function_exists( 'WC' ) ) {
			$mails = WC()->mailer()->get_emails();
			if ( isset( $mails['WC_Email_Customer_Invoice'] ) ) {
				$mails['WC_Email_Customer_Invoice']->trigger( $order->get_id(), $order );
				$emailed = true;
			}
		}
		$out = array(
			'order_id'    => (int) $order->get_id(),
			'donation_id' => $ids ? (int) $ids[0] : 0,
			'pay_url'     => $order->get_checkout_payment_url(),
			'amount'      => (float) $order->get_total(),
			'currency'    => (string) $order->get_currency(),
			'emailed'     => $emailed,
		);
		/**
		 * A staff member opened a donation request; the donor should get the payment link.
		 *
		 * @param array $out     order_id, donation_id, pay_url, amount, currency, emailed
		 * @param int   $ref     The staff member (referral).
		 * @param array $contact name, phone, email of the donor.
		 */
		do_action( 'flxb_ref_request_created', $out, $ref, array( 'name' => $name, 'phone' => $phone, 'email' => $email ) );
		return $out;
	}
}
