<?php
/**
 * WhatsApp ban koruması (Evolution API / Flux WPAPI, resmî olmayan bağlantılar).
 *
 * Toplu gönderim kuyruğu her mesajdan önce bağlantının "kapısına" bakar:
 * - mesajlar arası rastgele bekleme (en az / en çok saniye),
 * - N mesajda bir uzun mola,
 * - saatlik ve günlük üst sınır (yeni numarada günlük sınır ısınma ile yavaş yavaş artar),
 * - sessiz saatler (ör. 21:00–09:00 arası toplu mesaj gitmez),
 * - art arda hata olursa gönderimi bir süre durdurma (numara kısıtlanmış/kopmuş olabilir),
 * - WhatsApp'ı olmayan numaraları atlama (bu numaralara mesaj denemek risk puanını artırır).
 *
 * Tekil mesajlar (sohbet cevabı, sertifika, bildirim) bu kapıya takılmaz; yalnız kuyruk.
 * Durum `flxi_wa_guard` seçeneğinde bağlantı başına tutulur.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/** Bağlantı ayarlarına eklenen ban koruması alanları (Evolution ve Flux WPAPI). */
function flxi_wa_guard_fields() {
	$hours = array( '' => __( 'Off', 'flx-mail' ) );
	for ( $h = 0; $h < 24; $h++ ) {
		$hours[ (string) $h ] = sprintf( '%02d:00', $h );
	}
	return array(
		array( 'key' => 'guard_heading', 'type' => 'heading', 'label' => __( 'Ban protection (bulk sends)', 'flx-mail' ), 'help' => __( 'WhatsApp restricts numbers that send many similar messages quickly. These limits apply only to bulk sends from the queue; replies in chats and single messages are not delayed.', 'flx-mail' ) ),
		array( 'key' => 'delay', 'label' => __( 'Minimum wait between messages (sec)', 'flx-mail' ), 'type' => 'number', 'default' => 8 ),
		array( 'key' => 'delay_max', 'label' => __( 'Maximum wait between messages (sec)', 'flx-mail' ), 'type' => 'number', 'default' => 20, 'help' => __( 'Each wait is a random time between the minimum and the maximum, so the sending does not look like a machine.', 'flx-mail' ) ),
		array( 'key' => 'batch_size', 'label' => __( 'Take a break every … messages', 'flx-mail' ), 'type' => 'number', 'default' => 25, 'help' => __( '0 = no break.', 'flx-mail' ) ),
		array( 'key' => 'batch_pause', 'label' => __( 'Break length (min)', 'flx-mail' ), 'type' => 'number', 'default' => 10 ),
		array( 'key' => 'hourly_limit', 'label' => __( 'Bulk messages per hour (at most)', 'flx-mail' ), 'type' => 'number', 'default' => 60, 'help' => __( '0 = no limit.', 'flx-mail' ) ),
		array( 'key' => 'daily_limit', 'label' => __( 'Bulk messages per day (at most)', 'flx-mail' ), 'type' => 'number', 'default' => 250, 'help' => __( 'Counted over the last 24 hours. The rest of the list waits and continues by itself. 0 = no limit.', 'flx-mail' ) ),
		array( 'key' => 'warmup', 'label' => __( 'Warm up a new number (daily limit starts at 20 and grows by 20 every day)', 'flx-mail' ), 'type' => 'checkbox', 'default' => 1 ),
		array( 'key' => 'quiet_from', 'label' => __( 'Quiet hours: from', 'flx-mail' ), 'type' => 'select', 'default' => '21', 'options' => $hours ),
		array( 'key' => 'quiet_to', 'label' => __( 'Quiet hours: until', 'flx-mail' ), 'type' => 'select', 'default' => '9', 'options' => $hours, 'help' => __( 'Bulk messages are not sent in these hours (site time zone); the queue continues in the morning.', 'flx-mail' ) ),
		array( 'key' => 'skip_no_wa', 'label' => __( 'Skip numbers that do not have WhatsApp (checked before sending)', 'flx-mail' ), 'type' => 'checkbox', 'default' => 1 ),
		array( 'key' => 'fail_stop', 'label' => __( 'Pause bulk sending for 30 minutes after … failures in a row', 'flx-mail' ), 'type' => 'number', 'default' => 5, 'help' => __( 'Many failures in a row usually mean the number was disconnected or restricted. 0 = never pause.', 'flx-mail' ) ),
		array( 'key' => 'typing_auto', 'label' => __( 'Show "typing…" for a time that fits the message length', 'flx-mail' ), 'type' => 'checkbox', 'default' => 1 ),
	);
}

