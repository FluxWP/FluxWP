<?php
/**
 * Flux App: SEO is a native screen (the same React screen as wp-admin, data over GraphQL). The REST route
 * flx-seo/v1/audit stays for older app versions that read the manifest screens (docs/graphql/ISTISNALAR.md).
 * REST: flx-seo/v1/audit
 *
 * @package flx-seo
 */

defined( 'ABSPATH' ) || exit;

function flxseo_app_can() {
	return current_user_can( 'manage_options' );
}

function flxseo_app_nocache( $res, $server, $req ) {
	unset( $server );
	if ( $res instanceof WP_REST_Response && 0 === strpos( (string) $req->get_route(), '/flx-seo/v1/' ) ) {
		flx_rest_nocache( $res );
		$res->header( 'CDN-Cache-Control', 'no-store' );
		do_action( 'litespeed_control_set_nocache', 'flx-seo rest' );
	}
	return $res;
}
add_filter( 'rest_post_dispatch', 'flxseo_app_nocache', 10, 3 );

function flxseo_app_payload() {
	$report = flxseo_audit_report();
	$labels = array();
	foreach ( flxseo_audit_priorities() as $k => $row ) {
		$labels[ $k ] = $row[1];
	}
	$findings = array();
	foreach ( (array) $report['findings'] as $fd ) {
		$prio       = (string) ( $fd['priority'] ?? '' );
		$findings[] = array(
			'id'             => (string) ( $fd['id'] ?? '' ),
			'title'          => (string) ( $fd['title'] ?? '' ),
			'priority'       => $prio,
			'priority_label' => isset( $labels[ $prio ] ) ? $labels[ $prio ] : $prio,
			'category'       => (string) ( $fd['category'] ?? '' ),
			'detail'         => (string) ( $fd['detail'] ?? '' ),
			'fix'            => (string) ( $fd['fix'] ?? '' ),
		);
	}
	$scores = array();
	foreach ( array( 'mobile', 'desktop' ) as $strategy ) {
		$block = (array) ( $report['psi'][ $strategy ] ?? array() );
		foreach ( (array) ( $block['scores'] ?? array() ) as $key => $sc ) {
			if ( ! is_array( $sc ) ) {
				continue;
			}
			$scores[] = array(
				'id'       => $strategy . '-' . $key,
				'strategy' => $strategy,
				'label'    => (string) ( $sc['title'] ?? $key ),
				'score'    => $sc['score'],
			);
		}
	}
	$counts = (array) ( $report['counts'] ?? array() );
	$local  = (array) ( $report['local'] ?? array() );
	return array(
		'counts'     => array(
			'critical' => (int) ( $counts['critical'] ?? 0 ),
			'high'     => (int) ( $counts['high'] ?? 0 ),
			'medium'   => (int) ( $counts['medium'] ?? 0 ),
			'low'      => (int) ( $counts['low'] ?? 0 ),
		),
		'ran_at'     => ! empty( $local['time'] ) && function_exists( 'flx_date' ) ? flx_date( (int) $local['time'], 'datetime' ) : '',
		'url'        => (string) ( $local['url'] ?? '' ),
		'scores'     => $scores,
		'findings'   => $findings,
		'total'      => count( $findings ),
	);
}

function flxseo_app_audit() {
	return rest_ensure_response( flxseo_app_payload() );
}

function flxseo_app_audit_run() {
	$res = flxseo_audit_run_local();
	if ( is_wp_error( $res ) ) {
		return new WP_Error( $res->get_error_code(), $res->get_error_message(), array( 'status' => 400 ) );
	}
	return rest_ensure_response( flxseo_app_payload() );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'flx-seo/v1',
			'/audit',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => 'flxseo_app_audit',
					'permission_callback' => 'flxseo_app_can',
				),
				array(
					'methods'             => 'POST',
					'callback'            => 'flxseo_app_audit_run',
					'permission_callback' => 'flxseo_app_can',
				),
			)
		);
	}
);

/* Native module screen: the same React screen as wp-admin (@fluxwp/modules `flxseo-seo`), data over GraphQL. */
add_filter(
	'flxapp_modules',
	static function ( $m ) {
		$m                = is_array( $m ) ? $m : array();
		$m['flxseo-seo'] = array(
			'title'   => 'SEO',
			'cap'     => 'manage_options',
			'menu'    => 'flxseo',
			'url'     => admin_url( 'admin.php?page=flxseo' ),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'search',
			'version' => FLXSEO_VERSION,
			'order'   => 70,
		);
		return $m;
	}
);
