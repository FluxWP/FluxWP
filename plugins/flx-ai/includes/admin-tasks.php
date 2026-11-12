<?php
/**
 * Flux AI & MCP › Zamanlanmış görevler: liste, ekle/düzenle, şimdi çalıştır, duraklat, sil.
 * Görevler sohbetten de (flxai_task_* araçları) oluşturulur ve düzenlenir.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/* Geçmiş / rapor bağlantıları (eski &history= dahil) doğrudan görevin AI sohbetini açar; rapor okunmuş sayılır. */
add_action(
	'admin_init',
	function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( 'flx-ai-tasks' !== ( $_GET['page'] ?? '' ) || ( empty( $_GET['open'] ) && empty( $_GET['history'] ) ) ) {
			return;
		}
		$t = flxai_task_get( (int) ( $_GET['open'] ?? $_GET['history'] ) );
		// phpcs:enable
		if ( ! $t || ( (int) $t['user_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=flx-ai-tasks' ) );
			exit;
		}
		flxai_task_mark_read( $t['id'] );
		wp_safe_redirect( $t['chat_id'] ? flxai_task_chat_url( $t ) : admin_url( 'admin.php?page=flx-ai-tasks' ) );
		exit;
	}
);

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
					'skill'    => (string) ( $post['skill'] ?? '' ),
					'ws'       => array(
						'access'  => sanitize_key( $post['ws_access'] ?? 'workspace' ),
						'folders' => (string) ( $post['ws_folders'] ?? '' ),
					),
					'notify'   => array(
						'mode'     => sanitize_key( $post['notify'] ?? 'none' ),
						'email'    => ! empty( $post['notify_email'] ),
						'panel'    => ! empty( $post['notify_panel'] ),
						'whatsapp' => ! empty( $post['notify_wa'] ) ? (string) ( $post['notify_whatsapp'] ?? '' ) : '',
						'to'       => (array) json_decode( (string) ( $post['notify_to'] ?? '[]' ), true ),
					),
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

/** Görevin sohbetini tam ekran AI sohbetinde açan adres. */
function flxai_task_chat_url( array $t ) {
	return $t['chat_id'] ? add_query_arg( 'flxai_chat', (int) $t['chat_id'], admin_url( 'admin.php?page=flx-ai-chat' ) ) : '';
}

/** Görevin raporunu açan adres (okundu işaretler, sohbete yönlendirir). */
function flxai_task_open_url( array $t ) {
	return admin_url( 'admin.php?page=flx-ai-tasks&open=' . (int) $t['id'] );
}

