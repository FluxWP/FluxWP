<?php
/**
 * Flux AI & MCP › Zamanlanmış görevler: liste, ekle/düzenle, şimdi çalıştır, duraklat, sil.
 * Görevler sohbetten de (flxai_task_* araçları) oluşturulur ve düzenlenir.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/* Form işlemleri ekran çizilmeden önce; sonra aynı ekrana dönülür. */
add_action(
	'admin_init',
	function () {
		if ( empty( $_POST['flxai_tasks_nonce'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'flxai_tasks', 'flxai_tasks_nonce' );
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidationSanitization
		$do   = sanitize_key( $post['do'] ?? '' );
		$id   = (int) ( $post['id'] ?? 0 );
		$back = admin_url( 'admin.php?page=flx-ai-tasks' );
		$msg  = 'saved';
		if ( 'toggle_system' === $do ) {
			flxai_update_settings( array( 'tasks_enabled' => empty( $post['enabled'] ) ? 0 : 1 ) );
		} elseif ( 'save' === $do ) {
			$sched = array(
				'freq'  => sanitize_key( $post['freq'] ?? 'daily' ),
				'time'  => sanitize_text_field( $post['time'] ?? '09:00' ),
				'days'  => array_map( 'intval', (array) ( $post['days'] ?? array() ) ),
				'day'   => (int) ( $post['day'] ?? 1 ),
				'every' => (int) ( $post['every'] ?? 1 ),
				'at'    => sanitize_text_field( $post['at'] ?? '' ),
			);
			$t     = flxai_task_save(
				array(
					'title'    => (string) ( $post['title'] ?? '' ),
					'prompt'   => (string) ( $post['prompt'] ?? '' ),
					'status'   => empty( $post['active'] ) ? 'paused' : 'active',
					'schedule' => $sched,
				),
				$id
			);
			if ( is_wp_error( $t ) ) {
				set_transient( 'flxai_tasks_err_' . get_current_user_id(), $t->get_error_message(), 60 );
				wp_safe_redirect( add_query_arg( array( 'edit' => $id ? $id : 'new', 'err' => 1 ), $back ) );
				exit;
			}
		} elseif ( $id && flxai_task_get( $id ) ) {
			if ( 'run' === $do ) {
				flxai_task_queue_now( $id );
				$msg = 'queued';
			} elseif ( 'pause' === $do || 'resume' === $do ) {
				$t = flxai_task_save( array( 'status' => 'pause' === $do ? 'paused' : 'active' ), $id );
				if ( is_wp_error( $t ) ) {
					set_transient( 'flxai_tasks_err_' . get_current_user_id(), $t->get_error_message(), 60 );
					$msg = 'error';
				}
			} elseif ( 'delete' === $do ) {
				flxai_task_delete( $id );
				$msg = 'deleted';
			}
		}
		wp_safe_redirect( add_query_arg( 'msg', $msg, $back ) );
		exit;
	}
);

/** Durum rozeti. */
function flxai_task_state_pill( array $t ) {
	if ( 'paused' === $t['status'] ) {
		return '<span class="flxai-pill">' . esc_html__( 'Paused', 'flx-ai' ) . '</span>';
	}
	if ( 'done' === $t['status'] ) {
		return '<span class="flxai-pill">' . esc_html__( 'Done', 'flx-ai' ) . '</span>';
	}
	$map = array(
		'ok'       => array( 'is-ok', __( 'Completed', 'flx-ai' ) ),
		'running'  => array( 'is-run', __( 'Running', 'flx-ai' ) ),
		'approval' => array( 'is-warn', __( 'Awaiting approval', 'flx-ai' ) ),
		'waiting'  => array( 'is-warn', __( 'Skipped: approval pending', 'flx-ai' ) ),
		'error'    => array( 'is-err', __( 'Error', 'flx-ai' ) ),
	);
	if ( ! isset( $map[ $t['last_state'] ] ) ) {
		return '<span class="flxai-pill is-ok">' . esc_html__( 'Active', 'flx-ai' ) . '</span>';
	}
	return '<span class="flxai-pill ' . esc_attr( $map[ $t['last_state'] ][0] ) . '">' . esc_html( $map[ $t['last_state'] ][1] ) . '</span>';
}

/** Görevin sohbetini ortak sohbet panelinde açan adres. */
function flxai_task_chat_url( array $t ) {
	return $t['chat_id'] ? add_query_arg( 'flxai_chat', (int) $t['chat_id'], admin_url( 'admin.php?page=flx-ai-tasks' ) ) : '';
}

function flxai_page_tasks() {
	$on   = (bool) flxai_setting( 'tasks_enabled', 1 );
	$edit = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$hid = isset( $_GET['history'] ) ? (int) $_GET['history'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
	if ( $hid ) {
		$ht = flxai_task_get( $hid );
		if ( $ht ) {
			flxai_task_history_page( $ht );
		} else {
			flxai_page_head( __( 'Scheduled tasks', 'flx-ai' ) );
			echo '<p>' . esc_html__( 'Task not found.', 'flx-ai' ) . '</p>';
		}
		echo '</div>';
		return;
	}
	flxai_page_head( __( 'Scheduled tasks', 'flx-ai' ), __( 'Instructions the assistant runs on its own at set times: a daily briefing, a weekly donor review, a site performance check. Each task runs with its owner\'s permissions and writes its report to its own chat; changes wait for approval.', 'flx-ai' ) );

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$err = get_transient( 'flxai_tasks_err_' . get_current_user_id() );
	if ( $err && ( isset( $_GET['err'] ) || 'error' === ( $_GET['msg'] ?? '' ) ) ) {
		delete_transient( 'flxai_tasks_err_' . get_current_user_id() );
		echo '<div class="notice notice-error"><p>' . esc_html( $err ) . '</p></div>';
	} elseif ( isset( $_GET['msg'] ) ) {
		$m = array(
			'saved'   => __( 'Saved.', 'flx-ai' ),
			'queued'  => __( 'The task will start in a few seconds. Its report appears in the task chat.', 'flx-ai' ),
			'deleted' => __( 'Task deleted. Its chat was kept.', 'flx-ai' ),
		)[ sanitize_key( $_GET['msg'] ) ] ?? '';
		if ( $m ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $m ) . '</p></div>';
		}
	}
	// phpcs:enable

	if ( '' !== $edit ) {
		flxai_task_form( 'new' === $edit ? null : flxai_task_get( (int) $edit ) );
		echo '</div>';
		return;
	}

	$tasks  = flxai_task_list( array( 'limit' => 200 ) );
	$active = array_filter( $tasks, fn( $t ) => 'active' === $t['status'] );
	$next   = 0;
	foreach ( $active as $t ) {
		$next = $t['next_run'] && ( ! $next || $t['next_run'] < $next ) ? $t['next_run'] : $next;
	}
	$wait = count( array_filter( $tasks, fn( $t ) => 'approval' === $t['last_state'] ) );
	$tick = (int) get_option( 'flxai_tasks_tick', 0 );

	echo '<div class="flxai-kpis flxai-tools-kpis">';
	flxai_kpi( __( 'Active', 'flx-ai' ), number_format_i18n( count( $active ) ), sprintf( /* translators: %s: number of tasks */ __( '%s in total', 'flx-ai' ), number_format_i18n( count( $tasks ) ) ) );
	flxai_kpi( __( 'Next run', 'flx-ai' ), $next ? flx_date( $next, 'H:i' ) : '—', $next ? flx_date( $next, 'j F Y' ) : '' );
	flxai_kpi( __( 'Awaiting approval', 'flx-ai' ), number_format_i18n( $wait ), __( 'Open the task chat to approve', 'flx-ai' ), null, $wait > 0 );
	flxai_kpi( __( 'Last check', 'flx-ai' ), $tick ? flx_time_ago( $tick ) : '—', $on ? __( 'Checked every 5 minutes', 'flx-ai' ) : __( 'Tasks are turned off', 'flx-ai' ), null, ! $on );
	echo '</div>';

	echo '<section class="flxai-card flxai-tasks">';
	echo '<div class="flxai-bill-head"><h2>' . esc_html__( 'Tasks', 'flx-ai' ) . '</h2><div class="flxai-tasks-acts">';
	echo '<form method="post" class="flxai-inline">';
	wp_nonce_field( 'flxai_tasks', 'flxai_tasks_nonce' );
	echo '<input type="hidden" name="do" value="toggle_system"><input type="hidden" name="enabled" value="' . ( $on ? '' : '1' ) . '">';
	echo '<button type="submit" class="button">' . esc_html( $on ? __( 'Turn off all tasks', 'flx-ai' ) : __( 'Turn on tasks', 'flx-ai' ) ) . '</button></form>';
	echo '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=flx-ai-tasks&edit=new' ) ) . '">' . esc_html__( 'New task', 'flx-ai' ) . '</a>';
	echo '</div></div>';

	if ( ! $tasks ) {
		echo '<div class="flxai-tasks-empty"><p><strong>' . esc_html__( 'No scheduled tasks yet.', 'flx-ai' ) . '</strong></p>';
		echo '<p class="flxai-note">' . esc_html__( 'Add one here, or just tell the assistant, for example:', 'flx-ai' ) . '</p><ul class="flxai-tasks-ex">';
		foreach ( array(
			__( 'Give me an AI briefing every morning at 9: yesterday\'s donations, new messages and anything that needs me.', 'flx-ai' ),
			__( 'Every Monday at 10, review donors who have not given in 3 months and suggest who to call.', 'flx-ai' ),
			__( 'Check the site speed every 6 hours and tell me only if something got worse.', 'flx-ai' ),
		) as $ex ) {
			echo '<li>“' . esc_html( $ex ) . '”</li>';
		}
		echo '</ul></div></section></div>';
		return;
	}

	echo '<div class="flxai-tscroll"><table class="flxai-table flxai-tasks-table"><thead><tr><th>' . esc_html__( 'Task', 'flx-ai' ) . '</th><th>' . esc_html__( 'Schedule', 'flx-ai' ) . '</th><th>' . esc_html__( 'Next run', 'flx-ai' ) . '</th><th>' . esc_html__( 'Last run', 'flx-ai' ) . '</th><th></th></tr></thead><tbody>';
	foreach ( $tasks as $t ) {
		$owner = get_userdata( $t['user_id'] );
		$chat  = flxai_task_chat_url( $t );
		echo '<tr' . ( 'error' === $t['last_state'] ? ' class="is-err"' : '' ) . '>';
		echo '<td data-l="' . esc_attr__( 'Task', 'flx-ai' ) . '"><strong>' . esc_html( $t['title'] ) . '</strong> ' . flxai_task_state_pill( $t ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="flxai-desc">' . esc_html( wp_trim_words( $t['prompt'], 24, '…' ) ) . '</div>';
		if ( $owner && (int) $owner->ID !== get_current_user_id() ) {
			/* translators: %s: user name */
			echo '<div class="flxai-note">' . esc_html( sprintf( __( 'Runs as %s', 'flx-ai' ), $owner->display_name ) ) . '</div>';
		}
		echo '</td>';
		echo '<td data-l="' . esc_attr__( 'Schedule', 'flx-ai' ) . '">' . esc_html( flxai_task_schedule_label( $t['schedule'] ) ) . '</td>';
		echo '<td data-l="' . esc_attr__( 'Next run', 'flx-ai' ) . '">' . ( $t['next_run'] && 'active' === $t['status'] ? esc_html( flx_date( $t['next_run'], 'j M H:i' ) ) . '<div class="flxai-note">' . esc_html( flx_time_ago( $t['next_run'] ) ) . '</div>' : '—' ) . '</td>';
		echo '<td data-l="' . esc_attr__( 'Last run', 'flx-ai' ) . '">';
		if ( $t['last_run'] ) {
			echo esc_html( flx_time_ago( $t['last_run'] ) );
			if ( '' !== $t['last_error'] ) {
				echo '<div class="flxai-err">' . esc_html( mb_substr( $t['last_error'], 0, 160 ) ) . '</div>';
			} elseif ( ! empty( $t['meta']['summary'] ) ) {
				echo '<div class="flxai-desc">' . esc_html( wp_trim_words( (string) $t['meta']['summary'], 18, '…' ) ) . '</div>';
			}
			echo '<div><a href="' . esc_url( admin_url( 'admin.php?page=flx-ai-tasks&history=' . $t['id'] ) ) . '">' . esc_html__( 'Open report', 'flx-ai' ) . '</a></div>';
		} else {
			echo '<span class="flxai-note">' . esc_html__( 'Not run yet', 'flx-ai' ) . '</span>';
		}
		echo '</td><td class="flxai-tasks-row-acts">';
		echo '<form method="post">';
		wp_nonce_field( 'flxai_tasks', 'flxai_tasks_nonce' );
		echo '<input type="hidden" name="id" value="' . esc_attr( $t['id'] ) . '">';
		echo '<button type="submit" name="do" value="run" class="button button-small">' . esc_html__( 'Run now', 'flx-ai' ) . '</button> ';
		if ( 'active' === $t['status'] ) {
			echo '<button type="submit" name="do" value="pause" class="button button-small">' . esc_html__( 'Pause', 'flx-ai' ) . '</button> ';
		} else {
			echo '<button type="submit" name="do" value="resume" class="button button-small">' . esc_html__( 'Resume', 'flx-ai' ) . '</button> ';
		}
		echo '<a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=flx-ai-tasks&history=' . $t['id'] ) ) . '">' . esc_html__( 'History', 'flx-ai' ) . '</a> ';
		echo '<a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=flx-ai-tasks&edit=' . $t['id'] ) ) . '">' . esc_html__( 'Edit', 'flx-ai' ) . '</a> ';
		printf(
			'<button type="submit" name="do" value="delete" class="button button-small button-link-delete" data-flx-confirm="%1$s" data-flx-confirm-ok="%2$s" data-flx-confirm-danger>%2$s</button>',
			/* translators: %s: task name */
			esc_attr( sprintf( __( 'Delete the task "%s"? Its chat is kept.', 'flx-ai' ), $t['title'] ) ),
			esc_html__( 'Delete', 'flx-ai' )
		);
		echo '</form></td></tr>';
	}
	echo '</tbody></table></div>';
	echo '<p class="flxai-note flxai-tasks-tip">' . esc_html__( 'Tip: you can also create and change tasks from the chat, e.g. "move my daily briefing to 8:30 and add the weather".', 'flx-ai' ) . '</p>';
	echo '</section></div>';
}

