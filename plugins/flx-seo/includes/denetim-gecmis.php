<?php
/**
 * Site denetimi geçmişi ve zamanlanmış (arka planda) denetim.
 *
 * Geçmiş: option `flxseo_audit_history` (autoload kapalı, en yeni başta, en çok 90 kayıt). Her kayıt bir çalıştırmanın özeti:
 *   t, trigger (auto|manual|mcp|fix), url, counts, scores { mobile|desktop: { performance, accessibility, … } },
 *   metrics { mobile|desktop: { lcp, cls, tbt, fcp, si } }, psi_time, items [[id, priority, title]], new [id], fixed [id].
 *
 * Zamanlama (flxseo ayarları audit_freq daily|weekly|off, audit_day 1–7 (Pazartesi=1), audit_hour 0–23, audit_psi, audit_mail):
 * Hostinger'da WP-Cron güvenilir değil; üç tetik birlikte çalışır: saatlik WP-Cron olayı, herhangi bir PHP
 * isteğinin sonunda (yanıt gönderildikten sonra) kontrol ve adımlar arası kendini çağıran admin-ajax isteği.
 * İstek sonu adımı çalıştırmaz; işi açar ve belirteçli loopback gönderir. Saatlik cron olayı adımı kendi yürütür
 * (loopback'in engelli olduğu sunucuda denetim yine ilerlesin). Ajax herkese açık değildir: yönetici nonce'u ya da
 * denetim başlarken üretilen belirteç.
 * Her adım tek iş yapar (PageSpeed mobil, PageSpeed masaüstü, site içi denetim). Web isteğinde PageSpeed en çok 40 sn
 * bekler; zaman aşımı o adımı atlar, kilit iki adımın aynı anda çalışmasını engeller.
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FLXSEO_HISTORY_MAX = 90;

/**
 * @return list<array<string, mixed>>
 */
function flxseo_audit_history( $limit = FLXSEO_HISTORY_MAX ) {
	$h = get_option( 'flxseo_audit_history', array() );
	return array_slice( is_array( $h ) ? $h : array(), 0, $limit );
}

/** Geçerli raporu geçmişe yazar, öncekiyle farkı (yeni/çözülen) hesaplar. */
function flxseo_audit_snapshot( $trigger = 'manual' ) {
	$r     = flxseo_audit_report();
	$hist  = flxseo_audit_history();
	$prev  = $hist[0] ?? null;
	$items = array();
	foreach ( $r['findings'] as $f ) {
		$items[] = array( $f['id'], $f['priority'], $f['title'] );
	}
	$ids      = array_column( $items, 0 );
	$prev_ids = $prev ? array_column( (array) $prev['items'], 0 ) : $ids;
	$scores   = array();
	$metrics  = array();
	$psi_time = 0;
	foreach ( (array) $r['psi'] as $s => $p ) {
		$scores[ $s ] = array_map( fn( $x ) => $x['score'], (array) ( $p['scores'] ?? array() ) );
		$m            = (array) ( $p['metrics'] ?? array() );
		$metrics[ $s ] = array(
			'fcp' => $m['first-contentful-paint']['value'] ?? '',
			'lcp' => $m['largest-contentful-paint']['value'] ?? '',
			'tbt' => $m['total-blocking-time']['value'] ?? '',
			'cls' => $m['cumulative-layout-shift']['value'] ?? '',
			'si'  => $m['speed-index']['value'] ?? '',
		);
		$psi_time = max( $psi_time, (int) ( $p['time'] ?? 0 ) );
	}
	$entry = array(
		't'        => time(),
		'trigger'  => sanitize_key( $trigger ),
		'url'      => (string) ( $r['local']['url'] ?? home_url( '/' ) ),
		'counts'   => $r['counts'],
		'scores'   => $scores,
		'metrics'  => $metrics,
		'psi_time' => $psi_time,
		'agentic'  => count( array_filter( flxseo_audit_agentic( $r ), fn( $c ) => $c[1] ) ) . '/' . count( flxseo_audit_agentic( $r ) ),
		'items'    => $items,
		'new'      => array_values( array_diff( $ids, $prev_ids ) ),
		'fixed'    => array_values( array_diff( $prev_ids, $ids ) ),
	);
	array_unshift( $hist, $entry );
	update_option( 'flxseo_audit_history', array_slice( $hist, 0, FLXSEO_HISTORY_MAX ), false );
	return $entry;
}

