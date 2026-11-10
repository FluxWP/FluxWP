<?php
/**
 * Asenkron gönderim kuyruğu.
 * Mesajlar wp_flxs_messages tablosunda tutulur (bekliyor / gönderiliyor / gönderildi / başarısız / iptal).
 * İşleyici: WooCommerce varsa Action Scheduler, yoksa WP-Cron (dakikada bir) + panel açıkken tarayıcı "pump".
 */
defined( 'ABSPATH' ) || exit;
FLX_Background_Pool::register( 'flxs_run_queue', 'flx-certificates' );

add_action( 'flxs_run_queue', 'flxs_run_queue' );
add_action( 'flxs_queue_tick', 'flxs_run_queue' );

function flxs_message_texts( $channel, $template = null ) {
	$m = $template ? $template['config']['messages'] : array();
	if ( 'email' === $channel ) {
		return array(
			'subject' => ! empty( $m['email_subject'] ) ? $m['email_subject'] : flxs_setting( 'email_subject' ),
			'body'    => ! empty( $m['email_body'] ) ? $m['email_body'] : flxs_setting( 'email_body' ),
		);
	}
	$key = 'sms' === $channel ? 'sms' : 'whatsapp';
	return array(
		'subject' => '',
		'body'    => ! empty( $m[ $key ] ) ? $m[ $key ] : flxs_setting( 'msg_' . $key ),
	);
}

/** Kanal için alıcı adresi (telefon/e-posta) */
function flxs_person_address( $person, $channel ) {
	if ( ! $person ) {
		return '';
	}
	return 'email' === $channel ? (string) $person['email'] : (string) $person['phone'];
}

/**
 * Sertifika için mesaj hazırlar.
 * @return array|WP_Error recipient, subject, body
 */
function flxs_build_certificate_message( $cert, $channel, $overrides = array() ) {
	$person   = flxs_get_person( $cert['person_id'] );
	$template = flxs_get_template( $cert['template_id'] );
	$to       = flxs_person_address( $person, $channel );
	$other    = isset( $overrides['to'] ) && '' !== trim( (string) $overrides['to'] );
	if ( $other ) {
		// Başka bir alıcıya (sertifika sahibi dışında)
		$to = 'email' === $channel ? strtolower( sanitize_email( $overrides['to'] ) ) : flxs_normalize_phone( $overrides['to'] );
		if ( ! $to || ( 'email' === $channel && ! is_email( $to ) ) ) {
			return new WP_Error( 'flxs_to', 'email' === $channel ? 'E-posta adresi geçersiz.' : 'Telefon numarası geçersiz. Örnek: 0532 123 45 67' );
		}
	}
	if ( ! $to ) {
		return new WP_Error( 'flxs_no_contact', 'email' === $channel ? 'Kişinin e-postası yok.' : 'Kişinin telefonu yok.' );
	}
	$texts = flxs_message_texts( $channel, $template );
	if ( ! empty( $overrides['subject'] ) ) {
		$texts['subject'] = $overrides['subject'];
	}
	if ( ! empty( $overrides['body'] ) ) {
		$texts['body'] = $overrides['body'];
	}
	$vars          = flxs_certificate_vars( $cert, $person, $template );
	$vars['buton'] = 'email' === $channel ? '{buton}' : $vars['link'];
	return array(
		'recipient'      => $to,
		'recipient_name' => $other ? ( '' !== trim( (string) ( $overrides['to_name'] ?? '' ) ) ? sanitize_text_field( $overrides['to_name'] ) : 'Başka alıcı' ) . ( $person ? ' (' . $person['name'] . ' sertifikası)' : '' ) : ( $person ? $person['name'] : '' ),
		'person_id'      => $other ? 0 : ( $person ? (int) $person['id'] : 0 ),
		'certificate_id' => (int) $cert['id'],
		'subject'        => trim( flxs_fill( $texts['subject'], $vars ) ),
		'body'           => trim( flxs_fill( $texts['body'], $vars ) ),
	);
}

/** Sağlayıcının "mesajlar arası bekleme" ayarına göre sıradaki gönderim zamanı */
function flxs_next_slot( $provider ) {
	$delay = (float) ( $provider['config']['delay'] ?? 0 );
	$now   = time();
	if ( $delay <= 0 ) {
		return $now;
	}
	$key  = 'flxs_slot_' . $provider['channel'] . '_' . (int) $provider['id'];
	$last = (int) get_option( $key, 0 );
	$slot = max( $now, $last + (int) ceil( $delay ) );
	update_option( $key, $slot, false );
	return $slot;
}

