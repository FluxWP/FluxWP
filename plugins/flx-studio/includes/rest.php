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
	return (int) $wpdb->insert_id;
}

/** Servis sonucu → REST cevabı (WP_Error aynen döner). */
function flxst_rest( $res, $map = null ) {
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return $map ? $map( $res ) : $res;
}

/** @param FLXST_Item[] $items */
function flxst_rest_items( array $items ) {
	return array( 'items' => array_map( static fn( $i ) => $i->to_rest(), $items ) );
}

/** @param FLXST_Template[] $tpls */
function flxst_rest_templates( array $tpls ) {
	return array( 'templates' => array_map( static fn( $t ) => $t->to_rest(), $tpls ) );
}

/** Kaynak liste: ["type" => ..., "id" => ...] (REST gövdesindeki refs). */
function flxst_ref_inputs( $refs ) {
	$out = array();
	foreach ( (array) $refs as $r ) {
		if ( is_array( $r ) ) {
			$out[] = array(
				'type' => (string) ( $r['type'] ?? '' ),
				'id'   => (int) ( $r['id'] ?? 0 ),
				'role' => (string) ( $r['role'] ?? '' ),
			);
		}
	}
	return $out;
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
				$item = FLXST_Service::item( (int) $r['id'] );
				return $item ? $item->to_rest() : flxst_nf();
			}
		);
		flxst_route(
			'/items/(?P<id>\d+)',
			'POST',
			function ( WP_REST_Request $r ) {
				$change = array();
				if ( null !== $r->get_param( 'favorite' ) ) {
					$change['favorite'] = (bool) rest_sanitize_boolean( $r->get_param( 'favorite' ) );
				}
				if ( null !== $r->get_param( 'title' ) ) {
					$change['title'] = (string) $r->get_param( 'title' );
				}
				if ( null !== $r->get_param( 'folder' ) ) {
					$change['folder'] = (int) $r->get_param( 'folder' );
				}
				return flxst_rest( FLXST_Service::item_update( (int) $r['id'], $change ), static fn( $i ) => $i->to_rest() );
			}
		);
		flxst_route(
			'/items/delete',
			'POST',
			function ( WP_REST_Request $r ) {
				return array( 'deleted' => count( FLXST_Service::items_delete( array_map( 'intval', (array) $r->get_param( 'ids' ) ) ) ) );
			}
		);
		flxst_route(
			'/items/move',
			'POST',
			function ( WP_REST_Request $r ) {
				return flxst_rest( FLXST_Service::items_move( array_map( 'intval', (array) $r->get_param( 'ids' ) ), (int) $r->get_param( 'folder' ) ), static fn( $ids ) => array( 'moved' => count( $ids ) ) );
			}
		);
		flxst_route(
			'/items/(?P<id>\d+)/library',
			'POST',
			function ( WP_REST_Request $r ) {
				return flxst_rest( FLXST_Service::item_to_library( (int) $r['id'] ), static fn( $l ) => array( 'attachment' => $l->attachment, 'edit_url' => $l->edit_url, 'url' => $l->url ) );
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
				return flxst_rest( FLXST_Service::generate( $in ), 'flxst_rest_items' );
			}
		);
		flxst_route(
			'/poll',
			'GET',
			function ( WP_REST_Request $r ) {
				$jobs = array_filter( explode( ',', (string) $r->get_param( 'jobs' ) ) );
				return flxst_rest_items( FLXST_Service::poll( array_values( $jobs ) ) );
			}
		);
		flxst_route(
			'/jobs/(?P<job>flx[md]_[a-f0-9]+)/cancel',
			'POST',
			function ( WP_REST_Request $r ) {
				return flxst_rest( FLXST_Service::cancel( (string) $r['job'] ), 'flxst_rest_items' );
			}
		);

		flxst_route(
			'/enhance',
			'POST',
			function ( WP_REST_Request $r ) {
				$out = FLXST_Service::enhance( (string) $r->get_param( 'prompt' ), (string) $r->get_param( 'mode' ), flxst_ref_inputs( $r->get_param( 'refs' ) ), (string) $r->get_param( 'model_label' ) );
				return flxst_rest( $out, static fn( $p ) => array( 'prompt' => $p ) );
			}
		);
		flxst_route(
			'/brainstorm',
			'POST',
			function ( WP_REST_Request $r ) {
				$out = FLXST_Service::brainstorm( (string) $r->get_param( 'brief' ), (int) $r->get_param( 'count' ), (string) $r->get_param( 'style' ), flxst_ref_inputs( $r->get_param( 'refs' ) ), 'video' === $r->get_param( 'mode' ) ? 'video' : 'image' );
				return flxst_rest(
					$out,
					static fn( $o ) => array(
						'ideas' => array_map( static fn( $i ) => array( 'title' => $i->title, 'prompt' => $i->prompt ), $o['ideas'] ),
						'batch' => $o['batch'],
					)
				);
			}
		);

		/* Klasörler */
		flxst_route(
			'/folders',
			'POST',
			function ( WP_REST_Request $r ) {
				return flxst_rest( FLXST_Service::folder_create( (string) $r->get_param( 'name' ), (int) $r->get_param( 'parent' ), (bool) rest_sanitize_boolean( $r->get_param( 'shared' ) ) ), static fn( $f ) => $f->to_rest() );
			}
		);
		flxst_route(
			'/folders/(?P<id>\d+)',
			'POST',
			function ( WP_REST_Request $r ) {
				return flxst_rest(
					FLXST_Service::folder_update(
						(int) $r['id'],
						null !== $r->get_param( 'name' ) ? (string) $r->get_param( 'name' ) : null,
						null !== $r->get_param( 'shared' ) ? (bool) rest_sanitize_boolean( $r->get_param( 'shared' ) ) : null,
						null !== $r->get_param( 'parent' ) ? (int) $r->get_param( 'parent' ) : null
					),
					static fn( $f ) => $f->to_rest()
				);
			}
		);
		flxst_route(
			'/folders/(?P<id>\d+)/delete',
			'POST',
			function ( WP_REST_Request $r ) {
				return flxst_rest( FLXST_Service::folder_delete( (int) $r['id'] ), static fn() => array( 'deleted' => true ) );
			}
		);

		/* Kişiler */
		flxst_route(
			'/people',
			'POST',
			function ( WP_REST_Request $r ) {
				return flxst_rest( FLXST_Service::person_create( (string) $r->get_param( 'name' ) ), static fn( $p ) => $p->to_rest() );
			}
		);
		flxst_route(
			'/people/(?P<id>\d+)',
			'POST',
			function ( WP_REST_Request $r ) {
				return flxst_rest( FLXST_Service::person_rename( (int) $r['id'], (string) $r->get_param( 'name' ) ), static fn() => array( 'ok' => true ) );
			}
		);
		flxst_route(
			'/people/(?P<id>\d+)/delete',
			'POST',
			function ( WP_REST_Request $r ) {
				return flxst_rest( FLXST_Service::person_delete( (int) $r['id'] ), static fn() => array( 'deleted' => true ) );
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
				$person = (int) $r->get_param( 'person' );
				if ( ! FLXST_Service::person_usable( $person ) ) {
					return flxst_nf();
				}
				$kind    = (string) $r->get_param( 'kind' );
				$allowed = in_array( $kind, array( 'video', 'audio' ), true ) ? FLXST_MEDIA_MIMES : FLXST_IMAGE_MIMES;
				$file    = flxst_request_file( $r, $allowed, 'refs', in_array( $kind, array( 'video', 'audio' ), true ) ? 100 : 25 );
				if ( is_wp_error( $file ) ) {
					return $file;
				}
				return flxst_rest( FLXST_Service::ref_create( $file, $person, (string) $r->get_param( 'name' ), (string) $r->get_param( 'role' ), (bool) rest_sanitize_boolean( $r->get_param( 'saved' ) ) ), static fn( $f ) => $f->to_rest() );
			}
		);
		flxst_route(
			'/refs/(?P<id>\d+)',
			'POST',
			function ( WP_REST_Request $r ) {
				return flxst_rest(
					FLXST_Service::ref_update(
						(int) $r['id'],
						null !== $r->get_param( 'name' ) ? (string) $r->get_param( 'name' ) : null,
						null !== $r->get_param( 'role' ) ? (string) $r->get_param( 'role' ) : null,
						null !== $r->get_param( 'saved' ) ? (bool) rest_sanitize_boolean( $r->get_param( 'saved' ) ) : null
					),
					static fn( $f ) => $f->to_rest()
				);
			}
		);
		flxst_route(
			'/refs/(?P<id>\d+)/delete',
			'POST',
			function ( WP_REST_Request $r ) {
				return flxst_rest( FLXST_Service::ref_delete( (int) $r['id'] ), static fn() => array( 'deleted' => true ) );
			}
		);

		/* Şablonlar */
		flxst_route(
			'/templates',
			'POST',
			function ( WP_REST_Request $r ) {
				return flxst_rest( FLXST_Service::template_create( (string) $r->get_param( 'name' ), (string) $r->get_param( 'prompt' ), (array) $r->get_param( 'params' ), (bool) rest_sanitize_boolean( $r->get_param( 'shared' ) ) ), 'flxst_rest_templates' );
			}
		);
		flxst_route(
			'/templates/(?P<id>\d+)/delete',
			'POST',
			function ( WP_REST_Request $r ) {
				return flxst_rest( FLXST_Service::template_delete( (int) $r['id'] ), 'flxst_rest_templates' );
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
				$in = array();
				foreach ( array( 'image_model', 'video_model', 'text_model', 'max_n', 'max_batch' ) as $k ) {
					if ( null !== $r->get_param( $k ) ) {
						$in[ $k ] = $r->get_param( $k );
					}
				}
				if ( null !== $r->get_param( 'shared_people' ) ) {
					$in['shared_people'] = (bool) rest_sanitize_boolean( $r->get_param( 'shared_people' ) );
				}
				FLXST_Service::settings_update( $in );
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
