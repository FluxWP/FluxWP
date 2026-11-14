<?php
/**
 * Veri katmanı: projeler, klasörler, listeler, görünümler, görevler, alanlar, etiketler, yorumlar.
 * REST, MCP ve yapay zekâ araçları aynı fonksiyonları kullanır; yetki kontrolü çağıran tarafta yapılır.
 */

defined( 'ABSPATH' ) || exit;

function flxg_ids_in( array $ids ) {
	$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	return $ids ? implode( ',', $ids ) : '0';
}

/* =========================================================================
 * Projeler (spaces)
 * ====================================================================== */

function flxg_space_row( $r ) {
	return array(
		'id'           => (int) $r['id'],
		'name'         => (string) $r['name'],
		'description'  => (string) $r['description'],
		'color'        => flxg_color( $r['color'], '#2e7d32' ),
		'icon'         => (string) $r['icon'],
		'owner_id'     => (int) $r['owner_id'],
		'access'       => 'members' === $r['access'] ? 'members' : 'everyone',
		'default_role' => flxg_role_rank( $r['default_role'] ) ? $r['default_role'] : 'edit',
		'subtask_depth' => flxg_clean_depth( $r['subtask_depth'] ?? 3 ),
		'position'     => (float) $r['position'],
		'archived'     => (int) $r['archived'],
	);
}

/** Alt görev kademe sınırı: 1–10, boşsa 3. */
function flxg_clean_depth( $v ) {
	$v = (int) $v;
	return $v < 1 ? 3 : min( 10, $v );
}

function flxg_all_spaces( $refresh = false ) {
	static $cache = null;
	if ( null === $cache || $refresh ) {
		global $wpdb;
		$cache = array();
		foreach ( (array) $wpdb->get_results( 'SELECT * FROM ' . flxg_table( 'spaces' ) . ' WHERE archived = 0 ORDER BY position, id', ARRAY_A ) as $r ) { // phpcs:ignore
			$cache[ (int) $r['id'] ] = flxg_space_row( $r );
		}
	}
	return $cache;
}

function flxg_get_space( $id ) {
	$all = flxg_all_spaces();
	return $all[ (int) $id ] ?? null;
}

function flxg_create_space( array $d, $uid ) {
	global $wpdb;
	$now = flxg_now();
	$max = (float) $wpdb->get_var( 'SELECT MAX(position) FROM ' . flxg_table( 'spaces' ) ); // phpcs:ignore
	$wpdb->insert(
		flxg_table( 'spaces' ),
		array(
			'name'         => mb_substr( sanitize_text_field( $d['name'] ?? '' ), 0, 190 ),
			'description'  => sanitize_textarea_field( $d['description'] ?? '' ),
			'color'        => flxg_color( $d['color'] ?? '', '#2e7d32' ),
			'icon'         => sanitize_key( $d['icon'] ?? '' ),
			'owner_id'     => (int) $uid,
			'access'       => 'members' === ( $d['access'] ?? '' ) ? 'members' : 'everyone',
			'default_role' => flxg_role_rank( $d['default_role'] ?? '' ) ? $d['default_role'] : 'edit',
			'subtask_depth' => flxg_clean_depth( $d['subtask_depth'] ?? 3 ),
			'position'     => $max + 1,
			'created_at'   => $now,
			'updated_at'   => $now,
		)
	);
	$id = (int) $wpdb->insert_id;
	flxg_all_spaces( true );
	return $id;
}

function flxg_update_space( $id, array $d ) {
	global $wpdb;
	$set = array();
	if ( isset( $d['name'] ) ) {
		$set['name'] = mb_substr( sanitize_text_field( $d['name'] ), 0, 190 );
	}
	if ( isset( $d['description'] ) ) {
		$set['description'] = sanitize_textarea_field( $d['description'] );
	}
	if ( isset( $d['color'] ) ) {
		$set['color'] = flxg_color( $d['color'], '#2e7d32' );
	}
	if ( isset( $d['icon'] ) ) {
		$set['icon'] = sanitize_key( $d['icon'] );
	}
	if ( isset( $d['access'] ) ) {
		$set['access'] = 'members' === $d['access'] ? 'members' : 'everyone';
	}
	if ( isset( $d['default_role'] ) && flxg_role_rank( $d['default_role'] ) ) {
		$set['default_role'] = $d['default_role'];
	}
	if ( isset( $d['subtask_depth'] ) ) {
		$set['subtask_depth'] = flxg_clean_depth( $d['subtask_depth'] );
	}
	if ( isset( $d['position'] ) ) {
		$set['position'] = (float) $d['position'];
	}
	if ( $set ) {
		$set['updated_at'] = flxg_now();
		$wpdb->update( flxg_table( 'spaces' ), $set, array( 'id' => (int) $id ) );
		flxg_all_spaces( true );
	}
	return flxg_get_space( $id );
}

function flxg_delete_space( $id ) {
	global $wpdb;
	foreach ( flxg_all_lists() as $l ) {
		if ( (int) $l['space_id'] === (int) $id ) {
			flxg_delete_list( $l['id'] );
		}
	}
	foreach ( flxg_all_docs() as $doc ) {
		if ( (int) $doc['space_id'] === (int) $id ) {
			flxg_delete_doc( $doc['id'] );
		}
	}
	$wpdb->delete( flxg_table( 'folders' ), array( 'space_id' => (int) $id ) );
	$wpdb->delete( flxg_table( 'tags' ), array( 'space_id' => (int) $id ) );
	$wpdb->delete( flxg_table( 'members' ), array( 'object_type' => 'space', 'object_id' => (int) $id ) );
	$wpdb->delete( flxg_table( 'views' ), array( 'scope' => 'space', 'scope_id' => (int) $id ) );
	$wpdb->delete( flxg_table( 'spaces' ), array( 'id' => (int) $id ) );
	flxg_all_spaces( true );
}

/* =========================================================================
 * Klasörler
 * ====================================================================== */

function flxg_all_folders( $refresh = false ) {
	static $cache = null;
	if ( null === $cache || $refresh ) {
		global $wpdb;
		$cache = array();
		foreach ( (array) $wpdb->get_results( 'SELECT * FROM ' . flxg_table( 'folders' ) . ' WHERE archived = 0 ORDER BY position, id', ARRAY_A ) as $r ) { // phpcs:ignore
			$cache[ (int) $r['id'] ] = array(
				'id'       => (int) $r['id'],
				'space_id' => (int) $r['space_id'],
				'name'     => (string) $r['name'],
				'color'    => (string) $r['color'],
				'position' => (float) $r['position'],
			);
		}
	}
	return $cache;
}

function flxg_get_folder( $id ) {
	$all = flxg_all_folders();
	return $all[ (int) $id ] ?? null;
}

function flxg_create_folder( array $d ) {
	global $wpdb;
	$max = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(position) FROM ' . flxg_table( 'folders' ) . ' WHERE space_id = %d', (int) $d['space_id'] ) ); // phpcs:ignore
	$wpdb->insert(
		flxg_table( 'folders' ),
		array(
			'space_id'   => (int) $d['space_id'],
			'name'       => mb_substr( sanitize_text_field( $d['name'] ?? '' ), 0, 190 ),
			'color'      => sanitize_hex_color( $d['color'] ?? '' ) ?: '',
			'position'   => $max + 1,
			'created_at' => flxg_now(),
		)
	);
	flxg_all_folders( true );
	return (int) $wpdb->insert_id;
}

function flxg_update_folder( $id, array $d ) {
	global $wpdb;
	$set = array();
	if ( isset( $d['name'] ) ) {
		$set['name'] = mb_substr( sanitize_text_field( $d['name'] ), 0, 190 );
	}
	if ( isset( $d['position'] ) ) {
		$set['position'] = (float) $d['position'];
	}
	if ( $set ) {
		$wpdb->update( flxg_table( 'folders' ), $set, array( 'id' => (int) $id ) );
		flxg_all_folders( true );
	}
	return flxg_get_folder( $id );
}

/** Klasör silinince içindeki listeler silinmez, projenin köküne taşınır. */
function flxg_delete_folder( $id ) {
	global $wpdb;
	$wpdb->update( flxg_table( 'lists' ), array( 'folder_id' => 0 ), array( 'folder_id' => (int) $id ) );
	$wpdb->update( flxg_table( 'docs' ), array( 'folder_id' => 0 ), array( 'folder_id' => (int) $id ) );
	$wpdb->delete( flxg_table( 'folders' ), array( 'id' => (int) $id ) );
	flxg_all_docs( true );
	flxg_all_folders( true );
	flxg_all_lists( true );
}

/* =========================================================================
 * Listeler
 * ====================================================================== */

function flxg_list_row( $r ) {
	$statuses = flxg_json( $r['statuses'] ?? '', array() );
	if ( ! $statuses ) {
		$statuses = flxg_default_statuses();
	}
	return array(
		'id'          => (int) $r['id'],
		'space_id'    => (int) $r['space_id'],
		'folder_id'   => (int) $r['folder_id'],
		'name'        => (string) $r['name'],
		'description' => (string) $r['description'],
		'color'       => flxg_color( $r['color'], '#64748b' ),
		'owner_id'    => (int) $r['owner_id'],
		'access'      => 'members' === $r['access'] ? 'members' : 'inherit',
		'statuses'    => array_values( $statuses ),
		'kind'        => 'meeting' === ( $r['kind'] ?? '' ) ? 'meeting' : 'task',
		'position'    => (float) $r['position'],
	);
}

/** Liste türleri: görev listesi ya da toplantı listesi (aynı altyapı, farklı ekran). */
function flxg_clean_list_kind( $v ) {
	return 'meeting' === $v ? 'meeting' : 'task';
}