/**
 * Kuyruğa mesaj ekler.
 * @param array $m channel, recipient, body (+ subject, recipient_name, certificate_id, person_id, batch_id, source, provider_id)
 */
function flxs_enqueue( array $m ) {
	global $wpdb;
	$provider = flxs_resolve_provider( $m['channel'], (int) ( $m['provider_id'] ?? 0 ) );
	if ( ! $provider ) {
		return new WP_Error( 'flxs_no_provider', flxs_channel_label( $m['channel'] ) . ' için aktif sağlayıcı yok. Sertifikalar › Sağlayıcılar\'dan ekleyin.' );
	}
	$slot = flxs_next_slot( $provider );
	$ok   = $wpdb->insert(
		flxs_table( 'messages' ),
		array(
			'batch_id'       => (int) ( $m['batch_id'] ?? 0 ),
			'certificate_id' => (int) ( $m['certificate_id'] ?? 0 ),
			'person_id'      => (int) ( $m['person_id'] ?? 0 ),
			'channel'        => $m['channel'],
			'provider_id'    => (int) $provider['id'],
			'recipient'      => (string) $m['recipient'],
			'recipient_name' => (string) ( $m['recipient_name'] ?? '' ),
			'subject'        => mb_substr( (string) ( $m['subject'] ?? '' ), 0, 250 ),
			'body'           => (string) $m['body'],
			'status'         => 'queued',
			'attempts'       => 0,
			'max_attempts'   => max( 1, (int) flxs_setting( 'max_attempts' ) ),
			'scheduled_at'   => gmdate( 'Y-m-d H:i:s', $slot ),
			'source'         => (string) ( $m['source'] ?? 'manual' ),
			'created_by'     => get_current_user_id(),
			'created_at'     => flxs_now(),
			'updated_at'     => flxs_now(),
		)
	);
	if ( ! $ok ) {
		return new WP_Error( 'flxs_db', 'Kuyruğa eklenemedi: ' . $wpdb->last_error );
	}
	$id = (int) $wpdb->insert_id;
	if ( empty( $m['batch_id'] ) ) {
		flxs_log(
			'message_queued',
			'message',
			$id,
			sprintf( '%s → %s (%s)', flxs_channel_label( $m['channel'] ), $m['recipient_name'] ?? '', $provider['name'] ),
			array( 'certificate_id' => (int) ( $m['certificate_id'] ?? 0 ), 'recipient' => $m['recipient'], 'source' => $m['source'] ?? 'manual' )
		);
	}
	return $id;
}

/**
 * Sertifika linkini seçilen kanallardan gönderir (kuyruğa alır).
 * @return array channel => message id | WP_Error
 */
function flxs_send_certificate( $cert_id, array $channels, array $providers = array(), $source = 'manual', $batch_id = 0, $overrides = array() ) {
	$cert = is_array( $cert_id ) ? $cert_id : flxs_get_certificate( $cert_id );
	$out  = array();
	if ( ! $cert ) {
		return $out;
	}
	foreach ( $channels as $ch ) {
		if ( ! isset( flxs_channels()[ $ch ] ) ) {
			continue;
		}
		if ( 'revoked' === $cert['status'] ) {
			$out[ $ch ] = new WP_Error( 'flxs_revoked', 'Sertifika iptal edilmiş.' );
			continue;
		}
		$m = flxs_build_certificate_message( $cert, $ch, $overrides[ $ch ] ?? array() );
		if ( is_wp_error( $m ) ) {
			$out[ $ch ] = $m;
			continue;
		}
		$m['channel']     = $ch;
		$m['provider_id'] = (int) ( $providers[ $ch ] ?? 0 );
		$m['source']      = $source;
		$m['batch_id']    = $batch_id;
		$out[ $ch ]       = flxs_enqueue( $m );
	}
	return $out;
}

function flxs_create_batch( $name, $channel, $source, $meta = array() ) {
	global $wpdb;
	$wpdb->insert(
		flxs_table( 'batches' ),
		array(
			'name'       => sanitize_text_field( $name ),
			'channel'    => $channel,
			'source'     => $source,
			'total'      => 0,
			'meta'       => flxs_json( $meta ),
			'created_by' => get_current_user_id(),
			'created_at' => flxs_now(),
		)
	);
	return (int) $wpdb->insert_id;
}

