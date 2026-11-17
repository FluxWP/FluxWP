<?php
/**
 * Flux App: Chatbot settings and Site chats are native screens (the same React screens as wp-admin,
 * @fluxwp/modules `flxcb-settings` and `flxcb-inbox`, data over GraphQL). App versions without the
 * screens open the admin pages instead.
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flxapp_modules',
	static function ( $m ) {
		$m = is_array( $m ) ? $m : array();
		$m['flxcb-settings'] = array(
			'title'   => __( 'Chatbot settings', 'flx-chatbot' ),
			'cap'     => 'manage_options',
			'menu'    => 'flx-chatbot',
			'url'     => flxcb_admin_url(),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'bot-message-square',
			'version' => FLXCB_VERSION,
			'order'   => 61,
		);
		if ( ! flxcb_fc_unified() ) {
			$m['flxcb-inbox'] = array(
				'title'   => __( 'Site chats', 'flx-chatbot' ),
				'cap'     => 'flxcb_reply',
				'menu'    => 'flx-chatbot-inbox',
				'url'     => admin_url( 'admin.php?page=flx-chatbot-inbox' ),
				'api'     => home_url( '/graphql' ),
				'icon'    => 'bot-message-square',
				'version' => FLXCB_VERSION,
				'order'   => 60,
			);
		}
		return $m;
	}
);

/*
 * New view (0.2.0, GraphQL): the admin pages open the same React screens as the FluxWP app
 * (assets/flux/, tools/fluxapp/embed-sync.sh flx-chatbot). It is the default and has every feature of the
 * classic pages; a user can switch back to the classic page from the screen header for now.
 * Needs Flux GraphQL.
 */
function flxcb_flux_available() {
	return file_exists( FLXCB_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
}

function flxcb_flux_ui() {
	return flxcb_flux_available() && 'classic' !== get_user_meta( get_current_user_id(), 'flxcb_ui', true );
}

/** Page slug => module id of the new view. */
function flxcb_flux_pages() {
	return array(
		'flx-chatbot'       => 'flxcb-settings',
		'flx-chatbot-inbox' => 'flxcb-inbox',
	);
}

/** Link that switches the current user between the views, back to the given page. */
function flxcb_ui_switch_url( $ui, $page = 'flx-chatbot' ) {
	return wp_nonce_url( admin_url( 'admin.php?page=' . rawurlencode( $page ) . '&flxcb_ui=' . ( 'flux' === $ui ? 'flux' : 'classic' ) ), 'flxcb_ui' );
}

add_action(
	'admin_init',
	static function () {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $_GET['flxcb_ui'] ) || ! isset( flxcb_flux_pages()[ $page ] ) || ! is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		check_admin_referer( 'flxcb_ui' );
		if ( 'classic' === $_GET['flxcb_ui'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			update_user_meta( get_current_user_id(), 'flxcb_ui', 'classic' );
		} else {
			delete_user_meta( get_current_user_id(), 'flxcb_ui' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . $page ) );
		exit;
	}
);

/** The chunked bundle loads as an ES module. */
function flxcb_flux_module_tag( $tag, $handle ) {
	if ( 'flxcb-flux' !== $handle ) {
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
 * Mount options of the screen besides what the Flux GraphQL shell adds (rest, nonce, locale…).
 *
 * @param string $page Page slug.
 */
function flxcb_flux_options( $page ) {
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	return array(
		'siteUrl'    => home_url( '/' ),
		'colors'     => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
		'classicUrl' => flxcb_ui_switch_url( 'classic', $page ),
	);
}

// Flux GraphQL screen shell: first data printed by PHP, in-place navigation from the menu.
add_action(
	'admin_menu',
	static function () {
		if ( ! function_exists( 'flx_admin_screen' ) || ! flxcb_flux_available() ) {
			return;
		}
		foreach ( flxcb_flux_pages() as $page => $module ) {
			$settings = 'flx-chatbot' === $page;
			flx_admin_screen(
				$page,
				array(
					'module'       => $module,
					'assets_dir'   => FLXCB_DIR . 'assets/flux/',
					'assets_url'   => FLXCB_URL . 'assets/flux/',
					'version'      => FLXCB_VERSION,
					'enabled'      => 'flxcb_flux_ui',
					'options'      => 'flxcb_flux_options',
					'container_id' => 'flxcb-app',
					'preload'      => $settings ? array( array( 'FlxChatbotSettingsScreen' ) ) : array(),
					'wrap_class'   => 'flxcb-wrap',
					// Avatar picker: the media library is on its own page; elsewhere the link loads the page.
					'enqueue'      => $settings ? static fn () => wp_enqueue_media() : null,
					'needs'        => $settings ? array( 'wp.media' ) : array(),
				)
			);
		}
	},
	20
);

/** Enqueues the bundle and mounts the screen of this admin page. */
function flxcb_flux_assets( $page ) {
	$module = flxcb_flux_pages()[ $page ] ?? '';
	if ( '' === $module || ( function_exists( 'flx_admin_screen_current' ) && '' !== flx_admin_screen_current() ) ) {
		return; // The Flux GraphQL screen shell loads the bundle.
	}
	$ver  = FLXCB_VERSION . '-' . (int) filemtime( FLXCB_DIR . 'assets/flux/flux-modules.js' );
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
		flxcb_flux_options( $page )
	);
	if ( 'flx-chatbot' === $page ) {
		wp_enqueue_media(); // Avatar picker.
	}
	wp_enqueue_style( 'flxcb-flux', FLXCB_URL . 'assets/flux/flux-modules.css', array(), $ver );
	wp_enqueue_script( 'flxcb-flux', FLXCB_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
	add_filter( 'script_loader_tag', 'flxcb_flux_module_tag', 10, 2 );
	// Light / dark follows Flux Admin (html.flxa-dark), also when the user switches it on the page.
	wp_add_inline_script(
		'flxcb-flux',
		'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxcb-app";slot.opts=o;function sync(){var el=document.getElementById("flxcb-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
		'before'
	);
}

/** Markup the screen mounts into. */
function flxcb_flux_mount() {
	if ( function_exists( 'flx_admin_screen_render' ) && flx_admin_screen_render() ) {
		return;
	}
	echo '<style>#flxcb-app.flux-root{height:auto;background:transparent;font-family:inherit}</style>';
	echo '<div class="wrap flxcb-wrap"><div id="flxcb-app"><noscript>' . esc_html__( 'JavaScript is required for this screen.', 'flx-chatbot' ) . '</noscript></div></div>';
}

/** "New view" link of the classic pages. */
function flxcb_flux_switch_link( $page ) {
	if ( ! flxcb_flux_available() ) {
		return;
	}
	echo '<p><a class="button" href="' . esc_url( flxcb_ui_switch_url( 'flux', $page ) ) . '">' . esc_html__( 'New view', 'flx-chatbot' ) . '</a> <span class="flxcb-muted">' . esc_html__( 'Open the new view (same features, works in the FluxWP app too).', 'flx-chatbot' ) . '</span></p>';
}
