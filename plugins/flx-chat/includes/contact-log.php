<?php
/**
 * 1.41.0: contact change log. Every change to what we know about a contact (name, notes, labels,
 * assignee, staff link, blocklist, assistant memory) is written with who made it (agent, assistant,
 * system, the contact), when, and the old and new value. Shown under "Change history" in Contact info.
 */
defined( 'ABSPATH' ) || exit;

/** Chat columns that are logged when they change. */
function flxc_clog_fields() {
	return array( 'name', 'notes', 'labels', 'assigned_to', 'phone' );
}

/**
 * Who makes the next change, when the caller does not say it. A logged-in user (panel, REST) is "staff";
 * webhooks, cron and the assistant run without a user and are "system" unless they say otherwise.
 */
function flxc_clog_default_by() {
	$uid = get_current_user_id();
	return array(
		'source'  => $uid ? 'staff' : 'system',
		'user_id' => $uid,
		'note'    => '',
	);
}

/**
 * Writes one entry. Notes typed in the panel are saved while typing: the same person editing the same
 * field again within 10 minutes updates the last entry (its old value stays) instead of adding a new one.
 *
 * @param int    $chat_id Chat.
 * @param string $field   name|notes|labels|assigned_to|phone|staff_user|blocked|memory.
 * @param mixed  $old     Old value.
 * @param mixed  $new     New value.
 * @param array  $by      source (staff|ai|system|contact), user_id, note.
 * @return int Entry id (0 when nothing changed).
 */
function flxc_clog_add( $chat_id, $field, $old, $new, array $by = array() ) {
	global $wpdb;
	$old = (string) $old;
	$new = (string) $new;
	if ( $old === $new || ! (int) $chat_id ) {
		return 0;
	}
	$by     = array_merge( flxc_clog_default_by(), $by );
	$source = in_array( $by['source'], array( 'staff', 'ai', 'system', 'contact' ), true ) ? $by['source'] : 'system';
	$uid    = (int) $by['user_id'];
	$note   = mb_substr( (string) $by['note'], 0, 250 );
	$t      = flxc_table( 'contact_log' );
	$now    = time();
	if ( in_array( $field, array( 'notes', 'name' ), true ) ) {
		$last = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE chat_id = %d ORDER BY id DESC LIMIT 1", (int) $chat_id ), ARRAY_A ); // phpcs:ignore
		if ( $last && $last['field'] === $field && $last['source'] === $source && (int) $last['user_id'] === $uid && (string) $last['note'] === $note && $now - (int) $last['created_at'] < 600 ) {
			if ( (string) $last['old_value'] === $new ) {
				$wpdb->delete( $t, array( 'id' => (int) $last['id'] ) ); // Back to where it was: nothing changed.
				return 0;
			}
			$wpdb->update( $t, array( 'new_value' => $new, 'created_at' => $now ), array( 'id' => (int) $last['id'] ) );
			return (int) $last['id'];
		}
	}
	$wpdb->insert(
		$t,
		array(
			'chat_id'    => (int) $chat_id,
			'field'      => mb_substr( (string) $field, 0, 40 ),
			'old_value'  => $old,
			'new_value'  => $new,
			'source'     => $source,
			'user_id'    => $uid,
			'note'       => $note,
			'created_at' => $now,
		)
	);
	$id = (int) $wpdb->insert_id;
	do_action( 'flxc_contact_changed', (int) $chat_id, $field, $old, $new, array_merge( $by, array( 'source' => $source ) ) );
	return $id;
}

/** Compares the logged columns of a chat before and after an update and writes what changed. */
function flxc_clog_diff( array $before, array $fields, array $by = array() ) {
	foreach ( flxc_clog_fields() as $f ) {
		if ( array_key_exists( $f, $fields ) && (string) ( $before[ $f ] ?? '' ) !== (string) $fields[ $f ] ) {
			flxc_clog_add( (int) $before['id'], $f, (string) ( $before[ $f ] ?? '' ), (string) $fields[ $f ], $by );
		}
	}
}

/** Entries of one chat, newest first. */
function flxc_clog_rows( $chat_id, $limit = 50 ) {
	global $wpdb;
	return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'contact_log' ) . ' WHERE chat_id = %d ORDER BY id DESC LIMIT %d', (int) $chat_id, max( 1, min( 500, (int) $limit ) ) ), ARRAY_A ); // phpcs:ignore
}

