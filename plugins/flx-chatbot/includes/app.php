<?php
/**
 * Flux App: Chatbot settings and Site chats are native screens (the same React screens as wp-admin,
 * @fluxwp/modules `flxcb-settings` and `flxcb-inbox`, data over GraphQL). App versions without the
 * screens open the admin pages instead.
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flxapp_modules',
	static function ( $m ) {
		$m = is_array( $m ) ? $m : array();
		$m['flxcb-settings'] = array(
			'title'   => __( 'Chatbot settings', 'flx-chatbot' ),
			'cap'     => 'manage_options',
			'menu'    => 'flx-chatbot',
			'url'     => flxcb_admin_url(),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'bot-message-square',
			'version' => FLXCB_VERSION,
			'order'   => 61,
		);
		if ( ! flxcb_fc_unified() ) {
			$m['flxcb-inbox'] = array(
				'title'   => __( 'Site chats', 'flx-chatbot' ),
				'cap'     => 'flxcb_reply',
				'menu'    => 'flx-chatbot-inbox',
				'url'     => admin_url( 'admin.php?page=flx-chatbot-inbox' ),
				'api'     => home_url( '/graphql' ),
				'icon'    => 'bot-message-square',
				'version' => FLXCB_VERSION,
				'order'   => 60,
			);
		}
		return $m;
	}
);
