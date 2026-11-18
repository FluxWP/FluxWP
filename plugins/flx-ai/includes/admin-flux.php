<?php
/**
 * Flux AI & MCP: the shared React screens in wp-admin (0.23.0, GraphQL). Overview, Logs, Billing, Connected
 * systems and Tools open the same screen as the FluxWP app (assets/flux/, tools/fluxapp/embed-sync.sh
 * flx-ai). There is no classic page: without Flux GraphQL or the bundle the page says it cannot open.
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

/**
 * First query of each page with the variables the screen sends, printed by PHP so the page opens without a
 * spinner on the first visit too (later visits also use what the screen learned).
 *
 * @param string $view Start page.
 * @return list<array{0:string,1?:array<string,mixed>}>
 */
function flxai_flux_preload( $view ) {
	$map = array(
		'overview'    => array( 'FlxAiOverview', array( 'days' => 30, 'source' => null ) ),
		'logs'        => array( 'FlxAiLogs', array( 'first' => 50, 'after' => null, 'days' => 7, 'kind' => null, 'source' => null, 'errorsOnly' => false ) ),
		'billing'     => array( 'FlxAiBilling', array( 'month' => null ) ),
		'systems'     => array( 'FlxAiSystems' ),
		'tools'       => array( 'FlxAiTools' ),
		'settings'    => array( 'FlxAiSettingsScreen' ),
		'approvals'   => array( 'FlxAiApprovals' ),
		'tasks'       => array( 'FlxAiTasks' ),
		'skills'      => array( 'FlxAiSkills' ),
		'mcp'         => array( 'FlxAiMcp' ),
		'connections' => array( 'FlxAiConnections' ),
		'history'     => array( 'FlxAiChats', array( 'q' => '', 'archived' => 0, 'limit' => 30, 'offset' => 0 ) ),
		'chat'        => array( 'FlxAiChats', array( 'q' => '', 'archived' => 0, 'limit' => 40, 'offset' => 0 ) ),
	);
	return isset( $map[ $view ] ) ? array( $map[ $view ] ) : array();
}

function flxai_flux_available() {
	return file_exists( FLXAI_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
}

/** The screen the current request shows, or '' when the page is not a Flux AI screen or cannot open. */
function flxai_flux_view() {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$map  = flxai_flux_pages();
	if ( ! isset( $map[ $page ] ) || ! flxai_flux_available() ) {
		return '';
	}
	return $map[ $page ];
}

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

/**
 * Mount options of a screen besides what the shell adds (rest, nonce, locale…).
 *
 * @param string $page Page slug.
 */
function flxai_flux_options( $page = '' ) {
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	return array(
		'colors' => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
	);
}

// Flux GraphQL screen shell: first data printed by PHP, in-place navigation between the pages.
add_action(
	'admin_menu',
	static function () {
		if ( ! function_exists( 'flx_admin_screen' ) || ! flxai_flux_available() ) {
			return;
		}
		foreach ( flxai_flux_pages() as $page => $view ) {
			flx_admin_screen(
				$page,
				array(
					'module'       => 'flxai-admin',
					'assets_dir'   => FLXAI_DIR . 'assets/flux/',
					'assets_url'   => FLXAI_URL . 'assets/flux/',
					'version'      => FLXAI_VERSION,
					'options'      => 'flxai_flux_options',
					'start'        => $view,
					'container_id' => 'flxai-app',
					'wrap_class'   => 'flxai-flux',
					'preload'      => flxai_flux_preload( $view ),
				)
			);
		}
	},
	99
);

add_action(
	'admin_enqueue_scripts',
	function () {
		$view = flxai_flux_view();
		if ( '' === $view ) {
			return;
		}
		if ( function_exists( 'flx_admin_screen_current' ) && '' !== flx_admin_screen_current() ) {
			return; // The screen shell loads it.
		}
		$ver  = FLXAI_VERSION . '-' . (int) filemtime( FLXAI_DIR . 'assets/flux/flux-modules.js' );
		$opts = array_merge(
			array(
				'module'    => 'flxai-admin',
				'startPage' => $view,
				'rest'      => rest_url(),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'locale'    => get_user_locale(),
				'siteName'  => get_bloginfo( 'name' ),
				'adminUrl'  => admin_url(),
				'theme'     => 'light',
			),
			flxai_flux_options()
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
 * Callback of every Flux AI page: the React mount point and the "could not be loaded" box, or that box
 * alone when Flux GraphQL or the screen files are missing.
 */
function flxai_flux_render() {
	if ( '' === flxai_flux_view() ) {
		if ( function_exists( 'flx_admin_screen_unavailable' ) ) {
			flx_admin_screen_unavailable();
		} else {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Flux GraphQL is not active, so FluxWP screens cannot open.', 'flx-ai' ) . '</p></div></div>';
		}
		return;
	}
	if ( function_exists( 'flx_admin_screen_render' ) && flx_admin_screen_render() ) {
		return;
	}
	echo '<div class="wrap flxai-flux"><div id="flxai-app"></div>';
	if ( function_exists( 'flx_admin_screen_failsafe' ) ) {
		flx_admin_screen_failsafe( 'flxai-app' );
	}
	echo '</div>';
}
