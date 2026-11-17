<?php
/**
 * Admin page: the React screen (assets/flux/, tools/fluxapp/embed-sync.sh flx-headless) opened through Flux
 * GraphQL's screen shell. This package is new and GraphQL-only, so there is no classic page; without the
 * bundle or Flux GraphQL a short notice is shown.
 *
 * @package FLX_Headless
 */

defined( 'ABSPATH' ) || exit;

final class FLXHL_Admin {

	public const CAP = 'manage_options';

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_menu', array( self::class, 'screen' ), 20 );
	}

	public static function menu(): void {
		add_menu_page( __( 'Headless', 'flx-headless' ), __( 'Headless', 'flx-headless' ), self::CAP, 'flx-headless', array( self::class, 'render' ), 'dashicons-admin-site-alt3', 82 );
	}

	public static function available(): bool {
		return file_exists( FLXHL_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_admin_screen' ) && function_exists( 'flx_gql_ready' ) && flx_gql_ready();
	}

	/** @return array<string,mixed> Mount options (the shell adds GraphQL URL, nonce, locale, site name). */
	public static function options(): array {
		$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
		return array(
			'colors' => array(
				'primary'         => $accent,
				'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
			),
		);
	}

	public static function screen(): void {
		if ( ! function_exists( 'flx_admin_screen' ) ) {
			return;
		}
		flx_admin_screen(
			'flx-headless',
			array(
				'module'     => 'flxhl-headless',
				'assets_dir' => FLXHL_DIR . 'assets/flux/',
				'assets_url' => FLXHL_URL . 'assets/flux/',
				'version'    => FLXHL_VERSION,
				'enabled'    => array( self::class, 'available' ),
				'options'    => array( self::class, 'options' ),
				'preload'    => array( array( 'FlxHeadlessScreen' ) ),
			)
		);
	}

	public static function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		if ( self::available() && function_exists( 'flx_admin_screen_render' ) && flx_admin_screen_render() ) {
			return;
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Headless', 'flx-headless' ) . '</h1><div class="notice notice-warning"><p>'
			. esc_html__( 'This screen needs Flux GraphQL (0.4.0 or newer). Install or update it from the FluxWP Packages screen.', 'flx-headless' )
			. '</p></div></div>';
	}
}

FLXHL_Admin::init();
