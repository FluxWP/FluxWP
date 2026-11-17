<?php
/**
 * Flux AI & MCP › Chat history: every chat of the signed-in user, with search, archive, rename and delete. The
 * React screen is the main view; this classic page is the fallback when Flux GraphQL is not available.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

function flxai_page_history() {
	if ( flxai_flux_render() ) {
		return;
	}
	$page = FLXAI_Chats_Service::page( '', -1, 100 );
	flxai_page_head( __( 'Chat history', 'flx-ai' ), __( 'Your earlier chats with the assistant.', 'flx-ai' ) );
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Chat', 'flx-ai' ) . '</th><th>' . esc_html__( 'Last activity', 'flx-ai' ) . '</th></tr></thead><tbody>';
	foreach ( $page->items as $c ) {
		$title = '' !== $c->title ? $c->title : __( 'New chat', 'flx-ai' );
		echo '<tr><td><a href="' . esc_url( admin_url( 'admin.php?page=flx-ai-chat&flxai_chat=' . $c->id ) ) . '">' . esc_html( $title ) . '</a>' . ( $c->archived ? ' <em>(' . esc_html__( 'Archived', 'flx-ai' ) . ')</em>' : '' ) . '</td><td>' . esc_html( flx_date( $c->updated, 'j M Y H:i' ) ) . '</td></tr>';
	}
	if ( ! $page->items ) {
		echo '<tr><td colspan="2">' . esc_html__( 'No chats yet.', 'flx-ai' ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}
