<?php
/**
 * FluxWP Panel screens (Packages, Settings) as the shared React screens (assets/flux/, built with
 * tools/fluxapp/embed-sync.sh flx-setup). They are the default when Flux GraphQL (0.2.0+) runs; a user can
 * switch to the classic page from the screen header. Without Flux GraphQL the classic pages open and show
 * a notice with the install button (flxk_graphql_notice).
 *
 * @package FLX_Setup
 */

defined( 'ABSPATH' ) || exit;

function flxk_flux_available(): bool {
	return file_exists( FLXK_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
}

function flxk_flux_ui(): bool {
	return flxk_flux_available() && 'classic' !== get_user_meta( get_current_user_id(), 'flxk_ui', true );
}

/**
 * Link that switches the current user between the views.
 *
 * @param string $ui   flux|classic.
 * @param string $page Admin page slug.
 */
function flxk_ui_switch_url( string $ui, string $page ): string {
	return wp_nonce_url( add_query_arg( 'flxk_ui', 'flux' === $ui ? 'flux' : 'classic', admin_url( 'admin.php?page=' . $page ) ), 'flxk_ui' );
}

add_action(
	'admin_init',
	static function () {
		if ( ! isset( $_GET['flxk_ui'], $_GET['page'] ) || ! in_array( $_GET['page'], array( 'flx-setup', 'flx-setup-settings' ), true ) || ! current_user_can( 'update_plugins' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		check_admin_referer( 'flxk_ui' );
		if ( 'classic' === $_GET['flxk_ui'] ) {
			update_user_meta( get_current_user_id(), 'flxk_ui', 'classic' );
		} else {
			delete_user_meta( get_current_user_id(), 'flxk_ui' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . sanitize_key( wp_unslash( $_GET['page'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		exit;
	}
);

/**
 * The chunked bundle loads as an ES module.
 *
 * @param string $tag    Script tag.
 * @param string $handle Handle.
 */
function flxk_flux_module_tag( $tag, $handle ) {
	if ( 'flxk-flux' !== $handle ) {
		return $tag;
	}
	return (string) preg_replace_callback(
		'/<script\b[^>]*\ssrc=[^>]*>/',
		static function ( $m ) {
			$open = (string) preg_replace( '/\stype=("|\')[^"\']*\1/', '', $m[0] );
			return (string) preg_replace( '/^<script\b/', '<script type="module"', $open );
		},
		(string) $tag,
		1
	);
}

/**
 * Mount options of the screen besides what the Flux GraphQL shell adds (rest, nonce, locale…).
 *
 * @param string $page Admin page slug.
 */
function flxk_flux_options( string $page ): array {
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	return array(
		'colors'     => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
		'classicUrl' => flxk_ui_switch_url( 'classic', $page ),
	);
}

// Flux GraphQL screen shell: first data printed by PHP, in-place navigation from the menu.
add_action(
	'admin_menu',
	static function (): void {
		if ( ! function_exists( 'flx_admin_screen' ) || ! flxk_flux_available() ) {
			return;
		}
		foreach ( array( 'flx-setup' => 'flxk-packages', 'flx-setup-settings' => 'flxk-settings' ) as $page => $module ) {
			flx_admin_screen(
				$page,
				array(
					'module'       => $module,
					'assets_dir'   => FLXK_DIR . 'assets/flux/',
					'assets_url'   => FLXK_URL . 'assets/flux/',
					'version'      => FLXK_VERSION,
					'enabled'      => 'flxk_flux_ui',
					'options'      => 'flxk_flux_options',
					'container_id' => 'flxk-app',
					'preload'      => 'flxk-packages' === $module ? array( array( 'FlxSetupOverview', array( 'search' => null ) ), array( 'FlxSetupHistory', array( 'page' => 1 ) ) ) : array(),
					'wrap_class'   => 'flxk-wrap',
				)
			);
		}
	},
	20
);

/**
 * Enqueues the bundle and mounts a module in #flxk-app.
 *
 * @param string $module Module id (flxk-packages|flxk-settings).
 * @param string $page   Admin page slug (for the classic view link).
 */
function flxk_flux_assets( string $module, string $page ): void {
	$ver  = FLXK_VERSION . '-' . (int) filemtime( FLXK_DIR . 'assets/flux/flux-modules.js' );
	$opts = array_merge(
		array(
			'module'   => $module,
			'rest'     => rest_url(),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'locale'   => get_user_locale(),
			'siteName' => get_bloginfo( 'name' ),
			'adminUrl' => admin_url(),
			'theme'    => 'light',
		),
		flxk_flux_options( $page )
	);
	wp_enqueue_style( 'flxk-flux', FLXK_URL . 'assets/flux/flux-modules.css', array(), $ver );
	wp_enqueue_script( 'flxk-flux', FLXK_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
	add_filter( 'script_loader_tag', 'flxk_flux_module_tag', 10, 2 );
	// Light / dark follows Flux Admin (html.flxa-dark), also when the user switches it on the page.
	wp_add_inline_script(
		'flxk-flux',
		'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxk-app";slot.opts=o;function sync(){var el=document.getElementById("flxk-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
		'before'
	);
}

/**
 * Prints the mount point and loads the bundle. True when the shared screen is shown.
 *
 * @param string $module Module id.
 * @param string $page   Admin page slug.
 */
function flxk_flux_render( string $module, string $page ): bool {
	if ( ! flxk_flux_ui() ) {
		return false;
	}
	if ( function_exists( 'flx_admin_screen_render' ) && flx_admin_screen_render() ) {
		return true;
	}
	flxk_flux_assets( $module, $page );
	echo '<style>#flxk-app.flux-root{height:auto;background:transparent;font-family:inherit}</style>';
	echo '<div class="wrap flxk-wrap"><div id="flxk-app"><noscript>' . esc_html__( 'JavaScript is required for this screen.', 'flx-setup' ) . '</noscript></div></div>';
	return true;
}

/**
 * On the classic pages: a link to the new view, or (without Flux GraphQL) the notice with the install button.
 *
 * @param string $page Admin page slug.
 */
function flxk_flux_classic_notice( string $page ): void {
	if ( flxk_flux_available() ) {
		echo '<p><a class="button" href="' . esc_url( flxk_ui_switch_url( 'flux', $page ) ) . '">' . esc_html__( 'New view', 'flx-setup' ) . '</a> <span class="description">' . esc_html__( 'Open the new view (same features, works in the FluxWP app too).', 'flx-setup' ) . '</span></p>';
	} elseif ( function_exists( 'flxk_graphql_notice' ) ) {
		flxk_graphql_notice( __( 'FluxWP Panel', 'flx-setup' ) );
	}
}

add_filter(
	'flxapp_modules',
	static function ( $m ) {
		$m                  = is_array( $m ) ? $m : array();
		$m['flxk-packages'] = array(
			'title'   => __( 'Packages', 'flx-setup' ),
			'cap'     => 'update_plugins',
			'menu'    => 'flx-setup',
			'url'     => admin_url( 'admin.php?page=flx-setup' ),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'package',
			'version' => FLXK_VERSION,
			'order'   => 20,
		);
		$m['flxk-settings'] = array(
			'title'   => __( 'Update settings', 'flx-setup' ),
			'cap'     => 'manage_options',
			'menu'    => 'flx-setup-settings',
			'url'     => admin_url( 'admin.php?page=flx-setup-settings' ),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'settings',
			'version' => FLXK_VERSION,
			'order'   => 21,
		);
		return $m;
	}
);