/** Ekle / düzenle formu. */
function flxai_task_form( $t ) {
	global $wp_locale;
	$s    = $t ? $t['schedule'] : flxai_task_schedule_clean( array() );
	$freq = $s['freq'];
	echo '<form method="post" class="flxai-card flxai-task-form" data-flxai-task-form>';
	wp_nonce_field( 'flxai_tasks', 'flxai_tasks_nonce' );
	echo '<input type="hidden" name="do" value="save"><input type="hidden" name="id" value="' . esc_attr( $t ? $t['id'] : 0 ) . '">';
	echo '<h2>' . esc_html( $t ? __( 'Edit task', 'flx-ai' ) : __( 'New task', 'flx-ai' ) ) . '</h2>';
	echo '<table class="form-table" role="presentation">';
	echo '<tr><th><label for="flxai-task-title">' . esc_html__( 'Name', 'flx-ai' ) . '</label></th><td><input id="flxai-task-title" name="title" type="text" class="regular-text" maxlength="190" value="' . esc_attr( $t ? $t['title'] : '' ) . '" placeholder="' . esc_attr__( 'Daily AI briefing', 'flx-ai' ) . '"></td></tr>';
	echo '<tr><th><label for="flxai-task-prompt">' . esc_html__( 'Instructions', 'flx-ai' ) . '</label></th><td><textarea id="flxai-task-prompt" name="prompt" rows="7" class="large-text" required placeholder="' . esc_attr__( 'Summarise yesterday\'s donations, unanswered messages and site issues. Keep it short, with headings.', 'flx-ai' ) . '">' . esc_textarea( $t ? $t['prompt'] : '' ) . '</textarea>';
	echo '<p class="description">' . esc_html__( 'Written to the assistant as if you sent it in the chat. It uses the same tools (CRM, Donate, SEO, Speed…) with your permissions.', 'flx-ai' ) . '</p></td></tr>';

	$freqs = array(
		'daily'    => __( 'Every day', 'flx-ai' ),
		'weekdays' => __( 'Weekdays', 'flx-ai' ),
		'weekly'   => __( 'Selected days of the week', 'flx-ai' ),
		'monthly'  => __( 'Every month', 'flx-ai' ),
		'hourly'   => __( 'Every few hours', 'flx-ai' ),
		'once'     => __( 'Once', 'flx-ai' ),
	);
	echo '<tr><th><label for="flxai-task-freq">' . esc_html__( 'Repeat', 'flx-ai' ) . '</label></th><td><select id="flxai-task-freq" name="freq">';
	foreach ( $freqs as $k => $l ) {
		printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $k ), selected( $freq, $k, false ), esc_html( $l ) );
	}
	echo '</select></td></tr>';

	echo '<tr data-for="weekly"><th>' . esc_html__( 'Days', 'flx-ai' ) . '</th><td><div class="flxai-days">';
	$days = (array) ( $s['days'] ?? array( 1 ) );
	for ( $d = 1; $d <= 7; $d++ ) {
		$name = $wp_locale ? $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( 7 === $d ? 0 : $d ) ) : (string) $d;
		printf( '<label class="flxai-check"><input type="checkbox" name="days[]" value="%1$d"%2$s> %3$s</label>', (int) $d, checked( in_array( $d, $days, true ), true, false ), esc_html( $name ) );
	}
	echo '</div></td></tr>';
	echo '<tr data-for="monthly"><th><label for="flxai-task-day">' . esc_html__( 'Day of month', 'flx-ai' ) . '</label></th><td><input id="flxai-task-day" name="day" type="number" min="1" max="31" class="small-text" value="' . esc_attr( (int) ( $s['day'] ?? 1 ) ) . '"><p class="description">' . esc_html__( 'In shorter months the task runs on the last day.', 'flx-ai' ) . '</p></td></tr>';
	echo '<tr data-for="hourly"><th><label for="flxai-task-every">' . esc_html__( 'Every', 'flx-ai' ) . '</label></th><td><input id="flxai-task-every" name="every" type="number" min="1" max="24" class="small-text" value="' . esc_attr( (int) ( $s['every'] ?? 6 ) ) . '"> ' . esc_html__( 'hours', 'flx-ai' ) . '</td></tr>';
	echo '<tr data-for="daily weekdays weekly monthly hourly"><th><label for="flxai-task-time">' . esc_html__( 'Time', 'flx-ai' ) . '</label></th><td><input id="flxai-task-time" name="time" type="time" value="' . esc_attr( $s['time'] ?? '09:00' ) . '">';
	/* translators: %s: timezone name */
	echo '<p class="description">' . esc_html( sprintf( __( 'Site time zone: %s.', 'flx-ai' ), wp_timezone_string() ) ) . '</p></td></tr>';
	echo '<tr data-for="once"><th><label for="flxai-task-at">' . esc_html__( 'Date and time', 'flx-ai' ) . '</label></th><td><input id="flxai-task-at" name="at" type="datetime-local" data-flxa-native value="' . esc_attr( str_replace( ' ', 'T', (string) ( $s['at'] ?? '' ) ) ) . '"></td></tr>';
	echo '<tr><th>' . esc_html__( 'Status', 'flx-ai' ) . '</th><td><label class="flxai-check"><input type="checkbox" name="active" value="1"' . checked( ! $t || 'active' === $t['status'], true, false ) . '> ' . esc_html__( 'Active', 'flx-ai' ) . '</label></td></tr>';
	echo '</table>';
	echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save', 'flx-ai' ) . '</button> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=flx-ai-tasks' ) ) . '">' . esc_html__( 'Cancel', 'flx-ai' ) . '</a></p>';
	echo '</form>';
}