/* ------------------------------------------------------------------ */
/* İşleyici                                                            */
/* ------------------------------------------------------------------ */

function flxs_kick_queue( $when = 0 ) {
	$when = max( time(), (int) $when );
	if ( flx_background_worker_active() ) {
		flx_background_schedule( 'flxs_run_queue', array(), max( 1, $when - time() ) );
		return 'wp-cron';
	}
	if ( function_exists( 'as_schedule_single_action' ) && function_exists( 'as_has_scheduled_action' ) && did_action( 'action_scheduler_init' ) ) {
		if ( ! as_has_scheduled_action( 'flxs_run_queue', array(), 'flx-certificates' ) ) {
			as_schedule_single_action( $when, 'flxs_run_queue', array(), 'flx-certificates' );
		}
		return 'action-scheduler';
	}
	if ( ! wp_next_scheduled( 'flxs_run_queue' ) ) {
		wp_schedule_single_event( $when, 'flxs_run_queue' );
	}
	if ( $when <= time() + 1 && ! wp_doing_cron() ) {
		spawn_cron();
	}
	return 'wp-cron';
}

function flxs_queue_runner_name() {
	if ( function_exists( 'as_schedule_single_action' ) ) {
		return 'Action Scheduler';
	}
	return ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? 'WP-Cron (sunucu cron\'u)' : 'WP-Cron';
}

function flxs_acquire_lock( $ttl = 90 ) {
	$token = flx_background_lock( 'certificate-messages', $ttl );
	if ( $token ) {
		$GLOBALS['flxs_queue_owner'] = $token;
	}
	return (bool) $token;
}

function flxs_release_lock() {
	flx_background_unlock( 'certificate-messages', $GLOBALS['flxs_queue_owner'] ?? '' );
	unset( $GLOBALS['flxs_queue_owner'] );
}

/**
 * Kuyruğu işler. Zaman bütçesi dolana ya da gönderilecek mesaj kalmayana kadar çalışır.
 * @return array işlem özeti
 */
