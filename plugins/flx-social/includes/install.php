<?php
/** Tablolar, yetkiler, ayarlar. */

defined( 'ABSPATH' ) || exit;

const FLXSM_CAP_POST    = 'flxsm_post';    // gönderi hazırla (taslak, onaya gönder)
const FLXSM_CAP_PUBLISH = 'flxsm_publish'; // yayınla, zamanla, onayla, ağda düzenle/sil
const FLXSM_CAP_MANAGE  = 'flxsm_manage';  // hesap bağla, anahtarlar, ayarlar

function flxsm_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'flxsm_' . $name;
}

function flxsm_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$cs = $wpdb->get_charset_collate();
	$a  = flxsm_table( 'accounts' );
	$p  = flxsm_table( 'posts' );
	$t  = flxsm_table( 'targets' );
	$m  = flxsm_table( 'metrics' );
	$l  = flxsm_table( 'log' );
	dbDelta(
		"CREATE TABLE {$a} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		provider varchar(30) NOT NULL DEFAULT '',
		via varchar(30) NOT NULL DEFAULT '',
		ext_id varchar(191) NOT NULL DEFAULT '',
		name varchar(190) NOT NULL DEFAULT '',
		username varchar(190) NOT NULL DEFAULT '',
		avatar text NOT NULL,
		token longtext NOT NULL,
		expires int(10) unsigned NOT NULL DEFAULT 0,
		status varchar(12) NOT NULL DEFAULT 'active',
		meta longtext NOT NULL,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		sort int(10) NOT NULL DEFAULT 0,
		last_error text NOT NULL,
		created int(10) unsigned NOT NULL DEFAULT 0,
		updated int(10) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		UNIQUE KEY prov_ext (provider, ext_id),
		KEY status (status)
		) {$cs};
		CREATE TABLE {$p} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		status varchar(16) NOT NULL DEFAULT 'draft',
		title varchar(190) NOT NULL DEFAULT '',
		content longtext NOT NULL,
		media longtext NOT NULL,
		tags varchar(255) NOT NULL DEFAULT '',
		scheduled_at int(10) unsigned NOT NULL DEFAULT 0,
		published_at int(10) unsigned NOT NULL DEFAULT 0,
		repeat_days smallint(5) unsigned NOT NULL DEFAULT 0,
		source varchar(40) NOT NULL DEFAULT 'manual',
		note text NOT NULL,
		approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
		created int(10) unsigned NOT NULL DEFAULT 0,
		updated int(10) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY status_time (status, scheduled_at),
		KEY user_id (user_id)
		) {$cs};
		CREATE TABLE {$t} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		post_id bigint(20) unsigned NOT NULL DEFAULT 0,
		account_id bigint(20) unsigned NOT NULL DEFAULT 0,
		status varchar(16) NOT NULL DEFAULT 'draft',
		content longtext,
		media longtext,
		options longtext NOT NULL,
		remote_id varchar(191) NOT NULL DEFAULT '',
		remote_url text NOT NULL,
		state longtext NOT NULL,
		attempts smallint(5) unsigned NOT NULL DEFAULT 0,
		next_at int(10) unsigned NOT NULL DEFAULT 0,
		lock_until int(10) unsigned NOT NULL DEFAULT 0,
		error text NOT NULL,
		published_at int(10) unsigned NOT NULL DEFAULT 0,
		metrics longtext NOT NULL,
		metrics_at int(10) unsigned NOT NULL DEFAULT 0,
		updated int(10) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY post_id (post_id),
		KEY account_id (account_id),
		KEY status_next (status, next_at)
		) {$cs};
		CREATE TABLE {$m} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		account_id bigint(20) unsigned NOT NULL DEFAULT 0,
		day date NOT NULL,
		followers bigint(20) NOT NULL DEFAULT -1,
		data longtext NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY acc_day (account_id, day)
		) {$cs};
		CREATE TABLE {$l} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		ts int(10) unsigned NOT NULL DEFAULT 0,
		level varchar(8) NOT NULL DEFAULT 'info',
		account_id bigint(20) unsigned NOT NULL DEFAULT 0,
		target_id bigint(20) unsigned NOT NULL DEFAULT 0,
		msg text NOT NULL,
		PRIMARY KEY  (id),
		KEY ts (ts)
		) {$cs};"
	);
	$o = get_role( 'administrator' );
	if ( $o ) {
		$o->add_cap( FLXSM_CAP_POST );
		$o->add_cap( FLXSM_CAP_PUBLISH );
		$o->add_cap( FLXSM_CAP_MANAGE );
	}
	if ( ! get_option( 'flxsm_tick_key' ) ) {
		update_option( 'flxsm_tick_key', wp_generate_password( 32, false ), false );
	}
	if ( ! wp_next_scheduled( 'flxsm_tick' ) ) {
		wp_schedule_event( time() + 60, 'flxsm_minute', 'flxsm_tick' );
	}
	if ( ! wp_next_scheduled( 'flxsm_daily' ) ) {
		wp_schedule_event( time() + 300, 'daily', 'flxsm_daily' );
	}
	update_option( 'flxsm_db_version', FLXSM_DB_VERSION, false );
}