/** Bir çalışmanın sohbetteki mesajları: başlangıçtan bir sonraki kullanıcı mesajına kadar (onay sonrası devam dahil). */
function flxai_task_run_messages( array $chat, array $run ) {
	$from = (int) ( $run['from'] ?? -1 );
	$msgs = $chat['messages'];
	if ( $from < 0 || ! isset( $msgs[ $from ] ) ) {
		return array();
	}
	$out = array( $msgs[ $from ] );
	for ( $i = $from + 1, $n = count( $msgs ); $i < $n; $i++ ) {
		if ( 'user' === ( $msgs[ $i ]['role'] ?? '' ) ) {
			break;
		}
		$out[] = $msgs[ $i ];
	}
	return $out;
}

/** Çalışmanın akışı: AI sohbetindeki gibi istem, düşünme, araç çağrıları (girdi + sonuç) ve cevap. */
function flxai_task_run_timeline( array $msgs ) {
	$tools   = flxai_tools_all();
	$results = array();
	foreach ( $msgs as $m ) {
		if ( 'tool' === ( $m['role'] ?? '' ) ) {
			$results[ (string) $m['tool_call_id'] ] = $m;
		}
	}
	$pretty = function ( $v ) {
		$d = is_string( $v ) ? json_decode( $v ) : $v;
		$t = null !== $d ? wp_json_encode( $d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : (string) $v;
		return mb_strlen( $t ) > 4000 ? mb_substr( $t, 0, 4000 ) . "\n…" : $t;
	};
	echo '<div class="flxai-tl">';
	foreach ( $msgs as $m ) {
		$role = $m['role'] ?? '';
		if ( 'user' === $role ) {
			echo '<div class="flxai-tl-user"><span class="flxai-tl-k">' . esc_html__( 'Instructions', 'flx-ai' ) . '</span><div class="flxai-tl-b">' . esc_html( (string) $m['content'] ) . '</div></div>';
		} elseif ( 'assistant' === $role ) {
			if ( ! empty( $m['_thinking'] ) ) {
				echo '<details class="flxai-tl-think"><summary>' . esc_html__( 'Reasoning', 'flx-ai' ) . '</summary><div>' . esc_html( (string) $m['_thinking'] ) . '</div></details>';
			}
			foreach ( (array) ( $m['tool_calls'] ?? array() ) as $c ) {
				$name = (string) ( $c['function']['name'] ?? '' );
				$res  = $results[ (string) ( $c['id'] ?? '' ) ] ?? null;
				$ok   = $res ? ! empty( $res['_ok'] ) : null;
				$pill = null === $res
					? '<span class="flxai-pill is-warn">' . esc_html__( 'Awaiting approval', 'flx-ai' ) . '</span>'
					: ( ! empty( $res['_rejected'] ) ? '<span class="flxai-pill is-err">' . esc_html__( 'Not run', 'flx-ai' ) . '</span>'
						: ( $ok ? '<span class="flxai-pill is-ok">' . esc_html( flxai_ms( (int) ( $res['_ms'] ?? 0 ) ) ) . '</span>' : '<span class="flxai-pill is-err">' . esc_html__( 'Error', 'flx-ai' ) . '</span>' ) );
				echo '<details class="flxai-tl-tool"><summary><span class="flxai-tl-ic" aria-hidden="true">⚙</span><strong>' . esc_html( (string) ( $tools[ $name ]['title'] ?? $name ) ) . '</strong> <code>' . esc_html( $name ) . '</code> ' . $pill . '</summary>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<div class="flxai-tl-io"><span class="flxai-tl-k">' . esc_html__( 'Input', 'flx-ai' ) . '</span><pre>' . esc_html( $pretty( (string) ( $c['function']['arguments'] ?? '{}' ) ) ) . '</pre>';
				if ( $res ) {
					echo '<span class="flxai-tl-k">' . esc_html__( 'Result', 'flx-ai' ) . '</span><pre>' . esc_html( $pretty( (string) $res['content'] ) ) . '</pre>';
				}
				echo '</div></details>';
			}
			$text = is_string( $m['content'] ?? null ) ? trim( $m['content'] ) : '';
			if ( '' !== $text ) {
				echo '<div class="flxai-tl-ai' . ( ! empty( $m['_error'] ) ? ' is-err' : '' ) . '"><div class="flxai-md" data-md="' . esc_attr( $text ) . '">' . esc_html( $text ) . '</div></div>';
			}
		}
	}
	echo '</div>';
}

/** Görevin çalışma geçmişi. */
function flxai_task_history_page( array $t ) {
	$back = admin_url( 'admin.php?page=flx-ai-tasks' );
	/* translators: %s: task name */
	flxai_page_head( sprintf( __( 'History: %s', 'flx-ai' ), $t['title'] ), flxai_task_schedule_label( $t['schedule'] ) );
	$hist  = (array) ( $t['meta']['history'] ?? array() );
	$ok    = count( array_filter( $hist, fn( $h ) => 'ok' === ( $h['state'] ?? '' ) ) );
	$cost  = array_sum( array_map( fn( $h ) => (float) ( $h['cost'] ?? 0 ), $hist ) );
	$ms    = array_filter( array_map( fn( $h ) => (int) ( $h['ms'] ?? 0 ), $hist ) );
	echo '<p class="flxai-tasks-back"><a href="' . esc_url( $back ) . '">← ' . esc_html__( 'Scheduled tasks', 'flx-ai' ) . '</a></p>';
	echo '<div class="flxai-kpis flxai-tools-kpis">';
	flxai_kpi( __( 'Runs', 'flx-ai' ), number_format_i18n( $t['runs'] ), sprintf( /* translators: %s: number of runs */ __( '%s completed', 'flx-ai' ), number_format_i18n( $ok ) ) );
	flxai_kpi( __( 'Average time', 'flx-ai' ), $ms ? flxai_ms( (int) ( array_sum( $ms ) / count( $ms ) ) ) : '—' );
	flxai_kpi( __( 'Cost', 'flx-ai' ), flxai_usd( $cost ), __( 'Shown runs', 'flx-ai' ) );
	flxai_kpi( __( 'Next run', 'flx-ai' ), $t['next_run'] && 'active' === $t['status'] ? flx_date( $t['next_run'], 'j M H:i' ) : '—' );
	echo '</div>';

	echo '<section class="flxai-card"><div class="flxai-bill-head"><h2>' . esc_html__( 'Instructions', 'flx-ai' ) . '</h2><a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=flx-ai-tasks&edit=' . $t['id'] ) ) . '">' . esc_html__( 'Edit', 'flx-ai' ) . '</a></div><div class="flxai-tl-prompt">' . esc_html( $t['prompt'] ) . '</div></section>';

	echo '<section class="flxai-card flxai-runs"><h2>' . esc_html__( 'Runs', 'flx-ai' ) . '</h2>';
	if ( ! $hist ) {
		echo '<p class="flxai-empty">' . esc_html__( 'Not run yet', 'flx-ai' ) . '</p></section>';
		return;
	}
	$chats = array();
	$state = array(
		'ok'       => array( 'is-ok', __( 'Completed', 'flx-ai' ) ),
		'approval' => array( 'is-warn', __( 'Awaiting approval', 'flx-ai' ) ),
		'waiting'  => array( 'is-warn', __( 'Skipped: approval pending', 'flx-ai' ) ),
		'error'    => array( 'is-err', __( 'Error', 'flx-ai' ) ),
	);
	foreach ( $hist as $i => $h ) {
		$cid = (int) ( $h['chat'] ?? $t['chat_id'] );
		if ( $cid && ! array_key_exists( $cid, $chats ) ) {
			$chats[ $cid ] = flxai_chat_get( $cid, $t['user_id'] );
		}
		$chat = $cid ? $chats[ $cid ] : null;
		$msgs = $chat ? flxai_task_run_messages( $chat, $h ) : array();
		$st   = $state[ $h['state'] ?? '' ] ?? array( '', (string) ( $h['state'] ?? '' ) );
		// Onay bekleyen çalışma onaylandıysa son durumu sohbetten okunur.
		if ( 'approval' === ( $h['state'] ?? '' ) && $chat && 'approval' !== $chat['status'] && $msgs && 'assistant' === ( end( $msgs )['role'] ?? '' ) ) {
			$st = $state['ok'];
		}
		$n_tools = 0;
		foreach ( $msgs as $m ) {
			$n_tools += count( (array) ( $m['tool_calls'] ?? array() ) );
		}
		echo '<details class="flxai-run"' . ( 0 === $i ? ' open' : '' ) . '><summary>';
		echo '<span class="flxai-run-when"><strong>' . esc_html( flx_date( (int) $h['ts'], 'j M Y H:i' ) ) . '</strong><span class="flxai-note">' . esc_html( flx_time_ago( (int) $h['ts'] ) ) . '</span></span>';
		echo '<span class="flxai-pill ' . esc_attr( $st[0] ) . '">' . esc_html( $st[1] ) . '</span>';
		echo '<span class="flxai-run-meta">';
		if ( ! empty( $h['ms'] ) ) {
			echo '<span>' . esc_html( flxai_ms( (int) $h['ms'] ) ) . '</span>';
		}
		if ( $n_tools ) {
			/* translators: %s: number of tool calls */
			echo '<span>' . esc_html( sprintf( __( 'Tools: %s', 'flx-ai' ), number_format_i18n( $n_tools ) ) ) . '</span>';
		}
		if ( ! empty( $h['cost'] ) ) {
			echo '<span>' . esc_html( flxai_usd( (float) $h['cost'] ) ) . '</span>';
		}
		echo '</span></summary><div class="flxai-run-body">';
		if ( ! empty( $h['error'] ) ) {
			echo '<div class="flxai-err">' . esc_html( (string) $h['error'] ) . '</div>';
		}
		if ( $msgs ) {
			flxai_task_run_timeline( $msgs );
			$url = add_query_arg( 'flxai_chat', $cid, admin_url( 'admin.php?page=flx-ai-tasks&history=' . $t['id'] ) );
			echo '<p class="flxai-run-foot"><a href="' . esc_url( $url ) . '">' . esc_html__( 'Continue in the AI chat', 'flx-ai' ) . '</a></p>';
		} elseif ( 'waiting' === ( $h['state'] ?? '' ) ) {
			echo '<p class="flxai-note">' . esc_html__( 'The previous run still had an action waiting for approval, so this run was skipped.', 'flx-ai' ) . '</p>';
		} else {
			echo '<p class="flxai-note">' . esc_html__( 'No details were kept for this run.', 'flx-ai' ) . '</p>';
		}
		echo '</div></details>';
	}
	echo '</section>';
}