function flxs_run_queue( $budget = null ) {
	global $wpdb;
	$t = flxs_table( 'messages' );
	if ( null === $budget || ! is_numeric( $budget ) ) {
		$budget = wp_doing_cron() ? 25 : 20;
	}
	return flx_background_run( 'certificate-messages', function () use ( $budget, $wpdb, $t ) {
		flxs_mark_queue_run();
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( min( 50, (int) $budget + 10 ) ); // phpcs:ignore
		}
		// Takılı kalan "gönderiliyor" kayıtlarını geri al
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET status = CASE WHEN attempts >= max_attempts THEN 'failed' ELSE 'queued' END, locked_at = NULL WHERE status = 'processing' AND locked_at < %s", gmdate( 'Y-m-d H:i:s', time() - 600 ) ) );

		$start     = microtime( true );
		$processed = 0;
		$sent      = 0;
		$failed    = 0;
		$limit     = max( 1, (int) flxs_setting( 'queue_batch' ) );
		while ( $processed < $limit ) {
			$left = $budget - ( microtime( true ) - $start );
			$timeout = (int) floor( $left - 5 );
			if ( $timeout < 5 ) {
				break;
			}
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE status = 'queued' AND scheduled_at <= %s ORDER BY scheduled_at ASC, id ASC LIMIT 1", flxs_now() ), ARRAY_A );
			if ( ! $row ) {
				$next = $wpdb->get_var( "SELECT MIN(scheduled_at) FROM $t WHERE status = 'queued'" );
				$wait = $next ? strtotime( $next . ' UTC' ) - time() : 999;
				if ( $wait > 0 && $wait < $timeout - 5 ) {
					sleep( (int) $wait );
					continue;
				}
				break;
			}
			$claimed = $wpdb->query( $wpdb->prepare( "UPDATE $t SET status = 'processing', locked_at = %s, attempts = attempts + 1, updated_at = %s WHERE id = %d AND status = 'queued'", flxs_now(), flxs_now(), $row['id'] ) );
			if ( 1 !== (int) $claimed ) {
				continue;
			}
			flx_background_log( 'certificate.message.claimed', array( 'plugin' => 'flx-certificates', 'item' => (int) $row['id'], 'attempt' => (int) $row['attempts'] + 1 ) );
			$row['attempts'] = (int) $row['attempts'] + 1;
			$GLOBALS['flxs_queue_timeout'] = min( 20, $timeout );
			try {
				$r = flxs_process_message( $row );
			} finally {
				unset( $GLOBALS['flxs_queue_timeout'] );
			}
			flx_background_log( 'certificate.message.result', array( 'plugin' => 'flx-certificates', 'item' => (int) $row['id'], 'attempt' => (int) $row['attempts'], 'state' => $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $t WHERE id=%d", $row['id'] ) ) ) );
			++$processed;
			$r ? ++$sent : ++$failed;
		}

		$next = $wpdb->get_var( "SELECT MIN(scheduled_at) FROM $t WHERE status = 'queued'" );
		if ( $next ) {
			flxs_kick_queue( strtotime( $next . ' UTC' ) );
		}
		$processing = $wpdb->get_var( "SELECT MIN(locked_at) FROM $t WHERE status = 'processing'" );
		if ( $processing ) { flxs_kick_queue( max( time() + 30, strtotime( $processing . ' UTC' ) + 600 ) ); }
		return array( 'processed' => $processed, 'sent' => $sent, 'failed' => $failed, 'pending' => (bool) ( $next || $processing ) );
	}, array(
		'ttl' => (int) $budget + 30,
		'hook' => 'flxs_run_queue',
		'on_defer' => static function () {
			flxs_kick_queue();
			return array( 'processed' => 0, 'sent' => 0, 'failed' => 0, 'pending' => true, 'deferred' => true );
		},
	) );
}

function flxs_process_message( $row ) {
	global $wpdb;
	$t        = flxs_table( 'messages' );
	$provider = $row['provider_id'] ? flxs_get_provider( $row['provider_id'] ) : ( 'email' === $row['channel'] ? flxs_wpmail_provider() : null );
	if ( ! $provider || ! $provider['active'] ) {
		$res = array( 'ok' => false, 'error' => 'Sağlayıcı silinmiş ya da pasif.', 'retry' => false );
	} else {
		$res = flxs_provider_send( $provider, $row );
	}
	$who   = (int) $row['created_by'];
	$label = sprintf( '%s → %s', flxs_channel_label( $row['channel'] ), $row['recipient_name'] ? $row['recipient_name'] : $row['recipient'] );
	$ctx   = array(
		'message_id'     => (int) $row['id'],
		'certificate_id' => (int) $row['certificate_id'],
		'batch_id'       => (int) $row['batch_id'],
		'recipient'      => $row['recipient'],
		'provider'       => $provider ? $provider['name'] : '',
		'attempt'        => (int) $row['attempts'],
	);

	if ( $res['ok'] ) {
		$wpdb->update(
			$t,
			array(
				'status'      => 'sent',
				'sent_at'     => flxs_now(),
				'locked_at'   => null,
				'last_error'  => null,
				'external_id' => (string) $res['external_id'],
				'response'    => flxs_json( $res['response'] ),
				'updated_at'  => flxs_now(),
			),
			array( 'id' => $row['id'] )
		);
		flxs_log( 'message_sent', 'message', $row['id'], $label, $ctx + array( 'external_id' => $res['external_id'] ), $who );
		do_action( 'flxs_message_sent', $row, $res );
		return true;
	}

	$can_retry = $res['retry'] && (int) $row['attempts'] < (int) $row['max_attempts'];
	$backoff   = array( 1 => 60, 2 => 300, 3 => 900, 4 => 1800 );
	$wpdb->update(
		$t,
		array(
			'status'       => $can_retry ? 'queued' : 'failed',
			'scheduled_at' => $can_retry ? gmdate( 'Y-m-d H:i:s', time() + ( $backoff[ (int) $row['attempts'] ] ?? 1800 ) ) : $row['scheduled_at'],
			'locked_at'    => null,
			'last_error'   => mb_substr( (string) $res['error'], 0, 1000 ),
			'response'     => flxs_json( $res['response'] ?? null ),
			'updated_at'   => flxs_now(),
		),
		array( 'id' => $row['id'] )
	);
	flxs_log( $can_retry ? 'message_retry' : 'message_failed', 'message', $row['id'], $label . ' · ' . $res['error'], $ctx, $who );
	do_action( 'flxs_message_failed', $row, $res, $can_retry );
	return false;
}

function flxs_get_message( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxs_table( 'messages' ) . ' WHERE id = %d', $id ), ARRAY_A );
}

