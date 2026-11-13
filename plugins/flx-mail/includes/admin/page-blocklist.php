<?php
/**
 * Kara liste: tek sayfada iki tür kayıt.
 *  - Tam engel (Flux Chat kara listesi): hiçbir kanaldan mesaj gitmez.
 *  - Tanıtım engeli (IPTAL): yalnız o kanaldan tanıtım gitmez; bilgilendirme ve kişinin kendi başlattığı sohbet sürer.
 * Her satırda ne zaman, nasıl, kişinin yazdığı ve öncesinde ona giden son mesaj, kişi kartı ve sohbet linki.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/** Flux Chat kara listesi okunabiliyor mu (Flux Chat 1.7.0+). */
function flxi_chat_blocklist_ready() {
	global $wpdb;
	static $ok = null;
	if ( null === $ok ) {
		$ok = function_exists( 'flxc_table' ) && function_exists( 'flxc_block_remove' ) && flxc_table( 'blocklist' ) === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', flxc_table( 'blocklist' ) ) ); // phpcs:ignore
	}
	return $ok;
}

/**
 * Birleşik kara liste kayıtları (yeniden eskiye).
 *
 * @param array $f type (block|optout), channel, s
 * @return array[] type, id, phone, email, channel, time (unix), how, reason, said, log_id, chat_id, user_id
 */
function flxi_blocklist_entries( array $f = array() ) {
	global $wpdb;
	$type = $f['type'] ?? '';
	$ch   = $f['channel'] ?? '';
	$s    = trim( (string) ( $f['s'] ?? '' ) );
	$like = '' !== $s ? '%' . $wpdb->esc_like( preg_match( '/^[\d\s+()-]+$/', $s ) ? preg_replace( '/\D+/', '', $s ) : $s ) . '%' : '';
	$out  = array();

	if ( 'block' !== $type ) {
		$t   = flxi_table( 'optouts' );
		$sql = "SELECT * FROM $t WHERE 1=1";
		$prm = array();
		if ( $ch ) {
			$sql  .= ' AND channel = %s';
			$prm[] = $ch;
		}
		if ( $like ) {
			$sql  .= ' AND (recipient LIKE %s OR message LIKE %s OR note LIKE %s)';
			$prm   = array_merge( $prm, array( $like, $like, $like ) );
		}
		$sql .= ' ORDER BY id DESC LIMIT 2000';
		foreach ( (array) $wpdb->get_results( $prm ? $wpdb->prepare( $sql, $prm ) : $sql, ARRAY_A ) as $r ) { // phpcs:ignore
			$out[] = array(
				'type'    => 'optout',
				'id'      => (int) $r['id'],
				'phone'   => 'email' === $r['channel'] ? '' : $r['recipient'],
				'email'   => 'email' === $r['channel'] ? $r['recipient'] : '',
				'channel' => $r['channel'],
				'time'    => (int) strtotime( $r['created_at'] . ' UTC' ),
				'how'     => 'inbound' === $r['source'] ? 'reply' : ( 'admin' === $r['source'] ? 'staff' : $r['source'] ),
				'keyword' => (string) $r['keyword'],
				'reason'  => (string) ( $r['note'] ?? '' ),
				'said'    => (string) ( $r['message'] ?? '' ),
				'log_id'  => (int) ( $r['log_id'] ?? 0 ),
				'chat_id' => (int) ( $r['chat_id'] ?? 0 ),
				'user_id' => (int) ( $r['user_id'] ?? 0 ),
			);
		}
	}

	if ( 'optout' !== $type && ( ! $ch || 'whatsapp' === $ch || 'email' === $ch || 'sms' === $ch ) && flxi_chat_blocklist_ready() ) {
		$t   = flxc_table( 'blocklist' );
		$sql = "SELECT * FROM $t";
		$prm = array();
		if ( $like ) {
			$sql .= ' WHERE (phone LIKE %s OR email LIKE %s OR reason LIKE %s)';
			$prm  = array( $like, $like, $like );
		}
		$sql .= ' ORDER BY id DESC LIMIT 2000';
		foreach ( (array) $wpdb->get_results( $prm ? $wpdb->prepare( $sql, $prm ) : $sql, ARRAY_A ) as $r ) { // phpcs:ignore
			$out[] = array(
				'type'    => 'block',
				'id'      => (int) $r['id'],
				'phone'   => (string) $r['phone'],
				'email'   => (string) $r['email'],
				'channel' => '',
				'time'    => (int) $r['created_at'],
				'how'     => 'bot' === $r['source'] ? 'assistant' : 'staff',
				'keyword' => '',
				'reason'  => (string) $r['reason'],
				'said'    => '',
				'log_id'  => 0,
				'chat_id' => (int) $r['chat_id'],
				'user_id' => (int) $r['user_id'],
			);
		}
	}

	usort(
		$out,
		function ( $a, $b ) {
			return $b['time'] <=> $a['time'];
		}
	);
	return (array) apply_filters( 'flxi_blocklist_entries', $out, $f );
}

