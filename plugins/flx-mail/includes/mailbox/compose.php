<?php
/**
 * Posta kutusundan yazma: cevap/iletme taslağı, gönderim (bilgi@), Gönderilmiş'e ekleme.
 *
 * Gönderim normal wp_mail hattından geçer: günlük hak, kara liste, kayıt, görsel gömme aynen uygulanır.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/** "Re: " / "Fwd: " ön eki (zaten varsa eklenmez). */
function flxi_mailbox_subject_prefix( $subject, $prefix ) {
	$subject = trim( (string) $subject );
	$re      = 'Re' === $prefix ? '/^(re|ynt|cvp|yanıt)\s*:/iu' : '/^(fwd?|ilt|ileti)\s*:/iu';
	return preg_match( $re, $subject ) ? $subject : $prefix . ': ' . $subject;
}

/** Yanıtta alıntılanacak eski e-posta (düz metinden; dış HTML/CSS taşınmaz). */
function flxi_mailbox_quote_html( array $m, $forward = false ) {
	$text = trim( (string) $m['body_text'] );
	if ( '' === $text ) {
		$html = (string) $m['body_html'];
		if ( '' === $html && $m['log_id'] ) {
			global $wpdb;
			$html = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT body FROM ' . flxi_table( 'log' ) . ' WHERE id = %d', (int) $m['log_id'] ) ); // phpcs:ignore
		}
		$text = flxi_html_to_text( $html );
	}
	// Önceki alıntılar dahil çok uzun yazışmayı kısalt.
	$text = mb_substr( $text, 0, 8000 );
	$from = $m['from_name'] ? $m['from_name'] . ' <' . $m['from_email'] . '>' : $m['from_email'];
	$when = get_date_from_gmt( $m['mail_date'], 'd.m.Y H:i' );
	if ( $forward ) {
		$head = '<p>---------- ' . esc_html__( 'İletilen e-posta', 'flx-mail' ) . ' ----------<br>'
			. esc_html__( 'Kimden', 'flx-mail' ) . ': ' . esc_html( $from ) . '<br>'
			. esc_html__( 'Tarih', 'flx-mail' ) . ': ' . esc_html( $when ) . '<br>'
			. esc_html__( 'Konu', 'flx-mail' ) . ': ' . esc_html( $m['subject'] ) . '<br>'
			. esc_html__( 'Kime', 'flx-mail' ) . ': ' . esc_html( $m['to_list'] ) . '</p>';
	} else {
		/* translators: 1: tarih, 2: gönderen */
		$head = '<p>' . esc_html( sprintf( __( '%1$s tarihinde %2$s şunu yazdı:', 'flx-mail' ), $when, $from ) ) . '</p>';
	}
	return $head . flxi_text_to_html( $text );
}

/**
 * Bir e-postaya cevap/iletme taslağı açar.
 *
 * @param string $mode reply|replyall|forward
 * @return int taslak id
 */
function flxi_mailbox_start_reply( $mail_id, $mode = 'reply', array $extra = array() ) {
	$m = flxi_mailbox_get( $mail_id );
	if ( ! $m ) {
		return 0;
	}
	$me   = array_map( 'strtolower', array_filter( array( flxi_setting( 'from_email' ), flxi_setting( 'noreply_email' ), flxi_imap_creds()['user'] ) ) );
	$to   = array();
	$cc   = array();
	$back = 'sent' === $m['folder'] ? flxi_mime_addresses( $m['to_list'] ) : flxi_mime_addresses( $m['reply_to'] ? $m['reply_to'] : $m['from_email'] );
	if ( 'forward' !== $mode ) {
		$to = $back;
		if ( 'replyall' === $mode ) {
			foreach ( array_merge( 'sent' === $m['folder'] ? array() : flxi_mime_addresses( $m['to_list'] ), flxi_mime_addresses( $m['cc_list'] ) ) as $a ) {
				if ( ! in_array( $a['email'], $me, true ) && ! in_array( $a['email'], wp_list_pluck( $to, 'email' ), true ) ) {
					$cc[] = $a;
				}
			}
		}
	}
	return flxi_draft_save(
		array_merge(
			array(
				'reply_to_id' => (int) $m['id'],
				'mode'        => $mode,
				'to_list'     => flxi_mime_addr_text( $to ),
				'cc_list'     => flxi_mime_addr_text( $cc ),
				'subject'     => flxi_mailbox_subject_prefix( $m['subject'], 'forward' === $mode ? 'Fwd' : 'Re' ),
				'body'        => '',
				'layout'      => 'sade',
				'status'      => 'draft',
				'origin'      => 'user',
			),
			$extra
		)
	);
}

