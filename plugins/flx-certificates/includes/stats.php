<?php
/**
 * Dashboard istatistikleri: fidan, sertifika, kişi, mesaj.
 * Tarihler sitenin saat dilimine göre (Ayarlar › Genel) gruplanır; veritabanında UTC durur.
 */

defined( 'ABSPATH' ) || exit;

/** Sertifika verisinden fidan sayısı: fidan_sayisi, yoksa adında "fidan" geçen ilk sayısal alan. */
function flxs_cert_fidan( $data ) {
	$d = is_array( $data ) ? $data : json_decode( (string) $data, true );
	$v = isset( $d['values'] ) && is_array( $d['values'] ) ? $d['values'] : array();
	if ( isset( $v['fidan_sayisi'] ) && is_numeric( $v['fidan_sayisi'] ) ) {
		return max( 0, (int) $v['fidan_sayisi'] );
	}
	foreach ( $v as $k => $x ) {
		if ( false !== strpos( (string) $k, 'fidan' ) && is_numeric( $x ) ) {
			return max( 0, (int) $x );
		}
	}
	return 0;
}

/** Yerel Y-m-d aralığını UTC datetime aralığına çevirir: [başlangıç, bitiş) */
function flxs_stats_utc_range( $from, $to ) {
	$tz  = wp_timezone();
	$utc = new DateTimeZone( 'UTC' );
	$a   = new DateTime( $from . ' 00:00:00', $tz );
	$b   = new DateTime( $to . ' 00:00:00', $tz );
	$b->modify( '+1 day' );
	return array( $a->setTimezone( $utc )->format( 'Y-m-d H:i:s' ), $b->setTimezone( $utc )->format( 'Y-m-d H:i:s' ) );
}

/** Filtre WHERE parçası (c. takma adlı sertifika tablosu için) */
function flxs_stats_where( $args, $range ) {
	global $wpdb;
	$w = array( $wpdb->prepare( 'c.created_at >= %s AND c.created_at < %s', $range[0], $range[1] ) );
	if ( '' !== $args['ref'] ) {
		$w[] = $wpdb->prepare( 'c.ref_user_id = %d', (int) $args['ref'] );
	}
	if ( $args['tpl'] ) {
		$w[] = $wpdb->prepare( 'c.template_id = %d', (int) $args['tpl'] );
	}
	if ( isset( $args['event'] ) && '' !== $args['event'] ) {
		$w[] = $wpdb->prepare( 'c.event_id = %d', (int) $args['event'] );
	}
	if ( flxs_dashboard_own_only() ) {
		$w[] = $wpdb->prepare( 'c.created_by = %d', get_current_user_id() );
	}
	return implode( ' AND ', $w );
}

/** Önceki dönem karşılaştırması için yalnızca toplamlar */
function flxs_stats_totals( $args, $from, $to ) {
	global $wpdb;
	$t    = flxs_table( 'certificates' );
	$rows = $wpdb->get_results( "SELECT c.person_id, c.data FROM $t c WHERE c.status <> 'revoked' AND " . flxs_stats_where( $args, flxs_stats_utc_range( $from, $to ) ), ARRAY_A ); // phpcs:ignore
	$out  = array( 'fidan' => 0, 'certs' => count( $rows ), 'people' => 0 );
	$p    = array();
	foreach ( $rows as $r ) {
		$out['fidan'] += flxs_cert_fidan( $r['data'] );
		if ( (int) $r['person_id'] ) {
			$p[ (int) $r['person_id'] ] = 1;
		}
	}
	$out['people'] = count( $p );
	return $out;
}

/**
 * @param array $args from, to (Y-m-d, yerel), ref ('' | 0 | user id), tpl (0 | id)
 */
