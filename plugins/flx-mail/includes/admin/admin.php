<?php
/**
 * Yönetim menüsü, ortak parçalar ve form işleyicileri.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/page-templates.php';
require_once __DIR__ . '/page-send.php';
require_once __DIR__ . '/page-log.php';
require_once __DIR__ . '/page-connections.php';
require_once __DIR__ . '/page-settings.php';
require_once __DIR__ . '/page-mailbox.php';

add_action(
	'admin_menu',
	function () {
		$cap = current_user_can( 'manage_options' ) ? 'manage_options' : 'flxi_manage';
		// Posta kutusu her gün açılan bir ekran: WordPress'teki Yorumlar gibi sol menüde ayrı ana öğe.
		$unread = flxi_mailbox_enabled() ? flxi_mailbox_unread_count() + flxi_drafts_count( 'review' ) : 0;
		add_menu_page( __( 'Posta kutusu', 'flx-mail' ), __( 'Posta kutusu', 'flx-mail' ) . ( $unread ? ' <span class="awaiting-mod">' . (int) $unread . '</span>' : '' ), $cap, 'flxi-posta', 'flxi_page_mailbox', 'dashicons-email-alt', 26 );
		add_menu_page( __( 'İletişim', 'flx-mail' ), __( 'İletişim', 'flx-mail' ), $cap, 'flxi', 'flxi_page_templates', 'none', 58 );
		add_submenu_page( 'flxi', __( 'E-posta şablonları', 'flx-mail' ), __( 'E-postalar', 'flx-mail' ), $cap, 'flxi', 'flxi_page_templates' );
		add_submenu_page( 'flxi', __( 'Gönder', 'flx-mail' ), __( 'Gönder', 'flx-mail' ), $cap, 'flxi-gonder', 'flxi_page_send' );
		add_submenu_page( 'flxi', __( 'Kayıtlar', 'flx-mail' ), __( 'Kayıtlar', 'flx-mail' ), $cap, 'flxi-kayitlar', 'flxi_page_log' );
		add_submenu_page( 'flxi', __( 'WhatsApp ve SMS bağlantıları', 'flx-mail' ), __( 'Bağlantılar', 'flx-mail' ), $cap, 'flxi-baglantilar', 'flxi_page_connections' );
		add_submenu_page( 'flxi', __( 'İletişim ayarları', 'flx-mail' ), __( 'Ayarlar', 'flx-mail' ), $cap, 'flxi-ayarlar', 'flxi_page_settings' );
		if ( ! flxi_system_moved() ) {
			add_submenu_page( 'flxi', __( 'Sistem kontrolü', 'flx-mail' ), __( 'Sistem kontrolü', 'flx-mail' ), $cap, 'flxi-sistem', 'flxi_page_system' );
		}
	}
);

/** Sistem kontrolü Flux Speed'ta mı (Performans › Sistem Kontrolü). Öyleyse buradaki ekran gizlenir. */
function flxi_system_moved() {
	return function_exists( 'flxp_system_checks_url' );
}

