<?php
/**
 * Bağış asistanının çalışma özeti: günlük sayaçlar (harcama, token, cevap, fiyat listesi, bağış, devir, dekont)
 * ve AI asistan sayfasındaki özet kartları. Test sohbeti ve benchmark sayılmaz.
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bugünün sayaçlarına ekler. $inc: alan => artış; 'pl' alanı fiyat listesi gönderilen sohbet kimliğidir.
 * $chat_id verilirse yapay zekâ kullanımı o sohbetin sayacına da yazılır (1.12.0, sohbet ⋮ › Asistan kullanımı).
 */
function flxc_bot_stat_add( array $inc, array $usage = array(), $chat_id = 0 ) {
	if ( $chat_id && $usage ) {
		flxc_chat_usage_add( (int) $chat_id, $usage );
	}
	$day = wp_date( 'Y-m-d' );
	$all = get_option( 'flxc_bot_stats', array() );
	$all = is_array( $all ) ? $all : array();
	$d   = isset( $all[ $day ] ) && is_array( $all[ $day ] ) ? $all[ $day ] : array();
	foreach ( $inc as $k => $v ) {
		if ( 'pl' === $k ) {
			$d['pl']   = isset( $d['pl'] ) ? (array) $d['pl'] : array();
			$d['pl'][] = (int) $v;
			$d['pl']   = array_slice( array_values( array_unique( $d['pl'] ) ), -2000 );
			continue;
		}
		$d[ $k ] = ( $d[ $k ] ?? 0 ) + $v;
	}
	foreach ( $usage as $u ) {
		$m = (string) ( $u['model'] ?? '' );
		$d['calls'] = ( $d['calls'] ?? 0 ) + 1;
		$d['in']    = ( $d['in'] ?? 0 ) + (int) $u['in'];
		$d['out']   = ( $d['out'] ?? 0 ) + (int) $u['out'];
		$d['cost']  = ( $d['cost'] ?? 0 ) + (float) $u['cost'];
		if ( '' !== $m ) {
			$x = $d['m'][ $m ] ?? array( 'n' => 0, 'in' => 0, 'out' => 0, 'cost' => 0.0 );
			$d['m'][ $m ] = array( 'n' => $x['n'] + 1, 'in' => $x['in'] + (int) $u['in'], 'out' => $x['out'] + (int) $u['out'], 'cost' => $x['cost'] + (float) $u['cost'] );
		}
	}
	$all[ $day ] = $d;
	ksort( $all );
	if ( count( $all ) > 800 ) {
		$all = array_slice( $all, -800, null, true );
	}
	update_option( 'flxc_bot_stats', $all, false );
}

/** Sohbetin yapay zekâ kullanımı: toplam + son 31 gün (istek, giriş/çıkış token, maliyet). Sohbet meta'sında 'use'. */
function flxc_chat_usage_add( $chat_id, array $usage ) {
	$chat = flxc_get_chat( $chat_id );
	if ( ! $chat ) {
		return;
	}
	$meta = flxc_decode( $chat['meta'] );
	$use  = isset( $meta['use'] ) && is_array( $meta['use'] ) ? $meta['use'] : array();
	$day  = wp_date( 'Y-m-d' );
	$add  = function ( $x ) use ( $usage ) {
		$x = wp_parse_args( is_array( $x ) ? $x : array(), array( 'calls' => 0, 'in' => 0, 'out' => 0, 'cost' => 0.0 ) );
		foreach ( $usage as $u ) {
			$x['calls']++;
			$x['in']   += (int) ( $u['in'] ?? 0 );
			$x['out']  += (int) ( $u['out'] ?? 0 );
			$x['cost'] += (float) ( $u['cost'] ?? 0 );
		}
		return $x;
	};
	$use['all']         = $add( $use['all'] ?? null );
	$days               = isset( $use['days'] ) && is_array( $use['days'] ) ? $use['days'] : array();
	$days[ $day ]       = $add( $days[ $day ] ?? null );
	ksort( $days );
	$use['days']        = array_slice( $days, -31, null, true );
	$meta['use']        = $use;
	flxc_update_chat( $chat_id, array( 'meta' => flxc_json( $meta ) ) );
}

