<?php
/** Metrik toplama (günlük, sınırlı süre) ve pano verisi. */

defined( 'ABSPATH' ) || exit;

/**
 * Hesap takipçi sayıları (günde bir) ve son 30 günün gönderi metrikleri (12 saatte bir).
 * Dönüş: güncellenen kayıt sayısı.
 */
function flxsm_collect_metrics( $budget = 30, $only_account = 0 ) {
	global $wpdb;
	$start = microtime( true );
	$n     = 0;
	$day   = wp_date( 'Y-m-d' );
	foreach ( flxsm_accounts( true ) as $a ) {
		if ( $only_account && (int) $a['id'] !== (int) $only_account ) {
			continue;
		}
		if ( microtime( true ) - $start > $budget ) {
			return $n;
		}
		$has = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxsm_table( 'metrics' ) . ' WHERE account_id = %d AND day = %s', $a['id'], $day ) ); // phpcs:ignore WordPress.DB
		if ( $has && ! $only_account ) {
			continue;
		}
		$prov = flxsm_provider( $a['provider'] );
		$acc  = flxsm_account( $a['id'] );
		if ( ! $prov || ! $acc ) {
			continue;
		}
		$acc = $prov->ensure( $acc );
		if ( is_wp_error( $acc ) ) {
			continue;
		}
		$m = $prov->account_metrics( $acc );
		if ( is_wp_error( $m ) ) {
			continue;
		}
		if ( ! empty( $m['avatar'] ) && $m['avatar'] !== $a['avatar'] ) {
			flxsm_update_account( $a['id'], array( 'avatar' => esc_url_raw( $m['avatar'] ) ) );
		}
		unset( $m['avatar'] );
		$wpdb->replace( // phpcs:ignore WordPress.DB
			flxsm_table( 'metrics' ),
			array(
				'account_id' => (int) $a['id'],
				'day'        => $day,
				'followers'  => isset( $m['followers'] ) ? (int) $m['followers'] : -1,
				'data'       => flxsm_json( $m ),
			)
		);
		++$n;
	}
	$tt   = flxsm_table( 'targets' );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tt} WHERE status = 'published' AND remote_id <> '' AND published_at > %d AND metrics_at < %d" . ( $only_account ? ' AND account_id = %d' : ' AND %d = %d' ) . ' ORDER BY metrics_at ASC LIMIT 60', time() - 30 * DAY_IN_SECONDS, time() - 12 * HOUR_IN_SECONDS, $only_account ? $only_account : 1, $only_account ? $only_account : 1 ), ARRAY_A ); // phpcs:ignore WordPress.DB
	$accs = array();
	foreach ( (array) $rows as $t ) {
		if ( microtime( true ) - $start > $budget ) {
			break;
		}
		$aid = (int) $t['account_id'];
		if ( ! isset( $accs[ $aid ] ) ) {
			$acc          = flxsm_account( $aid );
			$prov         = $acc ? flxsm_provider( $acc['provider'] ) : null;
			$acc          = $prov ? $prov->ensure( $acc ) : null;
			$accs[ $aid ] = $prov && $acc && ! is_wp_error( $acc ) ? array( $acc, $prov ) : null;
		}
		if ( ! $accs[ $aid ] ) {
			continue;
		}
		list( $acc, $prov ) = $accs[ $aid ];
		$m                  = $prov->post_metrics( $acc, $t['remote_id'], $t );
		$patch              = array( 'metrics_at' => time() );
		if ( ! is_wp_error( $m ) ) {
			$patch['metrics'] = $m;
			++$n;
		}
		flxsm_update_target( $t['id'], $patch );
	}
	update_option( 'flxsm_last_metrics', time(), false );
	return $n;
}

/** Etkileşim toplamı (beğeni + yorum + paylaşım + kaydetme). */
function flxsm_engagement( array $m ) {
	return (int) ( $m['likes'] ?? 0 ) + (int) ( $m['comments'] ?? 0 ) + (int) ( $m['shares'] ?? 0 ) + (int) ( $m['saves'] ?? $m['saved'] ?? 0 );
}

