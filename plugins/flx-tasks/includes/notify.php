<?php
/**
 * Bildirimler: atama, yorum, bahsetme, durum değişikliği, yaklaşan / geciken bitiş tarihi.
 * Arayüz bildirimleri seyrek yoklamayla alır (gizli sekmede durur); isteğe bağlı e-posta.
 */

defined( 'ABSPATH' ) || exit;

function flxg_notify( $user_ids, $type, $task_id, $data, $actor ) {
	global $wpdb;
	$now = flxg_now();
	foreach ( array_unique( array_map( 'intval', (array) $user_ids ) ) as $u ) {
		if ( ! $u || $u === (int) $actor || ! flxg_can_task( $task_id, 'view', $u ) ) {
			continue;
		}
		$wpdb->insert(
			flxg_table( 'notifications' ),
			array(
				'user_id'    => $u,
				'actor_id'   => (int) $actor,
				'task_id'    => (int) $task_id,
				'type'       => $type,
				'data'       => flxg_enc( $data ),
				'created_at' => $now,
			)
		);
		flxg_maybe_email( $u, $type, $task_id, $data, $actor );
	}
}

function flxg_after_task_change( $task_id, $logs, $newly_assigned, $uid, $before ) {
	if ( $newly_assigned ) {
		flxg_notify( $newly_assigned, 'assigned', $task_id, array(), $uid );
	}
	foreach ( $logs as $l ) {
		if ( 'status' === $l[0] ) {
			$task = flxg_get_task_row( $task_id );
			$list = flxg_get_list( (int) $task['list_id'] );
			$to   = flxg_status_info( $list, $l[1]['to'] );
			flxg_notify( flxg_watchers( $task_id ), 'status', $task_id, array( 'to' => $l[1]['to'], 'label' => $to['label'] ?? $l[1]['to'], 'color' => $to['color'] ?? '' ), $uid );
		}
	}
}

function flxg_after_comment( $task_id, $comment_id, $body, $uid ) {
	$mentioned = array_values( array_intersect( flxg_mentions( $body ), array_keys( flxg_users() ) ) );
	$excerpt   = flxg_comment_excerpt( $body );
	if ( $mentioned ) {
		flxg_notify( $mentioned, 'mention', $task_id, array( 'comment' => $comment_id, 'excerpt' => $excerpt ), $uid );
		foreach ( $mentioned as $m ) {
			if ( flxg_can_task( $task_id, 'view', $m ) ) {
				flxg_add_watcher( $task_id, $m );
			}
		}
	}
	$others = array_diff( flxg_watchers( $task_id ), $mentioned );
	flxg_notify( $others, 'comment', $task_id, array( 'comment' => $comment_id, 'excerpt' => $excerpt ), $uid );
}

/** Bahsetmeleri adlara çevrilmiş kısa metin. */
function flxg_comment_excerpt( $body, $len = 140 ) {
	$text = preg_replace_callback(
		'/@\{(\d+)\}/',
		function ( $m ) {
			return '@' . flxg_user_name( (int) $m[1] );
		},
		(string) $body
	);
	$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
	return mb_strlen( $text ) > $len ? mb_substr( $text, 0, $len - 1 ) . '…' : $text;
}

function flxg_notification_out( $r ) {
	$task = flxg_get_task_row( (int) $r['task_id'] );
	return array(
		'id'      => (int) $r['id'],
		'type'    => (string) $r['type'],
		'actor'   => (int) $r['actor_id'],
		'task'    => (int) $r['task_id'],
		'title'   => $task ? (string) $task['title'] : '',
		'list'    => $task ? (int) $task['list_id'] : 0,
		'data'    => flxg_json( $r['data'] ),
		'read'    => ! empty( $r['read_at'] ),
		'created' => flxg_iso( $r['created_at'] ),
	);
}

function flxg_get_notifications( $uid, $args = array() ) {
	global $wpdb;
	$where = array( $wpdb->prepare( 'user_id = %d', (int) $uid ) );
	if ( ! empty( $args['after'] ) ) {
		$where[] = $wpdb->prepare( 'id > %d', (int) $args['after'] );
	}
	if ( ! empty( $args['before'] ) ) {
		$where[] = $wpdb->prepare( 'id < %d', (int) $args['before'] );
	}
	if ( ! empty( $args['unread'] ) ) {
		$where[] = 'read_at IS NULL';
	}
	$limit = max( 1, min( 100, (int) ( $args['limit'] ?? 30 ) ) );
	$rows  = $wpdb->get_results( 'SELECT * FROM ' . flxg_table( 'notifications' ) . ' WHERE ' . implode( ' AND ', $where ) . " ORDER BY id DESC LIMIT $limit", ARRAY_A ); // phpcs:ignore
	return array_map( 'flxg_notification_out', (array) $rows );
}

function flxg_unread_count( $uid ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxg_table( 'notifications' ) . ' WHERE user_id = %d AND read_at IS NULL', (int) $uid ) ); // phpcs:ignore
}

