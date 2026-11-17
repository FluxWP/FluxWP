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

/**
 * Registers a block another package adds to the person card over GraphQL.
 *
 * The classic hooks (flxcrm_person_card_actions, _main_before, _main, _side) keep working. A package that
 * moves to GraphQL calls this and sets `replaces` to the hook it used to print, so the card does not show
 * the same block twice. The resolver must load its own data: it receives the contact, not a request static.
 *
 *     flxcrm_register_person_section( array(
 *         'id'        => 'donations',
 *         'package'   => 'flx-donate',
 *         'title'     => __( 'Donations', 'flx-donate' ),
 *         'placement' => 'main',          // actions | main_before | main | side
 *         'replaces'  => 'flxcrm_person_card_main',
 *         'cap'       => 'flxcrm_view',   // capability, or fn( FLXCRM_Contact $c ): bool. Default: view.
 *         'resolve'   => function ( FLXCRM_Contact $c, string $context ) {
 *             return array( 'blocks' => array(
 *                 array( 'kind' => 'note', 'text' => '…' ),
 *             ) );
 *         },
 *     ) );
 *
 * `resolve` returns null to skip the section, a list of blocks, or array{ title?: string, blocks: array }.
 * Block kinds: stats, links, list, table, note, html, actions.
 *   stats|links|list|actions → items: [ { label, value, url, meta, primary } ]
 *   table → columns: [string], rows: [ { cells: [string], url, label, meta } ]
 *   note|html → text. html strips <script>/<style> and runs wp_kses_post on the wire; forms/scripts do not work in React or FluxApp, so owners should move to typed blocks. Item/row urls allow http, https, mailto, tel and relative admin URLs (others become '').
 * Duplicate ids are not merged (use a unique id per package); a `replaces` value for an unused hook is ignored; a throwing resolver is dropped; check `function_exists( 'flxcrm_register_person_section' )` when CRM may be inactive. A section whose cap fails is left out without failing the card.
 *
 * @param array<string,mixed> $section Section.
 */
function flxcrm_register_person_section( array $section ): void {
	$id = sanitize_key( (string) ( $section['id'] ?? '' ) );
	if ( '' === $id || ! is_callable( $section['resolve'] ?? null ) ) {
		return;
	}
	$section['id'] = $id;
	add_filter(
		'flxcrm_gql_person_sections',
		static function ( $list ) use ( $section ) {
			$list   = is_array( $list ) ? $list : array();
			$list[] = $section;
			return $list;
		}
	);
}
