<?php
/**
 * Flux Donate: recurring donations (one row per provider subscription).
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Subscriptions {

	const STATUSES = array( 'active', 'past_due', 'canceled' );

	/** Provider status → ours. */
	public static function map_status( $s ) {
		$s = strtolower( (string) $s );
		if ( in_array( $s, array( 'active', 'trialing', 'completed' ), true ) ) {
			return 'active';
		}
		if ( in_array( $s, array( 'past_due', 'unpaid', 'incomplete' ), true ) ) {
			return 'past_due';
		}
		return 'canceled';
	}

	public static function get( $id ) {
		global $wpdb;
		$t = flxb_table( 'subscriptions' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function get_by_ref( $provider, $ref ) {
		global $wpdb;
		if ( '' === (string) $ref ) {
			return null;
		}
		$t = flxb_table( 'subscriptions' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE provider = %s AND provider_ref = %s", (string) $provider, (string) $ref ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** @return int subscription id */
	public static function upsert( array $d ) {
		global $wpdb;
		$t        = flxb_table( 'subscriptions' );
		$provider = sanitize_key( (string) ( $d['provider'] ?? '' ) );
		$ref      = sanitize_text_field( (string) ( $d['provider_ref'] ?? '' ) );
		$existing = self::get_by_ref( $provider, $ref );
		$interval = strtolower( (string) ( $d['interval_unit'] ?? 'month' ) );
		$interval = in_array( $interval, array( 'day', 'week', 'month', 'year' ), true ) ? $interval : 'month';
		$status   = in_array( $d['status'] ?? '', self::STATUSES, true ) ? $d['status'] : 'active';
		$now      = flxb_now();
		if ( $existing ) {
			$upd = array( 'updated_at' => $now );
			foreach ( array( 'donor_id', 'form_id', 'type_id', 'ref_user_id' ) as $k ) {
				if ( ! empty( $d[ $k ] ) && empty( $existing[ $k ] ) ) {
					$upd[ $k ] = (int) $d[ $k ];
				}
			}
			if ( ! empty( $d['track'] ) && '' === (string) $existing['track'] ) {
				$upd['track'] = sanitize_text_field( (string) $d['track'] );
			}
			if ( ! empty( $d['next_charge_at'] ) ) {
				$upd['next_charge_at'] = $d['next_charge_at'];
			}
			if ( isset( $d['status'] ) && $status !== $existing['status'] ) {
				$upd['status'] = $status;
				if ( 'canceled' === $status && empty( $existing['canceled_at'] ) ) {
					$upd['canceled_at'] = $d['canceled_at'] ?? $now;
				}
			}
			$wpdb->update( $t, $upd, array( 'id' => (int) $existing['id'] ) );
			return (int) $existing['id'];
		}
		$wpdb->insert(
			$t,
			array(
				'donor_id'       => (int) ( $d['donor_id'] ?? 0 ),
				'amount'         => (float) ( $d['amount'] ?? 0 ),
				'currency'       => strtoupper( (string) ( $d['currency'] ?? flxb_default_currency() ) ),
				'interval_unit'  => $interval,
				'status'         => $status,
				'provider'       => $provider,
				'provider_ref'   => $ref,
				'form_id'        => (int) ( $d['form_id'] ?? 0 ),
				'type_id'    => (int) ( $d['type_id'] ?? 0 ),
				'track'          => sanitize_text_field( (string) ( $d['track'] ?? '' ) ),
				'ref_user_id'    => (int) ( $d['ref_user_id'] ?? 0 ),
				'next_charge_at' => $d['next_charge_at'] ?? null,
				'started_at'     => $d['started_at'] ?? $now,
				'canceled_at'    => 'canceled' === $status ? ( $d['canceled_at'] ?? $now ) : null,
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function set_status( $provider, $ref, $provider_status, array $extra = array() ) {
		$row = self::get_by_ref( $provider, $ref );
		if ( ! $row ) {
			return 0;
		}
		return self::upsert( array_merge( $extra, array( 'provider' => $provider, 'provider_ref' => $ref, 'status' => self::map_status( $provider_status ) ) ) );
	}

	/** Provider data kept with a recurring donation (never shown or exported as is). */
	public static function meta( $row ) {
		$m = is_array( $row ) ? json_decode( (string) ( $row['meta'] ?? '' ), true ) : null;
		return is_array( $m ) ? $m : array();
	}

	/** Merge keys into a recurring donation's meta (null removes a key); other columns optional. */
	public static function update( $id, array $meta = array(), array $cols = array() ) {
		global $wpdb;
		$row = self::get( $id );
		if ( ! $row ) {
			return false;
		}
		$m = self::meta( $row );
		foreach ( $meta as $k => $v ) {
			if ( null === $v ) {
				unset( $m[ $k ] );
			} else {
				$m[ $k ] = $v;
			}
		}
		$cols = array_intersect_key( $cols, array_flip( array( 'status', 'next_charge_at', 'canceled_at', 'donor_id', 'amount' ) ) );
		if ( isset( $cols['status'] ) && ! in_array( $cols['status'], self::STATUSES, true ) ) {
			unset( $cols['status'] );
		}
		$cols['meta']       = $m ? wp_json_encode( $m ) : null;
		$cols['updated_at'] = flxb_now();
		return false !== $wpdb->update( flxb_table( 'subscriptions' ), $cols, array( 'id' => (int) $id ) );
	}

	/** Next charge after $from (UTC) for an interval. */
	public static function next_charge( $interval, $from = '' ) {
		$ts  = $from ? strtotime( $from . ' UTC' ) : time();
		$map = array( 'day' => '+1 day', 'week' => '+1 week', 'month' => '+1 month', 'year' => '+1 year' );
		return gmdate( 'Y-m-d H:i:s', strtotime( $map[ $interval ] ?? '+1 month', $ts ) );
	}

	/** Monthly value of one subscription. */
	public static function monthly( $amount, $interval ) {
		$f = array( 'day' => 30, 'week' => 52 / 12, 'month' => 1, 'year' => 1 / 12 );
		return (float) $amount * ( $f[ $interval ] ?? 1 );
	}

	public static function query( array $a ) {
		global $wpdb;
		$a     = wp_parse_args( $a, array( 'status' => '', 'search' => '', 'per_page' => 25, 'page' => 1, 'donor_id' => 0 ) );
		$t     = flxb_table( 'subscriptions' );
		$d     = flxb_table( 'donors' );
		$where = array( '1=1' );
		$prm   = array();
		if ( in_array( $a['status'], self::STATUSES, true ) ) {
			$where[] = 's.status = %s';
			$prm[]   = $a['status'];
		}
		if ( $a['donor_id'] ) {
			$where[] = 's.donor_id = %d';
			$prm[]   = (int) $a['donor_id'];
		}
		if ( '' !== $a['search'] ) {
			$like    = '%' . $wpdb->esc_like( $a['search'] ) . '%';
			$where[] = '(d.name LIKE %s OR d.email LIKE %s OR d.phone LIKE %s OR s.provider_ref LIKE %s)';
			array_push( $prm, $like, $like, $like, $like );
		}
		$w     = implode( ' AND ', $where );
		$from  = "FROM {$t} s LEFT JOIN {$d} d ON d.id = s.donor_id WHERE {$w}";
		$total = (int) ( $prm ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$from}", $prm ) ) : $wpdb->get_var( "SELECT COUNT(*) {$from}" ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$limit = $wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $a['per_page'], ( max( 1, (int) $a['page'] ) - 1 ) * (int) $a['per_page'] );
		$sql   = "SELECT s.*, d.name AS donor_name, d.email AS donor_email, d.phone AS donor_phone {$from} ORDER BY CASE s.status WHEN 'active' THEN 0 WHEN 'past_due' THEN 1 WHEN 'canceled' THEN 2 ELSE 3 END, s.id DESC" . $limit;
		$items = $prm ? $wpdb->get_results( $wpdb->prepare( $sql, $prm ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		return array( 'items' => (array) $items, 'total' => $total );
	}

	/** MRR (default currency) and active count. */
	public static function totals() {
		global $wpdb;
		$t    = flxb_table( 'subscriptions' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT amount, interval_unit FROM {$t} WHERE status = 'active' AND currency = %s", flxb_default_currency() ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$mrr  = 0.0;
		foreach ( (array) $rows as $r ) {
			$mrr += self::monthly( $r['amount'], $r['interval_unit'] );
		}
		return array(
			'mrr'      => round( $mrr, 2 ),
			'active'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE status = 'active'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'past_due' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE status = 'past_due'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'canceled' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE status = 'canceled'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}
}
