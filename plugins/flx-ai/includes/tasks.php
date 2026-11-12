<?php
/**
 * Zamanlanmış görevler (0.11.0): kullanıcının yazdığı bir istem, belirlenen zamanda ortak sohbet motoruyla
 * (agent.php) o kullanıcının yetkileriyle çalışır. Sonuç görevin kendi sohbetine yazılır; değişiklik yapan
 * araçlar her zamanki gibi onay kartında bekler (kullanıcı sohbeti açınca onaylar ya da reddeder).
 *
 * Tetikleme üç yoldan, hangisi önce gelirse: WP-Cron (5 dk, Docker worker'da gerçek cron), sitedeki herhangi bir
 * istekten sonra kısa loopback (flxai_tasks_next zamanı geçtiyse), yönetim ekranındaki "Şimdi çalıştır".
 * Bir istek en fazla bir sohbet adımı çalıştırır (~45 sn, Hostinger ~60 sn'de keser); iş sürüyorsa
 * görev "running" kalır ve bir sonraki tetiklemede kaldığı yerden devam eder.
 *
 * Görev: id, user_id, title, prompt, schedule (JSON), status (active|paused|done), next_run, last_run,
 *        last_state (ok|error|approval|running|waiting), last_error, chat_id, runs, bot (Faz 3 için boş), model, meta (JSON: history).
 * Zamanlama: freq hourly|daily|weekdays|weekly|monthly|once, time "HH:MM" (site saat dilimi),
 *            days [1..7] (ISO, 1 = Pazartesi; weekly), day 1..31 (monthly; ay kısaysa son gün), every 1..24 (hourly), at "Y-m-d H:i" (once).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

const FLXAI_TASK_FREQS = array( 'hourly', 'daily', 'weekdays', 'weekly', 'monthly', 'once' );

/* ---------- Kayıt ---------- */

function flxai_task_decode( $row ) {
	if ( ! $row ) {
		return null;
	}
	$row = (array) $row;
	foreach ( array( 'id', 'user_id', 'next_run', 'last_run', 'chat_id', 'runs', 'created', 'updated' ) as $k ) {
		$row[ $k ] = (int) $row[ $k ];
	}
	$s               = json_decode( (string) $row['schedule'], true );
	$m               = json_decode( (string) $row['meta'], true );
	$row['schedule'] = flxai_task_schedule_clean( is_array( $s ) ? $s : array() );
	$row['meta']     = is_array( $m ) ? $m : array();
	return $row;
}

/** Görev; $user_id verilirse yalnız sahibininki. */
function flxai_task_get( $id, $user_id = 0 ) {
	global $wpdb;
	$sql = 'SELECT * FROM ' . flxai_table( 'tasks' ) . ' WHERE id = %d';
	$row = $user_id
		? $wpdb->get_row( $wpdb->prepare( $sql . ' AND user_id = %d', (int) $id, (int) $user_id ) ) // phpcs:ignore
		: $wpdb->get_row( $wpdb->prepare( $sql, (int) $id ) ); // phpcs:ignore
	return flxai_task_decode( $row );
}

/**
 * Görev listesi.
 *
 * @param array $args user_id (0 = herkes), status, limit
 */
function flxai_task_list( array $args = array() ) {
	global $wpdb;
	$where = '1=1';
	if ( ! empty( $args['user_id'] ) ) {
		$where .= $wpdb->prepare( ' AND user_id = %d', (int) $args['user_id'] );
	}
	if ( ! empty( $args['status'] ) ) {
		$where .= $wpdb->prepare( ' AND status = %s', (string) $args['status'] );
	}
	$limit = max( 1, min( 200, (int) ( $args['limit'] ?? 100 ) ) );
	$rows  = $wpdb->get_results( 'SELECT * FROM ' . flxai_table( 'tasks' ) . " WHERE {$where} ORDER BY status = 'active' DESC, next_run ASC, id DESC LIMIT {$limit}" ); // phpcs:ignore
	return array_map( 'flxai_task_decode', (array) $rows );
}

/** Zamanlamayı doğrular ve varsayılanlarla doldurur. */
function flxai_task_schedule_clean( array $s ) {
	$freq = in_array( $s['freq'] ?? '', FLXAI_TASK_FREQS, true ) ? $s['freq'] : 'daily';
	$time = preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', trim( (string) ( $s['time'] ?? '' ) ), $m ) ? sprintf( '%02d:%02d', $m[1], $m[2] ) : '09:00';
	$days = array_values( array_unique( array_filter( array_map( 'intval', (array) ( $s['days'] ?? array() ) ), fn( $d ) => $d >= 1 && $d <= 7 ) ) );
	sort( $days );
	$out = array(
		'freq' => $freq,
		'time' => $time,
	);
	if ( 'weekly' === $freq ) {
		$out['days'] = $days ? $days : array( 1 );
	} elseif ( 'monthly' === $freq ) {
		$out['day'] = max( 1, min( 31, (int) ( $s['day'] ?? 1 ) ) );
	} elseif ( 'hourly' === $freq ) {
		$out['every'] = max( 1, min( 24, (int) ( $s['every'] ?? 1 ) ) );
	} elseif ( 'once' === $freq ) {
		$at        = trim( str_replace( 'T', ' ', (string) ( $s['at'] ?? '' ) ) );
		$out['at'] = preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $at ) ? $at : '';
		unset( $out['time'] );
	}
	return $out;
}

/**
 * Bir sonraki çalışma zamanı ($from'dan sonra). Tek seferlik görev geçtiyse 0.
 *
 * @return int Unix zaman damgası
 */
