<?php
defined( 'ABSPATH' ) || exit;

/* ---------- Sohbetler ---------- */

function flxc_get_chat( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'chats' ) . ' WHERE id = %d', $id ), ARRAY_A );
}

function flxc_find_chat( $conn, $jid ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'chats' ) . ' WHERE conn = %s AND jid = %s', $conn, $jid ), ARRAY_A );
}

/** @lid gibi ikinci kimlikle bilinen sohbet (meta.jids). */
function flxc_find_chat_by_alias( $conn, $jid ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'chats' ) . ' WHERE conn = %s AND meta LIKE %s ORDER BY id LIMIT 1', $conn, '%' . $wpdb->esc_like( '"' . $jid . '"' ) . '%' ), ARRAY_A );
}

/** Sohbetin Evolution'daki tüm kimlikleri (telefon jid'i + @lid). */
function flxc_chat_jids( $chat ) {
	$meta = flxc_decode( $chat['meta'] );
	$out  = array( (string) $chat['jid'] );
	foreach ( (array) ( $meta['jids'] ?? array() ) as $j ) {
		$out[] = (string) $j;
	}
	return array_values( array_unique( array_filter( $out ) ) );
}

function flxc_remember_alias( $chat, $jid ) {
	$jid = (string) $jid;
	if ( '' === $jid || $jid === $chat['jid'] || false === strpos( $jid, '@' ) ) {
		return;
	}
	$fresh = flxc_get_chat( (int) $chat['id'] );
	$meta  = flxc_decode( $fresh ? $fresh['meta'] : $chat['meta'] );
	$list  = (array) ( $meta['jids'] ?? array() );
	if ( in_array( $jid, $list, true ) ) {
		return;
	}
	$list[]       = $jid;
	$meta['jids'] = array_values( array_slice( $list, -5 ) );
	flxc_update_chat( (int) $chat['id'], array( 'meta' => flxc_json( $meta ) ) );
}

function flxc_find_chat_by_phone( $conn, $phone ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'chats' ) . ' WHERE conn = %s AND phone = %s ORDER BY id LIMIT 1', $conn, $phone ), ARRAY_A );
}

/**
 * @lid sohbetine gerçek telefon numarasını yazar. Numarayla ayrı sohbet zaten varsa dokunmaz.
 * @return array|null sohbet
 */
function flxc_lid_chat_learn_phone( $conn, $lid_jid, $phone ) {
	if ( '' === $phone || flxc_find_chat( $conn, $phone . '@s.whatsapp.net' ) || flxc_find_chat_by_phone( $conn, $phone ) ) {
		return null;
	}
	$chat = flxc_find_chat( $conn, $lid_jid );
	if ( ! $chat ) {
		return null;
	}
	if ( $phone !== (string) $chat['phone'] ) {
		flxc_update_chat( (int) $chat['id'], array( 'phone' => $phone ) );
		$chat['phone'] = $phone;
	}
	return $chat;
}

