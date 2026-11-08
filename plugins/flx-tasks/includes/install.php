<?php
defined( 'ABSPATH' ) || exit;

function flxg_activate() {
	flxg_install_tables();
	flxg_install_defaults();
	flxg_files_dir();
	flxg_schedule();
}

function flxg_deactivate() {
	wp_clear_scheduled_hook( 'flxg_daily' );
}

function flxg_maybe_upgrade() {
	if ( get_option( 'flxg_db_version' ) !== FLXG_DB_VERSION ) {
		flxg_install_tables();
		flxg_install_defaults();
		flxg_files_dir();
	}
	flxg_schedule();
}

function flxg_schedule() {
	if ( ! wp_next_scheduled( 'flxg_daily' ) ) {
		// Her sabah 08:00 (site saati) civarı.
		$tz   = wp_timezone();
		$next = new DateTime( 'tomorrow 08:00', $tz );
		wp_schedule_event( $next->getTimestamp(), 'daily', 'flxg_daily' );
	}
}

function flxg_install_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c   = $wpdb->get_charset_collate();
	$sql = array();

	$sql[] = 'CREATE TABLE ' . flxg_table( 'spaces' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(190) NOT NULL DEFAULT '',
		description text,
		color varchar(9) NOT NULL DEFAULT '',
		icon varchar(32) NOT NULL DEFAULT '',
		owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
		access varchar(20) NOT NULL DEFAULT 'everyone',
		default_role varchar(20) NOT NULL DEFAULT 'edit',
		subtask_depth tinyint(3) unsigned NOT NULL DEFAULT 3,
		position double NOT NULL DEFAULT 0,
		archived tinyint(1) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxg_table( 'folders' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		space_id bigint(20) unsigned NOT NULL DEFAULT 0,
		name varchar(190) NOT NULL DEFAULT '',
		color varchar(9) NOT NULL DEFAULT '',
		position double NOT NULL DEFAULT 0,
		archived tinyint(1) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY space_id (space_id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxg_table( 'lists' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		space_id bigint(20) unsigned NOT NULL DEFAULT 0,
		folder_id bigint(20) unsigned NOT NULL DEFAULT 0,
		name varchar(190) NOT NULL DEFAULT '',
		description text,
		color varchar(9) NOT NULL DEFAULT '',
		owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
		access varchar(20) NOT NULL DEFAULT 'inherit',
		statuses longtext,
		kind varchar(12) NOT NULL DEFAULT 'task',
		position double NOT NULL DEFAULT 0,
		archived tinyint(1) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY space_id (space_id),
		KEY folder_id (folder_id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxg_table( 'members' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		object_type varchar(10) NOT NULL DEFAULT '',
		object_id bigint(20) unsigned NOT NULL DEFAULT 0,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		role varchar(20) NOT NULL DEFAULT 'edit',
		PRIMARY KEY  (id),
		KEY object (object_type,object_id),
		KEY user_id (user_id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxg_table( 'tasks' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		list_id bigint(20) unsigned NOT NULL DEFAULT 0,
		parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
		kind varchar(12) NOT NULL DEFAULT 'task',
		title varchar(500) NOT NULL DEFAULT '',
		description longtext,
		status varchar(40) NOT NULL DEFAULT '',
		priority tinyint(1) NOT NULL DEFAULT 0,
		start_date varchar(16) NOT NULL DEFAULT '',
		due_date varchar(16) NOT NULL DEFAULT '',
		cover_id bigint(20) unsigned NOT NULL DEFAULT 0,
		position double NOT NULL DEFAULT 0,
		creator_id bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		completed_at datetime DEFAULT NULL,
		archived tinyint(1) NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY list_id (list_id),
		KEY updated_at (updated_at),
		KEY due_date (due_date)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxg_table( 'assignees' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		task_id bigint(20) unsigned NOT NULL DEFAULT 0,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY task_id (task_id),
		KEY user_id (user_id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxg_table( 'watchers' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		task_id bigint(20) unsigned NOT NULL DEFAULT 0,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY task_id (task_id),
		KEY user_id (user_id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxg_table( 'tags' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		space_id bigint(20) unsigned NOT NULL DEFAULT 0,
		name varchar(80) NOT NULL DEFAULT '',
		color varchar(9) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY space_id (space_id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxg_table( 'task_tags' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		task_id bigint(20) unsigned NOT NULL DEFAULT 0,
		tag_id bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY task_id (task_id),
		KEY tag_id (tag_id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxg_table( 'fields' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		list_id bigint(20) unsigned NOT NULL DEFAULT 0,
		name varchar(190) NOT NULL DEFAULT '',
		type varchar(20) NOT NULL DEFAULT 'text',
		options longtext,
		position double NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY list_id (list_id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxg_table( 'values' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		task_id bigint(20) unsigned NOT NULL DEFAULT 0,
		field_id bigint(20) unsigned NOT NULL DEFAULT 0,
		value longtext,
		PRIMARY KEY  (id),
		KEY task_id (task_id),
		KEY field_id (field_id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxg_table( 'views' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		scope varchar(10) NOT NULL DEFAULT 'list',
		scope_id bigint(20) unsigned NOT NULL DEFAULT 0,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		name varchar(190) NOT NULL DEFAULT '',
		type varchar(20) NOT NULL DEFAULT 'list',
		config longtext,
		position double NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY scope (scope,scope_id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxg_table( 'comments' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		task_id bigint(20) unsigned NOT NULL DEFAULT 0,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		body longtext,
		created_at datetime NOT NULL,
		updated_at datetime DEFAULT NULL,
		PRIMARY KEY  (id),
		KEY task_id (task_id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxg_table( 'files' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		task_id bigint(20) unsigned NOT NULL DEFAULT 0,
		comment_id bigint(20) unsigned NOT NULL DEFAULT 0,
		field_id bigint(20) unsigned NOT NULL DEFAULT 0,
		doc_id bigint(20) unsigned NOT NULL DEFAULT 0,
		context varchar(10) NOT NULL DEFAULT 'pending',
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		name varchar(255) NOT NULL DEFAULT '',
		path varchar(255) NOT NULL DEFAULT '',
		thumb varchar(255) NOT NULL DEFAULT '',
		mime varchar(100) NOT NULL DEFAULT '',
		size bigint(20) unsigned NOT NULL DEFAULT 0,
		width int(11) NOT NULL DEFAULT 0,
		height int(11) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY task_id (task_id),
		KEY comment_id (comment_id),
		KEY doc_id (doc_id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxg_table( 'activity' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		task_id bigint(20) unsigned NOT NULL DEFAULT 0,
		list_id bigint(20) unsigned NOT NULL DEFAULT 0,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		action varchar(30) NOT NULL DEFAULT '',
		data longtext,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY task_id (task_id),
		KEY list_created (list_id,created_at)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxg_table( 'notifications' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
		task_id bigint(20) unsigned NOT NULL DEFAULT 0,
		type varchar(20) NOT NULL DEFAULT '',
		data longtext,
		read_at datetime DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY user_read (user_id,read_at)
	) $c;";

	// Dokümanlar: çok sayfalı, sayfalar blok blok saklanır (ortak düzenlemede blok bazında birleşir).
	$sql[] = 'CREATE TABLE ' . flxg_table( 'docs' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		space_id bigint(20) unsigned NOT NULL DEFAULT 0,
		folder_id bigint(20) unsigned NOT NULL DEFAULT 0,
		title varchar(190) NOT NULL DEFAULT '',
		icon varchar(16) NOT NULL DEFAULT '',
		owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
		position double NOT NULL DEFAULT 0,
		tree_rev int(11) unsigned NOT NULL DEFAULT 0,
		archived tinyint(1) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY space_id (space_id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxg_table( 'doc_pages' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		doc_id bigint(20) unsigned NOT NULL DEFAULT 0,
		parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
		title varchar(255) NOT NULL DEFAULT '',
		position double NOT NULL DEFAULT 0,
		created_by bigint(20) unsigned NOT NULL DEFAULT 0,
		updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY doc_id (doc_id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxg_table( 'doc_blocks' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		page_id bigint(20) unsigned NOT NULL DEFAULT 0,
		bid varchar(24) NOT NULL DEFAULT '',
		html longtext,
		position double NOT NULL DEFAULT 0,
		rev bigint(20) unsigned NOT NULL DEFAULT 0,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		deleted tinyint(1) NOT NULL DEFAULT 0,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY page_bid (page_id,bid),
		KEY page_rev (page_id,rev)
	) $c;";

	// Sıra numarası üretici: her blok değişikliği buradan artan, tekil bir sürüm alır.
	$sql[] = 'CREATE TABLE ' . flxg_table( 'doc_revs' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		page_id bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxg_table( 'doc_presence' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		page_id bigint(20) unsigned NOT NULL DEFAULT 0,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		cursor_data varchar(255) NOT NULL DEFAULT '',
		seen datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY page_user (page_id,user_id)
	) $c;";

	foreach ( $sql as $q ) {
		dbDelta( $q );
	}
	update_option( 'flxg_db_version', FLXG_DB_VERSION );
}

/**
 * İlk kurulum: herkesin ortak kullandığı "Örnek Vakfı" projesi ve içinde "İşler" listesi.
 * Adlar site dilinde yazılır, sonra panelden değiştirilebilir.
 */
function flxg_install_defaults() {
	if ( get_option( 'flxg_seeded' ) ) {
		return;
	}
	global $wpdb;
	$now   = flxg_now();
	$owner = get_current_user_id();
	if ( ! $owner ) {
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
		$owner  = $admins ? (int) $admins[0] : 0;
	}
	$exists = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . flxg_table( 'spaces' ) ); // phpcs:ignore
	if ( ! $exists ) {
		$wpdb->insert(
			flxg_table( 'spaces' ),
			array(
				'name'         => flxg_brand_name() ? flxg_brand_name() : 'Örnek Vakfı',
				'description'  => '',
				'color'        => '#2e7d32',
				'icon'         => 'leaf',
				'owner_id'     => $owner,
				'access'       => 'everyone',
				'default_role' => 'edit',
				'position'     => 1,
				'created_at'   => $now,
				'updated_at'   => $now,
			)
		);
		$space = (int) $wpdb->insert_id;
		$list  = flxg_create_list_row(
			array(
				'space_id' => $space,
				'name'     => __( 'Tasks', 'flx-tasks' ),
				'color'    => '#2e7d32',
				'owner_id' => $owner,
			)
		);
		if ( $list ) {
			flxg_create_task_row(
				array(
					'list_id'     => $list,
					'title'       => __( 'Welcome to Flux Tasks: open this task and write a comment', 'flx-tasks' ),
					'description' => '<p>' . esc_html__( 'Track tasks in list, board and calendar views. Add new lists or folders from the left menu, and set up custom fields and permissions in list settings.', 'flx-tasks' ) . '</p>',
					'status'      => 'todo',
					'priority'    => 3,
					'creator_id'  => $owner,
				)
			);
		}
	}
	update_option( 'flxg_seeded', 1, false );
}

function flxg_files_dir() {
	$up  = wp_upload_dir( null, false );
	$dir = trailingslashit( $up['basedir'] ) . 'flx-tasks';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		@file_put_contents( $dir . '/.htaccess', "# Flux Tasks: dosyalar yalnız yetkili REST isteğiyle sunulur.\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore
	}
	if ( ! file_exists( $dir . '/index.php' ) ) {
		@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
	}
	return $dir;
}