function flxai_page_tasks() {
	$on   = (bool) flxai_setting( 'tasks_enabled', 1 );
	$edit = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
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
	$wait   = flxai_approvals_count();
	$unread = flxai_tasks_unread();
	$tick = (int) get_option( 'flxai_tasks_tick', 0 );

	echo '<div class="flxai-kpis flxai-tools-kpis">';
	flxai_kpi( __( 'Active', 'flx-ai' ), number_format_i18n( count( $active ) ), sprintf( /* translators: %s: number of tasks */ __( '%s in total', 'flx-ai' ), number_format_i18n( count( $tasks ) ) ) );
	flxai_kpi( __( 'Next run', 'flx-ai' ), $next ? flx_date( $next, 'H:i' ) : '—', $next ? flx_date( $next, 'j F Y' ) : '' );
	flxai_kpi( __( 'Awaiting approval', 'flx-ai' ), number_format_i18n( $wait ), __( 'Across all your chats and tasks', 'flx-ai' ), null, $wait > 0 );
	flxai_kpi( __( 'Last check', 'flx-ai' ), $tick ? flx_time_ago( $tick ) : '—', $on ? __( 'Checked every 5 minutes', 'flx-ai' ) : __( 'Tasks are turned off', 'flx-ai' ), null, ! $on );
	echo '</div>';

	if ( $wait ) {
		/* translators: %s: number of actions */
		echo '<div class="notice notice-warning inline flxai-appr-notice"><p>' . esc_html( sprintf( __( '%s actions are waiting for your approval.', 'flx-ai' ), number_format_i18n( $wait ) ) ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=flx-ai-approvals' ) ) . '">' . esc_html__( 'Review approvals', 'flx-ai' ) . '</a></p></div>';
	}
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

	echo '<div class="flxai-tlist">';
	foreach ( $tasks as $t ) {
		flxai_task_card( $t, isset( $unread[ $t['id'] ] ) );
	}
	echo '</div>';
	echo '<p class="flxai-note flxai-tasks-tip">' . esc_html__( 'Tip: you can also create and change tasks from the chat, e.g. "move my daily briefing to 8:30 and add the weather".', 'flx-ai' ) . '</p>';
	echo '</section></div>';
}

/** Bildirim alıcılarının kısa özeti. */
function flxai_task_notify_label( array $t ) {
	$n = flxai_task_notify_cfg( $t );
	if ( 'none' === $n['mode'] ) {
		return '';
	}
	$who = array();
	if ( $n['panel'] ) {
		$who[] = __( 'panel', 'flx-ai' );
	}
	if ( $n['email'] ) {
		$who[] = __( 'my e-mail', 'flx-ai' );
	}
	if ( '' !== $n['whatsapp'] ) {
		$who[] = 'WhatsApp';
	}
	foreach ( $n['to'] as $r ) {
		$who[] = $r['name'];
	}
	if ( ! $who ) {
		return '';
	}
	$more = count( $who ) > 3 ? ' +' . number_format_i18n( count( $who ) - 3 ) : '';
	return implode( ', ', array_slice( $who, 0, 3 ) ) . $more;
}

/** Görev kartı: başlık, zamanlama, işlemler, son çıktı (açılır). */
function flxai_task_card( array $t, $unread ) {
	$owner = get_userdata( $t['user_id'] );
	$hist  = (array) ( $t['meta']['history'] ?? array() );
	$cost  = array_sum( array_map( fn( $h ) => (float) ( $h['cost'] ?? 0 ), $hist ) );
	$ms    = array_filter( array_map( fn( $h ) => (int) ( $h['ms'] ?? 0 ), $hist ) );
	$open  = flxai_task_open_url( $t );
	$cls   = 'flxai-tc' . ( 'error' === $t['last_state'] ? ' is-err' : '' ) . ( 'active' !== $t['status'] ? ' is-off' : '' ) . ( $unread ? ' is-new' : '' );
	echo '<article class="' . esc_attr( $cls ) . '">';
	echo '<div class="flxai-tc-head"><span class="flxai-tc-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>';
	echo '<div class="flxai-tc-main"><div class="flxai-tc-title">';
	echo $t['chat_id'] ? '<a href="' . esc_url( $open ) . '">' . esc_html( $t['title'] ) . '</a>' : '<strong>' . esc_html( $t['title'] ) . '</strong>';
	echo ' ' . flxai_task_state_pill( $t ); // phpcs:ignore WordPress.Security.EscapeOutput
	if ( $unread ) {
		echo ' <span class="flxai-pill is-new">' . esc_html__( 'New report', 'flx-ai' ) . '</span>';
	}
	if ( ! empty( $t['meta']['skill'] ) ) {
		echo ' <code class="flxai-skill-slug">/' . esc_html( $t['meta']['skill'] ) . '</code>';
	}
	echo '</div><div class="flxai-tc-meta">';
	echo '<span>' . esc_html( flxai_task_schedule_label( $t['schedule'] ) ) . '</span>';
	if ( $t['next_run'] && 'active' === $t['status'] ) {
		/* translators: 1: date and time, 2: relative time */
		echo '<span>' . esc_html( sprintf( __( 'Next: %1$s (%2$s)', 'flx-ai' ), flx_date( $t['next_run'], 'j M H:i' ), flx_time_ago( $t['next_run'] ) ) ) . '</span>';
	}
	if ( $t['runs'] ) {
		/* translators: %s: number of runs */
		$bits = array( sprintf( __( 'Runs: %s', 'flx-ai' ), number_format_i18n( $t['runs'] ) ) );
		if ( $ms ) {
			/* translators: %s: duration */
			$bits[] = sprintf( __( 'avg. %s', 'flx-ai' ), flxai_ms( (int) ( array_sum( $ms ) / count( $ms ) ) ) );
		}
		if ( $cost > 0 ) {
			$bits[] = flxai_usd( $cost );
		}
		echo '<span>' . esc_html( implode( ' · ', $bits ) ) . '</span>';
	}
	$nl = flxai_task_notify_label( $t );
	if ( '' !== $nl ) {
		echo '<span title="' . esc_attr__( 'Notifications', 'flx-ai' ) . '">🔔 ' . esc_html( $nl ) . '</span>';
	}
	if ( $owner && (int) $owner->ID !== get_current_user_id() ) {
		/* translators: %s: user name */
		echo '<span>' . esc_html( sprintf( __( 'Runs as %s', 'flx-ai' ), $owner->display_name ) ) . '</span>';
	}
	echo '</div></div>';

	echo '<form method="post" class="flxai-tc-acts">';
	wp_nonce_field( 'flxai_tasks', 'flxai_tasks_nonce' );
	echo '<input type="hidden" name="id" value="' . esc_attr( $t['id'] ) . '">';
	if ( $t['chat_id'] ) {
		echo '<a class="button" href="' . esc_url( $open ) . '">' . esc_html__( 'Details', 'flx-ai' ) . '</a>';
	}
	echo '<button type="submit" name="do" value="run" class="button" title="' . esc_attr__( 'Run now', 'flx-ai' ) . '"><span class="flxai-tc-run" aria-hidden="true">▶</span><span class="flxai-tc-lbl">' . esc_html__( 'Run now', 'flx-ai' ) . '</span></button>';
	echo '<details class="flxai-more"><summary class="button" aria-label="' . esc_attr__( 'More', 'flx-ai' ) . '">⋯</summary><div class="flxai-more-menu">';
	if ( 'active' === $t['status'] ) {
		echo '<button type="submit" name="do" value="pause">' . esc_html__( 'Pause', 'flx-ai' ) . '</button>';
	} else {
		echo '<button type="submit" name="do" value="resume">' . esc_html__( 'Resume', 'flx-ai' ) . '</button>';
	}
	echo '<a href="' . esc_url( admin_url( 'admin.php?page=flx-ai-tasks&edit=' . $t['id'] ) ) . '">' . esc_html__( 'Edit', 'flx-ai' ) . '</a>';
	printf(
		'<button type="submit" name="do" value="delete" class="is-danger" data-flx-confirm="%1$s" data-flx-confirm-ok="%2$s" data-flx-confirm-danger>%2$s</button>',
		/* translators: %s: task name */
		esc_attr( sprintf( __( 'Delete the task "%s"? Its chat is kept.', 'flx-ai' ), $t['title'] ) ),
		esc_html__( 'Delete', 'flx-ai' )
	);
	echo '</div></details></form></div>';

	if ( ! $t['last_run'] ) {
		echo '<div class="flxai-tc-prompt">' . esc_html( wp_trim_words( $t['prompt'], 30, '…' ) ) . '</div>';
	} elseif ( '' !== $t['last_error'] && 'error' === $t['last_state'] ) {
		echo '<div class="flxai-tc-err">' . esc_html( mb_substr( $t['last_error'], 0, 300 ) ) . '</div>';
	} else {
		$out = flxai_task_last_output( $t );
		echo '<details class="flxai-tc-out"' . ( $unread ? ' open' : '' ) . '><summary><span>' . esc_html__( 'Last output', 'flx-ai' ) . '</span><span class="flxai-note">' . esc_html( flx_time_ago( $t['last_run'] ) ) . '</span>';
		if ( '' === $out && ! empty( $t['meta']['summary'] ) ) {
			$out = (string) $t['meta']['summary'];
		}
		if ( '' !== $out ) {
			echo '<span class="flxai-tc-peek">' . esc_html( wp_trim_words( wp_strip_all_tags( preg_replace( '/[#*_`>|]+/', ' ', $out ) ), 16, '…' ) ) . '</span>';
		}
		echo '</summary><div class="flxai-tc-body">';
		echo '' !== $out ? '<div class="flxai-md" data-md="' . esc_attr( mb_substr( $out, 0, 20000 ) ) . '">' . esc_html( $out ) . '</div>' : '<p class="flxai-note">' . esc_html__( 'No details were kept for this run.', 'flx-ai' ) . '</p>';
		if ( $t['chat_id'] ) {
			echo '<p class="flxai-tc-foot"><a href="' . esc_url( $open ) . '">' . esc_html__( 'Open in the AI chat: reasoning, tool calls and follow-up', 'flx-ai' ) . ' →</a></p>';
		}
		echo '</div></details>';
	}
	echo '</article>';
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
	$cur = (string) ( $t['meta']['skill'] ?? '' );
	echo '<tr><th><label for="flxai-task-skill">' . esc_html__( 'Skill', 'flx-ai' ) . '</label></th><td><select id="flxai-task-skill" name="skill"><option value="">' . esc_html__( 'None', 'flx-ai' ) . '</option>';
	foreach ( flxai_skills_all() as $sk ) {
		printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $sk['slug'] ), selected( $cur, $sk['slug'], false ), esc_html( $sk['name'] . ( 'active' !== $sk['status'] ? ' (' . __( 'Off', 'flx-ai' ) . ')' : '' ) ) );
	}
	echo '</select> <a href="' . esc_url( admin_url( 'admin.php?page=flx-ai-skills' ) ) . '">' . esc_html__( 'Skills', 'flx-ai' ) . '</a>';
	echo '<p class="description">' . esc_html__( 'The skill\'s instructions are added on every run, always in their latest version. The instructions above can stay short (e.g. "only donations").', 'flx-ai' ) . '</p></td></tr>';

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
	$n = $t ? flxai_task_notify_cfg( $t ) : array(
		'mode'     => 'important',
		'email'    => false,
		'panel'    => true,
		'whatsapp' => '',
		'to'       => array(),
	);
	echo '<tr><th><label for="flxai-task-notify">' . esc_html__( 'Notify me', 'flx-ai' ) . '</label></th><td><select id="flxai-task-notify" name="notify">';
	foreach ( array(
		'important' => __( 'Only when something important comes up', 'flx-ai' ),
		'always'    => __( 'After every run', 'flx-ai' ),
		'none'      => __( 'Never', 'flx-ai' ),
	) as $k => $l ) {
		printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $k ), selected( $n['mode'], $k, false ), esc_html( $l ) );
	}
	echo '</select><p class="description">' . esc_html__( '"Important": the assistant decides there is something you should know now, an action waits for your approval, or the task failed.', 'flx-ai' ) . '</p>';
	$owner = $t ? get_userdata( $t['user_id'] ) : wp_get_current_user();
	echo '<div class="flxai-notify-ch" data-for-notify>';
	/* translators: %s: e-mail address */
	echo '<label class="flxai-check"><input type="checkbox" name="notify_panel" value="1"' . checked( $n['panel'], true, false ) . '> ' . esc_html__( 'In the admin panel (badge on the menu)', 'flx-ai' ) . '</label>';
	echo '<label class="flxai-check"><input type="checkbox" name="notify_email" value="1"' . checked( $n['email'], true, false ) . '> ' . esc_html( sprintf( __( 'By e-mail (%s)', 'flx-ai' ), $owner ? $owner->user_email : '' ) ) . '</label>';
	$wa_ok = function_exists( 'flxi_send_message' );
	echo '<label class="flxai-check"><input type="checkbox" name="notify_wa" value="1"' . checked( '' !== $n['whatsapp'], true, false ) . disabled( ! $wa_ok, true, false ) . '> ' . esc_html__( 'By WhatsApp', 'flx-ai' ) . '</label>';
	if ( $wa_ok ) {
		if ( function_exists( 'flx_phone_field' ) ) {
			echo flx_phone_field( // phpcs:ignore WordPress.Security.EscapeOutput
				array(
					'name'  => 'notify_whatsapp',
					'value' => '' !== $n['whatsapp'] ? '+' . $n['whatsapp'] : '',
					'id'    => 'flxai-task-wa',
				)
			);
		} else {
			echo '<input id="flxai-task-wa" name="notify_whatsapp" type="tel" class="regular-text" value="' . esc_attr( $n['whatsapp'] ) . '" placeholder="905321234567">';
		}
	} else {
		echo '<p class="description">' . esc_html__( 'WhatsApp needs Flux Mail with a WhatsApp connection.', 'flx-ai' ) . '</p>';
	}
	echo '<div class="flxai-rcp" data-rcp data-rest="' . esc_url( rest_url( 'flx-ai/v1/notify/recipients' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '" data-channels="' . esc_attr( wp_json_encode( flxai_notify_channels() ) ) . '">';
	echo '<div class="flxai-rcp-h"><strong>' . esc_html__( 'Also notify', 'flx-ai' ) . '</strong> <span class="description">' . esc_html__( 'People from Flux CRM, site users or WhatsApp groups. Pick a channel for each.', 'flx-ai' ) . '</span></div>';
	echo '<input type="hidden" name="notify_to" value="' . esc_attr( wp_json_encode( $n['to'] ) ) . '">';
	echo '<div class="flxai-rcp-list"></div>';
	echo '<div class="flxai-rcp-add"><input type="search" class="regular-text" autocomplete="off" placeholder="' . esc_attr__( 'Search name, e-mail, phone or group…', 'flx-ai' ) . '" aria-label="' . esc_attr__( 'Add recipient', 'flx-ai' ) . '"><div class="flxai-rcp-res" hidden></div></div>';
	echo '<p class="description">' . esc_html__( 'You can also say it in the AI chat, e.g. "send the daily briefing to Ayşe by WhatsApp and to the board group".', 'flx-ai' ) . '</p>';
	echo '</div></div></td></tr>';
	if ( function_exists( 'flxai_ws_ready' ) ) {
		$ws  = (array) ( $t['meta']['ws'] ?? array() );
		$acc = 'site' === ( $ws['access'] ?? '' ) ? 'site' : 'workspace';
		echo '<tr><th>' . esc_html__( 'Files', 'flx-ai' ) . '</th><td>';
		if ( ! flxai_ws_ready() ) {
			echo '<p class="description">' . esc_html__( 'Install Flux Files to give tasks a workspace folder (reports, notes, drafts).', 'flx-ai' ) . '</p>';
		}
		echo '<fieldset class="flxai-ws-acc"><label class="flxai-check"><input type="radio" name="ws_access" value="workspace"' . checked( $acc, 'workspace', false ) . '> ' . esc_html__( 'Only its own folder and the folders below', 'flx-ai' ) . '</label>';
		echo '<label class="flxai-check"><input type="radio" name="ws_access" value="site"' . checked( $acc, 'site', false ) . '> ' . esc_html__( 'Can read all site files', 'flx-ai' ) . '</label></fieldset>';
		echo '<p><label for="flxai-ws-folders">' . esc_html__( 'Allowed folders (one per line, relative to the site root)', 'flx-ai' ) . '</label><br><textarea id="flxai-ws-folders" name="ws_folders" rows="3" class="large-text code" placeholder="wp-content/uploads/raporlar">' . esc_textarea( implode( "\n", (array) ( $ws['folders'] ?? array() ) ) ) . '</textarea></p>';
		echo '<p class="description">' . esc_html__( 'The task writes freely only in its own folder. Any change to other files waits for your approval as a change request. Other chats\' folders, passwords and WordPress core are always off limits.', 'flx-ai' );
		if ( ! empty( $ws['dir'] ) ) {
			echo ' <code>' . esc_html( $ws['dir'] ) . '</code>';
		}
		echo '</p></td></tr>';
	}
	echo '</table>';
	echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save', 'flx-ai' ) . '</button> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=flx-ai-tasks' ) ) . '">' . esc_html__( 'Cancel', 'flx-ai' ) . '</a></p>';
	echo '</form>';
}