function flxai_task_next_run( array $s, $from = 0 ) {
	$s    = flxai_task_schedule_clean( $s );
	$from = $from ? (int) $from : time();
	$tz   = wp_timezone();
	$now  = ( new DateTimeImmutable( '@' . $from ) )->setTimezone( $tz );
	if ( 'once' === $s['freq'] ) {
		if ( '' === $s['at'] ) {
			return 0;
		}
		$at = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $s['at'], $tz );
		return $at && $at->getTimestamp() > $from ? $at->getTimestamp() : 0;
	}
	list( $h, $i ) = array_map( 'intval', explode( ':', $s['time'] ) );
	$base          = $now->setTime( $h, $i, 0 );
	if ( 'hourly' === $s['freq'] ) {
		// Gün içinde saat çapasından itibaren her N saatte bir (ör. 09:00 + 6 saat: 03, 09, 15, 21).
		$step   = $s['every'] * HOUR_IN_SECONDS;
		$anchor = $base->getTimestamp();
		return $anchor + ( (int) floor( ( $from - $anchor ) / $step ) + 1 ) * $step;
	}
	if ( 'monthly' === $s['freq'] ) {
		for ( $k = 0; $k < 3; $k++ ) {
			$first = $now->modify( 'first day of this month' )->modify( "+{$k} month" );
			$d     = min( $s['day'], (int) $first->format( 't' ) );
			$c     = $first->setDate( (int) $first->format( 'Y' ), (int) $first->format( 'n' ), $d )->setTime( $h, $i, 0 );
			if ( $c->getTimestamp() > $from ) {
				return $c->getTimestamp();
			}
		}
		return 0;
	}
	for ( $k = 0; $k <= 8; $k++ ) {
		$c   = $base->modify( "+{$k} day" );
		$dow = (int) $c->format( 'N' );
		if ( $c->getTimestamp() <= $from ) {
			continue;
		}
		if ( 'weekdays' === $s['freq'] && $dow > 5 ) {
			continue;
		}
		if ( 'weekly' === $s['freq'] && ! in_array( $dow, $s['days'], true ) ) {
			continue;
		}
		return $c->getTimestamp();
	}
	return 0;
}

/** Zamanlamanın okunur adı ("Her gün 09:00"). */
function flxai_task_schedule_label( array $s ) {
	global $wp_locale;
	$s = flxai_task_schedule_clean( $s );
	switch ( $s['freq'] ) {
		case 'hourly':
			/* translators: %s: number of hours */
			return 1 === $s['every'] ? __( 'Every hour', 'flx-ai' ) : sprintf( __( 'Every %s hours', 'flx-ai' ), number_format_i18n( $s['every'] ) );
		case 'weekdays':
			/* translators: %s: time, e.g. 09:00 */
			return sprintf( __( 'Weekdays at %s', 'flx-ai' ), $s['time'] );
		case 'weekly':
			$names = array();
			foreach ( $s['days'] as $d ) {
				$names[] = $wp_locale ? $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( 7 === $d ? 0 : $d ) ) : (string) $d;
			}
			/* translators: 1: weekday names, 2: time */
			return sprintf( __( 'Every %1$s at %2$s', 'flx-ai' ), implode( ', ', $names ), $s['time'] );
		case 'monthly':
			/* translators: 1: day of month, 2: time */
			return sprintf( __( 'Monthly on day %1$s at %2$s', 'flx-ai' ), number_format_i18n( $s['day'] ), $s['time'] );
		case 'once':
			$at = '' !== $s['at'] ? DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $s['at'], wp_timezone() ) : null;
			/* translators: %s: date and time */
			return sprintf( __( 'Once: %s', 'flx-ai' ), $at ? flx_date( $at->getTimestamp(), 'j F Y H:i' ) : '—' );
		default:
			/* translators: %s: time, e.g. 09:00 */
			return sprintf( __( 'Every day at %s', 'flx-ai' ), $s['time'] );
	}
}

/**
 * Görev oluşturur ya da günceller. Yalnız verilen alanlar değişir.
 *
 * @param array $data title, prompt, schedule, status, model, bot
 * @return array|WP_Error Görev
 */
