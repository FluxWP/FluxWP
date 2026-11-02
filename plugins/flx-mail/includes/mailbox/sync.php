<?php
/**
 * IMAP eşitleme: bilgi@ Gelen kutusu ve Gönderilmiş klasörü (web postadan gönderilenler dahil).
 *
 * Paylaşımlı sunucuya uygun: her çalışmada kısa süre (varsayılan 30 sn), az sayıda ileti;
 * ilk kurulumda son N gün geriye doğru parça parça alınır. Ekler indirilmez, açınca sunucudan okunur.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/** Posta kutusu açık ve bağlantı bilgisi var mı? */
function flxi_mailbox_enabled() {
	$c = flxi_imap_creds();
	return (bool) flxi_setting( 'mailbox_enabled' ) && '' !== $c['host'] && '' !== $c['user'] && '' !== $c['pass'];
}

/** IMAP bağlantı bilgisi: boş alanlar SMTP (bilgi@) hesabından gelir. Şifre yalnız sunucuda kullanılır. */
function flxi_imap_creds() {
	$s    = flxi_settings();
	$user = trim( (string) $s['imap_user'] );
	$pass = defined( 'FLXI_IMAP_PASS' ) ? (string) FLXI_IMAP_PASS : flxi_decrypt( $s['imap_pass'] );
	if ( '' === $user ) {
		$user = trim( (string) $s['smtp_user'] );
	}
	if ( '' === $pass ) {
		$pass = flxi_smtp_password( false );
	}
	return array(
		'host'   => trim( (string) $s['imap_host'] ),
		'port'   => (int) $s['imap_port'] ? (int) $s['imap_port'] : 993,
		'secure' => (string) $s['imap_secure'],
		'user'   => $user,
		'pass'   => $pass,
	);
}

/** @return FLXI_Imap|WP_Error */
function flxi_imap_open() {
	$c = flxi_imap_creds();
	if ( '' === $c['host'] || '' === $c['user'] || '' === $c['pass'] ) {
		return new WP_Error( 'flxi_imap_conf', __( 'IMAP sunucusu, kullanıcı ya da şifre eksik (Ayarlar › Posta kutusu).', 'flx-mail' ) );
	}
	$imap = new FLXI_Imap( $c['host'], $c['port'], $c['secure'], 15 );
	if ( ! $imap->connect() || ! $imap->login( $c['user'], $c['pass'] ) ) {
		return new WP_Error( 'flxi_imap', $imap->error );
	}
	return $imap;
}

function flxi_mailbox_state() {
	$s = get_option( 'flxi_mailbox_state', array() );
	return is_array( $s ) ? wp_parse_args( $s, array( 'folders' => array(), 'last_run' => 0, 'last_ok' => 0, 'error' => '', 'names' => array() ) ) : array( 'folders' => array(), 'last_run' => 0, 'last_ok' => 0, 'error' => '', 'names' => array() );
}

function flxi_mailbox_state_save( $s ) {
	update_option( 'flxi_mailbox_state', $s, false );
}

/** Gelen ve Gönderilmiş klasörlerinin sunucudaki adları. */
function flxi_mailbox_detect_folders( FLXI_Imap $imap ) {
	$sent = '';
	$list = $imap->folders();
	foreach ( $list as $f ) {
		if ( in_array( '\\sent', $f['attrs'], true ) ) {
			$sent = $f['name'];
			break;
		}
	}
	if ( '' === $sent ) {
		$names = array( 'sent', 'inbox.sent', 'sent items', 'sent messages', 'gönderilmiş öğeler', 'gönderilenler', 'inbox.sent items', 'inbox.gönderilenler' );
		foreach ( $list as $f ) {
			if ( in_array( mb_strtolower( $f['label'] ), $names, true ) ) {
				$sent = $f['name'];
				break;
			}
		}
	}
	return array(
		'inbox' => 'INBOX',
		'sent'  => $sent,
		'all'   => wp_list_pluck( $list, 'label' ),
	);
}

/**
 * Eşitleme.
 *
 * @param int $budget saniye
 * @return array new (sayı), more (geriye dönük kalan var mı), error
 */
