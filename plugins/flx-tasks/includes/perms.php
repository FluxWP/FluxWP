<?php
/**
 * Yetkiler.
 *
 * Genel yetkiler (WordPress rolüne göre, Ayarlar'dan seçilir):
 *   flxg_use    : uygulamayı kullanır
 *   flxg_create : proje / klasör / liste oluşturur
 *   flxg_manage : yönetici (her şeyi görür, pano, ayarlar) — manage_options
 *
 * Proje ve liste rolleri: view < comment < edit < admin
 *   Proje erişimi "everyone": tüm kullanıcılar projenin varsayılan rolüyle girer; "members": yalnız üyeler.
 *   Liste erişimi "inherit": projeden gelir (liste üyeliği rolü yükseltebilir); "members": yalnız liste üyeleri
 *   (proje yöneticileri ve sahibi de görür).
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'user_has_cap',
	function ( $all, $caps, $args, $user ) {
		if ( ! array_intersect( array( 'flxg_use', 'flxg_create', 'flxg_manage', 'flxg_ai' ), (array) $caps ) ) {
			return $all;
		}
		$roles = $user instanceof WP_User ? (array) $user->roles : array();
		if ( ! empty( $all['manage_options'] ) ) {
			$all['flxg_use']    = true;
			$all['flxg_create'] = true;
			$all['flxg_manage'] = true;
			$all['flxg_ai']     = true;
			return $all;
		}
		if ( array_intersect( $roles, (array) flxg_setting( 'roles_use' ) ) ) {
			$all['flxg_use'] = true;
		}
		if ( ! empty( $all['flxg_use'] ) && array_intersect( $roles, (array) flxg_setting( 'roles_create' ) ) ) {
			$all['flxg_create'] = true;
		}
		if ( ! empty( $all['flxg_use'] ) && array_intersect( $roles, (array) flxg_setting( 'ai_roles' ) ) ) {
			$all['flxg_ai'] = true;
		}
		return $all;
	},
	10,
	4
);

function flxg_role_rank( $role ) {
	$r = array(
		'view'    => 1,
		'comment' => 2,
		'edit'    => 3,
		'admin'   => 4,
	);
	return $r[ $role ] ?? 0;
}

function flxg_roles() {
	return array(
		'view'    => __( 'Görüntüler', 'flx-tasks' ),
		'comment' => __( 'Yorum yapar', 'flx-tasks' ),
		'edit'    => __( 'Düzenler', 'flx-tasks' ),
		'admin'   => __( 'Yönetir', 'flx-tasks' ),
	);
}

function flxg_max_role( $a, $b ) {
	return flxg_role_rank( $a ) >= flxg_role_rank( $b ) ? $a : $b;
}

/** Kullanıcının üyelikleri: [ 'space' => [id => role], 'list' => [id => role] ]. */
function flxg_memberships( $uid, $refresh = false ) {
	static $cache = array();
	$uid = (int) $uid;
	if ( isset( $cache[ $uid ] ) && ! $refresh ) {
		return $cache[ $uid ];
	}
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT object_type, object_id, role FROM ' . flxg_table( 'members' ) . ' WHERE user_id = %d', $uid ) ); // phpcs:ignore
	$out  = array( 'space' => array(), 'list' => array() );
	foreach ( (array) $rows as $r ) {
		$out[ $r->object_type ][ (int) $r->object_id ] = $r->role;
	}
	$cache[ $uid ] = $out;
	return $out;
}

function flxg_flush_memberships() {
	flxg_memberships( get_current_user_id(), true );
}

function flxg_space_role( $space, $uid = null ) {
	$uid = null === $uid ? get_current_user_id() : (int) $uid;
	if ( ! $uid || ! user_can( $uid, 'flxg_use' ) ) {
		return '';
	}
	if ( is_numeric( $space ) ) {
		$space = flxg_get_space( (int) $space );
	}
	if ( ! $space ) {
		return '';
	}
	if ( user_can( $uid, 'flxg_manage' ) || (int) $space['owner_id'] === $uid ) {
		return 'admin';
	}
	$m    = flxg_memberships( $uid );
	$role = $m['space'][ (int) $space['id'] ] ?? '';
	if ( 'everyone' === $space['access'] ) {
		$role = flxg_max_role( $role, $space['default_role'] );
	}
	return $role;
}

