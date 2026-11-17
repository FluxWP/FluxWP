<?php
/**
 * Flux AI & MCP: the shared React screens in wp-admin (0.23.0, GraphQL). Overview, Logs, Billing, Connected
 * systems and Tools open the same screen as the FluxWP app (assets/flux/, tools/fluxapp/embed-sync.sh
 * flx-ai). A user can switch back to the classic page from the screen header. Needs Flux GraphQL.
 *
 * The Chat page and its history are screens too. The floating chat (every other page) and the FluxApp embed stay on
 * assets/js/chat.js; both speak the same chat REST protocol.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** Page slug => screen. */
function flxai_flux_pages() {
	return array(
		'flx-ai'         => 'overview',
		'flx-ai-log'     => 'logs',
		'flx-ai-billing' => 'billing',
		'flx-ai-systems' => 'systems',
		'flx-ai-tools'   => 'tools',
		'flx-ai-settings' => 'settings',
		'flx-ai-approvals' => 'approvals',
		'flx-ai-tasks' => 'tasks',
		'flx-ai-skills' => 'skills',
		'flx-ai-mcp' => 'mcp',
		'flx-ai-connections' => 'connections',
		'flx-ai-history' => 'history',
		'flx-ai-chat' => 'chat',
	);
}

function flxai_flux_available() {
	return file_exists( FLXAI_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
}

/** The screen the current request shows, or '' for the classic page. */
function flxai_flux_view() {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$map  = flxai_flux_pages();
	if ( ! isset( $map[ $page ] ) || ! flxai_flux_available() || 'classic' === get_user_meta( get_current_user_id(), 'flxai_ui', true ) ) {
		return '';
	}
	return $map[ $page ];
}

function flxai_ui_switch_url( $ui, $page = '' ) {
	$page = '' !== $page ? $page : ( isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'flx-ai' ); // phpcs:ignore WordPress.Security.NonceVerification
	$args = array( 'page' => $page, 'flxai_ui' => 'flux' === $ui ? 'flux' : 'classic' );
	if ( 'flx-ai-systems' === $page && ! empty( $_GET['system'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$args['system'] = sanitize_key( wp_unslash( $_GET['system'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	if ( 'flx-ai-chat' === $page && ! empty( $_GET['flxai_chat'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$args['flxai_chat'] = (int) $_GET['flxai_chat']; // phpcs:ignore WordPress.Security.NonceVerification
	}
	return wp_nonce_url( add_query_arg( $args, admin_url( 'admin.php' ) ), 'flxai_ui' );
}

add_action(
	'admin_init',
	function () {
		if ( ! isset( $_GET['flxai_ui'], $_GET['page'] ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( flxai_flux_pages()[ $page ] ) ) {
			return;
		}
		check_admin_referer( 'flxai_ui' );
		if ( 'classic' === $_GET['flxai_ui'] ) {
			update_user_meta( get_current_user_id(), 'flxai_ui', 'classic' );
		} else {
			delete_user_meta( get_current_user_id(), 'flxai_ui' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . $page . ( 'flx-ai-systems' === $page && ! empty( $_GET['system'] ) ? '&system=' . rawurlencode( sanitize_key( wp_unslash( $_GET['system'] ) ) ) : '' ) . ( 'flx-ai-chat' === $page && ! empty( $_GET['flxai_chat'] ) ? '&flxai_chat=' . (int) $_GET['flxai_chat'] : '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		exit;
	}
);

/** The chunked bundle loads as an ES module. */
function flxai_flux_module_tag( $tag, $handle ) {
	if ( 'flxai-flux' !== $handle ) {
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

add_action(
	'admin_enqueue_scripts',
	function () {
		$view = flxai_flux_view();
		if ( '' === $view ) {
			return;
		}
		wp_dequeue_style( 'flxai-admin' );
		$ver    = FLXAI_VERSION . '-' . (int) filemtime( FLXAI_DIR . 'assets/flux/flux-modules.js' );
		$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
		$opts   = array(
			'module'     => 'flxai-admin',
			'startPage'  => $view,
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
			'classicUrl' => flxai_ui_switch_url( 'classic' ),
		);
		wp_enqueue_style( 'flxai-flux', FLXAI_URL . 'assets/flux/flux-modules.css', array(), $ver );
		wp_enqueue_script( 'flxai-flux', FLXAI_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
		add_filter( 'script_loader_tag', 'flxai_flux_module_tag', 10, 2 );
		wp_add_inline_script(
			'flxai-flux',
			'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxai-app";slot.opts=o;function sync(){var el=document.getElementById("flxai-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
			'before'
		);
	},
	30
);

/**
 * Called first thing in each classic page: prints the React mount point and returns true when the
 * page should not render its classic markup.
 */
function flxai_flux_render() {
	if ( '' === flxai_flux_view() ) {
		return false;
	}
	echo '<div class="wrap flxai-flux"><div id="flxai-app"></div></div>';
	return true;
}

/** Switch link on classic pages that have a React screen. */
function flxai_flux_new_link() {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! isset( flxai_flux_pages()[ $page ] ) || ! flxai_flux_available() ) {
		return;
	}
	echo '<p><a class="button" href="' . esc_url( flxai_ui_switch_url( 'flux' ) ) . '">' . esc_html__( 'New view', 'flx-ai' ) . '</a> <span class="flxai-note">' . esc_html__( 'Open the new view (same features, works in the FluxWP app too).', 'flx-ai' ) . '</span></p>';
}