/** Elle (panel, MCP, düzeltme) çalıştırmalardan sonra geçmişe yaz. */
add_action( 'flxseo_audit_done', 'flxseo_audit_snapshot' );

/* ---------------------------------------------------------------------------
 * Zamanlama
 * ------------------------------------------------------------------------- */

function flxseo_audit_freqs() {
	return array(
		'daily'  => __( 'Daily', 'flx-seo' ),
		'weekly' => __( 'Weekly', 'flx-seo' ),
		'off'    => __( 'Off', 'flx-seo' ),
	);
}

/** Pazartesi=1 … Pazar=7. */
function flxseo_audit_days() {
	return array(
		'1' => __( 'Monday', 'flx-seo' ),
		'2' => __( 'Tuesday', 'flx-seo' ),
		'3' => __( 'Wednesday', 'flx-seo' ),
		'4' => __( 'Thursday', 'flx-seo' ),
		'5' => __( 'Friday', 'flx-seo' ),
		'6' => __( 'Saturday', 'flx-seo' ),
		'7' => __( 'Sunday', 'flx-seo' ),
	);
}

/** Verilen andan sonraki ilk planlı zaman (site saat diliminde); kapalıysa 0.
 * @return int
 */
function flxseo_audit_next_after( $after ) {
	$freq = (string) flxseo_opt( 'audit_freq' );
	if ( ! in_array( $freq, array( 'daily', 'weekly' ), true ) ) {
		return 0;
	}
	$tz   = wp_timezone();
	$hour = max( 0, min( 23, (int) flxseo_opt( 'audit_hour' ) ) );
	$day  = max( 1, min( 7, (int) flxseo_opt( 'audit_day' ) ) );
	$d    = ( new DateTimeImmutable( '@' . (int) $after ) )->setTimezone( $tz )->setTime( $hour, 0 );
	for ( $i = 0; $i < 9; $i++ ) {
		$c = $d->modify( '+' . $i . ' day' );
		if ( $c->getTimestamp() > $after && ( 'daily' === $freq || (int) $c->format( 'N' ) === $day ) ) {
			return $c->getTimestamp();
		}
	}
	return 0;
}

/**
 * @return int
 */
function flxseo_audit_next_run() {
	$last = (int) get_option( 'flxseo_audit_last_auto', 0 );
	if ( ! $last ) {
		// İlk kez: kurulumdan sonraki ilk planlı saat.
		$last = (int) get_option( 'flxseo_audit_sched_from', 0 );
		if ( ! $last ) {
			$last = time();
			update_option( 'flxseo_audit_sched_from', $last, false );
		}
	}
	return flxseo_audit_next_after( $last );
}

/** Zamanlanmış işin adımları. */
function flxseo_audit_steps() {
	$steps = array();
	$host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	if ( flxseo_opt( 'audit_psi' ) && flxseo_psi_key() && ! preg_match( '/^(localhost|127\.|10\.|192\.168\.)|\.(local|test)$/', $host ) ) {
		$steps = array( 'mobile', 'desktop' );
	}
	$steps[] = 'local';
	return $steps;
}

/** Denetim işini ve loopback belirtecini açar. Belirteç iş bitince seçenekle birlikte silinir. */
function flxseo_audit_open_job() {
	$existing = get_option( 'flxseo_audit_job' );
	if ( is_array( $existing ) && ! empty( $existing['token'] ) ) {
		return $existing;
	}
	$job = array(
		'steps'   => flxseo_audit_steps(),
		'started' => time(),
		'errors'  => array(),
		'token'   => bin2hex( random_bytes( 16 ) ),
	);
	update_option( 'flxseo_audit_job', $job, false );
	return $job;
}

/**
 * Aynı adımın iki kez yürümesini engeller. Seçenek tablosu (add_option) tek birincil yazıcıda atomiktir.
 * Süreç ölürse kilit 5 dk sonra düşer.
 *
 * @return bool Kilidi bu istek aldıysa true.
 */
