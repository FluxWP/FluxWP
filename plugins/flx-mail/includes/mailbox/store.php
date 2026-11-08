<?php
/**
 * Posta kutusu kayıtları: gelen/gönderilen e-postalar ve taslaklar.
 *
 * Kaynaklar: imap (bilgi@ Gelen ve Gönderilmiş, web posta dahil), center (bu ekrandan yazılan),
 * site (siteden giden sistem e-postaları; gövdesi Kayıtlar'da).
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

function flxi_mailbox_install_tables( $c ) {
	dbDelta(
		'CREATE TABLE ' . flxi_table( 'mail' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			folder varchar(12) NOT NULL DEFAULT 'inbox',
			source varchar(12) NOT NULL DEFAULT 'imap',
			uid bigint(20) unsigned NOT NULL DEFAULT 0,
			uidvalidity bigint(20) unsigned NOT NULL DEFAULT 0,
			message_id varchar(255) NOT NULL DEFAULT '',
			in_reply_to varchar(255) NOT NULL DEFAULT '',
			refs text NULL,
			thread_key varchar(64) NOT NULL DEFAULT '',
			from_email varchar(190) NOT NULL DEFAULT '',
			from_name varchar(190) NOT NULL DEFAULT '',
			to_list text NULL,
			cc_list text NULL,
			reply_to varchar(190) NOT NULL DEFAULT '',
			subject text NULL,
			snippet varchar(300) NOT NULL DEFAULT '',
			body_text longtext NULL,
			body_html longtext NULL,
			attachments text NULL,
			size int(10) unsigned NOT NULL DEFAULT 0,
			mail_date datetime NOT NULL,
			is_read tinyint(1) NOT NULL DEFAULT 0,
			is_starred tinyint(1) NOT NULL DEFAULT 0,
			is_system tinyint(1) NOT NULL DEFAULT 0,
			replied tinyint(1) NOT NULL DEFAULT 0,
			template varchar(160) NOT NULL DEFAULT '',
			log_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY folder_date (folder,mail_date),
			KEY message_id (message_id(100)),
			KEY thread_key (thread_key),
			KEY folder_uid (folder,uid)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . flxi_table( 'drafts' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reply_to_id bigint(20) unsigned NOT NULL DEFAULT 0,
			mode varchar(10) NOT NULL DEFAULT 'new',
			to_list text NULL,
			cc_list text NULL,
			subject text NULL,
			body longtext NULL,
			layout varchar(40) NOT NULL DEFAULT 'kurumsal',
			status varchar(12) NOT NULL DEFAULT 'draft',
			origin varchar(10) NOT NULL DEFAULT 'user',
			note text NULL,
			error text NULL,
			sent_mail_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			sent_at datetime NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) $c;"
	);
}

/** Otomatik/sistem e-postası mı (bildirim, bülten, teslim edilemedi…)? Varsayılan listede gizlenir. */
function flxi_mailbox_is_system( array $h, $from ) {
	$auto = strtolower( (string) ( $h['auto-submitted'] ?? '' ) );
	if ( $auto && 'no' !== $auto ) {
		return true;
	}
	if ( in_array( strtolower( (string) ( $h['precedence'] ?? '' ) ), array( 'bulk', 'list', 'junk', 'auto_reply' ), true ) ) {
		return true;
	}
	if ( ! empty( $h['list-unsubscribe'] ) || ! empty( $h['list-id'] ) || ! empty( $h['x-autoreply'] ) || ! empty( $h['x-autorespond'] ) ) {
		return true;
	}
	if ( preg_match( '/^(no-?reply|do-?not-?reply|mailer-daemon|postmaster|notifications?|bounce[s]?)[@+.-]/i', (string) $from ) ) {
		return true;
	}
	// Siteden giden kendi e-postalarımız (gelen kutusuna kopya düşerse).
	return ! empty( $h['x-flxi-site'] );
}

/** Yazışma anahtarı: yanıtlanan iletinin anahtarı, yoksa kendi Message-ID'si. */
function flxi_mailbox_thread_key( $message_id, $in_reply_to, $refs ) {
	global $wpdb;
	$t   = flxi_table( 'mail' );
	$ids = array_values( array_unique( array_filter( array_merge( array( $in_reply_to ), array_reverse( (array) $refs ) ) ) ) );
	foreach ( array_slice( $ids, 0, 10 ) as $id ) {
		$k = $wpdb->get_var( $wpdb->prepare( "SELECT thread_key FROM $t WHERE message_id = %s AND thread_key <> '' LIMIT 1", $id ) ); // phpcs:ignore
		if ( $k ) {
			return $k;
		}
	}
	// Kök ileti henüz yoksa (eski e-postalar sonra gelir) kökün kimliğinden: kök gelince aynı anahtarı alır.
	$refs = array_values( array_filter( (array) $refs ) );
	$root = $refs ? $refs[0] : ( $in_reply_to ? $in_reply_to : $message_id );
	return md5( $root ? $root : uniqid( 'flxi', true ) );
}

