<?php
/**
 * Yönetim: site sohbetleri gelen kutusu. Yalnız Flux Chat kurulu değilse ya da ayarda "Eklentinin gelen kutusu" seçildiyse
 * görünür; Flux Chat varken tüm sohbetler çağrı merkezinin tek gelen kutusundadır.
 * Ekran ortak React ekranıdır (includes/app.php, GraphQL); klasik sayfa yoktur.
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

final class FLXCB_Inbox {

	public static function render() {
		if ( ! flxcb_can_reply() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-chatbot' ), 403 );
		}
		flxcb_flux_mount();
	}
}
