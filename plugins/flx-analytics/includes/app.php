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