/** Kayıt ekler; aynı klasörde aynı Message-ID varsa eklemez. Dönen: id. */
function flxi_mailbox_insert( array $row ) {
	global $wpdb;
	$t   = flxi_table( 'mail' );
	$row = wp_parse_args(
		$row,
		array(
			'folder'      => 'inbox',
			'source'      => 'imap',
			'uid'         => 0,
			'uidvalidity' => 0,
			'message_id'  => '',
			'in_reply_to' => '',
			'refs'        => '',
			'thread_key'  => '',
			'from_email'  => '',
			'from_name'   => '',
			'to_list'     => '',
			'cc_list'     => '',
			'reply_to'    => '',
			'subject'     => '',
			'snippet'     => '',
			'body_text'   => '',
			'body_html'   => '',
			'attachments' => '',
			'size'        => 0,
			'mail_date'   => flxi_now(),
			'is_read'     => 0,
			'is_system'   => 0,
			'replied'     => 0,
			'template'    => '',
			'log_id'      => 0,
			'user_id'     => 0,
		)
	);
	if ( $row['message_id'] ) {
		$GLOBALS['flxi_mailbox_inserted'] = false;
		$dup = $wpdb->get_row( $wpdb->prepare( "SELECT id, uid FROM $t WHERE message_id = %s AND folder = %s LIMIT 1", $row['message_id'], $row['folder'] ), ARRAY_A ); // phpcs:ignore
		if ( $dup ) {
			// Bu ekrandan gönderileni IMAP "Gönderilmiş" klasöründe de bulduk: UID'yi bağla.
			if ( $row['uid'] && ! (int) $dup['uid'] ) {
				$wpdb->update( $t, array( 'uid' => (int) $row['uid'], 'uidvalidity' => (int) $row['uidvalidity'] ), array( 'id' => (int) $dup['id'] ) );
			}
			return (int) $dup['id'];
		}
	}
	if ( ! $row['thread_key'] ) {
		$row['thread_key'] = flxi_mailbox_thread_key( $row['message_id'], $row['in_reply_to'], flxi_mime_msgids( $row['refs'] ) );
	}
	$row['created_at'] = flxi_now();
	$row['subject']    = mb_substr( (string) $row['subject'], 0, 1000 );
	$row['snippet']    = mb_substr( (string) $row['snippet'], 0, 290 );
	$row['body_html']  = mb_substr( (string) $row['body_html'], 0, 600000 );
	$row['body_text']  = mb_substr( (string) $row['body_text'], 0, 300000 );
	foreach ( array( 'message_id', 'in_reply_to', 'from_email', 'from_name', 'reply_to' ) as $k ) {
		$row[ $k ] = mb_substr( (string) $row[ $k ], 0, 'message_id' === $k || 'in_reply_to' === $k ? 255 : 190 );
	}
	if ( is_array( $row['attachments'] ) ) {
		$row['attachments'] = $row['attachments'] ? wp_json_encode( $row['attachments'] ) : '';
	}
	$wpdb->insert( $t, $row );
	$id = (int) $wpdb->insert_id;
	$GLOBALS['flxi_mailbox_inserted'] = (bool) $id;
	if ( $id && 'inbox' === $row['folder'] && ! $row['is_system'] ) {
		do_action( 'flxi_mailbox_received', $id, $row );
	}
	return $id;
}

