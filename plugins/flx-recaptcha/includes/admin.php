<?php
/**
 * Yönetim: "reCAPTCHA" sayfası. Sayfa FluxWP uygulamasıyla aynı React ekranını açar (assets/flux/,
 * tools/fluxapp/embed-sync.sh flx-recaptcha; veri GraphQL'den). Klasik PHP sayfası yoktur; ekran açılamazsa
 * Flux GraphQL "yüklenemedi" uyarısını gösterir.
 *
 * @package FLX_Recaptcha
 */

defined( 'ABSPATH' ) || exit;

final class FLXRC_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_head', array( __CLASS__, 'menu_logo' ) );
		add_action( 'admin_notices', array( __CLASS__, 'nag' ) );
	}

	public static function menu() {
		add_menu_page( __( 'reCAPTCHA', 'flx-recaptcha' ), __( 'reCAPTCHA', 'flx-recaptcha' ), self::CAP, 'flx-recaptcha', array( __CLASS__, 'render' ), 'none', 81 );
		if ( function_exists( 'flx_admin_screen' ) ) {
			flx_admin_screen(
				'flx-recaptcha',
				array(
					'module'     => 'flxrc-recaptcha',
					'assets_dir' => FLXRC_DIR . 'assets/flux/',
					'assets_url' => FLXRC_URL . 'assets/flux/',
					'version'    => FLXRC_VERSION,
					'options'    => array( __CLASS__, 'flux_options' ),
				)
			);
		}
	}

	/** Mount options of the screen. */
	public static function flux_options() {
		$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
		return array(
			'colors' => array(
				'primary'         => $accent,
				'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
			),
		);
	}

	/** Renkli menü logosu (WP data-URI SVG'yi tek renge boyar). */
	public static function menu_logo() {
		echo '<style>#toplevel_page_flx-recaptcha .wp-menu-image{background:url(' . esc_url( FLXRC_URL . 'assets/icon.svg?ver=' . FLXRC_VERSION ) . ') center/20px 20px no-repeat}</style>';
	}

	/** Anahtar yoksa ya da test edilmediyse eklentiler ekranında küçük uyarı. */
	public static function nag() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'plugins' !== $screen->id || ! current_user_can( self::CAP ) || flxrc_keys_verified() || flxrc_disabled_by_constant() ) {
			return;
		}
		echo '<div class="notice notice-info"><p><strong>Flux reCAPTCHA:</strong> ' . esc_html__( 'Forms are not protected yet. Enter your Google reCAPTCHA keys and test them.', 'flx-recaptcha' ) . ' <a href="' . esc_url( flxrc_admin_url() ) . '">' . esc_html__( 'Go to settings', 'flx-recaptcha' ) . '</a></p></div>';
	}

	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-recaptcha' ), 403 );
		}
		flx_admin_screen_page();
	}
}
