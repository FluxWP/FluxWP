<?php
/**
 * Flux App manifesti (`analytics`). Uçlar hazır: flxan/v1/dashboard, report, realtime, list.
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rozet ve duyuru metinleri (`text`) JSON çeviri anahtarlarında yok; burada çevrilir.
 *
 * @param mixed $node
 * @return mixed
 */
function flxan_app_texts( $node, $domain ) {
	if ( ! is_array( $node ) ) {
		return $node;
	}
	foreach ( $node as $k => $v ) {
		if ( 'text' === $k && is_string( $v ) && false === strpos( $v, '{{' ) ) {
			$node[ $k ] = translate( $v, $domain ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain
		} else {
			$node[ $k ] = flxan_app_texts( $v, $domain );
		}
	}
	return $node;
}

add_action(
	'plugins_loaded',
	function () {
		if ( ! function_exists( 'flxapp_manifest_file' ) ) {
			return;
		}
		add_filter(
			'flxapp_manifest',
			function ( $m ) {
				$loaded            = flxapp_manifest_file(
					FLXAN_DIR . 'app/manifest.json',
					'flx-analytics',
					array(
						'version' => FLXAN_VERSION,
						'caps'    => array(
							'view' => array( 'manage_options', 'flxan_view' ),
						),
					)
				);
				$m['analytics'] = flxan_app_texts( $loaded, 'flx-analytics' );
				return $m;
			},
			10,
			2
		);
	},
	20
);

/*
 * Flux App: the statistics screens are native screens (the same React screens as wp-admin, @fluxwp/modules
 * `flxan-*`, data over GraphQL). App versions without them keep using the manifest above.
 */
add_filter(
	'flxapp_modules',
	static function ( $m ) {
		$m   = is_array( $m ) ? $m : array();
		$api = home_url( '/graphql' );
		$def = array(
			'flxan-stats'     => array( __( 'Statistics', 'flx-analytics' ), 'manage_options', 'flx-analytics', 'admin.php?page=flx-analytics', 'chart', 30 ),
			'flxan-journeys'  => array( __( 'Journeys', 'flx-analytics' ), 'manage_options', 'flx-analytics-journeys', 'admin.php?page=flx-analytics-journeys', 'route', 31 ),
			'flxan-settings'  => array( __( 'Settings', 'flx-analytics' ), 'manage_options', 'flx-analytics-settings', 'admin.php?page=flx-analytics-settings', 'settings', 32 ),
		);
		foreach ( $def as $id => $d ) {
			$m[ $id ] = array(
				'title'   => $d[0],
				'cap'     => $d[1],
				'menu'    => $d[2],
				'url'     => admin_url( $d[3] ),
				'api'     => $api,
				'icon'    => $d[4],
				'version' => FLXAN_VERSION,
				'order'   => $d[5],
			);
		}
		return $m;
	}
);
