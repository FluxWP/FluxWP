<?php
/**
 * Posta kutusu: gelen/gönderilen e-postalar, taslaklar (AI dahil), şablonlar.
 * Ekranı assets/js/mailbox.js çizer; veriler /flxi/v1/mailbox REST uçlarından gelir.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

function flxi_mailbox_boot() {
	$layouts = array();
	foreach ( flxi_layouts() as $id => $l ) {
		$layouts[] = array_merge( $l, array( 'id' => $id ) );
	}
	// phpcs:disable WordPress.Security.NonceVerification
	$open = array(
		'mail'  => (int) ( $_GET['id'] ?? 0 ),
		'draft' => (int) ( $_GET['taslak'] ?? 0 ),
		'view'  => sanitize_key( wp_unslash( $_GET['view'] ?? '' ) ),
	);
	// phpcs:enable
	return array(
		'rest'     => esc_url_raw( rest_url( 'flxi/v1/mailbox/' ) ),
		'nonce'    => wp_create_nonce( 'wp_rest' ),
		'settings' => flxi_admin_url( 'flxi-ayarlar' ) . '#posta',
		'status'   => flxi_mailbox_status(),
		'layouts'  => $layouts,
		'open'     => $open,
		'me'       => flxi_setting( 'from_email' ),
		'i18n'     => array(
			'inbox'        => __( 'Inbox', 'flx-mail' ),
			'review'       => __( 'Awaiting approval', 'flx-mail' ),
			'drafts'       => __( 'Drafts', 'flx-mail' ),
			'sent'         => __( 'Sent', 'flx-mail' ),
			'layouts'      => __( 'Layouts', 'flx-mail' ),
			'newMail'      => __( 'New email', 'flx-mail' ),
			'search'       => __( 'Search: person, subject…', 'flx-mail' ),
			'system'       => __( 'System emails', 'flx-mail' ),
			'unreadOnly'   => __( 'Unread', 'flx-mail' ),
			'more'         => __( 'Load more', 'flx-mail' ),
			'empty'        => __( 'No emails here.', 'flx-mail' ),
			'emptyDrafts'  => __( 'No drafts.', 'flx-mail' ),
			'pick'         => __( 'Select an email on the left to read it.', 'flx-mail' ),
			'sync'         => __( 'Sync', 'flx-mail' ),
			'syncing'      => __( 'Syncing…', 'flx-mail' ),
			/* translators: %s: süre */
			'lastSync'     => __( 'Last sync: %s', 'flx-mail' ),
			'syncMore'     => __( 'Fetching older emails…', 'flx-mail' ),
			'off'          => __( 'Mailbox is off or IMAP details are missing. Fill in Settings › Mailbox to see incoming email. Writing and sending drafts still works.', 'flx-mail' ),
			'openSettings' => __( 'Open settings', 'flx-mail' ),
			'reply'        => __( 'Reply', 'flx-mail' ),
			'replyAll'     => __( 'Reply all', 'flx-mail' ),
			'forward'      => __( 'Forward', 'flx-mail' ),
			'aiReply'      => __( 'Write reply with AI', 'flx-mail' ),
			'aiWrite'      => __( 'Write with AI', 'flx-mail' ),
			'aiFix'        => __( 'Fix with AI', 'flx-mail' ),
			'aiHint'       => __( 'Optional instruction: "thank them, invite them to the 11 November event"', 'flx-mail' ),
			'aiNewHint'    => __( 'What should it say? e.g. "A short, warm tree-planting partnership offer to company X"', 'flx-mail' ),
			'aiWorking'    => __( 'AI is writing…', 'flx-mail' ),
			'aiOff'        => __( 'AI needs the Flux AI & MCP plugin and its key.', 'flx-mail' ),
			'aiDraft'      => __( 'AI draft: check and edit it, then send. Nothing goes out until you send it.', 'flx-mail' ),
			'markUnread'   => __( 'Mark unread', 'flx-mail' ),
			'star'         => __( 'Star', 'flx-mail' ),
			'from'         => __( 'From', 'flx-mail' ),
			'to'           => __( 'To', 'flx-mail' ),
			'cc'           => __( 'Cc', 'flx-mail' ),
			'subject'      => __( 'Subject', 'flx-mail' ),
			'layout'       => __( 'Layout', 'flx-mail' ),
			'send'         => __( 'Send', 'flx-mail' ),
			'sending'      => __( 'Sending…', 'flx-mail' ),
			'sentOk'       => __( 'Sent', 'flx-mail' ),
			'save'         => __( 'Save', 'flx-mail' ),
			'saved'        => __( 'Saved', 'flx-mail' ),
			'preview'      => __( 'Preview', 'flx-mail' ),
			'delete'       => __( 'Delete', 'flx-mail' ),
			'confirmDel'   => __( 'Delete?', 'flx-mail' ),
			/* translators: %s: alıcılar */
			'confirmSend'  => __( 'Send this email now? To: %s', 'flx-mail' ),
			'replyingTo'   => __( 'Replying to', 'flx-mail' ),
			'quoteNote'    => __( 'The earlier conversation is added below the email automatically.', 'flx-mail' ),
			'images'       => __( 'Show external images', 'flx-mail' ),
			'imagesBlocked' => __( 'External images were not shown for privacy.', 'flx-mail' ),
			'thread'       => __( 'This conversation', 'flx-mail' ),
			'attachments'  => __( 'Attachments', 'flx-mail' ),
			'back'         => __( 'Back', 'flx-mail' ),
			'noSubject'    => __( '(no subject)', 'flx-mail' ),
			'me'           => __( 'me', 'flx-mail' ),
			'ai'           => 'AI',
			'sys'          => __( 'System', 'flx-mail' ),
			'replied'      => __( 'Replied', 'flx-mail' ),
			'error'        => __( 'Error', 'flx-mail' ),
			'bold'         => __( 'Bold', 'flx-mail' ),
			'italic'       => __( 'Italic', 'flx-mail' ),
			'link'         => __( 'Link', 'flx-mail' ),
			'linkUrl'      => __( 'Link address:', 'flx-mail' ),
			'list'         => __( 'List', 'flx-mail' ),
			'clear'        => __( 'Clear formatting', 'flx-mail' ),
			'logView'      => __( 'Open in logs', 'flx-mail' ),
			// Şablonlar
			'layoutsIntro' => __( 'Layouts chosen when sending from the mailbox. In sub-layouts like "Örnek Vakfı × Company" the company logo appears at the top; the preset subject and content fill a new email.', 'flx-mail' ),
			'newLayout'    => __( 'New sub-layout', 'flx-mail' ),
			'builtin'      => __( 'Built-in layout (cannot be changed)', 'flx-mail' ),
			'lName'        => __( 'Layout name', 'flx-mail' ),
			'lDesc'        => __( 'Short description', 'flx-mail' ),
			'lBase'        => __( 'Appearance', 'flx-mail' ),
			'lBaseCard'    => __( 'Corporate (card)', 'flx-mail' ),
			'lBaseLetter'  => __( 'Plain (letter)', 'flx-mail' ),
			'lPartner'     => __( 'Company name', 'flx-mail' ),
			'lLogo'        => __( 'Company logo', 'flx-mail' ),
			'lLogoPick'    => __( 'Choose logo', 'flx-mail' ),
			'lUrl'         => __( 'Company website', 'flx-mail' ),
			'lAccent'      => __( 'Accent color', 'flx-mail' ),
			'lHeading'     => __( 'Heading (large heading in corporate)', 'flx-mail' ),
			'lSubject'     => __( 'Preset subject', 'flx-mail' ),
			'lBody'        => __( 'Preset content', 'flx-mail' ),
			'useLayout'    => __( 'New email with this layout', 'flx-mail' ),
			'builtinShort' => __( 'Built-in', 'flx-mail' ),
			'aiShort'      => __( 'AI', 'flx-mail' ),
			'close'        => __( 'Close', 'flx-mail' ),
			'previewTitle' => __( 'Preview', 'flx-mail' ),
			'desktop'      => __( 'Desktop', 'flx-mail' ),
			'phone'        => __( 'Phone', 'flx-mail' ),
			'dark'         => __( 'Dark', 'flx-mail' ),
			'leave'        => __( 'There are unsaved changes. Close anyway?', 'flx-mail' ),
		),
	);
}

function flxi_page_mailbox() {
	flxi_page_head( __( 'Mailbox', 'flx-mail' ), __( 'Incoming and outgoing email for bilgi@, drafts and AI replies.', 'flx-mail' ), false );
	echo '<div class="flxm" data-flxm><p class="flxi-muted">' . esc_html__( 'Loading…', 'flx-mail' ) . '</p></div>';
	flxi_page_foot();
}