function flxsm_deactivate() {
	wp_clear_scheduled_hook( 'flxsm_tick' );
	wp_clear_scheduled_hook( 'flxsm_daily' );
}

function flxsm_maybe_upgrade() {
	if ( (int) get_option( 'flxsm_db_version', 0 ) < FLXSM_DB_VERSION ) {
		flxsm_install();
	}
}

add_filter(
	'cron_schedules',
	function ( $s ) {
		$s['flxsm_minute'] = array(
			'interval' => 60,
			'display'  => 'Flux Social (1 min)',
		);
		return $s;
	}
);

/* Flux Roles'e yetkileri bildir. */
add_filter(
	'flx_role_caps_supported',
	function ( $caps ) {
		$caps   = is_array( $caps ) ? $caps : array();
		$caps[] = FLXSM_CAP_POST;
		$caps[] = FLXSM_CAP_PUBLISH;
		$caps[] = FLXSM_CAP_MANAGE;
		return $caps;
	}
);
add_filter(
	'flxa_cap_labels',
	function ( $l ) {
		$l[ FLXSM_CAP_POST ]    = __( 'Sosyal medya: gönderi hazırla', 'flx-social' );
		$l[ FLXSM_CAP_PUBLISH ] = __( 'Sosyal medya: yayınla ve onayla', 'flx-social' );
		$l[ FLXSM_CAP_MANAGE ]  = __( 'Sosyal medya: hesaplar ve ayarlar', 'flx-social' );
		return $l;
	}
);

function flxsm_defaults() {
	return array(
		'approval'      => 1,        // yayın yetkisi olmayanın gönderisi onaya düşer
		'signature'     => '',       // her gönderinin sonuna eklenebilir imza
		'utm'           => 1,        // site bağlantılarına UTM ekle
		'meta_version'  => 'v25.0',
		'x_tier'        => 'free',
		'x_premium'     => 0,
		'x_month_limit' => 0,
		'tiktok_mode'   => 'inbox',
		'pin_sandbox'   => 0,
		'li_org'        => 1,
		'wa_conn'       => 0,
		'auto'          => array(
			'enabled'    => 0,
			'post_types' => array( 'post' ),
			'accounts'   => array(),
			'template'   => "{title}\n\n{excerpt}\n\n{link}",
			'mode'       => 'draft', // draft | schedule
			'delay'      => 10,      // dk
			'image'      => 1,
		),
		'apps'          => array(),  // sağlayıcı başına herkese açık alanlar (gizliler flxsm_secrets'ta)
	);
}

function flxsm_settings() {
	$s = wp_parse_args( (array) get_option( 'flxsm_settings', array() ), flxsm_defaults() );
	$s['auto'] = wp_parse_args( (array) $s['auto'], flxsm_defaults()['auto'] );
	return $s;
}

function flxsm_setting( $key, $def = null ) {
	$s = flxsm_settings();
	return array_key_exists( $key, $s ) ? $s[ $key ] : $def;
}

function flxsm_save_settings( array $patch ) {
	$s = array_merge( flxsm_settings(), $patch );
	update_option( 'flxsm_settings', $s, false );
	return $s;
}

function flxsm_can_post( $user_id = 0 ) {
	$u = $user_id ? $user_id : get_current_user_id();
	return user_can( $u, FLXSM_CAP_POST ) || user_can( $u, FLXSM_CAP_PUBLISH ) || user_can( $u, FLXSM_CAP_MANAGE );
}

function flxsm_can_publish( $user_id = 0 ) {
	$u = $user_id ? $user_id : get_current_user_id();
	return user_can( $u, FLXSM_CAP_PUBLISH ) || user_can( $u, FLXSM_CAP_MANAGE );
}

function flxsm_can_manage( $user_id = 0 ) {
	return user_can( $user_id ? $user_id : get_current_user_id(), FLXSM_CAP_MANAGE );
}
