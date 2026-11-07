<?php
/**
 * Tablolar, yetki ve günlük temizlik.
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

function flxcb_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'flxcb_' . $name;
}

function flxcb_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();

	dbDelta(
		'CREATE TABLE ' . flxcb_table( 'conversations' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		token char(64) NOT NULL DEFAULT '',
		status varchar(12) NOT NULL DEFAULT 'bot',
		name varchar(190) NOT NULL DEFAULT '',
		email varchar(190) NOT NULL DEFAULT '',
		phone varchar(40) NOT NULL DEFAULT '',
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		assigned_to bigint(20) unsigned NOT NULL DEFAULT 0,
		flxc_chat bigint(20) unsigned NOT NULL DEFAULT 0,
		page_url varchar(500) NOT NULL DEFAULT '',
		page_title varchar(190) NOT NULL DEFAULT '',
		locale varchar(12) NOT NULL DEFAULT '',
		rating tinyint(1) NOT NULL DEFAULT 0,
		rating_note text,
		ai_turns int(11) NOT NULL DEFAULT 0,
		unread int(11) NOT NULL DEFAULT 0,
		lead tinyint(1) NOT NULL DEFAULT 0,
		order_id bigint(20) unsigned NOT NULL DEFAULT 0,
		seen_ts bigint(20) NOT NULL DEFAULT 0,
		agent_read bigint(20) unsigned NOT NULL DEFAULT 0,
		visitor_read bigint(20) unsigned NOT NULL DEFAULT 0,
		last_ts bigint(20) NOT NULL DEFAULT 0,
		queued_ts bigint(20) NOT NULL DEFAULT 0,
		mailed_ts bigint(20) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		meta longtext,
		PRIMARY KEY  (id),
		UNIQUE KEY token (token),
		KEY status (status),
		KEY last_ts (last_ts),
		KEY flxc_chat (flxc_chat)
	) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . flxcb_table( 'messages' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		conv_id bigint(20) unsigned NOT NULL DEFAULT 0,
		role varchar(10) NOT NULL DEFAULT 'visitor',
		type varchar(10) NOT NULL DEFAULT 'text',
		body longtext,
		file_path varchar(255) NOT NULL DEFAULT '',
		file_name varchar(190) NOT NULL DEFAULT '',
		file_mime varchar(100) NOT NULL DEFAULT '',
		file_size bigint(20) NOT NULL DEFAULT 0,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		ext_id bigint(20) unsigned NOT NULL DEFAULT 0,
		ts bigint(20) NOT NULL DEFAULT 0,
		meta longtext,
		PRIMARY KEY  (id),
		KEY conv_id (conv_id,id),
		KEY ext_id (ext_id)
	) $c;"
	);

	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( 'flxcb_reply' );
	}
	if ( false === get_option( 'flxcb_settings', false ) ) {
		add_option( 'flxcb_settings', array(), '', false );
	}
	update_option( 'flxcb_db_version', FLXCB_DB_VERSION, false );
	flxcb_upload_dir();
}

/* Sürüm değişince tabloları güncelle (etkinleştirme kancası çalışmadan güncellenen kurulumlar için). */
add_action(
	'plugins_loaded',
	function () {
		if ( FLXCB_DB_VERSION !== get_option( 'flxcb_db_version' ) ) {
			flxcb_install();
		}
		if ( ! wp_next_scheduled( 'flxcb_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'flxcb_cleanup' );
		}
	},
	5
);

/** Saklama süresi dolan sohbetleri ve dosyalarını siler. */
add_action(
	'flxcb_cleanup',
	function () {
		global $wpdb;
		$days = (int) flxcb_setting( 'retention_days' );
		if ( $days <= 0 ) {
			return;
		}
		$before = ( time() - $days * DAY_IN_SECONDS ) * 1000;
		$ids    = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxcb_table( 'conversations' ) . ' WHERE last_ts > 0 AND last_ts < %d LIMIT 500', $before ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $ids as $id ) {
			flxcb_delete_conversation( (int) $id );
		}
	}
);