function flxi_mailbox_sync( $budget = 0 ) {
	if ( ! flxi_mailbox_enabled() ) {
		return array( 'new' => 0, 'more' => false, 'error' => __( 'Posta kutusu kapalı.', 'flx-mail' ) );
	}
	if ( get_transient( 'flxi_mailbox_lock' ) ) {
		return array( 'new' => 0, 'more' => true, 'error' => '', 'busy' => true );
	}
	set_transient( 'flxi_mailbox_lock', 1, 90 );
	$budget = $budget ? (int) $budget : (int) apply_filters( 'flxi_mailbox_budget', 30 );
	$start  = microtime( true );
	$state  = flxi_mailbox_state();
	$stats  = array( 'new' => 0, 'more' => false, 'error' => '' );

	$state['last_run'] = time();
	$imap              = flxi_imap_open();
	if ( is_wp_error( $imap ) ) {
		$state['error'] = $imap->get_error_message();
		flxi_mailbox_state_save( $state );
		delete_transient( 'flxi_mailbox_lock' );
		$stats['error'] = $state['error'];
		return $stats;
	}
	if ( empty( $state['names']['inbox'] ) || empty( $state['names']['checked'] ) || $state['names']['checked'] < time() - DAY_IN_SECONDS ) {
		$state['names']            = flxi_mailbox_detect_folders( $imap );
		$state['names']['checked'] = time();
	}

	try {
		flxi_mailbox_run_pending( $imap, $state );
		foreach ( array( 'inbox', 'sent' ) as $folder ) {
			$name = $state['names'][ $folder ] ?? '';
			if ( '' === $name ) {
				continue;
			}
			$left = $budget - ( microtime( true ) - $start );
			if ( $left < 3 ) {
				$stats['more'] = true;
				break;
			}
			$r              = flxi_mailbox_sync_folder( $imap, $folder, $name, $state, $start + $budget );
			$stats['new']  += $r['new'];
			$stats['more']  = $stats['more'] || $r['more'];
		}
		$state['error']   = '';
		$state['last_ok'] = time();
	} catch ( Exception $e ) {
		$state['error'] = $e->getMessage();
		$stats['error'] = $state['error'];
	}
	$imap->logout();
	flxi_mailbox_state_save( $state );
	delete_transient( 'flxi_mailbox_lock' );
	if ( $stats['new'] ) {
		do_action( 'flxi_mailbox_synced', $stats );
	}
	return $stats;
}

function flxi_mailbox_sync_folder( FLXI_Imap $imap, $folder, $name, array &$state, $deadline ) {
	$out = array( 'new' => 0, 'more' => false );
	$sel = $imap->select( $name );
	if ( ! $sel ) {
		throw new Exception( $imap->error ); // phpcs:ignore
	}
	$fs = $state['folders'][ $folder ] ?? array();
	if ( empty( $fs ) || (int) ( $fs['uidvalidity'] ?? 0 ) !== (int) $sel['uidvalidity'] || ( $fs['name'] ?? '' ) !== $name ) {
		// İlk kez (ya da klasör sıfırlandı): son N günün UID'leri, yeniden eskiye.
		$days = max( 1, (int) flxi_setting( 'mailbox_days' ) );
		$uids = $sel['exists'] ? $imap->uid_search( 'SINCE ' . gmdate( 'd-M-Y', time() - $days * DAY_IN_SECONDS ) ) : array();
		$uids = array_slice( $uids, -1 * (int) apply_filters( 'flxi_mailbox_initial_max', 1000 ) );
		$fs   = array(
			'name'        => $name,
			'uidvalidity' => (int) $sel['uidvalidity'],
			'last_uid'    => $uids ? max( $uids ) : max( 0, (int) $sel['uidnext'] - 1 ),
			'backfill'    => array_reverse( $uids ),
		);
	}

	// 1) Yeni gelenler (artan).
	$new = array();
	if ( $sel['exists'] ) {
		$new = array_values(
			array_filter(
				$imap->uid_search( 'UID ' . ( (int) $fs['last_uid'] + 1 ) . ':*' ),
				function ( $u ) use ( $fs ) {
					return $u > (int) $fs['last_uid'];
				}
			)
		);
	}
	foreach ( array_chunk( $new, 10 ) as $chunk ) {
		if ( microtime( true ) > $deadline ) {
			$out['more'] = true;
			break;
		}
		$out['new']    += flxi_mailbox_import( $imap, $folder, $chunk, (int) $sel['uidvalidity'], $deadline );
		$fs['last_uid'] = max( (int) $fs['last_uid'], max( $chunk ) );
	}

	// 2) Geriye dönük (ilk kurulum).
	while ( ! empty( $fs['backfill'] ) && microtime( true ) < $deadline ) {
		$chunk          = array_splice( $fs['backfill'], 0, 10 );
		$out['new']    += flxi_mailbox_import( $imap, $folder, $chunk, (int) $sel['uidvalidity'], $deadline );
	}
	if ( ! empty( $fs['backfill'] ) ) {
		$out['more'] = true;
	}

	// 3) Web postada okunan/yıldızlanan son iletilerin bayrakları.
	if ( microtime( true ) < $deadline && $sel['exists'] ) {
		flxi_mailbox_refresh_flags( $imap, $folder, (int) $sel['uidvalidity'] );
	}

	$state['folders'][ $folder ] = $fs;
	return $out;
}

