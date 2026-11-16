<?php
/**
 * REST: tek işlem ucu (POST /flx-files/v1/op), parça parça yükleme (POST /upload).
 * İndirme ve önizleme admin-post.php üzerinden (nonce + yetki). Hepsi `flxfm_manage` ister.
 *
 * @package FLX_Files
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	static function () {
		$perm = static function () {
			return current_user_can( 'flxfm_manage' );
		};
		register_rest_route(
			'flx-files/v1',
			'/op',
			array(
				'methods'             => 'POST',
				'permission_callback' => $perm,
				'callback'            => 'flxfm_rest_op',
			)
		);
		register_rest_route(
			'flx-files/v1',
			'/upload',
			array(
				'methods'             => 'POST',
				'permission_callback' => $perm,
				'callback'            => 'flxfm_rest_upload',
			)
		);
	}
);

/* Değişken veri: önbelleğe alınmaz (LiteSpeed REST önbelleği). */
add_filter(
	'rest_post_dispatch',
	static function ( $res, $server, $req ) {
		if ( 0 === strpos( (string) $req->get_route(), '/flx-files/' ) ) {
			flx_rest_nocache( $res );
			do_action( 'litespeed_control_set_nocache', 'flx-files' );
		}
		return $res;
	},
	10,
	3
);

/** WP_Error ya da sonuç → REST yanıtı. */
function flxfm_rest_out( $r ) {
	if ( is_wp_error( $r ) ) {
		$data = $r->get_error_data();
		return new WP_REST_Response(
			array(
				'ok'      => false,
				'code'    => $r->get_error_code(),
				'message' => $r->get_error_message(),
			),
			is_array( $data ) && ! empty( $data['status'] ) ? (int) $data['status'] : 400
		);
	}
	return new WP_REST_Response( array_merge( array( 'ok' => true ), is_array( $r ) ? $r : array( 'result' => $r ) ), 200 );
}

/** Servis sonucu (tipli değer ya da WP_Error) → REST yanıtı; gövde eski biçimle aynıdır. */
function flxfm_rest_svc( $r ) {
	return flxfm_rest_out( is_object( $r ) && method_exists( $r, 'to_rest' ) ? $r->to_rest() : $r );
}

/** Dizi parametresi → göreli yol listesi. */
function flxfm_paths_param( $v ) {
	return FLXFM_Files_Service::paths( $v );
}

/**
 * Tek uç, işlem `op` alanında. Mantık FLXFM_Files_Service'te; GraphQL ve bu uç aynı servisi kullanır.
 *
 * @param WP_REST_Request $req İstek.
 */
