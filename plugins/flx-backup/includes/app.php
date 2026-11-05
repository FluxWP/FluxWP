<?php
/**
 * Flux App: yedek listesi, şimdi yedekle, durum. Geri yükleme web görünümünde kalır.
 * REST: flx-backup/v1/backups
 *
 * @package flx-backup
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param mixed $node
 * @return mixed
 */
function flxy_app_texts( $node, $domain ) {
	if ( ! is_array( $node ) ) {
		return $node;
	}
	foreach ( $node as $k => $v ) {
		if ( 'text' === $k && is_string( $v ) && false === strpos( $v, '{{' ) ) {
			$node[ $k ] = translate( $v, $domain ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain
		} else {
			$node[ $k ] = flxy_app_texts( $v, $domain );
		}
	}
	return $node;
}

function flxy_app_can() {
	return current_user_can( 'manage_options' );
}

function flxy_app_nocache( $res, $server, $req ) {
	unset( $server );
	if ( $res instanceof WP_REST_Response && 0 === strpos( (string) $req->get_route(), '/flx-backup/v1' ) ) {
		$res->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$res->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
		$res->header( 'CDN-Cache-Control', 'no-store' );
		do_action( 'litespeed_control_set_nocache', 'flx-backup rest' );
	}
	return $res;
}
add_filter( 'rest_post_dispatch', 'flxy_app_nocache', 10, 3 );

function flxy_app_type_label( $type ) {
	$labels = array(
		'full'       => __( 'Tam yedek', 'flx-backup' ),
		'db'         => __( 'Veritabanı', 'flx-backup' ),
		'files'      => __( 'Dosyalar', 'flx-backup' ),
		'pre_update' => __( 'Güncelleme öncesi', 'flx-backup' ),
		'manual'     => __( 'Elle', 'flx-backup' ),
	);
	return isset( $labels[ $type ] ) ? $labels[ $type ] : (string) $type;
}

function flxy_app_status_label( $status ) {
	$labels = array(
		'running' => __( 'Sürüyor', 'flx-backup' ),
		'done'    => __( 'Tamam', 'flx-backup' ),
		'ok'      => __( 'Tamam', 'flx-backup' ),
		'failed'  => __( 'Hata', 'flx-backup' ),
	);
	return isset( $labels[ $status ] ) ? $labels[ $status ] : (string) $status;
}

/** Uygulamaya giden kayıt: sunucu yolu yok. */
function flxy_app_public( array $r ) {
	unset( $r['file'] );
	$r['type_label']    = flxy_app_type_label( (string) $r['type'] );
	$r['status_label']  = flxy_app_status_label( (string) $r['status'] );
	$r['title']         = '' !== (string) $r['label'] ? (string) $r['label'] : $r['type_label'];
	$r['size_label']    = $r['size'] ? size_format( (int) $r['size'], 1 ) : '';
	$r['created_label'] = function_exists( 'flx_date' ) ? flx_date( (int) $r['created'], 'datetime' ) : '';
	$r['percent']       = null;
	$r['progress']      = '';
	if ( in_array( $r['status'], array( 'running' ), true ) && class_exists( 'FLXY_Runner' ) && class_exists( 'FLXY_Backup' ) ) {
		$state = FLXY_Runner::load_backup();
		if ( is_array( $state ) && (string) ( $state['id'] ?? '' ) === (string) $r['id'] ) {
			$p              = FLXY_Backup::progress( $state );
			$r['percent']   = (int) ( $p['percent'] ?? 0 );
			$r['progress']  = (string) ( $p['text'] ?? '' );
		}
	}
	return $r;
}

function flxy_app_list( WP_REST_Request $req ) {
	$args = array();
	if ( $req->get_param( 'type' ) ) {
		$args['type'] = sanitize_key( (string) $req->get_param( 'type' ) );
	}
	if ( $req->get_param( 'status' ) ) {
		$args['status'] = sanitize_key( (string) $req->get_param( 'status' ) );
	}
	$all   = flxy_backup_list( $args );
	$page  = max( 1, (int) $req->get_param( 'page' ) );
	$per   = max( 1, min( 50, (int) ( $req->get_param( 'per_page' ) ? $req->get_param( 'per_page' ) : 20 ) ) );
	$total = count( $all );
	$slice = array_slice( $all, ( $page - 1 ) * $per, $per );
	$items = array();
	foreach ( $slice as $r ) {
		$items[] = flxy_app_public( $r );
	}
	return rest_ensure_response(
		array(
			'items' => $items,
			'total' => $total,
			'page'  => $page,
			'pages' => (int) ceil( $total / $per ),
		)
	);
}

function flxy_app_get( WP_REST_Request $req ) {
	$r = flxy_backup_get( (string) $req['id'] );
	if ( ! $r ) {
		return new WP_Error( 'flxy_not_found', __( 'Yedek bulunamadı.', 'flx-backup' ), array( 'status' => 404 ) );
	}
	return rest_ensure_response( flxy_app_public( $r ) );
}

function flxy_app_create( WP_REST_Request $req ) {
	$type = sanitize_key( (string) $req->get_param( 'type' ) );
	if ( '' === $type ) {
		$type = 'full';
	}
	$res = flxy_backup_create(
		array(
			'type'  => $type,
			'label' => sanitize_text_field( (string) $req->get_param( 'label' ) ),
			'sync'  => false,
		)
	);
	if ( is_wp_error( $res ) ) {
		$status = in_array( $res->get_error_code(), array( 'flxy_busy' ), true ) ? 409 : 400;
		return new WP_Error( $res->get_error_code(), $res->get_error_message(), array( 'status' => $status ) );
	}
	return new WP_REST_Response( flxy_app_public( $res ), 201 );
}

function flxy_app_delete( WP_REST_Request $req ) {
	$id = (string) $req['id'];
	if ( ! flxy_backup_get( $id ) ) {
		return new WP_Error( 'flxy_not_found', __( 'Yedek bulunamadı.', 'flx-backup' ), array( 'status' => 404 ) );
	}
	if ( class_exists( 'FLXY_Runner' ) ) {
		$state = FLXY_Runner::load_backup();
		if ( is_array( $state ) && (string) ( $state['id'] ?? '' ) === $id ) {
			FLXY_Runner::cancel();
		}
	}
	flxy_backup_delete( $id );
	return rest_ensure_response( array( 'deleted' => true ) );
}

add_action(
	'rest_api_init',
	function () {
		$args = array(
			'page'     => array( 'type' => 'integer', 'default' => 1 ),
			'per_page' => array( 'type' => 'integer', 'default' => 20 ),
			'type'     => array( 'type' => 'string', 'default' => '' ),
			'status'   => array( 'type' => 'string', 'default' => '' ),
		);
		register_rest_route(
			'flx-backup/v1',
			'/backups',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => 'flxy_app_list',
					'permission_callback' => 'flxy_app_can',
					'args'                => $args,
				),
				array(
					'methods'             => 'POST',
					'callback'            => 'flxy_app_create',
					'permission_callback' => 'flxy_app_can',
					'args'                => array(
						'type'  => array( 'type' => 'string', 'default' => 'full' ),
						'label' => array( 'type' => 'string', 'default' => '' ),
					),
				),
			)
		);
		register_rest_route(
			'flx-backup/v1',
			'/backups/(?P<id>[A-Za-z0-9\-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => 'flxy_app_get',
				'permission_callback' => 'flxy_app_can',
			)
		);
		register_rest_route(
			'flx-backup/v1',
			'/backups/(?P<id>[A-Za-z0-9\-]+)/delete',
			array(
				'methods'             => 'POST',
				'callback'            => 'flxy_app_delete',
				'permission_callback' => 'flxy_app_can',
			)
		);
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! function_exists( 'flxapp_manifest_file' ) ) {
			return;
		}
		add_filter(
			'flxapp_manifest',
			function ( $m ) {
				$loaded       = flxapp_manifest_file(
					FLXY_DIR . 'app/manifest.json',
					'flx-backup',
					array(
						'version' => FLXY_VERSION,
						'caps'    => array( 'view' => 'manage_options' ),
					)
				);
				$m['backup'] = flxy_app_texts( $loaded, 'flx-backup' );
				return $m;
			},
			10,
			2
		);
	},
	20
);
