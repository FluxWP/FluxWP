<?php
/**
 * Flux App: güncellemeler, sihirbaz, ön ayarlar ve güncelleme ayarları web görünümünde açılır.
 *
 * @package flx-setup
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param mixed $node
 * @return mixed
 */
function flxk_app_texts( $node, $domain ) {
	if ( ! is_array( $node ) ) {
		return $node;
	}
	foreach ( $node as $k => $v ) {
		if ( 'text' === $k && is_string( $v ) && false === strpos( $v, '{{' ) ) {
			$node[ $k ] = translate( $v, $domain ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain
		} else {
			$node[ $k ] = flxk_app_texts( $v, $domain );
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
				$loaded      = flxapp_manifest_file(
					FLXK_DIR . 'app/manifest.json',
					'flx-setup',
					array(
						'version' => FLXK_VERSION,
						'caps'    => array( 'view' => 'manage_options' ),
					)
				);
				$m['setup'] = flxk_app_texts( $loaded, 'flx-setup' );
				return $m;
			},
			10,
			2
		);
	},
	20
);
