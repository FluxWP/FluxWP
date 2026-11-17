<?php
/**
 * React screens for Settings, Templates, Log and System. Default when Flux GraphQL is available.
 * Classic stays one link away (user meta flxi_ui = classic).
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pages that open the React screen.
 *
 * @return array<string,string> Page slug => start page.
 */
function flxi_flux_pages(): array {
	$pages = array(
		'flxi'          => 'templates',
		'flxi-kayitlar' => 'logs',
		'flxi-ayarlar'  => 'settings',
		'flxi-baglantilar' => 'connections',
	);
	if ( ! flxi_system_moved() ) {
		$pages['flxi-sistem'] = 'system';
	}
	return $pages;
}

function flxi_flux_available(): bool {
	return file_exists( FLXI_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && flx_gql_ready();
}

function flxi_flux_ui(): bool {
	return flxi_flux_available() && 'classic' !== get_user_meta( get_current_user_id(), 'flxi_ui', true );
}

/**
 * @param string $ui flux or classic.
 */
function flxi_ui_switch_url( string $ui ): string {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'flxi'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$args = array(
		'page'    => isset( flxi_flux_pages()[ $page ] ) ? $page : 'flxi',
		'flxi_ui' => 'flux' === $ui ? 'flux' : 'classic',
	);
	if ( isset( $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$args['key'] = sanitize_text_field( wp_unslash( $_GET['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	foreach ( array( 'edit', 'type' ) as $extra ) {
		if ( 'flxi-baglantilar' === $args['page'] && isset( $_GET[ $extra ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args[ $extra ] = sanitize_text_field( wp_unslash( $_GET[ $extra ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
	}
	return wp_nonce_url( add_query_arg( $args, admin_url( 'admin.php' ) ), 'flxi_ui' );
}

add_action(
	'admin_init',
	static function () {
		if ( ! isset( $_GET['flxi_ui'], $_GET['page'] ) || ! isset( flxi_flux_pages()[ sanitize_key( wp_unslash( $_GET['page'] ) ) ] ) || ! flxi_can_manage() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		check_admin_referer( 'flxi_ui' );
		if ( 'classic' === $_GET['flxi_ui'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			update_user_meta( get_current_user_id(), 'flxi_ui', 'classic' );
		} else {
			delete_user_meta( get_current_user_id(), 'flxi_ui' );
		}
		$args = array( 'page' => sanitize_key( wp_unslash( $_GET['page'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['key'] = sanitize_text_field( wp_unslash( $_GET['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		foreach ( array( 'edit', 'type' ) as $extra ) {
			if ( 'flxi-baglantilar' === $args['page'] && isset( $_GET[ $extra ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$args[ $extra ] = sanitize_text_field( wp_unslash( $_GET[ $extra ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
);

/**
 * @param string $tag    Script tag.
 * @param string $handle Handle.
 */
function flxi_flux_module_tag( $tag, $handle ) {
	if ( 'flxi-flux' !== $handle ) {
		return $tag;
	}
	return (string) preg_replace_callback(
		'/<script\b[^>]*\ssrc=[^>]*>/',
		static function ( $m ) {
			$open = (string) preg_replace( '/\stype=("|\')[^"\']*\1/', '', $m[0] );
			return (string) preg_replace( '/^<script\b/', '<script type="module"', $open );
		},
		$tag,
		1
	);
}

function flxi_flux_assets( string $page ): void {
	$ver   = FLXI_VERSION . '-' . (int) filemtime( FLXI_DIR . 'assets/flux/flux-modules.js' );
	$start = flxi_flux_pages()[ $page ] ?? 'templates';
	$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( '' !== $key && 'templates' === $start ) {
		$start = 'edit:' . $key;
	}
	if ( 'connections' === $start ) {
		$edit = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'new' === $edit ) {
			$start = 'connections?' . http_build_query( array( 'key' => 'new:' . ( '' !== $type ? $type : 'evolution' ) ) );
		} elseif ( ctype_digit( $edit ) ) {
			$start = 'connections?' . http_build_query( array( 'key' => $edit ) );
		}
	}
	if ( 'logs' === $start ) {
		$q_args = array();
		$st     = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tpl    = isset( $_GET['template'] ) ? sanitize_text_field( wp_unslash( $_GET['template'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$camp   = isset( $_GET['campaign'] ) ? absint( wp_unslash( $_GET['campaign'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $st ) {
			$q_args['status'] = $st;
		}
		if ( '' !== $tpl ) {
			$q_args['template'] = $tpl;
		}
		if ( $camp > 0 ) {
			$q_args['campaign'] = (string) $camp;
		}
		if ( $q_args ) {
			$start = 'logs?' . http_build_query( $q_args );
		}
	}
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	$opts   = array(
		'module'     => 'flxi-mail',
		'rest'       => rest_url(),
		'nonce'      => wp_create_nonce( 'wp_rest' ),
		'locale'     => get_user_locale(),
		'siteName'   => get_bloginfo( 'name' ),
		'adminUrl'   => admin_url(),
		'appIcon'    => FLXI_URL . 'assets/icon.svg',
		'theme'      => 'light',
		'colors'     => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
		'startPage'  => $start,
		'classicUrl' => flxi_ui_switch_url( 'classic' ),
	);
	wp_enqueue_style( 'flxi-flux', FLXI_URL . 'assets/flux/flux-modules.css', array(), $ver );
	wp_enqueue_script( 'flxi-flux', FLXI_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
	add_filter( 'script_loader_tag', 'flxi_flux_module_tag', 10, 2 );
	wp_add_inline_script(
		'flxi-flux',
		'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxi-app";slot.opts=o;function sync(){var el=document.getElementById("flxi-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
		'before'
	);
}

/**
 * Renders the React screen and returns true when it replaces the classic page.
 */
function flxi_flux_screen( string $start ): bool {
	if ( ! flxi_flux_ui() ) {
		return false;
	}
	echo '<style>#flxi-app.flux-root{height:auto;background:transparent;font-family:inherit}</style>';
	echo '<div class="wrap"><div id="flxi-app"><noscript>' . esc_html__( 'Communication requires JavaScript.', 'flx-mail' ) . '</noscript></div></div>';
	unset( $start );
	return true;
}

add_filter(
	'flxapp_modules',
	static function ( $m ) {
		$m              = is_array( $m ) ? $m : array();
		$m['flxi-mail'] = array(
			'title'   => __( 'Communication', 'flx-mail' ),
			'cap'     => 'flxi_manage',
			'menu'    => 'flxi',
			'url'     => admin_url( 'admin.php?page=flxi' ),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'mail',
			'version' => FLXI_VERSION,
		);
		return $m;
	}
);

add_filter(
	'flxa_app_screens',
	static function ( $map ) {
		$map = is_array( $map ) ? $map : array();
		foreach ( array_keys( flxi_flux_pages() ) as $slug ) {
			$map[ $slug ] = 'mail';
		}
		return $map;
	}
);