/**
 * Satırın gösterim bağlamı (yalnız sayfadaki satırlar için çağrılır): ad, kişi kartı, sohbet, kişinin yazdığı ve
 * öncesinde giden son mesaj.
 */
function flxi_blocklist_context( array $e ) {
	global $wpdb;
	$c = array( 'name' => '', 'person_url' => '', 'chat_url' => '', 'said' => $e['said'], 'sent' => null );

	// Kişi kartı: Flux CRM > ortak kişi kaydı.
	$who = array_filter( array( 'phone' => $e['phone'], 'email' => $e['email'] ) );
	if ( $who && function_exists( 'flxcrm_contact_for' ) ) {
		$cid = (int) flxcrm_contact_for( $who );
		if ( $cid ) {
			$c['person_url'] = function_exists( 'flxcrm_contact_url' ) ? flxcrm_contact_url( $cid ) : '';
			$p               = class_exists( 'FLXCRM_Contacts' ) && method_exists( 'FLXCRM_Contacts', 'get' ) ? (array) FLXCRM_Contacts::get( $cid ) : array();
			$c['name']       = (string) ( $p['name'] ?? '' );
		}
	}
	if ( '' === $c['name'] && $who && function_exists( 'flx_person_find' ) && function_exists( 'flx_people_ready' ) && flx_people_ready() ) {
		$p         = flx_person_get( (int) flx_person_find( $who ) );
		$c['name'] = (string) ( $p['name'] ?? '' );
	}

	// Sohbet: kayıttaki sohbet, yoksa telefondan.
	$chat = null;
	if ( function_exists( 'flxc_get_chat' ) ) {
		if ( $e['chat_id'] ) {
			$chat = flxc_get_chat( $e['chat_id'] );
		} elseif ( '' !== $e['phone'] && function_exists( 'flxc_table' ) ) {
			$chat = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'chats' ) . ' WHERE phone = %s ORDER BY id DESC LIMIT 1', $e['phone'] ), ARRAY_A ); // phpcs:ignore
		}
	}
	if ( $chat ) {
		$c['chat_url'] = add_query_arg( array( 'page' => 'flx-chat', 'chat' => (int) $chat['id'] ), admin_url( 'admin.php' ) );
		if ( '' === $c['name'] ) {
			$c['name'] = (string) ( '' !== (string) $chat['name'] ? $chat['name'] : ( $chat['push_name'] ?? '' ) );
		}
		// Tam engelde kişinin engelden hemen önce yazdığı mesaj (ör. "bana bir daha yazmayın").
		if ( '' === $c['said'] && $e['time'] ) {
			$c['said'] = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT body FROM ' . flxc_table( 'messages' ) . " WHERE chat_id = %d AND direction = 'in' AND type = 'text' AND ts <= %d ORDER BY ts DESC LIMIT 1", (int) $chat['id'], ( $e['time'] + 5 ) * 1000 ) ); // phpcs:ignore
		}
	}

	// Öncesinde giden son mesaj (Flux Mail kaydı).
	if ( $e['log_id'] ) {
		$c['sent'] = $wpdb->get_row( $wpdb->prepare( 'SELECT id, created_at, body, subject, campaign_id, source, template FROM ' . flxi_table( 'log' ) . ' WHERE id = %d', $e['log_id'] ), ARRAY_A ); // phpcs:ignore
	} else {
		$ch        = $e['channel'] ? $e['channel'] : ( '' !== $e['phone'] ? 'whatsapp' : 'email' );
		$to        = 'email' === $ch ? $e['email'] : $e['phone'];
		$c['sent'] = '' !== (string) $to ? flxi_last_sent_to( $ch, $to, gmdate( 'Y-m-d H:i:s', $e['time'] + 5 ) ) : null;
	}
	if ( $c['sent'] && $c['sent']['campaign_id'] ) {
		$c['sent']['campaign'] = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ' . flxi_table( 'campaigns' ) . ' WHERE id = %d', $c['sent']['campaign_id'] ) ); // phpcs:ignore
	}
	return $c;
}

