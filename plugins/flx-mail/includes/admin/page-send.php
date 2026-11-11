<?php
/**
 * Gönder: tekil ve toplu e-posta / WhatsApp / SMS.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

function flxi_page_send() {
	global $wpdb;
	$cid = isset( $_GET['campaign'] ) ? (int) $_GET['campaign'] : 0; // phpcs:ignore
	flxi_page_head( __( 'Send', 'flx-mail' ), __( 'Send an email, WhatsApp or SMS message to one person or a list using the branded template. Bulk sends are processed in the background, one after another.', 'flx-mail' ) );

	if ( $cid ) { echo '<p><a class="button button-primary" href="' . esc_url( flxi_admin_url( 'flxi-kuyruk', array( 'campaign' => $cid ) ) ) . '">' . esc_html__( 'View delivery queue', 'flx-mail' ) . '</a></p>'; }

	$sources = flxi_recipient_sources();
	$conns   = array( 'whatsapp' => flxi_connection_choices( 'whatsapp' ), 'sms' => flxi_connection_choices( 'sms' ) );

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxi-editor" id="flxi-send" data-key="">';
	wp_nonce_field( 'flxi_create_campaign' );
	echo '<input type="hidden" name="action" value="flxi_create_campaign">';
	echo '<div class="flxi-editor__form"><div class="flxi-card">';

	echo '<fieldset class="flxi-field"><legend>' . esc_html__( 'Channel', 'flx-mail' ) . '</legend><div class="flxi-seg flxi-seg--wide" data-channel-seg>';
	foreach ( flxi_channels() as $ch => $label ) {
		$dis = 'email' !== $ch && ! $conns[ $ch ];
		printf( '<label class="%s"><input type="radio" name="channel" value="%s"%s%s> %s%s</label>', $dis ? 'is-disabled' : '', esc_attr( $ch ), checked( $ch, 'email', false ), disabled( $dis, true, false ), esc_html( $label ), $dis ? ' <small>(' . esc_html__( 'no connection', 'flx-mail' ) . ')</small>' : '' );
	}
	echo '</div></fieldset>';

	// Alıcılar
	echo '<div class="flxi-field"><span>' . esc_html__( 'Recipients', 'flx-mail' ) . '</span>';
	echo '<textarea name="manual" rows="4" data-recipients placeholder="' . esc_attr__( "ayse@ornek.com\nMehmet Demir <mehmet@ornek.com>\nZeynep Kaya, 0532 123 45 67", 'flx-mail' ) . '"></textarea>';
	echo '<small>' . esc_html__( 'One person per line: email, phone, or "Full Name <email>" / "Full Name, email, phone".', 'flx-mail' ) . '</small>';
	if ( $sources ) {
		echo '<div class="flxi-sources">';
		foreach ( $sources as $k => $label ) {
			echo '<label class="flxi-source"><input type="checkbox" name="sources[]" value="' . esc_attr( $k ) . '" data-recipients><span>' . esc_html( $label ) . '</span></label>';
		}
		echo '</div>';
	}
	echo '<p class="flxi-count" data-recipient-count aria-live="polite"></p><ul class="flxi-count__why" data-recipient-why hidden></ul></div>';

	echo '<label class="flxi-field"><span>' . esc_html__( 'Scheduled send (optional)', 'flx-mail' ) . '</span><input type="datetime-local" name="send_at"><small>' . esc_html__( 'Uses the WordPress time zone. Future Netgsm sends can be cancelled before sending starts.', 'flx-mail' ) . ' <span class="flxi-now" data-site-clock data-offset="' . esc_attr( (string) ( (int) wp_date( 'Z' ) ) ) . '" data-now="' . esc_attr( (string) time() ) . '">' . esc_html__( 'Site time now:', 'flx-mail' ) . ' <strong data-site-clock-value>' . esc_html( wp_date( 'd.m.Y H:i' ) ) . '</strong> <span>(' . esc_html( flxi_timezone_label() ) . ')</span></span></small></label>';
	// E-posta alanları
	echo '<div data-for-channel="email">';
	echo '<label class="flxi-field"><span>' . esc_html__( 'Subject', 'flx-mail' ) . '</span><input type="text" name="subject" data-preview data-insert-target></label>';
	echo '<label class="flxi-field"><span>' . esc_html__( 'Heading', 'flx-mail' ) . '</span><input type="text" name="heading" data-preview data-insert-target placeholder="' . esc_attr__( 'Hello {ad}', 'flx-mail' ) . '"></label>';
	echo '<div class="flxi-field"><span>' . esc_html__( 'Content', 'flx-mail' ) . '</span>';
	wp_editor(
		'',
		'flxi_body',
		array(
			'textarea_name' => 'body',
			'textarea_rows' => 10,
			'media_buttons' => true,
			'teeny'         => true,
			'quicktags'     => array( 'buttons' => 'strong,em,link,ul,ol,li,img,close' ),
			'tinymce'       => array( 'toolbar1' => 'bold,italic,link,unlink,bullist,numlist,blockquote,undo,redo' ),
		)
	);
	echo '</div><div class="flxi-grid2">';
	echo '<label class="flxi-field"><span>' . esc_html__( 'Button text', 'flx-mail' ) . '</span><input type="text" name="button_text" data-preview placeholder="' . esc_attr__( 'Donate', 'flx-mail' ) . '"></label>';
	echo '<label class="flxi-field"><span>' . esc_html__( 'Button link', 'flx-mail' ) . '</span><input type="url" name="button_url" data-preview placeholder="' . esc_attr( home_url( '/bagis/' ) ) . '"></label>';
	echo '</div><div class="flxi-grid2"><label class="flxi-field"><span>' . esc_html__( 'From', 'flx-mail' ) . '</span><select name="sender" data-preview>';
	printf( '<option value="info">%s</option><option value="noreply">%s</option>', esc_html( flxi_setting( 'from_email' ) ), esc_html( flxi_setting( 'noreply_email' ) ) );
	echo '</select></label><div class="flxi-field"><span>&nbsp;</span>' . flxi_switch( 'unsubscribe', true, __( 'Add an "unsubscribe" link (recommended for bulk sends)', 'flx-mail' ), 'data-preview' ) . '</div></div>'; // phpcs:ignore
	echo '<div class="flxi-vars"><span>' . esc_html__( 'Variables', 'flx-mail' ) . '</span><div class="flxi-chips">';
	foreach ( array( 'ad' => __( 'First name', 'flx-mail' ), 'ad_soyad' => __( 'Full name', 'flx-mail' ), 'eposta' => __( 'Email', 'flx-mail' ), 'site' => __( 'Site name', 'flx-mail' ) ) as $v => $d ) {
		echo '<button type="button" class="flxi-chip" data-var="{' . esc_attr( $v ) . '}" title="' . esc_attr( $d ) . '">{' . esc_html( $v ) . '}</button>';
	}
	echo '</div></div></div>';

	// WhatsApp / SMS alanları
	echo '<div data-for-channel="whatsapp sms" hidden>';
	echo '<label class="flxi-field"><span>' . esc_html__( 'Message', 'flx-mail' ) . '</span><textarea name="text" rows="6" data-msg-text data-insert-target placeholder="' . esc_attr__( 'Hello {ad} 🌱 …', 'flx-mail' ) . '"></textarea><small>' . esc_html__( 'Variables: {ad}, {ad_soyad}, {telefon}. *Bold* and _italic_ work in WhatsApp.', 'flx-mail' ) . '</small></label>';
	foreach ( $conns as $ch => $list ) {
		if ( count( $list ) > 1 ) {
			echo '<label class="flxi-field" data-for-channel="' . esc_attr( $ch ) . '"><span>' . esc_html__( 'Link', 'flx-mail' ) . '</span><select name="connection_' . esc_attr( $ch ) . '">';
			foreach ( $list as $c ) {
				printf( '<option value="%d"%s>%s</option>', (int) $c['id'], selected( $c['default'], true, false ), esc_html( $c['name'] ) );
			}
			echo '</select></label>';
		}
	}
	echo '</div>';

	echo '<label class="flxi-field"><span>' . esc_html__( 'Campaign name (only visible to you)', 'flx-mail' ) . '</span><input type="text" name="name" placeholder="' . esc_attr__( 'E.g. November sapling planting invitation', 'flx-mail' ) . '"></label>';
	echo '<div class="flxi-actions"><button type="submit" class="button button-primary button-large" data-confirm="' . esc_attr__( 'Start sending?', 'flx-mail' ) . '">' . esc_html__( 'Send', 'flx-mail' ) . '</button> <button type="button" class="button button-large" data-test-free>' . esc_html__( 'Send me a test', 'flx-mail' ) . '</button><span class="flxi-status" aria-live="polite"></span></div>';
	echo '</div></div>';

	// WhatsApp / SMS önizlemesi: kanalın kendi sınırlarıyla (uzunluk, SMS parçası, biçim).
	echo '<div class="flxi-editor__preview" data-for-channel="whatsapp sms" hidden><div class="flxi-msgpv" data-msg-preview data-boot="' . esc_attr( wp_json_encode( flxi_message_preview_boot() ) ) . '">';
	echo '<div class="flxi-msgpv__head"><span class="flxi-msgpv__avatar" aria-hidden="true"></span><div><strong data-msg-title></strong><small data-msg-sub></small></div></div>';
	echo '<div class="flxi-msgpv__chat" data-msg-chat></div>';
	echo '<div class="flxi-msgpv__meter"><div class="flxi-msgpv__stat" data-msg-stat aria-live="polite"></div><div class="flxi-bar"><span data-msg-bar style="width:0"></span></div><ul class="flxi-msgpv__notes" data-msg-notes></ul></div>';
	echo '</div></div>';

	echo '<div class="flxi-editor__preview" data-for-channel="email"><div class="flxi-preview"><div class="flxi-preview__bar"><div class="flxi-preview__subject"><span class="flxi-muted">' . esc_html__( 'Subject:', 'flx-mail' ) . '</span> <strong data-preview-subject></strong></div>';
	echo '<div class="flxi-seg" role="group" aria-label="' . esc_attr__( 'Preview size', 'flx-mail' ) . '"><button type="button" class="is-active" data-device="desktop" aria-pressed="true"><span class="dashicons dashicons-desktop"></span><span class="screen-reader-text">' . esc_html__( 'Desktop', 'flx-mail' ) . '</span></button><button type="button" data-device="mobile" aria-pressed="false"><span class="dashicons dashicons-smartphone"></span><span class="screen-reader-text">' . esc_html__( 'Phone', 'flx-mail' ) . '</span></button><button type="button" data-device="dark" aria-pressed="false"><span class="dashicons dashicons-admin-appearance"></span><span class="screen-reader-text">' . esc_html__( 'Dark mode', 'flx-mail' ) . '</span></button></div></div>';
	echo '<div class="flxi-preview__frame"><iframe title="' . esc_attr__( 'Email preview', 'flx-mail' ) . '" data-preview-frame sandbox="allow-same-origin"></iframe></div></div></div>';
	echo '</form>';

	// Geçmiş
	$rows = $wpdb->get_results( 'SELECT * FROM ' . flxi_table( 'campaigns' ) . ' ORDER BY id DESC LIMIT 20', ARRAY_A ); // phpcs:ignore
	if ( $rows ) {
		$st = array(
			'queued'    => __( 'Queued', 'flx-mail' ),
			'running'   => __( 'Sending', 'flx-mail' ),
			'done'      => __( 'Done', 'flx-mail' ),
			'cancelled' => __( 'Stopped', 'flx-mail' ),
			'reporting' => __( 'Awaiting delivery reports', 'flx-mail' ),
			'review' => __( 'Needs review', 'flx-mail' ),
		);
		echo '<div class="flxi-card"><h2 class="flxi-card__title">' . esc_html__( 'Recent sends', 'flx-mail' ) . '</h2><div class="flxi-tablewrap"><table class="flxi-table"><thead><tr><th>' . esc_html__( 'First name', 'flx-mail' ) . '</th><th>' . esc_html__( 'Channel', 'flx-mail' ) . '</th><th>' . esc_html__( 'Status', 'flx-mail' ) . '</th><th class="num">' . esc_html__( 'Sent', 'flx-mail' ) . '</th><th class="num">' . esc_html__( 'Failed', 'flx-mail' ) . '</th><th>' . esc_html__( 'Date', 'flx-mail' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><td><a href="' . esc_url( flxi_admin_url( 'flxi-kuyruk', array( 'campaign' => $r['id'] ) ) ) . '">' . esc_html( $r['name'] ) . '</a></td><td>' . esc_html( flxi_channels()[ $r['channel'] ] ?? $r['channel'] ) . '</td><td>' . esc_html( $st[ $r['status'] ] ?? $r['status'] ) . '</td><td class="num">' . esc_html( number_format_i18n( $r['sent'] ) . ' / ' . number_format_i18n( $r['total'] ) ) . '</td><td class="num">' . esc_html( number_format_i18n( $r['failed'] ) ) . '</td><td>' . esc_html( flx_date( $r['created_at'], 'datetime' ) ) . '</td></tr>';
		}
		echo '</tbody></table></div></div>';
	}
	flxi_page_foot();
}

/** Saat dilimi etiketi: "Europe/Istanbul, UTC+3" ya da yalnız "UTC+0". */
function flxi_timezone_label() {
	$off  = (int) wp_date( 'Z' );
	$sign = $off < 0 ? '-' : '+';
	$h    = (int) floor( abs( $off ) / 3600 );
	$m    = (int) ( ( abs( $off ) % 3600 ) / 60 );
	$utc  = 'UTC' . $sign . $h . ( $m ? ':' . str_pad( (string) $m, 2, '0', STR_PAD_LEFT ) : '' );
	$name = (string) get_option( 'timezone_string' );
	return $name ? $name . ', ' . $utc : $utc;
}

