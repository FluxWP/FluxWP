<?php
/**
 * Kullanıcılar › Roller ve erişim. Sayfa FluxWP uygulamasıyla aynı React ekranını açar (assets/flux/,
 * tools/fluxapp/embed-sync.sh flx-roles; veri GraphQL'den). Klasik PHP sayfası yoktur; ekran açılamazsa
 * Flux GraphQL "yüklenemedi" uyarısını gösterir.
 */

defined( 'ABSPATH' ) || exit;

function flxr_admin_url( $args = array() ) {
	return add_query_arg( $args, admin_url( 'users.php?page=flx-roles' ) );
}

add_action(
	'admin_menu',
	function () {
		add_users_page( __( 'Roles and access', 'flx-roles' ), __( 'Roles and access', 'flx-roles' ), 'manage_options', 'flx-roles', 'flxr_render_page' );
		if ( ! function_exists( 'flx_admin_screen' ) ) {
			return;
		}
		flx_admin_screen(
			'flx-roles',
			array(
				'module'     => 'flxr-roles',
				'assets_dir' => FLXR_DIR . 'assets/flux/',
				'assets_url' => FLXR_URL . 'assets/flux/',
				'version'    => FLXR_VERSION,
				'options'    => static function () {
					$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
					return array(
						'colors' => array(
							'primary'         => $accent,
							'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
						),
					);
				},
			)
		);
	}
);

function flxr_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'flx-roles' ), 403 );
	}
	flx_admin_screen_page();
}
