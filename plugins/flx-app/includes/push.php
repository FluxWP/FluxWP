<?php
/**
 * Push bildirimleri (Expo Push API). Paketler gönderir:
 *
 *   do_action( 'flxapp_push', $user_ids, array(
 *       'event'  => 'flxg.assigned',          // flxapp_push_events'te kayıtlı tür; kullanıcı kapattıysa gitmez
 *       'title'  => __( 'Yeni görev', 'flx-tasks' ),
 *       'body'   => $task_title,
 *       'target' => 'module:flxg-tasks',      // §6 hedef sözdizimi (screen:, module:, menu:, web:, url:)
 *       'params' => array( 'id' => 12 ),
 *   ) );
 *
 * Gönderim yanıt kullanıcıya gittikten sonra yapılır (fastcgi/litespeed_finish_request), cron'a güvenilmez.
 * Expo'ya 100'lük paketler; `DeviceNotRegistered` dönen cihazın belirteci silinir. FCM/APNs için
 * `flxapp_push_send` filtresi. Bildirim verisi: { v: 1, site, user, event, target, params }.
 */

defined( 'ABSPATH' ) || exit;

/** Kullanıcının açıp kapatabileceği bildirim türleri: [ id => { title, default, source } ]. */
function flxapp_push_events() {
	$ev = array(
		'core.comment' => array(
			'title'   => __( 'Pending comments', 'flx-app' ),
			'default' => true,
			'source'  => 'core',
			'cap'     => 'moderate_comments',
		),
	);
	if ( function_exists( 'flxapp_manifests_raw' ) ) {
		foreach ( flxapp_manifests_raw() as $mid => $m ) {
			foreach ( (array) ( $m['push']['events'] ?? array() ) as $e ) {
				if ( ! empty( $e['id'] ) ) {
					$ev[ (string) $e['id'] ] = array(
						'title'   => (string) ( $e['title'] ?? $e['id'] ),
						'default' => ! isset( $e['default'] ) || (bool) $e['default'],
						'source'  => $mid,
					);
				}
			}
		}
	}
	/**
	 * Bildirim türleri.
	 *
	 * @param array $events id => { title, default, source, cap? }
	 */
	return (array) apply_filters( 'flxapp_push_events', $ev );
}

/** Kullanıcı bu türü almak istiyor mu (prefs.push, yoksa türün varsayılanı). */
function flxapp_push_wanted( $user_id, $event, $device_events = '' ) {
	if ( '' === (string) $event ) {
		return true;
	}
	$dev = json_decode( (string) $device_events, true );
	if ( is_array( $dev ) && array_key_exists( $event, $dev ) ) {
		return (bool) $dev[ $event ];
	}
	$prefs = flxapp_user_prefs( $user_id );
	if ( isset( $prefs['push'][ $event ] ) ) {
		return (bool) $prefs['push'][ $event ];
	}
	$all = flxapp_push_events();
	return ! isset( $all[ $event ] ) || ! empty( $all[ $event ]['default'] );
}

/**
 * Bildirimi kuyruğa alır; istek bitince gönderilir. `now => true` hemen gönderir ve sonucu döner (Test düğmesi).
 *
 * @param int[]|int $user_ids
 * @param array     $msg event, title, body, target, params, badge?, now?
 * @return array|null { sent, failed, skipped } (yalnız now)
 */