/** Taslağın gönderilecek HTML'i (önizleme ve gönderim aynı). */
function flxi_draft_html( array $d ) {
	$quote = '';
	if ( ! empty( $d['reply_to_id'] ) ) {
		$m = flxi_mailbox_get( $d['reply_to_id'] );
		if ( $m ) {
			$quote = flxi_mailbox_quote_html( $m, 'forward' === ( $d['mode'] ?? '' ) );
		}
	}
	return flxi_mailbox_render(
		array(
			'subject' => $d['subject'] ?? '',
			'body'    => $d['body'] ?? '',
			'layout'  => $d['layout'] ?? 'kurumsal',
			'quote'   => $quote,
		)
	);
}

/* Gönderilen e-postanın Message-ID'si bizden: yazışma ve Gönderilmiş klasörü eşleşmesi için. */
add_action(
	'phpmailer_init',
	function ( $mailer ) {
		if ( ! empty( $GLOBALS['flxi_force_msgid'] ) ) {
			$mailer->MessageID = '<' . $GLOBALS['flxi_force_msgid'] . '>';
		}
	},
	5
);

/**
 * Taslağı gönderir.
 *
 * @return array ok, error, mail_id
 */
function flxi_draft_send( $draft_id ) {
	$d = flxi_draft_get( $draft_id );
	if ( ! $d ) {
		return array( 'ok' => false, 'error' => __( 'Taslak bulunamadı.', 'flx-mail' ) );
	}
	if ( 'sent' === $d['status'] ) {
		return array( 'ok' => false, 'error' => __( 'Bu taslak zaten gönderildi.', 'flx-mail' ) );
	}
	$to = flxi_mime_addresses( $d['to_list'] );
	$cc = flxi_mime_addresses( $d['cc_list'] );
	if ( ! $to ) {
		return array( 'ok' => false, 'error' => __( 'Alıcı (Kime) yok ya da adres geçersiz.', 'flx-mail' ) );
	}
	if ( '' === trim( (string) $d['subject'] ) ) {
		return array( 'ok' => false, 'error' => __( 'Konu boş.', 'flx-mail' ) );
	}
	$limits = flxi_limits();
	if ( $limits['recipients'] && count( $to ) + count( $cc ) > $limits['recipients'] ) {
		/* translators: %s: sayı */
		return array( 'ok' => false, 'error' => sprintf( __( 'Bir e-postada en çok %s alıcı olabilir.', 'flx-mail' ), number_format_i18n( $limits['recipients'] ) ) );
	}

	$orig    = $d['reply_to_id'] ? flxi_mailbox_get( $d['reply_to_id'] ) : null;
	$html    = flxi_draft_html( $d );
	$domain  = wp_parse_url( home_url(), PHP_URL_HOST );
	$msgid   = 'flxi.' . gmdate( 'YmdHis' ) . '.' . substr( md5( uniqid( '', true ) ), 0, 12 ) . '@' . ( $domain ? $domain : 'localhost' );
	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		'X-FLXI-Rendered: 1',
		'X-FLXI-Sender: info',
		'X-FLXI-Template: flx-mail/mailbox',
	);
	foreach ( $cc as $a ) {
		$headers[] = 'Cc: ' . ( $a['name'] ? '"' . str_replace( '"', '', $a['name'] ) . '" <' . $a['email'] . '>' : $a['email'] );
	}
	$refs = '';
	if ( $orig && 'forward' !== $d['mode'] && $orig['message_id'] && 0 !== strpos( $orig['message_id'], 'imap-' ) ) {
		$chain     = trim( (string) $orig['refs'] );
		$refs      = trim( $chain . ' <' . $orig['message_id'] . '>' );
		$headers[] = 'In-Reply-To: <' . $orig['message_id'] . '>';
		$headers[] = 'References: ' . $refs;
	}
	$recipients = array_map(
		function ( $a ) {
			return $a['name'] ? '"' . str_replace( '"', '', $a['name'] ) . '" <' . $a['email'] . '>' : $a['email'];
		},
		$to
	);

	$GLOBALS['flxi_force_msgid'] = $msgid;
	$GLOBALS['flxi_last_block']  = '';
	$GLOBALS['flxi_retry_ok']    = null;
	$ok                         = wp_mail( $recipients, (string) $d['subject'], $html, $headers );
	$GLOBALS['flxi_force_msgid'] = '';
	$block                      = $GLOBALS['flxi_last_block'] ?? '';
	$raw                        = '';
	if ( $ok && ! $block && isset( $GLOBALS['phpmailer'] ) && is_object( $GLOBALS['phpmailer'] ) && method_exists( $GLOBALS['phpmailer'], 'getSentMIMEMessage' ) ) {
		$raw = (string) $GLOBALS['phpmailer']->getSentMIMEMessage();
	}
	if ( $block ) {
		$err = 'blocklist' === $block ? __( 'Alıcı kara listede: e-posta gönderilmedi.', 'flx-mail' ) : __( 'E-posta gönderilmedi (şablon kapalı).', 'flx-mail' );
		flxi_draft_save( array( 'status' => 'failed', 'error' => $err ), $draft_id );
		return array( 'ok' => false, 'error' => $err );
	}
	if ( ! $ok ) {
		$err = isset( $GLOBALS['phpmailer'] ) && is_object( $GLOBALS['phpmailer'] ) && ! empty( $GLOBALS['phpmailer']->ErrorInfo ) ? $GLOBALS['phpmailer']->ErrorInfo : __( 'Gönderilemedi. Ayrıntı Kayıtlar ekranında.', 'flx-mail' );
		flxi_draft_save( array( 'status' => 'failed', 'error' => $err ), $draft_id );
		return array( 'ok' => false, 'error' => $err );
	}

	$from    = flxi_sender_address( 'info' );
	$mail_id = flxi_mailbox_insert(
		array(
			'folder'      => 'sent',
			'source'      => 'center',
			'message_id'  => strtolower( $msgid ),
			'in_reply_to' => $orig && 'forward' !== $d['mode'] ? $orig['message_id'] : '',
			'refs'        => $refs,
			'thread_key'  => $orig && 'forward' !== $d['mode'] && $orig['thread_key'] ? $orig['thread_key'] : '',
			'from_email'  => (string) $from[0],
			'from_name'   => (string) ( $from[1] ?? '' ),
			'to_list'     => $d['to_list'],
			'cc_list'     => $d['cc_list'],
			'subject'     => $d['subject'],
			'snippet'     => flxi_mime_snippet( wp_strip_all_tags( (string) $d['body'] ), '' ),
			'body_text'   => flxi_html_to_text( (string) $d['body'] ),
			'body_html'   => $html,
			'is_read'     => 1,
			'template'    => (string) $d['layout'],
			'user_id'     => get_current_user_id(),
		)
	);
	if ( $orig && 'forward' !== $d['mode'] ) {
		flxi_mailbox_mark( $orig['id'], 'replied', 1 );
	}
	if ( $raw && flxi_mailbox_enabled() ) {
		flxi_mailbox_pending_add( 'append', array( 'raw' => $raw ) );
	}
	flxi_draft_save(
		array(
			'status'       => 'sent',
			'error'        => '',
			'sent_mail_id' => $mail_id,
			'sent_at'      => flxi_now(),
		),
		$draft_id
	);
	do_action( 'flxi_mailbox_sent', $mail_id, $draft_id );
	return array( 'ok' => true, 'error' => '', 'mail_id' => $mail_id );
}