/** UID'leri içeri alır; zaten olanlar atlanır. Dönen: yeni kayıt sayısı. */
function flxi_mailbox_import( FLXI_Imap $imap, $folder, array $uids, $uidvalidity, $deadline ) {
	global $wpdb;
	$t    = flxi_table( 'mail' );
	$have = $wpdb->get_col( $wpdb->prepare( "SELECT uid FROM $t WHERE folder = %s AND uidvalidity = %d AND uid IN (" . implode( ',', array_map( 'intval', $uids ) ) . ')', $folder, $uidvalidity ) ); // phpcs:ignore
	$uids = array_values( array_diff( $uids, array_map( 'intval', $have ) ) );
	if ( ! $uids ) {
		return 0;
	}
	$meta  = $imap->uid_fetch( $uids, 'UID FLAGS INTERNALDATE RFC822.SIZE BODYSTRUCTURE BODY.PEEK[HEADER]' );
	$count = 0;
	foreach ( $meta as $uid => $m ) {
		$h     = flxi_mime_headers( (string) ( $m['BODY[HEADER]'] ?? '' ) );
		$parts = flxi_mime_parts( $m['BODYSTRUCTURE'] ?? array() );
		list( $tp, $hp, $att ) = flxi_mime_pick( $parts );
		$text = '';
		$html = '';
		if ( ( $tp || $hp ) && microtime( true ) < $deadline + 10 ) {
			$max   = (int) apply_filters( 'flxi_mailbox_part_max', 400000 );
			$items = array();
			foreach ( array( $tp, $hp ) as $p ) {
				if ( $p ) {
					$items[] = 'BODY.PEEK[' . $p['section'] . ']<0.' . $max . '>';
				}
			}
			$b = $imap->uid_fetch( array( $uid ), implode( ' ', $items ) );
			$b = $b[ $uid ] ?? array();
			if ( $tp ) {
				$text = flxi_mime_to_utf8( flxi_mime_body_decode( $b[ 'BODY[' . $tp['section'] . ']' ] ?? '', $tp['encoding'] ), $tp['params']['charset'] ?? '' );
			}
			if ( $hp ) {
				$html = flxi_mime_to_utf8( flxi_mime_body_decode( $b[ 'BODY[' . $hp['section'] . ']' ] ?? '', $hp['encoding'] ), $hp['params']['charset'] ?? '' );
			}
		}
		$from  = flxi_mime_addresses( $h['from'] ?? '' );
		$flags = array_map( 'strtolower', (array) ( $m['FLAGS'] ?? array() ) );
		$date  = strtotime( (string) ( $m['INTERNALDATE'] ?? '' ) );
		if ( ! $date ) {
			$date = strtotime( (string) ( $h['date'] ?? '' ) );
		}
		$mid = flxi_mime_msgid( $h['message-id'] ?? '' );
		$rep = flxi_mime_addresses( $h['reply-to'] ?? '' );
		$id  = flxi_mailbox_insert(
			array(
				'folder'      => $folder,
				'source'      => 'imap',
				'uid'         => (int) $uid,
				'uidvalidity' => (int) $uidvalidity,
				'message_id'  => $mid ? $mid : 'imap-' . $uidvalidity . '-' . $uid . '-' . $folder,
				'in_reply_to' => flxi_mime_msgid( $h['in-reply-to'] ?? '' ),
				'refs'        => mb_substr( (string) ( $h['references'] ?? '' ), 0, 4000 ),
				'from_email'  => $from ? $from[0]['email'] : '',
				'from_name'   => $from ? $from[0]['name'] : '',
				'to_list'     => flxi_mime_addr_text( flxi_mime_addresses( $h['to'] ?? '' ) ),
				'cc_list'     => flxi_mime_addr_text( flxi_mime_addresses( $h['cc'] ?? '' ) ),
				'reply_to'    => $rep ? $rep[0]['email'] : '',
				'subject'     => flxi_mime_decode( $h['subject'] ?? '' ),
				'snippet'     => flxi_mime_snippet( $text, $html ),
				'body_text'   => $text,
				'body_html'   => $html,
				'attachments' => $att,
				'size'        => (int) ( $m['RFC822.SIZE'] ?? 0 ),
				'mail_date'   => gmdate( 'Y-m-d H:i:s', $date ? $date : time() ),
				'is_read'     => 'sent' === $folder || in_array( '\\seen', $flags, true ) ? 1 : 0,
				'is_starred'  => in_array( '\\flagged', $flags, true ) ? 1 : 0,
				'replied'     => in_array( '\\answered', $flags, true ) ? 1 : 0,
				'is_system'   => 'inbox' === $folder && flxi_mailbox_is_system( $h, $from ? $from[0]['email'] : '' ) ? 1 : 0,
			)
		);
		if ( $id && ! empty( $GLOBALS['flxi_mailbox_inserted'] ) ) {
			++$count;
		}
	}
	return $count;
}