add_filter(
	'flxi_connection_types',
	function ( $types ) {
		foreach ( array( 'evolution', 'flxwpapi' ) as $k ) {
			if ( empty( $types[ $k ]['fields'] ) ) {
				continue;
			}
			$fields = array_values(
				array_filter(
					$types[ $k ]['fields'],
					function ( $f ) {
						return 'delay' !== $f['key'];
					}
				)
			);
			$types[ $k ]['fields'] = array_merge( $fields, flxi_wa_guard_fields() );
		}
		return $types;
	},
	20
);

/** Bu bağlantıda ban koruması var mı? (Cloud API resmî, kendi kotası var.) */
function flxi_wa_guard_applies( $conn ) {
	return is_array( $conn ) && 'whatsapp' === ( $conn['channel'] ?? '' ) && function_exists( 'flxi_is_evo_type' ) && flxi_is_evo_type( $conn['type'] ?? '' );
}

/** Bağlantının koruma ayarları (eksikler varsayılan). */
function flxi_wa_guard_conf( $conn ) {
	$c   = (array) ( $conn['config'] ?? array() );
	$get = function ( $k, $def ) use ( $c ) {
		return array_key_exists( $k, $c ) && '' !== $c[ $k ] ? $c[ $k ] : $def;
	};
	$min = max( 0, (int) $get( 'delay', 8 ) );
	$max = max( $min, (int) $get( 'delay_max', max( $min, 20 ) ) );
	return array(
		'min'        => $min,
		'max'        => $max,
		'batch'      => max( 0, (int) $get( 'batch_size', 25 ) ),
		'pause'      => max( 0, (int) $get( 'batch_pause', 10 ) ),
		'hourly'     => max( 0, (int) $get( 'hourly_limit', 60 ) ),
		'daily'      => max( 0, (int) $get( 'daily_limit', 250 ) ),
		'warmup'     => ! empty( $get( 'warmup', 1 ) ),
		'quiet_from' => '' === (string) $get( 'quiet_from', '' ) ? null : (int) $get( 'quiet_from', 21 ),
		'quiet_to'   => '' === (string) $get( 'quiet_to', '' ) ? null : (int) $get( 'quiet_to', 9 ),
		'skip_no_wa' => ! empty( $get( 'skip_no_wa', 1 ) ),
		'fail_stop'  => max( 0, (int) $get( 'fail_stop', 5 ) ),
	);
}

function flxi_wa_guard_all() {
	$d = get_option( 'flxi_wa_guard', array() );
	return is_array( $d ) ? $d : array();
}

function flxi_wa_guard_state( $id ) {
	$d = flxi_wa_guard_all();
	return wp_parse_args( (array) ( $d[ (int) $id ] ?? array() ), array( 'next' => 0, 'batch' => 0, 'hits' => array(), 'fails' => 0, 'paused' => 0, 'wait' => 0, 'reason' => '' ) );
}

function flxi_wa_guard_save( $id, array $s ) {
	$d               = flxi_wa_guard_all();
	$cut             = time() - DAY_IN_SECONDS;
	$s['hits']       = array_values(
		array_filter(
			(array) $s['hits'],
			function ( $t ) use ( $cut ) {
				return (int) $t > $cut;
			}
		)
	);
	$d[ (int) $id ] = $s;
	update_option( 'flxi_wa_guard', $d, false );
}

