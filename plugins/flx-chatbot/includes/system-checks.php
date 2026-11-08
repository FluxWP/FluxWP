<?php
/**
 * Ortak sistem kontrolü (`flx_system_checks`). Dış HTTP yok.
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_system_checks_versions',
	function ( $v ) {
		$v['flx-chatbot'] = FLXCB_VERSION;
		return $v;
	}
);

add_filter(
	'flx_system_checks',
	function ( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		$src    = 'Flux AI Chatbot';
		$mode   = (string) flxcb_setting( 'mode' );

		if ( ! flxcb_setting( 'enabled' ) ) {
			$checks[] = array(
				'id'           => 'flxcb.enabled',
				'source'       => $src,
				'group'        => 'Communication',
				'label'        => __( 'Site chat window', 'flx-chatbot' ),
				'status'       => 'info',
				'message'      => __( 'The window is off; visitors don\'t see it.', 'flx-chatbot' ),
				'action_url'   => flxcb_admin_url( 'general' ),
				'action_label' => __( 'Settings', 'flx-chatbot' ),
			);
			return $checks;
		}

		if ( 'live' !== $mode ) {
			$ok       = flxcb_ai_ready();
			$checks[] = array(
				'id'           => 'flxcb.ai',
				'source'       => $src,
				'group'        => 'Communication',
				'label'        => __( 'Chat assistant', 'flx-chatbot' ),
				'status'       => $ok ? 'ok' : 'warning',
				'message'      => $ok ? __( 'Flux AI connected; the assistant is answering.', 'flx-chatbot' ) : __( 'Flux AI is not ready; messages go to an agent instead of the assistant.', 'flx-chatbot' ),
				'action_url'   => $ok ? flx_ai_settings_url( 'flx-chatbot' ) : admin_url( 'admin.php?page=flx-ai' ),
				'action_label' => __( 'Flux AI', 'flx-chatbot' ),
			);
		}

		if ( 'ai' !== $mode ) {
			$target   = flxcb_handoff_target();
			$open     = flxcb_stats_open_count_safe();
			$checks[] = array(
				'id'           => 'flxcb.live',
				'source'       => $src,
				'group'        => 'Communication',
				'label'        => __( 'Live chat', 'flx-chatbot' ),
				'status'       => $open > 5 ? 'warning' : 'ok',
				'message'      => ( 'flx-chat' === $target ? __( 'Handoffs go to the Flux Chat inbox.', 'flx-chatbot' ) : __( 'Handoffs go to the plugin inbox.', 'flx-chatbot' ) )
					/* translators: %d: number of conversations */
					. ( $open ? ' ' . sprintf( _n( '%d chat is waiting for a reply.', '%d chats are waiting for a reply.', $open, 'flx-chatbot' ), $open ) : '' ),
				'action_url'   => flxcb_inbox_url(),
				'action_label' => __( 'Chats', 'flx-chatbot' ),
			);
		}
		return $checks;
	}
);

/** Menü rozeti sayacı yalnız yönetimde yüklenir; kontrol için doğrudan sorgu. */
function flxcb_stats_open_count_safe() {
	if ( function_exists( 'flxcb_stats_open_count' ) ) {
		return flxcb_stats_open_count();
	}
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . flxcb_table( 'conversations' ) . " WHERE status IN ('queued','offline') OR (status = 'agent' AND unread > 0)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}
