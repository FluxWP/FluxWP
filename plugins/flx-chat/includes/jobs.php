<?php
/**
 * Arka plan işleri: asistan cevapları (webhook isteğinin sonunda çalışır, burada yalnız kaydı tutulur)
 * ve süresi dolan sohbetlerin özeti (site kendine engellemeyen istek atar, her istek tek iş yapar).
 * Aynı anda en fazla "workers_max" iş çalışır; özet yalnız o an cevap yazılan sohbet yokken başlar.
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

/** Arka plan işleri açık mı (özet ya da hatırlatıcı). */
function flxc_jobs_on() {
	return flxc_mem_on() || ( function_exists( 'flxc_rem_on' ) && flxc_rem_on() ) || ( function_exists( 'flxc_bot_enabled' ) && flxc_bot_enabled() );
}

function flxc_jobs_max() {
	return min( 30, max( 1, (int) flxc_setting( 'workers_max' ) ) );
}

/** Arka plan (özet, hatırlatıcı) işlerinin kullanabileceği en fazla işçi: kalanlar hep cevaplara ayrılır. */
function flxc_jobs_bg_max() {
	return max( 0, flxc_jobs_max() - min( flxc_jobs_max(), max( 0, (int) flxc_setting( 'workers_reserved' ) ) ) );
}

/** Özet saati mi? (site saat dilimine göre; ör. 03:00–05:00, gece yarısını geçen aralık da olur) */
function flxc_jobs_in_window( $ts = null ) {
	$from = flxc_rem_parse_time( (string) flxc_setting( 'mem_window_from' ), 180 );
	$to   = flxc_rem_parse_time( (string) flxc_setting( 'mem_window_to' ), 300 );
	if ( $from === $to ) {
		return true;
	}
	$d   = ( new DateTimeImmutable( '@' . ( null === $ts ? time() : (int) $ts ) ) )->setTimezone( wp_timezone() );
	$now = (int) $d->format( 'G' ) * 60 + (int) $d->format( 'i' );
	return $from < $to ? ( $now >= $from && $now < $to ) : ( $now >= $from || $now < $to );
}

/* 1.8.1: işçi sınırı 10'a çıktı. 1.20.0: 15-20 eş zamanlı sohbet için varsayılan 20; eski varsayılan (≤10) bir kez 20 yapılır. */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'flxc_mig_workers1200' ) ) {
			return;
		}
		update_option( 'flxc_mig_workers181', 1, false );
		update_option( 'flxc_mig_workers1200', 1, false );
		if ( (int) flxc_setting( 'workers_max' ) <= 10 ) {
			flxc_update_settings( array( 'workers_max' => 20 ) );
		}
	}
);

function flxc_job_start( $kind, $chat_id, $lock = 150 ) {
	global $wpdb;
	$now = time();
	$wpdb->insert(
		flxc_table( 'jobs' ),
		array(
			'kind'         => $kind,
			'chat_id'      => (int) $chat_id,
			'status'       => 'running',
			'attempts'     => 1,
			'locked_until' => $now + $lock,
			'created_at'   => $now,
			'started_at'   => $now,
		)
	);
	return (int) $wpdb->insert_id;
}

/** İşi bitirir. $usage: flxc_ai_chat _usage listesi. */
function flxc_job_finish( $id, $status, $note = '', array $usage = array() ) {
	global $wpdb;
	if ( ! $id ) {
		return;
	}
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT started_at FROM ' . flxc_table( 'jobs' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
	$f   = array(
		'status'       => $status,
		'finished_at'  => time(),
		'locked_until' => 0,
		'note'         => mb_substr( (string) $note, 0, 250 ),
		'cost'         => 0,
		'tokens_in'    => 0,
		'tokens_out'   => 0,
	);
	foreach ( $usage as $u ) {
		$f['cost']       += (float) ( $u['cost'] ?? 0 );
		$f['tokens_in']  += (int) ( $u['in'] ?? 0 );
		$f['tokens_out'] += (int) ( $u['out'] ?? 0 );
	}
	if ( $row ) {
		$f['ms'] = (int) max( 0, ( microtime( true ) - (int) $row['started_at'] ) * 1000 );
	}
	$wpdb->update( flxc_table( 'jobs' ), $f, array( 'id' => (int) $id ) );
}

