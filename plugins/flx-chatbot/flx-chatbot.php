<?php
/**
 * Plugin Name:       Flux AI Chatbot, Live Chat & Customer Support
 * Description:       Siteye yapay zekâ destekli sohbet penceresi: Flux AI ile sitenizin içeriğinden ve ürünlerinden 7/24 cevap, potansiyel müşteri toplama, WooCommerce bilgisi, tek tıkla canlı temsilciye devir (Flux Chat gelen kutusu ya da eklentinin kendi gelen kutusu), dosya paylaşımı, okundu bilgisi, sohbet sonu puanlama ve mesai dışında mesaj bırakma. Renk, konum, avatar, metinler ve görünürlük panelden özelleştirilir.
 * Version:           0.4.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Requires Plugins:  flx-graphql
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

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).
require_once __DIR__ . '/includes/flx-ai-sdk/loader.php'; // Flux AI SDK (tools/ai-sdk).

if ( defined( 'FLXCB_VERSION' ) ) {
	return;
}

define( 'FLXCB_VERSION', '0.4.0' );
define( 'FLXCB_DB_VERSION', '1' );
define( 'FLXCB_FILE', __FILE__ );
define( 'FLXCB_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXCB_URL', plugin_dir_url( __FILE__ ) );

require_once FLXCB_DIR . 'includes/services/class-flxcb-dto.php';
require_once FLXCB_DIR . 'includes/helpers.php';
require_once FLXCB_DIR . 'includes/settings.php';
require_once FLXCB_DIR . 'includes/install.php';
require_once FLXCB_DIR . 'includes/store.php';
require_once FLXCB_DIR . 'includes/hours.php';
require_once FLXCB_DIR . 'includes/knowledge.php';
require_once FLXCB_DIR . 'includes/bot.php';
require_once FLXCB_DIR . 'includes/live.php';
require_once FLXCB_DIR . 'includes/flux-chat.php';
require_once FLXCB_DIR . 'includes/services/class-flxcb-chat-service.php';
require_once FLXCB_DIR . 'includes/services/class-flxcb-settings-service.php';
require_once FLXCB_DIR . 'includes/rest.php';
require_once FLXCB_DIR . 'includes/front.php';
require_once FLXCB_DIR . 'includes/woo.php';
require_once FLXCB_DIR . 'includes/ai.php';
require_once FLXCB_DIR . 'includes/system-checks.php';
require_once FLXCB_DIR . 'includes/app.php';
if ( is_admin() ) {
	require_once FLXCB_DIR . 'includes/admin.php';
	require_once FLXCB_DIR . 'includes/admin-inbox.php';
}

/*
 * GraphQL (Flux GraphQL 0.2.0+): the schema is only loaded inside a GraphQL request. The agent side of the
 * chat (list, read, reply, close, delete), the figures and the settings screen read and write through it;
 * the visitor side of the chat stays on REST. Without Flux GraphQL the admin pages say they cannot open.
 */
add_action(
	'flx_gql_register',
	static function () {
		if ( ! function_exists( 'flx_gql_any_cap' ) ) {
			return;
		}
		require_once FLXCB_DIR . 'includes/graphql/schema.php';
	}
);
add_action(
	'flx_gql_loaders',
	static function () {
		flx_gql_node_loader( 'FlxChatbotConversation', 'FLXCB_Conversation_Loader' );
	}
);
add_filter(
	'flx_gql_persisted_manifests',
	static function ( $m ) {
		$m                = is_array( $m ) ? $m : array();
		$m['flx-chatbot'] = FLXCB_DIR . 'graphql/persisted-queries.json';
		return $m;
	}
);
add_filter(
	'flx_gql_capabilities',
	static function ( $caps ) {
		$caps = is_array( $caps ) ? $caps : array();
		if ( ! isset( $caps['flxcb_reply'] ) ) {
			$caps['flxcb_reply'] = array( 'label' => __( 'Answers site chats', 'flx-chatbot' ), 'group' => 'communication', 'package' => 'flx-chatbot' );
		}
		return $caps;
	}
);
/* Agents attach a file to a reply: sent first to /wp-json/flx/v1/upload, then taken over by the mutation. */
add_filter(
	'flx_gql_upload_contexts',
	static function ( $c ) {
		$c                          = is_array( $c ) ? $c : array();
		$c['flx-chatbot/reply']     = array(
			'cap'    => static fn(): bool => flxcb_can_reply(),
			'max_mb' => max( 1, (int) flxcb_setting( 'upload_mb' ) ),
		);
		return $c;
	}
);

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
		array_unshift( $links, '<a href="' . esc_url( flxcb_admin_url() ) . '">' . esc_html__( 'Settings', 'flx-chatbot' ) . '</a>' );
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
		$heads['flx-chatbot'] = array( 'Flux AI Chatbot', __( 'AI chat window, live chat and customer support', 'flx-chatbot' ) );
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
			'label' => __( 'Site chats', 'flx-chatbot' ),
			'url'   => admin_url( 'admin.php?page=flx-chatbot-inbox' ),
			'cap'   => 'flxcb_reply',
			'icon'  => 'bot-message-square',
		);
		return $apps;
	}
);
