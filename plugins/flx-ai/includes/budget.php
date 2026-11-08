<?php
/**
 * Harcama sınırı: günlük, haftalık (Pazartesi başlar), aylık; site saat dilimine göre yenilenir.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** Dönem başlangıçları (UTC unix): day, week, month. */
function flxai_period_starts( $now = null ) {
	$tz  = wp_timezone();
	$now = new DateTimeImmutable( '@' . ( $now ? (int) $now : time() ) );
	$now = $now->setTimezone( $tz );
	$day = $now->setTime( 0, 0, 0 );
	$dow = (int) $day->format( 'N' ); // 1 = Pazartesi
	return array(
		'day'   => $day->getTimestamp(),
		'week'  => $day->modify( '-' . ( $dow - 1 ) . ' days' )->getTimestamp(),
		'month' => $day->modify( 'first day of this month' )->getTimestamp(),
	);
}

/** Dönem bitişleri (sınırın yenileneceği an). */
function flxai_period_ends() {
	$s  = flxai_period_starts();
	$tz = wp_timezone();
	$d  = ( new DateTimeImmutable( '@' . $s['day'] ) )->setTimezone( $tz );
	$w  = ( new DateTimeImmutable( '@' . $s['week'] ) )->setTimezone( $tz );
	$m  = ( new DateTimeImmutable( '@' . $s['month'] ) )->setTimezone( $tz );
	return array(
		'day'   => $d->modify( '+1 day' )->getTimestamp(),
		'week'  => $w->modify( '+7 days' )->getTimestamp(),
		'month' => $m->modify( 'first day of next month' )->getTimestamp(),
	);
}

/** Harcanan USD: day, week, month (tek sorgu; istek başına bir kez okunur). */
function flxai_spent( $fresh = false ) {
	static $cache = null;
	if ( null !== $cache && ! $fresh ) {
		return $cache;
	}
	global $wpdb;
	$s   = flxai_period_starts();
	$min = min( $s );
	$row = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT SUM(CASE WHEN ts >= %d THEN cost ELSE 0 END) AS d, SUM(CASE WHEN ts >= %d THEN cost ELSE 0 END) AS w, SUM(cost) AS m FROM ' . flxai_table( 'log' ) . ' WHERE ts >= %d', // phpcs:ignore
			$s['day'],
			$s['week'],
			$min
		),
		ARRAY_A
	);
	// Ay başı haftadan sonra olabilir: ay toplamı ayrı süzülür.
	$month = $s['month'] === $min ? (float) ( $row['m'] ?? 0 ) : (float) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(cost) FROM ' . flxai_table( 'log' ) . ' WHERE ts >= %d', $s['month'] ) ); // phpcs:ignore
	$cache = array(
		'day'   => (float) ( $row['d'] ?? 0 ),
		'week'  => (float) ( $row['w'] ?? 0 ),
		'month' => $month,
	);
	return $cache;
}

function flxai_limits() {
	return array(
		'day'   => max( 0, (float) flxai_setting( 'limit_day', 0 ) ),
		'week'  => max( 0, (float) flxai_setting( 'limit_week', 0 ) ),
		'month' => max( 0, (float) flxai_setting( 'limit_month', 0 ) ),
	);
}

function flxai_period_label( $p ) {
	$l = array(
		'day'   => __( 'Daily', 'flx-ai' ),
		'week'  => __( 'Weekly', 'flx-ai' ),
		'month' => __( 'Monthly', 'flx-ai' ),
	);
	return $l[ $p ] ?? $p;
}

/**
 * Sınır durumu: her dönem için limit, harcanan, yüzde, aşıldı mı.
 *
 * @return array
 */
function flxai_budget_status( $fresh = false ) {
	$spent  = flxai_spent( $fresh );
	$limits = flxai_limits();
	$ends   = flxai_period_ends();
	$out    = array();
	foreach ( $limits as $p => $lim ) {
		$out[ $p ] = array(
			'label'  => flxai_period_label( $p ),
			'limit'  => $lim,
			'spent'  => $spent[ $p ],
			'pct'    => $lim > 0 ? min( 999, round( $spent[ $p ] / $lim * 100, 1 ) ) : 0,
			'over'   => $lim > 0 && $spent[ $p ] >= $lim,
			'resets' => $ends[ $p ],
		);
	}
	return $out;
}

/**
 * İstek öncesi sınır kontrolü. Aşıldıysa ve ayar "durdur" ise WP_Error.
 * $opts['ignore_limit'] yalnız yönetici testleri içindir.
 */
