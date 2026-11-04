<?php
/**
 * Ortak kişi: başka eklentiler (Flux Donate bağışçı sayfası, Flux Commerce müşteri sayfası, Flux Chat…) kendi kişi
 * sayfalarında CRM kişi kartını gösterir. Kişi tek yerde tutulur; eklentinin kendi tablosu kişiye `link` ile bağlıdır.
 *
 * Kullanım (Flux CRM yoksa ya da kullanıcının CRM yetkisi yoksa false döner, eklenti kendi sayfasını gösterir):
 *
 *     if ( function_exists( 'flxcrm_person_card' ) ) {
 *         $cid = flxcrm_contact_for( array( 'link' => array( 'donate', 'donor:' . $id ), 'email' => $email, 'phone' => $phone ) );
 *         if ( $cid && flxcrm_person_card( $cid, array( 'context' => 'donate', 'base' => $this_page_url, 'back' => array( $list_url, 'Bağışçılar' ) ) ) ) {
 *             return;
 *         }
 *     }
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Kişiyi bulur: kaynak bağlantısı, kullanıcı, e-posta, telefon sırasıyla.
 *
 * @param array $who link [kaynak, kimlik], user_id, email, phone.
 * @return int Kişi id'si ya da 0.
 */
function flxcrm_contact_for( array $who ) {
	return (int) FLXCRM_Contacts::find( $who );
}

/**
 * Kişiyi bulur, yoksa oluşturur (kaynak veri yalnız boş alanları doldurur, elle girilen bilgi ezilmez).
 *
 * @param array $data name, email, phone, user_id, city, company, roles[], source, link [kaynak, kimlik].
 * @return int Kişi id'si ya da 0 (e-posta/telefon/bağlantı yoksa).
 */
function flxcrm_ensure_contact( array $data ) {
	return (int) FLXCRM_Contacts::upsert( $data );
}

/** Kişi kartının stil ve betiği. Kartı gösteren sayfa admin_enqueue_scripts'te çağırırsa stil başta yüklenir. */
function flxcrm_person_card_enqueue() {
	if ( is_admin() && class_exists( 'FLXCRM_Admin' ) ) {
		FLXCRM_Admin::enqueue();
	}
}

/**
 * Kişi kartını basar: başlık, özet, sekmeli zaman tüneli, bilgiler, roller, izinler, etiketler, kaynaklar.
 * Eklentiye özel bölümler için: flxcrm_person_card_actions, flxcrm_person_card_main_before, flxcrm_person_card_main,
 * flxcrm_person_card_side eylemleri ( $contact, $context ).
 *
 * @param int   $contact_id Kişi.
 * @param array $args       context (çağıran paket, ör. 'donate'), base (bu sayfanın adresi; sekmeler ve formlar
 *                          buraya döner), back ([adres, ad] ya da false).
 * @return bool Basıldıysa true.
 */
function flxcrm_person_card( $contact_id, array $args = array() ) {
	if ( ! is_admin() || ! class_exists( 'FLXCRM_Page_Contact' ) || ! flxcrm_can() ) {
		return false;
	}
	$c = FLXCRM_Contacts::get( (int) $contact_id );
	if ( ! $c ) {
		return false;
	}
	$args = wp_parse_args( $args, array( 'context' => 'external', 'back' => false ) );
	if ( empty( $args['base'] ) ) {
		$args['base'] = remove_query_arg( array( 'tl', 'flxcrm_msg', 'flxcrm_err' ), set_url_scheme( ( is_ssl() ? 'https://' : 'http://' ) . ( $_SERVER['HTTP_HOST'] ?? '' ) . ( $_SERVER['REQUEST_URI'] ?? '' ) ) ); // phpcs:ignore WordPress.Security
	}
	flxcrm_person_card_enqueue();
	FLXCRM_Page_Contact::card( $c, $args );
	return true;
}