/**
 * Başka eklentiden (ör. Flux Tasks belgesi) posta kutusunda yeni taslak açar. Gönderilmez; alıcıyı kişi seçip gönderir.
 *
 * @param array $a { subject, body (HTML), to, cc, layout (kurumsal|sade|alt şablon), note }
 * @return array|WP_Error array( 'id' => int, 'url' => taslağın posta kutusundaki adresi )
 */
function flxi_mailbox_new_draft( array $a ) {
	if ( ! flxi_can_manage() ) {
		return new WP_Error( 'flxi_cap', __( 'Yetkiniz yok.', 'flx-mail' ) );
	}
	$body = (string) ( $a['body'] ?? '' );
	if ( '' === trim( wp_strip_all_tags( $body ) ) && '' === trim( (string) ( $a['subject'] ?? '' ) ) ) {
		return new WP_Error( 'flxi_empty', __( 'Taslak boş.', 'flx-mail' ) );
	}
	$id = flxi_draft_save(
		array(
			'mode'    => 'new',
			'status'  => 'draft',
			'origin'  => 'user',
			'subject' => (string) ( $a['subject'] ?? '' ),
			'body'    => $body,
			'to_list' => (string) ( $a['to'] ?? '' ),
			'cc_list' => (string) ( $a['cc'] ?? '' ),
			'layout'  => (string) ( $a['layout'] ?? 'kurumsal' ),
			'note'    => sanitize_text_field( (string) ( $a['note'] ?? '' ) ),
		)
	);
	if ( ! $id ) {
		return new WP_Error( 'flxi_db', __( 'İşlem yapılamadı.', 'flx-mail' ) );
	}
	return array( 'id' => $id, 'url' => flxi_admin_url( 'flxi-posta', array( 'taslak' => $id ) ) );
}
