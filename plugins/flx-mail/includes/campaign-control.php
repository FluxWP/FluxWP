<?php
/**
 * Kampanya kontrolü: duraklat / devam ettir, tahmini bitiş, alıcı listesi.
 *
 * Duraklatma kuyruk satırlarını 'paused' durumuna alır; kuyruk işleyicisi yalnız 'queued' satırları
 * seçtiği için işleyicide değişiklik gerekmez (çalışan gönderim bozulmaz). O an gönderilmekte olan tek
 * satır biter, sonrakiler bekler. Devam ettirince kaldığı yerden sürer.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/** Kampanyayı duraklatır (WhatsApp, e-posta, SMS). @return bool */
function flxi_pause_campaign( $id ) {
	global $wpdb;
	$c = flxi_get_campaign( $id );
	if ( ! $c || ! empty( $c['data']['stopped'] ) || in_array( $c['status'], array( 'done', 'cancelled' ), true ) ) {
		return false;
	}
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . flxi_table( 'queue' ) . " SET status = 'paused' WHERE campaign_id = %d AND status = 'queued'", (int) $id ) ); // phpcs:ignore
	$c['data']['paused']    = true;
	$c['data']['paused_at'] = time();
	$c['data']['paused_by'] = get_current_user_id();
	$wpdb->update( flxi_table( 'campaigns' ), array( 'data' => flxi_json( $c['data'] ) ), array( 'id' => (int) $id ) );
	flxi_refresh_campaign( $id );
	flx_background_log( 'campaign.paused', array( 'plugin' => 'flx-mail', 'campaign' => (int) $id ) );
	do_action( 'flxi_campaign_paused', (int) $id );
	return true;
}

/** Duraklatılan kampanyayı kaldığı yerden sürdürür. @return bool */
function flxi_resume_campaign( $id ) {
	global $wpdb;
	$c = flxi_get_campaign( $id );
	if ( ! $c || ! empty( $c['data']['stopped'] ) ) {
		return false;
	}
	$now = flxi_now();
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . flxi_table( 'queue' ) . " SET status = 'queued', scheduled_at = CASE WHEN scheduled_at < %s THEN %s ELSE scheduled_at END WHERE campaign_id = %d AND status = 'paused'", $now, $now, (int) $id ) ); // phpcs:ignore
	unset( $c['data']['paused'], $c['data']['paused_at'], $c['data']['paused_by'] );
	$wpdb->update( flxi_table( 'campaigns' ), array( 'data' => flxi_json( $c['data'] ) ), array( 'id' => (int) $id ) );
	flxi_refresh_campaign( $id );
	flx_background_log( 'campaign.resumed', array( 'plugin' => 'flx-mail', 'campaign' => (int) $id ) );
	do_action( 'flxi_campaign_resumed', (int) $id );
	flxi_kick_queue();
	return true;
}

/** Kampanyanın kalan (gönderilecek) satır sayısı ve en erken zamanı. */
function flxi_campaign_left( $id ) {
	global $wpdb;
	$t   = flxi_table( 'queue' );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) n, MIN(scheduled_at) first FROM $t WHERE campaign_id = %d AND status IN ('queued','processing','paused')", (int) $id ), ARRAY_A ); // phpcs:ignore
	return array( (int) ( $row['n'] ?? 0 ), ! empty( $row['first'] ) ? (int) strtotime( $row['first'] . ' UTC' ) : 0 );
}

/**
 * Tahmini bitiş (Unix). Kalan yoksa, kampanya duraklatıldıysa ya da durdurulduysa null.
 * WhatsApp'ta ban koruması (bekleme aralığı, mola, saatlik/günlük sınır, ısınma, sessiz saatler) ve aynı
 * bağlantıda sırada önde bekleyen gönderimler, e-postada tur başına gönderim ve posta kutusu günlük kotası
 * hesaba katılır. Tahmindir; sağlayıcı yavaşlığı ve yeniden denemeler süreyi uzatabilir.
 */
