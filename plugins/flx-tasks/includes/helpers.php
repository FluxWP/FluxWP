<?php
defined( 'ABSPATH' ) || exit;

function flxg_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'flxg_' . $name;
}

function flxg_default_settings() {
	return array(
		'roles_use'       => array( 'administrator', 'editor', 'author' ),
		'roles_create'    => array( 'administrator', 'editor' ),
		'poll_interval'   => 45,
		'email_notify'    => 0,
		'max_upload_mb'   => 20,
		'accent'          => '', // boş = Flux Admin / tema vurgu rengi
		'ai_temperature'  => 0.4,
		'ai_instructions' => '',
		'ai_roles'        => array( 'administrator', 'editor', 'author' ),
		'mcp_enabled'     => 1,
		'delete_data'     => 0,
	);
}

function flxg_settings( $reset = false ) {
	static $cache = null;
	if ( null === $cache || $reset ) {
		$saved = get_option( 'flxg_settings', array() );
		$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), flxg_default_settings() );
	}
	return $cache;
}

function flxg_setting( $key ) {
	$s = flxg_settings();
	return $s[ $key ] ?? null;
}

function flxg_update_settings( array $values ) {
	$s = array_merge( flxg_settings(), $values );
	update_option( 'flxg_settings', $s, false );
	return flxg_settings( true );
}

/** UTC zaman damgası (veritabanı). */
function flxg_now() {
	return current_time( 'mysql', true );
}

/** Veritabanındaki UTC zamanı ISO 8601'e çevirir (arayüz yerel saate kendisi çevirir). */
function flxg_iso( $mysql ) {
	if ( ! $mysql || '0000-00-00 00:00:00' === $mysql ) {
		return null;
	}
	return str_replace( ' ', 'T', $mysql ) . 'Z';
}

function flxg_json( $value, $default = array() ) {
	if ( is_array( $value ) ) {
		return $value;
	}
	if ( ! is_string( $value ) || '' === $value ) {
		return $default;
	}
	$d = json_decode( $value, true );
	return is_array( $d ) ? $d : $default;
}

