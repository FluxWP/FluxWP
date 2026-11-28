<?php
/**
 * Yönetim: "POS" sayfası. Sayfa FluxWP uygulamasıyla aynı React ekranını açar (assets/flux/,
 * tools/fluxapp/embed-sync.sh flx-pos; veri GraphQL'den). Klasik PHP sayfası yoktur; ekran açılamazsa
 * Flux GraphQL "yüklenemedi" uyarısını gösterir.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

final class FLXPOS_Admin {

	const CAP = 'manage_woocommerce';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_head', array( __CLASS__, 'menu_logo' ) );
	}

	private static function cap() {
		return current_user_can( self::CAP ) ? self::CAP : 'manage_options';
	}

	public static function menu() {
		add_menu_page( __( 'Sanal POS', 'flx-pos' ), __( 'POS', 'flx-pos' ), self::cap(), 'flx-pos', array( __CLASS__, 'render' ), 'none', 56 );
		if ( function_exists( 'flx_admin_screen' ) ) {
			flx_admin_screen(
				'flx-pos',
				array(
					'start_param'  => 'tab',
					'module'       => 'flxpos-pos',
					'assets_dir'   => FLXPOS_DIR . 'assets/flux/',
					'assets_url'   => FLXPOS_URL . 'assets/flux/',
					'version'      => FLXPOS_VERSION,
					'options'      => array( __CLASS__, 'flux_options' ),
					'container_id' => 'flxpos-app',
					'preload'      => array( array( 'FlxPosMethods' ), array( 'FlxPosAccounts' ) ),
					'wrap_class'   => 'flxpos-wrap',
				)
			);
		}
	}

	/** Ekranın açılış seçenekleri. */
	public static function flux_options() {
		$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return array(
			'colors' => array(
				'primary'         => $accent,
				'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
			),
			'appIcon' => flxpos_logo_url(),
			'search' => $search,
		);
	}

	/** Renkli menü logosu (WP data-URI SVG'yi tek renge boyar). */
	public static function menu_logo() {
		echo '<style>#toplevel_page_flx-pos .wp-menu-image{background:url(' . esc_url( flxpos_logo_url( function_exists( 'flxa_logo_style' ) ? flxa_logo_style() : 'gradient' ) ) . ') center/20px 20px no-repeat}</style>';
	}

	public static function render() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'Bunu yapmaya yetkiniz yok.', 'flx-pos' ), 403 );
		}
		flx_admin_screen_page();
	}
}
