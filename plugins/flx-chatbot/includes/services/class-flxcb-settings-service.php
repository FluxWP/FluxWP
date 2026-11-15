<?php
/**
 * Chatbot settings screen: saving, the Connections card and the data the live preview needs.
 * The classic settings page (admin-post), the GraphQL mutation and the React screen all use this class.
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

final class FLXCB_Settings_Service {

	/**
	 * Cleans the values like the form does, stores them and tells the rest of the plugin.
	 *
	 * @param array<string,mixed> $in Values by setting key; only the keys given change.
	 */
	public static function save( array $in ): void {
		flxcb_update_settings( flxcb_sanitize( $in ) );
		delete_transient( 'flxcb_team' );
		do_action( 'flxcb_settings_saved' );
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( 'flx-chatbot:settings' ) );
		}
	}

	/**
	 * The Connections card.
	 *
	 * @return list<FLXCB_Status_Row>
	 */
	public static function status_rows(): array {
		$s    = flxcb_settings();
		$rows = array();
		$fc   = flxcb_fc_ready();
		if ( flxcb_ai_ready() ) {
			$rows[] = new FLXCB_Status_Row( 'ai', 'ok', __( 'Flux AI & MCP connected', 'flx-chatbot' ), __( 'Key, model, spending limit and logs come from Flux AI.', 'flx-chatbot' ), FLXCB_Val::str( flx_ai_settings_url( 'flx-chatbot' ) ), __( 'Choose model', 'flx-chatbot' ) );
		} elseif ( function_exists( 'flxai_chat' ) ) {
			$rows[] = new FLXCB_Status_Row( 'ai', 'warn', __( 'Flux AI is not ready', 'flx-chatbot' ), __( 'Enter an API key in Flux AI & MCP or turn on Flux AI Chatbot under Connected systems.', 'flx-chatbot' ), admin_url( 'admin.php?page=flx-ai' ), __( 'Flux AI', 'flx-chatbot' ) );
		} else {
			$rows[] = new FLXCB_Status_Row( 'ai', 'warn', __( 'Flux AI & MCP is not installed', 'flx-chatbot' ), __( 'Install the Flux AI & MCP plugin for the assistant. Until then the window works as live chat only.', 'flx-chatbot' ), '', '' );
		}
		if ( 'ai' === $s['mode'] ) {
			$rows[] = new FLXCB_Status_Row( 'live', 'info', __( 'Live chat off', 'flx-chatbot' ), __( 'Mode is "AI only".', 'flx-chatbot' ), '', '' );
		} elseif ( 'flx-chat' === flxcb_handoff_target() ) {
			$rows[] = new FLXCB_Status_Row( 'live', 'ok', __( 'Flux Chat inbox', 'flx-chatbot' ), __( 'All site chats (including the ones the assistant answered) appear in the call center with the site chat icon; agents reply from the same screen as WhatsApp and other channels.', 'flx-chatbot' ), admin_url( 'admin.php?page=flx-chat' ), __( 'Flux Chat', 'flx-chatbot' ) );
		} else {
			$rows[] = new FLXCB_Status_Row( 'live', 'ok', __( 'Plugin inbox', 'flx-chatbot' ), $fc || flxcb_flux_chat_active() ? __( 'Handed-over chats land on the Chatbot › Chats screen.', 'flx-chatbot' ) : __( 'Handed-over chats land on the Chatbot › Chats screen. With Flux Chat (1.37+) installed they can also be answered from the call center.', 'flx-chatbot' ), flxcb_inbox_url(), __( 'Chats', 'flx-chatbot' ) );
		}
		$online = flxcb_is_online();
		if ( 'custom' === $s['hours_mode'] ) {
			/* translators: %s: hours text */
			$text = sprintf( __( 'Working hours: %s', 'flx-chatbot' ), flxcb_hours_text() );
		} elseif ( 'flx-chat' === $s['hours_mode'] ) {
			$text = __( 'Working hours come from the Flux Chat settings.', 'flx-chatbot' );
		} else {
			$text = __( 'Agents always count as online.', 'flx-chatbot' );
		}
		$rows[] = new FLXCB_Status_Row( 'hours', $online ? 'ok' : 'info', $online ? __( 'Online now', 'flx-chatbot' ) : __( 'Outside working hours now', 'flx-chatbot' ), $text, flxcb_admin_url( 'live' ), __( 'Hours', 'flx-chatbot' ) );
		return $rows;
	}

	/**
	 * Texts used when a text field is left empty.
	 *
	 * @return array<string,string>
	 */
	public static function default_texts(): array {
		$out = array();
		foreach ( array( 'bot_name', 'home_title', 'home_text', 'welcome', 'placeholder', 'teaser_text', 'offline_text' ) as $k ) {
			$out[ $k ] = flxcb_default_text( $k );
		}
		return $out;
	}

	/**
	 * Page types of the visibility tab.
	 *
	 * @return array<string,string>
	 */
	public static function page_types(): array {
		return flxcb_page_types();
	}

	/**
	 * Widget configuration of the live preview: the same object the site gets, in preview mode.
	 *
	 * @return array<string,mixed>
	 */
	public static function preview_config(): array {
		$cfg            = flxcb_widget_config();
		$cfg['preview'] = true;
		$cfg['enabled'] = (bool) flxcb_setting( 'enabled' );
		$cfg['demo']    = self::demo();
		return $cfg;
	}

	/**
	 * Sample conversation of the preview (no real chat needed). With a store, the newest product is a card.
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function demo(): array {
		$now  = flxcb_now_ms();
		$card = array();
		if ( function_exists( 'wc_get_products' ) ) {
			$ids = wc_get_products( array( 'status' => 'publish', 'limit' => 1, 'orderby' => 'date' ) );
			$p   = is_array( $ids ) && $ids ? flxcb_product_info( $ids[0] ) : null;
			if ( is_array( $p ) ) {
				$card = array( flxcb_product_card( $p ) );
			}
		}
		return array(
			array( 'id' => -1, 'role' => 'visitor', 'body' => __( 'Hi, how long does shipping take?', 'flx-chatbot' ), 'ts' => $now - 60000, 'read' => 1 ),
			array(
				'id'    => -2,
				'role'  => 'bot',
				'body'  => $card ? __( 'Orders ship within **1-3 business days**. Here is a product you might like:', 'flx-chatbot' ) : __( 'Orders ship within **1-3 business days**; shipping is free over $50. Anything else I can help with?', 'flx-chatbot' ),
				'ts'    => $now - 55000,
				'cards' => $card,
			),
		);
	}

	/**
	 * Avatar image thumbnail (empty when none).
	 */
	public static function avatar_thumb(): string {
		$id = (int) flxcb_setting( 'avatar_id' );
		return $id ? FLXCB_Val::str( wp_get_attachment_image_url( $id, 'thumbnail' ) ) : '';
	}
}
