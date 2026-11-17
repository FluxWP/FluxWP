<?php
/**
 * Flux App: Languages is a native screen (the same React screen as wp-admin, @fluxwp/modules
 * `flxl-lang`, data over GraphQL). App versions without the screen open the admin page instead.
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flxapp_modules',
	static function ( $m ) {
		$m               = is_array( $m ) ? $m : array();
		$m['flxl-lang'] = array(
			'title'   => __( 'Languages', 'flx-lang' ),
			'cap'     => 'manage_options',
			'menu'    => 'flx-lang',
			'url'     => admin_url( 'options-general.php?page=flx-lang' ),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'globe',
			'version' => FLXL_VERSION,
			'order'   => 90,
		);
		return $m;
	}
);

/* Mobile app: the same screens as a manifest (app/manifest.json), drawn natively, data over GraphQL. */
add_action(
	'plugins_loaded',
	static function () {
		if ( ! function_exists( 'flxapp_manifest_file' ) ) {
			return;
		}
		add_filter(
			'flxapp_manifest',
			static function ( $m ) {
				$m         = is_array( $m ) ? $m : array();
				$m['lang'] = flxapp_manifest_file(
					FLXL_DIR . 'app/manifest.json',
					'flx-lang',
					array(
						'version' => FLXL_VERSION,
						'caps'    => array( 'view' => 'manage_options' ),
					)
				);
				return $m;
			}
		);
	},
	20
);
