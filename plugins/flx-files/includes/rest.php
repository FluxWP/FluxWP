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

/** Dizi parametresi → göreli yol listesi. */
function flxfm_paths_param( $v ) {
	$out = array();
	foreach ( (array) $v as $p ) {
		if ( is_string( $p ) ) {
			$out[] = $p;
		}
	}
	return array_slice( $out, 0, 1000 );
}

/** Birden çok öğede aynı işlemi uygular; hataları toplar. */
function flxfm_each( array $paths, callable $fn ) {
	$done = array();
	$errs = array();
	foreach ( $paths as $p ) {
		$r = $fn( $p );
		if ( is_wp_error( $r ) ) {
			$errs[] = array( 'path' => $p, 'message' => $r->get_error_message() );
		} else {
			$done[] = $r;
		}
	}
	return array( 'done' => $done, 'errors' => $errs );
}

/**
 * Tek uç, işlem `op` alanında.
 *
 * @param WP_REST_Request $req İstek.
 */
function flxfm_rest_op( WP_REST_Request $req ) {
	$p    = (array) $req->get_json_params();
	$op   = sanitize_key( (string) ( $p['op'] ?? '' ) );
	$path = (string) ( $p['path'] ?? '' );
	$s    = flxfm_settings();
	switch ( $op ) {
		case 'list':
			$hidden = array_key_exists( 'hidden', $p ) ? ! empty( $p['hidden'] ) : ! empty( $s['show_hidden'] );
			$r      = FLXFM_FS::list_dir( $path, array( 'hidden' => $hidden ) );
			if ( ! is_wp_error( $r ) && ! empty( $p['disk'] ) ) {
				$r['disk'] = FLXFM_FS::disk();
			}
			return flxfm_rest_out( $r );

		case 'tree':
			$hidden = ! empty( $s['show_hidden'] );
			$r      = FLXFM_FS::list_dir( $path, array( 'hidden' => $hidden, 'no_count' => true ) );
			if ( is_wp_error( $r ) ) {
				return flxfm_rest_out( $r );
			}
			$items = array();
			foreach ( $r['items'] as $it ) {
				$items[] = array_intersect_key( $it, array_flip( array( 'name', 'path', 'type', 'kind', 'ext', 'locked', 'sensitive' ) ) );
			}
			return flxfm_rest_out( array( 'path' => $r['path'], 'items' => $items ) );

		case 'read':
			return flxfm_rest_out( FLXFM_FS::read( $path ) );

		case 'write':
			return flxfm_rest_out(
				FLXFM_FS::write(
					$path,
					(string) ( $p['content'] ?? '' ),
					array(
						'expect_hash' => (string) ( $p['hash'] ?? '' ),
						'create_only' => ! empty( $p['create_only'] ),
					)
				)
			);

		case 'mkdir':
			return flxfm_rest_out( FLXFM_FS::mkdir( $path ) );

		case 'rename':
			$name = (string) ( $p['name'] ?? '' );
			if ( ! flxfm_valid_name( $name ) ) {
				return flxfm_rest_out( FLXFM_FS::err( 'name', __( 'Invalid name.', 'flx-files' ) ) );
			}
			$dir = dirname( flxfm_clean( $path ) );
			return flxfm_rest_out( FLXFM_FS::move( $path, ( '.' === $dir ? '' : $dir . '/' ) . $name ) );

		case 'move':
		case 'copy':
			$dest = flxfm_clean( (string) ( $p['dest'] ?? '' ) );
			if ( is_wp_error( $dest ) ) {
				return flxfm_rest_out( $dest );
			}
			$overwrite = ! empty( $p['overwrite'] );
			return flxfm_rest_out(
				flxfm_each(
					flxfm_paths_param( $p['paths'] ?? array() ),
					static function ( $src ) use ( $op, $dest, $overwrite ) {
						$to = ( '' === $dest ? '' : $dest . '/' ) . basename( (string) flxfm_clean( $src ) );
						return 'move' === $op ? FLXFM_FS::move( $src, $to, $overwrite ) : FLXFM_FS::copy( $src, $to, $overwrite );
					}
				)
			);

		case 'duplicate':
			return flxfm_rest_out(
				flxfm_each(
					flxfm_paths_param( $p['paths'] ?? array() ),
					static function ( $src ) {
						return FLXFM_FS::copy( $src, $src );
					}
				)
			);

		case 'trash':
			return flxfm_rest_out( flxfm_each( flxfm_paths_param( $p['paths'] ?? array() ), array( 'FLXFM_FS', 'trash' ) ) );

		case 'chmod':
			return flxfm_rest_out( FLXFM_FS::chmod( $path, (string) ( $p['mode'] ?? '' ) ) );

		case 'size':
			return flxfm_rest_out( FLXFM_FS::dir_size( $path ) );

		case 'search':
			return flxfm_rest_out(
				FLXFM_FS::search(
					$path,
					(string) ( $p['q'] ?? '' ),
					array(
						'content' => ! empty( $p['content'] ),
						'hidden'  => ! empty( $s['show_hidden'] ),
					)
				)
			);

		case 'zip':
			$paths = flxfm_paths_param( $p['paths'] ?? array() );
			$name  = (string) ( $p['name'] ?? '' );
			if ( '' === $name ) {
				$name = 1 === count( $paths ) ? basename( (string) flxfm_clean( $paths[0] ) ) . '.zip' : 'archive.zip';
			}
			if ( ! flxfm_valid_name( $name ) ) {
				return flxfm_rest_out( FLXFM_FS::err( 'name', __( 'Invalid name.', 'flx-files' ) ) );
			}
			if ( ! preg_match( '/\.zip$/i', $name ) ) {
				$name .= '.zip';
			}
			$dir = flxfm_clean( (string) ( $p['dir'] ?? '' ) );
			if ( is_wp_error( $dir ) ) {
				return flxfm_rest_out( $dir );
			}
			$r = FLXFM_FS::zip( $paths, ( '' === $dir ? '' : $dir . '/' ) . $name );
			if ( ! is_wp_error( $r ) ) {
				$r = array( 'item' => $r['item'] );
			}
			return flxfm_rest_out( $r );

		case 'unzip':
			return flxfm_rest_out( FLXFM_FS::unzip( $path, isset( $p['dest'] ) ? (string) $p['dest'] : null ) );

		case 'versions':
			return flxfm_rest_out( array( 'versions' => flxfm_versions( (string) flxfm_clean( $path ) ) ) );

		case 'version':
			$c = flxfm_version_read( (string) flxfm_clean( $path ), (string) ( $p['id'] ?? '' ) );
			return flxfm_rest_out( is_wp_error( $c ) ? $c : array( 'content' => $c ) );

		case 'revert':
			$c = flxfm_version_read( (string) flxfm_clean( $path ), (string) ( $p['id'] ?? '' ) );
			if ( is_wp_error( $c ) ) {
				return flxfm_rest_out( $c );
			}
			$r = FLXFM_FS::write( $path, $c );
			if ( ! is_wp_error( $r ) ) {
				flxfm_log( 'revert', $r['path'] );
			}
			return flxfm_rest_out( $r );

		case 'trash_list':
			return flxfm_rest_out( array( 'items' => flxfm_trash_list(), 'days' => (int) $s['trash_days'] ) );

		case 'restore':
			return flxfm_rest_out(
				flxfm_each(
					flxfm_paths_param( $p['ids'] ?? array() ),
					'flxfm_trash_restore'
				)
			);

		case 'purge':
			if ( ! empty( $p['all'] ) ) {
				return flxfm_rest_out( array( 'removed' => flxfm_trash_purge( true ) ) );
			}
			return flxfm_rest_out( flxfm_each( flxfm_paths_param( $p['ids'] ?? array() ), 'flxfm_trash_delete' ) );

		case 'log':
			return flxfm_rest_out(
				flxfm_log_list(
					array(
						'path'  => (string) ( $p['filter_path'] ?? '' ),
						'via'   => sanitize_key( (string) ( $p['via'] ?? '' ) ),
						'page'  => (int) ( $p['page'] ?? 1 ),
						'limit' => 50,
					)
				)
			);

		case 'settings':
			if ( isset( $p['settings'] ) && is_array( $p['settings'] ) ) {
				$s = flxfm_save_settings( $p['settings'] );
				flxfm_log( 'settings', '' );
			}
			return flxfm_rest_out( array( 'settings' => $s ) );

		case 'share':
			$rel = flxfm_clean( $path );
			if ( is_wp_error( $rel ) ) {
				return flxfm_rest_out( $rel );
			}
			if ( ! empty( $p['on'] ) && ! flxfm_shareable( $rel ) ) {
				return flxfm_rest_out( FLXFM_FS::err( 'share', __( 'Only folders inside the uploads folder can be shared on the site.', 'flx-files' ) ) );
			}
			$list = array_values( array_diff( (array) $s['shared'], array( $rel ) ) );
			if ( ! empty( $p['on'] ) ) {
				$list[] = $rel;
			}
			flxfm_save_settings( array( 'shared' => $list ) );
			flxfm_log( empty( $p['on'] ) ? 'unshare' : 'share', $rel );
			return flxfm_rest_out( array( 'shared' => ! empty( $p['on'] ), 'shortcode' => flxfm_shortcode_for( $rel ) ) );

		case 'ai_explain':
		case 'ai_edit':
			return flxfm_rest_out( flxfm_ai_run( $op, $path, $p ) );
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