/** Sohbeti bulur ya da oluşturur. @return array chat satırı */
function flxc_ensure_chat( $conn, $jid, $data = array() ) {
	global $wpdb;
	$chat = flxc_find_chat( $conn, $jid );
	if ( ! $chat && '@lid' === substr( $jid, -4 ) ) {
		$chat = flxc_find_chat_by_alias( $conn, $jid );
	}
	if ( ! $chat ) {
		$phone = flxc_is_group_jid( $jid ) ? '' : flxc_jid_phone( $jid );
		// Aynı numara farklı jid ile (ör. @lid) daha önce açıldıysa onu kullan.
		if ( $phone ) {
			$chat = flxc_find_chat_by_phone( $conn, $phone );
		}
	}
	if ( $chat ) {
		$upd = array();
		if ( ! empty( $data['push_name'] ) && $data['push_name'] !== $chat['push_name'] ) {
			$upd['push_name'] = mb_substr( $data['push_name'], 0, 190 );
		}
		if ( $upd ) {
			flxc_update_chat( $chat['id'], $upd );
			$chat = array_merge( $chat, $upd );
		}
		// 1.41.0: a chat opened earlier without a name (e.g. by an incoming message) takes the name a plugin
		// sends with (donor, certificate owner); otherwise the name known from records for this number.
		if ( '' === (string) $chat['name'] && ! flxc_is_group_jid( $jid ) ) {
			$name = trim( (string) ( $data['name'] ?? '' ) );
			$note = __( 'Name given by the sending record', 'flx-chat' );
			if ( '' === $name && '' !== (string) $chat['phone'] && ! get_transient( 'flxc_noname_' . $chat['phone'] ) ) {
				$name = flxc_lookup_contact_name( $chat['phone'] );
				$note = __( 'Filled from donation and certificate records', 'flx-chat' );
				if ( '' === $name ) {
					set_transient( 'flxc_noname_' . $chat['phone'], 1, HOUR_IN_SECONDS ); // Incoming messages do not look it up every time.
				}
			}
			if ( '' !== $name && flxc_fill_chat_name( $chat, $name, array_merge( flxc_clog_default_by(), array( 'note' => $note ) ) ) ) {
				$chat = flxc_get_chat( $chat['id'] );
			}
		}
		return $chat;
	}
	$row = array(
		'conn'       => $conn,
		'jid'        => $jid,
		'phone'      => flxc_is_group_jid( $jid ) ? '' : flxc_jid_phone( $jid ),
		'name'       => mb_substr( (string) ( $data['name'] ?? '' ), 0, 190 ),
		'push_name'  => mb_substr( (string) ( $data['push_name'] ?? '' ), 0, 190 ),
		'status'     => 'open',
		'labels'     => '',
		'notes'      => '',
		'rev'        => flxc_rev(),
		'created_at' => flxc_now(),
		'meta'       => '{}',
	);
	$name_note = '' !== $row['name'] ? __( 'Name given by the sending record', 'flx-chat' ) : '';
	if ( '' === $row['name'] && $row['phone'] ) {
		$row['name'] = flxc_lookup_contact_name( $row['phone'] );
		$name_note   = __( 'Filled from donation and certificate records', 'flx-chat' );
	}
	$wpdb->insert( flxc_table( 'chats' ), $row );
	$row['id'] = (int) $wpdb->insert_id;
	if ( ! $row['id'] ) {
		// Aynı anda iki webhook geldiyse benzersiz anahtar ikinciyi reddeder.
		$chat = flxc_find_chat( $conn, $jid );
		if ( $chat ) {
			return $chat;
		}
	}
	if ( '' !== $row['name'] && $row['id'] && function_exists( 'flxc_clog_add' ) ) {
		flxc_clog_add( $row['id'], 'name', '', $row['name'], array_merge( flxc_clog_default_by(), array( 'note' => $name_note ) ) );
	}
	do_action( 'flxc_chat_created', $row );
	return array_merge(
		$row,
		array(
			'avatar'       => '',
			'avatar_at'    => 0,
			'assigned_to'  => 0,
			'unread'       => 0,
			'pinned'       => 0,
			'last_msg_id'  => 0,
			'last_ts'      => 0,
			'last_in_ts'   => 0,
			'last_auto_ts' => 0,
		)
	);
}

/**
 * Bilinen kişi adı (1.43.0): ortak FluxWP kişi tablosundan (Flux CRM, Certificates, Donate, Commerce aynı kayıt).
 * Ortak tablo yoksa (eski paketler) eski sıra: Certificates kişisi, CRM kişisi, Donate bağışçısı. Filtre: flxc_contact_name.
 */