function flxfm_rest_op( WP_REST_Request $req ) {
	$p    = (array) $req->get_json_params();
	$op   = sanitize_key( (string) ( $p['op'] ?? '' ) );
	$path = (string) ( $p['path'] ?? '' );
	switch ( $op ) {
		case 'list':
			return flxfm_rest_svc( FLXFM_Files_Service::listing( $path, array_key_exists( 'hidden', $p ) ? ! empty( $p['hidden'] ) : null, ! empty( $p['disk'] ) ) );

		case 'tree':
			return flxfm_rest_svc( FLXFM_Files_Service::tree( $path ) );

		case 'read':
			return flxfm_rest_svc( FLXFM_Files_Service::read( $path ) );

		case 'write':
			return flxfm_rest_svc( FLXFM_Files_Service::write( $path, (string) ( $p['content'] ?? '' ), (string) ( $p['hash'] ?? '' ), ! empty( $p['create_only'] ) ) );

		case 'mkdir':
			return flxfm_rest_svc( FLXFM_Files_Service::mkdir( $path ) );

		case 'rename':
			return flxfm_rest_svc( FLXFM_Files_Service::rename( $path, (string) ( $p['name'] ?? '' ) ) );

		case 'move':
		case 'copy':
			return flxfm_rest_svc( FLXFM_Files_Service::transfer( $op, flxfm_paths_param( $p['paths'] ?? array() ), (string) ( $p['dest'] ?? '' ), ! empty( $p['overwrite'] ) ) );

		case 'duplicate':
			return flxfm_rest_svc( FLXFM_Files_Service::duplicate( flxfm_paths_param( $p['paths'] ?? array() ) ) );

		case 'trash':
			return flxfm_rest_svc( FLXFM_Files_Service::trash( flxfm_paths_param( $p['paths'] ?? array() ) ) );

		case 'chmod':
			return flxfm_rest_svc( FLXFM_Files_Service::chmod( $path, (string) ( $p['mode'] ?? '' ) ) );

		case 'size':
			return flxfm_rest_svc( FLXFM_Files_Service::size( $path ) );

		case 'search':
			return flxfm_rest_svc( FLXFM_Files_Service::search( $path, (string) ( $p['q'] ?? '' ), ! empty( $p['content'] ) ) );

		case 'zip':
			$r = FLXFM_Files_Service::zip( flxfm_paths_param( $p['paths'] ?? array() ), (string) ( $p['name'] ?? '' ), (string) ( $p['dir'] ?? '' ) );
			return flxfm_rest_out( is_wp_error( $r ) ? $r : array( 'item' => $r->to_rest() ) );

		case 'unzip':
			return flxfm_rest_svc( FLXFM_Files_Service::unzip( $path, isset( $p['dest'] ) ? (string) $p['dest'] : null ) );

		case 'versions':
			return flxfm_rest_out( array( 'versions' => FLXFM_Files_Service::versions_rest( $path ) ) );

		case 'version':
			$c = FLXFM_Files_Service::version_content( $path, (string) ( $p['id'] ?? '' ) );
			return flxfm_rest_out( is_wp_error( $c ) ? $c : array( 'content' => $c ) );

		case 'revert':
			return flxfm_rest_svc( FLXFM_Files_Service::revert( $path, (string) ( $p['id'] ?? '' ) ) );

		case 'usage':
			return flxfm_rest_out( array( 'disk' => FLXFM_Files_Service::disk( ! empty( $p['force'] ) )->to_rest() ) );

		case 'trash_list':
			return flxfm_rest_svc( FLXFM_Files_Service::trash_list() );

		case 'restore':
			return flxfm_rest_svc( FLXFM_Files_Service::restore( flxfm_paths_param( $p['ids'] ?? array() ) ) );

		case 'purge':
			$r = FLXFM_Files_Service::purge( flxfm_paths_param( $p['ids'] ?? array() ), ! empty( $p['all'] ) );
			return flxfm_rest_out( ! empty( $p['all'] ) ? $r->to_rest_all() : $r->to_rest() );

		case 'log':
			$page  = max( 1, (int) ( $p['page'] ?? 1 ) );
			$entry = FLXFM_Files_Service::log( (string) ( $p['filter_path'] ?? '' ), sanitize_key( (string) ( $p['via'] ?? '' ) ), ( $page - 1 ) * 50, 50 );
			return flxfm_rest_out( $entry->rest );

		case 'settings':
			if ( isset( $p['settings'] ) && is_array( $p['settings'] ) ) {
				FLXFM_Files_Service::update_settings( $p['settings'] );
			}
			return flxfm_rest_out( array( 'settings' => flxfm_settings() ) );

		case 'share':
			return flxfm_rest_svc( FLXFM_Files_Service::share( $path, ! empty( $p['on'] ) ) );

		case 'ai_explain':
			return flxfm_rest_svc( FLXFM_Files_Service::ai_explain( $path, isset( $p['content'] ) ? (string) $p['content'] : null, (string) ( $p['selection'] ?? '' ) ) );

		case 'ai_edit':
			return flxfm_rest_svc( FLXFM_Files_Service::ai_edit( $path, isset( $p['content'] ) ? (string) $p['content'] : null, (string) ( $p['prompt'] ?? '' ) ) );
	}
	return flxfm_rest_out( FLXFM_FS::err( 'op', __( 'Unknown action.', 'flx-files' ) ) );
}

/**
 * Parça parça yükleme. Her parça `offset` konumuna yazılır; son parçada dosya yerine taşınır.
 * Alanlar: file (parça), dir, name (göreli yol olabilir: klasör yükleme), id, offset, total, overwrite.
 *
 * @param WP_REST_Request $req İstek.
 */
