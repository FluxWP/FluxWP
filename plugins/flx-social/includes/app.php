<?php
/**
 * Flux App: gönderi listesi, takvim, hesaplar. Medyalı yazar yerelde sonra açılır (flxsm-composer).
 * REST: flx-social/v1/calendar (bu ayın gönderileri).
 *
 * @package flx-social
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param mixed $node
 * @return mixed
 */
function flxsm_app_texts( $node, $domain ) {
	if ( ! is_array( $node ) ) {
		return $node;
	}
	foreach ( $node as $k => $v ) {
		if ( 'text' === $k && is_string( $v ) && false === strpos( $v, '{{' ) ) {
			$node[ $k ] = translate( $v, $domain ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain
		} else {
			$node[ $k ] = flxsm_app_texts( $v, $domain );
		}
	}
	return $node;
}

function flxsm_app_stamp( array $item ) {
	$ts = (int) ( $item['scheduled_at'] ? $item['scheduled_at'] : ( $item['published_at'] ? $item['published_at'] : $item['created'] ) );
	$item['when_label'] = ( $ts && function_exists( 'flx_date' ) ) ? flx_date( $ts, 'datetime' ) : '';
	$names              = array();
	foreach ( (array) $item['targets'] as $t ) {
		if ( ! empty( $t['account'] ) ) {
			$names[] = (string) $t['account'];
		}
	}
	$item['accounts_label'] = implode( ', ', $names );
	$item['excerpt']        = mb_substr( trim( wp_strip_all_tags( (string) $item['content'] ) ), 0, 140 );
	return $item;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			FLXSM_NS,
			'/calendar',
			array(
				'methods'             => 'GET',
				'permission_callback' => 'flxsm_perm',
				'args'                => array(
					'from'     => array( 'type' => 'integer', 'default' => 0 ),
					'to'       => array( 'type' => 'integer', 'default' => 0 ),
					'page'     => array( 'type' => 'integer', 'default' => 1 ),
					'per_page' => array( 'type' => 'integer', 'default' => 50 ),
				),
				'callback'            => function ( WP_REST_Request $r ) {
					$from = (int) $r->get_param( 'from' );
					$to   = (int) $r->get_param( 'to' );
					if ( ! $from ) {
						$from = strtotime( wp_date( 'Y-m-01 00:00:00' ) );
					}
					if ( ! $to ) {
						$to = strtotime( '+1 month', $from );
					}
					$res = flxsm_query_posts(
						array(
							'from'   => $from,
							'to'     => $to,
							'status' => 'scheduled,publishing,published,partial,failed',
							'page'   => (int) $r->get_param( 'page' ),
							'per'    => (int) $r->get_param( 'per_page' ),
							'asc'    => true,
						)
					);
					$res['from']  = $from;
					$res['to']    = $to;
					$res['items'] = array_map( 'flxsm_app_stamp', (array) $res['items'] );
					return rest_ensure_response( $res );
				},
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
					FLXSM_DIR . 'app/manifest.json',
					'flx-social',
					array(
						'version' => FLXSM_VERSION,
						'caps'    => array(
							'view'    => array( 'flxsm_post', 'flxsm_publish', 'flxsm_manage' ),
							'publish' => array( 'flxsm_publish', 'flxsm_manage' ),
							'manage'  => 'flxsm_manage',
						),
					)
				);
				$m['social'] = flxsm_app_texts( $loaded, 'flx-social' );
				return $m;
			},
			10,
			2
		);
	},
	20
);