function flxc_lookup_contact_name( $phone ) {
	global $wpdb;
	$phone = preg_replace( '/\D+/', '', (string) $phone );
	$name  = '';
	if ( '' === $phone ) {
		return (string) apply_filters( 'flxc_contact_name', '', $phone );
	}
	if ( function_exists( 'flx_person_find' ) ) {
		$p    = flx_person_get( flx_person_find( array( 'phone' => $phone ) ) );
		$name = $p ? trim( (string) $p['name'] ) : '';
		return (string) apply_filters( 'flxc_contact_name', $name, $phone );
	}
	if ( function_exists( 'flxs_table' ) ) {
		$t = flxs_table( 'people' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$name = (string) $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $t WHERE phone = %s AND name <> '' ORDER BY id DESC LIMIT 1", $phone ) );
	}
	if ( '' === $name && class_exists( 'FLXCRM_Contacts' ) && function_exists( 'flxcrm_contact_for' ) ) {
		$c    = FLXCRM_Contacts::get( flxcrm_contact_for( array( 'phone' => $phone ) ) );
		$name = $c ? trim( (string) $c['name'] ) : '';
	}
	if ( '' === $name && class_exists( 'FLXB_Donors' ) ) {
		$d    = FLXB_Donors::find( '+' . $phone, '' );
		$name = $d ? trim( (string) $d['name'] ) : '';
	}
	return (string) apply_filters( 'flxc_contact_name', $name, $phone );
}

/**
 * @param array $by 1.41.0: who makes the change (source staff|ai|system|contact, user_id, note) for the
 *                  contact change log. Default: the logged-in user, otherwise the system.
 */
function flxc_update_chat( $id, array $fields, array $by = array() ) {
	global $wpdb;
	$before = null;
	if ( function_exists( 'flxc_clog_diff' ) && array_intersect( array_keys( $fields ), flxc_clog_fields() ) ) {
		$before = flxc_get_chat( (int) $id );
	}
	$fields['rev'] = flxc_rev();
	$wpdb->update( flxc_table( 'chats' ), $fields, array( 'id' => (int) $id ) );
	if ( $before ) {
		flxc_clog_diff( $before, $fields, $by );
	}
}

function flxc_chat_title( $chat ) {
	if ( '' !== (string) $chat['name'] ) {
		return $chat['name'];
	}
	if ( '' !== (string) $chat['push_name'] ) {
		return $chat['push_name'];
	}
	return $chat['phone'] ? flxc_format_phone( $chat['phone'] ) : $chat['jid'];
}

/** Temsilci bu sohbeti görebilir mi? ("Temsilciler yalnız kendi sohbetlerini görsün" ayarı) */
function flxc_user_can_see_chat( $chat, $user_id = 0 ) {
	if ( ! $chat ) {
		return false;
	}
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( ! flxc_setting( 'restrict_agents' ) || user_can( $user_id, 'flxc_manage' ) || user_can( $user_id, 'manage_options' ) ) {
		return true;
	}
	return 0 === (int) $chat['assigned_to'] || (int) $chat['assigned_to'] === (int) $user_id;
}

/**
 * Sohbet listesi.
 * @param array $args filter (all|unread|mine|unassigned|closed), q, label, since (rev), limit, offset, conn
 */
function flxc_query_chats( $args = array() ) {
	global $wpdb;
	$args  = wp_parse_args( $args, array( 'filter' => 'all', 'q' => '', 'label' => '', 'since' => 0, 'limit' => 60, 'offset' => 0, 'conn' => '', 'channel' => '', 'count' => false ) );
	$t     = flxc_table( 'chats' );
	$where = array( '1=1' );
	$prm   = array();
	$uid   = get_current_user_id();

	if ( $args['since'] ) {
		$where[] = 'rev > %d';
		$prm[]   = (int) $args['since'];
	} else {
		switch ( $args['filter'] ) {
			case 'unread':
				$where[] = "unread > 0 AND status <> 'closed'";
				break;
			case 'mine':
				$where[] = "assigned_to = %d AND status <> 'closed'";
				$prm[]   = $uid;
				break;
			case 'unassigned':
				$where[] = "assigned_to = 0 AND status <> 'closed'";
				break;
			case 'closed':
				$where[] = "status = 'closed'";
				break;
			case 'ai':
				$where[] = 'meta LIKE %s';
				$prm[]   = '%"ai":1%';
				break;
			case 'any':
				break; // kapalılar dahil (uygulamanın "tümü" filtresi)
			default:
				$where[] = "status <> 'closed'";
		}
		if ( '' !== $args['label'] ) {
			$where[] = 'CONCAT(\',\', labels, \',\') LIKE %s';
			$prm[]   = '%,' . $wpdb->esc_like( $args['label'] ) . ',%';
		}
		if ( '' !== trim( $args['q'] ) ) {
			$q       = trim( $args['q'] );
			$like    = '%' . $wpdb->esc_like( $q ) . '%';
			$digits  = preg_replace( '/\D+/', '', $q );
			$sub     = 'SELECT DISTINCT chat_id FROM ' . flxc_table( 'messages' ) . ' WHERE body LIKE %s';
			$cond    = "(name LIKE %s OR push_name LIKE %s OR notes LIKE %s OR id IN ($sub)";
			array_push( $prm, $like, $like, $like, $like );
			if ( strlen( $digits ) >= 3 ) {
				$cond .= ' OR phone LIKE %s';
				$prm[] = '%' . ltrim( $digits, '0' ) . '%';
			}
			$where[] = $cond . ')';
		}
	}
	if ( '' !== $args['conn'] ) {
		$where[] = 'conn = %s';
		$prm[]   = $args['conn'];
	}
	if ( '' !== $args['channel'] && ! $args['since'] ) {
		// 1.37.0: kanal süzgeci. WhatsApp = sanal olmayan bağlantılar; diğerleri kanalın kaydettiği sanal bağlantılar.
		$chs     = flxc_channels();
		$virtual = array();
		foreach ( $chs as $ch ) {
			$virtual = array_merge( $virtual, $ch['conns'] );
		}
		if ( 'whatsapp' === $args['channel'] ) {
			if ( $virtual ) {
				$where[] = 'conn NOT IN (' . implode( ',', array_fill( 0, count( $virtual ), '%s' ) ) . ')';
				$prm     = array_merge( $prm, $virtual );
			}
		} else {
			$own     = $chs[ $args['channel'] ]['conns'] ?? array();
			$where[] = $own ? 'conn IN (' . implode( ',', array_fill( 0, count( $own ), '%s' ) ) . ')' : '1=0';
			$prm     = array_merge( $prm, $own );
		}
	}
	if ( ! flxc_setting( 'show_groups' ) ) {
		$where[] = "jid NOT LIKE '%@g.us'";
	}
	if ( flxc_setting( 'restrict_agents' ) && ! flxc_can_manage() ) {
		$where[] = '(assigned_to = 0 OR assigned_to = %d)';
		$prm[]   = $uid;
	}
	if ( ! empty( $args['count'] ) ) {
		// 1.33.0: sayfalama için toplam (uygulama modülü).
		$sql = "SELECT COUNT(*) FROM $t WHERE " . implode( ' AND ', $where );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $prm ? $wpdb->prepare( $sql, $prm ) : $sql );
	}
	$sql = "SELECT * FROM $t WHERE " . implode( ' AND ', $where ) . ' ORDER BY pinned DESC, last_ts DESC, id DESC LIMIT %d OFFSET %d';
	array_push( $prm, (int) $args['limit'], (int) $args['offset'] );
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return $wpdb->get_results( $wpdb->prepare( $sql, $prm ), ARRAY_A );
}