/** A stored value as people read it (label names, user names, yes/no). */
function flxc_clog_display( $field, $value ) {
	$value = (string) $value;
	if ( '' === $value ) {
		return '';
	}
	switch ( $field ) {
		case 'assigned_to':
		case 'staff_user':
			$n = flxc_user_name( (int) $value );
			return '' !== $n ? $n : ( (int) $value ? '#' . (int) $value : '' );
		case 'labels':
			$names = array();
			$all   = function_exists( 'flxc_labels_for_js' ) ? flxc_labels_for_js() : array();
			$map   = array();
			foreach ( $all as $l ) {
				$map[ $l['id'] ] = $l['name'];
			}
			foreach ( array_filter( explode( ',', $value ) ) as $id ) {
				$names[] = $map[ $id ] ?? $id;
			}
			return implode( ', ', $names );
		case 'blocked':
			return '1' === $value ? '1' : '0'; // The panel writes "Added to / removed from blocklist".
		case 'phone':
			return flxc_format_phone( $value );
	}
	return $value;
}

/** Entries for the Contact info panel. */
function flxc_clog_for_js( $chat_id, $limit = 50 ) {
	$out = array();
	foreach ( flxc_clog_rows( $chat_id, $limit ) as $r ) {
		$out[] = array(
			'id'     => (int) $r['id'],
			'field'  => (string) $r['field'],
			'old'    => flxc_clog_display( $r['field'], $r['old_value'] ),
			'new'    => flxc_clog_display( $r['field'], $r['new_value'] ),
			'source' => (string) $r['source'],
			'user'   => (int) $r['user_id'] ? flxc_user_name( (int) $r['user_id'] ) : '',
			'note'   => (string) $r['note'],
			'ts'     => (int) $r['created_at'] * 1000,
		);
	}
	return $out;
}

/* ---------- Contact name from records ---------- */

/**
 * Sets the chat name when it is empty (or when $force), and logs it. Used when a plugin, the assistant
 * or the backfill learns the person's name.
 *
 * @return bool Changed.
 */
function flxc_fill_chat_name( $chat, $name, array $by = array(), $force = false ) {
	$name = trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( (string) $name ) ) );
	$name = mb_substr( $name, 0, 190 );
	if ( ! $chat || '' === $name || flxc_is_group_jid( (string) $chat['jid'] ) ) {
		return false;
	}
	$cur = (string) $chat['name'];
	if ( $cur === $name || ( '' !== $cur && ! $force ) ) {
		return false;
	}
	flxc_update_chat( (int) $chat['id'], array( 'name' => $name ), $by );
	return true;
}

/**
 * 1.41.0 one-off backfill: chats that have no name but whose number is known from a donation, certificate
 * or CRM record get that name. Runs in small batches (Hostinger cuts PHP at ~60 s) until it is done.
 */
function flxc_clog_backfill_names() {
	if ( get_option( 'flxc_mig_names1410' ) ) {
		return;
	}
	$lock = 'flxc_mig_names_lock';
	if ( get_transient( $lock ) ) {
		return;
	}
	set_transient( $lock, 1, 60 );
	global $wpdb;
	$after = (int) get_option( 'flxc_mig_names_after', 0 );
	$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'chats' ) . " WHERE id > %d AND name = '' AND phone <> '' ORDER BY id ASC LIMIT 200", $after ), ARRAY_A ); // phpcs:ignore
	foreach ( (array) $rows as $r ) {
		$after = (int) $r['id'];
		if ( flxc_is_group_jid( (string) $r['jid'] ) ) {
			continue;
		}
		$name = flxc_lookup_contact_name( $r['phone'] );
		if ( '' !== $name ) {
			flxc_fill_chat_name( $r, $name, array( 'source' => 'system', 'user_id' => 0, 'note' => __( 'Filled from donation and certificate records (one-off update)', 'flx-chat' ) ) );
		}
	}
	if ( count( (array) $rows ) < 200 ) {
		update_option( 'flxc_mig_names1410', 1, false );
		delete_option( 'flxc_mig_names_after' );
	} else {
		update_option( 'flxc_mig_names_after', $after, false );
	}
	delete_transient( $lock );
}
add_action( 'admin_init', 'flxc_clog_backfill_names' );
add_action( 'flxc_jobs_tick', 'flxc_clog_backfill_names' );