function flxapp_push( $user_ids, array $msg ) {
	static $hooked = false;
	$user_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $user_ids ) ) ) );
	if ( ! $user_ids ) {
		return array( 'sent' => 0, 'failed' => 0, 'skipped' => 0 );
	}
	// Masaüstü push almaz; olay /sync `events` listesinden okunur.
	if ( empty( $msg['test'] ) ) {
		foreach ( array_slice( $user_ids, 0, 200 ) as $uid ) {
			flxapp_event_record( $uid, $msg );
		}
	}
	if ( ! flxapp_opt( 'push_enabled' ) ) {
		return array( 'sent' => 0, 'failed' => 0, 'skipped' => 0 );
	}
	if ( ! empty( $msg['now'] ) ) {
		return flxapp_push_deliver( $user_ids, $msg );
	}
	$GLOBALS['flxapp_push_queue'][] = array( $user_ids, $msg );
	if ( ! $hooked ) {
		$hooked = true;
		add_action( 'shutdown', 'flxapp_push_flush', 1 );
	}
	return null;
}
add_action( 'flxapp_push', 'flxapp_push', 10, 2 );

function flxapp_push_flush() {
	$queue = (array) ( $GLOBALS['flxapp_push_queue'] ?? array() );
	$GLOBALS['flxapp_push_queue'] = array();
	if ( ! $queue ) {
		return;
	}
	if ( function_exists( 'fastcgi_finish_request' ) ) {
		fastcgi_finish_request();
	} elseif ( function_exists( 'litespeed_finish_request' ) ) {
		litespeed_finish_request();
	}
	foreach ( $queue as $q ) {
		flxapp_push_deliver( $q[0], $q[1] );
	}
}