function flxc_unread_total() {
	global $wpdb;
	$t   = flxc_table( 'chats' );
	$sql = "SELECT COALESCE(SUM(unread),0) FROM $t WHERE status <> 'closed'";
	if ( ! flxc_setting( 'show_groups' ) ) {
		$sql .= " AND jid NOT LIKE '%@g.us'";
	}
	if ( flxc_setting( 'restrict_agents' ) && ! flxc_can_manage() ) {
		$sql .= $wpdb->prepare( ' AND (assigned_to = 0 OR assigned_to = %d)', get_current_user_id() );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return (int) $wpdb->get_var( $sql );
}

function flxc_chats_for_js( array $rows ) {
	global $wpdb;
	$ids = array();
	foreach ( $rows as $r ) {
		if ( $r['last_msg_id'] ) {
			$ids[] = (int) $r['last_msg_id'];
		}
	}
	$last = array();
	if ( $ids ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$msgs = $wpdb->get_results( 'SELECT * FROM ' . flxc_table( 'messages' ) . ' WHERE id IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')', ARRAY_A );
		foreach ( $msgs as $m ) {
			$last[ (int) $m['id'] ] = $m;
		}
	}
	$out = array();
	foreach ( $rows as $r ) {
		$out[] = flxc_chat_for_js( $r, $last[ (int) $r['last_msg_id'] ] ?? null );
	}
	return $out;
}

function flxc_chat_for_js( $r, $last = null ) {
	$meta = flxc_decode( $r['meta'] ?? '' );
	$typing = get_transient( 'flxc_typing_' . $r['id'] );
	$bot    = function_exists( 'flxc_bot_chat_status' ) ? flxc_bot_chat_status( $r ) : '';
	return array(
		'id'        => (int) $r['id'],
		'conn'      => $r['conn'],
		'jid'       => $r['jid'],
		'phone'     => $r['phone'],
		'phoneFmt'  => flxc_is_virtual_conn( $r['conn'] ) ? (string) apply_filters( 'flxc_virtual_label', __( 'Site chat', 'flx-chat' ), $r ) : ( '' === (string) $r['phone'] && '@lid' === substr( (string) $r['jid'], -4 ) ? __( 'Hidden number (WhatsApp)', 'flx-chat' ) : flxc_format_phone( $r['phone'] ) ),
		'channel'   => flxc_chat_channel( $r ),
		'title'     => flxc_chat_title( $r ),
		'name'      => $r['name'],
		'pushName'  => $r['push_name'],
		'avatar'    => flxc_avatar_url( $r ),
		'avatarAt'  => (int) $r['avatar_at'],
		'labels'    => '' === $r['labels'] ? array() : explode( ',', $r['labels'] ),
		'notes'     => (string) $r['notes'],
		'status'    => $r['status'],
		'assigned'  => (int) $r['assigned_to'],
		'unread'    => (int) $r['unread'],
		'pinned'    => (int) $r['pinned'],
		'lastTs'    => (int) $r['last_ts'],
		'lastInTs'  => (int) $r['last_in_ts'],
		'group'     => flxc_is_group_jid( $r['jid'] ),
		'typing'    => $typing ? (int) $typing : 0,
		'rev'       => (int) $r['rev'],
		'created'   => strtotime( $r['created_at'] . ' UTC' ) * 1000,
		'last'      => $last ? array(
			'dir'    => $last['direction'],
			'type'   => $last['type'],
			'body'   => flxc_preview_text( $last ),
			'status' => $last['status'],
			'user'   => flxc_user_name( $last['user_id'] ),
		) : null,
		'unknownName' => '' === $r['name'],
		'meta'      => array_intersect_key( $meta, array_flip( array( 'blocked' ) ) ),
		'staffUser' => (int) ( $meta['staff_user'] ?? 0 ),
		'bot'       => $bot,
		'botBusy'   => 'on' === $bot && get_transient( 'flxc_bot_busy_' . $r['id'] ) ? 1 : 0,
		'ai'        => ! empty( $meta['bot']['ai'] ) ? 1 : 0,
		'optout'    => function_exists( 'flxc_chat_blocked' ) && flxc_chat_blocked( $r ) ? 1 : 0,
	);
}

/* ---------- Mesajlar ---------- */

function flxc_get_message( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'messages' ) . ' WHERE id = %d', $id ), ARRAY_A );
}

function flxc_find_message_by_wa( $conn, $wa_id ) {
	global $wpdb;
	if ( '' === (string) $wa_id ) {
		return null;
	}
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'messages' ) . ' WHERE wa_id = %s AND conn = %s ORDER BY id LIMIT 1', $wa_id, $conn ), ARRAY_A );
}