/** Görev türleri: görev; toplantıda gündem maddesi ve karar (toplantının alt kayıtları). */
function flxg_clean_task_kind( $v ) {
	return in_array( $v, array( 'agenda', 'decision' ), true ) ? $v : 'task';
}

function flxg_meeting_statuses() {
	return array(
		array( 'key' => 'planned', 'label' => __( 'Planned', 'flx-tasks' ), 'color' => '#3b82f6', 'type' => 'open' ),
		array( 'key' => 'held', 'label' => __( 'Held', 'flx-tasks' ), 'color' => '#22c55e', 'type' => 'done' ),
		array( 'key' => 'cancelled', 'label' => __( 'Cancelled', 'flx-tasks' ), 'color' => '#94a3b8', 'type' => 'done' ),
	);
}

function flxg_all_lists( $refresh = false ) {
	static $cache = null;
	if ( null === $cache || $refresh ) {
		global $wpdb;
		$cache = array();
		foreach ( (array) $wpdb->get_results( 'SELECT * FROM ' . flxg_table( 'lists' ) . ' WHERE archived = 0 ORDER BY position, id', ARRAY_A ) as $r ) { // phpcs:ignore
			$cache[ (int) $r['id'] ] = flxg_list_row( $r );
		}
	}
	return $cache;
}

function flxg_get_list( $id ) {
	$all = flxg_all_lists();
	return $all[ (int) $id ] ?? null;
}

function flxg_clean_statuses( $in ) {
	$out  = array();
	$seen = array();
	foreach ( (array) $in as $s ) {
		$label = mb_substr( sanitize_text_field( $s['label'] ?? '' ), 0, 60 );
		if ( '' === $label ) {
			continue;
		}
		$key = sanitize_key( $s['key'] ?? '' );
		if ( '' === $key ) {
			$key = 's' . substr( md5( $label . wp_rand() ), 0, 6 );
		}
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		$out[]        = array(
			'key'   => $key,
			'label' => $label,
			'color' => flxg_color( $s['color'] ?? '', '#94a3b8' ),
			'type'  => 'done' === ( $s['type'] ?? '' ) ? 'done' : 'open',
		);
	}
	if ( ! $out ) {
		return flxg_default_statuses();
	}
	// En az bir "tamamlandı" durumu olmalı.
	$has_done = false;
	foreach ( $out as $s ) {
		$has_done = $has_done || 'done' === $s['type'];
	}
	if ( ! $has_done ) {
		$out[ count( $out ) - 1 ]['type'] = 'done';
	}
	return $out;
}

function flxg_create_list_row( array $d ) {
	global $wpdb;
	$now  = flxg_now();
	$max  = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(position) FROM ' . flxg_table( 'lists' ) . ' WHERE space_id = %d', (int) $d['space_id'] ) ); // phpcs:ignore
	$kind = flxg_clean_list_kind( $d['kind'] ?? '' );
	if ( 'meeting' === $kind && empty( $d['statuses'] ) ) {
		$d['statuses'] = flxg_meeting_statuses();
	}
	$wpdb->insert(
		flxg_table( 'lists' ),
		array(
			'space_id'    => (int) $d['space_id'],
			'folder_id'   => (int) ( $d['folder_id'] ?? 0 ),
			'name'        => mb_substr( sanitize_text_field( $d['name'] ?? '' ), 0, 190 ),
			'description' => sanitize_textarea_field( $d['description'] ?? '' ),
			'color'       => flxg_color( $d['color'] ?? '', '#64748b' ),
			'owner_id'    => (int) ( $d['owner_id'] ?? 0 ),
			'access'      => 'members' === ( $d['access'] ?? '' ) ? 'members' : 'inherit',
			'statuses'    => flxg_enc( flxg_clean_statuses( $d['statuses'] ?? flxg_default_statuses() ) ),
			'kind'        => $kind,
			'position'    => $max + 1,
			'created_at'  => $now,
			'updated_at'  => $now,
		)
	);
	$id = (int) $wpdb->insert_id;
	flxg_all_lists( true );
	if ( $id ) {
		// Toplantı listesi: tarih, katılımcılar ve etiketler; yaklaşan toplantı üstte.
		flxg_create_default_views( 'list', $id, 'meeting' === $kind ? array( 'fields' => array( 'due', 'assignees', 'tags' ), 'sort' => array( 'by' => 'due', 'dir' => 'asc' ) ) : array() );
	}
	return $id;
}

function flxg_update_list( $id, array $d ) {
	global $wpdb;
	$set  = array();
	$list = flxg_get_list( $id );
	if ( ! $list ) {
		return null;
	}
	foreach ( array( 'name' ) as $k ) {
		if ( isset( $d[ $k ] ) ) {
			$set[ $k ] = mb_substr( sanitize_text_field( $d[ $k ] ), 0, 190 );
		}
	}
	if ( isset( $d['description'] ) ) {
		$set['description'] = sanitize_textarea_field( $d['description'] );
	}
	if ( isset( $d['color'] ) ) {
		$set['color'] = flxg_color( $d['color'] );
	}
	if ( isset( $d['access'] ) ) {
		$set['access'] = 'members' === $d['access'] ? 'members' : 'inherit';
	}
	if ( isset( $d['kind'] ) ) {
		$set['kind'] = flxg_clean_list_kind( $d['kind'] );
	}
	if ( isset( $d['folder_id'] ) ) {
		$f                = flxg_get_folder( (int) $d['folder_id'] );
		$set['folder_id'] = $f && (int) $f['space_id'] === (int) ( $d['space_id'] ?? $list['space_id'] ) ? (int) $f['id'] : 0;
	}
	if ( isset( $d['space_id'] ) && flxg_get_space( (int) $d['space_id'] ) ) {
		$set['space_id'] = (int) $d['space_id'];
		if ( ! isset( $set['folder_id'] ) ) {
			$set['folder_id'] = 0;
		}
	}
	if ( isset( $d['position'] ) ) {
		$set['position'] = (float) $d['position'];
	}
	if ( isset( $d['statuses'] ) ) {
		$new           = flxg_clean_statuses( $d['statuses'] );
		$set['statuses'] = flxg_enc( $new );
		// Silinen durumdaki görevler ilk açık duruma alınır.
		$keys = wp_list_pluck( $new, 'key' );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . flxg_table( 'tasks' ) . ' SET status = %s WHERE list_id = %d AND status NOT IN (' . implode( ',', array_fill( 0, count( $keys ), '%s' ) ) . ')', array_merge( array( $new[0]['key'], (int) $id ), $keys ) ) ); // phpcs:ignore
	}
	if ( $set ) {
		$set['updated_at'] = flxg_now();
		$wpdb->update( flxg_table( 'lists' ), $set, array( 'id' => (int) $id ) );
		flxg_all_lists( true );
	}
	return flxg_get_list( $id );
}

function flxg_delete_list( $id ) {
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxg_table( 'tasks' ) . ' WHERE list_id = %d', (int) $id ) ); // phpcs:ignore
	foreach ( $ids as $tid ) {
		flxg_delete_task( (int) $tid, 0, false );
	}
	foreach ( flxg_get_fields( $id ) as $f ) {
		flxg_delete_field( $f['id'] );
	}
	$wpdb->delete( flxg_table( 'views' ), array( 'scope' => 'list', 'scope_id' => (int) $id ) );
	$wpdb->delete( flxg_table( 'members' ), array( 'object_type' => 'list', 'object_id' => (int) $id ) );
	$wpdb->delete( flxg_table( 'activity' ), array( 'list_id' => (int) $id ) );
	$wpdb->delete( flxg_table( 'lists' ), array( 'id' => (int) $id ) );
	flxg_all_lists( true );
}

function flxg_status_info( $list, $key ) {
	foreach ( (array) ( $list['statuses'] ?? array() ) as $s ) {
		if ( $s['key'] === $key ) {
			return $s;
		}
	}
	return null;
}

function flxg_first_status( $list, $type = 'open' ) {
	foreach ( (array) $list['statuses'] as $s ) {
		if ( $s['type'] === $type ) {
			return $s['key'];
		}
	}
	return $list['statuses'][0]['key'] ?? 'todo';
}

/* =========================================================================
 * Görünümler (kayıtlı filtreli görünümler)
 * ====================================================================== */

function flxg_view_types() {
	return array( 'list', 'board', 'calendar' );
}

function flxg_default_view_config( $type ) {
	return array(
		'filters'  => array(),
		'group'    => 'calendar' === $type ? 'none' : 'status',
		'sort'     => array( 'by' => 'position', 'dir' => 'asc' ),
		'fields'   => 'list' === $type ? array( 'assignees', 'due', 'priority', 'tags' ) : array( 'assignees', 'due', 'priority', 'tags' ),
		'cover'    => 'list' !== $type,
		'showDone' => true,
		'dateField' => 'due',
	);
}

function flxg_create_default_views( $scope, $scope_id, $config = array() ) {
	$names = array(
		'list'     => __( 'List', 'flx-tasks' ),
		'board'    => __( 'Board', 'flx-tasks' ),
		'calendar' => __( 'Calendar', 'flx-tasks' ),
	);
	$i = 1;
	foreach ( $names as $type => $name ) {
		flxg_create_view(
			array(
				'scope'    => $scope,
				'scope_id' => $scope_id,
				'name'     => $name,
				'type'     => $type,
				'user_id'  => 0,
				'position' => $i++,
				'config'   => $config ? array_merge( flxg_default_view_config( $type ), $config ) : array(),
			)
		);
	}
}