/** Isınma dahil bugünkü günlük sınır (0: sınırsız). */
function flxi_wa_guard_daily_cap( $conn, $g = null ) {
	$g = $g ? $g : flxi_wa_guard_conf( $conn );
	$cap = $g['daily'];
	if ( $g['warmup'] && ! empty( $conn['created_at'] ) ) {
		$days = max( 0, (int) floor( ( time() - strtotime( $conn['created_at'] . ' UTC' ) ) / DAY_IN_SECONDS ) );
		$warm = 20 * ( $days + 1 );
		$cap  = $cap ? min( $cap, $warm ) : ( $days < 14 ? $warm : 0 );
	}
	return (int) $cap;
}

/** Şimdi sessiz saatlerde miyiz? Evetse sessizliğin bittiği an (Unix), değilse 0. */
function flxi_wa_guard_quiet_until( $g, $now = null ) {
	if ( null === $g['quiet_from'] || null === $g['quiet_to'] || $g['quiet_from'] === $g['quiet_to'] ) {
		return 0;
	}
	$tz  = wp_timezone();
	$dt  = ( new DateTimeImmutable( '@' . ( $now ? $now : time() ) ) )->setTimezone( $tz );
	$h   = (int) $dt->format( 'G' );
	$in  = $g['quiet_from'] < $g['quiet_to'] ? ( $h >= $g['quiet_from'] && $h < $g['quiet_to'] ) : ( $h >= $g['quiet_from'] || $h < $g['quiet_to'] );
	if ( ! $in ) {
		return 0;
	}
	$end = $dt->setTime( $g['quiet_to'], 0 );
	if ( $end <= $dt ) {
		$end = $end->modify( '+1 day' );
	}
	return $end->getTimestamp() + wp_rand( 0, 600 );
}

/**
 * Bu bağlantıdan şimdi toplu mesaj gidebilir mi?
 *
 * @return array { wait: saniye (0 = gidebilir), until: Unix, reason: pause|quiet|delay|hourly|daily }
 */
function flxi_wa_guard_check( $conn ) {
	$now = time();
	if ( ! flxi_wa_guard_applies( $conn ) ) {
		return array( 'wait' => 0, 'until' => $now, 'reason' => '' );
	}
	$g     = flxi_wa_guard_conf( $conn );
	$s     = flxi_wa_guard_state( $conn['id'] );
	$until = 0;
	$why   = '';
	$push  = function ( $t, $r ) use ( &$until, &$why ) {
		if ( $t > $until ) {
			$until = (int) $t;
			$why   = $r;
		}
	};
	if ( $s['paused'] > $now ) {
		$push( $s['paused'], 'pause' );
	}
	$quiet = flxi_wa_guard_quiet_until( $g, $now );
	if ( $quiet ) {
		$push( $quiet, 'quiet' );
	}
	if ( $s['next'] > $now ) {
		$push( $s['next'], 'delay' );
	}
	$hits = array_map( 'intval', (array) $s['hits'] );
	sort( $hits );
	if ( $g['hourly'] ) {
		$hour = array_values(
			array_filter(
				$hits,
				function ( $t ) use ( $now ) {
					return $t > $now - HOUR_IN_SECONDS;
				}
			)
		);
		if ( count( $hour ) >= $g['hourly'] ) {
			$push( $hour[ count( $hour ) - $g['hourly'] ] + HOUR_IN_SECONDS + 5, 'hourly' );
		}
	}
	$cap = flxi_wa_guard_daily_cap( $conn, $g );
	if ( $cap ) {
		$day = array_values(
			array_filter(
				$hits,
				function ( $t ) use ( $now ) {
					return $t > $now - DAY_IN_SECONDS;
				}
			)
		);
		if ( count( $day ) >= $cap ) {
			$push( $day[ count( $day ) - $cap ] + DAY_IN_SECONDS + 60, 'daily' );
		}
	}
	return array( 'wait' => max( 0, $until - $now ), 'until' => max( $now, $until ), 'reason' => $why );
}

