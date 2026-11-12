<?php
/**
 * Tablolar: flxai_log (her model ve araç çağrısı), flxai_chats (ortak sohbetler), flxai_media (görsel/video işleri),
 * flxai_tasks (zamanlanmış görevler), flxai_skills (skill'ler, 0.12.0).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

function flxai_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'flxai_' . $name;
}

function flxai_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$cs  = $wpdb->get_charset_collate();
	$log = flxai_table( 'log' );
	$cht = flxai_table( 'chats' );
	$bdy = flxai_table( 'log_body' );
	$med = flxai_table( 'media' );
	$tsk = flxai_table( 'tasks' );
	$skl = flxai_table( 'skills' );
	dbDelta(
		"CREATE TABLE {$skl} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(60) NOT NULL DEFAULT '',
			name varchar(190) NOT NULL DEFAULT '',
			description varchar(500) NOT NULL DEFAULT '',
			body longtext NOT NULL,
			tools text NOT NULL,
			example text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			version int(10) unsigned NOT NULL DEFAULT 1,
			created int(10) unsigned NOT NULL DEFAULT 0,
			updated int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$cs};"
	);
	dbDelta(
		"CREATE TABLE {$tsk} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(190) NOT NULL DEFAULT '',
			prompt text NOT NULL,
			schedule varchar(500) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			next_run int(10) unsigned NOT NULL DEFAULT 0,
			last_run int(10) unsigned NOT NULL DEFAULT 0,
			last_state varchar(20) NOT NULL DEFAULT '',
			last_error varchar(255) NOT NULL DEFAULT '',
			chat_id bigint(20) unsigned NOT NULL DEFAULT 0,
			runs int(10) unsigned NOT NULL DEFAULT 0,
			bot varchar(60) NOT NULL DEFAULT '',
			model varchar(120) NOT NULL DEFAULT '',
			created int(10) unsigned NOT NULL DEFAULT 0,
			updated int(10) unsigned NOT NULL DEFAULT 0,
			meta longtext NOT NULL,
			PRIMARY KEY  (id),
			KEY status_next (status,next_run),
			KEY user_id (user_id)
		) {$cs};"
	);
	dbDelta(
		"CREATE TABLE {$bdy} (
			log_id bigint(20) unsigned NOT NULL DEFAULT 0,
			ts int(10) unsigned NOT NULL DEFAULT 0,
			req mediumtext NOT NULL,
			res mediumtext NOT NULL,
			PRIMARY KEY  (log_id),
			KEY ts (ts)
		) {$cs};"
	);
	dbDelta(
		"CREATE TABLE {$log} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ts int(10) unsigned NOT NULL DEFAULT 0,
			kind varchar(10) NOT NULL DEFAULT 'model',
			source varchar(60) NOT NULL DEFAULT '',
			purpose varchar(60) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			chat_id bigint(20) unsigned NOT NULL DEFAULT 0,
			provider varchar(20) NOT NULL DEFAULT '',
			model varchar(120) NOT NULL DEFAULT '',
			tool varchar(80) NOT NULL DEFAULT '',
			in_tok int(10) unsigned NOT NULL DEFAULT 0,
			out_tok int(10) unsigned NOT NULL DEFAULT 0,
			cache_read int(10) unsigned NOT NULL DEFAULT 0,
			cache_write int(10) unsigned NOT NULL DEFAULT 0,
			cost decimal(14,8) NOT NULL DEFAULT 0,
			full_cost decimal(14,8) NOT NULL DEFAULT 0,
			est tinyint(1) NOT NULL DEFAULT 0,
			ms int(10) unsigned NOT NULL DEFAULT 0,
			ok tinyint(1) NOT NULL DEFAULT 1,
			error varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY ts (ts),
			KEY source_ts (source,ts),
			KEY kind_ts (kind,ts)
		) {$cs};"
	);
	dbDelta(
		"CREATE TABLE {$cht} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(190) NOT NULL DEFAULT '',
			archived tinyint(1) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'idle',
			model varchar(120) NOT NULL DEFAULT '',
			created int(10) unsigned NOT NULL DEFAULT 0,
			updated int(10) unsigned NOT NULL DEFAULT 0,
			messages longtext NOT NULL,
			state longtext NOT NULL,
			PRIMARY KEY  (id),
			KEY user_upd (user_id,archived,updated)
		) {$cs};"
	);
	dbDelta(
		"CREATE TABLE {$med} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job varchar(32) NOT NULL DEFAULT '',
			ts int(10) unsigned NOT NULL DEFAULT 0,
			updated int(10) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source varchar(60) NOT NULL DEFAULT '',
			purpose varchar(60) NOT NULL DEFAULT '',
			kind varchar(10) NOT NULL DEFAULT 'image',
			model varchar(80) NOT NULL DEFAULT '',
			provider varchar(20) NOT NULL DEFAULT '',
			state varchar(12) NOT NULL DEFAULT 'queued',
			progress decimal(5,4) NOT NULL DEFAULT 0,
			req longtext NOT NULL,
			outputs longtext NOT NULL,
			meta longtext NOT NULL,
			cost decimal(14,8) NOT NULL DEFAULT 0,
			est tinyint(1) NOT NULL DEFAULT 1,
			log_id bigint(20) unsigned NOT NULL DEFAULT 0,
			error varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY job (job),
			KEY source_ts (source,ts),
			KEY user_ts (user_id,ts)
		) {$cs};"
	);
	$role = get_role( 'administrator' );
	if ( $role ) {
		$role->add_cap( 'flxai_chat' );
	}
	flxai_grant_mcp_cap();
	update_option( 'flxai_db_version', FLXAI_DB_VERSION, true );
}

/**
 * MCP yetkisi (flxai_mcp). Yönetici her kurulumda alır. edit_posts olan roller bir kez (şema 4) alır;
 * canlı kural user_has_cap filtresindedir, bu yüzden sonradan açılan edit_posts rolleri de bağlanır.
 */