function flxai_task_save( array $data, $id = 0, $user_id = 0 ) {
	global $wpdb;
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	$old     = $id ? flxai_task_get( $id ) : null;
	if ( $id && ! $old ) {
		return new WP_Error( 'flxai_not_found', __( 'Task not found.', 'flx-ai' ), array( 'status' => 404 ) );
	}
	$row = array();
	if ( array_key_exists( 'title', $data ) || ! $old ) {
		$row['title'] = mb_substr( trim( sanitize_text_field( (string) ( $data['title'] ?? '' ) ) ), 0, 190 );
	}
	if ( array_key_exists( 'prompt', $data ) || ! $old ) {
		$row['prompt'] = mb_substr( trim( sanitize_textarea_field( (string) ( $data['prompt'] ?? '' ) ) ), 0, 8000 );
		if ( '' === $row['prompt'] ) {
			return new WP_Error( 'flxai_task_prompt', __( 'Write what the task should do.', 'flx-ai' ), array( 'status' => 400 ) );
		}
	}
	if ( isset( $row['title'] ) && '' === $row['title'] ) {
		$row['title'] = wp_trim_words( (string) ( $row['prompt'] ?? $old['prompt'] ?? '' ), 6, '…' );
	}
	$sched = $old ? $old['schedule'] : array();
	if ( isset( $data['schedule'] ) && is_array( $data['schedule'] ) ) {
		$sched = flxai_task_schedule_clean( $data['schedule'] + ( ( $data['schedule']['freq'] ?? $sched['freq'] ?? '' ) === ( $sched['freq'] ?? '' ) ? $sched : array() ) );
	} elseif ( ! $old ) {
		$sched = flxai_task_schedule_clean( array() );
	}
	$row['schedule'] = wp_json_encode( $sched );
	$status          = $old ? $old['status'] : 'active';
	if ( isset( $data['status'] ) && in_array( $data['status'], array( 'active', 'paused' ), true ) ) {
		$status = $data['status'];
	}
	$next = flxai_task_next_run( $sched );
	if ( 'once' === $sched['freq'] && ! $next && 'active' === $status ) {
		return new WP_Error( 'flxai_task_at', __( 'Pick a future date and time for a one-time task.', 'flx-ai' ), array( 'status' => 400 ) );
	}
	$row['status']   = $status;
	$row['next_run'] = 'active' === $status ? $next : 0;
	if ( array_key_exists( 'model', $data ) ) {
		$row['model'] = mb_substr( sanitize_text_field( (string) $data['model'] ), 0, 120 );
	}
	if ( array_key_exists( 'bot', $data ) ) {
		$row['bot'] = sanitize_key( (string) $data['bot'] );
	}
	$meta = $old ? $old['meta'] : array();
	if ( array_key_exists( 'skill', $data ) ) {
		$sk = flxai_skill_slug( (string) $data['skill'] );
		if ( '' !== $sk && ! flxai_skill_get( $sk ) ) {
			/* translators: %s: skill name */
			return new WP_Error( 'flxai_task_skill', sprintf( __( 'Skill not found: %s', 'flx-ai' ), $sk ), array( 'status' => 400 ) );
		}
		$meta['skill'] = $sk;
	}
	if ( isset( $data['notify'] ) && is_array( $data['notify'] ) ) {
		$meta['notify'] = flxai_task_notify_clean( $data['notify'] + (array) ( $meta['notify'] ?? array() ) );
	}
	if ( isset( $data['ws'] ) && is_array( $data['ws'] ) && function_exists( 'flxai_ws_clean_cfg' ) ) {
		$cur        = (array) ( $meta['ws'] ?? array() );
		$cfg        = flxai_ws_clean_cfg( $data['ws'] + $cur );
		$meta['ws'] = array_merge( $cur, $cfg );
	}
	$row['meta'] = wp_json_encode( $meta ? $meta : new stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	$row['updated'] = time();
	if ( $old ) {
		$wpdb->update( flxai_table( 'tasks' ), $row, array( 'id' => (int) $old['id'] ) ); // phpcs:ignore
		$id = (int) $old['id'];
	} else {
		$max = (int) apply_filters( 'flxai_tasks_max_per_user', 50 );
		if ( count( flxai_task_list( array( 'user_id' => $user_id, 'limit' => 200 ) ) ) >= $max ) {
			/* translators: %s: maximum number of tasks */
			return new WP_Error( 'flxai_task_max', sprintf( __( 'You can have at most %s scheduled tasks.', 'flx-ai' ), number_format_i18n( $max ) ), array( 'status' => 400 ) );
		}
		$row += array(
			'user_id'    => $user_id,
			'created'    => time(),
			'last_state' => '',
			'last_error' => '',
			'model'      => '',
			'bot'        => '',
		);
		$wpdb->insert( flxai_table( 'tasks' ), $row ); // phpcs:ignore
		$id = (int) $wpdb->insert_id;
	}
	flxai_tasks_refresh_next();
	return flxai_task_get( $id );
}

function flxai_task_delete( $id ) {
	global $wpdb;
	$ok = (bool) $wpdb->delete( flxai_table( 'tasks' ), array( 'id' => (int) $id ) ); // phpcs:ignore
	flxai_tasks_refresh_next();
	return $ok;
}

/** Çalışma alanlarını yazar (görev kaydı güncellemesi). */
function flxai_task_patch( $id, array $row ) {
	global $wpdb;
	if ( isset( $row['meta'] ) && is_array( $row['meta'] ) ) {
		$row['meta'] = wp_json_encode( $row['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}
	if ( isset( $row['last_error'] ) ) {
		$row['last_error'] = mb_substr( (string) $row['last_error'], 0, 250 );
	}
	$wpdb->update( flxai_table( 'tasks' ), $row, array( 'id' => (int) $id ) ); // phpcs:ignore
}

/** Sıradaki en erken iş zamanı (autoload): her istekte ucuz "zamanı geldi mi" kontrolü için. */
function flxai_tasks_refresh_next() {
	global $wpdb;
	$t   = flxai_table( 'tasks' );
	$run = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE status = 'active' AND last_state = 'running'" ); // phpcs:ignore
	$min = (int) $wpdb->get_var( "SELECT MIN(next_run) FROM {$t} WHERE status = 'active' AND next_run > 0" ); // phpcs:ignore
	update_option( 'flxai_tasks_next', $run ? time() : $min, true );
}

/** Kullanıcıya görünen görev (REST, araçlar, ekran). */
function flxai_task_public( array $t ) {
	$owner = get_userdata( $t['user_id'] );
	return array(
		'id'             => $t['id'],
		'title'          => $t['title'],
		'prompt'         => $t['prompt'],
		'schedule'       => $t['schedule'],
		'schedule_label' => flxai_task_schedule_label( $t['schedule'] ),
		'status'         => $t['status'],
		'next_run'       => $t['next_run'] ? wp_date( 'c', $t['next_run'] ) : null,
		'next_run_text'  => $t['next_run'] ? flx_date( $t['next_run'], 'j F Y H:i' ) : '',
		'last_run'       => $t['last_run'] ? wp_date( 'c', $t['last_run'] ) : null,
		'last_state'     => $t['last_state'],
		'last_error'     => $t['last_error'],
		'last_summary'   => (string) ( $t['meta']['summary'] ?? '' ),
		'runs'           => $t['runs'],
		'chat_id'        => $t['chat_id'],
		'owner'          => $owner ? $owner->display_name : '',
		'skill'          => (string) ( $t['meta']['skill'] ?? '' ),
		'notify'         => flxai_task_notify_cfg( $t ),
		'workspace'      => function_exists( 'flxai_ws_clean_cfg' ) ? array(
			'dir'     => (string) ( $t['meta']['ws']['dir'] ?? '' ),
			'access'  => (string) ( $t['meta']['ws']['access'] ?? '' ) ?: 'workspace',
			'folders' => (array) ( $t['meta']['ws']['folders'] ?? array() ),
		) : null,
	);
}

/* ---------- Çalıştırma ---------- */

function flxai_task_lock( $id ) {
	$k = 'flxai_task_lock_' . (int) $id;
	if ( get_transient( $k ) ) {
		return false;
	}
	set_transient( $k, 1, 90 );
	return true;
}

function flxai_task_unlock( $id ) {
	delete_transient( 'flxai_task_lock_' . (int) $id );
}

/** Sohbetteki son asistan cevabının kısa özeti. */
function flxai_task_summary( array $chat ) {
	for ( $i = count( $chat['messages'] ) - 1; $i >= 0; $i-- ) {
		$m = $chat['messages'][ $i ];
		if ( 'user' === ( $m['role'] ?? '' ) ) {
			break;
		}
		if ( 'assistant' === ( $m['role'] ?? '' ) && is_string( $m['content'] ?? null ) && '' !== trim( $m['content'] ) ) {
			$txt = preg_replace( '/```.*?```/s', '', $m['content'] );
			$txt = trim( preg_replace( '/[#*_`>|]+/', '', (string) $txt ) );
			return mb_substr( preg_replace( '/\s+/u', ' ', $txt ), 0, 280 );
		}
	}
	return '';
}

/**
 * Bir görevi çalıştırır ya da yarım kalan çalışmasını sürdürür (bir sohbet adımı).
 *
 * @param bool $force Zamanı gelmemiş olsa da (Şimdi çalıştır).
 * @return string ok|approval|running|error|waiting|busy|skip
 */
function flxai_task_run( $id, $force = false ) {
	$task = flxai_task_get( $id );
	if ( ! $task || ( 'active' !== $task['status'] && ! $force ) ) {
		return 'skip';
	}
	$resume = 'running' === $task['last_state'];
	if ( ! $resume && ! $force && ( ! $task['next_run'] || $task['next_run'] > time() ) ) {
		return 'skip';
	}
	if ( ! flxai_task_lock( $task['id'] ) ) {
		return 'busy';
	}
	$prev_user = get_current_user_id();
	$state     = 'error';
	$meta      = $task['meta'];
	$patch     = array();
	$chat      = null;
	try {
		wp_set_current_user( $task['user_id'] );
		$started = microtime( true );
		if ( ! $resume ) {
			// Zaman hemen ilerler: bu çalışma yarıda kalsa da aynı saat ikinci kez başlamaz.
			$next             = 'once' === $task['schedule']['freq'] ? 0 : flxai_task_next_run( $task['schedule'], max( time(), $task['next_run'] ) );
			$patch['next_run'] = $next;
			if ( ! $next && 'once' === $task['schedule']['freq'] ) {
				$patch['status'] = 'done';
			}
			$patch['last_run'] = time();
		}
		if ( ! flxai_can_chat( $task['user_id'] ) || ! flxai_source_enabled( 'flx-ai' ) ) {
			throw new Exception( __( 'The task owner can no longer use the AI chat, or Flux AI chat is turned off.', 'flx-ai' ) );
		}
		$chat = $task['chat_id'] ? flxai_chat_get( $task['chat_id'], $task['user_id'] ) : null;
		if ( ! $chat ) {
			$chat          = flxai_chat_create( $task['user_id'] );
			$chat['title'] = $task['title'];
			if ( '' !== $task['model'] ) {
				$chat['model'] = $task['model'];
			}
			flxai_chat_save( $chat );
			$patch['chat_id'] = (int) $chat['id'];
		}
		if ( ! $resume ) {
			if ( 'approval' === $chat['status'] ) {
				// Önceki çalışmanın işlemi hâlâ onay bekliyor: üzerine yeni tur açılmaz, taslak kaybolmaz.
				$state = 'waiting';
				return $state;
			}
			if ( 'running' === $chat['status'] ) {
				$chat['status'] = 'idle';
			}
			$sent = flxai_agent_send( $chat, $task['prompt'] );
			if ( is_wp_error( $sent ) ) {
				throw new Exception( $sent->get_error_message() );
			}
			$chat = $sent['chat'];
			$last = count( $chat['messages'] ) - 1;
			$chat['messages'][ $last ]['_task'] = array(
				'id'    => (int) $task['id'],
				'title' => (string) $task['title'],
				'skill' => (string) ( $meta['skill'] ?? '' ),
			);
			unset( $meta['notify_msg'] );
			flxai_chat_save( $chat );
			$meta['run_start'] = time();
			$meta['run_from']  = $last; // Bu çalışmanın sohbetteki ilk mesajı (Geçmiş ekranı).
		} elseif ( 'running' !== $chat['status'] ) {
			$state = 'approval' === $chat['status'] ? 'approval' : 'ok';
			return $state;
		}
		$r     = flxai_agent_step( $chat );
		$chat  = $r['chat'];
		$state = array(
			'continue' => 'running',
			'approval' => 'approval',
			'done'     => 'ok',
			'busy'     => 'running',
		)[ $r['state'] ] ?? 'error';
		if ( 'error' === $state ) {
			$err = '';
			foreach ( array_reverse( $chat['messages'] ) as $m ) {
				if ( ! empty( $m['_error'] ) && 'assistant' === ( $m['role'] ?? '' ) ) {
					$err = (string) $m['content'];
					break;
				}
			}
			$patch['last_error'] = $err;
		} else {
			$patch['last_error'] = '';
		}
	} catch ( Throwable $e ) {
		$state               = 'error';
		$patch['last_error'] = $e->getMessage();
	} finally {
		if ( 'ok' === $state && $chat && function_exists( 'flxai_change_open_pr' ) && flxai_change_open_pr( (int) $chat['id'] ) ) {
			$state = 'approval'; // Site dosyaları için değişiklik isteği onay bekliyor.
		}
		if ( 'running' !== $state ) {
			// Çalışma bitti (ya da atlandı): geçmişe yazılır. from/to: sohbetteki mesaj aralığı.
			$from = isset( $meta['run_from'] ) && 'waiting' !== $state ? (int) $meta['run_from'] : -1;
			if ( $chat && $from >= 0 ) {
				$meta['summary'] = flxai_task_summary( $chat );
			}
			$hist = (array) ( $meta['history'] ?? array() );
			array_unshift(
				$hist,
				array(
					'ts'    => (int) ( 'waiting' === $state ? time() : ( $meta['run_start'] ?? time() ) ),
					'state' => $state,
					'ms'    => $chat && $from >= 0 ? (int) ( $chat['state']['turn']['total_ms'] ?? round( ( microtime( true ) - (float) ( $chat['state']['turn']['started'] ?? microtime( true ) ) ) * 1000 ) ) : 0,
					'cost'  => $chat && $from >= 0 ? (float) ( $chat['state']['turn']['cost'] ?? 0 ) : 0,
					'chat'  => $chat ? (int) $chat['id'] : 0,
					'from'  => $chat ? $from : -1,
					'to'    => $chat && $from >= 0 ? count( $chat['messages'] ) : -1,
					'error' => (string) ( $patch['last_error'] ?? '' ),
				)
			);
			$meta['history'] = array_slice( $hist, 0, (int) apply_filters( 'flxai_task_history_max', 50 ) );
			if ( 'waiting' !== $state ) {
				$patch['runs'] = $task['runs'] + 1;
			}
			unset( $meta['run_start'], $meta['run_from'] );
		}
		$patch['last_state'] = $state;
		$patch['meta']       = $meta;
		flxai_task_patch( $task['id'], $patch );
		wp_set_current_user( $prev_user );
		flxai_task_unlock( $task['id'] );
		flxai_tasks_refresh_next();
		do_action( 'flxai_task_ran', (int) $task['id'], $state );
	}
	if ( 'running' === $state ) {
		flxai_tasks_spawn( true ); // Kalan adım ayrı istekte (bir istek ~45 sn).
	}
	return $state;
}

/** Zamanı gelen görevleri sırayla çalıştırır; süre biterse kalanlar sonraki tetiklemeye kalır. */
function flxai_tasks_tick() {
	global $wpdb;
	update_option( 'flxai_tasks_tick', time(), false );
	if ( ! flxai_setting( 'tasks_enabled', 1 ) ) {
		return;
	}
	$t0  = microtime( true );
	$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxai_table( 'tasks' ) . " WHERE status = 'active' AND ( last_state = 'running' OR ( next_run > 0 AND next_run <= %d ) ) ORDER BY next_run ASC LIMIT 20", time() ) ); // phpcs:ignore
	$did = false;
	foreach ( (array) $ids as $id ) {
		// Bir sohbet adımı ~45 sn sürebilir: ikinci görev yalnız ilk görev hızlı bittiyse aynı istekte başlar.
		if ( $did && ( microtime( true ) - $t0 ) > 10 ) {
			flxai_tasks_spawn( true );
			break;
		}
		$s   = flxai_task_run( (int) $id );
		$did = $did || in_array( $s, array( 'ok', 'approval', 'running', 'error' ), true );
	}
}

/**
 * Tetikleme isteği (loopback): yanıt beklenmez. $now: kısa aralık kilidine bakmadan.
 * İstek admin-ajax'a gelir, görev sahibinin yetkileri görevin içinde kurulur; istek gizli anahtarla doğrulanır.
 */
function flxai_tasks_spawn( $now = false ) {
	if ( ! $now && get_transient( 'flxai_tasks_spawn' ) ) {
		return;
	}
	set_transient( 'flxai_tasks_spawn', 1, 60 );
	wp_remote_post(
		admin_url( 'admin-ajax.php' ),
		array(
			'timeout'   => 0.01,
			'blocking'  => false,
			'sslverify' => (bool) apply_filters( 'https_local_ssl_verify', false ),
			'body'      => array(
				'action' => 'flxai_tasks_run',
				'token'  => flxai_tasks_token(),
			),
		)
	);
}

function flxai_tasks_token() {
	$t = (string) get_option( 'flxai_tasks_token', '' );
	if ( strlen( $t ) < 32 ) {
		$t = wp_generate_password( 40, false, false );
		update_option( 'flxai_tasks_token', $t, false );
	}
	return $t;
}

function flxai_tasks_ajax() {
	$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( '' === $token || ! hash_equals( flxai_tasks_token(), $token ) ) {
		wp_die( '', '', array( 'response' => 403 ) );
	}
	ignore_user_abort( true );
	flxai_tasks_tick();
	wp_die( '', '', array( 'response' => 200 ) );
}
add_action( 'wp_ajax_nopriv_flxai_tasks_run', 'flxai_tasks_ajax' );
add_action( 'wp_ajax_flxai_tasks_run', 'flxai_tasks_ajax' );

/* WP-Cron: 5 dakikada bir (Docker worker'da gerçek cron, paylaşımlı sunucuda istek gelince). */
add_filter(
	'cron_schedules',
	function ( $s ) {
		$s['flxai_5min'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => 'Flux AI (5 min)',
		);
		return $s;
	}
);
add_action( 'flxai_tasks_tick', 'flxai_tasks_tick' );
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'flxai_tasks_tick' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'flxai_5min', 'flxai_tasks_tick' );
		}
	},
	20
);

