<?php
/**
 * New view (0.10.0, GraphQL): the CRM pages open the same React screen as the FluxWP app (assets/flux/,
 * tools/fluxapp/embed-sync.sh flx-crm). It is the default and has every feature of the classic pages; a user
 * can switch back to the classic pages from the screen header for now. Needs Flux GraphQL.
 * Events and the person-card sections other plugins add are part of this screen.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

function flxcrm_flux_available() {
	return file_exists( FLXCRM_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
}

function flxcrm_flux_ui() {
	return flxcrm_flux_available() && 'classic' !== get_user_meta( get_current_user_id(), 'flxcrm_ui', true );
}

/** Link that switches the current user between the views. */
function flxcrm_ui_switch_url( $ui ) {
	return wp_nonce_url( add_query_arg( array( 'flxcrm_ui' => 'flux' === $ui ? 'flux' : 'classic' ), flxcrm_admin_url() ), 'flxcrm_ui' );
}

add_action(
	'admin_init',
	function () {
		if ( ! isset( $_GET['flxcrm_ui'], $_GET['page'] ) || 0 !== strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), 'flx-crm' ) || ! flxcrm_can() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		check_admin_referer( 'flxcrm_ui' );
		if ( 'classic' === $_GET['flxcrm_ui'] ) {
			update_user_meta( get_current_user_id(), 'flxcrm_ui', 'classic' );
		} else {
			delete_user_meta( get_current_user_id(), 'flxcrm_ui' );
		}
		wp_safe_redirect( flxcrm_admin_url() );
		exit;
	}
);

/** The screen the current admin page starts on (hash route inside the React screen). */
function flxcrm_flux_start() {
	// phpcs:disable WordPress.Security.NonceVerification
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'flx-crm';
	if ( 'flx-crm-events' === $page ) {
		if ( ! empty( $_GET['add'] ) ) {
			return 'events/new';
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( $id ) {
			$q = array();
			foreach ( array( 'view', 'sub', 'send', 'ch', 'st' ) as $k ) {
				if ( isset( $_GET[ $k ] ) && '' !== (string) $_GET[ $k ] ) {
					$q[ $k ] = sanitize_text_field( wp_unslash( (string) $_GET[ $k ] ) );
				}
			}
			if ( ! empty( $_GET['edit'] ) ) {
				$q['edit'] = '1';
			}
			return 'event/' . $id . ( $q ? '?' . http_build_query( $q ) : '' );
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : '';
		return in_array( $tab, array( 'archive', 'arsiv', 'closed' ), true ) ? 'events?status=closed' : ( 'site' === $tab ? 'events?tab=site' : 'events' );
	}
	if ( 'flx-crm-contacts' === $page ) {
		if ( ! empty( $_GET['id'] ) ) {
			return 'contact/' . absint( $_GET['id'] );
		}
		if ( isset( $_GET['action'] ) && 'new' === $_GET['action'] ) {
			return 'contacts/new';
		}
		if ( isset( $_GET['view'] ) && 'duplicates' === $_GET['view'] ) {
			return 'duplicates';
		}
		return 'contacts';
	}
	// phpcs:enable WordPress.Security.NonceVerification
	$map = array(
		'flx-crm'          => 'overview',
		'flx-crm-segments' => 'segments',
		'flx-crm-journeys' => 'journeys',
		'flx-crm-settings' => 'settings',
		'flx-crm-events'   => 'events',
	);
	return $map[ $page ] ?? 'overview';
}

/** Classic render callback or the React screen, whichever the user has on. */
function flxcrm_flux_page( $classic ) {
	return static function () use ( $classic ) {
		if ( ! flxcrm_can() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-crm' ), 403 );
		}
		if ( ! flxcrm_flux_ui() ) {
			call_user_func( $classic );
			return;
		}
		echo '<style>#flxcrm-app.flux-root{height:auto;background:transparent;font-family:inherit}</style>';
		echo '<div class="wrap flxcrm-wrap"><div id="flxcrm-app"><noscript>' . esc_html__( 'JavaScript is required for this screen.', 'flx-crm' ) . '</noscript></div></div>';
	};
}

/** True on the CRM pages that show the React screen. */
function flxcrm_flux_screen() {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	return flxcrm_flux_ui() && 0 === strpos( $page, 'flx-crm' );
}

/** The chunked bundle loads as an ES module. */
function flxcrm_flux_module_tag( $tag, $handle ) {
	if ( 'flxcrm-flux' !== $handle ) {
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

function flxcrm_flux_assets() {
	$ver    = FLXCRM_VERSION . '-' . (int) filemtime( FLXCRM_DIR . 'assets/flux/flux-modules.js' );
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#be185d';
	$opts   = array(
		'module'     => 'crm',
		'rest'       => rest_url(),
		'nonce'      => wp_create_nonce( 'wp_rest' ),
		'locale'     => get_user_locale(),
		'siteName'   => get_bloginfo( 'name' ),
		'adminUrl'   => admin_url(),
		'theme'      => 'light',
		'startPage'  => flxcrm_flux_start(),
		'colors'     => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
		'classicUrl' => flxcrm_ui_switch_url( 'classic' ),
	);
	wp_enqueue_media(); // Event image and message file; the Events tab is a hash route on every CRM page.
	wp_enqueue_style( 'flxcrm-flux', FLXCRM_URL . 'assets/flux/flux-modules.css', array(), $ver );
	wp_enqueue_script( 'flxcrm-flux', FLXCRM_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
	add_filter( 'script_loader_tag', 'flxcrm_flux_module_tag', 10, 2 );
	// Light / dark follows Flux Admin (html.flxa-dark), also when the user switches it on the page.
	wp_add_inline_script(
		'flxcrm-flux',
		'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxcrm-app";slot.opts=o;function sync(){var el=document.getElementById("flxcrm-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
		'before'
	);
}
