<?php
/**
 * Yönetim: Chatbot menüsü; ayarlar ve gelen kutusu ortak React ekranıdır (includes/app.php), klasik sayfa yoktur.
 * Alan tanımları ve temizleme includes/settings.php'de (GraphQL isteğinde de gerekir).
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

final class FLXCB_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 58 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_head', array( __CLASS__, 'menu_logo' ) );
	}

	public static function menu() {
		if ( flxcb_fc_unified() ) {
			// Tek gelen kutusu: sohbetler Flux Chat'te; burada yalnız ayarlar.
			add_menu_page( __( 'Flux AI Chatbot', 'flx-chatbot' ), __( 'Chatbot', 'flx-chatbot' ), 'manage_options', 'flx-chatbot', array( __CLASS__, 'render' ), 'none', 27 );
			return;
		}
		$open  = flxcb_stats_open_count();
		$label = __( 'Chatbot', 'flx-chatbot' ) . ( $open ? ' <span class="awaiting-mod">' . (int) $open . '</span>' : '' );
		add_menu_page( __( 'Flux AI Chatbot', 'flx-chatbot' ), $label, 'flxcb_reply', 'flx-chatbot-inbox', array( 'FLXCB_Inbox', 'render' ), 'none', 27 );
		add_submenu_page( 'flx-chatbot-inbox', __( 'Site chats', 'flx-chatbot' ), __( 'Chats', 'flx-chatbot' ), 'flxcb_reply', 'flx-chatbot-inbox', array( 'FLXCB_Inbox', 'render' ) );
		add_submenu_page( 'flx-chatbot-inbox', __( 'Chatbot settings', 'flx-chatbot' ), __( 'Settings', 'flx-chatbot' ), 'manage_options', 'flx-chatbot', array( __CLASS__, 'render' ) );
	}

	public static function menu_logo() {
		echo '<style>#toplevel_page_flx-chatbot-inbox .wp-menu-image,#toplevel_page_flx-chatbot .wp-menu-image{background:url(' . esc_url( FLXCB_URL . 'assets/icon.svg?ver=' . FLXCB_VERSION ) . ') center/20px 20px no-repeat}</style>';
	}

	private static function page() {
		return isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	public static function assets() {
		$page = self::page();
		if ( ( 'flx-chatbot' === $page || 'flx-chatbot-inbox' === $page ) && flxcb_flux_available() ) {
			flxcb_flux_assets( $page );
		}
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-chatbot' ), 403 );
		}
		flxcb_flux_mount();
	}
}

/** Açık (temsilci bekleyen / görüşülen) sohbet sayısı, menü rozeti için 60 sn önbellekli. */
function flxcb_stats_open_count() {
	$n = get_transient( 'flxcb_open_count' );
	if ( false === $n ) {
		global $wpdb;
		$n = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . flxcb_table( 'conversations' ) . " WHERE status IN ('queued','offline') OR (status = 'agent' AND unread > 0)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		set_transient( 'flxcb_open_count', $n, MINUTE_IN_SECONDS );
	}
	return (int) $n;
}

add_action( 'plugins_loaded', array( 'FLXCB_Admin', 'init' ), 15 );
