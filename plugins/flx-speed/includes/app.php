<?php
/**
 * Flux App: sunucu özeti ve yavaş istekler.
 * REST: flx-speed/v1/summary, /requests, /requests/<id>
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param mixed $node
 * @return mixed
 */
function flxp_app_texts( $node, $domain ) {
	if ( ! is_array( $node ) ) {
		return $node;
	}
	foreach ( $node as $k => $v ) {
		if ( 'text' === $k && is_string( $v ) && false === strpos( $v, '{{' ) ) {
			$node[ $k ] = translate( $v, $domain ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain
		} else {
			$node[ $k ] = flxp_app_texts( $v, $domain );
		}
	}
	return $node;
}

function flxp_app_can() {
	return flxp_can_view();
}

function flxp_app_nocache( $res, $server, $req ) {
	unset( $server );
	if ( $res instanceof WP_REST_Response && 0 === strpos( (string) $req->get_route(), '/flx-speed/v1' ) ) {
		flx_rest_nocache( $res );
		$res->header( 'CDN-Cache-Control', 'no-store' );
		do_action( 'litespeed_control_set_nocache', 'flx-speed rest' );
	}
	return $res;
}
add_filter( 'rest_post_dispatch', 'flxp_app_nocache', 10, 3 );

function flxp_app_summary( WP_REST_Request $req ) {
	$hours = max( 1, min( 720, (int) ( $req->get_param( 'hours' ) ? $req->get_param( 'hours' ) : 24 ) ) );
	$live  = flxp_server_live( 0 );
	$reqst = FLXP_Store::summary( $hours );
	return rest_ensure_response(
		array(
			'server'   => array(
				'scope'    => (string) ( $live['scope'] ?? '' ),
				'cores'    => $live['cores'],
				'load_pct' => $live['load_pct'],
				'ram_pct'  => $live['ram_pct'],
				'disk_pct' => $live['disk_pct'],
				'uptime_s' => $live['uptime_s'],
			),
			'requests' => $reqst,
		)
	);
}

function flxp_app_requests( WP_REST_Request $req ) {
	$flag = sanitize_key( (string) $req->get_param( 'flag' ) );
	$res  = FLXP_Store::query(
		array(
			'hours'    => max( 1, min( 720, (int) ( $req->get_param( 'hours' ) ? $req->get_param( 'hours' ) : 24 ) ) ),
			'flag'     => $flag,
			'type'     => sanitize_key( (string) $req->get_param( 'type' ) ),
			'q'        => (string) $req->get_param( 'q' ),
			'min_ms'   => (int) $req->get_param( 'min_ms' ),
			'page'     => max( 1, (int) $req->get_param( 'page' ) ),
			'per_page' => max( 1, min( 50, (int) ( $req->get_param( 'per_page' ) ? $req->get_param( 'per_page' ) : 20 ) ) ),
			'order'    => 'slow' === $req->get_param( 'order' ) ? 'slow' : 'new',
		)
	);
	$items = array();
	foreach ( (array) $res['items'] as $it ) {
		$items[] = flxp_app_request_row( $it );
	}
	$per = max( 1, min( 50, (int) ( $req->get_param( 'per_page' ) ? $req->get_param( 'per_page' ) : 20 ) ) );
	return rest_ensure_response(
		array(
			'items' => $items,
			'total' => (int) $res['total'],
			'page'  => max( 1, (int) $req->get_param( 'page' ) ),
			'pages' => (int) ceil( (int) $res['total'] / $per ),
		)
	);
}

function flxp_app_request_row( array $it ) {
	$it['flags_label'] = implode( ', ', (array) $it['flags'] );
	$it['ms_label']    = number_format_i18n( (int) $it['ms'] ) . ' ms';
	$it['title']       = (string) $it['url'];
	return $it;
}

function flxp_app_request( WP_REST_Request $req ) {
	$row = FLXP_Store::get( (int) $req['id'] );
	if ( ! $row ) {
		return new WP_Error( 'flxp_not_found', __( 'Record not found.', 'flx-speed' ), array( 'status' => 404 ) );
	}
	$data = isset( $row['data'] ) && is_array( $row['data'] ) ? $row['data'] : array();
	unset( $row['data'] );
	$row              = flxp_app_request_row( $row );
	$row['overview']  = isset( $data['overview'] ) && is_array( $data['overview'] ) ? $data['overview'] : array();
	$row['php_count'] = isset( $data['php'] ) && is_array( $data['php'] ) ? count( $data['php'] ) : 0;
	return rest_ensure_response( $row );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'flx-speed/v1',
			'/summary',
			array(
				'methods'             => 'GET',
				'callback'            => 'flxp_app_summary',
				'permission_callback' => 'flxp_app_can',
				'args'                => array( 'hours' => array( 'type' => 'integer', 'default' => 24 ) ),
			)
		);
		register_rest_route(
			'flx-speed/v1',
			'/requests',
			array(
				'methods'             => 'GET',
				'callback'            => 'flxp_app_requests',
				'permission_callback' => 'flxp_app_can',
				'args'                => array(
					'hours'    => array( 'type' => 'integer', 'default' => 24 ),
					'flag'     => array( 'type' => 'string', 'default' => '' ),
					'type'     => array( 'type' => 'string', 'default' => '' ),
					'q'        => array( 'type' => 'string', 'default' => '' ),
					'min_ms'   => array( 'type' => 'integer', 'default' => 0 ),
					'page'     => array( 'type' => 'integer', 'default' => 1 ),
					'per_page' => array( 'type' => 'integer', 'default' => 20 ),
					'order'    => array( 'type' => 'string', 'default' => 'new' ),
				),
			)
		);
		register_rest_route(
			'flx-speed/v1',
			'/requests/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => 'flxp_app_request',
				'permission_callback' => 'flxp_app_can',
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
				$loaded      = flxapp_manifest_file(
					FLXP_DIR . 'app/manifest.json',
					'flx-speed',
					array(
						'version' => FLXP_VERSION,
						'caps'    => array(
							'view' => array( 'manage_options', 'flxp_view' ),
						),
					)
				);
				$m['speed'] = flxp_app_texts( $loaded, 'flx-speed' );
				return $m;
			},
			10,
			2
		);
	},
	20
);
