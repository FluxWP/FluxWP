<?php
/**
 * Görev bildirimleri (0.12.0): görev bitince e-posta (Flux Mail, yoksa wp_mail), WhatsApp (Flux Mail) ve
 * yönetim paneli rozeti. Mod: none | important | always.
 * "important": çalışmada model flxai_task_notify aracını çağırdıysa, işlem onay bekliyorsa ya da hata olduysa.
 * Ayar görevin meta.notify alanında: mode, email (bool), whatsapp (numara, boş = kapalı), panel (bool).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

function flxai_task_notify_clean( array $n ) {
	$mode = (string) ( $n['mode'] ?? 'none' );
	$wa   = preg_replace( '/\D+/', '', (string) ( $n['whatsapp'] ?? '' ) );
	return array(
		'mode'     => in_array( $mode, array( 'none', 'important', 'always' ), true ) ? $mode : 'none',
		'email'    => ! empty( $n['email'] ),
		'whatsapp' => strlen( $wa ) >= 8 ? substr( $wa, 0, 20 ) : '',
		'panel'    => ! empty( $n['panel'] ),
	);
}

function flxai_task_notify_cfg( array $t ) {
	return flxai_task_notify_clean( (array) ( $t['meta']['notify'] ?? array() ) );
}

/** Görev notuna eklenen bildirim talimatı (modele). */
function flxai_task_notify_hint( $task_id ) {
	$t = $task_id ? flxai_task_get( $task_id ) : null;
	if ( ! $t ) {
		return '';
	}
	$mode = flxai_task_notify_cfg( $t )['mode'];
	if ( 'important' === $mode ) {
		return ' Kullanıcı yalnız önemli bir şey olunca bildirim almak istiyor: hemen bilmesi gereken bir durum (ani düşüş, hata, acil mesaj, bekleyen önemli iş) varsa flxai_task_notify ile tek-iki cümlelik bildirim bırak; yoksa bu aracı çağırma.';
	}
	if ( 'always' === $mode ) {
		return ' Raporun özeti kullanıcıya bildirim olarak gider; istersen flxai_task_notify ile kısa, özel bir bildirim metni bırak.';
	}
	return '';
}

/** Sohbet kimliğinden görev. */
function flxai_task_by_chat( $chat_id ) {
	global $wpdb;
	if ( ! $chat_id ) {
		return null;
	}
	$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . flxai_table( 'tasks' ) . ' WHERE chat_id = %d LIMIT 1', (int) $chat_id ) ); // phpcs:ignore
	return $id ? flxai_task_get( $id ) : null;
}

/* Yalnız sohbette (MCP'de değil): görev çalışırken modelin bildirim bırakması. */
add_filter(
	'flxai_tools',
	function ( $tools ) {
		$tools                     = is_array( $tools ) ? $tools : array();
		$tools['flxai_task_notify'] = array(
			'title'       => __( 'Notify the task owner', 'flx-ai' ),
			'description' => 'Yalnız zamanlanmış görev çalışırken: görev sahibine kısa bir bildirim bırakır (görevin bildirim ayarındaki kanallardan gider). Önemli bir durum yoksa çağırma.',
			'readonly'    => true,
			'cap'         => 'read',
			'source'      => 'flx-ai',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'message' => array(
						'type'        => 'string',
						'description' => 'Bildirim metni, bir-iki cümle, düz metin.',
					),
				),
				'required'   => array( 'message' ),
			),
			'run'         => function ( $a, $ctx = array() ) {
				$task = flxai_task_by_chat( (int) ( $ctx['chat_id'] ?? 0 ) );
				if ( ! $task ) {
					throw new Exception( __( 'This tool only works while a scheduled task is running.', 'flx-ai' ) );
				}
				$mode = flxai_task_notify_cfg( $task )['mode'];
				return array(
					'noted'  => true,
					'sent'   => 'none' !== $mode ? 'görev bitince' : 'bildirim kapalı; yalnız raporda görünür',
				);
			},
		);
		return $tools;
	}
);