/** "Nasıl" etiketi. */
function flxi_blocklist_how( array $e ) {
	if ( 'reply' === $e['how'] ) {
		/* translators: %s: kişinin yazdığı kelime (IPTAL) */
		return '' !== $e['keyword'] ? sprintf( __( 'Replied "%s"', 'flx-mail' ), mb_strtoupper( $e['keyword'] ) ) : __( 'Replied', 'flx-mail' );
	}
	if ( 'assistant' === $e['how'] ) {
		return __( 'AI assistant', 'flx-mail' );
	}
	if ( 'staff' === $e['how'] ) {
		$u = $e['user_id'] ? get_userdata( $e['user_id'] ) : null;
		/* translators: %s: kullanıcı adı */
		return $u ? sprintf( __( 'Added by %s', 'flx-mail' ), $u->display_name ) : __( 'Added by staff', 'flx-mail' );
	}
	return flxi_source_label( $e['how'] );
}

function flxi_page_blocklist() {
	// phpcs:disable WordPress.Security.NonceVerification
	$type  = sanitize_key( wp_unslash( $_GET['type'] ?? '' ) );
	$type  = in_array( $type, array( 'block', 'optout' ), true ) ? $type : '';
	$ch    = sanitize_key( wp_unslash( $_GET['channel'] ?? '' ) );
	$ch    = in_array( $ch, array( 'whatsapp', 'sms', 'email' ), true ) ? $ch : '';
	$s     = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
	$paged = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
	// phpcs:enable
	$lib  = function_exists( 'flxa_list_form_attrs' );
	$per  = function_exists( 'flxa_list_per_page' ) ? flxa_list_per_page( 'flxi-blocklist', 20 ) : 30;
	$chat = flxi_chat_blocklist_ready();

	flxi_page_head( __( 'Blocklist', 'flx-mail' ), __( 'People who asked not to be messaged. <strong>Blocked</strong>: nothing is sent on any channel. <strong>No promotions</strong>: they replied STOP, so only promotional messages stop on that channel; information messages and chats they start still go through.', 'flx-mail' ) );

	$all   = flxi_blocklist_entries( array( 'type' => $type, 'channel' => $ch, 's' => $s ) );
	$total = count( $all );
	$rows  = array_slice( $all, ( $paged - 1 ) * $per, $per );
	$chs   = flxi_channels();

	echo '<form method="get" class="flxi-filters"' . ( $lib ? flxa_list_form_attrs( array( 'key' => 'flxi-blocklist' ) ) : '' ) . '><input type="hidden" name="page" value="flxi-kara-liste">'; // phpcs:ignore
	echo '<input type="search" name="s" value="' . esc_attr( $s ) . '" placeholder="' . esc_attr__( 'Phone, email or reason', 'flx-mail' ) . '">';
	echo '<select name="type" aria-label="' . esc_attr__( 'Type', 'flx-mail' ) . '"><option value="">' . esc_html__( 'All types', 'flx-mail' ) . '</option>';
	foreach ( array( 'block' => __( 'Blocked', 'flx-mail' ), 'optout' => __( 'No promotions', 'flx-mail' ) ) as $k => $l ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $type, $k, false ), esc_html( $l ) );
	}
	echo '</select><select name="channel" aria-label="' . esc_attr__( 'Channel', 'flx-mail' ) . '"><option value="">' . esc_html__( 'All channels', 'flx-mail' ) . '</option>';
	foreach ( $chs as $k => $l ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $ch, $k, false ), esc_html( $l ) );
	}
	echo '</select><button class="button">' . esc_html__( 'Filter', 'flx-mail' ) . '</button>';
	if ( ! $lib && ( $type || $ch || $s ) ) {
		echo ' <a class="button-link" href="' . esc_url( flxi_admin_url( 'flxi-kara-liste' ) ) . '">' . esc_html__( 'Clear', 'flx-mail' ) . '</a>';
	}
	/* translators: %s: sayı */
	echo '<span class="flxi-muted flxi-filters__count">' . esc_html( sprintf( __( '%s people', 'flx-mail' ), number_format_i18n( $total ) ) ) . '</span>';
	echo '</form>';
	echo '<div class="flxi-bl-toolbar"><button type="button" class="button button-primary" data-flxi-toggle="#flxi-bl-add" aria-controls="flxi-bl-add">' . esc_html__( 'Add to the blocklist', 'flx-mail' ) . '</button></div>';

	// Elle ekleme.
	echo '<section class="flxi-card flxi-bl-add" id="flxi-bl-add" hidden><h2 class="flxi-card__title">' . esc_html__( 'Add to the blocklist', 'flx-mail' ) . '</h2>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxi-optout-add">';
	wp_nonce_field( 'flxi_blocklist_add' );
	echo '<input type="hidden" name="action" value="flxi_blocklist_add">';
	echo '<label class="flxi-field"><span>' . esc_html__( 'Phone', 'flx-mail' ) . '</span><input type="tel" name="recipient" placeholder="0532 123 45 67" required></label>';
	echo '<label class="flxi-field"><span>' . esc_html__( 'Type', 'flx-mail' ) . '</span><select name="kind">';
	echo '<option value="optout_whatsapp">' . esc_html__( 'No promotions · WhatsApp', 'flx-mail' ) . '</option><option value="optout_sms">' . esc_html__( 'No promotions · SMS', 'flx-mail' ) . '</option>';
	if ( $chat ) {
		echo '<option value="block">' . esc_html__( 'Blocked · all channels', 'flx-mail' ) . '</option>';
	}
	echo '</select></label>';
	echo '<label class="flxi-field"><span>' . esc_html__( 'Reason (optional)', 'flx-mail' ) . '</span><input type="text" name="note" maxlength="250" placeholder="' . esc_attr__( 'e.g. Asked on the phone', 'flx-mail' ) . '"></label>';
	echo '<button class="button button-primary">' . esc_html__( 'Add', 'flx-mail' ) . '</button></form></section>';

	echo '<div class="flxi-card flxi-card--flush"><div class="flxi-tablewrap"><table class="flxi-table flxi-table--bl"><thead><tr><th>' . esc_html__( 'Person', 'flx-mail' ) . '</th><th>' . esc_html__( 'Type', 'flx-mail' ) . '</th><th>' . esc_html__( 'When and how', 'flx-mail' ) . '</th><th>' . esc_html__( 'Details', 'flx-mail' ) . '</th><th></th></tr></thead><tbody>';
	if ( ! $rows ) {
		echo '<tr><td colspan="5" class="flxi-empty">' . esc_html( $type || $ch || $s ? __( 'No matching entries.', 'flx-mail' ) : __( 'Nobody is on the blocklist.', 'flx-mail' ) ) . '</td></tr>';
	}
	foreach ( $rows as $e ) {
		$c     = flxi_blocklist_context( $e );
		$label = '' !== $e['phone'] ? '+' . ltrim( $e['phone'], '+' ) : $e['email'];
		echo '<tr>';
		// Kişi.
		echo '<td class="flxi-bl-person">';
		$name = '' !== $c['name'] ? $c['name'] : $label;
		if ( $c['person_url'] ) {
			echo '<a class="flxi-bl-name" href="' . esc_url( $c['person_url'] ) . '">' . esc_html( $name ) . '</a>';
		} else {
			echo '<strong class="flxi-bl-name">' . esc_html( $name ) . '</strong>';
		}
		$sub = array();
		if ( '' !== $c['name'] ) {
			$sub[] = esc_html( $label );
		}
		if ( 'block' === $e['type'] && '' !== $e['phone'] && '' !== $e['email'] ) {
			$sub[] = esc_html( $e['email'] );
		}
		if ( $c['chat_url'] ) {
			$sub[] = '<a href="' . esc_url( $c['chat_url'] ) . '">' . esc_html__( 'Open chat', 'flx-mail' ) . '</a>';
		}
		if ( $c['person_url'] ) {
			$sub[] = '<a href="' . esc_url( $c['person_url'] ) . '">' . esc_html__( 'Contact card', 'flx-mail' ) . '</a>';
		}
		if ( $sub ) {
			echo '<div class="flxi-muted flxi-bl-sub">' . implode( ' · ', $sub ) . '</div>'; // phpcs:ignore
		}
		echo '</td>';
		// Tür.
		echo '<td>' . ( 'block' === $e['type'] ? flxi_badge( __( 'Blocked', 'flx-mail' ), 'red' ) . '<div class="flxi-muted">' . esc_html__( 'All channels', 'flx-mail' ) . '</div>' : flxi_badge( __( 'No promotions', 'flx-mail' ), 'amber' ) . '<div class="flxi-muted">' . esc_html( $chs[ $e['channel'] ] ?? $e['channel'] ) . '</div>' ) . '</td>'; // phpcs:ignore
		// Ne zaman, nasıl.
		echo '<td class="flxi-nowrap"><span title="' . esc_attr( flx_date( $e['time'], 'datetime' ) ) . '">' . esc_html( flx_date( $e['time'], 'datetime' ) ) . '</span><div class="flxi-muted">' . esc_html( flx_time_ago( $e['time'] ) ) . ' · ' . esc_html( flxi_blocklist_how( $e ) ) . '</div></td>';
		// Ayrıntı.
		echo '<td class="flxi-bl-detail">';
		if ( '' !== $e['reason'] ) {
			echo '<div><span class="flxi-bl-k">' . esc_html__( 'Reason', 'flx-mail' ) . '</span> ' . esc_html( $e['reason'] ) . '</div>';
		}
		if ( '' !== trim( (string) $c['said'] ) ) {
			echo '<div><span class="flxi-bl-k">' . esc_html__( 'They wrote', 'flx-mail' ) . '</span> <q>' . esc_html( mb_strimwidth( trim( (string) $c['said'] ), 0, 160, '…' ) ) . '</q></div>';
		}
		if ( $c['sent'] ) {
			$sent = $c['sent'];
			$what = trim( (string) ( $sent['subject'] ? $sent['subject'] : wp_strip_all_tags( (string) $sent['body'] ) ) );
			$what = preg_replace( '/(^|\s)[_*~]+|[_*~]+(?=\s|$)/u', '$1', $what ); // WhatsApp biçim işaretleri
			echo '<div><span class="flxi-bl-k">' . esc_html__( 'After', 'flx-mail' ) . '</span> <button type="button" class="button-link flxi-logrow" data-log="' . (int) $sent['id'] . '">' . esc_html( mb_strimwidth( preg_replace( '/\s+/u', ' ', $what ), 0, 110, '…' ) ) . '</button>';
			$meta = array( flx_date( strtotime( $sent['created_at'] . ' UTC' ), 'short' ) );
			if ( ! empty( $sent['campaign'] ) ) {
				$meta[] = '<a href="' . esc_url( flxi_admin_url( 'flxi-kayitlar', array( 'campaign' => (int) $sent['campaign_id'] ) ) ) . '">' . esc_html( $sent['campaign'] ) . '</a>';
			} elseif ( $sent['source'] ) {
				$meta[] = esc_html( flxi_source_label( $sent['source'] ) );
			}
			echo ' <span class="flxi-muted">(' . implode( ' · ', $meta ) . ')</span></div>'; // phpcs:ignore
		}
		if ( '' === $e['reason'] && '' === trim( (string) $c['said'] ) && ! $c['sent'] ) {
			echo '<span class="flxi-muted">—</span>';
		}
		echo '</td><td class="flxi-bl-act">';
		if ( 'block' === $e['type'] ) {
			echo flxi_post_button( 'flxi_blocklist_remove', array( 'id' => $e['id'] ), __( 'Remove', 'flx-mail' ), __( 'Messages will be sent to this person again on all channels. Remove only if they asked for it.', 'flx-mail' ) ); // phpcs:ignore
		} else {
			echo flxi_post_button( 'flxi_optout_remove', array( 'channel' => $e['channel'], 'recipient' => '' !== $e['phone'] ? $e['phone'] : $e['email'] ), __( 'Remove', 'flx-mail' ), __( 'Promotional messages will be sent to this person again. Remove only if they asked for it.', 'flx-mail' ) ); // phpcs:ignore
		}
		echo '</td></tr>';
	}
	echo '</tbody></table></div></div>';

	if ( function_exists( 'flxa_list_pagination' ) ) {
		flxa_list_pagination( $total, $per, $paged );
	} else {
		$pages = (int) ceil( $total / $per );
		if ( $pages > 1 ) {
			echo '<div class="flxi-pager">' . paginate_links( // phpcs:ignore
				array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => $paged,
					'total'   => $pages,
				)
			) . '</div>';
		}
	}
	if ( ! $chat ) {
		echo '<p class="flxi-muted">' . esc_html__( 'Full blocks are kept by Flux Chat. Install it to block people on all channels from here and from chats.', 'flx-mail' ) . '</p>';
	}
	echo '<p class="flxi-muted"><a href="' . esc_url( flxi_admin_url( 'flxi-ayarlar' ) . '#iptal' ) . '">' . esc_html__( 'STOP keywords and replies', 'flx-mail' ) . '</a></p>';

	echo '<dialog class="flxi-dialog" id="flxi-log-dialog" aria-labelledby="flxi-log-title"><div class="flxi-dialog__head"><h2 id="flxi-log-title"></h2><button type="button" class="button-link" data-close aria-label="' . esc_attr__( 'Close', 'flx-mail' ) . '"><span class="dashicons dashicons-no-alt"></span></button></div><dl class="flxi-dl" data-log-meta></dl><iframe title="' . esc_attr__( 'Content', 'flx-mail' ) . '" sandbox="allow-same-origin" data-log-frame></iframe></dialog>';
	flxi_page_foot();
}