/** Toplu mesaj gönderildikten sonra: sayaç, sonraki bekleme, mola, art arda hata. */
function flxi_wa_guard_after( $conn, array $res ) {
	if ( ! flxi_wa_guard_applies( $conn ) ) {
		return;
	}
	$g   = flxi_wa_guard_conf( $conn );
	$s   = flxi_wa_guard_state( $conn['id'] );
	$now = time();
	if ( ! empty( $res['skip'] ) ) {
		return; // gönderim denenmedi (numara WhatsApp'ta yok, IPTAL, kara liste)
	}
	if ( ! empty( $res['ok'] ) ) {
		$s['hits'][] = $now;
		$s['fails']  = 0;
		$s['batch']  = (int) $s['batch'] + 1;
		$s['next']   = $now + wp_rand( $g['min'], $g['max'] );
		if ( $g['batch'] && $s['batch'] >= $g['batch'] ) {
			$s['batch'] = 0;
			$pause      = $g['pause'] * MINUTE_IN_SECONDS;
			$s['next']  = $now + $pause + wp_rand( 0, (int) ceil( $pause * 0.3 ) );
		}
	} else {
		$s['fails'] = (int) $s['fails'] + 1;
		$s['next']  = $now + wp_rand( $g['min'], $g['max'] );
		if ( $g['fail_stop'] && $s['fails'] >= $g['fail_stop'] ) {
			$s['fails']  = 0;
			$s['paused'] = $now + 30 * MINUTE_IN_SECONDS;
			flx_background_log( 'wa_guard.paused', array( 'plugin' => 'flx-mail', 'connection' => (int) $conn['id'] ) );
			do_action( 'flxi_wa_guard_paused', (int) $conn['id'], (string) ( $res['error'] ?? '' ) );
		}
	}
	flxi_wa_guard_save( $conn['id'], $s );
}

/** Bekleme nedeninin okunur hali (kuyruk ekranı). */
function flxi_wa_guard_reason_label( $reason ) {
	$map = array(
		'pause'  => __( 'Paused after failures in a row', 'flx-mail' ),
		'quiet'  => __( 'Quiet hours', 'flx-mail' ),
		'delay'  => __( 'Waiting between messages', 'flx-mail' ),
		'hourly' => __( 'Hourly limit reached', 'flx-mail' ),
		'daily'  => __( 'Daily limit reached', 'flx-mail' ),
	);
	return $map[ $reason ] ?? '';
}

/** Elle devam: art arda hata molasını kaldırır. */
function flxi_wa_guard_resume( $id ) {
	$s           = flxi_wa_guard_state( $id );
	$s['paused'] = 0;
	$s['fails']  = 0;
	flxi_wa_guard_save( $id, $s );
}

/**
 * Numara WhatsApp'ta var mı? (Evolution / Flux WPAPI /chat/whatsappNumbers). 30 gün önbellek.
 *
 * @return bool|null null: bilinmiyor (istek başarısız) — gönderim denenir
 */
function flxi_wa_exists( $conn, $to ) {
	if ( ! flxi_wa_guard_applies( $conn ) || flxi_is_wa_group( $to ) ) {
		return null;
	}
	$num = flxi_normalize_phone( $to );
	if ( ! $num ) {
		return false;
	}
	$key = 'flxi_waex_' . md5( (int) $conn['id'] . '|' . $num );
	$hit = get_transient( $key );
	if ( false !== $hit ) {
		return '1' === $hit;
	}
	$r = flxi_evolution_request( $conn['config'], '/chat/whatsappNumbers/' . rawurlencode( (string) $conn['config']['instance'] ), array( 'numbers' => array( $num ) ) );
	if ( ! $r['ok'] || ! is_array( $r['json'] ) ) {
		return null;
	}
	$row = $r['json'][0] ?? ( $r['json']['numbers'][0] ?? null );
	if ( ! is_array( $row ) || ! array_key_exists( 'exists', $row ) ) {
		return null;
	}
	$ok = ! empty( $row['exists'] );
	set_transient( $key, $ok ? '1' : '0', ( $ok ? 30 : 7 ) * DAY_IN_SECONDS );
	return $ok;
}