function flxg_clean_view_config( $c, $type ) {
	$c   = is_array( $c ) ? $c : array();
	$def = flxg_default_view_config( $type );
	$out = array();
	// Filtreler.
	$f    = is_array( $c['filters'] ?? null ) ? $c['filters'] : array();
	$filt = array();
	foreach ( array( 'status', 'assignees', 'priority', 'tags' ) as $k ) {
		if ( ! empty( $f[ $k ] ) && is_array( $f[ $k ] ) ) {
			$filt[ $k ] = array_values( array_map( 'sanitize_text_field', array_map( 'strval', $f[ $k ] ) ) );
		}
	}
	if ( ! empty( $f['due'] ) ) {
		$filt['due'] = sanitize_key( $f['due'] );
	}
	if ( ! empty( $f['q'] ) ) {
		$filt['q'] = sanitize_text_field( $f['q'] );
	}
	if ( ! empty( $f['fields'] ) && is_array( $f['fields'] ) ) {
		$filt['fields'] = array();
		foreach ( $f['fields'] as $fid => $val ) {
			$filt['fields'][ sanitize_key( $fid ) ] = is_array( $val ) ? array_map( 'sanitize_text_field', array_map( 'strval', $val ) ) : sanitize_text_field( (string) $val );
		}
	}
	$out['filters'] = $filt;
	$out['group']   = sanitize_key( $c['group'] ?? $def['group'] );
	$sort           = is_array( $c['sort'] ?? null ) ? $c['sort'] : $def['sort'];
	$out['sort']    = array(
		'by'  => sanitize_key( $sort['by'] ?? 'position' ),
		'dir' => 'desc' === ( $sort['dir'] ?? '' ) ? 'desc' : 'asc',
	);
	$out['fields']    = array_values( array_map( 'sanitize_key', (array) ( $c['fields'] ?? $def['fields'] ) ) );
	$out['cover']     = ! empty( $c['cover'] ?? $def['cover'] );
	$out['showDone']  = ! isset( $c['showDone'] ) || ! empty( $c['showDone'] );
	$out['dateField'] = 'start' === ( $c['dateField'] ?? '' ) ? 'start' : 'due';
	$out['collapsed'] = array_values( array_map( 'sanitize_text_field', array_map( 'strval', (array) ( $c['collapsed'] ?? array() ) ) ) );
	// Sütun genişlikleri (px), alt görev gösterimi (open|closed|off), filtrenin alt görevlere uygulanması.
	$out['widths'] = array();
	foreach ( (array) ( $c['widths'] ?? array() ) as $k => $w ) {
		$k = sanitize_key( $k );
		if ( '' !== $k && is_numeric( $w ) ) {
			$out['widths'][ $k ] = max( 50, min( 900, (int) $w ) );
		}
	}
	if ( ! $out['widths'] ) {
		$out['widths'] = new stdClass();
	}
	$out['subtasks']  = in_array( $c['subtasks'] ?? '', array( 'open', 'closed', 'off' ), true ) ? $c['subtasks'] : 'open';
	$out['subFilter'] = ! empty( $c['subFilter'] );
	$out['wrap']      = ! isset( $c['wrap'] ) || ! empty( $c['wrap'] );
	return $out;
}

function flxg_view_row( $r ) {
	return array(
		'id'       => (int) $r['id'],
		'scope'    => (string) $r['scope'],
		'scope_id' => (int) $r['scope_id'],
		'user_id'  => (int) $r['user_id'],
		'name'     => (string) $r['name'],
		'type'     => in_array( $r['type'], flxg_view_types(), true ) ? $r['type'] : 'list',
		'config'   => flxg_clean_view_config( flxg_json( $r['config'] ), $r['type'] ),
		'position' => (float) $r['position'],
	);
}

function flxg_create_view( array $d ) {
	global $wpdb;
	$type = in_array( $d['type'] ?? '', flxg_view_types(), true ) ? $d['type'] : 'list';
	$pos  = $d['position'] ?? ( 1 + (float) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(position) FROM ' . flxg_table( 'views' ) . ' WHERE scope = %s AND scope_id = %d', $d['scope'], (int) $d['scope_id'] ) ) ); // phpcs:ignore
	$wpdb->insert(
		flxg_table( 'views' ),
		array(
			'scope'      => in_array( $d['scope'], array( 'list', 'space', 'home' ), true ) ? $d['scope'] : 'list',
			'scope_id'   => (int) $d['scope_id'],
			'user_id'    => (int) ( $d['user_id'] ?? 0 ),
			'name'       => mb_substr( sanitize_text_field( $d['name'] ?? '' ), 0, 190 ),
			'type'       => $type,
			'config'     => flxg_enc( flxg_clean_view_config( $d['config'] ?? array(), $type ) ),
			'position'   => (float) $pos,
			'created_at' => flxg_now(),
		)
	);
	return (int) $wpdb->insert_id;
}

function flxg_get_view( $id ) {
	global $wpdb;
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxg_table( 'views' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore
	return $r ? flxg_view_row( $r ) : null;
}

/** Bir kapsamın görünümleri: paylaşılanlar + kullanıcının kişisel görünümleri. */
function flxg_get_views( $scope, $scope_id, $uid ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxg_table( 'views' ) . ' WHERE scope = %s AND scope_id = %d AND (user_id = 0 OR user_id = %d) ORDER BY position, id', $scope, (int) $scope_id, (int) $uid ), ARRAY_A ); // phpcs:ignore
	if ( ! $rows && in_array( $scope, array( 'space', 'home' ), true ) ) {
		flxg_create_default_views( $scope, $scope_id );
		return flxg_get_views( $scope, $scope_id, $uid );
	}
	return array_map( 'flxg_view_row', (array) $rows );
}

function flxg_update_view( $id, array $d ) {
	global $wpdb;
	$v = flxg_get_view( $id );
	if ( ! $v ) {
		return null;
	}
	$set = array();
	if ( isset( $d['name'] ) ) {
		$set['name'] = mb_substr( sanitize_text_field( $d['name'] ), 0, 190 );
	}
	$type = $v['type'];
	if ( isset( $d['type'] ) && in_array( $d['type'], flxg_view_types(), true ) ) {
		$set['type'] = $d['type'];
		$type        = $d['type'];
	}
	if ( isset( $d['config'] ) ) {
		$set['config'] = flxg_enc( flxg_clean_view_config( $d['config'], $type ) );
	}
	if ( isset( $d['position'] ) ) {
		$set['position'] = (float) $d['position'];
	}
	if ( isset( $d['shared'] ) ) {
		$set['user_id'] = $d['shared'] ? 0 : get_current_user_id();
	}
	if ( $set ) {
		$wpdb->update( flxg_table( 'views' ), $set, array( 'id' => (int) $id ) );
	}
	return flxg_get_view( $id );
}

function flxg_delete_view( $id ) {
	global $wpdb;
	$wpdb->delete( flxg_table( 'views' ), array( 'id' => (int) $id ) );
}

/* =========================================================================
 * Özel alanlar
 * ====================================================================== */

function flxg_field_row( $r ) {
	return array(
		'id'       => (int) $r['id'],
		'list_id'  => (int) $r['list_id'],
		'name'     => (string) $r['name'],
		'type'     => (string) $r['type'],
		'options'  => flxg_json( $r['options'] ),
		'position' => (float) $r['position'],
	);
}

function flxg_get_fields( $list_ids ) {
	global $wpdb;
	$list_ids = (array) $list_ids;
	$rows     = $wpdb->get_results( 'SELECT * FROM ' . flxg_table( 'fields' ) . ' WHERE list_id IN (' . flxg_ids_in( $list_ids ) . ') ORDER BY position, id', ARRAY_A ); // phpcs:ignore
	return array_map( 'flxg_field_row', (array) $rows );
}

function flxg_get_field( $id ) {
	global $wpdb;
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxg_table( 'fields' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore
	return $r ? flxg_field_row( $r ) : null;
}

function flxg_clean_field_options( $type, $o ) {
	$o   = is_array( $o ) ? $o : array();
	$out = array();
	if ( in_array( $type, array( 'dropdown', 'labels' ), true ) ) {
		$out['choices'] = array();
		$seen           = array();
		foreach ( (array) ( $o['choices'] ?? array() ) as $c ) {
			$label = mb_substr( sanitize_text_field( $c['label'] ?? '' ), 0, 80 );
			if ( '' === $label ) {
				continue;
			}
			$id = sanitize_key( $c['id'] ?? '' );
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				$id = 'c' . substr( md5( $label . wp_rand() ), 0, 6 );
			}
			$seen[ $id ]      = true;
			$out['choices'][] = array(
				'id'    => $id,
				'label' => $label,
				'color' => flxg_color( $c['color'] ?? '', '#94a3b8' ),
			);
		}
	}
	if ( 'rating' === $type ) {
		$out['max'] = max( 3, min( 10, (int) ( $o['max'] ?? 5 ) ) );
	}
	if ( 'money' === $type ) {
		$out['currency'] = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) ( $o['currency'] ?? 'TRY' ) ), 0, 3 ) );
		if ( '' === $out['currency'] ) {
			$out['currency'] = 'TRY';
		}
	}
	if ( 'number' === $type ) {
		$out['decimals'] = max( 0, min( 4, (int) ( $o['decimals'] ?? 0 ) ) );
	}
	$out['required'] = ! empty( $o['required'] );
	$out['hint']     = mb_substr( sanitize_text_field( $o['hint'] ?? '' ), 0, 200 );
	return $out;
}