/** Son 100 iletinin bayraklarını (okundu, yıldız, cevaplandı) sunucudakiyle eşitler. */
function flxi_mailbox_refresh_flags( FLXI_Imap $imap, $folder, $uidvalidity ) {
	global $wpdb;
	$t    = flxi_table( 'mail' );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, uid, is_read, is_starred, replied FROM $t WHERE folder = %s AND source = 'imap' AND uidvalidity = %d ORDER BY uid DESC LIMIT 100", $folder, $uidvalidity ), ARRAY_A ); // phpcs:ignore
	if ( ! $rows ) {
		return;
	}
	$pending = array();
	foreach ( flxi_mailbox_pending() as $p ) {
		if ( 'flag' === $p['type'] && $p['folder'] === $folder ) {
			$pending[ (int) $p['uid'] ] = true;
		}
	}
	$fl = $imap->uid_fetch( wp_list_pluck( $rows, 'uid' ), 'UID FLAGS' );
	foreach ( $rows as $r ) {
		$uid = (int) $r['uid'];
		if ( ! isset( $fl[ $uid ] ) || isset( $pending[ $uid ] ) ) {
			continue;
		}
		$f   = array_map( 'strtolower', (array) ( $fl[ $uid ]['FLAGS'] ?? array() ) );
		$new = array(
			'is_read'    => 'sent' === $folder || in_array( '\\seen', $f, true ) ? 1 : 0,
			'is_starred' => in_array( '\\flagged', $f, true ) ? 1 : 0,
			'replied'    => in_array( '\\answered', $f, true ) ? 1 : 0,
		);
		if ( $new['is_read'] !== (int) $r['is_read'] || $new['is_starred'] !== (int) $r['is_starred'] || $new['replied'] !== (int) $r['replied'] ) {
			$wpdb->update( $t, $new, array( 'id' => (int) $r['id'] ) );
		}
	}
}

