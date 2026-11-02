<?php
/**
 * REST: flx-social/v1. Web arayüzü (çerez + nonce), Flux App belirteci ve MCP aynı işlevleri kullanır.
 * Yazma POST (bazı sunucular PATCH/DELETE'i keser). Belge: API.md.
 */

defined( 'ABSPATH' ) || exit;

function flxsm_perm() {
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'rest_forbidden', __( 'Giriş gerekli.', 'flx-social' ), array( 'status' => 401 ) );
	}
	return flxsm_can_post() ? true : new WP_Error( 'rest_forbidden', __( 'Sosyal medya için yetkiniz yok.', 'flx-social' ), array( 'status' => 403 ) );
}

function flxsm_perm_manage() {
	return flxsm_can_manage() ? true : new WP_Error( 'rest_forbidden', __( 'Bu işlem için yetkiniz yok.', 'flx-social' ), array( 'status' => 403 ) );
}

function flxsm_route( $path, $methods, $cb, $perm = 'flxsm_perm' ) {
	register_rest_route(
		FLXSM_NS,
		$path,
		array(
			'methods'             => $methods,
			'callback'            => $cb,
			'permission_callback' => $perm,
		)
	);
}

function flxsm_nf() {
	return new WP_Error( 'flxsm_not_found', __( 'Kayıt bulunamadı.', 'flx-social' ), array( 'status' => 404 ) );
}

/** Yayın yetkisi olmayan kullanıcı yalnız kendi taslağını/onay bekleyenini değiştirir. */
function flxsm_can_edit_post( array $p ) {
	if ( flxsm_can_publish() ) {
		return true;
	}
	return (int) $p['user_id'] === get_current_user_id() && in_array( $p['status'], array( 'draft', 'pending', 'failed', 'cancelled' ), true );
}

/* LiteSpeed/CDN önbelleği değişken veriyi tutmasın. */
add_filter(
	'rest_post_dispatch',
	function ( $res, $server, $req ) {
		if ( 0 === strpos( $req->get_route(), '/' . FLXSM_NS ) && $res instanceof WP_REST_Response ) {
			$res->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
			$res->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
			$res->header( 'CDN-Cache-Control', 'no-store' );
			do_action( 'litespeed_control_set_nocache', 'flx-social rest' );
		}
		return $res;
	},
	10,
	3
);

/** Arayüzün açılış verisi. */
function flxsm_boot_data() {
	$provs = array();
	foreach ( flxsm_providers() as $id => $p ) {
		$provs[ $id ] = array(
			'id'    => $id,
			'label' => $p->label(),
			'color' => $p->color(),
			'caps'  => $p->caps(),
		);
	}
	$s    = flxsm_settings();
	$data = array(
		'user'      => array(
			'id'      => get_current_user_id(),
			'name'    => wp_get_current_user()->display_name,
			'publish' => flxsm_can_publish(),
			'manage'  => flxsm_can_manage(),
			'upload'  => current_user_can( 'upload_files' ),
		),
		'providers' => $provs,
		'accounts'  => flxsm_accounts(),
		'ai'        => flxsm_ai_ready(),
		'studio'    => function_exists( 'flxst_table' ),
		'settings'  => array(
			'approval'  => (int) $s['approval'],
			'signature' => (string) $s['signature'],
			'x_tier'    => (string) $s['x_tier'],
		),
		'tz'        => wp_timezone_string(),
		'tz_offset' => (int) wp_timezone()->getOffset( new DateTime( 'now' ) ),
		'week'      => (int) get_option( 'start_of_week', 1 ),
		'counts'    => flxsm_counts(),
		'version'   => FLXSM_VERSION,
	);
	if ( flxsm_can_manage() ) {
		$data['apps']   = flxsm_apps_public();
		$data['admin']  = flxsm_admin_settings();
	}
	return $data;
}

function flxsm_counts() {
	global $wpdb;
	$pt = flxsm_table( 'posts' );
	return array(
		'pending'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$pt} WHERE status = 'pending'" ), // phpcs:ignore WordPress.DB
		'scheduled' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$pt} WHERE status IN ('scheduled','publishing')" ), // phpcs:ignore WordPress.DB
		'failed'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$pt} WHERE status IN ('failed','partial')" ), // phpcs:ignore WordPress.DB
	);
}

