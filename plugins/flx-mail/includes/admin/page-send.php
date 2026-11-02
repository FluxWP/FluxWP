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
	flxi_page_head( __( 'Gönder', 'flx-mail' ), __( 'Bir kişiye ya da bir listeye kurumsal şablonla e-posta, WhatsApp veya SMS gönderin. Toplu gönderim arka planda sırayla gider.', 'flx-mail' ) );

	if ( $cid ) {
		$c = flxi_get_campaign( $cid );
		if ( $c ) {
			$open = in_array( $c['status'], array( 'queued', 'running' ), true );
			echo '<div class="flxi-card flxi-progress" data-campaign="' . (int) $cid . '" data-open="' . ( $open ? '1' : '0' ) . '">';
			echo '<h2 class="flxi-card__title">' . esc_html( $c['name'] ) . ' ' . flxi_badge( flxi_channels()[ $c['channel'] ] ?? $c['channel'] ) . '</h2>'; // phpcs:ignore
			$pct = $c['total'] ? round( 100 * ( $c['sent'] + $c['failed'] + $c['skipped'] ) / $c['total'] ) : 0;
			echo '<div class="flxi-bar"><span style="width:' . (int) $pct . '%"></span></div>';
			/* translators: 1: gönderilen, 2: toplam, 3: hatalı */
			echo '<p data-progress-text>' . esc_html( sprintf( __( '%1$s / %2$s gönderildi · %3$s hatalı', 'flx-mail' ), number_format_i18n( $c['sent'] ), number_format_i18n( $c['total'] ), number_format_i18n( $c['failed'] ) ) ) . '</p>';
			if ( $open ) {
				$paused = (int) get_option( 'flxi_quota_paused' );
				$ptext  = '';
				if ( 'email' === $c['channel'] && $paused > time() ) {
					/* translators: %s: time */
					$ptext = sprintf( __( 'Posta kutusunun günlük gönderim sınırı doldu. Gönderim %s civarında kendiliğinden devam edecek.', 'flx-mail' ), wp_date( get_option( 'time_format' ) . ', j M', $paused ) );
				}
				echo '<p class="flxi-notice-inline" data-quota-paused' . ( $ptext ? '' : ' hidden' ) . '>' . esc_html( $ptext ) . '</p>';
				echo '<p class="flxi-muted">' . esc_html__( 'Bu sayfa açıkken gönderim hızlanır; kapatırsanız arka planda devam eder.', 'flx-mail' ) . '</p>';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				wp_nonce_field( 'flxi_cancel_campaign' );
				echo '<input type="hidden" name="action" value="flxi_cancel_campaign"><input type="hidden" name="id" value="' . (int) $cid . '"><button class="button">' . esc_html__( 'Durdur', 'flx-mail' ) . '</button></form>';
			}
			echo ' <a class="button" href="' . esc_url( flxi_admin_url( 'flxi-kayitlar', array( 'campaign' => $cid ) ) ) . '">' . esc_html__( 'Kayıtları gör', 'flx-mail' ) . '</a></div>';
		}
	}

	if ( flxi_limits()['daily'] ) {
		echo '<details class="flxi-card flxi-quota-card"><summary class="flxi-card__title">' . esc_html__( 'Günlük e-posta hakkı', 'flx-mail' ) . '</summary>';
		echo flxi_quota_meter_html(); // phpcs:ignore
		echo '<a href="' . esc_url( flxi_admin_url( 'flxi-ayarlar' ) . '#sinirlar' ) . '">' . esc_html__( 'Posta kutusu sınırları', 'flx-mail' ) . '</a></details>';
	}

	$sources = flxi_recipient_sources();
	$conns   = array( 'whatsapp' => flxi_connection_choices( 'whatsapp' ), 'sms' => flxi_connection_choices( 'sms' ) );

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxi-editor" id="flxi-send" data-key="">';
	wp_nonce_field( 'flxi_create_campaign' );
	echo '<input type="hidden" name="action" value="flxi_create_campaign">';
	echo '<div class="flxi-editor__form"><div class="flxi-card">';

	echo '<fieldset class="flxi-field"><legend>' . esc_html__( 'Kanal', 'flx-mail' ) . '</legend><div class="flxi-seg flxi-seg--wide" data-channel-seg>';
	foreach ( flxi_channels() as $ch => $label ) {
		$dis = 'email' !== $ch && ! $conns[ $ch ];
		printf( '<label class="%s"><input type="radio" name="channel" value="%s"%s%s> %s%s</label>', $dis ? 'is-disabled' : '', esc_attr( $ch ), checked( $ch, 'email', false ), disabled( $dis, true, false ), esc_html( $label ), $dis ? ' <small>(' . esc_html__( 'bağlantı yok', 'flx-mail' ) . ')</small>' : '' );
	}
	echo '</div></fieldset>';

	// Alıcılar
	echo '<div class="flxi-field"><span>' . esc_html__( 'Alıcılar', 'flx-mail' ) . '</span>';
	echo '<textarea name="manual" rows="4" data-recipients placeholder="' . esc_attr__( "ayse@ornek.com\nMehmet Demir <mehmet@ornek.com>\nZeynep Kaya, 0532 123 45 67", 'flx-mail' ) . '"></textarea>';
	echo '<small>' . esc_html__( 'Her satıra bir kişi: e-posta, telefon ya da "Ad Soyad <e-posta>" / "Ad Soyad, e-posta, telefon".', 'flx-mail' ) . '</small>';
	if ( $sources ) {
		echo '<div class="flxi-checks">';
		foreach ( $sources as $k => $label ) {
			echo '<label><input type="checkbox" name="sources[]" value="' . esc_attr( $k ) . '" data-recipients> ' . esc_html( $label ) . '</label>';
		}
		echo '</div>';
	}
	echo '<p class="flxi-count" data-recipient-count aria-live="polite"></p></div>';

	// E-posta alanları
	echo '<div data-for-channel="email">';
	echo '<label class="flxi-field"><span>' . esc_html__( 'Konu', 'flx-mail' ) . '</span><input type="text" name="subject" data-preview data-insert-target></label>';
	echo '<label class="flxi-field"><span>' . esc_html__( 'Başlık', 'flx-mail' ) . '</span><input type="text" name="heading" data-preview data-insert-target placeholder="' . esc_attr__( 'Merhaba {ad}', 'flx-mail' ) . '"></label>';
	echo '<div class="flxi-field"><span>' . esc_html__( 'İçerik', 'flx-mail' ) . '</span>';
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
	echo '<label class="flxi-field"><span>' . esc_html__( 'Düğme yazısı', 'flx-mail' ) . '</span><input type="text" name="button_text" data-preview placeholder="' . esc_attr__( 'Bağış yap', 'flx-mail' ) . '"></label>';
	echo '<label class="flxi-field"><span>' . esc_html__( 'Düğme bağlantısı', 'flx-mail' ) . '</span><input type="url" name="button_url" data-preview placeholder="' . esc_attr( home_url( '/bagis/' ) ) . '"></label>';
	echo '</div><div class="flxi-grid2"><label class="flxi-field"><span>' . esc_html__( 'Kimden', 'flx-mail' ) . '</span><select name="sender" data-preview>';
	printf( '<option value="info">%s</option><option value="noreply">%s</option>', esc_html( flxi_setting( 'from_email' ) ), esc_html( flxi_setting( 'noreply_email' ) ) );
	echo '</select></label><div class="flxi-field"><span>&nbsp;</span>' . flxi_switch( 'unsubscribe', true, __( '"Listeden çık" bağlantısı ekle (toplu gönderimde önerilir)', 'flx-mail' ), 'data-preview' ) . '</div></div>'; // phpcs:ignore
	echo '<div class="flxi-vars"><span>' . esc_html__( 'Değişkenler', 'flx-mail' ) . '</span><div class="flxi-chips">';
	foreach ( array( 'ad' => __( 'Ad', 'flx-mail' ), 'ad_soyad' => __( 'Ad soyad', 'flx-mail' ), 'eposta' => __( 'E-posta', 'flx-mail' ), 'site' => __( 'Site adı', 'flx-mail' ) ) as $v => $d ) {
		echo '<button type="button" class="flxi-chip" data-var="{' . esc_attr( $v ) . '}" title="' . esc_attr( $d ) . '">{' . esc_html( $v ) . '}</button>';
	}
	echo '</div></div></div>';

	// WhatsApp / SMS alanları
	echo '<div data-for-channel="whatsapp sms" hidden>';
	echo '<label class="flxi-field"><span>' . esc_html__( 'Mesaj', 'flx-mail' ) . '</span><textarea name="text" rows="6" data-insert-target placeholder="' . esc_attr__( 'Merhaba {ad} 🌱 …', 'flx-mail' ) . '"></textarea><small>' . esc_html__( 'Değişkenler: {ad}, {ad_soyad}, {telefon}. WhatsApp\'ta *kalın* ve _italik_ çalışır.', 'flx-mail' ) . '</small></label>';
	foreach ( $conns as $ch => $list ) {
		if ( count( $list ) > 1 ) {
			echo '<label class="flxi-field" data-for-channel="' . esc_attr( $ch ) . '"><span>' . esc_html__( 'Bağlantı', 'flx-mail' ) . '</span><select name="connection_' . esc_attr( $ch ) . '">';
			foreach ( $list as $c ) {
				printf( '<option value="%d"%s>%s</option>', (int) $c['id'], selected( $c['default'], true, false ), esc_html( $c['name'] ) );
			}
			echo '</select></label>';
		}
	}
	echo '</div>';

	echo '<label class="flxi-field"><span>' . esc_html__( 'Gönderim adı (yalnız sizin için)', 'flx-mail' ) . '</span><input type="text" name="name" placeholder="' . esc_attr__( 'Ör. Kasım fidan dikimi daveti', 'flx-mail' ) . '"></label>';
	echo '<div class="flxi-actions"><button type="submit" class="button button-primary button-large" data-confirm="' . esc_attr__( 'Gönderim başlasın mı?', 'flx-mail' ) . '">' . esc_html__( 'Gönder', 'flx-mail' ) . '</button> <button type="button" class="button button-large" data-test-free>' . esc_html__( 'Bana test gönder', 'flx-mail' ) . '</button><span class="flxi-status" aria-live="polite"></span></div>';
	echo '</div></div>';

	echo '<div class="flxi-editor__preview" data-for-channel="email"><div class="flxi-preview"><div class="flxi-preview__bar"><div class="flxi-preview__subject"><span class="flxi-muted">' . esc_html__( 'Konu:', 'flx-mail' ) . '</span> <strong data-preview-subject></strong></div>';
	echo '<div class="flxi-seg" role="group" aria-label="' . esc_attr__( 'Önizleme boyutu', 'flx-mail' ) . '"><button type="button" class="is-active" data-device="desktop" aria-pressed="true"><span class="dashicons dashicons-desktop"></span><span class="screen-reader-text">' . esc_html__( 'Masaüstü', 'flx-mail' ) . '</span></button><button type="button" data-device="mobile" aria-pressed="false"><span class="dashicons dashicons-smartphone"></span><span class="screen-reader-text">' . esc_html__( 'Telefon', 'flx-mail' ) . '</span></button><button type="button" data-device="dark" aria-pressed="false"><span class="dashicons dashicons-admin-appearance"></span><span class="screen-reader-text">' . esc_html__( 'Koyu mod', 'flx-mail' ) . '</span></button></div></div>';
	echo '<div class="flxi-preview__frame"><iframe title="' . esc_attr__( 'E-posta önizlemesi', 'flx-mail' ) . '" data-preview-frame sandbox="allow-same-origin"></iframe></div></div></div>';
	echo '</form>';

	// Geçmiş
	$rows = $wpdb->get_results( 'SELECT * FROM ' . flxi_table( 'campaigns' ) . ' ORDER BY id DESC LIMIT 20', ARRAY_A ); // phpcs:ignore
	if ( $rows ) {
		$st = array(
			'queued'    => __( 'Sırada', 'flx-mail' ),
			'running'   => __( 'Gönderiliyor', 'flx-mail' ),
			'done'      => __( 'Bitti', 'flx-mail' ),
			'cancelled' => __( 'Durduruldu', 'flx-mail' ),
		);
		echo '<div class="flxi-card"><h2 class="flxi-card__title">' . esc_html__( 'Son gönderimler', 'flx-mail' ) . '</h2><div class="flxi-tablewrap"><table class="flxi-table"><thead><tr><th>' . esc_html__( 'Ad', 'flx-mail' ) . '</th><th>' . esc_html__( 'Kanal', 'flx-mail' ) . '</th><th>' . esc_html__( 'Durum', 'flx-mail' ) . '</th><th class="num">' . esc_html__( 'Gönderilen', 'flx-mail' ) . '</th><th class="num">' . esc_html__( 'Hatalı', 'flx-mail' ) . '</th><th>' . esc_html__( 'Tarih', 'flx-mail' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><td><a href="' . esc_url( flxi_admin_url( 'flxi-gonder', array( 'campaign' => $r['id'] ) ) ) . '">' . esc_html( $r['name'] ) . '</a></td><td>' . esc_html( flxi_channels()[ $r['channel'] ] ?? $r['channel'] ) . '</td><td>' . esc_html( $st[ $r['status'] ] ?? $r['status'] ) . '</td><td class="num">' . esc_html( number_format_i18n( $r['sent'] ) . ' / ' . number_format_i18n( $r['total'] ) ) . '</td><td class="num">' . esc_html( number_format_i18n( $r['failed'] ) ) . '</td><td>' . esc_html( get_date_from_gmt( $r['created_at'], get_option( 'date_format' ) . ' H:i' ) ) . '</td></tr>';
		}
		echo '</tbody></table></div></div>';
	}
	flxi_page_foot();
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
		flxi_redirect( flxi_admin_url( 'flxi-gonder', array( 'campaign' => $id ) ), sprintf( __( '%s alıcı için gönderim başladı.', 'flx-mail' ), number_format_i18n( count( $list ) ) ) );
	}
);

add_action(
	'admin_post_flxi_cancel_campaign',
	function () {
		flxi_check( 'flxi_cancel_campaign' );
		$id = (int) ( $_POST['id'] ?? 0 );
		flxi_cancel_campaign( $id );
		flxi_redirect( flxi_admin_url( 'flxi-gonder', array( 'campaign' => $id ) ), __( 'Gönderim durduruldu.', 'flx-mail' ) );
	}
);
