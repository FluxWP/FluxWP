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
			'inbox'        => __( 'Gelen kutusu', 'flx-mail' ),
			'review'       => __( 'Onay bekleyen', 'flx-mail' ),
			'drafts'       => __( 'Taslaklar', 'flx-mail' ),
			'sent'         => __( 'Gönderilen', 'flx-mail' ),
			'layouts'      => __( 'Şablonlar', 'flx-mail' ),
			'newMail'      => __( 'Yeni e-posta', 'flx-mail' ),
			'search'       => __( 'Ara: kişi, konu…', 'flx-mail' ),
			'system'       => __( 'Sistem e-postaları', 'flx-mail' ),
			'unreadOnly'   => __( 'Okunmamış', 'flx-mail' ),
			'more'         => __( 'Daha fazla', 'flx-mail' ),
			'empty'        => __( 'Burada e-posta yok.', 'flx-mail' ),
			'emptyDrafts'  => __( 'Taslak yok.', 'flx-mail' ),
			'pick'         => __( 'Okumak için soldan bir e-posta seçin.', 'flx-mail' ),
			'sync'         => __( 'Eşitle', 'flx-mail' ),
			'syncing'      => __( 'Eşitleniyor…', 'flx-mail' ),
			/* translators: %s: süre */
			'lastSync'     => __( 'Son eşitleme: %s', 'flx-mail' ),
			'syncMore'     => __( 'Eski e-postalar alınıyor…', 'flx-mail' ),
			'off'          => __( 'Posta kutusu kapalı ya da IMAP bilgisi eksik. Gelen e-postaları görmek için Ayarlar › Posta kutusu\'nu doldurun. Taslak yazıp göndermek yine çalışır.', 'flx-mail' ),
			'openSettings' => __( 'Ayarları aç', 'flx-mail' ),
			'reply'        => __( 'Cevapla', 'flx-mail' ),
			'replyAll'     => __( 'Tümünü cevapla', 'flx-mail' ),
			'forward'      => __( 'İlet', 'flx-mail' ),
			'aiReply'      => __( 'AI ile cevap yaz', 'flx-mail' ),
			'aiWrite'      => __( 'AI ile yaz', 'flx-mail' ),
			'aiFix'        => __( 'AI ile düzelt', 'flx-mail' ),
			'aiHint'       => __( 'İsteğe bağlı talimat: "teşekkür et, 11 Kasım etkinliğine davet et"', 'flx-mail' ),
			'aiNewHint'    => __( 'Ne yazılsın? Ör. "Firma X\'e fidan dikim işbirliği teklifi, kısa ve sıcak"', 'flx-mail' ),
			'aiWorking'    => __( 'AI yazıyor…', 'flx-mail' ),
			'aiOff'        => __( 'AI için Flux AI & MCP eklentisi ve anahtarı gerekir.', 'flx-mail' ),
			'aiDraft'      => __( 'AI taslağı: kontrol edip düzenleyin, sonra gönderin. Siz göndermeden gitmez.', 'flx-mail' ),
			'markUnread'   => __( 'Okunmadı yap', 'flx-mail' ),
			'star'         => __( 'Yıldızla', 'flx-mail' ),
			'from'         => __( 'Kimden', 'flx-mail' ),
			'to'           => __( 'Kime', 'flx-mail' ),
			'cc'           => __( 'Bilgi (Cc)', 'flx-mail' ),
			'subject'      => __( 'Konu', 'flx-mail' ),
			'layout'       => __( 'Şablon', 'flx-mail' ),
			'send'         => __( 'Gönder', 'flx-mail' ),
			'sending'      => __( 'Gönderiliyor…', 'flx-mail' ),
			'sentOk'       => __( 'Gönderildi', 'flx-mail' ),
			'save'         => __( 'Kaydet', 'flx-mail' ),
			'saved'        => __( 'Kaydedildi', 'flx-mail' ),
			'preview'      => __( 'Önizle', 'flx-mail' ),
			'delete'       => __( 'Sil', 'flx-mail' ),
			'confirmDel'   => __( 'Silinsin mi?', 'flx-mail' ),
			/* translators: %s: alıcılar */
			'confirmSend'  => __( 'Bu e-posta şimdi gönderilsin mi? Kime: %s', 'flx-mail' ),
			'replyingTo'   => __( 'Cevaplanan', 'flx-mail' ),
			'quoteNote'    => __( 'Eski yazışma e-postanın altına otomatik eklenir.', 'flx-mail' ),
			'images'       => __( 'Dış görselleri göster', 'flx-mail' ),
			'imagesBlocked' => __( 'Gizlilik için dış görseller gösterilmedi.', 'flx-mail' ),
			'thread'       => __( 'Bu yazışma', 'flx-mail' ),
			'attachments'  => __( 'Ekler', 'flx-mail' ),
			'back'         => __( 'Geri', 'flx-mail' ),
			'noSubject'    => __( '(konu yok)', 'flx-mail' ),
			'me'           => __( 'ben', 'flx-mail' ),
			'ai'           => 'AI',
			'sys'          => __( 'Sistem', 'flx-mail' ),
			'replied'      => __( 'Cevaplandı', 'flx-mail' ),
			'error'        => __( 'Hata', 'flx-mail' ),
			'bold'         => __( 'Kalın', 'flx-mail' ),
			'italic'       => __( 'İtalik', 'flx-mail' ),
			'link'         => __( 'Bağlantı', 'flx-mail' ),
			'linkUrl'      => __( 'Bağlantı adresi:', 'flx-mail' ),
			'list'         => __( 'Liste', 'flx-mail' ),
			'clear'        => __( 'Biçimi temizle', 'flx-mail' ),
			'logView'      => __( 'Kayıtlarda aç', 'flx-mail' ),
			// Şablonlar
			'layoutsIntro' => __( 'Posta kutusundan gönderirken seçilen şablonlar. "Örnek Vakfı × Firma" gibi alt şablonlarda firmanın logosu üstte görünür; hazır konu ve içerik yeni e-postaya dolar.', 'flx-mail' ),
			'newLayout'    => __( 'Yeni alt şablon', 'flx-mail' ),
			'builtin'      => __( 'Hazır şablon (değiştirilemez)', 'flx-mail' ),
			'lName'        => __( 'Şablon adı', 'flx-mail' ),
			'lDesc'        => __( 'Kısa açıklama', 'flx-mail' ),
			'lBase'        => __( 'Görünüm', 'flx-mail' ),
			'lBaseCard'    => __( 'Kurumsal (kart)', 'flx-mail' ),
			'lBaseLetter'  => __( 'Sade (mektup)', 'flx-mail' ),
			'lPartner'     => __( 'Firma adı', 'flx-mail' ),
			'lLogo'        => __( 'Firma logosu', 'flx-mail' ),
			'lLogoPick'    => __( 'Logo seç', 'flx-mail' ),
			'lUrl'         => __( 'Firma web sitesi', 'flx-mail' ),
			'lAccent'      => __( 'Vurgu rengi', 'flx-mail' ),
			'lHeading'     => __( 'Başlık (kurumsalda büyük başlık)', 'flx-mail' ),
			'lSubject'     => __( 'Hazır konu', 'flx-mail' ),
			'lBody'        => __( 'Hazır içerik', 'flx-mail' ),
			'useLayout'    => __( 'Bu şablonla yeni e-posta', 'flx-mail' ),
			'builtinShort' => __( 'Hazır', 'flx-mail' ),
			'aiShort'      => __( 'AI', 'flx-mail' ),
			'close'        => __( 'Kapat', 'flx-mail' ),
			'previewTitle' => __( 'Önizleme', 'flx-mail' ),
			'desktop'      => __( 'Masaüstü', 'flx-mail' ),
			'phone'        => __( 'Telefon', 'flx-mail' ),
			'dark'         => __( 'Koyu', 'flx-mail' ),
			'leave'        => __( 'Kaydedilmemiş değişiklik var. Yine de kapatılsın mı?', 'flx-mail' ),
		),
	);
}

function flxi_page_mailbox() {
	flxi_page_head( __( 'Posta kutusu', 'flx-mail' ), __( 'bilgi@ adresine gelen ve giden e-postalar, taslaklar ve AI cevapları.', 'flx-mail' ), false );
	echo '<div class="flxm" data-flxm><p class="flxi-muted">' . esc_html__( 'Yükleniyor…', 'flx-mail' ) . '</p></div>';
	flxi_page_foot();
}