// Eski adres (yer imi, bildirim linki) Performans'taki ekrana gider. Sayfa kayıtlı olmadığından WordPress
// erişim reddinden hemen önce (admin_page_access_denied) yönlendirilir.
add_action(
	'admin_page_access_denied',
	function () {
		if ( flxi_system_moved() && 'flxi-sistem' === ( $_GET['page'] ?? '' ) ) { // phpcs:ignore
			wp_safe_redirect( flxp_system_checks_url() );
			exit;
		}
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
		if ( 0 !== strpos( $page, 'flxi' ) ) {
			return;
		}
		wp_enqueue_style( 'flxi-admin', FLXI_URL . 'assets/css/admin.css', array(), FLXI_VERSION );
		if ( 'flxi-ayarlar' === $page ) {
			wp_enqueue_media();
		}
		if ( 'flxi-posta' === $page ) {
			wp_enqueue_media();
			wp_enqueue_style( 'flxi-mailbox', FLXI_URL . 'assets/css/mailbox.css', array( 'flxi-admin' ), FLXI_VERSION );
			wp_enqueue_script( 'flxi-mailbox', FLXI_URL . 'assets/js/mailbox.js', array(), FLXI_VERSION, true );
			wp_localize_script( 'flxi-mailbox', 'FLXIMB', flxi_mailbox_boot() );
		}
		wp_enqueue_script( 'flxi-admin', FLXI_URL . 'assets/js/admin.js', array(), FLXI_VERSION, true );
		wp_localize_script(
			'flxi-admin',
			'FLXI',
			array(
				'rest'  => esc_url_raw( rest_url( 'flxi/v1/' ) ),
				'pdf'   => flxi_pdf_available(),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'  => array(
					'sending'   => __( 'Gönderiliyor…', 'flx-mail' ),
					'sent'      => __( 'Gönderildi', 'flx-mail' ),
					/* translators: %s: adres */
					'sentTo'    => __( '%s adresine gönderildi. Gelen kutusunu (ve spam klasörünü) kontrol edin.', 'flx-mail' ),
					'failed'    => __( 'Gönderilemedi', 'flx-mail' ),
					'testing'   => __( 'Deneniyor…', 'flx-mail' ),
					'ok'        => __( 'Başarılı', 'flx-mail' ),
					'error'     => __( 'Hata', 'flx-mail' ),
					/* translators: %s: sayı */
					'recipients' => __( '%s alıcı', 'flx-mail' ),
					'noRecipients' => __( 'Alıcı yok', 'flx-mail' ),
					'chooseLogo' => __( 'Logo seç', 'flx-mail' ),
					'use'       => __( 'Kullan', 'flx-mail' ),
					'confirmSend' => __( 'Gönderim başlasın mı?', 'flx-mail' ),
					'checking'  => __( 'Kontrol ediliyor…', 'flx-mail' ),
					'copied'    => __( 'Kopyalandı', 'flx-mail' ),
					'pdf'       => __( 'PDF indir', 'flx-mail' ),
					'pdfWait'   => __( 'PDF hazırlanıyor…', 'flx-mail' ),
					'test'      => array(
						'title'      => __( 'Test et', 'flx-mail' ),
						'check'      => __( 'Bağlantıyı kontrol et', 'flx-mail' ),
						'checkHelp'  => __( 'Mesaj göndermez; yalnız bağlantıya bakar.', 'flx-mail' ),
						'to'         => __( 'Alıcı numara', 'flx-mail' ),
						'toHelp'     => __( 'Ülke koduyla, ör. 905301234567. Mesajlar bu numaraya gider.', 'flx-mail' ),
						'scenarios'  => __( 'Ne gönderilsin?', 'flx-mail' ),
						'text'       => __( 'Metin', 'flx-mail' ),
						'image'      => __( 'Resim', 'flx-mail' ),
						'audio'      => __( 'Sesli mesaj', 'flx-mail' ),
						'document'   => __( 'Belge (PDF)', 'flx-mail' ),
						'template'   => __( 'Şablon', 'flx-mail' ),
						'message'    => __( 'Mesaj metni', 'flx-mail' ),
						'caption'    => __( 'Resim / belge açıklaması', 'flx-mail' ),
						/* translators: %s: site adı */
						'sampleText' => sprintf( __( 'Merhaba, bu %s test mesajıdır. 🌱', 'flx-mail' ), get_bloginfo( 'name' ) ),
						/* translators: %s: site adı */
						'sampleCap'  => sprintf( __( 'Test görseli · %s', 'flx-mail' ), get_bloginfo( 'name' ) ),
						/* translators: %s: site adı */
						'sampleSms'  => sprintf( __( 'Test SMS · %s', 'flx-mail' ), get_bloginfo( 'name' ) ),
						'preview'    => __( 'Önizleme', 'flx-mail' ),
						'send'       => __( 'Gönder', 'flx-mail' ),
						/* translators: %d: mesaj sayısı */
						'sendN'      => __( '%d mesaj gönder', 'flx-mail' ),
						'needTo'     => __( 'Önce alıcı numarayı yazın.', 'flx-mail' ),
						'needOne'    => __( 'En az bir senaryo seçin.', 'flx-mail' ),
						'notOpen'    => __( 'Numara bağlı değil; mesajlar gitmez. Önce "Bağlan" ile QR okutun.', 'flx-mail' ),
						'window'     => __( '24 saat kuralı: kişi son 24 saatte bu hatta yazmadıysa metin, resim, ses ve belge gitmez; yalnız onaylı şablon gider. Önce o numaradan bu hatta bir mesaj atın.', 'flx-mail' ),
						'tplPick'    => __( 'Şablon seçin', 'flx-mail' ),
						'tplLoad'    => __( 'Şablonlar yükleniyor…', 'flx-mail' ),
						'tplNone'    => __( 'Onaylı şablon yok.', 'flx-mail' ),
						/* translators: %d: değişken sırası */
						'tplParam'   => __( 'Değişken {{%d}}', 'flx-mail' ),
						'smsNote'    => __( 'SMS ücretlidir; her gönderim bakiyeden düşer.', 'flx-mail' ),
						'pdfName'    => 'test-belgesi.pdf',
						'pdfMeta'    => __( '1 sayfa · PDF', 'flx-mail' ),
						'sending'    => __( 'Gönderiliyor…', 'flx-mail' ),
						'delivered'  => __( 'Gönderildi', 'flx-mail' ),
						/* translators: 1: başarılı, 2: toplam */
						'summary'    => __( '%1$d / %2$d gönderildi.', 'flx-mail' ),
						'checkPhone' => __( 'Telefonda kontrol edin.', 'flx-mail' ),
						'sms'        => __( 'SMS', 'flx-mail' ),
					),
					'samples'   => array(
						'image' => FLXI_URL . 'assets/test/ornek.jpg',
						'audio' => FLXI_URL . 'assets/test/ornek.ogg',
					),
					'wa'        => array(
						'open'          => __( 'Bağlı', 'flx-mail' ),
						'connecting'    => __( 'QR bekliyor', 'flx-mail' ),
						'close'         => __( 'Bağlı değil', 'flx-mail' ),
						'missing'       => __( 'Sunucuda yok', 'flx-mail' ),
						'unreach'       => __( 'Sunucuya ulaşılamadı', 'flx-mail' ),
						'unknown'       => __( 'Durum bilinmiyor', 'flx-mail' ),
						'wait'          => __( 'Bekleyin…', 'flx-mail' ),
						'scan'          => __( 'QR\'ı telefonla okutun.', 'flx-mail' ),
						'pairHint'      => __( 'Bu kodu telefondaki WhatsApp\'a yazın.', 'flx-mail' ),
						'done'          => __( 'Bağlandı.', 'flx-mail' ),
						'okDone'        => __( 'Tamam', 'flx-mail' ),
						'confirmLogout' => __( 'Numaranın oturumu kapatılsın mı? Mesaj gelmez; yeniden bağlamak için QR okutmak gerekir.', 'flx-mail' ),
						'version'       => __( 'Sürüm', 'flx-mail' ),
						'store'         => __( 'Veri deposu', 'flx-mail' ),
						'file'          => __( 'Dosya', 'flx-mail' ),
						'lastKa'        => __( 'Son uyanık tutma', 'flx-mail' ),
						'never'         => __( 'henüz yok', 'flx-mail' ),
						'voice'         => __( 'Sesli mesaj çevirici', 'flx-mail' ),
						'yes'           => __( 'Var', 'flx-mail' ),
						'no'            => __( 'Yok', 'flx-mail' ),
						'publicUrl'     => __( 'Köprünün dış adresi', 'flx-mail' ),
						'publicUrlHelp' => __( 'Uyanık tutma bu adrese istek atar. Ör. https://example.org', 'flx-mail' ),
						'keepAlive'     => __( 'Uyanık tut (Hostinger uygulamayı uyutmasın)', 'flx-mail' ),
						'db'            => __( 'Veritabanı', 'flx-mail' ),
						'dbLocked'      => __( 'Veritabanı köprünün ortam değişkenlerinden geliyor; buradan değiştirilemez.', 'flx-mail' ),
						'driver'        => __( 'Depo', 'flx-mail' ),
						'host'          => __( 'Sunucu', 'flx-mail' ),
						'dbName'        => __( 'Veritabanı adı', 'flx-mail' ),
						'dbUser'        => __( 'Kullanıcı', 'flx-mail' ),
						'dbPass'        => __( 'Parola', 'flx-mail' ),
						'passKeep'      => __( 'Kayıtlı · değiştirmek için yazın', 'flx-mail' ),
						'prefix'        => __( 'Tablo öneki', 'flx-mail' ),
						'dbWarn'        => __( 'Parola yalnız köprüye gönderilir, WordPress\'te saklanmaz.', 'flx-mail' ),
						'dbSwitch'      => __( 'Depo değişince numaraların bağlantısı kapanır ve yeniden QR okutmak gerekir. Devam edilsin mi?', 'flx-mail' ),
						'dbTest'        => __( 'Veritabanını dene', 'flx-mail' ),
						'save'          => __( 'Kaydet', 'flx-mail' ),
						'copy'          => __( 'Kopyala', 'flx-mail' ),
						'quality'       => __( 'Numara kalitesi', 'flx-mail' ),
						'qGreen'        => __( 'Yüksek', 'flx-mail' ),
						'qYellow'       => __( 'Orta', 'flx-mail' ),
						'qRed'          => __( 'Düşük', 'flx-mail' ),
						'hookTitle'     => __( 'Webhook (gelen mesajlar)', 'flx-mail' ),
						'hookHelp'      => __( 'Meta for Developers › uygulamanız › WhatsApp › Yapılandırma › Webhook: aşağıdaki adresi ve doğrulama anahtarını girip "Doğrula ve kaydet"e basın, sonra "messages" alanına abone olun.', 'flx-mail' ),
						'callback'      => __( 'Geri çağırma adresi (Callback URL)', 'flx-mail' ),
						'verifyTok'     => __( 'Doğrulama anahtarı (Verify token)', 'flx-mail' ),
						'lastVerify'    => __( 'Meta doğrulaması', 'flx-mail' ),
						'lastEvent'     => __( 'Son gelen olay', 'flx-mail' ),
						'badSig'        => __( 'İmzası tutmayan istek', 'flx-mail' ),
						'openChats'     => __( 'Açık 24 saat penceresi', 'flx-mail' ),
						'noSecret'      => __( 'App Secret girilmedi: gelen isteklerin Meta\'dan geldiği doğrulanmıyor. Düzenle\'den ekleyin.', 'flx-mail' ),
						'templates'     => __( 'Onaylı şablonlar', 'flx-mail' ),
						'needWaba'      => __( 'Şablonlar için WABA ID gerekli.', 'flx-mail' ),
						'noTpl'         => __( 'Şablon yok. WhatsApp Manager › Mesaj şablonları\'ndan ekleyin.', 'flx-mail' ),
					),
				),
			)
		);
	}
);

