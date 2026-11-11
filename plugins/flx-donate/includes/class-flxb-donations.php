<?php
/**
 * Flux Donate: donations. Idempotent: one row per provider payment ("uniq" = provider:reference),
 * so the same payment seen by the form submit, a webhook, the hourly sync and the backfill
 * is stored once.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Donations {

	const STATUSES = array( 'completed', 'pending', 'failed', 'canceled', 'refunded', 'partially_refunded', 'trash' );

	/** Statuses that count as money received. */
	const PAID = array( 'completed', 'partially_refunded' );

	public static function uniq( $provider, $ref, $fallback = '' ) {
		$provider = sanitize_key( (string) $provider );
		$ref      = (string) $ref;
		return mb_substr( '' !== $ref ? $provider . ':' . $ref : $fallback, 0, 191 );
	}

	public static function get( $id ) {
		global $wpdb;
		$t = flxb_table( 'donations' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function get_by_uniq( $uniq ) {
		global $wpdb;
		$t = flxb_table( 'donations' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE uniq = %s", (string) $uniq ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function get_by_track( $track ) {
		global $wpdb;
		if ( '' === (string) $track ) {
			return null;
		}
		$t = flxb_table( 'donations' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE track = %s ORDER BY id ASC LIMIT 1", (string) $track ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Store (or complete) a donation.
	 *
	 * @param array $d ref_user_id (referral, see FLXB_Refs::resolve()), provider, provider_ref, amount, currency, type, status, form_id, track, pay_id,
	 *                 donor (name/email/phone/consents), donor_id, subscription_id, type_id,
	 *                 option_key, option_label, quantity, unit_price (snapshot of the type's price),
	 *                 certificate_code, source, wc_order_id (WooCommerce order), created_at (UTC), meta (array),
	 *                 lines (line items, see FLXB_Items::create()), beneficiaries (names, proxy types),
	 *                 beneficiary_phones (2.16.0: phone per name, same order).
	 * @return int Donation id (0 on failure).
	 */
	public static function record( array $d ) {
		global $wpdb;
		$t        = flxb_table( 'donations' );
		$provider = sanitize_key( (string) ( $d['provider'] ?? '' ) );
		$ref      = sanitize_text_field( (string) ( $d['provider_ref'] ?? '' ) );
		$uniq     = ! empty( $d['uniq'] ) ? (string) $d['uniq'] : self::uniq( $provider, $ref, ! empty( $d['pay_id'] ) ? 'pay:' . (int) $d['pay_id'] : 'track:' . ( $d['track'] ?? '' ) );
		if ( '' === $uniq || 'track:' === $uniq ) {
			return 0;
		}
		$status = in_array( $d['status'] ?? '', self::STATUSES, true ) ? $d['status'] : 'completed';
		$type   = 'recurring' === ( $d['type'] ?? '' ) ? 'recurring' : 'one_time';

		$donor_id = (int) ( $d['donor_id'] ?? 0 );
		if ( ! $donor_id && ! empty( $d['donor'] ) && is_array( $d['donor'] ) ) {
			$donor_id = FLXB_Donors::upsert( $d['donor'] );
		}
		$created  = ! empty( $d['created_at'] ) ? (string) $d['created_at'] : flxb_now();
		$form_id  = (int) ( $d['form_id'] ?? 0 );
		$type_id = isset( $d['type_id'] ) ? (int) $d['type_id'] : FLXB_Types::for_form( $form_id, $created );

		$ref_user = FLXB_Refs::resolve( $d );

		$existing = self::get_by_uniq( $uniq );
		if ( $existing ) {
			$upd = array();
			if ( $ref_user && ! (int) ( $existing['ref_user_id'] ?? 0 ) ) {
				$upd['ref_user_id'] = $ref_user;
			}
			if ( $status !== $existing['status'] && self::status_may_change( $existing['status'], $status ) ) {
				$upd['status'] = $status;
			}
			if ( $donor_id && ! (int) $existing['donor_id'] ) {
				$upd['donor_id'] = $donor_id;
			}
			foreach ( array( 'track', 'certificate_code', 'option_key', 'option_label' ) as $k ) {
				if ( ! empty( $d[ $k ] ) && '' === (string) $existing[ $k ] ) {
					$upd[ $k ] = sanitize_text_field( (string) $d[ $k ] );
				}
			}
			foreach ( array( 'form_id', 'pay_id', 'subscription_id', 'wc_order_id' ) as $k ) {
				if ( ! empty( $d[ $k ] ) && ! (int) $existing[ $k ] ) {
					$upd[ $k ] = (int) $d[ $k ];
				}
			}
			if ( $type_id && ! (int) $existing['type_id'] ) {
				$upd['type_id'] = $type_id;
			}
			if ( $upd ) {
				$upd['updated_at'] = flxb_now();
				$wpdb->update( $t, $upd, array( 'id' => (int) $existing['id'] ) );
				FLXB_Donors::recalc( $donor_id ? $donor_id : (int) $existing['donor_id'] );
				FLXB_Types::flush_progress( (int) ( $upd['type_id'] ?? $existing['type_id'] ) );
				do_action( 'flxb_donation_updated', (int) $existing['id'], $upd );
			}
			return (int) $existing['id'];
		}

		$ok = $wpdb->insert(
			$t,
			array(
				'uniq'             => $uniq,
				'donor_id'         => $donor_id,
				'amount'           => round( (float) ( $d['amount'] ?? 0 ), 4 ),
				'currency'         => strtoupper( (string) ( $d['currency'] ?? flxb_default_currency() ) ),
				'type'             => $type,
				'status'           => $status,
				'provider'         => $provider,
				'provider_ref'     => $ref,
				'subscription_id'  => (int) ( $d['subscription_id'] ?? 0 ),
				'pay_id'           => (int) ( $d['pay_id'] ?? 0 ),
				'form_id'          => $form_id,
				'track'            => sanitize_text_field( (string) ( $d['track'] ?? '' ) ),
				'type_id'      => $type_id,
				'option_key'       => sanitize_key( (string) ( $d['option_key'] ?? '' ) ),
				'option_label'     => mb_substr( sanitize_text_field( (string) ( $d['option_label'] ?? '' ) ), 0, 191 ),
				'quantity'         => max( 1, (int) ( $d['quantity'] ?? 1 ) ),
				'unit_price'       => round( (float) ( $d['unit_price'] ?? ( $d['amount'] ?? 0 ) ), 4 ),
				'certificate_code' => sanitize_text_field( (string) ( $d['certificate_code'] ?? '' ) ),
				'source'           => sanitize_key( (string) ( $d['source'] ?? '' ) ),
				'wc_order_id'      => (int) ( $d['wc_order_id'] ?? 0 ),
				'ref_user_id'      => $ref_user,
				'meta'             => ! empty( $d['meta'] ) ? wp_json_encode( $d['meta'] ) : null,
				'created_at'       => $created,
				'updated_at'       => flxb_now(),
			)
		);
		if ( ! $ok ) {
			/* Lost a race with another request inserting the same payment. */
			$again = self::get_by_uniq( $uniq );
			return $again ? (int) $again['id'] : 0;
		}
		$id = (int) $wpdb->insert_id;
		FLXB_Donors::recalc( $donor_id );
		$row = self::get( $id );
		if ( $row ) {
			FLXB_Items::create( $row, isset( $d['lines'] ) && is_array( $d['lines'] ) ? $d['lines'] : array(), isset( $d['beneficiaries'] ) && is_array( $d['beneficiaries'] ) ? $d['beneficiaries'] : array(), isset( $d['beneficiary_phones'] ) && is_array( $d['beneficiary_phones'] ) ? $d['beneficiary_phones'] : array() );
		}
		if ( $type_id ) {
			FLXB_Types::flush_progress( $type_id );
		}
		do_action( 'flxb_donation_recorded', $id, $d );
		return $id;
	}

	/** A refund or failure may follow a completion; a late "pending" never undoes one. */
	private static function status_may_change( $from, $to ) {
		if ( 'pending' === $to ) {
			return 'pending' === $from;
		}
		if ( in_array( $from, array( 'refunded' ), true ) ) {
			return false;
		}
		return true;
	}

	public static function set_status_by_uniq( $uniq, $status ) {
		$row = self::get_by_uniq( $uniq );
		if ( ! $row || ! in_array( $status, self::STATUSES, true ) || ! self::status_may_change( $row['status'], $status ) ) {
			return false;
		}
		global $wpdb;
		$wpdb->update( flxb_table( 'donations' ), array( 'status' => $status, 'updated_at' => flxb_now() ), array( 'id' => (int) $row['id'] ) );
		FLXB_Donors::recalc( (int) $row['donor_id'] );
		FLXB_Types::flush_progress( (int) $row['type_id'] );
		do_action( 'flxb_donation_updated', (int) $row['id'], array( 'status' => $status ) );
		return true;
	}

	/**
	 * 2.14.0: an admin sets the status by hand (Donations › detail). Unlike the provider paths this may
	 * also put a paid gift back on hold. Refunds go through the payment provider, not here.
	 *
	 * @return bool
	 */
	public static function set_status( $id, $status ) {
		$row = self::get( $id );
		if ( ! $row || ! in_array( $status, array( 'completed', 'pending', 'canceled', 'failed' ), true ) || $status === $row['status'] || in_array( $row['status'], array( 'refunded', 'partially_refunded', 'trash' ), true ) ) {
			return false;
		}
		global $wpdb;
		$wpdb->update( flxb_table( 'donations' ), array( 'status' => $status, 'updated_at' => flxb_now() ), array( 'id' => (int) $row['id'] ) );
		FLXB_Donors::recalc( (int) $row['donor_id'] );
		FLXB_Types::flush_progress( (int) $row['type_id'] );
		self::add_audit( (int) $row['id'], 'status', array( 'status' => array( $row['status'], $status ) ) );
		do_action( 'flxb_donation_updated', (int) $row['id'], array( 'status' => $status, 'manual' => 1 ) );
		return true;
	}

	public static function meta( array $d ) {
		$m = json_decode( (string) ( $d['meta'] ?? '' ), true );
		return is_array( $m ) ? $m : array();
	}

	/** Merge keys into a donation's meta. */
	public static function update_meta( $id, array $add ) {
		global $wpdb;
		$d = self::get( $id );
		if ( ! $d ) {
			return;
		}
		$wpdb->update( flxb_table( 'donations' ), array( 'meta' => wp_json_encode( array_merge( self::meta( $d ), $add ) ), 'updated_at' => flxb_now() ), array( 'id' => (int) $id ) );
	}

	/** Change log of admin corrections (meta.audit). */
	public static function add_audit( $id, $what, $changes ) {
		$d = self::get( $id );
		if ( ! $d ) {
			return;
		}
		$m          = self::meta( $d );
		$m['audit'] = isset( $m['audit'] ) && is_array( $m['audit'] ) ? $m['audit'] : array();
		$m['audit'][] = array( 'at' => flxb_now(), 'user' => get_current_user_id(), 'what' => (string) $what, 'changes' => $changes );
		self::update_meta( $id, array( 'audit' => $m['audit'] ) );
	}

	public static function set_certificate( $id, $code ) {
		global $wpdb;
		$wpdb->update( flxb_table( 'donations' ), array( 'certificate_code' => sanitize_text_field( (string) $code ), 'updated_at' => flxb_now() ), array( 'id' => (int) $id ) );
	}

	/** Filters shared by the list, the CSV export and the dashboard links. */
	public static function where( array $a, &$prm ) {
		global $wpdb;
		$where = array( '1=1' );
		$prm   = array();
		if ( ! empty( $a['from'] ) ) {
			$where[] = 'x.created_at >= %s';
			$prm[]   = flxb_local_day_to_utc( $a['from'] );
		}
		if ( ! empty( $a['to'] ) ) {
			$where[] = 'x.created_at <= %s';
			$prm[]   = flxb_local_day_to_utc( $a['to'], true );
		}
		if ( ! empty( $a['status'] ) && in_array( $a['status'], self::STATUSES, true ) ) {
			$where[] = 'x.status = %s';
			$prm[]   = $a['status'];
		} else {
			$where[] = "x.status <> 'trash'"; // 2.5.0: the trash is shown only when asked for
		}
		if ( isset( $a['stage'] ) && is_array( $a['stage'] ) ) {
			// 2.19.0: pending gifts with (or without) a receipt; the lists come from FLXB_Approval::stage_filter().
			$o     = array_filter( array_map( 'intval', (array) ( $a['stage']['orders'] ?? array() ) ) );
			$i     = array_filter( array_map( 'intval', (array) ( $a['stage']['ids'] ?? array() ) ) );
			$parts = array();
			if ( $o ) {
				$parts[] = 'x.wc_order_id IN (' . implode( ',', $o ) . ')';
			}
			if ( $i ) {
				$parts[] = '(x.wc_order_id = 0 AND x.id IN (' . implode( ',', $i ) . '))';
			}
			$in      = $parts ? '(' . implode( ' OR ', $parts ) . ')' : '0=1';
			$where[] = "x.status = 'pending'";
			$where[] = empty( $a['stage']['not'] ) ? $in : 'NOT ' . $in;
		}
		if ( ! empty( $a['type'] ) && in_array( $a['type'], array( 'one_time', 'recurring' ), true ) ) {
			$where[] = 'x.type = %s';
			$prm[]   = $a['type'];
		}
		foreach ( array( 'form_id', 'type_id', 'donor_id', 'subscription_id', 'ref_user_id' ) as $k ) {
			if ( isset( $a[ $k ] ) && '' !== (string) $a[ $k ] ) {
				$where[] = "x.$k = %d";
				$prm[]   = (int) $a[ $k ];
			}
		}
		if ( ! empty( $a['provider'] ) ) {
			$where[] = 'x.provider = %s';
			$prm[]   = sanitize_key( $a['provider'] );
		}
		if ( ! empty( $a['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( (string) $a['search'] ) . '%';
			$where[] = '(d.name LIKE %s OR d.email LIKE %s OR d.phone LIKE %s OR x.track LIKE %s OR x.provider_ref LIKE %s OR x.certificate_code LIKE %s)';
			array_push( $prm, $like, $like, $like, $like, $like, $like );
		}
		return implode( ' AND ', $where );
	}

	public static function query( array $a ) {
		global $wpdb;
		$a     = wp_parse_args( $a, array( 'per_page' => 25, 'page' => 1, 'orderby' => 'created_at', 'order' => 'DESC' ) );
		$t     = flxb_table( 'donations' );
		$d     = flxb_table( 'donors' );
		$prm   = array();
		$w     = self::where( $a, $prm );
		$from  = "FROM {$t} x LEFT JOIN {$d} d ON d.id = x.donor_id WHERE {$w}";
		$total = (int) ( $prm ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$from}", $prm ) ) : $wpdb->get_var( "SELECT COUNT(*) {$from}" ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$sum   = $prm ? $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN x.status IN ('completed','partially_refunded') THEN x.amount ELSE 0 END),0) {$from}", $prm ) ) : $wpdb->get_var( "SELECT COALESCE(SUM(CASE WHEN x.status IN ('completed','partially_refunded') THEN x.amount ELSE 0 END),0) {$from}" ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( ! empty( $a['group'] ) ) {
			return self::query_grouped( $a, $from, $prm, (float) $sum );
		}
		$cols  = array( 'created_at', 'amount' );
		$ob    = in_array( $a['orderby'], $cols, true ) ? 'x.' . $a['orderby'] : 'x.created_at';
		$order = 'ASC' === strtoupper( (string) $a['order'] ) ? 'ASC' : 'DESC';
		$open  = ! empty( $a['open_first'] ) ? "CASE WHEN x.status = 'pending' THEN 0 ELSE 1 END, " : '';
		$limit = (int) $a['per_page'] > 0 ? $wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $a['per_page'], ( max( 1, (int) $a['page'] ) - 1 ) * (int) $a['per_page'] ) : '';
		$sql   = "SELECT x.*, d.name AS donor_name, d.email AS donor_email, d.phone AS donor_phone {$from} ORDER BY {$open}{$ob} {$order}, x.id DESC" . $limit;
		$items = $prm ? $wpdb->get_results( $wpdb->prepare( $sql, $prm ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		return array( 'items' => (array) $items, 'total' => $total, 'sum' => (float) $sum );
	}

	/**
	 * Group key: the lines of one WooCommerce order are one gift in the list (2.0.9).
	 * 2.2.3: even = order, odd = donation; no subtraction, MySQL refuses a negative unsigned result.
	 */
	const GROUP_KEY = 'CASE WHEN x.wc_order_id > 0 THEN x.wc_order_id * 2 ELSE x.id * 2 + 1 END';

	/**
	 * One row per order (other donations stay one row each): the first line's row with the order's
	 * amount (group_amount), line count (group_lines) and statuses (group_statuses).
	 */
	private static function query_grouped( array $a, $from, array $prm, $sum ) {
		global $wpdb;
		$g     = self::GROUP_KEY;
		$t     = flxb_table( 'donations' );
		$d     = flxb_table( 'donors' );
		$run   = function ( $sql, $method = 'get_var' ) use ( $wpdb, $prm ) {
			$sql = $prm ? $wpdb->prepare( $sql, $prm ) : $sql; // phpcs:ignore WordPress.DB.PreparedSQL
			return 'get_var' === $method ? $wpdb->get_var( $sql ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		};
		$total = (int) $run( "SELECT COUNT(DISTINCT {$g}) {$from}" );
		$ob    = 'amount' === $a['orderby'] ? 'g_amount' : 'g_created';
		$open  = ! empty( $a['open_first'] ) ? 'g_open DESC, ' : ''; // 2.19.0: gifts still waiting for something come first
		$order = 'ASC' === strtoupper( (string) $a['order'] ) ? 'ASC' : 'DESC';
		$limit = (int) $a['per_page'] > 0 ? $wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $a['per_page'], ( max( 1, (int) $a['page'] ) - 1 ) * (int) $a['per_page'] ) : '';
		$heads = (array) $run( "SELECT MIN(x.id) AS g_id, SUM(x.amount) AS g_amount, COUNT(*) AS g_lines, GROUP_CONCAT(DISTINCT x.status) AS g_statuses, MAX(x.created_at) AS g_created, MAX(CASE WHEN x.status = 'pending' THEN 1 ELSE 0 END) AS g_open {$from} GROUP BY {$g} ORDER BY {$open}{$ob} {$order}, g_id DESC" . $limit, 'get_results' );
		$items = array();
		if ( $heads ) {
			$ids  = implode( ',', array_map( 'intval', wp_list_pluck( $heads, 'g_id' ) ) );
			$rows = array();
			foreach ( (array) $wpdb->get_results( "SELECT x.*, d.name AS donor_name, d.email AS donor_email, d.phone AS donor_phone FROM {$t} x LEFT JOIN {$d} d ON d.id = x.donor_id WHERE x.id IN ({$ids})", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL
				$rows[ (int) $r['id'] ] = $r;
			}
			foreach ( $heads as $h ) {
				$r = $rows[ (int) $h['g_id'] ] ?? null;
				if ( ! $r ) {
					continue;
				}
				$r['group_amount']   = (float) $h['g_amount'];
				$r['group_lines']    = (int) $h['g_lines'];
				$r['group_statuses'] = array_values( array_filter( explode( ',', (string) $h['g_statuses'] ) ) );
				$items[]             = $r;
			}
		}
		return array( 'items' => $items, 'total' => $total, 'sum' => $sum );
	}

	/** The other lines of the same WooCommerce order (with the donation itself), oldest first. */
	public static function siblings( array $d ) {
		global $wpdb;
		if ( ! (int) ( $d['wc_order_id'] ?? 0 ) ) {
			return array( $d );
		}
		$t = flxb_table( 'donations' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE wc_order_id = %d ORDER BY id ASC", (int) $d['wc_order_id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/* ------------------------------------------------------------------
	 * 2.5.0: trash and permanent delete
	 * ------------------------------------------------------------------ */

	/** Donation ids of a WooCommerce order. */
	public static function ids_of_order( $order_id ) {
		global $wpdb;
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxb_table( 'donations' ) . ' WHERE wc_order_id = %d', (int) $order_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** After rows changed: donor totals and type progress. */
	private static function touch( array $rows ) {
		foreach ( array_unique( array_map( 'intval', wp_list_pluck( $rows, 'donor_id' ) ) ) as $did ) {
			if ( $did ) {
				FLXB_Donors::recalc( $did );
			}
		}
		foreach ( array_unique( array_map( 'intval', wp_list_pluck( $rows, 'type_id' ) ) ) as $tid ) {
			if ( $tid ) {
				FLXB_Types::flush_progress( $tid );
			}
		}
	}

	/** Move donations to the trash (their status before is kept in meta.pre_trash). */
	public static function trash_rows( array $ids ) {
		global $wpdb;
		$rows = array();
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			$d = self::get( $id );
			if ( ! $d || 'trash' === $d['status'] ) {
				continue;
			}
			$m              = self::meta( $d );
			$m['pre_trash'] = $d['status'];
			$wpdb->update( flxb_table( 'donations' ), array( 'status' => 'trash', 'meta' => wp_json_encode( $m ), 'updated_at' => flxb_now() ), array( 'id' => $id ) );
			$rows[] = $d;
			do_action( 'flxb_donation_trashed', $id, $d );
		}
		self::touch( $rows );
		return count( $rows );
	}

	/** Take donations out of the trash (back to their status before). */
	public static function restore_rows( array $ids ) {
		global $wpdb;
		$rows = array();
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			$d = self::get( $id );
			if ( ! $d || 'trash' !== $d['status'] ) {
				continue;
			}
			$m  = self::meta( $d );
			$st = isset( $m['pre_trash'] ) && in_array( $m['pre_trash'], self::STATUSES, true ) && 'trash' !== $m['pre_trash'] ? $m['pre_trash'] : 'pending';
			unset( $m['pre_trash'] );
			$wpdb->update( flxb_table( 'donations' ), array( 'status' => $st, 'meta' => wp_json_encode( $m ), 'updated_at' => flxb_now() ), array( 'id' => $id ) );
			$rows[] = $d;
			do_action( 'flxb_donation_restored', $id, $st );
		}
		self::touch( $rows );
		return count( $rows );
	}

	/** Delete donations for good (with their line items). */
	public static function delete_rows( array $ids ) {
		global $wpdb;
		$rows = array();
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			$d = self::get( $id );
			if ( ! $d ) {
				continue;
			}
			$wpdb->delete( flxb_table( 'items' ), array( 'donation_id' => $id ) );
			$wpdb->delete( flxb_table( 'donations' ), array( 'id' => $id ) );
			$rows[] = $d;
			do_action( 'flxb_donation_deleted', $id, $d );
		}
		self::touch( $rows );
		return count( $rows );
	}

	/** Gifts awaiting approval, an order counted once (2.0.9; the dashboards use this). */
	public static function pending_count() {
		global $wpdb;
		$t = flxb_table( 'donations' );
		$g = self::GROUP_KEY;
		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT {$g}) FROM {$t} x WHERE x.status = 'pending'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** Gifts in the trash, an order counted once. */
	public static function trash_count() {
		global $wpdb;
		$t = flxb_table( 'donations' );
		$g = self::GROUP_KEY;
		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT {$g}) FROM {$t} x WHERE x.status = 'trash'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