/**
 * Metin çeşitlemesi: {Merhaba|Selam|İyi günler} her alıcıda rastgele biri. Aynı metnin yüzlerce kez
 * gitmesi kısıtlanma riskini artırır. Değişkenler ({ad}) dokunulmaz (içinde | yok).
 */
function flxi_spin( $text ) {
	$text = (string) $text;
	for ( $i = 0; $i < 5 && false !== strpos( $text, '|' ); $i++ ) {
		$new = preg_replace_callback(
			'/\{([^{}]*\|[^{}]*)\}/u',
			function ( $m ) {
				$opts = explode( '|', $m[1] );
				return $opts[ wp_rand( 0, count( $opts ) - 1 ) ];
			},
			$text
		);
		if ( $new === $text || null === $new ) {
			break;
		}
		$text = $new;
	}
	return $text;
}

/** "Yazıyor…" süresi (ms): mesaj uzunluğuna göre, 1–8 sn, biraz rastgele. */
function flxi_wa_typing_ms( $config, $text ) {
	$base = max( 0, (int) ( $config['typing'] ?? 0 ) );
	if ( empty( $config['typing_auto'] ) ) {
		return $base; // kapalı ya da 1.17.0 öncesi kaydedilmiş bağlantı
	}
	$ms = $base + mb_strlen( (string) $text ) * 30;
	$ms = (int) round( $ms * wp_rand( 80, 120 ) / 100 );
	return max( 1000, min( 8000, $ms ) );
}

/* Diğer eklentiler (Flux CRM) kampanyanın neleri desteklediğini buradan öğrenir. */
add_filter(
	'flxi_campaign_features',
	function ( $f ) {
		return array_values( array_unique( array_merge( (array) $f, array( 'media', 'kind', 'ref', 'optout', 'wa_guard', 'spin', 'recipient_vars' ) ) ) );
	}
);

/** Ban koruması özeti (MCP, sistem kontrolü). */
function flxi_wa_guard_summary() {
	$out = array();
	foreach ( flxi_list_connections( 'whatsapp', true ) as $c ) {
		if ( ! flxi_wa_guard_applies( $c ) ) {
			continue;
		}
		$s    = flxi_wa_guard_state( $c['id'] );
		$now  = time();
		$hits = array_map( 'intval', (array) $s['hits'] );
		$gate = flxi_wa_guard_check( $c );
		$out[] = array(
			'id'         => (int) $c['id'],
			'name'       => (string) $c['name'],
			'last_hour'  => count( array_filter( $hits, function ( $t ) use ( $now ) { return $t > $now - HOUR_IN_SECONDS; } ) ),
			'last_day'   => count( $hits ),
			'daily_cap'  => flxi_wa_guard_daily_cap( $c ),
			'waiting'    => $gate['wait'] > 0 ? $gate['reason'] : '',
			'until'      => $gate['wait'] > 0 ? gmdate( 'c', $gate['until'] ) : null,
			'paused'     => $s['paused'] > $now,
		);
	}
	return $out;
}

/* Sistem kontrolü: art arda hatayla duran WhatsApp bağlantısı. */
add_filter(
	'flx_system_checks',
	function ( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		foreach ( flxi_wa_guard_summary() as $g ) {
			if ( ! $g['paused'] ) {
				continue;
			}
			$checks[] = array(
				'id'           => 'flxi.wa_guard.' . $g['id'],
				'source'       => 'Flux Mail',
				'group'        => 'WhatsApp',
				/* translators: %s: bağlantı adı */
				'label'        => sprintf( __( 'Bulk WhatsApp paused: %s', 'flx-mail' ), $g['name'] ),
				'status'       => 'warning',
				'message'      => __( 'Several messages in a row failed, so bulk sending was paused for 30 minutes. Check that the number is connected; the queue continues by itself.', 'flx-mail' ),
				'action_url'   => flxi_admin_url( 'flxi-baglantilar' ),
				'action_label' => __( 'Connections', 'flx-mail' ),
			);
		}
		return $checks;
	},
	30
);