/** Sayfa başlığı + sekme çubuğu. */
function flxi_page_head( $title, $sub = '', $tabs_on = true ) {
	$page  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'flxi'; // phpcs:ignore
	$tabs  = array(
		'flxi'             => __( 'E-postalar', 'flx-mail' ),
		'flxi-gonder'      => __( 'Gönder', 'flx-mail' ),
		'flxi-kayitlar'    => __( 'Kayıtlar', 'flx-mail' ),
		'flxi-baglantilar' => __( 'Bağlantılar', 'flx-mail' ),
		'flxi-ayarlar'     => __( 'Ayarlar', 'flx-mail' ),
		'flxi-sistem'      => __( 'Sistem kontrolü', 'flx-mail' ),
	);
	if ( flxi_system_moved() ) {
		unset( $tabs['flxi-sistem'] );
	}
	echo '<div class="wrap flxi-wrap"><div class="flxi-head"><div class="flxi-head__title"><img class="flxi-logo" src="' . esc_url( FLXI_URL . 'assets/icon.svg' ) . '" alt="" width="42" height="42"><div><h1>' . esc_html( $title ) . '</h1>';
	if ( $sub ) {
		echo '<p class="flxi-head__sub">' . wp_kses_post( $sub ) . '</p>';
	}
	echo '</div></div>';
	if ( $tabs_on ) {
		echo '<nav class="flxi-tabs" aria-label="' . esc_attr__( 'İletişim', 'flx-mail' ) . '">';
		foreach ( $tabs as $slug => $label ) {
			printf( '<a href="%s" class="flxi-tab%s"%s>%s</a>', esc_url( flxi_admin_url( $slug ) ), $slug === $page ? ' is-active' : '', $slug === $page ? ' aria-current="page"' : '', esc_html( $label ) );
		}
		echo '</nav>';
	}
	echo '</div>';
	flxi_notices();
}

