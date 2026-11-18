<?php
/**
 * Yönetim ekranı: Performans menüsü ve sekmeleri. Sayfa FluxWP uygulamasıyla aynı React ekranını açar
 * (assets/flux/, tools/fluxapp/embed-sync.sh flx-speed; veri GraphQL'den) ve Flux GraphQL ekran kabuğuyla basılır.
 * Klasik PHP sayfası yoktur; ekran açılamazsa kabuk "yüklenemedi" uyarısını gösterir.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

class FLXP_Admin {

	const SLUG = 'flx-speed';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_screen' ), 20 );
		add_action( 'admin_head', array( __CLASS__, 'menu_icon' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ) );
	}

	/** Mount options of the React screen (the Flux GraphQL screen shell adds rest, nonce, locale, site name, admin URL). */
	public static function flux_options() {
		$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
		return array(
			'colors' => array(
				'primary'         => $accent,
				'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
			),
		);
	}

	/*
	 * The screen opens through Flux GraphQL's screen shell (includes/screens.php there): first queries preloaded
	 * into the page, and the other Flux screens open from the menu without reloading wp-admin.
	 */
	public static function register_screen() {
		if ( ! function_exists( 'flx_admin_screen' ) ) {
			return;
		}
		flx_admin_screen(
			self::SLUG,
			array(
				'module'      => 'flxp-speed',
				'assets_dir'  => FLXP_DIR . 'assets/flux/',
				'assets_url'  => FLXP_URL . 'assets/flux/',
				'version'     => FLXP_VERSION,
				'options'     => array( __CLASS__, 'flux_options' ),
				'start'       => 'summary',
				'start_param' => 'tab',
				'preload'     => array( array( 'FlxSpeedConfig' ), array( 'FlxSpeedLive' ), array( 'FlxSpeedSummary', array( 'hours' => 24 ) ) ),
			)
		);
	}

	/** Sekme → [başlık, yalnız yönetici]. */
	private static function tab_list() {
		return array(
			'summary'  => array( __( 'Summary', 'flx-speed' ), false ),
			'records'  => array( __( 'Records', 'flx-speed' ), false ),
			'server'   => array( __( 'Server', 'flx-speed' ), false ),
			'database' => array( __( 'Database', 'flx-speed' ), false ),
			'autoload' => array( 'Autoload', false ),
			'cron'     => array( 'Cron', false ),
			'system'   => array( __( 'System check', 'flx-speed' ), true ),
			'pagespeed' => array( 'PageSpeed', true ),
			'cache'    => array( __( 'Cache', 'flx-speed' ), true ),
			'settings' => array( __( 'Settings', 'flx-speed' ), true ),
		);
	}

	private static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'summary'; // phpcs:ignore
		$all = self::tab_list();
		if ( ! isset( $all[ $tab ] ) || ( $all[ $tab ][1] && ! current_user_can( 'manage_options' ) ) ) {
			$tab = 'summary';
		}
		return $tab;
	}

	/** Alt menüde doğru öğe seçili görünsün. */
	public static function submenu_file( $file ) {
		if ( isset( $_GET['page'] ) && self::SLUG === $_GET['page'] ) { // phpcs:ignore
			$tab = isset( $_GET['id'] ) ? 'records' : self::current_tab(); // phpcs:ignore
			return 'summary' === $tab ? self::SLUG : self::SLUG . '&tab=' . $tab;
		}
		return $file;
	}

	public static function menu() {
		$cap = current_user_can( 'manage_options' ) ? 'manage_options' : 'flxp_view';
		add_menu_page( __( 'Flux Performance', 'flx-speed' ), __( 'Performance', 'flx-speed' ), $cap, self::SLUG, array( __CLASS__, 'page' ), 'none', 81 );
		foreach ( self::tab_list() as $k => $t ) {
			$slug = 'summary' === $k ? self::SLUG : self::SLUG . '&tab=' . $k;
			add_submenu_page( self::SLUG, $t[0], $t[0], $t[1] ? 'manage_options' : $cap, $slug, 'summary' === $k ? array( __CLASS__, 'page' ) : '__return_null' );
		}
	}

	/** Renkli menü logosu (WP data-URI SVG'yi tek renge boyar). */
	public static function menu_icon() {
		echo '<style>#toplevel_page_flx-speed .wp-menu-image{background:url(' . esc_url( FLXP_URL . 'assets/icon.svg' ) . ') center/20px 20px no-repeat}</style>';
	}

	public static function page() {
		if ( ! flxp_can_view() ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'flx-speed' ) );
		}
		flx_admin_screen_page();
	}
}
FLXP_Admin::init();
