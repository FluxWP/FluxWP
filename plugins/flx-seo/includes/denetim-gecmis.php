<?php
/**
 * Site denetimi geçmişi ve zamanlanmış (arka planda) denetim.
 *
 * Geçmiş: option `flxseo_audit_history` (autoload kapalı, en yeni başta, en çok 90 kayıt). Her kayıt bir çalıştırmanın özeti:
 *   t, trigger (auto|manual|mcp|fix), url, counts, scores { mobile|desktop: { performance, accessibility, … } },
 *   metrics { mobile|desktop: { lcp, cls, tbt, fcp, si } }, psi_time, items [[id, priority, title]], new [id], fixed [id].
 *
 * Zamanlama (flxseo ayarları audit_freq daily|weekly|off, audit_day 1–7 (Pazartesi=1), audit_hour 0–23, audit_psi, audit_mail):
 * Hostinger'da WP-Cron güvenilir değil; üç tetik birlikte çalışır: saatlik WP-Cron olayı, herhangi bir PHP isteğinin
 * sonunda (yanıt gönderildikten sonra) kontrol ve adımlar arası kendini çağıran admin-ajax isteği. Her adım tek iş
 * (PageSpeed mobil, PageSpeed masaüstü, site içi denetim) yapar; böylece 60 sn PHP sınırına takılmaz.
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FLXSEO_HISTORY_MAX = 90;

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

/** Verilen andan sonraki ilk planlı zaman (site saat diliminde); kapalıysa 0. */
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
		$job = array(
			'steps'   => flxseo_audit_steps(),
			'started' => time(),
			'errors'  => array(),
		);
		update_option( 'flxseo_audit_job', $job, false );
	}
	if ( get_transient( 'flxseo_audit_lock' ) ) {
		return 'locked';
	}
	// Takılmış iş (ör. sunucu yeniden başladı): 2 saat sonra bırakılır.
	if ( time() - (int) $job['started'] > 2 * HOUR_IN_SECONDS ) {
		delete_option( 'flxseo_audit_job' );
		update_option( 'flxseo_audit_last_auto', time(), false );
		return 'idle';
	}
	set_transient( 'flxseo_audit_lock', 1, 5 * MINUTE_IN_SECONDS );
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	$step = array_shift( $job['steps'] );
	$res  = 'local' === $step ? flxseo_audit_run_local() : flxseo_psi_run( $step );
	if ( is_wp_error( $res ) ) {
		$job['errors'][] = $step . ': ' . $res->get_error_message();
	}
	if ( $job['steps'] ) {
		update_option( 'flxseo_audit_job', $job, false );
		delete_transient( 'flxseo_audit_lock' );
		flxseo_audit_kick();
		return 'step:' . $step;
	}
	delete_option( 'flxseo_audit_job' );
	update_option( 'flxseo_audit_last_auto', time(), false );
	update_option( 'flxseo_audit_last_errors', $job['errors'], false );
	$entry = flxseo_audit_snapshot( 'auto' );
	flxseo_audit_mail( $entry );
	delete_transient( 'flxseo_audit_lock' );
	return 'done';
}

/** Bir sonraki adımı hemen başlatmak için engellemesiz istek. */
function flxseo_audit_kick() {
	wp_remote_post(
		admin_url( 'admin-ajax.php?action=flxseo_audit_tick' ),
		array(
			'timeout'   => 0.01,
			'blocking'  => false,
			'sslverify' => false,
		)
	);
}

/* Herkese açık uç: yalnız zamanı gelmişse ve kilit yoksa iş yapar. */
add_action( 'wp_ajax_nopriv_flxseo_audit_tick', 'flxseo_audit_tick_endpoint' );
add_action( 'wp_ajax_flxseo_audit_tick', 'flxseo_audit_tick_endpoint' );
function flxseo_audit_tick_endpoint() {
	ignore_user_abort( true );
	flxseo_audit_tick();
	wp_die( '', '', array( 'response' => 204 ) );
}

/* WP-Cron: saatlik kontrol. */
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

function flxseo_audit_triggers() {
	return array(
		'auto'   => __( 'Automatic', 'flx-seo' ),
		'manual' => __( 'Manual', 'flx-seo' ),
		'mcp'    => __( 'AI', 'flx-seo' ),
		'fix'    => __( 'After fix', 'flx-seo' ),
	);
}

