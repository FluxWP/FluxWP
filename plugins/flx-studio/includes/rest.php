<?php
/**
 * REST: flx-studio/v1. Web arayüzü (çerez + nonce) ve Flux Masaüstü (Flux App belirteci) aynı uçları kullanır.
 * Yazma POST (bazı sunucular PATCH/DELETE'i keser). Belge: API.md.
 */

defined( 'ABSPATH' ) || exit;

function flxst_perm() {
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'rest_forbidden', __( 'Login required.', 'flx-studio' ), array( 'status' => 401 ) );
	}
	return flxst_can_use() ? true : new WP_Error( 'rest_forbidden', __( 'You do not have access to Studio.', 'flx-studio' ), array( 'status' => 403 ) );
}

function flxst_perm_manage() {
	return flxst_can_manage() ? true : new WP_Error( 'rest_forbidden', __( 'You are not allowed to do this.', 'flx-studio' ), array( 'status' => 403 ) );
}

function flxst_route( $path, $methods, $cb, $perm = 'flxst_perm' ) {
	register_rest_route( FLXST_NS, $path, array( 'methods' => $methods, 'callback' => $cb, 'permission_callback' => $perm ) );
}

function flxst_nf() {
	return new WP_Error( 'flxst_not_found', __( 'Record not found.', 'flx-studio' ), array( 'status' => 404 ) );
}

function flxst_boot_data() {
	$uid = get_current_user_id();
	$cat = flxst_models();
	$s   = flxst_settings();
	return array(
		'user'      => array(
			'id'     => $uid,
			'name'   => wp_get_current_user()->display_name,
			'manage' => flxst_can_manage(),
		),
		'ai'        => array(
			'media' => flxst_ai_ready(),
			'text'  => flxst_text_ready(),
			'setup' => current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=flx-ai-settings' ) : '',
		),
		'models'    => $cat,
		'settings'  => array(
			'image_model' => $s['image_model'] ? $s['image_model'] : (string) ( $cat['default_image'] ?? '' ),
			'video_model' => $s['video_model'] ? $s['video_model'] : (string) ( $cat['default_video'] ?? '' ),
			'max_n'       => (int) $s['max_n'],
			'max_batch'   => (int) $s['max_batch'],
			'text_model'    => flxst_can_manage() ? (string) $s['text_model'] : '',
			'shared_people' => (bool) $s['shared_people'],
		),
		'folders'   => flxst_folders( $uid ),
		'people'    => flxst_people( $uid ),
		'refs'      => flxst_refs( $uid ),
		'templates' => flxst_templates( $uid ),
		'budget'    => function_exists( 'flxai_budget_status' ) ? flxai_budget_status() : null,
		'version'   => FLXST_VERSION,
	);
}

/** Toplu kimlik listesi → düzenlenebilir kayıtlar. */
function flxst_editable_rows( $ids, $uid ) {
	$out = array();
	foreach ( array_slice( array_unique( array_map( 'intval', (array) $ids ) ), 0, 500 ) as $id ) {
		$r = flxst_item( $id );
		if ( flxst_item_editable( $r, $uid ) ) {
			$out[] = $r;
		}
	}
	return $out;
}

/** Yüklenen dosya: multipart `file` ya da JSON `data` (data URL). */
function flxst_request_file( WP_REST_Request $req, array $allowed, $sub, $max_mb = 25 ) {
	$files = $req->get_file_params();
	if ( ! empty( $files['file']['tmp_name'] ) ) {
		if ( ! empty( $files['file']['error'] ) ) {
			return new WP_Error( 'flxst_file', __( 'The file could not be uploaded.', 'flx-studio' ), array( 'status' => 400 ) );
		}
		return flxst_store_file( array( 'tmp' => $files['file']['tmp_name'], 'name' => $files['file']['name'] ), $sub, $allowed, $max_mb );
	}
	$d = flxst_parse_data_url( (string) $req->get_param( 'data' ), $max_mb );
	if ( ! $d ) {
		return new WP_Error( 'flxst_file', __( 'No file received.', 'flx-studio' ), array( 'status' => 400 ) );
	}
	$ext = explode( '/', $d[1] )[1] ?? 'bin';
	return flxst_store_file( array( 'data' => $d[0], 'name' => sanitize_file_name( (string) $req->get_param( 'name' ) ) . '.' . $ext ), $sub, $allowed, $max_mb );
}