add_action(
	'admin_post_flxi_blocklist_add',
	function () {
		flxi_check( 'flxi_blocklist_add' );
		$in   = wp_unslash( $_POST ); // phpcs:ignore
		$kind = sanitize_key( $in['kind'] ?? '' );
		$to   = sanitize_text_field( $in['recipient'] ?? '' );
		$note = sanitize_text_field( $in['note'] ?? '' );
		$back = flxi_admin_url( 'flxi-kara-liste' );
		if ( '' === flxi_optout_key( 'whatsapp', $to ) ) {
			flxi_redirect( $back, '', __( 'Enter a valid phone number.', 'flx-mail' ) );
		}
		if ( 'block' === $kind && flxi_chat_blocklist_ready() && function_exists( 'flxc_block_add' ) ) {
			$r = flxc_block_add( flxi_optout_key( 'whatsapp', $to ), '', $note, 'agent' );
			if ( is_wp_error( $r ) ) {
				flxi_redirect( $back, '', $r->get_error_message() );
			}
			flxi_redirect( $back, __( 'Blocked. Nothing will be sent to this person on any channel.', 'flx-mail' ) );
		}
		$ch = 'optout_sms' === $kind ? 'sms' : 'whatsapp';
		flxi_optout_add( $ch, $to, '', 'admin', array( 'note' => $note ) );
		flxi_redirect( $back, __( 'Added to the opt-out list.', 'flx-mail' ) );
	}
);

add_action(
	'admin_post_flxi_blocklist_remove',
	function () {
		flxi_check( 'flxi_blocklist_remove' );
		$id = (int) ( $_POST['id'] ?? 0 ); // phpcs:ignore
		if ( $id && flxi_chat_blocklist_ready() ) {
			flxc_block_remove( $id );
		}
		flxi_redirect( flxi_back( flxi_admin_url( 'flxi-kara-liste' ) ), __( 'Removed from the blocklist. Messages will be sent to this person again.', 'flx-mail' ) );
	}
);