/**
 * Mesaj ekler, sohbetin son mesajını ve okunmamış sayısını günceller.
 * @return int mesaj id
 */
function flxc_insert_message( array $m, $chat = null ) {
	global $wpdb;
	$m = wp_parse_args(
		$m,
		array(
			'chat_id'    => 0,
			'conn'       => '',
			'wa_id'      => '',
			'direction'  => 'in',
			'type'       => 'text',
			'body'       => '',
			'media_path' => '',
			'media_mime' => '',
			'media_name' => '',
			'media_size' => 0,
			'quoted_id'  => 0,
			'quoted_wa'  => '',
			'status'     => '',
			'error'      => '',
			'user_id'    => 0,
			'source'     => '',
			'ts'         => (int) floor( microtime( true ) * 1000 ),
			'meta'       => array(),
		)
	);
	$skip_unread = ! empty( $m['skip_unread'] );
	$m           = array_intersect_key( $m, array_flip( array( 'chat_id', 'conn', 'wa_id', 'direction', 'type', 'body', 'media_path', 'media_mime', 'media_name', 'media_size', 'quoted_id', 'quoted_wa', 'status', 'error', 'user_id', 'source', 'ts', 'meta' ) ) );
	if ( ! $m['quoted_id'] && $m['quoted_wa'] ) {
		$q = flxc_find_message_by_wa( $m['conn'], $m['quoted_wa'] );
		if ( $q ) {
			$m['quoted_id'] = (int) $q['id'];
		}
	}
	$m['meta']       = flxc_json( (object) $m['meta'] );
	$m['rev']        = flxc_rev();
	$m['created_at'] = flxc_now();
	$wpdb->insert( flxc_table( 'messages' ), $m );
	$id = (int) $wpdb->insert_id;
	if ( ! $id ) {
		return 0;
	}
	$chat = $chat ? $chat : flxc_get_chat( $m['chat_id'] );
	if ( $chat ) {
		$upd = array();
		if ( $m['ts'] >= (int) $chat['last_ts'] && 'reaction' !== $m['type'] ) {
			$upd['last_msg_id'] = $id;
			$upd['last_ts']     = $m['ts'];
		}
		if ( 'in' === $m['direction'] ) {
			$upd['unread'] = (int) $chat['unread'] + 1;
			if ( $m['ts'] > (int) $chat['last_in_ts'] ) {
				$upd['last_in_ts'] = $m['ts'];
			}
			if ( 'closed' === $chat['status'] ) {
				$upd['status'] = 'open';
			}
		}
		if ( 'out' === $m['direction'] ) {
			$upd['unread'] = 0;
		}
		if ( $skip_unread ) {
			unset( $upd['unread'], $upd['status'] );
		}
		if ( $upd ) {
			flxc_update_chat( $chat['id'], $upd );
		}
	}
	return $id;
}

function flxc_update_message( $id, array $fields ) {
	global $wpdb;
	if ( isset( $fields['meta'] ) && is_array( $fields['meta'] ) ) {
		$fields['meta'] = flxc_json( (object) $fields['meta'] );
	}
	$fields['rev'] = flxc_rev();
	$wpdb->update( flxc_table( 'messages' ), $fields, array( 'id' => (int) $id ) );
	// Sohbet listesindeki önizleme (tik) de güncellensin.
	$m = flxc_get_message( $id );
	if ( $m ) {
		$chat = flxc_get_chat( $m['chat_id'] );
		if ( $chat && (int) $chat['last_msg_id'] === (int) $id ) {
			flxc_update_chat( $chat['id'], array() );
		}
	}
}

/**
 * 1.38.0: mesaj silme hiçbir zaman satırı veya dosyayı kaldırmaz (hard delete yok).
 * type 'deleted' olur; asıl tür/metin meta'da saklanır, kim/ne zaman/nasıl silindiği yazılır.
 * $scope: contact (kişi sildi), phone (bağlı telefondan silindi), everyone (panelden herkesten silindi), panel (yalnız panelde gizlendi).
 */
/**
 * 1.45.0: mesaj metnini düzenlenmiş olarak günceller (panelden ya da WhatsApp'tan gelen düzenleme).
 * İlk metin meta orig_body'de kalır; düzenleyen panel kullanıcısıysa edited_by yazılır.
 */
function flxc_apply_edit( $m, $body, $user_id = 0 ) {
	$meta = flxc_decode( $m['meta'] );
	if ( ! isset( $meta['orig_body'] ) ) {
		$meta['orig_body'] = (string) $m['body'];
	}
	$meta['edited']    = 1;
	$meta['edited_at'] = time();
	if ( $user_id ) {
		$meta['edited_by'] = (int) $user_id;
	}
	flxc_update_message( $m['id'], array( 'body' => (string) $body, 'meta' => $meta ) );
}