function flxi_campaign_eta( $id ) {
	$c = flxi_get_campaign( $id );
	if ( ! $c || ! empty( $c['data']['stopped'] ) || ! empty( $c['data']['paused'] ) ) {
		return null;
	}
	list( $left, $first ) = flxi_campaign_left( $id );
	if ( ! $left ) {
		return null;
	}
	$now   = time();
	$start = max( $now, $first );
	$eta   = null;
	if ( 'whatsapp' === $c['channel'] ) {
		$conn = flxi_resolve_connection( 'whatsapp', (int) ( $c['data']['connection_id'] ?? 0 ) );
		if ( $conn && flxi_wa_guard_applies( $conn ) ) {
			$ahead = flxi_campaign_ahead( $c, $conn );
			$eta   = flxi_wa_guard_simulate( $conn, $ahead + $left, $start );
		} else {
			$eta = $start + (int) ceil( $left * 1.5 );
		}
	} elseif ( 'email' === $c['channel'] ) {
		$per  = max( 1, (int) flxi_setting( 'bulk_per_run' ) );
		$rate = flx_background_worker_active() ? 2 : 60; // saniyede bir tur (işçi) ya da dakikada bir (WP-Cron)
		$snd  = ( $c['data']['sender'] ?? 'info' ) === 'noreply' ? 'noreply' : 'info';
		$days = function_exists( 'flxi_quota_days_for' ) ? flxi_quota_days_for( $left, $snd ) : 0;
		$eta  = $start + (int) ceil( $left / $per ) * $rate;
		if ( $days ) {
			// Günlük kota dolunca kalanlar ertesi günlere kayar.
			$eta = max( $eta, flxi_quota_next_free( flxi_quota_mailbox( $snd ) ) + ( $days - 1 ) * DAY_IN_SECONDS + 600 );
		}
	} else {
		$eta = $start + 60 + (int) ceil( $left * 0.5 );
	}
	return (int) apply_filters( 'flxi_campaign_eta', $eta, $c, $left );
}

/** Aynı WhatsApp bağlantısında bu kampanyadan önce kuyrukta bekleyen mesaj sayısı. */
function flxi_campaign_ahead( array $c, array $conn ) {
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxi_table( 'campaigns' ) . " WHERE channel = 'whatsapp' AND id < %d AND status IN ('queued','running')", (int) $c['id'] ) ); // phpcs:ignore
	$n   = 0;
	foreach ( $ids as $oid ) {
		$o = flxi_get_campaign( $oid );
		if ( $o && empty( $o['data']['paused'] ) && empty( $o['data']['stopped'] ) ) {
			$oc = flxi_resolve_connection( 'whatsapp', (int) ( $o['data']['connection_id'] ?? 0 ) );
			if ( $oc && (int) $oc['id'] === (int) $conn['id'] ) {
				$n += (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxi_table( 'queue' ) . " WHERE campaign_id = %d AND status IN ('queued','processing')", (int) $oid ) ); // phpcs:ignore
			}
		}
	}
	return $n;
}

/** Sessiz saatlerin bittiği an (rastgele pay olmadan); sessiz değilse 0. */
function flxi_wa_guard_quiet_end( array $g, $t ) {
	if ( null === $g['quiet_from'] || null === $g['quiet_to'] || $g['quiet_from'] === $g['quiet_to'] ) {
		return 0;
	}
	$dt = ( new DateTimeImmutable( '@' . (int) $t ) )->setTimezone( wp_timezone() );
	$h  = (int) $dt->format( 'G' );
	$in = $g['quiet_from'] < $g['quiet_to'] ? ( $h >= $g['quiet_from'] && $h < $g['quiet_to'] ) : ( $h >= $g['quiet_from'] || $h < $g['quiet_to'] );
	if ( ! $in ) {
		return 0;
	}
	$end = $dt->setTime( $g['quiet_to'], 0 );
	if ( $end <= $dt ) {
		$end = $end->modify( '+1 day' );
	}
	return $end->getTimestamp() + 300;
}

/**
 * Ban korumasının kurallarıyla $n mesajın ne zaman biteceğini canlandırır (ortalama bekleme ile).
 *
 * @return int Unix
 */