function flxs_retry_message( $id ) {
	global $wpdb;
	$m = flxs_get_message( $id );
	if ( ! $m || ! in_array( $m['status'], array( 'failed', 'cancelled' ), true ) ) {
		return false;
	}
	$wpdb->update(
		flxs_table( 'messages' ),
		array( 'status' => 'queued', 'scheduled_at' => flxs_now(), 'max_attempts' => (int) $m['attempts'] + max( 1, (int) flxs_setting( 'max_attempts' ) ), 'updated_at' => flxs_now() ),
		array( 'id' => $id )
	);
	flxs_log( 'message_retry', 'message', $id, 'Elle yeniden deneme · ' . $m['recipient_name'], array( 'recipient' => $m['recipient'] ) );
	return true;
}

function flxs_cancel_message( $id ) {
	global $wpdb;
	$m = flxs_get_message( $id );
	if ( ! $m || 'queued' !== $m['status'] ) {
		return false;
	}
	$wpdb->update( flxs_table( 'messages' ), array( 'status' => 'cancelled', 'updated_at' => flxs_now() ), array( 'id' => $id, 'status' => 'queued' ) );
	flxs_log( 'message_cancelled', 'message', $id, $m['recipient_name'] . ' · ' . flxs_channel_label( $m['channel'] ), array( 'recipient' => $m['recipient'] ) );
	return true;
}

/** Kısıtlı kullanıcı için mesaj koşulu: kendi gönderdiği + görebildiği sertifikaların mesajları */
function flxs_messages_scope_sql() {
	global $wpdb;
	return $wpdb->prepare( '(created_by = %d OR certificate_id IN (SELECT id FROM ' . flxs_table( 'certificates' ) . ' WHERE ' . flxs_scope_sql( '' ) . '))', get_current_user_id() ); // phpcs:ignore
}

function flxs_queue_counts( $batch_id = 0, $scope = false ) {
	global $wpdb;
	$t     = flxs_table( 'messages' );
	$where = array();
	if ( $batch_id ) {
		$where[] = $wpdb->prepare( 'batch_id = %d', $batch_id );
	}
	if ( $scope && flxs_scoped() ) {
		$where[] = flxs_messages_scope_sql();
	}
	$sql = "SELECT status, COUNT(*) AS n FROM $t" . ( $where ? ' WHERE ' . implode( ' AND ', $where ) : '' ) . ' GROUP BY status';
	$out = array_fill_keys( array_keys( flxs_status_labels() ), 0 );
	foreach ( $wpdb->get_results( $sql, ARRAY_A ) as $r ) {
		$out[ $r['status'] ] = (int) $r['n'];
	}
	$out['total'] = array_sum( $out );
	return $out;
}