function flxg_create_field( $list_id, array $d ) {
	global $wpdb;
	$type = array_key_exists( $d['type'] ?? '', flxg_field_types() ) ? $d['type'] : 'text';
	$max  = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(position) FROM ' . flxg_table( 'fields' ) . ' WHERE list_id = %d', (int) $list_id ) ); // phpcs:ignore
	$wpdb->insert(
		flxg_table( 'fields' ),
		array(
			'list_id'    => (int) $list_id,
			'name'       => mb_substr( sanitize_text_field( $d['name'] ?? '' ), 0, 190 ),
			'type'       => $type,
			'options'    => flxg_enc( flxg_clean_field_options( $type, $d['options'] ?? array() ) ),
			'position'   => $max + 1,
			'created_at' => flxg_now(),
		)
	);
	return (int) $wpdb->insert_id;
}

function flxg_update_field( $id, array $d ) {
	global $wpdb;
	$f = flxg_get_field( $id );
	if ( ! $f ) {
		return null;
	}
	$set = array();
	if ( isset( $d['name'] ) ) {
		$set['name'] = mb_substr( sanitize_text_field( $d['name'] ), 0, 190 );
	}
	if ( isset( $d['options'] ) ) {
		$set['options'] = flxg_enc( flxg_clean_field_options( $f['type'], $d['options'] ) );
	}
	if ( isset( $d['position'] ) ) {
		$set['position'] = (float) $d['position'];
	}
	if ( $set ) {
		$wpdb->update( flxg_table( 'fields' ), $set, array( 'id' => (int) $id ) );
	}
	return flxg_get_field( $id );
}

function flxg_delete_field( $id ) {
	global $wpdb;
	$files = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxg_table( 'files' ) . ' WHERE field_id = %d', (int) $id ) ); // phpcs:ignore
	foreach ( $files as $fid ) {
		flxg_delete_file( (int) $fid );
	}
	$wpdb->delete( flxg_table( 'values' ), array( 'field_id' => (int) $id ) );
	$wpdb->delete( flxg_table( 'fields' ), array( 'id' => (int) $id ) );
}

/** Alan değerini türüne göre temizler; boş değer null döner. */
function flxg_clean_field_value( $field, $v ) {
	if ( null === $v || '' === $v || array() === $v ) {
		return null;
	}
	switch ( $field['type'] ) {
		case 'textarea':
			return mb_substr( sanitize_textarea_field( (string) $v ), 0, 20000 );
		case 'number':
		case 'money':
			$v = str_replace( ',', '.', (string) $v );
			return is_numeric( $v ) ? (string) ( 0 + $v ) : null;
		case 'date':
			$d = flxg_clean_date( $v );
			return '' !== $d ? $d : null;
		case 'checkbox':
			return ! empty( $v ) && 'false' !== $v && '0' !== (string) $v ? '1' : null;
		case 'dropdown':
			$ids = wp_list_pluck( (array) ( $field['options']['choices'] ?? array() ), 'id' );
			return in_array( (string) $v, $ids, true ) ? (string) $v : null;
		case 'labels':
			$ids = wp_list_pluck( (array) ( $field['options']['choices'] ?? array() ), 'id' );
			$v   = array_values( array_intersect( array_map( 'strval', (array) $v ), $ids ) );
			return $v ? flxg_enc( $v ) : null;
		case 'rating':
			$n = (int) $v;
			return $n > 0 ? (string) min( $n, (int) ( $field['options']['max'] ?? 5 ) ) : null;
		case 'people':
			$v = array_values( array_filter( array_map( 'intval', (array) $v ) ) );
			return $v ? flxg_enc( $v ) : null;
		case 'file':
			$v = array_values( array_filter( array_map( 'intval', (array) $v ) ) );
			return $v ? flxg_enc( $v ) : null;
		case 'url':
			$u = esc_url_raw( trim( (string) $v ) );
			return '' !== $u ? $u : null;
		case 'email':
			$e = sanitize_email( (string) $v );
			return '' !== $e ? $e : null;
		case 'phone':
			$p = preg_replace( '/[^0-9+()\s-]/', '', (string) $v );
			return '' !== trim( $p ) ? trim( $p ) : null;
		default:
			return mb_substr( sanitize_text_field( (string) $v ), 0, 1000 );
	}
}

/** Arayüze giden değer (JSON çözülmüş). */
function flxg_field_out( $type, $raw ) {
	if ( in_array( $type, array( 'labels', 'people', 'file' ), true ) ) {
		return flxg_json( $raw, array() );
	}
	if ( 'checkbox' === $type ) {
		return '1' === (string) $raw;
	}
	if ( in_array( $type, array( 'number', 'money', 'rating' ), true ) ) {
		return is_numeric( $raw ) ? 0 + $raw : null;
	}
	return $raw;
}

/* =========================================================================
 * Etiketler (proje bazlı)
 * ====================================================================== */

function flxg_all_tags() {
	global $wpdb;
	$out = array();
	foreach ( (array) $wpdb->get_results( 'SELECT * FROM ' . flxg_table( 'tags' ) . ' ORDER BY name', ARRAY_A ) as $r ) { // phpcs:ignore
		$out[] = array(
			'id'       => (int) $r['id'],
			'space_id' => (int) $r['space_id'],
			'name'     => (string) $r['name'],
			'color'    => flxg_color( $r['color'], '#64748b' ),
		);
	}
	return $out;
}

function flxg_get_tag( $id ) {
	foreach ( flxg_all_tags() as $t ) {
		if ( (int) $t['id'] === (int) $id ) {
			return $t;
		}
	}
	return null;
}

function flxg_create_tag( $space_id, $name, $color = '' ) {
	global $wpdb;
	$name = mb_substr( sanitize_text_field( $name ), 0, 80 );
	if ( '' === $name ) {
		return 0;
	}
	$ex = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . flxg_table( 'tags' ) . ' WHERE space_id = %d AND name = %s', (int) $space_id, $name ) ); // phpcs:ignore
	if ( $ex ) {
		return (int) $ex;
	}
	$palette = array( '#ef4444', '#f97316', '#eab308', '#22c55e', '#14b8a6', '#3b82f6', '#8b5cf6', '#ec4899', '#64748b' );
	$wpdb->insert(
		flxg_table( 'tags' ),
		array(
			'space_id' => (int) $space_id,
			'name'     => $name,
			'color'    => flxg_color( $color, $palette[ abs( crc32( $name ) ) % count( $palette ) ] ),
		)
	);
	return (int) $wpdb->insert_id;
}

function flxg_update_tag( $id, array $d ) {
	global $wpdb;
	$set = array();
	if ( isset( $d['name'] ) ) {
		$set['name'] = mb_substr( sanitize_text_field( $d['name'] ), 0, 80 );
	}
	if ( isset( $d['color'] ) ) {
		$set['color'] = flxg_color( $d['color'] );
	}
	if ( $set ) {
		$wpdb->update( flxg_table( 'tags' ), $set, array( 'id' => (int) $id ) );
	}
	return flxg_get_tag( $id );
}

function flxg_delete_tag( $id ) {
	global $wpdb;
	$wpdb->delete( flxg_table( 'task_tags' ), array( 'tag_id' => (int) $id ) );
	$wpdb->delete( flxg_table( 'tags' ), array( 'id' => (int) $id ) );
}

/* =========================================================================
 * Görevler
 * ====================================================================== */