/** Panel için: bugün, toplam, son 7 gün ve günlük cevap sınırı. */
function flxc_chat_usage_for_js( $chat ) {
	$meta  = flxc_decode( $chat['meta'] );
	$use   = isset( $meta['use'] ) && is_array( $meta['use'] ) ? $meta['use'] : array();
	$z     = array( 'calls' => 0, 'in' => 0, 'out' => 0, 'cost' => 0.0 );
	$norm  = function ( $x ) use ( $z ) {
		$x = wp_parse_args( is_array( $x ) ? $x : array(), $z );
		return array( 'calls' => (int) $x['calls'], 'in' => (int) $x['in'], 'out' => (int) $x['out'], 'cost' => round( (float) $x['cost'], 6 ) );
	};
	$days  = isset( $use['days'] ) && is_array( $use['days'] ) ? $use['days'] : array();
	$today = wp_date( 'Y-m-d' );
	$list  = array();
	for ( $i = 0; $i < 7; $i++ ) {
		$d      = wp_date( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
		$list[] = array( 'day' => $d ) + $norm( $days[ $d ] ?? null );
	}
	$state = flxc_bot_state( $chat );
	return array(
		'today'   => $norm( $days[ $today ] ?? null ),
		'all'     => $norm( $use['all'] ?? null ),
		'days'    => $list,
		'replies' => $state['day'] === $today ? (int) $state['n'] : 0,
		'limit'   => flxc_bot_daily_limit(),
		'limited' => $state['day'] === $today && (int) $state['n'] >= flxc_bot_daily_limit(),
	);
}

/** Asistanın açtığı bağış kayıtları (onaylananları saymak için). */
function flxc_bot_stat_donation( $id ) {
	if ( flxc_flxb_on() ) {
		flxc_bot_stats_backfill();
	}
	$l   = get_option( 'flxc_bot_donations', array() );
	$l   = is_array( $l ) ? $l : array();
	$l[] = array( (int) $id, time() );
	update_option( 'flxc_bot_donations', array_slice( $l, -3000 ), false );
	flxc_bot_stat_add( array( 'donations' => 1 ) );
}

/** Aralığın başlangıç günü (site saat dilimi). */
function flxc_bot_stats_from( $range ) {
	$now = new DateTime( 'now', wp_timezone() );
	switch ( $range ) {
		case 'today':
			break;
		case '7':
			$now->modify( '-6 days' );
			break;
		case '30':
			$now->modify( '-29 days' );
			break;
		case 'month':
			$now->modify( 'first day of this month' );
			break;
		default:
			return '0000-00-00';
	}
	return $now->format( 'Y-m-d' );
}

/** Özet: kartlar, model kırılımı ve günlük satırlar. */
function flxc_bot_stats( $range = '30' ) {
	global $wpdb;
	$from = flxc_bot_stats_from( $range );
	$ts   = '0000-00-00' === $from ? 0 : ( new DateTime( $from . ' 00:00:00', wp_timezone() ) )->getTimestamp();
	$keys = array( 'calls', 'in', 'out', 'cost', 'turns', 'price', 'donations', 'handoffs', 'errors', 'rc', 'rc_match', 'rc_bad', 'rc_not', 'summaries', 'optouts', 'reminders', 'paylinks', 'verified', 'fallbacks', 'vc_email', 'vc_whatsapp', 'vc_sms', 'vc_ok_email', 'vc_ok_phone', 'vc_wrong', 'vc_expired', 'vc_fail', 'vc_locked', 'cert_resent', 'canceled' );
	$sum  = array_fill_keys( $keys, 0 );
	$pl   = array();
	$mods = array();
	$days = array();
	foreach ( (array) get_option( 'flxc_bot_stats', array() ) as $day => $d ) {
		if ( $day < $from || ! is_array( $d ) ) {
			continue;
		}
		foreach ( $keys as $k ) {
			$sum[ $k ] += $d[ $k ] ?? 0;
		}
		$pl = array_merge( $pl, (array) ( $d['pl'] ?? array() ) );
		foreach ( (array) ( $d['m'] ?? array() ) as $m => $x ) {
			$y           = $mods[ $m ] ?? array( 'model' => $m, 'n' => 0, 'in' => 0, 'out' => 0, 'cost' => 0.0 );
			$mods[ $m ]  = array( 'model' => $m, 'n' => $y['n'] + $x['n'], 'in' => $y['in'] + $x['in'], 'out' => $y['out'] + $x['out'], 'cost' => $y['cost'] + $x['cost'] );
		}
		$days[] = array(
			'day'       => $day,
			'cost'      => (float) ( $d['cost'] ?? 0 ),
			'in'        => (int) ( $d['in'] ?? 0 ),
			'out'       => (int) ( $d['out'] ?? 0 ),
			'turns'     => (int) ( $d['turns'] ?? 0 ),
			'price'     => count( (array) ( $d['pl'] ?? array() ) ),
			'donations' => (int) ( $d['donations'] ?? 0 ),
			'handoffs'  => (int) ( $d['handoffs'] ?? 0 ),
			'rc'        => (int) ( $d['rc'] ?? 0 ),
		);
	}
	$mt   = flxc_table( 'messages' );
	$msgs = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) n, COUNT(DISTINCT chat_id) c FROM $mt WHERE source = 'bot' AND direction = 'out' AND ts >= %d", $ts * 1000 ), ARRAY_A ); // phpcs:ignore
	// Asistanın açtığı bağışlardan ödenenler.
	$conf  = 0;
	$pend  = 0;
	$amt   = 0.0;
	$cur   = 'TRY';
	$total = 0;
	if ( flxc_flxb_on() ) {
		flxc_bot_stats_backfill();
		foreach ( (array) get_option( 'flxc_bot_donations', array() ) as $x ) {
			if ( (int) $x[1] < $ts ) {
				continue;
			}
			$d = FLXB_Donations::get( (int) $x[0] );
			if ( ! $d ) {
				continue;
			}
			$total++;
			if ( 'completed' === $d['status'] ) {
				$conf++;
				$amt += (float) $d['amount'];
				$cur  = (string) $d['currency'];
			} elseif ( 'pending' === $d['status'] ) {
				$pend++;
			}
		}
	}
	$mods = array_values( $mods );
	usort(
		$mods,
		function ( $a, $b ) {
			return $b['cost'] <=> $a['cost'];
		}
	);
	return array(
		'range'     => $range,
		'from'      => $from,
		'cost'      => (float) $sum['cost'],
		'in'        => (int) $sum['in'],
		'out'       => (int) $sum['out'],
		'calls'     => (int) $sum['calls'],
		'turns'     => (int) $sum['turns'],
		'messages'  => (int) ( $msgs['n'] ?? 0 ),
		'people'    => (int) ( $msgs['c'] ?? 0 ),
		'price'     => count( array_unique( $pl ) ),
		'donations' => max( (int) $sum['donations'], $total ),
		'confirmed' => $conf,
		'pending'   => $pend,
		'amount'    => flxc_money( $amt, $cur ),
		'handoffs'  => (int) $sum['handoffs'],
		'errors'    => (int) $sum['errors'],
		'receipts'  => array( 'n' => (int) $sum['rc'], 'match' => (int) $sum['rc_match'], 'bad' => (int) $sum['rc_bad'], 'not' => (int) $sum['rc_not'] ),
		'models'    => $mods,
		'days'      => array_reverse( $days ),
		'since'     => flxc_bot_stats_first_day(),
		'summaries' => (int) $sum['summaries'],
		'optouts'   => (int) $sum['optouts'],
		'reminders' => (int) $sum['reminders'],
		'paylinks'  => (int) $sum['paylinks'],
		'verified'  => (int) $sum['verified'],
		'vc'        => array(
			'email'    => (int) $sum['vc_email'],
			'whatsapp' => (int) $sum['vc_whatsapp'],
			'sms'      => (int) $sum['vc_sms'],
			'okEmail'  => (int) $sum['vc_ok_email'],
			'okPhone'  => (int) $sum['vc_ok_phone'],
			'wrong'    => (int) $sum['vc_wrong'],
			'expired'  => (int) $sum['vc_expired'],
			'fail'     => (int) $sum['vc_fail'],
			'locked'   => (int) $sum['vc_locked'],
		),
		'certResent' => (int) $sum['cert_resent'],
		'canceled'   => (int) $sum['canceled'],
		'fallbacks' => (int) $sum['fallbacks'],
		'memory'    => function_exists( 'flxc_mem_stats' ) ? flxc_mem_stats( 5 ) : null,
	);
}

