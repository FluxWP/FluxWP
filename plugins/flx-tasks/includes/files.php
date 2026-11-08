<?php
/**
 * Dosyalar: uploads/flx-tasks altında, doğrudan erişime kapalı; yetkili REST isteğiyle sunulur.
 * Görsellerin küçük bir önizlemesi (kart kapağı, yorum küçük resmi) yüklemede üretilir.
 */

defined( 'ABSPATH' ) || exit;

function flxg_is_image( $mime ) {
	return in_array( $mime, array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ), true );
}

function flxg_file_row( $r ) {
	return array(
		'id'      => (int) $r['id'],
		'task'    => (int) $r['task_id'],
		'comment' => (int) $r['comment_id'],
		'field'   => (int) $r['field_id'],
		'doc'     => (int) ( $r['doc_id'] ?? 0 ),
		'context' => (string) $r['context'],
		'user'    => (int) $r['user_id'],
		'name'    => (string) $r['name'],
		'mime'    => (string) $r['mime'],
		'size'    => (int) $r['size'],
		'image'   => flxg_is_image( $r['mime'] ),
		'thumb'   => '' !== $r['thumb'],
		'w'       => (int) $r['width'],
		'h'       => (int) $r['height'],
		'created' => flxg_iso( $r['created_at'] ),
	);
}

function flxg_get_file( $id ) {
	global $wpdb;
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxg_table( 'files' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore
	return $r ? $r : null;
}

function flxg_task_files( $task_id ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxg_table( 'files' ) . ' WHERE task_id = %d ORDER BY id', (int) $task_id ), ARRAY_A ); // phpcs:ignore
	return array_map( 'flxg_file_row', (array) $rows );
}

function flxg_max_upload_bytes() {
	$mb = max( 1, (int) flxg_setting( 'max_upload_mb' ) );
	return min( $mb * MB_IN_BYTES, (int) wp_max_upload_size() );
}

/**
 * Yüklenen dosyayı kaydeder.
 *
 * @param array $file   $_FILES öğesi.
 * @param int   $task_id 0 ise yalnız kullanıcıya ait "bekleyen" dosya.
 * @return array|WP_Error dosya
 */
