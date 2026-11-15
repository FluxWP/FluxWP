<?php
/**
 * One upload endpoint for every package: POST /wp-json/flx/v1/upload (REST exception, ISTISNALAR.md).
 *
 * GraphQL over HTTP has no file upload; the client sends the file here first and passes the returned
 * `uploadId` to a mutation. The mutation's package takes the file over with flx_gql_upload_take().
 *
 *     add_filter( 'flx_gql_upload_contexts', function ( $c ) {
 *         $c['flx-tasks/attachment'] = array(
 *             'cap'    => 'read',                          // capability, 'public', or fn( WP_REST_Request ): bool
 *             'mimes'  => array( 'jpg|jpeg' => 'image/jpeg', 'pdf' => 'application/pdf' ), // default: WordPress' allowed types
 *             'max_mb' => 20,                              // default: Settings › Flux GraphQL › Upload limit
 *         );
 *         return $c;
 *     } );
 *
 *     $file = flx_gql_upload_take( $input['uploadId'], 'flx-tasks/attachment' ); // array|WP_Error
 *     rename( $file['path'], $my_dir . '/' . $file['name'] );                     // the package stores it
 *
 * The upload ID carries a secret ("<id>.<secret>"), so only the uploader can use it. Files sit in a
 * private folder (uploads/flx-uploads/tmp, no web access) and are deleted after a day when nobody takes them.
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registered upload contexts.
 *
 * @return array<string,array<string,mixed>>
 */
function flx_gql_upload_contexts(): array {
	/**
	 * Upload contexts: [ '<package>/<purpose>' => { cap, mimes?, max_mb? } ].
	 *
	 * @param array<string,array<string,mixed>> $contexts Contexts.
	 */
	$c = apply_filters( 'flx_gql_upload_contexts', array() );
	return array_map( 'flx_gql_arr', flx_gql_arr( $c ) );
}

/**
 * Private temporary folder.
 */
function flx_gql_upload_dir(): string {
	$up  = wp_upload_dir( null, false );
	$dir = trailingslashit( (string) $up['basedir'] ) . 'flx-uploads/tmp';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( function_exists( 'flx_private_dir' ) ) {
		flx_private_dir( dirname( $dir ) );
	} elseif ( ! file_exists( dirname( $dir ) . '/.htaccess' ) ) {
		file_put_contents( dirname( $dir ) . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( dirname( $dir ) . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}
	return $dir;
}

/**
 * Deletes temporary files older than a day (runs on each upload; no cron needed).
 */
function flx_gql_upload_cleanup(): void {
	$dir   = flx_gql_upload_dir();
	$items = is_dir( $dir ) ? scandir( $dir ) : false;
	if ( ! $items ) {
		return;
	}
	$limit = time() - DAY_IN_SECONDS;
	foreach ( $items as $name ) {
		if ( '.' === $name[0] ) {
			continue;
		}
		$path = $dir . '/' . $name;
		if ( is_file( $path ) && filemtime( $path ) < $limit ) {
			wp_delete_file( $path );
		}
	}
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'flx/v1',
			'/upload',
			array(
				'methods'             => 'POST',
				'callback'            => 'flx_gql_rest_upload',
				'permission_callback' => 'flx_gql_rest_upload_permission',
				'args'                => array(
					'context' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}
);

/**
 * Permission: the context's cap.
 *
 * @param WP_REST_Request $request Request.
 * @return true|WP_Error
 */
function flx_gql_rest_upload_permission( WP_REST_Request $request ) {
	$contexts = flx_gql_upload_contexts();
	$name     = flx_gql_str( $request->get_param( 'context' ) );
	if ( ! isset( $contexts[ $name ] ) ) {
		return new WP_Error( 'flxgql_upload_context', __( 'Uploads are not accepted for this screen.', 'flx-graphql' ), array( 'status' => 400 ) );
	}
	$cap = $contexts[ $name ]['cap'] ?? null;
	$ok  = false;
	if ( 'public' === $cap ) {
		$ok = true;
	} elseif ( is_string( $cap ) && '' !== $cap ) {
		$ok = current_user_can( $cap );
	} elseif ( is_callable( $cap ) ) {
		$ok = (bool) call_user_func( $cap, $request );
	}
	if ( ! $ok ) {
		return new WP_Error( 'rest_forbidden', __( 'You do not have permission to upload files here.', 'flx-graphql' ), array( 'status' => is_user_logged_in() ? 403 : 401 ) );
	}
	$who = is_user_logged_in() ? 'u' . get_current_user_id() : 'ip' . ( function_exists( 'flx_client_ip' ) ? flx_gql_str( flx_client_ip() ) : '' );
	if ( function_exists( 'flx_rate_limit' ) && ! flx_rate_limit( 'flxgql_upload_' . $who, 60, 600 ) ) {
		return new WP_Error( 'flxgql_rate', __( 'Too many uploads. Wait a few minutes and try again.', 'flx-graphql' ), array( 'status' => 429 ) );
	}
	return true;
}

/**
 * Stores the upload and returns its ID.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function flx_gql_rest_upload( WP_REST_Request $request ) {
	$name  = flx_gql_str( $request->get_param( 'context' ) );
	$ctx   = flx_gql_upload_contexts()[ $name ] ?? array();
	$files = $request->get_file_params();
	$f     = flx_gql_arr( $files['file'] ?? null );
	$tmp   = flx_gql_str( $f['tmp_name'] ?? '' );
	$orig  = flx_gql_str( $f['name'] ?? '' );
	if ( '' === $tmp || '' === $orig || UPLOAD_ERR_OK !== flx_gql_int( $f['error'] ?? UPLOAD_ERR_NO_FILE, UPLOAD_ERR_NO_FILE ) || ! is_uploaded_file( $tmp ) ) {
		return new WP_Error( 'flxgql_upload_missing', __( 'The file did not arrive. Try again.', 'flx-graphql' ), array( 'status' => 400 ) );
	}
	$max = flx_gql_int( $ctx['max_mb'] ?? null, flx_gql_settings()['upload_max_mb'] ) * MB_IN_BYTES;
	$max = min( $max, (int) wp_max_upload_size() );
	if ( flx_gql_int( $f['size'] ?? 0 ) > $max ) {
		/* translators: %s: size like "20 MB". */
		return new WP_Error( 'flxgql_upload_size', sprintf( __( 'The file is larger than %s.', 'flx-graphql' ), size_format( $max ) ), array( 'status' => 400 ) );
	}
	$mimes = isset( $ctx['mimes'] ) && is_array( $ctx['mimes'] ) ? array_map( 'flx_gql_str', flx_gql_arr( $ctx['mimes'] ) ) : null;
	$check = wp_check_filetype_and_ext( $tmp, sanitize_file_name( $orig ), $mimes );
	if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
		return new WP_Error( 'flxgql_upload_type', __( 'This file type is not allowed.', 'flx-graphql' ), array( 'status' => 400 ) );
	}

	flx_gql_upload_cleanup();
	$id     = wp_generate_password( 20, false, false );
	$secret = wp_generate_password( 24, false, false );
	$path   = flx_gql_upload_dir() . '/' . $id . '.' . $check['ext'];
	if ( ! move_uploaded_file( $tmp, $path ) ) {
		return new WP_Error( 'flxgql_upload_store', __( 'The file could not be saved on the server.', 'flx-graphql' ), array( 'status' => 500 ) );
	}
	$meta = array(
		'path'    => $path,
		'name'    => sanitize_file_name( $orig ),
		'mime'    => flx_gql_str( $check['type'] ),
		'size'    => (int) filesize( $path ),
		'user'    => get_current_user_id(),
		'context' => $name,
		'secret'  => wp_hash( $secret ),
		'created' => time(),
	);
	set_transient( 'flxgql_up_' . $id, $meta, DAY_IN_SECONDS );

	$res = rest_ensure_response(
		array(
			'uploadId' => $id . '.' . $secret,
			'name'     => $meta['name'],
			'mime'     => $meta['mime'],
			'size'     => $meta['size'],
			'isImage'  => str_starts_with( $meta['mime'], 'image/' ),
		)
	);
	$res->set_status( 201 );
	$out = function_exists( 'flx_rest_nocache' ) ? flx_rest_nocache( $res ) : $res;
	return $out instanceof WP_REST_Response ? $out : $res;
}