function flxfm_rest_upload( WP_REST_Request $req ) {
	if ( ! flxfm_writable_mode() ) {
		return flxfm_rest_out( FLXFM_FS::err( 'readonly', __( 'File changes are disabled on this site (DISALLOW_FILE_MODS).', 'flx-files' ), 403 ) );
	}
	$files = $req->get_file_params();
	if ( empty( $files['file'] ) || ! empty( $files['file']['error'] ) || ! is_uploaded_file( $files['file']['tmp_name'] ) ) {
		return flxfm_rest_out( FLXFM_FS::err( 'upload', __( 'The upload failed. The file may be larger than the server allows.', 'flx-files' ) ) );
	}
	$dir  = flxfm_clean( (string) $req->get_param( 'dir' ) );
	$name = flxfm_clean( (string) $req->get_param( 'name' ) );
	if ( is_wp_error( $dir ) || is_wp_error( $name ) || '' === $name ) {
		return flxfm_rest_out( FLXFM_FS::err( 'name', __( 'Invalid name.', 'flx-files' ) ) );
	}
	foreach ( explode( '/', $name ) as $part ) {
		if ( ! flxfm_valid_name( $part ) ) {
			return flxfm_rest_out( FLXFM_FS::err( 'name', __( 'Invalid name.', 'flx-files' ) ) );
		}
	}
	$rel    = ( '' === $dir ? '' : $dir . '/' ) . $name;
	$id     = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $req->get_param( 'id' ) ) );
	$offset = max( 0, (int) $req->get_param( 'offset' ) );
	$total  = max( 0, (int) $req->get_param( 'total' ) );
	if ( strlen( $id ) < 8 ) {
		return flxfm_rest_out( FLXFM_FS::err( 'upload', __( 'The upload failed. Try again.', 'flx-files' ) ) );
	}

	// İlk parçada hedef kontrolü (çakışma, yetki, korumalı yer).
	if ( 0 === $offset ) {
		$base = FLXFM_FS::resolve( $dir );
		if ( is_wp_error( $base ) ) {
			return flxfm_rest_out( $base );
		}
		if ( flxfm_in_data( $base['rel'] ) ) {
			return flxfm_rest_out( FLXFM_FS::err( 'locked', __( 'This folder is managed by Flux Files.', 'flx-files' ), 403 ) );
		}
		if ( flxfm_is_executable( $rel ) && ! flxfm_code_edit_allowed() ) {
			return flxfm_rest_out( FLXFM_FS::err( 'code', __( 'Editing code files is disabled on this site (DISALLOW_FILE_EDIT).', 'flx-files' ), 403 ) );
		}
		$exists = file_exists( flxfm_root() . '/' . $rel );
		if ( $exists && ! $req->get_param( 'overwrite' ) ) {
			return flxfm_rest_out( FLXFM_FS::err( 'exists', __( 'A file with this name already exists.', 'flx-files' ), 409 ) );
		}
		$free = @disk_free_space( flxfm_root() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( $free && $total > $free ) {
			return flxfm_rest_out( FLXFM_FS::err( 'space', __( 'Not enough disk space.', 'flx-files' ), 507 ) );
		}
	}

	$part = flxfm_data_dir( 'tmp' ) . '/' . $id . '.part';
	if ( $offset > 0 && ( ! is_file( $part ) || filesize( $part ) !== $offset ) ) {
		return flxfm_rest_out( FLXFM_FS::err( 'upload', __( 'The upload was interrupted. Try again.', 'flx-files' ), 409 ) );
	}
	$in  = fopen( $files['file']['tmp_name'], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$out = fopen( $part, 0 === $offset ? 'wb' : 'ab' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! $in || ! $out ) {
		return flxfm_rest_out( FLXFM_FS::err( 'write', __( 'The file could not be written (permissions or disk space).', 'flx-files' ), 500 ) );
	}
	stream_copy_to_stream( $in, $out );
	fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	clearstatcache( true, $part );
	$have = (int) filesize( $part );
	if ( $have < $total ) {
		return flxfm_rest_out( array( 'received' => $have ) );
	}

	// Son parça: klasörleri oluştur, yerine taşı.
	$target = flxfm_root() . '/' . $rel;
	$parent = dirname( $target );
	if ( ! is_dir( $parent ) && ! wp_mkdir_p( $parent ) ) {
		@unlink( $part ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return flxfm_rest_out( FLXFM_FS::err( 'write', __( 'The folder could not be created (permissions).', 'flx-files' ), 500 ) );
	}
	$r = FLXFM_FS::resolve( $rel, false );
	if ( is_wp_error( $r ) ) {
		@unlink( $part ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return flxfm_rest_out( $r );
	}
	if ( is_dir( $r['abs'] ) ) {
		@unlink( $part ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return flxfm_rest_out( FLXFM_FS::err( 'isdir', __( 'A folder with this name already exists.', 'flx-files' ), 409 ) );
	}
	$existed = file_exists( $r['abs'] );
	if ( $existed ) {
		flxfm_version_save( $r['rel'], $r['abs'] );
	}
	if ( ! @rename( $part, $r['abs'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		@unlink( $part ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return flxfm_rest_out( FLXFM_FS::err( 'write', __( 'The file could not be written (permissions or disk space).', 'flx-files' ), 500 ) );
	}
	@chmod( $r['abs'], defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
	flxfm_log( 'upload', $r['rel'] );
	return flxfm_rest_out( array( 'item' => FLXFM_FS::item( $r['abs'], $r['rel'] ) ) );
}

/* ------------------------------------------------------- İndirme / önizleme */

/** İndirme ya da önizleme adresi. */
function flxfm_file_url( $rel, $inline = false ) {
	return add_query_arg(
		array(
			'action'   => 'flxfm_file',
			'path'     => rawurlencode( $rel ),
			'inline'   => $inline ? 1 : null,
			'_wpnonce' => wp_create_nonce( 'flxfm_file' ),
		),
		admin_url( 'admin-post.php' )
	);
}

/*
 * Tek dosya ya da klasör/çoklu seçim (ZIP). Satır içi önizleme yalnız güvenli türlerde;
 * SVG ve diğerleri sandbox CSP ile gönderilir (yönetici oturumunda betik çalışmaz).
 */
add_action(
	'admin_post_flxfm_file',
	static function () {
		if ( ! current_user_can( 'flxfm_manage' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'flxfm_file' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-files' ), '', array( 'response' => 403 ) );
		}
		$paths = array();
		if ( isset( $_GET['paths'] ) ) {
			$paths = array_filter( explode( "\n", rawurldecode( (string) wp_unslash( $_GET['paths'] ) ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} elseif ( isset( $_GET['path'] ) ) {
			$paths = array( rawurldecode( (string) wp_unslash( $_GET['path'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		$inline = ! empty( $_GET['inline'] );
		if ( 1 === count( $paths ) ) {
			$r = FLXFM_FS::resolve( $paths[0] );
			if ( is_wp_error( $r ) ) {
				wp_die( esc_html( $r->get_error_message() ), '', array( 'response' => 404 ) );
			}
			if ( flxfm_in_data( $r['rel'] ) ) {
				wp_die( esc_html__( 'This folder is managed by Flux Files.', 'flx-files' ), '', array( 'response' => 403 ) );
			}
			if ( is_file( $r['abs'] ) ) {
				$kind = flxfm_kind( $r['rel'] );
				$ext  = strtolower( pathinfo( $r['rel'], PATHINFO_EXTENSION ) );
				$safe = $inline && ( in_array( $kind, array( 'image', 'video', 'audio', 'pdf' ), true ) && 'svg' !== $ext );
				$type = '';
				if ( $inline && ! $safe ) {
					$type = 'text/plain; charset=utf-8'; // Kod/metin ham gösterilir, çalıştırılmaz.
				}
				nocache_headers();
				header( "Content-Security-Policy: sandbox; default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'" );
				if ( $safe && 'pdf' === $kind ) {
					header_remove( 'Content-Security-Policy' ); // Chrome PDF görüntüleyicisi sandbox'ta açılmıyor; PDF betik çalıştırmaz.
				}
				flx_send_file(
					$r['abs'],
					array(
						'inline'   => $inline,
						'type'     => $type,
						'filename' => basename( $r['rel'] ),
					)
				);
				exit;
			}
		}
		if ( ! $paths ) {
			wp_die( esc_html__( 'Nothing selected.', 'flx-files' ), '', array( 'response' => 400 ) );
		}
		$z = FLXFM_FS::zip( $paths );
		if ( is_wp_error( $z ) ) {
			wp_die( esc_html( $z->get_error_message() ), '', array( 'response' => 400 ) );
		}
		nocache_headers();
		flx_send_file( $z['abs'], array( 'filename' => $z['name'], 'type' => 'application/zip' ) );
		@unlink( $z['abs'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		exit;
	}
);