function flxc_soft_delete_message( $m, $scope, $user_id = 0 ) {
	if ( ! is_array( $m ) ) {
		$m = flxc_get_message( (int) $m );
	}
	if ( ! $m ) {
		return false;
	}
	$meta = flxc_decode( $m['meta'] );
	if ( 'deleted' !== $m['type'] ) {
		$meta['deleted_type'] = $m['type'];
		$meta['deleted_body'] = $m['body'];
	} elseif ( ! empty( $meta['deleted_scope'] ) && ! in_array( $scope, array( 'everyone', 'panel' ), true ) ) {
		return true; // Zaten silinmiş; webhook yankısı mevcut kaydı ezmez.
	}
	$meta['deleted_scope'] = (string) $scope;
	$meta['deleted_by']    = (int) $user_id;
	$meta['deleted_at']    = time();
	flxc_update_message( $m['id'], array( 'type' => 'deleted', 'body' => '', 'meta' => $meta ) );
	return true;
}

/** WhatsApp "herkesten sil" süresi (saat). WhatsApp sınırı yaklaşık 2 gün 12 saat; daha uzun ayar anlamsız. */
function flxc_revoke_limit_hours() {
	return (int) min( 60, max( 0, (int) flxc_setting( 'delete_everyone_hours' ) ) );
}

/** Bu bağlantıda karşı taraftan silme destekleniyor mu (yalnız Evolution hattı; Cloud API ve site sohbeti desteklemez). */
function flxc_conn_can_revoke( $conn_id ) {
	static $cache = array();
	$conn_id = (string) $conn_id;
	if ( ! isset( $cache[ $conn_id ] ) ) {
		$ok = '' !== $conn_id && ! flxc_is_virtual_conn( $conn_id );
		if ( $ok ) {
			$cfg = flxc_connection_config( $conn_id );
			$ok  = ! is_wp_error( $cfg ) && empty( $cfg['wacloud'] );
		}
		$cache[ $conn_id ] = (bool) apply_filters( 'flxc_conn_can_revoke', $ok, $conn_id );
	}
	return $cache[ $conn_id ];
}

/** Herkesten silinebileceği son an (ms), olmuyorsa 0. */
function flxc_revoke_until( $m ) {
	$h = flxc_revoke_limit_hours();
	if ( ! $h || 'out' !== $m['direction'] || '' === (string) $m['wa_id'] || 'deleted' === $m['type'] || 'failed' === $m['status'] || ! flxc_conn_can_revoke( $m['conn'] ) ) {
		return 0;
	}
	return (int) $m['ts'] + $h * HOUR_IN_SECONDS * 1000;
}

/**
 * 1.45.0: WhatsApp düzenleme süresi (dakika). WhatsApp gönderimden sonra 15 dakika izin verir.
 * Filtre `flxc_edit_minutes` (0 = düzenleme kapalı, en çok 15).
 */
function flxc_edit_minutes() {
	return (int) min( 15, max( 0, (int) apply_filters( 'flxc_edit_minutes', 15 ) ) );
}

/**
 * Düzenlenebileceği son an (ms), olmuyorsa 0. Yalnız Evolution hattından giden, gönderilmiş mesaj:
 * metin ya da (1.46.0) fotoğraf/video/belge açıklaması (WhatsApp açıklamayı da düzenletir).
 */
function flxc_edit_until( $m ) {
	$min = flxc_edit_minutes();
	if ( ! $min || 'out' !== $m['direction'] || '' === (string) $m['wa_id'] || ! in_array( $m['type'], array( 'text', 'image', 'video', 'document' ), true ) || in_array( $m['status'], array( 'failed', 'pending', '' ), true ) || ! flxc_conn_can_revoke( $m['conn'] ) ) {
		return 0;
	}
	return (int) $m['ts'] + $min * MINUTE_IN_SECONDS * 1000;
}

/** Durum yalnız ileri gider: pending < sent < delivered < read < played. */
function flxc_status_rank( $s ) {
	$r = array( 'failed' => -1, '' => 0, 'pending' => 1, 'sent' => 2, 'delivered' => 3, 'read' => 4, 'played' => 5 );
	return $r[ $s ] ?? 0;
}

function flxc_chat_messages( $chat_id, $before_id = 0, $limit = 40 ) {
	global $wpdb;
	$t = flxc_table( 'messages' );
	if ( $before_id ) {
		$b    = flxc_get_message( $before_id );
		$rows = $b ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE chat_id = %d AND type <> 'reaction' AND (ts < %d OR (ts = %d AND id < %d)) ORDER BY ts DESC, id DESC LIMIT %d", $chat_id, $b['ts'], $b['ts'], $b['id'], $limit ), ARRAY_A ) : array(); // phpcs:ignore
	} else {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE chat_id = %d AND type <> 'reaction' ORDER BY ts DESC, id DESC LIMIT %d", $chat_id, $limit ), ARRAY_A ); // phpcs:ignore
	}
	return array_reverse( $rows );
}

/**
 * Yapay zekâya verilecek geçmiş: son $hours saatteki tüm mesajlar (iki taraf), en az $min, en çok $max mesaj.
 */
