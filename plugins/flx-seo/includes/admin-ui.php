<?php
/**
 * SEO admin page, new view (1.11.0, GraphQL): the page opens the same React screen as the FluxWP app
 * (assets/flux/, tools/fluxapp/embed-sync.sh flx-seo). It is the default and has every feature of the classic
 * tabs; a user can switch back to the classic page from the screen header for now. Needs Flux GraphQL.
 *
 * @package flx-seo
 */

defined( 'ABSPATH' ) || exit;

function flxseo_flux_available() {
	return file_exists( FLXSEO_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && function_exists( 'flx_admin_screen' ) && flx_gql_ready();
}

function flxseo_flux_ui() {
	return flxseo_flux_available() && 'classic' !== get_user_meta( get_current_user_id(), 'flxseo_ui', true );
}

/** Link that switches the current user between the views. */
function flxseo_ui_switch_url( $ui, $tab = '' ) {
	$args = array( 'flxseo_ui' => 'flux' === $ui ? 'flux' : 'classic' );
	if ( '' !== $tab && 'genel' !== $tab ) {
		$args['tab'] = $tab;
	}
	return wp_nonce_url( add_query_arg( $args, admin_url( 'admin.php?page=flxseo' ) ), 'flxseo_ui' );
}

add_action(
	'admin_init',
	function () {
		if ( ! isset( $_GET['flxseo_ui'], $_GET['page'] ) || 'flxseo' !== $_GET['page'] || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		check_admin_referer( 'flxseo_ui' );
		if ( 'classic' === $_GET['flxseo_ui'] ) {
			update_user_meta( get_current_user_id(), 'flxseo_ui', 'classic' );
		} else {
			delete_user_meta( get_current_user_id(), 'flxseo_ui' );
		}
		wp_safe_redirect( flxseo_admin_url( isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		exit;
	}
);

/** Mount options of the React screen (Flux GraphQL screen shell adds rest, nonce, locale, site name, admin URL). */
function flxseo_flux_options() {
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	$tab    = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	return array(
		'colors'     => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
		'classicUrl' => flxseo_ui_switch_url( 'classic', isset( flxseo_tabs()[ $tab ] ) ? $tab : '' ),
	);
}

/*
 * The screen opens through Flux GraphQL's screen shell (includes/screens.php there): first queries preloaded
 * into the page, and the other Flux screens open from the menu without reloading wp-admin.
 */
add_action(
	'admin_menu',
	static function () {
		if ( ! function_exists( 'flx_admin_screen' ) ) {
			return;
		}
		flx_admin_screen(
			'flxseo',
			array(
				'module'      => 'flxseo-seo',
				'assets_dir'  => FLXSEO_DIR . 'assets/flux/',
				'assets_url'  => FLXSEO_URL . 'assets/flux/',
				'version'     => FLXSEO_VERSION,
				'enabled'     => 'flxseo_flux_ui',
				'options'     => 'flxseo_flux_options',
				'start'       => 'genel',
				'start_param' => 'tab',
				'preload'     => array( array( 'FlxSeoSettingsScreen' ) ),
			)
		);
	},
	20
);