/**
 * Looks up an upload without taking it.
 *
 * @param string $upload_id "<id>.<secret>".
 * @param string $context   Expected context.
 * @return array<string,mixed>|WP_Error
 */
function flx_gql_upload_get( string $upload_id, string $context ) {
	$parts = explode( '.', $upload_id, 2 );
	if ( 2 !== count( $parts ) || ! preg_match( '/^[A-Za-z0-9]{20}$/', $parts[0] ) ) {
		return new WP_Error( 'flxgql_upload_id', __( 'The uploaded file was not found. Upload it again.', 'flx-graphql' ), array( 'status' => 404, 'field' => 'uploadId' ) );
	}
	$meta = flx_gql_arr( get_transient( 'flxgql_up_' . $parts[0] ) );
	if ( ! $meta || ! hash_equals( flx_gql_str( $meta['secret'] ?? '' ), wp_hash( $parts[1] ) ) || ( $meta['context'] ?? null ) !== $context || ! is_file( flx_gql_str( $meta['path'] ?? '' ) ) ) {
		return new WP_Error( 'flxgql_upload_id', __( 'The uploaded file was not found. Upload it again.', 'flx-graphql' ), array( 'status' => 404, 'field' => 'uploadId' ) );
	}
	$owner = flx_gql_int( $meta['user'] ?? 0 );
	if ( $owner && get_current_user_id() !== $owner ) {
		return new WP_Error( 'flxgql_upload_id', __( 'The uploaded file was not found. Upload it again.', 'flx-graphql' ), array( 'status' => 404, 'field' => 'uploadId' ) );
	}
	unset( $meta['secret'] );
	$meta['id'] = $parts[0];
	return $meta;
}

/**
 * Takes an upload over: returns { path, name, mime, size, … } and forgets it. The caller moves or copies
 * the file; whatever stays in the temporary folder is deleted after a day.
 *
 * @param string $upload_id "<id>.<secret>".
 * @param string $context   Expected context.
 * @return array<string,mixed>|WP_Error
 */
function flx_gql_upload_take( string $upload_id, string $context ) {
	$meta = flx_gql_upload_get( $upload_id, $context );
	if ( ! is_wp_error( $meta ) ) {
		delete_transient( 'flxgql_up_' . flx_gql_str( $meta['id'] ?? '' ) );
	}
	return $meta;
}
