<?php
/**
 * Görev bildirimleri (0.12.0): görev bitince e-posta (Flux Mail, yoksa wp_mail), WhatsApp (Flux Mail) ve
 * yönetim paneli rozeti. Mod: none | important | always.
 * "important": çalışmada model flxai_task_notify aracını çağırdıysa, işlem onay bekliyorsa ya da hata olduysa.
 * Ayar görevin meta.notify alanında: mode, email (bool), whatsapp (numara, boş = kapalı), panel (bool),
 * to (0.14.0): ek alıcılar; Flux CRM kişisi, kullanıcı, e-posta, telefon ya da WhatsApp grubu, her biri için kanallar
 * (email, sms, whatsapp). Gönderim Flux Mail üzerinden.
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
		'to'       => flxai_notify_to_clean( (array) ( $n['to'] ?? array() ) ),
	);
}

/** Ek bildirim alıcılarını temizler (en fazla 20). */
function flxai_notify_to_clean( array $list ) {
	$out  = array();
	$seen = array();
	foreach ( $list as $r ) {
		if ( ! is_array( $r ) ) {
			continue;
		}
		$kind = sanitize_key( (string) ( $r['kind'] ?? '' ) );
		if ( ! in_array( $kind, array( 'contact', 'user', 'email', 'phone', 'group' ), true ) ) {
			continue;
		}
		$email = sanitize_email( (string) ( $r['email'] ?? '' ) );
		$phone = preg_replace( '/\D+/', '', (string) ( $r['phone'] ?? '' ) );
		$jid   = (string) ( $r['jid'] ?? '' );
		$jid   = preg_match( '/^[0-9-]{6,40}@g\.us$/', $jid ) ? $jid : '';
		$ch    = array_values( array_intersect( array( 'email', 'sms', 'whatsapp' ), array_map( 'sanitize_key', (array) ( $r['channels'] ?? $r['ch'] ?? array() ) ) ) );
		if ( 'group' === $kind ) {
			$ch    = $jid ? array( 'whatsapp' ) : array();
			$email = '';
			$phone = '';
		}
		$phone = strlen( $phone ) >= 8 ? substr( $phone, 0, 20 ) : '';
		$ch    = array_values(
			array_filter(
				$ch,
				function ( $c ) use ( $email, $phone, $jid, $kind ) {
					return 'email' === $c ? ( '' !== $email || in_array( $kind, array( 'contact', 'user' ), true ) ) : ( '' !== $phone || '' !== $jid || 'contact' === $kind );
				}
			)
		);
		$id = (int) ( $r['id'] ?? 0 );
		if ( ! $ch || ( in_array( $kind, array( 'contact', 'user' ), true ) && ! $id ) ) {
			continue;
		}
		$key = $kind . ':' . ( $id ? $id : $email . $phone . $jid );
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = 1;
		$name         = mb_substr( trim( wp_strip_all_tags( (string) ( $r['name'] ?? '' ) ) ), 0, 120 );
		$out[]        = array(
			'kind'     => $kind,
			'id'       => $id,
			'name'     => '' !== $name ? $name : ( $email ? $email : ( $phone ? '+' . $phone : $jid ) ),
			'email'    => $email,
			'phone'    => $phone,
			'jid'      => $jid,
			'channels' => $ch,
		);
		if ( count( $out ) >= 20 ) {
			break;
		}
	}
	return $out;
}

/** Kullanılabilir bildirim kanalları (Flux Mail bağlantılarına göre). */
function flxai_notify_channels() {
	$ok = function ( $c ) {
		return function_exists( 'flxi_connection_choices' ) && (bool) flxi_connection_choices( $c );
	};
	return array(
		'email'    => true,
		'sms'      => function_exists( 'flxi_send_message' ) && $ok( 'sms' ),
		'whatsapp' => function_exists( 'flxi_send_message' ) && $ok( 'whatsapp' ),
	);
}

/**
 * Bildirim alıcısı arar: Flux CRM kişileri, site kullanıcıları ve WhatsApp grupları (Flux Chat).
 *
 * @return array[] kind, id, name, email, phone, jid, sub
 */
