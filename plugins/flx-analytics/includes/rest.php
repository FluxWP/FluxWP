<?php
/**
 * REST: /wp-json/flxan/v1 — c (toplama yedeği), report, list, realtime, page, funnel, journeys, refs, fast-test.
 * Okuma uçları seg (all|guest|members|<rol>) ve rc (''|*|<kod>) süzgeçlerini alır.
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

/** Toplama yedeği yalnız kendi sitemizdeki sayfalardan kabul edilir. */
function flxan_rest_same_origin() {
	$home = strtolower( preg_replace( '~^www\.~', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
	foreach ( array( 'HTTP_ORIGIN', 'HTTP_REFERER' ) as $h ) {
		if ( empty( $_SERVER[ $h ] ) ) {
			continue;
		}
		$host = strtolower( preg_replace( '~^www\.~', '', (string) wp_parse_url( wp_unslash( $_SERVER[ $h ] ), PHP_URL_HOST ) ) ); // phpcs:ignore
		return $host === $home;
	}
	return false;
}

/** Ortak aralık parametreleri. */
function flxan_rest_range_args() {
	return array(
		'range' => array( 'type' => 'string', 'default' => '30d' ),
		'start' => array( 'type' => 'string', 'default' => '' ),
		'end'   => array( 'type' => 'string', 'default' => '' ),
		'seg'   => array( 'type' => 'string', 'default' => 'all' ),
		'rc'    => array( 'type' => 'string', 'default' => '' ),
	);
}

function flxan_rest_range( WP_REST_Request $r ) {
	FLXAN_Stats::set_filter( (string) $r['seg'], (string) $r['rc'] );
	return FLXAN_Stats::range( (string) $r['range'], (string) $r['start'], (string) $r['end'] );
}

add_action(
	'rest_api_init',
	function () {
		$view = function () {
			return flxan_can_view();
		};

		register_rest_route(
			'flxan/v1',
			'/c',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'flxan_rest_same_origin',
				'callback'            => function ( WP_REST_Request $r ) {
					$code = flxan_c_handle( $r->get_body_params() ? $r->get_body_params() : $r->get_params(), $_SERVER, flxan_data_dir() ); // phpcs:ignore
					return new WP_REST_Response( null, $code );
				},
			)
		);

		register_rest_route(
			'flxan/v1',
			'/report',
			array(
				'methods'             => 'GET',
				'permission_callback' => $view,
				'args'                => flxan_rest_range_args(),
				'callback'            => function ( WP_REST_Request $r ) {
					FLXAN_Aggregator::maybe_run( 60 );
					return FLXAN_Stats::report( flxan_rest_range( $r ) );
				},
			)
		);

		register_rest_route(
			'flxan/v1',
			'/list',
			array(
				'methods'             => 'GET',
				'permission_callback' => $view,
				'args'                => array_merge(
					flxan_rest_range_args(),
					array(
						'type'   => array( 'type' => 'string', 'enum' => array( 'pages', 'notfound', 'referrers', 'dim', 'events' ), 'required' => true ),
						'kind'   => array( 'type' => 'string', 'default' => '' ),
						'name'   => array( 'type' => 'string', 'default' => '' ),
						'q'      => array( 'type' => 'string', 'default' => '' ),
						'limit'  => array( 'type' => 'integer', 'default' => 25 ),
						'offset' => array( 'type' => 'integer', 'default' => 0 ),
					)
				),
				'callback'            => function ( WP_REST_Request $r ) {
					$g   = flxan_rest_range( $r );
					$lim = max( 1, min( 200, (int) $r['limit'] ) );
					$off = max( 0, (int) $r['offset'] );
					switch ( $r['type'] ) {
						case 'pages':
							return FLXAN_Stats::pages( $g['start'], $g['end'], $lim, $off, (string) $r['q'] );
						case 'notfound':
							return FLXAN_Stats::pages( $g['start'], $g['end'], $lim, $off, (string) $r['q'], true );
						case 'referrers':
							return FLXAN_Stats::referrers( $g['start'], $g['end'], $lim, $off, (string) $r['q'] );
						case 'dim':
							return FLXAN_Stats::dim( (string) $r['kind'], $g['start'], $g['end'], $lim, $off );
						default:
							return FLXAN_Stats::events( $g['start'], $g['end'], (string) $r['name'], $lim, $off );
					}
				},
			)
		);

		register_rest_route(
			'flxan/v1',
			'/realtime',
			array(
				'methods'             => 'GET',
				'permission_callback' => $view,
				'args'                => array_merge(
					flxan_rest_range_args(),
					array(
						'minutes' => array( 'type' => 'integer', 'default' => 30 ),
						'kpi'     => array( 'type' => 'boolean', 'default' => false ),
					)
				),
				'callback'            => function ( WP_REST_Request $r ) {
					FLXAN_Aggregator::maybe_run( 5 );
					$g   = flxan_rest_range( $r );
					$out = FLXAN_Stats::realtime( (int) $r['minutes'] );
					if ( $r['kpi'] ) {
						// Paneli açık tutan için üst göstergeler ve grafik de canlı (hafif sorgular).
						$out['kpi'] = FLXAN_Stats::kpi( $g );
					}
					return $out;
				},
			)
		);

		register_rest_route(
			'flxan/v1',
			'/funnel',
			array(
				'methods'             => 'GET',
				'permission_callback' => $view,
				'args'                => array_merge(
					flxan_rest_range_args(),
					array(
						'id' => array( 'type' => 'integer', 'default' => 0 ),
						'by' => array( 'type' => 'string', 'default' => '' ),
					)
				),
				'callback'            => function ( WP_REST_Request $r ) {
					$g = flxan_rest_range( $r );
					return FLXAN_Stats::funnel( (int) $r['id'], $g['start'], $g['end'], (string) $r['by'] );
				},
			)
		);

		register_rest_route(
			'flxan/v1',
			'/journeys',
			array(
				'methods'             => 'GET',
				'permission_callback' => $view,
				'args'                => array_merge(
					flxan_rest_range_args(),
					array(
						'steps'      => array( 'type' => 'integer', 'default' => 4 ),
						'from'       => array( 'type' => 'string', 'default' => '' ),
						'to'         => array( 'type' => 'string', 'default' => '' ),
						'goal'       => array( 'type' => 'string', 'default' => 'auto' ),
						'group'      => array( 'type' => 'boolean', 'default' => false ),
						'top'        => array( 'type' => 'integer', 'default' => 15 ),
					)
				),
				'callback'            => function ( WP_REST_Request $r ) {
					FLXAN_Aggregator::maybe_run( 30 );
					$g = flxan_rest_range( $r );
					return array( 'range' => $g, 'filter' => FLXAN_Stats::$filter ) + FLXAN_Journeys::report(
						$g['start'],
						$g['end'],
						$g['prev_start'],
						$g['prev_end'],
						array(
							'steps' => (int) $r['steps'],
							'start' => (string) $r['from'],
							'end'   => (string) $r['to'],
							'goal'  => 'auto' === (string) $r['goal'] ? null : (string) $r['goal'],
							'group' => (bool) $r['group'],
							'top'   => (int) $r['top'],
						)
					);
				},
			)
		);

		register_rest_route(
			'flxan/v1',
			'/refs',
			array(
				'methods'             => 'GET',
				'permission_callback' => $view,
				'args'                => flxan_rest_range_args(),
				'callback'            => function ( WP_REST_Request $r ) {
					$g = flxan_rest_range( $r );
					return FLXAN_Stats::refs( $g['start'], $g['end'], 200 );
				},
			)
		);

		register_rest_route(
			'flxan/v1',
			'/refs/save',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'flxan_can_manage_refs',
				'args'                => array(
					'code'   => array( 'type' => 'string', 'required' => true ),
					'name'   => array( 'type' => 'string', 'default' => '' ),
					'delete' => array( 'type' => 'boolean', 'default' => false ),
				),
				'callback'            => function ( WP_REST_Request $r ) {
					$code = flxan_ref_save( (string) $r['code'], $r['delete'] ? null : (string) $r['name'] );
					if ( '' === $code ) {
						return new WP_Error( 'flxan_bad_code', __( 'Geçersiz kod. Harf, rakam, - ve _ kullanın.', 'flx-analytics' ), array( 'status' => 400 ) );
					}
					return array(
						'code' => $code,
						'name' => flxan_ref_names()[ $code ] ?? '',
						'url'  => add_query_arg( 'r', $code, home_url( '/' ) ),
					);
				},
			)
		);

		register_rest_route(
			'flxan/v1',
			'/page',
			array(
				'methods'             => 'GET',
				'permission_callback' => $view,
				'args'                => array_merge( flxan_rest_range_args(), array( 'target' => array( 'type' => 'string', 'required' => true ) ) ),
				'callback'            => function ( WP_REST_Request $r ) {
					$g = flxan_rest_range( $r );
					return FLXAN_Stats::page_stats( (string) $r['target'], $g['start'], $g['end'] );
				},
			)
		);

		register_rest_route(
			'flxan/v1',
			'/fast-test',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => array( 'ok' => array( 'type' => 'boolean', 'required' => true ) ),
				'callback'            => function ( WP_REST_Request $r ) {
					update_option( 'flxan_fast_test', $r['ok'] ? 'ok' : 'fail', false );
					update_option( 'flxan_fast_test_time', time(), false );
					return array( 'method' => flxan_method() );
				},
			)
		);
	}
);

/** Değişken veri: LiteSpeed ve CDN önbelleğe almasın. */
add_filter(
	'rest_post_dispatch',
	function ( $res, $server, $req ) {
		if ( 0 === strpos( (string) $req->get_route(), '/flxan/v1' ) && $res instanceof WP_REST_Response ) {
			$res->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private' );
			$res->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
			$res->header( 'CDN-Cache-Control', 'no-store' );
			$res->header( 'X-Robots-Tag', 'noindex' );
			do_action( 'litespeed_control_set_nocache', 'flx-analytics rest' );
		}
		return $res;
	},
	10,
	3
);