function flxs_query_messages( $args = array() ) {
	global $wpdb;
	$a = wp_parse_args(
		$args,
		array( 'status' => '', 'channel' => '', 'batch_id' => 0, 'certificate_id' => 0, 'person_id' => 0, 'search' => '', 'per_page' => 30, 'page' => 1, 'created_by' => 0, 'scope' => false )
	);
	$t     = flxs_table( 'messages' );
	$where = array( '1=1' );
	$prm   = array();
	foreach ( array( 'status', 'channel' ) as $k ) {
		if ( $a[ $k ] ) {
			$where[] = "$k = %s";
			$prm[]   = $a[ $k ];
		}
	}
	foreach ( array( 'batch_id', 'certificate_id', 'person_id', 'created_by' ) as $k ) {
		if ( $a[ $k ] ) {
			$where[] = "$k = %d";
			$prm[]   = (int) $a[ $k ];
		}
	}
	if ( $a['scope'] && flxs_scoped() ) {
		$where[] = flxs_messages_scope_sql();
	}
	if ( $a['search'] ) {
		$like    = '%' . $wpdb->esc_like( $a['search'] ) . '%';
		$where[] = '(recipient LIKE %s OR recipient_name LIKE %s OR body LIKE %s)';
		array_push( $prm, $like, $like, $like );
	}
	$w     = implode( ' AND ', $where );
	$total = (int) $wpdb->get_var( $prm ? $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE $w", $prm ) : "SELECT COUNT(*) FROM $t WHERE $w" );
	$sql   = "SELECT * FROM $t WHERE $w ORDER BY id DESC" . $wpdb->prepare( ' LIMIT %d OFFSET %d', $a['per_page'], ( max( 1, $a['page'] ) - 1 ) * $a['per_page'] );
	$items = $wpdb->get_results( $prm ? $wpdb->prepare( $sql, $prm ) : $sql, ARRAY_A );
	return array( 'items' => $items, 'total' => $total );
}

/* ------------------------------------------------------------------ */
/* Toplu gönderim                                                      */
/* ------------------------------------------------------------------ */

/**
 * @param array $spec mode (certificates|people|list), channel, provider_id, subject, body, name,
 *                    certificate_ids[], template_id, person_ids[], list (metin), only_with_cert
 * @param bool  $dry  true: yalnızca önizleme
 */
function flxs_bulk_send( array $spec, $dry = false ) {
	global $wpdb;
	$channel = $spec['channel'] ?? '';
	if ( ! isset( flxs_channels()[ $channel ] ) ) {
		return new WP_Error( 'flxs_channel', 'Kanal seçin.' );
	}
	$provider = flxs_resolve_provider( $channel, (int) ( $spec['provider_id'] ?? 0 ) );
	if ( ! $provider ) {
		return new WP_Error( 'flxs_no_provider', flxs_channel_label( $channel ) . ' için aktif sağlayıcı yok.' );
	}
	$subject = (string) ( $spec['subject'] ?? '' );
	$body    = (string) ( $spec['body'] ?? '' );
	$mode    = $spec['mode'] ?? 'certificates';
	$items   = array();
	$skipped = array();
	$seen    = array();

	if ( 'certificates' === $mode ) {
		$ids = array_filter( array_map( 'intval', (array) ( $spec['certificate_ids'] ?? array() ) ) );
		if ( ! $ids && ! empty( $spec['template_id'] ) ) {
			$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxs_table( 'certificates' ) . " WHERE template_id = %d AND status = 'active'", $spec['template_id'] ) ) );
		}
		foreach ( $ids as $id ) {
			$cert = flxs_get_certificate( $id );
			if ( ! $cert ) {
				continue;
			}
			if ( 'revoked' === $cert['status'] ) {
				$skipped[] = $cert['number'] . ': iptal edilmiş';
				continue;
			}
			$m = flxs_build_certificate_message( $cert, $channel, array( 'subject' => $subject, 'body' => $body ) );
			if ( is_wp_error( $m ) ) {
				$skipped[] = $cert['number'] . ': ' . $m->get_error_message();
				continue;
			}
			$items[] = $m;
		}
	} else {
		$people = array();
		if ( 'list' === $mode ) {
			foreach ( preg_split( '/\r?\n/', (string) ( $spec['list'] ?? '' ) ) as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				$parts = array_map( 'trim', preg_split( '/[;,\t]/', $line ) );
				$name  = '';
				$addr  = '';
				foreach ( $parts as $p ) {
					if ( ( 'email' === $channel && is_email( $p ) ) || ( 'email' !== $channel && flxs_normalize_phone( $p ) ) ) {
						$addr = $p;
					} elseif ( '' !== $p ) {
						$name = $p;
					}
				}
				if ( ! $addr ) {
					$skipped[] = $line . ': adres okunamadı';
					continue;
				}
				$known = 'email' === $channel ? flxs_find_person_by_email( $addr ) : flxs_find_person_by_phone( $addr );
				if ( $known ) {
					$people[] = $known;
				} else {
					$people[] = array( 'id' => 0, 'name' => $name, 'phone' => flxs_normalize_phone( $addr ), 'email' => strtolower( $addr ) );
				}
			}
		} else {
			$pt  = flxs_table( 'people' );
			$ct  = flxs_table( 'certificates' );
			$ids = array_filter( array_map( 'intval', (array) ( $spec['person_ids'] ?? array() ) ) );
			if ( $ids ) {
				$people = $wpdb->get_results( "SELECT * FROM $pt WHERE id IN (" . implode( ',', $ids ) . ')', ARRAY_A );
			} elseif ( ! empty( $spec['template_id'] ) ) {
				$people = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT p.* FROM $pt p INNER JOIN $ct c ON c.person_id = p.id WHERE c.template_id = %d AND c.status = 'active'", $spec['template_id'] ), ARRAY_A );
			} else {
				$people = $wpdb->get_results( "SELECT * FROM $pt ORDER BY id", ARRAY_A );
			}
		}
		if ( '' === trim( $body ) ) {
			return new WP_Error( 'flxs_body', 'Mesaj metni boş.' );
		}
		foreach ( $people as $p ) {
			$addr = 'email' === $channel ? (string) $p['email'] : (string) $p['phone'];
			if ( ! $addr ) {
				$skipped[] = ( $p['name'] ? $p['name'] : '#' . $p['id'] ) . ': ' . ( 'email' === $channel ? 'e-posta yok' : 'telefon yok' );
				continue;
			}
			$vars = array(
				'ad_soyad' => $p['name'],
				'ad'       => flxs_first_name( $p['name'] ),
				'site'     => get_bloginfo( 'name' ),
				'kurum'    => flxs_setting( 'org_name' ),
				'link'     => '',
				'buton'    => '',
			);
			if ( ! empty( $p['id'] ) ) {
				$last = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxs_table( 'certificates' ) . " WHERE person_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1", $p['id'] ), ARRAY_A );
				if ( $last ) {
					$vars['link']         = flxs_certificate_url( $last );
					$vars['sertifika']    = $last['title'];
					$vars['sertifika_no'] = $last['number'];
					$vars['buton']        = 'email' === $channel ? '{buton}' : $vars['link'];
				}
			}
			$items[] = array(
				'recipient'      => $addr,
				'recipient_name' => $p['name'],
				'person_id'      => (int) $p['id'],
				'certificate_id' => isset( $last ) && $last ? (int) $last['id'] : 0,
				'subject'        => trim( flxs_fill( $subject, $vars ) ),
				'body'           => trim( flxs_fill( $body, $vars ) ),
			);
			unset( $last );
		}
	}

	// Aynı adrese aynı metni iki kez gönderme
	$uniq = array();
	foreach ( $items as $it ) {
		$k = strtolower( $it['recipient'] ) . '|' . md5( $it['body'] );
		if ( isset( $seen[ $k ] ) ) {
			$skipped[] = $it['recipient_name'] . ': tekrar eden alıcı';
			continue;
		}
		$seen[ $k ] = 1;
		$uniq[]     = $it;
	}
	$items = $uniq;

	if ( $dry ) {
		$delay = (float) ( $provider['config']['delay'] ?? 0 );
		return array(
			'count'    => count( $items ),
			'skipped'  => $skipped,
			'samples'  => array_slice( $items, 0, 3 ),
			'provider' => $provider['name'],
			'duration' => $delay > 0 ? (int) ceil( count( $items ) * $delay ) : 0,
		);
	}
	if ( ! $items ) {
		return new WP_Error( 'flxs_empty', 'Gönderilecek alıcı yok.' . ( $skipped ? ' Atlanan: ' . count( $skipped ) : '' ) );
	}

	$name  = ! empty( $spec['name'] ) ? $spec['name'] : flxs_channel_label( $channel ) . ' toplu gönderim · ' . wp_date( 'd.m.Y H:i' );
	$batch = flxs_create_batch( $name, $channel, $mode, array( 'provider' => $provider['name'], 'skipped' => $skipped ) );
	$n     = 0;
	foreach ( $items as $it ) {
		$it['channel']     = $channel;
		$it['provider_id'] = (int) $provider['id'];
		$it['batch_id']    = $batch;
		$it['source']      = 'bulk';
		$r                 = flxs_enqueue( $it );
		if ( ! is_wp_error( $r ) ) {
			++$n;
		}
	}
	$wpdb->update( flxs_table( 'batches' ), array( 'total' => $n ), array( 'id' => $batch ) );
	flxs_log( 'batch_created', 'batch', $batch, sprintf( '%s · %d alıcı · %s', $name, $n, $provider['name'] ), array( 'mode' => $mode, 'channel' => $channel, 'skipped' => count( $skipped ) ) );
	flxs_kick_queue();
	return array( 'batch_id' => $batch, 'queued' => $n, 'skipped' => $skipped );
}

function flxs_recent_batches( $limit = 5 ) {
	global $wpdb;
	$own  = flxs_scoped() ? $wpdb->prepare( ' WHERE created_by = %d', get_current_user_id() ) : '';
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxs_table( 'batches' ) . $own . ' ORDER BY id DESC LIMIT %d', $limit ), ARRAY_A ); // phpcs:ignore
	foreach ( $rows as &$r ) {
		$r['counts'] = flxs_queue_counts( $r['id'] );
	}
	return $rows;
}