function flxai_budget_check( array $opts = array() ) {
	if ( ! empty( $opts['ignore_limit'] ) ) {
		return true;
	}
	$st = flxai_budget_status();
	foreach ( $st as $p => $row ) {
		if ( $row['over'] && 'block' === flxai_setting( 'limit_action', 'block' ) ) {
			return new WP_Error(
				'flxai_limit',
				/* translators: 1: period (Günlük…), 2: limit, 3: reset date */
				sprintf( __( '%1$s AI spending limit (%2$s) reached. It resets %3$s; you can change it in Flux AI & MCP › Settings.', 'flx-ai' ), $row['label'], flxai_usd( $row['limit'], 2 ), flx_date( $row['resets'], 'datetime' ) ),
				array( 'status' => 402, 'http' => 402, 'period' => $p )
			);
		}
	}
	return true;
}

/** Kayıt sonrası: %80 ve %100 eşiğinde bir kez bildirim (dönem başına). */
function flxai_budget_after_log() {
	if ( ! flxai_setting( 'limit_notify', 1 ) ) {
		return;
	}
	$st   = flxai_budget_status( true );
	$warn = max( 1, min( 99, (int) flxai_setting( 'limit_warn_pct', 80 ) ) );
	$sent = get_option( 'flxai_budget_notified', array() );
	$sent = is_array( $sent ) ? $sent : array();
	$s    = flxai_period_starts();
	foreach ( $st as $p => $row ) {
		if ( $row['limit'] <= 0 ) {
			continue;
		}
		$level = $row['over'] ? 100 : ( $row['pct'] >= $warn ? $warn : 0 );
		$key   = $p . ':' . $s[ $p ];
		if ( ! $level || (int) ( $sent[ $key ] ?? 0 ) >= $level ) {
			continue;
		}
		$sent[ $key ] = $level;
		/* translators: 1: period, 2: percent */
		$subject = sprintf( __( 'Flux AI: %2$s%% of the %1$s spending limit used', 'flx-ai' ), $row['label'], number_format_i18n( $row['pct'], 0 ) );
		$body    = sprintf(
			/* translators: 1: spent, 2: limit, 3: reset date, 4: url */
			__( 'Spent:%1$s/ Limit:%2$s
Regeneration:%3$s

Detail:%4$s', 'flx-ai' ),
			flxai_usd( $row['spent'], 2 ),
			flxai_usd( $row['limit'], 2 ),
			flx_date( $row['resets'], 'datetime' ),
			admin_url( 'admin.php?page=flx-ai-billing' )
		);
		$to = get_option( 'admin_email' );
		if ( function_exists( 'flxi_send_email' ) ) {
			flxi_send_email( array( 'to' => $to, 'subject' => $subject, 'body' => nl2br( esc_html( $body ) ) ) );
		} else {
			wp_mail( $to, $subject, $body );
		}
	}
	// Eski dönem anahtarları temizlenir.
	$keep = array();
	foreach ( $sent as $k => $v ) {
		list( $p, $start ) = array_pad( explode( ':', $k ), 2, 0 );
		if ( isset( $s[ $p ] ) && (int) $start === (int) $s[ $p ] ) {
			$keep[ $k ] = $v;
		}
	}
	update_option( 'flxai_budget_notified', $keep, false );
}

/** Sınır doluysa ya da yaklaştıysa yöneticilere wp-admin uyarısı. */
add_action(
	'admin_notices',
	function () {
		if ( ! flxai_can_manage() ) {
			return;
		}
		$warn = (int) flxai_setting( 'limit_warn_pct', 80 );
		foreach ( flxai_budget_status() as $row ) {
			if ( $row['limit'] <= 0 || $row['pct'] < $warn ) {
				continue;
			}
			$cls = $row['over'] ? 'notice-error' : 'notice-warning';
			printf(
				'<div class="notice %1$s"><p><strong>Flux AI & MCP:</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
				esc_attr( $cls ),
				esc_html(
					sprintf(
						/* translators: 1: period, 2: spent, 3: limit, 4: percent */
						__( '%1$s spending: %2$s / %3$s (%4$s%%).', 'flx-ai' ),
						$row['label'],
						flxai_usd( $row['spent'], 2 ),
						flxai_usd( $row['limit'], 2 ),
						number_format_i18n( $row['pct'], 0 )
					) . ( $row['over'] && 'block' === flxai_setting( 'limit_action' ) ? ' ' . __( 'AI requests are stopped.', 'flx-ai' ) : '' )
				),
				esc_url( admin_url( 'admin.php?page=flx-ai-billing' ) ),
				esc_html__( 'Billing', 'flx-ai' )
			);
			return;
		}
	}
);