function flxc_jobs_counts() {
	global $wpdb;
	$t    = flxc_table( 'jobs' );
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT kind, status, COUNT(*) n FROM $t WHERE status = 'queued' OR (status = 'running' AND locked_until >= %d) GROUP BY kind, status", time() ), ARRAY_A ); // phpcs:ignore
	$c    = array( 'reply' => 0, 'summary' => 0, 'queued' => 0, 'running' => 0 );
	foreach ( $rows as $r ) {
		if ( 'queued' === $r['status'] ) {
			$c['queued'] += (int) $r['n'];
		} else {
			$c[ 'reply' === $r['kind'] ? 'reply' : 'summary' ] += (int) $r['n'];
			$c['running'] += (int) $r['n'];
		}
	}
	// Çalışan benchmark da bir işçi sayılır.
	$c['bench'] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . flxc_table( 'bench_runs' ) . " WHERE status = 'running'" ) ? 1 : 0; // phpcs:ignore
	return $c;
}

/** Süresi geçen "çalışıyor" işler: özet yeniden kuyruğa (en çok 3 deneme), cevap hatalı. */
function flxc_jobs_recover() {
	global $wpdb;
	$t   = flxc_table( 'jobs' );
	$now = time();
	// Bekçi: yarıda ölen (sunucu kesti) ya da sırada çok bekleyen cevap; kişiye o işten sonra hiç mesaj gitmediyse yedek mesaj.
	$dead = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, chat_id, started_at, created_at, status FROM $t WHERE kind = 'reply' AND ((status = 'running' AND locked_until < %d) OR (status = 'queued' AND created_at < %d))", $now, $now - 3 * MINUTE_IN_SECONDS ), ARRAY_A ); // phpcs:ignore
	foreach ( $dead as $j ) {
		$wpdb->update( $t, array( 'status' => 'error', 'locked_until' => 0, 'finished_at' => $now, 'note' => 'queued' === $j['status'] ? __( 'Sırada çok bekledi.', 'flx-chat' ) : __( 'İş yarıda kaldı.', 'flx-chat' ) ), array( 'id' => (int) $j['id'] ) );
		$since = 1000 * (int) ( $j['started_at'] ? $j['started_at'] : $j['created_at'] );
		// Saatler önce ölmüş bir iş için şimdi özür yazılmaz; kişi yeniden yazınca asistan baştan cevaplar.
		if ( $since < ( $now - 15 * MINUTE_IN_SECONDS ) * 1000 ) {
			continue;
		}
		$out   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxc_table( 'messages' ) . " WHERE chat_id = %d AND direction = 'out' AND ts >= %d", (int) $j['chat_id'], $since ) ); // phpcs:ignore
		if ( ! $out && function_exists( 'flxc_bot_fallback' ) ) {
			delete_transient( 'flxc_bot_lock_' . (int) $j['chat_id'] );
			flxc_bot_busy( (int) $j['chat_id'], false );
			flxc_bot_fallback( flxc_get_chat( (int) $j['chat_id'] ), 'queued' === $j['status'] ? __( 'sırada çok bekledi', 'flx-chat' ) : __( 'cevap yarıda kaldı', 'flx-chat' ) );
		}
	}
	$wpdb->query( $wpdb->prepare( "UPDATE $t SET status = 'queued', locked_until = 0 WHERE status = 'running' AND kind = 'summary' AND locked_until < %d AND attempts < 3", $now ) ); // phpcs:ignore
	$wpdb->query( $wpdb->prepare( "UPDATE $t SET status = 'error', locked_until = 0, finished_at = %d, note = %s WHERE status = 'running' AND locked_until < %d", $now, __( 'İş yarıda kaldı.', 'flx-chat' ), $now ) ); // phpcs:ignore
}

/**
 * Yoğunluk: aynı anda çalışan cevap sayısı doluysa kişi sıraya alınır, boşalan işçi alır.
 *
 * @return bool sıraya alındı mı (true ise şimdi çalıştırma)
 */