function flxc_recent_messages( $chat_id, $hours = 24, $min = 10, $max = 120 ) {
	$cut  = ( time() - max( 1, (int) $hours ) * HOUR_IN_SECONDS ) * 1000;
	$rows = array_reverse( flxc_chat_messages( $chat_id, 0, $max ) );
	$out  = array();
	foreach ( $rows as $m ) {
		if ( count( $out ) >= $min && (int) $m['ts'] < $cut ) {
			break;
		}
		$out[] = $m;
	}
	return array_reverse( $out );
}

function flxc_messages_since( $chat_id, $since, $after_id = 0 ) {
	global $wpdb;
	$t = flxc_table( 'messages' );
	// after_id: istemcinin gördüğü en büyük id; geç yazılan satır rev penceresini kaçırsa da gelir.
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE chat_id = %d AND (rev > %d OR id > %d) AND type <> 'reaction' ORDER BY ts ASC, id ASC LIMIT 200", $chat_id, $since, $after_id ? $after_id : PHP_INT_MAX ), ARRAY_A ); // phpcs:ignore
}

/** Gelen yeni mesajlar (bildirim için): verilen rev'den sonra eklenen, gelen yönlü mesajlar. */
function flxc_new_incoming_since( $since ) {
	global $wpdb;
	$t  = flxc_table( 'messages' );
	$c  = flxc_table( 'chats' );
	$ms = (int) floor( $since );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT m.id, m.chat_id, m.type, m.body, m.ts FROM $t m WHERE m.rev > %d AND m.direction = 'in' AND m.ts > %d ORDER BY m.id DESC LIMIT 20", $since, $ms - 120000 ), ARRAY_A ); // phpcs:ignore
	$out  = array();
	foreach ( $rows as $r ) {
		$chat = flxc_get_chat( $r['chat_id'] );
		if ( ! $chat || ! flxc_user_can_see_chat( $chat ) || ( flxc_is_group_jid( $chat['jid'] ) && ! flxc_setting( 'show_groups' ) ) ) {
			continue;
		}
		$out[] = array(
			'id'     => (int) $r['id'],
			'chat'   => (int) $r['chat_id'],
			'title'  => flxc_chat_title( $chat ),
			'body'   => flxc_preview_text( $r ),
			'avatar' => flxc_avatar_url( $chat ),
		);
	}
	unset( $c );
	return $out;
}

/** Listedeki kısa metin. Medyada türün adı. */
function flxc_preview_text( $m ) {
	$body = trim( (string) $m['body'] );
	$labels = array(
		'image'    => __( 'Photo', 'flx-chat' ),
		'video'    => __( 'Video', 'flx-chat' ),
		'audio'    => __( 'Voice message', 'flx-chat' ),
		'document' => __( 'Document', 'flx-chat' ),
		'sticker'  => __( 'Sticker', 'flx-chat' ),
		'location' => __( 'Location', 'flx-chat' ),
		'contact'  => __( 'Contact card', 'flx-chat' ),
		'poll'     => __( 'Poll', 'flx-chat' ),
		'deleted'  => __( 'This message was deleted', 'flx-chat' ),
		'unknown'  => __( 'Unsupported message', 'flx-chat' ),
	);
	$type = $m['type'];
	if ( 'note' === ( $m['direction'] ?? '' ) ) {
		return '📝 ' . mb_substr( $body, 0, 120 );
	}
	if ( isset( $labels[ $type ] ) ) {
		if ( 'document' === $type && ! empty( $m['media_name'] ) && '' === $body ) {
			return $m['media_name'];
		}
		return '' !== $body && ! in_array( $type, array( 'deleted', 'location', 'contact' ), true ) ? $labels[ $type ] . ': ' . mb_substr( $body, 0, 100 ) : $labels[ $type ];
	}
	return mb_substr( preg_replace( '/\s+/', ' ', $body ), 0, 120 );
}

function flxc_media_url( $id ) {
	static $nonce = null;
	if ( null === $nonce ) {
		$nonce = wp_create_nonce( 'wp_rest' );
	}
	return add_query_arg( '_wpnonce', $nonce, rest_url( 'flx-chat/v1/media/' . (int) $id ) );
}

function flxc_messages_for_js( array $rows ) {
	global $wpdb;
	$qids = array();
	foreach ( $rows as $r ) {
		if ( $r['quoted_id'] ) {
			$qids[] = (int) $r['quoted_id'];
		}
	}
	$quoted = array();
	if ( $qids ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $wpdb->get_results( 'SELECT * FROM ' . flxc_table( 'messages' ) . ' WHERE id IN (' . implode( ',', array_unique( $qids ) ) . ')', ARRAY_A ) as $q ) {
			$quoted[ (int) $q['id'] ] = $q;
		}
	}
	$out = array();
	foreach ( $rows as $r ) {
		$out[] = flxc_message_for_js( $r, $quoted[ (int) $r['quoted_id'] ] ?? null );
	}
	return $out;
}

