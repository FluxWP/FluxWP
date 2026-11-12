<?php
/**
 * Flux Donate 2.27.0: read-only donation summary for Flux AI & MCP (`flx_mcp_tools`).
 *
 * One tool, `flxb_donation_summary`: paid gifts and totals for a day or a range, split by donation type
 * (sapling, zakat …), one-time / monthly, pending gifts (receipt awaiting approval / awaiting payment),
 * cancellations and refunds, and, with compare, the same numbers for the previous period of the same
 * length. Counts and sums only: no names, phones, e-mails, receipts or other personal data.
 * Without Flux AI the filter never runs and nothing changes.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'flx_mcp_tools', 'flxb_mcp_tools' );
add_filter( 'flx_mcp_instructions', 'flxb_mcp_instruction_lines' );

/**
 * @param mixed $tools
 * @return array
 */
function flxb_mcp_tools( $tools ) {
	$tools = is_array( $tools ) ? $tools : array();

	$tools['flxb_donation_summary'] = array(
		'title'       => __( 'Donation summary', 'flx-donate' ),
		'description' => 'Flux Donate: donation numbers for a day or a date range (site-local dates). Returns paid gifts (count, amount, units such as saplings, donors, new donors), a split by donation type (sapling, zakat, kurban …) and one-time / monthly, pending gifts (receipts awaiting approval, awaiting payment) both created in the range and open right now, cancelled / failed / refunded gifts, and active monthly donations. With compare=true it adds the previous period of the same length and the change. Amounts are in the default currency; other currencies are listed separately and never mixed. No personal data. Use it for briefings and "how did donations go yesterday / this week".',
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'period'  => array(
					'type'        => 'string',
					'enum'        => array( 'today', 'yesterday', 'last_7_days', 'last_30_days', 'this_month', 'last_month' ),
					'description' => 'Shortcut for the range. Ignored when date or from/to is given. Default: today.',
				),
				'date'    => array(
					'type'        => 'string',
					'description' => 'One day, YYYY-MM-DD (site-local).',
				),
				'from'    => array(
					'type'        => 'string',
					'description' => 'Range start, YYYY-MM-DD (site-local, inclusive).',
				),
				'to'      => array(
					'type'        => 'string',
					'description' => 'Range end, YYYY-MM-DD (site-local, inclusive). Default: from.',
				),
				'compare' => array(
					'type'        => 'boolean',
					'description' => 'Also return the previous period of the same length (e.g. the day before) and the change.',
				),
			),
		),
		'readonly'    => true,
		'cap'         => (string) apply_filters( 'flxb_manage_capability', 'manage_options' ),
		'source'      => 'flx-donate',
		'run'         => function ( $args ) {
			if ( ! flxb_can_manage() ) {
				throw new Exception( 'forbidden' );
			}
			return flxb_donation_summary( is_array( $args ) ? $args : array() );
		},
	);

	return $tools;
}

/**
 * @param mixed $lines
 * @return array
 */
function flxb_mcp_instruction_lines( $lines ) {
	$lines   = is_array( $lines ) ? $lines : array();
	$lines[] = 'Flux Donate: for donation numbers (paid, by type, pending receipts, cancellations, day-over-day change) call flxb_donation_summary; dates are site-local (' . wp_timezone_string() . '), today is ' . wp_date( 'Y-m-d' ) . '.';
	return $lines;
}

/**
 * Resolve the tool input to a site-local [from, to].
 *
 * @param array $a period | date | from, to
 * @return array { 0: from Y-m-d, 1: to Y-m-d }
 * @throws Exception On a malformed date.
 */