/* Flux Mail şablonu. */
add_filter(
	'flxi_email_types',
	function ( $types ) {
		$types                     = is_array( $types ) ? $types : array();
		$types['flx-ai/task_report'] = array(
			'label'       => __( 'AI task report', 'flx-ai' ),
			'group'       => 'Flux AI & MCP',
			'description' => __( 'Sent to the task owner when a scheduled AI task has something to report.', 'flx-ai' ),
			'recipient'   => 'user',
			'vars'        => array(
				'gorev' => __( 'Task name', 'flx-ai' ),
				'ozet'  => __( 'Report summary', 'flx-ai' ),
				'durum' => __( 'Status', 'flx-ai' ),
				'link'  => __( 'Report link', 'flx-ai' ),
			),
			'sample'      => array(
				'gorev' => __( 'Daily AI briefing', 'flx-ai' ),
				'ozet'  => __( 'Yesterday: 12 donations, 4,350 TL in total. 2 receipts are waiting for approval.', 'flx-ai' ),
				'durum' => __( 'Completed', 'flx-ai' ),
				'link'  => admin_url( 'admin.php?page=flx-ai-tasks' ),
			),
			'defaults'    => array(
				/* translators: {gorev} is a template variable, keep it as is */
				'subject'     => __( 'AI task: {gorev}', 'flx-ai' ),
				'heading'     => '{gorev}',
				'body'        => '<p>{ozet_html}</p><p><small>{durum}</small></p>',
				'button_text' => __( 'Open the report', 'flx-ai' ),
				'button_url'  => '{link}',
				'sender'      => 'noreply',
				'enabled'     => 1,
			),
			'can_disable' => true,
		);
		return $types;
	}
);

/** Bir çalışmanın modelin bıraktığı bildirim metinleri. */
function flxai_task_run_notes( array $chat, array $run ) {
	$out  = array();
	$from = (int) ( $run['from'] ?? -1 );
	$to   = (int) ( $run['to'] ?? -1 );
	if ( $from < 0 ) {
		return $out;
	}
	foreach ( array_slice( $chat['messages'], $from, max( 0, $to - $from ) ) as $m ) {
		if ( 'tool' === ( $m['role'] ?? '' ) && 'flxai_task_notify' === ( $m['_name'] ?? '' ) && ! empty( $m['_ok'] ) ) {
			$t = trim( (string) ( $m['_args']['message'] ?? '' ) );
			if ( '' !== $t ) {
				$out[] = mb_substr( $t, 0, 600 );
			}
		}
	}
	return $out;
}

/**
 * Bildirimi gönderir.
 *
 * @return string[] Gönderilen kanallar.
 */
