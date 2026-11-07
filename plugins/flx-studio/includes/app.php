<?php
/**
 * Flux App: galeri listesi. Üretim ekranı (studio) sonra yerelde açılır; bu sürüm yalnız galeriyi çizer.
 *
 * @package flx-studio
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param mixed $node
 * @return mixed
 */
function flxst_app_texts( $node, $domain ) {
	if ( ! is_array( $node ) ) {
		return $node;
	}
	foreach ( $node as $k => $v ) {
		if ( 'text' === $k && is_string( $v ) && false === strpos( $v, '{{' ) ) {
			$node[ $k ] = translate( $v, $domain ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain
		} else {
			$node[ $k ] = flxst_app_texts( $v, $domain );
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
				$loaded       = flxapp_manifest_file(
					FLXST_DIR . 'app/manifest.json',
					'flx-studio',
					array(
						'version' => FLXST_VERSION,
						'caps'    => array(
							'view' => array( 'flxst_use', 'flxst_manage' ),
						),
					)
				);
				$m['studio'] = flxst_app_texts( $loaded, 'flx-studio' );
				return $m;
			},
			10,
			2
		);
	},
	20
);