function flxb_summary_range( array $a ) {
	$re    = '/^\d{4}-\d{2}-\d{2}$/';
	$valid = function ( $v ) use ( $re ) {
		$v = trim( (string) $v );
		if ( '' === $v ) {
			return '';
		}
		$d = preg_match( $re, $v ) ? DateTimeImmutable::createFromFormat( '!Y-m-d', $v, wp_timezone() ) : false;
		if ( ! $d || $d->format( 'Y-m-d' ) !== $v ) {
			throw new Exception( 'Invalid date "' . $v . '", use YYYY-MM-DD.' );
		}
		return $v;
	};
	$date = $valid( $a['date'] ?? '' );
	if ( '' !== $date ) {
		return array( $date, $date );
	}
	$from = $valid( $a['from'] ?? '' );
	$to   = $valid( $a['to'] ?? '' );
	if ( '' !== $from || '' !== $to ) {
		$from = '' !== $from ? $from : $to;
		$to   = '' !== $to ? $to : $from;
		if ( $from > $to ) {
			list( $from, $to ) = array( $to, $from );
		}
		if ( FLXB_Stats::days_between( $from, $to ) > FLXB_Stats::MAX_DAYS ) {
			$from = FLXB_Stats::shift( $to, -( FLXB_Stats::MAX_DAYS - 1 ) );
		}
		return array( $from, $to );
	}
	$today = wp_date( 'Y-m-d' );
	$first = substr( $today, 0, 8 ) . '01';
	switch ( (string) ( $a['period'] ?? '' ) ) {
		case 'yesterday':
			$y = FLXB_Stats::shift( $today, -1 );
			return array( $y, $y );
		case 'last_7_days':
			return array( FLXB_Stats::shift( $today, -6 ), $today );
		case 'last_30_days':
			return array( FLXB_Stats::shift( $today, -29 ), $today );
		case 'this_month':
			return array( $first, $today );
		case 'last_month':
			$end = FLXB_Stats::shift( $first, -1 );
			return array( substr( $end, 0, 8 ) . '01', $end );
	}
	return array( $today, $today );
}

/**
 * Donation summary for a site-local range. Public: other plugins (Flux AI briefings, Flux App) may call it.
 *
 * @param array $a period | date | from, to; compare (bool)
 * @return array
 * @throws Exception On a malformed date.
 */
function flxb_donation_summary( array $a = array() ) {
	list( $from, $to ) = flxb_summary_range( $a );
	$days              = FLXB_Stats::days_between( $from, $to );

	$out = array(
		'range'    => array(
			'from'     => $from,
			'to'       => $to,
			'days'     => $days,
			'timezone' => wp_timezone_string(),
		),
		'currency' => flxb_default_currency(),
	);
	$out += flxb_summary_period( $from, $to );

	$out['open_now']      = flxb_summary_pending( '', '' );
	$out['subscriptions'] = FLXB_Subscriptions::totals();

	if ( ! empty( $a['compare'] ) && 'false' !== $a['compare'] ) {
		$pto   = FLXB_Stats::shift( $from, -1 );
		$pfrom = FLXB_Stats::shift( $pto, -( $days - 1 ) );
		$prev  = flxb_summary_period( $pfrom, $pto );

		$out['previous'] = array( 'range' => array( 'from' => $pfrom, 'to' => $pto, 'days' => $days ) ) + $prev;
		$out['change']   = array(
			'paid_amount'     => flxb_summary_delta( $out['paid']['amount'], $prev['paid']['amount'] ),
			'paid_gifts'      => flxb_summary_delta( $out['paid']['gifts'], $prev['paid']['gifts'] ),
			'paid_units'      => flxb_summary_delta( $out['paid']['units'], $prev['paid']['units'] ),
			'pending_gifts'   => flxb_summary_delta( $out['pending']['gifts'], $prev['pending']['gifts'] ),
			'cancelled_gifts' => flxb_summary_delta( $out['cancelled']['gifts'], $prev['cancelled']['gifts'] ),
		);
	}

	$out['links'] = array(
		'donations' => FLXB_Admin_Url::page( 'flxb-donations', array( 'from' => $from, 'to' => $to ) ),
		'receipts'  => FLXB_Admin_Url::page( 'flxb-donations', array( 'status' => 'receipt' ) ),
	);
	$out['note'] = 'A gift is one payment: the lines of one WooCommerce order count once. Dates use the time the gift was recorded. Trashed gifts are left out.';

	return $out;
}

/** Change between two numbers: { diff, pct } (pct null when the previous value is 0). */
function flxb_summary_delta( $now, $prev ) {
	$now  = (float) $now;
	$prev = (float) $prev;
	return array(
		'diff' => round( $now - $prev, 2 ),
		'pct'  => $prev > 0 ? round( ( $now - $prev ) / $prev * 100, 1 ) : null,
	);
}

/**
 * Paid, by type, pending and cancelled numbers for one range.
 *
 * @return array { paid, other_currencies, by_type, by_kind, pending, cancelled }
 */
