<?php
/**
 * Tekil ve toplu gönderim (e-posta, WhatsApp, SMS): alıcı listesi, kuyruk, abonelikten çıkma.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;
FLX_Background_Pool::register( 'flxi_run_queue', 'flx-mail' );

/** Alıcı kaynakları (gönderim ekranındaki seçimler). */
function flxi_recipient_sources() {
	$out = array();
	foreach ( wp_roles()->get_names() as $role => $name ) {
		$count = count_users()['avail_roles'][ $role ] ?? 0;
		if ( $count ) {
			/* translators: 1: rol adı, 2: kişi sayısı */
			$out[ 'role:' . $role ] = sprintf( __( 'Members: %1$s (%2$s)', 'flx-mail' ), translate_user_role( $name ), number_format_i18n( $count ) );
		}
	}
	if ( function_exists( 'flx_people_query' ) ) {
		// 1.15.0: shared FluxWP people table (one record per person across CRM, Certificates, Donate, Commerce…).
		/* translators: %s: number of people */
		$out['flx_people'] = sprintf( __( 'All people, who allowed this channel (%s)', 'flx-mail' ), number_format_i18n( flx_people_query( array( 'count' => true ) ) ) );
		if ( function_exists( 'flxs_table' ) ) {
			/* translators: %s: number of people */
			$out['flxs_people'] = sprintf( __( 'Flux Certificates contacts (%s)', 'flx-mail' ), number_format_i18n( flx_people_query( array( 'count' => true, 'source' => 'certificates' ) ) ) );
		}
	} elseif ( function_exists( 'flxs_table' ) ) {
		global $wpdb;
		$t = flxs_table( 'people' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) { // phpcs:ignore
			$n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" ); // phpcs:ignore
			/* translators: %s: kişi sayısı */
			$out['flxs_people'] = sprintf( __( 'Flux Certificates contacts (%s)', 'flx-mail' ), number_format_i18n( $n ) );
		}
	}
	return apply_filters( 'flxi_recipient_sources', $out );
}

/**
 * Alıcı listesini çıkarır.
 *
 * @param array  $sources Kaynak anahtarları.
 * @param string $manual  Elle yazılan liste: her satırda "e-posta", "telefon", "Ad Soyad <e-posta>" ya da "Ad Soyad, e-posta, telefon".
 * @param string $channel email|whatsapp|sms
 * @param array|null $report Verilirse kaynak başına döküm yazılır: [ kaynak => [ found, added, no_address, duplicate, no_consent ] ]
 *                           ve 'unsubscribed' (e-postada abonelikten çıkanlar). Gönder ekranı "neden alıcı yok" sorusunu bununla yanıtlar.
 * @return array [ ['to' => …, 'name' => …], … ] (tekrarsız, abonelikten çıkanlar hariç)
 */