function flxi_page_foot() {
	echo '</div>';
}

/** İşlem sonrası bildirim (flxi_msg / flxi_err sorgu parametresi). */
function flxi_notices() {
	// phpcs:disable WordPress.Security.NonceVerification
	if ( ! empty( $_GET['flxi_msg'] ) ) {
		echo '<div class="notice notice-success is-dismissible flxi-notice"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['flxi_msg'] ) ) ) . '</p></div>';
	}
	if ( ! empty( $_GET['flxi_err'] ) ) {
		echo '<div class="notice notice-error is-dismissible flxi-notice"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['flxi_err'] ) ) ) . '</p></div>';
	}
	// phpcs:enable
}

function flxi_redirect( $url, $msg = '', $err = '' ) {
	$args = array();
	if ( $msg ) {
		$args['flxi_msg'] = rawurlencode( $msg );
	}
	if ( $err ) {
		$args['flxi_err'] = rawurlencode( $err );
	}
	wp_safe_redirect( add_query_arg( $args, $url ) );
	exit;
}

function flxi_check( $action ) {
	if ( ! flxi_can_manage() ) {
		wp_die( esc_html__( 'Yetkiniz yok.', 'flx-mail' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( $action );
}

function flxi_back( $default ) {
	$ref = wp_get_referer();
	$ref = $ref ? remove_query_arg( array( 'flxi_msg', 'flxi_err' ), $ref ) : $default;
	return $ref;
}

/** Açık/kapalı anahtarı (checkbox). */
function flxi_switch( $name, $on, $label = '', $attrs = '' ) {
	return '<label class="flxi-switch"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( (bool) $on, true, false ) . ' ' . $attrs . '><span class="flxi-switch__track" aria-hidden="true"></span>' . ( $label ? '<span class="flxi-switch__label">' . esc_html( $label ) . '</span>' : '' ) . '</label>';
}

function flxi_badge( $text, $kind = '' ) {
	return '<span class="flxi-badge' . ( $kind ? ' flxi-badge--' . esc_attr( $kind ) : '' ) . '">' . esc_html( $text ) . '</span>';
}

function flxi_sender_label( $sender ) {
	return 'noreply' === $sender ? flxi_setting( 'noreply_email' ) : flxi_setting( 'from_email' );
}

/* ------------------------------------------------------------------ */
/* Sistem kontrolü sayfası                                             */
/* ------------------------------------------------------------------ */

function flxi_page_system() {
	flxi_page_head( __( 'Sistem kontrolü', 'flx-mail' ), __( 'E-posta, WhatsApp ve SMS ile ilgili satırlar. Tüm eklentilerin kontrolleri Görünüm › Sistem Kontrolü ekranında.', 'flx-mail' ) );
	add_filter( 'flx_system_checks_labels', 'flxi_system_checks_labels', 20 );
	$all  = function_exists( 'flx_system_checks_sorted' ) ? flx_system_checks_sorted() : array();
	$mine = array_values(
		array_filter(
			$all,
			function ( $c ) {
				return 0 === strpos( (string) $c['id'], 'flxi.' );
			}
		)
	);
	echo '<div class="flxi-card">';
	if ( function_exists( 'flx_system_checks_render' ) ) {
		flx_system_checks_render( array( 'checks' => $mine, 'recheck_url' => flxi_admin_url( 'flxi-sistem' ), 'heading' => false ) );
	}
	echo '</div>';
	flxi_page_foot();
}

/** Renkli menü ikonu (WordPress menü ikonlarını tek renge boyadığı için CSS ile). */
add_action(
	'admin_head',
	function () {
		echo '<style>#toplevel_page_flxi .wp-menu-image{background:url(' . esc_url( FLXI_URL . 'assets/icon.svg' ) . ') center/20px 20px no-repeat}</style>';
	}
);