function flxseo_audit_lock() {
	$now = time();
	$row = get_option( 'flxseo_audit_lock', null );
	if ( is_array( $row ) && (int) ( $row['until'] ?? 0 ) > $now ) {
		return false;
	}
	if ( null !== $row ) {
		delete_option( 'flxseo_audit_lock' );
	}
	return (bool) add_option(
		'flxseo_audit_lock',
		array(
			'until' => $now + 5 * MINUTE_IN_SECONDS,
		),
		'',
		false
	);
}

function flxseo_audit_unlock() {
	delete_option( 'flxseo_audit_lock' );
}

/**
 * Zamanı geldiyse bir adım çalıştırır. Kilitli: aynı anda tek adım.
 *
 * @return string Ne yapıldı (MCP/test için): idle|locked|step:<ad>|done.
 */
function flxseo_audit_tick( $force = false ) {
	$job = get_option( 'flxseo_audit_job' );
	if ( ! is_array( $job ) ) {
		$next = flxseo_audit_next_run();
		if ( ! $force && ( ! $next || time() < $next ) ) {
			return 'idle';
		}
		$job = flxseo_audit_open_job();
	}
	if ( ! flxseo_audit_lock() ) {
		return 'locked';
	}
	$fresh = get_option( 'flxseo_audit_job' );
	if ( ! is_array( $fresh ) ) {
		flxseo_audit_unlock();
		return 'idle';
	}
	$job = $fresh;
	// Takılmış iş (ör. sunucu yeniden başladı): 2 saat sonra bırakılır.
	if ( time() - (int) $job['started'] > 2 * HOUR_IN_SECONDS ) {
		delete_option( 'flxseo_audit_job' );
		update_option( 'flxseo_audit_last_auto', time(), false );
		flxseo_audit_unlock();
		return 'idle';
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( flxseo_psi_http_timeout() + 10 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	$step = array_shift( $job['steps'] );
	$res  = 'local' === $step ? flxseo_audit_run_local() : flxseo_psi_run( $step );
	if ( is_wp_error( $res ) ) {
		// Zaman aşımı dahil: adım kayda geçer, kilit açılır, sonraki adım devam eder.
		$job['errors'][] = $step . ': ' . $res->get_error_message();
	}
	if ( $job['steps'] ) {
		update_option( 'flxseo_audit_job', $job, false );
		flxseo_audit_unlock();
		flxseo_audit_kick();
		return 'step:' . $step;
	}
	delete_option( 'flxseo_audit_job' );
	update_option( 'flxseo_audit_last_auto', time(), false );
	update_option( 'flxseo_audit_last_errors', $job['errors'], false );
	$entry = flxseo_audit_snapshot( 'auto' );
	flxseo_audit_mail( $entry );
	flxseo_audit_unlock();
	return 'done';
}

/** Bir sonraki adımı hemen başlatmak için engellemesiz istek. Belirteç gövdede gider, adreste değil. */
function flxseo_audit_kick() {
	$job = get_option( 'flxseo_audit_job' );
	if ( ! is_array( $job ) || empty( $job['token'] ) ) {
		return;
	}
	wp_remote_post(
		admin_url( 'admin-ajax.php' ),
		array(
			'timeout'   => 0.01,
			'blocking'  => false,
			'sslverify' => false,
			'body'      => array(
				'action' => 'flxseo_audit_tick',
				'token'  => $job['token'],
			),
		)
	);
}

/**
 * Ajax adımı: (a) denetim ekranının yetkisi + nonce, ya da (b) süren işin belirteci.
 * Girişsiz loopback (b) için nopriv kancası durur; belirteç yoksa iş yapılmaz.
 */
add_action( 'wp_ajax_nopriv_flxseo_audit_tick', 'flxseo_audit_tick_endpoint' );
add_action( 'wp_ajax_flxseo_audit_tick', 'flxseo_audit_tick_endpoint' );
function flxseo_audit_tick_authorized() {
	if ( is_user_logged_in() && current_user_can( 'manage_options' ) && check_ajax_referer( 'flxseo_audit_tick', false, false ) ) {
		return true;
	}
	$job   = get_option( 'flxseo_audit_job' );
	$given = isset( $_POST['token'] ) ? (string) wp_unslash( $_POST['token'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$have  = is_array( $job ) ? (string) ( $job['token'] ?? '' ) : '';
	if ( '' === $have || strlen( $given ) !== strlen( $have ) ) {
		return false;
	}
	return hash_equals( $have, $given );
}
function flxseo_audit_tick_endpoint() {
	if ( ! flxseo_audit_tick_authorized() ) {
		wp_die( '', '', array( 'response' => 403 ) );
	}
	ignore_user_abort( true );
	flxseo_audit_tick();
	wp_die( '', '', array( 'response' => 204 ) );
}

/*
 * WP-Cron: saatlik kontrol. Olay yalnız zamanı gelince çalışır (dışarıdan istendiği kadar değil), bu yüzden adımı
 * kendi yürütür; loopback'in engelli olduğu sunucuda denetim saatte bir adım ilerler.
 */
add_action( 'flxseo_audit_cron', 'flxseo_audit_tick' );
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'flxseo_audit_cron' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'flxseo_audit_cron' );
		}
	}
);
register_deactivation_hook(
	FLXSEO_FILE,
	function () {
		wp_clear_scheduled_hook( 'flxseo_audit_cron' );
	}
);