function flxi_collect_recipients( array $sources, $manual, $channel, &$report = null ) {
	$list = array();
	$stat = array( 'found' => 0, 'added' => 0, 'no_address' => 0, 'duplicate' => 0, 'no_consent' => 0 );
	$cur  = $stat;
	$add  = function ( $name, $email, $phone ) use ( &$list, &$cur, $channel ) {
		++$cur['found'];
		$to = 'email' === $channel ? strtolower( trim( (string) $email ) ) : flxi_normalize_phone( $phone );
		if ( ! $to || ( 'email' === $channel && ! is_email( $to ) ) ) {
			++$cur['no_address'];
			return;
		}
		if ( isset( $list[ $to ] ) ) {
			++$cur['duplicate'];
			return;
		}
		++$cur['added'];
		$list[ $to ] = array( 'to' => $to, 'name' => trim( (string) $name ) );
	};
	$by_source = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $manual ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$name  = '';
		$email = '';
		$phone = '';
		if ( preg_match( '/^(.*)<([^>]+)>\s*$/', $line, $m ) ) {
			$name  = trim( $m[1], " \t\"'," );
			$email = trim( $m[2] );
		} else {
			foreach ( preg_split( '/[,;\t]/', $line ) as $part ) {
				$part = trim( $part );
				if ( is_email( $part ) ) {
					$email = $part;
				} elseif ( preg_match( '/^[+\d][\d\s().-]{8,}$/', $part ) ) {
					$phone = $part;
				} elseif ( '' !== $part && '' === $name ) {
					$name = $part;
				}
			}
		}
		$add( $name, $email, $phone );
	}
	$by_source['manual'] = $cur;
	foreach ( $sources as $src ) {
		$cur = $stat;
		if ( 0 === strpos( $src, 'role:' ) ) {
			$users = get_users( array( 'role' => substr( $src, 5 ), 'number' => 20000, 'fields' => array( 'ID', 'user_email', 'display_name' ) ) );
			foreach ( array_chunk( $users, 500 ) as $chunk ) {
				update_meta_cache( 'user', wp_list_pluck( $chunk, 'ID' ) );
				foreach ( $chunk as $u ) {
					$phone = get_user_meta( $u->ID, 'billing_phone', true );
					$first = trim( get_user_meta( $u->ID, 'first_name', true ) . ' ' . get_user_meta( $u->ID, 'last_name', true ) );
					$add( $first ? $first : $u->display_name, $u->user_email, $phone );
				}
			}
		} elseif ( 'flx_people' === $src && function_exists( 'flx_people_query' ) ) {
			// Everyone in the shared table who allowed this channel; the others are counted as "no consent".
			$consent = 'email' === $channel ? 'email' : ( 'sms' === $channel ? 'sms' : 'whatsapp' );
			$all     = flx_people_query( array( 'count' => true ) );
			for ( $pg = 1; $pg <= 100; $pg++ ) {
				$rows = flx_people_query( array( 'consent' => $consent, 'per_page' => 500, 'page' => $pg, 'orderby' => 'id', 'order' => 'asc' ) );
				foreach ( $rows as $r ) {
					$add( $r['name'], $r['email'], $r['phone'] );
				}
				if ( count( $rows ) < 500 ) {
					break;
				}
			}
			$cur['no_consent'] = max( 0, $all - $cur['found'] );
			$cur['found']     += $cur['no_consent'];
		} elseif ( 'flxs_people' === $src && function_exists( 'flxs_table' ) ) {
			global $wpdb;
			$mine = function_exists( 'flxs_people_mine_sql' ) ? ' WHERE ' . flxs_people_mine_sql( 'p' ) : '';
			$rows = $wpdb->get_results( 'SELECT p.name, p.email, p.phone FROM ' . flxs_table( 'people' ) . ' p' . $mine . ' LIMIT 50000', ARRAY_A ); // phpcs:ignore
			foreach ( (array) $rows as $r ) {
				$add( $r['name'], $r['email'], $r['phone'] );
			}
		} else {
			foreach ( (array) apply_filters( 'flxi_recipients_from_source', array(), $src, $channel ) as $r ) {
				$add( $r['name'] ?? '', $r['email'] ?? '', $r['phone'] ?? '' );
			}
			/**
			 * Kaynak, bu kanal için eleyip hiç döndürmediği kişileri bildirir (ör. Flux CRM: izin vermeyenler).
			 * Dönüş: [ 'total' => kaynaktaki kişi sayısı, 'no_consent' => kanala izin vermediği için alınmayanlar ].
			 */
			$info = (array) apply_filters( 'flxi_recipient_source_info', array(), $src, $channel );
			if ( $info ) {
				$cur['no_consent'] = max( 0, (int) ( $info['no_consent'] ?? 0 ) );
				$cur['found']      = max( $cur['found'] + $cur['no_consent'], (int) ( $info['total'] ?? 0 ) );
			}
		}
		$by_source[ $src ] = $cur;
	}
	$before = count( $list );
	if ( 'email' === $channel ) {
		$unsub = flxi_unsubscribed();
		$list  = array_diff_key( $list, array_flip( $unsub ) );
	}
	if ( null !== $report ) {
		$report = array( 'sources' => $by_source, 'unsubscribed' => $before - count( $list ) );
	}
	return array_values( $list );
}

/**
 * Gönderim oluşturur ve kuyruğa ekler.
 *
 * @param array $data name, channel, subject, heading, body, button_text, button_url, sender, text, connection_id, unsubscribe
 * @param array $recipients flxi_collect_recipients() sonucu
 * @return int|WP_Error
 */