/* Cron gelmese de: zamanı geçmiş görev varsa herhangi bir sayfa isteği bittikten sonra loopback ile tetiklenir. */
add_action(
	'shutdown',
	function () {
		$next = (int) get_option( 'flxai_tasks_next', 0 );
		if ( ! $next || $next > time() || wp_doing_cron() || ( wp_doing_ajax() && 'flxai_tasks_run' === ( $_REQUEST['action'] ?? '' ) ) ) { // phpcs:ignore
			return;
		}
		if ( ! flxai_setting( 'tasks_enabled', 1 ) ) {
			return;
		}
		flxai_tasks_spawn();
	}
);

/* ---------- Sohbet ve MCP araçları ---------- */

/** Görev aracı için kullanıcının görevi (yönetici her görevi yönetir). */
function flxai_task_for_tool( $id ) {
	$uid  = get_current_user_id();
	$task = current_user_can( 'manage_options' ) ? flxai_task_get( $id ) : flxai_task_get( $id, $uid );
	if ( ! $task ) {
		throw new Exception( __( 'Task not found.', 'flx-ai' ) );
	}
	return $task;
}

function flxai_task_tool_data( array $a ) {
	$data = array_intersect_key( $a, array_flip( array( 'title', 'prompt', 'status', 'model', 'skill' ) ) );
	$n    = array();
	if ( isset( $a['notify'] ) ) {
		$n['mode'] = (string) $a['notify'];
	}
	if ( isset( $a['notify_channels'] ) ) {
		$ch           = (array) $a['notify_channels'];
		$n['email']    = in_array( 'email', $ch, true );
		$n['panel']    = in_array( 'panel', $ch, true );
		$n['whatsapp'] = in_array( 'whatsapp', $ch, true ) ? (string) ( $a['notify_whatsapp'] ?? '' ) : '';
	} elseif ( isset( $a['notify_whatsapp'] ) ) {
		$n['whatsapp'] = (string) $a['notify_whatsapp'];
	}
	if ( $n ) {
		$data['notify'] = $n;
	}
	if ( isset( $a['access'] ) || isset( $a['folders'] ) ) {
		$data['ws'] = array_intersect_key( $a, array_flip( array( 'access', 'folders' ) ) );
	}
	$s    = array_intersect_key( $a, array_flip( array( 'freq', 'time', 'days', 'day', 'every', 'at' ) ) );
	if ( $s ) {
		$data['schedule'] = $s;
	}
	return $data;
}