/** Bekleyen işleri sunucuya yazar: bayraklar ve Gönderilmiş'e eklenecek iletiler. */
function flxi_mailbox_run_pending( FLXI_Imap $imap, array $state ) {
	$p = flxi_mailbox_pending();
	if ( ! $p ) {
		return;
	}
	$keep = array();
	$by   = array();
	foreach ( $p as $x ) {
		if ( 'flag' === $x['type'] ) {
			$by[ $x['folder'] ][ ( $x['on'] ? '+' : '-' ) . $x['flag'] ][] = (int) $x['uid'];
		} elseif ( 'append' === $x['type'] ) {
			$name = $state['names']['sent'] ?? '';
			if ( '' === $name ) {
				continue; // Gönderilmiş klasörü yok: ekleme yapılamaz, bırak.
			}
			if ( ! $imap->append( $name, $x['raw'], '\\Seen' ) && ( (int) ( $x['tries'] ?? 0 ) < 3 ) ) {
				$x['tries'] = (int) ( $x['tries'] ?? 0 ) + 1;
				$keep[]     = $x;
			}
		}
	}
	foreach ( $by as $folder => $ops ) {
		$name = $state['names'][ $folder ] ?? '';
		if ( '' === $name || ! $imap->select( $name ) ) {
			continue;
		}
		foreach ( $ops as $op => $uids ) {
			$imap->uid_store( $uids, '+' === $op[0] ? '+FLAGS.SILENT' : '-FLAGS.SILENT', substr( $op, 1 ) );
		}
	}
	update_option( 'flxi_mailbox_pending', $keep, false );
}

/** Bir ekin içeriği (açınca sunucudan okunur). @return array|WP_Error name, type, data */
function flxi_mailbox_attachment( $mail_id, $section ) {
	$m = flxi_mailbox_get( $mail_id );
	if ( ! $m || 'imap' !== $m['source'] ) {
		return new WP_Error( 'flxi_att', __( 'Ek bulunamadı.', 'flx-mail' ) );
	}
	$att = null;
	foreach ( $m['attachments'] as $a ) {
		if ( (string) $a['section'] === (string) $section ) {
			$att = $a;
		}
	}
	if ( ! $att ) {
		return new WP_Error( 'flxi_att', __( 'Ek bulunamadı.', 'flx-mail' ) );
	}
	$key    = 'flxi_att_' . md5( $mail_id . '|' . $section );
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$state = flxi_mailbox_state();
	$imap  = flxi_imap_open();
	if ( is_wp_error( $imap ) ) {
		return $imap;
	}
	$name = $state['folders'][ $m['folder'] ]['name'] ?? ( $state['names'][ $m['folder'] ] ?? '' );
	$sel  = $name ? $imap->select( $name, true ) : false;
	if ( ! $sel || (int) $sel['uidvalidity'] !== (int) $m['uidvalidity'] ) {
		$imap->logout();
		return new WP_Error( 'flxi_att', __( 'İleti sunucuda bulunamadı (silinmiş ya da taşınmış olabilir).', 'flx-mail' ) );
	}
	$structure = $imap->uid_fetch( array( (int) $m['uid'] ), 'UID BODYSTRUCTURE' );
	$enc       = '7bit';
	foreach ( flxi_mime_parts( $structure[ (int) $m['uid'] ]['BODYSTRUCTURE'] ?? array() ) as $p ) {
		if ( $p['section'] === (string) $section ) {
			$enc = $p['encoding'];
		}
	}
	$b = $imap->uid_fetch( array( (int) $m['uid'] ), 'BODY.PEEK[' . preg_replace( '/[^0-9.]/', '', (string) $section ) . ']' );
	$imap->logout();
	$data = flxi_mime_body_decode( $b[ (int) $m['uid'] ][ 'BODY[' . $section . ']' ] ?? '', $enc );
	$out  = array( 'name' => $att['name'], 'type' => $att['type'], 'data' => $data );
	if ( strlen( $data ) < 1500000 ) {
		set_transient( $key, $out, 10 * MINUTE_IN_SECONDS );
	}
	return $out;
}

/* ------------------------------------------------------------------ */
/* Zamanlama                                                           */
/* ------------------------------------------------------------------ */

add_filter(
	'cron_schedules',
	function ( $s ) {
		$s['flxi_mailbox'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => 'Flux Mail posta kutusu',
		);
		return $s;
	}
);

add_action(
	'init',
	function () {
		$on = flxi_mailbox_enabled();
		$at = wp_next_scheduled( 'flxi_mailbox_sync' );
		if ( $on && ! $at ) {
			wp_schedule_event( time() + 60, 'flxi_mailbox', 'flxi_mailbox_sync' );
		} elseif ( ! $on && $at ) {
			wp_clear_scheduled_hook( 'flxi_mailbox_sync' );
		}
	}
);

add_action(
	'flxi_mailbox_sync',
	function () {
		flxi_mailbox_sync();
	}
);
