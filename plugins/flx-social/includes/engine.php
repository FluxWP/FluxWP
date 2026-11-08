<?php
/**
 * Yayın motoru. Kuyruk sunucusu (Redis/Supervisor) yok; iş veritabanında durur, kısa adımlarla ilerler.
 * Tetikler: WP-Cron (ziyaretle), site kendine arka plan isteği (loopback), panel açıkken tarayıcı,
 * dışarıdan her dakika ping (Ayarlar'daki adres; cron-job.org ya da Docker sunucusu). Bir tetik en çok ~40 sn çalışır.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'flxsm_tick', fn() => flxsm_tick( 35, 'cron' ) );
add_action( 'flxsm_daily', 'flxsm_daily' );

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			FLXSM_NS,
			'/tick',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => 'flxsm_rest_tick',
				'permission_callback' => 'flxsm_tick_perm',
			)
		);
	}
);

function flxsm_tick_perm( WP_REST_Request $req ) {
	$key = (string) ( $req->get_param( 'key' ) ?? '' );
	if ( '' === $key ) {
		$key = (string) $req->get_header( 'x-flxsm-key' );
	}
	if ( '' !== $key ) {
		return hash_equals( (string) get_option( 'flxsm_tick_key', '' ), $key );
	}
	return is_user_logged_in() && flxsm_can_post();
}

function flxsm_rest_tick( WP_REST_Request $req ) {
	ignore_user_abort( true );
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 60 ); // phpcs:ignore
	}
	$via = $req->get_param( 'key' ) || $req->get_header( 'x-flxsm-key' ) ? ( $req->get_param( 'loop' ) ? 'loopback' : 'external' ) : 'panel';
	$n   = flxsm_tick( 'panel' === $via ? 20 : 40, $via );
	$r   = rest_ensure_response(
		array(
			'processed' => $n,
			'due'       => flxsm_due_count(),
			'time'      => time(),
		)
	);
	$r->header( 'Cache-Control', 'no-store' );
	return $r;
}

/** Kaç hedef işlenmeyi bekliyor (şimdi ya da 1 dk içinde). */
function flxsm_due_count( $within = 60 ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxsm_table( 'targets' ) . " WHERE status IN ('scheduled','processing') AND next_at <= %d", time() + $within ) ); // phpcs:ignore WordPress.DB
}

/** Arka planda kendine istek atar (yanıtı beklemez). 5 sn'de en çok bir kez. */
function flxsm_kick() {
	static $queued = false;
	if ( $queued || get_transient( 'flxsm_kick_lock' ) ) {
		return;
	}
	$queued = true;
	set_transient( 'flxsm_kick_lock', 1, 5 );
	$send = function () {
		$r = wp_remote_post(
			rest_url( FLXSM_NS . '/tick' ),
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions
				'body'      => array(
					'key'  => (string) get_option( 'flxsm_tick_key', '' ),
					'loop' => 1,
				),
			)
		);
		if ( is_wp_error( $r ) ) {
			update_option( 'flxsm_loopback_err', array( 'at' => time(), 'msg' => mb_substr( $r->get_error_message(), 0, 200 ) ), false );
		}
	};
	if ( did_action( 'shutdown' ) ) {
		$send();
	} else {
		add_action( 'shutdown', $send, 99 );
	}
}

/**
 * Sırası gelen hedefleri işler. Dönüş: işlenen adım sayısı.
 */
