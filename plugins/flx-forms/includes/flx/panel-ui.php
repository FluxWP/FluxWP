<?php
/**
 * Panel page (`admin.php?page=Emsfb`): the React screen is the default, the classic panel stays one click away.
 *
 * The React screen is the `flxf-forms` screen of @fluxwp/modules, the same code the FluxWP app runs
 * (bundle in assets/flux/, built by tools/fluxapp/embed-sync.sh flx-forms). Every read and write goes through
 * the Flux GraphQL fields of this plugin. The form builder (Emsfb_create) and the other admin pages are
 * not touched. A user switches views with a link; the choice is the `flxf_ui` user meta (`classic`).
 * Without Flux GraphQL or without the bundle the classic panel opens as before.
 *
 * @package FLX_Forms
 */

defined( 'ABSPATH' ) || exit;

final class FLXF_Panel_UI {

	const PAGE = 'Emsfb';

	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'ui_switch' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 5 );
		add_action( 'admin_notices', array( __CLASS__, 'classic_notice' ), 1 );
	}

	private static function is_panel_page(): bool {
		return is_admin() && isset( $_GET['page'] ) && self::PAGE === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/** The bundle is built and Flux GraphQL answers. */
	public static function available(): bool {
		return file_exists( EMSFB_PLUGIN_DIRECTORY . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && flx_gql_ready();
	}

	/** This request draws the React screen. */
	public static function active(): bool {
		return self::is_panel_page() && self::available() && 'classic' !== get_user_meta( get_current_user_id(), 'flxf_ui', true );
	}

	public static function panel_url( array $args = array() ): string {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . self::PAGE ) );
	}

	public static function switch_url( string $ui ): string {
		return wp_nonce_url( self::panel_url( array( 'flxf_ui' => 'flux' === $ui ? 'flux' : 'classic' ) ), 'flxf_ui' );
	}

	public static function ui_switch(): void {
		if ( ! isset( $_GET['flxf_ui'] ) || ! self::is_panel_page() || ! current_user_can( 'Emsfb' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		check_admin_referer( 'flxf_ui' );
		if ( 'classic' === $_GET['flxf_ui'] ) {
			update_user_meta( get_current_user_id(), 'flxf_ui', 'classic' );
		} else {
			delete_user_meta( get_current_user_id(), 'flxf_ui' );
		}
		wp_safe_redirect( self::panel_url() );
		exit;
	}

	/** A link back to the new view on the classic panel. */
	public static function classic_notice(): void {
		if ( ! self::is_panel_page() || ! self::available() || self::active() ) {
			return;
		}
		echo '<div class="notice notice-info inline" style="margin:12px 20px 0 0"><p><a href="' . esc_url( self::switch_url( 'flux' ) ) . '">' . esc_html__( 'New view', 'flx-forms' ) . '</a> ' . esc_html__( 'with the same features, built on Flux GraphQL.', 'flx-forms' ) . '</p></div>';
	}

	/** The chunked bundle loads as an ES module. */
	public static function module_tag( $tag, $handle ) {
		if ( 'flxf-flux' !== $handle ) {
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

	public static function assets(): void {
		if ( ! self::active() ) {
			return;
		}
		$ver    = EMSFB_PLUGIN_VERSION . '-' . (int) filemtime( EMSFB_PLUGIN_DIRECTORY . 'assets/flux/flux-modules.js' );
		$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
		$opts   = array(
			'module'     => 'flxf-forms',
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
			'classicUrl' => self::switch_url( 'classic' ),
		);
		wp_enqueue_style( 'flxf-flux', EMSFB_PLUGIN_URL . 'assets/flux/flux-modules.css', array(), $ver );
		wp_enqueue_script( 'flxf-flux', EMSFB_PLUGIN_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
		add_filter( 'script_loader_tag', array( __CLASS__, 'module_tag' ), 10, 2 );
		// Light / dark follows Flux Admin (html.flxa-dark), also when the user switches it on the page.
		wp_add_inline_script(
			'flxf-flux',
			'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxf-app";slot.opts=o;function sync(){var el=document.getElementById("flxf-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
			'before'
		);
	}

	/** Page body of the React view. */
	public static function render(): void {
		echo '<div class="wrap flxf-react"><div id="flxf-app"></div></div>';
	}
}

FLXF_Panel_UI::init();