function flxc_jobs_reply_queue( $chat_id ) {
	global $wpdb;
	if ( flxc_jobs_counts()['reply'] < flxc_jobs_max() ) {
		return false;
	}
	$t = flxc_table( 'jobs' );
	if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE kind = 'reply' AND chat_id = %d AND status = 'queued' LIMIT 1", $chat_id ) ) ) { // phpcs:ignore
		$wpdb->insert(
			$t,
			array(
				'kind'       => 'reply',
				'chat_id'    => (int) $chat_id,
				'status'     => 'queued',
				'created_at' => time(),
				'note'       => 'queue',
			)
		);
	}
	return true;
}

function flxc_jobs_has_queued_reply() {
	global $wpdb;
	return (bool) $wpdb->get_var( 'SELECT id FROM ' . flxc_table( 'jobs' ) . " WHERE kind = 'reply' AND status = 'queued' LIMIT 1" ); // phpcs:ignore
}

function flxc_jobs_enqueue_summary( $chat_id, $note = '' ) {
	global $wpdb;
	$t = flxc_table( 'jobs' );
	if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE kind = 'summary' AND chat_id = %d AND status IN ('queued','running') LIMIT 1", $chat_id ) ) ) { // phpcs:ignore
		return 0;
	}
	$wpdb->insert(
		$t,
		array(
			'kind'       => 'summary',
			'chat_id'    => (int) $chat_id,
			'status'     => 'queued',
			'created_at' => time(),
			'note'       => $note,
		)
	);
	return (int) $wpdb->insert_id;
}

/** Süresi dolan (son mesajdan bu yana mem_after_hours geçmiş) ve özetlenmemiş sohbetleri kuyruğa alır. */
function flxc_jobs_scan( $force = false ) {
	global $wpdb;
	if ( ! flxc_jobs_on() || ( ! $force && get_transient( 'flxc_jobs_scanned' ) ) ) {
		return 0;
	}
	set_transient( 'flxc_jobs_scanned', time(), MINUTE_IN_SECONDS );
	$ct   = flxc_table( 'chats' );
	$jt   = flxc_table( 'jobs' );
	$now  = time();
	// Biten işler 7 gün tutulur.
	$wpdb->query( $wpdb->prepare( "DELETE FROM $jt WHERE status IN ('done','skipped','error') AND finished_at < %d", $now - 7 * DAY_IN_SECONDS ) ); // phpcs:ignore
	$n = function_exists( 'flxc_rem_enqueue_due' ) ? flxc_rem_enqueue_due() : 0;
	// Özetler yalnız gece aralığında kuyruğa girer (gündüz canlı sohbetlere işçi kalsın).
	if ( ! flxc_mem_on() || ! flxc_jobs_in_window() ) {
		return $n;
	}
	$idle = ( $now - max( 1, (int) flxc_setting( 'mem_after_hours' ) ) * HOUR_IN_SECONDS ) * 1000;
	// İlk açılışta eski sohbetlerin hepsi değil, yalnız son 7 gün.
	$from = ( $now - 7 * DAY_IN_SECONDS ) * 1000;
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, phone, last_ts FROM $ct WHERE last_ts > mem_ts AND last_in_ts > mem_ts AND last_ts < %d AND last_ts > %d AND jid NOT LIKE %s AND jid NOT LIKE %s AND jid NOT LIKE %s AND id NOT IN (SELECT chat_id FROM $jt WHERE kind = 'summary' AND (status IN ('queued','running') OR (status = 'error' AND finished_at > %d))) ORDER BY last_ts DESC LIMIT 20", $idle, $from, '%@g.us', '%@broadcast', '%@newsletter', $now - 6 * HOUR_IN_SECONDS ), ARRAY_A ); // phpcs:ignore
	foreach ( $rows as $r ) {
		if ( flxc_is_notify_number( $r['phone'] ) ) {
			$wpdb->update( $ct, array( 'mem_ts' => (int) $r['last_ts'] ), array( 'id' => (int) $r['id'] ) );
			continue;
		}
		$n += flxc_jobs_enqueue_summary( (int) $r['id'] ) ? 1 : 0;
	}
	return $n;
}