function flxi_create_campaign( array $data, array $recipients ) {
	global $wpdb;
	if ( ! $recipients ) {
		return new WP_Error( 'flxi_empty', __( 'There are no recipients to send to.', 'flx-mail' ) );
	}
	$channel = in_array( $data['channel'] ?? 'email', array( 'email', 'whatsapp', 'sms' ), true ) ? $data['channel'] : 'email';
	if ( 'email' === $channel && '' === trim( (string) ( $data['subject'] ?? '' ) ) ) {
		return new WP_Error( 'flxi_subject', __( 'Subject is required.', 'flx-mail' ) );
	}
	if ( 'email' !== $channel ) {
		if ( '' === trim( (string) ( $data['text'] ?? '' ) ) ) {
			return new WP_Error( 'flxi_text', __( 'Message text is required.', 'flx-mail' ) );
		}
		if ( ! flxi_resolve_connection( $channel, (int) ( $data['connection_id'] ?? 0 ) ) ) {
			return new WP_Error( 'flxi_conn', __( 'There is no active connection for this channel. Add one in Communication › Connections.', 'flx-mail' ) );
		}
	}
	$send_at = 0;
	if ( ! empty( $data['send_at'] ) ) {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', (string) $data['send_at'], wp_timezone() );
		if ( ! $date || $date->format( 'Y-m-d\TH:i' ) !== $data['send_at'] || $date->getTimestamp() <= time() + 60 ) { return new WP_Error( 'flxi_schedule', __( 'Choose a scheduled time at least one minute in the future.', 'flx-mail' ) ); }
		$send_at = $date->getTimestamp();
	}
	$name = sanitize_text_field( $data['name'] ?? '' );
	if ( '' === $name ) {
		$name = 'email' === $channel ? sanitize_text_field( $data['subject'] ) : mb_substr( sanitize_text_field( $data['text'] ), 0, 60 );
	}
	$wpdb->insert(
		flxi_table( 'campaigns' ),
		array(
			'name'       => mb_substr( $name, 0, 190 ),
			'channel'    => $channel,
			'data'       => flxi_json(
				array(
					'send_at'       => $send_at,
					'subject'       => sanitize_text_field( $data['subject'] ?? '' ),
					'heading'       => sanitize_text_field( $data['heading'] ?? '' ),
					'body'          => wp_kses_post( $data['body'] ?? '' ),
					'button_text'   => sanitize_text_field( $data['button_text'] ?? '' ),
					'button_url'    => esc_url_raw( $data['button_url'] ?? '' ),
					'sender'        => 'noreply' === ( $data['sender'] ?? '' ) ? 'noreply' : 'info',
					'text'          => sanitize_textarea_field( $data['text'] ?? '' ),
					'connection_id' => (int) ( $data['connection_id'] ?? 0 ),
					'unsubscribe'   => ! empty( $data['unsubscribe'] ),
				)
			),
			'status'     => 'queued',
			'total'      => count( $recipients ),
			'created_by' => get_current_user_id(),
			'created_at' => flxi_now(),
		)
	);
	$cid = (int) $wpdb->insert_id;

	// Mesajlar arası bekleme (WhatsApp numarası engellenmesin).
	$delay = 0;
	if ( 'email' !== $channel ) {
		$conn  = flxi_resolve_connection( $channel, (int) ( $data['connection_id'] ?? 0 ) );
		$delay = max( 0, (float) ( $conn['config']['delay'] ?? 0 ) );
	}
	$t = $send_at ?: time();
	if ( 'sms' === $channel && isset( $conn ) && 'netgsm' === $conn['type'] ) { $t = time(); $delay = 0; }
	$rows  = array();
	$table = flxi_table( 'queue' );
	foreach ( $recipients as $i => $r ) {
		$at     = gmdate( 'Y-m-d H:i:s', $t + (int) ceil( $i * $delay ) );
		$rows[] = $wpdb->prepare( '(%d,%s,%s,%s,%s,%s)', $cid, $channel, $r['to'], mb_substr( (string) $r['name'], 0, 190 ), 'queued', $at );
		if ( count( $rows ) >= 200 ) {
			$wpdb->query( "INSERT INTO $table (campaign_id,channel,recipient,name,status,scheduled_at) VALUES " . implode( ',', $rows ) ); // phpcs:ignore
			$rows = array();
		}
	}
	if ( $rows ) {
		$wpdb->query( "INSERT INTO $table (campaign_id,channel,recipient,name,status,scheduled_at) VALUES " . implode( ',', $rows ) ); // phpcs:ignore
	}
	flx_background_log( 'campaign.created', array( 'plugin' => 'flx-mail', 'campaign' => $cid, 'count' => count( $recipients ) ) );
	flxi_kick_queue();
	return $cid;
}

