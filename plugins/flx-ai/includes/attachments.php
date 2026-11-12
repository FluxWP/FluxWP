<?php
/**
 * Sohbet ekleri (0.12.0): kullanıcı sohbete dosya ekler (görsel, PDF, metin/CSV/JSON).
 * Dosyalar uploads/flxai-chat/<kullanıcı>/ altında, dışarıdan erişim kapalı (.htaccess + index.php);
 * yalnız sahibi REST ile görür. Mesajda `_files` => [ key, name, type, size ].
 * Modele: görsel image_url (data URL), PDF file, metin dosyası içerik olarak gider; yalnız son iki ekli
 * kullanıcı mesajının dosyaları tam gönderilir (maliyet), öncekiler "[Ek: ad]" notu olur.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** İzinli uzantılar: uzantı => tür. */
function flxai_attach_types() {
	return (array) apply_filters(
		'flxai_attach_types',
		array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'pdf'  => 'application/pdf',
			'txt'  => 'text/plain',
			'md'   => 'text/markdown',
			'csv'  => 'text/csv',
			'tsv'  => 'text/tab-separated-values',
			'json' => 'application/json',
			'xml'  => 'application/xml',
			'html' => 'text/html',
			'log'  => 'text/plain',
		)
	);
}

function flxai_attach_max_bytes() {
	return (int) apply_filters( 'flxai_attach_max_bytes', 10 * MB_IN_BYTES );
}

function flxai_attach_dir( $user_id ) {
	$base = trailingslashit( wp_upload_dir()['basedir'] ) . 'flxai-chat';
	if ( ! is_dir( $base ) ) {
		wp_mkdir_p( $base );
	}
	if ( ! file_exists( $base . '/.htaccess' ) ) {
		@file_put_contents( $base . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
		@file_put_contents( $base . '/index.php', "<?php // Silence.\n" ); // phpcs:ignore
	}
	$dir = $base . '/' . (int) $user_id;
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		@file_put_contents( $dir . '/index.php', "<?php // Silence.\n" ); // phpcs:ignore
	}
	return $dir;
}

/** Ek bilgisi (yoksa null): key, name, type, size, path. */
function flxai_attach_get( $key, $user_id ) {
	$key = (string) $key;
	if ( ! preg_match( '/^[A-Za-z0-9]{16}-[A-Za-z0-9._-]{1,120}$/', $key ) ) {
		return null;
	}
	$path = flxai_attach_dir( $user_id ) . '/' . $key;
	if ( ! is_file( $path ) ) {
		return null;
	}
	$ext   = strtolower( pathinfo( $key, PATHINFO_EXTENSION ) );
	$types = flxai_attach_types();
	if ( ! isset( $types[ $ext ] ) ) {
		return null;
	}
	return array(
		'key'  => $key,
		'name' => substr( $key, 17 ),
		'type' => $types[ $ext ],
		'size' => (int) filesize( $path ),
		'path' => $path,
	);
}

/**
 * Yüklenen dosyayı kaydeder.
 *
 * @param array $file $_FILES öğesi.
 * @return array|WP_Error key, name, type, size.
 */
function flxai_attach_save( array $file, $user_id ) {
	if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return new WP_Error( 'flxai_upload', __( 'The file could not be uploaded.', 'flx-ai' ), array( 'status' => 400 ) );
	}
	$name  = sanitize_file_name( wp_basename( (string) $file['name'] ) );
	$ext   = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	$types = flxai_attach_types();
	if ( ! isset( $types[ $ext ] ) ) {
		/* translators: %s: list of file extensions */
		return new WP_Error( 'flxai_upload_type', sprintf( __( 'This file type is not supported. Allowed: %s', 'flx-ai' ), implode( ', ', array_keys( $types ) ) ), array( 'status' => 400 ) );
	}
	if ( (int) $file['size'] > flxai_attach_max_bytes() ) {
		/* translators: %s: maximum file size */
		return new WP_Error( 'flxai_upload_size', sprintf( __( 'The file is too large (max %s).', 'flx-ai' ), size_format( flxai_attach_max_bytes() ) ), array( 'status' => 400 ) );
	}
	if ( 0 === strpos( $types[ $ext ], 'image/' ) && ! @getimagesize( $file['tmp_name'] ) ) { // phpcs:ignore
		return new WP_Error( 'flxai_upload_type', __( 'The image could not be read.', 'flx-ai' ), array( 'status' => 400 ) );
	}
	$base = preg_replace( '/[^A-Za-z0-9._-]+/', '-', remove_accents( pathinfo( $name, PATHINFO_FILENAME ) ) );
	$base = trim( substr( (string) $base, 0, 100 ), '-.' );
	$key  = wp_generate_password( 16, false, false ) . '-' . ( '' !== $base ? $base : 'file' ) . '.' . $ext;
	$path = flxai_attach_dir( $user_id ) . '/' . $key;
	if ( ! @move_uploaded_file( $file['tmp_name'], $path ) ) { // phpcs:ignore
		return new WP_Error( 'flxai_upload', __( 'The file could not be uploaded.', 'flx-ai' ), array( 'status' => 500 ) );
	}
	$a = flxai_attach_get( $key, $user_id );
	unset( $a['path'] );
	$a['name'] = $name;
	return $a;
}