/** Hesap/bütçe hatasında özetler bir süre durur (her sohbet için boşuna istek atılmasın). */
function flxc_jobs_paused() {
	$p = get_transient( 'flxc_jobs_pause' );
	return is_array( $p ) ? $p : null;
}

/**
 * Boş işçi varsa kuyruktaki özetleri başlatır. Kilit add_option ile (aynı anda iki istek fazla iş başlatmasın).
 */
function flxc_jobs_pump( $scan = true ) {
	global $wpdb;
	if ( ! flxc_jobs_on() || ! flxc_ai_ready() ) {
		return 0;
	}
	$lock = get_option( 'flxc_jobs_lock' );
	if ( $lock && time() - (int) $lock > 30 ) {
		delete_option( 'flxc_jobs_lock' );
	}
	// Aynı anda biten işler sırayla dener; kimse boşta kalmasın.
	$got = false;
	for ( $i = 0; $i < 15 && ! ( $got = add_option( 'flxc_jobs_lock', time(), '', 'no' ) ); $i++ ) {
		usleep( 200000 );
	}
	if ( ! $got ) {
		return 0;
	}
	$kick = array();
	try {
		flxc_jobs_recover();
		if ( $scan ) {
			flxc_jobs_scan();
		}
		if ( flxc_jobs_paused() ) {
			return 0;
		}
		$c = flxc_jobs_counts();
		if ( ! $c['queued'] ) {
			return 0;
		}
		$slots = flxc_jobs_max() - $c['running'] - $c['bench'];
		if ( $slots <= 0 ) {
			return 0;
		}
		$t = flxc_table( 'jobs' );
		// Öncelik canlı sohbet: sıradaki kişiler önce. Cevap yazılan ya da bekleyen kişi varken özet/hatırlatıcı başlamaz;
		// arka plan işleri en fazla flxc_jobs_bg_max() işçi kullanır, özetler yalnız gece aralığında (elle istenen hariç).
		$bg_slots = ( $c['reply'] > 0 || flxc_jobs_has_queued_reply() ) ? 0 : max( 0, flxc_jobs_bg_max() - ( $c['running'] - $c['reply'] ) - $c['bench'] );
		$win      = flxc_jobs_in_window() ? '' : " AND (kind <> 'summary' OR note = 'manual')";
		$bg       = $bg_slots > 0 ? " OR (kind <> 'reply'$win)" : '';
		$ids      = $wpdb->get_col( $wpdb->prepare( "SELECT id, kind FROM $t WHERE status = 'queued' AND (kind = 'reply'$bg) ORDER BY (kind = 'reply') DESC, (kind = 'reminder') DESC, (note = 'manual') DESC, id ASC LIMIT %d", $slots ) ); // phpcs:ignore
		$nbg      = 0;
		foreach ( (array) $ids as $k => $jid ) {
			if ( 'reply' !== $wpdb->get_var( $wpdb->prepare( "SELECT kind FROM $t WHERE id = %d", $jid ) ) && ++$nbg > $bg_slots ) { // phpcs:ignore
				unset( $ids[ $k ] );
			}
		}
		foreach ( (array) $ids as $id ) {
			$ok = $wpdb->query( $wpdb->prepare( "UPDATE $t SET status = 'running', attempts = attempts + 1, locked_until = %d, started_at = %d WHERE id = %d AND status = 'queued'", time() + 120, time(), $id ) ); // phpcs:ignore
			if ( $ok ) {
				$kick[] = (int) $id;
			}
		}
	} finally {
		delete_option( 'flxc_jobs_lock' );
	}
	// İstekler kilit bırakıldıktan sonra atılır: biten işçi hemen sıradakini alabilsin.
	foreach ( $kick as $id ) {
		flxc_jobs_kick( $id );
	}
	return count( $kick );
}

/** Panel ve webhook isteklerinden: en çok 20 sn'de bir. */
function flxc_jobs_maybe_pump() {
	do_action( 'flxc_jobs_poked' ); // zamanlanmış raporlar (reports.php) da buradan dürtülür
	// 1.20.0: Hostinger'da WP-Cron çalışmıyor; açık herhangi bir panel sekmesi takılan benchmark'ı da yeniden başlatır.
	if ( function_exists( 'flxc_bench_revive' ) && ! get_transient( 'flxc_bench_rv' ) ) {
		set_transient( 'flxc_bench_rv', 1, 20 );
		flxc_bench_revive();
	}
	if ( ! flxc_jobs_on() || get_transient( 'flxc_jobs_pumped' ) ) {
		return;
	}
	set_transient( 'flxc_jobs_pumped', 1, 20 );
	flxc_jobs_pump();
}