/** 1.6.3 öncesi: sohbetlerde kayıtlı son bağışlar bir kez listeye alınır. */
function flxc_bot_stats_backfill() {
	global $wpdb;
	if ( false !== get_option( 'flxc_bot_donations' ) ) {
		return;
	}
	$l = array();
	foreach ( (array) $wpdb->get_col( 'SELECT meta FROM ' . flxc_table( 'chats' ) . " WHERE meta LIKE '%\"donation\"%'" ) as $m ) { // phpcs:ignore
		$b  = flxc_decode( $m )['bot'] ?? array();
		$id = (int) ( $b['donation'] ?? 0 );
		$d  = $id ? FLXB_Donations::get( $id ) : null;
		if ( $d ) {
			$l[] = array( $id, (int) strtotime( $d['created_at'] . ' UTC' ) );
		}
	}
	update_option( 'flxc_bot_donations', $l, false );
}

function flxc_bot_stats_first_day() {
	$all = get_option( 'flxc_bot_stats', array() );
	return is_array( $all ) && $all ? (string) array_key_first( $all ) : '';
}

/* Ekibe devir (araç, temsilci isteği, günlük sınır, dekont). */
add_action(
	'flxc_bot_handoff',
	function () {
		flxc_bot_stat_add( array( 'handoffs' => 1 ) );
	}
);

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'flx-chat/v1',
			'/bot/stats',
			array(
				'methods'             => 'GET',
				'permission_callback' => function () {
					return flxc_can_manage();
				},
				'callback'            => function ( WP_REST_Request $req ) {
					$r = (string) $req->get_param( 'range' );
					return flxc_bot_stats( in_array( $r, array( 'today', '7', '30', 'month', 'all' ), true ) ? $r : '30' );
				},
			)
		);
	}
);

