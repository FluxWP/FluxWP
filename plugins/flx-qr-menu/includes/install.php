<?php
/**
 * Kurulum: geçmiş tablosu, yetki, yazı türü.
 *
 * @package FLX_QR_Menu
 */

defined( 'ABSPATH' ) || exit;

function flxq_history_table() {
	global $wpdb;
	return $wpdb->prefix . 'flxq_history';
}

/** Etkinleştirmede ve DB sürümü değişince çalışır (güncellemede etkinleştirme kancası çalışmaz). */
function flxq_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = flxq_history_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  menu_id bigint(20) unsigned NOT NULL DEFAULT 0,
  version int(10) unsigned NOT NULL DEFAULT 0,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_gmt datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  action varchar(20) NOT NULL DEFAULT '',
  summary text NULL,
  data longtext NULL,
  PRIMARY KEY  (id),
  KEY menu_id (menu_id,id)
) {$charset};"
	);

	$role = get_role( 'administrator' );
	if ( $role && ! $role->has_cap( FLXQ_CAP ) ) {
		$role->add_cap( FLXQ_CAP );
	}
	// Menü yöneticisi: yalnız kendi açtığı ya da kendisine atanan menüleri düzenler. Menü açma ve adres değiştirme kullanıcı başına verilir.
	$caps = array( 'read' => true, 'upload_files' => true, FLXQ_EDIT_CAP => true );
	$mgr  = get_role( FLXQ_ROLE );
	if ( ! $mgr ) {
		add_role( FLXQ_ROLE, 'Menü yöneticisi', $caps );
	} else {
		foreach ( $caps as $c => $on ) {
			if ( ! $mgr->has_cap( $c ) ) {
				$mgr->add_cap( $c );
			}
		}
	}
	if ( false === get_option( 'flxq_settings' ) ) {
		add_option( 'flxq_settings', array( 'delete_on_uninstall' => false ), '', false );
	}
	if ( false === get_option( 'flxq_keys' ) ) {
		add_option( 'flxq_keys', array(), '', true );
	}
	if ( false === get_option( 'flxq_routes' ) ) {
		add_option( 'flxq_routes', array( 'slugs' => array(), 'hosts' => array() ), '', true );
	}
	update_option( 'flxq_db_version', FLXQ_DB_VERSION, true );
}

function flxq_maybe_upgrade() {
	if ( (int) get_option( 'flxq_db_version', 0 ) < FLXQ_DB_VERSION ) {
		flxq_install();
	}
}

function flxq_register_post_type() {
	register_post_type(
		'flxq_menu',
		array(
			'labels'              => array(
				'name'          => __( 'QR Menüler', 'flx-qr-menu' ),
				'singular_name' => __( 'QR Menü', 'flx-qr-menu' ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_rest'        => false,
			'show_in_nav_menus'   => false,
			'rewrite'             => false,
			'query_var'           => false,
			'can_export'          => true,
			'supports'            => array( 'title' ),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		)
	);
}

/*
 * Yönetici rolüne yetki eklenmemiş olsa bile (çoklu site, rol eklentisi) manage_options sahibi menüleri yönetebilir.
 * Editör gibi rollere yalnız flxq_manage_menus yetkisi verilerek erişim açılabilir.
 */
add_filter(
	'user_has_cap',
	function ( $allcaps ) {
		if ( ! empty( $allcaps['manage_options'] ) ) {
			$allcaps[ FLXQ_CAP ] = true;
		}
		// Tüm menüleri yöneten, menü yöneticisinin her yetkisine de sahiptir.
		if ( ! empty( $allcaps[ FLXQ_CAP ] ) ) {
			$allcaps[ FLXQ_EDIT_CAP ]      = true;
			$allcaps['flxq_create_menus'] = true;
			$allcaps['flxq_set_address']  = true;
		}
		return $allcaps;
	}
);

/** Rol adı yönetim ekranlarında kullanıcının dilinde görünsün. */
add_filter(
	'gettext_with_context',
	function ( $translation, $text, $context, $domain ) {
		if ( 'User role' === $context && 'Menü yöneticisi' === $text && 'default' === $domain ) {
			return __( 'Menü yöneticisi', 'flx-qr-menu' );
		}
		return $translation;
	},
	10,
	4
);

/**
 * Eklenti ayarları.
 * front_mode: blank (temanın ana sayfası) | redirect (seçili menüye 302) | menu (seçili menü ana sayfada açılır).
 */
function flxq_settings() {
	$s = get_option( 'flxq_settings', array() );
	$s = wp_parse_args(
		is_array( $s ) ? $s : array(),
		array(
			'delete_on_uninstall' => false,
			'front_mode'          => 'blank',
			'front_menu'          => 0,
		)
	);
	if ( ! in_array( $s['front_mode'], array( 'blank', 'redirect', 'menu' ), true ) ) {
		$s['front_mode'] = 'blank';
	}
	$s['front_menu'] = (int) $s['front_menu'];
	return $s;
}