function flxi_wa_guard_simulate( array $conn, $n, $t ) {
	$g     = flxi_wa_guard_conf( $conn );
	$s     = flxi_wa_guard_state( $conn['id'] );
	$t     = max( (int) $t, (int) $s['next'], (int) $s['paused'] );
	$hits  = array_map( 'intval', (array) $s['hits'] );
	sort( $hits );
	$batch = (int) $s['batch'];
	$step  = ( $g['min'] + $g['max'] ) / 2 + 4; // ortalama bekleme + "yazıyor…" + gönderim
	$born  = ! empty( $conn['created_at'] ) ? strtotime( $conn['created_at'] . ' UTC' ) : 0;
	$cap   = function ( $at ) use ( $g, $born ) {
		$c = $g['daily'];
		if ( $g['warmup'] && $born ) {
			$days = max( 0, (int) floor( ( $at - $born ) / DAY_IN_SECONDS ) );
			$warm = 20 * ( $days + 1 );
			$c    = $c ? min( $c, $warm ) : ( $days < 14 ? $warm : 0 );
		}
		return (int) $c;
	};
	$n     = (int) $n;
	$sim   = min( $n, 20000 );
	for ( $i = 0; $i < $sim; $i++ ) {
		for ( $guard = 0; $guard < 50; $guard++ ) {
			$move = 0;
			$q    = flxi_wa_guard_quiet_end( $g, $t );
			if ( $q ) {
				$move = $q;
			}
			// Son 24 saatten eskiyi at.
			while ( $hits && $hits[0] <= $t - DAY_IN_SECONDS ) {
				array_shift( $hits );
			}
			if ( $g['hourly'] ) {
				$hour = 0;
				for ( $k = count( $hits ) - 1; $k >= 0 && $hits[ $k ] > $t - HOUR_IN_SECONDS; $k-- ) {
					++$hour;
				}
				if ( $hour >= $g['hourly'] ) {
					$move = max( $move, $hits[ count( $hits ) - $g['hourly'] ] + HOUR_IN_SECONDS + 5 );
				}
			}
			$dc = $cap( $t );
			if ( $dc && count( $hits ) >= $dc ) {
				$move = max( $move, $hits[ count( $hits ) - $dc ] + DAY_IN_SECONDS + 60 );
			}
			if ( $move <= $t ) {
				break;
			}
			$t = (int) $move;
		}
		$hits[] = (int) $t;
		++$batch;
		$t += (int) round( $step );
		if ( $g['batch'] && $g['pause'] && 0 === $batch % $g['batch'] ) {
			$t += (int) round( $g['pause'] * MINUTE_IN_SECONDS * 1.15 );
		}
	}
	if ( $n > $sim ) {
		$t += (int) ( ( $t - time() ) / max( 1, $sim ) * ( $n - $sim ) );
	}
	return (int) $t;
}

/**
 * Kampanyanın durum özeti (CRM kartı, Gönder ekranı).
 *
 * @return array|null id, name, channel, status (queued|running|paused|done|cancelled|reporting|review), total, sent, failed,
 *                    skipped, left, paused, eta (Unix|null), wait_until, wait_reason
 */
function flxi_campaign_progress( $id ) {
	$c = flxi_get_campaign( $id );
	if ( ! $c ) {
		return null;
	}
	list( $left ) = flxi_campaign_left( $id );
	$wait         = (int) ( $c['data']['wait_until'] ?? 0 );
	return array(
		'id'          => (int) $c['id'],
		'name'        => (string) $c['name'],
		'channel'     => (string) $c['channel'],
		'status'      => (string) $c['status'],
		'total'       => (int) $c['total'],
		'sent'        => (int) $c['sent'],
		'failed'      => (int) $c['failed'],
		'skipped'     => (int) $c['skipped'],
		'left'        => $left,
		'paused'      => ! empty( $c['data']['paused'] ),
		'eta'         => flxi_campaign_eta( $id ),
		'wait_until'  => $wait > time() ? $wait : 0,
		'wait_reason' => $wait > time() ? (string) ( $c['data']['wait_reason'] ?? '' ) : '',
	);
}

/**
 * Kampanyanın alıcıları (kuyruk satırları).
 *
 * @param array $args status ('' tümü; 'sent', 'failed,skipped' gibi virgüllü), per_page (en çok 200), page, s (alıcı/ad arama)
 * @return array rows [ id, name, recipient, status, label, error, sent_at, scheduled_at, delivered, read, replied, optout,
 *               delivered_at, read_at, replied_at ] (tarihler GMT; bilinmeyen null), total
 */