/* ---------- Sohbet ⋮ › Asistan kullanımı (1.12.0) ---------- */

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'flx-chat/v1',
			'/chats/(?P<id>\d+)/usage',
			array(
				'methods'             => 'GET',
				'permission_callback' => 'flxc_can_use',
				'callback'            => function ( WP_REST_Request $req ) {
					$chat = flxc_rest_chat_or_error( $req['id'] );
					return is_wp_error( $chat ) ? $chat : flxc_chat_usage_for_js( $chat );
				},
			)
		);
		register_rest_route(
			'flx-chat/v1',
			'/chats/(?P<id>\d+)/usage/reset',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'flxc_can_use',
				'callback'            => function ( WP_REST_Request $req ) {
					$chat = flxc_rest_chat_or_error( $req['id'] );
					if ( is_wp_error( $chat ) ) {
						return $chat;
					}
					$b        = flxc_bot_state( $chat );
					$b['day'] = wp_date( 'Y-m-d' );
					$b['n']   = 0;
					$b['lim'] = '';
					// Sınır yüzünden devredildiyse asistan yeniden cevap verir; elle kapatıldıysa kapalı kalır.
					$b['until'] = 0;
					flxc_bot_save_state( $chat['id'], $b );
					/* translators: %s: user */
					flxc_system_message( $chat, sprintf( __( 'Günlük asistan sınırı sıfırlandı (%s)', 'flx-chat' ), flxc_user_name( get_current_user_id() ) ) );
					$fresh = flxc_get_chat( $chat['id'] );
					return array( 'usage' => flxc_chat_usage_for_js( $fresh ), 'chat' => flxc_chat_for_js( $fresh ) );
				},
			)
		);
		register_rest_route(
			'flx-chat/v1',
			'/bot/limit',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'flxc_can_manage',
				'callback'            => function ( WP_REST_Request $req ) {
					flxc_update_settings( array( 'bot_daily_limit' => min( 500, max( 5, (int) $req->get_param( 'limit' ) ) ) ) );
					return array( 'limit' => flxc_bot_daily_limit() );
				},
			)
		);
	}
);