function flxg_mark_read( $uid, $ids = null, $task = 0 ) {
	global $wpdb;
	$t = flxg_table( 'notifications' );
	if ( $task ) {
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET read_at = %s WHERE user_id = %d AND task_id = %d AND read_at IS NULL", flxg_now(), (int) $uid, (int) $task ) ); // phpcs:ignore
	} elseif ( null === $ids ) {
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET read_at = %s WHERE user_id = %d AND read_at IS NULL", flxg_now(), (int) $uid ) ); // phpcs:ignore
	} else {
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET read_at = %s WHERE user_id = %d AND id IN (" . flxg_ids_in( (array) $ids ) . ')', flxg_now(), (int) $uid ) ); // phpcs:ignore
	}
}

/* ---------------- E-posta ---------------- */

function flxg_user_wants_email( $uid ) {
	if ( ! flxg_setting( 'email_notify' ) ) {
		return false;
	}
	$pref = get_user_meta( (int) $uid, 'flxg_email', true );
	return '0' !== (string) $pref;
}

function flxg_notification_text( $type, $data, $actor_name ) {
	switch ( $type ) {
		case 'assigned':
			/* translators: %s: person */
			return sprintf( __( '%s assigned you to a task', 'flx-tasks' ), $actor_name );
		case 'mention':
			/* translators: %s: person */
			return sprintf( __( '%s mentioned you in a comment', 'flx-tasks' ), $actor_name );
		case 'comment':
			/* translators: %s: person */
			return sprintf( __( '%s wrote a comment', 'flx-tasks' ), $actor_name );
		case 'status':
			/* translators: 1: person, 2: status */
			return sprintf( __( '%1$s changed the status to "%2$s"', 'flx-tasks' ), $actor_name, $data['label'] ?? '' );
		case 'due':
			return __( 'Due tomorrow', 'flx-tasks' );
		case 'overdue':
			return __( 'Overdue', 'flx-tasks' );
	}
	return '';
}

function flxg_maybe_email( $uid, $type, $task_id, $data, $actor ) {
	if ( ! in_array( $type, array( 'assigned', 'mention', 'due' ), true ) || ! flxg_user_wants_email( $uid ) ) {
		return;
	}
	$user = get_userdata( $uid );
	$task = flxg_get_task_row( $task_id );
	if ( ! $user || ! $user->user_email || ! $task ) {
		return;
	}
	$switched = switch_to_user_locale( $uid );
	$line     = flxg_notification_text( $type, $data, $actor ? flxg_user_name( $actor ) : flxg_brand_name() );
	/* translators: 1: site, 2: task title */
	$subject = sprintf( __( '[%1$s] %2$s', 'flx-tasks' ), flxg_brand_name(), $task['title'] );
	$body    = $line . "\n\n" . $task['title'] . "\n";
	if ( ! empty( $data['excerpt'] ) ) {
		$body .= "\n“" . $data['excerpt'] . "”\n";
	}
	$body .= "\n" . flxg_app_url( $task_id ) . "\n\n— " . __( 'You can turn off email notifications under Flux Tasks › Profile.', 'flx-tasks' );
	$list = flxg_get_list( (int) $task['list_id'] );
	$vars = array(
		'ad'     => flxg_user_name( $uid ),
		'kisi'   => $actor ? flxg_user_name( $actor ) : flxg_brand_name(),
		'gorev'  => $task['title'],
		'liste'  => $list ? $list['name'] : '',
		'mesaj'  => $line,
		'alinti' => (string) ( $data['excerpt'] ?? '' ),
		'tarih'  => $task['due_date'] ? flx_date( strtotime( $task['due_date'] ) ) : '',
		'link'   => flxg_app_url( $task_id ),
	);
	if ( $switched ) {
		restore_previous_locale();
	}
	// Flux Mail varsa kayıtlı, düzenlenebilir kurumsal şablonla; yoksa düz wp_mail.
	if ( function_exists( 'flxi_send_email' ) ) {
		flxi_send_email(
			array(
				'to'       => $user->user_email,
				'template' => 'flx-tasks/' . $type,
				'vars'     => $vars,
			)
		);
		return;
	}
	wp_mail( $user->user_email, $subject, $body );
}

/* ---------------- Flux Mail e-posta şablonları ---------------- */

add_filter( 'flxi_email_types', 'flxg_email_types' );
function flxg_email_types( $types ) {
	$vars   = array(
		'ad'     => __( 'Recipient name', 'flx-tasks' ),
		'kisi'   => __( 'Person who acted', 'flx-tasks' ),
		'gorev'  => __( 'Task name', 'flx-tasks' ),
		'liste'  => __( 'List', 'flx-tasks' ),
		'mesaj'  => __( 'Notification sentence', 'flx-tasks' ),
		'alinti' => __( 'Comment excerpt', 'flx-tasks' ),
		'tarih'  => __( 'Due date', 'flx-tasks' ),
		'link'   => __( 'Task link', 'flx-tasks' ),
	);
	$sample = array(
		'ad'     => 'Ayşe',
		'kisi'   => 'Mehmet',
		'gorev'  => __( 'Booth supplies list', 'flx-tasks' ),
		'liste'  => __( 'Tasks', 'flx-tasks' ),
		'mesaj'  => __( 'Mehmet assigned you to a task', 'flx-tasks' ),
		'alinti' => __( 'Can you look at it by Friday?', 'flx-tasks' ),
		'tarih'  => flx_date( time() + DAY_IN_SECONDS ),
		'link'   => admin_url( 'admin.php?page=flx-tasks' ),
	);
	$foot   = '<p style="color:#6b7280;font-size:13px">' . __( 'You can turn off email notifications under Flux Tasks › Profile.', 'flx-tasks' ) . '</p>';
	$base   = array(
		'group'       => 'Flux Tasks',
		'recipient'   => 'user',
		'vars'        => $vars,
		'sample'      => $sample,
		'can_disable' => true,
	);
	$types['flx-tasks/assigned'] = $base + array(
		'label'       => __( 'You were assigned to a task', 'flx-tasks' ),
		'description' => __( 'Sent when someone assigns you to a task.', 'flx-tasks' ),
		'defaults'    => array(
			'subject'     => __( '{kisi} assigned you to a task: {gorev}', 'flx-tasks' ),
			'heading'     => __( 'New task: {gorev}', 'flx-tasks' ),
			'body'        => '<p>' . __( 'Hi {ad},', 'flx-tasks' ) . '</p><p>' . __( '{kisi} assigned you to <strong>{gorev}</strong> in the <strong>{liste}</strong> list.', 'flx-tasks' ) . '</p>' . $foot,
			'button_text' => __( 'Open task', 'flx-tasks' ),
			'button_url'  => '{link}',
			'sender'      => 'noreply',
			'enabled'     => 1,
		),
	);
	$types['flx-tasks/mention']  = $base + array(
		'label'       => __( 'You were mentioned in a comment', 'flx-tasks' ),
		'description' => __( 'Sent when someone @mentions you in a comment.', 'flx-tasks' ),
		'defaults'    => array(
			'subject'     => __( '{kisi} mentioned you in a comment: {gorev}', 'flx-tasks' ),
			'heading'     => __( '{kisi} mentioned you', 'flx-tasks' ),
			'body'        => '<p>' . __( 'Hi {ad},', 'flx-tasks' ) . '</p><p>' . __( 'Comment on <strong>{gorev}</strong>:', 'flx-tasks' ) . '</p><blockquote style="margin:0;padding:8px 14px;border-left:3px solid #2e7d32;color:#374151">{alinti}</blockquote>' . $foot,
			'button_text' => __( 'Go to comment', 'flx-tasks' ),
			'button_url'  => '{link}',
			'sender'      => 'noreply',
			'enabled'     => 1,
		),
	);
	$types['flx-tasks/due']      = $base + array(
		'label'       => __( 'Task due date is coming up', 'flx-tasks' ),
		'description' => __( 'Sent one day before the due date of a task assigned to you.', 'flx-tasks' ),
		'defaults'    => array(
			'subject'     => __( 'Due tomorrow: {gorev}', 'flx-tasks' ),
			'heading'     => __( 'Due tomorrow', 'flx-tasks' ),
			'body'        => '<p>' . __( 'Hi {ad},', 'flx-tasks' ) . '</p><p>' . __( '<strong>{gorev}</strong> is due on {tarih}.', 'flx-tasks' ) . '</p>' . $foot,
			'button_text' => __( 'Open task', 'flx-tasks' ),
			'button_url'  => '{link}',
			'sender'      => 'noreply',
			'enabled'     => 1,
		),
	);
	return $types;
}

/* ---------------- Günlük hatırlatma ---------------- */

add_action( 'flxg_daily', 'flxg_due_reminders' );
function flxg_due_reminders() {
	global $wpdb;
	$tomorrow = wp_date( 'Y-m-d', time() + DAY_IN_SECONDS );
	$today    = flxg_today();
	$t        = flxg_table( 'tasks' );
	$a        = flxg_table( 'assignees' );
	$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT t.id, t.list_id, t.status, t.due_date, a.user_id FROM $t t JOIN $a a ON a.task_id = t.id WHERE t.archived = 0 AND t.due_date <> '' AND t.due_date <= %s AND t.due_date >= %s", $tomorrow . 'T99', wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ) ), ARRAY_A ); // phpcs:ignore
	foreach ( (array) $rows as $r ) {
		$list = flxg_get_list( (int) $r['list_id'] );
		$info = $list ? flxg_status_info( $list, $r['status'] ) : null;
		if ( ! $info || 'done' === $info['type'] ) {
			continue;
		}
		$day  = substr( $r['due_date'], 0, 10 );
		$type = $day === $tomorrow ? 'due' : ( $day < $today ? 'overdue' : '' );
		if ( $type ) {
			flxg_notify( array( (int) $r['user_id'] ), $type, (int) $r['id'], array( 'due' => $r['due_date'] ), 0 );
		}
	}
	// 90 günden eski okunmuş bildirimler silinir.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . flxg_table( 'notifications' ) . ' WHERE read_at IS NOT NULL AND created_at < %s', gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ) ); // phpcs:ignore
}