/** Yönetici ayarları (gizli değer yok). */
function flxsm_admin_settings() {
	$s = flxsm_settings();
	unset( $s['apps'] );
	$s['tick_url']   = add_query_arg( 'key', (string) get_option( 'flxsm_tick_key', '' ), rest_url( FLXSM_NS . '/tick' ) );
	$s['last_tick']  = (array) get_option( 'flxsm_last_tick', array() );
	$s['wa_choices'] = flxsm_wa_choices();
	$s['x_tiers']    = FLXSM_X::tiers();
	$s['x_month']    = flxsm_x_month_count();
	$s['post_types'] = array_values(
		array_map(
			fn( $pt ) => array(
				'id'    => $pt->name,
				'label' => $pt->labels->singular_name,
			),
			get_post_types( array( 'public' => true ), 'objects' )
		)
	);
	return $s;
}

add_action(
	'rest_api_init',
	function () {
		flxsm_route( '/boot', 'GET', fn() => rest_ensure_response( flxsm_boot_data() ) );

		/* ---------- Hesaplar ---------- */
		flxsm_route( '/accounts', 'GET', fn() => rest_ensure_response( flxsm_accounts() ) );
		flxsm_route(
			'/accounts/(?P<id>\d+)',
			'POST',
			function ( WP_REST_Request $r ) {
				$a = flxsm_account_row( $r['id'] );
				if ( ! $a ) {
					return flxsm_nf();
				}
				$patch = array();
				if ( null !== $r->get_param( 'disabled' ) ) {
					$patch['status'] = $r->get_param( 'disabled' ) ? 'disabled' : 'active';
				}
				if ( null !== $r->get_param( 'name' ) ) {
					$patch['name'] = mb_substr( sanitize_text_field( (string) $r->get_param( 'name' ) ), 0, 190 );
				}
				if ( null !== $r->get_param( 'sort' ) ) {
					$patch['sort'] = (int) $r->get_param( 'sort' );
				}
				if ( $patch ) {
					flxsm_update_account( $a['id'], $patch );
				}
				return rest_ensure_response( flxsm_account_public( flxsm_account_row( $a['id'] ) ) );
			},
			'flxsm_perm_manage'
		);
		flxsm_route(
			'/accounts/order',
			'POST',
			function ( WP_REST_Request $r ) {
				foreach ( array_values( (array) $r->get_param( 'ids' ) ) as $i => $id ) {
					flxsm_update_account( (int) $id, array( 'sort' => $i + 1 ) );
				}
				return rest_ensure_response( flxsm_accounts() );
			},
			'flxsm_perm_manage'
		);
		flxsm_route(
			'/accounts/(?P<id>\d+)/delete',
			'POST',
			function ( WP_REST_Request $r ) {
				flxsm_delete_account( (int) $r['id'] );
				return rest_ensure_response( array( 'deleted' => true ) );
			},
			'flxsm_perm_manage'
		);
		flxsm_route(
			'/accounts/(?P<id>\d+)/refresh',
			'POST',
			function ( WP_REST_Request $r ) {
				$acc  = flxsm_account( $r['id'] );
				$prov = $acc ? flxsm_provider( $acc['provider'] ) : null;
				if ( ! $prov ) {
					return flxsm_nf();
				}
				$acc = $prov->ensure( $acc );
				if ( is_wp_error( $acc ) ) {
					flxsm_account_failed( (int) $r['id'], $acc->get_error_message(), true );
					return new WP_Error( 'flxsm_expired', $acc->get_error_message(), array( 'status' => 400 ) );
				}
				if ( $prov instanceof FLXSM_Pinterest ) {
					$prov->reload_boards( $acc );
				}
				$m = $prov->account_metrics( $acc );
				if ( is_wp_error( $m ) && 'flxsm_unsupported' !== $m->get_error_code() ) {
					$d = (array) $m->get_error_data();
					flxsm_account_failed( $acc['id'], $m->get_error_message(), ! empty( $d['auth'] ) );
					return new WP_Error( 'flxsm_api', $m->get_error_message(), array( 'status' => 400 ) );
				}
				flxsm_update_account(
					$acc['id'],
					array(
						'last_error' => '',
						'status'     => 'disabled' === $acc['status'] ? 'disabled' : 'active',
					)
				);
				flxsm_collect_metrics( 20, (int) $acc['id'] );
				return rest_ensure_response( flxsm_account_public( flxsm_account_row( $acc['id'] ) ) );
			},
			'flxsm_perm_manage'
		);

		/* ---------- WhatsApp hedefleri ---------- */
		flxsm_route(
			'/whatsapp/targets',
			'GET',
			function () {
				$t = flxsm_provider( 'whatsapp' )->targets();
				if ( is_wp_error( $t ) ) {
					return new WP_Error( $t->get_error_code(), $t->get_error_message(), array( 'status' => 400 ) );
				}
				foreach ( $t as &$x ) {
					$x['exists'] = flxsm_account_exists( 'whatsapp', $x['ext_id'] );
				}
				return rest_ensure_response( array( 'items' => $t ) );
			},
			'flxsm_perm_manage'
		);
		flxsm_route(
			'/whatsapp/add',
			'POST',
			function ( WP_REST_Request $r ) {
				$cfg = flxsm_wa_config();
				if ( is_wp_error( $cfg ) ) {
					return $cfg;
				}
				$n = 0;
				foreach ( (array) $r->get_param( 'items' ) as $it ) {
					$ext = sanitize_text_field( (string) ( $it['ext_id'] ?? '' ) );
					if ( '' === $ext ) {
						continue;
					}
					flxsm_upsert_account(
						array(
							'provider' => 'whatsapp',
							'via'      => 'evolution',
							'ext_id'   => $ext,
							'name'     => sanitize_text_field( (string) ( $it['name'] ?? $ext ) ),
							'username' => $cfg['instance'],
							'avatar'   => esc_url_raw( (string) ( $it['avatar'] ?? '' ) ),
							'token'    => array(),
							'expires'  => 0,
							'meta'     => array( 'kind' => 'status' === ( $it['kind'] ?? '' ) ? 'status' : 'group' ),
						)
					);
					++$n;
				}
				return rest_ensure_response(
					array(
						'added'    => $n,
						'accounts' => flxsm_accounts(),
					)
				);
			},
			'flxsm_perm_manage'
		);

		/* ---------- Gönderiler ---------- */
		flxsm_route(
			'/posts',
			'GET',
			function ( WP_REST_Request $r ) {
				return rest_ensure_response(
					flxsm_query_posts(
						array(
							'status'   => (string) $r->get_param( 'status' ),
							'account'  => (int) $r->get_param( 'account' ),
							'provider' => (string) $r->get_param( 'provider' ),
							'q'        => (string) $r->get_param( 'q' ),
							'tag'      => (string) $r->get_param( 'tag' ),
							'mine'     => (bool) $r->get_param( 'mine' ),
							'from'     => (int) $r->get_param( 'from' ),
							'to'       => (int) $r->get_param( 'to' ),
							'page'     => (int) $r->get_param( 'page' ),
							'per'      => (int) ( $r->get_param( 'per' ) ? $r->get_param( 'per' ) : 30 ),
							'asc'      => (bool) $r->get_param( 'asc' ),
							'full'     => (bool) $r->get_param( 'full' ),
						)
					)
				);
			}
		);
		flxsm_route(
			'/posts',
			'POST',
			function ( WP_REST_Request $r ) {
				$id = flxsm_save_post( flxsm_post_input( $r ) );
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				return rest_ensure_response( flxsm_post_public( flxsm_post_row( $id ) ) );
			}
		);
		flxsm_route(
			'/posts/(?P<id>\d+)',
			'GET',
			function ( WP_REST_Request $r ) {
				$p = flxsm_post_row( $r['id'] );
				return $p ? rest_ensure_response( flxsm_post_public( $p ) ) : flxsm_nf();
			}
		);
		flxsm_route(
			'/posts/(?P<id>\d+)',
			'POST',
			function ( WP_REST_Request $r ) {
				$p = flxsm_post_row( $r['id'] );
				if ( ! $p ) {
					return flxsm_nf();
				}
				if ( ! flxsm_can_edit_post( $p ) ) {
					return new WP_Error( 'rest_forbidden', __( 'Bu gönderiyi değiştiremezsiniz.', 'flx-social' ), array( 'status' => 403 ) );
				}
				$id = flxsm_save_post( flxsm_post_input( $r ), (int) $p['id'] );
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				return rest_ensure_response( flxsm_post_public( flxsm_post_row( $id ) ) );
			}
		);
		flxsm_route(
			'/posts/(?P<id>\d+)/action',
			'POST',
			function ( WP_REST_Request $r ) {
				$p = flxsm_post_row( $r['id'] );
				if ( ! $p ) {
					return flxsm_nf();
				}
				$act = sanitize_key( (string) $r->get_param( 'action' ) );
				if ( ( 'approve' === $act && ! flxsm_can_publish() ) || ! flxsm_can_edit_post( $p ) ) {
					return new WP_Error( 'rest_forbidden', __( 'Bu işlem için yetkiniz yok.', 'flx-social' ), array( 'status' => 403 ) );
				}
				if ( 'reject' === $act ) {
					global $wpdb;
					$wpdb->update( // phpcs:ignore WordPress.DB
						flxsm_table( 'posts' ),
						array(
							'status' => 'draft',
							'note'   => sanitize_textarea_field( (string) $r->get_param( 'note' ) ),
						),
						array( 'id' => (int) $p['id'] )
					);
					do_action( 'flxsm_post_rejected', (int) $p['id'] );
				} else {
					$res = flxsm_apply_action( (int) $p['id'], $act );
					if ( is_wp_error( $res ) ) {
						return $res;
					}
					flxsm_refresh_post_status( (int) $p['id'] );
				}
				return rest_ensure_response( flxsm_post_public( flxsm_post_row( $p['id'] ) ) );
			}
		);
		flxsm_route(
			'/posts/(?P<id>\d+)/delete',
			'POST',
			function ( WP_REST_Request $r ) {
				$p = flxsm_post_row( $r['id'] );
				if ( ! $p ) {
					return flxsm_nf();
				}
				if ( ! flxsm_can_edit_post( $p ) ) {
					return new WP_Error( 'rest_forbidden', __( 'Bu gönderiyi silemezsiniz.', 'flx-social' ), array( 'status' => 403 ) );
				}
				$left = flxsm_delete_post( (int) $p['id'], (bool) $r->get_param( 'remote' ) );
				if ( $left ) {
					return new WP_Error( 'flxsm_remote', __( 'Bazı ağlarda silinemedi; gönderi listede kaldı.', 'flx-social' ) . "\n" . implode( "\n", $left ), array( 'status' => 400 ) );
				}
				return rest_ensure_response( array( 'deleted' => true ) );
			}
		);
		flxsm_route(
			'/posts/bulk',
			'POST',
			function ( WP_REST_Request $r ) {
				$act  = sanitize_key( (string) $r->get_param( 'action' ) );
				$done = 0;
				$errs = array();
				foreach ( array_map( 'intval', (array) $r->get_param( 'ids' ) ) as $id ) {
					$p = flxsm_post_row( $id );
					if ( ! $p || ! flxsm_can_edit_post( $p ) ) {
						continue;
					}
					if ( 'delete' === $act ) {
						$left = flxsm_delete_post( $id, false );
					} else {
						$left = flxsm_apply_action( $id, $act );
						flxsm_refresh_post_status( $id );
					}
					if ( is_wp_error( $left ) ) {
						$errs[] = '#' . $id . ': ' . $left->get_error_message();
					} else {
						++$done;
					}
				}
				return rest_ensure_response(
					array(
						'done'   => $done,
						'errors' => $errs,
					)
				);
			}
		);
		flxsm_route(
			'/posts/(?P<id>\d+)/duplicate',
			'POST',
			function ( WP_REST_Request $r ) {
				$p = flxsm_post_row( $r['id'] );
				if ( ! $p ) {
					return flxsm_nf();
				}
				$targets = array();
				foreach ( flxsm_targets_of( $p['id'] ) as $t ) {
					$targets[] = array(
						'account_id' => (int) $t['account_id'],
						'content'    => $t['content'],
						'media'      => null === $t['media'] ? null : flxsm_dec( $t['media'] ),
						'options'    => flxsm_dec( $t['options'] ),
					);
				}
				$id = flxsm_save_post(
					array(
						/* translators: %s: title */
						'title'   => $p['title'] ? sprintf( __( '%s (kopya)', 'flx-social' ), $p['title'] ) : '',
						'content' => $p['content'],
						'media'   => flxsm_dec( $p['media'] ),
						'tags'    => explode( ',', (string) $p['tags'] ),
						'targets' => $targets,
						'source'  => 'duplicate',
					)
				);
				return is_wp_error( $id ) ? $id : rest_ensure_response( flxsm_post_public( flxsm_post_row( $id ) ) );
			}
		);

		/* ---------- Hedef (ağdaki gönderi) ---------- */
		flxsm_route(
			'/targets/(?P<id>\d+)/retry',
			'POST',
			function ( WP_REST_Request $r ) {
				if ( ! flxsm_can_publish() ) {
					return new WP_Error( 'rest_forbidden', __( 'Bu işlem için yetkiniz yok.', 'flx-social' ), array( 'status' => 403 ) );
				}
				$res = flxsm_retry_target( (int) $r['id'] );
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				$t = flxsm_target_row( $r['id'] );
				return rest_ensure_response( flxsm_post_public( flxsm_post_row( $t['post_id'] ) ) );
			}
		);
		flxsm_route(
			'/targets/(?P<id>\d+)/remote-edit',
			'POST',
			function ( WP_REST_Request $r ) {
				if ( ! flxsm_can_publish() ) {
					return new WP_Error( 'rest_forbidden', __( 'Bu işlem için yetkiniz yok.', 'flx-social' ), array( 'status' => 403 ) );
				}
				$res = flxsm_remote_edit( (int) $r['id'], null === $r->get_param( 'content' ) ? null : (string) $r->get_param( 'content' ), (array) $r->get_param( 'options' ) );
				if ( is_wp_error( $res ) ) {
					return new WP_Error( $res->get_error_code(), $res->get_error_message(), array( 'status' => 400 ) );
				}
				$t = flxsm_target_row( $r['id'] );
				return rest_ensure_response( flxsm_post_public( flxsm_post_row( $t['post_id'] ) ) );
			}
		);
		flxsm_route(
			'/targets/(?P<id>\d+)/remote-delete',
			'POST',
			function ( WP_REST_Request $r ) {
				if ( ! flxsm_can_publish() ) {
					return new WP_Error( 'rest_forbidden', __( 'Bu işlem için yetkiniz yok.', 'flx-social' ), array( 'status' => 403 ) );
				}
				$res = flxsm_remote_delete( (int) $r['id'] );
				if ( is_wp_error( $res ) ) {
					return new WP_Error( $res->get_error_code(), $res->get_error_message(), array( 'status' => 400 ) );
				}
				$t = flxsm_target_row( $r['id'] );
				return rest_ensure_response( flxsm_post_public( flxsm_post_row( $t['post_id'] ) ) );
			}
		);

		/* ---------- Pano ---------- */
		flxsm_route( '/stats', 'GET', fn( WP_REST_Request $r ) => rest_ensure_response( flxsm_dashboard( (int) $r->get_param( 'days' ) ) ) );
		flxsm_route(
			'/stats/refresh',
			'POST',
			function () {
				$n = flxsm_collect_metrics( 25 );
				return rest_ensure_response(
					array(
						'updated' => $n,
						'stats'   => flxsm_dashboard( 30 ),
					)
				);
			},
			'flxsm_perm_manage'
		);
		flxsm_route( '/counts', 'GET', fn() => rest_ensure_response( flxsm_counts() + array( 'due' => flxsm_due_count( 0 ) ) ) );

		/* ---------- AI ---------- */
		flxsm_route(
			'/ai',
			'POST',
			function ( WP_REST_Request $r ) {
				$res = flxsm_ai_write(
					sanitize_key( (string) $r->get_param( 'mode' ) ),
					array(
						'text'      => (string) $r->get_param( 'text' ),
						'brief'     => (string) $r->get_param( 'brief' ),
						'providers' => array_map( 'sanitize_key', (array) $r->get_param( 'providers' ) ),
						'tone'      => sanitize_text_field( (string) $r->get_param( 'tone' ) ),
						'limit'     => (int) $r->get_param( 'limit' ),
						'to'        => sanitize_text_field( (string) $r->get_param( 'to' ) ),
					)
				);
				if ( is_wp_error( $res ) ) {
					$d = (array) $res->get_error_data();
					return new WP_Error( $res->get_error_code(), $res->get_error_message(), array( 'status' => (int) ( $d['status'] ?? 400 ) ) );
				}
				return rest_ensure_response( array( 'result' => $res ) );
			}
		);

		/* ---------- Medya ---------- */
		flxsm_route(
			'/media',
			'GET',
			function ( WP_REST_Request $r ) {
				$ids = array_filter( array_map( 'absint', explode( ',', (string) $r->get_param( 'ids' ) ) ) );
				if ( $ids ) {
					return rest_ensure_response( flxsm_media_public( $ids ) );
				}
				$q = new WP_Query(
					array(
						'post_type'      => 'attachment',
						'post_status'    => 'inherit',
						'post_mime_type' => array( 'image', 'video' ),
						'posts_per_page' => 40,
						'paged'          => max( 1, (int) $r->get_param( 'page' ) ),
						's'              => (string) $r->get_param( 'q' ),
						'fields'         => 'ids',
						'orderby'        => 'date',
						'order'          => 'DESC',
					)
				);
				return rest_ensure_response(
					array(
						'items' => flxsm_media_public( $q->posts ),
						'pages' => (int) $q->max_num_pages,
					)
				);
			}
		);
		flxsm_route(
			'/media',
			'POST',
			function ( WP_REST_Request $r ) {
				if ( ! current_user_can( 'upload_files' ) ) {
					return new WP_Error( 'rest_forbidden', __( 'Dosya yükleme yetkiniz yok.', 'flx-social' ), array( 'status' => 403 ) );
				}
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$url = esc_url_raw( (string) $r->get_param( 'url' ) );
				if ( $url ) {
					$id = media_sideload_image( $url, 0, null, 'id' );
					if ( is_wp_error( $id ) ) {
						$tmp = download_url( $url, 120 );
						if ( is_wp_error( $tmp ) ) {
							return new WP_Error( 'flxsm_media', $tmp->get_error_message(), array( 'status' => 400 ) );
						}
						$id = media_handle_sideload(
							array(
								'name'     => basename( wp_parse_url( $url, PHP_URL_PATH ) ),
								'tmp_name' => $tmp,
							),
							0
						);
					}
				} else {
					$files = $r->get_file_params();
					if ( empty( $files['file'] ) ) {
						return new WP_Error( 'flxsm_media', __( 'Dosya gelmedi.', 'flx-social' ), array( 'status' => 400 ) );
					}
					$id = media_handle_sideload( $files['file'], 0 );
				}
				if ( is_wp_error( $id ) ) {
					return new WP_Error( 'flxsm_media', $id->get_error_message(), array( 'status' => 400 ) );
				}
				$poster = (int) $r->get_param( 'poster' );
				if ( $poster ) {
					update_post_meta( (int) $id, '_flxsm_poster', $poster );
				}
				$item = flxsm_media_item( (int) $id );
				unset( $item['path'] );
				return rest_ensure_response( $item );
			}
		);
		flxsm_route(
			'/media/studio',
			'GET',
			function () {
				global $wpdb;
				if ( ! function_exists( 'flxst_table' ) ) {
					return rest_ensure_response( array( 'items' => array() ) );
				}
				$t    = flxst_table( 'items' );
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, kind, url, thumb, title, prompt, attachment_id, width, height FROM {$t} WHERE state = 'done' AND url <> '' AND (user_id = %d OR %d = 1) ORDER BY id DESC LIMIT 60", get_current_user_id(), current_user_can( 'flxst_manage' ) ? 1 : 0 ), ARRAY_A ); // phpcs:ignore WordPress.DB
				return rest_ensure_response( array( 'items' => (array) $rows ) );
			}
		);

		/* ---------- Ayarlar ---------- */
		flxsm_route(
			'/settings',
			'POST',
			function ( WP_REST_Request $r ) {
				$in    = (array) $r->get_json_params();
				$patch = array();
				foreach ( array( 'approval', 'utm', 'x_premium', 'pin_sandbox', 'li_org' ) as $k ) {
					if ( isset( $in[ $k ] ) ) {
						$patch[ $k ] = $in[ $k ] ? 1 : 0;
					}
				}
				foreach ( array( 'x_month_limit', 'wa_conn' ) as $k ) {
					if ( isset( $in[ $k ] ) ) {
						$patch[ $k ] = absint( $in[ $k ] );
					}
				}
				if ( isset( $in['signature'] ) ) {
					$patch['signature'] = sanitize_textarea_field( (string) $in['signature'] );
				}
				if ( isset( $in['meta_version'] ) && preg_match( '/^v\d+\.\d+$/', (string) $in['meta_version'] ) ) {
					$patch['meta_version'] = (string) $in['meta_version'];
				}
				if ( isset( $in['x_tier'] ) && isset( FLXSM_X::tiers()[ $in['x_tier'] ] ) ) {
					$patch['x_tier'] = (string) $in['x_tier'];
				}
				if ( isset( $in['tiktok_mode'] ) ) {
					$patch['tiktok_mode'] = 'direct' === $in['tiktok_mode'] ? 'direct' : 'inbox';
				}
				if ( isset( $in['auto'] ) && is_array( $in['auto'] ) ) {
					$a             = $in['auto'];
					$patch['auto'] = array(
						'enabled'    => ! empty( $a['enabled'] ) ? 1 : 0,
						'post_types' => array_values( array_filter( array_map( 'sanitize_key', (array) ( $a['post_types'] ?? array() ) ) ) ),
						'accounts'   => array_values( array_filter( array_map( 'absint', (array) ( $a['accounts'] ?? array() ) ) ) ),
						'template'   => sanitize_textarea_field( (string) ( $a['template'] ?? '' ) ),
						'mode'       => 'schedule' === ( $a['mode'] ?? '' ) ? 'schedule' : 'draft',
						'delay'      => min( 1440, absint( $a['delay'] ?? 10 ) ),
						'image'      => ! empty( $a['image'] ) ? 1 : 0,
					);
				}
				flxsm_save_settings( $patch );
				if ( ! empty( $in['regen_key'] ) ) {
					update_option( 'flxsm_tick_key', wp_generate_password( 32, false ), false );
				}
				return rest_ensure_response( flxsm_boot_data() );
			},
			'flxsm_perm_manage'
		);
		flxsm_route(
			'/settings/app/(?P<app>[a-z]+)',
			'POST',
			function ( WP_REST_Request $r ) {
				$res = flxsm_save_app( (string) $r['app'], (array) $r->get_json_params() );
				return is_wp_error( $res ) ? $res : rest_ensure_response( flxsm_apps_public() );
			},
			'flxsm_perm_manage'
		);
		flxsm_route(
			'/log',
			'GET',
			function () {
				global $wpdb;
				$rows = $wpdb->get_results( 'SELECT * FROM ' . flxsm_table( 'log' ) . ' ORDER BY id DESC LIMIT 200', ARRAY_A ); // phpcs:ignore WordPress.DB
				return rest_ensure_response( (array) $rows );
			},
			'flxsm_perm_manage'
		);
	}
);

/** İstekten gönderi verisi. */
function flxsm_post_input( WP_REST_Request $r ) {
	$in  = (array) $r->get_json_params();
	$out = array();
	foreach ( array( 'title', 'content', 'media', 'tags', 'scheduled_at', 'repeat_days', 'note', 'targets', 'action', 'source' ) as $k ) {
		if ( array_key_exists( $k, $in ) ) {
			$out[ $k ] = $in[ $k ];
		}
	}
	if ( isset( $out['source'] ) && ! in_array( $out['source'], array( 'manual', 'ai', 'app' ), true ) ) {
		unset( $out['source'] );
	}
	return $out;
}