function flxg_list_role( $list, $uid = null ) {
	$uid = null === $uid ? get_current_user_id() : (int) $uid;
	if ( ! $uid || ! user_can( $uid, 'flxg_use' ) ) {
		return '';
	}
	if ( is_numeric( $list ) ) {
		$list = flxg_get_list( (int) $list );
	}
	if ( ! $list ) {
		return '';
	}
	if ( user_can( $uid, 'flxg_manage' ) || (int) $list['owner_id'] === $uid ) {
		return 'admin';
	}
	$m        = flxg_memberships( $uid );
	$explicit = $m['list'][ (int) $list['id'] ] ?? '';
	$space    = flxg_space_role( (int) $list['space_id'], $uid );
	if ( 'admin' === $space ) {
		return 'admin';
	}
	if ( 'members' === $list['access'] ) {
		return $explicit;
	}
	return flxg_max_role( $explicit, $space );
}

function flxg_can_list( $list, $need = 'view', $uid = null ) {
	return flxg_role_rank( flxg_list_role( $list, $uid ) ) >= flxg_role_rank( $need );
}

function flxg_can_space( $space, $need = 'view', $uid = null ) {
	return flxg_role_rank( flxg_space_role( $space, $uid ) ) >= flxg_role_rank( $need );
}

/** Görev üzerinde yetki (görevin listesine göre). */
function flxg_can_task( $task, $need = 'view', $uid = null ) {
	if ( is_numeric( $task ) ) {
		$task = flxg_get_task_row( (int) $task );
	}
	if ( ! $task ) {
		return false;
	}
	return flxg_can_list( (int) $task['list_id'], $need, $uid );
}

/** Kullanıcının görebildiği liste kimlikleri (isteğe bağlı bir projeyle sınırlı). */
function flxg_visible_list_ids( $space_id = 0, $uid = null, $need = 'view' ) {
	$out = array();
	foreach ( flxg_all_lists() as $l ) {
		if ( $space_id && (int) $l['space_id'] !== (int) $space_id ) {
			continue;
		}
		if ( flxg_can_list( $l, $need, $uid ) ) {
			$out[] = (int) $l['id'];
		}
	}
	return $out;
}

/** Proje / liste üyeleri listesi (ayar ekranı için). */
function flxg_get_members( $type, $id ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT user_id, role FROM ' . flxg_table( 'members' ) . ' WHERE object_type = %s AND object_id = %d', $type, $id ), ARRAY_A ); // phpcs:ignore
	$out  = array();
	foreach ( (array) $rows as $r ) {
		$out[] = array( 'user' => (int) $r['user_id'], 'role' => $r['role'] );
	}
	return $out;
}

/**
 * Üyeleri değiştirir. $members = [ [user, role], ... ].
 */
function flxg_set_members( $type, $id, array $members ) {
	global $wpdb;
	$t = flxg_table( 'members' );
	$wpdb->delete( $t, array( 'object_type' => $type, 'object_id' => (int) $id ) );
	$seen = array();
	foreach ( $members as $m ) {
		$uid  = (int) ( $m['user'] ?? 0 );
		$role = (string) ( $m['role'] ?? 'edit' );
		if ( ! $uid || isset( $seen[ $uid ] ) || ! flxg_role_rank( $role ) || ! get_userdata( $uid ) ) {
			continue;
		}
		$seen[ $uid ] = true;
		$wpdb->insert(
			$t,
			array(
				'object_type' => $type,
				'object_id'   => (int) $id,
				'user_id'     => $uid,
				'role'        => $role,
			)
		);
	}
	flxg_flush_memberships();
}
