<?php
/**
 * Veri: üretimler (items), klasörler, kişiler, referanslar, şablonlar.
 * Görünürlük: herkes kendi kayıtlarını ve "paylaşılan" klasör/kişi/şablonları görür; flxst_manage hepsini.
 */

defined( 'ABSPATH' ) || exit;

function flxst_json( $v ) {
	return wp_json_encode( $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

function flxst_dec( $s ) {
	$v = json_decode( (string) $s, true );
	return is_array( $v ) ? $v : array();
}

/* ---------------------------------------------------------------------------
 * Klasörler
 * ------------------------------------------------------------------------- */

function flxst_folders( $user_id ) {
	global $wpdb;
	$t = flxst_table( 'folders' );
	if ( user_can( $user_id, FLXST_CAP_MANAGE ) ) {
		$rows = $wpdb->get_results( "SELECT * FROM {$t} ORDER BY name", ARRAY_A ); // phpcs:ignore WordPress.DB
	} else {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE user_id = %d OR shared = 1 ORDER BY name", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}
	return array_map( 'flxst_format_folder', (array) $rows );
}

function flxst_format_folder( $r ) {
	return array(
		'id'      => (int) $r['id'],
		'parent'  => (int) $r['parent_id'],
		'name'    => $r['name'],
		'shared'  => (bool) $r['shared'],
		'owner'   => (int) $r['user_id'],
		'created' => (int) $r['created'],
	);
}

function flxst_folder( $id ) {
	global $wpdb;
	$t = flxst_table( 'folders' );
	return $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ) : null; // phpcs:ignore WordPress.DB
}

/** Klasöre kayıt koyabilir mi (sahibi, paylaşılan ya da yönetici). 0 = kök. */
function flxst_folder_writable( $id, $user_id ) {
	if ( ! $id ) {
		return true;
	}
	$f = flxst_folder( $id );
	return $f && ( (int) $f['user_id'] === (int) $user_id || (int) $f['shared'] || user_can( $user_id, FLXST_CAP_MANAGE ) );
}

function flxst_folder_owned( $id, $user_id ) {
	$f = flxst_folder( $id );
	return $f && ( (int) $f['user_id'] === (int) $user_id || user_can( $user_id, FLXST_CAP_MANAGE ) );
}

/* ---------------------------------------------------------------------------
 * Üretimler
 * ------------------------------------------------------------------------- */

function flxst_item( $id ) {
	global $wpdb;
	$t = flxst_table( 'items' );
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
}

function flxst_item_visible( $row, $user_id ) {
	if ( ! $row ) {
		return false;
	}
	if ( (int) $row['user_id'] === (int) $user_id || user_can( $user_id, FLXST_CAP_MANAGE ) ) {
		return true;
	}
	$f = flxst_folder( (int) $row['folder_id'] );
	return $f && (int) $f['shared'];
}

function flxst_item_editable( $row, $user_id ) {
	return $row && ( (int) $row['user_id'] === (int) $user_id || user_can( $user_id, FLXST_CAP_MANAGE ) );
}

function flxst_format_item( $r ) {
	$p = flxst_dec( $r['params'] );
	$u = (int) $r['user_id'];
	return array(
		'id'         => (int) $r['id'],
		'kind'       => $r['kind'],
		'state'      => $r['state'],
		'job'        => $r['job'],
		'batch'      => $r['batch'],
		'parent'     => (int) $r['parent_id'],
		'folder'     => (int) $r['folder_id'],
		'title'      => $r['title'],
		'prompt'     => $r['prompt'],
		'model'      => $r['model'],
		'provider'   => $r['provider'],
		'params'     => $p,
		'url'        => $r['url'],
		'thumb'      => $r['thumb'] ? $r['thumb'] : $r['url'],
		'mime'       => $r['mime'],
		'width'      => (int) $r['width'],
		'height'     => (int) $r['height'],
		'bytes'      => (int) $r['bytes'],
		'cost'       => (float) $r['cost'],
		'error'      => $r['error'],
		'favorite'   => (bool) $r['favorite'],
		'attachment' => (int) $r['attachment_id'],
		'owner'      => $u,
		'owner_name' => flxst_user_name( $u ),
		'created'    => (int) $r['created'],
		'updated'    => (int) $r['updated'],
	);
}

function flxst_user_name( $id ) {
	static $c = array();
	if ( ! isset( $c[ $id ] ) ) {
		$u        = get_userdata( $id );
		$c[ $id ] = $u ? $u->display_name : '';
	}
	return $c[ $id ];
}

/**
 * Liste. $q: folder (int|'all'), kind, fav, search, state, owner ('me'|'all'), before (id), limit.
 */
function flxst_items_query( $user_id, array $q ) {
	global $wpdb;
	$t     = flxst_table( 'items' );
	$f     = flxst_table( 'folders' );
	$where = array( '1=1' );
	$args  = array();
	$all   = user_can( $user_id, FLXST_CAP_MANAGE ) && 'all' === ( $q['owner'] ?? 'me' );
	if ( ! $all ) {
		$where[] = "( i.user_id = %d OR i.folder_id IN ( SELECT id FROM {$f} WHERE shared = 1 ) )";
		$args[]  = $user_id;
	}
	$folder = $q['folder'] ?? 'all';
	if ( 'all' !== $folder && '' !== $folder ) {
		$where[] = 'i.folder_id = %d';
		$args[]  = (int) $folder;
	}
	if ( in_array( $q['kind'] ?? '', array( 'image', 'video' ), true ) ) {
		$where[] = 'i.kind = %s';
		$args[]  = $q['kind'];
	}
	if ( ! empty( $q['fav'] ) ) {
		$where[] = 'i.favorite = 1';
	}
	if ( ! empty( $q['batch'] ) ) {
		$where[] = 'i.batch = %s';
		$args[]  = (string) $q['batch'];
	}
	$s = trim( (string) ( $q['search'] ?? '' ) );
	if ( '' !== $s ) {
		$like    = '%' . $wpdb->esc_like( $s ) . '%';
		$where[] = '( i.prompt LIKE %s OR i.title LIKE %s OR i.model LIKE %s )';
		array_push( $args, $like, $like, $like );
	}
	if ( ! empty( $q['before'] ) ) {
		$where[] = 'i.id < %d';
		$args[]  = (int) $q['before'];
	}
	$limit  = max( 1, min( 200, (int) ( $q['limit'] ?? 60 ) ) );
	$page   = max( 1, (int) ( $q['page'] ?? 1 ) );
	$offset = ( $page - 1 ) * $limit;
	$sql    = "SELECT i.* FROM {$t} i WHERE " . implode( ' AND ', $where ) . " ORDER BY i.id DESC LIMIT {$limit} OFFSET {$offset}";
	$rows   = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB
	return array_map( 'flxst_format_item', (array) $rows );
}

function flxst_item_update( $id, array $data ) {
	global $wpdb;
	$data['updated'] = time();
	$wpdb->update( flxst_table( 'items' ), $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
}

function flxst_item_insert( array $data ) {
	global $wpdb;
	$now  = time();
	$data = wp_parse_args(
		$data,
		array(
			'user_id'  => get_current_user_id(),
			'kind'     => 'image',
			'state'    => 'queued',
			'prompt'   => '',
			'params'   => '{}',
			'url'      => '',
			'path'     => '',
			'thumb'    => '',
			'error'    => '',
			'created'  => $now,
			'updated'  => $now,
		)
	);
	$wpdb->insert( flxst_table( 'items' ), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	return (int) $wpdb->insert_id;
}

/** Siler; dosyalar Flux AI'nin klasöründe kalır, yalnız küçük resim silinir. */
function flxst_item_delete( $row ) {
	global $wpdb;
	if ( ! empty( $row['thumb'] ) ) {
		$p = flxst_url_to_path( $row['thumb'] );
		if ( $p && 0 === strpos( $p, flxst_upload_dir() ) && is_file( $p ) ) {
			wp_delete_file( $p );
		}
	}
	if ( ! empty( $row['path'] ) && 0 === strpos( (string) $row['path'], flxst_upload_dir() ) && is_file( $row['path'] ) ) {
		wp_delete_file( $row['path'] );
	}
	$wpdb->delete( flxst_table( 'items' ), array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
}

/* ---------------------------------------------------------------------------
 * Kişiler ve referanslar
 * ------------------------------------------------------------------------- */

function flxst_people( $user_id ) {
	global $wpdb;
	$t   = flxst_table( 'people' );
	$shr = (int) flxst_settings()['shared_people'];
	$sql = user_can( $user_id, FLXST_CAP_MANAGE ) || $shr
		? "SELECT * FROM {$t} ORDER BY name"
		: $wpdb->prepare( "SELECT * FROM {$t} WHERE user_id = %d OR shared = 1 ORDER BY name", $user_id );
	$out = array();
	foreach ( (array) $wpdb->get_results( $sql, ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
		$out[] = array(
			'id'     => (int) $r['id'],
			'name'   => $r['name'],
			'owner'  => (int) $r['user_id'],
			'photos' => flxst_refs( $user_id, array( 'person' => (int) $r['id'] ) ),
		);
	}
	return $out;
}

function flxst_person( $id ) {
	global $wpdb;
	$t = flxst_table( 'people' );
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
}

function flxst_person_visible( $p, $user_id ) {
	return $p && ( (int) $p['user_id'] === (int) $user_id || (int) $p['shared'] || (int) flxst_settings()['shared_people'] || user_can( $user_id, FLXST_CAP_MANAGE ) );
}

function flxst_format_ref( $r ) {
	return array(
		'id'     => (int) $r['id'],
		'person' => (int) $r['person_id'],
		'name'   => $r['name'],
		'role'   => $r['role'],
		'url'    => $r['url'],
		'thumb'  => $r['thumb'] ? $r['thumb'] : $r['url'],
		'mime'   => $r['mime'],
		'width'  => (int) $r['width'],
		'height' => (int) $r['height'],
		'saved'  => (bool) $r['saved'],
	);
}

/** $q: person (int) | saved (bool). Kişi fotoğrafları kişiyle görünür; kayıtlı referanslar sahibine. */
function flxst_refs( $user_id, array $q = array() ) {
	global $wpdb;
	$t = flxst_table( 'refs' );
	if ( ! empty( $q['person'] ) ) {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE person_id = %d ORDER BY id", (int) $q['person'] ), ARRAY_A ); // phpcs:ignore WordPress.DB
	} else {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE user_id = %d AND person_id = 0 AND saved = 1 ORDER BY id DESC LIMIT 200", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}
	return array_map( 'flxst_format_ref', (array) $rows );
}

function flxst_ref( $id ) {
	global $wpdb;
	$t = flxst_table( 'refs' );
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
}

function flxst_ref_visible( $r, $user_id ) {
	if ( ! $r ) {
		return false;
	}
	if ( (int) $r['user_id'] === (int) $user_id || user_can( $user_id, FLXST_CAP_MANAGE ) ) {
		return true;
	}
	return (int) $r['person_id'] && flxst_person_visible( flxst_person( (int) $r['person_id'] ), $user_id );
}

/* ---------------------------------------------------------------------------
 * Şablonlar
 * ------------------------------------------------------------------------- */

function flxst_templates( $user_id ) {
	global $wpdb;
	$t    = flxst_table( 'templates' );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE user_id = %d OR shared = 1 ORDER BY name", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	$out  = array();
	foreach ( (array) $rows as $r ) {
		$out[] = array(
			'id'     => (int) $r['id'],
			'name'   => $r['name'],
			'prompt' => $r['prompt'],
			'params' => flxst_dec( $r['params'] ),
			'shared' => (bool) $r['shared'],
			'owner'  => (int) $r['user_id'],
		);
	}
	return $out;
}
