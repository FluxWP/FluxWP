<?php
/**
 * Yönetim: site sohbetleri gelen kutusu. Yalnız Flux Chat kurulu değilse ya da ayarda "Eklentinin gelen kutusu" seçildiyse
 * görünür; Flux Chat varken tüm sohbetler çağrı merkezinin tek gelen kutusundadır.
 * Ekran iskeletini PHP basar; liste, sohbet ve cevap assets/inbox.js ile REST agent/* uçlarından gelir.
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

final class FLXCB_Inbox {

	/** inbox.js metinleri. */
	public static function strings() {
		return array(
			'open'        => __( 'Open', 'flx-chatbot' ),
			'all'         => __( 'All', 'flx-chatbot' ),
			'bot'         => __( 'With assistant', 'flx-chatbot' ),
			'closed'      => __( 'Closed', 'flx-chatbot' ),
			'search'      => __( 'Search name, email, phone or page', 'flx-chatbot' ),
			'empty'       => __( 'No chats in this view.', 'flx-chatbot' ),
			'pick'        => __( 'Pick a chat on the left.', 'flx-chatbot' ),
			'loading'     => __( 'Loading…', 'flx-chatbot' ),
			'placeholder' => __( 'Type your reply… (Enter sends, Shift+Enter for a new line)', 'flx-chatbot' ),
			'send'        => __( 'Send', 'flx-chatbot' ),
			'attach'      => __( 'Attach file', 'flx-chatbot' ),
			'close'       => __( 'Close chat', 'flx-chatbot' ),
			'delete'      => __( 'Delete', 'flx-chatbot' ),
			'confirmDel'  => __( 'Delete this chat and its files permanently?', 'flx-chatbot' ),
			'confirmEnd'  => __( 'Close the chat? It reopens if the visitor writes again.', 'flx-chatbot' ),
			'fluxChat'    => __( 'Open in Flux Chat', 'flx-chatbot' ),
			'inFluxChat'  => __( 'This chat is also in the Flux Chat inbox; replies from either place reach the visitor.', 'flx-chatbot' ),
			'visitor'     => __( 'Visitor', 'flx-chatbot' ),
			'you'         => __( 'You', 'flx-chatbot' ),
			'online'      => __( 'On the page', 'flx-chatbot' ),
			'away'        => __( 'Left', 'flx-chatbot' ),
			'typing'      => __( 'typing…', 'flx-chatbot' ),
			'page'        => __( 'Page', 'flx-chatbot' ),
			'email'       => __( 'Email', 'flx-chatbot' ),
			'phone'       => __( 'Phone', 'flx-chatbot' ),
			'member'      => __( 'Member', 'flx-chatbot' ),
			'order'       => __( 'Order', 'flx-chatbot' ),
			'rating'      => __( 'Rating', 'flx-chatbot' ),
			'summary'     => __( 'Assistant\'s note', 'flx-chatbot' ),
			'lead'        => __( 'Lead', 'flx-chatbot' ),
			'assigned'    => __( 'Agent', 'flx-chatbot' ),
			'error'       => __( 'Something went wrong. Please try again.', 'flx-chatbot' ),
			'st_bot'      => __( 'With assistant', 'flx-chatbot' ),
			'st_queued'   => __( 'Waiting for an agent', 'flx-chatbot' ),
			'st_agent'    => __( 'In conversation', 'flx-chatbot' ),
			'st_offline'  => __( 'Left a message', 'flx-chatbot' ),
			'st_closed'   => __( 'Closed', 'flx-chatbot' ),
			'more'        => __( 'More', 'flx-chatbot' ),
			'back'        => __( 'Back to list', 'flx-chatbot' ),
			'details'     => __( 'Details', 'flx-chatbot' ),
			'products'    => __( 'Suggested products', 'flx-chatbot' ),
		);
	}

	public static function render() {
		if ( ! flxcb_can_reply() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-chatbot' ), 403 );
		}
		$fc = 'flx-chat' === flxcb_handoff_target();
		echo '<div class="wrap flxcb flxcb-inbox-wrap">';
		echo '<div class="flxcb-head"><div><h1>' . esc_html__( 'Site chats', 'flx-chatbot' ) . '</h1><p class="flxcb-lead">' . esc_html__( 'Conversations from the chat window on your site. Chats the assistant answered are here too; visitors asking for an agent are on top.', 'flx-chatbot' ) . '</p></div>';
		echo '<div class="flxcb-head__actions">';
		if ( $fc ) {
			echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=flx-chat' ) ) . '">' . flxcb_icon( 'chat', 14 ) . esc_html__( 'Flux Chat', 'flx-chatbot' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		if ( current_user_can( 'manage_options' ) ) {
			echo '<a class="button" href="' . esc_url( flxcb_admin_url() ) . '">' . flxcb_icon( 'settings', 14 ) . esc_html__( 'Settings', 'flx-chatbot' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div></div>';
		echo '<div class="flxcb-inbox" data-flxcb-inbox>';
		echo '<aside class="flxcb-ilist"><div class="flxcb-ilist__top"><div class="flxcb-seg" role="tablist" data-filters></div><input type="search" class="flxcb-isearch" data-search></div><ul class="flxcb-iitems" data-items></ul></aside>';
		echo '<section class="flxcb-iconv" data-conv><div class="flxcb-iempty">' . flxcb_icon( 'chat', 28 ) . '<p data-pick></p></div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div></div>';
	}
}