function flxg_get_task_row( $id ) {
	global $wpdb;
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxg_table( 'tasks' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore
	return $r ? $r : null;
}

/**
 * Görev satırlarını arayüz biçimine çevirir (atananlar, etiketler, alan değerleri, sayılar tek seferde yüklenir).
 */
function flxg_format_tasks( array $rows, $with_desc = false ) {
	global $wpdb;
	if ( ! $rows ) {
		return array();
	}
	$ids   = array_map( 'intval', wp_list_pluck( $rows, 'id' ) );
	$in    = flxg_ids_in( $ids );
	$assig = array();
	foreach ( (array) $wpdb->get_results( 'SELECT task_id, user_id FROM ' . flxg_table( 'assignees' ) . " WHERE task_id IN ($in) ORDER BY id" ) as $r ) { // phpcs:ignore
		$assig[ (int) $r->task_id ][] = (int) $r->user_id;
	}
	$tags = array();
	foreach ( (array) $wpdb->get_results( 'SELECT task_id, tag_id FROM ' . flxg_table( 'task_tags' ) . " WHERE task_id IN ($in) ORDER BY id" ) as $r ) { // phpcs:ignore
		$tags[ (int) $r->task_id ][] = (int) $r->tag_id;
	}
	$ftypes = array();
	$lists  = array_unique( array_map( 'intval', wp_list_pluck( $rows, 'list_id' ) ) );
	foreach ( flxg_get_fields( $lists ) as $f ) {
		$ftypes[ $f['id'] ] = $f['type'];
	}
	$vals = array();
	foreach ( (array) $wpdb->get_results( 'SELECT task_id, field_id, value FROM ' . flxg_table( 'values' ) . " WHERE task_id IN ($in)" ) as $r ) { // phpcs:ignore
		if ( isset( $ftypes[ (int) $r->field_id ] ) ) {
			$vals[ (int) $r->task_id ][ (int) $r->field_id ] = flxg_field_out( $ftypes[ (int) $r->field_id ], $r->value );
		}
	}
	$ccount = array();
	foreach ( (array) $wpdb->get_results( 'SELECT task_id, COUNT(*) AS n FROM ' . flxg_table( 'comments' ) . " WHERE task_id IN ($in) GROUP BY task_id" ) as $r ) { // phpcs:ignore
		$ccount[ (int) $r->task_id ] = (int) $r->n;
	}
	$fcount = array();
	foreach ( (array) $wpdb->get_results( 'SELECT task_id, COUNT(*) AS n FROM ' . flxg_table( 'files' ) . " WHERE task_id IN ($in) AND context <> 'pending' GROUP BY task_id" ) as $r ) { // phpcs:ignore
		$fcount[ (int) $r->task_id ] = (int) $r->n;
	}
	$sub = array();
	$agn = array();
	$dec = array();
	foreach ( (array) $wpdb->get_results( 'SELECT parent_id, kind, COUNT(*) AS n FROM ' . flxg_table( 'tasks' ) . " WHERE parent_id IN ($in) AND archived = 0 GROUP BY parent_id, kind" ) as $r ) { // phpcs:ignore
		if ( 'agenda' === $r->kind ) {
			$agn[ (int) $r->parent_id ] = (int) $r->n;
		} elseif ( 'decision' === $r->kind ) {
			$dec[ (int) $r->parent_id ] = (int) $r->n;
		} else {
			$sub[ (int) $r->parent_id ] = ( $sub[ (int) $r->parent_id ] ?? 0 ) + (int) $r->n;
		}
	}
	$out = array();
	foreach ( $rows as $r ) {
		$id = (int) $r['id'];
		$t  = array(
			'id'        => $id,
			'list'      => (int) $r['list_id'],
			'parent'    => (int) $r['parent_id'],
			'kind'      => flxg_clean_task_kind( $r['kind'] ?? '' ),
			'title'     => (string) $r['title'],
			'status'    => (string) $r['status'],
			'priority'  => (int) $r['priority'],
			'start'     => (string) $r['start_date'],
			'due'       => (string) $r['due_date'],
			'cover'     => (int) $r['cover_id'],
			'pos'       => (float) $r['position'],
			'creator'   => (int) $r['creator_id'],
			'created'   => flxg_iso( $r['created_at'] ),
			'updated'   => flxg_iso( $r['updated_at'] ),
			'completed' => flxg_iso( $r['completed_at'] ),
			'assignees' => $assig[ $id ] ?? array(),
			'tags'      => $tags[ $id ] ?? array(),
			'fields'    => isset( $vals[ $id ] ) ? (object) $vals[ $id ] : new stdClass(),
			'comments'  => $ccount[ $id ] ?? 0,
			'files'     => $fcount[ $id ] ?? 0,
			'subtasks'  => $sub[ $id ] ?? 0,
			'archived'  => (int) $r['archived'],
		);
		if ( isset( $agn[ $id ] ) ) {
			$t['agenda'] = $agn[ $id ];
		}
		if ( isset( $dec[ $id ] ) ) {
			$t['decisions'] = $dec[ $id ];
		}
		if ( $with_desc ) {
			$t['description'] = (string) $r['description'];
		} else {
			$t['hasDesc'] = '' !== trim( wp_strip_all_tags( (string) $r['description'] ) ) || false !== stripos( (string) $r['description'], '<img' );
		}
		$out[] = $t;
	}
	return $out;
}

/**
 * Görev sorgusu.
 *
 * @param array $a lists (int[]), q, status (string[]), status_type (open|done), assignee (int), priority (int[]),
 *                 tag (int[]), due_before, due_after, since (UTC mysql), parent (int|null), archived (bool), limit, offset, order,
 *                 exclude_kind (string[]), count (bool: yalnız sayı döner).
 */
function flxg_query_tasks( array $a ) {
	global $wpdb;
	$lists = array_map( 'intval', (array) ( $a['lists'] ?? array() ) );
	if ( ! $lists ) {
		return array();
	}
	$t     = flxg_table( 'tasks' );
	$where = array( "t.list_id IN (" . flxg_ids_in( $lists ) . ')' );
	$args  = array();
	$where[] = empty( $a['archived'] ) ? 't.archived = 0' : 't.archived = 1';
	if ( ! empty( $a['ids'] ) ) {
		$where[] = 't.id IN (' . flxg_ids_in( (array) $a['ids'] ) . ')';
	}
	if ( ! empty( $a['q'] ) ) {
		$like    = '%' . $wpdb->esc_like( (string) $a['q'] ) . '%';
		$where[] = '(t.title LIKE %s OR t.description LIKE %s' . ( ctype_digit( ltrim( (string) $a['q'], '#' ) ) ? ' OR t.id = %d' : '' ) . ')';
		$args[]  = $like;
		$args[]  = $like;
		if ( ctype_digit( ltrim( (string) $a['q'], '#' ) ) ) {
			$args[] = (int) ltrim( (string) $a['q'], '#' );
		}
	}
	if ( ! empty( $a['status'] ) ) {
		$st      = array_map( 'strval', (array) $a['status'] );
		$where[] = 't.status IN (' . implode( ',', array_fill( 0, count( $st ), '%s' ) ) . ')';
		$args    = array_merge( $args, $st );
	}
	if ( ! empty( $a['status_type'] ) ) {
		// Durum türü listeye göre değişir: listelerin "done" anahtarlarını topla.
		$done = array();
		foreach ( $lists as $lid ) {
			$l = flxg_get_list( $lid );
			foreach ( (array) ( $l['statuses'] ?? array() ) as $s ) {
				if ( 'done' === $s['type'] ) {
					$done[] = $wpdb->prepare( '(t.list_id = %d AND t.status = %s)', $lid, $s['key'] );
				}
			}
		}
		$cond = $done ? '(' . implode( ' OR ', $done ) . ')' : '1=0';
		$where[] = 'done' === $a['status_type'] ? $cond : 'NOT ' . $cond;
	}
	if ( ! empty( $a['assignee'] ) ) {
		$where[] = 't.id IN (SELECT task_id FROM ' . flxg_table( 'assignees' ) . ' WHERE user_id = %d)';
		$args[]  = (int) $a['assignee'];
	}
	if ( ! empty( $a['priority'] ) ) {
		$where[] = 't.priority IN (' . flxg_ids_in( (array) $a['priority'] ) . ')';
	}
	if ( ! empty( $a['tag'] ) ) {
		$where[] = 't.id IN (SELECT task_id FROM ' . flxg_table( 'task_tags' ) . ' WHERE tag_id IN (' . flxg_ids_in( (array) $a['tag'] ) . '))';
	}
	if ( ! empty( $a['due_before'] ) ) {
		$where[] = "t.due_date <> '' AND t.due_date <= %s";
		$args[]  = (string) $a['due_before'] . 'T99';
	}
	if ( ! empty( $a['due_after'] ) ) {
		$where[] = "t.due_date <> '' AND t.due_date >= %s";
		$args[]  = (string) $a['due_after'];
	}
	if ( ! empty( $a['since'] ) ) {
		$where[] = 't.updated_at > %s';
		$args[]  = (string) $a['since'];
	}
	if ( isset( $a['parent'] ) && null !== $a['parent'] ) {
		$where[] = 't.parent_id = %d';
		$args[]  = (int) $a['parent'];
	}
	if ( ! empty( $a['exclude_kind'] ) ) {
		$where[] = "t.kind NOT IN ('" . implode( "','", array_map( 'sanitize_key', (array) $a['exclude_kind'] ) ) . "')";
	}
	if ( ! empty( $a['count'] ) ) {
		$sql = "SELECT COUNT(*) FROM $t t WHERE " . implode( ' AND ', $where );
		return (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_var( $sql ) ); // phpcs:ignore
	}
	$limit  = max( 1, min( 5000, (int) ( $a['limit'] ?? 2000 ) ) );
	$offset = max( 0, (int) ( $a['offset'] ?? 0 ) );
	$order  = 'due' === ( $a['order'] ?? '' ) ? "CASE WHEN t.due_date = '' THEN 1 ELSE 0 END, t.due_date, t.position" : ( 'updated' === ( $a['order'] ?? '' ) ? 't.updated_at DESC' : 't.position, t.id' );
	$sql    = "SELECT t.* FROM $t t WHERE " . implode( ' AND ', $where ) . " ORDER BY $order LIMIT $limit OFFSET $offset";
	$rows   = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore
	return flxg_format_tasks( (array) $rows, ! empty( $a['with_desc'] ) );
}

function flxg_get_task( $id, $with_desc = true ) {
	$r = flxg_get_task_row( $id );
	if ( ! $r ) {
		return null;
	}
	$t = flxg_format_tasks( array( $r ), $with_desc );
	return $t[0] ?? null;
}

/** Açıklama HTML'i: sınırlı etiketler. */
function flxg_kses_desc( $html ) {
	$allowed = array(
		'p'          => array(),
		'br'         => array(),
		'strong'     => array(),
		'b'          => array(),
		'em'         => array(),
		'i'          => array(),
		'u'          => array(),
		's'          => array(),
		'strike'     => array(),
		'del'        => array(),
		'h2'         => array(),
		'h3'         => array(),
		'h4'         => array(),
		'ul'         => array( 'data-checklist' => true ),
		'ol'         => array(),
		'li'         => array( 'data-checked' => true ),
		'blockquote' => array(),
		'code'       => array(),
		'pre'        => array(),
		'hr'         => array(),
		'a'          => array( 'href' => true, 'target' => true, 'rel' => true ),
		'div'        => array(),
		'span'       => array(),
		'img'        => array( 'data-file' => true, 'alt' => true ),
	);
	$html = trim( wp_kses( (string) $html, $allowed ) );
	// Görsel yalnız dosya kimliğiyle; adres istemcide verilir.
	return preg_replace_callback(
		'/<img\b[^>]*>/i',
		function ( $m ) {
			if ( ! preg_match( '/data-file="(\d+)"/', $m[0], $id ) ) {
				return '';
			}
			$alt = preg_match( '/alt="([^"]*)"/', $m[0], $a ) ? $a[1] : '';
			return '<img data-file="' . (int) $id[1] . '" alt="' . esc_attr( $alt ) . '" />';
		},
		$html
	);
}

function flxg_next_position( $list_id ) {
	global $wpdb;
	return 1024 + (float) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(position) FROM ' . flxg_table( 'tasks' ) . ' WHERE list_id = %d', (int) $list_id ) ); // phpcs:ignore
}

function flxg_create_task_row( array $d ) {
	global $wpdb;
	$list = flxg_get_list( (int) $d['list_id'] );
	if ( ! $list ) {
		return 0;
	}
	$status = (string) ( $d['status'] ?? '' );
	if ( ! flxg_status_info( $list, $status ) ) {
		$status = flxg_first_status( $list );
	}
	$info = flxg_status_info( $list, $status );
	$now  = flxg_now();
	$wpdb->insert(
		flxg_table( 'tasks' ),
		array(
			'list_id'      => (int) $list['id'],
			'parent_id'    => (int) ( $d['parent_id'] ?? 0 ),
			'kind'         => flxg_clean_task_kind( $d['kind'] ?? '' ),
			'title'        => mb_substr( sanitize_text_field( $d['title'] ?? '' ), 0, 500 ),
			'description'  => flxg_kses_desc( $d['description'] ?? '' ),
			'status'       => $status,
			'priority'     => max( 0, min( 4, (int) ( $d['priority'] ?? 0 ) ) ),
			'start_date'   => flxg_clean_date( $d['start'] ?? ( $d['start_date'] ?? '' ) ),
			'due_date'     => flxg_clean_date( $d['due'] ?? ( $d['due_date'] ?? '' ) ),
			'position'     => isset( $d['position'] ) ? (float) $d['position'] : flxg_next_position( $list['id'] ),
			'creator_id'   => (int) ( $d['creator_id'] ?? 0 ),
			'created_at'   => $now,
			'updated_at'   => $now,
			'completed_at' => $info && 'done' === $info['type'] ? $now : null,
		)
	);
	return (int) $wpdb->insert_id;
}

/**
 * Görev oluşturur (atananlar, etiketler, alanlar, etkinlik ve bildirimlerle).
 *
 * @return int|WP_Error
 */
/** Görevin kaçıncı kademe alt görev olduğu (üst görev 0). */
function flxg_task_depth( $id ) {
	global $wpdb;
	$depth = 0;
	$seen  = array();
	$id    = (int) $id;
	while ( $id && $depth < 20 && ! isset( $seen[ $id ] ) ) {
		$seen[ $id ] = 1;
		$id          = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT parent_id FROM ' . flxg_table( 'tasks' ) . ' WHERE id = %d', $id ) ); // phpcs:ignore
		if ( $id ) {
			++$depth;
		}
	}
	return $depth;
}

/** Görevin altındaki en derin kademe sayısı (alt görevi yoksa 0). */
function flxg_task_height( $id, $guard = 0 ) {
	global $wpdb;
	if ( $guard > 20 ) {
		return 0;
	}
	$max = 0;
	foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxg_table( 'tasks' ) . ' WHERE parent_id = %d', (int) $id ) ) as $kid ) { // phpcs:ignore
		$max = max( $max, 1 + flxg_task_height( (int) $kid, $guard + 1 ) );
	}
	return $max;
}

