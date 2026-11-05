<?php
/**
 * Flux App: dil ayarları web görünümünde açılır.
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param mixed $node
 * @return mixed
 */
function flxl_app_texts( $node, $domain ) {
	if ( ! is_array( $node ) ) {
		return $node;
	}
	foreach ( $node as $k => $v ) {
		if ( 'text' === $k && is_string( $v ) && false === strpos( $v, '{{' ) ) {
			$node[ $k ] = translate( $v, $domain ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain
		} else {
			$node[ $k ] = flxl_app_texts( $v, $domain );
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
				$loaded     = flxapp_manifest_file(
					FLXL_DIR . 'app/manifest.json',
					'flx-lang',
					array(
						'version' => FLXL_VERSION,
						'caps'    => array( 'view' => 'manage_options' ),
					)
				);
				$m['lang'] = flxl_app_texts( $loaded, 'flx-lang' );
				return $m;
			},
			10,
			2
		);
	},
	20
);
