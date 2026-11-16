<?php
/**
 * GraphQL (Flux GraphQL) and the shared React screens of the theme.
 *
 * - The schema loads only inside a GraphQL request (lazy); without Flux GraphQL (0.2.0+) nothing changes
 *   and the classic Theme Settings and System Check pages keep working.
 * - Theme Settings and System Check open the same React screens as the FluxWP app (assets/flux/,
 *   tools/fluxapp/embed-sync.sh fluxwp). They are the default and have every feature of the classic
 *   pages; a user can switch to the classic page from the screen header for now.
 *
 * @package fluxwp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_theme_file_path( 'inc/services/class-flx-theme-dto.php' );
require_once get_theme_file_path( 'inc/services/class-flx-theme-service.php' );

add_action(
	'flx_gql_register',
	static function () {
		if ( ! function_exists( 'flx_gql_all_caps' ) ) {
			return;
		}
		require_once get_theme_file_path( 'inc/graphql/schema.php' );
	}
);

add_filter(
	'flx_gql_persisted_manifests',
	static function ( $m ) {
		$m           = is_array( $m ) ? $m : array();
		$m['fluxwp'] = get_theme_file_path( 'graphql/persisted-queries.json' );
		return $m;
	}
);

/* Cached reads (global styles, settings) are dropped when the Site Editor styles or the theme change. */
add_action( 'save_post_wp_global_styles', array( 'FLX_Theme_Service', 'purge' ) );
add_action( 'switch_theme', array( 'FLX_Theme_Service', 'purge' ) );

/* FluxWP app: both screens are native there (same React code, data over GraphQL). */
add_filter(
	'flxapp_modules',
	static function ( $m ) {
		$m                       = is_array( $m ) ? $m : array();
		$m['flxt-theme-settings'] = array(
			'title'   => __( 'Theme Settings', 'fluxwp' ),
			'cap'     => 'manage_options',
			'menu'    => 'flx-ayarlar',
			'url'     => admin_url( 'themes.php?page=flx-ayarlar' ),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'settings',
			'version' => FLX_VERSION,
			'order'   => 70,
		);
		$m['flxt-theme-system']   = array(
			'title'   => __( 'System Check', 'fluxwp' ),
			'cap'     => 'manage_options',
			'menu'    => 'flx-sistem',
			'url'     => flx_sk_url(),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'shield',
			'version' => FLX_VERSION,
			'order'   => 71,
		);
		return $m;
	}
);

/* ---------- wp-admin: the shared React screens ---------- */

/** The bundle is in the theme and Flux GraphQL (0.2.0+) is ready. */
function flx_flux_available() {
	return file_exists( get_theme_file_path( 'assets/flux/flux-modules.js' ) ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
}

/** This user sees the React screens (default) and has not chosen the classic pages. */
function flx_flux_ui() {
	return flx_flux_available() && 'classic' !== get_user_meta( get_current_user_id(), 'flx_ui', true );
}

/**
 * Link that switches the current user between the views.
 *
 * @param string $page Admin page slug (flx-ayarlar | flx-sistem).
 * @param string $ui   flux | classic.
 */
function flx_ui_switch_url( $page, $ui ) {
	return wp_nonce_url( add_query_arg( array( 'page' => $page, 'flx_ui' => 'flux' === $ui ? 'flux' : 'classic' ), admin_url( 'themes.php' ) ), 'flx_ui' );
}

add_action(
	'admin_init',
	static function () {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $_GET['flx_ui'] ) || ! in_array( $page, array( 'flx-ayarlar', 'flx-sistem' ), true ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		check_admin_referer( 'flx_ui' );
		if ( 'classic' === $_GET['flx_ui'] ) {
			update_user_meta( get_current_user_id(), 'flx_ui', 'classic' );
		} else {
			delete_user_meta( get_current_user_id(), 'flx_ui' );
		}
		wp_safe_redirect( admin_url( 'themes.php?page=' . $page ) );
		exit;
	}
);

/** The chunked bundle loads as an ES module. */
function flx_flux_module_tag( $tag, $handle ) {
	if ( 'flx-flux' !== $handle ) {
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

/**
 * Loads the bundle for one screen.
 *
 * @param string $module  flxt-theme-settings | flxt-theme-system.
 * @param string $page    Admin page slug (for the classic-view link).
 * @param string $root_id Mount element ID.
 */
function flx_flux_assets( $module, $page, $root_id ) {
	$ver    = FLX_VERSION . '-' . (int) filemtime( get_theme_file_path( 'assets/flux/flux-modules.js' ) );
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	$opts   = array(
		'module'     => $module,
		'rest'       => rest_url(),
		'nonce'      => wp_create_nonce( 'wp_rest' ),
		'locale'     => get_user_locale(),
		'siteName'   => get_bloginfo( 'name' ),
		'adminUrl'   => admin_url(),
		'theme'      => 'light',
		'colors'     => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
		'classicUrl' => flx_ui_switch_url( $page, 'classic' ),
	);
	if ( 'flxt-theme-settings' === $module ) {
		wp_enqueue_media(); // Payment logos come from the media library.
	}
	wp_enqueue_style( 'flx-flux', get_theme_file_uri( 'assets/flux/flux-modules.css' ), array(), $ver );
	wp_enqueue_script( 'flx-flux', get_theme_file_uri( 'assets/flux/flux-modules.js' ), array(), $ver, true );
	add_filter( 'script_loader_tag', 'flx_flux_module_tag', 10, 2 );
	// Light / dark follows Flux Admin (html.flxa-dark), also when the user switches it on the page.
	wp_add_inline_script(
		'flx-flux',
		'(function(){var h=document.documentElement,id=' . wp_json_encode( $root_id ) . ',o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id=id;slot.opts=o;function sync(){var el=document.getElementById(id);if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
		'before'
	);
}

/** Prints the mount point of a React screen. */
function flx_flux_root( $root_id ) {
	echo '<style>#' . esc_attr( $root_id ) . '.flux-root{height:auto;background:transparent;font-family:inherit}</style>';
	echo '<div class="wrap flx-flux-wrap"><div id="' . esc_attr( $root_id ) . '"><noscript>' . esc_html__( 'JavaScript is required for this screen.', 'fluxwp' ) . '</noscript></div></div>';
}

/** "New view" bar on top of a classic page (only when the React screen is available). */
function flx_flux_new_view_link( $page ) {
	if ( ! flx_flux_available() ) {
		return;
	}
	echo '<p><a class="button" href="' . esc_url( flx_ui_switch_url( $page, 'flux' ) ) . '">' . esc_html__( 'New view', 'fluxwp' ) . '</a> <span class="description">' . esc_html__( 'Open the new view (same features, works in the FluxWP app too).', 'fluxwp' ) . '</span></p>';
}

/* Assets are enqueued for the two pages only, and only when their React screen is the one shown. */
add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		$tab = isset( $_GET['bolum'] ) ? sanitize_key( wp_unslash( $_GET['bolum'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'appearance_page_flx-ayarlar' === $hook && 'onayarlar' !== $tab && flx_flux_ui() ) {
			flx_flux_assets( 'flxt-theme-settings', 'flx-ayarlar', 'flx-theme-settings-app' );
		} elseif ( 'appearance_page_flx-sistem' === $hook && flx_flux_ui() ) {
			flx_flux_assets( 'flxt-theme-system', 'flx-sistem', 'flx-theme-system-app' );
		}
	}
);