function flxai_task_tool_schema( $with_id ) {
	$p = array(
		'title'  => array(
			'type'        => 'string',
			'description' => 'Kısa görev adı (ör. "Günlük YZ brifingi").',
		),
		'prompt' => array(
			'type'        => 'string',
			'description' => 'Görev her çalıştığında asistana verilecek talimatın tamamı. Kendi başına anlaşılır yaz: ne incelenecek, hangi araçlar kullanılacak, rapor nasıl olacak.',
		),
		'freq'   => array(
			'type'        => 'string',
			'enum'        => FLXAI_TASK_FREQS,
			'description' => 'hourly: her N saatte, daily: her gün, weekdays: hafta içi, weekly: seçili günler, monthly: ayın günü, once: tek sefer.',
		),
		'time'   => array(
			'type'        => 'string',
			'description' => 'Saat, site saat diliminde HH:MM (varsayılan 09:00). hourly için başlangıç çapası.',
		),
		'days'   => array(
			'type'        => 'array',
			'items'       => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 7 ),
			'description' => 'weekly için günler, 1 = Pazartesi … 7 = Pazar.',
		),
		'day'    => array(
			'type'        => 'integer',
			'description' => 'monthly için ayın günü (1-31; kısa ayda son gün).',
		),
		'every'  => array(
			'type'        => 'integer',
			'description' => 'hourly için kaç saatte bir (1-24).',
		),
		'at'     => array(
			'type'        => 'string',
			'description' => 'once için tarih ve saat, site saat diliminde "YYYY-MM-DD HH:MM".',
		),
		'status' => array(
			'type' => 'string',
			'enum' => array( 'active', 'paused' ),
		),
		'skill'  => array(
			'type'        => 'string',
			'description' => 'Görevin kullanacağı skill adı (flxai_skill_list); talimat her çalışmada skill\'in güncel haliyle birleşir. Boş = skill yok.',
		),
		'notify' => array(
			'type'        => 'string',
			'enum'        => array( 'none', 'important', 'always' ),
			'description' => 'Görev bitince bildirim: none, important (yalnız önemli bir şey, onay bekleyen işlem ya da hata varsa), always (her çalışmada).',
		),
		'notify_channels' => array(
			'type'        => 'array',
			'items'       => array( 'type' => 'string', 'enum' => array( 'email', 'whatsapp', 'panel' ) ),
			'description' => 'Bildirim kanalları: email (görev sahibinin e-postası), whatsapp (notify_whatsapp numarası), panel (yönetim panelinde rozet).',
		),
		'notify_whatsapp' => array(
			'type'        => 'string',
			'description' => 'WhatsApp bildirimi için numara, ülke koduyla (ör. 905321234567).',
		),
		'access'          => array(
			'type'        => 'string',
			'enum'        => array( 'workspace', 'site' ),
			'description' => 'Dosya erişimi: workspace (varsayılan; yalnız görevin kendi klasörü + folders) ya da site (tüm site okunur). Klasör dışındaki her değişiklik kullanıcı onayına (PR) gider.',
		),
		'folders'         => array(
			'type'        => 'array',
			'items'       => array( 'type' => 'string' ),
			'description' => 'Görevin okuyabileceği ek klasörler (site köküne göre, ör. wp-content/uploads/raporlar). Değişiklikler yine onaya gider.',
		),
	);
	$schema = array(
		'type'       => 'object',
		'properties' => $p,
	);
	if ( $with_id ) {
		$schema['properties'] = array( 'id' => array( 'type' => 'integer', 'description' => 'Görev kimliği (flxai_task_list).' ) ) + $p;
		$schema['required']   = array( 'id' );
	} else {
		$schema['required'] = array( 'title', 'prompt', 'freq' );
	}
	return $schema;
}