/*
 * Yedek tetik: cron çalışmasa da herhangi bir PHP isteğinin sonunda (yanıt kullanıcıya gittikten sonra) en çok 5 dakikada bir
 * zamanı kontrol eder. Yanıtı kapatamayan sunucularda yalnız admin-ajax ile başlatır (ziyaretçiyi bekletmez).
 */
add_action(
	'shutdown',
	function () {
		if ( wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || get_transient( 'flxseo_audit_peek' ) ) {
			return;
		}
		if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && 'flxseo_audit_tick' === $_REQUEST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		set_transient( 'flxseo_audit_peek', 1, 5 * MINUTE_IN_SECONDS );
		$job  = get_option( 'flxseo_audit_job' );
		$next = flxseo_audit_next_run();
		if ( ! is_array( $job ) && ( ! $next || time() < $next ) ) {
			return;
		}
		// Ziyaretçi isteğinde PageSpeed çalışmaz: iş ve belirteç yazılır, adım loopback'te yürür.
		if ( ! is_array( $job ) ) {
			flxseo_audit_open_job();
		}
		flxseo_audit_kick();
	},
	100
);

/* Ayar değişince takvim yeniden hesaplanır (yeni saat bugünden itibaren). */
add_action(
	'update_option_flxseo',
	function ( $old, $new ) {
		foreach ( array( 'audit_freq', 'audit_day', 'audit_hour' ) as $k ) {
			if ( ( $old[ $k ] ?? null ) !== ( $new[ $k ] ?? null ) ) {
				update_option( 'flxseo_audit_last_auto', time(), false );
				return;
			}
		}
	},
	10,
	2
);

/* ---------------------------------------------------------------------------
 * E-posta: yeni kritik/yüksek bulgu varsa
 * ------------------------------------------------------------------------- */

function flxseo_audit_mail( array $entry ) {
	if ( ! flxseo_opt( 'audit_mail' ) ) {
		return false;
	}
	$prio = array();
	foreach ( $entry['items'] as $it ) {
		$prio[ $it[0] ] = $it;
	}
	$new = array_values( array_filter( array_map( fn( $id ) => $prio[ $id ] ?? null, $entry['new'] ), fn( $it ) => $it && in_array( $it[1], array( 'critical', 'high' ), true ) ) );
	if ( ! $new ) {
		return false;
	}
	$to = trim( (string) flxseo_opt( 'audit_mail_to' ) );
	$to = is_email( $to ) ? $to : get_option( 'admin_email' );
	$p  = flxseo_audit_priorities();
	/* translators: 1: site adı, 2: bulgu sayısı */
	$subject = sprintf( __( '[%1$s] Site audit: %2$d new critical/high recommendations', 'flx-seo' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), count( $new ) );
	$lines   = array( __( 'The automatic site audit found new recommendations:', 'flx-seo' ), '' );
	foreach ( $new as $it ) {
		$lines[] = '- [' . ( $p[ $it[1] ][1] ?? $it[1] ) . '] ' . flxseo_audit_title( $it[0], $it[2] );
	}
	$c       = $entry['counts'];
	$lines[] = '';
	/* translators: 1: kritik, 2: yüksek, 3: orta, 4: düşük bulgu sayısı */
	$lines[] = sprintf( __( 'Total: critical %1$d, high %2$d, medium %3$d, low %4$d.', 'flx-seo' ), $c['critical'], $c['high'], $c['medium'], $c['low'] );
	if ( $entry['fixed'] ) {
		/* translators: %d: çözülen öneri sayısı */
		$lines[] = sprintf( __( 'Resolved since the previous audit: %d.', 'flx-seo' ), count( $entry['fixed'] ) );
	}
	$lines[] = '';
	$lines[] = __( 'Details and fixes:', 'flx-seo' ) . ' ' . flxseo_admin_url( 'denetim' );
	return wp_mail( $to, $subject, implode( "\n", $lines ) );
}