function flxb_summary_period( $from, $to ) {
	global $wpdb;
	$t   = flxb_table( 'donations' );
	$typ = flxb_table( 'types' );
	$dn  = flxb_table( 'donors' );
	$cur = flxb_default_currency();
	$u0  = flxb_local_day_to_utc( $from );
	$u1  = flxb_local_day_to_utc( $to, true );
	$g   = FLXB_Donations::GROUP_KEY;
	$in  = "x.created_at BETWEEN %s AND %s AND x.status <> 'trash'";
	$pd  = "x.status IN ('completed','partially_refunded')";

	/* Paid, default currency. */
	$r = (array) $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(DISTINCT {$g}) AS gifts, COUNT(*) AS lines_n, COALESCE(SUM(x.amount),0) AS amount, COALESCE(SUM(x.quantity),0) AS units, COUNT(DISTINCT NULLIF(x.donor_id,0)) AS donors FROM {$t} x WHERE {$in} AND {$pd} AND x.currency = %s", $u0, $u1, $cur ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	$new = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$dn} WHERE first_donation_at BETWEEN %s AND %s AND id IN (SELECT x.donor_id FROM {$t} x WHERE {$in} AND {$pd})", $u0, $u1, $u0, $u1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	$gifts = (int) ( $r['gifts'] ?? 0 );
	$amt   = round( (float) ( $r['amount'] ?? 0 ), 2 );
	$paid  = array(
		'gifts'      => $gifts,
		'lines'      => (int) ( $r['lines_n'] ?? 0 ),
		'amount'     => $amt,
		'units'      => (int) ( $r['units'] ?? 0 ),
		'average'    => $gifts ? round( $amt / $gifts, 2 ) : 0,
		'donors'     => (int) ( $r['donors'] ?? 0 ),
		'new_donors' => $new,
		'one_time'   => array( 'gifts' => 0, 'amount' => 0 ),
		'monthly'    => array( 'gifts' => 0, 'amount' => 0 ),
	);
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT x.type, COUNT(DISTINCT {$g}) AS gifts, COALESCE(SUM(x.amount),0) AS amount FROM {$t} x WHERE {$in} AND {$pd} AND x.currency = %s GROUP BY x.type", $u0, $u1, $cur ), ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL
		$k = 'recurring' === $row['type'] ? 'monthly' : 'one_time';
		$paid[ $k ]['gifts']  += (int) $row['gifts'];
		$paid[ $k ]['amount'] += round( (float) $row['amount'], 2 );
	}

	/* Paid in other currencies, never added to the totals above. */
	$other = array();
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT x.currency, COUNT(DISTINCT {$g}) AS gifts, COALESCE(SUM(x.amount),0) AS amount, COALESCE(SUM(x.quantity),0) AS units FROM {$t} x WHERE {$in} AND {$pd} AND x.currency <> %s GROUP BY x.currency ORDER BY amount DESC", $u0, $u1, $cur ), ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL
		$other[] = array(
			'currency' => (string) $row['currency'],
			'gifts'    => (int) $row['gifts'],
			'amount'   => round( (float) $row['amount'], 2 ),
			'units'    => (int) $row['units'],
		);
	}

	/* By donation type (all currencies, one row per type and currency). */
	$by_type = array();
	$by_kind = array();
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT x.type_id, c.title, c.slug, c.category, x.currency, COUNT(DISTINCT {$g}) AS gifts, COALESCE(SUM(x.amount),0) AS amount, COALESCE(SUM(x.quantity),0) AS units FROM {$t} x LEFT JOIN {$typ} c ON c.id = x.type_id WHERE {$in} AND {$pd} GROUP BY x.type_id, c.title, c.slug, c.category, x.currency ORDER BY amount DESC LIMIT 60", $u0, $u1 ), ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL
		$id   = (int) $row['type_id'];
		$kind = $id ? flxb_summary_kind( (string) $row['slug'], (string) $row['category'], (string) $row['title'] ) : 'other';
		$item = array(
			'type_id'  => $id,
			'title'    => $id && '' !== (string) $row['title'] ? (string) $row['title'] : __( 'No donation type', 'flx-donate' ),
			'kind'     => $kind,
			'currency' => (string) $row['currency'],
			'gifts'    => (int) $row['gifts'],
			'units'    => (int) $row['units'],
			'amount'   => round( (float) $row['amount'], 2 ),
		);
		$by_type[] = $item;
		if ( $cur === $item['currency'] ) {
			if ( ! isset( $by_kind[ $kind ] ) ) {
				$by_kind[ $kind ] = array( 'gifts' => 0, 'units' => 0, 'amount' => 0 );
			}
			$by_kind[ $kind ]['gifts']  += $item['gifts'];
			$by_kind[ $kind ]['units']  += $item['units'];
			$by_kind[ $kind ]['amount'] += $item['amount'];
		}
	}

	/* Cancelled, failed, refunded. */
	$cancel = array(
		'gifts'    => 0,
		'canceled' => array( 'gifts' => 0, 'amount' => 0 ),
		'failed'   => array( 'gifts' => 0, 'amount' => 0 ),
		'refunded' => array( 'gifts' => 0, 'amount' => 0 ),
	);
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT x.status, COUNT(DISTINCT {$g}) AS gifts, COALESCE(SUM(x.amount),0) AS amount FROM {$t} x WHERE {$in} AND x.status IN ('canceled','failed','refunded') AND x.currency = %s GROUP BY x.status", $u0, $u1, $cur ), ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL
		$cancel[ $row['status'] ] = array(
			'gifts'  => (int) $row['gifts'],
			'amount' => round( (float) $row['amount'], 2 ),
		);
		$cancel['gifts']         += (int) $row['gifts'];
	}

	return array(
		'paid'             => $paid,
		'other_currencies' => $other,
		'by_type'          => $by_type,
		'by_kind'          => $by_kind,
		'pending'          => flxb_summary_pending( $u0, $u1 ),
		'cancelled'        => $cancel,
	);
}