function flxi_campaign_recipients( $id, array $args = array() ) {
	global $wpdb;
	$t    = flxi_table( 'queue' );
	$per  = min( 200, max( 1, (int) ( $args['per_page'] ?? 50 ) ) );
	$page = max( 1, (int) ( $args['page'] ?? 1 ) );
	$w    = array( 'campaign_id = %d' );
	$p    = array( (int) $id );
	$st   = array_filter( array_map( 'sanitize_key', explode( ',', (string) ( $args['status'] ?? '' ) ) ) );
	if ( $st ) {
		// "sent" kuyrukta Netgsm için delivered/submitted olarak da tutulur.
		if ( in_array( 'sent', $st, true ) ) {
			$st[] = 'delivered';
		}
		$w[] = 'status IN (' . implode( ',', array_fill( 0, count( $st ), '%s' ) ) . ')';
		$p   = array_merge( $p, array_values( array_unique( $st ) ) );
	}
	$s = trim( (string) ( $args['s'] ?? '' ) );
	if ( '' !== $s ) {
		$like = '%' . $wpdb->esc_like( $s ) . '%';
		$w[]  = '(recipient LIKE %s OR name LIKE %s)';
		$p[]  = $like;
		$p[]  = $like;
	}
	$where = implode( ' AND ', $w );
	$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE $where", $p ) ); // phpcs:ignore
	$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, name, recipient, status, error, sent_at, scheduled_at, ext_id FROM $t WHERE $where ORDER BY id ASC LIMIT %d OFFSET %d", array_merge( $p, array( $per, ( $page - 1 ) * $per ) ) ), ARRAY_A ); // phpcs:ignore
	$lab   = function_exists( 'flxi_queue_labels' ) ? flxi_queue_labels() : array();
	$c     = flxi_get_campaign( $id );
	$eng   = ( $c && function_exists( 'flxi_engagement_rows' ) ) ? flxi_engagement_rows( $c, $rows ) : array();
	foreach ( $rows as &$r ) {
		$r += array_intersect_key( (array) ( $eng[ $r['id'] ] ?? array() ), array_flip( array( 'delivered', 'read', 'replied', 'optout', 'delivered_at', 'read_at', 'replied_at' ) ) );
		unset( $r['ext_id'] );
		$r['id']    = (int) $r['id'];
		$r['label'] = (string) ( $lab[ $r['status'] ] ?? $r['status'] );
		$r['error'] = (string) $r['error'];
	}
	unset( $r );
	return array( 'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max( 1, (int) ceil( $total / $per ) ) );
}

/** "Tahmini bitiş: 13 Kasım 15:40 (yaklaşık 2 saat)" */
function flxi_campaign_eta_label( $eta ) {
	if ( ! $eta ) {
		return '';
	}
	/* translators: 1: tarih saat, 2: "2 saat sonra" gibi */
	return sprintf( __( 'Estimated finish: %1$s (%2$s)', 'flx-mail' ), flx_date( $eta, 'datetime' ), flx_time_ago( $eta ) );
}

add_filter(
	'flxi_campaign_features',
	function ( $f ) {
		return array_values( array_unique( array_merge( (array) $f, array( 'pause', 'eta', 'recipients', 'progress' ) ) ) );
	},
	20
);

add_action(
	'admin_post_flxi_pause_campaign',
	function () {
		flxi_check( 'flxi_pause_campaign' );
		$id = (int) ( $_POST['id'] ?? 0 ); // phpcs:ignore
		flxi_pause_campaign( $id );
		flxi_redirect( flxi_back( flxi_admin_url( 'flxi-gonder' ) ), __( 'Sending paused. Press Resume to continue where it stopped.', 'flx-mail' ) );
	}
);

add_action(
	'admin_post_flxi_resume_campaign',
	function () {
		flxi_check( 'flxi_resume_campaign' );
		$id = (int) ( $_POST['id'] ?? 0 ); // phpcs:ignore
		flxi_resume_campaign( $id );
		flxi_redirect( flxi_back( flxi_admin_url( 'flxi-gonder' ) ), __( 'Sending resumed.', 'flx-mail' ) );
	}
);

/** Gönder ekranı geçmişinde: tahmini bitiş + Duraklat / Devam et. */
function flxi_campaign_actions_html( array $r ) {
	$data = (array) json_decode( (string) ( $r['data'] ?? '' ), true );
	if ( ! empty( $data['paused'] ) && 'paused' === $r['status'] ) {
		return flxi_post_button( 'flxi_resume_campaign', array( 'id' => (int) $r['id'] ), __( 'Resume', 'flx-mail' ), '', 'button button-small' );
	}
	if ( ! in_array( $r['status'], array( 'queued', 'running' ), true ) || ! empty( $data['stopped'] ) ) {
		return '';
	}
	$eta = flxi_campaign_eta( (int) $r['id'] );
	return ( $eta ? '<div class="flxi-muted flxi-eta">' . esc_html( flxi_campaign_eta_label( $eta ) ) . '</div>' : '' ) . flxi_post_button( 'flxi_pause_campaign', array( 'id' => (int) $r['id'] ), __( 'Pause', 'flx-mail' ), '', 'button button-small' );
}