function flxs_stats( $args ) {
	global $wpdb;
	$t     = flxs_table( 'certificates' );
	$tz    = wp_timezone();
	$today = wp_date( 'Y-m-d' );
	$args  = wp_parse_args( $args, array( 'from' => '', 'to' => '', 'ref' => '', 'tpl' => 0, 'event' => '' ) );
	$valid = function ( $d ) { return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $d ); };

	if ( ! $valid( $args['to'] ) ) {
		$args['to'] = $today;
	}
	if ( 'all' === $args['from'] || ! $valid( $args['from'] ) ) {
		$first        = $wpdb->get_var( "SELECT MIN(c.created_at) FROM $t c WHERE " . flxs_stats_where( $args, array( '1970-01-01 00:00:00', '9999-12-31 00:00:00' ) ) ); // phpcs:ignore
		$args['from'] = $first ? get_date_from_gmt( $first, 'Y-m-d' ) : $today;
	}
	if ( $args['from'] > $args['to'] ) {
		list( $args['from'], $args['to'] ) = array( $args['to'], $args['from'] );
	}
	$d1   = new DateTime( $args['from'], $tz );
	$d2   = new DateTime( $args['to'], $tz );
	$days = (int) $d1->diff( $d2 )->days + 1;
	$unit = $days <= 92 ? 'day' : 'month';

	$range = flxs_stats_utc_range( $args['from'], $args['to'] );
	$where = flxs_stats_where( $args, $range );
	$rows  = $wpdb->get_results( "SELECT c.id, c.person_id, c.template_id, c.ref_user_id, c.event_id, c.created_by, c.status, c.views, c.data, c.created_at FROM $t c WHERE $where ORDER BY c.created_at", ARRAY_A ); // phpcs:ignore

	// Boş kovalarla seri iskeleti
	$series = array();
	$cur    = clone $d1;
	if ( 'month' === $unit ) {
		$cur->modify( 'first day of this month' );
	}
	while ( $cur <= $d2 ) {
		$k            = 'month' === $unit ? $cur->format( 'Y-m' ) : $cur->format( 'Y-m-d' );
		$series[ $k ] = array( 'key' => $k, 'fidan' => 0, 'certs' => 0 );
		$cur->modify( 'month' === $unit ? '+1 month' : '+1 day' );
	}

	$tot     = array( 'fidan' => 0, 'certs' => 0, 'people' => 0, 'new_people' => 0, 'views' => 0, 'revoked' => 0, 'max_day' => null );
	$weekday = array_fill( 1, 7, array( 'fidan' => 0, 'certs' => 0 ) );
	$by_ref  = array();
	$by_tpl  = array();
	$by_evt  = array();
	$people  = array();
	foreach ( $rows as $r ) {
		if ( 'revoked' === $r['status'] ) {
			$tot['revoked']++;
			continue;
		}
		$f     = flxs_cert_fidan( $r['data'] );
		$local = new DateTime( $r['created_at'], new DateTimeZone( 'UTC' ) );
		$local->setTimezone( $tz );
		$k = 'month' === $unit ? $local->format( 'Y-m' ) : $local->format( 'Y-m-d' );
		if ( isset( $series[ $k ] ) ) {
			$series[ $k ]['fidan'] += $f;
			$series[ $k ]['certs']++;
		}
		$wd = (int) $local->format( 'N' );
		$weekday[ $wd ]['fidan'] += $f;
		$weekday[ $wd ]['certs']++;
		$tot['fidan'] += $f;
		$tot['certs']++;
		$tot['views'] += (int) $r['views'];
		$ref = (int) $r['ref_user_id'];
		if ( ! isset( $by_ref[ $ref ] ) ) {
			$by_ref[ $ref ] = array( 'id' => $ref, 'fidan' => 0, 'certs' => 0 );
		}
		$by_ref[ $ref ]['fidan'] += $f;
		$by_ref[ $ref ]['certs']++;
		$tp = (int) $r['template_id'];
		if ( ! isset( $by_tpl[ $tp ] ) ) {
			$by_tpl[ $tp ] = array( 'id' => $tp, 'fidan' => 0, 'certs' => 0 );
		}
		$by_tpl[ $tp ]['fidan'] += $f;
		$by_tpl[ $tp ]['certs']++;
		$ev = (int) $r['event_id'];
		if ( ! isset( $by_evt[ $ev ] ) ) {
			$by_evt[ $ev ] = array( 'id' => $ev, 'fidan' => 0, 'certs' => 0 );
		}
		$by_evt[ $ev ]['fidan'] += $f;
		$by_evt[ $ev ]['certs']++;
		$pid = (int) $r['person_id'];
		if ( $pid ) {
			if ( ! isset( $people[ $pid ] ) ) {
				$people[ $pid ] = array( 'id' => $pid, 'fidan' => 0, 'certs' => 0 );
			}
			$people[ $pid ]['fidan'] += $f;
			$people[ $pid ]['certs']++;
		}
	}
	$tot['people'] = count( $people );

	// İlk sertifikası bu dönemde olan kişiler = yeni kişi
	if ( $people ) {
		$ids   = implode( ',', array_map( 'intval', array_keys( $people ) ) );
		foreach ( (array) $wpdb->get_results( "SELECT person_id, MIN(created_at) AS first_at FROM $t WHERE person_id IN ($ids) GROUP BY person_id", ARRAY_A ) as $fr ) { // phpcs:ignore
			if ( $fr['first_at'] >= $range[0] ) {
				$tot['new_people']++;
			}
		}
	}

	// Etiketler
	foreach ( $by_ref as &$x ) {
		$x['name'] = $x['id'] ? flxs_ref_label( $x['id'] ) : 'Referanssız';
	}
	unset( $x );
	$tpl_names = array();
	foreach ( (array) $wpdb->get_results( 'SELECT id, name FROM ' . flxs_table( 'templates' ), ARRAY_A ) as $tr ) { // phpcs:ignore
		$tpl_names[ (int) $tr['id'] ] = $tr['name'];
	}
	foreach ( $by_tpl as &$x ) {
		$x['name'] = isset( $tpl_names[ $x['id'] ] ) ? $tpl_names[ $x['id'] ] : 'Silinmiş şablon';
	}
	unset( $x );
	foreach ( $by_evt as &$x ) {
		$x['name'] = $x['id'] ? ( flxs_event_label( $x['id'] ) ? flxs_event_label( $x['id'] ) : 'Silinmiş etkinlik' ) : 'Etkinliksiz';
	}
	unset( $x );
	$sort = function ( $a, $b ) { return $b['fidan'] - $a['fidan'] ? $b['fidan'] - $a['fidan'] : $b['certs'] - $a['certs']; };
	usort( $by_ref, $sort );
	usort( $by_tpl, $sort );
	usort( $by_evt, $sort );
	usort( $people, $sort );
	$top = array_slice( $people, 0, 10 );
	if ( $top ) {
		$ids   = implode( ',', array_map( function ( $p ) { return (int) $p['id']; }, $top ) );
		$names = array();
		foreach ( (array) $wpdb->get_results( 'SELECT id, name, phone FROM ' . flxs_table( 'people' ) . " WHERE id IN ($ids)", ARRAY_A ) as $pr ) { // phpcs:ignore
			$names[ (int) $pr['id'] ] = $pr;
		}
		foreach ( $top as &$p ) {
			$p['name']  = isset( $names[ $p['id'] ] ) ? $names[ $p['id'] ]['name'] : '#' . $p['id'];
			$p['phone'] = isset( $names[ $p['id'] ] ) ? flxs_mask_phone( $names[ $p['id'] ]['phone'] ) : '';
			$p['url']   = admin_url( 'admin.php?page=flxs-kisiler&person=' . $p['id'] );
		}
		unset( $p );
	}

	$best = null;
	foreach ( $series as $s ) {
		if ( $s['fidan'] && ( ! $best || $s['fidan'] > $best['fidan'] ) ) {
			$best = $s;
		}
	}
	$tot['max_day'] = $best;

	// Mesajlar: kanal × durum
	$m    = flxs_table( 'messages' );
	$msgw = str_replace( 'c.created_at', 'm.created_at', flxs_stats_where( array( 'ref' => '', 'tpl' => 0 ), $range ) );
	$msgw = str_replace( 'c.created_by', 'm.created_by', $msgw );
	if ( '' !== $args['ref'] ) {
		$msgw .= $wpdb->prepare( ' AND c.ref_user_id = %d', (int) $args['ref'] );
	}
	if ( $args['tpl'] ) {
		$msgw .= $wpdb->prepare( ' AND c.template_id = %d', (int) $args['tpl'] );
	}
	if ( '' !== $args['event'] ) {
		$msgw .= $wpdb->prepare( ' AND c.event_id = %d', (int) $args['event'] );
	}
	$msgs = $wpdb->get_results( "SELECT m.channel, m.status, COUNT(*) n FROM $m m LEFT JOIN $t c ON c.id = m.certificate_id WHERE $msgw GROUP BY m.channel, m.status", ARRAY_A ); // phpcs:ignore
	$mess = array();
	foreach ( flxs_channels() as $ch => $label ) {
		$mess[ $ch ] = array( 'channel' => $ch, 'label' => $label, 'sent' => 0, 'failed' => 0, 'waiting' => 0, 'cancelled' => 0 );
	}
	foreach ( (array) $msgs as $r ) {
		if ( ! isset( $mess[ $r['channel'] ] ) ) {
			continue;
		}
		$st = in_array( $r['status'], array( 'queued', 'processing' ), true ) ? 'waiting' : $r['status'];
		if ( isset( $mess[ $r['channel'] ][ $st ] ) ) {
			$mess[ $r['channel'] ][ $st ] += (int) $r['n'];
		}
	}

	// Önceki eşit uzunlukta dönem
	$p2   = ( clone $d1 )->modify( '-1 day' );
	$p1   = ( clone $d1 )->modify( '-' . $days . ' days' );
	$prev = flxs_stats_totals( $args, $p1->format( 'Y-m-d' ), $p2->format( 'Y-m-d' ) );

	// Bugüne kadar (filtreler dahil, tarih hariç)
	$allw = flxs_stats_where( $args, array( '1970-01-01 00:00:00', '9999-12-31 00:00:00' ) );
	$all  = 0;
	foreach ( (array) $wpdb->get_col( "SELECT c.data FROM $t c WHERE c.status <> 'revoked' AND $allw" ) as $dj ) { // phpcs:ignore
		$all += flxs_cert_fidan( $dj );
	}

	return array(
		'from'      => $args['from'],
		'to'        => $args['to'],
		'days'      => $days,
		'unit'      => $unit,
		'today'     => $today,
		'totals'    => $tot,
		'prev'      => $prev,
		'prev_from' => $p1->format( 'Y-m-d' ),
		'prev_to'   => $p2->format( 'Y-m-d' ),
		'all_fidan' => $all,
		'series'    => array_values( $series ),
		'weekday'   => array_values( $weekday ),
		'by_ref'    => $by_ref,
		'by_tpl'    => $by_tpl,
		'by_event'  => $by_evt,
		'event'     => '' !== $args['event'] && (int) $args['event'] ? flxs_events_for_js_one( (int) $args['event'] ) : null,
		'top'       => $top,
		'messages'  => array_values( $mess ),
	);
}

function flxs_events_for_js_one( $id ) {
	$e = flxs_get_event( $id );
	return $e ? array( 'id' => (int) $e['id'], 'name' => $e['name'], 'date' => (string) $e['event_date'], 'status' => $e['status'], 'target' => (int) $e['target'] ) : null;
}