function flxi_get_campaign( $id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxi_table( 'campaigns' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore
	if ( $row ) {
		$row['data'] = (array) json_decode( (string) $row['data'], true );
	}
	return $row;
}

function flxi_cancel_campaign( $id ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . flxi_table( 'queue' ) . " SET status = 'cancelled' WHERE campaign_id = %d AND status = 'queued'", (int) $id ) ); // phpcs:ignore
	$c = flxi_get_campaign( $id );
	if ( $c ) { $c['data']['stopped'] = true; $wpdb->update( flxi_table( 'campaigns' ), array( 'data' => flxi_json( $c['data'] ) ), array( 'id' => (int) $id ) ); }
	flxi_refresh_campaign( $id );
	flx_background_log( 'campaign.queue.cancelled', array( 'plugin' => 'flx-mail', 'campaign' => (int) $id ) );
}

/** Kuyruk işleyicisini planlar (WP-Cron; kuyruk sayfası açıksa tarayıcı da tetikler). */
function flxi_kick_queue() {
	flx_background_schedule( 'flxi_run_queue', array(), flx_background_worker_active() ? 1 : 5 );
}

add_action(
	'flxi_run_queue',
	function () {
		flxi_run_queue( 25 );
	}
);

/**
 * Kuyruğu işler.
 *
 * @return array processed, sent, failed, left
 */