function flxsm_tick( $budget = 35, $via = 'cron' ) {
	global $wpdb;
	$start = microtime( true );
	$beat  = (array) get_option( 'flxsm_last_tick', array() );
	$beat[ $via ] = time();
	$beat['any']  = time();
	update_option( 'flxsm_last_tick', $beat, false );

	$table = flxsm_table( 'targets' );
	$n     = 0;
	$seen  = array();
	while ( microtime( true ) - $start < $budget ) {
		$now = time();
		$id  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE status IN ('scheduled','processing') AND next_at <= %d AND lock_until < %d ORDER BY next_at ASC, id ASC LIMIT 1", $now, $now ) ); // phpcs:ignore WordPress.DB
		if ( ! $id ) {
			break;
		}
		$got = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET lock_until = %d WHERE id = %d AND lock_until < %d", $now + 120, $id, $now ) ); // phpcs:ignore WordPress.DB
		if ( 1 !== (int) $got ) {
			continue;
		}
		$seen[ $id ] = ( $seen[ $id ] ?? 0 ) + 1;
		flxsm_process_target( $id, $start + $budget - microtime( true ) );
		$wpdb->update( $table, array( 'lock_until' => 0 ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		++$n;
		if ( $seen[ $id ] > 40 ) {
			break; // aynı hedef dönüp duruyorsa bu turu bırak
		}
	}
	if ( flxsm_due_count( 5 ) && 'loopback' !== $via ) {
		flxsm_kick();
	} elseif ( 'loopback' === $via && $n && flxsm_due_count( 5 ) ) {
		flxsm_kick();
	}
	return $n;
}

/** Tek hedefte bir adım. */
function flxsm_process_target( $id, $left = 30 ) {
	$t = flxsm_target_row( $id );
	if ( ! $t || ! in_array( $t['status'], array( 'scheduled', 'processing' ), true ) ) {
		return;
	}
	$post = flxsm_post_row( $t['post_id'] );
	$acc  = flxsm_account( $t['account_id'] );
	$prov = $acc ? flxsm_provider( $acc['provider'] ) : null;
	if ( ! $post || ! $acc || ! $prov ) {
		flxsm_target_fail( $t, __( 'Account or post not found.', 'flx-social' ) );
		return;
	}
	if ( 'disabled' === $acc['status'] ) {
		flxsm_target_fail( $t, __( 'Account is off.', 'flx-social' ) );
		return;
	}
	$acc = $prov->ensure( $acc );
	if ( is_wp_error( $acc ) ) {
		flxsm_account_failed( $t['account_id'], $acc->get_error_message(), true );
		flxsm_target_fail( $t, __( 'The connection expired; reconnect it under Accounts.', 'flx-social' ) . ' (' . $acc->get_error_message() . ')' );
		return;
	}
	$pl         = flxsm_target_payload( $t, $post );
	$pl['text'] = flxsm_apply_utm( $pl['text'], $acc['provider'] );
	if ( ! empty( $pl['options']['link'] ) ) {
		$pl['options']['link'] = flxsm_apply_utm( $pl['options']['link'], $acc['provider'] );
	}
	$state = flxsm_dec( $t['state'] );
	if ( 'scheduled' === $t['status'] ) {
		flxsm_update_target( $id, array( 'status' => 'processing' ) );
		flxsm_refresh_post_status( $t['post_id'] );
	}
	try {
		$res = $prov->publish_step( $acc, $pl, $state );
	} catch ( Throwable $e ) {
		$res = new WP_Error( 'flxsm_php', $e->getMessage(), array( 'retry' => false ) );
	}

	if ( is_wp_error( $res ) ) {
		$d = (array) $res->get_error_data();
		if ( ! empty( $d['auth'] ) ) {
			flxsm_account_failed( $t['account_id'], $res->get_error_message(), true );
			flxsm_target_fail( $t, $res->get_error_message() );
			return;
		}
		$att = (int) $t['attempts'] + 1;
		if ( ! empty( $d['retry'] ) && $att <= 5 ) {
			$wait = ! empty( $d['wait'] ) ? (int) $d['wait'] : 60 * ( 2 ** $att );
			flxsm_update_target(
				$id,
				array(
					'attempts' => $att,
					'next_at'  => time() + min( $wait, 6 * HOUR_IN_SECONDS ),
					'error'    => mb_substr( $res->get_error_message(), 0, 1000 ),
				)
			);
			flxsm_log( $res->get_error_message() . " (retry {$att})", 'warn', $t['account_id'], $id );
			return;
		}
		flxsm_target_fail( $t, $res->get_error_message() );
		return;
	}

	if ( ! empty( $res['done'] ) ) {
		$st = array();
		if ( ! empty( $res['note'] ) ) {
			$st['note'] = (string) $res['note'];
		}
		flxsm_update_target(
			$id,
			array(
				'status'       => 'published',
				'remote_id'    => (string) $res['id'],
				'remote_url'   => esc_url_raw( (string) $res['url'] ),
				'published_at' => time(),
				'state'        => $st,
				'error'        => '',
				'next_at'      => 0,
			)
		);
		flxsm_log( 'published ' . $res['id'], 'info', $t['account_id'], $id );
		$fc = trim( (string) ( $pl['options']['first_comment'] ?? '' ) );
		$c  = $prov->caps( $acc );
		if ( '' !== $fc && ! empty( $c['comment'] ) && '' !== (string) $res['id'] ) {
			$cr = $prov->comment( $acc, (string) $res['id'], flxsm_apply_utm( $fc, $acc['provider'] ) );
			if ( is_wp_error( $cr ) ) {
				/* translators: %s: error */
				flxsm_update_target( $id, array( 'error' => sprintf( __( 'The post was published but the first comment could not be added: %s', 'flx-social' ), $cr->get_error_message() ) ) );
			}
		}
		flxsm_refresh_post_status( $t['post_id'] );
		do_action( 'flxsm_target_published', $id, $res );
		return;
	}

	$wait = max( 0, (int) ( $res['wait'] ?? 10 ) );
	flxsm_update_target(
		$id,
		array(
			'state'   => (array) ( $res['state'] ?? array() ),
			'next_at' => time() + $wait,
			'error'   => '',
		)
	);
}

function flxsm_target_fail( array $t, $msg ) {
	flxsm_update_target(
		$t['id'],
		array(
			'status'  => 'failed',
			'error'   => mb_substr( (string) $msg, 0, 1000 ),
			'next_at' => 0,
			'state'   => '',
		)
	);
	flxsm_log( $msg, 'error', $t['account_id'], $t['id'] );
	flxsm_refresh_post_status( $t['post_id'] );
	do_action( 'flxsm_target_failed', (int) $t['id'], $msg );
}

/** Başarısız hedefi yeniden dener. */
function flxsm_retry_target( $id ) {
	$t = flxsm_target_row( $id );
	if ( ! $t || 'failed' !== $t['status'] ) {
		return new WP_Error( 'flxsm_state', __( 'Only a failed target can be retried.', 'flx-social' ), array( 'status' => 400 ) );
	}
	flxsm_update_target(
		$id,
		array(
			'status'   => 'scheduled',
			'next_at'  => time(),
			'attempts' => 0,
			'error'    => '',
			'state'    => '',
		)
	);
	flxsm_refresh_post_status( $t['post_id'] );
	flxsm_kick();
	return true;
}

/* ---------------- Ağda düzenle / sil ---------------- */

function flxsm_remote_ctx( $target_id ) {
	$t = flxsm_target_row( $target_id );
	if ( ! $t || 'published' !== $t['status'] || '' === $t['remote_id'] ) {
		return new WP_Error( 'flxsm_state', __( 'This target is not published on the network.', 'flx-social' ), array( 'status' => 400 ) );
	}
	$acc  = flxsm_account( $t['account_id'] );
	$prov = $acc ? flxsm_provider( $acc['provider'] ) : null;
	if ( ! $prov ) {
		return new WP_Error( 'flxsm_account', __( 'Account not found.', 'flx-social' ), array( 'status' => 404 ) );
	}
	$acc = $prov->ensure( $acc );
	if ( is_wp_error( $acc ) ) {
		return $acc;
	}
	return array( $t, $acc, $prov );
}

function flxsm_remote_delete( $target_id ) {
	$c = flxsm_remote_ctx( $target_id );
	if ( is_wp_error( $c ) ) {
		return $c;
	}
	list( $t, $acc, $prov ) = $c;
	$r                      = $prov->delete( $acc, $t['remote_id'] );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	flxsm_update_target(
		$target_id,
		array(
			'status'     => 'deleted',
			'remote_url' => '',
		)
	);
	flxsm_log( 'deleted ' . $t['remote_id'], 'info', $t['account_id'], $target_id );
	flxsm_refresh_post_status( $t['post_id'] );
	return true;
}

/** $text null ise kayıtlı metin; $options hesaba özel alanlarla birleşir. */
function flxsm_remote_edit( $target_id, $text = null, array $options = array() ) {
	$c = flxsm_remote_ctx( $target_id );
	if ( is_wp_error( $c ) ) {
		return $c;
	}
	list( $t, $acc, $prov ) = $c;
	$post                   = flxsm_post_row( $t['post_id'] );
	if ( null !== $text ) {
		$t['content'] = str_replace( "\r\n", "\n", wp_kses( (string) $text, array() ) );
	}
	if ( $options ) {
		$t['options'] = flxsm_json( array_merge( flxsm_dec( $t['options'] ), flxsm_clean_options( $options ) ) );
	}
	$pl         = flxsm_target_payload( $t, $post );
	$pl['text'] = flxsm_apply_utm( $pl['text'], $acc['provider'] );
	$r          = $prov->edit( $acc, $t['remote_id'], $pl );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	flxsm_update_target(
		$target_id,
		array(
			'content' => $t['content'],
			'options' => $t['options'],
		)
	);
	return true;
}

/* ---------------- Tekrar ---------------- */

add_action(
	'flxsm_post_status',
	function ( $post_id, $status ) {
		if ( 'published' !== $status ) {
			return;
		}
		$p = flxsm_post_row( $post_id );
		if ( ! $p || ! (int) $p['repeat_days'] ) {
			return;
		}
		$targets = array();
		foreach ( flxsm_targets_of( $post_id ) as $t ) {
			$targets[] = array(
				'account_id' => (int) $t['account_id'],
				'content'    => $t['content'],
				'media'      => null === $t['media'] ? null : flxsm_dec( $t['media'] ),
				'options'    => flxsm_dec( $t['options'] ),
			);
		}
		flxsm_save_post(
			array(
				'title'        => $p['title'],
				'content'      => $p['content'],
				'media'        => flxsm_dec( $p['media'] ),
				'tags'         => explode( ',', (string) $p['tags'] ),
				'repeat_days'  => (int) $p['repeat_days'],
				'scheduled_at' => max( time() + 3600, (int) $p['scheduled_at'] + (int) $p['repeat_days'] * DAY_IN_SECONDS ),
				'source'       => 'repeat',
				'user_id'      => (int) $p['user_id'],
				'targets'      => $targets,
				'action'       => 'schedule',
				'_system'      => true,
			)
		);
		// Eski kayıtta tekrar kapanır; zincir yeni kayıtla sürer.
		global $wpdb;
		$wpdb->update( flxsm_table( 'posts' ), array( 'repeat_days' => 0 ), array( 'id' => (int) $post_id ) ); // phpcs:ignore WordPress.DB
	},
	10,
	2
);

/* ---------------- Günlük işler ---------------- */

function flxsm_daily() {
	update_option( 'flxsm_last_daily', time(), false );
	flxsm_collect_metrics( 40 );
	// Süresi yaklaşan belirteçleri tazele (yenileme anahtarı olanlar).
	foreach ( flxsm_accounts() as $a ) {
		if ( $a['expires'] && $a['expires'] < time() + 3 * DAY_IN_SECONDS && 'disabled' !== $a['status'] ) {
			$acc  = flxsm_account( $a['id'] );
			$prov = flxsm_provider( $a['provider'] );
			if ( $prov && $acc ) {
				$acc['expires'] = min( (int) $acc['expires'], time() + 60 ); // ensure() yenilesin
				$r              = $prov->ensure( $acc );
				if ( is_wp_error( $r ) ) {
					flxsm_account_failed( $a['id'], $r->get_error_message(), $a['expires'] < time() );
				}
			}
		}
	}
}
