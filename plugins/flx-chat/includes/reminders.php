<?php
/**
 * 1.8.0: kişinin asistana kurdurduğu hatırlatıcılar ("her ay bana fidan dikimini hatırlat", "her ay son
 * projelerinizi gönderin", "her ay benden bir fidan iste"). Asistan özeti gösterip onay alana kadar
 * revize eder; zamanı gelince arka plan işi (jobs.php) asistanı bu görevle çalıştırır ve mesaj kişiye gider.
 * Saat söylenmezse ayardaki saat (09:00), ay günü söylenmezse ayın 1'i.
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

function flxc_rem_on() {
	return (bool) flxc_setting( 'rem_enabled' );
}

function flxc_rem_freqs() {
	return array(
		'daily'   => __( 'Every day', 'flx-chat' ),
		'weekly'  => __( 'Every week', 'flx-chat' ),
		'monthly' => __( 'Every month', 'flx-chat' ),
		'yearly'  => __( 'Every year', 'flx-chat' ),
		'once'    => __( 'Once', 'flx-chat' ),
	);
}

function flxc_rem_default_min() {
	return flxc_rem_parse_time( (string) flxc_setting( 'rem_time' ), 540 );
}

/** "9", "09:30", "21.00" → dakika. */
function flxc_rem_parse_time( $t, $fallback ) {
	if ( preg_match( '/^\s*(\d{1,2})(?:[:\.](\d{2}))?\s*$/', (string) $t, $m ) && (int) $m[1] < 24 && (int) ( $m[2] ?? 0 ) < 60 ) {
		return (int) $m[1] * 60 + (int) ( $m[2] ?? 0 );
	}
	return (int) $fallback;
}

/** Bir sonraki çalışma zamanı ($from'dan sonra; 0 = bir daha yok). */
function flxc_rem_next( array $r, $from = null ) {
	$tz   = wp_timezone();
	$from = null === $from ? time() : (int) $from;
	$now  = ( new DateTimeImmutable( '@' . $from ) )->setTimezone( $tz );
	$h    = intdiv( (int) $r['at_min'], 60 );
	$i    = (int) $r['at_min'] % 60;
	$at   = function ( $y, $mo, $d ) use ( $tz, $h, $i ) {
		$days = (int) ( new DateTimeImmutable( sprintf( '%04d-%02d-01', $y, $mo ), $tz ) )->format( 't' );
		return ( new DateTimeImmutable( sprintf( '%04d-%02d-%02d %02d:%02d:00', $y, $mo, min( max( 1, $d ), $days ), $h, $i ), $tz ) )->getTimestamp();
	};
	$y  = (int) $now->format( 'Y' );
	$mo = (int) $now->format( 'n' );
	switch ( $r['freq'] ) {
		case 'daily':
			for ( $k = 0; $k < 3; $k++ ) {
				$c = $now->modify( "+$k day" );
				$t = $at( (int) $c->format( 'Y' ), (int) $c->format( 'n' ), (int) $c->format( 'j' ) );
				if ( $t > $from ) {
					return $t;
				}
			}
			break;
		case 'weekly':
			for ( $k = 0; $k < 15; $k++ ) {
				$c = $now->modify( "+$k day" );
				if ( (int) $c->format( 'N' ) === max( 1, min( 7, (int) $r['dow'] ) ) ) {
					$t = $at( (int) $c->format( 'Y' ), (int) $c->format( 'n' ), (int) $c->format( 'j' ) );
					if ( $t > $from ) {
						return $t;
					}
				}
			}
			break;
		case 'monthly':
			for ( $k = 0; $k < 3; $k++ ) {
				$c = ( new DateTimeImmutable( sprintf( '%04d-%02d-01', $y, $mo ), $tz ) )->modify( "+$k month" );
				$t = $at( (int) $c->format( 'Y' ), (int) $c->format( 'n' ), (int) $r['dom'] );
				if ( $t > $from ) {
					return $t;
				}
			}
			break;
		case 'yearly':
			for ( $k = 0; $k < 3; $k++ ) {
				$t = $at( $y + $k, max( 1, min( 12, (int) $r['mon'] ) ), (int) $r['dom'] );
				if ( $t > $from ) {
					return $t;
				}
			}
			break;
		case 'once':
			if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $r['once_date'], $m ) ) {
				$t = $at( (int) $m[1], (int) $m[2], (int) $m[3] );
				return $t > $from ? $t : 0;
			}
			break;
	}
	return 0;
}

