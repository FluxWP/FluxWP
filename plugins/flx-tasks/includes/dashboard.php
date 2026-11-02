<?php
/**
 * Yönetici panosu: depolama (dosya sayısı ve boyutu, proje/liste/tür kırılımı), görev ve veritabanı istatistikleri.
 */

defined( 'ABSPATH' ) || exit;

function flxg_file_kind( $mime ) {
	if ( 0 === strpos( $mime, 'image/' ) ) {
		return 'image';
	}
	if ( 0 === strpos( $mime, 'video/' ) ) {
		return 'video';
	}
	if ( 0 === strpos( $mime, 'audio/' ) ) {
		return 'audio';
	}
	if ( 'application/pdf' === $mime ) {
		return 'pdf';
	}
	if ( preg_match( '/word|excel|spreadsheet|powerpoint|presentation|opendocument|msword|text\/|csv|rtf/', $mime ) ) {
		return 'doc';
	}
	if ( preg_match( '/zip|rar|7z|tar|gzip/', $mime ) ) {
		return 'archive';
	}
	return 'other';
}

function flxg_dashboard_data() {
	global $wpdb;
	$ft    = flxg_table( 'files' );
	$tt    = flxg_table( 'tasks' );
	$files = $wpdb->get_results( "SELECT f.id, f.task_id, f.mime, f.size, f.name, f.user_id, f.context, f.created_at, f.thumb, t.list_id FROM $ft f LEFT JOIN $tt t ON t.id = f.task_id", ARRAY_A ); // phpcs:ignore

	$base       = flxg_files_dir();
	$total      = 0;
	$thumbs     = 0;
	$by_kind    = array();
	$by_list    = array();
	$by_user    = array();
	$pending    = array( 'count' => 0, 'size' => 0 );
	$by_month   = array();
	foreach ( (array) $files as $f ) {
		$size   = (int) $f['size'];
		$total += $size;
		$kind   = flxg_file_kind( $f['mime'] );
		$by_kind[ $kind ]['count'] = ( $by_kind[ $kind ]['count'] ?? 0 ) + 1;
		$by_kind[ $kind ]['size']  = ( $by_kind[ $kind ]['size'] ?? 0 ) + $size;
		$l = (int) $f['list_id'];
		$by_list[ $l ]['count'] = ( $by_list[ $l ]['count'] ?? 0 ) + 1;
		$by_list[ $l ]['size']  = ( $by_list[ $l ]['size'] ?? 0 ) + $size;
		$u = (int) $f['user_id'];
		$by_user[ $u ]['count'] = ( $by_user[ $u ]['count'] ?? 0 ) + 1;
		$by_user[ $u ]['size']  = ( $by_user[ $u ]['size'] ?? 0 ) + $size;
		if ( 'pending' === $f['context'] ) {
			$pending['count']++;
			$pending['size'] += $size;
		}
		$mon = substr( (string) $f['created_at'], 0, 7 );
		$by_month[ $mon ] = ( $by_month[ $mon ] ?? 0 ) + $size;
		if ( $f['thumb'] && file_exists( $base . '/' . $f['thumb'] ) ) {
			$thumbs += (int) filesize( $base . '/' . $f['thumb'] );
		}
	}

	// Proje/liste kırılımı.
	$lists  = flxg_all_lists();
	$spaces = flxg_all_spaces();
	$counts = array();
	foreach ( (array) $wpdb->get_results( "SELECT list_id, COUNT(*) AS n FROM $tt GROUP BY list_id", ARRAY_A ) as $r ) { // phpcs:ignore
		$counts[ (int) $r['list_id'] ] = (int) $r['n'];
	}
	$list_rows = array();
	foreach ( $lists as $l ) {
		$list_rows[] = array(
			'id'    => $l['id'],
			'name'  => $l['name'],
			'space' => $spaces[ $l['space_id'] ]['name'] ?? '',
			'tasks' => $counts[ $l['id'] ] ?? 0,
			'files' => $by_list[ $l['id'] ]['count'] ?? 0,
			'size'  => $by_list[ $l['id'] ]['size'] ?? 0,
		);
	}
	usort(
		$list_rows,
		function ( $a, $b ) {
			return $b['size'] <=> $a['size'] ?: $b['tasks'] <=> $a['tasks'];
		}
	);
	$user_rows = array();
	foreach ( $by_user as $u => $v ) {
		$user_rows[] = array( 'user' => $u, 'name' => flxg_user_name( $u ), 'count' => $v['count'], 'size' => $v['size'] );
	}
	usort(
		$user_rows,
		function ( $a, $b ) {
			return $b['size'] <=> $a['size'];
		}
	);

	// En büyük dosyalar.
	$largest = array();
	foreach ( (array) $wpdb->get_results( "SELECT f.*, t.title, t.list_id FROM $ft f LEFT JOIN $tt t ON t.id = f.task_id ORDER BY f.size DESC LIMIT 15", ARRAY_A ) as $r ) { // phpcs:ignore
		$largest[] = array(
			'id'    => (int) $r['id'],
			'name'  => (string) $r['name'],
			'size'  => (int) $r['size'],
			'mime'  => (string) $r['mime'],
			'task'  => (int) $r['task_id'],
			'title' => (string) ( $r['title'] ?? '' ),
			'list'  => $lists[ (int) $r['list_id'] ]['name'] ?? '',
			'user'  => flxg_user_name( (int) $r['user_id'] ),
			'created' => flxg_iso( $r['created_at'] ),
		);
	}

	// Görev istatistikleri.
	$open = 0;
	$done = 0;
	$overdue = 0;
	$today = flxg_today();
	foreach ( (array) $wpdb->get_results( "SELECT list_id, status, due_date FROM $tt WHERE archived = 0", ARRAY_A ) as $r ) { // phpcs:ignore
		$l  = $lists[ (int) $r['list_id'] ] ?? null;
		$st = $l ? flxg_status_info( $l, $r['status'] ) : null;
		if ( $st && 'done' === $st['type'] ) {
			$done++;
		} else {
			$open++;
			if ( $r['due_date'] && substr( $r['due_date'], 0, 10 ) < $today ) {
				$overdue++;
			}
		}
	}
	$since = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
	$created30   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tt WHERE created_at >= %s", $since ) ); // phpcs:ignore
	$completed30 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tt WHERE completed_at IS NOT NULL AND completed_at >= %s", $since ) ); // phpcs:ignore
	$active30    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT user_id) FROM ' . flxg_table( 'activity' ) . ' WHERE created_at >= %s', $since ) ); // phpcs:ignore

	// Veritabanı tabloları.
	$tables = array();
	$names  = array( 'tasks', 'comments', 'activity', 'notifications', 'files', 'values', 'assignees', 'watchers', 'task_tags', 'tags', 'fields', 'views', 'lists', 'folders', 'spaces', 'members', 'docs', 'doc_pages', 'doc_blocks' );
	$sizes  = array();
	if ( defined( 'DATABASE_TYPE' ) && 'sqlite' === DATABASE_TYPE ) {
		$sizes = array();
	} else {
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT TABLE_NAME AS n, (DATA_LENGTH + INDEX_LENGTH) AS s FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME LIKE %s', DB_NAME, $wpdb->esc_like( $wpdb->prefix . 'flxg_' ) . '%' ), ARRAY_A ) as $r ) { // phpcs:ignore
			$sizes[ $r['n'] ] = (int) $r['s'];
		}
	}
	$db_total = 0;
	foreach ( $names as $n ) {
		$t        = flxg_table( $n );
		$rows     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" ); // phpcs:ignore
		$size     = $sizes[ $t ] ?? null;
		$db_total += (int) $size;
		$tables[] = array( 'name' => $n, 'rows' => $rows, 'size' => $size );
	}

	$disk_free = function_exists( 'disk_free_space' ) ? @disk_free_space( $base ) : false; // phpcs:ignore

	ksort( $by_month );
	return array(
		'storage'  => array(
			'total'    => $total,
			'thumbs'   => $thumbs,
			'count'    => count( (array) $files ),
			'kinds'    => $by_kind,
			'pending'  => $pending,
			'months'   => array_slice( $by_month, -12, 12, true ),
			'free'     => false === $disk_free ? null : (float) $disk_free,
			'limit'    => flxg_max_upload_bytes(),
			'uploads'  => get_transient( 'flxg_uploads_size' ),
		),
		'lists'    => $list_rows,
		'users'    => array_slice( $user_rows, 0, 20 ),
		'largest'  => $largest,
		'tasks'    => array(
			'open'        => $open,
			'done'        => $done,
			'overdue'     => $overdue,
			'created30'   => $created30,
			'completed30' => $completed30,
			'active30'    => $active30,
			'comments'    => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . flxg_table( 'comments' ) ), // phpcs:ignore
			'spaces'      => count( $spaces ),
			'lists'       => count( $lists ),
			'users'       => count( flxg_users() ),
		),
		'db'       => array(
			'tables' => $tables,
			'total'  => $sizes ? $db_total : null,
		),
		'ai'       => get_option( 'flxg_ai_usage', null ),
	);
}

/** Tüm wp-content/uploads boyutu (zaman sınırlı tarama, 6 saat önbellek). */
function flxg_scan_uploads() {
	$up    = wp_upload_dir( null, false );
	$dir   = $up['basedir'];
	$start = microtime( true );
	$size  = 0;
	$count = 0;
	$done  = true;
	try {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			if ( $f->isFile() ) {
				$size += $f->getSize();
				$count++;
			}
			if ( 0 === $count % 500 && microtime( true ) - $start > 12 ) {
				$done = false;
				break;
			}
		}
	} catch ( Exception $e ) {
		$done = false;
	}
	$res = array(
		'size'     => $size,
		'count'    => $count,
		'complete' => $done,
		'at'       => gmdate( 'c' ),
	);
	set_transient( 'flxg_uploads_size', $res, 6 * HOUR_IN_SECONDS );
	return $res;
}