/** Görev araçları (MCP + sohbet). Görev, onu oluşturan kullanıcının yetkileriyle çalışır. */
add_filter(
	'flx_mcp_tools',
	function ( $tools ) {
		$tools = is_array( $tools ) ? $tools : array();
		if ( ! flxai_setting( 'tasks_enabled', 1 ) ) {
			return $tools;
		}
		$tools['flxai_task_list']   = array(
			'title'       => __( 'List scheduled tasks', 'flx-ai' ),
			'description' => 'Kullanıcının zamanlanmış AI görevleri: ad, talimat, zamanlama, sonraki/son çalışma, son durum ve kısa sonuç özeti. Yönetici tüm görevleri görür.',
			'readonly'    => true,
			'cap'         => 'read',
			'log'         => false,
			'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
			'run'         => function () {
				$args = current_user_can( 'manage_options' ) ? array() : array( 'user_id' => get_current_user_id() );
				return array(
					'timezone' => wp_timezone_string(),
					'now'      => wp_date( 'Y-m-d H:i' ),
					'tasks'    => array_map( 'flxai_task_public', flxai_task_list( $args ) ),
				);
			},
		);
		$tools['flxai_task_create'] = array(
			'title'       => __( 'Create a scheduled task', 'flx-ai' ),
			'description' => 'Belirli zamanlarda kendiliğinden çalışan bir AI görevi oluşturur (ör. "her sabah 9\'da günlük brifing", "her pazartesi bağışçı durumunu incele", "6 saatte bir site performansına bak"). Görev senin araçlarınla ve kullanıcının yetkileriyle çalışır; sonucu görevin sohbetine yazılır, değişiklik yapan işlemler kullanıcı onayı bekler. Önce kullanıcıya zamanlamayı ve talimatı kısaca söyle.',
			'readonly'    => false,
			'cap'         => 'read',
			'schema'      => flxai_task_tool_schema( false ),
			'run'         => function ( $a ) {
				if ( ! flxai_can_chat() ) {
					throw new Exception( __( 'You cannot use the AI chat, so you cannot create tasks.', 'flx-ai' ) );
				}
				$t = flxai_task_save( flxai_task_tool_data( (array) $a ) );
				if ( is_wp_error( $t ) ) {
					throw new Exception( $t->get_error_message() );
				}
				return array(
					'created' => true,
					'task'    => flxai_task_public( $t ),
					'manage'  => admin_url( 'admin.php?page=flx-ai-tasks' ),
				);
			},
		);
		$tools['flxai_task_update'] = array(
			'title'       => __( 'Update a scheduled task', 'flx-ai' ),
			'description' => 'Zamanlanmış görevin adını, talimatını, zamanlamasını değiştirir ya da duraklatır/sürdürür (status). Yalnız verilen alanlar değişir. Talimatı değiştirirken eski talimatı flxai_task_list ile oku ve tam yeni metni gönder.',
			'readonly'    => false,
			'cap'         => 'read',
			'schema'      => flxai_task_tool_schema( true ),
			'run'         => function ( $a ) {
				$task = flxai_task_for_tool( (int) ( $a['id'] ?? 0 ) );
				$t    = flxai_task_save( flxai_task_tool_data( (array) $a ), $task['id'], $task['user_id'] );
				if ( is_wp_error( $t ) ) {
					throw new Exception( $t->get_error_message() );
				}
				return array(
					'updated' => true,
					'task'    => flxai_task_public( $t ),
				);
			},
		);
		$tools['flxai_task_delete'] = array(
			'title'       => __( 'Delete a scheduled task', 'flx-ai' ),
			'description' => 'Zamanlanmış görevi kalıcı olarak siler. Görevin geçmiş sohbeti kalır.',
			'readonly'    => false,
			'cap'         => 'read',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'id' => array( 'type' => 'integer' ) ),
				'required'   => array( 'id' ),
			),
			'run'         => function ( $a ) {
				$task = flxai_task_for_tool( (int) ( $a['id'] ?? 0 ) );
				return array( 'deleted' => flxai_task_delete( $task['id'] ) );
			},
		);
		$tools['flxai_task_run']    = array(
			'title'       => __( 'Run a scheduled task now', 'flx-ai' ),
			'description' => 'Görevi zamanını beklemeden birkaç saniye içinde arka planda çalıştırır. Sonuç görevin sohbetine yazılır.',
			'readonly'    => false,
			'cap'         => 'read',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'id' => array( 'type' => 'integer' ) ),
				'required'   => array( 'id' ),
			),
			'run'         => function ( $a ) {
				$task = flxai_task_for_tool( (int) ( $a['id'] ?? 0 ) );
				flxai_task_queue_now( $task['id'] );
				return array(
					'queued'  => true,
					'chat_id' => $task['chat_id'],
				);
			},
		);
		return $tools;
	}
);