function flxc_jobs_kick( $id ) {
	if ( flxc_loopback( 'flx-chat/v1/jobs/bg/' . (int) $id ) ) {
		return true;
	}
	// Ulaşmadı: iş sıraya geri döner, sonraki dürtmede yeniden denenir (bekçi 3 dk sonra yedek mesaj gönderir).
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . flxc_table( 'jobs' ) . " SET status = 'queued', locked_until = 0, attempts = CASE WHEN attempts > 0 THEN attempts - 1 ELSE 0 END WHERE id = %d AND status = 'running'", (int) $id ) ); // phpcs:ignore
	return false;
}

/** Tek bir işi çalıştırır, sonra sıradakileri başlatır. */
function flxc_jobs_run( $id ) {
	global $wpdb;
	$job = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'jobs' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
	if ( ! $job || 'running' !== $job['status'] || ! in_array( $job['kind'], array( 'summary', 'reminder', 'reply' ), true ) ) {
		return false;
	}
	if ( 'reply' === $job['kind'] ) {
		// Sıradaki kişi: kaydı kapatılır, asistan turu kendi işini açar.
		flxc_job_finish( $id, 'done', __( 'sıradan alındı', 'flx-chat' ) );
		ignore_user_abort( true );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		flxc_bot_run( (int) $job['chat_id'] );
		flxc_jobs_pump( false );
		return true;
	}
	ignore_user_abort( true );
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	$r = 'reminder' === $job['kind']
		? flxc_rem_run( (int) $job['ref'], time() + 90 )
		: flxc_mem_summarize( (int) $job['chat_id'], time() + 90 );
	if ( 'error' === $r['status'] && ! empty( $r['error'] ) && flxc_ai_is_account_error( $r['error'] ) ) {
		set_transient( 'flxc_jobs_pause', array( 'reason' => $r['note'], 'at' => time() ), 30 * MINUTE_IN_SECONDS );
	}
	// Hata, deneme hakkı varsa kuyruğa döner.
	if ( 'error' === $r['status'] && (int) $job['attempts'] < 3 && empty( $r['error'] ) ) {
		$wpdb->update( flxc_table( 'jobs' ), array( 'status' => 'queued', 'locked_until' => 0, 'note' => mb_substr( $r['note'], 0, 250 ) ), array( 'id' => (int) $id ) );
	} else {
		flxc_job_finish( $id, $r['status'], $r['note'], $r['usage'] );
	}
	flxc_jobs_pump( false );
	return true;
}

function flxc_jobs_for_js( $limit = 50 ) {
	global $wpdb;
	$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'jobs' ) . ' ORDER BY id DESC LIMIT %d', $limit ), ARRAY_A ); // phpcs:ignore
	$out  = array();
	foreach ( $rows as $r ) {
		$chat  = flxc_get_chat( (int) $r['chat_id'] );
		$out[] = array(
			'id'       => (int) $r['id'],
			'kind'     => $r['kind'],
			'status'   => 'running' === $r['status'] && (int) $r['locked_until'] < time() ? 'stale' : $r['status'],
			'chat'     => (int) $r['chat_id'],
			'title'    => $chat ? flxc_chat_title( $chat ) : '#' . (int) $r['chat_id'],
			'url'      => admin_url( 'admin.php?page=flx-chat&chat=' . (int) $r['chat_id'] ),
			'attempts' => (int) $r['attempts'],
			'created'  => (int) $r['created_at'] * 1000,
			'started'  => (int) $r['started_at'] * 1000,
			'finished' => (int) $r['finished_at'] * 1000,
			'ms'       => (int) $r['ms'],
			'cost'     => (float) $r['cost'],
			'tokens'   => (int) $r['tokens_in'] + (int) $r['tokens_out'],
			'note'     => 'manual' === $r['note'] ? '' : (string) $r['note'],
			'manual'   => 'manual' === $r['note'],
		);
	}
	return $out;
}

