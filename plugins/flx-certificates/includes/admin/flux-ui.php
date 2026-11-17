<?php
/**
 * Records, new certificate and people open the React screen (assets/flux/, tools/fluxapp/embed-sync.sh
 * flx-certificates). It is the default when Flux GraphQL is available. A user can switch back to the
 * classic pages; pages that have not moved stay classic.
 *
 * @package flx-certificates
 */

defined( 'ABSPATH' ) || exit;

/**
 * The bundle and Flux GraphQL are both present.
 */
function flxs_flux_available(): bool {
	return is_readable( FLXS_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
}

/**
 * This user sees the React screen.
 */
function flxs_flux_ui(): bool {
	return flxs_flux_available() && 'classic' !== get_user_meta( get_current_user_id(), 'flxs_ui', true );
}

/**
 * Pages that have a React screen. The rest stay classic.
 *
 * @return string[]
 */
function flxs_flux_pages(): array {
	return array( 'flxs', 'flxs-yeni', 'flxs-kisiler' );
}

/**
 * @return array<string,string>
 */
function flxs_flux_current_args(): array {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'flxs'; // phpcs:ignore WordPress.Security.NonceVerification
	$args = array( 'page' => $page );
	foreach ( array( 'id', 'person', 'add', 's', 'status', 'event', 'template', 'ref', 'by' ) as $key ) {
		if ( isset( $_GET[ $key ] ) && '' !== (string) $_GET[ $key ] ) { // phpcs:ignore WordPress.Security.NonceVerification
			$args[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
	}
	return $args;
}

/**
 * Link that switches this user between the views.
 */
function flxs_ui_switch_url( string $ui ): string {
	$args           = flxs_flux_current_args();
	$args['flxs_ui'] = 'flux' === $ui ? 'flux' : 'classic';
	// Not wp_nonce_url(): it HTML-escapes the ampersand, and this address also goes to a link in JSON (classicUrl).
	// Markup output passes it through esc_url().
	$args['_wpnonce'] = wp_create_nonce( 'flxs_ui' );
	return esc_url_raw( add_query_arg( $args, admin_url( 'admin.php' ) ) );
}

add_action(
	'admin_init',
	static function (): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $_GET['flxs_ui'] ) || ! in_array( $page, flxs_flux_pages(), true ) || ! flxs_can_use() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		check_admin_referer( 'flxs_ui' );
		if ( 'classic' === $_GET['flxs_ui'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			update_user_meta( get_current_user_id(), 'flxs_ui', 'classic' );
		} else {
			delete_user_meta( get_current_user_id(), 'flxs_ui' );
		}
		$args = flxs_flux_current_args();
		unset( $args['flxs_ui'] );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	},
	1
);

/**
 * startPage for the React screen.
 */
function flxs_flux_start(): string {
	$args = flxs_flux_current_args();
	$page = $args['page'];
	if ( 'flxs-yeni' === $page ) {
		return ! empty( $args['id'] ) ? 'new:' . (int) $args['id'] : 'new';
	}
	if ( 'flxs-kisiler' === $page ) {
		if ( ! empty( $args['person'] ) ) {
			return 'person:' . (int) $args['person'];
		}
		return ! empty( $args['add'] ) ? 'people:add' : 'people';
	}
	return 'records';
}

/**
 * @param string $tag    Tag.
 * @param string $handle Handle.
 */
function flxs_flux_module_tag( $tag, $handle ): string {
	if ( 'flxs-flux' !== $handle ) {
		return (string) $tag;
	}
	$replaced = preg_replace_callback(
		'/<script\b[^>]*\ssrc=[^>]*>/',
		static function ( array $m ): string {
			$open = (string) preg_replace( '/\stype=("|\')[^"\']*\1/', '', $m[0] );
			return (string) preg_replace( '/^<script\b/', '<script type="module"', $open );
		},
		(string) $tag,
		1
	);
	return is_string( $replaced ) ? $replaced : (string) $tag;
}

/**
 * Prints the React mount and returns true when the new view is on.
 */
function flxs_render_flux(): bool {
	if ( ! flxs_flux_ui() ) {
		return false;
	}
	$ver    = FLXS_VERSION . '-' . (int) filemtime( FLXS_DIR . 'assets/flux/flux-modules.js' );
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	$opts   = array(
		'module'     => 'flxs-certificates',
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
		'appIcon'    => FLXS_URL . 'assets/icon.svg',
		'startPage'  => flxs_flux_start(),
		'classicUrl' => flxs_ui_switch_url( 'classic' ),
	);
	wp_enqueue_style( 'flxs-flux', FLXS_URL . 'assets/flux/flux-modules.css', array(), $ver );
	wp_enqueue_script( 'flxs-flux', FLXS_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
	add_filter( 'script_loader_tag', 'flxs_flux_module_tag', 10, 2 );
	wp_add_inline_script(
		'flxs-flux',
		'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxs-app";slot.opts=o;function sync(){var el=document.getElementById("flxs-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
		'before'
	);
	echo '<style>#flxs-app.flux-root{height:auto;background:transparent;font-family:inherit}</style>';
	echo '<div class="wrap flxs-react"><div id="flxs-app"><noscript>' . esc_html__( 'JavaScript is required for this screen.', 'flx-certificates' ) . '</noscript></div></div>';
	return true;
}

/**
 * Classic pages that have a React twin link to it.
 *
 * @param array<int,array<string,string>> $actions Actions.
 * @return array<int,array<string,string>>
 */
function flxs_classic_switch( array $actions ): array {
	if ( flxs_flux_available() && ! flxs_flux_ui() ) {
		array_unshift(
			$actions,
			array(
				'label' => __( 'New view', 'flx-certificates' ),
				'url'   => flxs_ui_switch_url( 'flux' ),
				'class' => '',
			)
		);
	}
	return $actions;
}