function flxi_mailbox_get( $id ) {
	global $wpdb;
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxi_table( 'mail' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore
	if ( $r ) {
		$r['attachments'] = $r['attachments'] ? (array) json_decode( $r['attachments'], true ) : array();
	}
	return $r;
}

/**
 * Liste.
 *
 * @param array $q folder (inbox|sent|all), search, system (göster), unread, thread, limit, offset
 */
function flxi_mailbox_query( array $q ) {
	global $wpdb;
	$t     = flxi_table( 'mail' );
	$where = array( '1=1' );
	$prm   = array();
	$f     = $q['folder'] ?? 'inbox';
	if ( in_array( $f, array( 'inbox', 'sent' ), true ) ) {
		$where[] = 'folder = %s';
		$prm[]   = $f;
	}
	if ( empty( $q['system'] ) ) {
		$where[] = 'is_system = 0';
	}
	if ( ! empty( $q['unread'] ) ) {
		$where[] = 'is_read = 0';
	}
	if ( ! empty( $q['starred'] ) ) {
		$where[] = 'is_starred = 1';
	}
	if ( ! empty( $q['thread'] ) ) {
		$where[] = 'thread_key = %s';
		$prm[]   = (string) $q['thread'];
	}
	if ( ! empty( $q['search'] ) ) {
		$like    = '%' . $wpdb->esc_like( (string) $q['search'] ) . '%';
		$where[] = '(subject LIKE %s OR from_email LIKE %s OR from_name LIKE %s OR to_list LIKE %s OR snippet LIKE %s)';
		array_push( $prm, $like, $like, $like, $like, $like );
	}
	$w      = implode( ' AND ', $where );
	$limit  = max( 1, min( 100, (int) ( $q['limit'] ?? 30 ) ) );
	$offset = max( 0, (int) ( $q['offset'] ?? 0 ) );
	$order  = ! empty( $q['thread'] ) ? 'ASC' : 'DESC';
	$sql    = "SELECT id, folder, source, message_id, thread_key, from_email, from_name, to_list, cc_list, subject, snippet, attachments, mail_date, is_read, is_starred, is_system, replied, template FROM $t WHERE $w ORDER BY mail_date $order, id $order LIMIT $limit OFFSET $offset";
	$rows   = $wpdb->get_results( $prm ? $wpdb->prepare( $sql, $prm ) : $sql, ARRAY_A ); // phpcs:ignore
	$total  = (int) $wpdb->get_var( $prm ? $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE $w", $prm ) : "SELECT COUNT(*) FROM $t WHERE $w" ); // phpcs:ignore
	return array( 'rows' => (array) $rows, 'total' => $total );
}

function flxi_mailbox_unread_count() {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . flxi_table( 'mail' ) . " WHERE folder = 'inbox' AND is_read = 0 AND is_system = 0" ); // phpcs:ignore
}

/** Okundu işaretler; IMAP'e bir sonraki eşitlemede yazılır. */
function flxi_mailbox_mark( $id, $field, $value ) {
	global $wpdb;
	if ( ! in_array( $field, array( 'is_read', 'is_starred', 'replied' ), true ) ) {
		return false;
	}
	$m = flxi_mailbox_get( $id );
	if ( ! $m ) {
		return false;
	}
	$wpdb->update( flxi_table( 'mail' ), array( $field => $value ? 1 : 0 ), array( 'id' => (int) $id ) );
	if ( 'imap' === $m['source'] && (int) $m['uid'] && in_array( $field, array( 'is_read', 'is_starred', 'replied' ), true ) ) {
		$flag = array( 'is_read' => '\\Seen', 'is_starred' => '\\Flagged', 'replied' => '\\Answered' )[ $field ];
		flxi_mailbox_pending_add( 'flag', array( 'folder' => $m['folder'], 'uid' => (int) $m['uid'], 'flag' => $flag, 'on' => (bool) $value ) );
	}
	return true;
}

/* ------------------------------------------------------------------ */
/* Bekleyen IMAP işleri (bayrak, Gönderilmiş'e ekleme)                   */
/* ------------------------------------------------------------------ */

function flxi_mailbox_pending() {
	$p = get_option( 'flxi_mailbox_pending', array() );
	return is_array( $p ) ? $p : array();
}

function flxi_mailbox_pending_add( $type, array $data ) {
	$p = flxi_mailbox_pending();
	if ( 'flag' === $type ) {
		// Aynı bayrak için son istek geçerli.
		$p = array_values(
			array_filter(
				$p,
				function ( $x ) use ( $data ) {
					return ! ( 'flag' === $x['type'] && $x['folder'] === $data['folder'] && $x['uid'] === $data['uid'] && $x['flag'] === $data['flag'] );
				}
			)
		);
	}
	$p[] = array_merge( array( 'type' => $type ), $data );
	// Gönderilmiş'e eklenecek ham iletiler büyük olabilir: en çok 20 tane beklet.
	$appends = array_keys(
		array_filter(
			$p,
			function ( $x ) {
				return 'append' === $x['type'];
			}
		)
	);
	while ( count( $appends ) > 20 ) {
		unset( $p[ array_shift( $appends ) ] );
	}
	update_option( 'flxi_mailbox_pending', array_slice( array_values( $p ), -500 ), false );
}

/* ------------------------------------------------------------------ */
/* Siteden giden e-postalar (sistem)                                    */
/* ------------------------------------------------------------------ */

add_action(
	'flxi_mail_logged',
	function ( $log_id, $status ) {
		global $wpdb;
		if ( 'sent' !== $status || ! flxi_mailbox_enabled() ) {
			return;
		}
		$l = $wpdb->get_row( $wpdb->prepare( 'SELECT id, created_at, channel, template, recipient, subject, sender, campaign_id FROM ' . flxi_table( 'log' ) . ' WHERE id = %d', (int) $log_id ), ARRAY_A ); // phpcs:ignore
		// Posta kutusundan yazılanlar kendi kaydını tutar.
		if ( ! $l || 'email' !== $l['channel'] || 'flx-mail/mailbox' === $l['template'] ) {
			return;
		}
		$from = flxi_mime_addresses( $l['sender'] );
		flxi_mailbox_insert(
			array(
				'folder'     => 'sent',
				'source'     => 'site',
				'from_email' => $from ? $from[0]['email'] : '',
				'from_name'  => $from ? $from[0]['name'] : '',
				'to_list'    => $l['recipient'],
				'subject'    => $l['subject'],
				'snippet'    => '',
				'mail_date'  => $l['created_at'],
				'is_read'    => 1,
				'is_system'  => 1,
				'template'   => $l['template'],
				'log_id'     => (int) $l['id'],
				'thread_key' => 'site-' . (int) $l['id'],
			)
		);
	},
	20,
	2
);

/* Bakım: eski sistem kayıtları Kayıtlar ile aynı sürede, diğerleri posta kutusu süresinde silinir. */
add_action(
	'flxi_daily',
	function () {
		global $wpdb;
		$t    = flxi_table( 'mail' );
		$sys  = gmdate( 'Y-m-d H:i:s', time() - max( 1, (int) flxi_setting( 'log_days' ) ) * DAY_IN_SECONDS );
		$keep = gmdate( 'Y-m-d H:i:s', time() - max( 30, (int) flxi_setting( 'mailbox_keep_days' ) ) * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE is_system = 1 AND mail_date < %s LIMIT 5000", $sys ) ); // phpcs:ignore
		$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE mail_date < %s LIMIT 5000", $keep ) ); // phpcs:ignore
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . flxi_table( 'drafts' ) . " WHERE status = 'sent' AND updated_at < %s LIMIT 5000", $keep ) ); // phpcs:ignore
	}
);