/* ---------------------------------------------------------------------------
 * Panel: geçmiş kartı
 * ------------------------------------------------------------------------- */

/**
 * @return array<string, string>
 */
function flxseo_audit_triggers() {
	return array(
		'auto'   => __( 'Automatic', 'flx-seo' ),
		'manual' => __( 'Manual', 'flx-seo' ),
		'mcp'    => __( 'AI', 'flx-seo' ),
		'fix'    => __( 'After fix', 'flx-seo' ),
	);
}

/** Gösterilen dilde başlık (katalogda varsa). */
function flxseo_audit_title( $id, $fallback ) {
	$c = flxseo_audit_catalog();
	return isset( $c[ $id ] ) ? $c[ $id ][1] : $fallback;
}

/* ---------------------------------------------------------------------------
 * MCP: geçmiş
 * ------------------------------------------------------------------------- */

add_filter(
	'flx_mcp_tools',
	function ( $tools ) {
		$tools['flxseo_site_audit_history'] = array(
			'aliases'     => array( 'site_audit_history' ), // Flux SEO 1.5.x ve öncesindeki ad.
			'source'      => 'flx-seo',
			'title'       => __( 'Site audit history', 'flx-seo' ),
			'description' => 'Past site audit reports, newest first: time, trigger (auto|manual|mcp|fix), counts by priority, PageSpeed scores and Core Web Vitals per strategy, agentic x/y, new and fixed finding ids since the previous report. Also returns the automatic schedule (next_run). Use it to show trends or to check whether a fix stuck.',
			'readonly'    => true,
			'cap'         => 'manage_options',
			'log'         => false,
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'limit'         => array( 'type' => 'integer', 'default' => 10, 'maximum' => FLXSEO_HISTORY_MAX ),
					'with_findings' => array( 'type' => 'boolean', 'default' => false, 'description' => 'Include every finding id/priority of each report.' ),
				),
			),
			'run'         => function ( $a ) {
				$out = array();
				foreach ( flxseo_audit_history( max( 1, min( FLXSEO_HISTORY_MAX, (int) ( $a['limit'] ?? 10 ) ) ) ) as $e ) {
					$titles = array();
					foreach ( (array) $e['items'] as $it ) {
						$titles[ $it[0] ] = $it[2];
					}
					$row = array(
						'time'    => gmdate( 'c', (int) $e['t'] ),
						'trigger' => $e['trigger'],
						'counts'  => $e['counts'],
						'scores'  => $e['scores'],
						'metrics' => $e['metrics'],
						'agentic' => $e['agentic'] ?? '',
						'new'     => array_map( fn( $id ) => array( 'id' => $id, 'title' => $titles[ $id ] ?? $id ), $e['new'] ),
						'fixed'   => array_map( fn( $id ) => array( 'id' => $id, 'title' => flxseo_audit_title( $id, $id ) ), $e['fixed'] ),
					);
					if ( ! empty( $a['with_findings'] ) ) {
						$row['findings'] = array_map( fn( $it ) => array( 'id' => $it[0], 'priority' => $it[1] ), (array) $e['items'] );
					}
					$out[] = $row;
				}
				$next = flxseo_audit_next_run();
				return array(
					'schedule' => array(
						'frequency' => flxseo_opt( 'audit_freq' ),
						'day'       => (int) flxseo_opt( 'audit_day' ),
						'hour'      => (int) flxseo_opt( 'audit_hour' ),
						'pagespeed' => (bool) ( flxseo_opt( 'audit_psi' ) && flxseo_psi_key() ),
						'email'     => (bool) flxseo_opt( 'audit_mail' ),
						'next_run'  => $next ? gmdate( 'c', $next ) : null,
					),
					'reports'  => $out,
				);
			},
		);
		return $tools;
	},
	6
);