/**
 * Pending gifts, split by what they wait for. With UTC bounds: created in that range; without: all open now.
 *
 * @return array { gifts, amount, receipt_awaiting_approval {gifts, amount}, awaiting_payment {gifts, amount}, oldest }
 */
function flxb_summary_pending( $u0, $u1 ) {
	global $wpdb;
	if ( ! class_exists( 'FLXB_Approval' ) ) {
		require_once FLXB_DIR . 'includes/admin/class-flxb-approval.php';
	}
	$t   = flxb_table( 'donations' );
	$cur = flxb_default_currency();
	$g   = FLXB_Donations::GROUP_KEY;
	$sql = "SELECT {$g} AS gk, MIN(x.id) AS id, MAX(x.wc_order_id) AS wc_order_id, SUM(CASE WHEN x.currency = %s THEN x.amount ELSE 0 END) AS amount, MIN(x.created_at) AS created_at FROM {$t} x WHERE x.status = 'pending'";
	$prm = array( $cur );
	if ( '' !== $u0 ) {
		$sql  .= ' AND x.created_at BETWEEN %s AND %s';
		$prm[] = $u0;
		$prm[] = $u1;
	}
	$sql  .= ' GROUP BY gk ORDER BY created_at ASC LIMIT 1000';
	$rows  = (array) $wpdb->get_results( $wpdb->prepare( $sql, $prm ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	$keys  = FLXB_Approval::receipt_keys();
	$out   = array(
		'gifts'                     => 0,
		'amount'                    => 0,
		'receipt_awaiting_approval' => array( 'gifts' => 0, 'amount' => 0 ),
		'awaiting_payment'          => array( 'gifts' => 0, 'amount' => 0 ),
		'oldest'                    => null,
	);
	foreach ( $rows as $r ) {
		$k    = (int) $r['wc_order_id'] ? 'o' . (int) $r['wc_order_id'] : 'd' . (int) $r['id'];
		$side = isset( $keys[ $k ] ) ? 'receipt_awaiting_approval' : 'awaiting_payment';
		$amt  = round( (float) $r['amount'], 2 );
		++$out['gifts'];
		$out['amount']          += $amt;
		++$out[ $side ]['gifts'];
		$out[ $side ]['amount'] += $amt;
		if ( null === $out['oldest'] ) {
			$out['oldest'] = wp_date( 'Y-m-d H:i', strtotime( $r['created_at'] . ' UTC' ) );
		}
	}
	$out['amount'] = round( $out['amount'], 2 );
	return $out;
}

/**
 * Broad kind of a donation type, so briefings can say "saplings" or "zakat" on any site.
 * Filter `flxb_summary_kind` ( $kind, $slug, $category, $title ).
 *
 * @return string sapling | zakat | kurban | sadaka | fitre | other
 */
function flxb_summary_kind( $slug, $category, $title ) {
	$hay  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $slug . ' ' . $category . ' ' . $title ) : strtolower( $slug . ' ' . $category . ' ' . $title );
	$map  = array(
		'sapling' => array( 'fidan', 'sapling', 'tree', 'agac', 'ağaç', 'orman' ),
		'zakat'   => array( 'zekat', 'zakat' ),
		'kurban'  => array( 'kurban', 'qurban', 'udhiyah', 'akika', 'adak' ),
		'fitre'   => array( 'fitre', 'fitr' ),
		'sadaka'  => array( 'sadaka', 'sadaqah', 'sadaqa' ),
	);
	$kind = 'other';
	foreach ( $map as $k => $words ) {
		foreach ( $words as $w ) {
			if ( false !== strpos( $hay, $w ) ) {
				$kind = $k;
				break 2;
			}
		}
	}
	return (string) apply_filters( 'flxb_summary_kind', $kind, $slug, $category, $title );
}