/** Listenin projesindeki alt görev kademe sınırı. */
function flxg_list_depth_limit( $list_id ) {
	$list  = flxg_get_list( (int) $list_id );
	$space = $list ? flxg_get_space( $list['space_id'] ) : null;
	return $space ? (int) $space['subtask_depth'] : 3;
}

/**
 * $parent altına (kendi alt ağacıyla birlikte, yüksekliği $height) görev konabilir mi?
 * Hata: WP_Error, uygun: true.
 */
function flxg_check_parent( $parent, $list_id, $height = 0, $self = 0 ) {
	$parent = (int) $parent;
	if ( ! $parent ) {
		return true;
	}
	$row = flxg_get_task_row( $parent );
	if ( ! $row ) {
		return flxg_error( 'flxg_parent', __( 'Parent task not found.', 'flx-tasks' ), 404 );
	}
	if ( $self ) {
		// Görev kendi altına taşınamaz.
		for ( $p = $parent, $i = 0; $p && $i < 25; $i++ ) {
			if ( (int) $p === (int) $self ) {
				return flxg_error( 'flxg_parent', __( 'A task cannot be moved under its own subtask.', 'flx-tasks' ) );
			}
			$r = flxg_get_task_row( $p );
			$p = $r ? (int) $r['parent_id'] : 0;
		}
	}
	$limit = flxg_list_depth_limit( $list_id ?: $row['list_id'] );
	if ( flxg_task_depth( $parent ) + 1 + (int) $height > $limit ) {
		/* translators: %d: kademe sayısı */
		return flxg_error( 'flxg_depth', sprintf( __( 'Subtasks can be nested at most %d levels in this project. You can change the limit in project settings.', 'flx-tasks' ), $limit ) );
	}
	return true;
}

function flxg_create_task( array $d, $uid ) {
	$title = trim( (string) ( $d['title'] ?? '' ) );
	if ( '' === $title ) {
		return flxg_error( 'flxg_title', __( 'Task name cannot be empty.', 'flx-tasks' ) );
	}
	if ( ! empty( $d['parent_id'] ) ) {
		$ok = flxg_check_parent( $d['parent_id'], (int) ( $d['list_id'] ?? 0 ) );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
	}
	$d['creator_id'] = (int) $uid;
	$id              = flxg_create_task_row( $d );
	if ( ! $id ) {
		return flxg_error( 'flxg_list', __( 'List not found.', 'flx-tasks' ), 404 );
	}
	flxg_log( $id, 'created', array(), $uid );
	flxg_add_watcher( $id, $uid );
	$patch = array();
	foreach ( array( 'assignees', 'tags', 'fields', 'tag_names' ) as $k ) {
		if ( isset( $d[ $k ] ) ) {
			$patch[ $k ] = $d[ $k ];
		}
	}
	if ( $patch ) {
		flxg_update_task( $id, $patch, $uid, true );
	}
	do_action( 'flxg_task_created', $id, $uid );
	return $id;
}

/**
 * Görevi günceller. Desteklenen alanlar: title, description, status, priority, start, due, cover, list, position,
 * parent, assignees (int[]), add_assignees, remove_assignees, tags (int[]), tag_names (string[]), fields ({id: value}), archived.
 *
 * @return array|WP_Error güncel görev
 */
