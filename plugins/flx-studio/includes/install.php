<?php
/** Tablolar, yetkiler, ayarlar. */

defined( 'ABSPATH' ) || exit;

const FLXST_CAP_USE    = 'flxst_use';
const FLXST_CAP_MANAGE = 'flxst_manage';

function flxst_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'flxst_' . $name;
}

function flxst_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$cs = $wpdb->get_charset_collate();
	$i  = flxst_table( 'items' );
	$f  = flxst_table( 'folders' );
	$p  = flxst_table( 'people' );
	$r  = flxst_table( 'refs' );
	$t  = flxst_table( 'templates' );
	dbDelta(
		"CREATE TABLE {$i} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		folder_id bigint(20) unsigned NOT NULL DEFAULT 0,
		kind varchar(10) NOT NULL DEFAULT 'image',
		state varchar(12) NOT NULL DEFAULT 'queued',
		job varchar(40) NOT NULL DEFAULT '',
		out_index smallint(5) unsigned NOT NULL DEFAULT 0,
		batch varchar(32) NOT NULL DEFAULT '',
		parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
		title varchar(190) NOT NULL DEFAULT '',
		prompt longtext NOT NULL,
		model varchar(80) NOT NULL DEFAULT '',
		provider varchar(30) NOT NULL DEFAULT '',
		params longtext NOT NULL,
		url text NOT NULL,
		path text NOT NULL,
		thumb text NOT NULL,
		mime varchar(60) NOT NULL DEFAULT '',
		width int(10) unsigned NOT NULL DEFAULT 0,
		height int(10) unsigned NOT NULL DEFAULT 0,
		bytes bigint(20) unsigned NOT NULL DEFAULT 0,
		cost decimal(10,4) NOT NULL DEFAULT 0,
		error text NOT NULL,
		favorite tinyint(1) NOT NULL DEFAULT 0,
		attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
		created int(10) unsigned NOT NULL DEFAULT 0,
		updated int(10) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY user_folder (user_id, folder_id),
		KEY job (job),
		KEY state (state),
		KEY batch (batch)
		) {$cs};
		CREATE TABLE {$f} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
		name varchar(120) NOT NULL DEFAULT '',
		shared tinyint(1) NOT NULL DEFAULT 0,
		created int(10) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY user_id (user_id)
		) {$cs};
		CREATE TABLE {$p} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		name varchar(120) NOT NULL DEFAULT '',
		shared tinyint(1) NOT NULL DEFAULT 0,
		created int(10) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY user_id (user_id)
		) {$cs};
		CREATE TABLE {$r} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		person_id bigint(20) unsigned NOT NULL DEFAULT 0,
		name varchar(190) NOT NULL DEFAULT '',
		role varchar(20) NOT NULL DEFAULT '',
		url text NOT NULL,
		path text NOT NULL,
		thumb text NOT NULL,
		mime varchar(60) NOT NULL DEFAULT '',
		width int(10) unsigned NOT NULL DEFAULT 0,
		height int(10) unsigned NOT NULL DEFAULT 0,
		saved tinyint(1) NOT NULL DEFAULT 0,
		created int(10) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY user_id (user_id),
		KEY person_id (person_id)
		) {$cs};
		CREATE TABLE {$t} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		name varchar(120) NOT NULL DEFAULT '',
		prompt longtext NOT NULL,
		params longtext NOT NULL,
		shared tinyint(1) NOT NULL DEFAULT 0,
		created int(10) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY user_id (user_id)
		) {$cs};"
	);
	foreach ( array( 'administrator' ) as $role ) {
		$o = get_role( $role );
		if ( $o ) {
			$o->add_cap( FLXST_CAP_USE );
			$o->add_cap( FLXST_CAP_MANAGE );
		}
	}
	update_option( 'flxst_db_version', FLXST_DB_VERSION, false );
}

function flxst_maybe_upgrade() {
	if ( (int) get_option( 'flxst_db_version', 0 ) < FLXST_DB_VERSION ) {
		flxst_install();
	}
}

/* Flux Roles'e yetkileri bildir (Roller ve erişim sayfasında açılıp kapatılır). */
add_filter(
	'flx_role_caps_supported',
	function ( $caps ) {
		$caps   = is_array( $caps ) ? $caps : array();
		$caps[] = FLXST_CAP_USE;
		$caps[] = FLXST_CAP_MANAGE;
		return $caps;
	}
);
add_filter(
	'flxa_cap_labels',
	function ( $l ) {
		$l[ FLXST_CAP_USE ]    = __( 'Stüdyo: görsel/video üret', 'flx-studio' );
		$l[ FLXST_CAP_MANAGE ] = __( 'Stüdyo: tüm galeriler ve ayarlar', 'flx-studio' );
		return $l;
	}
);

function flxst_defaults() {
	return array(
		'image_model'   => '',
		'video_model'   => '',
		'text_model'    => '',
		'max_n'         => 4,
		'max_batch'     => 20,
		'shared_people' => 1,
	);
}

function flxst_settings() {
	return wp_parse_args( (array) get_option( 'flxst_settings', array() ), flxst_defaults() );
}

function flxst_can_use( $user_id = 0 ) {
	return $user_id ? user_can( $user_id, FLXST_CAP_USE ) || user_can( $user_id, FLXST_CAP_MANAGE ) : ( current_user_can( FLXST_CAP_USE ) || current_user_can( FLXST_CAP_MANAGE ) );
}

function flxst_can_manage() {
	return current_user_can( FLXST_CAP_MANAGE );
}