/** Görevi hemen sıraya alır (arka planda, istek beklemez). */
function flxai_task_queue_now( $id ) {
	$task = flxai_task_get( $id );
	if ( ! $task ) {
		return false;
	}
	$row = array( 'next_run' => time() );
	if ( 'active' !== $task['status'] ) {
		$row['status'] = 'active';
		// Duraklatılmış görev bir kez çalışır, sonra yine durur.
		$task['meta']['pause_after'] = 1;
		$row['meta']                 = $task['meta'];
	}
	flxai_task_patch( $id, $row );
	flxai_tasks_refresh_next();
	flxai_tasks_spawn( true );
	return true;
}

/* "Bir kez çalıştır" ile açılan duraklatılmış görev çalıştıktan sonra yine durur. */
add_action(
	'flxai_task_ran',
	function ( $id, $state ) {
		if ( 'running' === $state ) {
			return;
		}
		$task = flxai_task_get( $id );
		if ( $task && ! empty( $task['meta']['pause_after'] ) ) {
			unset( $task['meta']['pause_after'] );
			flxai_task_patch(
				$id,
				array(
					'status'   => 'paused',
					'next_run' => 0,
					'meta'     => $task['meta'],
				)
			);
			flxai_tasks_refresh_next();
		}
	},
	10,
	2
);