/* ------------------------------------------------------------------ */
/* Taslaklar                                                           */
/* ------------------------------------------------------------------ */

function flxi_draft_statuses() {
	return array(
		'draft'  => __( 'Draft', 'flx-mail' ),
		'review' => __( 'Awaiting approval', 'flx-mail' ),
		'sent'   => __( 'Sent', 'flx-mail' ),
		'failed' => __( 'Could not send', 'flx-mail' ),
	);
}

function flxi_draft_get( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxi_table( 'drafts' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore
}

/** Taslak oluşturur ya da günceller. Dönen: id. */
function flxi_draft_save( array $d, $id = 0 ) {
	global $wpdb;
	$t     = flxi_table( 'drafts' );
	$allow = array( 'reply_to_id', 'mode', 'to_list', 'cc_list', 'subject', 'body', 'layout', 'status', 'origin', 'note', 'error', 'sent_mail_id', 'sent_at' );
	$d     = array_intersect_key( $d, array_flip( $allow ) );
	if ( isset( $d['body'] ) ) {
		$d['body'] = wp_kses_post( (string) $d['body'] );
	}
	foreach ( array( 'to_list', 'cc_list' ) as $k ) {
		if ( isset( $d[ $k ] ) ) {
			$d[ $k ] = flxi_mime_addr_text( flxi_mime_addresses( is_array( $d[ $k ] ) ? implode( ',', $d[ $k ] ) : (string) $d[ $k ] ) );
		}
	}
	if ( isset( $d['subject'] ) ) {
		$d['subject'] = sanitize_text_field( (string) $d['subject'] );
	}
	if ( isset( $d['layout'] ) ) {
		$d['layout'] = flxi_layout_exists( $d['layout'] ) ? $d['layout'] : 'kurumsal';
	}
	if ( isset( $d['mode'] ) && ! in_array( $d['mode'], array( 'new', 'reply', 'replyall', 'forward' ), true ) ) {
		$d['mode'] = 'new';
	}
	$d['updated_at'] = flxi_now();
	if ( $id ) {
		$wpdb->update( $t, $d, array( 'id' => (int) $id ) );
		return (int) $id;
	}
	$d['created_at'] = $d['updated_at'];
	$d['created_by'] = get_current_user_id();
	$wpdb->insert( $t, $d );
	return (int) $wpdb->insert_id;
}

function flxi_draft_delete( $id ) {
	global $wpdb;
	return (bool) $wpdb->delete( flxi_table( 'drafts' ), array( 'id' => (int) $id ) );
}

function flxi_drafts_query( $status = '', $limit = 50 ) {
	global $wpdb;
	$t = flxi_table( 'drafts' );
	if ( $status ) {
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE status = %s ORDER BY updated_at DESC LIMIT %d", $status, (int) $limit ), ARRAY_A ); // phpcs:ignore
	}
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE status IN ('draft','review','failed') ORDER BY updated_at DESC LIMIT %d", (int) $limit ), ARRAY_A ); // phpcs:ignore
}

function flxi_drafts_count( $status ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxi_table( 'drafts' ) . ' WHERE status = %s', $status ) ); // phpcs:ignore
}
