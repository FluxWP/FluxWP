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
	return file_exists( FLXSEO_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
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

/** The chunked bundle loads as an ES module. */
function flxseo_flux_module_tag( $tag, $handle ) {
	if ( 'flxseo-flux' !== $handle ) {
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

function flxseo_flux_assets() {
	$ver    = FLXSEO_VERSION . '-' . (int) filemtime( FLXSEO_DIR . 'assets/flux/flux-modules.js' );
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	$tab    = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'genel'; // phpcs:ignore WordPress.Security.NonceVerification
	$opts   = array(
		'module'     => 'flxseo-seo',
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
		'startPage'  => isset( flxseo_tabs()[ $tab ] ) ? $tab : 'genel',
		'classicUrl' => flxseo_ui_switch_url( 'classic', $tab ),
	);
	wp_enqueue_style( 'flxseo-flux', FLXSEO_URL . 'assets/flux/flux-modules.css', array(), $ver );
	wp_enqueue_script( 'flxseo-flux', FLXSEO_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
	add_filter( 'script_loader_tag', 'flxseo_flux_module_tag', 10, 2 );
	// Light / dark follows Flux Admin (html.flxa-dark), also when the user switches it on the page.
	wp_add_inline_script(
		'flxseo-flux',
		'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxseo-app";slot.opts=o;function sync(){var el=document.getElementById("flxseo-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
		'before'
	);
}

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'toplevel_page_flxseo' === $hook && flxseo_flux_ui() ) {
			flxseo_flux_assets();
		}
	}
);