/* ---------- Sohbet bağlamı ---------- */

/* Görevden gelen mesaj modele "zamanlanmış görev, kullanıcı başında değil" notuyla gider. */
add_filter(
	'flxai_agent_user_text',
	function ( $text, $m ) {
		if ( empty( $m['_task'] ) ) {
			return $text;
		}
		return '[Zamanlanmış görev "' . mb_substr( (string) ( $m['_task']['title'] ?? '' ), 0, 120 ) . '" kendiliğinden çalıştı; kullanıcı şu an başında değil, soru sorma. '
			. 'Gereken bilgiyi araçlarla topla ve sonucu kısa, başlıklı bir rapor olarak yaz. Değişiklik yapan işlemler kullanıcının onayına düşer: '
			. 'gerekiyorsa taslak olarak hazırla ve ne onaylanacağını raporda belirt.'
			. flxai_task_notify_hint( (int) ( $m['_task']['id'] ?? 0 ) ) . "]\n" . $text;
	},
	10,
	2
);

/* ---------- Sistem kontrolü ---------- */

add_filter(
	'flx_system_checks',
	function ( $checks ) {
		global $wpdb;
		$checks = is_array( $checks ) ? $checks : array();
		if ( ! flxai_setting( 'tasks_enabled', 1 ) ) {
			return $checks;
		}
		$n = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . flxai_table( 'tasks' ) . " WHERE status = 'active'" ); // phpcs:ignore
		if ( ! $n ) {
			return $checks;
		}
		$late = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxai_table( 'tasks' ) . " WHERE status = 'active' AND next_run > 0 AND next_run < %d", time() - 15 * MINUTE_IN_SECONDS ) ); // phpcs:ignore
		$tick = (int) get_option( 'flxai_tasks_tick', 0 );
		$checks[] = array(
			'id'           => 'flxai.tasks',
			'source'       => 'Flux AI & MCP',
			'group'        => 'Cron',
			'label'        => __( 'Scheduled AI tasks', 'flx-ai' ),
			'status'       => $late ? 'warning' : 'ok',
			'message'      => $late
				/* translators: 1: number of late tasks, 2: last check time */
				? sprintf( __( '%1$s tasks are more than 15 minutes late. Last check: %2$s. Make sure WP-Cron or a server cron calls wp-cron.php.', 'flx-ai' ), number_format_i18n( $late ), $tick ? flx_time_ago( $tick ) : '—' )
				/* translators: 1: number of active tasks, 2: last check time */
				: sprintf( __( '%1$s active tasks. Last check: %2$s.', 'flx-ai' ), number_format_i18n( $n ), $tick ? flx_time_ago( $tick ) : '—' ),
			'action_url'   => admin_url( 'admin.php?page=flx-ai-tasks' ),
			'action_label' => __( 'Scheduled tasks', 'flx-ai' ),
		);
		return $checks;
	}
);