function flxai_grant_mcp_cap() {
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( FLXAI_CAP_MCP );
	}
	$prev = get_option( 'flxai_db_version' );
	if ( false !== $prev && (int) $prev >= 4 ) {
		return;
	}
	if ( ! function_exists( 'wp_roles' ) ) {
		return;
	}
	foreach ( wp_roles()->roles as $key => $role ) {
		if ( 'administrator' === $key || empty( $role['capabilities']['edit_posts'] ) ) {
			continue;
		}
		$obj = get_role( $key );
		if ( $obj ) {
			$obj->add_cap( FLXAI_CAP_MCP );
		}
	}
}

/* edit_posts olan herkes ve WordPress yöneticisi MCP kullanır. Flux Roles bu yetkiyi edit_posts olmayan role de verebilir. */
add_filter(
	'user_has_cap',
	function ( $all, $caps, $args, $user ) {
		if ( ! in_array( FLXAI_CAP_MCP, (array) $caps, true ) || ! empty( $all[ FLXAI_CAP_MCP ] ) ) {
			return $all;
		}
		$roles = $user instanceof WP_User ? (array) $user->roles : array();
		if ( in_array( 'administrator', $roles, true ) || ! empty( $all['manage_options'] ) || ! empty( $all['edit_posts'] ) ) {
			$all[ FLXAI_CAP_MCP ] = true;
		}
		return $all;
	},
	10,
	4
);

/* Flux Roles kataloğu: yetki Roller ve erişim tablosunda görünür ve herhangi bir role verilebilir. */
add_filter(
	'flxr_catalog',
	function ( $groups ) {
		$groups['ai'] = array(
			'label'  => __( 'AI & MCP', 'flx-ai' ),
			'plugin' => 'Flux AI & MCP',
			'active' => true,
			'caps'   => array(
				FLXAI_CAP_MCP => array(
					'label' => __( 'Connects assistants over MCP (only tools their own permissions allow)', 'flx-ai' ),
					'new'   => true,
				),
			),
		);
		return $groups;
	}
);
add_filter(
	'flx_role_caps_supported',
	function ( $caps ) {
		$caps   = is_array( $caps ) ? $caps : array();
		$caps[] = FLXAI_CAP_MCP;
		return $caps;
	}
);

/** Aktivasyon güncellemede çalışmaz; şema sürümü değişince kurulum burada yapılır. */
function flxai_maybe_upgrade() {
	if ( FLXAI_DB_VERSION !== get_option( 'flxai_db_version' ) ) {
		flxai_install();
	}
}

/**
 * Kayıt saklama süresi (gün). 0 = sınırsız. Boş/0 dışındaki değerler 1..3650 aralığına çekilir.
 *
 * @param string $key log_days | log_body_days
 * @return int
 */
function flxai_log_days( $key ) {
	$d = (int) flxai_setting( $key, 0 );
	return $d > 0 ? min( 3650, $d ) : 0;
}

/** Eski kayıtlar günde bir kez silinir (cron'a bağlı değil). */
add_action(
	'admin_init',
	function () {
		if ( get_transient( 'flxai_prune' ) ) {
			return;
		}
		set_transient( 'flxai_prune', 1, DAY_IN_SECONDS );
		global $wpdb;
		// 0 = sınırsız (varsayılan): kayıt silinmez.
		$days = flxai_log_days( 'log_days' );
		if ( $days ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . flxai_table( 'log' ) . ' WHERE ts < %d', time() - $days * DAY_IN_SECONDS ) ); // phpcs:ignore
		}
		$bdays = flxai_log_days( 'log_body_days' );
		if ( $bdays ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . flxai_table( 'log_body' ) . ' WHERE ts < %d', time() - $bdays * DAY_IN_SECONDS ) ); // phpcs:ignore
		}
		// Görsel/video iş kayıtları; üretilen dosyalar uploads/flxai-media'da kalır.
		$mdays = max( 7, (int) flxai_setting( 'media_days', 90 ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . flxai_table( 'media' ) . ' WHERE ts < %d', time() - $mdays * DAY_IN_SECONDS ) ); // phpcs:ignore
	}
);