/** Mesaja yazılacak ek listesi (geçersizler atlanır). */
function flxai_attach_for_message( array $keys, array $names, $user_id ) {
	$out = array();
	foreach ( array_slice( $keys, 0, (int) apply_filters( 'flxai_attach_max_files', 5 ) ) as $i => $k ) {
		$a = flxai_attach_get( (string) $k, $user_id );
		if ( $a ) {
			$n     = sanitize_file_name( (string) ( $names[ $i ] ?? '' ) );
			$out[] = array(
				'key'  => $a['key'],
				'name' => '' !== $n ? mb_substr( $n, 0, 120 ) : $a['name'],
				'type' => $a['type'],
				'size' => $a['size'],
			);
		}
	}
	return $out;
}

/** Modele giden içerik parçaları. $full = false ise yalnız not. */
function flxai_attach_parts( array $files, $user_id, $full ) {
	$parts = array();
	$notes = array();
	foreach ( $files as $f ) {
		$a = $full ? flxai_attach_get( (string) ( $f['key'] ?? '' ), $user_id ) : null;
		$n = (string) ( $f['name'] ?? '' );
		if ( ! $a ) {
			$notes[] = '[Ek: ' . $n . ( $full ? ' (dosya artık yok)' : ' (önceki mesajda gönderildi)' ) . ']';
			continue;
		}
		$type = $a['type'];
		if ( 0 === strpos( $type, 'image/' ) ) {
			$parts[] = array(
				'type'      => 'image_url',
				'image_url' => array( 'url' => 'data:' . $type . ';base64,' . base64_encode( (string) file_get_contents( $a['path'] ) ) ), // phpcs:ignore
			);
			$notes[] = '[Ek görsel: ' . $n . ']';
		} elseif ( 'application/pdf' === $type ) {
			$parts[] = array(
				'type' => 'file',
				'file' => array(
					'filename'  => $n,
					'file_data' => 'data:application/pdf;base64,' . base64_encode( (string) file_get_contents( $a['path'] ) ), // phpcs:ignore
				),
			);
			$notes[] = '[Ek PDF: ' . $n . ']';
		} else {
			$txt = (string) file_get_contents( $a['path'], false, null, 0, 200000 ); // phpcs:ignore
			if ( ! mb_check_encoding( $txt, 'UTF-8' ) ) {
				$txt = mb_convert_encoding( $txt, 'UTF-8', 'ISO-8859-9' );
			}
			$notes[] = '[Ek dosya: ' . $n . "]\n" . mb_substr( $txt, 0, 60000 ) . ( mb_strlen( $txt ) > 60000 ? "\n…(kısaltıldı)" : '' ) . "\n[/Ek]";
		}
	}
	return array( $parts, $notes );
}

/* ---------- REST ---------- */

add_action(
	'rest_api_init',
	function () {
		$perm = function () {
			return is_user_logged_in() && flxai_can_chat();
		};
		register_rest_route(
			'flx-ai/v1',
			'/files',
			array(
				'methods'             => 'POST',
				'permission_callback' => $perm,
				'callback'            => function ( WP_REST_Request $req ) {
					$files = $req->get_file_params();
					if ( empty( $files['file'] ) ) {
						return new WP_Error( 'flxai_upload', __( 'The file could not be uploaded.', 'flx-ai' ), array( 'status' => 400 ) );
					}
					$a = flxai_attach_save( (array) $files['file'], get_current_user_id() );
					return is_wp_error( $a ) ? $a : $a;
				},
			)
		);
		register_rest_route(
			'flx-ai/v1',
			'/files/(?P<key>[A-Za-z0-9._-]+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => $perm,
				'callback'            => function ( WP_REST_Request $req ) {
					$a = flxai_attach_get( (string) $req['key'], get_current_user_id() );
					if ( ! $a ) {
						return new WP_Error( 'flxai_not_found', __( 'File not found.', 'flx-ai' ), array( 'status' => 404 ) );
					}
					nocache_headers();
					header( 'Content-Type: ' . ( 0 === strpos( $a['type'], 'image/' ) || 'application/pdf' === $a['type'] ? $a['type'] : 'text/plain; charset=utf-8' ) );
					header( 'Content-Disposition: ' . ( 0 === strpos( $a['type'], 'image/' ) || 'application/pdf' === $a['type'] ? 'inline' : 'attachment' ) . '; filename="' . str_replace( '"', '', $a['name'] ) . '"' );
					header( 'X-Content-Type-Options: nosniff' );
					header( 'Content-Length: ' . $a['size'] );
					readfile( $a['path'] ); // phpcs:ignore
					exit;
				},
			)
		);
	}
);

/** Sohbet silinince ekleri de silinir. */
function flxai_attach_delete_for_chat( array $chat ) {
	foreach ( $chat['messages'] as $m ) {
		foreach ( (array) ( $m['_files'] ?? array() ) as $f ) {
			$a = flxai_attach_get( (string) ( $f['key'] ?? '' ), (int) $chat['user_id'] );
			if ( $a ) {
				wp_delete_file( $a['path'] );
			}
		}
	}
}