function flxst_insert_ref( array $file, $uid, $person, $name, $role, $saved ) {
	global $wpdb;
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		flxst_table( 'refs' ),
		array(
			'user_id'   => $uid,
			'person_id' => (int) $person,
			'name'      => mb_substr( sanitize_text_field( (string) $name ), 0, 190 ),
			'role'      => sanitize_key( (string) $role ),
			'url'       => $file['url'],
			'path'      => $file['path'],
			'thumb'     => flxst_make_thumb( $file['path'], 'r' . wp_generate_password( 8, false ) ),
			'mime'      => $file['mime'],
			'width'     => $file['width'],
			'height'    => $file['height'],
			'saved'     => $saved ? 1 : 0,
			'created'   => time(),
		)
	);
	return flxst_format_ref( flxst_ref( (int) $wpdb->insert_id ) );
}

add_action(
	'rest_api_init',
	function () {
		flxst_route( '/boot', 'GET', 'flxst_boot_data' );

		flxst_route(
			'/items',
			'GET',
			function ( WP_REST_Request $r ) {
				return flxst_items_query(
					get_current_user_id(),
					array(
						'folder' => $r->get_param( 'folder' ) ?? 'all',
						'kind'   => (string) $r->get_param( 'kind' ),
						'fav'    => (bool) $r->get_param( 'fav' ),
						'batch'  => (string) $r->get_param( 'batch' ),
						'search' => (string) $r->get_param( 'q' ),
						'owner'  => (string) $r->get_param( 'owner' ),
						'before' => (int) $r->get_param( 'before' ),
						'page'   => (int) $r->get_param( 'page' ),
						'limit'  => (int) ( $r->get_param( 'limit' ) ? $r->get_param( 'limit' ) : 60 ),
					)
				);
			}
		);
		flxst_route(
			'/items/(?P<id>\d+)',
			'GET',
			function ( WP_REST_Request $r ) {
				$row = flxst_item( (int) $r['id'] );
				return flxst_item_visible( $row, get_current_user_id() ) ? flxst_format_item( $row ) : flxst_nf();
			}
		);
		flxst_route(
			'/items/(?P<id>\d+)',
			'POST',
			function ( WP_REST_Request $r ) {
				$uid = get_current_user_id();
				$row = flxst_item( (int) $r['id'] );
				if ( ! flxst_item_editable( $row, $uid ) ) {
					return flxst_nf();
				}
				$d = array();
				if ( null !== $r->get_param( 'favorite' ) ) {
					$d['favorite'] = rest_sanitize_boolean( $r->get_param( 'favorite' ) ) ? 1 : 0;
				}
				if ( null !== $r->get_param( 'title' ) ) {
					$d['title'] = mb_substr( sanitize_text_field( (string) $r->get_param( 'title' ) ), 0, 190 );
				}
				if ( null !== $r->get_param( 'folder' ) && flxst_folder_writable( (int) $r->get_param( 'folder' ), $uid ) ) {
					$d['folder_id'] = (int) $r->get_param( 'folder' );
				}
				if ( $d ) {
					flxst_item_update( (int) $row['id'], $d );
				}
				return flxst_format_item( flxst_item( (int) $row['id'] ) );
			}
		);
		flxst_route(
			'/items/delete',
			'POST',
			function ( WP_REST_Request $r ) {
				$n = 0;
				foreach ( flxst_editable_rows( $r->get_param( 'ids' ), get_current_user_id() ) as $row ) {
					if ( in_array( $row['state'], array( 'queued', 'running' ), true ) && function_exists( 'flxai_media_cancel' ) && $row['job'] ) {
						flxai_media_cancel( $row['job'] );
					}
					flxst_item_delete( $row );
					++$n;
				}
				return array( 'deleted' => $n );
			}
		);
		flxst_route(
			'/items/move',
			'POST',
			function ( WP_REST_Request $r ) {
				$uid    = get_current_user_id();
				$folder = (int) $r->get_param( 'folder' );
				if ( ! flxst_folder_writable( $folder, $uid ) ) {
					return new WP_Error( 'rest_forbidden', __( 'You cannot move items to this folder.', 'flx-studio' ), array( 'status' => 403 ) );
				}
				$n = 0;
				foreach ( flxst_editable_rows( $r->get_param( 'ids' ), $uid ) as $row ) {
					flxst_item_update( (int) $row['id'], array( 'folder_id' => $folder ) );
					++$n;
				}
				return array( 'moved' => $n );
			}
		);
		flxst_route(
			'/items/(?P<id>\d+)/library',
			'POST',
			function ( WP_REST_Request $r ) {
				if ( ! current_user_can( 'upload_files' ) ) {
					return new WP_Error( 'rest_forbidden', __( 'You are not allowed to add to the Media Library.', 'flx-studio' ), array( 'status' => 403 ) );
				}
				$row = flxst_item( (int) $r['id'] );
				if ( ! flxst_item_visible( $row, get_current_user_id() ) || 'done' !== $row['state'] ) {
					return flxst_nf();
				}
				$id = flxst_to_library( $row );
				return is_wp_error( $id ) ? $id : array( 'attachment' => $id, 'edit_url' => get_edit_post_link( $id, 'raw' ), 'url' => wp_get_attachment_url( $id ) );
			}
		);

		flxst_route(
			'/generate',
			'POST',
			function ( WP_REST_Request $r ) {
				$in = (array) $r->get_json_params();
				if ( ! $in ) {
					$in = $r->get_params();
				}
				$res = flxst_generate( $in, get_current_user_id() );
				return is_wp_error( $res ) ? $res : array( 'items' => $res );
			}
		);
		flxst_route(
			'/poll',
			'GET',
			function ( WP_REST_Request $r ) {
				$jobs = array_filter( explode( ',', (string) $r->get_param( 'jobs' ) ) );
				return array( 'items' => flxst_poll( $jobs, get_current_user_id() ) );
			}
		);
		flxst_route(
			'/jobs/(?P<job>flx[md]_[a-f0-9]+)/cancel',
			'POST',
			function ( WP_REST_Request $r ) {
				global $wpdb;
				$t   = flxst_table( 'items' );
				$job = (string) $r['job'];
				$real = 0 === strpos( $job, 'flxd_' ) ? get_transient( 'flxst_map_' . $job ) : '';
				$job  = $real ? (string) $real : $job; // Bu arada gönderildiyse gerçek iş.
				$row  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE job = %s LIMIT 1", $job ), ARRAY_A ); // phpcs:ignore WordPress.DB
				if ( ! flxst_item_editable( $row, get_current_user_id() ) ) {
					return flxst_nf();
				}
				if ( 0 === strpos( $row['job'], 'flxd_' ) ) {
					// Henüz gönderilmedi: kayıtlar iptal, gönderim yapılmaz (gönderim sürüyorsa iş ardından durdurulur).
					$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET state = 'cancelled', error = %s WHERE job = %s AND state IN ('queued','running')", __( 'Cancelled.', 'flx-studio' ), $row['job'] ) ); // phpcs:ignore WordPress.DB
					delete_transient( 'flxst_' . $row['job'] );
					return array( 'items' => flxst_poll( array( $row['job'] ), get_current_user_id() ) );
				}
				$st = function_exists( 'flxai_media_cancel' ) ? flxai_media_cancel( $row['job'] ) : null;
				if ( is_array( $st ) ) {
					flxst_apply_job( $st );
				}
				return array( 'items' => flxst_poll( array( $row['job'] ), get_current_user_id() ) );
			}
		);

		flxst_route(
			'/enhance',
			'POST',
			function ( WP_REST_Request $r ) {
				$refs = flxst_resolve_refs( (array) $r->get_param( 'refs' ), get_current_user_id() );
				$out  = flxst_enhance( (string) $r->get_param( 'prompt' ), sanitize_key( (string) $r->get_param( 'mode' ) ), is_wp_error( $refs ) ? array() : $refs[1], sanitize_text_field( (string) $r->get_param( 'model_label' ) ) );
				return is_wp_error( $out ) ? $out : array( 'prompt' => $out );
			}
		);
		flxst_route(
			'/brainstorm',
			'POST',
			function ( WP_REST_Request $r ) {
				$refs = flxst_resolve_refs( (array) $r->get_param( 'refs' ), get_current_user_id() );
				$out  = flxst_brainstorm( (string) $r->get_param( 'brief' ), (int) $r->get_param( 'count' ), sanitize_key( (string) $r->get_param( 'style' ) ), is_wp_error( $refs ) ? array() : $refs[1], 'video' === $r->get_param( 'mode' ) ? 'video' : 'image' );
				return is_wp_error( $out ) ? $out : array( 'ideas' => $out, 'batch' => strtolower( wp_generate_password( 12, false ) ) );
			}
		);

		/* Klasörler */
		flxst_route(
			'/folders',
			'POST',
			function ( WP_REST_Request $r ) {
				global $wpdb;
				$uid  = get_current_user_id();
				$name = mb_substr( sanitize_text_field( (string) $r->get_param( 'name' ) ), 0, 120 );
				if ( '' === $name ) {
					return new WP_Error( 'flxst_name', __( 'Folder name is required.', 'flx-studio' ), array( 'status' => 400 ) );
				}
				$parent = (int) $r->get_param( 'parent' );
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					flxst_table( 'folders' ),
					array(
						'user_id'   => $uid,
						'parent_id' => flxst_folder_writable( $parent, $uid ) ? $parent : 0,
						'name'      => $name,
						'shared'    => rest_sanitize_boolean( $r->get_param( 'shared' ) ) ? 1 : 0,
						'created'   => time(),
					)
				);
				return flxst_format_folder( flxst_folder( (int) $wpdb->insert_id ) );
			}
		);
		flxst_route(
			'/folders/(?P<id>\d+)',
			'POST',
			function ( WP_REST_Request $r ) {
				global $wpdb;
				$uid = get_current_user_id();
				if ( ! flxst_folder_owned( (int) $r['id'], $uid ) ) {
					return flxst_nf();
				}
				$d = array();
				if ( null !== $r->get_param( 'name' ) && '' !== trim( (string) $r->get_param( 'name' ) ) ) {
					$d['name'] = mb_substr( sanitize_text_field( (string) $r->get_param( 'name' ) ), 0, 120 );
				}
				if ( null !== $r->get_param( 'shared' ) ) {
					$d['shared'] = rest_sanitize_boolean( $r->get_param( 'shared' ) ) ? 1 : 0;
				}
				if ( null !== $r->get_param( 'parent' ) ) {
					$p = (int) $r->get_param( 'parent' );
					// Kendisinin altına taşınamaz.
					$x = $p;
					$ok = true;
					for ( $i = 0; $x && $i < 50; $i++ ) {
						if ( $x === (int) $r['id'] ) {
							$ok = false;
							break;
						}
						$f = flxst_folder( $x );
						$x = $f ? (int) $f['parent_id'] : 0;
					}
					if ( $ok && flxst_folder_writable( $p, $uid ) ) {
						$d['parent_id'] = $p;
					}
				}
				if ( $d ) {
					$wpdb->update( flxst_table( 'folders' ), $d, array( 'id' => (int) $r['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				}
				return flxst_format_folder( flxst_folder( (int) $r['id'] ) );
			}
		);
		flxst_route(
			'/folders/(?P<id>\d+)/delete',
			'POST',
			function ( WP_REST_Request $r ) {
				global $wpdb;
				$id = (int) $r['id'];
				$f  = flxst_folder( $id );
				if ( ! flxst_folder_owned( $id, get_current_user_id() ) ) {
					return flxst_nf();
				}
				// İçerik ve alt klasörler üst klasöre çıkar; üretimler silinmez.
				$wpdb->update( flxst_table( 'items' ), array( 'folder_id' => (int) $f['parent_id'] ), array( 'folder_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->update( flxst_table( 'folders' ), array( 'parent_id' => (int) $f['parent_id'] ), array( 'parent_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->delete( flxst_table( 'folders' ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				return array( 'deleted' => true );
			}
		);

		/* Kişiler */
		flxst_route(
			'/people',
			'POST',
			function ( WP_REST_Request $r ) {
				global $wpdb;
				$name = mb_substr( sanitize_text_field( (string) $r->get_param( 'name' ) ), 0, 120 );
				if ( '' === $name ) {
					return new WP_Error( 'flxst_name', __( 'Person name is required.', 'flx-studio' ), array( 'status' => 400 ) );
				}
				$wpdb->insert( flxst_table( 'people' ), array( 'user_id' => get_current_user_id(), 'name' => $name, 'shared' => 1, 'created' => time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				return array( 'id' => (int) $wpdb->insert_id, 'name' => $name, 'owner' => get_current_user_id(), 'photos' => array() );
			}
		);
		flxst_route(
			'/people/(?P<id>\d+)',
			'POST',
			function ( WP_REST_Request $r ) {
				global $wpdb;
				$p = flxst_person( (int) $r['id'] );
				if ( ! $p || ( (int) $p['user_id'] !== get_current_user_id() && ! flxst_can_manage() ) ) {
					return flxst_nf();
				}
				$name = mb_substr( sanitize_text_field( (string) $r->get_param( 'name' ) ), 0, 120 );
				if ( '' !== $name ) {
					$wpdb->update( flxst_table( 'people' ), array( 'name' => $name ), array( 'id' => (int) $p['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				}
				return array( 'ok' => true );
			}
		);
		flxst_route(
			'/people/(?P<id>\d+)/delete',
			'POST',
			function ( WP_REST_Request $r ) {
				global $wpdb;
				$p = flxst_person( (int) $r['id'] );
				if ( ! $p || ( (int) $p['user_id'] !== get_current_user_id() && ! flxst_can_manage() ) ) {
					return flxst_nf();
				}
				foreach ( flxst_refs( get_current_user_id(), array( 'person' => (int) $p['id'] ) ) as $ph ) {
					flxst_delete_ref( flxst_ref( $ph['id'] ) );
				}
				$wpdb->delete( flxst_table( 'people' ), array( 'id' => (int) $p['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				return array( 'deleted' => true );
			}
		);

		/* Referanslar (kişi fotoğrafları dahil) */
		flxst_route(
			'/refs',
			'GET',
			function () {
				return flxst_refs( get_current_user_id() );
			}
		);
		flxst_route(
			'/refs',
			'POST',
			function ( WP_REST_Request $r ) {
				$uid    = get_current_user_id();
				$person = (int) $r->get_param( 'person' );
				if ( $person ) {
					$p = flxst_person( $person );
					if ( ! flxst_person_visible( $p, $uid ) ) {
						return flxst_nf();
					}
				}
				$kind    = (string) $r->get_param( 'kind' );
				$allowed = in_array( $kind, array( 'video', 'audio' ), true ) ? FLXST_MEDIA_MIMES : FLXST_IMAGE_MIMES;
				$file    = flxst_request_file( $r, $allowed, 'refs', in_array( $kind, array( 'video', 'audio' ), true ) ? 100 : 25 );
				if ( is_wp_error( $file ) ) {
					return $file;
				}
				return flxst_insert_ref( $file, $uid, $person, (string) $r->get_param( 'name' ), (string) $r->get_param( 'role' ), $person || rest_sanitize_boolean( $r->get_param( 'saved' ) ) );
			}
		);
		flxst_route(
			'/refs/(?P<id>\d+)',
			'POST',
			function ( WP_REST_Request $r ) {
				global $wpdb;
				$row = flxst_ref( (int) $r['id'] );
				if ( ! $row || ( (int) $row['user_id'] !== get_current_user_id() && ! flxst_can_manage() ) ) {
					return flxst_nf();
				}
				$d = array();
				foreach ( array( 'name', 'role' ) as $k ) {
					if ( null !== $r->get_param( $k ) ) {
						$d[ $k ] = 'role' === $k ? sanitize_key( (string) $r->get_param( $k ) ) : mb_substr( sanitize_text_field( (string) $r->get_param( $k ) ), 0, 190 );
					}
				}
				if ( null !== $r->get_param( 'saved' ) ) {
					$d['saved'] = rest_sanitize_boolean( $r->get_param( 'saved' ) ) ? 1 : 0;
				}
				if ( $d ) {
					$wpdb->update( flxst_table( 'refs' ), $d, array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				}
				return flxst_format_ref( flxst_ref( (int) $row['id'] ) );
			}
		);
		flxst_route(
			'/refs/(?P<id>\d+)/delete',
			'POST',
			function ( WP_REST_Request $r ) {
				$row = flxst_ref( (int) $r['id'] );
				if ( ! $row || ( (int) $row['user_id'] !== get_current_user_id() && ! flxst_can_manage() ) ) {
					return flxst_nf();
				}
				flxst_delete_ref( $row );
				return array( 'deleted' => true );
			}
		);

		/* Şablonlar */
		flxst_route(
			'/templates',
			'POST',
			function ( WP_REST_Request $r ) {
				global $wpdb;
				$name = mb_substr( sanitize_text_field( (string) $r->get_param( 'name' ) ), 0, 120 );
				if ( '' === $name ) {
					return new WP_Error( 'flxst_name', __( 'Template name is required.', 'flx-studio' ), array( 'status' => 400 ) );
				}
				$params = (array) $r->get_param( 'params' );
				$keep   = array_intersect_key( $params, array_flip( array( 'kind', 'model', 'aspect', 'resolution', 'quality', 'size', 'n', 'duration', 'audio', 'mode', 'refs' ) ) );
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					flxst_table( 'templates' ),
					array(
						'user_id' => get_current_user_id(),
						'name'    => $name,
						'prompt'  => (string) $r->get_param( 'prompt' ),
						'params'  => flxst_json( $keep ),
						'shared'  => rest_sanitize_boolean( $r->get_param( 'shared' ) ) ? 1 : 0,
						'created' => time(),
					)
				);
				return array( 'templates' => flxst_templates( get_current_user_id() ) );
			}
		);
		flxst_route(
			'/templates/(?P<id>\d+)/delete',
			'POST',
			function ( WP_REST_Request $r ) {
				global $wpdb;
				$t   = flxst_table( 'templates' );
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $r['id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB
				if ( ! $row || ( (int) $row['user_id'] !== get_current_user_id() && ! flxst_can_manage() ) ) {
					return flxst_nf();
				}
				$wpdb->delete( $t, array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				return array( 'templates' => flxst_templates( get_current_user_id() ) );
			}
		);

		/* Pano ve ayarlar (yönetici) */
		flxst_route(
			'/stats',
			'GET',
			function ( WP_REST_Request $r ) {
				return flxst_stats( (int) ( $r->get_param( 'days' ) ?? 30 ) );
			},
			'flxst_perm_manage'
		);
		flxst_route(
			'/settings',
			'POST',
			function ( WP_REST_Request $r ) {
				$s = flxst_settings();
				foreach ( array( 'image_model', 'video_model', 'text_model' ) as $k ) {
					if ( null !== $r->get_param( $k ) ) {
						$s[ $k ] = sanitize_text_field( (string) $r->get_param( $k ) );
					}
				}
				foreach ( array( 'max_n' => array( 1, 8 ), 'max_batch' => array( 1, 40 ) ) as $k => $lim ) {
					if ( null !== $r->get_param( $k ) ) {
						$s[ $k ] = max( $lim[0], min( $lim[1], (int) $r->get_param( $k ) ) );
					}
				}
				if ( null !== $r->get_param( 'shared_people' ) ) {
					$s['shared_people'] = rest_sanitize_boolean( $r->get_param( 'shared_people' ) ) ? 1 : 0;
				}
				update_option( 'flxst_settings', $s, false );
				return flxst_settings();
			},
			'flxst_perm_manage'
		);
	}
);

function flxst_delete_ref( $row ) {
	global $wpdb;
	if ( ! $row ) {
		return;
	}
	foreach ( array( $row['path'], flxst_url_to_path( $row['thumb'] ) ) as $p ) {
		if ( $p && 0 === strpos( $p, flxst_upload_dir() ) && is_file( $p ) ) {
			wp_delete_file( $p );
		}
	}
	$wpdb->delete( flxst_table( 'refs' ), array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
}

/* Değişken veri: önbelleğe girmesin. */
add_filter(
	'rest_post_dispatch',
	function ( $res, $server, $req ) {
		if ( $res instanceof WP_HTTP_Response && 0 === strpos( (string) $req->get_route(), '/' . FLXST_NS ) ) {
			flx_rest_nocache( $res );
			$res->header( 'CDN-Cache-Control', 'no-store' );
			do_action( 'litespeed_control_set_nocache', 'flx-studio rest' );
		}
		return $res;
	},
	10,
	3
);