/** Geçmiş kartı (denetim sayfasında). */
function flxseo_audit_history_card() {
	$hist  = flxseo_audit_history( 30 );
	$trig  = flxseo_audit_triggers();
	$prios = flxseo_audit_priorities();
	$cat   = flxseo_audit_categories();
	$next  = is_array( get_option( 'flxseo_audit_job' ) ) ? time() : flxseo_audit_next_run();
	?>
	<section class="au-card au-history" id="au-history">
		<div class="au-bar">
			<h2><?php esc_html_e( 'Report history', 'flx-seo' ); ?> <span class="au-meta"><?php echo esc_html( number_format_i18n( count( flxseo_audit_history() ) ) ); ?></span></h2>
			<p class="au-meta">
				<?php
				if ( $next ) {
					/* translators: %s: tarih ve saat */
					printf( esc_html__( 'Next automatic audit: %s', 'flx-seo' ), esc_html( flx_date( $next, 'datetime' ) ) );
				} else {
					esc_html_e( 'Automatic audit is off.', 'flx-seo' );
				}
				$errs = (array) get_option( 'flxseo_audit_last_errors', array() );
				if ( $errs ) {
					echo ' · <span class="au-tone-bad">' . esc_html( implode( ' · ', $errs ) ) . '</span>';
				}
				?>
			</p>
		</div>
		<?php if ( ! $hist ) : ?>
			<p class="au-empty"><?php esc_html_e( 'No reports yet. Every audit (manual, automatic or by AI) is saved here.', 'flx-seo' ); ?></p>
		<?php else : ?>
			<div class="au-charts">
				<?php flxseo_audit_chart_counts( $hist ); ?>
				<?php flxseo_audit_chart_scores( $hist ); ?>
			</div>
			<div class="au-table-wrap">
				<table class="au-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Date', 'flx-seo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Trigger', 'flx-seo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Recommendations', 'flx-seo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'PageSpeed scores', 'flx-seo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Change', 'flx-seo' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $hist as $e ) : ?>
						<?php
						$titles = array();
						foreach ( (array) $e['items'] as $it ) {
							$titles[ $it[0] ] = $it;
						}
						?>
						<tr>
							<td class="au-td-date"><?php echo esc_html( flx_date( $e['t'], 'datetime' ) ); ?><br><span class="au-meta"><?php echo esc_html( flx_time_ago( $e['t'] ) ); ?></span></td>
							<td class="au-td-trig"><?php echo esc_html( $trig[ $e['trigger'] ] ?? $e['trigger'] ); ?></td>
							<td class="au-counts">
								<?php foreach ( $prios as $pk => $pv ) : ?>
									<span class="au-badge au-p-<?php echo esc_attr( $pk ); ?>" title="<?php echo esc_attr( $pv[1] ); ?>"><?php echo (int) ( $e['counts'][ $pk ] ?? 0 ); ?></span>
								<?php endforeach; ?>
							</td>
							<td class="au-scorecell">
								<?php flxseo_audit_score_rows( $e ); ?>
							</td>
							<td class="au-td-diff">
								<?php if ( ! $e['new'] && ! $e['fixed'] ) : ?>
									<span class="au-meta"><?php esc_html_e( 'No change', 'flx-seo' ); ?></span>
								<?php else : ?>
									<details>
										<summary>
											<?php if ( $e['new'] ) : ?>
												<span class="au-tone-bad">+<?php echo count( $e['new'] ); ?> <?php esc_html_e( 'new', 'flx-seo' ); ?></span>
											<?php endif; ?>
											<?php if ( $e['fixed'] ) : ?>
												<span class="au-tone-good">−<?php echo count( $e['fixed'] ); ?> <?php esc_html_e( 'resolved', 'flx-seo' ); ?></span>
											<?php endif; ?>
										</summary>
										<ul class="au-diff">
											<?php foreach ( $e['new'] as $id ) : ?>
												<li class="au-tone-bad">+ <?php echo esc_html( flxseo_audit_title( $id, $titles[ $id ][2] ?? $id ) ); ?></li>
											<?php endforeach; ?>
											<?php foreach ( $e['fixed'] as $id ) : ?>
												<li class="au-tone-good">✓ <?php echo esc_html( flxseo_audit_title( $id, $id ) ); ?></li>
											<?php endforeach; ?>
										</ul>
									</details>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
	<?php
}

/** Tablo hücresi: mobil ve masaüstü puanları + LCP. */
function flxseo_audit_score_rows( array $e ) {
	$cat    = flxseo_audit_categories();
	$labels = flxseo_audit_strategies();
	$any    = false;
	foreach ( $labels as $st => $label ) {
		$sc = (array) ( $e['scores'][ $st ] ?? array() );
		if ( ! $sc ) {
			continue;
		}
		$any = true;
		echo '<span class="au-srow"><span class="au-srow__k">' . esc_html( $label ) . '</span>';
		foreach ( $sc as $ck => $v ) {
			printf( '<span class="au-tone-%s" title="%s">%s</span>', esc_attr( flxseo_audit_tone( $v ) ), esc_attr( $cat[ $ck ] ?? $ck ), null === $v ? '–' : (int) $v );
		}
		if ( ! empty( $e['metrics'][ $st ]['lcp'] ) ) {
			echo '<span class="au-meta">LCP ' . esc_html( $e['metrics'][ $st ]['lcp'] ) . '</span>';
		}
		echo '</span>';
	}
	if ( ! $any ) {
		echo '<span class="au-meta">' . esc_html__( 'PageSpeed not run', 'flx-seo' ) . '</span>';
	}
}

