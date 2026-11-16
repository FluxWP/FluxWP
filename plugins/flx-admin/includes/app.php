<?php
/**
 * Flux App: the Admin theme settings are a native screen (the same React screen as wp-admin,
 * @fluxwp/modules `flxa-admin`, data over GraphQL). App versions without the screen open the admin page instead.
 * Also the upload context of the logo (the screen sends the file to /flx/v1/upload first).
 *
 * @package flx-admin
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flxapp_modules',
	static function ( $m ) {
		$m               = is_array( $m ) ? $m : array();
		$m['flxa-admin'] = array(
			'title'   => __( 'Admin theme', 'flx-admin' ),
			'cap'     => 'manage_options',
			'menu'    => 'flx-admin',
			'url'     => flxa_settings_url(),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'settings',
			'version' => FLXA_VERSION,
			'order'   => 90,
		);
		return $m;
	}
);

add_filter(
	'flx_gql_upload_contexts',
	static function ( $c ) {
		$c                       = is_array( $c ) ? $c : array();
		$c['flx-admin/logo']     = array(
			'cap'    => 'manage_options',
			'mimes'  => array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
				'gif'          => 'image/gif',
				'webp'         => 'image/webp',
			),
			'max_mb' => 5,
		);
		return $c;
	}
);