/** Pano: $days gün (7/30/90). */
function flxsm_dashboard( $days = 30 ) {
	global $wpdb;
	$days  = in_array( (int) $days, array( 7, 30, 90 ), true ) ? (int) $days : 30;
	$from  = time() - $days * DAY_IN_SECONDS;
	$pt    = flxsm_table( 'posts' );
	$tt    = flxsm_table( 'targets' );
	$at    = flxsm_table( 'accounts' );
	$accs  = array();
	foreach ( flxsm_accounts() as $a ) {
		$accs[ $a['id'] ] = $a;
	}

	$targets = $wpdb->get_results( $wpdb->prepare( "SELECT t.id, t.post_id, t.account_id, t.status, t.published_at, t.metrics, t.remote_url, t.updated, a.provider FROM {$tt} t LEFT JOIN {$at} a ON a.id = t.account_id WHERE (t.published_at >= %d) OR (t.status = 'failed' AND t.updated >= %d)", $from, $from ), ARRAY_A ); // phpcs:ignore WordPress.DB
	$by_day  = array();
	for ( $i = $days - 1; $i >= 0; $i-- ) {
		$by_day[ wp_date( 'Y-m-d', time() - $i * DAY_IN_SECONDS ) ] = array();
	}
	$by_net  = array();
	$pub     = 0;
	$failed  = 0;
	$eng     = 0;
	$views   = 0;
	$top     = array();
	foreach ( (array) $targets as $t ) {
		$net = (string) $t['provider'];
		if ( ! isset( $by_net[ $net ] ) ) {
			$by_net[ $net ] = array(
				'provider'   => $net,
				'published'  => 0,
				'failed'     => 0,
				'engagement' => 0,
				'views'      => 0,
			);
		}
		if ( 'failed' === $t['status'] ) {
			++$failed;
			++$by_net[ $net ]['failed'];
			continue;
		}
		if ( ! in_array( $t['status'], array( 'published', 'deleted' ), true ) ) {
			continue;
		}
		++$pub;
		++$by_net[ $net ]['published'];
		$d = wp_date( 'Y-m-d', (int) $t['published_at'] );
		if ( isset( $by_day[ $d ] ) ) {
			$by_day[ $d ][ $net ] = ( $by_day[ $d ][ $net ] ?? 0 ) + 1;
		}
		$m  = flxsm_dec( $t['metrics'] );
		$e  = flxsm_engagement( $m );
		$v  = (int) ( $m['views'] ?? $m['impressions'] ?? $m['reach'] ?? 0 );
		$eng                          += $e;
		$views                        += $v;
		$by_net[ $net ]['engagement'] += $e;
		$by_net[ $net ]['views']      += $v;
		if ( $m ) {
			$top[] = array(
				'target_id'  => (int) $t['id'],
				'post_id'    => (int) $t['post_id'],
				'provider'   => $net,
				'account'    => $accs[ (int) $t['account_id'] ]['name'] ?? '',
				'engagement' => $e,
				'views'      => $v,
				'metrics'    => $m,
				'url'        => $t['remote_url'],
				'at'         => (int) $t['published_at'],
			);
		}
	}
	usort( $top, fn( $a, $b ) => ( $b['engagement'] * 10 + $b['views'] ) <=> ( $a['engagement'] * 10 + $a['views'] ) );
	$top = array_slice( $top, 0, 8 );
	foreach ( $top as &$row ) {
		$p            = flxsm_post_row( $row['post_id'] );
		$row['title'] = $p ? ( '' !== $p['title'] ? $p['title'] : mb_substr( (string) $p['content'], 0, 90 ) ) : '';
	}
	unset( $row );

	/* Takipçi: hesap başına seri. */
	$mt        = flxsm_table( 'metrics' );
	$fdays     = $wpdb->get_results( $wpdb->prepare( "SELECT account_id, day, followers FROM {$mt} WHERE day >= %s AND followers >= 0 ORDER BY day ASC", wp_date( 'Y-m-d', $from ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
	$followers = array();
	foreach ( (array) $fdays as $r ) {
		$aid = (int) $r['account_id'];
		if ( ! isset( $accs[ $aid ] ) ) {
			continue;
		}
		if ( ! isset( $followers[ $aid ] ) ) {
			$followers[ $aid ] = array(
				'account_id' => $aid,
				'name'       => $accs[ $aid ]['name'],
				'provider'   => $accs[ $aid ]['provider'],
				'avatar'     => $accs[ $aid ]['avatar'],
				'series'     => array(),
			);
		}
		$followers[ $aid ]['series'][] = array( $r['day'], (int) $r['followers'] );
	}
	$total_f = 0;
	$delta_f = 0;
	foreach ( $followers as &$f ) {
		$last       = end( $f['series'] );
		$first      = reset( $f['series'] );
		$f['now']   = (int) $last[1];
		$f['delta'] = (int) $last[1] - (int) $first[1];
		$total_f   += $f['now'];
		$delta_f   += $f['delta'];
	}
	unset( $f );

	$now      = time();
	$upcoming = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$pt} WHERE status IN ('scheduled','publishing') AND scheduled_at >= %d ORDER BY scheduled_at ASC LIMIT 8", $now - 3600 ), ARRAY_A ); // phpcs:ignore WordPress.DB
	$pending  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$pt} WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB
	$drafts   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$pt} WHERE status = 'draft'" ); // phpcs:ignore WordPress.DB
	$sched    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$pt} WHERE status IN ('scheduled','publishing')" ); // phpcs:ignore WordPress.DB
	$errs     = $wpdb->get_results( $wpdb->prepare( "SELECT t.id, t.post_id, t.account_id, t.error, t.updated FROM {$tt} t WHERE t.status = 'failed' AND t.updated >= %d ORDER BY t.updated DESC LIMIT 6", $from ), ARRAY_A ); // phpcs:ignore WordPress.DB

	return array(
		'days'      => $days,
		'totals'    => array(
			'published'  => $pub,
			'failed'     => $failed,
			'scheduled'  => $sched,
			'pending'    => $pending,
			'drafts'     => $drafts,
			'engagement' => $eng,
			'views'      => $views,
			'followers'  => $total_f,
			'followers_delta' => $delta_f,
			'success'    => $pub + $failed ? round( 100 * $pub / ( $pub + $failed ) ) : null,
		),
		'by_day'    => array_map( null, array_keys( $by_day ), array_values( $by_day ) ),
		'by_net'    => array_values( $by_net ),
		'followers' => array_values( $followers ),
		'top'       => $top,
		'upcoming'  => array_map( fn( $p ) => flxsm_post_public( $p, false ), (array) $upcoming ),
		'errors'    => array_map(
			fn( $e ) => array(
				'target_id' => (int) $e['id'],
				'post_id'   => (int) $e['post_id'],
				'account'   => $accs[ (int) $e['account_id'] ]['name'] ?? '',
				'provider'  => $accs[ (int) $e['account_id'] ]['provider'] ?? '',
				'error'     => $e['error'],
				'at'        => (int) $e['updated'],
			),
			(array) $errs
		),
		'last_metrics' => (int) get_option( 'flxsm_last_metrics', 0 ),
	);
}