function flxseo_audit_strategies() {
	return array(
		'mobile'  => __( 'Mobile', 'flx-seo' ),
		'desktop' => __( 'Desktop', 'flx-seo' ),
	);
}

/** Grafik x konumları: eskiden yeniye eşit aralık. */
function flxseo_audit_chart_x( $i, $n, $w, $pad ) {
	return $n < 2 ? $pad + ( $w - 2 * $pad ) / 2 : $pad + $i * ( $w - 2 * $pad ) / ( $n - 1 );
}

/** Öneri sayısı grafiği (önceliğe göre yığılmış sütunlar). */
function flxseo_audit_chart_counts( array $hist ) {
	$rev   = array_reverse( $hist );
	$n     = count( $rev );
	$prios = flxseo_audit_priorities();
	$max   = max( 1, max( array_map( fn( $e ) => array_sum( (array) $e['counts'] ), $rev ) ) );
	$w     = 600;
	$h     = 180;
	$top   = 10;
	$bot   = 24;
	$left  = 30;
	$slot  = ( $w - $left - 6 ) / max( 1, $n );
	$bw    = max( 4, min( 28, $slot * 0.7 ) );
	$step  = $max > 20 ? 10 : ( $max > 8 ? 5 : 2 );
	?>
	<figure class="au-chart">
		<figcaption><?php esc_html_e( 'Recommendations', 'flx-seo' ); ?></figcaption>
		<svg viewBox="0 0 <?php echo (int) $w; ?> <?php echo (int) $h; ?>" role="img" aria-label="<?php esc_attr_e( 'Number of recommendations over time', 'flx-seo' ); ?>">
			<?php
			for ( $g = 0; $g <= $max; $g += $step ) {
				$y = $h - $bot - ( $h - $top - $bot ) * $g / $max;
				printf( '<line x1="%d" x2="%d" y1="%.1f" y2="%.1f" class="au-grid"/><text x="%d" y="%.1f" class="au-axis" text-anchor="end">%d</text>', $left, $w, $y, $y, $left - 6, $y + 4, $g );
			}
			foreach ( $rev as $i => $e ) {
				$x = $left + $i * $slot + ( $slot - $bw ) / 2;
				$y = $h - $bot;
				foreach ( array( 'low', 'medium', 'high', 'critical' ) as $pk ) {
					$v = (int) ( $e['counts'][ $pk ] ?? 0 );
					if ( $v ) {
						$hh = ( $h - $top - $bot ) * $v / $max;
						$y -= $hh;
						printf( '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" class="au-bar-%s"><title>%s</title></rect>', $x, $y, $bw, $hh, esc_attr( $pk ), esc_html( flx_date( $e['t'], 'datetime' ) . ' · ' . $prios[ $pk ][1] . ' ' . $v ) );
					}
				}
			}
			flxseo_audit_chart_dates( $rev, $w, $h, fn( $i ) => $left + $i * $slot + $slot / 2 );
			?>
		</svg>
		<ul class="au-legend">
			<?php foreach ( $prios as $pk => $pv ) : ?>
				<li><i class="au-key au-bar-<?php echo esc_attr( $pk ); ?>"></i><?php echo esc_html( $pv[1] ); ?></li>
			<?php endforeach; ?>
		</ul>
	</figure>
	<?php
}