function flxg_enc( $value ) {
	return wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

function flxg_color( $c, $fallback = '#64748b' ) {
	$c = sanitize_hex_color( (string) $c );
	return $c ? $c : $fallback;
}

/**
 * Görev tarihi: site saatinde "YYYY-MM-DD" ya da "YYYY-MM-DDTHH:MM". Geçersizse boş.
 */
function flxg_clean_date( $v ) {
	$v = trim( (string) $v );
	if ( '' === $v ) {
		return '';
	}
	if ( preg_match( '/^(\d{4}-\d{2}-\d{2})(?:[T ](\d{2}:\d{2}))?/', $v, $m ) ) {
		return $m[1] . ( ! empty( $m[2] ) && '00:00' !== $m[2] ? 'T' . $m[2] : '' );
	}
	$ts = strtotime( $v );
	return $ts ? wp_date( 'Y-m-d', $ts ) : '';
}

/** Bugün (site saat diliminde). */
function flxg_today() {
	return wp_date( 'Y-m-d' );
}

function flxg_user_public( $user ) {
	if ( ! $user instanceof WP_User ) {
		$user = get_userdata( (int) $user );
	}
	if ( ! $user ) {
		return null;
	}
	return array(
		'id'       => (int) $user->ID,
		'name'     => $user->display_name ? $user->display_name : $user->user_login,
		'initials' => flxg_initials( $user->display_name ? $user->display_name : $user->user_login ),
		'avatar'   => get_avatar_url( $user->ID, array( 'size' => 64, 'default' => 'blank' ) ),
		'color'    => flxg_user_color( $user->ID ),
	);
}

function flxg_initials( $name ) {
	$parts = preg_split( '/\s+/u', trim( (string) $name ) );
	$out   = '';
	foreach ( array_slice( array_filter( $parts ), 0, 2 ) as $p ) {
		$out .= mb_strtoupper( mb_substr( $p, 0, 1 ) );
	}
	return '' !== $out ? $out : '?';
}

function flxg_user_color( $id ) {
	$palette = array( '#2e7d32', '#1565c0', '#6a1b9a', '#c62828', '#ef6c00', '#00838f', '#ad1457', '#4e342e', '#283593', '#558b2f' );
	return $palette[ absint( $id ) % count( $palette ) ];
}

/** Görev uygulamasını kullanabilen kullanıcılar. */
function flxg_users( $refresh = false ) {
	static $cache = null;
	if ( null !== $cache && ! $refresh ) {
		return $cache;
	}
	$roles = array_values( array_unique( array_merge( array( 'administrator' ), (array) flxg_setting( 'roles_use' ) ) ) );
	$users = get_users(
		array(
			'role__in' => $roles,
			'orderby'  => 'display_name',
			'number'   => 500,
		)
	);
	$cache = array();
	foreach ( $users as $u ) {
		$cache[ (int) $u->ID ] = flxg_user_public( $u );
	}
	return $cache;
}

function flxg_user_name( $id ) {
	$u = get_userdata( (int) $id );
	return $u ? ( $u->display_name ? $u->display_name : $u->user_login ) : __( 'Silinmiş kullanıcı', 'flx-tasks' );
}

function flxg_brand_name() {
	return (string) get_bloginfo( 'name' );
}

/** Uygulama adresi (isteğe bağlı görev açık). */
function flxg_app_url( $task_id = 0 ) {
	$url = admin_url( 'admin.php?page=flx-tasks' );
	return $task_id ? $url . '#/task/' . (int) $task_id : $url;
}

function flxg_priorities() {
	return array(
		1 => array( 'label' => __( 'Acil', 'flx-tasks' ), 'color' => '#dc2626' ),
		2 => array( 'label' => __( 'Yüksek', 'flx-tasks' ), 'color' => '#f59e0b' ),
		3 => array( 'label' => __( 'Normal', 'flx-tasks' ), 'color' => '#3b82f6' ),
		4 => array( 'label' => __( 'Düşük', 'flx-tasks' ), 'color' => '#94a3b8' ),
	);
}

function flxg_default_statuses() {
	return array(
		array( 'key' => 'todo', 'label' => __( 'Yapılacak', 'flx-tasks' ), 'color' => '#94a3b8', 'type' => 'open' ),
		array( 'key' => 'doing', 'label' => __( 'Devam ediyor', 'flx-tasks' ), 'color' => '#3b82f6', 'type' => 'open' ),
		array( 'key' => 'review', 'label' => __( 'İncelemede', 'flx-tasks' ), 'color' => '#a855f7', 'type' => 'open' ),
		array( 'key' => 'done', 'label' => __( 'Tamamlandı', 'flx-tasks' ), 'color' => '#16a34a', 'type' => 'done' ),
	);
}

function flxg_field_types() {
	return array(
		'text'     => __( 'Kısa metin', 'flx-tasks' ),
		'textarea' => __( 'Uzun metin', 'flx-tasks' ),
		'number'   => __( 'Sayı', 'flx-tasks' ),
		'money'    => __( 'Tutar', 'flx-tasks' ),
		'date'     => __( 'Tarih', 'flx-tasks' ),
		'checkbox' => __( 'Onay kutusu', 'flx-tasks' ),
		'dropdown' => __( 'Açılır liste', 'flx-tasks' ),
		'labels'   => __( 'Etiketler (çoklu seçim)', 'flx-tasks' ),
		'rating'   => __( 'Yıldız', 'flx-tasks' ),
		'people'   => __( 'Kişi', 'flx-tasks' ),
		'file'     => __( 'Dosya', 'flx-tasks' ),
		'url'      => __( 'Web adresi', 'flx-tasks' ),
		'email'    => __( 'E-posta', 'flx-tasks' ),
		'phone'    => __( 'Telefon', 'flx-tasks' ),
	);
}

function flxg_error( $code, $message, $status = 400 ) {
	return new WP_Error( $code, $message, array( 'status' => $status ) );
}
