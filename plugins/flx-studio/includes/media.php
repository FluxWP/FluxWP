<?php
/**
 * Dosyalar: referans yükleme, küçük resimler, Flux AI'ye referans verme, medya kitaplığına aktarma.
 * Üretilen dosyalar Flux AI'nin klasöründe (uploads/flxai-media) kalır; Stüdyo yalnız küçük resim üretir.
 */

defined( 'ABSPATH' ) || exit;

const FLXST_IMAGE_MIMES = array( 'image/png', 'image/jpeg', 'image/webp', 'image/gif' );
const FLXST_MEDIA_MIMES = array( 'image/png', 'image/jpeg', 'image/webp', 'image/gif', 'video/mp4', 'video/webm', 'video/quicktime', 'audio/mpeg', 'audio/wav', 'audio/mp4', 'audio/x-m4a' );

function flxst_upload_dir() {
	$u = wp_upload_dir( null, false );
	return trailingslashit( $u['basedir'] ) . 'flx-studio';
}

function flxst_upload_url() {
	$u = wp_upload_dir( null, false );
	return trailingslashit( $u['baseurl'] ) . 'flx-studio';
}

function flxst_ensure_dir( $sub ) {
	$dir = flxst_upload_dir() . '/' . $sub;
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	foreach ( array( flxst_upload_dir(), $dir ) as $d ) {
		if ( ! file_exists( $d . '/index.php' ) ) {
			file_put_contents( $d . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}
	return $dir;
}

/** uploads içindeki adresi dosya yoluna çevirir (dışarıdaki adres için ''). */
function flxst_url_to_path( $url ) {
	$u    = wp_upload_dir( null, false );
	$base = set_url_scheme( trailingslashit( $u['baseurl'] ), 'http' );
	$url  = set_url_scheme( (string) $url, 'http' );
	if ( 0 !== strpos( $url, $base ) ) {
		return '';
	}
	$rel = rawurldecode( substr( $url, strlen( $base ) ) );
	if ( false !== strpos( $rel, '..' ) ) {
		return '';
	}
	return trailingslashit( $u['basedir'] ) . $rel;
}

function flxst_path_to_url( $path ) {
	$u = wp_upload_dir( null, false );
	if ( 0 !== strpos( $path, trailingslashit( $u['basedir'] ) ) ) {
		return '';
	}
	return trailingslashit( $u['baseurl'] ) . str_replace( '%2F', '/', rawurlencode( substr( $path, strlen( trailingslashit( $u['basedir'] ) ) ) ) );
}

/**
 * Küçük resim (en uzun kenar 640 px, JPEG/WebP). Video ve hata durumunda ''.
 */
function flxst_make_thumb( $src_path, $key ) {
	if ( ! $src_path || ! is_file( $src_path ) ) {
		return '';
	}
	$mime = wp_check_filetype( $src_path )['type'];
	if ( ! in_array( $mime, FLXST_IMAGE_MIMES, true ) ) {
		return '';
	}
	$ed = wp_get_image_editor( $src_path );
	if ( is_wp_error( $ed ) ) {
		return '';
	}
	$size = $ed->get_size();
	if ( max( (int) $size['width'], (int) $size['height'] ) <= 640 ) {
		return '';
	}
	$ed->resize( 640, 640, false );
	$ed->set_quality( 82 );
	$dir  = flxst_ensure_dir( 'thumbs/' . gmdate( 'Y/m' ) );
	$dest = $dir . '/' . sanitize_file_name( $key ) . '.jpg';
	$res  = $ed->save( $dest, 'image/jpeg' );
	return is_wp_error( $res ) ? '' : flxst_path_to_url( $res['path'] );
}

/**
 * Dosyayı Stüdyo klasörüne kaydeder. $src: [ 'tmp' => yol ] ya da [ 'data' => ikili ], 'name'.
 * @return array|WP_Error { path, url, mime, width, height, bytes }
 */
function flxst_store_file( array $src, $sub, array $allowed = FLXST_IMAGE_MIMES, $max_mb = 25 ) {
	$name  = sanitize_file_name( (string) ( $src['name'] ?? 'dosya' ) );
	$bytes = isset( $src['data'] ) ? strlen( $src['data'] ) : ( isset( $src['tmp'] ) && is_file( $src['tmp'] ) ? filesize( $src['tmp'] ) : 0 );
	if ( ! $bytes ) {
		return new WP_Error( 'flxst_file', __( 'The file is empty.', 'flx-studio' ), array( 'status' => 400 ) );
	}
	if ( $bytes > $max_mb * MB_IN_BYTES ) {
		/* translators: %d: megabytes */
		return new WP_Error( 'flxst_file', sprintf( __( 'The file exceeds the %d MB limit.', 'flx-studio' ), $max_mb ), array( 'status' => 413 ) );
	}
	$tmp = isset( $src['tmp'] ) ? $src['tmp'] : wp_tempnam( $name );
	if ( isset( $src['data'] ) ) {
		file_put_contents( $tmp, $src['data'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	$check = wp_check_filetype_and_ext( $tmp, $name );
	$mime  = $check['type'] ? $check['type'] : ( function_exists( 'mime_content_type' ) ? mime_content_type( $tmp ) : '' );
	if ( ! in_array( $mime, $allowed, true ) ) {
		if ( isset( $src['data'] ) ) {
			wp_delete_file( $tmp );
		}
		return new WP_Error( 'flxst_file', __( 'This file type is not supported.', 'flx-studio' ), array( 'status' => 415 ) );
	}
	$ext  = array_search( $mime, array( 'png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif', 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', 'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'm4a' => 'audio/mp4' ), true );
	$ext  = $ext ? $ext : 'bin';
	$dir  = flxst_ensure_dir( $sub . '/' . gmdate( 'Y/m' ) );
	$dest = $dir . '/' . strtolower( wp_generate_password( 20, false ) ) . '.' . $ext;
	$ok   = isset( $src['data'] ) ? rename( $tmp, $dest ) : ( is_uploaded_file( $tmp ) ? move_uploaded_file( $tmp, $dest ) : copy( $tmp, $dest ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! $ok ) {
		return new WP_Error( 'flxst_file', __( 'The file could not be saved.', 'flx-studio' ), array( 'status' => 500 ) );
	}
	$w = 0;
	$h = 0;
	if ( 0 === strpos( $mime, 'image/' ) ) {
		$sz = @getimagesize( $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( $sz ) {
			$w = (int) $sz[0];
			$h = (int) $sz[1];
		}
	}
	return array(
		'path'   => $dest,
		'url'    => flxst_path_to_url( $dest ),
		'mime'   => $mime,
		'width'  => $w,
		'height' => $h,
		'bytes'  => (int) $bytes,
	);
}

/** data:image/png;base64,… → [ data, mime ] ya da null. */
function flxst_parse_data_url( $s, $max_mb = 25 ) {
	if ( ! is_string( $s ) || ! preg_match( '#^data:([a-z]+/[a-z0-9.+-]+);base64,#i', $s, $m ) ) {
		return null;
	}
	if ( strlen( $s ) > $max_mb * MB_IN_BYTES * 1.4 ) {
		return null;
	}
	$bin = base64_decode( substr( $s, strlen( $m[0] ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	return false === $bin ? null : array( $bin, strtolower( $m[1] ) );
}

/**
 * Flux AI'ye verilecek referans: https sitede herkese açık adres, değilse data URL (yerel geliştirme).
 */
function flxst_ai_input( $url, $path ) {
	if ( 0 === strpos( (string) $url, 'https://' ) ) {
		return $url;
	}
	if ( $path && is_file( $path ) && filesize( $path ) <= 20 * MB_IN_BYTES ) {
		$mime = wp_check_filetype( $path )['type'];
		return 'data:' . $mime . ';base64,' . base64_encode( file_get_contents( $path ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions
	}
	return $url;
}

/**
 * İstemcinin referans listesini çözer: [{type: item|ref|person, id}] → Flux AI girdileri ve kayıt için özet.
 * Kişi = o kişinin tüm fotoğrafları (aynı kişi).
 *
 * @return array|WP_Error [ inputs[], summary[] ]
 */
function flxst_resolve_refs( array $refs, $user_id, $max = 14 ) {
	$inputs  = array();
	$summary = array();
	foreach ( $refs as $r ) {
		$type = (string) ( $r['type'] ?? '' );
		$id   = (int) ( $r['id'] ?? 0 );
		if ( 'item' === $type ) {
			$row = flxst_item( $id );
			if ( ! flxst_item_visible( $row, $user_id ) || 'done' !== $row['state'] || 'image' !== $row['kind'] ) {
				return new WP_Error( 'flxst_ref', __( 'Reference image not found.', 'flx-studio' ), array( 'status' => 400 ) );
			}
			$inputs[]  = flxst_ai_input( $row['url'], $row['path'] );
			$summary[] = array( 'type' => 'item', 'id' => $id, 'role' => sanitize_key( $r['role'] ?? '' ), 'thumb' => $row['thumb'] ? $row['thumb'] : $row['url'] );
		} elseif ( 'ref' === $type ) {
			$row = flxst_ref( $id );
			if ( ! flxst_ref_visible( $row, $user_id ) ) {
				return new WP_Error( 'flxst_ref', __( 'Reference image not found.', 'flx-studio' ), array( 'status' => 400 ) );
			}
			$inputs[]  = flxst_ai_input( $row['url'], $row['path'] );
			$summary[] = array( 'type' => 'ref', 'id' => $id, 'role' => sanitize_key( $r['role'] ?? $row['role'] ), 'thumb' => $row['thumb'] ? $row['thumb'] : $row['url'] );
		} elseif ( 'person' === $type ) {
			$p = flxst_person( $id );
			if ( ! flxst_person_visible( $p, $user_id ) ) {
				return new WP_Error( 'flxst_ref', __( 'Person not found.', 'flx-studio' ), array( 'status' => 400 ) );
			}
			$photos = flxst_refs( $user_id, array( 'person' => $id ) );
			foreach ( array_slice( $photos, 0, 4 ) as $ph ) {
				$row      = flxst_ref( $ph['id'] );
				$inputs[] = flxst_ai_input( $row['url'], $row['path'] );
			}
			$summary[] = array( 'type' => 'person', 'id' => $id, 'name' => $p['name'], 'photos' => min( 4, count( $photos ) ), 'thumb' => $photos ? $photos[0]['thumb'] : '' );
		}
		if ( count( $inputs ) > $max ) {
			/* translators: %d: max reference images */
			return new WP_Error( 'flxst_ref', sprintf( __( 'At most %d reference images can be used.', 'flx-studio' ), $max ), array( 'status' => 400 ) );
		}
	}
	return array( $inputs, $summary );
}

/** Üretimi WordPress Medya kitaplığına ekler (dosya kopyalanır). */
function flxst_to_library( $row ) {
	if ( (int) $row['attachment_id'] && get_post( (int) $row['attachment_id'] ) ) {
		return (int) $row['attachment_id'];
	}
	if ( empty( $row['path'] ) || ! is_file( $row['path'] ) ) {
		return new WP_Error( 'flxst_file', __( 'The file is not on the server.', 'flx-studio' ), array( 'status' => 404 ) );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = wp_tempnam( basename( $row['path'] ) );
	copy( $row['path'], $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$title = $row['title'] ? $row['title'] : wp_trim_words( $row['prompt'], 8, '' );
	$id    = media_handle_sideload(
		array(
			'name'     => sanitize_file_name( ( $title ? $title : 'flx-studio' ) . '.' . pathinfo( $row['path'], PATHINFO_EXTENSION ) ),
			'tmp_name' => $tmp,
		),
		0,
		$title
	);
	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		return $id;
	}
	update_post_meta( $id, '_wp_attachment_image_alt', wp_trim_words( $row['prompt'], 20, '' ) );
	update_post_meta( $id, '_flxst_item', (int) $row['id'] );
	flxst_item_update( (int) $row['id'], array( 'attachment_id' => (int) $id ) );
	return (int) $id;
}