/**
 * WhatsApp / SMS önizlemesinin bildiği kurallar: bağlantının türü, Netgsm başlığı, Türkçe karakter ayarı,
 * İYS filtresi, WhatsApp hesabının durumu. Gizli değer (parola, token) yer almaz.
 */
function flxi_message_preview_boot() {
	$out = array(
		'site'     => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		'whatsapp' => array(),
		'sms'      => array(),
	);
	foreach ( array( 'whatsapp', 'sms' ) as $ch ) {
		foreach ( flxi_list_connections( $ch, true ) as $c ) {
			$cfg = (array) ( $c['config'] ?? array() );
			$row = array(
				'id'   => (int) $c['id'],
				'name' => (string) $c['name'],
				'type' => (string) $c['type'],
			);
			if ( 'sms' === $ch ) {
				$row['header']   = (string) ( $cfg['header'] ?? '' );
				$row['encoding'] = array_key_exists( 'encoding', $cfg ) ? (string) $cfg['encoding'] : 'TR';
				$row['iys']      = (string) ( $cfg['iysfilter'] ?? '0' );
			} elseif ( function_exists( 'flxi_wa_cached_state' ) ) {
				$st           = flxi_wa_cached_state( $c['id'] );
				$row['state'] = (string) $st['state'];
				$row['me']    = (string) $st['me'];
				$row['title'] = (string) $st['name'];
			}
			$out[ $ch ][] = $row;
		}
	}
	return $out;
}