function flxapp_push_deliver( array $user_ids, array $msg ) {
	global $wpdb;
	$out  = array( 'sent' => 0, 'failed' => 0, 'skipped' => 0 );
	$d    = flxapp_devices_table();
	$t    = flxapp_table();
	$in   = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
	$args = array_merge( $user_ids, array( time() ) );
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT d.* FROM {$d} d WHERE d.user_id IN ({$in}) AND d.token_hash <> '' AND EXISTS ( SELECT 1 FROM {$t} k WHERE k.grant_id = d.grant_id AND k.kind = 'refresh' AND k.revoked = 0 AND k.expires > %d )", $args ), ARRAY_A ); // phpcs:ignore WordPress.DB
	$event = preg_replace( '/[^a-z0-9_.\-]/', '', strtolower( (string) ( $msg['event'] ?? '' ) ) );
	$by    = array();
	foreach ( $rows as $r ) {
		if ( ! flxapp_push_wanted( (int) $r['user_id'], $event, $r['events'] ) ) {
			++$out['skipped'];
			continue;
		}
		$r['token'] = flxapp_decrypt( $r['token_enc'] );
		if ( '' === $r['token'] ) {
			++$out['skipped'];
			continue;
		}
		$by[ $r['provider'] ][] = $r;
	}
	$title = (string) ( $msg['title'] ?? '' );
	$body  = (string) ( $msg['body'] ?? '' );
	if ( flxapp_opt( 'push_hide' ) && empty( $msg['public'] ) ) {
		// İçerik gizli: kilit ekranında yalnız tür görünür.
		$body = '';
	}
	$base = array(
		'v'      => 1,
		'site'   => flxapp_issuer(),
		'event'  => $event,
		'target' => (string) ( $msg['target'] ?? '' ),
		'params' => (object) (array) ( $msg['params'] ?? array() ),
	);
	foreach ( $by as $provider => $list ) {
		$m = array( 'title' => $title, 'body' => $body, 'data' => $base, 'badge' => $msg['badge'] ?? null );
		/**
		 * Başka taşıyıcıyla gönderim (FCM, APNs). Dizi dönerse ({sent, failed, invalid: [token]}) Expo'ya gidilmez.
		 *
		 * @param array|null $result
		 * @param string     $provider expo | fcm | apns
		 * @param array      $devices  cihaz satırları (`token` çözülmüş)
		 * @param array      $message  title, body, data, badge
		 */
		$res = apply_filters( 'flxapp_push_send', null, $provider, $list, $m );
		if ( null === $res && 'expo' === $provider ) {
			$res = flxapp_push_expo( $list, $m );
		}
		if ( ! is_array( $res ) ) {
			$out['skipped'] += count( $list );
			continue;
		}
		$out['sent']   += (int) ( $res['sent'] ?? 0 );
		$out['failed'] += (int) ( $res['failed'] ?? 0 );
		foreach ( (array) ( $res['invalid'] ?? array() ) as $bad ) {
			$wpdb->update( $d, array( 'token_hash' => '', 'token_enc' => '', 'provider' => '' ), array( 'token_hash' => flxapp_hash( (string) $bad ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
	}
	update_option( 'flxapp_push_last', $out + array( 'at' => time(), 'error' => $GLOBALS['flxapp_push_error'] ?? '' ), false );
	return $out;
}

/** Expo push servisi. Erişim belirteci isteğe bağlı (Expo hesabında "enhanced security" açıksa gerekir). */
function flxapp_push_expo( array $devices, array $m ) {
	$res   = array( 'sent' => 0, 'failed' => 0, 'invalid' => array() );
	$token = flxapp_secret_get( 'push_expo_token' );
	foreach ( array_chunk( $devices, 100 ) as $chunk ) {
		$batch = array();
		foreach ( $chunk as $r ) {
			$one = array(
				'to'       => $r['token'],
				'title'    => $m['title'],
				'body'     => $m['body'],
				'data'     => $m['data'] + array( 'user' => (int) $r['user_id'] ),
				'sound'    => 'default',
				'priority' => 'high',
			);
			if ( null !== $m['badge'] ) {
				$one['badge'] = (int) $m['badge'];
			}
			if ( '' !== $m['data']['event'] ) {
				$one['channelId'] = 'default';
				$one['categoryId'] = str_replace( '.', '_', $m['data']['event'] );
			}
			$batch[] = $one;
		}
		$headers = array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' );
		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}
		$http = wp_remote_post(
			(string) apply_filters( 'flxapp_push_expo_url', 'https://exp.host/--/api/v2/push/send' ),
			array(
				'timeout' => 15,
				'headers' => $headers,
				'body'    => wp_json_encode( $batch ),
			)
		);
		$body = is_wp_error( $http ) ? null : json_decode( (string) wp_remote_retrieve_body( $http ), true );
		if ( ! is_array( $body ) || ! isset( $body['data'] ) || ! is_array( $body['data'] ) ) {
			$GLOBALS['flxapp_push_error'] = is_wp_error( $http ) ? $http->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $http );
			$res['failed']               += count( $batch );
			continue;
		}
		foreach ( $body['data'] as $i => $ticket ) {
			if ( 'ok' === ( $ticket['status'] ?? '' ) ) {
				++$res['sent'];
				continue;
			}
			++$res['failed'];
			$GLOBALS['flxapp_push_error'] = (string) ( $ticket['message'] ?? ( $ticket['details']['error'] ?? 'error' ) );
			if ( 'DeviceNotRegistered' === ( $ticket['details']['error'] ?? '' ) && isset( $batch[ $i ] ) ) {
				$res['invalid'][] = $batch[ $i ]['to'];
			}
		}
	}
	return $res;
}

/* Çekirdek olay: yeni onay bekleyen yorum → yorum moderatörlerine. */
add_action(
	'comment_post',
	function ( $id, $approved ) {
		if ( 0 !== (int) $approved && '0' !== (string) $approved ) {
			return;
		}
		$c = get_comment( $id );
		if ( ! $c || ! flxapp_opt( 'push_enabled' ) ) {
			return;
		}
		$users = get_users( array( 'capability' => 'moderate_comments', 'fields' => 'ID', 'number' => 50 ) );
		do_action(
			'flxapp_push',
			$users,
			array(
				'event'  => 'core.comment',
				'title'  => __( 'Pending comments', 'flx-app' ),
				'body'   => $c->comment_author . ': ' . wp_trim_words( wp_strip_all_tags( $c->comment_content ), 16 ),
				'target' => 'screen:core/comments',
				'params' => array( 'status' => 'hold' ),
			)
		);
	},
	20,
	2
);