function flxg_update_task( $id, array $d, $uid, $quiet_create = false ) {
	global $wpdb;
	$row = flxg_get_task_row( $id );
	if ( ! $row ) {
		return flxg_error( 'flxg_task', __( 'Task not found.', 'flx-tasks' ), 404 );
	}
	$list = flxg_get_list( (int) $row['list_id'] );
	$set  = array();
	$logs = array();

	if ( isset( $d['list'] ) && (int) $d['list'] !== (int) $row['list_id'] ) {
		$new = flxg_get_list( (int) $d['list'] );
		if ( ! $new ) {
			return flxg_error( 'flxg_list', __( 'List not found.', 'flx-tasks' ), 404 );
		}
		$set['list_id'] = (int) $new['id'];
		if ( ! isset( $d['position'] ) ) {
			$set['position'] = flxg_next_position( $new['id'] );
		}
		// Durum yeni listede yoksa aynı türdeki ilk duruma geçer.
		$cur = flxg_status_info( $list, $row['status'] );
		if ( ! flxg_status_info( $new, $d['status'] ?? $row['status'] ) ) {
			$d['status'] = flxg_first_status( $new, $cur ? $cur['type'] : 'open' );
		}
		$logs[] = array( 'moved', array( 'from' => (int) $row['list_id'], 'to' => (int) $new['id'] ) );
		flxg_log_raw( (int) $id, (int) $row['list_id'], 'removed', array( 'to' => (int) $new['id'] ), $uid );
		$list = $new;
	}
	if ( isset( $d['title'] ) ) {
		$title = mb_substr( sanitize_text_field( $d['title'] ), 0, 500 );
		if ( '' !== $title && $title !== $row['title'] ) {
			$set['title'] = $title;
			$logs[]       = array( 'title', array( 'from' => $row['title'], 'to' => $title ) );
		}
	}
	if ( isset( $d['description'] ) ) {
		$desc = flxg_kses_desc( $d['description'] );
		if ( $desc !== (string) $row['description'] ) {
			$set['description'] = $desc;
			$logs[]             = array( 'description', array() );
		}
	}
	if ( isset( $d['status'] ) && $d['status'] !== $row['status'] ) {
		$info = flxg_status_info( $list, (string) $d['status'] );
		if ( $info ) {
			$set['status']       = $info['key'];
			$set['completed_at'] = 'done' === $info['type'] ? flxg_now() : null;
			if ( (int) $row['list_id'] === (int) $list['id'] ) {
				$logs[] = array( 'status', array( 'from' => $row['status'], 'to' => $info['key'] ) );
			}
		}
	}
	if ( isset( $d['priority'] ) && (int) $d['priority'] !== (int) $row['priority'] ) {
		$set['priority'] = max( 0, min( 4, (int) $d['priority'] ) );
		$logs[]          = array( 'priority', array( 'from' => (int) $row['priority'], 'to' => $set['priority'] ) );
	}
	foreach ( array( 'start' => 'start_date', 'due' => 'due_date' ) as $k => $col ) {
		if ( array_key_exists( $k, $d ) ) {
			$v = flxg_clean_date( $d[ $k ] );
			if ( $v !== $row[ $col ] ) {
				$set[ $col ] = $v;
				$logs[]      = array( $k, array( 'from' => $row[ $col ], 'to' => $v ) );
			}
		}
	}
	if ( isset( $d['cover'] ) && (int) $d['cover'] !== (int) $row['cover_id'] ) {
		$fid = (int) $d['cover'];
		if ( $fid ) {
			$f = flxg_get_file( $fid );
			if ( ! $f || ! flxg_is_image( $f['mime'] ) ) {
				return flxg_error( 'flxg_cover', __( 'Choose an image for the cover.', 'flx-tasks' ) );
			}
			if ( (int) $f['task_id'] !== (int) $id ) {
				$wpdb->update( flxg_table( 'files' ), array( 'task_id' => (int) $id, 'context' => 'task' ), array( 'id' => $fid ) );
			} elseif ( 'pending' === $f['context'] ) {
				$wpdb->update( flxg_table( 'files' ), array( 'context' => 'task' ), array( 'id' => $fid ) );
			}
		}
		$set['cover_id'] = $fid;
		$logs[]          = array( 'cover', array( 'to' => $fid ) );
	}
	if ( isset( $d['position'] ) ) {
		$set['position'] = (float) $d['position'];
	}
	if ( isset( $d['kind'] ) && flxg_clean_task_kind( $d['kind'] ) !== ( $row['kind'] ?? 'task' ) ) {
		$set['kind'] = flxg_clean_task_kind( $d['kind'] );
	}
	if ( isset( $d['parent'] ) && (int) $d['parent'] !== (int) $row['parent_id'] && (int) $d['parent'] !== (int) $id ) {
		$ok = flxg_check_parent( (int) $d['parent'], (int) $row['list_id'], flxg_task_height( $id ), $id );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$set['parent_id'] = (int) $d['parent'];
	}
	if ( isset( $d['archived'] ) && (int) (bool) $d['archived'] !== (int) $row['archived'] ) {
		$set['archived'] = $d['archived'] ? 1 : 0;
		$logs[]          = array( $d['archived'] ? 'archived' : 'unarchived', array() );
	}

	// Atananlar.
	$cur_assignees = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM ' . flxg_table( 'assignees' ) . ' WHERE task_id = %d', (int) $id ) ) ); // phpcs:ignore
	$want          = null;
	if ( isset( $d['assignees'] ) ) {
		$want = array_map( 'intval', (array) $d['assignees'] );
	}
	if ( isset( $d['add_assignees'] ) ) {
		$want = array_merge( null === $want ? $cur_assignees : $want, array_map( 'intval', (array) $d['add_assignees'] ) );
	}
	if ( isset( $d['remove_assignees'] ) ) {
		$want = array_diff( null === $want ? $cur_assignees : $want, array_map( 'intval', (array) $d['remove_assignees'] ) );
	}
	$newly = array();
	if ( null !== $want ) {
		$valid = flxg_users();
		$want  = array_values( array_unique( array_filter( $want, function ( $u ) use ( $valid ) {
			return isset( $valid[ $u ] );
		} ) ) );
		$add   = array_diff( $want, $cur_assignees );
		$rem   = array_diff( $cur_assignees, $want );
		foreach ( $add as $u ) {
			$wpdb->insert( flxg_table( 'assignees' ), array( 'task_id' => (int) $id, 'user_id' => (int) $u ) );
			flxg_add_watcher( $id, $u );
			$logs[]  = array( 'assign', array( 'user' => (int) $u ) );
			$newly[] = (int) $u;
		}
		foreach ( $rem as $u ) {
			$wpdb->delete( flxg_table( 'assignees' ), array( 'task_id' => (int) $id, 'user_id' => (int) $u ) );
			$logs[] = array( 'unassign', array( 'user' => (int) $u ) );
		}
		if ( $add || $rem ) {
			$set['updated_at'] = flxg_now();
		}
	}

	// Etiketler (proje içi). tag_names ile yoksa oluşturulur.
	if ( isset( $d['tags'] ) || isset( $d['tag_names'] ) ) {
		$space = (int) $list['space_id'];
		$want  = isset( $d['tags'] ) ? array_map( 'intval', (array) $d['tags'] ) : array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT tag_id FROM ' . flxg_table( 'task_tags' ) . ' WHERE task_id = %d', (int) $id ) ) ); // phpcs:ignore
		foreach ( (array) ( $d['tag_names'] ?? array() ) as $n ) {
			$tid = flxg_create_tag( $space, $n );
			if ( $tid ) {
				$want[] = $tid;
			}
		}
		$want = array_values( array_unique( array_filter( $want ) ) );
		$cur  = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT tag_id FROM ' . flxg_table( 'task_tags' ) . ' WHERE task_id = %d', (int) $id ) ) ); // phpcs:ignore
		foreach ( array_diff( $want, $cur ) as $tg ) {
			$wpdb->insert( flxg_table( 'task_tags' ), array( 'task_id' => (int) $id, 'tag_id' => (int) $tg ) );
			$logs[] = array( 'tag', array( 'tag' => (int) $tg ) );
		}
		foreach ( array_diff( $cur, $want ) as $tg ) {
			$wpdb->delete( flxg_table( 'task_tags' ), array( 'task_id' => (int) $id, 'tag_id' => (int) $tg ) );
			$logs[] = array( 'untag', array( 'tag' => (int) $tg ) );
		}
		$set['updated_at'] = flxg_now();
	}

	// Özel alanlar.
	if ( isset( $d['fields'] ) && is_array( $d['fields'] ) ) {
		$fields = array();
		foreach ( flxg_get_fields( (int) $list['id'] ) as $f ) {
			$fields[ $f['id'] ] = $f;
		}
		foreach ( $d['fields'] as $fid => $val ) {
			$fid = (int) preg_replace( '/\D/', '', (string) $fid );
			if ( ! isset( $fields[ $fid ] ) ) {
				continue;
			}
			$f     = $fields[ $fid ];
			$clean = flxg_clean_field_value( $f, $val );
			$old   = $wpdb->get_var( $wpdb->prepare( 'SELECT value FROM ' . flxg_table( 'values' ) . ' WHERE task_id = %d AND field_id = %d', (int) $id, $fid ) ); // phpcs:ignore
			if ( $old === $clean ) {
				continue;
			}
			$wpdb->delete( flxg_table( 'values' ), array( 'task_id' => (int) $id, 'field_id' => $fid ) );
			if ( null !== $clean ) {
				$wpdb->insert( flxg_table( 'values' ), array( 'task_id' => (int) $id, 'field_id' => $fid, 'value' => $clean ) );
			}
			if ( 'file' === $f['type'] ) {
				foreach ( flxg_json( $clean, array() ) as $file_id ) {
					$wpdb->update( flxg_table( 'files' ), array( 'task_id' => (int) $id, 'field_id' => $fid, 'context' => 'field' ), array( 'id' => (int) $file_id ) );
				}
			}
			$logs[] = array( 'field', array( 'field' => $fid, 'name' => $f['name'], 'type' => $f['type'], 'to' => flxg_field_out( $f['type'], $clean ) ) );
		}
		$set['updated_at'] = flxg_now();
	}

	if ( $set || $logs ) {
		$set['updated_at'] = flxg_now();
		$wpdb->update( flxg_table( 'tasks' ), $set, array( 'id' => (int) $id ) );
	}
	if ( ! $quiet_create ) {
		foreach ( $logs as $l ) {
			flxg_log( $id, $l[0], $l[1], $uid );
		}
	}
	flxg_after_task_change( (int) $id, $logs, $newly, $uid, $row );
	return flxg_get_task( $id );
}

/** Görevi siler (dosyalar, yorumlar, alt görevler dahil). */
function flxg_delete_task( $id, $uid = 0, $log = true ) {
	global $wpdb;
	$row = flxg_get_task_row( $id );
	if ( ! $row ) {
		return;
	}
	foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxg_table( 'tasks' ) . ' WHERE parent_id = %d', (int) $id ) ) as $sub ) { // phpcs:ignore
		flxg_delete_task( (int) $sub, $uid, $log );
	}
	foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxg_table( 'files' ) . ' WHERE task_id = %d', (int) $id ) ) as $fid ) { // phpcs:ignore
		flxg_delete_file( (int) $fid );
	}
	foreach ( array( 'assignees', 'watchers', 'task_tags', 'values', 'comments', 'notifications' ) as $t ) {
		$wpdb->delete( flxg_table( $t ), array( 'task_id' => (int) $id ) );
	}
	$wpdb->delete( flxg_table( 'activity' ), array( 'task_id' => (int) $id ) );
	$wpdb->delete( flxg_table( 'tasks' ), array( 'id' => (int) $id ) );
	if ( $log ) {
		flxg_log_raw( (int) $id, (int) $row['list_id'], 'deleted', array( 'title' => $row['title'] ), $uid );
	}
}

/* =========================================================================
 * İzleyiciler
 * ====================================================================== */

function flxg_add_watcher( $task_id, $uid ) {
	global $wpdb;
	if ( ! $uid ) {
		return;
	}
	$ex = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . flxg_table( 'watchers' ) . ' WHERE task_id = %d AND user_id = %d', (int) $task_id, (int) $uid ) ); // phpcs:ignore
	if ( ! $ex ) {
		$wpdb->insert( flxg_table( 'watchers' ), array( 'task_id' => (int) $task_id, 'user_id' => (int) $uid ) );
	}
}

