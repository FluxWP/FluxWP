<?php
/**
 * Flux Ads: yönetim menüsü. Her sekme (Rapor, Kampanyalar, Öneriler, Stüdyo, Sezon planı, Testler, Bağışçı değeri,
 * Reklam harcaması, Kitleler, Gönderim kaydı, Reklam bağlantısı, Ayarlar) FluxWP uygulamasıyla aynı React ekranını
 * açar (assets/flux/, tools/fluxapp/embed-sync.sh flx-ads; veri GraphQL'den), Flux GraphQL ekran kabuğuyla.
 * Klasik PHP sayfası yoktur; ekran açılamazsa kabuk "yüklenemedi" uyarısını gösterir.
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

final class FLXADS_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'admin_head', array( __CLASS__, 'menu_icon' ) );
	}

	public static function menu() {
		add_menu_page( 'Flux Ads', 'Flux Ads', 'flxads_view', 'flx-ads', array( __CLASS__, 'render' ), 'none', 58.6 );
		foreach ( self::tabs() as $tab => $label ) {
			$cap = 'settings' === $tab ? 'flxads_manage' : 'flxads_view';
			add_submenu_page( 'flx-ads', $label . ' ‹ Flux Ads', 'suggestions' === $tab ? self::opt_label() : $label, $cap, flxads_page_slug( $tab ), array( __CLASS__, 'render' ) );
		}
		if ( ! function_exists( 'flx_admin_screen' ) ) {
			return;
		}
		foreach ( array_keys( self::tabs() ) as $tab ) {
			flx_admin_screen(
				flxads_page_slug( $tab ),
				array(
					'module'     => 'flxads-ads',
					'assets_dir' => FLXADS_DIR . 'assets/flux/',
					'assets_url' => FLXADS_URL . 'assets/flux/',
					'version'    => FLXADS_VERSION,
					'start'       => $tab,
					'start_param' => 'tab',
					'options'    => array( __CLASS__, 'flux_options' ),
				)
			);
		}
	}

	/** Mount options of the screen (the shell adds rest, nonce, locale, site name, admin URL). */
	public static function flux_options() {
		$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
		return array(
			'colors' => array(
				'primary'         => $accent,
				'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
			),
		);
	}

	public static function menu_icon() {
		echo '<style>#toplevel_page_flx-ads .wp-menu-image{background:url(' . esc_url( FLXADS_URL . 'assets/icon.svg' ) . ') center/20px 20px no-repeat}</style>';
	}

	private static function tabs() {
		$t = array(
			'report'    => __( 'Report', 'flx-ads' ),
			'campaigns' => __( 'Campaigns', 'flx-ads' ),
			'suggestions' => __( 'Suggestions', 'flx-ads' ),
			'studio'    => __( 'Studio', 'flx-ads' ),
			'planner'   => __( 'Season plan', 'flx-ads' ),
			'experiments' => __( 'Tests', 'flx-ads' ),
			'value'     => __( 'Donor value', 'flx-ads' ),
			'spend'     => __( 'Ad spend', 'flx-ads' ),
			'audiences' => __( 'Audiences', 'flx-ads' ),
			'log'    => __( 'Send log', 'flx-ads' ),
			'links'  => __( 'Ad link', 'flx-ads' ),
		);
		if ( flxads_can( true ) ) {
			$t['settings'] = __( 'Settings', 'flx-ads' );
		}
		return $t;
	}

	public static function render() {
		if ( ! flxads_can() ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'flx-ads' ) );
		}
		flx_admin_screen_page();
	}

	private static function opt_label() {
		$n = count( FLXADS_Optimizer::open() );
		return __( 'Suggestions', 'flx-ads' ) . ( $n ? ' <span class="awaiting-mod count-' . $n . '"><span class="pending-count">' . number_format_i18n( $n ) . '</span></span>' : '' );
	}
}