function flxc_jobs_overview() {
	global $wpdb;
	$c     = flxc_jobs_counts();
	$ct    = flxc_table( 'chats' );
	$idle  = ( time() - max( 1, (int) flxc_setting( 'mem_after_hours' ) ) * HOUR_IN_SECONDS ) * 1000;
	$wait  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $ct WHERE last_ts > mem_ts AND last_in_ts > mem_ts AND last_ts >= %d AND jid NOT LIKE %s", $idle, '%@g.us' ) ); // phpcs:ignore
	$today = (array) $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) n, SUM(cost) cost FROM ' . flxc_table( 'jobs' ) . " WHERE kind = 'summary' AND status = 'done' AND finished_at >= %d", strtotime( 'today', time() ) ), ARRAY_A ); // phpcs:ignore
	$p     = flxc_jobs_paused();
	return array(
		'enabled'  => flxc_jobs_on(),
		'memory'   => flxc_mem_on(),
		'ready'    => flxc_ai_ready(),
		'max'      => flxc_jobs_max(),
		'hours'    => max( 1, (int) flxc_setting( 'mem_after_hours' ) ),
		'reserved' => flxc_jobs_max() - flxc_jobs_bg_max(),
		'window'   => flxc_setting( 'mem_window_from' ) . '–' . flxc_setting( 'mem_window_to' ),
		'counts'   => $c,
		'waiting'  => $wait,
		'today'    => array( 'n' => (int) ( $today['n'] ?? 0 ), 'cost' => (float) ( $today['cost'] ?? 0 ) ),
		'paused'   => $p ? $p['reason'] : '',
		'jobs'     => flxc_jobs_for_js(),
	);
}

/* ---------- Tetikleyiciler ---------- */

// Hostinger cron çalışmasa da WP-Cron ziyaretle tetiklenir; panel ve webhook istekleri de dürtür.
add_action(
	'init',
	function () {
		if ( flxc_jobs_on() && ! wp_next_scheduled( 'flxc_jobs_tick' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'flxc_jobs_tick' );
		}
	}
);
add_action( 'flxc_jobs_tick', 'flxc_jobs_pump' );
// Hatırlatıcı saatinde ayrıca tek seferlik tetik (saatlik tetiği beklemesin).
add_action(
	'flxc_rem_tick',
	function () {
		delete_transient( 'flxc_jobs_scanned' );
		flxc_jobs_pump();
		flxc_rem_schedule();
	}
);

add_filter(
	'rest_post_dispatch',
	function ( $res, $server, $req ) {
		if ( 'GET' === $req->get_method() && 0 === strpos( $req->get_route(), '/flx-chat/v1/' ) && flxc_can_use() ) {
			flxc_jobs_maybe_pump();
		}
		return $res;
	},
	10,
	3
);

/* ---------- REST ---------- */

add_action(
	'rest_api_init',
	function () {
		$ns  = 'flx-chat/v1';
		$mng = function () {
			return flxc_can_manage();
		};
		register_rest_route(
			$ns,
			'/jobs',
			array(
				'methods'             => 'GET',
				'permission_callback' => $mng,
				'callback'            => 'flxc_jobs_overview',
			)
		);
		register_rest_route(
			$ns,
			'/jobs/scan',
			array(
				'methods'             => 'POST',
				'permission_callback' => $mng,
				'callback'            => function () {
					delete_transient( 'flxc_jobs_pause' );
					$n = flxc_jobs_scan( true );
					flxc_jobs_pump( false );
					return array( 'queued' => $n ) + flxc_jobs_overview();
				},
			)
		);
		register_rest_route(
			$ns,
			'/jobs/bg/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'permission_callback' => function ( WP_REST_Request $req ) {
					return hash_equals( flxc_bench_key(), (string) $req->get_param( 'key' ) );
				},
				'callback'            => function ( WP_REST_Request $req ) {
					$early = flxc_detach_response();
					$ok    = flxc_jobs_run( (int) $req['id'] );
					if ( $early ) {
						exit;
					}
					return array( 'ok' => $ok );
				},
			)
		);
	}
);