add_action(
	'admin_post_flxi_create_campaign',
	function () {
		flxi_check( 'flxi_create_campaign' );
		$in      = wp_unslash( $_POST ); // phpcs:ignore
		$channel = sanitize_key( $in['channel'] ?? 'email' );
		$list    = flxi_collect_recipients( array_map( 'sanitize_text_field', (array) ( $in['sources'] ?? array() ) ), (string) ( $in['manual'] ?? '' ), $channel );
		$id      = flxi_create_campaign(
			array(
				'name'          => $in['name'] ?? '',
				'send_at'       => sanitize_text_field( $in['send_at'] ?? '' ),
				'channel'       => $channel,
				'subject'       => $in['subject'] ?? '',
				'heading'       => $in['heading'] ?? '',
				'body'          => $in['body'] ?? '',
				'button_text'   => $in['button_text'] ?? '',
				'button_url'    => $in['button_url'] ?? '',
				'sender'        => $in['sender'] ?? 'info',
				'text'          => $in['text'] ?? '',
				'connection_id' => (int) ( $in[ 'connection_' . $channel ] ?? 0 ),
				'unsubscribe'   => ! empty( $in['unsubscribe'] ) && count( $list ) > 1,
			),
			$list
		);
		if ( is_wp_error( $id ) ) {
			flxi_redirect( flxi_admin_url( 'flxi-gonder' ), '', $id->get_error_message() );
		}
		// Tek alıcı: hemen gönder.
		if ( 1 === count( $list ) ) {
			flxi_run_queue( 15 );
		}
		/* translators: %s: alıcı sayısı */
		flxi_redirect( flxi_admin_url( 'flxi-kuyruk', array( 'campaign' => $id ) ), sprintf( __( 'Sending started for %s recipients.', 'flx-mail' ), number_format_i18n( count( $list ) ) ) );
	}
);

add_action(
	'admin_post_flxi_cancel_campaign',
	function () {
		flxi_check( 'flxi_cancel_campaign' );
		$id = (int) ( $_POST['id'] ?? 0 );
		flxi_cancel_campaign( $id );
		flxi_redirect( flxi_admin_url( 'flxi-kuyruk', array( 'campaign' => $id ) ), __( 'Sending stopped.', 'flx-mail' ) );
	}
);