function flxg_remove_watcher( $task_id, $uid ) {
	global $wpdb;
	$wpdb->delete( flxg_table( 'watchers' ), array( 'task_id' => (int) $task_id, 'user_id' => (int) $uid ) );
}

function flxg_watchers( $task_id ) {
	global $wpdb;
	return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM ' . flxg_table( 'watchers' ) . ' WHERE task_id = %d', (int) $task_id ) ) ); // phpcs:ignore
}

/* =========================================================================
 * Yorumlar
 * ====================================================================== */

function flxg_comment_out( $r, $files = array() ) {
	return array(
		'id'      => (int) $r['id'],
		'task'    => (int) $r['task_id'],
		'user'    => (int) $r['user_id'],
		'body'    => (string) $r['body'],
		'created' => flxg_iso( $r['created_at'] ),
		'edited'  => flxg_iso( $r['updated_at'] ),
		'files'   => $files,
	);
}

function flxg_get_comments( $task_id ) {
	global $wpdb;
	$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxg_table( 'comments' ) . ' WHERE task_id = %d ORDER BY id', (int) $task_id ), ARRAY_A ); // phpcs:ignore
	$files = array();
	foreach ( flxg_task_files( $task_id ) as $f ) {
		if ( $f['comment'] ) {
			$files[ $f['comment'] ][] = $f;
		}
	}
	$out = array();
	foreach ( (array) $rows as $r ) {
		$out[] = flxg_comment_out( $r, $files[ (int) $r['id'] ] ?? array() );
	}
	return $out;
}

function flxg_get_comment( $id ) {
	global $wpdb;
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxg_table( 'comments' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore
	return $r ? $r : null;
}

function flxg_clean_comment( $body ) {
	$body = (string) $body;
	$body = wp_check_invalid_utf8( $body );
	$body = str_replace( "\r\n", "\n", $body );
	$body = wp_strip_all_tags( $body, false );
	return trim( mb_substr( $body, 0, 20000 ) );
}

/** Yorumdaki @{id} bahsetmeleri. */
function flxg_mentions( $body ) {
	preg_match_all( '/@\{(\d+)\}/', (string) $body, $m );
	return array_values( array_unique( array_map( 'intval', $m[1] ?? array() ) ) );
}

/**
 * @return array|WP_Error yorum
 */
function flxg_add_comment( $task_id, $body, $file_ids, $uid ) {
	global $wpdb;
	$row = flxg_get_task_row( $task_id );
	if ( ! $row ) {
		return flxg_error( 'flxg_task', __( 'Task not found.', 'flx-tasks' ), 404 );
	}
	$body     = flxg_clean_comment( $body );
	$file_ids = array_values( array_filter( array_map( 'intval', (array) $file_ids ) ) );
	if ( '' === $body && ! $file_ids ) {
		return flxg_error( 'flxg_empty', __( 'Comment cannot be empty.', 'flx-tasks' ) );
	}
	$wpdb->insert(
		flxg_table( 'comments' ),
		array(
			'task_id'    => (int) $task_id,
			'user_id'    => (int) $uid,
			'body'       => $body,
			'created_at' => flxg_now(),
		)
	);
	$cid = (int) $wpdb->insert_id;
	foreach ( $file_ids as $fid ) {
		$f = flxg_get_file( $fid );
		if ( $f && (int) $f['user_id'] === (int) $uid && 'pending' === $f['context'] ) {
			$wpdb->update( flxg_table( 'files' ), array( 'task_id' => (int) $task_id, 'comment_id' => $cid, 'context' => 'comment' ), array( 'id' => $fid ) );
		}
	}
	$wpdb->update( flxg_table( 'tasks' ), array( 'updated_at' => flxg_now() ), array( 'id' => (int) $task_id ) );
	flxg_add_watcher( $task_id, $uid );
	flxg_after_comment( (int) $task_id, $cid, $body, $uid );
	$c = flxg_get_comment( $cid );
	$files = array();
	foreach ( flxg_task_files( $task_id ) as $f ) {
		if ( $f['comment'] === $cid ) {
			$files[] = $f;
		}
	}
	return flxg_comment_out( $c, $files );
}

function flxg_update_comment( $id, $body ) {
	global $wpdb;
	$body = flxg_clean_comment( $body );
	$wpdb->update( flxg_table( 'comments' ), array( 'body' => $body, 'updated_at' => flxg_now() ), array( 'id' => (int) $id ) );
	$c = flxg_get_comment( $id );
	$wpdb->update( flxg_table( 'tasks' ), array( 'updated_at' => flxg_now() ), array( 'id' => (int) $c['task_id'] ) );
	return $c;
}

function flxg_delete_comment( $id ) {
	global $wpdb;
	$c = flxg_get_comment( $id );
	foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxg_table( 'files' ) . ' WHERE comment_id = %d', (int) $id ) ) as $fid ) { // phpcs:ignore
		flxg_delete_file( (int) $fid );
	}
	$wpdb->delete( flxg_table( 'comments' ), array( 'id' => (int) $id ) );
	if ( $c ) {
		$wpdb->update( flxg_table( 'tasks' ), array( 'updated_at' => flxg_now() ), array( 'id' => (int) $c['task_id'] ) );
	}
}

/* =========================================================================
 * Etkinlik günlüğü
 * ====================================================================== */

function flxg_log( $task_id, $action, $data, $uid ) {
	global $wpdb;
	$list = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT list_id FROM ' . flxg_table( 'tasks' ) . ' WHERE id = %d', (int) $task_id ) ); // phpcs:ignore
	flxg_log_raw( $task_id, $list, $action, $data, $uid );
}

function flxg_log_raw( $task_id, $list_id, $action, $data, $uid ) {
	global $wpdb;
	$wpdb->insert(
		flxg_table( 'activity' ),
		array(
			'task_id'    => (int) $task_id,
			'list_id'    => (int) $list_id,
			'user_id'    => (int) $uid,
			'action'     => $action,
			'data'       => flxg_enc( $data ),
			'created_at' => flxg_now(),
		)
	);
}

function flxg_get_activity( $task_id ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxg_table( 'activity' ) . " WHERE task_id = %d AND action NOT IN ('removed','deleted') ORDER BY id", (int) $task_id ), ARRAY_A ); // phpcs:ignore
	$out  = array();
	foreach ( (array) $rows as $r ) {
		$out[] = array(
			'id'      => (int) $r['id'],
			'user'    => (int) $r['user_id'],
			'action'  => (string) $r['action'],
			'data'    => flxg_json( $r['data'] ),
			'created' => flxg_iso( $r['created_at'] ),
		);
	}
	return $out;
}

/** Görev detayı: görev + yorumlar + etkinlik + dosyalar + alt görevler. */
function flxg_task_detail( $id, $uid ) {
	global $wpdb;
	$t = flxg_get_task( $id, true );
	if ( ! $t ) {
		return null;
	}
	$t['comments_list'] = flxg_get_comments( $id );
	$t['activity']      = flxg_get_activity( $id );
	$t['attachments']   = array_values(
		array_filter(
			flxg_task_files( $id ),
			function ( $f ) {
				return 'pending' !== $f['context'];
			}
		)
	);
	$t['watchers']   = flxg_watchers( $id );
	$t['watching']   = in_array( (int) $uid, $t['watchers'], true );
	$t['children']   = flxg_query_tasks( array( 'lists' => array( $t['list'] ), 'parent' => $id, 'with_desc' => true ) );
	// Gündem maddelerinin ekleri (toplantı ekranında küçük önizleme).
	$t['childFiles'] = new stdClass();
	$agenda          = array();
	foreach ( $t['children'] as $c ) {
		if ( 'agenda' === $c['kind'] ) {
			$agenda[] = $c['id'];
		}
	}
	if ( $agenda ) {
		$cf = array();
		foreach ( (array) $wpdb->get_results( 'SELECT * FROM ' . flxg_table( 'files' ) . " WHERE context <> 'pending' AND task_id IN (" . flxg_ids_in( $agenda ) . ') ORDER BY id', ARRAY_A ) as $fr ) { // phpcs:ignore
			$cf[ (int) $fr['task_id'] ][] = flxg_file_row( $fr );
		}
		if ( $cf ) {
			$t['childFiles'] = $cf;
		}
	}
	$t['role']       = flxg_list_role( $t['list'], $uid );
	$parent          = $t['parent'] ? flxg_get_task_row( $t['parent'] ) : null;
	$t['parentInfo'] = $parent ? array( 'id' => (int) $parent['id'], 'title' => $parent['title'] ) : null;
	$t['depth']      = flxg_task_depth( $id );
	$t['depthLimit'] = flxg_list_depth_limit( $t['list'] );
	// Alt görevlerin altındakiler (detaydaki tabloda iç içe gösterilir).
	$t['subtree'] = array();
	$level        = wp_list_pluck( $t['children'], 'id' );
	global $wpdb;
	for ( $i = 0; $level && $i < 10 && count( $t['subtree'] ) < 300; $i++ ) {
		$next = array_map( 'intval', $wpdb->get_col( 'SELECT id FROM ' . flxg_table( 'tasks' ) . ' WHERE archived = 0 AND parent_id IN (' . flxg_ids_in( $level ) . ')' ) ); // phpcs:ignore
		if ( ! $next ) {
			break;
		}
		$rows          = flxg_query_tasks( array( 'lists' => array( $t['list'] ), 'ids' => $next, 'limit' => 300 ) );
		$t['subtree'] = array_merge( $t['subtree'], $rows );
		$level         = wp_list_pluck( $rows, 'id' );
	}
	return $t;
}
