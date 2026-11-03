<?php
/**
 * Ayarlar: gönderen, gönderim yöntemi (SMTP), görünüm, Google ile giriş, WooCommerce, kayıt, DNS.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

function flxi_page_settings() {
	$s = flxi_settings();
	$b = flxi_brand();
	flxi_page_head( __( 'Ayarlar', 'flx-mail' ), __( 'Gönderen adı ve adresleri, gönderim yöntemi, e-postaların görünümü.', 'flx-mail' ) );
	$n = function ( $k ) {
		return 'flxi[' . $k . ']';
	};
	$text = function ( $k, $label, $help = '', $type = 'text', $ph = '' ) use ( $s, $n ) {
		echo '<label class="flxi-field"><span>' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $n( $k ) ) . '" value="' . esc_attr( is_array( $s[ $k ] ) ? '' : $s[ $k ] ) . '" placeholder="' . esc_attr( $ph ) . '" data-brand>' . ( $help ? '<small>' . wp_kses_post( $help ) . '</small>' : '' ) . '</label>';
	};

	echo '<nav class="flxi-subnav" aria-label="' . esc_attr__( 'Bölümler', 'flx-mail' ) . '">';
	foreach ( array( 'gonderen' => __( 'Gönderen', 'flx-mail' ), 'smtp' => __( 'Gönderim yöntemi', 'flx-mail' ), 'posta' => __( 'Posta kutusu', 'flx-mail' ), 'test' => __( 'Test', 'flx-mail' ), 'gorunum' => __( 'Görünüm', 'flx-mail' ), 'google' => __( 'Google ile giriş', 'flx-mail' ), 'woocommerce' => 'WooCommerce', 'kayit' => __( 'Kayıt ve toplu gönderim', 'flx-mail' ), 'sinirlar' => __( 'Posta kutusu sınırları', 'flx-mail' ) ) as $id => $l ) {
		echo '<a href="#' . esc_attr( $id ) . '">' . esc_html( $l ) . '</a>';
	}
	echo '</nav>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" autocomplete="off" class="flxi-settings">';
	wp_nonce_field( 'flxi_save_settings' );
	echo '<input type="hidden" name="action" value="flxi_save_settings">';

	// Gönderen
	echo '<section class="flxi-card" id="gonderen"><h2 class="flxi-card__title">' . esc_html__( 'Gönderen', 'flx-mail' ) . '</h2>';
	echo '<p class="flxi-muted">' . esc_html__( 'Gelen kutusunda "WordPress <wordpress@…>" yerine kurumunuzun adı ve adresi görünür. Yanıt beklenen e-postalar bilgi@ adresinden, parola ve bildirim gibi yanıt beklenmeyenler no-reply@ adresinden gider. Hangisinin kullanılacağını her e-postada ayrı seçebilirsiniz.', 'flx-mail' ) . '</p>';
	echo '<div class="flxi-grid2">';
	$text( 'from_name', __( 'Gönderen adı', 'flx-mail' ) );
	$text( 'from_email', __( 'Gönderen adresi', 'flx-mail' ), '', 'email' );
	$text( 'noreply_name', __( 'No-reply adı', 'flx-mail' ) );
	$text( 'noreply_email', __( 'No-reply adresi', 'flx-mail' ), '', 'email' );
	echo '</div>';
	$text( 'reply_to', __( 'Yanıt adresi (isteğe bağlı)', 'flx-mail' ), __( 'Boşsa yanıtlar gönderen adrese gider.', 'flx-mail' ), 'email' );
	echo '<div class="flxi-field">' . flxi_switch( $n( 'force_from' ), $s['force_from'], __( 'Diğer eklentilerin gönderen adresini de bununla değiştir (önerilir; SPF/DKIM için tüm e-postalar aynı alan adından gitmeli)', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '</section>';

	// SMTP
	$other = flxi_other_smtp_plugin();
	echo '<section class="flxi-card" id="smtp"><h2 class="flxi-card__title">' . esc_html__( 'Gönderim yöntemi', 'flx-mail' ) . '</h2>';
	if ( $other ) {
		/* translators: %s: eklenti */
		echo '<div class="flxi-callout flxi-callout--info">' . esc_html( sprintf( __( '%s etkin; e-postaları o gönderiyor ve buradaki SMTP ayarı kullanılmıyor. İkisini aynı anda kullanmayın: birini kapatın.', 'flx-mail' ), $other ) ) . '</div>';
	}
	echo '<div class="flxi-seg flxi-seg--wide" role="radiogroup">';
	printf( '<label><input type="radio" name="%s" value="smtp"%s data-mailer> %s</label>', esc_attr( $n( 'mailer' ) ), checked( $s['mailer'], 'smtp', false ), esc_html__( 'SMTP (önerilen)', 'flx-mail' ) );
	printf( '<label><input type="radio" name="%s" value="php"%s data-mailer> %s</label>', esc_attr( $n( 'mailer' ) ), checked( $s['mailer'], 'php', false ), esc_html__( 'Sunucunun PHP mail()', 'flx-mail' ) );
	echo '</div>';
	echo '<p class="flxi-muted" data-for-mailer="php">' . esc_html__( 'PHP mail() ile giden e-postalarda Gmail "via srv….main-hosting.eu" yazar ve spama düşme riski yüksektir.', 'flx-mail' ) . '</p>';
	echo '<div data-for-mailer="smtp"><div class="flxi-callout"><strong>Hostinger:</strong> ' . esc_html__( 'hPanel › E-postalar › E-posta hesapları\'ndan bilgi@ hesabını oluşturun (no-reply@ için ayrı hesap ya da takma ad). Sunucu smtp.hostinger.com, port 465, SSL; kullanıcı adı e-posta adresinin tamamı.', 'flx-mail' ) . ' <button type="button" class="button button-small" data-preset-hostinger>' . esc_html__( 'Hostinger ayarlarını doldur', 'flx-mail' ) . '</button></div>';
	echo '<div class="flxi-grid3">';
	$text( 'smtp_host', __( 'SMTP sunucusu', 'flx-mail' ), '', 'text', 'smtp.hostinger.com' );
	$text( 'smtp_port', __( 'Port', 'flx-mail' ), '', 'number' );
	echo '<label class="flxi-field"><span>' . esc_html__( 'Şifreleme', 'flx-mail' ) . '</span><select name="' . esc_attr( $n( 'smtp_secure' ) ) . '">';
	foreach ( array( 'ssl' => 'SSL (465)', 'tls' => 'TLS (587)', '' => __( 'Yok', 'flx-mail' ) ) as $v => $l ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $v ), selected( $s['smtp_secure'], $v, false ), esc_html( $l ) );
	}
	echo '</select></label></div>';
	echo '<div class="flxi-field">' . flxi_switch( $n( 'smtp_auth' ), $s['smtp_auth'], __( 'Kimlik doğrulama', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '<div class="flxi-grid2">';
	$text( 'smtp_user', __( 'Kullanıcı adı (bilgi@)', 'flx-mail' ), '', 'text', flxi_setting( 'from_email' ) );
	$pw  = flxi_smtp_password();
	$cst = defined( 'FLXI_SMTP_PASS' );
	echo '<label class="flxi-field"><span>' . esc_html__( 'Şifre', 'flx-mail' ) . '</span><input type="password" name="' . esc_attr( $n( 'smtp_pass' ) ) . '" value="" autocomplete="new-password" placeholder="' . esc_attr( $cst ? __( 'wp-config.php\'de tanımlı', 'flx-mail' ) : ( $pw ? '••••••••' . ' · ' . __( 'değiştirmek için yazın', 'flx-mail' ) : '' ) ) . '"' . disabled( $cst, true, false ) . '><small>' . esc_html__( 'Şifreli saklanır, panelde gösterilmez.', 'flx-mail' ) . '</small></label>';
	$text( 'smtp_nr_user', __( 'No-reply için ayrı kullanıcı (isteğe bağlı)', 'flx-mail' ), __( 'no-reply@ ayrı bir hesapsa. Boşsa no-reply e-postaları da yukarıdaki hesapla gider (no-reply@, bilgi@ hesabının takma adı olmalı).', 'flx-mail' ), 'text', flxi_setting( 'noreply_email' ) );
	$pw2 = flxi_smtp_password( true );
	echo '<label class="flxi-field"><span>' . esc_html__( 'No-reply şifresi', 'flx-mail' ) . '</span><input type="password" name="' . esc_attr( $n( 'smtp_nr_pass' ) ) . '" value="" autocomplete="new-password" placeholder="' . esc_attr( $pw2 ? '••••••••' . ' · ' . __( 'değiştirmek için yazın', 'flx-mail' ) : '' ) . '"></label>';
	echo '</div>';
	foreach ( flxi_smtp_account_notes() as $note ) {
		echo '<div class="flxi-callout flxi-callout--' . esc_attr( $note['level'] ) . '">' . esc_html( $note['text'] ) . '</div>';
	}
	echo '</div>';
	echo '<div class="flxi-field">' . flxi_switch( $n( 'set_sender' ), $s['set_sender'], __( 'Return-Path\'i gönderen adresi yap (SPF uyumu için önerilir)', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '</section>';

	// Posta kutusu (IMAP)
	$ipw = defined( 'FLXI_IMAP_PASS' ) ? 'const' : ( '' !== flxi_decrypt( $s['imap_pass'] ) ? 'own' : '' );
	echo '<section class="flxi-card" id="posta"><h2 class="flxi-card__title">' . esc_html__( 'Posta kutusu', 'flx-mail' ) . '</h2>';
	echo '<p class="flxi-muted">' . esc_html__( 'bilgi@ adresine gelen e-postalar ve web postadan gönderilenler sol menüdeki Posta kutusu ekranında görünür; oradan cevap yazılır. Sunucuya IMAP ile bağlanılır, birkaç dakikada bir az sayıda e-posta alınır (paylaşımlı sunucuyu yormaz). Ekler sitede saklanmaz, açınca sunucudan okunur.', 'flx-mail' ) . '</p>';
	echo '<div class="flxi-field">' . flxi_switch( $n( 'mailbox_enabled' ), $s['mailbox_enabled'], __( 'Posta kutusunu aç', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '<div class="flxi-grid3">';
	$text( 'imap_host', __( 'IMAP sunucusu', 'flx-mail' ), '', 'text', 'imap.hostinger.com' );
	$text( 'imap_port', __( 'Port', 'flx-mail' ), '', 'number' );
	echo '<label class="flxi-field"><span>' . esc_html__( 'Şifreleme', 'flx-mail' ) . '</span><select name="' . esc_attr( $n( 'imap_secure' ) ) . '">';
	foreach ( array( 'ssl' => 'SSL (993)', 'tls' => 'STARTTLS (143)', '' => __( 'Yok', 'flx-mail' ) ) as $v => $l ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $v ), selected( $s['imap_secure'], $v, false ), esc_html( $l ) );
	}
	echo '</select></label></div>';
	echo '<div class="flxi-grid2">';
	$text( 'imap_user', __( 'Kullanıcı adı', 'flx-mail' ), __( 'Boşsa SMTP kullanıcısı (bilgi@) kullanılır.', 'flx-mail' ), 'text', $s['smtp_user'] ? $s['smtp_user'] : $s['from_email'] );
	echo '<label class="flxi-field"><span>' . esc_html__( 'Şifre', 'flx-mail' ) . '</span><input type="password" name="' . esc_attr( $n( 'imap_pass' ) ) . '" value="" autocomplete="new-password" placeholder="' . esc_attr( 'const' === $ipw ? __( 'wp-config.php\'de tanımlı', 'flx-mail' ) : ( 'own' === $ipw ? '••••••••' . ' · ' . __( 'değiştirmek için yazın', 'flx-mail' ) : __( 'Boşsa SMTP şifresi kullanılır', 'flx-mail' ) ) ) . '"' . disabled( 'const' === $ipw, true, false ) . '><small>' . esc_html__( 'Şifreli saklanır, panelde gösterilmez.', 'flx-mail' ) . '</small></label>';
	$text( 'mailbox_days', __( 'İlk kurulumda kaç günlük e-posta alınsın', 'flx-mail' ), '', 'number' );
	$text( 'mailbox_keep_days', __( 'Sitede kaç gün saklansın', 'flx-mail' ), __( 'Sunucudaki e-postalar silinmez; yalnız sitedeki kopya.', 'flx-mail' ), 'number' );
	echo '</div>';
	echo '<label class="flxi-field"><span>' . esc_html__( 'İmza (isteğe bağlı)', 'flx-mail' ) . '</span><textarea name="' . esc_attr( $n( 'mailbox_signature' ) ) . '" rows="3" placeholder="{ad_soyad}">' . esc_textarea( $s['mailbox_signature'] ) . '</textarea><small>' . esc_html__( 'Posta kutusundan yazılan e-postaların sonuna eklenir; altına logolu kurum bilgisi gelir. {ad_soyad} yazan kişinin adı olur. Boşsa hiçbir şey eklenmez.', 'flx-mail' ) . '</small></label>';
	echo '<div class="flxi-inline"><button type="button" class="button" data-mailbox-test>' . esc_html__( 'Bağlantıyı dene', 'flx-mail' ) . '</button><span class="flxi-status" aria-live="polite"></span></div>';
	echo '<p class="flxi-muted">' . esc_html__( 'Önce kaydedin, sonra deneyin.', 'flx-mail' ) . '</p>';
	echo '</section>';

	echo '<div class="flxi-sticky"><button class="button button-primary button-large">' . esc_html__( 'Ayarları kaydet', 'flx-mail' ) . '</button></div>';

	// Test + DNS
	$last = get_option( 'flxi_last_test' );
	$dns  = get_option( 'flxi_dns_check' );
	echo '<section class="flxi-card" id="test"><h2 class="flxi-card__title">' . esc_html__( 'Test', 'flx-mail' ) . '</h2>';
	echo '<p class="flxi-muted">' . esc_html__( 'Önce ayarları kaydedin, sonra test edin.', 'flx-mail' ) . '</p>';
	echo '<div class="flxi-inline"><input type="email" id="flxi-test-to" value="' . esc_attr( wp_get_current_user()->user_email ) . '" aria-label="' . esc_attr__( 'Test adresi', 'flx-mail' ) . '"><select id="flxi-test-sender" aria-label="' . esc_attr__( 'Gönderen', 'flx-mail' ) . '"><option value="info">' . esc_html( $s['from_email'] ) . '</option><option value="noreply">' . esc_html( $s['noreply_email'] ) . '</option></select><button type="button" class="button" data-test-settings>' . esc_html__( 'Test e-postası gönder', 'flx-mail' ) . '</button><span class="flxi-status" aria-live="polite"></span></div>';
	if ( is_array( $last ) ) {
		/* translators: 1: tarih, 2: adres */
		echo '<p class="flxi-muted">' . esc_html( sprintf( __( 'Son test: %1$s · %2$s', 'flx-mail' ), wp_date( 'd.m.Y H:i', (int) $last['at'] ), $last['to'] ) ) . ' · ' . ( $last['ok'] ? '<span class="flxi-ok">' . esc_html__( 'gönderildi', 'flx-mail' ) . '</span>' : '<span class="flxi-err">' . esc_html( $last['error'] ) . '</span>' ) . '</p>';
	}
	echo '<h3 id="dns">' . esc_html__( 'Alan adı kayıtları (SPF / DMARC)', 'flx-mail' ) . '</h3><p class="flxi-muted">' . esc_html__( 'E-postaların spama düşmemesi için alan adının DNS\'inde SPF, DKIM ve DMARC kayıtları olmalı. Hostinger\'da e-posta hesabı açınca SPF ve DKIM genelde kendiliğinden eklenir.', 'flx-mail' ) . '</p>';
	echo '<div class="flxi-inline"><button type="button" class="button" data-dns-check>' . esc_html__( 'DNS\'i kontrol et', 'flx-mail' ) . '</button><span class="flxi-status" aria-live="polite"></span></div><dl class="flxi-dl" data-dns-out>';
	if ( is_array( $dns ) ) {
		echo '<dt>SPF</dt><dd>' . ( $dns['spf'] ? '<code>' . esc_html( $dns['spf'] ) . '</code>' : '<span class="flxi-err">' . esc_html__( 'yok', 'flx-mail' ) . '</span>' ) . '</dd>';
		echo '<dt>DMARC</dt><dd>' . ( $dns['dmarc'] ? '<code>' . esc_html( $dns['dmarc'] ) . '</code>' : '<span class="flxi-muted">' . esc_html__( 'yok', 'flx-mail' ) . '</span>' ) . '</dd>';
		echo '<dt>MX</dt><dd>' . esc_html( implode( ', ', (array) $dns['mx'] ) ) . '</dd>';
	}
	echo '</dl></section>';

	// Görünüm
	echo '<section class="flxi-card" id="gorunum"><h2 class="flxi-card__title">' . esc_html__( 'Görünüm', 'flx-mail' ) . '</h2>';
	echo '<div class="flxi-split"><div>';
	echo '<div class="flxi-field"><span>' . esc_html__( 'Logo', 'flx-mail' ) . '</span><div class="flxi-media" data-media><img src="' . esc_url( $b['logo'] ) . '" alt="" data-media-img><input type="hidden" name="' . esc_attr( $n( 'logo' ) ) . '" value="' . esc_attr( $s['logo'] ) . '" data-media-input data-brand><button type="button" class="button" data-media-pick>' . esc_html__( 'Değiştir', 'flx-mail' ) . '</button><button type="button" class="button-link" data-media-clear>' . esc_html__( 'Varsayılan', 'flx-mail' ) . '</button></div><small>' . esc_html__( 'PNG ya da JPG (e-posta programları SVG göstermez). Varsayılan: temanın logosu.', 'flx-mail' ) . '</small></div>';
	echo '<div class="flxi-field"><span>' . esc_html__( 'Koyu zemin logosu', 'flx-mail' ) . '</span><div class="flxi-media flxi-media--dark" data-media><img src="' . esc_url( $b['logo_white'] ) . '" alt="" data-media-img><input type="hidden" name="' . esc_attr( $n( 'logo_white' ) ) . '" value="' . esc_attr( $s['logo_white'] ) . '" data-media-input data-brand><button type="button" class="button" data-media-pick>' . esc_html__( 'Değiştir', 'flx-mail' ) . '</button><button type="button" class="button-link" data-media-clear>' . esc_html__( 'Varsayılan', 'flx-mail' ) . '</button></div><small>' . esc_html__( 'Koyu modda (Apple Mail, iOS) görünür.', 'flx-mail' ) . '</small></div>';
	echo '<div class="flxi-grid2">';
	$text( 'logo_width', __( 'Logo genişliği (px)', 'flx-mail' ), '', 'number' );
	echo '</div><div class="flxi-grid4">';
	foreach ( array( 'color_primary' => __( 'Ana renk', 'flx-mail' ), 'color_dark' => __( 'Koyu renk', 'flx-mail' ), 'color_bg' => __( 'Arka plan', 'flx-mail' ), 'color_text' => __( 'Başlık rengi', 'flx-mail' ) ) as $k => $l ) {
		echo '<label class="flxi-field flxi-color"><span>' . esc_html( $l ) . '</span><input type="color" name="' . esc_attr( $n( $k ) ) . '" value="' . esc_attr( flxi_hex( $s[ $k ], flxi_theme_color( $k ) ) ) . '" data-brand></label>';
	}
	echo '</div>';
	echo '<h3>' . esc_html__( 'Alt bilgi (kurum)', 'flx-mail' ) . '</h3><p class="flxi-muted">' . esc_html__( 'Boş alanlar SEO & MCP › Kurum ve sosyal ayarlarından gelir.', 'flx-mail' ) . '</p>';
	$text( 'org_name', __( 'Kurum adı', 'flx-mail' ), '', 'text', $b['org'] );
	$text( 'tagline', __( 'Slogan', 'flx-mail' ) );
	$text( 'address', __( 'Adres', 'flx-mail' ), '', 'text', $b['address'] );
	echo '<div class="flxi-grid2">';
	$text( 'phone', __( 'Telefon', 'flx-mail' ), '', 'text', $b['phone'] );
	$text( 'contact_email', __( 'İletişim e-postası', 'flx-mail' ), '', 'email', $b['email'] );
	echo '</div>';
	$text( 'website', __( 'Web sitesi', 'flx-mail' ), '', 'url', home_url( '/' ) );
	echo '<div class="flxi-grid2">';
	foreach ( flxi_social_services() as $k => $l ) {
		echo '<label class="flxi-field"><span>' . esc_html( $l ) . '</span><input type="url" name="' . esc_attr( $n( 'social' ) ) . '[' . esc_attr( $k ) . ']" value="' . esc_attr( $s['social'][ $k ] ?? '' ) . '" placeholder="' . esc_attr( $b['social'][ $k ] ?? 'https://' ) . '" data-brand></label>';
	}
	echo '</div>';
	echo '<label class="flxi-field"><span>' . esc_html__( 'Alt not (isteğe bağlı)', 'flx-mail' ) . '</span><textarea name="' . esc_attr( $n( 'footer_note' ) ) . '" rows="2" data-brand>' . esc_textarea( $s['footer_note'] ) . '</textarea><small>' . esc_html__( 'Ör. dernek kütük numarası ya da KVKK notu. Bütün e-postaların en altında küçük yazıyla görünür.', 'flx-mail' ) . '</small></label>';
	echo '<div class="flxi-field"><span>' . esc_html__( 'Alt kısım çizimi', 'flx-mail' ) . '</span><div class="flxi-arts">';
	foreach ( flxi_footer_arts() as $ak => $al ) {
		$img = 'none' === $ak ? '' : '<img src="' . esc_url( FLXI_URL . 'assets/img/footer/' . $ak . '.png' ) . '" alt="">';
		echo '<label class="flxi-art"><input type="radio" name="' . esc_attr( $n( 'footer_art' ) ) . '" value="' . esc_attr( $ak ) . '"' . checked( $s['footer_art'], $ak, false ) . ' data-brand><span class="flxi-art__img">' . $img . '</span><span>' . esc_html( $al ) . '</span></label>'; // phpcs:ignore
	}
	echo '</div><small>' . esc_html__( 'Bütün e-postaların en altında ağaç silüeti. Koyu modda koyu renkli çizim görünür.', 'flx-mail' ) . '</small></div>';
	echo '<h3>' . esc_html__( 'Görseller', 'flx-mail' ) . '</h3>';
	echo '<div class="flxi-field">' . flxi_switch( $n( 'email_images_fix' ), $s['email_images_fix'], __( 'Görselleri her e-posta programına uygun hale getir (WebP/AVIF → JPG/PNG, tam adres)', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '<div class="flxi-field">' . flxi_switch( $n( 'embed_images' ), $s['embed_images'], __( 'Sitenin görsellerini e-postaya göm (alıcı "görselleri göster" demeden görür)', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '<h3>' . esc_html__( 'Diğer eklentilerin e-postaları', 'flx-mail' ) . '</h3>';
	echo '<div class="flxi-field">' . flxi_switch( $n( 'wrap_plain' ), $s['wrap_plain'], __( 'Düz metin e-postaları kurumsal şablona sar', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '<div class="flxi-field">' . flxi_switch( $n( 'wrap_html' ), $s['wrap_html'], __( 'Basit HTML e-postaları da sar (kendi tam tasarımı olanlara dokunulmaz)', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '</div><div class="flxi-split__preview"><div class="flxi-preview"><div class="flxi-preview__bar"><strong>' . esc_html__( 'Önizleme', 'flx-mail' ) . '</strong><div class="flxi-seg" role="group"><button type="button" class="is-active" data-device="desktop" aria-pressed="true"><span class="dashicons dashicons-desktop"></span><span class="screen-reader-text">' . esc_html__( 'Masaüstü', 'flx-mail' ) . '</span></button><button type="button" data-device="mobile" aria-pressed="false"><span class="dashicons dashicons-smartphone"></span><span class="screen-reader-text">' . esc_html__( 'Telefon', 'flx-mail' ) . '</span></button><button type="button" data-device="dark" aria-pressed="false"><span class="dashicons dashicons-admin-appearance"></span><span class="screen-reader-text">' . esc_html__( 'Koyu mod', 'flx-mail' ) . '</span></button></div></div><div class="flxi-preview__frame"><iframe title="' . esc_attr__( 'E-posta önizlemesi', 'flx-mail' ) . '" data-preview-frame data-brand-preview sandbox="allow-same-origin"></iframe></div><p class="flxi-muted flxi-preview__note">' . esc_html__( 'Görünüm değişiklikleri kaydedince bütün e-postalara uygulanır.', 'flx-mail' ) . '</p></div></div></div>';
	echo '</section>';

	// Google
	$social = flxi_active_social_plugins();
	echo '<section class="flxi-card" id="google"><h2 class="flxi-card__title">' . esc_html__( 'Google ile giriş', 'flx-mail' ) . '</h2>';
	echo '<p class="flxi-muted">' . esc_html__( 'Google e-posta adresini zaten doğruladığı için Google ile kayıt olan kişiye "parolanızı belirleyin / oturum açma bilgileri" e-postası gitmez ve hesabı doğrulanmış sayılır.', 'flx-mail' ) . '</p>';
	/* translators: %s: eklenti listesi */
	echo '<p>' . ( $social ? esc_html( sprintf( __( 'Algılanan eklenti: %s', 'flx-mail' ), implode( ', ', $social ) ) ) : esc_html__( 'Bilinen bir Google giriş eklentisi algılanmadı; Google dönüşü istekten de anlaşılır.', 'flx-mail' ) ) . '</p>';
	echo '<div class="flxi-field">' . flxi_switch( $n( 'social_block_core' ), $s['social_block_core'], __( 'WordPress "oturum açma bilgileri / parola belirleme" e-postasını gönderme', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '<div class="flxi-field">' . flxi_switch( $n( 'social_block_wc' ), $s['social_block_wc'], __( 'WooCommerce "hesabınız oluşturuldu" e-postasını gönderme', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '<div class="flxi-field">' . flxi_switch( $n( 'social_verified' ), $s['social_verified'], __( 'E-postayı doğrulanmış olarak işaretle (doğrulama eklentileri için)', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '</section>';

	// WooCommerce
	echo '<section class="flxi-card" id="woocommerce"><h2 class="flxi-card__title">WooCommerce</h2>';
	if ( class_exists( 'WooCommerce' ) ) {
		echo '<p class="flxi-muted">' . esc_html__( 'WooCommerce e-postaları kendi şablonunu kullanır. Logomuzu, renklerimizi, gönderen adını ve kurumsal alt bilgiyi WooCommerce › Ayarlar › E-postalar\'a tek tıkla uygulayabilirsiniz.', 'flx-mail' ) . '</p>';
		$at = (int) get_option( 'flxi_wc_branded' );
		if ( $at ) {
			/* translators: %s: tarih */
			echo '<p>' . esc_html( sprintf( __( 'Son uygulama: %s', 'flx-mail' ), wp_date( 'd.m.Y H:i', $at ) ) ) . '</p>';
		}
		echo '</form>';
		echo flxi_post_button( 'flxi_wc_brand', array(), __( 'Görünümü WooCommerce\'e uygula', 'flx-mail' ), '', 'button' ); // phpcs:ignore
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxi-settings flxi-settings--tail">';
		wp_nonce_field( 'flxi_save_settings' );
		echo '<input type="hidden" name="action" value="flxi_save_settings"><input type="hidden" name="partial" value="1">';
	} else {
		echo '<p class="flxi-muted">' . esc_html__( 'WooCommerce etkin değil.', 'flx-mail' ) . '</p>';
	}
	echo '</section>';

	// Kayıt
	echo '<section class="flxi-card" id="kayit"><h2 class="flxi-card__title">' . esc_html__( 'Kayıt ve toplu gönderim', 'flx-mail' ) . '</h2>';
	echo '<div class="flxi-field">' . flxi_switch( $n( 'log_enabled' ), $s['log_enabled'], __( 'Giden e-posta ve mesajları kaydet', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '<div class="flxi-field">' . flxi_switch( $n( 'log_body' ), $s['log_body'], __( 'İçeriği de sakla (kayıtta e-postayı görmek için)', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '<div class="flxi-grid2">';
	$text( 'log_days', __( 'Kayıtları kaç gün sakla', 'flx-mail' ), '', 'number' );
	$text( 'bulk_per_run', __( 'Toplu e-postada her turda gönderilecek sayı', 'flx-mail' ), __( 'Hostinger paylaşımlı hostingte saatlik gönderim sınırı vardır; varsayılan 20 (dakikada bir tur).', 'flx-mail' ), 'number' );
	echo '</div>';
	echo '<div class="flxi-field">' . flxi_switch( $n( 'bulk_unsubscribe' ), $s['bulk_unsubscribe'], __( 'Toplu e-postalara "listeden çık" bağlantısı ekle', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	$unsub = flxi_unsubscribed();
	/* translators: %s: sayı */
	echo '<p class="flxi-muted">' . esc_html( sprintf( __( 'Listeden çıkan: %s kişi', 'flx-mail' ), number_format_i18n( count( $unsub ) ) ) ) . '</p>';
	echo '</section>';

	// Posta kutusu sınırları
	echo '<section class="flxi-card" id="sinirlar"><h2 class="flxi-card__title">' . esc_html__( 'Posta kutusu sınırları', 'flx-mail' ) . '</h2>';
	echo '<p class="flxi-muted">' . esc_html__( 'Hostinger e-posta paketinizin sınırları. Gönderim sayacı bunlara göre çalışır; toplu gönderim günlük sınırı aşmaz, kalanlar hak açıldıkça sırayla gider. Sınırlar sık doluyorsa Hostinger\'da üst e-posta paketine geçin ve buradaki değerleri güncelleyin.', 'flx-mail' ) . '</p>';
	echo flxi_quota_meter_html(); // phpcs:ignore
	$full = get_option( 'flxi_quota_full_days', array() );
	$full = is_array( $full ) ? array_filter( $full, function ( $x ) {
		return strtotime( $x ) > time() - 30 * DAY_IN_SECONDS;
	} ) : array();
	if ( $full ) {
		/* translators: %s: number of days */
		echo '<p class="flxi-notice-inline">' . esc_html( sprintf( _n( 'Son 30 günde %s gün günlük sınıra dayanıldı.', 'Son 30 günde %s gün günlük sınıra dayanıldı.', count( $full ), 'flx-mail' ), number_format_i18n( count( $full ) ) ) ) . '</p>';
	}
	echo '<div class="flxi-grid2">';
	$text( 'limit_daily', __( 'Günlük gönderim (24 saatte, posta kutusu başına)', 'flx-mail' ), __( 'Kime + Bilgi + Gizli alıcıların her biri bir sayılır. 0 = sınır yok.', 'flx-mail' ), 'number' );
	$text( 'limit_reserve', __( 'Tekil e-postalara ayrılan pay', 'flx-mail' ), __( 'Toplu gönderim bu kadar hakkı parola sıfırlama, bağış ve sertifika e-postalarına bırakır.', 'flx-mail' ), 'number' );
	$text( 'limit_recipients', __( 'İleti başına alıcı (Kime, Bilgi, Gizli)', 'flx-mail' ), '', 'number' );
	$text( 'limit_size_mb', __( 'Giden e-posta boyutu (MB)', 'flx-mail' ), '', 'number' );
	$text( 'limit_attach_mb', __( 'E-posta eki boyutu (MB)', 'flx-mail' ), '', 'number' );
	$text( 'limit_storage_gb', __( 'Posta kutusu başına depolama (GB)', 'flx-mail' ), '', 'number' );
	$text( 'limit_mailbox_emails', __( 'Posta kutusu başına e-posta', 'flx-mail' ), '', 'number' );
	$text( 'limit_aliases', __( 'Posta kutusu başına takma ad', 'flx-mail' ), '', 'number' );
	$text( 'limit_forwarders', __( 'Posta kutusu yönlendiricileri', 'flx-mail' ), '', 'number' );
	echo '</div>';
	echo '</section>';

	echo '<div class="flxi-actions"><button class="button button-primary button-large">' . esc_html__( 'Ayarları kaydet', 'flx-mail' ) . '</button></div>';
	echo '</form>';
	flxi_page_foot();
}

add_action(
	'admin_post_flxi_save_settings',
	function () {
		flxi_check( 'flxi_save_settings' );
		$in    = (array) wp_unslash( $_POST['flxi'] ?? array() ); // phpcs:ignore
		$d     = flxi_defaults();
		$clean = array();
		// İşaret kutuları formda yoksa kapalıdır (yalnız bu formdaki bölümler için).
		$bools = array( 'force_from', 'set_sender', 'smtp_auth', 'wrap_plain', 'wrap_html', 'email_images_fix', 'embed_images', 'social_block_core', 'social_block_wc', 'social_verified', 'log_enabled', 'log_body', 'bulk_unsubscribe', 'mailbox_enabled' );
		$partial = ! empty( $_POST['partial'] );
		foreach ( $d as $k => $def ) {
			if ( in_array( $k, $bools, true ) ) {
				if ( $partial && ! array_key_exists( $k, $in ) && ! in_array( $k, array( 'log_enabled', 'log_body', 'bulk_unsubscribe' ), true ) ) {
					continue;
				}
				$clean[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
				continue;
			}
			if ( ! array_key_exists( $k, $in ) ) {
				continue;
			}
			if ( in_array( $k, array( 'smtp_pass', 'smtp_nr_pass', 'imap_pass' ), true ) ) {
				$v = trim( (string) $in[ $k ] );
				if ( '' !== $v ) {
					$clean[ $k ] = flxi_encrypt( $v );
				}
				continue;
			}
			try {
				$clean[ $k ] = flxi_sanitize_setting( $k, $in[ $k ] );
			} catch ( Exception $e ) {
				flxi_redirect( flxi_admin_url( 'flxi-ayarlar' ), '', $e->getMessage() );
			}
		}
		foreach ( array( 'from_name', 'noreply_name' ) as $k ) {
			if ( isset( $clean[ $k ] ) && '' === $clean[ $k ] ) {
				$clean[ $k ] = $d[ $k ];
			}
		}
		foreach ( array( 'from_email', 'noreply_email' ) as $k ) {
			if ( isset( $clean[ $k ] ) && '' === $clean[ $k ] ) {
				$clean[ $k ] = $d[ $k ];
			}
		}
		if ( isset( $clean['log_days'] ) ) {
			$clean['log_days'] = max( 1, min( 3650, (int) $clean['log_days'] ) );
		}
		foreach ( array( 'limit_daily', 'limit_recipients', 'limit_size_mb', 'limit_attach_mb', 'limit_storage_gb', 'limit_mailbox_emails', 'limit_aliases', 'limit_forwarders', 'limit_reserve' ) as $k ) {
			if ( isset( $clean[ $k ] ) ) {
				$clean[ $k ] = max( 0, min( 1000000, (int) $clean[ $k ] ) );
			}
		}
		if ( isset( $clean['limit_reserve'], $clean['limit_daily'] ) && $clean['limit_daily'] ) {
			$clean['limit_reserve'] = min( $clean['limit_reserve'], max( 0, $clean['limit_daily'] - 1 ) );
		}
		if ( isset( $clean['bulk_per_run'] ) ) {
			$clean['bulk_per_run'] = max( 1, min( 500, (int) $clean['bulk_per_run'] ) );
		}
		flxi_update_settings( $clean );
		flxi_redirect( flxi_back( flxi_admin_url( 'flxi-ayarlar' ) ), __( 'Ayarlar kaydedildi.', 'flx-mail' ) );
	}
);

add_action(
	'admin_post_flxi_wc_brand',
	function () {
		flxi_check( 'flxi_wc_brand' );
		flxi_wc_apply_brand();
		flxi_redirect( flxi_admin_url( 'flxi-ayarlar' ) . '#woocommerce', __( 'Logo, renkler, gönderen ve alt bilgi WooCommerce e-postalarına uygulandı.', 'flx-mail' ) );
	}
);
