<?php
/**
 * Flux Donate: dashboard numbers. Amounts are in the default currency (other currencies are left out
 * of the sums, so totals never mix ₺ and $).
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Stats {

	const MAX_DAYS = 731;

	/** Validated [from, to] local Y-m-d for a preset (7|30|90) or custom range. */
	public static function range( $preset, $from = '', $to = '' ) {
		$today = wp_date( 'Y-m-d' );
		$re    = '/^\d{4}-\d{2}-\d{2}$/';
		if ( 'custom' === $preset && preg_match( $re, (string) $from ) && preg_match( $re, (string) $to ) ) {
			if ( $from > $to ) {
				list( $from, $to ) = array( $to, $from );
			}
			$days = self::days_between( $from, $to );
			if ( $days > self::MAX_DAYS ) {
				$from = self::shift( $to, -( self::MAX_DAYS - 1 ) );
			}
			return array( $from, $to );
		}
		$n = in_array( (int) $preset, array( 7, 30, 90, 365 ), true ) ? (int) $preset : 30;
		return array( self::shift( $today, -( $n - 1 ) ), $today );
	}

	public static function shift( $ymd, $days ) {
		$d = new DateTimeImmutable( $ymd, wp_timezone() );
		return $d->modify( ( $days >= 0 ? '+' : '' ) . (int) $days . ' days' )->format( 'Y-m-d' );
	}

	public static function days_between( $a, $b ) {
		$x = new DateTimeImmutable( $a, wp_timezone() );
		$y = new DateTimeImmutable( $b, wp_timezone() );
		return (int) $x->diff( $y )->format( '%a' ) + 1;
	}

	/** Totals for a local date range. */
	private static function totals( $from, $to ) {
		global $wpdb;
		$t   = flxb_table( 'donations' );
		$cur = flxb_default_currency();
		$r   = $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) AS total, COUNT(*) AS gifts, COUNT(DISTINCT NULLIF(donor_id,0)) AS donors FROM {$t} WHERE status IN ('completed','partially_refunded') AND currency = %s AND created_at BETWEEN %s AND %s", $cur, flxb_local_day_to_utc( $from ), flxb_local_day_to_utc( $to, true ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$d   = flxb_table( 'donors' );
		/* New donors: gave in this period (same filter as above) and made their first gift ever in it. */
		$new = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$d} WHERE first_donation_at BETWEEN %s AND %s AND id IN (SELECT donor_id FROM {$t} WHERE status IN ('completed','partially_refunded') AND currency = %s AND created_at BETWEEN %s AND %s)", flxb_local_day_to_utc( $from ), flxb_local_day_to_utc( $to, true ), $cur, flxb_local_day_to_utc( $from ), flxb_local_day_to_utc( $to, true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array(
			'total'      => round( (float) $r['total'], 2 ),
			'gifts'      => (int) $r['gifts'],
			'donors'     => (int) $r['donors'],
			'new_donors' => $new,
			'average'    => (int) $r['gifts'] ? round( (float) $r['total'] / (int) $r['gifts'], 2 ) : 0,
		);
	}

	public static function collect( $from, $to ) {
		global $wpdb;
		$t     = flxb_table( 'donations' );
		$cur   = flxb_default_currency();
		$days  = self::days_between( $from, $to );
		$pto   = self::shift( $from, -1 );
		$pfrom = self::shift( $pto, -( $days - 1 ) );
		$u0    = flxb_local_day_to_utc( $from );
		$u1    = flxb_local_day_to_utc( $to, true );
		$off   = (int) wp_timezone()->getOffset( new DateTime( 'now', new DateTimeZone( 'UTC' ) ) );
		$paid  = "status IN ('completed','partially_refunded') AND currency = %s AND created_at BETWEEN %s AND %s";

		/* Daily series (local days). */
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(DATE_ADD(created_at, INTERVAL %d SECOND)) AS day, type, SUM(amount) AS amount, COUNT(*) AS gifts FROM {$t} WHERE {$paid} GROUP BY day, type", $off, $cur, $u0, $u1 ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$index = array();
		foreach ( (array) $rows as $r ) {
			$index[ $r['day'] ][ $r['type'] ] = array( (float) $r['amount'], (int) $r['gifts'] );
		}
		$series = array();
		for ( $i = 0; $i < $days; $i++ ) {
			$day      = self::shift( $from, $i );
			$series[] = array(
				'day'       => $day,
				'label'     => flx_date( strtotime( $day . ' 12:00:00' ), 'short' ),
				'one_time'  => round( $index[ $day ]['one_time'][0] ?? 0, 2 ),
				'recurring' => round( $index[ $day ]['recurring'][0] ?? 0, 2 ),
				'gifts'     => ( $index[ $day ]['one_time'][1] ?? 0 ) + ( $index[ $day ]['recurring'][1] ?? 0 ),
			);
		}

		/* Split. */
		$split = array( 'one_time' => array( 0, 0 ), 'recurring' => array( 0, 0 ) );
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT type, SUM(amount) AS amount, COUNT(*) AS gifts FROM {$t} WHERE {$paid} GROUP BY type", $cur, $u0, $u1 ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL
			$split[ $r['type'] ] = array( round( (float) $r['amount'], 2 ), (int) $r['gifts'] );
		}

		/* By donation type. */
		$cmp       = flxb_table( 'types' );
		$types = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT x.type_id, c.title, SUM(x.amount) AS amount, COUNT(*) AS gifts FROM {$t} x LEFT JOIN {$cmp} c ON c.id = x.type_id WHERE x.status IN ('completed','partially_refunded') AND x.currency = %s AND x.created_at BETWEEN %s AND %s GROUP BY x.type_id, c.title ORDER BY amount DESC LIMIT 12", $cur, $u0, $u1 ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL
			$types[] = array(
				'id'     => (int) $r['type_id'],
				'label'  => (int) $r['type_id'] ? (string) $r['title'] : __( '\\nNo donation type\\n', 'flx-donate' ),
				'amount' => round( (float) $r['amount'], 2 ),
				'gifts'  => (int) $r['gifts'],
			);
		}

		/* By form. */
		$forms = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT form_id, SUM(amount) AS amount, COUNT(*) AS gifts FROM {$t} WHERE {$paid} GROUP BY form_id ORDER BY amount DESC LIMIT 12", $cur, $u0, $u1 ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL
			$forms[] = array(
				'id'     => (int) $r['form_id'],
				'label'  => (int) $r['form_id'] ? flxb_form_title( (int) $r['form_id'] ) : __( 'Manual', 'flx-donate' ),
				'amount' => round( (float) $r['amount'], 2 ),
				'gifts'  => (int) $r['gifts'],
			);
		}

		/* Top donors in the period. */
		$dt  = flxb_table( 'donors' );
		$top = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT x.donor_id, d.name, d.email, d.phone, SUM(x.amount) AS amount, COUNT(*) AS gifts FROM {$t} x INNER JOIN {$dt} d ON d.id = x.donor_id WHERE x.status IN ('completed','partially_refunded') AND x.currency = %s AND x.created_at BETWEEN %s AND %s GROUP BY x.donor_id, d.name, d.email, d.phone ORDER BY amount DESC LIMIT 10", $cur, $u0, $u1 ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL
			$top[] = array(
				'id'      => (int) $r['donor_id'],
				'name'    => '' !== (string) $r['name'] ? (string) $r['name'] : ( '' !== (string) $r['email'] ? (string) $r['email'] : ( '' !== (string) $r['phone'] ? flxb_format_phone( $r['phone'] ) : __( 'Anonymous', 'flx-donate' ) ) ),
				'contact' => '' !== (string) $r['email'] ? (string) $r['email'] : flxb_format_phone( $r['phone'] ),
				'amount'  => round( (float) $r['amount'], 2 ),
				'gifts'   => (int) $r['gifts'],
				'url'     => FLXB_Admin_Url::donor( (int) $r['donor_id'] ),
			);
		}

		$subs = FLXB_Subscriptions::totals();
		$all  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . flxb_table( 'donors' ) . ' WHERE donation_count > 0' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array(
			'range'     => array( 'from' => $from, 'to' => $to, 'days' => $days, 'prev_from' => $pfrom, 'prev_to' => $pto ),
			'currency'  => $cur,
			'now'       => self::totals( $from, $to ),
			'prev'      => self::totals( $pfrom, $pto ),
			'all_donors' => $all,
			'subs'      => $subs,
			'series'    => $series,
			'split'     => $split,
			'types'     => $types,
			'forms'     => $forms,
			'top'       => $top,
		);
	}
}

/** Admin URLs (kept outside is_admin() so front/REST code can build links too). */
final class FLXB_Admin_Url {
	public static function page( $page, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}
	public static function donor( $id ) {
		return self::page( 'flxb-donors', array( 'donor' => (int) $id ) );
	}
	public static function donation( $id ) {
		return self::page( 'flxb-donations', array( 'donation' => (int) $id ) );
	}
}