function flxc_message_for_js( $r, $q = null ) {
	$meta = flxc_decode( $r['meta'] );
	// Karşı tarafın sildiği mesaj: içerik bizde saklı, ekip görür ("Silindi" etiketiyle). Yapay zekâ okumaz.
	$del = 'deleted' === $r['type'] && ! empty( $meta['deleted_type'] ) && 'deleted' !== $meta['deleted_type'];
	if ( $del ) {
		$r['type'] = (string) $meta['deleted_type'];
		$r['body'] = (string) ( $meta['deleted_body'] ?? '' );
	}
	$out  = array(
		'id'      => (int) $r['id'],
		'chat'    => (int) $r['chat_id'],
		'wa'      => $r['wa_id'],
		'dir'     => $r['direction'],
		'type'    => $r['type'],
		'body'    => (string) $r['body'],
		'status'  => $r['status'],
		'error'   => (string) $r['error'],
		'user'    => flxc_user_name( $r['user_id'] ),
		'userId'  => (int) $r['user_id'],
		'source'  => $r['source'],
		'ts'      => (int) $r['ts'],
		'rev'     => (int) $r['rev'],
		'react'   => isset( $meta['reactions'] ) ? (array) $meta['reactions'] : new stdClass(),
		'edited'  => ! empty( $meta['edited'] ),
		'sender'  => (string) ( $meta['sender'] ?? '' ),
	);
	if ( $del || 'deleted' === $r['type'] ) {
		$out['deleted'] = true;
		$scope          = (string) ( $meta['deleted_scope'] ?? ( 'in' === $r['direction'] ? 'contact' : ( 'note' === $r['direction'] ? 'panel' : 'phone' ) ) );
		$at             = (int) ( $meta['deleted_at'] ?? 0 );
		$uloc           = static function () {
			return get_user_locale();
		}; // REST site dilinde çalışır; tarih panel kullanıcısının dilinde olsun.
		add_filter( 'flx_lang_locale', $uloc );
		$out['del']     = array(
			'scope'  => $scope,
			'by'     => ! empty( $meta['deleted_by'] ) ? flxc_user_name( (int) $meta['deleted_by'] ) : '',
			'at'     => $at * 1000,
			'atText' => $at ? flx_date( $at, 'datetime' ) : '',
			'ago'    => $at ? flx_time_ago( $at ) : '',
			'empty'  => ! $del, // 1.38.0 öncesi silinen not: içerik saklanmamış.
		);
		remove_filter( 'flx_lang_locale', $uloc );
	} else {
		$until = flxc_revoke_until( $r );
		if ( $until ) {
			$out['revokeUntil'] = $until;
		}
		$euntil = flxc_edit_until( $r );
		if ( $euntil ) {
			$out['editUntil'] = $euntil;
		}
	}
	if ( ! empty( $meta['lp'] ) ) {
		$out['lp'] = $meta['lp'];
	} elseif ( ! empty( $meta['nolp'] ) ) {
		$out['lp'] = false;
	}
	if ( ! empty( $meta['receipts'] ) ) {
		$out['receipts'] = array_values( (array) $meta['receipts'] );
	}
	if ( ! empty( $meta['rcheck']['verdict'] ) && 'skipped' !== $meta['rcheck']['verdict'] && function_exists( 'flxc_rcheck_summary' ) ) {
		$out['rcheck'] = array( 'v' => (string) $meta['rcheck']['verdict'], 's' => flxc_rcheck_summary( $meta['rcheck'] ) );
	}
	if ( $r['media_path'] || in_array( $r['type'], array( 'image', 'video', 'audio', 'document', 'sticker' ), true ) ) {
		$out['media'] = array(
			'url'   => flxc_media_url( $r['id'] ),
			'mime'  => $r['media_mime'],
			'name'  => $r['media_name'],
			'size'  => (int) $r['media_size'],
			'ready' => '' !== $r['media_path'],
			'ptt'   => ! empty( $meta['ptt'] ),
			'secs'  => (int) ( $meta['seconds'] ?? 0 ),
			'w'     => (int) ( $meta['w'] ?? 0 ),
			'h'     => (int) ( $meta['h'] ?? 0 ),
			'thumb' => (string) ( $meta['thumb'] ?? '' ),
		);
	}
	if ( 'location' === $r['type'] ) {
		$out['loc'] = array(
			'lat'  => (float) ( $meta['lat'] ?? 0 ),
			'lng'  => (float) ( $meta['lng'] ?? 0 ),
			'name' => (string) ( $meta['loc_name'] ?? '' ),
		);
	}
	if ( 'contact' === $r['type'] && ! empty( $meta['vcard_phone'] ) ) {
		$out['vcardPhone'] = (string) $meta['vcard_phone'];
	}
	if ( $q ) {
		$out['quoted'] = array(
			'id'   => (int) $q['id'],
			'dir'  => $q['direction'],
			'type' => $q['type'],
			'body' => flxc_preview_text( $q ),
			'user' => flxc_user_name( $q['user_id'] ),
		);
	}
	return $out;
}