function flxg_store_upload( $file, $task_id, $uid, $context = 'pending' ) {
	global $wpdb;
	if ( empty( $file['tmp_name'] ) || ! empty( $file['error'] ) ) {
		$code = (int) ( $file['error'] ?? 0 );
		if ( in_array( $code, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ) {
			/* translators: %s: size */
			return flxg_error( 'flxg_size', sprintf( __( 'File is too large. The maximum upload size is %s.', 'flx-tasks' ), size_format( flxg_max_upload_bytes() ) ), 413 );
		}
		return flxg_error( 'flxg_upload', __( 'Could not upload the file.', 'flx-tasks' ) );
	}
	if ( (int) $file['size'] > flxg_max_upload_bytes() ) {
		/* translators: %s: size */
		return flxg_error( 'flxg_size', sprintf( __( 'File is too large. The maximum upload size is %s.', 'flx-tasks' ), size_format( flxg_max_upload_bytes() ) ), 413 );
	}
	$name  = sanitize_file_name( wp_basename( (string) $file['name'] ) );
	$check = wp_check_filetype_and_ext( $file['tmp_name'], $name );
	if ( empty( $check['type'] ) ) {
		return flxg_error( 'flxg_type', __( 'This file type is not allowed.', 'flx-tasks' ), 415 );
	}
	if ( ! empty( $check['proper_filename'] ) ) {
		$name = $check['proper_filename'];
	}
	$base = flxg_files_dir();
	$sub  = gmdate( 'Y/m' );
	wp_mkdir_p( $base . '/' . $sub );
	$stored = $sub . '/' . wp_generate_password( 12, false ) . '-' . $name;
	if ( ! @move_uploaded_file( $file['tmp_name'], $base . '/' . $stored ) ) { // phpcs:ignore
		if ( ! @rename( $file['tmp_name'], $base . '/' . $stored ) ) { // phpcs:ignore
			return flxg_error( 'flxg_move', __( 'Could not save the file.', 'flx-tasks' ), 500 );
		}
	}
	$mime  = $check['type'];
	$thumb = '';
	$w     = 0;
	$h     = 0;
	if ( flxg_is_image( $mime ) ) {
		$size = @getimagesize( $base . '/' . $stored ); // phpcs:ignore
		if ( $size ) {
			$w = (int) $size[0];
			$h = (int) $size[1];
		}
		$thumb = flxg_make_thumb( $base . '/' . $stored, $stored );
	}
	$wpdb->insert(
		flxg_table( 'files' ),
		array(
			'task_id'    => (int) $task_id,
			'context'    => $context,
			'user_id'    => (int) $uid,
			'name'       => mb_substr( $name, 0, 255 ),
			'path'       => $stored,
			'thumb'      => $thumb,
			'mime'       => $mime,
			'size'       => (int) filesize( $base . '/' . $stored ),
			'width'      => $w,
			'height'     => $h,
			'created_at' => flxg_now(),
		)
	);
	$id = (int) $wpdb->insert_id;
	if ( $task_id && 'task' === $context ) {
		flxg_log( $task_id, 'attach', array( 'file' => $id, 'name' => $name ), $uid );
		$wpdb->update( flxg_table( 'tasks' ), array( 'updated_at' => flxg_now() ), array( 'id' => (int) $task_id ) );
	}
	return flxg_file_row( flxg_get_file( $id ) );
}

/** 640px genişlikte önizleme (JPEG/PNG/WebP). */
function flxg_make_thumb( $abs, $stored ) {
	$ed = wp_get_image_editor( $abs );
	if ( is_wp_error( $ed ) ) {
		return '';
	}
	$s = $ed->get_size();
	if ( ( $s['width'] ?? 0 ) <= 640 && ( $s['height'] ?? 0 ) <= 640 ) {
		return '';
	}
	$ed->resize( 640, 640, false );
	$ed->set_quality( 78 );
	$info = pathinfo( $stored );
	$rel  = $info['dirname'] . '/' . $info['filename'] . '-thumb.' . ( 'png' === strtolower( $info['extension'] ?? '' ) ? 'png' : 'jpg' );
	$res  = $ed->save( flxg_files_dir() . '/' . $rel );
	return is_wp_error( $res ) ? '' : $rel;
}

function flxg_delete_file( $id ) {
	global $wpdb;
	$f = flxg_get_file( $id );
	if ( ! $f ) {
		return;
	}
	$base = flxg_files_dir();
	foreach ( array( $f['path'], $f['thumb'] ) as $p ) {
		if ( $p && false === strpos( $p, '..' ) && file_exists( $base . '/' . $p ) ) {
			wp_delete_file( $base . '/' . $p );
		}
	}
	$wpdb->delete( flxg_table( 'files' ), array( 'id' => (int) $id ) );
	$wpdb->update( flxg_table( 'tasks' ), array( 'cover_id' => 0 ), array( 'cover_id' => (int) $id ) );
}

/** Dosyayı tarayıcıya gönderir. */
function flxg_send_file( $f, $size = '' ) {
	$base = flxg_files_dir();
	$rel  = 'thumb' === $size && $f['thumb'] ? $f['thumb'] : $f['path'];
	$abs  = $base . '/' . $rel;
	if ( false !== strpos( $rel, '..' ) || ! is_readable( $abs ) ) {
		status_header( 404 );
		exit;
	}
	$mime   = 'thumb' === $size && $f['thumb'] ? ( substr( $rel, -4 ) === '.png' ? 'image/png' : 'image/jpeg' ) : $f['mime'];
	$inline = flxg_is_image( $mime ) || 'application/pdf' === $mime || 0 === strpos( $mime, 'video/' ) || 0 === strpos( $mime, 'audio/' );
	while ( ob_get_level() ) {
		ob_end_clean();
	}
	header( 'Content-Type: ' . $mime );
	header( 'Content-Length: ' . filesize( $abs ) );
	header( 'Content-Disposition: ' . ( $inline && empty( $_GET['download'] ) ? 'inline' : 'attachment' ) . "; filename*=UTF-8''" . rawurlencode( $f['name'] ) ); // phpcs:ignore
	header( 'Cache-Control: private, max-age=604800' );
	header( 'X-Content-Type-Options: nosniff' );
	readfile( $abs ); // phpcs:ignore
	exit;
}

/** Bir günden eski, hiçbir yere bağlanmamış dosyaları temizler. */
add_action(
	'flxg_daily',
	function () {
		global $wpdb;
		$old = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxg_table( 'files' ) . " WHERE context = 'pending' AND created_at < %s", $old ) ) as $id ) { // phpcs:ignore
			flxg_delete_file( (int) $id );
		}
	}
);
