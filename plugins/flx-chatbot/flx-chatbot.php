<?php
/**
 * Plugin Name:       Flux AI Chatbot, Live Chat & Customer Support
 * Description:       Siteye yapay zekâ destekli sohbet penceresi: Flux AI ile sitenizin içeriğinden ve ürünlerinden 7/24 cevap, potansiyel müşteri toplama, WooCommerce bilgisi, tek tıkla canlı temsilciye devir (Flux Chat gelen kutusu ya da eklentinin kendi gelen kutusu), dosya paylaşımı, okundu bilgisi, sohbet sonu puanlama ve mesai dışında mesaj bırakma. Renk, konum, avatar, metinler ve görünürlük panelden özelleştirilir.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flx-chatbot
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-chatbot
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).
require_once __DIR__ . '/includes/flx-ai-sdk/loader.php'; // Flux AI SDK (tools/ai-sdk).

if ( defined( 'FLXCB_VERSION' ) ) {
	return;
}

define( 'FLXCB_VERSION', '0.1.0' );
define( 'FLXCB_DB_VERSION', '1' );
define( 'FLXCB_FILE', __FILE__ );
define( 'FLXCB_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXCB_URL', plugin_dir_url( __FILE__ ) );

require_once FLXCB_DIR . 'includes/helpers.php';
require_once FLXCB_DIR . 'includes/install.php';
require_once FLXCB_DIR . 'includes/store.php';
require_once FLXCB_DIR . 'includes/hours.php';
require_once FLXCB_DIR . 'includes/knowledge.php';
require_once FLXCB_DIR . 'includes/bot.php';
require_once FLXCB_DIR . 'includes/live.php';
require_once FLXCB_DIR . 'includes/flux-chat.php';
require_once FLXCB_DIR . 'includes/rest.php';
require_once FLXCB_DIR . 'includes/front.php';
require_once FLXCB_DIR . 'includes/woo.php';
require_once FLXCB_DIR . 'includes/ai.php';
require_once FLXCB_DIR . 'includes/system-checks.php';
if ( is_admin() ) {
	require_once FLXCB_DIR . 'includes/admin.php';
	require_once FLXCB_DIR . 'includes/admin-inbox.php';
}

register_activation_hook( __FILE__, 'flxcb_install' );
register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'flxcb_cleanup' );
	}
);

/*
 * Çeviri: kaynak metinler Türkçe; site/kullanıcı dili İngilizce olduğunda languages/ içindeki çeviri kullanılır.
 * en_GB, en_AU gibi diğer İngilizce yerel ayarları en_US çevirisine düşer.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'flx-chatbot', false, dirname( plugin_basename( FLXCB_FILE ) ) . '/languages' );
	},
	1
);
add_filter(
	'load_textdomain_mofile',
	function ( $mofile, $domain ) {
		if ( 'flx-chatbot' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXCB_DIR . 'languages/flx-chatbot-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

add_filter(
	'plugin_action_links_' . plugin_basename( FLXCB_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( flxcb_admin_url() ) . '">' . esc_html__( 'Ayarlar', 'flx-chatbot' ) . '</a>' );
		return $links;
	}
);

/** Güncellemeler ekranında paket logosu. */
add_filter(
	'flxu_package_icons',
	function ( $icons, $slug ) {
		if ( 'flx-chatbot' === $slug && ! $icons ) {
			$url   = FLXCB_URL . 'assets/icon.svg?ver=' . FLXCB_VERSION;
			$icons = array( 'svg' => $url, '1x' => $url, '2x' => $url );
		}
		return $icons;
	},
	10,
	2
);

/* Flux Admin: sayfa başlığı, menü logosu ve "İletişim" grubunda yer. */
add_filter(
	'flxa_page_heads',
	function ( $map ) {
		$map['flx-chatbot']       = 'flx-chatbot';
		$map['flx-chatbot-inbox'] = 'flx-chatbot';
		return $map;
	}
);
add_filter(
	'flxa_package_heads',
	function ( $heads ) {
		$heads['flx-chatbot'] = array( 'Flux AI Chatbot', __( 'Yapay zekâ sohbet penceresi, canlı destek ve müşteri hizmetleri', 'flx-chatbot' ) );
		return $heads;
	}
);
add_filter(
	'flxa_menu_logos',
	function ( $m ) {
		$m['flx-chatbot-inbox'] = 'flx-chatbot.svg';
		$m['flx-chatbot']       = 'flx-chatbot.svg';
		return $m;
	}
);
add_filter(
	'flxa_default_groups',
	function ( $groups ) {
		if ( isset( $groups['comms'][1] ) && is_array( $groups['comms'][1] ) && ! in_array( 'flx-chatbot', $groups['comms'][1], true ) ) {
			$at = array_search( 'flx-chat', $groups['comms'][1], true );
			array_splice( $groups['comms'][1], false === $at ? 0 : $at + 1, 0, array( 'flx-chatbot-inbox', 'flx-chatbot' ) );
		}
		return $groups;
	}
);
add_filter(
	'flxa_apps',
	function ( $apps ) {
		if ( function_exists( 'flxcb_fc_unified' ) && flxcb_fc_unified() ) {
			return $apps; // sohbetler Flux Chat uygulamasında
		}
		$apps['chatbot'] = array(
			'label' => __( 'Site sohbetleri', 'flx-chatbot' ),
			'url'   => admin_url( 'admin.php?page=flx-chatbot-inbox' ),
			'cap'   => 'flxcb_reply',
			'icon'  => 'bot-message-square',
		);
		return $apps;
	}
);
