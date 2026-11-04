<?php
/**
 * Ayarlar, yetki, tablo kurulumu.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

/** Varsayılan ayarlar. */
function flxp_defaults() {
	return array(
		'record'        => 1,    // yavaş/hatalı istekleri kaydet
		'sample'        => 2,    // her isteğin % kaçı ayrıntılı (sorgu listesiyle) toplanır
		'slow_ms'       => 1500, // bu süreyi aşan istek "yavaş"
		'slow_query_ms' => 100,  // bu süreyi aşan sorgu "yavaş"
		'keep_days'     => 7,
		'max_rows'      => 5000,
		'panel'         => 1,    // yetkili kullanıcıya alt panel
		'panel_front'   => 1,    // ön yüzde de göster
		'caps'          => 1,    // panelde yetki kontrollerini topla
		'record_admins' => 0,    // panel açıkken yöneticinin kendi isteklerini de kaydet (yalnız eşik/hata varsa)
		'disk_quota_gb' => 0,    // paylaşımlı hosting disk kotası (GB); 0 = bilinmiyor, yüzde gösterilmez
		'login_slug'    => '',   // özel giriş adresi (örn. fluxadmin); boş = wp-login.php
		'login_blocked' => '404', // wp-login.php / misafir wp-admin: 404 ya da home
	);
}

/** Ayar(lar)ı okur. */
function flxp_settings( $key = null ) {
	static $cache = null;
	if ( null === $cache ) {
		$saved = get_option( 'flxp_settings', array() );
		$cache = array_merge( flxp_defaults(), is_array( $saved ) ? $saved : array() );
	}
	if ( null === $key ) {
		return $cache;
	}
	return isset( $cache[ $key ] ) ? $cache[ $key ] : null;
}

/** Formdan gelen ayarları temizler. */
function flxp_sanitize_settings( $in ) {
	$d   = flxp_defaults();
	$out = array();
	foreach ( array( 'record', 'panel', 'panel_front', 'caps', 'record_admins' ) as $k ) {
		$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
	}
	$out['sample']        = max( 0, min( 100, (int) ( $in['sample'] ?? $d['sample'] ) ) );
	$out['slow_ms']       = max( 100, min( 60000, (int) ( $in['slow_ms'] ?? $d['slow_ms'] ) ) );
	$out['slow_query_ms'] = max( 1, min( 10000, (int) ( $in['slow_query_ms'] ?? $d['slow_query_ms'] ) ) );
	$out['keep_days']     = max( 1, min( 90, (int) ( $in['keep_days'] ?? $d['keep_days'] ) ) );
	$out['max_rows']      = max( 100, min( 50000, (int) ( $in['max_rows'] ?? $d['max_rows'] ) ) );
	$out['login_blocked'] = in_array( $in['login_blocked'] ?? '', array( '404', 'home' ), true ) ? $in['login_blocked'] : $d['login_blocked'];
	$out['login_slug']    = (string) flxp_settings( 'login_slug' ); // admin.php save() doğrulayıp değiştirir
	$out['disk_quota_gb'] = max( 0, min( 100000, round( (float) str_replace( ',', '.', (string) ( $in['disk_quota_gb'] ?? 0 ) ), 1 ) ) );
	return $out;
}

/** Paneli ve kayıtları görebilir mi? */
function flxp_can_view( $user_id = null ) {
	if ( null === $user_id ) {
		return current_user_can( 'manage_options' ) || current_user_can( 'flxp_view' );
	}
	return user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'flxp_view' );
}

/** Kayıt tablosu adı. */
function flxp_table() {
	global $wpdb;
	return $wpdb->prefix . 'flxp_requests';
}

/** Tablo + yönetici yetkisi. */
function flxp_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t       = flxp_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created datetime NOT NULL,
			url varchar(500) NOT NULL DEFAULT '',
			method varchar(10) NOT NULL DEFAULT '',
			type varchar(10) NOT NULL DEFAULT '',
			status smallint(5) unsigned NOT NULL DEFAULT 0,
			ms int(10) unsigned NOT NULL DEFAULT 0,
			mem_kb int(10) unsigned NOT NULL DEFAULT 0,
			queries int(10) unsigned NOT NULL DEFAULT 0,
			query_ms int(10) unsigned NOT NULL DEFAULT 0,
			http int(10) unsigned NOT NULL DEFAULT 0,
			errors int(10) unsigned NOT NULL DEFAULT 0,
			flags varchar(100) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			data longtext NULL,
			PRIMARY KEY  (id),
			KEY created (created),
			KEY ms (ms)
		) {$charset};"
	);
	$role = get_role( 'administrator' );
	if ( $role && ! $role->has_cap( 'flxp_view' ) ) {
		$role->add_cap( 'flxp_view' );
	}
	update_option( 'flxp_db_version', FLXP_DB_VERSION, false );
}

/** Aktivasyon kancası güncellemede çalışmaz: şema sürümüne bakılır. */
function flxp_maybe_upgrade() {
	if ( (int) get_option( 'flxp_db_version', 0 ) < FLXP_DB_VERSION ) {
		flxp_install();
	}
}