function flxai_notify_find( $q, $limit = 12 ) {
	global $wpdb;
	$q   = trim( (string) $q );
	$out = array();
	if ( class_exists( 'FLXCRM_Contacts' ) && ( current_user_can( 'flxcrm_view' ) || current_user_can( 'manage_options' ) ) ) {
		$res = FLXCRM_Contacts::query(
			array(
				's'        => $q,
				'per_page' => 8,
				'orderby'  => 'last_activity_at',
			)
		);
		foreach ( $res['rows'] as $c ) {
			$out[] = array(
				'kind'  => 'contact',
				'id'    => (int) $c['id'],
				'name'  => (string) ( '' !== (string) $c['name'] ? $c['name'] : ( $c['email'] ? $c['email'] : '+' . $c['phone'] ) ),
				'email' => (string) $c['email'],
				'phone' => (string) $c['phone'],
				'jid'   => '',
				'sub'   => trim( implode( ' · ', array_filter( array( (string) $c['email'], $c['phone'] ? '+' . $c['phone'] : '' ) ) ) ),
			);
		}
	}
	if ( current_user_can( 'list_users' ) ) {
		$users = get_users(
			array(
				'search'         => '' !== $q ? '*' . $q . '*' : '',
				'search_columns' => array( 'display_name', 'user_email', 'user_login' ),
				'number'         => 5,
				'role__not_in'   => array( 'customer', 'subscriber' ),
			)
		);
		foreach ( $users as $u ) {
			$out[] = array(
				'kind'  => 'user',
				'id'    => (int) $u->ID,
				'name'  => $u->display_name,
				'email' => $u->user_email,
				'phone' => preg_replace( '/\D+/', '', (string) get_user_meta( $u->ID, 'billing_phone', true ) ),
				'jid'   => '',
				'sub'   => $u->user_email,
			);
		}
	}
	if ( function_exists( 'flxc_table' ) && ( current_user_can( 'flxc_use' ) || current_user_can( 'manage_options' ) ) ) {
		$t    = flxc_table( 'chats' );
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT jid, name, push_name FROM {$t} WHERE jid LIKE %s AND (name LIKE %s OR push_name LIKE %s) ORDER BY id DESC LIMIT 8", '%@g.us', $like, $like ), ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as $g ) {
			$out[] = array(
				'kind'  => 'group',
				'id'    => 0,
				'name'  => (string) ( '' !== (string) $g['name'] ? $g['name'] : ( $g['push_name'] ? $g['push_name'] : $g['jid'] ) ),
				'email' => '',
				'phone' => '',
				'jid'   => (string) $g['jid'],
				'sub'   => __( 'WhatsApp group', 'flx-ai' ),
			);
		}
	}
	if ( is_email( $q ) ) {
		$out[] = array( 'kind' => 'email', 'id' => 0, 'name' => $q, 'email' => $q, 'phone' => '', 'jid' => '', 'sub' => __( 'E-mail address', 'flx-ai' ) );
	} elseif ( strlen( preg_replace( '/\D+/', '', $q ) ) >= 10 && ! preg_match( '/[a-z]/i', $q ) ) {
		$d     = preg_replace( '/\D+/', '', $q );
		$out[] = array( 'kind' => 'phone', 'id' => 0, 'name' => '+' . $d, 'email' => '', 'phone' => $d, 'jid' => '', 'sub' => __( 'Phone number', 'flx-ai' ) );
	}
	return array_slice( $out, 0, max( 1, (int) $limit ) );
}

/** Alıcının güncel e-posta/telefonu (CRM kişisi ve kullanıcı her gönderimde yeniden okunur). */
function flxai_notify_resolve( array $r ) {
	if ( 'contact' === $r['kind'] && class_exists( 'FLXCRM_Contacts' ) ) {
		$c = FLXCRM_Contacts::get( $r['id'] );
		if ( ! $c ) {
			return null;
		}
		$r['email'] = (string) $c['email'];
		$r['phone'] = (string) $c['phone'];
	} elseif ( 'user' === $r['kind'] ) {
		$u = get_userdata( $r['id'] );
		if ( ! $u ) {
			return null;
		}
		$r['email'] = $u->user_email;
		if ( '' === $r['phone'] ) {
			$r['phone'] = preg_replace( '/\D+/', '', (string) get_user_meta( $u->ID, 'billing_phone', true ) );
		}
	}
	return $r;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'flx-ai/v1',
			'/notify/recipients',
			array(
				'methods'             => 'GET',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ) || ( function_exists( 'flxai_can_chat' ) && flxai_can_chat() );
				},
				'callback'            => function ( WP_REST_Request $req ) {
					nocache_headers();
					return array(
						'items'    => flxai_notify_find( (string) $req->get_param( 'q' ) ),
						'channels' => flxai_notify_channels(),
					);
				},
			)
		);
	}
);

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