function flxai_task_send_notice( array $task, $state, $text ) {
	$cfg   = flxai_task_notify_cfg( $task );
	$owner = get_userdata( $task['user_id'] );
	$link  = admin_url( 'admin.php?page=flx-ai-tasks&history=' . (int) $task['id'] );
	$label = array(
		'ok'       => __( 'Completed', 'flx-ai' ),
		'approval' => __( 'Awaiting approval', 'flx-ai' ),
		'error'    => __( 'Error', 'flx-ai' ),
	)[ $state ] ?? $state;
	$sent  = array();
	if ( $cfg['email'] && $owner && is_email( $owner->user_email ) ) {
		$ok = false;
		if ( function_exists( 'flxi_send_email' ) ) {
			$ok = (bool) flxi_send_email(
				array(
					'to'       => $owner->user_email,
					'template' => 'flx-ai/task_report',
					'vars'     => array(
						'gorev'     => $task['title'],
						'ozet'      => $text,
						'ozet_html' => nl2br( esc_html( $text ) ),
						'durum'     => $label,
						'link'      => $link,
					),
				)
			);
		} else {
			/* translators: %s: task name */
			$ok = wp_mail( $owner->user_email, sprintf( __( 'AI task: %s', 'flx-ai' ), $task['title'] ), $text . "\n\n" . $label . "\n" . $link );
		}
		if ( $ok ) {
			$sent[] = 'email';
		}
	}
	if ( '' !== $cfg['whatsapp'] && function_exists( 'flxi_send_message' ) ) {
		$r = flxi_send_message( 'whatsapp', $cfg['whatsapp'], '*' . $task['title'] . "*\n" . $text . "\n\n" . $link, array( 'source' => 'flx-ai' ) );
		if ( ! empty( $r['ok'] ) ) {
			$sent[] = 'whatsapp';
		}
	}
	if ( $cfg['panel'] ) {
		$u = (array) get_user_meta( $task['user_id'], 'flxai_task_unread', true );
		$u = array_filter( $u );
		$u[ (int) $task['id'] ] = time();
		update_user_meta( $task['user_id'], 'flxai_task_unread', $u );
		$sent[] = 'panel';
	}
	/**
	 * Görev bildirimi gönderildi (başka kanallar için).
	 *
	 * @param array  $task  Görev.
	 * @param string $state ok|approval|error.
	 * @param string $text  Bildirim metni.
	 * @param array  $sent  Gönderilen kanallar.
	 */
	do_action( 'flxai_task_notified', $task, $state, $text, $sent );
	return $sent;
}

add_action(
	'flxai_task_ran',
	function ( $id, $state ) {
		if ( ! in_array( $state, array( 'ok', 'approval', 'error' ), true ) ) {
			return;
		}
		$task = flxai_task_get( $id );
		if ( ! $task ) {
			return;
		}
		$cfg = flxai_task_notify_cfg( $task );
		if ( 'none' === $cfg['mode'] || ( ! $cfg['email'] && ! $cfg['panel'] && '' === $cfg['whatsapp'] ) ) {
			return;
		}
		$run   = (array) ( $task['meta']['history'][0] ?? array() );
		$chat  = ! empty( $run['chat'] ) ? flxai_chat_get( (int) $run['chat'], $task['user_id'] ) : null;
		$notes = $chat ? flxai_task_run_notes( $chat, $run ) : array();
		if ( 'important' === $cfg['mode'] && ! $notes && 'ok' === $state ) {
			return;
		}
		if ( $notes ) {
			$text = implode( "\n", $notes );
		} elseif ( 'error' === $state ) {
			/* translators: %s: error message */
			$text = sprintf( __( 'The task could not finish: %s', 'flx-ai' ), (string) $task['last_error'] );
		} else {
			$text = (string) ( $task['meta']['summary'] ?? '' );
		}
		if ( 'approval' === $state ) {
			$text = __( 'An action is waiting for your approval.', 'flx-ai' ) . ( '' !== $text ? "\n" . $text : '' );
		}
		$sent = flxai_task_send_notice( $task, $state, trim( $text ) );
		if ( $sent && isset( $task['meta']['history'][0] ) ) {
			$task['meta']['history'][0]['notified'] = $sent;
			flxai_task_patch( $task['id'], array( 'meta' => $task['meta'] ) );
		}
	},
	20,
	2
);

/** Kullanıcının okunmamış görev raporları: task_id => zaman. */
function flxai_tasks_unread( $user_id = 0 ) {
	$u = get_user_meta( $user_id ? $user_id : get_current_user_id(), 'flxai_task_unread', true );
	return is_array( $u ) ? array_filter( $u ) : array();
}

function flxai_task_mark_read( $task_id, $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$u       = flxai_tasks_unread( $user_id );
	if ( isset( $u[ (int) $task_id ] ) ) {
		unset( $u[ (int) $task_id ] );
		update_user_meta( $user_id, 'flxai_task_unread', $u );
	}
}