/** İnsan okur biçimde: "Her ay 1'inde 09:00". */
function flxc_rem_when( array $r ) {
	$time = sprintf( '%02d:%02d', intdiv( (int) $r['at_min'], 60 ), (int) $r['at_min'] % 60 );
	switch ( $r['freq'] ) {
		case 'daily':
			/* translators: %s: time */
			return sprintf( __( 'Every day at %s', 'flx-chat' ), $time );
		case 'weekly':
			global $wp_locale;
			$day = $wp_locale ? $wp_locale->get_weekday( (int) $r['dow'] % 7 ) : (string) $r['dow'];
			/* translators: 1: weekday, 2: time */
			return sprintf( __( 'Every week on %1$s at %2$s', 'flx-chat' ), $day, $time );
		case 'monthly':
			/* translators: 1: day of month, 2: time */
			return sprintf( __( 'Every month on day %1$d at %2$s', 'flx-chat' ), (int) $r['dom'], $time );
		case 'yearly':
			/* translators: 1: date, 2: time */
			return sprintf( __( 'Every year on %1$s at %2$s', 'flx-chat' ), flx_date( gmmktime( 12, 0, 0, (int) $r['mon'], (int) $r['dom'], 2026 ), 'j F' ), $time );
		case 'once':
			$t = flxc_rem_next( $r, 0 );
			return $t ? flx_date( $t, 'datetime' ) : (string) $r['once_date'];
	}
	return '';
}

/* ---------- Kayıt ---------- */

function flxc_rem_row( $r ) {
	$r['donation'] = flxc_decode( $r['donation'] ?? '' );
	foreach ( array( 'id', 'chat_id', 'dom', 'dow', 'mon', 'at_min', 'next_at', 'runs', 'last_at', 'created_at', 'updated_at' ) as $k ) {
		$r[ $k ] = (int) ( $r[ $k ] ?? 0 );
	}
	return $r;
}

function flxc_rem_get( $id ) {
	global $wpdb;
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'reminders' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
	return $r ? flxc_rem_row( $r ) : null;
}

/** @param string $status active | all */
function flxc_rem_for_chat( $chat_id, $status = 'active' ) {
	global $wpdb;
	$t    = flxc_table( 'reminders' );
	$rows = 'all' === $status
		? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE chat_id = %d AND status <> 'deleted' ORDER BY id", $chat_id ), ARRAY_A ) // phpcs:ignore
		: $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE chat_id = %d AND status = 'active' ORDER BY id", $chat_id ), ARRAY_A ); // phpcs:ignore
	return array_map( 'flxc_rem_row', (array) $rows );
}

/**
 * Araç girdisini doğrular (kayıt açmaz).
 *
 * @return array|WP_Error hatırlatıcı alanları
 */