/* Sohbet + MCP: görev bildirimine alıcı bulmak için. */
add_filter(
	'flx_mcp_tools',
	function ( $tools ) {
		$tools                                  = is_array( $tools ) ? $tools : array();
		$tools['flxai_notify_find_recipients'] = array(
			'title'       => __( 'Find notification recipients', 'flx-ai' ),
			'description' => 'Zamanlanmış görev bildirimine eklenecek alıcıları arar: Flux CRM kişileri, site kullanıcıları ve WhatsApp grupları (Flux Chat). q boşsa son etkin kişiler. Dönen kayıtları flxai_task_create/flxai_task_update içindeki notify_to alanına (kind, id, name, email, phone, jid, channels) olduğu gibi koy; kanalları kullanıcıya sor. channels hangi kanalların bu sitede kullanılabildiğini gösterir.',
			'readonly'    => true,
			'cap'         => 'read',
			'log'         => false,
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'q' => array(
						'type'        => 'string',
						'description' => 'Ad, e-posta, telefon ya da grup adı.',
					),
				),
			),
			'run'         => function ( $a ) {
				return array(
					'items'    => flxai_notify_find( (string) ( $a['q'] ?? '' ) ),
					'channels' => flxai_notify_channels(),
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
	$link  = admin_url( 'admin.php?page=flx-ai-tasks&open=' . (int) $task['id'] );
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
	foreach ( $cfg['to'] as $r ) {
		$r = flxai_notify_resolve( $r );
		if ( ! $r ) {
			continue;
		}
		$staff = 'user' === $r['kind'];
		$body  = $task['title'] . "\n" . $text . ( $staff ? "\n\n" . $link : '' );
		foreach ( $r['channels'] as $ch ) {
			$ok = false;
			if ( 'email' === $ch && is_email( $r['email'] ) ) {
				if ( function_exists( 'flxi_send_email' ) ) {
					$ok = (bool) flxi_send_email(
						array(
							'to'       => $r['email'],
							'template' => 'flx-ai/task_report',
							'vars'     => array(
								'gorev'     => $task['title'],
								'ozet'      => $text,
								'ozet_html' => nl2br( esc_html( $text ) ),
								'durum'     => $label,
								'link'      => $staff ? $link : home_url( '/' ),
							),
						)
					);
				} else {
					/* translators: %s: task name */
					$ok = wp_mail( $r['email'], sprintf( __( 'AI task: %s', 'flx-ai' ), $task['title'] ), $body );
				}
			} elseif ( in_array( $ch, array( 'sms', 'whatsapp' ), true ) && function_exists( 'flxi_send_message' ) ) {
				$to = 'whatsapp' === $ch && '' !== $r['jid'] ? $r['jid'] : $r['phone'];
				if ( '' !== $to ) {
					$res = flxi_send_message( $ch, $to, 'whatsapp' === $ch ? '*' . $task['title'] . "*\n" . $text . ( $staff ? "\n\n" . $link : '' ) : $body, array( 'source' => 'flx-ai' ) );
					$ok  = ! empty( $res['ok'] );
				}
			}
			if ( $ok ) {
				$sent[] = $ch . ':' . $r['name'];
			}
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
		if ( 'none' === $cfg['mode'] || ( ! $cfg['email'] && ! $cfg['panel'] && '' === $cfg['whatsapp'] && ! $cfg['to'] ) ) {
			return;
		}
		$run   = (array) ( $task['meta']['history'][0] ?? array() );
		$chat  = ! empty( $run['chat'] ) ? flxai_chat_get( (int) $run['chat'], $task['user_id'] ) : null;
		$notes = $chat ? flxai_task_run_notes( $chat, $run ) : array();
		// Çalışmada site dosyaları için açılan değişiklik isteği (PR) de onay bekler.
		if ( 'ok' === $state && $chat && function_exists( 'flxai_change_open_pr' ) && flxai_change_open_pr( (int) $chat['id'] ) ) {
			$state = 'approval';
		}
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
