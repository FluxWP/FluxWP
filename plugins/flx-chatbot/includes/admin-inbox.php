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
			'open'        => __( 'Açık', 'flx-chatbot' ),
			'all'         => __( 'Tümü', 'flx-chatbot' ),
			'bot'         => __( 'Asistanda', 'flx-chatbot' ),
			'closed'      => __( 'Kapalı', 'flx-chatbot' ),
			'search'      => __( 'Ad, e-posta, telefon ya da sayfa ara', 'flx-chatbot' ),
			'empty'       => __( 'Bu görünümde sohbet yok.', 'flx-chatbot' ),
			'pick'        => __( 'Soldan bir sohbet seçin.', 'flx-chatbot' ),
			'loading'     => __( 'Yükleniyor…', 'flx-chatbot' ),
			'placeholder' => __( 'Cevabınızı yazın… (Enter gönderir, Shift+Enter yeni satır)', 'flx-chatbot' ),
			'send'        => __( 'Gönder', 'flx-chatbot' ),
			'attach'      => __( 'Dosya ekle', 'flx-chatbot' ),
			'close'       => __( 'Sohbeti kapat', 'flx-chatbot' ),
			'delete'      => __( 'Sil', 'flx-chatbot' ),
			'confirmDel'  => __( 'Bu sohbet ve dosyaları kalıcı olarak silinsin mi?', 'flx-chatbot' ),
			'confirmEnd'  => __( 'Sohbet kapatılsın mı? Ziyaretçi yeni mesaj yazarsa yeniden açılır.', 'flx-chatbot' ),
			'fluxChat'    => __( 'Flux Chat\'te aç', 'flx-chatbot' ),
			'inFluxChat'  => __( 'Bu sohbet Flux Chat gelen kutusuna da düştü; iki yerden yazılan cevaplar ziyaretçiye gider.', 'flx-chatbot' ),
			'visitor'     => __( 'Ziyaretçi', 'flx-chatbot' ),
			'you'         => __( 'Siz', 'flx-chatbot' ),
			'online'      => __( 'Sayfada', 'flx-chatbot' ),
			'away'        => __( 'Ayrıldı', 'flx-chatbot' ),
			'typing'      => __( 'yazıyor…', 'flx-chatbot' ),
			'page'        => __( 'Sayfa', 'flx-chatbot' ),
			'email'       => __( 'E-posta', 'flx-chatbot' ),
			'phone'       => __( 'Telefon', 'flx-chatbot' ),
			'member'      => __( 'Üye', 'flx-chatbot' ),
			'order'       => __( 'Sipariş', 'flx-chatbot' ),
			'rating'      => __( 'Puan', 'flx-chatbot' ),
			'summary'     => __( 'Asistanın notu', 'flx-chatbot' ),
			'lead'        => __( 'Potansiyel müşteri', 'flx-chatbot' ),
			'assigned'    => __( 'Temsilci', 'flx-chatbot' ),
			'error'       => __( 'Bir hata oluştu. Tekrar deneyin.', 'flx-chatbot' ),
			'st_bot'      => __( 'Asistanda', 'flx-chatbot' ),
			'st_queued'   => __( 'Temsilci bekliyor', 'flx-chatbot' ),
			'st_agent'    => __( 'Görüşülüyor', 'flx-chatbot' ),
			'st_offline'  => __( 'Mesaj bıraktı', 'flx-chatbot' ),
			'st_closed'   => __( 'Kapalı', 'flx-chatbot' ),
			'more'        => __( 'Daha fazla', 'flx-chatbot' ),
			'back'        => __( 'Listeye dön', 'flx-chatbot' ),
			'details'     => __( 'Ayrıntılar', 'flx-chatbot' ),
			'products'    => __( 'Önerilen ürünler', 'flx-chatbot' ),
		);
	}

	public static function render() {
		if ( ! flxcb_can_reply() ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-chatbot' ), 403 );
		}
		$fc = 'flx-chat' === flxcb_handoff_target();
		echo '<div class="wrap flxcb flxcb-inbox-wrap">';
		echo '<div class="flxcb-head"><div><h1>' . esc_html__( 'Site sohbetleri', 'flx-chatbot' ) . '</h1><p class="flxcb-lead">' . esc_html__( 'Sitenizdeki sohbet penceresinden gelen konuşmalar. Asistanın cevapladıkları da burada; temsilci isteyenler üstte.', 'flx-chatbot' ) . '</p></div>';
		echo '<div class="flxcb-head__actions">';
		if ( $fc ) {
			echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=flx-chat' ) ) . '">' . flxcb_icon( 'chat', 14 ) . esc_html__( 'Flux Chat', 'flx-chatbot' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		if ( current_user_can( 'manage_options' ) ) {
			echo '<a class="button" href="' . esc_url( flxcb_admin_url() ) . '">' . flxcb_icon( 'settings', 14 ) . esc_html__( 'Ayarlar', 'flx-chatbot' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div></div>';
		echo '<div class="flxcb-inbox" data-flxcb-inbox>';
		echo '<aside class="flxcb-ilist"><div class="flxcb-ilist__top"><div class="flxcb-seg" role="tablist" data-filters></div><input type="search" class="flxcb-isearch" data-search></div><ul class="flxcb-iitems" data-items></ul></aside>';
		echo '<section class="flxcb-iconv" data-conv><div class="flxcb-iempty">' . flxcb_icon( 'chat', 28 ) . '<p data-pick></p></div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div></div>';
	}
}