/** Puan grafiği: dört başlık, mobil / masaüstü seçmeli çizgiler. */
function flxseo_audit_chart_scores( array $hist ) {
	$rev  = array_reverse( $hist );
	$cats = array_intersect_key( flxseo_audit_categories(), array_flip( array( 'performance', 'accessibility', 'best-practices', 'seo' ) ) );
	$sts  = array_filter( flxseo_audit_strategies(), fn( $k ) => (bool) array_filter( $rev, fn( $e ) => ! empty( $e['scores'][ $k ] ) ), ARRAY_FILTER_USE_KEY );
	?>
	<figure class="au-chart au-chart--scores">
		<figcaption>
			<?php esc_html_e( 'PageSpeed scores', 'flx-seo' ); ?>
			<?php if ( count( $sts ) > 1 ) : ?>
				<span class="au-seg" role="group" aria-label="<?php esc_attr_e( 'Device', 'flx-seo' ); ?>">
					<?php $first = true; foreach ( $sts as $sk => $sl ) : ?>
						<button type="button" class="au-chip<?php echo $first ? ' is-on' : ''; ?>" data-strat="<?php echo esc_attr( $sk ); ?>" aria-pressed="<?php echo $first ? 'true' : 'false'; ?>"><?php echo esc_html( $sl ); ?></button>
					<?php $first = false; endforeach; ?>
				</span>
			<?php endif; ?>
		</figcaption>
		<?php if ( ! $sts ) : ?>
			<p class="au-empty"><?php esc_html_e( 'No report with a PageSpeed run yet. Enter the API key and turn on PageSpeed in the automatic audit.', 'flx-seo' ); ?></p>
		<?php endif; ?>
		<?php
		$first = true;
		foreach ( $sts as $sk => $sl ) {
			// Ölçeği veriye göre: en düşük puanın altındaki onluktan 100'e.
			$vals = array();
			foreach ( $rev as $e ) {
				foreach ( (array) ( $e['scores'][ $sk ] ?? array() ) as $v ) {
					if ( null !== $v ) {
						$vals[] = (int) $v;
					}
				}
			}
			$min  = $vals ? max( 0, min( 90, (int) floor( ( min( $vals ) - 1 ) / 10 ) * 10 ) ) : 0;
			$w    = 600;
			$h    = 180;
			$top  = 10;
			$bot  = 24;
			$left = 30;
			$n    = count( $rev );
			$sy   = fn( $v ) => $h - $bot - ( $h - $top - $bot ) * ( $v - $min ) / ( 100 - $min );
			$sx   = fn( $i ) => flxseo_audit_chart_x( $i, $n, $w - $left, 10 ) + $left;
			/* translators: %s: Mobil / Masaüstü */
			$aria = sprintf( __( '%s PageSpeed scores over time', 'flx-seo' ), $sl );
			printf( '<svg viewBox="0 0 %d %d" role="img" aria-label="%s" data-strat="%s"%s>', (int) $w, (int) $h, esc_attr( $aria ), esc_attr( $sk ), $first ? '' : ' hidden' );
			$gstep = ( 100 - $min ) > 40 ? 20 : 10;
			for ( $g = $min; $g <= 100; $g += $gstep ) {
				$y = $sy( $g );
				printf( '<line x1="%d" x2="%d" y1="%.1f" y2="%.1f" class="au-grid"/><text x="%d" y="%.1f" class="au-axis" text-anchor="end">%d</text>', $left, $w, $y, $y, $left - 6, $y + 4, $g );
			}
			foreach ( array_keys( $cats ) as $ci => $ck ) {
				// Aynı puandaki çizgiler (ör. 100) üst üste binmesin: başlık başına küçük kaydırma.
				$off  = ( $ci - 1.5 ) * 2.2;
				$pts = array();
				$dots = '';
				foreach ( $rev as $i => $e ) {
					$v = $e['scores'][ $sk ][ $ck ] ?? null;
					if ( null === $v ) {
						continue;
					}
					$pts[] = sprintf( '%.1f,%.1f', $sx( $i ), $sy( $v ) + $off );
					$dots .= sprintf( '<circle cx="%.1f" cy="%.1f" r="3.5"><title>%s</title></circle>', $sx( $i ), $sy( $v ) + $off, esc_html( flx_date( $e['t'], 'datetime' ) . ' · ' . $cats[ $ck ] . ' ' . (int) $v ) );
				}
				if ( $pts ) {
					printf( '<g class="au-l-%s"><polyline points="%s"/>%s</g>', esc_attr( $ck ), esc_attr( implode( ' ', $pts ) ), $dots ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
			}
			flxseo_audit_chart_dates( $rev, $w, $h, $sx );
			echo '</svg>';
			$first = false;
		}
		?>
		<?php if ( $sts ) : ?>
			<ul class="au-legend">
				<?php foreach ( $cats as $ck => $cl ) : ?>
					<li><i class="au-key au-l-<?php echo esc_attr( $ck ); ?>"></i><?php echo esc_html( $cl ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</figure>
	<?php
}

/** X ekseni: ilk, orta ve son tarih. */
function flxseo_audit_chart_dates( array $rev, $w, $h, callable $x ) {
	$n = count( $rev );
	$idx = array_unique( $n > 2 ? array( 0, (int) floor( ( $n - 1 ) / 2 ), $n - 1 ) : range( 0, max( 0, $n - 1 ) ) );
	foreach ( $idx as $k => $i ) {
		$anchor = 0 === $k && $n > 1 ? 'start' : ( $i === $n - 1 && $n > 1 ? 'end' : 'middle' );
		printf( '<text x="%.1f" y="%d" class="au-axis" text-anchor="%s">%s</text>', $x( $i ), $h - 6, $anchor, esc_html( flx_date( $rev[ $i ]['t'], 'short' ) ) );
	}
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