function flxi_run_queue( $budget = 20 ) {
	global $wpdb;
	return flx_background_run( 'mail-campaigns', function () use ( $budget, $wpdb ) {
		$t      = flxi_table( 'queue' );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 600 );
		$interrupted = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT campaign_id FROM $t WHERE status = 'processing' AND scheduled_at <= %s", $cutoff ) );
		flxi_netgsm_recover_processing( $cutoff );
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET status = CASE WHEN attempts >= 3 THEN 'failed' ELSE 'queued' END, scheduled_at = %s WHERE status = 'processing' AND scheduled_at <= %s", flxi_now(), $cutoff ) );
		foreach ( $interrupted as $cid ) { flxi_refresh_campaign( (int) $cid ); }
		$start  = microtime( true );
		$limit  = max( 1, (int) flxi_setting( 'bulk_per_run' ) );
		$stats  = array( 'processed' => 0, 'sent' => 0, 'failed' => 0 );
		$camps  = array();
		$emails = 0;
		while ( true ) {
			$left    = $budget - ( microtime( true ) - $start );
			$timeout = (int) floor( $left - 5 );
			if ( $timeout < 5 ) {
				break;
			}
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE status = 'queued' AND scheduled_at <= %s ORDER BY scheduled_at ASC, id ASC LIMIT 1", flxi_now() ), ARRAY_A ); // phpcs:ignore
			if ( ! $row ) {
				break;
			}
			if ( 'email' === $row['channel'] && $emails >= $limit ) {
				break;
			}
			$cid = (int) $row['campaign_id'];
			if ( ! isset( $camps[ $cid ] ) ) {
				$camps[ $cid ] = flxi_get_campaign( $cid );
				if ( $camps[ $cid ] && 'queued' === $camps[ $cid ]['status'] ) {
					$wpdb->update( flxi_table( 'campaigns' ), array( 'status' => 'running' ), array( 'id' => $cid ) );
				}
			}
			// Posta kutusu günlük sınırı: toplu e-posta yedek payı tekil e-postalara bırakır, hak açılınca devam eder.
			if ( 'email' === $row['channel'] ) {
				$snd = ( $camps[ $cid ]['data']['sender'] ?? 'info' ) === 'noreply' ? 'noreply' : 'info';
				if ( ! flxi_quota_bulk_allowed( $snd ) ) {
					$when = gmdate( 'Y-m-d H:i:s', flxi_quota_next_free( flxi_quota_mailbox( $snd ) ) + 60 );
					$wpdb->query( $wpdb->prepare( "UPDATE $t SET scheduled_at = %s WHERE status = 'queued' AND channel = 'email' AND scheduled_at < %s", $when, $when ) ); // phpcs:ignore
					update_option( 'flxi_quota_paused', strtotime( $when . ' UTC' ), false );
					$stats['paused_until'] = strtotime( $when . ' UTC' );
					break;
				}
			}
			if ( $camps[ $cid ] && empty( $camps[ $cid ]['data']['stopped'] ) && 'sms' === $row['channel'] ) {
				$conn = flxi_resolve_connection( 'sms', (int) $camps[ $cid ]['data']['connection_id'] );
				$type = $conn ? ( flxi_connection_types()[ $conn['type'] ] ?? array() ) : array();
				if ( is_callable( $type['queue_batch'] ?? null ) ) {
					$GLOBALS['flxi_queue_timeout'] = min( 30, $timeout );
					$res = call_user_func( $type['queue_batch'], $row, $camps[ $cid ] );
					unset( $GLOBALS['flxi_queue_timeout'] );
					if ( is_array( $res ) ) { foreach ( array( 'processed', 'sent', 'failed', 'submitted' ) as $key ) { $stats[ $key ] = ( $stats[ $key ] ?? 0 ) + (int) ( $res[ $key ] ?? 0 ); } if ( empty( $res['processed'] ) ) { break; } continue; }
				}
			}
			$claimed = $wpdb->query( $wpdb->prepare( "UPDATE $t SET status = 'processing', attempts = attempts + 1, scheduled_at = %s WHERE id = %d AND status = 'queued'", flxi_now(), $row['id'] ) );
			if ( 1 !== (int) $claimed ) {
				continue;
			}
			flx_background_log( 'message.claimed', array( 'plugin' => 'flx-mail', 'campaign' => $cid, 'item' => (int) $row['id'], 'attempt' => (int) $row['attempts'] + 1 ) );
			$GLOBALS['flxi_queue_timeout'] = min( 30, $timeout );
			try {
				$res = flxi_send_queue_item( $row, flxi_get_campaign( $cid ) );
			} catch ( Throwable $error ) {
				flx_background_log( 'message.exception', array( 'plugin' => 'flx-mail', 'campaign' => $cid, 'item' => (int) $row['id'], 'error_class' => get_class( $error ), 'error_file' => basename( $error->getFile() ), 'error_line' => $error->getLine() ) );
				$res = array( 'ok' => false, 'error' => $error->getMessage(), 'retry' => true );
			}
			unset( $GLOBALS['flxi_queue_timeout'] );
			$stats['processed']++;
			if ( 'email' === $row['channel'] ) {
				$emails++;
			}
			if ( $res['ok'] ) {
				$stats['sent']++;
				$wpdb->update( $t, array( 'status' => 'sent', 'sent_at' => flxi_now(), 'error' => '' ), array( 'id' => $row['id'] ) );
			} elseif ( ! empty( $res['skip'] ) ) {
				$wpdb->update( $t, array( 'status' => 'skipped', 'error' => mb_substr( (string) $res['error'], 0, 500 ) ), array( 'id' => $row['id'] ) );
			} elseif ( ! empty( $res['retry'] ) && (int) $row['attempts'] < 2 ) {
				$wpdb->update( $t, array( 'status' => 'queued', 'error' => mb_substr( (string) $res['error'], 0, 500 ), 'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() + 120 ) ), array( 'id' => $row['id'] ) );
			} else {
				$stats['failed']++;
				$wpdb->update( $t, array( 'status' => 'failed', 'error' => mb_substr( (string) $res['error'], 0, 500 ) ), array( 'id' => $row['id'] ) );
			}
			flx_background_log( 'message.result', array( 'plugin' => 'flx-mail', 'campaign' => $cid, 'item' => (int) $row['id'], 'state' => $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $t WHERE id=%d", $row['id'] ) ), 'attempt' => (int) $row['attempts'] + 1 ) );
		}
		foreach ( array_keys( $camps ) as $cid ) {
			flxi_refresh_campaign( $cid );
		}

		$stats['left'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'queued'" ); // phpcs:ignore
		if ( $stats['left'] ) {
			$next = $wpdb->get_var( "SELECT MIN(scheduled_at) FROM $t WHERE status = 'queued'" ); // phpcs:ignore
			$when = max( time() + ( flx_background_worker_active() ? 1 : 30 ), $next ? strtotime( $next . ' UTC' ) : time() + 60 );
			flx_background_schedule( 'flxi_run_queue', array(), $when - time() );
		}
		$processing = $wpdb->get_var( "SELECT MIN(scheduled_at) FROM $t WHERE status = 'processing'" );
		if ( $processing ) { flx_background_schedule( 'flxi_run_queue', array(), max( 30, strtotime( $processing . ' UTC' ) + 600 - time() ) ); }
		return $stats;
	}, array(
		'ttl' => $budget + 90,
		'hook' => 'flxi_run_queue',
		'on_defer' => static function () use ( $wpdb ) {
			flxi_kick_queue();
			return array( 'processed' => 0, 'sent' => 0, 'failed' => 0, 'left' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . flxi_table( 'queue' ) . " WHERE status = 'queued'" ), 'deferred' => true );
		},
	) );
}

function flxi_refresh_campaign( $cid ) {
	global $wpdb;
	$t   = flxi_table( 'queue' );
	$cnt = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) n FROM $t WHERE campaign_id = %d GROUP BY status", $cid ), ARRAY_A ); // phpcs:ignore
	$by  = array();
	foreach ( (array) $cnt as $r ) {
		$by[ $r['status'] ] = (int) $r['n'];
	}
	$open = ( $by['queued'] ?? 0 ) + ( $by['processing'] ?? 0 );
	$c = flxi_get_campaign( $cid );
	$state = $open ? 'running' : ( ! empty( $by['submitted'] ) ? 'reporting' : ( ! empty( $by['unknown'] ) ? 'review' : ( ! empty( $c['data']['stopped'] ) ? 'cancelled' : 'done' ) ) );
	$data = array( 'sent' => ( $by['sent'] ?? 0 ) + ( $by['delivered'] ?? 0 ), 'failed' => $by['failed'] ?? 0, 'skipped' => $by['skipped'] ?? 0, 'status' => $state, 'finished_at' => in_array( $state, array( 'done', 'cancelled' ), true ) ? flxi_now() : null );

	$wpdb->update( flxi_table( 'campaigns' ), $data, array( 'id' => (int) $cid ) );
}

/** Tek kuyruk satırını gönderir. */
function flxi_send_queue_item( $row, $camp ) {
	if ( ! $camp || 'cancelled' === $camp['status'] || ! empty( $camp['data']['stopped'] ) ) {
		return array( 'ok' => false, 'error' => __( 'Sending was canceled.', 'flx-mail' ), 'retry' => false );
	}
	$d    = $camp['data'];
	$name = (string) $row['name'];
	$vars = array(
		'ad'       => $name ? preg_split( '/\s+/u', trim( $name ) )[0] : '',
		'ad_soyad' => $name,
		'eposta'   => 'email' === $row['channel'] ? $row['recipient'] : '',
		'telefon'  => 'email' !== $row['channel'] ? $row['recipient'] : '',
	);
	if ( flxi_contact_blocked( 'email' === $row['channel'] ? '' : $row['recipient'], 'email' === $row['channel'] ? $row['recipient'] : '', $row['channel'] ) ) {
		return array( 'ok' => false, 'error' => __( 'On the blocklist: not sent', 'flx-mail' ), 'retry' => false, 'skip' => true );
	}
	if ( 'email' === $row['channel'] ) {
		if ( in_array( $row['recipient'], flxi_unsubscribed(), true ) ) {
			return array( 'ok' => false, 'error' => __( 'Unsubscribed.', 'flx-mail' ), 'retry' => false );
		}
		$unsub   = ! empty( $d['unsubscribe'] ) && flxi_setting( 'bulk_unsubscribe' ) ? flxi_unsubscribe_url( $row['recipient'] ) : '';
		$headers = array();
		if ( $unsub ) {
			$headers[] = 'List-Unsubscribe: <' . $unsub . '>';
			$headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
		}
		$error = '';
		$catch = function ( $e ) use ( &$error ) {
			$error = $e->get_error_message();
		};
		add_action( 'wp_mail_failed', $catch );
		$ok = flxi_send_email(
			array(
				'to'              => $row['recipient'],
				'template'        => 'flx-mail/campaign',
				'vars'            => $vars,
				'subject'         => $d['subject'],
				'heading'         => $d['heading'],
				'body'            => $d['body'],
				'button_text'     => $d['button_text'],
				'button_url'      => $d['button_url'],
				'sender'          => $d['sender'],
				'campaign_id'     => (int) $camp['id'],
				'unsubscribe_url' => $unsub,
				'headers'         => $headers,
			)
		);
		remove_action( 'wp_mail_failed', $catch );
		return array( 'ok' => (bool) $ok, 'error' => $error, 'retry' => ! $ok );
	}
	return flxi_send_message(
		$row['channel'],
		$row['recipient'],
		flxi_fill( (string) $d['text'], $vars ),
		array(
			'connection_id' => (int) $d['connection_id'],
			'campaign_id'   => (int) $camp['id'],
			'source'        => 'flx-mail',
			'template'      => 'flx-mail/campaign',
		)
	);
}

/* ------------------------------------------------------------------ */
/* Abonelikten çıkma                                                   */
/* ------------------------------------------------------------------ */

function flxi_unsubscribed() {
	$l = get_option( 'flxi_unsubscribed', array() );
	return is_array( $l ) ? $l : array();
}

function flxi_unsubscribe_token( $email ) {
	return substr( hash_hmac( 'sha256', strtolower( $email ), wp_salt( 'auth' ) . 'flxi-unsub' ), 0, 20 );
}

function flxi_unsubscribe_url( $email ) {
	$e = rtrim( strtr( base64_encode( strtolower( $email ) ), '+/', '-_' ), '=' );
	return add_query_arg( array( 'flxi_unsub' => $e, 'k' => flxi_unsubscribe_token( $email ) ), home_url( '/' ) );
}

add_action(
	'init',
	function () {
		// Links in emails sent before the FluxWP rename use yti_unsub and the old key.
		$legacy = empty( $_GET['flxi_unsub'] ) && ! empty( $_GET['yti_unsub'] ); // phpcs:ignore
		$param  = $legacy ? 'yti_unsub' : 'flxi_unsub';
		if ( empty( $_GET[ $param ] ) || empty( $_GET['k'] ) ) { // phpcs:ignore
			return;
		}
		$email = strtolower( (string) base64_decode( strtr( sanitize_text_field( wp_unslash( $_GET[ $param ] ) ), '-_', '+/' ) ) ); // phpcs:ignore
		$key   = sanitize_text_field( wp_unslash( $_GET['k'] ) ); // phpcs:ignore
		$want  = $legacy ? substr( hash_hmac( 'sha256', $email, wp_salt( 'auth' ) . 'yti-unsub' ), 0, 20 ) : flxi_unsubscribe_token( $email );
		if ( ! is_email( $email ) || ! hash_equals( $want, $key ) ) {
			wp_die( esc_html__( 'The link is invalid.', 'flx-mail' ), '', array( 'response' => 400 ) );
		}
		$post = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ); // phpcs:ignore
		if ( $post ) {
			$list = flxi_unsubscribed();
			if ( ! in_array( $email, $list, true ) ) {
				$list[] = $email;
				update_option( 'flxi_unsubscribed', $list, false );
			}
			// Tek tıkla çıkma (RFC 8058): e-posta istemcisi gövdesiz yanıt bekler.
			if ( isset( $_POST['List-Unsubscribe'] ) ) { // phpcs:ignore
				status_header( 200 );
				exit;
			}
			wp_die( '<h1>' . esc_html__( 'You have been unsubscribed', 'flx-mail' ) . '</h1><p>' . esc_html( sprintf( /* translators: %s: e-posta */ __( 'We will no longer send bulk emails to %s. Transactional emails such as donation and certificate emails will continue to be sent.', 'flx-mail' ), $email ) ) . '</p>', esc_html__( 'You have been unsubscribed', 'flx-mail' ), array( 'response' => 200 ) );
		}
		$form = '<h1>' . esc_html__( 'Do you want to unsubscribe from bulk emails?', 'flx-mail' ) . '</h1><p>' . esc_html( $email ) . '</p><form method="post"><button type="submit" class="button button-primary" style="padding:10px 20px;font-size:15px">' . esc_html__( 'Yes, unsubscribe me', 'flx-mail' ) . '</button></form>';
		wp_die( $form, esc_html__( 'Unsubscribe', 'flx-mail' ), array( 'response' => 200 ) ); // phpcs:ignore
	}
);