function flxc_rem_build( array $a, array $base = array() ) {
	$r = wp_parse_args(
		$base,
		array(
			'title'     => '',
			'task'      => '',
			'freq'      => 'monthly',
			'dom'       => 1,
			'dow'       => 1,
			'mon'       => 1,
			'once_date' => '',
			'at_min'    => flxc_rem_default_min(),
			'donation'  => array(),
		)
	);
	foreach ( array( 'title' => 160, 'task' => 1000 ) as $k => $max ) {
		if ( isset( $a[ $k ] ) && '' !== trim( (string) $a[ $k ] ) ) {
			$r[ $k ] = mb_substr( sanitize_textarea_field( (string) $a[ $k ] ), 0, $max );
		}
	}
	if ( ! empty( $a['freq'] ) && isset( flxc_rem_freqs()[ $a['freq'] ] ) ) {
		$r['freq'] = $a['freq'];
	}
	if ( isset( $a['day'] ) && (int) $a['day'] ) {
		$r['dom'] = max( 1, min( 31, (int) $a['day'] ) );
	}
	if ( isset( $a['weekday'] ) && (int) $a['weekday'] ) {
		$r['dow'] = max( 1, min( 7, (int) $a['weekday'] ) );
	}
	if ( isset( $a['month'] ) && (int) $a['month'] ) {
		$r['mon'] = max( 1, min( 12, (int) $a['month'] ) );
	}
	if ( ! empty( $a['date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $a['date'] ) ) {
		$r['once_date'] = (string) $a['date'];
		if ( 'yearly' === $r['freq'] ) {
			$r['mon'] = (int) substr( $a['date'], 5, 2 );
			$r['dom'] = (int) substr( $a['date'], 8, 2 );
		}
	}
	if ( isset( $a['time'] ) && '' !== trim( (string) $a['time'] ) ) {
		$r['at_min'] = flxc_rem_parse_time( (string) $a['time'], $r['at_min'] );
	}
	// "Her ay benden bir fidan iste": zamanı gelince bu seçim hazır özetle sorulur.
	if ( ! empty( $a['type_id'] ) ) {
		$o = flxc_bot_build_order(
			array(
				'type_id'  => (int) $a['type_id'],
				'option'   => (string) ( $a['option'] ?? '' ),
				'quantity' => (int) ( $a['quantity'] ?? 1 ),
				'amount'   => $a['amount'] ?? 0,
			)
		);
		if ( is_wp_error( $o ) ) {
			return $o;
		}
		$r['donation'] = array(
			'type_id'  => $o['type'],
			'option'   => $o['option'],
			'quantity' => $o['qty'],
			'amount'   => $o['amount'],
		);
		/* translators: 1: donation type, 2: quantity and unit */
		$r['title'] = '' !== $r['title'] ? $r['title'] : sprintf( __( '%1$s: %2$s', 'flx-chat' ), $o['title'], number_format_i18n( $o['qty'] ) . ( '' !== $o['unit'] ? ' ' . $o['unit'] : '' ) ) . ' · ' . flxc_money( $o['amount'], $o['currency'] );
	}
	if ( '' === trim( $r['task'] ) ) {
		return new WP_Error( 'task', 'task boş: zamanı gelince ne yapılacağını yaz (ör. "Fidan dikimini hatırlat", "Son projelerimizi gönder", "Bu ayki fidan bağışını sor").' );
	}
	if ( '' === trim( $r['title'] ) ) {
		$r['title'] = mb_substr( $r['task'], 0, 80 );
	}
	if ( 'once' === $r['freq'] && '' === $r['once_date'] ) {
		return new WP_Error( 'date', 'Bir kezlik hatırlatıcı için date (YYYY-AA-GG) ver.' );
	}
	unset( $r['id'], $r['status'] );
	$r['next_at'] = flxc_rem_next( $r );
	if ( ! $r['next_at'] ) {
		return new WP_Error( 'past', 'Bu tarih geçmiş. Kişiden ileri bir tarih iste.' );
	}
	return $r;
}

function flxc_rem_summary( array $r ) {
	$lines = array(
		'*' . $r['title'] . '*',
		flxc_rem_when( $r ),
		/* translators: %s: date */
		sprintf( __( 'First: %s', 'flx-chat' ), flx_date( (int) $r['next_at'], 'datetime' ) ),
	);
	if ( $r['task'] !== $r['title'] ) {
		$lines[] = $r['task'];
	}
	return implode( "\n", $lines );
}

function flxc_rem_save( $chat, array $r, $id = 0, $source = 'bot' ) {
	global $wpdb;
	$f = array(
		'title'      => $r['title'],
		'task'       => $r['task'],
		'freq'       => $r['freq'],
		'dom'        => (int) $r['dom'],
		'dow'        => (int) $r['dow'],
		'mon'        => (int) $r['mon'],
		'once_date'  => $r['once_date'],
		'at_min'     => (int) $r['at_min'],
		'donation'   => $r['donation'] ? flxc_json( $r['donation'] ) : '',
		'next_at'    => (int) $r['next_at'],
		'status'     => 'active',
		'updated_at' => time(),
	);
	if ( $id ) {
		$wpdb->update( flxc_table( 'reminders' ), $f, array( 'id' => (int) $id, 'chat_id' => (int) $chat['id'] ) );
	} else {
		$f += array( 'chat_id' => (int) $chat['id'], 'source' => $source, 'created_at' => time() );
		$wpdb->insert( flxc_table( 'reminders' ), $f );
		$id = (int) $wpdb->insert_id;
	}
	do_action( 'flxc_reminder_saved', $id, $chat );
	flxc_rem_schedule();
	return $id;
}

function flxc_rem_set_status( $id, $status ) {
	global $wpdb;
	$r = flxc_rem_get( $id );
	if ( ! $r ) {
		return false;
	}
	$f = array( 'status' => $status, 'updated_at' => time() );
	if ( 'active' === $status ) {
		$f['next_at'] = flxc_rem_next( $r );
		if ( ! $f['next_at'] ) {
			return false;
		}
	}
	return false !== $wpdb->update( flxc_table( 'reminders' ), $f, array( 'id' => (int) $id ) );
}

function flxc_rem_for_js( array $r ) {
	$chat = flxc_get_chat( $r['chat_id'] );
	return array(
		'id'      => $r['id'],
		'chat'    => $r['chat_id'],
		'who'     => $chat ? flxc_chat_title( $chat ) : '#' . $r['chat_id'],
		'url'     => admin_url( 'admin.php?page=flx-chat&chat=' . $r['chat_id'] ),
		'title'   => $r['title'],
		'task'    => $r['task'],
		'when'    => flxc_rem_when( $r ),
		'next'    => 'active' === $r['status'] ? $r['next_at'] * 1000 : 0,
		'status'  => $r['status'],
		'runs'    => $r['runs'],
		'last'    => $r['last_at'] * 1000,
		'note'    => $r['last_note'],
		'gift'    => (bool) $r['donation'],
	);
}

/* ---------- Asistan araçları ---------- */

function flxc_rem_tools( $f ) {
	$types = array();
	foreach ( flxc_ai_types() as $t ) {
		$types[] = (int) $t['id'];
	}
	return array(
		$f(
			'reminder_set',
			'Kişinin istediği hatırlatıcıyı hazırlar ya da düzenler. Önce confirmed=false ile çağır, dönen özeti kişiye göster; kişi değişiklik isterse yeniden çağır; kişi açıkça onaylayınca confirmed=true ile kaydet. Saat söylenmezse boş bırak (varsayılan saat), "her ay" deyip gün söylemezse day boş (ayın 1\'i). Kişi "her ay benden bir fidan iste" gibi bir bağış hatırlatıcısı isterse type_id/option/quantity de ver.',
			array(
				'id'        => array( 'type' => 'integer', 'description' => 'Var olan hatırlatıcıyı düzenlemek için' ),
				'title'     => array( 'type' => 'string', 'description' => 'Kısa ad' ),
				'task'      => array( 'type' => 'string', 'description' => 'Zamanı gelince ne yapılacak (ör. "Fidan dikimini hatırlat", "Son projelerimizi ve sosyal medya içeriklerimizi gönder", "Bu ayki 1 fidan bağışını sor")' ),
				'freq'      => array( 'type' => 'string', 'enum' => array_keys( flxc_rem_freqs() ) ),
				'day'       => array( 'type' => 'integer', 'description' => 'Ayın günü (aylık/yıllık)' ),
				'weekday'   => array( 'type' => 'integer', 'description' => '1 Pazartesi … 7 Pazar (haftalık)' ),
				'month'     => array( 'type' => 'integer', 'description' => '1-12 (yıllık)' ),
				'date'      => array( 'type' => 'string', 'description' => 'YYYY-AA-GG (bir kez)' ),
				'time'      => array( 'type' => 'string', 'description' => 'SS:DD; söylenmediyse boş' ),
				'type_id'   => array( 'type' => 'integer', 'enum' => $types ),
				'option'    => array( 'type' => 'string' ),
				'quantity'  => array( 'type' => 'integer' ),
				'confirmed' => array( 'type' => 'boolean' ),
			),
			array( 'confirmed' )
		),
		$f( 'reminder_list', 'Kişinin etkin hatırlatıcılarını listeler.' ),
		$f(
			'reminder_cancel',
			'Kişi bir hatırlatıcıyı durdurmak/silmek isterse çağır.',
			array( 'id' => array( 'type' => 'integer' ) ),
			array( 'id' )
		),
	);
}

function flxc_rem_tool( $name, array $a, $chat, array &$state, $sandbox ) {
	$list = $sandbox ? array_values( (array) ( $state['rems'] ?? array() ) ) : flxc_rem_for_chat( (int) $chat['id'] );
	$find = function ( $id ) use ( $list ) {
		foreach ( $list as $r ) {
			if ( (int) $r['id'] === (int) $id ) {
				return $r;
			}
		}
		return null;
	};
	switch ( $name ) {
		case 'reminder_list':
			$out = array();
			foreach ( $list as $r ) {
				$out[] = array( 'id' => (int) $r['id'], 'title' => $r['title'], 'task' => $r['task'], 'when' => flxc_rem_when( $r ), 'next' => flx_date( (int) $r['next_at'], 'datetime' ) );
			}
			return array( 'ok' => true, 'reminders' => $out );

		case 'reminder_cancel':
			$r = $find( (int) ( $a['id'] ?? 0 ) );
			if ( ! $r ) {
				return array( 'ok' => false, 'error' => 'Bu kişide böyle bir hatırlatıcı yok; reminder_list ile bak.' );
			}
			if ( $sandbox ) {
				unset( $state['rems'][ (int) $r['id'] ] );
			} else {
				flxc_rem_set_status( (int) $r['id'], 'deleted' );
				/* translators: %s: title */
				flxc_system_message( $chat, sprintf( __( 'The person stopped the reminder: %s', 'flx-chat' ), $r['title'] ) );
			}
			return array( 'ok' => true, 'note' => 'Durduruldu. Kişiye kısaca bildir.' );

		case 'reminder_set':
			$id   = (int) ( $a['id'] ?? 0 );
			$base = $id ? $find( $id ) : null;
			if ( $id && ! $base ) {
				return array( 'ok' => false, 'error' => 'Bu kişide böyle bir hatırlatıcı yok.' );
			}
			// Onay beklenen taslak: kişi revize ettikçe üzerine yazılır.
			if ( ! $base && ! empty( $state['rem_draft'] ) && (int) ( $state['rem_draft']['id'] ?? 0 ) === $id ) {
				$base = $state['rem_draft'];
			}
			$r = flxc_rem_build( $a, $base ? $base : array() );
			if ( is_wp_error( $r ) ) {
				return array( 'ok' => false, 'error' => $r->get_error_message() );
			}
			if ( empty( $a['confirmed'] ) ) {
				$state['rem_draft'] = $r + array( 'id' => $id );
				return array(
					'ok'      => true,
					'summary' => flxc_rem_summary( $r ),
					'note'    => 'Henüz kaydedilmedi. Bu özeti kişiye göster ve onay iste; değişiklik isterse reminder_set\'i yeniden çağır. Kişi başka konuya geçerse zorlamadan o konuya devam et, onayı tekrar tekrar sorma.',
				);
			}
			if ( ! $id && count( $list ) >= max( 1, (int) flxc_setting( 'rem_max' ) ) ) {
				return array( 'ok' => false, 'error' => 'Kişinin etkin hatırlatıcı sınırı doldu; önce birini durdurmasını öner.' );
			}
			unset( $state['rem_draft'] );
			if ( $sandbox ) {
				$id                   = $id ? $id : 900 + count( (array) ( $state['rems'] ?? array() ) ) + 1;
				$state['rems'][ $id ] = array_merge( $r, array( 'id' => $id, 'status' => 'active' ) );
			} else {
				$id = flxc_rem_save( $chat, $r, $id );
				/* translators: 1: title, 2: when */
				flxc_system_message( $chat, sprintf( __( 'The assistant set a reminder: %1$s (%2$s)', 'flx-chat' ), $r['title'], flxc_rem_when( $r ) ) );
			}
			return array( 'ok' => true, 'id' => $id, 'summary' => flxc_rem_summary( $r ), 'note' => 'Kaydedildi. Kişiye kısaca bildir; istediği zaman "hatırlatıcıyı durdur" yazabileceğini söyle.' );
	}
	return array( 'ok' => false, 'error' => 'Bilinmeyen araç.' );
}

/** Sistem talimatına: kişinin hatırlatıcıları ve onay bekleyen taslak. */
function flxc_rem_prompt( $chat, array $state ) {
	$list = empty( $chat['id'] ) ? array_values( (array) ( $state['rems'] ?? array() ) ) : flxc_rem_for_chat( (int) $chat['id'] );
	$out  = array( '## Hatırlatıcılar', '- Kişi düzenli bir hatırlatma ya da gönderim isterse (her ay hatırlat, her ay projelerinizi gönderin, her ay benden bir fidan iste) reminder_set kullan: özet → onay → kayıt. Adet, tür ya da saat sormadan hemen reminder_set (confirmed=false) çağır; söylenmeyen saat varsayılan, "her ay" ve gün yoksa ayın 1\'i.' );
	foreach ( $list as $r ) {
		$out[] = '- #' . (int) $r['id'] . ' ' . $r['title'] . ' · ' . flxc_rem_when( $r );
	}
	if ( ! empty( $state['rem_draft'] ) ) {
		$out[] = '- Onay bekleyen taslak: ' . str_replace( "\n", ' · ', flxc_rem_summary( $state['rem_draft'] ) ) . ' (kişi başka konudaysa üstelemeden devam et)';
	}
	return implode( "\n", $out );
}

/* ---------- Zamanı gelince ---------- */

/** En yakın hatırlatıcı saatine tek seferlik WP-Cron tetiği. */
function flxc_rem_schedule() {
	global $wpdb;
	$next = (int) $wpdb->get_var( 'SELECT MIN(next_at) FROM ' . flxc_table( 'reminders' ) . " WHERE status = 'active' AND next_at > 0" ); // phpcs:ignore
	$cur  = wp_next_scheduled( 'flxc_rem_tick' );
	if ( ! $next || ( $cur && $cur <= $next + 5 ) ) {
		return;
	}
	if ( $cur ) {
		wp_unschedule_event( $cur, 'flxc_rem_tick' );
	}
	wp_schedule_single_event( max( time() + 30, $next + 5 ), 'flxc_rem_tick' );
}

/** Zamanı gelen hatırlatıcıları kuyruğa alır; sonraki zamanı hemen ilerletir (aynısı iki kez gitmesin). */
function flxc_rem_enqueue_due() {
	global $wpdb;
	if ( ! flxc_rem_on() ) {
		return 0;
	}
	$rt   = flxc_table( 'reminders' );
	$now  = time();
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $rt WHERE status = 'active' AND next_at > 0 AND next_at <= %d ORDER BY next_at LIMIT 20", $now ), ARRAY_A ); // phpcs:ignore
	$n    = 0;
	foreach ( $rows as $r ) {
		$r    = flxc_rem_row( $r );
		$next = flxc_rem_next( $r, $now );
		$wpdb->update( $rt, array( 'next_at' => $next, 'status' => $next ? 'active' : 'done', 'updated_at' => $now ), array( 'id' => $r['id'] ) );
		// Site günlerce ziyaret almadıysa çok eski hatırlatma gönderilmez.
		if ( $now - $r['next_at'] > 2 * DAY_IN_SECONDS ) {
			$wpdb->update( $rt, array( 'last_note' => __( 'Did not run on time, skipped.', 'flx-chat' ) ), array( 'id' => $r['id'] ) );
			continue;
		}
		$wpdb->insert(
			flxc_table( 'jobs' ),
			array(
				'kind'       => 'reminder',
				'chat_id'    => $r['chat_id'],
				'ref'        => $r['id'],
				'status'     => 'queued',
				'created_at' => $now,
			)
		);
		$n++;
	}
	if ( $rows ) {
		flxc_rem_schedule();
	}
	return $n;
}

/**
 * Hatırlatıcıyı çalıştırır: asistan görevi yapan mesajı yazar, kişiye gider.
 *
 * @return array { status, note, usage[, error] }
 */
function flxc_rem_run( $rid, $deadline ) {
	global $wpdb;
	$r    = flxc_rem_get( $rid );
	$chat = $r ? flxc_get_chat( $r['chat_id'] ) : null;
	$res  = function ( $status, $note, $usage = array(), $err = null ) use ( $r, $wpdb ) {
		if ( $r ) {
			$f = array( 'last_note' => 'done' === $status ? '' : mb_substr( $note, 0, 250 ) );
			if ( 'done' === $status ) {
				$f['runs']    = $r['runs'] + 1;
				$f['last_at'] = time();
			}
			$wpdb->update( flxc_table( 'reminders' ), $f, array( 'id' => $r['id'] ) );
		}
		return array( 'status' => $status, 'note' => $note, 'usage' => $usage ) + ( $err ? array( 'error' => $err ) : array() );
	};
	if ( ! $r || ! $chat || 'deleted' === $r['status'] ) {
		return $res( 'skipped', __( 'No reminder or chat.', 'flx-chat' ) );
	}
	if ( flxc_chat_blocked( $chat ) ) {
		return $res( 'skipped', __( 'The person is on the blocklist.', 'flx-chat' ) );
	}
	if ( ! flxc_bot_enabled() || ! flxc_ai_ready() ) {
		return $res( 'skipped', __( 'The assistant is off.', 'flx-chat' ) );
	}
	$state = flxc_bot_state( $chat );
	$task  = "## Şimdiki görev: hatırlatıcı\nKişinin kurduğu hatırlatıcının zamanı geldi; konuşmayı sen başlatıyorsun, kişi şu an yazmadı.\n"
		. '- Hatırlatıcı #' . $r['id'] . ': ' . $r['title'] . ' (' . flxc_rem_when( $r ) . ")\n- Görev: " . $r['task'] . "\n"
		. "- Kişiye bu görevi yapan kısa, sıcak tek bir WhatsApp mesajı yaz. İçerik/proje göndermen gerekiyorsa search_site ile bul, yalnız dönen linkleri ver.\n";
	if ( $r['donation'] ) {
		$o = flxc_bot_build_order( $r['donation'] );
		if ( ! is_wp_error( $o ) ) {
			$state['order']    = $o;
			$state['donation'] = 0;
			unset( $state['done_sig'] );
			flxc_bot_save_state( $chat['id'], $state );
			$task .= "- Bağış hatırlatıcısı: seçim hazır (" . str_replace( "\n", ' · ', flxc_bot_order_summary( $o, $state ) ) . "). Özeti göster ve bu ay da yapmak isteyip istemediğini sor; kişi onaylarsa create_donation ile devam edilir. Baskı yapma.\n";
		}
	}
	$task .= '- Mesajın sonunda istemezse "hatırlatıcıyı durdur" yazabileceğini kısaca belirt.';
	$messages = array(
		array( 'role' => 'system', 'content' => flxc_bot_system_prompt( $chat, $state ) . "\n\n" . $task ),
		array( 'role' => 'user', 'content' => '(' . __( 'reminder time', 'flx-chat' ) . ')' ),
	);
	$ctx   = array(
		'after'      => array(),
		'deadline'   => $deadline,
		'only_tools' => array( 'search_site', 'send_price_list', 'my_records', 'donation_options', 'payment_info', 'current_time' ),
	);
	$reply = flxc_bot_complete( $messages, $chat, $ctx );
	$usage = (array) ( $ctx['usage'] ?? array() );
	flxc_bot_stat_add( is_wp_error( $reply ) ? array( 'errors' => 1 ) : array( 'reminders' => 1 ), $usage, (int) $chat['id'] );
	if ( is_wp_error( $reply ) ) {
		return $res( 'error', $reply->get_error_message(), $usage, $reply->get_error_message() );
	}
	$sent = 0;
	// 1.30.1: ```widget görselleri metinden ayrılır, metinden sonra PNG resim olarak gider (sohbetteki gibi).
	$wg    = flxc_widgets_split( $reply );
	$reply = $wg['text'];
	foreach ( array_merge( '' === $reply ? array() : preg_split( '/\n\s*-{3,}\s*\n/', $reply ), $ctx['after'] ) as $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			continue;
		}
		$s = flxc_send(
			flxc_get_chat( $chat['id'] ),
			array(
				'type'    => 'text',
				'body'    => $text,
				'source'  => 'bot',
				'user_id' => 0,
				'preview' => false !== strpos( $text, '://' ),
				'delay'   => $sent ? 1200 : 0,
			)
		);
		if ( ! is_wp_error( $s ) ) {
			$sent++;
		}
	}
	if ( flxc_widgets_on() ) {
		foreach ( $wg['codes'] as $code ) {
			if ( flxc_widget_send( flxc_get_chat( $chat['id'] ), $code, $sent ? 1200 : 0 ) ) {
				$sent++;
			}
		}
	}
	if ( ! $sent ) {
		return $res( 'error', __( 'The message could not be sent.', 'flx-chat' ), $usage );
	}
	$st       = flxc_bot_state( flxc_get_chat( $chat['id'] ) );
	$st['ai'] = 1;
	flxc_bot_save_state( $chat['id'], $st );
	/* translators: %s: title */
	flxc_system_message( $chat, sprintf( __( 'Reminder sent: %s', 'flx-chat' ), $r['title'] ) );
	return $res( 'done', $r['title'], $usage );
}

/* ---------- REST (panel) ---------- */

add_action(
	'rest_api_init',
	function () {
		$ns = 'flx-chat/v1';
		register_rest_route(
			$ns,
			'/reminders',
			array(
				'methods'             => 'GET',
				'permission_callback' => 'flxc_can_manage',
				'callback'            => function ( WP_REST_Request $req ) {
					global $wpdb;
					$chat = (int) $req->get_param( 'chat' );
					$t    = flxc_table( 'reminders' );
					$rows = $chat
						? flxc_rem_for_chat( $chat, 'all' )
						: array_map( 'flxc_rem_row', (array) $wpdb->get_results( "SELECT * FROM $t WHERE status <> 'deleted' ORDER BY (status = 'active') DESC, next_at ASC LIMIT 300", ARRAY_A ) ); // phpcs:ignore
					return array(
						'enabled' => flxc_rem_on(),
						'items'   => array_map( 'flxc_rem_for_js', $rows ),
					);
				},
			)
		);
		register_rest_route(
			$ns,
			'/reminders/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'POST',
					'permission_callback' => 'flxc_can_manage',
					'callback'            => function ( WP_REST_Request $req ) {
						$st = (string) $req->get_param( 'status' );
						if ( ! in_array( $st, array( 'active', 'paused' ), true ) || ! flxc_rem_set_status( (int) $req['id'], $st ) ) {
							return new WP_Error( 'flxc_rem', __( 'The reminder could not be updated.', 'flx-chat' ), array( 'status' => 400 ) );
						}
						return flxc_rem_for_js( flxc_rem_get( (int) $req['id'] ) );
					},
				),
				array(
					'methods'             => 'DELETE',
					'permission_callback' => 'flxc_can_manage',
					'callback'            => function ( WP_REST_Request $req ) {
						return array( 'ok' => flxc_rem_set_status( (int) $req['id'], 'deleted' ) );
					},
				),
			)
		);
	}
);
